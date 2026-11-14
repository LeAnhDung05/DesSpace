<?php
/**
 * WooCommerce BACS Bank Transfer Instructions Adapter for DeSpace (M3 Phase 2B / Issue #18).
 *
 * Reads gateway instructions and configured bank accounts exclusively from WooCommerce's
 * native BACS gateway settings (`WC_Gateway_BACS` and `woocommerce_bacs_accounts`).
 * Never hardcodes bank accounts, never generates fake QR codes or transaction IDs,
 * never discloses bank details when BACS is disabled or to unauthorized viewers.
 *
 * @package DeSpace\CheckoutOrders\Payment
 */

namespace DeSpace\CheckoutOrders\Payment;

defined( 'ABSPATH' ) || exit;

/**
 * Class BacsInstructions
 */
class BacsInstructions {

	/**
	 * Prevents duplicate hook registration.
	 *
	 * @var bool
	 */
	private static bool $hooks_registered = false;

	/**
	 * Register frontend handoff config filter for BACS checkout display.
	 */
	public static function init(): void {
		if ( self::$hooks_registered ) {
			return;
		}

		self::$hooks_registered = true;

		add_filter( 'despace_m3_cart_frontend_config', array( __CLASS__, 'extend_frontend_config' ), 55, 1 );
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
	 * Extend M2 frontend handoff metadata with BACS presentation guidance without leaking bank numbers.
	 *
	 * @param array $config Existing M3 frontend configuration.
	 * @return array
	 */
	public static function extend_frontend_config( array $config ): array {
		$summary = self::get_public_checkout_summary();

		if ( isset( $config['payment'] ) && is_array( $config['payment'] ) ) {
			$config['payment']['bacs_summary'] = $summary;
		} else {
			$config['payment'] = array( 'bacs_summary' => $summary );
		}

		return $config;
	}

	/**
	 * Return public checkout-level summary of BACS configuration without exposing full account numbers.
	 *
	 * @return array
	 */
	public static function get_public_checkout_summary(): array {
		if ( ! PaymentMethodValidator::is_bacs_enabled() ) {
			return array(
				'enabled'            => false,
				'title'              => '',
				'description'        => '',
				'instructions'       => '',
				'has_bank_accounts'  => false,
				'bank_details_scope' => 'disabled',
			);
		}

		$gateway  = self::resolve_bacs_gateway();
		$settings = self::resolve_bacs_settings( $gateway );
		$accounts = self::extract_raw_accounts( $gateway, 0 );

		return array(
			'enabled'            => true,
			'title'              => $settings['title'],
			'description'        => $settings['description'],
			'instructions'       => $settings['instructions'],
			'has_bank_accounts'  => ! empty( $accounts ),
			'bank_details_scope' => 'woocommerce_thankyou_and_customer_email',
		);
	}

	/**
	 * Read authorized BACS bank instructions and configured account details for an existing WooCommerce order.
	 *
	 * Enforces HPOS CRUD reads (`WC_Order`), checks BACS enabled state and order payment method,
	 * and prevents unauthorized guests or other customers from reading an order's BACS details.
	 *
	 * @param object $order   WC_Order instance.
	 * @param array  $context Optional authorization context (e.g. `['order_key' => '...']`).
	 * @return array|\WP_Error Sanitized instruction structure or WP_Error if disabled/unauthorized.
	 */
	public static function get_order_instructions( object $order, array $context = array() ) {
		if ( ! PaymentMethodValidator::is_bacs_enabled() ) {
			return new \WP_Error(
				'despace_bacs_disabled',
				__( 'Phương thức chuyển khoản ngân hàng hiện đang tạm ngưng.', 'despace-core' ),
				array( 'status' => 403 )
			);
		}

		$payment_method = method_exists( $order, 'get_payment_method' ) ? sanitize_key( (string) $order->get_payment_method() ) : '';
		if ( PaymentMethodValidator::GATEWAY_BACS !== $payment_method ) {
			return new \WP_Error(
				'despace_order_not_bacs',
				__( 'Đơn hàng này không thanh toán bằng chuyển khoản ngân hàng (BACS).', 'despace-core' ),
				array( 'status' => 400 )
			);
		}

		if ( ! self::is_viewer_authorized( $order, $context ) ) {
			return new \WP_Error(
				'despace_unauthorized_bacs_view',
				__( 'Không được phép truy cập hướng dẫn thanh toán của đơn hàng khác.', 'despace-core' ),
				array( 'status' => 403 )
			);
		}

		$order_id     = method_exists( $order, 'get_id' ) ? (int) $order->get_id() : 0;
		$order_number = method_exists( $order, 'get_order_number' ) ? self::sanitize_text( (string) $order->get_order_number() ) : (string) $order_id;
		$gateway      = self::resolve_bacs_gateway();
		$settings     = self::resolve_bacs_settings( $gateway );
		$accounts     = self::extract_sanitized_accounts( $gateway, $order_id );

		$is_paid = method_exists( $order, 'is_paid' ) ? (bool) $order->is_paid() : false;
		$status  = method_exists( $order, 'get_status' ) ? sanitize_key( (string) $order->get_status() ) : 'on-hold';

		return array(
			'enabled'                => true,
			'gateway_id'             => PaymentMethodValidator::GATEWAY_BACS,
			'order_id'               => $order_id,
			'order_number'           => $order_number,
			'order_status'           => $status,
			'is_paid'                => $is_paid,
			'payment_confirmed'      => $is_paid,
			'instructions'           => $settings['instructions'],
			'accounts'               => $accounts,
			'transfer_reference'     => $order_number,
			'qr_generated'           => false,
			'qr_image_url'           => '',
		);
	}

	/**
	 * Verify viewer authorization strictly against order owner, admin capability, or native WC order_key.
	 *
	 * @param object $order   WC_Order instance.
	 * @param array  $context Viewer context (e.g. order_key on WooCommerce Thank-you redirect).
	 * @return bool
	 */
	private static function is_viewer_authorized( object $order, array $context ): bool {
		if ( PaymentStatusGuard::can_confirm_manual_payment() ) {
			return true;
		}

		$current_user_id   = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
		$order_customer_id = method_exists( $order, 'get_customer_id' ) ? (int) $order->get_customer_id() : 0;

		if ( $current_user_id > 0 && $order_customer_id > 0 && $current_user_id === $order_customer_id ) {
			return true;
		}

		$provided_key = self::sanitize_text( (string) ( $context['order_key'] ?? '' ) );
		$expected_key = method_exists( $order, 'get_order_key' ) ? (string) $order->get_order_key() : '';

		if ( '' !== $provided_key && '' !== $expected_key && hash_equals( $expected_key, $provided_key ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Locate registered WC_Gateway_BACS instance from WooCommerce registry.
	 *
	 * @return object|null
	 */
	private static function resolve_bacs_gateway(): ?object {
		$registered = PaymentMethodValidator::get_registered_gateways();
		$gw         = $registered[ PaymentMethodValidator::GATEWAY_BACS ] ?? null;
		return is_object( $gw ) ? $gw : null;
	}

	/**
	 * Read title, description, and instructions from WooCommerce BACS gateway object or option settings.
	 *
	 * @param object|null $gateway WC_Gateway_BACS instance if available.
	 * @return array{title: string, description: string, instructions: string}
	 */
	private static function resolve_bacs_settings( ?object $gateway ): array {
		$option = function_exists( 'get_option' ) ? get_option( 'woocommerce_bacs_settings', array() ) : array();
		if ( ! is_array( $option ) ) {
			$option = array();
		}

		$title = $gateway && method_exists( $gateway, 'get_title' )
			? (string) $gateway->get_title()
			: (string) ( $gateway->title ?? ( $option['title'] ?? '' ) );

		$description = $gateway && method_exists( $gateway, 'get_description' )
			? (string) $gateway->get_description()
			: (string) ( $gateway->description ?? ( $option['description'] ?? '' ) );

		$instructions = $gateway && isset( $gateway->instructions )
			? (string) $gateway->instructions
			: (string) ( $option['instructions'] ?? '' );

		return array(
			'title'        => self::sanitize_text( $title ),
			'description'  => self::sanitize_text( $description ),
			'instructions' => self::sanitize_text( $instructions ),
		);
	}

	/**
	 * Read configured BACS bank accounts from gateway instance or WooCommerce option/filter.
	 *
	 * @param object|null $gateway  WC_Gateway_BACS instance.
	 * @param int         $order_id WooCommerce order ID for filter context.
	 * @return array
	 */
	private static function extract_raw_accounts( ?object $gateway, int $order_id ): array {
		$raw = array();
		if ( $gateway && isset( $gateway->account_details ) && is_array( $gateway->account_details ) ) {
			$raw = $gateway->account_details;
		} elseif ( function_exists( 'get_option' ) ) {
			$opt = get_option( 'woocommerce_bacs_accounts', array() );
			if ( is_array( $opt ) ) {
				$raw = $opt;
			}
		}

		if ( function_exists( 'apply_filters' ) ) {
			$raw = (array) apply_filters( 'woocommerce_bacs_accounts', $raw, $order_id );
		}

		return is_array( $raw ) ? $raw : array();
	}

	/**
	 * Sanitize BACS account rows read from WooCommerce configuration.
	 *
	 * @param object|null $gateway  WC_Gateway_BACS instance.
	 * @param int         $order_id WooCommerce order ID.
	 * @return array<int, array<string, string>>
	 */
	private static function extract_sanitized_accounts( ?object $gateway, int $order_id ): array {
		$accounts = array();
		foreach ( self::extract_raw_accounts( $gateway, $order_id ) as $row ) {
			if ( is_object( $row ) ) {
				$row = (array) $row;
			}
			if ( ! is_array( $row ) ) {
				continue;
			}

			$clean = array(
				'account_name'   => self::sanitize_text( (string) ( $row['account_name'] ?? '' ) ),
				'account_number' => self::sanitize_text( (string) ( $row['account_number'] ?? '' ) ),
				'bank_name'      => self::sanitize_text( (string) ( $row['bank_name'] ?? '' ) ),
				'sort_code'      => self::sanitize_text( (string) ( $row['sort_code'] ?? '' ) ),
				'iban'           => self::sanitize_text( (string) ( $row['iban'] ?? '' ) ),
				'bic'            => self::sanitize_text( (string) ( $row['bic'] ?? '' ) ),
			);

			if ( '' === $clean['account_number'] && '' === $clean['iban'] && '' === $clean['account_name'] ) {
				continue;
			}

			$accounts[] = $clean;
		}

		return $accounts;
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

