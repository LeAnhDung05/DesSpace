<?php
/**
 * WooCommerce Stock Movement Observer.
 *
 * @package DeSpace\Inventory\Events
 */

namespace DeSpace\Inventory\Events;

defined( 'ABSPATH' ) || exit;

/**
 * Class InventoryStockObserver
 *
 * Hooks into WooCommerce stock lifecycle to record real stock changes
 * and dispatch them to InventoryEventRecorder.
 */
class InventoryStockObserver {

	/**
	 * Stored stock before change, indexed by product/variation ID.
	 *
	 * @var array<int, float|null>
	 */
	private static array $stock_before_cache = array();

	/**
	 * Register all WooCommerce hooks.
	 */
	public static function init(): void {
		// 1. Order operation contexts (to enrich event context without creating duplicate logs).
		add_action( 'woocommerce_reduce_order_stock', array( __CLASS__, 'on_before_reduce_order_stock' ), 5, 1 );
		add_action( 'woocommerce_reduce_order_stock', array( __CLASS__, 'on_after_order_stock_action' ), 999, 1 );

		add_action( 'woocommerce_restore_order_stock', array( __CLASS__, 'on_before_restore_order_stock' ), 5, 1 );
		add_action( 'woocommerce_restore_order_stock', array( __CLASS__, 'on_after_order_stock_action' ), 999, 1 );

		add_action( 'woocommerce_restock_refunded_item', array( __CLASS__, 'on_restock_refunded_item' ), 5, 5 );

		// 2. Core stock observation hooks for Simple Products and Variations.
		add_action( 'woocommerce_product_before_set_stock', array( __CLASS__, 'on_before_set_stock' ), 10, 1 );
		add_action( 'woocommerce_variation_before_set_stock', array( __CLASS__, 'on_before_set_stock' ), 10, 1 );

		add_action( 'woocommerce_product_set_stock', array( __CLASS__, 'on_set_stock' ), 10, 1 );
		add_action( 'woocommerce_variation_set_stock', array( __CLASS__, 'on_set_stock' ), 10, 1 );
	}

	/**
	 * Capture stock before update.
	 *
	 * @param \WC_Product $product
	 */
	public static function on_before_set_stock( $product ): void {
		if ( ! $product instanceof \WC_Product ) {
			return;
		}

		$product_id = $product->get_id();
		// In WooCommerce CRUD, $product->get_stock_quantity() already reflects newly assigned changes.
		// The genuine old stock before DB update is stored in $product->get_data()['stock_quantity']
		// or in the database post meta '_stock'.
		$old_stock = null;
		$data      = $product->get_data();

		if ( isset( $data['stock_quantity'] ) && null !== $data['stock_quantity'] ) {
			$old_stock = (float) $data['stock_quantity'];
		} else {
			$meta_stock = get_post_meta( $product_id, '_stock', true );
			if ( '' !== $meta_stock && false !== $meta_stock ) {
				$old_stock = (float) $meta_stock;
			}
		}

		self::$stock_before_cache[ $product_id ] = $old_stock;
	}

	/**
	 * Capture stock after update and record event.
	 *
	 * @param \WC_Product $product
	 */
	public static function on_set_stock( $product ): void {
		if ( ! $product instanceof \WC_Product ) {
			return;
		}

		$item_id     = $product->get_id();
		$stock_after = $product->get_stock_quantity();

		$stock_after_float = null !== $stock_after ? (float) $stock_after : null;
		$stock_before      = self::$stock_before_cache[ $item_id ] ?? null;

		// Clean cache immediately.
		unset( self::$stock_before_cache[ $item_id ] );

		// If before and after are identical or both unknown, no delta occurred.
		if ( null !== $stock_before && null !== $stock_after_float && abs( $stock_after_float - $stock_before ) < 0.000001 ) {
			return;
		}

		// Calculate delta.
		$delta = 0.0;
		if ( null !== $stock_before && null !== $stock_after_float ) {
			$delta = $stock_after_float - $stock_before;
		} elseif ( null === $stock_before && null !== $stock_after_float ) {
			$delta = $stock_after_float;
		}

		if ( abs( $delta ) < 0.000001 ) {
			return;
		}

		// Determine product vs variation entity.
		$product_id   = $item_id;
		$variation_id = null;

		if ( $product->is_type( 'variation' ) ) {
			$variation_id = $item_id;
			$product_id   = $product->get_parent_id();
		}

		// Contextual resolution.
		$context      = InventoryOrderContext::get();
		$order_id     = $context['order_id'] ?? null;
		$refund_id    = $context['refund_id'] ?? null;
		$event_source = $context['event_source'] ?? null;
		$event_type   = $context['event_type'] ?? null;

		if ( ! $event_source ) {
			if ( ! empty( $order_id ) ) {
				$event_source = InventoryEvent::SOURCE_ORDER;
			} elseif ( ! empty( $refund_id ) ) {
				$event_source = InventoryEvent::SOURCE_REFUND;
			} elseif ( is_admin() ) {
				$event_source = InventoryEvent::SOURCE_ADMIN;
			} elseif ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
				$event_source = InventoryEvent::SOURCE_REST_API;
			} else {
				$event_source = InventoryEvent::SOURCE_SYSTEM;
			}
		}

