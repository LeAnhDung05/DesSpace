<?php
/**
 * DeSpace M3 Phase 1A — Cart & Module Foundation Unit Test Suite (TC-M3-01 to TC-M3-16).
 *
 * Standalone PHP CLI test suite mocking WordPress & WooCommerce APIs
 * using the same pattern as M4's test-inventory-matrix.php.
 * Run via CLI: php tests/test-m3-cart-unit.php
 *
 * @package DeSpace\Tests
 */

define( 'ABSPATH', __DIR__ . '/../' );

// -----------------------------------------------------------------------------
// Lightweight WordPress Hook & Utility Mocks
// -----------------------------------------------------------------------------
$despace_mock_actions = array();
$despace_mock_filters = array();
$despace_mock_logged_in = false;
$despace_mock_stock_hook_calls = array();

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private string $code;
		private string $message;
		private $data;

		public function __construct( string $code = '', string $message = '', $data = '' ) {
			$this->code    = $code;
			$this->message = $message;
			$this->data    = $data;
		}

		public function get_error_code(): string {
			return $this->code;
		}

		public function get_error_message(): string {
			return $this->message;
		}

		public function get_error_data() {
			return $this->data;
		}
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ): bool {
		return $thing instanceof WP_Error;
	}
}
if ( ! function_exists( 'add_action' ) ) {
	function add_action( string $tag, callable $callback, int $priority = 10, int $accepted_args = 1 ): bool {
		global $despace_mock_actions;
		$despace_mock_actions[ $tag ][] = array( 'callback' => $callback, 'priority' => $priority );
		return true;
	}
}
if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( string $tag, callable $callback, int $priority = 10, int $accepted_args = 1 ): bool {
		global $despace_mock_filters;
		$despace_mock_filters[ $tag ][] = array( 'callback' => $callback, 'priority' => $priority );
		return true;
	}
}
if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( string $tag, $value, ...$args ) {
		global $despace_mock_filters;
		if ( empty( $despace_mock_filters[ $tag ] ) ) {
			return $value;
		}
		foreach ( $despace_mock_filters[ $tag ] as $hook ) {
			$value = call_user_func( $hook['callback'], $value, ...$args );
		}
		return $value;
	}
}
if ( ! function_exists( 'do_action' ) ) {
	function do_action( string $tag, ...$args ): void {
		global $despace_mock_stock_hook_calls;
		if ( in_array( $tag, array(
			'woocommerce_reduce_order_stock',
			'woocommerce_restore_order_stock',
			'woocommerce_restock_refunded_item',
			'woocommerce_product_set_stock',
			'woocommerce_variation_set_stock',
		), true ) ) {
			$despace_mock_stock_hook_calls[] = $tag;
		}
	}
}
if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( string $path = '' ): string {
		return 'https://despace.test/wp-admin/' . ltrim( $path, '/' );
	}
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ): string {
		return trim( strip_tags( (string) $str ) );
	}
}
if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ): string {
		return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $key ) );
	}
}
if ( ! function_exists( 'absint' ) ) {
	function absint( $val ): int {
		return abs( (int) $val );
	}
}
if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		return $value;
	}
}
if ( ! function_exists( '__' ) ) {
	function __( string $text, string $domain = 'default' ): string {
		return $text;
	}
}
if ( ! function_exists( 'is_user_logged_in' ) ) {
	function is_user_logged_in(): bool {
		global $despace_mock_logged_in;
		return (bool) $despace_mock_logged_in;
	}
}
if ( ! function_exists( 'wp_verify_nonce' ) ) {
	function wp_verify_nonce( string $nonce, $action = -1 ) {
		return ( 'valid_nonce_' . $action ) === $nonce ? 1 : false;
	}
}
if ( ! function_exists( 'wp_create_nonce' ) ) {
	function wp_create_nonce( $action = -1 ): string {
		return 'valid_nonce_' . $action;
	}
}
if ( ! function_exists( 'get_woocommerce_currency' ) ) {
	function get_woocommerce_currency(): string {
		return 'VND';
	}
}
if ( ! function_exists( 'wc_price' ) ) {
	function wc_price( float $price ): string {
		return '<span class="woocommerce-Price-amount amount">' . number_format( $price, 0, ',', '.' ) . ' ₫</span>';
	}
}

// -----------------------------------------------------------------------------
// Load M3 Entrypoint BEFORE WooCommerce is defined to test safe missing-WC guard
// -----------------------------------------------------------------------------
require_once __DIR__ . '/../wordpress/wp-content/plugins/despace-core/includes/Checkout-Orders/CheckoutOrdersModule.php';

