<?php
/**
 * Privacy-Minimized Order Tracking Presenter for DeSpace (M3 Phase 3 / Issue #19).
 *
 * Transforms an already-authorized `WC_Order` into a safe customer-facing summary via
 * strict field whitelisting and WooCommerce CRUD getters (HPOS-compatible):
 * - Maps native WooCommerce order statuses to Vietnamese labels without altering status values.
 * - Exposes only customer-visible notes (`get_customer_order_notes()`), never private admin notes.
 * - Integrates `BacsInstructions` securely without leaking `order_key`, `customer_id`, or `transaction_id`.
 * - Clearly states that tracking reflects WooCommerce order status (not carrier GPS).
 *
 * @package DeSpace\CheckoutOrders\Orders
 */

namespace DeSpace\CheckoutOrders\Orders;

use DeSpace\CheckoutOrders\Payment\BacsInstructions;
use DeSpace\CheckoutOrders\Payment\PaymentMethodValidator;
use DeSpace\CheckoutOrders\Shipping\VietnamRegions;

defined( 'ABSPATH' ) || exit;

/**
 * Class OrderTrackingPresenter
 */
class OrderTrackingPresenter {

	/**
	 * Vietnamese display map for native WooCommerce order statuses.
	 *
	 * @var array<string, string>
	 */
	private const STATUS_LABELS = array(
		'pending'    => 'Chờ thanh toán',
		'on-hold'    => 'Đang tạm giữ (Chờ xác nhận)',
		'processing' => 'Đang xử lý',
		'completed'  => 'Hoàn thành',
		'cancelled'  => 'Đã hủy',
		'refunded'   => 'Đã hoàn tiền',
		'failed'     => 'Thanh toán thất bại',
	);

	/**
	 * Build strictly whitelisted tracking payload from an authorized WC_Order.
	 *
	 * Never returns `customer_id`, `order_key`, `transaction_id`, or private admin order notes.
	 *
	 * @param object $order Authorized WC_Order instance.
	 * @return array
	 */
	public static function present_order( object $order ): array {
		$status         = method_exists( $order, 'get_status' ) ? sanitize_key( (string) $order->get_status() ) : 'pending';
		$order_number   = method_exists( $order, 'get_order_number' ) ? self::sanitize_text( (string) $order->get_order_number() ) : '';
		$created_at     = self::format_order_datetime( method_exists( $order, 'get_date_created' ) ? $order->get_date_created() : null );
		$updated_at     = self::format_order_datetime( method_exists( $order, 'get_date_modified' ) ? $order->get_date_modified() : null );
		$currency       = method_exists( $order, 'get_currency' ) ? self::sanitize_text( (string) $order->get_currency() ) : 'VND';

		$subtotal       = method_exists( $order, 'get_subtotal' ) ? (float) $order->get_subtotal() : 0.0;
		$discount_total = method_exists( $order, 'get_discount_total' ) ? (float) $order->get_discount_total() : 0.0;
		$shipping_total = method_exists( $order, 'get_shipping_total' ) ? (float) $order->get_shipping_total() : 0.0;
		$order_total    = method_exists( $order, 'get_total' ) ? (float) $order->get_total() : 0.0;

		$payment_method = method_exists( $order, 'get_payment_method' ) ? sanitize_key( (string) $order->get_payment_method() ) : '';
		$payment_title  = method_exists( $order, 'get_payment_method_title' ) ? self::sanitize_text( (string) $order->get_payment_method_title() ) : strtoupper( $payment_method );
		$is_paid        = method_exists( $order, 'is_paid' ) ? (bool) $order->is_paid() : false;
		$payment_status = self::resolve_payment_status( $status, $is_paid );

		$bacs_payload = null;
		if ( PaymentMethodValidator::GATEWAY_BACS === $payment_method && 'on-hold' === $status && ! $is_paid ) {
			// Pass order_key purely internally to authorize BacsInstructions; NEVER include it in response.
			$internal_key = method_exists( $order, 'get_order_key' ) ? (string) $order->get_order_key() : '';
			$bacs_res     = BacsInstructions::get_order_instructions( $order, array( 'order_key' => $internal_key ) );
			if ( ! is_wp_error( $bacs_res ) ) {
				$bacs_payload = array(
					'instructions'       => (string) ( $bacs_res['instructions'] ?? '' ),
					'accounts'           => (array) ( $bacs_res['accounts'] ?? array() ),
					'transfer_reference' => (string) ( $bacs_res['transfer_reference'] ?? $order_number ),
					'qr_generated'       => false,
				);
			}
		}

		return array(
			'order_number'        => $order_number,
			'created_at'          => $created_at,
			'updated_at'          => $updated_at,
			'status'              => $status,
			'status_label'        => self::translate_status( $status ),
			'tracking_scope_note' => __( 'Trạng thái được đồng bộ trực tiếp từ hệ thống xử lý đơn hàng WooCommerce; chưa bao gồm định vị GPS hoặc mã vận đơn đơn vị vận chuyển.', 'despace-core' ),
			'payment'             => array(
				'method'               => $payment_method,
				'method_title'         => $payment_title,
				'status'               => $payment_status['code'],
				'status_label'         => $payment_status['label'],
				'is_paid'              => $is_paid,
				'can_confirm_payment'  => false,
				'bacs_instructions'    => $bacs_payload,
			),
			'shipping'            => array(
				'method'       => self::extract_shipping_method_title( $order ),
				'total'        => $shipping_total,
				'total_html'   => self::format_price( $shipping_total ),
			),
			'recipient'           => self::extract_minimized_recipient( $order ),
			'items'               => self::extract_safe_items( $order ),
			'totals'              => array(
				'currency'            => $currency ?: 'VND',
				'subtotal'            => $subtotal,
				'subtotal_html'       => self::format_price( $subtotal ),
				'discount_total'      => $discount_total,
				'discount_total_html' => self::format_price( $discount_total ),
				'shipping_total'      => $shipping_total,
				'shipping_total_html' => self::format_price( $shipping_total ),
				'order_total'         => $order_total,
				'order_total_html'    => self::format_price( $order_total ),
			),
			'customer_notes'      => self::extract_customer_notes( $order ),
		);
	}

