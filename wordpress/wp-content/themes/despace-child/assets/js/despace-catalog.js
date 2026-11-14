/**
 * DeSpace Catalog Live REST API Filter (ES6+)
 * Zero jQuery dependency. Adheres to DeSpace SADD v1.2 Sections 4.3.4 & 4.3.5.
 */

class DeSpaceCatalog {
  constructor() {
    this.restUrl = (window.despaceData && window.despaceData.restUrl) || '/wp-json/despace/v1/';
    this.shopUrl = (window.despaceData && window.despaceData.shopUrl) || '/shop/';
    this.currency = (window.despaceData && window.despaceData.currency) || '$';
    this.currentFilters = this.parseUrlParams();
    this.isLoading = false;

    this.init();
  }

  parseUrlParams() {
    const params = new URLSearchParams(window.location.search);
    return {
      keyword: params.get('keyword') || '',
      category: params.get('category') || '',
      min_price: params.get('min_price') || '',
      max_price: params.get('max_price') || '',
      has_3d: params.get('has_3d') === '1',
      page: parseInt(params.get('page'), 10) || 1,
      sort: params.get('sort') || 'date_desc',
    };
  }

  init() {
    // Check if on catalog / shop container
    this.container = document.querySelector('.ds-woocommerce-content') || document.querySelector('ul.products');
    if (!this.container) return;

    this.bindEvents();
  }

  bindEvents() {
    // Listen to 3D filter toggles
    document.querySelectorAll('[data-despace-filter="has_3d"]').forEach(el => {
      el.addEventListener('change', (e) => {
        this.currentFilters.has_3d = e.target.checked;
        this.currentFilters.page = 1;
        this.fetchProducts();
      });
    });

    // Listen to sort dropdown
    const sortSelect = document.querySelector('.woocommerce-ordering select');
    if (sortSelect) {
      sortSelect.addEventListener('change', (e) => {
        this.currentFilters.sort = e.target.value;
        this.currentFilters.page = 1;
        this.fetchProducts();
      });
    }

    // Handle browser back/forward buttons
    window.addEventListener('popstate', () => {
      this.currentFilters = this.parseUrlParams();
      this.fetchProducts(false);
    });
  }

  updateUrl() {
    const params = new URLSearchParams();
    if (this.currentFilters.keyword) params.set('keyword', this.currentFilters.keyword);
    if (this.currentFilters.category) params.set('category', this.currentFilters.category);
    if (this.currentFilters.min_price) params.set('min_price', this.currentFilters.min_price);
    if (this.currentFilters.max_price) params.set('max_price', this.currentFilters.max_price);
    if (this.currentFilters.has_3d) params.set('has_3d', '1');
    if (this.currentFilters.page > 1) params.set('page', this.currentFilters.page);
    if (this.currentFilters.sort && this.currentFilters.sort !== 'date_desc') params.set('sort', this.currentFilters.sort);

    const newUrl = `${window.location.pathname}?${params.toString()}`;
    window.history.pushState(this.currentFilters, '', newUrl);
  }

  async fetchProducts(updateBrowserUrl = true) {
    if (this.isLoading) return;
    this.isLoading = true;

    if (updateBrowserUrl) {
      this.updateUrl();
    }

    // Render loading indicator
    if (this.container) {
      this.container.style.opacity = '0.5';
      this.container.style.pointerEvents = 'none';
    }

    try {
      const endpoint = new URL(`${this.restUrl}products/filter`, window.location.origin);
      if (this.currentFilters.keyword) endpoint.searchParams.set('keyword', this.currentFilters.keyword);
      if (this.currentFilters.category) endpoint.searchParams.set('category', this.currentFilters.category);
      if (this.currentFilters.min_price) endpoint.searchParams.set('min_price', this.currentFilters.min_price);
      if (this.currentFilters.max_price) endpoint.searchParams.set('max_price', this.currentFilters.max_price);
      if (this.currentFilters.has_3d) endpoint.searchParams.set('has_3d', '1');
      endpoint.searchParams.set('page', this.currentFilters.page);
      endpoint.searchParams.set('per_page', 12);
      endpoint.searchParams.set('sort', this.currentFilters.sort);

      const response = await fetch(endpoint.toString(), {
        headers: {
          'Accept': 'application/json',
          'X-WP-Nonce': (window.despaceData && window.despaceData.nonce) || '',
        }
      });

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      const json = await response.json();
      if (json.success && json.data) {
        this.renderItems(json.data.items, json.data.pagination);
      }
    } catch (err) {
      console.error('[DeSpace Catalog] Filter error:', err);
    } finally {
      this.isLoading = false;
      if (this.container) {
        this.container.style.opacity = '1';
        this.container.style.pointerEvents = 'auto';
      }
    }
  }

  renderItems(items, pagination) {
    if (!this.container) return;

    if (!items || items.length === 0) {
      this.container.innerHTML = `
        <div style="text-align: center; padding: 60px 20px; color: #888;">
          <p style="font-size: 18px; margin-bottom: 15px;">Không tìm thấy sản phẩm nào phù hợp với bộ lọc đã chọn.</p>
          <a href="${this.shopUrl}" class="ds-btn ds-btn--outline" style="color: #111 !important; border-color: #ccc;">Xóa tất cả bộ lọc</a>
        </div>
      `;
      return;
    }

    const html = `
      <div class="ds-product-grid">
        ${items.map(item => `
          <div class="ds-card">
            <div class="ds-card__thumb">
              <a href="${item.permalink}">
                <img src="${item.image_url}" alt="${item.name}" loading="lazy" />
              </a>
              ${item.has_3d ? `
                <span class="ds-badge-3d-tag">
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                  </svg>
                  3D / AR
                </span>
              ` : ''}
            </div>
            <div class="ds-card__body">
              <div class="ds-card__category">${item.categories && item.categories.length > 0 ? item.categories[0].name : 'DeSpace'}</div>
              <h3 class="ds-card__title">
                <a href="${item.permalink}">${item.name}</a>
              </h3>
              <div class="ds-card__price-row">
                ${item.price_html}
              </div>
              <div class="ds-card__actions">
                ${item.has_3d ? `
                  <button type="button" class="ds-btn-view-3d-card despace-btn-open-3d" data-product-id="${item.id}" data-model="${item.model_url}" data-poster="${item.poster_url}" data-ar="${item.enable_ar ? '1' : '0'}" data-name="${item.name}">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                    </svg>
                    Xem 3D
                  </button>
                ` : ''}
                <a href="${item.permalink}" class="ds-btn-view-3d-card" style="background:var(--ds-dark);color:#FAF9F6;border-color:var(--ds-dark);">
                  Chi tiết &rarr;
                </a>
              </div>
            </div>
          </div>
        `).join('')}
      </div>
    `;

    this.container.innerHTML = html;
  }
}

document.addEventListener('DOMContentLoaded', () => {
  window.despaceCatalog = new DeSpaceCatalog();
});
