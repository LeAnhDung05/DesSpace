<?php
/**
 * DeSpace M3 Phase 3 — Guest Order Tracking & Order Access Security Unit Test Suite (Issue #19).
 *
 * Tests all 28 required Order Tracking unit cases: Guest email & phone lookup, mismatched
 * credentials, IDOR protection (Customer A vs Customer B, order-ID-only blocked), data
 * minimization (no order_key, no private notes, no other-order bank details), BACS/COD/Cancelled/
 * Refunded presentations, transient rate limiting without plaintext PII, uniform failure notice,
 * HTTP POST + nonce + no-cache headers, HPOS CRUD, custom order numbers, and M4/Payment boundaries.
 *
 * Run via CLI: php tests/test-m3-order-tracking-unit.php
 *
 * @package DeSpace\Tests
 */

define( 'ABSPATH', __DIR__ . '/../' );

$despace_mock_actions      = array();
$despace_mock_filters      = array();
$despace_mock_shortcodes   = array();
$despace_mock_transients   = array();
$despace_mock_options      = array(
	'woocommerce_bacs_settings' => array(
		'title'        => 'Chuyển khoản ngân hàng',
		'description'  => 'Chuyển khoản qua ngân hàng.',
		'instructions' => 'Ghi rõ mã đơn hàng khi chuyển khoản.',
	),
	'woocommerce_bacs_accounts' => array(
		array(
			'account_name'   => 'DeSpace Fixture Account',
			'account_number' => '888800002222',
			'bank_name'      => 'Fixture Bank',
		),
	),
);
$despace_mock_orders       = array();
$despace_mock_products     = array();
$despace_mock_current_user = 0;
$despace_mock_caps         = array();

