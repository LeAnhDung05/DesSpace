<?php
/**
 * Furniva - Wishlist Page
 * Premium design matching AXGNUD aesthetic
 */

if ( ! defined( 'ABSPATH' ) ) exit;

get_header();

// Get wishlist items via YITH
$wishlist = null;
$wishlist_items = array();

if ( class_exists( 'YITH_WCWL_Wishlist_Factory' ) ) {
    $current_wishlist = YITH_WCWL_Wishlist_Factory::get_current_wishlist();
    if ( $current_wishlist && method_exists( $current_wishlist, 'get_items' ) ) {
        $wishlist_items = $current_wishlist->get_items();
    }
}

if ( empty( $wishlist_items ) && function_exists( 'yith_wcwl_wishlists' ) ) {
    $wishlist_items = yith_wcwl_wishlists()->get_items();
} elseif ( empty( $wishlist_items ) && function_exists( 'YITH_WCWL' ) ) {
    $wishlist_items = YITH_WCWL()->get_products();
}

if ( ! is_array( $wishlist_items ) ) {
    $wishlist_items = array();
}

$shop_url = wc_get_page_permalink( 'shop' );
?>

<div class="ax-wishlist-page">

    <!-- Hero Section -->
    <section class="ax-wishlist-hero">
        <div class="ax-container">
            <nav class="ax-breadcrumb">
                <a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo esc_html( furniva_t( 'Home', 'Trang chủ' ) ); ?></a>
                <span>/</span>
                <span><?php echo esc_html( furniva_t( 'Wishlist', 'Danh sách yêu thích' ) ); ?></span>
            </nav>

            <h1 class="ax-wishlist-title">
                <span class="ax-title-icon">♥</span>
                <?php echo esc_html( furniva_t( 'My Wishlist', 'Danh sách yêu thích' ) ); ?>
            </h1>
            <p class="ax-wishlist-subtitle" id="axWishlistSubtitle">
                <?php
                $count = is_array( $wishlist_items ) ? count( $wishlist_items ) : 0;
                if ( function_exists( 'furniva_is_vi' ) && furniva_is_vi() ) {
                    printf( '%d sản phẩm đã lưu', $count );
                } else {
                    printf( esc_html( _n( '%d item saved for later', '%d items saved for later', $count, 'furniva' ) ), $count );
                }
                ?>
            </p>
        </div>
    </section>

    <div class="ax-container">
        <?php if ( ! is_user_logged_in() ) : ?>
            <div class="ax-wishlist-guest-notice" style="background:#ffffff; border:1px solid #e9e4dc; border-radius:14px; padding:28px 24px; margin:30px 0; text-align:center; box-shadow:0 4px 18px rgba(0,0,0,0.04);">
                <span style="display:inline-block; font-size:11px; font-weight:700; color:#c9933f; letter-spacing:0.8px; margin-bottom:8px;">✦ <?php echo esc_html( furniva_t( 'AXGNUD PRIVILEGE CLUB', 'ĐẶC QUYỀN HỘI VIÊN' ) ); ?></span>
                <h3 style="font-family:'Newsreader',serif; font-size:23px; color:#191715; margin:0 0 10px;"><?php echo esc_html( furniva_t( 'Sign in to sync your wishlist across all devices', 'Đăng nhập để lưu và đồng bộ danh sách yêu thích trên mọi thiết bị' ) ); ?></h3>
                <p style="font-size:14px; color:#6a645d; margin:0 0 18px; max-width:620px; margin-left:auto; margin-right:auto;"><?php echo esc_html( furniva_t( 'Save your bespoke architectural furniture pieces and receive real-time updates when prices change or stock runs low.', 'Lưu giữ những tác phẩm nội thất yêu thích, nhận thông báo ưu đãi độc quyền và bảo lưu danh mục thiết kế riêng của bạn.' ) ); ?></p>
                <div style="display:inline-flex; gap:12px; flex-wrap:wrap; justify-content:center;">
                    <a href="<?php echo esc_url( add_query_arg( array( 'action' => 'login', 'redirect_to' => urlencode( get_permalink() ) ), wc_get_page_permalink( 'myaccount' ) ) ); ?>" style="display:inline-flex; align-items:center; height:42px; padding:0 24px; background:#191715; color:#fff; border-radius:8px; font-size:13.5px; font-weight:600; text-decoration:none;"><?php echo esc_html( furniva_t( 'Sign In', 'Đăng nhập ngay' ) ); ?></a>
                    <a href="<?php echo esc_url( add_query_arg( array( 'action' => 'register', 'redirect_to' => urlencode( get_permalink() ) ), wc_get_page_permalink( 'myaccount' ) ) ); ?>" style="display:inline-flex; align-items:center; height:42px; padding:0 24px; background:#fff; color:#191715; border:1px solid #dcd7ce; border-radius:8px; font-size:13.5px; font-weight:600; text-decoration:none;"><?php echo esc_html( furniva_t( 'Create Account (10% Off)', 'Đăng ký thành viên (Giảm 10%)' ) ); ?></a>
                </div>
            </div>
        <?php endif; ?>

        <?php if ( ! empty( $wishlist_items ) ) : ?>

        <!-- Wishlist Table -->
        <div class="ax-wishlist-table-wrap">
            <table class="ax-wishlist-table">
                <thead>
                    <tr>
                        <th class="ax-product-col"><?php echo esc_html( furniva_t( 'Product', 'Sản phẩm' ) ); ?></th>
                        <th class="ax-name-col"></th>
                        <th class="ax-price-col"><?php echo esc_html( furniva_t( 'Price', 'Giá' ) ); ?></th>
                        <th class="ax-stock-col"><?php echo esc_html( furniva_t( 'Stock', 'Tình trạng' ) ); ?></th>
                        <th class="ax-action-col"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $wishlist_items as $item ) :
                        $product_id = 0;
                        if ( is_object( $item ) ) {
                            if ( method_exists( $item, 'get_product_id' ) ) {
                                $product_id = $item->get_product_id();
                            } elseif ( isset( $item->prod_id ) ) {
                                $product_id = $item->prod_id;
                            } elseif ( isset( $item->product_id ) ) {
                                $product_id = $item->product_id;
                            }
                        } elseif ( is_array( $item ) ) {
                            $product_id = isset( $item['prod_id'] ) ? $item['prod_id'] : ( isset( $item['product_id'] ) ? $item['product_id'] : 0 );
                        } elseif ( is_numeric( $item ) ) {
                            $product_id = absint( $item );
                        }
                        $product = wc_get_product( $product_id );
                        if ( ! $product ) continue;

                        $product_url = get_permalink( $product_id );
                        $image = $product->get_image( 'woocommerce_gallery_thumbnail' );
                        $name = $product->get_name();
                        $price = $product->get_price_html();
                        $in_stock = $product->is_in_stock();
                        $add_to_cart_url = esc_url( add_query_arg( 'add_to_cart', $product_id, wc_get_cart_url() ) );
                    ?>
                    <tr class="ax-wishlist-item" data-product-id="<?php echo esc_attr( $product_id ); ?>">
                        <td class="ax-product-col">
                            <a href="<?php echo esc_url( $product_url ); ?>" class="ax-product-image">
                                <?php echo $image; ?>
                            </a>
                        </td>
                        <td class="ax-name-col">
                            <a href="<?php echo esc_url( $product_url ); ?>" class="ax-product-name">
                                <?php echo esc_html( $name ); ?>
                            </a>
                            <?php if ( $product->get_short_description() ) : ?>
                            <p class="ax-product-excerpt"><?php echo wp_kses_post( wp_trim_words( $product->get_short_description(), 12 ) ); ?></p>
                            <?php endif; ?>
                        </td>
                        <td class="ax-price-col">
                            <span class="ax-price"><?php echo $price; ?></span>
                        </td>
                        <td class="ax-stock-col">
                            <?php if ( $in_stock ) : ?>
                                <span class="ax-stock in-stock">
                                    <span class="ax-stock-dot"></span>
                                    <?php echo esc_html( furniva_t( 'In stock', 'Còn hàng' ) ); ?>
                                </span>
                            <?php else : ?>
                                <span class="ax-stock out-stock">
                                    <?php echo esc_html( furniva_t( 'Out of stock', 'Hết hàng' ) ); ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="ax-action-col">
                            <?php if ( $in_stock ) : ?>
                            <button type="button" class="ax-btn ax-btn-primary ax-btn-add-to-cart"
                                    data-product-id="<?php echo esc_attr( $product_id ); ?>"
                                    aria-label="<?php echo esc_attr( sprintf( furniva_t( 'Add %s to cart', 'Thêm %s vào giỏ' ), $name ) ); ?>">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <circle cx="9" cy="21" r="1.6"/><circle cx="19" cy="21" r="1.6"/>
                                    <path d="M2.05 2.05h2l2.4 12.4a2 2 0 0 0 2 1.6h9.7a2 2 0 0 0 2-1.6l1.4-8H6"/>
                                </svg>
                                <span><?php echo esc_html( furniva_t( 'Add to Cart', 'Thêm vào giỏ' ) ); ?></span>
                            </button>
                            <?php else : ?>
                            <button type="button" class="ax-btn ax-btn-disabled" disabled>
                                <?php echo esc_html( furniva_t( 'Out of Stock', 'Hết hàng' ) ); ?>
                            </button>
                            <?php endif; ?>

                            <button type="button" class="ax-btn-remove"
                                    data-remove-id="<?php echo esc_attr( $product_id ); ?>"
                                    aria-label="<?php echo esc_attr( furniva_t( 'Remove from wishlist', 'Xóa khỏi danh sách' ) ); ?>">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                                </svg>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Actions Row -->
        <div class="ax-wishlist-actions">
            <a href="<?php echo esc_url( $shop_url ); ?>" class="ax-btn ax-btn-secondary">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M19 12H5M12 19l-7-7 7-7"/>
                </svg>
                <span><?php echo esc_html( furniva_t( 'Continue Shopping', 'Tiếp tục mua sắm' ) ); ?></span>
            </a>

            <?php if ( function_exists( 'YITH_WCWL' ) ) : ?>
            <button type="button" class="ax-btn ax-btn-text ax-clear-wishlist" id="axClearWishlist">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                </svg>
                <span><?php echo esc_html( furniva_t( 'Clear Wishlist', 'Xóa tất cả' ) ); ?></span>
            </button>
            <?php endif; ?>
        </div>

        <?php else : ?>

        <!-- Empty State -->
        <div class="ax-wishlist-empty fv-reveal-up">
            <div class="ax-empty-icon">
                <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                    <path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21.2l7.8-7.8 1-1a5.5 5.5 0 0 0 0-7.8z"/>
                </svg>
            </div>
            <h2><?php echo esc_html( furniva_t( 'Your wishlist is empty', 'Danh sách yêu thích trống' ) ); ?></h2>
            <p><?php echo esc_html( furniva_t( 'Save items you love by clicking the heart icon on any product.', 'Lưu sản phẩm bạn yêu thích bằng cách nhấn vào biểu tượng trái tim trên sản phẩm.' ) ); ?></p>
            <a href="<?php echo esc_url( $shop_url ); ?>" class="ax-btn ax-btn-primary">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <circle cx="9" cy="21" r="1.6"/><circle cx="19" cy="21" r="1.6"/>
                    <path d="M2.05 2.05h2l2.4 12.4a2 2 0 0 0 2 1.6h9.7a2 2 0 0 0 2-1.6l1.4-8H6"/>
                </svg>
                <span><?php echo esc_html( furniva_t( 'Start Shopping', 'Bắt đầu mua sắm' ) ); ?></span>
            </a>
        </div>

        <?php endif; ?>

        <!-- Recently Viewed / Recommendations -->
        <?php
        $recently_viewed = isset( $_COOKIE['woocommerce_recently_viewed'] ) ? explode( '|', sanitize_text_field( wp_unslash( $_COOKIE['woocommerce_recently_viewed'] ) ) ) : array();
        $recently_viewed = array_filter( array_map( 'absint', $recently_viewed ) );

        if ( ! empty( $recently_viewed ) ) :
        ?>
        <section class="ax-wishlist-recommendations">
            <h2 class="ax-section-title">
                <?php echo esc_html( furniva_t( 'Recently Viewed', 'Đã xem gần đây' ) ); ?>
            </h2>
            <div class="ax-products-grid fv-reveal-stagger">
                <?php
                $args = array(
                    'post_type'      => 'product',
                    'post__in'       => array_slice( $recently_viewed, 0, 4 ),
                    'posts_per_page' => 4,
                );
                $recent_query = new WP_Query( $args );
                while ( $recent_query->have_posts() ) : $recent_query->the_post();
                    wc_get_template_part( 'content', 'product' );
                endwhile; wp_reset_postdata();
                ?>
            </div>
        </section>
        <?php endif; ?>
    </div>

