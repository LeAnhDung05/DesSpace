<?php
/**
 * DeSpace M3 Phase 1B-A — Live WordPress & WooCommerce Coupon Integration Tests.
 *
 * Executes against an active WordPress + WooCommerce database runtime.
 * Run via CLI: php tests/test-m3-coupon-integration.php
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
	fwrite( STDERR, "[BLOCKED] WordPress wp-load.php not found in repository workspace. Integration test requires an installed WordPress + WooCommerce environment.\n" );
	exit( 2 );
}

require_once $wp_load;

if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'WC' ) ) {
	fwrite( STDERR, "[BLOCKED] WooCommerce plugin is not active in target WordPress environment.\n" );
	exit( 2 );
}

require_once __DIR__ . '/../wordpress/wp-content/plugins/despace-core/includes/Checkout-Orders/CheckoutOrdersModule.php';
\DeSpace\CheckoutOrders\CheckoutOrdersModule::init();

use DeSpace\CheckoutOrders\Cart\CartHandler;
use DeSpace\CheckoutOrders\Cart\CouponValidator;

class M3CouponLiveIntegrationRunner {

	private array $results = array();
	private array $created_ids = array();

	public function run(): void {
		echo "========================================================================\n";
		echo "    DeSpace M3 Phase 1B-A — LIVE WOOCOMMERCE COUPON INTEGRATION TESTS   \n";
		echo "========================================================================\n\n";

		try {
			if ( function_exists( 'wc_load_cart' ) && null === WC()->cart ) {
				wc_load_cart();
			}

			$product = new \WC_Product_Simple();
			$product->set_name( 'M3 Coupon Test Cabinet ' . time() );
			$product->set_status( 'publish' );
			$product->set_regular_price( '5000000' );
			$product->set_manage_stock( true );
			$product->set_stock_quantity( 5 );
			$product_id          = $product->save();
			$this->created_ids[] = $product_id;

			$valid_code = 'm3test10_' . time();
			$valid_cpn  = new \WC_Coupon();
			$valid_cpn->set_code( $valid_code );
			$valid_cpn->set_discount_type( 'percent' );
			$valid_cpn->set_amount( 10 );
			$valid_cpn->set_minimum_amount( '4000000' );
			$this->created_ids[] = $valid_cpn->save();

			$expired_code = 'm3exp_' . time();
			$expired_cpn  = new \WC_Coupon();
			$expired_cpn->set_code( $expired_code );
			$expired_cpn->set_discount_type( 'fixed_cart' );
			$expired_cpn->set_amount( 500000 );
			$expired_cpn->set_date_expires( time() - 86400 );
			$this->created_ids[] = $expired_cpn->save();

			global $wpdb;
			$inv_table     = $wpdb->prefix . 'despace_inventory_logs';
			$has_inv_table = ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $inv_table ) ) === $inv_table );
			$inv_pre       = $has_inv_table ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$inv_table}" ) : 0;

			WC()->cart->empty_cart();
			CartHandler::add_item( $product_id, 1 );

			$r_apply = CouponValidator::handle_apply_request( array(
				'nonce'           => wp_create_nonce( CartHandler::NONCE_ACTION ),
				'coupon_code'     => $valid_code,
				'discount_amount' => 4999999,
			) );
			$this->record(
				'M3-CPN-INT-01',
				'Live apply valid coupon & ignore spoofed discount',
				! empty( $r_apply['success'] ) && 500000.0 === (float) $r_apply['data']['cart']['discount_total']
			);

			$r_expired = CouponValidator::apply_coupon( $expired_code );
			$this->record( 'M3-CPN-INT-02', 'Live reject expired coupon via WC_Discounts', is_wp_error( $r_expired ) );

			$r_dup = CouponValidator::apply_coupon( $valid_code );
			$this->record( 'M3-CPN-INT-03', 'Live reject duplicate coupon', is_wp_error( $r_dup ) );

			$r_remove = CouponValidator::remove_coupon( $valid_code );
			$this->record(
				'M3-CPN-INT-04',
				'Live remove coupon & recalculate totals',
				! is_wp_error( $r_remove ) && 0.0 === (float) $r_remove['cart']['discount_total']
			);

			$live_product = wc_get_product( $product_id );
			$inv_post     = $has_inv_table ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$inv_table}" ) : 0;
			$this->record(
				'M3-CPN-INT-05',
				'Stock untouched & zero M4 inventory log writes',
				5 === (int) $live_product->get_stock_quantity() && $inv_pre === $inv_post
			);

			WC()->cart->empty_cart();
		} finally {
			foreach ( array_reverse( $this->created_ids ) as $id ) {
				wp_delete_post( $id, true );
			}
		}

		$total  = count( $this->results );
		$passed = count( array_filter( $this->results ) );
		$failed = $total - $passed;
		printf( "\nSUMMARY: %d Live Coupon Tests | %d Passed | %d Failed\n", $total, $passed, $failed );
		if ( $failed > 0 ) {
			exit( 1 );
		}
	}

	private function record( string $id, string $desc, bool $passed ): void {
		printf( "[%-14s] %-50s : %s\n", $id, $desc, $passed ? 'PASS' : 'FAIL' );
		$this->results[ $id ] = $passed;
	}
}

$runner = new M3CouponLiveIntegrationRunner();
$runner->run();

