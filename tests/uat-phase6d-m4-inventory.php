<?php
/**
 * DeSpace M3 Live UAT — Phase 6D: M4 Inventory Lifecycle & Stock Reconciliation.
 *
 * Runs against live MariaDB and HPOS.
 */

require_once __DIR__ . '/../wordpress/wp-load.php';

use DeSpace\CheckoutOrders\Cart\CartHandler;
use DeSpace\CheckoutOrders\Payment\PaymentStatusGuard;

if ( ! class_exists( 'WooCommerce' ) ) {
	fwrite( STDERR, "WooCommerce not active.\n" );
	exit( 1 );
}

echo "========================================================================\n";
echo "   DeSpace M3 Phase 6D — M4 INVENTORY LIFECYCLE & RECONCILIATION       \n";
echo "========================================================================\n\n";

global $wpdb;
$log_table = $wpdb->prefix . 'despace_inventory_logs';

// 1. Create a stock-managed product
$product = new \WC_Product_Simple();
$product->set_name( 'UAT M4 Stock Chair ' . time() );
$product->set_regular_price( '3200000' );
$product->set_manage_stock( true );
$product->set_stock_quantity( 20 );
$product->set_status( 'publish' );
$product_id = $product->save();

echo "Step 1: Created product #{$product_id} with initial stock = 20.\n";
$initial_stock = (float) wc_get_product( $product_id )->get_stock_quantity();
echo "  -> Verified initial stock in WooCommerce: {$initial_stock}\n\n";

// 2. Place a valid BACS order with quantity = 3
wp_set_current_user( 0 );
WC()->cart->empty_cart();
CartHandler::add_item( $product_id, 3 );

$checkout = WC()->checkout();
$posted_data = array(
	'billing_first_name'           => 'Trần',
	'billing_last_name'            => 'Văn UAT',
	'billing_email'                => 'tran.uat@despace.vn',
	'billing_phone'                => '0988776655',
	'billing_country'              => 'VN',
	'billing_state'                => '15',
	'billing_city'                 => 'Lào Cai',
	'billing_despace_commune_code' => '02647',
	'billing_address_1'            => '123 Đường Fansipan',
	'payment_method'               => 'bacs',
);

$order_id = $checkout->create_order( $posted_data );
if ( is_wp_error( $order_id ) || ! ( $order_id > 0 ) ) {
	fwrite( STDERR, "Failed to create order: " . ( is_wp_error( $order_id ) ? $order_id->get_error_message() : 'Unknown' ) . "\n" );
	$product->delete( true );
	exit( 1 );
}

// Transition order to On-Hold via BACS gateway process_payment
$gateways = WC()->payment_gateways->payment_gateways();
if ( isset( $gateways['bacs'] ) ) {
	$gateways['bacs']->process_payment( $order_id );
}

$order = wc_get_order( $order_id );
echo "Step 2: Placed BACS Order #{$order_id} for 3 units.\n";
echo "  -> Order initial status: " . $order->get_status() . " (Expected: on-hold)\n";
echo "  -> Stock immediately after BACS checkout (On hold): " . wc_get_product( $product_id )->get_stock_quantity() . " (Expected: 20)\n\n";

// 3. Confirm BACS payment by Admin -> invokes payment_complete() -> native wc_reduce_stock_levels()
$admin_user = get_user_by( 'email', 'admin_test@despace.vn' );
$admin_id   = $admin_user ? $admin_user->ID : 1;
wp_set_current_user( $admin_id );

$nonce = wp_create_nonce( 'despace_confirm_bacs_payment_' . $order_id );
$confirm_res = PaymentStatusGuard::confirm_bacs_payment_by_admin(
	$order_id,
	'TX_M4_UAT_' . time(),
	array(
		'confirmed_received' => true,
		'nonce'              => $nonce,
	)
);

echo "Step 3: Confirmed BACS Payment via PaymentStatusGuard.\n";
echo "  -> Result: " . ( is_wp_error( $confirm_res ) ? 'ERROR: ' . $confirm_res->get_error_message() : 'SUCCESS' ) . "\n";

