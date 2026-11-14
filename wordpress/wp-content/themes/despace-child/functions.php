<?php
/**
 * DeSpace Child Theme Functions.
 * Strictly adheres to DeSpace SADD v1.2 - Presentation layer only.
 *
 * @package DeSpace_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DESPACE_THEME_VERSION', '1.2.0' );

/**
 * Setup Theme Supports.
 */
function despace_theme_setup() {
	// WooCommerce supports
	add_theme_support( 'woocommerce', array(
		'product_grid' => array(
			'default_columns' => 4,
			'min_columns'     => 1,
			'max_columns'     => 4,
		),
	) );
	add_theme_support( 'wc-product-gallery-zoom' );
	add_theme_support( 'wc-product-gallery-lightbox' );
	add_theme_support( 'wc-product-gallery-slider' );
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );

	// Navigation Menus
	register_nav_menus( array(
		'primary'     => __( 'Menu Chính (DeSpace Primary)', 'despace-child' ),
		'footer_shop' => __( 'Menu Chân trang - Danh mục', 'despace-child' ),
		'footer_help' => __( 'Menu Chân trang - Trợ giúp', 'despace-child' ),
	) );
}
add_action( 'after_setup_theme', 'despace_theme_setup' );

/**
 * Enqueue Styles and ES6+ JavaScript.
 */
function despace_enqueue_assets() {
	// Parent theme style
	wp_enqueue_style( 'astra-parent-theme', get_template_directory_uri() . '/style.css', array(), '4.14.0' );

	// Google Fonts (Inter + Cormorant Garamond)
	wp_enqueue_style(
		'despace-fonts',
		'https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;0,700;1,400&family=Inter:wght@300;400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap',
		array(),
		null
	);

	// DeSpace Child Theme Stylesheet
	wp_enqueue_style(
		'despace-theme-style',
		get_stylesheet_uri(),
		array( 'astra-parent-theme', 'despace-fonts' ),
		DESPACE_THEME_VERSION
	);

	// DeSpace Core Theme UI Script (ES6+)
	wp_enqueue_script(
		'despace-theme-ui',
		get_stylesheet_directory_uri() . '/assets/js/despace-theme.js',
		array(),
		DESPACE_THEME_VERSION,
		true
	);

	// DeSpace Catalog & REST API Fetch Script (ES6+, No jQuery dependency)
	if ( is_shop() || is_product_category() || is_front_page() || is_search() ) {
		wp_enqueue_script(
			'despace-catalog-fetch',
			get_stylesheet_directory_uri() . '/assets/js/despace-catalog.js',
			array(),
			DESPACE_THEME_VERSION,
			true
		);

		wp_localize_script( 'despace-catalog-fetch', 'despaceData', array(
			'restUrl'     => esc_url_raw( rest_url( 'despace/v1/' ) ),
			'nonce'       => wp_create_nonce( 'wp_rest' ),
			'shopUrl'     => function_exists( 'wc_get_page_id' ) ? get_permalink( wc_get_page_id( 'shop' ) ) : home_url( '/shop/' ),
			'currency'    => function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '$',
		) );
	}
}
add_action( 'wp_enqueue_scripts', 'despace_enqueue_assets', 15 );

/**
 * Clean Translation helper.
 */
function despace_t( $en, $vi ) {
	$lang = get_locale();
	if ( 'vi' === $lang || 'vi_VN' === $lang ) {
		return $vi;
	}
	return $en;
}
