<?php
/**
 * Furniva WooCommerce wrapper — custom shop layout with filter sidebar.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

get_header();

$uploads = wp_get_upload_dir();

if ( is_singular( 'product' ) ) {

	woocommerce_content();

} elseif ( is_shop() || is_product_taxonomy() ) {

	// Page head & categories for quick navigation
	$title              = woocommerce_page_title( false );
	$current_cat_id     = is_product_category() ? get_queried_object_id() : 0;
	$shop_url           = wc_get_page_permalink( 'shop' );
	$total_all_products = wp_count_posts( 'product' )->publish ?? 0;

	$top_cats = get_terms( array(
		'taxonomy'   => 'product_cat',
		'hide_empty' => true,
		'parent'     => 0,
		'exclude'    => array( wc_get_page_id( 'shop' ) ),
	) );
	?>
	<?php
	// Determine curated hero image for Shop / Category
	$hero_image_url = '';
	if ( is_product_category() ) {
		$thumbnail_id = get_term_meta( $current_cat_id, 'thumbnail_id', true );
		if ( $thumbnail_id ) {
			$hero_image_url = wp_get_attachment_image_url( $thumbnail_id, 'large' );
		}
	}
	if ( empty( $hero_image_url ) ) {
		$hero_image_url = get_stylesheet_directory_uri() . '/assets/images/hero-scandinavian.jpg';
	}
	?>
	<section class="f-shop-hero <?php echo is_shop() ? 'f-shop-hero--no-split' : ''; ?> fv-reveal fv-reveal-up" aria-label="<?php echo esc_attr( furniva_t( 'Shop Catalogue Header', 'Tiêu đề danh mục cửa hàng' ) ); ?>">
		<div class="f-wrap">
			<?php if ( ! is_shop() ) : ?>
			<div class="f-shop-hero-split">
				<!-- Left: 40% Editorial Copy -->
				<div class="f-shop-hero-left">
					<div class="f-breadcrumbs">
						<?php woocommerce_breadcrumb( array( 'delimiter' => '<span class="sep">/</span>', 'wrap_before' => '<nav class="f-crumb-nav" aria-label="' . esc_attr( furniva_t( 'Breadcrumb', 'Điều hướng' ) ) . '">', 'wrap_after' => '</nav>' ) ); ?>
					</div>
					<div class="f-hero-eyebrow-row">
						<span class="f-shop-eyebrow"><?php echo esc_html( furniva_t( 'OUR COLLECTION', 'BỘ SƯU TẬP CỦA CHÚNG TÔI' ) ); ?></span>
						<span class="f-hero-eyebrow-line"></span>
					</div>
					<h1 class="f-shop-title"><?php echo esc_html( $title ?: 'Shop' ); ?></h1>
					<p class="f-shop-lede">
						<?php
						if ( is_product_category() && term_description() ) {
							echo wp_kses_post( wp_strip_all_tags( term_description() ) );
						} else {
							echo esc_html( furniva_t( 'Timeless Scandinavian furniture crafted from sustainable solid woods, tactile linen, and mouth-blown glass.', 'Nội thất Bắc Âu vượt thời gian, chế tác từ gỗ tự nhiên bền vững, vải linen giàu xúc cảm và thủy tinh thổi thủ công.' ) );
						}
						?>
					</p>
				</div>

				<!-- Right: 60% Lifestyle Image -->
				<div class="f-shop-hero-right">
					<div class="f-shop-hero-media">
						<img src="<?php echo esc_url( $hero_image_url ); ?>" alt="<?php echo esc_attr( $title ?: 'AXGNUD Living' ); ?>" class="f-shop-hero-img" loading="eager" />
						
						<!-- Floating Trust Card matching user mockup -->
						<div class="f-hero-trust-card">
							<div class="f-trust-card-item">
								<svg class="f-trust-card-icon" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
									<path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 2 8 0 5.5-4.78 10-10 10Z"></path>
									<path d="M2 21c0-3 1.85-5.36 5.08-6C9.5 14.52 12 13 13 12"></path>
								</svg>
								<div class="f-trust-card-text">
									<strong class="f-trust-card-val">100%</strong>
									<span class="f-trust-card-lbl"><?php echo esc_html( furniva_t( 'SUSTAINABLE SOLID WOOD', 'GỖ TỰ NHIÊN BỀN VỮNG' ) ); ?></span>
								</div>
							</div>
							<div class="f-trust-card-item">
								<svg class="f-trust-card-icon" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
									<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
									<path d="m9 12 2 2 4-4"></path>
								</svg>
								<div class="f-trust-card-text">
									<strong class="f-trust-card-val"><?php echo esc_html( furniva_t( '5 Yr', '5 năm' ) ); ?></strong>
									<span class="f-trust-card-lbl"><?php echo esc_html( furniva_t( 'CRAFT WARRANTY', 'BẢO HÀNH CHẾ TÁC' ) ); ?></span>
								</div>
							</div>
							<div class="f-trust-card-item">
								<svg class="f-trust-card-icon" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
									<rect x="1" y="3" width="15" height="13" rx="2"></rect>
									<polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon>
									<circle cx="5.5" cy="18.5" r="2.5"></circle>
									<circle cx="18.5" cy="18.5" r="2.5"></circle>
								</svg>
								<div class="f-trust-card-text">
									<strong class="f-trust-card-val"><?php echo esc_html( furniva_t( 'FREE DELIVERY', 'GIAO HÀNG MIỄN PHÍ' ) ); ?></strong>
									<span class="f-trust-card-lbl"><?php echo esc_html( furniva_t( 'ON ORDERS OVER $500', 'CHO ĐƠN TRÊN $500' ) ); ?></span>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
			<?php else : ?>
			<h1 class="screen-reader-text"><?php echo esc_html( $title ?: 'Shop' ); ?></h1>
			<?php endif; ?>

			<!-- Editorial Catalogue Navigation -->
			<?php if ( ! empty( $top_cats ) && ! is_wp_error( $top_cats ) ) : ?>
			<nav class="f-shop-cat-nav" aria-label="<?php echo esc_attr( furniva_t( 'Browse by category', 'Khám phá theo danh mục' ) ); ?>">
				<div class="f-shop-cat-heading">
					<span class="f-shop-cat-kicker"><?php echo esc_html( furniva_t( 'BROWSE BY TYPE', 'KHÁM PHÁ THEO DANH MỤC' ) ); ?></span>
					<h2><?php echo esc_html( furniva_t( 'Find the piece for your space', 'Tìm món đồ phù hợp với không gian của bạn' ) ); ?></h2>
				</div>
				<ul class="f-shop-cat-list">
					<?php $all_is_active = is_shop() && empty( $_GET['category'] ); ?>
					<li class="f-shop-cat-item <?php echo $all_is_active ? 'is-active' : ''; ?>">
						<a href="<?php echo esc_url( $shop_url ); ?>" class="f-shop-cat-link" <?php echo $all_is_active ? 'aria-current="page"' : ''; ?>>
							<span class="f-cat-image f-cat-image--all" aria-hidden="true">
								<svg viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="1.25" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="5" width="8" height="8" rx="1"></rect><rect x="19" y="5" width="8" height="8" rx="1"></rect><rect x="5" y="19" width="8" height="8" rx="1"></rect><rect x="19" y="19" width="8" height="8" rx="1"></rect></svg>
							</span>
							<span class="f-cat-copy">
								<span class="f-cat-name"><?php echo esc_html( furniva_t( 'All pieces', 'Tất cả sản phẩm' ) ); ?></span>
								<span class="f-cat-count"><?php echo esc_html( sprintf( furniva_t( '%d pieces', '%d sản phẩm' ), (int) $total_all_products ) ); ?></span>
							</span>
							<span class="f-cat-arrow" aria-hidden="true">↗</span>
						</a>
					</li>
					<?php foreach ( $top_cats as $cat ) :
						$cat_link = get_term_link( $cat );
						$is_active = ( $current_cat_id === $cat->term_id );
						$cat_thumb_id = absint( get_term_meta( $cat->term_id, 'thumbnail_id', true ) );
					?>
					<li class="f-shop-cat-item <?php echo $is_active ? 'is-active' : ''; ?>">
						<a href="<?php echo esc_url( $cat_link ); ?>" class="f-shop-cat-link" <?php echo $is_active ? 'aria-current="page"' : ''; ?>>
							<span class="f-cat-image" aria-hidden="true">
								<?php if ( $cat_thumb_id ) : ?>
									<?php echo wp_get_attachment_image( $cat_thumb_id, 'thumbnail', false, array( 'class' => 'f-cat-thumb-image', 'alt' => '', 'loading' => 'lazy', 'decoding' => 'async' ) ); ?>
								<?php else : ?>
									<span class="f-cat-image-fallback"><?php echo esc_html( mb_substr( furniva_term_label( $cat->name, 'product_cat' ), 0, 1 ) ); ?></span>
								<?php endif; ?>
							</span>
							<span class="f-cat-copy">
								<span class="f-cat-name"><?php echo esc_html( furniva_term_label( $cat->name, 'product_cat' ) ); ?></span>
								<span class="f-cat-count"><?php echo esc_html( sprintf( furniva_t( '%d pieces', '%d sản phẩm' ), (int) $cat->count ) ); ?></span>
							</span>
							<span class="f-cat-arrow" aria-hidden="true">↗</span>
						</a>
					</li>
					<?php endforeach; ?>
				</ul>
			</nav>
			<?php endif; ?>
		</div>
	</section>

	<div class="f-wrap">
		<div class="f-shop-layout">
			<?php furniva_filter_sidebar(); ?>

			<div class="f-shop-main">
				<div class="f-shop-head">
					<div class="f-shop-head-left">
						<button type="button" class="f-filter-toggle" id="furniva-filter-toggle" aria-label="<?php echo esc_attr( furniva_t( 'Open filter sidebar', 'Mở bộ lọc sản phẩm' ) ); ?>">
							<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="4" y1="21" x2="4" y2="14"></line><line x1="4" y1="10" x2="4" y2="3"></line><line x1="12" y1="21" x2="12" y2="12"></line><line x1="12" y1="8" x2="12" y2="3"></line><line x1="20" y1="21" x2="20" y2="16"></line><line x1="20" y1="12" x2="20" y2="3"></line><line x1="1" y1="14" x2="7" y2="14"></line><line x1="9" y1="8" x2="15" y2="8"></line><line x1="17" y1="16" x2="23" y2="16"></line></svg>
							<span><?php echo esc_html( furniva_t( 'Filter', 'Lọc' ) ); ?></span>
							<span class="f-filter-active-count" id="furniva-filter-badge" style="display:none">0</span>
						</button>
						<span class="f-result-count" id="furniva-result-count">
							<?php
							$total = (int) ( $GLOBALS['wp_query']->found_posts ?? 0 );
							printf( wp_kses_post( furniva_t( '<strong>%d</strong> products found', '<strong>%d</strong> sản phẩm được tìm thấy' ) ), $total );
							?>
						</span>
					</div>

					<div class="f-shop-head-right">
						<div class="f-grid-switcher" role="group" aria-label="<?php echo esc_attr( furniva_t( 'Product grid layout', 'Bố cục lưới sản phẩm' ) ); ?>">
							<button type="button" class="f-grid-btn" data-cols="3" title="<?php echo esc_attr( furniva_t( '3 Columns View', 'Xem 3 cột' ) ); ?>" aria-label="3 columns">
								<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
									<rect x="2" y="3" width="5.3" height="18" rx="1.2"></rect>
									<rect x="9.3" y="3" width="5.3" height="18" rx="1.2"></rect>
									<rect x="16.7" y="3" width="5.3" height="18" rx="1.2"></rect>
								</svg>
							</button>
							<button type="button" class="f-grid-btn is-active" data-cols="4" title="<?php echo esc_attr( furniva_t( '4 Columns View', 'Xem 4 cột' ) ); ?>" aria-label="4 columns">
								<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
									<rect x="2" y="3" width="3.7" height="18" rx="0.8"></rect>
									<rect x="7.7" y="3" width="3.7" height="18" rx="0.8"></rect>
									<rect x="13.4" y="3" width="3.7" height="18" rx="0.8"></rect>
									<rect x="19" y="3" width="3.7" height="18" rx="0.8"></rect>
								</svg>
							</button>
						</div>
						<?php woocommerce_catalog_ordering(); ?>
					</div>
				</div>

				<div class="f-active-filters-bar" id="furniva-active-filters" style="display:none">
					<span class="f-active-label"><?php echo esc_html( furniva_t( 'Active:', 'Đang lọc:' ) ); ?></span>
					<div class="f-active-tags-list"></div>
					<button type="button" class="f-clear-all-tag" id="furniva-clear-all-tags"><?php echo esc_html( furniva_t( 'Clear all', 'Xóa tất cả' ) ); ?></button>
				</div>

				<div id="furniva-shop-results">
					<?php
					if ( have_posts() ) {
						echo '<ul class="f-products columns-4">';
						while ( have_posts() ) {
							the_post();
							wc_get_template_part( 'content', 'product' );
						}
						echo '</ul>';
						woocommerce_pagination();
					} else {
						?>
						<div class="f-no-results">
							<div class="f-no-results-icon">
								<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
									<circle cx="11" cy="11" r="8"></circle>
									<line x1="21" y1="21" x2="16.65" y2="16.65"></line>
									<line x1="8" y1="11" x2="14" y2="11"></line>
								</svg>
							</div>
							<h3 class="f-no-results-title"><?php echo esc_html( furniva_t( 'No matching objects found', 'Không tìm thấy sản phẩm phù hợp' ) ); ?></h3>
							<p class="f-no-results-desc"><?php echo esc_html( furniva_t( 'We couldn’t find any items matching your selected criteria. Try adjusting or clearing your filters.', 'Không có sản phẩm nào khớp với tiêu chí bạn chọn. Hãy điều chỉnh hoặc xóa bộ lọc.' ) ); ?></p>
							<button type="button" class="f-btn f-btn--primary f-no-results-reset" id="furniva-no-results-reset"><?php echo esc_html( furniva_t( 'Reset All Filters', 'Đặt lại bộ lọc' ) ); ?></button>
						</div>
						<?php
					}
					?>
				</div>
			</div>
		</div>
	</div>

	<section class="f-shop-guide" aria-labelledby="f-shop-guide-title">
		<div class="f-wrap f-shop-guide-inner">
			<div class="f-shop-guide-intro">
				<span class="f-shop-guide-kicker"><?php echo esc_html( furniva_t( 'BEFORE YOU CHOOSE', 'TRƯỚC KHI BẠN CHỌN' ) ); ?></span>
				<h2 id="f-shop-guide-title"><?php echo esc_html( furniva_t( 'A thoughtful choice starts with a few details.', 'Một lựa chọn phù hợp bắt đầu từ vài chi tiết nhỏ.' ) ); ?></h2>
				<p><?php echo esc_html( furniva_t( 'A little planning helps each piece feel as if it was made for your home.', 'Một chút chuẩn bị giúp món đồ mới hòa hợp tự nhiên với ngôi nhà của bạn.' ) ); ?></p>
				<div class="f-shop-guide-links">
					<a href="<?php echo esc_url( home_url( '/shipping-returns/' ) ); ?>"><?php echo esc_html( furniva_t( 'Shipping & Returns', 'Giao hàng & Đổi trả' ) ); ?><span aria-hidden="true">↗</span></a>
					<a href="<?php echo esc_url( home_url( '/faqs/' ) ); ?>"><?php echo esc_html( furniva_t( 'Visit FAQs', 'Xem câu hỏi thường gặp' ) ); ?><span aria-hidden="true">↗</span></a>
				</div>
			</div>
			<div class="f-shop-guide-questions">
				<details class="f-shop-guide-item" open>
					<summary><span class="f-guide-number">01</span><span><?php echo esc_html( furniva_t( 'How do I check that a piece will fit?', 'Làm sao để biết món đồ có vừa với không gian?' ) ); ?></span></summary>
					<p><?php echo esc_html( furniva_t( 'Measure the space and the access route, then compare both with the dimensions on the product page. Leave room to move around the furniture comfortably.', 'Hãy đo vị trí đặt và lối vận chuyển, sau đó đối chiếu với kích thước trên trang sản phẩm. Chừa đủ khoảng trống để đi lại và sử dụng thoải mái.' ) ); ?></p>
				</details>
				<details class="f-shop-guide-item">
					<summary><span class="f-guide-number">02</span><span><?php echo esc_html( furniva_t( 'How should I choose a color or finish?', 'Nên chọn màu sắc và bề mặt hoàn thiện thế nào?' ) ); ?></span></summary>
					<p><?php echo esc_html( furniva_t( 'Compare the finish swatches with the colors and materials already in your room. Product photos show the character of each piece; the color filter helps narrow the collection.', 'Hãy so màu hoàn thiện với màu sắc và chất liệu sẵn có trong phòng. Ảnh sản phẩm giúp bạn hình dung phong cách; bộ lọc màu sẽ thu hẹp lựa chọn nhanh hơn.' ) ); ?></p>
				</details>
				<details class="f-shop-guide-item">
					<summary><span class="f-guide-number">03</span><span><?php echo esc_html( furniva_t( 'Where can I find delivery and care details?', 'Xem thông tin giao hàng và bảo quản ở đâu?' ) ); ?></span></summary>
					<p><?php echo esc_html( furniva_t( 'Check the product page for item-specific details, then review Shipping & Returns for store policies. Our FAQs cover common questions before and after your order.', 'Trang sản phẩm có thông tin riêng của từng món. Bạn cũng có thể xem chính sách Giao hàng & Đổi trả và mục Câu hỏi thường gặp trước hoặc sau khi đặt hàng.' ) ); ?></p>
				</details>
			</div>
			<div class="f-shop-guide-ornament" aria-hidden="true">
				<svg viewBox="0 0 120 120" fill="none" stroke="currentColor" stroke-width="1.1" stroke-linecap="round" stroke-linejoin="round"><circle cx="60" cy="60" r="46"></circle><path d="M39 79c17-18 27-34 39-48M51 65c-10-1-16-8-17-17 10 0 17 6 17 17ZM63 51c-1-11 4-19 14-23 2 10-3 19-14 23ZM47 72c-10 0-17-5-21-14 10-2 18 3 21 14ZM72 39c2-8 8-13 17-14-1 9-7 14-17 14ZM58 58c9 0 15 5 18 13-9 1-16-4-18-13Z"></path><path d="M30 91h60"></path></svg>
			</div>
		</div>
	</section>

	<?php
} elseif ( is_account_page() ) {

	// My Account luxury layout
	?>
	<div class="ax-account-page-wrap">
		<?php woocommerce_content(); ?>
	</div>
	<?php

} else {

	// Cart, checkout, static WC endpoints — with a consistent page banner
	$title = get_the_title();
	$crumb = $title ?: furniva_t( 'Store', 'Cửa hàng' );
	if ( is_cart() )     { $title = furniva_t( 'Shopping Cart', 'Giỏ hàng' ); $crumb = furniva_t( 'Cart', 'Giỏ hàng' ); }
	if ( is_checkout() ) { $title = furniva_t( 'Checkout', 'Thanh toán' ); $crumb = furniva_t( 'Checkout', 'Thanh toán' ); }
	furniva_page_head( $title ?: 'AXGNUD', $crumb );
	?>
	<div class="f-wrap">
		<?php woocommerce_content(); ?>
	</div>
	<?php
}

get_footer();
