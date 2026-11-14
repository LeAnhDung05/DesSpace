<?php
/**
 * Furniva storefront header adapted from the local visual reference.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$shop_id    = wc_get_page_id( 'shop' );
$shop_url   = $shop_id > 0 ? get_permalink( $shop_id ) : home_url( '/shop/' );
$blog_id    = (int) get_option( 'page_for_posts' );
$blog_url   = $blog_id ? get_permalink( $blog_id ) : home_url( '/blog/' );
$account_url = furniva_wc_active() ? get_permalink( wc_get_page_id( 'myaccount' ) ) : home_url( '/my-account/' );
$wishlist_url = function_exists( 'YITH_WCWL' ) ? YITH_WCWL()->get_wishlist_url() : home_url( '/wishlist/' );
$wishlist_count = 0;
if ( function_exists( 'yith_wcwl_count_all_products' ) ) {
	$wishlist_count = yith_wcwl_count_all_products();
} elseif ( function_exists( 'yith_wcwl_count_products' ) ) {
	$wishlist_count = yith_wcwl_count_products();
} elseif ( function_exists( 'YITH_WCWL' ) ) {
	$wishlist_count = count( YITH_WCWL()->get_products() );
}
$logo_id = get_theme_mod( 'custom_logo' );
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<link rel="icon" type="image/png" href="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/images/axgnud-app-icon-dark.png' ); ?>" />
<link rel="apple-touch-icon" href="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/images/axgnud-app-icon-dark.png' ); ?>" />
<?php wp_head(); ?>
</head>
<body <?php body_class( 'furniva-site' ); ?>>
<?php wp_body_open(); ?>

<?php
$announcements = array(
	array(
		'text'    => furniva_t( 'Welcome offer: 10% off with code WELCOME10', 'Ưu đãi chào mừng: giảm 10% với mã WELCOME10' ),
		'title'   => furniva_t( 'Welcome Offer: 10% Off Your First Order', 'Ưu đãi chào mừng: Giảm 10% đơn hàng đầu tiên' ),
		'content' => furniva_t( 'Enter promotional code WELCOME10 at checkout to enjoy 10% off your entire first luxury furniture order at DeSpace.', 'Sử dụng mã ưu đãi WELCOME10 khi thanh toán để được giảm ngay 10% cho toàn bộ sản phẩm trong đơn hàng đầu tiên tại DeSpace.' ),
		'action'  => furniva_t( 'Shop Now', 'Mua sắm ngay' ),
		'url'     => home_url( '/shop/' ),
	),
	array(
		'text'    => furniva_t( 'Complimentary delivery on orders over $500', 'Miễn phí giao hàng toàn quốc cho đơn từ $500' ),
		'title'   => furniva_t( 'Complimentary Insured Delivery & Assembly', 'Miễn phí giao hàng & lắp đặt tận nơi' ),
		'content' => furniva_t( 'DeSpace offers 100% complimentary nationwide delivery and white-glove setup on all orders over $500 (or 12,000,000 VND).', 'DeSpace miễn phí 100% chi phí vận chuyển toàn quốc và hỗ trợ lắp đặt chuyên nghiệp đối với mọi đơn hàng từ $500 (hoặc 12.000.000 VNĐ).' ),
		'action'  => furniva_t( 'Explore Collection', 'Khám phá bộ sưu tập' ),
		'url'     => home_url( '/shop/' ),
	),
	array(
		'text'    => furniva_t( 'Thoughtfully selected furniture for modern living', 'Nội thất tinh tuyển cho không gian sống hiện đại' ),
		'title'   => furniva_t( 'Thoughtfully Curated Design Philosophy', 'Triết lý thiết kế tinh tuyển & bền vững' ),
		'content' => furniva_t( 'Every DeSpace piece is thoughtfully crafted from sustainably sourced timbers and moisture-resistant surfaces designed to elevate contemporary spaces.', 'Mỗi sản phẩm tại DeSpace đều được tuyển chọn kỹ lưỡng từ chất liệu gỗ tự nhiên cao cấp, bề mặt phủ melamine chống ẩm, đạt chuẩn thẩm mỹ tinh tế trường tồn.' ),
		'action'  => furniva_t( 'About DeSpace', 'Về DeSpace' ),
		'url'     => home_url( '/ve-despace/' ),
	),
	array(
		'text'    => furniva_t( '5-Year Official Warranty & 30-Day Hassle-Free Returns', 'Bảo hành chính hãng 5 năm & Đổi trả trong 30 ngày' ),
		'title'   => furniva_t( '5-Year Warranty & 30-Day Free Returns', 'Bảo hành chính hãng 5 năm & Đổi trả 30 ngày' ),
		'content' => furniva_t( 'Uncompromised craftsmanship guarantee: 5-year structural warranty with complimentary 30-day exchange for complete peace of mind.', 'Cam kết chất lượng vượt trội: Bảo hành kết cấu 5 năm, 1 đổi 1 trong 30 ngày nếu phát sinh lỗi từ nhà sản xuất.' ),
		'action'  => furniva_t( 'Contact Support', 'Liên hệ hỗ trợ' ),
		'url'     => home_url( '/lien-he/' ),
	),
	array(
		'text'    => furniva_t( 'Personal styling & showroom consultation by AXGNUD', 'Tư vấn không gian sống 1-1 cùng chuyên gia AXGNUD' ),
		'title'   => furniva_t( '1-on-1 Personal Interior Styling Consultation', 'Tư vấn không gian sống 1-1 cùng chuyên gia' ),
		'content' => furniva_t( 'Book a personalized styling session with our interior architects at our showroom (So 20 ngach 21 ngo 367 Ngo Xuan Quan, Gia Lam, Hanoi) or via virtual consultation.', 'Đặt lịch tư vấn trực tiếp 1-1 cùng đội ngũ kiến trúc sư DeSpace tại Showroom (Số 20 ngách 21 ngõ 367 Ngô Xuân Quảng, Gia Lâm, Hà Nội) hoặc qua tư vấn từ xa.' ),
		'action'  => furniva_t( 'Book Consultation', 'Đặt lịch tư vấn' ),
		'url'     => home_url( '/lien-he/' ),
	),
	array(
		'text'    => furniva_t( 'Discover our newest 2026 curated design collection', 'Khám phá bộ sưu tập thiết kế mới nhất 2026 tại showroom' ),
		'title'   => furniva_t( 'Discover DeSpace 2026 Curated Collection', 'Bộ sưu tập DeSpace 2026 Mới Nhất' ),
		'content' => furniva_t( 'Explore our latest releases of smart modular shoe cabinets, wardrobes, and minimalist display bookshelves designed for modern homes.', 'Khám phá các thiết kế tủ giày thông minh, tủ quần áo module và kệ sách cao cấp vừa xuất xưởng năm 2026 tại hệ thống DeSpace.' ),
		'action'  => furniva_t( 'Discover Now', 'Khám phá ngay' ),
		'url'     => home_url( '/shop/' ),
	),
);
?>
<!-- Announcement Dialog -->
<dialog id="furniva-announcement-dialog" class="fv-announcement-dialog">
	<div class="fv-announcement-dialog__inner">
		<button class="fv-announcement-dialog__close" aria-label="<?php echo esc_attr( furniva_t( 'Close', 'Đóng' ) ); ?>">✕</button>
		<div class="fv-announcement-dialog__content"></div>
	</div>
</dialog>

<header class="fv-header" id="top">
	<div class="fv-announcement" role="region" aria-label="<?php echo esc_attr( furniva_t( 'Store announcements', 'Thông báo từ cửa hàng' ) ); ?>">
		<div class="fv-announcement__viewport">
			<div class="fv-announcement__track">
				<div class="fv-announcement__group">
					<?php foreach ( $announcements as $item ) : ?>
						<span class="fv-announcement__item" role="button" tabindex="0"
							data-dialog-title="<?php echo esc_attr( $item['title'] ); ?>"
							data-dialog-content="<?php echo esc_attr( $item['content'] ); ?>"
							data-dialog-action="<?php echo esc_attr( $item['action'] ); ?>"
							data-dialog-url="<?php echo esc_url( $item['url'] ); ?>">
							<?php echo esc_html( $item['text'] ); ?>
						</span>
						<span class="fv-announcement__separator" aria-hidden="true">✦</span>
					<?php endforeach; ?>
				</div>
				<div class="fv-announcement__group" aria-hidden="true">
					<?php foreach ( $announcements as $item ) : ?>
						<span class="fv-announcement__item" role="button" tabindex="-1"
							data-dialog-title="<?php echo esc_attr( $item['title'] ); ?>"
							data-dialog-content="<?php echo esc_attr( $item['content'] ); ?>"
							data-dialog-action="<?php echo esc_attr( $item['action'] ); ?>"
							data-dialog-url="<?php echo esc_url( $item['url'] ); ?>">
							<?php echo esc_html( $item['text'] ); ?>
						</span>
						<span class="fv-announcement__separator">✦</span>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
	</div>
	<div class="fv-header__bar fv-wrap">
		<div class="fv-header__brand-block">
			<button class="fv-icon-button fv-menu-button" id="furniva-burger" type="button" aria-label="<?php echo esc_attr( furniva_t( 'Open menu', 'Mở menu' ) ); ?>" aria-controls="furniva-mobile-nav" aria-expanded="false">
				<?php echo furniva_svg( 'burger' ); ?>
			</button>
			<a class="fv-header__logo" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="AXGNUD — <?php echo esc_attr( furniva_t( 'Home', 'Trang chủ' ) ); ?>">
				<?php if ( $logo_id ) : ?>
					<?php echo wp_get_attachment_image( $logo_id, 'full', false, array( 'alt' => 'AXGNUD' ) ); ?>
				<?php else : ?>
					<img class="fv-brand-logo-img" src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/images/axgnud-logo-header.png' ); ?>" alt="AXGNUD - Luxury Living, Lasting Value" width="160" height="110" />
				<?php endif; ?>
			</a>
		</div>

		<nav class="fv-primary-nav" aria-label="<?php echo esc_attr( furniva_t( 'Primary navigation', 'Điều hướng chính' ) ); ?>">
			<a <?php echo is_front_page() ? 'aria-current="page"' : ''; ?> href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo esc_html( furniva_t( 'Home', 'Trang chủ' ) ); ?></a>
			<a <?php echo ( is_shop() || is_product_taxonomy() ) ? 'aria-current="page"' : ''; ?> href="<?php echo esc_url( $shop_url ); ?>"><?php echo esc_html( furniva_t( 'Shop', 'Cửa hàng' ) ); ?></a>
			<a <?php echo is_home() || is_singular( 'post' ) ? 'aria-current="page"' : ''; ?> href="<?php echo esc_url( $blog_url ); ?>"><?php echo esc_html( furniva_t( 'Journal', 'Tạp chí' ) ); ?></a>
			<a <?php echo is_page( 'about' ) ? 'aria-current="page"' : ''; ?> href="<?php echo esc_url( home_url( '/about/' ) ); ?>"><?php echo esc_html( furniva_t( 'About', 'Giới thiệu' ) ); ?></a>
			<a <?php echo is_page( 'contact' ) ? 'aria-current="page"' : ''; ?> href="<?php echo esc_url( home_url( '/contact/' ) ); ?>"><?php echo esc_html( furniva_t( 'Contact', 'Liên hệ' ) ); ?></a>
		</nav>

		<div class="fv-header__actions">
			<div class="fv-header-language" aria-label="<?php echo esc_attr( furniva_t( 'Language', 'Ngôn ngữ' ) ); ?>"><?php echo furniva_language_switcher_html(); ?></div>
			<button class="fv-icon-button" type="button" data-furniva-search-open aria-label="<?php echo esc_attr( furniva_t( 'Search products', 'Tìm kiếm sản phẩm' ) ); ?>">
				<?php echo furniva_svg( 'search' ); ?>
				<span class="screen-reader-text sr-only"><?php echo esc_html( furniva_t( 'Search products', 'Tìm kiếm sản phẩm' ) ); ?></span>
			</button>
			<a class="fv-icon-button fv-desktop-icon" href="<?php echo esc_url( $wishlist_url ); ?>" aria-label="<?php echo esc_attr( furniva_t( 'Wishlist', 'Danh sách yêu thích' ) ); ?>">
				<?php echo furniva_svg( 'heart' ); ?><span class="fv-action-count fv-wishlist-count" <?php echo ( $wishlist_count > 0 ) ? '' : 'style="display:none;"'; ?>><?php echo esc_html( $wishlist_count ); ?></span>
				<span class="screen-reader-text sr-only"><?php echo esc_html( furniva_t( 'Wishlist', 'Danh sách yêu thích' ) ); ?></span>
			</a>
			<a class="fv-icon-button fv-desktop-icon" href="<?php echo esc_url( $account_url ); ?>" aria-label="<?php echo esc_attr( furniva_t( 'My account', 'Tài khoản của tôi' ) ); ?>">
				<?php echo furniva_svg( 'user' ); ?>
				<span class="screen-reader-text sr-only"><?php echo esc_html( furniva_t( 'My account', 'Tài khoản của tôi' ) ); ?></span>
			</a>
			<?php if ( furniva_wc_active() ) : ?>
				<?php furniva_cart_icon_html(); ?>
			<?php endif; ?>
		</div>

		<!-- Expandable Header Search Bar -->
		<div class="fv-header__search-bar" id="fv-header-search-bar" aria-hidden="true">
			<div class="fv-search-bar__inner">
				<span class="fv-search-bar__icon" aria-hidden="true"><?php echo furniva_svg( 'search' ); ?></span>
				<form class="fv-search-bar__form" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
					<input type="hidden" name="post_type" value="product" />
					<input type="search" class="fv-search-bar__input" name="s" placeholder="<?php echo esc_attr( furniva_t( 'Search products, collections, materials…', 'Tìm kiếm sản phẩm, bộ sưu tập, chất liệu…' ) ); ?>" autocomplete="off" />
				</form>
				<div class="fv-search-bar__quick-tags">
					<span class="fv-quick-tag-label"><?php echo esc_html( furniva_t( 'Hot:', 'Gợi ý:' ) ); ?></span>
					<a href="<?php echo esc_url( home_url( '/?s=sofa&post_type=product' ) ); ?>">Sofa</a>
					<a href="<?php echo esc_url( home_url( '/?s=' . rawurlencode( furniva_t( 'chair', 'ghế' ) ) . '&post_type=product' ) ); ?>"><?php echo esc_html( furniva_t( 'Chairs', 'Ghế' ) ); ?></a>
					<a href="<?php echo esc_url( home_url( '/?s=' . rawurlencode( furniva_t( 'table', 'bàn' ) ) . '&post_type=product' ) ); ?>"><?php echo esc_html( furniva_t( 'Tables', 'Bàn trà' ) ); ?></a>
					<a href="<?php echo esc_url( home_url( '/?s=' . rawurlencode( furniva_t( 'lamp', 'đèn' ) ) . '&post_type=product' ) ); ?>"><?php echo esc_html( furniva_t( 'Lighting', 'Đèn' ) ); ?></a>
					<a href="<?php echo esc_url( home_url( '/?s=' . rawurlencode( furniva_t( 'bed', 'giường' ) ) . '&post_type=product' ) ); ?>"><?php echo esc_html( furniva_t( 'Beds', 'Giường' ) ); ?></a>
				</div>
				<button class="fv-icon-button fv-search-bar__close" type="button" data-furniva-search-close aria-label="<?php echo esc_attr( furniva_t( 'Close search', 'Đóng tìm kiếm' ) ); ?>">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
				</button>
			</div>
		</div>
	</div>

	<!-- Category Subnavigation Row with Hover Recommended Products (Dòng dưới danh mục sản phẩm) -->
	<nav class="fv-header__subnav" aria-label="<?php echo esc_attr( furniva_t( 'Product categories', 'Danh mục sản phẩm' ) ); ?>">
		<div class="fv-wrap fv-header__subnav-inner">
			<div class="fv-header__subnav-cats">
				<a class="fv-subnav-link fv-subnav-link--all <?php echo is_shop() ? 'is-active' : ''; ?>" href="<?php echo esc_url( $shop_url ); ?>">
					<?php echo furniva_svg( 'grid' ); ?>
					<span><?php echo esc_html( furniva_t( 'All Collections', 'Tất cả sản phẩm' ) ); ?></span>
				</a>
				<?php
				$subnav_cats = get_terms( array(
					'taxonomy'   => 'product_cat',
					'hide_empty' => true,
					'parent'     => 0,
					'exclude'    => array( wc_get_page_id( 'shop' ) ),
				) );
				if ( ! empty( $subnav_cats ) && ! is_wp_error( $subnav_cats ) ) :
					foreach ( $subnav_cats as $scat ) :
						if ( in_array( $scat->slug, array( 'uncategorized', 'khac' ), true ) ) continue;
						$is_cat_active = is_product_category( $scat->slug );
						$cat_display   = furniva_term_label( $scat->name, 'product_cat' );
				?>
				<div class="fv-subnav-item fv-subnav-item--has-mega">
					<a class="fv-subnav-link <?php echo $is_cat_active ? 'is-active' : ''; ?>" href="<?php echo esc_url( get_term_link( $scat ) ); ?>">
						<span><?php echo esc_html( $cat_display ); ?></span>
						<svg class="fv-subnav-caret" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"></polyline></svg>
					</a>
					<?php echo furniva_category_mega_dropdown( $scat->slug ); ?>
				</div>
				<?php
					endforeach;
				endif;
				?>
				<span class="fv-subnav-divider" aria-hidden="true"></span>
				<a class="fv-subnav-link fv-subnav-link--highlight" href="<?php echo esc_url( home_url( '/shop/?on_sale=1' ) ); ?>">
					<span class="fv-subnav-badge">✦ <?php echo esc_html( furniva_t( 'Special Offers', 'Ưu đãi đặc biệt' ) ); ?></span>
				</a>
			</div>
		</div>
	</nav>

	<div class="f-mobile-nav" id="furniva-mobile-nav">
		<ul>
			<li class="f-mobile-language"><?php echo furniva_language_switcher_html(); ?></li>
			<li><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo esc_html( furniva_t( 'Home', 'Trang chủ' ) ); ?></a></li>
			<li><a href="<?php echo esc_url( $shop_url ); ?>"><?php echo esc_html( furniva_t( 'Shop all', 'Xem tất cả sản phẩm' ) ); ?></a></li>
			<?php
			$mobile_categories = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => true, 'parent' => 0 ) );
			if ( ! is_wp_error( $mobile_categories ) && $mobile_categories ) :
				foreach ( $mobile_categories as $category ) :
					if ( 'uncategorized' === $category->slug ) continue;
				?>
				<li><a href="<?php echo esc_url( get_term_link( $category ) ); ?>"><?php echo esc_html( furniva_term_label( $category->name, 'product_cat' ) ); ?></a></li>
				<?php endforeach; ?>
			<?php endif; ?>
			<li><a href="<?php echo esc_url( $blog_url ); ?>"><?php echo esc_html( furniva_t( 'Journal', 'Tạp chí' ) ); ?></a></li>
			<li><a href="<?php echo esc_url( home_url( '/about/' ) ); ?>"><?php echo esc_html( furniva_t( 'About', 'Giới thiệu' ) ); ?></a></li>
			<li><a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>"><?php echo esc_html( furniva_t( 'Contact', 'Liên hệ' ) ); ?></a></li>
		</ul>
	</div>
</header>

<dialog class="fv-dialog fv-search-dialog" id="furniva-search-dialog" aria-labelledby="furniva-search-title">
	<div class="fv-dialog__head">
		<div><span class="fv-eyebrow"><?php echo esc_html( furniva_t( 'Search the collection', 'Tìm trong bộ sưu tập' ) ); ?></span><h2 id="furniva-search-title"><?php echo esc_html( furniva_t( 'What are you looking for?', 'Bạn đang tìm sản phẩm nào?' ) ); ?></h2></div>
		<button class="fv-icon-button fv-search-dialog__close" type="button" data-furniva-search-close aria-label="<?php echo esc_attr( furniva_t( 'Close search', 'Đóng tìm kiếm' ) ); ?>">×</button>
	</div>
	<div class="f-header-search"><?php echo do_shortcode( '[fibosearch]' ); ?></div>
</dialog>

<?php if ( ! is_front_page() ) : ?><main id="content"><?php endif; ?>
