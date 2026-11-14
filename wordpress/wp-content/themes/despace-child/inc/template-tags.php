<?php
/**
 * DeSpace template helpers: mega menu, quick view integration, filter sidebar.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/* ---------------------------------------------------------------- Mega menu walker
 * Any depth-0 item carrying the class `furniva-mega` gets a category mega panel.
 */
class DeSpace_Mega_Walker extends Walker_Nav_Menu {

	public function start_lvl( &$output, $depth = 0, $args = null ) {
		// Mega panel is injected in start_el; nothing to open here.
	}

	public function end_lvl( &$output, $depth = 0, $args = null ) {
	}

	public static function mega_panel() {
		$panel = '<div class="f-mega">';
		$cats  = get_terms( array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => true,
			'parent'     => 0,
			'exclude'    => array( wc_get_page_id( 'shop' ) ),
		) );
		if ( ! is_wp_error( $cats ) && $cats ) {
			$panel .= '<h4>' . esc_html( furniva_t( 'Product Categories', 'Danh mục sản phẩm' ) ) . '</h4><ul>';
			foreach ( $cats as $c ) {
				$panel .= sprintf(
				'<li><a href="%s">%s <span class="f-count">%d %s</span></a></li>',
				esc_url( get_term_link( $c ) ),
					esc_html( furniva_term_label( $c->name, 'product_cat' ) ),
				(int) $c->count,
				esc_html( furniva_t( 'products', 'sản phẩm' ) )
				);
			}
			$panel .= '</ul>';
		}
		$chair_cat = get_term_by( 'slug', 'chairs', 'product_cat' );
		$thumb_id  = $chair_cat ? get_term_meta( $chair_cat->term_id, 'thumbnail_id', true ) : 0;
		$panel    .= '<a class="f-mega-card" href="' . esc_url( get_permalink( wc_get_page_id( 'shop' ) ) ) . '">';
		if ( $thumb_id ) {
			$panel .= wp_get_attachment_image( $thumb_id, 'medium' );
		}
		$panel .= '<span>' . esc_html( furniva_t( 'Shop all', 'Xem tất cả' ) ) . ' →</span></a></div>';
		return $panel;
	}

	public function start_el( &$output, $item, $depth = 0, $args = null, $id = 0 ) {
		$classes   = empty( $item->classes ) ? array() : (array) $item->classes;
		$classes[] = 'menu-item-' . $item->ID;
		$is_mega   = 0 === $depth && in_array( 'furniva-mega', $classes, true );
		if ( $is_mega ) {
			$classes[] = 'menu-item-has-children';
		}
		$class_names = implode( ' ', apply_filters( 'nav_menu_css_class', array_filter( $classes ), $item, $args, $depth ) );
		$output .= '<li class="' . esc_attr( $class_names ) . '">';
		$output .= '<a href="' . esc_url( $item->url ) . '"' . ( $item->current ? ' aria-current="page"' : '' ) . '>' . esc_html( $item->title ) . '</a>';
		if ( $is_mega ) {
			$output .= self::mega_panel();
		}
	}

	public function end_el( &$output, $item, $depth = 0, $args = null ) {
		$output .= "</li>\n";
	}
}

/* ---------------------------------------------------------------- Quick view button (YITH QV) */
add_action( 'furniva_card_quick_view', function ( $id ) {
	if ( ! class_exists( 'YITH_WCQV_Frontend' ) ) {
		return;
	}
	$label = apply_filters( 'yith_wcqv_button_label', get_option( 'yith-wcqv-button-label', 'Quick View' ) );
	printf(
		'<button type="button" class="f-card-action yith-wcqv-button" data-product_id="%d" title="%s">%s</button>',
		esc_attr( $id ),
		esc_attr( $label ),
		furniva_svg( 'eye' )
	);
}, 10, 1 );