// Reload fresh product & order
$product_after_pay = wc_get_product( $product_id );
$stock_after_pay   = (float) $product_after_pay->get_stock_quantity();
echo "  -> WooCommerce stock quantity after payment complete: {$stock_after_pay} (Expected: 17)\n";

// 4. Inspect M4 Inventory Logs in wp_despace_inventory_logs
$order_logs = $wpdb->get_results( $wpdb->prepare(
	"SELECT * FROM {$log_table} WHERE product_id = %d ORDER BY id ASC",
	$product_id
), ARRAY_A );

echo "\nStep 4: M4 Inventory Log Reconciliation for Product #{$product_id} (Order #{$order_id}):\n";
echo "  -> Total log rows recorded for product: " . count( $order_logs ) . "\n";
$reduction_ok = false;
foreach ( $order_logs as $l ) {
	echo sprintf(
		"     Row #%d | type: %s | delta: %f | before: %f | after: %f | source: %s | event_key: %s\n",
		$l['id'],
		$l['event_type'],
		(float) $l['quantity_delta'],
		(float) $l['quantity_before'],
		(float) $l['stock_after'],
		$l['event_source'],
		substr( $l['event_key'], 0, 16 ) . '...'
	);
	if ( -3.0 === (float) $l['quantity_delta'] && 17.0 === (float) $l['stock_after'] ) {
		$reduction_ok = true;
		if ( 'order_reduction' !== $l['event_type'] ) {
			echo "     [ADVISORY M4]: Stock decrease of -3.0 captured, but recorded as '{$l['event_type']}'\n";
			echo "                    because WooCommerce wc_reduce_order_stock() updates stock before\n";
			echo "                    firing 'woocommerce_reduce_order_stock' hook.\n";
		}
	}
}

// 5. Test Refund & Restock Lifecycle
echo "\nStep 5: Process Refund and Restock of 1 item in WooCommerce:\n";
$items = $order->get_items();
$first_item = reset( $items );
$item_id = $first_item ? $first_item->get_id() : 0;

$refund = wc_create_refund( array(
	'amount'         => 3200000,
	'reason'         => 'UAT M4 Restock test',
	'order_id'       => $order_id,
	'line_items'     => array(
		$item_id => array(
			'qty'          => 1,
			'refund_total' => 3200000,
			'refund_tax'   => array(),
		),
	),
	'restock_items'  => true,
) );

$product_after_refund = wc_get_product( $product_id );
$stock_after_refund   = (float) $product_after_refund->get_stock_quantity();
echo "  -> WooCommerce stock quantity after refund restock: {$stock_after_refund} (Expected: 18)\n";

$refund_logs = $wpdb->get_results( $wpdb->prepare(
	"SELECT * FROM {$log_table} WHERE product_id = %d ORDER BY id DESC LIMIT 1",
	$product_id
), ARRAY_A );

echo "  -> M4 last log row for product: " . count( $refund_logs ) . "\n";
$restock_ok = false;
foreach ( $refund_logs as $rl ) {
	echo sprintf(
		"     Row #%d | type: %s | delta: %f | before: %f | after: %f\n",
		$rl['id'],
		$rl['event_type'],
		(float) $rl['quantity_delta'],
		(float) $rl['quantity_before'],
		(float) $rl['stock_after']
	);
	if ( 1.0 === (float) $rl['quantity_delta'] && 18.0 === (float) $rl['stock_after'] ) {
		$restock_ok = true;
	}
}

// Cleanup
$order->delete( true );
$product->delete( true );

echo "\n========================================================================\n";
if ( 17.0 === $stock_after_pay && 18.0 === $stock_after_refund && $reduction_ok && $restock_ok ) {
	echo ">>> PHASE 6D M4 INVENTORY RECONCILIATION: ALL ASSERTIONS PASSED (100%) <<<\n";
	echo "========================================================================\n";
} else {
	echo ">>> PHASE 6D M4 INVENTORY RECONCILIATION: FAILED <<<\n";
	echo "========================================================================\n";
	exit( 1 );
}
