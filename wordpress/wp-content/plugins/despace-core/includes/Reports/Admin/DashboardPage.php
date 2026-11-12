<?php
/**
 * Admin Dashboard & Reports Page for DeSpace Core (M5).
 *
 * @package DeSpace\Reports\Admin
 */

namespace DeSpace\Reports\Admin;

use DeSpace\Security\RoleManager;

defined( 'ABSPATH' ) || exit;

/**
 * Class DashboardPage
 */
class DashboardPage {

	/**
	 * Page slug in admin menu.
	 */
	public const PAGE_SLUG = 'despace-reports';

	/**
	 * Initialize Admin Hooks.
	 */
	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'register_admin_menu' ), 20 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	/**
	 * Register the admin menu for DeSpace Reports.
	 *
	 * Access strictly restricted to users with 'view_despace_reports' capability.
	 */
	public static function register_admin_menu(): void {
		$capability = RoleManager::CAP_VIEW_REPORTS;

		// 1. Top-level DeSpace Reports menu.
		add_menu_page(
			__( 'DeSpace Báo Cáo', 'despace-core' ),
			__( 'DeSpace Báo Cáo', 'despace-core' ),
			$capability,
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' ),
			'dashicons-chart-area',
			55
		);

		// 2. Also register as a submenu under WooCommerce for convenient admin access.
		add_submenu_page(
			'woocommerce',
			__( 'DeSpace Báo Cáo', 'despace-core' ),
			__( 'DeSpace Báo Cáo', 'despace-core' ),
			$capability,
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Enqueue styles and scripts for the dashboard page.
	 *
	 * @param string $hook_suffix
	 */
	public static function enqueue_assets( string $hook_suffix ): void {
		// Only enqueue on DeSpace Reports admin page.
		if ( false === strpos( $hook_suffix, self::PAGE_SLUG ) ) {
			return;
		}

		$version   = defined( 'DESPACE_CORE_VERSION' ) ? DESPACE_CORE_VERSION : '1.0.0';
		$assets_url = defined( 'DESPACE_CORE_URL' ) ? DESPACE_CORE_URL . 'assets/' : plugin_dir_url( dirname( dirname( __DIR__ ) ) ) . 'assets/';

		// Enqueue CSS.
		wp_enqueue_style(
			'despace-admin-dashboard-css',
			$assets_url . 'css/admin-dashboard.css',
			array(),
			$version
		);

		// Enqueue pure ES6+ JavaScript (no jQuery dependency).
		wp_enqueue_script(
			'despace-admin-dashboard-js',
			$assets_url . 'js/admin-dashboard.js',
			array(),
			$version,
			true
		);

		// Pass localized configuration and security nonces to JavaScript.
		$default_from = gmdate( 'Y-m-d', strtotime( '-30 days' ) );
		$default_to   = gmdate( 'Y-m-d' );

		wp_localize_script(
			'despace-admin-dashboard-js',
			'despaceDashboardSettings',
			array(
				'restUrl'        => esc_url_raw( rest_url( 'despace/v1/dashboard/metrics' ) ),
				'nonce'          => wp_create_nonce( 'wp_rest' ),
				'defaultFrom'    => $default_from,
				'defaultTo'      => $default_to,
				'currencySymbol' => function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '₫',
				'i18n'           => array(
					'loading'    => __( 'Đang tải dữ liệu báo cáo...', 'despace-core' ),
					'error'      => __( 'Đã xảy ra lỗi khi tải dữ liệu báo cáo.', 'despace-core' ),
					'noProducts' => __( 'Chưa có sản phẩm nào được bán trong khoảng thời gian này.', 'despace-core' ),
					'ready3d'    => __( '3D Sẵn sàng', 'despace-core' ),
					'no3d'       => __( 'Chưa có 3D', 'despace-core' ),
				),
			)
		);
	}

	/**
	 * Render the Dashboard HTML interface.
	 */
	public static function render_page(): void {
		if ( ! RoleManager::current_user_can_view_reports() ) {
			wp_die(
				esc_html__( 'Bạn không có quyền truy cập trang báo cáo DeSpace.', 'despace-core' ),
				esc_html__( 'Truy cập bị từ chối', 'despace-core' ),
				array( 'response' => 403 )
			);
		}

		$today        = gmdate( 'Y-m-d' );
		$thirty_days  = gmdate( 'Y-m-d', strtotime( '-30 days' ) );
		?>
		<div class="wrap despace-dashboard-wrap">
			<!-- Header -->
			<div class="despace-dashboard-header">
				<div class="despace-header-title">
					<h1><?php esc_html_e( 'DeSpace — Báo Cáo & Dashboard', 'despace-core' ); ?></h1>
					<p class="despace-header-subtitle">
						<?php esc_html_e( 'Hệ thống báo cáo hiệu suất kinh doanh, tồn kho và danh mục nội thất 3D/AR tương thích chuẩn HPOS.', 'despace-core' ); ?>
					</p>
				</div>
				<div class="despace-header-badge">
					<span class="despace-badge-hpos">HPOS Verified</span>
					<span class="despace-badge-role"><?php esc_html_e( 'Quyền: Báo cáo', 'despace-core' ); ?></span>
				</div>
			</div>

			<!-- Filter Controls Bar -->
			<div class="despace-filter-card">
				<form id="despace-filter-form" onsubmit="return false;">
					<div class="despace-filter-row">
						<div class="despace-filter-group">
							<label for="despace-date-from"><?php esc_html_e( 'Từ ngày:', 'despace-core' ); ?></label>
							<input type="date" id="despace-date-from" name="from_date" value="<?php echo esc_attr( $thirty_days ); ?>" max="<?php echo esc_attr( $today ); ?>" />
						</div>

						<div class="despace-filter-group">
							<label for="despace-date-to"><?php esc_html_e( 'Đến ngày:', 'despace-core' ); ?></label>
							<input type="date" id="despace-date-to" name="to_date" value="<?php echo esc_attr( $today ); ?>" max="<?php echo esc_attr( $today ); ?>" />
						</div>

						<div class="despace-filter-presets">
							<button type="button" class="button despace-preset-btn" data-preset="today"><?php esc_html_e( 'Hôm nay', 'despace-core' ); ?></button>
							<button type="button" class="button despace-preset-btn" data-preset="last7"><?php esc_html_e( '7 ngày qua', 'despace-core' ); ?></button>
							<button type="button" class="button despace-preset-btn active" data-preset="last30"><?php esc_html_e( '30 ngày qua', 'despace-core' ); ?></button>
							<button type="button" class="button despace-preset-btn" data-preset="thisMonth"><?php esc_html_e( 'Tháng này', 'despace-core' ); ?></button>
						</div>

						<div class="despace-filter-action">
							<button type="button" id="despace-btn-apply-filter" class="button button-primary">
								<span class="dashicons dashicons-filter"></span> <?php esc_html_e( 'Lọc báo cáo', 'despace-core' ); ?>
							</button>
						</div>
					</div>
				</form>
				<div id="despace-filter-loading" class="despace-loading-indicator" style="display: none;">
					<span class="spinner is-active" style="float: none; margin: 0 8px 0 0;"></span>
					<span><?php esc_html_e( 'Đang tính toán số liệu từ WooCommerce HPOS...', 'despace-core' ); ?></span>
				</div>
			</div>

			<!-- Error Alert Container -->
			<div id="despace-error-box" class="notice notice-error" style="display: none; margin: 16px 0;">
				<p id="despace-error-message"></p>
			</div>

			<!-- Metrics Summary Grid -->
			<div class="despace-metrics-grid">
				<!-- Metric Card 1: Doanh thu hợp lệ -->
				<div class="despace-metric-card despace-card-revenue">
					<div class="despace-card-icon"><span class="dashicons dashicons-money-alt"></span></div>
					<div class="despace-card-content">
						<span class="despace-card-label"><?php esc_html_e( 'Doanh thu hợp lệ', 'despace-core' ); ?></span>
						<h3 id="metric-revenue" class="despace-card-value">—</h3>
						<span class="despace-card-hint">
							<span class="dashicons dashicons-yes-alt"></span>
							<?php esc_html_e( 'Chỉ tính đơn Hoàn thành & Đang xử lý (đã trừ hủy/chưa đối soát)', 'despace-core' ); ?>
						</span>
					</div>
				</div>

				<!-- Metric Card 2: Tổng đơn hàng -->
				<div class="despace-metric-card despace-card-orders">
					<div class="despace-card-icon"><span class="dashicons dashicons-cart"></span></div>
					<div class="despace-card-content">
						<span class="despace-card-label"><?php esc_html_e( 'Tổng số đơn hàng', 'despace-core' ); ?></span>
						<h3 id="metric-total-orders" class="despace-card-value">—</h3>
						<span class="despace-card-hint">
							<span id="metric-valid-orders-hint">—</span> <?php esc_html_e( 'đơn hợp lệ phát sinh doanh thu', 'despace-core' ); ?>
						</span>
					</div>
				</div>

				<!-- Metric Card 3: Sản phẩm có 3D/AR -->
				<div class="despace-metric-card despace-card-3d">
					<div class="despace-card-icon"><span class="dashicons dashicons-visibility"></span></div>
					<div class="despace-card-content">
						<span class="despace-card-label"><?php esc_html_e( 'Sản phẩm có mô hình 3D', 'despace-core' ); ?></span>
						<h3 id="metric-3d-count" class="despace-card-value">—</h3>
						<span class="despace-card-hint">
							<span id="metric-3d-percentage">—</span>% <?php esc_html_e( 'trên tổng số danh mục', 'despace-core' ); ?>
						</span>
					</div>
				</div>

				<!-- Metric Card 4: Giá trị đơn trung bình (AOV) -->
				<div class="despace-metric-card despace-card-aov">
					<div class="despace-card-icon"><span class="dashicons dashicons-chart-pie"></span></div>
					<div class="despace-card-content">
						<span class="despace-card-label"><?php esc_html_e( 'Giá trị trung bình / đơn (AOV)', 'despace-core' ); ?></span>
						<h3 id="metric-aov" class="despace-card-value">—</h3>
						<span class="despace-card-hint">
							<?php esc_html_e( 'Doanh thu hợp lệ / Đơn hợp lệ', 'despace-core' ); ?>
						</span>
					</div>
				</div>
			</div>

			<!-- Order Status Breakdown & 3D Catalog Status -->
			<div class="despace-section-grid">
				<div class="despace-panel">
					<div class="despace-panel-header">
						<h2><span class="dashicons dashicons-index-card"></span> <?php esc_html_e( 'Phân Bổ Trạng Thái Đơn Hàng', 'despace-core' ); ?></h2>
					</div>
					<div class="despace-panel-body">
						<div id="despace-status-bars" class="despace-status-bars-container">
							<!-- Rendered dynamically by ES6 JavaScript -->
						</div>
					</div>
				</div>

				<div class="despace-panel">
					<div class="despace-panel-header">
						<h2><span class="dashicons dashicons-info"></span> <?php esc_html_e( 'Ranh Giới Dữ Liệu & Đối Soát', 'despace-core' ); ?></h2>
					</div>
					<div class="despace-panel-body">
						<ul class="despace-audit-notes">
							<li>
								<strong><?php esc_html_e( 'Nguồn dữ liệu:', 'despace-core' ); ?></strong>
								<?php esc_html_e( 'WooCommerce CRUD API (wc_get_orders) tuân thủ kiến trúc HPOS.', 'despace-core' ); ?>
							</li>
							<li>
								<strong><?php esc_html_e( 'Đơn chuyển khoản ngân hàng:', 'despace-core' ); ?></strong>
								<?php esc_html_e( 'Trạng thái Chờ tạm giữ (on-hold) tuyệt đối không cộng vào doanh thu khi chưa có đối soát thành công.', 'despace-core' ); ?>
							</li>
							<li>
								<strong><?php esc_html_e( 'Kiểm soát quyền:', 'despace-core' ); ?></strong>
								<?php esc_html_e( 'Chỉ nhân viên (despace_staff) và Quản trị viên (administrator) có capability view_despace_reports mới được truy cập dữ liệu này.', 'despace-core' ); ?>
							</li>
						</ul>
					</div>
				</div>
			</div>

			<!-- Top 10 Best Sellers Table -->
			<div class="despace-panel despace-panel-table">
				<div class="despace-panel-header" style="display: flex; justify-content: space-between; align-items: center;">
					<h2><span class="dashicons dashicons-awards"></span> <?php esc_html_e( 'Top 10 Sản Phẩm Bán Chạy Nhất', 'despace-core' ); ?></h2>
					<span class="despace-table-subtitle"><?php esc_html_e( 'Dựa trên số lượng đã giao & đang xử lý trong kỳ', 'despace-core' ); ?></span>
				</div>
				<div class="despace-panel-body" style="padding: 0;">
					<table class="wp-list-table widefat fixed striped despace-table">
						<thead>
							<tr>
								<th scope="col" style="width: 50px; text-align: center;"><?php esc_html_e( '#', 'despace-core' ); ?></th>
								<th scope="col" style="width: 60px;"><?php esc_html_e( 'Ảnh', 'despace-core' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Tên sản phẩm', 'despace-core' ); ?></th>
								<th scope="col" style="width: 140px;"><?php esc_html_e( 'Mã SKU', 'despace-core' ); ?></th>
								<th scope="col" style="width: 130px; text-align: center;"><?php esc_html_e( 'Mô hình 3D AR', 'despace-core' ); ?></th>
								<th scope="col" style="width: 130px; text-align: right;"><?php esc_html_e( 'Số lượng bán', 'despace-core' ); ?></th>
								<th scope="col" style="width: 160px; text-align: right;"><?php esc_html_e( 'Doanh thu', 'despace-core' ); ?></th>
							</tr>
						</thead>
						<tbody id="despace-top-products-tbody">
							<tr>
								<td colspan="7" style="text-align: center; padding: 24px;">
									<span class="spinner is-active" style="float: none; margin: 0 6px 0 0;"></span>
									<?php esc_html_e( 'Đang tải danh sách...', 'despace-core' ); ?>
								</td>
							</tr>
						</tbody>
					</table>
				</div>
			</div>
		</div>
		<?php
	}
}

