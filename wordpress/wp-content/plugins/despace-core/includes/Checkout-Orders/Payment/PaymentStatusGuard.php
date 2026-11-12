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
	 * Authorized admin confirmation helper for manual BACS settlement via native WooCommerce Order CRUD.
	 *
	 * Strictly verifies capability (and optional WP admin CSRF nonce), confirms explicit receipt
	 * flag when provided via request, prevents duplicate processing on already-paid orders,
	 * delegates payment settlement to `$order->payment_complete( $bank_reference )` so WooCommerce
	 * records `date_paid`, `transaction_id`, status transition, and payment hooks natively.
	 * Never modifies stock directly or runs SQL queries.
	 *
	 * @param int    $order_id       WooCommerce order ID.
	 * @param string $bank_reference Optional real bank statement reference verified by admin.
	 * @param array  $options        Optional safeguards: `nonce`, `confirmed_received` (bool).
	 * @return array|\WP_Error Result summary or WP_Error.
	 */
	public static function confirm_bacs_payment_by_admin( int $order_id, string $bank_reference = '', array $options = array() ) {
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

		if ( array_key_exists( 'confirmed_received', $options ) && empty( $options['confirmed_received'] ) ) {
			return new \WP_Error(
				'despace_bacs_receipt_not_confirmed',
				__( 'Chỉ được xác nhận đơn hàng sau khi quản trị viên đối soát tiền thực nhận.', 'despace-core' ),
				array( 'status' => 400 )
			);
		}

		if ( ! function_exists( 'wc_get_order' ) ) {
			return new \WP_Error( 'despace_woocommerce_unavailable', __( 'WooCommerce is not available.', 'despace-core' ) );
		}

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
		if ( $is_paid || null !== $date_paid || 'on-hold' !== $status ) {
			return new \WP_Error(
				'despace_bacs_already_processed',
				__( 'Đơn hàng không ở trạng thái chờ đối soát (On hold) hoặc đã được ghi nhận thanh toán.', 'despace-core' ),
				array( 'status' => 409 )
			);
		}

		$clean_ref = self::sanitize_text( $bank_reference );
		$note      = '' !== $clean_ref
			? sprintf( __( 'Quản trị viên đã xác nhận nhận chuyển khoản BACS thực tế (Mã đối soát: %s).', 'despace-core' ), $clean_ref )
			: __( 'Quản trị viên đã xác nhận nhận chuyển khoản BACS thực tế.', 'despace-core' );

		if ( method_exists( $order, 'add_order_note' ) ) {
			$order->add_order_note( $note, false, true );
		}

		if ( method_exists( $order, 'payment_complete' ) ) {
			$order->payment_complete( $clean_ref );
		} elseif ( method_exists( $order, 'update_status' ) ) {
			$order->update_status( 'processing', $note, true );
		}

		return array(
			'confirmed'      => true,
			'order_id'       => $order_id,
			'payment_method' => $method,
			'new_status'     => method_exists( $order, 'get_status' ) ? sanitize_key( (string) $order->get_status() ) : 'processing',
			'date_paid_set'  => method_exists( $order, 'get_date_paid' ) && null !== $order->get_date_paid(),
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

