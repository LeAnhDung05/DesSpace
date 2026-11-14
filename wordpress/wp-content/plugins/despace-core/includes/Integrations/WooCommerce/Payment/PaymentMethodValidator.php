<?php
/**
 * WooCommerce Payment Method Validator for DeSpace (M3 Phase 2B / Issue #18).
 *
 * Uses WooCommerce's native payment gateway registry (`WC()->payment_gateways()`) as the
 * single source of truth for Cash on Delivery (`cod`) and Direct Bank Transfer (`bacs`).
 * Never registers custom payment gateways, never bypasses shipping-method compatibility rules,
 * and never accepts payment status, transaction status, paid dates, or totals from frontend input.
 *
 * @package DeSpace\CheckoutOrders\Payment
 */

namespace DeSpace\CheckoutOrders\Payment;

defined( 'ABSPATH' ) || exit;

/**
 * Class PaymentMethodValidator
 */
class PaymentMethodValidator {

	public const GATEWAY_COD  = 'cod';
	public const GATEWAY_BACS = 'bacs';

	/**
	 * Supported MVP native WooCommerce gateway IDs.
	 *
	 * @var array<int, string>
	 */
	public const MVP_GATEWAYS = array(
		self::GATEWAY_COD,
		self::GATEWAY_BACS,
	);

	/**
	 * Prevents duplicate hook registration.
	 *
	 * @var bool
	 */
	private static bool $hooks_registered = false;