/* ---------------------------------------------------------------- Homepage section head */
function furniva_section_head( $kicker, $title, $link_url = '', $link_text = '' ) {
	echo '<div class="f-section-head"><div><span class="f-kicker">' . esc_html( $kicker ) . '</span><h2>' . esc_html( $title ) . '</h2></div>';
	if ( $link_url ) {
		echo '<a class="f-link" href="' . esc_url( $link_url ) . '">' . esc_html( $link_text ?: 'View all' ) . '</a>';
	}
	echo '</div>';
}

/* ---------------------------------------------------------------- Page hero (blog/about/contact) */
function furniva_page_head( $title, $crumb_extra = '' ) {
	?>
	<div class="f-page-head">
		<div class="f-wrap">
			<h1 class="f-page-title"><?php echo esc_html( $title ); ?></h1>
			<div class="f-breadcrumbs">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo esc_html( furniva_t( 'Home', 'Trang chủ' ) ); ?></a><span class="sep">/</span><?php echo wp_kses_post( $crumb_extra ?: $title ); ?>
			</div>
		</div>
	</div>
	<?php
}

/* ---------------------------------------------------------------- Shop filter sidebar */
function furniva_filter_sidebar() {
	if ( ! furniva_wc_active() ) {
		return;
	}

	$cat_terms = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => true, 'parent' => 0, 'exclude' => array( wc_get_page_id( 'shop' ) ) ) );
	$brands    = get_terms( array( 'taxonomy' => 'pa_brand', 'hide_empty' => true ) );
	$colors    = get_terms( array( 'taxonomy' => 'pa_color', 'hide_empty' => true ) );
	$sizes     = get_terms( array( 'taxonomy' => 'pa_size', 'hide_empty' => true ) );

	$price_range = furniva_get_price_range_data();
	?>
	<aside class="f-filters" id="furniva-filters">
		<div class="f-filters-header">
			<div class="f-filters-head-left">
				<h3 class="f-filters-title"><?php echo esc_html( furniva_t( 'Filter Catalogue', 'Bộ lọc sản phẩm' ) ); ?></h3>
				<button type="button" class="f-filter-reset-link" id="furniva-filter-clear-top"><?php echo esc_html( furniva_t( 'Reset', 'Đặt lại' ) ); ?></button>
			</div>
			<button type="button" class="f-filter-close-drawer" id="furniva-filter-close" aria-label="<?php echo esc_attr( furniva_t( 'Close filters', 'Đóng bộ lọc' ) ); ?>">✕</button>
		</div>

		<form id="furniva-filter-form" onsubmit="return false;">
			<!-- Category (Open) -->
			<details open class="f-filter-group" data-filter-name="category">
				<summary>
					<span><?php echo esc_html( furniva_t( 'CATEGORY', 'DANH MỤC' ) ); ?></span>
					<span class="f-group-meta"><?php echo count( $cat_terms ); ?></span>
				</summary>
				<div class="f-filter-body">
					<?php foreach ( $cat_terms as $t ) : ?>
						<label class="f-filter-check">
							<input type="checkbox" name="category" value="<?php echo esc_attr( $t->slug ); ?>" data-name="<?php echo esc_attr( furniva_term_label( $t->name, 'product_cat' ) ); ?>" />
							<span><?php echo esc_html( furniva_term_label( $t->name, 'product_cat' ) ); ?></span>
							<span class="f-count">(<?php echo (int) $t->count; ?>)</span>
						</label>
					<?php endforeach; ?>
				</div>
			</details>

			<!-- Brand (Collapsed) -->
			<?php if ( $brands && ! is_wp_error( $brands ) ) : ?>
			<details class="f-filter-group" data-filter-name="brand">
				<summary>
					<span><?php echo esc_html( furniva_t( 'BRAND', 'THƯƠNG HIỆU' ) ); ?></span>
					<span class="f-group-meta"><?php echo count( $brands ); ?></span>
				</summary>
				<div class="f-filter-body">
					<?php foreach ( $brands as $t ) : ?>
						<label class="f-filter-check">
							<input type="checkbox" name="brand" value="<?php echo esc_attr( $t->slug ); ?>" data-name="<?php echo esc_attr( $t->name ); ?>" />
							<span><?php echo esc_html( $t->name ); ?></span>
							<span class="f-count">(<?php echo (int) $t->count; ?>)</span>
						</label>
					<?php endforeach; ?>
				</div>
			</details>
			<?php endif; ?>

			<!-- Color & Finish (Open with scroll list) -->
			<?php if ( $colors && ! is_wp_error( $colors ) ) : ?>
			<details open class="f-filter-group" data-filter-name="color">
				<summary>
					<span><?php echo esc_html( furniva_t( 'COLOR & FINISH', 'MÀU SẮC & VÂN GỖ' ) ); ?></span>
					<span class="f-group-meta"><?php echo count( $colors ); ?></span>
				</summary>
				<div class="f-filter-body">
					<div class="f-filter-scroll-list" tabindex="0" role="region" aria-label="<?php echo esc_attr( furniva_t( 'Color finishes list', 'Danh sách màu sắc và vân gỗ' ) ); ?>">
						<?php foreach ( $colors as $t ) :
							$color_key = ! empty( $t->slug ) ? $t->slug : $t->name;
							$hex = furniva_color_hex( $color_key );
							$is_light = in_array( strtolower( $hex ), array( '#ffffff', '#fff', '#f8f6f0', '#f5f5f5' ) );
						?>
							<label class="f-filter-check f-filter-check--color" title="<?php echo esc_attr( furniva_term_label( $t->name, 'pa_color' ) ); ?>">
								<input type="checkbox" name="color" value="<?php echo esc_attr( $t->slug ?: $t->name ); ?>" data-name="<?php echo esc_attr( furniva_term_label( $t->name, 'pa_color' ) ); ?>" />
								<span class="f-filter-color-dot" style="background:<?php echo esc_attr( $hex ); ?>; border: 1px solid <?php echo $is_light ? '#d1d5db' : 'rgba(0,0,0,0.12)'; ?>;"></span>
								<span class="f-filter-color-name"><?php echo esc_html( furniva_term_label( $t->name, 'pa_color' ) ); ?></span>
								<span class="f-count">(<?php echo (int) $t->count; ?>)</span>
							</label>
						<?php endforeach; ?>
					</div>
				</div>
			</details>
			<?php endif; ?>

			<!-- Size / dimensions -->
			<?php if ( $sizes && ! is_wp_error( $sizes ) ) : ?>
			<details class="f-filter-group" data-filter-name="size">
				<summary>
					<span><?php echo esc_html( furniva_t( 'SIZE', 'KÍCH THƯỚC' ) ); ?></span>
					<span class="f-group-meta"><?php echo count( $sizes ); ?></span>
				</summary>
				<div class="f-filter-body">
					<?php foreach ( $sizes as $t ) : ?>
						<label class="f-filter-check">
							<input type="checkbox" name="size" value="<?php echo esc_attr( $t->slug ); ?>" data-name="<?php echo esc_attr( furniva_term_label( $t->name, 'pa_size' ) ); ?>" />
							<span><?php echo esc_html( furniva_term_label( $t->name, 'pa_size' ) ); ?></span>
							<span class="f-count">(<?php echo (int) $t->count; ?>)</span>
						</label>
					<?php endforeach; ?>
				</div>
			</details>
			<?php endif; ?>

			<!-- Price (Collapsed by default, styled with slider) -->
			<details class="f-filter-group" data-filter-name="price">
				<summary>
					<span><?php echo esc_html( furniva_t( 'PRICE', 'GIÁ TIỀN' ) ); ?></span>
				</summary>
				<div class="f-filter-body">
					<div class="f-price-slider-box">
						<div class="f-price-labels-row">
							<span class="f-price-lbl" id="f-price-min-val">$0</span>
							<span class="f-price-lbl" id="f-price-max-val">$2,000</span>
						</div>
						<div class="f-price-slider-track">
							<div class="f-price-slider-fill"></div>
							<div class="f-price-thumb f-price-thumb-left"></div>
							<div class="f-price-thumb f-price-thumb-right"></div>
						</div>
						<input type="hidden" name="price_min" id="f-price-min" value="" />
						<input type="hidden" name="price_max" id="f-price-max" value="" />
					</div>
				</div>
			</details>

			<!-- Availability (Collapsed by default) -->
			<details class="f-filter-group" data-filter-name="availability">
				<summary>
					<span><?php echo esc_html( furniva_t( 'AVAILABILITY', 'TÌNH TRẠNG' ) ); ?></span>
				</summary>
				<div class="f-filter-body">
					<label class="f-filter-check">
						<input type="checkbox" name="availability" value="instock" data-name="<?php echo esc_attr( furniva_t( 'In stock', 'Còn hàng' ) ); ?>" />
						<span><?php echo esc_html( furniva_t( 'In stock', 'Còn hàng' ) ); ?></span>
						<span class="f-count">(40)</span>
					</label>
				</div>
			</details>

			<div class="f-filter-foot">
				<button type="button" class="f-filter-apply" id="furniva-filter-apply"><?php echo esc_html( furniva_t( 'Apply', 'Áp dụng' ) ); ?></button>
				<button type="button" class="f-filter-clear" id="furniva-filter-clear"><?php echo esc_html( furniva_t( 'Clear All', 'Xóa tất cả' ) ); ?></button>
			</div>
		</form>
	</aside>
	<div class="f-filters-backdrop" id="furniva-filters-backdrop"></div>
	<?php
}

