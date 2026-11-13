<?php
/**
 * Header template.
 *
 * @package DeSpaceStaging
 */
defined( 'ABSPATH' ) || exit;
$shop_url = despace_staging_shop_url();
$cart_url = function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/cart/' );
$account_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : wp_login_url();
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<div class="ds-topbar">DE SPACE · TIMELESS FURNITURE · MADE FOR LIVING</div>
<header class="ds-header">
	<div class="ds-container ds-header-inner">
		<a class="ds-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>">DESPACE<small>FURNITURE & LIVING</small></a>
		<button class="ds-menu-toggle" type="button" aria-label="<?php esc_attr_e( 'Open menu', 'despace-child' ); ?>" aria-expanded="false">☰</button>
		<nav class="ds-nav" aria-label="<?php esc_attr_e( 'Main navigation', 'despace-child' ); ?>">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>">Trang chủ</a>
			<a href="<?php echo esc_url( $shop_url ); ?>">Sản phẩm</a>
			<a href="<?php echo esc_url( $shop_url . '#collections' ); ?>">Bộ sưu tập</a>
			<a href="<?php echo esc_url( home_url( '/order-tracking/' ) ); ?>">Tra cứu đơn</a>
		</nav>
		<div class="ds-actions">
			<a href="<?php echo esc_url( $account_url ); ?>">Tài khoản</a>
			<a class="ds-cart-pill" href="<?php echo esc_url( $cart_url ); ?>">Giỏ hàng (<span class="ds-cart-count"><?php echo esc_html( (string) despace_staging_cart_count() ); ?></span>)</a>
		</div>
	</div>
</header>
