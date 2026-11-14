<?php
/**
 * Furniva - Cart Page
 * Premium design matching AXGNUD aesthetic
 */

if ( ! defined( 'ABSPATH' ) ) exit;

get_header();

// Get cart
$cart = WC()->cart;
$cart_items = $cart->get_cart();
$subtotal = $cart->get_subtotal();
$total = $cart->get_total('raw');
$shipping_total = $cart->get_shipping_total();
$needs_shipping = $cart->needs_shipping();
$shipping_threshold = 500; // Free shipping threshold

// Calculate progress
$remaining_for_free_shipping = $needs_shipping ? max(0, $shipping_threshold - $subtotal) : 0;
$progress_percent = $needs_shipping ? min(100, ($subtotal / $shipping_threshold) * 100) : 100;
?>

<div class="ax-cart-page">

    <!-- Hero Section -->
    <section class="ax-cart-hero">
        <div class="ax-container">
            <nav class="ax-breadcrumb">
                <a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo esc_html( furniva_t( 'Home', 'Trang chủ' ) ); ?></a>
                <span>/</span>
                <span><?php echo esc_html( furniva_t( 'Shopping Cart', 'Giỏ hàng' ) ); ?></span>
            </nav>

            <h1 class="ax-cart-title">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                    <circle cx="9" cy="21" r="1.6"/><circle cx="19" cy="21" r="1.6"/>
                    <path d="M2.05 2.05h2l2.4 12.4a2 2 0 0 0 2 1.6h9.7a2 2 0 0 0 2-1.6l1.4-8H6"/>
                </svg>
                <?php echo esc_html( furniva_t( 'Shopping Cart', 'Giỏ hàng' ) ); ?>
            </h1>

            <?php if ( ! empty( $cart_items ) && $needs_shipping ) : ?>
            <!-- Free Shipping Progress -->
            <div class="ax-shipping-progress">
                <?php if ( $remaining_for_free_shipping > 0 ) : ?>
                    <p class="ax-progress-text">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/>
                            <circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>
                        </svg>
                        <?php printf( esc_html( 'Add %s more for FREE shipping!', 'Thêm %s nữa để được MIỄN PHÍ giao hàng!' ), wp_kses_post( wc_price( $remaining_for_free_shipping ) ) ); ?>
                    </p>
                <?php else : ?>
                    <p class="ax-progress-text success">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                            <polyline points="22 4 12 14.01 9 11.01"/>
                        </svg>
                        <?php echo esc_html( furniva_t( 'You\'ve unlocked FREE shipping!', 'Bạn đã được MIỄN PHÍ giao hàng!' ) ); ?>
                    </p>
                <?php endif; ?>
                <div class="ax-progress-bar">
                    <div class="ax-progress-fill" style="width: <?php echo esc_attr( $progress_percent ); ?>%"></div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <div class="ax-container">
        <?php if ( ! empty( $cart_items ) ) : ?>

        <div class="ax-cart-grid">

            <!-- Cart Items -->
            <div class="ax-cart-items">
                <table class="ax-cart-table">
                    <thead>
                        <tr>
                            <th colspan="2"><?php echo esc_html( furniva_t( 'Product', 'Sản phẩm' ) ); ?></th>
                            <th><?php echo esc_html( furniva_t( 'Price', 'Giá' ) ); ?></th>
                            <th><?php echo esc_html( furniva_t( 'Quantity', 'Số lượng' ) ); ?></th>
                            <th><?php echo esc_html( furniva_t( 'Subtotal', 'Tạm tính' ) ); ?></th>
                        </tr>
                    </thead>
                    <tbody class="fv-reveal-stagger">
                        <?php
                        foreach ( $cart_items as $cart_item_key => $cart_item ) :
                            $_product = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
                            if ( ! $_product || ! $_product->exists() ) continue;

                            $product_id = apply_filters( 'woocommerce_cart_item_product_id', $cart_item['product_id'], $cart_item, $cart_item_key );
                            $product_name = apply_filters( 'woocommerce_cart_item_name', $_product->get_name(), $cart_item, $cart_item_key );
                            $thumbnail = apply_filters( 'woocommerce_cart_item_thumbnail', $_product->get_image(), $cart_item, $cart_item_key );
                            $product_price = apply_filters( 'woocommerce_cart_item_price', WC()->cart->get_product_price( $_product ), $cart_item, $cart_item_key );
                            $product_subtotal = apply_filters( 'woocommerce_cart_item_subtotal', WC()->cart->get_product_subtotal( $_product, $cart_item['quantity'] ), $cart_item, $cart_item_key );
                            $product_permalink = apply_filters( 'woocommerce_cart_item_permalink', $_product->is_visible() ? $_product->get_permalink( $cart_item ) : '', $cart_item, $cart_item_key );
                        ?>
                        <tr class="ax-cart-item" data-item-key="<?php echo esc_attr( $cart_item_key ); ?>">
                            <td class="ax-item-remove">
                                <?php
                                echo apply_filters( 'woocommerce_cart_item_remove_link', sprintf(
                                    '<a href="%s" class="ax-remove-btn" aria-label="%s" data-cart-item-key="%s">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                                        </svg>
                                    </a>',
                                    esc_url( wc_get_cart_remove_url( $cart_item_key ) ),
                                    esc_attr( sprintf( furniva_t( 'Remove %s', 'Xóa %s' ), $product_name ) ),
                                    esc_attr( $cart_item_key )
                                ), $cart_item_key );
                                ?>
                            </td>
                            <td class="ax-item-product">
                                <?php if ( $product_permalink ) : ?>
                                    <a href="<?php echo esc_url( $product_permalink ); ?>" class="ax-item-image">
                                        <?php echo $thumbnail; ?>
                                    </a>
                                <?php else : ?>
                                    <span class="ax-item-image"><?php echo $thumbnail; ?></span>
                                <?php endif; ?>
                                <div class="ax-item-info">
                                    <?php if ( $product_permalink ) : ?>
                                        <a href="<?php echo esc_url( $product_permalink ); ?>" class="ax-item-name">
                                            <?php echo $product_name; ?>
                                        </a>
                                    <?php else : ?>
                                        <span class="ax-item-name"><?php echo $product_name; ?></span>
                                    <?php endif; ?>

                                    <?php
                                    // Variation data
                                    if ( ! empty( $cart_item['variation_id'] ) ) {
                                        $variation_id = $cart_item['variation_id'];
                                        $variation = new WC_product_variation( $variation_id );
                                        $attributes = $variation->get_attributes();
                                        if ( ! empty( $attributes ) ) {
                                            echo '<div class="ax-item-variation">';
                                            foreach ( $attributes as $attr_name => $attr_value ) {
                                                $attr_label = wc_attribute_label( str_replace( 'pa_', '', $attr_name ) );
                                                echo '<span>' . esc_html( $attr_label . ': ' . ucfirst( $attr_value ) ) . '</span>';
                                            }
                                            echo '</div>';
                                        }
                                    }
                                    ?>
                                </div>
                            </td>
                            <td class="ax-item-price">
                                <?php echo $product_price; ?>
                            </td>
                            <td class="ax-item-quantity">
                                <div class="ax-qty-control">
                                    <button type="button" class="ax-qty-btn ax-qty-minus" aria-label="<?php echo esc_attr( furniva_t( 'Decrease quantity', 'Giảm số lượng' ) ); ?>">−</button>
                                    <?php
                                    if ( $_product->is_sold_individually() ) {
                                        $min_quantity = 1;
                                        $max_quantity = 1;
                                    } else {
                                        $min_quantity = 0;
                                        $max_quantity = $_product->get_max_purchase_quantity();
                                    }
                                    ?>
                                    <input type="number" class="ax-qty-input"
                                           value="<?php echo esc_attr( $cart_item['quantity'] ); ?>"
                                           min="<?php echo esc_attr( $min_quantity ); ?>"
                                           max="<?php echo esc_attr( $max_quantity ); ?>"
                                           data-cart-item-key="<?php echo esc_attr( $cart_item_key ); ?>" />
                                    <button type="button" class="ax-qty-btn ax-qty-plus" aria-label="<?php echo esc_attr( furniva_t( 'Increase quantity', 'Tăng số lượng' ) ); ?>">+</button>
                                </div>
                            </td>
                            <td class="ax-item-subtotal">
                                <?php echo $product_subtotal; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Cart Actions -->
                <div class="ax-cart-actions">
                    <a href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>" class="ax-btn ax-btn-secondary">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M19 12H5M12 19l-7-7 7-7"/>
                        </svg>
                        <span><?php echo esc_html( furniva_t( 'Continue Shopping', 'Tiếp tục mua sắm' ) ); ?></span>
                    </a>

                    <button type="button" class="ax-btn ax-btn-text" id="axUpdateCart">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/>
                            <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/>
                        </svg>
                        <span><?php echo esc_html( furniva_t( 'Update Cart', 'Cập nhật giỏ hàng' ) ); ?></span>
                    </button>
                </div>
            </div>

            <!-- Cart Summary -->
            <aside class="ax-cart-summary">
                <div class="ax-summary-card">
                    <h2 class="ax-summary-title"><?php echo esc_html( furniva_t( 'Order Summary', 'Tóm tắt đơn hàng' ) ); ?></h2>

                    <div class="ax-summary-rows">
                        <div class="ax-summary-row">
                            <span><?php echo esc_html( furniva_t( 'Subtotal', 'Tạm tính' ) ); ?></span>
                            <span><?php echo wp_kses_post( $cart->get_cart_subtotal() ); ?></span>
                        </div>

                        <?php if ( $needs_shipping ) : ?>
                        <div class="ax-summary-row">
                            <span><?php echo esc_html( furniva_t( 'Shipping', 'Vận chuyển' ) ); ?></span>
                            <span class="ax-shipping-note">
                                <?php if ( $subtotal >= $shipping_threshold ) : ?>
                                    <span class="ax-free-shipping"><?php echo esc_html( furniva_t( 'FREE', 'MIỄN PHÍ' ) ); ?></span>
                                <?php else : ?>
                                    <?php echo esc_html( furniva_t( 'Calculated at checkout', 'Tính khi thanh toán' ) ); ?>
                                <?php endif; ?>
                            </span>
                        </div>
                        <?php endif; ?>

                        <?php foreach ( $cart->get_coupons() as $code => $coupon ) : ?>
                        <div class="ax-summary-row ax-coupon-row">
                            <span><?php echo esc_html( furniva_t( 'Coupon', 'Mã giảm giá' ) ); ?>: <strong><?php echo esc_html( $code ); ?></strong></span>
                            <span class="ax-coupon-amount">−<?php echo wp_kses_post( $cart->get_coupon_discount_amount( $code ) ); ?></span>
                        </div>
                        <?php endforeach; ?>

                        <?php if ( wc_tax_enabled() && ! WC()->cart->display_prices_including_tax() ) : ?>
                        <?php foreach ( $cart->get_tax_totals() as $code => $tax ) : ?>
                        <div class="ax-summary-row">
                            <span><?php echo esc_html( $tax->label ); ?></span>
                            <span><?php echo wp_kses_post( $tax->formatted_amount ); ?></span>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <div class="ax-summary-total">
                        <span><?php echo esc_html( furniva_t( 'Total', 'Tổng cộng' ) ); ?></span>
                        <span class="ax-total-amount"><?php echo wp_kses_post( $cart->get_total() ); ?></span>
                    </div>

                    <?php if ( $subtotal < $shipping_threshold && $needs_shipping ) : ?>
                    <p class="ax-tax-note">
                        <?php echo esc_html( furniva_t( 'Tax & shipping calculated at checkout', 'Thuế và vận chuyển tính khi thanh toán' ) ); ?>
                    </p>
                    <?php endif; ?>

                    <!-- Coupon Form -->
                    <form class="ax-coupon-form" method="post">
                        <div class="ax-coupon-input-wrap">
                            <input type="text" name="coupon_code" class="ax-coupon-input"
                                   placeholder="<?php echo esc_attr( furniva_t( 'Enter coupon code', 'Nhập mã giảm giá' ) ); ?>" />
                            <button type="submit" class="ax-btn ax-btn-coupon" name="apply_coupon" value="<?php esc_attr_e( 'Apply', 'Áp dụng' ); ?>">
                                <?php esc_html_e( 'Apply', 'Áp dụng' ); ?>
                            </button>
                        </div>
                        <?php do_action( 'woocommerce_cart_coupon' ); ?>
                    </form>

                    <a href="<?php echo esc_url( wc_get_checkout_url() ); ?>" class="ax-btn ax-btn-primary ax-btn-checkout">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/>
                        </svg>
                        <span><?php echo esc_html( furniva_t( 'Proceed to Checkout', 'Tiến hành thanh toán' ) ); ?></span>
                    </a>

                    <!-- Trust Badges -->
                    <div class="ax-summary-trust">
                        <div class="ax-trust-item">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                                <polyline points="9 12 11 14 15 10"/>
                            </svg>
                            <span><?php echo esc_html( furniva_t( 'Secure Checkout', 'Thanh toán bảo mật' ) ); ?></span>
                        </div>
                        <div class="ax-trust-item">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <polyline points="9 11 12 14 22 4"/>
                                <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
                            </svg>
                            <span><?php echo esc_html( furniva_t( '30-Day Returns', 'Đổi trả 30 ngày' ) ); ?></span>
                        </div>
                    </div>
                </div>
            </aside>

        </div>

        <?php else : ?>

        <!-- Empty Cart -->
        <div class="ax-cart-empty fv-reveal-up">
            <div class="ax-empty-icon">
                <svg width="100" height="100" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" aria-hidden="true">
                    <circle cx="9" cy="21" r="1.6"/><circle cx="19" cy="21" r="1.6"/>
                    <path d="M2.05 2.05h2l2.4 12.4a2 2 0 0 0 2 1.6h9.7a2 2 0 0 0 2-1.6l1.4-8H6"/>
                </svg>
            </div>
            <h2><?php echo esc_html( furniva_t( 'Your cart is empty', 'Giỏ hàng trống' ) ); ?></h2>
            <p><?php echo esc_html( furniva_t( 'Looks like you haven\'t added any items yet.', 'Có vẻ như bạn chưa thêm sản phẩm nào.' ) ); ?></p>
            <a href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>" class="ax-btn ax-btn-primary">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <circle cx="9" cy="21" r="1.6"/><circle cx="19" cy="21" r="1.6"/>
                    <path d="M2.05 2.05h2l2.4 12.4a2 2 0 0 0 2 1.6h9.7a2 2 0 0 0 2-1.6l1.4-8H6"/>
                </svg>
                <span><?php echo esc_html( furniva_t( 'Start Shopping', 'Bắt đầu mua sắm' ) ); ?></span>
            </a>
        </div>

        <?php endif; ?>

    </div>

