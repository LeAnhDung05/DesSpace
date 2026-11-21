<?php
/**
 * DeSpace store dashboard for WordPress administrators.
 *
 * @package DeSpace
 */

defined( 'ABSPATH' ) || exit;

/**
 * Keep store controls visible only to people who can manage the shop.
 */
function despace_store_admin_can_manage() {
	if ( ! function_exists( 'wp_get_current_user' ) ) {
		return false;
	}

	return current_user_can( 'manage_woocommerce' ) || current_user_can( 'manage_options' );
}

/**
 * Determine the primary DeSpace capability held by the current user for the unified cockpit menu.
 *
 * Allows administrator, shop_manager, and despace_staff to enter the DeSpace menu
 * while hiding it completely from customers/subscribers.
 */
function despace_store_admin_hub_capability() {
	if ( ! function_exists( 'current_user_can' ) ) {
		return '';
	}

	$candidates = array(
		'manage_woocommerce',
		'manage_options',
		'view_despace_reports',
		'view_despace_inventory_logs',
		'manage_despace_3d',
	);

	foreach ( $candidates as $cap ) {
		if ( current_user_can( $cap ) ) {
			return $cap;
		}
	}

	return '';
}

/**
 * Check whether the current user can access the unified DeSpace Admin Cockpit.
 */
function despace_store_admin_can_access_hub() {
	return '' !== despace_store_admin_hub_capability();
}

/**
 * Check whether the current user can access DeSpace Reports (M5).
 */
function despace_store_admin_can_view_reports() {
	return function_exists( 'current_user_can' )
		&& ( current_user_can( 'view_despace_reports' ) || current_user_can( 'manage_options' ) );
}

/**
 * Check whether the current user can access DeSpace Inventory Audit Logs (M4).
 */
function despace_store_admin_can_view_inventory() {
	return function_exists( 'current_user_can' )
		&& ( current_user_can( 'view_despace_inventory_logs' ) || current_user_can( 'manage_woocommerce' ) || current_user_can( 'manage_options' ) );
}

/**
 * Check whether the current user can access DeSpace 3D & AR Studio.
 */
function despace_store_admin_can_manage_3d() {
	return function_exists( 'current_user_can' )
		&& ( current_user_can( 'manage_despace_3d' ) || current_user_can( 'manage_woocommerce' ) || current_user_can( 'manage_options' ) );
}

/**
 * Localize the most-used WordPress menu labels for the store owner.
 */
function despace_store_admin_localize_menu() {
	if ( ! despace_store_admin_can_manage() ) {
		return;
	}

	global $menu, $submenu;

	$labels = array(
		'index.php'                    => 'Tổng quan',
		'edit.php'                     => 'Bài viết',
		'upload.php'                   => 'Thư viện ảnh',
		'edit.php?post_type=page'      => 'Trang',
		'edit.php?post_type=product'   => 'Sản phẩm',
		'woocommerce'                  => 'Bán hàng',
		'wc-admin&path=/analytics/overview' => 'Phân tích',
		'wc-admin&path=/marketing'      => 'Khuyến mãi',
		'wc-settings&tab=checkout&from=PAYMENTS_MENU_ITEM' => 'Thanh toán',
		'edit-comments.php'            => 'Bình luận',
		'themes.php'                   => 'Giao diện',
		'plugins.php'                  => 'Plugin',
		'users.php'                    => 'Người dùng',
		'tools.php'                    => 'Công cụ',
		'options-general.php'          => 'Cài đặt',
		'yith_plugin_panel'             => 'Tiện ích cửa hàng',
		'astra'                        => 'Giao diện Astra',
	);

	foreach ( $menu as $index => $item ) {
		if ( isset( $item[2], $labels[ $item[2] ] ) ) {
			$menu[ $index ][0] = esc_html( $labels[ $item[2] ] );
		} else {
			$menu_text_labels = array(
				'Payments'  => 'Thanh toán',
				'Analytics' => 'Phân tích',
				'Marketing' => 'Khuyến mãi',
			);
			$plain_label = trim( wp_strip_all_tags( $item[0] ) );
			if ( isset( $menu_text_labels[ $plain_label ] ) ) {
				$menu[ $index ][0] = esc_html( $menu_text_labels[ $plain_label ] );
			}
		}
	}

	$submenu_labels = array(
		'index.php' => array(
			'index.php'      => 'Trang chủ',
			'update-core.php' => 'Cập nhật',
		),
		'edit.php' => array(
			'edit.php'                                    => 'Tất cả bài viết',
			'post-new.php'                                => 'Viết bài mới',
			'edit-tags.php?taxonomy=category'             => 'Chuyên mục',
			'edit-tags.php?taxonomy=post_tag'             => 'Thẻ bài viết',
		),
		'upload.php' => array(
			'upload.php'   => 'Thư viện ảnh',
			'media-new.php' => 'Tải ảnh lên',
		),
		'edit.php?post_type=page' => array(
			'edit.php?post_type=page' => 'Tất cả trang',
			'post-new.php?post_type=page' => 'Thêm trang mới',
		),
		'edit.php?post_type=product' => array(
			'edit.php?post_type=product' => 'Tất cả sản phẩm',
			'post-new.php?post_type=product' => 'Thêm sản phẩm',
			'edit-tags.php?taxonomy=product_cat&post_type=product' => 'Danh mục sản phẩm',
			'edit-tags.php?taxonomy=product_tag&post_type=product' => 'Thẻ sản phẩm',
		),
		'woocommerce' => array(
			'wc-admin' => 'Tổng quan bán hàng',
			'edit.php?post_type=shop_order' => 'Đơn hàng',
			'wc-admin&path=/customers' => 'Khách hàng',
			'edit.php?post_type=shop_coupon' => 'Mã giảm giá',
			'wc-reports' => 'Báo cáo',
			'wc-settings' => 'Cài đặt bán hàng',
			'wc-status' => 'Trạng thái hệ thống',
			'wc-admin&path=/extensions' => 'Tiện ích mở rộng',
		),
		'wc-admin&path=/analytics/overview' => array(
			'wc-admin&path=/analytics/overview' => 'Tổng quan',
			'wc-admin&path=/analytics/products' => 'Sản phẩm',
			'wc-admin&path=/analytics/revenue' => 'Doanh thu',
			'wc-admin&path=/analytics/orders' => 'Đơn hàng',
			'wc-admin&path=/analytics/variations' => 'Biến thể',
			'wc-admin&path=/analytics/categories' => 'Danh mục',
			'wc-admin&path=/analytics/coupons' => 'Mã giảm giá',
			'wc-admin&path=/analytics/taxes' => 'Thuế',
			'wc-admin&path=/analytics/downloads' => 'Tệp tải xuống',
			'wc-admin&path=/analytics/stock' => 'Tồn kho',
			'wc-admin&path=/analytics/settings' => 'Cài đặt phân tích',
		),
		'woocommerce-marketing' => array(
			'wc-admin&path=/marketing' => 'Tổng quan',
			'edit.php?post_type=shop_coupon' => 'Mã giảm giá',
		),
		'yith_plugin_panel' => array(
			'yith_wcqv_panel' => 'Xem nhanh sản phẩm',
			'yith_wcwl_panel' => 'Danh sách yêu thích',
			'yith_system_info' => 'Trạng thái hệ thống',
		),
	);

	foreach ( $submenu_labels as $parent_slug => $items ) {
		if ( empty( $submenu[ $parent_slug ] ) ) {
			continue;
		}

		foreach ( $submenu[ $parent_slug ] as $index => $item ) {
			if ( isset( $item[2], $items[ $item[2] ] ) ) {
				$badge = '';
				if ( preg_match( '/(<span\b[^>]*>.*)$/s', $item[0], $matches ) ) {
					$badge = ' ' . $matches[1];
				}
				$submenu[ $parent_slug ][ $index ][0] = esc_html( $items[ $item[2] ] ) . $badge;
			}
		}
	}

	$localized_submenu_text = array(
		'Home'       => 'Tổng quan bán hàng',
		'Orders'     => 'Đơn hàng',
		'Customers'  => 'Khách hàng',
		'Coupons'    => 'Mã giảm giá',
		'Reports'    => 'Báo cáo',
		'Settings'   => 'Cài đặt',
		'Status'     => 'Trạng thái hệ thống',
		'Extensions' => 'Tiện ích mở rộng',
		'Overview'   => 'Tổng quan',
		'Products'   => 'Sản phẩm',
		'Revenue'    => 'Doanh thu',
		'Variations' => 'Biến thể',
		'Categories' => 'Danh mục',
		'Taxes'      => 'Thuế',
		'Downloads'  => 'Tệp tải xuống',
		'Stock'      => 'Tồn kho',
		'Brands'     => 'Thương hiệu',
		'Tags'       => 'Thẻ sản phẩm',
		'Attributes' => 'Thuộc tính',
		'Reviews'    => 'Đánh giá',
		'Dashboard'  => 'Tổng quan',
		'Customize'  => 'Tùy chỉnh giao diện',
		'Site Builder' => 'Trình dựng trang',
		'Quick View' => 'Xem nhanh sản phẩm',
		'Wishlist'   => 'Danh sách yêu thích',
	);

	foreach ( $submenu as $parent_slug => $items ) {
		$is_store_menu = 'woocommerce' === $parent_slug
			|| 'woocommerce-marketing' === $parent_slug
			|| 'edit.php?post_type=product' === $parent_slug
			|| 'yith_plugin_panel' === $parent_slug
			|| 'astra' === $parent_slug
			|| false !== strpos( $parent_slug, 'wc-admin' );

		if ( ! $is_store_menu ) {
			continue;
		}

		foreach ( $items as $index => $item ) {
			$raw_label   = (string) $item[0];
			$plain_label = trim( wp_strip_all_tags( preg_replace( '/<span\b.*$/s', '', $raw_label ) ) );
			$count_suffix = '';
			if ( preg_match( '/\s+(\d+)$/', $plain_label, $count_match ) ) {
				$count_suffix = ' ' . $count_match[1];
				$plain_label  = trim( preg_replace( '/\s+\d+$/', '', $plain_label ) );
			}

			if ( ! isset( $localized_submenu_text[ $plain_label ] ) ) {
				continue;
			}

			$badge = '';
			if ( preg_match( '/(<span\b[^>]*>.*)$/s', $raw_label, $matches ) ) {
				$badge = ' ' . $matches[1];
			}

			$submenu[ $parent_slug ][ $index ][0] = esc_html( $localized_submenu_text[ $plain_label ] ) . $count_suffix . $badge;
		}
	}
}
add_action( 'admin_menu', 'despace_store_admin_localize_menu', 9999 );

/**
 * Translate the dashboard heading and browser title without changing the public site language.
 */
function despace_store_admin_dashboard_heading( $translation, $text, $domain ) {
	if ( 'default' !== $domain && 'woocommerce' !== $domain ) {
		return $translation;
	}

	static $can_manage = null;
	if ( null === $can_manage ) {
		$can_manage = despace_store_admin_can_manage();
	}
	if ( ! $can_manage ) {
		return $translation;
	}

	if ( 'index.php' === ( $GLOBALS['pagenow'] ?? '' ) && 'default' === $domain && 'Dashboard' === $text ) {
		return 'Tổng quan cửa hàng';
	}

	if ( 'woocommerce' !== $domain || ! function_exists( 'get_current_screen' ) ) {
		return $translation;
	}

	$screen = get_current_screen();
	if ( ! $screen ) {
		return $translation;
	}

	if ( 'edit-product' === $screen->id ) {
		$product_labels = array(
			'Image'      => 'Ảnh',
			'Name'       => 'Tên sản phẩm',
			'SKU'        => 'Mã SKU',
			'Stock'      => 'Tồn kho',
			'Price'      => 'Giá bán',
			'Categories' => 'Danh mục',
			'Tags'       => 'Thẻ',
			'Date'       => 'Ngày',
		);

		if ( isset( $product_labels[ $text ] ) ) {
			return $product_labels[ $text ];
		}
	}

	$order_screens = array( 'edit-shop_order', 'woocommerce_page_wc-orders' );
	if ( in_array( $screen->id, $order_screens, true ) ) {
		$order_labels = array(
			'All'                        => 'Tất cả',
			'Pending payment'            => 'Chờ thanh toán',
			'Processing'                 => 'Đang xử lý',
			'On hold'                    => 'Chờ xác nhận',
			'Completed'                  => 'Hoàn thành',
			'Cancelled'                  => 'Đã hủy',
			'Refunded'                   => 'Đã hoàn tiền',
			'Failed'                     => 'Thất bại',
			'Draft'                      => 'Bản nháp',
			'Date'                       => 'Ngày đặt',
			'Filter by date'             => 'Lọc theo tháng',
			'Filter by registered customer' => 'Lọc theo khách hàng',
			'Search orders'              => 'Tìm đơn hàng',
			'Search orders:'             => 'Tìm đơn hàng:',
		);

		if ( isset( $order_labels[ $text ] ) ) {
			return $order_labels[ $text ];
		}
	}

	return $translation;
}
if ( is_admin() ) {
	add_filter( 'gettext', 'despace_store_admin_dashboard_heading', 20, 3 );
}

/**
 * Apply the same admin labels to WooCommerce strings translated with context.
 *
 * @param string $translation Translated text.
 * @param string $text        Original text.
 * @param string $context     Translation context.
 * @param string $domain      Text domain.
 * @return string
 */
function despace_store_admin_dashboard_heading_with_context( $translation, $text, $context, $domain ) {
	return despace_store_admin_dashboard_heading( $translation, $text, $domain );
}
if ( is_admin() ) {
	add_filter( 'gettext_with_context', 'despace_store_admin_dashboard_heading_with_context', 20, 4 );
}

