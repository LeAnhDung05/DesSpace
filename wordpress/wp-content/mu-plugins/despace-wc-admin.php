<?php
/**
 * DeSpace — Modern Architectural WordPress Admin UI for WooCommerce & Products.
 *
 * Scoped exclusively to WooCommerce & Products WP-Admin screens:
 *  - Products list, Add/Edit Product, Categories, Tags, Attributes, Reviews
 *  - WooCommerce Orders (HPOS & legacy), Coupons, Settings, Status, Reports, React Admin
 *
 * Preserves 100% of native WordPress/WooCommerce JS, HPOS, metaboxes, Screen Options & RBAC.
 *
 * @package DeSpace
 */

defined( 'ABSPATH' ) || exit;

/**
 * Determine if the current WP-Admin screen belongs to WooCommerce or Products.
 */
function despace_is_wc_or_product_admin_screen(): bool {
	if ( ! is_admin() || wp_doing_ajax() ) {
		return false;
	}

	if ( function_exists( 'despace_is_cockpit_admin_page' ) && despace_is_cockpit_admin_page() ) {
		return false;
	}

	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( $screen instanceof WP_Screen ) {
		$id        = (string) $screen->id;
		$post_type = (string) $screen->post_type;
		$taxonomy  = (string) $screen->taxonomy;

		if ( in_array( $post_type, array( 'product', 'shop_order', 'shop_coupon' ), true ) ) {
			return true;
		}
		if ( in_array( $taxonomy, array( 'product_cat', 'product_tag', 'product_brand' ), true ) || 0 === strpos( $taxonomy, 'pa_' ) ) {
			return true;
		}
		if ( 0 === strpos( $id, 'woocommerce_page_' ) || 0 === strpos( $id, 'product_page_' ) || 'toplevel_page_woocommerce' === $id || 'toplevel_page_wc-admin-path--analytics-overview' === $id || 'toplevel_page_woocommerce-marketing' === $id ) {
			return true;
		}
	}

	$page      = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
	$post_type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : '';
	$taxonomy  = isset( $_GET['taxonomy'] ) ? sanitize_key( wp_unslash( $_GET['taxonomy'] ) ) : '';

	if ( in_array( $post_type, array( 'product', 'shop_order', 'shop_coupon' ), true ) ) {
		return true;
	}
	if ( in_array( $taxonomy, array( 'product_cat', 'product_tag', 'product_brand' ), true ) || 0 === strpos( $taxonomy, 'pa_' ) ) {
		return true;
	}
	if ( in_array( $page, array( 'wc-orders', 'wc-settings', 'wc-status', 'wc-reports', 'wc-admin', 'wc-addons', 'product_attributes', 'product-reviews' ), true ) ) {
		return true;
	}

	return false;
}

/**
 * Check if current screen is the React-driven WooCommerce Admin shell (`wc-admin`),
 * which renders its own fixed top bar so we should NOT inject a duplicate PHP top bar above it.
 */
function despace_is_wc_react_admin_screen(): bool {
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
	return 'wc-admin' === $page;
}

/**
 * Add scoped body class `ds-wc-admin-modern` on WooCommerce & Products admin screens.
 */
add_filter( 'admin_body_class', function ( string $classes ): string {
	if ( despace_is_wc_or_product_admin_screen() ) {
		$classes .= ' ds-wc-admin-modern';
	}
	return $classes;
} );

/**
 * Enqueue modern architectural CSS strictly on WooCommerce & Products screens.
 */
add_action( 'admin_enqueue_scripts', function (): void {
	if ( ! despace_is_wc_or_product_admin_screen() ) {
		return;
	}

	$css_path = __DIR__ . '/despace-wc-admin.css';
	$css_url  = content_url( 'mu-plugins/despace-wc-admin.css' );
	if ( file_exists( $css_path ) ) {
		wp_enqueue_style(
			'despace-wc-admin-modern',
			$css_url,
			array(),
			(string) filemtime( $css_path ) . '-' . (string) filesize( $css_path )
		);
	}
}, 30 );