use DeSpace\CheckoutOrders\CheckoutOrdersModule;
use DeSpace\CheckoutOrders\Cart\CartHandler;

// Pre-check: calling init() without WooCommerce must not fatal and stays uninitialized.
CheckoutOrdersModule::init();
$guard_no_wc_ok = ( false === CheckoutOrdersModule::is_initialized() );

// -----------------------------------------------------------------------------
// Mock WooCommerce Runtime (declared conditionally so missing-WC pre-check runs first)
// -----------------------------------------------------------------------------
if ( ! class_exists( 'WooCommerce' ) ) {
class Mock_WC_Product {
	protected int $id;
	protected string $type;
	protected string $name;
	protected string $sku;
	protected float $price;
	protected string $status;
	protected bool $purchasable;
	protected bool $in_stock;
	protected bool $manage_stock;
	protected ?int $stock_qty;
	protected bool $backorders;
	protected bool $sold_individually;
	protected array $variation_attrs = array();
	protected int $parent_id = 0;
	protected ?int $stock_managed_by_id = null;

	public function __construct( array $props ) {
		$this->id                = (int) ( $props['id'] ?? 0 );
		$this->type              = (string) ( $props['type'] ?? 'simple' );
		$this->name              = (string) ( $props['name'] ?? 'Product' );
		$this->sku               = (string) ( $props['sku'] ?? 'SKU-' . $this->id );
		$this->price             = (float) ( $props['price'] ?? 0.0 );
		$this->status            = (string) ( $props['status'] ?? 'publish' );
		$this->purchasable       = (bool) ( $props['purchasable'] ?? true );
		$this->in_stock          = (bool) ( $props['in_stock'] ?? true );
		$this->manage_stock      = (bool) ( $props['manage_stock'] ?? false );
		$this->stock_qty         = array_key_exists( 'stock_qty', $props ) ? $props['stock_qty'] : null;
		$this->backorders        = (bool) ( $props['backorders'] ?? false );
		$this->sold_individually = (bool) ( $props['sold_individually'] ?? false );
		$this->variation_attrs   = (array) ( $props['variation_attrs'] ?? array() );
		$this->parent_id           = (int) ( $props['parent_id'] ?? 0 );
		$this->stock_managed_by_id = isset( $props['stock_managed_by_id'] ) ? (int) $props['stock_managed_by_id'] : null;
	}

	public function get_id(): int { return $this->id; }
	public function is_type( string $type ): bool { return $this->type === $type; }
	public function get_name(): string { return $this->name; }
	public function get_sku(): string { return $this->sku; }
	public function get_price(): float { return $this->price; }
	public function get_status(): string { return $this->status; }
	public function exists(): bool { return 'trash' !== $this->status && $this->id > 0; }
	public function is_purchasable(): bool { return $this->purchasable; }
	public function is_in_stock(): bool { return $this->in_stock && ( ! $this->manage_stock || ( $this->stock_qty ?? 0 ) > 0 || $this->backorders ); }
	public function managing_stock(): bool { return $this->manage_stock; }
	public function get_stock_quantity(): ?int { return $this->stock_qty; }
	public function backorders_allowed(): bool { return $this->backorders; }
	public function is_sold_individually(): bool { return $this->sold_individually; }
	public function get_permalink(): string { return 'https://despace.test/product/' . $this->id; }
	public function get_stock_status(): string { return $this->is_in_stock() ? 'instock' : 'outofstock'; }
	public function get_variation_attributes(): array { return $this->variation_attrs; }
	public function get_parent_id(): int { return $this->parent_id; }
	public function get_stock_managed_by_id(): int { return $this->stock_managed_by_id ?? $this->id; }
	public function set_status_for_test( string $status ): void { $this->status = $status; }
}

class Mock_WC_Session {
	public bool $has_cookie = false;
	public bool $initialized = true;
	private array $bag = array();

	public function has_session(): bool {
		return $this->has_cookie;
	}

	public function set_customer_session_cookie( bool $set ): void {
		$this->has_cookie = $set;
	}

	public function get( string $key, $default = null ) {
		return $this->bag[ $key ] ?? $default;
	}

	public function set( string $key, $value ): void {
		$this->bag[ $key ] = $value;
	}
}

class Mock_WC_Cart {
	private array $items = array();
	private float $subtotal = 0.0;
	private float $total = 0.0;

	public function add_to_cart( int $product_id, int $quantity = 1, int $variation_id = 0, array $variation = array() ) {
		ksort( $variation );
		$target_id = $variation_id > 0 ? $variation_id : $product_id;
		$product   = wc_get_product( $target_id );
		if ( ! $product ) {
			return false;
		}

		$cart_key = md5( $product_id . '|' . $variation_id . '|' . json_encode( $variation ) );

		if ( isset( $this->items[ $cart_key ] ) ) {
			$this->items[ $cart_key ]['quantity'] += $quantity;
		} else {
			$this->items[ $cart_key ] = array(
				'key'           => $cart_key,
				'product_id'    => $product_id,
				'variation_id'  => $variation_id,
				'variation'     => $variation,
				'quantity'      => $quantity,
				'data'          => $product,
				'line_subtotal' => $product->get_price() * $quantity,
			);
		}

		return $cart_key;
	}

	public function set_quantity( string $cart_item_key, int $quantity, bool $refresh_totals = true ): bool {
		if ( ! isset( $this->items[ $cart_item_key ] ) ) {
			return false;
		}
		if ( $quantity <= 0 ) {
			unset( $this->items[ $cart_item_key ] );
			return true;
		}
		$this->items[ $cart_item_key ]['quantity'] = $quantity;
		if ( $refresh_totals ) {
			$this->calculate_totals();
		}
		return true;
	}

	public function remove_cart_item( string $cart_item_key ): bool {
		if ( ! isset( $this->items[ $cart_item_key ] ) ) {
			return false;
		}
		unset( $this->items[ $cart_item_key ] );
		return true;
	}

	public function get_cart(): array {
		return $this->items;
	}

	public function get_cart_contents_count(): int {
		$count = 0;
		foreach ( $this->items as $item ) {
			$count += (int) $item['quantity'];
		}
		return $count;
	}

	public function calculate_totals(): void {
		$sum = 0.0;
		foreach ( $this->items as $key => $item ) {
			$price = (float) $item['data']->get_price();
			$line  = $price * (int) $item['quantity'];
			$this->items[ $key ]['line_subtotal'] = $line;
			$sum += $line;
		}
		$this->subtotal = $sum;
		$this->total    = $sum;
		$this->persist_to_session();
	}

	public function persist_to_session(): void {
		if ( WC()->session && method_exists( WC()->session, 'set' ) ) {
			$snapshot = array();
			foreach ( $this->items as $key => $item ) {
				$snapshot[ $key ] = array(
					'key'          => $item['key'],
					'product_id'   => $item['product_id'],
					'variation_id' => $item['variation_id'],
					'variation'    => $item['variation'],
					'quantity'     => $item['quantity'],
				);
			}
			WC()->session->set( 'cart', $snapshot );
		}
	}

	public function load_from_session(): void {
		$this->items = array();
		if ( ! WC()->session || ! method_exists( WC()->session, 'get' ) ) {
			return;
		}
		$snapshot = WC()->session->get( 'cart', array() );
		foreach ( (array) $snapshot as $key => $row ) {
			$target_id = ! empty( $row['variation_id'] ) ? (int) $row['variation_id'] : (int) $row['product_id'];
			$product   = wc_get_product( $target_id );
			if ( $product ) {
				$this->items[ $key ] = array(
					'key'           => $key,
					'product_id'    => (int) $row['product_id'],
					'variation_id'  => (int) $row['variation_id'],
					'variation'     => (array) $row['variation'],
					'quantity'      => (int) $row['quantity'],
					'data'          => $product,
					'line_subtotal' => $product->get_price() * (int) $row['quantity'],
				);
			}
		}
		$this->calculate_totals();
	}

	public function get_subtotal(): float { return $this->subtotal; }
	public function get_total( string $context = 'view' ) { return 'edit' === $context ? $this->total : wc_price( $this->total ); }
	public function get_cart_subtotal(): string { return wc_price( $this->subtotal ); }
	public function get_cart_total(): string { return wc_price( $this->total ); }
	public function is_empty(): bool { return empty( $this->items ); }
	public function needs_shipping(): bool { return ! empty( $this->items ); }
	public function empty_cart( bool $clear_persistent = true ): void {
		$this->items    = array();
		$this->subtotal = 0.0;
		$this->total    = 0.0;
	}
}

class WooCommerce {
	public ?Mock_WC_Cart $cart = null;
	public ?Mock_WC_Session $session = null;

	public function __construct() {
		$this->session = new Mock_WC_Session();
		$this->cart    = new Mock_WC_Cart();
	}

	public function initialize_session(): void {
		if ( null === $this->session ) {
			$this->session = new Mock_WC_Session();
		}
	}
}

function WC(): WooCommerce {
	global $despace_mock_wc;
	return $despace_mock_wc;
}

function wc_get_product( int $id ): ?Mock_WC_Product {
	global $despace_mock_products;
	return $despace_mock_products[ $id ] ?? null;
}
}

