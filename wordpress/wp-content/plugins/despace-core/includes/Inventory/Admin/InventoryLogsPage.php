<?php
/**
 * Admin Inventory Logs Page.
 *
 * @package DeSpace\Inventory\Admin
 */

namespace DeSpace\Inventory\Admin;

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

		// Handle CSV export action early before headers are sent.
		add_action( 'admin_init', array( __CLASS__, 'handle_export' ) );
	}

	/**
	 * Handle CSV Export request.
	 */
	public static function handle_export(): void {
		if ( isset( $_GET['page'], $_GET['action'] ) && 'despace-inventory-logs' === $_GET['page'] && 'export_csv' === $_GET['action'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			InventoryExportService::export_csv( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
	}

	/**
	 * Render the page content.
	 */
	public static function render_page(): void {
		if ( ! current_user_can( 'view_despace_inventory_logs' ) && ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'despace-core' ) );
		}

		$table = new InventoryLogsTable();
		$table->prepare_items();

		$current_event_type = isset( $_GET['event_type'] ) ? sanitize_key( wp_unslash( $_GET['event_type'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current_date_from  = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current_date_to    = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current_product_id = isset( $_GET['product_id'] ) ? absint( $_GET['product_id'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'DeSpace — Inventory Audit Logs', 'despace-core' ); ?></h1>
			<hr class="wp-header-end">

			<form method="get" action="">
				<input type="hidden" name="page" value="despace-inventory-logs" />

				<div class="tablenav top" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap; margin-bottom: 12px;">
					<label for="event_type_filter"><?php esc_html_e( 'Event Type:', 'despace-core' ); ?></label>
					<select name="event_type" id="event_type_filter">
						<option value=""><?php esc_html_e( 'All Events', 'despace-core' ); ?></option>
						<option value="order_reduction" <?php selected( $current_event_type, 'order_reduction' ); ?>><?php esc_html_e( 'Order Reduction', 'despace-core' ); ?></option>
						<option value="stock_restoration" <?php selected( $current_event_type, 'stock_restoration' ); ?>><?php esc_html_e( 'Stock Restoration', 'despace-core' ); ?></option>
						<option value="refund_restock" <?php selected( $current_event_type, 'refund_restock' ); ?>><?php esc_html_e( 'Refund Restock', 'despace-core' ); ?></option>
						<option value="manual_adjustment" <?php selected( $current_event_type, 'manual_adjustment' ); ?>><?php esc_html_e( 'Manual Adjustment', 'despace-core' ); ?></option>
					</select>

					<label for="product_id_filter"><?php esc_html_e( 'Product ID:', 'despace-core' ); ?></label>
					<input type="number" id="product_id_filter" name="product_id" value="<?php echo esc_attr( $current_product_id ); ?>" style="width: 100px;" placeholder="ID" />

					<label for="date_from_filter"><?php esc_html_e( 'From:', 'despace-core' ); ?></label>
					<input type="date" id="date_from_filter" name="date_from" value="<?php echo esc_attr( $current_date_from ); ?>" />

					<label for="date_to_filter"><?php esc_html_e( 'To:', 'despace-core' ); ?></label>
					<input type="date" id="date_to_filter" name="date_to" value="<?php echo esc_attr( $current_date_to ); ?>" />

					<input type="submit" class="button" value="<?php esc_attr_e( 'Filter', 'despace-core' ); ?>" />
					<?php
					$export_url = add_query_arg(
						array(
							'page'       => 'despace-inventory-logs',
							'action'     => 'export_csv',
							'event_type' => $current_event_type,
							'product_id' => $current_product_id,
							'date_from'  => $current_date_from,
							'date_to'    => $current_date_to,
						),
						admin_url( 'admin.php' )
					);
					?>
					<a href="<?php echo esc_url( $export_url ); ?>" class="button button-secondary"><?php esc_html_e( 'Export CSV', 'despace-core' ); ?></a>
					<?php if ( ! empty( $current_event_type ) || ! empty( $current_product_id ) || ! empty( $current_date_from ) || ! empty( $current_date_to ) ) : ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=despace-inventory-logs' ) ); ?>" class="button"><?php esc_html_e( 'Reset', 'despace-core' ); ?></a>
					<?php endif; ?>
				</div>

				<?php $table->display(); ?>
			</form>
		</div>
		<?php
	}
}

