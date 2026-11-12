<?php
/**
 * WooCommerce Payment Status & Reconciliation Guard for DeSpace (M3 Phase 2B / Issue #18).
 *
 * Protects COD and BACS orders from client-side status/date_paid/transaction_id spoofing
 * and enforces WooCommerce's native offline-payment lifecycle:
 * - BACS orders start as `on-hold`, unpaid (`date_paid = null`), until confirmed by an authorized admin.
 * - COD orders follow native COD status (`processing` or `on-hold` for downloadable) without auto-`completed`.
 *
 * Uses strict WooCommerce Order CRUD (HPOS-compatible). Never calls `payment_complete()`
 * from frontend logic and never reduces or restores product stock directly.
 *
 * @package DeSpace\CheckoutOrders\Payment
 */

namespace DeSpace\CheckoutOrders\Payment;

defined( 'ABSPATH' ) || exit;

/**
 * Class PaymentStatusGuard
 */
class PaymentStatusGuard {

	/**
	 * Prevents duplicate hook registration.
	 *
	 * @var bool
	 */
	private static bool $hooks_registered = false;

	/**
	 * Register WooCommerce status guards and create-order payment sanitization hooks.
	 */
	public static function init(): void {
		if ( self::$hooks_registered ) {
			return;
		}

		self::$hooks_registered = true;

		add_filter( 'woocommerce_cod_process_payment_order_status', array( __CLASS__, 'enforce_cod_initial_status' ), 20, 2 );
		add_filter( 'woocommerce_bacs_process_payment_order_status', array( __CLASS__, 'enforce_bacs_initial_status' ), 20, 2 );
		add_action( 'woocommerce_checkout_create_order', array( __CLASS__, 'guard_new_order_payment_fields' ), 30, 2 );
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
	 * Detect client attempts to inject payment status, paid dates, transaction IDs, or order totals.
	 *
	 * @param array $raw_input Untrusted request input.
	 * @return \WP_Error|null Returns WP_Error when spoofing keys carry values, null otherwise.
	 */
	public static function detect_tampered_payment_input( array $raw_input ): ?\WP_Error {
		foreach ( PaymentMethodValidator::tampered_field_keys() as $key ) {
			if ( ! array_key_exists( $key, $raw_input ) ) {
				continue;
			}
			$val = $raw_input[ $key ];
			if ( is_bool( $val ) || '' !== trim( (string) $val ) ) {
				return new \WP_Error(
					'despace_forbidden_payment_override',
					__( 'Không được phép tự truyền trạng thái thanh toán, mã giao dịch hoặc tổng tiền từ phía trình duyệt.', 'despace-core' ),
					array( 'status' => 400, 'field' => $key )
				);
			}
		}

		return null;
	}

	/**
	 * Prevent COD orders from starting as `completed` at checkout before cash delivery is collected.
	 *
	 * Respects WooCommerce's native `processing` / `on-hold` transition behavior.
	 *
	 * @param string      $status Initial status proposed by WC_Gateway_COD.
	 * @param object|null $order  WC_Order instance if supplied.
	 * @return string
	 */
	public static function enforce_cod_initial_status( string $status, ?object $order = null ): string {
		$clean = sanitize_key( $status );
		if ( 'completed' === $clean || '' === $clean ) {
			if ( is_object( $order ) && method_exists( $order, 'has_downloadable_item' ) && $order->has_downloadable_item() ) {
				return 'on-hold';
			}
			return 'processing';
		}

		return $clean;
	}

	/**
	 * Ensure payable BACS orders always start in `on-hold` awaiting manual bank transfer verification.
	 *
	 * @param string      $status Initial status proposed by WC_Gateway_BACS.
	 * @param object|null $order  WC_Order instance if supplied.
	 * @return string
	 */
	public static function enforce_bacs_initial_status( string $status, ?object $order = null ): string {
		if ( is_object( $order ) && method_exists( $order, 'get_total' ) && (float) $order->get_total() <= 0.0 ) {
			return sanitize_key( $status ) ?: 'processing';
		}

		return 'on-hold';
	}

	/**
	 * Clear any stray paid date or fake transaction ID before WooCommerce saves a new COD/BACS order.
	 *
	 * Invoked during `woocommerce_checkout_create_order` prior to `$order->save()`.
	 *
	 * @param object $order Newly built WC_Order instance.
	 * @param array  $data  Posted checkout data.
	 */
	public static function guard_new_order_payment_fields( object $order, array $data ): void {
		$method = method_exists( $order, 'get_payment_method' )
			? sanitize_key( (string) $order->get_payment_method() )
			: sanitize_key( (string) ( $data['payment_method'] ?? '' ) );

		if ( ! in_array( $method, PaymentMethodValidator::MVP_GATEWAYS, true ) ) {
			return;
		}

		$total = method_exists( $order, 'get_total' ) ? (float) $order->get_total() : 0.0;
		if ( $total <= 0.0 ) {
			return;
		}

		if ( method_exists( $order, 'set_date_paid' ) ) {
			$order->set_date_paid( null );
		}
		if ( method_exists( $order, 'set_transaction_id' ) ) {
			$order->set_transaction_id( '' );
		}
	}

	/**
	 * Check whether an offline COD/BACS order is genuinely awaiting payment settlement/confirmation.
	 *
	 * @param object $order WC_Order instance.
	 * @return bool
	 */
	public static function is_order_awaiting_settlement( object $order ): bool {
		if ( ! method_exists( $order, 'get_payment_method' ) ) {
			return false;
		}

		$method = sanitize_key( (string) $order->get_payment_method() );
		if ( ! in_array( $method, PaymentMethodValidator::MVP_GATEWAYS, true ) ) {
			return false;
		}

		$is_paid   = method_exists( $order, 'is_paid' ) ? (bool) $order->is_paid() : false;
		$date_paid = method_exists( $order, 'get_date_paid' ) ? $order->get_date_paid() : null;
		$status    = method_exists( $order, 'get_status' ) ? sanitize_key( (string) $order->get_status() ) : '';

		if ( $is_paid || null !== $date_paid ) {
			return false;
		}

		if ( PaymentMethodValidator::GATEWAY_BACS === $method ) {
			return 'on-hold' === $status;
		}

		return 'completed' !== $status && 'cancelled' !== $status && 'refunded' !== $status;
	}

	/**
	 * Verify whether current WordPress user holds capability to confirm manual offline payments.
	 *
	 * @return bool
	 */
	public static function can_confirm_manual_payment(): bool {
		if ( ! function_exists( 'current_user_can' ) ) {
			return false;
		}

		return current_user_can( 'manage_woocommerce' ) || current_user_can( 'edit_shop_orders' );
	}

	/**
	 * Default DB atomic lock TTL in seconds for manual BACS settlement.
	 */
	public const DEFAULT_LOCK_TTL = 30;

	/**
	 * Meta key marking that BACS confirmation note has already been persisted.
	 */
	public const META_CONFIRMED_AT = '_despace_bacs_confirmed_at';

	/**
	 * Normalize and validate explicit admin confirmation flag (`confirmed_received`).
	 *
	 * Never infers payment receipt from nonce presence alone. Accepts strict booleans or
	 * explicit normalized REST/AJAX strings (`"true"`, `"1"`, `"yes"`, `"on"` vs `"false"`, `"0"`, `"no"`, `"off"`),
	 * and rejects missing, false, or ambiguous inputs.
	 *
	 * @param array $options Request options containing `confirmed_received`.
	 * @return true|\WP_Error True if explicitly confirmed, WP_Error otherwise.
	 */
	public static function parse_confirmed_received( array $options ) {
		if ( ! array_key_exists( 'confirmed_received', $options ) ) {
			return new \WP_Error(
				'despace_bacs_receipt_confirmation_required',
				__( 'Bắt buộc phải có xác nhận đã đối soát nhận tiền thực tế từ quản trị viên.', 'despace-core' ),
				array( 'status' => 400 )
			);
		}

		$raw = $options['confirmed_received'];
		if ( is_bool( $raw ) ) {
			$confirmed = $raw;
		} elseif ( is_int( $raw ) ) {
			if ( 1 === $raw ) {
				$confirmed = true;
			} elseif ( 0 === $raw ) {
				$confirmed = false;
			} else {
				return new \WP_Error(
					'despace_invalid_bacs_receipt_confirmation',
					__( 'Giá trị xác nhận đã nhận tiền không hợp lệ.', 'despace-core' ),
					array( 'status' => 400 )
				);
			}
		} elseif ( is_string( $raw ) ) {
			$norm = strtolower( trim( $raw ) );
			if ( in_array( $norm, array( 'true', '1', 'yes', 'on' ), true ) ) {
				$confirmed = true;
			} elseif ( in_array( $norm, array( 'false', '0', 'no', 'off' ), true ) ) {
				$confirmed = false;
			} else {
				return new \WP_Error(
					'despace_invalid_bacs_receipt_confirmation',
					__( 'Giá trị xác nhận đã nhận tiền không hợp lệ hoặc mơ hồ.', 'despace-core' ),
					array( 'status' => 400 )
				);
			}
		} else {
			return new \WP_Error(
				'despace_invalid_bacs_receipt_confirmation',
				__( 'Kiểu dữ liệu xác nhận đã nhận tiền không hợp lệ.', 'despace-core' ),
				array( 'status' => 400 )
			);
		}

		if ( ! $confirmed ) {
			return new \WP_Error(
				'despace_bacs_receipt_not_confirmed',
				__( 'Chỉ được xác nhận đơn hàng sau khi quản trị viên đối soát tiền thực nhận.', 'despace-core' ),
				array( 'status' => 400 )
			);
		}

		return true;
	}

	/**
	 * Authorized admin confirmation helper for manual BACS settlement via native WooCommerce Order CRUD.
	 *
	 * Enforces capability, mandatory CSRF nonce, explicit `confirmed_received` boolean confirmation,
	 * and a multi-request DB-backed atomic lock on WordPress core's options store (with TTL expiry
	 * CAS takeover and owner-token safe release in `finally`). Re-reads order inside lock via HPOS
	 * CRUD, checks `payment_complete()` return value, re-verifies persisted paid status, and writes
	 * the admin audit note strictly after verified completion. Never mutates stock directly.
	 *
	 * @param int    $order_id       WooCommerce order ID.
	 * @param string $bank_reference Optional real bank statement reference verified by admin.
	 * @param array  $options        Safeguards: `nonce` (string), `confirmed_received` (bool/normalized), optional `lock_ttl` (int).
	 * @return array|\WP_Error Result summary or WP_Error.
	 */
	public static function confirm_bacs_payment_by_admin( int $order_id, string $bank_reference = '', array $options = array() ) {
		$auth_error = self::verify_confirmation_authorization( $order_id, $options );
		if ( null !== $auth_error ) {
			return $auth_error;
		}

		if ( ! function_exists( 'wc_get_order' ) ) {
			return new \WP_Error( 'despace_woocommerce_unavailable', __( 'WooCommerce is not available.', 'despace-core' ), array( 'status' => 503 ) );
		}

		$ttl  = isset( $options['lock_ttl'] ) ? max( 1, (int) $options['lock_ttl'] ) : self::DEFAULT_LOCK_TTL;
		$lock = self::acquire_bacs_lock( absint( $order_id ), $ttl );
		if ( is_wp_error( $lock ) ) {
			return $lock;
		}

		try {
			// Re-verify authorization and explicit confirmation strictly under the atomic lock.
			$recheck_auth = self::verify_confirmation_authorization( $order_id, $options );
			if ( null !== $recheck_auth ) {
				return $recheck_auth;
			}

			// Always re-read fresh order state inside the lock via HPOS-compatible CRUD.
			$order = wc_get_order( absint( $order_id ) );
			if ( ! $order || ! is_object( $order ) ) {
				return new \WP_Error( 'despace_order_not_found', __( 'Không tìm thấy đơn hàng.', 'despace-core' ), array( 'status' => 404 ) );
			}

			$method = method_exists( $order, 'get_payment_method' ) ? sanitize_key( (string) $order->get_payment_method() ) : '';
			if ( PaymentMethodValidator::GATEWAY_BACS !== $method ) {
				return new \WP_Error(
					'despace_invalid_bacs_order',
					__( 'Đơn hàng này không sử dụng phương thức chuyển khoản ngân hàng (BACS).', 'despace-core' ),
					array( 'status' => 400 )
				);
			}

			$is_paid   = method_exists( $order, 'is_paid' ) ? (bool) $order->is_paid() : false;
			$date_paid = method_exists( $order, 'get_date_paid' ) ? $order->get_date_paid() : null;
			$status    = method_exists( $order, 'get_status' ) ? sanitize_key( (string) $order->get_status() ) : '';
			$confirmed_meta = method_exists( $order, 'get_meta' ) ? (string) $order->get_meta( self::META_CONFIRMED_AT, true ) : '';

			if ( $is_paid || null !== $date_paid || 'on-hold' !== $status || '' !== $confirmed_meta ) {
				return new \WP_Error(
					'despace_bacs_already_processed',
					__( 'Đơn hàng không ở trạng thái chờ đối soát (On hold) hoặc đã được ghi nhận thanh toán.', 'despace-core' ),
					array( 'status' => 409 )
				);
			}

			$clean_ref = self::sanitize_text( $bank_reference );
			$completed = false;

			if ( method_exists( $order, 'payment_complete' ) ) {
				$completed = (bool) $order->payment_complete( $clean_ref );
			} elseif ( method_exists( $order, 'update_status' ) ) {
				$order->update_status( 'processing', '', true );
				$completed = true;
			}

			if ( ! $completed ) {
				return new \WP_Error(
					'despace_bacs_payment_complete_failed',
					__( 'WooCommerce không thể hoàn tất ghi nhận thanh toán BACS cho đơn hàng này.', 'despace-core' ),
					array( 'status' => 500 )
				);
			}

			// Re-read order after payment_complete() to confirm payment status actually transitioned.
			$verified_order = wc_get_order( absint( $order_id ) );
			if ( ! $verified_order || ! is_object( $verified_order ) ) {
				$verified_order = $order;
			}

			$verified_paid      = method_exists( $verified_order, 'is_paid' ) ? (bool) $verified_order->is_paid() : false;
			$verified_date_paid = method_exists( $verified_order, 'get_date_paid' ) ? $verified_order->get_date_paid() : null;
			$verified_status    = method_exists( $verified_order, 'get_status' ) ? sanitize_key( (string) $verified_order->get_status() ) : '';

			if ( ( ! $verified_paid && null === $verified_date_paid ) || 'on-hold' === $verified_status || 'pending' === $verified_status ) {
				return new \WP_Error(
					'despace_bacs_payment_not_recorded',
					__( 'Trạng thái thanh toán BACS chưa được ghi nhận thực tế sau khi gọi payment_complete().', 'despace-core' ),
					array( 'status' => 500 )
				);
			}

			// Record idempotent audit meta and confirmation note strictly AFTER verified settlement.
			$already_noted = method_exists( $verified_order, 'get_meta' ) ? (string) $verified_order->get_meta( self::META_CONFIRMED_AT, true ) : '';
			if ( '' === $already_noted ) {
				if ( method_exists( $verified_order, 'update_meta_data' ) ) {
					$verified_order->update_meta_data( self::META_CONFIRMED_AT, gmdate( 'c' ) );
				}
				if ( method_exists( $verified_order, 'add_order_note' ) ) {
					$note = '' !== $clean_ref
						? sprintf( __( 'Quản trị viên đã xác nhận nhận chuyển khoản BACS thực tế (Mã đối soát: %s).', 'despace-core' ), $clean_ref )
						: __( 'Quản trị viên đã xác nhận nhận chuyển khoản BACS thực tế.', 'despace-core' );
					$verified_order->add_order_note( $note, false, true );
				}
				if ( method_exists( $verified_order, 'save' ) ) {
					$verified_order->save();
				}
			}

			return array(
				'confirmed'      => true,
				'order_id'       => absint( $order_id ),
				'payment_method' => $method,
				'new_status'     => $verified_status,
				'is_paid'        => $verified_paid,
				'date_paid_set'  => null !== $verified_date_paid,
			);
		} finally {
			self::release_bacs_lock( $lock );
		}
	}

	/**
	 * Verify admin capability, CSRF nonce, and explicit payment receipt confirmation.
	 *
	 * @param int   $order_id WooCommerce order ID.
	 * @param array $options  Confirmation request options.
	 * @return \WP_Error|null Null when authorized, WP_Error on failure.
	 */
	private static function verify_confirmation_authorization( int $order_id, array $options ): ?\WP_Error {
		if ( ! self::can_confirm_manual_payment() ) {
			return new \WP_Error(
				'despace_unauthorized_payment_confirmation',
				__( 'Bạn không có quyền xác nhận thanh toán chuyển khoản.', 'despace-core' ),
				array( 'status' => 403 )
			);
		}

		$nonce = self::sanitize_text( (string) ( $options['nonce'] ?? '' ) );
		if ( '' === $nonce || ! function_exists( 'wp_verify_nonce' ) || ! wp_verify_nonce( $nonce, 'despace_confirm_bacs_payment_' . absint( $order_id ) ) ) {
			return new \WP_Error(
				'despace_invalid_nonce',
				__( 'Phiên xác thực bảo mật (nonce) không hợp lệ hoặc bị thiếu.', 'despace-core' ),
				array( 'status' => 403 )
			);
		}

		$confirmed_check = self::parse_confirmed_received( $options );
		if ( is_wp_error( $confirmed_check ) ) {
			return $confirmed_check;
		}

		return null;
	}

	/**
	 * Acquire a DB-backed atomic idempotency lock using WordPress core's options table.
	 *
	 * Guarantees multi-request / multi-process atomicity via unique `option_name` INSERT IGNORE
	 * and Compare-And-Swap expiry takeover, without custom payment tables or order table queries.
	 *
	 * @param int $order_id    Order ID being confirmed.
	 * @param int $ttl_seconds Lock lease lifetime in seconds.
	 * @return array|\WP_Error Lock descriptor (`key`, `token`, `payload`, `expires_at`) or WP_Error.
	 */
	public static function acquire_bacs_lock( int $order_id, int $ttl_seconds = self::DEFAULT_LOCK_TTL ) {
		$db = $GLOBALS['wpdb'] ?? null;
		if ( ! is_object( $db ) || empty( $db->options ) || ! method_exists( $db, 'prepare' ) || ! method_exists( $db, 'query' ) || ! method_exists( $db, 'get_var' ) ) {
			return new \WP_Error(
				'despace_bacs_lock_unavailable',
				__( 'Hệ thống khóa nguyên tử xử lý thanh toán tạm thời không khả dụng.', 'despace-core' ),
				array( 'status' => 503 )
			);
		}

		$table   = (string) $db->options;
		$key     = 'despace_bacs_lock_' . absint( $order_id );
		$ttl     = max( 1, $ttl_seconds );
		$now     = time();
		$expires = $now + $ttl;
		$token   = self::generate_lock_token();
		$payload = (string) json_encode(
			array(
				'token'      => $token,
				'expires_at' => $expires,
			)
		);

		$insert_sql = $db->prepare(
			"INSERT IGNORE INTO {$table} (option_name, option_value, autoload) VALUES (%s, %s, %s)",
			$key,
			$payload,
			'no'
		);
		$inserted = (int) $db->query( $insert_sql );
		if ( 1 === $inserted ) {
			return array(
				'key'        => $key,
				'token'      => $token,
				'payload'    => $payload,
				'expires_at' => $expires,
			);
		}

		// Row already exists — check whether previous lease expired and take over atomically via CAS.
		$existing_raw = $db->get_var(
			$db->prepare( "SELECT option_value FROM {$table} WHERE option_name = %s LIMIT 1", $key )
		);

		if ( null === $existing_raw || false === $existing_raw ) {
			// Prior holder just released between INSERT IGNORE and SELECT; try atomic insert once more.
			if ( 1 === (int) $db->query( $insert_sql ) ) {
				return array(
					'key'        => $key,
					'token'      => $token,
					'payload'    => $payload,
					'expires_at' => $expires,
				);
			}
		} else {
			$decoded = json_decode( (string) $existing_raw, true );
			$old_exp = is_array( $decoded ) ? (int) ( $decoded['expires_at'] ?? 0 ) : 0;
			if ( $old_exp <= $now ) {
				$cas_sql = $db->prepare(
					"UPDATE {$table} SET option_value = %s WHERE option_name = %s AND option_value = %s",
					$payload,
					$key,
					(string) $existing_raw
				);
				if ( 1 === (int) $db->query( $cas_sql ) ) {
					return array(
						'key'        => $key,
						'token'      => $token,
						'payload'    => $payload,
						'expires_at' => $expires,
					);
				}
			}
		}

		return new \WP_Error(
			'despace_bacs_confirmation_in_progress',
			__( 'Đơn hàng đang được xử lý xác nhận thanh toán bởi một yêu cầu khác.', 'despace-core' ),
			array( 'status' => 409 )
		);
	}

	/**
	 * Release a previously acquired DB atomic lock strictly if caller's owner token still owns it.
	 *
	 * Prevents an expired slow request from deleting a newer request's active lock.
	 *
	 * @param array $lock Lock descriptor returned by `acquire_bacs_lock()`.
	 * @return bool True when row owned by this token was removed, false otherwise.
	 */
	public static function release_bacs_lock( array $lock ): bool {
		if ( empty( $lock['key'] ) || empty( $lock['payload'] ) ) {
			return false;
		}

		$db = $GLOBALS['wpdb'] ?? null;
		if ( ! is_object( $db ) || empty( $db->options ) || ! method_exists( $db, 'prepare' ) || ! method_exists( $db, 'query' ) ) {
			return false;
		}

		$table   = (string) $db->options;
		$deleted = (int) $db->query(
			$db->prepare(
				"DELETE FROM {$table} WHERE option_name = %s AND option_value = %s",
				(string) $lock['key'],
				(string) $lock['payload']
			)
		);

		return 1 === $deleted;
	}

	/**
	 * Generate cryptographically random owner token for atomic lock ownership checks.
	 *
	 * @return string
	 */
	private static function generate_lock_token(): string {
		if ( function_exists( 'random_bytes' ) ) {
			try {
				return bin2hex( random_bytes( 16 ) );
			} catch ( \Throwable $e ) {
				// Fallback below.
			}
		}
		if ( function_exists( 'wp_generate_uuid4' ) ) {
			return (string) wp_generate_uuid4();
		}
		return uniqid( 'despace_lock_', true );
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

