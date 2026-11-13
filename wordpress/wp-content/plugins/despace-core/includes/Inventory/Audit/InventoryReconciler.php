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
		$target_id  = ( $variation_id && $variation_id > 0 ) ? $variation_id : $product_id;

		$product = function_exists( 'wc_get_product' ) ? wc_get_product( $target_id ) : null;

		$current_wc_stock      = null;
		$sku                   = '';
		$product_name          = '';
		$stock_scope           = 'simple_product';
		$stock_managed_by_id   = $product_id;
		$reconciliation_status = 'product_not_found';
		$message               = '';

		if ( ! $product ) {
			return array(
				'product_id'            => $product_id,
				'variation_id'          => $variation_id,
				'sku'                   => '',
				'product_name'          => '',
				'current_wc_stock'      => null,
				'initial_stock'         => null,
				'total_events'          => 0,
				'total_logged_delta'    => 0.0,
				'expected_stock'        => null,
				'latest_logged_stock'   => null,
				'discrepancy'           => null,
				'is_reconciled'         => false,
				'reconciliation_status' => 'product_not_found',
				'stock_scope'           => 'product_not_found',
				'stock_managed_by_id'   => null,
				'message'               => __( 'Product or variation does not exist in WooCommerce.', 'despace-core' ),
			);
		}

		$sku          = $product->get_sku();
		$product_name = $product->get_name();

		// Determine stock ownership and target query filters.
		if ( $variation_id && $variation_id > 0 && $product->is_type( 'variation' ) ) {
			$parent_id           = absint( $product->get_parent_id() );
			$stock_managed_by_id = absint( $product->get_stock_managed_by_id() );

			if ( $stock_managed_by_id === $variation_id ) {
				// Individual variation managing its own stock.
				$stock_scope      = 'individual_variation';
				$current_wc_stock = $product->get_stock_quantity();
				$current_wc_stock = null !== $current_wc_stock ? (float) $current_wc_stock : null;

				$where_clauses = array( 'product_id = %d', 'variation_id = %d' );
				$where_values  = array( $parent_id, $variation_id );
				$message       = __( 'Reconciled against individual variation stock.', 'despace-core' );
			} else {
				// Parent-managed variation: stock is pooled at parent product.
				$stock_scope      = 'parent_managed_shared';
				$parent_product   = wc_get_product( $parent_id );
				$current_wc_stock = $parent_product ? $parent_product->get_stock_quantity() : null;
				$current_wc_stock = null !== $current_wc_stock ? (float) $current_wc_stock : null;

				$where_clauses = array( 'product_id = %d', 'variation_id IS NULL' );
				$where_values  = array( $parent_id );
				$message       = sprintf(
					__( 'Parent-managed shared stock pool. Reconciled against parent product #%d.', 'despace-core' ),
					$parent_id
				);
			}
		} elseif ( $product->is_type( 'variable' ) ) {
			$stock_managed_by_id = absint( $product->get_stock_managed_by_id() );
			if ( $product->get_manage_stock() ) {
				$stock_scope      = 'parent_managed_shared';
				$current_wc_stock = $product->get_stock_quantity();
				$current_wc_stock = null !== $current_wc_stock ? (float) $current_wc_stock : null;
				$where_clauses    = array( 'product_id = %d', 'variation_id IS NULL' );
				$where_values     = array( $product_id );
				$message          = __( 'Reconciled against variable parent shared stock pool.', 'despace-core' );
			} else {
				$stock_scope      = 'variable_unmanaged_parent';
				$current_wc_stock = null;
				$where_clauses    = array( 'product_id = %d' );
				$where_values     = array( $product_id );
				$message          = __( 'Variable product parent does not manage stock directly.', 'despace-core' );
			}
		} else {
			// Simple product or other product type.
			$stock_scope         = 'simple_product';
			$stock_managed_by_id = absint( $product->get_stock_managed_by_id() );
			$current_wc_stock    = $product->get_stock_quantity();
			$current_wc_stock    = null !== $current_wc_stock ? (float) $current_wc_stock : null;

			$where_clauses = array( 'product_id = %d', 'variation_id IS NULL' );
			$where_values  = array( $product_id );
			$message       = __( 'Reconciled against simple product stock.', 'despace-core' );
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
		if ( $first_log ) {
			// If quantity_before is recorded, use it as baseline; if null (product was created as new item), baseline is 0.0.
			$initial_stock = null !== $first_log['quantity_before'] ? (float) $first_log['quantity_before'] : 0.0;
		}

		$latest_logged_stock = null;
		if ( $last_log && null !== $last_log['stock_after'] ) {
			$latest_logged_stock = (float) $last_log['stock_after'];
		}

		$expected_stock        = null;
		$discrepancy           = null;
		$is_reconciled         = false;
		$reconciliation_status = 'insufficient_history';

		if ( 0 === $total_events || null === $initial_stock ) {
			$is_reconciled         = false;
			$reconciliation_status = 'insufficient_history';
			$discrepancy           = null;
			$message               = ( '' !== $message ? $message . ' ' : '' ) . __( 'No inventory log history found to establish baseline stock.', 'despace-core' );
		} elseif ( null === $current_wc_stock ) {
			$expected_stock        = $initial_stock + $total_delta;
			$is_reconciled         = false;
			$reconciliation_status = 'discrepancy';
			$discrepancy           = null;
			$message               = ( '' !== $message ? $message . ' ' : '' ) . __( 'Product stock is not currently managed in WooCommerce.', 'despace-core' );
		} else {
			$expected_stock = $initial_stock + $total_delta;
			$discrepancy    = abs( $current_wc_stock - $expected_stock );
			$is_reconciled  = ( $discrepancy < 0.000001 );
			$reconciliation_status = $is_reconciled ? 'reconciled' : 'discrepancy';
			if ( ! $is_reconciled ) {
				$message = ( '' !== $message ? $message . ' ' : '' ) . sprintf(
					__( 'Discrepancy detected: WooCommerce stock (%f) does not match expected stock (%f).', 'despace-core' ),
					$current_wc_stock,
					$expected_stock
				);
			}
		}

		return array(
			'product_id'            => $product_id,
			'variation_id'          => $variation_id,
			'sku'                   => $sku,
			'product_name'          => $product_name,
			'current_wc_stock'      => $current_wc_stock,
			'initial_stock'         => $initial_stock,
			'total_events'          => $total_events,
			'total_logged_delta'    => $total_delta,
			'expected_stock'        => $expected_stock,
			'latest_logged_stock'   => $latest_logged_stock,
			'discrepancy'           => $discrepancy,
			'is_reconciled'         => $is_reconciled,
			'reconciliation_status' => $reconciliation_status,
			'stock_scope'           => $stock_scope,
			'stock_managed_by_id'   => $stock_managed_by_id,
			'message'               => $message,
		);
	}
}

