<?php
/**
 * AJAX Handlers for AR Room Visualizer
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Save AR Layout (authenticated users only to prevent unauthenticated wp_options transient bloat)
add_action('wp_ajax_ar_save_layout', 'ar_save_layout');

function ar_get_guest_layout_key() {
    if ( function_exists( 'WC' ) && WC()->session && method_exists( WC()->session, 'get_customer_id' ) ) {
        return 'ar_guest_layout_' . md5( (string) WC()->session->get_customer_id() );
    }
    return '';
}

function ar_save_layout() {
    check_ajax_referer('ar_security', 'nonce');

    $user_id = get_current_user_id();
    if ( ! $user_id ) {
        wp_send_json_error( array( 'message' => 'Please sign in to save AR room layouts to your account.' ), 401 );
        return;
    }

    $raw_json = isset($_POST['layout_data']) ? wp_unslash($_POST['layout_data']) : '';
    if ( strlen( $raw_json ) > 262144 ) {
        wp_send_json_error(array('message' => 'Layout payload too large'), 413);
        return;
    }
    $layout_data = ! empty( $raw_json ) ? json_decode($raw_json, true) : array();

    if (empty($layout_data) || ! is_array($layout_data)) {
        wp_send_json_error(array('message' => 'No layout data provided'), 400);
        return;
    }

    $sanitized_products = array();
    if ( isset( $layout_data['products'] ) && is_array( $layout_data['products'] ) ) {
        foreach ( array_slice( $layout_data['products'], 0, 50 ) as $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }
            $sanitized_products[] = array(
                'id'       => absint( $item['id'] ?? 0 ),
                'name'     => sanitize_text_field( $item['name'] ?? '' ),
                'price'    => (float) ( $item['price'] ?? 0 ),
                'image'    => esc_url_raw( $item['image'] ?? '' ),
                'x'        => (float) ( $item['x'] ?? 0 ),
                'y'        => (float) ( $item['y'] ?? 0 ),
                'width'    => (float) ( $item['width'] ?? 100 ),
                'height'   => (float) ( $item['height'] ?? 100 ),
                'scale'    => (float) ( $item['scale'] ?? 1 ),
                'rotation' => (float) ( $item['rotation'] ?? 0 ),
            );
        }
    }

    $raw_room_image = isset($layout_data['room_image']) ? trim((string) $layout_data['room_image']) : '';
    $safe_room_img  = '';
    if ( '' !== $raw_room_image ) {
        if ( 0 === strpos( $raw_room_image, 'data:image/' ) && strlen( $raw_room_image ) <= 200000 ) {
            $safe_room_img = preg_replace( '/[^a-zA-Z0-9\/+,;=:\-]/', '', $raw_room_image );
        } elseif ( preg_match( '#^https?://#i', $raw_room_image ) ) {
            $safe_room_img = esc_url_raw( $raw_room_image );
        }
    }

    $layout = array(
        'user_id'    => $user_id,
        'products'   => $sanitized_products,
        'room_image' => $safe_room_img,
        'created_at' => current_time('mysql'),
    );

    $saved_layouts = get_user_meta($user_id, '_ar_saved_layouts', true);
    if (!is_array($saved_layouts)) {
        $saved_layouts = array();
    }
    $layout_id = uniqid('ar_layout_');
    $saved_layouts[$layout_id] = $layout;
    // Cap saved layouts per user to 10 most recent entries to prevent unbounded usermeta growth.
    if ( count( $saved_layouts ) > 10 ) {
        $saved_layouts = array_slice( $saved_layouts, -10, 10, true );
    }
    update_user_meta($user_id, '_ar_saved_layouts', $saved_layouts);

    wp_send_json_success(array(
        'layout_id' => $layout_id,
        'message' => 'Layout saved successfully'
    ));
}

// Get Saved Layouts
add_action('wp_ajax_ar_get_layouts', 'ar_get_layouts');
add_action('wp_ajax_nopriv_ar_get_layouts', 'ar_get_layouts');

function ar_get_layouts() {
    check_ajax_referer('ar_security', 'nonce');

    $user_id = get_current_user_id();

    if ($user_id) {
        $layouts = get_user_meta($user_id, '_ar_saved_layouts', true);
        wp_send_json_success(array('layouts' => $layouts ?: array()));
    } else {
        $guest_key = ar_get_guest_layout_key();
        $layout = get_transient($guest_key);
        wp_send_json_success(array('layouts' => $layout ? array($guest_key => $layout) : array()));
    }
}

// Delete Layout
add_action('wp_ajax_ar_delete_layout', 'ar_delete_layout');
add_action('wp_ajax_nopriv_ar_delete_layout', 'ar_delete_layout');

function ar_delete_layout() {
    check_ajax_referer('ar_security', 'nonce');

    $layout_id = isset($_POST['layout_id']) ? sanitize_text_field($_POST['layout_id']) : '';

    if (empty($layout_id)) {
        wp_send_json_error(array('message' => 'No layout ID provided'));
        return;
    }

    $user_id = get_current_user_id();

    if ($user_id) {
        $layouts = get_user_meta($user_id, '_ar_saved_layouts', true);
        if (isset($layouts[$layout_id])) {
            unset($layouts[$layout_id]);
            update_user_meta($user_id, '_ar_saved_layouts', $layouts);
            wp_send_json_success(array('message' => 'Layout deleted'));
        } else {
            wp_send_json_error(array('message' => 'Layout not found'));
        }
    } else {
        $guest_key = ar_get_guest_layout_key();
        if ($guest_key === $layout_id) {
            delete_transient($guest_key);
            wp_send_json_success(array('message' => 'Layout deleted'));
        } else {
            wp_send_json_error(array('message' => 'Layout not found'));
        }
    }
}

// Add to Cart from AR
add_action('wp_ajax_ar_add_to_cart', 'ar_add_to_cart');
add_action('wp_ajax_nopriv_ar_add_to_cart', 'ar_add_to_cart');

function ar_add_to_cart() {
    check_ajax_referer('ar_security', 'nonce');

    $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
    $quantity = isset($_POST['quantity']) ? absint($_POST['quantity']) : 1;

    if (!$product_id) {
        wp_send_json_error(array('message' => 'Invalid product'));
        return;
    }

    $product = wc_get_product($product_id);

    if (!$product || !$product->exists()) {
        wp_send_json_error(array('message' => 'Product not found'));
        return;
    }

    $cart_item_key = WC()->cart->add_to_cart($product_id, $quantity);

    if ($cart_item_key) {
        wp_send_json_success(array(
            'message' => 'Added to cart',
            'cart_url' => wc_get_cart_url(),
            'checkout_url' => wc_get_checkout_url()
        ));
    } else {
        wp_send_json_error(array('message' => 'Could not add to cart'));
    }
}

// Get AR-enabled products
add_action('wp_ajax_ar_get_products', 'ar_get_products');
add_action('wp_ajax_nopriv_ar_get_products', 'ar_get_products');

function ar_get_products() {
    check_ajax_referer('ar_security', 'nonce');

    $category = isset($_POST['category']) ? sanitize_text_field($_POST['category']) : 'all';
    $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';

    $args = array(
        'post_type' => 'product',
        'posts_per_page' => 20,
        'post_status' => 'publish',
    );

    if ($search) {
        $args['s'] = $search;
    }

    if ($category !== 'all') {
        $args['tax_query'] = array(
            array(
                'taxonomy' => 'product_cat',
                'field' => 'slug',
                'terms' => $category,
            ),
        );
    }

    $products = array();
    $query = new WP_Query($args);

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            global $product;

            $dimensions = $product->get_dimensions(false);

            $products[] = array(
                'id' => $product->get_id(),
                'name' => $product->get_name(),
                'price' => $product->get_price(),
                'price_html' => $product->get_price_html(),
                'image' => wp_get_attachment_image_url($product->get_image_id(), 'woocommerce_thumbnail'),
                'url' => get_permalink(),
                'width' => isset($dimensions['width']) ? floatval($dimensions['width']) : 100,
                'height' => isset($dimensions['height']) ? floatval($dimensions['height']) : 80,
                'length' => isset($dimensions['length']) ? floatval($dimensions['length']) : 80,
            );
        }
        wp_reset_postdata();
    }

    wp_send_json_success(array('products' => $products));
}
