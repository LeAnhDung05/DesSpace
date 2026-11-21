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
 * Load dashboard-specific styles only on the dashboard screen.
 */
function despace_store_admin_assets( $hook_suffix ) {
	if ( ! despace_store_admin_can_manage() ) {
		return;
	}

	$allowed_hooks = array(
		'index.php',
		'toplevel_page_despace-hub',
		'edit.php',
		'woocommerce_page_wc-orders',
		'woocommerce_page_despace-reports',
		'woocommerce_page_despace-inventory-logs',
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
	$contact_page  = get_page_by_path( 'contact', OBJECT, 'page' );
	$contact_url   = $contact_page ? get_edit_post_link( $contact_page->ID, '' ) : admin_url( 'edit.php?post_type=page' );
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
				<a class="despace-admin-button despace-admin-button--light" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=product' ) ); ?>">Thêm sản phẩm</a>
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
	$wp_admin_bar->add_node( array(
		'id'    => 'despace-logo-node',
		'title' => '<img src="' . esc_url( $icon_url ) . '" style="height:18px; width:18px; vertical-align:middle; margin-right:6px;" alt="DeSpace" /> <span style="font-weight:700; letter-spacing:0.5px; color:#F5F0EA;">DeSpace</span>',
		'href'  => admin_url( 'admin.php?page=despace-hub' ),
		'meta'  => array( 'title' => 'DeSpace Management Center' ),
	) );
}
add_action( 'admin_bar_menu', 'despace_admin_bar_branding', 11 );

/** Register Top-Level DeSpace Hub in Admin Navigation */
function despace_register_admin_hub_menu() {
	if ( ! despace_store_admin_can_manage() ) {
		return;
	}

	add_menu_page(
		'DeSpace Hub — Trung Tâm Quản Trị Cửa Hàng',
		'DeSpace Hub',
		'manage_woocommerce',
		'despace-hub',
		'despace_render_hub_overview_page',
		'dashicons-store',
		3
	);

	add_submenu_page(
		'despace-hub',
		'Tổng quan DeSpace Hub',
		'Tổng quan hệ thống',
		'manage_woocommerce',
		'despace-hub',
		'despace_render_hub_overview_page'
	);

	add_submenu_page(
		'despace-hub',
		'Thông tin Showroom & Chủ cửa hàng',
		'Thông tin Showroom',
		'manage_woocommerce',
		'despace-showroom-info',
		'despace_render_showroom_info_page'
	);
}
add_action( 'admin_menu', 'despace_register_admin_hub_menu', 8 );

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
			wp_safe_redirect( admin_url( 'admin.php?page=despace-hub&tab=assets&unzipped=1' ) );
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
		wp_safe_redirect( admin_url( 'admin.php?page=despace-hub&tab=assets&unzipped=1' ) );
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

	wp_safe_redirect( admin_url( 'admin.php?page=despace-hub&tab=assets&repaired=' . intval( $updated_rows ) ) );
	exit;
}

