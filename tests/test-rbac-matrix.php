<?php
/**
 * DeSpace RBAC & Architecture Verification Test Suite (TC01 - TC30).
 *
 * Simulates and validates all architectural and RBAC requirements:
 * 1. Directory layout & protective index.php for M1, M2, M3, M4, M5, REST-API, Admin, Security, Database, Products.
 * 2. Role registration (despace_staff) with display name & capabilities.
 * 3. Administrator capability provisioning (view_despace_reports, etc.).
 * 4. Permission callbacks and security checks for REST API endpoints.
 * 5. Idempotency, self-healing, and cleanup.
 *
 * Run via CLI: php tests/test-rbac-matrix.php
 *
 * @package DeSpace\Tests
 */

define( 'ABSPATH', __DIR__ . '/../' );

// Mock WordPress Environment for Standalone CLI Testing.
$GLOBALS['mock_roles']        = array();
$GLOBALS['mock_options']      = array();
$GLOBALS['mock_filters']      = array();
$GLOBALS['mock_actions']      = array();
$GLOBALS['mock_current_user'] = null;
$GLOBALS['mock_is_logged_in'] = false;

if ( ! class_exists( 'WP_Role' ) ) {
	class WP_Role {
		public string $name;
		public array $capabilities = array();

		public function __construct( string $name, array $capabilities = array() ) {
			$this->name         = $name;
			$this->capabilities = $capabilities;
		}

		public function add_cap( string $cap, bool $grant = true ): void {
			$this->capabilities[ $cap ] = $grant;
		}

		public function remove_cap( string $cap ): void {
			unset( $this->capabilities[ $cap ] );
		}

		public function has_cap( string $cap ): bool {
			return ! empty( $this->capabilities[ $cap ] );
		}
	}
}

