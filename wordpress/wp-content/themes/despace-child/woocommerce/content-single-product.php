<?php
/**
 * DeSpace - Single Product Page (Editorial Design)
 * Premium layout matching the DeSpace design reference
 *
 * @global WC_Product $product
 */
if ( ! defined( 'ABSPATH' ) ) exit;

global $product;
if ( ! $product ) {
    $product = wc_get_product( get_the_ID() );
}

if ( ! $product || ! $product->exists() ) {
    wc_no_products_found();
    return;
}

$id       = $product->get_id();
$regular  = (float) $product->get_regular_price();
$sale     = (float) $product->get_sale_price();
$percent  = ( $regular && $sale ) ? round( ( ( $regular - $sale ) / $regular ) * 100 ) : 0;

// Get rating
$rating = (float) $product->get_average_rating();
$review_count = (int) $product->get_review_count();

// Get all gallery images
$main_image_id  = $product->get_image_id();
$gallery_ids    = $product->get_gallery_image_ids();
$all_images     = array();
if ( $main_image_id && wp_get_attachment_image_url( $main_image_id, 'full' ) ) {
    $all_images[] = $main_image_id;
}
foreach ( $gallery_ids as $gid ) {
    if ( wp_get_attachment_image_url( $gid, 'full' ) ) {
        $all_images[] = $gid;
    }
}

// Get attributes & custom meta for finish/color & badge
$finish_desc_meta = get_post_meta( $id, '_despace_finish_desc', true ) ?: get_post_meta( $id, '_axgnud_finish_desc', true );
$color_hex_meta   = get_post_meta( $id, '_despace_color_hex', true ) ?: get_post_meta( $id, '_axgnud_color_hex', true );
$custom_badge     = get_post_meta( $id, '_despace_badge', true ) ?: get_post_meta( $id, '_axgnud_badge', true );

$attributes = $product->get_attributes();
$swatches_data = array();

// 1. Check custom attribute 'Finish' or 'finish'
$finish_attr_val = $product->get_attribute( 'Finish' ) ?: $product->get_attribute( 'finish' );
if ( ! empty( $finish_attr_val ) ) {
    $finish_names = array_values( array_filter( array_map( 'trim', preg_split( '/\s*[,|]\s*/', $finish_attr_val ) ) ) );
    $hex_list     = ! empty( $color_hex_meta ) ? array_values( array_filter( array_map( 'trim', preg_split( '/\s*[,|]\s*/', $color_hex_meta ) ) ) ) : array();
    foreach ( $finish_names as $idx => $f_name ) {
        $hex = isset( $hex_list[ $idx ] ) ? $hex_list[ $idx ] : ( function_exists( 'furniva_color_hex' ) ? furniva_color_hex( $f_name ) : '#a89f91' );
        $swatches_data[] = array(
            'slug' => sanitize_title( $f_name ),
            'name' => $f_name,
            'hex'  => $hex,
            'desc' => $finish_desc_meta ?: sprintf( furniva_t( 'Premium %s finish.', 'Hoàn thiện màu %s cao cấp.' ), $f_name ),
        );
    }
} elseif ( isset( $attributes['pa_color'] ) ) {
    // 2. Fallback to pa_color taxonomy if present
    $color_terms = wc_get_product_terms( $id, 'pa_color', array( 'fields' => 'all' ) );
    if ( ! empty( $color_terms ) && ! is_wp_error( $color_terms ) ) {
        $hex_list = ! empty( $color_hex_meta ) ? array_map( 'trim', explode( ',', $color_hex_meta ) ) : array();
        foreach ( $color_terms as $idx => $term ) {
            $hex = isset( $hex_list[ $idx ] ) ? $hex_list[ $idx ] : ( function_exists( 'furniva_color_hex' ) ? furniva_color_hex( $term->name ) : '#a89f91' );
            $swatches_data[] = array(
                'slug' => $term->slug,
                'name' => $term->name,
                'hex'  => $hex,
                'desc' => $finish_desc_meta ?: sprintf( furniva_t( 'Premium %s finish.', 'Hoàn thiện màu %s cao cấp.' ), $term->name ),
            );
        }
    }
}
?>

<?php
do_action( 'woocommerce_before_single_product' );
?>

