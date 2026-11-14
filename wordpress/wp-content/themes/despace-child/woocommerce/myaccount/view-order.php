<?php
/**
 * View Order — DeSpace Luxury Edition
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 10.6.0
 */

defined( 'ABSPATH' ) || exit;

$notes       = $order->get_customer_order_notes();
$status      = $order->get_status();
$status_name = wc_get_order_status_name( $status );
$date        = $order->get_date_created() ? wc_format_datetime( $order->get_date_created() ) : '';
?>

<div class="ax-view-order-wrap">
	<!-- Top Navigation Back Link -->
	<div class="ax-orders-header" style="margin-bottom: 24px;">
		<a href="<?php echo esc_url( wc_get_endpoint_url( 'orders' ) ); ?>" class="ax-back-link">
			<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
			<span><?php echo esc_html( furniva_t( 'Back to Order History', 'Quay lại danh sách đơn hàng' ) ); ?></span>
		</a>
		<div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; margin-top: 10px;">
			<h2 style="margin: 0;"><?php echo esc_html( furniva_t( 'Order #', 'Chi Tiết Đơn Hàng #' ) . $order->get_order_number() ); ?></h2>
			<span class="ax-status-pill ax-status-<?php echo esc_attr( $status ); ?>">
				<?php echo esc_html( $status_name ); ?>
			</span>
		</div>
		<p style="margin-top: 6px;"><?php echo esc_html( furniva_t( 'Placed on ', 'Thời gian khởi tạo: ' ) . $date ); ?></p>
	</div>

	<!-- Digital Warranty Vault Ribbon -->
	<div class="ax-reg-perk-banner" style="margin-bottom: 28px; background: #fdfaf3; border: 1px solid var(--ax-account-gold-border); border-radius: var(--ax-account-radius-sm); padding: 14px 18px; display: flex; align-items: center; gap: 14px;">
		<span class="ax-reg-perk-badge" style="background: var(--ax-account-gold); color: #fff; padding: 4px 10px; border-radius: 4px; font-size: 11px; font-weight: 700; letter-spacing: 0.05em;">✦ <?php echo esc_html( furniva_t( '5-YEAR WARRANTY ACTIVATED', 'KÍCH HOẠT BẢO HÀNH 5 NĂM' ) ); ?></span>
		<p style="margin: 0; font-size: 13.5px; color: var(--ax-account-text-body);">
			<?php echo esc_html( furniva_t( 'All bespoke wooden structures and soft-close hardware in this order are certified under DeSpace Global Warranty Vault.', 'Toàn bộ kết cấu gỗ cao cấp và phụ kiện giảm chấn thuộc đơn hàng này được bảo vệ tự động bởi hệ thống bảo hành chính hãng DeSpace.' ) ); ?>
		</p>
	</div>

	<?php if ( $notes ) : ?>
		<div class="ax-order-notes-box" style="background: #ffffff; border: 1px solid var(--ax-account-border); border-radius: var(--ax-account-radius-sm); padding: 20px; margin-bottom: 30px;">
			<h3 style="font-size: 16px; margin: 0 0 14px; font-weight: 600;"><?php echo esc_html( furniva_t( 'Order Progress Updates', 'Nhật Ký Tiến Trình Gia Công & Giao Hàng' ) ); ?></h3>
			<ul style="list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 12px;">
				<?php foreach ( $notes as $note ) : ?>
					<li style="border-left: 2px solid var(--ax-account-gold); padding-left: 14px;">
						<div style="font-size: 12px; color: var(--ax-account-text-muted);"><?php echo date_i18n( 'd/m/Y H:i', strtotime( $note->comment_date ) ); ?></div>
						<div style="font-size: 14px; color: var(--ax-account-text-dark); margin-top: 2px;"><?php echo wp_kses_post( wpautop( wptexturize( $note->comment_content ) ) ); ?></div>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>

	<?php
	/**
	 * Order details hook (renders items table and billing/shipping addresses).
	 */
	do_action( 'woocommerce_view_order', $order_id );
	?>
</div>
