<?php
/**
 * My Account page — AXGNUD Luxury Edition
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 3.5.0
 */

defined( 'ABSPATH' ) || exit;

$current_user = wp_get_current_user();
$user_id      = $current_user->ID;
$first_name   = $current_user->first_name;
$last_name    = $current_user->last_name;

$display_name = trim( $first_name . ' ' . $last_name );
if ( empty( $display_name ) ) {
	$display_name = $current_user->display_name ?: $current_user->user_login;
}

// Compute Monogram Initials
$monogram = '';
if ( ! empty( $first_name ) && ! empty( $last_name ) ) {
	$monogram = mb_strtoupper( mb_substr( $first_name, 0, 1 ) . mb_substr( $last_name, 0, 1 ) );
} elseif ( ! empty( $first_name ) ) {
	$monogram = mb_strtoupper( mb_substr( $first_name, 0, 1 ) );
} else {
	$parts = preg_split( '/\s+/', trim( $display_name ) );
	if ( count( $parts ) >= 2 ) {
		$monogram = mb_strtoupper( mb_substr( $parts[0], 0, 1 ) . mb_substr( end( $parts ), 0, 1 ) );
	} else {
		$monogram = mb_strtoupper( mb_substr( $display_name, 0, 2 ) );
	}
}

// Real customer order count
$orders_count = 0;
if ( function_exists( 'wc_get_customer_order_count' ) ) {
	$orders_count = wc_get_customer_order_count( $user_id );
} elseif ( function_exists( 'wc_get_orders' ) ) {
	$orders = wc_get_orders( array( 'customer_id' => $user_id, 'limit' => -1, 'return' => 'ids' ) );
	$orders_count = is_array( $orders ) ? count( $orders ) : 0;
}

// Real wishlist count
$wishlist_count = 0;
if ( function_exists( 'yith_wcwl_count_all_products' ) ) {
	$wishlist_count = yith_wcwl_count_all_products();
} elseif ( function_exists( 'yith_wcwl_count_products' ) ) {
	$wishlist_count = yith_wcwl_count_products();
} elseif ( function_exists( 'YITH_WCWL' ) ) {
	$wishlist_count = count( YITH_WCWL()->get_products() );
}
?>

<div class="ax-account-container">
	<!-- Top Profile Monogram Hero -->
	<header class="ax-profile-hero">
		<div class="ax-profile-main">
			<div class="ax-monogram-avatar" title="<?php echo esc_attr( $display_name ); ?>">
				<?php echo esc_html( $monogram ); ?>
			</div>
			<div class="ax-profile-info">
				<div class="ax-profile-badge-row">
					<span class="ax-member-tier-pill">✦ <?php echo esc_html( furniva_t( 'AXGNUD Elite Member', 'Hội Viên Tinh Hoa AXGNUD' ) ); ?></span>
				</div>
				<h1 class="ax-profile-name"><?php echo esc_html( furniva_t( 'Hello, ', 'Xin chào, ' ) . $display_name ); ?></h1>
				<p class="ax-profile-email"><?php echo esc_html( $current_user->user_email ); ?></p>
			</div>
		</div>

		<!-- Quick Stats Ribbon -->
		<div class="ax-hero-stats">
			<a href="<?php echo esc_url( wc_get_endpoint_url( 'orders' ) ); ?>" class="ax-stat-item">
				<span class="ax-stat-value"><?php echo esc_html( $orders_count ); ?></span>
				<span class="ax-stat-label"><?php echo esc_html( furniva_t( 'Orders Placed', 'Đơn hàng' ) ); ?></span>
			</a>
			<a href="<?php echo esc_url( home_url( '/wishlist/' ) ); ?>" class="ax-stat-item">
				<span class="ax-stat-value fv-wishlist-count"><?php echo esc_html( $wishlist_count ); ?></span>
				<span class="ax-stat-label"><?php echo esc_html( furniva_t( 'Saved Items', 'Yêu thích' ) ); ?></span>
			</a>
			<div class="ax-stat-item">
				<span class="ax-stat-value">2</span>
				<span class="ax-stat-label"><?php echo esc_html( furniva_t( 'VIP Vouchers', 'Mã ưu đãi' ) ); ?></span>
			</div>
			<div class="ax-stat-item">
				<span class="ax-stat-value">5 <?php echo esc_html( furniva_t( 'Yrs', 'Năm' ) ); ?></span>
				<span class="ax-stat-label"><?php echo esc_html( furniva_t( 'Warranty', 'Bảo hành' ) ); ?></span>
			</div>
		</div>
	</header>

	<!-- Main 2-Column Account Grid -->
	<div class="ax-account-grid">
		<!-- Left: Sidebar Navigation -->
		<aside class="ax-account-sidebar">
			<?php
			/**
			 * My Account navigation hook.
			 */
			do_action( 'woocommerce_account_navigation' );
			?>
		</aside>

		<!-- Right: Account Content Area -->
		<main class="woocommerce-MyAccount-content" role="main">
			<?php
			/**
			 * My Account content hook.
			 */
			do_action( 'woocommerce_account_content' );
			?>
		</main>
	</div>
</div>
