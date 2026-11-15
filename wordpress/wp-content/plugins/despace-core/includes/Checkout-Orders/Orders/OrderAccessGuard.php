<?php
/**
 * Order Tracking Access Control, IDOR Defense & Rate Limit Guard (M3 Phase 3 / Issue #19).
 *
 * Enforces strict ownership and billing verification before any WooCommerce order
 * data is revealed to Guest or Logged-in Customer lookups:
 * - Prevents IDOR / order enumeration (uniform generic error messages).
 * - Protects against brute-force via IP + hashed-identifier Transients rate limiting.
 * - Rejects HTTP GET requests, missing/invalid WP nonces, and PII in URL query parameters.
 * - Never exposes plaintext email/phone in rate-limit keys and never trusts spoofable headers
 *   unless explicitly listed as a trusted proxy.
 *
 * @package DeSpace\CheckoutOrders\Orders
 */

namespace DeSpace\CheckoutOrders\Orders;

defined( 'ABSPATH' ) || exit;

/**
 * Class OrderAccessGuard
 */
class OrderAccessGuard {

	public const NONCE_ACTION           = 'despace_order_tracking_nonce';
	public const DEFAULT_MAX_ATTEMPTS   = 5;
	public const DEFAULT_WINDOW_SECONDS = 600; // 10 minutes.

	public const GENERIC_FAILURE_MESSAGE = 'Không tìm thấy đơn hàng hoặc thông tin xác minh không khớp. Vui lòng kiểm tra lại thông tin.';
	public const RATE_LIMIT_MESSAGE      = 'Bạn đã thử tra cứu quá số lần cho phép. Vui lòng thử lại sau ít phút.';
	public const STORAGE_ERROR_MESSAGE   = 'Hệ thống bảo vệ tra cứu tạm thời gián đoạn. Vui lòng thử lại sau.';

	/**
	 * Tracks whether persistent rate-limit storage failed during the current request.
	 *
	 * @var bool
	 */
	private static bool $storage_failed = false;

	/**
	 * Last emitted no-cache headers record (for verifiable response telemetry & tests).
	 *
	 * @var array<string, string>
	 */
	private static array $last_cache_headers = array();

	/**
	 * Verify that request uses HTTP POST, has no PII in query string, and carries a valid WP CSRF nonce.
	 *
	 * Note: Nonce protects strictly against CSRF; access authorization runs separately.
	 *
	 * @param array $raw_input Request payload (`$_POST`).
	 * @param array $query_env Optional query array override (defaults to `$_GET`).
	 * @return \WP_Error|null Null if valid, WP_Error on violation.
	 */
	public static function validate_http_and_csrf( array $raw_input, ?array $query_env = null ): ?\WP_Error {
		$method = strtoupper( self::sanitize_text( (string) ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) );
		if ( 'POST' !== $method ) {
			return new \WP_Error(
				'despace_method_not_allowed',
				__( 'Yêu cầu tra cứu đơn hàng chỉ hỗ trợ phương thức POST.', 'despace-core' ),
				array( 'status' => 405 )
			);
		}

		$query_params = null !== $query_env ? $query_env : ( is_array( $_GET ?? null ) ? $_GET : array() );
		foreach ( array( 'billing_email', 'order_email', 'email', 'billing_phone', 'order_phone', 'phone', 'order_key' ) as $sensitive_param ) {
			if ( isset( $query_params[ $sensitive_param ] ) && '' !== trim( (string) $query_params[ $sensitive_param ] ) ) {
				return new \WP_Error(
					'despace_pii_in_query_rejected',
					__( 'Không được truyền thông tin cá nhân qua URL query string.', 'despace-core' ),
					array( 'status' => 400 )
				);
			}
		}

		$nonce = '';
		foreach ( array( 'despace_tracking_nonce', 'nonce', '_wpnonce', '_ajax_nonce' ) as $nonce_key ) {
			if ( isset( $raw_input[ $nonce_key ] ) && '' !== (string) $raw_input[ $nonce_key ] ) {
				$nonce = self::sanitize_text( (string) $raw_input[ $nonce_key ] );
				break;
			}
		}

		$valid_nonce = '' !== $nonce && function_exists( 'wp_verify_nonce' ) && (bool) wp_verify_nonce( $nonce, self::NONCE_ACTION );
		if ( ! $valid_nonce ) {
			return new \WP_Error(
				'despace_invalid_nonce',
				__( 'Phiên làm việc đã hết hạn hoặc mã bảo mật không hợp lệ. Vui lòng tải lại trang.', 'despace-core' ),
				array( 'status' => 403 )
			);
		}

		return null;
	}