/**
 * Put the most useful WooCommerce product columns first without removing plugin columns.
 *
 * @param array $columns Product list table columns.
 * @return array
 */
function despace_store_admin_product_columns( $columns ) {
	if ( ! despace_store_admin_can_manage() || ! is_array( $columns ) ) {
		return $columns;
	}

	$preferred = array( 'cb', 'thumb', 'name', 'sku', 'is_in_stock', 'price', 'product_cat' );
	$ordered   = array();

	foreach ( $preferred as $key ) {
		if ( isset( $columns[ $key ] ) ) {
			$ordered[ $key ] = $columns[ $key ];
			unset( $columns[ $key ] );
		}
	}

	return array_merge( $ordered, $columns );
}
add_filter( 'manage_product_posts_columns', 'despace_store_admin_product_columns', 99 );

/**
 * Put order identity, customer, status, date, and total before secondary information.
 *
 * @param array $columns Order list table columns.
 * @return array
 */
function despace_store_admin_order_columns( $columns ) {
	if ( ! despace_store_admin_can_manage() || ! is_array( $columns ) ) {
		return $columns;
	}

	$columns['despace_customer'] = 'Khách hàng';
	$labels                     = array(
		'order_number'     => 'Mã đơn',
		'order_date'       => 'Ngày đặt',
		'order_status'     => 'Trạng thái',
		'billing_address'  => 'Thông tin thanh toán',
		'shipping_address' => 'Địa chỉ giao',
		'order_total'      => 'Tổng tiền',
		'wc_actions'       => 'Thao tác',
	);
	$preferred                  = array( 'cb', 'order_number', 'despace_customer', 'order_status', 'order_date', 'order_total', 'billing_address', 'shipping_address', 'wc_actions' );
	$ordered                    = array();

	foreach ( $preferred as $key ) {
		if ( isset( $columns[ $key ] ) ) {
			$ordered[ $key ] = isset( $labels[ $key ] ) ? $labels[ $key ] : $columns[ $key ];
			unset( $columns[ $key ] );
		}
	}

	return array_merge( $ordered, $columns );
}
add_filter( 'woocommerce_shop_order_list_table_columns', 'despace_store_admin_order_columns', 99 );
add_filter( 'manage_shop_order_posts_columns', 'despace_store_admin_order_columns', 99 );

/**
 * Render useful customer contact details in the order table.
 *
 * @param WC_Order|false $order WooCommerce order.
 * @return void
 */
function despace_store_admin_render_order_customer( $order ) {
	if ( ! class_exists( 'WC_Order' ) || ! ( $order instanceof WC_Order ) ) {
		return;
	}

	$name  = $order->get_formatted_billing_full_name();
	$email = $order->get_billing_email();
	$phone = $order->get_billing_phone();
	?>
	<div class="despace-order-customer">
		<strong class="despace-order-customer__name"><?php echo esc_html( $name ? $name : 'Khách mua lẻ' ); ?></strong>
		<?php if ( $email ) : ?>
			<a class="despace-order-customer__detail" href="mailto:<?php echo esc_attr( $email ); ?>"><?php echo esc_html( $email ); ?></a>
		<?php endif; ?>
		<?php if ( $phone ) : ?>
			<a class="despace-order-customer__detail" href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) ); ?>"><?php echo esc_html( $phone ); ?></a>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Render the customer column in the HPOS order list.
 *
 * @param string   $column Column name.
 * @param WC_Order $order  Order object.
 * @return void
 */
function despace_store_admin_hpos_order_customer_column( $column, $order ) {
	if ( 'despace_customer' === $column ) {
		despace_store_admin_render_order_customer( $order );
	}
}
add_action( 'woocommerce_shop_order_list_table_custom_column', 'despace_store_admin_hpos_order_customer_column', 10, 2 );

/**
 * Render the customer column in the legacy order list.
 *
 * @param string $column  Column name.
 * @param int    $post_id Order post ID.
 * @return void
 */
function despace_store_admin_legacy_order_customer_column( $column, $post_id ) {
	if ( 'despace_customer' === $column && function_exists( 'wc_get_order' ) ) {
		despace_store_admin_render_order_customer( wc_get_order( $post_id ) );
	}
}
add_action( 'manage_shop_order_posts_custom_column', 'despace_store_admin_legacy_order_customer_column', 20, 2 );

function despace_store_admin_browser_title( $admin_title, $title ) {
	if ( despace_store_admin_can_manage() && 'index.php' === ( $GLOBALS['pagenow'] ?? '' ) ) {
		return 'Tổng quan cửa hàng ‹ ' . get_bloginfo( 'name' ) . ' — WordPress';
	}

	return $admin_title;
}
add_filter( 'admin_title', 'despace_store_admin_browser_title', 20, 2 );

/**
 * Replace the crowded default dashboard with the store overview below.
 */
function despace_store_admin_clean_dashboard() {
	if ( ! despace_store_admin_can_manage() ) {
		return;
	}

	remove_action( 'welcome_panel', 'wp_welcome_panel' );

	$widgets = array(
		'dashboard_activity',
		'dashboard_quick_press',
		'dashboard_primary',
		'dashboard_right_now',
		'dashboard_site_health',
		'woocommerce_dashboard_status',
		'woocommerce_dashboard_recent_reviews',
		'yith_dashboard_products_news',
		'yith_dashboard_blog_news',
	);

	foreach ( $widgets as $widget_id ) {
		remove_meta_box( $widget_id, 'dashboard', 'normal' );
		remove_meta_box( $widget_id, 'dashboard', 'side' );
	}
}
add_action( 'wp_dashboard_setup', 'despace_store_admin_clean_dashboard', 100 );

/**
 * Load dashboard-specific styles across all DeSpace Admin Cockpit screens.
 */
function despace_store_admin_assets( $hook_suffix ) {
	if ( ! despace_store_admin_can_access_hub() ) {
		return;
	}

	$allowed_hooks = array(
		'index.php',
		'toplevel_page_despace-hub',
		'edit.php',
		'woocommerce_page_wc-orders',
		'woocommerce_page_despace-reports',
		'woocommerce_page_despace-inventory-logs',
		'despace_page_despace-reports',
		'despace_page_despace-inventory-logs',
		'despace_page_despace-3d-manager',
		'despace_page_despace-contact-settings',
		'despace_page_despace-system',
	);
	$is_despace_screen = in_array( (string) $hook_suffix, $allowed_hooks, true )
		|| false !== strpos( (string) $hook_suffix, 'despace' );
	if ( ! $is_despace_screen ) {
		return;
	}

	$css_path = __DIR__ . '/despace-store-admin.css';
	$css_url  = plugin_dir_url( __FILE__ ) . 'despace-store-admin.css';
	if ( ! file_exists( $css_path ) && defined( 'WPMU_PLUGIN_DIR' ) && file_exists( WPMU_PLUGIN_DIR . '/despace-store-admin.css' ) ) {
		$css_path = WPMU_PLUGIN_DIR . '/despace-store-admin.css';
		$css_url  = content_url( 'mu-plugins/despace-store-admin.css' );
	}

	if ( file_exists( $css_path ) ) {
		wp_enqueue_style(
			'despace-store-admin',
			$css_url,
			array(),
			(string) filemtime( $css_path )
		);
	}
}
add_action( 'admin_enqueue_scripts', 'despace_store_admin_assets' );

/**
 * Return the order-management URL for either WooCommerce order storage mode.
 */
function despace_store_admin_orders_url() {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
		return admin_url( 'admin.php?page=wc-orders' );
	}

	return admin_url( 'edit.php?post_type=shop_order' );
}

/**
 * Return a filtered order list URL for a standard WooCommerce status.
 */
function despace_store_admin_orders_status_url( $status ) {
	$status = sanitize_key( $status );

	if ( ! in_array( $status, array( 'pending', 'processing', 'on-hold' ), true ) ) {
		return despace_store_admin_orders_url();
	}

	if ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
		return admin_url( 'admin.php?page=wc-orders&status=wc-' . rawurlencode( $status ) );
	}

	return admin_url( 'edit.php?post_type=shop_order&post_status=wc-' . rawurlencode( $status ) );
}

/**
 * Return published products and variations below their configured low-stock threshold.
 */
function despace_store_admin_low_stock_products( $limit = 0 ) {
	global $wpdb;

	if ( empty( $wpdb->wc_product_meta_lookup ) ) {
		return array();
	}

	$threshold = absint( max( get_option( 'woocommerce_notify_low_stock_amount', 2 ), 1 ) );
	$no_stock  = absint( max( get_option( 'woocommerce_notify_no_stock_amount', 0 ), 0 ) );
	$limit     = absint( $limit );
	$limit_sql = $limit ? $wpdb->prepare( ' LIMIT %d', $limit ) : '';

	return $wpdb->get_results(
		$wpdb->prepare(
			"SELECT posts.ID, lookup.stock_quantity
			FROM {$wpdb->wc_product_meta_lookup} AS lookup
			INNER JOIN {$wpdb->posts} AS posts ON lookup.product_id = posts.ID
			LEFT JOIN {$wpdb->postmeta} AS low_stock ON posts.ID = low_stock.post_id AND low_stock.meta_key = '_low_stock_amount'
			WHERE posts.post_type IN ('product', 'product_variation')
			AND posts.post_status = 'publish'
			AND lookup.stock_quantity IS NOT NULL
			AND lookup.stock_status = 'instock'
			AND lookup.stock_quantity > %d
			AND (
				(low_stock.meta_value > '' AND lookup.stock_quantity <= CAST(low_stock.meta_value AS SIGNED))
				OR ((low_stock.meta_value IS NULL OR low_stock.meta_value <= '') AND lookup.stock_quantity <= %d)
			)
			ORDER BY lookup.stock_quantity ASC, posts.post_title ASC{$limit_sql}",
			$no_stock,
			$threshold
		),
		ARRAY_A
	);
}

/**
 * Count the same low-stock products shown in the dashboard inventory list.
 */
function despace_store_admin_low_stock_count() {
	global $wpdb;

	if ( empty( $wpdb->wc_product_meta_lookup ) ) {
		return 0;
	}

	$threshold = absint( max( get_option( 'woocommerce_notify_low_stock_amount', 2 ), 1 ) );
	$no_stock  = absint( max( get_option( 'woocommerce_notify_no_stock_amount', 0 ), 0 ) );
	$count = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(DISTINCT posts.ID)
			FROM {$wpdb->wc_product_meta_lookup} AS lookup
			INNER JOIN {$wpdb->posts} AS posts ON lookup.product_id = posts.ID
			LEFT JOIN {$wpdb->postmeta} AS low_stock ON posts.ID = low_stock.post_id AND low_stock.meta_key = '_low_stock_amount'
			WHERE posts.post_type IN ('product', 'product_variation')
			AND posts.post_status = 'publish'
			AND lookup.stock_quantity IS NOT NULL
			AND lookup.stock_status = 'instock'
			AND lookup.stock_quantity > %d
			AND (
				(low_stock.meta_value > '' AND lookup.stock_quantity <= CAST(low_stock.meta_value AS SIGNED))
				OR ((low_stock.meta_value IS NULL OR low_stock.meta_value <= '') AND lookup.stock_quantity <= %d)
			)",
			$no_stock,
			$threshold
		)
	);

	return absint( $count );
}

/**
 * Render the DeSpace store overview on the WordPress dashboard.
 */
