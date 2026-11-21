<?php
/**
 * DeSpace Reports and Analytics Module.
 *
 * Provides business intelligence, inventory movement summaries,
 * and executive store metrics for administrators and staff.
 *
 * @package DeSpace\Reports
 */

namespace DeSpace\Reports;

use DeSpace\Security\RoleManager;

defined( 'ABSPATH' ) || exit;

/**
 * Class ReportsModule
 */
class ReportsModule {

	/**
	 * Singleton instance.
	 *
	 * @var ReportsModule|null
	 */
	private static ?ReportsModule $instance = null;

	/**
	 * Initialize module.
	 */
	public static function init(): void {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_reports_menu' ), 60 );
	}

	/**
	 * Register admin reporting menu.
	 */
	public function register_reports_menu(): void {
		if ( ! current_user_can( RoleManager::CAP_VIEW_REPORTS ) && ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$capability = current_user_can( RoleManager::CAP_VIEW_REPORTS )
			? RoleManager::CAP_VIEW_REPORTS
			: ( current_user_can( 'manage_woocommerce' ) ? 'manage_woocommerce' : 'manage_options' );

		add_submenu_page(
			'woocommerce',
			__( 'Báo cáo DeSpace', 'despace-core' ),
			__( 'Báo cáo DeSpace', 'despace-core' ),
			$capability,
			'despace-reports',
			array( $this, 'render_reports_page' )
		);
	}

	/**
	 * Render executive reports dashboard page.
	 */
	public function render_reports_page(): void {
		if ( ! current_user_can( RoleManager::CAP_VIEW_REPORTS ) && ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Bạn không có quyền truy cập trang báo cáo này.', 'despace-core' ), 403 );
		}

		global $wpdb;

		$table_logs = $wpdb->prefix . 'despace_inventory_logs';
		$total_logs = 0;
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table_logs ) ) ) === $table_logs ) {
			$total_logs = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_logs}" );
		}

		$counts         = wp_count_posts( 'product' );
		$total_products = (int) ( $counts->publish ?? 0 );

		$table_3d       = $wpdb->prefix . 'despace_product_3d_models';
		$total_3d_ready = 0;
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table_3d ) ) ) === $table_3d ) {
			$total_3d_ready = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT product_id) FROM {$table_3d} WHERE glb_url IS NOT NULL AND glb_url != ''" );
		}
		$ready_pct = $total_products > 0 ? min( 100, (int) round( ( $total_3d_ready / $total_products ) * 100 ) ) : 0;

		?>
		<div class="wrap despace-reports-wrap">
			<h1><?php esc_html_e( 'DeSpace — Báo cáo & Tổng quan Vận hành', 'despace-core' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Báo cáo tổng hợp số liệu kiểm toán kho, sản phẩm và giao dịch theo thời gian thực.', 'despace-core' ); ?>
			</p>

			<div style="display:flex; gap:20px; margin-top:20px;">
				<div style="flex:1; background:#fff; padding:20px; border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,0.05); border-left:4px solid #9A6745;">
					<h3 style="margin:0 0 10px;"><?php esc_html_e( 'Tổng sản phẩm Catalog', 'despace-core' ); ?></h3>
					<div style="font-size:32px; font-weight:700; color:#262522;"><?php echo esc_html( (string) $total_products ); ?></div>
					<p style="margin:5px 0 0; color:#69645D; font-size:13px;"><?php esc_html_e( 'Đang phát hành trên cửa hàng', 'despace-core' ); ?></p>
				</div>

				<div style="flex:1; background:#fff; padding:20px; border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,0.05); border-left:4px solid #6F806C;">
					<h3 style="margin:0 0 10px;"><?php esc_html_e( 'Nhật ký biến động kho', 'despace-core' ); ?></h3>
					<div style="font-size:32px; font-weight:700; color:#262522;"><?php echo esc_html( (string) $total_logs ); ?></div>
					<p style="margin:5px 0 0; color:#69645D; font-size:13px;"><?php esc_html_e( 'Sự kiện kiểm toán đã ghi nhận', 'despace-core' ); ?></p>
				</div>

				<div style="flex:1; background:#fff; padding:20px; border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,0.05); border-left:4px solid #C5A880;">
					<h3 style="margin:0 0 10px;"><?php esc_html_e( 'Mô hình 3D & AR', 'despace-core' ); ?></h3>
					<div style="font-size:32px; font-weight:700; color:#262522;"><?php echo esc_html( $total_3d_ready . ' / ' . $total_products ); ?></div>
					<p style="margin:5px 0 0; color:#69645D; font-size:13px;"><?php echo esc_html( sprintf( '%d%% Sản phẩm sẵn sàng AR', $ready_pct ) ); ?></p>
				</div>
			</div>
		</div>
		<?php
	}
}