</div>

<script>
jQuery(function($) {
    var cartNonce = '<?php echo wp_create_nonce( 'furniva_cart' ); ?>';

    // Quantity controls
    $(document).on('click', '.ax-qty-minus', function() {
        var $input = $(this).siblings('.ax-qty-input');
        var val = parseInt($input.val()) || 1;
        if (val > 1) {
            $input.val(val - 1).trigger('change');
        }
    });

    $(document).on('click', '.ax-qty-plus', function() {
        var $input = $(this).siblings('.ax-qty-input');
        var val = parseInt($input.val()) || 1;
        var max = parseInt($input.attr('max')) || 999;
        if (val < max) {
            $input.val(val + 1).trigger('change');
        }
    });

    // Update quantity on change
    $(document).on('change', '.ax-qty-input', function() {
        var $input = $(this);
        var quantity = parseInt($input.val()) || 1;
        var itemKey = $input.data('cart-item-key');

        if (quantity < 1) {
            quantity = 1;
            $input.val(1);
        }

        $.ajax({
            url: '<?php echo admin_url( 'admin-ajax.php' ); ?>',
            type: 'POST',
            data: {
                action: 'furniva_cart_update_qty',
                security: cartNonce,
                cart_item_key: itemKey,
                quantity: quantity
            },
            success: function(response) {
                if (response.success) {
                    $(document.body).trigger('wc_fragment_refresh');
                    setTimeout(function() {
                        location.reload();
                    }, 300);
                }
            },
            error: function() {
                location.reload();
            }
        });
    });

    // Remove item
    $(document).on('click', '.ax-remove-btn', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var $row = $btn.closest('.ax-cart-item');
        var itemKey = $btn.data('cart-item-key');

        $row.addClass('removing');

        $.ajax({
            url: '<?php echo admin_url( 'admin-ajax.php' ); ?>',
            type: 'POST',
            data: {
                action: 'furniva_cart_remove_item',
                security: cartNonce,
                cart_item_key: itemKey
            },
            success: function(response) {
                if (response.success) {
                    $row.slideUp(300, function() {
                        $(document.body).trigger('wc_fragment_refresh');
                        setTimeout(function() {
                            location.reload();
                        }, 300);
                    });
                }
            },
            error: function() {
                location.reload();
            }
        });
    });

    // Update cart button
    $('#axUpdateCart').on('click', function() {
        var $form = $('form.woocommerce-cart-form');
        block($form);

        $.ajax({
            type: 'POST',
            url: $form.attr('action'),
            data: $form.serialize(),
            success: function() {
                $(document.body).trigger('wc_fragment_refresh');
                setTimeout(function() {
                    location.reload();
                }, 300);
            },
            error: function() {
                location.reload();
            }
        });
    });

    function block($element) {
        $element.addClass('processing').fadeTo('400', '0.6').block({
            message: null,
            overlayCSS: { opacity: 0.6 }
        });
    }
});
</script>

<?php get_footer(); ?>
