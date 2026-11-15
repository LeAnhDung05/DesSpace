<?php
/**
 * WooCommerce Shipping Notice & Rate Helper for DeSpace (M3 Phase 1B-B / Issue #32).
 *
 * Reads shipping zones, methods, and live shipping rates strictly from WooCommerce
 * (`WC()->shipping()`, `WC()->cart`, `WC()->customer`, `WC_Shipping_Rate`).
 * Never hardcodes shipping fees, free-shipping thresholds, or custom shipping tables.
 *
 * @package DeSpace\CheckoutOrders\Shipping
 */

namespace DeSpace\CheckoutOrders\Shipping;

use DeSpace\CheckoutOrders\Cart\CartHandler;

defined( 'ABSPATH' ) || exit;

/**
 * Class ShippingNoticeHelper
 */
class ShippingNoticeHelper {

	/**
	 * Prevents duplicate hook registration.
	 *
	 * @var bool
	 */
	private static bool $hooks_registered = false;

	/**
	 * Register WooCommerce shipping notice filters, AJAX estimator, and frontend config.
	 */
	public static function init(): void {
		if ( self::$hooks_registered ) {
			return;
		}

		self::$hooks_registered = true;

		add_filter( 'despace_m3_cart_frontend_config', array( __CLASS__, 'extend_frontend_config' ), 30, 1 );
		add_filter( 'woocommerce_cart_no_shipping_available_html', array( __CLASS__, 'filter_no_shipping_html' ), 10, 1 );
		add_filter( 'woocommerce_no_shipping_available_html', array( __CLASS__, 'filter_no_shipping_html' ), 10, 1 );

		add_action( 'wp_ajax_despace_shipping_estimate', array( __CLASS__, 'ajax_estimate_shipping' ) );
		add_action( 'wp_ajax_nopriv_despace_shipping_estimate', array( __CLASS__, 'ajax_estimate_shipping' ) );
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
	 * Extend M3 frontend configuration with Vietnam regions metadata and shipping estimator action.
	 *
	 * @param array $config Existing M3 frontend config.
	 * @return array
	 */
	public static function extend_frontend_config( array $config ): array {
		$config['actions'] = array_merge(
			isset( $config['actions'] ) && is_array( $config['actions'] ) ? $config['actions'] : array(),
			array(
				'estimate_shipping' => 'despace_shipping_estimate',
			)
		);

		$config['vietnam_regions'] = VietnamRegions::get_frontend_config();

		return $config;
	}

	/**
	 * Customize WooCommerce default "no shipping available" notice for Vietnamese UX.
	 *
	 * @param string $html Existing WooCommerce message.
	 * @return string
	 */
	public static function filter_no_shipping_html( string $html ): string {
		$msg = __(
			'Hiện chưa có phương thức vận chuyển phù hợp cho địa chỉ này. Vui lòng kiểm tra lại Tỉnh/Thành phố, Phường/Xã hoặc liên hệ DeSpace để được hỗ trợ giao nhận.',
			'despace-core'
		);

		if ( function_exists( 'esc_html' ) ) {
			return '<p class="despace-shipping-unavailable-notice">' . esc_html( $msg ) . '</p>';
		}

		return '<p class="despace-shipping-unavailable-notice">' . htmlspecialchars( $msg, ENT_QUOTES, 'UTF-8' ) . '</p>';
	}

	/**
	 * AJAX endpoint: Update shipping destination on WC()->customer and read live WooCommerce rates (POST only).
	 */
	public static function ajax_estimate_shipping(): void {
		if ( ! self::is_post_request() ) {
			self::send_json_response( self::method_not_allowed_response() );
			return;
		}

		$raw_post = self::get_unslashed_post();
		$result   = self::handle_estimate_request( $raw_post );
		self::send_json_response( $result );
	}

	/**
	 * Handle shipping estimate request payload with nonce verification and anti-spoofing.
	 *
	 * Completely ignores any shipping_amount, shipping_cost, or fee passed from frontend.
	 *
	 * @param array $raw_input Request payload.
	 * @return array
	 */
	public static function handle_estimate_request( array $raw_input ): array {
		$nonce_error = self::validate_request_nonce( $raw_input );
		if ( null !== $nonce_error ) {
			return $nonce_error;
		}

		$address_payload = array(
			'country'        => $raw_input['country'] ?? 'VN',
			'region_code'    => $raw_input['region_code'] ?? ( $raw_input['state'] ?? '' ),
			'region_name'    => $raw_input['region_name'] ?? '',
			'commune_code'   => $raw_input['commune_code'] ?? '',
			'commune_name'   => $raw_input['commune_name'] ?? ( $raw_input['city'] ?? '' ),
			'address_detail' => $raw_input['address_detail'] ?? ( $raw_input['address_1'] ?? '' ),
			'postcode'       => $raw_input['postcode'] ?? '',
		);

		$result = self::get_shipping_summary( null, $address_payload );
		if ( is_wp_error( $result ) ) {
			return self::format_action_result( $result, 400 );
		}

		return self::format_action_result( $result, 200, array( 'action' => 'estimate_shipping' ) );
	}

	/**
	 * Read shipping eligibility, methods, and live rates strictly from WooCommerce.
	 *
	 * @param mixed      $cart             Optional WC_Cart instance.
	 * @param array|null $address_override Optional normalized/raw destination update.
	 * @return array|\WP_Error
	 */
	public static function get_shipping_summary( $cart = null, ?array $address_override = null ) {
		if ( ! function_exists( 'WC' ) || ! WC() ) {
			return new \WP_Error(
				'despace_woocommerce_unavailable',
				__( 'WooCommerce is not available.', 'despace-core' ),
				array( 'status' => 503 )
			);
		}

		$wc = WC();
		if ( null === $wc->session && method_exists( $wc, 'initialize_session' ) ) {
			$wc->initialize_session();
		}
		if ( null === $cart ) {
			if ( null === $wc->cart && function_exists( 'wc_load_cart' ) ) {
				wc_load_cart();
			}
			$cart = $wc->cart;
		}

		if ( ! $cart || ! is_object( $cart ) ) {
			return new \WP_Error(
				'despace_cart_unavailable',
				__( 'WooCommerce cart session is not initialized.', 'despace-core' ),
				array( 'status' => 503 )
			);
		}

		$destination = null;
		if ( null !== $address_override ) {
			$normalized = VietnamRegions::validate_and_normalize_address( $address_override );
			if ( is_wp_error( $normalized ) ) {
				return $normalized;
			}
			$destination = $normalized;
			self::apply_destination_to_customer( $wc, $normalized );
		} else {
			$destination = self::read_destination_from_customer( $wc );
		}

		$provinces        = VietnamRegions::get_provinces();
		$region_code      = (string) ( $destination['region_code'] ?? '' );
		$commune_code     = (string) ( $destination['commune_code'] ?? '' );
		$country          = (string) ( $destination['country'] ?? 'VN' );
		$region_verified  = ( 'VN' === $country && '' !== $region_code && isset( $provinces[ $region_code ] ) );
		$commune_verified = false;
		if ( $region_verified && '' !== $commune_code && VietnamRegions::is_commune_dropdown_enabled( $region_code ) ) {
			$prov_communes    = VietnamRegions::get_communes( $region_code );
			$commune_verified = isset( $prov_communes[ $commune_code ] );
		}

		$destination_data = array(
			'country'            => $country,
			'region_code'        => $region_code,
			'region_name'        => $destination['region_name'],
			'commune_code'       => $commune_code,
			'commune_name'       => $destination['commune_name'],
			'address_detail'     => $destination['address_detail'],
			'postcode'           => $destination['postcode'],
			'commune_input_mode' => $destination['commune_input_mode'],
		);

		$limitation_note = __(
			'WooCommerce Shipping Zones match destinations by Country, Province/State, and Postcode; native zones do not natively segment all 3,321 wards/communes without postcode or zone rules.',
			'despace-core'
		);

		if ( $cart->is_empty() ) {
			return self::build_summary_envelope(
				'empty_cart',
				false,
				__( 'Giỏ hàng đang trống. Vui lòng thêm sản phẩm để kiểm tra phí vận chuyển.', 'despace-core' ),
				$destination_data,
				array(),
				'',
				0.0,
				array(),
				$limitation_note,
				'none',
				$region_verified,
				$commune_verified
			);
		}

		if ( method_exists( $cart, 'needs_shipping' ) && ! $cart->needs_shipping() ) {
			return self::build_summary_envelope(
				'not_needed',
				true,
				__( 'Đơn hàng hiện tại không yêu cầu vận chuyển.', 'despace-core' ),
				$destination_data,
				array(),
				'',
				0.0,
				array(),
				$limitation_note,
				'none',
				$region_verified,
				$commune_verified
			);
		}

		$missing_fields = self::detect_missing_address_fields( $destination );
		if ( ! empty( $missing_fields ) ) {
			return self::build_summary_envelope(
				'address_incomplete',
				false,
				__( 'Vui lòng chọn Tỉnh/Thành phố để xác định phương thức và phí vận chuyển.', 'despace-core' ),
				$destination_data,
				array(),
				'',
				0.0,
				$missing_fields,
				$limitation_note,
				'none',
				$region_verified,
				$commune_verified
			);
		}

		if ( method_exists( $cart, 'calculate_shipping' ) ) {
			$cart->calculate_shipping();
		}
		if ( method_exists( $cart, 'calculate_totals' ) ) {
			$cart->calculate_totals();
		}

		$packages = self::resolve_shipping_packages( $wc, $cart );
		$chosen   = self::get_chosen_shipping_methods( $wc );
		$rates    = array();
		$chosen_id = '';

		foreach ( $packages as $package_index => $package ) {
			$pkg_chosen = $chosen[ $package_index ] ?? '';
			$pkg_rates  = isset( $package['rates'] ) && is_array( $package['rates'] ) ? $package['rates'] : array();

			foreach ( $pkg_rates as $rate_key => $rate ) {
				if ( ! is_object( $rate ) ) {
					continue;
				}
				$rate_id    = method_exists( $rate, 'get_id' ) ? (string) $rate->get_id() : (string) $rate_key;
				$method_id  = method_exists( $rate, 'get_method_id' ) ? (string) $rate->get_method_id() : '';
				$label      = method_exists( $rate, 'get_label' ) ? (string) $rate->get_label() : '';
				$cost       = method_exists( $rate, 'get_cost' ) ? (float) $rate->get_cost() : 0.0;
				$taxes      = method_exists( $rate, 'get_taxes' ) ? (array) $rate->get_taxes() : array();
				$tax_amount = (float) array_sum( array_map( 'floatval', $taxes ) );
				$total_cost = $cost + $tax_amount;
				$selected   = ( '' !== $pkg_chosen && $pkg_chosen === $rate_id );

				if ( $selected && '' === $chosen_id ) {
					$chosen_id = $rate_id;
				}

				$rates[] = array(
					'shipping_rate_id'      => $rate_id,
					'shipping_method_id'    => $method_id,
					'shipping_method_title' => $label,
					'shipping_amount'       => $cost,
					'shipping_tax'          => $tax_amount,
					'shipping_total'        => $total_cost,
					'shipping_amount_html'  => self::format_price( $total_cost ),
					'is_free'               => ( $cost <= 0.0 ),
					'selected'              => $selected,
				);
			}
		}

		if ( empty( $rates ) ) {
			$needs_postcode = '' === $destination_data['postcode'] && self::store_has_postcode_zones();
			$notice         = $needs_postcode
				? __( 'Chưa tìm thấy phương thức giao hàng cho địa chỉ hiện tại. Vui lòng nhập thêm mã bưu chính (Postcode) hoặc kiểm tra khu vực giao hàng.', 'despace-core' )
				: __( 'Địa chỉ nhận hàng hiện nằm ngoài khu vực giao hàng được hỗ trợ hoặc chưa có phương thức vận chuyển khả dụng.', 'despace-core' );

			return self::build_summary_envelope(
				'unavailable',
				false,
				$notice,
				$destination_data,
				array(),
				'',
				0.0,
				$needs_postcode ? array( 'postcode' ) : array(),
				$limitation_note,
				'none',
				$region_verified,
				$commune_verified
			);
		}

		if ( '' === $chosen_id && ! empty( $rates ) ) {
			$chosen_id             = $rates[0]['shipping_rate_id'];
			$rates[0]['selected']  = true;
		}

		$shipping_total = method_exists( $cart, 'get_shipping_total' )
			? (float) $cart->get_shipping_total() + ( method_exists( $cart, 'get_shipping_tax' ) ? (float) $cart->get_shipping_tax() : 0.0 )
			: (float) ( $rates[0]['shipping_total'] ?? 0.0 );

		$zone_match_level = self::resolve_zone_match_level( $destination_data, $packages, $rates, $region_verified );

		$notice = sprintf(
			/* translators: %d: number of available shipping methods */
			__( 'Có %d phương thức vận chuyển khả dụng cho địa chỉ nhận hàng của bạn.', 'despace-core' ),
			count( $rates )
		);

		return self::build_summary_envelope(
			'available',
			true,
			$notice,
			$destination_data,
			$rates,
			$chosen_id,
			$shipping_total,
			array(),
			$limitation_note,
			$zone_match_level,
			$region_verified,
			$commune_verified
		);
	}

	/**
	 * Build stable response payload envelope for shipping status and rates.
	 *
	 * @param string $status           Status key.
	 * @param bool   $can_deliver      Whether deliverable.
	 * @param string $notice           Human-readable status message.
	 * @param array  $destination      Normalized address data.
	 * @param array  $rates            Available WC shipping rates.
	 * @param string $chosen_rate_id   Selected rate ID.
	 * @param float  $shipping_total   Calculated WC shipping total.
	 * @param array  $missing_fields   Missing address fields if incomplete.
	 * @param string $limitation_note  Transparent WC zone granularity note.
	 * @param string $zone_match_level Matched WC zone granularity (postcode|province|country|fallback|none).
	 * @param bool   $region_verified  Whether province/municipality matches official dataset.
	 * @param bool   $commune_verified Whether ward/commune/special-zone matches official dataset.
	 * @return array
	 */
	private static function build_summary_envelope(
		string $status,
		bool $can_deliver,
		string $notice,
		array $destination,
		array $rates,
		string $chosen_rate_id,
		float $shipping_total,
		array $missing_fields,
		string $limitation_note,
		string $zone_match_level = 'none',
		bool $region_verified = false,
		bool $commune_verified = false
	): array {
		$chosen_method_id    = '';
		$chosen_method_title = '';
		foreach ( $rates as $rate_entry ) {
			if ( ( $rate_entry['shipping_rate_id'] ?? '' ) === $chosen_rate_id ) {
				$chosen_method_id    = (string) ( $rate_entry['shipping_method_id'] ?? '' );
				$chosen_method_title = (string) ( $rate_entry['shipping_method_title'] ?? '' );
				break;
			}
		}

		return array(
			'status'                      => $status,
			'can_deliver'                 => $can_deliver,
			'notice'                      => $notice,
			'requires_more_address_info'  => ! empty( $missing_fields ),
			'missing_fields'              => $missing_fields,
			'destination'                 => $destination,
			'rates'                       => $rates,
			'shipping_rate_id'            => $chosen_rate_id,
			'shipping_method_id'          => $chosen_method_id,
			'shipping_method_title'       => $chosen_method_title,
			'chosen_rate_id'              => $chosen_rate_id,
			'shipping_amount'             => $shipping_total,
			'shipping_amount_html'        => self::format_price( $shipping_total ),
			'zone_granularity_limitation' => $limitation_note,
			'zone_match_level'            => $zone_match_level,
			'region_verified'             => $region_verified,
			'commune_verified'            => $commune_verified,
			'shipping_estimate_only'      => true,
			'user_context'                => ( function_exists( 'is_user_logged_in' ) && is_user_logged_in() ) ? 'customer' : 'guest',
		);
	}

	/**
	 * Apply normalized address fields onto session-scoped WC()->customer strictly for rate calculation.
	 *
	 * Never invokes customer DB persistence save methods or alters saved WordPress user profile meta.
	 *
	 * @param object $wc         WooCommerce instance.
	 * @param array  $normalized Output from VietnamRegions::validate_and_normalize_address().
	 */
	private static function apply_destination_to_customer( object $wc, array $normalized ): void {
		$customer = $wc->customer ?? null;
		if ( ! $customer || ! is_object( $customer ) ) {
			return;
		}

		$mapping = $normalized['wc_mapping'];
		if ( method_exists( $customer, 'set_shipping_country' ) ) {
			$customer->set_shipping_country( $mapping['country'] );
		}
		if ( method_exists( $customer, 'set_shipping_state' ) ) {
			$customer->set_shipping_state( $mapping['state'] );
		}
		if ( method_exists( $customer, 'set_shipping_city' ) ) {
			$customer->set_shipping_city( $mapping['city'] );
		}
		if ( method_exists( $customer, 'set_shipping_address_1' ) ) {
			$customer->set_shipping_address_1( $mapping['address_1'] );
		}
		if ( method_exists( $customer, 'set_shipping_postcode' ) ) {
			$customer->set_shipping_postcode( $mapping['postcode'] );
		}
		if ( method_exists( $customer, 'set_calculated_shipping' ) ) {
			$customer->set_calculated_shipping( true );
		}
	}

	/**
	 * Read current shipping address from WC()->customer through VietnamRegions normalizer.
	 *
	 * @param object $wc WooCommerce instance.
	 * @return array
	 */
	private static function read_destination_from_customer( object $wc ): array {
		$customer = $wc->customer ?? null;
		$raw      = array( 'country' => 'VN' );

		if ( $customer && is_object( $customer ) ) {
			$raw = array(
				'country'        => method_exists( $customer, 'get_shipping_country' ) ? (string) $customer->get_shipping_country() : 'VN',
				'region_code'    => method_exists( $customer, 'get_shipping_state' ) ? (string) $customer->get_shipping_state() : '',
				'commune_name'   => method_exists( $customer, 'get_shipping_city' ) ? (string) $customer->get_shipping_city() : '',
				'address_detail' => method_exists( $customer, 'get_shipping_address_1' ) ? (string) $customer->get_shipping_address_1() : '',
				'postcode'       => method_exists( $customer, 'get_shipping_postcode' ) ? (string) $customer->get_shipping_postcode() : '',
			);
		}

		$normalized = VietnamRegions::validate_and_normalize_address( $raw );
		if ( ! is_wp_error( $normalized ) ) {
			return $normalized;
		}

		return array(
			'country'            => 'VN',
			'region_code'        => '',
			'region_name'        => '',
			'commune_code'       => '',
			'commune_name'       => '',
			'address_detail'     => '',
			'postcode'           => '',
			'commune_input_mode' => 'free_text',
		);
	}

	/**
	 * Check minimal required fields for shipping rate lookup without requiring district or postcode.
	 *
	 * @param array $destination Normalized destination.
	 * @return array List of missing field identifiers.
	 */
	private static function detect_missing_address_fields( array $destination ): array {
		$missing = array();
		if ( '' === (string) ( $destination['country'] ?? '' ) ) {
			$missing[] = 'country';
		}
		if ( '' === (string) ( $destination['region_code'] ?? '' ) && '' === (string) ( $destination['region_name'] ?? '' ) ) {
			$missing[] = 'region_code';
		}
		return $missing;
	}

	/**
	 * Obtain calculated shipping packages from WC()->shipping().
	 *
	 * @param object $wc   WooCommerce instance.
	 * @param object $cart WC_Cart instance.
	 * @return array
	 */
	private static function resolve_shipping_packages( object $wc, object $cart ): array {
		$shipping = method_exists( $wc, 'shipping' ) ? $wc->shipping() : ( $wc->shipping ?? null );
		if ( $shipping && is_object( $shipping ) ) {
			if ( method_exists( $shipping, 'get_packages' ) ) {
				$packages = (array) $shipping->get_packages();
				if ( ! empty( $packages ) ) {
					return $packages;
				}
			}
			if ( method_exists( $shipping, 'calculate_shipping' ) && method_exists( $cart, 'get_shipping_packages' ) ) {
				return (array) $shipping->calculate_shipping( $cart->get_shipping_packages() );
			}
		}

		if ( method_exists( $cart, 'get_shipping_packages' ) ) {
			return (array) $cart->get_shipping_packages();
		}

		return array();
	}

	/**
	 * Read chosen shipping method IDs stored in WC()->session.
	 *
	 * @param object $wc WooCommerce instance.
	 * @return array
	 */
	private static function get_chosen_shipping_methods( object $wc ): array {
		if ( isset( $wc->session ) && is_object( $wc->session ) && method_exists( $wc->session, 'get' ) ) {
			$chosen = $wc->session->get( 'chosen_shipping_methods', array() );
			return is_array( $chosen ) ? $chosen : array();
		}
		return array();
	}

	/**
	 * Inspect WooCommerce Shipping Zones (read-only) to check if any zone defines postcode locations.
	 *
	 * @return bool
	 */
	private static function store_has_postcode_zones(): bool {
		if ( ! class_exists( 'WC_Shipping_Zones' ) || ! method_exists( 'WC_Shipping_Zones', 'get_zones' ) ) {
			return false;
		}

		$zones = (array) \WC_Shipping_Zones::get_zones();
		foreach ( $zones as $zone ) {
			$locations = isset( $zone['zone_locations'] ) && is_array( $zone['zone_locations'] )
				? $zone['zone_locations']
				: array();
			foreach ( $locations as $loc ) {
				$loc_type = is_object( $loc ) ? ( $loc->type ?? '' ) : ( $loc['type'] ?? '' );
				if ( 'postcode' === $loc_type ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Determine which WooCommerce Shipping Zone granularity matched the calculated package.
	 *
	 * Never claims 'province' match when the administrative dataset is absent / unverified.
	 *
	 * @param array $destination     Normalized destination payload.
	 * @param array $packages        Resolved shipping packages.
	 * @param array $rates           Available shipping rates.
	 * @param bool  $region_verified Whether province/municipality code was verified against official dataset.
	 * @return string One of: postcode, province, country, fallback, none.
	 */
	private static function resolve_zone_match_level(
		array $destination,
		array $packages,
		array $rates,
		bool $region_verified
	): string {
		if ( empty( $rates ) ) {
			return 'none';
		}

		$locations = self::find_matched_zone_locations( $destination, $packages );
		if ( empty( $locations ) ) {
			return 'fallback';
		}

		$has_postcode = false;
		$has_state    = false;
		$has_country  = false;

		foreach ( $locations as $loc ) {
			$loc_type = is_object( $loc ) ? (string) ( $loc->type ?? '' ) : (string) ( $loc['type'] ?? '' );
			if ( 'postcode' === $loc_type ) {
				$has_postcode = true;
			} elseif ( 'state' === $loc_type ) {
				$has_state = true;
			} elseif ( 'country' === $loc_type ) {
				$has_country = true;
			}
		}

		if ( $has_postcode ) {
			return 'postcode';
		}
		if ( $has_state ) {
			return $region_verified ? 'province' : 'country';
		}
		if ( $has_country ) {
			return 'country';
		}

		return 'fallback';
	}

	/**
	 * Read-only lookup of zone locations from WooCommerce's matched zone.
	 *
	 * @param array $destination Normalized destination payload.
	 * @param array $packages    Resolved shipping packages.
	 * @return array
	 */
	private static function find_matched_zone_locations( array $destination, array $packages ): array {
		$country   = strtoupper( (string) ( $destination['country'] ?? 'VN' ) );
		$state_raw = '' !== (string) ( $destination['region_code'] ?? '' )
			? (string) $destination['region_code']
			: (string) ( $destination['region_name'] ?? '' );
		$postcode  = strtoupper( trim( (string) ( $destination['postcode'] ?? '' ) ) );

		$package = isset( $packages[0] ) && is_array( $packages[0] ) && isset( $packages[0]['destination'] )
			? $packages[0]
			: array(
				'destination' => array(
					'country'   => $country,
					'state'     => $state_raw,
					'postcode'  => $postcode,
					'city'      => (string) ( $destination['commune_name'] ?? '' ),
					'address_1' => (string) ( $destination['address_detail'] ?? '' ),
				),
			);

		if ( class_exists( 'WC_Shipping_Zones' ) && method_exists( 'WC_Shipping_Zones', 'get_zone_matching_package' ) ) {
			$matched_zone = \WC_Shipping_Zones::get_zone_matching_package( $package );
			if ( is_object( $matched_zone ) && method_exists( $matched_zone, 'get_zone_locations' ) ) {
				return (array) $matched_zone->get_zone_locations();
			}
		}

		if ( ! class_exists( 'WC_Shipping_Zones' ) || ! method_exists( 'WC_Shipping_Zones', 'get_zones' ) ) {
			return array();
		}

		$pkg_dest  = $package['destination'];
		$pkg_cntry = strtoupper( (string) ( $pkg_dest['country'] ?? $country ) );
		$pkg_state = strtoupper( (string) ( $pkg_dest['state'] ?? $state_raw ) );
		$pkg_post  = strtoupper( trim( (string) ( $pkg_dest['postcode'] ?? $postcode ) ) );

		foreach ( (array) \WC_Shipping_Zones::get_zones() as $zone ) {
			$locations = isset( $zone['zone_locations'] ) && is_array( $zone['zone_locations'] )
				? $zone['zone_locations']
				: array();
			if ( self::zone_locations_match( $locations, $pkg_cntry, $pkg_state, $pkg_post ) ) {
				return $locations;
			}
		}

		return array();
	}

	/**
	 * Evaluate whether a zone's locations satisfy WooCommerce's country/state/postcode matching rules.
	 *
	 * @param array  $locations Zone locations.
	 * @param string $country   Uppercase country code.
	 * @param string $state     Uppercase state code.
	 * @param string $postcode  Normalized postcode.
	 * @return bool
	 */
	private static function zone_locations_match( array $locations, string $country, string $state, string $postcode ): bool {
		if ( empty( $locations ) ) {
			return true;
		}

		$geo_rules      = array();
		$postcode_rules = array();

		foreach ( $locations as $loc ) {
			$loc_type = is_object( $loc ) ? (string) ( $loc->type ?? '' ) : (string) ( $loc['type'] ?? '' );
			$loc_code = is_object( $loc ) ? (string) ( $loc->code ?? '' ) : (string) ( $loc['code'] ?? '' );
			if ( 'postcode' === $loc_type ) {
				$postcode_rules[] = $loc_code;
			} else {
				$geo_rules[] = array( 'type' => $loc_type, 'code' => strtoupper( $loc_code ) );
			}
		}

		$geo_ok = empty( $geo_rules );
		foreach ( $geo_rules as $rule ) {
			if ( 'country' === $rule['type'] && $rule['code'] === $country ) {
				$geo_ok = true;
				break;
			}
			if ( 'state' === $rule['type'] && '' !== $state && $rule['code'] === ( $country . ':' . $state ) ) {
				$geo_ok = true;
				break;
			}
		}

		if ( ! $geo_ok ) {
			return false;
		}

		if ( empty( $postcode_rules ) ) {
			return true;
		}

		if ( '' === $postcode ) {
			return false;
		}

		if ( function_exists( 'wc_postcode_location_matcher' ) ) {
			$rows = array();
			foreach ( $postcode_rules as $pc_rule ) {
				$rows[] = (object) array( 'zone_id' => 1, 'location_code' => $pc_rule );
			}
			$matched = \wc_postcode_location_matcher( $postcode, $rows, 'zone_id', 'location_code', $country );
			return ! empty( $matched );
		}

		foreach ( $postcode_rules as $pc_rule ) {
			if ( self::matches_postcode_rule( $postcode, $pc_rule ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Minimal fallback postcode matcher supporting exact codes, wildcards (*), and numeric ranges (...).
	 *
	 * @param string $postcode Customer postcode.
	 * @param string $rule     Zone postcode rule.
	 * @return bool
	 */
	private static function matches_postcode_rule( string $postcode, string $rule ): bool {
		$clean_pc   = strtoupper( preg_replace( '/\s+/', '', $postcode ) ?? '' );
		$clean_rule = strtoupper( preg_replace( '/\s+/', '', $rule ) ?? '' );
		if ( '' === $clean_pc || '' === $clean_rule ) {
			return false;
		}
		if ( $clean_pc === $clean_rule ) {
			return true;
		}
		if ( false !== strpos( $clean_rule, '...' ) ) {
			$bounds = explode( '...', $clean_rule, 2 );
			if ( is_numeric( $bounds[0] ) && is_numeric( $bounds[1] ) && is_numeric( $clean_pc ) ) {
				return (int) $clean_pc >= (int) $bounds[0] && (int) $clean_pc <= (int) $bounds[1];
			}
		}
		if ( false !== strpos( $clean_rule, '*' ) ) {
			$regex = '/^' . str_replace( '\*', '.*', preg_quote( $clean_rule, '/' ) ) . '$/i';
			return 1 === preg_match( $regex, $clean_pc );
		}
		return false;
	}

	/**
	 * Validate AJAX nonce against CartHandler's shared action.
	 *
	 * @param array $raw_input Request payload.
	 * @return array|null
	 */
	private static function validate_request_nonce( array $raw_input ): ?array {
		$nonce = '';
		if ( isset( $raw_input['nonce'] ) ) {
			$nonce = sanitize_text_field( (string) $raw_input['nonce'] );
		} elseif ( isset( $raw_input['_ajax_nonce'] ) ) {
			$nonce = sanitize_text_field( (string) $raw_input['_ajax_nonce'] );
		}

		$valid = '' !== $nonce && function_exists( 'wp_verify_nonce' ) && (bool) wp_verify_nonce( $nonce, CartHandler::NONCE_ACTION );

		if ( ! $valid ) {
			return array(
				'success' => false,
				'status'  => 403,
				'data'    => array(
					'code'    => 'despace_invalid_nonce',
					'message' => __( 'Security verification failed. Please refresh the page and try again.', 'despace-core' ),
				),
			);
		}

		return null;
	}

	/**
	 * Verify POST method on state-updating estimator requests.
	 *
	 * @return bool
	 */
	private static function is_post_request(): bool {
		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) ) {
			return false;
		}
		$method = strtoupper( sanitize_text_field( (string) $_SERVER['REQUEST_METHOD'] ) );
		return 'POST' === $method;
	}

	/**
	 * Build 405 Method Not Allowed error envelope.
	 *
	 * @return array
	 */
	private static function method_not_allowed_response(): array {
		return array(
			'success' => false,
			'status'  => 405,
			'data'    => array(
				'code'    => 'despace_method_not_allowed',
				'message' => __( 'HTTP POST method is required for this action.', 'despace-core' ),
			),
		);
	}

	/**
	 * Format result into unified response envelope.
	 *
	 * @param mixed $result         Result array or WP_Error.
	 * @param int   $default_status Default HTTP status.
	 * @param array $meta           Extra response fields.
	 * @return array
	 */
	private static function format_action_result( $result, int $default_status = 200, array $meta = array() ): array {
		if ( is_wp_error( $result ) ) {
			$err_data = $result->get_error_data();
			$status   = is_array( $err_data ) && isset( $err_data['status'] ) ? (int) $err_data['status'] : 400;

			return array(
				'success' => false,
				'status'  => $status,
				'data'    => array(
					'code'    => $result->get_error_code(),
					'message' => $result->get_error_message(),
				),
			);
		}

		return array(
			'success' => true,
			'status'  => $default_status,
			'data'    => array_merge( $meta, is_array( $result ) ? $result : array() ),
		);
	}

	/**
	 * Emit JSON response via WordPress AJAX helpers.
	 *
	 * @param array $response Formatted response array.
	 */
	private static function send_json_response( array $response ): void {
		$status = (int) ( $response['status'] ?? 200 );
		$data   = $response['data'] ?? array();

		if ( ! empty( $response['success'] ) ) {
			wp_send_json_success( $data, $status );
		}

		wp_send_json_error( $data, $status );
	}

	/**
	 * Read unslashed $_POST payload safely.
	 *
	 * @return array
	 */
	private static function get_unslashed_post(): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is checked immediately in handle_estimate_request().
		$raw = $_POST ?? array();
		if ( function_exists( 'wp_unslash' ) ) {
			$raw = wp_unslash( $raw );
		}
		return is_array( $raw ) ? $raw : array();
	}

	/**
	 * Format price using WooCommerce when available.
	 *
	 * @param float $amount Price amount.
	 * @return string
	 */
	private static function format_price( float $amount ): string {
		if ( function_exists( 'wc_price' ) ) {
			return wc_price( $amount );
		}

		return number_format( $amount, 0, ',', '.' ) . ' ₫';
	}
}