function furniva_color_hex( $name_or_slug ) {
	$map = array(
		'wenge'                   => '#362b24', 'nâu wenge'                 => '#362b24',
		'sonoma-oak'              => '#d2b591', 'sonoma oak'                => '#d2b591', 'gỗ sồi sonoma'         => '#d2b591',
		'columbia-walnut'         => '#6f4e37', 'columbia walnut'           => '#6f4e37', 'gỗ óc chó columbia'     => '#6f4e37',
		'lome-cherry'             => '#8d4a3b', 'lome cherry'               => '#8d4a3b', 'gỗ anh đào lome cherry' => '#8d4a3b',
		'mocha-walnut'            => '#4d3324', 'mocha walnut'              => '#4d3324', 'gỗ óc chó mocha'        => '#4d3324',
		'giant-wood'              => '#a67c52', 'giant wood'                => '#a67c52', 'gỗ giant wood'         => '#a67c52',
		'sonoma-oak-white'        => '#e6d8c3', 'sonoma oak & white'        => '#e6d8c3', 'gỗ sồi sonoma & trắng' => '#e6d8c3',
		'yellow-brown'            => '#d4a359', 'yellow & brown'            => '#d4a359', 'vàng & nâu'             => '#d4a359', 'vàng & nâu two-tone' => '#d4a359',
		'linear'                  => '#bda17e', 'vân gỗ linear'             => '#bda17e',
		'gray'                    => '#8a8d8f', 'xám'                       => '#8a8d8f', 'xám hiện đại'           => '#8a8d8f',
		'milky-wenge'             => '#4a3b32', 'milky wenge'               => '#4a3b32', 'nâu milky wenge'        => '#4a3b32',
		'rustic-oak-white'        => '#d7c4a7', 'rustic oak & white'        => '#d7c4a7', 'gỗ sồi rustic & trắng'  => '#d7c4a7',
		'rustic-oak'              => '#b59a78', 'rustic oak'                => '#b59a78', 'gỗ sồi rustic'         => '#b59a78',
		'lyon-walnut'             => '#5c4033', 'lyon walnut'               => '#5c4033', 'gỗ óc chó lyon'         => '#5c4033',
		'modern-oak'              => '#c2a382', 'modern oak'                => '#c2a382', 'gỗ sồi modern oak'     => '#c2a382',
		'ice-white'               => '#ffffff', 'ice white'                 => '#ffffff', 'trắng ice white'        => '#ffffff',
		'black'                   => '#222220', 'đen'                       => '#222220',
		'white'                   => '#f8f6f0', 'trắng'                     => '#f8f6f0',
		'brown'                   => '#7a4e31', 'nâu'                       => '#7a4e31',
		'walnut'                  => '#583b28', 'óc chó'                    => '#583b28',
	);
	$key = mb_strtolower( trim( (string) $name_or_slug ) );
	return $map[ $key ] ?? '#a37b58';
}

