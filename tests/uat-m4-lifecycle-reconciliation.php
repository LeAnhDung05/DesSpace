<?php
/**
 * DeSpace M4 — Final Staging UAT & Stock Reconciliation Verification.
 *
 * Runs end-to-end against live MariaDB, HPOS, and WooCommerce Order Lifecycle.
 */

ini_set( 'memory_limit', '512M' );
$_SERVER['REQUEST_METHOD'] = 'POST';

require_once __DIR__ . '/../wordpress/wp-load.php';

use DeSpace\CheckoutOrders\Cart\CartHandler;
use DeSpace\CheckoutOrders\Payment\PaymentStatusGuard;
use DeSpace\Inventory\Database\InventoryLogInstaller;
use DeSpace\Inventory\Audit\InventoryReconciler;

if ( ! class_exists( 'WooCommerce' ) ) {
	fwrite( STDERR, "WooCommerce not active.\n" );
	exit( 1 );
}

echo "========================================================================\n";
echo "   DeSpace M4 — FINAL STAGING UAT & INVENTORY RECONCILIATION            \n";
echo "========================================================================\n";
echo "Active WC Version: " . WC_VERSION . "\n";
echo "HPOS Active: " . ( \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ? 'YES' : 'NO' ) . "\n\n";

global $wpdb;
$log_table = InventoryLogInstaller::get_table_name();

// 1. Create a fresh stock-managed product with initial stock = 20
$product = new \WC_Product_Simple();
$product->set_name( 'Final UAT Sofa ' . time() );
$product->set_regular_price( '5000000' );
$product->set_manage_stock( true );
$product->set_stock_quantity( 20 );
$product->set_status( 'publish' );
$product_id = $product->save();

// Delete any initial product creation log so reconciliation starts cleanly from order events
$wpdb->delete( $log_table, array( 'product_id' => $product_id ) );

echo "Step 1: Created product #{$product_id} with initial stock = 20.\n";
$stock_step1 = (float) wc_get_product( $product_id )->get_stock_quantity();
echo "  -> Verified initial stock in WooCommerce: {$stock_step1}\n\n";

// 2. Place BACS Order for 3 units
wp_set_current_user( 0 );
WC()->cart->empty_cart();
CartHandler::add_item( $product_id, 3 );

$checkout = WC()->checkout();
$posted_data = array(
	'billing_first_name'           => 'Lê',
	'billing_last_name'            => 'Thành Phố',
	'billing_email'                => 'uat.m4@despace.test',
	'billing_phone'                => '0912999888',
	'billing_country'              => 'VN',
	'billing_state'                => '15',
	'billing_city'                 => 'Phường Lào Cai',
	'billing_despace_commune_code' => '02647',
	'billing_address_1'            => '88 Fansipan',
	'payment_method'               => 'bacs',
);

$order_id = $checkout->create_order( $posted_data );
if ( is_wp_error( $order_id ) || ! ( $order_id > 0 ) ) {
	fwrite( STDERR, "Failed to create order.\n" );
	exit( 1 );
}

$gateways = WC()->payment_gateways->payment_gateways();
if ( isset( $gateways['bacs'] ) ) {
	$gateways['bacs']->process_payment( $order_id );
}

$order = wc_get_order( $order_id );
$items = $order->get_items();
$line_item = reset( $items );
$order_item_id = $line_item ? $line_item->get_id() : 0;

$stock_step2 = (float) wc_get_product( $product_id )->get_stock_quantity();
echo "Step 2: Placed BACS Order #{$order_id} (Item #{$order_item_id}) for 3 units.\n";
echo "  -> Order Status: " . $order->get_status() . " (Expected: on-hold)\n";
echo "  -> WooCommerce stock quantity after checkout reduction: {$stock_step2} (Expected: 17)\n\n";

// 3. Confirm BACS payment by Admin
$admin_id = 1;
$admins   = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
if ( ! empty( $admins ) ) {
	$admin_id = $admins[0]->ID;
}
wp_set_current_user( $admin_id );

