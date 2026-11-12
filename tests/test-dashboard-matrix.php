<?php
/**
 * DeSpace M5 Dashboard & Reports Verification Matrix (TC01 - TC20).
 *
 * Validates:
 * 1. Admin menu registration with capability view_despace_reports.
 * 2. REST API GET /wp-json/despace/v1/dashboard/metrics registration & permissions.
 * 3. Date sanitization, validation, and boundary rules.
 * 4. HPOS compliance via wc_get_orders() with strictly zero raw SQL queries.
 * 5. Revenue accuracy: processing + completed only, excluding on-hold (unreconciled bank transfers) and cancelled.
 * 6. Order status breakdown counts.
 * 7. Top 10 best sellers ranking, aggregation, and 3D flag detection.
 * 8. Pure ES6+ Fetch API compliance with X-WP-Nonce (no jQuery).
 *
 * Run via CLI: php tests/test-dashboard-matrix.php
 *
 * @package DeSpace\Tests
 */

define( 'ABSPATH', __DIR__ . '/../' );

// Mock WordPress Environment for Testing.
$GLOBALS['mock_menu_pages']    = array();
$GLOBALS['mock_submenu_pages'] = array();
$GLOBALS['mock_rest_routes']   = array();
$GLOBALS['mock_orders_db']     = array();
$GLOBALS['mock_products_db']   = array();
$GLOBALS['mock_current_caps']   = array();
$GLOBALS['mock_is_logged_in']  = false;
$GLOBALS['mock_scripts']       = array();
$GLOBALS['mock_styles']        = array();
$GLOBALS['mock_localized']     = array();

if ( ! class_exists( 'WP_REST_Server' ) ) {
	class WP_REST_Server {
		public const READABLE = 'GET';
	}
}

if ( ! class_exists( 'WP_REST_Request' ) ) {
	class WP_REST_Request {
		private array $params = array();

		public function __construct( array $params = array() ) {
			$this->params = $params;
		}

		public function get_param( string $key ) {
			return $this->params[ $key ] ?? null;
		}
	}
}

if ( ! class_exists( 'WP_REST_Response' ) ) {
	class WP_REST_Response {
		public $data;
		public int $status;

		public function __construct( $data = null, int $status = 200 ) {
			$this->data   = $data;
			$this->status = $status;
		}

		public function get_data() {
			return $this->data;
		}

		public function get_status(): int {
			return $this->status;
		}
	}
}

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		public string $code;
		public string $message;
		public array $data;

		public function __construct( string $code = '', string $message = '', array $data = array() ) {
			$this->code    = $code;
			$this->message = $message;
			$this->data    = $data;
		}

		public function get_error_code(): string {
			return $this->code;
		}

		public function get_error_message(): string {
			return $this->message;
		}

		public function get_error_data(): array {
			return $this->data;
		}
	}

	function is_wp_error( $thing ): bool {
		return $thing instanceof WP_Error;
	}
}

function rest_ensure_response( $response ) {
	if ( $response instanceof WP_Error ) {
		return $response;
	}
	return new WP_REST_Response( $response );
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( string $tag, $callback, int $priority = 10, int $accepted_args = 1 ): void {
		global $mock_actions;
		$mock_actions[ $tag ][] = array( 'callback' => $callback, 'priority' => $priority );
	}
}

if ( ! function_exists( 'is_admin' ) ) {
	function is_admin(): bool {
		return true;
	}
}

if ( ! function_exists( 'register_activation_hook' ) ) {
	function register_activation_hook( string $file, $callback ): void {}
}

if ( ! function_exists( 'register_deactivation_hook' ) ) {
	function register_deactivation_hook( string $file, $callback ): void {}
}

if ( ! function_exists( 'add_menu_page' ) ) {
	function add_menu_page( $page_title, $menu_title, $capability, $menu_slug, $function = '', $icon_url = '', $position = null ) {
		global $mock_menu_pages;
		$mock_menu_pages[ $menu_slug ] = array(
			'title'      => $menu_title,
			'capability' => $capability,
			'callback'   => $function,
		);
	}
}