/**
 * Render DeSpace Commerce Top Workspace Bar on classic WooCommerce & Products screens.
 */
add_action( 'in_admin_header', function (): void {
	if ( ! despace_is_wc_or_product_admin_screen() || despace_is_wc_react_admin_screen() ) {
		return;
	}

	$screen    = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	$page      = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
	$post_type = $screen instanceof WP_Screen ? (string) $screen->post_type : ( isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : '' );
	$taxonomy  = $screen instanceof WP_Screen ? (string) $screen->taxonomy : ( isset( $_GET['taxonomy'] ) ? sanitize_key( wp_unslash( $_GET['taxonomy'] ) ) : '' );
	$base      = $screen instanceof WP_Screen ? (string) $screen->base : '';

	// Context title, kicker & primary CTA
	$active_tab  = 'products';
	$kicker      = 'Sản phẩm & Danh mục';
	$title       = 'Quản lý Sản phẩm DeSpace';
	$cta_label   = '+ Thêm sản phẩm mới';
	$cta_url     = admin_url( 'post-new.php?post_type=product' );

	if ( 'wc-orders' === $page || 'shop_order' === $post_type ) {
		$active_tab = 'orders';
		$kicker     = 'WooCommerce HPOS';
		$title      = isset( $_GET['id'] ) ? sprintf( 'Chi tiết Đơn hàng #%d', absint( $_GET['id'] ) ) : 'Quản lý Đơn hàng (HPOS)';
		$cta_label  = '+ Tạo đơn hàng';
		$cta_url    = admin_url( 'admin.php?page=wc-orders&action=new' );
	} elseif ( 'shop_coupon' === $post_type ) {
		$active_tab = 'coupons';
		$kicker     = 'Ưu đãi WooCommerce';
		$title      = 'Mã giảm giá & Khuyến mãi';
		$cta_label  = '+ Tạo mã ưu đãi';
		$cta_url    = admin_url( 'post-new.php?post_type=shop_coupon' );
	} elseif ( 'wc-settings' === $page ) {
		$active_tab = 'settings';
		$kicker     = 'Cấu hình Cửa hàng';
		$title      = 'Cài đặt WooCommerce';
		$cta_label  = 'Trạng thái hệ thống';
		$cta_url    = admin_url( 'admin.php?page=wc-status' );
	} elseif ( 'wc-status' === $page || 'wc-reports' === $page ) {
		$active_tab = 'settings';
		$kicker     = 'Vận hành WooCommerce';
		$title      = 'wc-status' === $page ? 'Trạng thái Hệ thống WooCommerce' : 'Báo cáo WooCommerce';
	} elseif ( 'product_cat' === $taxonomy || 'product_tag' === $taxonomy || 'product_brand' === $taxonomy || 0 === strpos( $taxonomy, 'pa_' ) || 'product_attributes' === $page ) {
		$active_tab = 'categories';
		$kicker     = 'Phân loại Nội thất';
		$title      = 'product_cat' === $taxonomy ? 'Danh mục Sản phẩm' : ( 'product_attributes' === $page ? 'Thuộc tính Sản phẩm' : 'Phân loại & Thẻ Sản phẩm' );
	} elseif ( 'product' === $post_type && 'post' === $base ) {
		$active_tab = 'products';
		$kicker     = 'Biên tập Sản phẩm';
		$title      = isset( $_GET['post'] ) ? 'Chỉnh sửa Sản phẩm #' . absint( $_GET['post'] ) : 'Thêm Sản phẩm mới';
	}

	// Real verified database counts (zero fabrication)
	$prod_counts  = wp_count_posts( 'product' );
	$prod_total   = isset( $prod_counts->publish ) ? (int) $prod_counts->publish : 0;
	$coupon_cnt   = wp_count_posts( 'shop_coupon' );
	$coupon_total = isset( $coupon_cnt->publish ) ? (int) $coupon_cnt->publish : 0;
	$cat_total    = (int) wp_count_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => true ) );
	$orders_total = 0;
	if ( function_exists( 'wc_orders_count' ) ) {
		$orders_total = (int) wc_orders_count( 'processing' ) + (int) wc_orders_count( 'on-hold' ) + (int) wc_orders_count( 'pending' ) + (int) wc_orders_count( 'completed' );
	}
	$count_3d = function_exists( 'despace_get_3d_product_ids' ) ? count( despace_get_3d_product_ids() ) : 0;
	$orders_url = function_exists( 'despace_store_admin_orders_url' ) ? despace_store_admin_orders_url() : admin_url( 'admin.php?page=wc-orders' );
	$logo_url   = content_url( '/themes/despace-child/assets/images/despace-logo-header.png' );

	$tabs = array(
		array(
			'key'   => 'products',
			'label' => 'Sản phẩm',
			'url'   => admin_url( 'edit.php?post_type=product' ),
			'count' => $prod_total,
		),
		array(
			'key'   => 'categories',
			'label' => 'Danh mục',
			'url'   => admin_url( 'edit-tags.php?taxonomy=product_cat&post_type=product' ),
			'count' => $cat_total,
		),
		array(
			'key'   => 'orders',
			'label' => 'Đơn hàng (HPOS)',
			'url'   => $orders_url,
			'count' => $orders_total,
		),
		array(
			'key'   => 'coupons',
			'label' => 'Mã ưu đãi',
			'url'   => admin_url( 'edit.php?post_type=shop_coupon' ),
			'count' => $coupon_total,
		),
		array(
			'key'   => 'settings',
			'label' => 'Cài đặt WC',
			'url'   => admin_url( 'admin.php?page=wc-settings' ),
			'count' => null,
		),
	);
	?>
	<div class="ds-wc-topbar" role="region" aria-label="Thanh điều hướng nhanh DeSpace Commerce">
		<div class="ds-wc-topbar__hero">
			<div class="ds-wc-topbar__left">
				<div class="ds-wc-topbar__brand">
					<img src="<?php echo esc_url( $logo_url ); ?>" alt="DeSpace" class="ds-wc-topbar__logo" />
					<span class="ds-wc-topbar__sep" aria-hidden="true"></span>
					<span class="ds-wc-topbar__kicker"><?php echo esc_html( $kicker ); ?></span>
				</div>
				<h2 class="ds-wc-topbar__title"><?php echo esc_html( $title ); ?></h2>
				<div class="ds-wc-topbar__stats">
					<span><strong><?php echo esc_html( (string) $prod_total ); ?></strong> sản phẩm xuất bản</span>
					<?php if ( $count_3d > 0 ) : ?>
						<span>· <strong><?php echo esc_html( (string) $count_3d ); ?></strong> hỗ trợ 3D/AR</span>
					<?php endif; ?>
					<span>· <strong><?php echo esc_html( (string) $orders_total ); ?></strong> đơn hàng HPOS</span>
					<span>· <strong><?php echo esc_html( (string) $coupon_total ); ?></strong> mã giảm giá hoạt động</span>
				</div>
			</div>
			<div class="ds-wc-topbar__actions">
				<a href="<?php echo esc_url( $cta_url ); ?>" class="ds-wc-btn ds-wc-btn--primary"<?php echo home_url( '/' ) === $cta_url ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>>
					<?php echo esc_html( $cta_label ); ?>
				</a>
				<?php if ( function_exists( 'despace_store_admin_can_manage_3d' ) && despace_store_admin_can_manage_3d() ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=despace-3d-manager' ) ); ?>" class="ds-wc-btn ds-wc-btn--ghost">
						Xưởng 3D &amp; AR
					</a>
				<?php endif; ?>
				<a href="<?php echo esc_url( home_url( '/cua-hang/' ) ); ?>" target="_blank" rel="noopener noreferrer" class="ds-wc-btn ds-wc-btn--ghost">
					Xem Cửa hàng ↗
				</a>
			</div>
		</div>
		<nav class="ds-wc-topbar__nav" aria-label="Chuyển nhanh màn hình WooCommerce & Sản phẩm">
			<?php foreach ( $tabs as $tab ) : ?>
				<a href="<?php echo esc_url( $tab['url'] ); ?>" class="ds-wc-topbar__tab <?php echo $active_tab === $tab['key'] ? 'is-active' : ''; ?>">
					<span><?php echo esc_html( $tab['label'] ); ?></span>
					<?php if ( null !== $tab['count'] ) : ?>
						<span class="ds-wc-topbar__tab-count"><?php echo esc_html( (string) $tab['count'] ); ?></span>
					<?php endif; ?>
				</a>
			<?php endforeach; ?>
			<?php if ( function_exists( 'despace_store_admin_can_access_hub' ) && despace_store_admin_can_access_hub() ) : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=despace-hub' ) ); ?>" class="ds-wc-topbar__tab ds-wc-topbar__tab--cockpit">
					← DeSpace Cockpit
				</a>
			<?php endif; ?>
		</nav>
	</div>
	<?php
}, 20 );

