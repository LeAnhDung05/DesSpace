<?php
/**
 * DeSpace M3 Phase 4 — Customer Account, Profile, Address & Order History Unit Test Suite (Issue #33).
 *
 * Covers all 26 required Account Unit Tests (`TC-ACC-01`..`TC-ACC-26`): logged-in dashboard,
 * guest auth denial, profile read/update, email validation & duplicate prevention, password
 * change/current-password/confirmation checks, registration toggle, billing & shipping VN address
 * updates, wrong province-commune rejection, dataset fallback, invalid commune rejection, optional
 * address_2 & legacy preservation, strict order ownership (blocking Customer B & Guest order adoption),
 * HPOS CRUD compliance, native payment methods, and native logout URLs.
 *
 * Run via CLI: php tests/test-m3-account-unit.php
 *
 * @package DeSpace\Tests
 */

define( 'ABSPATH', __DIR__ . '/../' );

$despace_mock_actions      = array();
$despace_mock_filters      = array();
$despace_mock_options      = array( 'woocommerce_enable_myaccount_registration' => 'yes' );
$despace_mock_users        = array();
$despace_mock_customers    = array();
$despace_mock_orders       = array();
$despace_mock_notices      = array();
$despace_mock_current_user = 0;

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
	public function get_error_messages( string $code = '' ): array {
		if ( '' !== $code ) return $this->errors[ $code ] ?? array();
		$out = array();
		foreach ( $this->errors as $list ) foreach ( $list as $m ) $out[] = $m;
		return $out;
	}
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
function add_shortcode( string $tag, callable $cb ): void {}
function sanitize_text_field( $str ): string { return trim( strip_tags( (string) $str ) ); }
function sanitize_email( $email ): string { return trim( strip_tags( (string) $email ) ); }
function sanitize_key( $key ): string { return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $key ) ); }
function absint( $val ): int { return abs( (int) $val ); }
function wp_unslash( $value ) { return $value; }
function esc_html( string $text ): string { return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( string $text ): string { return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' ); }
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
function wc_add_notice( string $msg, string $type = 'success' ): void { global $despace_mock_notices; $despace_mock_notices[ $type ][] = $msg; }
function wc_price( float $price ): string { return number_format( $price, 0, ',', '.' ) . ' ₫'; }
function wc_get_page_permalink( string $page ): string { return 'https://despace.test/' . $page . '/'; }
function wc_get_account_endpoint_url( string $ep ): string { return 'https://despace.test/my-account/' . $ep . '/'; }
function wc_logout_url(): string { return 'https://despace.test/my-account/customer-logout/?_wpnonce=valid'; }

function get_userdata( int $uid ) { global $despace_mock_users; return $despace_mock_users[ $uid ] ?? false; }
function email_exists( string $email ) {
	global $despace_mock_users;
	foreach ( $despace_mock_users as $uid => $u ) {
		if ( 0 === strcasecmp( (string) $u->user_email, $email ) ) return (int) $uid;
	}
	return false;
}
function wp_check_password( string $plain, string $hash, int $uid = 0 ): bool {
	return 'hash_' . $plain === $hash;
}
function wp_update_user( array $data ) {
	global $despace_mock_users;
	$uid = (int) ( $data['ID'] ?? 0 );
	if ( ! isset( $despace_mock_users[ $uid ] ) ) return new WP_Error( 'invalid_user', 'No user' );
	$u = $despace_mock_users[ $uid ];
	foreach ( array( 'first_name', 'last_name', 'display_name', 'user_email' ) as $k ) {
		if ( isset( $data[ $k ] ) ) $u->$k = $data[ $k ];
	}
	if ( isset( $data['user_pass'] ) ) $u->user_pass = 'hash_' . $data['user_pass'];
	return $uid;
}
function wc_get_order( int $id ) { global $despace_mock_orders; return $despace_mock_orders[ $id ] ?? false; }
function wc_get_orders( array $args = array() ): array {
	global $despace_mock_orders;
	$out = array();
	foreach ( $despace_mock_orders as $ord ) {
		if ( isset( $args['customer_id'] ) && (int) $ord->get_customer_id() === (int) $args['customer_id'] ) {
			$out[] = $ord;
		}
	}
	return $out;
}

class WC_Customer {
	private int $id;
	public function __construct( int $id = 0 ) {
		global $despace_mock_customers;
		$this->id = $id;
		if ( ! isset( $despace_mock_customers[ $id ] ) ) {
			$despace_mock_customers[ $id ] = array(
				'first_name'         => '',
				'last_name'          => '',
				'display_name'       => '',
				'email'              => '',
				'billing_phone'      => '',
				'billing_country'    => 'VN',
				'billing_state'      => '',
				'billing_city'       => '',
				'billing_address_1'  => '',
				'billing_address_2'  => '',
				'billing_postcode'   => '',
				'shipping_country'   => 'VN',
				'shipping_state'     => '',
				'shipping_city'      => '',
				'shipping_address_1' => '',
				'shipping_address_2' => '',
				'shipping_postcode'  => '',
				'meta'               => array(),
			);
		}
	}
	private function &store(): array { global $despace_mock_customers; return $despace_mock_customers[ $this->id ]; }
	public function set_first_name( string $v ): void { $this->store()['first_name'] = $v; }
	public function set_last_name( string $v ): void { $this->store()['last_name'] = $v; }
	public function set_display_name( string $v ): void { $this->store()['display_name'] = $v; }
	public function set_email( string $v ): void { $this->store()['email'] = $v; }
	public function set_billing_phone( string $v ): void { $this->store()['billing_phone'] = $v; }
	public function get_billing_phone(): string { return $this->store()['billing_phone']; }
	public function set_billing_country( string $v ): void { $this->store()['billing_country'] = $v; }
	public function set_billing_state( string $v ): void { $this->store()['billing_state'] = $v; }
	public function set_billing_city( string $v ): void { $this->store()['billing_city'] = $v; }
	public function set_billing_address_1( string $v ): void { $this->store()['billing_address_1'] = $v; }
	public function set_billing_postcode( string $v ): void { $this->store()['billing_postcode'] = $v; }
	public function get_billing_country(): string { return $this->store()['billing_country']; }
	public function get_billing_state(): string { return $this->store()['billing_state']; }
	public function get_billing_city(): string { return $this->store()['billing_city']; }
	public function get_billing_address_1(): string { return $this->store()['billing_address_1']; }
	public function get_billing_address_2(): string { return $this->store()['billing_address_2']; }
	public function get_billing_postcode(): string { return $this->store()['billing_postcode']; }
	public function set_shipping_country( string $v ): void { $this->store()['shipping_country'] = $v; }
	public function set_shipping_state( string $v ): void { $this->store()['shipping_state'] = $v; }
	public function set_shipping_city( string $v ): void { $this->store()['shipping_city'] = $v; }
	public function set_shipping_address_1( string $v ): void { $this->store()['shipping_address_1'] = $v; }
	public function set_shipping_postcode( string $v ): void { $this->store()['shipping_postcode'] = $v; }
	public function get_shipping_country(): string { return $this->store()['shipping_country']; }
	public function get_shipping_state(): string { return $this->store()['shipping_state']; }
	public function get_shipping_city(): string { return $this->store()['shipping_city']; }
	public function get_shipping_address_1(): string { return $this->store()['shipping_address_1']; }
	public function get_shipping_address_2(): string { return $this->store()['shipping_address_2']; }
	public function get_shipping_postcode(): string { return $this->store()['shipping_postcode']; }
	public function update_meta_data( string $k, $v ): void { $this->store()['meta'][ $k ] = $v; }
	public function get_meta( string $k, bool $single = true ) { return $this->store()['meta'][ $k ] ?? ''; }
	public function save(): int { return $this->id; }
}

class Mock_Acc_Order {
	public int $id;
	public int $customer_id;
	public string $status;
	public string $billing_email;
	public string $order_key;
	public string $payment_method = 'cod';
	public float $total = 3500000.0;
	public function __construct( int $id, int $cid, string $status, string $email ) {
		$this->id            = $id;
		$this->customer_id   = $cid;
		$this->status        = $status;
		$this->billing_email = $email;
		$this->order_key     = 'wc_order_secret_' . $id;
	}
	public function get_id(): int { return $this->id; }
	public function get_order_number(): string { return (string) $this->id; }
	public function get_customer_id(): int { return $this->customer_id; }
	public function get_order_key(): string { return $this->order_key; }
	public function get_status(): string { return $this->status; }
	public function get_billing_email(): string { return $this->billing_email; }
	public function get_billing_first_name(): string { return 'Anh'; }
	public function get_billing_last_name(): string { return 'Lê'; }
	public function get_shipping_first_name(): string { return 'Anh'; }
	public function get_shipping_last_name(): string { return 'Lê'; }
	public function get_shipping_country(): string { return 'VN'; }
	public function get_shipping_state(): string { return '15'; }
	public function get_shipping_city(): string { return 'Phường Lào Cai'; }
	public function get_shipping_address_1(): string { return '10 Hoàng Liên'; }
	public function get_shipping_address_2(): string { return ''; }
	public function get_shipping_postcode(): string { return ''; }
	public function get_payment_method(): string { return $this->payment_method; }
	public function get_payment_method_title(): string { return 'COD'; }
	public function is_paid(): bool { return false; }
	public function get_shipping_method(): string { return 'Flat Rate'; }
	public function get_subtotal(): float { return $this->total; }
	public function get_discount_total(): float { return 0.0; }
	public function get_shipping_total(): float { return 0.0; }
	public function get_total(): float { return $this->total; }
	public function get_currency(): string { return 'VND'; }
	public function get_date_created(): string { return '2026-11-12 08:00:00'; }
	public function get_date_modified(): string { return '2026-11-12 08:05:00'; }
	public function get_items(): array { return array(); }
	public function get_customer_order_notes(): array { return array(); }
	public function get_meta( string $k, bool $single = true ) { return ''; }
}

class Mock_Acc_Gateways {
	public function get_available_payment_gateways(): array { return array(); }
}

class WooCommerce {
	private Mock_Acc_Gateways $gw;
	public function __construct() { $this->gw = new Mock_Acc_Gateways(); }
	public function payment_gateways(): Mock_Acc_Gateways { return $this->gw; }
}

$despace_mock_wc = new WooCommerce();
function WC(): WooCommerce { global $despace_mock_wc; return $despace_mock_wc; }

require_once __DIR__ . '/../wordpress/wp-content/plugins/despace-core/includes/Checkout-Orders/CheckoutOrdersModule.php';

use DeSpace\CheckoutOrders\CheckoutOrdersModule;
use DeSpace\CheckoutOrders\Account\AccountAddressHandler;
use DeSpace\CheckoutOrders\Account\AccountDataPresenter;
use DeSpace\CheckoutOrders\Account\AccountProfileHandler;
use DeSpace\CheckoutOrders\Shipping\VietnamRegions;

CheckoutOrdersModule::init();

function reset_account_env(): void {
	global $despace_mock_options, $despace_mock_users, $despace_mock_customers, $despace_mock_orders, $despace_mock_notices, $despace_mock_current_user;
	$despace_mock_options      = array( 'woocommerce_enable_myaccount_registration' => 'yes' );
	$despace_mock_notices      = array();
	$despace_mock_current_user = 10;
	VietnamRegions::reset_dataset();

	$despace_mock_users = array(
		10 => (object) array(
			'ID'           => 10,
			'first_name'   => 'Hùng',
			'last_name'    => 'Trần',
			'display_name' => 'Trần Hùng',
			'user_email'   => 'hung@despace.test',
			'user_pass'    => 'hash_OldPass123!',
		),
		20 => (object) array(
			'ID'           => 20,
			'first_name'   => 'Lan',
			'last_name'    => 'Phạm',
			'display_name' => 'Phạm Lan',
			'user_email'   => 'lan@despace.test',
			'user_pass'    => 'hash_OtherPass9!',
		),
	);

	$despace_mock_customers = array(
		10 => array(
			'first_name'         => 'Hùng',
			'last_name'          => 'Trần',
			'display_name'       => 'Trần Hùng',
			'email'              => 'hung@despace.test',
			'billing_phone'      => '0912345678',
			'billing_country'    => 'VN',
			'billing_state'      => 'Hồ Chí Minh',
			'billing_city'       => 'Phường Bến Nghé',
			'billing_address_1'  => '12 Lê Lợi',
			'billing_address_2'  => 'Quận 1', // Legacy address_2 must remain untouched!
			'billing_postcode'   => '700000',
			'shipping_country'   => 'VN',
			'shipping_state'     => '15',
			'shipping_city'      => 'Phường Lào Cai',
			'shipping_address_1' => '10 Hoàng Liên',
			'shipping_address_2' => 'Khu cũ',
			'shipping_postcode'  => '',
			'meta'               => array(),
		),
	);

	$despace_mock_orders = array(
		2001 => new Mock_Acc_Order( 2001, 10, 'processing', 'hung@despace.test' ),
		2002 => new Mock_Acc_Order( 2002, 10, 'completed', 'hung@despace.test' ),
		2003 => new Mock_Acc_Order( 2003, 20, 'processing', 'lan@despace.test' ),
		// Guest order sharing hung@despace.test email must NEVER be linked to Account #10!
		2004 => new Mock_Acc_Order( 2004, 0, 'pending', 'hung@despace.test' ),
	);
}

class M3AccountUnitRunner {
	private array $results = array();

	public function run(): void {
		echo "========================================================================\n";
		echo "     DeSpace M3 Phase 4 — CUSTOMER ACCOUNT & ADDRESS UNIT TESTS         \n";
		echo "========================================================================\n\n";

		$this->tc_01_logged_in_account_dashboard();
		$this->tc_02_guest_unauthenticated_blocked();
		$this->tc_03_customer_views_own_profile();
		$this->tc_04_customer_updates_name();
		$this->tc_05_customer_updates_email();
		$this->tc_06_invalid_email_rejected();
		$this->tc_07_duplicate_email_rejected();
		$this->tc_08_change_password_success();
		$this->tc_09_wrong_current_password_rejected();
		$this->tc_10_password_confirmation_mismatch_rejected();
		$this->tc_11_registration_enabled();
		$this->tc_12_registration_disabled();
		$this->tc_13_billing_address_update();
		$this->tc_14_shipping_address_update();
		$this->tc_15_valid_province_15_accepted();
		$this->tc_16_commune_wrong_province_rejected();
		$this->tc_17_dataset_fallback_free_text();
		$this->tc_18_invalid_commune_code_rejected();
		$this->tc_19_address_2_not_required();
		$this->tc_20_legacy_address_2_preserved();
		$this->tc_21_customer_views_only_own_orders();
		$this->tc_22_customer_a_cannot_view_customer_b();
		$this->tc_23_no_guest_order_adopted_by_email();
		$this->tc_24_hpos_compatibility_and_no_raw_sql();
		$this->tc_25_payment_methods_native();
		$this->tc_26_logout_and_handoff_contract();

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

	private function tc_01_logged_in_account_dashboard(): void {
		reset_account_env();
		$dash = AccountDataPresenter::get_dashboard_summary();
		$ok   = ! is_wp_error( $dash )
			&& 2 === ( $dash['stats']['total_orders'] ?? -1 )
			&& 1 === ( $dash['stats']['processing_orders'] ?? -1 )
			&& 1 === ( $dash['stats']['completed_orders'] ?? -1 )
			&& 0 === ( $dash['stats']['pending_on_hold_orders'] ?? -1 )
			&& false !== strpos( (string) ( $dash['greeting'] ?? '' ), 'Trần Hùng' );
		$this->record( 'TC-ACC-01', 'Logged-in dashboard returns real WC order stats', $ok );
	}

	private function tc_02_guest_unauthenticated_blocked(): void {
		global $despace_mock_current_user;
		reset_account_env();
		$despace_mock_current_user = 0;
		$dash = AccountDataPresenter::get_dashboard_summary();
		$prof = AccountProfileHandler::get_profile_data();
		$ok   = is_wp_error( $dash ) && 'despace_authentication_required' === $dash->get_error_code()
			&& is_wp_error( $prof ) && 'despace_authentication_required' === $prof->get_error_code();
		$this->record( 'TC-ACC-02', 'Reject guest access to account dashboard & profile', $ok );
	}

	private function tc_03_customer_views_own_profile(): void {
		reset_account_env();
		$prof  = AccountProfileHandler::get_profile_data();
		$other = AccountProfileHandler::get_profile_data( 20 );
		$ok    = ! is_wp_error( $prof )
			&& 'hung@despace.test' === ( $prof['email'] ?? '' )
			&& ! isset( $prof['user_pass'] )
			&& is_wp_error( $other )
			&& 'despace_forbidden_profile_access' === $other->get_error_code();
		$this->record( 'TC-ACC-03', 'Customer views own sanitized profile cleanly', $ok );
	}

	private function tc_04_customer_updates_name(): void {
		reset_account_env();
		$res = AccountProfileHandler::update_profile( array(
			'save-account-details-nonce' => wp_create_nonce( 'save_account_details' ),
			'user_id'                    => 20, // Forged ID must be ignored!
			'account_first_name'         => 'Minh Quân',
			'account_last_name'          => 'Trần',
			'account_display_name'       => 'Trần Minh Quân',
			'account_email'              => 'hung@despace.test',
		) );
		$ok = ! is_wp_error( $res ) && 'Minh Quân' === ( $res['first_name'] ?? '' ) && 'Trần Minh Quân' === ( $res['display_name'] ?? '' );
		$this->record( 'TC-ACC-04', 'Update first/last/display name ignoring forged user_id', $ok );
	}

	private function tc_05_customer_updates_email(): void {
		reset_account_env();
		$res = AccountProfileHandler::update_profile( array(
			'save-account-details-nonce' => wp_create_nonce( 'save_account_details' ),
			'account_first_name'         => 'Hùng',
			'account_last_name'          => 'Trần',
			'account_display_name'       => 'Trần Hùng',
			'account_email'              => 'hung.new@despace.test',
		) );
		$ok = ! is_wp_error( $res ) && 'hung.new@despace.test' === ( $res['email'] ?? '' );
		$this->record( 'TC-ACC-05', 'Update unique valid customer email cleanly', $ok );
	}

	private function tc_06_invalid_email_rejected(): void {
		reset_account_env();
		$res = AccountProfileHandler::update_profile( array(
			'save-account-details-nonce' => wp_create_nonce( 'save_account_details' ),
			'account_first_name'         => 'Hùng',
			'account_last_name'          => 'Trần',
			'account_email'              => 'bad-email-format',
		) );
		$ok = is_wp_error( $res ) && in_array( 'despace_invalid_email', $res->get_error_codes(), true );
		$this->record( 'TC-ACC-06', 'Reject invalid email address in profile update', $ok );
	}

	private function tc_07_duplicate_email_rejected(): void {
		reset_account_env();
		$res = AccountProfileHandler::update_profile( array(
			'save-account-details-nonce' => wp_create_nonce( 'save_account_details' ),
			'account_first_name'         => 'Hùng',
			'account_last_name'          => 'Trần',
			'account_email'              => 'lan@despace.test', // Belongs to user 20!
		) );
		$ok = is_wp_error( $res ) && in_array( 'despace_email_exists', $res->get_error_codes(), true );
		$this->record( 'TC-ACC-07', 'Reject duplicate email belonging to another user', $ok );
	}

	private function tc_08_change_password_success(): void {
		global $despace_mock_users;
		reset_account_env();
		$res = AccountProfileHandler::update_profile( array(
			'save-account-details-nonce' => wp_create_nonce( 'save_account_details' ),
			'account_first_name'         => 'Hùng',
			'account_last_name'          => 'Trần',
			'account_email'              => 'hung@despace.test',
			'password_current'           => 'OldPass123!',
			'password_1'                 => 'NewStrongPass9!',
			'password_2'                 => 'NewStrongPass9!',
		) );
		$ok = ! is_wp_error( $res ) && 'hash_NewStrongPass9!' === $despace_mock_users[10]->user_pass;
		$this->record( 'TC-ACC-08', 'Change password via WP User API when current matches', $ok );
	}

	private function tc_09_wrong_current_password_rejected(): void {
		reset_account_env();
		$res = AccountProfileHandler::update_profile( array(
			'save-account-details-nonce' => wp_create_nonce( 'save_account_details' ),
			'account_first_name'         => 'Hùng',
			'account_last_name'          => 'Trần',
			'account_email'              => 'hung@despace.test',
			'password_current'           => 'WrongPass',
			'password_1'                 => 'NewStrongPass9!',
			'password_2'                 => 'NewStrongPass9!',
		) );
		$ok = is_wp_error( $res ) && in_array( 'despace_invalid_current_password', $res->get_error_codes(), true );
		$this->record( 'TC-ACC-09', 'Reject password update when current password is wrong', $ok );
	}

	private function tc_10_password_confirmation_mismatch_rejected(): void {
		reset_account_env();
		$res = AccountProfileHandler::update_profile( array(
			'save-account-details-nonce' => wp_create_nonce( 'save_account_details' ),
			'account_first_name'         => 'Hùng',
			'account_last_name'          => 'Trần',
			'account_email'              => 'hung@despace.test',
			'password_current'           => 'OldPass123!',
			'password_1'                 => 'NewStrongPass9!',
			'password_2'                 => 'DifferentPass9!',
		) );
		$ok = is_wp_error( $res ) && in_array( 'despace_password_mismatch', $res->get_error_codes(), true );
		$this->record( 'TC-ACC-10', 'Reject mismatched password confirmation cleanly', $ok );
	}

	private function tc_11_registration_enabled(): void {
		global $despace_mock_options;
		reset_account_env();
		$despace_mock_options['woocommerce_enable_myaccount_registration'] = 'yes';
		$ok_reg  = AccountProfileHandler::validate_registration_request( array( 'email' => 'fresh@despace.test' ) );
		$dup_reg = AccountProfileHandler::validate_registration_request( array( 'email' => 'hung@despace.test' ) );
		$ok = true === $ok_reg && is_wp_error( $dup_reg ) && 'despace_registration_conflict' === $dup_reg->get_error_code();
		$this->record( 'TC-ACC-11', 'Support registration & handle duplicate email safely', $ok );
	}

	private function tc_12_registration_disabled(): void {
		global $despace_mock_options;
		reset_account_env();
		$despace_mock_options['woocommerce_enable_myaccount_registration'] = 'no';
		$denied = AccountProfileHandler::validate_registration_request( array( 'email' => 'fresh@despace.test' ) );
		$hooked = AccountProfileHandler::validate_native_registration_errors( new WP_Error(), 'u', 'p', 'fresh@despace.test' );
		$ok = is_wp_error( $denied )
			&& 'despace_registration_disabled' === $denied->get_error_code()
			&& $hooked->has_errors();
		$this->record( 'TC-ACC-12', 'Block registration when disabled in WC settings', $ok );
	}

	private function tc_13_billing_address_update(): void {
		reset_account_env();
		$res = AccountAddressHandler::save_customer_address( 'billing', array(
			'woocommerce-edit-address-nonce' => wp_create_nonce( 'woocommerce-edit_address' ),
			'billing_country'                => 'VN',
			'billing_state'                  => '01',
			'billing_city'                   => 'Tên giả',
			'billing_despace_commune_code'   => '00004',
			'billing_address_1'              => '28 Điện Biên Phủ',
		) );
		$ok = ! is_wp_error( $res )
			&& '01' === $res['region_code']
			&& 'Thành phố Hà Nội' === $res['region_name']
			&& '00004' === $res['commune_code']
			&& 'Phường Ba Đình' === $res['commune_name']
			&& 'verified_dataset' === $res['verification_mode'];
		$this->record( 'TC-ACC-13', 'Update billing address using official VN dataset', $ok );
	}

	private function tc_14_shipping_address_update(): void {
		reset_account_env();
		$res = AccountAddressHandler::save_customer_address( 'shipping', array(
			'woocommerce-edit-address-nonce' => wp_create_nonce( 'woocommerce-edit_address' ),
			'shipping_country'               => 'VN',
			'shipping_state'                 => '15',
			'shipping_despace_commune_code'  => '02647',
			'shipping_address_1'             => '99 Hoàng Liên',
		) );
		$ok = ! is_wp_error( $res )
			&& '15' === $res['region_code']
			&& '02647' === $res['commune_code']
			&& 'Phường Lào Cai' === $res['commune_name'];
		$this->record( 'TC-ACC-14', 'Update shipping address via WC_Customer & 2-tier VN', $ok );
	}

	private function tc_15_valid_province_15_accepted(): void {
		reset_account_env();
		$res = AccountAddressHandler::save_customer_address( 'shipping', array(
			'woocommerce-edit-address-nonce' => wp_create_nonce( 'woocommerce-edit_address' ),
			'shipping_country'               => 'VN',
			'shipping_state'                 => '15',
			'shipping_despace_commune_code'  => '02671',
			'shipping_address_1'             => '10 Đại lộ Trần Hưng Đạo',
		) );
		$ok = ! is_wp_error( $res ) && 'Tỉnh Lào Cai' === $res['region_name'] && 'Phường Cam Đường' === $res['commune_name'];
		$this->record( 'TC-ACC-15', 'Accept valid province 15 Lào Cai & commune 02671', $ok );
	}

	private function tc_16_commune_wrong_province_rejected(): void {
		reset_account_env();
		$res = AccountAddressHandler::save_customer_address( 'shipping', array(
			'woocommerce-edit-address-nonce' => wp_create_nonce( 'woocommerce-edit_address' ),
			'shipping_country'               => 'VN',
			'shipping_state'                 => '01',
			'shipping_despace_commune_code'  => '02647',
			'shipping_address_1'             => '10 Đường A',
		) );
		$ok = is_wp_error( $res ) && 'despace_commune_province_mismatch' === $res->get_error_code();
		$this->record( 'TC-ACC-16', 'Reject commune code belonging to another province', $ok );
	}

	private function tc_17_dataset_fallback_free_text(): void {
		global $despace_mock_filters;
		reset_account_env();
		$prev = $despace_mock_filters['despace_vietnam_regions_dataset_path'] ?? null;
		add_filter( 'despace_vietnam_regions_dataset_path', fn() => '' );
		VietnamRegions::reset_dataset();

		$res = AccountAddressHandler::save_customer_address( 'shipping', array(
			'woocommerce-edit-address-nonce' => wp_create_nonce( 'woocommerce-edit_address' ),
			'shipping_country'               => 'VN',
			'shipping_state'                 => 'Tỉnh Lào Cai',
			'shipping_city'                  => 'Phường Lào Cai',
			'shipping_address_1'             => '15 Đường B',
		) );

		if ( null === $prev ) unset( $despace_mock_filters['despace_vietnam_regions_dataset_path'] );
		else $despace_mock_filters['despace_vietnam_regions_dataset_path'] = $prev;
		VietnamRegions::reset_dataset();

		$ok = ! is_wp_error( $res )
			&& 'free_text_fallback' === ( $res['verification_mode'] ?? '' )
			&& '' === ( $res['region_code'] ?? 'bad' );
		$this->record( 'TC-ACC-17', 'Fallback to free_text cleanly when dataset absent', $ok );
	}

	private function tc_18_invalid_commune_code_rejected(): void {
		reset_account_env();
		$res = AccountAddressHandler::save_customer_address( 'shipping', array(
			'woocommerce-edit-address-nonce' => wp_create_nonce( 'woocommerce-edit_address' ),
			'shipping_country'               => 'VN',
			'shipping_state'                 => '15',
			'shipping_despace_commune_code'  => '99999',
			'shipping_address_1'             => '10 Hoàng Liên',
		) );
		$ok = is_wp_error( $res ) && 'despace_invalid_commune_code' === $res->get_error_code();
		$this->record( 'TC-ACC-18', 'Reject nonexistent 5-digit commune code cleanly', $ok );
	}

	private function tc_19_address_2_not_required(): void {
		reset_account_env();
		$fields = apply_filters( 'woocommerce_address_to_edit', array(
			'billing_state'     => array( 'required' => false ),
			'billing_city'      => array( 'required' => false ),
			'billing_address_1' => array( 'required' => false ),
			'billing_address_2' => array( 'required' => true ),
			'billing_postcode'  => array( 'required' => true ),
		), 'billing' );
		$ok = false === $fields['billing_address_2']['required']
			&& false === $fields['billing_postcode']['required']
			&& true === $fields['billing_state']['required']
			&& isset( $fields['billing_despace_commune_code'] );
		$this->record( 'TC-ACC-19', 'Do not require address_2 district or postcode fields', $ok );
	}

	private function tc_20_legacy_address_2_preserved(): void {
		reset_account_env();
		AccountAddressHandler::save_customer_address( 'billing', array(
			'woocommerce-edit-address-nonce' => wp_create_nonce( 'woocommerce-edit_address' ),
			'billing_country'                => 'VN',
			'billing_state'                  => '01',
			'billing_despace_commune_code'   => '00004',
			'billing_address_1'              => '28 Điện Biên Phủ',
		) );
		$read = AccountAddressHandler::get_customer_address( 'billing' );
		$ok   = 'Quận 1' === ( $read['legacy_line_2'] ?? '' );
		$this->record( 'TC-ACC-20', 'Preserve legacy address_2 when updating address_1/state', $ok );
	}

	private function tc_21_customer_views_only_own_orders(): void {
		reset_account_env();
		$hist   = AccountDataPresenter::get_customer_order_history();
		$detail = AccountDataPresenter::get_customer_order_detail( 2001 );
		$json   = (string) json_encode( $hist ) . (string) json_encode( $detail );

		$ok = ! is_wp_error( $hist )
			&& 2 === ( $hist['total_items'] ?? 0 )
			&& ! is_wp_error( $detail )
			&& '2001' === ( $detail['order_number'] ?? '' )
			&& false === strpos( $json, 'wc_order_secret_' )
			&& false === strpos( $json, 'order_key' );
		$this->record( 'TC-ACC-21', 'Customer sees only own orders without order_key leak', $ok );
	}

	private function tc_22_customer_a_cannot_view_customer_b(): void {
		reset_account_env();
		$denied = AccountDataPresenter::get_customer_order_detail( 2003 ); // Owned by user 20!
		$ok     = is_wp_error( $denied ) && 'despace_order_not_found_or_mismatch' === $denied->get_error_code();
		$this->record( 'TC-ACC-22', 'Block Customer A from viewing Customer B order', $ok );
	}

	private function tc_23_no_guest_order_adopted_by_email(): void {
		reset_account_env();
		$hist       = AccountDataPresenter::get_customer_order_history();
		$guest_try  = AccountDataPresenter::get_customer_order_detail( 2004 ); // customer_id=0, same email as user 10!
		$order_nums = array_map( fn( $o ) => $o['order_number'], $hist['items'] ?? array() );

		$ok = ! in_array( '2004', $order_nums, true )
			&& is_wp_error( $guest_try )
			&& 'despace_order_not_found_or_mismatch' === $guest_try->get_error_code();
		$this->record( 'TC-ACC-23', 'Never auto-attach Guest order to customer by email match', $ok );
	}

	private function tc_24_hpos_compatibility_and_no_raw_sql(): void {
		$files = array(
			__DIR__ . '/../wordpress/wp-content/plugins/despace-core/includes/Checkout-Orders/Account/AccountProfileHandler.php',
			__DIR__ . '/../wordpress/wp-content/plugins/despace-core/includes/Checkout-Orders/Account/AccountAddressHandler.php',
			__DIR__ . '/../wordpress/wp-content/plugins/despace-core/includes/Checkout-Orders/Account/AccountDataPresenter.php',
		);
		$src = '';
		foreach ( $files as $f ) $src .= "\n" . (string) file_get_contents( $f );

		$ok = false === strpos( $src, 'wp_posts' )
			&& false === strpos( $src, 'wp_postmeta' )
			&& false === strpos( $src, 'wp_users' )
			&& false === strpos( $src, '$wpdb' )
			&& false === strpos( $src, 'update_post_meta' );
		$this->record( 'TC-ACC-24', 'Enforce HPOS CRUD & zero direct SQL/postmeta queries', $ok );
	}

	private function tc_25_payment_methods_native(): void {
		reset_account_env();
		$pm = AccountDataPresenter::get_payment_methods_status();
		$ok = 'woocommerce_native' === ( $pm['managed_by'] ?? '' )
			&& 'payment-methods' === ( $pm['endpoint'] ?? '' )
			&& false === ( $pm['stored_cards_in_m3'] ?? true );
		$this->record( 'TC-ACC-25', 'Delegate payment methods to WC without custom storage', $ok );
	}

	private function tc_26_logout_and_handoff_contract(): void {
		reset_account_env();
		$logout = AccountDataPresenter::get_logout_url();
		$cfg    = apply_filters( 'despace_m3_cart_frontend_config', array() );
		$ok     = false !== strpos( $logout, 'customer-logout' )
			&& 'woocommerce_my_account' === ( $cfg['account']['shortcode'] ?? '' )
			&& in_array( 'orders', $cfg['account']['native_endpoints'] ?? array(), true )
			&& in_array( 'customer-logout', $cfg['account']['native_endpoints'] ?? array(), true );
		$this->record( 'TC-ACC-26', 'Expose native logout URL & [woocommerce_my_account] contract', $ok );
	}
}

$runner = new M3AccountUnitRunner();
$runner->run();

