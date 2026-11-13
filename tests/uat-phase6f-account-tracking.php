<?php
/**
 * DeSpace M3 Live UAT — Phase 6F: Customer Account & Guest Order Tracking.
 *
 * Runs against live WordPress, WooCommerce HPOS, and MariaDB.
 */

ini_set( 'memory_limit', '512M' );
$_SERVER['REQUEST_METHOD'] = 'POST';
require_once __DIR__ . '/../wordpress/wp-load.php';

use DeSpace\CheckoutOrders\Account\AccountAddressHandler;
use DeSpace\CheckoutOrders\Account\AccountDataPresenter;
use DeSpace\CheckoutOrders\Account\AccountProfileHandler;
use DeSpace\CheckoutOrders\Orders\GuestOrderTracker;
use DeSpace\CheckoutOrders\Orders\OrderAccessGuard;
use DeSpace\CheckoutOrders\Orders\OrderTrackingPresenter;

if ( ! class_exists( 'WooCommerce' ) ) {
	fwrite( STDERR, "WooCommerce not active.\n" );
	exit( 1 );
}

echo "========================================================================\n";
echo "   DeSpace M3 Phase 6F — ACCOUNT PAGINATION (>100 ORDERS) & TRACKING    \n";
echo "========================================================================\n\n";

// 1. Create a Customer User
$customer_email = 'pagination_test_' . time() . '@despace.test';
$customer_id    = wp_create_user( 'pag_user_' . time(), 'Password@123', $customer_email );
$customer       = new \WC_Customer( $customer_id );
$customer->set_first_name( 'Nguyễn' );
$customer->set_last_name( 'Văn Phân Trang' );
$customer->save();
echo "Step 1: Created customer #{$customer_id} ({$customer_email}).\n";

// 2. Generate 105 Synthetic HPOS Orders
echo "Step 2: Generating 105 synthetic HPOS orders for Customer #{$customer_id}...\n";
$created_order_ids = array();

for ( $i = 1; $i <= 105; $i++ ) {
	$order = wc_create_order( array(
		'customer_id' => $customer_id,
		'status'      => ( $i % 3 === 0 ? 'completed' : ( $i % 3 === 1 ? 'processing' : 'on-hold' ) ),
		'created_via' => 'uat_pagination_105',
	) );
	$order->set_billing_first_name( 'Nguyễn' );
	$order->set_billing_last_name( 'Văn Phân Trang' );
	$order->set_billing_email( $customer_email );
	$order->set_billing_phone( '0987654321' );
	$order->set_total( 1000000 + ( $i * 10000 ) );
	$order->save();
	$created_order_ids[] = $order->get_id();
}
echo "  -> Created 105 orders. First ID: {$created_order_ids[0]}, Last ID: " . end( $created_order_ids ) . "\n\n";

// 3. Test Order History Pagination via AccountDataPresenter
echo "Step 3: Testing Account Order History Pagination (>100 Orders):\n";

wp_set_current_user( $customer_id );

// Page 1
$page_1 = AccountDataPresenter::get_customer_order_history( 1, 10 );
$total_orders = $page_1['total'];
$max_pages    = $page_1['max_num_pages'];
$p1_count     = count( $page_1['items'] );

echo "  -> Page 1: {$p1_count} orders returned | Total reported: {$total_orders} | Total pages: {$max_pages}\n";

// Page 2
$page_2 = AccountDataPresenter::get_customer_order_history( 2, 10 );
$p2_count = count( $page_2['items'] );
echo "  -> Page 2: {$p2_count} orders returned\n";

// Last Page (Page 11)
$page_last = AccountDataPresenter::get_customer_order_history( 11, 10 );
$last_count = count( $page_last['items'] );
echo "  -> Page 11 (Last): {$last_count} orders returned (Expected: 5)\n";

// Ensure no ID overlap between Page 1 and Page 2
$p1_ids = array_column( $page_1['items'], 'id' );
$p2_ids = array_column( $page_2['items'], 'id' );
$overlap = array_intersect( $p1_ids, $p2_ids );
echo "  -> Overlap between Page 1 and Page 2: " . ( empty( $overlap ) ? 'ZERO (PASS)' : 'OVERLAP FOUND (FAIL)' ) . "\n";

$pagination_ok = ( 105 === $total_orders && 11 === $max_pages && 10 === $p1_count && 10 === $p2_count && 5 === $last_count && empty( $overlap ) );

// 4. Test Customer Profile & Address Update
echo "\nStep 4: Testing Customer Profile & Address Handlers:\n";

// Profile update
$nonce_profile = wp_create_nonce( 'save_account_details' );
$profile_post = array(
	'account_first_name' => 'Nguyễn Văn',
	'account_last_name'  => 'Cập Nhật',
	'account_email'      => $customer_email,
	'account_phone'      => '0911223344',
	'nonce'              => $nonce_profile,
);
$profile_res = AccountProfileHandler::update_profile( $profile_post );
echo "  -> Profile update result: " . ( is_wp_error( $profile_res ) ? 'FAIL: ' . $profile_res->get_error_message() : 'SUCCESS' ) . "\n";