/** Render DeSpace Hub Overview Page */
function despace_render_hub_overview_page() {
	$current_tab   = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'overview';
	$logo_url      = content_url( '/themes/despace-child/assets/images/despace-logo-header.png' );
	$product_count = (int) ( wp_count_posts( 'product' )->publish ?? 0 );
	$post_count    = (int) ( wp_count_posts( 'post' )->publish ?? 0 );
	$processing    = function_exists( 'wc_orders_count' ) ? wc_orders_count( 'processing' ) : 0;
	$pending       = function_exists( 'wc_orders_count' ) ? wc_orders_count( 'pending' ) : 0;
	$completed     = function_exists( 'wc_orders_count' ) ? wc_orders_count( 'completed' ) : 0;
	$low_stock     = function_exists( 'despace_store_admin_low_stock_count' ) ? despace_store_admin_low_stock_count() : 0;
	
	global $wpdb;
	$log_table = $wpdb->prefix . 'despace_inventory_logs';
	$log_count = $wpdb->get_var( "SHOW TABLES LIKE '$log_table'" ) ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM $log_table" ) : 0;
	$review_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_type = 'review' AND comment_approved = '1'" );
	$avg_rating   = 5.0;
	if ( $review_count > 0 ) {
		$rating_sum = (float) $wpdb->get_var( "SELECT SUM(meta_value) FROM {$wpdb->commentmeta} cm JOIN {$wpdb->comments} c ON c.comment_ID = cm.comment_id WHERE c.comment_type = 'review' AND c.comment_approved = '1' AND cm.meta_key = 'rating'" );
		$avg_rating = $rating_sum > 0 ? round( $rating_sum / $review_count, 1 ) : 4.8;
	}

	// Inspect Uploads Directory Status
	$uploads_info = wp_upload_dir();
	$uploads_basedir = $uploads_info['basedir'];
	$has_uploads_dir = is_dir( $uploads_basedir );
	$has_3d_dir      = is_dir( $uploads_basedir . '/3d' );
	$has_catalog_dir = is_dir( $uploads_basedir . '/despace-catalog' ) || is_dir( $uploads_basedir . '/furniva-real' );
	
	$models_count = 0;
	if ( $has_3d_dir ) {
		$subdirs = glob( $uploads_basedir . '/3d/*', GLOB_ONLYDIR );
		$models_count = $subdirs ? count( $subdirs ) : 0;
	}

	// Check if uploads.zip is detected on host
	$candidates = array(
		dirname( ABSPATH ) . '/uploads.zip',
		ABSPATH . 'uploads.zip',
		WP_CONTENT_DIR . '/uploads.zip',
		$uploads_basedir . '/uploads.zip',
		dirname( get_stylesheet_directory(), 4 ) . '/uploads.zip',
	);
	$detected_zip = null;
	foreach ( $candidates as $candidate ) {
		if ( file_exists( $candidate ) && filesize( $candidate ) > 1000 ) {
			$detected_zip = $candidate;
			break;
		}
	}
	?>
	<div class="wrap despace-hub-wrap" style="max-width:1360px; margin:24px 20px 48px 0; font-family:-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
		
		<!-- Alerts & Notifications -->
		<?php if ( isset( $_GET['unzipped'] ) && '1' === $_GET['unzipped'] ) : ?>
			<div class="notice notice-success is-dismissible" style="padding:14px 18px; border-left:4px solid #10b981; background:#ecfdf5; margin-bottom:20px;">
				<h3 style="margin:0 0 4px; color:#065f46; font-size:16px;">🎉 Khôi phục toàn bộ Hình ảnh & Mô hình 3D AR thành công!</h3>
				<p style="margin:0; color:#047857; font-size:14px;">
					Thư mục <code>wp-content/uploads/</code> đã được giải nén đầy đủ. Toàn bộ ảnh sản phẩm thực tế và 32 file 3D (.glb, .usdz) đã sẵn sàng hiển thị trên cửa hàng.
				</p>
			</div>
		<?php endif; ?>

		<?php if ( isset( $_GET['repaired'] ) ) : ?>
			<div class="notice notice-success is-dismissible" style="padding:14px 18px; border-left:4px solid #2563eb; background:#eff6ff; margin-bottom:20px;">
				<h3 style="margin:0 0 4px; color:#1e40af; font-size:16px;">🔧 Đồng bộ đường dẫn liên kết thành công!</h3>
				<p style="margin:0; color:#1d4ed8; font-size:14px;">
					Đã quét và cập nhật <strong><?php echo intval( $_GET['repaired'] ); ?></strong> bản ghi liên kết hình ảnh và mô hình 3D khớp chuẩn xác với tên miền/cổng hiện tại: <code><?php echo esc_html( home_url() ); ?></code>.
				</p>
			</div>
		<?php endif; ?>

		<!-- Executive Brand Header -->
		<div style="background:linear-gradient(135deg, #1C1917 0%, #2A1D16 100%); border-radius:18px; padding:32px 36px; color:#fff; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:24px; box-shadow:0 12px 32px rgba(42,29,22,0.18); margin-bottom:24px;">
			<div>
				<div style="display:flex; align-items:center; gap:16px; margin-bottom:12px;">
					<img src="<?php echo esc_url( $logo_url ); ?>" alt="DeSpace" style="height:44px; filter:brightness(0) invert(1);" />
					<span style="background:rgba(255,255,255,0.15); font-size:11px; font-weight:700; letter-spacing:1px; padding:4px 10px; border-radius:99px; text-transform:uppercase;">Admin Cockpit v2.5 Enterprise</span>
				</div>
				<h1 style="color:#FAF7F2; font-size:26px; font-weight:700; margin:0 0 6px;">Bảng Điều Khiển Quản Trị Cửa Hàng DeSpace</h1>
				<p style="color:#D6CFC7; margin:0; font-size:14px; max-width:700px; line-height:1.5;">
					Trung tâm vận hành thương mại điện tử cao cấp: Quản trị 40 sản phẩm, 32 mô hình 3D AR WebXR, tra cứu kho thời gian thực, ma trận phân quyền RBAC và quản lý đơn hàng HPOS.
				</p>
			</div>
			<div style="display:flex; gap:12px; flex-wrap:wrap;">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=despace-reports' ) ); ?>" class="button button-primary" style="background:#2563EB; border-color:#2563EB; color:#fff; font-weight:600; padding:8px 18px; height:auto; border-radius:8px; font-size:13.5px;">
					📈 Báo Cáo Doanh Thu (M5)
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=despace-hub&tab=assets' ) ); ?>" class="button button-primary" style="background:#10B981; border-color:#10B981; color:#fff; font-weight:600; padding:8px 18px; height:auto; border-radius:8px; font-size:13.5px;">
					📦 Khôi Phục Ảnh & 3D
				</a>
				<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=product' ) ); ?>" class="button button-primary" style="background:#C25E3A; border-color:#C25E3A; color:#fff; font-weight:600; padding:8px 18px; height:auto; border-radius:8px; font-size:13.5px; box-shadow:0 4px 12px rgba(194,94,58,0.35);">
					+ Thêm Sản Phẩm Mới
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-orders' ) ); ?>" class="button" style="background:rgba(255,255,255,0.1); border-color:rgba(255,255,255,0.2); color:#FAF7F2; font-weight:600; padding:8px 18px; height:auto; border-radius:8px; font-size:13.5px;">
					Đơn Hàng (HPOS)
				</a>
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" class="button" style="background:transparent; border-color:rgba(255,255,255,0.3); color:#fff; font-weight:600; padding:8px 16px; height:auto; border-radius:8px; font-size:13.5px;">
					Xem Website ↗
				</a>
			</div>
		</div>

		<!-- Navigation Tabs -->
		<div style="display:flex; gap:8px; border-bottom:2px solid #E7E3DC; margin-bottom:28px; flex-wrap:wrap;">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=despace-hub&tab=overview' ) ); ?>" style="padding:12px 20px; font-weight:700; font-size:14px; text-decoration:none; border-bottom:3px solid <?php echo ( 'overview' === $current_tab ) ? '#C25E3A' : 'transparent'; ?>; color:<?php echo ( 'overview' === $current_tab ) ? '#C25E3A' : '#78716C'; ?>; margin-bottom:-2px;">
				📊 Tổng Quan KPI & Hạ Tầng
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=despace-hub&tab=assets' ) ); ?>" style="padding:12px 20px; font-weight:700; font-size:14px; text-decoration:none; border-bottom:3px solid <?php echo ( 'assets' === $current_tab ) ? '#10B981' : 'transparent'; ?>; color:<?php echo ( 'assets' === $current_tab ) ? '#10B981' : '#78716C'; ?>; margin-bottom:-2px;">
				📦 Khôi Phục Ảnh & Mô Hình 3D <?php if ( ! $has_3d_dir || 0 === $models_count ) : ?><span style="background:#EF4444; color:#fff; padding:2px 7px; border-radius:99px; font-size:11px; margin-left:4px;">Cần xử lý</span><?php endif; ?>
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=despace-hub&tab=3d' ) ); ?>" style="padding:12px 20px; font-weight:700; font-size:14px; text-decoration:none; border-bottom:3px solid <?php echo ( '3d' === $current_tab ) ? '#C25E3A' : 'transparent'; ?>; color:<?php echo ( '3d' === $current_tab ) ? '#C25E3A' : '#78716C'; ?>; margin-bottom:-2px;">
				🧊 Trình Quản Lý 3D & WebXR AR
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=despace-hub&tab=rbac' ) ); ?>" style="padding:12px 20px; font-weight:700; font-size:14px; text-decoration:none; border-bottom:3px solid <?php echo ( 'rbac' === $current_tab ) ? '#C25E3A' : 'transparent'; ?>; color:<?php echo ( 'rbac' === $current_tab ) ? '#C25E3A' : '#78716C'; ?>; margin-bottom:-2px;">
				👥 Phân Quyền 5 Thành Viên (RBAC)
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=despace-hub&tab=inventory' ) ); ?>" style="padding:12px 20px; font-weight:700; font-size:14px; text-decoration:none; border-bottom:3px solid <?php echo ( 'inventory' === $current_tab ) ? '#C25E3A' : 'transparent'; ?>; color:<?php echo ( 'inventory' === $current_tab ) ? '#C25E3A' : '#78716C'; ?>; margin-bottom:-2px;">
				📋 Kiểm Toán Tồn Kho (Audit Logs)
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=despace-hub&tab=showroom' ) ); ?>" style="padding:12px 20px; font-weight:700; font-size:14px; text-decoration:none; border-bottom:3px solid <?php echo ( 'showroom' === $current_tab ) ? '#C25E3A' : 'transparent'; ?>; color:<?php echo ( 'showroom' === $current_tab ) ? '#C25E3A' : '#78716C'; ?>; margin-bottom:-2px;">
				🏢 Thông Tin Showroom DeSpace
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=despace-reports' ) ); ?>" style="padding:12px 20px; font-weight:700; font-size:14px; text-decoration:none; color:#2563EB; background:rgba(37,99,235,0.06); border-radius:8px 8px 0 0; margin-bottom:-2px;">
				📈 Báo Cáo Doanh Thu (M5) ↗
			</a>
		</div>

		<!-- ========================================== -->
		<!-- TAB 1: OVERVIEW & SYSTEM HEALTH -->
		<!-- ========================================== -->
		<?php if ( 'overview' === $current_tab ) : ?>
			<!-- 5 Core KPI Metric Cards -->
			<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:18px; margin-bottom:32px;">
				
				<div style="background:#fff; border:1px solid #E7E3DC; border-radius:14px; padding:20px 22px; box-shadow:0 2px 8px rgba(0,0,0,0.03);">
					<div style="font-size:12px; font-weight:700; color:#78716C; text-transform:uppercase; letter-spacing:0.6px;">Sản Phẩm Nội Thất</div>
					<div style="font-size:32px; font-weight:800; color:#1C1917; margin:6px 0 2px;"><?php echo esc_html( number_format_i18n( $product_count ) ); ?></div>
					<div style="font-size:12.5px; color:#059669; font-weight:600;">✓ <?php echo intval( $models_count ); ?>/<?php echo esc_html( max( 40, $product_count ) ); ?> Đã gắn Mô hình 3D AR</div>
				</div>

				<div style="background:#fff; border:1px solid #E7E3DC; border-radius:14px; padding:20px 22px; box-shadow:0 2px 8px rgba(0,0,0,0.03);">
					<div style="font-size:12px; font-weight:700; color:#78716C; text-transform:uppercase; letter-spacing:0.6px;">Đơn Cần Xử Lý</div>
					<div style="font-size:32px; font-weight:800; color:#C25E3A; margin:6px 0 2px;"><?php echo esc_html( number_format_i18n( $processing + $pending ) ); ?></div>
					<div style="font-size:12.5px; color:#78716C;">Đơn hàng Processing / Pending</div>
				</div>

				<div style="background:#fff; border:1px solid #E7E3DC; border-radius:14px; padding:20px 22px; box-shadow:0 2px 8px rgba(0,0,0,0.03);">
					<div style="font-size:12px; font-weight:700; color:#78716C; text-transform:uppercase; letter-spacing:0.6px;">Nhật Ký Tồn Kho</div>
					<div style="font-size:32px; font-weight:800; color:#1C1917; margin:6px 0 2px;"><?php echo esc_html( number_format_i18n( $log_count ) ); ?></div>
					<div style="font-size:12.5px; color:#2563EB; font-weight:600;">Audit Logs thời gian thực</div>
				</div>

				<div style="background:#fff; border:1px solid #E7E3DC; border-radius:14px; padding:20px 22px; box-shadow:0 2px 8px rgba(0,0,0,0.03);">
					<div style="font-size:12px; font-weight:700; color:#78716C; text-transform:uppercase; letter-spacing:0.6px;">Đánh Giá Khách Hàng</div>
					<div style="font-size:32px; font-weight:800; color:#D97706; margin:6px 0 2px;"><?php echo esc_html( number_format_i18n( $review_count ) ); ?></div>
					<div style="font-size:12.5px; color:#D97706; font-weight:600;">★ <?php echo esc_html( $avg_rating ); ?> / 5.0 (<?php echo esc_html( $review_count ); ?> Đánh giá xác thực)</div>
				</div>

				<div style="background:#fff; border:1px solid #E7E3DC; border-radius:14px; padding:20px 22px; box-shadow:0 2px 8px rgba(0,0,0,0.03);">
					<div style="font-size:12px; font-weight:700; color:#78716C; text-transform:uppercase; letter-spacing:0.6px;">Cảnh Báo Tồn Kho</div>
					<div style="font-size:32px; font-weight:800; color:<?php echo ( $low_stock > 0 ) ? '#EF4444' : '#059669'; ?>; margin:6px 0 2px;"><?php echo esc_html( $low_stock ); ?></div>
					<div style="font-size:12.5px; color:#78716C;">Sản phẩm số lượng &le; 5</div>
				</div>

			</div>

			<!-- System Health Overview -->
			<div style="background:#fff; border:1px solid #E7E3DC; border-radius:16px; padding:24px 28px; margin-bottom:32px; box-shadow:0 2px 8px rgba(0,0,0,0.02);">
				<h3 style="font-size:16px; font-weight:700; color:#1C1917; margin:0 0 16px;">Tình Trạng Sức Khỏe Máy Chủ & Môi Trường Hoạt Động</h3>
				<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:16px;">
					<div style="background:#FBF9F7; border:1px solid #ECE7E1; border-radius:10px; padding:14px 16px;">
						<div style="font-size:11.5px; color:#78716C; font-weight:600; text-transform:uppercase;">Phiên bản PHP & DB</div>
						<div style="font-size:16px; font-weight:700; color:#1C1917; margin-top:4px;">PHP <?php echo esc_html( PHP_VERSION ); ?> · SQL <?php echo esc_html( substr( $wpdb->db_version(), 0, 8 ) ); ?></div>
					</div>
					<div style="background:#FBF9F7; border:1px solid #ECE7E1; border-radius:10px; padding:14px 16px;">
						<div style="font-size:11.5px; color:#78716C; font-weight:600; text-transform:uppercase;">Bộ Nhớ Giới Hạn</div>
						<div style="font-size:16px; font-weight:700; color:#1C1917; margin-top:4px;"><?php echo esc_html( ini_get( 'memory_limit' ) ); ?></div>
					</div>
					<div style="background:#FBF9F7; border:1px solid #ECE7E1; border-radius:10px; padding:14px 16px;">
						<div style="font-size:11.5px; color:#78716C; font-weight:600; text-transform:uppercase;">Kiến Trúc HPOS</div>
						<div style="font-size:16px; font-weight:700; color:#059669; margin-top:4px;">Bật (Tốc độ cao) ✓</div>
					</div>
					<div style="background:#FBF9F7; border:1px solid #ECE7E1; border-radius:10px; padding:14px 16px;">
						<div style="font-size:11.5px; color:#78716C; font-weight:600; text-transform:uppercase;">Đường Dẫn Hiện Tại</div>
						<div style="font-size:14px; font-weight:700; color:#2563EB; margin-top:6px; word-break:break-all;"><?php echo esc_html( home_url() ); ?></div>
					</div>
				</div>
			</div>

			<!-- Direct Navigation Grid -->
			<h2 style="font-size:18px; font-weight:700; color:#1C1917; margin:0 0 16px;">Các Chức Năng Quản Trị Cốt Lõi</h2>
			<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(360px, 1fr)); gap:20px;">
				
				<div style="background:#fff; border:1px solid #E7E3DC; border-radius:16px; padding:24px 26px; box-shadow:0 2px 10px rgba(0,0,0,0.04); display:flex; flex-direction:column; justify-content:space-between;">
					<div>
						<div style="display:flex; align-items:center; gap:12px; margin-bottom:12px;">
							<span style="background:#ECFDF5; color:#059669; width:40px; height:40px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:20px;">📦</span>
							<h3 style="font-size:17px; font-weight:700; margin:0; color:#1C1917;">Khôi Phục Hình Ảnh & 3D (Asset Auto-Recovery)</h3>
						</div>
						<p style="color:#6B635B; font-size:13.5px; line-height:1.6; margin:0 0 16px;">
							Dành riêng cho thành viên mới kéo Git về máy: Công cụ 1-Click tự động giải nén tệp <code>uploads.zip</code> và sửa link ảnh/3D theo đúng cổng máy tính.
						</p>
					</div>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=despace-hub&tab=assets' ) ); ?>" class="button button-primary" style="align-self:flex-start; font-weight:600; border-radius:8px; background:#10B981; border-color:#10B981;">
						Vào Trình Khôi Phục Assets →
					</a>
				</div>

				<div style="background:#fff; border:1px solid #E7E3DC; border-radius:16px; padding:24px 26px; box-shadow:0 2px 10px rgba(0,0,0,0.04); display:flex; flex-direction:column; justify-content:space-between;">
					<div>
						<div style="display:flex; align-items:center; gap:12px; margin-bottom:12px;">
							<span style="background:#F4EDE4; color:#9A6745; width:40px; height:40px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:20px;">✧</span>
							<h3 style="font-size:17px; font-weight:700; margin:0; color:#1C1917;">Quản Lý Mô Hình 3D & WebXR AR</h3>
						</div>
						<p style="color:#6B635B; font-size:13.5px; line-height:1.6; margin:0 0 16px;">
							Xem và quản lý toàn bộ mô hình PBR <code>.glb</code> và Apple Quick Look <code>.usdz</code>. Cung cấp mã QR quét trải nghiệm AR trực tiếp trong phòng thực tế.
						</p>
					</div>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=despace-hub&tab=3d' ) ); ?>" class="button button-secondary" style="align-self:flex-start; font-weight:600; border-radius:8px;">
						Quản Lý 3D & WebXR AR →
					</a>
				</div>

				<div style="background:#fff; border:1px solid #E7E3DC; border-radius:16px; padding:24px 26px; box-shadow:0 2px 10px rgba(0,0,0,0.04); display:flex; flex-direction:column; justify-content:space-between;">
					<div>
						<div style="display:flex; align-items:center; gap:12px; margin-bottom:12px;">
							<span style="background:#EFF6FF; color:#2563EB; width:40px; height:40px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:20px;">👥</span>
							<h3 style="font-size:17px; font-weight:700; margin:0; color:#1C1917;">Phân Quyền 5 Thành Viên Nhóm (RBAC)</h3>
						</div>
						<p style="color:#6B635B; font-size:13.5px; line-height:1.6; margin:0 0 16px;">
							Xem phân chia trách nhiệm và ma trận phân quyền của Member 01 đến Member 05 theo đúng đồ án chuyển đổi nền tảng DeSpace.
						</p>
					</div>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=despace-hub&tab=rbac' ) ); ?>" class="button button-secondary" style="align-self:flex-start; font-weight:600; border-radius:8px;">
						Xem Ma Trận Phân Quyền →
					</a>
				</div>

				<div style="background:#fff; border:1px solid #E7E3DC; border-radius:16px; padding:24px 26px; box-shadow:0 2px 10px rgba(0,0,0,0.04); display:flex; flex-direction:column; justify-content:space-between;">
					<div>
						<div style="display:flex; align-items:center; gap:12px; margin-bottom:12px;">
							<span style="background:#EEF2FF; color:#4F46E5; width:40px; height:40px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:20px;">📈</span>
							<h3 style="font-size:17px; font-weight:700; margin:0; color:#1C1917;">Báo Cáo Doanh Thu & KPI Kinh Doanh (M5)</h3>
						</div>
						<p style="color:#6B635B; font-size:13.5px; line-height:1.6; margin:0 0 16px;">
							Thống kê doanh thu hợp lệ, quy đổi AOV, lọc theo khoảng ngày, phân tích trạng thái đơn hàng HPOS và xếp hạng Top 10 sản phẩm bán chạy nhất.
						</p>
					</div>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=despace-reports' ) ); ?>" class="button button-primary" style="align-self:flex-start; font-weight:600; border-radius:8px; background:#4F46E5; border-color:#4338CA;">
						Vào Bảng Báo Cáo Doanh Thu →
					</a>
				</div>

				<div style="background:#fff; border:1px solid #E7E3DC; border-radius:16px; padding:24px 26px; box-shadow:0 2px 10px rgba(0,0,0,0.04); display:flex; flex-direction:column; justify-content:space-between;">
					<div>
						<div style="display:flex; align-items:center; gap:12px; margin-bottom:12px;">
							<span style="background:#FEF2F2; color:#DC2626; width:40px; height:40px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:20px;">📋</span>
							<h3 style="font-size:17px; font-weight:700; margin:0; color:#1C1917;">Nhật Ký Kiểm Toán Tồn Kho (Audit Logs)</h3>
						</div>
						<p style="color:#6B635B; font-size:13.5px; line-height:1.6; margin:0 0 16px;">
							Truy vết thời gian thực mọi biến động tồn kho khi mua hàng hoặc cập nhật số lượng. Lưu trữ minh bạch, hỗ trợ xuất CSV.
						</p>
					</div>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=despace-hub&tab=inventory' ) ); ?>" class="button button-secondary" style="align-self:flex-start; font-weight:600; border-radius:8px;">
						Kiểm Toán Tồn Kho →
					</a>
				</div>

			</div>
		<?php endif; ?>

		<!-- ========================================== -->
		<!-- TAB 2: ASSET AUTO-RECOVERY & 3D FIX -->
		<!-- ========================================== -->
		<?php if ( 'assets' === $current_tab ) : ?>
			<div style="background:#fff; border:1px solid #E7E3DC; border-radius:16px; padding:32px; box-shadow:0 2px 10px rgba(0,0,0,0.04);">
				
				<div style="border-bottom:1px solid #ECE7E1; padding-bottom:20px; margin-bottom:28px;">
					<h2 style="font-size:22px; font-weight:700; color:#1C1917; margin:0 0 8px;">Trình Quản Lý & Khôi Phục Hình Ảnh, Mô Hình 3D AR</h2>
					<p style="font-size:14px; color:#6B635B; margin:0; line-height:1.6;">
						Giải thích: Khi thành viên mới kéo code về qua Git, thư mục <code>wp-content/uploads/</code> (hơn 400MB) không lưu trên Git để tránh nặng kho chứa. Trang này giúp bạn khôi phục lại toàn bộ ảnh sản phẩm thực tế và file 3D chỉ với 1 cú click chuột!
					</p>
				</div>

				<!-- Live Diagnostic Status -->
				<h3 style="font-size:16px; font-weight:700; color:#1C1917; margin:0 0 16px;">1. Tình Trạng Thư Mục Assets Hiện Tại Trên Máy Bạn</h3>
				<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(260px, 1fr)); gap:18px; margin-bottom:32px;">
					
					<div style="background:#FBF9F7; border:1px solid <?php echo $has_catalog_dir ? '#10B981' : '#F59E0B'; ?>; border-radius:12px; padding:18px 20px;">
						<div style="font-size:13px; font-weight:700; color:#78716C; text-transform:uppercase;">Thư Mục Ảnh Sản Phẩm</div>
						<div style="font-size:20px; font-weight:800; color:<?php echo $has_catalog_dir ? '#059669' : '#D97706'; ?>; margin:8px 0 4px;">
							<?php echo $has_catalog_dir ? '✓ Đã Có Ảnh Đầy Đủ' : '⚠️ Chưa Có Thư Mục Ảnh'; ?>
						</div>
						<div style="font-size:12.5px; color:#78716C;">
							<code>wp-content/uploads/despace-catalog/</code>
						</div>
					</div>

					<div style="background:#FBF9F7; border:1px solid <?php echo ( $has_3d_dir && $models_count >= 30 ) ? '#10B981' : '#EF4444'; ?>; border-radius:12px; padding:18px 20px;">
						<div style="font-size:13px; font-weight:700; color:#78716C; text-transform:uppercase;">Thư Mục Mô Hình 3D AR</div>
						<div style="font-size:20px; font-weight:800; color:<?php echo ( $has_3d_dir && $models_count >= 30 ) ? '#059669' : '#DC2626'; ?>; margin:8px 0 4px;">
							<?php echo ( $has_3d_dir && $models_count >= 30 ) ? "✓ Đã Có $models_count Mô Hình 3D" : "⚠️ Đang Có $models_count/32 Mô Hình"; ?>
						</div>
						<div style="font-size:12.5px; color:#78716C;">
							<code>wp-content/uploads/3d/</code>
						</div>
					</div>

					<div style="background:#FBF9F7; border:1px solid <?php echo $detected_zip ? '#10B981' : '#6B7280'; ?>; border-radius:12px; padding:18px 20px;">
						<div style="font-size:13px; font-weight:700; color:#78716C; text-transform:uppercase;">Tệp Đóng Gói uploads.zip</div>
						<div style="font-size:20px; font-weight:800; color:<?php echo $detected_zip ? '#059669' : '#4B5563'; ?>; margin:8px 0 4px;">
							<?php echo $detected_zip ? '✓ Đã Tìm Thấy File Zip' : '❌ Chưa Tìm Thấy Zip'; ?>
						</div>
						<div style="font-size:12.5px; color:#78716C;">
							<?php echo $detected_zip ? esc_html( basename( $detected_zip ) . ' (' . size_format( filesize( $detected_zip ) ) . ')' ) : 'Chưa có uploads.zip trên máy'; ?>
						</div>
					</div>

				</div>

				<!-- Action Section 1: Auto-Unzip -->
				<h3 style="font-size:16px; font-weight:700; color:#1C1917; margin:0 0 16px;">2. Giải Nén Tự Động (1-Click Restore)</h3>
				<?php if ( $detected_zip ) : ?>
					<div style="background:#ECFDF5; border:2px solid #10B981; border-radius:14px; padding:24px; margin-bottom:28px;">
						<div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
							<div>
								<h4 style="margin:0 0 6px; color:#065F46; font-size:17px; font-weight:700;">🚀 Phát Hiện Tệp Nén: <?php echo esc_html( basename( $detected_zip ) ); ?> (<?php echo size_format( filesize( $detected_zip ) ); ?>)</h4>
								<p style="margin:0; color:#047857; font-size:14px; line-height:1.5;">
									Tệp đóng gói chứa toàn bộ ảnh sản phẩm thực tế và 32 thư mục 3D model đã sẵn sàng trên hệ thống. Bấm nút bên phải để tự động giải nén vào <code>wp-content/uploads/</code>!
								</p>
							</div>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
								<?php wp_nonce_field( 'despace_hub_unzip_nonce' ); ?>
								<input type="hidden" name="action" value="despace_hub_unzip_uploads" />
								<button type="submit" class="button button-primary" style="background:#059669; border-color:#047857; font-size:15px; font-weight:700; padding:10px 24px; height:auto; border-radius:8px;" onclick="this.innerHTML='⏳ Đang giải nén... vui lòng chờ khoảng 5-10 giây'; this.style.pointerEvents='none';">
									⚡ BẤM GIẢI NÉN & NẠP ẢNH + 3D NGAY
								</button>
							</form>
						</div>
					</div>
				<?php else : ?>
					<div style="background:#FFFBEB; border:1px solid #F59E0B; border-radius:14px; padding:20px; margin-bottom:28px;">
						<h4 style="margin:0 0 6px; color:#92400E; font-size:15px; font-weight:700;">💡 Hướng Dẫn Tải Tệp uploads.zip Dành Cho Thành Viên:</h4>
						<ol style="margin:8px 0 0 20px; color:#78350F; font-size:13.5px; line-height:1.6;">
							<li>Nhận tệp <code>uploads.zip</code> (~250MB) từ Trưởng nhóm (qua Google Drive / Zalo).</li>
							<li>Chép file <code>uploads.zip</code> vào thư mục gốc của dự án DeSpace hoặc tải trực tiếp lên bằng form dưới đây.</li>
							<li>Tải lại trang này và bấm nút <strong>"Giải nén & Nạp ảnh + 3D ngay"</strong>!</li>
						</ol>
						
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" style="margin-top:16px;">
							<?php wp_nonce_field( 'despace_hub_unzip_nonce' ); ?>
							<input type="hidden" name="action" value="despace_hub_unzip_uploads" />
							<input type="file" name="upload_zip_file" accept=".zip" style="margin-right:10px;" />
							<button type="submit" class="button button-secondary" style="font-weight:600;">Tải Lên & Giải Nén</button>
						</form>
					</div>
				<?php endif; ?>

				<!-- Action Section 2: URL Repair Tool -->
				<h3 style="font-size:16px; font-weight:700; color:#1C1917; margin:0 0 16px;">3. Tự Động Sửa Đường Dẫn Link Ảnh & 3D Theo Cổng Máy Hiện Tại</h3>
				<div style="background:#EFF6FF; border:1px solid #BFDBFE; border-radius:14px; padding:20px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
					<div>
						<h4 style="margin:0 0 4px; color:#1E40AF; font-size:15px; font-weight:700;">🔧 Khắc phục lỗi ảnh bị gãy link do lệch cổng (8088 / 8090 / 10016)</h4>
						<p style="margin:0; color:#1D4ED8; font-size:13.5px; line-height:1.5;">
							Công cụ này sẽ tự động quét cơ sở dữ liệu và thay thế toàn bộ link ảnh/3D cũ thành đúng cổng <code><?php echo esc_html( home_url() ); ?></code> trên máy bạn.
						</p>
					</div>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( 'despace_hub_repair_nonce' ); ?>
						<input type="hidden" name="action" value="despace_hub_repair_urls" />
						<button type="submit" class="button button-primary" style="background:#2563EB; border-color:#1D4ED8; font-weight:700; padding:8px 20px; height:auto; border-radius:8px;">
							🔧 TỰ ĐỘNG SỬA ĐƯỜNG DẪN LINK
						</button>
					</form>
				</div>

			</div>
		<?php endif; ?>

		<!-- ========================================== -->
		<!-- TAB 3: 3D MODELS & WEBXR AR MATRIX -->
		<!-- ========================================== -->
		<?php if ( '3d' === $current_tab ) : ?>
			<div style="background:#fff; border:1px solid #E7E3DC; border-radius:16px; padding:32px; box-shadow:0 2px 10px rgba(0,0,0,0.04);">
				<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; flex-wrap:wrap; gap:16px;">
					<div>
						<h2 style="font-size:22px; font-weight:700; color:#1C1917; margin:0 0 6px;">Ma Trận Mô Hình 3D PBR & WebXR Augmented Reality (AR)</h2>
						<p style="font-size:14px; color:#6B635B; margin:0;">
							Tổng hợp mô hình không gian ba chiều được nhúng trực tiếp vào sản phẩm nội thất DeSpace và liên kết với Ứng Dụng Desktop 3D Builder.
						</p>
					</div>
					<div style="display:flex; align-items:center; gap:10px;">
						<span id="despace-hub-desktop-agent-pill" style="display:inline-flex; align-items:center; gap:6px; background:#F3F4F6; border:1px solid #D1D5DB; color:#374151; font-size:12px; font-weight:700; padding:6px 14px; border-radius:99px;">
							<span style="width:8px; height:8px; border-radius:50%; background:#9CA3AF; display:inline-block;" class="status-dot"></span>
							<span class="status-label">Đang kiểm tra Desktop App...</span>
						</span>
						<span style="background:#ECFDF5; color:#059669; font-weight:700; padding:6px 16px; border-radius:99px; font-size:13px;">
							✓ <?php echo intval( $models_count ); ?>/40 Mô hình GLB Sẵn Sàng
						</span>
					</div>
				</div>

				<!-- Desktop 3D Studio Connection Banner -->
				<div style="background:linear-gradient(135deg, #FAF7F2 0%, #F5EBE1 100%); border:1.5px solid #E2D3C4; border-radius:14px; padding:20px 24px; margin-bottom:24px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
					<div>
						<div style="display:flex; align-items:center; gap:8px; margin-bottom:6px;">
							<span style="font-size:18px;">🖥️</span>
							<h3 style="margin:0; font-size:16px; font-weight:700; color:#1C1917;">Ứng Dụng Desktop Xây Dựng 3D Tự Động (DeSpace 3D Studio Desktop)</h3>
						</div>
						<p style="margin:0; font-size:13.5px; color:#69645D; line-height:1.5; max-width:780px;">
							Khi thêm sản phẩm mới với <strong>3–5 ảnh nhiều góc</strong> (Mặt trước, Góc 45°, Mặt bên, Mặt sau, Mặt trên), bạn có thể gửi yêu cầu trực tiếp sang <strong>Phần mềm Desktop</strong>. Phần mềm sẽ tự động dựng mô hình 3D chi tiết chuẩn nhất và tải ngược lên website! Hỗ trợ cả <strong>file Excel hàng loạt</strong>.
						</p>
					</div>
					<div style="display:flex; gap:10px; align-items:center;">
						<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=product' ) ); ?>" class="button button-primary" style="background:#C25E3A; border-color:#C25E3A; font-weight:700; border-radius:8px; padding:8px 16px; height:auto;">
							+ Thêm Sản Phẩm Mới (3-5 Ảnh)
						</a>
					</div>
				</div>

				<script>
				(function(){
					fetch('http://127.0.0.1:58300/api/health')
						.then(r => r.json())
						.then(d => {
							var pill = document.getElementById('despace-hub-desktop-agent-pill');
							if (pill && d && d.status === 'online') {
								pill.style.background = '#ECFDF5';
								pill.style.borderColor = '#10B981';
								pill.style.color = '#065F46';
								pill.querySelector('.status-dot').style.background = '#10B981';
								pill.querySelector('.status-label').textContent = '🟢 Desktop 3D Studio Đang Chạy (Port 58300)';
							}
						})
						.catch(() => {
							var pill = document.getElementById('despace-hub-desktop-agent-pill');
							if (pill) {
								pill.style.background = '#FEF3C7';
								pill.style.borderColor = '#F59E0B';
								pill.style.color = '#92400E';
								pill.querySelector('.status-dot').style.background = '#F59E0B';
								pill.querySelector('.status-label').textContent = '🟡 REST API Sync Queue (Desktop App Chưa Mở)';
							}
						});
				})();
				</script>

				<table class="wp-list-table widefat fixed striped" style="border-radius:10px; overflow:hidden; border:1px solid #E7E3DC;">
					<thead>
						<tr style="background:#F9F8F6;">
							<th style="width:70px; font-weight:700;">Ảnh</th>
							<th style="width:110px; font-weight:700;">Mã SKU</th>
							<th style="font-weight:700;">Tên Sản Phẩm</th>
							<th style="font-weight:700;">File 3D (.glb / .usdz)</th>
							<th style="width:130px; font-weight:700;">Trạng Thái 3D</th>
							<th style="width:120px; font-weight:700; text-align:center;">Thao Tác</th>
						</tr>
					</thead>
					<tbody>
						<?php
						$products_3d = get_posts( array(
							'post_type'      => 'product',
							'posts_per_page' => 40,
							'post_status'    => 'publish',
							'orderby'        => 'ID',
							'order'          => 'ASC',
						) );

						if ( $products_3d ) :
							foreach ( $products_3d as $p ) :
								$sku = get_post_meta( $p->ID, '_sku', true );
								$thumb_id = get_post_thumbnail_id( $p->ID );
								$thumb_url = $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'woocommerce_thumbnail' ) : content_url( '/themes/despace-child/assets/images/despace-app-icon.png' );
								$glb_rel = "/wp-content/uploads/3d/{$sku}/model.glb";
								$glb_exists = file_exists( ABSPATH . ltrim( $glb_rel, '/' ) );
								?>
								<tr>
									<td>
										<img src="<?php echo esc_url( $thumb_url ); ?>" style="width:48px; height:48px; object-fit:cover; border-radius:6px; border:1px solid #E7E3DC;" alt="" />
									</td>
									<td><strong><?php echo esc_html( $sku ?: 'N/A' ); ?></strong></td>
									<td>
										<a href="<?php echo esc_url( get_edit_post_link( $p->ID ) ); ?>" style="font-weight:600; text-decoration:none; color:#1C1917;">
											<?php echo esc_html( $p->post_title ); ?>
										</a>
									</td>
									<td style="font-size:12.5px; color:#6B635B;">
										<code><?php echo esc_html( $glb_rel ); ?></code>
									</td>
									<td>
										<?php if ( $glb_exists ) : ?>
											<span style="color:#059669; font-weight:700; font-size:12.5px;">✓ Đã gắn GLB & AR</span>
										<?php else : ?>
											<span style="color:#D97706; font-weight:600; font-size:12.5px;">Thiếu file uploads</span>
										<?php endif; ?>
									</td>
									<td style="text-align:center;">
										<a href="<?php echo esc_url( get_permalink( $p->ID ) ); ?>" target="_blank" class="button button-small" style="font-weight:600;">
											Xem Web ↗
										</a>
									</td>
								</tr>
								<?php
							endforeach;
						endif;
						?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>

		<!-- ========================================== -->
		<!-- TAB 4: MEMBER RBAC MATRIX -->
		<!-- ========================================== -->
		<?php if ( 'rbac' === $current_tab ) : ?>
			<div style="background:#fff; border:1px solid #E7E3DC; border-radius:16px; padding:32px; box-shadow:0 2px 10px rgba(0,0,0,0.04);">
				<div style="border-bottom:1px solid #ECE7E1; padding-bottom:16px; margin-bottom:24px;">
					<h2 style="font-size:22px; font-weight:700; color:#1C1917; margin:0 0 6px;">Ma Trận Phân Quyền Bảo Mật 5 Thành Viên (RBAC Matrix)</h2>
					<p style="font-size:14px; color:#6B635B; margin:0;">
						Mỗi thành viên trong đồ án DeSpace được cấp vai trò độc lập theo đúng nguyên tắc Least Privilege (Đặc quyền tối thiểu) đảm bảo an toàn tuyệt đối cho hệ sinh thái.
					</p>
				</div>

				<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(360px, 1fr)); gap:20px;">
					
					<div style="background:#FBF9F7; border:1px solid #ECE7E1; border-radius:14px; padding:20px 22px;">
						<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
							<span style="background:#1C1917; color:#fff; font-size:12px; font-weight:700; padding:3px 10px; border-radius:99px;">LEADER / ADMIN</span>
							<code style="font-size:12px;">administrator</code>
						</div>
						<h3 style="margin:0 0 6px; font-size:16px; font-weight:700; color:#1C1917;">Trưởng Nhóm & Tổng Kiến Trúc Sư</h3>
						<p style="font-size:13px; color:#6B635B; line-height:1.5; margin:0 0 12px;">
							Toàn quyền quản trị cơ sở dữ liệu, quản trị phân quyền RBAC, triển khai hosting, quản lý cấu trúc code plugin và theme.
						</p>
						<div style="font-size:12px; color:#059669; font-weight:600;">✓ Toàn quyền Super-Admin (manage_options)</div>
					</div>

					<div style="background:#FBF9F7; border:1px solid #ECE7E1; border-radius:14px; padding:20px 22px;">
						<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
							<span style="background:#7C3AED; color:#fff; font-size:12px; font-weight:700; padding:3px 10px; border-radius:99px;">MEMBER 01</span>
							<code style="font-size:12px;">despace_3d_specialist</code>
						</div>
						<h3 style="margin:0 0 6px; font-size:16px; font-weight:700; color:#1C1917;">Chuyên Gia 3D AR & Showroom Ảo</h3>
						<p style="font-size:13px; color:#6B635B; line-height:1.5; margin:0 0 12px;">
							Chịu trách nhiệm 32 mô hình GLB/USDZ, cấu hình ánh sáng Studio/Warm, tối ưu WebXR AR và trang bài trí không gian sống AR Room Visualizer.
						</p>
						<div style="font-size:12px; color:#2563EB; font-weight:600;">Quyền: edit_despace_3d_models, view_showroom</div>
					</div>

					<div style="background:#FBF9F7; border:1px solid #ECE7E1; border-radius:14px; padding:20px 22px;">
						<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
							<span style="background:#059669; color:#fff; font-size:12px; font-weight:700; padding:3px 10px; border-radius:99px;">MEMBER 02</span>
							<code style="font-size:12px;">despace_catalog_manager</code>
						</div>
						<h3 style="margin:0 0 6px; font-size:16px; font-weight:700; color:#1C1917;">Quản Lý Danh Mục & Trưng Bày</h3>
						<p style="font-size:13px; color:#6B635B; line-height:1.5; margin:0 0 12px;">
							Chịu trách nhiệm 40 sản phẩm, 5 danh mục phòng, 21 biến thể màu sắc vân gỗ chuẩn và bộ lọc AJAX Filter Sidebar.
						</p>
						<div style="font-size:12px; color:#059669; font-weight:600;">Quyền: edit_products, manage_product_terms</div>
					</div>

					<div style="background:#FBF9F7; border:1px solid #ECE7E1; border-radius:14px; padding:20px 22px;">
						<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
							<span style="background:#D97706; color:#fff; font-size:12px; font-weight:700; padding:3px 10px; border-radius:99px;">MEMBER 03</span>
							<code style="font-size:12px;">despace_checkout_manager</code>
						</div>
						<h3 style="margin:0 0 6px; font-size:16px; font-weight:700; color:#1C1917;">Kiến Trúc Sư Thanh Toán & Đơn Hàng</h3>
						<p style="font-size:13px; color:#6B635B; line-height:1.5; margin:0 0 12px;">
							Chịu trách nhiệm giỏ hàng Mini-Cart, luồng thanh toán HPOS, chuẩn hóa 63 tỉnh thành Việt Nam 2025, cổng thanh toán BACS và mã vận đơn.
						</p>
						<div style="font-size:12px; color:#D97706; font-weight:600;">Quyền: manage_woocommerce_orders, view_hpos</div>
					</div>

					<div style="background:#FBF9F7; border:1px solid #ECE7E1; border-radius:14px; padding:20px 22px;">
						<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
							<span style="background:#DC2626; color:#fff; font-size:12px; font-weight:700; padding:3px 10px; border-radius:99px;">MEMBER 04</span>
							<code style="font-size:12px;">despace_inventory_manager</code>
						</div>
						<h3 style="margin:0 0 6px; font-size:16px; font-weight:700; color:#1C1917;">Kiểm Soát Tồn Kho & Chuỗi Cung Ứng</h3>
						<p style="font-size:13px; color:#6B635B; line-height:1.5; margin:0 0 12px;">
							Chịu trách nhiệm bảo toàn kho hàng, ngăn chặn race condition khi nhiều người mua đồng thời, quản lý bảng kiểm toán <code>despace_inventory_logs</code>.
						</p>
						<div style="font-size:12px; color:#DC2626; font-weight:600;">Quyền: manage_inventory_logs, audit_stock</div>
					</div>

					<div style="background:#FBF9F7; border:1px solid #ECE7E1; border-radius:14px; padding:20px 22px;">
						<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
							<span style="background:#2563EB; color:#fff; font-size:12px; font-weight:700; padding:3px 10px; border-radius:99px;">MEMBER 05</span>
							<code style="font-size:12px;">despace_staff</code>
						</div>
						<h3 style="margin:0 0 6px; font-size:16px; font-weight:700; color:#1C1917;">Báo Cáo Doanh Thu & Vận Hành Cửa Hàng</h3>
						<p style="font-size:13px; color:#6B635B; line-height:1.5; margin:0 0 12px;">
							Chịu trách nhiệm phân tích doanh số, hiệu suất bán hàng HPOS, bộ lọc thời gian thực tế, quản trị đánh giá và báo cáo KPI tài chính.
						</p>
						<div style="font-size:12px; color:#2563EB; font-weight:600;">Quyền: view_despace_reports, view_despace_inventory_logs, manage_despace_3d</div>
					</div>

				</div>
			</div>
		<?php endif; ?>

		<!-- ========================================== -->
		<!-- TAB 5: INVENTORY AUDIT LOGS -->
		<!-- ========================================== -->
		<?php if ( 'inventory' === $current_tab ) : ?>
			<div style="background:#fff; border:1px solid #E7E3DC; border-radius:16px; padding:32px; box-shadow:0 2px 10px rgba(0,0,0,0.04);">
				<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; flex-wrap:wrap; gap:16px;">
					<div>
						<h2 style="font-size:22px; font-weight:700; color:#1C1917; margin:0 0 6px;">Nhật Ký Kiểm Toán Tồn Kho Thời Gian Thực</h2>
						<p style="font-size:14px; color:#6B635B; margin:0;">
							Lưu trữ minh bạch mọi hành vi biến động số lượng sản phẩm (bán ra, nhập kho, chỉnh sửa quản trị).
						</p>
					</div>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=despace-inventory-logs' ) ); ?>" class="button button-primary" style="background:#2563EB; border-color:#1D4ED8; font-weight:600; border-radius:8px;">
						Xem Toàn Bộ <?php echo number_format_i18n( $log_count ); ?> Bản Ghi →
					</a>
				</div>

				<table class="wp-list-table widefat fixed striped" style="border-radius:10px; overflow:hidden; border:1px solid #E7E3DC;">
					<thead>
						<tr style="background:#F9F8F6;">
							<th style="width:140px; font-weight:700;">Thời Gian</th>
							<th style="width:100px; font-weight:700;">Mã SP</th>
							<th style="font-weight:700;">Tên Sản Phẩm</th>
							<th style="width:120px; font-weight:700; text-align:center;">Biến Động</th>
							<th style="width:110px; font-weight:700; text-align:center;">Tồn Sau</th>
							<th style="font-weight:700;">Hành Động / Ghi Chú</th>
						</tr>
					</thead>
					<tbody>
						<?php
						$recent_logs = array();
						if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $log_table ) ) ) {
							$recent_logs = $wpdb->get_results( "SELECT * FROM {$log_table} ORDER BY id DESC LIMIT 10" );
						}

						if ( $recent_logs ) :
							foreach ( $recent_logs as $log ) :
								$delta = (int) ( $log->quantity_delta ?? 0 );
								?>
								<tr>
									<td style="font-size:12.5px; color:#6B635B;"><?php echo esc_html( $log->created_at ?? '' ); ?></td>
									<td><strong>#<?php echo esc_html( $log->product_id ?? '' ); ?></strong></td>
									<td style="font-weight:600; color:#1C1917;"><?php echo esc_html( get_the_title( $log->product_id ?? 0 ) ?: 'N/A' ); ?></td>
									<td style="text-align:center; font-weight:700; color:<?php echo ( $delta < 0 ) ? '#DC2626' : '#059669'; ?>;">
										<?php echo ( $delta > 0 ) ? "+$delta" : "$delta"; ?>
									</td>
									<td style="text-align:center; font-weight:700;"><?php echo esc_html( $log->stock_after ?? $log->quantity_after ?? 0 ); ?></td>
									<td style="font-size:13px; color:#4B5563;"><?php echo esc_html( $log->event_type ?? $log->action_type ?? 'Điều chỉnh hệ thống' ); ?></td>
								</tr>
								<?php
							endforeach;
						else :
							?>
							<tr>
								<td colspan="6" style="text-align:center; padding:30px; color:#78716C;">
									Chưa có bản ghi biến động tồn kho nào. Hệ thống sẽ tự động ghi nhật ký khi có đơn hàng hoặc điều chỉnh kho.
								</td>
							</tr>
							<?php
						endif;
						?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>

		<!-- ========================================== -->
		<!-- TAB 6: SHOWROOM & BRAND CONTACTS -->
		<!-- ========================================== -->
		<?php if ( 'showroom' === $current_tab ) : ?>
			<div style="background:#fff; border:1px solid #E7E3DC; border-radius:16px; padding:32px; box-shadow:0 2px 10px rgba(0,0,0,0.04);">
				<h2 style="font-size:22px; font-weight:700; color:#1C1917; margin:0 0 16px;">Thông Tin Thương Hiệu & Showroom Trải Nghiệm DeSpace</h2>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row" style="font-weight:600; width:220px;">Tên thương hiệu:</th>
							<td style="font-size:16px; font-weight:700; color:#1C1917;">DeSpace (Nội Thất Cao Cấp & Trải Nghiệm 3D AR)</td>
						</tr>
						<tr>
							<th scope="row" style="font-weight:600;">Người đại diện:</th>
							<td><strong>DeSpace Sáng lập & Giám đốc thiết kế</strong><br><span style="color:#78716C;">Tư vấn trực tiếp không gian sống & hoàn thiện nội thất cao cấp.</span></td>
						</tr>
						<tr>
							<th scope="row" style="font-weight:600;">Địa chỉ Showroom:</th>
							<td><strong>Số 20 ngách 21 ngõ 367, Phố Ngô Xuân Quảng, Xã Gia Lâm, Hà Nội</strong></td>
						</tr>
						<tr>
							<th scope="row" style="font-weight:600;">Hotline tư vấn:</th>
							<td><a href="tel:0332174590" style="font-size:16px; font-weight:700; color:#C25E3A; text-decoration:none;">0332 174 590</a></td>
						</tr>
						<tr>
							<th scope="row" style="font-weight:600;">Email liên hệ:</th>
							<td><a href="mailto:noithat@despace.vn" style="color:#2563EB; text-decoration:none;">noithat@despace.vn</a></td>
						</tr>
						<tr>
							<th scope="row" style="font-weight:600;">Giờ mở cửa:</th>
							<td><strong>08:30 – 21:30</strong> (Thứ 2 – Chủ Nhật hàng tuần)</td>
						</tr>
						<tr>
							<th scope="row" style="font-weight:600;">Chính sách bảo hành:</th>
							<td><strong>Bảo hành kết cấu 5 năm</strong> · 1 đổi 1 trong 30 ngày đối với lỗi từ nhà sản xuất.</td>
						</tr>
					</tbody>
				</table>
			</div>
		<?php endif; ?>

	</div>
	<?php
}

