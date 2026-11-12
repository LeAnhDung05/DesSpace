<?php
/**
 * DeSpace Role-Based Access Control (RBAC) Manager.
 *
 * Handles custom roles (despace_staff), capability registration for staff
 * and administrator, and permission verification for Admin & REST endpoints.
 *
 * @package DeSpace\Security
 */

namespace DeSpace\Security;

defined( 'ABSPATH' ) || exit;

/**
 * Class RoleManager
 */
class RoleManager {

	/**
	 * DeSpace Staff Role identifier.
	 */
	public const ROLE_STAFF = 'despace_staff';

	/**
	 * WordPress Administrator Role identifier.
	 */
	public const ROLE_ADMIN = 'administrator';

	/**
	 * Capability to view DeSpace dashboard and analytics reports.
	 */
	public const CAP_VIEW_REPORTS = 'view_despace_reports';

	/**
	 * Capability to view DeSpace inventory traceability audit logs.
	 */
	public const CAP_VIEW_INVENTORY_LOGS = 'view_despace_inventory_logs';

	/**
	 * Capability to manage, upload, and update 3D/GLB models and AR metadata.
	 */
	public const CAP_MANAGE_3D = 'manage_despace_3d';

	/**
	 * Capability to manage DeSpace core options and settings.
	 */
	public const CAP_MANAGE_SETTINGS = 'manage_despace_settings';

	/**
	 * Capability to edit DeSpace specific product metadata.
	 */
	public const CAP_EDIT_PRODUCTS = 'edit_despace_products';

	/**
	 * Database option tracking RBAC schema version.
	 */
	public const OPTION_RBAC_VERSION = 'despace_rbac_version';

	/**
	 * Current RBAC schema version.
	 */
	public const RBAC_VERSION = '1.0.0';

	/**
	 * Initialize RBAC hooks.
	 */
	public static function init(): void {
		// Ensure roles and capabilities are registered even if activation hook was bypassed.
		add_action( 'init', array( __CLASS__, 'ensure_roles_registered' ), 5 );
	}

	/**
	 * Plugin activation entry point for RBAC.
	 */
	public static function activate(): void {
		self::register_roles();
		self::grant_admin_capabilities();
		update_option( self::OPTION_RBAC_VERSION, self::RBAC_VERSION );
	}

	/**
	 * Plugin deactivation hook.
	 *
	 * Note: Roles and capabilities are intentionally preserved on deactivation
	 * to prevent destructive permission loss during routine plugin updates.
	 */
	public static function deactivate(): void {
		// No destructive removal on deactivation.
	}

	/**
	 * Plugin uninstall cleanup for RBAC.
	 */
	public static function uninstall(): void {
		self::remove_roles();
		delete_option( self::OPTION_RBAC_VERSION );
	}

	/**
	 * Ensure roles and capabilities exist on WordPress runtime.
	 */
	public static function ensure_roles_registered(): void {
		$installed_version = get_option( self::OPTION_RBAC_VERSION, '0.0.0' );

		if ( version_compare( (string) $installed_version, self::RBAC_VERSION, '<' ) || ! get_role( self::ROLE_STAFF ) ) {
			self::register_roles();
			self::grant_admin_capabilities();
			update_option( self::OPTION_RBAC_VERSION, self::RBAC_VERSION );
		}
	}

	/**
	 * Register or update the despace_staff role with required capabilities.
	 */
	public static function register_roles(): void {
		$staff_caps = self::get_staff_capabilities();

		$existing_role = get_role( self::ROLE_STAFF );

		if ( ! $existing_role ) {
			add_role(
				self::ROLE_STAFF,
				__( 'DeSpace Staff', 'despace-core' ),
				$staff_caps
			);
		} else {
			// Idempotently ensure all declared capabilities are granted to existing role.
			foreach ( $staff_caps as $cap => $grant ) {
				if ( $grant ) {
					$existing_role->add_cap( $cap );
				} else {
					$existing_role->remove_cap( $cap );
				}
			}
		}
	}

	/**
	 * Grant all DeSpace capabilities to the administrator role.
	 */
	public static function grant_admin_capabilities(): void {
		$admin_role = get_role( self::ROLE_ADMIN );

		if ( ! $admin_role ) {
			return;
		}

		$all_caps = self::get_all_custom_capabilities();

		foreach ( $all_caps as $cap ) {
			$admin_role->add_cap( $cap );
		}
	}