	/**
	 * Map native WooCommerce status slug to Vietnamese display text.
	 *
	 * @param string $status Native WC status slug (with or without `wc-` prefix).
	 * @return string
	 */
	public static function translate_status( string $status ): string {
		$clean = sanitize_key( $status );
		$clean = 0 === strpos( $clean, 'wc-' ) ? substr( $clean, 3 ) : $clean;
		return self::STATUS_LABELS[ $clean ] ?? ucfirst( $clean );
	}

	/**
	 * Build minimized recipient name & bounded regional location (hiding exact street number/private PII).
	 *
	 * Uses `VietnamRegions::extract_order_address()` cleanly without mutating historical orders.
	 *
	 * @param object $order WC_Order instance.
	 * @return array
	 */
	private static function extract_minimized_recipient( object $order ): array {
		$first = method_exists( $order, 'get_shipping_first_name' ) ? (string) $order->get_shipping_first_name() : '';
		$last  = method_exists( $order, 'get_shipping_last_name' ) ? (string) $order->get_shipping_last_name() : '';
		if ( '' === trim( $first . $last ) ) {
			$first = method_exists( $order, 'get_billing_first_name' ) ? (string) $order->get_billing_first_name() : '';
			$last  = method_exists( $order, 'get_billing_last_name' ) ? (string) $order->get_billing_last_name() : '';
		}
		$recipient_name = self::sanitize_text( trim( $last . ' ' . $first ) );

		$addr        = VietnamRegions::extract_order_address( $order, 'shipping' );
		$commune     = self::sanitize_text( (string) ( $addr['commune_name'] ?? '' ) );
		$region_name = self::sanitize_text( (string) ( $addr['region_name'] ?? '' ) );
		$country     = self::sanitize_text( (string) ( $addr['country'] ?? 'VN' ) );
		$masked_line = self::mask_address_line( (string) ( $addr['address_detail'] ?? '' ) );

		$parts         = array_values( array_filter( array( $masked_line, $commune, $region_name ) ) );
		$limited_label = implode( ', ', $parts );

		return array(
			'name'             => $recipient_name,
			'country'          => $country,
			'commune_name'     => $commune,
			'region_name'      => $region_name,
			'address_summary'  => $limited_label,
		);
	}

	/**
	 * Extract purchased items with safe quantities, totals, and product image URL if available.
	 *
	 * @param object $order WC_Order instance.
	 * @return array<int, array<string, mixed>>
	 */
	private static function extract_safe_items( object $order ): array {
		if ( ! method_exists( $order, 'get_items' ) ) {
			return array();
		}

		$out = array();
		foreach ( (array) $order->get_items() as $item ) {
			if ( ! is_object( $item ) ) {
				continue;
			}
			$name     = method_exists( $item, 'get_name' ) ? self::sanitize_text( (string) $item->get_name() ) : '';
			$qty      = method_exists( $item, 'get_quantity' ) ? (int) $item->get_quantity() : 1;
			$total    = method_exists( $item, 'get_total' ) ? (float) $item->get_total() : 0.0;
			$prod_id  = method_exists( $item, 'get_variation_id' ) && $item->get_variation_id() > 0
				? (int) $item->get_variation_id()
				: ( method_exists( $item, 'get_product_id' ) ? (int) $item->get_product_id() : 0 );

			$image_url = '';
			if ( $prod_id > 0 && function_exists( 'wc_get_product' ) ) {
				$product = wc_get_product( $prod_id );
				if ( is_object( $product ) && method_exists( $product, 'get_image_id' ) ) {
					$img_id = (int) $product->get_image_id();
					if ( $img_id > 0 && function_exists( 'wp_get_attachment_image_url' ) ) {
						$url = wp_get_attachment_image_url( $img_id, 'woocommerce_thumbnail' );
						if ( is_string( $url ) ) {
							$image_url = function_exists( 'esc_url_raw' ) ? esc_url_raw( $url ) : $url;
						}
					}
				}
			}

			$out[] = array(
				'name'            => $name,
				'quantity'        => $qty,
				'line_total'      => $total,
				'line_total_html' => self::format_price( $total ),
				'image_url'       => $image_url,
			);
		}

		return $out;
	}