/* Price range from all published products (cached). */
function furniva_get_price_range_data() {
	$cache = get_transient( 'furniva_price_range' );
	if ( $cache ) {
		return $cache;
	}
	global $wpdb;
	$min = (float) $wpdb->get_var( "SELECT MIN(meta_value+0) FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID=pm.post_id WHERE meta_key='_price' AND p.post_type='product' AND p.post_status='publish'" );
	$max = (float) $wpdb->get_var( "SELECT MAX(meta_value+0) FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID=pm.post_id WHERE meta_key='_price' AND p.post_type='product' AND p.post_status='publish'" );
	$data = array( 'min' => floor( $min ), 'max' => ceil( $max ) );
	set_transient( 'furniva_price_range', $data, HOUR_IN_SECONDS );
	return $data;
}
if ( ! function_exists( 'wc_get_price_range_data' ) ) {
	function wc_get_price_range_data() { return furniva_get_price_range_data(); }
}
add_action( 'save_post_product', function () { delete_transient( 'furniva_price_range' ); } );

/**
 * Render category hover mega menu with hot recommended products.
 */
function furniva_category_mega_dropdown( $cat_slug ) {
	if ( ! function_exists( 'wc_get_products' ) ) {
		return '';
	}
	$term = get_term_by( 'slug', $cat_slug, 'product_cat' );
	if ( ! $term || is_wp_error( $term ) ) {
		return '';
	}

	$products = wc_get_products( array(
		'status'   => 'publish',
		'category' => array( $cat_slug ),
		'limit'    => 3,
		'orderby'  => 'popularity',
		'order'    => 'DESC',
	) );

	if ( empty( $products ) ) {
		return '';
	}

	$cat_url  = get_term_link( $term );
	$cat_name = furniva_term_label( $term->name, 'product_cat' );

	ob_start();
	?>
	<div class="fv-subnav-mega" role="region" aria-label="<?php echo esc_attr( $cat_name ); ?>">
		<div class="fv-subnav-mega__head">
			<div>
				<span class="fv-subnav-mega__tag">✦ <?php echo esc_html( furniva_t( 'Curated Highlights', 'Sản phẩm đề xuất hot' ) ); ?></span>
				<h4 class="fv-subnav-mega__title"><?php echo esc_html( $cat_name ); ?></h4>
			</div>
			<a class="fv-subnav-mega__more" href="<?php echo esc_url( $cat_url ); ?>">
				<span><?php echo esc_html( furniva_t( 'View collection', 'Xem tất cả' ) ); ?></span>
				<span aria-hidden="true">→</span>
			</a>
		</div>
		<div class="fv-subnav-mega__grid">
			<?php 
			$idx = 0;
			foreach ( $products as $product ) :
				$product_id = $product->get_id();
				$image_url  = wp_get_attachment_image_url( $product->get_image_id(), 'woocommerce_thumbnail' ) ?: wc_placeholder_img_src();
				$title      = furniva_translate_product_title( $product->get_name(), $product );
				$price_html = $product->get_price_html();
				$is_badge   = ( 0 === $idx ) && ( $product->is_featured() || $product->get_total_sales() > 0 );
			?>
				<a class="fv-mega-product-card" href="<?php echo esc_url( get_permalink( $product_id ) ); ?>">
					<div class="fv-mega-product-card__thumb">
						<img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $title ); ?>" loading="lazy" />
						<?php if ( $is_badge ) : ?>
							<span class="fv-mega-product-card__badge"><?php echo esc_html( furniva_t( 'Top Pick', 'Bán chạy' ) ); ?></span>
						<?php endif; ?>
					</div>
					<div class="fv-mega-product-card__info">
						<strong class="fv-mega-product-card__name"><?php echo esc_html( $title ); ?></strong>
						<span class="fv-mega-product-card__price"><?php echo wp_kses_post( $price_html ); ?></span>
					</div>
				</a>
			<?php 
				$idx++;
			endforeach; ?>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

