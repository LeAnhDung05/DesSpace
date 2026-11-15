<?php
/**
 * Guest & Customer Order Tracking Handler for DeSpace (M3 Phase 3 / Issue #19).
 *
 * Performs HPOS-safe order lookup strictly through WooCommerce CRUD (`wc_get_order()`,
 * `wc_get_orders()`, `woocommerce_shortcode_order_tracking_order_id`), enforces `OrderAccessGuard`
 * for IDOR / rate-limit / HTTP POST+nonce / anti-enumeration protection, and delegates response
 * shaping to `OrderTrackingPresenter`. Never mutates inventory or payment state.
 *
 * @package DeSpace\CheckoutOrders\Orders
 */

namespace DeSpace\CheckoutOrders\Orders;

defined( 'ABSPATH' ) || exit;

/**
 * Class GuestOrderTracker
 */
class GuestOrderTracker {

	/**
	 * Prevents duplicate hook registration.
	 *
	 * @var bool
	 */
	private static bool $hooks_registered = false;

	/**
	 * Register WooCommerce tracking AJAX endpoint, frontend config, and `[despace_order_tracking]` adapter.
	 */
	public static function init(): void {
		if ( self::$hooks_registered ) {
			return;
		}

		self::$hooks_registered = true;

		add_filter( 'despace_m3_cart_frontend_config', array( __CLASS__, 'extend_frontend_config' ), 60, 1 );

		if ( function_exists( 'add_shortcode' ) ) {
			add_shortcode( 'despace_order_tracking', array( __CLASS__, 'render_shortcode' ) );
		}

		add_action( 'wp_ajax_despace_track_order', array( __CLASS__, 'ajax_track_order' ) );
		add_action( 'wp_ajax_nopriv_despace_track_order', array( __CLASS__, 'ajax_track_order' ) );
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
	 * Expose non-PII Order Tracking form & AJAX contract to M2 Child Theme.
	 *
	 * @param array $config Existing M3 frontend configuration.
	 * @return array
	 */
	public static function extend_frontend_config( array $config ): array {
		$config['actions'] = array_merge(
			isset( $config['actions'] ) && is_array( $config['actions'] ) ? $config['actions'] : array(),
			array(
				'track_order' => 'despace_track_order',
			)
		);

		$config['order_tracking'] = array(
			'shortcode'                => 'despace_order_tracking',
			'native_shortcode'         => 'woocommerce_order_tracking',
			'method'                   => 'POST',
			'nonce_action'             => OrderAccessGuard::NONCE_ACTION,
			'nonce'                    => function_exists( 'wp_create_nonce' ) ? wp_create_nonce( OrderAccessGuard::NONCE_ACTION ) : '',
			'nonce_field'              => 'despace_tracking_nonce',
			'fields'                   => array(
				'order_number'  => 'order_number',
				'billing_email' => 'billing_email',
				'billing_phone' => 'billing_phone',
			),
			'logged_in_requires_email' => false,
			'guest_requires_verifier'  => true,
			'rate_limit_max_attempts'  => OrderAccessGuard::DEFAULT_MAX_ATTEMPTS,
			'rate_limit_window_sec'    => OrderAccessGuard::DEFAULT_WINDOW_SECONDS,
		);

		return $config;
	}

	/**
	 * AJAX handler for POST-only Guest & Customer order tracking.
	 */
	public static function ajax_track_order(): void {
		$raw_post = self::get_unslashed_post();
		$result   = self::handle_lookup_request( $raw_post );
		self::send_json_response( $result );
	}

	/**
	 * Process POST order tracking lookup request end-to-end with strict security guards.
	 *
	 * @param array      $raw_input POST input containing `order_number` and verifier (`billing_email` / `billing_phone`).
	 * @param array|null $query_env Optional GET array override for testing URL PII rejection.
	 * @return array Unified response array `['success' => bool, 'status' => int, 'data' => array]`.
	 */
	public static function handle_lookup_request( array $raw_input, ?array $query_env = null ): array {
		unset( $raw_input['customer_id'], $raw_input['user_id'], $raw_input['order_key'] );

		$http_error = OrderAccessGuard::validate_http_and_csrf( $raw_input, $query_env );
		if ( null !== $http_error ) {
			return self::format_error_response( $http_error );
		}

		$raw_number = (string) ( $raw_input['order_number'] ?? ( $raw_input['orderid'] ?? '' ) );
		$raw_email  = (string) ( $raw_input['billing_email'] ?? ( $raw_input['order_email'] ?? '' ) );
		$raw_phone  = (string) ( $raw_input['billing_phone'] ?? ( $raw_input['order_phone'] ?? '' ) );

		$clean_number = ltrim( self::sanitize_text( $raw_number ), "# \t\n\r\0\x0B" );
		$norm_email   = '' !== trim( $raw_email ) ? OrderAccessGuard::normalize_email( $raw_email ) : '';
		$norm_phone   = '' !== trim( $raw_phone ) ? OrderAccessGuard::normalize_phone( $raw_phone ) : '';

		$credentials = array(
			'order_number'  => $clean_number,
			'billing_email' => $norm_email,
			'billing_phone' => $norm_phone,
		);

		$rate_limit_error = OrderAccessGuard::check_rate_limit( $credentials );
		if ( null !== $rate_limit_error ) {
			return self::format_error_response( $rate_limit_error );
		}

		if ( '' === $clean_number ) {
			OrderAccessGuard::record_failed_attempt( $credentials );
			return self::format_error_response( OrderAccessGuard::generic_access_error() );
		}

		// Reject unparseable guest contact input or missing guest verifier without leaking details.
		$current_user_id = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
		$malformed_email = '' !== trim( $raw_email ) && '' === $norm_email;
		$malformed_phone = '' !== trim( $raw_phone ) && '' === $norm_phone;

		if ( 0 === $current_user_id && ( $malformed_email || $malformed_phone || ( '' === $norm_email && '' === $norm_phone ) ) ) {
			OrderAccessGuard::record_failed_attempt( $credentials );
			return self::format_error_response( OrderAccessGuard::generic_access_error() );
		}

		$order = self::find_order_by_number( $clean_number );
		if ( ! $order || ! is_object( $order ) ) {
			OrderAccessGuard::record_failed_attempt( $credentials );
			return self::format_error_response( OrderAccessGuard::generic_access_error() );
		}

		$authorized = OrderAccessGuard::authorize_order_access( $order, $credentials );
		if ( is_wp_error( $authorized ) ) {
			OrderAccessGuard::record_failed_attempt( $credentials );
			return self::format_error_response( $authorized );
		}

		OrderAccessGuard::clear_rate_limit( $credentials );

		if ( function_exists( 'do_action' ) ) {
			$oid = method_exists( $order, 'get_id' ) ? (int) $order->get_id() : 0;
			if ( $oid > 0 ) {
				do_action( 'woocommerce_track_order', $oid );
			}
		}

		$cache_headers = OrderAccessGuard::emit_no_cache_headers();
		$presented     = OrderTrackingPresenter::present_order( $order );

		return array(
			'success' => true,
			'status'  => 200,
			'data'    => array(
				'action'        => 'track_order',
				'order'         => $presented,
				'cache_headers' => $cache_headers,
			),
		);
	}

	/**
	 * Locate a WooCommerce order via CRUD APIs supporting internal ID, `$order->get_order_number()`,
	 * and custom order-number extension hooks (HPOS-safe; zero direct legacy SQL tables).
	 *
	 * @param string $order_number Cleaned order number without `#`.
	 * @return object|null Matched WC_Order or null.
	 */
	public static function find_order_by_number( string $order_number ): ?object {
		$clean = ltrim( self::sanitize_text( $order_number ), "# \t\n\r\0\x0B" );
		if ( '' === $clean ) {
			return null;
		}

		if ( function_exists( 'apply_filters' ) ) {
			$hooked_order = apply_filters( 'despace_order_tracking_lookup_order', null, $clean );
			if ( is_object( $hooked_order ) && OrderAccessGuard::order_number_matches_input( $hooked_order, $clean ) ) {
				return $hooked_order;
			}
		}

		$resolved_id = $clean;
		if ( function_exists( 'apply_filters' ) ) {
			$resolved_id = apply_filters( 'woocommerce_shortcode_order_tracking_order_id', $resolved_id );
			$resolved_id = apply_filters( 'despace_order_tracking_resolve_order_id', $resolved_id, $clean );
		}

		if ( ctype_digit( (string) $resolved_id ) && (int) $resolved_id > 0 && function_exists( 'wc_get_order' ) ) {
			$order = wc_get_order( (int) $resolved_id );
			if ( is_object( $order ) && ( ! method_exists( $order, 'get_id' ) || (int) $order->get_id() > 0 ) ) {
				if ( OrderAccessGuard::order_number_matches_input( $order, $clean ) ) {
					return $order;
				}
			}
		}

		// HPOS-safe CRUD lookup fallback across filterable custom order number meta keys without assuming a single key.
		if ( function_exists( 'wc_get_orders' ) ) {
			$meta_keys = function_exists( 'apply_filters' )
				? (array) apply_filters( 'despace_order_tracking_custom_number_meta_keys', array( '_order_number', '_wc_sequential_order_numbers_formatted' ) )
				: array( '_order_number' );

			$meta_clauses = array( 'relation' => 'OR' );
			foreach ( array_unique( array_filter( array_map( 'strval', $meta_keys ) ) ) as $mkey ) {
				$meta_clauses[] = array(
					'key'     => sanitize_key( $mkey ),
					'value'   => $clean,
					'compare' => '=',
				);
			}

			if ( count( $meta_clauses ) > 1 ) {
				$candidates = (array) wc_get_orders( array(
					'limit'      => 5,
					'return'     => 'objects',
					'meta_query' => $meta_clauses,
				) );
				foreach ( $candidates as $candidate ) {
					if ( is_object( $candidate ) && OrderAccessGuard::order_number_matches_input( $candidate, $clean ) ) {
						return $candidate;
					}
				}
			}
		}

		return null;
	}

	/**
	 * Thin shortcode adapter `[despace_order_tracking]` for M2 theme placement.
	 *
	 * Delegates submission directly to `handle_lookup_request()` without duplicating engine logic.
	 *
	 * @param array|string $atts Optional shortcode attributes.
	 * @return string Escaped safe HTML container output.
	 */
	public static function render_shortcode( $atts = array() ): string {
		$is_post     = isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === strtoupper( (string) $_SERVER['REQUEST_METHOD'] );
		$is_logged   = function_exists( 'is_user_logged_in' ) && is_user_logged_in();
		$nonce_value = function_exists( 'wp_create_nonce' ) ? wp_create_nonce( OrderAccessGuard::NONCE_ACTION ) : '';

		$result_html = '';
		if ( $is_post && isset( $_POST['order_number'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified inside handle_lookup_request().
			$lookup = self::handle_lookup_request( self::get_unslashed_post() );
			if ( ! empty( $lookup['success'] ) && ! empty( $lookup['data']['order'] ) ) {
				$order       = $lookup['data']['order'];
				$result_html = '<div class="despace-order-tracking-result" data-status="' . self::esc_attr( (string) $order['status'] ) . '">'
					. '<p class="despace-tracking-summary">'
					. self::esc_html( '#' . (string) $order['order_number'] . ' — ' . (string) $order['status_label'] )
					. '</p>'
					. '<p class="despace-tracking-note">' . self::esc_html( (string) $order['tracking_scope_note'] ) . '</p>'
					. '</div>';
			} else {
				$msg         = (string) ( $lookup['data']['message'] ?? OrderAccessGuard::GENERIC_FAILURE_MESSAGE );
				$result_html = '<div class="despace-order-tracking-error" role="alert">' . self::esc_html( $msg ) . '</div>';
			}
		}

		$form = '<div class="despace-order-tracking-wrapper" data-logged-in="' . ( $is_logged ? '1' : '0' ) . '">'
			. $result_html
			. '<form method="post" class="despace-order-tracking-form">'
			. '<input type="hidden" name="despace_tracking_nonce" value="' . self::esc_attr( $nonce_value ) . '" />'
			. '<input type="text" name="order_number" required placeholder="' . self::esc_attr__( 'Mã đơn hàng' ) . '" />'
			. ( $is_logged ? '' : '<input type="email" name="billing_email" placeholder="' . self::esc_attr__( 'Email đặt hàng' ) . '" /><input type="tel" name="billing_phone" placeholder="' . self::esc_attr__( 'Số điện thoại đặt hàng' ) . '" />' )
			. '<button type="submit">' . self::esc_html__( 'Tra cứu đơn hàng' ) . '</button>'
			. '</form></div>';

		return $form;
	}

	/**
	 * Format WP_Error into API-contract JSON failure envelope.
	 *
	 * @param \WP_Error $error Validation/access error.
	 * @return array
	 */
	private static function format_error_response( \WP_Error $error ): array {
		$data   = $error->get_error_data();
		$status = is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 400;

		return array(
			'success' => false,
			'status'  => $status,
			'data'    => array(
				'code'    => $error->get_error_code(),
				'message' => $error->get_error_message(),
			),
		);
	}

	/**
	 * Emit JSON response via WordPress AJAX helpers.
	 *
	 * @param array $response Formatted response envelope.
	 */
	private static function send_json_response( array $response ): void {
		OrderAccessGuard::emit_no_cache_headers();
		$status = (int) ( $response['status'] ?? 200 );
		$data   = $response['data'] ?? array();

		if ( ! empty( $response['success'] ) ) {
			wp_send_json_success( $data, $status );
		}

		wp_send_json_error( $data, $status );
	}

	/**
	 * Read unslashed $_POST payload safely.
	 *
	 * @return array
	 */
	private static function get_unslashed_post(): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified via OrderAccessGuard::validate_http_and_csrf().
		$raw = $_POST ?? array();
		if ( function_exists( 'wp_unslash' ) ) {
			$raw = wp_unslash( $raw );
		}
		return is_array( $raw ) ? $raw : array();
	}

	private static function sanitize_text( string $value ): string {
		return function_exists( 'sanitize_text_field' ) ? sanitize_text_field( $value ) : trim( strip_tags( $value ) );
	}

	private static function esc_html( string $value ): string {
		return function_exists( 'esc_html' ) ? esc_html( $value ) : htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' );
	}

	private static function esc_attr( string $value ): string {
		return function_exists( 'esc_attr' ) ? esc_attr( $value ) : htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' );
	}

	private static function esc_html__( string $value ): string {
		return self::esc_html( __( $value, 'despace-core' ) );
	}

	private static function esc_attr__( string $value ): string {
		return self::esc_attr( __( $value, 'despace-core' ) );
	}
}
