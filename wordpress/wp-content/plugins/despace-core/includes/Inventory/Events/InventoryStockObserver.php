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
 * and dispatch them to InventoryEventRecorder with complete business context.
 * Utilizes a FIFO queue for reliable per-line-item attribution even when duplicate
 * products exist in the same order.
 */
class InventoryStockObserver {

	/**
	 * Stored stock before change, indexed by product/variation ID.
	 *
	 * @var array<int, float|null>
	 */
	private static array $stock_before_cache = array();

	/**
	 * Active refund object reference during refund request lifecycle.
	 *
	 * @var \WC_Order_Refund|null
	 */
	private static ?\WC_Order_Refund $active_refund_object = null;

	/**
	 * Register all WooCommerce hooks.
	 */
	public static function init(): void {
		// 1. Order Stock Reduction lifecycle hooks.
		add_filter( 'woocommerce_can_reduce_order_stock', array( __CLASS__, 'on_can_reduce_order_stock' ), 10, 2 );
		add_filter( 'woocommerce_can_reduce_order_stock', array( __CLASS__, 'on_check_can_reduce_order_stock_final' ), 999999, 2 );
		add_filter( 'woocommerce_order_item_quantity', array( __CLASS__, 'on_filter_order_item_quantity' ), 10, 3 );
		add_action( 'woocommerce_reduce_order_item_stock', array( __CLASS__, 'on_after_reduce_order_item_stock' ), 999, 3 );
		add_action( 'woocommerce_reduce_order_stock', array( __CLASS__, 'on_after_order_stock_action' ), 999, 1 );

		// 2. Order Stock Restoration lifecycle hooks (cancelled, pending, failed, or programmatic increase).
		add_filter( 'woocommerce_can_restore_order_stock', array( __CLASS__, 'on_can_restore_order_stock' ), 10, 2 );
		add_filter( 'woocommerce_can_restore_order_stock', array( __CLASS__, 'on_check_can_restore_order_stock_final' ), 999999, 2 );
		add_action( 'woocommerce_restore_order_item_stock', array( __CLASS__, 'on_restore_order_item_stock' ), 999, 4 );
		add_action( 'woocommerce_restore_order_stock', array( __CLASS__, 'on_after_order_stock_action' ), 999, 1 );

		// 3. Refund Restock lifecycle hooks.
		add_action( 'woocommerce_create_refund', array( __CLASS__, 'on_woocommerce_create_refund' ), 10, 2 );
		add_filter( 'woocommerce_can_restock_refunded_items', array( __CLASS__, 'on_can_restock_refunded_items' ), 10, 3 );
		add_filter( 'woocommerce_can_restock_refunded_items', array( __CLASS__, 'on_check_can_restock_refunded_items_final' ), 999999, 3 );
		add_action( 'woocommerce_restock_refunded_item', array( __CLASS__, 'on_restock_refunded_item' ), 10, 5 );
		add_action( 'woocommerce_order_partially_refunded', array( __CLASS__, 'on_after_refund_action' ), 999, 2 );
		add_action( 'woocommerce_order_fully_refunded', array( __CLASS__, 'on_after_refund_action' ), 999, 2 );

		// 4. Core stock observation hooks for Simple Products and Variations.
		add_action( 'woocommerce_product_before_set_stock', array( __CLASS__, 'on_before_set_stock' ), 10, 1 );
		add_action( 'woocommerce_variation_before_set_stock', array( __CLASS__, 'on_before_set_stock' ), 10, 1 );

		add_action( 'woocommerce_product_set_stock', array( __CLASS__, 'on_set_stock' ), 10, 1 );
		add_action( 'woocommerce_variation_set_stock', array( __CLASS__, 'on_set_stock' ), 10, 1 );
	}

	/**
	 * Reset all cached state and context.
	 */
	public static function clear_all_context(): void {
		self::$active_refund_object = null;
		self::$stock_before_cache   = array();
		InventoryOrderContext::clear();
	}

	/**
	 * Final check on stock reduction permission: clear context if operation was denied.
	 *
	 * @param bool  $can   Whether stock reduction is permitted.
	 * @param mixed $order Order instance.
	 * @return bool
	 */
	public static function on_check_can_reduce_order_stock_final( $can, $order ) {
		if ( ! $can ) {
			self::clear_all_context();
		}
		return $can;
	}

