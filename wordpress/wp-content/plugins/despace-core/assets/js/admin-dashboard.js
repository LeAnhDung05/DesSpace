/**
 * DeSpace Admin Dashboard & Reports (M5).
 *
 * ES6+ pure Fetch API client (no jQuery) for dynamic asynchronous reporting.
 *
 * @package DeSpace\Reports
 */

document.addEventListener('DOMContentLoaded', () => {
    // 1. Resolve localized configuration.
    const settings = window.despaceDashboardSettings || {
        restUrl: '/wp-json/despace/v1/dashboard/metrics',
        nonce: '',
        defaultFrom: '',
        defaultTo: '',
        currencySymbol: '₫',
        i18n: {
            loading: 'Đang tải dữ liệu báo cáo...',
            error: 'Đã xảy ra lỗi khi tải dữ liệu báo cáo.',
            noProducts: 'Chưa có sản phẩm nào được bán trong khoảng thời gian này.',
            ready3d: '3D Sẵn sàng',
            no3d: 'Chưa có 3D'
        }
    };

    // 2. Cache DOM Elements.
    const dateFromInput = document.getElementById('despace-date-from');
    const dateToInput = document.getElementById('despace-date-to');
    const filterBtn = document.getElementById('despace-btn-apply-filter');
    const presetButtons = document.querySelectorAll('.despace-preset-btn');
    const loadingEl = document.getElementById('despace-filter-loading');
    const errorBox = document.getElementById('despace-error-box');
    const errorMsg = document.getElementById('despace-error-message');

    // KPI Elements.
    const metricRevenue = document.getElementById('metric-revenue');
    const metricTotalOrders = document.getElementById('metric-total-orders');
    const metricValidOrdersHint = document.getElementById('metric-valid-orders-hint');
    const metric3dCount = document.getElementById('metric-3d-count');
    const metric3dPercentage = document.getElementById('metric-3d-percentage');
    const metricAov = document.getElementById('metric-aov');

    // Section containers.
    const statusBarsContainer = document.getElementById('despace-status-bars');
    const topProductsTbody = document.getElementById('despace-top-products-tbody');

    // Friendly labels and styles for WooCommerce order statuses.
    const statusMetadata = {
        completed: { label: 'Hoàn thành', color: '#2e7d32', bg: '#e8f5e9' },
        processing: { label: 'Đang xử lý', color: '#1565c0', bg: '#e3f2fd' },
        'on-hold': { label: 'Tạm giữ (Chờ đối soát)', color: '#e65100', bg: '#fff3e0' },
        pending: { label: 'Chờ thanh toán', color: '#f57f17', bg: '#fffde7' },
        cancelled: { label: 'Đã hủy', color: '#c62828', bg: '#ffebee' },
        refunded: { label: 'Đã hoàn tiền', color: '#455a64', bg: '#eceff1' },
        failed: { label: 'Thất bại', color: '#b71c1c', bg: '#fbe9e7' }
    };

    /**
     * Format a date object into YYYY-MM-DD.
     *
     * @param {Date} date
     * @returns {string}
     */
    const formatDate = (date) => {
        const y = date.getFullYear();
        const m = String(date.getMonth() + 1).padStart(2, '0');
        const d = String(date.getDate()).padStart(2, '0');
        return `${y}-${m}-${d}`;
    };

    /**
     * Show or hide loading state.
     *
     * @param {boolean} isLoading
     */
    const setLoading = (isLoading) => {
        if (loadingEl) {
            loadingEl.style.display = isLoading ? 'flex' : 'none';
        }
        if (filterBtn) {
            filterBtn.disabled = isLoading;
        }
        presetButtons.forEach(btn => {
            btn.disabled = isLoading;
        });
    };

    /**
     * Display or hide error box.
     *
     * @param {string|null} message
     */
    const showError = (message = null) => {
        if (!errorBox || !errorMsg) return;
        if (message) {
            errorMsg.textContent = message;
            errorBox.style.display = 'block';
        } else {
            errorBox.style.display = 'none';
            errorMsg.textContent = '';
        }
    };

    /**
     * Fetch metrics from REST API using Fetch API with X-WP-Nonce.
     *
     * @param {string} fromDate
     * @param {string} toDate
     */
    const fetchMetrics = async (fromDate, toDate) => {
        setLoading(true);
        showError(null);

        const params = new URLSearchParams({
            from_date: fromDate,
            to_date: toDate
        });

        const url = `${settings.restUrl}?${params.toString()}`;

        try {
            const response = await fetch(url, {
                method: 'GET',
                headers: {
                    'X-WP-Nonce': settings.nonce,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                }
            });

            const data = await response.json();

            if (!response.ok) {
                const message = data && data.message ? data.message : `${settings.i18n.error} (Mã HTTP: ${response.status})`;
                throw new Error(message);
            }

            renderDashboard(data);
        } catch (err) {
            console.error('DeSpace Dashboard Fetch Error:', err);
            showError(err.message || settings.i18n.error);
        } finally {
            setLoading(false);
        }
    };

    /**
     * Render data payload onto Dashboard DOM.
     *
     * @param {Object} data
     */
    const renderDashboard = (data) => {
        const { metrics, orders_by_status, top_products } = data;

        // 1. Update KPI Values.
        if (metricRevenue && metrics) {
            metricRevenue.textContent = metrics.valid_revenue_formatted || '0 ₫';
        }
        if (metricTotalOrders && metrics) {
            metricTotalOrders.textContent = metrics.total_orders.toLocaleString('vi-VN');
        }
        if (metricValidOrdersHint && metrics) {
            metricValidOrdersHint.textContent = metrics.valid_orders_count.toLocaleString('vi-VN');
        }
        if (metric3dCount && metrics) {
            metric3dCount.textContent = metrics.products_with_3d_count.toLocaleString('vi-VN');
        }
        if (metric3dPercentage && metrics) {
            metric3dPercentage.textContent = metrics.percentage_3d;
        }
        if (metricAov && metrics) {
            metricAov.textContent = metrics.average_order_value_formatted || '0 ₫';
        }

        // 2. Render Order Status Breakdown Bars.
        renderStatusBars(orders_by_status, metrics ? metrics.total_orders : 0);

        // 3. Render Top 10 Best Sellers Table.
        renderTopProducts(top_products);
    };

    /**
     * Render Status Progress Bars.
     *
     * @param {Object} statusCounts
     * @param {number} totalOrders
     */
    const renderStatusBars = (statusCounts, totalOrders) => {
        if (!statusBarsContainer) return;
        statusBarsContainer.innerHTML = '';

        if (!statusCounts || totalOrders === 0) {
            statusBarsContainer.innerHTML = `
                <div class="despace-empty-state">
                    <p>${settings.i18n.noProducts}</p>
                </div>
            `;
            return;
        }

        const entries = Object.entries(statusCounts);

        entries.forEach(([statusKey, count]) => {
            const meta = statusMetadata[statusKey] || {
                label: statusKey,
                color: '#555',
                bg: '#f0f0f0'
            };
            const percentage = totalOrders > 0 ? ((count / totalOrders) * 100).toFixed(1) : 0;

            const row = document.createElement('div');
            row.className = 'despace-status-row';
            row.innerHTML = `
                <div class="despace-status-info">
                    <span class="despace-status-pill" style="background-color: ${meta.bg}; color: ${meta.color}; border: 1px solid ${meta.color}33;">
                        ${meta.label}
                    </span>
                    <span class="despace-status-count">
                        <strong>${count}</strong> (${percentage}%)
                    </span>
                </div>
                <div class="despace-progress-track">
                    <div class="despace-progress-bar" style="width: ${percentage}%; background-color: ${meta.color};"></div>
                </div>
            `;
            statusBarsContainer.appendChild(row);
        });
    };

    /**
     * Render Top 10 Products Table.
     *
     * @param {Array} products
     */
    const renderTopProducts = (products) => {
        if (!topProductsTbody) return;
        topProductsTbody.innerHTML = '';

        if (!Array.isArray(products) || products.length === 0) {
            topProductsTbody.innerHTML = `
                <tr>
                    <td colspan="7" class="despace-table-empty">
                        <span class="dashicons dashicons-warning" style="margin-right: 6px;"></span>
                        ${settings.i18n.noProducts}
                    </td>
                </tr>
            `;
            return;
        }

        products.forEach((prod, index) => {
            const tr = document.createElement('tr');
            tr.className = 'despace-product-row';

            const badge3d = prod.has_3d
                ? `<span class="despace-badge-3d-active"><span class="dashicons dashicons-visibility"></span> ${settings.i18n.ready3d}</span>`
                : `<span class="despace-badge-3d-inactive">${settings.i18n.no3d}</span>`;

            const rankClass = index < 3 ? `despace-rank-${index + 1}` : '';

            tr.innerHTML = `
                <td style="text-align: center;">
                    <span class="despace-rank-badge ${rankClass}">${index + 1}</span>
                </td>
                <td>
                    <img src="${prod.image_url}" alt="${prod.name}" class="despace-product-thumbnail" loading="lazy" />
                </td>
                <td>
                    <a href="${prod.permalink}" target="_blank" rel="noopener noreferrer" class="despace-product-name">
                        ${prod.name}
                    </a>
                </td>
                <td><code class="despace-sku">${prod.sku}</code></td>
                <td style="text-align: center;">${badge3d}</td>
                <td style="text-align: right;"><strong>${prod.quantity_sold.toLocaleString('vi-VN')}</strong></td>
                <td style="text-align: right;" class="despace-revenue-cell">${prod.total_revenue_formatted}</td>
            `;

            topProductsTbody.appendChild(tr);
        });
    };

    /**
     * Handle Date Presets.
     *
     * @param {string} preset
     */
    const applyDatePreset = (preset) => {
        const today = new Date();
        let from = new Date();
        const to = new Date();

        switch (preset) {
            case 'today':
                from = new Date();
                break;
            case 'last7':
                from.setDate(today.getDate() - 7);
                break;
            case 'last30':
                from.setDate(today.getDate() - 30);
                break;
            case 'thisMonth':
                from = new Date(today.getFullYear(), today.getMonth(), 1);
                break;
            default:
                from.setDate(today.getDate() - 30);
                break;
        }

        const formattedFrom = formatDate(from);
        const formattedTo = formatDate(to);

        dateFromInput.value = formattedFrom;
        dateToInput.value = formattedTo;

        presetButtons.forEach(btn => {
            btn.classList.toggle('active', btn.dataset.preset === preset);
        });

        fetchMetrics(formattedFrom, formattedTo);
    };

    // 3. Register Event Listeners.
    presetButtons.forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const preset = btn.dataset.preset;
            applyDatePreset(preset);
        });
    });

    if (filterBtn) {
        filterBtn.addEventListener('click', (e) => {
            e.preventDefault();
            const from = dateFromInput.value;
            const to = dateToInput.value;

            if (!from || !to) {
                showError('Vui lòng chọn đầy đủ ngày bắt đầu và ngày kết thúc.');
                return;
            }
            if (from > to) {
                showError('Ngày bắt đầu không được lớn hơn ngày kết thúc.');
                return;
            }

            // Remove active status on preset buttons when custom range chosen.
            presetButtons.forEach(b => b.classList.remove('active'));

            fetchMetrics(from, to);
        });
    }

    // 4. Initial Trigger on Load (Last 30 days).
    const initialFrom = dateFromInput ? dateFromInput.value : settings.defaultFrom;
    const initialTo = dateToInput ? dateToInput.value : settings.defaultTo;

    if (initialFrom && initialTo) {
        fetchMetrics(initialFrom, initialTo);
    }
});

