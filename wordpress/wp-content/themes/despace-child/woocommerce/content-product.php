<?php
/**
 * Furniva custom product card — used on shop, archives, shortcodes and related products.
 *
 * @global WC_Product $product
 */
if ( ! defined( 'ABSPATH' ) ) exit;

global $product;
if ( empty( $product ) || ! $product->is_visible() ) {
	return;
}

$id        = $product->get_id();
$link      = get_permalink( $id );
$regular   = (float) $product->get_regular_price();
$sale      = (float) $product->get_sale_price();
$percent   = ( $regular && $sale ) ? round( ( ( $regular - $sale ) / $regular ) * 100 ) : 0;
$gallery   = $product->get_gallery_image_ids();
$hover_img = $gallery ? array_shift( $gallery ) : 0;
$product_cats = get_the_terms( $id, 'product_cat' );
$cat_name     = ( ! empty( $product_cats ) && ! is_wp_error( $product_cats ) ) ? furniva_term_label( $product_cats[0]->name, 'product_cat' ) : '';
$rating       = $product->get_average_rating();
$count        = $product->get_review_count();
$colors       = wc_get_product_terms( $id, 'pa_color' );
?>
<li <?php wc_product_class( 'f-card-wrap', $product ); ?>>
	<div class="f-card">
		<div class="f-card-media">
			<?php
			$custom_badge = get_post_meta( $id, '_axgnud_badge', true );
			?>
			<div class="f-card-badges">
				<?php if ( ! empty( $custom_badge ) ) :
					$badge_slug  = sanitize_title( $custom_badge );
					$badge_label = furniva_t( $custom_badge, ( 'Best Seller' === $custom_badge ? 'Bán chạy' : ( 'Featured' === $custom_badge ? 'Nổi bật' : ( 'New' === $custom_badge ? 'Mới' : ( 'Popular' === $custom_badge ? 'Phổ biến' : ( 'Sale' === $custom_badge ? 'Giảm giá' : $custom_badge ) ) ) ) ) );
				?>
					<span class="f-badge-custom f-badge-<?php echo esc_attr( $badge_slug ); ?>"><?php echo esc_html( $badge_label ); ?></span>
				<?php elseif ( $percent ) : ?>
					<span class="f-badge-sale">-<?php echo esc_html( $percent ); ?>%</span>
				<?php endif; ?>
			</div>

			<div class="f-card-actions">
				<?php
				// Wishlist (YITH or native fallback).
				if ( shortcode_exists( 'yith_wcwl_add_to_wishlist' ) ) {
					echo do_shortcode( '[yith_wcwl_add_to_wishlist product_id="' . $id . '"]' );
				} else {
					echo '<button type="button" class="f-card-wishlist-btn" aria-label="' . esc_attr( furniva_t( 'Add to wishlist', 'Thêm vào danh sách yêu thích' ) ) . '">' . furniva_svg( 'heart' ) . '</button>';
				}
				?>
			</div>

			<a href="<?php echo esc_url( $link ); ?>" aria-label="<?php the_title(); ?>" class="f-card-img-link">
				<?php
				echo $product->get_image( 'woocommerce_thumbnail', array( 'class' => 'f-card-img' ) );
				if ( $hover_img ) {
					echo wp_get_attachment_image( $hover_img, 'woocommerce_thumbnail', false, array( 'class' => 'f-card-img--hover', 'loading' => 'lazy' ) );
				}
				?>
			</a>
		</div>

		<div class="f-card-body">
			<!-- 4. Category (Clean uppercase text) -->
			<?php if ( $cat_name ) : ?>
				<div class="f-card-cat"><?php echo esc_html( strtoupper( $cat_name ) ); ?></div>
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
				if ( $sale ) {
					echo '<del>' . wp_kses_post( wc_price( $regular ) ) . '</del><ins>' . wp_kses_post( wc_price( $sale ) ) . '</ins>';
				} else {
					echo wp_kses_post( wc_price( $product->get_price() ) );
				}
				?>
			</div>


			<!-- 9. Primary CTA: Add to cart -->
			<?php if ( $product->is_type( 'simple' ) && $product->is_purchasable() && $product->is_in_stock() ) : ?>
				<a href="<?php echo esc_url( '?add-to-cart=' . $id ); ?>"
					data-quantity="1" data-product_id="<?php echo esc_attr( $id ); ?>"
					class="f-card-add add_to_cart_button ajax_add_to_cart product_type_simple">
					<?php echo furniva_svg( 'cart' ); ?> <?php echo esc_html( furniva_t( 'Add to cart', 'Thêm vào giỏ' ) ); ?>
				</a>
			<?php elseif ( $product->is_type( 'variable' ) ) : ?>
				<a href="<?php echo esc_url( $link ); ?>" class="f-card-add">
					<?php echo furniva_svg( 'cart' ); ?> <?php echo esc_html( furniva_t( 'Add to cart', 'Thêm vào giỏ' ) ); ?>
				</a>
			<?php elseif ( ! $product->is_in_stock() ) : ?>
				<span class="f-card-add is-disabled"><?php echo esc_html( furniva_t( 'Out of stock', 'Hết hàng' ) ); ?></span>
			<?php endif; ?>
		</div>
	</div>
</li>
