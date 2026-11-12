<?php
/**
 * DeSpace M3 Phase 2B — Payment Methods (COD & BACS) Unit Test Suite (Issue #18).
 *
 * Verifies all 24 mandatory payment checks: WooCommerce gateway registry, COD & BACS enabled/disabled
 * states, shipping method compatibility (`enable_for_methods`), guest/logged-in support, BACS `on-hold`
 * & unpaid `date_paid = null`, COD non-completion guard, frontend payment/tx/total spoofing defense,
 * zero hardcoded bank accounts or fake QR codes, HPOS CRUD, M4 inventory boundaries, and dynamic WC settings.
 *
 * Run via CLI: php tests/test-m3-payment-unit.php
 *
 * @package DeSpace\Tests
 */

define( 'ABSPATH', __DIR__ . '/../' );

$despace_mock_actions      = array();
$despace_mock_filters      = array();
$despace_mock_options      = array(
	'woocommerce_enable_guest_checkout' => 'yes',
	'woocommerce_bacs_settings'         => array(),
	'woocommerce_bacs_accounts'         => array(),
);
$despace_mock_current_user = 0;
$despace_mock_caps         = array();
$despace_mock_orders       = array();

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
	public function get_error_code(): string { $c = $this->get_error_codes(); return $c[0] ?? ''; }
	public function get_error_message( string $code = '' ): string {
		if ( '' === $code ) $code = $this->get_error_code();
		return $this->errors[ $code ][0] ?? '';
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
function current_user_can( string $cap ): bool { global $despace_mock_caps; return ! empty( $despace_mock_caps[ $cap ] ); }
function get_option( string $opt, $default = false ) {
	global $despace_mock_options;
	return array_key_exists( $opt, $despace_mock_options ) ? $despace_mock_options[ $opt ] : $default;
}
function wp_create_nonce( $action = -1 ): string { return 'valid_nonce_' . $action; }
function wp_verify_nonce( string $nonce, $action = -1 ) { return ( 'valid_nonce_' . $action ) === $nonce ? 1 : false; }
function wc_price( float $price ): string { return number_format( $price, 0, ',', '.' ) . ' ₫'; }
function wc_get_order( int $id ) {
	global $despace_mock_orders;
	return $despace_mock_orders[ $id ] ?? false;
}

class Mock_WC_Gateway {
	public string $id;
	public string $title;
	public string $description;
	public string $enabled = 'yes';
	public string $instructions = '';
	public array $enable_for_methods = array();
	public bool $enable_for_virtual = true;
	public array $account_details = array();

	public function __construct( string $id, array $props = array() ) {
		$this->id          = $id;
		$this->title       = strtoupper( $id );
		$this->description = '';
		foreach ( $props as $k => $v ) {
			$this->$k = $v;
		}
	}
	public function get_title(): string { return $this->title; }
	public function get_description(): string { return $this->description; }
	public function is_available(): bool {
		if ( 'yes' !== $this->enabled ) {
			return false;
		}
		if ( 'cod' === $this->id && ! empty( $this->enable_for_methods ) && WC()->cart->needs_shipping() ) {
			$chosen = (array) WC()->session->get( 'chosen_shipping_methods', array() );
			foreach ( $chosen as $c ) {
				$base = explode( ':', (string) $c )[0];
				if ( in_array( (string) $c, $this->enable_for_methods, true ) || in_array( $base, $this->enable_for_methods, true ) ) {
					return true;
				}
			}
			return false;
		}
		return true;
	}
}

class Mock_WC_Payment_Gateways {
	public array $gateways = array();
	public function payment_gateways(): array { return $this->gateways; }
	public function get_available_payment_gateways(): array {
		$avail = array();
		foreach ( $this->gateways as $id => $gw ) {
			if ( $gw->is_available() ) {
				$avail[ $id ] = $gw;
			}
		}
		return $avail;
	}
}

class Mock_Pay_Session {
	private array $bag = array();
	public function get( string $k, $d = null ) { return $this->bag[ $k ] ?? $d; }
	public function set( string $k, $v ): void { $this->bag[ $k ] = $v; }
}

class Mock_Pay_Cart {
	public bool $needs_payment = true;
	public bool $needs_shipping = true;
	public float $total = 4500000.0;
	public function needs_payment(): bool { return $this->needs_payment; }
	public function needs_shipping(): bool { return $this->needs_shipping; }
	public function get_shipping_methods(): array { return array(); }
	public function get_total( string $ctx = 'view' ): float { return $this->total; }
}

class Mock_Pay_Customer {
	public function get_shipping_country(): string { return 'VN'; }
	public function get_shipping_state(): string { return '15'; }
	public function get_shipping_city(): string { return 'Phường Lào Cai'; }
	public function get_shipping_address_1(): string { return '10 Hoàng Liên'; }
	public function get_shipping_postcode(): string { return ''; }
}

class Mock_Pay_Order {
	public int $id = 5001;
	public int $customer_id = 0;
	public string $order_key = 'wc_order_secret_5001';
	public string $payment_method = 'bacs';
	public string $status = 'on-hold';
	public float $total = 4500000.0;
	public bool $paid = false;
	public $date_paid = null;
	public string $transaction_id = '';
	public array $notes = array();
	public array $meta = array();
	public bool $downloadable = false;
	public bool $payment_complete_result = true;
	public bool $payment_complete_no_state_change = false;
	public bool $throw_on_payment_complete = false;
	public int $payment_complete_calls = 0;
	/** @var callable|null */
	public $on_payment_complete = null;

	public function get_id(): int { return $this->id; }
	public function get_order_number(): string { return (string) $this->id; }
	public function get_customer_id(): int { return $this->customer_id; }
	public function get_order_key(): string { return $this->order_key; }
	public function get_payment_method(): string { return $this->payment_method; }
	public function set_payment_method( string $v ): void { $this->payment_method = $v; }
	public function get_status(): string { return $this->status; }
	public function get_total(): float { return $this->total; }
	public function is_paid(): bool { return $this->paid; }
	public function get_date_paid() { return $this->date_paid; }
	public function set_date_paid( $v ): void { $this->date_paid = $v; }
	public function get_transaction_id(): string { return $this->transaction_id; }
	public function set_transaction_id( string $v ): void { $this->transaction_id = $v; }
	public function has_downloadable_item(): bool { return $this->downloadable; }
	public function add_order_note( string $n, int $cust = 0, bool $manual = false ): void { $this->notes[] = $n; }
	public function update_status( string $new_status, string $note = '', bool $manual = false ): void {
		$this->status = $new_status;
		if ( '' !== $note ) $this->notes[] = $note;
	}
	public function payment_complete( string $tx = '' ): bool {
		$this->payment_complete_calls++;
		if ( is_callable( $this->on_payment_complete ) ) {
			call_user_func( $this->on_payment_complete, $this );
		}
		if ( $this->throw_on_payment_complete ) {
			throw new \RuntimeException( 'Simulated payment_complete failure' );
		}
		if ( ! $this->payment_complete_result ) {
			return false;
		}
		if ( $this->payment_complete_no_state_change ) {
			return true;
		}
		if ( '' !== $tx ) $this->transaction_id = $tx;
		if ( null === $this->date_paid ) $this->date_paid = time();
		$this->paid   = true;
		$this->status = 'processing';
		return true;
	}
	public function update_meta_data( string $k, $v ): void { $this->meta[ $k ] = $v; }
	public function get_meta( string $k, bool $single = true ) { return $this->meta[ $k ] ?? ''; }
	public function save(): int { return $this->id; }
}

class Mock_WPDB {
	public string $options = 'wp_options';
	public array $rows = array();

	public function prepare( string $query, ...$args ): array {
		return array(
			'query' => $query,
			'args'  => $args,
		);
	}

	public function get_var( $prepared ) {
		if ( ! is_array( $prepared ) ) {
			return null;
		}
		$sql  = (string) ( $prepared['query'] ?? '' );
		$args = (array) ( $prepared['args'] ?? array() );
		if ( false !== strpos( $sql, 'SELECT option_value FROM' ) ) {
			$key = (string) ( $args[0] ?? '' );
			return $this->rows[ $key ]['option_value'] ?? null;
		}
		return null;
	}

	public function query( $prepared ): int {
		if ( ! is_array( $prepared ) ) {
			return 0;
		}
		$sql  = (string) ( $prepared['query'] ?? '' );
		$args = (array) ( $prepared['args'] ?? array() );

		if ( 0 === strpos( $sql, 'INSERT IGNORE INTO' ) ) {
			$key = (string) ( $args[0] ?? '' );
			$val = (string) ( $args[1] ?? '' );
			$al  = (string) ( $args[2] ?? 'no' );
			if ( isset( $this->rows[ $key ] ) ) {
				return 0;
			}
			$this->rows[ $key ] = array(
				'option_value' => $val,
				'autoload'     => $al,
			);
			return 1;
		}

		if ( 0 === strpos( $sql, 'UPDATE' ) ) {
			$new_val = (string) ( $args[0] ?? '' );
			$key     = (string) ( $args[1] ?? '' );
			$old_val = (string) ( $args[2] ?? '' );
			if ( isset( $this->rows[ $key ] ) && $this->rows[ $key ]['option_value'] === $old_val ) {
				$this->rows[ $key ]['option_value'] = $new_val;
				return 1;
			}
			return 0;
		}

		if ( 0 === strpos( $sql, 'DELETE FROM' ) ) {
			$key = (string) ( $args[0] ?? '' );
			$val = (string) ( $args[1] ?? '' );
			if ( isset( $this->rows[ $key ] ) && $this->rows[ $key ]['option_value'] === $val ) {
				unset( $this->rows[ $key ] );
				return 1;
			}
			return 0;
		}

		return 0;
	}
}

$wpdb = new Mock_WPDB();

class WooCommerce {
	public Mock_Pay_Cart $cart;
	public Mock_Pay_Session $session;
	public Mock_Pay_Customer $customer;
	private Mock_WC_Payment_Gateways $gateways;
	public function __construct() {
		$this->cart     = new Mock_Pay_Cart();
		$this->session  = new Mock_Pay_Session();
		$this->customer = new Mock_Pay_Customer();
		$this->gateways = new Mock_WC_Payment_Gateways();
	}
	public function payment_gateways(): Mock_WC_Payment_Gateways { return $this->gateways; }
}

$despace_mock_wc = new WooCommerce();
function WC(): WooCommerce { global $despace_mock_wc; return $despace_mock_wc; }

require_once __DIR__ . '/../wordpress/wp-content/plugins/despace-core/includes/Checkout-Orders/CheckoutOrdersModule.php';

use DeSpace\CheckoutOrders\CheckoutOrdersModule;
use DeSpace\CheckoutOrders\Payment\PaymentMethodValidator;
use DeSpace\CheckoutOrders\Payment\PaymentStatusGuard;
use DeSpace\CheckoutOrders\Payment\BacsInstructions;

CheckoutOrdersModule::init();

function reset_payment_env( array $overrides = array() ): void {
	global $despace_mock_wc, $despace_mock_options, $despace_mock_current_user, $despace_mock_caps, $despace_mock_orders, $wpdb;
	$despace_mock_wc           = new WooCommerce();
	$despace_mock_current_user = 0;
	$despace_mock_caps         = array();
	$despace_mock_orders       = array();
	$wpdb                      = new Mock_WPDB();

	$test_account = array(
		'account_name'   => 'Test Fixture Merchant',
		'account_number' => '999900001111',
		'bank_name'      => 'Test Commercial Bank',
		'sort_code'      => '',
		'iban'           => '',
		'bic'            => '',
	);

	$despace_mock_options = array(
		'woocommerce_enable_guest_checkout' => 'yes',
		'woocommerce_bacs_settings'         => array(
			'title'        => 'Chuyển khoản ngân hàng (WC Test Option)',
			'description'  => 'Thanh toán qua tài khoản ngân hàng cấu hình trong WooCommerce.',
			'instructions' => 'Ghi rõ mã đơn hàng trong nội dung chuyển khoản.',
		),
		'woocommerce_bacs_accounts'         => array( $test_account ),
	);

	$cod_enabled  = $overrides['cod_enabled'] ?? 'yes';
	$bacs_enabled = $overrides['bacs_enabled'] ?? 'yes';
	$cod_methods  = $overrides['cod_methods'] ?? array();

	WC()->payment_gateways()->gateways = array(
		'cod'  => new Mock_WC_Gateway( 'cod', array(
			'title'              => 'Thanh toán khi nhận hàng (COD)',
			'description'        => 'Trả tiền mặt khi nhận hàng.',
			'enabled'            => $cod_enabled,
			'enable_for_methods' => $cod_methods,
		) ),
		'bacs' => new Mock_WC_Gateway( 'bacs', array(
			'title'           => $despace_mock_options['woocommerce_bacs_settings']['title'],
			'description'     => $despace_mock_options['woocommerce_bacs_settings']['description'],
			'instructions'    => $despace_mock_options['woocommerce_bacs_settings']['instructions'],
			'enabled'         => $bacs_enabled,
			'account_details' => $despace_mock_options['woocommerce_bacs_accounts'],
		) ),
	);

	WC()->session->set( 'chosen_shipping_methods', array( 'flat_rate:1' ) );
}

class M3PaymentUnitRunner {
	private array $results = array();

	public function run(): void {
		echo "========================================================================\n";
		echo "       DeSpace M3 Phase 2B — PAYMENT (COD & BACS) UNIT TEST SUITE       \n";
		echo "========================================================================\n\n";

		$this->tc_01_cod_enabled();
		$this->tc_02_cod_disabled();
		$this->tc_03_bacs_enabled();
		$this->tc_04_bacs_disabled();
		$this->tc_05_valid_payment_method();
		$this->tc_06_invalid_payment_method();
		$this->tc_07_gateway_unavailable_for_shipping_method();
		$this->tc_08_guest_cod();
		$this->tc_09_guest_bacs();
		$this->tc_10_logged_in_cod();
		$this->tc_11_logged_in_bacs();
		$this->tc_12_bacs_order_on_hold();
		$this->tc_13_bacs_no_date_paid();
		$this->tc_14_cod_not_auto_completed();
		$this->tc_15_frontend_spoof_payment_status();
		$this->tc_16_frontend_spoof_transaction_id();
		$this->tc_17_frontend_spoof_order_total();
		$this->tc_18_no_hardcoded_bank_account();
		$this->tc_19_no_fake_qr_code();
		$this->tc_20_hpos_compatibility_and_admin_confirm();
		$this->tc_21_no_manual_stock_reduction();
		$this->tc_22_no_inventory_event_created();
		$this->tc_23_bacs_instructions_hidden_when_disabled();
		$this->tc_24_gateway_settings_read_from_woocommerce();
		$this->tc_25_bacs_requires_explicit_receipt_confirmation();
		$this->tc_26_bacs_rechecks_order_state_inside_lock();
		$this->tc_27_bacs_atomic_lock_blocks_concurrent_confirm();
		$this->tc_28_bacs_payment_complete_failure_handling();
		$this->tc_29_bacs_lock_finally_expiry_and_owner_token();

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

	private function tc_01_cod_enabled(): void {
		reset_payment_env( array( 'cod_enabled' => 'yes' ) );
		$avail = PaymentMethodValidator::get_available_gateways();
		$ok    = true === PaymentMethodValidator::is_cod_enabled() && isset( $avail['cod'] );
		$this->record( 'TC-PAY-01', 'Detect native COD gateway enabled via WC', $ok );
	}

	private function tc_02_cod_disabled(): void {
		reset_payment_env( array( 'cod_enabled' => 'no' ) );
		$avail = PaymentMethodValidator::get_available_gateways();
		$val   = PaymentMethodValidator::validate_selected_payment_method( array( 'payment_method' => 'cod' ) );
		$ok    = false === PaymentMethodValidator::is_cod_enabled()
			&& ! isset( $avail['cod'] )
			&& is_wp_error( $val )
			&& 'despace_payment_method_unavailable' === $val->get_error_code();
		$this->record( 'TC-PAY-02', 'Reject COD when disabled in WooCommerce settings', $ok );
	}

	private function tc_03_bacs_enabled(): void {
		reset_payment_env( array( 'bacs_enabled' => 'yes' ) );
		$avail = PaymentMethodValidator::get_available_gateways();
		$ok    = true === PaymentMethodValidator::is_bacs_enabled() && isset( $avail['bacs'] );
		$this->record( 'TC-PAY-03', 'Detect native BACS gateway enabled via WC', $ok );
	}

	private function tc_04_bacs_disabled(): void {
		reset_payment_env( array( 'bacs_enabled' => 'no' ) );
		$avail = PaymentMethodValidator::get_available_gateways();
		$val   = PaymentMethodValidator::validate_selected_payment_method( array( 'payment_method' => 'bacs' ) );
		$ok    = false === PaymentMethodValidator::is_bacs_enabled()
			&& ! isset( $avail['bacs'] )
			&& is_wp_error( $val )
			&& 'despace_payment_method_unavailable' === $val->get_error_code();
		$this->record( 'TC-PAY-04', 'Reject BACS when disabled in WooCommerce settings', $ok );
	}

	private function tc_05_valid_payment_method(): void {
		reset_payment_env();
		$cod_res  = PaymentMethodValidator::validate_selected_payment_method( array( 'payment_method' => 'cod' ) );
		$bacs_res = PaymentMethodValidator::validate_selected_payment_method( array( 'payment_method' => 'bacs' ) );
		$ok = ! is_wp_error( $cod_res ) && 'cod' === $cod_res['payment_method']
			&& ! is_wp_error( $bacs_res ) && 'bacs' === $bacs_res['payment_method'];
		$this->record( 'TC-PAY-05', 'Accept valid enabled COD and BACS methods', $ok );
	}

	private function tc_06_invalid_payment_method(): void {
		reset_payment_env();
		$empty_res = PaymentMethodValidator::validate_selected_payment_method( array( 'payment_method' => '' ) );
		$fake_res  = PaymentMethodValidator::validate_selected_payment_method( array( 'payment_method' => 'despace_fake_qr' ) );
		$ok = is_wp_error( $empty_res )
			&& 'despace_missing_payment_method' === $empty_res->get_error_code()
			&& is_wp_error( $fake_res )
			&& 'despace_invalid_payment_method' === $fake_res->get_error_code();
		$this->record( 'TC-PAY-06', 'Reject empty, unknown or custom unapproved gateway', $ok );
	}

	private function tc_07_gateway_unavailable_for_shipping_method(): void {
		reset_payment_env( array( 'cod_methods' => array( 'flat_rate:1' ) ) );
		WC()->session->set( 'chosen_shipping_methods', array( 'local_pickup:3' ) );
		$cod_blocked = PaymentMethodValidator::validate_selected_payment_method( array( 'payment_method' => 'cod' ) );
		$bacs_ok     = PaymentMethodValidator::validate_selected_payment_method( array( 'payment_method' => 'bacs' ) );
		$ok = false === PaymentMethodValidator::is_gateway_available( 'cod' )
			&& is_wp_error( $cod_blocked )
			&& ! is_wp_error( $bacs_ok );
		$this->record( 'TC-PAY-07', 'Enforce COD shipping method restriction enable_for_methods', $ok );
	}

	private function tc_08_guest_cod(): void {
		global $despace_mock_current_user;
		reset_payment_env();
		$despace_mock_current_user = 0;
		$val = PaymentMethodValidator::validate_selected_payment_method( array( 'payment_method' => 'cod' ) );
		$ok  = ! is_wp_error( $val ) && 'cod' === $val['payment_method'];
		$this->record( 'TC-PAY-08', 'Allow Guest checkout using native COD gateway', $ok );
	}

	private function tc_09_guest_bacs(): void {
		global $despace_mock_current_user;
		reset_payment_env();
		$despace_mock_current_user = 0;
		$val = PaymentMethodValidator::validate_selected_payment_method( array( 'payment_method' => 'bacs' ) );

		$guest_order              = new Mock_Pay_Order();
		$guest_order->customer_id = 0;
		$guest_order->order_key   = 'wc_order_guest_key_99';

		$allowed = BacsInstructions::get_order_instructions( $guest_order, array( 'order_key' => 'wc_order_guest_key_99' ) );
		$denied  = BacsInstructions::get_order_instructions( $guest_order, array( 'order_key' => 'wrong_key' ) );

		$ok = ! is_wp_error( $val )
			&& ! is_wp_error( $allowed )
			&& ! empty( $allowed['accounts'] )
			&& is_wp_error( $denied )
			&& 'despace_unauthorized_bacs_view' === $denied->get_error_code();
		$this->record( 'TC-PAY-09', 'Allow Guest BACS checkout & verify order_key guard', $ok );
	}

	private function tc_10_logged_in_cod(): void {
		global $despace_mock_current_user;
		reset_payment_env();
		$despace_mock_current_user = 15;
		$val = PaymentMethodValidator::validate_selected_payment_method( array( 'payment_method' => 'cod' ) );
		$ok  = ! is_wp_error( $val ) && 'cod' === $val['payment_method'];
		$this->record( 'TC-PAY-10', 'Allow logged-in customer checkout using COD', $ok );
	}

	private function tc_11_logged_in_bacs(): void {
		global $despace_mock_current_user;
		reset_payment_env();
		$order              = new Mock_Pay_Order();
		$order->customer_id = 15;

		$despace_mock_current_user = 15;
		$owner_view                = BacsInstructions::get_order_instructions( $order );

		$despace_mock_current_user = 99; // Other customer must be denied!
		$other_view                = BacsInstructions::get_order_instructions( $order );

		$ok = ! is_wp_error( $owner_view )
			&& ! empty( $owner_view['accounts'] )
			&& is_wp_error( $other_view )
			&& 'despace_unauthorized_bacs_view' === $other_view->get_error_code();
		$this->record( 'TC-PAY-11', 'Logged-in BACS allows owner & blocks other customers', $ok );
	}

	private function tc_12_bacs_order_on_hold(): void {
		reset_payment_env();
		$order         = new Mock_Pay_Order();
		$order->total  = 4500000.0;
		$guarded_stat  = apply_filters( 'woocommerce_bacs_process_payment_order_status', 'processing', $order );
		$ok            = 'on-hold' === $guarded_stat;
		$this->record( 'TC-PAY-12', 'Enforce BACS order status strictly On hold before confirm', $ok );
	}

	private function tc_13_bacs_no_date_paid(): void {
		reset_payment_env();
		$order                 = new Mock_Pay_Order();
		$order->payment_method = 'bacs';
		$order->status         = 'on-hold';
		$order->date_paid      = '2026-11-12T10:00:00Z'; // Simulated injection before create_order hook.
		$order->transaction_id = 'FAKE-TX';

		do_action( 'woocommerce_checkout_create_order', $order, array( 'payment_method' => 'bacs' ) );

		$ok = null === $order->get_date_paid()
			&& false === $order->is_paid()
			&& '' === $order->get_transaction_id()
			&& true === PaymentStatusGuard::is_order_awaiting_settlement( $order );
		$this->record( 'TC-PAY-13', 'Ensure BACS order stays unpaid with empty date_paid', $ok );
	}

	private function tc_14_cod_not_auto_completed(): void {
		reset_payment_env();
		$order         = new Mock_Pay_Order();
		$order->total  = 4500000.0;
		$cod_stat      = apply_filters( 'woocommerce_cod_process_payment_order_status', 'completed', $order );
		$order->downloadable = true;
		$cod_dl_stat   = apply_filters( 'woocommerce_cod_process_payment_order_status', 'completed', $order );

		$ok = 'processing' === $cod_stat
			&& 'on-hold' === $cod_dl_stat
			&& 'completed' !== $cod_stat;
		$this->record( 'TC-PAY-14', 'Prevent COD order from auto-completing before delivery', $ok );
	}

	private function tc_15_frontend_spoof_payment_status(): void {
		reset_payment_env();
		$spoof_direct = PaymentMethodValidator::validate_selected_payment_method( array(
			'payment_method' => 'bacs',
			'payment_status' => 'paid',
			'is_paid'        => true,
		) );
		$stripped = apply_filters( 'woocommerce_checkout_posted_data', array(
			'payment_method' => 'bacs',
			'payment_status' => 'paid',
			'order_status'   => 'completed',
			'status'         => 'completed',
			'date_paid'      => '2026-11-12',
		) );
		$ok = is_wp_error( $spoof_direct )
			&& 'despace_forbidden_payment_override' === $spoof_direct->get_error_code()
			&& ! isset( $stripped['payment_status'], $stripped['order_status'], $stripped['status'], $stripped['date_paid'] );
		$this->record( 'TC-PAY-15', 'Block & strip frontend payment_status/order_status spoof', $ok );
	}

	private function tc_16_frontend_spoof_transaction_id(): void {
		reset_payment_env();
		$spoof = PaymentMethodValidator::validate_selected_payment_method( array(
			'payment_method'     => 'bacs',
			'transaction_id'     => 'BANK-FAKE-9999',
			'transaction_status' => 'settled',
		) );
		$stripped = apply_filters( 'woocommerce_checkout_posted_data', array(
			'payment_method' => 'bacs',
			'transaction_id' => 'BANK-FAKE-9999',
		) );
		$ok = is_wp_error( $spoof )
			&& 'despace_forbidden_payment_override' === $spoof->get_error_code()
			&& ! isset( $stripped['transaction_id'] );
		$this->record( 'TC-PAY-16', 'Block & strip frontend fake transaction_id submission', $ok );
	}

	private function tc_17_frontend_spoof_order_total(): void {
		reset_payment_env();
		$spoof = PaymentMethodValidator::validate_selected_payment_method( array(
			'payment_method' => 'cod',
			'order_total'    => '0',
		) );
		$stripped = apply_filters( 'woocommerce_checkout_posted_data', array(
			'payment_method' => 'cod',
			'order_total'    => '1',
			'total'          => '1',
		) );
		$ok = is_wp_error( $spoof )
			&& ! isset( $stripped['order_total'], $stripped['total'] );
		$this->record( 'TC-PAY-17', 'Block & strip frontend order_total manipulation', $ok );
	}

	private function get_combined_payment_source(): string {
		$files = array(
			__DIR__ . '/../wordpress/wp-content/plugins/despace-core/includes/Checkout-Orders/Payment/PaymentMethodValidator.php',
			__DIR__ . '/../wordpress/wp-content/plugins/despace-core/includes/Checkout-Orders/Payment/PaymentStatusGuard.php',
			__DIR__ . '/../wordpress/wp-content/plugins/despace-core/includes/Checkout-Orders/Payment/BacsInstructions.php',
		);
		$buf = '';
		foreach ( $files as $f ) {
			$buf .= "\n" . (string) file_get_contents( $f );
		}
		return $buf;
	}

	private function tc_18_no_hardcoded_bank_account(): void {
		$src = $this->get_combined_payment_source();
		$ok  = false === stripos( $src, 'vietcombank' )
			&& false === stripos( $src, 'techcombank' )
			&& false === stripos( $src, 'mbbank' )
			&& false === stripos( $src, 'bidv' )
			&& false === stripos( $src, 'vpbank' )
			&& false === stripos( $src, 'vietinbank' )
			&& false === strpos( $src, '999900001111' );
		$this->record( 'TC-PAY-18', 'Zero hardcoded bank names or account numbers in source', $ok );
	}

	private function tc_19_no_fake_qr_code(): void {
		global $despace_mock_current_user;
		reset_payment_env();
		$despace_mock_current_user = 15;
		$order                     = new Mock_Pay_Order();
		$order->customer_id        = 15;
		$instr                     = BacsInstructions::get_order_instructions( $order );
		$src                       = $this->get_combined_payment_source();

		$ok = false === stripos( $src, 'vietqr' )
			&& false === stripos( $src, 'img.vietqr.io' )
			&& false === ( $instr['qr_generated'] ?? true )
			&& '' === ( $instr['qr_image_url'] ?? 'bad' );
		$this->record( 'TC-PAY-19', 'Zero fake QR payment generation in source or payload', $ok );
	}

	private function tc_20_hpos_compatibility_and_admin_confirm(): void {
		global $despace_mock_caps, $despace_mock_orders, $wpdb;
		reset_payment_env();
		$order                  = new Mock_Pay_Order();
		$order->id              = 7001;
		$despace_mock_orders[7001] = $order;

		$unauth = PaymentStatusGuard::confirm_bacs_payment_by_admin( 7001, 'FT261112', array( 'confirmed_received' => true ) );
		$despace_mock_caps['manage_woocommerce'] = true;
		$valid_nonce = wp_create_nonce( 'despace_confirm_bacs_payment_7001' );
		$miss_nonce  = PaymentStatusGuard::confirm_bacs_payment_by_admin( 7001, 'FT261112', array( 'confirmed_received' => true ) );
		$bad_nonce   = PaymentStatusGuard::confirm_bacs_payment_by_admin( 7001, 'FT261112', array( 'nonce' => 'bad', 'confirmed_received' => true ) );
		$no_rcv      = PaymentStatusGuard::confirm_bacs_payment_by_admin( 7001, 'FT261112', array( 'nonce' => $valid_nonce, 'confirmed_received' => false ) );
		$auth        = PaymentStatusGuard::confirm_bacs_payment_by_admin( 7001, 'FT261112', array(
			'nonce'              => $valid_nonce,
			'confirmed_received' => true,
		) );
		$repeat      = PaymentStatusGuard::confirm_bacs_payment_by_admin( 7001, 'FT261112', array(
			'nonce'              => $valid_nonce,
			'confirmed_received' => true,
		) );

		$src = $this->get_combined_payment_source();
		$ok  = is_wp_error( $unauth )
			&& 'despace_unauthorized_payment_confirmation' === $unauth->get_error_code()
			&& is_wp_error( $miss_nonce )
			&& 'despace_invalid_nonce' === $miss_nonce->get_error_code()
			&& is_wp_error( $bad_nonce )
			&& 'despace_invalid_nonce' === $bad_nonce->get_error_code()
			&& is_wp_error( $no_rcv )
			&& 'despace_bacs_receipt_not_confirmed' === $no_rcv->get_error_code()
			&& ! is_wp_error( $auth )
			&& 'processing' === ( $auth['new_status'] ?? '' )
			&& true === ( $auth['is_paid'] ?? false )
			&& true === ( $auth['date_paid_set'] ?? false )
			&& 'FT261112' === $order->get_transaction_id()
			&& 1 === count( $order->notes )
			&& is_wp_error( $repeat )
			&& 'despace_bacs_already_processed' === $repeat->get_error_code()
			&& empty( $wpdb->rows )
			&& false === strpos( $src, 'update_post_meta' )
			&& false === strpos( $src, 'wp_posts' )
			&& false === strpos( $src, 'wp_postmeta' )
			&& false === strpos( $src, 'wp_wc_orders' );
		$this->record( 'TC-PAY-20', 'Enforce HPOS CRUD & capability-checked BACS confirm', $ok );
	}

	private function tc_21_no_manual_stock_reduction(): void {
		$src = $this->get_combined_payment_source();
		$ok  = false === strpos( $src, 'wc_reduce_stock_levels' )
			&& false === strpos( $src, 'wc_update_product_stock' )
			&& false === strpos( $src, 'wc_restore_stock_levels' )
			&& false === strpos( $src, 'set_stock_quantity' );
		$this->record( 'TC-PAY-21', 'Zero manual stock reduction calls in Payment module', $ok );
	}

	private function tc_22_no_inventory_event_created(): void {
		$src = $this->get_combined_payment_source();
		$ok  = false === strpos( $src, 'despace_inventory_logs' )
			&& false === strpos( $src, 'InventoryEventRecorder' )
			&& false === strpos( $src, 'InventoryStockObserver' );
		$this->record( 'TC-PAY-22', 'Zero M4 inventory log writes in Payment module', $ok );
	}

	private function tc_23_bacs_instructions_hidden_when_disabled(): void {
		global $despace_mock_current_user;
		reset_payment_env( array( 'bacs_enabled' => 'no' ) );
		$despace_mock_current_user = 15;
		$order                     = new Mock_Pay_Order();
		$order->customer_id        = 15;

		$pub = BacsInstructions::get_public_checkout_summary();
		$ord = BacsInstructions::get_order_instructions( $order );

		$ok = false === $pub['enabled']
			&& false === $pub['has_bank_accounts']
			&& '' === $pub['instructions']
			&& is_wp_error( $ord )
			&& 'despace_bacs_disabled' === $ord->get_error_code();
		$this->record( 'TC-PAY-23', 'Hide BACS instructions & bank accounts when disabled', $ok );
	}

	private function tc_24_gateway_settings_read_from_woocommerce(): void {
		global $despace_mock_current_user;
		reset_payment_env();
		$despace_mock_current_user = 15;

		// Mutate live WC gateway config dynamically -> helper must reflect new WC values without code edits.
		WC()->payment_gateways()->gateways['bacs']->instructions = 'Huong dan moi tu admin WC';
		WC()->payment_gateways()->gateways['bacs']->account_details = array(
			array(
				'account_name'   => 'Cong ty DeSpace',
				'account_number' => '1122334455',
				'bank_name'      => 'Dynamic Admin Bank',
			),
		);

		$order              = new Mock_Pay_Order();
		$order->customer_id = 15;
		$instr              = BacsInstructions::get_order_instructions( $order );
		$cfg                = apply_filters( 'despace_m3_cart_frontend_config', array() );

		$ok = ! is_wp_error( $instr )
			&& 'Huong dan moi tu admin WC' === $instr['instructions']
			&& '1122334455' === ( $instr['accounts'][0]['account_number'] ?? '' )
			&& 'Dynamic Admin Bank' === ( $instr['accounts'][0]['bank_name'] ?? '' )
			&& 'woocommerce_native_gateways' === ( $cfg['payment']['source_of_truth'] ?? '' )
			&& false === ( $cfg['payment']['qr_generation_enabled'] ?? true );
		$this->record( 'TC-PAY-24', 'Reflect dynamic WC gateway settings without code edits', $ok );
	}

	private function tc_25_bacs_requires_explicit_receipt_confirmation(): void {
		global $despace_mock_caps, $despace_mock_orders;
		reset_payment_env();
		$despace_mock_caps['manage_woocommerce'] = true;
		$order                    = new Mock_Pay_Order();
		$order->id                = 7002;
		$despace_mock_orders[7002] = $order;
		$nonce                    = wp_create_nonce( 'despace_confirm_bacs_payment_7002' );

		$missing   = PaymentStatusGuard::confirm_bacs_payment_by_admin( 7002, 'FT01', array( 'nonce' => $nonce ) );
		$false_val = PaymentStatusGuard::confirm_bacs_payment_by_admin( 7002, 'FT01', array( 'nonce' => $nonce, 'confirmed_received' => false ) );
		$ambig_str = PaymentStatusGuard::confirm_bacs_payment_by_admin( 7002, 'FT01', array( 'nonce' => $nonce, 'confirmed_received' => 'maybe' ) );
		$ambig_int = PaymentStatusGuard::confirm_bacs_payment_by_admin( 7002, 'FT01', array( 'nonce' => $nonce, 'confirmed_received' => 2 ) );
		$null_val  = PaymentStatusGuard::confirm_bacs_payment_by_admin( 7002, 'FT01', array( 'nonce' => $nonce, 'confirmed_received' => null ) );
		$str_true  = PaymentStatusGuard::confirm_bacs_payment_by_admin( 7002, 'FT01', array( 'nonce' => $nonce, 'confirmed_received' => 'true' ) );

		$ok = is_wp_error( $missing ) && 'despace_bacs_receipt_confirmation_required' === $missing->get_error_code()
			&& is_wp_error( $false_val ) && 'despace_bacs_receipt_not_confirmed' === $false_val->get_error_code()
			&& is_wp_error( $ambig_str ) && 'despace_invalid_bacs_receipt_confirmation' === $ambig_str->get_error_code()
			&& is_wp_error( $ambig_int ) && 'despace_invalid_bacs_receipt_confirmation' === $ambig_int->get_error_code()
			&& is_wp_error( $null_val ) && 'despace_invalid_bacs_receipt_confirmation' === $null_val->get_error_code()
			&& ! is_wp_error( $str_true ) && true === ( $str_true['confirmed'] ?? false );
		$this->record( 'TC-PAY-25', 'Require explicit boolean confirmed_received cleanly', $ok );
	}

	private function tc_26_bacs_rechecks_order_state_inside_lock(): void {
		global $despace_mock_caps, $despace_mock_orders, $wpdb;
		reset_payment_env();
		$despace_mock_caps['edit_shop_orders'] = true;

		$cod               = new Mock_Pay_Order();
		$cod->id           = 7003;
		$cod->payment_method = 'cod';
		$despace_mock_orders[7003] = $cod;

		$processing         = new Mock_Pay_Order();
		$processing->id     = 7004;
		$processing->status = 'processing';
		$despace_mock_orders[7004] = $processing;

		$paid        = new Mock_Pay_Order();
		$paid->id    = 7005;
		$paid->paid  = true;
		$despace_mock_orders[7005] = $paid;

		$res_cod  = PaymentStatusGuard::confirm_bacs_payment_by_admin( 7003, 'FT', array( 'nonce' => wp_create_nonce( 'despace_confirm_bacs_payment_7003' ), 'confirmed_received' => true ) );
		$res_proc = PaymentStatusGuard::confirm_bacs_payment_by_admin( 7004, 'FT', array( 'nonce' => wp_create_nonce( 'despace_confirm_bacs_payment_7004' ), 'confirmed_received' => true ) );
		$res_paid = PaymentStatusGuard::confirm_bacs_payment_by_admin( 7005, 'FT', array( 'nonce' => wp_create_nonce( 'despace_confirm_bacs_payment_7005' ), 'confirmed_received' => true ) );

		$ok = is_wp_error( $res_cod ) && 'despace_invalid_bacs_order' === $res_cod->get_error_code()
			&& is_wp_error( $res_proc ) && 'despace_bacs_already_processed' === $res_proc->get_error_code()
			&& is_wp_error( $res_paid ) && 'despace_bacs_already_processed' === $res_paid->get_error_code()
			&& empty( $wpdb->rows );
		$this->record( 'TC-PAY-26', 'Reject non-BACS, non-on-hold, & paid orders inside lock', $ok );
	}

	private function tc_27_bacs_atomic_lock_blocks_concurrent_confirm(): void {
		global $despace_mock_caps, $despace_mock_orders, $wpdb;
		reset_payment_env();
		$despace_mock_caps['manage_woocommerce'] = true;

		$order                    = new Mock_Pay_Order();
		$order->id                = 7006;
		$despace_mock_orders[7006] = $order;
		$nonce                    = wp_create_nonce( 'despace_confirm_bacs_payment_7006' );

		$concurrent_result = null;
		// Simulate overlapping second request arriving while first request is inside payment_complete().
		$order->on_payment_complete = function () use ( &$concurrent_result, $nonce ): void {
			$concurrent_result = PaymentStatusGuard::confirm_bacs_payment_by_admin(
				7006,
				'FT-SECOND',
				array(
					'nonce'              => $nonce,
					'confirmed_received' => true,
				)
			);
		};

		$first_result = PaymentStatusGuard::confirm_bacs_payment_by_admin(
			7006,
			'FT-FIRST',
			array(
				'nonce'              => $nonce,
				'confirmed_received' => true,
			)
		);

		$ok = ! is_wp_error( $first_result )
			&& true === ( $first_result['confirmed'] ?? false )
			&& 1 === $order->payment_complete_calls
			&& 'FT-FIRST' === $order->get_transaction_id()
			&& 1 === count( $order->notes )
			&& is_wp_error( $concurrent_result )
			&& 'despace_bacs_confirmation_in_progress' === $concurrent_result->get_error_code()
			&& empty( $wpdb->rows );
		$this->record( 'TC-PAY-27', 'Block concurrent BACS confirmation via atomic DB lock', $ok );
	}

	private function tc_28_bacs_payment_complete_failure_handling(): void {
		global $despace_mock_caps, $despace_mock_orders, $wpdb;
		reset_payment_env();
		$despace_mock_caps['manage_woocommerce'] = true;

		$order_fail                          = new Mock_Pay_Order();
		$order_fail->id                      = 7007;
		$order_fail->payment_complete_result = false;
		$despace_mock_orders[7007]           = $order_fail;

		$order_noop                                  = new Mock_Pay_Order();
		$order_noop->id                              = 7008;
		$order_noop->payment_complete_no_state_change = true;
		$despace_mock_orders[7008]                   = $order_noop;

		$r_fail = PaymentStatusGuard::confirm_bacs_payment_by_admin( 7007, 'FT', array(
			'nonce'              => wp_create_nonce( 'despace_confirm_bacs_payment_7007' ),
			'confirmed_received' => true,
		) );
		$r_noop = PaymentStatusGuard::confirm_bacs_payment_by_admin( 7008, 'FT', array(
			'nonce'              => wp_create_nonce( 'despace_confirm_bacs_payment_7008' ),
			'confirmed_received' => true,
		) );

		$ok = is_wp_error( $r_fail )
			&& 'despace_bacs_payment_complete_failed' === $r_fail->get_error_code()
			&& 0 === count( $order_fail->notes )
			&& is_wp_error( $r_noop )
			&& 'despace_bacs_payment_not_recorded' === $r_noop->get_error_code()
			&& 0 === count( $order_noop->notes )
			&& empty( $wpdb->rows );
		$this->record( 'TC-PAY-28', 'Reject payment_complete false/unrecorded & skip note', $ok );
	}

	private function tc_29_bacs_lock_finally_expiry_and_owner_token(): void {
		global $despace_mock_caps, $despace_mock_orders, $wpdb;
		reset_payment_env();
		$despace_mock_caps['manage_woocommerce'] = true;

		// 1. Exception during payment_complete must still release lock via finally.
		$order_exc                            = new Mock_Pay_Order();
		$order_exc->id                        = 7009;
		$order_exc->throw_on_payment_complete = true;
		$despace_mock_orders[7009]            = $order_exc;
		$caught                               = false;
		try {
			PaymentStatusGuard::confirm_bacs_payment_by_admin( 7009, 'FT', array(
				'nonce'              => wp_create_nonce( 'despace_confirm_bacs_payment_7009' ),
				'confirmed_received' => true,
			) );
		} catch ( \RuntimeException $e ) {
			$caught = true;
		}
		$lock_released_after_exc = $caught && empty( $wpdb->rows ) && 0 === count( $order_exc->notes );

		// 2. Expired lock taken over atomically by Request B; stale Request A cannot delete B's lock.
		$stale_payload = (string) json_encode( array( 'token' => 'stale_tok_A', 'expires_at' => time() - 60 ) );
		$wpdb->rows['despace_bacs_lock_7010'] = array( 'option_value' => $stale_payload, 'autoload' => 'no' );
		$lock_b = PaymentStatusGuard::acquire_bacs_lock( 7010, 30 );
		$stale_release_res = PaymentStatusGuard::release_bacs_lock( array(
			'key'     => 'despace_bacs_lock_7010',
			'token'   => 'stale_tok_A',
			'payload' => $stale_payload,
		) );
		$lock_b_still_present = ! is_wp_error( $lock_b )
			&& false === $stale_release_res
			&& isset( $wpdb->rows['despace_bacs_lock_7010'] )
			&& $wpdb->rows['despace_bacs_lock_7010']['option_value'] === $lock_b['payload'];
		$own_release_res = is_array( $lock_b ) && PaymentStatusGuard::release_bacs_lock( $lock_b );

		$ok = $lock_released_after_exc && $lock_b_still_present && $own_release_res && empty( $wpdb->rows );
		$this->record( 'TC-PAY-29', 'Release lock on exception & protect owner token on expiry', $ok );
	}
}

$runner = new M3PaymentUnitRunner();
$runner->run();

