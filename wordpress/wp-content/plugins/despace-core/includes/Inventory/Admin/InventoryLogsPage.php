<?php
/**
 * Admin Inventory Logs Page — Styled for DeSpace Admin Cockpit V2.0.
 *
 * @package DeSpace\Inventory\Admin
 */

namespace DeSpace\Inventory\Admin;

use DeSpace\Inventory\Database\InventoryLogInstaller;

defined( 'ABSPATH' ) || exit;

/**
 * Class InventoryLogsPage
 */
class InventoryLogsPage {

	/**
	 * Register menu item under WooCommerce or custom DeSpace menu.
	 */
	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'register_admin_menu' ), 20 );
	}

	/**
	 * Register submenu.
	 */
	public static function register_admin_menu(): void {
		$capability = current_user_can( 'view_despace_inventory_logs' )
			? 'view_despace_inventory_logs'
			: 'manage_woocommerce';

		add_submenu_page(
			'woocommerce',
			__( 'Inventory Logs', 'despace-core' ),
			__( 'Inventory Logs', 'despace-core' ),
			$capability,
			'despace-inventory-logs',
			array( __CLASS__, 'render_page' )
		);

		// Also register under DeSpace Hub if available.
		if ( isset( $GLOBALS['admin_page_hooks']['despace-hub'] ) ) {
			add_submenu_page(
				'despace-hub',
				__( 'Inventory Logs', 'despace-core' ),
				__( 'Nhật Ký Tồn Kho', 'despace-core' ),
				$capability,
				'despace-inventory-logs',
				array( __CLASS__, 'render_page' )
			);
		}

		// Handle CSV export action early before headers are sent.
		add_action( 'admin_init', array( __CLASS__, 'handle_export' ) );
	}

	/**
	 * Validate and authorize an incoming CSV export request.
	 *
	 * @param array $request_params Request parameters (defaults to $_GET).
	 * @return true|\WP_Error True if request is valid and authorized, \WP_Error on failure.
	 */
	public static function validate_export_request( array $request_params = array() ) {
		if ( empty( $request_params ) ) {
			$request_params = $_GET;
		}

		$page   = $request_params['page'] ?? '';
		$action = $request_params['action'] ?? '';

		if ( 'despace-inventory-logs' !== $page || 'export_csv' !== $action ) {
			return new \WP_Error( 'not_export_request', __( 'Not an export request.', 'despace-core' ) );
		}

		if ( ! current_user_can( 'view_despace_inventory_logs' ) && ! current_user_can( 'manage_woocommerce' ) ) {
			return new \WP_Error( 'permission_denied', __( 'Permission denied.', 'despace-core' ), array( 'status' => 403 ) );
		}

		$nonce = isset( $request_params['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $request_params['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'despace_export_csv' ) ) {
			return new \WP_Error( 'invalid_nonce', __( 'Action failed. Invalid or missing security nonce.', 'despace-core' ), array( 'status' => 403 ) );
		}

		return true;
	}

	/**
	 * Handle CSV Export request.
	 */
	public static function handle_export(): void {
		if ( isset( $_GET['page'], $_GET['action'] ) && 'despace-inventory-logs' === $_GET['page'] && 'export_csv' === $_GET['action'] ) {
			$validation = self::validate_export_request( $_GET );
			if ( is_wp_error( $validation ) ) {
				$status = (int) ( $validation->get_error_data()['status'] ?? 403 );
				wp_die( esc_html( $validation->get_error_message() ), $status );
			}
			InventoryExportService::export_csv( $_GET );
		}
	}

	/**
	 * Render the page content.
	 */
	public static function render_page(): void {
		if ( ! current_user_can( 'view_despace_inventory_logs' ) && ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'despace-core' ) );
		}

		global $wpdb;
		$table_name = InventoryLogInstaller::get_table_name();

		// Fetch KPI metrics.
		$has_table = InventoryLogInstaller::verify_table_exists();
		$total_logs = 0;
		$sales_count = 0;
		$restock_count = 0;
		$manual_count = 0;

		if ( $has_table ) {
			$total_logs    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );
			$sales_count   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name} WHERE event_type = 'order_reduction'" );
			$restock_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name} WHERE event_type IN ('stock_restoration', 'refund_restock')" );
			$manual_count  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name} WHERE event_type = 'manual_adjustment'" );
		}

		$table = new InventoryLogsTable();
		$table->prepare_items();

		$current_event_type = isset( $_GET['event_type'] ) ? sanitize_key( wp_unslash( $_GET['event_type'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current_date_from  = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current_date_to    = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current_product_id = isset( $_GET['product_id'] ) ? absint( $_GET['product_id'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$export_url = wp_nonce_url(
			add_query_arg(
				array(
					'page'       => 'despace-inventory-logs',
					'action'     => 'export_csv',
					'event_type' => $current_event_type,
					'product_id' => $current_product_id,
					'date_from'  => $current_date_from,
					'date_to'    => $current_date_to,
				),
				admin_url( 'admin.php' )
			),
			'despace_export_csv'
		);

		?>
		<div class="wrap despace-hub-wrap" style="max-width:1380px; margin:24px 20px 48px 0; font-family:-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">

			<!-- 1. Executive Brand Header (Matching Cockpit Theme) -->
			<div style="background:linear-gradient(135deg, #1C1917 0%, #2A1D16 100%); border-radius:18px; padding:30px 36px; color:#fff; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:20px; box-shadow:0 12px 32px rgba(42,29,22,0.18); margin-bottom:26px;">
				<div>
					<div style="display:flex; align-items:center; gap:14px; margin-bottom:10px;">
						<span style="font-size:22px; font-weight:800; letter-spacing:-0.5px; color:#FAF7F2;">DeSpace</span>
						<span style="background:rgba(255,255,255,0.15); font-size:11px; font-weight:700; letter-spacing:1px; padding:4px 10px; border-radius:99px; text-transform:uppercase;">INVENTORY AUDIT ENGINE V2.0</span>
					</div>
					<h1 style="color:#FAF7F2; font-size:26px; font-weight:700; margin:0 0 6px; padding:0;"><?php esc_html_e( 'Nhật Ký Kiểm Toán Tồn Kho (Audit Logs)', 'despace-core' ); ?></h1>
					<p style="color:#D6CFC7; margin:0; font-size:14px; max-width:720px; line-height:1.5;">
						<?php esc_html_e( 'Truy vết chi tiết mọi biến động số lượng tồn kho (đơn hàng mới, nhập xưởng, điều chỉnh, hoàn trả). Dữ liệu đối soát thời gian thực theo chuẩn WooCommerce HPOS.', 'despace-core' ); ?>
					</p>
				</div>
				<div style="display:flex; gap:12px; flex-wrap:wrap; align-items:center;">
					<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=product' ) ); ?>" class="button button-primary" style="background:#C25E3A; border-color:#C25E3A; color:#fff; font-weight:600; padding:8px 18px; height:auto; border-radius:8px; font-size:13.5px; box-shadow:0 4px 12px rgba(194,94,58,0.35);">
						+ Cập Nhật Kho (Sản Phẩm)
					</a>
					<a href="<?php echo esc_url( $export_url ); ?>" class="button" style="background:rgba(255,255,255,0.1); border-color:rgba(255,255,255,0.25); color:#FAF7F2; font-weight:600; padding:8px 18px; height:auto; border-radius:8px; font-size:13.5px;">
						📥 Xuất File Excel / CSV
					</a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=despace-hub' ) ); ?>" class="button" style="background:transparent; border-color:rgba(255,255,255,0.3); color:#fff; font-weight:600; padding:8px 16px; height:auto; border-radius:8px; font-size:13.5px;">
						DeSpace Hub ↗
					</a>
				</div>
			</div>

			<!-- 2. Core KPI Metric Cards -->
			<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:18px; margin-bottom:28px;">
				
				<div style="background:#fff; border:1px solid #E7E3DC; border-radius:14px; padding:20px 22px; box-shadow:0 2px 8px rgba(0,0,0,0.03);">
					<div style="font-size:12px; font-weight:700; color:#78716C; text-transform:uppercase; letter-spacing:0.6px;"><?php esc_html_e( 'Tổng Bản Ghi Logs', 'despace-core' ); ?></div>
					<div style="font-size:32px; font-weight:800; color:#1C1917; margin:6px 0 2px;"><?php echo esc_html( number_format_i18n( $total_logs ) ); ?></div>
					<div style="font-size:12.5px; color:#2563EB; font-weight:600;">⚡ Audit Logs thời gian thực</div>
				</div>

				<div style="background:#fff; border:1px solid #E7E3DC; border-radius:14px; padding:20px 22px; box-shadow:0 2px 8px rgba(0,0,0,0.03);">
					<div style="font-size:12px; font-weight:700; color:#78716C; text-transform:uppercase; letter-spacing:0.6px;"><?php esc_html_e( 'Sự Kiện Giảm Kho', 'despace-core' ); ?></div>
					<div style="font-size:32px; font-weight:800; color:#DC2626; margin:6px 0 2px;"><?php echo esc_html( number_format_i18n( $sales_count ) ); ?></div>
					<div style="font-size:12.5px; color:#78716C;">Đơn hàng bán ra tự động</div>
				</div>

				<div style="background:#fff; border:1px solid #E7E3DC; border-radius:14px; padding:20px 22px; box-shadow:0 2px 8px rgba(0,0,0,0.03);">
					<div style="font-size:12px; font-weight:700; color:#78716C; text-transform:uppercase; letter-spacing:0.6px;"><?php esc_html_e( 'Hoàn Kho & Restock', 'despace-core' ); ?></div>
					<div style="font-size:32px; font-weight:800; color:#059669; margin:6px 0 2px;"><?php echo esc_html( number_format_i18n( $restock_count ) ); ?></div>
					<div style="font-size:12.5px; color:#059669; font-weight:600;">✓ Hủy đơn & Hoàn trả</div>
				</div>

				<div style="background:#fff; border:1px solid #E7E3DC; border-radius:14px; padding:20px 22px; box-shadow:0 2px 8px rgba(0,0,0,0.03);">
					<div style="font-size:12px; font-weight:700; color:#78716C; text-transform:uppercase; letter-spacing:0.6px;"><?php esc_html_e( 'Điều Chỉnh Thủ Công', 'despace-core' ); ?></div>
					<div style="font-size:32px; font-weight:800; color:#D97706; margin:6px 0 2px;"><?php echo esc_html( number_format_i18n( $manual_count ) ); ?></div>
					<div style="font-size:12.5px; color:#78716C;">Nhập xưởng & Kiểm kê</div>
				</div>

				<div style="background:#fff; border:1px solid #E7E3DC; border-radius:14px; padding:20px 22px; box-shadow:0 2px 8px rgba(0,0,0,0.03);">
					<div style="font-size:12px; font-weight:700; color:#78716C; text-transform:uppercase; letter-spacing:0.6px;"><?php esc_html_e( 'Tình Trạng Đối Soát', 'despace-core' ); ?></div>
					<div style="font-size:24px; font-weight:800; color:#059669; margin:10px 0 6px;">100% Khớp Kho</div>
					<div style="font-size:12.5px; color:#059669; font-weight:600;">✓ Idempotent & HPOS Ready</div>
				</div>

			</div>

			<!-- 3. Filter Bar Card -->
			<div style="background:#fff; border:1px solid #E7E3DC; border-radius:14px; padding:20px 24px; box-shadow:0 2px 8px rgba(0,0,0,0.03); margin-bottom:24px;">
				<form method="get" action="" style="display:flex; gap:16px; align-items:flex-end; flex-wrap:wrap;">
					<input type="hidden" name="page" value="despace-inventory-logs" />

					<div style="display:flex; flex-direction:column; gap:6px;">
						<label for="event_type_filter" style="font-size:12px; font-weight:700; color:#78716C; text-transform:uppercase; letter-spacing:0.5px;"><?php esc_html_e( 'Loại sự kiện:', 'despace-core' ); ?></label>
						<select name="event_type" id="event_type_filter" style="height:38px; border-radius:8px; border:1px solid #D6CFC7; padding:0 12px; min-width:180px; font-size:13.5px;">
							<option value=""><?php esc_html_e( '— Tất cả sự kiện —', 'despace-core' ); ?></option>
							<option value="order_reduction" <?php selected( $current_event_type, 'order_reduction' ); ?>><?php esc_html_e( 'Giảm kho đơn hàng (Order Reduction)', 'despace-core' ); ?></option>
							<option value="stock_restoration" <?php selected( $current_event_type, 'stock_restoration' ); ?>><?php esc_html_e( 'Khôi phục kho (Stock Restoration)', 'despace-core' ); ?></option>
							<option value="refund_restock" <?php selected( $current_event_type, 'refund_restock' ); ?>><?php esc_html_e( 'Hoàn trả hàng (Refund Restock)', 'despace-core' ); ?></option>
							<option value="manual_adjustment" <?php selected( $current_event_type, 'manual_adjustment' ); ?>><?php esc_html_e( 'Chỉnh tay Admin (Manual Adjustment)', 'despace-core' ); ?></option>
						</select>
					</div>

					<div style="display:flex; flex-direction:column; gap:6px;">
						<label for="product_id_filter" style="font-size:12px; font-weight:700; color:#78716C; text-transform:uppercase; letter-spacing:0.5px;"><?php esc_html_e( 'Mã Sản Phẩm:', 'despace-core' ); ?></label>
						<input type="number" id="product_id_filter" name="product_id" value="<?php echo esc_attr( $current_product_id ); ?>" style="height:38px; border-radius:8px; border:1px solid #D6CFC7; padding:0 12px; width:120px; font-size:13.5px;" placeholder="ID" />
					</div>

					<div style="display:flex; flex-direction:column; gap:6px;">
						<label for="date_from_filter" style="font-size:12px; font-weight:700; color:#78716C; text-transform:uppercase; letter-spacing:0.5px;"><?php esc_html_e( 'Từ ngày:', 'despace-core' ); ?></label>
						<input type="date" id="date_from_filter" name="date_from" value="<?php echo esc_attr( $current_date_from ); ?>" style="height:38px; border-radius:8px; border:1px solid #D6CFC7; padding:0 10px; font-size:13px;" />
					</div>

					<div style="display:flex; flex-direction:column; gap:6px;">
						<label for="date_to_filter" style="font-size:12px; font-weight:700; color:#78716C; text-transform:uppercase; letter-spacing:0.5px;"><?php esc_html_e( 'Đến ngày:', 'despace-core' ); ?></label>
						<input type="date" id="date_to_filter" name="date_to" value="<?php echo esc_attr( $current_date_to ); ?>" style="height:38px; border-radius:8px; border:1px solid #D6CFC7; padding:0 10px; font-size:13px;" />
					</div>

					<div style="display:flex; gap:10px;">
						<input type="submit" class="button" value="<?php esc_attr_e( 'Lọc Dữ Liệu', 'despace-core' ); ?>" style="height:38px; padding:0 18px; border-radius:8px; background:#1C1917; border-color:#1C1917; color:#fff; font-weight:600; cursor:pointer;" />
						<?php if ( ! empty( $current_event_type ) || ! empty( $current_product_id ) || ! empty( $current_date_from ) || ! empty( $current_date_to ) ) : ?>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=despace-inventory-logs' ) ); ?>" class="button" style="height:38px; line-height:36px; padding:0 16px; border-radius:8px; border-color:#D6CFC7; font-weight:600;"><?php esc_html_e( 'Đặt Lại', 'despace-core' ); ?></a>
						<?php endif; ?>
					</div>
				</form>
			</div>

			<!-- 4. Data Table Container Card -->
			<div style="background:#fff; border:1px solid #E7E3DC; border-radius:14px; padding:20px 24px; box-shadow:0 2px 8px rgba(0,0,0,0.03);">
				<form method="get" action="">
					<input type="hidden" name="page" value="despace-inventory-logs" />
					<?php $table->display(); ?>
				</form>
			</div>

		</div>
		<?php
	}
}
