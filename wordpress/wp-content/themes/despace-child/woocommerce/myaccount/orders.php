<?php
/**
 * Orders — AXGNUD Luxury Edition
 *
 * Shows orders on the account page.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 9.5.0
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_before_account_orders', $has_orders ); ?>

<div class="ax-orders-view">
	<div class="ax-orders-header">
		<h2><?php echo esc_html( furniva_t( 'Your Bespoke Order History', 'Lịch Sử Đơn Hàng Của Quý Khách' ) ); ?></h2>
		<p><?php echo esc_html( furniva_t( 'Track fabrication, shipping timelines, and access official purchase invoices.', 'Theo dõi tiến trình gia công, lộ trình giao hàng và xem hóa đơn điện tử cho từng đơn.' ) ); ?></p>
	</div>

	<?php if ( $has_orders ) : ?>
		<div class="ax-orders-list">
			<?php
			foreach ( $customer_orders->orders as $customer_order ) {
				$order      = wc_get_order( $customer_order );
				$item_count = $order->get_item_count() - $order->get_item_count_refunded();
				$status     = $order->get_status();
				$status_name = wc_get_order_status_name( $status );
				$actions    = wc_get_account_orders_actions( $order );
				?>
				<div class="ax-order-row-card">
					<div class="ax-order-row-top">
						<div class="ax-order-title-row">
							<span class="ax-order-id">#<?php echo esc_html( $order->get_order_number() ); ?></span>
							<span class="ax-status-pill ax-status-<?php echo esc_attr( $status ); ?>">
								<?php echo esc_html( $status_name ); ?>
							</span>
						</div>
						<div class="ax-order-date">
							<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="vertical-align: -2px; margin-right: 4px;"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
							<time datetime="<?php echo esc_attr( $order->get_date_created()->date( 'c' ) ); ?>">
								<?php echo esc_html( wc_format_datetime( $order->get_date_created() ) ); ?>
							</time>
						</div>
					</div>

					<div class="ax-order-row-bottom">
						<div class="ax-order-pricing">
							<span class="ax-order-total"><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></span>
							<span class="ax-order-items-count"><?php echo esc_html( sprintf( _n( '%d item', '%d items', $item_count, 'woocommerce' ), $item_count ) ); ?></span>
						</div>

						<div class="ax-order-actions">
							<?php
							if ( ! empty( $actions ) ) {
								foreach ( $actions as $key => $action ) {
									$action_name = $action['name'];
									if ( 'view' === $key ) {
										$action_name = furniva_t( 'View Details', 'Xem Chi Tiết' );
									} elseif ( 'pay' === $key ) {
										$action_name = furniva_t( 'Pay Now', 'Thanh Toán' );
									} elseif ( 'cancel' === $key ) {
										$action_name = furniva_t( 'Cancel Order', 'Hủy Đơn' );
									}
									echo '<a href="' . esc_url( $action['url'] ) . '" class="ax-btn-outline ' . sanitize_html_class( $key ) . '"><span>' . esc_html( $action_name ) . '</span><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg></a>';
								}
							} else {
								echo '<a href="' . esc_url( $order->get_view_order_url() ) . '" class="ax-btn-outline"><span>' . esc_html( furniva_t( 'View Order Details', 'Xem Chi Tiết' ) ) . '</span><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg></a>';
							}
							?>
						</div>
					</div>
				</div>
				<?php
			}
			?>
		</div>

		<?php do_action( 'woocommerce_before_account_orders_pagination' ); ?>

		<?php if ( 1 < $customer_orders->max_num_pages ) : ?>
			<div class="woocommerce-pagination woocommerce-pagination--without-numbers woocommerce-Pagination" style="margin-top: 24px; text-align: center;">
				<?php if ( 1 !== $current_page ) : ?>
					<a class="woocommerce-button woocommerce-button--previous woocommerce-Button woocommerce-Button--previous button" href="<?php echo esc_url( wc_get_endpoint_url( 'orders', $current_page - 1 ) ); ?>"><?php esc_html_e( 'Previous', 'woocommerce' ); ?></a>
				<?php endif; ?>

				<?php if ( intval( $customer_orders->max_num_pages ) !== $current_page ) : ?>
					<a class="woocommerce-button woocommerce-button--next woocommerce-Button woocommerce-Button--next button" href="<?php echo esc_url( wc_get_endpoint_url( 'orders', $current_page + 1 ) ); ?>"><?php esc_html_e( 'Next', 'woocommerce' ); ?></a>
				<?php endif; ?>
			</div>
		<?php endif; ?>

	<?php else : ?>
		<div class="ax-empty-state">
			<div class="ax-empty-icon">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>
			</div>
			<h4 class="ax-empty-title"><?php echo esc_html( furniva_t( 'No Orders Placed Yet', 'Quý Khách Chưa Có Đơn Hàng Nào' ) ); ?></h4>
			<p class="ax-empty-text"><?php echo esc_html( furniva_t( 'Browse our signature storage, wardrobes, and bookshelves to begin furnishing your space.', 'Khám phá ngay các bộ sưu tập nội thất tủ kệ kiến trúc AXGNUD để bắt đầu hoàn thiện không gian sống.' ) ); ?></p>
			<a href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>" class="ax-empty-btn">
				<span><?php echo esc_html( furniva_t( 'Explore Catalogue', 'Khám Phá Cửa Hàng' ) ); ?></span>
				<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
			</a>
		</div>
	<?php endif; ?>
</div>

<?php do_action( 'woocommerce_after_account_orders', $has_orders ); ?>