$nonce = wp_create_nonce( 'despace_confirm_bacs_payment_' . $order_id );
$confirm_res = PaymentStatusGuard::confirm_bacs_payment_by_admin(
	$order_id,
	'TX_UAT_M4_' . time(),
	array(
		'confirmed_received' => true,
		'nonce'              => $nonce,
	)
);

$stock_step3 = (float) wc_get_product( $product_id )->get_stock_quantity();
echo "Step 3: Confirmed BACS Payment via PaymentStatusGuard.\n";
echo "  -> Payment Confirmation Result: " . ( is_wp_error( $confirm_res ) ? 'ERROR' : 'SUCCESS' ) . "\n";
echo "  -> WooCommerce stock quantity after BACS settlement: {$stock_step3} (Expected: 17 — NO double reduction)\n\n";

// 4. Verify Order Reduction Log in wp_despace_inventory_logs
$reduction_log = $wpdb->get_row( $wpdb->prepare(
	"SELECT * FROM {$log_table} WHERE product_id = %d AND event_type = %s",
	$product_id,
	'order_reduction'
), ARRAY_A );

echo "Step 4: Verifying Order Reduction Log Attribution:\n";
$reduction_ok = ! empty( $reduction_log )
	&& ( 'order_reduction' === $reduction_log['event_type'] )
	&& ( 'order' === $reduction_log['event_source'] )
	&& ( (int) $reduction_log['order_id'] === (int) $order_id )
	&& ( (int) $reduction_log['order_item_id'] === (int) $order_item_id )
	&& ( -3.0 === (float) $reduction_log['quantity_delta'] )
	&& ( 20.0 === (float) $reduction_log['quantity_before'] )
	&& ( 17.0 === (float) $reduction_log['stock_after'] );

echo "  -> event_type: " . ( $reduction_log['event_type'] ?? 'NONE' ) . " (Expected: order_reduction)\n";
echo "  -> event_source: " . ( $reduction_log['event_source'] ?? 'NONE' ) . " (Expected: order)\n";
echo "  -> order_id: " . ( $reduction_log['order_id'] ?? 'NULL' ) . " (Expected: {$order_id})\n";
echo "  -> order_item_id: " . ( $reduction_log['order_item_id'] ?? 'NULL' ) . " (Expected: {$order_item_id})\n";
echo "  -> delta: " . ( $reduction_log['quantity_delta'] ?? 'NULL' ) . " (Expected: -3.000000)\n";
echo "  -> Reduction Attribution Status: " . ( $reduction_ok ? 'PASS' : 'FAIL' ) . "\n\n";

// 5. Process Refund & Restock of 1 item
$refund = wc_create_refund( array(
	'amount'         => 5000000,
	'reason'         => 'Final Staging UAT Restock 1 unit',
	'order_id'       => $order_id,
	'line_items'     => array(
		$order_item_id => array(
			'qty'          => 1,
			'refund_total' => 5000000,
			'refund_tax'   => array(),
		),
	),
	'restock_items'  => true,
) );

$refund_id   = $refund instanceof \WC_Order_Refund ? $refund->get_id() : 0;
$stock_step5 = (float) wc_get_product( $product_id )->get_stock_quantity();

echo "Step 5: Processed Refund #{$refund_id} & Restock of 1 item:\n";
echo "  -> WooCommerce stock quantity after refund restock: {$stock_step5} (Expected: 18)\n\n";

// 6. Verify Refund Restock Log in wp_despace_inventory_logs
$refund_log = $wpdb->get_row( $wpdb->prepare(
	"SELECT * FROM {$log_table} WHERE product_id = %d AND event_type = %s",
	$product_id,
	'refund_restock'
), ARRAY_A );

