<?php
/**
 * DeSpace M3 Phase 1B-B — Vietnam Administrative Regions Unit Test Suite.
 *
 * Tests 2-tier (Province 34 -> Ward/Commune/Special Zone -> Detailed Address) validation,
 * dataset integrity checks, free-text fallback, Vietnamese Unicode, and legacy order safety.
 * Official dataset completeness check marks BLOCKED if official 34/3,321 file is not bundled.
 *
 * Run via CLI: php tests/test-m3-vietnam-regions-unit.php
 *
 * @package DeSpace\Tests
 */

define( 'ABSPATH', __DIR__ . '/../' );

$despace_mock_filters = array();

class WP_Error {
	private string $code;
	private string $message;
	private $data;
	public function __construct( string $code = '', string $message = '', $data = '' ) {
		$this->code    = $code;
		$this->message = $message;
		$this->data    = $data;
	}
	public function get_error_code(): string { return $this->code; }
	public function get_error_message(): string { return $this->message; }
	public function get_error_data() { return $this->data; }
}

function is_wp_error( $thing ): bool { return $thing instanceof WP_Error; }
function add_filter( string $tag, callable $cb, int $prio = 10, int $args = 1 ): bool {
	global $despace_mock_filters;
	$despace_mock_filters[ $tag ][] = array( 'callback' => $cb, 'priority' => $prio );
	return true;
}
function apply_filters( string $tag, $value, ...$args ) {
	global $despace_mock_filters;
	foreach ( $despace_mock_filters[ $tag ] ?? array() as $hook ) {
		$value = call_user_func( $hook['callback'], $value, ...$args );
	}
	return $value;
}
function sanitize_text_field( $str ): string { return trim( strip_tags( (string) $str ) ); }
function __( string $text, string $domain = 'default' ): string { return $text; }

require_once __DIR__ . '/../wordpress/wp-content/plugins/despace-core/includes/Checkout-Orders/Shipping/VietnamRegions.php';

use DeSpace\CheckoutOrders\Shipping\VietnamRegions;

class Mock_Legacy_WC_Order {
	private array $data;
	public bool $mutated = false;

	public function __construct( array $data ) { $this->data = $data; }
	public function get_shipping_country(): string { return $this->data['shipping_country'] ?? ''; }
	public function get_shipping_state(): string { return $this->data['shipping_state'] ?? ''; }
	public function get_shipping_city(): string { return $this->data['shipping_city'] ?? ''; }
	public function get_shipping_address_1(): string { return $this->data['shipping_address_1'] ?? ''; }
	public function get_shipping_address_2(): string { return $this->data['shipping_address_2'] ?? ''; }
	public function get_shipping_postcode(): string { return $this->data['shipping_postcode'] ?? ''; }
	public function get_billing_country(): string { return $this->data['billing_country'] ?? ''; }
	public function get_billing_state(): string { return $this->data['billing_state'] ?? ''; }
	public function get_billing_city(): string { return $this->data['billing_city'] ?? ''; }
	public function get_billing_address_1(): string { return $this->data['billing_address_1'] ?? ''; }
	public function get_billing_address_2(): string { return $this->data['billing_address_2'] ?? ''; }
	public function get_billing_postcode(): string { return $this->data['billing_postcode'] ?? ''; }
	public function get_meta( string $key, bool $single = true ) { return $this->data['meta'][ $key ] ?? ''; }
	public function set_shipping_state( string $v ): void { $this->mutated = true; }
	public function save(): void { $this->mutated = true; }
}

class M3VietnamRegionsUnitRunner {
	private array $counts = array( 'PASS' => 0, 'FAIL' => 0, 'BLOCKED' => 0 );

