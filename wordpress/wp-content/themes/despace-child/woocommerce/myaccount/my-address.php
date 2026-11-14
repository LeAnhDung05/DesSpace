<?php
/**
 * My Addresses — DeSpace Luxury Edition
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 9.3.0
 */

defined( 'ABSPATH' ) || exit;

$customer_id = get_current_user_id();

if ( ! wc_ship_to_billing_address_only() && wc_shipping_enabled() ) {
	$get_addresses = apply_filters(
		'woocommerce_my_account_get_addresses',
		array(
			'billing'  => furniva_t( 'Billing Address', 'Địa chỉ thanh toán' ),
			'shipping' => furniva_t( 'Shipping Residence', 'Địa chỉ giao hàng & lắp đặt' ),
		),
		$customer_id
	);
} else {
	$get_addresses = apply_filters(
		'woocommerce_my_account_get_addresses',
		array(
			'billing' => furniva_t( 'Billing Address', 'Địa chỉ thanh toán' ),
		),
		$customer_id
	);
}
?>

<div class="ax-addresses-view">
	<div class="ax-orders-header">
		<h2><?php echo esc_html( furniva_t( 'Saved Delivery Residences', 'Sổ Địa Chỉ Giao Nhận & Thanh Toán' ) ); ?></h2>
		<p><?php echo esc_html( furniva_t( 'Manage your primary residential delivery address and corporate tax billing details.', 'Quản lý địa chỉ giao nhận nội thất mặc định và thông tin xuất hóa đơn khi thanh toán.' ) ); ?></p>
	</div>

	<div class="ax-address-grid">
		<?php foreach ( $get_addresses as $name => $address_title ) : ?>
			<?php
				$address = wc_get_account_formatted_address( $name );
			?>
			<div class="ax-address-card">
				<div>
					<div class="ax-address-card-top">
						<h3 class="ax-address-title">
							<?php if ( 'billing' === $name ) : ?>
								<svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line></svg>
							<?php else : ?>
								<svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
							<?php endif; ?>
							<span><?php echo esc_html( $address_title ); ?></span>
						</h3>
					</div>

					<address class="ax-address-body">
						<?php
						if ( $address ) {
							echo wp_kses_post( $address );
						} else {
							echo '<span style="color: var(--ax-account-text-muted); font-style: italic;">' . esc_html( furniva_t( 'No address saved yet. Click below to add your primary delivery location.', 'Quý khách chưa thiết lập địa chỉ này. Bấm bên dưới để thêm địa chỉ nhận hàng.' ) ) . '</span>';
						}

						/**
						 * Hook after core address fields.
						 */
						do_action( 'woocommerce_my_account_after_my_address', $name );
						?>
					</address>
				</div>

				<a href="<?php echo esc_url( wc_get_endpoint_url( 'edit-address', $name ) ); ?>" class="ax-address-edit-btn">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>
					<span>
						<?php
						printf(
							/* translators: %s: Address title */
							$address ? furniva_t( 'Edit %s', 'Chỉnh sửa địa chỉ' ) : furniva_t( 'Add %s', 'Thêm địa chỉ' ),
							''
						);
						?>
					</span>
				</a>
			</div>
		<?php endforeach; ?>
	</div>
</div>