	/**
	 * Remove custom DeSpace roles and capabilities.
	 */
	public static function remove_roles(): void {
		// 1. Remove capabilities from administrator.
		$admin_role = get_role( self::ROLE_ADMIN );
		if ( $admin_role ) {
			foreach ( self::get_all_custom_capabilities() as $cap ) {
				$admin_role->remove_cap( $cap );
			}
		}

		// 2. Remove the despace_staff role.
		remove_role( self::ROLE_STAFF );
	}

	/**
	 * Get the default capabilities array for despace_staff.
	 *
	 * @return array<string, bool>
	 */
	public static function get_staff_capabilities(): array {
		$capabilities = array(
			// Standard WordPress baseline caps for admin access.
			'read'                        => true,
			'view_admin_dashboard'        => true,

			// DeSpace specific capabilities.
			self::CAP_VIEW_REPORTS        => true,
			self::CAP_VIEW_INVENTORY_LOGS => true,
			self::CAP_MANAGE_3D           => true,
			self::CAP_EDIT_PRODUCTS       => true,

			// Staff must NOT have superadmin or full store configuration powers.
			'manage_options'              => false,
			'manage_woocommerce'          => false,
		);

		/**
		 * Filter capabilities granted to the despace_staff role.
		 *
		 * @param array<string, bool> $capabilities Staff capabilities map.
		 */
		return apply_filters( 'despace_staff_capabilities', $capabilities );
	}

	/**
	 * Get a list of all custom capabilities introduced by DeSpace Core.
	 *
	 * @return string[]
	 */
	public static function get_all_custom_capabilities(): array {
		$capabilities = array(
			self::CAP_VIEW_REPORTS,
			self::CAP_VIEW_INVENTORY_LOGS,
			self::CAP_MANAGE_3D,
			self::CAP_MANAGE_SETTINGS,
			self::CAP_EDIT_PRODUCTS,
		);

		/**
		 * Filter the complete list of custom DeSpace capabilities.
		 *
		 * @param string[] $capabilities List of capability strings.
		 */
		return apply_filters( 'despace_all_capabilities', $capabilities );
	}

	/**
	 * Check if current user (or specified user) can view DeSpace reports.
	 *
	 * Satisfied if user has view_despace_reports or full WooCommerce management rights.
	 *
	 * @param int|\WP_User|null $user Optional user ID or WP_User instance. Defaults to current user.
	 * @return bool
	 */
	public static function current_user_can_view_reports( $user = null ): bool {
		return self::user_has_capability( $user, self::CAP_VIEW_REPORTS )
			|| self::user_has_capability( $user, 'manage_woocommerce' )
			|| self::user_has_capability( $user, 'manage_options' );
	}

	/**
	 * Check if user can view inventory logs.
	 *
	 * @param int|\WP_User|null $user Optional user ID or WP_User instance. Defaults to current user.
	 * @return bool
	 */
	public static function current_user_can_view_inventory( $user = null ): bool {
		return self::user_has_capability( $user, self::CAP_VIEW_INVENTORY_LOGS )
			|| self::user_has_capability( $user, 'manage_woocommerce' )
			|| self::user_has_capability( $user, 'manage_options' );
	}

	/**
	 * Check if user can manage 3D / AR assets.
	 *
	 * @param int|\WP_User|null $user Optional user ID or WP_User instance. Defaults to current user.
	 * @return bool
	 */
	public static function current_user_can_manage_3d( $user = null ): bool {
		return self::user_has_capability( $user, self::CAP_MANAGE_3D )
			|| self::user_has_capability( $user, 'manage_woocommerce' )
			|| self::user_has_capability( $user, 'manage_options' );
	}

	/**
	 * Check if user has despace_staff role.
	 *
	 * @param int|\WP_User|null $user Optional user ID or WP_User instance. Defaults to current user.
	 * @return bool
	 */
	public static function is_staff( $user = null ): bool {
		$user_obj = self::resolve_user( $user );
		if ( ! $user_obj || ! ( $user_obj instanceof \WP_User ) ) {
			return false;
		}

		return in_array( self::ROLE_STAFF, (array) $user_obj->roles, true );
	}

	/**
	 * Check if user has administrator role.
	 *
	 * @param int|\WP_User|null $user Optional user ID or WP_User instance. Defaults to current user.
	 * @return bool
	 */
	public static function is_admin( $user = null ): bool {
		$user_obj = self::resolve_user( $user );
		if ( ! $user_obj || ! ( $user_obj instanceof \WP_User ) ) {
			return false;
		}

		return in_array( self::ROLE_ADMIN, (array) $user_obj->roles, true );
	}