	public function run(): void {
		echo "========================================================================\n";
		echo "    DeSpace M3 Phase 1B-B — VIETNAM REGIONS UNIT TEST SUITE             \n";
		echo "========================================================================\n\n";

		VietnamRegions::init();

		// Test 1: Validate bundled official Quyết định 19/2025/QĐ-TTg dataset (34 provinces / 3,321 communes).
		$this->test_01_official_repository_dataset_completeness();

		// Tests 2-12: Schema integrity, state mapping, fallback & legacy order safety.
		$this->test_02_reject_duplicate_province_codes();
		$this->test_03_commune_belongs_to_correct_province();
		$this->test_04_reject_duplicate_commune_codes();
		$this->test_05_no_mandatory_district_field();
		$this->test_06_reject_invalid_province();
		$this->test_07_reject_commune_from_wrong_province();
		$this->test_08_free_text_fallback_works_without_full_dataset();
		$this->test_09_vietnamese_unicode_preservation();
		$this->test_10_legacy_order_data_preserved_readonly();
		$this->test_11_woocommerce_states_and_short_name_mapping();
		$this->test_12_reject_invalid_formats_and_corrupt_metadata();

		echo "\n========================================================================\n";
		printf(
			"SUMMARY: %d Passed | %d Blocked | %d Failed\n",
			$this->counts['PASS'],
			$this->counts['BLOCKED'],
			$this->counts['FAIL']
		);
		echo "========================================================================\n";

		if ( $this->counts['FAIL'] > 0 ) {
			exit( 1 );
		}
	}

	private function record( string $id, string $desc, string $status, string $note = '' ): void {
		printf( "[%-10s] %-50s : %s %s\n", $id, $desc, $status, $note );
		$this->counts[ $status ]++;
	}

	private function load_sample_two_tier_fixture(): void {
		VietnamRegions::load_dataset( array(
			'source'         => 'unit_test_fixture',
			'version'        => 'fixture-1',
			'effective_date' => '2025-07-01',
			'provinces'      => array(
				array( 'code' => '01', 'name' => 'Thành phố Hà Nội' ),
				array( 'code' => '79', 'name' => 'Thành phố Hồ Chí Minh' ),
			),
			'communes'       => array(
				array( 'code' => '00004', 'name' => 'Phường Ba Đình', 'province_code' => '01' ),
				array( 'code' => '26734', 'name' => 'Phường Sài Gòn', 'province_code' => '79' ),
			),
		) );
	}

	private function test_01_official_repository_dataset_completeness(): void {
		VietnamRegions::reset_dataset();
		$json_path = __DIR__ . '/../wordpress/wp-content/plugins/despace-core/includes/Checkout-Orders/Shipping/data/vietnam-regions-2025.json';
		$raw_json  = file_exists( $json_path ) ? json_decode( (string) file_get_contents( $json_path ), true ) : null;
		$status    = VietnamRegions::get_dataset_status();
		$provinces = VietnamRegions::get_provinces();

		if ( ! is_array( $raw_json ) || empty( $status['is_complete'] ) ) {
			$this->record(
				'TC-VNR-01',
				'Official 34-province / 3,321-commune dataset',
				'FAIL',
				'(' . ( $status['missing_reason'] ?: 'Missing or invalid official dataset' ) . ')'
			);
			return;
		}

		$all_prov_codes_valid = 34 === count( $provinces );
		$all_comm_codes_valid = true;
		$types_ok             = true;
		$commune_types_seen   = array();
		$total_communes       = 0;
		$orphan_or_dup        = false;
		$seen_comm_codes      = array();

		foreach ( $provinces as $p_code => $prov ) {
			if ( 1 !== preg_match( '/^\d{2}$/', (string) $p_code ) || ! in_array( $prov['type'] ?? '', array( 'province', 'municipality' ), true ) ) {
				$all_prov_codes_valid = false;
			}
			$p_comms = VietnamRegions::get_communes( (string) $p_code );
			foreach ( $p_comms as $c_code => $comm ) {
				$total_communes++;
				if ( 1 !== preg_match( '/^\d{5}$/', (string) $c_code ) ) {
					$all_comm_codes_valid = false;
				}
				$ctype = $comm['type'] ?? '';
				if ( ! in_array( $ctype, array( 'commune', 'ward', 'special_zone' ), true ) ) {
					$types_ok = false;
				} else {
					$commune_types_seen[ $ctype ] = true;
				}
				if ( (string) ( $comm['province_code'] ?? '' ) !== (string) $p_code || isset( $seen_comm_codes[ (string) $c_code ] ) ) {
					$orphan_or_dup = true;
				}
				$seen_comm_codes[ (string) $c_code ] = true;
			}
		}

		$lao_cai_communes = VietnamRegions::get_communes( '15' );
		$hanoi_communes   = VietnamRegions::get_communes( '01' );
		$hcm_communes     = VietnamRegions::get_communes( '79' );
		$meta             = $raw_json['metadata'] ?? array();

		$ok = 34 === (int) $status['province_count']
			&& 3321 === (int) $status['commune_count']
			&& 3321 === $total_communes
			&& 'dropdown' === $status['fallback_mode']
			&& $all_prov_codes_valid
			&& $all_comm_codes_valid
			&& $types_ok
			&& ! $orphan_or_dup
			&& isset( $commune_types_seen['commune'], $commune_types_seen['ward'], $commune_types_seen['special_zone'] )
			&& 'Tỉnh Lào Cai' === ( $provinces['15']['name'] ?? '' )
			&& 99 === count( $lao_cai_communes )
			&& 126 === count( $hanoi_communes )
			&& 168 === count( $hcm_communes )
			&& 'Phường Lào Cai' === ( $lao_cai_communes['02647']['name'] ?? '' )
			&& 'Phường Cam Đường' === ( $lao_cai_communes['02671']['name'] ?? '' )
			&& 'Phường Ba Đình' === ( $hanoi_communes['00004']['name'] ?? '' )
			&& 'Đặc khu Hoàng Sa' === ( VietnamRegions::get_communes( '48' )['20333']['name'] ?? '' )
			&& '2025-07-01' === ( $meta['effective_date'] ?? '' )
			&& '19/2025/QĐ-TTg' === ( $meta['version'] ?? '' )
			&& '' !== ( $meta['source_url'] ?? '' );

		$this->record(
			'TC-VNR-01',
			'Official 34-province / 3,321-commune dataset',
			$ok ? 'PASS' : 'FAIL',
			$ok ? '(QD 19/2025/QD-TTg: 34 provinces, 3,321 units verified)' : '(Verification assertion failed)'
		);
	}