<div id="product-<?php echo esc_attr( $id ); ?>" <?php wc_product_class( 'ax-product-page', $product ); ?>>

    <!-- Breadcrumb -->
    <div class="ax-breadcrumb">
        <div class="ax-container">
            <?php woocommerce_breadcrumb( array(
                'delimiter' => '<span class="ax-bc-sep">/</span>',
                'wrap_before' => '<nav class="ax-bc-nav">',
                'wrap_after' => '</nav>'
            ) ); ?>
        </div>
    </div>

    <div class="ax-container">
        <div class="ax-product-grid">

            <!-- LEFT: Gallery -->
            <div class="ax-gallery-wrap">
                <?php if ( $percent ) : ?>
                    <span class="ax-badge-designer">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                        DESIGNER PICK
                    </span>
                <?php else : ?>
                    <span class="ax-badge-designer">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                        <?php echo esc_html( furniva_t( 'FEATURED', 'NỔI BẬT' ) ); ?>
                    </span>
                <?php endif; ?>

                <div class="ax-gallery-main" id="axGalleryMain">
                    <?php
                    if ( ! empty( $all_images ) ) :
                        foreach ( $all_images as $idx => $img_id ) :
                            $img_full = wp_get_attachment_image_url( $img_id, 'full' );
                            $img_alt = get_post_meta( $img_id, '_wp_attachment_image_alt', true );
                            if ( $idx === 0 ) : ?>
                                <img src="<?php echo esc_url( $img_full ); ?>" alt="<?php echo esc_attr( $img_alt ?: $product->get_name() ); ?>" class="ax-gallery-image active" data-index="<?php echo $idx; ?>" />
                            <?php else : ?>
                                <img src="<?php echo esc_url( $img_full ); ?>" alt="<?php echo esc_attr( $img_alt ?: $product->get_name() ); ?>" class="ax-gallery-image" data-index="<?php echo $idx; ?>" style="display:none;" />
                            <?php endif;
                        endforeach;
                    else :
                        ?>
                        <img src="<?php echo esc_url( wc_placeholder_img_src( 'full' ) ); ?>" alt="<?php echo esc_attr( $product->get_name() ); ?>" class="ax-gallery-image active" data-index="0" />
                    <?php endif;
                    ?>
                </div>

                <!-- Navigation arrows -->
                <?php if ( count( $all_images ) > 1 ) : ?>
                <button class="ax-gallery-nav ax-gallery-prev" id="axGalleryPrev" aria-label="Previous image">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="m15 18-6-6 6-6"/></svg>
                </button>
                <button class="ax-gallery-nav ax-gallery-next" id="axGalleryNext" aria-label="Next image">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="m9 18 6-6-6-6"/></svg>
                </button>
                <?php endif; ?>

                <!-- Zoom button -->
                <button class="ax-gallery-zoom" aria-label="Zoom image">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><line x1="11" y1="8" x2="11" y2="14"/><line x1="8" y1="11" x2="14" y2="11"/></svg>
                </button>

                <!-- Thumbnails -->
                <?php if ( count( $all_images ) > 1 ) : ?>
                <div class="ax-gallery-thumbs">
                    <?php foreach ( $all_images as $idx => $img_id ) :
                        $thumb_url = wp_get_attachment_image_url( $img_id, 'woocommerce_gallery_thumbnail' );
                        if ( ! $thumb_url ) continue;
                    ?>
                        <div class="ax-thumb-item <?php echo $idx === 0 ? 'active' : ''; ?>" data-index="<?php echo $idx; ?>">
                            <img src="<?php echo esc_url( $thumb_url ); ?>" alt="" loading="lazy" />
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- RIGHT: Product Info -->
            <div class="ax-info-wrap">

                <!-- Category & Badge -->
                <div class="ax-product-meta-top" style="display:flex; align-items:center; gap:10px; margin-bottom:8px;">
                    <span class="ax-product-cat">
                        <?php
                        $cats = get_the_terms( $id, 'product_cat' );
                        if ( $cats && ! is_wp_error( $cats ) ) {
                            echo esc_html( strtoupper( furniva_term_label( $cats[0]->name, 'product_cat' ) ) );
                        }
                        ?>
                    </span>
                    <?php if ( ! empty( $custom_badge ) ) :
                        $badge_slug  = sanitize_title( $custom_badge );
                        $badge_label = furniva_t( $custom_badge, ( 'Best Seller' === $custom_badge ? 'Bán chạy' : ( 'Featured' === $custom_badge ? 'Nổi bật' : ( 'New' === $custom_badge ? 'Mới' : ( 'Popular' === $custom_badge ? 'Phổ biến' : ( 'Sale' === $custom_badge ? 'Giảm giá' : $custom_badge ) ) ) ) ) );
                    ?>
                        <span class="f-badge-custom f-badge-<?php echo esc_attr( $badge_slug ); ?>"><?php echo esc_html( $badge_label ); ?></span>
                    <?php endif; ?>
                </div>

                <!-- Title -->
                <h1 class="ax-product-name"><?php echo esc_html( $product->get_name() ); ?></h1>

                <p class="ax-product-sku">
                    <span><?php echo esc_html( furniva_t( 'SKU:', 'Mã sản phẩm:' ) ); ?></span>
                    <span id="axProductSku" aria-live="polite"><?php echo esc_html( $product->get_sku() ?: '—' ); ?></span>
                </p>

                <!-- Rating -->
                <div class="ax-product-rating">
                    <div class="ax-stars">
                        <?php for ( $i = 1; $i <= 5; $i++ ) : ?>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="<?php echo $i <= round($rating) ? '#c5a880' : '#e5ddd2'; ?>">
                                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                            </svg>
                        <?php endfor; ?>
                    </div>
                    <span class="ax-rating-text">
                        <?php echo number_format($rating, 1); ?> (<?php echo $review_count; ?> <?php echo esc_html( furniva_t( 'reviews', 'đánh giá' ) ); ?>)
                    </span>
                    <span class="ax-write-review"><?php echo esc_html( furniva_t( 'Write a review', 'Viết đánh giá' ) ); ?></span>
                </div>

                <!-- Price -->
                <div class="ax-product-price" id="axProductPrice" aria-live="polite">
                    <?php echo wp_kses_post( $product->get_price_html() ); ?>
                </div>

                <!-- Short Description -->
                <div class="ax-product-desc">
                    <?php if ( $product->get_short_description() ) : ?>
                        <?php echo wp_kses_post( $product->get_short_description() ); ?>
                    <?php else : ?>
                        <?php echo esc_html( furniva_t( 'No short description is available for this product.', 'Sản phẩm chưa có mô tả ngắn.' ) ); ?>
                    <?php endif; ?>
                </div>

                <div class="ax-divider"></div>

                <!-- Single Product Finish Display (No dots swatches) -->
                <?php
                $single_finish = '';
                $color_terms = wc_get_product_terms( $id, 'pa_color', array( 'fields' => 'names' ) );
                if ( ! empty( $color_terms ) && ! is_wp_error( $color_terms ) ) {
                    $single_finish = furniva_term_label( $color_terms[0], 'pa_color' );
                }
                if ( $single_finish && ! $product->is_type( 'variable' ) ) : ?>
                <div class="ax-finish-badge-row" style="margin-bottom: 18px; font-size: 0.95rem; display: flex; align-items: center; gap: 8px;">
                    <span class="ax-var-label" style="font-size: 0.8rem; font-weight: 600; letter-spacing: 0.05em; color: #71717a; text-transform: uppercase;"><?php echo esc_html( furniva_t( 'Finish:', 'Màu hoàn thiện:' ) ); ?></span>
                    <strong class="ax-finish-val" style="color: #18181b; font-weight: 600;"><?php echo esc_html( $single_finish ); ?></strong>
                </div>
                <?php endif; ?>

                <!-- Availability -->
                <div class="ax-availability">
                    <strong><?php echo esc_html( furniva_t( 'Availability:', 'Tình trạng:' ) ); ?></strong>
                    <span class="ax-stock-status <?php echo $product->is_in_stock() ? 'is-in-stock' : 'is-out-of-stock'; ?>" id="axProductStock" data-default-in-stock="<?php echo $product->is_in_stock() ? '1' : '0'; ?>" aria-live="polite">
                        <span class="ax-stock-dot" aria-hidden="true"></span>
                        <span class="ax-stock-status-text">
                        <?php
                        if ( $product->is_in_stock() ) {
                            $stock = $product->get_stock_quantity();
                            $stock_text = null !== $stock && (float) $stock > 0 ? ' (' . sprintf( esc_html( furniva_t( '%s available', 'Còn %s sản phẩm' ) ), esc_html( wc_stock_amount( $stock ) ) ) . ')' : '';
                            echo '<span class="ax-stock-in">' . esc_html( furniva_t( 'In stock', 'Còn hàng' ) ) . '</span>' . $stock_text;
                        } else {
                            echo '<span class="ax-stock-out">' . esc_html( furniva_t( 'Out of stock', 'Hết hàng' ) ) . '</span>';
                        }
                        ?>
                        </span>
                    </span>
                </div>

                <!-- Add to Cart Form -->
                <div class="ax-product-form <?php echo $product->is_type( 'variable' ) ? 'is-variable' : 'is-simple'; ?>">
                    <?php woocommerce_template_single_add_to_cart(); ?>
                </div>
                <?php if ( $product->is_type( 'variable' ) ) : ?>
                    <p class="ax-variation-feedback" id="axVariationFeedback" role="status" aria-live="polite"></p>
                <?php endif; ?>

                <!-- 3D Product Viewer & Real AR Interactive Action Bar -->
                <?php
                if ( ! $product->is_virtual() ) :
                    $model_info = function_exists( 'ax_get_product_3d_info' ) ? ax_get_product_3d_info( $product->get_id() ) : array( 'has_3d' => false, 'glb_url' => '', 'usdz_url' => '', 'ar_enabled' => false );
                    $ax_3d_data = function_exists( 'axgnud_get_product_3d_data' ) ? axgnud_get_product_3d_data( $product->get_id() ) : array();
                    $cat_type   = $ax_3d_data['category_type'] ?? 'cabinet';
                    $def_color  = $ax_3d_data['default_color'] ?? '#5c3a21';
                    $dims       = $product->get_dimensions( false );
                    $dim_str    = '';
                    if ( ! empty( $dims['length'] ) && ! empty( $dims['width'] ) && ! empty( $dims['height'] ) ) {
                        $dim_str = "{$dims['length']} × {$dims['width']} × {$dims['height']} cm";
                    } elseif ( ! empty( $model_info['dimensions'] ) ) {
                        $dim_str = $model_info['dimensions'];
                    }
                    $image_url  = wp_get_attachment_image_url( $product->get_image_id(), 'large' );
                    $price_clean = wc_price( $product->get_price() );
                    $regular_price_clean = $product->is_on_sale() ? wc_price( $product->get_regular_price() ) : '';
                    $has_model = ! empty( $model_info['glb_url'] );
                    $has_ar    = $has_model && ! empty( $model_info['ar_enabled'] );

                    if ( $has_model ) :
                ?>
                <div class="ax-3d-ar-actions-group" style="display:flex; gap:10px; margin: 16px 0 20px; flex-wrap:wrap;">
                    <!-- Nút 1: Xem Mô hình 3D 360° -->
                    <button type="button" class="ar-product-btn ax-open-3d-btn" id="axView3DModelBtn"
                        data-product-id="<?php echo esc_attr( $product->get_id() ); ?>"
                        data-product-name="<?php echo esc_attr( $product->get_name() ); ?>"
                        data-product-price="<?php echo esc_attr( wp_strip_all_tags( $price_clean ) ); ?>"
                        data-product-regular-price="<?php echo esc_attr( wp_strip_all_tags( $regular_price_clean ) ); ?>"
                        data-product-raw-price="<?php echo esc_attr( $product->get_price() ); ?>"
                        data-product-image="<?php echo esc_url( $image_url ); ?>"
                        data-product-dimensions="<?php echo esc_attr( $dim_str ); ?>"
                        data-category-type="<?php echo esc_attr( $cat_type ); ?>"
                        data-default-color="<?php echo esc_attr( $def_color ); ?>"
                        data-has-3d="yes"
                        data-ar-enabled="<?php echo $has_ar ? 'yes' : 'no'; ?>"
                        data-glb-url="<?php echo esc_url( $model_info['glb_url'] ); ?>"
                        data-usdz-url="<?php echo esc_url( $model_info['usdz_url'] ); ?>"
                        data-initial-tab="3d"
                        style="flex: 1 1 200px; justify-content: center; background: #FAF7F2; color: #1C1917; border: 1.5px solid #D5C9BC;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                            <polyline points="3.27 6.96 12 12.01 20.73 6.96"/>
                            <line x1="12" y1="22.08" x2="12" y2="12"/>
                        </svg>
                        <span><?php echo esc_html( furniva_t( 'View 3D Model', 'Xem Mô Hình 3D' ) ); ?></span>
                    </button>

                    <?php if ( $has_ar ) : ?>
                    <!-- Nút 2: Xem Trong Phòng Bạn (AR Thực tế qua Camera) -->
                    <button type="button" class="ar-product-btn ax-open-ar-btn" id="arViewInRoomBtn"
                        data-product-id="<?php echo esc_attr( $product->get_id() ); ?>"
                        data-product-name="<?php echo esc_attr( $product->get_name() ); ?>"
                        data-product-price="<?php echo esc_attr( wp_strip_all_tags( $price_clean ) ); ?>"
                        data-product-regular-price="<?php echo esc_attr( wp_strip_all_tags( $regular_price_clean ) ); ?>"
                        data-product-raw-price="<?php echo esc_attr( $product->get_price() ); ?>"
                        data-product-image="<?php echo esc_url( $image_url ); ?>"
                        data-product-dimensions="<?php echo esc_attr( $dim_str ); ?>"
                        data-category-type="<?php echo esc_attr( $cat_type ); ?>"
                        data-default-color="<?php echo esc_attr( $def_color ); ?>"
                        data-has-3d="yes"
                        data-ar-enabled="yes"
                        data-glb-url="<?php echo esc_url( $model_info['glb_url'] ); ?>"
                        data-usdz-url="<?php echo esc_url( $model_info['usdz_url'] ); ?>"
                        data-initial-tab="ar"
                        style="flex: 1 1 200px; justify-content: center; background: #1C1917; color: #FAF7F2; border: 1.5px solid #1C1917;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
                            <circle cx="12" cy="13" r="4"/>
                        </svg>
                        <span><?php echo esc_html( furniva_t( 'View in Your Room (AR)', 'Xem Trong Phòng Bạn (AR)' ) ); ?></span>
                    </button>
                    <?php endif; ?>
                </div>
                <?php endif; // $has_model ?>
                <?php endif; // ! is_virtual ?>

                <!-- Buy Buttons -->
                <div class="ax-buy-actions">
                    <div class="ax-qty-cart">
                        <div class="ax-qty-stepper <?php echo ( ! $product->is_type( 'variable' ) && ( ! $product->is_purchasable() || ! $product->is_in_stock() ) ) ? 'is-disabled' : ''; ?>">
                            <button type="button" class="ax-qty-minus" aria-label="<?php echo esc_attr( furniva_t( 'Decrease quantity', 'Giảm số lượng' ) ); ?>" <?php disabled( ! $product->is_type( 'variable' ) && ( ! $product->is_purchasable() || ! $product->is_in_stock() ) ); ?>>−</button>
                            <input type="number" class="ax-qty-input" value="1" min="1" step="1" inputmode="numeric" aria-label="<?php echo esc_attr( furniva_t( 'Quantity', 'Số lượng' ) ); ?>" <?php disabled( ! $product->is_type( 'variable' ) && ( ! $product->is_purchasable() || ! $product->is_in_stock() ) ); ?> />
                            <button type="button" class="ax-qty-plus" aria-label="<?php echo esc_attr( furniva_t( 'Increase quantity', 'Tăng số lượng' ) ); ?>" <?php disabled( ! $product->is_type( 'variable' ) && ( ! $product->is_purchasable() || ! $product->is_in_stock() ) ); ?>>+</button>
                        </div>
                        <button type="button" class="ax-btn ax-btn-primary ax-btn-cart" id="axAddToCart" <?php disabled( ! $product->is_type( 'variable' ) && ( ! $product->is_purchasable() || ! $product->is_in_stock() ) ); ?>>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1.6"/><circle cx="19" cy="21" r="1.6"/><path d="M2.5 3h2l2.4 12.4a2 2 0 0 0 2 1.6h9.7a2 2 0 0 0 2-1.6l1.4-8H6"/></svg>
                            <span><?php echo esc_html( furniva_t( 'Add to cart', 'Thêm vào giỏ' ) ); ?></span>
                        </button>
                    </div>

                    <?php
                    $is_in_wl = false;
                    if ( function_exists( 'yith_wcwl_wishlists' ) ) {
                        $is_in_wl = yith_wcwl_wishlists()->is_product_in_wishlist( $id );
                    } elseif ( function_exists( 'YITH_WCWL' ) && method_exists( YITH_WCWL(), 'is_product_in_wishlist' ) ) {
                        $is_in_wl = YITH_WCWL()->is_product_in_wishlist( $id );
                    }
                    ?>
                    <button type="button" class="ax-btn ax-btn-wishlist <?php echo $is_in_wl ? 'is-added' : ''; ?>" id="axAddToWishlist" data-product-id="<?php echo esc_attr( $id ); ?>" aria-label="<?php echo esc_attr( furniva_t( 'Wishlist', 'Danh sách yêu thích' ) ); ?>">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="<?php echo $is_in_wl ? 'currentColor' : 'none'; ?>" stroke="currentColor" stroke-width="2"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21.2l7.8-7.8 1-1a5.5 5.5 0 0 0 0-7.8z"/></svg>
                        <span><?php echo esc_html( $is_in_wl ? furniva_t( 'Saved to wishlist ♥', 'Đã lưu yêu thích ♥' ) : furniva_t( 'Add to wishlist', 'Thêm yêu thích' ) ); ?></span>
                    </button>
                </div>

                <!-- Trust Features -->
                <div class="ax-trust-features">
                    <div class="ax-trust-item">
                        <div class="ax-trust-icon">
                            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>
                            </svg>
                        </div>
                        <div>
                            <strong><?php echo esc_html( furniva_t( 'Delivery options at checkout', 'Tùy chọn giao hàng tại thanh toán' ) ); ?></strong>
                            <span><?php echo esc_html( furniva_t( 'Shown for your address and order', 'Hiển thị theo địa chỉ và đơn hàng' ) ); ?></span>
                        </div>
                    </div>
                    <div class="ax-trust-item">
                        <div class="ax-trust-icon">
                            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/>
                            </svg>
                        </div>
                        <div>
                            <strong><?php echo esc_html( furniva_t( 'WooCommerce stock status', 'Tình trạng kho WooCommerce' ) ); ?></strong>
                            <span><?php echo esc_html( furniva_t( 'Updated for the selected option', 'Cập nhật theo lựa chọn sản phẩm' ) ); ?></span>
                        </div>
                    </div>
                    <div class="ax-trust-item">
                        <div class="ax-trust-icon">
                            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
                            </svg>
                        </div>
                        <div>
                            <strong><?php echo esc_html( furniva_t( 'Product information', 'Thông tin sản phẩm' ) ); ?></strong>
                            <span><?php echo esc_html( furniva_t( 'Managed in the store catalog', 'Quản lý trong danh mục cửa hàng' ) ); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Product Tabs (Description, Details, Shipping) -->
        <div class="ax-product-tabs">
            <div class="ax-tabs-nav" role="tablist" aria-label="<?php echo esc_attr( furniva_t( 'Product information tabs', 'Các tab thông tin sản phẩm' ) ); ?>">
                <button type="button" class="ax-tab-btn active" role="tab" id="ax-tab-btn-description" aria-selected="true" aria-controls="ax-tab-description" data-tab="description"><?php echo esc_html( furniva_t( 'Description', 'Mô tả' ) ); ?></button>
                <button type="button" class="ax-tab-btn" role="tab" id="ax-tab-btn-details" aria-selected="false" aria-controls="ax-tab-details" data-tab="details"><?php echo esc_html( furniva_t( 'Details', 'Chi tiết' ) ); ?></button>
                <button type="button" class="ax-tab-btn" role="tab" id="ax-tab-btn-shipping" aria-selected="false" aria-controls="ax-tab-shipping" data-tab="shipping"><?php echo esc_html( furniva_t( 'Shipping & Returns', 'Vận chuyển & Đổi trả' ) ); ?></button>
                <button type="button" class="ax-tab-btn" role="tab" id="ax-tab-btn-reviews" aria-selected="false" aria-controls="ax-tab-reviews" data-tab="reviews"><?php echo esc_html( furniva_t( 'Reviews', 'Đánh giá' ) ); ?></button>
            </div>

            <div class="ax-tabs-content">
                <div class="ax-tab-panel active" role="tabpanel" id="ax-tab-description" aria-labelledby="ax-tab-btn-description">
                    <div class="ax-tab-inner">
                        <div class="ax-tab-text">
                            <?php if ( $product->get_description() ) : ?>
                                <?php echo wp_kses_post( apply_filters( 'the_content', $product->get_description() ) ); ?>
                            <?php else : ?>
                                <p><?php echo esc_html( furniva_t( 'Product information is being updated.', 'Thông tin chi tiết sản phẩm đang được cập nhật.' ) ); ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="ax-tab-image">
                            <?php if ( ! empty( $all_images[1] ) ) :
                                $img2 = wp_get_attachment_image_url( $all_images[1], 'large' );
                            ?>
                                <img src="<?php echo esc_url( $img2 ); ?>" alt="" loading="lazy" />
                            <?php elseif ( ! empty( $all_images[0] ) ) :
                                $img1 = wp_get_attachment_image_url( $all_images[0], 'large' );
                            ?>
                                <img src="<?php echo esc_url( $img1 ); ?>" alt="" loading="lazy" />
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="ax-tab-panel" role="tabpanel" id="ax-tab-details" aria-labelledby="ax-tab-btn-details" hidden>
                    <div class="ax-tab-inner">
                        <?php $care_instructions = get_post_meta( $id, '_despace_care_instructions', true ); ?>
                        <?php if ( $care_instructions ) : ?>
                            <section class="ax-product-care">
                                <h3><?php echo esc_html( furniva_t( 'Care instructions', 'Hướng dẫn bảo quản' ) ); ?></h3>
                                <?php echo wp_kses_post( wpautop( $care_instructions ) ); ?>
                            </section>
                        <?php endif; ?>
                        <?php woocommerce_product_additional_information_tab(); ?>
                    </div>
                </div>

                <div class="ax-tab-panel" role="tabpanel" id="ax-tab-shipping" aria-labelledby="ax-tab-btn-shipping" hidden>
                    <div class="ax-tab-inner">
                        <h3><?php echo esc_html( furniva_t( 'Shipping Information', 'Thông tin vận chuyển' ) ); ?></h3>
                        <p><?php echo esc_html( furniva_t( 'Available delivery methods and fees are shown during checkout based on your address and order.', 'Phương thức và phí giao hàng sẽ được hiển thị tại bước thanh toán theo địa chỉ và đơn hàng của bạn.' ) ); ?></p>
                        <h3><?php echo esc_html( furniva_t( 'Return Policy', 'Chính sách đổi trả' ) ); ?></h3>
                        <?php $terms_url = wc_get_page_permalink( 'terms' ); ?>
                        <p><?php echo esc_html( furniva_t( 'Please review the store terms for the applicable return and exchange conditions.', 'Vui lòng xem điều khoản của cửa hàng để biết điều kiện đổi trả áp dụng.' ) ); ?>
                            <?php if ( $terms_url ) : ?> <a href="<?php echo esc_url( $terms_url ); ?>"><?php echo esc_html( furniva_t( 'View store terms', 'Xem điều khoản cửa hàng' ) ); ?></a><?php endif; ?>
                        </p>
                    </div>
                </div>

                <div class="ax-tab-panel" role="tabpanel" id="ax-tab-reviews" aria-labelledby="ax-tab-btn-reviews" hidden>
                    <div class="ax-tab-inner">
                        <?php comments_template(); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Related Products -->
        <?php if ( wc_get_related_products( $id, 4 ) ) : ?>
        <div class="ax-related-section">
            <h2 class="ax-related-title"><?php echo esc_html( furniva_t( 'You May Also Like', 'Có thể bạn cũng thích' ) ); ?></h2>
            <?php
            add_filter( 'woocommerce_output_related_products_args', function ( $args ) {
                $args['posts_per_page'] = 4;
                $args['columns']        = 4;
                return $args;
            }, 20 );
            woocommerce_output_related_products();
            ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Image Lightbox Modal -->
