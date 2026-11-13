<?php
/**
 * DeSpace M3 Live UAT — BACS Real Concurrency & Atomic Lock Test.
 *
 * Runs against live MariaDB and HPOS.
 */

require_once __DIR__ . '/../wordpress/wp-load.php';

use DeSpace\CheckoutOrders\Payment\PaymentStatusGuard;

if ( ! class_exists( 'WooCommerce' ) ) {
	fwrite( STDERR, "WooCommerce not active.\n" );
	exit( 1 );
}

$action = $argv[1] ?? 'coordinator';

if ( 'worker' === $action ) {
	$order_id   = absint( $argv[2] ?? 0 );
	$worker_id  = $argv[3] ?? 'unknown';
	$delay_ms   = absint( $argv[4] ?? 0 );
	$admin_id   = absint( $argv[5] ?? 1 );

	wp_set_current_user( $admin_id );

	if ( $delay_ms > 0 ) {
		usleep( $delay_ms * 1000 );
	}

	$nonce = wp_create_nonce( 'despace_confirm_bacs_payment_' . $order_id );
	$options = array(
		'confirmed_received' => true,
		'nonce'              => $nonce,
	);
	$bank_ref = 'TX_' . $worker_id . '_' . time();

	$res = PaymentStatusGuard::confirm_bacs_payment_by_admin( $order_id, $bank_ref, $options );
	echo json_encode( array(
		'worker'  => $worker_id,
		'success' => ! is_wp_error( $res ),
		'error'   => is_wp_error( $res ) ? $res->get_error_message() : null,
		'code'    => is_wp_error( $res ) ? $res->get_error_code() : null,
		'data'    => is_wp_error( $res ) ? null : $res,
	) ) . "\n";
	exit( 0 );
}

// Coordinator mode
echo "========================================================================\n";
echo "   DeSpace M3 Phase 6C — REAL CONCURRENT BACS PAYMENT CONFIRMATION      \n";
echo "========================================================================\n\n";

// 1. Create shop manager or admin user
$admin_user = get_user_by( 'email', 'admin_test@despace.vn' );
if ( ! $admin_user ) {
	$admin_id = wp_create_user( 'admin_test_' . time(), wp_generate_password( 16, true ), 'admin_test@despace.vn' );
	$admin_user = get_user_by( 'id', $admin_id );
	$admin_user->set_role( 'administrator' );
} else {
	$admin_id = $admin_user->ID;
}

// 2. Create BACS Order in On-Hold status
$order = wc_create_order( array(
	'status'        => 'on-hold',
	'customer_id'   => 0,
	'created_via'   => 'uat_bacs',
) );
$order->set_payment_method( 'bacs' );
$order->set_payment_method_title( 'Chuyển khoản ngân hàng' );
$order->set_total( 5000000 );
$order->save();
$order_id = $order->get_id();

echo "Created test BACS Order #{$order_id} in status 'on-hold'.\n";
echo "Initial is_paid(): " . ( $order->is_paid() ? 'YES' : 'NO' ) . "\n";
echo "Initial date_paid(): " . var_export( $order->get_date_paid(), true ) . "\n\n";

// 3. Spawn Two Concurrent PHP Subprocesses
echo "Spawning Worker A and Worker B concurrently against Order #{$order_id}...\n";

$cmd_a = sprintf( 'php %s worker %d Worker_A 0 %d', escapeshellarg( __FILE__ ), $order_id, $admin_id );
$cmd_b = sprintf( 'php %s worker %d Worker_B 15 %d', escapeshellarg( __FILE__ ), $order_id, $admin_id ); // 15ms offset to hit while A processes inside lock

$descriptors = array(
	0 => array( 'pipe', 'r' ),
	1 => array( 'pipe', 'w' ),
	2 => array( 'pipe', 'w' ),
);

$proc_a = proc_open( $cmd_a, $descriptors, $pipes_a );
$proc_b = proc_open( $cmd_b, $descriptors, $pipes_b );

$out_a = stream_get_contents( $pipes_a[1] );
$err_a = stream_get_contents( $pipes_a[2] );
fclose( $pipes_a[1] ); fclose( $pipes_a[2] );
$exit_a = proc_close( $proc_a );

$out_b = stream_get_contents( $pipes_b[1] );
$err_b = stream_get_contents( $pipes_b[2] );
fclose( $pipes_b[1] ); fclose( $pipes_b[2] );
$exit_b = proc_close( $proc_b );

