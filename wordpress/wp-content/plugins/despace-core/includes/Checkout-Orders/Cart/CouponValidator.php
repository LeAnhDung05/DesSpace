<?php
/**
 * WooCommerce Coupon Validation & Cart Coupon Backend for DeSpace (M3 Phase 1B-A).
 *
 * Delegates all discount calculation and eligibility rules to WooCommerce
 * (WC_Coupon, WC_Discounts, WC()->cart). Never stores custom coupon tables,
 * never trusts client-supplied discounts, and never mutates product stock or M4 logs.
 *
 * @package DeSpace\CheckoutOrders\Cart
 */

namespace DeSpace\CheckoutOrders\Cart;

defined( 'ABSPATH' ) || exit;

/**
 * Class CouponValidator
 */
class CouponValidator {

	/**
	 * Prevents duplicate hook registration.
	 *
	 * @var bool
	 */
	private static bool $hooks_registered = false;

	/**
	 * Register WooCommerce coupon AJAX endpoints, fragments, and frontend config extensions.
	 */
	public static function init(): void {
		if ( self::$hooks_registered ) {
			return;
		}

		self::$hooks_registered = true;

		add_filter( 'despace_m3_cart_frontend_config', array( __CLASS__, 'extend_frontend_config' ), 20, 1 );
		add_filter( 'woocommerce_add_to_cart_fragments', array( __CLASS__, 'build_coupon_fragments' ), 20, 1 );

		$ajax_actions = array(
			'despace_coupon_apply'  => 'ajax_apply_coupon',
			'despace_coupon_remove' => 'ajax_remove_coupon',
		);

		foreach ( $ajax_actions as $action => $method ) {
			add_action( 'wp_ajax_' . $action, array( __CLASS__, $method ) );
			add_action( 'wp_ajax_nopriv_' . $action, array( __CLASS__, $method ) );
		}
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
	 * Extend CartHandler's frontend config array with coupon AJAX actions and fragment selectors.
	 *
	 * @param array $config Existing M3 frontend config.
	 * @return array
	 */
	public static function extend_frontend_config( array $config ): array {
		$config['actions'] = array_merge(
			isset( $config['actions'] ) && is_array( $config['actions'] ) ? $config['actions'] : array(),
			array(
				'apply_coupon'  => 'despace_coupon_apply',
				'remove_coupon' => 'despace_coupon_remove',
			)
		);

		$config['fragments'] = array_merge(
			isset( $config['fragments'] ) && is_array( $config['fragments'] ) ? $config['fragments'] : array(),
			array(
				'coupons'  => '.despace-cart-coupons',
				'discount' => '.despace-cart-discount',
				'tax'      => '.despace-cart-tax',
			)
		);

		return $config;
	}

	/**
	 * AJAX endpoint: Apply a coupon code to the active WooCommerce cart (POST only).
	 */
	public static function ajax_apply_coupon(): void {
		if ( ! self::is_post_request() ) {
			self::send_json_response( self::method_not_allowed_response() );
			return;
		}

		$raw_post = self::get_unslashed_post();
		$result   = self::handle_apply_request( $raw_post );
		self::send_json_response( $result );
	}

	/**
	 * AJAX endpoint: Remove an applied coupon code from the active WooCommerce cart (POST only).
	 */
	public static function ajax_remove_coupon(): void {
		if ( ! self::is_post_request() ) {
			self::send_json_response( self::method_not_allowed_response() );
			return;
		}

		$raw_post = self::get_unslashed_post();
		$result   = self::handle_remove_request( $raw_post );
		self::send_json_response( $result );
	}

	/**
	 * Handle coupon apply request payload with nonce verification and input sanitization.
	 *
	 * @param array $raw_input Unslashed request array.
	 * @return array
	 */
	public static function handle_apply_request( array $raw_input ): array {
		$nonce_error = self::validate_request_nonce( $raw_input );
		if ( null !== $nonce_error ) {
			return $nonce_error;
		}

		// Explicitly ignore any client-supplied discount/price/total overrides.
		$coupon_code = isset( $raw_input['coupon_code'] ) ? (string) $raw_input['coupon_code'] : '';
		$result      = self::apply_coupon( $coupon_code );

		return self::format_action_result( $result, 200, array( 'action' => 'apply_coupon' ) );
	}

	/**
	 * Handle coupon remove request payload with nonce verification and input sanitization.
	 *
	 * @param array $raw_input Unslashed request array.
	 * @return array
	 */
	public static function handle_remove_request( array $raw_input ): array {
		$nonce_error = self::validate_request_nonce( $raw_input );
		if ( null !== $nonce_error ) {
			return $nonce_error;
		}

		$coupon_code = isset( $raw_input['coupon_code'] ) ? (string) $raw_input['coupon_code'] : '';
		$result      = self::remove_coupon( $coupon_code );

		return self::format_action_result( $result, 200, array( 'action' => 'remove_coupon' ) );
	}

	/**
	 * Validate and apply a coupon code using WooCommerce APIs.
	 *
	 * @param string $raw_code Raw coupon code input.
	 * @return array|\WP_Error
	 */
	public static function apply_coupon( string $raw_code ) {
		$cart = self::ensure_cart_ready();
		if ( is_wp_error( $cart ) ) {
			return $cart;
		}

		if ( function_exists( 'wc_coupons_enabled' ) && ! wc_coupons_enabled() ) {
			return new \WP_Error(
				'despace_coupons_disabled',
				__( 'Coupons are currently disabled.', 'despace-core' ),
				array( 'status' => 400 )
			);
		}

		CartHandler::cleanup_invalid_cart_items( $cart );

		if ( $cart->is_empty() ) {
			return new \WP_Error(
				'despace_empty_cart',
				__( 'Your cart is empty. Please add products before applying a coupon.', 'despace-core' ),
				array( 'status' => 400 )
			);
		}

		$coupon_code = self::sanitize_coupon_code( $raw_code );
		if ( '' === $coupon_code ) {
			return new \WP_Error(
				'despace_empty_coupon',
				__( 'Please enter a coupon code.', 'despace-core' ),
				array( 'status' => 400 )
			);
		}

		if ( self::is_coupon_already_applied( $cart, $coupon_code ) ) {
			return new \WP_Error(
				'despace_coupon_already_applied',
				__( 'Coupon code already applied!', 'despace-core' ),
				array( 'status' => 400 )
			);
		}

		if ( ! class_exists( 'WC_Coupon' ) ) {
			return new \WP_Error(
				'despace_woocommerce_unavailable',
				__( 'WooCommerce coupon engine is unavailable.', 'despace-core' ),
				array( 'status' => 503 )
			);
		}

		$coupon = new \WC_Coupon( $coupon_code );
		if ( ! self::is_coupon_publicly_accessible( $coupon ) ) {
			return new \WP_Error(
				'despace_coupon_invalid',
				__( 'Coupon code is invalid or does not exist.', 'despace-core' ),
				array( 'status' => 400 )
			);
		}

		// Snapshot existing notices so unrelated notices in WC session are preserved.
		$notice_snapshot = self::snapshot_wc_notices();

		if ( class_exists( 'WC_Discounts' ) ) {
			$discounts = new \WC_Discounts( $cart );
			$validity  = $discounts->is_coupon_valid( $coupon );
			if ( is_wp_error( $validity ) ) {
				self::restore_wc_notices( $notice_snapshot );
				return new \WP_Error(
					self::map_wc_coupon_error_code( $validity->get_error_code() ),
					self::sanitize_public_error_message( $validity->get_error_message() ),
					array( 'status' => 400 )
				);
			}
		}

		$applied = $cart->apply_coupon( $coupon_code );
		if ( ! $applied ) {
			$wc_error_message = self::extract_new_error_notice( $notice_snapshot );
			self::restore_wc_notices( $notice_snapshot );

			return new \WP_Error(
				'despace_coupon_rejected',
				'' !== $wc_error_message
					? $wc_error_message
					: __( 'This coupon cannot be applied to your cart.', 'despace-core' ),
				array( 'status' => 400 )
			);
		}

		self::restore_wc_notices( $notice_snapshot );
		self::ensure_guest_session_cookie();
		$cart->calculate_totals();

		return array(
			'coupon_code' => self::find_applied_coupon_code( $cart, $coupon_code ),
			'cart'        => self::build_cart_with_coupons_data( $cart ),
			'fragments'   => self::get_combined_fragments(),
		);
	}

	/**
	 * Remove an applied coupon code from the WooCommerce cart and recalculate totals.
	 *
	 * @param string $raw_code Raw coupon code input.
	 * @return array|\WP_Error
	 */
	public static function remove_coupon( string $raw_code ) {
		$cart = self::ensure_cart_ready();
		if ( is_wp_error( $cart ) ) {
			return $cart;
		}

		$coupon_code = self::sanitize_coupon_code( $raw_code );
		if ( '' === $coupon_code ) {
			return new \WP_Error(
				'despace_empty_coupon',
				__( 'Please specify the coupon code to remove.', 'despace-core' ),
				array( 'status' => 400 )
			);
		}

		$matched_code = self::find_applied_coupon_code( $cart, $coupon_code );
		if ( '' === $matched_code ) {
			return new \WP_Error(
				'despace_coupon_not_applied',
				__( 'Coupon is not currently applied to your cart.', 'despace-core' ),
				array( 'status' => 404 )
			);
		}

		$notice_snapshot = self::snapshot_wc_notices();
		$removed         = $cart->remove_coupon( $matched_code );
		self::restore_wc_notices( $notice_snapshot );

		if ( ! $removed ) {
			return new \WP_Error(
				'despace_coupon_remove_failed',
				__( 'Unable to remove coupon from cart.', 'despace-core' ),
				array( 'status' => 500 )
			);
		}

		$cart->calculate_totals();

		return array(
			'coupon_code' => $matched_code,
			'cart'        => self::build_cart_with_coupons_data( $cart ),
			'fragments'   => self::get_combined_fragments(),
		);
	}

	/**
	 * Build normalized cart summary enriched with WooCommerce coupon, discount, and tax totals.
	 *
	 * Exposes only public display values; never leaks internal coupon metadata such as
	 * usage limits, used_by lists, or email_restrictions.
	 *
	 * @param mixed $cart Optional WC_Cart instance.
	 * @return array
	 */
	public static function build_cart_with_coupons_data( $cart = null ): array {
		if ( null === $cart && function_exists( 'WC' ) && WC() ) {
			$cart = WC()->cart;
		}

		$cart_data = CartHandler::get_cart_data( $cart );

		if ( ! $cart || ! is_object( $cart ) ) {
			return array_merge(
				$cart_data,
				array(
					'applied_coupons'     => array(),
					'discount_total'      => 0.0,
					'discount_total_html' => self::format_price( 0.0 ),
					'discount_tax'        => 0.0,
					'tax_total'           => 0.0,
					'tax_total_html'      => self::format_price( 0.0 ),
				)
			);
		}

		$applied_coupons = array();
		$raw_coupons     = method_exists( $cart, 'get_applied_coupons' ) ? (array) $cart->get_applied_coupons() : array();

		foreach ( $raw_coupons as $code ) {
			$clean_code = sanitize_text_field( (string) $code );
			if ( '' === $clean_code ) {
				continue;
			}

			$discount_amt = method_exists( $cart, 'get_coupon_discount_amount' )
				? (float) $cart->get_coupon_discount_amount( $clean_code )
				: 0.0;
			$discount_tax = method_exists( $cart, 'get_coupon_discount_tax_amount' )
				? (float) $cart->get_coupon_discount_tax_amount( $clean_code )
				: 0.0;

			$applied_coupons[] = array(
				'code'                 => $clean_code,
				'discount_amount'      => $discount_amt,
				'discount_amount_html' => self::format_price( $discount_amt ),
				'discount_tax'         => $discount_tax,
			);
		}

		$discount_total = method_exists( $cart, 'get_discount_total' ) ? (float) $cart->get_discount_total() : 0.0;
		$discount_tax   = method_exists( $cart, 'get_discount_tax' ) ? (float) $cart->get_discount_tax() : 0.0;
		$tax_total      = method_exists( $cart, 'get_total_tax' ) ? (float) $cart->get_total_tax() : 0.0;

		return array_merge(
			$cart_data,
			array(
				'applied_coupons'     => $applied_coupons,
				'discount_total'      => $discount_total,
				'discount_total_html' => self::format_price( $discount_total ),
				'discount_tax'        => $discount_tax,
				'tax_total'           => $tax_total,
				'tax_total_html'      => self::format_price( $tax_total ),
			)
		);
	}

	/**
	 * Append coupon, discount, and tax fragments to WooCommerce cart fragments.
	 *
	 * @param array $fragments Existing WooCommerce fragments.
	 * @return array
	 */
	public static function build_coupon_fragments( array $fragments = array() ): array {
		$summary = self::build_cart_with_coupons_data();

		$fragments['.despace-cart-discount'] = sprintf(
			'<span class="despace-cart-discount" data-discount="%s">%s</span>',
			self::escape_attr( (string) $summary['discount_total'] ),
			$summary['discount_total_html']
		);

		$fragments['.despace-cart-tax'] = sprintf(
			'<span class="despace-cart-tax" data-tax="%s">%s</span>',
			self::escape_attr( (string) $summary['tax_total'] ),
			$summary['tax_total_html']
		);

		$coupon_items_html = '';
		foreach ( $summary['applied_coupons'] as $coupon ) {
			$coupon_items_html .= sprintf(
				'<li class="despace-cart-coupon-item" data-coupon-code="%s"><span class="despace-coupon-code">%s</span><span class="despace-coupon-amount">-%s</span></li>',
				self::escape_attr( $coupon['code'] ),
				self::escape_html( $coupon['code'] ),
				$coupon['discount_amount_html']
			);
		}

		$fragments['.despace-cart-coupons'] = sprintf(
			'<ul class="despace-cart-coupons">%s</ul>',
			$coupon_items_html
		);

		return $fragments;
	}

	/**
	 * Collect all M3 cart + coupon fragments consistently.
	 *
	 * @return array
	 */
	private static function get_combined_fragments(): array {
		$fragments = CartHandler::build_cart_fragments( array() );
		return self::build_coupon_fragments( $fragments );
	}

	/**
	 * Ensure WooCommerce session & cart objects are initialized for guest or logged-in AJAX.
	 *
	 * @return object|\WP_Error
	 */
	private static function ensure_cart_ready() {
		if ( ! function_exists( 'WC' ) || ! WC() ) {
			return new \WP_Error(
				'despace_woocommerce_unavailable',
				__( 'WooCommerce is not available.', 'despace-core' ),
				array( 'status' => 503 )
			);
		}

		$wc = WC();

		if ( null === $wc->session && method_exists( $wc, 'initialize_session' ) ) {
			$wc->initialize_session();
		}

		if ( null === $wc->cart && function_exists( 'wc_load_cart' ) ) {
			wc_load_cart();
		}

		if ( null === $wc->cart ) {
			return new \WP_Error(
				'despace_cart_unavailable',
				__( 'WooCommerce cart session is not initialized.', 'despace-core' ),
				array( 'status' => 503 )
			);
		}

		return $wc->cart;
	}

	/**
	 * Guarantee guest session cookie exists when applying a coupon before page reload.
	 */
	private static function ensure_guest_session_cookie(): void {
		if ( function_exists( 'is_user_logged_in' ) && is_user_logged_in() ) {
			return;
		}

		if ( function_exists( 'WC' ) && WC() && WC()->session ) {
			$session     = WC()->session;
			$has_session = method_exists( $session, 'has_session' ) ? $session->has_session() : false;
			if ( ! $has_session && method_exists( $session, 'set_customer_session_cookie' ) ) {
				$session->set_customer_session_cookie( true );
			}
		}
	}

	/**
	 * Validate AJAX nonce in request payload using CartHandler's shared action.
	 *
	 * @param array $raw_input Request payload.
	 * @return array|null
	 */
	private static function validate_request_nonce( array $raw_input ): ?array {
		$nonce = '';
		if ( isset( $raw_input['nonce'] ) ) {
			$nonce = sanitize_text_field( (string) $raw_input['nonce'] );
		} elseif ( isset( $raw_input['_ajax_nonce'] ) ) {
			$nonce = sanitize_text_field( (string) $raw_input['_ajax_nonce'] );
		}

		$valid = '' !== $nonce && function_exists( 'wp_verify_nonce' ) && (bool) wp_verify_nonce( $nonce, CartHandler::NONCE_ACTION );

		if ( ! $valid ) {
			return array(
				'success' => false,
				'status'  => 403,
				'data'    => array(
					'code'    => 'despace_invalid_nonce',
					'message' => __( 'Security verification failed. Please refresh the page and try again.', 'despace-core' ),
				),
			);
		}

		return null;
	}

	/**
	 * Format and sanitize coupon code cleanly.
	 *
	 * @param string $raw_code Raw code.
	 * @return string
	 */
	private static function sanitize_coupon_code( string $raw_code ): string {
		$trimmed = trim( sanitize_text_field( $raw_code ) );
		if ( '' === $trimmed ) {
			return '';
		}

		if ( function_exists( 'wc_format_coupon_code' ) ) {
			return (string) wc_format_coupon_code( $trimmed );
		}

		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $trimmed, 'UTF-8' ) : strtolower( $trimmed );
	}

