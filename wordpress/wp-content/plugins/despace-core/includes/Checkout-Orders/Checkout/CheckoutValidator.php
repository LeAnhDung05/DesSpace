<?php
/**
 * Server-Side Checkout Validator for DeSpace (M3 Phase 2A / Issue #17).
 *
 * Uses WooCommerce (`WC_Cart`, `WC_Coupon`, `WC_Discounts`, `WC_Product`, `WC_Checkout`)
 * as the single source of truth for cart eligibility, pricing, coupons, shipping rates,
 * and order totals. Never mutates product stock, never writes M4 inventory logs, and
 * never registers a parallel checkout or order engine.
 *
 * Architecture: Classic WooCommerce Checkout (`WC_Checkout` hooks & validation lifecycle).
 *
 * @package DeSpace\CheckoutOrders\Checkout
 */

namespace DeSpace\CheckoutOrders\Checkout;

use DeSpace\CheckoutOrders\Cart\CartHandler;
use DeSpace\CheckoutOrders\Shipping\ShippingNoticeHelper;
use DeSpace\CheckoutOrders\Shipping\VietnamRegions;

defined( 'ABSPATH' ) || exit;

/**
 * Class CheckoutValidator
 */
class CheckoutValidator {

	/**
	 * Active Checkout architecture identifier until M2 locks final theme implementation.
	 */
	public const CHECKOUT_ARCHITECTURE = 'classic_woocommerce_checkout';

	/**
	 * Prevents duplicate hook registration.
	 *
	 * @var bool
	 */
	private static bool $hooks_registered = false;