$despace_mock_wc       = new WooCommerce();
$despace_mock_products = array();

function reset_test_catalog_and_cart(): void {
	global $despace_mock_products, $despace_mock_wc, $despace_mock_logged_in, $despace_mock_stock_hook_calls;
	$despace_mock_logged_in        = false;
	$despace_mock_stock_hook_calls = array();
	$despace_mock_wc->session      = new Mock_WC_Session();
	$despace_mock_wc->cart->empty_cart();

	$despace_mock_products = array(
		// Simple product in stock (stock = 5)
		101 => new Mock_WC_Product( array(
			'id'           => 101,
			'type'         => 'simple',
			'name'         => 'Tủ giày gỗ sồi Nord',
			'sku'          => 'AXG-STR-001',
			'price'        => 4500000.0,
			'manage_stock' => true,
			'stock_qty'    => 5,
		) ),
		// Variable parent product
		200 => new Mock_WC_Product( array(
			'id'              => 200,
			'type'            => 'variable',
			'name'            => 'Sofa modular Arlo',
			'sku'             => 'DSP-SF-ARLO',
			'price'           => 28900000.0,
			'variation_attrs' => array(
				'pa_color' => array( 'sand', 'olive' ),
			),
		) ),
		// Valid variation belonging to 200 (stock = 3)
		201 => new Mock_WC_Product( array(
			'id'              => 201,
			'type'            => 'variation',
			'parent_id'       => 200,
			'name'            => 'Sofa modular Arlo - Sand',
			'sku'             => 'DSP-SF-ARLO-SAND',
			'price'           => 28900000.0,
			'manage_stock'    => true,
			'stock_qty'       => 3,
			'variation_attrs' => array(
				'attribute_pa_color' => 'sand',
			),
		) ),
		// Variation belonging to a DIFFERENT parent (299)
		202 => new Mock_WC_Product( array(
			'id'              => 202,
			'type'            => 'variation',
			'parent_id'       => 299,
			'name'            => 'Foreign Variation',
			'price'           => 1000000.0,
			'variation_attrs' => array(
				'attribute_pa_color' => 'black',
			),
		) ),
		// Out of stock simple product
		301 => new Mock_WC_Product( array(
			'id'           => 301,
			'type'         => 'simple',
			'name'         => 'Kệ sách hết hàng',
			'price'        => 3200000.0,
			'in_stock'     => false,
			'manage_stock' => true,
			'stock_qty'    => 0,
		) ),
		// Parent-managed stock variable product (shared parent stock = 4, mixed-case custom attribute 'Color')
		250 => new Mock_WC_Product( array(
			'id'              => 250,
			'type'            => 'variable',
			'name'            => 'Bàn ăn gỗ óc chó',
			'price'           => 15000000.0,
			'manage_stock'    => true,
			'stock_qty'       => 4,
			'variation_attrs' => array(
				'Color' => array( 'Sand', 'Olive' ),
			),
		) ),
		251 => new Mock_WC_Product( array(
			'id'                  => 251,
			'type'                => 'variation',
			'parent_id'           => 250,
			'stock_managed_by_id' => 250,
			'name'                => 'Bàn ăn gỗ óc chó - Sand',
			'price'               => 15000000.0,
			'manage_stock'        => true,
			'stock_qty'           => 4,
			'variation_attrs'     => array( 'attribute_color' => 'Sand' ),
		) ),
		252 => new Mock_WC_Product( array(
			'id'                  => 252,
			'type'                => 'variation',
			'parent_id'           => 250,
			'stock_managed_by_id' => 250,
			'name'                => 'Bàn ăn gỗ óc chó - Olive',
			'price'               => 15500000.0,
			'manage_stock'        => true,
			'stock_qty'           => 4,
			'variation_attrs'     => array( 'attribute_color' => 'Olive' ),
		) ),
		// Wildcard ("Any Color") variation under parent 250
		253 => new Mock_WC_Product( array(
			'id'                  => 253,
			'type'                => 'variation',
			'parent_id'           => 250,
			'stock_managed_by_id' => 250,
			'name'                => 'Bàn ăn gỗ óc chó - Custom',
			'price'               => 16000000.0,
			'manage_stock'        => true,
			'stock_qty'           => 4,
			'variation_attrs'     => array( 'attribute_color' => '' ),
		) ),
		// Sold individually product
		401 => new Mock_WC_Product( array(
			'id'                => 401,
			'type'              => 'simple',
			'name'              => 'Tác phẩm giới hạn',
			'price'             => 19000000.0,
			'sold_individually' => true,
		) ),
	);
}

