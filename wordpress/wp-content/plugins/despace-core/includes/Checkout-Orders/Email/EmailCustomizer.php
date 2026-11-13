<?php
/**
 * WooCommerce Transactional Email Customizer for DeSpace (M3 Phase 4 / Issue #33).
 *
 * Customizes native WooCommerce transactional email subjects, headings, footer text, and
 * Vietnam 2-tier order address presentation through official WooCommerce email hooks.
 * Never builds a custom mailer engine, never sends duplicate emails, respects email
 * enabled/disabled status, never inserts bank details into COD emails, and relies on
 * native WooCommerce BACS email instructions without fake QR codes or transaction IDs.
 *
 * @package DeSpace\CheckoutOrders\Email
 */

namespace DeSpace\CheckoutOrders\Email;

use DeSpace\CheckoutOrders\Payment\PaymentMethodValidator;
use DeSpace\CheckoutOrders\Shipping\VietnamRegions;

defined( 'ABSPATH' ) || exit;

/**
 * Class EmailCustomizer
 */
class EmailCustomizer {

	/**
	 * Native WooCommerce order email IDs supported for subject/heading customization.
	 *
	 * @var array<int, string>
	 */
	public const SUPPORTED_ORDER_EMAILS = array(
		'new_order',
		'customer_processing_order',
		'customer_completed_order',
		'customer_on_hold_order',
		'cancelled_order',
		'customer_cancelled_order',
		'customer_refunded_order',
		'customer_invoice',
	);

	/**
	 * Native WooCommerce account email IDs supported for subject/heading customization.
	 *
	 * @var array<int, string>
	 */
	public const SUPPORTED_ACCOUNT_EMAILS = array(
		'customer_new_account',
		'customer_reset_password',
	);

	/**
	 * Prevents duplicate hook registration.
	 *
	 * @var bool
	 */
	private static bool $hooks_registered = false;

	/**
	 * Register WooCommerce native email formatting and address hooks.
	 */
	public static function init(): void {
		if ( self::$hooks_registered ) {
			return;
		}

		self::$hooks_registered = true;

		foreach ( self::SUPPORTED_ORDER_EMAILS as $email_id ) {
			add_filter( 'woocommerce_email_subject_' . $email_id, array( __CLASS__, 'filter_order_email_subject' ), 20, 3 );
			add_filter( 'woocommerce_email_heading_' . $email_id, array( __CLASS__, 'filter_order_email_heading' ), 20, 3 );
		}

		foreach ( self::SUPPORTED_ACCOUNT_EMAILS as $email_id ) {
			add_filter( 'woocommerce_email_subject_' . $email_id, array( __CLASS__, 'filter_account_email_subject' ), 20, 3 );
			add_filter( 'woocommerce_email_heading_' . $email_id, array( __CLASS__, 'filter_account_email_heading' ), 20, 3 );
		}

		add_filter( 'woocommerce_email_footer_text', array( __CLASS__, 'filter_email_footer_text' ), 20, 1 );
		add_filter( 'woocommerce_email_order_meta_fields', array( __CLASS__, 'filter_email_order_meta_fields' ), 20, 3 );
		add_filter( 'woocommerce_order_formatted_shipping_address', array( __CLASS__, 'filter_formatted_shipping_address' ), 20, 2 );
		add_filter( 'woocommerce_order_formatted_billing_address', array( __CLASS__, 'filter_formatted_billing_address' ), 20, 2 );
	}

	/**
	 * Check whether hooks have been registered.
	 *
	 * @return bool
	 */
	public static function are_hooks_registered(): bool {
		return self::$hooks_registered;
	}

	/**
	 * Customize native WooCommerce order email subject cleanly while respecting enabled settings.
	 *
	 * @param string      $subject Default subject.
	 * @param object|null $order   WC_Order object.
	 * @param object|null $email   WC_Email instance.
	 * @return string
	 */
	public static function filter_order_email_subject( string $subject, ?object $order = null, ?object $email = null ): string {
		if ( ! self::is_email_instance_enabled( $email ) || ! is_object( $order ) ) {
			return $subject;
		}

		$order_number = method_exists( $order, 'get_order_number' ) ? self::sanitize_text( (string) $order->get_order_number() ) : '';
		$email_id     = ( $email && isset( $email->id ) ) ? sanitize_key( (string) $email->id ) : '';

		if ( '' === $order_number || '' === $email_id ) {
			return $subject;
		}

		$prefix = '[DeSpace] ';
		if ( 0 === strpos( $subject, $prefix ) ) {
			return $subject;
		}

		return $prefix . $subject;
	}