/**
 * Add verified "3D / AR" readiness column to the WP-Admin Products list table.
 */
add_filter( 'manage_edit-product_columns', function ( array $columns ): array {
	$new_cols = array();
	foreach ( $columns as $key => $label ) {
		$new_cols[ $key ] = $label;
		if ( 'price' === $key ) {
			$new_cols['ds_3d_status'] = '3D / AR';
		}
	}
	if ( ! isset( $new_cols['ds_3d_status'] ) ) {
		$new_cols['ds_3d_status'] = '3D / AR';
	}
	return $new_cols;
}, 25 );

add_action( 'manage_product_posts_custom_column', function ( string $column, int $post_id ): void {
	if ( 'ds_3d_status' !== $column ) {
		return;
	}
	$info = function_exists( 'ax_get_product_3d_info' ) ? ax_get_product_3d_info( $post_id ) : array();
	$has_glb = ! empty( $info['glb_url'] );
	$has_ar  = $has_glb && ! empty( $info['ar_enabled'] );
	if ( $has_ar ) {
		echo '<span class="ds-admin-3d-badge ds-admin-3d-badge--ready">✓ 3D · AR</span>';
	} elseif ( $has_glb ) {
		echo '<span class="ds-admin-3d-badge ds-admin-3d-badge--3d">3D 360°</span>';
	} else {
		echo '<span class="ds-admin-3d-badge ds-admin-3d-badge--none">Chưa có</span>';
	}
}, 10, 2 );

/**
 * Resilient thumbnail fallback: if an attachment subsize (e.g. -150x150.jpg) is missing on disk
 * while the full original image exists, automatically fall back to the original file URL so
 * WP-Admin Category & Product tables never render broken image icons.
 */
add_filter( 'wp_get_attachment_image_src', function ( $image, $attachment_id, $size ) {
	if ( ! is_array( $image ) || empty( $image[0] ) || 'full' === $size ) {
		return $image;
	}
	$uploads = wp_get_upload_dir();
	if ( empty( $uploads['baseurl'] ) || empty( $uploads['basedir'] ) ) {
		return $image;
	}
	if ( 0 !== strpos( (string) $image[0], $uploads['baseurl'] ) ) {
		return $image;
	}
	$rel  = substr( (string) $image[0], strlen( $uploads['baseurl'] ) );
	$path = $uploads['basedir'] . $rel;
	if ( ! file_exists( $path ) ) {
		$orig_file = get_attached_file( (int) $attachment_id );
		if ( $orig_file && file_exists( $orig_file ) ) {
			$orig_url = wp_get_attachment_url( (int) $attachment_id );
			if ( $orig_url ) {
				$image[0] = $orig_url;
			}
		}
	}
	return $image;
}, 20, 3 );
