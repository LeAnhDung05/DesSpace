<?php
/**
 * WooCommerce Cart Backend Handler for DeSpace (M3 Phase 1A).
 *
 * Uses WooCommerce Cart & Session APIs as the single source of truth.
 * Never mutates product stock or writes to M4 inventory logs.
 *
 * @package DeSpace\CheckoutOrders\Cart
 */

namespace DeSpace\CheckoutOrders\Cart;

defined( 'ABSPATH' ) || exit;

/**
 * Class CartHandler
 */
class CartHandler {

	/**
	 * Nonce action for cart AJAX operations.
	 */
	public const NONCE_ACTION = 'despace_cart_nonce';

	/**
	 * Maximum sensible quantity per request to prevent abusive payloads.
	 */
	public const MAX_QUANTITY = 9999;

	/**
	 * Prevents duplicate hook registration.
	 *
	 * @var bool
	 */
	private static bool $hooks_registered = false;

	/**
	 * Register WordPress / WooCommerce cart hooks and AJAX endpoints.
	 */
	public static function init(): void {
		if ( self::$hooks_registered ) {
			return;
		}

		self::$hooks_registered = true;

		add_action( 'woocommerce_cart_loaded_from_session', array( __CLASS__, 'cleanup_invalid_cart_items' ), 20, 1 );
		add_filter( 'woocommerce_add_to_cart_fragments', array( __CLASS__, 'build_cart_fragments' ), 10, 1 );
		add_filter( 'despace_m3_cart_frontend_config', array( __CLASS__, 'get_frontend_config' ), 10, 0 );

		$ajax_actions = array(
			'despace_cart_add'    => 'ajax_add_to_cart',
			'despace_cart_update' => 'ajax_update_quantity',
			'despace_cart_remove' => 'ajax_remove_item',
			'despace_cart_get'    => 'ajax_get_cart',
		);

		foreach ( $ajax_actions as $action => $method ) {
			add_action( 'wp_ajax_' . $action, array( __CLASS__, $method ) );
			add_action( 'wp_ajax_nopriv_' . $action, array( __CLASS__, $method ) );
		}
	}

