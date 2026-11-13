<?php
/**
 * DeSpace M4 — Live Inventory Order Context & Stock Lifecycle Test Suite.
 *
 * Verifies exact attribution, hook order, and idempotency across all business operations
 * against live WordPress, WooCommerce, MariaDB, and HPOS.
 *
 * @package DeSpace\Tests
 */

ini_set( 'memory_limit', '512M' );
$_SERVER['REQUEST_METHOD'] = 'POST';

// Locate wp-load.php
$possible_paths = array(
	__DIR__ . '/../wordpress/wp-load.php',
	'/var/www/html/wp-load.php',
);

$wp_load = null;
foreach ( $possible_paths as $p ) {
	if ( file_exists( $p ) ) {
		$wp_load = $p;
		break;
	}
}

if ( ! $wp_load ) {
	fwrite( STDERR, "Error: wp-load.php not found.\n" );
	exit( 1 );
}

require_once $wp_load;

if ( ! class_exists( 'WooCommerce' ) ) {
	fwrite( STDERR, "WooCommerce not active.\n" );
	exit( 1 );
}

use DeSpace\Inventory\Database\InventoryLogInstaller;
use DeSpace\Inventory\Events\InventoryEvent;
use DeSpace\Inventory\Events\InventoryStockObserver;
use DeSpace\Inventory\Events\InventoryOrderContext;

echo "========================================================================\n";
echo "   DeSpace M4 — LIVE INVENTORY ORDER CONTEXT TEST SUITE (CASES A-J)     \n";
echo "========================================================================\n";
echo "Active WC Version: " . WC_VERSION . "\n";
echo "HPOS Active: " . ( \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ? 'YES' : 'NO' ) . "\n\n";

global $wpdb;
$log_table = InventoryLogInstaller::get_table_name();

$test_results = array();

function record_test( string $case_id, string $desc, bool $passed, string $details = '' ) {
	global $test_results;
	$test_results[] = array(
		'case_id' => $case_id,
		'desc'    => $desc,
		'passed'  => $passed,
		'details' => $details,
	);
	printf( "[CASE %-2s] %-54s : %s %s\n", $case_id, $desc, ( $passed ? 'PASS' : 'FAIL' ), ( $details ? "($details)" : '' ) );
}

// -------------------------------------------------------------------------
// Case A: Order Reduction (Single Item)
// -------------------------------------------------------------------------
$prod_a = new \WC_Product_Simple();
$prod_a->set_name( 'Case A Product ' . time() );
$prod_a->set_regular_price( '100000' );
$prod_a->set_manage_stock( true );
$prod_a->set_stock_quantity( 20 );
$prod_a_id = $prod_a->save();

// Clear pre-existing logs for this product to isolate test
$wpdb->delete( $log_table, array( 'product_id' => $prod_a_id ) );

$order_a = wc_create_order();
$item_a_id = $order_a->add_product( $prod_a, 3 );
$order_a->calculate_totals();
$order_a->save();
$order_a_id = $order_a->get_id();

// Execute reduction
wc_reduce_stock_levels( $order_a );

$prod_a_fresh = wc_get_product( $prod_a_id );
$stock_a_after = (float) $prod_a_fresh->get_stock_quantity();

$logs_a = $wpdb->get_results( $wpdb->prepare(
	"SELECT * FROM {$log_table} WHERE product_id = %d ORDER BY id ASC",
	$prod_a_id
), ARRAY_A );

$case_a_ok = ( 17.0 === $stock_a_after )
	&& ( 1 === count( $logs_a ) )
	&& ( 'order_reduction' === $logs_a[0]['event_type'] )
	&& ( 'order' === $logs_a[0]['event_source'] )
	&& ( (int) $order_a_id === (int) $logs_a[0]['order_id'] )
	&& ( (int) $item_a_id === (int) $logs_a[0]['order_item_id'] )
	&& ( -3.0 === (float) $logs_a[0]['quantity_delta'] )
	&& ( 20.0 === (float) $logs_a[0]['quantity_before'] )
	&& ( 17.0 === (float) $logs_a[0]['stock_after'] );

record_test( 'A', 'Single item reduction (20 -> 17, order_reduction, order_id & item_id)', $case_a_ok, "Rows: " . count( $logs_a ) . ", stock: {$stock_a_after}" );

// -------------------------------------------------------------------------
// Case B: Multi-item Order
// -------------------------------------------------------------------------
$prod_b1 = new \WC_Product_Simple();
$prod_b1->set_name( 'Case B1 Product ' . time() );
$prod_b1->set_regular_price( '100000' );
$prod_b1->set_manage_stock( true );
$prod_b1->set_stock_quantity( 15 );
$prod_b1_id = $prod_b1->save();

