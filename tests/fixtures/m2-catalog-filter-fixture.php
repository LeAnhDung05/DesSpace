<?php
/**
 * DeSpace M2 Catalog Filter Test Fixture Helper (Issue #13)
 * Creates and safely cleans up temporary pa_size and product_brand terms
 * plus a dedicated test product fixture.
 * Does NOT touch existing products, existing terms, or WooCommerce sessions.
 *
 * Usage:
 *   php m2-catalog-filter-fixture.php create
 *   php m2-catalog-filter-fixture.php delete
 *   php m2-catalog-filter-fixture.php status
 */

if ( php_sapi_name() !== 'cli' ) {
	exit( 1 );
}

$wp_load_candidates = array(
	'/var/www/html/wp-load.php',
	dirname( __DIR__, 2 ) . '/wordpress/wp-load.php',
);

$wp_loaded = false;
foreach ( $wp_load_candidates as $candidate ) {
	if ( file_exists( $candidate ) ) {
		require_once $candidate;
		$wp_loaded = true;
		break;
	}
}

if ( ! $wp_loaded || ! function_exists( 'wc_get_product' ) ) {
	echo wp_json_encode( array( 'error' => 'WordPress or WooCommerce environment not found' ) );
	exit( 1 );
}

$action = $argv[1] ?? 'status';

const M2_FILTER_FIXTURE_SLUG       = 'm2-test-fixture-size-product';
const M2_FILTER_FIXTURE_TITLE      = '[TEST-FIXTURE-M2-FILTER] DeSpace Size & Brand Filter Fixture';
const M2_FILTER_FIXTURE_SKU        = 'M2-TEST-SIZE-001';
const M2_FILTER_FIXTURE_SIZE_SLUG  = 'm2-test-size-180cm';
const M2_FILTER_FIXTURE_SIZE_NAME  = '[TEST-M2] 180cm';
const M2_FILTER_FIXTURE_BRAND_SLUG = 'm2-test-brand-atelier';
const M2_FILTER_FIXTURE_BRAND_NAME = '[TEST-M2] Atelier';

function despace_m2_filter_fixture_cleanup() {
	global $wpdb;

	$deleted_products = 0;
	$post_ids = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts}
			 WHERE post_type = 'product'
			   AND (post_name LIKE %s OR post_title LIKE %s)",
			'm2-test-fixture-size%',
			'%[TEST-FIXTURE-M2-FILTER]%'
		)
	);

	foreach ( $post_ids as $pid ) {
		$product = wc_get_product( (int) $pid );
		if ( $product ) {
			$product->delete( true );
			$deleted_products++;
		} elseif ( wp_delete_post( (int) $pid, true ) ) {
			$deleted_products++;
		}
	}

	$deleted_size_terms = 0;
	if ( taxonomy_exists( 'pa_size' ) ) {
		$term = get_term_by( 'slug', M2_FILTER_FIXTURE_SIZE_SLUG, 'pa_size' );
		if ( $term && ! is_wp_error( $term ) ) {
			$res = wp_delete_term( (int) $term->term_id, 'pa_size' );
			if ( true === $res ) {
				$deleted_size_terms++;
			}
		}
	}

	$deleted_brand_terms = 0;
	if ( taxonomy_exists( 'product_brand' ) ) {
		$term = get_term_by( 'slug', M2_FILTER_FIXTURE_BRAND_SLUG, 'product_brand' );
		if ( $term && ! is_wp_error( $term ) ) {
			$res = wp_delete_term( (int) $term->term_id, 'product_brand' );
			if ( true === $res ) {
				$deleted_brand_terms++;
			}
		}
	}

	if ( function_exists( 'wc_delete_product_transients' ) ) {
		wc_delete_product_transients();
	}

	return array(
		'status'              => 'cleaned',
		'deleted_products'    => $deleted_products,
		'deleted_size_terms'  => $deleted_size_terms,
		'deleted_brand_terms' => $deleted_brand_terms,
		'session_cleaned'     => false,
	);
}

if ( 'delete' === $action || 'cleanup' === $action ) {
	echo wp_json_encode( despace_m2_filter_fixture_cleanup(), JSON_PRETTY_PRINT );
	exit( 0 );
}

