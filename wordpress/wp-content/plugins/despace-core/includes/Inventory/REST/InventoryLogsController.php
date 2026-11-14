<?php
/**
 * REST API Controller for Inventory Logs.
 *
 * @package DeSpace\Inventory\REST
 */

namespace DeSpace\Inventory\REST;

use DeSpace\Inventory\Database\InventoryLogInstaller;

defined( 'ABSPATH' ) || exit;

/**
 * Class InventoryLogsController
 *
 * Handles GET /wp-json/despace/v1/inventory/logs.
 */
class InventoryLogsController {

	/**
	 * Route namespace.
	 */
	const NAMESPACE = 'despace/v1';

	/**
	 * Route base.
	 */
	const REST_BASE = 'inventory/logs';

	/**
	 * Register REST routes.
	 */
	public static function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/' . self::REST_BASE,
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_items' ),
					'permission_callback' => array( __CLASS__, 'permissions_check' ),
					'args'                => self::get_collection_params(),
				),
			)
		);
	}

	/**
	 * Check user capabilities for accessing inventory logs.
	 *
	 * @param \WP_REST_Request $request
	 * @return bool|\WP_Error
	 */
	public static function permissions_check( $request ) {
		if ( current_user_can( 'view_despace_inventory_logs' ) || current_user_can( 'manage_woocommerce' ) ) {
			return true;
		}

		return new \WP_Error(
			'despace_rest_forbidden',
			__( 'You do not have permission to view inventory logs.', 'despace-core' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}

	/**
	 * Fetch inventory logs matching query parameters.
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response
	 */
	public static function get_items( $request ) {
		global $wpdb;

		$table_name = InventoryLogInstaller::get_table_name();

		$page     = absint( $request->get_param( 'page' ) );
		$per_page = absint( $request->get_param( 'per_page' ) );
		if ( $page < 1 ) {
			$page = 1;
		}
		if ( $per_page < 1 || $per_page > 100 ) {
			$per_page = 20;
		}

		$offset = ( $page - 1 ) * $per_page;

		$where_clauses = array( '1=1' );
		$where_values  = array();

		// Filters.
		$product_id = $request->get_param( 'product_id' );
		if ( ! empty( $product_id ) ) {
			$where_clauses[] = 'product_id = %d';
			$where_values[]  = absint( $product_id );
		}

		$variation_id = $request->get_param( 'variation_id' );
		if ( ! empty( $variation_id ) ) {
			$where_clauses[] = 'variation_id = %d';
			$where_values[]  = absint( $variation_id );
		}

		$order_id = $request->get_param( 'order_id' );
		if ( ! empty( $order_id ) ) {
			$where_clauses[] = 'order_id = %d';
			$where_values[]  = absint( $order_id );
		}

		$event_type = $request->get_param( 'event_type' );
		if ( ! empty( $event_type ) ) {
			$where_clauses[] = 'event_type = %s';
			$where_values[]  = sanitize_key( $event_type );
		}

		$date_from = $request->get_param( 'date_from' );
		if ( ! empty( $date_from ) ) {
			if ( ! self::is_valid_date( $date_from ) ) {
				return new \WP_Error(
					'despace_invalid_date_format',
					__( 'Invalid date_from format. Expected YYYY-MM-DD.', 'despace-core' ),
					array( 'status' => 400 )
				);
			}
			$where_clauses[] = 'created_at >= %s';
			$where_values[]  = $date_from . ' 00:00:00';
		}

		$date_to = $request->get_param( 'date_to' );
		if ( ! empty( $date_to ) ) {
			if ( ! self::is_valid_date( $date_to ) ) {
				return new \WP_Error(
					'despace_invalid_date_format',
					__( 'Invalid date_to format. Expected YYYY-MM-DD.', 'despace-core' ),
					array( 'status' => 400 )
				);
			}
			$where_clauses[] = 'created_at <= %s';
			$where_values[]  = $date_to . ' 23:59:59';
		}

		if ( ! empty( $date_from ) && ! empty( $date_to ) && $date_from > $date_to ) {
			return new \WP_Error(
				'despace_invalid_date_range',
				__( 'date_from cannot be after date_to.', 'despace-core' ),
				array( 'status' => 400 )
			);
		}

		$where_sql = implode( ' AND ', $where_clauses );

		// Count total items.
		$count_sql = "SELECT COUNT(*) FROM {$table_name} WHERE {$where_sql}";
		if ( ! empty( $where_values ) ) {
			$total_items = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $where_values ) );
		} else {
			$total_items = (int) $wpdb->get_var( $count_sql );
		}

		// Query page items.
		$items_sql = "SELECT * FROM {$table_name} WHERE {$where_sql} ORDER BY created_at DESC, log_id DESC LIMIT %d OFFSET %d";
		$items_values = array_merge( $where_values, array( $per_page, $offset ) );
		$rows = $wpdb->get_results( $wpdb->prepare( $items_sql, $items_values ), ARRAY_A );

		$total_pages = ceil( $total_items / $per_page );

		$response_items = array();
		foreach ( (array) $rows as $row ) {
			$response_items[] = array(
				'id'              => (int) ( $row['log_id'] ?? $row['id'] ?? 0 ),
				'event_type'      => $row['event_type'],
				'event_source'    => $row['event_source'],
				'product_id'      => (int) $row['product_id'],
				'variation_id'    => $row['variation_id'] ? (int) $row['variation_id'] : null,
				'order_id'        => $row['order_id'] ? (int) $row['order_id'] : null,
				'order_item_id'   => $row['order_item_id'] ? (int) $row['order_item_id'] : null,
				'refund_id'       => $row['refund_id'] ? (int) $row['refund_id'] : null,
				'actor_user_id'   => $row['actor_user_id'] ? (int) $row['actor_user_id'] : null,
				'quantity_before' => null !== $row['quantity_before'] ? (float) $row['quantity_before'] : null,
				'quantity_delta'  => (float) $row['quantity_delta'],
				'stock_after'     => null !== $row['stock_after'] ? (float) $row['stock_after'] : null,
				'created_at'      => $row['created_at'],
			);
		}

		return rest_ensure_response(
			array(
				'items'      => $response_items,
				'pagination' => array(
					'page'        => $page,
					'per_page'    => $per_page,
					'total_items' => $total_items,
					'total_pages' => (int) $total_pages,
				),
			)
		);
	}

	/**
	 * Collection parameter schema.
	 *
	 * @return array
	 */
	public static function get_collection_params(): array {
		return array(
			'page'         => array(
				'type'              => 'integer',
				'default'           => 1,
				'sanitize_callback' => 'absint',
				'validate_callback' => 'rest_validate_request_arg',
			),
			'per_page'     => array(
				'type'              => 'integer',
				'default'           => 20,
				'sanitize_callback' => 'absint',
				'validate_callback' => 'rest_validate_request_arg',
			),
			'product_id'   => array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			),
			'variation_id' => array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			),
			'order_id'     => array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			),
			'event_type'   => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_key',
			),
			'date_from'    => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'date_to'      => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
	}

	/**
	 * Validate if string is a valid Gregorian calendar date in YYYY-MM-DD format.
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

