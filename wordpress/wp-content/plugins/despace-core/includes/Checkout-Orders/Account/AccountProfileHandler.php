<?php
/**
 * Customer Account Profile & Registration Policy Handler for DeSpace (M3 Phase 4 / Issue #33).
 *
 * Extends and validates WooCommerce native My Account (`[woocommerce_my_account]`)
 * profile updates and registration policies strictly through WordPress User APIs
 * and `WC_Customer`. Never accesses password hashes via SQL, never builds a custom
 * authentication/reset engine, and always preserves WooCommerce Privacy/Account settings.
 *
 * @package DeSpace\CheckoutOrders\Account
 */

namespace DeSpace\CheckoutOrders\Account;

use DeSpace\CheckoutOrders\Checkout\AddressValidator;

defined( 'ABSPATH' ) || exit;

/**
 * Class AccountProfileHandler
 */
class AccountProfileHandler {

	/**
	 * Prevents duplicate hook registration.
	 *
	 * @var bool
	 */
	private static bool $hooks_registered = false;

	/**
	 * Register WooCommerce native My Account profile & registration validation hooks.
	 */
	public static function init(): void {
		if ( self::$hooks_registered ) {
			return;
		}

		self::$hooks_registered = true;

		add_filter( 'woocommerce_save_account_details_required_fields', array( __CLASS__, 'filter_required_fields' ), 20, 1 );
		add_action( 'woocommerce_save_account_details_errors', array( __CLASS__, 'validate_native_account_save_errors' ), 20, 2 );
		add_action( 'woocommerce_save_account_details', array( __CLASS__, 'save_extra_profile_fields' ), 20, 1 );
		add_filter( 'woocommerce_process_registration_errors', array( __CLASS__, 'validate_native_registration_errors' ), 20, 4 );
	}

	/**
	 * Check whether hooks have been registered.
	 *
	 * @return bool
	 */
	public static function are_hooks_registered(): bool {
		return self::$hooks_registered;
	}

	/**
	 * Determine whether WooCommerce allows My Account self-registration.
	 *
	 * @return bool
	 */
	public static function is_registration_enabled(): bool {
		if ( ! function_exists( 'get_option' ) ) {
			return false;
		}
		return 'yes' === (string) get_option( 'woocommerce_enable_myaccount_registration', 'no' );
	}

	/**
	 * Keep WooCommerce standard required account profile fields intact.
	 *
	 * @param array $fields Native required account fields.
	 * @return array
	 */
	public static function filter_required_fields( array $fields ): array {
		$fields['account_first_name']   = $fields['account_first_name'] ?? __( 'Tên', 'despace-core' );
		$fields['account_last_name']    = $fields['account_last_name'] ?? __( 'Họ', 'despace-core' );
		$fields['account_display_name'] = $fields['account_display_name'] ?? __( 'Tên hiển thị', 'despace-core' );
		$fields['account_email']        = $fields['account_email'] ?? __( 'Địa chỉ email', 'despace-core' );
		return $fields;
	}

	/**
	 * Read authenticated customer profile cleanly without exposing password hashes or internal flags.
	 *
	 * Always scopes strictly to `get_current_user_id()`; rejects unauthenticated callers or attempts
	 * to inspect another user ID.
	 *
	 * @param int $requested_user_id Optional requested user ID (rejected if mismatched).
	 * @return array|\WP_Error Profile summary or WP_Error.
	 */
	public static function get_profile_data( int $requested_user_id = 0 ) {
		$current_user_id = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
		if ( $current_user_id <= 0 ) {
			return new \WP_Error(
				'despace_authentication_required',
				__( 'Vui lòng đăng nhập để xem thông tin tài khoản.', 'despace-core' ),
				array( 'status' => 401 )
			);
		}

		if ( $requested_user_id > 0 && $requested_user_id !== $current_user_id ) {
			return new \WP_Error(
				'despace_forbidden_profile_access',
				__( 'Bạn không có quyền truy cập hồ sơ của khách hàng khác.', 'despace-core' ),
				array( 'status' => 403 )
			);
		}

		$user = function_exists( 'get_userdata' ) ? get_userdata( $current_user_id ) : null;
		if ( ! $user || ! is_object( $user ) ) {
			return new \WP_Error( 'despace_user_not_found', __( 'Không tìm thấy tài khoản.', 'despace-core' ), array( 'status' => 404 ) );
		}

		$phone = '';
		if ( class_exists( 'WC_Customer' ) ) {
			$customer = new \WC_Customer( $current_user_id );
			if ( method_exists( $customer, 'get_billing_phone' ) ) {
				$phone = (string) $customer->get_billing_phone();
			}
		} elseif ( function_exists( 'get_user_meta' ) ) {
			$phone = (string) get_user_meta( $current_user_id, 'billing_phone', true );
		}

		return array(
			'first_name'   => self::sanitize_text( (string) ( $user->first_name ?? '' ) ),
			'last_name'    => self::sanitize_text( (string) ( $user->last_name ?? '' ) ),
			'display_name' => self::sanitize_text( (string) ( $user->display_name ?? '' ) ),
			'email'        => self::sanitize_email( (string) ( $user->user_email ?? '' ) ),
			'phone'        => self::sanitize_text( $phone ),
		);
	}

