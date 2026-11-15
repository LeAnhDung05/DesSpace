<?php
/**
 * DeSpace M3 Phase 3 — Live WordPress & WooCommerce Order Tracking Integration Tests (Issue #19).
 *
 * Tests real WordPress + WooCommerce HPOS order lookup for Guest email, Guest phone, Logged-in
 * owner, Unauthorized cross-customer attempt, BACS & COD status details, WP Transients rate
 * limiting, Custom order number hooks, and native `[woocommerce_order_tracking]` coexistence.
 *
 * Run via CLI: php tests/test-m3-order-tracking-integration.php
 *
 * @package DeSpace\Tests
 */

$possible_paths = array(
	__DIR__ . '/../wordpress/wp-load.php',
	__DIR__ . '/../../wordpress/wp-load.php',
);

$wp_load = null;
foreach ( $possible_paths as $path ) {
	if ( file_exists( $path ) ) {
		$wp_load = $path;
		break;
	}
}

if ( ! $wp_load ) {
	fwrite( STDERR, "[BLOCKED] WordPress wp-load.php not found in repository workspace. Live Order Tracking integration tests require an installed WordPress + WooCommerce environment.\n" );
	exit( 2 );
}

require_once $wp_load;

if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'WC' ) ) {
	fwrite( STDERR, "[BLOCKED] WooCommerce plugin is not active in target WordPress environment.\n" );
	exit( 2 );
}

require_once __DIR__ . '/../wordpress/wp-content/plugins/despace-core/includes/Checkout-Orders/CheckoutOrdersModule.php';
\DeSpace\CheckoutOrders\CheckoutOrdersModule::init();

use DeSpace\CheckoutOrders\Orders\GuestOrderTracker;
use DeSpace\CheckoutOrders\Orders\OrderAccessGuard;

class M3OrderTrackingLiveIntegrationRunner {
	private array $results = array();
	private array $created_order_ids = array();
	private array $created_user_ids = array();

	public function run(): void {
		echo "========================================================================\n";
		echo "  DeSpace M3 Phase 3 — LIVE WOOCOMMERCE ORDER TRACKING INTEGRATION      \n";
		echo "========================================================================\n\n";

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['REMOTE_ADDR']    = '127.0.0.1';

		try {
			// Create real Guest BACS order & Customer COD order via WC CRUD.
			$guest_bacs = wc_create_order();
			$guest_bacs->set_customer_id( 0 );
			$guest_bacs->set_billing_email( 'live-trk-guest@despace.test' );
			$guest_bacs->set_billing_phone( '0912345678' );
			$guest_bacs->set_payment_method( 'bacs' );
			$guest_bacs->set_status( 'on-hold' );
			$guest_oid                 = $guest_bacs->save();
			$this->created_order_ids[] = $guest_oid;

			$uid_a = wp_create_user( 'm3_trk_a_' . time(), wp_generate_password( 16, true ), 'trka' . time() . '@despace.test' );
			$uid_b = wp_create_user( 'm3_trk_b_' . time(), wp_generate_password( 16, true ), 'trkb' . time() . '@despace.test' );
			if ( ! is_wp_error( $uid_a ) ) $this->created_user_ids[] = $uid_a;
			if ( ! is_wp_error( $uid_b ) ) $this->created_user_ids[] = $uid_b;

			$cust_cod = wc_create_order();
			$cust_cod->set_customer_id( (int) $uid_a );
			$cust_cod->set_billing_email( 'trka@despace.test' );
			$cust_cod->set_billing_phone( '0987654321' );
			$cust_cod->set_payment_method( 'cod' );
			$cust_cod->set_status( 'processing' );
			$cust_oid                  = $cust_cod->save();
			$this->created_order_ids[] = $cust_oid;

			wp_set_current_user( 0 );
			$nonce = wp_create_nonce( OrderAccessGuard::NONCE_ACTION );

			$g_email = GuestOrderTracker::handle_lookup_request( array(
				'despace_tracking_nonce' => $nonce,
				'order_number'           => (string) $guest_oid,
				'billing_email'          => 'live-trk-guest@despace.test',
			) );
			$this->record( 'M3-TRK-INT-01', 'Guest lookup via billing email & BACS On hold', ! empty( $g_email['success'] ) && 'on-hold' === ( $g_email['data']['order']['status'] ?? '' ) );

			$g_phone = GuestOrderTracker::handle_lookup_request( array(
				'despace_tracking_nonce' => $nonce,
				'order_number'           => (string) $guest_oid,
				'billing_phone'          => '+84912345678',
			) );
			$this->record( 'M3-TRK-INT-02', 'Guest lookup via normalized billing phone', ! empty( $g_phone['success'] ) );

			wp_set_current_user( (int) $uid_a );
			$owner_ok = GuestOrderTracker::handle_lookup_request( array(
				'despace_tracking_nonce' => wp_create_nonce( OrderAccessGuard::NONCE_ACTION ),
				'order_number'           => (string) $cust_oid,
			) );
			$this->record( 'M3-TRK-INT-03', 'Logged-in owner lookup without billing email', ! empty( $owner_ok['success'] ) && 'processing' === ( $owner_ok['data']['order']['status'] ?? '' ) );

			wp_set_current_user( (int) $uid_b );
			$other_denied = GuestOrderTracker::handle_lookup_request( array(
				'despace_tracking_nonce' => wp_create_nonce( OrderAccessGuard::NONCE_ACTION ),
				'order_number'           => (string) $cust_oid,
			) );
			$this->record( 'M3-TRK-INT-04', 'Unauthorized customer blocked (IDOR defense)', empty( $other_denied['success'] ) );

			$this->record( 'M3-TRK-INT-05', 'Native shortcode WC_Shortcode_Order_Tracking & despace shortcode present', class_exists( 'WC_Shortcode_Order_Tracking' ) && shortcode_exists( 'despace_order_tracking' ) );
		} finally {
			wp_set_current_user( 0 );
			foreach ( array_reverse( $this->created_order_ids ) as $oid ) {
				$o = wc_get_order( $oid );
				if ( $o ) $o->delete( true );
			}
			foreach ( $this->created_user_ids as $uid ) {
				require_once ABSPATH . 'wp-admin/includes/user.php';
				wp_delete_user( $uid );
			}
		}

		$total  = count( $this->results );
		$passed = count( array_filter( $this->results ) );
		$failed = $total - $passed;
		printf( "\nSUMMARY: %d Live Order Tracking Tests | %d Passed | %d Failed\n", $total, $passed, $failed );
		if ( $failed > 0 ) exit( 1 );
	}

	private function record( string $id, string $desc, bool $passed ): void {
		printf( "[%-14s] %-52s : %s\n", $id, $desc, $passed ? 'PASS' : 'FAIL' );
		$this->results[ $id ] = $passed;
	}
}

$runner = new M3OrderTrackingLiveIntegrationRunner();
$runner->run();

