<?php
/**
 * DeSpace M3 Phase 1B-A — Coupon Validation & Cart Coupon Unit Test Suite.
 *
 * Standalone PHP CLI unit tests mocking WordPress & WooCommerce WC_Coupon,
 * WC_Discounts, and WC_Cart behaviors.
 * Run via CLI: php tests/test-m3-coupon-unit.php
 *
 * @package DeSpace\Tests
 */

define( 'ABSPATH', __DIR__ . '/../' );

$despace_mock_actions          = array();
$despace_mock_filters          = array();
$despace_mock_logged_in        = false;
$despace_mock_current_user_id  = 0;
$despace_mock_notices          = array();
$despace_mock_coupons_enabled  = true;
$despace_mock_stock_hook_calls = array();

class MockAjaxExitException extends \RuntimeException {
	public array $payload;
	public int $status_code;
	public function __construct( array $payload, int $status_code ) {
		parent::__construct( 'AJAX JSON response' );
		$this->payload     = $payload;
		$this->status_code = $status_code;
	}
}

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
		public function get_error_code(): string { return $this->code; }
		public function get_error_message(): string { return $this->message; }
		public function get_error_data() { return $this->data; }
	}
}

function is_wp_error( $thing ): bool { return $thing instanceof WP_Error; }
function add_action( string $tag, callable $callback, int $priority = 10, int $args = 1 ): bool {
	global $despace_mock_actions;
	$despace_mock_actions[ $tag ][] = array( 'callback' => $callback, 'priority' => $priority );
	return true;
}
function add_filter( string $tag, callable $callback, int $priority = 10, int $args = 1 ): bool {
	global $despace_mock_filters;
	$despace_mock_filters[ $tag ][] = array( 'callback' => $callback, 'priority' => $priority );
	return true;
}
function apply_filters( string $tag, $value, ...$args ) {
	global $despace_mock_filters;
	if ( empty( $despace_mock_filters[ $tag ] ) ) {
		return $value;
	}
	$buckets = $despace_mock_filters[ $tag ];
	usort( $buckets, fn( $a, $b ) => $a['priority'] <=> $b['priority'] );
	foreach ( $buckets as $hook ) {
		$value = call_user_func( $hook['callback'], $value, ...$args );
	}
	return $value;
}
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
function sanitize_text_field( $str ): string { return trim( strip_tags( (string) $str ) ); }
function sanitize_key( $key ): string { return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $key ) ); }
function absint( $val ): int { return abs( (int) $val ); }
function wp_unslash( $value ) { return $value; }
function wp_strip_all_tags( string $text ): string { return trim( strip_tags( $text ) ); }
function esc_html( string $text ): string { return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( string $text ): string { return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' ); }
function __( string $text, string $domain = 'default' ): string { return $text; }
function admin_url( string $path = '' ): string { return 'https://despace.test/wp-admin/' . ltrim( $path, '/' ); }
function is_user_logged_in(): bool { global $despace_mock_logged_in; return (bool) $despace_mock_logged_in; }
function get_current_user_id(): int { global $despace_mock_current_user_id; return (int) $despace_mock_current_user_id; }
function wp_verify_nonce( string $nonce, $action = -1 ) { return ( 'valid_nonce_' . $action ) === $nonce ? 1 : false; }
function wp_create_nonce( $action = -1 ): string { return 'valid_nonce_' . $action; }
function wc_coupons_enabled(): bool { global $despace_mock_coupons_enabled; return (bool) $despace_mock_coupons_enabled; }
function wc_format_coupon_code( string $code ): string { return strtolower( trim( $code ) ); }
function get_woocommerce_currency(): string { return 'VND'; }
function wc_price( float $price ): string { return '<span class="woocommerce-Price-amount">' . number_format( $price, 0, ',', '.' ) . ' ₫</span>'; }
function wc_add_notice( string $msg, string $type = 'success' ): void {
	global $despace_mock_notices;
	$despace_mock_notices[ $type ][] = array( 'notice' => $msg );
}
function wc_get_notices( string $type = '' ): array {
	global $despace_mock_notices;
	return '' === $type ? $despace_mock_notices : ( $despace_mock_notices[ $type ] ?? array() );
}
function wc_clear_notices(): void {
	global $despace_mock_notices;
	$despace_mock_notices = array();
}
function wc_set_notices( array $notices = array() ): void {
	global $despace_mock_notices;
	$despace_mock_notices = $notices;
}
function wp_send_json_success( $data = null, int $status_code = 200 ): void {
	throw new MockAjaxExitException( array( 'success' => true, 'data' => $data ), $status_code );
}
function wp_send_json_error( $data = null, int $status_code = 400 ): void {
	throw new MockAjaxExitException( array( 'success' => false, 'data' => $data ), $status_code );
}

// Load M3 Entrypoint (automatically registers PSR-4 autoloader).
require_once __DIR__ . '/../wordpress/wp-content/plugins/despace-core/includes/Checkout-Orders/CheckoutOrdersModule.php';

use DeSpace\CheckoutOrders\CheckoutOrdersModule;
use DeSpace\CheckoutOrders\Cart\CartHandler;
use DeSpace\CheckoutOrders\Cart\CouponValidator;

if ( ! class_exists( 'WooCommerce' ) ) {

class Mock_WC_Product_For_Coupon {
	private int $id;
	private string $name;
	private string $sku;
	private float $price;
	private bool $on_sale;
	private array $category_ids;
	private int $stock_qty;

	public function __construct( array $p ) {
		$this->id           = (int) $p['id'];
		$this->name         = (string) ( $p['name'] ?? 'Product' );
		$this->sku          = (string) ( $p['sku'] ?? 'SKU-' . $this->id );
		$this->price        = (float) $p['price'];
		$this->on_sale      = (bool) ( $p['on_sale'] ?? false );
		$this->category_ids = (array) ( $p['category_ids'] ?? array() );
		$this->stock_qty    = (int) ( $p['stock_qty'] ?? 10 );
	}
	public function get_id(): int { return $this->id; }
	public function is_type( string $t ): bool { return 'simple' === $t; }
	public function get_name(): string { return $this->name; }
	public function get_sku(): string { return $this->sku; }
	public function get_price(): float { return $this->price; }
	public function get_status(): string { return 'publish'; }
	public function exists(): bool { return true; }
	public function is_purchasable(): bool { return true; }
	public function is_in_stock(): bool { return $this->stock_qty > 0; }
	public function managing_stock(): bool { return true; }
	public function get_stock_quantity(): int { return $this->stock_qty; }
	public function backorders_allowed(): bool { return false; }
	public function is_sold_individually(): bool { return false; }
	public function get_permalink(): string { return 'https://despace.test/p/' . $this->id; }
	public function get_stock_status(): string { return 'instock'; }
	public function get_stock_managed_by_id(): int { return $this->id; }
	public function is_on_sale(): bool { return $this->on_sale; }
	public function get_category_ids(): array { return $this->category_ids; }
}

class WC_Coupon {
	private int $id = 0;
	private string $code = '';
	private string $status = 'publish';
	private string $discount_type = 'percent';
	private float $amount = 0.0;
	private ?int $date_expires = null;
	private ?int $date_starts = null;
	private int $usage_limit = 0;
	private int $usage_count = 0;
	private int $usage_limit_per_user = 0;
	private array $used_by = array();
	private array $product_ids = array();
	private array $excluded_product_ids = array();
	private array $product_categories = array();
	private array $excluded_product_categories = array();
	private bool $exclude_sale_items = false;
	private float $minimum_amount = 0.0;
	private float $maximum_amount = 0.0;
	private bool $individual_use = false;
	private array $email_restrictions = array();

	public function __construct( string $code ) {
		global $despace_mock_coupons_db;
		$norm = wc_format_coupon_code( $code );
		if ( isset( $despace_mock_coupons_db[ $norm ] ) ) {
			foreach ( $despace_mock_coupons_db[ $norm ] as $k => $v ) {
				if ( property_exists( $this, $k ) ) {
					$this->$k = $v;
				}
			}
			$this->code = $norm;
		}
	}

	public function get_id(): int { return $this->id; }
	public function get_code(): string { return $this->code; }
	public function get_status(): string { return $this->status; }
	public function get_discount_type(): string { return $this->discount_type; }
	public function get_amount(): float { return $this->amount; }
	public function get_date_expires(): ?int { return $this->date_expires; }
	public function get_date_starts(): ?int { return $this->date_starts; }
	public function get_usage_limit(): int { return $this->usage_limit; }
	public function get_usage_count(): int { return $this->usage_count; }
	public function get_usage_limit_per_user(): int { return $this->usage_limit_per_user; }
	public function get_used_by(): array { return $this->used_by; }
	public function get_product_ids(): array { return $this->product_ids; }
	public function get_excluded_product_ids(): array { return $this->excluded_product_ids; }
	public function get_product_categories(): array { return $this->product_categories; }
	public function get_excluded_product_categories(): array { return $this->excluded_product_categories; }
	public function get_exclude_sale_items(): bool { return $this->exclude_sale_items; }
	public function get_minimum_amount(): float { return $this->minimum_amount; }
	public function get_maximum_amount(): float { return $this->maximum_amount; }
	public function get_individual_use(): bool { return $this->individual_use; }
	public function get_email_restrictions(): array { return $this->email_restrictions; }
}

class WC_Discounts {
	private Mock_WC_Cart_Coupon $cart;

	public function __construct( Mock_WC_Cart_Coupon $cart ) {
		$this->cart = $cart;
	}

	public function is_coupon_valid( WC_Coupon $coupon ) {
		if ( $coupon->get_id() <= 0 || 'publish' !== $coupon->get_status() ) {
			return new WP_Error( 105, 'Coupon does not exist!' );
		}
		$now = time();
		if ( null !== $coupon->get_date_expires() && $now > $coupon->get_date_expires() ) {
			return new WP_Error( 107, 'This coupon has expired.' );
		}
		if ( null !== $coupon->get_date_starts() && $now < $coupon->get_date_starts() ) {
			return new WP_Error( 115, 'This coupon is not active yet.' );
		}
		if ( $coupon->get_usage_limit() > 0 && $coupon->get_usage_count() >= $coupon->get_usage_limit() ) {
			return new WP_Error( 106, 'Coupon usage limit has been reached.' );
		}
		if ( $coupon->get_usage_limit_per_user() > 0 ) {
			$uid   = get_current_user_id();
			$count = 0;
			foreach ( $coupon->get_used_by() as $used ) {
				if ( $uid > 0 && (int) $used === $uid ) {
					$count++;
				}
			}
			if ( $count >= $coupon->get_usage_limit_per_user() ) {
				return new WP_Error( 106, 'Coupon usage limit per user has been reached.' );
			}
		}

		$subtotal = $this->cart->get_subtotal();
		if ( $coupon->get_minimum_amount() > 0 && $subtotal < $coupon->get_minimum_amount() ) {
			return new WP_Error( 108, 'The minimum spend for this coupon has not been met.' );
		}
		if ( $coupon->get_maximum_amount() > 0 && $subtotal > $coupon->get_maximum_amount() ) {
			return new WP_Error( 109, 'The maximum spend for this coupon has been exceeded.' );
		}

		$items       = $this->cart->get_cart();
		$prod_ids    = $coupon->get_product_ids();
		$excl_prods  = $coupon->get_excluded_product_ids();
		$cat_ids     = $coupon->get_product_categories();
		$excl_cats   = $coupon->get_excluded_product_categories();

		$eligible_found = false;
		foreach ( $items as $item ) {
			$p      = $item['data'];
			$pid    = $p->get_id();
			$pcats  = $p->get_category_ids();

			if ( in_array( $pid, $excl_prods, true ) ) {
				continue;
			}
			if ( ! empty( $excl_cats ) && ! empty( array_intersect( $pcats, $excl_cats ) ) ) {
				continue;
			}
			if ( $coupon->get_exclude_sale_items() && $p->is_on_sale() ) {
				continue;
			}
			if ( ! empty( $prod_ids ) && ! in_array( $pid, $prod_ids, true ) ) {
				continue;
			}
			if ( ! empty( $cat_ids ) && empty( array_intersect( $pcats, $cat_ids ) ) ) {
				continue;
			}
			$eligible_found = true;
		}

		if ( ! $eligible_found ) {
			if ( $coupon->get_exclude_sale_items() ) {
				return new WP_Error( 113, 'Sorry, this coupon is not valid for sale items.' );
			}
			if ( ! empty( $cat_ids ) || ! empty( $excl_cats ) ) {
				return new WP_Error( 114, 'Sorry, this coupon is not applicable to the categories in your cart.' );
			}
			return new WP_Error( 101, 'Sorry, this coupon is not applicable to selected products.' );
		}

		return true;
	}
}

class Mock_WC_Session_Coupon {
	public bool $has_cookie = false;
	public function has_session(): bool { return $this->has_cookie; }
	public function set_customer_session_cookie( bool $set ): void { $this->has_cookie = $set; }
}

class Mock_WC_Cart_Coupon {
	private array $items = array();
	private array $applied_coupons = array();
	private array $coupon_discount_totals = array();
	private float $subtotal = 0.0;
	private float $discount_total = 0.0;
	private float $tax_total = 0.0;
	private float $total = 0.0;

	public function add_to_cart( int $product_id, int $quantity = 1 ) {
		$p   = wc_get_product( $product_id );
		$key = md5( (string) $product_id );
		if ( isset( $this->items[ $key ] ) ) {
			$this->items[ $key ]['quantity'] += $quantity;
		} else {
			$this->items[ $key ] = array(
				'key'           => $key,
				'product_id'    => $product_id,
				'variation_id'  => 0,
				'variation'     => array(),
				'quantity'      => $quantity,
				'data'          => $p,
				'line_subtotal' => $p->get_price() * $quantity,
			);
		}
		$this->calculate_totals();
		return $key;
	}

	public function remove_cart_item( string $key ): bool {
		unset( $this->items[ $key ] );
		return true;
	}

	public function apply_coupon( string $code ): bool {
		$code   = wc_format_coupon_code( $code );
		$coupon = new WC_Coupon( $code );
		$disc   = new WC_Discounts( $this );
		$valid  = $disc->is_coupon_valid( $coupon );
		if ( is_wp_error( $valid ) ) {
			wc_add_notice( $valid->get_error_message(), 'error' );
			return false;
		}

		// WooCommerce individual use check against existing applied coupons.
		foreach ( $this->applied_coupons as $existing_code ) {
			$existing = new WC_Coupon( $existing_code );
			if ( $existing->get_individual_use() ) {
				wc_add_notice( sprintf( 'Sorry, coupon "%s" has already been applied and cannot be used in conjunction with other coupons.', $existing_code ), 'error' );
				return false;
			}
		}

		if ( $coupon->get_individual_use() ) {
			$this->applied_coupons = array( $code );
		} else {
			$this->applied_coupons[] = $code;
		}

		$this->calculate_totals();
		return true;
	}

	public function remove_coupon( string $code ): bool {
		$code = wc_format_coupon_code( $code );
		$idx  = array_search( $code, $this->applied_coupons, true );
		if ( false === $idx ) {
			return false;
		}
		unset( $this->applied_coupons[ $idx ] );
		$this->applied_coupons = array_values( $this->applied_coupons );
		$this->calculate_totals();
		return true;
	}

	public function has_discount( string $code = '' ): bool {
		return '' === $code ? ! empty( $this->applied_coupons ) : in_array( wc_format_coupon_code( $code ), $this->applied_coupons, true );
	}

	public function get_applied_coupons(): array { return $this->applied_coupons; }
	public function get_coupon_discount_amount( string $code ): float { return (float) ( $this->coupon_discount_totals[ wc_format_coupon_code( $code ) ] ?? 0.0 ); }
	public function get_coupon_discount_tax_amount( string $code ): float { return 0.0; }
	public function get_discount_total(): float { return $this->discount_total; }
	public function get_discount_tax(): float { return 0.0; }
	public function get_total_tax(): float { return $this->tax_total; }

	public function calculate_totals(): void {
		$sub = 0.0;
		foreach ( $this->items as $k => $item ) {
			$line = $item['data']->get_price() * $item['quantity'];
			$this->items[ $k ]['line_subtotal'] = $line;
			$sub += $line;
		}
		$this->subtotal = $sub;

		$this->coupon_discount_totals = array();
		$total_discount               = 0.0;

		foreach ( $this->applied_coupons as $code ) {
			$c   = new WC_Coupon( $code );
			$amt = 'percent' === $c->get_discount_type()
				? round( $sub * ( $c->get_amount() / 100.0 ), 0 )
				: min( $sub - $total_discount, $c->get_amount() );
			$amt = max( 0.0, $amt );
			$this->coupon_discount_totals[ $code ] = $amt;
			$total_discount += $amt;
		}

		$this->discount_total = min( $sub, $total_discount );
		$net                  = max( 0.0, $sub - $this->discount_total );
		$this->tax_total      = round( $net * 0.1, 0 ); // Simulate 10% VAT recalculated after discount.
		$this->total          = $net + $this->tax_total;
	}

	public function get_cart(): array { return $this->items; }
	public function get_cart_contents_count(): int { return array_sum( array_column( $this->items, 'quantity' ) ); }
	public function get_subtotal(): float { return $this->subtotal; }
	public function get_total( string $ctx = 'view' ) { return 'edit' === $ctx ? $this->total : wc_price( $this->total ); }
	public function get_cart_subtotal(): string { return wc_price( $this->subtotal ); }
	public function get_cart_total(): string { return wc_price( $this->total ); }
	public function is_empty(): bool { return empty( $this->items ); }
	public function needs_shipping(): bool { return ! empty( $this->items ); }
	public function empty_cart( bool $clear_persistent = true ): void {
		$this->items                  = array();
		$this->applied_coupons        = array();
		$this->coupon_discount_totals = array();
		$this->subtotal               = 0.0;
		$this->discount_total         = 0.0;
		$this->tax_total              = 0.0;
		$this->total                  = 0.0;
	}
}

class WooCommerce {
	public ?Mock_WC_Cart_Coupon $cart = null;
	public ?Mock_WC_Session_Coupon $session = null;
	public function __construct() {
		$this->session = new Mock_WC_Session_Coupon();
		$this->cart    = new Mock_WC_Cart_Coupon();
	}
	public function initialize_session(): void {
		if ( null === $this->session ) {
			$this->session = new Mock_WC_Session_Coupon();
		}
	}
}

function WC(): WooCommerce { global $despace_mock_wc; return $despace_mock_wc; }
function wc_get_product( int $id ) { global $despace_mock_products; return $despace_mock_products[ $id ] ?? null; }
}

$despace_mock_wc         = new WooCommerce();
$despace_mock_products   = array();
$despace_mock_coupons_db = array();

function reset_coupon_test_env(): void {
	global $despace_mock_wc, $despace_mock_products, $despace_mock_coupons_db;
	global $despace_mock_logged_in, $despace_mock_current_user_id, $despace_mock_coupons_enabled, $despace_mock_stock_hook_calls;

	$despace_mock_logged_in        = false;
	$despace_mock_current_user_id  = 0;
	$despace_mock_coupons_enabled  = true;
	$despace_mock_stock_hook_calls = array();
	$despace_mock_wc->session      = new Mock_WC_Session_Coupon();
	$despace_mock_wc->cart->empty_cart();
	wc_clear_notices();

	$despace_mock_products = array(
		101 => new Mock_WC_Product_For_Coupon( array(
			'id'           => 101,
			'name'         => 'Tủ giày Nord',
			'price'        => 4500000.0,
			'category_ids' => array( 10 ),
			'stock_qty'    => 10,
		) ),
		102 => new Mock_WC_Product_For_Coupon( array(
			'id'           => 102,
			'name'         => 'Ghế giảm giá',
			'price'        => 2000000.0,
			'on_sale'      => true,
			'category_ids' => array( 20 ),
			'stock_qty'    => 8,
		) ),
		103 => new Mock_WC_Product_For_Coupon( array(
			'id'           => 103,
			'name'         => 'Tủ áo Lumi',
			'price'        => 6000000.0,
			'category_ids' => array( 30 ),
			'stock_qty'    => 6,
		) ),
	);

	$despace_mock_coupons_db = array(
		'despace10' => array(
			'id'                 => 501,
			'discount_type'      => 'percent',
			'amount'             => 10.0,
			'usage_limit'        => 50,
			'usage_count'        => 3,
			'used_by'            => array( 999, 'other@vip.test' ),
			'email_restrictions' => array( 'secret@vip.test' ),
		),
		'giam500k' => array(
			'id'             => 502,
			'discount_type'  => 'fixed_cart',
			'amount'         => 500000.0,
			'minimum_amount' => 4000000.0,
		),
		'draftonly' => array(
			'id'     => 503,
			'status' => 'draft',
			'amount' => 50.0,
		),
		'expired25' => array(
			'id'           => 504,
			'amount'       => 15.0,
			'date_expires' => time() - 86400,
		),
		'future2027' => array(
			'id'          => 505,
			'amount'      => 15.0,
			'date_starts' => time() + 86400,
		),
		'limitmax' => array(
			'id'          => 506,
			'amount'      => 15.0,
			'usage_limit' => 5,
			'usage_count' => 5,
		),
		'limituser' => array(
			'id'                   => 507,
			'amount'               => 15.0,
			'usage_limit_per_user' => 1,
			'used_by'              => array( 42 ),
		),
		'min10m' => array(
			'id'             => 508,
			'amount'         => 10.0,
			'minimum_amount' => 10000000.0,
		),
		'max5m' => array(
			'id'             => 509,
			'amount'         => 10.0,
			'maximum_amount' => 5000000.0,
		),
		'onlywardrobe' => array(
			'id'                 => 510,
			'amount'             => 10.0,
			'product_ids'        => array( 103 ),
			'product_categories' => array( 30 ),
		),
		'nosale' => array(
			'id'                 => 511,
			'amount'             => 10.0,
			'exclude_sale_items' => true,
		),
		'solo20' => array(
			'id'             => 512,
			'discount_type'  => 'percent',
			'amount'         => 20.0,
			'individual_use' => true,
		),
	);
}

CheckoutOrdersModule::init();

class M3CouponUnitRunner {
	private array $results = array();

	public function run(): void {
		global $despace_mock_actions;
		echo "========================================================================\n";
		echo "      DeSpace M3 Phase 1B-A — COUPON BACKEND UNIT TEST SUITE            \n";
		echo "========================================================================\n\n";

		$cfg    = apply_filters( 'despace_m3_cart_frontend_config', array() );
		$initOk = CouponValidator::are_hooks_registered()
			&& 'despace_coupon_apply' === ( $cfg['actions']['apply_coupon'] ?? '' )
			&& 'despace_coupon_remove' === ( $cfg['actions']['remove_coupon'] ?? '' )
			&& '.despace-cart-coupons' === ( $cfg['fragments']['coupons'] ?? '' )
			&& ! empty( $despace_mock_actions['wp_ajax_despace_coupon_apply'] )
			&& ! empty( $despace_mock_actions['wp_ajax_nopriv_despace_coupon_apply'] )
			&& ! empty( $despace_mock_actions['wp_ajax_despace_coupon_remove'] )
			&& ! empty( $despace_mock_actions['wp_ajax_nopriv_despace_coupon_remove'] );
		$this->record( 'CPN-INIT', 'Module registers CouponValidator & frontend config', $initOk );

		$this->tc_01_valid_coupon_and_recalculate();
		$this->tc_02_invalid_and_draft_coupon();
		$this->tc_03_expired_coupon();
		$this->tc_04_not_started_coupon();
		$this->tc_05_usage_limit_global_and_per_user();
		$this->tc_06_minimum_spend();
		$this->tc_07_maximum_spend();
		$this->tc_08_product_and_sale_restrictions();
		$this->tc_09_category_restriction();
		$this->tc_10_individual_use_conflict_and_replace();
		$this->tc_11_duplicate_coupon();
		$this->tc_12_empty_cart_rejected();
		$this->tc_13_remove_coupon_and_recalculate();
		$this->tc_14_security_nonce_method_spoof_and_no_leak();
		$this->tc_15_guest_customer_and_fragments();
		$this->tc_16_no_stock_mutation_or_m4_inventory_logs();

		$this->print_summary();
	}

	private function record( string $id, string $desc, bool $passed ): void {
		printf( "[%-10s] %-52s : %s\n", $id, $desc, $passed ? 'PASS' : 'FAIL' );
		$this->results[ $id ] = $passed;
	}

	private function tc_01_valid_coupon_and_recalculate(): void {
		reset_coupon_test_env();
		WC()->cart->add_to_cart( 101, 1 ); // Subtotal 4,500,000.
		$res = CouponValidator::apply_coupon( '  DESPACE10  ' );
		// 10% of 4,500,000 = 450,000; net = 4,050,000; tax 10% = 405,000; total = 4,455,000.
		$ok = ! is_wp_error( $res )
			&& 'despace10' === $res['coupon_code']
			&& 4500000.0 === $res['cart']['subtotal']
			&& 450000.0 === $res['cart']['discount_total']
			&& 405000.0 === $res['cart']['tax_total']
			&& 4455000.0 === $res['cart']['total'];
		$this->record( 'TC-CPN-01', 'Apply valid coupon & recalculate discount/tax/total', $ok );
	}

	private function tc_02_invalid_and_draft_coupon(): void {
		reset_coupon_test_env();
		WC()->cart->add_to_cart( 101, 1 );
		$bad   = CouponValidator::apply_coupon( 'KHONGTONTAI' );
		$draft = CouponValidator::apply_coupon( 'DRAFTONLY' );
		$ok    = is_wp_error( $bad ) && 'despace_coupon_invalid' === $bad->get_error_code()
			&& is_wp_error( $draft ) && 'despace_coupon_invalid' === $draft->get_error_code();
		$this->record( 'TC-CPN-02', 'Reject nonexistent & unpublished draft coupons', $ok );
	}

	private function tc_03_expired_coupon(): void {
		reset_coupon_test_env();
		WC()->cart->add_to_cart( 101, 1 );
		$res = CouponValidator::apply_coupon( 'EXPIRED25' );
		$ok  = is_wp_error( $res ) && 'despace_coupon_expired' === $res->get_error_code();
		$this->record( 'TC-CPN-03', 'Reject expired coupon', $ok );
	}

	private function tc_04_not_started_coupon(): void {
		reset_coupon_test_env();
		WC()->cart->add_to_cart( 101, 1 );
		$res = CouponValidator::apply_coupon( 'FUTURE2027' );
		$ok  = is_wp_error( $res ) && 'despace_coupon_not_started' === $res->get_error_code();
		$this->record( 'TC-CPN-04', 'Reject coupon before start date', $ok );
	}

	private function tc_05_usage_limit_global_and_per_user(): void {
		global $despace_mock_logged_in, $despace_mock_current_user_id;
		reset_coupon_test_env();
		WC()->cart->add_to_cart( 101, 1 );

		$global_limit = CouponValidator::apply_coupon( 'LIMITMAX' );

		$despace_mock_logged_in       = true;
		$despace_mock_current_user_id = 42;
		$user_limit                   = CouponValidator::apply_coupon( 'LIMITUSER' );

		$ok = is_wp_error( $global_limit ) && 'despace_coupon_usage_limit_reached' === $global_limit->get_error_code()
			&& is_wp_error( $user_limit ) && 'despace_coupon_usage_limit_reached' === $user_limit->get_error_code();
		$this->record( 'TC-CPN-05', 'Reject global & per-user usage limit reached', $ok );
	}

	private function tc_06_minimum_spend(): void {
		reset_coupon_test_env();
		WC()->cart->add_to_cart( 101, 1 ); // 4.5M < 10M.
		$fail = CouponValidator::apply_coupon( 'MIN10M' );

		WC()->cart->add_to_cart( 103, 1 ); // 4.5M + 6M = 10.5M >= 10M.
		$pass = CouponValidator::apply_coupon( 'MIN10M' );

		$ok = is_wp_error( $fail ) && 'despace_coupon_min_spend_not_met' === $fail->get_error_code()
			&& ! is_wp_error( $pass ) && 1050000.0 === $pass['cart']['discount_total'];
		$this->record( 'TC-CPN-06', 'Enforce minimum spend threshold accurately', $ok );
	}

	private function tc_07_maximum_spend(): void {
		reset_coupon_test_env();
		WC()->cart->add_to_cart( 103, 1 ); // 6M > 5M.
		$res = CouponValidator::apply_coupon( 'MAX5M' );
		$ok  = is_wp_error( $res ) && 'despace_coupon_max_spend_exceeded' === $res->get_error_code();
		$this->record( 'TC-CPN-07', 'Reject cart exceeding maximum spend', $ok );
	}

	private function tc_08_product_and_sale_restrictions(): void {
		reset_coupon_test_env();
		WC()->cart->add_to_cart( 102, 1 ); // On-sale item in cat 20.
		$sale_rejected = CouponValidator::apply_coupon( 'NOSALE' );
		$prod_rejected = CouponValidator::apply_coupon( 'ONLYWARDROBE' );

		$ok = is_wp_error( $sale_rejected ) && 'despace_coupon_exclude_sale_items' === $sale_rejected->get_error_code()
			&& is_wp_error( $prod_rejected );
		$this->record( 'TC-CPN-08', 'Reject restricted product & excluded sale items', $ok );
	}

	private function tc_09_category_restriction(): void {
		reset_coupon_test_env();
		WC()->cart->add_to_cart( 101, 1 ); // Category 10 only; ONLYWARDROBE requires 30.
		$fail = CouponValidator::apply_coupon( 'ONLYWARDROBE' );

		WC()->cart->add_to_cart( 103, 1 ); // Add product in category 30.
		$pass = CouponValidator::apply_coupon( 'ONLYWARDROBE' );

		$ok = is_wp_error( $fail ) && 'despace_coupon_category_restricted' === $fail->get_error_code()
			&& ! is_wp_error( $pass );
		$this->record( 'TC-CPN-09', 'Enforce product category restriction', $ok );
	}

	private function tc_10_individual_use_conflict_and_replace(): void {
		reset_coupon_test_env();
		WC()->cart->add_to_cart( 101, 1 ); // 4.5M.

		// Individual coupon replaces existing regular coupon per WC rule.
		CouponValidator::apply_coupon( 'DESPACE10' );
		$solo = CouponValidator::apply_coupon( 'SOLO20' );

		// Verify unrelated session notices remain intact while failed coupon returns exact error.
		wc_add_notice( 'Unrelated shipping info', 'notice' );
		$conflict = CouponValidator::apply_coupon( 'GIAM500K' );
		$preserved_notices = wc_get_notices( 'notice' );

		$ok = ! is_wp_error( $solo )
			&& 1 === count( $solo['cart']['applied_coupons'] )
			&& 'solo20' === $solo['cart']['applied_coupons'][0]['code']
			&& 900000.0 === $solo['cart']['discount_total']
			&& is_wp_error( $conflict ) && 'despace_coupon_rejected' === $conflict->get_error_code()
			&& false !== strpos( $conflict->get_error_message(), 'cannot be used in conjunction' )
			&& 1 === count( $preserved_notices )
			&& 'Unrelated shipping info' === ( $preserved_notices[0]['notice'] ?? '' );
		$this->record( 'TC-CPN-10', 'Enforce individual-use coupon rules via WC', $ok );
	}

	private function tc_11_duplicate_coupon(): void {
		reset_coupon_test_env();
		WC()->cart->add_to_cart( 101, 1 );
		$first = CouponValidator::apply_coupon( 'DESPACE10' );
		$dup   = CouponValidator::apply_coupon( 'despace10' );
		$ok    = ! is_wp_error( $first )
			&& is_wp_error( $dup ) && 'despace_coupon_already_applied' === $dup->get_error_code();
		$this->record( 'TC-CPN-11', 'Reject duplicate coupon already in cart', $ok );
	}

	private function tc_12_empty_cart_rejected(): void {
		reset_coupon_test_env();
		$res = CouponValidator::apply_coupon( 'DESPACE10' );
		$ok  = is_wp_error( $res ) && 'despace_empty_cart' === $res->get_error_code();
		$this->record( 'TC-CPN-12', 'Reject applying coupon when cart is empty', $ok );
	}

	private function tc_13_remove_coupon_and_recalculate(): void {
		reset_coupon_test_env();
		WC()->cart->add_to_cart( 101, 1 ); // 4.5M -> with 10% tax = 4,950,000.
		CouponValidator::apply_coupon( 'DESPACE10' );
		$removed = CouponValidator::remove_coupon( 'DESPACE10' );
		$missing = CouponValidator::remove_coupon( 'DESPACE10' );

		$ok = ! is_wp_error( $removed )
			&& empty( $removed['cart']['applied_coupons'] )
			&& 0.0 === $removed['cart']['discount_total']
			&& 450000.0 === $removed['cart']['tax_total']
			&& 4950000.0 === $removed['cart']['total']
			&& is_wp_error( $missing ) && 'despace_coupon_not_applied' === $missing->get_error_code();
		$this->record( 'TC-CPN-13', 'Remove coupon, recalculate totals & reject non-applied', $ok );
	}

	private function tc_14_security_nonce_method_spoof_and_no_leak(): void {
		reset_coupon_test_env();
		WC()->cart->add_to_cart( 101, 1 );

		// 1. Missing & invalid nonce rejected (403).
		$no_nonce_apply = CouponValidator::handle_apply_request( array( 'coupon_code' => 'DESPACE10' ) );
		$bad_nonce_rem  = CouponValidator::handle_remove_request( array( 'nonce' => 'bad', 'coupon_code' => 'DESPACE10' ) );

		// 2. Non-POST HTTP method rejected (405).
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$method_apply_status       = 0;
		$method_remove_status      = 0;
		try {
			CouponValidator::ajax_apply_coupon();
		} catch ( MockAjaxExitException $e ) {
			$method_apply_status = $e->status_code;
		}
		try {
			CouponValidator::ajax_remove_coupon();
		} catch ( MockAjaxExitException $e ) {
			$method_remove_status = $e->status_code;
		}
		$_SERVER['REQUEST_METHOD'] = 'POST';

		// 3. Forged discount/total spoofing ignored; internal coupon fields not leaked.
		$spoof = CouponValidator::handle_apply_request( array(
			'nonce'           => wp_create_nonce( CartHandler::NONCE_ACTION ),
			'coupon_code'     => 'DESPACE10',
			'discount_amount' => 4499999,
			'discount_type'   => 'fixed_cart',
			'total'           => 1,
		) );

		$json_dump = json_encode( $spoof );
		$no_leak   = false === strpos( (string) $json_dump, 'secret@vip.test' )
			&& false === strpos( (string) $json_dump, 'other@vip.test' )
			&& false === strpos( (string) $json_dump, 'usage_limit' )
			&& false === strpos( (string) $json_dump, 'used_by' );

		$ok = false === $no_nonce_apply['success'] && 403 === $no_nonce_apply['status']
			&& false === $bad_nonce_rem['success'] && 403 === $bad_nonce_rem['status']
			&& 405 === $method_apply_status && 405 === $method_remove_status
			&& ! empty( $spoof['success'] )
			&& 450000.0 === $spoof['data']['cart']['discount_total']
			&& 4455000.0 === $spoof['data']['cart']['total']
			&& $no_leak;
		$this->record( 'TC-CPN-14', 'Enforce nonce, POST-only, anti-spoof & no internal leak', $ok );
	}

	private function tc_15_guest_customer_and_fragments(): void {
		global $despace_mock_logged_in;
		reset_coupon_test_env();
		$despace_mock_logged_in = false;
		WC()->cart->add_to_cart( 101, 1 );

		$apply_res = CouponValidator::handle_apply_request( array(
			'nonce'       => wp_create_nonce( CartHandler::NONCE_ACTION ),
			'coupon_code' => 'DESPACE10',
		) );

		$frags = apply_filters( 'woocommerce_add_to_cart_fragments', array() );

		$rem_res = CouponValidator::handle_remove_request( array(
			'nonce'       => wp_create_nonce( CartHandler::NONCE_ACTION ),
			'coupon_code' => 'DESPACE10',
		) );

		$ok = ! empty( $apply_res['success'] )
			&& 'guest' === $apply_res['data']['cart']['user_context']
			&& true === WC()->session->has_session()
			&& isset( $frags['.despace-cart-discount'], $frags['.despace-cart-tax'], $frags['.despace-cart-coupons'] )
			&& false !== strpos( $frags['.despace-cart-coupons'], 'despace10' )
			&& ! empty( $rem_res['success'] )
			&& 0.0 === $rem_res['data']['cart']['discount_total'];
		$this->record( 'TC-CPN-15', 'Support guest AJAX coupon apply/remove & fragments', $ok );
	}

	private function tc_16_no_stock_mutation_or_m4_inventory_logs(): void {
		global $despace_mock_stock_hook_calls;
		reset_coupon_test_env();
		WC()->cart->add_to_cart( 101, 2 );

		$stock_before = wc_get_product( 101 )->get_stock_quantity();
		CouponValidator::apply_coupon( 'DESPACE10' );
		CouponValidator::remove_coupon( 'DESPACE10' );
		$stock_after  = wc_get_product( 101 )->get_stock_quantity();

		$src       = (string) file_get_contents( __DIR__ . '/../wordpress/wp-content/plugins/despace-core/includes/Checkout-Orders/Cart/CouponValidator.php' );
		$forbidden = array(
			'despace_inventory_logs',
			'InventoryModule',
			'InventoryEventRecorder',
			'wc_update_product_stock',
			'set_stock_quantity',
			'wc_reduce_stock_levels',
			'wc_restore_stock_levels',
			'$wpdb',
		);
		$clean = true;
		foreach ( $forbidden as $token ) {
			if ( false !== strpos( $src, $token ) ) {
				$clean = false;
			}
		}

		$ok = 10 === $stock_before
			&& 10 === $stock_after
			&& empty( $despace_mock_stock_hook_calls )
			&& $clean;
		$this->record( 'TC-CPN-16', 'Coupon never mutates stock or triggers M4 logs', $ok );
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

$runner = new M3CouponUnitRunner();
$runner->run();

