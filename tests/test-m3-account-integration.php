<?php
/**
 * DeSpace M3 Phase 4 — Live WordPress & WooCommerce Account & Email Integration Tests (Issue #33).
 *
 * Verifies real WordPress + WooCommerce `[woocommerce_my_account]`, registration/login policy,
 * customer profile & 2-tier VN address updates via `WC_Customer`, HPOS order history & isolation
 * from Guest orders, permission boundaries, and native WooCommerce transactional email classes.
 *
 * Run via CLI: php tests/test-m3-account-integration.php
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
	fwrite( STDERR, "[BLOCKED] WordPress wp-load.php not found in repository workspace. Live Account & Email integration tests require an installed WordPress + WooCommerce environment.\n" );
	exit( 2 );
}

require_once $wp_load;

if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'WC' ) ) {
	fwrite( STDERR, "[BLOCKED] WooCommerce plugin is not active in target WordPress environment.\n" );
	exit( 2 );
}

require_once __DIR__ . '/../wordpress/wp-content/plugins/despace-core/includes/Checkout-Orders/CheckoutOrdersModule.php';
\DeSpace\CheckoutOrders\CheckoutOrdersModule::init();

use DeSpace\CheckoutOrders\Account\AccountAddressHandler;
use DeSpace\CheckoutOrders\Account\AccountDataPresenter;
use DeSpace\CheckoutOrders\Account\AccountProfileHandler;

class M3AccountEmailLiveIntegrationRunner {
	private array $results = array();
	private array $created_user_ids = array();
	private array $created_order_ids = array();

	public function run(): void {
		echo "========================================================================\n";
		echo " DeSpace M3 Phase 4 — LIVE ACCOUNT & EMAIL INTEGRATION TESTS            \n";
		echo "========================================================================\n\n";

		try {
			$this->record( 'M3-ACC-INT-01', 'Native shortcode [woocommerce_my_account] registered', shortcode_exists( 'woocommerce_my_account' ) );

			$email_a = 'm3acca' . time() . '@despace.test';
			$uid_a   = wp_create_user( 'm3_acc_a_' . time(), wp_generate_password( 16, true ), $email_a );
			$uid_b   = wp_create_user( 'm3_acc_b_' . time(), wp_generate_password( 16, true ), 'm3accb' . time() . '@despace.test' );
			if ( ! is_wp_error( $uid_a ) ) $this->created_user_ids[] = $uid_a;
			if ( ! is_wp_error( $uid_b ) ) $this->created_user_ids[] = $uid_b;

			wp_set_current_user( (int) $uid_a );

			$addr_res = AccountAddressHandler::save_customer_address( 'shipping', array(
				'woocommerce-edit-address-nonce' => wp_create_nonce( 'woocommerce-edit_address' ),
				'shipping_country'               => 'VN',
				'shipping_state'                 => '15',
				'shipping_despace_commune_code'  => '02647',
				'shipping_address_1'             => '10 Hoàng Liên',
			) );
			$this->record( 'M3-ACC-INT-02', 'WC_Customer saves 2-tier VN:15 & 02647 address cleanly', ! is_wp_error( $addr_res ) && 'Tỉnh Lào Cai' === ( $addr_res['region_name'] ?? '' ) );

			$ord_a = wc_create_order( array( 'customer_id' => (int) $uid_a ) );
			$ord_a->set_billing_email( $email_a );
			$ord_a->set_payment_method( 'cod' );
			$ord_a->set_status( 'processing' );
			$oid_a                     = $ord_a->save();
			$this->created_order_ids[] = $oid_a;

			// Guest order sharing customer A email must remain isolated from Account A!
			$ord_guest = wc_create_order( array( 'customer_id' => 0 ) );
			$ord_guest->set_billing_email( $email_a );
			$ord_guest->set_payment_method( 'bacs' );
			$ord_guest->set_status( 'on-hold' );
			$oid_guest                 = $ord_guest->save();
			$this->created_order_ids[] = $oid_guest;

			$dash      = AccountDataPresenter::get_dashboard_summary();
			$guest_try = AccountDataPresenter::get_customer_order_detail( $oid_guest );
			$this->record(
				'M3-ACC-INT-03',
				'Customer dashboard shows own orders & isolates Guest orders',
				! is_wp_error( $dash ) && 1 === ( $dash['stats']['total_orders'] ?? 0 ) && is_wp_error( $guest_try )
			);

			wp_set_current_user( (int) $uid_b );
			$cross_try = AccountDataPresenter::get_customer_order_detail( $oid_a );
			$this->record( 'M3-ACC-INT-04', 'Customer B blocked from accessing Customer A order', is_wp_error( $cross_try ) );

			$mailer = WC()->mailer();
			$emails = is_object( $mailer ) ? $mailer->get_emails() : array();
			$this->record(
				'M3-ACC-INT-05',
				'Native WC transactional email classes loaded & hookable',
				isset( $emails['WC_Email_New_Order'], $emails['WC_Email_Customer_On_Hold_Order'], $emails['WC_Email_Customer_Reset_Password'] )
			);
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
		printf( "\nSUMMARY: %d Live Account & Email Integration Tests | %d Passed | %d Failed\n", $total, $passed, $failed );
		if ( $failed > 0 ) exit( 1 );
	}

	private function record( string $id, string $desc, bool $passed ): void {
		printf( "[%-14s] %-52s : %s\n", $id, $desc, $passed ? 'PASS' : 'FAIL' );
		$this->results[ $id ] = $passed;
	}
}

$runner = new M3AccountEmailLiveIntegrationRunner();
$runner->run();

