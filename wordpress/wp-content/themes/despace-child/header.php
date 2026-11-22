<?php
/**
 * DeSpace storefront header adapted from the local visual reference.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$shop_id    = wc_get_page_id( 'shop' );
$shop_url     = furniva_url( function_exists( 'despace_safe_wc_page_url' ) ? despace_safe_wc_page_url( 'shop', '/cua-hang/' ) : ( ( $shop_id > 0 && 'publish' === get_post_status( $shop_id ) ) ? get_permalink( $shop_id ) : home_url( '/cua-hang/' ) ) );
$blog_id      = (int) get_option( 'page_for_posts' );
$blog_url     = furniva_url( ( $blog_id > 0 && 'publish' === get_post_status( $blog_id ) ) ? get_permalink( $blog_id ) : home_url( '/blog/' ) );
$account_url  = furniva_url( function_exists( 'despace_safe_wc_page_url' ) ? despace_safe_wc_page_url( 'myaccount', '/tai-khoan/' ) : home_url( '/tai-khoan/' ) );
$wishlist_url = home_url( '/wishlist/' );
if ( function_exists( 'YITH_WCWL' ) ) {
	$wl_raw = YITH_WCWL()->get_wishlist_url();
	if ( is_string( $wl_raw ) && '' !== $wl_raw && false === strpos( $wl_raw, '?page_id=' ) ) {
		$wishlist_url = $wl_raw;
	}
}
$wishlist_url = furniva_url( $wishlist_url );
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
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<?php if ( is_front_page() ) : ?>
<link rel="preload" as="image" href="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/images/hero-scandinavian.jpg' ); ?>" fetchpriority="high" />
<?php endif; ?>
<link rel="icon" type="image/png" href="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/images/despace-favicon.png' ); ?>" />
<link rel="apple-touch-icon" href="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/images/despace-app-icon.png' ); ?>" />
<?php wp_head(); ?>
</head>
<body <?php body_class( 'furniva-site despace-site' ); ?>>
<?php wp_body_open(); ?>

<?php
$free_ship_vnd = function_exists( 'despace_get_free_shipping_threshold' ) ? despace_get_free_shipping_threshold() : 5000000.0;
$free_ship_vi  = number_format( $free_ship_vnd, 0, ',', '.' ) . ' ₫';
$free_ship_en  = number_format( $free_ship_vnd, 0, '.', ',' ) . ' ₫ (≈ $' . number_format( round( $free_ship_vnd / 25000 ), 0 ) . ')';
$default_cat_id = (int) get_option( 'default_product_cat', 0 );
$nav_categories = get_terms( array(
	'taxonomy'   => 'product_cat',
	'hide_empty' => true,
	'parent'     => 0,
	'exclude'    => $default_cat_id ? array( $default_cat_id ) : array(),
) );
if ( is_wp_error( $nav_categories ) || ! is_array( $nav_categories ) ) {
	$nav_categories = array();
}
$nav_categories = array_values( array_filter( $nav_categories, static function ( $term ) {
	return ! in_array( $term->slug, array( 'uncategorized', 'khac' ), true ) && (int) $term->count > 0;
} ) );
$cat_priority = array( 'shoe-racks-storage', 'wardrobes', 'bookshelves', 'kitchen-cabinets', 'chest-of-drawers-sideboards' );
usort( $nav_categories, static function ( $a, $b ) use ( $cat_priority ) {
	$pa = array_search( $a->slug, $cat_priority, true );
	$pb = array_search( $b->slug, $cat_priority, true );
	$pa = false === $pa ? 99 : $pa;
	$pb = false === $pb ? 99 : $pb;
	return $pa === $pb ? ( (int) $b->count <=> (int) $a->count ) : ( $pa <=> $pb );
} );

$seasonal_cfg = function_exists( 'despace_get_active_seasonal_config' ) ? despace_get_active_seasonal_config() : null;
$marquee_sep  = $seasonal_cfg ? '❄' : '✦';

$announcements = array(
	array(
		'text'    => furniva_t( 'Welcome offer: 10% off your first order with code WELCOME10', 'Ưu đãi chào mừng: giảm 10% đơn đầu tiên với mã WELCOME10' ),
		'title'   => furniva_t( 'Welcome Offer: 10% Off Your First Order', 'Ưu đãi chào mừng: Giảm 10% đơn hàng đầu tiên' ),
		'content' => furniva_t( 'Enter promotional code WELCOME10 at checkout to enjoy 10% off your entire first luxury furniture order at DeSpace.', 'Sử dụng mã ưu đãi WELCOME10 khi thanh toán để được giảm ngay 10% cho toàn bộ sản phẩm trong đơn hàng đầu tiên tại DeSpace.' ),
		'action'  => furniva_t( 'Shop Now', 'Mua sắm ngay' ),
		'url'     => $shop_url,
	),
	array(
		'text'    => sprintf( furniva_t( 'Complimentary nationwide delivery on orders over %s', 'Miễn phí giao hàng toàn quốc cho đơn từ %s' ), furniva_is_vi() ? $free_ship_vi : $free_ship_en ),
		'title'   => furniva_t( 'Complimentary Insured Delivery & Assembly', 'Miễn phí giao hàng & lắp đặt tận nơi' ),
		'content' => sprintf( furniva_t( 'DeSpace offers complimentary nationwide delivery and white-glove assembly on all orders from %s.', 'DeSpace miễn phí 100%% chi phí vận chuyển toàn quốc và hỗ trợ lắp đặt chuyên nghiệp đối với mọi đơn hàng từ %s.' ), furniva_is_vi() ? $free_ship_vi : $free_ship_en ),
		'action'  => furniva_t( 'Explore Collection', 'Khám phá bộ sưu tập' ),
		'url'     => $shop_url,
	),
	array(
		'text'    => furniva_t( 'Interactive 1:1 3D & AR room preview on every design', 'Trải nghiệm xem 3D & đặt thử AR tỷ lệ 1:1 trên mọi sản phẩm' ),
		'title'   => furniva_t( 'DeSpace 3D & AR Showroom', 'Không gian trải nghiệm 3D & AR DeSpace' ),
		'content' => furniva_t( 'Inspect wood textures, open doors in 3D, and place true-to-scale furniture directly in your room via mobile AR.', 'Quan sát chi tiết vân gỗ, đóng mở cánh tủ 3D trực quan và đặt nội thất tỷ lệ 1:1 ngay trong phòng qua Camera AR.' ),
		'action'  => furniva_t( 'Explore 3D Collection', 'Khám phá bộ sưu tập 3D' ),
		'url'     => add_query_arg( 'has_3d', 'yes', $shop_url ),
	),
	array(
		'text'    => furniva_t( '5-year structural warranty & 30-day return assurance', 'Bảo hành kết cấu 5 năm · Đổi trả linh hoạt trong 30 ngày' ),
		'title'   => furniva_t( 'Craftsmanship Warranty & Returns', 'Chính sách bảo hành & Đổi trả DeSpace' ),
		'content' => furniva_t( 'Every DeSpace piece is backed by our 5-year craftsmanship warranty and 30-day return support.', 'Mỗi thiết kế tại DeSpace đi kèm chính sách bảo hành kết cấu 5 năm và hỗ trợ đổi trả minh bạch trong 30 ngày.' ),
		'action'  => furniva_t( 'View Policy', 'Xem chính sách' ),
		'url'     => furniva_url( '/chinh-sach-doi-tra-bao-hanh/' ),
	),
	array(
		'text'    => furniva_t( '1-on-1 interior consultation with DeSpace architects', 'Tư vấn không gian sống 1-1 cùng kiến trúc sư DeSpace' ),
		'title'   => furniva_t( '1-on-1 Personal Interior Styling Consultation', 'Tư vấn không gian sống 1-1 cùng chuyên gia' ),
		'content' => furniva_t( 'Book a personalized styling session with our interior architects at our Hanoi showroom or via virtual consultation.', 'Đặt lịch tư vấn trực tiếp 1-1 cùng đội ngũ kiến trúc sư DeSpace tại Showroom Hà Nội hoặc tư vấn trực tuyến.' ),
		'action'  => furniva_t( 'Book Consultation', 'Đặt lịch tư vấn' ),
		'url'     => furniva_url( '/lien-he/' ),
	),
);

if ( $seasonal_cfg ) {
	$noel_marquee_text = furniva_is_vi()
		? trim( (string) ( $seasonal_cfg['marquee_vi'] ?? '' ) )
		: trim( (string) ( $seasonal_cfg['marquee_en'] ?? '' ) );
	$noel_items = array();
	if ( '' !== $noel_marquee_text ) {
		$noel_items[] = array(
			'text'    => $noel_marquee_text,
			'title'   => furniva_t( 'DeSpace Holiday Edition', 'Bộ sưu tập Mùa Giáng Sinh DeSpace' ),
			'content' => $noel_marquee_text,
			'action'  => furniva_t( 'Explore Holiday Offers', 'Khám phá ưu đãi Noel' ),
			'url'     => add_query_arg( 'on_sale', '1', $shop_url ),
		);
	}
	$noel_code = trim( (string) ( $seasonal_cfg['promo_code'] ?? '' ) );
	if ( '' !== $noel_code ) {
		$noel_items[] = array(
			'text'    => sprintf( furniva_t( 'Holiday Code %s — Exclusive seasonal furniture privileges', 'Mã ưu đãi Noel %s — Đặc quyền nội thất mùa lễ hội' ), $noel_code ),
			'title'   => furniva_t( 'Holiday Promo Code', 'Mã ưu đãi Giáng sinh' ),
			'content' => $noel_code,
			'action'  => furniva_t( 'Shop Now', 'Mua sắm ngay' ),
			'url'     => add_query_arg( 'on_sale', '1', $shop_url ),
		);
	}
	if ( ! empty( $noel_items ) ) {
		$announcements = array_merge( $noel_items, array_slice( $announcements, 1 ) );
	}
}
?>
<header class="fv-header" id="top">
	<div class="fv-announcement" role="region" aria-label="<?php echo esc_attr( furniva_t( 'Store announcements', 'Thông báo từ cửa hàng' ) ); ?>">
		<div class="fv-announcement__viewport">
			<div class="fv-announcement__track">
				<div class="fv-announcement__group">
					<?php foreach ( $announcements as $item ) : ?>
						<a class="fv-announcement__item" href="<?php echo esc_url( $item['url'] ); ?>" title="<?php echo esc_attr( $item['title'] ); ?>">
							<?php echo esc_html( $item['text'] ); ?>
						</a>
						<span class="fv-announcement__separator" aria-hidden="true"><?php echo esc_html( $marquee_sep ); ?></span>
					<?php endforeach; ?>
				</div>
				<div class="fv-announcement__group" aria-hidden="true">
					<?php foreach ( $announcements as $item ) : ?>
						<a class="fv-announcement__item" href="<?php echo esc_url( $item['url'] ); ?>" tabindex="-1">
							<?php echo esc_html( $item['text'] ); ?>
						</a>
						<span class="fv-announcement__separator"><?php echo esc_html( $marquee_sep ); ?></span>
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
			<a class="fv-header__logo" href="<?php echo esc_url( furniva_url( '/' ) ); ?>" aria-label="DeSpace — <?php echo esc_attr( furniva_t( 'Home', 'Trang chủ' ) ); ?>">
				<?php if ( $logo_id ) : ?>
					<?php echo wp_get_attachment_image( $logo_id, 'full', false, array( 'alt' => 'DeSpace' ) ); ?>
				<?php else : ?>
					<img class="fv-brand-logo-img" src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/images/despace-logo-header.png' ); ?>" alt="DeSpace - Luxury Living, Lasting Value" width="168" height="56" />
				<?php endif; ?>
				<?php if ( $seasonal_cfg && ! empty( $seasonal_cfg['header_accent'] ) ) : ?>
					<span class="ds-noel-brand-accent" aria-hidden="true">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l1.6 4.4L18 8l-4.4 1.6L12 14l-1.6-4.4L6 8l4.4-1.6L12 2z"/><path d="M18.5 14l.8 2.2 2.2.8-2.2.8-.8 2.2-.8-2.2-2.2-.8 2.2-.8.8-2.2z"/></svg>
					</span>
				<?php endif; ?>
			</a>
		</div>

		<nav class="fv-primary-nav" aria-label="<?php echo esc_attr( furniva_t( 'Primary navigation', 'Điều hướng chính' ) ); ?>">
			<a <?php echo is_front_page() ? 'aria-current="page"' : ''; ?> href="<?php echo esc_url( furniva_url( '/' ) ); ?>"><?php echo esc_html( furniva_t( 'Home', 'Trang chủ' ) ); ?></a>
			<a <?php echo ( is_shop() || is_product_taxonomy() ) ? 'aria-current="page"' : ''; ?> href="<?php echo esc_url( $shop_url ); ?>"><?php echo esc_html( furniva_t( 'Shop', 'Cửa hàng' ) ); ?></a>
			<a <?php echo is_home() || is_singular( 'post' ) ? 'aria-current="page"' : ''; ?> href="<?php echo esc_url( $blog_url ); ?>"><?php echo esc_html( furniva_t( 'Journal', 'Tạp chí' ) ); ?></a>
			<a <?php echo ( is_page( 'about' ) || is_page( 've-despace' ) ) ? 'aria-current="page"' : ''; ?> href="<?php echo esc_url( furniva_url( '/ve-despace/' ) ); ?>"><?php echo esc_html( furniva_t( 'About', 'Giới thiệu' ) ); ?></a>
			<a <?php echo ( is_page( 'contact' ) || is_page( 'lien-he' ) ) ? 'aria-current="page"' : ''; ?> href="<?php echo esc_url( furniva_url( '/lien-he/' ) ); ?>"><?php echo esc_html( furniva_t( 'Contact', 'Liên hệ' ) ); ?></a>
		</nav>

		<div class="fv-header__actions">
			<div class="fv-header-language" aria-label="<?php echo esc_attr( furniva_t( 'Language', 'Ngôn ngữ' ) ); ?>"><?php echo furniva_language_switcher_html(); ?></div>
			<button class="fv-icon-button" type="button" data-furniva-search-open aria-controls="fv-header-search-bar" aria-expanded="false" aria-label="<?php echo esc_attr( furniva_t( 'Search products', 'Tìm kiếm sản phẩm' ) ); ?>">
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
				<form class="fv-search-bar__form" role="search" method="get" action="<?php echo esc_url( furniva_url( '/' ) ); ?>">
					<input type="hidden" name="post_type" value="product" />
					<input type="search" class="fv-search-bar__input" name="s" aria-label="<?php echo esc_attr( furniva_t( 'Search products, collections, materials', 'Tìm kiếm sản phẩm, bộ sưu tập, chất liệu' ) ); ?>" placeholder="<?php echo esc_attr( furniva_t( 'Search products, collections, materials…', 'Tìm kiếm sản phẩm, bộ sưu tập, chất liệu…' ) ); ?>" autocomplete="off" />
				</form>
				<div class="fv-search-bar__quick-tags">
					<span class="fv-quick-tag-label"><?php echo esc_html( furniva_t( 'Popular:', 'Gợi ý:' ) ); ?></span>
					<?php foreach ( array_slice( $nav_categories, 0, 5 ) as $qcat ) :
						$qlink = get_term_link( $qcat );
						if ( is_wp_error( $qlink ) ) continue;
					?>
						<a href="<?php echo esc_url( furniva_url( $qlink ) ); ?>"><?php echo esc_html( furniva_term_label( $qcat->name, 'product_cat' ) ); ?></a>
					<?php endforeach; ?>
				</div>
				<button class="fv-icon-button fv-search-bar__close" type="button" data-furniva-search-close aria-label="<?php echo esc_attr( furniva_t( 'Close search', 'Đóng tìm kiếm' ) ); ?>">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
				</button>
			</div>
		</div>
	</div>

	<!-- Category Subnavigation Row with Hover Recommended Products -->
	<nav class="fv-header__subnav" aria-label="<?php echo esc_attr( furniva_t( 'Product categories', 'Danh mục sản phẩm' ) ); ?>">
		<div class="fv-wrap fv-header__subnav-inner">
			<div class="fv-header__subnav-cats">
				<a class="fv-subnav-link fv-subnav-link--all <?php echo ( is_shop() && empty( $_GET['on_sale'] ) ) ? 'is-active' : ''; ?>" href="<?php echo esc_url( $shop_url ); ?>">
					<?php echo furniva_svg( 'grid' ); ?>
					<span><?php echo esc_html( furniva_t( 'All Collections', 'Tất cả sản phẩm' ) ); ?></span>
				</a>
				<?php foreach ( $nav_categories as $scat ) :
					$scat_link     = get_term_link( $scat );
					if ( is_wp_error( $scat_link ) ) continue;
					$is_cat_active = is_product_category( $scat->slug );
					$cat_display   = furniva_term_label( $scat->name, 'product_cat' );
				?>
				<div class="fv-subnav-item fv-subnav-item--has-mega">
					<a class="fv-subnav-link <?php echo $is_cat_active ? 'is-active' : ''; ?>" href="<?php echo esc_url( furniva_url( $scat_link ) ); ?>" aria-haspopup="true" aria-expanded="false">
						<span><?php echo esc_html( $cat_display ); ?></span>
						<svg class="fv-subnav-caret" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polyline points="6 9 12 15 18 9"></polyline></svg>
					</a>
					<?php echo furniva_category_mega_dropdown( $scat->slug ); ?>
				</div>
				<?php endforeach; ?>
				<span class="fv-subnav-divider" aria-hidden="true"></span>
				<a class="fv-subnav-link fv-subnav-link--highlight <?php echo ( is_shop() && isset( $_GET['on_sale'] ) && '1' === $_GET['on_sale'] ) ? 'is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'on_sale', '1', $shop_url ) ); ?>">
					<span class="fv-subnav-badge">✦ <?php echo esc_html( furniva_t( 'Special Offers', 'Ưu đãi đặc biệt' ) ); ?></span>
				</a>
			</div>
		</div>
	</nav>

	<div class="f-mobile-nav-backdrop" id="furniva-mobile-backdrop" aria-hidden="true"></div>
	<nav class="f-mobile-nav" id="furniva-mobile-nav" aria-label="<?php echo esc_attr( furniva_t( 'Mobile menu', 'Menu di động' ) ); ?>" aria-hidden="true">
		<div class="f-mobile-nav__head">
			<span class="f-mobile-nav__brand">DeSpace</span>
			<div class="f-mobile-nav__head-right">
				<div class="f-mobile-language"><?php echo furniva_language_switcher_html(); ?></div>
				<button type="button" class="f-mobile-nav__close" id="furniva-mobile-close" aria-label="<?php echo esc_attr( furniva_t( 'Close menu', 'Đóng menu' ) ); ?>">
					<?php echo furniva_svg( 'close' ); ?>
				</button>
			</div>
		</div>

		<form class="f-mobile-nav__search" role="search" method="get" action="<?php echo esc_url( furniva_url( '/' ) ); ?>">
			<input type="hidden" name="post_type" value="product" />
			<span class="f-mobile-nav__search-ico" aria-hidden="true"><?php echo furniva_svg( 'search' ); ?></span>
			<input type="search" name="s" placeholder="<?php echo esc_attr( furniva_t( 'Search furniture, materials…', 'Tìm kiếm nội thất, chất liệu…' ) ); ?>" aria-label="<?php echo esc_attr( furniva_t( 'Search products', 'Tìm kiếm sản phẩm' ) ); ?>" autocomplete="off" />
		</form>

		<div class="f-mobile-nav__body">
			<div class="f-mobile-nav__section">
				<span class="f-mobile-nav__kicker"><?php echo esc_html( furniva_t( 'Collections', 'Danh mục nội thất' ) ); ?></span>
				<ul>
					<li>
						<a href="<?php echo esc_url( $shop_url ); ?>" class="f-mobile-nav__cat-link">
							<span><?php echo esc_html( furniva_t( 'All Collections', 'Tất cả sản phẩm' ) ); ?></span>
							<span class="f-mobile-nav__arrow" aria-hidden="true">→</span>
						</a>
					</li>
					<?php foreach ( $nav_categories as $category ) :
						$cat_href = get_term_link( $category );
						if ( is_wp_error( $cat_href ) ) continue;
					?>
					<li>
						<a href="<?php echo esc_url( furniva_url( $cat_href ) ); ?>" class="f-mobile-nav__cat-link">
							<span><?php echo esc_html( furniva_term_label( $category->name, 'product_cat' ) ); ?></span>
							<span class="f-mobile-nav__count"><?php echo (int) $category->count; ?></span>
						</a>
					</li>
					<?php endforeach; ?>
					<li>
						<a href="<?php echo esc_url( add_query_arg( 'on_sale', '1', $shop_url ) ); ?>" class="f-mobile-nav__cat-link f-mobile-nav__cat-link--accent">
							<span>✦ <?php echo esc_html( furniva_t( 'Special Offers', 'Ưu đãi đặc biệt' ) ); ?></span>
							<span class="f-mobile-nav__arrow" aria-hidden="true">→</span>
						</a>
					</li>
				</ul>
			</div>

			<div class="f-mobile-nav__section">
				<span class="f-mobile-nav__kicker"><?php echo esc_html( furniva_t( 'Explore DeSpace', 'Khám phá DeSpace' ) ); ?></span>
				<ul>
					<li><a href="<?php echo esc_url( furniva_url( '/' ) ); ?>"><?php echo esc_html( furniva_t( 'Home', 'Trang chủ' ) ); ?></a></li>
					<li><a href="<?php echo esc_url( $blog_url ); ?>"><?php echo esc_html( furniva_t( 'Journal', 'Tạp chí' ) ); ?></a></li>
					<li><a href="<?php echo esc_url( furniva_url( '/ve-despace/' ) ); ?>"><?php echo esc_html( furniva_t( 'About', 'Giới thiệu' ) ); ?></a></li>
					<li><a href="<?php echo esc_url( furniva_url( '/lien-he/' ) ); ?>"><?php echo esc_html( furniva_t( 'Contact', 'Liên hệ' ) ); ?></a></li>
				</ul>
			</div>
		</div>

		<div class="f-mobile-nav__foot">
			<a href="<?php echo esc_url( $account_url ); ?>" class="f-mobile-nav__util">
				<?php echo furniva_svg( 'user' ); ?>
				<span><?php echo esc_html( furniva_t( 'Account', 'Tài khoản' ) ); ?></span>
			</a>
			<a href="<?php echo esc_url( $wishlist_url ); ?>" class="f-mobile-nav__util">
				<?php echo furniva_svg( 'heart' ); ?>
				<span><?php echo esc_html( furniva_t( 'Wishlist', 'Yêu thích' ) ); ?> (<span class="fv-wishlist-count-inline"><?php echo (int) $wishlist_count; ?></span>)</span>
			</a>
		</div>
	</nav>
	<?php if ( $seasonal_cfg && ! empty( $seasonal_cfg['header_accent'] ) ) : ?>
		<div class="ds-noel-header-garland" aria-hidden="true">
			<?php for ( $b = 0; $b < 18; $b++ ) : ?><span class="ds-noel-light-bead"></span><?php endfor; ?>
		</div>
	<?php endif; ?>
</header>

<dialog class="fv-dialog fv-search-dialog" id="furniva-search-dialog" aria-labelledby="furniva-search-title">
	<div class="fv-dialog__head">
		<div><span class="fv-eyebrow"><?php echo esc_html( furniva_t( 'Search the collection', 'Tìm trong bộ sưu tập' ) ); ?></span><h2 id="furniva-search-title"><?php echo esc_html( furniva_t( 'What are you looking for?', 'Bạn đang tìm sản phẩm nào?' ) ); ?></h2></div>
		<button class="fv-icon-button fv-search-dialog__close" type="button" data-furniva-search-close aria-label="<?php echo esc_attr( furniva_t( 'Close search', 'Đóng tìm kiếm' ) ); ?>">×</button>
	</div>
	<div class="f-header-search"><?php echo do_shortcode( '[fibosearch]' ); ?></div>
</dialog>

<?php if ( ! is_front_page() ) : ?><main id="main-content" class="site-main"><?php endif; ?>