		if ( ! $event_type ) {
			if ( ! empty( $refund_id ) ) {
				$event_type = InventoryEvent::TYPE_REFUND_RESTOCK;
			} elseif ( ! empty( $order_id ) ) {
				$event_type = $delta < 0 ? InventoryEvent::TYPE_ORDER_REDUCTION : InventoryEvent::TYPE_STOCK_RESTORATION;
			} else {
				$event_type = InventoryEvent::TYPE_MANUAL_ADJUSTMENT;
			}
		}

		$actor_user_id = get_current_user_id() ? get_current_user_id() : null;

		// Build Event DTO.
		$event = new InventoryEvent( array(
			'event_type'      => $event_type,
			'event_source'    => $event_source,
			'product_id'      => $product_id,
			'variation_id'    => $variation_id,
			'order_id'        => $order_id,
			'order_item_id'   => $context['order_item_id'] ?? null,
			'refund_id'       => $refund_id,
			'actor_user_id'   => $actor_user_id,
			'quantity_before' => $stock_before,
			'quantity_delta'  => $delta,
			'stock_after'     => $stock_after_float,
			'context'         => array(
				'sku'          => $product->get_sku(),
				'product_name' => $product->get_name(),
			),
		) );

		// Set stable source operation ID if present for idempotency.
		$source_op_id = $context['source_operation_id'] ?? '';
		if ( empty( $source_op_id ) && empty( $order_id ) && empty( $refund_id ) ) {
			// For manual or unlinked adjustments, use unique request operation identity.
			$source_op_id = 'manual_' . $product_id . '_' . ( $stock_before ?? 0 ) . '_' . $stock_after_float . '_' . microtime( true );
		}

		if ( ! empty( $source_op_id ) ) {
			$event->build_event_key( $source_op_id );
		}

		InventoryEventRecorder::record( $event );
	}

	/**
	 * Set order reduction context.
	 *
	 * @param \WC_Order|int $order
	 */
	public static function on_before_reduce_order_stock( $order ): void {
		$order_id = is_numeric( $order ) ? absint( $order ) : ( method_exists( $order, 'get_id' ) ? $order->get_id() : 0 );
		if ( $order_id > 0 ) {
			InventoryOrderContext::set( array(
				'order_id'            => $order_id,
				'event_type'          => InventoryEvent::TYPE_ORDER_REDUCTION,
				'event_source'        => InventoryEvent::SOURCE_ORDER,
				'source_operation_id' => 'order_reduce_' . $order_id,
			) );
		}
	}

	/**
	 * Set order stock restoration context.
	 *
	 * @param \WC_Order|int $order
	 */
	public static function on_before_restore_order_stock( $order ): void {
		$order_id = is_numeric( $order ) ? absint( $order ) : ( method_exists( $order, 'get_id' ) ? $order->get_id() : 0 );
		if ( $order_id > 0 ) {
			InventoryOrderContext::set( array(
				'order_id'            => $order_id,
				'event_type'          => InventoryEvent::TYPE_STOCK_RESTORATION,
				'event_source'        => InventoryEvent::SOURCE_ORDER,
				'source_operation_id' => 'order_restore_' . $order_id,
			) );
		}
	}

	/**
	 * Set refund restock context.
	 *
	 * @param int       $product_id
	 * @param int       $old_stock
	 * @param int       $new_stock
	 * @param \WC_Order $order
	 * @param bool      $restock
	 */
	public static function on_restock_refunded_item( $product_id, $old_stock, $new_stock, $order, $restock ): void {
		if ( ! $restock ) {
			return;
		}

		$order_id = ( $order && method_exists( $order, 'get_id' ) ) ? $order->get_id() : null;

		InventoryOrderContext::set( array(
			'order_id'            => $order_id,
			'event_type'          => InventoryEvent::TYPE_REFUND_RESTOCK,
			'event_source'        => InventoryEvent::SOURCE_REFUND,
			'source_operation_id' => 'refund_restock_' . ( $order_id ?? 0 ) . '_' . $product_id,
		) );
	}

	/**
	 * Clear context after order stock action completes.
	 *
	 * @param mixed $order
	 */
	public static function on_after_order_stock_action( $order ): void {
		InventoryOrderContext::clear();
	}
}

