<?php
/**
 * Inventory Stock Reconciliation Service.
 *
 * @package DeSpace\Inventory\Audit
 */

namespace DeSpace\Inventory\Audit;

use DeSpace\Inventory\Database\InventoryLogInstaller;

defined( 'ABSPATH' ) || exit;

/**
 * Class InventoryReconciler
 *
 * Reconciles logged stock movements against current WooCommerce stock
 * following the formula: expected_stock_after = initial_stock + SUM(quantity_delta).
 */
class InventoryReconciler {

	/**
	 * Run reconciliation check for a specific product or variation.
	 *
	 * @param int      $product_id
	 * @param int|null $variation_id
	 * @return array Audit result.
	 */
	public static function reconcile( int $product_id, ?int $variation_id = null ): array {
		global $wpdb;

		$table_name = InventoryLogInstaller::get_table_name();
		$target_id  = $variation_id && $variation_id > 0 ? $variation_id : $product_id;

		$product = function_exists( 'wc_get_product' ) ? wc_get_product( $target_id ) : null;

		$current_wc_stock = null;
		$sku              = '';
		$product_name     = '';

		if ( $product ) {
			$current_wc_stock = $product->get_stock_quantity();
			$current_wc_stock = null !== $current_wc_stock ? (float) $current_wc_stock : null;
			$sku              = $product->get_sku();
			$product_name     = $product->get_name();
		}

		// Build query conditions for logs.
		$where_clauses = array( 'product_id = %d' );
		$where_values  = array( $product_id );

		if ( $variation_id && $variation_id > 0 ) {
			$where_clauses[] = 'variation_id = %d';
			$where_values[]  = $variation_id;
		}

		$where_sql = implode( ' AND ', $where_clauses );

		// 1. Total events and sum of deltas.
		$agg_sql = "SELECT COUNT(*) as total_events, COALESCE(SUM(quantity_delta), 0) as total_delta FROM {$table_name} WHERE {$where_sql}";
		$agg     = $wpdb->get_row( $wpdb->prepare( $agg_sql, $where_values ), ARRAY_A );

		$total_events = (int) ( $agg['total_events'] ?? 0 );
		$total_delta  = (float) ( $agg['total_delta'] ?? 0.0 );

		// 2. Earliest recorded log for baseline initial stock.
		$first_log_sql = "SELECT quantity_before, stock_after FROM {$table_name} WHERE {$where_sql} ORDER BY created_at ASC, id ASC LIMIT 1";
		$first_log     = $wpdb->get_row( $wpdb->prepare( $first_log_sql, $where_values ), ARRAY_A );

		// 3. Latest recorded log for stock_after verification.
		$last_log_sql = "SELECT stock_after FROM {$table_name} WHERE {$where_sql} ORDER BY created_at DESC, id DESC LIMIT 1";
		$last_log     = $wpdb->get_row( $wpdb->prepare( $last_log_sql, $where_values ), ARRAY_A );

		$initial_stock = null;
		if ( $first_log && null !== $first_log['quantity_before'] ) {
			$initial_stock = (float) $first_log['quantity_before'];
		}

		$latest_logged_stock = null;
		if ( $last_log && null !== $last_log['stock_after'] ) {
			$latest_logged_stock = (float) $last_log['stock_after'];
		}

		$expected_stock = null;
		$discrepancy    = 0.0;
		$is_reconciled  = false;

		if ( null !== $initial_stock ) {
			$expected_stock = $initial_stock + $total_delta;
			if ( null !== $current_wc_stock ) {
				$discrepancy   = abs( $current_wc_stock - $expected_stock );
				$is_reconciled = $discrepancy < 0.000001;
			}
		} elseif ( 0 === $total_events ) {
			// No log events yet.
			$is_reconciled = true;
			$discrepancy   = 0.0;
		}

		return array(
			'product_id'          => $product_id,
			'variation_id'        => $variation_id,
			'sku'                 => $sku,
			'product_name'        => $product_name,
			'current_wc_stock'    => $current_wc_stock,
			'initial_stock'       => $initial_stock,
			'total_events'        => $total_events,
			'total_logged_delta'  => $total_delta,
			'expected_stock'      => $expected_stock,
			'latest_logged_stock' => $latest_logged_stock,
			'discrepancy'         => $discrepancy,
			'is_reconciled'       => $is_reconciled,
		);
	}
}

