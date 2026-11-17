<?php
/**
 * Inventory Logs CSV Exporter.
 *
 * @package DeSpace\Inventory\Admin
 */

namespace DeSpace\Inventory\Admin;

use DeSpace\Inventory\Database\InventoryLogInstaller;

defined( 'ABSPATH' ) || exit;

/**
 * Class InventoryExportService
 */
class InventoryExportService {

	/**
	 * Export inventory logs matching filters as CSV.
	 *
	 * @param array         $filters       Query filters.
	 * @param resource|null $output_stream Optional writable stream for testing/direct export.
	 */
	public static function export_csv( array $filters = array(), $output_stream = null ): void {
		if ( ! current_user_can( 'view_despace_inventory_logs' ) && ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'despace-core' ) );
		}

		global $wpdb;
		$table_name = InventoryLogInstaller::get_table_name();

		$where_clauses = array( '1=1' );
		$where_values  = array();

		if ( ! empty( $filters['event_type'] ) ) {
			$where_clauses[] = 'event_type = %s';
			$where_values[]  = sanitize_key( $filters['event_type'] );
		}

		if ( ! empty( $filters['product_id'] ) ) {
			$where_clauses[] = 'product_id = %d';
			$where_values[]  = absint( $filters['product_id'] );
		}

		if ( ! empty( $filters['date_from'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $filters['date_from'] ) ) {
			$where_clauses[] = 'created_at >= %s';
			$where_values[]  = $filters['date_from'] . ' 00:00:00';
		}

		if ( ! empty( $filters['date_to'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $filters['date_to'] ) ) {
			$where_clauses[] = 'created_at <= %s';
			$where_values[]  = $filters['date_to'] . ' 23:59:59';
		}

		$where_sql = implode( ' AND ', $where_clauses );
		$query     = "SELECT * FROM {$table_name} WHERE {$where_sql} ORDER BY created_at DESC, id DESC LIMIT 5000";

		$rows = ! empty( $where_values )
			? $wpdb->get_results( $wpdb->prepare( $query, $where_values ), ARRAY_A )
			: $wpdb->get_results( $query, ARRAY_A );

		$is_direct_stream = null !== $output_stream && is_resource( $output_stream );
		$output           = $is_direct_stream ? $output_stream : fopen( 'php://output', 'w' );

		if ( ! $is_direct_stream ) {
			// Clean output buffer.
			if ( ob_get_level() ) {
				ob_end_clean();
			}

			$filename = 'despace-inventory-logs-' . gmdate( 'Y-m-d-His' ) . '.csv';

			header( 'Content-Type: text/csv; charset=UTF-8' );
			header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
			header( 'Pragma: no-cache' );
			header( 'Expires: 0' );
		}

		// UTF-8 BOM for Excel compatibility.
		fputs( $output, "\xEF\xBB\xBF" );

		// Header columns.
		fputcsv( $output, array(
			'ID',
			'Event Key',
			'Event Type',
			'Source',
			'Product ID',
			'Variation ID',
			'Order ID',
			'Order Item ID',
			'Refund ID',
			'Actor User ID',
			'Quantity Before',
			'Quantity Delta',
			'Stock After',
			'Created At (UTC)',
		) );

		foreach ( (array) $rows as $row ) {
			$escaped_row = array(
				$row['id'],
				self::escape_csv_cell( $row['event_key'] ),
				self::escape_csv_cell( $row['event_type'] ),
				self::escape_csv_cell( $row['event_source'] ),
				$row['product_id'],
				$row['variation_id'] ?? '',
				$row['order_id'] ?? '',
				$row['order_item_id'] ?? '',
				$row['refund_id'] ?? '',
				$row['actor_user_id'] ?? '',
				$row['quantity_before'] ?? '',
				$row['quantity_delta'],
				$row['stock_after'] ?? '',
				$row['created_at'],
			);
			fputcsv( $output, $escaped_row );
		}

		if ( ! $is_direct_stream ) {
			fclose( $output );
			exit;
		}
	}

	/**
	 * Escape CSV cell to prevent formula injection in spreadsheet applications.
	 *
	 * Values beginning with =, +, -, @, \t, or \r can be interpreted as formulas.
	 *
	 * @param mixed $value
	 * @return mixed
	 */
	public static function escape_csv_cell( $value ) {
		if ( is_string( $value ) && '' !== $value ) {
			$first_char = $value[0];
			if ( in_array( $first_char, array( '=', '+', '-', '@', "\t", "\r" ), true ) ) {
				if ( ! is_numeric( $value ) ) {
					return "'" . $value;
				}
			}
		}
		return $value;
	}
}