// Now initialize M3 Module twice to confirm idempotent initialization
CheckoutOrdersModule::init();
CheckoutOrdersModule::init();

// -----------------------------------------------------------------------------
// Unit Test Runner (TC-M3-01 .. TC-M3-16)
// -----------------------------------------------------------------------------
class M3CartUnitRunner {
	private array $results = array();

	public function run( bool $guard_no_wc_ok ): void {
		global $despace_mock_actions;

		echo "========================================================================\n";
		echo "       DeSpace M3 Phase 1A — CART BACKEND UNIT TEST SUITE               \n";
		echo "========================================================================\n\n";

		$frontend_cfg = apply_filters( 'despace_m3_cart_frontend_config', array() );
		$init_ok = $guard_no_wc_ok
			&& CheckoutOrdersModule::is_initialized()
			&& CartHandler::are_hooks_registered()
			&& 1 === count( $despace_mock_actions['wp_ajax_despace_cart_add'] ?? array() )
			&& ! empty( $frontend_cfg['ajax_url'] )
			&& wp_create_nonce( CartHandler::NONCE_ACTION ) === ( $frontend_cfg['nonce'] ?? '' );
		$this->record( 'FOUNDATION', 'Module guard, single-init & frontend nonce config', $init_ok );

		$this->tc_01_add_simple_product();
		$this->tc_02_add_variable_product_and_no_duplicate_row();
		$this->tc_03_missing_variation_id();
		$this->tc_04_invalid_variation();
		$this->tc_05_out_of_stock_product();
		$this->tc_06_quantity_zero();
		$this->tc_07_negative_or_invalid_quantity();
		$this->tc_08_update_quantity();
		$this->tc_09_remove_cart_item();
		$this->tc_10_guest_cart_session();
		$this->tc_11_logged_in_customer_cart();
		$this->tc_12_ajax_missing_or_invalid_nonce();
		$this->tc_13_forged_frontend_price_ignored();
		$this->tc_14_cart_fragments();
		$this->tc_15_deleted_or_unavailable_product();
		$this->tc_16_no_stock_mutation_or_m4_inventory_logs();

		$this->print_summary();
	}