	/**
	 * Register WooCommerce checkout payment validation hooks and M2 frontend handoff filters.
	 */
	public static function init(): void {
		if ( self::$hooks_registered ) {
			return;
		}

		self::$hooks_registered = true;

		add_filter( 'despace_m3_cart_frontend_config', array( __CLASS__, 'extend_frontend_config' ), 50, 1 );
		add_filter( 'woocommerce_checkout_posted_data', array( __CLASS__, 'strip_tampered_payment_fields' ), 25, 1 );
		add_action( 'woocommerce_after_checkout_validation', array( __CLASS__, 'validate_checkout_payment_submission' ), 30, 2 );
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
	 * Expose non-authoritative payment gateway options for M2 Checkout UI presentation.
	 *
	 * Never leaks BACS bank account numbers into general cart/checkout config; bank details
	 * are rendered strictly post-order on authorized WooCommerce thank-you/email surfaces.
	 *
	 * @param array $config Existing M3 frontend configuration.
	 * @return array
	 */
	public static function extend_frontend_config( array $config ): array {
		$available = self::get_available_gateways();
		$gateways  = array();

		foreach ( self::MVP_GATEWAYS as $gw_id ) {
			if ( ! isset( $available[ $gw_id ] ) || ! is_object( $available[ $gw_id ] ) ) {
				continue;
			}

			$gw        = $available[ $gw_id ];
			$gateways[] = array(
				'id'          => $gw_id,
				'title'       => method_exists( $gw, 'get_title' ) ? self::sanitize_text( (string) $gw->get_title() ) : self::sanitize_text( (string) ( $gw->title ?? '' ) ),
				'description' => method_exists( $gw, 'get_description' ) ? self::sanitize_text( (string) $gw->get_description() ) : self::sanitize_text( (string) ( $gw->description ?? '' ) ),
				'available'   => true,
			);
		}

		$chosen_id = '';
		if ( function_exists( 'WC' ) && WC() && isset( WC()->session ) && is_object( WC()->session ) && method_exists( WC()->session, 'get' ) ) {
			$raw_chosen = (string) WC()->session->get( 'chosen_payment_method', '' );
			if ( '' !== $raw_chosen && isset( $available[ $raw_chosen ] ) ) {
				$chosen_id = $raw_chosen;
			}
		}

		$config['payment'] = array(
			'source_of_truth'         => 'woocommerce_native_gateways',
			'supported_gateway_ids'   => self::MVP_GATEWAYS,
			'cod_enabled'             => self::is_cod_enabled(),
			'bacs_enabled'            => self::is_bacs_enabled(),
			'available_gateways'      => $gateways,
			'selected_payment_method' => $chosen_id,
			'qr_generation_enabled'   => false,
		);

		return $config;
	}

	/**
	 * Strip any tampered payment status, transaction ID, paid date, or total fields from posted checkout data.
	 *
	 * Prevents WC_Checkout::create_order() dynamic property setter loop from applying client-supplied
	 * status, transaction_id, date_paid, or totals onto the newly created WC_Order.
	 *
	 * @param array $data Posted checkout data.
	 * @return array
	 */
	public static function strip_tampered_payment_fields( array $data ): array {
		foreach ( self::tampered_field_keys() as $forbidden_key ) {
			unset( $data[ $forbidden_key ] );
		}
		return $data;
	}

	/**
	 * Hook callback for `woocommerce_after_checkout_validation` (priority 30).
	 *
	 * Validates selected payment method strictly against WooCommerce's live gateway availability.
	 *
	 * @param array  $posted_data Posted data from WC_Checkout.
	 * @param object $errors      WP_Error bag managed by WC_Checkout.
	 */
	public static function validate_checkout_payment_submission( array $posted_data, object $errors ): void {
		self::validate_selected_payment_method( $posted_data, $errors );
	}

	/**
	 * Retrieve all registered gateways from WooCommerce payment gateway registry.
	 *
	 * @return array<string, object>
	 */
	public static function get_registered_gateways(): array {
		if ( ! function_exists( 'WC' ) || ! WC() ) {
			return array();
		}

		$wc = WC();
		$pg = method_exists( $wc, 'payment_gateways' ) ? $wc->payment_gateways() : ( $wc->payment_gateways ?? null );
		if ( ! $pg || ! is_object( $pg ) ) {
			return array();
		}

		if ( method_exists( $pg, 'payment_gateways' ) ) {
			$all = (array) $pg->payment_gateways();
			return array_filter( $all, 'is_object' );
		}

		if ( isset( $pg->payment_gateways ) && is_array( $pg->payment_gateways ) ) {
			return array_filter( $pg->payment_gateways, 'is_object' );
		}

		return array();
	}

	/**
	 * Retrieve currently available gateways from WooCommerce, double-checking gateway eligibility
	 * and COD shipping method restrictions.
	 *
	 * @return array<string, object>
	 */
	public static function get_available_gateways(): array {
		if ( ! function_exists( 'WC' ) || ! WC() ) {
			return array();
		}

		$wc = WC();
		$pg = method_exists( $wc, 'payment_gateways' ) ? $wc->payment_gateways() : ( $wc->payment_gateways ?? null );
		if ( ! $pg || ! is_object( $pg ) ) {
			return array();
		}

		$candidates = method_exists( $pg, 'get_available_payment_gateways' )
			? (array) $pg->get_available_payment_gateways()
			: self::get_registered_gateways();

		$available = array();
		foreach ( $candidates as $id => $gw ) {
			if ( ! is_object( $gw ) ) {
				continue;
			}
			$gw_id = sanitize_key( (string) ( $gw->id ?? $id ) );
			if ( '' === $gw_id ) {
				continue;
			}

			$is_enabled = 'yes' === (string) ( $gw->enabled ?? 'no' );
			if ( ! $is_enabled ) {
				continue;
			}

			if ( method_exists( $gw, 'is_available' ) && ! $gw->is_available() ) {
				continue;
			}

			if ( self::GATEWAY_COD === $gw_id && ! self::is_cod_compatible_with_shipping( $gw ) ) {
				continue;
			}

			$available[ $gw_id ] = $gw;
		}

		return $available;
	}

	/**
	 * Determine whether native COD gateway is enabled in WooCommerce settings.
	 *
	 * @return bool
	 */
	public static function is_cod_enabled(): bool {
		return self::is_registered_gateway_enabled( self::GATEWAY_COD );
	}

	/**
	 * Determine whether native BACS gateway is enabled in WooCommerce settings.
	 *
	 * @return bool
	 */
	public static function is_bacs_enabled(): bool {
		return self::is_registered_gateway_enabled( self::GATEWAY_BACS );
	}

	/**
	 * Check whether a specific gateway is currently available for checkout + current shipping choice.
	 *
	 * @param string $gateway_id Payment gateway ID.
	 * @return bool
	 */
	public static function is_gateway_available( string $gateway_id ): bool {
		$clean_id  = sanitize_key( $gateway_id );
		$available = self::get_available_gateways();
		return '' !== $clean_id && isset( $available[ $clean_id ] );
	}

	/**
	 * Validate selected checkout payment method strictly server-side against WooCommerce.
	 *
	 * Rejects disabled/unregistered gateways, COD blocked by `enable_for_methods`, and any attempt
	 * to inject client-authoritative payment status, transaction ID, paid date, or order totals.
	 *
	 * @param array       $raw_input Checkout payload.
	 * @param object|null $wc_errors Optional WP_Error bag.
	 * @param mixed       $cart      Optional WC_Cart override.
	 * @return array|\WP_Error Validated summary array on pass, or WP_Error on validation failure.
	 */
	public static function validate_selected_payment_method(
		array $raw_input,
		?object $wc_errors = null,
		$cart = null
	) {
		$errors = ( null !== $wc_errors && is_wp_error( $wc_errors ) )
			? $wc_errors
			: new \WP_Error();

		$tamper_check = PaymentStatusGuard::detect_tampered_payment_input( $raw_input );
		if ( is_wp_error( $tamper_check ) ) {
			$errors->add( $tamper_check->get_error_code(), $tamper_check->get_error_message() );
			return $errors;
		}

		if ( ! function_exists( 'WC' ) || ! WC() ) {
			$errors->add( 'despace_woocommerce_unavailable', __( 'WooCommerce is not available.', 'despace-core' ) );
			return $errors;
		}

		$cart_obj      = is_object( $cart ) ? $cart : ( WC()->cart ?? null );
		$needs_payment = true;
		if ( is_object( $cart_obj ) && method_exists( $cart_obj, 'needs_payment' ) ) {
			$raw_needs     = (bool) $cart_obj->needs_payment();
			$needs_payment = function_exists( 'apply_filters' )
				? (bool) apply_filters( 'woocommerce_cart_needs_payment', $raw_needs, $cart_obj )
				: $raw_needs;
		}

		if ( ! $needs_payment ) {
			return array(
				'valid'          => true,
				'needs_payment'  => false,
				'payment_method' => '',
				'gateway_title'  => '',
			);
		}

		$method_id = sanitize_key( (string) ( $raw_input['payment_method'] ?? '' ) );
		if ( '' === $method_id ) {
			$errors->add(
				'despace_missing_payment_method',
				__( 'Vui lòng chọn phương thức thanh toán hợp lệ.', 'despace-core' ),
				array( 'id' => 'payment_method' )
			);
			return $errors;
		}

		$available = self::get_available_gateways();
		if ( ! isset( $available[ $method_id ] ) ) {
			$registered = self::get_registered_gateways();
			$err_code   = isset( $registered[ $method_id ] ) ? 'despace_payment_method_unavailable' : 'despace_invalid_payment_method';
			$errors->add(
				$err_code,
				__( 'Phương thức thanh toán đã chọn hiện không khả dụng cho đơn hàng hoặc địa chỉ giao hàng này.', 'despace-core' ),
				array( 'id' => 'payment_method' )
			);
			return $errors;
		}

		$gw = $available[ $method_id ];

		return array(
			'valid'              => true,
			'needs_payment'      => true,
			'payment_method'     => $method_id,
			'gateway_title'      => method_exists( $gw, 'get_title' ) ? self::sanitize_text( (string) $gw->get_title() ) : self::sanitize_text( (string) ( $gw->title ?? $method_id ) ),
			'available_gateways' => array_keys( $available ),
		);
	}

	/**
	 * Verify COD `enable_for_methods` restriction against currently chosen WooCommerce shipping method(s).
	 *
	 * Mirrors WooCommerce's `WC_Gateway_COD::is_available()` check so mocked/filtered shipping changes
	 * cannot bypass admin shipping-method restrictions.
	 *
	 * @param object $cod_gateway COD gateway instance.
	 * @return bool
	 */
	private static function is_cod_compatible_with_shipping( object $cod_gateway ): bool {
		$wc = WC();
		if ( ! $wc || ! isset( $wc->cart ) || ! is_object( $wc->cart ) ) {
			return true;
		}

		$needs_shipping = method_exists( $wc->cart, 'needs_shipping' ) ? (bool) $wc->cart->needs_shipping() : true;
		if ( ! $needs_shipping ) {
			$enable_virtual = $cod_gateway->enable_for_virtual ?? true;
			return (bool) $enable_virtual && 'no' !== (string) $enable_virtual;
		}

		$allowed_methods = isset( $cod_gateway->enable_for_methods ) && is_array( $cod_gateway->enable_for_methods )
			? array_filter( array_map( 'strval', $cod_gateway->enable_for_methods ) )
			: array();

		if ( empty( $allowed_methods ) ) {
			return true;
		}

		$chosen = array();
		if ( isset( $wc->session ) && is_object( $wc->session ) && method_exists( $wc->session, 'get' ) ) {
			$chosen = (array) $wc->session->get( 'chosen_shipping_methods', array() );
		}

		if ( empty( $chosen ) && method_exists( $wc->cart, 'get_shipping_methods' ) ) {
			foreach ( (array) $wc->cart->get_shipping_methods() as $sm ) {
				if ( is_object( $sm ) && method_exists( $sm, 'get_method_id' ) ) {
					$inst     = method_exists( $sm, 'get_instance_id' ) ? (string) $sm->get_instance_id() : '';
					$chosen[] = '' !== $inst ? ( $sm->get_method_id() . ':' . $inst ) : (string) $sm->get_method_id();
				}
			}
		}

		if ( empty( $chosen ) ) {
			return true;
		}

		foreach ( $chosen as $rate_id ) {
			$rate_id_str = (string) $rate_id;
			$base_method = explode( ':', $rate_id_str )[0];
			if ( in_array( $rate_id_str, $allowed_methods, true ) || in_array( $base_method, $allowed_methods, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check whether a gateway exists in registry and has enabled = 'yes'.
	 *
	 * @param string $gateway_id Gateway identifier.
	 * @return bool
	 */
	private static function is_registered_gateway_enabled( string $gateway_id ): bool {
		$registered = self::get_registered_gateways();
		if ( ! isset( $registered[ $gateway_id ] ) || ! is_object( $registered[ $gateway_id ] ) ) {
			return false;
		}

		return 'yes' === (string) ( $registered[ $gateway_id ]->enabled ?? 'no' );
	}

	/**
	 * Keys forbidden from frontend client tampering on checkout payment payloads.
	 *
	 * @return array<int, string>
	 */
	public static function tampered_field_keys(): array {
		return array(
			'payment_status',
			'transaction_status',
			'transaction_id',
			'date_paid',
			'is_paid',
			'paid',
			'order_status',
			'status',
			'order_total',
			'total',
		);
	}

	/**
	 * Sanitize UTF-8 text cleanly.
	 *
	 * @param string $value Raw string.
	 * @return string
	 */
	private static function sanitize_text( string $value ): string {
		if ( function_exists( 'sanitize_text_field' ) ) {
			return sanitize_text_field( $value );
		}
		return trim( strip_tags( $value ) );
	}
}

