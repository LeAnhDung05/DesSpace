<?php
/**
 * Edit address form — DeSpace Luxury Edition
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 9.3.0
 */

defined( 'ABSPATH' ) || exit;

$page_title = ( 'billing' === $load_address ) ? furniva_t( 'Billing & Invoicing Address', 'Địa chỉ thanh toán & Xuất hóa đơn' ) : furniva_t( 'Shipping & White-Glove Delivery Address', 'Địa chỉ giao hàng & Lắp đặt tận nơi' );
$page_desc  = ( 'billing' === $load_address ) ? furniva_t( 'Update legal billing entity and invoice recipient information.', 'Cập nhật thông tin xuất hóa đơn VAT và thông tin đối soát thanh toán.' ) : furniva_t( 'Specify precise residence address and contact phone for our logistics team.', 'Cung cấp số nhà, tòa nhà và số điện thoại liên lạc để chuyên viên giao lắp liên hệ.' );

do_action( 'woocommerce_before_edit_account_address_form' ); ?>

<?php if ( ! $load_address ) : ?>
	<?php wc_get_template( 'myaccount/my-address.php' ); ?>
<?php else : ?>

	<div class="ax-edit-address-wrap">
		<div class="ax-orders-header">
			<a href="<?php echo esc_url( wc_get_endpoint_url( 'edit-address' ) ); ?>" class="ax-back-link">
				<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
				<span><?php echo esc_html( furniva_t( 'Back to Addresses', 'Quay lại sổ địa chỉ' ) ); ?></span>
			</a>
			<h2><?php echo esc_html( $page_title ); ?></h2>
			<p><?php echo esc_html( $page_desc ); ?></p>
		</div>

		<form method="post" class="ax-address-edit-form" novalidate>
			<div class="woocommerce-address-fields">
				<?php do_action( "woocommerce_before_edit_address_form_{$load_address}" ); ?>

				<div class="woocommerce-address-fields__field-wrapper">
					<?php
					foreach ( $address as $key => $field ) {
						woocommerce_form_field( $key, $field, wc_get_post_data_by_key( $key, $field['value'] ) );
					}
					?>
				</div>

				<?php do_action( "woocommerce_after_edit_address_form_{$load_address}" ); ?>

				<div class="ax-address-submit-row" style="margin-top: 28px;">
					<?php wp_nonce_field( 'woocommerce-edit_address', 'woocommerce-edit-address-nonce' ); ?>
					<button type="submit" class="ax-submit-btn" style="max-width: 260px;" name="save_address" value="<?php esc_attr_e( 'Save address', 'woocommerce' ); ?>">
						<span><?php echo esc_html( furniva_t( 'Save Address', 'LƯU ĐỊA CHỈ' ) ); ?></span>
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
					</button>
					<input type="hidden" name="action" value="edit_address" />
				</div>
			</div>
		</form>
	</div>

<?php endif; ?>

<?php do_action( 'woocommerce_after_edit_account_address_form' ); ?>