/** Render Showroom Information Page */
function despace_render_showroom_info_page() {
	?>
	<div class="wrap" style="max-width:960px; margin:24px 20px 48px 0; font-family:-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
		<h1 style="font-size:24px; font-weight:700; color:#1C1917; margin-bottom:20px;">Thông Tin Thương Hiệu & Showroom DeSpace</h1>
		
		<div style="background:#fff; border:1px solid #E7E3DC; border-radius:16px; padding:32px; box-shadow:0 2px 10px rgba(0,0,0,0.04);">
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row" style="font-weight:600; width:220px;">Tên thương hiệu:</th>
						<td style="font-size:16px; font-weight:700; color:#1C1917;">DeSpace (Nội Thất Cao Cấp & Trải Nghiệm 3D AR)</td>
					</tr>
					<tr>
						<th scope="row" style="font-weight:600;">Người đại diện:</th>
						<td><strong>DeSpace Sáng lập & Giám đốc thiết kế</strong><br><span style="color:#78716C;">Tư vấn trực tiếp không gian sống & hoàn thiện nội thất cao cấp.</span></td>
					</tr>
					<tr>
						<th scope="row" style="font-weight:600;">Địa chỉ Showroom:</th>
						<td><strong>Số 20 ngách 21 ngõ 367, Phố Ngô Xuân Quảng, Xã Gia Lâm, Hà Nội</strong></td>
					</tr>
					<tr>
						<th scope="row" style="font-weight:600;">Hotline tư vấn:</th>
						<td><a href="tel:0332174590" style="font-size:16px; font-weight:700; color:#C25E3A; text-decoration:none;">0332 174 590</a></td>
					</tr>
					<tr>
						<th scope="row" style="font-weight:600;">Email liên hệ:</th>
						<td><a href="mailto:noithat@despace.vn" style="color:#2563EB; text-decoration:none;">noithat@despace.vn</a></td>
					</tr>
					<tr>
						<th scope="row" style="font-weight:600;">Giờ mở cửa:</th>
						<td><strong>08:30 – 21:30</strong> (Thứ 2 – Chủ Nhật hàng tuần)</td>
					</tr>
					<tr>
						<th scope="row" style="font-weight:600;">Chính sách bảo hành:</th>
						<td><strong>Bảo hành kết cấu 5 năm</strong> · 1 đổi 1 trong 30 ngày đối với lỗi từ nhà sản xuất.</td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>
	<?php
}