function despace_store_admin_render_dashboard() {
	global $pagenow;

	if ( 'index.php' !== $pagenow || ! despace_store_admin_can_manage() ) {
		return;
	}

	$current_user = wp_get_current_user();
	$products     = wp_count_posts( 'product' );
	$posts        = wp_count_posts( 'post' );
	$product_count = $products && isset( $products->publish ) ? (int) $products->publish : 0;
	$post_count    = $posts && isset( $posts->publish ) ? (int) $posts->publish : 0;
	$processing    = function_exists( 'wc_orders_count' ) ? wc_orders_count( 'processing' ) : 0;
	$pending       = function_exists( 'wc_orders_count' ) ? wc_orders_count( 'pending' ) : 0;
	$on_hold       = function_exists( 'wc_orders_count' ) ? wc_orders_count( 'on-hold' ) : 0;
	$low_stock     = function_exists( 'wc_get_products' ) ? despace_store_admin_low_stock_count() : 0;
	$low_stock_products = function_exists( 'wc_get_product' ) ? despace_store_admin_low_stock_products( 5 ) : array();
	$orders        = function_exists( 'wc_get_orders' ) ? wc_get_orders(
		array(
			'status'  => array( 'processing', 'pending', 'on-hold' ),
			'limit'   => 5,
			'orderby' => 'date',
			'order'   => 'DESC',
		)
	) : array();
	$contact_url   = admin_url( 'admin.php?page=despace-contact-settings&tab=showroom' );
	?>
	<div class="despace-admin-dashboard">
		<header class="despace-admin-hero">
			<div class="despace-admin-hero__copy">
				<p class="despace-admin-eyebrow">DESPACE <span>·</span> BẢNG ĐIỀU KHIỂN CỬA HÀNG CAO CẤP</p>
				<h1><?php echo esc_html( sprintf( 'Chào %s,', $current_user->display_name ) ); ?> <em>hôm nay cửa hàng thế nào?</em></h1>
				<p class="despace-admin-hero__description">Theo dõi đơn hàng, sản phẩm và nội dung website tại một nơi.</p>
				<p class="despace-admin-date"><?php echo esc_html( date_i18n( 'l, j F Y' ) ); ?></p>
			</div>
			<nav class="despace-admin-hero__actions" aria-label="Lối tắt quản trị">
				<a class="despace-admin-button despace-admin-button--light" href="<?php echo esc_url( admin_url( 'admin.php?page=despace-hub' ) ); ?>">Mở DeSpace Cockpit</a>
				<a class="despace-admin-button despace-admin-button--outline" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=product' ) ); ?>">+ Thêm sản phẩm</a>
				<a class="despace-admin-button despace-admin-button--outline" href="<?php echo esc_url( despace_store_admin_orders_url() ); ?>">Mở đơn hàng</a>
			</nav>
		</header>

		<section class="despace-admin-stats" aria-label="Tình hình cửa hàng">
			<a class="despace-admin-stat despace-admin-stat--priority" href="<?php echo esc_url( despace_store_admin_orders_status_url( 'processing' ) ); ?>">
				<span class="despace-admin-stat__label">Đơn đang xử lý</span>
				<strong class="despace-admin-stat__value"><?php echo esc_html( number_format_i18n( $processing ) ); ?></strong>
				<span class="despace-admin-stat__note">Đơn đã xác nhận, cần giao hàng</span>
			</a>
			<a class="despace-admin-stat despace-admin-stat--pending" href="<?php echo esc_url( despace_store_admin_orders_status_url( 'pending' ) ); ?>">
				<span class="despace-admin-stat__label">Chờ thanh toán</span>
				<strong class="despace-admin-stat__value"><?php echo esc_html( number_format_i18n( $pending ) ); ?></strong>
				<span class="despace-admin-stat__note">Kiểm tra thanh toán của khách</span>
			</a>
			<a class="despace-admin-stat despace-admin-stat--hold" href="<?php echo esc_url( despace_store_admin_orders_status_url( 'on-hold' ) ); ?>">
				<span class="despace-admin-stat__label">Chờ xác nhận</span>
				<strong class="despace-admin-stat__value"><?php echo esc_html( number_format_i18n( $on_hold ) ); ?></strong>
				<span class="despace-admin-stat__note">Đơn đang tạm giữ để xử lý</span>
			</a>
			<a class="despace-admin-stat" href="<?php echo esc_url( admin_url( 'edit.php?post_type=product' ) ); ?>">
				<span class="despace-admin-stat__label">Sản phẩm đã đăng</span>
				<strong class="despace-admin-stat__value"><?php echo esc_html( number_format_i18n( $product_count ) ); ?></strong>
				<span class="despace-admin-stat__note">Đang hiển thị trên cửa hàng</span>
			</a>
			<a class="despace-admin-stat despace-admin-stat--stock" href="<?php echo esc_url( admin_url( 'admin.php?page=wc-admin&type=lowstock&path=%2Fanalytics%2Fstock' ) ); ?>">
				<span class="despace-admin-stat__label">Sắp hết hàng</span>
				<strong class="despace-admin-stat__value"><?php echo esc_html( number_format_i18n( $low_stock ) ); ?></strong>
				<span class="despace-admin-stat__note">Theo ngưỡng tồn kho WooCommerce</span>
			</a>
		</section>

		<div class="despace-admin-main-grid">
			<section class="despace-admin-panel despace-admin-orders" aria-labelledby="despace-admin-orders-title">
				<div class="despace-admin-panel__heading">
					<div>
					<p class="despace-admin-eyebrow despace-admin-eyebrow--muted">VẬN HÀNH</p>
					<h3 id="despace-admin-orders-title">Đơn hàng cần chú ý</h3>
				</div>
					<a class="despace-admin-text-link" href="<?php echo esc_url( despace_store_admin_orders_url() ); ?>">Tất cả đơn hàng <span aria-hidden="true">→</span></a>
				</div>
				<?php if ( ! empty( $orders ) ) : ?>
					<div class="despace-admin-table-wrap">
						<table class="despace-admin-table">
							<thead>
								<tr>
									<th scope="col">Đơn hàng</th>
									<th scope="col">Khách hàng</th>
									<th scope="col">Ngày đặt</th>
									<th scope="col">Tổng tiền</th>
									<th scope="col">Trạng thái</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $orders as $order ) : ?>
									<?php
									$status      = $order->get_status();
									$status_names = array(
										'processing' => 'Đang xử lý',
										'pending'    => 'Chờ thanh toán',
										'on-hold'    => 'Chờ xác nhận',
									);
									$date_created = $order->get_date_created();
									$customer     = $order->get_formatted_billing_full_name();
									?>
									<tr>
										<td><a class="despace-admin-order-number" href="<?php echo esc_url( $order->get_edit_order_url() ); ?>">#<?php echo esc_html( $order->get_order_number() ); ?></a><span class="despace-admin-table-subtext"><?php echo esc_html( sprintf( '%s sản phẩm', number_format_i18n( $order->get_item_count() ) ) ); ?></span></td>
										<td><?php echo esc_html( $customer ? $customer : 'Khách mua hàng' ); ?></td>
										<td><?php echo $date_created ? esc_html( date_i18n( 'j M, H:i', $date_created->getTimestamp() ) ) : '—'; ?></td>
										<td><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></td>
										<td><span class="despace-admin-status despace-admin-status--<?php echo esc_attr( sanitize_html_class( $status ) ); ?>"><?php echo esc_html( $status_names[ $status ] ?? wc_get_order_status_name( $status ) ); ?></span></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php else : ?>
					<div class="despace-admin-empty-state">
						<span class="despace-admin-empty-state__mark" aria-hidden="true">✓</span>
						<div><strong>Không có đơn cần xử lý</strong><p>Khi có đơn mới, đơn sẽ xuất hiện ở đây.</p></div>
					</div>
				<?php endif; ?>
			</section>

			<aside class="despace-admin-panel despace-admin-shortcuts" aria-labelledby="despace-admin-shortcuts-title">
				<div class="despace-admin-panel__heading">
					<div>
					<p class="despace-admin-eyebrow despace-admin-eyebrow--muted">THAO TÁC NHANH</p>
					<h3 id="despace-admin-shortcuts-title">Bạn muốn làm gì?</h3>
				</div>
				</div>
				<nav class="despace-admin-link-list" aria-label="Thao tác cửa hàng">
					<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=product' ) ); ?>"><span class="despace-admin-link-icon" aria-hidden="true">＋</span><span><strong>Thêm sản phẩm</strong><small>Tạo sản phẩm mới cho cửa hàng</small></span><span class="despace-admin-link-arrow" aria-hidden="true">→</span></a>
					<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=product' ) ); ?>"><span class="despace-admin-link-icon" aria-hidden="true">▤</span><span><strong>Quản lý sản phẩm</strong><small>Giá bán, hình ảnh và tồn kho</small></span><span class="despace-admin-link-arrow" aria-hidden="true">→</span></a>
					<a href="<?php echo esc_url( despace_store_admin_orders_url() ); ?>"><span class="despace-admin-link-icon" aria-hidden="true">□</span><span><strong>Quản lý đơn hàng</strong><small>Xem đơn, thanh toán và giao hàng</small></span><span class="despace-admin-link-arrow" aria-hidden="true">→</span></a>
					<a href="<?php echo esc_url( admin_url( 'post-new.php' ) ); ?>"><span class="despace-admin-link-icon" aria-hidden="true">✎</span><span><strong>Viết bài blog</strong><small>Chia sẻ câu chuyện và cảm hứng</small></span><span class="despace-admin-link-arrow" aria-hidden="true">→</span></a>
					<a href="<?php echo esc_url( $contact_url ); ?>"><span class="despace-admin-link-icon" aria-hidden="true">⌂</span><span><strong>Sửa trang liên hệ</strong><small>Địa chỉ, giờ làm và thông tin hỗ trợ</small></span><span class="despace-admin-link-arrow" aria-hidden="true">→</span></a>
				</nav>
			</aside>
		</div>

		<section class="despace-admin-panel despace-admin-stock-panel" aria-labelledby="despace-admin-stock-title">
			<div class="despace-admin-panel__heading">
				<div>
					<p class="despace-admin-eyebrow despace-admin-eyebrow--muted">TỒN KHO</p>
					<h3 id="despace-admin-stock-title">Sản phẩm cần nhập thêm</h3>
				</div>
				<a class="despace-admin-text-link" href="<?php echo esc_url( admin_url( 'admin.php?page=wc-admin&type=lowstock&path=%2Fanalytics%2Fstock' ) ); ?>">Xem báo cáo tồn kho <span aria-hidden="true">→</span></a>
			</div>
			<?php if ( ! empty( $low_stock_products ) ) : ?>
				<div class="despace-admin-table-wrap">
					<table class="despace-admin-table despace-admin-stock-table">
						<thead>
							<tr>
								<th scope="col">Sản phẩm</th>
								<th scope="col">SKU</th>
								<th scope="col">Còn lại</th>
								<th scope="col">Ngưỡng cảnh báo</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $low_stock_products as $stock_row ) : ?>
								<?php
								$product = wc_get_product( (int) $stock_row['ID'] );
								if ( ! $product ) {
									continue;
								}
								$name = $product->get_name();
								if ( $product->is_type( 'variation' ) ) {
									$variation = wp_strip_all_tags( wc_get_formatted_variation( $product, true, true, true ) );
									$name      = trim( $name . ' — ' . $variation, ' —' );
								}
								$edit_id  = $product->is_type( 'variation' ) && $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
								$edit_url = get_edit_post_link( $edit_id, '' );
								$threshold = $product->get_low_stock_amount();
								if ( '' === $threshold ) {
									$threshold = absint( max( get_option( 'woocommerce_notify_low_stock_amount', 2 ), 1 ) );
								}
								?>
								<tr>
									<td><?php if ( $edit_url ) : ?><a class="despace-admin-order-number" href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( $name ); ?></a><?php else : ?><?php echo esc_html( $name ); ?><?php endif; ?></td>
									<td><?php echo esc_html( $product->get_sku() ? $product->get_sku() : '—' ); ?></td>
									<td><span class="despace-admin-stock-quantity"><?php echo esc_html( wc_format_stock_quantity_for_display( $product->get_stock_quantity(), $product ) ); ?></span></td>
									<td><?php echo esc_html( wc_format_stock_quantity_for_display( $threshold, $product ) ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php else : ?>
				<div class="despace-admin-empty-state despace-admin-stock-empty">
					<span class="despace-admin-empty-state__mark" aria-hidden="true">✓</span>
					<div><strong>Tồn kho đang ổn</strong><p>Hiện chưa có sản phẩm nào dưới ngưỡng cảnh báo.</p></div>
				</div>
			<?php endif; ?>
		</section>

		<div class="despace-admin-lower-grid">
			<section class="despace-admin-panel despace-admin-content-panel" aria-labelledby="despace-admin-content-title">
				<div class="despace-admin-panel__heading">
					<div>
					<p class="despace-admin-eyebrow despace-admin-eyebrow--muted">NỘI DUNG WEBSITE</p>
					<h3 id="despace-admin-content-title">Cập nhật câu chuyện DeSpace</h3>
				</div>
				</div>
				<div class="despace-admin-content-summary">
					<p>Website hiện có <strong><?php echo esc_html( number_format_i18n( $post_count ) ); ?> bài viết</strong> đã đăng. Hãy dùng ảnh đại diện rõ nét và chọn chuyên mục trước khi xuất bản.</p>
					<a class="despace-admin-button despace-admin-button--dark" href="<?php echo esc_url( admin_url( 'edit.php' ) ); ?>">Mở thư viện bài viết</a>
				</div>
			</section>

			<section class="despace-admin-panel despace-admin-guide" aria-labelledby="despace-admin-guide-title">
				<div class="despace-admin-panel__heading">
					<div>
					<p class="despace-admin-eyebrow despace-admin-eyebrow--muted">HƯỚNG DẪN NHANH</p>
					<h3 id="despace-admin-guide-title">Quy trình một đơn hàng</h3>
				</div>
				</div>
				<ol class="despace-admin-steps">
					<li><span>01</span><p><strong>Mở đơn hàng</strong> và kiểm tra địa chỉ, sản phẩm, thanh toán.</p></li>
					<li><span>02</span><p><strong>Cập nhật trạng thái</strong> sau khi xác nhận hoặc gửi hàng.</p></li>
					<li><span>03</span><p><strong>Kiểm tra tồn kho</strong> nếu sản phẩm sắp hết hàng.</p></li>
				</ol>
				<a class="despace-admin-text-link despace-admin-guide__link" href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener noreferrer">Mở website để xem như khách hàng <span aria-hidden="true">↗</span></a>
			</section>
		</div>
		<footer class="despace-admin-footer"><span>DESPACE MANAGEMENT CENTER</span><span><?php echo esc_html( number_format_i18n( $product_count ) ); ?> sản phẩm <i>·</i> <?php echo esc_html( number_format_i18n( $post_count ) ); ?> bài viết</span><a href="<?php echo esc_url( admin_url( 'site-health.php' ) ); ?>">Tình trạng website <span aria-hidden="true">→</span></a></footer>
	</div>
	<?php
}
add_action( 'admin_notices', 'despace_store_admin_render_dashboard', 20 );

/**
 * -----------------------------------------------------------------------------
 * DESPACE EXECUTIVE MANAGEMENT HUB & BRANDING
 * -----------------------------------------------------------------------------
 */

