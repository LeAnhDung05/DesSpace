<?php
/**
 * Customer Account Billing & Shipping Address Handler for DeSpace (M3 Phase 4 / Issue #33).
 *
 * Integrates WooCommerce My Account `/edit-address/` (`WC_Customer`) directly with
 * `VietnamRegions::validate_and_normalize_address()` under the 2-tier 2025 Vietnam
 * administrative model (Province -> Ward/Commune/Special Zone -> Detailed Address).
 * Preserves legacy `address_2` values, never requires district input, and stores metadata
 * strictly through `WC_Customer` CRUD APIs.
 *
 * @package DeSpace\CheckoutOrders\Account
 */

namespace DeSpace\CheckoutOrders\Account;

use DeSpace\CheckoutOrders\Shipping\VietnamRegions;

defined( 'ABSPATH' ) || exit;

/**
 * Class AccountAddressHandler
 */
class AccountAddressHandler {

	public const ALLOWED_ADDRESS_TYPES = array( 'billing', 'shipping' );

	/**
	 * Prevents duplicate hook registration.
	 *
	 * @var bool
	 */
	private static bool $hooks_registered = false;

	/**
	 * Register WooCommerce My Account address form & validation hooks.
	 */
	public static function init(): void {
		if ( self::$hooks_registered ) {
			return;
		}

		self::$hooks_registered = true;

		add_filter( 'woocommerce_address_to_edit', array( __CLASS__, 'filter_address_to_edit_fields' ), 20, 2 );
		add_action( 'woocommerce_after_save_address_validation', array( __CLASS__, 'validate_native_save_address' ), 20, 4 );
		add_action( 'woocommerce_customer_save_address', array( __CLASS__, 'sync_native_saved_customer_address' ), 20, 4 );
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
	 * Configure WooCommerce `/edit-address/{type}` form fields for 2-tier VN model without mandatory address_2.
	 *
	 * @param array  $fields       Fields returned for the edit address screen.
	 * @param string $load_address 'billing' or 'shipping'.
	 * @return array
	 */
	public static function filter_address_to_edit_fields( array $fields, string $load_address = 'billing' ): array {
		$prefix = in_array( $load_address, self::ALLOWED_ADDRESS_TYPES, true ) ? $load_address : 'billing';

		if ( isset( $fields[ $prefix . '_state' ] ) ) {
			$fields[ $prefix . '_state' ]['label']    = __( 'Tỉnh / Thành phố', 'despace-core' );
			$fields[ $prefix . '_state' ]['required'] = true;
		}
		if ( isset( $fields[ $prefix . '_city' ] ) ) {
			$fields[ $prefix . '_city' ]['label']    = __( 'Phường / Xã / Đặc khu', 'despace-core' );
			$fields[ $prefix . '_city' ]['required'] = true;
		}
		if ( isset( $fields[ $prefix . '_address_1' ] ) ) {
			$fields[ $prefix . '_address_1' ]['label']    = __( 'Địa chỉ chi tiết', 'despace-core' );
			$fields[ $prefix . '_address_1' ]['required'] = true;
		}
		if ( isset( $fields[ $prefix . '_address_2' ] ) ) {
			$fields[ $prefix . '_address_2' ]['required'] = false;
		}
		if ( isset( $fields[ $prefix . '_postcode' ] ) ) {
			$fields[ $prefix . '_postcode' ]['required'] = false;
		}

		$current_user_id = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
		$saved_comm_code = '';
		if ( $current_user_id > 0 && class_exists( 'WC_Customer' ) ) {
			$customer        = new \WC_Customer( $current_user_id );
			$saved_comm_code = method_exists( $customer, 'get_meta' )
				? (string) $customer->get_meta( '_' . $prefix . '_despace_commune_code', true )
				: '';
		}

		$fields[ $prefix . '_despace_commune_code' ] = array(
			'label'    => __( 'Mã Phường / Xã / Đặc khu', 'despace-core' ),
			'required' => false,
			'type'     => 'text',
			'value'    => self::sanitize_code( $saved_comm_code ),
			'class'    => array( 'form-row-wide', 'despace-commune-code-field' ),
			'priority' => 61,
		);

		return $fields;
	}

	/**
	 * Hook into WooCommerce's native `/edit-address/` save validation before `$customer->save()`.
	 *
	 * @param int    $user_id      Customer user ID.
	 * @param string $load_address 'billing' or 'shipping'.
	 * @param array  $address      Fields configuration.
	 * @param object $customer     WC_Customer instance.
	 */
	public static function validate_native_save_address(
		int $user_id,
		string $load_address,
		array $address,
		object $customer
	): void {
		if ( ! in_array( $load_address, self::ALLOWED_ADDRESS_TYPES, true ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Already verified in WC_Form_Handler::save_address().
		$post = self::unslash_array( $_POST ?? array() );
		$raw  = self::extract_raw_input_for_type( $post, $load_address );
		$norm = VietnamRegions::validate_and_normalize_address( $raw );

		if ( is_wp_error( $norm ) ) {
			if ( function_exists( 'wc_add_notice' ) ) {
				wc_add_notice( $norm->get_error_message(), 'error' );
			}
			return;
		}

		self::apply_normalized_to_customer_object( $customer, $load_address, $norm );
	}

	/**
	 * Ensure normalized DeSpace codes persist when WooCommerce fires `woocommerce_customer_save_address`.
	 *
	 * @param int    $user_id      Customer user ID.
	 * @param string $load_address 'billing' or 'shipping'.
	 * @param array  $address      Fields array.
	 * @param object $customer     WC_Customer instance.
	 */
	public static function sync_native_saved_customer_address(
		int $user_id,
		string $load_address,
		array $address,
		object $customer
	): void {
		if ( ! in_array( $load_address, self::ALLOWED_ADDRESS_TYPES, true ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in WC_Form_Handler::save_address().
		$post = self::unslash_array( $_POST ?? array() );
		$raw  = self::extract_raw_input_for_type( $post, $load_address );
		$norm = VietnamRegions::validate_and_normalize_address( $raw );
		if ( is_wp_error( $norm ) ) {
			return;
		}

		self::apply_normalized_to_customer_object( $customer, $load_address, $norm );
		if ( method_exists( $customer, 'save' ) ) {
			$customer->save();
		}
	}

	/**
	 * Read customer saved billing or shipping address via `WC_Customer` CRUD, preserving legacy `address_2`.
	 *
	 * @param string $type    'billing' or 'shipping'.
	 * @param int    $user_id Optional explicit user ID (must match current user).
	 * @return array|\WP_Error Address summary or WP_Error.
	 */
	public static function get_customer_address( string $type = 'billing', int $user_id = 0 ) {
		$current_user_id = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
		if ( $current_user_id <= 0 ) {
			return new \WP_Error( 'despace_authentication_required', __( 'Vui lòng đăng nhập.', 'despace-core' ), array( 'status' => 401 ) );
		}
		if ( $user_id > 0 && $user_id !== $current_user_id ) {
			return new \WP_Error( 'despace_forbidden_address_access', __( 'Không có quyền truy cập địa chỉ khách hàng khác.', 'despace-core' ), array( 'status' => 403 ) );
		}

		$prefix = in_array( $type, self::ALLOWED_ADDRESS_TYPES, true ) ? $type : 'billing';
		if ( ! class_exists( 'WC_Customer' ) ) {
			return new \WP_Error( 'despace_woocommerce_unavailable', __( 'WooCommerce is not available.', 'despace-core' ) );
		}

		$customer  = new \WC_Customer( $current_user_id );
		$country   = method_exists( $customer, 'get_' . $prefix . '_country' ) ? (string) $customer->{'get_' . $prefix . '_country'}() : 'VN';
		$state     = method_exists( $customer, 'get_' . $prefix . '_state' ) ? (string) $customer->{'get_' . $prefix . '_state'}() : '';
		$city      = method_exists( $customer, 'get_' . $prefix . '_city' ) ? (string) $customer->{'get_' . $prefix . '_city'}() : '';
		$address_1 = method_exists( $customer, 'get_' . $prefix . '_address_1' ) ? (string) $customer->{'get_' . $prefix . '_address_1'}() : '';
		$address_2 = method_exists( $customer, 'get_' . $prefix . '_address_2' ) ? (string) $customer->{'get_' . $prefix . '_address_2'}() : '';
		$postcode  = method_exists( $customer, 'get_' . $prefix . '_postcode' ) ? (string) $customer->{'get_' . $prefix . '_postcode'}() : '';

		$region_code  = method_exists( $customer, 'get_meta' ) ? (string) $customer->get_meta( '_' . $prefix . '_despace_region_code', true ) : '';
		$commune_code = method_exists( $customer, 'get_meta' ) ? (string) $customer->get_meta( '_' . $prefix . '_despace_commune_code', true ) : '';

		$provinces   = VietnamRegions::get_provinces();
		$region_name = ( '' !== $state && isset( $provinces[ $state ] ) )
			? $provinces[ $state ]['name']
			: ( '' !== $region_code && isset( $provinces[ $region_code ] ) ? $provinces[ $region_code ]['name'] : $state );

		return array(
			'type'           => $prefix,
			'country'        => self::sanitize_text( $country ?: 'VN' ),
			'state'          => self::sanitize_text( $state ),
			'region_code'    => self::sanitize_code( $region_code ?: ( isset( $provinces[ $state ] ) ? $state : '' ) ),
			'region_name'    => self::sanitize_text( $region_name ),
			'commune_code'   => self::sanitize_code( $commune_code ),
			'commune_name'   => self::sanitize_text( $city ),
			'address_detail' => self::sanitize_text( $address_1 ),
			'legacy_line_2'  => self::sanitize_text( $address_2 ),
			'postcode'       => self::sanitize_text( $postcode ),
		);
	}

	/**
	 * Validate and persist customer billing or shipping address programmatically via `WC_Customer`.
	 *
	 * Enforces login, nonce (`woocommerce-edit_address`), 2-tier VietnamRegions validation, and preserves
	 * legacy `address_2` if not explicitly overwritten.
	 *
	 * @param string $type      'billing' or 'shipping'.
	 * @param array  $raw_input Address form payload.
	 * @return array|\WP_Error Saved address payload or WP_Error.
	 */
	public static function save_customer_address( string $type, array $raw_input ) {
		unset( $raw_input['customer_id'], $raw_input['user_id'] );

		$current_user_id = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
		if ( $current_user_id <= 0 ) {
			return new \WP_Error( 'despace_authentication_required', __( 'Vui lòng đăng nhập để cập nhật địa chỉ.', 'despace-core' ), array( 'status' => 401 ) );
		}

		$prefix = sanitize_key( $type );
		if ( ! in_array( $prefix, self::ALLOWED_ADDRESS_TYPES, true ) ) {
			return new \WP_Error( 'despace_invalid_address_type', __( 'Loại địa chỉ không hợp lệ.', 'despace-core' ), array( 'status' => 400 ) );
		}

		$nonce = self::sanitize_text( (string) ( $raw_input['woocommerce-edit-address-nonce'] ?? ( $raw_input['nonce'] ?? '' ) ) );
		if ( '' === $nonce || ! function_exists( 'wp_verify_nonce' ) || ! wp_verify_nonce( $nonce, 'woocommerce-edit_address' ) ) {
			return new \WP_Error( 'despace_invalid_nonce', __( 'Mã bảo mật không hợp lệ.', 'despace-core' ), array( 'status' => 403 ) );
		}

		$raw_addr   = self::extract_raw_input_for_type( $raw_input, $prefix );
		$normalized = VietnamRegions::validate_and_normalize_address( $raw_addr );
		if ( is_wp_error( $normalized ) ) {
			return $normalized;
		}

		if ( empty( $normalized['is_complete'] ) ) {
			return new \WP_Error(
				'despace_incomplete_address',
				__( 'Vui lòng nhập đầy đủ Tỉnh/Thành phố, Phường/Xã/Đặc khu và Địa chỉ chi tiết.', 'despace-core' ),
				array( 'status' => 400 )
			);
		}

		if ( ! class_exists( 'WC_Customer' ) ) {
			return new \WP_Error( 'despace_woocommerce_unavailable', __( 'WooCommerce is not available.', 'despace-core' ) );
		}

		$customer = new \WC_Customer( $current_user_id );
		self::apply_normalized_to_customer_object( $customer, $prefix, $normalized );
		if ( method_exists( $customer, 'save' ) ) {
			$customer->save();
		}

		$saved                        = self::get_customer_address( $prefix, $current_user_id );
		$saved['verification_mode']   = ( '' !== $normalized['region_code'] && isset( VietnamRegions::get_provinces()[ $normalized['region_code'] ] ) )
			? 'verified_dataset'
			: 'free_text_fallback';

		return $saved;
	}

	/**
	 * Extract prefixed address inputs cleanly into `VietnamRegions::validate_and_normalize_address()` structure.
	 *
	 * @param array  $input  Posted input.
	 * @param string $prefix 'billing' or 'shipping'.
	 * @return array
	 */
	private static function extract_raw_input_for_type( array $input, string $prefix ): array {
		$country       = strtoupper( self::sanitize_text( (string) ( $input[ $prefix . '_country' ] ?? ( $input['country'] ?? 'VN' ) ) ) );
		$state_raw     = self::sanitize_text( (string) ( $input[ $prefix . '_state' ] ?? '' ) );
		$region_code   = self::sanitize_code( (string) ( $input[ $prefix . '_despace_region_code' ] ?? ( $input['region_code'] ?? '' ) ) );
		$region_name   = self::sanitize_text( (string) ( $input[ $prefix . '_despace_region_name' ] ?? ( $input['region_name'] ?? '' ) ) );
		$commune_code  = self::sanitize_code( (string) ( $input[ $prefix . '_despace_commune_code' ] ?? ( $input['commune_code'] ?? '' ) ) );
		$commune_name  = self::sanitize_text( (string) ( $input[ $prefix . '_city' ] ?? ( $input['commune_name'] ?? '' ) ) );
		$address_line1 = self::sanitize_text( (string) ( $input[ $prefix . '_address_1' ] ?? ( $input['address_detail'] ?? '' ) ) );
		$postcode      = self::sanitize_text( (string) ( $input[ $prefix . '_postcode' ] ?? ( $input['postcode'] ?? '' ) ) );

		if ( '' === $region_code && '' !== $state_raw ) {
			$provinces = VietnamRegions::get_provinces();
			if ( 1 === preg_match( '/^\d{2}$/', $state_raw ) || isset( $provinces[ $state_raw ] ) ) {
				$region_code = $state_raw;
			} elseif ( '' === $region_name ) {
				$region_name = $state_raw;
			}
		}

		return array(
			'country'        => '' !== $country ? $country : 'VN',
			'region_code'    => $region_code,
			'region_name'    => $region_name,
			'commune_code'   => $commune_code,
			'commune_name'   => $commune_name,
			'address_detail' => $address_line1,
			'postcode'       => $postcode,
		);
	}

	/**
	 * Apply normalized address setters & DeSpace meta keys onto `WC_Customer` without altering legacy `address_2`.
	 *
	 * @param object $customer   WC_Customer instance.
	 * @param string $prefix     'billing' or 'shipping'.
	 * @param array  $normalized Output from VietnamRegions::validate_and_normalize_address().
	 */
	private static function apply_normalized_to_customer_object( object $customer, string $prefix, array $normalized ): void {
		$map = $normalized['wc_mapping'] ?? array();

		$country_setter   = 'set_' . $prefix . '_country';
		$state_setter     = 'set_' . $prefix . '_state';
		$city_setter      = 'set_' . $prefix . '_city';
		$addr1_setter     = 'set_' . $prefix . '_address_1';
		$postcode_setter  = 'set_' . $prefix . '_postcode';

		if ( method_exists( $customer, $country_setter ) ) {
			$customer->$country_setter( (string) ( $map['country'] ?? 'VN' ) );
		}
		if ( method_exists( $customer, $state_setter ) ) {
			$customer->$state_setter( (string) ( $map['state'] ?? '' ) );
		}
		if ( method_exists( $customer, $city_setter ) ) {
			$customer->$city_setter( (string) ( $map['city'] ?? '' ) );
		}
		if ( method_exists( $customer, $addr1_setter ) ) {
			$customer->$addr1_setter( (string) ( $map['address_1'] ?? '' ) );
		}
		if ( method_exists( $customer, $postcode_setter ) ) {
			$customer->$postcode_setter( (string) ( $map['postcode'] ?? '' ) );
		}

		if ( method_exists( $customer, 'update_meta_data' ) ) {
			$customer->update_meta_data( '_' . $prefix . '_despace_region_code', self::sanitize_code( (string) ( $normalized['region_code'] ?? '' ) ) );
			$customer->update_meta_data( '_' . $prefix . '_despace_commune_code', self::sanitize_code( (string) ( $normalized['commune_code'] ?? '' ) ) );
		}
	}

	private static function unslash_array( $val ): array {
		if ( function_exists( 'wp_unslash' ) ) {
			$val = wp_unslash( $val );
		}
		return is_array( $val ) ? $val : array();
	}

	private static function sanitize_code( string $code ): string {
		return preg_replace( '/[^A-Za-z0-9_\-]/', '', self::sanitize_text( $code ) ) ?? '';
	}

	private static function sanitize_text( string $text ): string {
		return function_exists( 'sanitize_text_field' ) ? sanitize_text_field( $text ) : trim( strip_tags( $text ) );
	}
}

