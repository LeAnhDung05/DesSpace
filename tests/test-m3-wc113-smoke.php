<?php
/**
 * DeSpace M3 — WooCommerce 11.3.0 Targeted Smoke Test Suite.
 *
 * Verifies compatibility of M3 Checkout & Orders subsystem against WooCommerce 11.3.0
 * with HPOS and MariaDB active.
 */

ini_set( 'memory_limit', '512M' );
$_SERVER['REQUEST_METHOD'] = 'POST';
require_once __DIR__ . '/../wordpress/wp-load.php';

use DeSpace\CheckoutOrders\Cart\CartHandler;
use DeSpace\CheckoutOrders\Cart\CouponValidator;
use DeSpace\CheckoutOrders\Checkout\AddressValidator;
use DeSpace\CheckoutOrders\Checkout\CheckoutValidator;
use DeSpace\CheckoutOrders\Payment\PaymentMethodValidator;
use DeSpace\CheckoutOrders\Payment\PaymentStatusGuard;
use DeSpace\CheckoutOrders\Orders\GuestOrderTracker;
use DeSpace\CheckoutOrders\Orders\OrderAccessGuard;
use DeSpace\CheckoutOrders\Orders\OrderTrackingPresenter;
use DeSpace\CheckoutOrders\Account\AccountDataPresenter;
use DeSpace\CheckoutOrders\Email\EmailCustomizer;

if ( ! class_exists( 'WooCommerce' ) ) {
	fwrite( STDERR, "WooCommerce not active.\n" );
	exit( 1 );
}

echo "========================================================================\n";
echo "   DeSpace M3 — WOOCOMMERCE 11.3.0 COMPATIBILITY SMOKE TEST             \n";
echo "========================================================================\n";
echo "Active WC Version: " . WC_VERSION . "\n";
echo "HPOS Active: " . ( \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ? 'YES' : 'NO' ) . "\n\n";

$smoke_results = array();

function record_smoke( string $check, string $desc, bool $passed, string $details = '' ) {
	global $smoke_results;
	$smoke_results[] = array(
		'check'   => $check,
		'desc'    => $desc,
		'passed'  => $passed,
		'details' => $details,
	);
	printf( "[%-22s] %-52s : %s %s\n", $check, $desc, ( $passed ? 'PASS' : 'FAIL' ), ( $details ? "($details)" : '' ) );
}

// Ensure session
if ( null === WC()->session ) {
	WC()->session = new \WC_Session_Handler();
	WC()->session->init();
}
if ( null === WC()->customer ) {
	WC()->customer = new \WC_Customer( get_current_user_id(), true );
}
if ( null === WC()->cart ) {
	WC()->cart = new \WC_Cart();
}

// Create a test product
$product = new \WC_Product_Simple();
$product->set_name( 'WC 11.3 Smoke Test Product ' . time() );
$product->set_regular_price( '500000' );
$product->set_manage_stock( true );
$product->set_stock_quantity( 20 );
$product->save();
$product_id = $product->get_id();

// -------------------------------------------------------------
// 1. Cart Add / Update / Remove
// -------------------------------------------------------------
WC()->cart->empty_cart();
$cart_item_key = WC()->cart->add_to_cart( $product_id, 2 );
$add_ok = ! empty( $cart_item_key ) && 2 === WC()->cart->get_cart_contents_count();

WC()->cart->set_quantity( $cart_item_key, 3 );
$update_ok = 3 === WC()->cart->get_cart_contents_count();

WC()->cart->remove_cart_item( $cart_item_key );
$remove_ok = 0 === WC()->cart->get_cart_contents_count();

record_smoke( 'SMOKE-01-CART', 'Cart add / update quantity / remove lifecycle', ( $add_ok && $update_ok && $remove_ok ) );

// -------------------------------------------------------------
// 2. Coupon Application
// -------------------------------------------------------------
$coupon_code = 'wc113coupon_' . time();
$coupon = new \WC_Coupon();
$coupon->set_code( $coupon_code );
$coupon->set_discount_type( 'fixed_cart' );
$coupon->set_amount( '50000' );
$coupon->save();

WC()->cart->empty_cart();
WC()->cart->add_to_cart( $product_id, 1 );
$applied = WC()->cart->apply_coupon( $coupon_code );
$has_coupon = WC()->cart->has_discount( $coupon_code );
$discount_total = (float) WC()->cart->get_discount_total();
$coupon_ok = $has_coupon && ( 50000.0 === $discount_total );
record_smoke( 'SMOKE-02-COUPON', 'Coupon application & discount recalculation', $coupon_ok, "Discount: {$discount_total}" );

// -------------------------------------------------------------
// 3. Classic Checkout Execution
// -------------------------------------------------------------
// Set up guest session
wp_set_current_user( 0 );
WC()->cart->empty_cart();
WC()->cart->add_to_cart( $product_id, 2 );

