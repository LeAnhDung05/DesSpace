<?php
/**
 * DeSpace Luxury Edition — Customer Authentication & Access Gate Handler
 *
 * 1. Handles comprehensive registration fields (First/Last name, Phone, City, Password confirmation, Terms),
 *    validation, and automatic profile meta synchronization.
 * 2. Enforces customer authentication requirement: customers must be logged in to use store features
 *    (Add to Cart, Buy Now, Wishlist, Checkout, AR Visualizer, and Reviews).
 *
 * @package DeSpaceChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ==========================================================================
   1. REGISTRATION FORM VALIDATION & PERSISTENCE
   ========================================================================== */

/**
 * Validate custom registration fields on WooCommerce customer registration.
 *
 * @param string   $username          Customer username.
 * @param string   $email             Customer email.
 * @param WP_Error $validation_errors Validation errors object.
 * @return WP_Error
 */
function axgnud_validate_registration_fields( $username, $email, $validation_errors ) {
	// 1. First Name (Tên)
	if ( isset( $_POST['billing_first_name'] ) && '' === trim( sanitize_text_field( wp_unslash( $_POST['billing_first_name'] ) ) ) ) {
		$validation_errors->add(
			'billing_first_name_error',
			esc_html( furniva_t( 'Please enter your first name.', 'Vui lòng nhập Tên của bạn.' ) )
		);
	}

	// 2. Last Name (Họ và tên đệm)
	if ( isset( $_POST['billing_last_name'] ) && '' === trim( sanitize_text_field( wp_unslash( $_POST['billing_last_name'] ) ) ) ) {
		$validation_errors->add(
			'billing_last_name_error',
			esc_html( furniva_t( 'Please enter your last name.', 'Vui lòng nhập Họ và tên đệm của bạn.' ) )
		);
	}

	// 3. Phone Number (Số điện thoại)
	if ( isset( $_POST['billing_phone'] ) ) {
		$raw_phone   = sanitize_text_field( wp_unslash( $_POST['billing_phone'] ) );
		$clean_phone = preg_replace( '/[^0-9]/', '', $raw_phone );
		if ( empty( $clean_phone ) || strlen( $clean_phone ) < 9 || strlen( $clean_phone ) > 12 ) {
			$validation_errors->add(
				'billing_phone_error',
				esc_html( furniva_t( 'Please enter a valid phone number (9-11 digits).', 'Vui lòng nhập số điện thoại hợp lệ (9 - 11 chữ số).' ) )
			);
		}
	}

	// 4. Password confirmation (if password generation is disabled)
	if ( 'no' === get_option( 'woocommerce_registration_generate_password' ) ) {
		$password         = isset( $_POST['password'] ) ? $_POST['password'] : '';
		$password_confirm = isset( $_POST['password_confirm'] ) ? $_POST['password_confirm'] : '';

		if ( empty( $password ) ) {
			$validation_errors->add(
				'password_empty_error',
				esc_html( furniva_t( 'Please enter a password.', 'Vui lòng nhập mật khẩu tài khoản.' ) )
			);
		} elseif ( strlen( $password ) < 8 ) {
			$validation_errors->add(
				'password_short_error',
				esc_html( furniva_t( 'Password must be at least 8 characters long.', 'Mật khẩu phải có tối thiểu 8 ký tự.' ) )
			);
		} elseif ( $password !== $password_confirm ) {
			$validation_errors->add(
				'password_mismatch_error',
				esc_html( furniva_t( 'Passwords do not match. Please re-enter.', 'Mật khẩu xác nhận không trùng khớp. Vui lòng kiểm tra lại.' ) )
			);
		}
	}

	// 5. Terms Agreement Checkbox
	if ( empty( $_POST['terms_agree'] ) ) {
		$validation_errors->add(
			'terms_agree_error',
			esc_html( furniva_t( 'Please agree to the DeSpace Privilege Club Terms and Privacy Policy to complete registration.', 'Quý khách vui lòng đồng ý với Điều khoản dịch vụ & Chính sách quyền riêng tư để hoàn tất đăng ký.' ) )
		);
	}

	return $validation_errors;
}
add_action( 'woocommerce_register_post', 'axgnud_validate_registration_fields', 10, 3 );

/**
 * Save custom registration fields to customer profile meta.
 *
 * @param int $customer_id Customer user ID.
 */
