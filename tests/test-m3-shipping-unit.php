<?php
/**
 * DeSpace M3 Phase 1B-B — Shipping Zones & ShippingNoticeHelper Unit Test Suite.
 *
 * Tests WooCommerce shipping rate lookup, missing shipping methods, multiple rates,
 * out-of-zone addresses, guest vs customer context, security checks, and compliance boundaries.
 * Run via CLI: php tests/test-m3-shipping-unit.php
 *
 * @package DeSpace\Tests
 */

define( 'ABSPATH', __DIR__ . '/../' );

$despace_mock_actions          = array();
$despace_mock_filters          = array();
$despace_mock_logged_in        = false;
$despace_mock_stock_hook_calls = array();
$despace_mock_zone_changes     = 0;

class MockShippingAjaxExitException extends \RuntimeException {
	public array $payload;
	public int $status_code;
	public function __construct( array $payload, int $status_code ) {
		parent::__construct( 'AJAX JSON' );
		$this->payload     = $payload;
		$this->status_code = $status_code;
	}
}

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

function is_wp_error( $thing ): bool { return $thing instanceof WP_Error; }
function add_action( string $tag, callable $cb, int $prio = 10, int $args = 1 ): bool {
	global $despace_mock_actions;
	$despace_mock_actions[ $tag ][] = array( 'callback' => $cb, 'priority' => $prio );
	return true;
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
function is_user_logged_in(): bool { global $despace_mock_logged_in; return (bool) $despace_mock_logged_in; }
function wp_verify_nonce( string $nonce, $action = -1 ) { return ( 'valid_nonce_' . $action ) === $nonce ? 1 : false; }
function wp_create_nonce( $action = -1 ): string { return 'valid_nonce_' . $action; }
function get_woocommerce_currency(): string { return 'VND'; }
function wc_price( float $price ): string { return '<span class="woocommerce-Price-amount">' . number_format( $price, 0, ',', '.' ) . ' ₫</span>'; }
function wp_send_json_success( $data = null, int $status = 200 ): void {
	throw new MockShippingAjaxExitException( array( 'success' => true, 'data' => $data ), $status );
}
function wp_send_json_error( $data = null, int $status = 400 ): void {
	throw new MockShippingAjaxExitException( array( 'success' => false, 'data' => $data ), $status );
}

require_once __DIR__ . '/../wordpress/wp-content/plugins/despace-core/includes/Checkout-Orders/CheckoutOrdersModule.php';

use DeSpace\CheckoutOrders\CheckoutOrdersModule;
use DeSpace\CheckoutOrders\Cart\CartHandler;
use DeSpace\CheckoutOrders\Shipping\VietnamRegions;
use DeSpace\CheckoutOrders\Shipping\ShippingNoticeHelper;

if ( ! class_exists( 'WooCommerce' ) ) {

class Mock_WC_Shipping_Rate {
	private string $id;
	private string $method_id;
	private string $label;
	private float $cost;
	private array $taxes;

	public function __construct( string $id, string $method_id, string $label, float $cost, array $taxes = array() ) {
		$this->id        = $id;
		$this->method_id = $method_id;
		$this->label     = $label;
		$this->cost      = $cost;
		$this->taxes     = $taxes;
	}
	public function get_id(): string { return $this->id; }
	public function get_method_id(): string { return $this->method_id; }
	public function get_label(): string { return $this->label; }
	public function get_cost(): float { return $this->cost; }
	public function get_taxes(): array { return $this->taxes; }
}

class WC_Shipping_Zones {
	public static array $zones = array();
	public static function get_zones(): array { return self::$zones; }
}

class Mock_WC_Customer {
	private string $country = 'VN';
	private string $state = '';
	private string $city = '';
	private string $address_1 = '';
	private string $postcode = '';
	public bool $calculated = false;
	public bool $saved_to_db = false;

	public function set_shipping_country( string $v ): void { $this->country = $v; }
	public function set_shipping_state( string $v ): void { $this->state = $v; }
	public function set_shipping_city( string $v ): void { $this->city = $v; }
	public function set_shipping_address_1( string $v ): void { $this->address_1 = $v; }
	public function set_shipping_postcode( string $v ): void { $this->postcode = $v; }
	public function set_calculated_shipping( bool $v ): void { $this->calculated = $v; }
	public function save(): void { $this->saved_to_db = true; }
	public function get_shipping_country(): string { return $this->country; }
	public function get_shipping_state(): string { return $this->state; }
	public function get_shipping_city(): string { return $this->city; }
	public function get_shipping_address_1(): string { return $this->address_1; }
	public function get_shipping_postcode(): string { return $this->postcode; }
}

class Mock_WC_Shipping {
	private array $packages = array();

	public function set_packages( array $packages ): void { $this->packages = $packages; }
	public function get_packages(): array { return $this->packages; }
	public function calculate_shipping( array $packages ): array { return $this->packages; }
}

class Mock_WC_Session_Ship {
	private array $bag = array();
	public function get( string $key, $default = null ) { return $this->bag[ $key ] ?? $default; }
	public function set( string $key, $val ): void { $this->bag[ $key ] = $val; }
	public function has_session(): bool { return true; }
	public function set_customer_session_cookie( bool $v ): void {}
}

class Mock_WC_Cart_Ship {
	public bool $empty = false;
	public bool $requires_shipping = true;
	private float $shipping_total = 0.0;

	public function is_empty(): bool { return $this->empty; }
	public function needs_shipping(): bool { return $this->requires_shipping; }
	public function get_shipping_packages(): array { return array(); }
	public function get_cart(): array { return array(); }
	public function get_subtotal(): float { return 0.0; }
	public function get_total( string $ctx = 'view' ) { return 0.0; }
	public function calculate_shipping(): void {
		// Dynamically match mocked WooCommerce zones in zone order (mirrors WC_Shipping_Zone_Data_Store).
		$country  = strtoupper( WC()->customer->get_shipping_country() );
		$state    = strtoupper( WC()->customer->get_shipping_state() );
		$postcode = strtoupper( trim( WC()->customer->get_shipping_postcode() ) );

		$rates = array();
		foreach ( WC_Shipping_Zones::$zones as $zone ) {
			$locations = $zone['zone_locations'] ?? array();
			if ( ! empty( $locations ) ) {
				$geo_rules      = array();
				$postcode_rules = array();
				foreach ( $locations as $loc ) {
					$t = $loc['type'] ?? '';
					$c = strtoupper( (string) ( $loc['code'] ?? '' ) );
					if ( 'postcode' === $t ) {
						$postcode_rules[] = $c;
					} else {
						$geo_rules[] = array( 'type' => $t, 'code' => $c );
					}
				}
				$geo_ok = empty( $geo_rules );
				foreach ( $geo_rules as $gr ) {
					if ( ( 'country' === $gr['type'] && $gr['code'] === $country )
						|| ( 'state' === $gr['type'] && '' !== $state && $gr['code'] === ( $country . ':' . $state ) )
					) {
						$geo_ok = true;
						break;
					}
				}
				if ( ! $geo_ok ) {
					continue;
				}
				if ( ! empty( $postcode_rules ) ) {
					$pc_ok = false;
					if ( '' !== $postcode ) {
						foreach ( $postcode_rules as $rule ) {
							if ( $postcode === $rule || ( false !== strpos( $rule, '...' ) && ( function () use ( $postcode, $rule ) {
								$b = explode( '...', $rule, 2 );
								return is_numeric( $postcode ) && is_numeric( $b[0] ) && is_numeric( $b[1] ) && (int) $postcode >= (int) $b[0] && (int) $postcode <= (int) $b[1];
							} )() ) ) {
								$pc_ok = true;
								break;
							}
						}
					}
					if ( ! $pc_ok ) {
						continue;
					}
				}
				$rates = $zone['rates'];
				break;
			} elseif ( in_array( $country . ':' . $state, $zone['match_keys'] ?? array(), true ) || in_array( $country, $zone['match_keys'] ?? array(), true ) ) {
				$rates = $zone['rates'];
				break;
			}
		}

		WC()->shipping()->set_packages( array(
			array(
				'destination' => array(
					'country'   => $country,
					'state'     => $state,
					'postcode'  => $postcode,
					'city'      => WC()->customer->get_shipping_city(),
					'address_1' => WC()->customer->get_shipping_address_1(),
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
	public function calculate_totals(): void {}
	public function get_shipping_total(): float { return $this->shipping_total; }
	public function get_shipping_tax(): float { return 0.0; }
}

class WooCommerce {
	public ?Mock_WC_Cart_Ship $cart = null;
	public ?Mock_WC_Session_Ship $session = null;
	public ?Mock_WC_Customer $customer = null;
	private Mock_WC_Shipping $shipping;

	public function __construct() {
		$this->cart     = new Mock_WC_Cart_Ship();
		$this->session  = new Mock_WC_Session_Ship();
		$this->customer = new Mock_WC_Customer();
		$this->shipping = new Mock_WC_Shipping();
	}
	public function shipping(): Mock_WC_Shipping { return $this->shipping; }
	public function initialize_session(): void {}
}

function WC(): WooCommerce { global $despace_mock_wc; return $despace_mock_wc; }
}

$despace_mock_wc = new WooCommerce();

function reset_shipping_test_env(): void {
	global $despace_mock_wc, $despace_mock_logged_in;
	$despace_mock_logged_in = false;
	$despace_mock_wc        = new WooCommerce();

	VietnamRegions::load_dataset( array(
		'source'         => 'test_fixture',
		'version'        => '1.0',
		'effective_date' => '2025-07-01',
		'provinces'      => array(
			array( 'code' => '79', 'name' => 'Thành phố Hồ Chí Minh' ),
			array( 'code' => '01', 'name' => 'Thành phố Hà Nội' ),
			array( 'code' => '48', 'name' => 'Thành phố Đà Nẵng' ),
		),
		'communes'       => array(),
	) );

	// Admin-configured WooCommerce Shipping Zones (test fixture only).
	WC_Shipping_Zones::$zones = array(
		array(
			'zone_name'      => 'TP.HCM Zone',
			'match_keys'     => array( 'VN:79' ),
			'zone_locations' => array( array( 'type' => 'state', 'code' => 'VN:79' ) ),
			'rates'          => array(
				'flat_rate:1'     => new Mock_WC_Shipping_Rate( 'flat_rate:1', 'flat_rate', 'Giao hàng tiêu chuẩn HCM', 200000.0 ),
				'free_shipping:2' => new Mock_WC_Shipping_Rate( 'free_shipping:2', 'free_shipping', 'Miễn phí vận chuyển', 0.0 ),
			),
		),
		array(
			'zone_name'      => 'Hà Nội Zone',
			'match_keys'     => array( 'VN:01' ),
			'zone_locations' => array( array( 'type' => 'state', 'code' => 'VN:01' ) ),
			'rates'          => array(
				'flat_rate:3' => new Mock_WC_Shipping_Rate( 'flat_rate:3', 'flat_rate', 'Giao hàng liên tỉnh', 350000.0 ),
			),
		),
	);
}

CheckoutOrdersModule::init();

class M3ShippingUnitRunner {
	private array $results = array();

	public function run(): void {
		echo "========================================================================\n";
		echo "       DeSpace M3 Phase 1B-B — SHIPPING BACKEND UNIT TEST SUITE         \n";
		echo "========================================================================\n\n";

		$this->tc_01_matching_shipping_method();
		$this->tc_02_no_shipping_method_in_zone();
		$this->tc_03_multiple_methods_in_zone();
		$this->tc_04_address_outside_supported_zones();
		$this->tc_05_guest_cart_estimate_and_security();
		$this->tc_06_logged_in_customer_estimate();
		$this->tc_07_address_change_recalculates_rates();
		$this->tc_08_no_hardcoded_shipping_fees();
		$this->tc_09_no_hardcoded_free_shipping_threshold();
		$this->tc_10_admin_zones_never_modified();
		$this->tc_11_no_custom_shipping_db_tables();
		$this->tc_12_consistent_errors_and_frontend_config();
		$this->tc_13_vn_country_zone_without_state();
		$this->tc_14_vn_lao_cai_province_zone_15();
		$this->tc_15_other_province_excludes_lao_cai_zone();
		$this->tc_16_vn_postcode_zone_matching();
		$this->tc_17_free_text_fallback_honest_country_level();
		$this->tc_18_commune_granularity_and_profile_safety();

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

	private function tc_01_matching_shipping_method(): void {
		reset_shipping_test_env();
		$res = ShippingNoticeHelper::get_shipping_summary( null, array(
			'country'     => 'VN',
			'region_code' => '01',
		) );
		$ok = ! is_wp_error( $res )
			&& 'available' === $res['status']
			&& true === $res['can_deliver']
			&& 'flat_rate:3' === $res['shipping_rate_id']
			&& 350000.0 === $res['shipping_amount'];
		$this->record( 'TC-SHP-01', 'Return matching WC shipping method & cost', $ok );
	}

	private function tc_02_no_shipping_method_in_zone(): void {
		reset_shipping_test_env();
		$res  = ShippingNoticeHelper::get_shipping_summary( null, array(
			'country'     => 'VN',
			'region_code' => '48', // Đà Nẵng has no zone configured.
		) );
		$html = apply_filters( 'woocommerce_cart_no_shipping_available_html', 'Default' );
		$ok   = ! is_wp_error( $res )
			&& 'unavailable' === $res['status']
			&& false === $res['can_deliver']
			&& empty( $res['rates'] )
			&& false !== strpos( $html, 'despace-shipping-unavailable-notice' );
		$this->record( 'TC-SHP-02', 'Handle province with no configured shipping method', $ok );
	}

	private function tc_03_multiple_methods_in_zone(): void {
		reset_shipping_test_env();
		$res = ShippingNoticeHelper::get_shipping_summary( null, array(
			'country'     => 'VN',
			'region_code' => '79',
		) );
		$ok = ! is_wp_error( $res )
			&& 2 === count( $res['rates'] )
			&& 'flat_rate' === $res['rates'][0]['shipping_method_id']
			&& 'free_shipping' === $res['rates'][1]['shipping_method_id']
			&& true === $res['rates'][1]['is_free'];
		$this->record( 'TC-SHP-03', 'Expose multiple shipping methods in single zone', $ok );
	}

	private function tc_04_address_outside_supported_zones(): void {
		reset_shipping_test_env();
		$res = ShippingNoticeHelper::get_shipping_summary( null, array(
			'country'     => 'US',
			'region_code' => 'NY',
		) );
		$ok = ! is_wp_error( $res )
			&& 'unavailable' === $res['status']
			&& false === $res['can_deliver'];
		$this->record( 'TC-SHP-04', 'Reject/handle out-of-zone foreign destination', $ok );
	}

	private function tc_05_guest_cart_estimate_and_security(): void {
		global $despace_mock_logged_in, $despace_mock_actions;
		reset_shipping_test_env();
		$despace_mock_logged_in = false;

		$no_nonce = ShippingNoticeHelper::handle_estimate_request( array( 'region_code' => '01' ) );

		$_SERVER['REQUEST_METHOD'] = 'GET';
		$method_status             = 0;
		try {
			ShippingNoticeHelper::ajax_estimate_shipping();
		} catch ( MockShippingAjaxExitException $e ) {
			$method_status = $e->status_code;
		}
		$_SERVER['REQUEST_METHOD'] = 'POST';

		$spoof = ShippingNoticeHelper::handle_estimate_request( array(
			'nonce'           => wp_create_nonce( CartHandler::NONCE_ACTION ),
			'country'         => 'VN',
			'region_code'     => '01',
			'shipping_amount' => 0, // Attempted fee spoofing from frontend.
		) );

		$ok = false === $no_nonce['success'] && 403 === $no_nonce['status']
			&& 405 === $method_status
			&& ! empty( $spoof['success'] )
			&& 'guest' === $spoof['data']['user_context']
			&& 350000.0 === $spoof['data']['shipping_amount']
			&& ! empty( $despace_mock_actions['wp_ajax_nopriv_despace_shipping_estimate'] );
		$this->record( 'TC-SHP-05', 'Guest AJAX shipping estimate + nonce/POST/anti-spoof', $ok );
	}

	private function tc_06_logged_in_customer_estimate(): void {
		global $despace_mock_logged_in, $despace_mock_actions;
		reset_shipping_test_env();
		$despace_mock_logged_in = true;

		$res = ShippingNoticeHelper::handle_estimate_request( array(
			'nonce'       => wp_create_nonce( CartHandler::NONCE_ACTION ),
			'country'     => 'VN',
			'region_code' => '79',
		) );
		$ok = ! empty( $res['success'] )
			&& 'customer' === $res['data']['user_context']
			&& 200000.0 === $res['data']['shipping_amount']
			&& ! empty( $despace_mock_actions['wp_ajax_despace_shipping_estimate'] );
		$this->record( 'TC-SHP-06', 'Logged-in customer AJAX shipping estimate', $ok );
	}

	private function tc_07_address_change_recalculates_rates(): void {
		reset_shipping_test_env();
		$r1 = ShippingNoticeHelper::get_shipping_summary( null, array( 'country' => 'VN', 'region_code' => '79' ) );
		$r2 = ShippingNoticeHelper::get_shipping_summary( null, array( 'country' => 'VN', 'region_code' => '01' ) );
		$ok = 200000.0 === $r1['shipping_amount']
			&& 350000.0 === $r2['shipping_amount']
			&& '01' === WC()->customer->get_shipping_state();
		$this->record( 'TC-SHP-07', 'Changing address recalculates WC shipping rate', $ok );
	}

	private function get_combined_shipping_source(): string {
		$regions_src = (string) file_get_contents( __DIR__ . '/../wordpress/wp-content/plugins/despace-core/includes/Checkout-Orders/Shipping/VietnamRegions.php' );
		$helper_src  = (string) file_get_contents( __DIR__ . '/../wordpress/wp-content/plugins/despace-core/includes/Checkout-Orders/Shipping/ShippingNoticeHelper.php' );
		return $regions_src . "\n" . $helper_src;
	}

	private function tc_08_no_hardcoded_shipping_fees(): void {
		$src = $this->get_combined_shipping_source();
		$ok  = false === strpos( $src, '350000' )
			&& false === strpos( $src, '220000' )
			&& false === strpos( $src, '200000' );
		$this->record( 'TC-SHP-08', 'No hardcoded shipping fees in module source', $ok );
	}

	private function tc_09_no_hardcoded_free_shipping_threshold(): void {
		$src = $this->get_combined_shipping_source();
		$ok  = false === strpos( $src, '500000' )
			&& false === strpos( $src, '10000000' )
			&& false === strpos( $src, 'min_amount' );
		$this->record( 'TC-SHP-09', 'No hardcoded free shipping thresholds in source', $ok );
	}

	private function tc_10_admin_zones_never_modified(): void {
		reset_shipping_test_env();
		$zones_before = WC_Shipping_Zones::get_zones();
		ShippingNoticeHelper::get_shipping_summary( null, array( 'country' => 'VN', 'region_code' => '79' ) );
		$zones_after = WC_Shipping_Zones::get_zones();
		$src         = $this->get_combined_shipping_source();

		$ok = ( $zones_before === $zones_after )
			&& false === strpos( $src, 'add_shipping_method' )
			&& false === strpos( $src, '->save(' )
			&& false === strpos( $src, 'update_option' );
		$this->record( 'TC-SHP-10', 'Admin WooCommerce Shipping Zones remain untouched', $ok );
	}

	private function tc_11_no_custom_shipping_db_tables(): void {
		$src       = $this->get_combined_shipping_source();
		$forbidden = array(
			'despace_shipping_rates',
			'despace_shipping_zones',
			'despace_addresses',
			'despace_provinces',
			'despace_communes',
			'despace_inventory_logs',
			'$wpdb',
			'dbDelta',
			'CREATE TABLE',
		);
		$clean = true;
		foreach ( $forbidden as $tok ) {
			if ( false !== strpos( $src, $tok ) ) {
				$clean = false;
			}
		}
		$this->record( 'TC-SHP-11', 'No custom shipping/region DB tables or M4 touches', $clean );
	}

	private function tc_12_consistent_errors_and_frontend_config(): void {
		reset_shipping_test_env();
		$cfg        = apply_filters( 'despace_m3_cart_frontend_config', array() );
		$incomplete = ShippingNoticeHelper::get_shipping_summary( null, array( 'country' => 'VN', 'region_code' => '' ) );

		WC()->cart->empty = true;
		$empty_cart       = ShippingNoticeHelper::get_shipping_summary();

		$ok = 'despace_shipping_estimate' === ( $cfg['actions']['estimate_shipping'] ?? '' )
			&& isset( $cfg['vietnam_regions']['provinces'] )
			&& 'address_incomplete' === $incomplete['status']
			&& true === $incomplete['requires_more_address_info']
			&& 'empty_cart' === $empty_cart['status'];
		$this->record( 'TC-SHP-12', 'Consistent error statuses & frontend config contract', $ok );
	}

	private function tc_13_vn_country_zone_without_state(): void {
		reset_shipping_test_env();
		VietnamRegions::reset_dataset();

		WC_Shipping_Zones::$zones = array(
			array(
				'zone_name'      => 'Vietnam Countrywide',
				'zone_locations' => array( array( 'type' => 'country', 'code' => 'VN' ) ),
				'rates'          => array(
					'flat_rate:vn' => new Mock_WC_Shipping_Rate( 'flat_rate:vn', 'flat_rate', 'Giao hàng toàn quốc', 150000.0 ),
				),
			),
		);

		$res = ShippingNoticeHelper::get_shipping_summary( null, array(
			'country'     => 'VN',
			'region_code' => '15',
		) );

		$ok = ! is_wp_error( $res )
			&& 'available' === $res['status']
			&& 'country' === $res['zone_match_level']
			&& true === $res['region_verified']
			&& false === $res['commune_verified']
			&& 'flat_rate:vn' === $res['shipping_rate_id'];
		$this->record( 'TC-SHP-13', 'Match VN country zone honestly as country-level', $ok );
	}

	private function tc_14_vn_lao_cai_province_zone_15(): void {
		reset_shipping_test_env();
		VietnamRegions::reset_dataset();

		WC_Shipping_Zones::$zones = array(
			array(
				'zone_name'      => 'Lào Cai Zone',
				'zone_locations' => array( array( 'type' => 'state', 'code' => 'VN:15' ) ),
				'rates'          => array(
					'flat_rate:lc' => new Mock_WC_Shipping_Rate( 'flat_rate:lc', 'flat_rate', 'Giao hàng Lào Cai', 180000.0 ),
				),
			),
		);

		$res = ShippingNoticeHelper::get_shipping_summary( null, array(
			'country'        => 'VN',
			'region_code'    => '15',
			'commune_code'   => '02647',
			'address_detail' => '10 Hoàng Liên',
		) );

		$ok = ! is_wp_error( $res )
			&& 'available' === $res['status']
			&& 'province' === $res['zone_match_level']
			&& true === $res['region_verified']
			&& true === $res['commune_verified']
			&& '15' === WC()->customer->get_shipping_state()
			&& 'Phường Lào Cai' === WC()->customer->get_shipping_city()
			&& 'flat_rate:lc' === $res['shipping_rate_id'];
		$this->record( 'TC-SHP-14', 'Match VN:15 Lào Cai province zone with real dataset', $ok );
	}

	private function tc_15_other_province_excludes_lao_cai_zone(): void {
		reset_shipping_test_env();
		VietnamRegions::reset_dataset();

		WC_Shipping_Zones::$zones = array(
			array(
				'zone_name'      => 'Lào Cai Only',
				'zone_locations' => array( array( 'type' => 'state', 'code' => 'VN:15' ) ),
				'rates'          => array(
					'flat_rate:lc' => new Mock_WC_Shipping_Rate( 'flat_rate:lc', 'flat_rate', 'Giao hàng Lào Cai', 180000.0 ),
				),
			),
		);

		$res = ShippingNoticeHelper::get_shipping_summary( null, array(
			'country'     => 'VN',
			'region_code' => '01', // Hà Nội should not match VN:15.
		) );

		$ok = ! is_wp_error( $res )
			&& 'unavailable' === $res['status']
			&& 'none' === $res['zone_match_level']
			&& true === $res['region_verified'];
		$this->record( 'TC-SHP-15', 'Exclude non-matching province from VN:15 zone', $ok );
	}

	private function tc_16_vn_postcode_zone_matching(): void {
		reset_shipping_test_env();
		VietnamRegions::reset_dataset();

		WC_Shipping_Zones::$zones = array(
			array(
				'zone_name'      => 'VN Central Postcode Hub',
				'zone_locations' => array(
					array( 'type' => 'country', 'code' => 'VN' ),
					array( 'type' => 'postcode', 'code' => '330000...339999' ),
				),
				'rates'          => array(
					'flat_rate:pc' => new Mock_WC_Shipping_Rate( 'flat_rate:pc', 'flat_rate', 'Giao tuyến bưu chính 33xxxx', 120000.0 ),
				),
			),
		);

		$matched = ShippingNoticeHelper::get_shipping_summary( null, array(
			'country'     => 'VN',
			'region_code' => '15',
			'postcode'    => '331000',
		) );
		$missed = ShippingNoticeHelper::get_shipping_summary( null, array(
			'country'     => 'VN',
			'region_code' => '15',
			'postcode'    => '700000',
		) );

		$ok = ! is_wp_error( $matched )
			&& 'available' === $matched['status']
			&& 'postcode' === $matched['zone_match_level']
			&& 'flat_rate:pc' === $matched['shipping_rate_id']
			&& ! is_wp_error( $missed )
			&& 'unavailable' === $missed['status']
			&& 'none' === $missed['zone_match_level'];
		$this->record( 'TC-SHP-16', 'Match & exclude VN postcode zone rules accurately', $ok );
	}

	private function tc_17_free_text_fallback_honest_country_level(): void {
		global $despace_mock_filters;
		reset_shipping_test_env();
		$prev = $despace_mock_filters['despace_vietnam_regions_dataset_path'] ?? null;
		add_filter( 'despace_vietnam_regions_dataset_path', fn() => '' );
		VietnamRegions::reset_dataset();

		WC_Shipping_Zones::$zones = array(
			array(
				'zone_name'      => 'Vietnam Countrywide',
				'zone_locations' => array( array( 'type' => 'country', 'code' => 'VN' ) ),
				'rates'          => array(
					'flat_rate:vn' => new Mock_WC_Shipping_Rate( 'flat_rate:vn', 'flat_rate', 'Giao hàng VN', 150000.0 ),
				),
			),
		);

		$res = ShippingNoticeHelper::get_shipping_summary( null, array(
			'country'      => 'VN',
			'region_name'  => 'Tỉnh Lào Cai',
			'commune_name' => 'Phường Lào Cai',
		) );

		if ( null === $prev ) {
			unset( $despace_mock_filters['despace_vietnam_regions_dataset_path'] );
		} else {
			$despace_mock_filters['despace_vietnam_regions_dataset_path'] = $prev;
		}
		VietnamRegions::reset_dataset();

		$ok = ! is_wp_error( $res )
			&& 'available' === $res['status']
			&& 'country' === $res['zone_match_level']
			&& false === $res['region_verified']
			&& false === $res['commune_verified']
			&& 'free_text' === ( $res['destination']['commune_input_mode'] ?? '' );
		$this->record( 'TC-SHP-17', 'Free-text fallback reports country match, never province', $ok );
	}

	private function tc_18_commune_granularity_and_profile_safety(): void {
		global $despace_mock_logged_in;
		reset_shipping_test_env();
		VietnamRegions::reset_dataset();
		$despace_mock_logged_in = true;

		WC_Shipping_Zones::$zones = array(
			array(
				'zone_name'      => 'Lào Cai Province',
				'zone_locations' => array( array( 'type' => 'state', 'code' => 'VN:15' ) ),
				'rates'          => array(
					'flat_rate:lc' => new Mock_WC_Shipping_Rate( 'flat_rate:lc', 'flat_rate', 'Giao hàng Lào Cai', 180000.0 ),
				),
			),
		);

		$res = ShippingNoticeHelper::get_shipping_summary( null, array(
			'country'        => 'VN',
			'region_code'    => '15',
			'commune_code'   => '02647',
			'address_detail' => '10 Hoàng Liên',
		) );

		$ok = ! is_wp_error( $res )
			&& true === $res['shipping_estimate_only']
			&& true === $res['region_verified']
			&& true === $res['commune_verified']
			&& false !== strpos( $res['zone_granularity_limitation'], 'Country, Province/State, and Postcode' )
			&& false === WC()->customer->saved_to_db;
		$this->record( 'TC-SHP-18', 'Honest commune granularity & non-mutating customer profile', $ok );
	}
}

$runner = new M3ShippingUnitRunner();
$runner->run();
