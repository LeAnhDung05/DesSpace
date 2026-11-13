<?php
/**
 * DeSpace M3 Phase 4 — Transactional Email Customizer Unit Test Suite (Issue #33).
 *
 * Tests all 15 required Email checks (`TC-EML-01`..`TC-EML-15`): New Order, Processing,
 * Completed, BACS On-hold, COD, Customer New Account, Reset Password, disabled email
 * respect, zero duplicate mail sends, zero bank details in COD emails, native BACS instructions,
 * normalized 2-tier VN addresses, legacy order fallback preserving address_2, privacy/PII exclusion,
 * and strict WooCommerce trigger reliance.
 *
 * Run via CLI: php tests/test-m3-email-unit.php
 *
 * @package DeSpace\Tests
 */

define( 'ABSPATH', __DIR__ . '/../' );

$despace_mock_actions = array();
$despace_mock_filters = array();

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
function do_action( string $tag, ...$args ): void {
	global $despace_mock_actions;
	$buckets = $despace_mock_actions[ $tag ] ?? array();
	usort( $buckets, fn( $a, $b ) => $a['priority'] <=> $b['priority'] );
	foreach ( $buckets as $hook ) call_user_func_array( $hook['callback'], $args );
}
function sanitize_text_field( $str ): string { return trim( strip_tags( (string) $str ) ); }
function sanitize_key( $key ): string { return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $key ) ); }
function esc_html( string $text ): string { return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' ); }
function __( string $text, string $domain = 'default' ): string { return $text; }
function add_shortcode( string $tag, callable $cb ): void {}
function admin_url( string $path = '' ): string { return 'https://despace.test/wp-admin/' . ltrim( $path, '/' ); }
function is_wp_error( $thing ): bool { return $thing instanceof \WP_Error; }

class Mock_Email_Instance {
	public string $id;
	public bool $enabled;
	public function __construct( string $id, bool $enabled = true ) {
		$this->id      = $id;
		$this->enabled = $enabled;
	}
	public function is_enabled(): bool { return $this->enabled; }
}

class Mock_Email_Order {
	public int $id = 3001;
	public int $customer_id = 42;
	public string $order_key = 'wc_order_EMAIL_SECRET_KEY';
	public string $status = 'processing';
	public string $payment_method = 'cod';
	public string $shipping_country = 'VN';
	public string $shipping_state = '15';
	public string $shipping_city = 'Phường Lào Cai';
	public string $shipping_address_1 = '10 Đường Hoàng Liên';
	public string $shipping_address_2 = '';
	public string $shipping_postcode = '';
	public string $billing_country = 'VN';
	public string $billing_state = '15';
	public string $billing_city = 'Phường Lào Cai';
	public string $billing_address_1 = '10 Đường Hoàng Liên';
	public string $billing_address_2 = '';
	public string $billing_postcode = '';
	public array $meta = array(
		'_shipping_despace_region_code'  => '15',
		'_shipping_despace_commune_code' => '02647',
	);

	public function get_id(): int { return $this->id; }
	public function get_order_number(): string { return (string) $this->id; }
	public function get_customer_id(): int { return $this->customer_id; }
	public function get_order_key(): string { return $this->order_key; }
	public function get_status(): string { return $this->status; }
	public function get_payment_method(): string { return $this->payment_method; }
	public function get_shipping_country(): string { return $this->shipping_country; }
	public function get_shipping_state(): string { return $this->shipping_state; }
	public function get_shipping_city(): string { return $this->shipping_city; }
	public function get_shipping_address_1(): string { return $this->shipping_address_1; }
	public function get_shipping_address_2(): string { return $this->shipping_address_2; }
	public function get_shipping_postcode(): string { return $this->shipping_postcode; }
	public function get_billing_country(): string { return $this->billing_country; }
	public function get_billing_state(): string { return $this->billing_state; }
	public function get_billing_city(): string { return $this->billing_city; }
	public function get_billing_address_1(): string { return $this->billing_address_1; }
	public function get_billing_address_2(): string { return $this->billing_address_2; }
	public function get_billing_postcode(): string { return $this->billing_postcode; }
	public function get_meta( string $k, bool $single = true ) { return $this->meta[ $k ] ?? ''; }
	public function get_address( string $type = 'billing' ): array {
		$p = 'shipping' === $type ? 'shipping_' : 'billing_';
		return array(
			'address_1' => $this->{$p . 'address_1'},
			'address_2' => $this->{$p . 'address_2'},
			'city'      => $this->{$p . 'city'},
			'state'     => $this->{$p . 'state'},
			'postcode'  => $this->{$p . 'postcode'},
			'country'   => $this->{$p . 'country'},
		);
	}
	public function get_formatted_billing_address(): string {
		$raw = apply_filters( 'woocommerce_order_formatted_billing_address', $this->get_address( 'billing' ), $this );
		return implode( '<br/>', array_filter( array( $raw['address_1'], $raw['address_2'], $raw['city'], $raw['state'] ) ) );
	}
	public function get_formatted_shipping_address(): string {
		$raw = apply_filters( 'woocommerce_order_formatted_shipping_address', $this->get_address( 'shipping' ), $this );
		return implode( '<br/>', array_filter( array( $raw['address_1'], $raw['address_2'], $raw['city'], $raw['state'] ) ) );
	}
}

class WooCommerce {}
function WC(): WooCommerce { return new WooCommerce(); }

require_once __DIR__ . '/../wordpress/wp-content/plugins/despace-core/includes/Checkout-Orders/CheckoutOrdersModule.php';

use DeSpace\CheckoutOrders\CheckoutOrdersModule;
use DeSpace\CheckoutOrders\Email\EmailCustomizer;
use DeSpace\CheckoutOrders\Shipping\VietnamRegions;

CheckoutOrdersModule::init();

class M3EmailUnitRunner {
	private array $results = array();

	public function run(): void {
		echo "========================================================================\n";
		echo "     DeSpace M3 Phase 4 — TRANSACTIONAL EMAILS UNIT TEST SUITE          \n";
		echo "========================================================================\n\n";

		VietnamRegions::reset_dataset();

		$this->tc_01_new_order_email();
		$this->tc_02_processing_order_email();
		$this->tc_03_completed_order_email();
		$this->tc_04_on_hold_bacs_email();
		$this->tc_05_cod_email();
		$this->tc_06_customer_new_account_email();
		$this->tc_07_reset_password_email();
		$this->tc_08_disabled_email_untouched();
		$this->tc_09_no_duplicate_mailer_engine();
		$this->tc_10_cod_never_shows_bank_details();
		$this->tc_11_bacs_relies_on_native_woocommerce();
		$this->tc_12_vietnam_2tier_address_formatted();
		$this->tc_13_legacy_order_address_fallback();
		$this->tc_14_no_private_pii_leaked_in_email_meta();
		$this->tc_15_no_untriggered_custom_send();
		$this->tc_16_no_duplicate_address_in_html_plain_admin_customer();

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

	private function tc_01_new_order_email(): void {
		$order = new Mock_Email_Order();
		$email = new Mock_Email_Instance( 'new_order', true );
		$subj  = apply_filters( 'woocommerce_email_subject_new_order', 'New order #3001', $order, $email );
		$head  = apply_filters( 'woocommerce_email_heading_new_order', 'New Order', $order, $email );
		$ok    = '[DeSpace] New order #3001' === $subj && 'Đơn hàng mới tại DeSpace' === $head;
		$this->record( 'TC-EML-01', 'Customize enabled New Order subject & heading', $ok );
	}

	private function tc_02_processing_order_email(): void {
		$order = new Mock_Email_Order();
		$email = new Mock_Email_Instance( 'customer_processing_order', true );
		$subj  = apply_filters( 'woocommerce_email_subject_customer_processing_order', 'Your order #3001', $order, $email );
		$head  = apply_filters( 'woocommerce_email_heading_customer_processing_order', 'Processing', $order, $email );
		$ok    = '[DeSpace] Your order #3001' === $subj && 'Đơn hàng đang được xử lý' === $head;
		$this->record( 'TC-EML-02', 'Customize Processing Order email cleanly', $ok );
	}

	private function tc_03_completed_order_email(): void {
		$order         = new Mock_Email_Order();
		$order->status = 'completed';
		$email         = new Mock_Email_Instance( 'customer_completed_order', true );
		$subj          = apply_filters( 'woocommerce_email_subject_customer_completed_order', 'Order #3001 complete', $order, $email );
		$head          = apply_filters( 'woocommerce_email_heading_customer_completed_order', 'Complete', $order, $email );
		$ok            = '[DeSpace] Order #3001 complete' === $subj && 'Đơn hàng đã hoàn thành' === $head;
		$this->record( 'TC-EML-03', 'Customize Completed Order email cleanly', $ok );
	}

	private function tc_04_on_hold_bacs_email(): void {
		$order                 = new Mock_Email_Order();
		$order->status         = 'on-hold';
		$order->payment_method = 'bacs';
		$email                 = new Mock_Email_Instance( 'customer_on_hold_order', true );
		$head                  = apply_filters( 'woocommerce_email_heading_customer_on_hold_order', 'On-hold', $order, $email );
		$fields                = apply_filters( 'woocommerce_email_order_meta_fields', array(), false, $order );
		$ok = 'Đơn hàng đang tạm giữ chờ xác nhận' === $head
			&& isset( $fields['despace_payment_note'] )
			&& false !== strpos( (string) $fields['despace_payment_note']['value'], 'On hold' );
		$this->record( 'TC-EML-04', 'Customize On-hold BACS email status note cleanly', $ok );
	}

	private function tc_05_cod_email(): void {
		$order                 = new Mock_Email_Order();
		$order->status         = 'processing';
		$order->payment_method = 'cod';
		$fields                = apply_filters( 'woocommerce_email_order_meta_fields', array(), false, $order );
		$ok = isset( $fields['despace_payment_note'] )
			&& false !== strpos( (string) $fields['despace_payment_note']['value'], 'COD' );
		$this->record( 'TC-EML-05', 'Format COD payment method email metadata safely', $ok );
	}

	private function tc_06_customer_new_account_email(): void {
		$email = new Mock_Email_Instance( 'customer_new_account', true );
		$subj  = apply_filters( 'woocommerce_email_subject_customer_new_account', 'Your account created', array(), $email );
		$head  = apply_filters( 'woocommerce_email_heading_customer_new_account', 'Welcome', array(), $email );
		$ok    = '[DeSpace] Your account created' === $subj && 'Chào mừng bạn đến với DeSpace' === $head;
		$this->record( 'TC-EML-06', 'Customize Customer New Account email safely', $ok );
	}

	private function tc_07_reset_password_email(): void {
		$email = new Mock_Email_Instance( 'customer_reset_password', true );
		$subj  = apply_filters( 'woocommerce_email_subject_customer_reset_password', 'Password Reset', array(), $email );
		$head  = apply_filters( 'woocommerce_email_heading_customer_reset_password', 'Reset', array(), $email );
		$ok    = '[DeSpace] Password Reset' === $subj && false !== strpos( $head, 'đặt lại mật khẩu' );
		$this->record( 'TC-EML-07', 'Customize Reset Password email via native hook only', $ok );
	}

	private function tc_08_disabled_email_untouched(): void {
		$order    = new Mock_Email_Order();
		$disabled = new Mock_Email_Instance( 'customer_processing_order', false );
		$subj     = apply_filters( 'woocommerce_email_subject_customer_processing_order', 'Original Subject', $order, $disabled );
		$head     = apply_filters( 'woocommerce_email_heading_customer_processing_order', 'Original Heading', $order, $disabled );

		ob_start();
		do_action( 'woocommerce_email_customer_details', $order, false, false, $disabled );
		$out = (string) ob_get_clean();

		$ok = 'Original Subject' === $subj && 'Original Heading' === $head && '' === $out;
		$this->record( 'TC-EML-08', 'Respect disabled email settings with zero output', $ok );
	}

	private function tc_09_no_duplicate_mailer_engine(): void {
		$src = (string) file_get_contents( __DIR__ . '/../wordpress/wp-content/plugins/despace-core/includes/Checkout-Orders/Email/EmailCustomizer.php' );
		$ok  = false === strpos( $src, 'wp_mail(' )
			&& false === strpos( $src, 'PHPMailer' )
			&& false === strpos( $src, '->trigger(' )
			&& false === strpos( $src, 'send_transactional_email' );
		$this->record( 'TC-EML-09', 'Zero duplicate wp_mail or custom mailer triggers', $ok );
	}

	private function tc_10_cod_never_shows_bank_details(): void {
		$order                 = new Mock_Email_Order();
		$order->payment_method = 'cod';
		$fields                = apply_filters( 'woocommerce_email_order_meta_fields', array(), false, $order );
		$joined                = strtolower( (string) json_encode( $fields ) );
		$ok = false === strpos( $joined, 'số tài khoản' )
			&& false === strpos( $joined, 'bank' )
			&& false === strpos( $joined, 'chuyển khoản' );
		$this->record( 'TC-EML-10', 'Never inject bank account details into COD emails', $ok );
	}

	private function tc_11_bacs_relies_on_native_woocommerce(): void {
		$src = (string) file_get_contents( __DIR__ . '/../wordpress/wp-content/plugins/despace-core/includes/Checkout-Orders/Email/EmailCustomizer.php' );
		$ok  = false === strpos( $src, 'vietqr' )
			&& false === strpos( $src, 'transaction_id' )
			&& false === strpos( $src, 'payment_complete' );
		$this->record( 'TC-EML-11', 'BACS relies on native WC instructions, zero QR/fake TX', $ok );
	}

	private function tc_12_vietnam_2tier_address_formatted(): void {
		$order = new Mock_Email_Order();
		$order->billing_address_1 = '99 Phố Huế';
		$order->billing_state     = '01';
		$order->billing_city      = 'Phường Hai Bà Trưng';
		$order->meta['_billing_despace_region_code']  = '01';
		$order->meta['_billing_despace_commune_code'] = '00004';

		$formatted_shipping = $order->get_formatted_shipping_address();
		$formatted_billing  = $order->get_formatted_billing_address();
		$fields             = apply_filters( 'woocommerce_email_order_meta_fields', array(), false, $order );

		$ok = false !== strpos( $formatted_shipping, '10 Đường Hoàng Liên' )
			&& false !== strpos( $formatted_shipping, 'Phường Lào Cai' )
			&& false !== strpos( $formatted_shipping, 'Tỉnh Lào Cai' )
			&& false !== strpos( $formatted_billing, '99 Phố Huế' )
			&& ! isset( $fields['despace_shipping_address_line'] );
		$this->record( 'TC-EML-12', 'Render 2-tier Vietnam address natively without duplication', $ok );
	}

	private function tc_13_legacy_order_address_fallback(): void {
		$legacy                     = new Mock_Email_Order();
		$legacy->meta               = array(); // No DeSpace metadata!
		$legacy->shipping_state     = 'Hồ Chí Minh';
		$legacy->shipping_city      = 'Phường 22';
		$legacy->shipping_address_1 = '12 Nguyễn Hữu Cảnh';
		$legacy->shipping_address_2 = 'Quận Bình Thạnh';

		$res = EmailCustomizer::format_order_address_for_email( $legacy, 'shipping' );
		$ok  = true === $res['is_legacy']
			&& false !== strpos( $res['formatted'], 'Quận Bình Thạnh' )
			&& false !== strpos( $res['formatted'], 'Hồ Chí Minh' );
		$this->record( 'TC-EML-13', 'Fallback gracefully & keep address_2 for legacy orders', $ok );
	}

	private function tc_14_no_private_pii_leaked_in_email_meta(): void {
		$order  = new Mock_Email_Order();
		$fields = apply_filters( 'woocommerce_email_order_meta_fields', array(), false, $order );
		$json   = (string) json_encode( $fields );
		$ok     = false === strpos( $json, 'wc_order_EMAIL_SECRET_KEY' )
			&& false === strpos( $json, 'order_key' )
			&& false === strpos( $json, 'customer_id' );
		$this->record( 'TC-EML-14', 'Exclude internal order_key and customer_id from email meta', $ok );
	}

	private function tc_15_no_untriggered_custom_send(): void {
		$footer = apply_filters( 'woocommerce_email_footer_text', '' );
		$ok     = false !== strpos( $footer, 'DeSpace' ) && true === EmailCustomizer::are_hooks_registered();
		$this->record( 'TC-EML-15', 'Customize footer via filter without untriggered sends', $ok );
	}

	private function render_simulated_wc_email( Mock_Email_Order $order, bool $sent_to_admin, bool $plain_text, Mock_Email_Instance $email ): string {
		$meta_fields = apply_filters( 'woocommerce_email_order_meta_fields', array(), $sent_to_admin, $order );
		$out         = '';
		foreach ( $meta_fields as $field ) {
			$out .= ( $field['label'] ?? '' ) . ': ' . ( $field['value'] ?? '' ) . "\n";
		}

		// Native WC_Emails::email_addresses() renders formatted billing and shipping once.
		$billing  = $order->get_formatted_billing_address();
		$shipping = $order->get_formatted_shipping_address();
		if ( $plain_text ) {
			$out .= "BILLING ADDRESS\n" . preg_replace( '#<br\s*/?>#i', "\n", $billing ) . "\n";
			$out .= "SHIPPING ADDRESS\n" . preg_replace( '#<br\s*/?>#i', "\n", $shipping ) . "\n";
		} else {
			$out .= '<address class="billing">' . $billing . '</address>';
			$out .= '<address class="shipping">' . $shipping . '</address>';
		}

		ob_start();
		do_action( 'woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email );
		$out .= (string) ob_get_clean();

		return $out;
	}

	/**
	 * TC-EML-16: Verify duplicate address prevention in a simulated WooCommerce email rendering context.
	 *
	 * NOTE: This is a unit-level simulation of WooCommerce email output pipelines (meta fields + formatted addresses).
	 * Actual end-to-end template verification with live WooCommerce email templates is performed in the integration
	 * test suite (tests/test-m3-account-integration.php) on a live WordPress + WooCommerce runtime.
	 */
	private function tc_16_no_duplicate_address_in_html_plain_admin_customer(): void {
		$order                     = new Mock_Email_Order();
		$order->billing_address_1  = '99 Phố Huế';
		$order->billing_state      = '01';
		$order->billing_city       = 'Phường Hai Bà Trưng';
		$order->meta['_billing_despace_region_code']  = '01';
		$order->meta['_billing_despace_commune_code'] = '00004';

		$cust_email  = new Mock_Email_Instance( 'customer_processing_order', true );
		$admin_email = new Mock_Email_Instance( 'new_order', true );

		$html_customer  = $this->render_simulated_wc_email( $order, false, false, $cust_email );
		$plain_customer = $this->render_simulated_wc_email( $order, false, true, $cust_email );
		$html_admin     = $this->render_simulated_wc_email( $order, true, false, $admin_email );
		$plain_admin    = $this->render_simulated_wc_email( $order, true, true, $admin_email );

		$legacy                     = new Mock_Email_Order();
		$legacy->meta               = array();
		$legacy->shipping_state     = 'Hồ Chí Minh';
		$legacy->shipping_city      = 'Phường 22';
		$legacy->shipping_address_1 = '12 Nguyễn Hữu Cảnh';
		$legacy->shipping_address_2 = 'Quận Bình Thạnh';
		$legacy_html                = $this->render_simulated_wc_email( $legacy, false, false, $cust_email );
		$legacy_plain               = $this->render_simulated_wc_email( $legacy, false, true, $cust_email );

		$ok = 1 === substr_count( $html_customer, '10 Đường Hoàng Liên' )
			&& 1 === substr_count( $html_customer, '99 Phố Huế' )
			&& 1 === substr_count( $plain_customer, '10 Đường Hoàng Liên' )
			&& 1 === substr_count( $html_admin, '10 Đường Hoàng Liên' )
			&& 1 === substr_count( $plain_admin, '10 Đường Hoàng Liên' )
			&& false !== strpos( $html_customer, 'Tỉnh Lào Cai' )
			&& 1 === substr_count( $legacy_html, 'Quận Bình Thạnh' )
			&& 1 === substr_count( $legacy_plain, 'Quận Bình Thạnh' );
		$this->record( 'TC-EML-16', 'Prevent duplicate address in simulated email render', $ok );
	}
}

$runner = new M3EmailUnitRunner();
$runner->run();