<div id="axGalleryLightbox" class="ax-gallery-lightbox" style="display:none;" role="dialog" aria-modal="true" aria-label="<?php echo esc_attr( furniva_t( 'Enlarged product image', 'Phóng to ảnh sản phẩm' ) ); ?>">
    <div class="ax-lightbox-backdrop"></div>
    <div class="ax-lightbox-content">
        <button type="button" class="ax-lightbox-close" aria-label="<?php echo esc_attr( furniva_t( 'Close', 'Đóng' ) ); ?>">&times;</button>
        <img src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7" alt="" class="ax-lightbox-img" />
    </div>
</div>

<script>
jQuery(function($) {
    // Gallery navigation
    var currentImageIndex = 0;
    var $images = $('.ax-gallery-image');
    var $thumbs = $('.ax-thumb-item');
    var totalImages = $images.length;

    function showImage(index) {
        if (index < 0 || index >= totalImages) return;
        $images.hide().removeClass('active');
        $images.eq(index).show().addClass('active');
        $thumbs.removeClass('active');
        $thumbs.eq(index).addClass('active');
        currentImageIndex = index;
    }

    $('#axGalleryPrev').on('click', function() {
        var newIndex = currentImageIndex > 0 ? currentImageIndex - 1 : totalImages - 1;
        showImage(newIndex);
    });

    $('#axGalleryNext').on('click', function() {
        var newIndex = currentImageIndex < totalImages - 1 ? currentImageIndex + 1 : 0;
        showImage(newIndex);
    });

    $thumbs.on('click', function() {
        var idx = $(this).data('index');
        showImage(idx);
    });

    // Zoom Lightbox
    $('.ax-gallery-zoom').on('click', function() {
        var $activeImg = $('#axGalleryMain .ax-gallery-image.active');
        if ($activeImg.length) {
            var src = $activeImg.attr('src');
            var alt = $activeImg.attr('alt') || '';
            $('#axGalleryLightbox .ax-lightbox-img').attr('src', src).attr('alt', alt);
            $('#axGalleryLightbox').fadeIn(200).attr('aria-hidden', 'false');
            $('body').css('overflow', 'hidden');
        }
    });

    $(document).on('click', '#axGalleryLightbox .ax-lightbox-close, #axGalleryLightbox .ax-lightbox-backdrop', function() {
        $('#axGalleryLightbox').fadeOut(180).attr('aria-hidden', 'true');
        $('body').css('overflow', '');
    });

    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' && $('#axGalleryLightbox').is(':visible')) {
            $('#axGalleryLightbox').fadeOut(180).attr('aria-hidden', 'true');
            $('body').css('overflow', '');
        }
    });

    // Quantity stepper
    var $quantity = $('.ax-qty-input');
    var $nativeQuantity = $('.ax-product-form form.cart input.qty').first();
    function syncQuantity() {
        var min = parseInt($quantity.attr('min'), 10) || 1;
        var max = parseInt($quantity.attr('max'), 10);
        var rawVal = $quantity.val();
        var value = parseInt(rawVal, 10);
        if (!Number.isFinite(value) || value < min) value = min;
        if (Number.isFinite(max) && max >= min) value = Math.min(value, max);
        $quantity.val(value);
        if ($nativeQuantity.length) $nativeQuantity.val(value).trigger('change');
        return value;
    }
    if ($nativeQuantity.length) {
        var nativeMin = parseInt($nativeQuantity.attr('min'), 10);
        var nativeMax = parseInt($nativeQuantity.attr('max'), 10);
        if (Number.isFinite(nativeMin)) $quantity.attr('min', nativeMin);
        if (Number.isFinite(nativeMax) && nativeMax > 0) $quantity.attr('max', nativeMax);
    }
    $quantity.on('input change blur', syncQuantity);

    $('.ax-qty-minus').on('click', function() {
        if ($(this).prop('disabled')) return;
        var val = syncQuantity();
        var min = parseInt($quantity.attr('min'), 10) || 1;
        $quantity.val(Math.max(min, val - 1)).trigger('change');
    });

    $('.ax-qty-plus').on('click', function() {
        if ($(this).prop('disabled')) return;
        var val = syncQuantity();
        var max = parseInt($quantity.attr('max'), 10);
        var nextVal = val + 1;
        if (Number.isFinite(max) && max > 0) nextVal = Math.min(max, nextVal);
        $quantity.val(nextVal).trigger('change');
    });

    // Color swatches
    $('.ax-color-swatch').on('click', function() {
        $('.ax-color-swatch').removeClass('active');
        $(this).addClass('active');
        var color = $(this).data('color');
        var name = $(this).attr('title');
        var desc = $(this).data('desc');
        $('#axSelectedColorName').text(name);
        if (desc) {
            $('#axSelectedColorDesc').text(desc);
        }
        // Apply to variation select
        var termSlug = $(this).data('term-slug');
        var $select = $('select[name="attribute_pa_color"]');
        if ($select.length) {
            $select.val(termSlug).trigger('change');
        }
    });

    // Keep the editorial product summary in sync with WooCommerce variation data.
    var $variationForm = $('.ax-product-form form.variations_form');
    var $customCartButton = $('#axAddToCart');
    var $price = $('#axProductPrice');
    var $sku = $('#axProductSku');
    var $stock = $('#axProductStock .ax-stock-status-text');
    var defaultPrice = $price.html();
    var defaultSku = $sku.text();
    var defaultStock = $stock.html();
    var $mainImage = $('#axGalleryMain .ax-gallery-image').first();
    var originalImage = $mainImage.length ? {
        src: $mainImage.attr('src'),
        srcset: $mainImage.attr('srcset') || '',
        sizes: $mainImage.attr('sizes') || '',
        alt: $mainImage.attr('alt') || ''
    } : null;

    function syncCustomCartState() {
        if (!$variationForm.length) {
            var $nativeButton = $('.ax-product-form .single_add_to_cart_button').first();
            var parentInStock = $('#axProductStock').data('default-in-stock') == 1 || $('#axProductStock').data('default-in-stock') === '1';
            var nativeUnavailable = $nativeButton.is(':disabled') || $nativeButton.hasClass('disabled') || !parentInStock;
            $customCartButton.prop('disabled', nativeUnavailable).attr('aria-disabled', nativeUnavailable ? 'true' : 'false');
            return;
        }
        var variationId = $variationForm.find('input[name="variation_id"]').val();
        var needsSelection = !variationId || variationId === '0';
        if (needsSelection) {
            var parentUnavailable = $('#axProductStock').data('default-in-stock') !== 1 && $('#axProductStock').data('default-in-stock') !== '1';
            $customCartButton.prop('disabled', parentUnavailable).attr('aria-disabled', parentUnavailable ? 'true' : 'false');
        }
    }

    $variationForm.on('hide_variation reset_data', syncCustomCartState);
    $variationForm.find('.variations select').on('change', function() {
        $('#axVariationFeedback').empty();
        syncCustomCartState();
    });
    syncCustomCartState();

    $variationForm.on('found_variation', function(event, variation) {
        if (variation.price_html) $price.html(variation.price_html);
        $sku.text(variation.sku || '—');
        var stockText = variation.is_in_stock ? '<?php echo esc_js( furniva_t( 'In stock', 'Còn hàng' ) ); ?>' : '<?php echo esc_js( furniva_t( 'Out of stock', 'Hết hàng' ) ); ?>';
        if (variation.availability_html) stockText = $('<div>').html(variation.availability_html).text().trim() || stockText;
        $stock.text(stockText);
        $('#axProductStock').toggleClass('is-out-of-stock', !variation.is_in_stock).toggleClass('is-in-stock', !!variation.is_in_stock);

        if (Number.isFinite(parseInt(variation.max_qty, 10)) && parseInt(variation.max_qty, 10) > 0) {
            $quantity.attr('max', variation.max_qty);
        } else {
            $quantity.removeAttr('max');
        }
        if (Number.isFinite(parseInt(variation.min_qty, 10)) && parseInt(variation.min_qty, 10) > 0) {
            $quantity.attr('min', variation.min_qty);
        } else {
            $quantity.attr('min', 1);
        }
        syncQuantity();

        if (variation.is_in_stock) {
            $quantity.prop('disabled', false);
            $('.ax-qty-minus, .ax-qty-plus').prop('disabled', false);
            $('.ax-qty-stepper').removeClass('is-disabled');
            $customCartButton.prop('disabled', false).attr('aria-disabled', 'false');
            $('#axVariationFeedback').empty();
        } else {
            $quantity.prop('disabled', true);
            $('.ax-qty-minus, .ax-qty-plus').prop('disabled', true);
            $('.ax-qty-stepper').addClass('is-disabled');
            $customCartButton.prop('disabled', true).attr('aria-disabled', 'true');
            $('#axVariationFeedback').text('<?php echo esc_js( furniva_t( 'The selected option is out of stock.', 'Tùy chọn đã chọn hiện đang hết hàng.' ) ); ?>');
        }

        if (variation.image && variation.image.src && $mainImage.length) {
            $mainImage.attr({
                src: variation.image.src,
                srcset: variation.image.srcset || '',
                sizes: variation.image.sizes || '',
                alt: variation.image.alt || '<?php echo esc_js( $product->get_name() ); ?>'
            });
            showImage(0);
        }
    });

    $variationForm.on('reset_data hide_variation', function() {
        $price.html(defaultPrice);
        $sku.text(defaultSku || '—');
        $stock.html(defaultStock);
        var parentInStock = $('#axProductStock').data('default-in-stock') == 1 || $('#axProductStock').data('default-in-stock') === '1';
        $('#axProductStock').toggleClass('is-out-of-stock', !parentInStock).toggleClass('is-in-stock', parentInStock);
        $quantity.removeAttr('max').attr('min', 1).val(1);
        $quantity.prop('disabled', !parentInStock);
        $('.ax-qty-minus, .ax-qty-plus').prop('disabled', !parentInStock);
        $('.ax-qty-stepper').toggleClass('is-disabled', !parentInStock);
        if (originalImage && $mainImage.length) {
            $mainImage.attr(originalImage);
            showImage(0);
        }
        $('#axVariationFeedback').empty();
        syncCustomCartState();
    });

    // Add to cart button
    $('#axAddToCart').on('click', function(e) {
        e.preventDefault();
        var qty = syncQuantity();
        var $form = $('.ax-product-form form.cart');
        if (!$form.length) return;

        var isVariable = $form.hasClass('variations_form');
        if (isVariable) {
            var variationId = parseInt($form.find('input[name="variation_id"]').val(), 10);
            var $nativeButton = $form.find('button.single_add_to_cart_button');

            if (!variationId || variationId <= 0) {
                var $unfilled = $form.find('.variations select').filter(function() { return !$(this).val(); }).first();
                if ($unfilled.length) $unfilled.trigger('focus');
                $('#axVariationFeedback').text('<?php echo esc_js( furniva_t( 'Choose all available product options before adding this item to your cart.', 'Vui lòng chọn đầy đủ các thuộc tính sản phẩm trước khi thêm vào giỏ hàng.' ) ); ?>');
                return;
            }

            if ($nativeButton.is(':disabled') || $nativeButton.hasClass('disabled')) {
                $('#axVariationFeedback').text('<?php echo esc_js( furniva_t( 'The selected option is out of stock.', 'Tùy chọn đã chọn hiện đang hết hàng.' ) ); ?>');
                return;
            }

            $('#axVariationFeedback').empty();
            $form.find('input[name="quantity"]').val(qty).trigger('change');
            if (!$form.find('input[name="add-to-cart"]').length) {
                $('<input>').attr({type: 'hidden', name: 'add-to-cart', value: '<?php echo esc_js( $id ); ?>'}).appendTo($form);
            }
            if ($nativeButton.length && $form[0].requestSubmit) {
                $form[0].requestSubmit($nativeButton[0]);
            } else if ($nativeButton.length) {
                $nativeButton.trigger('click');
            } else {
                $form.trigger('submit');
            }
        } else {
            // Simple product
            var $nativeButton = $form.find('button.single_add_to_cart_button');
            var parentInStock = $('#axProductStock').data('default-in-stock') == 1 || $('#axProductStock').data('default-in-stock') === '1';
            if ($nativeButton.is(':disabled') || $nativeButton.hasClass('disabled') || !parentInStock) {
                $('#axVariationFeedback').text('<?php echo esc_js( furniva_t( 'This product is currently out of stock.', 'Sản phẩm hiện đang hết hàng.' ) ); ?>');
                return;
            }
            $('#axVariationFeedback').empty();
            $form.find('input[name="quantity"]').val(qty).trigger('change');
            if (!$form.find('input[name="add-to-cart"]').length) {
                $('<input>').attr({type: 'hidden', name: 'add-to-cart', value: '<?php echo esc_js( $id ); ?>'}).appendTo($form);
            }
            if ($nativeButton.length && $form[0].requestSubmit) {
                $form[0].requestSubmit($nativeButton[0]);
            } else if ($nativeButton.length) {
                $nativeButton.trigger('click');
            } else {
                $form.trigger('submit');
            }
        }
    });

    // Tabs
    $('.ax-tab-btn').on('click', function() {
        var tab = $(this).data('tab');
        $('.ax-tab-btn').removeClass('active').attr('aria-selected', 'false');
        $(this).addClass('active').attr('aria-selected', 'true');
        $('.ax-tab-panel').removeClass('active').attr('hidden', true);
        $('#ax-tab-' + tab).addClass('active').removeAttr('hidden');
    });

    // Keyboard navigation for tabs
    $('.ax-tabs-nav').on('keydown', '.ax-tab-btn', function(e) {
        var $tabs = $('.ax-tab-btn');
        var index = $tabs.index(this);
        var targetIndex = -1;

        if (e.key === 'ArrowRight' || e.key === 'ArrowDown') {
            targetIndex = (index + 1) % $tabs.length;
        } else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') {
            targetIndex = (index - 1 + $tabs.length) % $tabs.length;
        } else if (e.key === 'Home') {
            targetIndex = 0;
        } else if (e.key === 'End') {
            targetIndex = $tabs.length - 1;
        }

        if (targetIndex !== -1) {
            e.preventDefault();
            $tabs.eq(targetIndex).trigger('focus').trigger('click');
        }
    });

    // Click rating / write review to jump to reviews tab
    $('.ax-product-rating, .ax-write-review').css('cursor', 'pointer').on('click', function(e) {
        e.preventDefault();
        $('.ax-tab-btn[data-tab="reviews"]').trigger('click');
        var $target = $('#ax-tab-reviews');
        if ($target.length) {
            $('html, body').animate({
                scrollTop: $target.offset().top - 110
            }, 400);
        }
    });
});
</script>

<?php do_action( 'woocommerce_after_single_product' ); ?>
