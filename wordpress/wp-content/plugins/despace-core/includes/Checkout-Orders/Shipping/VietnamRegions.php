<?php
/**
 * Vietnam Administrative Regions (2-tier 01/07/2025 model) & WooCommerce Address Adapter.
 *
 * Administrative hierarchy:
 *   Province / Municipality (34 units) -> Ward / Commune / Special Zone (3,321 units) -> Detailed Address
 *
 * Dataset Source:
 * Loads the bundled official government dataset (`data/vietnam-regions-2025.json`)
 * extracted from Quyết định 19/2025/QĐ-TTg (effective 2025-07-01: 34 provinces/municipalities,
 * 3,321 wards/communes/special zones), or an override via `despace_vietnam_regions_dataset_path`.
 * When the dataset file is absent or fails structural validation, the class degrades safely
 * to free-text administrative input without blocking checkout or corrupting legacy orders.
 *
 * @package DeSpace\CheckoutOrders\Shipping
 */

namespace DeSpace\CheckoutOrders\Shipping;

defined( 'ABSPATH' ) || exit;

/**
 * Class VietnamRegions
 */
class VietnamRegions {

	/**
	 * Expected counts per Vietnam 2025 administrative reform (effective 2025-07-01).
	 */
	public const EXPECTED_PROVINCE_COUNT = 34;
	public const EXPECTED_COMMUNE_COUNT  = 3321;
	public const REFORM_EFFECTIVE_DATE   = '2025-07-01';

	/**
	 * DeSpace-prefixed WooCommerce customer/order meta keys for canonical region linking.
	 */
	public const META_REGION_CODE  = '_despace_region_code';
	public const META_COMMUNE_CODE = '_despace_commune_code';

	/**
	 * Prevents duplicate hook registration.
	 *
	 * @var bool
	 */
	private static bool $hooks_registered = false;

	/**
	 * Loaded and validated dataset cache.
	 *
	 * @var array|null
	 */
	private static ?array $dataset = null;

	/**
	 * Dataset metadata & integrity summary.
	 *
	 * @var array|null
	 */
	private static ?array $dataset_status = null;

