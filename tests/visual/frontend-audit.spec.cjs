/**
 * DeSpace Luxury Furniture - Automated Frontend Visual & Interaction Audit Suite
 * 
 * Tests:
 * 1. Burger Navigation & Animated X State:
 *    - Initial state (3 lines, aria-expanded=false, not active)
 *    - Open state (symmetrical X bounds, line 2 opacity 0, aria-expanded=true, body.is-menu-open, dock hidden)
 *    - Close via burger click, Escape key, outside click
 *    - Link navigation: clicking a menu link closes menu and navigates to target URL
 * 2. Empty Cart State (Mobile 390x844 & Tablet 768x1024):
 *    - Assert empty message visible, return to shop button present, cardCount === 0, dock hidden, 0 overflow
 * 3. Cart with Items (Mobile 390x844 & Tablet 768x1024):
 *    - Reads TEST_USER and TEST_PASS from environment variables (reports BLOCKED if missing)
 *    - Assert cardCount > 0, checkout button present, dock hidden, overlapsCheckout === false, 0 overflow
 * 4. Multi-Viewport System Audit across ALL 11 pages at:
 *    - Desktop: 1440 × 900
 *    - Laptop: 1024 × 768
 *    - Tablet: 768 × 1024
 *    - Mobile: 390 × 844
 *    - Strict PASS criteria: status404Count === 0 && failedRequests === 0 && consoleErrorCount === 0 && brokenImageCount === 0 && !hasHorizontalOverflow
 * 
 * Usage:
 * npm install
 * $env:TEST_USER="<username>"; $env:TEST_PASS="<password>"; npm run test:visual
 */

const { chromium } = require('playwright-core');
const fs = require('fs');
const path = require('path');

const BASE_URL = process.env.WP_BASE_URL || 'http://localhost:8090';
const TEST_USER = process.env.TEST_USER || process.env.WP_TEST_USER;
const TEST_PASS = process.env.TEST_PASS || process.env.WP_TEST_PASS;

const SCREENSHOT_DIR = path.resolve(__dirname, '../../docs/reports/screenshots');
const REPORT_OUTPUT = path.resolve(__dirname, '../../docs/reports/frontend-audit-results.json');

const PAGES = [
  { name: 'Trang chủ (Homepage)', slug: 'home', url: `${BASE_URL}/` },
  { name: 'Cửa hàng (Shop)', slug: 'shop', url: `${BASE_URL}/shop/` },
  { name: 'Chi tiết sản phẩm (Single Product)', slug: 'single_product', url: `${BASE_URL}/product/tu-sach-canh-kinh-chong-bui-despace-shelvo-go-soi-modern-oak/` },
  { name: 'Giỏ hàng (Cart)', slug: 'cart', url: `${BASE_URL}/cart/` },
  { name: 'Thanh toán (Checkout)', slug: 'checkout', url: `${BASE_URL}/checkout/` },
  { name: 'Tài khoản (My Account)', slug: 'my_account', url: `${BASE_URL}/my-account/` },
  { name: 'Giới thiệu (About)', slug: 'about', url: `${BASE_URL}/ve-despace/` },
  { name: 'Liên hệ (Contact)', slug: 'contact', url: `${BASE_URL}/lien-he/` },
  { name: 'Tạp chí (Blog)', slug: 'blog', url: `${BASE_URL}/blog/` },
  { name: 'Trải nghiệm 3D / AR', slug: 'ar_visualizer', url: `${BASE_URL}/ar-room-visualizer/` },
  { name: 'Yêu thích (Wishlist)', slug: 'wishlist', url: `${BASE_URL}/wishlist/` }
];

const VIEWPORTS = {
  desktop: { width: 1440, height: 900 },
  laptop: { width: 1024, height: 768 },
  tablet: { width: 768, height: 1024 },
  mobile: { width: 390, height: 844 }
};

if (!fs.existsSync(SCREENSHOT_DIR)) {
  fs.mkdirSync(SCREENSHOT_DIR, { recursive: true });
}