function axgnud_save_registration_fields( $customer_id ) {
	$first_name = ! empty( $_POST['billing_first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['billing_first_name'] ) ) : '';
	$last_name  = ! empty( $_POST['billing_last_name'] )  ? sanitize_text_field( wp_unslash( $_POST['billing_last_name'] ) )  : '';
	$phone      = ! empty( $_POST['billing_phone'] )      ? sanitize_text_field( wp_unslash( $_POST['billing_phone'] ) )      : '';
	$city       = ! empty( $_POST['billing_city'] )       ? sanitize_text_field( wp_unslash( $_POST['billing_city'] ) )       : '';

	if ( $first_name ) {
		update_user_meta( $customer_id, 'first_name', $first_name );
		update_user_meta( $customer_id, 'billing_first_name', $first_name );
		update_user_meta( $customer_id, 'shipping_first_name', $first_name );
	}

	if ( $last_name ) {
		update_user_meta( $customer_id, 'last_name', $last_name );
		update_user_meta( $customer_id, 'billing_last_name', $last_name );
		update_user_meta( $customer_id, 'shipping_last_name', $last_name );
	}

	if ( $first_name || $last_name ) {
		$display_name = trim( $last_name . ' ' . $first_name );
		wp_update_user( array(
			'ID'           => $customer_id,
			'display_name' => $display_name,
		) );
	}

	if ( $phone ) {
		$clean_phone = preg_replace( '/[^0-9]/', '', $phone );
		update_user_meta( $customer_id, 'billing_phone', $clean_phone );
	}

	if ( $city ) {
		update_user_meta( $customer_id, 'billing_city', $city );
		update_user_meta( $customer_id, 'shipping_city', $city );
	}

	// Set initial membership level & audit info
	update_user_meta( $customer_id, 'axgnud_membership_tier', 'Silver Privilege' );
	update_user_meta( $customer_id, 'axgnud_registered_via', 'web_luxury_register' );
}
add_action( 'woocommerce_created_customer', 'axgnud_save_registration_fields', 10, 1 );

/* ==========================================================================
   2. ACCESS GATE: REQUIRE LOGIN TO USE STORE FEATURES
   ========================================================================== */

/**
 * Gate 1: Require login to access Checkout.
 * Redirects guests from /checkout/ to /my-account/?action=login&redirect_to=checkout.
 */
function axgnud_require_login_for_checkout() {
	if ( function_exists( 'is_checkout' ) && is_checkout() && ! is_user_logged_in() && ! is_order_received_page() ) {
		wc_add_notice(
			furniva_t(
				'Please sign in or create an DeSpace Privilege account to complete your purchase and activate 5-year warranty.',
				'Quý khách vui lòng đăng nhập hoặc đăng ký tài khoản thành viên DeSpace Privilege để hoàn tất thanh toán và kích hoạt bảo hành 5 năm.'
			),
			'notice'
		);
		$login_url = add_query_arg(
			array(
				'action'      => 'login',
				'redirect_to' => urlencode( wc_get_checkout_url() ),
			),
			wc_get_page_permalink( 'myaccount' )
		);
		wp_safe_redirect( $login_url );
		exit;
	}
}
add_action( 'template_redirect', 'axgnud_require_login_for_checkout' );

/**
 * Gate 2: Server-side validation requiring login before adding items to Cart.
 *
 * @param bool $passed     Validation status.
 * @param int  $product_id Product ID.
 * @param int  $quantity   Quantity.
 * @return bool
 */
function axgnud_require_login_for_add_to_cart( $passed, $product_id, $quantity ) {
	if ( ! is_user_logged_in() ) {
		$login_url    = add_query_arg( 'action', 'login', wc_get_page_permalink( 'myaccount' ) );
		$register_url = add_query_arg( 'action', 'register', wc_get_page_permalink( 'myaccount' ) );

		$notice = sprintf(
			furniva_t(
				'Please <a href="%1$s" style="text-decoration:underline;font-weight:600;">Sign In</a> or <a href="%2$s" style="text-decoration:underline;font-weight:600;">Register an Account</a> to add items to your cart.',
				'Quý khách vui lòng <a href="%1$s" style="text-decoration:underline;font-weight:600;">Đăng Nhập</a> hoặc <a href="%2$s" style="text-decoration:underline;font-weight:600;">Đăng Ký Hội Viên</a> để thêm sản phẩm vào giỏ hàng.'
			),
			esc_url( $login_url ),
			esc_url( $register_url )
		);
		wc_add_notice( $notice, 'error' );
		return false;
	}
	return $passed;
}
add_filter( 'woocommerce_add_to_cart_validation', 'axgnud_require_login_for_add_to_cart', 10, 3 );

/**
 * Gate 3: Handle post-login and post-registration redirect to preserve user journey.
 *
 * @param string  $redirect Default redirect URL.
 * @param WP_User $user     Logged in or registered user.
 * @return string
 */
function axgnud_preserve_auth_redirect( $redirect, $user ) {
	if ( ! empty( $_REQUEST['redirect_to'] ) ) {
		$target = esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) );
		// Validate that target is an internal URL
		$parsed_home   = wp_parse_url( home_url() );
		$parsed_target = wp_parse_url( $target );

		if ( empty( $parsed_target['host'] ) || ( isset( $parsed_home['host'] ) && $parsed_home['host'] === $parsed_target['host'] ) ) {
			return $target;
		}
	}
	return $redirect;
}
add_filter( 'woocommerce_login_redirect', 'axgnud_preserve_auth_redirect', 10, 2 );
add_filter( 'woocommerce_registration_redirect', 'axgnud_preserve_auth_redirect', 10, 2 );