	/**
	 * Customize native WooCommerce order email heading while preserving native triggers & disabled states.
	 *
	 * @param string      $heading Default heading.
	 * @param object|null $order   WC_Order instance.
	 * @param object|null $email   WC_Email instance.
	 * @return string
	 */
	public static function filter_order_email_heading( string $heading, ?object $order = null, ?object $email = null ): string {
		if ( ! self::is_email_instance_enabled( $email ) || ! is_object( $order ) ) {
			return $heading;
		}

		$email_id = ( $email && isset( $email->id ) ) ? sanitize_key( (string) $email->id ) : '';
		$map      = array(
			'new_order'                 => __( 'Đơn hàng mới tại DeSpace', 'despace-core' ),
			'customer_processing_order' => __( 'Đơn hàng đang được xử lý', 'despace-core' ),
			'customer_completed_order'  => __( 'Đơn hàng đã hoàn thành', 'despace-core' ),
			'customer_on_hold_order'    => __( 'Đơn hàng đang tạm giữ chờ xác nhận', 'despace-core' ),
			'cancelled_order'           => __( 'Đơn hàng đã bị hủy', 'despace-core' ),
			'customer_cancelled_order'  => __( 'Đơn hàng của bạn đã được hủy', 'despace-core' ),
			'customer_refunded_order'   => __( 'Thông báo hoàn tiền đơn hàng', 'despace-core' ),
			'customer_invoice'          => __( 'Chi tiết hóa đơn đơn hàng DeSpace', 'despace-core' ),
		);

		return $map[ $email_id ] ?? $heading;
	}

	/**
	 * Customize native WooCommerce New Account / Reset Password subject safely without custom triggers.
	 *
	 * @param string      $subject Default subject.
	 * @param mixed       $user    WP_User or user data.
	 * @param object|null $email   WC_Email instance.
	 * @return string
	 */
	public static function filter_account_email_subject( string $subject, $user = null, ?object $email = null ): string {
		if ( ! self::is_email_instance_enabled( $email ) ) {
			return $subject;
		}

		$prefix = '[DeSpace] ';
		return 0 === strpos( $subject, $prefix ) ? $subject : ( $prefix . $subject );
	}

	/**
	 * Customize native WooCommerce New Account / Reset Password heading cleanly.
	 *
	 * @param string      $heading Default heading.
	 * @param mixed       $user    WP_User or user data.
	 * @param object|null $email   WC_Email instance.
	 * @return string
	 */
	public static function filter_account_email_heading( string $heading, $user = null, ?object $email = null ): string {
		if ( ! self::is_email_instance_enabled( $email ) ) {
			return $heading;
		}

		$email_id = ( $email && isset( $email->id ) ) ? sanitize_key( (string) $email->id ) : '';
		if ( 'customer_new_account' === $email_id ) {
			return __( 'Chào mừng bạn đến với DeSpace', 'despace-core' );
		}
		if ( 'customer_reset_password' === $email_id ) {
			return __( 'Yêu cầu đặt lại mật khẩu tài khoản DeSpace', 'despace-core' );
		}

		return $heading;
	}

	/**
	 * Format brand-consistent WooCommerce email footer without leaking private system details.
	 *
	 * @param string $footer Default WooCommerce footer text.
	 * @return string
	 */
	public static function filter_email_footer_text( string $footer ): string {
		if ( '' !== trim( $footer ) ) {
			return $footer;
		}
		return __( 'DeSpace Furniture — Nền tảng nội thất 3D/AR.', 'despace-core' );
	}

	/**
	 * Append sanitized offline payment status note to WooCommerce email order meta fields.
	 *
	 * Intentionally excludes duplicate shipping/billing address lines (handled strictly once via
	 * WooCommerce's native formatted address filters) and omits bank account info on COD orders.
	 * Strictly omits bank account info on COD orders and never exposes sensitive internal keys
	 * (`order_key`, `customer_id`, or payment references). Works gracefully on legacy orders lacking DeSpace meta.
	 *
	 * @param array  $fields        Existing meta fields.
	 * @param bool   $sent_to_admin Whether email goes to admin.
	 * @param object $order         WC_Order instance.
	 * @return array
	 */
	public static function filter_email_order_meta_fields( array $fields, bool $sent_to_admin, $order ): array {
		if ( ! is_object( $order ) ) {
			return $fields;
		}

		$method = method_exists( $order, 'get_payment_method' ) ? sanitize_key( (string) $order->get_payment_method() ) : '';
		$status = method_exists( $order, 'get_status' ) ? sanitize_key( (string) $order->get_status() ) : '';

		// Payment note only clarifies COD or BACS pending status without duplicating native WC BACS instructions block.
		if ( PaymentMethodValidator::GATEWAY_COD === $method ) {
			$fields['despace_payment_note'] = array(
				'label' => __( 'Hình thức thanh toán', 'despace-core' ),
				'value' => __( 'Thanh toán khi nhận hàng (COD) — Thanh toán trực tiếp khi nhận hàng.', 'despace-core' ),
			);
		} elseif ( PaymentMethodValidator::GATEWAY_BACS === $method && 'on-hold' === $status ) {
			$fields['despace_payment_note'] = array(
				'label' => __( 'Trạng thái chuyển khoản', 'despace-core' ),
				'value' => __( 'Đơn hàng đang tạm giữ (On hold) chờ xác nhận chuyển khoản theo hướng dẫn ngân hàng của WooCommerce bên trên.', 'despace-core' ),
			);
		}

		return $fields;
	}