async function runAudit() {
  console.log('================================================================');
  console.log('   DeSpace Luxury Furniture - Automated Visual Audit Suite      ');
  console.log('================================================================\n');

  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  const results = {
    timestamp: new Date().toISOString(),
    environment: {
      baseUrl: BASE_URL,
      browser: 'Google Chrome (Playwright)',
      pagesAuditedCount: PAGES.length,
      viewportsAudited: Object.keys(VIEWPORTS),
      authConfigured: !!(TEST_USER && TEST_PASS)
    },
    burgerNavTest: {},
    emptyCartTest: {},
    cartWithItemsTest: {},
    pageAudits: {}
  };

  // -------------------------------------------------------------
  // PART 1: Burger Navigation & Animated X State (Mobile 390x844)
  // -------------------------------------------------------------
  console.log('--- TEST 1: Mobile Burger Navigation, Animated X & Interactions ---');
  {
    const page = await browser.newPage({ viewport: VIEWPORTS.mobile });
    await page.goto(`${BASE_URL}/`, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(800);

    const burger = await page.$('#furniva-burger');
    if (!burger) throw new Error('#furniva-burger not found in DOM');

    // 1.1 Initial state
    const initialState = await page.evaluate(() => {
      const b = document.querySelector('#furniva-burger');
      const lines = b ? b.querySelectorAll('svg line, svg path') : [];
      return {
        ariaExpanded: b.getAttribute('aria-expanded'),
        isActive: b.classList.contains('is-active'),
        lineCount: lines.length,
        isMenuOpen: document.body.classList.contains('is-menu-open')
      };
    });
    console.log('1.1. Trạng thái ban đầu:', initialState);

    // 1.2 Click to Open
    await burger.click();
    await page.waitForTimeout(500);

    const openState = await page.evaluate(() => {
      const b = document.querySelector('#furniva-burger');
      const nav = document.querySelector('#furniva-mobile-nav');
      const lines = b.querySelectorAll('svg line, svg path');
      const dock = document.querySelector('#ax-chat-dock');
      const dockComputed = dock ? window.getComputedStyle(dock) : null;
      
      const line1Rect = lines[0] ? lines[0].getBoundingClientRect() : null;
      const line2Computed = lines[1] ? window.getComputedStyle(lines[1]) : null;
      const line3Rect = lines[2] ? lines[2].getBoundingClientRect() : null;

      return {
        ariaExpanded: b.getAttribute('aria-expanded'),
        isActive: b.classList.contains('is-active'),
        navHasOpenClass: nav ? nav.classList.contains('open') : false,
        bodyHasMenuOpen: document.body.classList.contains('is-menu-open'),
        line2Opacity: line2Computed ? line2Computed.opacity : null,
        line1Rect: line1Rect ? { x: Math.round(line1Rect.x), y: Math.round(line1Rect.y), w: Math.round(line1Rect.width), h: Math.round(line1Rect.height) } : null,
        line3Rect: line3Rect ? { x: Math.round(line3Rect.x), y: Math.round(line3Rect.y), w: Math.round(line3Rect.width), h: Math.round(line3Rect.height) } : null,
        isDockHidden: dockComputed ? (dockComputed.display === 'none' || dockComputed.opacity === '0' || dockComputed.visibility === 'hidden') : true
      };
    });
    console.log('1.2. Trạng thái khi mở (Open/Active):', openState);

    // Capture screenshot with active X
    const menuFixedPath = path.join(SCREENSHOT_DIR, 'mobile_menu_fixed.png');
    await page.screenshot({ path: menuFixedPath, fullPage: false });
    console.log(`     Saved screenshot -> ${menuFixedPath}`);

    // 1.3 Test close via clicking burger again
    await burger.click();
    await page.waitForTimeout(400);
    const closedAfterBurgerClick = await page.evaluate(() => {
      const b = document.querySelector('#furniva-burger');
      const nav = document.querySelector('#furniva-mobile-nav');
      return {
        ariaExpanded: b.getAttribute('aria-expanded'),
        isActive: b.classList.contains('is-active'),
        navHasOpen: nav.classList.contains('open'),
        bodyHasMenuOpen: document.body.classList.contains('is-menu-open')
      };
    });
    console.log('1.3. Đóng bằng cách click lại burger:', closedAfterBurgerClick);

    // 1.4 Test open then close via Escape
    await burger.click();
    await page.waitForTimeout(300);
    await page.keyboard.press('Escape');
    await page.waitForTimeout(400);
    const closedAfterEscape = await page.evaluate(() => {
      const b = document.querySelector('#furniva-burger');
      const nav = document.querySelector('#furniva-mobile-nav');
      return {
        ariaExpanded: b.getAttribute('aria-expanded'),
        isActive: b.classList.contains('is-active'),
        navHasOpen: nav.classList.contains('open'),
        bodyHasMenuOpen: document.body.classList.contains('is-menu-open')
      };
    });
    console.log('1.4. Đóng bằng phím Escape:', closedAfterEscape);

    // 1.5 Test open then close via click outside
    await burger.click();
    await page.waitForTimeout(300);
    await page.evaluate(() => {
      document.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true, clientX: 200, clientY: 700 }));
    });
    await page.waitForTimeout(400);
    const closedAfterOutsideClick = await page.evaluate(() => {
      const b = document.querySelector('#furniva-burger');
      const nav = document.querySelector('#furniva-mobile-nav');
      return {
        ariaExpanded: b.getAttribute('aria-expanded'),
        isActive: b.classList.contains('is-active'),
        navHasOpen: nav.classList.contains('open'),
        bodyHasMenuOpen: document.body.classList.contains('is-menu-open')
      };
    });
    console.log('1.5. Đóng bằng click bên ngoài:', closedAfterOutsideClick);

    // 1.6 Test link navigation from mobile menu
    await burger.click();
    await page.waitForTimeout(300);
    const shopLink = await page.$('#furniva-mobile-nav a[href*="shop"]');
    let navResult = { clicked: false, finalUrl: null, menuClosed: false, ariaExpanded: null };
    if (shopLink) {
      const targetHref = await shopLink.getAttribute('href');
      await Promise.all([
        page.waitForNavigation({ waitUntil: 'domcontentloaded' }).catch(() => {}),
        shopLink.click()
      ]);
      await page.waitForTimeout(600);
      const postNavState = await page.evaluate(() => {
        const b = document.querySelector('#furniva-burger');
        const nav = document.querySelector('#furniva-mobile-nav');
        return {
          ariaExpanded: b ? b.getAttribute('aria-expanded') : null,
          isActive: b ? b.classList.contains('is-active') : null,
          navHasOpen: nav ? nav.classList.contains('open') : false,
          bodyHasMenuOpen: document.body.classList.contains('is-menu-open')
        };
      });
      navResult = {
        clicked: true,
        targetHref,
        finalUrl: page.url(),
        menuClosed: !postNavState.navHasOpen && !postNavState.bodyHasMenuOpen,
        ariaExpanded: postNavState.ariaExpanded,
        isActive: postNavState.isActive,
        isTargetReached: page.url().includes('/shop/')
      };
      console.log('1.6. Bấm link trong menu di động (Điều hướng & Đóng menu):', navResult);
    }

    const isXFormed = openState.line2Opacity === '0' && openState.line1Rect && openState.line3Rect && Math.abs(openState.line1Rect.x - openState.line3Rect.x) <= 2;
    const test1Pass = (
      initialState.ariaExpanded === 'false' &&
      openState.ariaExpanded === 'true' &&
      isXFormed &&
      closedAfterBurgerClick.ariaExpanded === 'false' &&
      closedAfterEscape.ariaExpanded === 'false' &&
      closedAfterOutsideClick.ariaExpanded === 'false' &&
      navResult.menuClosed &&
      navResult.isTargetReached
    );

    results.burgerNavTest = {
      initialAria: initialState.ariaExpanded === 'false',
      openedAria: openState.ariaExpanded === 'true',
      isXSymbolVerified: isXFormed,
      line1Rect: openState.line1Rect,
      line3Rect: openState.line3Rect,
      closedViaClick: closedAfterBurgerClick.ariaExpanded === 'false' && !closedAfterBurgerClick.isActive,
      closedViaEscape: closedAfterEscape.ariaExpanded === 'false' && !closedAfterEscape.isActive,
      closedViaOutsideClick: closedAfterOutsideClick.ariaExpanded === 'false' && !closedAfterOutsideClick.isActive,
      linkNavigation: navResult,
      dockHiddenWhenOpen: openState.isDockHidden,
      status: test1Pass ? 'PASS' : 'FAIL'
    };
    await page.close();
  }

  // -------------------------------------------------------------
  // PART 2: Empty Cart State Test (Mobile 390px & Tablet 768px)
  // -------------------------------------------------------------
  console.log('\n--- TEST 2: Empty Cart State (Mobile 390px & Tablet 768px) ---');
  {
    // Test on Mobile (390x844)
    const pageEmptyMobile = await browser.newPage({ viewport: VIEWPORTS.mobile });
    await pageEmptyMobile.goto(`${BASE_URL}/cart/`, { waitUntil: 'domcontentloaded' });
    await pageEmptyMobile.waitForTimeout(800);

    const emptyMobileData = await pageEmptyMobile.evaluate(() => {
      const dock = document.querySelector('#ax-chat-dock');
      const dockStyle = dock ? window.getComputedStyle(dock) : null;
      const emptyMsg = document.querySelector('.cart-empty, .woocommerce-info, .ax-cart-empty, .wc-empty-cart-message');
      const returnBtn = document.querySelector('a.return-to-shop, a[href*="shop"], .wc-backward');
      const cardCount = document.querySelectorAll('.ax-cart-item').length;

      return {
        cardCount,
        isEmptyMessageVisible: !!emptyMsg && emptyMsg.offsetParent !== null,
        emptyMessageText: emptyMsg ? emptyMsg.textContent.trim().replace(/\s+/g, ' ') : null,
        hasReturnToShopButton: !!returnBtn && returnBtn.offsetParent !== null,
        isDockHidden: !dock || dockStyle.display === 'none' || dockStyle.opacity === '0' || dockStyle.visibility === 'hidden',
        dockDisplay: dockStyle ? dockStyle.display : null,
        scrollWidth: document.documentElement.scrollWidth,
        innerWidth: window.innerWidth,
        hasHorizontalOverflow: document.documentElement.scrollWidth > window.innerWidth
      };
    });
    const emptyMobilePath = path.join(SCREENSHOT_DIR, 'cart_empty_mobile.png');
    await pageEmptyMobile.screenshot({ path: emptyMobilePath });
    await pageEmptyMobile.close();

    // Test on Tablet (768x1024)
    const pageEmptyTablet = await browser.newPage({ viewport: VIEWPORTS.tablet });
    await pageEmptyTablet.goto(`${BASE_URL}/cart/`, { waitUntil: 'domcontentloaded' });
    await pageEmptyTablet.waitForTimeout(800);

    const emptyTabletData = await pageEmptyTablet.evaluate(() => {
      const dock = document.querySelector('#ax-chat-dock');
      const dockStyle = dock ? window.getComputedStyle(dock) : null;
      const emptyMsg = document.querySelector('.cart-empty, .woocommerce-info, .ax-cart-empty, .wc-empty-cart-message');
      const returnBtn = document.querySelector('a.return-to-shop, a[href*="shop"], .wc-backward');
      const cardCount = document.querySelectorAll('.ax-cart-item').length;

      return {
        cardCount,
        isEmptyMessageVisible: !!emptyMsg && emptyMsg.offsetParent !== null,
        emptyMessageText: emptyMsg ? emptyMsg.textContent.trim().replace(/\s+/g, ' ') : null,
        hasReturnToShopButton: !!returnBtn && returnBtn.offsetParent !== null,
        isDockHidden: !dock || dockStyle.display === 'none' || dockStyle.opacity === '0' || dockStyle.visibility === 'hidden',
        dockDisplay: dockStyle ? dockStyle.display : null,
        scrollWidth: document.documentElement.scrollWidth,
        innerWidth: window.innerWidth,
        hasHorizontalOverflow: document.documentElement.scrollWidth > window.innerWidth
      };
    });
    const emptyTabletPath = path.join(SCREENSHOT_DIR, 'cart_empty_tablet.png');
    await pageEmptyTablet.screenshot({ path: emptyTabletPath });
    await pageEmptyTablet.close();

    const mobileEmptyPass = emptyMobileData.cardCount === 0 && emptyMobileData.isEmptyMessageVisible && emptyMobileData.hasReturnToShopButton && emptyMobileData.isDockHidden && !emptyMobileData.hasHorizontalOverflow;
    const tabletEmptyPass = emptyTabletData.cardCount === 0 && emptyTabletData.isEmptyMessageVisible && emptyTabletData.hasReturnToShopButton && emptyTabletData.isDockHidden && !emptyTabletData.hasHorizontalOverflow;

    results.emptyCartTest = {
      mobile: {
        ...emptyMobileData,
        status: mobileEmptyPass ? 'PASS' : 'FAIL'
      },
      tablet: {
        ...emptyTabletData,
        status: tabletEmptyPass ? 'PASS' : 'FAIL'
      },
      status: mobileEmptyPass && tabletEmptyPass ? 'PASS' : 'FAIL'
    };
    console.log(`2.1. Giỏ hàng trống Mobile (390px): [${results.emptyCartTest.mobile.status}]`, emptyMobileData);
    console.log(`2.2. Giỏ hàng trống Tablet (768px): [${results.emptyCartTest.tablet.status}]`, emptyTabletData);
  }

  // -------------------------------------------------------------
  // PART 3: Cart with Items Test (Mobile 390px & Tablet 768px)
  // -------------------------------------------------------------
  console.log('\n--- TEST 3: Cart with Items (Mobile 390px & Tablet 768px) ---');
  if (!TEST_USER || !TEST_PASS) {
    console.log('[-] [BLOCKED] Biến môi trường TEST_USER hoặc TEST_PASS chưa được thiết lập.');
    console.log('    Để chạy bài test giỏ hàng có sản phẩm, thiết lập:');
    console.log('    $env:TEST_USER="<user>"; $env:TEST_PASS="<pass>"; npm run test:visual');
    results.cartWithItemsTest = {
      status: 'BLOCKED',
      reason: 'Missing TEST_USER / TEST_PASS environment variables'
    };
  } else {
    try {
      const authContext = await browser.newContext({ viewport: VIEWPORTS.mobile });
      const authPage = await authContext.newPage();

      // Login customer
      await authPage.goto(`${BASE_URL}/my-account/`, { waitUntil: 'domcontentloaded' });
      await authPage.waitForTimeout(800);
      const isLoginForm = await authPage.$('#username');
      if (isLoginForm) {
        await authPage.fill('#username', TEST_USER);
        await authPage.fill('#password', TEST_PASS);
        await authPage.click('button[name="login"]');
        await authPage.waitForNavigation({ waitUntil: 'domcontentloaded' }).catch(() => {});
      }

      // Add product to cart
      await authPage.goto(`${BASE_URL}/product/tu-sach-canh-kinh-chong-bui-despace-shelvo-go-soi-modern-oak/`, { waitUntil: 'domcontentloaded' });
      await authPage.waitForTimeout(800);
      const addBtn = await authPage.$('#axAddToCart');
      if (addBtn) {
        await addBtn.click();
        await authPage.waitForTimeout(1500);
      }

      // 3.1 Mobile 390x844 with items
      await authPage.goto(`${BASE_URL}/cart/`, { waitUntil: 'domcontentloaded' });
      await authPage.waitForTimeout(1000);

      const mobileWithItems = await authPage.evaluate(() => {
        const dock = document.querySelector('#ax-chat-dock');
        const dockStyle = dock ? window.getComputedStyle(dock) : null;
        const isDockHidden = !dock || dockStyle.display === 'none' || dockStyle.opacity === '0' || dockStyle.visibility === 'hidden';

        const checkoutBtn = document.querySelector('.ax-checkout-btn, .checkout-button, a[href*="checkout"]');
        const hasCheckoutButton = !!checkoutBtn && checkoutBtn.offsetParent !== null;
        const checkoutRect = checkoutBtn ? checkoutBtn.getBoundingClientRect() : null;

        const dockRect = dock && !isDockHidden ? dock.getBoundingClientRect() : null;
        let overlapsCheckout = false;
        if (dockRect && checkoutRect) {
          overlapsCheckout = !(
            dockRect.right < checkoutRect.left ||
            dockRect.left > checkoutRect.right ||
            dockRect.bottom < checkoutRect.top ||
            dockRect.top > checkoutRect.bottom
          );
        }

        const cardCount = document.querySelectorAll('.ax-cart-item').length;

        return {
          cardCount,
          hasCheckoutButton,
          isDockHidden,
          dockDisplay: dockStyle ? dockStyle.display : null,
          overlapsCheckout,
          scrollWidth: document.documentElement.scrollWidth,
          innerWidth: window.innerWidth,
          hasHorizontalOverflow: document.documentElement.scrollWidth > window.innerWidth
        };
      });

      const cartMobileFixedPath = path.join(SCREENSHOT_DIR, 'cart_mobile_fixed.png');
      await authPage.screenshot({ path: cartMobileFixedPath, fullPage: true });

      // 3.2 Tablet 768x1024 with items
      await authPage.setViewportSize(VIEWPORTS.tablet);
      await authPage.reload({ waitUntil: 'domcontentloaded' });
      await authPage.waitForTimeout(1000);

      const tabletWithItems = await authPage.evaluate(() => {
        const dock = document.querySelector('#ax-chat-dock');
        const dockStyle = dock ? window.getComputedStyle(dock) : null;
        const isDockHidden = !dock || dockStyle.display === 'none' || dockStyle.opacity === '0' || dockStyle.visibility === 'hidden';

        const checkoutBtn = document.querySelector('.ax-checkout-btn, .checkout-button, a[href*="checkout"]');
        const hasCheckoutButton = !!checkoutBtn && checkoutBtn.offsetParent !== null;
        const checkoutRect = checkoutBtn ? checkoutBtn.getBoundingClientRect() : null;

        const dockRect = dock && !isDockHidden ? dock.getBoundingClientRect() : null;
        let overlapsCheckout = false;
        if (dockRect && checkoutRect) {
          overlapsCheckout = !(
            dockRect.right < checkoutRect.left ||
            dockRect.left > checkoutRect.right ||
            dockRect.bottom < checkoutRect.top ||
            dockRect.top > checkoutRect.bottom
          );
        }

        const cardCount = document.querySelectorAll('.ax-cart-item').length;

        return {
          cardCount,
          hasCheckoutButton,
          isDockHidden,
          dockDisplay: dockStyle ? dockStyle.display : null,
          overlapsCheckout,
          scrollWidth: document.documentElement.scrollWidth,
          innerWidth: window.innerWidth,
          hasHorizontalOverflow: document.documentElement.scrollWidth > window.innerWidth
        };
      });

      const cartTabletFixedPath = path.join(SCREENSHOT_DIR, 'cart_tablet_fixed.png');
      await authPage.screenshot({ path: cartTabletFixedPath, fullPage: true });

      await authPage.close();
      await authContext.close();

      const mobileWithItemsPass = mobileWithItems.cardCount > 0 && mobileWithItems.hasCheckoutButton && mobileWithItems.isDockHidden && !mobileWithItems.overlapsCheckout && !mobileWithItems.hasHorizontalOverflow;
      const tabletWithItemsPass = tabletWithItems.cardCount > 0 && tabletWithItems.hasCheckoutButton && tabletWithItems.isDockHidden && !tabletWithItems.overlapsCheckout && !tabletWithItems.hasHorizontalOverflow;

      results.cartWithItemsTest = {
        mobile: {
          ...mobileWithItems,
          status: mobileWithItemsPass ? 'PASS' : 'FAIL'
        },
        tablet: {
          ...tabletWithItems,
          status: tabletWithItemsPass ? 'PASS' : 'FAIL'
        },
        status: mobileWithItemsPass && tabletWithItemsPass ? 'PASS' : 'FAIL'
      };

      console.log(`3.1. Giỏ hàng có sản phẩm Mobile (390px): [${results.cartWithItemsTest.mobile.status}]`, mobileWithItems);
      console.log(`3.2. Giỏ hàng có sản phẩm Tablet (768px): [${results.cartWithItemsTest.tablet.status}]`, tabletWithItems);
    } catch (err) {
      console.error('Cart with items test error:', err.message);
      results.cartWithItemsTest = {
        status: 'FAIL',
        error: err.message
      };
    }
  }

  // -------------------------------------------------------------
  // PART 4: Multi-Viewport Audit across ALL 11 Pages
  // -------------------------------------------------------------
  console.log('\n--- TEST 4: Multi-Viewport Audit across 11 Pages (1440, 1024, 768, 390) ---');
  for (const p of PAGES) {
    results.pageAudits[p.slug] = {
      name: p.name,
      url: p.url,
      viewports: {},
      overallStatus: 'PASS'
    };

    const page = await browser.newPage();
    const consoleErrors = [];
    const networkFailures = [];
    const httpErrors = [];

    page.on('console', msg => {
      if (msg.type() === 'error') {
        consoleErrors.push(msg.text());
      }
    });

    page.on('requestfailed', req => {
      networkFailures.push({
        url: req.url(),
        method: req.method(),
        errorText: req.failure() ? req.failure().errorText : 'Request Failed'
      });
    });

    page.on('response', resp => {
      if (resp.status() >= 400) {
        httpErrors.push({
          url: resp.url(),
          status: resp.status(),
          statusText: resp.statusText()
        });
      }
    });

    try {
      await page.goto(p.url, { waitUntil: 'domcontentloaded', timeout: 15000 });
      await page.waitForTimeout(600);

      for (const [vpName, vpSize] of Object.entries(VIEWPORTS)) {
        await page.setViewportSize(vpSize);
        await page.waitForTimeout(300);

        const vpMetrics = await page.evaluate(() => {
          const imgs = Array.from(document.querySelectorAll('img'));
          const brokenImgs = imgs.filter(img => {
            const src = img.getAttribute('src');
            if (src === '' || src === null) return true;
            if (src.startsWith('data:')) return false;

            // Check if image is in a hidden subtree on this viewport (e.g. desktop mega dropdowns on mobile)
            let el = img;
            while (el && el !== document.body) {
              const s = window.getComputedStyle(el);
              if (s.display === 'none' || s.visibility === 'hidden') {
                return false;
              }
              el = el.parentElement;
            }

            // Check if image is in or near viewport and failed to decode
            const rect = img.getBoundingClientRect();
            const isNearViewport = rect.top < window.innerHeight * 2 && rect.bottom > -200;
            if (isNearViewport && img.complete && img.naturalWidth === 0) {
              return true;
            }
            return false;
          }).map(img => img.outerHTML.substring(0, 100));

          return {
            brokenImageCount: brokenImgs.length,
            brokenImages: brokenImgs,
            scrollWidth: document.documentElement.scrollWidth,
            innerWidth: window.innerWidth,
            hasHorizontalOverflow: document.documentElement.scrollWidth > window.innerWidth
          };
        });

        // Screenshot for this viewport
        const screenshotFilename = `${p.slug}_${vpName}.png`;
        const screenshotPath = path.join(SCREENSHOT_DIR, screenshotFilename);
        await page.screenshot({ path: screenshotPath });

        const status404Count = httpErrors.filter(r => r.status === 404).length;
        const isPass = (
          networkFailures.length === 0 &&
          httpErrors.length === 0 &&
          consoleErrors.length === 0 &&
          vpMetrics.brokenImageCount === 0 &&
          !vpMetrics.hasHorizontalOverflow
        );

        results.pageAudits[p.slug].viewports[vpName] = {
          viewport: `${vpSize.width}x${vpSize.height}`,
          status: isPass ? 'PASS' : 'FAIL',
          consoleErrorCount: consoleErrors.length,
          consoleErrors: [...consoleErrors],
          networkFailureCount: networkFailures.length,
          networkFailures: [...networkFailures],
          httpErrorCount: httpErrors.length,
          httpErrors: [...httpErrors],
          failedRequestCount: networkFailures.length + httpErrors.length,
          status404Count,
          brokenImageCount: vpMetrics.brokenImageCount,
          brokenImages: vpMetrics.brokenImages,
          hasHorizontalOverflow: vpMetrics.hasHorizontalOverflow,
          screenshot: screenshotFilename
        };

        if (!isPass) {
          results.pageAudits[p.slug].overallStatus = 'FAIL';
        }
      }

      console.log(`- [${results.pageAudits[p.slug].overallStatus}] ${p.name} | Viewports: 1440:${results.pageAudits[p.slug].viewports.desktop.status}, 1024:${results.pageAudits[p.slug].viewports.laptop.status}, 768:${results.pageAudits[p.slug].viewports.tablet.status}, 390:${results.pageAudits[p.slug].viewports.mobile.status} (Console: ${consoleErrors.length}, NetFailed: ${networkFailures.length}, HTTP>=400: ${httpErrors.length})`);
    } catch (err) {
      results.pageAudits[p.slug].overallStatus = 'FAIL';
      results.pageAudits[p.slug].error = err.message;
      console.log(`- [FAIL] ${p.name}: ${err.message}`);
    } finally {
      await page.close();
    }
  }

  // -------------------------------------------------------------
  // PART 5: Overall Suite Evaluation & Exit Code Calculation
  // -------------------------------------------------------------
  let hasFail = false;
  let hasBlocked = false;

  // 1. Burger Nav Test
  if (results.burgerNavTest.status === 'FAIL') {
    hasFail = true;
  } else if (results.burgerNavTest.status === 'BLOCKED') {
    hasBlocked = true;
  }

  // 2. Empty Cart Test
  if (results.emptyCartTest.status === 'FAIL') {
    hasFail = true;
  } else if (results.emptyCartTest.status === 'BLOCKED') {
    hasBlocked = true;
  }

  // 3. Cart with Items Test
  if (results.cartWithItemsTest.status === 'FAIL') {
    hasFail = true;
  } else if (results.cartWithItemsTest.status === 'BLOCKED') {
    hasBlocked = true;
  }

  // 4. Page Audits (11 Pages x 4 Viewports)
  let totalViewportRuns = 0;
  let passedViewportRuns = 0;
  for (const p of PAGES) {
    const pageAudit = results.pageAudits[p.slug];
    if (!pageAudit) {
      hasFail = true;
      continue;
    }
    if (pageAudit.overallStatus === 'FAIL') {
      hasFail = true;
    } else if (pageAudit.overallStatus === 'BLOCKED') {
      hasBlocked = true;
    }

    if (pageAudit.viewports) {
      for (const vp of Object.values(pageAudit.viewports)) {
        totalViewportRuns++;
        if (vp.status === 'PASS') {
          passedViewportRuns++;
        } else if (vp.status === 'FAIL') {
          hasFail = true;
        } else if (vp.status === 'BLOCKED') {
          hasBlocked = true;
        }
      }
    }
  }

  let overallStatus = 'PASS';
  if (hasFail) {
    overallStatus = 'FAIL';
    process.exitCode = 1;
  } else if (hasBlocked) {
    overallStatus = 'BLOCKED';
    process.exitCode = 0;
  } else {
    overallStatus = 'PASS';
    process.exitCode = 0;
  }

  results.summary = {
    overallStatus,
    exitCode: process.exitCode || 0,
    hasFail,
    hasBlocked,
    burgerNavStatus: results.burgerNavTest.status,
    emptyCartStatus: results.emptyCartTest.status,
    cartWithItemsStatus: results.cartWithItemsTest.status,
    totalPagesAudited: PAGES.length,
    viewportsPerCount: Object.keys(VIEWPORTS).length,
    totalViewportAudits: totalViewportRuns,
    passedViewportAudits: passedViewportRuns
  };

  await browser.close();

  fs.writeFileSync(REPORT_OUTPUT, JSON.stringify(results, null, 2), 'utf-8');
  console.log(`\n================================================================`);
  console.log(`   OVERALL TEST SUITE STATUS: [${overallStatus}] (Exit Code: ${process.exitCode || 0})`);
  console.log(`   - Burger Navigation & Animated X: [${results.burgerNavTest.status}]`);
  console.log(`   - Empty Cart (Mobile/Tablet):     [${results.emptyCartTest.status}]`);
  console.log(`   - Cart with Items (Mobile/Tablet): [${results.cartWithItemsTest.status}]`);
  console.log(`   - 11 Pages Multi-Viewport Audits:  [${passedViewportRuns}/${totalViewportRuns} PASS]`);
  if (hasBlocked) {
    console.log(`   * NOTE: Suite contains BLOCKED tests (Credentials not provided for Auth Cart test).`);
    console.log(`     BLOCKED is NOT counted as PASS.`);
  }
  console.log(`\nAudit Completed! Full evidence saved to: ${REPORT_OUTPUT}`);
  console.log(`================================================================\n`);
}

runAudit().catch(err => {
  console.error('Audit failed with error:', err);
  process.exit(1);
});
