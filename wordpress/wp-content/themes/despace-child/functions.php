<?php
/**
 * DeSpace temporary standalone storefront for integration staging.
 *
 * @package DeSpaceStaging
 */

defined( 'ABSPATH' ) || exit;

function despace_staging_theme_setup(): void {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'custom-logo', array( 'height' => 80, 'width' => 240, 'flex-height' => true, 'flex-width' => true ) );
	add_theme_support( 'woocommerce' );
	add_theme_support( 'wc-product-gallery-zoom' );
	add_theme_support( 'wc-product-gallery-lightbox' );
	add_theme_support( 'wc-product-gallery-slider' );
	register_nav_menus( array( 'primary' => __( 'Primary Navigation', 'despace-child' ) ) );
}
add_action( 'after_setup_theme', 'despace_staging_theme_setup' );

function despace_staging_assets(): void {
	wp_enqueue_style( 'despace-staging', get_stylesheet_uri(), array(), '0.1.0' );
	wp_enqueue_script( 'despace-staging', get_stylesheet_directory_uri() . '/assets/js/store.js', array(), '0.1.0', true );
}
add_action( 'wp_enqueue_scripts', 'despace_staging_assets' );

function despace_staging_cart_count(): int {
	if ( function_exists( 'WC' ) && WC()->cart ) {
		return (int) WC()->cart->get_cart_contents_count();
	}
	return 0;
}

function despace_staging_shop_url(): string {
	return function_exists( 'wc_get_page_permalink' ) ? (string) wc_get_page_permalink( 'shop' ) : home_url( '/shop/' );
}