	/**
	 * Register WooCommerce state and VN locale compatibility hooks.
	 */
	public static function init(): void {
		if ( self::$hooks_registered ) {
			return;
		}

		self::$hooks_registered = true;

		add_filter( 'woocommerce_states', array( __CLASS__, 'filter_woocommerce_states' ), 10, 1 );
		add_filter( 'woocommerce_get_country_locale', array( __CLASS__, 'filter_country_locale' ), 10, 1 );
		add_filter( 'woocommerce_default_address_fields', array( __CLASS__, 'filter_default_address_fields' ), 10, 1 );
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
	 * Reset in-memory dataset cache (primarily for switching/reloading dataset configs cleanly).
	 */
	public static function reset_dataset(): void {
		self::$dataset        = null;
		self::$dataset_status = null;
	}

	/**
	 * Ensure dataset is attempted from default/filtered file path if not loaded explicitly.
	 */
	public static function ensure_dataset_loaded(): void {
		if ( null !== self::$dataset ) {
			return;
		}

		$default_json = __DIR__ . '/data/vietnam-regions-2025.json';
		$default_php  = __DIR__ . '/data/vietnam-regions-2025.php';
		$default_path = file_exists( $default_json ) ? $default_json : ( file_exists( $default_php ) ? $default_php : '' );

		$dataset_path = function_exists( 'apply_filters' )
			? (string) apply_filters( 'despace_vietnam_regions_dataset_path', $default_path )
			: $default_path;

		if ( '' !== $dataset_path && file_exists( $dataset_path ) ) {
			$loaded = self::load_dataset_from_file( $dataset_path );
			if ( ! is_wp_error( $loaded ) ) {
				return;
			}
		}

		// Initialize empty verified state (free-text fallback active).
		self::$dataset = array(
			'source'         => 'none',
			'source_title'   => '',
			'source_url'     => '',
			'version'        => '',
			'effective_date' => self::REFORM_EFFECTIVE_DATE,
			'updated_at'     => '',
			'provinces'      => array(),
			'communes'       => array(),
			'by_province'    => array(),
		);
		self::$dataset_status = array(
			'loaded'                  => false,
			'has_complete_provinces'  => false,
			'has_complete_communes'   => false,
			'is_complete'             => false,
			'province_count'          => 0,
			'commune_count'           => 0,
			'expected_province_count' => self::EXPECTED_PROVINCE_COUNT,
			'expected_commune_count'  => self::EXPECTED_COMMUNE_COUNT,
			'fallback_mode'           => 'free_text',
			'missing_reason'          => 'No valid official 34-province / 3,321-commune dataset file loaded.',
		);
	}

	/**
	 * Load and validate an official Vietnam regions dataset from a JSON or PHP file.
	 *
	 * Supports nested metadata + provinces[].communes[] structure (Quyết định 19/2025/QĐ-TTg)
	 * as well as top-level flat provinces/communes arrays.
	 *
	 * @param string $file_path Absolute path to .json or .php dataset file.
	 * @return array|\WP_Error Dataset status array or WP_Error.
	 */
	public static function load_dataset_from_file( string $file_path ) {
		if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			return new \WP_Error(
				'despace_dataset_file_missing',
				__( 'Vietnam regions dataset file does not exist or is not readable.', 'despace-core' )
			);
		}

		$ext = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );
		if ( 'json' === $ext ) {
			$raw     = file_get_contents( $file_path );
			$decoded = is_string( $raw ) ? json_decode( $raw, true ) : null;
		} elseif ( 'php' === $ext ) {
			$decoded = include $file_path;
		} else {
			return new \WP_Error(
				'despace_dataset_invalid_format',
				__( 'Dataset file must be JSON or PHP.', 'despace-core' )
			);
		}

		if ( ! is_array( $decoded ) ) {
			return new \WP_Error(
				'despace_dataset_corrupt',
				__( 'Dataset payload could not be parsed as an array.', 'despace-core' )
			);
		}

