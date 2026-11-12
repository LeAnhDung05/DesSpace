<?php
/**
 * Customer Account Dashboard & Order History Data Presenter (M3 Phase 4 / Issue #33).
 *
 * Supplies HPOS-safe dashboard statistics, paginated customer order history, order detail
 * inspection, native My Account endpoint URLs, and M2 frontend handoff contracts.
 * Strictly scopes all queries to `get_current_user_id()`, never links Guest orders by email,
 * never creates custom order/customer tables, and delegates presentation filtering to
 * `OrderTrackingPresenter` so `order_key`, `customer_id`, and private admin notes never leak.
 *
 * @package DeSpace\CheckoutOrders\Account
 */

namespace DeSpace\CheckoutOrders\Account;

use DeSpace\CheckoutOrders\Orders\GuestOrderTracker;
use DeSpace\CheckoutOrders\Orders\OrderAccessGuard;
use DeSpace\CheckoutOrders\Orders\OrderTrackingPresenter;

defined( 'ABSPATH' ) || exit;

/**
 * Class AccountDataPresenter
 */
class AccountDataPresenter {

	/**
	 * WooCommerce `[woocommerce_my_account]` native endpoints retained as single source of truth.
	 *
	 * @var array<int, string>
	 */
	public const NATIVE_ENDPOINTS = array(
		'dashboard',
		'orders',
		'view-order',
		'edit-account',
		'edit-address',
		'payment-methods',
		'downloads',
		'customer-logout',
	);

	/**
	 * Prevents duplicate hook registration.
	 *
	 * @var bool
	 */
	private static bool $hooks_registered = false;