	private function test_02_reject_duplicate_province_codes(): void {
		VietnamRegions::reset_dataset();
		$res = VietnamRegions::load_dataset( array(
			'provinces' => array(
				array( 'code' => '79', 'name' => 'TP. Hồ Chí Minh' ),
				array( 'code' => '79', 'name' => 'Trùng mã' ),
			),
			'communes'  => array(),
		) );
		$ok = is_wp_error( $res ) && 'despace_dataset_duplicate_province_code' === $res->get_error_code();
		$this->record( 'TC-VNR-02', 'Reject duplicate province codes in dataset', $ok ? 'PASS' : 'FAIL' );
	}

	private function test_03_commune_belongs_to_correct_province(): void {
		$this->load_sample_two_tier_fixture();
		$res = VietnamRegions::validate_and_normalize_address( array(
			'country'        => 'VN',
			'region_code'    => '79',
			'commune_code'   => '26734',
			'address_detail' => '12 Nguyễn Hữu Cảnh',
		) );
		$ok = ! is_wp_error( $res )
			&& '79' === $res['region_code']
			&& 'Thành phố Hồ Chí Minh' === $res['region_name']
			&& '26734' === $res['commune_code']
			&& 'Phường Sài Gòn' === $res['commune_name']
			&& true === $res['is_complete'];
		$this->record( 'TC-VNR-03', 'Link commune to matching province cleanly', $ok ? 'PASS' : 'FAIL' );
	}

	private function test_04_reject_duplicate_commune_codes(): void {
		VietnamRegions::reset_dataset();
		$res = VietnamRegions::load_dataset( array(
			'provinces' => array(
				array( 'code' => '79', 'name' => 'Thành phố Hồ Chí Minh' ),
			),
			'communes'  => array(
				array( 'code' => '26734', 'name' => 'Phường A', 'province_code' => '79' ),
				array( 'code' => '26734', 'name' => 'Phường B', 'province_code' => '79' ),
			),
		) );
		$ok = is_wp_error( $res ) && 'despace_dataset_duplicate_commune_code' === $res->get_error_code();
		$this->record( 'TC-VNR-04', 'Reject duplicate commune codes in dataset', $ok ? 'PASS' : 'FAIL' );
	}