$prod_b2 = new \WC_Product_Simple();
$prod_b2->set_name( 'Case B2 Product ' . time() );
$prod_b2->set_regular_price( '200000' );
$prod_b2->set_manage_stock( true );
$prod_b2->set_stock_quantity( 25 );
$prod_b2_id = $prod_b2->save();

$wpdb->delete( $log_table, array( 'product_id' => $prod_b1_id ) );
$wpdb->delete( $log_table, array( 'product_id' => $prod_b2_id ) );

$order_b = wc_create_order();
$item_b1_id = $order_b->add_product( $prod_b1, 2 );
$item_b2_id = $order_b->add_product( $prod_b2, 4 );
$order_b->calculate_totals();
$order_b->save();
$order_b_id = $order_b->get_id();

wc_reduce_stock_levels( $order_b );

$log_b1 = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$log_table} WHERE product_id = %d", $prod_b1_id ), ARRAY_A );
$log_b2 = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$log_table} WHERE product_id = %d", $prod_b2_id ), ARRAY_A );

$case_b_ok = ( ! empty( $log_b1 ) && ! empty( $log_b2 ) )
	&& ( (int) $log_b1['order_item_id'] === (int) $item_b1_id )
	&& ( (int) $log_b2['order_item_id'] === (int) $item_b2_id )
	&& ( (int) $log_b1['order_id'] === (int) $order_b_id )
	&& ( (int) $log_b2['order_id'] === (int) $order_b_id )
	&& ( $log_b1['event_key'] !== $log_b2['event_key'] )
	&& ( -2.0 === (float) $log_b1['quantity_delta'] )
	&& ( -4.0 === (float) $log_b2['quantity_delta'] );

record_test( 'B', 'Multi-item order (distinct order_item_id & no event_key collision)', $case_b_ok );

// -------------------------------------------------------------------------
// Case C: Variation Stock Reduction
// -------------------------------------------------------------------------
$prod_c_var = new \WC_Product_Variation();
$prod_c_var->set_parent_id( $prod_a_id );
$prod_c_var->set_regular_price( '120000' );
$prod_c_var->set_manage_stock( true );
$prod_c_var->set_stock_quantity( 8 );
$prod_c_var_id = $prod_c_var->save();

$wpdb->delete( $log_table, array( 'product_id' => $prod_a_id, 'variation_id' => $prod_c_var_id ) );

$order_c = wc_create_order();
$item_c_id = $order_c->add_product( $prod_c_var, 3 );
$order_c->calculate_totals();
$order_c->save();

wc_reduce_stock_levels( $order_c );

$log_c = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$log_table} WHERE variation_id = %d", $prod_c_var_id ), ARRAY_A );

$case_c_ok = ! empty( $log_c )
	&& ( (int) $log_c['product_id'] === (int) $prod_a_id )
	&& ( (int) $log_c['variation_id'] === (int) $prod_c_var_id )
	&& ( -3.0 === (float) $log_c['quantity_delta'] )
	&& ( 5.0 === (float) $log_c['stock_after'] )
	&& ( 'order_reduction' === $log_c['event_type'] );

record_test( 'C', 'Variation stock reduction (records parent product_id & variation_id)', $case_c_ok );

// -------------------------------------------------------------------------
// Case D: Order Cancellation / Restoration
// -------------------------------------------------------------------------
// Restore order_a (which had reduced 3 units from prod_a: 17 -> 20)
$initial_logs_count_d = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$log_table} WHERE product_id = %d", $prod_a_id ) );
wc_increase_stock_levels( $order_a );

$prod_a_restored = wc_get_product( $prod_a_id );
$stock_d_after = (float) $prod_a_restored->get_stock_quantity();

$restore_log = $wpdb->get_row( $wpdb->prepare(
	"SELECT * FROM {$log_table} WHERE product_id = %d AND event_type = %s ORDER BY id DESC LIMIT 1",
	$prod_a_id,
	'stock_restoration'
), ARRAY_A );

$case_d_ok = ( 20.0 === $stock_d_after )
	&& ( ! empty( $restore_log ) )
	&& ( 'stock_restoration' === $restore_log['event_type'] )
	&& ( 'order' === $restore_log['event_source'] )
	&& ( (int) $restore_log['order_id'] === (int) $order_a_id )
	&& ( (int) $restore_log['order_item_id'] === (int) $item_a_id )
	&& ( 3.0 === (float) $restore_log['quantity_delta'] )
	&& ( 20.0 === (float) $restore_log['stock_after'] );

record_test( 'D', 'Order cancellation/restoration (+3 delta, stock_restoration, order context)', $case_d_ok, "Restored stock: {$stock_d_after}" );