echo "Worker A result: " . trim( $out_a ) . "\n";
echo "Worker B result: " . trim( $out_b ) . "\n";

$res_a = json_decode( trim( $out_a ), true );
$res_b = json_decode( trim( $out_b ), true );

// Verification: exactly one worker succeeded, one worker was rejected
$success_count = ( ! empty( $res_a['success'] ) ? 1 : 0 ) + ( ! empty( $res_b['success'] ) ? 1 : 0 );
echo "\nTotal Successful Payment Confirmations: {$success_count} (Expected: exactly 1)\n";

// 4. Verify Order State in MySQL
$fresh_order = wc_get_order( $order_id );
echo "Final Order Status: " . $fresh_order->get_status() . " (Expected: processing)\n";
echo "Final is_paid(): " . ( $fresh_order->is_paid() ? 'YES' : 'NO' ) . " (Expected: YES)\n";
echo "Final date_paid(): " . ( $fresh_order->get_date_paid() ? $fresh_order->get_date_paid()->format( 'Y-m-d H:i:s' ) : 'NULL' ) . " (Expected: not null)\n";

// 5. Verify Settlement Order Notes
$notes = wc_get_order_notes( array( 'order_id' => $order_id ) );
$settlement_notes = array_filter( $notes, function( $n ) {
	return false !== strpos( $n->content, 'BACS' ) || false !== strpos( $n->content, 'xác nhận' );
} );
echo "Settlement audit notes count: " . count( $settlement_notes ) . " (Expected: exactly 1)\n";
foreach ( $settlement_notes as $sn ) {
	echo "  -> Note: " . trim( $sn->content ) . "\n";
}

// 6. Test Stale Lock Expiry & Token Safety
echo "\nTesting MySQL Lock Expiry & Token Replacement Protection...\n";
$lock_key = 'despace_bacs_lock_' . $order_id;
$token_a  = 'token_stale_123';

// Manually insert an expired lock in wp_options table
$expired_payload = json_encode( array(
	'token'      => $token_a,
	'expires_at' => time() - 5,
) );
global $wpdb;
$wpdb->query( $wpdb->prepare(
	"REPLACE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, %s)",
	$lock_key,
	$expired_payload,
	'no'
) );
echo "Simulated expired lock written to MySQL options table (token: $token_a, expires: -5s).\n";

// Fresh worker tries to acquire lock - should detect expired lock and acquire replacement
$lock_b = PaymentStatusGuard::acquire_bacs_lock( $order_id, 30 );
$acquired_b = ! is_wp_error( $lock_b );
echo "Acquire lock on expired lock: " . ( $acquired_b ? "SUCCESS (token: {$lock_b['token']})" : "FAILED" ) . "\n";

// Stale worker A tries to release lock with its old stale token - MUST BE BLOCKED
$stale_lock_a = array(
	'key'     => $lock_key,
	'token'   => $token_a,
	'payload' => $expired_payload,
);
$release_a = PaymentStatusGuard::release_bacs_lock( $stale_lock_a );
echo "Stale worker release attempt with old token/payload: " . ( $release_a ? "UNSAFE_RELEASED (BUG)" : "BLOCKED_SAFE (PASS)" ) . "\n";

// Legitimate worker B releases lock with active token
$release_b = PaymentStatusGuard::release_bacs_lock( $lock_b );
echo "Legitimate worker release attempt with active token: " . ( $release_b ? "SUCCESS_RELEASED (PASS)" : "FAILED" ) . "\n";

// Confirm lock option cleaned up in MySQL
$option_after = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $lock_key ) );
echo "Lock key in MySQL after cleanup: " . ( null === $option_after ? "CLEANED_UP (PASS)" : "DIRTY: $option_after" ) . "\n";

// Cleanup test order
$fresh_order->delete( true );
delete_option( $lock_key );

if ( 1 === $success_count && $fresh_order->is_paid() && 1 === count( $settlement_notes ) && ! $release_a && $release_b && null === $option_after ) {
	echo "\n========================================================================\n";
	echo ">>> BACS CONCURRENCY & MYSQL LOCK TEST: ALL ASSERTIONS PASSED (100%) <<<\n";
	echo "========================================================================\n";
} else {
	echo "\n>>> BACS CONCURRENCY & MYSQL LOCK TEST: FAILED ASSERTIONS <<<\n";
	exit( 1 );
}
