<?php
/**
 * WordPress Admin List Table for Inventory Logs.
 *
 * @package DeSpace\Inventory\Admin
 */

namespace DeSpace\Inventory\Admin;

use DeSpace\Inventory\Database\InventoryLogInstaller;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	$list_table_path = ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
	if ( file_exists( $list_table_path ) ) {
		require_once $list_table_path;
	} else {
		// Fallback stub for CLI or non-admin testing.
		class WP_List_Table_Stub {
			protected $_column_headers = array();
			protected $items = array();
			public function __construct( $args = array() ) {}
			public function get_pagenum() { return 1; }
			public function set_pagination_args( $args = array() ) {}
			public function display() {}
		}
		class_alias( 'DeSpace\Inventory\Admin\WP_List_Table_Stub', 'WP_List_Table' );
	}
}

/**
 * Class InventoryLogsTable
 */
class InventoryLogsTable extends \WP_List_Table {

	public function __construct() {
		parent::__construct( array(
			'singular' => 'inventory_log',
			'plural'   => 'inventory_logs',
			'ajax'     => false,
		) );
	}

	public function get_columns(): array {
		return array(
			'created_at'      => __( 'Time (UTC)', 'despace-core' ),
			'product'         => __( 'Product', 'despace-core' ),
			'variation'       => __( 'Variation / SKU', 'despace-core' ),
			'order_ref'       => __( 'Order / Refund', 'despace-core' ),
			'event_source'    => __( 'Source', 'despace-core' ),
			'event_type'      => __( 'Event Type', 'despace-core' ),
			'quantity_delta'  => __( 'Change (Delta)', 'despace-core' ),
			'stock_after'     => __( 'Stock After', 'despace-core' ),
			'actor'           => __( 'Actor', 'despace-core' ),
		);
	}

	public function prepare_items(): void {
		global $wpdb;

		$table_name = InventoryLogInstaller::get_table_name();
		$per_page   = 20;
		$current_page = $this->get_pagenum();

		$columns  = $this->get_columns();
		$hidden   = array();
		$sortable = array();
		$this->_column_headers = array( $columns, $hidden, $sortable );

		$where_clauses = array( '1=1' );
		$where_values  = array();

		// Sanitize and apply filters.
		$event_type = isset( $_GET['event_type'] ) ? sanitize_key( wp_unslash( $_GET['event_type'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $event_type ) ) {
			$where_clauses[] = 'event_type = %s';
			$where_values[]  = $event_type;
		}

		$product_id = isset( $_GET['product_id'] ) ? absint( $_GET['product_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $product_id > 0 ) {
			$where_clauses[] = 'product_id = %d';
			$where_values[]  = $product_id;
		}

		$date_from = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $date_from ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_from ) ) {
			$where_clauses[] = 'created_at >= %s';
			$where_values[]  = $date_from . ' 00:00:00';
		}

		$date_to = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $date_to ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_to ) ) {
			$where_clauses[] = 'created_at <= %s';
			$where_values[]  = $date_to . ' 23:59:59';
		}

		$where_sql = implode( ' AND ', $where_clauses );

		$count_sql = "SELECT COUNT(*) FROM {$table_name} WHERE {$where_sql}";
		$total_items = ! empty( $where_values )
			? (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $where_values ) )
			: (int) $wpdb->get_var( $count_sql );

		$offset = ( $current_page - 1 ) * $per_page;
		$items_sql = "SELECT * FROM {$table_name} WHERE {$where_sql} ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d";
		$items_values = array_merge( $where_values, array( $per_page, $offset ) );

		$this->items = $wpdb->get_results( $wpdb->prepare( $items_sql, $items_values ), ARRAY_A );

		$this->set_pagination_args( array(
			'total_items' => $total_items,
			'per_page'    => $per_page,
			'total_pages' => ceil( $total_items / $per_page ),
		) );
	}

	public function column_default( $item, $column_name ): string {
		switch ( $column_name ) {
			case 'created_at':
				return esc_html( $item['created_at'] );

			case 'product':
				$product_id = (int) $item['product_id'];
				$product    = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;
				$name       = $product ? $product->get_name() : __( 'Product #', 'despace-core' ) . $product_id;
				$edit_link  = get_edit_post_link( $product_id );
				return $edit_link ? '<a href="' . esc_url( $edit_link ) . '"><strong>' . esc_html( $name ) . '</strong></a>' : esc_html( $name );

			case 'variation':
				if ( ! empty( $item['variation_id'] ) ) {
					$var_id  = (int) $item['variation_id'];
					$variant = function_exists( 'wc_get_product' ) ? wc_get_product( $var_id ) : null;
					$sku     = $variant ? $variant->get_sku() : '';
					return esc_html( '#' . $var_id . ( $sku ? ' (' . $sku . ')' : '' ) );
				}
				return '—';

			case 'order_ref':
				$refs = array();
				if ( ! empty( $item['order_id'] ) ) {
					$order_id   = (int) $item['order_id'];
					$order_link = function_exists( 'wc_get_order' ) && wc_get_order( $order_id )
						? ( function_exists( 'admin_url' ) ? admin_url( 'post.php?post=' . $order_id . '&action=edit' ) : '' )
						: '';
					$refs[] = $order_link
						? '<a href="' . esc_url( $order_link ) . '">Order #' . esc_html( $order_id ) . '</a>'
						: 'Order #' . esc_html( $order_id );
				}
				if ( ! empty( $item['refund_id'] ) ) {
					$refs[] = 'Refund #' . esc_html( $item['refund_id'] );
				}
				return ! empty( $refs ) ? implode( ' / ', $refs ) : '—';

			case 'event_source':
				return '<span class="badge">' . esc_html( $item['event_source'] ) . '</span>';

			case 'event_type':
				return '<code>' . esc_html( $item['event_type'] ) . '</code>';

			case 'quantity_delta':
				$delta = (float) $item['quantity_delta'];
				$color = $delta > 0 ? '#1b7d34' : '#b32d2e';
				$sign  = $delta > 0 ? '+' : '';
				return '<strong style="color:' . esc_attr( $color ) . ';">' . esc_html( $sign . $delta ) . '</strong>';

			case 'stock_after':
				return null !== $item['stock_after'] ? esc_html( (float) $item['stock_after'] ) : '—';

			case 'actor':
				if ( ! empty( $item['actor_user_id'] ) ) {
					$user = get_userdata( (int) $item['actor_user_id'] );
					return $user ? esc_html( $user->display_name ) : '#' . esc_html( $item['actor_user_id'] );
				}
				return '<em>' . esc_html__( 'System / Guest', 'despace-core' ) . '</em>';

			default:
				return '';
		}
	}

	public function no_items(): void {
		esc_html_e( 'No inventory events recorded yet.', 'despace-core' );
	}
}

