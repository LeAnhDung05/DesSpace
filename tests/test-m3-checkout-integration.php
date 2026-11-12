<?php
/**
 * DeSpace M3 Phase 2A — Live WordPress & WooCommerce Checkout Integration Tests (Issue #17).
 *
 * Verifies real WordPress + WooCommerce Classic Checkout lifecycle (`WC()->checkout()`),
 * guest vs logged-in checkout, cart + coupon + variable products, Shipping Zones (VN:15 Lào Cai
 * & Postcode), HPOS order metadata persistence (`OrderMetaSaver`), duplicate-order prevention,
 * and M4 inventory hook boundaries.
 *
 * Run via CLI: php tests/test-m3-checkout-integration.php
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
	fwrite( STDERR, "[BLOCKED] WordPress wp-load.php not found in repository workspace. Live Checkout integration tests require an installed WordPress + WooCommerce environment.\n" );
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
use DeSpace\CheckoutOrders\Checkout\CheckoutValidator;
use DeSpace\CheckoutOrders\Checkout\OrderMetaSaver;

class M3CheckoutLiveIntegrationRunner {
	private array $results = array();
	private array $created_ids = array();
	private array $created_order_ids = array();
	private array $created_zone_ids = array();
	private array $created_user_ids = array();

	public function run(): void {
		echo "========================================================================\n";
		echo "  DeSpace M3 Phase 2A — LIVE WOOCOMMERCE CHECKOUT INTEGRATION TESTS     \n";
		echo "========================================================================\n\n";

		try {
			if ( function_exists( 'wc_load_cart' ) && null === WC()->cart ) {
				wc_load_cart();
			}

			// Fixture: Simple & Variable Products, Coupon, Shipping Zones (VN:15 Lào Cai + Postcode).
			$simple = new \WC_Product_Simple();
			$simple->set_name( 'M3 Checkout Simple Chair ' . time() );
			$simple->set_status( 'publish' );
			$simple->set_regular_price( '4500000' );
			$simple->set_manage_stock( true );
			$simple->set_stock_quantity( 10 );
			$simple_id           = $simple->save();
			$this->created_ids[] = $simple_id;

			$variable = new \WC_Product_Variable();
			$variable->set_name( 'M3 Checkout Variable Table ' . time() );
			$variable->set_status( 'publish' );
			$var_parent_id       = $variable->save();
			$this->created_ids[] = $var_parent_id;

			$variation = new \WC_Product_Variation();
			$variation->set_parent_id( $var_parent_id );
			$variation->set_status( 'publish' );
			$variation->set_regular_price( '8200000' );
			$variation->set_manage_stock( true );
			$variation->set_stock_quantity( 5 );
			$variation_id        = $variation->save();
			$this->created_ids[] = $variation_id;

			$coupon = new \WC_Coupon();
			$coupon_code = 'M3CHK' . time();
			$coupon->set_code( $coupon_code );
			$coupon->set_discount_type( 'percent' );
			$coupon->set_amount( 10 );
			$coupon->set_usage_limit( 5 );
			$coupon_id           = $coupon->save();
			$this->created_ids[] = $coupon_id;

			$zone_lc = new \WC_Shipping_Zone();
			$zone_lc->set_zone_name( 'M3 Test Lao Cai VN:15 ' . time() );
			$zone_lc->add_location( 'VN:15', 'state' );
			$zone_lc->save();
			$rate_instance_lc         = $zone_lc->add_shipping_method( 'flat_rate' );
			$this->created_zone_ids[] = $zone_lc->get_id();
			$rate_id_lc               = 'flat_rate:' . $rate_instance_lc;

			$zone_pc = new \WC_Shipping_Zone();
			$zone_pc->set_zone_name( 'M3 Test Postcode Hub ' . time() );
			$zone_pc->add_location( 'VN', 'country' );
			$zone_pc->add_location( '331000', 'postcode' );
			$zone_pc->save();
			$rate_instance_pc         = $zone_pc->add_shipping_method( 'flat_rate' );
			$this->created_zone_ids[] = $zone_pc->get_id();
			$rate_id_pc               = 'flat_rate:' . $rate_instance_pc;

			// 1. Live Guest Checkout + VN:15 Lào Cai + Coupon + Variable product + HPOS Metadata + Native stock hook count.
			wp_set_current_user( 0 );
			WC()->cart->empty_cart();
			CartHandler::add_item( $simple_id, 1 );
			CartHandler::add_item( $var_parent_id, 1, $variation_id );
			CouponValidator::apply_coupon( $coupon_code );

			$stock_hook_count = 0;
			$stock_counter_cb = function () use ( &$stock_hook_count ) {
				$stock_hook_count++;
			};
			add_action( 'woocommerce_reduce_order_stock', $stock_counter_cb, 99, 1 );

			$guest_posted = array(
				'billing_first_name'           => 'Khách Lẻ',
				'billing_last_name'            => 'Nguyễn',
				'billing_email'                => 'guest-checkout@despace.test',
				'billing_phone'                => '0912345678',
				'billing_country'              => 'VN',
				'billing_state'                => '15',
				'billing_city'                 => 'Phường Lào Cai',
				'billing_despace_commune_code' => '02647',
				'billing_address_1'            => '10 Hoàng Liên',
				'shipping_method'              => array( 0 => $rate_id_lc ),
				'payment_method'               => 'cod',
			);

			$errors = new \WP_Error();
			CheckoutValidator::validate_checkout_submission( $guest_posted, $errors );
			$this->record( 'M3-CHK-INT-01', 'Guest checkout validation passes with cart/coupon/VN:15', ! $errors->has_errors() );

			$checkout = WC()->checkout();
			$order_id = $checkout->create_order( $guest_posted );
			$valid_id = is_int( $order_id ) && $order_id > 0;
			if ( $valid_id ) {
				$this->created_order_ids[] = $order_id;
				$order = wc_get_order( $order_id );
				$meta  = $order ? OrderMetaSaver::read_order_metadata( $order ) : array();
				$this->record(
					'M3-CHK-INT-02',
					'HPOS order created & DeSpace 2-tier metadata persisted',
					$order instanceof \WC_Order
						&& 0 === (int) $order->get_customer_id()
						&& '15' === ( $meta['shipping']['region_code'] ?? '' )
						&& '02647' === ( $meta['shipping']['commune_code'] ?? '' )
						&& 'Tỉnh Lào Cai' === ( $meta['shipping']['region_name'] ?? '' )
				);

				// Retry same cart hash pending order should resume rather than creating duplicate orders.
				WC()->session->set( 'order_awaiting_payment', $order_id );
				$resumed_id = $checkout->create_order( $guest_posted );
				$this->record( 'M3-CHK-INT-03', 'Native cart_hash prevents duplicate orders on retry', (int) $resumed_id === (int) $order_id );

				// Ensure CheckoutValidator did not fire manual stock reduction before WC order lifecycle.
				$this->record( 'M3-CHK-INT-04', 'Zero duplicate or premature stock reduction from M3', $stock_hook_count <= 1 );
			} else {
				$this->record( 'M3-CHK-INT-02', 'HPOS order created & DeSpace 2-tier metadata persisted', false );
				$this->record( 'M3-CHK-INT-03', 'Native cart_hash prevents duplicate orders on retry', false );
				$this->record( 'M3-CHK-INT-04', 'Zero duplicate or premature stock reduction from M3', false );
			}

			// 2. Postcode Shipping Zone check + Logged-in Customer checkout check.
			$user_id = wp_create_user( 'm3_chk_user_' . time(), wp_generate_password( 16, true ), 'm3user' . time() . '@despace.test' );
			if ( ! is_wp_error( $user_id ) ) {
				$this->created_user_ids[] = $user_id;
				wp_set_current_user( $user_id );
				WC()->cart->empty_cart();
				CartHandler::add_item( $simple_id, 1 );
				WC()->session->set( 'order_awaiting_payment', 0 );

				$pc_posted = array_merge( $guest_posted, array(
					'billing_email'    => 'm3user@despace.test',
					'billing_postcode' => '331000',
					'shipping_method'  => array( 0 => $rate_id_pc ),
				) );
				$pc_check = CheckoutValidator::validate_checkout( $pc_posted );
				$cust_oid = $checkout->create_order( $pc_posted );
				if ( is_int( $cust_oid ) && $cust_oid > 0 ) {
					$this->created_order_ids[] = $cust_oid;
				}
				$cust_order = is_int( $cust_oid ) ? wc_get_order( $cust_oid ) : null;
				$this->record(
					'M3-CHK-INT-05',
					'Logged-in checkout & Postcode zone validated via WC',
					! is_wp_error( $pc_check )
						&& 'customer' === ( $pc_check['customer_context']['mode'] ?? '' )
						&& $cust_order instanceof \WC_Order
						&& (int) $user_id === (int) $cust_order->get_customer_id()
				);
			}

			WC()->cart->empty_cart();
		} finally {
			wp_set_current_user( 0 );
			foreach ( array_reverse( $this->created_order_ids ) as $oid ) {
				$o = wc_get_order( $oid );
				if ( $o ) {
					$o->delete( true );
				}
			}
			foreach ( $this->created_zone_ids as $zid ) {
				$z = \WC_Shipping_Zones::get_zone( $zid );
				if ( $z ) {
					$z->delete();
				}
			}
			foreach ( array_reverse( $this->created_ids ) as $id ) {
				wp_delete_post( $id, true );
			}
			foreach ( $this->created_user_ids as $uid ) {
				require_once ABSPATH . 'wp-admin/includes/user.php';
				wp_delete_user( $uid );
			}
		}

		$total  = count( $this->results );
		$passed = count( array_filter( $this->results ) );
		$failed = $total - $passed;
		printf( "\nSUMMARY: %d Live Checkout Integration Tests | %d Passed | %d Failed\n", $total, $passed, $failed );
		if ( $failed > 0 ) {
			exit( 1 );
		}
	}

	private function record( string $id, string $desc, bool $passed ): void {
		printf( "[%-14s] %-52s : %s\n", $id, $desc, $passed ? 'PASS' : 'FAIL' );
		$this->results[ $id ] = $passed;
	}
}

$runner = new M3CheckoutLiveIntegrationRunner();
$runner->run();

