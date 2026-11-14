<?php
/**
 * Furniva store dashboard for WordPress administrators.
 *
 * @package Furniva
 */

defined( 'ABSPATH' ) || exit;

/**
 * Keep store controls visible only to people who can manage the shop.
 */
function furniva_store_admin_can_manage() {
	if ( ! function_exists( 'wp_get_current_user' ) ) {
		return false;
	}

	return current_user_can( 'manage_woocommerce' ) || current_user_can( 'manage_options' );
}

/**
 * Localize the most-used WordPress menu labels for the store owner.
 */
function furniva_store_admin_localize_menu() {
	if ( ! furniva_store_admin_can_manage() ) {
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
add_action( 'admin_menu', 'furniva_store_admin_localize_menu', 9999 );

/**
 * Translate the dashboard heading and browser title without changing the public site language.
 */
function furniva_store_admin_dashboard_heading( $translation, $text, $domain ) {
	if ( furniva_store_admin_can_manage() && 'index.php' === ( $GLOBALS['pagenow'] ?? '' ) && 'default' === $domain && 'Dashboard' === $text ) {
		return 'Tổng quan cửa hàng';
	}

	if ( 'woocommerce' !== $domain || ! furniva_store_admin_can_manage() || ! function_exists( 'get_current_screen' ) ) {
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
add_filter( 'gettext', 'furniva_store_admin_dashboard_heading', 20, 3 );

/**
 * Apply the same admin labels to WooCommerce strings translated with context.
 *
 * @param string $translation Translated text.
 * @param string $text        Original text.
 * @param string $context     Translation context.
 * @param string $domain      Text domain.
 * @return string
 */
function furniva_store_admin_dashboard_heading_with_context( $translation, $text, $context, $domain ) {
	return furniva_store_admin_dashboard_heading( $translation, $text, $domain );
}
add_filter( 'gettext_with_context', 'furniva_store_admin_dashboard_heading_with_context', 20, 4 );

/**
 * Put the most useful WooCommerce product columns first without removing plugin columns.
 *
 * @param array $columns Product list table columns.
 * @return array
 */
function furniva_store_admin_product_columns( $columns ) {
	if ( ! furniva_store_admin_can_manage() || ! is_array( $columns ) ) {
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
add_filter( 'manage_product_posts_columns', 'furniva_store_admin_product_columns', 99 );

/**
 * Put order identity, customer, status, date, and total before secondary information.
 *
 * @param array $columns Order list table columns.
 * @return array
 */
function furniva_store_admin_order_columns( $columns ) {
	if ( ! furniva_store_admin_can_manage() || ! is_array( $columns ) ) {
		return $columns;
	}

	$columns['furniva_customer'] = 'Khách hàng';
	$labels                     = array(
		'order_number'     => 'Mã đơn',
		'order_date'       => 'Ngày đặt',
		'order_status'     => 'Trạng thái',
		'billing_address'  => 'Thông tin thanh toán',
		'shipping_address' => 'Địa chỉ giao',
		'order_total'      => 'Tổng tiền',
		'wc_actions'       => 'Thao tác',
	);
	$preferred                  = array( 'cb', 'order_number', 'furniva_customer', 'order_status', 'order_date', 'order_total', 'billing_address', 'shipping_address', 'wc_actions' );
	$ordered                    = array();

	foreach ( $preferred as $key ) {
		if ( isset( $columns[ $key ] ) ) {
			$ordered[ $key ] = isset( $labels[ $key ] ) ? $labels[ $key ] : $columns[ $key ];
			unset( $columns[ $key ] );
		}
	}

	return array_merge( $ordered, $columns );
}
add_filter( 'woocommerce_shop_order_list_table_columns', 'furniva_store_admin_order_columns', 99 );
add_filter( 'manage_shop_order_posts_columns', 'furniva_store_admin_order_columns', 99 );

/**
 * Render useful customer contact details in the order table.
 *
 * @param WC_Order|false $order WooCommerce order.
 * @return void
 */
function furniva_store_admin_render_order_customer( $order ) {
	if ( ! class_exists( 'WC_Order' ) || ! ( $order instanceof WC_Order ) ) {
		return;
	}

	$name  = $order->get_formatted_billing_full_name();
	$email = $order->get_billing_email();
	$phone = $order->get_billing_phone();
	?>
	<div class="furniva-order-customer">
		<strong class="furniva-order-customer__name"><?php echo esc_html( $name ? $name : 'Khách mua lẻ' ); ?></strong>
		<?php if ( $email ) : ?>
			<a class="furniva-order-customer__detail" href="mailto:<?php echo esc_attr( $email ); ?>"><?php echo esc_html( $email ); ?></a>
		<?php endif; ?>
		<?php if ( $phone ) : ?>
			<a class="furniva-order-customer__detail" href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) ); ?>"><?php echo esc_html( $phone ); ?></a>
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
function furniva_store_admin_hpos_order_customer_column( $column, $order ) {
	if ( 'furniva_customer' === $column ) {
		furniva_store_admin_render_order_customer( $order );
	}
}
add_action( 'woocommerce_shop_order_list_table_custom_column', 'furniva_store_admin_hpos_order_customer_column', 10, 2 );

/**
 * Render the customer column in the legacy order list.
 *
 * @param string $column  Column name.
 * @param int    $post_id Order post ID.
 * @return void
 */
function furniva_store_admin_legacy_order_customer_column( $column, $post_id ) {
	if ( 'furniva_customer' === $column && function_exists( 'wc_get_order' ) ) {
		furniva_store_admin_render_order_customer( wc_get_order( $post_id ) );
	}
}
add_action( 'manage_shop_order_posts_custom_column', 'furniva_store_admin_legacy_order_customer_column', 20, 2 );

function furniva_store_admin_browser_title( $admin_title, $title ) {
	if ( furniva_store_admin_can_manage() && 'index.php' === ( $GLOBALS['pagenow'] ?? '' ) ) {
		return 'Tổng quan cửa hàng ‹ ' . get_bloginfo( 'name' ) . ' — WordPress';
	}

	return $admin_title;
}
add_filter( 'admin_title', 'furniva_store_admin_browser_title', 20, 2 );

/**
 * Replace the crowded default dashboard with the store overview below.
 */
function furniva_store_admin_clean_dashboard() {
	if ( ! furniva_store_admin_can_manage() ) {
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
add_action( 'wp_dashboard_setup', 'furniva_store_admin_clean_dashboard', 100 );

/**
 * Load dashboard-specific styles only on the dashboard screen.
 */
function furniva_store_admin_assets( $hook_suffix ) {
	if ( ! furniva_store_admin_can_manage() ) {
		return;
	}

	wp_enqueue_style(
		'furniva-store-admin',
		plugin_dir_url( __FILE__ ) . 'furniva-store-admin.css',
		array(),
		(string) filemtime( __DIR__ . '/furniva-store-admin.css' )
	);
}
add_action( 'admin_enqueue_scripts', 'furniva_store_admin_assets' );

/**
 * Return the order-management URL for either WooCommerce order storage mode.
 */
function furniva_store_admin_orders_url() {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
		return admin_url( 'admin.php?page=wc-orders' );
	}

	return admin_url( 'edit.php?post_type=shop_order' );
}

/**
 * Return a filtered order list URL for a standard WooCommerce status.
 */
function furniva_store_admin_orders_status_url( $status ) {
	$status = sanitize_key( $status );

	if ( ! in_array( $status, array( 'pending', 'processing', 'on-hold' ), true ) ) {
		return furniva_store_admin_orders_url();
	}

	if ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
		return admin_url( 'admin.php?page=wc-orders&status=wc-' . rawurlencode( $status ) );
	}

	return admin_url( 'edit.php?post_type=shop_order&post_status=wc-' . rawurlencode( $status ) );
}

/**
 * Return published products and variations below their configured low-stock threshold.
 */
function furniva_store_admin_low_stock_products( $limit = 0 ) {
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
function furniva_store_admin_low_stock_count() {
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
 * Render the Furniva store overview on the WordPress dashboard.
 */
function furniva_store_admin_render_dashboard() {
	global $pagenow;

	if ( 'index.php' !== $pagenow || ! furniva_store_admin_can_manage() ) {
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
	$low_stock     = function_exists( 'wc_get_products' ) ? furniva_store_admin_low_stock_count() : 0;
	$low_stock_products = function_exists( 'wc_get_product' ) ? furniva_store_admin_low_stock_products( 5 ) : array();
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
	<div class="furniva-admin-dashboard">
		<header class="furniva-admin-hero">
			<div class="furniva-admin-hero__copy">
				<p class="furniva-admin-eyebrow">FURNIVA <span>·</span> QUẢN TRỊ CỬA HÀNG</p>
				<h1><?php echo esc_html( sprintf( 'Chào %s,', $current_user->display_name ) ); ?> <em>hôm nay cửa hàng thế nào?</em></h1>
				<p class="furniva-admin-hero__description">Theo dõi đơn hàng, sản phẩm và nội dung website tại một nơi.</p>
				<p class="furniva-admin-date"><?php echo esc_html( date_i18n( 'l, j F Y' ) ); ?></p>
			</div>
			<nav class="furniva-admin-hero__actions" aria-label="Lối tắt quản trị">
				<a class="furniva-admin-button furniva-admin-button--light" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=product' ) ); ?>">Thêm sản phẩm</a>
				<a class="furniva-admin-button furniva-admin-button--outline" href="<?php echo esc_url( furniva_store_admin_orders_url() ); ?>">Mở đơn hàng</a>
			</nav>
		</header>

		<section class="furniva-admin-stats" aria-label="Tình hình cửa hàng">
			<a class="furniva-admin-stat furniva-admin-stat--priority" href="<?php echo esc_url( furniva_store_admin_orders_status_url( 'processing' ) ); ?>">
				<span class="furniva-admin-stat__label">Đơn đang xử lý</span>
				<strong class="furniva-admin-stat__value"><?php echo esc_html( number_format_i18n( $processing ) ); ?></strong>
				<span class="furniva-admin-stat__note">Đơn đã xác nhận, cần giao hàng</span>
			</a>
			<a class="furniva-admin-stat furniva-admin-stat--pending" href="<?php echo esc_url( furniva_store_admin_orders_status_url( 'pending' ) ); ?>">
				<span class="furniva-admin-stat__label">Chờ thanh toán</span>
				<strong class="furniva-admin-stat__value"><?php echo esc_html( number_format_i18n( $pending ) ); ?></strong>
				<span class="furniva-admin-stat__note">Kiểm tra thanh toán của khách</span>
			</a>
			<a class="furniva-admin-stat furniva-admin-stat--hold" href="<?php echo esc_url( furniva_store_admin_orders_status_url( 'on-hold' ) ); ?>">
				<span class="furniva-admin-stat__label">Chờ xác nhận</span>
				<strong class="furniva-admin-stat__value"><?php echo esc_html( number_format_i18n( $on_hold ) ); ?></strong>
				<span class="furniva-admin-stat__note">Đơn đang tạm giữ để xử lý</span>
			</a>
			<a class="furniva-admin-stat" href="<?php echo esc_url( admin_url( 'edit.php?post_type=product' ) ); ?>">
				<span class="furniva-admin-stat__label">Sản phẩm đã đăng</span>
				<strong class="furniva-admin-stat__value"><?php echo esc_html( number_format_i18n( $product_count ) ); ?></strong>
				<span class="furniva-admin-stat__note">Đang hiển thị trên cửa hàng</span>
			</a>
			<a class="furniva-admin-stat furniva-admin-stat--stock" href="<?php echo esc_url( admin_url( 'admin.php?page=wc-admin&type=lowstock&path=%2Fanalytics%2Fstock' ) ); ?>">
				<span class="furniva-admin-stat__label">Sắp hết hàng</span>
				<strong class="furniva-admin-stat__value"><?php echo esc_html( number_format_i18n( $low_stock ) ); ?></strong>
				<span class="furniva-admin-stat__note">Theo ngưỡng tồn kho WooCommerce</span>
			</a>
		</section>

		<div class="furniva-admin-main-grid">
			<section class="furniva-admin-panel furniva-admin-orders" aria-labelledby="furniva-admin-orders-title">
				<div class="furniva-admin-panel__heading">
					<div>
					<p class="furniva-admin-eyebrow furniva-admin-eyebrow--muted">VẬN HÀNH</p>
					<h3 id="furniva-admin-orders-title">Đơn hàng cần chú ý</h3>
				</div>
					<a class="furniva-admin-text-link" href="<?php echo esc_url( furniva_store_admin_orders_url() ); ?>">Tất cả đơn hàng <span aria-hidden="true">→</span></a>
				</div>
				<?php if ( ! empty( $orders ) ) : ?>
					<div class="furniva-admin-table-wrap">
						<table class="furniva-admin-table">
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
										<td><a class="furniva-admin-order-number" href="<?php echo esc_url( $order->get_edit_order_url() ); ?>">#<?php echo esc_html( $order->get_order_number() ); ?></a><span class="furniva-admin-table-subtext"><?php echo esc_html( sprintf( '%s sản phẩm', number_format_i18n( $order->get_item_count() ) ) ); ?></span></td>
										<td><?php echo esc_html( $customer ? $customer : 'Khách mua hàng' ); ?></td>
										<td><?php echo $date_created ? esc_html( date_i18n( 'j M, H:i', $date_created->getTimestamp() ) ) : '—'; ?></td>
										<td><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></td>
										<td><span class="furniva-admin-status furniva-admin-status--<?php echo esc_attr( sanitize_html_class( $status ) ); ?>"><?php echo esc_html( $status_names[ $status ] ?? wc_get_order_status_name( $status ) ); ?></span></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php else : ?>
					<div class="furniva-admin-empty-state">
						<span class="furniva-admin-empty-state__mark" aria-hidden="true">✓</span>
						<div><strong>Không có đơn cần xử lý</strong><p>Khi có đơn mới, đơn sẽ xuất hiện ở đây.</p></div>
					</div>
				<?php endif; ?>
			</section>

			<aside class="furniva-admin-panel furniva-admin-shortcuts" aria-labelledby="furniva-admin-shortcuts-title">
				<div class="furniva-admin-panel__heading">
					<div>
					<p class="furniva-admin-eyebrow furniva-admin-eyebrow--muted">THAO TÁC NHANH</p>
					<h3 id="furniva-admin-shortcuts-title">Bạn muốn làm gì?</h3>
				</div>
				</div>
				<nav class="furniva-admin-link-list" aria-label="Thao tác cửa hàng">
					<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=product' ) ); ?>"><span class="furniva-admin-link-icon" aria-hidden="true">＋</span><span><strong>Thêm sản phẩm</strong><small>Tạo sản phẩm mới cho cửa hàng</small></span><span class="furniva-admin-link-arrow" aria-hidden="true">→</span></a>
					<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=product' ) ); ?>"><span class="furniva-admin-link-icon" aria-hidden="true">▤</span><span><strong>Quản lý sản phẩm</strong><small>Giá bán, hình ảnh và tồn kho</small></span><span class="furniva-admin-link-arrow" aria-hidden="true">→</span></a>
					<a href="<?php echo esc_url( furniva_store_admin_orders_url() ); ?>"><span class="furniva-admin-link-icon" aria-hidden="true">□</span><span><strong>Quản lý đơn hàng</strong><small>Xem đơn, thanh toán và giao hàng</small></span><span class="furniva-admin-link-arrow" aria-hidden="true">→</span></a>
					<a href="<?php echo esc_url( admin_url( 'post-new.php' ) ); ?>"><span class="furniva-admin-link-icon" aria-hidden="true">✎</span><span><strong>Viết bài blog</strong><small>Chia sẻ câu chuyện và cảm hứng</small></span><span class="furniva-admin-link-arrow" aria-hidden="true">→</span></a>
					<a href="<?php echo esc_url( $contact_url ); ?>"><span class="furniva-admin-link-icon" aria-hidden="true">⌂</span><span><strong>Sửa trang liên hệ</strong><small>Địa chỉ, giờ làm và thông tin hỗ trợ</small></span><span class="furniva-admin-link-arrow" aria-hidden="true">→</span></a>
				</nav>
			</aside>
		</div>

		<section class="furniva-admin-panel furniva-admin-stock-panel" aria-labelledby="furniva-admin-stock-title">
			<div class="furniva-admin-panel__heading">
				<div>
					<p class="furniva-admin-eyebrow furniva-admin-eyebrow--muted">TỒN KHO</p>
					<h3 id="furniva-admin-stock-title">Sản phẩm cần nhập thêm</h3>
				</div>
				<a class="furniva-admin-text-link" href="<?php echo esc_url( admin_url( 'admin.php?page=wc-admin&type=lowstock&path=%2Fanalytics%2Fstock' ) ); ?>">Xem báo cáo tồn kho <span aria-hidden="true">→</span></a>
			</div>
			<?php if ( ! empty( $low_stock_products ) ) : ?>
				<div class="furniva-admin-table-wrap">
					<table class="furniva-admin-table furniva-admin-stock-table">
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
									<td><?php if ( $edit_url ) : ?><a class="furniva-admin-order-number" href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( $name ); ?></a><?php else : ?><?php echo esc_html( $name ); ?><?php endif; ?></td>
									<td><?php echo esc_html( $product->get_sku() ? $product->get_sku() : '—' ); ?></td>
									<td><span class="furniva-admin-stock-quantity"><?php echo esc_html( wc_format_stock_quantity_for_display( $product->get_stock_quantity(), $product ) ); ?></span></td>
									<td><?php echo esc_html( wc_format_stock_quantity_for_display( $threshold, $product ) ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php else : ?>
				<div class="furniva-admin-empty-state furniva-admin-stock-empty">
					<span class="furniva-admin-empty-state__mark" aria-hidden="true">✓</span>
					<div><strong>Tồn kho đang ổn</strong><p>Hiện chưa có sản phẩm nào dưới ngưỡng cảnh báo.</p></div>
				</div>
			<?php endif; ?>
		</section>

		<div class="furniva-admin-lower-grid">
			<section class="furniva-admin-panel furniva-admin-content-panel" aria-labelledby="furniva-admin-content-title">
				<div class="furniva-admin-panel__heading">
					<div>
					<p class="furniva-admin-eyebrow furniva-admin-eyebrow--muted">NỘI DUNG WEBSITE</p>
					<h3 id="furniva-admin-content-title">Cập nhật câu chuyện AXGNUD</h3>
				</div>
				</div>
				<div class="furniva-admin-content-summary">
					<p>Website hiện có <strong><?php echo esc_html( number_format_i18n( $post_count ) ); ?> bài viết</strong> đã đăng. Hãy dùng ảnh đại diện rõ nét và chọn chuyên mục trước khi xuất bản.</p>
					<a class="furniva-admin-button furniva-admin-button--dark" href="<?php echo esc_url( admin_url( 'edit.php' ) ); ?>">Mở thư viện bài viết</a>
				</div>
			</section>

			<section class="furniva-admin-panel furniva-admin-guide" aria-labelledby="furniva-admin-guide-title">
				<div class="furniva-admin-panel__heading">
					<div>
					<p class="furniva-admin-eyebrow furniva-admin-eyebrow--muted">HƯỚNG DẪN NHANH</p>
					<h3 id="furniva-admin-guide-title">Quy trình một đơn hàng</h3>
				</div>
				</div>
				<ol class="furniva-admin-steps">
					<li><span>01</span><p><strong>Mở đơn hàng</strong> và kiểm tra địa chỉ, sản phẩm, thanh toán.</p></li>
					<li><span>02</span><p><strong>Cập nhật trạng thái</strong> sau khi xác nhận hoặc gửi hàng.</p></li>
					<li><span>03</span><p><strong>Kiểm tra tồn kho</strong> nếu sản phẩm sắp hết hàng.</p></li>
				</ol>
				<a class="furniva-admin-text-link furniva-admin-guide__link" href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener noreferrer">Mở website để xem như khách hàng <span aria-hidden="true">↗</span></a>
			</section>
		</div>
		<footer class="furniva-admin-footer"><span>FURNIVA STORE DESK</span><span><?php echo esc_html( number_format_i18n( $product_count ) ); ?> sản phẩm <i>·</i> <?php echo esc_html( number_format_i18n( $post_count ) ); ?> bài viết</span><a href="<?php echo esc_url( admin_url( 'site-health.php' ) ); ?>">Tình trạng website <span aria-hidden="true">→</span></a></footer>
	</div>
	<?php
}
add_action( 'admin_notices', 'furniva_store_admin_render_dashboard', 20 );
