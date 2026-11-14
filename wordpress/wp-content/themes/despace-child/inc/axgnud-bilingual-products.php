<?php
/**
 * AXGNUD Bilingual Product Display & Search System
 * Automatically switches Product Name, Short Description, Description
 * based on current language (VI default / EN at /en/ or ?lang=en).
 * Expands search to include Vietnamese names, keywords, and attributes.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * 1. Kiểm tra ngôn ngữ hiện tại của giao diện (Mặc định '/' là 'vi', '/en/' hoặc ?lang=en là 'en')
 */
function axgnud_is_vietnamese_locale() {
    if ( is_admin() && ! wp_doing_ajax() ) {
        return false;
    }
    $req_uri = $_SERVER['REQUEST_URI'] ?? '';
    if ( strpos( $req_uri, '/en/' ) === 0 || strpos( $req_uri, '/en?' ) === 0 || ( isset( $_GET['lang'] ) && $_GET['lang'] === 'en' ) ) {
        return false;
    }
    if ( function_exists( 'pll_current_language' ) ) {
        return pll_current_language() === 'vi';
    }
    return get_locale() === 'vi' || strpos( get_locale(), 'vi' ) === 0 || strpos( $req_uri, '/en/' ) === false;
}

/**
 * 2. Tự động hiển thị Tên sản phẩm, Mô tả ngắn & Mô tả chi tiết theo đúng ngôn ngữ VI / EN đang chọn
 */
add_filter( 'the_title', function( $title, $post_id = 0 ) {
    if ( ! $post_id || get_post_type( $post_id ) !== 'product' || ! axgnud_is_vietnamese_locale() ) {
        return $title;
    }
    $vi_name = get_post_meta( $post_id, '_axgnud_name_vi', true );
    return ! empty( $vi_name ) ? $vi_name : $title;
}, 20, 2 );

add_filter( 'woocommerce_product_get_name', function( $name, $product ) {
    if ( ! axgnud_is_vietnamese_locale() ) {
        return $name;
    }
    $vi_name = get_post_meta( $product->get_id(), '_axgnud_name_vi', true );
    return ! empty( $vi_name ) ? $vi_name : $name;
}, 20, 2 );

add_filter( 'woocommerce_short_description', function( $desc ) {
    global $post;
    if ( ! $post || $post->post_type !== 'product' || ! axgnud_is_vietnamese_locale() ) {
        return $desc;
    }
    $vi_short = get_post_meta( $post->ID, '_axgnud_short_desc_vi', true );
    return ! empty( $vi_short ) ? wpautop( wp_kses_post( $vi_short ) ) : $desc;
}, 20 );

add_filter( 'the_content', function( $content ) {
    global $post;
    if ( ! $post || $post->post_type !== 'product' || ! axgnud_is_vietnamese_locale() ) {
        return $content;
    }
    $vi_desc = get_post_meta( $post->ID, '_axgnud_desc_vi', true );
    return ! empty( $vi_desc ) ? wp_kses_post( $vi_desc ) : $content;
}, 20 );

/**
 * 3. Mở rộng Tìm kiếm sản phẩm: Gõ tiếng Việt (?s=ghế, ?s=bàn, ?s=đèn, ?s=giường) hay tiếng Anh đều ra đúng sản phẩm
 */
function axgnud_is_product_search( $query ) {
    if ( is_admin() || ! $query->is_search() ) {
        return false;
    }
    $pt = $query->get( 'post_type' );
    if ( 'product' === $pt ) {
        return true;
    }
    if ( is_array( $pt ) && in_array( 'product', $pt, true ) ) {
        return true;
    }
    if ( isset( $_GET['post_type'] ) && 'product' === $_GET['post_type'] ) {
        return true;
    }
    return false;
}

/**
 * Map high-level category search terms (?s=ghế, ?s=bàn, ?s=đèn, ?s=giường, ?s=sofa)
 * to their respective category taxonomy query so each returns exactly the 5 products of that category.
 */
add_action( 'pre_get_posts', function( $query ) {
    if ( is_admin() || ! $query->is_search() ) {
        return;
    }
    if ( ! axgnud_is_product_search( $query ) ) {
        return;
    }

    $s = trim( (string) $query->get( 's' ) );
    $s_clean = mb_strtolower( $s, 'UTF-8' );

    $category_map = array(
        'ghế'        => 'chairs',
        'ghe'        => 'chairs',
        'chairs'     => 'chairs',
        'chair'      => 'chairs',
        'bàn'        => 'tables',
        'ban'        => 'tables',
        'tables'     => 'tables',
        'table'      => 'tables',
        'đèn'        => 'lighting',
        'den'        => 'lighting',
        'lighting'   => 'lighting',
        'lamp'       => 'lighting',
        'giường'     => 'beds',
        'giuong'     => 'beds',
        'beds'       => 'beds',
        'bed'        => 'beds',
        'sofa'       => 'sofas',
        'sofas'      => 'sofas',
        'trang trí'  => 'decoration',
        'decor'      => 'decoration',
        'decoration' => 'decoration',
    );

    if ( isset( $category_map[ $s_clean ] ) ) {
        $cat_slug = $category_map[ $s_clean ];
        $tax_query = (array) $query->get( 'tax_query' );
        $tax_query[] = array(
            'taxonomy' => 'product_cat',
            'field'    => 'slug',
            'terms'    => $cat_slug,
        );
        $query->set( 'tax_query', $tax_query );
        // Clear 's' for high-level category words so it cleanly lists the 5 category items
        $query->set( 's', '' );
        $query->set( 'posts_per_page', 12 );
    }
}, 5 );

add_filter( 'posts_join', function( $join, $query ) {
    global $wpdb;
    if ( axgnud_is_product_search( $query ) ) {
        if ( strpos( $join, 'axg_search_meta' ) === false ) {
            $join .= " LEFT JOIN {$wpdb->postmeta} AS axg_search_meta ON ({$wpdb->posts}.ID = axg_search_meta.post_id AND axg_search_meta.meta_key IN ('_axgnud_name_vi', '_axgnud_short_desc_vi', '_axgnud_search_keywords')) ";
        }
    }
    return $join;
}, 10, 2 );

add_filter( 'posts_where', function( $where, $query ) {
    global $wpdb;
    if ( axgnud_is_product_search( $query ) ) {
        $s = trim( (string) $query->get( 's' ) );
        if ( ! empty( $s ) ) {
            $like = '%' . $wpdb->esc_like( $s ) . '%';
            $where = preg_replace(
                "/\(\s*{$wpdb->posts}\.post_title\s+LIKE\s*(\'[^\']+\')\s*\)/",
                "({$wpdb->posts}.post_title LIKE $1 OR axg_search_meta.meta_value LIKE '" . esc_sql( $like ) . "')",
                $where
            );
        }
    }
    return $where;
}, 10, 2 );

add_filter( 'posts_distinct', function( $distinct, $query ) {
    if ( axgnud_is_product_search( $query ) ) {
        return 'DISTINCT';
    }
    return $distinct;
}, 10, 2 );