	/**
	 * Register M2 frontend handoff config extension for Customer Account.
	 */
	public static function init(): void {
		if ( self::$hooks_registered ) {
			return;
		}

		self::$hooks_registered = true;

		add_filter( 'despace_m3_cart_frontend_config', array( __CLASS__, 'extend_frontend_config' ), 70, 1 );
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
	 * Expose non-PII My Account shortcode, endpoints, and capability hints for M2 theme UI.
	 *
	 * @param array $config Existing M3 frontend configuration.
	 * @return array
	 */
	public static function extend_frontend_config( array $config ): array {
		$myaccount_url = function_exists( 'wc_get_page_permalink' ) ? (string) wc_get_page_permalink( 'myaccount' ) : '/my-account/';

		$config['account'] = array(
			'shortcode'            => 'woocommerce_my_account',
			'myaccount_url'        => $myaccount_url ?: '/my-account/',
			'native_endpoints'     => self::NATIVE_ENDPOINTS,
			'registration_enabled' => AccountProfileHandler::is_registration_enabled(),
			'logout_url'           => self::get_logout_url(),
			'payment_methods_url'  => function_exists( 'wc_get_account_endpoint_url' ) ? (string) wc_get_account_endpoint_url( 'payment-methods' ) : '/my-account/payment-methods/',
			'guest_tracking'       => array(
				'shortcode' => 'despace_order_tracking',
				'action'    => 'despace_track_order',
			),
		);

		return $config;
	}

	/**
	 * Build authenticated customer dashboard statistics and recent orders strictly from live WooCommerce data.
	 *
	 * Never links Guest orders (`customer_id === 0`) or another customer's orders into account stats.
	 *
	 * @param int $recent_limit Maximum number of recent orders to return (1..20).
	 * @return array|\WP_Error Dashboard data or WP_Error when unauthenticated.
	 */
	public static function get_dashboard_summary( int $recent_limit = 5 ) {
		$current_user_id = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
		if ( $current_user_id <= 0 ) {
			return new \WP_Error(
				'despace_authentication_required',
				__( 'Vui lòng đăng nhập để xem trang tài khoản.', 'despace-core' ),
				array( 'status' => 401 )
			);
		}

		$profile = AccountProfileHandler::get_profile_data( $current_user_id );
		if ( is_wp_error( $profile ) ) {
			return $profile;
		}

		$billing_addr  = AccountAddressHandler::get_customer_address( 'billing', $current_user_id );
		$shipping_addr = AccountAddressHandler::get_customer_address( 'shipping', $current_user_id );

		$bounded_recent     = max( 1, min( 20, $recent_limit ) );
		$recent_page        = self::fetch_paginated_owned_orders( $current_user_id, 1, $bounded_recent );
		$recent_orders      = array();
		foreach ( $recent_page['orders'] as $order ) {
			$recent_orders[] = OrderTrackingPresenter::present_order( $order );
		}

		$total_orders       = $recent_page['total'];
		$pending_or_on_hold = self::count_owned_orders_by_statuses( $current_user_id, array( 'pending', 'on-hold' ) );
		$processing_count   = self::count_owned_orders_by_statuses( $current_user_id, array( 'processing' ) );
		$completed_count    = self::count_owned_orders_by_statuses( $current_user_id, array( 'completed' ) );

		$greeting_name = '' !== $profile['display_name']
			? $profile['display_name']
			: trim( $profile['last_name'] . ' ' . $profile['first_name'] );

		return array(
			'greeting'        => '' !== $greeting_name ? sprintf( __( 'Xin chào, %s', 'despace-core' ), $greeting_name ) : __( 'Xin chào Quý khách', 'despace-core' ),
			'profile'         => $profile,
			'stats'           => array(
				'total_orders'           => $total_orders,
				'pending_on_hold_orders' => $pending_or_on_hold,
				'processing_orders'      => $processing_count,
				'completed_orders'       => $completed_count,
			),
			'recent_orders'   => $recent_orders,
			'addresses'       => array(
				'billing'  => is_wp_error( $billing_addr ) ? array() : $billing_addr,
				'shipping' => is_wp_error( $shipping_addr ) ? array() : $shipping_addr,
			),
			'payment_methods' => self::get_payment_methods_status(),
			'logout_url'      => self::get_logout_url(),
		);
	}

	/**
	 * Fetch paginated order history owned strictly by the currently logged-in customer via HPOS pagination.
	 *
	 * Uses `wc_get_orders(['paginate' => true])` so only the requested page of orders is loaded
	 * into PHP memory regardless of total order count (> 100 orders supported accurately).
	 *
	 * @param int $page     1-based page number.
	 * @param int $per_page Items per page (1..50).
	 * @return array|\WP_Error Paginated list of sanitized orders or WP_Error.
	 */
	public static function get_customer_order_history( int $page = 1, int $per_page = 10 ) {
		$current_user_id = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
		if ( $current_user_id <= 0 ) {
			return new \WP_Error(
				'despace_authentication_required',
				__( 'Vui lòng đăng nhập để xem lịch sử đơn hàng.', 'despace-core' ),
				array( 'status' => 401 )
			);
		}

		$page      = max( 1, $page );
		$limit     = max( 1, min( 50, $per_page ) );
		$paginated = self::fetch_paginated_owned_orders( $current_user_id, $page, $limit );

		$items = array();
		foreach ( $paginated['orders'] as $ord ) {
			$items[] = OrderTrackingPresenter::present_order( $ord );
		}

		return array(
			'items'         => $items,
			'page'          => $page,
			'limit'         => $limit,
			'per_page'      => $limit,
			'total'         => $paginated['total'],
			'total_items'   => $paginated['total'],
			'max_num_pages' => $paginated['max_num_pages'],
			'total_pages'   => $paginated['max_num_pages'],
		);
	}

	/**
	 * Read single order detail for an authenticated customer with strict ownership & HPOS guard.
	 *
	 * Never allows viewing another customer's order or adopting Guest orders (`customer_id === 0`).
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return array|\WP_Error Whitelisted order summary or WP_Error.
	 */
	public static function get_customer_order_detail( int $order_id ) {
		$current_user_id = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
		if ( $current_user_id <= 0 ) {
			return new \WP_Error(
				'despace_authentication_required',
				__( 'Vui lòng đăng nhập để xem chi tiết đơn hàng.', 'despace-core' ),
				array( 'status' => 401 )
			);
		}

		if ( ! function_exists( 'wc_get_order' ) ) {
			return new \WP_Error( 'despace_woocommerce_unavailable', __( 'WooCommerce is not available.', 'despace-core' ) );
		}

		$order = wc_get_order( absint( $order_id ) );
		if ( ! $order || ! is_object( $order ) ) {
			return OrderAccessGuard::generic_access_error();
		}

		$owner_id = method_exists( $order, 'get_customer_id' ) ? (int) $order->get_customer_id() : 0;
		if ( $owner_id <= 0 || $owner_id !== $current_user_id ) {
			return OrderAccessGuard::generic_access_error();
		}

		return OrderTrackingPresenter::present_order( $order );
	}

	/**
	 * Report native `/my-account/payment-methods/` capability honestly without storing cards.
	 *
	 * @return array
	 */
	public static function get_payment_methods_status(): array {
		$endpoint_url = function_exists( 'wc_get_account_endpoint_url' )
			? (string) wc_get_account_endpoint_url( 'payment-methods' )
			: '/my-account/payment-methods/';

		$supports_tokens = false;
		if ( function_exists( 'WC' ) && WC() ) {
			$pg = method_exists( WC(), 'payment_gateways' ) ? WC()->payment_gateways() : null;
			if ( is_object( $pg ) && method_exists( $pg, 'get_available_payment_gateways' ) ) {
				foreach ( (array) $pg->get_available_payment_gateways() as $gw ) {
					if ( is_object( $gw ) && method_exists( $gw, 'supports' ) && $gw->supports( 'tokenization' ) ) {
						$supports_tokens = true;
						break;
					}
				}
			}
		}

		return array(
			'managed_by'             => 'woocommerce_native',
			'endpoint'               => 'payment-methods',
			'endpoint_url'           => $endpoint_url,
			'tokenization_supported' => $supports_tokens,
			'stored_cards_in_m3'     => false,
		);
	}

	/**
	 * Return WooCommerce native customer logout URL cleanly.
	 *
	 * @return string
	 */
	public static function get_logout_url(): string {
		if ( function_exists( 'wc_logout_url' ) ) {
			return (string) wc_logout_url();
		}
		if ( function_exists( 'wc_get_account_endpoint_url' ) ) {
			return (string) wc_get_account_endpoint_url( 'customer-logout' );
		}
		return '/my-account/customer-logout/';
	}

	/**
	 * Fetch a single page of WooCommerce orders via HPOS-compatible paginated `wc_get_orders()`.
	 *
	 * @param int $customer_id Authenticated user ID.
	 * @param int $page        1-based page number.
	 * @param int $limit       Items per page (1..50).
	 * @return array{orders: array<int, object>, total: int, max_num_pages: int}
	 */
	private static function fetch_paginated_owned_orders( int $customer_id, int $page, int $limit ): array {
		if ( $customer_id <= 0 || ! function_exists( 'wc_get_orders' ) ) {
			return array(
				'orders'        => array(),
				'total'         => 0,
				'max_num_pages' => 1,
			);
		}

		$page  = max( 1, $page );
		$limit = max( 1, min( 50, $limit ) );

		$raw = wc_get_orders( array(
			'customer_id' => $customer_id,
			'limit'       => $limit,
			'page'        => $page,
			'paginate'    => true,
			'orderby'     => 'date',
			'order'       => 'DESC',
			'return'      => 'objects',
		) );

		if ( is_object( $raw ) && isset( $raw->orders ) ) {
			$list          = (array) $raw->orders;
			$total         = max( 0, (int) ( $raw->total ?? count( $list ) ) );
			$max_num_pages = max( 1, (int) ( $raw->max_num_pages ?? (int) ceil( max( 1, $total ) / $limit ) ) );
		} else {
			$all           = is_array( $raw ) ? $raw : array();
			$total         = count( $all );
			$max_num_pages = max( 1, (int) ceil( max( 1, $total ) / $limit ) );
			$list          = array_slice( $all, ( $page - 1 ) * $limit, $limit );
		}

		$owned = array();
		foreach ( $list as $order ) {
			if ( ! is_object( $order ) || ! method_exists( $order, 'get_customer_id' ) ) {
				continue;
			}
			$owner = (int) $order->get_customer_id();
			// Strict boundary: never auto-attach Guest orders (owner === 0) or other customers' orders.
			if ( $owner > 0 && $owner === $customer_id ) {
				$owned[] = $order;
			}
		}

		return array(
			'orders'        => $owned,
			'total'         => $total,
			'max_num_pages' => $max_num_pages,
		);
	}

	/**
	 * Count owned orders for specified statuses via paginated IDs query (`limit => 1, paginate => true`)
	 * without hydrating order objects into PHP.
	 *
	 * @param int               $customer_id Authenticated user ID.
	 * @param array<int,string> $statuses    WooCommerce status slugs (without `wc-`).
	 * @return int
	 */
	private static function count_owned_orders_by_statuses( int $customer_id, array $statuses ): int {
		if ( $customer_id <= 0 || ! function_exists( 'wc_get_orders' ) ) {
			return 0;
		}

		$res = wc_get_orders( array(
			'customer_id' => $customer_id,
			'status'      => $statuses,
			'limit'       => 1,
			'page'        => 1,
			'paginate'    => true,
			'return'      => 'ids',
		) );

		if ( is_object( $res ) && isset( $res->total ) ) {
			return max( 0, (int) $res->total );
		}

		if ( is_array( $res ) ) {
			return count( $res );
		}

		return 0;
	}
}