echo "Step 6: Verifying Refund Restock Log Attribution:\n";
$refund_ok = ! empty( $refund_log )
	&& ( 'refund_restock' === $refund_log['event_type'] )
	&& ( 'refund' === $refund_log['event_source'] )
	&& ( (int) $refund_log['order_id'] === (int) $order_id )
	&& ( (int) $refund_log['refund_id'] === (int) $refund_id )
	&& ( (int) $refund_log['order_item_id'] === (int) $order_item_id )
	&& ( 1.0 === (float) $refund_log['quantity_delta'] )
	&& ( 17.0 === (float) $refund_log['quantity_before'] )
	&& ( 18.0 === (float) $refund_log['stock_after'] );

echo "  -> event_type: " . ( $refund_log['event_type'] ?? 'NONE' ) . " (Expected: refund_restock)\n";
echo "  -> event_source: " . ( $refund_log['event_source'] ?? 'NONE' ) . " (Expected: refund)\n";
echo "  -> order_id: " . ( $refund_log['order_id'] ?? 'NULL' ) . " (Expected: {$order_id})\n";
echo "  -> refund_id: " . ( $refund_log['refund_id'] ?? 'NULL' ) . " (Expected: {$refund_id})\n";
echo "  -> order_item_id: " . ( $refund_log['order_item_id'] ?? 'NULL' ) . " (Expected: {$order_item_id})\n";
echo "  -> delta: " . ( $refund_log['quantity_delta'] ?? 'NULL' ) . " (Expected: 1.000000)\n";
echo "  -> Refund Attribution Status: " . ( $refund_ok ? 'PASS' : 'FAIL' ) . "\n\n";

// 7. Event Count Check: Exactly 1 event per actual stock movement
$all_logs = $wpdb->get_results( $wpdb->prepare(
	"SELECT * FROM {$log_table} WHERE product_id = %d ORDER BY id ASC",
	$product_id
), ARRAY_A );

echo "Step 7: Verifying Event Count Integrity:\n";
echo "  -> Total logged events for product: " . count( $all_logs ) . " (Expected: exactly 2: 1 reduction + 1 restock)\n";
$count_ok = ( 2 === count( $all_logs ) );
echo "  -> Event Count Status: " . ( $count_ok ? 'PASS' : 'FAIL' ) . "\n\n";

// 8. InventoryReconciler Audit
echo "Step 8: Running InventoryReconciler on Product #{$product_id}:\n";
$reconciler_result = InventoryReconciler::reconcile( $product_id );
echo "  -> Current WooCommerce Stock: " . $reconciler_result['current_wc_stock'] . "\n";
echo "  -> Total Logged Delta: " . $reconciler_result['total_logged_delta'] . "\n";
echo "  -> Latest Logged Stock: " . $reconciler_result['latest_logged_stock'] . "\n";
echo "  -> Discrepancy: " . $reconciler_result['discrepancy'] . " (Expected: 0)\n";
$reconcile_ok = ( 0.0 === (float) $reconciler_result['discrepancy'] )
	&& ( (float) $reconciler_result['current_wc_stock'] === (float) $reconciler_result['latest_logged_stock'] );
echo "  -> Reconciliation Status: " . ( $reconcile_ok ? 'PASS' : 'FAIL' ) . "\n\n";

// Cleanup
$order->delete( true );
$product->delete( true );

// -------------------------------------------------------------------------
// Final Verdict
// -------------------------------------------------------------------------
echo "========================================================================\n";
if ( 20.0 === $stock_step1 && 17.0 === $stock_step2 && 17.0 === $stock_step3 && 18.0 === $stock_step5 && $reduction_ok && $refund_ok && $count_ok && $reconcile_ok ) {
	echo ">>> FINAL STAGING UAT (PHASE 6D RECONCILIATION): ALL ASSERTIONS PASSED (100%) <<<\n";
	echo "========================================================================\n";
	exit( 0 );
} else {
	echo ">>> FINAL STAGING UAT: FAILED <<<\n";
	echo "========================================================================\n";
	exit( 1 );
}