	private function record( string $id, string $desc, bool $passed, string $detail = '' ): void {
		$label = $passed ? 'PASS' : 'FAIL';
		printf( "[%-10s] %-52s : %s %s\n", $id, $desc, $label, $detail );
		$this->results[ $id ] = $passed;
	}

	private function tc_01_add_simple_product(): void {
		reset_test_catalog_and_cart();
		$res = CartHandler::add_item( 101, 2 );
		$ok  = ! is_wp_error( $res )
			&& ! empty( $res['cart_item_key'] )
			&& 2 === $res['cart']['item_count']
			&& 9000000.0 === $res['cart']['subtotal']
			&& 9000000.0 === $res['cart']['total'];
		$this->record( 'TC-M3-01', 'Add simple product into cart', $ok );
	}

	private function tc_02_add_variable_product_and_no_duplicate_row(): void {
		reset_test_catalog_and_cart();
		// Variation-managed stock (200 -> 201) merges duplicate adds without creating two rows.
		$res1 = CartHandler::add_item( 200, 1, 201, array( 'attribute_pa_color' => 'sand' ) );
		$res2 = CartHandler::add_item( 200, 1, 201, array( 'attribute_pa_color' => 'sand' ) );

		// Parent-managed stock (250 shared across 251 & 252, parent stock = 4).
		$p1   = CartHandler::add_item( 250, 3, 251, array( 'attribute_color' => 'Sand' ) );
		$p2   = CartHandler::add_item( 250, 2, 252, array( 'attribute_color' => 'Olive' ) ); // 3 + 2 > 4 => blocked!

		// Wildcard variation under mixed-case parent attribute 'Color' resolves valid option & rejects invalid option.
		$wc_bad = CartHandler::add_item( 250, 1, 253, array( 'attribute_color' => 'Red' ) );
		$wc_ok  = CartHandler::add_item( 250, 1, 253, array( 'attribute_color' => 'Olive' ) );

		$ok   = ! is_wp_error( $res1 )
			&& ! is_wp_error( $res2 )
			&& $res1['cart_item_key'] === $res2['cart_item_key']
			&& 2 === $res2['cart']['items'][0]['quantity']
			&& ! is_wp_error( $p1 )
			&& is_wp_error( $p2 ) && 'despace_insufficient_stock' === $p2->get_error_code()
			&& is_wp_error( $wc_bad ) && 'despace_invalid_variation_attributes' === $wc_bad->get_error_code()
			&& ! is_wp_error( $wc_ok );
		$this->record( 'TC-M3-02', 'Variable product (variation + parent stock, no dup)', $ok );
	}

