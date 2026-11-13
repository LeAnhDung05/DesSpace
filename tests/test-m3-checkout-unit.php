<?php
/**
 * DeSpace M3 Phase 2A — Checkout Validation, Address Processing & Order Meta Unit Test Suite.
 *
 * Covers all 28 required Checkout Issue #17 unit checks: Cart integrity, price tampering,
 * coupons, shipping rates/zones, Vietnam 2-tier addresses, recipient email/phone, guest vs
 * customer checkout, billing/shipping split, HPOS OrderMetaSaver, legacy address_2 safety,
 * M4 inventory boundaries, and native checkout lifecycle compliance.
 *
 * Run via CLI: php tests/test-m3-checkout-unit.php
 *
 * @package DeSpace\Tests
 */

define( 'ABSPATH', __DIR__ . '/../' );

$despace_mock_actions       = array();
$despace_mock_filters       = array();
$despace_mock_options       = array( 'woocommerce_enable_guest_checkout' => 'yes' );
$despace_mock_current_user  = 0;
$despace_mock_products      = array();
$despace_mock_coupons       = array();
$despace_mock_notices       = array();
$despace_mock_stock_calls   = 0;
$despace_mock_inv_events    = 0;

class WP_Error {
	private array $errors = array();
	private array $error_data = array();

	public function __construct( string $code = '', string $message = '', $data = '' ) {
		if ( '' !== $code ) {
			$this->add( $code, $message, $data );
		}
	}

	public function add( string $code, string $message, $data = '' ): void {
		$this->errors[ $code ][] = $message;
		if ( '' !== $data ) {
			$this->error_data[ $code ][] = $data;
		}
	}

	public function has_errors(): bool { return ! empty( $this->errors ); }
	public function get_error_codes(): array { return array_keys( $this->errors ); }
	public function get_error_code(): string { $codes = $this->get_error_codes(); return $codes[0] ?? ''; }
	public function get_error_messages( string $code = '' ): array {
		if ( '' !== $code ) {
			return $this->errors[ $code ] ?? array();
		}
		$all = array();
		foreach ( $this->errors as $msgs ) {
			foreach ( $msgs as $m ) {
				$all[] = $m;
			}
		}
		return $all;
	}
	public function get_error_message( string $code = '' ): string {
		if ( '' === $code ) {
			$code = $this->get_error_code();
		}
		return $this->errors[ $code ][0] ?? '';
	}
	public function get_error_data( string $code = '' ) {
		if ( '' === $code ) {
			$code = $this->get_error_code();
		}
		return $this->error_data[ $code ][0] ?? null;
	}
}

