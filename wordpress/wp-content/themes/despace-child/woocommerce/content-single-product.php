<?php
/**
 * DeSpace - Single Product Page (Editorial Design)
 * Premium layout matching the DeSpace design reference
 *
 * @global WC_Product $product
 */
if (!defined('ABSPATH'))
    exit;

global $product;
if (!$product) {
    $product = wc_get_product(get_the_ID());
}
if (!$product) {
    return;
}
if (post_password_required()) {
    echo get_the_password_form(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    return;
}

if (!$product || !$product->exists()) {
    wc_no_products_found();
    return;
}

$id = $product->get_id();
$regular = (float) $product->get_regular_price();
$sale = (float) $product->get_sale_price();
$percent = ($regular && $sale) ? round((($regular - $sale) / $regular) * 100) : 0;

// Get rating
$rating = (float) $product->get_average_rating();
$review_count = (int) $product->get_review_count();

// Get all gallery images
$main_image_id = $product->get_image_id();
$gallery_ids = $product->get_gallery_image_ids();
$all_images = array();
if ($main_image_id && wp_get_attachment_image_url($main_image_id, 'full')) {
    $all_images[] = $main_image_id;
}
foreach ($gallery_ids as $gid) {
    if (wp_get_attachment_image_url($gid, 'full')) {
        $all_images[] = $gid;
    }
}

// Get attributes & custom meta for finish/color & badge
$finish_desc_meta = get_post_meta($id, '_despace_finish_desc', true) ?: get_post_meta($id, '_axgnud_finish_desc', true);
$color_hex_meta = get_post_meta($id, '_despace_color_hex', true) ?: get_post_meta($id, '_axgnud_color_hex', true);
$custom_badge = get_post_meta($id, '_despace_badge', true) ?: get_post_meta($id, '_axgnud_badge', true);

// Pre-compute 3D/AR & dimensions so both Gallery Mode Switcher and Spec Accordion stay 100% synced
$pdp_model_info = (!$product->is_virtual() && function_exists('ax_get_product_3d_info')) ? ax_get_product_3d_info($id) : array('has_3d' => false, 'glb_url' => '', 'usdz_url' => '', 'ar_enabled' => false);
$pdp_has_3d = !empty($pdp_model_info['glb_url']);
$pdp_has_ar = $pdp_has_3d && !empty($pdp_model_info['ar_enabled']);
$pdp_raw_dims = $product->get_dimensions(false);
$pdp_dim_str = '';
if (!empty($pdp_raw_dims['length']) && !empty($pdp_raw_dims['width']) && !empty($pdp_raw_dims['height'])) {
    $pdp_dim_str = furniva_t(
        sprintf('%s × %s × %s cm (W × D × H)', $pdp_raw_dims['length'], $pdp_raw_dims['width'], $pdp_raw_dims['height']),
        sprintf('%s × %s × %s cm (Rộng × Sâu × Cao)', $pdp_raw_dims['length'], $pdp_raw_dims['width'], $pdp_raw_dims['height'])
    );
} elseif (!empty($pdp_model_info['dimensions'])) {
    $pdp_dim_str = $pdp_model_info['dimensions'];
}
$pdp_seasonal = function_exists('despace_get_active_seasonal_config') ? despace_get_active_seasonal_config() : null;
$pdp_verified_coupon = function_exists('despace_get_verified_coupon') ? despace_get_verified_coupon(is_array($pdp_seasonal) && !empty($pdp_seasonal['promo_code']) ? (string) $pdp_seasonal['promo_code'] : 'DESPACE10') : null;
$pdp_single_finish = '';
$pdp_color_terms = wc_get_product_terms($id, 'pa_color', array('fields' => 'names'));
if (!empty($pdp_color_terms) && !is_wp_error($pdp_color_terms)) {
    $pdp_single_finish = furniva_term_label($pdp_color_terms[0], 'pa_color');
}
?>

<div id="product-<?php echo esc_attr($id); ?>" <?php wc_product_class('ax-product-page', $product); ?>>
    <div class="ax-container">
        <?php do_action('woocommerce_before_single_product'); ?>
    </div>

    <!-- Breadcrumb -->
    <div class="ax-breadcrumb">
        <div class="ax-container">
            <?php woocommerce_breadcrumb(array(
                'delimiter' => '<span class="ax-bc-sep">/</span>',
                'wrap_before' => '<nav class="ax-bc-nav">',
                'wrap_after' => '</nav>'
            )); ?>
        </div>
    </div>

    <div class="ax-container">
        <div class="ax-product-grid">

            <!-- LEFT: Gallery -->
            <div class="ax-gallery-wrap">
                <?php if ($percent > 0): ?>
                    <span class="ax-badge-designer ax-badge-designer--sale">
                        -<?php echo esc_html($percent); ?>% ·
                        <?php echo esc_html(furniva_t('SPECIAL OFFER', 'ƯU ĐÃI')); ?>
                    </span>
                <?php else: ?>
                    <span class="ax-badge-designer">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path
                                d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
                        </svg>
                        <?php echo esc_html(furniva_t('CURATED DESIGN', 'TUYỂN CHỌN')); ?>
                    </span>
                <?php endif; ?>

                <div class="ax-gallery-main" id="axGalleryMain">
                    <?php
                    if (!empty($all_images)):
                        foreach ($all_images as $idx => $img_id):
                            $img_full = wp_get_attachment_image_url($img_id, 'full');
                            $img_alt = get_post_meta($img_id, '_wp_attachment_image_alt', true);
                            if ($idx === 0): ?>
                                <img src="<?php echo esc_url($img_full); ?>"
                                    alt="<?php echo esc_attr($img_alt ?: $product->get_name()); ?>" class="ax-gallery-image active"
                                    data-index="<?php echo $idx; ?>" />
                            <?php else: ?>
                                <img src="<?php echo esc_url($img_full); ?>"
                                    alt="<?php echo esc_attr($img_alt ?: $product->get_name()); ?>" class="ax-gallery-image"
                                    data-index="<?php echo $idx; ?>" style="display:none;" />
                            <?php endif;
                        endforeach;
                    else:
                        ?>
                        <img src="<?php echo esc_url(wc_placeholder_img_src('full')); ?>"
                            alt="<?php echo esc_attr($product->get_name()); ?>" class="ax-gallery-image active"
                            data-index="0" />
                    <?php endif;
                    ?>

                    <!-- Navigation arrows -->
                    <?php if (count($all_images) > 1): ?>
                        <button type="button" class="ax-gallery-nav ax-gallery-prev" id="axGalleryPrev"
                            aria-label="<?php echo esc_attr(furniva_t('Previous image', 'Ảnh trước')); ?>">
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round" aria-hidden="true">
                                <path d="m15 18-6-6 6-6" />
                            </svg>
                        </button>
                        <button type="button" class="ax-gallery-nav ax-gallery-next" id="axGalleryNext"
                            aria-label="<?php echo esc_attr(furniva_t('Next image', 'Ảnh tiếp theo')); ?>">
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round" aria-hidden="true">
                                <path d="m9 18 6-6-6-6" />
                            </svg>
                        </button>
                    <?php endif; ?>

                    <!-- Gallery Counter Pill -->
                    <?php if (count($all_images) > 1): ?>
                        <span class="ax-gallery-counter" id="axGalleryCounter" aria-live="polite">1 / <?php echo esc_html((string) count($all_images)); ?></span>
                    <?php endif; ?>

                    <!-- Zoom button -->
                    <button type="button" class="ax-gallery-zoom"
                        aria-label="<?php echo esc_attr(furniva_t('Zoom image', 'Phóng to ảnh')); ?>">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor"
                            stroke-width="2" stroke-linecap="round" aria-hidden="true">
                            <circle cx="11" cy="11" r="8" />
                            <line x1="21" y1="21" x2="16.65" y2="16.65" />
                            <line x1="11" y1="8" x2="11" y2="14" />
                            <line x1="8" y1="11" x2="14" y2="11" />
                        </svg>
                    </button>
                </div>
                <!-- Gallery Mode Switcher: 2D Gallery <-> 3D Interactive <-> AR in Room -->
                <?php if ($pdp_has_3d): ?>
                    <div class="ax-stage-switcher" role="group"
                        aria-label="<?php echo esc_attr(furniva_t('Product view mode', 'Chế độ xem sản phẩm')); ?>">
                        <button type="button" class="ax-stage-pill is-active" data-stage-mode="2d">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2" aria-hidden="true">
                                <rect x="3" y="3" width="18" height="18" rx="2" />
                                <circle cx="8.5" cy="8.5" r="1.5" />
                                <polyline points="21 15 16 10 5 21" />
                            </svg>
                            <span><?php echo esc_html(furniva_t('2D Gallery', 'Thư viện ảnh 2D')); ?></span>
                        </button>
                        <button type="button" class="ax-stage-pill" data-stage-mode="3d"
                            onclick="document.getElementById('axView3DModelBtn') && document.getElementById('axView3DModelBtn').click();">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2" aria-hidden="true">
                                <path
                                    d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" />
                                <polyline points="3.27 6.96 12 12.01 20.73 6.96" />
                                <line x1="12" y1="22.08" x2="12" y2="12" />
                            </svg>
                            <span><?php echo esc_html(furniva_t('3D Space 360°', 'Không gian 3D')); ?></span>
                        </button>
                        <?php if ($pdp_has_ar): ?>
                            <button type="button" class="ax-stage-pill ax-stage-pill--ar" data-stage-mode="ar"
                                onclick="document.getElementById('arViewInRoomBtn') && document.getElementById('arViewInRoomBtn').click();">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2" aria-hidden="true">
                                    <path
                                        d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z" />
                                    <circle cx="12" cy="13" r="4" />
                                </svg>
                                <span><?php echo esc_html(furniva_t('View AR in Room', 'Xem AR trong phòng')); ?></span>
                            </button>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- Thumbnails -->
                <?php if (count($all_images) > 1): ?>
                    <div class="ax-gallery-thumbs" role="group"
                        aria-label="<?php echo esc_attr(furniva_t('Product thumbnails', 'Ảnh thu nhỏ sản phẩm')); ?>">
                        <?php foreach ($all_images as $idx => $img_id):
                            $thumb_url = wp_get_attachment_image_url($img_id, 'woocommerce_gallery_thumbnail');
                            if (!$thumb_url)
                                continue;
                            ?>
                            <button type="button" class="ax-thumb-item <?php echo $idx === 0 ? 'active' : ''; ?>"
                                data-index="<?php echo esc_attr($idx); ?>"
                                aria-pressed="<?php echo $idx === 0 ? 'true' : 'false'; ?>"
                                aria-label="<?php echo esc_attr(sprintf(furniva_t('View image %d', 'Xem ảnh %d'), $idx + 1)); ?>">
                                <img src="<?php echo esc_url($thumb_url); ?>" alt="" loading="lazy" />
                            </button>
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
                        $cats = get_the_terms($id, 'product_cat');
                        if ($cats && !is_wp_error($cats)) {
                            echo esc_html(strtoupper(furniva_term_label($cats[0]->name, 'product_cat')));
                        }
                        ?>
                    </span>
                    <?php if (!empty($custom_badge)):
                        $badge_slug = sanitize_title($custom_badge);
                        $badge_label = furniva_t($custom_badge, ('Best Seller' === $custom_badge ? 'Bán chạy' : ('Featured' === $custom_badge ? 'Nổi bật' : ('New' === $custom_badge ? 'Mới' : ('Popular' === $custom_badge ? 'Phổ biến' : ('Sale' === $custom_badge ? 'Giảm giá' : $custom_badge))))));
                        ?>
                        <span
                            class="f-badge-custom f-badge-<?php echo esc_attr($badge_slug); ?>"><?php echo esc_html($badge_label); ?></span>
                    <?php endif; ?>
                </div>

                <!-- Title -->
                <h1 class="ax-product-name"><?php echo esc_html($product->get_name()); ?></h1>

                <div class="ax-pdp-meta-ribbon">
                    <p class="ax-product-sku">
                        <span><?php echo esc_html(furniva_t('SKU:', 'Mã SP:')); ?></span>
                        <span id="axProductSku"
                            aria-live="polite"><?php echo esc_html($product->get_sku() ?: '—'); ?></span>
                    </p>

                    <!-- Rating -->
                    <div class="ax-product-rating">
                        <div class="ax-stars">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <svg width="15" height="15" viewBox="0 0 24 24"
                                    fill="<?php echo $i <= round($rating) ? '#c5a880' : '#e5ddd2'; ?>" aria-hidden="true">
                                    <path
                                        d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
                                </svg>
                            <?php endfor; ?>
                        </div>
                        <span class="ax-rating-text">
                            <?php echo number_format($rating, 1); ?> (<?php echo $review_count; ?>
                            <?php echo esc_html(furniva_t('reviews', 'đánh giá')); ?>)
                        </span>
                        <span
                            class="ax-write-review"><?php echo esc_html(furniva_t('Write a review', 'Viết đánh giá')); ?></span>
                    </div>
                </div>

                <!-- Price & Verified Active Voucher Card -->
                <div class="ax-pdp-price-card">
                    <div class="ax-product-price" id="axProductPrice" aria-live="polite">
                        <?php echo wp_kses_post($product->get_price_html()); ?>
                        <?php if ($percent > 0): ?>
                            <span class="ax-pdp-save-pill"><?php echo esc_html(sprintf(furniva_t('Save %d%%', 'Tiết kiệm %d%%'), (int) $percent)); ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if ($pdp_verified_coupon): ?>
                        <div class="ax-pdp-voucher-hint">
                            <span>
                                <?php
                                echo wp_kses_post(
                                    furniva_is_vi()
                                        ? sprintf('Nhập mã <strong>%s</strong> (%s) tại Giỏ hàng', esc_html($pdp_verified_coupon['code']), esc_html($pdp_verified_coupon['label_vi']))
                                        : sprintf('Apply active code <strong>%s</strong> (%s) in Cart', esc_html($pdp_verified_coupon['code']), esc_html($pdp_verified_coupon['label_en']))
                                );
                                ?>
                            </span>
                            <button type="button" class="ax-pdp-voucher-hint__copy" data-copy-coupon="<?php echo esc_attr($pdp_verified_coupon['code']); ?>">
                                <?php echo esc_html(furniva_t('Copy', 'Sao chép')); ?>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Short Description -->
                <div class="ax-product-desc">
                    <?php if ($product->get_short_description()): ?>
                        <?php echo wp_kses_post($product->get_short_description()); ?>
                    <?php else: ?>
                        <?php echo esc_html(furniva_t('No short description is available for this product.', 'Sản phẩm chưa có mô tả ngắn.')); ?>
                    <?php endif; ?>
                </div>

                <!-- Quick Verified Physical Specifications Strip -->
                <?php
                $quick_dims = (!empty($pdp_raw_dims['length']) && !empty($pdp_raw_dims['width']) && !empty($pdp_raw_dims['height']))
                    ? sprintf('%s × %s × %s cm', $pdp_raw_dims['length'], $pdp_raw_dims['width'], $pdp_raw_dims['height'])
                    : '';
                $quick_weight = $product->get_weight() ? wc_format_weight($product->get_weight()) : '';
                if ($quick_dims || $pdp_single_finish || $quick_weight):
                ?>
                    <div class="ax-pdp-quick-specs" aria-label="<?php echo esc_attr(furniva_t('Key specifications', 'Thông số nổi bật')); ?>">
                        <?php if ($quick_dims): ?>
                            <div class="ax-pdp-quick-spec">
                                <span class="ax-pdp-quick-spec__lbl"><?php echo esc_html(furniva_t('Dimensions', 'Kích thước')); ?></span>
                                <span class="ax-pdp-quick-spec__val"><?php echo esc_html($quick_dims); ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if ($pdp_single_finish && !$product->is_type('variable')): ?>
                            <div class="ax-pdp-quick-spec">
                                <span class="ax-pdp-quick-spec__lbl"><?php echo esc_html(furniva_t('Finish', 'Hoàn thiện')); ?></span>
                                <span class="ax-pdp-quick-spec__val"><?php echo esc_html($pdp_single_finish); ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if ($quick_weight): ?>
                            <div class="ax-pdp-quick-spec">
                                <span class="ax-pdp-quick-spec__lbl"><?php echo esc_html(furniva_t('Weight', 'Trọng lượng')); ?></span>
                                <span class="ax-pdp-quick-spec__val"><?php echo esc_html($quick_weight); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="ax-divider"></div>

                <?php $single_finish = $pdp_single_finish; ?>

                <!-- Availability -->
                <div class="ax-availability">
                    <strong><?php echo esc_html(furniva_t('Availability:', 'Tình trạng:')); ?></strong>
                    <span
                        class="ax-stock-status <?php echo $product->is_in_stock() ? 'is-in-stock' : 'is-out-of-stock'; ?>"
                        id="axProductStock" data-default-in-stock="<?php echo $product->is_in_stock() ? '1' : '0'; ?>"
                        aria-live="polite">
                        <span class="ax-stock-dot" aria-hidden="true"></span>
                        <span class="ax-stock-status-text">
                            <?php
                            if ($product->is_in_stock()) {
                                $stock = $product->get_stock_quantity();
                                $stock_text = null !== $stock && (float) $stock > 0 ? ' (' . sprintf(esc_html(furniva_t('%s available', 'Còn %s sản phẩm')), esc_html(wc_stock_amount($stock))) . ')' : '';
                                echo '<span class="ax-stock-in">' . esc_html(furniva_t('In stock', 'Còn hàng')) . '</span>' . $stock_text;
                            } else {
                                echo '<span class="ax-stock-out">' . esc_html(furniva_t('Out of stock', 'Hết hàng')) . '</span>';
                            }
                            ?>
                        </span>
                    </span>
                </div>

                <!-- Add to Cart Form -->
                <div class="ax-product-form <?php echo $product->is_type('variable') ? 'is-variable' : 'is-simple'; ?>">
                    <?php woocommerce_template_single_add_to_cart(); ?>
                </div>
                <?php if ($product->is_type('variable')): ?>
                    <p class="ax-variation-feedback" id="axVariationFeedback" role="status" aria-live="polite"></p>
                <?php endif; ?>

                <!-- 3D Product Viewer & Real AR Interactive Action Bar -->
                <?php
                if (!$product->is_virtual()):
                    $model_info = function_exists('ax_get_product_3d_info') ? ax_get_product_3d_info($product->get_id()) : array('has_3d' => false, 'glb_url' => '', 'usdz_url' => '', 'ar_enabled' => false);
                    $ax_3d_data = function_exists('axgnud_get_product_3d_data') ? axgnud_get_product_3d_data($product->get_id()) : array();
                    $cat_type = $ax_3d_data['category_type'] ?? 'cabinet';
                    $def_color = $ax_3d_data['default_color'] ?? '#5c3a21';
                    $dims = $product->get_dimensions(false);
                    $dim_str = '';
                    if (!empty($dims['length']) && !empty($dims['width']) && !empty($dims['height'])) {
                        $dim_str = "{$dims['length']} × {$dims['width']} × {$dims['height']} cm";
                    } elseif (!empty($model_info['dimensions'])) {
                        $dim_str = $model_info['dimensions'];
                    }
                    $image_url = wp_get_attachment_image_url($product->get_image_id(), 'large');
                    $price_clean = wc_price($product->get_price());
                    $regular_price_clean = $product->is_on_sale() ? wc_price($product->get_regular_price()) : '';
                    $has_model = !empty($model_info['glb_url']);
                    $has_ar = $has_model && !empty($model_info['ar_enabled']);

                    if ($has_model):
                        ?>
                        <div class="ax-3d-ar-actions-group">
                            <!-- Nút 1: Xem Mô hình 3D 360° -->
                            <button type="button" class="ar-product-btn ax-open-3d-btn" id="axView3DModelBtn"
                                data-product-id="<?php echo esc_attr($product->get_id()); ?>"
                                data-product-name="<?php echo esc_attr($product->get_name()); ?>"
                                data-product-price="<?php echo esc_attr(wp_strip_all_tags($price_clean)); ?>"
                                data-product-regular-price="<?php echo esc_attr(wp_strip_all_tags($regular_price_clean)); ?>"
                                data-product-raw-price="<?php echo esc_attr($product->get_price()); ?>"
                                data-product-image="<?php echo esc_url($image_url); ?>"
                                data-product-dimensions="<?php echo esc_attr($dim_str); ?>"
                                data-category-type="<?php echo esc_attr($cat_type); ?>"
                                data-default-color="<?php echo esc_attr($def_color); ?>" data-has-3d="yes"
                                data-ar-enabled="<?php echo $has_ar ? 'yes' : 'no'; ?>"
                                data-glb-url="<?php echo esc_url($model_info['glb_url']); ?>"
                                data-usdz-url="<?php echo esc_url($model_info['usdz_url']); ?>" data-initial-tab="3d">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2" aria-hidden="true">
                                    <path
                                        d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" />
                                    <polyline points="3.27 6.96 12 12.01 20.73 6.96" />
                                    <line x1="12" y1="22.08" x2="12" y2="12" />
                                </svg>
                                <span><?php echo esc_html(furniva_t('View 3D Model', 'Xem Mô Hình 3D')); ?></span>
                            </button>

                            <?php if ($has_ar): ?>
                                <!-- Nút 2: Xem Trong Phòng Bạn (AR Thực tế qua Camera) -->
                                <button type="button" class="ar-product-btn ax-open-ar-btn" id="arViewInRoomBtn"
                                    data-product-id="<?php echo esc_attr($product->get_id()); ?>"
                                    data-product-name="<?php echo esc_attr($product->get_name()); ?>"
                                    data-product-price="<?php echo esc_attr(wp_strip_all_tags($price_clean)); ?>"
                                    data-product-regular-price="<?php echo esc_attr(wp_strip_all_tags($regular_price_clean)); ?>"
                                    data-product-raw-price="<?php echo esc_attr($product->get_price()); ?>"
                                    data-product-image="<?php echo esc_url($image_url); ?>"
                                    data-product-dimensions="<?php echo esc_attr($dim_str); ?>"
                                    data-category-type="<?php echo esc_attr($cat_type); ?>"
                                    data-default-color="<?php echo esc_attr($def_color); ?>" data-has-3d="yes" data-ar-enabled="yes"
                                    data-glb-url="<?php echo esc_url($model_info['glb_url']); ?>"
                                    data-usdz-url="<?php echo esc_url($model_info['usdz_url']); ?>" data-initial-tab="ar">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                        stroke-width="2" aria-hidden="true">
                                        <path
                                            d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z" />
                                        <circle cx="12" cy="13" r="4" />
                                    </svg>
                                    <span><?php echo esc_html($pdp_seasonal ? furniva_t('Preview in Room (AR)', 'Đặt vào phòng (AR)') : furniva_t('View in Your Room (AR)', 'Xem Trong Phòng (AR)')); ?></span>
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php endif; // $has_model ?>
                <?php endif; // ! is_virtual ?>

                <!-- Buy Buttons -->
                <div class="ax-buy-actions">
                    <div class="ax-qty-cart">
                        <div
                            class="ax-qty-stepper <?php echo (!$product->is_type('variable') && (!$product->is_purchasable() || !$product->is_in_stock())) ? 'is-disabled' : ''; ?>">
                            <button type="button" class="ax-qty-minus"
                                aria-label="<?php echo esc_attr(furniva_t('Decrease quantity', 'Giảm số lượng')); ?>"
                                <?php disabled(!$product->is_type('variable') && (!$product->is_purchasable() || !$product->is_in_stock())); ?>>−</button>
                            <input type="number" class="ax-qty-input" value="1" min="1" step="1" inputmode="numeric"
                                aria-label="<?php echo esc_attr(furniva_t('Quantity', 'Số lượng')); ?>" <?php disabled(!$product->is_type('variable') && (!$product->is_purchasable() || !$product->is_in_stock())); ?> />
                            <button type="button" class="ax-qty-plus"
                                aria-label="<?php echo esc_attr(furniva_t('Increase quantity', 'Tăng số lượng')); ?>"
                                <?php disabled(!$product->is_type('variable') && (!$product->is_purchasable() || !$product->is_in_stock())); ?>>+</button>
                        </div>
                        <button type="button" class="ax-btn ax-btn-primary ax-btn-cart" id="axAddToCart" <?php disabled(!$product->is_type('variable') && (!$product->is_purchasable() || !$product->is_in_stock())); ?>>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <circle cx="9" cy="21" r="1.6" />
                                <circle cx="19" cy="21" r="1.6" />
                                <path d="M2.5 3h2l2.4 12.4a2 2 0 0 0 2 1.6h9.7a2 2 0 0 0 2-1.6l1.4-8H6" />
                            </svg>
                            <span><?php echo esc_html(furniva_t('Add to cart', 'Thêm vào giỏ')); ?></span>
                        </button>
                    </div>

                    <?php
                    $is_in_wl = false;
                    if (function_exists('yith_wcwl_wishlists')) {
                        $is_in_wl = yith_wcwl_wishlists()->is_product_in_wishlist($id);
                    } elseif (function_exists('YITH_WCWL') && method_exists(YITH_WCWL(), 'is_product_in_wishlist')) {
                        $is_in_wl = YITH_WCWL()->is_product_in_wishlist($id);
                    }
                    ?>
                    <button type="button" class="ax-btn ax-btn-wishlist <?php echo $is_in_wl ? 'is-added' : ''; ?>"
                        id="axAddToWishlist" data-product-id="<?php echo esc_attr($id); ?>"
                        aria-label="<?php echo esc_attr(furniva_t('Wishlist', 'Danh sách yêu thích')); ?>">
                        <svg width="18" height="18" viewBox="0 0 24 24"
                            fill="<?php echo $is_in_wl ? 'currentColor' : 'none'; ?>" stroke="currentColor"
                            stroke-width="2">
                            <path
                                d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21.2l7.8-7.8 1-1a5.5 5.5 0 0 0 0-7.8z" />
                        </svg>
                        <span><?php echo esc_html($is_in_wl ? furniva_t('Saved to wishlist ♥', 'Đã lưu yêu thích ♥') : furniva_t('Add to wishlist', 'Thêm yêu thích')); ?></span>
                    </button>
                </div>

                <!-- Trust Features (Strictly aligned with live WooCommerce shipping & DB policy pages) -->
                <div class="ax-trust-features">
                    <div class="ax-trust-item">
                        <div class="ax-trust-icon">
                            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor"
                                stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <rect x="1" y="3" width="15" height="13" />
                                <polygon points="16 8 20 8 23 11 23 16 16 16 16 8" />
                                <circle cx="5.5" cy="18.5" r="2.5" />
                                <circle cx="18.5" cy="18.5" r="2.5" />
                            </svg>
                        </div>
                        <div>
                            <strong><?php echo esc_html(furniva_t('Standard Delivery & Inspection', 'Giao hàng tiêu chuẩn & đồng kiểm')); ?></strong>
                            <span><?php echo esc_html(furniva_t('Shipping method & fee displayed transparently at checkout', 'Phương thức và phí giao hàng hiển thị minh bạch tại thanh toán')); ?></span>
                        </div>
                    </div>
                    <div class="ax-trust-item">
                        <div class="ax-trust-icon">
                            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor"
                                stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                                <polyline points="9 12 11 14 15 10" />
                            </svg>
                        </div>
                        <div>
                            <strong><?php echo esc_html(furniva_t('Structural & Hardware Support', 'Hỗ trợ kết cấu & phụ kiện')); ?></strong>
                            <span><?php echo esc_html(furniva_t('Timber frame joints & soft-close hardware per store policy (demo)', 'Khung gỗ & phụ kiện giảm chấn theo chính sách cửa hàng (mô phỏng)')); ?></span>
                        </div>
                    </div>
                    <div class="ax-trust-item">
                        <div class="ax-trust-icon">
                            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor"
                                stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <polyline points="9 11 12 14 22 4" />
                                <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11" />
                            </svg>
                        </div>
                        <div>
                            <strong><?php echo esc_html(furniva_t('Technical Defect Exchange', 'Đổi mới đối với lỗi kỹ thuật')); ?></strong>
                            <span><?php echo esc_html(furniva_t('Inspect surface finish & structure upon delivery before signing', 'Đồng kiểm ngoại quan bề mặt gỗ & kết cấu trước khi ký nhận')); ?></span>
                        </div>
                    </div>
                </div>

                <!-- 5-Part Structured Accordion: Tổng quan / Kích thước / Vật liệu / Lắp đặt / Bảo hành -->
                <?php
                $mat_terms = wc_get_product_terms($id, 'pa_material', array('fields' => 'names'));
                $mat_display = (!empty($mat_terms) && !is_wp_error($mat_terms))
                    ? implode(', ', array_map(static function ($t) {
                        return furniva_term_label($t, 'pa_material');
                    }, $mat_terms))
                    : ($finish_desc_meta ?: furniva_t('FSC-certified engineered timber with scratch-resistant matte veneer', 'Gỗ công nghiệp đạt chuẩn FSC phủ Melamine/Veneer chống trầy mịn lì'));
                $policy_ret_url = furniva_url('/chinh-sach-doi-tra-bao-hanh/');
                $policy_ship_url = furniva_url('/chinh-sach-giao-hang/');
                $overview_plain = trim(wp_strip_all_tags($product->get_short_description()));
                if ('' === $overview_plain) {
                    $overview_plain = furniva_t(
                        'Designed with balanced Nordic proportions and concealed hardware to bring calm, lasting order to modern living spaces.',
                        'Thiết kế theo tỷ lệ Bắc Âu cân đối với phụ kiện giảm chấn tinh gọn, mang lại sự ngăn nắp và vẻ đẹp bền vững cho không gian sống.'
                    );
                }
                ?>
                <div class="ax-spec-accordion" id="axProductAccordion">
                    <!-- 1. Tổng quan -->
                    <details class="ax-spec-item" open>
                        <summary class="ax-spec-summary">
                            <span><?php echo esc_html(furniva_t('1. Overview & 3D/AR Readiness', '1. Tổng quan thiết kế')); ?></span>
                            <svg class="ax-spec-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <polyline points="6 9 12 15 18 9" />
                            </svg>
                        </summary>
                        <div class="ax-spec-body">
                            <p><?php echo esc_html($overview_plain); ?></p>
                            <dl class="ax-spec-dl">
                                <?php if ($product->get_sku()): ?>
                                    <div class="ax-spec-row">
                                        <dt><?php echo esc_html(furniva_t('SKU', 'Mã thiết kế')); ?></dt>
                                        <dd><?php echo esc_html($product->get_sku()); ?></dd>
                                    </div>
                                <?php endif; ?>
                                <?php if ($pdp_has_3d): ?>
                                    <div class="ax-spec-row">
                                        <dt><?php echo esc_html(furniva_t('3D & AR Format', 'Hỗ trợ 3D & AR')); ?></dt>
                                        <dd><?php echo esc_html($pdp_has_ar ? furniva_t('360° 3D (.glb) & 1:1 AR (iOS .usdz / Android)', '3D 360° (.glb) & AR 1:1 (iOS .usdz / Android)') : furniva_t('360° 3D Interactive (.glb)', 'Mô hình 3D 360° (.glb)')); ?>
                                        </dd>
                                    </div>
                                <?php endif; ?>
                            </dl>
                        </div>
                    </details>

                    <!-- 2. Kích thước -->
                    <details class="ax-spec-item">
                        <summary class="ax-spec-summary">
                            <span><?php echo esc_html(furniva_t('2. Dimensions & Proportions', '2. Kích thước & Tỷ lệ')); ?></span>
                            <svg class="ax-spec-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <polyline points="6 9 12 15 18 9" />
                            </svg>
                        </summary>
                        <div class="ax-spec-body">
                            <dl class="ax-spec-dl">
                                <?php if ($pdp_dim_str): ?>
                                    <div class="ax-spec-row">
                                        <dt><?php echo esc_html(furniva_t('Overall Dimensions', 'Kích thước tổng thể')); ?>
                                        </dt>
                                        <dd><?php echo esc_html($pdp_dim_str); ?></dd>
                                    </div>
                                <?php endif; ?>
                                <?php if ($product->get_weight()): ?>
                                    <div class="ax-spec-row">
                                        <dt><?php echo esc_html(furniva_t('Weight', 'Trọng lượng')); ?></dt>
                                        <dd><?php echo esc_html(wc_format_weight($product->get_weight())); ?></dd>
                                    </div>
                                <?php endif; ?>
                                <div class="ax-spec-row">
                                    <dt><?php echo esc_html(furniva_t('Scale Verification', 'Kiểm tra khoảng đặt')); ?>
                                    </dt>
                                    <dd><?php echo esc_html(furniva_t('Use 3D Dimensions or 1:1 AR before ordering', 'Bật thước đo 3D hoặc đặt thử AR tỷ lệ 1:1')); ?>
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    </details>

                    <!-- 3. Vật liệu -->
                    <details class="ax-spec-item">
                        <summary class="ax-spec-summary">
                            <span><?php echo esc_html(furniva_t('3. Materials & Craftsmanship', '3. Vật liệu & Hoàn thiện')); ?></span>
                            <svg class="ax-spec-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <polyline points="6 9 12 15 18 9" />
                            </svg>
                        </summary>
                        <div class="ax-spec-body">
                            <dl class="ax-spec-dl">
                                <div class="ax-spec-row">
                                    <dt><?php echo esc_html(furniva_t('Primary Material', 'Chất liệu chính')); ?>
                                    </dt>
                                    <dd><?php echo esc_html($mat_display); ?></dd>
                                </div>
                                <?php if ($single_finish): ?>
                                    <div class="ax-spec-row">
                                        <dt><?php echo esc_html(furniva_t('Wood Tone / Finish', 'Tông màu hoàn thiện')); ?>
                                        </dt>
                                        <dd><?php echo esc_html($single_finish); ?></dd>
                                    </div>
                                <?php endif; ?>
                                <div class="ax-spec-row">
                                    <dt><?php echo esc_html(furniva_t('Surface Care', 'Bảo dưỡng bề mặt')); ?></dt>
                                    <dd><?php echo esc_html(furniva_t('Wipe with dry microfiber cloth; avoid direct standing water', 'Lau bằng khăn mềm khô, tránh ngâm ẩm trực tiếp')); ?>
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    </details>

                    <!-- 4. Lắp đặt -->
                    <details class="ax-spec-item">
                        <summary class="ax-spec-summary">
                            <span><?php echo esc_html(furniva_t('4. Delivery & In-Room Placement', '4. Lắp đặt & Giao hàng')); ?></span>
                            <svg class="ax-spec-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <polyline points="6 9 12 15 18 9" />
                            </svg>
                        </summary>
                        <div class="ax-spec-body">
                            <p><?php echo esc_html(furniva_t('According to our Shipping Policy (project simulation): in-stock items estimate 1–3 business days in inner Hanoi and 3–7 business days to other provinces, with white-glove placement and pre-sign inspection. Active shipping options and costs are calculated directly at checkout.', 'Theo Chính sách giao hàng (mô phỏng đồ án): sản phẩm có sẵn dự kiến giao 1–3 ngày làm việc tại nội thành Hà Nội và 3–7 ngày làm việc tại các tỉnh thành khác, kèm quy trình đồng kiểm và cân chỉnh vị trí lắp đặt. Phương thức và phí giao hàng hiện hành được tính trực tiếp tại bước thanh toán.')); ?>
                            </p>
                            <a class="ax-spec-link"
                                href="<?php echo esc_url($policy_ship_url); ?>"><?php echo esc_html(furniva_t('Read full shipping policy →', 'Xem chi tiết chính sách giao hàng →')); ?></a>
                        </div>
                    </details>

                    <!-- 5. Bảo hành -->
                    <details class="ax-spec-item">
                        <summary class="ax-spec-summary">
                            <span><?php echo esc_html(furniva_t('5. Warranty & Technical Exchange', '5. Bảo hành & Đổi trả')); ?></span>
                            <svg class="ax-spec-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <polyline points="6 9 12 15 18 9" />
                            </svg>
                        </summary>
                        <div class="ax-spec-body">
                            <p><?php echo esc_html(furniva_t('According to our Returns & Warranty Policy (project simulation — no fixed commercial duration asserted): covers structural timber joints, drawer slides, and soft-close hinges, plus equivalent replacement for confirmed manufacturing defects or mismatched items.', 'Theo Chính sách đổi trả & bảo hành (mô phỏng đồ án — không ấn định thời hạn thương mại cố định): hỗ trợ bảo hành kết cấu khung gỗ, bản lề giảm chấn, ray trượt và đổi sản phẩm tương đương khi phát sinh lỗi kỹ thuật sản xuất hoặc giao sai quy cách.')); ?>
                            </p>
                            <a class="ax-spec-link"
                                href="<?php echo esc_url($policy_ret_url); ?>"><?php echo esc_html(furniva_t('Read full warranty & return policy →', 'Xem chi tiết chính sách bảo hành & đổi trả →')); ?></a>
                        </div>
                    </details>
                </div>
            </div>
        </div>

        <!-- Product Tabs (Description, Details, Shipping) -->
        <div class="ax-product-tabs">
            <div class="ax-tabs-nav" role="tablist"
                aria-label="<?php echo esc_attr(furniva_t('Product information tabs', 'Các tab thông tin sản phẩm')); ?>">
                <button type="button" class="ax-tab-btn active" role="tab" id="ax-tab-btn-description"
                    aria-selected="true" aria-controls="ax-tab-description"
                    data-tab="description"><?php echo esc_html(furniva_t('Description', 'Mô tả')); ?></button>
                <button type="button" class="ax-tab-btn" role="tab" id="ax-tab-btn-details" aria-selected="false"
                    aria-controls="ax-tab-details"
                    data-tab="details"><?php echo esc_html(furniva_t('Details', 'Chi tiết')); ?></button>
                <button type="button" class="ax-tab-btn" role="tab" id="ax-tab-btn-shipping" aria-selected="false"
                    aria-controls="ax-tab-shipping"
                    data-tab="shipping"><?php echo esc_html(furniva_t('Shipping & Returns', 'Vận chuyển & Đổi trả')); ?></button>
                <button type="button" class="ax-tab-btn" role="tab" id="ax-tab-btn-reviews" aria-selected="false"
                    aria-controls="ax-tab-reviews"
                    data-tab="reviews"><?php echo esc_html(furniva_t('Reviews', 'Đánh giá')); ?></button>
            </div>

            <div class="ax-tabs-content">
                <div class="ax-tab-panel active" role="tabpanel" id="ax-tab-description"
                    aria-labelledby="ax-tab-btn-description">
                    <div class="ax-tab-inner">
                        <div class="ax-tab-text">
                            <?php if ($product->get_description()): ?>
                                <?php echo wp_kses_post(apply_filters('the_content', $product->get_description())); ?>
                            <?php else: ?>
                                <p><?php echo esc_html(furniva_t('Product information is being updated.', 'Thông tin chi tiết sản phẩm đang được cập nhật.')); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                        <div class="ax-tab-image">
                            <?php if (!empty($all_images[1])):
                                $img2 = wp_get_attachment_image_url($all_images[1], 'large');
                                ?>
                                <img src="<?php echo esc_url($img2); ?>" alt="" loading="lazy" />
                            <?php elseif (!empty($all_images[0])):
                                $img1 = wp_get_attachment_image_url($all_images[0], 'large');
                                ?>
                                <img src="<?php echo esc_url($img1); ?>" alt="" loading="lazy" />
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="ax-tab-panel" role="tabpanel" id="ax-tab-details" aria-labelledby="ax-tab-btn-details"
                    hidden>
                    <div class="ax-tab-inner">
                        <?php $care_instructions = get_post_meta($id, '_despace_care_instructions', true); ?>
                        <?php if ($care_instructions): ?>
                            <section class="ax-product-care">
                                <h3><?php echo esc_html(furniva_t('Care instructions', 'Hướng dẫn bảo quản')); ?></h3>
                                <?php echo wp_kses_post(wpautop($care_instructions)); ?>
                            </section>
                        <?php endif; ?>
                        <?php woocommerce_product_additional_information_tab(); ?>
                    </div>
                </div>

                <div class="ax-tab-panel" role="tabpanel" id="ax-tab-shipping" aria-labelledby="ax-tab-btn-shipping"
                    hidden>
                    <div class="ax-tab-inner">
                        <h3><?php echo esc_html(furniva_t('Shipping Information', 'Thông tin vận chuyển')); ?></h3>
                        <p><?php echo esc_html(furniva_t('Available delivery methods and fees are shown during checkout based on your address and order.', 'Phương thức và phí giao hàng sẽ được hiển thị tại bước thanh toán theo địa chỉ và đơn hàng của bạn.')); ?>
                        </p>
                        <h3><?php echo esc_html(furniva_t('Return Policy', 'Chính sách đổi trả')); ?></h3>
                        <?php $terms_url = wc_get_page_permalink('terms') ? furniva_url(wc_get_page_permalink('terms')) : furniva_url('/chinh-sach-doi-tra-bao-hanh/'); ?>
                        <p><?php echo esc_html(furniva_t('Please review the store terms for the applicable return and exchange conditions.', 'Vui lòng xem điều khoản của cửa hàng để biết điều kiện đổi trả áp dụng.')); ?>
                            <?php if ($terms_url): ?> <a
                                    href="<?php echo esc_url($terms_url); ?>"><?php echo esc_html(furniva_t('View store terms', 'Xem điều khoản cửa hàng')); ?></a><?php endif; ?>
                        </p>
                    </div>
                </div>

                <div class="ax-tab-panel" role="tabpanel" id="ax-tab-reviews" aria-labelledby="ax-tab-btn-reviews"
                    hidden>
                    <div class="ax-tab-inner">
                        <?php comments_template(); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Subtle Winter Atelier Holiday Room Styling Context (never alters product photography) -->
        <?php if ($pdp_seasonal): ?>
            <section class="ds-pdp-holiday-styling"
                aria-label="<?php echo esc_attr(furniva_t('Winter Atelier Room Styling Context', 'Gợi ý bài trí mùa lễ hội Winter Atelier')); ?>">
                <div class="ds-pdp-holiday-styling__lead">
                    <picture>
                        <source
                            srcset="<?php echo esc_url(get_stylesheet_directory_uri() . '/assets/images/astro-winter-atelier.webp'); ?>"
                            type="image/webp" />
                        <img class="ds-pdp-holiday-styling__astro"
                            src="<?php echo esc_url(get_stylesheet_directory_uri() . '/assets/images/astro-winter-atelier.jpg'); ?>"
                            alt="Astro — DeSpace Winter Atelier" width="60" height="60" loading="lazy" decoding="async" />
                    </picture>
                    <div>
                        <span class="ds-pdp-holiday-styling__kicker">❄
                            <?php echo esc_html(furniva_t('WINTER ATELIER 2026 · STYLING NOTE', 'WINTER ATELIER 2026 · GỢI Ý BÀI TRÍ MÙA LỄ')); ?></span>
                        <h3><?php echo esc_html(furniva_t('Style this piece for a calm Nordic Christmas', 'Bài trí thiết kế này cho không gian Giáng sinh tinh giản')); ?>
                        </h3>
                        <p><?php echo esc_html(furniva_t('Pair natural wood grain with an ivory stoneware vase, fresh pine sprigs, and warm brushed-brass candlelight — use 1:1 AR to check wall proportions in your own room.', 'Phối hợp bề mặt gỗ ấm cùng bình gốm kem, một cành thông tươi và ánh đèn đồng thau dịu nhẹ — dùng tính năng AR 1:1 để ướm vừa vặn vào góc phòng thực tế của bạn.')); ?>
                        </p>
                    </div>
                </div>
                <?php if ($pdp_has_ar): ?>
                    <button type="button" class="ds-pdp-holiday-styling__cta"
                        onclick="document.getElementById('arViewInRoomBtn') && document.getElementById('arViewInRoomBtn').click();">
                        <span><?php echo esc_html(furniva_t('Preview in Your Space (AR)', 'Thử đặt vào phòng (AR)')); ?></span>
                        <span aria-hidden="true">↗</span>
                    </button>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <!-- Related Products -->
        <?php if (wc_get_related_products($id, 4)): ?>
            <div class="ax-related-section">
                <h2 class="ax-related-title">
                    <?php echo esc_html(furniva_t('You May Also Like', 'Có thể bạn cũng thích')); ?>
                </h2>
                <?php
                add_filter('woocommerce_output_related_products_args', function ($args) {
                    $args['posts_per_page'] = 4;
                    $args['columns'] = 4;
                    return $args;
                }, 20);
                woocommerce_output_related_products();
                ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Image Lightbox Modal -->
<div id="axGalleryLightbox" class="ax-gallery-lightbox" style="display:none;" role="dialog" aria-modal="true"
    aria-label="<?php echo esc_attr(furniva_t('Enlarged product image', 'Phóng to ảnh sản phẩm')); ?>">
    <div class="ax-lightbox-backdrop"></div>
    <div class="ax-lightbox-content">
        <button type="button" class="ax-lightbox-close"
            aria-label="<?php echo esc_attr(furniva_t('Close', 'Đóng')); ?>">&times;</button>
        <img src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7" alt=""
            class="ax-lightbox-img" />
    </div>
</div>

<script>
    jQuery(function ($) {
        // Gallery navigation
        var currentImageIndex = 0;
        var $images = $('.ax-gallery-image');
        var $thumbs = $('.ax-thumb-item');
        var totalImages = $images.length;

        function showImage(index) {
            if (index < 0 || index >= totalImages) return;
            $images.hide().removeClass('active');
            $images.eq(index).show().addClass('active');
            $thumbs.removeClass('active').attr('aria-pressed', 'false');
            $thumbs.eq(index).addClass('active').attr('aria-pressed', 'true');
            currentImageIndex = index;
            var $counter = $('#axGalleryCounter');
            if ($counter.length) {
                $counter.text((index + 1) + ' / ' + totalImages);
            }
        }

        // One-click verified coupon copy on PDP
        $(document).on('click', '.ax-pdp-voucher-hint__copy', function () {
            var $btn = $(this);
            var code = $btn.attr('data-copy-coupon');
            if (!code) return;
            var origText = $btn.text();
            var doneText = '<?php echo esc_js(furniva_t('Copied ✓', 'Đã chép ✓')); ?>';
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(code).catch(function () {});
            }
            $btn.text(doneText);
            setTimeout(function () { $btn.text(origText); }, 1800);
        });

        $('#axGalleryPrev').on('click', function () {
            var newIndex = currentImageIndex > 0 ? currentImageIndex - 1 : totalImages - 1;
            showImage(newIndex);
        });

        $('#axGalleryNext').on('click', function () {
            var newIndex = currentImageIndex < totalImages - 1 ? currentImageIndex + 1 : 0;
            showImage(newIndex);
        });

        $thumbs.on('click', function () {
            var idx = $(this).data('index');
            showImage(idx);
        });

        // Zoom Lightbox
        function closeLightbox(restoreFocus) {
            $('#axGalleryLightbox').fadeOut(180).attr('aria-hidden', 'true');
            $('body').css('overflow', '');
            if (restoreFocus) {
                $('.ax-gallery-zoom').first().trigger('focus');
            }
        }

        $('.ax-gallery-zoom').on('click', function () {
            var $activeImg = $('#axGalleryMain .ax-gallery-image.active');
            if ($activeImg.length) {
                var src = $activeImg.attr('src');
                var alt = $activeImg.attr('alt') || '';
                $('#axGalleryLightbox .ax-lightbox-img').attr('src', src).attr('alt', alt);
                $('#axGalleryLightbox').fadeIn(200).attr('aria-hidden', 'false');
                $('body').css('overflow', 'hidden');
                setTimeout(function () {
                    $('#axGalleryLightbox .ax-lightbox-close').trigger('focus');
                }, 60);
            }
        });

        $(document).on('click', '#axGalleryLightbox .ax-lightbox-close, #axGalleryLightbox .ax-lightbox-backdrop', function () {
            closeLightbox(true);
        });

        $(document).on('keydown', function (e) {
            if (e.key === 'Escape' && $('#axGalleryLightbox').is(':visible')) {
                closeLightbox(true);
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

        $('.ax-qty-minus').on('click', function () {
            if ($(this).prop('disabled')) return;
            var val = syncQuantity();
            var min = parseInt($quantity.attr('min'), 10) || 1;
            $quantity.val(Math.max(min, val - 1)).trigger('change');
        });

        $('.ax-qty-plus').on('click', function () {
            if ($(this).prop('disabled')) return;
            var val = syncQuantity();
            var max = parseInt($quantity.attr('max'), 10);
            var nextVal = val + 1;
            if (Number.isFinite(max) && max > 0) nextVal = Math.min(max, nextVal);
            $quantity.val(nextVal).trigger('change');
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
        $variationForm.find('.variations select').on('change', function () {
            $('#axVariationFeedback').empty();
            syncCustomCartState();
        });
        syncCustomCartState();

        $variationForm.on('found_variation', function (event, variation) {
            if (variation.price_html) $price.html(variation.price_html);
            $sku.text(variation.sku || '—');
            var stockText = variation.is_in_stock ? '<?php echo esc_js(furniva_t('In stock', 'Còn hàng')); ?>' : '<?php echo esc_js(furniva_t('Out of stock', 'Hết hàng')); ?>';
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
                $('#axVariationFeedback').text('<?php echo esc_js(furniva_t('The selected option is out of stock.', 'Tùy chọn đã chọn hiện đang hết hàng.')); ?>');
            }

            if (variation.image && variation.image.src && $mainImage.length) {
                $mainImage.attr({
                    src: variation.image.src,
                    srcset: variation.image.srcset || '',
                    sizes: variation.image.sizes || '',
                    alt: variation.image.alt || '<?php echo esc_js($product->get_name()); ?>'
                });
                showImage(0);
            } else if (originalImage && $mainImage.length) {
                $mainImage.attr(originalImage);
                showImage(0);
            }
        });

        $variationForm.on('reset_data hide_variation', function () {
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
        $('#axAddToCart').on('click', function (e) {
            e.preventDefault();
            var qty = syncQuantity();
            var $form = $('.ax-product-form form.cart');
            if (!$form.length) return;

            var isVariable = $form.hasClass('variations_form');
            if (isVariable) {
                var variationId = parseInt($form.find('input[name="variation_id"]').val(), 10);
                var $nativeButton = $form.find('button.single_add_to_cart_button');

                if (!variationId || variationId <= 0) {
                    var $unfilled = $form.find('.variations select').filter(function () { return !$(this).val(); }).first();
                    if ($unfilled.length) $unfilled.trigger('focus');
                    $('#axVariationFeedback').text('<?php echo esc_js(furniva_t('Choose all available product options before adding this item to your cart.', 'Vui lòng chọn đầy đủ các thuộc tính sản phẩm trước khi thêm vào giỏ hàng.')); ?>');
                    return;
                }

                if ($nativeButton.is(':disabled') || $nativeButton.hasClass('disabled')) {
                    $('#axVariationFeedback').text('<?php echo esc_js(furniva_t('The selected option is out of stock.', 'Tùy chọn đã chọn hiện đang hết hàng.')); ?>');
                    return;
                }

                $('#axVariationFeedback').empty();
                $form.find('input[name="quantity"]').val(qty).trigger('change');
                if (!$form.find('input[name="add-to-cart"]').length) {
                    $('<input>').attr({ type: 'hidden', name: 'add-to-cart', value: '<?php echo esc_js($id); ?>' }).appendTo($form);
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
                    $('#axVariationFeedback').text('<?php echo esc_js(furniva_t('This product is currently out of stock.', 'Sản phẩm hiện đang hết hàng.')); ?>');
                    return;
                }
                $('#axVariationFeedback').empty();
                $form.find('input[name="quantity"]').val(qty).trigger('change');
                if (!$form.find('input[name="add-to-cart"]').length) {
                    $('<input>').attr({ type: 'hidden', name: 'add-to-cart', value: '<?php echo esc_js($id); ?>' }).appendTo($form);
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
        $('.ax-tab-btn').on('click', function () {
            var tab = $(this).data('tab');
            $('.ax-tab-btn').removeClass('active').attr('aria-selected', 'false');
            $(this).addClass('active').attr('aria-selected', 'true');
            $('.ax-tab-panel').removeClass('active').attr('hidden', true);
            $('#ax-tab-' + tab).addClass('active').removeAttr('hidden');
        });

        // Keyboard navigation for tabs
        $('.ax-tabs-nav').on('keydown', '.ax-tab-btn', function (e) {
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
        $('.ax-product-rating, .ax-write-review').css('cursor', 'pointer').on('click', function (e) {
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

<?php do_action('woocommerce_after_single_product'); ?>