	/**
	 * Authorize access to a resolved WC_Order for either a logged-in customer or an unauthenticated guest.
	 *
	 * Rules:
	 * - Logged-in customer (`get_current_user_id() > 0`): strict ownership match (`customer_id === current_user_id`).
	 *   Never reveals Guest orders or other customers' orders to another logged-in user.
	 * - Guest (`0 === current_user_id`): requires matching order number + valid billing email and/or phone.
	 *   If both email and phone are provided, BOTH must match the order simultaneously.
	 * - Always returns unified generic error code/message on any failure to prevent enumeration.
	 *
	 * @param object $order        WC_Order instance.
	 * @param array  $credentials  Sanitized lookup inputs: `order_number`, `billing_email`, `billing_phone`.
	 * @return true|\WP_Error True if authorized, generic WP_Error otherwise.
	 */
	public static function authorize_order_access( object $order, array $credentials ) {
		if ( ! self::order_number_matches_input( $order, (string) ( $credentials['order_number'] ?? '' ) ) ) {
			return self::generic_access_error();
		}

		$current_user_id = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
		$order_owner_id  = method_exists( $order, 'get_customer_id' ) ? (int) $order->get_customer_id() : 0;

		if ( $current_user_id > 0 ) {
			if ( $order_owner_id > 0 && $order_owner_id === $current_user_id ) {
				return true;
			}
			return self::generic_access_error();
		}

		$input_email = self::normalize_email( (string) ( $credentials['billing_email'] ?? '' ) );
		$input_phone = self::normalize_phone( (string) ( $credentials['billing_phone'] ?? '' ) );

		// Never authorize guest access by order number/ID alone or without billing contact verifier.
		if ( '' === $input_email && '' === $input_phone ) {
			return self::generic_access_error();
		}

		if ( '' !== $input_email ) {
			$order_email = method_exists( $order, 'get_billing_email' )
				? self::normalize_email( (string) $order->get_billing_email() )
				: '';
			if ( '' === $order_email || ! hash_equals( $order_email, $input_email ) ) {
				return self::generic_access_error();
			}
		}

		if ( '' !== $input_phone ) {
			$order_phone = method_exists( $order, 'get_billing_phone' )
				? self::normalize_phone( (string) $order->get_billing_phone() )
				: '';
			if ( '' === $order_phone || strlen( $input_phone ) < 7 || ! hash_equals( $order_phone, $input_phone ) ) {
				return self::generic_access_error();
			}
		}

		return true;
	}

	/**
	 * Check whether current client IP or hashed lookup identifier is rate-limited.
	 *
	 * Requires persistent multi-request storage (WP Transients or persistent object cache).
	 * Never relies on single-request PHP memory; fails closed for unauthenticated Guest lookups
	 * if persistent rate-limit storage is unavailable or broken.
	 *
	 * @param array $credentials Normalized lookup credentials.
	 * @return \WP_Error|null Null when allowed, WP_Error (429 or 503 fail-closed) when blocked.
	 */
	public static function check_rate_limit( array $credentials ): ?\WP_Error {
		$current_user_id = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
		$is_guest        = ( 0 === $current_user_id );

		if ( self::$storage_failed || ! self::has_persistent_storage() ) {
			if ( $is_guest ) {
				return self::storage_unavailable_error();
			}
			return null;
		}

		$max_attempts = self::get_max_attempts();
		foreach ( self::build_rate_limit_keys( $credentials ) as $bucket_key ) {
			$state = self::read_bucket( $bucket_key );
			if ( null === $state && $is_guest ) {
				// Probe persistent write once for new IP/identifier bucket so Guest lookup fails closed if DB/cache write fails.
				$probe = array(
					'count'      => 0,
					'expires_at' => time() + self::get_window_seconds(),
				);
				if ( ! self::write_bucket( $bucket_key, $probe, self::get_window_seconds() ) ) {
					self::$storage_failed = true;
					return self::storage_unavailable_error();
				}
				continue;
			}
			if ( is_array( $state ) && (int) ( $state['count'] ?? 0 ) >= $max_attempts ) {
				return new \WP_Error(
					'despace_order_lookup_rate_limited',
					__( self::RATE_LIMIT_MESSAGE, 'despace-core' ),
					array( 'status' => 429 )
				);
			}
		}

		return null;
	}