	private function tc_03_missing_variation_id(): void {
		reset_test_catalog_and_cart();
		$res = CartHandler::add_item( 200, 1, 0 );
		$ok  = is_wp_error( $res ) && 'despace_missing_variation' === $res->get_error_code();
		$this->record( 'TC-M3-03', 'Reject variable product with missing variation_id', $ok );
	}

	private function tc_04_invalid_variation(): void {
		reset_test_catalog_and_cart();
		$bad_parent = CartHandler::add_item( 200, 1, 202 );
		$bad_attr   = CartHandler::add_item( 200, 1, 201, array( 'attribute_pa_color' => 'olive' ) );
		$bad_simple = CartHandler::add_item( 101, 1, 201 );
		$ok = is_wp_error( $bad_parent ) && 'despace_invalid_variation' === $bad_parent->get_error_code()
			&& is_wp_error( $bad_attr ) && 'despace_invalid_variation_attributes' === $bad_attr->get_error_code()
			&& is_wp_error( $bad_simple ) && 'despace_invalid_variation' === $bad_simple->get_error_code();
		$this->record( 'TC-M3-04', 'Reject invalid variation ID / parent / attributes', $ok );
	}

	private function tc_05_out_of_stock_product(): void {
		reset_test_catalog_and_cart();
		$oos       = CartHandler::add_item( 301, 1 );
		$over      = CartHandler::add_item( 101, 6 );
		$si_first  = CartHandler::add_item( 401, 1 );
		$si_second = CartHandler::add_item( 401, 1 );
		$ok   = is_wp_error( $oos ) && 'despace_out_of_stock' === $oos->get_error_code()
			&& is_wp_error( $over ) && 'despace_insufficient_stock' === $over->get_error_code()
			&& ! is_wp_error( $si_first )
			&& is_wp_error( $si_second ) && 'despace_sold_individually' === $si_second->get_error_code();
		$this->record( 'TC-M3-05', 'Reject out-of-stock, excess stock & sold individually', $ok );
	}

	private function tc_06_quantity_zero(): void {
		reset_test_catalog_and_cart();
		$add_zero = CartHandler::add_item( 101, 0 );
		$ok       = is_wp_error( $add_zero ) && 'despace_invalid_quantity' === $add_zero->get_error_code();
		$this->record( 'TC-M3-06', 'Reject quantity = 0 when adding to cart', $ok );
	}

	private function tc_07_negative_or_invalid_quantity(): void {
		reset_test_catalog_and_cart();
		$neg   = CartHandler::add_item( 101, -3 );
		$str   = CartHandler::add_item( 101, 'abc' );
		$float = CartHandler::add_item( 101, 1.5 );
		$upd   = CartHandler::update_quantity( 'dummy_key', -1 );
		$ok    = is_wp_error( $neg ) && 'despace_invalid_quantity' === $neg->get_error_code()
			&& is_wp_error( $str ) && 'despace_invalid_quantity' === $str->get_error_code()
			&& is_wp_error( $float ) && 'despace_invalid_quantity' === $float->get_error_code()
			&& is_wp_error( $upd ) && 'despace_invalid_quantity' === $upd->get_error_code();
		$this->record( 'TC-M3-07', 'Reject negative, float, and non-numeric quantities', $ok );
	}

	private function tc_08_update_quantity(): void {
		reset_test_catalog_and_cart();
		$added   = CartHandler::add_item( 101, 1 );
		$updated = CartHandler::update_quantity( $added['cart_item_key'], 4 );
		$over    = CartHandler::update_quantity( $added['cart_item_key'], 6 );
		$ok = ! is_wp_error( $updated )
			&& 4 === $updated['cart']['item_count']
			&& 18000000.0 === $updated['cart']['total']
			&& is_wp_error( $over ) && 'despace_insufficient_stock' === $over->get_error_code();
		$this->record( 'TC-M3-08', 'Update cart quantity & enforce stock boundary', $ok );
	}

	private function tc_09_remove_cart_item(): void {
		reset_test_catalog_and_cart();
		$added   = CartHandler::add_item( 101, 2 );
		$removed = CartHandler::remove_item( $added['cart_item_key'] );
		$missing = CartHandler::remove_item( $added['cart_item_key'] );
		$ok = ! is_wp_error( $removed )
			&& true === $removed['cart']['is_empty']
			&& 0 === $removed['cart']['item_count']
			&& 0.0 === $removed['cart']['total']
			&& is_wp_error( $missing ) && 'despace_cart_item_not_found' === $missing->get_error_code();
		$this->record( 'TC-M3-09', 'Remove cart item & handle expired/missing key', $ok );
	}