		return self::load_dataset( $decoded );
	}

	/**
	 * Validate and load a structured Vietnam regions array into memory.
	 *
	 * Enforces 2-digit province codes, 5-digit commune/ward/special-zone codes, valid types,
	 * no duplicate province or commune codes, and no orphan communes.
	 *
	 * @param array $raw_dataset Structured administrative dataset.
	 * @return array|\WP_Error
	 */
	public static function load_dataset( array $raw_dataset ) {
		$metadata = isset( $raw_dataset['metadata'] ) && is_array( $raw_dataset['metadata'] )
			? $raw_dataset['metadata']
			: array();

		$raw_provinces = isset( $raw_dataset['provinces'] ) && is_array( $raw_dataset['provinces'] )
			? $raw_dataset['provinces']
			: array();
		$raw_communes  = isset( $raw_dataset['communes'] ) && is_array( $raw_dataset['communes'] )
			? $raw_dataset['communes']
			: array();

		$valid_province_types = array( 'province', 'municipality' );
		$valid_commune_types  = array( 'commune', 'ward', 'special_zone' );

		$provinces = array();
		foreach ( $raw_provinces as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$code = self::sanitize_code( (string) ( $row['code'] ?? '' ) );
			$name = self::sanitize_text( (string) ( $row['name'] ?? '' ) );
			$type = self::sanitize_text( (string) ( $row['type'] ?? '' ) );

			if ( ! preg_match( '/^\d{2}$/', $code ) || '' === $name ) {
				return new \WP_Error(
					'despace_dataset_invalid_province',
					__( 'Each province record requires a 2-digit code and non-empty name.', 'despace-core' )
				);
			}
			if ( '' !== $type && ! in_array( $type, $valid_province_types, true ) ) {
				return new \WP_Error(
					'despace_dataset_invalid_province_type',
					sprintf( 'Invalid province type detected for %s: %s', $code, $type )
				);
			}
			if ( isset( $provinces[ $code ] ) ) {
				return new \WP_Error(
					'despace_dataset_duplicate_province_code',
					sprintf( 'Duplicate province code detected: %s', $code )
				);
			}

			$provinces[ $code ] = array(
				'code' => $code,
				'name' => $name,
				'type' => $type,
			);

			// Support nested provinces[].communes[] structure seamlessly alongside flat payloads.
			if ( isset( $row['communes'] ) && is_array( $row['communes'] ) ) {
				foreach ( $row['communes'] as $nested_commune ) {
					if ( ! is_array( $nested_commune ) ) {
						continue;
					}
					if ( ! isset( $nested_commune['province_code'] ) ) {
						$nested_commune['province_code'] = $code;
					}
					$raw_communes[] = $nested_commune;
				}
			}
		}

		$communes    = array();
		$by_province = array();
		foreach ( array_keys( $provinces ) as $p_code ) {
			$by_province[ $p_code ] = array();
		}

		foreach ( $raw_communes as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$c_code = self::sanitize_code( (string) ( $row['code'] ?? '' ) );
			$c_name = self::sanitize_text( (string) ( $row['name'] ?? '' ) );
			$p_code = self::sanitize_code( (string) ( $row['province_code'] ?? '' ) );
			$c_type = self::sanitize_text( (string) ( $row['type'] ?? '' ) );

			if ( ! preg_match( '/^\d{5}$/', $c_code ) || '' === $c_name || ! preg_match( '/^\d{2}$/', $p_code ) ) {
				return new \WP_Error(
					'despace_dataset_invalid_commune',
					__( 'Each commune/ward/special-zone record requires a 5-digit code, name, and 2-digit province_code.', 'despace-core' )
				);
			}
			if ( '' !== $c_type && ! in_array( $c_type, $valid_commune_types, true ) ) {
				return new \WP_Error(
					'despace_dataset_invalid_commune_type',
					sprintf( 'Invalid commune type detected for %s: %s', $c_code, $c_type )
				);
			}
			if ( isset( $communes[ $c_code ] ) ) {
				return new \WP_Error(
					'despace_dataset_duplicate_commune_code',
					sprintf( 'Duplicate commune code detected: %s', $c_code )
				);
			}
			if ( ! isset( $provinces[ $p_code ] ) ) {
				return new \WP_Error(
					'despace_dataset_orphan_commune',
					sprintf( 'Commune %s references unknown province_code %s.', $c_code, $p_code )
				);
			}

			$record = array(
				'code'          => $c_code,
				'name'          => $c_name,
				'province_code' => $p_code,
				'type'          => $c_type,
			);

			$communes[ $c_code ]               = $record;
			$by_province[ $p_code ][ $c_code ] = $record;
		}

		$p_count = count( $provinces );
		$c_count = count( $communes );

		if ( isset( $metadata['province_count'] ) && (int) $metadata['province_count'] !== $p_count ) {
			return new \WP_Error(
				'despace_dataset_count_mismatch',
				sprintf( 'Metadata province_count (%d) does not match loaded count (%d).', (int) $metadata['province_count'], $p_count )
			);
		}
		if ( isset( $metadata['commune_count'] ) && (int) $metadata['commune_count'] !== $c_count ) {
			return new \WP_Error(
				'despace_dataset_count_mismatch',
				sprintf( 'Metadata commune_count (%d) does not match loaded count (%d).', (int) $metadata['commune_count'], $c_count )
			);
		}

		$has_all_prov = ( self::EXPECTED_PROVINCE_COUNT === $p_count );
		$has_all_comm = ( self::EXPECTED_COMMUNE_COUNT === $c_count );
		$is_complete  = $has_all_prov && $has_all_comm;

		$source_title   = self::sanitize_text( (string) ( $metadata['source_title'] ?? ( $raw_dataset['source'] ?? 'configured' ) ) );
		$source_url     = self::sanitize_text( (string) ( $metadata['source_url'] ?? '' ) );
		$version        = self::sanitize_text( (string) ( $metadata['version'] ?? ( $raw_dataset['version'] ?? '' ) ) );
		$effective_date = self::sanitize_text( (string) ( $metadata['effective_date'] ?? ( $raw_dataset['effective_date'] ?? self::REFORM_EFFECTIVE_DATE ) ) );
		$updated_at     = self::sanitize_text( (string) ( $metadata['extracted_at'] ?? ( $raw_dataset['updated_at'] ?? '' ) ) );

		self::$dataset = array(
			'source'         => $source_title,
			'source_title'   => $source_title,
			'source_url'     => $source_url,
			'version'        => $version,
			'effective_date' => $effective_date,
			'updated_at'     => $updated_at,
			'provinces'      => $provinces,
			'communes'       => $communes,
			'by_province'    => $by_province,
		);

		$missing = array();
		if ( ! $has_all_prov ) {
			$missing[] = sprintf( 'provinces (%d/%d)', $p_count, self::EXPECTED_PROVINCE_COUNT );
		}
		if ( ! $has_all_comm ) {
			$missing[] = sprintf( 'communes (%d/%d)', $c_count, self::EXPECTED_COMMUNE_COUNT );
		}

		self::$dataset_status = array(
			'loaded'                  => $p_count > 0 || $c_count > 0,
			'has_complete_provinces'  => $has_all_prov,
			'has_complete_communes'   => $has_all_comm,
			'is_complete'             => $is_complete,
			'province_count'          => $p_count,
			'commune_count'           => $c_count,
			'expected_province_count' => self::EXPECTED_PROVINCE_COUNT,
			'expected_commune_count'  => self::EXPECTED_COMMUNE_COUNT,
			'fallback_mode'           => $is_complete ? 'dropdown' : 'free_text',
			'missing_reason'          => empty( $missing ) ? '' : 'Incomplete dataset: ' . implode( ', ', $missing ),
		);

		return self::$dataset_status;
	}

	/**
	 * Return dataset completeness and source status.
	 *
	 * @return array
	 */
	public static function get_dataset_status(): array {
		self::ensure_dataset_loaded();

		return array_merge(
			self::$dataset_status,
			array(
				'source'         => self::$dataset['source'],
				'source_title'   => self::$dataset['source_title'] ?? self::$dataset['source'],
				'source_url'     => self::$dataset['source_url'] ?? '',
				'version'        => self::$dataset['version'],
				'effective_date' => self::$dataset['effective_date'],
				'updated_at'     => self::$dataset['updated_at'],
			)
		);
	}

	/**
	 * Return loaded province map [code => ['code', 'name', 'type']].
	 *
	 * @return array
	 */
	public static function get_provinces(): array {
		self::ensure_dataset_loaded();
		return self::$dataset['provinces'];
	}

	/**
	 * Return loaded provinces as WooCommerce states map [code => name].
	 *
	 * @return array
	 */
	public static function get_woocommerce_states_map(): array {
		$map = array();
		foreach ( self::get_provinces() as $code => $prov ) {
			$map[ $code ] = $prov['name'];
		}
		return $map;
	}

	/**
	 * Return loaded communes/wards/special zones for a specific province code.
	 *
	 * @param string $province_code Province code.
	 * @return array
	 */
	public static function get_communes( string $province_code ): array {
		self::ensure_dataset_loaded();
		$code = self::sanitize_code( $province_code );
		return self::$dataset['by_province'][ $code ] ?? array();
	}

	/**
	 * Determine whether commune dropdown can safely be enforced for a province.
	 *
	 * Returns true only when full dataset is verified OR the target province has communes loaded
	 * inside a complete dataset. Never forces selection from a partial dataset.
	 *
	 * @param string $province_code Optional province code.
	 * @return bool
	 */
	public static function is_commune_dropdown_enabled( string $province_code = '' ): bool {
		$status = self::get_dataset_status();
		if ( empty( $status['is_complete'] ) ) {
			return false;
		}
		if ( '' === $province_code ) {
			return true;
		}
		return ! empty( self::get_communes( $province_code ) );
	}

	/**
	 * Validate and normalize an administrative VN address input under 2-tier model.
	 *
	 * Standard contract fields:
	 * - region_code, region_name
	 * - commune_code, commune_name
	 * - address_detail
	 *
	 * Enforces:
	 * - No mandatory district/huyen field.
	 * - Rejects unknown region_code / mismatched commune_code when dataset entries are supplied.
	 * - Allows clean Vietnamese Unicode free-text when dataset is absent/incomplete.
	 *
	 * @param array $raw_input Input address array.
	 * @return array|\WP_Error
	 */
	public static function validate_and_normalize_address( array $raw_input ) {
		self::ensure_dataset_loaded();

		$country = strtoupper( self::sanitize_text( (string) ( $raw_input['country'] ?? 'VN' ) ) );
		if ( '' === $country ) {
			$country = 'VN';
		}

		$region_code    = self::sanitize_code( (string) ( $raw_input['region_code'] ?? ( $raw_input['state'] ?? '' ) ) );
		$region_name    = self::sanitize_text( (string) ( $raw_input['region_name'] ?? '' ) );
		$commune_code   = self::sanitize_code( (string) ( $raw_input['commune_code'] ?? '' ) );
		$commune_name   = self::sanitize_text( (string) ( $raw_input['commune_name'] ?? ( $raw_input['city'] ?? '' ) ) );
		$address_detail = self::sanitize_text( (string) ( $raw_input['address_detail'] ?? ( $raw_input['address_1'] ?? '' ) ) );
		$postcode       = self::sanitize_text( (string) ( $raw_input['postcode'] ?? '' ) );

		$provinces        = self::get_provinces();
		$has_province_ref = ! empty( $provinces );

		// Validate province against VN dataset only when country is VN and province dataset is loaded.
		if ( 'VN' === $country && $has_province_ref && '' !== $region_code ) {
			if ( ! isset( $provinces[ $region_code ] ) ) {
				return new \WP_Error(
					'despace_invalid_region_code',
					__( 'Selected province/municipality is invalid.', 'despace-core' ),
					array( 'status' => 400 )
				);
			}
			$region_name = $provinces[ $region_code ]['name'];
		} elseif ( '' !== $region_code && ( 'VN' !== $country || ! $has_province_ref ) && '' === $region_name ) {
			$region_name = $region_code;
		}

		// Reject a VN province selection if province dropdown IS populated but region_name matches none.
		if ( 'VN' === $country && $has_province_ref && '' === $region_code && '' !== $region_name ) {
			$matched_code = self::find_province_code_by_name( $region_name );
			if ( '' === $matched_code ) {
				return new \WP_Error(
					'despace_invalid_region_name',
					__( 'Province/municipality does not match the official administrative list.', 'despace-core' ),
					array( 'status' => 400 )
				);
			}
			$region_code = $matched_code;
			$region_name = $provinces[ $region_code ]['name'];
		}

		// Validate commune/ward/special-zone linkage when country is VN and commune_code is provided or dropdown is active.
		$communes_in_province = ( 'VN' === $country && '' !== $region_code ) ? self::get_communes( $region_code ) : array();
		if ( 'VN' === $country && '' !== $commune_code ) {
			$all_communes = self::$dataset['communes'] ?? array();
			if ( empty( $all_communes ) ) {
				return new \WP_Error(
					'despace_commune_dataset_unavailable',
					__( 'Commune code verification requires the administrative dataset; please enter commune name in free text.', 'despace-core' ),
					array( 'status' => 400 )
				);
			}
			if ( ! isset( $all_communes[ $commune_code ] ) ) {
				return new \WP_Error(
					'despace_invalid_commune_code',
					__( 'Selected ward/commune/special zone does not exist.', 'despace-core' ),
					array( 'status' => 400 )
				);
			}
			$commune_record = $all_communes[ $commune_code ];
			if ( '' !== $region_code && $commune_record['province_code'] !== $region_code ) {
				return new \WP_Error(
					'despace_commune_province_mismatch',
					__( 'Selected ward/commune/special zone does not belong to the chosen province/municipality.', 'despace-core' ),
					array( 'status' => 400 )
				);
			}
			$commune_name = $commune_record['name'];
			if ( '' === $region_code ) {
				$region_code = $commune_record['province_code'];
				$region_name = $provinces[ $region_code ]['name'] ?? '';
			}
		} elseif ( ! empty( $communes_in_province ) && self::is_commune_dropdown_enabled( $region_code ) && '' !== $commune_name ) {
			$matched_c_code = self::find_commune_code_by_name( $region_code, $commune_name );
			if ( '' === $matched_c_code ) {
				return new \WP_Error(
					'despace_commune_province_mismatch',
					__( 'Entered ward/commune/special zone was not found in the selected province.', 'despace-core' ),
					array( 'status' => 400 )
				);
			}
			$commune_code = $matched_c_code;
			$commune_name = $communes_in_province[ $commune_code ]['name'];
		}

		$is_complete = ( '' !== $region_code || '' !== $region_name )
			&& '' !== $commune_name
			&& '' !== $address_detail;

		return array(
			'country'            => $country,
			'region_code'        => $region_code,
			'region_name'        => $region_name,
			'commune_code'       => $commune_code,
			'commune_name'       => $commune_name,
			'address_detail'     => $address_detail,
			'postcode'           => $postcode,
			'wc_mapping'         => array(
				'country'   => $country,
				'state'     => '' !== $region_code ? $region_code : $region_name,
				'city'      => $commune_name,
				'address_1' => $address_detail,
				'postcode'  => $postcode,
			),
			'commune_input_mode' => self::is_commune_dropdown_enabled( $region_code ) ? 'dropdown' : 'free_text',
			'district_required'  => false,
			'is_complete'        => $is_complete,
		);
	}

	/**
	 * Extract address safely from a historical or new WC_Order (or compatible mock) WITHOUT modifying it.
	 *
	 * Preserves legacy billing/shipping data (e.g., old 63-province names or address_2 district strings).
	 *
	 * @param object $order WC_Order or compatible order instance.
	 * @param string $type  'shipping' or 'billing'.
	 * @return array
	 */
	public static function extract_order_address( object $order, string $type = 'shipping' ): array {
		$use_shipping = ( 'shipping' === $type && method_exists( $order, 'get_shipping_address_1' ) );

		$country   = $use_shipping ? (string) $order->get_shipping_country() : ( method_exists( $order, 'get_billing_country' ) ? (string) $order->get_billing_country() : '' );
		$state     = $use_shipping ? (string) $order->get_shipping_state() : ( method_exists( $order, 'get_billing_state' ) ? (string) $order->get_billing_state() : '' );
		$city      = $use_shipping ? (string) $order->get_shipping_city() : ( method_exists( $order, 'get_billing_city' ) ? (string) $order->get_billing_city() : '' );
		$address_1 = $use_shipping ? (string) $order->get_shipping_address_1() : ( method_exists( $order, 'get_billing_address_1' ) ? (string) $order->get_billing_address_1() : '' );
		$address_2 = $use_shipping && method_exists( $order, 'get_shipping_address_2' )
			? (string) $order->get_shipping_address_2()
			: ( method_exists( $order, 'get_billing_address_2' ) ? (string) $order->get_billing_address_2() : '' );
		$postcode  = $use_shipping && method_exists( $order, 'get_shipping_postcode' )
			? (string) $order->get_shipping_postcode()
			: ( method_exists( $order, 'get_billing_postcode' ) ? (string) $order->get_billing_postcode() : '' );

		// Fallback to billing if shipping was empty on legacy pickup/billing-only orders.
		if ( $use_shipping && '' === $address_1 && '' === $city && '' === $state && method_exists( $order, 'get_billing_address_1' ) ) {
			return self::extract_order_address( $order, 'billing' );
		}

		$prefix       = $use_shipping ? '_shipping' : '_billing';
		$region_code  = method_exists( $order, 'get_meta' ) ? (string) $order->get_meta( $prefix . self::META_REGION_CODE, true ) : '';
		$commune_code = method_exists( $order, 'get_meta' ) ? (string) $order->get_meta( $prefix . self::META_COMMUNE_CODE, true ) : '';

		$provinces   = self::get_provinces();

		if ( '' === $region_code ) {
			$clean_state = self::sanitize_code( $state );
			if ( preg_match( '/^\d{2}$/', $clean_state ) && isset( $provinces[ $clean_state ] ) ) {
				$region_code = $clean_state;
			} elseif ( ! empty( $provinces ) && '' !== $state ) {
				$region_code = self::find_province_code_by_name( $state );
			}
		}

		$region_name = ( '' !== $region_code && isset( $provinces[ $region_code ] ) )
			? $provinces[ $region_code ]['name']
			: $state;

		return array(
			'country'        => $country,
			'region_code'    => $region_code,
			'region_name'    => $region_name,
			'commune_code'   => $commune_code,
			'commune_name'   => $city,
			'address_detail' => $address_1,
			'legacy_line_2'  => $address_2,
			'postcode'       => $postcode,
			'is_legacy'      => ( '' === $commune_code && '' !== $address_2 ),
		);
	}

	/**
	 * Inject validated VN states into WooCommerce when province list is loaded.
	 *
	 * Never injects legacy 63-province defaults or guessed data.
	 *
	 * @param array $states Existing WooCommerce states map.
	 * @return array
	 */
	public static function filter_woocommerce_states( array $states ): array {
		$vn_states = self::get_woocommerce_states_map();
		if ( ! empty( $vn_states ) ) {
			$states['VN'] = $vn_states;
		}
		return $states;
	}

	/**
	 * Adjust WooCommerce 'VN' country locale to match 2-tier 2025 address model.
	 *
	 * WooCommerce natively hides 'state' for VN because i18n/states.php ships VN empty.
	 * When VN provinces are loaded, expose 'state' as Province/Municipality and rename
	 * 'city' to Ward/Commune/Special Zone; never require a district field.
	 *
	 * @param array $locales Country locales map.
	 * @return array
	 */
	public static function filter_country_locale( array $locales ): array {
		$has_provinces = ! empty( self::get_provinces() );

		$locales['VN'] = array(
			'state'     => array(
				'label'    => __( 'Tỉnh / Thành phố', 'despace-core' ),
				'required' => true,
				'hidden'   => false,
				'priority' => 50,
			),
			'city'      => array(
				'label'       => __( 'Phường / Xã / Đặc khu', 'despace-core' ),
				'placeholder' => __( 'Nhập hoặc chọn Phường / Xã / Đặc khu', 'despace-core' ),
				'required'    => true,
				'hidden'      => false,
				'priority'    => 60,
			),
			'address_1' => array(
				'label'       => __( 'Địa chỉ chi tiết', 'despace-core' ),
				'placeholder' => __( 'Số nhà, tên đường, tòa nhà / thôn / ấp', 'despace-core' ),
				'required'    => true,
				'priority'    => 70,
			),
			'address_2' => array(
				'required' => false,
				'hidden'   => true,
				'priority' => 75,
			),
			'postcode'  => array(
				'required' => false,
				'hidden'   => false,
				'priority' => 80,
			),
		);

		if ( ! $has_provinces ) {
			$locales['VN']['state']['placeholder'] = __( 'Nhập Tỉnh / Thành phố', 'despace-core' );
		}

		return $locales;
	}

	/**
	 * Ensure default address fields do not enforce district or postcode for Vietnam storefront flows.
	 *
	 * @param array $fields Default WC address fields.
	 * @return array
	 */
	public static function filter_default_address_fields( array $fields ): array {
		if ( isset( $fields['postcode'] ) ) {
			$fields['postcode']['required'] = false;
		}
		if ( isset( $fields['address_2'] ) ) {
			$fields['address_2']['required'] = false;
		}
		return $fields;
	}

	/**
	 * Provide frontend configuration metadata for M2 dropdown/fallback rendering.
	 *
	 * @return array
	 */
	public static function get_frontend_config(): array {
		$status = self::get_dataset_status();
		return array(
			'country'                 => 'VN',
			'provinces'               => array_values( self::get_provinces() ),
			'commune_input_mode'      => $status['fallback_mode'],
			'dataset_status'          => $status,
			'district_required'       => false,
			'labels'                  => array(
				'province'       => __( 'Tỉnh / Thành phố', 'despace-core' ),
				'commune'        => __( 'Phường / Xã / Đặc khu', 'despace-core' ),
				'address_detail' => __( 'Địa chỉ chi tiết', 'despace-core' ),
			),
		);
	}

	/**
	 * Find matching province code by Unicode name (supports full official prefix and short name).
	 *
	 * @param string $name Province name.
	 * @return string
	 */
	private static function find_province_code_by_name( string $name ): string {
		$needle       = self::normalize_compare( $name );
		$needle_short = self::strip_admin_prefix( $needle );
		$provinces    = self::get_provinces();

		foreach ( $provinces as $code => $prov ) {
			if ( self::normalize_compare( $prov['name'] ) === $needle ) {
				return (string) $code;
			}
		}

		if ( '' !== $needle_short ) {
			foreach ( $provinces as $code => $prov ) {
				if ( self::strip_admin_prefix( self::normalize_compare( $prov['name'] ) ) === $needle_short ) {
					return (string) $code;
				}
			}
		}

		return '';
	}

	/**
	 * Strip leading administrative prefix (Tỉnh / Thành phố / TP.) for short-name lookup.
	 *
	 * @param string $normalized Lowercase normalized province name.
	 * @return string
	 */
	private static function strip_admin_prefix( string $normalized ): string {
		$stripped = preg_replace( '/^(?:tỉnh|thành phố|tp\.?)\s+/u', '', $normalized );
		return is_string( $stripped ) ? trim( $stripped ) : $normalized;
	}

	/**
	 * Find matching commune code inside a given province by Unicode name.
	 *
	 * @param string $province_code Province code.
	 * @param string $name          Commune name.
	 * @return string
	 */
	private static function find_commune_code_by_name( string $province_code, string $name ): string {
		$needle = self::normalize_compare( $name );
		foreach ( self::get_communes( $province_code ) as $code => $commune ) {
			if ( self::normalize_compare( $commune['name'] ) === $needle ) {
				return (string) $code;
			}
		}
		return '';
	}

	/**
	 * Normalize string for exact Vietnamese UTF-8 comparison.
	 *
	 * @param string $value Raw string.
	 * @return string
	 */
	private static function normalize_compare( string $value ): string {
		$clean = preg_replace( '/\s+/u', ' ', trim( $value ) );
		$clean = is_string( $clean ) ? $clean : trim( $value );
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $clean, 'UTF-8' ) : strtolower( $clean );
	}

	/**
	 * Sanitize administrative identifier code.
	 *
	 * @param string $code Raw code.
	 * @return string
	 */
	private static function sanitize_code( string $code ): string {
		$clean = self::sanitize_text( $code );
		return preg_replace( '/[^A-Za-z0-9_\-]/', '', $clean ) ?? '';
	}

	/**
	 * Sanitize UTF-8 Vietnamese text cleanly without corrupting diacritics.
	 *
	 * @param string $text Raw text.
	 * @return string
	 */
	private static function sanitize_text( string $text ): string {
		if ( function_exists( 'sanitize_text_field' ) ) {
			return sanitize_text_field( $text );
		}
		return trim( strip_tags( $text ) );
	}
}