// Address update (2-tier Vietnam)
$nonce_addr = wp_create_nonce( 'woocommerce-edit_address' );
$addr_post = array(
	'billing_country'              => 'VN',
	'billing_state'                => '15',
	'billing_city'                 => 'Phường Lào Cai',
	'billing_despace_commune_code' => '02647',
	'billing_address_1'            => '100 Đường Trần Hưng Đạo',
	'billing_phone'                => '0911223344',
	'nonce'                        => $nonce_addr,
);
$addr_res = AccountAddressHandler::save_customer_address( 'billing', $addr_post );
echo "  -> Address update result: " . ( is_wp_error( $addr_res ) ? 'FAIL: ' . $addr_res->get_error_message() : 'SUCCESS' ) . "\n";

$fresh_customer = new \WC_Customer( $customer_id );
$saved_state = $fresh_customer->get_billing_state();
$saved_commune = get_user_meta( $customer_id, '_billing_despace_commune_code', true );
echo "  -> Saved state: {$saved_state} | Saved commune: {$saved_commune} (Expected: 15 & 02647)\n";
$account_ok = ( '15' === $saved_state && '02647' === $saved_commune );

// 5. Test Guest Order Tracking Security & Invariants
echo "\nStep 5: Testing Guest Order Tracking Security & Invariants:\n";
wp_set_current_user( 0 ); // Guest context
$test_order_id = $created_order_ids[0];
$test_order    = wc_get_order( $test_order_id );

// Add a private admin note
$test_order->add_order_note( 'PRIVATE_SECRET_ADMIN_NOTE_DO_NOT_EXPOSE', false, false );

$tracking_nonce = wp_create_nonce( OrderAccessGuard::NONCE_ACTION );

// A. Lookup by Email
$req_email = array(
	'order_number'           => (string) $test_order_id,
	'billing_email'          => $customer_email,
	'despace_tracking_nonce' => $tracking_nonce,
);
$res_email = GuestOrderTracker::handle_lookup_request( $req_email, array() );
$lookup_email_ok = ! empty( $res_email['success'] );
echo "  -> Lookup by valid email: " . ( $lookup_email_ok ? 'FOUND (PASS)' : 'FAIL: ' . json_encode( $res_email ) ) . "\n";

// B. Lookup by Normalized Phone
$req_phone = array(
	'order_number'           => (string) $test_order_id,
	'billing_phone'          => '0987654321',
	'despace_tracking_nonce' => $tracking_nonce,
);
$res_phone = GuestOrderTracker::handle_lookup_request( $req_phone, array() );
$lookup_phone_ok = ! empty( $res_phone['success'] );
echo "  -> Lookup by normalized phone: " . ( $lookup_phone_ok ? 'FOUND (PASS)' : 'FAIL: ' . json_encode( $res_phone ) ) . "\n";

// C. IDOR Protection: Wrong email
$req_wrong = array(
	'order_number'           => (string) $test_order_id,
	'billing_email'          => 'attacker@hacker.test',
	'despace_tracking_nonce' => $tracking_nonce,
);
$res_wrong = GuestOrderTracker::handle_lookup_request( $req_wrong, array() );
$lookup_wrong_rejected = empty( $res_wrong['success'] );
echo "  -> Lookup with mismatched email: " . ( $lookup_wrong_rejected ? 'REJECTED (PASS)' : 'UNSAFE (FAIL)' ) . "\n";

// D. Privacy Audit: Ensure private admin notes and order_key are NOT exposed in presenter DTO
$presented = OrderTrackingPresenter::present_order( $test_order );
$json_dump = json_encode( $presented );
$has_private_note = false !== strpos( $json_dump, 'PRIVATE_SECRET_ADMIN_NOTE' );
$has_order_key    = isset( $presented['order_key'] ) || false !== strpos( $json_dump, $test_order->get_order_key() );

echo "  -> Private admin notes exposed in tracking DTO: " . ( $has_private_note ? 'EXPOSED (BUG)' : 'NO (SAFE)' ) . "\n";
echo "  -> Order key exposed in guest tracking DTO: " . ( $has_order_key ? 'EXPOSED (BUG)' : 'NO (SAFE)' ) . "\n";

$tracking_ok = ( $lookup_email_ok && $lookup_phone_ok && $lookup_wrong_rejected && ! $has_private_note && ! $has_order_key );

// 6. Cleanup 105 Orders & Customer
echo "\nStep 6: Cleaning up test orders and user...\n";
foreach ( $created_order_ids as $oid ) {
	$o = wc_get_order( $oid );
	if ( $o ) $o->delete( true );
}
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $customer_id );
echo "  -> Cleaned up 105 test orders and customer user.\n";

echo "\n========================================================================\n";
if ( $pagination_ok && $account_ok && $tracking_ok ) {
	echo ">>> PHASE 6F ACCOUNT PAGINATION & TRACKING: ALL ASSERTIONS PASSED (100%) <<<\n";
	echo "========================================================================\n";
} else {
	echo ">>> PHASE 6F ACCOUNT PAGINATION & TRACKING: FAILED <<<\n";
	echo "========================================================================\n";
	exit( 1 );
}