/** Custom Login Screen with Official DeSpace Logo */
function despace_custom_login_branding() {
	$logo_url = content_url( '/themes/despace-child/assets/images/despace-logo-header.png' );
	?>
	<style type="text/css">
		body.login {
			background: #FAF7F2 !important;
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important;
		}
		body.login div#login h1 a {
			background-image: url('<?php echo esc_url( $logo_url ); ?>') !important;
			background-size: contain !important;
			background-position: center !important;
			background-repeat: no-repeat !important;
			width: 240px !important;
			height: 80px !important;
			margin-bottom: 24px !important;
		}
		body.login form {
			background: #FFFFFF !important;
			border: 1px solid #E7E3DC !important;
			border-radius: 16px !important;
			box-shadow: 0 16px 40px rgba(42, 29, 22, 0.08) !important;
			padding: 32px !important;
		}
		body.login #loginform .button-primary {
			background: #1C1917 !important;
			border-color: #1C1917 !important;
			border-radius: 8px !important;
			font-weight: 600 !important;
			padding: 6px 20px !important;
			text-shadow: none !important;
			box-shadow: none !important;
		}
		body.login #loginform .button-primary:hover {
			background: #3B2A20 !important;
			border-color: #3B2A20 !important;
		}
		body.login .message, body.login .success {
			border-left-color: #9A6745 !important;
			border-radius: 8px !important;
		}
	</style>
	<?php
}
add_action( 'login_enqueue_scripts', 'despace_custom_login_branding' );

add_filter( 'login_headerurl', function() {
	return home_url( '/' );
} );

add_filter( 'login_headertext', function() {
	return 'DeSpace — Luxury Living, Lasting Value';
} );

/** Top Admin Bar DeSpace Brand Mark */
function despace_admin_bar_branding( $wp_admin_bar ) {
	$icon_url = content_url( '/themes/despace-child/assets/images/despace-mark.png' );
	$wp_admin_bar->remove_node( 'wp-logo' );
	if ( ! despace_store_admin_can_access_hub() ) {
		return;
	}
	$wp_admin_bar->add_node( array(
		'id'    => 'despace-logo-node',
		'title' => '<img src="' . esc_url( $icon_url ) . '" style="height:18px; width:18px; vertical-align:middle; margin-right:6px;" alt="DeSpace" /> <span style="font-weight:700; letter-spacing:0.5px; color:#F5F0EA;">DeSpace</span>',
		'href'  => admin_url( 'admin.php?page=despace-hub' ),
		'meta'  => array( 'title' => 'DeSpace Admin Cockpit' ),
	) );
}
add_action( 'admin_bar_menu', 'despace_admin_bar_branding', 11 );

/**
 * Count consultation leads stored in despace_contact_inquiries.
 */
function despace_admin_get_inquiry_count() {
	$inquiries = get_option( 'despace_contact_inquiries', array() );
	return is_array( $inquiries ) ? count( $inquiries ) : 0;
}

/**
 * Register unified top-level DeSpace Admin Cockpit menu and its 6 ordered sub-pages.
 */
function despace_register_admin_hub_menu() {
	$hub_cap = despace_store_admin_hub_capability();
	if ( '' === $hub_cap ) {
		return;
	}

	add_menu_page(
		'DeSpace — Trung Tâm Điều Hành',
		'DeSpace',
		$hub_cap,
		'despace-hub',
		'despace_render_hub_overview_page',
		'dashicons-store',
		3
	);

	// 1. Tổng quan điều hành (Executive Overview)
	add_submenu_page(
		'despace-hub',
		'DeSpace — Tổng quan điều hành',
		'Tổng quan điều hành',
		$hub_cap,
		'despace-hub',
		'despace_render_hub_overview_page'
	);

	// 2. Báo cáo Doanh thu & KPI (M5)
	if ( class_exists( '\DeSpace\Reports\Admin\DashboardPage' ) ) {
		$reports_cap = current_user_can( 'view_despace_reports' ) ? 'view_despace_reports' : 'manage_options';
		add_submenu_page(
			'despace-hub',
			'DeSpace — Báo cáo Doanh thu & KPI',
			'Báo cáo Doanh thu',
			$reports_cap,
			'despace-reports',
			array( '\DeSpace\Reports\Admin\DashboardPage', 'render_page' )
		);
	}

	// 3. Nhật ký & Kiểm toán Kho (M4)
	if ( class_exists( '\DeSpace\Inventory\Admin\InventoryLogsPage' ) ) {
		$inv_cap = current_user_can( 'view_despace_inventory_logs' ) ? 'view_despace_inventory_logs' : 'manage_woocommerce';
		$inv_hook = add_submenu_page(
			'despace-hub',
			'DeSpace — Nhật ký & Kiểm toán Kho',
			'Nhật ký Kho',
			$inv_cap,
			'despace-inventory-logs',
			array( '\DeSpace\Inventory\Admin\InventoryLogsPage', 'render_page' )
		);
		if ( $inv_hook ) {
			add_filter( "manage_{$inv_hook}_columns", array( '\DeSpace\Inventory\Admin\InventoryLogsPage', 'get_table_columns' ) );
		}
	}

	// 4. Xưởng 3D & AR Studio
	if ( function_exists( 'axgnud_render_3d_manager_page' ) ) {
		$cap_3d = current_user_can( 'manage_despace_3d' ) ? 'manage_despace_3d' : 'manage_woocommerce';
		add_submenu_page(
			'despace-hub',
			'DeSpace — Xưởng 3D & AR Studio',
			'Xưởng 3D & AR Studio',
			$cap_3d,
			'despace-3d-manager',
			'axgnud_render_3d_manager_page'
		);
	}

	// 5. Tư vấn & Showroom
	if ( function_exists( 'despace_render_contact_admin_page' ) ) {
		$lead_count   = despace_admin_get_inquiry_count();
		$lead_badge   = $lead_count > 0
			? ' <span class="update-plugins count-' . absint( $lead_count ) . '"><span class="plugin-count">' . absint( $lead_count ) . '</span></span>'
			: '';
		$showroom_cap = current_user_can( 'manage_woocommerce' ) ? 'manage_woocommerce' : 'manage_options';
		add_submenu_page(
			'despace-hub',
			'DeSpace — Tư vấn & Showroom',
			'Tư vấn & Showroom' . $lead_badge,
			$showroom_cap,
			'despace-contact-settings',
			'despace_render_contact_admin_page'
		);
	}

	// 6. Hệ thống & Phân quyền (Administrators only)
	add_submenu_page(
		'despace-hub',
		'DeSpace — Hệ thống & Phân quyền',
		'Hệ thống & Phân quyền',
		'manage_options',
		'despace-system',
		'despace_render_system_admin_page'
	);
}
add_action( 'admin_menu', 'despace_register_admin_hub_menu', 8 );

/**
 * Remove duplicate standalone sidebar registrations once unified under DeSpace.
 * Keeps individual plugin hooks intact for isolated unit tests.
 */
function despace_consolidate_duplicate_admin_menus() {
	if ( ! despace_store_admin_can_access_hub() ) {
		return;
	}

	global $admin_page_hooks, $_registered_pages;

	if ( class_exists( '\DeSpace\Reports\Admin\DashboardPage' ) ) {
		remove_menu_page( 'despace-reports' );
		remove_submenu_page( 'woocommerce', 'despace-reports' );
		unset( $admin_page_hooks['despace-reports'], $_registered_pages['toplevel_page_despace-reports'] );
	}

	if ( class_exists( '\DeSpace\Inventory\Admin\InventoryLogsPage' ) ) {
		remove_submenu_page( 'woocommerce', 'despace-inventory-logs' );
	}

	if ( function_exists( 'axgnud_render_3d_manager_page' ) ) {
		remove_menu_page( 'despace-3d-manager' );
		unset( $admin_page_hooks['despace-3d-manager'], $_registered_pages['toplevel_page_despace-3d-manager'] );
	}

	if ( function_exists( 'despace_render_contact_admin_page' ) ) {
		remove_submenu_page( 'options-general.php', 'despace-contact-settings' );
	}

	if ( function_exists( 'axgnud_3d_ai_settings_page' ) ) {
		remove_submenu_page( 'options-general.php', 'axgnud-3d-ai-settings' );
	}
}
add_action( 'admin_menu', 'despace_consolidate_duplicate_admin_menus', 999 );

/**
 * Backward-compatible redirects for legacy DeSpace admin slugs and tabs.
 * Runs late on admin_menu (priority 1000) before WordPress core checks user_can_access_admin_page().
 */
function despace_redirect_legacy_admin_routes() {
	if ( ! is_admin() || wp_doing_ajax() ) {
		return;
	}

	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
	if ( '' === $page ) {
		return;
	}

	if ( 'despace-showroom-info' === $page && despace_store_admin_can_manage() ) {
		wp_safe_redirect( admin_url( 'admin.php?page=despace-contact-settings&tab=showroom' ) );
		exit;
	}

	if ( 'axgnud-3d-ai-settings' === $page && current_user_can( 'manage_options' ) ) {
		wp_safe_redirect( admin_url( 'admin.php?page=despace-3d-manager&tab=ai' ) );
		exit;
	}

	if ( 'despace-hub' === $page && isset( $_GET['tab'] ) && despace_store_admin_can_access_hub() ) {
		$tab = sanitize_key( wp_unslash( $_GET['tab'] ) );
		$map = array(
			'assets'    => array( 'url' => 'admin.php?page=despace-system&tab=tools', 'cap' => 'manage_options' ),
			'rbac'      => array( 'url' => 'admin.php?page=despace-system&tab=rbac', 'cap' => 'manage_options' ),
			'3d'        => array( 'url' => 'admin.php?page=despace-3d-manager', 'cap' => '' ),
			'inventory' => array( 'url' => 'admin.php?page=despace-inventory-logs', 'cap' => '' ),
			'showroom'  => array( 'url' => 'admin.php?page=despace-contact-settings&tab=showroom', 'cap' => '' ),
		);
		if ( isset( $map[ $tab ] ) ) {
			if ( '' !== $map[ $tab ]['cap'] && ! current_user_can( $map[ $tab ]['cap'] ) ) {
				wp_die( 'Bạn không có quyền truy cập mục này.', 'Truy cập bị từ chối', array( 'response' => 403 ) );
			}
			$target = admin_url( $map[ $tab ]['url'] );
			if ( isset( $_GET['unzipped'] ) ) {
				$target = add_query_arg( 'unzipped', sanitize_key( wp_unslash( $_GET['unzipped'] ) ), $target );
			}
			if ( isset( $_GET['repaired'] ) ) {
				$target = add_query_arg( 'repaired', absint( $_GET['repaired'] ), $target );
			}
			wp_safe_redirect( $target );
			exit;
		}
	}
}
add_action( 'admin_menu', 'despace_redirect_legacy_admin_routes', 1000 );

/**
 * Render the shared Quiet Luxury Admin Cockpit header and 6-module navigation bar.
 *
 * @param string $active_slug   Current admin page slug.
 * @param string $page_title    Main heading for the active screen.
 * @param string $page_subtitle Descriptive subtitle.
 * @param array  $actions       Optional array of CTA button definitions:
 *                              [ 'label' => string, 'url' => string, 'style' => 'primary|ghost|dark', 'target' => '_blank', 'id' => string, 'type' => 'link|button' ].
 */