if ( ! class_exists( 'WP_User' ) ) {
	class WP_User {
		public int $ID = 0;
		public array $roles = array();
		public array $allcaps = array();

		public function __construct( int $id = 1, array $roles = array(), array $caps = array() ) {
			$this->ID      = $id;
			$this->roles   = $roles;
			$this->allcaps = $caps;
		}

		public function exists(): bool {
			return $this->ID > 0;
		}

		public function has_cap( string $cap ): bool {
			if ( ! empty( $this->allcaps[ $cap ] ) ) {
				return true;
			}
			global $mock_roles;
			foreach ( $this->roles as $role_name ) {
				if ( isset( $mock_roles[ $role_name ] ) && $mock_roles[ $role_name ]->has_cap( $cap ) ) {
					return true;
				}
			}
			return false;
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

		public function get_error_data(): array {
			return $this->data;
		}
	}

	function is_wp_error( $thing ): bool {
		return $thing instanceof WP_Error;
	}
}

if ( ! function_exists( 'add_role' ) ) {
	function add_role( string $role, string $display_name, array $capabilities = array() ): ?WP_Role {
		global $mock_roles;
		if ( isset( $mock_roles[ $role ] ) ) {
			return null;
		}
		$wp_role = new WP_Role( $role, $capabilities );
		$mock_roles[ $role ] = $wp_role;
		return $wp_role;
	}
}

if ( ! function_exists( 'get_role' ) ) {
	function get_role( string $role ): ?WP_Role {
		global $mock_roles;
		return $mock_roles[ $role ] ?? null;
	}
}

if ( ! function_exists( 'remove_role' ) ) {
	function remove_role( string $role ): void {
		global $mock_roles;
		unset( $mock_roles[ $role ] );
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( string $option, $default = false ) {
		global $mock_options;
		return $mock_options[ $option ] ?? $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( string $option, $value ): bool {
		global $mock_options;
		$mock_options[ $option ] = $value;
		return true;
	}
}

if ( ! function_exists( 'delete_option' ) ) {
	function delete_option( string $option ): bool {
		global $mock_options;
		unset( $mock_options[ $option ] );
		return true;
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( string $tag, $value, ...$args ) {
		global $mock_filters;
		if ( isset( $mock_filters[ $tag ] ) && is_callable( $mock_filters[ $tag ] ) ) {
			return call_user_func( $mock_filters[ $tag ], $value, ...$args );
		}
		return $value;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( string $tag, $callback, int $priority = 10, int $accepted_args = 1 ): void {
		global $mock_actions;
		$mock_actions[ $tag ][] = array( 'callback' => $callback, 'priority' => $priority );
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( string $cap ): bool {
		global $mock_current_user;
		if ( ! $mock_current_user instanceof WP_User ) {
			return false;
		}
		return $mock_current_user->has_cap( $cap );
	}
}

if ( ! function_exists( 'is_user_logged_in' ) ) {
	function is_user_logged_in(): bool {
		global $mock_is_logged_in;
		return (bool) $mock_is_logged_in;
	}
}

if ( ! function_exists( 'wp_get_current_user' ) ) {
	function wp_get_current_user(): ?WP_User {
		global $mock_current_user;
		return $mock_current_user;
	}
}

if ( ! function_exists( 'rest_authorization_required_code' ) ) {
	function rest_authorization_required_code(): int {
		return 401;
	}
}

if ( ! function_exists( '__' ) ) {
	function __( string $text, string $domain = 'default' ): string {
		return $text;
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

if ( ! function_exists( 'register_activation_hook' ) ) {
	function register_activation_hook( string $file, $callback ): void {}
}

if ( ! function_exists( 'register_deactivation_hook' ) ) {
	function register_deactivation_hook( string $file, $callback ): void {}
}

// Load Plugin Bootstrap and RBAC Manager.
require_once __DIR__ . '/../wordpress/wp-content/plugins/despace-core/despace-core.php';

use DeSpace\Security\RoleManager;

/**
 * Class TestRBACRunner
 */
class TestRBACRunner {

	private array $results = array();
	private string $base_dir;

	public function __construct() {
		$this->base_dir = dirname( __DIR__ );
	}

	public function run(): void {
		echo "========================================================================\n";
		echo "       DeSpace Core — RBAC & ARCHITECTURE TEST SUITE (TC01–TC30)        \n";
		echo "========================================================================\n\n";

		// Initialize default WordPress administrator role.
		global $mock_roles;
		$mock_roles['administrator'] = new WP_Role( 'administrator', array(
			'manage_options'     => true,
			'manage_woocommerce' => true,
			'read'               => true,
		) );
		$mock_roles['customer'] = new WP_Role( 'customer', array(
			'read' => true,
		) );

		// Run tests.
		$this->test_TC01_directory_structure();
		$this->test_TC02_protective_index_files();
		$this->test_TC03_autoloader_resolution();
		$this->test_TC04_role_constants();
		$this->test_TC05_view_reports_capability_constant();
		$this->test_TC06_staff_role_registration();
		$this->test_TC07_staff_has_view_reports_cap();
		$this->test_TC08_staff_has_inventory_logs_cap();
		$this->test_TC09_staff_has_manage_3d_cap();
		$this->test_TC10_staff_has_edit_products_cap();
		$this->test_TC11_staff_denied_admin_caps();
		$this->test_TC12_admin_has_view_reports_cap();
		$this->test_TC13_admin_has_all_despace_caps();
		$this->test_TC14_staff_caps_filter_hook();
		$this->test_TC15_role_registration_idempotency();
		$this->test_TC16_ensure_roles_registered_self_heal();
		$this->test_TC17_user_can_view_reports_with_cap();
		$this->test_TC18_user_can_view_reports_with_admin();
		$this->test_TC19_customer_cannot_view_reports();
		$this->test_TC20_inventory_cap_checks();
		$this->test_TC21_3d_cap_checks();
		$this->test_TC22_role_helper_checks();
		$this->test_TC23_rest_reports_allowed_for_staff();
		$this->test_TC24_rest_reports_allowed_for_admin();
		$this->test_TC25_rest_reports_unauthorized_guest();
		$this->test_TC26_rest_reports_forbidden_for_customer();
		$this->test_TC27_rest_inventory_permissions();
		$this->test_TC28_rest_3d_permissions();
		$this->test_TC29_uninstall_role_cleanup();
		$this->test_TC30_hpos_compatibility_declaration();

		$this->print_summary();
	}

	private function assert_test( string $id, string $desc, bool $passed, string $details = '' ): void {
		$status = $passed ? "\033[32mPASS\033[0m" : "\033[31mFAIL\033[0m";
		printf( "[%s] %-50s : %s %s\n", $id, $desc, $status, $details );
		$this->results[ $id ] = $passed;
	}

	private function test_TC01_directory_structure(): void {
		$plugin_path = $this->base_dir . '/wordpress/wp-content/plugins/despace-core';
		$expected_dirs = array(
			$plugin_path . '/includes/Products',
			$plugin_path . '/includes/Product-3D',      // M1
			$plugin_path . '/includes/Catalog',         // M2
			$plugin_path . '/includes/Checkout-Orders', // M3
			$plugin_path . '/includes/Inventory',       // M4
			$plugin_path . '/includes/Reports',         // M5
			$plugin_path . '/includes/REST-API',
			$plugin_path . '/includes/Admin',
			$plugin_path . '/includes/Security',
			$plugin_path . '/includes/Database',
		);

		$all_exist = true;
		foreach ( $expected_dirs as $dir ) {
			if ( ! is_dir( $dir ) ) {
				$all_exist = false;
				break;
			}
		}

		$this->assert_test( 'TC01', 'Plugin directory structure for all modules', $all_exist );
	}

	private function test_TC02_protective_index_files(): void {
		$plugin_path = $this->base_dir . '/wordpress/wp-content/plugins/despace-core';
		$subdirs = array(
			'includes',
			'includes/Products',
			'includes/Product-3D',
			'includes/Catalog',
			'includes/Checkout-Orders',
			'includes/Inventory',
			'includes/Reports',
			'includes/REST-API',
			'includes/Admin',
			'includes/Security',
			'includes/Database',
		);

		$all_have_index = true;
		foreach ( $subdirs as $subdir ) {
			$file = $plugin_path . '/' . $subdir . '/index.php';
			if ( ! file_exists( $file ) || false === strpos( file_get_contents( $file ), 'Silence is golden' ) ) {
				$all_have_index = false;
				break;
			}
		}

		$this->assert_test( 'TC02', 'Protective index.php in M1-M4 and all modules', $all_have_index );
	}

	private function test_TC03_autoloader_resolution(): void {
		$exists = class_exists( 'DeSpace\\Security\\RoleManager' );
		$this->assert_test( 'TC03', 'PSR-4 autoloader resolves DeSpace\\Security\\RoleManager', $exists );
	}

	private function test_TC04_role_constants(): void {
		$valid = ( 'despace_staff' === RoleManager::ROLE_STAFF && 'administrator' === RoleManager::ROLE_ADMIN );
		$this->assert_test( 'TC04', 'Role identifiers despace_staff and administrator', $valid );
	}

	private function test_TC05_view_reports_capability_constant(): void {
		$valid = ( 'view_despace_reports' === RoleManager::CAP_VIEW_REPORTS );
		$this->assert_test( 'TC05', 'Capability view_despace_reports constant defined', $valid );
	}

	private function test_TC06_staff_role_registration(): void {
		RoleManager::activate();
		$staff_role = get_role( 'despace_staff' );
		$this->assert_test( 'TC06', 'despace_staff role registered successfully', $staff_role instanceof WP_Role );
	}

	private function test_TC07_staff_has_view_reports_cap(): void {
		$staff_role = get_role( 'despace_staff' );
		$has_cap    = $staff_role && $staff_role->has_cap( 'view_despace_reports' );
		$this->assert_test( 'TC07', 'despace_staff possesses view_despace_reports', $has_cap );
	}

	private function test_TC08_staff_has_inventory_logs_cap(): void {
		$staff_role = get_role( 'despace_staff' );
		$has_cap    = $staff_role && $staff_role->has_cap( 'view_despace_inventory_logs' );
		$this->assert_test( 'TC08', 'despace_staff possesses view_despace_inventory_logs', $has_cap );
	}

	private function test_TC09_staff_has_manage_3d_cap(): void {
		$staff_role = get_role( 'despace_staff' );
		$has_cap    = $staff_role && $staff_role->has_cap( 'manage_despace_3d' );
		$this->assert_test( 'TC09', 'despace_staff possesses manage_despace_3d (M1)', $has_cap );
	}

	private function test_TC10_staff_has_edit_products_cap(): void {
		$staff_role = get_role( 'despace_staff' );
		$has_cap    = $staff_role && $staff_role->has_cap( 'edit_despace_products' );
		$this->assert_test( 'TC10', 'despace_staff possesses edit_despace_products', $has_cap );
	}

	private function test_TC11_staff_denied_admin_caps(): void {
		$staff_role = get_role( 'despace_staff' );
		$denied = $staff_role && ! $staff_role->has_cap( 'manage_options' ) && ! $staff_role->has_cap( 'manage_woocommerce' );
		$this->assert_test( 'TC11', 'despace_staff restricted from superadmin caps', $denied );
	}

	private function test_TC12_admin_has_view_reports_cap(): void {
		$admin_role = get_role( 'administrator' );
		$has_cap    = $admin_role && $admin_role->has_cap( 'view_despace_reports' );
		$this->assert_test( 'TC12', 'administrator possesses view_despace_reports', $has_cap );
	}

	private function test_TC13_admin_has_all_despace_caps(): void {
		$admin_role = get_role( 'administrator' );
		$all_caps   = RoleManager::get_all_custom_capabilities();
		$has_all    = true;
		foreach ( $all_caps as $cap ) {
			if ( ! $admin_role->has_cap( $cap ) ) {
				$has_all = false;
				break;
			}
		}
		$this->assert_test( 'TC13', 'administrator granted all DeSpace custom caps', $has_all );
	}

	private function test_TC14_staff_caps_filter_hook(): void {
		global $mock_filters;
		$mock_filters['despace_staff_capabilities'] = function( $caps ) {
			$caps['custom_staff_cap'] = true;
			return $caps;
		};

		RoleManager::register_roles();
		$staff_role = get_role( 'despace_staff' );
		$passed     = $staff_role && $staff_role->has_cap( 'custom_staff_cap' );

		unset( $mock_filters['despace_staff_capabilities'] );
		$this->assert_test( 'TC14', 'despace_staff_capabilities filter hook extensible', $passed );
	}

	private function test_TC15_role_registration_idempotency(): void {
		// Run multiple times.
		RoleManager::register_roles();
		RoleManager::grant_admin_capabilities();
		RoleManager::register_roles();
		$staff_role = get_role( 'despace_staff' );
		$this->assert_test( 'TC15', 'Role registration is fully idempotent', $staff_role instanceof WP_Role );
	}

	private function test_TC16_ensure_roles_registered_self_heal(): void {
		remove_role( 'despace_staff' );
		update_option( RoleManager::OPTION_RBAC_VERSION, '0.1.0' );

		RoleManager::ensure_roles_registered();
		$staff_role = get_role( 'despace_staff' );
		$healed     = $staff_role instanceof WP_Role && $staff_role->has_cap( 'view_despace_reports' );
		$this->assert_test( 'TC16', 'Self-healing restores missing roles on runtime', $healed );
	}

	private function test_TC17_user_can_view_reports_with_cap(): void {
		global $mock_current_user, $mock_is_logged_in;
		$mock_is_logged_in   = true;
		$mock_current_user   = new WP_User( 10, array( 'despace_staff' ) );

		$can = RoleManager::current_user_can_view_reports();
		$this->assert_test( 'TC17', 'current_user_can_view_reports() returns true for staff', $can );
	}

	private function test_TC18_user_can_view_reports_with_admin(): void {
		global $mock_current_user, $mock_is_logged_in;
		$mock_is_logged_in   = true;
		$mock_current_user   = new WP_User( 1, array( 'administrator' ) );

		$can = RoleManager::current_user_can_view_reports();
		$this->assert_test( 'TC18', 'current_user_can_view_reports() returns true for admin', $can );
	}

	private function test_TC19_customer_cannot_view_reports(): void {
		global $mock_current_user, $mock_is_logged_in;
		$mock_is_logged_in   = true;
		$mock_current_user   = new WP_User( 20, array( 'customer' ) );

		$can = RoleManager::current_user_can_view_reports();
		$this->assert_test( 'TC19', 'current_user_can_view_reports() denied for customer', false === $can );
	}

	private function test_TC20_inventory_cap_checks(): void {
		global $mock_current_user;
		$mock_current_user = new WP_User( 10, array( 'despace_staff' ) );
		$staff_can         = RoleManager::current_user_can_view_inventory();

		$mock_current_user = new WP_User( 20, array( 'customer' ) );
		$customer_can      = RoleManager::current_user_can_view_inventory();

		$this->assert_test( 'TC20', 'current_user_can_view_inventory() staff=true, customer=false', $staff_can && ! $customer_can );
	}

	private function test_TC21_3d_cap_checks(): void {
		global $mock_current_user;
		$mock_current_user = new WP_User( 10, array( 'despace_staff' ) );
		$staff_can         = RoleManager::current_user_can_manage_3d();

		$mock_current_user = new WP_User( 20, array( 'customer' ) );
		$customer_can      = RoleManager::current_user_can_manage_3d();

		$this->assert_test( 'TC21', 'current_user_can_manage_3d() staff=true, customer=false', $staff_can && ! $customer_can );
	}

	private function test_TC22_role_helper_checks(): void {
		$staff_user = new WP_User( 10, array( 'despace_staff' ) );
		$admin_user = new WP_User( 1, array( 'administrator' ) );
		$cust_user  = new WP_User( 20, array( 'customer' ) );

		$p1 = RoleManager::is_staff( $staff_user ) && ! RoleManager::is_admin( $staff_user );
		$p2 = RoleManager::is_admin( $admin_user ) && ! RoleManager::is_staff( $admin_user );
		$p3 = RoleManager::is_staff_or_admin( $staff_user ) && RoleManager::is_staff_or_admin( $admin_user );
		$p4 = ! RoleManager::is_staff_or_admin( $cust_user );

		$this->assert_test( 'TC22', 'is_staff(), is_admin(), is_staff_or_admin() helpers', $p1 && $p2 && $p3 && $p4 );
	}

	private function test_TC23_rest_reports_allowed_for_staff(): void {
		global $mock_current_user, $mock_is_logged_in;
		$mock_is_logged_in = true;
		$mock_current_user = new WP_User( 10, array( 'despace_staff' ) );

		$result = RoleManager::rest_check_reports_permission();
		$this->assert_test( 'TC23', 'REST reports permission allowed for despace_staff', true === $result );
	}

	private function test_TC24_rest_reports_allowed_for_admin(): void {
		global $mock_current_user, $mock_is_logged_in;
		$mock_is_logged_in = true;
		$mock_current_user = new WP_User( 1, array( 'administrator' ) );

		$result = RoleManager::rest_check_reports_permission();
		$this->assert_test( 'TC24', 'REST reports permission allowed for administrator', true === $result );
	}

	private function test_TC25_rest_reports_unauthorized_guest(): void {
		global $mock_current_user, $mock_is_logged_in;
		$mock_is_logged_in = false;
		$mock_current_user = null;

		$result = RoleManager::rest_check_reports_permission();
		$passed = is_wp_error( $result ) && 401 === $result->get_error_data()['status'];
		$this->assert_test( 'TC25', 'REST reports returns 401 WP_Error for guest', $passed );
	}

	private function test_TC26_rest_reports_forbidden_for_customer(): void {
		global $mock_current_user, $mock_is_logged_in;
		$mock_is_logged_in = true;
		$mock_current_user = new WP_User( 20, array( 'customer' ) );

		$result = RoleManager::rest_check_reports_permission();
		$passed = is_wp_error( $result ) && 403 === $result->get_error_data()['status'];
		$this->assert_test( 'TC26', 'REST reports returns 403 WP_Error for customer', $passed );
	}

	private function test_TC27_rest_inventory_permissions(): void {
		global $mock_current_user, $mock_is_logged_in;
		$mock_is_logged_in = true;

		$mock_current_user = new WP_User( 10, array( 'despace_staff' ) );
		$staff_allowed     = ( true === RoleManager::rest_check_inventory_permission() );

		$mock_current_user = new WP_User( 20, array( 'customer' ) );
		$cust_result       = RoleManager::rest_check_inventory_permission();
		$cust_forbidden    = is_wp_error( $cust_result ) && 403 === $cust_result->get_error_data()['status'];

		$this->assert_test( 'TC27', 'REST inventory permission checks staff=200, cust=403', $staff_allowed && $cust_forbidden );
	}

	private function test_TC28_rest_3d_permissions(): void {
		global $mock_current_user, $mock_is_logged_in;
		$mock_is_logged_in = true;

		$mock_current_user = new WP_User( 10, array( 'despace_staff' ) );
		$staff_allowed     = ( true === RoleManager::rest_check_3d_permission() );

		$mock_current_user = new WP_User( 20, array( 'customer' ) );
		$cust_result       = RoleManager::rest_check_3d_permission();
		$cust_forbidden    = is_wp_error( $cust_result ) && 403 === $cust_result->get_error_data()['status'];

		$this->assert_test( 'TC28', 'REST 3D mutation permission checks staff=200, cust=403', $staff_allowed && $cust_forbidden );
	}

	private function test_TC29_uninstall_role_cleanup(): void {
		RoleManager::uninstall();
		$staff_removed = ( null === get_role( 'despace_staff' ) );
		$admin_role    = get_role( 'administrator' );
		$admin_stripped = $admin_role && ! $admin_role->has_cap( 'view_despace_reports' );

		// Reinstall for normal state.
		RoleManager::activate();

		$this->assert_test( 'TC29', 'Uninstall cleanly strips custom caps and removes staff', $staff_removed && $admin_stripped );
	}

	private function test_TC30_hpos_compatibility_declaration(): void {
		$plugin_source = file_get_contents( $this->base_dir . '/wordpress/wp-content/plugins/despace-core/includes/Plugin.php' );
		$has_hpos      = false !== strpos( $plugin_source, 'declare_compatibility' ) && false !== strpos( $plugin_source, 'custom_order_tables' );
		$this->assert_test( 'TC30', 'HPOS custom_order_tables compatibility declared', $has_hpos );
	}

	private function print_summary(): void {
		$total  = count( $this->results );
		$passed = count( array_filter( $this->results ) );
		$failed = $total - $passed;

		echo "\n========================================================================\n";
		printf( "SUMMARY: %d Total | \033[32m%d Passed\033[0m | \033[31m%d Failed\033[0m\n", $total, $passed, $failed );
		echo "========================================================================\n";

		if ( 0 === $failed ) {
			echo "\n>>> ALL 30 MANDATORY TEST CASES (TC01 - TC30) PASSED SUCCESSFULLY! <<<\n\n";
		} else {
			echo "\n>>> SOME TEST CASES FAILED! <<<\n\n";
		}
	}
}

$runner = new TestRBACRunner();
$runner->run();