	private function test_05_no_mandatory_district_field(): void {
		$locales = apply_filters( 'woocommerce_get_country_locale', array() );
		$fields  = apply_filters( 'woocommerce_default_address_fields', array(
			'address_2' => array( 'required' => true ),
			'postcode'  => array( 'required' => true ),
		) );

		$ok = false === ( $locales['VN']['address_2']['required'] ?? true )
			&& true === ( $locales['VN']['address_2']['hidden'] ?? false )
			&& false === ( $locales['VN']['state']['hidden'] ?? true )
			&& false === ( $fields['address_2']['required'] ?? true )
			&& false === ( $fields['postcode']['required'] ?? true );
		$this->record( 'TC-VNR-05', 'No mandatory district (quan/huyen) field in VN locale', $ok ? 'PASS' : 'FAIL' );
	}

	private function test_06_reject_invalid_province(): void {
		$this->load_sample_two_tier_fixture();
		$res = VietnamRegions::validate_and_normalize_address( array(
			'country'        => 'VN',
			'region_code'    => '99',
			'commune_name'   => 'Phường X',
			'address_detail' => '1 Đường A',
		) );
		$ok = is_wp_error( $res ) && 'despace_invalid_region_code' === $res->get_error_code();
		$this->record( 'TC-VNR-06', 'Reject unknown province code when dataset active', $ok ? 'PASS' : 'FAIL' );
	}

	private function test_07_reject_commune_from_wrong_province(): void {
		$this->load_sample_two_tier_fixture();
		$res = VietnamRegions::validate_and_normalize_address( array(
			'country'        => 'VN',
			'region_code'    => '01',
			'commune_code'   => '26734', // Belongs to 79, not 01.
			'address_detail' => '10 Hoàng Diệu',
		) );
		$ok = is_wp_error( $res ) && 'despace_commune_province_mismatch' === $res->get_error_code();
		$this->record( 'TC-VNR-07', 'Reject commune code not belonging to selected province', $ok ? 'PASS' : 'FAIL' );
	}

	private function test_08_free_text_fallback_works_without_full_dataset(): void {
		global $despace_mock_filters;
		$prev_filters = $despace_mock_filters['despace_vietnam_regions_dataset_path'] ?? null;
		add_filter( 'despace_vietnam_regions_dataset_path', fn() => '' );
		VietnamRegions::reset_dataset();

		$res = VietnamRegions::validate_and_normalize_address( array(
			'country'        => 'VN',
			'region_name'    => 'Thành phố Đà Nẵng',
			'commune_name'   => 'Đặc khu Hoàng Sa',
			'address_detail' => 'Khu dân cư số 1',
		) );
		$ok = ! is_wp_error( $res )
			&& 'free_text' === $res['commune_input_mode']
			&& false === $res['district_required']
			&& 'Thành phố Đà Nẵng' === $res['region_name']
			&& 'Đặc khu Hoàng Sa' === $res['commune_name']
			&& true === $res['is_complete'];

		if ( null === $prev_filters ) {
			unset( $despace_mock_filters['despace_vietnam_regions_dataset_path'] );
		} else {
			$despace_mock_filters['despace_vietnam_regions_dataset_path'] = $prev_filters;
		}
		VietnamRegions::reset_dataset();

		$this->record( 'TC-VNR-08', 'Allow free-text province/commune when dataset absent', $ok ? 'PASS' : 'FAIL' );
	}

	private function test_09_vietnamese_unicode_preservation(): void {
		VietnamRegions::reset_dataset();
		$res = VietnamRegions::validate_and_normalize_address( array(
			'country'        => 'VN',
			'region_name'    => 'Tỉnh Đắk Lắk',
			'commune_name'   => 'Phường Buôn Ma Thuột',
			'address_detail' => 'Số 88 Đường Nguyễn Thị Minh Khai, Tổ 3',
		) );
		$ok = ! is_wp_error( $res )
			&& '66' === $res['region_code']
			&& 'Tỉnh Đắk Lắk' === $res['region_name']
			&& 'Phường Buôn Ma Thuột' === $res['commune_name']
			&& 'Số 88 Đường Nguyễn Thị Minh Khai, Tổ 3' === $res['address_detail'];
		$this->record( 'TC-VNR-09', 'Preserve UTF-8 Vietnamese diacritics accurately', $ok ? 'PASS' : 'FAIL' );
	}