	/**
	 * Final check on stock restoration permission: clear context if operation was denied.
	 *
	 * @param bool  $can   Whether stock restoration is permitted.
	 * @param mixed $order Order instance.
	 * @return bool
	 */
	public static function on_check_can_restore_order_stock_final( $can, $order ) {
		if ( ! $can ) {
			self::clear_all_context();
		}
		return $can;
	}

	/**
	 * Final check on refund restock permission: clear context if operation was denied.
	 *
	 * @param bool  $can                 Whether refund restock is permitted.
	 * @param mixed $order               Order instance.
	 * @param mixed $refunded_line_items Refunded line items array.
	 * @return bool
	 */
	public static function on_check_can_restock_refunded_items_final( $can, $order, $refunded_line_items ) {
		if ( ! $can ) {
			self::clear_all_context();
		}
		return $can;
	}

	/**
	 * Helper to build product_id/variation_id to order_item_id map for an order (legacy fallback).
	 *
	 * @param mixed $order WC_Order object or null.
	 * @return array<int, int>
	 */
	public static function build_order_items_map( $order ): array {
		$map = array();
		if ( ! $order instanceof \WC_Order ) {
			return $map;
		}

		foreach ( $order->get_items() as $item_id => $item ) {
			if ( ! is_object( $item ) || ! method_exists( $item, 'get_product_id' ) ) {
				continue;
			}
			$pid = absint( $item->get_product_id() );
			$vid = method_exists( $item, 'get_variation_id' ) ? absint( $item->get_variation_id() ) : 0;
			$iid = absint( $item_id );

			if ( $vid > 0 ) {
				$map[ $vid ] = $iid;
			}
			if ( $pid > 0 ) {
				$map[ $pid ] = $iid;
			}
		}

		return $map;
	}

	/**
	 * Pre-reduction hook establishing order reduction context.
	 *
	 * @param bool      $can   Can reduce stock.
	 * @param \WC_Order $order Order instance.
	 * @return bool
	 */
	public static function on_can_reduce_order_stock( $can, $order ) {
		if ( ! $can || ! ( $order instanceof \WC_Order ) ) {
			self::clear_all_context();
			return $can;
		}

		self::on_before_reduce_order_stock( $order );
		return $can;
	}

	/**
	 * Pre-item reduction filter establishing item-level context immediately prior to wc_update_product_stock().
	 *
	 * @param int|float              $qty   Quantity to reduce.
	 * @param \WC_Order              $order Order instance.
	 * @param \WC_Order_Item_Product $item  Line item instance.
	 * @return int|float
	 */
	public static function on_filter_order_item_quantity( $qty, $order, $item ) {
		if ( $order instanceof \WC_Order && $item instanceof \WC_Order_Item_Product ) {
			$order_id      = absint( $order->get_id() );
			$order_item_id = absint( $item->get_id() );

			InventoryOrderContext::set( array(
				'order_id'            => $order_id,
				'order_item_id'       => $order_item_id,
				'event_type'          => InventoryEvent::TYPE_ORDER_REDUCTION,
				'event_source'        => InventoryEvent::SOURCE_ORDER,
				'source_operation_id' => 'order_reduce_' . $order_id . '_' . $order_item_id,
			) );
		}

		return $qty;
	}

	/**
	 * Reset item-level context after line item stock is reduced.
	 *
	 * @param \WC_Order_Item_Product $item
	 * @param array                  $change
	 * @param \WC_Order              $order
	 */
	public static function on_after_reduce_order_item_stock( $item, $change, $order ): void {
		InventoryOrderContext::set( array(
			'order_item_id' => null,
		) );
	}

	/**
	 * Pre-restoration hook establishing order stock restoration context.
	 *
	 * @param bool      $can   Can restore stock.
	 * @param \WC_Order $order Order instance.
	 * @return bool
	 */
	public static function on_can_restore_order_stock( $can, $order ) {
		if ( ! $can || ! ( $order instanceof \WC_Order ) ) {
			self::clear_all_context();
			return $can;
		}

		self::on_before_restore_order_stock( $order );
		return $can;
	}

	/**
	 * Reset item-level context after line item stock is restored.
	 *
	 * @param \WC_Order_Item_Product $item
	 * @param int                    $new_stock
	 * @param int                    $old_stock
	 * @param \WC_Order              $order
	 */
	public static function on_restore_order_item_stock( $item, $new_stock, $old_stock, $order ): void {
		InventoryOrderContext::set( array(
			'order_item_id' => null,
		) );
	}