function despace_render_admin_cockpit_header( string $active_slug, string $page_title, string $page_subtitle = '', array $actions = array() ): void {
	$logo_url   = content_url( '/themes/despace-child/assets/images/despace-logo-header.png' );
	$lead_count = despace_admin_get_inquiry_count();
	$env_type   = function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production';

	$nav_items = array(
		array(
			'slug'    => 'despace-hub',
			'label'   => 'Tổng quan',
			'url'     => admin_url( 'admin.php?page=despace-hub' ),
			'visible' => despace_store_admin_can_access_hub(),
			'badge'   => 0,
		),
		array(
			'slug'    => 'despace-reports',
			'label'   => 'Báo cáo Doanh thu',
			'url'     => admin_url( 'admin.php?page=despace-reports' ),
			'visible' => class_exists( '\DeSpace\Reports\Admin\DashboardPage' ) && despace_store_admin_can_view_reports(),
			'badge'   => 0,
		),
		array(
			'slug'    => 'despace-inventory-logs',
			'label'   => 'Nhật ký Kho',
			'url'     => admin_url( 'admin.php?page=despace-inventory-logs' ),
			'visible' => class_exists( '\DeSpace\Inventory\Admin\InventoryLogsPage' ) && despace_store_admin_can_view_inventory(),
			'badge'   => 0,
		),
		array(
			'slug'    => 'despace-3d-manager',
			'label'   => 'Xưởng 3D & AR',
			'url'     => admin_url( 'admin.php?page=despace-3d-manager' ),
			'visible' => function_exists( 'axgnud_render_3d_manager_page' ) && despace_store_admin_can_manage_3d(),
			'badge'   => 0,
		),
		array(
			'slug'    => 'despace-contact-settings',
			'label'   => 'Tư vấn & Showroom',
			'url'     => admin_url( 'admin.php?page=despace-contact-settings' ),
			'visible' => function_exists( 'despace_render_contact_admin_page' ) && despace_store_admin_can_manage(),
			'badge'   => $lead_count,
		),
		array(
			'slug'    => 'despace-system',
			'label'   => 'Hệ thống & Phân quyền',
			'url'     => admin_url( 'admin.php?page=despace-system' ),
			'visible' => current_user_can( 'manage_options' ),
			'badge'   => 0,
		),
	);

	if ( empty( $actions ) ) {
		if ( despace_store_admin_can_manage() ) {
			$actions[] = array(
				'label' => '+ Thêm sản phẩm',
				'url'   => admin_url( 'post-new.php?post_type=product' ),
				'style' => 'primary',
			);
			$actions[] = array(
				'label' => 'Đơn hàng (HPOS)',
				'url'   => despace_store_admin_orders_url(),
				'style' => 'ghost',
			);
		}
		$actions[] = array(
			'label'  => 'Xem cửa hàng ↗',
			'url'    => home_url( '/' ),
			'style'  => 'ghost',
			'target' => '_blank',
		);
	}
	?>
	<header class="ds-cockpit-header" role="banner">
		<div class="ds-cockpit-banner">
			<div class="ds-cockpit-banner__left">
				<div class="ds-cockpit-brand-row">
					<img src="<?php echo esc_url( $logo_url ); ?>" alt="DeSpace" class="ds-cockpit-logo" />
					<span class="ds-cockpit-badge">Admin Cockpit v3.0</span>
					<?php if ( 'local' === $env_type ) : ?>
						<span class="ds-cockpit-badge">Local Environment</span>
					<?php endif; ?>
				</div>
				<h1><?php echo esc_html( $page_title ); ?></h1>
				<?php if ( '' !== $page_subtitle ) : ?>
					<p class="ds-cockpit-subtitle"><?php echo esc_html( $page_subtitle ); ?></p>
				<?php endif; ?>
			</div>

			<div class="ds-cockpit-actions">
				<?php foreach ( $actions as $action ) : ?>
					<?php
					$style_class = 'ds-btn--ghost';
					if ( isset( $action['style'] ) && 'primary' === $action['style'] ) {
						$style_class = 'ds-btn--primary';
					} elseif ( isset( $action['style'] ) && 'dark' === $action['style'] ) {
						$style_class = 'ds-btn--dark';
					}
					$btn_id   = ! empty( $action['id'] ) ? ' id="' . esc_attr( $action['id'] ) . '"' : '';
					$btn_type = $action['type'] ?? 'link';
					if ( 'button' === $btn_type ) :
						?>
						<button type="button"<?php echo $btn_id; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> class="ds-btn <?php echo esc_attr( $style_class ); ?>">
							<?php echo esc_html( $action['label'] ?? '' ); ?>
						</button>
					<?php else : ?>
						<?php
						$target_attr = ! empty( $action['target'] ) ? ' target="' . esc_attr( $action['target'] ) . '" rel="noopener noreferrer"' : '';
						?>
						<a href="<?php echo esc_url( $action['url'] ?? '#' ); ?>"<?php echo $btn_id . $target_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> class="ds-btn <?php echo esc_attr( $style_class ); ?>">
							<?php echo esc_html( $action['label'] ?? '' ); ?>
						</a>
					<?php endif; ?>
				<?php endforeach; ?>
			</div>
		</div>

		<nav class="ds-cockpit-nav" aria-label="Điều hướng trung tâm quản trị DeSpace">
			<?php foreach ( $nav_items as $item ) : ?>
				<?php
				if ( empty( $item['visible'] ) ) {
					continue;
				}
				$is_active = ( $item['slug'] === $active_slug );
				?>
				<a href="<?php echo esc_url( $item['url'] ); ?>" class="ds-cockpit-nav__item <?php echo $is_active ? 'is-active' : ''; ?>"<?php echo $is_active ? ' aria-current="page"' : ''; ?>>
					<span><?php echo esc_html( $item['label'] ); ?></span>
					<?php if ( ! empty( $item['badge'] ) ) : ?>
						<span class="ds-cockpit-nav__badge"><?php echo esc_html( number_format_i18n( (int) $item['badge'] ) ); ?></span>
					<?php endif; ?>
				</a>
			<?php endforeach; ?>
		</nav>
	</header>
	<?php
}

/**
 * Safely extract only allowed media/3D files from a ZipArchive into target directory (prevents Zip Slip & RCE).
 *
 * @param ZipArchive $zip        Opened ZipArchive instance.
 * @param string     $target_dir Destination directory path.
 * @return int Number of extracted files.
 */
function despace_safe_extract_zip_archive( ZipArchive $zip, string $target_dir ): int {
	// Strictly disallow SVG/CSS/PHP/HTML to prevent stored XSS & RCE in uploads.
	$allowed_exts   = array( 'jpg', 'jpeg', 'png', 'webp', 'gif', 'glb', 'gltf', 'usdz', 'json', 'csv', 'txt' );
	$max_files      = 500;
	$max_file_bytes = 25 * 1024 * 1024;  // 25 MB per file
	$max_total      = 200 * 1024 * 1024; // 200 MB total uncompressed (Zip Bomb protection)
	$total_bytes    = 0;
	$extracted      = 0;
	$target_base    = rtrim( wp_normalize_path( $target_dir ), '/' );

	for ( $i = 0; $i < $zip->numFiles && $extracted < $max_files; $i++ ) {
		$entry_name = $zip->getNameIndex( $i );
		if ( false === $entry_name ) {
			continue;
		}

		$normalized = ltrim( wp_normalize_path( $entry_name ), '/' );
		// Block Zip Slip traversal, hidden system files, and __MACOSX metadata.
		if (
			'' === $normalized ||
			str_contains( $normalized, '../' ) ||
			str_starts_with( $normalized, '..' ) ||
			str_starts_with( $normalized, '__MACOSX/' ) ||
			str_contains( $normalized, '/.' ) ||
			str_starts_with( $normalized, '.' )
		) {
			continue;
		}

		// Directory entry.
		if ( str_ends_with( $normalized, '/' ) ) {
			wp_mkdir_p( $target_base . '/' . rtrim( $normalized, '/' ) );
			continue;
		}

		$ext = strtolower( pathinfo( $normalized, PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, $allowed_exts, true ) ) {
			continue;
		}

		$stat = $zip->statIndex( $i );
		if ( is_array( $stat ) && (int) ( $stat['size'] ?? 0 ) > $max_file_bytes ) {
			continue;
		}

		$dest_path = $target_base . '/' . $normalized;
		$dest_dir  = dirname( $dest_path );
		if ( ! is_dir( $dest_dir ) ) {
			wp_mkdir_p( $dest_dir );
		}

		$stream = $zip->getStream( $entry_name );
		if ( $stream ) {
			$out = fopen( $dest_path, 'wb' );
			if ( $out ) {
				$written = 0;
				$aborted = false;
				while ( ! feof( $stream ) ) {
					$chunk = fread( $stream, 65536 );
					if ( false === $chunk ) {
						$aborted = true;
						break;
					}
					$len = strlen( $chunk );
					$written     += $len;
					$total_bytes += $len;
					if ( $written > $max_file_bytes || $total_bytes > $max_total ) {
						$aborted = true;
						break;
					}
					fwrite( $out, $chunk );
				}
				fclose( $out );
				if ( $aborted ) {
					@unlink( $dest_path );
				} else {
					$extracted++;
				}
			}
			fclose( $stream );
			if ( $total_bytes > $max_total ) {
				break;
			}
		}
	}

	return $extracted;
}

// Handle 1-Click Unzip Uploads in DeSpace Hub (Administrators only)
add_action( 'admin_post_despace_hub_unzip_uploads', 'despace_handle_hub_unzip_uploads' );
function despace_handle_hub_unzip_uploads() {
	check_admin_referer( 'despace_hub_unzip_nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Bạn không có quyền thực hiện thao tác này.', 403 );
	}

	$target_dir = WP_CONTENT_DIR . '/uploads';
	if ( ! is_dir( $target_dir ) ) {
		wp_mkdir_p( $target_dir );
	}

	// Handle direct file upload via browser
	if ( ! empty( $_FILES['upload_zip_file']['tmp_name'] ) && is_uploaded_file( $_FILES['upload_zip_file']['tmp_name'] ) ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			wp_die( 'Máy chủ không hỗ trợ tiện ích PHP ZipArchive.' );
		}
		$zip = new ZipArchive();
		if ( $zip->open( $_FILES['upload_zip_file']['tmp_name'] ) === true ) {
			despace_safe_extract_zip_archive( $zip, $target_dir );
			$zip->close();
			wp_safe_redirect( admin_url( 'admin.php?page=despace-system&tab=tools&unzipped=1' ) );
			exit;
		} else {
			wp_die( 'Không thể giải nén tệp zip vừa tải lên.' );
		}
	}

	// Search for local uploads.zip
	$candidates = array(
		dirname( ABSPATH ) . '/uploads.zip',
		ABSPATH . 'uploads.zip',
		WP_CONTENT_DIR . '/uploads.zip',
		$target_dir . '/uploads.zip',
		dirname( get_stylesheet_directory(), 4 ) . '/uploads.zip',
	);

	$found_zip = null;
	foreach ( $candidates as $candidate ) {
		if ( file_exists( $candidate ) && filesize( $candidate ) > 1000 ) {
			$found_zip = $candidate;
			break;
		}
	}

	if ( ! $found_zip ) {
		wp_die( 'Không tìm thấy tệp uploads.zip trên hệ thống máy chủ. Vui lòng đặt tệp uploads.zip vào thư mục gốc dự án.' );
	}

	if ( ! class_exists( 'ZipArchive' ) ) {
		wp_die( 'Máy chủ không hỗ trợ tiện ích mở rộng ZipArchive của PHP.' );
	}

	$zip = new ZipArchive();
	if ( $zip->open( $found_zip ) === true ) {
		despace_safe_extract_zip_archive( $zip, $target_dir );
		$zip->close();
		wp_safe_redirect( admin_url( 'admin.php?page=despace-system&tab=tools&unzipped=1' ) );
		exit;
	} else {
		wp_die( 'Lỗi khi mở tệp ZIP: ' . esc_html( $found_zip ) );
	}
}

// Handle Auto Repair Asset URLs (Administrators only)
add_action( 'admin_post_despace_hub_repair_urls', 'despace_handle_hub_repair_urls' );
function despace_handle_hub_repair_urls() {
	check_admin_referer( 'despace_hub_repair_nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Bạn không có quyền thực hiện thao tác này.', 403 );
	}

	global $wpdb;
	$current_home = home_url();

	$old_hosts = array(
		'http://localhost:8088',
		'http://localhost:8090',
		'http://localhost:10016',
		'http://localhost:8080',
		'https://axgnud-luxury.infinityfree.io',
	);

	$updated_rows = 0;
	foreach ( $old_hosts as $old ) {
		if ( $old === $current_home ) {
			continue;
		}

		$r1 = $wpdb->query( $wpdb->prepare(
			"UPDATE {$wpdb->posts} SET post_content = REPLACE(post_content, %s, %s) WHERE post_content LIKE %s",
			$old, $current_home, '%' . $wpdb->esc_like( $old ) . '%'
		) );

		$r2 = $wpdb->query( $wpdb->prepare(
			"UPDATE {$wpdb->posts} SET guid = REPLACE(guid, %s, %s) WHERE guid LIKE %s",
			$old, $current_home, '%' . $wpdb->esc_like( $old ) . '%'
		) );

		// Only update non-serialized postmeta rows in SQL; serialized rows are safely updated in PHP below.
		$r3 = $wpdb->query( $wpdb->prepare(
			"UPDATE {$wpdb->postmeta} SET meta_value = REPLACE(meta_value, %s, %s) WHERE meta_value LIKE %s AND meta_value NOT REGEXP '^(a|O|s):[0-9]+:'",
			$old, $current_home, '%' . $wpdb->esc_like( $old ) . '%'
		) );

		if ( $r1 ) $updated_rows += (int) $r1;
		if ( $r2 ) $updated_rows += (int) $r2;
		if ( $r3 ) $updated_rows += (int) $r3;
	}

	wp_cache_flush();
	$transient_like = '%' . $wpdb->esc_like( '_transient_' ) . '%';
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $transient_like ) );

	wp_safe_redirect( admin_url( 'admin.php?page=despace-system&tab=tools&repaired=' . intval( $updated_rows ) ) );
	exit;
}

// Handle Desktop 3D Studio Sync Key rotation (Administrators only)
add_action( 'admin_post_despace_rotate_desktop_key', 'despace_handle_rotate_desktop_key' );
function despace_handle_rotate_desktop_key() {
	check_admin_referer( 'despace_rotate_desktop_key_nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Bạn không có quyền xoay khóa kết nối Desktop 3D Studio.', 403 );
	}

	try {
		$new_key = bin2hex( random_bytes( 32 ) );
	} catch ( Exception $e ) {
		$new_key = hash( 'sha256', wp_generate_password( 64, true, true ) . microtime( true ) );
	}

	update_option( 'despace_desktop_sync_key', $new_key, false );
	wp_safe_redirect( admin_url( 'admin.php?page=despace-3d-manager&tab=desktop&key_rotated=1' ) );
	exit;
}

/**
 * Render Screen 1: Tổng quan điều hành (Executive Overview).
 */
