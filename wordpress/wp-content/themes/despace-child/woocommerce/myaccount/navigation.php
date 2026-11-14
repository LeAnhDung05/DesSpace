<?php
/**
 * My Account Navigation — AXGNUD Luxury Edition
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 9.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$current_user_id = get_current_user_id();
$orders_count    = 0;
if ( function_exists( 'wc_get_customer_order_count' ) ) {
	$orders_count = wc_get_customer_order_count( $current_user_id );
}

$wishlist_count = 0;
if ( function_exists( 'yith_wcwl_count_all_products' ) ) {
	$wishlist_count = yith_wcwl_count_all_products();
} elseif ( function_exists( 'yith_wcwl_count_products' ) ) {
	$wishlist_count = yith_wcwl_count_products();
} elseif ( function_exists( 'YITH_WCWL' ) ) {
	$wishlist_count = count( YITH_WCWL()->get_products() );
}

do_action( 'woocommerce_before_account_navigation' );
?>

<nav class="woocommerce-MyAccount-navigation" aria-label="<?php esc_html_e( 'Account pages', 'woocommerce' ); ?>">
	<ul>
		<?php foreach ( wc_get_account_menu_items() as $endpoint => $label ) : 
			$is_active = wc_is_current_account_menu_item( $endpoint );
		?>
			<li class="<?php echo esc_attr( wc_get_account_menu_item_classes( $endpoint ) ); ?> <?php echo $is_active ? 'is-active' : ''; ?>">
				<a href="<?php echo esc_url( wc_get_account_endpoint_url( $endpoint ) ); ?>" <?php echo $is_active ? 'aria-current="page"' : ''; ?>>
					<?php if ( 'dashboard' === $endpoint ) : ?>
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
					<?php elseif ( 'orders' === $endpoint ) : ?>
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
					<?php elseif ( 'edit-address' === $endpoint ) : ?>
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
					<?php elseif ( 'edit-account' === $endpoint ) : ?>
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
					<?php elseif ( 'wishlist' === $endpoint ) : ?>
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>
					<?php elseif ( 'customer-logout' === $endpoint ) : ?>
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
					<?php else : ?>
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
					<?php endif; ?>

					<span><?php echo esc_html( $label ); ?></span>

					<?php if ( 'orders' === $endpoint && $orders_count > 0 ) : ?>
						<span class="ax-nav-badge"><?php echo esc_html( $orders_count ); ?></span>
					<?php elseif ( 'wishlist' === $endpoint && $wishlist_count > 0 ) : ?>
						<span class="ax-nav-badge fv-wishlist-count"><?php echo esc_html( $wishlist_count ); ?></span>
					<?php endif; ?>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
</nav>

<!-- VIP Concierge Support Card -->
<div class="ax-concierge-card">
	<div class="ax-concierge-icon">
		<svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
	</div>
	<h4><?php echo esc_html( furniva_t( 'Dedicated Concierge', 'Chuyên Viên Tư Vấn VIP' ) ); ?></h4>
	<p><?php echo esc_html( furniva_t( 'Need personal styling or warranty support? Our architectural team is ready.', 'Cần hỗ trợ phối cảnh 3D không gian hoặc bảo hành? Đội ngũ AXGNUD luôn sẵn sàng.' ) ); ?></p>
	<a href="tel:19006868" class="ax-concierge-hotline">
		<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
		<span>HOTLINE 1900 6868</span>
	</a>
</div>

<?php do_action( 'woocommerce_after_account_navigation' ); ?>
