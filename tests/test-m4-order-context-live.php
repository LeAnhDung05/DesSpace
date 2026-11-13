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
echo "   DeSpace M4 — LIVE INVENTORY ORDER CONTEXT TEST SUITE (CASES A-O)     \n";
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
// Case N: Variation Restoration and Refund (Individual & Parent-Managed)
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

$order_n1 = wc_create_order();
$item_n1_id = $order_n1->add_product( wc_get_product( $var_n1_id ), 4 );
$order_n1->calculate_totals();
$order_n1->save();
$order_n1_id = $order_n1->get_id();

wc_reduce_stock_levels( $order_n1 ); // stock 15 -> 11

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
// stock 11 -> 13

// Restore remaining reduced stock
$order_n1 = wc_get_order( $order_n1_id );
wc_increase_stock_levels( $order_n1 ); // stock 13 -> 15

$var_n1_final_stock = (float) wc_get_product( $var_n1_id )->get_stock_quantity();

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

$order_n2 = wc_create_order();
$item_n2_id = $order_n2->add_product( wc_get_product( $var_n2_id ), 3 );
$order_n2->calculate_totals();
$order_n2->save();
$order_n2_id = $order_n2->get_id();

wc_reduce_stock_levels( $order_n2 ); // parent stock 20 -> 17
$parent_n2_reduced_stock = (float) wc_get_product( $var_parent_n2_id )->get_stock_quantity();

$log_n2_reduce = $wpdb->get_row( $wpdb->prepare(
	"SELECT * FROM {$log_table} WHERE product_id = %d AND order_id = %d AND event_type = %s",
	$var_parent_n2_id,
	$order_n2_id,
	'order_reduction'
), ARRAY_A );

$case_n_ok = ( 15.0 === $var_n1_final_stock )
	&& ( ! empty( $log_n1_reduce ) && (int) $log_n1_reduce['product_id'] === (int) $var_parent_n1_id && (int) $log_n1_reduce['order_item_id'] === (int) $item_n1_id )
	&& ( ! empty( $log_n1_refund ) && (int) $log_n1_refund['product_id'] === (int) $var_parent_n1_id && (int) $log_n1_refund['refund_id'] === (int) $refund_n1_id )
	&& ( ! empty( $log_n1_restore ) && (int) $log_n1_restore['product_id'] === (int) $var_parent_n1_id && ( 2.0 === (float) $log_n1_restore['quantity_delta'] ) )
	&& ( 17.0 === $parent_n2_reduced_stock )
	&& ( ! empty( $log_n2_reduce ) && null === $log_n2_reduce['variation_id'] && (int) $log_n2_reduce['order_item_id'] === (int) $item_n2_id );

record_test( 'N', 'Variation restoration/refund (own-stock & parent-managed stock attributed)', $case_n_ok, "Var stock: {$var_n1_final_stock}, Parent stock: {$parent_n2_reduced_stock}" );

// -------------------------------------------------------------------------
// Case O: Denied Operation & Stale Context Cleanup
// -------------------------------------------------------------------------
$prod_o = new \WC_Product_Simple();
$prod_o->set_name( 'Case O Denied Prod ' . time() );
$prod_o->set_regular_price( '75000' );
$prod_o->set_manage_stock( true );
$prod_o->set_stock_quantity( 10 );
$prod_o_id = $prod_o->save();

$wpdb->delete( $log_table, array( 'product_id' => $prod_o_id ) );

$order_o = wc_create_order();
$item_o_id = $order_o->add_product( $prod_o, 3 );
$order_o->calculate_totals();
$order_o->save();
$order_o_id = $order_o->get_id();

// Hook filter to DENY stock reduction
$deny_filter = function() { return false; };
add_filter( 'woocommerce_can_reduce_order_stock', $deny_filter, 1 );

wc_reduce_stock_levels( $order_o );

remove_filter( 'woocommerce_can_reduce_order_stock', $deny_filter, 1 );

$stock_o_after_denied = (float) wc_get_product( $prod_o_id )->get_stock_quantity();
$logs_count_o_denied = (int) $wpdb->get_var( $wpdb->prepare(
	"SELECT COUNT(*) FROM {$log_table} WHERE product_id = %d",
	$prod_o_id
) );

$context_clean_after_denied = ! \DeSpace\Inventory\Events\InventoryOrderContext::has_order_context()
	&& ! \DeSpace\Inventory\Events\InventoryOrderContext::has_refund_context();

// Trigger an unrelated manual stock update by an admin
wp_set_current_user( 1 );
$prod_o->set_stock_quantity( 18 );
$prod_o->save();

$manual_log_o = $wpdb->get_row( $wpdb->prepare(
	"SELECT * FROM {$log_table} WHERE product_id = %d ORDER BY id DESC LIMIT 1",
	$prod_o_id
), ARRAY_A );

$case_o_ok = ( 10.0 === $stock_o_after_denied )
	&& ( 0 === $logs_count_o_denied )
	&& $context_clean_after_denied
	&& ( ! empty( $manual_log_o ) )
	&& ( 'manual_adjustment' === $manual_log_o['event_type'] )
	&& ( empty( $manual_log_o['order_id'] ) )
	&& ( empty( $manual_log_o['refund_id'] ) )
	&& ( empty( $manual_log_o['order_item_id'] ) )
	&& ( (int) $manual_log_o['actor_user_id'] === 1 )
	&& ( 8.0 === (float) $manual_log_o['quantity_delta'] )
	&& ( 18.0 === (float) $manual_log_o['stock_after'] );

record_test( 'O', 'Denied operation / context cleanup (zero logs when denied, clean manual adjustment)', $case_o_ok, "Manual event_type: {$manual_log_o['event_type']}, actor: {$manual_log_o['actor_user_id']}" );

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
$order_o->delete( true );
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
$prod_o->delete( true );

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
	echo ">>> ALL 15 MANDATORY CASES (A - O) PASSED ON LIVE WOOCOMMERCE & MYSQL! <<<\n";
	echo "========================================================================\n";
	exit( 0 );
} else {
	echo ">>> SOME CASES FAILED <<<\n";
	echo "========================================================================\n";
	exit( 1 );
}