	/**
	 * Verify coupon exists and is not an unpublished internal draft/trash record.
	 *
	 * @param object $coupon WC_Coupon instance.
	 * @return bool
	 */
	private static function is_coupon_publicly_accessible( object $coupon ): bool {
		if ( ! method_exists( $coupon, 'get_id' ) || (int) $coupon->get_id() <= 0 ) {
			return false;
		}

		if ( method_exists( $coupon, 'get_status' ) ) {
			$status = (string) $coupon->get_status();
			if ( '' !== $status && ! in_array( $status, array( 'publish', 'future' ), true ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Check whether coupon code is already applied to the cart (case-insensitive).
	 *
	 * @param object $cart        WC_Cart instance.
	 * @param string $coupon_code Normalized coupon code.
	 * @return bool
	 */
	private static function is_coupon_already_applied( object $cart, string $coupon_code ): bool {
		if ( method_exists( $cart, 'has_discount' ) && $cart->has_discount( $coupon_code ) ) {
			return true;
		}

		return '' !== self::find_applied_coupon_code( $cart, $coupon_code );
	}

	/**
	 * Find exact stored coupon code inside WC()->cart->get_applied_coupons().
	 *
	 * @param object $cart        WC_Cart instance.
	 * @param string $coupon_code Sanitized coupon code.
	 * @return string Matched coupon code or empty string.
	 */
	private static function find_applied_coupon_code( object $cart, string $coupon_code ): string {
		$applied = method_exists( $cart, 'get_applied_coupons' ) ? (array) $cart->get_applied_coupons() : array();
		$needle  = self::sanitize_coupon_code( $coupon_code );

		foreach ( $applied as $item_code ) {
			$candidate = (string) $item_code;
			if ( self::sanitize_coupon_code( $candidate ) === $needle ) {
				return $candidate;
			}
		}

		return '';
	}

	/**
	 * Map WooCommerce internal/numeric coupon error codes to consistent DeSpace error slugs.
	 *
	 * @param string|int $wc_code WooCommerce coupon error code.
	 * @return string
	 */
	private static function map_wc_coupon_error_code( $wc_code ): string {
		$key = is_string( $wc_code ) || is_int( $wc_code ) ? (string) $wc_code : '';

		$map = array(
			'100' => 'despace_coupon_filter_rejected',
			'101' => 'despace_coupon_not_applicable',
			'102' => 'despace_coupon_min_quantity',
			'103' => 'despace_coupon_already_applied',
			'104' => 'despace_coupon_individual_use_conflict',
			'105' => 'despace_coupon_invalid',
			'106' => 'despace_coupon_usage_limit_reached',
			'107' => 'despace_coupon_expired',
			'108' => 'despace_coupon_min_spend_not_met',
			'109' => 'despace_coupon_max_spend_exceeded',
			'110' => 'despace_coupon_invalid',
			'111' => 'despace_coupon_not_applicable',
			'112' => 'despace_coupon_max_spend_exceeded',
			'113' => 'despace_coupon_exclude_sale_items',
			'114' => 'despace_coupon_category_restricted',
			'115' => 'despace_coupon_not_started',
			'invalid_coupon' => 'despace_coupon_invalid',
		);

		if ( isset( $map[ $key ] ) ) {
			return $map[ $key ];
		}

		if ( '' !== $key && 0 === strpos( $key, 'despace_' ) ) {
			return sanitize_key( $key );
		}

		return 'despace_coupon_rejected';
	}

	/**
	 * Sanitize error message before sending to client so HTML/tags never leak.
	 *
	 * @param string $message Raw error string.
	 * @return string
	 */
	private static function sanitize_public_error_message( string $message ): string {
		$clean = function_exists( 'wp_strip_all_tags' ) ? wp_strip_all_tags( $message ) : strip_tags( $message );
		$clean = trim( $clean );
		return '' !== $clean ? $clean : __( 'This coupon cannot be applied to your cart.', 'despace-core' );
	}

	/**
	 * Snapshot all existing WooCommerce notices so unrelated session notices are never wiped.
	 *
	 * @return array
	 */
	private static function snapshot_wc_notices(): array {
		if ( ! function_exists( 'wc_get_notices' ) ) {
			return array();
		}

		$notices = wc_get_notices();
		return is_array( $notices ) ? $notices : array();
	}

	/**
	 * Extract only newly appended error notice added during the current coupon call.
	 *
	 * @param array $before_snapshot Notices snapshot captured before coupon call.
	 * @return string
	 */
	private static function extract_new_error_notice( array $before_snapshot ): string {
		if ( ! function_exists( 'wc_get_notices' ) ) {
			return '';
		}

		$after_errors = wc_get_notices( 'error' );
		if ( empty( $after_errors ) || ! is_array( $after_errors ) ) {
			return '';
		}

		$before_errors = isset( $before_snapshot['error'] ) && is_array( $before_snapshot['error'] )
			? $before_snapshot['error']
			: array();
		$new_errors    = array_slice( $after_errors, count( $before_errors ) );
		$target_list   = ! empty( $new_errors ) ? $new_errors : $after_errors;

		$last = end( $target_list );
		if ( is_array( $last ) && isset( $last['notice'] ) ) {
			return self::sanitize_public_error_message( (string) $last['notice'] );
		}

		if ( is_string( $last ) ) {
			return self::sanitize_public_error_message( $last );
		}

		return '';
	}

	/**
	 * Restore pre-existing unrelated notices after isolating the coupon attempt.
	 *
	 * @param array $before_snapshot Notices snapshot captured before coupon call.
	 */
	private static function restore_wc_notices( array $before_snapshot ): void {
		if ( function_exists( 'wc_set_notices' ) ) {
			wc_set_notices( $before_snapshot );
			return;
		}

		if ( ! function_exists( 'wc_clear_notices' ) ) {
			return;
		}

		wc_clear_notices();
		if ( function_exists( 'wc_add_notice' ) ) {
			foreach ( $before_snapshot as $notice_type => $entries ) {
				if ( ! is_array( $entries ) ) {
					continue;
				}
				foreach ( $entries as $entry ) {
					$msg = is_array( $entry ) ? (string) ( $entry['notice'] ?? '' ) : (string) $entry;
					if ( '' !== $msg ) {
						wc_add_notice( $msg, (string) $notice_type );
					}
				}
			}
		}
	}

	/**
	 * Verify current HTTP request uses POST for state-changing AJAX actions.
	 *
	 * @return bool
	 */
	private static function is_post_request(): bool {
		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) ) {
			return false;
		}
		$method = strtoupper( sanitize_text_field( (string) $_SERVER['REQUEST_METHOD'] ) );
		return 'POST' === $method;
	}

	/**
	 * Build 405 Method Not Allowed error envelope.
	 *
	 * @return array
	 */
	private static function method_not_allowed_response(): array {
		return array(
			'success' => false,
			'status'  => 405,
			'data'    => array(
				'code'    => 'despace_method_not_allowed',
				'message' => __( 'HTTP POST method is required for this action.', 'despace-core' ),
			),
		);
	}

	/**
	 * Format action result into unified AJAX response envelope.
	 *
	 * @param mixed $result         Result array or WP_Error.
	 * @param int   $default_status Default HTTP status on success.
	 * @param array $meta           Extra metadata.
	 * @return array
	 */
	private static function format_action_result( $result, int $default_status = 200, array $meta = array() ): array {
		if ( is_wp_error( $result ) ) {
			$err_data = $result->get_error_data();
			$status   = is_array( $err_data ) && isset( $err_data['status'] ) ? (int) $err_data['status'] : 400;

			return array(
				'success' => false,
				'status'  => $status,
				'data'    => array(
					'code'    => $result->get_error_code(),
					'message' => self::sanitize_public_error_message( $result->get_error_message() ),
				),
			);
		}

		return array(
			'success' => true,
			'status'  => $default_status,
			'data'    => array_merge( $meta, is_array( $result ) ? $result : array() ),
		);
	}

	/**
	 * Emit JSON response via WordPress AJAX helpers.
	 *
	 * @param array $response Formatted response array.
	 */
	private static function send_json_response( array $response ): void {
		$status = (int) ( $response['status'] ?? 200 );
		$data   = $response['data'] ?? array();

		if ( ! empty( $response['success'] ) ) {
			wp_send_json_success( $data, $status );
		}

		wp_send_json_error( $data, $status );
	}

	/**
	 * Read unslashed $_POST array safely.
	 *
	 * @return array
	 */
	private static function get_unslashed_post(): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is validated inside handle_*_request().
		$raw = $_POST ?? array();
		if ( function_exists( 'wp_unslash' ) ) {
			$raw = wp_unslash( $raw );
		}
		return is_array( $raw ) ? $raw : array();
	}

	/**
	 * Format price using WooCommerce when available.
	 *
	 * @param float $amount Price amount.
	 * @return string
	 */
	private static function format_price( float $amount ): string {
		if ( function_exists( 'wc_price' ) ) {
			return wc_price( $amount );
		}

		return number_format( $amount, 0, ',', '.' ) . ' ₫';
	}

	/**
	 * Escape HTML safely with fallback when outside WP bootstrap.
	 *
	 * @param string $value Raw string.
	 * @return string
	 */
	private static function escape_html( string $value ): string {
		return function_exists( 'esc_html' ) ? esc_html( $value ) : htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * Escape HTML attribute safely with fallback when outside WP bootstrap.
	 *
	 * @param string $value Raw string.
	 * @return string
	 */
	private static function escape_attr( string $value ): string {
		return function_exists( 'esc_attr' ) ? esc_attr( $value ) : htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' );
	}
}