	/**
	 * Record a failed lookup attempt against both IP and hashed identifier buckets.
	 *
	 * @param array $credentials Normalized lookup credentials.
	 * @return array{degraded_storage: bool}
	 */
	public static function record_failed_attempt( array $credentials ): array {
		if ( ! self::has_persistent_storage() ) {
			self::$storage_failed = true;
			return array( 'degraded_storage' => true );
		}

		$window   = self::get_window_seconds();
		$degraded = false;
		$now      = time();

		foreach ( self::build_rate_limit_keys( $credentials ) as $bucket_key ) {
			$state      = self::read_bucket( $bucket_key );
			$expires_at = is_array( $state ) ? (int) ( $state['expires_at'] ?? 0 ) : 0;
			if ( ! is_array( $state ) || $expires_at <= $now ) {
				$new_state = array(
					'count'      => 1,
					'expires_at' => $now + $window,
				);
			} else {
				$new_state = array(
					'count'      => (int) ( $state['count'] ?? 0 ) + 1,
					'expires_at' => $expires_at,
				);
			}

			if ( ! self::write_bucket( $bucket_key, $new_state, max( 1, $new_state['expires_at'] - $now ) ) ) {
				self::$storage_failed = true;
				$degraded             = true;
			}
		}

		return array( 'degraded_storage' => $degraded );
	}

	/**
	 * Clear hashed-identifier rate-limit bucket on successful verification.
	 *
	 * @param array $credentials Normalized lookup credentials.
	 */
	public static function clear_rate_limit( array $credentials ): void {
		$keys = self::build_rate_limit_keys( $credentials );
		if ( isset( $keys['identifier'] ) ) {
			self::delete_bucket( $keys['identifier'] );
		}
	}

	/**
	 * Reset rate-limit failure flags and cache headers record (primarily for test isolation).
	 */
	public static function reset_fallback_rate_limits(): void {
		self::$storage_failed     = false;
		self::$last_cache_headers = array();
	}

	/**
	 * Emit strict `no-store, no-cache` headers before outputting any PII order tracking payload.
	 *
	 * @return array<string, string> Headers enforced.
	 */
	public static function emit_no_cache_headers(): array {
		$headers = array(
			'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
			'Pragma'        => 'no-cache',
			'Expires'       => '0',
		);
		self::$last_cache_headers = $headers;

		if ( function_exists( 'nocache_headers' ) && ! headers_sent() ) {
			nocache_headers();
		}

		if ( ! headers_sent() ) {
			foreach ( $headers as $name => $val ) {
				header( $name . ': ' . $val, true );
			}
		}

		return $headers;
	}

	/**
	 * Return last cache headers recorded by `emit_no_cache_headers()`.
	 *
	 * @return array<string, string>
	 */
	public static function get_last_cache_headers(): array {
		return self::$last_cache_headers;
	}

	/**
	 * Normalize billing email for exact case-insensitive comparison.
	 *
	 * @param string $email Raw email.
	 * @return string Empty string when malformed.
	 */
	public static function normalize_email( string $email ): string {
		$clean = function_exists( 'sanitize_email' )
			? sanitize_email( $email )
			: trim( strip_tags( $email ) );
		$clean = strtolower( trim( $clean ) );

		if ( '' === $clean ) {
			return '';
		}

		$is_valid = function_exists( 'is_email' ) ? ( false !== is_email( $clean ) ) : ( false !== filter_var( $clean, FILTER_VALIDATE_EMAIL ) );
		return $is_valid ? $clean : '';
	}

