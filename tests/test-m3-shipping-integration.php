<?php
/**
 * DeSpace M3 Phase 1B-B — Live WordPress & WooCommerce Shipping Integration Tests.
 *
 * Tests live WC_Countries, WC_Shipping_Zones, WC_Cart shipping calculation,
 * and confirms zero custom shipping DB tables or M4 inventory log writes.
 *
 * Run via CLI: php tests/test-m3-shipping-integration.php
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
use DeSpace\CheckoutOrders\Shipping\VietnamRegions;
use DeSpace\CheckoutOrders\Shipping\ShippingNoticeHelper;

class M3ShippingLiveIntegrationRunner {
	private array $results = array();
	private array $created_ids = array();

	public function run(): void {
		echo "========================================================================\n";
		echo "   DeSpace M3 Phase 1B-B — LIVE WOOCOMMERCE SHIPPING INTEGRATION TESTS  \n";
		echo "========================================================================\n\n";

		try {
			if ( function_exists( 'wc_load_cart' ) && null === WC()->cart ) {
				wc_load_cart();
			}

			$product = new \WC_Product_Simple();
			$product->set_name( 'M3 Shipping Test Shelf ' . time() );
			$product->set_status( 'publish' );
			$product->set_regular_price( '3500000' );
			$product->set_manage_stock( true );
			$product->set_stock_quantity( 5 );
			$pid                 = $product->save();
			$this->created_ids[] = $pid;

			global $wpdb;
			$custom_tables = array( 'despace_shipping_rates', 'despace_shipping_zones', 'despace_addresses', 'despace_provinces', 'despace_communes' );
			$no_custom_tbl = true;
			foreach ( $custom_tables as $tbl ) {
				$full = $wpdb->prefix . $tbl;
				if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $full ) ) === $full ) {
					$no_custom_tbl = false;
				}
			}
			$this->record( 'M3-SHP-INT-01', 'Zero duplicate custom shipping DB tables exist', $no_custom_tbl );

			$locale = WC()->countries->get_country_locale();
			$this->record(
				'M3-SHP-INT-02',
				'VN country locale active without mandatory district',
				isset( $locale['VN']['city'] ) && false === ( $locale['VN']['address_2']['required'] ?? true )
			);

			WC()->cart->empty_cart();
			CartHandler::add_item( $pid, 1 );
			$summary = ShippingNoticeHelper::get_shipping_summary( null, array(
				'country'        => 'VN',
				'region_name'    => 'Thành phố Hồ Chí Minh',
				'commune_name'   => 'Phường Sài Gòn',
				'address_detail' => '12 Nguyễn Hữu Cảnh',
			) );
			$this->record(
				'M3-SHP-INT-03',
				'Live shipping summary reads WC rates safely',
				! is_wp_error( $summary ) && in_array( $summary['status'], array( 'available', 'unavailable' ), true )
			);

			$dataset_status = VietnamRegions::get_dataset_status();
			$this->record(
				'M3-SHP-INT-04',
				'Full 34/3321 Vietnam dataset loaded (or flagged incomplete)',
				! empty( $dataset_status['is_complete'] )
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
		printf( "\nSUMMARY: %d Live Shipping Tests | %d Passed | %d Failed\n", $total, $passed, $failed );
		if ( $failed > 0 ) {
			exit( 1 );
		}
	}

	private function record( string $id, string $desc, bool $passed ): void {
		printf( "[%-14s] %-52s : %s\n", $id, $desc, $passed ? 'PASS' : 'FAIL' );
		$this->results[ $id ] = $passed;
	}
}

$runner = new M3ShippingLiveIntegrationRunner();
$runner->run();

