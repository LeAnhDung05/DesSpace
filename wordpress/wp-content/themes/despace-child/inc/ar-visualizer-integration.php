<?php
/**
 * AR Visualizer Integration
 * - AR & Room Visualizer in-page modal integration
 * - Auto-provisioning & 404 safety fallback for standalone AR page
 * - Support for 3D model-viewer and Scandinavian room visualizer
 *
 * @package Furniva_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Helper: Detect 3D model files (.glb / .usdz) and AR configuration from custom table & meta
 */
function ax_get_product_3d_info( $product_id ) {
	global $wpdb;
	$table = $wpdb->prefix . 'axgnud_product_3d_models';

	$row = null;
	if ( $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) === $table ) {
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE product_id = %d LIMIT 1", $product_id ) );
	}

	$glb = $row ? $row->glb_url : get_post_meta( $product_id, '_axgnud_model_glb', true );
	$usdz = $row ? $row->usdz_url : get_post_meta( $product_id, '_axgnud_model_usdz', true );
	$ar_meta = get_post_meta( $product_id, '_ar_enabled', true );
	$ar_enabled_flag = $row ? (int) $row->ar_enabled : ( 'yes' === $ar_meta || '1' === $ar_meta );
	$model_status = $row ? $row->model_status : ( get_post_meta( $product_id, '_axgnud_3d_status', true ) ?: 'published' );

	if ( ! $glb ) {
		$glb = get_post_meta( $product_id, '_glb_file', true )
			?: get_post_meta( $product_id, '_ar_glb_url', true )
			?: get_post_meta( $product_id, '_model_3d', true )
			?: get_post_meta( $product_id, '_model_glb', true );
	}
	if ( ! $usdz ) {
		$usdz = get_post_meta( $product_id, '_usdz_file', true )
			?: get_post_meta( $product_id, '_ar_usdz_url', true )
			?: get_post_meta( $product_id, '_model_usdz', true );
	}

	$glb_url = '';
	if ( $glb ) {
		$glb_url = is_numeric( $glb ) ? wp_get_attachment_url( $glb ) : $glb;
		if ( $glb_url && strpos( $glb_url, 'http' ) !== 0 ) {
			$glb_url = home_url( '/' . ltrim( $glb_url, '/' ) );
		}
	}
	$usdz_url = '';
	if ( $usdz ) {
		$usdz_url = is_numeric( $usdz ) ? wp_get_attachment_url( $usdz ) : $usdz;
		if ( $usdz_url && strpos( $usdz_url, 'http' ) !== 0 ) {
			$usdz_url = home_url( '/' . ltrim( $usdz_url, '/' ) );
		}
	}

	// Safety: Hard discard any fake sample models (sofa/chair) to prevent wrong model display
	if ( strpos( $glb_url, 'GlamVelvetSofa' ) !== false || strpos( $glb_url, 'SheenChair' ) !== false ) {
		$glb_url = '';
	}
	if ( strpos( $usdz_url, 'GlamVelvetSofa' ) !== false || strpos( $usdz_url, 'SheenChair' ) !== false ) {
		$usdz_url = '';
	}

	$product = wc_get_product( $product_id );
	$dims = $product ? $product->get_dimensions( false ) : array();
	$width  = ( $row && $row->width > 0 ) ? floatval( $row->width ) : ( ! empty( $dims['length'] ) ? floatval( $dims['length'] ) : floatval( get_post_meta( $product_id, '_length', true ) ) );
	$depth  = ( $row && $row->depth > 0 ) ? floatval( $row->depth ) : ( ! empty( $dims['width'] ) ? floatval( $dims['width'] ) : floatval( get_post_meta( $product_id, '_width', true ) ) );
	$height = ( $row && $row->height > 0 ) ? floatval( $row->height ) : ( ! empty( $dims['height'] ) ? floatval( $dims['height'] ) : floatval( get_post_meta( $product_id, '_height', true ) ) );

	$dim_str = '';
	if ( $width > 0 && $depth > 0 && $height > 0 ) {
		$dim_str = "Rộng {$width} cm × Sâu {$depth} cm × Cao {$height} cm";
	}

	$has_3d = ! empty( $glb_url ) && ( 'published' === $model_status || is_admin() );
	$ar_enabled = $has_3d && ! empty( $usdz_url ) && ( 1 === $ar_enabled_flag || 'yes' === $ar_enabled_flag );

	return array(
		'has_3d'       => $has_3d,
		'glb_url'      => $has_3d ? $glb_url : '',
		'usdz_url'     => $has_3d ? $usdz_url : '',
		'ar_enabled'   => $ar_enabled,
		'model_status' => $model_status,
		'width_cm'     => $width,
		'depth_cm'     => $depth,
		'height_cm'    => $height,
		'dimensions'   => $dim_str,
	);
}

/**
 * Auto-ensure AR Visualizer page exists with correct template
 */
