/**
 * DeSpace M2 Catalog Search & Multi-Criteria Filter Verification Suite (Issue #13)
 *
 * Usage:
 *   node tests/test-m2-catalog-filter.cjs               # Full Issue #13 suite
 *   node tests/test-m2-catalog-filter.cjs --tax-only    # Focused brand (product_brand) & size (pa_size) check
 *   node tests/test-m2-catalog-filter.cjs --size-only   # Alias for --tax-only
 *
 * Environment variables:
 *   WP_BASE_URL       (default: http://localhost:8090)
 *   DOCKER_CONTAINER  (default: despace-wordpress)
 */

const { chromium } = require('playwright-core');
const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

const BASE_URL = process.env.WP_BASE_URL || 'http://localhost:8090';
const DOCKER_CONTAINER = process.env.DOCKER_CONTAINER || 'despace-wordpress';
const TAX_ONLY = process.argv.includes('--tax-only') || process.argv.includes('--size-only');
const SUITE_TIMEOUT_MS = TAX_ONLY ? 50000 : 95000;

const VAR_FIXTURE_PATH = path.resolve(__dirname, 'fixtures/m2-variation-fixture.php');
const FILTER_FIXTURE_PATH = path.resolve(__dirname, 'fixtures/m2-catalog-filter-fixture.php');

function hasActiveDocker() {
  try {
    const out = execSync(`docker ps -q -f name=${DOCKER_CONTAINER}`, {
      stdio: 'pipe',
      encoding: 'utf8',
      timeout: 10000,
    }).trim();
    return out.length > 0;
  } catch (_) {
    return false;
  }
}

function runPhpScript(scriptPath, action) {
  const content = fs.readFileSync(scriptPath, 'utf8');
  let output = '';
  if (hasActiveDocker()) {
    output = execSync(`docker exec -i ${DOCKER_CONTAINER} php -- ${action}`, {
      input: content,
      encoding: 'utf8',
      stdio: ['pipe', 'pipe', 'pipe'],
      timeout: 20000,
    });
  } else {
    output = execSync(`php "${scriptPath}" ${action}`, {
      encoding: 'utf8',
      stdio: ['pipe', 'pipe', 'pipe'],
      timeout: 20000,
    });
  }
  const match = output.match(/\{[\s\S]*\}/);
  if (!match) {
    throw new Error(`Invalid fixture JSON for ${path.basename(scriptPath)} ${action}: ${output}`);
  }
  return JSON.parse(match[0]);
}

function getWcSessionCount() {
  const phpCode =
    "<?php require '/var/www/html/wp-load.php'; global $wpdb; echo (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . $wpdb->prefix . 'woocommerce_sessions');";
  const out = execSync(`docker exec -i ${DOCKER_CONTAINER} php`, {
    input: phpCode,
    encoding: 'utf8',
    stdio: ['pipe', 'pipe', 'pipe'],
    timeout: 15000,
  }).trim();
  return parseInt(out.replace(/\D/g, ''), 10);
}

async function fetchJson(url) {
  const res = await fetch(url);
  const data = await res.json();
  return { status: res.status, data };
}

async function waitFilterIdle(page) {
  await page.waitForSelector('#furniva-shop-results:not(.is-busy)', { timeout: 12000 });
  await page.locator('.f-skeleton-grid').waitFor({ state: 'detached', timeout: 12000 });
}

async function openDetails(page, filterName) {
  const det = page.locator(`#furniva-filter-form details[data-filter-name="${filterName}"]`);
  const isOpen = await det.evaluate(el => el.open);
  if (!isOpen) {
    await det.locator('summary').click();
  }
}

