<?php
/**
 * Edit account form — AXGNUD Luxury Edition
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 11.0.0
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_before_edit_account_form' );
?>

<div class="ax-edit-account-wrap">
	<div class="ax-orders-header">
		<h2><?php echo esc_html( furniva_t( 'Account Credentials & Security', 'Thông Tin Cá Nhân & Bảo Mật' ) ); ?></h2>
		<p><?php echo esc_html( furniva_t( 'Update your display profile, registered email for order receipts, and account password.', 'Cập nhật tên hiển thị, địa chỉ email nhận thông báo đơn hàng và thiết lập mật khẩu mới.' ) ); ?></p>
	</div>

	<form class="woocommerce-EditAccountForm edit-account ax-edit-account-form" action="" method="post" <?php do_action( 'woocommerce_edit_account_form_tag' ); ?>>
		<?php do_action( 'woocommerce_edit_account_form_start' ); ?>

		<!-- Personal Profile Section -->
		<div class="ax-form-section">
			<h3 class="ax-form-section-title"><?php echo esc_html( furniva_t( 'Profile Information', 'Hồ Sơ Cá Nhân' ) ); ?></h3>

			<div class="ax-form-row-2col">
				<div class="ax-field-group">
					<label for="account_first_name"><?php echo esc_html( furniva_t( 'First name', 'Tên' ) ); ?>&nbsp;<span class="required">*</span></label>
					<div class="ax-input-wrap">
						<svg class="ax-input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
						<input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="account_first_name" id="account_first_name" autocomplete="given-name" value="<?php echo esc_attr( $user->first_name ); ?>" aria-required="true" />
					</div>
				</div>

				<div class="ax-field-group">
					<label for="account_last_name"><?php echo esc_html( furniva_t( 'Last name', 'Họ & Tên đệm' ) ); ?>&nbsp;<span class="required">*</span></label>
					<div class="ax-input-wrap">
						<svg class="ax-input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
						<input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="account_last_name" id="account_last_name" autocomplete="family-name" value="<?php echo esc_attr( $user->last_name ); ?>" aria-required="true" />
					</div>
				</div>
			</div>

			<div class="ax-field-group">
				<label for="account_display_name"><?php echo esc_html( furniva_t( 'Display name', 'Tên hiển thị công khai' ) ); ?>&nbsp;<span class="required">*</span></label>
				<div class="ax-input-wrap">
					<svg class="ax-input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 14 14"></polyline></svg>
					<input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="account_display_name" id="account_display_name" aria-describedby="account_display_name_description" value="<?php echo esc_attr( $user->display_name ); ?>" aria-required="true" />
				</div>
				<p id="account_display_name_description" style="font-size: 12.5px; color: var(--ax-account-text-muted); margin: 6px 0 0;">
					<em><?php echo esc_html( furniva_t( 'This will be displayed in your member dashboard and verified customer reviews.', 'Tên này sẽ hiển thị tại bảng quản trị tài khoản và các đánh giá sản phẩm của bạn.' ) ); ?></em>
				</p>
			</div>

			<div class="ax-field-group">
				<label for="account_email"><?php echo esc_html( furniva_t( 'Email address', 'Địa chỉ email tài khoản' ) ); ?>&nbsp;<span class="required">*</span></label>
				<div class="ax-input-wrap">
					<svg class="ax-input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
					<input type="email" class="woocommerce-Input woocommerce-Input--email input-text" name="account_email" id="account_email" autocomplete="email" value="<?php echo esc_attr( $user->user_email ); ?>" aria-required="true" />
				</div>
			</div>
		</div>

		<?php do_action( 'woocommerce_edit_account_form_fields' ); ?>

		<!-- Security / Password Change Section -->
		<div class="ax-form-section">
			<div class="ax-security-box">
				<h3 class="ax-form-section-title" style="margin-bottom: 16px; border-bottom: none; padding-bottom: 0;">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: -2px; margin-right: 6px; color: var(--ax-account-gold);"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
					<span><?php echo esc_html( furniva_t( 'Password Change (Optional)', 'Đổi Mật Khẩu (Để trống nếu không đổi)' ) ); ?></span>
				</h3>

				<div class="ax-field-group">
					<label for="password_current"><?php echo esc_html( furniva_t( 'Current password (leave blank to leave unchanged)', 'Mật khẩu hiện tại' ) ); ?></label>
					<div class="ax-input-wrap">
						<svg class="ax-input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
						<input type="password" class="woocommerce-Input woocommerce-Input--password input-text" name="password_current" id="password_current" autocomplete="current-password" placeholder="<?php echo esc_attr( furniva_t( 'Enter current password', 'Nhập mật khẩu đang dùng...' ) ); ?>" />
					</div>
				</div>

				<div class="ax-form-row-2col">
					<div class="ax-field-group">
						<label for="password_1"><?php echo esc_html( furniva_t( 'New password', 'Mật khẩu mới' ) ); ?></label>
						<div class="ax-input-wrap">
							<svg class="ax-input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
							<input type="password" class="woocommerce-Input woocommerce-Input--password input-text" name="password_1" id="password_1" autocomplete="new-password" placeholder="<?php echo esc_attr( furniva_t( 'Enter new password', 'Nhập mật khẩu mới...' ) ); ?>" />
						</div>
					</div>

					<div class="ax-field-group">
						<label for="password_2"><?php echo esc_html( furniva_t( 'Confirm new password', 'Xác nhận lại mật khẩu mới' ) ); ?></label>
						<div class="ax-input-wrap">
							<svg class="ax-input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
							<input type="password" class="woocommerce-Input woocommerce-Input--password input-text" name="password_2" id="password_2" autocomplete="new-password" placeholder="<?php echo esc_attr( furniva_t( 'Re-type new password', 'Nhập lại mật khẩu mới...' ) ); ?>" />
						</div>
					</div>
				</div>
			</div>
		</div>

		<?php do_action( 'woocommerce_edit_account_form' ); ?>

		<div style="margin-top: 26px;">
			<?php wp_nonce_field( 'save_account_details', 'save-account-details-nonce' ); ?>
			<button type="submit" class="ax-submit-btn" style="max-width: 260px;" name="save_account_details" value="<?php esc_attr_e( 'Save changes', 'woocommerce' ); ?>">
				<span><?php echo esc_html( furniva_t( 'Save Changes', 'LƯU THAY ĐỔI' ) ); ?></span>
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
			</button>
			<input type="hidden" name="action" value="save_account_details" />
		</div>

		<?php do_action( 'woocommerce_edit_account_form_end' ); ?>
	</form>
</div>

<?php do_action( 'woocommerce_after_edit_account_form' ); ?>