	/**
	 * Normalize Vietnamese / international billing phone into canonical digits for safe matching.
	 *
	 * Converts leading `0` to `84` (`0912345678` -> `84912345678`) so `+84 912.345.678`,
	 * `84912345678`, and `0912345678` compare identically after sanitization.
	 *
	 * @param string $phone Raw phone string.
	 * @return string Normalized digits-only string, or '' if empty/invalid chars.
	 */
	public static function normalize_phone( string $phone ): string {
		$trimmed = self::sanitize_text( $phone );
		if ( '' === $trimmed || 1 === preg_match( '/[A-Za-z]/', $trimmed ) ) {
			return '';
		}

		$digits = preg_replace( '/[^0-9]/', '', $trimmed );
		$digits = is_string( $digits ) ? $digits : '';
		if ( strlen( $digits ) < 7 || strlen( $digits ) > 15 ) {
			return '';
		}

		if ( 0 === strpos( $digits, '0' ) ) {
			return '84' . substr( $digits, 1 );
		}

		return $digits;
	}

	/**
	 * Resolve client IP strictly from REMOTE_ADDR unless explicitly matching configured trusted proxies.
	 *
	 * @return string
	 */
	public static function resolve_client_ip(): string {
		$remote = trim( (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) );
		if ( '' === $remote || false === filter_var( $remote, FILTER_VALIDATE_IP ) ) {
			return '0.0.0.0';
		}

		$trusted_proxies = function_exists( 'apply_filters' )
			? (array) apply_filters( 'despace_order_tracking_trusted_proxies', array() )
			: array();

		if ( ! empty( $trusted_proxies ) && in_array( $remote, $trusted_proxies, true ) ) {
			$forwarded = trim( (string) ( $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '' ) );
			if ( '' !== $forwarded ) {
				$first_ip = trim( explode( ',', $forwarded )[0] );
				if ( false !== filter_var( $first_ip, FILTER_VALIDATE_IP ) ) {
					return $first_ip;
				}
			}
		}

		return $remote;
	}

	/**
	 * Build uniform anti-enumeration failure error.
	 *
	 * @return \WP_Error
	 */
	public static function generic_access_error(): \WP_Error {
		return new \WP_Error(
			'despace_order_not_found_or_mismatch',
			__( self::GENERIC_FAILURE_MESSAGE, 'despace-core' ),
			array( 'status' => 404 )
		);
	}

	/**
	 * Confirm that the resolved order corresponds to the normalized order number input.
	 *
	 * @param object $order        WC_Order instance.
	 * @param string $order_number Normalized order number without `#`.
	 * @return bool
	 */
	public static function order_number_matches_input( object $order, string $order_number ): bool {
		$clean_input = ltrim( trim( $order_number ), '#' );
		if ( '' === $clean_input ) {
			return false;
		}

		$id_str     = method_exists( $order, 'get_id' ) ? (string) $order->get_id() : '';
		$number_str = method_exists( $order, 'get_order_number' ) ? ltrim( trim( (string) $order->get_order_number() ), '#' ) : $id_str;

		return ( '' !== $id_str && 0 === strcasecmp( $id_str, $clean_input ) )
			|| ( '' !== $number_str && 0 === strcasecmp( $number_str, $clean_input ) );
	}

	/**
	 * Construct hashed bucket keys (never containing plaintext IP, email, or phone).
	 *
	 * @param array $credentials Normalized credentials.
	 * @return array<string, string>
	 */
	private static function build_rate_limit_keys( array $credentials ): array {
		$salt   = defined( 'NONCE_SALT' ) ? (string) NONCE_SALT : ( defined( 'AUTH_KEY' ) ? (string) AUTH_KEY : __FILE__ );
		$ip     = self::resolve_client_ip();
		$num    = strtolower( ltrim( trim( (string) ( $credentials['order_number'] ?? '' ) ), '#' ) );
		$email  = self::normalize_email( (string) ( $credentials['billing_email'] ?? '' ) );
		$phone  = self::normalize_phone( (string) ( $credentials['billing_phone'] ?? '' ) );
		$uid    = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;

		return array(
			'ip'         => 'despace_ot_ip_' . substr( hash_hmac( 'sha256', 'ip|' . $ip, $salt ), 0, 32 ),
			'identifier' => 'despace_ot_id_' . substr( hash_hmac( 'sha256', 'id|' . $uid . '|' . $num . '|' . $email . '|' . $phone, $salt ), 0, 32 ),
		);
	}