	/**
	 * Format native WooCommerce shipping address array via single extension point for HTML & plain-text emails.
	 *
	 * Ensures 2-tier Vietnam orders render Ward/Commune & Province/Municipality cleanly inside
	 * WooCommerce's native `email-addresses.php` block without duplicating the address elsewhere,
	 * while preserving legacy `address_2` untouched on historical orders.
	 *
	 * @param array       $address Raw WooCommerce address array.
	 * @param object|null $order   WC_Order instance.
	 * @return array
	 */
	public static function filter_formatted_shipping_address( array $address, ?object $order = null ): array {
		return self::normalize_native_order_address_array( $address, $order, 'shipping' );
	}

	/**
	 * Format native WooCommerce billing address array cleanly for 2-tier Vietnam orders while preserving legacy lines.
	 *
	 * @param array       $address Raw WooCommerce address array.
	 * @param object|null $order   WC_Order instance.
	 * @return array
	 */
	public static function filter_formatted_billing_address( array $address, ?object $order = null ): array {
		return self::normalize_native_order_address_array( $address, $order, 'billing' );
	}

	/**
	 * Normalize a native WooCommerce raw address array using `VietnamRegions::extract_order_address()`.
	 *
	 * @param array       $address Raw WooCommerce address components.
	 * @param object|null $order   WC_Order instance.
	 * @param string      $type    'shipping' or 'billing'.
	 * @return array
	 */
	private static function normalize_native_order_address_array( array $address, ?object $order, string $type ): array {
		if ( ! is_object( $order ) ) {
			return $address;
		}

		$country = strtoupper( self::sanitize_text( (string) ( $address['country'] ?? '' ) ) );
		if ( '' !== $country && 'VN' !== $country ) {
			return $address;
		}

		$extracted = VietnamRegions::extract_order_address( $order, $type );
		if ( ! empty( $extracted['is_legacy'] ) ) {
			// Preserve historical address_2 (old district), city, state, and address_1 untouched.
			return $address;
		}

		$commune_name   = self::sanitize_text( (string) ( $extracted['commune_name'] ?? '' ) );
		$region_name    = self::sanitize_text( (string) ( $extracted['region_name'] ?? '' ) );
		$address_detail = self::sanitize_text( (string) ( $extracted['address_detail'] ?? '' ) );

		if ( '' !== $address_detail ) {
			$address['address_1'] = $address_detail;
		}
		if ( '' !== $commune_name ) {
			$address['city'] = $commune_name;
		}
		if ( '' !== $region_name ) {
			$address['state'] = $region_name;
		}
		// Never inject artificial district lines into modern 2-tier orders.
		$address['address_2'] = '';

		return $address;
	}

	/**
	 * Format shipping or billing address cleanly for WooCommerce emails across new 2-tier and legacy orders.
	 *
	 * Reads via `VietnamRegions::extract_order_address()` without modifying the stored order.
	 *
	 * @param object $order WC_Order instance.
	 * @param string $type  'shipping' or 'billing'.
	 * @return array{formatted: string, is_legacy: bool, region_name: string, commune_name: string, address_detail: string, legacy_line_2: string}
	 */
	public static function format_order_address_for_email( object $order, string $type = 'shipping' ): array {
		$extracted      = VietnamRegions::extract_order_address( $order, $type );
		$address_detail = self::sanitize_text( (string) ( $extracted['address_detail'] ?? '' ) );
		$legacy_line_2  = self::sanitize_text( (string) ( $extracted['legacy_line_2'] ?? '' ) );
		$commune_name   = self::sanitize_text( (string) ( $extracted['commune_name'] ?? '' ) );
		$region_name    = self::sanitize_text( (string) ( $extracted['region_name'] ?? '' ) );
		$is_legacy      = ! empty( $extracted['is_legacy'] );

		$parts = array_filter( array(
			$address_detail,
			$is_legacy ? $legacy_line_2 : '',
			$commune_name,
			$region_name,
		) );

		return array(
			'formatted'      => implode( ', ', $parts ),
			'is_legacy'      => $is_legacy,
			'region_name'    => $region_name,
			'commune_name'   => $commune_name,
			'address_detail' => $address_detail,
			'legacy_line_2'  => $legacy_line_2,
		);
	}

	/**
	 * Check whether WooCommerce's email instance is enabled before customizing it.
	 *
	 * @param object|null $email WC_Email instance if supplied.
	 * @return bool
	 */
	private static function is_email_instance_enabled( ?object $email ): bool {
		if ( null === $email ) {
			return true;
		}
		if ( method_exists( $email, 'is_enabled' ) ) {
			return (bool) $email->is_enabled();
		}
		if ( isset( $email->enabled ) ) {
			return 'yes' === (string) $email->enabled;
		}
		return true;
	}

	private static function esc_html( string $value ): string {
		return function_exists( 'esc_html' ) ? esc_html( $value ) : htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' );
	}

	private static function sanitize_text( string $value ): string {
		return function_exists( 'sanitize_text_field' ) ? sanitize_text_field( $value ) : trim( strip_tags( $value ) );
	}
}