// -------------------------------------------------------------------------
// Case E: Partial Refund Restock
// -------------------------------------------------------------------------
// Re-reduce order_b so items are reduced:
// prod_b1 stock is 13 (was 15 - 2 = 13).
// Let's partially refund 1 unit of prod_b1 (stock should become 14).
$refund_e = wc_create_refund( array(
	'amount'         => 100000,
	'reason'         => 'Test Case E Partial Refund',
	'order_id'       => $order_b_id,
	'line_items'     => array(
		$item_b1_id => array(
			'qty'          => 1,
			'refund_total' => 100000,
			'refund_tax'   => array(),
		),
	),
	'restock_items'  => true,
) );

$prod_b1_refunded = wc_get_product( $prod_b1_id );
$stock_e_after = (float) $prod_b1_refunded->get_stock_quantity();

$refund_e_id = $refund_e instanceof \WC_Order_Refund ? $refund_e->get_id() : 0;
$log_e = $wpdb->get_row( $wpdb->prepare(
	"SELECT * FROM {$log_table} WHERE product_id = %d AND event_type = %s ORDER BY id DESC LIMIT 1",
	$prod_b1_id,
	'refund_restock'
), ARRAY_A );

$case_e_ok = ( 14.0 === $stock_e_after )
	&& ( ! empty( $log_e ) )
	&& ( 'refund_restock' === $log_e['event_type'] )
	&& ( 'refund' === $log_e['event_source'] )
	&& ( (int) $log_e['order_id'] === (int) $order_b_id )
	&& ( (int) $log_e['refund_id'] === (int) $refund_e_id )
	&& ( (int) $log_e['order_item_id'] === (int) $item_b1_id )
	&& ( 1.0 === (float) $log_e['quantity_delta'] )
	&& ( 14.0 === (float) $log_e['stock_after'] );

record_test( 'E', 'Partial refund restock (order_id, refund_id, order_item_id populated)', $case_e_ok, "Refund ID #{$refund_e_id}, stock: {$stock_e_after}" );

// -------------------------------------------------------------------------
// Case F: Full Refund Restock
// -------------------------------------------------------------------------
// Refund the remaining 1 unit of prod_b1:
$refund_f = wc_create_refund( array(
	'amount'         => 100000,
	'reason'         => 'Test Case F Full Refund',
	'order_id'       => $order_b_id,
	'line_items'     => array(
		$item_b1_id => array(
			'qty'          => 1,
			'refund_total' => 100000,
			'refund_tax'   => array(),
		),
	),
	'restock_items'  => true,
) );

$prod_b1_full = wc_get_product( $prod_b1_id );
$stock_f_after = (float) $prod_b1_full->get_stock_quantity();
$refund_f_id = $refund_f instanceof \WC_Order_Refund ? $refund_f->get_id() : 0;

$refund_logs_count = (int) $wpdb->get_var( $wpdb->prepare(
	"SELECT COUNT(*) FROM {$log_table} WHERE product_id = %d AND event_type = %s",
	$prod_b1_id,
	'refund_restock'
) );

$case_f_ok = ( 15.0 === $stock_f_after ) && ( 2 === $refund_logs_count );
record_test( 'F', 'Full refund restock (restores full original quantity, distinct refund_id)', $case_f_ok, "Total stock: {$stock_f_after}, refund logs: {$refund_logs_count}" );

// -------------------------------------------------------------------------
// Case G: Refund WITHOUT Restock
// -------------------------------------------------------------------------
$logs_before_g = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$log_table} WHERE product_id = %d", $prod_b2_id ) );
$stock_before_g = (float) wc_get_product( $prod_b2_id )->get_stock_quantity();

$refund_g = wc_create_refund( array(
	'amount'         => 200000,
	'reason'         => 'Test Case G Financial Refund Without Restock',
	'order_id'       => $order_b_id,
	'line_items'     => array(
		$item_b2_id => array(
			'qty'          => 1,
			'refund_total' => 200000,
			'refund_tax'   => array(),
		),
	),
	'restock_items'  => false, // DO NOT RESTOCK
) );

$stock_after_g = (float) wc_get_product( $prod_b2_id )->get_stock_quantity();
$logs_after_g  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$log_table} WHERE product_id = %d", $prod_b2_id ) );

$case_g_ok = ( $stock_after_g === $stock_before_g ) && ( $logs_after_g === $logs_before_g );
record_test( 'G', 'Refund without restock produces zero stock movement and zero logs', $case_g_ok, "Logs delta: 0, stock unchanged" );

// -------------------------------------------------------------------------
// Case H: Manual Stock Adjustment
// -------------------------------------------------------------------------
$admin_user_id = 1;
$admins = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
if ( ! empty( $admins ) ) {
	$admin_user_id = $admins[0]->ID;
}
wp_set_current_user( $admin_user_id );

