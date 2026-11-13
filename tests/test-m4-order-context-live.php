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
echo "   DeSpace M4 — LIVE INVENTORY ORDER CONTEXT TEST SUITE (CASES A-U)     \n";
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

try {
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
// Case K: Duplicate Product Line Items / Restoration
// -------------------------------------------------------------------------
$prod_k = new \WC_Product_Simple();
$prod_k->set_name( 'Case K Duplicate Item Prod ' . time() );
$prod_k->set_regular_price( '100000' );
$prod_k->set_manage_stock( true );
$prod_k->set_stock_quantity( 30 );
$prod_k_id = $prod_k->save();

$wpdb->delete( $log_table, array( 'product_id' => $prod_k_id ) );

$order_k = wc_create_order();
$item_k1_id = $order_k->add_product( $prod_k, 4 );
$item_k2_id = $order_k->add_product( $prod_k, 6 );
$order_k->calculate_totals();
$order_k->save();
$order_k_id = $order_k->get_id();

wc_reduce_stock_levels( $order_k );
$stock_k_after_reduce = (float) wc_get_product( $prod_k_id )->get_stock_quantity();

// Now restore stock for both duplicate line items
wc_increase_stock_levels( $order_k );
$stock_k_after_restore = (float) wc_get_product( $prod_k_id )->get_stock_quantity();

$restore_k_logs = $wpdb->get_results( $wpdb->prepare(
	"SELECT * FROM {$log_table} WHERE product_id = %d AND event_type = %s ORDER BY id ASC",
	$prod_k_id,
	'stock_restoration'
), ARRAY_A );

$case_k_ok = ( 20.0 === $stock_k_after_reduce )
	&& ( 30.0 === $stock_k_after_restore )
	&& ( 2 === count( $restore_k_logs ) )
	&& ( (int) $restore_k_logs[0]['order_item_id'] === (int) $item_k1_id )
	&& ( (int) $restore_k_logs[1]['order_item_id'] === (int) $item_k2_id )
	&& ( 4.0 === (float) $restore_k_logs[0]['quantity_delta'] )
	&& ( 6.0 === (float) $restore_k_logs[1]['quantity_delta'] )
	&& ( $restore_k_logs[0]['event_key'] !== $restore_k_logs[1]['event_key'] );

record_test( 'K', 'Duplicate product line items / restoration (distinct order_item_id & keys)', $case_k_ok, "Restored: 2 logs, deltas 4 & 6" );

// -------------------------------------------------------------------------
// Case L: Duplicate Product Line Items / Refund Restock
// -------------------------------------------------------------------------
$prod_l = new \WC_Product_Simple();
$prod_l->set_name( 'Case L Duplicate Item Refund ' . time() );
$prod_l->set_regular_price( '100000' );
$prod_l->set_manage_stock( true );
$prod_l->set_stock_quantity( 40 );
$prod_l_id = $prod_l->save();

$wpdb->delete( $log_table, array( 'product_id' => $prod_l_id ) );

$order_l = wc_create_order();
$item_l1_id = $order_l->add_product( $prod_l, 3 );
$item_l2_id = $order_l->add_product( $prod_l, 5 );
$order_l->calculate_totals();
$order_l->save();
$order_l_id = $order_l->get_id();

wc_reduce_stock_levels( $order_l ); // Stock: 40 - 8 = 32

$refund_l = wc_create_refund( array(
	'amount'         => 600000,
	'reason'         => 'Case L Duplicate Lines Refund',
	'order_id'       => $order_l_id,
	'line_items'     => array(
		$item_l1_id => array(
			'qty'          => 2,
			'refund_total' => 200000,
			'refund_tax'   => array(),
		),
		$item_l2_id => array(
			'qty'          => 4,
			'refund_total' => 400000,
			'refund_tax'   => array(),
		),
	),
	'restock_items'  => true,
) );

$refund_l_id = $refund_l instanceof \WC_Order_Refund ? $refund_l->get_id() : 0;
$stock_l_after_refund = (float) wc_get_product( $prod_l_id )->get_stock_quantity();

$refund_l_logs = $wpdb->get_results( $wpdb->prepare(
	"SELECT * FROM {$log_table} WHERE product_id = %d AND event_type = %s ORDER BY id ASC",
	$prod_l_id,
	'refund_restock'
), ARRAY_A );

$case_l_ok = ( 38.0 === $stock_l_after_refund )
	&& ( 2 === count( $refund_l_logs ) )
	&& ( (int) $refund_l_logs[0]['order_id'] === (int) $order_l_id )
	&& ( (int) $refund_l_logs[0]['refund_id'] === (int) $refund_l_id )
	&& ( (int) $refund_l_logs[0]['order_item_id'] === (int) $item_l1_id )
	&& ( 2.0 === (float) $refund_l_logs[0]['quantity_delta'] )
	&& ( (int) $refund_l_logs[1]['order_id'] === (int) $order_l_id )
	&& ( (int) $refund_l_logs[1]['refund_id'] === (int) $refund_l_id )
	&& ( (int) $refund_l_logs[1]['order_item_id'] === (int) $item_l2_id )
	&& ( 4.0 === (float) $refund_l_logs[1]['quantity_delta'] )
	&& ( $refund_l_logs[0]['event_key'] !== $refund_l_logs[1]['event_key'] );

record_test( 'L', 'Duplicate product line items / refund (distinct order_item_id, refund_id & keys)', $case_l_ok, "Restocked: 2 logs, stock 38" );

// -------------------------------------------------------------------------
// Case M: Multiple Partial Refunds
// -------------------------------------------------------------------------
$prod_m = new \WC_Product_Simple();
$prod_m->set_name( 'Case M Multiple Refunds Prod ' . time() );
$prod_m->set_regular_price( '100000' );
$prod_m->set_manage_stock( true );
$prod_m->set_stock_quantity( 30 );
$prod_m_id = $prod_m->save();

$wpdb->delete( $log_table, array( 'product_id' => $prod_m_id ) );

$order_m = wc_create_order();
$item_m1_id = $order_m->add_product( $prod_m, 4 );
$item_m2_id = $order_m->add_product( $prod_m, 4 );
$order_m->calculate_totals();
$order_m->save();
$order_m_id = $order_m->get_id();

wc_reduce_stock_levels( $order_m ); // Stock: 30 - 8 = 22

// Refund 1 for item_m1
$refund_m1 = wc_create_refund( array(
	'amount'         => 200000,
	'reason'         => 'Case M Partial Refund 1',
	'order_id'       => $order_m_id,
	'line_items'     => array(
		$item_m1_id => array(
			'qty'          => 2,
			'refund_total' => 200000,
			'refund_tax'   => array(),
		),
	),
	'restock_items'  => true,
) );
$refund_m1_id = $refund_m1 instanceof \WC_Order_Refund ? $refund_m1->get_id() : 0;

// Refund 2 for item_m2
$refund_m2 = wc_create_refund( array(
	'amount'         => 300000,
	'reason'         => 'Case M Partial Refund 2',
	'order_id'       => $order_m_id,
	'line_items'     => array(
		$item_m2_id => array(
			'qty'          => 3,
			'refund_total' => 300000,
			'refund_tax'   => array(),
		),
	),
	'restock_items'  => true,
) );
$refund_m2_id = $refund_m2 instanceof \WC_Order_Refund ? $refund_m2->get_id() : 0;

$stock_m_after = (float) wc_get_product( $prod_m_id )->get_stock_quantity();

$log_m1 = $wpdb->get_row( $wpdb->prepare(
	"SELECT * FROM {$log_table} WHERE refund_id = %d AND order_item_id = %d",
	$refund_m1_id,
	$item_m1_id
), ARRAY_A );

$log_m2 = $wpdb->get_row( $wpdb->prepare(
	"SELECT * FROM {$log_table} WHERE refund_id = %d AND order_item_id = %d",
	$refund_m2_id,
	$item_m2_id
), ARRAY_A );

$case_m_ok = ( $refund_m1_id > 0 )
	&& ( $refund_m2_id > 0 )
	&& ( $refund_m1_id !== $refund_m2_id )
	&& ( 27.0 === $stock_m_after )
	&& ( ! empty( $log_m1 ) )
	&& ( ! empty( $log_m2 ) )
	&& ( (int) $log_m1['order_id'] === (int) $order_m_id )
	&& ( (int) $log_m2['order_id'] === (int) $order_m_id )
	&& ( 2.0 === (float) $log_m1['quantity_delta'] )
	&& ( 3.0 === (float) $log_m2['quantity_delta'] )
	&& ( $log_m1['event_key'] !== $log_m2['event_key'] );

record_test( 'M', 'Multiple partial refunds (distinct refund IDs, correct item IDs & distinct keys)', $case_m_ok, "Refunds #{$refund_m1_id} & #{$refund_m2_id}" );

// -------------------------------------------------------------------------
// Case N: Complete Variation Lifecycle Assertions (Individual & Parent-Managed)
// -------------------------------------------------------------------------
// Part 1: Individually stock-managed variation
$var_parent_n1 = new \WC_Product_Variable();
$var_parent_n1->set_name( 'Case N Variable Parent ' . time() );
$var_parent_n1_id = $var_parent_n1->save();

$var_n1 = new \WC_Product_Variation();
$var_n1->set_parent_id( $var_parent_n1_id );
$var_n1->set_regular_price( '120000' );
$var_n1->set_manage_stock( true );
$var_n1->set_stock_quantity( 15 );
$var_n1_id = $var_n1->save();

$wpdb->delete( $log_table, array( 'product_id' => $var_parent_n1_id ) );

$order_n1 = wc_create_order();
$item_n1_id = $order_n1->add_product( wc_get_product( $var_n1_id ), 4 );
$order_n1->calculate_totals();
$order_n1->save();
$order_n1_id = $order_n1->get_id();

// 1. Reduction: 15 -> 11
wc_reduce_stock_levels( $order_n1 );
$var_n1_stock_after_reduce = (float) wc_get_product( $var_n1_id )->get_stock_quantity();

// 2. Refund Restock: 11 -> 13
$refund_n1 = wc_create_refund( array(
	'amount'         => 240000,
	'reason'         => 'Case N Variation Refund',
	'order_id'       => $order_n1_id,
	'line_items'     => array(
		$item_n1_id => array(
			'qty'          => 2,
			'refund_total' => 240000,
			'refund_tax'   => array(),
		),
	),
	'restock_items'  => true,
) );
$refund_n1_id = $refund_n1 instanceof \WC_Order_Refund ? $refund_n1->get_id() : 0;
$var_n1_stock_after_refund = (float) wc_get_product( $var_n1_id )->get_stock_quantity();

// 3. Restoration: 13 -> 15 (restore remaining 2 reduced units)
$order_n1 = wc_get_order( $order_n1_id );
wc_increase_stock_levels( $order_n1 );
$var_n1_stock_after_restore = (float) wc_get_product( $var_n1_id )->get_stock_quantity();

$log_n1_reduce = $wpdb->get_row( $wpdb->prepare(
	"SELECT * FROM {$log_table} WHERE variation_id = %d AND event_type = %s",
	$var_n1_id,
	'order_reduction'
), ARRAY_A );

$log_n1_refund = $wpdb->get_row( $wpdb->prepare(
	"SELECT * FROM {$log_table} WHERE variation_id = %d AND event_type = %s",
	$var_n1_id,
	'refund_restock'
), ARRAY_A );

$log_n1_restore = $wpdb->get_row( $wpdb->prepare(
	"SELECT * FROM {$log_table} WHERE variation_id = %d AND event_type = %s",
	$var_n1_id,
	'stock_restoration'
), ARRAY_A );

$case_n1_ok = ( 11.0 === $var_n1_stock_after_reduce )
	&& ( 13.0 === $var_n1_stock_after_refund )
	&& ( 15.0 === $var_n1_stock_after_restore )
	// Reduction row assertions
	&& ( ! empty( $log_n1_reduce ) )
	&& ( 'order_reduction' === $log_n1_reduce['event_type'] )
	&& ( 'order' === $log_n1_reduce['event_source'] )
	&& ( (int) $log_n1_reduce['product_id'] === (int) $var_parent_n1_id )
	&& ( (int) $log_n1_reduce['variation_id'] === (int) $var_n1_id )
	&& ( (int) $log_n1_reduce['order_id'] === (int) $order_n1_id )
	&& ( (int) $log_n1_reduce['order_item_id'] === (int) $item_n1_id )
	&& ( empty( $log_n1_reduce['refund_id'] ) )
	&& ( -4.0 === (float) $log_n1_reduce['quantity_delta'] )
	&& ( 15.0 === (float) $log_n1_reduce['quantity_before'] )
	&& ( 11.0 === (float) $log_n1_reduce['stock_after'] )
	&& ( ! empty( $log_n1_reduce['event_key'] ) )
	// Refund restock row assertions
	&& ( ! empty( $log_n1_refund ) )
	&& ( 'refund_restock' === $log_n1_refund['event_type'] )
	&& ( 'refund' === $log_n1_refund['event_source'] )
	&& ( (int) $log_n1_refund['product_id'] === (int) $var_parent_n1_id )
	&& ( (int) $log_n1_refund['variation_id'] === (int) $var_n1_id )
	&& ( (int) $log_n1_refund['order_id'] === (int) $order_n1_id )
	&& ( (int) $log_n1_refund['order_item_id'] === (int) $item_n1_id )
	&& ( (int) $log_n1_refund['refund_id'] === (int) $refund_n1_id )
	&& ( 2.0 === (float) $log_n1_refund['quantity_delta'] )
	&& ( 11.0 === (float) $log_n1_refund['quantity_before'] )
	&& ( 13.0 === (float) $log_n1_refund['stock_after'] )
	&& ( ! empty( $log_n1_refund['event_key'] ) )
	// Restoration row assertions
	&& ( ! empty( $log_n1_restore ) )
	&& ( 'stock_restoration' === $log_n1_restore['event_type'] )
	&& ( 'order' === $log_n1_restore['event_source'] )
	&& ( (int) $log_n1_restore['product_id'] === (int) $var_parent_n1_id )
	&& ( (int) $log_n1_restore['variation_id'] === (int) $var_n1_id )
	&& ( (int) $log_n1_restore['order_id'] === (int) $order_n1_id )
	&& ( (int) $log_n1_restore['order_item_id'] === (int) $item_n1_id )
	&& ( empty( $log_n1_restore['refund_id'] ) )
	&& ( 2.0 === (float) $log_n1_restore['quantity_delta'] )
	&& ( 13.0 === (float) $log_n1_restore['quantity_before'] )
	&& ( 15.0 === (float) $log_n1_restore['stock_after'] )
	&& ( ! empty( $log_n1_restore['event_key'] ) )
	// Distinct keys
	&& ( $log_n1_reduce['event_key'] !== $log_n1_refund['event_key'] )
	&& ( $log_n1_refund['event_key'] !== $log_n1_restore['event_key'] )
	&& ( $log_n1_reduce['event_key'] !== $log_n1_restore['event_key'] );

// Part 2: Parent-managed stock variation
$var_parent_n2 = new \WC_Product_Variable();
$var_parent_n2->set_name( 'Case N Parent-Managed Variable ' . time() );
$var_parent_n2->set_manage_stock( true );
$var_parent_n2->set_stock_quantity( 20 );
$var_parent_n2_id = $var_parent_n2->save();

$var_n2 = new \WC_Product_Variation();
$var_n2->set_parent_id( $var_parent_n2_id );
$var_n2->set_regular_price( '150000' );
$var_n2->set_manage_stock( false );
$var_n2_id = $var_n2->save();

$wpdb->delete( $log_table, array( 'product_id' => $var_parent_n2_id ) );

$order_n2 = wc_create_order();
$item_n2_id = $order_n2->add_product( wc_get_product( $var_n2_id ), 3 );
$order_n2->calculate_totals();
$order_n2->save();
$order_n2_id = $order_n2->get_id();

// 1. Reduction: 20 -> 17
wc_reduce_stock_levels( $order_n2 );
$parent_n2_stock_after_reduce = (float) wc_get_product( $var_parent_n2_id )->get_stock_quantity();

// 2. Refund Restock: 17 -> 18 (refund 1 item)
$refund_n2 = wc_create_refund( array(
	'amount'         => 150000,
	'reason'         => 'Case N Parent Var Refund',
	'order_id'       => $order_n2_id,
	'line_items'     => array(
		$item_n2_id => array(
			'qty'          => 1,
			'refund_total' => 150000,
			'refund_tax'   => array(),
		),
	),
	'restock_items'  => true,
) );
$refund_n2_id = $refund_n2 instanceof \WC_Order_Refund ? $refund_n2->get_id() : 0;
$parent_n2_stock_after_refund = (float) wc_get_product( $var_parent_n2_id )->get_stock_quantity();

// 3. Restoration: 18 -> 20 (restore remaining 2 units)
$order_n2 = wc_get_order( $order_n2_id );
wc_increase_stock_levels( $order_n2 );
$parent_n2_stock_after_restore = (float) wc_get_product( $var_parent_n2_id )->get_stock_quantity();

$log_n2_reduce = $wpdb->get_row( $wpdb->prepare(
	"SELECT * FROM {$log_table} WHERE product_id = %d AND order_id = %d AND event_type = %s",
	$var_parent_n2_id,
	$order_n2_id,
	'order_reduction'
), ARRAY_A );

$log_n2_refund = $wpdb->get_row( $wpdb->prepare(
	"SELECT * FROM {$log_table} WHERE product_id = %d AND order_id = %d AND event_type = %s",
	$var_parent_n2_id,
	$order_n2_id,
	'refund_restock'
), ARRAY_A );

$log_n2_restore = $wpdb->get_row( $wpdb->prepare(
	"SELECT * FROM {$log_table} WHERE product_id = %d AND order_id = %d AND event_type = %s",
	$var_parent_n2_id,
	$order_n2_id,
	'stock_restoration'
), ARRAY_A );

$case_n2_ok = ( 17.0 === $parent_n2_stock_after_reduce )
	&& ( 18.0 === $parent_n2_stock_after_refund )
	&& ( 20.0 === $parent_n2_stock_after_restore )
	// Reduction row assertions
	&& ( ! empty( $log_n2_reduce ) )
	&& ( 'order_reduction' === $log_n2_reduce['event_type'] )
	&& ( (int) $log_n2_reduce['product_id'] === (int) $var_parent_n2_id )
	&& ( null === $log_n2_reduce['variation_id'] )
	&& ( (int) $log_n2_reduce['order_item_id'] === (int) $item_n2_id )
	&& ( empty( $log_n2_reduce['refund_id'] ) )
	&& ( -3.0 === (float) $log_n2_reduce['quantity_delta'] )
	&& ( 20.0 === (float) $log_n2_reduce['quantity_before'] )
	&& ( 17.0 === (float) $log_n2_reduce['stock_after'] )
	// Refund restock row assertions
	&& ( ! empty( $log_n2_refund ) )
	&& ( 'refund_restock' === $log_n2_refund['event_type'] )
	&& ( (int) $log_n2_refund['product_id'] === (int) $var_parent_n2_id )
	&& ( null === $log_n2_refund['variation_id'] )
	&& ( (int) $log_n2_refund['order_item_id'] === (int) $item_n2_id )
	&& ( (int) $log_n2_refund['refund_id'] === (int) $refund_n2_id )
	&& ( 1.0 === (float) $log_n2_refund['quantity_delta'] )
	&& ( 17.0 === (float) $log_n2_refund['quantity_before'] )
	&& ( 18.0 === (float) $log_n2_refund['stock_after'] )
	// Restoration row assertions
	&& ( ! empty( $log_n2_restore ) )
	&& ( 'stock_restoration' === $log_n2_restore['event_type'] )
	&& ( (int) $log_n2_restore['product_id'] === (int) $var_parent_n2_id )
	&& ( null === $log_n2_restore['variation_id'] )
	&& ( (int) $log_n2_restore['order_item_id'] === (int) $item_n2_id )
	&& ( empty( $log_n2_restore['refund_id'] ) )
	&& ( 2.0 === (float) $log_n2_restore['quantity_delta'] )
	&& ( 18.0 === (float) $log_n2_restore['quantity_before'] )
	&& ( 20.0 === (float) $log_n2_restore['stock_after'] )
	// Distinct keys
	&& ( $log_n2_reduce['event_key'] !== $log_n2_refund['event_key'] )
	&& ( $log_n2_refund['event_key'] !== $log_n2_restore['event_key'] )
	&& ( $log_n2_reduce['event_key'] !== $log_n2_restore['event_key'] );

$case_n_ok = $case_n1_ok && $case_n2_ok;
record_test( 'N', 'Variation complete lifecycle assertions (own-stock & parent-managed: reduce, refund, restore)', $case_n_ok, "Part 1 & 2 fully verified" );

// -------------------------------------------------------------------------
// Case O-1: Late-Denied Stock Reduction (Priority 20)
// -------------------------------------------------------------------------
$prod_o1 = new \WC_Product_Simple();
$prod_o1->set_name( 'Case O1 Late Denied Reduce ' . time() );
$prod_o1->set_regular_price( '75000' );
$prod_o1->set_manage_stock( true );
$prod_o1->set_stock_quantity( 10 );
$prod_o1_id = $prod_o1->save();

$wpdb->delete( $log_table, array( 'product_id' => $prod_o1_id ) );

$order_o1 = wc_create_order();
$item_o1_id = $order_o1->add_product( $prod_o1, 3 );
$order_o1->calculate_totals();
$order_o1->save();
$order_o1_id = $order_o1->get_id();

// External filter returns false at priority 20 (after M4 priority 10 initialization)
$late_filter_reduce = function( $can, $order ) { return false; };
add_filter( 'woocommerce_can_reduce_order_stock', $late_filter_reduce, 20, 2 );

wc_reduce_stock_levels( $order_o1 );

remove_filter( 'woocommerce_can_reduce_order_stock', $late_filter_reduce, 20 );

$stock_o1_after_denied = (float) wc_get_product( $prod_o1_id )->get_stock_quantity();
$logs_count_o1_order = (int) $wpdb->get_var( $wpdb->prepare(
	"SELECT COUNT(*) FROM {$log_table} WHERE order_id = %d",
	$order_o1_id
) );

$context_clean_o1 = ! \DeSpace\Inventory\Events\InventoryOrderContext::has_order_context()
	&& ! \DeSpace\Inventory\Events\InventoryOrderContext::has_refund_context();

// Subsequent manual update
wp_set_current_user( 1 );
$prod_o1->set_stock_quantity( 14 );
$prod_o1->save();

$manual_log_o1 = $wpdb->get_row( $wpdb->prepare(
	"SELECT * FROM {$log_table} WHERE product_id = %d ORDER BY id DESC LIMIT 1",
	$prod_o1_id
), ARRAY_A );

$case_o1_ok = ( 10.0 === $stock_o1_after_denied )
	&& ( 0 === $logs_count_o1_order )
	&& $context_clean_o1
	&& ( ! empty( $manual_log_o1 ) )
	&& ( 'manual_adjustment' === $manual_log_o1['event_type'] )
	&& ( empty( $manual_log_o1['order_id'] ) )
	&& ( empty( $manual_log_o1['refund_id'] ) )
	&& ( empty( $manual_log_o1['order_item_id'] ) )
	&& ( (int) $manual_log_o1['actor_user_id'] === 1 )
	&& ( 4.0 === (float) $manual_log_o1['quantity_delta'] )
	&& ( 14.0 === (float) $manual_log_o1['stock_after'] );

record_test( 'O-1', 'Late-denied reduction at priority 20 (clean context, zero order logs, manual adjustment)', $case_o1_ok );

// -------------------------------------------------------------------------
// Case O-2: Late-Denied Stock Restoration (Priority 20)
// -------------------------------------------------------------------------
$prod_o2 = new \WC_Product_Simple();
$prod_o2->set_name( 'Case O2 Late Denied Restore ' . time() );
$prod_o2->set_regular_price( '80000' );
$prod_o2->set_manage_stock( true );
$prod_o2->set_stock_quantity( 10 );
$prod_o2_id = $prod_o2->save();

$order_o2 = wc_create_order();
$item_o2_id = $order_o2->add_product( $prod_o2, 3 );
$order_o2->calculate_totals();
$order_o2->save();
$order_o2_id = $order_o2->get_id();

wc_reduce_stock_levels( $order_o2 ); // 10 -> 7
$stock_o2_reduced = (float) wc_get_product( $prod_o2_id )->get_stock_quantity();

// External filter returns false at priority 20 during restore
$late_filter_restore = function( $can, $order ) { return false; };
add_filter( 'woocommerce_can_restore_order_stock', $late_filter_restore, 20, 2 );

wc_increase_stock_levels( $order_o2 );

remove_filter( 'woocommerce_can_restore_order_stock', $late_filter_restore, 20 );

$stock_o2_after_denied = (float) wc_get_product( $prod_o2_id )->get_stock_quantity();
$logs_count_o2_restore = (int) $wpdb->get_var( $wpdb->prepare(
	"SELECT COUNT(*) FROM {$log_table} WHERE order_id = %d AND event_type = %s",
	$order_o2_id,
	'stock_restoration'
) );

$context_clean_o2 = ! \DeSpace\Inventory\Events\InventoryOrderContext::has_order_context()
	&& ! \DeSpace\Inventory\Events\InventoryOrderContext::has_refund_context();

// Subsequent manual update
wp_set_current_user( 1 );
$prod_o2 = wc_get_product( $prod_o2_id );
$prod_o2->set_stock_quantity( 12 );
$prod_o2->save();

$manual_log_o2 = $wpdb->get_row( $wpdb->prepare(
	"SELECT * FROM {$log_table} WHERE product_id = %d ORDER BY id DESC LIMIT 1",
	$prod_o2_id
), ARRAY_A );

$case_o2_ok = ( 7.0 === $stock_o2_reduced )
	&& ( 7.0 === $stock_o2_after_denied )
	&& ( 0 === $logs_count_o2_restore )
	&& $context_clean_o2
	&& ( ! empty( $manual_log_o2 ) )
	&& ( 'manual_adjustment' === $manual_log_o2['event_type'] )
	&& ( empty( $manual_log_o2['order_id'] ) )
	&& ( empty( $manual_log_o2['refund_id'] ) )
	&& ( empty( $manual_log_o2['order_item_id'] ) )
	&& ( (int) $manual_log_o2['actor_user_id'] === 1 )
	&& ( 5.0 === (float) $manual_log_o2['quantity_delta'] )
	&& ( 12.0 === (float) $manual_log_o2['stock_after'] );

record_test( 'O-2', 'Late-denied restoration at priority 20 (clean context, zero restore logs, manual adjustment)', $case_o2_ok );

// -------------------------------------------------------------------------
// Case O-3: Late-Denied Refund Restock (Priority 20)
// -------------------------------------------------------------------------
$prod_o3 = new \WC_Product_Simple();
$prod_o3->set_name( 'Case O3 Late Denied Refund ' . time() );
$prod_o3->set_regular_price( '100000' );
$prod_o3->set_manage_stock( true );
$prod_o3->set_stock_quantity( 10 );
$prod_o3_id = $prod_o3->save();

$order_o3 = wc_create_order();
$item_o3_id = $order_o3->add_product( $prod_o3, 3 );
$order_o3->calculate_totals();
$order_o3->save();
$order_o3_id = $order_o3->get_id();

wc_reduce_stock_levels( $order_o3 ); // 10 -> 7

// External filter returns false at priority 20 during refund restock
$late_filter_restock = function( $can, $order, $items ) { return false; };
add_filter( 'woocommerce_can_restock_refunded_items', $late_filter_restock, 20, 3 );

$refund_o3 = wc_create_refund( array(
	'amount'         => 100000,
	'order_id'       => $order_o3_id,
	'line_items'     => array(
		$item_o3_id => array(
			'qty'          => 1,
			'refund_total' => 100000,
			'refund_tax'   => array(),
		),
	),
	'restock_items'  => true,
) );

remove_filter( 'woocommerce_can_restock_refunded_items', $late_filter_restock, 20 );

$stock_o3_after_denied = (float) wc_get_product( $prod_o3_id )->get_stock_quantity();
$logs_count_o3_refund = (int) $wpdb->get_var( $wpdb->prepare(
	"SELECT COUNT(*) FROM {$log_table} WHERE order_id = %d AND event_type = %s",
	$order_o3_id,
	'refund_restock'
) );

$context_clean_o3 = ! \DeSpace\Inventory\Events\InventoryOrderContext::has_order_context()
	&& ! \DeSpace\Inventory\Events\InventoryOrderContext::has_refund_context();

// Subsequent manual update
wp_set_current_user( 1 );
$prod_o3 = wc_get_product( $prod_o3_id );
$prod_o3->set_stock_quantity( 9 );
$prod_o3->save();

$manual_log_o3 = $wpdb->get_row( $wpdb->prepare(
	"SELECT * FROM {$log_table} WHERE product_id = %d ORDER BY id DESC LIMIT 1",
	$prod_o3_id
), ARRAY_A );

$case_o3_ok = ( 7.0 === $stock_o3_after_denied )
	&& ( 0 === $logs_count_o3_refund )
	&& $context_clean_o3
	&& ( ! empty( $manual_log_o3 ) )
	&& ( 'manual_adjustment' === $manual_log_o3['event_type'] )
	&& ( empty( $manual_log_o3['order_id'] ) )
	&& ( empty( $manual_log_o3['refund_id'] ) )
	&& ( empty( $manual_log_o3['order_item_id'] ) )
	&& ( (int) $manual_log_o3['actor_user_id'] === 1 )
	&& ( 2.0 === (float) $manual_log_o3['quantity_delta'] )
	&& ( 9.0 === (float) $manual_log_o3['stock_after'] );

record_test( 'O-3', 'Late-denied refund restock at priority 20 (clean context, zero refund logs, manual adjustment)', $case_o3_ok );

// -------------------------------------------------------------------------
// Case P: FIFO Fallback Safety & Unmatched Entity Isolation
// -------------------------------------------------------------------------
$prod_p1 = new \WC_Product_Simple();
$prod_p1->set_name( 'Case P1 Queued Prod ' . time() );
$prod_p1->set_manage_stock( true );
$prod_p1->set_stock_quantity( 20 );
$prod_p1_id = $prod_p1->save();

$prod_p2 = new \WC_Product_Simple();
$prod_p2->set_name( 'Case P2 Unmatched Prod ' . time() );
$prod_p2->set_manage_stock( true );
$prod_p2->set_stock_quantity( 10 );
$prod_p2_id = $prod_p2->save();

$wpdb->delete( $log_table, array( 'product_id' => $prod_p1_id ) );
$wpdb->delete( $log_table, array( 'product_id' => $prod_p2_id ) );

$order_p = wc_create_order();
$item_p1_id = $order_p->add_product( $prod_p1, 3 );
$order_p->calculate_totals();
$order_p->save();
$order_p_id = $order_p->get_id();

wc_reduce_stock_levels( $order_p ); // prod_p1 stock: 20 -> 17

// Now, start restoration context for order_p
\DeSpace\Inventory\Events\InventoryStockObserver::on_before_restore_order_stock( $order_p );

// While order_p restoration context is open, an UNMATCHED stock mutation occurs on prod_p2 (not in order)
$prod_p2 = wc_get_product( $prod_p2_id );
wc_update_product_stock( $prod_p2, 5, 'increase' ); // 10 -> 15

// Now complete the valid restoration for prod_p1
$prod_p1 = wc_get_product( $prod_p1_id );
wc_update_product_stock( $prod_p1, 3, 'increase' ); // 17 -> 20

\DeSpace\Inventory\Events\InventoryStockObserver::clear_all_context();

$log_p2_unmatched = $wpdb->get_row( $wpdb->prepare(
	"SELECT * FROM {$log_table} WHERE product_id = %d ORDER BY id DESC LIMIT 1",
	$prod_p2_id
), ARRAY_A );

$log_p1_valid = $wpdb->get_row( $wpdb->prepare(
	"SELECT * FROM {$log_table} WHERE product_id = %d AND event_type = %s ORDER BY id DESC LIMIT 1",
	$prod_p1_id,
	'stock_restoration'
), ARRAY_A );

$case_p_ok = ( ! empty( $log_p2_unmatched ) )
	&& ( 'manual_adjustment' === $log_p2_unmatched['event_type'] )
	&& ( empty( $log_p2_unmatched['order_id'] ) )
	&& ( empty( $log_p2_unmatched['order_item_id'] ) )
	&& ( empty( $log_p2_unmatched['refund_id'] ) )
	&& ( 5.0 === (float) $log_p2_unmatched['quantity_delta'] )
	&& ( ! empty( $log_p1_valid ) )
	&& ( 'stock_restoration' === $log_p1_valid['event_type'] )
	&& ( (int) $log_p1_valid['order_id'] === (int) $order_p_id )
	&& ( (int) $log_p1_valid['order_item_id'] === (int) $item_p1_id )
	&& ( 3.0 === (float) $log_p1_valid['quantity_delta'] );

record_test( 'P', 'FIFO fallback safety (unmatched stock entity isolated as manual_adjustment, queue intact)', $case_p_ok );

// -------------------------------------------------------------------------
// Case Q: Parent-Managed Variation Reconciliation (M4-REV-001)
// -------------------------------------------------------------------------
$var_parent_q = new \WC_Product_Variable();
$var_parent_q->set_name( 'Case Q Shared Parent ' . time() );
$var_parent_q->set_manage_stock( true );
$var_parent_q->set_stock_quantity( 30 );
$var_parent_q_id = $var_parent_q->save();

$var_q1 = new \WC_Product_Variation();
$var_q1->set_parent_id( $var_parent_q_id );
$var_q1->set_regular_price( '100000' );
$var_q1->set_manage_stock( false ); // Parent managed
$var_q1_id = $var_q1->save();

$var_q2 = new \WC_Product_Variation();
$var_q2->set_parent_id( $var_parent_q_id );
$var_q2->set_regular_price( '120000' );
$var_q2->set_manage_stock( false ); // Parent managed
$var_q2_id = $var_q2->save();

$wpdb->delete( $log_table, array( 'product_id' => $var_parent_q_id ) );

// Place order for variation 1 (qty 4) -> 30 to 26
$order_q1 = wc_create_order();
$order_q1->add_product( wc_get_product( $var_q1_id ), 4 );
$order_q1->calculate_totals();
$order_q1->save();
wc_reduce_stock_levels( $order_q1 );

// Place order for variation 2 (qty 6) -> 26 to 20
$order_q2 = wc_create_order();
$order_q2->add_product( wc_get_product( $var_q2_id ), 6 );
$order_q2->calculate_totals();
$order_q2->save();
wc_reduce_stock_levels( $order_q2 );

$parent_q_stock = (float) wc_get_product( $var_parent_q_id )->get_stock_quantity();

// Test A: Reconcile by parent ID
$res_q_parent = \DeSpace\Inventory\Audit\InventoryReconciler::reconcile( $var_parent_q_id );

// Test B: Reconcile by variation 1 ID (M4-REV-001 normalization)
$res_q_var1 = \DeSpace\Inventory\Audit\InventoryReconciler::reconcile( $var_parent_q_id, $var_q1_id );

// Test C: Reconcile by variation 2 ID
$res_q_var2 = \DeSpace\Inventory\Audit\InventoryReconciler::reconcile( $var_parent_q_id, $var_q2_id );

// Test D: Reconcile with non-existent IDs
$res_q_missing_parent = \DeSpace\Inventory\Audit\InventoryReconciler::reconcile( 99999999 );
$res_q_missing_var    = \DeSpace\Inventory\Audit\InventoryReconciler::reconcile( $var_parent_q_id, 99999999 );

$case_q_ok = ( 20.0 === $parent_q_stock )
	&& ( 'reconciled' === $res_q_parent['reconciliation_status'] )
	&& ( true === $res_q_parent['is_reconciled'] )
	&& ( 20.0 === (float) $res_q_parent['current_wc_stock'] )
	&& ( 20.0 === (float) $res_q_parent['expected_stock'] )
	&& ( -10.0 === (float) $res_q_parent['total_logged_delta'] )
	&& ( 'parent_managed_shared' === $res_q_parent['stock_scope'] )
	&& ( 'reconciled' === $res_q_var1['reconciliation_status'] )
	&& ( true === $res_q_var1['is_reconciled'] )
	&& ( 'parent_managed_shared' === $res_q_var1['stock_scope'] )
	&& ( (int) $res_q_var1['stock_managed_by_id'] === (int) $var_parent_q_id )
	&& ( 20.0 === (float) $res_q_var1['current_wc_stock'] )
	&& ( 'reconciled' === $res_q_var2['reconciliation_status'] )
	&& ( true === $res_q_var2['is_reconciled'] )
	&& ( 'product_not_found' === $res_q_missing_parent['reconciliation_status'] )
	&& ( false === $res_q_missing_parent['is_reconciled'] )
	&& ( 'product_not_found' === $res_q_missing_var['reconciliation_status'] )
	&& ( false === $res_q_missing_var['is_reconciled'] );

record_test( 'Q', 'Parent-managed variation reconciliation & shared pool normalization (M4-REV-001)', $case_q_ok );

// -------------------------------------------------------------------------
// Case R: Reconciliation History Baseline & Status Validation (M4-REV-004)
// -------------------------------------------------------------------------
$prod_r1 = new \WC_Product_Simple();
$prod_r1->set_name( 'Case R1 No Log ' . time() );
$prod_r1->set_manage_stock( true );
$prod_r1->set_stock_quantity( 10 );
$prod_r1_id = $prod_r1->save();
$wpdb->delete( $log_table, array( 'product_id' => $prod_r1_id ) );

$res_r1 = \DeSpace\Inventory\Audit\InventoryReconciler::reconcile( $prod_r1_id );

$prod_r2 = new \WC_Product_Simple();
$prod_r2->set_name( 'Case R2 Zero Stock No Log ' . time() );
$prod_r2->set_manage_stock( true );
$prod_r2->set_stock_quantity( 0 );
$prod_r2_id = $prod_r2->save();
$wpdb->delete( $log_table, array( 'product_id' => $prod_r2_id ) );

$res_r2 = \DeSpace\Inventory\Audit\InventoryReconciler::reconcile( $prod_r2_id );

$prod_r3 = new \WC_Product_Simple();
$prod_r3->set_name( 'Case R3 Valid Baseline ' . time() );
$prod_r3->set_manage_stock( true );
$prod_r3->set_stock_quantity( 15 );
$prod_r3_id = $prod_r3->save();
$prod_r3_reloaded = wc_get_product( $prod_r3_id );
$prod_r3_reloaded->set_stock_quantity( 11 );
$prod_r3_reloaded->save();

$res_r3 = \DeSpace\Inventory\Audit\InventoryReconciler::reconcile( $prod_r3_id );

// Subtest R4: Actual discrepancy detection (expected stock != current WC stock)
$prod_r4 = new \WC_Product_Simple();
$prod_r4->set_name( 'Case R4 Discrepancy ' . time() );
$prod_r4->set_manage_stock( true );
$prod_r4->set_stock_quantity( 20 );
$prod_r4_id = $prod_r4->save();

$wpdb->delete( $log_table, array( 'product_id' => $prod_r4_id ) );
$ev_r4 = new \DeSpace\Inventory\Events\InventoryEvent( array(
	'event_type'      => 'order_reduction',
	'event_source'    => 'order',
	'product_id'      => $prod_r4_id,
	'order_id'        => 99999,
	'quantity_before' => 20.0,
	'quantity_delta'  => -5.0,
	'stock_after'     => 15.0,
) );
$ev_r4->build_event_key( 'reconcile_disc_test_' . time() );
\DeSpace\Inventory\Events\InventoryEventRecorder::record( $ev_r4 );

// Tamper WC stock directly to 12 via postmeta (simulating unlogged stock movement)
update_post_meta( $prod_r4_id, '_stock', 12 );
wp_cache_delete( $prod_r4_id, 'post_meta' );
clean_post_cache( $prod_r4_id );

$res_r4 = \DeSpace\Inventory\Audit\InventoryReconciler::reconcile( $prod_r4_id );

$case_r_ok = ( false === $res_r1['is_reconciled'] )
	&& ( 'insufficient_history' === $res_r1['reconciliation_status'] )
	&& ( null === $res_r1['discrepancy'] )
	&& ( false === $res_r2['is_reconciled'] )
	&& ( 'insufficient_history' === $res_r2['reconciliation_status'] )
	&& ( null === $res_r2['discrepancy'] )
	&& ( true === $res_r3['is_reconciled'] )
	&& ( 'reconciled' === $res_r3['reconciliation_status'] )
	&& ( 0.0 === (float) $res_r3['discrepancy'] )
	&& ( 11.0 === (float) $res_r3['expected_stock'] )
	&& ( false === $res_r4['is_reconciled'] )
	&& ( 'discrepancy' === $res_r4['reconciliation_status'] )
	&& ( 12.0 === (float) $res_r4['current_wc_stock'] )
	&& ( 15.0 === (float) $res_r4['expected_stock'] )
	&& ( 3.0 === (float) $res_r4['discrepancy'] );

record_test( 'R', 'Reconciliation baseline safety, zero-history handling & real discrepancy (M4-REV-004)', $case_r_ok );

// -------------------------------------------------------------------------
// Case S: Admin CSV Export End-to-End Request Lifecycle & Streaming Verification (M4-REV-002)
// -------------------------------------------------------------------------
// 1. Hook registration in admin request flow
$GLOBALS['menu'] = array();
$GLOBALS['submenu'] = array();
\DeSpace\Inventory\Admin\InventoryLogsPage::init();
do_action( 'admin_menu' );
$s_hook_registered = has_action( 'admin_init', array( 'DeSpace\\Inventory\\Admin\\InventoryLogsPage', 'handle_export' ) );

// 2. Missing nonce validation check (Admin user)
wp_set_current_user( 1 );
$req_missing_nonce = array(
	'page'   => 'despace-inventory-logs',
	'action' => 'export_csv',
);
$res_missing_nonce = \DeSpace\Inventory\Admin\InventoryLogsPage::validate_export_request( $req_missing_nonce );
$s_missing_nonce_ok = is_wp_error( $res_missing_nonce ) && 'invalid_nonce' === $res_missing_nonce->get_error_code();

// 3. Invalid/tampered nonce validation check (Admin user)
$req_invalid_nonce = array(
	'page'     => 'despace-inventory-logs',
	'action'   => 'export_csv',
	'_wpnonce' => 'tampered_nonce_999',
);
$res_invalid_nonce = \DeSpace\Inventory\Admin\InventoryLogsPage::validate_export_request( $req_invalid_nonce );
$s_invalid_nonce_ok = is_wp_error( $res_invalid_nonce ) && 'invalid_nonce' === $res_invalid_nonce->get_error_code();

// 4. Valid nonce but unauthorized user (Guest user 0 with guest nonce)
wp_set_current_user( 0 );
$guest_nonce = wp_create_nonce( 'despace_export_csv' );
$req_guest = array(
	'page'       => 'despace-inventory-logs',
	'action'     => 'export_csv',
	'_wpnonce'   => $guest_nonce,
	'event_type' => 'order_reduction',
	'product_id' => $prod_a_id,
);
$res_guest = \DeSpace\Inventory\Admin\InventoryLogsPage::validate_export_request( $req_guest );
$s_guest_blocked_ok = is_wp_error( $res_guest ) && 'permission_denied' === $res_guest->get_error_code();

// 5. Valid nonce and authorized user (Admin user 1 with admin nonce)
wp_set_current_user( 1 );
$admin_nonce = wp_create_nonce( 'despace_export_csv' );
$req_admin = array(
	'page'       => 'despace-inventory-logs',
	'action'     => 'export_csv',
	'_wpnonce'   => $admin_nonce,
	'event_type' => 'order_reduction',
	'product_id' => $prod_a_id,
);
$res_admin = \DeSpace\Inventory\Admin\InventoryLogsPage::validate_export_request( $req_admin );
$s_admin_allowed_ok = ( true === $res_admin );

// 6. Export URL with exact nonce and preserved query parameters
$test_export_base = admin_url( 'admin.php?page=despace-inventory-logs&action=export_csv&event_type=order_reduction&product_id=' . $prod_a_id );
$test_nonce_url   = wp_nonce_url( $test_export_base, 'despace_export_csv' );
parse_str( (string) wp_parse_url( html_entity_decode( $test_nonce_url ), PHP_URL_QUERY ), $url_query_params );
$s_url_ok = ! empty( $url_query_params['_wpnonce'] )
	&& wp_verify_nonce( $url_query_params['_wpnonce'], 'despace_export_csv' )
	&& ( 'order_reduction' === ( $url_query_params['event_type'] ?? '' ) )
	&& ( (int) $url_query_params['product_id'] === (int) $prod_a_id );

// 7. End-to-End CSV output generation: verify UTF-8 BOM, headers, and filtered rows
$csv_memory_stream = fopen( 'php://memory', 'r+' );
\DeSpace\Inventory\Admin\InventoryExportService::export_csv( array(
	'event_type' => 'order_reduction',
	'product_id' => $prod_a_id,
), $csv_memory_stream );
rewind( $csv_memory_stream );
$csv_content = stream_get_contents( $csv_memory_stream );
fclose( $csv_memory_stream );

$s_bom_ok    = ( 0 === strpos( $csv_content, "\xEF\xBB\xBF" ) );
$s_header_ok = ( false !== strpos( $csv_content, 'Event Type' ) ) && ( false !== strpos( $csv_content, 'Product ID' ) );
$s_row_ok    = ( false !== strpos( $csv_content, (string) $prod_a_id ) ) && ( false !== strpos( $csv_content, 'order_reduction' ) );

$case_s_ok = (bool) $s_hook_registered
	&& $s_missing_nonce_ok
	&& $s_invalid_nonce_ok
	&& $s_guest_blocked_ok
	&& $s_admin_allowed_ok
	&& $s_url_ok
	&& $s_bom_ok
	&& $s_header_ok
	&& $s_row_ok;

record_test( 'S', 'Admin CSV export end-to-end request lifecycle & streaming verification (M4-REV-002)', $case_s_ok );

// -------------------------------------------------------------------------
// Case T: Real Action Scheduler Lifecycle, Shutdown & CLI Exception Isolation (M4-REV-003)
// -------------------------------------------------------------------------
$as_after_ok  = false;
$as_failed_ok = false;

if ( class_exists( 'ActionScheduler' ) && function_exists( 'as_enqueue_async_action' ) ) {
	// Subtest T1: Real Action Scheduler success lifecycle
	$hook_t1 = 'despace_test_as_success_' . time();
	add_action( $hook_t1, function() {
		\DeSpace\Inventory\Events\InventoryOrderContext::set( array( 'order_id' => 777111 ) );
	} );
	$as_action_id1 = as_enqueue_async_action( $hook_t1 );
	\ActionScheduler::runner()->process_action( $as_action_id1 );
	$as_after_ok = ! \DeSpace\Inventory\Events\InventoryOrderContext::has_order_context();
	\ActionScheduler::store()->delete_action( $as_action_id1 );

	// Subtest T2: Real Action Scheduler exception/failure lifecycle
	$hook_t2 = 'despace_test_as_fail_' . time();
	add_action( $hook_t2, function() {
		\DeSpace\Inventory\Events\InventoryOrderContext::set( array( 'order_id' => 777222 ) );
		throw new \RuntimeException( 'Simulated action failure' );
	} );
	$as_action_id2 = as_enqueue_async_action( $hook_t2 );
	try {
		\ActionScheduler::runner()->process_action( $as_action_id2 );
	} catch ( \Throwable $e ) {
		// ActionScheduler caught and triggered action_scheduler_failed_execution
	}
	$as_failed_ok = ! \DeSpace\Inventory\Events\InventoryOrderContext::has_order_context();
	\ActionScheduler::store()->delete_action( $as_action_id2 );
} else {
	// Fallback to simulated hooks if ActionScheduler class not present
	\DeSpace\Inventory\Events\InventoryOrderContext::set( array( 'order_id' => 777777 ) );
	do_action( 'action_scheduler_after_execute', 991, null, null );
	$as_after_ok = ! \DeSpace\Inventory\Events\InventoryOrderContext::has_order_context();

	\DeSpace\Inventory\Events\InventoryOrderContext::set( array( 'order_id' => 777778 ) );
	do_action( 'action_scheduler_failed_execution', 992, new \RuntimeException( 'Simulated action failure' ), null );
	$as_failed_ok = ! \DeSpace\Inventory\Events\InventoryOrderContext::has_order_context();
}

// Subtest T3: Shutdown hook cleanup
\DeSpace\Inventory\Events\InventoryOrderContext::set( array( 'order_id' => 777779 ) );
do_action( 'shutdown' );
$shutdown_clean_ok = ! \DeSpace\Inventory\Events\InventoryOrderContext::has_order_context();

// Subtest T4: CLI / Batch script try / finally pattern with subsequent stock mutation isolation
$prod_t = new \WC_Product_Simple();
$prod_t->set_name( 'Case T Batch Product ' . time() );
$prod_t->set_manage_stock( true );
$prod_t->set_stock_quantity( 10 );
$prod_t_id = $prod_t->save();

try {
	\DeSpace\Inventory\Events\InventoryOrderContext::set( array( 'order_id' => 777780 ) );
	throw new \RuntimeException( 'Simulated batch step failure' );
} catch ( \Exception $e ) {
	// Caught
} finally {
	\DeSpace\Inventory\Events\InventoryStockObserver::clear_all_context();
}
$batch_finally_ok = ! \DeSpace\Inventory\Events\InventoryOrderContext::has_order_context();

// Verify subsequent stock mutation in same PHP process is cleanly isolated
$prod_t->set_stock_quantity( 14 );
$prod_t->save();
$log_t = $wpdb->get_row( $wpdb->prepare(
	"SELECT * FROM {$log_table} WHERE product_id = %d ORDER BY id DESC LIMIT 1",
	$prod_t_id
), ARRAY_A );

$subsequent_isolated_ok = ! empty( $log_t )
	&& empty( $log_t['order_id'] )
	&& empty( $log_t['order_item_id'] )
	&& ( 'manual_adjustment' === $log_t['event_type'] );

$case_t_ok = $as_after_ok && $as_failed_ok && $shutdown_clean_ok && $batch_finally_ok && $subsequent_isolated_ok;
record_test( 'T', 'Real Action Scheduler lifecycle, shutdown & CLI exception isolation (M4-REV-003)', $case_t_ok );

// -------------------------------------------------------------------------
// Case U-1: Late Denial Priority Boundary at Priority 1000000 with PHP_INT_MAX Guard
// -------------------------------------------------------------------------
$prod_u1 = new \WC_Product_Simple();
$prod_u1->set_name( 'Case U1 Late Boundary ' . time() );
$prod_u1->set_manage_stock( true );
$prod_u1->set_stock_quantity( 20 );
$prod_u1_id = $prod_u1->save();

$order_u1 = wc_create_order();
$order_u1->add_product( $prod_u1, 5 );
$order_u1->calculate_totals();
$order_u1->save();
$order_u1_id = $order_u1->get_id();

$ultra_late_filter = function( $can, $order ) {
	return false;
};
add_filter( 'woocommerce_can_reduce_order_stock', $ultra_late_filter, 1000000, 2 );

wc_reduce_stock_levels( $order_u1 );

remove_filter( 'woocommerce_can_reduce_order_stock', $ultra_late_filter, 1000000 );

$stock_u1_after = (float) wc_get_product( $prod_u1_id )->get_stock_quantity();
$u1_logs_count = (int) $wpdb->get_var( $wpdb->prepare(
	"SELECT COUNT(*) FROM {$log_table} WHERE order_id = %d AND event_type = %s",
	$order_u1_id,
	'order_reduction'
) );
$u1_context_clean = ! \DeSpace\Inventory\Events\InventoryOrderContext::has_order_context();

$case_u1_ok = ( 20.0 === $stock_u1_after )
	&& ( 0 === $u1_logs_count )
	&& $u1_context_clean;

record_test( 'U-1', 'Late denial boundary at priority 1000000 and PHP_INT_MAX cleanup guard', $case_u1_ok );

// -------------------------------------------------------------------------
// Case U-2: Same-Priority Boundary Verification at PHP_INT_MAX
// -------------------------------------------------------------------------
$prod_u2 = new \WC_Product_Simple();
$prod_u2->set_name( 'Case U2 Same-Priority Boundary ' . time() );
$prod_u2->set_manage_stock( true );
$prod_u2->set_stock_quantity( 20 );
$prod_u2_id = $prod_u2->save();

$order_u2 = wc_create_order();
$order_u2->add_product( $prod_u2, 5 );
$order_u2->calculate_totals();
$order_u2->save();
$order_u2_id = $order_u2->get_id();

// Register external filter at PHP_INT_MAX returning false AFTER M4 was loaded
$external_same_priority_filter = function( $can, $order ) {
	return false;
};
add_filter( 'woocommerce_can_reduce_order_stock', $external_same_priority_filter, PHP_INT_MAX, 2 );

// Execute reduction: external filter denies it
wc_reduce_stock_levels( $order_u2 );
remove_filter( 'woocommerce_can_reduce_order_stock', $external_same_priority_filter, PHP_INT_MAX );

$stock_u2_after = (float) wc_get_product( $prod_u2_id )->get_stock_quantity();
$u2_logs_count  = (int) $wpdb->get_var( $wpdb->prepare(
	"SELECT COUNT(*) FROM {$log_table} WHERE order_id = %d AND event_type = %s",
	$order_u2_id,
	'order_reduction'
) );

// Verify: WooCommerce stock was NOT reduced (stock unchanged = 20.0, zero order logs)
$u2_wc_aborted_ok = ( 20.0 === $stock_u2_after ) && ( 0 === $u2_logs_count );

// Inspect same-priority execution order:
// In WordPress, callbacks at the same priority execute in FIFO order of addition.
// Because the external filter was added after M4, M4's guard ran first when $can was true.
// Thus, in-memory context remained open.
$u2_context_lingered = \DeSpace\Inventory\Events\InventoryOrderContext::has_order_context();

// Test subsequent mutation for an UNRELATED product:
$prod_unrelated = new \WC_Product_Simple();
$prod_unrelated->set_name( 'Case U2 Unrelated ' . time() );
$prod_unrelated->set_manage_stock( true );
$prod_unrelated->set_stock_quantity( 10 );
$prod_unrelated_id = $prod_unrelated->save();

$prod_unrelated->set_stock_quantity( 14 );
$prod_unrelated->save();

$log_unrelated = $wpdb->get_row( $wpdb->prepare(
	"SELECT * FROM {$log_table} WHERE product_id = %d ORDER BY id DESC LIMIT 1",
	$prod_unrelated_id
), ARRAY_A );

// Case P safety mechanism: unrelated entity has empty queue, so M4 cleanly isolates as manual_adjustment!
$unrelated_isolated_ok = ! empty( $log_unrelated )
	&& empty( $log_unrelated['order_id'] )
	&& empty( $log_unrelated['order_item_id'] )
	&& ( 'manual_adjustment' === $log_unrelated['event_type'] );

// Clean up context explicitly
\DeSpace\Inventory\Events\InventoryStockObserver::clear_all_context();
$u2_finally_clean = ! \DeSpace\Inventory\Events\InventoryOrderContext::has_order_context();

$case_u2_ok = $u2_wc_aborted_ok && $u2_context_lingered && $unrelated_isolated_ok && $u2_finally_clean;
record_test( 'U-2', 'Same-priority PHP_INT_MAX boundary analysis & FIFO isolation verification', $case_u2_ok );

// -------------------------------------------------------------------------
// Case U-3: Same Product Manual Mutation After Denied Reduction at PHP_INT_MAX (M4-REV-009)
// -------------------------------------------------------------------------
$prod_u3 = new \WC_Product_Simple();
$prod_u3->set_name( 'Case U3 Same-Product Reduction ' . time() );
$prod_u3->set_manage_stock( true );
$prod_u3->set_stock_quantity( 20 );
$prod_u3_id = $prod_u3->save();

$order_u3 = wc_create_order();
$order_u3->add_product( $prod_u3, 5 );
$order_u3->calculate_totals();
$order_u3->save();
$order_u3_id = $order_u3->get_id();

// External filter at PHP_INT_MAX returns false after M4 registered guard
$external_filter_u3 = function( $can, $order ) {
	return false;
};
add_filter( 'woocommerce_can_reduce_order_stock', $external_filter_u3, PHP_INT_MAX, 2 );

// Attempt reduction: external filter denies it
wc_reduce_stock_levels( $order_u3 );
remove_filter( 'woocommerce_can_reduce_order_stock', $external_filter_u3, PHP_INT_MAX );

$stock_u3_after_abort = (float) wc_get_product( $prod_u3_id )->get_stock_quantity();
$u3_reduction_logs    = (int) $wpdb->get_var( $wpdb->prepare(
	"SELECT COUNT(*) FROM {$log_table} WHERE order_id = %d AND event_type = %s",
	$order_u3_id,
	'order_reduction'
) );

// Verify reduction was aborted by WooCommerce
$u3_aborted_ok = ( 20.0 === $stock_u3_after_abort ) && ( 0 === $u3_reduction_logs );

// Context initially lingered due to same-priority addition order
$u3_lingered_ok = \DeSpace\Inventory\Events\InventoryOrderContext::has_order_context();

// Now perform a manual mutation ON THE SAME PRODUCT THAT WAS IN THE DENIED ORDER!
$prod_u3->set_stock_quantity( 14 );
$prod_u3->save();

$log_u3 = $wpdb->get_row( $wpdb->prepare(
	"SELECT * FROM {$log_table} WHERE product_id = %d ORDER BY id DESC LIMIT 1",
	$prod_u3_id
), ARRAY_A );

// Verify: Active caller check detected that mutation was NOT from wc_reduce_stock_levels.
// Correctly classified as manual_adjustment, order_id is null, order_item_id is null!
$u3_isolated_ok = ! empty( $log_u3 )
	&& empty( $log_u3['order_id'] )
	&& empty( $log_u3['order_item_id'] )
	&& ( 'manual_adjustment' === $log_u3['event_type'] )
	&& ( -6.0 === (float) $log_u3['quantity_delta'] )
	&& ( 14.0 === (float) $log_u3['stock_after'] );

// Verify context is now completely cleaned
$u3_clean_ok = ! \DeSpace\Inventory\Events\InventoryOrderContext::has_order_context()
	&& ! \DeSpace\Inventory\Events\InventoryOrderContext::has_queued_items();

$case_u3_ok = $u3_aborted_ok && $u3_lingered_ok && $u3_isolated_ok && $u3_clean_ok;
record_test( 'U-3', 'Same-product manual mutation after denied reduction at PHP_INT_MAX (M4-REV-009)', $case_u3_ok );

// -------------------------------------------------------------------------
// Case U-4: Same Product Manual Mutation After Denied Restoration at PHP_INT_MAX (M4-REV-009)
// -------------------------------------------------------------------------
$prod_u4 = new \WC_Product_Simple();
$prod_u4->set_name( 'Case U4 Same-Product Restoration ' . time() );
$prod_u4->set_manage_stock( true );
$prod_u4->set_stock_quantity( 20 );
$prod_u4_id = $prod_u4->save();

$order_u4 = wc_create_order();
$order_u4->add_product( $prod_u4, 5 );
$order_u4->calculate_totals();
$order_u4->save();
$order_u4_id = $order_u4->get_id();

// Legitimate reduction first (20 -> 15)
wc_reduce_stock_levels( $order_u4 );
$stock_u4_reduced = (float) wc_get_product( $prod_u4_id )->get_stock_quantity();

// External filter at PHP_INT_MAX denies restoration
$external_filter_u4 = function( $can, $order ) {
	return false;
};
add_filter( 'woocommerce_can_restore_order_stock', $external_filter_u4, PHP_INT_MAX, 2 );

// Attempt restoration: external filter denies it
wc_increase_stock_levels( $order_u4 );
remove_filter( 'woocommerce_can_restore_order_stock', $external_filter_u4, PHP_INT_MAX );

$stock_u4_after_abort = (float) wc_get_product( $prod_u4_id )->get_stock_quantity();
$u4_restore_logs      = (int) $wpdb->get_var( $wpdb->prepare(
	"SELECT COUNT(*) FROM {$log_table} WHERE order_id = %d AND event_type = %s",
	$order_u4_id,
	'stock_restoration'
) );

// Verify restoration was aborted by WooCommerce (stock remains 15.0, zero restoration logs)
$u4_aborted_ok = ( 15.0 === $stock_u4_reduced )
	&& ( 15.0 === $stock_u4_after_abort )
	&& ( 0 === $u4_restore_logs );

// Context initially lingered due to same-priority addition order
$u4_lingered_ok = \DeSpace\Inventory\Events\InventoryOrderContext::has_order_context();

// Now perform a manual mutation ON THE SAME PRODUCT THAT WAS IN THE DENIED ORDER!
$prod_u4 = wc_get_product( $prod_u4_id );
$prod_u4->set_stock_quantity( 18 );
$prod_u4->save();

$log_u4 = $wpdb->get_row( $wpdb->prepare(
	"SELECT * FROM {$log_table} WHERE product_id = %d ORDER BY id DESC LIMIT 1",
	$prod_u4_id
), ARRAY_A );

// Verify: Active caller check detected that mutation was NOT from wc_increase_stock_levels.
// Correctly classified as manual_adjustment, order_id is null, order_item_id is null!
$u4_isolated_ok = ! empty( $log_u4 )
	&& empty( $log_u4['order_id'] )
	&& empty( $log_u4['order_item_id'] )
	&& ( 'manual_adjustment' === $log_u4['event_type'] )
	&& ( 3.0 === (float) $log_u4['quantity_delta'] )
	&& ( 18.0 === (float) $log_u4['stock_after'] );

// Verify context is now completely cleaned
$u4_clean_ok = ! \DeSpace\Inventory\Events\InventoryOrderContext::has_order_context()
	&& ! \DeSpace\Inventory\Events\InventoryOrderContext::has_queued_items();

$case_u4_ok = $u4_aborted_ok && $u4_lingered_ok && $u4_isolated_ok && $u4_clean_ok;
record_test( 'U-4', 'Same-product manual mutation after denied restoration at PHP_INT_MAX (M4-REV-009)', $case_u4_ok );

// -------------------------------------------------------------------------
// Case U-5: Same Product Manual Mutation After Denied Refund Restock at PHP_INT_MAX (M4-REV-009)
// -------------------------------------------------------------------------
$prod_u5 = new \WC_Product_Simple();
$prod_u5->set_name( 'Case U5 Same-Product Refund ' . time() );
$prod_u5->set_regular_price( '10.00' );
$prod_u5->set_manage_stock( true );
$prod_u5->set_stock_quantity( 20 );
$prod_u5_id = $prod_u5->save();

$order_u5 = wc_create_order();
$item_u5_id = $order_u5->add_product( $prod_u5, 5 );
$order_u5->calculate_totals();
$order_u5->save();
$order_u5_id = $order_u5->get_id();

// Legitimate reduction first (20 -> 15)
wc_reduce_stock_levels( $order_u5 );
$order_u5 = wc_get_order( $order_u5_id ); // Reload order with _reduced_stock meta

// Create real refund entity without auto-restocking items
$refund_u5 = wc_create_refund( array(
	'amount'         => 20.00,
	'reason'         => 'Test refund U5',
	'order_id'       => $order_u5_id,
	'line_items'     => array(
		$item_u5_id => array(
			'qty'          => 2,
			'refund_total' => 20.00,
			'refund_tax'   => array(),
		),
	),
	'restock_items'  => false,
) );
$refund_u5_id = ( $refund_u5 instanceof \WC_Order_Refund ) ? $refund_u5->get_id() : 0;

// External filter at PHP_INT_MAX denies refund restock
$external_filter_u5 = function( $can, $order, $items ) {
	return false;
};
add_filter( 'woocommerce_can_restock_refunded_items', $external_filter_u5, PHP_INT_MAX, 3 );

// Attempt refund restock: external filter denies it
wc_restock_refunded_items( $order_u5, array(
	$item_u5_id => array(
		'qty'          => 2,
		'refund_total' => 20.00,
		'refund_tax'   => array(),
	),
) );
remove_filter( 'woocommerce_can_restock_refunded_items', $external_filter_u5, PHP_INT_MAX );

$stock_u5_after_abort = (float) wc_get_product( $prod_u5_id )->get_stock_quantity();
$u5_refund_logs       = (int) $wpdb->get_var( $wpdb->prepare(
	"SELECT COUNT(*) FROM {$log_table} WHERE order_id = %d AND event_type = %s",
	$order_u5_id,
	'refund_restock'
) );

// Verify refund restock was aborted by WooCommerce (stock remains 15.0, zero refund_restock logs)
$u5_aborted_ok = ( 15.0 === $stock_u5_after_abort ) && ( 0 === $u5_refund_logs ) && ( $refund_u5_id > 0 );

// Verify real refund_id existed in context before manual adjustment (strict equality, no fallback)
$u5_context_before = \DeSpace\Inventory\Events\InventoryOrderContext::get();
$u5_lingered_ok    = ( $refund_u5_id > 0 ) && ( (int) ( $u5_context_before['refund_id'] ?? 0 ) === $refund_u5_id );

// Now perform a manual mutation ON THE SAME PRODUCT THAT WAS IN THE DENIED REFUND!
$prod_u5 = wc_get_product( $prod_u5_id );
$prod_u5->set_stock_quantity( 17 );
$prod_u5->save();

$log_u5 = $wpdb->get_row( $wpdb->prepare(
	"SELECT * FROM {$log_table} WHERE product_id = %d ORDER BY id DESC LIMIT 1",
	$prod_u5_id
), ARRAY_A );

// Verify: Active caller check detected that mutation was NOT from wc_restock_refunded_items.
// Correctly classified as manual_adjustment, and all 3 IDs (order_id, refund_id, order_item_id) are strictly null!
$u5_isolated_ok = ! empty( $log_u5 )
	&& is_null( $log_u5['refund_id'] )
	&& is_null( $log_u5['order_id'] )
	&& is_null( $log_u5['order_item_id'] )
	&& ( 'manual_adjustment' === $log_u5['event_type'] )
	&& ( 2.0 === (float) $log_u5['quantity_delta'] )
	&& ( 17.0 === (float) $log_u5['stock_after'] );

// Verify context is now completely cleaned
$u5_clean_ok = ! \DeSpace\Inventory\Events\InventoryOrderContext::has_refund_context()
	&& ! \DeSpace\Inventory\Events\InventoryOrderContext::has_order_context()
	&& ! \DeSpace\Inventory\Events\InventoryOrderContext::has_queued_items();

$case_u5_ok = $u5_aborted_ok && $u5_lingered_ok && $u5_isolated_ok && $u5_clean_ok;
record_test( 'U-5', 'Same-product manual mutation after denied refund restock with real refund_id at PHP_INT_MAX (M4-REV-009)', $case_u5_ok );

// -------------------------------------------------------------------------
// Case U-6: Nested Stock Mutation During Active Order Reduction (M4-REV-010)
// -------------------------------------------------------------------------
// Scenario: A multi-item order (Product A and Product B).
// An external plugin hooks into woocommerce_product_set_stock during Product A reduction
// and mutates Product B stock.
// Requirements:
// 1. Nested mutation on B must be logged as manual_adjustment with order_id=null, order_item_id=null.
// 2. Active order context and FIFO queue must NOT be destroyed.
// 3. Subsequent reduction of Product B must retain correct order_id and order_item_id.
$prod_u6a = new \WC_Product_Simple();
$prod_u6a->set_name( 'Case U6A ' . time() );
$prod_u6a->set_regular_price( '10.00' );
$prod_u6a->set_manage_stock( true );
$prod_u6a->set_stock_quantity( 20 );
$prod_u6a_id = $prod_u6a->save();

$prod_u6b = new \WC_Product_Simple();
$prod_u6b->set_name( 'Case U6B ' . time() );
$prod_u6b->set_regular_price( '15.00' );
$prod_u6b->set_manage_stock( true );
$prod_u6b->set_stock_quantity( 20 );
$prod_u6b_id = $prod_u6b->save();

$order_u6 = wc_create_order();
$item_u6a_id = $order_u6->add_product( $prod_u6a, 2 );
$item_u6b_id = $order_u6->add_product( $prod_u6b, 3 );
$order_u6->calculate_totals();
$order_u6->save();
$order_u6_id = $order_u6->get_id();

$u6_nested_executed = false;
$u6_hook = function( $product ) use ( &$u6_nested_executed, $prod_u6a_id, $prod_u6b_id ) {
	if ( ! $u6_nested_executed && $product->get_id() === $prod_u6a_id ) {
		$u6_nested_executed = true;
		// Nested manual mutation on Product B during Product A order reduction
		$prod_b = wc_get_product( $prod_u6b_id );
		$prod_b->set_stock_quantity( 25 );
		$prod_b->save();
	}
};
add_action( 'woocommerce_product_set_stock', $u6_hook, 10, 1 );

wc_reduce_stock_levels( $order_u6 );
remove_action( 'woocommerce_product_set_stock', $u6_hook, 10 );

// Verify logs for Product A
$logs_u6a = $wpdb->get_results( $wpdb->prepare(
	"SELECT * FROM {$log_table} WHERE product_id = %d AND order_id = %d ORDER BY id ASC",
	$prod_u6a_id,
	$order_u6_id
), ARRAY_A );

$u6a_reduction_ok = ( 1 === count( $logs_u6a ) )
	&& ( 'order_reduction' === $logs_u6a[0]['event_type'] )
	&& ( (int) $logs_u6a[0]['order_item_id'] === $item_u6a_id )
	&& ( -2.0 === (float) $logs_u6a[0]['quantity_delta'] )
	&& ( 18.0 === (float) $logs_u6a[0]['stock_after'] );

// Verify logs for Product B (nested manual adjustment + legitimate order reduction)
$logs_u6b_nested = $wpdb->get_row( $wpdb->prepare(
	"SELECT * FROM {$log_table} WHERE product_id = %d AND event_type = %s ORDER BY id DESC LIMIT 1",
	$prod_u6b_id,
	'manual_adjustment'
), ARRAY_A );

$u6b_nested_ok = ! empty( $logs_u6b_nested )
	&& empty( $logs_u6b_nested['order_id'] )
	&& empty( $logs_u6b_nested['order_item_id'] )
	&& empty( $logs_u6b_nested['refund_id'] )
	&& ( 5.0 === (float) $logs_u6b_nested['quantity_delta'] )
	&& ( 25.0 === (float) $logs_u6b_nested['stock_after'] );

$logs_u6b_reduction = $wpdb->get_row( $wpdb->prepare(
	"SELECT * FROM {$log_table} WHERE product_id = %d AND order_id = %d AND event_type = %s ORDER BY id DESC LIMIT 1",
	$prod_u6b_id,
	$order_u6_id,
	'order_reduction'
), ARRAY_A );

$u6b_reduction_ok = ! empty( $logs_u6b_reduction )
	&& ( (int) $logs_u6b_reduction['order_item_id'] === $item_u6b_id )
	&& ( -3.0 === (float) $logs_u6b_reduction['quantity_delta'] )
	&& ( 22.0 === (float) $logs_u6b_reduction['stock_after'] );

$case_u6_ok = $u6_nested_executed && $u6a_reduction_ok && $u6b_nested_ok && $u6b_reduction_ok;
record_test( 'U-6', 'Nested stock mutation during active order reduction isolated without context disruption (M4-REV-010)', $case_u6_ok );

// -------------------------------------------------------------------------
// Case U-7: Nested Stock Mutation During Active Stock Restoration (M4-REV-010)
// -------------------------------------------------------------------------
// Scenario: A multi-item order (Product A and Product B).
// After reduction, order cancellation triggers wc_increase_stock_levels().
// An external plugin hooks into woocommerce_product_set_stock during Product A restoration
// and mutates Product B stock.
// Requirements:
// 1. Nested mutation on B must be isolated as manual_adjustment (order_id=null, order_item_id=null).
// 2. Product B FIFO queue entry must NOT be consumed or corrupted.
// 3. Product B restoration must succeed with correct order_id and order_item_id.
$prod_u7a = new \WC_Product_Simple();
$prod_u7a->set_name( 'Case U7A ' . time() );
$prod_u7a->set_regular_price( '10.00' );
$prod_u7a->set_manage_stock( true );
$prod_u7a->set_stock_quantity( 20 );
$prod_u7a_id = $prod_u7a->save();

$prod_u7b = new \WC_Product_Simple();
$prod_u7b->set_name( 'Case U7B ' . time() );
$prod_u7b->set_regular_price( '15.00' );
$prod_u7b->set_manage_stock( true );
$prod_u7b->set_stock_quantity( 20 );
$prod_u7b_id = $prod_u7b->save();

$order_u7 = wc_create_order();
$item_u7a_id = $order_u7->add_product( $prod_u7a, 2 );
$item_u7b_id = $order_u7->add_product( $prod_u7b, 3 );
$order_u7->calculate_totals();
$order_u7->save();
$order_u7_id = $order_u7->get_id();

wc_reduce_stock_levels( $order_u7 );
$order_u7 = wc_get_order( $order_u7_id );

$u7_nested_executed = false;
$u7_hook = function( $product ) use ( &$u7_nested_executed, $prod_u7a_id, $prod_u7b_id ) {
	if ( ! $u7_nested_executed && $product->get_id() === $prod_u7a_id ) {
		$u7_nested_executed = true;
		// Nested manual mutation on Product B during Product A restoration
		$prod_b = wc_get_product( $prod_u7b_id );
		$prod_b->set_stock_quantity( 15 );
		$prod_b->save();
	}
};
add_action( 'woocommerce_product_set_stock', $u7_hook, 10, 1 );

wc_increase_stock_levels( $order_u7 );
remove_action( 'woocommerce_product_set_stock', $u7_hook, 10 );

// Verify logs for Product A restoration
$log_u7a_restore = $wpdb->get_row( $wpdb->prepare(
	"SELECT * FROM {$log_table} WHERE product_id = %d AND order_id = %d AND event_type = %s ORDER BY id DESC LIMIT 1",
	$prod_u7a_id,
	$order_u7_id,
	'stock_restoration'
), ARRAY_A );

$u7a_restore_ok = ! empty( $log_u7a_restore )
	&& ( (int) $log_u7a_restore['order_item_id'] === $item_u7a_id )
	&& ( 2.0 === (float) $log_u7a_restore['quantity_delta'] )
	&& ( 20.0 === (float) $log_u7a_restore['stock_after'] );

// Verify nested manual mutation on Product B
$log_u7b_nested = $wpdb->get_row( $wpdb->prepare(
	"SELECT * FROM {$log_table} WHERE product_id = %d AND event_type = %s ORDER BY id DESC LIMIT 1",
	$prod_u7b_id,
	'manual_adjustment'
), ARRAY_A );

$u7b_nested_ok = ! empty( $log_u7b_nested )
	&& empty( $log_u7b_nested['order_id'] )
	&& empty( $log_u7b_nested['order_item_id'] )
	&& empty( $log_u7b_nested['refund_id'] )
	&& ( -2.0 === (float) $log_u7b_nested['quantity_delta'] )
	&& ( 15.0 === (float) $log_u7b_nested['stock_after'] );

// Verify legitimate restoration of Product B
$log_u7b_restore = $wpdb->get_row( $wpdb->prepare(
	"SELECT * FROM {$log_table} WHERE product_id = %d AND order_id = %d AND event_type = %s ORDER BY id DESC LIMIT 1",
	$prod_u7b_id,
	$order_u7_id,
	'stock_restoration'
), ARRAY_A );

$u7b_restore_ok = ! empty( $log_u7b_restore )
	&& ( (int) $log_u7b_restore['order_item_id'] === $item_u7b_id )
	&& ( 3.0 === (float) $log_u7b_restore['quantity_delta'] )
	&& ( 18.0 === (float) $log_u7b_restore['stock_after'] );

$case_u7_ok = $u7_nested_executed && $u7a_restore_ok && $u7b_nested_ok && $u7b_restore_ok;
record_test( 'U-7', 'Nested stock mutation during active restoration preserves FIFO queue and item attribution (M4-REV-010)', $case_u7_ok );

// -------------------------------------------------------------------------
// Case U-8: Nested Stock Mutation During Active Refund Restock (M4-REV-010)
// -------------------------------------------------------------------------
// Scenario: Multi-item order (Product A and Product B), reduced.
// Real refund created for both items.
// During wc_restock_refunded_items(), an external hook triggers manual mutation on Product B.
// Requirements:
// 1. Nested mutation on B must be isolated as manual_adjustment (order_id=null, refund_id=null, order_item_id=null).
// 2. Real refund restock for Product A and Product B must record exact refund_id, order_id, order_item_id.
$prod_u8a = new \WC_Product_Simple();
$prod_u8a->set_name( 'Case U8A ' . time() );
$prod_u8a->set_regular_price( '10.00' );
$prod_u8a->set_manage_stock( true );
$prod_u8a->set_stock_quantity( 20 );
$prod_u8a_id = $prod_u8a->save();

$prod_u8b = new \WC_Product_Simple();
$prod_u8b->set_name( 'Case U8B ' . time() );
$prod_u8b->set_regular_price( '15.00' );
$prod_u8b->set_manage_stock( true );
$prod_u8b->set_stock_quantity( 20 );
$prod_u8b_id = $prod_u8b->save();

$order_u8 = wc_create_order();
$item_u8a_id = $order_u8->add_product( $prod_u8a, 2 );
$item_u8b_id = $order_u8->add_product( $prod_u8b, 3 );
$order_u8->calculate_totals();
$order_u8->save();
$order_u8_id = $order_u8->get_id();

wc_reduce_stock_levels( $order_u8 );
$order_u8 = wc_get_order( $order_u8_id );

$refund_u8 = wc_create_refund( array(
	'amount'         => 35.00,
	'reason'         => 'Test refund U8',
	'order_id'       => $order_u8_id,
	'line_items'     => array(
		$item_u8a_id => array(
			'qty'          => 1,
			'refund_total' => 10.00,
			'refund_tax'   => array(),
		),
		$item_u8b_id => array(
			'qty'          => 2,
			'refund_total' => 25.00,
			'refund_tax'   => array(),
		),
	),
	'restock_items'  => false,
) );
$refund_u8_id = ( $refund_u8 instanceof \WC_Order_Refund ) ? $refund_u8->get_id() : 0;

$u8_nested_executed = false;
$u8_hook = function( $product ) use ( &$u8_nested_executed, $prod_u8a_id, $prod_u8b_id ) {
	if ( ! $u8_nested_executed && $product->get_id() === $prod_u8a_id ) {
		$u8_nested_executed = true;
		// Nested manual mutation on Product B during Product A refund restock
		$prod_b = wc_get_product( $prod_u8b_id );
		$prod_b->set_stock_quantity( 14 );
		$prod_b->save();
	}
};
add_action( 'woocommerce_product_set_stock', $u8_hook, 10, 1 );

wc_restock_refunded_items( $order_u8, array(
	$item_u8a_id => array(
		'qty'          => 1,
		'refund_total' => 10.00,
		'refund_tax'   => array(),
	),
	$item_u8b_id => array(
		'qty'          => 2,
		'refund_total' => 25.00,
		'refund_tax'   => array(),
	),
) );
remove_action( 'woocommerce_product_set_stock', $u8_hook, 10 );

// Verify Product A refund restock
$log_u8a = $wpdb->get_row( $wpdb->prepare(
	"SELECT * FROM {$log_table} WHERE product_id = %d AND order_id = %d AND event_type = %s ORDER BY id DESC LIMIT 1",
	$prod_u8a_id,
	$order_u8_id,
	'refund_restock'
), ARRAY_A );

$u8a_restock_ok = ! empty( $log_u8a )
	&& ( (int) $log_u8a['order_item_id'] === $item_u8a_id )
	&& ( (int) $log_u8a['refund_id'] === $refund_u8_id )
	&& ( 1.0 === (float) $log_u8a['quantity_delta'] )
	&& ( 19.0 === (float) $log_u8a['stock_after'] );

// Verify nested manual mutation on Product B
$log_u8b_nested = $wpdb->get_row( $wpdb->prepare(
	"SELECT * FROM {$log_table} WHERE product_id = %d AND event_type = %s ORDER BY id DESC LIMIT 1",
	$prod_u8b_id,
	'manual_adjustment'
), ARRAY_A );

$u8b_nested_ok = ! empty( $log_u8b_nested )
	&& empty( $log_u8b_nested['order_id'] )
	&& empty( $log_u8b_nested['order_item_id'] )
	&& empty( $log_u8b_nested['refund_id'] )
	&& ( -3.0 === (float) $log_u8b_nested['quantity_delta'] )
	&& ( 14.0 === (float) $log_u8b_nested['stock_after'] );

// Verify Product B refund restock
$log_u8b = $wpdb->get_row( $wpdb->prepare(
	"SELECT * FROM {$log_table} WHERE product_id = %d AND order_id = %d AND event_type = %s ORDER BY id DESC LIMIT 1",
	$prod_u8b_id,
	$order_u8_id,
	'refund_restock'
), ARRAY_A );

$u8b_restock_ok = ! empty( $log_u8b )
	&& ( (int) $log_u8b['order_item_id'] === $item_u8b_id )
	&& ( (int) $log_u8b['refund_id'] === $refund_u8_id )
	&& ( 2.0 === (float) $log_u8b['quantity_delta'] )
	&& ( 16.0 === (float) $log_u8b['stock_after'] );

$case_u8_ok = ( $refund_u8_id > 0 ) && $u8_nested_executed && $u8a_restock_ok && $u8b_nested_ok && $u8b_restock_ok;
record_test( 'U-8', 'Nested stock mutation during active refund restock preserves refund context and attribution (M4-REV-010)', $case_u8_ok );

// -------------------------------------------------------------------------
// Case U-9: Nested mutation via wc_update_product_stock() (M4-REV-011)
// -------------------------------------------------------------------------
// Scenario: An active WooCommerce reduction processes Product A.
// An intermediate callback directly calls wc_update_product_stock() to mutate Product B.
// Requirements:
// 1. Product B nested mutation must be recorded as manual_adjustment, order/refund/item IDs = null.
// 2. FIFO queue must NOT be dequeued or corrupted by Product B's nested mutation.
// 3. Legitimate reduction of Product A and Product B must record exact order and line-item IDs.
$prod_u9a = new \WC_Product_Simple();
$prod_u9a->set_name( 'Case U9A ' . time() );
$prod_u9a->set_regular_price( '10.00' );
$prod_u9a->set_manage_stock( true );
$prod_u9a->set_stock_quantity( 20 );
$prod_u9a_id = $prod_u9a->save();

$prod_u9b = new \WC_Product_Simple();
$prod_u9b->set_name( 'Case U9B ' . time() );
$prod_u9b->set_regular_price( '15.00' );
$prod_u9b->set_manage_stock( true );
$prod_u9b->set_stock_quantity( 20 );
$prod_u9b_id = $prod_u9b->save();

$order_u9 = wc_create_order();
$item_u9a_id = $order_u9->add_product( $prod_u9a, 2 );
$item_u9b_id = $order_u9->add_product( $prod_u9b, 3 );
$order_u9->calculate_totals();
$order_u9->save();
$order_u9_id = $order_u9->get_id();

$u9_nested_executed = false;
$u9_hook = function( $product ) use ( &$u9_nested_executed, $prod_u9a_id, $prod_u9b ) {
	if ( ! $u9_nested_executed && $product->get_id() === $prod_u9a_id ) {
		$u9_nested_executed = true;
		// Intermediate callback directly calls wc_update_product_stock() on Product B
		wc_update_product_stock( $prod_u9b, 26, 'set' );
	}
};
add_action( 'woocommerce_product_set_stock', $u9_hook, 10, 1 );

wc_reduce_stock_levels( $order_u9 );
remove_action( 'woocommerce_product_set_stock', $u9_hook, 10 );

// Verify Product A reduction
$log_u9a = $wpdb->get_row( $wpdb->prepare(
	"SELECT * FROM {$log_table} WHERE product_id = %d AND order_id = %d AND event_type = %s ORDER BY id DESC LIMIT 1",
	$prod_u9a_id,
	$order_u9_id,
	'order_reduction'
), ARRAY_A );

$u9a_reduction_ok = ! empty( $log_u9a )
	&& ( (int) $log_u9a['order_item_id'] === $item_u9a_id )
	&& ( -2.0 === (float) $log_u9a['quantity_delta'] )
	&& ( 18.0 === (float) $log_u9a['stock_after'] );

// Verify Product B nested mutation via wc_update_product_stock()
$log_u9b_nested = $wpdb->get_row( $wpdb->prepare(
	"SELECT * FROM {$log_table} WHERE product_id = %d AND event_type = %s ORDER BY id DESC LIMIT 1",
	$prod_u9b_id,
	'manual_adjustment'
), ARRAY_A );

$u9b_nested_ok = ! empty( $log_u9b_nested )
	&& is_null( $log_u9b_nested['order_id'] )
	&& is_null( $log_u9b_nested['order_item_id'] )
	&& is_null( $log_u9b_nested['refund_id'] )
	&& ( 6.0 === (float) $log_u9b_nested['quantity_delta'] )
	&& ( 26.0 === (float) $log_u9b_nested['stock_after'] );

// Verify legitimate Product B reduction
$log_u9b_reduction = $wpdb->get_row( $wpdb->prepare(
	"SELECT * FROM {$log_table} WHERE product_id = %d AND order_id = %d AND event_type = %s ORDER BY id DESC LIMIT 1",
	$prod_u9b_id,
	$order_u9_id,
	'order_reduction'
), ARRAY_A );

$u9b_reduction_ok = ! empty( $log_u9b_reduction )
	&& ( (int) $log_u9b_reduction['order_item_id'] === $item_u9b_id )
	&& ( -3.0 === (float) $log_u9b_reduction['quantity_delta'] )
	&& ( 23.0 === (float) $log_u9b_reduction['stock_after'] );

$case_u9_ok = $u9_nested_executed && $u9a_reduction_ok && $u9b_nested_ok && $u9b_reduction_ok;
record_test( 'U-9', 'Nested mutation via wc_update_product_stock() isolated without FIFO dequeue disruption (M4-REV-011)', $case_u9_ok );

// -------------------------------------------------------------------------
// Case U-10: Unrecognized mutation call path (M4-REV-011)
// -------------------------------------------------------------------------
// Scenario: While wc_reduce_stock_levels is active as an ancestor, an unrecognized
// test harness / custom mutator path triggers stock notification for Product B.
// Requirements:
// 1. Mutation must NOT be classified as direct (classified as unknown).
// 2. Must be isolated locally as manual_adjustment without order attribution.
// 3. Parent order context and FIFO queue must NOT be destroyed.
// 4. Product A and Product B legitimate reductions succeed with exact item attribution.
if ( ! function_exists( 'despace_test_unrecognized_harness_updater' ) ) {
	function despace_test_unrecognized_harness_updater( $product, $new_stock ) {
		$product->set_stock_quantity( $new_stock );
		// Trigger hook directly from this custom harness function
		do_action( 'woocommerce_product_set_stock', $product );
	}
}

$prod_u10a = new \WC_Product_Simple();
$prod_u10a->set_name( 'Case U10A ' . time() );
$prod_u10a->set_regular_price( '10.00' );
$prod_u10a->set_manage_stock( true );
$prod_u10a->set_stock_quantity( 20 );
$prod_u10a_id = $prod_u10a->save();

$prod_u10b = new \WC_Product_Simple();
$prod_u10b->set_name( 'Case U10B ' . time() );
$prod_u10b->set_regular_price( '15.00' );
$prod_u10b->set_manage_stock( true );
$prod_u10b->set_stock_quantity( 20 );
$prod_u10b_id = $prod_u10b->save();

$order_u10 = wc_create_order();
$item_u10a_id = $order_u10->add_product( $prod_u10a, 2 );
$item_u10b_id = $order_u10->add_product( $prod_u10b, 3 );
$order_u10->calculate_totals();
$order_u10->save();
$order_u10_id = $order_u10->get_id();

$u10_unrecognized_executed = false;
$u10_hook = function( $product ) use ( &$u10_unrecognized_executed, $prod_u10a_id, $prod_u10b ) {
	if ( ! $u10_unrecognized_executed && $product->get_id() === $prod_u10a_id ) {
		$u10_unrecognized_executed = true;
		// Trigger stock mutation via unrecognized custom harness function
		despace_test_unrecognized_harness_updater( $prod_u10b, 28 );
	}
};
add_action( 'woocommerce_product_set_stock', $u10_hook, 10, 1 );

wc_reduce_stock_levels( $order_u10 );
remove_action( 'woocommerce_product_set_stock', $u10_hook, 10 );

// Verify Product A reduction
$log_u10a = $wpdb->get_row( $wpdb->prepare(
	"SELECT * FROM {$log_table} WHERE product_id = %d AND order_id = %d AND event_type = %s ORDER BY id DESC LIMIT 1",
	$prod_u10a_id,
	$order_u10_id,
	'order_reduction'
), ARRAY_A );

$u10a_reduction_ok = ! empty( $log_u10a )
	&& ( (int) $log_u10a['order_item_id'] === $item_u10a_id )
	&& ( -2.0 === (float) $log_u10a['quantity_delta'] )
	&& ( 18.0 === (float) $log_u10a['stock_after'] );

// Verify Product B unrecognized mutation was isolated as manual_adjustment
$log_u10b_unrec = $wpdb->get_row( $wpdb->prepare(
	"SELECT * FROM {$log_table} WHERE product_id = %d AND event_type = %s ORDER BY id DESC LIMIT 1",
	$prod_u10b_id,
	'manual_adjustment'
), ARRAY_A );

$u10b_unrec_ok = ! empty( $log_u10b_unrec )
	&& is_null( $log_u10b_unrec['order_id'] )
	&& is_null( $log_u10b_unrec['order_item_id'] )
	&& is_null( $log_u10b_unrec['refund_id'] )
	&& ( 8.0 === (float) $log_u10b_unrec['quantity_delta'] )
	&& ( 28.0 === (float) $log_u10b_unrec['stock_after'] );

// Verify legitimate Product B reduction
$log_u10b_reduction = $wpdb->get_row( $wpdb->prepare(
	"SELECT * FROM {$log_table} WHERE product_id = %d AND order_id = %d AND event_type = %s ORDER BY id DESC LIMIT 1",
	$prod_u10b_id,
	$order_u10_id,
	'order_reduction'
), ARRAY_A );

$u10b_reduction_ok = ! empty( $log_u10b_reduction )
	&& ( (int) $log_u10b_reduction['order_item_id'] === $item_u10b_id )
	&& ( -3.0 === (float) $log_u10b_reduction['quantity_delta'] );

$case_u10_ok = $u10_unrecognized_executed && $u10a_reduction_ok && $u10b_unrec_ok && $u10b_reduction_ok;
record_test( 'U-10', 'Unrecognized mutation call path isolated locally without order attribution or queue loss (M4-REV-011)', $case_u10_ok, 'Unrecognized intermediate call path isolated locally (WooCommerce single source preserved)' );

// -------------------------------------------------------------------------
// Cleanup
// -------------------------------------------------------------------------
$order_a->delete( true );
$order_b->delete( true );
$order_c->delete( true );
$order_k->delete( true );
$order_l->delete( true );
$order_m->delete( true );
$order_n1->delete( true );
$order_n2->delete( true );
$order_o1->delete( true );
$order_o2->delete( true );
$order_o3->delete( true );
$order_p->delete( true );
$order_q1->delete( true );
$order_q2->delete( true );
$order_u1->delete( true );
$order_u2->delete( true );
$order_u3->delete( true );
$order_u4->delete( true );
if ( isset( $refund_u5 ) && $refund_u5 instanceof \WC_Order_Refund ) {
	$refund_u5->delete( true );
}
$order_u5->delete( true );
$order_u6->delete( true );
$order_u7->delete( true );
if ( isset( $refund_u8 ) && $refund_u8 instanceof \WC_Order_Refund ) {
	$refund_u8->delete( true );
}
$order_u8->delete( true );
$order_u9->delete( true );
$order_u10->delete( true );
$prod_a->delete( true );
$prod_b1->delete( true );
$prod_b2->delete( true );
$prod_c_var->delete( true );
$prod_h->delete( true );
$prod_i->delete( true );
$prod_k->delete( true );
$prod_l->delete( true );
$prod_m->delete( true );
$var_n1->delete( true );
$var_parent_n1->delete( true );
$var_n2->delete( true );
$var_parent_n2->delete( true );
$prod_o1->delete( true );
$prod_o2->delete( true );
$prod_o3->delete( true );
$prod_p1->delete( true );
$prod_p2->delete( true );
$var_q1->delete( true );
$var_q2->delete( true );
$var_parent_q->delete( true );
$prod_r1->delete( true );
$prod_r2->delete( true );
$prod_r3->delete( true );
$prod_r4->delete( true );
$prod_t->delete( true );
$prod_u1->delete( true );
$prod_u2->delete( true );
$prod_u3->delete( true );
$prod_u4->delete( true );
$prod_u5->delete( true );
$prod_u6a->delete( true );
$prod_u6b->delete( true );
$prod_u7a->delete( true );
$prod_u7b->delete( true );
$prod_u8a->delete( true );
$prod_u8b->delete( true );
$prod_u9a->delete( true );
$prod_u9b->delete( true );
$prod_u10a->delete( true );
$prod_u10b->delete( true );
$prod_unrelated->delete( true );

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

$total_cases = count( $test_results );
$passed_cases = count( array_filter( $test_results, function( $r ) { return $r['passed']; } ) );
printf( "SUMMARY: %d Total Cases | %d Passed | %d Failed\n", $total_cases, $passed_cases, ( $total_cases - $passed_cases ) );

if ( $all_cases_passed && 32 === $total_cases ) {
	echo ">>> ALL 32 TEST CASES (A - U) PASSED ON LIVE WOOCOMMERCE & MYSQL! <<<\n";
	echo "========================================================================\n";
	exit( 0 );
} else {
	echo ">>> SOME CASES FAILED OR INCOMPLETE RUN! <<<\n";
	echo "========================================================================\n";
	exit( 1 );
}
} catch ( \Throwable $e ) {
	fwrite( STDERR, "\nFATAL EXCEPTION in Live Order Context Suite: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n" );
	exit( 1 );
}


