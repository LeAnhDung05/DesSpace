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
		'items_map'           => array(),
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
	 * Reset active context.
	 */
	public static function clear(): void {
		self::$current_context = array(
			'order_id'            => null,
			'order_item_id'       => null,
			'refund_id'           => null,
			'event_type'          => null,
			'event_source'        => null,
			'source_operation_id' => null,
			'items_map'           => array(),
		);
	}
}

