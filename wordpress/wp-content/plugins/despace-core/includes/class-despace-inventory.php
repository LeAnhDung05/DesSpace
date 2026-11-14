<?php
/**
 * Inventory Auditing and Logging System for DeSpace.
 * Strictly adheres to DeSpace SADD v1.2 Sections 4.2.5, 4.2.6, and 6.3.
 *
 * @package DeSpace_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DeSpace_Inventory {

	public function __construct() {
		// WooCommerce Stock Reduction on Order
		add_action( 'woocommerce_reduce_order_stock', array( $this, 'on_reduce_order_stock' ), 10, 1 );

		// WooCommerce Stock Restoration on Order Cancellation/Refund
		add_action( 'woocommerce_restore_order_stock', array( $this, 'on_restore_order_stock' ), 10, 1 );

		// WooCommerce Direct Stock Adjustment (Manual / Hook)
		add_action( 'woocommerce_product_set_stock', array( $this, 'on_product_set_stock' ), 10, 1 );
		add_action( 'woocommerce_variation_set_stock', array( $this, 'on_variation_set_stock' ), 10, 1 );
	}

	/**
	 * Log a stock change event with idempotency protection.
	 */
	public static function log_event( $args ) {
		global $wpdb;

		$defaults = array(
			'product_id'     => 0,
			'variation_id'   => null,
			'order_id'       => null,
			'actor_user_id'  => get_current_user_id() ?: null,
			'event_type'     => 'adjust',
			'event_key'      => '',
			'quantity_delta' => 0,
			'stock_after'    => null,
			'note'           => '',
			'created_at'     => current_time( 'mysql' ),
		);

		$data = wp_parse_args( $args, $defaults );

		if ( empty( $data['product_id'] ) || empty( $data['event_key'] ) ) {
			return false;
		}

		$table_name = $wpdb->prefix . 'despace_inventory_logs';

		// Use INSERT IGNORE to prevent duplicate event_key
		$sql = $wpdb->prepare(
			"INSERT IGNORE INTO {$table_name} 
			(product_id, variation_id, order_id, actor_user_id, event_type, event_key, quantity_delta, stock_after, note, created_at)
			VALUES (%d, %s, %s, %s, %s, %s, %d, %s, %s, %s)",
			$data['product_id'],
			$data['variation_id'] ? $data['variation_id'] : null,
			$data['order_id'] ? $data['order_id'] : null,
			$data['actor_user_id'] ? $data['actor_user_id'] : null,
			$data['event_type'],
			$data['event_key'],
			$data['quantity_delta'],
			null !== $data['stock_after'] ? $data['stock_after'] : null,
			$data['note'],
			$data['created_at']
		);

		return $wpdb->query( $sql );
	}

	/**
	 * Listen to WooCommerce Order Stock Reduction.
	 */
	public function on_reduce_order_stock( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$order_id = $order->get_id();

		foreach ( $order->get_items() as $item_id => $item ) {
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}

			$product = $item->get_product();
			if ( ! $product || ! $product->managing_stock() ) {
				continue;
			}

			$qty = $item->get_quantity();
			$product_id   = $product->get_id();
			$variation_id = $product->is_type( 'variation' ) ? $product_id : null;
			$parent_id    = $product->is_type( 'variation' ) ? $product->get_parent_id() : $product_id;

			$event_key = sprintf( 'order_%d_item_%d_sale', $order_id, $item_id );

			self::log_event( array(
				'product_id'     => $parent_id,
				'variation_id'   => $variation_id,
				'order_id'       => $order_id,
				'event_type'     => 'sale',
				'event_key'      => $event_key,
				'quantity_delta' => -1 * abs( $qty ),
				'stock_after'    => $product->get_stock_quantity(),
				'note'           => sprintf( __( 'Khách đặt đơn hàng #%d (x%d)', 'despace-core' ), $order_id, $qty ),
			) );
		}
	}

	/**
	 * Listen to WooCommerce Order Stock Restoration.
	 */
	public function on_restore_order_stock( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$order_id = $order->get_id();

		foreach ( $order->get_items() as $item_id => $item ) {
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}

			$product = $item->get_product();
			if ( ! $product || ! $product->managing_stock() ) {
				continue;
			}

			$qty = $item->get_quantity();
			$product_id   = $product->get_id();
			$variation_id = $product->is_type( 'variation' ) ? $product_id : null;
			$parent_id    = $product->is_type( 'variation' ) ? $product->get_parent_id() : $product_id;

			$event_key = sprintf( 'order_%d_item_%d_restore', $order_id, $item_id );

			self::log_event( array(
				'product_id'     => $parent_id,
				'variation_id'   => $variation_id,
				'order_id'       => $order_id,
				'event_type'     => 'restore',
				'event_key'      => $event_key,
				'quantity_delta' => abs( $qty ),
				'stock_after'    => $product->get_stock_quantity(),
				'note'           => sprintf( __( 'Hoàn trả kho từ đơn hủy/trả hàng #%d (x%d)', 'despace-core' ), $order_id, $qty ),
			) );
		}
	}

	/**
	 * Direct Product Stock Set.
	 */
	public function on_product_set_stock( $product ) {
		if ( ! $product instanceof WC_Product || doing_action( 'woocommerce_reduce_order_stock' ) || doing_action( 'woocommerce_restore_order_stock' ) ) {
			return;
		}

		$event_key = sprintf( 'prod_%d_set_%s', $product->get_id(), microtime( true ) );

		self::log_event( array(
			'product_id'     => $product->get_id(),
			'event_type'     => 'adjust',
			'event_key'      => $event_key,
			'quantity_delta' => 0,
			'stock_after'    => $product->get_stock_quantity(),
			'note'           => __( 'Điều chỉnh số lượng tồn kho sản phẩm trực tiếp', 'despace-core' ),
		) );
	}

	/**
	 * Direct Variation Stock Set.
	 */
	public function on_variation_set_stock( $variation ) {
		if ( ! $variation instanceof WC_Product_Variation || doing_action( 'woocommerce_reduce_order_stock' ) || doing_action( 'woocommerce_restore_order_stock' ) ) {
			return;
		}

		$event_key = sprintf( 'var_%d_set_%s', $variation->get_id(), microtime( true ) );

		self::log_event( array(
			'product_id'     => $variation->get_parent_id(),
			'variation_id'   => $variation->get_id(),
			'event_type'     => 'adjust',
			'event_key'      => $event_key,
			'quantity_delta' => 0,
			'stock_after'    => $variation->get_stock_quantity(),
			'note'           => __( 'Điều chỉnh số lượng tồn kho biến thể trực tiếp', 'despace-core' ),
		) );
	}
}