if ( ! function_exists( 'add_submenu_page' ) ) {
	function add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $menu_slug, $function = '' ) {
		global $mock_submenu_pages;
		$mock_submenu_pages[ $parent_slug . '/' . $menu_slug ] = array(
			'title'      => $menu_title,
			'capability' => $capability,
			'callback'   => $function,
		);
	}
}

if ( ! function_exists( 'register_rest_route' ) ) {
	function register_rest_route( $namespace, $route, $args = array() ) {
		global $mock_rest_routes;
		$mock_rest_routes[ $namespace . $route ] = $args;
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( string $cap ): bool {
		global $mock_current_caps;
		return in_array( $cap, $mock_current_caps ?? array(), true );
	}
}

if ( ! function_exists( 'is_user_logged_in' ) ) {
	function is_user_logged_in(): bool {
		global $mock_is_logged_in;
		return (bool) $mock_is_logged_in;
	}
}

if ( ! function_exists( 'rest_authorization_required_code' ) ) {
	function rest_authorization_required_code(): int {
		return 401;
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $s ) {
		return trim( (string) $s );
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $val ) {
		return $val;
	}
}

if ( ! function_exists( '__' ) ) {
	function __( string $text, string $domain = 'default' ): string {
		return $text;
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( string $text, string $domain = 'default' ): string {
		return $text;
	}
}

if ( ! function_exists( 'esc_html_e' ) ) {
	function esc_html_e( string $text, string $domain = 'default' ): void {
		echo $text;
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES );
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( string $url ): string {
		return $url;
	}
}

if ( ! function_exists( 'rest_url' ) ) {
	function rest_url( string $path = '' ): string {
		return 'https://example.com/wp-json/' . ltrim( $path, '/' );
	}
}

if ( ! function_exists( 'wp_create_nonce' ) ) {
	function wp_create_nonce( string $action = '' ): string {
		return 'mock_nonce_' . $action;
	}
}

if ( ! function_exists( 'wp_enqueue_script' ) ) {
	function wp_enqueue_script( $handle, $src = '', $deps = array(), $ver = false, $in_footer = false ) {
		global $mock_scripts;
		$mock_scripts[ $handle ] = array( 'src' => $src, 'deps' => $deps );
	}
}

if ( ! function_exists( 'wp_enqueue_style' ) ) {
	function wp_enqueue_style( $handle, $src = '', $deps = array(), $ver = false, $media = 'all' ) {
		global $mock_styles;
		$mock_styles[ $handle ] = array( 'src' => $src );
	}
}

if ( ! function_exists( 'wp_localize_script' ) ) {
	function wp_localize_script( $handle, $name, $data ) {
		global $mock_localized;
		$mock_localized[ $handle ][ $name ] = $data;
	}
}

if ( ! function_exists( 'rest_validate_request_arg' ) ) {
	function rest_validate_request_arg( $value, $request, $param ) {
		return true;
	}
}

// Mock MockOrderItem.
class MockOrderItem {
	private int $product_id;
	private int $quantity;
	private float $total;

	public function __construct( int $product_id, int $quantity, float $total ) {
		$this->product_id = $product_id;
		$this->quantity   = $quantity;
		$this->total      = $total;
	}

	public function get_product_id(): int {
		return $this->product_id;
	}

	public function get_quantity(): int {
		return $this->quantity;
	}

	public function get_total(): float {
		return $this->total;
	}
}

// Mock MockOrder.
class MockOrder {
	private int $id;
	private string $status;
	private float $total;
	private float $refunded;
	private int $created_timestamp;
	private array $items;

	public function __construct( int $id, string $status, float $total, int $created_timestamp, array $items = array(), float $refunded = 0.0 ) {
		$this->id                = $id;
		$this->status            = $status;
		$this->total             = $total;
		$this->created_timestamp = $created_timestamp;
		$this->items             = $items;
		$this->refunded          = $refunded;
	}

	public function get_id(): int {
		return $this->id;
	}

	public function get_status(): string {
		return $this->status;
	}

	public function get_total(): float {
		return $this->total;
	}

	public function get_total_refunded(): float {
		return $this->refunded;
	}

	public function get_date_created(): int {
		return $this->created_timestamp;
	}

	public function get_items( string $type = 'line_item' ): array {
		return $this->items;
	}
}

// Mock MockProduct.
class MockProduct {
	private int $id;
	private string $name;
	private string $sku;
	private int $glb_id;

	public function __construct( int $id, string $name, string $sku, int $glb_id = 0 ) {
		$this->id     = $id;
		$this->name   = $name;
		$this->sku    = $sku;
		$this->glb_id = $glb_id;
	}

	public function get_id(): int {
		return $this->id;
	}

	public function get_name(): string {
		return $this->name;
	}

	public function get_sku(): string {
		return $this->sku;
	}

	public function get_image_id(): int {
		return 10;
	}
}

// Mock WooCommerce CRUD Functions.
if ( ! function_exists( 'wc_get_orders' ) ) {
	function wc_get_orders( array $args = array() ): array {
		global $mock_orders_db;
		$filtered = array();

		$date_range = $args['date_created'] ?? '';
		$parts = explode( '...', $date_range );
		$start = isset( $parts[0] ) ? (int) $parts[0] : 0;
		$end   = isset( $parts[1] ) ? (int) $parts[1] : PHP_INT_MAX;

		foreach ( $mock_orders_db as $order ) {
			$created = $order->get_date_created();
			if ( $created >= $start && $created <= $end ) {
				$filtered[] = $order;
			}
		}

		return $filtered;
	}
}

if ( ! function_exists( 'wc_get_product' ) ) {
	function wc_get_product( int $product_id ): ?MockProduct {
		global $mock_products_db;
		return $mock_products_db[ $product_id ] ?? null;
	}
}

if ( ! function_exists( 'get_post_meta' ) ) {
	function get_post_meta( int $post_id, string $key, bool $single = false ) {
		global $mock_products_db;
		if ( '_despace_glb_attachment_id' === $key && isset( $mock_products_db[ $post_id ] ) ) {
			$ref = new ReflectionClass( $mock_products_db[ $post_id ] );
			$prop = $ref->getProperty( 'glb_id' );
			$prop->setAccessible( true );
			return $prop->getValue( $mock_products_db[ $post_id ] );
		}
		return 0;
	}
}

if ( ! function_exists( 'get_posts' ) ) {
	function get_posts( array $args = array() ): array {
		global $mock_products_db;
		$ids = array();
		foreach ( $mock_products_db as $id => $p ) {
			if ( (int) get_post_meta( $id, '_despace_glb_attachment_id', true ) > 0 ) {
				$ids[] = $id;
			}
		}
		return $ids;
	}
}

if ( ! function_exists( 'wp_count_posts' ) ) {
	function wp_count_posts( string $type = 'post' ): object {
		global $mock_products_db;
		return (object) array( 'publish' => count( $mock_products_db ) );
	}
}

if ( ! function_exists( 'get_permalink' ) ) {
	function get_permalink( int $id = 0 ): string {
		return 'https://example.com/product/' . $id;
	}
}

if ( ! function_exists( 'wp_get_attachment_image_url' ) ) {
	function wp_get_attachment_image_url( int $id, string $size = 'thumbnail' ): string {
		return 'https://example.com/wp-content/uploads/img_' . $id . '.jpg';
	}
}

if ( ! function_exists( 'wc_placeholder_img_src' ) ) {
	function wc_placeholder_img_src(): string {
		return 'https://example.com/placeholder.jpg';
	}
}

if ( ! function_exists( 'plugin_dir_path' ) ) {
	function plugin_dir_path( string $file ): string {
		return dirname( $file ) . '/';
	}
}

if ( ! function_exists( 'plugin_dir_url' ) ) {
	function plugin_dir_url( string $file ): string {
		return 'https://example.com/wp-content/plugins/' . basename( dirname( $file ) ) . '/';
	}
}

if ( ! function_exists( 'plugin_basename' ) ) {
	function plugin_basename( string $file ): string {
		return basename( dirname( $file ) ) . '/' . basename( $file );
	}
}

// Load Plugin Bootstrap and Required Classes.
require_once __DIR__ . '/../wordpress/wp-content/plugins/despace-core/despace-core.php';
require_once __DIR__ . '/../wordpress/wp-content/plugins/despace-core/includes/Security/RoleManager.php';
require_once __DIR__ . '/../wordpress/wp-content/plugins/despace-core/includes/Reports/REST/DashboardMetricsController.php';
require_once __DIR__ . '/../wordpress/wp-content/plugins/despace-core/includes/Reports/Admin/DashboardPage.php';
require_once __DIR__ . '/../wordpress/wp-content/plugins/despace-core/includes/Reports/ReportsModule.php';

use DeSpace\Reports\REST\DashboardMetricsController;
use DeSpace\Reports\Admin\DashboardPage;
use DeSpace\Reports\ReportsModule;
use DeSpace\Security\RoleManager;

/**
 * Class TestDashboardRunner
 */
class TestDashboardRunner {

	private array $results = array();
	private string $base_dir;

	public function __construct() {
		$this->base_dir = dirname( __DIR__ );
	}

	public function run(): void {
		echo "========================================================================\n";
		echo "     DeSpace M5 — DASHBOARD & REPORTS TEST MATRIX (TC01–TC20)           \n";
		echo "========================================================================\n\n";

		$this->setup_mock_database();

		$this->test_TC01_admin_menu_registered_with_capability();
		$this->test_TC02_admin_page_capability_gate();
		$this->test_TC03_rest_route_registered();
		$this->test_TC04_rest_permission_blocks_guest();
		$this->test_TC05_rest_permission_blocks_customer();
		$this->test_TC06_rest_permission_allows_staff();
		$this->test_TC07_rest_permission_allows_admin();
		$this->test_TC08_date_sanitization_and_validation();
		$this->test_TC09_hpos_crud_contract_no_raw_sql();
		$this->test_TC10_valid_revenue_calculation();
		$this->test_TC11_unreconciled_on_hold_strictly_excluded();
		$this->test_TC12_cancelled_pending_failed_excluded();
		$this->test_TC13_refunds_deducted_from_revenue();
		$this->test_TC14_order_status_breakdown_counts();
		$this->test_TC15_3d_products_count();
		$this->test_TC16_top_10_ranking_and_aggregation();
		$this->test_TC17_top_10_has_3d_metadata_flag();
		$this->test_TC18_empty_date_range_graceful_handling();
		$this->test_TC19_admin_dashboard_assets_enqueued();
		$this->test_TC20_pure_es6_compliance_no_jquery();

		$this->print_summary();
	}

	private function assert_test( string $id, string $desc, bool $passed, string $details = '' ): void {
		$status = $passed ? "\033[32mPASS\033[0m" : "\033[31mFAIL\033[0m";
		printf( "[%s] %-50s : %s %s\n", $id, $desc, $status, $details );
		$this->results[ $id ] = $passed;
	}

	private function setup_mock_database(): void {
		global $mock_orders_db, $mock_products_db;

		// Mock Products: 5 products, 2 with 3D model.
		$mock_products_db = array(
			101 => new MockProduct( 101, 'Sofa Da DeSpace Elegance', 'DSP-SF-01', 501 ), // 3D = Yes
			102 => new MockProduct( 102, 'Bàn Ăn Gỗ Sồi Nordic', 'DSP-TB-02', 0 ),       // 3D = No
			103 => new MockProduct( 103, 'Ghế Thư Giãn Velvet Lounge', 'DSP-CH-03', 502 ), // 3D = Yes
			104 => new MockProduct( 104, 'Giường Ngủ Hiện Đại Milan', 'DSP-BD-04', 0 ),   // 3D = No
			105 => new MockProduct( 105, 'Tủ Quần Áo 4 Cánh Grand', 'DSP-WD-05', 0 ),    // 3D = No
		);

		$now = strtotime( '2026-11-10 12:00:00' );

		// Mock Orders:
		// Order 1: Completed, 10,000,000 VND. Items: 101 (qty 2, 8,000,000), 102 (qty 1, 2,000,000).
		// Order 2: Processing, 5,000,000 VND. Items: 103 (qty 1, 5,000,000).
		// Order 3: On-Hold (Manual Bank Transfer unverified), 20,000,000 VND. (MUST BE EXCLUDED FROM REVENUE!)
		// Order 4: Cancelled, 4,000,000 VND. (MUST BE EXCLUDED!)
		// Order 5: Completed, 15,000,000 VND with 2,000,000 refunded -> Net = 13,000,000. Items: 101 (qty 3, 15,000,000).
		// Order 6: Pending, 1,000,000 VND. (MUST BE EXCLUDED!)
		$mock_orders_db = array(
			new MockOrder( 1, 'completed', 10000000.0, $now, array(
				new MockOrderItem( 101, 2, 8000000.0 ),
				new MockOrderItem( 102, 1, 2000000.0 ),
			) ),
			new MockOrder( 2, 'processing', 5000000.0, $now, array(
				new MockOrderItem( 103, 1, 5000000.0 ),
			) ),
			new MockOrder( 3, 'on-hold', 20000000.0, $now, array(
				new MockOrderItem( 104, 2, 20000000.0 ),
			) ),
			new MockOrder( 4, 'cancelled', 4000000.0, $now, array(
				new MockOrderItem( 102, 2, 4000000.0 ),
			) ),
			new MockOrder( 5, 'completed', 15000000.0, $now, array(
				new MockOrderItem( 101, 3, 15000000.0 ),
			), 2000000.0 ),
			new MockOrder( 6, 'pending', 1000000.0, $now, array(
				new MockOrderItem( 105, 1, 1000000.0 ),
			) ),
		);
	}

	private function test_TC01_admin_menu_registered_with_capability(): void {
		global $mock_menu_pages, $mock_submenu_pages;
		DashboardPage::register_admin_menu();

		$has_top_menu = isset( $mock_menu_pages['despace-reports'] ) && RoleManager::CAP_VIEW_REPORTS === $mock_menu_pages['despace-reports']['capability'];
		$has_sub_menu = isset( $mock_submenu_pages['woocommerce/despace-reports'] ) && RoleManager::CAP_VIEW_REPORTS === $mock_submenu_pages['woocommerce/despace-reports']['capability'];

		$this->assert_test( 'TC01', 'Admin menu registered with view_despace_reports', $has_top_menu && $has_sub_menu );
	}

	private function test_TC02_admin_page_capability_gate(): void {
		global $mock_current_caps;

		// 1. Unauthorized customer.
		$mock_current_caps = array( 'read' );
		$denied = false === RoleManager::current_user_can_view_reports();

		// 2. Authorized staff.
		$mock_current_caps = array( 'read', RoleManager::CAP_VIEW_REPORTS );
		$allowed_staff = RoleManager::current_user_can_view_reports();

		// 3. Administrator.
		$mock_current_caps = array( 'read', 'manage_woocommerce', 'manage_options' );
		$allowed_admin = RoleManager::current_user_can_view_reports();

		$this->assert_test( 'TC02', 'Admin page access capability gate verification', $denied && $allowed_staff && $allowed_admin );
	}

	private function test_TC03_rest_route_registered(): void {
		global $mock_rest_routes;
		DashboardMetricsController::register_routes();

		$has_metrics  = isset( $mock_rest_routes['despace/v1/dashboard/metrics'] );
		$has_overview = isset( $mock_rest_routes['despace/v1/reports/overview'] );

		$this->assert_test( 'TC03', 'REST route /dashboard/metrics & /reports/overview', $has_metrics && $has_overview );
	}

	private function test_TC04_rest_permission_blocks_guest(): void {
		global $mock_is_logged_in, $mock_current_caps;
		$mock_is_logged_in = false;
		$mock_current_caps = array();

		$check = DashboardMetricsController::permissions_check();
		$passed = is_wp_error( $check ) && 401 === $check->get_error_data()['status'];

		$this->assert_test( 'TC04', 'REST permission blocks unauthenticated guest (401)', $passed );
	}

	private function test_TC05_rest_permission_blocks_customer(): void {
		global $mock_is_logged_in, $mock_current_caps;
		$mock_is_logged_in = true;
		$mock_current_caps = array( 'read' );

		$check = DashboardMetricsController::permissions_check();
		$passed = is_wp_error( $check ) && 403 === $check->get_error_data()['status'];

		$this->assert_test( 'TC05', 'REST permission blocks customer without cap (403)', $passed );
	}

	private function test_TC06_rest_permission_allows_staff(): void {
		global $mock_is_logged_in, $mock_current_caps;
		$mock_is_logged_in = true;
		$mock_current_caps = array( 'read', RoleManager::CAP_VIEW_REPORTS );

		$check = DashboardMetricsController::permissions_check();
		$this->assert_test( 'TC06', 'REST permission allows staff with view_despace_reports', true === $check );
	}

	private function test_TC07_rest_permission_allows_admin(): void {
		global $mock_is_logged_in, $mock_current_caps;
		$mock_is_logged_in = true;
		$mock_current_caps = array( 'read', 'manage_woocommerce' );

		$check = DashboardMetricsController::permissions_check();
		$this->assert_test( 'TC07', 'REST permission allows administrator with manage_wc', true === $check );
	}

	private function test_TC08_date_sanitization_and_validation(): void {
		// Valid dates.
		$req_valid = new WP_REST_Request( array( 'from_date' => '2026-11-01', 'to_date' => '2026-11-12' ) );
		$res_valid = DashboardMetricsController::get_metrics( $req_valid );

		// Invalid format.
		$req_invalid = new WP_REST_Request( array( 'from_date' => 'invalid-date', 'to_date' => '2026-11-12' ) );
		$res_invalid = DashboardMetricsController::get_metrics( $req_invalid );

		// from_date > to_date.
		$req_inverted = new WP_REST_Request( array( 'from_date' => '2026-11-20', 'to_date' => '2026-11-10' ) );
		$res_inverted = DashboardMetricsController::get_metrics( $req_inverted );

		$passed = ! is_wp_error( $res_valid )
			&& is_wp_error( $res_invalid ) && 400 === $res_invalid->get_error_data()['status']
			&& is_wp_error( $res_inverted ) && 400 === $res_inverted->get_error_data()['status'];

		$this->assert_test( 'TC08', 'Date parameter sanitization, format & bounds', $passed );
	}

	private function test_TC09_hpos_crud_contract_no_raw_sql(): void {
		$reflection = new ReflectionClass( DashboardMetricsController::class );
		$source     = file_get_contents( $reflection->getFileName() );

		// Strictly no raw SQL queries on orders/posts.
		$no_raw_posts = false === stripos( $source, 'wp_posts' );
		$no_raw_meta  = false === stripos( $source, 'wp_postmeta' );
		$no_raw_cot   = false === stripos( $source, 'wc_orders' );
		$uses_crud    = false !== strpos( $source, 'wc_get_orders' );

		$passed = $no_raw_posts && $no_raw_meta && $no_raw_cot && $uses_crud;
		$this->assert_test( 'TC09', 'HPOS compliant: uses wc_get_orders(), zero raw SQL', $passed );
	}

	private function test_TC10_valid_revenue_calculation(): void {
		$req = new WP_REST_Request( array( 'from_date' => '2026-11-01', 'to_date' => '2026-11-15' ) );
		$res = DashboardMetricsController::get_metrics( $req );
		$data = $res->get_data();

		// Expected Valid Revenue:
		// Order 1 (completed): 10,000,000
		// Order 2 (processing): 5,000,000
		// Order 5 (completed): 15,000,000 - 2,000,000 refund = 13,000,000
		// Total Valid Revenue = 28,000,000 VND
		$expected = 28000000.0;
		$actual   = (float) $data['metrics']['valid_revenue'];

		$this->assert_test( 'TC10', 'Valid revenue sum: processing + completed (28M)', $expected === $actual );
	}

	private function test_TC11_unreconciled_on_hold_strictly_excluded(): void {
		$req = new WP_REST_Request( array( 'from_date' => '2026-11-01', 'to_date' => '2026-11-15' ) );
		$res = DashboardMetricsController::get_metrics( $req );
		$data = $res->get_data();

		// Order 3 is on-hold with 20,000,000. If it were counted, revenue would be 48M.
		$excluded = ( 28000000.0 === (float) $data['metrics']['valid_revenue'] );
		$this->assert_test( 'TC11', 'Unreconciled bank transfer (on-hold) excluded', $excluded );
	}

	private function test_TC12_cancelled_pending_failed_excluded(): void {
		$req = new WP_REST_Request( array( 'from_date' => '2026-11-01', 'to_date' => '2026-11-15' ) );
		$res = DashboardMetricsController::get_metrics( $req );
		$data = $res->get_data();

		// Cancelled (4M) and Pending (1M) must NOT be in revenue.
		$excluded = ( 28000000.0 === (float) $data['metrics']['valid_revenue'] );
		$this->assert_test( 'TC12', 'Cancelled and pending orders excluded from revenue', $excluded );
	}

	private function test_TC13_refunds_deducted_from_revenue(): void {
		$req = new WP_REST_Request( array( 'from_date' => '2026-11-01', 'to_date' => '2026-11-15' ) );
		$res = DashboardMetricsController::get_metrics( $req );
		$data = $res->get_data();

		// Order 5 was 15M with 2M refund, so net is 13M.
		// Total is 10M + 5M + 13M = 28M.
		$passed = ( 28000000.0 === (float) $data['metrics']['valid_revenue'] );
		$this->assert_test( 'TC13', 'Refunds accurately deducted from net revenue', $passed );
	}

	private function test_TC14_order_status_breakdown_counts(): void {
		$req = new WP_REST_Request( array( 'from_date' => '2026-11-01', 'to_date' => '2026-11-15' ) );
		$res = DashboardMetricsController::get_metrics( $req );
		$data = $res->get_data();

		$status = $data['orders_by_status'];
		$passed = ( 2 === $status['completed'] )
			&& ( 1 === $status['processing'] )
			&& ( 1 === $status['on-hold'] )
			&& ( 1 === $status['cancelled'] )
			&& ( 1 === $status['pending'] )
			&& ( 6 === $data['metrics']['total_orders'] )
			&& ( 3 === $data['metrics']['valid_orders_count'] );

		$this->assert_test( 'TC14', 'Order counts breakdown by status accurately', $passed );
	}

	private function test_TC15_3d_products_count(): void {
		$req = new WP_REST_Request( array( 'from_date' => '2026-11-01', 'to_date' => '2026-11-15' ) );
		$res = DashboardMetricsController::get_metrics( $req );
		$data = $res->get_data();

		// Products 101 and 103 have 3D models out of 5 total products.
		$passed = ( 2 === $data['metrics']['products_with_3d_count'] )
			&& ( 5 === $data['metrics']['total_products_count'] )
			&& ( 40.0 === (float) $data['metrics']['percentage_3d'] );

		$this->assert_test( 'TC15', '3D model count (_despace_glb_attachment_id)', $passed );
	}

	private function test_TC16_top_10_ranking_and_aggregation(): void {
		$req = new WP_REST_Request( array( 'from_date' => '2026-11-01', 'to_date' => '2026-11-15' ) );
		$res = DashboardMetricsController::get_metrics( $req );
		$data = $res->get_data();

		$top = $data['top_products'];

		// Sales from valid orders (1, 2, 5):
		// Prod 101: Order 1 (qty 2) + Order 5 (qty 3) = 5 sold, 23M rev. Rank 1!
		// Prod 103: Order 2 (qty 1) = 1 sold, 5M rev.
		// Prod 102: Order 1 (qty 1) = 1 sold, 2M rev.
		// (Order 4 cancelled: 102 qty 2 excluded. Order 3 on-hold: 104 qty 2 excluded).
		$first_is_101  = isset( $top[0] ) && 101 === $top[0]['product_id'] && 5 === $top[0]['quantity_sold'];
		$second_is_103 = isset( $top[1] ) && 103 === $top[1]['product_id'] && 1 === $top[1]['quantity_sold'] && 5000000.0 === (float) $top[1]['total_revenue'];
		$third_is_102  = isset( $top[2] ) && 102 === $top[2]['product_id'] && 1 === $top[2]['quantity_sold'] && 2000000.0 === (float) $top[2]['total_revenue'];

		$passed = $first_is_101 && $second_is_103 && $third_is_102 && 3 === count( $top );
		$this->assert_test( 'TC16', 'Top Best Sellers: multi-order aggregation & rank', $passed );
	}

	private function test_TC17_top_10_has_3d_metadata_flag(): void {
		$req = new WP_REST_Request( array( 'from_date' => '2026-11-01', 'to_date' => '2026-11-15' ) );
		$res = DashboardMetricsController::get_metrics( $req );
		$data = $res->get_data();

		$top = $data['top_products'];
		// Prod 101 has 3D (true), Prod 103 has 3D (true), Prod 102 does NOT (false).
		$p101_3d = isset( $top[0] ) && true === $top[0]['has_3d'];
		$p103_3d = isset( $top[1] ) && true === $top[1]['has_3d'];
		$p102_3d = isset( $top[2] ) && false === $top[2]['has_3d'];

		$this->assert_test( 'TC17', 'Top products include 3D AR availability flag', $p101_3d && $p103_3d && $p102_3d );
	}

	private function test_TC18_empty_date_range_graceful_handling(): void {
		// Far future date with no orders.
		$req = new WP_REST_Request( array( 'from_date' => '2030-01-01', 'to_date' => '2030-01-10' ) );
		$res = DashboardMetricsController::get_metrics( $req );
		$data = $res->get_data();

		$passed = ( 0.0 === (float) $data['metrics']['valid_revenue'] )
			&& ( 0 === $data['metrics']['total_orders'] )
			&& ( 0 === count( $data['top_products'] ) )
			&& ( 0.0 === (float) $data['metrics']['average_order_value'] );

		$this->assert_test( 'TC18', 'Empty date range returns valid zero metrics', $passed );
	}

	private function test_TC19_admin_dashboard_assets_enqueued(): void {
		global $mock_scripts, $mock_styles, $mock_localized;

		DashboardPage::enqueue_assets( 'toplevel_page_despace-reports' );

		$has_css   = isset( $mock_styles['despace-admin-dashboard-css'] );
		$has_js    = isset( $mock_scripts['despace-admin-dashboard-js'] );
		$has_nonce = isset( $mock_localized['despace-admin-dashboard-js']['despaceDashboardSettings']['nonce'] );

		$this->assert_test( 'TC19', 'Dashboard CSS, JS and X-WP-Nonce localized', $has_css && $has_js && $has_nonce );
	}

	private function test_TC20_pure_es6_compliance_no_jquery(): void {
		$js_path = $this->base_dir . '/wordpress/wp-content/plugins/despace-core/assets/js/admin-dashboard.js';
		$js_code = file_get_contents( $js_path );

		$uses_fetch   = false !== strpos( $js_code, 'fetch(' );
		$uses_nonce   = false !== strpos( $js_code, 'X-WP-Nonce' );
		$no_jquery_fn = false === strpos( $js_code, 'jQuery(' );
		$has_dollar_call = (bool) preg_match( '/(?<!\\\\)\$\s*\(/', $js_code );
		$no_dollar    = ! $has_dollar_call;

		$passed = $uses_fetch && $uses_nonce && $no_jquery_fn && $no_dollar;
		$this->assert_test( 'TC20', 'Pure ES6+ Fetch API client, strictly NO jQuery', $passed );
	}

	private function print_summary(): void {
		$total  = count( $this->results );
		$passed = count( array_filter( $this->results ) );
		$failed = $total - $passed;

		echo "\n========================================================================\n";
		printf( "SUMMARY: %d Total | \033[32m%d Passed\033[0m | \033[31m%d Failed\033[0m\n", $total, $passed, $failed );
		echo "========================================================================\n";

		if ( 0 === $failed ) {
			echo "\n>>> ALL 20 MANDATORY TEST CASES (TC01 - TC20) PASSED SUCCESSFULLY! <<<\n\n";
		} else {
			echo "\n>>> SOME TEST CASES FAILED! <<<\n\n";
		}
	}
}

$runner = new TestDashboardRunner();
$runner->run();
