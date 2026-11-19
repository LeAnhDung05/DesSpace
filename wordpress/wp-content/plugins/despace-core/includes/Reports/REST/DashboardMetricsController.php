<?php
/**
 * REST API Controller for DeSpace Dashboard Metrics & Reports.
 *
 * Provides aggregated e-commerce analytics compliant with HPOS via WooCommerce CRUD APIs.
 *
 * @package DeSpace\Reports\REST
 */

namespace DeSpace\Reports\REST;

use DeSpace\Security\RoleManager;

defined( 'ABSPATH' ) || exit;

/**
 * Class DashboardMetricsController
 */
class DashboardMetricsController {

	/**
	 * Route namespace.
	 */
	public const NAMESPACE = 'despace/v1';

	/**
	 * Primary route base.
	 */
	public const REST_BASE = 'dashboard/metrics';

	/**
	 * Secondary alias route base per API contract.
	 */
	public const ALIAS_BASE = 'reports/overview';

	/**
	 * Register REST API routes.
	 */
	public static function register_routes(): void {
		$endpoint_args = array(
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_metrics' ),
				'permission_callback' => array( __CLASS__, 'permissions_check' ),
				'args'                => self::get_collection_params(),
			),
		);

		register_rest_route( self::NAMESPACE, '/' . self::REST_BASE, $endpoint_args );
		register_rest_route( self::NAMESPACE, '/' . self::ALIAS_BASE, $endpoint_args );
	}

	/**
	 * Verify permissions for accessing dashboard metrics.
	 *
	 * Requires view_despace_reports capability or WooCommerce store management rights.
	 *
	 * @param \WP_REST_Request|null $request
	 * @return true|\WP_Error
	 */
	public static function permissions_check( $request = null ) {
		return RoleManager::rest_check_reports_permission( $request );
	}

	/**
	 * Retrieve dashboard metrics aggregated within the requested date range.
	 *
	 * Uses WooCommerce wc_get_orders() exclusively for full HPOS compatibility.
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function get_metrics( $request ) {
		// 1. Resolve and sanitize date parameters.
		$from_date = $request->get_param( 'from_date' ) ?: $request->get_param( 'date_from' );
		$to_date   = $request->get_param( 'to_date' ) ?: $request->get_param( 'date_to' );

		$from_date = $from_date ? sanitize_text_field( wp_unslash( $from_date ) ) : gmdate( 'Y-m-d', strtotime( '-30 days' ) );
		$to_date   = $to_date ? sanitize_text_field( wp_unslash( $to_date ) ) : gmdate( 'Y-m-d' );

		// 2. Validate calendar date formats.
		if ( ! self::is_valid_date( $from_date ) ) {
			return new \WP_Error(
				'despace_invalid_from_date',
				__( 'Tham số from_date không hợp lệ. Định dạng yêu cầu là YYYY-MM-DD.', 'despace-core' ),
				array( 'status' => 400 )
			);
		}

		if ( ! self::is_valid_date( $to_date ) ) {
			return new \WP_Error(
				'despace_invalid_to_date',
				__( 'Tham số to_date không hợp lệ. Định dạng yêu cầu là YYYY-MM-DD.', 'despace-core' ),
				array( 'status' => 400 )
			);
		}

		if ( $from_date > $to_date ) {
			return new \WP_Error(
				'despace_invalid_date_range',
				__( 'from_date không được lớn hơn to_date.', 'despace-core' ),
				array( 'status' => 400 )
			);
		}

		// 3. Fetch orders via WooCommerce CRUD API (HPOS compliant).
		$start_timestamp = strtotime( $from_date . ' 00:00:00' );
		$end_timestamp   = strtotime( $to_date . ' 23:59:59' );

		$orders = self::fetch_orders_by_date_range( $start_timestamp, $end_timestamp );

		// 4. Aggregate metrics.
		$total_orders        = count( $orders );
		$valid_revenue       = 0.0;
		$valid_orders_count  = 0;
		$orders_by_status    = array(
			'completed'  => 0,
			'processing' => 0,
			'on-hold'    => 0,
			'pending'    => 0,
			'cancelled'  => 0,
			'refunded'   => 0,
			'failed'     => 0,
		);
		$products_aggregated = array();

		foreach ( $orders as $order ) {
			if ( ! is_object( $order ) || ! method_exists( $order, 'get_status' ) ) {
				continue;
			}

			$raw_status   = $order->get_status();
			$clean_status = strtolower( (string) preg_replace( '/^wc-/', '', $raw_status ) );

			if ( ! isset( $orders_by_status[ $clean_status ] ) ) {
				$orders_by_status[ $clean_status ] = 0;
			}
			$orders_by_status[ $clean_status ]++;

			// Valid revenue criteria: ONLY 'processing' and 'completed'.
			// Explicitly excludes 'on-hold' (unreconciled bank transfer), 'pending', 'cancelled', 'refunded'.
			$is_valid_revenue_order = in_array( $clean_status, array( 'processing', 'completed' ), true );

			if ( $is_valid_revenue_order ) {
				$valid_orders_count++;

				$order_total    = (float) $order->get_total();
				$refunded_total = method_exists( $order, 'get_total_refunded' ) ? (float) $order->get_total_refunded() : 0.0;
				$net_revenue    = max( 0.0, $order_total - $refunded_total );
				$valid_revenue += $net_revenue;

				// Aggregate line items for Top 10 Best Sellers.
				if ( method_exists( $order, 'get_items' ) ) {
					$items = $order->get_items( 'line_item' );
					foreach ( (array) $items as $item ) {
						if ( ! is_object( $item ) || ! method_exists( $item, 'get_product_id' ) ) {
							continue;
						}

						$product_id = (int) $item->get_product_id();
						if ( $product_id <= 0 ) {
							continue;
						}

						$qty   = method_exists( $item, 'get_quantity' ) ? (int) $item->get_quantity() : 0;
						$total = method_exists( $item, 'get_total' ) ? (float) $item->get_total() : 0.0;

						if ( ! isset( $products_aggregated[ $product_id ] ) ) {
							$products_aggregated[ $product_id ] = array(
								'product_id'    => $product_id,
								'quantity_sold' => 0,
								'total_revenue' => 0.0,
							);
						}

						$products_aggregated[ $product_id ]['quantity_sold'] += $qty;
						$products_aggregated[ $product_id ]['total_revenue'] += $total;
					}
				}
			}
		}

		// 5. Rank and build Top 10 Best Sellers.
		usort( $products_aggregated, function ( $a, $b ) {
			if ( $a['quantity_sold'] === $b['quantity_sold'] ) {
				return $b['total_revenue'] <=> $a['total_revenue'];
			}
			return $b['quantity_sold'] <=> $a['quantity_sold'];
		} );

		$top_10_slice = array_slice( $products_aggregated, 0, 10 );
		$top_products = self::enrich_top_products( $top_10_slice );

		// 6. Count products with 3D models.
		$products_with_3d_count = self::count_products_with_3d();
		$total_products_count   = self::count_total_products();

		// 7. Calculate Average Order Value (AOV).
		$average_order_value = $valid_orders_count > 0 ? ( $valid_revenue / $valid_orders_count ) : 0.0;

		$response_payload = array(
			'success'          => true,
			'date_range'       => array(
				'from_date' => $from_date,
				'to_date'   => $to_date,
			),
			'metrics'          => array(
				'valid_revenue'                 => round( $valid_revenue, 2 ),
				'valid_revenue_formatted'       => self::format_price( $valid_revenue ),
				'total_orders'                  => $total_orders,
				'valid_orders_count'            => $valid_orders_count,
				'average_order_value'           => round( $average_order_value, 2 ),
				'average_order_value_formatted' => self::format_price( $average_order_value ),
				'products_with_3d_count'        => $products_with_3d_count,
				'total_products_count'          => $total_products_count,
				'percentage_3d'                 => $total_products_count > 0 ? round( ( $products_with_3d_count / $total_products_count ) * 100, 1 ) : 0.0,
			),
			'orders_by_status' => $orders_by_status,
			'top_products'     => $top_products,
		);

		return rest_ensure_response( $response_payload );
	}

	/**
	 * Query WooCommerce orders using wc_get_orders().
	 *
	 * HPOS Compatible: preserves compatibility with both Custom Order Tables
	 * and traditional post/postmeta storage without direct SQL.
	 *
	 * @param int $start_timestamp
	 * @param int $end_timestamp
	 * @return \WC_Order[]
	 */
	public static function fetch_orders_by_date_range( int $start_timestamp, int $end_timestamp ): array {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return array();
		}

		$date_query = $start_timestamp . '...' . $end_timestamp;

		return wc_get_orders( array(
			'limit'        => -1,
			'date_created' => $date_query,
			'type'         => 'shop_order',
			'orderby'      => 'date',
			'order'        => 'DESC',
		) );
	}

	/**
	 * Enrich top products slice with WooCommerce metadata and 3D availability.
	 *
	 * @param array $top_slice
	 * @return array
	 */
	public static function enrich_top_products( array $top_slice ): array {
		$enriched = array();

		foreach ( $top_slice as $item ) {
			$product_id = (int) $item['product_id'];
			$product    = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;

			$name      = $product ? $product->get_name() : sprintf( __( 'Sản phẩm #%d', 'despace-core' ), $product_id );
			$sku       = $product ? $product->get_sku() : '';
			$permalink = $product ? get_permalink( $product->get_id() ) : '';

			$image_url = '';
			if ( $product && $product->get_image_id() ) {
				$image_url = wp_get_attachment_image_url( $product->get_image_id(), 'thumbnail' ) ?: '';
			}
			if ( empty( $image_url ) && function_exists( 'wc_placeholder_img_src' ) ) {
				$image_url = wc_placeholder_img_src();
			}

			// Check for 3D model attachment ID.
			$glb_attachment_id = (int) get_post_meta( $product_id, '_despace_glb_attachment_id', true );
			$has_3d            = $glb_attachment_id > 0;

			$enriched[] = array(
				'product_id'              => $product_id,
				'name'                    => $name,
				'sku'                     => $sku ?: '—',
				'permalink'               => $permalink,
				'image_url'               => $image_url,
				'has_3d'                  => $has_3d,
				'quantity_sold'           => (int) $item['quantity_sold'],
				'total_revenue'           => round( (float) $item['total_revenue'], 2 ),
				'total_revenue_formatted' => self::format_price( (float) $item['total_revenue'] ),
			);
		}

		return $enriched;
	}

	/**
	 * Count published products with a valid 3D model (_despace_glb_attachment_id).
	 *
	 * @return int
	 */
	public static function count_products_with_3d(): int {
		if ( ! function_exists( 'get_posts' ) ) {
			return 0;
		}

		$ids = get_posts( array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'     => '_despace_glb_attachment_id',
					'value'   => 0,
					'compare' => '>',
					'type'    => 'NUMERIC',
				),
			),
		) );

		return is_array( $ids ) ? count( $ids ) : 0;
	}

	/**
	 * Count total published WooCommerce products.
	 *
	 * @return int
	 */
	public static function count_total_products(): int {
		if ( ! function_exists( 'wp_count_posts' ) ) {
			return 0;
		}

		$counts = wp_count_posts( 'product' );
		return isset( $counts->publish ) ? (int) $counts->publish : 0;
	}

	/**
	 * Format currency amount cleanly.
	 *
	 * @param float $amount
	 * @return string
	 */
	public static function format_price( float $amount ): string {
		if ( function_exists( 'wc_price' ) ) {
			return html_entity_decode( wp_strip_all_tags( wc_price( $amount ) ) );
		}

		return number_format( $amount, 0, ',', '.' ) . ' ₫';
	}

	/**
	 * Parameter definitions for collection route.
	 *
	 * @return array
	 */
	public static function get_collection_params(): array {
		return array(
			'from_date' => array(
				'description'       => __( 'Ngày bắt đầu thống kê (YYYY-MM-DD).', 'despace-core' ),
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => 'rest_validate_request_arg',
			),
			'to_date'   => array(
				'description'       => __( 'Ngày kết thúc thống kê (YYYY-MM-DD).', 'despace-core' ),
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => 'rest_validate_request_arg',
			),
			'date_from' => array(
				'sanitize_callback' => 'sanitize_text_field',
			),
			'date_to'   => array(
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
	}

	/**
	 * Validate Gregorian date string in YYYY-MM-DD format.
	 *
	 * @param string $date
	 * @return bool
	 */
	public static function is_valid_date( string $date ): bool {
		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $date, $matches ) ) {
			return false;
		}

		$year  = (int) $matches[1];
		$month = (int) $matches[2];
		$day   = (int) $matches[3];

		return checkdate( $month, $day, $year );
	}
}

