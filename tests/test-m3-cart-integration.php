<?php
/**
 * DeSpace M3 Phase 1A — Live WordPress & WooCommerce Cart Integration Tests.
 *
 * Executes against an active WordPress + WooCommerce database runtime.
 * Run via CLI: php tests/test-m3-cart-integration.php
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

class M3CartLiveIntegrationRunner {

	private array $results = array();
	private array $created_ids = array();

	public function run(): void {
		echo "========================================================================\n";
		echo "     DeSpace M3 Phase 1A — LIVE WOOCOMMERCE CART INTEGRATION TESTS      \n";
		echo "========================================================================\n\n";

		try {
			if ( function_exists( 'wc_load_cart' ) && null === WC()->cart ) {
				wc_load_cart();
			}

			$simple = new \WC_Product_Simple();
			$simple->set_name( 'M3 Integration Simple Cabinet ' . time() );
			$simple->set_status( 'publish' );
			$simple->set_regular_price( '4500000' );
			$simple->set_manage_stock( true );
			$simple->set_stock_quantity( 5 );
			$simple_id = $simple->save();
			$this->created_ids[] = $simple_id;

			$variable = new \WC_Product_Variable();
			$variable->set_name( 'M3 Integration Variable Sofa ' . time() );
			$variable->set_status( 'publish' );
			$attr = new \WC_Product_Attribute();
			$attr->set_name( 'Color' );
			$attr->set_options( array( 'Sand', 'Olive' ) );
			$attr->set_visible( true );
			$attr->set_variation( true );
			$variable->set_attributes( array( $attr ) );
			$var_parent_id = $variable->save();
			$this->created_ids[] = $var_parent_id;

			$variation = new \WC_Product_Variation();
			$variation->set_parent_id( $var_parent_id );
			$variation->set_status( 'publish' );
			$variation->set_regular_price( '28900000' );
			$variation->set_attributes( array( 'color' => 'Sand' ) );
			$variation->set_manage_stock( true );
			$variation->set_stock_quantity( 3 );
			$variation_id = $variation->save();
			$this->created_ids[] = $variation_id;

			global $wpdb;
			$inv_table      = $wpdb->prefix . 'despace_inventory_logs';
			$has_inv_table  = ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $inv_table ) ) === $inv_table );
			$inv_count_pre  = $has_inv_table ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$inv_table}" ) : 0;

			WC()->cart->empty_cart();

			// Add simple + variable product, forge price in request, verify WC totals and untouched M4 inventory logs.
			$r1 = CartHandler::add_item( $simple_id, 2 );
			$this->record( 'M3-INT-01', 'Live add simple product to WC()->cart', ! is_wp_error( $r1 ) && 2 === $r1['cart']['item_count'] );

			$r2 = CartHandler::handle_add_request( array(
				'nonce'        => wp_create_nonce( CartHandler::NONCE_ACTION ),
				'product_id'   => $var_parent_id,
				'variation_id' => $variation_id,
				'quantity'     => 1,
				'price'        => 1,
				'attributes'   => array( 'attribute_color' => 'Sand' ),
			) );
			$this->record( 'M3-INT-02', 'Live add variable product & reject forged price', ! empty( $r2['success'] ) && 37900000.0 === $r2['data']['cart']['subtotal'] );

			$r3 = CartHandler::update_quantity( $r1['cart_item_key'], 4 );
			$this->record( 'M3-INT-03', 'Live update quantity & recalculate WC totals', ! is_wp_error( $r3 ) && 5 === $r3['cart']['item_count'] );

			$r4 = CartHandler::remove_item( $r1['cart_item_key'] );
			$this->record( 'M3-INT-04', 'Live remove cart item', ! is_wp_error( $r4 ) && 1 === $r4['cart']['item_count'] );

			$live_simple   = wc_get_product( $simple_id );
			$inv_count_post = $has_inv_table ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$inv_table}" ) : 0;
			$this->record(
				'M3-INT-05',
				'Stock untouched & zero M4 inventory log writes',
				5 === (int) $live_simple->get_stock_quantity() && $inv_count_pre === $inv_count_post
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
		printf( "\nSUMMARY: %d Live Tests | %d Passed | %d Failed\n", $total, $passed, $failed );
		if ( $failed > 0 ) {
			exit( 1 );
		}
	}

	private function record( string $id, string $desc, bool $passed ): void {
		printf( "[%-10s] %-50s : %s\n", $id, $desc, $passed ? 'PASS' : 'FAIL' );
		$this->results[ $id ] = $passed;
	}
}

$runner = new M3CartLiveIntegrationRunner();
$runner->run();

