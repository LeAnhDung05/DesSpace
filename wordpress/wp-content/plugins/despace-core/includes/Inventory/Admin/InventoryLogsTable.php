<?php
/**
 * Admin Inventory Logs Table — Minimalist Luxury DeSpace Styling.
 *
 * @package DeSpace\Inventory\Admin
 */

namespace DeSpace\Inventory\Admin;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	$wp_list_table_file = ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
	if ( file_exists( $wp_list_table_file ) ) {
		require_once $wp_list_table_file;
	} else {
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
			'created_at'      => __( 'Thời Gian', 'despace-core' ),
			'product'         => __( 'Sản Phẩm', 'despace-core' ),
			'variation'       => __( 'Biến Thể / SKU', 'despace-core' ),
			'order_ref'       => __( 'Đơn Hàng / Hoàn Tiền', 'despace-core' ),
			'event_source'    => __( 'Nguồn', 'despace-core' ),
			'event_type'      => __( 'Loại Sự Kiện', 'despace-core' ),
			'quantity_delta'  => __( 'Biến Động (Delta)', 'despace-core' ),
			'stock_after'     => __( 'Tồn Sau', 'despace-core' ),
			'actor'           => __( 'Người Thực Hiện', 'despace-core' ),
		);
	}

	protected function get_sortable_columns(): array {
		return array();
	}

	public function prepare_items(): void {
		global $wpdb;
		$table_name = $wpdb->prefix . 'despace_inventory_logs';

		$per_page     = 20;
		$current_page = $this->get_pagenum();

		$where_clauses = array( '1=1' );
		$where_values  = array();

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $_GET['event_type'] ) ) {
			$where_clauses[] = 'event_type = %s';
			$where_values[]  = sanitize_key( wp_unslash( $_GET['event_type'] ) );
		}

		if ( ! empty( $_GET['product_id'] ) ) {
			$where_clauses[] = 'product_id = %d';
			$where_values[]  = absint( $_GET['product_id'] );
		}

		if ( ! empty( $_GET['date_from'] ) ) {
			$where_clauses[] = 'created_at >= %s';
			$where_values[]  = sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) . ' 00:00:00';
		}

		if ( ! empty( $_GET['date_to'] ) ) {
			$where_clauses[] = 'created_at <= %s';
			$where_values[]  = sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) . ' 23:59:59';
		}
		// phpcs:enable

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
				return '<span style="color:#57534E; font-size:12.5px; font-variant-numeric:tabular-nums;">' . esc_html( $item['created_at'] ) . '</span>';

			case 'product':
				$product_id = (int) $item['product_id'];
				$product    = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;
				$name       = $product ? $product->get_name() : __( 'Product #', 'despace-core' ) . $product_id;
				$edit_link  = get_edit_post_link( $product_id );
				return $edit_link
					? '<a href="' . esc_url( $edit_link ) . '" style="color:#1C1917; font-weight:600; text-decoration:none; font-size:13.5px;" onmouseover="this.style.color=\'#C25E3A\'" onmouseout="this.style.color=\'#1C1917\'">' . esc_html( $name ) . '</a>'
					: '<strong style="color:#1C1917;">' . esc_html( $name ) . '</strong>';

			case 'variation':
				if ( ! empty( $item['variation_id'] ) ) {
					$var_id  = (int) $item['variation_id'];
					$variant = function_exists( 'wc_get_product' ) ? wc_get_product( $var_id ) : null;
					$sku     = $variant ? $variant->get_sku() : '';
					return '<span style="color:#78716C; font-size:12px; font-family:monospace;">#' . esc_html( $var_id . ( $sku ? ' (' . $sku . ')' : '' ) ) . '</span>';
				}
				return '<span style="color:#D6CFC7;">—</span>';

			case 'order_ref':
				$refs = array();
				if ( ! empty( $item['order_id'] ) ) {
					$order_id   = (int) $item['order_id'];
					$order_link = function_exists( 'wc_get_order' ) && wc_get_order( $order_id )
						? ( function_exists( 'admin_url' ) ? admin_url( 'post.php?post=' . $order_id . '&action=edit' ) : '' )
						: '';
					$refs[] = $order_link
						? '<a href="' . esc_url( $order_link ) . '" style="color:#1C1917; font-weight:600; text-decoration:none;" onmouseover="this.style.color=\'#C25E3A\'" onmouseout="this.style.color=\'#1C1917\'">Đơn #' . esc_html( $order_id ) . '</a>'
						: 'Đơn #' . esc_html( $order_id );
				}
				if ( ! empty( $item['refund_id'] ) ) {
					$refs[] = '<span style="color:#B91C1C; font-weight:600;">Hoàn #' . esc_html( $item['refund_id'] ) . '</span>';
				}
				return ! empty( $refs ) ? implode( ' <span style="color:#D6CFC7;">/</span> ', $refs ) : '<span style="color:#D6CFC7;">—</span>';

			case 'event_source':
				return '<span style="color:#78716C; font-size:11.5px; font-weight:600; letter-spacing:0.5px; text-transform:uppercase;">' . esc_html( $item['event_source'] ) . '</span>';

			case 'event_type':
				$type = $item['event_type'];
				if ( 'order_reduction' === $type ) {
					// Hugeicons / FontAwesome trending-down stroke icon
					return '<span style="color:#B91C1C; font-weight:600; font-size:13px; display:inline-flex; align-items:center; gap:6px;">'
						. '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 18 13.5 8.5 8.5 13.5 1 6"></polyline><polyline points="17 18 23 18 23 12"></polyline></svg>'
						. 'Giảm Tồn Đơn</span>';
				} elseif ( 'stock_restoration' === $type ) {
					// Hugeicons / FontAwesome trending-up stroke icon
					return '<span style="color:#15803D; font-weight:600; font-size:13px; display:inline-flex; align-items:center; gap:6px;">'
						. '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline><polyline points="17 6 23 6 23 12"></polyline></svg>'
						. 'Khôi Phục Kho</span>';
				} elseif ( 'refund_restock' === $type ) {
					// Hugeicons / FontAwesome refresh/undo stroke icon
					return '<span style="color:#2563EB; font-weight:600; font-size:13px; display:inline-flex; align-items:center; gap:6px;">'
						. '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 2v6h-6"></path><path d="M3 12a9 9 0 0 1 15-6.7L21 8"></path><path d="M3 22v-6h6"></path><path d="M21 12a9 9 0 0 1-15 6.7L3 16"></path></svg>'
						. 'Hoàn Trả Kho</span>';
				} elseif ( 'manual_adjustment' === $type ) {
					// Hugeicons / FontAwesome sliders/settings stroke icon
					return '<span style="color:#D97706; font-weight:600; font-size:13px; display:inline-flex; align-items:center; gap:6px;">'
						. '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="4" y1="21" x2="4" y2="14"></line><line x1="4" y1="10" x2="4" y2="3"></line><line x1="12" y1="21" x2="12" y2="12"></line><line x1="12" y1="8" x2="12" y2="3"></line><line x1="20" y1="21" x2="20" y2="16"></line><line x1="20" y1="12" x2="20" y2="3"></line><line x1="1" y1="14" x2="7" y2="14"></line><line x1="9" y1="8" x2="15" y2="8"></line><line x1="17" y1="16" x2="23" y2="16"></line></svg>'
						. 'Chỉnh Tay</span>';
				}
				return '<span style="color:#57534E; font-size:13px;">' . esc_html( $type ) . '</span>';

			case 'quantity_delta':
				$delta = (float) $item['quantity_delta'];
				if ( $delta > 0 ) {
					return '<span style="color:#15803D; font-weight:700; font-size:13.5px; font-variant-numeric:tabular-nums;">+' . esc_html( $delta ) . '</span>';
				}
				return '<span style="color:#B91C1C; font-weight:700; font-size:13.5px; font-variant-numeric:tabular-nums;">' . esc_html( $delta ) . '</span>';

			case 'stock_after':
				return null !== $item['stock_after'] ? '<span style="font-size:13px; font-weight:600; color:#1C1917; font-variant-numeric:tabular-nums;">' . esc_html( (float) $item['stock_after'] ) . '</span>' : '<span style="color:#D6CFC7;">—</span>';

			case 'actor':
				if ( ! empty( $item['actor_user_id'] ) ) {
					$user = get_userdata( (int) $item['actor_user_id'] );
					return '<span style="color:#57534E; font-size:12.5px;">' . ( $user ? esc_html( $user->display_name ) : '#' . esc_html( $item['actor_user_id'] ) ) . '</span>';
				}
				return '<span style="color:#A8A29E; font-size:12px; font-style:italic;">' . esc_html__( 'System / Khách', 'despace-core' ) . '</span>';

			default:
				return '';
		}
	}

	public function no_items(): void {
		echo '<div style="padding:32px 16px; text-align:center; color:#78716C; font-size:14px;">' . esc_html__( 'Chưa có bản ghi biến động tồn kho nào.', 'despace-core' ) . '</div>';
	}
}