	/**
	 * Register WooCommerce Classic Checkout validation hooks and frontend handoff metadata.
	 */
	public static function init(): void {
		if ( self::$hooks_registered ) {
			return;
		}

		self::$hooks_registered = true;

		add_filter( 'despace_m3_cart_frontend_config', array( __CLASS__, 'extend_frontend_config' ), 40, 1 );
		add_action( 'woocommerce_check_cart_items', array( __CLASS__, 'validate_cart_items_hook' ), 20 );
		add_action( 'woocommerce_after_checkout_validation', array( __CLASS__, 'validate_checkout_submission' ), 20, 2 );
		add_action( 'woocommerce_checkout_update_customer', array( __CLASS__, 'sync_customer_address_fields' ), 20, 2 );
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
	 * Expose non-authoritative Checkout handoff metadata for M2 Child Theme integration.
	 *
	 * @param array $config Existing M3 frontend configuration.
	 * @return array
	 */
	public static function extend_frontend_config( array $config ): array {
		$config['checkout'] = array(
			'architecture'                => self::CHECKOUT_ARCHITECTURE,
			'submission_mode'             => 'woocommerce_native_checkout',
			'requires_district_field'     => false,
			'postcode_required'           => false,
			'ship_to_different_supported' => true,
			'guest_checkout_enabled'      => ! self::is_registration_required(),
			'address_fields'              => array(
				'billing'  => array(
					'required' => array( 'billing_first_name', 'billing_email', 'billing_phone', 'billing_country', 'billing_state', 'billing_city', 'billing_address_1' ),
					'optional' => array( 'billing_last_name', 'billing_despace_commune_code', 'billing_postcode', 'order_comments' ),
				),
				'shipping' => array(
					'required_when_different' => array( 'shipping_first_name', 'shipping_country', 'shipping_state', 'shipping_city', 'shipping_address_1' ),
					'optional'                => array( 'shipping_last_name', 'shipping_phone', 'shipping_despace_commune_code', 'shipping_postcode' ),
				),
			),
			'meta_keys'                   => array(
				'shipping_region_code'  => OrderMetaSaver::META_SHIPPING_REGION_CODE,
				'shipping_commune_code' => OrderMetaSaver::META_SHIPPING_COMMUNE_CODE,
				'billing_region_code'   => OrderMetaSaver::META_BILLING_REGION_CODE,
				'billing_commune_code'  => OrderMetaSaver::META_BILLING_COMMUNE_CODE,
			),
		);

		return $config;
	}

	/**
	 * Hook callback for `woocommerce_check_cart_items` (before checkout renders or processes).
	 */
	public static function validate_cart_items_hook(): void {
		$wc = function_exists( 'WC' ) ? WC() : null;
		if ( ! $wc || empty( $wc->cart ) ) {
			return;
		}

		$cart_errors = new \WP_Error();
		self::validate_cart( $wc->cart, $cart_errors );
		self::validate_coupons( $wc->cart, $cart_errors );

		self::emit_wp_errors_as_wc_notices( $cart_errors );
	}

	/**
	 * Hook callback for `woocommerce_after_checkout_validation`.
	 *
	 * Validates guest/customer permission, cart items, product availability/prices, coupons,
	 * recipient/2-tier VN addresses, and shipping rates strictly server-side.
	 *
	 * @param array  $posted_data Posted data sanitized by WC_Checkout.
	 * @param object $errors      WP_Error bag managed by WC_Checkout.
	 */
	public static function validate_checkout_submission( array $posted_data, object $errors ): void {
		self::validate_checkout( $posted_data, null, $errors );
	}

	/**
	 * Keep customer session/saved shipping & billing columns aligned with normalized 2-tier VN values
	 * when WooCommerce updates customer profile on checkout, without erasing unchanged saved fields.
	 *
	 * @param object $customer WC_Customer object being updated.
	 * @param array  $data     Posted checkout data.
	 */
	public static function sync_customer_address_fields( object $customer, array $data ): void {
		$validated = AddressValidator::validate_checkout_addresses( $data );
		if ( is_wp_error( $validated ) ) {
			return;
		}

		$billing  = $validated['billing']['wc_mapping'] ?? array();
		$shipping = $validated['shipping']['wc_mapping'] ?? array();

		if ( ! empty( $billing ) ) {
			if ( '' !== ( $billing['state'] ?? '' ) && method_exists( $customer, 'set_billing_state' ) ) {
				$customer->set_billing_state( $billing['state'] );
			}
			if ( '' !== ( $billing['city'] ?? '' ) && method_exists( $customer, 'set_billing_city' ) ) {
				$customer->set_billing_city( $billing['city'] );
			}
		}

		if ( ! empty( $shipping ) ) {
			if ( '' !== ( $shipping['state'] ?? '' ) && method_exists( $customer, 'set_shipping_state' ) ) {
				$customer->set_shipping_state( $shipping['state'] );
			}
			if ( '' !== ( $shipping['city'] ?? '' ) && method_exists( $customer, 'set_shipping_city' ) ) {
				$customer->set_shipping_city( $shipping['city'] );
			}
		}
	}

	/**
	 * Run full server-side checkout validation across Cart, Coupons, Customer/Guest, Address, and Shipping.
	 *
	 * @param array       $raw_posted_data Raw or WC-posted checkout payload.
	 * @param mixed       $cart            Optional WC_Cart instance (defaults to WC()->cart).
	 * @param object|null $wc_errors       Optional existing WP_Error bag.
	 * @return array|\WP_Error Verified non-authoritative summary on pass, or WP_Error on any failure.
	 */
	public static function validate_checkout( array $raw_posted_data, $cart = null, ?object $wc_errors = null ) {
		$errors = ( null !== $wc_errors && is_wp_error( $wc_errors ) )
			? $wc_errors
			: new \WP_Error();

		$posted_data = AddressValidator::normalize_posted_checkout_data( $raw_posted_data );
		unset(
			$posted_data['customer_id'],
			$posted_data['user_id'],
			$posted_data['price'],
			$posted_data['line_total']
		);

		$cart_obj = self::resolve_cart( $cart );
		if ( is_wp_error( $cart_obj ) ) {
			$errors->add( $cart_obj->get_error_code(), $cart_obj->get_error_message() );
			return $errors;
		}

		$customer_ctx = self::validate_customer_eligibility( $errors );
		self::validate_cart( $cart_obj, $errors );
		self::validate_coupons( $cart_obj, $errors );

		$addresses = AddressValidator::validate_checkout_addresses( $posted_data, $errors );

		$shipping_summary = array();
		if ( ! is_wp_error( $addresses ) ) {
			$shipping_summary = self::validate_shipping( $cart_obj, $posted_data, $addresses, $errors );
		}

		if ( self::bag_has_errors( $errors ) ) {
			return $errors;
		}

		if ( method_exists( $cart_obj, 'calculate_totals' ) ) {
			$cart_obj->calculate_totals();
		}

		return array(
			'valid'             => true,
			'architecture'      => self::CHECKOUT_ARCHITECTURE,
			'customer_context'  => $customer_ctx,
			'addresses'         => $addresses,
			'shipping'          => $shipping_summary,
			'applied_coupons'   => method_exists( $cart_obj, 'get_applied_coupons' ) ? (array) $cart_obj->get_applied_coupons() : array(),
			'server_subtotal'   => method_exists( $cart_obj, 'get_subtotal' ) ? (float) $cart_obj->get_subtotal() : 0.0,
			'server_total'      => method_exists( $cart_obj, 'get_total' ) ? (float) $cart_obj->get_total( 'edit' ) : 0.0,
		);
	}

	/**
	 * Verify native WooCommerce Classic Checkout CSRF nonce when checking raw POST payloads.
	 *
	 * Note: Nonce is strictly CSRF protection, never user authentication.
	 *
	 * @param array $raw_post Raw request array.
	 * @return bool
	 */
	public static function verify_checkout_csrf_nonce( array $raw_post ): bool {
		$nonce = '';
		if ( isset( $raw_post['woocommerce-process-checkout-nonce'] ) ) {
			$nonce = self::sanitize_text( (string) $raw_post['woocommerce-process-checkout-nonce'] );
		} elseif ( isset( $raw_post['_wpnonce'] ) ) {
			$nonce = self::sanitize_text( (string) $raw_post['_wpnonce'] );
		}

		return '' !== $nonce
			&& function_exists( 'wp_verify_nonce' )
			&& (bool) wp_verify_nonce( $nonce, 'woocommerce-process_checkout' );
	}

	/**
	 * Validate cart existence, product publish/purchasable status, variations, stock, sold_individually,
	 * and authoritative server-side pricing.
	 *
	 * @param object    $cart   WC_Cart instance.
	 * @param \WP_Error $errors Error collector.
	 */
	public static function validate_cart( object $cart, \WP_Error $errors ): void {
		if ( $cart->is_empty() ) {
			$errors->add(
				'despace_empty_cart',
				__( 'Giỏ hàng của bạn đang trống. Vui lòng thêm sản phẩm trước khi thanh toán.', 'despace-core' )
			);
			return;
		}

		$items            = method_exists( $cart, 'get_cart' ) ? (array) $cart->get_cart() : array();
		$stock_totals     = array();
		$product_totals   = array();
		$product_objects  = array();

		if ( empty( $items ) ) {
			$errors->add(
				'despace_empty_cart',
				__( 'Không có sản phẩm hợp lệ nào trong giỏ hàng để đặt hàng.', 'despace-core' )
			);
			return;
		}

		foreach ( $items as $cart_item_key => $cart_item ) {
			if ( ! is_array( $cart_item ) ) {
				continue;
			}

			$product_id   = (int) ( $cart_item['product_id'] ?? 0 );
			$variation_id = (int) ( $cart_item['variation_id'] ?? 0 );
			$quantity_raw = $cart_item['quantity'] ?? 0;

			if ( ! is_numeric( $quantity_raw ) || (float) $quantity_raw !== (float) ( (int) $quantity_raw ) || (int) $quantity_raw <= 0 || (int) $quantity_raw > CartHandler::MAX_QUANTITY ) {
				$errors->add(
					'despace_invalid_cart_quantity',
					__( 'Số lượng một số sản phẩm trong giỏ hàng không hợp lệ. Vui lòng kiểm tra lại.', 'despace-core' )
				);
				return;
			}

			$quantity = (int) $quantity_raw;
			$product  = self::resolve_fresh_product( $cart_item, $product_id, $variation_id );

			if ( ! $product || ! is_object( $product ) || ( method_exists( $product, 'exists' ) && ! $product->exists() ) ) {
				$errors->add(
					'despace_product_missing',
					__( 'Một sản phẩm trong giỏ hàng không còn tồn tại hoặc đã bị xóa.', 'despace-core' )
				);
				return;
			}

			if ( method_exists( $product, 'get_status' ) && 'publish' !== $product->get_status() ) {
				$errors->add(
					'despace_product_unpublished',
					sprintf(
						/* translators: %s: product name */
						__( 'Sản phẩm "%s" hiện không còn được mở bán.', 'despace-core' ),
						self::safe_product_name( $product )
					)
				);
				return;
			}

			if ( ! self::validate_variation_integrity( $product, $product_id, $variation_id ) ) {
				$errors->add(
					'despace_invalid_variation',
					__( 'Biến thể sản phẩm trong giỏ hàng không hợp lệ hoặc không còn khả dụng.', 'despace-core' )
				);
				return;
			}

			if ( method_exists( $product, 'is_purchasable' ) && ! $product->is_purchasable() ) {
				$errors->add(
					'despace_product_unpurchasable',
					sprintf(
						/* translators: %s: product name */
						__( 'Sản phẩm "%s" hiện không thể mua được.', 'despace-core' ),
						self::safe_product_name( $product )
					)
				);
				return;
			}

			$server_price = method_exists( $product, 'get_price' ) ? $product->get_price() : '';
			if ( '' === (string) $server_price || ! is_numeric( $server_price ) || (float) $server_price < 0.0 ) {
				$errors->add(
					'despace_invalid_product_price',
					sprintf(
						/* translators: %s: product name */
						__( 'Giá sản phẩm "%s" chưa hợp lệ để thanh toán.', 'despace-core' ),
						self::safe_product_name( $product )
					)
				);
				return;
			}

			if ( method_exists( $product, 'is_in_stock' ) && ! $product->is_in_stock() ) {
				$errors->add(
					'despace_product_out_of_stock',
					sprintf(
						/* translators: %s: product name */
						__( 'Sản phẩm "%s" đã hết hàng.', 'despace-core' ),
						self::safe_product_name( $product )
					)
				);
				return;
			}

			$product_totals[ $product_id ] = ( $product_totals[ $product_id ] ?? 0 ) + $quantity;
			if ( method_exists( $product, 'is_sold_individually' ) && $product->is_sold_individually() && $product_totals[ $product_id ] > 1 ) {
				$errors->add(
					'despace_sold_individually_exceeded',
					sprintf(
						/* translators: %s: product name */
						__( 'Sản phẩm "%s" chỉ cho phép mua 1 sản phẩm cho mỗi đơn hàng.', 'despace-core' ),
						self::safe_product_name( $product )
					)
				);
				return;
			}

			$stock_id                    = method_exists( $product, 'get_stock_managed_by_id' ) ? (int) $product->get_stock_managed_by_id() : ( $variation_id > 0 ? $variation_id : $product_id );
			$stock_totals[ $stock_id ]   = ( $stock_totals[ $stock_id ] ?? 0 ) + $quantity;
			$product_objects[ $stock_id ] = $product;

			if ( method_exists( $product, 'has_enough_stock' ) && ! $product->has_enough_stock( $stock_totals[ $stock_id ] ) ) {
				$errors->add(
					'despace_insufficient_stock',
					sprintf(
						/* translators: %s: product name */
						__( 'Sản phẩm "%s" không đủ số lượng tồn kho cho yêu cầu đặt hàng.', 'despace-core' ),
						self::safe_product_name( $product )
					)
				);
				return;
			}
		}
	}

	/**
	 * Re-validate all coupons currently applied on the cart through WooCommerce Coupon/Discounts APIs.
	 *
	 * @param object    $cart   WC_Cart instance.
	 * @param \WP_Error $errors Error collector.
	 */
	public static function validate_coupons( object $cart, \WP_Error $errors ): void {
		if ( method_exists( $cart, 'check_cart_coupons' ) ) {
			$cart->check_cart_coupons();
		}

		$applied = method_exists( $cart, 'get_applied_coupons' ) ? (array) $cart->get_applied_coupons() : array();
		if ( empty( $applied ) ) {
			return;
		}

		if ( ! class_exists( 'WC_Coupon' ) || ! class_exists( 'WC_Discounts' ) ) {
			return;
		}

		$discounts       = new \WC_Discounts( $cart );
		$individual_seen = false;

		foreach ( $applied as $raw_code ) {
			$code   = (string) $raw_code;
			$coupon = new \WC_Coupon( $code );

			if ( method_exists( $coupon, 'get_id' ) && (int) $coupon->get_id() <= 0 ) {
				$errors->add(
					'despace_invalid_coupon',
					sprintf( __( 'Mã giảm giá "%s" không tồn tại.', 'despace-core' ), $code )
				);
				return;
			}

			if ( method_exists( $coupon, 'get_status' ) && 'publish' !== $coupon->get_status() ) {
				$errors->add(
					'despace_coupon_unavailable',
					sprintf( __( 'Mã giảm giá "%s" hiện không còn khả dụng.', 'despace-core' ), $code )
				);
				return;
			}

			if ( method_exists( $coupon, 'get_individual_use' ) && $coupon->get_individual_use() ) {
				if ( count( $applied ) > 1 || $individual_seen ) {
					$errors->add(
						'despace_coupon_individual_use',
						sprintf( __( 'Mã giảm giá "%s" không thể áp dụng cùng các mã khác.', 'despace-core' ), $code )
					);
					return;
				}
				$individual_seen = true;
			}

			if ( method_exists( $discounts, 'is_coupon_valid' ) ) {
				$validity = $discounts->is_coupon_valid( $coupon );
				if ( is_wp_error( $validity ) ) {
					$errors->add( $validity->get_error_code(), $validity->get_error_message() );
					return;
				}
			}
		}
	}

	/**
	 * Re-validate shipping eligibility and rate availability strictly server-side via ShippingNoticeHelper.
	 *
	 * @param object    $cart        WC_Cart instance.
	 * @param array     $posted_data Normalized checkout payload.
	 * @param array     $addresses   Validated addresses structure from AddressValidator.
	 * @param \WP_Error $errors      Error collector.
	 * @return array Verified shipping summary (or empty if cart needs no shipping).
	 */
	public static function validate_shipping(
		object $cart,
		array $posted_data,
		array $addresses,
		\WP_Error $errors
	): array {
		if ( method_exists( $cart, 'needs_shipping' ) && ! $cart->needs_shipping() ) {
			return array(
				'status'           => 'not_needed',
				'needs_shipping'   => false,
				'zone_match_level' => 'none',
			);
		}

		$shipping_dest = $addresses['shipping'] ?? $addresses['billing'];
		$dest_override = array(
			'country'        => $shipping_dest['country'] ?? 'VN',
			'region_code'    => $shipping_dest['region_code'] ?? '',
			'region_name'    => $shipping_dest['region_name'] ?? '',
			'commune_code'   => $shipping_dest['commune_code'] ?? '',
			'commune_name'   => $shipping_dest['commune_name'] ?? '',
			'address_detail' => $shipping_dest['address_detail'] ?? '',
			'postcode'       => $shipping_dest['postcode'] ?? '',
		);

		$summary = ShippingNoticeHelper::get_shipping_summary( $cart, $dest_override );
		if ( is_wp_error( $summary ) ) {
			$errors->add( $summary->get_error_code(), $summary->get_error_message() );
			return array();
		}

		if ( 'available' !== ( $summary['status'] ?? '' ) || empty( $summary['can_deliver'] ) || empty( $summary['rates'] ) ) {
			$err_code = ! empty( $summary['missing_fields'] ) ? 'despace_shipping_address_incomplete' : 'despace_no_shipping_method';
			$errors->add( $err_code, (string) ( $summary['notice'] ?? __( 'Không tìm thấy phương thức vận chuyển phù hợp cho địa chỉ nhận hàng.', 'despace-core' ) ) );
			return $summary;
		}

		$submitted_rate_id = self::extract_submitted_shipping_rate_id( $posted_data );
		if ( '' !== $submitted_rate_id ) {
			$valid_rate_ids = array();
			foreach ( $summary['rates'] as $rate ) {
				if ( '' !== (string) ( $rate['shipping_rate_id'] ?? '' ) ) {
					$valid_rate_ids[] = (string) $rate['shipping_rate_id'];
				}
			}

			if ( ! in_array( $submitted_rate_id, $valid_rate_ids, true ) ) {
				$errors->add(
					'despace_invalid_shipping_method',
					__( 'Phương thức vận chuyển đã chọn không còn tồn tại hoặc không áp dụng cho địa chỉ này.', 'despace-core' )
				);
			}
		}

		return $summary;
	}

	/**
	 * Enforce WooCommerce Guest vs Logged-in Checkout configuration without bypassing auth rules.
	 *
	 * @param \WP_Error $errors Error collector.
	 * @return array{mode: string, customer_id: int, guest_allowed: bool}
	 */
	private static function validate_customer_eligibility( \WP_Error $errors ): array {
		$current_user_id = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
		$customer_id     = function_exists( 'apply_filters' )
			? (int) apply_filters( 'woocommerce_checkout_customer_id', $current_user_id )
			: $current_user_id;

		$registration_required = self::is_registration_required();
		if ( $customer_id <= 0 && $registration_required ) {
			$errors->add(
				'despace_login_required',
				__( 'Bạn cần đăng nhập tài khoản trước khi tiến hành thanh toán.', 'despace-core' )
			);
		}

		return array(
			'mode'          => $customer_id > 0 ? 'customer' : 'guest',
			'customer_id'   => max( 0, $customer_id ),
			'guest_allowed' => ! $registration_required,
		);
	}

	/**
	 * Determine whether WooCommerce requires customer login at checkout.
	 *
	 * @return bool
	 */
	private static function is_registration_required(): bool {
		$default_required = function_exists( 'get_option' )
			? ( 'yes' !== get_option( 'woocommerce_enable_guest_checkout', 'yes' ) )
			: false;

		return function_exists( 'apply_filters' )
			? (bool) apply_filters( 'woocommerce_checkout_registration_required', $default_required )
			: $default_required;
	}

	/**
	 * Resolve fresh WooCommerce product/variation object for cart line verification.
	 *
	 * @param array $cart_item    Cart item data.
	 * @param int   $product_id   Parent or simple product ID.
	 * @param int   $variation_id Variation ID if variable.
	 * @return object|null
	 */
	private static function resolve_fresh_product( array $cart_item, int $product_id, int $variation_id ): ?object {
		$target_id = $variation_id > 0 ? $variation_id : $product_id;
		if ( $target_id > 0 && function_exists( 'wc_get_product' ) ) {
			$fresh = wc_get_product( $target_id );
			if ( is_object( $fresh ) ) {
				return $fresh;
			}
		}

		return ( isset( $cart_item['data'] ) && is_object( $cart_item['data'] ) ) ? $cart_item['data'] : null;
	}

	/**
	 * Verify variable vs simple product and variation parent relationship.
	 *
	 * @param object $product      Resolved product or variation object.
	 * @param int    $product_id   Cart product ID.
	 * @param int    $variation_id Cart variation ID.
	 * @return bool
	 */
	private static function validate_variation_integrity( object $product, int $product_id, int $variation_id ): bool {
		if ( $variation_id > 0 ) {
			$parent_id = method_exists( $product, 'get_parent_id' ) ? (int) $product->get_parent_id() : 0;
			if ( $parent_id <= 0 || $parent_id !== $product_id ) {
				return false;
			}
			if ( function_exists( 'wc_get_product' ) ) {
				$parent = wc_get_product( $parent_id );
				if ( ! $parent || ( method_exists( $parent, 'get_status' ) && 'publish' !== $parent->get_status() ) ) {
					return false;
				}
			}
			return true;
		}

		// Simple / non-variation line must not point directly to an orphaned variation or variable container.
		if ( method_exists( $product, 'is_type' ) && $product->is_type( array( 'variable', 'variation' ) ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Resolve shipping rate identifier sent by WooCommerce Classic Checkout (`shipping_method[0]`).
	 *
	 * @param array $posted_data Checkout payload.
	 * @return string
	 */
	private static function extract_submitted_shipping_rate_id( array $posted_data ): string {
		$raw = $posted_data['shipping_method'] ?? '';
		if ( is_array( $raw ) ) {
			$first = reset( $raw );
			return is_string( $first ) || is_numeric( $first ) ? self::sanitize_text( (string) $first ) : '';
		}
		return is_string( $raw ) || is_numeric( $raw ) ? self::sanitize_text( (string) $raw ) : '';
	}

	/**
	 * Resolve cart object safely via WooCommerce runtime or injected mock.
	 *
	 * @param mixed $cart Optional injected cart.
	 * @return object|\WP_Error
	 */
	private static function resolve_cart( $cart ) {
		if ( is_object( $cart ) ) {
			return $cart;
		}

		if ( ! function_exists( 'WC' ) || ! WC() ) {
			return new \WP_Error(
				'despace_woocommerce_unavailable',
				__( 'WooCommerce is not available.', 'despace-core' )
			);
		}

		$wc = WC();
		if ( null === $wc->cart && function_exists( 'wc_load_cart' ) ) {
			wc_load_cart();
		}

		if ( ! is_object( $wc->cart ) ) {
			return new \WP_Error(
				'despace_cart_unavailable',
				__( 'WooCommerce cart session is not initialized.', 'despace-core' )
			);
		}

		return $wc->cart;
	}

	/**
	 * Forward any WP_Error messages into WooCommerce error notices during check_cart_items hook.
	 *
	 * @param \WP_Error $errors Collected validation errors.
	 */
	private static function emit_wp_errors_as_wc_notices( \WP_Error $errors ): void {
		if ( ! function_exists( 'wc_add_notice' ) || ! self::bag_has_errors( $errors ) ) {
			return;
		}

		$messages = method_exists( $errors, 'get_error_messages' )
			? (array) $errors->get_error_messages()
			: array( $errors->get_error_message() );

		foreach ( array_unique( array_filter( $messages ) ) as $msg ) {
			wc_add_notice( (string) $msg, 'error' );
		}
	}

	/**
	 * Safely check whether a WP_Error contains at least one error regardless of mock completeness.
	 *
	 * @param \WP_Error $errors WP_Error instance.
	 * @return bool
	 */
	private static function bag_has_errors( \WP_Error $errors ): bool {
		if ( method_exists( $errors, 'has_errors' ) ) {
			return (bool) $errors->has_errors();
		}
		return '' !== $errors->get_error_code();
	}

	/**
	 * Safely extract product title for user-facing notices.
	 *
	 * @param object $product Product object.
	 * @return string
	 */
	private static function safe_product_name( object $product ): string {
		return method_exists( $product, 'get_name' )
			? self::sanitize_text( (string) $product->get_name() )
			: __( 'Sản phẩm', 'despace-core' );
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

