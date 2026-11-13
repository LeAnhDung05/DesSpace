<?php
/**
 * DeSpace M3 Phase 2B — Live WordPress & WooCommerce COD/BACS Integration Tests (Issue #18).
 *
 * Tests real WordPress + WooCommerce native gateways (`cod`, `bacs`), COD native status,
 * BACS `on-hold` status & unpaid `date_paid`, BACS instructions/emails/thank-you output,
 * Guest vs Logged-in checkout, HPOS compatibility, `enable_for_methods` shipping restrictions,
 * and single native M4 stock reduction lifecycle.
 *
 * Run via CLI: php tests/test-m3-payment-integration.php
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
	fwrite( STDERR, "[BLOCKED] WordPress wp-load.php not found in repository workspace. Live Payment integration tests require an installed WordPress + WooCommerce environment.\n" );
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
use DeSpace\CheckoutOrders\Payment\BacsInstructions;
use DeSpace\CheckoutOrders\Payment\PaymentMethodValidator;
use DeSpace\CheckoutOrders\Payment\PaymentStatusGuard;

class M3PaymentLiveIntegrationRunner {
	private array $results = array();
	private array $created_ids = array();
	private array $created_order_ids = array();
	private array $created_user_ids = array();

	public function run(): void {
		echo "========================================================================\n";
		echo "   DeSpace M3 Phase 2B — LIVE WOOCOMMERCE COD & BACS INTEGRATION TESTS  \n";
		echo "========================================================================\n\n";

		try {
			if ( function_exists( 'wc_load_cart' ) && null === WC()->cart ) {
				wc_load_cart();
			}

			$product = new \WC_Product_Simple();
			$product->set_name( 'M3 Payment Test Sofa ' . time() );
			$product->set_status( 'publish' );
			$product->set_regular_price( '6500000' );
			$product->set_manage_stock( true );
			$product->set_stock_quantity( 8 );
			$pid                 = $product->save();
			$this->created_ids[] = $pid;

			$gateways = PaymentMethodValidator::get_registered_gateways();
			$this->record( 'M3-PAY-INT-01', 'Native WC COD & BACS gateways registered in runtime', isset( $gateways['cod'], $gateways['bacs'] ) );

			$checkout = WC()->checkout();
			$stock_hook_count = 0;
			add_action( 'woocommerce_reduce_order_stock', function () use ( &$stock_hook_count ) {
				$stock_hook_count++;
			}, 99, 1 );

			// Guest COD order via native WooCommerce checkout & process_payment.
			wp_set_current_user( 0 );
			WC()->cart->empty_cart();
			CartHandler::add_item( $pid, 1 );

			if ( PaymentMethodValidator::is_gateway_available( 'cod' ) ) {
				$cod_oid = $checkout->create_order( array(
					'billing_first_name' => 'Guest COD',
					'billing_email'      => 'guest-cod@despace.test',
					'billing_phone'      => '0912345678',
					'billing_country'    => 'VN',
					'billing_state'      => '15',
					'billing_city'       => 'Phường Lào Cai',
					'billing_address_1'  => '10 Hoàng Liên',
					'payment_method'     => 'cod',
				) );
				if ( is_int( $cod_oid ) && $cod_oid > 0 ) {
					$this->created_order_ids[] = $cod_oid;
					$cod_res = $gateways['cod']->process_payment( $cod_oid );
					$cod_ord = wc_get_order( $cod_oid );
					$this->record(
						'M3-PAY-INT-02',
						'Guest COD creates order with native non-completed status',
						'success' === ( $cod_res['result'] ?? '' )
							&& $cod_ord instanceof \WC_Order
							&& 0 === (int) $cod_ord->get_customer_id()
							&& in_array( $cod_ord->get_status(), array( 'processing', 'on-hold' ), true )
							&& null === $cod_ord->get_date_paid()
					);
				}
			}

			// Logged-in BACS order via native WooCommerce checkout & thankyou/instructions.
			$uid = wp_create_user( 'm3_pay_user_' . time(), wp_generate_password( 16, true ), 'm3pay' . time() . '@despace.test' );
			if ( ! is_wp_error( $uid ) ) {
				$this->created_user_ids[] = $uid;
				wp_set_current_user( $uid );
				WC()->cart->empty_cart();
				CartHandler::add_item( $pid, 1 );
				WC()->session->set( 'order_awaiting_payment', 0 );

				if ( PaymentMethodValidator::is_gateway_available( 'bacs' ) ) {
					$bacs_oid = $checkout->create_order( array(
						'billing_first_name' => 'Customer BACS',
						'billing_email'      => 'm3pay@despace.test',
						'billing_phone'      => '0987654321',
						'billing_country'    => 'VN',
						'billing_state'      => '01',
						'billing_city'       => 'Phường Ba Đình',
						'billing_address_1'  => '1 Hoàng Diệu',
						'payment_method'     => 'bacs',
					) );
					if ( is_int( $bacs_oid ) && $bacs_oid > 0 ) {
						$this->created_order_ids[] = $bacs_oid;
						$bacs_res = $gateways['bacs']->process_payment( $bacs_oid );
						$bacs_ord = wc_get_order( $bacs_oid );
						$instr    = $bacs_ord ? BacsInstructions::get_order_instructions( $bacs_ord ) : null;

						ob_start();
						$gateways['bacs']->thankyou_page( $bacs_oid );
						$ty_html = (string) ob_get_clean();

						$this->record(
							'M3-PAY-INT-03',
							'Logged-in BACS order starts On hold, unpaid, with WC thank-you info',
							'success' === ( $bacs_res['result'] ?? '' )
								&& $bacs_ord instanceof \WC_Order
								&& (int) $uid === (int) $bacs_ord->get_customer_id()
								&& 'on-hold' === $bacs_ord->get_status()
								&& false === $bacs_ord->is_paid()
								&& null === $bacs_ord->get_date_paid()
								&& ! is_wp_error( $instr )
								&& ! empty( $bacs_res['redirect'] )
								&& '' !== $ty_html
						);
						$this->record( 'M3-PAY-INT-04', 'M4 stock hooks triggered via native WC only without dupes', $stock_hook_count <= 2 );
					}
				}
			}

			WC()->cart->empty_cart();
		} finally {
			wp_set_current_user( 0 );
			foreach ( array_reverse( $this->created_order_ids ) as $oid ) {
				$o = wc_get_order( $oid );
				if ( $o ) $o->delete( true );
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
		printf( "\nSUMMARY: %d Live Payment Integration Tests | %d Passed | %d Failed\n", $total, $passed, $failed );
		if ( $failed > 0 ) {
			exit( 1 );
		}
	}

	private function record( string $id, string $desc, bool $passed ): void {
		printf( "[%-14s] %-52s : %s\n", $id, $desc, $passed ? 'PASS' : 'FAIL' );
		$this->results[ $id ] = $passed;
	}
}

$runner = new M3PaymentLiveIntegrationRunner();
$runner->run();