function despace_render_hub_overview_page() {
	if ( ! despace_store_admin_can_access_hub() ) {
		wp_die( 'Bạn không có quyền truy cập Trung tâm Điều hành DeSpace.', 403 );
	}

	global $wpdb;

	$can_manage     = despace_store_admin_can_manage();
	$can_reports    = despace_store_admin_can_view_reports();
	$can_inventory  = despace_store_admin_can_view_inventory();
	$can_3d         = despace_store_admin_can_manage_3d();
	$can_system     = current_user_can( 'manage_options' );

	$product_count  = (int) ( wp_count_posts( 'product' )->publish ?? 0 );
	$processing     = function_exists( 'wc_orders_count' ) ? (int) wc_orders_count( 'processing' ) : 0;
	$on_hold        = function_exists( 'wc_orders_count' ) ? (int) wc_orders_count( 'on-hold' ) : 0;
	$pending        = function_exists( 'wc_orders_count' ) ? (int) wc_orders_count( 'pending' ) : 0;
	$completed      = function_exists( 'wc_orders_count' ) ? (int) wc_orders_count( 'completed' ) : 0;
	$action_orders  = $processing + $on_hold + $pending;
	$low_stock      = function_exists( 'despace_store_admin_low_stock_count' ) ? (int) despace_store_admin_low_stock_count() : 0;
	$lead_count     = despace_admin_get_inquiry_count();

	// 30-day HPOS net revenue (completed + processing orders).
	$revenue_30d      = 0.0;
	$valid_orders_30d = 0;
	if ( function_exists( 'wc_get_orders' ) ) {
		$since_30d   = gmdate( 'Y-m-d', strtotime( '-30 days' ) );
		$orders_30d  = wc_get_orders(
			array(
				'status'       => array( 'wc-processing', 'wc-completed' ),
				'date_created' => '>=' . $since_30d,
				'limit'        => -1,
				'return'       => 'objects',
			)
		);
		if ( is_array( $orders_30d ) ) {
			foreach ( $orders_30d as $ord ) {
				if ( $ord instanceof WC_Order ) {
					$revenue_30d += (float) $ord->get_total();
					$valid_orders_30d++;
				}
			}
		}
	}

	// 3D & AR coverage.
	$uploads_info    = wp_upload_dir();
	$uploads_basedir = $uploads_info['basedir'];
	$glb_files       = is_dir( $uploads_basedir . '/3d' ) ? glob( $uploads_basedir . '/3d/*/model.glb' ) : array();
	$usdz_files      = is_dir( $uploads_basedir . '/3d' ) ? glob( $uploads_basedir . '/3d/*/model.usdz' ) : array();
	$glb_count       = is_array( $glb_files ) ? count( $glb_files ) : 0;
	$usdz_count      = is_array( $usdz_files ) ? count( $usdz_files ) : 0;

	// M4 Inventory Logs count.
	$log_table  = $wpdb->prefix . 'despace_inventory_logs';
	$has_logs   = ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $log_table ) ) ) === $log_table );
	$log_count  = $has_logs ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$log_table}" ) : 0;
	$today_str  = current_time( 'Y-m-d' );
	$logs_today = $has_logs ? (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$log_table} WHERE DATE(created_at) = %s", $today_str ) ) : 0;

	// Reviews summary.
	$review_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_type = 'review' AND comment_approved = '1'" );
	$avg_rating   = 5.0;
	if ( $review_count > 0 ) {
		$rating_sum = (float) $wpdb->get_var( "SELECT SUM(meta_value) FROM {$wpdb->commentmeta} cm JOIN {$wpdb->comments} c ON c.comment_ID = cm.comment_id WHERE c.comment_type = 'review' AND c.comment_approved = '1' AND cm.meta_key = 'rating'" );
		$avg_rating = $rating_sum > 0 ? round( $rating_sum / $review_count, 1 ) : 5.0;
	}

	// Recent 5 orders & Low-stock list.
	$recent_orders      = function_exists( 'wc_get_orders' ) ? wc_get_orders(
		array(
			'limit'   => 5,
			'orderby' => 'date',
			'order'   => 'DESC',
		)
	) : array();
	$low_stock_products = function_exists( 'wc_get_product' ) ? despace_store_admin_low_stock_products( 5 ) : array();

	$hpos_active = class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' )
		&& \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();

	$status_map = array(
		'processing' => array( 'Đang xử lý', 'ds-pill--info' ),
		'on-hold'    => array( 'Chờ đối soát', 'ds-pill--warning' ),
		'pending'    => array( 'Chờ thanh toán', 'ds-pill--warning' ),
		'completed'  => array( 'Hoàn thành', 'ds-pill--success' ),
		'cancelled'  => array( 'Đã hủy', 'ds-pill--danger' ),
		'refunded'   => array( 'Đã hoàn tiền', 'ds-pill--neutral' ),
		'failed'     => array( 'Thất bại', 'ds-pill--danger' ),
	);
	?>
	<div class="wrap ds-cockpit-wrap">
		<?php
		despace_render_admin_cockpit_header(
			'despace-hub',
			'Tổng quan điều hành DeSpace',
			'Theo dõi doanh thu 30 ngày, đơn hàng HPOS, tồn kho, tài sản 3D/AR và yêu cầu tư vấn showroom tại một bảng điều khiển duy nhất.'
		);
		?>

		<!-- 5 Core Live KPI Cards -->
		<section class="ds-kpi-grid" aria-label="Chỉ số điều hành chính">
			<a class="ds-kpi-card ds-kpi-card--accent" href="<?php echo esc_url( $can_reports ? admin_url( 'admin.php?page=despace-reports' ) : '#' ); ?>">
				<span class="ds-kpi-card__label">Doanh thu 30 ngày (HPOS)</span>
				<div class="ds-kpi-card__value">
					<?php echo function_exists( 'wc_price' ) ? wp_kses_post( wc_price( $revenue_30d, array( 'decimals' => 0 ) ) ) : esc_html( number_format_i18n( $revenue_30d ) . ' ₫' ); ?>
				</div>
				<span class="ds-kpi-card__meta"><?php echo esc_html( sprintf( '%s đơn hợp lệ (Hoàn thành + Đang xử lý)', number_format_i18n( $valid_orders_30d ) ) ); ?></span>
			</a>

			<a class="ds-kpi-card ds-kpi-card--warning" href="<?php echo esc_url( $can_manage ? despace_store_admin_orders_url() : admin_url( 'admin.php?page=despace-reports' ) ); ?>">
				<span class="ds-kpi-card__label">Đơn hàng cần xử lý</span>
				<div class="ds-kpi-card__value"><?php echo esc_html( number_format_i18n( $action_orders ) ); ?></div>
				<span class="ds-kpi-card__meta">
					<?php echo esc_html( sprintf( '%d đang xử lý · %d chờ đối soát · %d hoàn thành', $processing, $on_hold + $pending, $completed ) ); ?>
				</span>
			</a>

			<a class="ds-kpi-card ds-kpi-card--success" href="<?php echo esc_url( $can_3d ? admin_url( 'admin.php?page=despace-3d-manager' ) : admin_url( 'edit.php?post_type=product' ) ); ?>">
				<span class="ds-kpi-card__label">Sản phẩm &amp; Phủ 3D/AR</span>
				<div class="ds-kpi-card__value"><?php echo esc_html( number_format_i18n( $product_count ) ); ?></div>
				<span class="ds-kpi-card__meta">
					<?php
					echo esc_html( sprintf( '%d GLB · %d USDZ', $glb_count, $usdz_count ) );
					if ( $low_stock > 0 ) {
						echo esc_html( sprintf( ' · %d sắp hết kho', $low_stock ) );
					}
					?>
				</span>
			</a>

			<a class="ds-kpi-card ds-kpi-card--info" href="<?php echo esc_url( $can_inventory ? admin_url( 'admin.php?page=despace-inventory-logs' ) : '#' ); ?>">
				<span class="ds-kpi-card__label">Nhật ký Kiểm toán Kho</span>
				<div class="ds-kpi-card__value"><?php echo esc_html( number_format_i18n( $log_count ) ); ?></div>
				<span class="ds-kpi-card__meta"><?php echo esc_html( sprintf( '+%s biến động hôm nay · Truy vết M4', number_format_i18n( $logs_today ) ) ); ?></span>
			</a>

			<a class="ds-kpi-card" href="<?php echo esc_url( $can_manage ? admin_url( 'admin.php?page=despace-contact-settings&tab=leads' ) : '#' ); ?>">
				<span class="ds-kpi-card__label">Khách đăng ký tư vấn</span>
				<div class="ds-kpi-card__value"><?php echo esc_html( number_format_i18n( $lead_count ) ); ?></div>
				<span class="ds-kpi-card__meta"><?php echo esc_html( sprintf( '★ %s/5.0 từ %s đánh giá đã duyệt', number_format( $avg_rating, 1 ), number_format_i18n( $review_count ) ) ); ?></span>
			</a>
		</section>

		<!-- Main Operational Workspace -->
		<div class="ds-cockpit-grid">
			<!-- Left Column: Recent Orders & Low Stock -->
			<div>
				<section class="ds-panel" aria-labelledby="ds-recent-orders-heading">
					<div class="ds-panel__header">
						<div>
							<h2 id="ds-recent-orders-heading" class="ds-panel__title">Đơn hàng mới nhất</h2>
							<p class="ds-panel__desc">5 đơn hàng gần nhất từ WooCommerce HPOS — bấm mã đơn để xem chi tiết &amp; đối soát VietQR.</p>
						</div>
						<?php if ( $can_manage ) : ?>
							<a href="<?php echo esc_url( despace_store_admin_orders_url() ); ?>" class="ds-btn ds-btn--outline">Xem tất cả đơn →</a>
						<?php endif; ?>
					</div>

					<?php if ( ! empty( $recent_orders ) ) : ?>
						<div class="ds-table-wrap">
							<table class="ds-table">
								<thead>
									<tr>
										<th>Mã đơn</th>
										<th>Khách hàng</th>
										<th>Ngày đặt</th>
										<th>Tổng tiền</th>
										<th>Trạng thái</th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $recent_orders as $order ) : ?>
										<?php
										if ( ! ( $order instanceof WC_Order ) ) {
											continue;
										}
										$st_key       = $order->get_status();
										$st_label     = $status_map[ $st_key ][0] ?? wc_get_order_status_name( $st_key );
										$st_class     = $status_map[ $st_key ][1] ?? 'ds-pill--neutral';
										$date_created = $order->get_date_created();
										$customer     = trim( (string) $order->get_formatted_billing_full_name() );
										$edit_url     = $can_manage ? $order->get_edit_order_url() : '';
										?>
										<tr>
											<td>
												<?php if ( $edit_url ) : ?>
													<a href="<?php echo esc_url( $edit_url ); ?>" style="font-weight:700; color:#8f3f21; text-decoration:none;">
														#<?php echo esc_html( $order->get_order_number() ); ?>
													</a>
												<?php else : ?>
													<strong>#<?php echo esc_html( $order->get_order_number() ); ?></strong>
												<?php endif; ?>
												<div style="font-size:12px; color:#57534e;"><?php echo esc_html( sprintf( '%d sản phẩm', $order->get_item_count() ) ); ?></div>
											</td>
											<td>
												<strong><?php echo esc_html( $customer ?: 'Khách lẻ' ); ?></strong>
												<?php if ( $order->get_billing_phone() ) : ?>
													<div style="font-size:12px; color:#57534e;"><?php echo esc_html( $order->get_billing_phone() ); ?></div>
												<?php endif; ?>
											</td>
											<td><?php echo $date_created ? esc_html( date_i18n( 'd/m/Y H:i', $date_created->getTimestamp() ) ) : '—'; ?></td>
											<td><strong><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></strong></td>
											<td><span class="ds-pill <?php echo esc_attr( $st_class ); ?>"><?php echo esc_html( $st_label ); ?></span></td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					<?php else : ?>
						<p style="margin:0; color:#57534e;">Chưa có đơn hàng nào trong hệ thống.</p>
					<?php endif; ?>
				</section>

				<section class="ds-panel" aria-labelledby="ds-low-stock-heading">
					<div class="ds-panel__header">
						<div>
							<h2 id="ds-low-stock-heading" class="ds-panel__title">Cảnh báo tồn kho thấp</h2>
							<p class="ds-panel__desc">Các sản phẩm đang dưới ngưỡng tồn kho WooCommerce cần nhập bổ sung.</p>
						</div>
						<?php if ( $can_inventory ) : ?>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=despace-inventory-logs' ) ); ?>" class="ds-btn ds-btn--outline">Mở Nhật ký Kho →</a>
						<?php endif; ?>
					</div>

					<?php if ( ! empty( $low_stock_products ) ) : ?>
						<div class="ds-table-wrap">
							<table class="ds-table">
								<thead>
									<tr>
										<th>Sản phẩm</th>
										<th>SKU</th>
										<th>Còn lại</th>
										<th>Ngưỡng</th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $low_stock_products as $stock_row ) : ?>
										<?php
										$prod = wc_get_product( (int) $stock_row['ID'] );
										if ( ! $prod ) {
											continue;
										}
										$edit_id   = $prod->is_type( 'variation' ) && $prod->get_parent_id() ? $prod->get_parent_id() : $prod->get_id();
										$edit_link = $can_manage ? get_edit_post_link( $edit_id, '' ) : '';
										$threshold = $prod->get_low_stock_amount();
										if ( '' === $threshold ) {
											$threshold = absint( max( get_option( 'woocommerce_notify_low_stock_amount', 2 ), 1 ) );
										}
										?>
										<tr>
											<td>
												<?php if ( $edit_link ) : ?>
													<a href="<?php echo esc_url( $edit_link ); ?>" style="font-weight:600; color:#1c1917; text-decoration:none;"><?php echo esc_html( $prod->get_name() ); ?></a>
												<?php else : ?>
													<strong><?php echo esc_html( $prod->get_name() ); ?></strong>
												<?php endif; ?>
											</td>
											<td><code><?php echo esc_html( $prod->get_sku() ?: '—' ); ?></code></td>
											<td><span class="ds-pill ds-pill--danger"><?php echo esc_html( (string) $prod->get_stock_quantity() ); ?></span></td>
											<td><?php echo esc_html( (string) $threshold ); ?></td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					<?php else : ?>
						<div style="display:flex; align-items:center; gap:12px; padding:14px 16px; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:10px; color:#166534;">
							<strong>✓ Tồn kho đang an toàn</strong> — Hiện không có sản phẩm nào xuống dưới ngưỡng cảnh báo.
						</div>
					<?php endif; ?>
				</section>
			</div>

			<!-- Right Column: Quick Operations & Compact Health -->
			<div>
				<aside class="ds-panel" aria-labelledby="ds-quick-ops-heading">
					<div class="ds-panel__header">
						<div>
							<h2 id="ds-quick-ops-heading" class="ds-panel__title">Lối tắt nghiệp vụ</h2>
							<p class="ds-panel__desc">Truy cập nhanh các phân hệ quản trị cốt lõi theo quyền tài khoản.</p>
						</div>
					</div>
					<div class="ds-action-list">
						<?php if ( $can_manage ) : ?>
							<a class="ds-action-item" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=product' ) ); ?>">
								<span><strong>+ Thêm sản phẩm nội thất</strong><small>Tạo SKU, giá bán, ảnh và biến thể gỗ</small></span>
								<span aria-hidden="true">→</span>
							</a>
							<a class="ds-action-item" href="<?php echo esc_url( despace_store_admin_orders_url() ); ?>">
								<span><strong>Quản lý Đơn hàng (HPOS)</strong><small>Xác nhận VietQR, cập nhật trạng thái giao hàng</small></span>
								<span aria-hidden="true">→</span>
							</a>
						<?php endif; ?>
						<?php if ( $can_reports ) : ?>
							<a class="ds-action-item" href="<?php echo esc_url( admin_url( 'admin.php?page=despace-reports' ) ); ?>">
								<span><strong>Báo cáo Doanh thu &amp; KPI</strong><small>Lọc theo ngày, AOV, Top 10 sản phẩm bán chạy</small></span>
								<span aria-hidden="true">→</span>
							</a>
						<?php endif; ?>
						<?php if ( $can_inventory ) : ?>
							<a class="ds-action-item" href="<?php echo esc_url( admin_url( 'admin.php?page=despace-inventory-logs' ) ); ?>">
								<span><strong>Nhật ký &amp; Kiểm toán Kho</strong><small>Truy vết biến động tồn kho &amp; xuất CSV</small></span>
								<span aria-hidden="true">→</span>
							</a>
						<?php endif; ?>
						<?php if ( $can_3d ) : ?>
							<a class="ds-action-item" href="<?php echo esc_url( admin_url( 'admin.php?page=despace-3d-manager' ) ); ?>">
								<span><strong>Xưởng 3D &amp; AR Studio</strong><small>Quản lý GLB/USDZ, Desktop Sync &amp; AI 3D</small></span>
								<span aria-hidden="true">→</span>
							</a>
						<?php endif; ?>
						<?php if ( $can_manage ) : ?>
							<a class="ds-action-item" href="<?php echo esc_url( admin_url( 'admin.php?page=despace-contact-settings&tab=leads' ) ); ?>">
								<span><strong>Tư vấn &amp; Showroom</strong><small>Theo dõi <?php echo esc_html( number_format_i18n( $lead_count ) ); ?> yêu cầu tư vấn &amp; sửa thông tin showroom</small></span>
								<span aria-hidden="true">→</span>
							</a>
						<?php endif; ?>
					</div>
				</aside>

				<section class="ds-panel" aria-labelledby="ds-sys-brief-heading">
					<div class="ds-panel__header">
						<h2 id="ds-sys-brief-heading" class="ds-panel__title">Tình trạng hạ tầng</h2>
						<?php if ( $can_system ) : ?>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=despace-system&tab=health' ) ); ?>" style="color:#8f3f21; font-weight:600; text-decoration:none; font-size:13px;">Chi tiết →</a>
						<?php endif; ?>
					</div>
					<div>
						<div class="ds-health-row">
							<span class="ds-health-row__label">PHP / Memory</span>
							<span class="ds-health-row__val"><?php echo esc_html( PHP_VERSION . ' · ' . ini_get( 'memory_limit' ) ); ?></span>
						</div>
						<div class="ds-health-row">
							<span class="ds-health-row__label">WooCommerce HPOS</span>
							<span class="ds-health-row__val">
								<span class="ds-pill <?php echo $hpos_active ? 'ds-pill--success' : 'ds-pill--warning'; ?>">
									<?php echo $hpos_active ? 'Đang bật ✓' : 'Legacy CPT'; ?>
								</span>
							</span>
						</div>
						<div class="ds-health-row">
							<span class="ds-health-row__label">Mô hình 3D &amp; AR</span>
							<span class="ds-health-row__val"><?php echo esc_html( sprintf( '%d GLB / %d USDZ', $glb_count, $usdz_count ) ); ?></span>
						</div>
						<div class="ds-health-row">
							<span class="ds-health-row__label">Môi trường</span>
							<span class="ds-health-row__val"><code><?php echo esc_html( function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production' ); ?></code></span>
						</div>
					</div>
				</section>
			</div>
		</div>
	</div>
	<?php
}