	private function tc_10_guest_cart_session(): void {
		global $despace_mock_logged_in, $despace_mock_actions, $despace_mock_wc;
		reset_test_catalog_and_cart();
		$despace_mock_logged_in = false;
		$nonce                  = wp_create_nonce( CartHandler::NONCE_ACTION );

		// Request 1: Guest adds product -> session cookie enabled and snapshot persisted.
		$r1 = CartHandler::handle_add_request( array(
			'nonce'      => $nonce,
			'product_id' => 101,
			'quantity'   => 1,
		) );
		$key = $r1['data']['cart_item_key'] ?? '';

		// Simulate Request 2: fresh in-memory WC_Cart hydrated from persisted WC()->session.
		$despace_mock_wc->cart = new Mock_WC_Cart();
		$despace_mock_wc->cart->load_from_session();
		$r2 = CartHandler::handle_update_request( array(
			'nonce'         => $nonce,
			'cart_item_key' => $key,
			'quantity'      => 3,
		) );

		// Simulate Request 3: fresh WC_Cart reads persisted quantity = 3 via handle_get_request.
		$despace_mock_wc->cart = new Mock_WC_Cart();
		$despace_mock_wc->cart->load_from_session();
		$r3 = CartHandler::handle_get_request( array( 'nonce' => $nonce ) );

		// Simulate Request 4: remove item & persist empty cart cleanly.
		$r4 = CartHandler::handle_remove_request( array(
			'nonce'         => $nonce,
			'cart_item_key' => $key,
		) );

		$ok = ! empty( $r1['success'] )
			&& ! empty( $r2['success'] ) && 3 === $r2['data']['cart']['item_count']
			&& ! empty( $r3['success'] ) && 3 === $r3['data']['cart']['item_count'] && 13500000.0 === $r3['data']['cart']['total']
			&& ! empty( $r4['success'] ) && true === $r4['data']['cart']['is_empty'] && 0.0 === $r4['data']['cart']['total']
			&& 'guest' === $r1['data']['cart']['user_context']
			&& true === WC()->session->has_session()
			&& ! empty( $despace_mock_actions['wp_ajax_nopriv_despace_cart_add'] )
			&& ! empty( $despace_mock_actions['wp_ajax_nopriv_despace_cart_update'] )
			&& ! empty( $despace_mock_actions['wp_ajax_nopriv_despace_cart_remove'] )
			&& ! empty( $despace_mock_actions['wp_ajax_nopriv_despace_cart_get'] );
		$this->record( 'TC-M3-10', 'Guest cart multi-request session persistence & nopriv', $ok );
	}

	private function tc_11_logged_in_customer_cart(): void {
		global $despace_mock_logged_in, $despace_mock_actions;
		reset_test_catalog_and_cart();
		$despace_mock_logged_in = true;

		$res = CartHandler::handle_add_request( array(
			'nonce'      => wp_create_nonce( CartHandler::NONCE_ACTION ),
			'product_id' => 101,
			'quantity'   => 2,
		) );

		$ok = ! empty( $res['success'] )
			&& 'customer' === $res['data']['cart']['user_context']
			&& 2 === $res['data']['cart']['item_count']
			&& ! empty( $despace_mock_actions['wp_ajax_despace_cart_add'] );
		$this->record( 'TC-M3-11', 'Logged-in customer cart AJAX handling', $ok );
	}

	private function tc_12_ajax_missing_or_invalid_nonce(): void {
		reset_test_catalog_and_cart();
		$r1 = CartHandler::handle_add_request( array( 'product_id' => 101, 'quantity' => 1 ) );
		$r2 = CartHandler::handle_update_request( array( 'nonce' => 'bad', 'cart_item_key' => 'abc', 'quantity' => 1 ) );
		$r3 = CartHandler::handle_remove_request( array( 'cart_item_key' => 'abc' ) );
		$r4 = CartHandler::handle_get_request( array() );

		$ok = false === $r1['success'] && 403 === $r1['status'] && 'despace_invalid_nonce' === $r1['data']['code']
			&& false === $r2['success'] && 403 === $r2['status']
			&& false === $r3['success'] && 403 === $r3['status']
			&& false === $r4['success'] && 403 === $r4['status'];
		$this->record( 'TC-M3-12', 'Reject AJAX requests missing or invalid nonce (403)', $ok );
	}