$checkout_data = array(
	'billing_first_name'           => 'Trần',
	'billing_last_name'            => 'Thử Nghiệm 11.3',
	'billing_country'              => 'VN',
	'billing_state'                => '15',
	'billing_city'                 => 'Phường Lào Cai',
	'billing_despace_commune_code' => '02647',
	'billing_address_1'            => '12 Hoàng Liên',
	'billing_phone'                => '0912345678',
	'billing_email'                => 'smoke113_' . time() . '@despace.test',
	'payment_method'               => 'bacs',
	'terms'                        => 1,
);
$_POST = $checkout_data;
$checkout = WC()->checkout();
$classic_order_id = $checkout->create_order( $checkout_data );
$checkout_ok = is_numeric( $classic_order_id ) && $classic_order_id > 0;
record_smoke( 'SMOKE-03-CHECKOUT', 'Classic checkout order creation via WC()->checkout()', $checkout_ok, "Order #{$classic_order_id}" );

// Process BACS payment through gateway
$gateways = WC()->payment_gateways()->get_available_payment_gateways();
if ( isset( $gateways['bacs'] ) ) {
	$gateways['bacs']->process_payment( $classic_order_id );
}

// -------------------------------------------------------------
// 4. COD Order Creation & Status
// -------------------------------------------------------------
WC()->cart->empty_cart();
WC()->cart->add_to_cart( $product_id, 1 );
$cod_data = $checkout_data;
$cod_data['payment_method'] = 'cod';
$cod_data['billing_email']  = 'cod113_' . time() . '@despace.test';
$_POST = $cod_data;
$cod_order_id = $checkout->create_order( $cod_data );
if ( isset( $gateways['cod'] ) ) {
	$gateways['cod']->process_payment( $cod_order_id );
}
$cod_order = wc_get_order( $cod_order_id );
$cod_status = $cod_order ? $cod_order->get_status() : '';
$cod_method = $cod_order ? $cod_order->get_payment_method() : '';
$cod_ok = ( 'cod' === $cod_method && in_array( $cod_status, array( 'processing', 'on-hold' ), true ) );
record_smoke( 'SMOKE-04-COD', 'COD order creation & non-completed initial status', $cod_ok, "Status: {$cod_status}" );

// -------------------------------------------------------------
// 5. BACS Order Creation & On-Hold Initial State
// -------------------------------------------------------------
$bacs_order = wc_get_order( $classic_order_id );
$bacs_status = $bacs_order ? $bacs_order->get_status() : '';
$bacs_initial_paid = $bacs_order ? $bacs_order->is_paid() : true;
$bacs_date_paid = $bacs_order ? $bacs_order->get_date_paid() : 'not-null';
$bacs_init_ok = ( 'on-hold' === $bacs_status && ! $bacs_initial_paid && null === $bacs_date_paid );
record_smoke( 'SMOKE-05-BACS-INIT', 'BACS order creation starts on-hold, unpaid', $bacs_init_ok, "is_paid=false, date_paid=null" );

// -------------------------------------------------------------
// 6. Admin BACS Payment Completion via PaymentStatusGuard
// -------------------------------------------------------------
// Elevate to admin
$admin_user_id = 1;
$all_admins = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
if ( ! empty( $all_admins ) ) {
	$admin_user_id = $all_admins[0]->ID;
}
wp_set_current_user( $admin_user_id );

$nonce = wp_create_nonce( 'despace_confirm_bacs_payment_' . $classic_order_id );
$confirm_res = PaymentStatusGuard::confirm_bacs_payment_by_admin(
	$classic_order_id,
	'TX_WC113_REF_' . time(),
	array(
		'confirmed_received' => true,
		'nonce'              => $nonce,
	)
);
$bacs_confirm_ok = ! is_wp_error( $confirm_res ) && ! empty( $confirm_res['confirmed'] );
record_smoke( 'SMOKE-06-BACS-CONFIRM', 'Admin BACS settlement via PaymentStatusGuard', $bacs_confirm_ok );

// -------------------------------------------------------------
// 7. Paid State & Date_Paid Dual Invariant Verification
// -------------------------------------------------------------
$fresh_bacs = wc_get_order( $classic_order_id );
$final_is_paid   = $fresh_bacs ? $fresh_bacs->is_paid() : false;
$final_date_paid = $fresh_bacs ? $fresh_bacs->get_date_paid() : null;
$final_status    = $fresh_bacs ? $fresh_bacs->get_status() : '';
$dual_invariant_ok = ( $final_is_paid && null !== $final_date_paid && 'on-hold' !== $final_status );
record_smoke( 'SMOKE-07-PAID-STATE', 'Strict dual invariant: is_paid=true AND date_paid!=null', $dual_invariant_ok, "Status: {$final_status}" );