	/**
	 * Capture active refund object when a refund is initiated in wc_create_refund().
	 *
	 * @param \WC_Order_Refund $refund Refund object before save.
	 * @param array            $args   Refund arguments.
	 */
	public static function on_woocommerce_create_refund( $refund, $args ): void {
		if ( $refund instanceof \WC_Order_Refund ) {
			self::$active_refund_object = $refund;
		}
	}

	/**
	 * Pre-refund restock hook establishing refund restock context and line-item queue.
	 *
	 * @param bool      $can                 Can restock refunded items.
	 * @param \WC_Order $order               Parent order instance.
	 * @param array     $refunded_line_items Array of refunded line items.
	 * @return bool
	 */
	public static function on_can_restock_refunded_items( $can, $order, $refunded_line_items ) {
		if ( ! $can || ! ( $order instanceof \WC_Order ) || ! is_array( $refunded_line_items ) ) {
			self::clear_all_context();
			return $can;
		}

		// Reset active order context without clearing self::$active_refund_object
		InventoryOrderContext::clear();
		$order_id  = absint( $order->get_id() );
		$refund_id = null;

		if ( self::$active_refund_object instanceof \WC_Order_Refund && self::$active_refund_object->get_id() > 0 ) {
			$refund_id = absint( self::$active_refund_object->get_id() );
		} elseif ( $order instanceof \WC_Order ) {
			$refunds = $order->get_refunds();
			if ( ! empty( $refunds ) && is_array( $refunds ) ) {
				$latest_refund = end( $refunds );
				if ( $latest_refund instanceof \WC_Order_Refund ) {
					$refund_id = absint( $latest_refund->get_id() );
				}
			}
		}

		InventoryOrderContext::set( array(
			'order_id'            => $order_id,
			'refund_id'           => $refund_id,
			'event_type'          => InventoryEvent::TYPE_REFUND_RESTOCK,
			'event_source'        => InventoryEvent::SOURCE_REFUND,
			'source_operation_id' => 'refund_restock_' . $order_id . '_' . ( $refund_id ?? 0 ),
		) );

		// Enqueue eligible refunded line items in WooCommerce iteration order (FIFO).
		foreach ( $order->get_items() as $item_id => $item ) {
			if ( ! isset( $refunded_line_items[ $item_id ], $refunded_line_items[ $item_id ]['qty'] ) ) {
				continue;
			}
			$product = $item->get_product();
			if ( ! $product || ! $product->managing_stock() ) {
				continue;
			}
			$item_stock_reduced = (float) $item->get_meta( '_reduced_stock', true );
			$qty_to_refund      = (float) $refunded_line_items[ $item_id ]['qty'];

			if ( $item_stock_reduced <= 0 || $qty_to_refund <= 0 ) {
				continue;
			}

			$stock_entity_id = absint( $product->get_stock_managed_by_id() );
			InventoryOrderContext::enqueue_item( $stock_entity_id, array(
				'order_item_id' => absint( $item_id ),
				'product_id'    => absint( $item->get_product_id() ),
				'variation_id'  => method_exists( $item, 'get_variation_id' ) ? ( absint( $item->get_variation_id() ) ?: null ) : null,
				'quantity'      => $qty_to_refund,
			) );
		}

		return $can;
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
		$old_stock  = null;
		$data       = $product->get_data();

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
		$context       = InventoryOrderContext::get();
		$order_id      = $context['order_id'] ?? null;
		$refund_id     = $context['refund_id'] ?? null;
		$event_source  = $context['event_source'] ?? null;
		$event_type    = $context['event_type'] ?? null;
		$order_item_id = $context['order_item_id'] ?? null;

		// If inside order or refund context, resolve exact order_item_id via FIFO queue.
		$stock_entity_id = absint( $product->get_stock_managed_by_id() );
		$queued_item     = null;

		if ( ! empty( $order_id ) || ! empty( $refund_id ) ) {
			if ( empty( $order_item_id ) ) {
				$queued_item = InventoryOrderContext::dequeue_item( $stock_entity_id );
				if ( null === $queued_item && $stock_entity_id !== $item_id ) {
					$queued_item = InventoryOrderContext::dequeue_item( $item_id );
				}
				if ( null !== $queued_item ) {
					$order_item_id = $queued_item['order_item_id'];
				}
			} else {
				// order_item_id was directly set (e.g. by on_filter_order_item_quantity).
				// Synchronize queue if matching item is at head.
				$peeked = InventoryOrderContext::peek_item( $stock_entity_id );
				if ( null !== $peeked && (int) $peeked['order_item_id'] === (int) $order_item_id ) {
					$queued_item = InventoryOrderContext::dequeue_item( $stock_entity_id );
				}
			}
		}

		// Fallback to legacy map if queue was not populated.
		if ( empty( $order_item_id ) && ! empty( $context['items_map'] ) && is_array( $context['items_map'] ) ) {
			if ( ! empty( $variation_id ) && isset( $context['items_map'][ $variation_id ] ) ) {
				$order_item_id = $context['items_map'][ $variation_id ];
			} elseif ( isset( $context['items_map'][ $product_id ] ) ) {
				$order_item_id = $context['items_map'][ $product_id ];
			} elseif ( isset( $context['items_map'][ $item_id ] ) ) {
				$order_item_id = $context['items_map'][ $item_id ];
			}
		}

		if ( ! $event_source ) {
			if ( ! empty( $refund_id ) ) {
				$event_source = InventoryEvent::SOURCE_REFUND;
			} elseif ( ! empty( $order_id ) ) {
				$event_source = InventoryEvent::SOURCE_ORDER;
			} elseif ( is_admin() && ! wp_doing_cron() && ! ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
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

		// Stable source operation ID for deterministic event_key uniqueness and idempotency.
		$source_op_id = '';
		if ( ! empty( $order_id ) ) {
			if ( InventoryEvent::TYPE_ORDER_REDUCTION === $event_type ) {
				$source_op_id = 'order_reduce_' . $order_id . '_' . ( $order_item_id ?? $item_id );
			} elseif ( InventoryEvent::TYPE_STOCK_RESTORATION === $event_type ) {
				$source_op_id = 'order_restore_' . $order_id . '_' . ( $order_item_id ?? $item_id );
			} elseif ( InventoryEvent::TYPE_REFUND_RESTOCK === $event_type ) {
				$source_op_id = 'refund_restock_' . $order_id . '_' . ( $refund_id ?? 0 ) . '_' . ( $order_item_id ?? $item_id );
			}
		} elseif ( ! empty( $refund_id ) ) {
			$source_op_id = 'refund_restock_' . ( $order_id ?? 0 ) . '_' . $refund_id . '_' . ( $order_item_id ?? $item_id );
		} else {
			// For manual adjustments or standalone changes, create unique request identity.
			$source_op_id = 'manual_' . $product_id . '_' . ( $stock_before ?? 0 ) . '_' . $stock_after_float . '_' . microtime( true );
		}

		$actor_user_id = get_current_user_id() ? get_current_user_id() : null;

		$context_data = array(
			'sku'          => $product->get_sku(),
			'product_name' => $product->get_name(),
		);
		if ( null !== $queued_item && ! empty( $queued_item['variation_id'] ) ) {
			$context_data['line_variation_id'] = $queued_item['variation_id'];
		}

		// Build Event DTO.
		$event = new InventoryEvent( array(
			'event_type'      => $event_type,
			'event_source'    => $event_source,
			'product_id'      => $product_id,
			'variation_id'    => $variation_id,
			'order_id'        => $order_id,
			'order_item_id'   => $order_item_id,
			'refund_id'       => $refund_id,
			'actor_user_id'   => $actor_user_id,
			'quantity_before' => $stock_before,
			'quantity_delta'  => $delta,
			'stock_after'     => $stock_after_float,
			'context'         => $context_data,
		) );

		if ( ! empty( $source_op_id ) ) {
			$event->build_event_key( $source_op_id );
		}

		InventoryEventRecorder::record( $event );
	}

	/**
	 * Set order reduction context (programmatic / filter helper).
	 *
	 * @param \WC_Order|int $order
	 */
	public static function on_before_reduce_order_stock( $order ): void {
		$order_id = is_numeric( $order ) ? absint( $order ) : ( method_exists( $order, 'get_id' ) ? $order->get_id() : 0 );
		if ( $order_id > 0 ) {
			self::clear_all_context();
			$order_obj = is_numeric( $order ) ? wc_get_order( $order_id ) : $order;
			$items_map = self::build_order_items_map( $order_obj );

			InventoryOrderContext::set( array(
				'order_id'            => $order_id,
				'event_type'          => InventoryEvent::TYPE_ORDER_REDUCTION,
				'event_source'        => InventoryEvent::SOURCE_ORDER,
				'items_map'           => $items_map,
				'source_operation_id' => 'order_reduce_' . $order_id,
			) );

			if ( $order_obj instanceof \WC_Order ) {
				foreach ( $order_obj->get_items() as $item_id => $item ) {
					if ( ! $item->is_type( 'line_item' ) ) {
						continue;
					}
					$product = $item->get_product();
					$item_stock_reduced = (float) $item->get_meta( '_reduced_stock', true );
					if ( $item_stock_reduced > 0 || ! $product || ! $product->managing_stock() ) {
						continue;
					}
					$stock_entity_id = absint( $product->get_stock_managed_by_id() );
					InventoryOrderContext::enqueue_item( $stock_entity_id, array(
						'order_item_id' => absint( $item_id ),
						'product_id'    => absint( $item->get_product_id() ),
						'variation_id'  => method_exists( $item, 'get_variation_id' ) ? ( absint( $item->get_variation_id() ) ?: null ) : null,
						'quantity'      => (float) $item->get_quantity(),
					) );
				}
			}
		}
	}

	/**
	 * Set order stock restoration context (programmatic / filter helper).
	 *
	 * @param \WC_Order|int $order
	 */
	public static function on_before_restore_order_stock( $order ): void {
		$order_id = is_numeric( $order ) ? absint( $order ) : ( method_exists( $order, 'get_id' ) ? $order->get_id() : 0 );
		if ( $order_id > 0 ) {
			self::clear_all_context();
			$order_obj = is_numeric( $order ) ? wc_get_order( $order_id ) : $order;
			$items_map = self::build_order_items_map( $order_obj );

			InventoryOrderContext::set( array(
				'order_id'            => $order_id,
				'event_type'          => InventoryEvent::TYPE_STOCK_RESTORATION,
				'event_source'        => InventoryEvent::SOURCE_ORDER,
				'items_map'           => $items_map,
				'source_operation_id' => 'order_restore_' . $order_id,
			) );

			if ( $order_obj instanceof \WC_Order ) {
				foreach ( $order_obj->get_items() as $item_id => $item ) {
					if ( ! $item->is_type( 'line_item' ) ) {
						continue;
					}
					$product = $item->get_product();
					$item_stock_reduced = (float) $item->get_meta( '_reduced_stock', true );
					if ( $item_stock_reduced <= 0 || ! $product || ! $product->managing_stock() ) {
						continue;
					}
					$stock_entity_id = absint( $product->get_stock_managed_by_id() );
					InventoryOrderContext::enqueue_item( $stock_entity_id, array(
						'order_item_id' => absint( $item_id ),
						'product_id'    => absint( $item->get_product_id() ),
						'variation_id'  => method_exists( $item, 'get_variation_id' ) ? ( absint( $item->get_variation_id() ) ?: null ) : null,
						'quantity'      => $item_stock_reduced,
					) );
				}
			}
		}
	}

	/**
	 * Handler for woocommerce_restock_refunded_item action.
	 *
	 * @param int              $product_id Product ID.
	 * @param int|float        $old_stock  Stock level prior to restock.
	 * @param int|float        $new_stock  Stock level after restock.
	 * @param \WC_Order        $order      Parent order.
	 * @param \WC_Product|null $product    Product instance restocked.
	 */
	public static function on_restock_refunded_item( $product_id, $old_stock, $new_stock, $order, $product ): void {
		InventoryOrderContext::set( array(
			'order_item_id' => null,
		) );
	}

	/**
	 * Clear context after order refund actions complete.
	 *
	 * @param int $order_id
	 * @param int $refund_id
	 */
	public static function on_after_refund_action( $order_id, $refund_id ): void {
		self::clear_all_context();
	}

	/**
	 * Clear context after order stock action completes.
	 *
	 * @param mixed $order
	 */
	public static function on_after_order_stock_action( $order ): void {
		self::clear_all_context();
	}
}