	private function tc_13_forged_frontend_price_ignored(): void {
		reset_test_catalog_and_cart();
		$res = CartHandler::handle_add_request( array(
			'nonce'         => wp_create_nonce( CartHandler::NONCE_ACTION ),
			'product_id'    => 200,
			'variation_id'  => 201,
			'quantity'      => 1,
			'price'         => 1,
			'regular_price' => 1,
			'subtotal'      => 0,
			'total'         => 0,
			'attributes'    => array( 'pa_color' => 'sand' ),
		) );

		$ok = ! empty( $res['success'] )
			&& 28900000.0 === $res['data']['cart']['items'][0]['unit_price']
			&& 28900000.0 === $res['data']['cart']['total'];
		$this->record( 'TC-M3-13', 'Ignore forged frontend price/total payload', $ok );
	}

	private function tc_14_cart_fragments(): void {
		reset_test_catalog_and_cart();
		CartHandler::add_item( 101, 3 );
		$frags = apply_filters( 'woocommerce_add_to_cart_fragments', array() );

		$ok = isset( $frags['.despace-cart-count'], $frags['.despace-cart-subtotal'], $frags['.despace-cart-total'] )
			&& false !== strpos( $frags['.despace-cart-count'], '>3<' )
			&& false !== strpos( $frags['.despace-cart-subtotal'], '13.500.000' );
		$this->record( 'TC-M3-14', 'Generate accurate WooCommerce cart fragments', $ok );
	}

	private function tc_15_deleted_or_unavailable_product(): void {
		global $despace_mock_products;
		reset_test_catalog_and_cart();

		$added = CartHandler::add_item( 101, 2 );
		// Simulate product deleted/trashed after already sitting in cart session.
		$despace_mock_products[101]->set_status_for_test( 'trash' );

		$cleaned = CartHandler::cleanup_invalid_cart_items( WC()->cart );
		$try_add = CartHandler::add_item( 101, 1 );
		$try_upd = CartHandler::update_quantity( $added['cart_item_key'], 3 );

		$ok = true === $cleaned
			&& true === WC()->cart->is_empty()
			&& 0.0 === WC()->cart->get_total( 'edit' )
			&& is_wp_error( $try_add ) && 'despace_product_unavailable' === $try_add->get_error_code()
			&& is_wp_error( $try_upd );
		$this->record( 'TC-M3-15', 'Clean up & reject trashed/unavailable products', $ok );
	}

	private function tc_16_no_stock_mutation_or_m4_inventory_logs(): void {
		global $despace_mock_stock_hook_calls;
		reset_test_catalog_and_cart();

		$stock_before = wc_get_product( 101 )->get_stock_quantity();
		$added        = CartHandler::add_item( 101, 3 );
		CartHandler::update_quantity( $added['cart_item_key'], 4 );
		CartHandler::remove_item( $added['cart_item_key'] );
		$stock_after  = wc_get_product( 101 )->get_stock_quantity();

		// Static verification that M3 Phase 1A sources never touch DB/M4 logs or WC stock mutation APIs.
		$module_src  = (string) file_get_contents( __DIR__ . '/../wordpress/wp-content/plugins/despace-core/includes/Checkout-Orders/CheckoutOrdersModule.php' );
		$handler_src = (string) file_get_contents( __DIR__ . '/../wordpress/wp-content/plugins/despace-core/includes/Checkout-Orders/Cart/CartHandler.php' );
		$combined    = $module_src . "\n" . $handler_src;
		$forbidden   = array(
			'despace_inventory_logs',
			'InventoryModule',
			'InventoryEventRecorder',
			'wc_update_product_stock',
			'set_stock_quantity',
			'wc_reduce_stock_levels',
			'wc_restore_stock_levels',
			'woocommerce_product_set_stock',
			'woocommerce_variation_set_stock',
			'$wpdb',
		);
		$clean_src   = true;
		foreach ( $forbidden as $token ) {
			if ( false !== strpos( $combined, $token ) ) {
				$clean_src = false;
			}
		}

		$ok = ( 5 === $stock_before )
			&& ( 5 === $stock_after )
			&& empty( $despace_mock_stock_hook_calls )
			&& $clean_src;
		$this->record( 'TC-M3-16', 'Cart never mutates stock or triggers M4 logs', $ok );
	}

	private function print_summary(): void {
		$total  = count( $this->results );
		$passed = count( array_filter( $this->results ) );
		$failed = $total - $passed;

		echo "\n========================================================================\n";
		printf( "SUMMARY: %d Total | %d Passed | %d Failed\n", $total, $passed, $failed );
		echo "========================================================================\n";

		if ( $failed > 0 ) {
			exit( 1 );
		}
	}
}

$runner = new M3CartUnitRunner();
$runner->run( $guard_no_wc_ok );
