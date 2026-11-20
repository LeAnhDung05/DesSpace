<?php
/**
 * DeSpace M2 Variable Product Fixture Helper
 * Can create and safely cleanup dedicated test fixtures on any fresh clone.
 * Usage:
 *   php m2-variation-fixture.php create
 *   php m2-variation-fixture.php delete
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
	echo json_encode( array( 'error' => 'WordPress or WooCommerce environment not found' ) );
	exit( 1 );
}

$action      = $argv[1] ?? 'status';
$session_key = $argv[2] ?? '';

function despace_cleanup_m2_fixtures( $target_session_key = '' ) {
	global $wpdb;
	$deleted = 0;

	// Find all products created with M2 test prefix
	$post_ids = $wpdb->get_col(
		"SELECT ID FROM {$wpdb->posts}
		 WHERE post_type IN ('product', 'product_variation')
		   AND (post_title LIKE '%[TEST-FIXTURE-M2]%' OR post_name LIKE 'm2-test-fixture%')"
	);

	foreach ( $post_ids as $pid ) {
		$prod = wc_get_product( $pid );
		if ( $prod ) {
			$prod->delete( true );
			$deleted++;
		}
	}

	// Clean up transients created during test run for this fixture
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '%_transient_wc_var_%' OR option_name LIKE '%_transient_timeout_wc_var_%'" );

	// Clean up ONLY the specific test session key if provided, NEVER truncating or touching other sessions
	$session_cleaned = false;
	if ( ! empty( $target_session_key ) ) {
		$clean_key = sanitize_text_field( $target_session_key );
		$wpdb->delete(
			$wpdb->prefix . 'woocommerce_sessions',
			array( 'session_key' => $clean_key )
		);
		$session_cleaned = true;
	}

	return array(
		'deleted'         => $deleted,
		'session_cleaned' => $session_cleaned,
	);
}

if ( 'delete' === $action || 'cleanup' === $action ) {
	$result = despace_cleanup_m2_fixtures( $session_key );
	echo json_encode( array( 'status' => 'cleaned', 'deleted_count' => $result['deleted'], 'session_cleaned' => $result['session_cleaned'] ) ) . "\n";
	exit( 0 );
}

if ( 'create' === $action ) {
	// First clean up any stale fixtures without touching sessions
	despace_cleanup_m2_fixtures();

	// 1. Create Variable Product with pa_color taxonomy
	$variable = new WC_Product_Variable();
	$variable->set_name( '[TEST-FIXTURE-M2] Ghế Thư Giãn DeSpace Lounge Chair' );
	$variable->set_slug( 'm2-test-fixture-lounge-chair' );
	$variable->set_status( 'publish' );
	$variable->set_catalog_visibility( 'visible' );
	$variable->set_description( 'Sản phẩm phục vụ kiểm thử biến thể M2 tự động có cleanup an toàn.' );
	$variable->set_short_description( 'Ghế thư giãn cao cấp bọc da DeSpace kiểm thử biến thể.' );

	// Reference existing published catalog product images that exist on disk (read-only; never created or deleted)
	$catalog_products = wc_get_products( array(
		'status'  => 'publish',
		'limit'   => 10,
		'orderby' => 'ID',
		'order'   => 'ASC',
	) );
	$valid_image_ids = array();
	foreach ( $catalog_products as $cp ) {
		$cid = (int) $cp->get_image_id();
		if ( $cid > 0 && ! in_array( $cid, $valid_image_ids, true ) ) {
			$file = get_attached_file( $cid );
			if ( $file && file_exists( $file ) && wp_get_attachment_image_url( $cid, 'full' ) ) {
				$valid_image_ids[] = $cid;
			}
		}
		if ( count( $valid_image_ids ) >= 2 ) {
			break;
		}
	}
	$img_id_main = ! empty( $valid_image_ids[0] ) ? $valid_image_ids[0] : 0;
	$img_id_a    = ! empty( $valid_image_ids[1] ) ? $valid_image_ids[1] : 0;
	// Variation B intentionally has NO own image (image_id = 0) to verify fallback to parent image
	$img_id_b    = 0;

	if ( $img_id_main ) {
		$variable->set_image_id( $img_id_main );
	}

	// Ensure pa_color terms
	$term_oak = get_term_by( 'slug', 'go-soi-modern-oak', 'pa_color' );
	if ( ! $term_oak ) {
		$t = wp_insert_term( 'Gỗ sồi Modern Oak', 'pa_color', array( 'slug' => 'go-soi-modern-oak' ) );
		$term_oak_id = is_array( $t ) ? $t['term_id'] : 0;
	} else {
		$term_oak_id = $term_oak->term_id;
	}

	$term_wenge = get_term_by( 'slug', 'nau-wenge', 'pa_color' );
	if ( ! $term_wenge ) {
		$t = wp_insert_term( 'Nâu Wenge', 'pa_color', array( 'slug' => 'nau-wenge' ) );
		$term_wenge_id = is_array( $t ) ? $t['term_id'] : 0;
	} else {
		$term_wenge_id = $term_wenge->term_id;
	}

	$taxonomy_id = wc_attribute_taxonomy_id_by_name( 'pa_color' );
	$attribute = new WC_Product_Attribute();
	$attribute->set_id( $taxonomy_id );
	$attribute->set_name( 'pa_color' );
	$attribute->set_options( array( $term_oak_id, $term_wenge_id ) );
	$attribute->set_position( 0 );
	$attribute->set_visible( true );
	$attribute->set_variation( true );
	$variable->set_attributes( array( $attribute ) );

	$var_parent_id = $variable->save();
	wp_set_object_terms( $var_parent_id, array( 'go-soi-modern-oak', 'nau-wenge' ), 'pa_color' );

	// Variation A: Gỗ sồi Modern Oak (In stock = 3, Sale price)
	$var_a = new WC_Product_Variation();
	$var_a->set_parent_id( $var_parent_id );
	$var_a->set_attributes( array( 'pa_color' => 'go-soi-modern-oak' ) );
	$var_a->set_regular_price( '21000000' );
	$var_a->set_sale_price( '18500000' );
	$var_a->set_price( '18500000' );
	$var_a->set_sku( 'M2-TEST-VAR-OAK' );
	$var_a->set_manage_stock( true );
	$var_a->set_stock_quantity( 3 );
	$var_a->set_stock_status( 'instock' );
	if ( $img_id_a ) {
		$var_a->set_image_id( $img_id_a );
	}
	$var_a_id = $var_a->save();

	// Variation B: Nâu Wenge (Out of stock = 0)
	$var_b = new WC_Product_Variation();
	$var_b->set_parent_id( $var_parent_id );
	$var_b->set_attributes( array( 'pa_color' => 'nau-wenge' ) );
	$var_b->set_regular_price( '19500000' );
	$var_b->set_price( '19500000' );
	$var_b->set_sku( 'M2-TEST-VAR-WENGE' );
	$var_b->set_manage_stock( true );
	$var_b->set_stock_quantity( 0 );
	$var_b->set_stock_status( 'outofstock' );
	if ( $img_id_b ) {
		$var_b->set_image_id( $img_id_b );
	}
	$var_b_id = $var_b->save();

	WC_Product_Variable::sync( $var_parent_id );

	// 2. Create Simple Out-of-Stock Product
	$simple_out = new WC_Product_Simple();
	$simple_out->set_name( '[TEST-FIXTURE-M2] Bàn Trà Stella Hết Hàng' );
	$simple_out->set_slug( 'm2-test-fixture-stella-outofstock' );
	$simple_out->set_status( 'publish' );
	$simple_out->set_catalog_visibility( 'visible' );
	$simple_out->set_regular_price( '8500000' );
	$simple_out->set_price( '8500000' );
	$simple_out->set_sku( 'M2-TEST-SIM-OUT' );
	$simple_out->set_manage_stock( true );
	$simple_out->set_stock_quantity( 0 );
	$simple_out->set_stock_status( 'outofstock' );
	$simple_out_id = $simple_out->save();

	// Retrieve a simple in-stock product from existing catalog for comparison
	$catalog_simple = wc_get_products( array(
		'status' => 'publish',
		'type'   => 'simple',
		'limit'  => 1,
		'stock_status' => 'instock',
		'exclude' => array( $simple_out_id ),
	) );
	$catalog_simple_id = ! empty( $catalog_simple[0] ) ? $catalog_simple[0]->get_id() : 0;

	$response = array(
		'status'            => 'created',
		'variable_parent'   => array(
			'id'        => $var_parent_id,
			'slug'      => 'm2-test-fixture-lounge-chair',
			'url'       => get_permalink( $var_parent_id ),
			'image_id'  => $img_id_main,
			'image_url' => $img_id_main ? wp_get_attachment_image_url( $img_id_main, 'full' ) : '',
		),
		'variation_a'       => array(
			'id'        => $var_a_id,
			'sku'       => 'M2-TEST-VAR-OAK',
			'color'     => 'go-soi-modern-oak',
			'price'     => '18500000',
			'stock'     => 3,
			'image_id'  => $img_id_a,
			'image_url' => $img_id_a ? wp_get_attachment_image_url( $img_id_a, 'full' ) : '',
		),
		'variation_b'       => array(
			'id'        => $var_b_id,
			'sku'       => 'M2-TEST-VAR-WENGE',
			'color'     => 'nau-wenge',
			'price'     => '19500000',
			'stock'     => 0,
			'image_id'  => 0,
			'image_url' => '',
		),
		'simple_outofstock' => array(
			'id'   => $simple_out_id,
			'sku'  => 'M2-TEST-SIM-OUT',
			'url'  => get_permalink( $simple_out_id ),
		),
		'simple_instock'    => array(
			'id'  => $catalog_simple_id,
			'url' => $catalog_simple_id ? get_permalink( $catalog_simple_id ) : '',
		),
	);

	echo json_encode( $response ) . "\n";
	exit( 0 );
}

echo json_encode( array( 'error' => 'Unknown action: ' . $action ) );
exit( 1 );