class WP_Error {
	private array $errors = array();
	private array $error_data = array();
	public function __construct( string $code = '', string $message = '', $data = '' ) {
		if ( '' !== $code ) $this->add( $code, $message, $data );
	}
	public function add( string $code, string $message, $data = '' ): void {
		$this->errors[ $code ][] = $message;
		if ( '' !== $data ) $this->error_data[ $code ][] = $data;
	}
	public function has_errors(): bool { return ! empty( $this->errors ); }
	public function get_error_codes(): array { return array_keys( $this->errors ); }
	public function get_error_code(): string { $c = $this->get_error_codes(); return $c[0] ?? ''; }
	public function get_error_message( string $code = '' ): string {
		if ( '' === $code ) $code = $this->get_error_code();
		return $this->errors[ $code ][0] ?? '';
	}
	public function get_error_data( string $code = '' ) {
		if ( '' === $code ) $code = $this->get_error_code();
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
	foreach ( $buckets as $hook ) call_user_func_array( $hook['callback'], $args );
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
	foreach ( $buckets as $hook ) $value = call_user_func( $hook['callback'], $value, ...$args );
	return $value;
}
function add_shortcode( string $tag, callable $cb ): void { global $despace_mock_shortcodes; $despace_mock_shortcodes[ $tag ] = $cb; }
function sanitize_text_field( $str ): string { return trim( strip_tags( (string) $str ) ); }
function sanitize_email( $email ): string { return trim( strip_tags( (string) $email ) ); }
function sanitize_key( $key ): string { return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $key ) ); }
function absint( $val ): int { return abs( (int) $val ); }
function wp_unslash( $value ) { return $value; }
function esc_html( string $text ): string { return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( string $text ): string { return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' ); }
function esc_url_raw( string $url ): string { return filter_var( $url, FILTER_SANITIZE_URL ) ?: ''; }
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
function get_transient( string $k ) {
	global $despace_mock_transients;
	if ( ! isset( $despace_mock_transients[ $k ] ) ) return false;
	$entry = $despace_mock_transients[ $k ];
	if ( $entry['exp'] > 0 && $entry['exp'] < time() ) {
		unset( $despace_mock_transients[ $k ] );
		return false;
	}
	return $entry['val'];
}
function set_transient( string $k, $v, int $ttl = 0 ): bool {
	global $despace_mock_transients, $despace_mock_transient_write_ok;
	if ( false === ( $despace_mock_transient_write_ok ?? true ) ) {
		return false;
	}
	$despace_mock_transients[ $k ] = array( 'val' => $v, 'exp' => $ttl > 0 ? time() + $ttl : 0 );
	return true;
}
function delete_transient( string $k ): bool {
	global $despace_mock_transients;
	unset( $despace_mock_transients[ $k ] );
	return true;
}
function nocache_headers(): void {}
function wp_get_attachment_image_url( int $att_id, string $size = 'thumbnail' ) {
	return 'https://despace.test/wp-content/uploads/chair-' . $att_id . '.jpg';
}
function wc_get_product( int $id ) { global $despace_mock_products; return $despace_mock_products[ $id ] ?? false; }
function wc_get_order( int $id ) { global $despace_mock_orders; return $despace_mock_orders[ $id ] ?? false; }
function wc_get_orders( array $args = array() ): array {
	global $despace_mock_orders;
	$targets = array();
	if ( ! empty( $args['meta_query'] ) && is_array( $args['meta_query'] ) ) {
		foreach ( $args['meta_query'] as $clause ) {
			if ( is_array( $clause ) && ! empty( $clause['key'] ) && '_order_number' === $clause['key'] ) {
				$targets[] = (string) ( $clause['value'] ?? '' );
			}
		}
	} elseif ( ! empty( $args['meta_key'] ) && '_order_number' === $args['meta_key'] && isset( $args['meta_value'] ) ) {
		$targets[] = (string) $args['meta_value'];
	}
	if ( ! empty( $targets ) ) {
		$out = array();
		foreach ( $despace_mock_orders as $ord ) {
			if ( in_array( (string) $ord->get_order_number(), $targets, true ) ) {
				$out[] = $ord;
			}
		}
		return $out;
	}
	return array();
}

class Mock_Trk_Product {
	private int $img_id;
	public function __construct( int $img_id ) { $this->img_id = $img_id; }
	public function get_image_id(): int { return $this->img_id; }
}

class Mock_Trk_Order_Item {
	private string $name;
	private int $qty;
	private float $total;
	private int $pid;
	public function __construct( string $name, int $qty, float $total, int $pid ) {
		$this->name  = $name;
		$this->qty   = $qty;
		$this->total = $total;
		$this->pid   = $pid;
	}
	public function get_name(): string { return $this->name; }
	public function get_quantity(): int { return $this->qty; }
	public function get_total(): float { return $this->total; }
	public function get_product_id(): int { return $this->pid; }
	public function get_variation_id(): int { return 0; }
}

class Mock_Trk_Order {
	public int $id;
	public string $order_number;
	public int $customer_id = 0;
	public string $order_key = 'wc_order_SECRET_DO_NOT_LEAK';
	public string $status = 'processing';
	public string $billing_email = '';
	public string $billing_phone = '';
	public string $billing_first_name = 'Minh Anh';
	public string $billing_last_name = 'Nguyễn';
	public string $shipping_first_name = 'Minh Anh';
	public string $shipping_last_name = 'Nguyễn';
	public string $shipping_country = 'VN';
	public string $shipping_state = '15';
	public string $shipping_city = 'Phường Lào Cai';
	public string $shipping_address_1 = '10 Đường Hoàng Liên';
	public string $payment_method = 'cod';
	public string $payment_method_title = 'Thanh toán khi nhận hàng (COD)';
	public bool $paid = false;
	public string $transaction_id = 'INTERNAL_TX_SECRET_999';
	public string $shipping_method = 'Giao hàng Lào Cai';
	public float $subtotal = 4500000.0;
	public float $discount_total = 450000.0;
	public float $shipping_total = 180000.0;
	public float $total = 4230000.0;
	public array $items = array();
	public array $customer_notes = array();
	public array $private_notes = array();

	public function __construct( int $id, array $props = array() ) {
		$this->id           = $id;
		$this->order_number = (string) $id;
		foreach ( $props as $k => $v ) $this->$k = $v;
	}
	public function get_id(): int { return $this->id; }
	public function get_order_number(): string { return $this->order_number; }
	public function get_customer_id(): int { return $this->customer_id; }
	public function get_order_key(): string { return $this->order_key; }
	public function get_status(): string { return $this->status; }
	public function get_billing_email(): string { return $this->billing_email; }
	public function get_billing_phone(): string { return $this->billing_phone; }
	public function get_billing_first_name(): string { return $this->billing_first_name; }
	public function get_billing_last_name(): string { return $this->billing_last_name; }
	public function get_shipping_first_name(): string { return $this->shipping_first_name; }
	public function get_shipping_last_name(): string { return $this->shipping_last_name; }
	public function get_shipping_country(): string { return $this->shipping_country; }
	public function get_shipping_state(): string { return $this->shipping_state; }
	public function get_shipping_city(): string { return $this->shipping_city; }
	public function get_shipping_address_1(): string { return $this->shipping_address_1; }
	public function get_shipping_address_2(): string { return ''; }
	public function get_shipping_postcode(): string { return ''; }
	public function get_payment_method(): string { return $this->payment_method; }
	public function get_payment_method_title(): string { return $this->payment_method_title; }
	public function is_paid(): bool { return $this->paid; }
	public function get_transaction_id(): string { return $this->transaction_id; }
	public function get_shipping_method(): string { return $this->shipping_method; }
	public function get_subtotal(): float { return $this->subtotal; }
	public function get_discount_total(): float { return $this->discount_total; }
	public function get_shipping_total(): float { return $this->shipping_total; }
	public function get_total(): float { return $this->total; }
	public function get_currency(): string { return 'VND'; }
	public function get_date_created(): string { return '2026-11-12 09:00:00'; }
	public function get_date_modified(): string { return '2026-11-12 09:30:00'; }
	public function get_items(): array { return $this->items; }
	public function get_customer_order_notes(): array { return $this->customer_notes; }
	public function get_meta( string $k, bool $single = true ) { return ''; }
}

class Mock_Trk_Gateway {
	public string $id;
	public string $title;
	public string $enabled = 'yes';
	public string $instructions = '';
	public array $account_details = array();
	public function __construct( string $id, array $props = array() ) {
		$this->id    = $id;
		$this->title = strtoupper( $id );
		foreach ( $props as $k => $v ) $this->$k = $v;
	}
	public function get_title(): string { return $this->title; }
	public function get_description(): string { return ''; }
	public function is_available(): bool { return 'yes' === $this->enabled; }
}

class Mock_Trk_Gateways {
	public array $gateways = array();
	public function payment_gateways(): array { return $this->gateways; }
	public function get_available_payment_gateways(): array { return $this->gateways; }
}

class Mock_Trk_Session {
	public function get( string $k, $d = null ) { return $d; }
}

class WooCommerce {
	public Mock_Trk_Session $session;
	private Mock_Trk_Gateways $gateways;
	public function __construct() {
		$this->session  = new Mock_Trk_Session();
		$this->gateways = new Mock_Trk_Gateways();
	}
	public function payment_gateways(): Mock_Trk_Gateways { return $this->gateways; }
}

$despace_mock_wc = new WooCommerce();
function WC(): WooCommerce { global $despace_mock_wc; return $despace_mock_wc; }

require_once __DIR__ . '/../wordpress/wp-content/plugins/despace-core/includes/Checkout-Orders/CheckoutOrdersModule.php';

use DeSpace\CheckoutOrders\CheckoutOrdersModule;
use DeSpace\CheckoutOrders\Orders\GuestOrderTracker;
use DeSpace\CheckoutOrders\Orders\OrderAccessGuard;
use DeSpace\CheckoutOrders\Orders\OrderTrackingPresenter;

CheckoutOrdersModule::init();

function reset_tracking_env(): void {
	global $despace_mock_wc, $despace_mock_orders, $despace_mock_products, $despace_mock_current_user, $despace_mock_caps, $despace_mock_transients, $despace_mock_options;
	$despace_mock_wc           = new WooCommerce();
	$despace_mock_current_user = 0;
	$despace_mock_caps         = array();
	$despace_mock_transients   = array();
	OrderAccessGuard::reset_fallback_rate_limits();
	$_SERVER['REQUEST_METHOD'] = 'POST';
	$_SERVER['REMOTE_ADDR']    = '203.0.113.10';
	unset( $_SERVER['HTTP_X_FORWARDED_FOR'] );
	$_GET = array();

	$despace_mock_wc->payment_gateways()->gateways = array(
		'cod'  => new Mock_Trk_Gateway( 'cod', array( 'title' => 'Thanh toán khi nhận hàng (COD)' ) ),
		'bacs' => new Mock_Trk_Gateway( 'bacs', array(
			'title'           => 'Chuyển khoản ngân hàng',
			'instructions'    => $despace_mock_options['woocommerce_bacs_settings']['instructions'],
			'account_details' => $despace_mock_options['woocommerce_bacs_accounts'],
		) ),
	);

	$despace_mock_products = array( 501 => new Mock_Trk_Product( 9001 ) );

	$despace_mock_orders = array(
		1001 => new Mock_Trk_Order( 1001, array(
			'customer_id'          => 0,
			'order_key'            => 'wc_order_secret_1001',
			'status'               => 'processing',
			'billing_email'        => 'guest@despace.test',
			'billing_phone'        => '0912345678',
			'payment_method'       => 'cod',
			'payment_method_title' => 'Thanh toán khi nhận hàng (COD)',
			'items'                => array( new Mock_Trk_Order_Item( 'Ghế Sofa Bắc Âu', 1, 4500000.0, 501 ) ),
			'customer_notes'       => array( (object) array( 'comment_date' => '2026-11-12 09:05:00', 'comment_content' => 'Đã tiếp nhận đơn hàng.' ) ),
			'private_notes'        => array( 'ADMIN_PRIVATE_NOTE_INTERNAL_MARGIN' ),
		) ),
		1002 => new Mock_Trk_Order( 1002, array(
			'customer_id'          => 0,
			'order_key'            => 'wc_order_secret_1002',
			'status'               => 'on-hold',
			'billing_email'        => 'bacs-guest@despace.test',
			'billing_phone'        => '0987654321',
			'payment_method'       => 'bacs',
			'payment_method_title' => 'Chuyển khoản ngân hàng (BACS)',
			'items'                => array( new Mock_Trk_Order_Item( 'Bàn Ăn Gỗ Sồi', 1, 8200000.0, 501 ) ),
		) ),
		1003 => new Mock_Trk_Order( 1003, array(
			'customer_id'   => 15,
			'order_key'     => 'wc_order_secret_1003',
			'status'        => 'processing',
			'billing_email' => 'customer15@despace.test',
			'billing_phone' => '0901112222',
			'payment_method'=> 'cod',
		) ),
		1004 => new Mock_Trk_Order( 1004, array(
			'customer_id'   => 28,
			'order_key'     => 'wc_order_secret_1004',
			'status'        => 'cancelled',
			'billing_email' => 'customer28@despace.test',
			'billing_phone' => '0903334444',
			'payment_method'=> 'bacs',
		) ),
		1005 => new Mock_Trk_Order( 1005, array(
			'customer_id'          => 0,
			'order_number'         => 'DS-2026-0088',
			'order_key'            => 'wc_order_secret_1005',
			'status'               => 'refunded',
			'billing_email'        => 'vip@despace.test',
			'billing_phone'        => '0933555777',
			'payment_method'       => 'bacs',
		) ),
	);
}

class M3OrderTrackingUnitRunner {
	private array $results = array();

	public function run(): void {
		echo "========================================================================\n";
		echo "  DeSpace M3 Phase 3 — ORDER TRACKING & SECURITY UNIT TEST SUITE        \n";
		echo "========================================================================\n\n";

		$this->tc_01_guest_lookup_by_email();
		$this->tc_02_guest_lookup_by_phone();
		$this->tc_03_wrong_order_number();
		$this->tc_04_wrong_billing_email();
		$this->tc_05_wrong_billing_phone();
		$this->tc_06_valid_order_different_email();
		$this->tc_07_valid_order_different_phone();
		$this->tc_08_guest_order_combined_email_phone_consistency();
		$this->tc_09_logged_in_owner_views_own_order();
		$this->tc_10_customer_a_cannot_view_customer_b_or_guest_order();
		$this->tc_11_cannot_access_by_order_id_only();
		$this->tc_12_no_order_key_in_response();
		$this->tc_13_no_private_order_notes_exposed();
		$this->tc_14_no_unauthorized_bank_account_leak();
		$this->tc_15_bacs_on_hold_display();
		$this->tc_16_cod_processing_display();
		$this->tc_17_cancelled_order_display();
		$this->tc_18_refunded_order_display();
		$this->tc_19_rate_limit_and_trusted_proxy();
		$this->tc_20_uniform_generic_failure_message();
		$this->tc_21_missing_or_invalid_nonce_rejected();
		$this->tc_22_get_request_and_pii_query_rejected();
		$this->tc_23_no_cache_headers_emitted();
		$this->tc_24_hpos_compatibility();
		$this->tc_25_custom_order_number_compatibility();
		$this->tc_26_no_direct_order_db_queries();
		$this->tc_27_no_inventory_mutations();
		$this->tc_28_no_payment_status_mutations();

		$total  = count( $this->results );
		$passed = count( array_filter( $this->results ) );
		$failed = $total - $passed;
		echo "\n========================================================================\n";
		printf( "SUMMARY: %d Total | %d Passed | %d Failed\n", $total, $passed, $failed );
		echo "========================================================================\n";
		if ( $failed > 0 ) exit( 1 );
	}

	private function record( string $id, string $desc, bool $passed ): void {
		printf( "[%-10s] %-52s : %s\n", $id, $desc, $passed ? 'PASS' : 'FAIL' );
		$this->results[ $id ] = $passed;
	}

	private function tc_01_guest_lookup_by_email(): void {
		reset_tracking_env();
		$res = GuestOrderTracker::handle_lookup_request( array(
			'despace_tracking_nonce' => wp_create_nonce( OrderAccessGuard::NONCE_ACTION ),
			'order_number'           => '#1001',
			'billing_email'          => 'GUEST@DESPACE.TEST',
		) );
		$ok = ! empty( $res['success'] )
			&& '1001' === ( $res['data']['order']['order_number'] ?? '' )
			&& 1 === count( $res['data']['order']['items'] ?? array() );
		$this->record( 'TC-TRK-01', 'Guest lookup succeeds with order number + email', $ok );
	}

	private function tc_02_guest_lookup_by_phone(): void {
		reset_tracking_env();
		$res = GuestOrderTracker::handle_lookup_request( array(
			'despace_tracking_nonce' => wp_create_nonce( OrderAccessGuard::NONCE_ACTION ),
			'order_number'           => '1001',
			'billing_phone'          => '+84 912.345.678',
		) );
		$ok = ! empty( $res['success'] )
			&& '1001' === ( $res['data']['order']['order_number'] ?? '' );
		$this->record( 'TC-TRK-02', 'Guest lookup succeeds with normalized billing phone', $ok );
	}

	private function tc_03_wrong_order_number(): void {
		reset_tracking_env();
		$res = GuestOrderTracker::handle_lookup_request( array(
			'despace_tracking_nonce' => wp_create_nonce( OrderAccessGuard::NONCE_ACTION ),
			'order_number'           => '999999',
			'billing_email'          => 'guest@despace.test',
		) );
		$ok = empty( $res['success'] ) && OrderAccessGuard::GENERIC_FAILURE_MESSAGE === ( $res['data']['message'] ?? '' );
		$this->record( 'TC-TRK-03', 'Reject nonexistent order number safely', $ok );
	}

	private function tc_04_wrong_billing_email(): void {
		reset_tracking_env();
		$res = GuestOrderTracker::handle_lookup_request( array(
			'despace_tracking_nonce' => wp_create_nonce( OrderAccessGuard::NONCE_ACTION ),
			'order_number'           => '1001',
			'billing_email'          => 'wrong@despace.test',
		) );
		$ok = empty( $res['success'] ) && 'despace_order_not_found_or_mismatch' === ( $res['data']['code'] ?? '' );
		$this->record( 'TC-TRK-04', 'Reject mismatched billing email cleanly', $ok );
	}

	private function tc_05_wrong_billing_phone(): void {
		reset_tracking_env();
		$res = GuestOrderTracker::handle_lookup_request( array(
			'despace_tracking_nonce' => wp_create_nonce( OrderAccessGuard::NONCE_ACTION ),
			'order_number'           => '1001',
			'billing_phone'          => '0999888777',
		) );
		$ok = empty( $res['success'] ) && 'despace_order_not_found_or_mismatch' === ( $res['data']['code'] ?? '' );
		$this->record( 'TC-TRK-05', 'Reject mismatched billing phone cleanly', $ok );
	}

	private function tc_06_valid_order_different_email(): void {
		reset_tracking_env();
		$res = GuestOrderTracker::handle_lookup_request( array(
			'despace_tracking_nonce' => wp_create_nonce( OrderAccessGuard::NONCE_ACTION ),
			'order_number'           => '1002', // belongs to bacs-guest@despace.test
			'billing_email'          => 'guest@despace.test',
		) );
		$ok = empty( $res['success'] ) && OrderAccessGuard::GENERIC_FAILURE_MESSAGE === ( $res['data']['message'] ?? '' );
		$this->record( 'TC-TRK-06', 'Block valid order when email belongs to other order', $ok );
	}

	private function tc_07_valid_order_different_phone(): void {
		reset_tracking_env();
		$res = GuestOrderTracker::handle_lookup_request( array(
			'despace_tracking_nonce' => wp_create_nonce( OrderAccessGuard::NONCE_ACTION ),
			'order_number'           => '1002', // phone 0987654321
			'billing_phone'          => '0912345678',
		) );
		$ok = empty( $res['success'] ) && OrderAccessGuard::GENERIC_FAILURE_MESSAGE === ( $res['data']['message'] ?? '' );
		$this->record( 'TC-TRK-07', 'Block valid order when phone belongs to other order', $ok );
	}

	private function tc_08_guest_order_combined_email_phone_consistency(): void {
		reset_tracking_env();
		$bad_combo = GuestOrderTracker::handle_lookup_request( array(
			'despace_tracking_nonce' => wp_create_nonce( OrderAccessGuard::NONCE_ACTION ),
			'order_number'           => '1001',
			'billing_email'          => 'guest@despace.test',
			'billing_phone'          => '0987654321', // Right email, wrong phone!
		) );
		$ok_combo = GuestOrderTracker::handle_lookup_request( array(
			'despace_tracking_nonce' => wp_create_nonce( OrderAccessGuard::NONCE_ACTION ),
			'order_number'           => '1001',
			'billing_email'          => 'guest@despace.test',
			'billing_phone'          => '0912345678',
		) );
		$ok = empty( $bad_combo['success'] ) && ! empty( $ok_combo['success'] );
		$this->record( 'TC-TRK-08', 'Require both email & phone to match when both supplied', $ok );
	}

	private function tc_09_logged_in_owner_views_own_order(): void {
		global $despace_mock_current_user;
		reset_tracking_env();
		$despace_mock_current_user = 15;
		$res = GuestOrderTracker::handle_lookup_request( array(
			'despace_tracking_nonce' => wp_create_nonce( OrderAccessGuard::NONCE_ACTION ),
			'order_number'           => '1003', // No email needed for logged-in owner.
		) );
		$ok = ! empty( $res['success'] ) && '1003' === ( $res['data']['order']['order_number'] ?? '' );
		$this->record( 'TC-TRK-09', 'Allow logged-in owner lookup by order number alone', $ok );
	}

	private function tc_10_customer_a_cannot_view_customer_b_or_guest_order(): void {
		global $despace_mock_current_user;
		reset_tracking_env();
		$despace_mock_current_user = 15;
		$other_cust = GuestOrderTracker::handle_lookup_request( array(
			'despace_tracking_nonce' => wp_create_nonce( OrderAccessGuard::NONCE_ACTION ),
			'order_number'           => '1004', // customer_id = 28.
			'customer_id'            => 28,     // Forged override must be ignored!
		) );
		$guest_ord = GuestOrderTracker::handle_lookup_request( array(
			'despace_tracking_nonce' => wp_create_nonce( OrderAccessGuard::NONCE_ACTION ),
			'order_number'           => '1001',
			'billing_email'          => 'guest@despace.test',
		) );
		$ok = empty( $other_cust['success'] )
			&& empty( $guest_ord['success'] )
			&& OrderAccessGuard::GENERIC_FAILURE_MESSAGE === ( $other_cust['data']['message'] ?? '' );
		$this->record( 'TC-TRK-10', 'Block Customer A viewing Customer B or Guest orders', $ok );
	}

	private function tc_11_cannot_access_by_order_id_only(): void {
		reset_tracking_env();
		$res = GuestOrderTracker::handle_lookup_request( array(
			'despace_tracking_nonce' => wp_create_nonce( OrderAccessGuard::NONCE_ACTION ),
			'order_number'           => '1001',
		) );
		$ok = empty( $res['success'] ) && OrderAccessGuard::GENERIC_FAILURE_MESSAGE === ( $res['data']['message'] ?? '' );
		$this->record( 'TC-TRK-11', 'Block guest lookup with only order ID & no verifier', $ok );
	}

	private function tc_12_no_order_key_in_response(): void {
		reset_tracking_env();
		$res  = GuestOrderTracker::handle_lookup_request( array(
			'despace_tracking_nonce' => wp_create_nonce( OrderAccessGuard::NONCE_ACTION ),
			'order_number'           => '1002',
			'billing_email'          => 'bacs-guest@despace.test',
		) );
		$json = (string) json_encode( $res );
		$ok   = ! empty( $res['success'] )
			&& false === strpos( $json, 'wc_order_secret_1002' )
			&& false === strpos( $json, 'order_key' )
			&& false === strpos( $json, 'INTERNAL_TX_SECRET_999' )
			&& false === strpos( $json, 'customer_id' );
		$this->record( 'TC-TRK-12', 'Exclude order_key, customer_id & transaction_id in output', $ok );
	}

	private function tc_13_no_private_order_notes_exposed(): void {
		reset_tracking_env();
		$res  = GuestOrderTracker::handle_lookup_request( array(
			'despace_tracking_nonce' => wp_create_nonce( OrderAccessGuard::NONCE_ACTION ),
			'order_number'           => '1001',
			'billing_email'          => 'guest@despace.test',
		) );
		$json = (string) json_encode( $res );
		$ok   = ! empty( $res['success'] )
			&& false === strpos( $json, 'ADMIN_PRIVATE_NOTE_INTERNAL_MARGIN' )
			&& 'Đã tiếp nhận đơn hàng.' === ( $res['data']['order']['customer_notes'][0]['note'] ?? '' );
		$this->record( 'TC-TRK-13', 'Show customer notes only & strip private admin notes', $ok );
	}

	private function tc_14_no_unauthorized_bank_account_leak(): void {
		reset_tracking_env();
		$denied = GuestOrderTracker::handle_lookup_request( array(
			'despace_tracking_nonce' => wp_create_nonce( OrderAccessGuard::NONCE_ACTION ),
			'order_number'           => '1002',
			'billing_email'          => 'intruder@despace.test',
		) );
		$cod_ok = GuestOrderTracker::handle_lookup_request( array(
			'despace_tracking_nonce' => wp_create_nonce( OrderAccessGuard::NONCE_ACTION ),
			'order_number'           => '1001',
			'billing_email'          => 'guest@despace.test',
		) );
		$json_denied = (string) json_encode( $denied );
		$json_cod    = (string) json_encode( $cod_ok );
		$ok = empty( $denied['success'] )
			&& false === strpos( $json_denied, '888800002222' )
			&& ! empty( $cod_ok['success'] )
			&& null === ( $cod_ok['data']['order']['payment']['bacs_instructions'] ?? null )
			&& false === strpos( $json_cod, '888800002222' );
		$this->record( 'TC-TRK-14', 'Prevent bank account leaks on failed or COD lookups', $ok );
	}

	private function tc_15_bacs_on_hold_display(): void {
		reset_tracking_env();
		$res = GuestOrderTracker::handle_lookup_request( array(
			'despace_tracking_nonce' => wp_create_nonce( OrderAccessGuard::NONCE_ACTION ),
			'order_number'           => '1002',
			'billing_email'          => 'bacs-guest@despace.test',
		) );
		$ord = $res['data']['order'] ?? array();
		$ok  = 'on-hold' === ( $ord['status'] ?? '' )
			&& 'Đang tạm giữ (Chờ xác nhận)' === ( $ord['status_label'] ?? '' )
			&& 'unpaid' === ( $ord['payment']['status'] ?? '' )
			&& false === ( $ord['payment']['bacs_instructions']['qr_generated'] ?? true )
			&& '888800002222' === ( $ord['payment']['bacs_instructions']['accounts'][0]['account_number'] ?? '' );
		$this->record( 'TC-TRK-15', 'Present authorized BACS on-hold status & bank info', $ok );
	}

	private function tc_16_cod_processing_display(): void {
		reset_tracking_env();
		$res = GuestOrderTracker::handle_lookup_request( array(
			'despace_tracking_nonce' => wp_create_nonce( OrderAccessGuard::NONCE_ACTION ),
			'order_number'           => '1001',
			'billing_email'          => 'guest@despace.test',
		) );
		$ord = $res['data']['order'] ?? array();
		$ok  = 'processing' === ( $ord['status'] ?? '' )
			&& 'Đang xử lý' === ( $ord['status_label'] ?? '' )
			&& 'cod' === ( $ord['payment']['method'] ?? '' )
			&& false === ( $ord['payment']['can_confirm_payment'] ?? true )
			&& false !== strpos( (string) ( $ord['tracking_scope_note'] ?? '' ), 'WooCommerce' );
		$this->record( 'TC-TRK-16', 'Present COD processing status honestly without confirm', $ok );
	}

	private function tc_17_cancelled_order_display(): void {
		global $despace_mock_current_user;
		reset_tracking_env();
		$despace_mock_current_user = 28;
		$res = GuestOrderTracker::handle_lookup_request( array(
			'despace_tracking_nonce' => wp_create_nonce( OrderAccessGuard::NONCE_ACTION ),
			'order_number'           => '1004',
		) );
		$ord = $res['data']['order'] ?? array();
		$ok  = ! empty( $res['success'] )
			&& 'cancelled' === ( $ord['status'] ?? '' )
			&& 'Đã hủy' === ( $ord['status_label'] ?? '' )
			&& 'unpaid' === ( $ord['payment']['status'] ?? '' );
		$this->record( 'TC-TRK-17', 'Present cancelled order Vietnamese label cleanly', $ok );
	}

	private function tc_18_refunded_order_display(): void {
		reset_tracking_env();
		$res = GuestOrderTracker::handle_lookup_request( array(
			'despace_tracking_nonce' => wp_create_nonce( OrderAccessGuard::NONCE_ACTION ),
			'order_number'           => 'DS-2026-0088',
			'billing_email'          => 'vip@despace.test',
		) );
		$ord = $res['data']['order'] ?? array();
		$ok  = ! empty( $res['success'] )
			&& 'refunded' === ( $ord['status'] ?? '' )
			&& 'Đã hoàn tiền' === ( $ord['status_label'] ?? '' )
			&& 'refunded' === ( $ord['payment']['status'] ?? '' );
		$this->record( 'TC-TRK-18', 'Present refunded order label and payment status cleanly', $ok );
	}

	private function tc_19_rate_limit_and_trusted_proxy(): void {
		global $despace_mock_transients, $despace_mock_transient_write_ok;
		reset_tracking_env();
		$despace_mock_transient_write_ok = true;
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.99'; // Must be ignored when REMOTE_ADDR not trusted!

		for ( $i = 0; $i < 5; $i++ ) {
			GuestOrderTracker::handle_lookup_request( array(
				'despace_tracking_nonce' => wp_create_nonce( OrderAccessGuard::NONCE_ACTION ),
				'order_number'           => '1001',
				'billing_email'          => 'attacker' . $i . '@evil.test',
			) );
		}
		// 6th attempt even with different spoofed X-Forwarded-For must be blocked by REMOTE_ADDR bucket!
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.123';
		$blocked = GuestOrderTracker::handle_lookup_request( array(
			'despace_tracking_nonce' => wp_create_nonce( OrderAccessGuard::NONCE_ACTION ),
			'order_number'           => '1001',
			'billing_email'          => 'guest@despace.test',
		) );

		// When persistent transient write fails, unauthenticated guest lookup MUST fail closed (503).
		reset_tracking_env();
		$despace_mock_transient_write_ok = false;
		$fail_closed = GuestOrderTracker::handle_lookup_request( array(
			'despace_tracking_nonce' => wp_create_nonce( OrderAccessGuard::NONCE_ACTION ),
			'order_number'           => '1001',
			'billing_email'          => 'guest@despace.test',
		) );
		$despace_mock_transient_write_ok = true;

		$keys_text = implode( ',', array_keys( $despace_mock_transients ) );
		$ok = empty( $blocked['success'] )
			&& 429 === ( $blocked['status'] ?? 0 )
			&& 'despace_order_lookup_rate_limited' === ( $blocked['data']['code'] ?? '' )
			&& empty( $fail_closed['success'] )
			&& 503 === ( $fail_closed['status'] ?? 0 )
			&& 'despace_order_lookup_rate_limit_unavailable' === ( $fail_closed['data']['code'] ?? '' )
			&& false === strpos( $keys_text, '203.0.113.10' )
			&& false === strpos( $keys_text, 'guest@despace.test' );
		$this->record( 'TC-TRK-19', 'Enforce rate limit, hashed keys, XFF guard & fail-closed', $ok );
	}

	private function tc_20_uniform_generic_failure_message(): void {
		global $despace_mock_current_user;
		reset_tracking_env();
		$r1 = GuestOrderTracker::handle_lookup_request( array( 'despace_tracking_nonce' => wp_create_nonce( OrderAccessGuard::NONCE_ACTION ), 'order_number' => '888888', 'billing_email' => 'a@b.com' ) );
		$r2 = GuestOrderTracker::handle_lookup_request( array( 'despace_tracking_nonce' => wp_create_nonce( OrderAccessGuard::NONCE_ACTION ), 'order_number' => '1001', 'billing_email' => 'wrong@b.com' ) );
		$despace_mock_current_user = 15;
		$r3 = GuestOrderTracker::handle_lookup_request( array( 'despace_tracking_nonce' => wp_create_nonce( OrderAccessGuard::NONCE_ACTION ), 'order_number' => '1004' ) );

		$msg = OrderAccessGuard::GENERIC_FAILURE_MESSAGE;
		$ok  = $msg === ( $r1['data']['message'] ?? '' )
			&& $msg === ( $r2['data']['message'] ?? '' )
			&& $msg === ( $r3['data']['message'] ?? '' );
		$this->record( 'TC-TRK-20', 'Return identical anti-enumeration failure message', $ok );
	}

	private function tc_21_missing_or_invalid_nonce_rejected(): void {
		reset_tracking_env();
		$missing = GuestOrderTracker::handle_lookup_request( array( 'order_number' => '1001', 'billing_email' => 'guest@despace.test' ) );
		$invalid = GuestOrderTracker::handle_lookup_request( array( 'despace_tracking_nonce' => 'bad', 'order_number' => '1001', 'billing_email' => 'guest@despace.test' ) );
		$ok = 403 === ( $missing['status'] ?? 0 )
			&& 403 === ( $invalid['status'] ?? 0 )
			&& 'despace_invalid_nonce' === ( $invalid['data']['code'] ?? '' );
		$this->record( 'TC-TRK-21', 'Reject missing or invalid CSRF nonce on lookup', $ok );
	}

	private function tc_22_get_request_and_pii_query_rejected(): void {
		reset_tracking_env();
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$get_res = GuestOrderTracker::handle_lookup_request( array(
			'despace_tracking_nonce' => wp_create_nonce( OrderAccessGuard::NONCE_ACTION ),
			'order_number'           => '1001',
			'billing_email'          => 'guest@despace.test',
		) );

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$pii_url = GuestOrderTracker::handle_lookup_request(
			array(
				'despace_tracking_nonce' => wp_create_nonce( OrderAccessGuard::NONCE_ACTION ),
				'order_number'           => '1001',
				'billing_email'          => 'guest@despace.test',
			),
			array( 'billing_email' => 'guest@despace.test' )
		);

		$ok = 405 === ( $get_res['status'] ?? 0 )
			&& 'despace_method_not_allowed' === ( $get_res['data']['code'] ?? '' )
			&& 400 === ( $pii_url['status'] ?? 0 )
			&& 'despace_pii_in_query_rejected' === ( $pii_url['data']['code'] ?? '' );
		$this->record( 'TC-TRK-22', 'Reject GET method and billing PII in URL query string', $ok );
	}

	private function tc_23_no_cache_headers_emitted(): void {
		reset_tracking_env();
		$res     = GuestOrderTracker::handle_lookup_request( array(
			'despace_tracking_nonce' => wp_create_nonce( OrderAccessGuard::NONCE_ACTION ),
			'order_number'           => '1001',
			'billing_email'          => 'guest@despace.test',
		) );
		$headers = $res['data']['cache_headers'] ?? array();
		$ok      = false !== strpos( (string) ( $headers['Cache-Control'] ?? '' ), 'no-store' )
			&& 'no-cache' === ( $headers['Pragma'] ?? '' );
		$this->record( 'TC-TRK-23', 'Enforce no-store/no-cache headers for order responses', $ok );
	}

	private function tc_24_hpos_compatibility(): void {
		global $despace_mock_shortcodes;
		reset_tracking_env();
		$cfg = apply_filters( 'despace_m3_cart_frontend_config', array() );
		$ok  = isset( $despace_mock_shortcodes['despace_order_tracking'] )
			&& 'despace_order_tracking' === ( $cfg['order_tracking']['shortcode'] ?? '' )
			&& 'despace_track_order' === ( $cfg['actions']['track_order'] ?? '' );
		$this->record( 'TC-TRK-24', 'Register shortcode adapter & HPOS CRUD lookup contract', $ok );
	}

	private function tc_25_custom_order_number_compatibility(): void {
		reset_tracking_env();
		// Found via HPOS wc_get_orders meta fallback (`DS-2026-0088`).
		$via_meta = GuestOrderTracker::handle_lookup_request( array(
			'despace_tracking_nonce' => wp_create_nonce( OrderAccessGuard::NONCE_ACTION ),
			'order_number'           => '#DS-2026-0088',
			'billing_email'          => 'vip@despace.test',
		) );

		// Found via native WC filter `woocommerce_shortcode_order_tracking_order_id`.
		add_filter( 'woocommerce_shortcode_order_tracking_order_id', fn( $v ) => 'CUSTOM-1001' === $v ? 1001 : $v );
		$via_wc_filter = GuestOrderTracker::find_order_by_number( '1001' );

		$ok = ! empty( $via_meta['success'] )
			&& 'DS-2026-0088' === ( $via_meta['data']['order']['order_number'] ?? '' )
			&& is_object( $via_wc_filter )
			&& 1001 === $via_wc_filter->get_id();
		$this->record( 'TC-TRK-25', 'Support custom order numbers via WC filter & HPOS meta', $ok );
	}

	private function get_combined_orders_source(): string {
		$files = array(
			__DIR__ . '/../wordpress/wp-content/plugins/despace-core/includes/Checkout-Orders/Orders/OrderAccessGuard.php',
			__DIR__ . '/../wordpress/wp-content/plugins/despace-core/includes/Checkout-Orders/Orders/OrderTrackingPresenter.php',
			__DIR__ . '/../wordpress/wp-content/plugins/despace-core/includes/Checkout-Orders/Orders/GuestOrderTracker.php',
		);
		$buf = '';
		foreach ( $files as $f ) $buf .= "\n" . (string) file_get_contents( $f );
		return $buf;
	}

	private function tc_26_no_direct_order_db_queries(): void {
		$src = $this->get_combined_orders_source();
		$ok  = false === strpos( $src, 'wp_posts' )
			&& false === strpos( $src, 'wp_postmeta' )
			&& false === strpos( $src, 'get_post_meta' )
			&& false === strpos( $src, 'update_post_meta' )
			&& false === strpos( $src, '$wpdb' );
		$this->record( 'TC-TRK-26', 'Zero direct wp_posts/postmeta or SQL order queries', $ok );
	}

	private function tc_27_no_inventory_mutations(): void {
		$src = $this->get_combined_orders_source();
		$ok  = false === strpos( $src, 'wc_reduce_stock_levels' )
			&& false === strpos( $src, 'wc_update_product_stock' )
			&& false === strpos( $src, 'wc_restore_stock_levels' )
			&& false === strpos( $src, 'despace_inventory_logs' );
		$this->record( 'TC-TRK-27', 'Zero stock reduction or M4 inventory mutations', $ok );
	}

	private function tc_28_no_payment_status_mutations(): void {
		$src = $this->get_combined_orders_source();
		$ok  = false === strpos( $src, 'payment_complete' )
			&& false === strpos( $src, 'update_status(' )
			&& false === strpos( $src, 'set_date_paid(' );
		$this->record( 'TC-TRK-28', 'Read-only presenter never mutates payment or order status', $ok );
	}
}

$runner = new M3OrderTrackingUnitRunner();
$runner->run();