	private function test_10_legacy_order_data_preserved_readonly(): void {
		VietnamRegions::reset_dataset();
		$legacy_order = new Mock_Legacy_WC_Order( array(
			'shipping_country'   => 'VN',
			'shipping_state'     => 'Hồ Chí Minh',
			'shipping_city'      => 'Phường 22',
			'shipping_address_1' => '12 Nguyễn Hữu Cảnh',
			'shipping_address_2' => 'Quận Bình Thạnh',
			'shipping_postcode'  => '700000',
		) );

		$extracted = VietnamRegions::extract_order_address( $legacy_order, 'shipping' );
		$ok        = false === $legacy_order->mutated
			&& 'Quận Bình Thạnh' === $extracted['legacy_line_2']
			&& 'Phường 22' === $extracted['commune_name']
			&& '12 Nguyễn Hữu Cảnh' === $extracted['address_detail']
			&& true === $extracted['is_legacy'];
		$this->record( 'TC-VNR-10', 'Read legacy orders without mutating historical fields', $ok ? 'PASS' : 'FAIL' );
	}

	private function test_11_woocommerce_states_and_short_name_mapping(): void {
		VietnamRegions::reset_dataset();
		$states = apply_filters( 'woocommerce_states', array() );
		$vn     = $states['VN'] ?? array();

		$short_norm = VietnamRegions::validate_and_normalize_address( array(
			'country'        => 'VN',
			'region_name'    => 'Lào Cai',
			'commune_code'   => '02647',
			'address_detail' => '10 Hoàng Liên',
		) );

		$ok = 34 === count( $vn )
			&& 'Tỉnh Lào Cai' === ( $vn['15'] ?? '' )
			&& 'Thành phố Hà Nội' === ( $vn['01'] ?? '' )
			&& ! is_wp_error( $short_norm )
			&& '15' === $short_norm['region_code']
			&& '15' === ( $short_norm['wc_mapping']['state'] ?? '' )
			&& 'Phường Lào Cai' === ( $short_norm['wc_mapping']['city'] ?? '' )
			&& '10 Hoàng Liên' === ( $short_norm['wc_mapping']['address_1'] ?? '' );

		$this->record( 'TC-VNR-11', 'Map woocommerce_states VN and short province names', $ok ? 'PASS' : 'FAIL' );
	}

	private function test_12_reject_invalid_formats_and_corrupt_metadata(): void {
		VietnamRegions::reset_dataset();
		$bad_pcode = VietnamRegions::load_dataset( array(
			'provinces' => array( array( 'code' => '1', 'name' => 'Tỉnh Lỗi' ) ),
		) );
		$bad_ptype = VietnamRegions::load_dataset( array(
			'provinces' => array( array( 'code' => '15', 'name' => 'Tỉnh Lào Cai', 'type' => 'district' ) ),
		) );
		$bad_ccode = VietnamRegions::load_dataset( array(
			'provinces' => array(
				array(
					'code'     => '15',
					'name'     => 'Tỉnh Lào Cai',
					'type'     => 'province',
					'communes' => array( array( 'code' => '2647', 'name' => 'Phường Lào Cai', 'type' => 'ward' ) ),
				),
			),
		) );
		$bad_meta = VietnamRegions::load_dataset( array(
			'metadata'  => array( 'province_count' => 34, 'commune_count' => 3321 ),
			'provinces' => array( array( 'code' => '15', 'name' => 'Tỉnh Lào Cai', 'type' => 'province', 'communes' => array() ) ),
		) );

		$ok = is_wp_error( $bad_pcode )
			&& 'despace_dataset_invalid_province' === $bad_pcode->get_error_code()
			&& is_wp_error( $bad_ptype )
			&& 'despace_dataset_invalid_province_type' === $bad_ptype->get_error_code()
			&& is_wp_error( $bad_ccode )
			&& 'despace_dataset_invalid_commune' === $bad_ccode->get_error_code()
			&& is_wp_error( $bad_meta )
			&& 'despace_dataset_count_mismatch' === $bad_meta->get_error_code();

		$this->record( 'TC-VNR-12', 'Reject invalid 2/5-digit codes, types & meta mismatch', $ok ? 'PASS' : 'FAIL' );
	}
}

$runner = new M3VietnamRegionsUnitRunner();
$runner->run();