if ( 'create' === $action ) {
	if ( ! taxonomy_exists( 'pa_size' ) || ! taxonomy_exists( 'product_brand' ) ) {
		echo wp_json_encode(
			array(
				'error' => 'Required taxonomies pa_size or product_brand are not registered',
			)
		);
		exit( 1 );
	}

	// Ensure idempotent state before creating
	despace_m2_filter_fixture_cleanup();

	// 1. Create dedicated temporary pa_size term
	$size_res = wp_insert_term(
		M2_FILTER_FIXTURE_SIZE_NAME,
		'pa_size',
		array( 'slug' => M2_FILTER_FIXTURE_SIZE_SLUG )
	);
	if ( is_wp_error( $size_res ) ) {
		$existing = get_term_by( 'slug', M2_FILTER_FIXTURE_SIZE_SLUG, 'pa_size' );
		if ( ! $existing || is_wp_error( $existing ) ) {
			echo wp_json_encode( array( 'error' => $size_res->get_error_message() ) );
			exit( 1 );
		}
		$size_term_id = (int) $existing->term_id;
	} else {
		$size_term_id = (int) $size_res['term_id'];
	}

	// 2. Create dedicated temporary product_brand term
	$brand_res = wp_insert_term(
		M2_FILTER_FIXTURE_BRAND_NAME,
		'product_brand',
		array( 'slug' => M2_FILTER_FIXTURE_BRAND_SLUG )
	);
	if ( is_wp_error( $brand_res ) ) {
		$existing = get_term_by( 'slug', M2_FILTER_FIXTURE_BRAND_SLUG, 'product_brand' );
		if ( ! $existing || is_wp_error( $existing ) ) {
			echo wp_json_encode( array( 'error' => $brand_res->get_error_message() ) );
			exit( 1 );
		}
		$brand_term_id = (int) $existing->term_id;
	} else {
		$brand_term_id = (int) $brand_res['term_id'];
	}

	// 3. Create dedicated temporary simple product assigned to pa_size + product_brand
	$product = new WC_Product_Simple();
	$product->set_name( M2_FILTER_FIXTURE_TITLE );
	$product->set_slug( M2_FILTER_FIXTURE_SLUG );
	$product->set_status( 'publish' );
	$product->set_catalog_visibility( 'visible' );
	$product->set_sku( M2_FILTER_FIXTURE_SKU );
	$product->set_regular_price( '2200000' );
	$product->set_price( '2200000' );
	$product->set_stock_status( 'instock' );

	$cat = get_term_by( 'slug', 'bookshelves', 'product_cat' );
	if ( $cat && ! is_wp_error( $cat ) ) {
		$product->set_category_ids( array( (int) $cat->term_id ) );
	}

	$attr = new WC_Product_Attribute();
	$attr->set_id( wc_attribute_taxonomy_id_by_name( 'pa_size' ) );
	$attr->set_name( 'pa_size' );
	$attr->set_options( array( $size_term_id ) );
	$attr->set_visible( true );
	$attr->set_variation( false );
	$product->set_attributes( array( $attr ) );

	$product_id = $product->save();
	wp_set_object_terms( $product_id, array( $size_term_id ), 'pa_size' );
	wp_set_object_terms( $product_id, array( $brand_term_id ), 'product_brand' );
	wp_update_term_count_now( array( $size_term_id ), 'pa_size' );
	wp_update_term_count_now( array( $brand_term_id ), 'product_brand' );

	echo wp_json_encode(
		array(
			'status'        => 'created',
			'product_id'    => $product_id,
			'sku'           => M2_FILTER_FIXTURE_SKU,
			'size_slug'     => M2_FILTER_FIXTURE_SIZE_SLUG,
			'size_name'     => M2_FILTER_FIXTURE_SIZE_NAME,
			'size_term_id'  => $size_term_id,
			'brand_slug'    => M2_FILTER_FIXTURE_BRAND_SLUG,
			'brand_name'    => M2_FILTER_FIXTURE_BRAND_NAME,
			'brand_term_id' => $brand_term_id,
		),
		JSON_PRETTY_PRINT
	);
	exit( 0 );
}

$pa_size_terms       = get_terms( array( 'taxonomy' => 'pa_size', 'hide_empty' => false, 'fields' => 'slugs' ) );
$product_brand_terms = get_terms( array( 'taxonomy' => 'product_brand', 'hide_empty' => false, 'fields' => 'slugs' ) );
$published_products  = (int) ( wp_count_posts( 'product' )->publish ?? 0 );

echo wp_json_encode(
	array(
		'taxonomy_exists_pa_size'       => taxonomy_exists( 'pa_size' ),
		'taxonomy_exists_pa_brand'      => taxonomy_exists( 'pa_brand' ),
		'taxonomy_exists_product_brand' => taxonomy_exists( 'product_brand' ),
		'pa_size_terms'                 => is_array( $pa_size_terms ) ? $pa_size_terms : array(),
		'product_brand_terms'           => is_array( $product_brand_terms ) ? $product_brand_terms : array(),
		'published_products'            => $published_products,
	),
	JSON_PRETTY_PRINT
);
