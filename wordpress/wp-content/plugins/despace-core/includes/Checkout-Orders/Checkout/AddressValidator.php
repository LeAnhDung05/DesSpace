<?php
/**
 * Checkout Recipient & Vietnam 2-Tier Address Validator for DeSpace (M3 Phase 2A / Issue #17).
 *
 * Delegates all Vietnam administrative normalization and 34-province / 3,321-commune
 * lookups directly to `VietnamRegions::validate_and_normalize_address()`.
 * Never introduces a separate region dataset, never requires district (`quận/huyện`),
 * and never alters historical `address_2` values on existing orders.
 *
 * @package DeSpace\CheckoutOrders\Checkout
 */

namespace DeSpace\CheckoutOrders\Checkout;

use DeSpace\CheckoutOrders\Shipping\VietnamRegions;

defined( 'ABSPATH' ) || exit;

/**
 * Class AddressValidator
 */
class AddressValidator {

	/**
	 * Prevents duplicate hook registration.
	 *
	 * @var bool
	 */
	private static bool $hooks_registered = false;

	/**
	 * Register WooCommerce Classic Checkout address field & normalization hooks.
	 */
	public static function init(): void {
		if ( self::$hooks_registered ) {
			return;
		}

		self::$hooks_registered = true;

		add_filter( 'woocommerce_checkout_fields', array( __CLASS__, 'filter_checkout_fields' ), 20, 1 );
		add_filter( 'woocommerce_checkout_posted_data', array( __CLASS__, 'normalize_posted_checkout_data' ), 20, 1 );
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
	 * Adjust WooCommerce Classic Checkout fields for DeSpace's 2-tier 2025 VN model.
	 *
	 * Ensures state (province/municipality), city (ward/commune/special zone), and address_1
	 * (detailed address) follow official labels; keeps address_2 optional/hidden and does not
	 * force postcode unless store zones require it.
	 *
	 * @param array $fields WooCommerce checkout field groups.
	 * @return array
	 */
	public static function filter_checkout_fields( array $fields ): array {
		foreach ( array( 'billing', 'shipping' ) as $group ) {
			if ( ! isset( $fields[ $group ] ) || ! is_array( $fields[ $group ] ) ) {
				$fields[ $group ] = array();
			}

			$fields[ $group ][ $group . '_country' ] = array_merge(
				$fields[ $group ][ $group . '_country' ] ?? array(),
				array(
					'required' => true,
					'default'  => 'VN',
				)
			);

			$fields[ $group ][ $group . '_state' ] = array_merge(
				$fields[ $group ][ $group . '_state' ] ?? array(),
				array(
					'label'    => __( 'Tỉnh / Thành phố', 'despace-core' ),
					'required' => true,
					'priority' => 50,
				)
			);

			$fields[ $group ][ $group . '_city' ] = array_merge(
				$fields[ $group ][ $group . '_city' ] ?? array(),
				array(
					'label'       => __( 'Phường / Xã / Đặc khu', 'despace-core' ),
					'placeholder' => __( 'Chọn hoặc nhập Phường / Xã / Đặc khu', 'despace-core' ),
					'required'    => true,
					'priority'    => 60,
				)
			);

			$fields[ $group ][ $group . '_address_1' ] = array_merge(
				$fields[ $group ][ $group . '_address_1' ] ?? array(),
				array(
					'label'       => __( 'Địa chỉ chi tiết', 'despace-core' ),
					'placeholder' => __( 'Số nhà, tên đường, tòa nhà / thôn / ấp', 'despace-core' ),
					'required'    => true,
					'priority'    => 70,
				)
			);

			$fields[ $group ][ $group . '_address_2' ] = array_merge(
				$fields[ $group ][ $group . '_address_2' ] ?? array(),
				array(
					'required' => false,
					'priority' => 75,
				)
			);

			$fields[ $group ][ $group . '_postcode' ] = array_merge(
				$fields[ $group ][ $group . '_postcode' ] ?? array(),
				array(
					'required' => false,
					'priority' => 80,
				)
			);

			// Optional 5-digit DeSpace commune code field for dropdown selection in Classic Checkout.
			$fields[ $group ][ $group . '_despace_commune_code' ] = array_merge(
				$fields[ $group ][ $group . '_despace_commune_code' ] ?? array(),
				array(
					'type'     => 'text',
					'label'    => __( 'Mã Phường / Xã / Đặc khu', 'despace-core' ),
					'required' => false,
					'class'    => array( 'form-row-wide', 'despace-commune-code-field' ),
					'priority' => 61,
				)
			);
		}

		return $fields;
	}

	/**
	 * Normalize posted billing and shipping address strings into canonical server values.
	 *
	 * Strips any tampered pricing/total keys if passed in raw posted payload, and preserves
	 * fallback text cleanly when the official dataset is not active.
	 *
	 * @param array $data WooCommerce checkout posted data.
	 * @return array
	 */
	public static function normalize_posted_checkout_data( array $data ): array {
		foreach ( array( 'total', 'subtotal', 'discount_total', 'shipping_total', 'shipping_amount', 'tax_total', 'order_total' ) as $authoritative_key ) {
			unset( $data[ $authoritative_key ] );
		}

		$data = self::apply_canonical_address_to_posted_group( $data, 'billing' );

		if ( ! empty( $data['ship_to_different_address'] ) ) {
			$data = self::apply_canonical_address_to_posted_group( $data, 'shipping' );
		}

		return $data;
	}

	/**
	 * Validate recipient contact fields and both billing/shipping addresses.
	 *
	 * Supports separate shipping address when `ship_to_different_address` is truthy, or reuses
	 * billing address as WooCommerce does natively when unchecked.
	 *
	 * @param array       $posted_data Checkout posted input.
	 * @param object|null $wc_errors   Optional WP_Error instance for field-level checkout notices.
	 * @return array|\WP_Error Validated structure on success, or WP_Error on failure.
	 */
	public static function validate_checkout_addresses( array $posted_data, ?object $wc_errors = null ) {
		$errors_bag = ( null !== $wc_errors && is_wp_error( $wc_errors ) )
			? $wc_errors
			: new \WP_Error();

		$billing = self::validate_recipient_group( $posted_data, 'billing', true, $errors_bag );

		$ship_different = self::is_truthy_flag( $posted_data['ship_to_different_address'] ?? false );
		if ( $ship_different ) {
			$shipping = self::validate_recipient_group( $posted_data, 'shipping', false, $errors_bag );
		} else {
			$shipping = is_wp_error( $billing ) ? $billing : self::mirror_billing_to_shipping( $billing );
		}

		if ( method_exists( $errors_bag, 'has_errors' ) && $errors_bag->has_errors() ) {
			return $errors_bag;
		}
		if ( '' !== $errors_bag->get_error_code() ) {
			return $errors_bag;
		}

		return array(
			'ship_to_different_address' => $ship_different,
			'billing'                   => $billing,
			'shipping'                  => $shipping,
		);
	}

	/**
	 * Validate phone number supporting Vietnamese formats and standard international E.164 inputs.
	 *
	 * @param string $raw_phone Raw phone input.
	 * @return bool
	 */
	public static function validate_phone( string $raw_phone ): bool {
		$trimmed = self::sanitize_text( $raw_phone );
		if ( '' === $trimmed ) {
			return false;
		}

		// Reject unexpected letters or control characters before stripping separators.
		if ( 1 === preg_match( '/[A-Za-z]/', $trimmed ) ) {
			return false;
		}

		$normalized = preg_replace( '/[\s\.\-\(\)]+/', '', $trimmed );
		$normalized = is_string( $normalized ) ? $normalized : '';

		// Must start optionally with + and contain only digits (7..15 digits per E.164 / VN standards).
		if ( 1 !== preg_match( '/^\+?[0-9]{7,15}$/', $normalized ) ) {
			return false;
		}

		$digits_only = ltrim( $normalized, '+' );
		// Reject obviously bogus all-identical-digit sequences like 0000000000.
		if ( 1 === preg_match( '/^(\d)\1+$/', $digits_only ) ) {
			return false;
		}

		// Explicit check for Vietnam domestic mobile / landline prefix when country prefix is 0 or 84.
		if ( 0 === strpos( $digits_only, '84' ) ) {
			return strlen( $digits_only ) >= 10 && strlen( $digits_only ) <= 11;
		}
		if ( 0 === strpos( $digits_only, '0' ) ) {
			return strlen( $digits_only ) >= 10 && strlen( $digits_only ) <= 11;
		}

		// Allow valid international telephone numbers (7..15 digits without leading + or 0).
		return strlen( $digits_only ) >= 7 && strlen( $digits_only ) <= 15;
	}

	/**
	 * Validate recipient email address cleanly.
	 *
	 * @param string $raw_email Raw email string.
	 * @return bool
	 */
	public static function validate_email( string $raw_email ): bool {
		$email = trim( self::sanitize_text( $raw_email ) );
		if ( '' === $email || strlen( $email ) > 254 ) {
			return false;
		}

		if ( function_exists( 'is_email' ) ) {
			return false !== is_email( $email );
		}

		return false !== filter_var( $email, FILTER_VALIDATE_EMAIL );
	}

	/**
	 * Normalize a single posted address group back into WooCommerce posted keys when valid.
	 *
	 * @param array  $data   Posted data.
	 * @param string $prefix 'billing' or 'shipping'.
	 * @return array
	 */
	private static function apply_canonical_address_to_posted_group( array $data, string $prefix ): array {
		$input      = self::extract_raw_address_from_posted( $data, $prefix );
		$normalized = VietnamRegions::validate_and_normalize_address( $input );
		if ( is_wp_error( $normalized ) ) {
			return $data;
		}

		$mapping                                     = $normalized['wc_mapping'];
		$data[ $prefix . '_country' ]                = $mapping['country'];
		$data[ $prefix . '_state' ]                  = $mapping['state'];
		$data[ $prefix . '_city' ]                   = $mapping['city'];
		$data[ $prefix . '_address_1' ]              = $mapping['address_1'];
		$data[ $prefix . '_postcode' ]               = $mapping['postcode'];
		$data[ $prefix . '_despace_commune_code' ]   = $normalized['commune_code'];

		return $data;
	}

	/**
	 * Validate recipient name, contact info (email/phone), and 2-tier address for a group.
	 *
	 * @param array     $posted_data  Input checkout fields.
	 * @param string    $prefix       'billing' or 'shipping'.
	 * @param bool      $require_contact Whether email & phone are mandatory.
	 * @param \WP_Error $errors_bag   Collector for validation errors.
	 * @return array|\WP_Error Normalized group data or WP_Error when invalid.
	 */
	private static function validate_recipient_group(
		array $posted_data,
		string $prefix,
		bool $require_contact,
		\WP_Error $errors_bag
	) {
		$first_name = self::sanitize_text( (string) ( $posted_data[ $prefix . '_first_name' ] ?? '' ) );
		$last_name  = self::sanitize_text( (string) ( $posted_data[ $prefix . '_last_name' ] ?? '' ) );
		$full_name  = self::sanitize_text( (string) ( $posted_data[ $prefix . '_full_name' ] ?? '' ) );

		if ( '' === $first_name && '' === $last_name && '' === $full_name ) {
			self::add_field_error(
				$errors_bag,
				$prefix . '_first_name',
				__( 'Vui lòng nhập họ tên người nhận hàng.', 'despace-core' )
			);
		}
		if ( '' === $full_name ) {
			$full_name = trim( $last_name . ' ' . $first_name );
		}

		$email = self::sanitize_text( (string) ( $posted_data[ $prefix . '_email' ] ?? '' ) );
		$phone = self::sanitize_text( (string) ( $posted_data[ $prefix . '_phone' ] ?? ( $posted_data['billing_phone'] ?? '' ) ) );

		if ( $require_contact || '' !== $email ) {
			if ( ! self::validate_email( $email ) ) {
				self::add_field_error(
					$errors_bag,
					$prefix . '_email',
					__( 'Địa chỉ email không hợp lệ. Vui lòng kiểm tra lại.', 'despace-core' )
				);
			}
		}

		if ( $require_contact || '' !== self::sanitize_text( (string) ( $posted_data[ $prefix . '_phone' ] ?? '' ) ) ) {
			if ( ! self::validate_phone( $phone ) ) {
				self::add_field_error(
					$errors_bag,
					$prefix . '_phone',
					__( 'Số điện thoại nhận hàng không hợp lệ.', 'despace-core' )
				);
			}
		}

		$raw_address = self::extract_raw_address_from_posted( $posted_data, $prefix );
		$normalized  = VietnamRegions::validate_and_normalize_address( $raw_address );

		if ( is_wp_error( $normalized ) ) {
			$err_code  = $normalized->get_error_code();
			$field_key = self::map_region_error_to_field_key( $err_code, $prefix );
			self::add_field_error( $errors_bag, $field_key, $normalized->get_error_message(), $err_code );
			return $normalized;
		}

		if ( '' === $normalized['country'] ) {
			self::add_field_error( $errors_bag, $prefix . '_country', __( 'Vui lòng chọn quốc gia giao hàng.', 'despace-core' ) );
		}
		if ( '' === $normalized['region_code'] && '' === $normalized['region_name'] ) {
			self::add_field_error( $errors_bag, $prefix . '_state', __( 'Vui lòng chọn hoặc nhập Tỉnh/Thành phố.', 'despace-core' ) );
		}
		if ( '' === $normalized['commune_name'] ) {
			self::add_field_error( $errors_bag, $prefix . '_city', __( 'Vui lòng chọn hoặc nhập Phường/Xã/Đặc khu.', 'despace-core' ) );
		}
		if ( '' === $normalized['address_detail'] ) {
			self::add_field_error( $errors_bag, $prefix . '_address_1', __( 'Vui lòng nhập địa chỉ chi tiết (số nhà, tên đường).', 'despace-core' ) );
		}

		$verification = self::build_verification_flags( $normalized );

		return array_merge(
			$normalized,
			$verification,
			array(
				'first_name' => $first_name,
				'last_name'  => $last_name,
				'full_name'  => $full_name,
				'email'      => $email,
				'phone'      => $phone,
			)
		);
	}

	/**
	 * Extract normalized VietnamRegions input payload from WooCommerce checkout fields.
	 *
	 * @param array  $posted_data Checkout fields array.
	 * @param string $prefix      'billing' or 'shipping'.
	 * @return array
	 */
	private static function extract_raw_address_from_posted( array $posted_data, string $prefix ): array {
		$country       = strtoupper( self::sanitize_text( (string) ( $posted_data[ $prefix . '_country' ] ?? 'VN' ) ) );
		$raw_state     = self::sanitize_text( (string) ( $posted_data[ $prefix . '_state' ] ?? '' ) );
		$raw_reg_code  = self::sanitize_text( (string) ( $posted_data[ $prefix . '_despace_region_code' ] ?? ( $posted_data['region_code'] ?? '' ) ) );
		$raw_reg_name  = self::sanitize_text( (string) ( $posted_data[ $prefix . '_despace_region_name' ] ?? ( $posted_data['region_name'] ?? '' ) ) );
		$raw_comm_code = self::sanitize_text( (string) ( $posted_data[ $prefix . '_despace_commune_code' ] ?? ( $posted_data['commune_code'] ?? '' ) ) );
		$raw_city      = self::sanitize_text( (string) ( $posted_data[ $prefix . '_city' ] ?? '' ) );
		$raw_addr_1    = self::sanitize_text( (string) ( $posted_data[ $prefix . '_address_1' ] ?? '' ) );
		$raw_postcode  = self::sanitize_text( (string) ( $posted_data[ $prefix . '_postcode' ] ?? '' ) );

		// Determine whether state carries a 2-digit province code or free-text province name.
		$region_code = $raw_reg_code;
		$region_name = $raw_reg_name;
		if ( '' === $region_code && '' !== $raw_state ) {
			if ( 1 === preg_match( '/^\d{2}$/', $raw_state ) ) {
				$region_code = $raw_state;
			} else {
				// Let VietnamRegions either resolve against loaded 34 provinces or accept free-text in fallback mode.
				$provinces = VietnamRegions::get_provinces();
				if ( isset( $provinces[ $raw_state ] ) ) {
					$region_code = $raw_state;
				} else {
					$region_name = '' !== $region_name ? $region_name : $raw_state;
				}
			}
		}

		return array(
			'country'        => '' !== $country ? $country : 'VN',
			'region_code'    => $region_code,
			'region_name'    => $region_name,
			'commune_code'   => $raw_comm_code,
			'commune_name'   => $raw_city,
			'address_detail' => $raw_addr_1,
			'postcode'       => $raw_postcode,
		);
	}

	/**
	 * Compute honest verification status for address provenance (verified dataset vs free-text).
	 *
	 * @param array $normalized Normalized address array from VietnamRegions.
	 * @return array
	 */
	private static function build_verification_flags( array $normalized ): array {
		if ( 'VN' !== (string) ( $normalized['country'] ?? 'VN' ) ) {
			return array(
				'region_verified'   => false,
				'commune_verified'  => false,
				'verification_mode' => 'country_non_vn',
			);
		}

		$provinces       = VietnamRegions::get_provinces();
		$region_code     = (string) ( $normalized['region_code'] ?? '' );
		$commune_code    = (string) ( $normalized['commune_code'] ?? '' );
		$region_verified = ( '' !== $region_code && isset( $provinces[ $region_code ] ) );

		$commune_verified = false;
		if ( $region_verified && '' !== $commune_code && VietnamRegions::is_commune_dropdown_enabled( $region_code ) ) {
			$communes         = VietnamRegions::get_communes( $region_code );
			$commune_verified = isset( $communes[ $commune_code ] );
		}

		return array(
			'region_verified'   => $region_verified,
			'commune_verified'  => $commune_verified,
			'verification_mode' => $region_verified ? 'verified_dataset' : 'free_text_fallback',
		);
	}

	/**
	 * Copy validated billing structure into shipping structure when shipping to same address.
	 *
	 * @param array $billing Validated billing group.
	 * @return array
	 */
	private static function mirror_billing_to_shipping( array $billing ): array {
		return $billing;
	}

	/**
	 * Add structured error to WP_Error with field context compatible with WC_Checkout.
	 *
	 * @param \WP_Error $errors    Target error bag.
	 * @param string    $field_key Field identifier.
	 * @param string    $message   Localized message.
	 * @param string    $code_hint Optional explicit error code.
	 */
	private static function add_field_error(
		\WP_Error $errors,
		string $field_key,
		string $message,
		string $code_hint = ''
	): void {
		$err_code = '' !== $code_hint ? $code_hint : ( 'despace_invalid_' . $field_key );
		$errors->add( $err_code, $message, array( 'id' => $field_key ) );
	}

	/**
	 * Map VietnamRegions WP_Error code onto WooCommerce checkout field identifier.
	 *
	 * @param string $err_code Error code from VietnamRegions.
	 * @param string $prefix   'billing' or 'shipping'.
	 * @return string
	 */
	private static function map_region_error_to_field_key( string $err_code, string $prefix ): string {
		if ( false !== strpos( $err_code, 'commune' ) ) {
			return $prefix . '_city';
		}
		if ( false !== strpos( $err_code, 'region' ) ) {
			return $prefix . '_state';
		}
		return $prefix . '_address_1';
	}

	/**
	 * Interpret WooCommerce boolean/string flag reliably.
	 *
	 * @param mixed $val Raw flag value.
	 * @return bool
	 */
	private static function is_truthy_flag( $val ): bool {
		if ( is_bool( $val ) ) {
			return $val;
		}
		if ( is_numeric( $val ) ) {
			return 1 === (int) $val;
		}
		$str = strtolower( trim( (string) $val ) );
		return in_array( $str, array( '1', 'true', 'yes', 'on' ), true );
	}

	/**
	 * Sanitize UTF-8 text cleanly without corrupting Vietnamese diacritics.
	 *
	 * @param string $value Raw string.
	 * @return string
	 */
	private static function sanitize_text( string $value ): string {
		if ( function_exists( 'sanitize_text_field' ) ) {
			return sanitize_text_field( $value );
		}
		return trim( strip_tags( $value ) );
	}
}

