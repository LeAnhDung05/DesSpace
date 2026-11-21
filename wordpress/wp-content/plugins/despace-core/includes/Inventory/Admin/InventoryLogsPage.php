<?php
/**
 * Admin Inventory Logs Page — Minimalist Luxury DeSpace Styling.
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
	 * Register the admin menu and action hooks.
	 */
	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'register_page' ) );
	}

	/**
	 * Register the menu page.
	 */
	public static function register_page(): void {
		$capability = 'view_despace_inventory_logs';
		if ( ! current_user_can( $capability ) && current_user_can( 'manage_woocommerce' ) ) {
			$capability = 'manage_woocommerce';
		}

		$hook = add_submenu_page(
			'woocommerce',
			__( 'DeSpace — Inventory Logs', 'despace-core' ),
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

		// Ensure columns are properly registered for this screen.
		if ( $hook ) {
			add_filter( "manage_{$hook}_columns", array( __CLASS__, 'get_table_columns' ) );
		}
		add_filter( 'manage_woocommerce_page_despace-inventory-logs_columns', array( __CLASS__, 'get_table_columns' ) );

		// Handle CSV export action early before headers are sent.
		add_action( 'admin_init', array( __CLASS__, 'handle_export' ) );
	}

	/**
	 * Provide columns callback for screen options.
	 */
	public static function get_table_columns(): array {
		$table = new InventoryLogsTable();
		return $table->get_columns();
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
		$current_product_id = ( isset( $_GET['product_id'] ) && '' !== trim( (string) $_GET['product_id'] ) && absint( $_GET['product_id'] ) > 0 ) ? absint( $_GET['product_id'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

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
		<style>
			.despace-audit-wrap {
				max-width: 1400px;
				margin: 24px 20px 48px 0;
				font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
				color: #1C1917;
			}
			.despace-audit-wrap .wp-list-table {
				border: none;
				box-shadow: none;
				border-collapse: collapse;
				width: 100%;
			}
			.despace-audit-wrap .wp-list-table th {
				background: #FAF8F5;
				color: #78716C;
				font-size: 11px;
				font-weight: 700;
				text-transform: uppercase;
				letter-spacing: 0.6px;
				padding: 14px 16px;
				border-bottom: 1px solid #E7E3DC;
				border-top: none;
			}
			.despace-audit-wrap .wp-list-table td {
				padding: 14px 16px;
				font-size: 13px;
				color: #1C1917;
				border-bottom: 1px solid #F3F1EC;
				vertical-align: middle;
			}
			.despace-audit-wrap .wp-list-table tbody tr:hover td {
				background: #FCFBF9;
			}
			.despace-kpi-card {
				background: #fff;
				border: 1px solid #E7E3DC;
				border-radius: 12px;
				padding: 20px 22px;
				transition: border-color 0.2s ease, box-shadow 0.2s ease;
			}
			.despace-kpi-card:hover {
				border-color: #D6CFC7;
				box-shadow: 0 4px 16px rgba(0,0,0,0.03);
			}
			.despace-filter-card {
				background: #fff;
				border: 1px solid #E7E3DC;
				border-radius: 12px;
				padding: 20px 24px;
				margin-bottom: 24px;
			}
			.despace-btn-terracotta {
				display: inline-flex;
				align-items: center;
				gap: 8px;
				background: #C25E3A !important;
				border: 1px solid #C25E3A !important;
				color: #FAF7F2 !important;
				font-weight: 600 !important;
				padding: 8px 18px !important;
				height: auto !important;
				border-radius: 8px !important;
				font-size: 13.5px !important;
				text-decoration: none !important;
				box-shadow: 0 2px 8px rgba(194,94,58,0.25);
				transition: background 0.15s ease;
			}
			.despace-btn-terracotta:hover {
				background: #A84A28 !important;
				border-color: #A84A28 !important;
			}
			.despace-btn-ghost {
				display: inline-flex;
				align-items: center;
				gap: 8px;
				background: rgba(255,255,255,0.06) !important;
				border: 1px solid rgba(255,255,255,0.2) !important;
				color: #FAF7F2 !important;
				font-weight: 600 !important;
				padding: 8px 16px !important;
				height: auto !important;
				border-radius: 8px !important;
				font-size: 13.5px !important;
				text-decoration: none !important;
				transition: background 0.15s ease, border-color 0.15s ease;
			}
			.despace-btn-ghost:hover {
				background: rgba(255,255,255,0.12) !important;
				border-color: rgba(255,255,255,0.35) !important;
				color: #fff !important;
			}
		</style>

		<div class="wrap despace-audit-wrap">

			<!-- 1. Executive Brand Header (Minimalist Luxury Studio Theme) -->
			<div style="background:#1C1917; border-radius:14px; padding:28px 32px; color:#fff; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:20px; box-shadow:0 8px 24px rgba(28,25,23,0.12); margin-bottom:24px;">
				<div>
					<div style="display:flex; align-items:center; gap:12px; margin-bottom:8px;">
						<span style="font-size:20px; font-weight:800; letter-spacing:-0.4px; color:#FAF7F2;">DeSpace</span>
						<span style="border:1px solid rgba(255,255,255,0.2); font-size:10.5px; font-weight:600; letter-spacing:1px; padding:3px 10px; border-radius:99px; text-transform:uppercase; color:#D6CFC7;">Inventory Audit Engine</span>
					</div>
					<h1 style="color:#FAF7F2; font-size:24px; font-weight:700; margin:0 0 6px; padding:0;"><?php esc_html_e( 'Nhật Ký Kiểm Toán Tồn Kho (Audit Logs)', 'despace-core' ); ?></h1>
					<p style="color:#A8A29E; margin:0; font-size:13.5px; max-width:680px; line-height:1.5;">
						<?php esc_html_e( 'Truy vết chi tiết mọi biến động số lượng tồn kho theo thời gian thực (đơn hàng mới, nhập xưởng, điều chỉnh, hoàn trả) chuẩn WooCommerce HPOS.', 'despace-core' ); ?>
					</p>
				</div>
				<div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
					<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=product' ) ); ?>" class="despace-btn-terracotta">
						<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
						<span>Cập Nhật Kho</span>
					</a>
					<a href="<?php echo esc_url( $export_url ); ?>" class="despace-btn-ghost">
						<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
						<span>Xuất File CSV</span>
					</a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=despace-hub' ) ); ?>" class="despace-btn-ghost">
						<span>DeSpace Hub</span>
						<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
					</a>
				</div>
			</div>

			<!-- 2. Core KPI Metric Cards (Clean Minimalism) -->
			<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:16px; margin-bottom:24px;">
				
				<div class="despace-kpi-card">
					<div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:10px;">
						<span style="font-size:11.5px; font-weight:700; color:#78716C; text-transform:uppercase; letter-spacing:0.6px;"><?php esc_html_e( 'Tổng Bản Ghi', 'despace-core' ); ?></span>
						<span style="color:#78716C;">
							<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="9" ry="3"></ellipse><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"></path><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"></path></svg>
						</span>
					</div>
					<div style="font-size:28px; font-weight:700; color:#1C1917; margin-bottom:4px; font-variant-numeric:tabular-nums;"><?php echo esc_html( number_format_i18n( $total_logs ) ); ?></div>
					<div style="font-size:12px; color:#A8A29E;"><?php esc_html_e( 'Audit logs thời gian thực', 'despace-core' ); ?></div>
				</div>

				<div class="despace-kpi-card">
					<div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:10px;">
						<span style="font-size:11.5px; font-weight:700; color:#78716C; text-transform:uppercase; letter-spacing:0.6px;"><?php esc_html_e( 'Giảm Kho Bán Hàng', 'despace-core' ); ?></span>
						<span style="color:#B91C1C;">
							<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"></path><path d="M3 6h18"></path><path d="M16 10a4 4 0 0 1-8 0"></path></svg>
						</span>
					</div>
					<div style="font-size:28px; font-weight:700; color:#B91C1C; margin-bottom:4px; font-variant-numeric:tabular-nums;"><?php echo esc_html( number_format_i18n( $sales_count ) ); ?></div>
					<div style="font-size:12px; color:#A8A29E;"><?php esc_html_e( 'Đơn hàng bán ra tự động', 'despace-core' ); ?></div>
				</div>

				<div class="despace-kpi-card">
					<div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:10px;">
						<span style="font-size:11.5px; font-weight:700; color:#78716C; text-transform:uppercase; letter-spacing:0.6px;"><?php esc_html_e( 'Hoàn Kho & Restock', 'despace-core' ); ?></span>
						<span style="color:#15803D;">
							<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline><polyline points="17 6 23 6 23 12"></polyline></svg>
						</span>
					</div>
					<div style="font-size:28px; font-weight:700; color:#15803D; margin-bottom:4px; font-variant-numeric:tabular-nums;"><?php echo esc_html( number_format_i18n( $restock_count ) ); ?></div>
					<div style="font-size:12px; color:#A8A29E;"><?php esc_html_e( 'Hủy đơn & hoàn trả', 'despace-core' ); ?></div>
				</div>

				<div class="despace-kpi-card">
					<div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:10px;">
						<span style="font-size:11.5px; font-weight:700; color:#78716C; text-transform:uppercase; letter-spacing:0.6px;"><?php esc_html_e( 'Chỉnh Sửa Thủ Công', 'despace-core' ); ?></span>
						<span style="color:#D97706;">
							<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><line x1="4" y1="21" x2="4" y2="14"></line><line x1="4" y1="10" x2="4" y2="3"></line><line x1="12" y1="21" x2="12" y2="12"></line><line x1="12" y1="8" x2="12" y2="3"></line><line x1="20" y1="21" x2="20" y2="16"></line><line x1="20" y1="12" x2="20" y2="3"></line></svg>
						</span>
					</div>
					<div style="font-size:28px; font-weight:700; color:#D97706; margin-bottom:4px; font-variant-numeric:tabular-nums;"><?php echo esc_html( number_format_i18n( $manual_count ) ); ?></div>
					<div style="font-size:12px; color:#A8A29E;"><?php esc_html_e( 'Nhập xưởng & kiểm kê', 'despace-core' ); ?></div>
				</div>

				<div class="despace-kpi-card">
					<div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:10px;">
						<span style="font-size:11.5px; font-weight:700; color:#78716C; text-transform:uppercase; letter-spacing:0.6px;"><?php esc_html_e( 'Tình Trạng Đối Soát', 'despace-core' ); ?></span>
						<span style="color:#15803D;">
							<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path><polyline points="9 12 11 14 15 10"></polyline></svg>
						</span>
					</div>
					<div style="font-size:22px; font-weight:700; color:#15803D; margin-bottom:4px; line-height:34px;">100% Khớp Kho</div>
					<div style="font-size:12px; color:#A8A29E;">Idempotent & HPOS Ready</div>
				</div>

			</div>

			<!-- 3. Minimalist Filter Bar -->
			<div class="despace-filter-card">
				<form method="get" action="" style="display:flex; gap:16px; align-items:flex-end; flex-wrap:wrap;">
					<input type="hidden" name="page" value="despace-inventory-logs" />

					<div style="display:flex; flex-direction:column; gap:6px;">
						<label for="event_type_filter" style="font-size:11.5px; font-weight:700; color:#78716C; text-transform:uppercase; letter-spacing:0.6px;"><?php esc_html_e( 'Loại sự kiện:', 'despace-core' ); ?></label>
						<select name="event_type" id="event_type_filter" style="height:36px; border-radius:6px; border:1px solid #D6CFC7; padding:0 12px; min-width:190px; font-size:13px; color:#1C1917; background:#fff;">
							<option value=""><?php esc_html_e( '— Tất cả sự kiện —', 'despace-core' ); ?></option>
							<option value="order_reduction" <?php selected( $current_event_type, 'order_reduction' ); ?>><?php esc_html_e( 'Giảm kho đơn hàng', 'despace-core' ); ?></option>
							<option value="stock_restoration" <?php selected( $current_event_type, 'stock_restoration' ); ?>><?php esc_html_e( 'Khôi phục kho', 'despace-core' ); ?></option>
							<option value="refund_restock" <?php selected( $current_event_type, 'refund_restock' ); ?>><?php esc_html_e( 'Hoàn trả kho', 'despace-core' ); ?></option>
							<option value="manual_adjustment" <?php selected( $current_event_type, 'manual_adjustment' ); ?>><?php esc_html_e( 'Chỉnh sửa thủ công', 'despace-core' ); ?></option>
						</select>
					</div>

					<div style="display:flex; flex-direction:column; gap:6px;">
						<label for="product_id_filter" style="font-size:11.5px; font-weight:700; color:#78716C; text-transform:uppercase; letter-spacing:0.6px;"><?php esc_html_e( 'Mã Sản Phẩm:', 'despace-core' ); ?></label>
						<input type="number" id="product_id_filter" name="product_id" value="<?php echo esc_attr( $current_product_id ); ?>" style="height:36px; border-radius:6px; border:1px solid #D6CFC7; padding:0 10px; width:110px; font-size:13px; color:#1C1917;" placeholder="ID" />
					</div>

					<div style="display:flex; flex-direction:column; gap:6px;">
						<label for="date_from_filter" style="font-size:11.5px; font-weight:700; color:#78716C; text-transform:uppercase; letter-spacing:0.6px;"><?php esc_html_e( 'Từ ngày:', 'despace-core' ); ?></label>
						<input type="date" id="date_from_filter" name="date_from" value="<?php echo esc_attr( $current_date_from ); ?>" style="height:36px; border-radius:6px; border:1px solid #D6CFC7; padding:0 10px; font-size:12.5px; color:#1C1917;" />
					</div>

					<div style="display:flex; flex-direction:column; gap:6px;">
						<label for="date_to_filter" style="font-size:11.5px; font-weight:700; color:#78716C; text-transform:uppercase; letter-spacing:0.6px;"><?php esc_html_e( 'Đến ngày:', 'despace-core' ); ?></label>
						<input type="date" id="date_to_filter" name="date_to" value="<?php echo esc_attr( $current_date_to ); ?>" style="height:36px; border-radius:6px; border:1px solid #D6CFC7; padding:0 10px; font-size:12.5px; color:#1C1917;" />
					</div>

					<div style="display:flex; gap:8px;">
						<button type="submit" class="button" style="height:36px; padding:0 16px; border-radius:6px; background:#1C1917; border-color:#1C1917; color:#fff; font-weight:600; cursor:pointer; display:inline-flex; align-items:center; gap:6px;">
							<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon></svg>
							<span>Lọc</span>
						</button>
						<?php if ( ! empty( $current_event_type ) || ! empty( $current_product_id ) || ! empty( $current_date_from ) || ! empty( $current_date_to ) ) : ?>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=despace-inventory-logs' ) ); ?>" class="button" style="height:36px; line-height:34px; padding:0 14px; border-radius:6px; border-color:#D6CFC7; color:#78716C; font-weight:600; text-decoration:none;">
								<?php esc_html_e( 'Đặt Lại', 'despace-core' ); ?>
							</a>
						<?php endif; ?>
					</div>
				</form>
			</div>

			<!-- 4. Clean Table Container Card -->
			<div style="background:#fff; border:1px solid #E7E3DC; border-radius:12px; padding:12px 18px 20px; box-shadow:0 2px 8px rgba(0,0,0,0.02); overflow-x:auto;">
				<form method="get" action="">
					<input type="hidden" name="page" value="despace-inventory-logs" />
					<?php $table->display(); ?>
				</form>
			</div>

		</div>
		<?php
	}
}