	/**
	 * Configurable maximum failed lookup attempts within rate-limit window.
	 *
	 * @return int
	 */
	private static function get_max_attempts(): int {
		$val = function_exists( 'apply_filters' )
			? (int) apply_filters( 'despace_order_tracking_rate_limit_max_attempts', self::DEFAULT_MAX_ATTEMPTS )
			: self::DEFAULT_MAX_ATTEMPTS;
		return max( 1, $val );
	}

	/**
	 * Configurable rate-limit window in seconds.
	 *
	 * @return int
	 */
	private static function get_window_seconds(): int {
		$val = function_exists( 'apply_filters' )
			? (int) apply_filters( 'despace_order_tracking_rate_limit_window_seconds', self::DEFAULT_WINDOW_SECONDS )
			: self::DEFAULT_WINDOW_SECONDS;
		return max( 30, $val );
	}

	/**
	 * Build persistent storage failure error (fail-closed for unauthenticated guest tracking).
	 *
	 * @return \WP_Error
	 */
	public static function storage_unavailable_error(): \WP_Error {
		return new \WP_Error(
			'despace_order_lookup_rate_limit_unavailable',
			__( self::STORAGE_ERROR_MESSAGE, 'despace-core' ),
			array( 'status' => 503 )
		);
	}

	/**
	 * Verify whether WordPress persistent object cache or Transients API is available.
	 *
	 * @return bool
	 */
	private static function has_persistent_storage(): bool {
		if ( function_exists( 'wp_using_ext_object_cache' ) && wp_using_ext_object_cache()
			&& function_exists( 'wp_cache_get' ) && function_exists( 'wp_cache_set' )
		) {
			return true;
		}

		return function_exists( 'get_transient' ) && function_exists( 'set_transient' );
	}

	/**
	 * Read rate-limit counter strictly from persistent object cache or WP Transients.
	 *
	 * @param string $key Hashed bucket key.
	 * @return array{count: int, expires_at: int}|null Null if absent in persistent storage.
	 */
	private static function read_bucket( string $key ): ?array {
		$val = false;

		if ( function_exists( 'wp_using_ext_object_cache' ) && wp_using_ext_object_cache() && function_exists( 'wp_cache_get' ) ) {
			$val = wp_cache_get( $key, 'despace_order_tracking' );
		} elseif ( function_exists( 'get_transient' ) ) {
			$val = get_transient( $key );
		}

		if ( is_array( $val ) && isset( $val['count'], $val['expires_at'] ) ) {
			return array(
				'count'      => (int) $val['count'],
				'expires_at' => (int) $val['expires_at'],
			);
		}
		if ( false !== $val && is_numeric( $val ) ) {
			return array(
				'count'      => (int) $val,
				'expires_at' => time() + self::get_window_seconds(),
			);
		}

		return null;
	}

	/**
	 * Persist rate-limit counter strictly into persistent object cache or WP Transients.
	 *
	 * @param string $key   Hashed key.
	 * @param array  $state Bucket state.
	 * @param int    $ttl   Expiration seconds.
	 * @return bool True if persistent write succeeded, false otherwise.
	 */
	private static function write_bucket( string $key, array $state, int $ttl ): bool {
		if ( function_exists( 'wp_using_ext_object_cache' ) && wp_using_ext_object_cache() && function_exists( 'wp_cache_set' ) ) {
			return (bool) wp_cache_set( $key, $state, 'despace_order_tracking', $ttl );
		}
		if ( function_exists( 'set_transient' ) ) {
			return (bool) set_transient( $key, $state, $ttl );
		}
		return false;
	}

	/**
	 * Delete rate-limit counter bucket from persistent storage.
	 *
	 * @param string $key Hashed key.
	 */
	private static function delete_bucket( string $key ): void {
		if ( function_exists( 'wp_using_ext_object_cache' ) && wp_using_ext_object_cache() && function_exists( 'wp_cache_delete' ) ) {
			wp_cache_delete( $key, 'despace_order_tracking' );
		}
		if ( function_exists( 'delete_transient' ) ) {
			delete_transient( $key );
		}
	}

	/**
	 * Sanitize UTF-8 text cleanly.
	 *
	 * @param string $value Raw text.
	 * @return string
	 */
	private static function sanitize_text( string $value ): string {
		if ( function_exists( 'sanitize_text_field' ) ) {
			return sanitize_text_field( $value );
		}
		return trim( strip_tags( $value ) );
	}
}