	/**
	 * Read ONLY customer-facing order notes (`get_customer_order_notes()`), excluding admin private notes.
	 *
	 * @param object $order WC_Order instance.
	 * @return array<int, array{date: string, note: string}>
	 */
	private static function extract_customer_notes( object $order ): array {
		if ( ! method_exists( $order, 'get_customer_order_notes' ) ) {
			return array();
		}

		$notes = array();
		foreach ( (array) $order->get_customer_order_notes() as $raw_note ) {
			$content = is_object( $raw_note ) ? (string) ( $raw_note->comment_content ?? '' ) : ( is_array( $raw_note ) ? (string) ( $raw_note['note'] ?? '' ) : (string) $raw_note );
			$date    = is_object( $raw_note ) ? (string) ( $raw_note->comment_date ?? '' ) : ( is_array( $raw_note ) ? (string) ( $raw_note['date'] ?? '' ) : '' );
			$clean   = self::sanitize_text( $content );
			if ( '' !== $clean ) {
				$notes[] = array(
					'date' => self::sanitize_text( $date ),
					'note' => $clean,
				);
			}
		}

		return $notes;
	}

	/**
	 * Read shipping method title safely from WC_Order.
	 *
	 * @param object $order WC_Order instance.
	 * @return string
	 */
	private static function extract_shipping_method_title( object $order ): string {
		if ( method_exists( $order, 'get_shipping_method' ) ) {
			return self::sanitize_text( (string) $order->get_shipping_method() );
		}
		return '';
	}

	/**
	 * Derive customer-safe payment status without modifying order state.
	 *
	 * @param string $order_status Native WC order status.
	 * @param bool   $is_paid      Whether WooCommerce reports order paid.
	 * @return array{code: string, label: string}
	 */
	private static function resolve_payment_status( string $order_status, bool $is_paid ): array {
		if ( 'refunded' === $order_status ) {
			return array( 'code' => 'refunded', 'label' => 'Đã hoàn tiền' );
		}
		if ( 'cancelled' === $order_status || 'failed' === $order_status ) {
			return array( 'code' => 'unpaid', 'label' => 'Chưa thanh toán / Đã hủy' );
		}
		if ( $is_paid ) {
			return array( 'code' => 'paid', 'label' => 'Đã xác nhận thanh toán' );
		}
		return array( 'code' => 'unpaid', 'label' => 'Chưa thanh toán' );
	}

	/**
	 * Mask leading street/house characters so tracking minimizes full address PII while keeping recognition.
	 *
	 * @param string $address_detail Raw street address line.
	 * @return string
	 */
	private static function mask_address_line( string $address_detail ): string {
		$clean = self::sanitize_text( $address_detail );
		if ( '' === $clean ) {
			return '';
		}

		$words = preg_split( '/\s+/u', $clean );
		if ( ! is_array( $words ) || count( $words ) <= 2 ) {
			return '***';
		}

		$tail = array_slice( $words, -2 );
		return '*** ' . implode( ' ', $tail );
	}

	/**
	 * Format WC_DateTime or string timestamps consistently.
	 *
	 * @param mixed $dt Date object or string.
	 * @return string
	 */
	private static function format_order_datetime( $dt ): string {
		if ( is_object( $dt ) && method_exists( $dt, 'format' ) ) {
			return (string) $dt->format( 'Y-m-d H:i:s' );
		}
		return is_string( $dt ) ? self::sanitize_text( $dt ) : '';
	}

	/**
	 * Format currency using WooCommerce when available.
	 *
	 * @param float $amount Amount value.
	 * @return string
	 */
	private static function format_price( float $amount ): string {
		if ( function_exists( 'wc_price' ) ) {
			return wc_price( $amount );
		}
		return number_format( $amount, 0, ',', '.' ) . ' ₫';
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