function is_wp_error( $thing ): bool { return $thing instanceof WP_Error; }
function add_action( string $tag, callable $cb, int $prio = 10, int $args = 1 ): bool {
	global $despace_mock_actions;
	$despace_mock_actions[ $tag ][] = array( 'callback' => $cb, 'priority' => $prio );
	return true;
}
function do_action( string $tag, ...$args ): void {
	global $despace_mock_actions;
	$buckets = $despace_mock_actions[ $tag ] ?? array();
	usort( $buckets, fn( $a, $b ) => $a['priority'] <=> $b['priority'] );
	foreach ( $buckets as $hook ) {
		call_user_func_array( $hook['callback'], $args );
	}
}
function add_filter( string $tag, callable $cb, int $prio = 10, int $args = 1 ): bool {
	global $despace_mock_filters;
	$despace_mock_filters[ $tag ][] = array( 'callback' => $cb, 'priority' => $prio );
	return true;
}
function apply_filters( string $tag, $value, ...$args ) {
	global $despace_mock_filters;
	$buckets = $despace_mock_filters[ $tag ] ?? array();
	usort( $buckets, fn( $a, $b ) => $a['priority'] <=> $b['priority'] );
	foreach ( $buckets as $hook ) {
		$value = call_user_func( $hook['callback'], $value, ...$args );
	}
	return $value;
}
function sanitize_text_field( $str ): string { return trim( strip_tags( (string) $str ) ); }
function sanitize_key( $key ): string { return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $key ) ); }
function absint( $val ): int { return abs( (int) $val ); }
function wp_unslash( $value ) { return $value; }
function esc_html( string $text ): string { return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' ); }
function __( string $text, string $domain = 'default' ): string { return $text; }
function admin_url( string $path = '' ): string { return 'https://despace.test/wp-admin/' . ltrim( $path, '/' ); }
function is_email( $email ) { return filter_var( (string) $email, FILTER_VALIDATE_EMAIL ) ? (string) $email : false; }
function get_current_user_id(): int { global $despace_mock_current_user; return (int) $despace_mock_current_user; }
function is_user_logged_in(): bool { return get_current_user_id() > 0; }
function get_option( string $opt, $default = false ) {
	global $despace_mock_options;
	return array_key_exists( $opt, $despace_mock_options ) ? $despace_mock_options[ $opt ] : $default;
}
function wp_create_nonce( $action = -1 ): string { return 'valid_nonce_' . $action; }
function wp_verify_nonce( string $nonce, $action = -1 ) { return ( 'valid_nonce_' . $action ) === $nonce ? 1 : false; }
function wc_add_notice( string $msg, string $type = 'success' ): void {
	global $despace_mock_notices;
	$despace_mock_notices[ $type ][] = $msg;
}
function wc_price( float $price ): string { return number_format( $price, 0, ',', '.' ) . ' ₫'; }
function wc_get_product( int $id ) {
	global $despace_mock_products;
	return $despace_mock_products[ $id ] ?? false;
}

class Mock_Checkout_Product {
	public int $id;
	public int $parent_id = 0;
	public string $type = 'simple';
	public string $name = 'Armchair Bắc Âu';
	public string $status = 'publish';
	public bool $purchasable = true;
	public bool $in_stock = true;
	public bool $manage_stock = true;
	public int $stock_qty = 10;
	public bool $sold_individually = false;
	public string $price = '4500000';

	public function __construct( int $id, array $props = array() ) {
		$this->id = $id;
		foreach ( $props as $k => $v ) {
			$this->$k = $v;
		}
	}
	public function exists(): bool { return 'trash' !== $this->status; }
	public function get_id(): int { return $this->id; }
	public function get_parent_id(): int { return $this->parent_id; }
	public function is_type( $t ): bool { return is_array( $t ) ? in_array( $this->type, $t, true ) : $this->type === $t; }
	public function get_name(): string { return $this->name; }
	public function get_status(): string { return $this->status; }
	public function is_purchasable(): bool { return $this->purchasable; }
	public function is_in_stock(): bool { return $this->in_stock; }
	public function is_sold_individually(): bool { return $this->sold_individually; }
	public function get_price(): string { return $this->price; }
	public function get_stock_managed_by_id(): int { return ( 'variation' === $this->type && ! $this->manage_stock && $this->parent_id > 0 ) ? $this->parent_id : $this->id; }
	public function has_enough_stock( int $qty ): bool {
		if ( ! $this->in_stock ) return false;
		if ( ! $this->manage_stock ) return true;
		return $this->stock_qty >= $qty;
	}
}

class WC_Coupon {
	public string $code;
	public int $id = 0;
	public string $status = 'publish';
	public string $discount_type = 'percent';
	public float $amount = 10.0;
	public ?int $expires_ts = null;
	public int $usage_limit = 0;
	public int $usage_count = 0;
	public bool $individual_use = false;
	public array $product_ids = array();
	public float $minimum_amount = 0.0;

	public function __construct( string $code = '' ) {
		global $despace_mock_coupons;
		$this->code = strtolower( trim( $code ) );
		if ( isset( $despace_mock_coupons[ $this->code ] ) ) {
			foreach ( $despace_mock_coupons[ $this->code ] as $k => $v ) {
				$this->$k = $v;
			}
		}
	}
	public function get_id(): int { return $this->id; }
	public function get_code(): string { return $this->code; }
	public function get_status(): string { return $this->status; }
	public function get_discount_type(): string { return $this->discount_type; }
	public function get_amount(): float { return $this->amount; }
	public function get_individual_use(): bool { return $this->individual_use; }
	public function get_usage_limit(): int { return $this->usage_limit; }
	public function get_usage_count(): int { return $this->usage_count; }
	public function get_date_expires(): ?int { return $this->expires_ts; }
	public function get_product_ids(): array { return $this->product_ids; }
	public function get_minimum_amount(): float { return $this->minimum_amount; }
}

class WC_Discounts {
	private object $cart;
	public function __construct( object $cart ) { $this->cart = $cart; }
	public function is_coupon_valid( WC_Coupon $coupon ) {
		if ( $coupon->get_id() <= 0 || 'publish' !== $coupon->get_status() ) {
			return new WP_Error( 'invalid_coupon', 'Coupon does not exist.' );
		}
		if ( null !== $coupon->get_date_expires() && $coupon->get_date_expires() < time() ) {
			return new WP_Error( 'coupon_expired', 'Coupon has expired.' );
		}
		if ( $coupon->get_usage_limit() > 0 && $coupon->get_usage_count() >= $coupon->get_usage_limit() ) {
			return new WP_Error( 'coupon_usage_limit_reached', 'Coupon usage limit has been reached.' );
		}
		if ( $coupon->get_minimum_amount() > 0 && $this->cart->get_subtotal() < $coupon->get_minimum_amount() ) {
			return new WP_Error( 'coupon_min_spend', 'Order subtotal does not meet coupon minimum spend.' );
		}
		$allowed_pids = $coupon->get_product_ids();
		if ( ! empty( $allowed_pids ) ) {
			$matched = false;
			foreach ( $this->cart->get_cart() as $item ) {
				if ( in_array( (int) $item['product_id'], $allowed_pids, true ) ) {
					$matched = true;
					break;
				}
			}
			if ( ! $matched ) {
				return new WP_Error( 'coupon_not_applicable', 'Coupon is not applicable to selected products.' );
			}
		}
		return true;
	}
}

class Mock_Checkout_Shipping_Rate {
	private string $id;
	private string $method_id;
	private string $label;
	private float $cost;
	public function __construct( string $id, string $method_id, string $label, float $cost ) {
		$this->id        = $id;
		$this->method_id = $method_id;
		$this->label     = $label;
		$this->cost      = $cost;
	}
	public function get_id(): string { return $this->id; }
	public function get_method_id(): string { return $this->method_id; }
	public function get_label(): string { return $this->label; }
	public function get_cost(): float { return $this->cost; }
	public function get_taxes(): array { return array(); }
}

class WC_Shipping_Zones {
	public static array $zones = array();
	public static function get_zones(): array { return self::$zones; }
}

class Mock_Checkout_Customer {
	public string $billing_country = 'VN';
	public string $billing_state = '15';
	public string $billing_city = 'Phường Cam Đường';
	public string $billing_address_1 = '15 Trần Hưng Đạo';
	public string $shipping_country = 'VN';
	public string $shipping_state = '15';
	public string $shipping_city = 'Phường Cam Đường';
	public string $shipping_address_1 = '15 Trần Hưng Đạo';
	public string $shipping_postcode = '';
	public bool $saved_profile_unchanged_flag = true;
	public function set_billing_state( string $v ): void { $this->billing_state = $v; }
	public function set_billing_city( string $v ): void { $this->billing_city = $v; }
	public function set_shipping_country( string $v ): void { $this->shipping_country = $v; }
	public function set_shipping_state( string $v ): void { $this->shipping_state = $v; }
	public function set_shipping_city( string $v ): void { $this->shipping_city = $v; }
	public function set_shipping_address_1( string $v ): void { $this->shipping_address_1 = $v; }
	public function set_shipping_postcode( string $v ): void { $this->shipping_postcode = $v; }
	public function set_calculated_shipping( bool $v ): void {}
	public function get_shipping_country(): string { return $this->shipping_country; }
	public function get_shipping_state(): string { return $this->shipping_state; }
	public function get_shipping_city(): string { return $this->shipping_city; }
	public function get_shipping_address_1(): string { return $this->shipping_address_1; }
	public function get_shipping_postcode(): string { return $this->shipping_postcode; }
}

class Mock_Checkout_Shipping {
	private array $packages = array();
	public function set_packages( array $p ): void { $this->packages = $p; }
	public function get_packages(): array { return $this->packages; }
	public function calculate_shipping( array $p ): array { return $this->packages; }
}

class Mock_Checkout_Session {
	private array $bag = array();
	public function get( string $k, $d = null ) { return $this->bag[ $k ] ?? $d; }
	public function set( string $k, $v ): void { $this->bag[ $k ] = $v; }
}

class Mock_Checkout_Cart {
	public array $items = array();
	public array $applied_coupons = array();
	public bool $requires_shipping = true;
	private float $subtotal = 0.0;
	private float $discount_total = 0.0;
	private float $shipping_total = 0.0;
	private float $total = 0.0;

	public function is_empty(): bool { return empty( $this->items ); }
	public function needs_shipping(): bool { return $this->requires_shipping; }
	public function get_cart(): array { return $this->items; }
	public function get_applied_coupons(): array { return $this->applied_coupons; }
	public function check_cart_coupons(): bool { return true; }
	public function calculate_shipping(): void {
		$country = strtoupper( WC()->customer->get_shipping_country() );
		$state   = strtoupper( WC()->customer->get_shipping_state() );
		$pc      = strtoupper( trim( WC()->customer->get_shipping_postcode() ) );
		$rates   = array();

		foreach ( WC_Shipping_Zones::$zones as $zone ) {
			$locs   = $zone['zone_locations'] ?? array();
			$geo_ok = empty( $locs );
			$pc_rules = array();
			foreach ( $locs as $loc ) {
				$t = $loc['type'] ?? '';
				$c = strtoupper( (string) ( $loc['code'] ?? '' ) );
				if ( 'postcode' === $t ) {
					$pc_rules[] = $c;
				} elseif ( ( 'country' === $t && $c === $country ) || ( 'state' === $t && '' !== $state && $c === ( $country . ':' . $state ) ) ) {
					$geo_ok = true;
				}
			}
			if ( ! $geo_ok ) continue;
			if ( ! empty( $pc_rules ) ) {
				if ( '' === $pc || ! in_array( $pc, $pc_rules, true ) ) continue;
			}
			$rates = $zone['rates'] ?? array();
			break;
		}

		WC()->shipping()->set_packages( array(
			array(
				'destination' => array(
					'country'  => $country,
					'state'    => $state,
					'postcode' => $pc,
				),
				'rates'       => $rates,
			),
		) );

		if ( ! empty( $rates ) ) {
			$first = reset( $rates );
			$this->shipping_total = $first->get_cost();
			WC()->session->set( 'chosen_shipping_methods', array( 0 => $first->get_id() ) );
		} else {
			$this->shipping_total = 0.0;
			WC()->session->set( 'chosen_shipping_methods', array() );
		}
	}
	public function calculate_totals(): void {
		$sub = 0.0;
		foreach ( $this->items as $key => &$item ) {
			$prod  = wc_get_product( $item['variation_id'] > 0 ? $item['variation_id'] : $item['product_id'] );
			$price = is_object( $prod ) ? (float) $prod->get_price() : 0.0;
			$qty   = (int) $item['quantity'];
			$item['line_subtotal'] = $price * $qty;
			$sub += $item['line_subtotal'];
		}
		unset( $item );
		$this->subtotal = $sub;
		$disc = 0.0;
		foreach ( $this->applied_coupons as $code ) {
			$cp = new WC_Coupon( $code );
			if ( $cp->get_id() > 0 && 'publish' === $cp->get_status() ) {
				$disc += ( 'percent' === $cp->get_discount_type() ) ? ( $sub * ( $cp->get_amount() / 100.0 ) ) : $cp->get_amount();
			}
		}
		$this->discount_total = min( $sub, $disc );
		$this->calculate_shipping();
		$this->total = max( 0.0, ( $this->subtotal - $this->discount_total ) + $this->shipping_total );
	}
	public function get_subtotal(): float { return $this->subtotal; }
	public function get_discount_total(): float { return $this->discount_total; }
	public function get_shipping_total(): float { return $this->shipping_total; }
	public function get_shipping_tax(): float { return 0.0; }
	public function get_total( string $ctx = 'view' ): float { return $this->total; }
}

class Mock_Checkout_Order {
	public array $meta = array();
	public array $props = array(
		'billing_country'    => 'VN',
		'billing_state'      => '',
		'billing_city'       => '',
		'billing_address_1'  => '',
		'billing_address_2'  => 'Quận Hoàn Kiếm',
		'shipping_country'   => 'VN',
		'shipping_state'     => '',
		'shipping_city'      => '',
		'shipping_address_1' => '',
		'shipping_address_2' => 'Quận Cầu Giấy',
	);
	public bool $saved = false;
	public function update_meta_data( string $k, $v ): void { $this->meta[ $k ] = $v; }
	public function get_meta( string $k, bool $single = true ) { return $this->meta[ $k ] ?? ''; }
	public function set_billing_country( string $v ): void { $this->props['billing_country'] = $v; }
	public function set_billing_state( string $v ): void { $this->props['billing_state'] = $v; }
	public function set_billing_city( string $v ): void { $this->props['billing_city'] = $v; }
	public function set_billing_address_1( string $v ): void { $this->props['billing_address_1'] = $v; }
	public function set_shipping_country( string $v ): void { $this->props['shipping_country'] = $v; }
	public function set_shipping_state( string $v ): void { $this->props['shipping_state'] = $v; }
	public function set_shipping_city( string $v ): void { $this->props['shipping_city'] = $v; }
	public function set_shipping_address_1( string $v ): void { $this->props['shipping_address_1'] = $v; }
	public function get_shipping_address_2(): string { return $this->props['shipping_address_2']; }
	public function get_billing_address_2(): string { return $this->props['billing_address_2']; }
	public function save(): int { $this->saved = true; return 1001; }
}

class WooCommerce {
	public Mock_Checkout_Cart $cart;
	public Mock_Checkout_Session $session;
	public Mock_Checkout_Customer $customer;
	private Mock_Checkout_Shipping $shipping;
	public function __construct() {
		$this->cart     = new Mock_Checkout_Cart();
		$this->session  = new Mock_Checkout_Session();
		$this->customer = new Mock_Checkout_Customer();
		$this->shipping = new Mock_Checkout_Shipping();
	}
	public function shipping(): Mock_Checkout_Shipping { return $this->shipping; }
}

$despace_mock_wc = new WooCommerce();
function WC(): WooCommerce { global $despace_mock_wc; return $despace_mock_wc; }

require_once __DIR__ . '/../wordpress/wp-content/plugins/despace-core/includes/Checkout-Orders/CheckoutOrdersModule.php';

use DeSpace\CheckoutOrders\CheckoutOrdersModule;
use DeSpace\CheckoutOrders\Checkout\AddressValidator;
use DeSpace\CheckoutOrders\Checkout\CheckoutValidator;
use DeSpace\CheckoutOrders\Checkout\OrderMetaSaver;
use DeSpace\CheckoutOrders\Shipping\VietnamRegions;

CheckoutOrdersModule::init();

function reset_checkout_env(): void {
	global $despace_mock_wc, $despace_mock_options, $despace_mock_current_user, $despace_mock_products, $despace_mock_coupons, $despace_mock_notices;
	$despace_mock_wc           = new WooCommerce();
	$despace_mock_options      = array( 'woocommerce_enable_guest_checkout' => 'yes' );
	$despace_mock_current_user = 0;
	$despace_mock_notices      = array();
	VietnamRegions::reset_dataset();

	$despace_mock_products = array(
		101 => new Mock_Checkout_Product( 101, array( 'name' => 'Ghế Sofa Bắc Âu', 'price' => '5000000', 'stock_qty' => 5 ) ),
		102 => new Mock_Checkout_Product( 102, array( 'type' => 'variable', 'name' => 'Bàn Ăn Gỗ Sồi', 'price' => '8000000' ) ),
		201 => new Mock_Checkout_Product( 201, array( 'type' => 'variation', 'parent_id' => 102, 'name' => 'Bàn Ăn Gỗ Sồi - Sồi Tự Nhiên', 'price' => '8200000', 'stock_qty' => 4 ) ),
		202 => new Mock_Checkout_Product( 202, array( 'type' => 'variation', 'parent_id' => 999, 'name' => 'Variation Khác Cha', 'price' => '1000000', 'stock_qty' => 4 ) ),
		103 => new Mock_Checkout_Product( 103, array( 'name' => 'Đèn Hết Hàng', 'price' => '900000', 'in_stock' => false, 'stock_qty' => 0 ) ),
		104 => new Mock_Checkout_Product( 104, array( 'name' => 'Tác Phẩm Độc Bản', 'price' => '12000000', 'sold_individually' => true, 'stock_qty' => 5 ) ),
	);

	$despace_mock_coupons = array(
		'despace10' => array( 'id' => 11, 'status' => 'publish', 'discount_type' => 'percent', 'amount' => 10.0 ),
		'expired10' => array( 'id' => 12, 'status' => 'publish', 'expires_ts' => time() - 3600 ),
		'maxedout'  => array( 'id' => 13, 'status' => 'publish', 'usage_limit' => 2, 'usage_count' => 2 ),
	);

	WC_Shipping_Zones::$zones = array(
		array(
			'zone_name'      => 'Lào Cai Zone',
			'zone_locations' => array( array( 'type' => 'state', 'code' => 'VN:15' ) ),
			'rates'          => array(
				'flat_rate:lc' => new Mock_Checkout_Shipping_Rate( 'flat_rate:lc', 'flat_rate', 'Giao hàng Lào Cai', 180000.0 ),
			),
		),
		array(
			'zone_name'      => 'Hà Nội Zone',
			'zone_locations' => array( array( 'type' => 'state', 'code' => 'VN:01' ) ),
			'rates'          => array(
				'flat_rate:hn' => new Mock_Checkout_Shipping_Rate( 'flat_rate:hn', 'flat_rate', 'Giao hàng Hà Nội', 250000.0 ),
			),
		),
	);
}

function valid_checkout_payload(): array {
	return array(
		'billing_first_name'            => 'Minh Anh',
		'billing_last_name'             => 'Nguyễn',
		'billing_email'                 => 'minhanh@example.com',
		'billing_phone'                 => '0912345678',
		'billing_country'               => 'VN',
		'billing_state'                 => '15',
		'billing_city'                  => 'Phường Lào Cai',
		'billing_despace_commune_code'  => '02647',
		'billing_address_1'             => '10 Đường Hoàng Liên',
		'shipping_method'               => array( 0 => 'flat_rate:lc' ),
	);
}

class M3CheckoutUnitRunner {
	private array $results = array();

	public function run(): void {
		echo "========================================================================\n";
		echo "      DeSpace M3 Phase 2A — CHECKOUT BACKEND UNIT TEST SUITE            \n";
		echo "========================================================================\n\n";

		$this->tc_01_empty_cart();
		$this->tc_02_valid_simple_product();
		$this->tc_03_valid_variable_product();
		$this->tc_04_invalid_variation();
		$this->tc_05_out_of_stock_product();
		$this->tc_06_quantity_exceeds_stock();
		$this->tc_07_sold_individually();
		$this->tc_08_forged_price_ignored();
		$this->tc_09_valid_coupon();
		$this->tc_10_expired_coupon();
		$this->tc_11_usage_limit_coupon();
		$this->tc_12_valid_shipping_rate();
		$this->tc_13_removed_or_stale_shipping_rate();
		$this->tc_14_no_shipping_method_for_zone();
		$this->tc_15_valid_province_15_lao_cai();
		$this->tc_16_commune_wrong_province_rejected();
		$this->tc_17_missing_address_detail_rejected();
		$this->tc_18_invalid_email_rejected();
		$this->tc_19_phone_validation_rules();
		$this->tc_20_guest_checkout();
		$this->tc_21_logged_in_customer_checkout();
		$this->tc_22_different_billing_and_shipping();
		$this->tc_23_dataset_free_text_fallback();
		$this->tc_24_hpos_order_metadata_saved();
		$this->tc_25_legacy_order_address_2_preserved();
		$this->tc_26_no_manual_stock_reduction();
		$this->tc_27_no_inventory_event_created();
		$this->tc_28_no_custom_duplicate_order_or_gateway();

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

	private function record( string $id, string $desc, bool $passed ): void {
		printf( "[%-10s] %-52s : %s\n", $id, $desc, $passed ? 'PASS' : 'FAIL' );
		$this->results[ $id ] = $passed;
	}

	private function tc_01_empty_cart(): void {
		reset_checkout_env();
		$res = CheckoutValidator::validate_checkout( valid_checkout_payload() );
		$ok  = is_wp_error( $res ) && in_array( 'despace_empty_cart', $res->get_error_codes(), true );
		$this->record( 'TC-CHK-01', 'Reject empty cart at checkout', $ok );
	}

	private function tc_02_valid_simple_product(): void {
		reset_checkout_env();
		WC()->cart->items = array( 'k1' => array( 'product_id' => 101, 'variation_id' => 0, 'quantity' => 1 ) );
		$res = CheckoutValidator::validate_checkout( valid_checkout_payload() );
		$ok  = ! is_wp_error( $res ) && true === $res['valid'] && 5180000.0 === $res['server_total'];
		$this->record( 'TC-CHK-02', 'Allow checkout with valid simple product', $ok );
	}

	private function tc_03_valid_variable_product(): void {
		reset_checkout_env();
		WC()->cart->items = array( 'k2' => array( 'product_id' => 102, 'variation_id' => 201, 'quantity' => 1 ) );
		$res = CheckoutValidator::validate_checkout( valid_checkout_payload() );
		$ok  = ! is_wp_error( $res ) && true === $res['valid'] && 8380000.0 === $res['server_total'];
		$this->record( 'TC-CHK-03', 'Allow checkout with valid variable & variation', $ok );
	}

	private function tc_04_invalid_variation(): void {
		reset_checkout_env();
		WC()->cart->items = array( 'k3' => array( 'product_id' => 102, 'variation_id' => 202, 'quantity' => 1 ) );
		$res = CheckoutValidator::validate_checkout( valid_checkout_payload() );
		$ok  = is_wp_error( $res ) && in_array( 'despace_invalid_variation', $res->get_error_codes(), true );
		$this->record( 'TC-CHK-04', 'Reject mismatched variation parent during checkout', $ok );
	}

	private function tc_05_out_of_stock_product(): void {
		reset_checkout_env();
		WC()->cart->items = array( 'k4' => array( 'product_id' => 103, 'variation_id' => 0, 'quantity' => 1 ) );
		$res = CheckoutValidator::validate_checkout( valid_checkout_payload() );
		$ok  = is_wp_error( $res ) && in_array( 'despace_product_out_of_stock', $res->get_error_codes(), true );
		$this->record( 'TC-CHK-05', 'Reject out-of-stock product before order creation', $ok );
	}

	private function tc_06_quantity_exceeds_stock(): void {
		reset_checkout_env();
		WC()->cart->items = array( 'k1' => array( 'product_id' => 101, 'variation_id' => 0, 'quantity' => 6 ) );
		$res = CheckoutValidator::validate_checkout( valid_checkout_payload() );
		$ok  = is_wp_error( $res ) && in_array( 'despace_insufficient_stock', $res->get_error_codes(), true );
		$this->record( 'TC-CHK-06', 'Reject checkout quantity exceeding current stock', $ok );
	}

	private function tc_07_sold_individually(): void {
		reset_checkout_env();
		WC()->cart->items = array( 'k5' => array( 'product_id' => 104, 'variation_id' => 0, 'quantity' => 2 ) );
		$res = CheckoutValidator::validate_checkout( valid_checkout_payload() );
		$ok  = is_wp_error( $res ) && in_array( 'despace_sold_individually_exceeded', $res->get_error_codes(), true );
		$this->record( 'TC-CHK-07', 'Enforce sold-individually limit at checkout', $ok );
	}

	private function tc_08_forged_price_ignored(): void {
		reset_checkout_env();
		WC()->cart->items = array( 'k1' => array( 'product_id' => 101, 'variation_id' => 0, 'quantity' => 1 ) );
		$payload = array_merge( valid_checkout_payload(), array(
			'price'           => 1000,
			'subtotal'        => 1000,
			'total'           => 1000,
			'order_total'     => 1000,
			'shipping_amount' => 0,
			'discount_total'  => 9999999,
		) );
		$res = CheckoutValidator::validate_checkout( $payload );
		$ok  = ! is_wp_error( $res ) && 5000000.0 === $res['server_subtotal'] && 5180000.0 === $res['server_total'];
		$this->record( 'TC-CHK-08', 'Ignore forged client price/discount/total/shipping', $ok );
	}

	private function tc_09_valid_coupon(): void {
		reset_checkout_env();
		WC()->cart->items           = array( 'k1' => array( 'product_id' => 101, 'variation_id' => 0, 'quantity' => 1 ) );
		WC()->cart->applied_coupons = array( 'despace10' );
		$res = CheckoutValidator::validate_checkout( valid_checkout_payload() );
		$ok  = ! is_wp_error( $res ) && 4680000.0 === $res['server_total'] && array( 'despace10' ) === $res['applied_coupons'];
		$this->record( 'TC-CHK-09', 'Validate eligible coupon and recompute via WC', $ok );
	}

	private function tc_10_expired_coupon(): void {
		reset_checkout_env();
		WC()->cart->items           = array( 'k1' => array( 'product_id' => 101, 'variation_id' => 0, 'quantity' => 1 ) );
		WC()->cart->applied_coupons = array( 'expired10' );
		$res = CheckoutValidator::validate_checkout( valid_checkout_payload() );
		$ok  = is_wp_error( $res ) && in_array( 'coupon_expired', $res->get_error_codes(), true );
		$this->record( 'TC-CHK-10', 'Block checkout when applied coupon expired', $ok );
	}

	private function tc_11_usage_limit_coupon(): void {
		reset_checkout_env();
		WC()->cart->items           = array( 'k1' => array( 'product_id' => 101, 'variation_id' => 0, 'quantity' => 1 ) );
		WC()->cart->applied_coupons = array( 'maxedout' );
		$res = CheckoutValidator::validate_checkout( valid_checkout_payload() );
		$ok  = is_wp_error( $res ) && in_array( 'coupon_usage_limit_reached', $res->get_error_codes(), true );
		$this->record( 'TC-CHK-11', 'Block checkout when coupon usage limit reached', $ok );
	}

	private function tc_12_valid_shipping_rate(): void {
		reset_checkout_env();
		WC()->cart->items = array( 'k1' => array( 'product_id' => 101, 'variation_id' => 0, 'quantity' => 1 ) );
		$res = CheckoutValidator::validate_checkout( valid_checkout_payload() );
		$ok  = ! is_wp_error( $res )
			&& 'available' === ( $res['shipping']['status'] ?? '' )
			&& 'province' === ( $res['shipping']['zone_match_level'] ?? '' )
			&& 'flat_rate:lc' === ( $res['shipping']['shipping_rate_id'] ?? '' );
		$this->record( 'TC-CHK-12', 'Verify matching server-side shipping rate & zone', $ok );
	}

	private function tc_13_removed_or_stale_shipping_rate(): void {
		reset_checkout_env();
		WC()->cart->items = array( 'k1' => array( 'product_id' => 101, 'variation_id' => 0, 'quantity' => 1 ) );
		$payload = array_merge( valid_checkout_payload(), array( 'shipping_method' => array( 0 => 'flat_rate:tampered' ) ) );
		$res = CheckoutValidator::validate_checkout( $payload );
		$ok  = is_wp_error( $res ) && in_array( 'despace_invalid_shipping_method', $res->get_error_codes(), true );
		$this->record( 'TC-CHK-13', 'Reject unavailable or forged shipping rate ID', $ok );
	}

	private function tc_14_no_shipping_method_for_zone(): void {
		reset_checkout_env();
		WC()->cart->items = array( 'k1' => array( 'product_id' => 101, 'variation_id' => 0, 'quantity' => 1 ) );
		$payload = array_merge( valid_checkout_payload(), array(
			'billing_state'                => '48', // Đà Nẵng has no zone in test fixture.
			'billing_city'                 => 'Phường Hải Châu',
			'billing_despace_commune_code' => '',
			'shipping_method'              => '',
		) );
		$res = CheckoutValidator::validate_checkout( $payload );
		$ok  = is_wp_error( $res ) && in_array( 'despace_no_shipping_method', $res->get_error_codes(), true );
		$this->record( 'TC-CHK-14', 'Block shippable order when no shipping method exists', $ok );
	}

	private function tc_15_valid_province_15_lao_cai(): void {
		reset_checkout_env();
		WC()->cart->items = array( 'k1' => array( 'product_id' => 101, 'variation_id' => 0, 'quantity' => 1 ) );
		$payload = array_merge( valid_checkout_payload(), array(
			'billing_state'                => '15',
			'billing_city'                 => 'Ten Gia Mao Frontend', // Must be overridden by official commune_code!
			'billing_despace_commune_code' => '02647',
		) );
		$res = CheckoutValidator::validate_checkout( $payload );
		$ok  = ! is_wp_error( $res )
			&& '15' === $res['addresses']['shipping']['region_code']
			&& 'Tỉnh Lào Cai' === $res['addresses']['shipping']['region_name']
			&& '02647' === $res['addresses']['shipping']['commune_code']
			&& 'Phường Lào Cai' === $res['addresses']['shipping']['commune_name']
			&& true === $res['addresses']['shipping']['region_verified']
			&& true === $res['addresses']['shipping']['commune_verified'];
		$this->record( 'TC-CHK-15', 'Validate VN:15 Lào Cai using official dataset names', $ok );
	}

	private function tc_16_commune_wrong_province_rejected(): void {
		reset_checkout_env();
		WC()->cart->items = array( 'k1' => array( 'product_id' => 101, 'variation_id' => 0, 'quantity' => 1 ) );
		$payload = array_merge( valid_checkout_payload(), array(
			'billing_state'                => '01', // Hà Nội.
			'billing_despace_commune_code' => '02647', // Belongs to Lào Cai (15).
		) );
		$res = CheckoutValidator::validate_checkout( $payload );
		$ok  = is_wp_error( $res ) && in_array( 'despace_commune_province_mismatch', $res->get_error_codes(), true );
		$this->record( 'TC-CHK-16', 'Reject commune code not belonging to province', $ok );
	}

	private function tc_17_missing_address_detail_rejected(): void {
		reset_checkout_env();
		WC()->cart->items = array( 'k1' => array( 'product_id' => 101, 'variation_id' => 0, 'quantity' => 1 ) );
		$payload = array_merge( valid_checkout_payload(), array( 'billing_address_1' => '   ' ) );
		$res = CheckoutValidator::validate_checkout( $payload );
		$ok  = is_wp_error( $res ) && in_array( 'despace_invalid_billing_address_1', $res->get_error_codes(), true );
		$this->record( 'TC-CHK-17', 'Reject missing street/address detail', $ok );
	}

	private function tc_18_invalid_email_rejected(): void {
		reset_checkout_env();
		WC()->cart->items = array( 'k1' => array( 'product_id' => 101, 'variation_id' => 0, 'quantity' => 1 ) );
		$payload = array_merge( valid_checkout_payload(), array( 'billing_email' => 'not-an-email' ) );
		$res = CheckoutValidator::validate_checkout( $payload );
		$ok  = is_wp_error( $res ) && in_array( 'despace_invalid_billing_email', $res->get_error_codes(), true );
		$this->record( 'TC-CHK-18', 'Reject malformed recipient email address', $ok );
	}

	private function tc_19_phone_validation_rules(): void {
		reset_checkout_env();
		WC()->cart->items = array( 'k1' => array( 'product_id' => 101, 'variation_id' => 0, 'quantity' => 1 ) );
		$bad_res = CheckoutValidator::validate_checkout( array_merge( valid_checkout_payload(), array( 'billing_phone' => '12ab' ) ) );
		$ok = is_wp_error( $bad_res )
			&& in_array( 'despace_invalid_billing_phone', $bad_res->get_error_codes(), true )
			&& true === AddressValidator::validate_phone( '0912 345 678' )
			&& true === AddressValidator::validate_phone( '+84 912345678' )
			&& true === AddressValidator::validate_phone( '+1 415 555 0199' )
			&& false === AddressValidator::validate_phone( '0000000000' );
		$this->record( 'TC-CHK-19', 'Validate VN & E.164 phones, reject invalid numbers', $ok );
	}

	private function tc_20_guest_checkout(): void {
		global $despace_mock_options, $despace_mock_current_user;
		reset_checkout_env();
		WC()->cart->items = array( 'k1' => array( 'product_id' => 101, 'variation_id' => 0, 'quantity' => 1 ) );

		$despace_mock_current_user = 0;
		$despace_mock_options['woocommerce_enable_guest_checkout'] = 'yes';
		$guest_ok = CheckoutValidator::validate_checkout( array_merge( valid_checkout_payload(), array( 'customer_id' => 999 ) ) );

		$despace_mock_options['woocommerce_enable_guest_checkout'] = 'no';
		$guest_blocked = CheckoutValidator::validate_checkout( valid_checkout_payload() );

		$ok = ! is_wp_error( $guest_ok )
			&& 'guest' === $guest_ok['customer_context']['mode']
			&& 0 === $guest_ok['customer_context']['customer_id']
			&& is_wp_error( $guest_blocked )
			&& in_array( 'despace_login_required', $guest_blocked->get_error_codes(), true );
		$this->record( 'TC-CHK-20', 'Enforce guest checkout policy & block forged user_id', $ok );
	}

	private function tc_21_logged_in_customer_checkout(): void {
		global $despace_mock_current_user;
		reset_checkout_env();
		$despace_mock_current_user = 42;
		WC()->cart->items          = array( 'k1' => array( 'product_id' => 101, 'variation_id' => 0, 'quantity' => 1 ) );

		$res = CheckoutValidator::validate_checkout( valid_checkout_payload() );
		do_action( 'woocommerce_checkout_update_customer', WC()->customer, valid_checkout_payload() );

		$ok = ! is_wp_error( $res )
			&& 'customer' === $res['customer_context']['mode']
			&& 42 === $res['customer_context']['customer_id']
			&& '15' === WC()->customer->billing_state
			&& true === WC()->customer->saved_profile_unchanged_flag;
		$this->record( 'TC-CHK-21', 'Support logged-in customer checkout cleanly', $ok );
	}

	private function tc_22_different_billing_and_shipping(): void {
		reset_checkout_env();
		WC()->cart->items = array( 'k1' => array( 'product_id' => 101, 'variation_id' => 0, 'quantity' => 1 ) );
		$payload = array_merge( valid_checkout_payload(), array(
			'ship_to_different_address'     => 1,
			'shipping_first_name'           => 'Hải Yến',
			'shipping_last_name'            => 'Trần',
			'shipping_country'              => 'VN',
			'shipping_state'                => '01',
			'shipping_city'                 => 'Phường Ba Đình',
			'shipping_despace_commune_code' => '00004',
			'shipping_address_1'            => '1 Hoàng Diệu',
			'shipping_method'               => array( 0 => 'flat_rate:hn' ),
		) );
		$res = CheckoutValidator::validate_checkout( $payload );
		$ok  = ! is_wp_error( $res )
			&& true === $res['addresses']['ship_to_different_address']
			&& '15' === $res['addresses']['billing']['region_code']
			&& '01' === $res['addresses']['shipping']['region_code']
			&& '00004' === $res['addresses']['shipping']['commune_code']
			&& 5250000.0 === $res['server_total'];
		$this->record( 'TC-CHK-22', 'Support separate shipping vs billing addresses', $ok );
	}

	private function tc_23_dataset_free_text_fallback(): void {
		global $despace_mock_filters;
		reset_checkout_env();
		$prev = $despace_mock_filters['despace_vietnam_regions_dataset_path'] ?? null;
		add_filter( 'despace_vietnam_regions_dataset_path', fn() => '' );
		VietnamRegions::reset_dataset();

		WC_Shipping_Zones::$zones = array(
			array(
				'zone_name'      => 'Vietnam Countrywide',
				'zone_locations' => array( array( 'type' => 'country', 'code' => 'VN' ) ),
				'rates'          => array(
					'flat_rate:vn' => new Mock_Checkout_Shipping_Rate( 'flat_rate:vn', 'flat_rate', 'Giao toàn quốc', 150000.0 ),
				),
			),
		);
		WC()->cart->items = array( 'k1' => array( 'product_id' => 101, 'variation_id' => 0, 'quantity' => 1 ) );

		$payload = array(
			'billing_first_name' => 'Lan',
			'billing_email'      => 'lan@example.com',
			'billing_phone'      => '0987654321',
			'billing_country'    => 'VN',
			'billing_state'      => 'Lào Cai',
			'billing_city'       => 'Phường Lào Cai',
			'billing_address_1'  => '20 Hoàng Liên',
			'shipping_method'    => array( 0 => 'flat_rate:vn' ),
		);
		$res = CheckoutValidator::validate_checkout( $payload );

		if ( null === $prev ) {
			unset( $despace_mock_filters['despace_vietnam_regions_dataset_path'] );
		} else {
			$despace_mock_filters['despace_vietnam_regions_dataset_path'] = $prev;
		}
		VietnamRegions::reset_dataset();

		$ok = ! is_wp_error( $res )
			&& 'free_text_fallback' === $res['addresses']['shipping']['verification_mode']
			&& false === $res['addresses']['shipping']['region_verified']
			&& false === $res['addresses']['shipping']['commune_verified']
			&& 'country' === $res['shipping']['zone_match_level'];
		$this->record( 'TC-CHK-23', 'Free-text fallback degrades honestly at country level', $ok );
	}

	private function tc_24_hpos_order_metadata_saved(): void {
		reset_checkout_env();
		$order   = new Mock_Checkout_Order();
		$payload = array_merge( valid_checkout_payload(), array(
			'ship_to_different_address'     => 1,
			'shipping_first_name'           => 'Hải Yến',
			'shipping_country'              => 'VN',
			'shipping_state'                => '01',
			'shipping_city'                 => 'Phường Ba Đình',
			'shipping_despace_commune_code' => '00004',
			'shipping_address_1'            => '1 Hoàng Diệu',
		) );
		do_action( 'woocommerce_checkout_create_order', $order, $payload );
		$read = OrderMetaSaver::read_order_metadata( $order );

		$ok = '15' === ( $read['billing']['region_code'] ?? '' )
			&& '02647' === ( $read['billing']['commune_code'] ?? '' )
			&& 'Tỉnh Lào Cai' === ( $read['billing']['region_name'] ?? '' )
			&& 'Phường Lào Cai' === ( $read['billing']['commune_name'] ?? '' )
			&& '01' === ( $read['shipping']['region_code'] ?? '' )
			&& '00004' === ( $read['shipping']['commune_code'] ?? '' )
			&& 'Thành phố Hà Nội' === ( $read['shipping']['region_name'] ?? '' )
			&& 'Phường Ba Đình' === ( $read['shipping']['commune_name'] ?? '' )
			&& false === $order->saved; // WC_Checkout calls save() once right after hook!
		$this->record( 'TC-CHK-24', 'Attach HPOS order metadata cleanly on create_order hook', $ok );
	}

	private function tc_25_legacy_order_address_2_preserved(): void {
		reset_checkout_env();
		$legacy = new Mock_Checkout_Order();
		do_action( 'woocommerce_checkout_create_order', $legacy, valid_checkout_payload() );
		$ok = 'Quận Cầu Giấy' === $legacy->get_shipping_address_2()
			&& 'Quận Hoàn Kiếm' === $legacy->get_billing_address_2();
		$this->record( 'TC-CHK-25', 'Preserve legacy address_2 fields on orders untouched', $ok );
	}

	private function get_combined_checkout_source(): string {
		$files = array(
			__DIR__ . '/../wordpress/wp-content/plugins/despace-core/includes/Checkout-Orders/Checkout/AddressValidator.php',
			__DIR__ . '/../wordpress/wp-content/plugins/despace-core/includes/Checkout-Orders/Checkout/CheckoutValidator.php',
			__DIR__ . '/../wordpress/wp-content/plugins/despace-core/includes/Checkout-Orders/Checkout/OrderMetaSaver.php',
		);
		$buf = '';
		foreach ( $files as $f ) {
			$buf .= "\n" . (string) file_get_contents( $f );
		}
		return $buf;
	}

	private function tc_26_no_manual_stock_reduction(): void {
		$src = $this->get_combined_checkout_source();
		$ok  = false === strpos( $src, 'wc_reduce_stock_levels' )
			&& false === strpos( $src, 'wc_update_product_stock' )
			&& false === strpos( $src, 'wc_restore_stock_levels' )
			&& false === strpos( $src, 'set_stock_quantity' );
		$this->record( 'TC-CHK-26', 'Zero manual stock reduction calls in Checkout module', $ok );
	}

	private function tc_27_no_inventory_event_created(): void {
		$src = $this->get_combined_checkout_source();
		$ok  = false === strpos( $src, 'despace_inventory_logs' )
			&& false === strpos( $src, 'InventoryEventRecorder' )
			&& false === strpos( $src, 'InventoryStockObserver' )
			&& false === strpos( $src, 'update_post_meta' )
			&& false === strpos( $src, 'wp_posts' )
			&& false === strpos( $src, 'wp_postmeta' );
		$this->record( 'TC-CHK-27', 'Zero M4 inventory logs or legacy postmeta SQL writes', $ok );
	}

	private function tc_28_no_custom_duplicate_order_or_gateway(): void {
		global $despace_mock_actions;
		$src = $this->get_combined_checkout_source();
		$cfg = apply_filters( 'despace_m3_cart_frontend_config', array() );

		$valid_nonce   = CheckoutValidator::verify_checkout_csrf_nonce( array( 'woocommerce-process-checkout-nonce' => wp_create_nonce( 'woocommerce-process_checkout' ) ) );
		$invalid_nonce = CheckoutValidator::verify_checkout_csrf_nonce( array( 'woocommerce-process-checkout-nonce' => 'bad' ) );

		$ok = false === strpos( $src, 'wp_ajax_despace_place_order' )
			&& false === strpos( $src, 'new WC_Order(' )
			&& false === strpos( $src, 'wc_create_order' )
			&& false === strpos( $src, 'WC_Gateway_COD' )
			&& false === strpos( $src, 'WC_Gateway_BACS' )
			&& empty( $despace_mock_actions['wp_ajax_despace_place_order'] )
			&& 'classic_woocommerce_checkout' === ( $cfg['checkout']['architecture'] ?? '' )
			&& true === $valid_nonce
			&& false === $invalid_nonce;
		$this->record( 'TC-CHK-28', 'Delegate order creation/payment to WC & verify CSRF', $ok );
	}
}

$runner = new M3CheckoutUnitRunner();
$runner->run();