async function main() {
  const startedAt = Date.now();
  const report = {
    mode: TAX_ONLY ? 'tax-only' : 'full',
    baseUrl: BASE_URL,
    durationMs: 0,
    failedStep: null,
    sessions: { before: null, after: null, unchanged: null },
    dbBefore: {},
    dbAfter: {},
    paSize: {},
    productBrand: {},
    fullSuite: {},
    fixtures: {},
    assertions: {},
    errors: [],
  };

  let browser = null;
  let filterFixtureCreated = false;
  let varFixtureCreated = false;
  let currentStep = 'init';

  const hardTimer = setTimeout(async () => {
    report.failedStep = currentStep;
    report.errors.push(`Hard suite timeout (${SUITE_TIMEOUT_MS}ms) reached at step: ${currentStep}`);
    if (browser) {
      try { await browser.close(); } catch (_) {}
    }
    report.durationMs = Date.now() - startedAt;
    console.log(JSON.stringify(report, null, 2));
    process.exit(2);
  }, SUITE_TIMEOUT_MS);

  try {
    currentStep = 'baseline';
    report.sessions.before = getWcSessionCount();
    report.dbBefore = runPhpScript(FILTER_FIXTURE_PATH, 'status');

    // --- 1. Provision temporary pa_size + product_brand fixture ---
    currentStep = 'filterFixture.create';
    const setup = runPhpScript(FILTER_FIXTURE_PATH, 'create');
    filterFixtureCreated = true;
    report.fixtures.filterSetup = setup;

    // --- 2. REST API (/products/filter & /catalog) for size and brand ---
    currentStep = 'rest.sizeAndBrand';
    const restSize = await fetchJson(
      `${BASE_URL}/wp-json/despace/v1/products/filter?size=${encodeURIComponent(setup.size_slug)}`
    );
    const restSizeAlias = await fetchJson(
      `${BASE_URL}/wp-json/despace/v1/catalog?size=${encodeURIComponent(setup.size_slug)}`
    );
    const restBrand = await fetchJson(
      `${BASE_URL}/wp-json/despace/v1/products/filter?brand=${encodeURIComponent(setup.brand_slug)}`
    );
    const restBrandAlias = await fetchJson(
      `${BASE_URL}/wp-json/despace/v1/catalog?brand=${encodeURIComponent(setup.brand_slug)}`
    );
    const restCombined = await fetchJson(
      `${BASE_URL}/wp-json/despace/v1/products/filter?brand=${encodeURIComponent(setup.brand_slug)}&size=${encodeURIComponent(setup.size_slug)}`
    );

    // --- 3. Browser UI AJAX & No-JS GET fallback for size and brand ---
    currentStep = 'browser.launch';
    browser = await chromium.launch({ headless: true, channel: 'chrome' });
    const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const page = await context.newPage();
    page.setDefaultTimeout(12000);
    page.setDefaultNavigationTimeout(15000);

    const pageErrors = [];
    page.on('pageerror', err => pageErrors.push(err.message));

    currentStep = 'ui.sizeAjax';
    await page.goto(`${BASE_URL}/shop/`, { waitUntil: 'networkidle' });
    await openDetails(page, 'size');
    const sizeCheckbox = page.locator(`#furniva-filter-form input[name="size"][value="${setup.size_slug}"]`);
    const sizeCheckboxVisible = await sizeCheckbox.isVisible();
    await Promise.all([
      page.waitForResponse(r => r.url().includes('admin-ajax.php') && r.status() === 200),
      sizeCheckbox.check(),
    ]);
    await waitFilterIdle(page);
    const uiSizeState = await page.evaluate(() => {
      const cards = Array.from(document.querySelectorAll('#furniva-shop-results ul.f-products > li'));
      return {
        url: window.location.search,
        cards: cards.length,
        ids: cards.map(li => {
          const m = (li.className || '').match(/\bpost-(\d+)\b/);
          return m ? Number(m[1]) : null;
        }),
        resultText: document.querySelector('#furniva-result-count')?.textContent?.trim(),
      };
    });

    currentStep = 'ui.brandAjax';
    await Promise.all([
      page.waitForResponse(r => r.url().includes('admin-ajax.php') && r.status() === 200),
      page.locator('#furniva-filter-clear').click(),
    ]);
    await waitFilterIdle(page);

    await openDetails(page, 'brand');
    const brandCheckbox = page.locator(`#furniva-filter-form input[name="brand"][value="${setup.brand_slug}"]`);
    const brandCheckboxVisible = await brandCheckbox.isVisible();
    await Promise.all([
      page.waitForResponse(r => r.url().includes('admin-ajax.php') && r.status() === 200),
      brandCheckbox.check(),
    ]);
    await waitFilterIdle(page);
    const uiBrandState = await page.evaluate(() => {
      const cards = Array.from(document.querySelectorAll('#furniva-shop-results ul.f-products > li'));
      return {
        url: window.location.search,
        cards: cards.length,
        ids: cards.map(li => {
          const m = (li.className || '').match(/\bpost-(\d+)\b/);
          return m ? Number(m[1]) : null;
        }),
        resultText: document.querySelector('#furniva-result-count')?.textContent?.trim(),
      };
    });

    currentStep = 'noJs.sizeAndBrand';
    const noJsContext = await browser.newContext({ javaScriptEnabled: false });
    const noJsPage = await noJsContext.newPage();
    noJsPage.setDefaultTimeout(12000);

    await noJsPage.goto(`${BASE_URL}/shop/?size=${encodeURIComponent(setup.size_slug)}`, {
      waitUntil: 'domcontentloaded',
    });
    const noJsSizeState = await noJsPage.evaluate(() => {
      const cards = Array.from(document.querySelectorAll('#furniva-shop-results ul.f-products > li'));
      return {
        cards: cards.length,
        ids: cards.map(li => {
          const m = (li.className || '').match(/\bpost-(\d+)\b/);
          return m ? Number(m[1]) : null;
        }),
      };
    });

    await noJsPage.goto(`${BASE_URL}/shop/?brand=${encodeURIComponent(setup.brand_slug)}`, {
      waitUntil: 'domcontentloaded',
    });
    const noJsBrandState = await noJsPage.evaluate(() => {
      const cards = Array.from(document.querySelectorAll('#furniva-shop-results ul.f-products > li'));
      return {
        cards: cards.length,
        ids: cards.map(li => {
          const m = (li.className || '').match(/\bpost-(\d+)\b/);
          return m ? Number(m[1]) : null;
        }),
      };
    });
    await noJsContext.close();

    // Clean up size+brand fixture immediately
    currentStep = 'filterFixture.cleanup';
    report.fixtures.filterCleanup = runPhpScript(FILTER_FIXTURE_PATH, 'delete');
    filterFixtureCreated = false;

    report.paSize = {
      termSlug: setup.size_slug,
      fixtureProductId: setup.product_id,
      restCount: restSize.data.pagination?.total_items,
      restMatchedIds: (restSize.data.items || []).map(i => i.id),
      restAliasMatches:
        restSizeAlias.status === 200 &&
        restSizeAlias.data.pagination?.total_items === restSize.data.pagination?.total_items,
      uiCheckboxVisible: sizeCheckboxVisible,
      uiAjax: uiSizeState,
      noJsGet: noJsSizeState,
    };

    report.productBrand = {
      taxonomy: 'product_brand',
      param: 'brand',
      termSlug: setup.brand_slug,
      fixtureProductId: setup.product_id,
      restCount: restBrand.data.pagination?.total_items,
      restMatchedIds: (restBrand.data.items || []).map(i => i.id),
      restAliasMatches:
        restBrandAlias.status === 200 &&
        restBrandAlias.data.pagination?.total_items === restBrand.data.pagination?.total_items,
      restCombinedBrandAndSizeCount: restCombined.data.pagination?.total_items,
      uiCheckboxVisible: brandCheckboxVisible,
      uiAjax: uiBrandState,
      noJsGet: noJsBrandState,
    };

    report.assertions.paSizeRest =
      report.paSize.restCount === 1 &&
      report.paSize.restMatchedIds.includes(setup.product_id) &&
      report.paSize.restAliasMatches;
    report.assertions.paSizeUiAjax =
      sizeCheckboxVisible &&
      uiSizeState.cards === 1 &&
      uiSizeState.ids.includes(setup.product_id) &&
      uiSizeState.url.includes(`size=${setup.size_slug}`);
    report.assertions.paSizeNoJs =
      noJsSizeState.cards === 1 && noJsSizeState.ids.includes(setup.product_id);

    report.assertions.brandRest =
      report.productBrand.restCount === 1 &&
      report.productBrand.restMatchedIds.includes(setup.product_id) &&
      report.productBrand.restAliasMatches &&
      report.productBrand.restCombinedBrandAndSizeCount === 1;
    report.assertions.brandUiAjax =
      brandCheckboxVisible &&
      uiBrandState.cards === 1 &&
      uiBrandState.ids.includes(setup.product_id) &&
      uiBrandState.url.includes(`brand=${setup.brand_slug}`);
    report.assertions.brandNoJs =
      noJsBrandState.cards === 1 && noJsBrandState.ids.includes(setup.product_id);

    report.assertions.filterFixtureCleanup =
      report.fixtures.filterCleanup.deleted_products === 1 &&
      report.fixtures.filterCleanup.deleted_size_terms === 1 &&
      report.fixtures.filterCleanup.deleted_brand_terms === 1;
    report.assertions.noPageErrors = pageErrors.length === 0;

    if (!TAX_ONLY) {
      // --- 4. Full Issue #13 regression checks ---
      currentStep = 'full.varFixtureCreate';
      const varSetup = runPhpScript(VAR_FIXTURE_PATH, 'create');
      varFixtureCreated = true;
      report.fixtures.varSetup = varSetup;
      const parentId = varSetup.variable_parent.id;
      const varSku = varSetup.variation_a.sku;

      currentStep = 'full.rest';
      const rP1 = await fetchJson(`${BASE_URL}/wp-json/despace/v1/products/filter?per_page=12&page=1&orderby=price`);
      const rP2 = await fetchJson(`${BASE_URL}/wp-json/despace/v1/products/filter?per_page=12&page=2&orderby=price`);
      const p1Ids = (rP1.data.items || []).map(i => i.id);
      const p2Ids = (rP2.data.items || []).map(i => i.id);
      const rVar = await fetchJson(`${BASE_URL}/wp-json/despace/v1/products/filter?q=${encodeURIComponent(varSku)}`);
      const rSwap = await fetchJson(`${BASE_URL}/wp-json/despace/v1/products/filter?min_price=3000000&max_price=1500000`);
      const rNorm = await fetchJson(`${BASE_URL}/wp-json/despace/v1/products/filter?min_price=1500000&max_price=3000000`);

      currentStep = 'full.uiOrderbyReload';
      await page.goto(`${BASE_URL}/shop/`, { waitUntil: 'networkidle' });
      await Promise.all([
        page.waitForResponse(r => r.url().includes('admin-ajax.php') && r.status() === 200),
        page.locator('#furniva-filter-form input[name="category"][value="wardrobes"]').check(),
      ]);
      await waitFilterIdle(page);
      await Promise.all([
        page.waitForResponse(r => r.url().includes('admin-ajax.php') && r.status() === 200),
        page.selectOption('select.orderby', 'price'),
      ]);
      await waitFilterIdle(page);
      await page.reload({ waitUntil: 'networkidle' });
      const reloadOk = await page.evaluate(() => ({
        wardrobesChecked: document.querySelector('#furniva-filter-form input[name="category"][value="wardrobes"]')?.checked,
        orderby: document.querySelector('select.orderby')?.value,
        cards: document.querySelectorAll('#furniva-shop-results ul.f-products > li').length,
      }));

      currentStep = 'full.networkErrorRetry';
      await openDetails(page, 'has_3d');
      await page.route('**/admin-ajax.php', route => route.abort('failed'));
      await page.locator('#furniva-filter-form input[name="has_3d"]').check();
      await page.waitForSelector('.f-filter-error[role="alert"]', { timeout: 8000 });
      await page.unroute('**/admin-ajax.php');
      await Promise.all([
        page.waitForResponse(r => r.url().includes('admin-ajax.php') && r.status() === 200),
        page.click('#furniva-filter-retry'),
      ]);
      await waitFilterIdle(page);
      const retryCards = await page.locator('#furniva-shop-results ul.f-products > li').count();

      currentStep = 'full.varFixtureCleanup';
      report.fixtures.varCleanup = runPhpScript(VAR_FIXTURE_PATH, 'delete');
      varFixtureCreated = false;

      currentStep = 'full.resetEmptyCat';
      await page.goto(`${BASE_URL}/product-category/uncategorized/`, { waitUntil: 'networkidle' });
      await Promise.all([
        page.waitForURL(url => new URL(url).pathname === '/shop/', { timeout: 12000 }),
        page.click('#furniva-no-results-reset'),
      ]);
      await page.waitForLoadState('networkidle');
      const emptyResetCards = await page.locator('#furniva-shop-results ul.f-products > li').count();

      report.fullSuite = {
        restDuplicates: p2Ids.filter(id => p1Ids.includes(id)).length,
        restVarMapsToParent: (rVar.data.items || []).some(i => i.id === parentId),
        restPriceSwapMatches: rSwap.data.pagination?.total_items === rNorm.data.pagination?.total_items,
        reloadOk,
        retryCards,
        emptyResetPath: new URL(page.url()).pathname,
        emptyResetCards,
      };
      report.assertions.fullSuite =
        report.fullSuite.restDuplicates === 0 &&
        report.fullSuite.restVarMapsToParent &&
        report.fullSuite.restPriceSwapMatches &&
        reloadOk.wardrobesChecked === true &&
        reloadOk.orderby === 'price' &&
        reloadOk.cards === 6 &&
        retryCards > 0 &&
        report.fullSuite.emptyResetPath === '/shop/' &&
        emptyResetCards === 12;
    }

    report.dbAfter = runPhpScript(FILTER_FIXTURE_PATH, 'status');
    report.sessions.after = getWcSessionCount();
    report.sessions.unchanged = report.sessions.before === report.sessions.after;
    report.assertions.dbRestored =
      report.dbBefore.published_products === report.dbAfter.published_products &&
      JSON.stringify(report.dbBefore.pa_size_terms) === JSON.stringify(report.dbAfter.pa_size_terms) &&
      JSON.stringify(report.dbBefore.product_brand_terms) === JSON.stringify(report.dbAfter.product_brand_terms);
    report.assertions.sessionsUnchanged = report.sessions.unchanged;

    const failed = Object.entries(report.assertions)
      .filter(([, ok]) => !ok)
      .map(([k]) => k);
    if (failed.length > 0) {
      report.failedStep = `assertions:${failed.join(',')}`;
      report.errors.push(`Failed assertions: ${failed.join(', ')}`);
      process.exitCode = 1;
    }
  } catch (err) {
    report.failedStep = currentStep;
    report.errors.push(err.stack || String(err));
    process.exitCode = 1;
  } finally {
    clearTimeout(hardTimer);
    if (browser) {
      try { await browser.close(); } catch (_) {}
    }
    if (filterFixtureCreated) {
      try {
        report.fixtures.filterCleanup = runPhpScript(FILTER_FIXTURE_PATH, 'delete');
      } catch (e) {
        report.errors.push(`filterCleanup failed: ${e.message}`);
        process.exitCode = 1;
      }
    }
    if (varFixtureCreated) {
      try {
        report.fixtures.varCleanup = runPhpScript(VAR_FIXTURE_PATH, 'delete');
      } catch (e) {
        report.errors.push(`varCleanup failed: ${e.message}`);
        process.exitCode = 1;
      }
    }
    if (report.sessions.after === null) {
      try {
        report.sessions.after = getWcSessionCount();
        report.sessions.unchanged = report.sessions.before === report.sessions.after;
      } catch (_) {}
    }
    report.durationMs = Date.now() - startedAt;
    console.log(JSON.stringify(report, null, 2));
  }
}

main();