/**
 * Render Screen 6: Hệ thống & Phân quyền (Administrators only).
 * Tabs: health | rbac | tools
 */
function despace_render_system_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Bạn không có quyền truy cập trang Hệ thống & Phân quyền.', 403 );
	}

	global $wpdb;

	$active_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'health';
	if ( ! in_array( $active_tab, array( 'health', 'rbac', 'tools' ), true ) ) {
		$active_tab = 'health';
	}

	$uploads_info    = wp_upload_dir();
	$uploads_basedir = $uploads_info['basedir'];
	$has_3d_dir      = is_dir( $uploads_basedir . '/3d' );
	$has_catalog_dir = is_dir( $uploads_basedir . '/despace-catalog' ) || is_dir( $uploads_basedir . '/furniva-real' );
	$glb_files       = $has_3d_dir ? glob( $uploads_basedir . '/3d/*/model.glb' ) : array();
	$usdz_files      = $has_3d_dir ? glob( $uploads_basedir . '/3d/*/model.usdz' ) : array();
	$glb_count       = is_array( $glb_files ) ? count( $glb_files ) : 0;
	$usdz_count      = is_array( $usdz_files ) ? count( $usdz_files ) : 0;

	$theme_dir      = get_stylesheet_directory();
	$has_mv_local   = file_exists( $theme_dir . '/assets/vendor/model-viewer/model-viewer.min.js' );
	$has_three_local = file_exists( $theme_dir . '/assets/vendor/three/three.module.min.js' );
	$hpos_active    = class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' )
		&& \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
	$has_vietqr_sec = ( defined( 'DESPACE_VIETQR_SECRET' ) && '' !== trim( (string) DESPACE_VIETQR_SECRET ) )
		|| '' !== trim( (string) get_option( 'despace_vietqr_secret', '' ) );
	$has_sync_key   = ( defined( 'DESPACE_DESKTOP_SYNC_KEY' ) && '' !== trim( (string) DESPACE_DESKTOP_SYNC_KEY ) )
		|| '' !== trim( (string) get_option( 'despace_desktop_sync_key', '' ) );
	$env_type       = function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production';

	// Detect uploads.zip on server for Tools tab.
	$zip_candidates = array(
		dirname( ABSPATH ) . '/uploads.zip',
		ABSPATH . 'uploads.zip',
		WP_CONTENT_DIR . '/uploads.zip',
		$uploads_basedir . '/uploads.zip',
		dirname( get_stylesheet_directory(), 4 ) . '/uploads.zip',
	);
	$detected_zip = null;
	foreach ( $zip_candidates as $candidate ) {
		if ( file_exists( $candidate ) && filesize( $candidate ) > 1000 ) {
			$detected_zip = $candidate;
			break;
		}
	}
	?>
	<div class="wrap ds-cockpit-wrap">
		<?php
		despace_render_admin_cockpit_header(
			'despace-system',
			'Hệ thống & Phân quyền',
			'Kiểm tra sức khỏe hạ tầng, ma trận phân quyền thực tế theo WordPress RBAC và các công cụ khôi phục tài sản dành cho quản trị viên.',
			array(
				array(
					'label' => 'Quản lý Người dùng',
					'url'   => admin_url( 'users.php' ),
					'style' => 'primary',
				),
				array(
					'label' => 'WooCommerce Status',
					'url'   => admin_url( 'admin.php?page=wc-status' ),
					'style' => 'ghost',
				),
				array(
					'label'  => 'Xem cửa hàng ↗',
					'url'    => home_url( '/' ),
					'style'  => 'ghost',
					'target' => '_blank',
				),
			)
		);
		?>

		<?php if ( isset( $_GET['unzipped'] ) && '1' === $_GET['unzipped'] ) : ?>
			<div class="notice notice-success is-dismissible" style="margin:0 0 18px;">
				<p><strong>Khôi phục tài sản thành công!</strong> Thư mục <code>wp-content/uploads/</code> đã được giải nén an toàn (chỉ cho phép tệp ảnh và mô hình 3D).</p>
			</div>
		<?php endif; ?>

		<?php if ( isset( $_GET['repaired'] ) ) : ?>
			<div class="notice notice-success is-dismissible" style="margin:0 0 18px;">
				<p><strong>Đồng bộ đường dẫn hoàn tất!</strong> Đã cập nhật <strong><?php echo esc_html( number_format_i18n( absint( $_GET['repaired'] ) ) ); ?></strong> bản ghi khớp với <code><?php echo esc_html( home_url() ); ?></code>.</p>
			</div>
		<?php endif; ?>

		<!-- Sub-navigation Tabs -->
		<nav class="ds-subtabs" aria-label="Phân mục Hệ thống & Phân quyền">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=despace-system&tab=health' ) ); ?>" class="ds-subtabs__item <?php echo 'health' === $active_tab ? 'is-active' : ''; ?>">
				Sức khỏe Hệ thống &amp; Trang chính sách
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=despace-system&tab=rbac' ) ); ?>" class="ds-subtabs__item <?php echo 'rbac' === $active_tab ? 'is-active' : ''; ?>">
				Ma trận Phân quyền (Live RBAC)
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=despace-system&tab=tools' ) ); ?>" class="ds-subtabs__item <?php echo 'tools' === $active_tab ? 'is-active' : ''; ?>">
				Công cụ Tài sản &amp; Bảo trì
			</a>
		</nav>

		<?php if ( 'health' === $active_tab ) : ?>
			<div class="ds-cockpit-grid ds-cockpit-grid--equal">
				<section class="ds-panel">
					<div class="ds-panel__header">
						<h2 class="ds-panel__title">Môi trường máy chủ &amp; WooCommerce</h2>
					</div>
					<div>
						<div class="ds-health-row">
							<span class="ds-health-row__label">Phiên bản PHP</span>
							<span class="ds-health-row__val"><?php echo esc_html( PHP_VERSION ); ?></span>
						</div>
						<div class="ds-health-row">
							<span class="ds-health-row__label">Giới hạn bộ nhớ (memory_limit)</span>
							<span class="ds-health-row__val"><?php echo esc_html( (string) ini_get( 'memory_limit' ) ); ?></span>
						</div>
						<div class="ds-health-row">
							<span class="ds-health-row__label">Tải tệp tối đa (upload / post)</span>
							<span class="ds-health-row__val"><?php echo esc_html( ini_get( 'upload_max_filesize' ) . ' / ' . ini_get( 'post_max_size' ) ); ?></span>
						</div>
						<div class="ds-health-row">
							<span class="ds-health-row__label">Cơ sở dữ liệu (MySQL)</span>
							<span class="ds-health-row__val"><?php echo esc_html( $wpdb->db_version() ); ?></span>
						</div>
						<div class="ds-health-row">
							<span class="ds-health-row__label">WordPress / WooCommerce</span>
							<span class="ds-health-row__val">
								<?php
								$wc_ver = defined( 'WC_VERSION' ) ? WC_VERSION : 'N/A';
								echo esc_html( get_bloginfo( 'version' ) . ' / ' . $wc_ver );
								?>
							</span>
						</div>
						<div class="ds-health-row">
							<span class="ds-health-row__label">WooCommerce HPOS</span>
							<span class="ds-health-row__val">
								<span class="ds-pill <?php echo $hpos_active ? 'ds-pill--success' : 'ds-pill--warning'; ?>">
									<?php echo $hpos_active ? 'Đang bật ✓' : 'Chưa bật'; ?>
								</span>
							</span>
						</div>
						<div class="ds-health-row">
							<span class="ds-health-row__label">WP_ENVIRONMENT_TYPE</span>
							<span class="ds-health-row__val"><code><?php echo esc_html( $env_type ); ?></code></span>
						</div>
					</div>
				</section>

				<section class="ds-panel">
					<div class="ds-panel__header">
						<h2 class="ds-panel__title">Tài sản 3D/AR, Thư viện nội bộ &amp; Bảo mật</h2>
					</div>
					<div>
						<div class="ds-health-row">
							<span class="ds-health-row__label">Thư mục ảnh danh mục</span>
							<span class="ds-health-row__val">
								<span class="ds-pill <?php echo $has_catalog_dir ? 'ds-pill--success' : 'ds-pill--warning'; ?>">
									<?php echo $has_catalog_dir ? 'Sẵn sàng ✓' : 'Chưa thấy thư mục'; ?>
								</span>
							</span>
						</div>
						<div class="ds-health-row">
							<span class="ds-health-row__label">Tệp 3D Web (.glb) &amp; iOS AR (.usdz)</span>
							<span class="ds-health-row__val">
								<span class="ds-pill <?php echo ( $glb_count >= 30 && $usdz_count >= 30 ) ? 'ds-pill--success' : 'ds-pill--warning'; ?>">
									<?php echo esc_html( sprintf( '%d GLB · %d USDZ', $glb_count, $usdz_count ) ); ?>
								</span>
							</span>
						</div>
						<div class="ds-health-row">
							<span class="ds-health-row__label">Self-hosted model-viewer &amp; Three.js</span>
							<span class="ds-health-row__val">
								<span class="ds-pill <?php echo ( $has_mv_local && $has_three_local ) ? 'ds-pill--success' : 'ds-pill--danger'; ?>">
									<?php echo ( $has_mv_local && $has_three_local ) ? 'Nội bộ (Không phụ thuộc CDN) ✓' : 'Thiếu tệp vendor'; ?>
								</span>
							</span>
						</div>
						<div class="ds-health-row">
							<span class="ds-health-row__label">Khóa ký VietQR Webhook</span>
							<span class="ds-health-row__val">
								<span class="ds-pill <?php echo $has_vietqr_sec ? 'ds-pill--success' : 'ds-pill--warning'; ?>">
									<?php echo $has_vietqr_sec ? 'Đã cấu hình ✓' : 'Chưa cấu hình'; ?>
								</span>
							</span>
						</div>
						<div class="ds-health-row">
							<span class="ds-health-row__label">Khóa kết nối Desktop 3D Studio</span>
							<span class="ds-health-row__val">
								<span class="ds-pill <?php echo $has_sync_key ? 'ds-pill--success' : 'ds-pill--danger'; ?>">
									<?php echo $has_sync_key ? 'Đã cấp khóa 64-hex ✓' : 'Chưa cấp khóa'; ?>
								</span>
							</span>
						</div>
						<div class="ds-health-row">
							<span class="ds-health-row__label">Địa chỉ gốc website (home_url)</span>
							<span class="ds-health-row__val"><code><?php echo esc_html( home_url() ); ?></code></span>
						</div>
					</div>
				</section>
			</div>

			<!-- 7 Canonical Policy & Info Pages Audit -->
			<section class="ds-panel">
				<div class="ds-panel__header">
					<div>
						<h2 class="ds-panel__title">Kiểm tra 7 trang Chính sách &amp; Thông tin chuẩn</h2>
						<p class="ds-panel__desc">Đảm bảo các liên kết footer và trang thanh toán luôn trả về HTTP 200 (không 404).</p>
					</div>
					<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=page' ) ); ?>" class="ds-btn ds-btn--outline">Tất cả Trang →</a>
				</div>
				<?php
				$canonical_slugs = array(
					'gioi-thieu'                 => 'Giới thiệu DeSpace',
					'lien-he'                    => 'Liên hệ & Showroom',
					'chinh-sach-giao-hang'       => 'Chính sách giao hàng & lắp đặt',
					'chinh-sach-doi-tra-bao-hanh'=> 'Chính sách đổi trả & bảo hành',
					'chinh-sach-bao-mat'         => 'Chính sách bảo mật',
					'dieu-khoan-mua-ban'         => 'Điều khoản mua bán',
					'huong-dan-mua-hang-thanh-toan' => 'Hướng dẫn mua hàng & thanh toán',
				);
				?>
				<div class="ds-table-wrap">
					<table class="ds-table">
						<thead>
							<tr>
								<th>Trang nội dung</th>
								<th>Đường dẫn chuẩn</th>
								<th>Trạng thái</th>
								<th style="text-align:right;">Thao tác</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $canonical_slugs as $slug => $label ) : ?>
								<?php
								$page_obj = get_page_by_path( $slug, OBJECT, 'page' );
								$is_pub   = ( $page_obj instanceof WP_Post && 'publish' === $page_obj->post_status );
								?>
								<tr>
									<td><strong><?php echo esc_html( $label ); ?></strong></td>
									<td><code>/<?php echo esc_html( $slug ); ?>/</code></td>
									<td>
										<span class="ds-pill <?php echo $is_pub ? 'ds-pill--success' : 'ds-pill--danger'; ?>">
											<?php echo $is_pub ? 'Đã xuất bản ✓' : 'Chưa có / Nháp'; ?>
										</span>
									</td>
									<td style="text-align:right;">
										<?php if ( $page_obj instanceof WP_Post ) : ?>
											<a href="<?php echo esc_url( get_edit_post_link( $page_obj->ID, '' ) ); ?>" class="button button-small">Sửa</a>
											<a href="<?php echo esc_url( get_permalink( $page_obj->ID ) ); ?>" target="_blank" rel="noopener noreferrer" class="button button-small">Xem ↗</a>
										<?php else : ?>
											<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=page' ) ); ?>" class="button button-small">Tạo mới</a>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</section>
		<?php endif; ?>

		<?php if ( 'rbac' === $active_tab ) : ?>
			<?php
			$wp_roles_obj = wp_roles();
			$user_counts  = count_users();
			$avail_roles  = $user_counts['avail_roles'] ?? array();
			$tracked_caps = array(
				'manage_options'              => 'Quản trị hệ thống',
				'manage_woocommerce'          => 'Quản lý cửa hàng & Đơn',
				'view_despace_reports'        => 'Báo cáo Doanh thu (M5)',
				'view_despace_inventory_logs' => 'Nhật ký Kho (M4)',
				'manage_despace_3d'           => 'Quản lý 3D & AR',
				'edit_despace_products'       => 'Sửa dữ liệu SP DeSpace',
			);
			$role_order = array(
				'administrator' => 'Quản trị viên cấp cao (Toàn quyền hệ thống, bảo mật, cài đặt API)',
				'shop_manager'  => 'Quản lý cửa hàng WooCommerce (Sản phẩm, đơn hàng, showroom)',
				'despace_staff' => 'Nhân viên vận hành DeSpace (Báo cáo M5, Nhật ký Kho M4, Danh mục 3D)',
				'customer'      => 'Khách hàng (Chỉ truy cập tài khoản cá nhân & đơn đã mua)',
			);
			?>
			<section class="ds-panel">
				<div class="ds-panel__header">
					<div>
						<h2 class="ds-panel__title">Ma trận Phân quyền Thực tế (Đọc trực tiếp từ WordPress RBAC)</h2>
						<p class="ds-panel__desc">Hiển thị vai trò, số lượng tài khoản thực tế và quyền hạn (capabilities) đang áp dụng trong cơ sở dữ liệu.</p>
					</div>
					<a href="<?php echo esc_url( admin_url( 'users.php' ) ); ?>" class="ds-btn ds-btn--primary">Quản lý &amp; Gán vai trò người dùng →</a>
				</div>

				<div class="ds-table-wrap">
					<table class="ds-table">
						<thead>
							<tr>
								<th>Vai trò (Role)</th>
								<th style="text-align:center;">Số tài khoản</th>
								<?php foreach ( $tracked_caps as $cap_label ) : ?>
									<th style="text-align:center;"><?php echo esc_html( $cap_label ); ?></th>
								<?php endforeach; ?>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $role_order as $role_slug => $role_desc ) : ?>
								<?php
								$role_obj   = $wp_roles_obj ? $wp_roles_obj->get_role( $role_slug ) : null;
								$role_name  = $wp_roles_obj->role_names[ $role_slug ] ?? $role_slug;
								$role_count = (int) ( $avail_roles[ $role_slug ] ?? 0 );
								?>
								<tr>
									<td>
										<strong><?php echo esc_html( translate_user_role( $role_name ) ); ?></strong>
										<code style="margin-left:6px; font-size:12px;"><?php echo esc_html( $role_slug ); ?></code>
										<div style="font-size:12.5px; color:#57534e; margin-top:3px;"><?php echo esc_html( $role_desc ); ?></div>
									</td>
									<td style="text-align:center;">
										<a href="<?php echo esc_url( admin_url( 'users.php?role=' . rawurlencode( $role_slug ) ) ); ?>" class="ds-pill ds-pill--neutral" style="text-decoration:none;">
											<?php echo esc_html( number_format_i18n( $role_count ) ); ?> tài khoản
										</a>
									</td>
									<?php foreach ( $tracked_caps as $cap_key => $cap_label ) : ?>
										<?php
										$has_cap = $role_obj && $role_obj->has_cap( $cap_key );
										?>
										<td style="text-align:center;">
											<?php if ( $has_cap ) : ?>
												<span class="ds-pill ds-pill--success" aria-label="Có quyền">✓ Có</span>
											<?php else : ?>
												<span style="color:#a8a29e;" aria-label="Không có quyền">—</span>
											<?php endif; ?>
										</td>
									<?php endforeach; ?>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</section>
		<?php endif; ?>

		<?php if ( 'tools' === $active_tab ) : ?>
			<section class="ds-panel">
				<div class="ds-panel__header">
					<div>
						<h2 class="ds-panel__title">1. Khôi phục Ảnh sản phẩm &amp; Mô hình 3D từ uploads.zip</h2>
						<p class="ds-panel__desc">Giải nén an toàn vào <code>wp-content/uploads/</code> (chỉ cho phép tệp ảnh, GLB, USDZ; tự động chặn Zip Slip và mã thực thi).</p>
					</div>
				</div>

				<div class="ds-kpi-grid" style="margin-bottom:18px;">
					<div class="ds-kpi-card">
						<span class="ds-kpi-card__label">Ảnh danh mục</span>
						<div class="ds-kpi-card__value" style="font-size:20px;"><?php echo $has_catalog_dir ? 'Đã có đầy đủ ✓' : 'Chưa có thư mục'; ?></div>
						<span class="ds-kpi-card__meta"><code>wp-content/uploads/despace-catalog/</code></span>
					</div>
					<div class="ds-kpi-card">
						<span class="ds-kpi-card__label">Mô hình 3D &amp; AR</span>
						<div class="ds-kpi-card__value" style="font-size:20px;"><?php echo esc_html( sprintf( '%d GLB · %d USDZ', $glb_count, $usdz_count ) ); ?></div>
						<span class="ds-kpi-card__meta"><code>wp-content/uploads/3d/</code></span>
					</div>
					<div class="ds-kpi-card">
						<span class="ds-kpi-card__label">Gói uploads.zip trên máy chủ</span>
						<div class="ds-kpi-card__value" style="font-size:20px;"><?php echo $detected_zip ? 'Đã phát hiện ✓' : 'Chưa đặt sẵn'; ?></div>
						<span class="ds-kpi-card__meta">
							<?php echo $detected_zip ? esc_html( basename( $detected_zip ) . ' (' . size_format( filesize( $detected_zip ) ) . ')' ) : 'Có thể tải tệp .zip trực tiếp bên dưới'; ?>
						</span>
					</div>
				</div>

				<?php if ( $detected_zip ) : ?>
					<div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px; padding:18px 20px; background:#f0fdf4; border:1px solid #86efac; border-radius:10px; margin-bottom:16px;">
						<div>
							<strong style="color:#166534; font-size:15px;">Phát hiện gói tài sản: <?php echo esc_html( basename( $detected_zip ) ); ?> (<?php echo esc_html( size_format( filesize( $detected_zip ) ) ); ?>)</strong>
							<p style="margin:4px 0 0; color:#15803d; font-size:13.5px;">Bấm nút bên phải để giải nén an toàn vào <code>wp-content/uploads/</code>.</p>
						</div>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<?php wp_nonce_field( 'despace_hub_unzip_nonce' ); ?>
							<input type="hidden" name="action" value="despace_hub_unzip_uploads" />
							<button type="submit" class="ds-btn ds-btn--primary" onclick="this.textContent='Đang giải nén...';">
								Giải nén &amp; Nạp Tài sản ngay
							</button>
						</form>
					</div>
				<?php else : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" style="display:flex; align-items:center; gap:12px; flex-wrap:wrap; padding:16px 18px; background:#faf8f5; border:1px solid #e7e3dc; border-radius:10px;">
						<?php wp_nonce_field( 'despace_hub_unzip_nonce' ); ?>
						<input type="hidden" name="action" value="despace_hub_unzip_uploads" />
						<input type="file" name="upload_zip_file" accept=".zip" required />
						<button type="submit" class="ds-btn ds-btn--dark">Tải lên &amp; Giải nén an toàn</button>
					</form>
				<?php endif; ?>
			</section>

			<section class="ds-panel">
				<div class="ds-panel__header">
					<div>
						<h2 class="ds-panel__title">2. Đồng bộ đường dẫn URL sau khi đổi cổng / tên miền</h2>
						<p class="ds-panel__desc">Quét và thay thế các URL localhost cũ (8088, 8090, 10016, 8080) về đúng tên miền hiện tại <code><?php echo esc_html( home_url() ); ?></code> (bảo toàn dữ liệu serialized).</p>
					</div>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( 'despace_hub_repair_nonce' ); ?>
						<input type="hidden" name="action" value="despace_hub_repair_urls" />
						<button type="submit" class="ds-btn ds-btn--dark">Chạy đồng bộ đường dẫn</button>
					</form>
				</div>
			</section>

			<?php if ( 'local' === $env_type ) : ?>
				<section class="ds-panel">
					<div class="ds-panel__header">
						<div>
							<h2 class="ds-panel__title">3. Tiện ích Môi trường Local (Docker)</h2>
							<p class="ds-panel__desc">Chỉ hiển thị khi <code>WP_ENVIRONMENT_TYPE === 'local'</code> — tự động ẩn trên môi trường Production.</p>
						</div>
					</div>
					<div style="display:flex; gap:10px; flex-wrap:wrap;">
						<a href="http://localhost:8026/" target="_blank" rel="noopener noreferrer" class="ds-btn ds-btn--outline">Mở Mailpit (Hộp thư test) ↗</a>
						<a href="<?php echo esc_url( admin_url( 'site-health.php' ) ); ?>" class="ds-btn ds-btn--outline">WordPress Site Health</a>
						<a href="<?php echo esc_url( admin_url( 'options-permalink.php' ) ); ?>" class="ds-btn ds-btn--outline">Làm mới Permalinks</a>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-status&tab=tools' ) ); ?>" class="ds-btn ds-btn--outline">Công cụ WooCommerce</a>
					</div>
				</section>
			<?php endif; ?>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Legacy fallback callback for despace-showroom-info (normal requests redirect via admin_init).
 */
function despace_render_showroom_info_page() {
	if ( function_exists( 'despace_render_contact_admin_page' ) ) {
		$_GET['tab'] = 'showroom';
		despace_render_contact_admin_page();
		return;
	}
	wp_safe_redirect( admin_url( 'admin.php?page=despace-contact-settings&tab=showroom' ) );
	exit;
}