function ar_ensure_visualizer_page() {
	$slug = 'ar-room-visualizer';
	$page = get_page_by_path( $slug );
	if ( ! $page ) {
		$page_id = wp_insert_post( array(
			'post_title'     => 'AR Room Visualizer',
			'post_name'      => $slug,
			'post_status'    => 'publish',
			'post_type'      => 'page',
			'comment_status' => 'closed',
			'ping_status'    => 'closed',
		) );
		if ( $page_id && ! is_wp_error( $page_id ) ) {
			update_post_meta( $page_id, '_wp_page_template', 'page-ar-visualizer.php' );
		}
	} else {
		$tpl = get_post_meta( $page->ID, '_wp_page_template', true );
		if ( $tpl !== 'page-ar-visualizer.php' ) {
			update_post_meta( $page->ID, '_wp_page_template', 'page-ar-visualizer.php' );
		}
	}
}
add_action( 'init', 'ar_ensure_visualizer_page' );

/**
 * 404 Fallback: Guarantee AR URL never throws 404 in any language (VI / EN)
 */
function ar_visualizer_404_fallback( $template ) {
	if ( is_404() ) {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		if ( strpos( $uri, 'ar-room-visualizer' ) !== false || strpos( $uri, 'ar-visualizer' ) !== false || strpos( $uri, 'room-visualizer' ) !== false ) {
			status_header( 200 );
			global $wp_query;
			$wp_query->is_404 = false;
			$theme_tpl = get_stylesheet_directory() . '/page-ar-visualizer.php';
			if ( file_exists( $theme_tpl ) ) {
				return $theme_tpl;
			}
		}
	}
	return $template;
}
add_filter( 'template_include', 'ar_visualizer_404_fallback', 99 );

/**
 * Safe AR page URL helper
 */
function ax_get_ar_page_url() {
	$page = get_page_by_path( 'ar-room-visualizer' );
	if ( $page ) {
		return get_permalink( $page );
	}
	return home_url( '/ar-room-visualizer/' );
}

/**
 * Add AR Button to Product Page (Hooked for standard WooCommerce layouts)
 */
add_action( 'woocommerce_single_product_summary', 'ar_add_button_to_product', 30 );

function ar_add_button_to_product() {
	global $product;
	if ( ! $product || $product->is_virtual() ) return;

	$model_info = ax_get_product_3d_info( $product->get_id() );
	$image_url  = wp_get_attachment_image_url( $product->get_image_id(), 'large' );
	$dims       = $product->get_dimensions( false );
	$dim_str    = '';
	if ( ! empty( $dims['length'] ) && ! empty( $dims['width'] ) && ! empty( $dims['height'] ) ) {
		$dim_str = "{$dims['length']} × {$dims['width']} × {$dims['height']} cm";
	}

	echo '<button type="button" class="ar-product-btn ax-open-room-modal" id="arViewInRoomBtn"
		data-product-id="' . esc_attr( $product->get_id() ) . '"
		data-product-name="' . esc_attr( $product->get_name() ) . '"
		data-product-price="' . esc_attr( html_entity_decode( wp_strip_all_tags( $product->get_price_html() ), ENT_QUOTES, 'UTF-8' ) ) . '"
		data-product-raw-price="' . esc_attr( $product->get_price() ) . '"
		data-product-image="' . esc_url( $image_url ) . '"
		data-product-dimensions="' . esc_attr( $dim_str ) . '"
		data-has-3d="' . ( $model_info['has_3d'] ? 'yes' : 'no' ) . '"
		data-glb-url="' . esc_url( $model_info['glb_url'] ) . '"
		data-usdz-url="' . esc_url( $model_info['usdz_url'] ) . '"
		aria-haspopup="dialog"
		aria-expanded="false">';
	echo '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
	echo '<path d="M12 2L2 7l10 5 10-5-10-5z"/>';
	echo '<path d="M2 17l10 5 10-5"/>';
	echo '<path d="M2 12l10 5 10-5"/>';
	echo '</svg>';
	echo '<span>' . esc_html( furniva_t( 'View in Your Room', 'Xem Trong Phòng Bạn' ) ) . '</span>';
	echo '</button>';
}

/**
 * Add AR data attributes to product for JavaScript
 */
add_action( 'woocommerce_before_single_product', 'ar_add_product_data' );

function ar_add_product_data() {
	global $product;
	if ( ! $product ) return;

	$dimensions = $product->get_dimensions( false );
	$image_url  = wp_get_attachment_image_url( $product->get_image_id(), 'woocommerce_single' );
	$model_info = ax_get_product_3d_info( $product->get_id() );

	echo '<div id="ar-product-data"
		 data-product-id="' . esc_attr( $product->get_id() ) . '"
		 data-product-name="' . esc_attr( $product->get_name() ) . '"
		 data-product-price="' . esc_attr( html_entity_decode( wp_strip_all_tags( $product->get_price_html() ), ENT_QUOTES, 'UTF-8' ) ) . '"
		 data-product-image="' . esc_url( $image_url ) . '"
		 data-product-width="' . esc_attr( $dimensions['width'] ?? 100 ) . '"
		 data-product-height="' . esc_attr( $dimensions['height'] ?? 80 ) . '"
		 data-product-length="' . esc_attr( $dimensions['length'] ?? 80 ) . '"
		 data-has-3d="' . ( $model_info['has_3d'] ? 'yes' : 'no' ) . '"
		 data-glb-url="' . esc_url( $model_info['glb_url'] ) . '"
		 data-usdz-url="' . esc_url( $model_info['usdz_url'] ) . '"
		 data-ar-page-url="' . esc_url( ax_get_ar_page_url() ) . '"
		 style="display:none;"></div>';
}