</div>

<script>
jQuery(function($) {
    var wishlistNonce = '<?php echo wp_create_nonce( 'furniva_wishlist' ); ?>';

    // Add to cart from wishlist
    $('.ax-btn-add-to-cart').on('click', function() {
        var $btn = $(this);
        var productId = $btn.data('product-id');

        $btn.addClass('loading').prop('disabled', true);

        $.ajax({
            url: '<?php echo admin_url( 'admin-ajax.php' ); ?>',
            type: 'POST',
            data: {
                action: 'furniva_wishlist_add_to_cart',
                security: wishlistNonce,
                product_id: productId,
                quantity: 1
            },
            success: function(response) {
                if (response.success) {
                    $btn.removeClass('loading').addClass('added');
                    $btn.find('span').text('<?php echo esc_js( furniva_t( 'Added!', 'Đã thêm!' ) ); ?>');

                    // Update mini cart
                    $(document.body).trigger('added_to_cart');

                    // Show toast
                    if (window.furnivaToast) {
                        furnivaToast('<?php echo esc_js( furniva_t( 'Added to cart!', 'Đã thêm vào giỏ!' ) ); ?>');
                    }

                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                }
            },
            error: function() {
                $btn.removeClass('loading').prop('disabled', false);
            }
        });
    });

    // Remove from wishlist
    $('.ax-btn-remove').on('click', function() {
        var $btn = $(this);
        var $row = $btn.closest('.ax-wishlist-item');
        var productId = $btn.data('remove-id');

        $row.addClass('removing');

        $.ajax({
            url: '<?php echo admin_url( 'admin-ajax.php' ); ?>',
            type: 'POST',
            data: {
                action: 'furniva_wishlist_remove',
                security: wishlistNonce,
                product_id: productId
            },
            success: function(res) {
                $row.slideUp(300, function() {
                    $(this).remove();

                    if (res && res.data && typeof res.data.count !== 'undefined') {
                        $('.fv-wishlist-count').text(res.data.count).toggle(res.data.count > 0);
                        var isVi = <?php echo ( function_exists( 'furniva_is_vi' ) && furniva_is_vi() ) ? 'true' : 'false'; ?>;
                        var cnt = res.data.count;
                        $('#axWishlistSubtitle').text(isVi ? cnt + ' sản phẩm đã lưu' : (cnt === 1 ? '1 item saved for later' : cnt + ' items saved for later'));
                    }

                    // Check if wishlist is empty
                    if ($('.ax-wishlist-item').length === 0) {
                        location.reload();
                    }
                });
            }
        });
    });

    // Clear wishlist
    $('#axClearWishlist').on('click', function() {
        if (confirm('<?php echo esc_js( furniva_t( 'Are you sure you want to clear your wishlist?', 'Bạn có chắc muốn xóa tất cả?' ) ); ?>')) {
            $.ajax({
                url: '<?php echo admin_url( 'admin-ajax.php' ); ?>',
                type: 'POST',
                data: {
                    action: 'furniva_wishlist_clear',
                    security: wishlistNonce
                },
                success: function() {
                    location.reload();
                }
            });
        }
    });
});
</script>

<?php get_footer(); ?>
