<?php
/**
 * My Account Dashboard — DeSpace Luxury Edition
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 4.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$user_id       = get_current_user_id();
$current_user  = wp_get_current_user();
$display_name  = $current_user->first_name ? $current_user->first_name : $current_user->display_name;

// Get customer's latest orders (up to 3)
$recent_orders = array();
if ( function_exists( 'wc_get_orders' ) ) {
	$recent_orders = wc_get_orders( array(
		'customer_id' => $user_id,
		'limit'       => 3,
		'orderby'     => 'date',
		'order'       => 'DESC',
	) );
}
?>

<div class="ax-dashboard-view">
	<!-- Welcome Banner -->
	<div class="ax-dash-welcome">
		<h2><?php echo esc_html( furniva_t( 'Welcome Back, ', 'Chào Mừng Trở Lại, ' ) . $display_name ); ?></h2>
		<p>
			<?php
			/* translators: 1: Orders URL 2: Addresses URL 3: Account URL */
			printf(
				furniva_t(
					'From here, you can seamlessly review your <a href="%1$s">recent bespoke orders</a>, manage your <a href="%2$s">shipping residences</a>, and update your <a href="%3$s">personal security credentials</a>.',
					'Tại đây, quý khách có thể theo dõi tiến độ <a href="%1$s">đơn hàng nội thất</a>, quản lý <a href="%2$s">sổ địa chỉ nhận hàng</a> và cập nhật <a href="%3$s">thông tin bảo mật tài khoản</a>.'
				),
				esc_url( wc_get_endpoint_url( 'orders' ) ),
				esc_url( wc_get_endpoint_url( 'edit-address' ) ),
				esc_url( wc_get_endpoint_url( 'edit-account' ) )
			);
			?>
		</p>
	</div>

	<!-- Quick Action Shortcuts -->
	<div class="ax-dash-actions">
		<a href="<?php echo esc_url( wc_get_endpoint_url( 'orders' ) ); ?>" class="ax-dash-action-card">
			<div class="ax-dash-action-icon">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
			</div>
			<span class="ax-dash-action-title"><?php echo esc_html( furniva_t( 'Track Orders', 'Theo Dõi Đơn Hàng' ) ); ?></span>
			<span class="ax-dash-action-sub"><?php echo esc_html( furniva_t( 'Delivery status & receipts', 'Tiến độ gia công & giao nhận' ) ); ?></span>
		</a>

		<a href="<?php echo esc_url( wc_get_endpoint_url( 'edit-address' ) ); ?>" class="ax-dash-action-card">
			<div class="ax-dash-action-icon">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
			</div>
			<span class="ax-dash-action-title"><?php echo esc_html( furniva_t( 'Residences & Addresses', 'Sổ Địa Chỉ Giao Hàng' ) ); ?></span>
			<span class="ax-dash-action-sub"><?php echo esc_html( furniva_t( 'Update delivery locations', 'Cập nhật địa chỉ nhận hàng' ) ); ?></span>
		</a>

		<a href="<?php echo esc_url( home_url( '/wishlist/' ) ); ?>" class="ax-dash-action-card">
			<div class="ax-dash-action-icon">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>
			</div>
			<span class="ax-dash-action-title"><?php echo esc_html( furniva_t( 'Saved Wishlist', 'Sản Phẩm Yêu Thích' ) ); ?></span>
			<span class="ax-dash-action-sub"><?php echo esc_html( furniva_t( 'Curated items for your home', 'Bộ sưu tập đã lưu lại' ) ); ?></span>
		</a>

		<a href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>" class="ax-dash-action-card">
			<div class="ax-dash-action-icon">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline></svg>
			</div>
			<span class="ax-dash-action-title"><?php echo esc_html( furniva_t( 'Catalogue Shop', 'Bộ Sưu Tập Mới' ) ); ?></span>
			<span class="ax-dash-action-sub"><?php echo esc_html( furniva_t( 'Cabinets, wardrobes, desks', 'Khám phá tủ, kệ cao cấp' ) ); ?></span>
		</a>
	</div>

	<!-- Member Exclusive Vouchers Section -->
	<section class="ax-dash-section">
		<div class="ax-section-heading">
			<h3 class="ax-section-title"><?php echo esc_html( furniva_t( 'Your Exclusive Privilege Vouchers', 'Ví Ưu Đãi Hội Viên Độc Quyền' ) ); ?></h3>
		</div>
		<div class="ax-voucher-grid">
			<!-- Voucher 1 -->
			<div class="ax-voucher-card">
				<div class="ax-voucher-info">
					<h4 class="ax-voucher-value"><?php echo esc_html( furniva_t( '10% OFF BESPOKE ORDER', 'GIẢM 10% ĐƠN ĐẦU TIÊN' ) ); ?></h4>
					<p class="ax-voucher-desc"><?php echo esc_html( furniva_t( 'Valid on all storage, wardrobes, bookshelves', 'Áp dụng cho toàn bộ tủ, kệ sách và nội thất cao cấp' ) ); ?></p>
					<div class="ax-voucher-code-wrap">
						<span class="ax-voucher-code">WELCOME10</span>
					</div>
				</div>
				<button type="button" class="ax-voucher-copy-btn" data-copy-code="WELCOME10">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
					<span><?php echo esc_html( furniva_t( 'Copy', 'Sao chép' ) ); ?></span>
				</button>
			</div>

			<!-- Voucher 2 -->
			<div class="ax-voucher-card">
				<div class="ax-voucher-info">
					<h4 class="ax-voucher-value"><?php echo esc_html( furniva_t( 'COMPLIMENTARY SHIPPING', 'MIỄN PHÍ VẬN CHUYỂN' ) ); ?></h4>
					<p class="ax-voucher-desc"><?php echo esc_html( furniva_t( 'Free white-glove delivery on orders over $500', 'Miễn phí giao hàng & lắp đặt tận nơi cho đơn từ $500' ) ); ?></p>
					<div class="ax-voucher-code-wrap">
						<span class="ax-voucher-code">FREESHIP500</span>
					</div>
				</div>
				<button type="button" class="ax-voucher-copy-btn" data-copy-code="FREESHIP500">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
					<span><?php echo esc_html( furniva_t( 'Copy', 'Sao chép' ) ); ?></span>
				</button>
			</div>
		</div>
	</section>

	<!-- Recent Orders Preview Section -->
	<section class="ax-dash-section">
		<div class="ax-section-heading">
			<h3 class="ax-section-title"><?php echo esc_html( furniva_t( 'Recent Orders', 'Đơn Hàng Gần Đây' ) ); ?></h3>
			<?php if ( ! empty( $recent_orders ) ) : ?>
				<a href="<?php echo esc_url( wc_get_endpoint_url( 'orders' ) ); ?>" class="ax-section-link">
					<span><?php echo esc_html( furniva_t( 'View all orders', 'Xem tất cả đơn hàng' ) ); ?></span>
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
				</a>
			<?php endif; ?>
		</div>

		<?php if ( ! empty( $recent_orders ) ) : ?>
			<div class="ax-orders-list">
				<?php foreach ( $recent_orders as $order ) : 
					$order_id   = $order->get_id();
					$status     = $order->get_status();
					$status_name = wc_get_order_status_name( $status );
					$date       = $order->get_date_created() ? wc_format_datetime( $order->get_date_created() ) : '';
					$total      = $order->get_formatted_order_total();
					$item_count = $order->get_item_count();
				?>
					<div class="ax-order-card">
						<div class="ax-order-meta">
							<div class="ax-order-title-row">
								<span class="ax-order-id">#<?php echo esc_html( $order->get_order_number() ); ?></span>
								<span class="ax-status-pill ax-status-<?php echo esc_attr( $status ); ?>">
									<?php echo esc_html( $status_name ); ?>
								</span>
							</div>
							<span class="ax-order-date"><?php echo esc_html( furniva_t( 'Placed on ', 'Đặt ngày ' ) . $date ); ?></span>
						</div>

						<div class="ax-order-pricing">
							<span class="ax-order-total"><?php echo wp_kses_post( $total ); ?></span>
							<span class="ax-order-items-count"><?php echo esc_html( sprintf( furniva_t( '%d item(s)', '%d sản phẩm' ), $item_count ) ); ?></span>
						</div>

						<div class="ax-order-actions">
							<a href="<?php echo esc_url( $order->get_view_order_url() ); ?>" class="ax-btn-outline">
								<span><?php echo esc_html( furniva_t( 'View Details', 'Xem Chi Tiết' ) ); ?></span>
								<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
							</a>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php else : ?>
			<div class="ax-empty-state">
				<div class="ax-empty-icon">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>
				</div>
				<h4 class="ax-empty-title"><?php echo esc_html( furniva_t( 'No Orders Found Yet', 'Quý Khách Chưa Có Đơn Hàng Nào' ) ); ?></h4>
				<p class="ax-empty-text"><?php echo esc_html( furniva_t( 'When you place an order for our architectural furniture pieces, their live production and delivery status will appear here.', 'Khi quý khách đặt mua các sản phẩm nội thất tủ kệ DeSpace, lộ trình gia công và vận chuyển sẽ được hiển thị chi tiết tại đây.' ) ); ?></p>
				<a href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>" class="ax-empty-btn">
					<span><?php echo esc_html( furniva_t( 'Explore Catalogue', 'Khám Phá Bộ Sưu Tập' ) ); ?></span>
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
				</a>
			</div>
		<?php endif; ?>
	</section>

	<!-- AR Room Visualizer Banner -->
	<section class="ax-ar-promo-card">
		<div class="ax-ar-promo-content">
			<h4>✦ <?php echo esc_html( furniva_t( 'Experience DeSpace AR Room Visualizer', 'Thử Đặt Nội Thất Với Công Nghệ AR 3D' ) ); ?></h4>
			<p><?php echo esc_html( furniva_t( 'Preview full-scale 3D models of our storage units, wardrobes, and bookshelves directly inside your room through your phone camera.', 'Ướm thử chính xác kích thước 1:1 của các mẫu tủ kệ vào từng góc phòng trước khi đặt mua thông qua thực tế ảo tăng cường AR.' ) ); ?></p>
		</div>
		<a href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>" class="ax-ar-promo-btn">
			<span><?php echo esc_html( furniva_t( 'Try In AR Now', 'Trải Nghiệm AR Ngay' ) ); ?></span>
			<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline></svg>
		</a>
	</section>
</div>

<?php
/**
 * My Account dashboard action hooks.
 */
do_action( 'woocommerce_account_dashboard' );
do_action( 'woocommerce_before_my_account' );
do_action( 'woocommerce_after_my_account' );
