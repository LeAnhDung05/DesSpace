<?php
/**
 * The template for displaying 404 pages (not found) in DeSpace Child.
 *
 * @package DeSpace_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header(); ?>

<main id="content" class="site-main ax-404-page">
	<div class="fv-wrap ax-404-container">
		<div class="ax-404-content">
			<span class="ax-404-badge">404 ERROR</span>
			<h1 class="ax-404-title"><?php echo esc_html( furniva_t( "This page doesn't seem to exist.", 'Trang này không tồn tại.' ) ); ?></h1>
			<p class="ax-404-subtitle">
				<?php echo esc_html( furniva_t( "It looks like the link pointing here was faulty. Maybe try searching for our collection?", 'Có vẻ như liên kết trỏ đến đây đã bị lỗi hoặc đã chuyển hướng. Bạn có thể thử tìm kiếm sản phẩm bên dưới.' ) ); ?>
			</p>

			<div class="ax-404-search-box">
				<form role="search" method="get" class="search-form ax-404-search-form" action="<?php echo esc_url( home_url( '/' ) ); ?>">
					<label for="ax-404-search-input">
						<span class="screen-reader-text"><?php echo esc_html( furniva_t( 'Search:', 'Tìm kiếm:' ) ); ?></span>
						<input type="search" id="ax-404-search-input" class="search-field ax-404-search-input" placeholder="<?php echo esc_attr( furniva_t( 'Search furniture, lighting, decor…', 'Tìm kiếm sofa, bàn ghế, đèn trang trí…' ) ); ?>" value="" name="s" autocomplete="off" />
						<button type="submit" class="search-submit ast-search-submit ax-404-search-submit" aria-label="<?php echo esc_attr( furniva_t( 'Search', 'Tìm kiếm' ) ); ?>">
							<span hidden><?php echo esc_html( furniva_t( 'Search', 'Tìm kiếm' ) ); ?></span>
							<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
								<circle cx="11" cy="11" r="8"></circle>
								<line x1="21" y1="21" x2="16.65" y2="16.65"></line>
							</svg>
						</button>
					</label>
				</form>
			</div>

			<div class="ax-404-actions">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="ax-btn ax-btn-primary">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
						<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
						<polyline points="9 22 9 12 15 12 15 22"/>
					</svg>
					<span><?php echo esc_html( furniva_t( 'Back to Home', 'Về trang chủ' ) ); ?></span>
				</a>
				<a href="<?php echo esc_url( function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop/' ) ); ?>" class="ax-btn ax-btn-outline">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
						<circle cx="9" cy="21" r="1.6"/>
						<circle cx="19" cy="21" r="1.6"/>
						<path d="M2.5 3h2l2.4 12.4a2 2 0 0 0 2 1.6h9.7a2 2 0 0 0 2-1.6l1.4-8H6"/>
					</svg>
					<span><?php echo esc_html( furniva_t( 'Explore Collection', 'Khám phá bộ sưu tập' ) ); ?></span>
				</a>
			</div>
		</div>
	</div>
</main>

<?php get_footer(); ?>
