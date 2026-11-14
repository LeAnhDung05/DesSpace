<?php
/**
 * AJAX Handlers for AR Room Visualizer
 */

// Save AR Layout
add_action('wp_ajax_ar_save_layout', 'ar_save_layout');
add_action('wp_ajax_nopriv_ar_save_layout', 'ar_save_layout');

function ar_save_layout() {
    check_ajax_referer('ar_security', 'nonce');

    $user_id = get_current_user_id();
    $layout_data = isset($_POST['layout_data']) ? json_decode(stripslashes($_POST['layout_data']), true) : array();

    if (empty($layout_data)) {
        wp_send_json_error(array('message' => 'No layout data provided'));
        return;
    }

    $layout = array(
        'user_id' => $user_id,
        'products' => $layout_data['products'],
        'room_image' => isset($layout_data['room_image']) ? $layout_data['room_image'] : '',
        'created_at' => current_time('mysql'),
    );

    // Save to user meta if logged in
    if ($user_id) {
        $saved_layouts = get_user_meta($user_id, '_ar_saved_layouts', true);
        if (!is_array($saved_layouts)) {
            $saved_layouts = array();
        }
        $layout_id = uniqid('ar_layout_');
        $saved_layouts[$layout_id] = $layout;
        update_user_meta($user_id, '_ar_saved_layouts', $saved_layouts);

        wp_send_json_success(array(
            'layout_id' => $layout_id,
            'message' => 'Layout saved successfully'
        ));
    } else {
        // Save to session/localStorage key
        $guest_key = 'ar_guest_layout_' . session_id();
        set_transient($guest_key, $layout, 7 * DAY_IN_SECONDS);

        wp_send_json_success(array(
            'layout_id' => $guest_key,
            'message' => 'Layout saved as guest'
        ));
    }
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
        $guest_key = 'ar_guest_layout_' . session_id();
        $layout = get_transient($guest_key);
        wp_send_json_success(array('layouts' => $layout ? array($guest_key => $layout) : array()));
    }
}

// Delete Layout
add_action('wp_ajax_ar_delete_layout', 'ar_delete_layout');

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
        $guest_key = 'ar_guest_layout_' . session_id();
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

    if ( ! is_user_logged_in() ) {
        wp_send_json_error( array(
            'require_login' => true,
            'redirect'      => add_query_arg( 'action', 'login', wc_get_page_permalink( 'myaccount' ) ),
            'message'       => function_exists( 'furniva_t' ) ? furniva_t( 'Please sign in to add products from 3D/AR to your cart.', 'Quý khách vui lòng đăng nhập để thêm sản phẩm từ không gian 3D/AR vào giỏ hàng.' ) : 'Please sign in to add products to your cart.'
        ) );
        return;
    }

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

            $dimensions = $product->get_dimensions();

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
