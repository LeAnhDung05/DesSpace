<?php
/**
 * DeSpace Header Template.
 *
 * @package DeSpace_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$shop_id      = function_exists( 'wc_get_page_id' ) ? wc_get_page_id( 'shop' ) : 0;
$shop_url     = $shop_id > 0 ? get_permalink( $shop_id ) : home_url( '/shop/' );
$account_url  = function_exists( 'wc_get_page_id' ) ? get_permalink( wc_get_page_id( 'myaccount' ) ) : home_url( '/my-account/' );
$cart_url     = function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/cart/' );
$wishlist_url = function_exists( 'YITH_WCWL' ) ? YITH_WCWL()->get_wishlist_url() : home_url( '/wishlist/' );
$cart_count   = ( function_exists( 'WC' ) && WC()->cart ) ? WC()->cart->get_cart_contents_count() : 0;

$wishlist_count = 0;
if ( function_exists( 'yith_wcwl_count_all_products' ) ) {
	$wishlist_count = yith_wcwl_count_all_products();
} elseif ( function_exists( 'yith_wcwl_count_products' ) ) {
	$wishlist_count = yith_wcwl_count_products();
}
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'despace-site' ); ?>>
<?php wp_body_open(); ?>

<!-- Announcement Ticker -->
<div class="ds-announcement" role="region" aria-label="<?php esc_attr_e( 'Thông báo cửa hàng', 'despace-child' ); ?>">
	<div class="ds-wrap">
		<div class="ds-announcement__track">
			<div class="ds-announcement__item">
				<span>✦ <?php echo esc_html( despace_t( 'Welcome to DeSpace: Luxury Living & Modern Furniture', 'Chào mừng đến với DeSpace: Nghệ thuật Không gian Sống Đương Đại' ) ); ?></span>
			</div>
			<div class="ds-announcement__item">
				<span class="ds-announcement__separator">✦</span>
				<span><?php echo esc_html( despace_t( 'Interactive 3D / AR Room Visualization is now live', 'Trải nghiệm không gian thực tế ảo 3D & AR trực tiếp trên mọi thiết bị' ) ); ?></span>
			</div>
			<div class="ds-announcement__item">
				<span class="ds-announcement__separator">✦</span>
				<span><?php echo esc_html( despace_t( 'Complimentary delivery & white-glove installation nationwide', 'Miễn phí giao hàng & lắp đặt tận nơi tiêu chuẩn White-Glove' ) ); ?></span>
			</div>
			<div class="ds-announcement__item">
				<span class="ds-announcement__separator">✦</span>
				<span><?php echo esc_html( despace_t( 'Official 5-Year Structural Warranty', 'Bảo hành kết cấu chính hãng 5 năm & Đổi trả trong 30 ngày' ) ); ?></span>
			</div>
		</div>
	</div>
</div>

<!-- Header -->
<header class="ds-header" id="masthead">
	<div class="ds-wrap ds-header__inner">
		<!-- Brand Logo -->
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="ds-brand" rel="home">
			<span class="ds-brand__logo">D</span>
			<span>DESPACE</span>
		</a>

		<!-- Navigation Menu -->
		<nav class="ds-nav" aria-label="<?php esc_attr_e( 'Điều hướng chính', 'despace-child' ); ?>">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="<?php echo is_front_page() ? 'active' : ''; ?>">
				<?php echo esc_html( despace_t( 'Home', 'Trang chủ' ) ); ?>
			</a>
			<a href="<?php echo esc_url( $shop_url ); ?>" class="<?php echo ( is_shop() || is_product_taxonomy() ) ? 'active' : ''; ?>">
				<?php echo esc_html( despace_t( 'Collection', 'Bộ sưu tập' ) ); ?>
			</a>
			<a href="<?php echo esc_url( add_query_arg( 'has_3d', '1', $shop_url ) ); ?>">
				<span style="color:var(--ds-accent-dark);font-weight:700;">3D & AR</span>
			</a>
			<a href="<?php echo esc_url( home_url( '/#spaces' ) ); ?>">
				<?php echo esc_html( despace_t( 'Spaces', 'Không gian sống' ) ); ?>
			</a>
			<a href="<?php echo esc_url( home_url( '/#story' ) ); ?>">
				<?php echo esc_html( despace_t( 'About', 'Về DeSpace' ) ); ?>
			</a>
		</nav>

		<!-- Actions -->
		<div class="ds-header__actions">
			<!-- Account -->
			<a href="<?php echo esc_url( $account_url ); ?>" class="ds-action-btn" title="<?php esc_attr_e( 'Tài khoản', 'despace-child' ); ?>">
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
					<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
					<circle cx="12" cy="7" r="4"></circle>
				</svg>
			</a>

			<!-- Wishlist -->
			<a href="<?php echo esc_url( $wishlist_url ); ?>" class="ds-action-btn" title="<?php esc_attr_e( 'Yêu thích', 'despace-child' ); ?>">
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
					<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
				</svg>
				<?php if ( $wishlist_count > 0 ) : ?>
					<span class="ds-badge-count"><?php echo esc_html( $wishlist_count ); ?></span>
				<?php endif; ?>
			</a>

			<!-- Cart -->
			<a href="<?php echo esc_url( $cart_url ); ?>" class="ds-action-btn" title="<?php esc_attr_e( 'Giỏ hàng', 'despace-child' ); ?>">
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
					<path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
					<line x1="3" y1="6" x2="21" y2="6"></line>
					<path d="M16 10a4 4 0 0 1-8 0"></path>
				</svg>
				<?php if ( $cart_count > 0 ) : ?>
					<span class="ds-badge-count"><?php echo esc_html( $cart_count ); ?></span>
				<?php endif; ?>
			</a>
		</div>
	</div>
</header>
<main id="content" class="ds-site-main">