/**
 * Gate 4: Render Luxury Auth Gate Modal in footer for unauthenticated visitors.
 */
function axgnud_render_auth_modal() {
	if ( is_user_logged_in() ) {
		return;
	}

	$account_url  = wc_get_page_permalink( 'myaccount' );
	$login_url    = add_query_arg( 'action', 'login', $account_url );
	$register_url = add_query_arg( 'action', 'register', $account_url );
	?>
	<div class="ax-auth-gate-modal" id="axAuthGateModal" aria-hidden="true" role="dialog" aria-labelledby="axAuthGateTitle">
		<div class="ax-auth-gate-backdrop" id="axAuthGateBackdrop"></div>
		<div class="ax-auth-gate-dialog">
			<button type="button" class="ax-auth-gate-close" id="axAuthGateClose" aria-label="<?php echo esc_attr( furniva_t( 'Close', 'Đóng' ) ); ?>">&times;</button>
			
			<div class="ax-auth-gate-eyebrow">
				<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
				<span><?php echo esc_html( furniva_t( 'DeSpace PRIVILEGE CLUB', 'CÂU LẠC BỘ ĐẶC QUYỀN DeSpace' ) ); ?></span>
			</div>
			
			<h3 class="ax-auth-gate-title" id="axAuthGateTitle"><?php echo esc_html( furniva_t( 'Sign In to Unlock Full Features', 'Vui Lòng Đăng Nhập Để Tiếp Tục' ) ); ?></h3>
			
			<p class="ax-auth-gate-desc" id="axAuthGateDesc">
				<?php echo esc_html( furniva_t( 'To add items to your cart, save favorites, or proceed to checkout, please sign in or register an DeSpace account.', 'Để thêm sản phẩm vào giỏ hàng, lưu danh sách yêu thích và tiến hành thanh toán, quý khách vui lòng đăng nhập hoặc gia nhập hội viên DeSpace.' ) ); ?>
			</p>

			<div class="ax-auth-gate-perk">
				<span class="ax-auth-gate-badge">✦ <?php echo esc_html( furniva_t( 'VIP WELCOME GIFT', 'ĐẶC QUYỀN HỘI VIÊN' ) ); ?></span>
				<p><?php echo esc_html( furniva_t( 'Sign up now to get 10% off with code WELCOME10 and activate 5-year structural warranty.', 'Gia nhập ngay hôm nay nhận mã giảm 10% WELCOME10 và kích hoạt bảo hành kết cấu 5 năm toàn diện.' ) ); ?></p>
			</div>

			<div class="ax-auth-gate-actions">
				<a href="<?php echo esc_url( $login_url ); ?>" class="ax-auth-gate-btn ax-auth-gate-btn--primary" id="axAuthGateLoginLink">
					<span><?php echo esc_html( furniva_t( 'Sign In to Account', 'ĐĂNG NHẬP VÀO TÀI KHOẢN' ) ); ?></span>
					<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
				</a>
				<a href="<?php echo esc_url( $register_url ); ?>" class="ax-auth-gate-btn ax-auth-gate-btn--secondary" id="axAuthGateRegisterLink">
					<span><?php echo esc_html( furniva_t( 'Create Account (10% Off)', 'GIA NHẬP HỘI VIÊN (GIẢM 10%)' ) ); ?></span>
				</a>
			</div>
		</div>
	</div>
	<?php
}
add_action( 'wp_footer', 'axgnud_render_auth_modal', 99 );