// -------------------------------------------------------------
// 8. Native Stock Lifecycle Verification
// -------------------------------------------------------------
// Initial stock was 20.
// Order 1 (classic BACS) took 2 units via process_payment.
// Order 2 (COD) took 1 unit via process_payment.
// Total expected remaining stock in WC 11.3 = 20 - 2 - 1 = 17 units.
$current_product = wc_get_product( $product_id );
$current_stock   = (float) $current_product->get_stock_quantity();
$stock_ok        = ( 17.0 === $current_stock );
record_smoke( 'SMOKE-08-STOCK-LIFECYCLE', 'Native stock deduction on orders (20 -> 17)', $stock_ok, "Remaining: {$current_stock}" );

// -------------------------------------------------------------
// 9. M3 Email Address Formatting (Single block & 2-tier VN)
// -------------------------------------------------------------
$formatted_addr = WC()->countries->get_formatted_address( array(
	'country'   => 'VN',
	'state'     => '15',
	'city'      => 'Phường Lào Cai',
	'address_1' => '12 Hoàng Liên',
) );
$has_commune = false !== strpos( $formatted_addr, 'Phường Lào Cai' );
$has_prov    = false !== strpos( $formatted_addr, 'Lào Cai' );
$email_addr_ok = ( $has_commune && $has_prov );
record_smoke( 'SMOKE-09-EMAIL-ADDR', 'M3 2-tier Vietnam address formatting filter', $email_addr_ok );

// -------------------------------------------------------------
// 10. Guest Order Tracking
// -------------------------------------------------------------
wp_set_current_user( 0 );
$tracking_nonce = wp_create_nonce( OrderAccessGuard::NONCE_ACTION );
$track_res = GuestOrderTracker::handle_lookup_request( array(
	'order_number'           => (string) $classic_order_id,
	'billing_email'          => $checkout_data['billing_email'],
	'despace_tracking_nonce' => $tracking_nonce,
), array() );
$tracking_found = ! empty( $track_res['success'] );

// IDOR rejection
$idor_res = GuestOrderTracker::handle_lookup_request( array(
	'order_number'           => (string) $classic_order_id,
	'billing_email'          => 'attacker@test.com',
	'despace_tracking_nonce' => $tracking_nonce,
), array() );
$idor_rejected = empty( $idor_res['success'] );
$tracking_ok = ( $tracking_found && $idor_rejected );
record_smoke( 'SMOKE-10-ORDER-TRACKING', 'Guest order tracking lookup & IDOR protection', $tracking_ok );

// -------------------------------------------------------------
// 11. Customer Account History Pagination (>100 Orders)
// -------------------------------------------------------------
$customer_email = 'wc113_cust_' . time() . '@despace.test';
$customer_user_id = wp_create_user( 'wc113_user_' . time(), 'Password@123', $customer_email );
$cust_orders = array();
for ( $i = 1; $i <= 105; $i++ ) {
	$o = wc_create_order( array(
		'customer_id' => $customer_user_id,
		'status'      => 'processing',
	) );
	$o->save();
	$cust_orders[] = $o->get_id();
}

wp_set_current_user( $customer_user_id );
$page_1 = AccountDataPresenter::get_customer_order_history( 1, 10 );
$page_11 = AccountDataPresenter::get_customer_order_history( 11, 10 );

$p1_count  = count( $page_1['items'] );
$p11_count = count( $page_11['items'] );
$total_rep = $page_1['total'];
$max_p     = $page_1['max_num_pages'];

$pag_ok = ( 105 === $total_rep && 11 === $max_p && 10 === $p1_count && 5 === $p11_count );
record_smoke( 'SMOKE-11-PAGINATION', 'Customer order history pagination (>100 HPOS orders)', $pag_ok, "105 total, 11 pages, p11 count=5" );

// Cleanup
foreach ( $cust_orders as $oid ) {
	$o = wc_get_order( $oid );
	if ( $o ) $o->delete( true );
}
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $customer_user_id );
$bacs_order->delete( true );
$cod_order->delete( true );
$product->delete( true );
$coupon->delete( true );

// -------------------------------------------------------------
// Summary
// -------------------------------------------------------------
echo "\n========================================================================\n";
$all_pass = true;
foreach ( $smoke_results as $sr ) {
	if ( ! $sr['passed'] ) {
		$all_pass = false;
	}
}

if ( $all_pass ) {
	echo ">>> WOOCOMMERCE 11.3.0 SMOKE TESTS: 11/11 PASSED (100%) <<<\n";
	echo "========================================================================\n";
	exit( 0 );
} else {
	echo ">>> WOOCOMMERCE 11.3.0 SMOKE TESTS: SOME CHECKS FAILED <<<\n";
	echo "========================================================================\n";
	exit( 1 );
}