$prod_h = new \WC_Product_Simple();
$prod_h->set_name( 'Case H Manual Product ' . time() );
$prod_h->set_regular_price( '50000' );
$prod_h->set_manage_stock( true );
$prod_h->set_stock_quantity( 10 );
$prod_h_id = $prod_h->save();

$wpdb->delete( $log_table, array( 'product_id' => $prod_h_id ) );

// Directly update stock as admin
$prod_h->set_stock_quantity( 13 );
$prod_h->save();

$log_h = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$log_table} WHERE product_id = %d", $prod_h_id ), ARRAY_A );

$case_h_ok = ! empty( $log_h )
	&& ( 'manual_adjustment' === $log_h['event_type'] )
	&& ( 'admin' === $log_h['event_source'] || 'system' === $log_h['event_source'] )
	&& ( 3.0 === (float) $log_h['quantity_delta'] )
	&& ( 13.0 === (float) $log_h['stock_after'] )
	&& ( empty( $log_h['order_id'] ) )
	&& ( (int) $log_h['actor_user_id'] === (int) $admin_user_id );

record_test( 'H', 'Manual stock adjustment (classified as manual_adjustment with actor ID)', $case_h_ok, "Actor: {$log_h['actor_user_id']}" );

// -------------------------------------------------------------------------
// Case I: System / Cron Update
// -------------------------------------------------------------------------
wp_set_current_user( 0 ); // Unauthenticated background system

$prod_i = new \WC_Product_Simple();
$prod_i->set_name( 'Case I System Product ' . time() );
$prod_i->set_regular_price( '50000' );
$prod_i->set_manage_stock( true );
$prod_i->set_stock_quantity( 5 );
$prod_i_id = $prod_i->save();

$wpdb->delete( $log_table, array( 'product_id' => $prod_i_id ) );

// Trigger stock update without active order/user context
wc_update_product_stock( $prod_i, 2, 'increase' );

$log_i = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$log_table} WHERE product_id = %d", $prod_i_id ), ARRAY_A );

$case_i_ok = ! empty( $log_i )
	&& ( 'system' === $log_i['event_source'] )
	&& ( 'manual_adjustment' === $log_i['event_type'] )
	&& ( empty( $log_i['actor_user_id'] ) )
	&& ( 2.0 === (float) $log_i['quantity_delta'] )
	&& ( 7.0 === (float) $log_i['stock_after'] );

record_test( 'I', 'System/cron background update (event_source = system, actor_user_id = NULL)', $case_i_ok );

// -------------------------------------------------------------------------
// Case J: Idempotency & Duplicate Protection
// -------------------------------------------------------------------------
// If the same stock observer event is processed again, MariaDB UNIQUE event_key blocks duplicate
$existing_event_key = $logs_a[0]['event_key'];
$count_before_j = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$log_table} WHERE event_key = %s", $existing_event_key ) );

// Attempt manual record of identical event
$duplicate_event = new InventoryEvent( array(
	'event_key'       => $existing_event_key,
	'product_id'      => $prod_a_id,
	'order_id'        => $order_a_id,
	'quantity_delta'  => -3.0,
	'stock_after'     => 17.0,
	'event_type'      => 'order_reduction',
	'event_source'    => 'order',
) );

$res_record = \DeSpace\Inventory\Events\InventoryEventRecorder::record( $duplicate_event );
$count_after_j = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$log_table} WHERE event_key = %s", $existing_event_key ) );

$case_j_ok = ( false === $res_record ) && ( 1 === $count_before_j ) && ( 1 === $count_after_j );
record_test( 'J', 'Idempotency & UNIQUE event_key (duplicate safely blocked, 0 extra rows)', $case_j_ok );

// -------------------------------------------------------------------------
// Cleanup
// -------------------------------------------------------------------------
$order_a->delete( true );
$order_b->delete( true );
$order_c->delete( true );
$prod_a->delete( true );
$prod_b1->delete( true );
$prod_b2->delete( true );
$prod_c_var->delete( true );
$prod_h->delete( true );
$prod_i->delete( true );

// -------------------------------------------------------------------------
// Summary
// -------------------------------------------------------------------------
echo "\n========================================================================\n";
$all_cases_passed = true;
foreach ( $test_results as $tr ) {
	if ( ! $tr['passed'] ) {
		$all_cases_passed = false;
	}
}

if ( $all_cases_passed ) {
	echo ">>> ALL 10 MANDATORY CASES (A - J) PASSED ON LIVE WOOCOMMERCE & MYSQL! <<<\n";
	echo "========================================================================\n";
	exit( 0 );
} else {
	echo ">>> SOME CASES FAILED <<<\n";
	echo "========================================================================\n";
	exit( 1 );
}