/**
 * Add AR section to product tabs
 */
add_filter( 'woocommerce_product_tabs', 'ar_add_product_tab' );

function ar_add_product_tab( $tabs ) {
	$tabs['ar_visualizer'] = array(
		'title'    => furniva_t( 'View in Your Space', 'Xem Trong Không Gian' ),
		'priority' => 15,
		'callback' => 'ar_product_tab_content',
	);
	return $tabs;
}

function ar_product_tab_content() {
	global $product;
	$ar_page_url = ax_get_ar_page_url();

	echo '<div class="ar-product-tab-content">';
	echo '<div class="ar-tab-inner">';
	echo '<div class="ar-tab-info">';
	echo '<h3>' . esc_html( furniva_t( 'Visualize This Product', 'Hình Dung Sản Phẩm Này' ) ) . '</h3>';
	echo '<p>' . esc_html( furniva_t( 'See how this piece looks in real living spaces. Check size, styling harmony, and ambient lighting directly in your room.', 'Xem sản phẩm này trông như thế nào trong không gian thực tế. Kiểm tra kích thước, phong cách và tông màu trước khi mua.' ) ) . '</p>';
	echo '<button type="button" class="ar-btn ar-btn-primary ax-open-room-modal" data-product-id="' . ( $product ? esc_attr( $product->get_id() ) : '' ) . '">';
	echo '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
	echo '<rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>';
	echo '<circle cx="8.5" cy="8.5" r="1.5"/>';
	echo '<polyline points="21 15 16 10 5 21"/>';
	echo '</svg>';
	echo '<span>' . esc_html( furniva_t( 'Open Room Visualizer', 'Mở Phòng Mẫu Trực Quan' ) ) . '</span>';
	echo '</button>';
	echo '</div>';
	echo '<div class="ar-tab-image">';
	echo '<img src="' . esc_url( get_stylesheet_directory_uri() . '/assets/images/room-living.jpg' ) . '" alt="AR Preview" loading="lazy" />';
	echo '</div>';
	echo '</div>';
	echo '</div>';
}

/**
 * Add AR quick button to product card on shop page
 */
add_action( 'woocommerce_after_shop_loop_item', 'ar_add_quick_button_to_product_card', 20 );

function ar_add_quick_button_to_product_card() {
	global $product;
	if ( ! $product || $product->is_virtual() ) return;

	$url = add_query_arg( 'open_ar', '1', get_permalink( $product->get_id() ) ) . '#view-in-room';
	echo '<a href="' . esc_url( $url ) . '" class="ar-quick-btn ax-quick-ar-btn" title="' . esc_attr( furniva_t( 'View in Your Room', 'Xem Trong Phòng Bạn' ) ) . '" data-product-id="' . esc_attr( $product->get_id() ) . '">';
	echo '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
	echo '<path d="M12 2L2 7l10 5 10-5-10-5z"/>';
	echo '<path d="M2 17l10 5 10-5"/>';
	echo '<path d="M2 12l10 5 10-5"/>';
	echo '</svg>';
	echo '</a>';
}

/**
 * Register AR Visualizer menu item
 */
add_filter( 'wp_nav_menu_items', 'ar_add_menu_item', 10, 2 );

function ar_add_menu_item( $items, $args ) {
	if ( $args->theme_location === 'primary' || $args->theme_location === 'main-menu' ) {
		$ar_url = ax_get_ar_page_url();

		$ar_item  = '<li class="menu-item ar-menu-item">';
		$ar_item .= '<a href="' . esc_url( $ar_url ) . '" class="ar-nav-link">';
		$ar_item .= '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
		$ar_item .= '<path d="M12 2L2 7l10 5 10-5-10-5z"/>';
		$ar_item .= '<path d="M2 17l10 5 10-5"/>';
		$ar_item .= '<path d="M2 12l10 5 10-5"/>';
		$ar_item .= '</svg>';
		$ar_item .= '<span>' . esc_html( furniva_t( 'AR Visualizer', 'Xem Phòng' ) ) . '</span>';
		$ar_item .= '</a>';
		$ar_item .= '</li>';

		$items .= $ar_item;
	}
	return $items;
}

/**
 * Add AR link to footer
 */
add_action( 'wp_footer', 'ar_add_footer_link' );

function ar_add_footer_link() {
	echo '<script>';
	echo 'window.AR_VISUALIZER_URL = "' . esc_url( ax_get_ar_page_url() ) . '";';
	echo '</script>';
}
