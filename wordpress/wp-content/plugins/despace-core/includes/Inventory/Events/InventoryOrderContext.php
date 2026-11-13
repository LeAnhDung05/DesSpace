<?php
/**
 * Context tracker for order and refund stock operations.
 *
 * @package DeSpace\Inventory\Events
 */

namespace DeSpace\Inventory\Events;

defined( 'ABSPATH' ) || exit;

/**
 * Class InventoryOrderContext
 *
 * Tracks the current active order, refund, and operation context during a request
 * so that lower-level stock observers can attach business context without duplicating logs.
 * Supports FIFO queueing of line items for accurate per-line-item attribution.
 */
class InventoryOrderContext {

	/**
	 * Active context state.
	 *
	 * @var array
	 */
	private static array $current_context = array(
		'order_id'            => null,
		'order_item_id'       => null,
		'refund_id'           => null,
		'event_type'          => null,
		'event_source'        => null,
		'source_operation_id' => null,
		'expected_caller'     => null,
		'items_queue'         => array(),
	);

	/**
	 * Set active context.
	 *
	 * @param array $context
	 */
	public static function set( array $context ): void {
		self::$current_context = array_merge( self::$current_context, $context );
	}

	/**
	 * Get current context.
	 *
	 * @return array
	 */
	public static function get(): array {
		return self::$current_context;
	}

	/**
	 * Get a specific key from current context.
	 *
	 * @param string $key
	 * @param mixed $default
	 * @return mixed
	 */
	public static function get_key( string $key, $default = null ) {
		return self::$current_context[ $key ] ?? $default;
	}

	/**
	 * Enqueue an eligible line item context for a specific stock-managed product/variation.
	 *
	 * @param int   $stock_entity_id The product or variation ID that manages stock.
	 * @param array $item_context   Item context array (order_item_id, product_id, variation_id, quantity).
	 */
	public static function enqueue_item( int $stock_entity_id, array $item_context ): void {
		if ( ! isset( self::$current_context['items_queue'][ $stock_entity_id ] ) ) {
			self::$current_context['items_queue'][ $stock_entity_id ] = array();
		}
		self::$current_context['items_queue'][ $stock_entity_id ][] = $item_context;
	}

	/**
	 * Dequeue the next eligible line item context for a specific stock-managed product/variation (FIFO).
	 *
	 * @param int $stock_entity_id The product or variation ID that manages stock.
	 * @return array|null The item context, or null if queue is empty.
	 */
	public static function dequeue_item( int $stock_entity_id ): ?array {
		if ( empty( self::$current_context['items_queue'][ $stock_entity_id ] ) ) {
			return null;
		}
		return array_shift( self::$current_context['items_queue'][ $stock_entity_id ] );
	}

	/**
	 * Peek at the next eligible line item context without removing it.
	 *
	 * @param int $stock_entity_id The product or variation ID that manages stock.
	 * @return array|null The item context, or null if queue is empty.
	 */
	public static function peek_item( int $stock_entity_id ): ?array {
		if ( empty( self::$current_context['items_queue'][ $stock_entity_id ] ) ) {
			return null;
		}
		return self::$current_context['items_queue'][ $stock_entity_id ][0];
	}

	/**
	 * Check if any items are currently queued across any stock entities.
	 *
	 * @return bool
	 */
	public static function has_queued_items(): bool {
		if ( empty( self::$current_context['items_queue'] ) ) {
			return false;
		}
		foreach ( self::$current_context['items_queue'] as $q ) {
			if ( ! empty( $q ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Check if currently inside an order operation context.
	 *
	 * @return bool
	 */
	public static function has_order_context(): bool {
		return ! empty( self::$current_context['order_id'] );
	}

	/**
	 * Check if currently inside a refund operation context.
	 *
	 * @return bool
	 */
	public static function has_refund_context(): bool {
		return ! empty( self::$current_context['refund_id'] );
	}

	/**
	 * Reset active context completely.
	 */
	public static function clear(): void {
		self::$current_context = array(
			'order_id'            => null,
			'order_item_id'       => null,
			'refund_id'           => null,
			'event_type'          => null,
			'event_source'        => null,
			'source_operation_id' => null,
			'expected_caller'     => null,
			'items_queue'         => array(),
		);
	}
}