	/**
	 * Build frontend AJAX & nonce configuration for M2 Child Theme localization.
	 *
	 * Nonce is used strictly for CSRF protection, not authentication or authorization.
	 *
	 * @return array
	 */
	public static function get_frontend_config(): array {
		return array(
			'ajax_url'     => function_exists( 'admin_url' ) ? admin_url( 'admin-ajax.php' ) : '/wp-admin/admin-ajax.php',
			'nonce'        => function_exists( 'wp_create_nonce' ) ? wp_create_nonce( self::NONCE_ACTION ) : '',
			'nonce_field'  => 'nonce',
			'nonce_action' => self::NONCE_ACTION,
			'actions'      => array(
				'add'    => 'despace_cart_add',
				'update' => 'despace_cart_update',
				'remove' => 'despace_cart_remove',
				'get'    => 'despace_cart_get',
			),
			'fragments'    => array(
				'count'    => '.despace-cart-count',
				'subtotal' => '.despace-cart-subtotal',
				'total'    => '.despace-cart-total',
			),
			'is_logged_in' => function_exists( 'is_user_logged_in' ) ? (bool) is_user_logged_in() : false,
		);
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
	 * AJAX endpoint: Add a product or variation to cart (POST only).
	 */
	public static function ajax_add_to_cart(): void {
		if ( ! self::is_post_request() ) {
			self::send_json_response( self::method_not_allowed_response() );
			return;
		}
		$raw_post = self::get_unslashed_post();
		$result   = self::handle_add_request( $raw_post );
		self::send_json_response( $result );
	}

	/**
	 * AJAX endpoint: Update cart item quantity (POST only).
	 */
	public static function ajax_update_quantity(): void {
		if ( ! self::is_post_request() ) {
			self::send_json_response( self::method_not_allowed_response() );
			return;
		}
		$raw_post = self::get_unslashed_post();
		$result   = self::handle_update_request( $raw_post );
		self::send_json_response( $result );
	}

	/**
	 * AJAX endpoint: Remove item from cart (POST only).
	 */
	public static function ajax_remove_item(): void {
		if ( ! self::is_post_request() ) {
			self::send_json_response( self::method_not_allowed_response() );
			return;
		}
		$raw_post = self::get_unslashed_post();
		$result   = self::handle_remove_request( $raw_post );
		self::send_json_response( $result );
	}

	/**
	 * AJAX endpoint: Fetch normalized cart data and fragments.
	 */
	public static function ajax_get_cart(): void {
		$raw_input = self::get_unslashed_request();
		$result    = self::handle_get_request( $raw_input );
		self::send_json_response( $result );
	}

	/**
	 * Handle add-to-cart request payload with nonce check and input sanitization.
	 *
	 * @param array $raw_input Unslashed request data.
	 * @return array
	 */
	public static function handle_add_request( array $raw_input ): array {
		$nonce_error = self::validate_request_nonce( $raw_input );
		if ( null !== $nonce_error ) {
			return $nonce_error;
		}

		// Explicitly ignore any price/total overrides supplied by client requests.
		$product_id   = isset( $raw_input['product_id'] ) ? absint( $raw_input['product_id'] ) : 0;
		$variation_id = isset( $raw_input['variation_id'] ) ? absint( $raw_input['variation_id'] ) : 0;
		$quantity     = $raw_input['quantity'] ?? 1;
		$attributes   = self::extract_sanitized_attributes( $raw_input );

		$result = self::add_item( $product_id, $quantity, $variation_id, $attributes );

		return self::format_action_result( $result, 200, array( 'action' => 'add' ) );
	}

	/**
	 * Handle update-quantity request payload.
	 *
	 * @param array $raw_input Unslashed request data.
	 * @return array
	 */
	public static function handle_update_request( array $raw_input ): array {
		$nonce_error = self::validate_request_nonce( $raw_input );
		if ( null !== $nonce_error ) {
			return $nonce_error;
		}

		$cart_item_key = isset( $raw_input['cart_item_key'] ) ? sanitize_text_field( (string) $raw_input['cart_item_key'] ) : '';
		$quantity      = $raw_input['quantity'] ?? null;

		$result = self::update_quantity( $cart_item_key, $quantity );

		return self::format_action_result( $result, 200, array( 'action' => 'update' ) );
	}

	/**
	 * Handle remove-item request payload.
	 *
	 * @param array $raw_input Unslashed request data.
	 * @return array
	 */
	public static function handle_remove_request( array $raw_input ): array {
		$nonce_error = self::validate_request_nonce( $raw_input );
		if ( null !== $nonce_error ) {
			return $nonce_error;
		}

		$cart_item_key = isset( $raw_input['cart_item_key'] ) ? sanitize_text_field( (string) $raw_input['cart_item_key'] ) : '';

		$result = self::remove_item( $cart_item_key );

		return self::format_action_result( $result, 200, array( 'action' => 'remove' ) );
	}

	/**
	 * Handle get-cart request payload.
	 *
	 * @param array $raw_input Unslashed request data.
	 * @return array
	 */
	public static function handle_get_request( array $raw_input ): array {
		$nonce_error = self::validate_request_nonce( $raw_input );
		if ( null !== $nonce_error ) {
			return $nonce_error;
		}

		$cart = self::ensure_cart_ready();
		if ( is_wp_error( $cart ) ) {
			return self::format_action_result( $cart, 503 );
		}

		self::cleanup_invalid_cart_items( $cart );

		return self::format_action_result(
			array(
				'cart'      => self::get_cart_data( $cart ),
				'fragments' => self::build_cart_fragments(),
			),
			200,
			array( 'action' => 'get' )
		);
	}

	/**
	 * Add a simple or variable product to WooCommerce cart.
	 *
	 * @param int   $product_id   Product ID.
	 * @param mixed $quantity     Positive integer quantity.
	 * @param int   $variation_id Variation ID (required for variable products).
	 * @param array $attributes   Sanitized variation attributes.
	 * @return array|\WP_Error
	 */
	public static function add_item( int $product_id, $quantity = 1, int $variation_id = 0, array $attributes = array() ) {
		$cart = self::ensure_cart_ready();
		if ( is_wp_error( $cart ) ) {
			return $cart;
		}

		if ( $product_id <= 0 ) {
			return new \WP_Error(
				'despace_invalid_product',
				__( 'Product ID is invalid.', 'despace-core' ),
				array( 'status' => 400 )
			);
		}

		$parsed_qty = self::parse_integer_quantity( $quantity, false );
		if ( is_wp_error( $parsed_qty ) ) {
			return $parsed_qty;
		}

		$product = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;
		if ( ! $product || ! is_object( $product ) || ! self::is_product_available( $product ) ) {
			return new \WP_Error(
				'despace_product_unavailable',
				__( 'This product is no longer available.', 'despace-core' ),
				array( 'status' => 404 )
			);
		}

		$target_product       = $product;
		$normalized_variation = 0;
		$normalized_attrs     = self::normalize_attribute_map( $attributes );

		if ( $product->is_type( 'variable' ) ) {
			if ( $variation_id <= 0 ) {
				return new \WP_Error(
					'despace_missing_variation',
					__( 'Please select a product variation before adding to cart.', 'despace-core' ),
					array( 'status' => 400 )
				);
			}

			$variation = function_exists( 'wc_get_product' ) ? wc_get_product( $variation_id ) : null;
			if ( ! self::is_valid_variation_for_parent( $variation, $product->get_id() ) ) {
				return new \WP_Error(
					'despace_invalid_variation',
					__( 'The selected variation is invalid or unavailable.', 'despace-core' ),
					array( 'status' => 400 )
				);
			}

			$attr_check = self::resolve_variation_attributes( $variation, $product, $normalized_attrs );
			if ( is_wp_error( $attr_check ) ) {
				return $attr_check;
			}

			$normalized_variation = $variation->get_id();
			$normalized_attrs     = $attr_check;
			$target_product       = $variation;
		} elseif ( $variation_id > 0 ) {
			return new \WP_Error(
				'despace_invalid_variation',
				__( 'Simple products do not accept a variation ID.', 'despace-core' ),
				array( 'status' => 400 )
			);
		} else {
			$normalized_attrs = array();
		}

		if ( ! $target_product->is_purchasable() ) {
			return new \WP_Error(
				'despace_product_not_purchasable',
				__( 'This product cannot be purchased at this time.', 'despace-core' ),
				array( 'status' => 400 )
			);
		}

		if ( ! $target_product->is_in_stock() ) {
			return new \WP_Error(
				'despace_out_of_stock',
				__( 'This product is currently out of stock.', 'despace-core' ),
				array( 'status' => 400 )
			);
		}

		$existing_cart_qty = self::get_cart_quantity_for_entity( $cart, $product_id, $normalized_variation, $normalized_attrs, $target_product );

		if ( method_exists( $target_product, 'is_sold_individually' ) && $target_product->is_sold_individually() && ( $existing_cart_qty + $parsed_qty ) > 1 ) {
			return new \WP_Error(
				'despace_sold_individually',
				__( 'You cannot add another of this item to your cart.', 'despace-core' ),
				array( 'status' => 400 )
			);
		}

		$stock_check = self::validate_requested_stock( $target_product, $existing_cart_qty + $parsed_qty );
		if ( is_wp_error( $stock_check ) ) {
			return $stock_check;
		}

		if ( function_exists( 'apply_filters' ) ) {
			$passed_validation = apply_filters(
				'woocommerce_add_to_cart_validation',
				true,
				$product_id,
				$parsed_qty,
				$normalized_variation,
				$normalized_attrs
			);
			if ( ! $passed_validation ) {
				return new \WP_Error(
					'despace_cart_validation_failed',
					__( 'Product could not be validated for cart addition.', 'despace-core' ),
					array( 'status' => 400 )
				);
			}
		}

		$cart_item_key = $cart->add_to_cart( $product_id, $parsed_qty, $normalized_variation, $normalized_attrs );
		if ( ! $cart_item_key ) {
			return new \WP_Error(
				'despace_add_to_cart_failed',
				__( 'Unable to add product to cart.', 'despace-core' ),
				array( 'status' => 400 )
			);
		}

		self::ensure_guest_session_cookie();
		$cart->calculate_totals();

		return array(
			'cart_item_key' => (string) $cart_item_key,
			'cart'          => self::get_cart_data( $cart ),
			'fragments'     => self::build_cart_fragments(),
		);
	}

	/**
	 * Update cart item quantity (setting quantity to 0 removes the item via WooCommerce).
	 *
	 * @param string $cart_item_key Cart item hash key.
	 * @param mixed  $quantity      Non-negative integer quantity.
	 * @return array|\WP_Error
	 */
	public static function update_quantity( string $cart_item_key, $quantity ) {
		$cart = self::ensure_cart_ready();
		if ( is_wp_error( $cart ) ) {
			return $cart;
		}

		$cart_item_key = sanitize_text_field( $cart_item_key );
		if ( '' === $cart_item_key ) {
			return new \WP_Error(
				'despace_invalid_cart_key',
				__( 'Cart item key is required.', 'despace-core' ),
				array( 'status' => 400 )
			);
		}

		$parsed_qty = self::parse_integer_quantity( $quantity, true );
		if ( is_wp_error( $parsed_qty ) ) {
			return $parsed_qty;
		}

		$cart_items = $cart->get_cart();
		if ( ! isset( $cart_items[ $cart_item_key ] ) ) {
			return new \WP_Error(
				'despace_cart_item_not_found',
				__( 'The cart item no longer exists or your session expired.', 'despace-core' ),
				array( 'status' => 404 )
			);
		}

		$item = $cart_items[ $cart_item_key ];

		if ( 0 === $parsed_qty ) {
			$removed = $cart->remove_cart_item( $cart_item_key );
			if ( ! $removed ) {
				return new \WP_Error(
					'despace_remove_failed',
					__( 'Could not remove cart item.', 'despace-core' ),
					array( 'status' => 500 )
				);
			}

			$cart->calculate_totals();
			if ( $cart->is_empty() ) {
				$cart->empty_cart( false );
			}

			return array(
				'cart_item_key' => $cart_item_key,
				'removed'       => true,
				'cart'          => self::get_cart_data( $cart ),
				'fragments'     => self::build_cart_fragments(),
			);
		}

		$target_product = self::resolve_cart_item_product( $item );
		if ( ! $target_product || ! self::is_product_available( $target_product ) ) {
			$cart->remove_cart_item( $cart_item_key );
			$cart->calculate_totals();

			return new \WP_Error(
				'despace_product_unavailable',
				__( 'This product is no longer available and was removed from your cart.', 'despace-core' ),
				array( 'status' => 404 )
			);
		}

		if ( ! $target_product->is_purchasable() || ! $target_product->is_in_stock() ) {
			return new \WP_Error(
				'despace_out_of_stock',
				__( 'This product is out of stock.', 'despace-core' ),
				array( 'status' => 400 )
			);
		}

		if ( method_exists( $target_product, 'is_sold_individually' ) && $target_product->is_sold_individually() && $parsed_qty > 1 ) {
			return new \WP_Error(
				'despace_sold_individually',
				__( 'Only one unit of this product may be purchased per order.', 'despace-core' ),
				array( 'status' => 400 )
			);
		}

		$other_qty   = self::get_other_cart_quantity_for_stock( $cart, $cart_item_key, $target_product );
		$stock_check = self::validate_requested_stock( $target_product, $other_qty + $parsed_qty );
		if ( is_wp_error( $stock_check ) ) {
			return $stock_check;
		}

		$updated = $cart->set_quantity( $cart_item_key, $parsed_qty, true );
		if ( ! $updated ) {
			return new \WP_Error(
				'despace_update_failed',
				__( 'Unable to update cart quantity.', 'despace-core' ),
				array( 'status' => 400 )
			);
		}

		$cart->calculate_totals();

		return array(
			'cart_item_key' => $cart_item_key,
			'removed'       => false,
			'cart'          => self::get_cart_data( $cart ),
			'fragments'     => self::build_cart_fragments(),
		);
	}

	/**
	 * Remove an item from WooCommerce cart without affecting product stock.
	 *
	 * @param string $cart_item_key Cart item key.
	 * @return array|\WP_Error
	 */
	public static function remove_item( string $cart_item_key ) {
		$cart = self::ensure_cart_ready();
		if ( is_wp_error( $cart ) ) {
			return $cart;
		}

		$cart_item_key = sanitize_text_field( $cart_item_key );
		if ( '' === $cart_item_key ) {
			return new \WP_Error(
				'despace_invalid_cart_key',
				__( 'Cart item key is required.', 'despace-core' ),
				array( 'status' => 400 )
			);
		}

		$cart_items = $cart->get_cart();
		if ( ! isset( $cart_items[ $cart_item_key ] ) ) {
			return new \WP_Error(
				'despace_cart_item_not_found',
				__( 'Cart item was not found or session has expired.', 'despace-core' ),
				array( 'status' => 404 )
			);
		}

		$removed = $cart->remove_cart_item( $cart_item_key );
		if ( ! $removed ) {
			return new \WP_Error(
				'despace_remove_failed',
				__( 'Unable to remove cart item.', 'despace-core' ),
				array( 'status' => 500 )
			);
		}

		$cart->calculate_totals();
		if ( $cart->is_empty() ) {
			$cart->empty_cart( false );
		}

		return array(
			'cart_item_key' => $cart_item_key,
			'cart'          => self::get_cart_data( $cart ),
			'fragments'     => self::build_cart_fragments(),
		);
	}

	/**
	 * Clean up invalid, trashed, or non-purchasable items loaded from session.
	 *
	 * @param mixed $cart Optional WC_Cart instance.
	 * @return bool True if cart was modified.
	 */
	public static function cleanup_invalid_cart_items( $cart = null ): bool {
		if ( null === $cart && function_exists( 'WC' ) && WC() ) {
			$cart = WC()->cart;
		}

		if ( ! $cart || ! is_object( $cart ) ) {
			return false;
		}

		$modified = false;
		$items    = $cart->get_cart();

		if ( empty( $items ) ) {
			if ( (float) $cart->get_total( 'edit' ) > 0 || (float) $cart->get_subtotal() > 0 ) {
				$cart->empty_cart( false );
				return true;
			}
			return false;
		}

		foreach ( $items as $key => $item ) {
			$product = self::resolve_cart_item_product( $item );
			if ( ! $product || ! self::is_product_available( $product ) || ! $product->is_purchasable() ) {
				$cart->remove_cart_item( $key );
				$modified = true;
			}
		}

		if ( $modified ) {
			$cart->calculate_totals();
			if ( $cart->is_empty() ) {
				$cart->empty_cart( false );
			}
		}

		return $modified;
	}

	/**
	 * Build normalized cart payload sourced strictly from WooCommerce.
	 *
	 * @param mixed $cart Optional WC_Cart instance.
	 * @return array
	 */
	public static function get_cart_data( $cart = null ): array {
		if ( null === $cart && function_exists( 'WC' ) && WC() ) {
			$cart = WC()->cart;
		}

		if ( ! $cart || ! is_object( $cart ) ) {
			return array(
				'items'         => array(),
				'item_count'    => 0,
				'subtotal'      => 0.0,
				'subtotal_html' => self::format_price( 0.0 ),
				'total'         => 0.0,
				'total_html'    => self::format_price( 0.0 ),
				'currency'      => self::get_store_currency(),
				'needs_shipping'=> false,
				'is_empty'      => true,
				'user_context'  => self::get_user_context(),
			);
		}

		$items_data = array();
		foreach ( $cart->get_cart() as $cart_item_key => $item ) {
			$product = self::resolve_cart_item_product( $item );
			if ( ! $product ) {
				continue;
			}

			$qty        = isset( $item['quantity'] ) ? (int) $item['quantity'] : 0;
			$unit_price = (float) $product->get_price();
			$line_sub   = isset( $item['line_subtotal'] ) ? (float) $item['line_subtotal'] : ( $unit_price * $qty );

			$max_qty = null;
			if ( method_exists( $product, 'managing_stock' ) && $product->managing_stock() && ! $product->backorders_allowed() ) {
				$max_qty = max( 0, (int) $product->get_stock_quantity() );
			}

			$unit_price_html = method_exists( $cart, 'get_product_price' )
				? $cart->get_product_price( $product )
				: self::format_price( $unit_price );
			$line_sub_html   = method_exists( $cart, 'get_product_subtotal' )
				? $cart->get_product_subtotal( $product, $qty )
				: self::format_price( $line_sub );

			$items_data[] = array(
				'key'                => (string) $cart_item_key,
				'product_id'         => isset( $item['product_id'] ) ? (int) $item['product_id'] : $product->get_id(),
				'variation_id'       => isset( $item['variation_id'] ) ? (int) $item['variation_id'] : 0,
				'name'               => $product->get_name(),
				'sku'                => $product->get_sku(),
				'permalink'          => method_exists( $product, 'get_permalink' ) ? $product->get_permalink() : '',
				'quantity'           => $qty,
				'unit_price'         => $unit_price,
				'unit_price_html'    => $unit_price_html,
				'line_subtotal'      => $line_sub,
				'line_subtotal_html' => $line_sub_html,
				'stock_status'       => method_exists( $product, 'get_stock_status' ) ? $product->get_stock_status() : ( $product->is_in_stock() ? 'instock' : 'outofstock' ),
				'in_stock'           => $product->is_in_stock(),
				'max_quantity'       => $max_qty,
				'attributes'         => isset( $item['variation'] ) && is_array( $item['variation'] ) ? $item['variation'] : array(),
			);
		}

		$subtotal = (float) $cart->get_subtotal();
		$total    = (float) $cart->get_total( 'edit' );

		return array(
			'items'          => $items_data,
			'item_count'     => (int) $cart->get_cart_contents_count(),
			'subtotal'       => $subtotal,
			'subtotal_html'  => $cart->get_cart_subtotal(),
			'total'          => $total,
			'total_html'     => $cart->get_cart_total(),
			'currency'       => self::get_store_currency(),
			'needs_shipping' => method_exists( $cart, 'needs_shipping' ) ? (bool) $cart->needs_shipping() : ! empty( $items_data ),
			'is_empty'       => (bool) $cart->is_empty(),
			'user_context'   => self::get_user_context(),
		);
	}

	/**
	 * Build WooCommerce cart fragments for header/drawer synchronization.
	 *
	 * @param array $fragments Existing fragments.
	 * @return array
	 */
	public static function build_cart_fragments( array $fragments = array() ): array {
		$data = self::get_cart_data();

		$fragments['.despace-cart-count'] = sprintf(
			'<span class="despace-cart-count" data-count="%d">%d</span>',
			$data['item_count'],
			$data['item_count']
		);
		$fragments['.despace-cart-subtotal'] = sprintf(
			'<span class="despace-cart-subtotal">%s</span>',
			$data['subtotal_html']
		);
		$fragments['.despace-cart-total'] = sprintf(
			'<span class="despace-cart-total">%s</span>',
			$data['total_html']
		);

		return $fragments;
	}

	/**
	 * Ensure WooCommerce session & cart objects are initialized (especially for guest AJAX requests).
	 *
	 * @return object|\WP_Error WC_Cart instance or WP_Error.
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
	 * Guarantee guest session cookie exists so guest cart items persist across requests.
	 */
	private static function ensure_guest_session_cookie(): void {
		if ( function_exists( 'is_user_logged_in' ) && is_user_logged_in() ) {
			return;
		}

		if ( function_exists( 'WC' ) && WC() && WC()->session ) {
			$session = WC()->session;
			$has_session = method_exists( $session, 'has_session' ) ? $session->has_session() : false;
			if ( ! $has_session && method_exists( $session, 'set_customer_session_cookie' ) ) {
				$session->set_customer_session_cookie( true );
			}
		}
	}

	/**
	 * Validate AJAX nonce in request payload.
	 *
	 * @param array $raw_input Request payload.
	 * @return array|null Error response array if invalid, null if valid.
	 */
	private static function validate_request_nonce( array $raw_input ): ?array {
		$nonce = '';
		if ( isset( $raw_input['nonce'] ) ) {
			$nonce = sanitize_text_field( (string) $raw_input['nonce'] );
		} elseif ( isset( $raw_input['_ajax_nonce'] ) ) {
			$nonce = sanitize_text_field( (string) $raw_input['_ajax_nonce'] );
		}

		$valid = '' !== $nonce && function_exists( 'wp_verify_nonce' ) && (bool) wp_verify_nonce( $nonce, self::NONCE_ACTION );

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
	 * Validate raw quantity input strictly (integer only, no floats/strings/negative).
	 *
	 * @param mixed $raw_qty    Raw quantity value.
	 * @param bool  $allow_zero Whether zero is accepted (allowed for update_quantity).
	 * @return int|\WP_Error
	 */
	private static function parse_integer_quantity( $raw_qty, bool $allow_zero ) {
		if ( is_bool( $raw_qty ) || is_array( $raw_qty ) || is_object( $raw_qty ) || null === $raw_qty ) {
			return new \WP_Error(
				'despace_invalid_quantity',
				__( 'Quantity must be a valid integer.', 'despace-core' ),
				array( 'status' => 400 )
			);
		}

		if ( is_string( $raw_qty ) ) {
			$trimmed = trim( $raw_qty );
			if ( '' === $trimmed || ! preg_match( '/^-?\d+$/', $trimmed ) ) {
				return new \WP_Error(
					'despace_invalid_quantity',
					__( 'Quantity must be a valid integer.', 'despace-core' ),
					array( 'status' => 400 )
				);
			}
			$qty = (int) $trimmed;
		} elseif ( is_int( $raw_qty ) ) {
			$qty = $raw_qty;
		} elseif ( is_float( $raw_qty ) && floor( $raw_qty ) === $raw_qty ) {
			$qty = (int) $raw_qty;
		} else {
			return new \WP_Error(
				'despace_invalid_quantity',
				__( 'Quantity must be a valid integer.', 'despace-core' ),
				array( 'status' => 400 )
			);
		}

		$min = $allow_zero ? 0 : 1;
		if ( $qty < $min || $qty > self::MAX_QUANTITY ) {
			return new \WP_Error(
				'despace_invalid_quantity',
				$allow_zero
					? __( 'Quantity must be between 0 and 9999.', 'despace-core' )
					: __( 'Quantity must be at least 1.', 'despace-core' ),
				array( 'status' => 400 )
			);
		}

		return $qty;
	}

	/**
	 * Check product existence and published status.
	 *
	 * @param object $product Product or variation instance.
	 * @return bool
	 */
	private static function is_product_available( object $product ): bool {
		if ( method_exists( $product, 'exists' ) && ! $product->exists() ) {
			return false;
		}

		if ( method_exists( $product, 'get_status' ) ) {
			$status = $product->get_status();
			if ( 'publish' !== $status ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Validate that a variation object belongs to the parent variable product and is published.
	 *
	 * @param mixed $variation Potential WC_Product_Variation.
	 * @param int   $parent_id Expected parent variable product ID.
	 * @return bool
	 */
	private static function is_valid_variation_for_parent( $variation, int $parent_id ): bool {
		if ( ! $variation || ! is_object( $variation ) || ! $variation->is_type( 'variation' ) ) {
			return false;
		}

		if ( ! self::is_product_available( $variation ) ) {
			return false;
		}

		if ( ! method_exists( $variation, 'get_parent_id' ) || (int) $variation->get_parent_id() !== $parent_id ) {
			return false;
		}

		return true;
	}

	/**
	 * Resolve and validate variation attributes against the selected variation & parent.
	 *
	 * @param object $variation        Variation product object.
	 * @param object $parent           Parent variable product object.
	 * @param array  $input_attributes Sanitized input attributes map.
	 * @return array|\WP_Error
	 */
	private static function resolve_variation_attributes( object $variation, object $parent, array $input_attributes ) {
		$expected_attrs = method_exists( $variation, 'get_variation_attributes' )
			? (array) $variation->get_variation_attributes()
			: array();

		$parent_attrs       = method_exists( $parent, 'get_variation_attributes' )
			? (array) $parent->get_variation_attributes()
			: array();
		$parent_attrs_lower = array_change_key_case( $parent_attrs, CASE_LOWER );

		$resolved = array();

		foreach ( $expected_attrs as $attr_key => $expected_value ) {
			$normalized_key = 0 === strpos( $attr_key, 'attribute_' ) ? $attr_key : 'attribute_' . $attr_key;
			$provided_value = $input_attributes[ $normalized_key ] ?? ( $input_attributes[ $attr_key ] ?? '' );

			if ( '' !== (string) $expected_value ) {
				// If caller supplied an attribute value, it must match the variation's fixed attribute.
				if ( '' !== $provided_value && (string) $provided_value !== (string) $expected_value ) {
					return new \WP_Error(
						'despace_invalid_variation_attributes',
						__( 'Provided variation attributes do not match the selected variation.', 'despace-core' ),
						array( 'status' => 400 )
					);
				}
				$resolved[ $normalized_key ] = (string) $expected_value;
			} else {
				// "Any ..." wildcard attribute on variation: require valid value from parent options (case-insensitive key match).
				$raw_tax_key    = str_replace( 'attribute_', '', $normalized_key );
				$allowed_values = $parent_attrs[ $raw_tax_key ]
					?? $parent_attrs[ $normalized_key ]
					?? $parent_attrs_lower[ strtolower( $raw_tax_key ) ]
					?? $parent_attrs_lower[ strtolower( $normalized_key ) ]
					?? array();

				if ( '' === $provided_value ) {
					return new \WP_Error(
						'despace_missing_variation_attribute',
						__( 'Please select all required product options.', 'despace-core' ),
						array( 'status' => 400 )
					);
				}

				if ( ! empty( $allowed_values ) && ! in_array( $provided_value, array_map( 'strval', $allowed_values ), true ) ) {
					return new \WP_Error(
						'despace_invalid_variation_attributes',
						__( 'Selected option is not valid for this product.', 'despace-core' ),
						array( 'status' => 400 )
					);
				}

				$resolved[ $normalized_key ] = $provided_value;
			}
		}

		ksort( $resolved );
		return $resolved;
	}

	/**
	 * Validate requested quantity against WooCommerce managed stock (read-only).
	 *
	 * @param object $product       Product or variation instance.
	 * @param int    $requested_qty Total desired quantity in cart.
	 * @return true|\WP_Error
	 */
	private static function validate_requested_stock( object $product, int $requested_qty ) {
		if ( method_exists( $product, 'managing_stock' ) && $product->managing_stock() ) {
			$backorders = method_exists( $product, 'backorders_allowed' ) ? $product->backorders_allowed() : false;
			if ( ! $backorders ) {
				$stock_qty = $product->get_stock_quantity();
				if ( null !== $stock_qty && $requested_qty > (int) $stock_qty ) {
					return new \WP_Error(
						'despace_insufficient_stock',
						sprintf(
							/* translators: %d: available stock quantity */
							__( 'Only %d unit(s) available in stock.', 'despace-core' ),
							max( 0, (int) $stock_qty )
						),
						array(
							'status'          => 400,
							'available_stock' => max( 0, (int) $stock_qty ),
						)
					);
				}
			}
		}

		return true;
	}

	/**
	 * Resolve WC_Product for an item stored inside WC()->cart.
	 *
	 * @param array $item Cart item array.
	 * @return object|null
	 */
	private static function resolve_cart_item_product( array $item ): ?object {
		if ( ! empty( $item['data'] ) && is_object( $item['data'] ) ) {
			$product = $item['data'];
			// Re-check live DB status via wc_get_product when available.
			if ( function_exists( 'wc_get_product' ) ) {
				$lookup_id = ! empty( $item['variation_id'] ) ? (int) $item['variation_id'] : ( ! empty( $item['product_id'] ) ? (int) $item['product_id'] : $product->get_id() );
				$fresh     = wc_get_product( $lookup_id );
				return is_object( $fresh ) ? $fresh : null;
			}
			return $product;
		}

		if ( function_exists( 'wc_get_product' ) ) {
			$lookup_id = ! empty( $item['variation_id'] ) ? (int) $item['variation_id'] : (int) ( $item['product_id'] ?? 0 );
			if ( $lookup_id > 0 ) {
				$product = wc_get_product( $lookup_id );
				return is_object( $product ) ? $product : null;
			}
		}

		return null;
	}

	/**
	 * Count existing cart quantity sharing stock with target entity.
	 *
	 * @param object $cart           WC_Cart instance.
	 * @param int    $product_id     Parent/simple product ID.
	 * @param int    $variation_id   Variation ID.
	 * @param array  $attributes     Variation attributes.
	 * @param object $target_product Target WC_Product.
	 * @return int
	 */
	private static function get_cart_quantity_for_entity( object $cart, int $product_id, int $variation_id, array $attributes, object $target_product ): int {
		$stock_managed_id = method_exists( $target_product, 'get_stock_managed_by_id' )
			? (int) $target_product->get_stock_managed_by_id()
			: $target_product->get_id();

		$qty = 0;
		foreach ( $cart->get_cart() as $item ) {
			$item_product = self::resolve_cart_item_product( $item );
			if ( $item_product && method_exists( $item_product, 'get_stock_managed_by_id' ) ) {
				if ( (int) $item_product->get_stock_managed_by_id() === $stock_managed_id ) {
					$qty += (int) ( $item['quantity'] ?? 0 );
				}
			} elseif ( (int) ( $item['product_id'] ?? 0 ) === $product_id && (int) ( $item['variation_id'] ?? 0 ) === $variation_id ) {
				$item_attrs = isset( $item['variation'] ) && is_array( $item['variation'] ) ? $item['variation'] : array();
				ksort( $item_attrs );
				if ( $item_attrs === $attributes ) {
					$qty += (int) ( $item['quantity'] ?? 0 );
				}
			}
		}

		return $qty;
	}

	/**
	 * Count quantity in OTHER cart rows sharing the same stock ID when updating a specific row.
	 *
	 * @param object $cart           WC_Cart instance.
	 * @param string $exclude_key    Cart item key being updated.
	 * @param object $target_product Target WC_Product.
	 * @return int
	 */
	private static function get_other_cart_quantity_for_stock( object $cart, string $exclude_key, object $target_product ): int {
		$stock_managed_id = method_exists( $target_product, 'get_stock_managed_by_id' )
			? (int) $target_product->get_stock_managed_by_id()
			: $target_product->get_id();

		$qty = 0;
		foreach ( $cart->get_cart() as $key => $item ) {
			if ( $key === $exclude_key ) {
				continue;
			}
			$item_product = self::resolve_cart_item_product( $item );
			if ( $item_product ) {
				$item_stock_id = method_exists( $item_product, 'get_stock_managed_by_id' )
					? (int) $item_product->get_stock_managed_by_id()
					: $item_product->get_id();
				if ( $item_stock_id === $stock_managed_id ) {
					$qty += (int) ( $item['quantity'] ?? 0 );
				}
			}
		}

		return $qty;
	}

	/**
	 * Extract and sanitize attributes from request payload (`attributes` array or `attribute_*` keys).
	 *
	 * @param array $raw_input Request array.
	 * @return array
	 */
	private static function extract_sanitized_attributes( array $raw_input ): array {
		$attrs = array();

		if ( isset( $raw_input['attributes'] ) && is_array( $raw_input['attributes'] ) ) {
			foreach ( $raw_input['attributes'] as $key => $value ) {
				if ( is_string( $key ) && ( is_string( $value ) || is_numeric( $value ) ) ) {
					$clean_key           = sanitize_text_field( $key );
					$normalized_key      = 0 === strpos( $clean_key, 'attribute_' ) ? $clean_key : 'attribute_' . $clean_key;
					$attrs[ $normalized_key ] = sanitize_text_field( (string) $value );
				}
			}
		}

		foreach ( $raw_input as $key => $value ) {
			if ( is_string( $key ) && 0 === strpos( $key, 'attribute_' ) && ( is_string( $value ) || is_numeric( $value ) ) ) {
				$clean_key           = sanitize_text_field( $key );
				$attrs[ $clean_key ] = sanitize_text_field( (string) $value );
			}
		}

		ksort( $attrs );
		return $attrs;
	}

	/**
	 * Normalize an attribute map passed directly in PHP.
	 *
	 * @param array $attributes Input attributes.
	 * @return array
	 */
	private static function normalize_attribute_map( array $attributes ): array {
		$normalized = array();
		foreach ( $attributes as $key => $value ) {
			if ( ! is_string( $key ) || ( ! is_string( $value ) && ! is_numeric( $value ) ) ) {
				continue;
			}
			$clean_key                = sanitize_text_field( $key );
			$prefixed_key             = 0 === strpos( $clean_key, 'attribute_' ) ? $clean_key : 'attribute_' . $clean_key;
			$normalized[ $prefixed_key ] = sanitize_text_field( (string) $value );
		}
		ksort( $normalized );
		return $normalized;
	}

	/**
	 * Format result into unified response envelope.
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
					'message' => $result->get_error_message(),
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
	 * Read unslashed $_POST array safely.
	 *
	 * @return array
	 */
	private static function get_unslashed_post(): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is validated immediately inside handle_*_request().
		$raw = $_POST ?? array();
		if ( function_exists( 'wp_unslash' ) ) {
			$raw = wp_unslash( $raw );
		}
		return is_array( $raw ) ? $raw : array();
	}

	/**
	 * Read unslashed POST or GET request array for read-only AJAX endpoints.
	 *
	 * @return array
	 */
	private static function get_unslashed_request(): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is validated immediately inside handle_get_request().
		$raw = ! empty( $_POST ) ? $_POST : ( $_GET ?? array() );
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
	 * Get store currency code from WooCommerce.
	 *
	 * @return string
	 */
	private static function get_store_currency(): string {
		if ( function_exists( 'get_woocommerce_currency' ) ) {
			return (string) get_woocommerce_currency();
		}

		return 'VND';
	}

	/**
	 * Determine current session user context.
	 *
	 * @return string
	 */
	private static function get_user_context(): string {
		if ( function_exists( 'is_user_logged_in' ) && is_user_logged_in() ) {
			return 'customer';
		}

		return 'guest';
	}
}

