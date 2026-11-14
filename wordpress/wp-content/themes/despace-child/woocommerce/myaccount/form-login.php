<?php
/**
 * Login & Registration Form — AXGNUD Luxury Edition
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 9.9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$registration_enabled = 'yes' === get_option( 'woocommerce_enable_myaccount_registration' );
$active_tab           = ( ! empty( $_POST['register'] ) || ! empty( $_GET['action'] ) && 'register' === $_GET['action'] ) ? 'register' : 'login';

do_action( 'woocommerce_before_customer_login_form' ); ?>

<div class="ax-account-container">
	<!-- Page Editorial Header -->
	<header class="ax-auth-header">
		<div class="ax-auth-eyebrow">
			<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
			<span><?php echo esc_html( furniva_t( 'AXGNUD PRIVILEGE CLUB', 'CÂU LẠC BỘ ĐẶC QUYỀN AXGNUD' ) ); ?></span>
		</div>
		<h1 class="ax-auth-title"><?php echo esc_html( furniva_t( 'Welcome to Your Private Sanctuary', 'Không Gian Quản Trị & Đặc Quyền Hội Viên' ) ); ?></h1>
		<p class="ax-auth-subtitle"><?php echo esc_html( furniva_t( 'Sign in to access your bespoke orders, 5-year warranty vault, and exclusive member privileges.', 'Đăng nhập để theo dõi đơn hàng thiết kế, sổ địa chỉ nhận hàng và kích hoạt gói bảo hành toàn diện 5 năm.' ) ); ?></p>
	</header>

	<div class="ax-auth-layout">
		<!-- Left: Interactive Login & Register Card -->
		<div class="ax-auth-card">
			<?php if ( $registration_enabled ) : ?>
				<!-- Segmented Tabs Switcher -->
				<div class="ax-auth-tabs" role="tablist" aria-label="<?php echo esc_attr( furniva_t( 'Authentication Tabs', 'Chuyển đổi đăng nhập đăng ký' ) ); ?>">
					<button type="button" class="ax-auth-tab <?php echo 'login' === $active_tab ? 'is-active' : ''; ?>" data-target="login" role="tab" aria-selected="<?php echo 'login' === $active_tab ? 'true' : 'false'; ?>">
						<svg class="ax-tab-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path><polyline points="10 17 15 12 10 7"></polyline><line x1="15" y1="12" x2="3" y2="12"></line></svg>
						<span><?php echo esc_html( furniva_t( 'Sign In', 'Đăng Nhập' ) ); ?></span>
					</button>
					<button type="button" class="ax-auth-tab <?php echo 'register' === $active_tab ? 'is-active' : ''; ?>" data-target="register" role="tab" aria-selected="<?php echo 'register' === $active_tab ? 'true' : 'false'; ?>">
						<svg class="ax-tab-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="8.5" cy="7" r="4"></circle><line x1="20" y1="8" x2="20" y2="14"></line><line x1="23" y1="11" x2="17" y2="11"></line></svg>
						<span><?php echo esc_html( furniva_t( 'Create Account', 'Đăng Ký Thành Viên' ) ); ?></span>
					</button>
				</div>
			<?php endif; ?>

			<!-- LOGIN PANEL -->
			<div class="ax-auth-panel <?php echo 'login' === $active_tab ? 'is-active' : ''; ?>" id="ax-panel-login">
				<form class="woocommerce-form woocommerce-form-login login" method="post" novalidate>
					<?php do_action( 'woocommerce_login_form_start' ); ?>

					<div class="ax-field-group">
						<label for="username"><?php echo esc_html( furniva_t( 'Username or email address', 'Tên đăng nhập hoặc địa chỉ email' ) ); ?>&nbsp;<span class="required">*</span></label>
						<div class="ax-input-wrap">
							<svg class="ax-input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
							<input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="username" id="username" autocomplete="username" placeholder="<?php echo esc_attr( furniva_t( 'Enter your username or email', 'Nhập email hoặc tên tài khoản...' ) ); ?>" value="<?php echo ( ! empty( $_POST['username'] ) && is_string( $_POST['username'] ) ) ? esc_attr( wp_unslash( $_POST['username'] ) ) : ''; ?>" required aria-required="true" />
						</div>
					</div>

					<div class="ax-field-group">
						<label for="password"><?php echo esc_html( furniva_t( 'Password', 'Mật khẩu' ) ); ?>&nbsp;<span class="required">*</span></label>
						<div class="ax-input-wrap">
							<svg class="ax-input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
							<input class="woocommerce-Input woocommerce-Input--text input-text" type="password" name="password" id="password" autocomplete="current-password" placeholder="<?php echo esc_attr( furniva_t( 'Enter your secure password', 'Nhập mật khẩu của bạn...' ) ); ?>" required aria-required="true" />
							<button type="button" class="ax-pwd-toggle" aria-label="<?php echo esc_attr( furniva_t( 'Toggle password visibility', 'Ẩn/hiện mật khẩu' ) ); ?>">
								<svg class="ax-eye-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" width="18" height="18"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
							</button>
						</div>
					</div>

					<?php do_action( 'woocommerce_login_form' ); ?>

					<div class="ax-form-helper-row">
						<label class="ax-checkbox-label">
							<input name="rememberme" type="checkbox" id="rememberme" value="forever" />
							<span><?php echo esc_html( furniva_t( 'Keep me signed in', 'Ghi nhớ đăng nhập' ) ); ?></span>
						</label>
						<a class="ax-lost-pwd-link" href="<?php echo esc_url( wp_lostpassword_url() ); ?>"><?php echo esc_html( furniva_t( 'Forgot password?', 'Quên mật khẩu?' ) ); ?></a>
					</div>

					<?php wp_nonce_field( 'woocommerce-login', 'woocommerce-login-nonce' ); ?>
					<button type="submit" class="ax-submit-btn" name="login" value="<?php esc_attr_e( 'Log in', 'woocommerce' ); ?>">
						<span><?php echo esc_html( furniva_t( 'Sign In to Account', 'ĐĂNG NHẬP VÀO TÀI KHOẢN' ) ); ?></span>
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
					</button>

					<?php do_action( 'woocommerce_login_form_end' ); ?>
				</form>
			</div>

			<?php if ( $registration_enabled ) : ?>
				<!-- REGISTER PANEL -->
				<div class="ax-auth-panel <?php echo 'register' === $active_tab ? 'is-active' : ''; ?>" id="ax-panel-register">
					<div class="ax-reg-perk-banner">
						<span class="ax-reg-perk-badge">✦ <?php echo esc_html( furniva_t( 'VIP WELCOME GIFT', 'QUÀ TẶNG THÀNH VIÊN' ) ); ?></span>
						<p class="ax-reg-perk-text"><?php echo esc_html( furniva_t( 'Sign up now to receive code ', 'Đăng ký ngay nhận mã ' ) ); ?><strong>WELCOME10</strong><?php echo esc_html( furniva_t( ' for 10% off your premier order.', ' giảm 10% cho đơn hàng đầu tiên.' ) ); ?></p>
					</div>

					<form method="post" class="woocommerce-form woocommerce-form-register register" <?php do_action( 'woocommerce_register_form_tag' ); ?>>
						<?php do_action( 'woocommerce_register_form_start' ); ?>

						<!-- Row 1: Full Name (Họ & Tên) -->
						<div class="ax-field-row-2">
							<div class="ax-field-group">
								<label for="reg_billing_last_name"><?php echo esc_html( furniva_t( 'Last Name', 'Họ & Tên đệm' ) ); ?>&nbsp;<span class="required">*</span></label>
								<div class="ax-input-wrap">
									<svg class="ax-input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
									<input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="billing_last_name" id="reg_billing_last_name" autocomplete="family-name" placeholder="<?php echo esc_attr( furniva_t( 'e.g. Nguyen', 'Ví dụ: Nguyễn Văn' ) ); ?>" value="<?php echo ( ! empty( $_POST['billing_last_name'] ) ) ? esc_attr( wp_unslash( $_POST['billing_last_name'] ) ) : ''; ?>" required aria-required="true" />
								</div>
							</div>

							<div class="ax-field-group">
								<label for="reg_billing_first_name"><?php echo esc_html( furniva_t( 'First Name', 'Tên của bạn' ) ); ?>&nbsp;<span class="required">*</span></label>
								<div class="ax-input-wrap">
									<svg class="ax-input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
									<input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="billing_first_name" id="reg_billing_first_name" autocomplete="given-name" placeholder="<?php echo esc_attr( furniva_t( 'e.g. Alex', 'Ví dụ: Anh Dũng' ) ); ?>" value="<?php echo ( ! empty( $_POST['billing_first_name'] ) ) ? esc_attr( wp_unslash( $_POST['billing_first_name'] ) ) : ''; ?>" required aria-required="true" />
								</div>
							</div>
						</div>

						<!-- Row 2: Phone & City -->
						<div class="ax-field-row-2">
							<div class="ax-field-group">
								<label for="reg_billing_phone"><?php echo esc_html( furniva_t( 'Phone Number', 'Số điện thoại nhận hàng' ) ); ?>&nbsp;<span class="required">*</span></label>
								<div class="ax-input-wrap">
									<svg class="ax-input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
									<input type="tel" class="woocommerce-Input woocommerce-Input--text input-text" name="billing_phone" id="reg_billing_phone" autocomplete="tel" placeholder="<?php echo esc_attr( furniva_t( 'e.g. 0901 234 567', 'Ví dụ: 0901 234 567' ) ); ?>" value="<?php echo ( ! empty( $_POST['billing_phone'] ) ) ? esc_attr( wp_unslash( $_POST['billing_phone'] ) ) : ''; ?>" required aria-required="true" />
								</div>
							</div>

							<div class="ax-field-group">
								<label for="reg_billing_city"><?php echo esc_html( furniva_t( 'City / Province (Delivery)', 'Tỉnh / Thành phố giao hàng' ) ); ?></label>
								<div class="ax-input-wrap">
									<svg class="ax-input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
									<input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="billing_city" id="reg_billing_city" autocomplete="address-level2" placeholder="<?php echo esc_attr( furniva_t( 'e.g. Hanoi, HCMC...', 'Ví dụ: TP. Hồ Chí Minh, Hà Nội...' ) ); ?>" value="<?php echo ( ! empty( $_POST['billing_city'] ) ) ? esc_attr( wp_unslash( $_POST['billing_city'] ) ) : ''; ?>" />
								</div>
							</div>
						</div>

						<?php if ( 'no' === get_option( 'woocommerce_registration_generate_username' ) ) : ?>
							<div class="ax-field-group">
								<label for="reg_username"><?php echo esc_html( furniva_t( 'Username', 'Tên tài khoản mong muốn' ) ); ?>&nbsp;<span class="required">*</span></label>
								<div class="ax-input-wrap">
									<svg class="ax-input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
									<input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="username" id="reg_username" autocomplete="username" placeholder="<?php echo esc_attr( furniva_t( 'Choose your username', 'Ví dụ: alex.nguyen' ) ); ?>" value="<?php echo ( ! empty( $_POST['username'] ) ) ? esc_attr( wp_unslash( $_POST['username'] ) ) : ''; ?>" required aria-required="true" />
								</div>
							</div>
						<?php endif; ?>

						<!-- Row 3: Email Address -->
						<div class="ax-field-group">
							<label for="reg_email"><?php echo esc_html( furniva_t( 'Email address', 'Địa chỉ email đăng nhập' ) ); ?>&nbsp;<span class="required">*</span></label>
							<div class="ax-input-wrap">
								<svg class="ax-input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
								<input type="email" class="woocommerce-Input woocommerce-Input--text input-text" name="email" id="reg_email" autocomplete="email" placeholder="<?php echo esc_attr( furniva_t( 'name@example.com', 'name@example.com' ) ); ?>" value="<?php echo ( ! empty( $_POST['email'] ) ) ? esc_attr( wp_unslash( $_POST['email'] ) ) : ''; ?>" required aria-required="true" />
							</div>
						</div>

						<!-- Row 4: Password & Password Confirm -->
						<?php if ( 'no' === get_option( 'woocommerce_registration_generate_password' ) ) : ?>
							<div class="ax-field-row-2">
								<div class="ax-field-group">
									<label for="reg_password"><?php echo esc_html( furniva_t( 'Create Password', 'Tạo mật khẩu' ) ); ?>&nbsp;<span class="required">*</span></label>
									<div class="ax-input-wrap">
										<svg class="ax-input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
										<input type="password" class="woocommerce-Input woocommerce-Input--text input-text" name="password" id="reg_password" autocomplete="new-password" placeholder="<?php echo esc_attr( furniva_t( 'Minimum 8 characters', 'Tối thiểu 8 ký tự...' ) ); ?>" required aria-required="true" />
										<button type="button" class="ax-pwd-toggle" aria-label="<?php echo esc_attr( furniva_t( 'Toggle password visibility', 'Ẩn/hiện mật khẩu' ) ); ?>">
											<svg class="ax-eye-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" width="18" height="18"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
										</button>
									</div>
								</div>

								<div class="ax-field-group">
									<label for="reg_password_confirm"><?php echo esc_html( furniva_t( 'Confirm Password', 'Xác nhận lại mật khẩu' ) ); ?>&nbsp;<span class="required">*</span></label>
									<div class="ax-input-wrap">
										<svg class="ax-input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
										<input type="password" class="woocommerce-Input woocommerce-Input--text input-text" name="password_confirm" id="reg_password_confirm" autocomplete="new-password" placeholder="<?php echo esc_attr( furniva_t( 'Re-enter your password', 'Nhập lại mật khẩu vừa tạo...' ) ); ?>" required aria-required="true" />
										<button type="button" class="ax-pwd-toggle" aria-label="<?php echo esc_attr( furniva_t( 'Toggle password visibility', 'Ẩn/hiện mật khẩu' ) ); ?>">
											<svg class="ax-eye-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" width="18" height="18"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
										</button>
									</div>
								</div>
							</div>

							<div class="ax-field-hint">
								<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
								<span><?php echo esc_html( furniva_t( 'Password must be at least 8 characters long for digital warranty security.', 'Mật khẩu từ 8 ký tự trở lên để bảo mật hồ sơ bảo hành điện tử và thông tin đơn hàng.' ) ); ?></span>
							</div>
						<?php else : ?>
							<p class="ax-auth-subtitle" style="margin-bottom: 20px; font-size: 13.5px;"><?php esc_html_e( 'A link to set a new password will be sent to your email address.', 'woocommerce' ); ?></p>
						<?php endif; ?>

						<!-- Terms & Privacy Agreement -->
						<div class="ax-terms-row">
							<label class="ax-checkbox-label">
								<input name="terms_agree" type="checkbox" id="reg_terms_agree" value="1" checked required />
								<span><?php echo furniva_t( 'I agree to the AXGNUD Privilege Club Terms of Service and Privacy Policy.', 'Tôi đồng ý với Điều khoản dịch vụ và Chính sách quyền riêng tư của AXGNUD Privilege Club.' ); ?></span>
							</label>
						</div>

						<?php do_action( 'woocommerce_register_form' ); ?>

						<?php wp_nonce_field( 'woocommerce-register', 'woocommerce-register-nonce' ); ?>
						<button type="submit" class="ax-submit-btn" name="register" value="<?php esc_attr_e( 'Register', 'woocommerce' ); ?>">
							<span><?php echo esc_html( furniva_t( 'Join AXGNUD Privilege', 'GIA NHẬP HỘI VIÊN AXGNUD' ) ); ?></span>
							<svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
						</button>

						<?php do_action( 'woocommerce_register_form_end' ); ?>
					</form>
				</div>
			<?php endif; ?>
		</div>

		<!-- Right: Luxury Member Privileges Card -->
		<aside class="ax-privilege-card">
			<div class="ax-privilege-eyebrow">
				<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="7"></circle><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"></polyline></svg>
				<span><?php echo esc_html( furniva_t( 'EXCLUSIVE BENEFITS', 'ĐẶC QUYỀN HỘI VIÊN' ) ); ?></span>
			</div>
			<h3 class="ax-privilege-title"><?php echo esc_html( furniva_t( 'Elevate Your Living Experience', 'Đặc Quyền Dành Riêng Cho Quý Gia Chủ Tinh Hoa' ) ); ?></h3>
			<p class="ax-privilege-desc"><?php echo esc_html( furniva_t( 'Becoming an AXGNUD member unlocks curated interior architectural benefits tailored to your dream residence.', 'Mở khóa đặc quyền tư vấn không gian nội thất chuẩn kiến trúc sư, bảo hành tận tâm và ưu đãi độc bản.' ) ); ?></p>

			<ul class="ax-privilege-list">
				<li class="ax-privilege-item">
					<div class="ax-privilege-icon-wrap">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="8" width="18" height="12" rx="2"></rect><path d="M12 8V4a2 2 0 0 1 2-2h4"></path><path d="M8 8H4"></path></svg>
					</div>
					<div class="ax-privilege-item-content">
						<h4><?php echo esc_html( furniva_t( '10% Welcome Voucher', 'Ưu Đãi 10% Chào Mừng Hội Viên' ) ); ?></h4>
						<p><?php echo esc_html( furniva_t( 'Apply code WELCOME10 instantly on your first luxury furniture order.', 'Nhận ngay mã WELCOME10 giảm trực tiếp 10% khi sở hữu món đồ đầu tiên.' ) ); ?></p>
					</div>
				</li>

				<li class="ax-privilege-item">
					<div class="ax-privilege-icon-wrap">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
					</div>
					<div class="ax-privilege-item-content">
						<h4><?php echo esc_html( furniva_t( '5-Year Structural Warranty', 'Bảo Hành Kết Cấu 5 Năm Toàn Diện' ) ); ?></h4>
						<p><?php echo esc_html( furniva_t( 'Automatic digital warranty vault for all engineered wood & fittings.', 'Hệ thống bảo hành điện tử trọn vòng đời cho khung gỗ, bản lề và ray trượt.' ) ); ?></p>
					</div>
				</li>

				<li class="ax-privilege-item">
					<div class="ax-privilege-icon-wrap">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline></svg>
					</div>
					<div class="ax-privilege-item-content">
						<h4><?php echo esc_html( furniva_t( '1-on-1 3D & AR Room Styling', 'Tư Vấn Thiết Kế & Mô Phỏng AR 1:1' ) ); ?></h4>
						<p><?php echo esc_html( furniva_t( 'Place 1:1 scale furniture into your actual living room with AR technology.', 'Trực tiếp ướm thử mẫu tủ, kệ sách vào căn hộ thực tế qua camera điện thoại.' ) ); ?></p>
					</div>
				</li>

				<li class="ax-privilege-item">
					<div class="ax-privilege-icon-wrap">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg>
					</div>
					<div class="ax-privilege-item-content">
						<h4><?php echo esc_html( furniva_t( 'Complimentary White-Glove Delivery', 'Giao Nhận & Lắp Đặt Tận Phòng Khách' ) ); ?></h4>
						<p><?php echo esc_html( furniva_t( 'Free installation by certified craftsmen on qualifying orders.', 'Đội ngũ kỹ thuật viên lành nghề lắp ráp chỉn chu, dọn dẹp sạch sẽ.' ) ); ?></p>
					</div>
				</li>
			</ul>

			<div class="ax-privilege-trust-box">
				<span class="ax-privilege-stars">★★★★★</span>
				<p class="ax-privilege-trust-text"><?php echo esc_html( furniva_t( 'Trusted by 12,000+ luxury homeowners across Vietnam. 99.4% satisfaction score.', 'Hơn 12.000+ gia chủ tinh hoa đã tin chọn AXGNUD cho không gian sống.' ) ); ?></p>
			</div>
		</aside>
	</div>
</div>

<?php do_action( 'woocommerce_after_customer_login_form' ); ?>
