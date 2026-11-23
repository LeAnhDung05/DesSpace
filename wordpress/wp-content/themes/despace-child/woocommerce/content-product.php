<?php
/**
 * DeSpace custom product card — used on shop, archives, shortcodes and related products.
 *
 * @global WC_Product $product
 */
if ( ! defined( 'ABSPATH' ) ) exit;

global $product;
if ( empty( $product ) || ! $product->is_visible() ) {
	return;
}

$id        = $product->get_id();
$link      = furniva_url( get_permalink( $id ) );
$sku       = $product->get_sku();
$is_on_sale = $product->is_on_sale();
$is_in_stock = $product->is_in_stock();
$regular   = (float) $product->get_regular_price();
$sale      = (float) $product->get_sale_price();
$percent   = 0;
if ( $is_on_sale ) {
	if ( $product->is_type( 'variable' ) ) {
		$var_prices = $product->get_variation_prices( true );
		if ( ! empty( $var_prices['regular_price'] ) && ! empty( $var_prices['sale_price'] ) ) {
			foreach ( $var_prices['regular_price'] as $vid => $reg_p ) {
				$reg_f = (float) $reg_p;
				$sal_f = isset( $var_prices['sale_price'][ $vid ] ) ? (float) $var_prices['sale_price'][ $vid ] : 0;
				if ( $reg_f > 0 && $sal_f > 0 && $sal_f < $reg_f ) {
					$percent = max( $percent, (int) round( ( ( $reg_f - $sal_f ) / $reg_f ) * 100 ) );
				}
			}
		}
	} elseif ( $regular > 0 && $sale > 0 && $sale < $regular ) {
		$percent = (int) round( ( ( $regular - $sale ) / $regular ) * 100 );
	}
}
$gallery   = $product->get_gallery_image_ids();
$hover_img = $gallery ? array_shift( $gallery ) : 0;
$product_cats = get_the_terms( $id, 'product_cat' );
$cat_name     = ( ! empty( $product_cats ) && ! is_wp_error( $product_cats ) ) ? furniva_term_label( $product_cats[0]->name, 'product_cat' ) : '';
$rating       = $product->get_average_rating();
$count        = $product->get_review_count();
$colors       = wc_get_product_terms( $id, 'pa_color' );
$thumb_large  = $product->get_image_id() ? wp_get_attachment_image_url( $product->get_image_id(), 'large' ) : wc_placeholder_img_src( 'woocommerce_single' );
$card_3d      = function_exists( 'ax_get_product_3d_info' ) ? ax_get_product_3d_info( $id ) : array();
$raw_dims     = $product->get_dimensions( false );
$card_dim_str = '';
if ( ! empty( $raw_dims['length'] ) && ! empty( $raw_dims['width'] ) && ! empty( $raw_dims['height'] ) ) {
	$card_dim_str = furniva_t(
		sprintf( 'W %s × D %s × H %s cm', $raw_dims['length'], $raw_dims['width'], $raw_dims['height'] ),
		sprintf( 'R %s × S %s × C %s cm', $raw_dims['length'], $raw_dims['width'], $raw_dims['height'] )
	);
} elseif ( ! empty( $card_3d['dimensions'] ) ) {
	$card_dim_str = $card_3d['dimensions'];
}
?>
<li <?php wc_product_class( 'f-card-wrap', $product ); ?>>
	<div class="f-card">
		<div class="f-card-media">
			<?php
			$custom_badge  = get_post_meta( $id, '_despace_badge', true ) ?: get_post_meta( $id, '_axgnud_badge', true );
			$card_seasonal = function_exists( 'despace_get_active_seasonal_config' ) ? despace_get_active_seasonal_config() : null;
			$is_winter_pick = $card_seasonal && function_exists( 'despace_get_winter_atelier_product_ids' ) && in_array( (int) $id, despace_get_winter_atelier_product_ids(), true );
			?>
			<div class="f-card-badges">
				<?php if ( ! $is_in_stock ) : ?>
					<span class="f-badge-custom f-badge-outofstock"><?php echo esc_html( furniva_t( 'Out of stock', 'Hết hàng' ) ); ?></span>
				<?php elseif ( $card_seasonal && $is_winter_pick ) : ?>
					<span class="f-badge-holiday">❄ <?php echo esc_html( 'Atelier' . ( $percent > 0 ? ' -' . $percent . '%' : '' ) ); ?></span>
				<?php elseif ( $percent > 0 ) : ?>
					<span class="<?php echo $card_seasonal ? 'f-badge-holiday f-badge-holiday--berry' : 'f-badge-sale'; ?>"><?php echo $card_seasonal ? esc_html( furniva_t( 'Holiday', 'Noel' ) . ' ' ) : ''; ?>-<?php echo esc_html( $percent ); ?>%</span>
				<?php elseif ( ! empty( $custom_badge ) ) :
					$badge_slug  = sanitize_title( $custom_badge );
					$badge_label = furniva_t( $custom_badge, ( 'Best Seller' === $custom_badge ? 'Bán chạy' : ( 'Featured' === $custom_badge ? 'Nổi bật' : ( 'New' === $custom_badge ? 'Mới' : ( 'Popular' === $custom_badge ? 'Phổ biến' : ( 'Sale' === $custom_badge ? 'Giảm giá' : $custom_badge ) ) ) ) ) );
				?>
					<span class="f-badge-custom f-badge-<?php echo esc_attr( $badge_slug ); ?>"><?php echo esc_html( $badge_label ); ?></span>
				<?php endif; ?>
			</div>

			<div class="f-card-actions">
				<?php
				// Wishlist button backed by existing YITH WooCommerce Wishlist AJAX handler (never fakes success when backend is missing).
				$wl_ready       = function_exists( 'despace_is_wishlist_backend_ready' ) && despace_is_wishlist_backend_ready();
				$wl_in_wishlist = $wl_ready && function_exists( 'despace_is_product_in_wishlist' ) && despace_is_product_in_wishlist( $id );
				$wl_label_add   = furniva_t( 'Add to wishlist', 'Thêm vào danh sách yêu thích' );
				$wl_label_rem   = furniva_t( 'Remove from wishlist', 'Bỏ khỏi danh sách yêu thích' );
				$wl_label_unav  = furniva_t( 'Wishlist temporarily unavailable (YITH WooCommerce Wishlist plugin not installed)', 'Tính năng yêu thích hiện chưa sẵn sàng (chưa cài plugin YITH WooCommerce Wishlist)' );
				$wl_aria_label  = $wl_ready ? ( $wl_in_wishlist ? $wl_label_rem : $wl_label_add ) : $wl_label_unav;
				$wl_classes     = 'f-card-wishlist-btn' . ( $wl_in_wishlist ? ' is-active' : '' ) . ( ! $wl_ready ? ' is-unavailable' : '' );
				?>
				<button
					type="button"
					class="<?php echo esc_attr( $wl_classes ); ?>"
					data-product-id="<?php echo esc_attr( $id ); ?>"
					data-product-name="<?php echo esc_attr( $product->get_name() ); ?>"
					data-label-add="<?php echo esc_attr( $wl_label_add ); ?>"
					data-label-remove="<?php echo esc_attr( $wl_label_rem ); ?>"
					<?php if ( ! $wl_ready ) : ?>data-wishlist-unavailable="1" aria-disabled="true"<?php endif; ?>
					aria-pressed="<?php echo $wl_in_wishlist ? 'true' : 'false'; ?>"
					aria-label="<?php echo esc_attr( $wl_aria_label ); ?>"
					title="<?php echo esc_attr( $wl_aria_label ); ?>">
					<?php echo furniva_svg( 'heart' ); ?>
				</button>
				<?php if ( class_exists( 'YITH_WCQV' ) ) : ?>
					<button type="button" class="f-card-qv-btn yith-wcqv-button"
						data-product_id="<?php echo esc_attr( $id ); ?>"
						aria-label="<?php echo esc_attr( furniva_t( 'Quick view', 'Xem nhanh' ) ); ?>"
						title="<?php echo esc_attr( furniva_t( 'Quick view', 'Xem nhanh' ) ); ?>">
						<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
					</button>
				<?php endif; ?>
			</div>

			<?php if ( ! empty( $card_3d['glb_url'] ) ) :
				$has_card_ar = ! empty( $card_3d['ar_enabled'] );
			?>
			<div class="f-card-spatial-dock" role="group" aria-label="<?php echo esc_attr( furniva_t( '3D & AR preview controls', 'Xem thử 3D và AR' ) ); ?>">
				<button type="button" class="f-card-3d-btn ax-open-3d-btn"
					data-product-id="<?php echo esc_attr( $id ); ?>"
					data-product-name="<?php echo esc_attr( $product->get_name() ); ?>"
					data-product-price="<?php echo esc_attr( wp_strip_all_tags( wc_price( $product->get_price() ) ) ); ?>"
					data-product-regular-price="<?php echo esc_attr( $is_on_sale ? wp_strip_all_tags( wc_price( $regular ) ) : '' ); ?>"
					data-product-raw-price="<?php echo esc_attr( $product->get_price() ); ?>"
					data-product-image="<?php echo esc_url( $thumb_large ); ?>"
					data-product-dimensions="<?php echo esc_attr( $card_dim_str ); ?>"
					data-has-3d="yes"
					data-ar-enabled="<?php echo $has_card_ar ? 'yes' : 'no'; ?>"
					data-glb-url="<?php echo esc_url( $card_3d['glb_url'] ); ?>"
					data-usdz-url="<?php echo esc_url( $card_3d['usdz_url'] ?? '' ); ?>"
					data-initial-tab="3d"
					aria-label="<?php echo esc_attr( furniva_t( 'Inspect 360° 3D Model', 'Xem mô hình 3D 360°' ) ); ?>"
					title="<?php echo esc_attr( furniva_t( 'Inspect 360° 3D Model', 'Xem mô hình 3D 360°' ) ); ?>">
					<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
					<span>3D</span>
				</button>
				<?php if ( $has_card_ar ) : ?>
				<button type="button" class="f-card-ar-btn ax-open-ar-btn"
					data-product-id="<?php echo esc_attr( $id ); ?>"
					data-product-name="<?php echo esc_attr( $product->get_name() ); ?>"
					data-product-price="<?php echo esc_attr( wp_strip_all_tags( wc_price( $product->get_price() ) ) ); ?>"
					data-product-regular-price="<?php echo esc_attr( $is_on_sale ? wp_strip_all_tags( wc_price( $regular ) ) : '' ); ?>"
					data-product-raw-price="<?php echo esc_attr( $product->get_price() ); ?>"
					data-product-image="<?php echo esc_url( $thumb_large ); ?>"
					data-product-dimensions="<?php echo esc_attr( $card_dim_str ); ?>"
					data-has-3d="yes"
					data-ar-enabled="yes"
					data-glb-url="<?php echo esc_url( $card_3d['glb_url'] ); ?>"
					data-usdz-url="<?php echo esc_url( $card_3d['usdz_url'] ?? '' ); ?>"
					data-initial-tab="ar"
					aria-label="<?php echo esc_attr( furniva_t( 'Preview AR in Your Room', 'Đặt thử AR trong phòng' ) ); ?>"
					title="<?php echo esc_attr( furniva_t( 'Preview AR in Your Room', 'Đặt thử AR trong phòng' ) ); ?>">
					<span>AR</span>
				</button>
				<?php endif; ?>
			</div>
			<?php endif; ?>

			<a href="<?php echo esc_url( $link ); ?>" aria-label="<?php echo esc_attr( $product->get_name() ); ?>" class="f-card-img-link">
				<?php
				$card_thumb_url = $product->get_image_id() ? wp_get_attachment_image_url( $product->get_image_id(), 'woocommerce_thumbnail' ) : '';
				if ( ! $card_thumb_url ) {
					$card_thumb_url = wc_placeholder_img_src( 'woocommerce_thumbnail' );
				}
				?>
				<img src="<?php echo esc_url( $card_thumb_url ); ?>" alt="<?php echo esc_attr( $product->get_name() ); ?>" class="f-card-img" loading="lazy" decoding="async" />
				<?php
				$hover_thumb_url = $hover_img ? wp_get_attachment_image_url( $hover_img, 'woocommerce_thumbnail' ) : '';
				if ( $hover_thumb_url ) :
				?>
					<img src="<?php echo esc_url( $hover_thumb_url ); ?>" alt="" class="f-card-img--hover" loading="lazy" decoding="async" />
				<?php endif; ?>
			</a>
			<?php if ( $card_dim_str ) : ?>
				<div class="f-card-dims-overlay" aria-label="<?php echo esc_attr( furniva_t( 'Dimensions', 'Kích thước' ) . ': ' . $card_dim_str ); ?>">
					<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 6H3"/><path d="M21 18H3"/><path d="M6 3v18"/><path d="M18 3v18"/></svg>
					<span><?php echo esc_html( $card_dim_str ); ?></span>
				</div>
			<?php endif; ?>
		</div>

		<div class="f-card-body">
			<!-- 4. Category & SKU -->
			<?php if ( $cat_name || $sku ) : ?>
				<div class="f-card-cat" style="display:flex;align-items:center;justify-content:space-between;gap:8px;">
					<?php if ( $cat_name ) : ?><span><?php echo esc_html( function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $cat_name, 'UTF-8' ) : strtoupper( $cat_name ) ); ?></span><?php endif; ?>
					<?php if ( $sku ) : ?><span class="f-card-sku" style="font-size:11px;opacity:0.75;letter-spacing:0.04em;"><?php echo esc_html( $sku ); ?></span><?php endif; ?>
				</div>
			<?php endif; ?>

			<!-- 5. Product Name -->
			<h3 class="f-card-title"><a href="<?php echo esc_url( $link ); ?>"><?php echo esc_html( $product->get_name() ); ?></a></h3>

			<!-- 6. Rating (Clean gold stars + review count) -->
			<div class="f-card-rating">
				<?php
				$rating_val = (float) $product->get_average_rating();
				$review_cnt = (int) $product->get_review_count();
				if ( $review_cnt > 0 ) :
					$stars_count = (int) round( $rating_val );
				?>
					<span class="f-stars" aria-label="<?php echo esc_attr( sprintf( furniva_t( 'Rated %s out of 5', 'Đánh giá %s trên 5' ), number_format( $rating_val, 1 ) ) ); ?>"><?php echo esc_html( str_repeat( '★', $stars_count ) . str_repeat( '☆', 5 - $stars_count ) ); ?></span>
					<span class="f-rating-count">(<?php echo esc_html( $review_cnt ); ?>)</span>
				<?php else : ?>
					<span class="f-stars f-stars--unrated" aria-label="<?php echo esc_attr( furniva_t( 'No reviews yet', 'Chưa có đánh giá' ) ); ?>">☆☆☆☆☆</span>
					<span class="f-rating-count f-rating-count--empty">(0)</span>
				<?php endif; ?>
			</div>

			<!-- 7. Price -->
			<div class="f-card-price">
				<?php
				if ( $product->is_type( 'variable' ) ) {
					echo wp_kses_post( $product->get_price_html() );
				} elseif ( $is_on_sale && $regular > 0 && $sale > 0 && $sale < $regular ) {
					echo '<del>' . wp_kses_post( wc_price( $regular ) ) . '</del><ins>' . wp_kses_post( wc_price( $sale ) ) . '</ins>';
				} else {
					echo wp_kses_post( wc_price( $product->get_price() ) );
				}
				?>
			</div>


			<!-- 9. Primary CTA: Add to cart -->
			<?php if ( ! $is_in_stock ) : ?>
				<span class="f-card-add is-disabled" aria-disabled="true"><?php echo esc_html( furniva_t( 'Out of stock', 'Hết hàng' ) ); ?></span>
			<?php elseif ( $product->is_type( 'simple' ) && $product->is_purchasable() ) : ?>
				<a href="<?php echo esc_url( '?add-to-cart=' . $id ); ?>"
					data-quantity="1" data-product_id="<?php echo esc_attr( $id ); ?>"
					class="f-card-add add_to_cart_button ajax_add_to_cart product_type_simple">
					<?php echo furniva_svg( 'cart' ); ?> <?php echo esc_html( furniva_t( 'Add to cart', 'Thêm vào giỏ' ) ); ?>
				</a>
			<?php elseif ( $product->is_type( 'variable' ) ) : ?>
				<a href="<?php echo esc_url( $link ); ?>" class="f-card-add product_type_variable">
					<?php echo furniva_svg( 'cart' ); ?> <?php echo esc_html( furniva_t( 'Select options', 'Chọn tùy chọn' ) ); ?>
				</a>
			<?php endif; ?>
		</div>
	</div>
</li>