	/**
	 * Check if user has staff or administrator role.
	 *
	 * @param int|\WP_User|null $user Optional user ID or WP_User instance. Defaults to current user.
	 * @return bool
	 */
	public static function is_staff_or_admin( $user = null ): bool {
		return self::is_staff( $user ) || self::is_admin( $user );
	}

	/**
	 * REST permission callback for reports endpoints (GET /reports/overview, GET /reports/top-products).
	 *
	 * @param \WP_REST_Request|null $request
	 * @return true|\WP_Error
	 */
	public static function rest_check_reports_permission( $request = null ) {
		if ( self::current_user_can_view_reports() ) {
			return true;
		}

		if ( function_exists( 'is_user_logged_in' ) && ! is_user_logged_in() ) {
			return new \WP_Error(
				'despace_rest_unauthorized',
				__( 'Authentication is required to access DeSpace reports.', 'despace-core' ),
				array( 'status' => function_exists( 'rest_authorization_required_code' ) ? rest_authorization_required_code() : 401 )
			);
		}

		return new \WP_Error(
			'despace_rest_forbidden',
			__( 'You do not have permission to view DeSpace reports.', 'despace-core' ),
			array( 'status' => 403 )
		);
	}

	/**
	 * REST permission callback for inventory logs endpoint (GET /inventory/logs).
	 *
	 * @param \WP_REST_Request|null $request
	 * @return true|\WP_Error
	 */
	public static function rest_check_inventory_permission( $request = null ) {
		if ( self::current_user_can_view_inventory() ) {
			return true;
		}

		if ( function_exists( 'is_user_logged_in' ) && ! is_user_logged_in() ) {
			return new \WP_Error(
				'despace_rest_unauthorized',
				__( 'Authentication is required to view inventory logs.', 'despace-core' ),
				array( 'status' => function_exists( 'rest_authorization_required_code' ) ? rest_authorization_required_code() : 401 )
			);
		}

		return new \WP_Error(
			'despace_rest_forbidden',
			__( 'You do not have permission to view inventory logs.', 'despace-core' ),
			array( 'status' => 403 )
		);
	}

	/**
	 * REST permission callback for 3D asset mutation endpoint (POST /products/{id}/3d).
	 *
	 * @param \WP_REST_Request|null $request
	 * @return true|\WP_Error
	 */
	public static function rest_check_3d_permission( $request = null ) {
		if ( self::current_user_can_manage_3d() ) {
			return true;
		}

		if ( function_exists( 'is_user_logged_in' ) && ! is_user_logged_in() ) {
			return new \WP_Error(
				'despace_rest_unauthorized',
				__( 'Authentication is required to manage 3D assets.', 'despace-core' ),
				array( 'status' => function_exists( 'rest_authorization_required_code' ) ? rest_authorization_required_code() : 401 )
			);
		}

		return new \WP_Error(
			'despace_rest_forbidden',
			__( 'You do not have permission to manage 3D assets.', 'despace-core' ),
			array( 'status' => 403 )
		);
	}

	/**
	 * Helper to check a capability on a specific user or current user.
	 *
	 * @param int|\WP_User|null $user
	 * @param string            $capability
	 * @return bool
	 */
	private static function user_has_capability( $user, string $capability ): bool {
		if ( null === $user ) {
			return function_exists( 'current_user_can' ) && current_user_can( $capability );
		}

		$user_obj = self::resolve_user( $user );
		if ( ! $user_obj || ! ( $user_obj instanceof \WP_User ) ) {
			return false;
		}

		return $user_obj->has_cap( $capability );
	}

	/**
	 * Resolve user argument to WP_User instance.
	 *
	 * @param int|\WP_User|null $user
	 * @return \WP_User|null
	 */
	private static function resolve_user( $user ): ?\WP_User {
		if ( $user instanceof \WP_User ) {
			return $user;
		}

		if ( is_numeric( $user ) && function_exists( 'get_userdata' ) ) {
			$found = get_userdata( (int) $user );
			return $found instanceof \WP_User ? $found : null;
		}

		if ( null === $user && function_exists( 'wp_get_current_user' ) ) {
			$current = wp_get_current_user();
			return ( $current instanceof \WP_User && $current->exists() ) ? $current : null;
		}

		return null;
	}
}