	/**
	 * Hook into `woocommerce_save_account_details_errors` to validate phone and password rules.
	 *
	 * @param \WP_Error $errors WooCommerce account error bag.
	 * @param object    $user   Pending user object prepared by WC_Form_Handler.
	 */
	public static function validate_native_account_save_errors( \WP_Error $errors, object $user ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Already verified by WC_Form_Handler::save_account_details().
		$post = self::unslash_array( $_POST ?? array() );
		if ( isset( $post['billing_phone'] ) && '' !== trim( (string) $post['billing_phone'] ) ) {
			if ( ! AddressValidator::validate_phone( (string) $post['billing_phone'] ) ) {
				$errors->add(
					'despace_invalid_account_phone',
					__( 'Số điện thoại trong hồ sơ không hợp lệ.', 'despace-core' )
				);
			}
		}

		$user_id = (int) ( $user->ID ?? 0 );
		if ( $user_id > 0 && '' !== (string) ( $user->user_pass ?? '' ) && strlen( (string) $user->user_pass ) < 8 ) {
			$errors->add(
				'despace_weak_password',
				__( 'Mật khẩu mới cần tối thiểu 8 ký tự.', 'despace-core' )
			);
		}
	}

	/**
	 * Persist optional sanitized billing_phone via WC_Customer on native WooCommerce account save.
	 *
	 * @param int $user_id Saved WordPress user ID.
	 */
	public static function save_extra_profile_fields( int $user_id ): void {
		$current_user_id = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
		if ( $user_id <= 0 || $current_user_id !== $user_id ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in WC_Form_Handler::save_account_details().
		$post = self::unslash_array( $_POST ?? array() );
		if ( ! isset( $post['billing_phone'] ) ) {
			return;
		}

		$clean_phone = self::sanitize_text( (string) $post['billing_phone'] );
		if ( '' !== $clean_phone && ! AddressValidator::validate_phone( $clean_phone ) ) {
			return;
		}

		if ( class_exists( 'WC_Customer' ) ) {
			$customer = new \WC_Customer( $user_id );
			if ( method_exists( $customer, 'set_billing_phone' ) ) {
				$customer->set_billing_phone( $clean_phone );
				$customer->save();
			}
		}
	}

	/**
	 * Enforce WooCommerce registration setting and generic email conflict handling.
	 *
	 * @param \WP_Error $errors   Registration error bag.
	 * @param string    $username Submitted username.
	 * @param string    $password Submitted password.
	 * @param string    $email    Submitted email.
	 * @return \WP_Error
	 */
	public static function validate_native_registration_errors(
		\WP_Error $errors,
		string $username,
		string $password,
		string $email
	): \WP_Error {
		if ( ! self::is_registration_enabled() ) {
			$errors->add(
				'despace_registration_disabled',
				__( 'Chức năng đăng ký tài khoản hiện đang tắt theo cấu hình cửa hàng.', 'despace-core' )
			);
			return $errors;
		}

		$clean_email = self::sanitize_email( $email );
		if ( '' === $clean_email || ! AddressValidator::validate_email( $clean_email ) ) {
			$errors->add(
				'despace_invalid_registration_email',
				__( 'Vui lòng nhập địa chỉ email hợp lệ để đăng ký.', 'despace-core' )
			);
		}

		return $errors;
	}

	/**
	 * Validate and persist customer profile update programmatically through WC/WP APIs.
	 *
	 * Supports unit testing and AJAX/REST extension adapters without creating custom auth tables.
	 * Strips any forged `user_id`/`ID`/`role`, verifies WP CSRF nonce, checks `wp_check_password()`
	 * on password changes, and blocks duplicate emails cleanly.
	 *
	 * @param array $raw_input Submitted profile update payload.
	 * @return array|\WP_Error Updated sanitized profile or WP_Error.
	 */
	public static function update_profile( array $raw_input ) {
		unset( $raw_input['user_id'], $raw_input['ID'], $raw_input['role'] );

		$current_user_id = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
		if ( $current_user_id <= 0 ) {
			return new \WP_Error(
				'despace_authentication_required',
				__( 'Bạn cần đăng nhập để cập nhật hồ sơ.', 'despace-core' ),
				array( 'status' => 401 )
			);
		}

		$nonce = self::sanitize_text( (string) ( $raw_input['save-account-details-nonce'] ?? ( $raw_input['nonce'] ?? '' ) ) );
		if ( '' === $nonce || ! function_exists( 'wp_verify_nonce' ) || ! wp_verify_nonce( $nonce, 'save_account_details' ) ) {
			return new \WP_Error(
				'despace_invalid_nonce',
				__( 'Mã bảo mật phiên làm việc không hợp lệ.', 'despace-core' ),
				array( 'status' => 403 )
			);
		}

		$user = function_exists( 'get_userdata' ) ? get_userdata( $current_user_id ) : null;
		if ( ! $user || ! is_object( $user ) ) {
			return new \WP_Error( 'despace_user_not_found', __( 'Không tìm thấy tài khoản.', 'despace-core' ), array( 'status' => 404 ) );
		}

		$first_name   = self::sanitize_text( (string) ( $raw_input['account_first_name'] ?? '' ) );
		$last_name    = self::sanitize_text( (string) ( $raw_input['account_last_name'] ?? '' ) );
		$display_name = self::sanitize_text( (string) ( $raw_input['account_display_name'] ?? '' ) );
		$email        = self::sanitize_email( (string) ( $raw_input['account_email'] ?? '' ) );
		$phone        = isset( $raw_input['billing_phone'] ) ? self::sanitize_text( (string) $raw_input['billing_phone'] ) : null;

		$errors = new \WP_Error();

		if ( '' === $first_name || '' === $last_name ) {
			$errors->add( 'despace_missing_name', __( 'Vui lòng nhập đầy đủ Họ và Tên.', 'despace-core' ) );
		}
		if ( '' === $display_name ) {
			$display_name = trim( $last_name . ' ' . $first_name );
		}

		if ( ! AddressValidator::validate_email( $email ) ) {
			$errors->add( 'despace_invalid_email', __( 'Địa chỉ email không hợp lệ.', 'despace-core' ) );
		} elseif ( function_exists( 'email_exists' ) ) {
			$existing_id = (int) email_exists( $email );
			if ( $existing_id > 0 && $existing_id !== $current_user_id ) {
				$errors->add(
					'despace_email_exists',
					__( 'Không thể cập nhật sang địa chỉ email này. Vui lòng kiểm tra lại.', 'despace-core' )
				);
			}
		}

		if ( null !== $phone && '' !== $phone && ! AddressValidator::validate_phone( $phone ) ) {
			$errors->add( 'despace_invalid_phone', __( 'Số điện thoại không hợp lệ.', 'despace-core' ) );
		}

		$pass_cur = (string) ( $raw_input['password_current'] ?? '' );
		$pass_1   = (string) ( $raw_input['password_1'] ?? '' );
		$pass_2   = (string) ( $raw_input['password_2'] ?? '' );
		$new_pass = '';

		if ( '' !== $pass_1 || '' !== $pass_2 || '' !== $pass_cur ) {
			if ( '' === $pass_cur || ! function_exists( 'wp_check_password' ) || ! wp_check_password( $pass_cur, (string) ( $user->user_pass ?? '' ), $current_user_id ) ) {
				$errors->add( 'despace_invalid_current_password', __( 'Mật khẩu hiện tại không chính xác.', 'despace-core' ) );
			} elseif ( strlen( $pass_1 ) < 8 ) {
				$errors->add( 'despace_weak_password', __( 'Mật khẩu mới cần tối thiểu 8 ký tự.', 'despace-core' ) );
			} elseif ( ! hash_equals( $pass_1, $pass_2 ) ) {
				$errors->add( 'despace_password_mismatch', __( 'Xác nhận mật khẩu mới không khớp.', 'despace-core' ) );
			} else {
				$new_pass = $pass_1;
			}
		}

		if ( self::bag_has_errors( $errors ) ) {
			self::emit_notices( $errors );
			return $errors;
		}

		$update_payload = array(
			'ID'           => $current_user_id,
			'first_name'   => $first_name,
			'last_name'    => $last_name,
			'display_name' => $display_name,
			'user_email'   => $email,
		);
		if ( '' !== $new_pass ) {
			$update_payload['user_pass'] = $new_pass;
		}

		if ( function_exists( 'wp_update_user' ) ) {
			$wp_res = wp_update_user( $update_payload );
			if ( is_wp_error( $wp_res ) ) {
				return $wp_res;
			}
		}

		if ( class_exists( 'WC_Customer' ) ) {
			$customer = new \WC_Customer( $current_user_id );
			if ( method_exists( $customer, 'set_first_name' ) ) $customer->set_first_name( $first_name );
			if ( method_exists( $customer, 'set_last_name' ) ) $customer->set_last_name( $last_name );
			if ( method_exists( $customer, 'set_display_name' ) ) $customer->set_display_name( $display_name );
			if ( method_exists( $customer, 'set_email' ) ) $customer->set_email( $email );
			if ( null !== $phone && method_exists( $customer, 'set_billing_phone' ) ) {
				$customer->set_billing_phone( $phone );
			}
			if ( method_exists( $customer, 'save' ) ) {
				$customer->save();
			}
		}

		if ( function_exists( 'wc_add_notice' ) ) {
			wc_add_notice( __( 'Cập nhật thông tin tài khoản thành công.', 'despace-core' ), 'success' );
		}

		return self::get_profile_data( $current_user_id );
	}

	/**
	 * Validate standalone registration request against WooCommerce registration settings.
	 *
	 * @param array $raw_input Registration fields (`email`, `username`).
	 * @return \WP_Error|bool True when allowed, WP_Error on denial/validation error.
	 */
	public static function validate_registration_request( array $raw_input ) {
		if ( ! self::is_registration_enabled() ) {
			return new \WP_Error(
				'despace_registration_disabled',
				__( 'Chức năng đăng ký tài khoản hiện đang tắt.', 'despace-core' ),
				array( 'status' => 403 )
			);
		}

		$email = self::sanitize_email( (string) ( $raw_input['email'] ?? '' ) );
		if ( ! AddressValidator::validate_email( $email ) ) {
			return new \WP_Error(
				'despace_invalid_registration_email',
				__( 'Vui lòng nhập email hợp lệ.', 'despace-core' ),
				array( 'status' => 400 )
			);
		}

		if ( function_exists( 'email_exists' ) && email_exists( $email ) ) {
			return new \WP_Error(
				'despace_registration_conflict',
				__( 'Không thể hoàn tất đăng ký với thông tin này. Vui lòng đăng nhập hoặc đặt lại mật khẩu.', 'despace-core' ),
				array( 'status' => 409 )
			);
		}

		return true;
	}

	private static function emit_notices( \WP_Error $errors ): void {
		if ( ! function_exists( 'wc_add_notice' ) ) {
			return;
		}
		$msgs = method_exists( $errors, 'get_error_messages' ) ? (array) $errors->get_error_messages() : array( $errors->get_error_message() );
		foreach ( $msgs as $msg ) {
			if ( '' !== (string) $msg ) {
				wc_add_notice( (string) $msg, 'error' );
			}
		}
	}

	private static function bag_has_errors( \WP_Error $errors ): bool {
		return method_exists( $errors, 'has_errors' ) ? (bool) $errors->has_errors() : '' !== $errors->get_error_code();
	}

	private static function unslash_array( $val ): array {
		if ( function_exists( 'wp_unslash' ) ) {
			$val = wp_unslash( $val );
		}
		return is_array( $val ) ? $val : array();
	}

	private static function sanitize_email( string $email ): string {
		return function_exists( 'sanitize_email' ) ? sanitize_email( $email ) : trim( strip_tags( $email ) );
	}

	private static function sanitize_text( string $text ): string {
		return function_exists( 'sanitize_text_field' ) ? sanitize_text_field( $text ) : trim( strip_tags( $text ) );
	}
}

