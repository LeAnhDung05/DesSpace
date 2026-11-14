<?php
/**
 * Test case for DeSpace Inventory Module.
 *
 * @package DeSpace\Tests
 */

namespace DeSpace\Tests;

use DeSpace\Inventory\Events\InventoryEvent;

/**
 * Class InventoryTest
 */
class InventoryTest {

	/**
	 * Test InventoryEvent object creation and array conversion.
	 */
	public function test_inventory_event_creation(): bool {
		$event = new InventoryEvent( array(
			'product_id'     => 46,
			'order_id'       => 516,
			'event_type'     => 'order_reduction',
			'quantity_delta' => -1.0,
			'stock_after'    => 17.0,
			'actor_user_id'  => 1,
		) );

		$data = $event->to_db_array();

		assert( 46 === $data['product_id'], 'Product ID must match.' );
		assert( 516 === $data['order_id'], 'Order ID must match.' );
		assert( 'order_reduction' === $data['event_type'], 'Event type must be order_reduction.' );
		assert( -1.0 === (float) $data['quantity_delta'], 'Quantity delta must be -1.0.' );
		assert( 17.0 === (float) $data['stock_after'], 'Stock after must be 17.0.' );

		return true;
	}
}
