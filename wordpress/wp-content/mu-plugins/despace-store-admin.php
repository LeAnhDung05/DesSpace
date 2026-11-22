<?php
/**
 * DeSpace Atelier Admin Cockpit (v3.2).
 *
 * Keeps all native WordPress & WooCommerce admin screens completely untouched
 * as original, while providing a unified, enterprise-grade 6-module DeSpace
 * management portal under a single "DeSpace" sidebar menu.
 *
 * @package DeSpace
 */

defined( 'ABSPATH' ) || exit;

/* ==========================================================================
   1. CAPABILITY HELPERS & ACTION SCHEDULER / NOTICE HYGIENE
   ========================================================================== */

/**
 * Keep store management controls visible to shop managers and administrators.
 */
function despace_store_admin_can_manage() {
	if ( ! function_exists( 'wp_get_current_user' ) ) {
		return false;
	}

	return current_user_can( 'manage_woocommerce' ) || current_user_can( 'manage_options' );
}

/**
 * Determine the primary DeSpace capability held by the current user for the unified cockpit menu.
 */
function despace_store_admin_hub_capability() {
	if ( ! function_exists( 'current_user_can' ) ) {
		return '';
	}

	$candidates = array(
		'manage_woocommerce',
		'manage_options',
		'view_despace_reports',
		'view_despace_inventory_logs',
		'manage_despace_3d',
	);

	foreach ( $candidates as $cap ) {
		if ( current_user_can( $cap ) ) {
			return $cap;
		}
	}

	return '';
}

function despace_store_admin_can_access_hub() {
	return '' !== despace_store_admin_hub_capability();
}

function despace_store_admin_can_view_reports() {
	return function_exists( 'current_user_can' )
		&& ( current_user_can( 'view_despace_reports' ) || current_user_can( 'manage_options' ) );
}

function despace_store_admin_can_view_inventory() {
	return function_exists( 'current_user_can' )
		&& ( current_user_can( 'view_despace_inventory_logs' ) || current_user_can( 'manage_woocommerce' ) || current_user_can( 'manage_options' ) );
}

function despace_store_admin_can_manage_3d() {
	return function_exists( 'current_user_can' )
		&& ( current_user_can( 'manage_despace_3d' ) || current_user_can( 'manage_woocommerce' ) || current_user_can( 'manage_options' ) );
}

/**
 * Check whether the current admin request is one of the 6 DeSpace Cockpit pages.
 */
function despace_is_cockpit_admin_page( string $hook_suffix = '' ): bool {
	$cockpit_slugs = array(
		'despace-hub',
		'despace-reports',
		'despace-inventory-logs',
		'despace-3d-manager',
		'despace-contact-settings',
		'despace-seasonal',
		'despace-system',
	);

	if ( '' !== $hook_suffix ) {
		foreach ( $cockpit_slugs as $slug ) {
			if ( false !== strpos( $hook_suffix, $slug ) ) {
				return true;
			}
		}
		return false;
	}

	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
	return in_array( $page, $cockpit_slugs, true );
}

/**
 * Suppress Action Scheduler's repetitive "past-due action found" admin banner
 * via its official filter (queue health & 1-click runner are managed inside
 * DeSpace -> Hệ thống & Phân quyền).
 */
add_filter( 'action_scheduler_check_pastdue_actions', '__return_false', 99 );

/**
 * Keep the 6 DeSpace Admin Cockpit screens free from unrelated 3rd-party plugin
 * promo/nag banners, while leaving all native WordPress admin pages untouched.
 */
function despace_isolate_cockpit_notices() {
	if ( ! is_admin() || ! despace_is_cockpit_admin_page() ) {
		return;
	}

	remove_all_actions( 'admin_notices' );
	remove_all_actions( 'all_admin_notices' );
	remove_all_actions( 'user_admin_notices' );
	remove_all_actions( 'network_admin_notices' );
}
add_action( 'in_admin_header', 'despace_isolate_cockpit_notices', 999 );

/**
 * Enqueue DeSpace Atelier Admin CSS strictly on the 6 DeSpace Cockpit pages.
 * Native WordPress & WooCommerce screens remain 100% original.
 */
function despace_store_admin_assets( $hook_suffix ) {
	if ( ! despace_store_admin_can_access_hub() ) {
		return;
	}

	if ( ! despace_is_cockpit_admin_page( (string) $hook_suffix ) ) {
		return;
	}

	$css_path = __DIR__ . '/despace-store-admin.css';
	$css_url  = plugin_dir_url( __FILE__ ) . 'despace-store-admin.css';
	if ( ! file_exists( $css_path ) && defined( 'WPMU_PLUGIN_DIR' ) && file_exists( WPMU_PLUGIN_DIR . '/despace-store-admin.css' ) ) {
		$css_path = WPMU_PLUGIN_DIR . '/despace-store-admin.css';
		$css_url  = content_url( 'mu-plugins/despace-store-admin.css' );
	}

	if ( file_exists( $css_path ) ) {
		wp_enqueue_style(
			'despace-store-admin',
			$css_url,
			array(),
			(string) filemtime( $css_path )
		);
	}
}
add_action( 'admin_enqueue_scripts', 'despace_store_admin_assets' );

/* ==========================================================================
   2. WOOCOMMERCE DATA HELPERS (HPOS & STOCK)
   ========================================================================== */

function despace_store_admin_orders_url() {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
		return admin_url( 'admin.php?page=wc-orders' );
	}

	return admin_url( 'edit.php?post_type=shop_order' );
}

function despace_store_admin_orders_status_url( $status ) {
	$status = sanitize_key( $status );

	if ( ! in_array( $status, array( 'pending', 'processing', 'on-hold', 'completed' ), true ) ) {
		return despace_store_admin_orders_url();
	}

	if ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
		return admin_url( 'admin.php?page=wc-orders&status=wc-' . rawurlencode( $status ) );
	}

	return admin_url( 'edit.php?post_type=shop_order&post_status=wc-' . rawurlencode( $status ) );
}

function despace_store_admin_low_stock_products( $limit = 0 ) {
	global $wpdb;

	if ( empty( $wpdb->wc_product_meta_lookup ) ) {
		return array();
	}

	$threshold = absint( max( get_option( 'woocommerce_notify_low_stock_amount', 2 ), 1 ) );
	$no_stock  = absint( max( get_option( 'woocommerce_notify_no_stock_amount', 0 ), 0 ) );
	$limit     = absint( $limit );
	$limit_sql = $limit ? $wpdb->prepare( ' LIMIT %d', $limit ) : '';

	return $wpdb->get_results(
		$wpdb->prepare(
			"SELECT posts.ID, lookup.stock_quantity
			FROM {$wpdb->wc_product_meta_lookup} AS lookup
			INNER JOIN {$wpdb->posts} AS posts ON lookup.product_id = posts.ID
			LEFT JOIN {$wpdb->postmeta} AS low_stock ON posts.ID = low_stock.post_id AND low_stock.meta_key = '_low_stock_amount'
			WHERE posts.post_type IN ('product', 'product_variation')
			AND posts.post_status = 'publish'
			AND lookup.stock_quantity IS NOT NULL
			AND lookup.stock_status = 'instock'
			AND lookup.stock_quantity > %d
			AND (
				(low_stock.meta_value > '' AND lookup.stock_quantity <= CAST(low_stock.meta_value AS SIGNED))
				OR ((low_stock.meta_value IS NULL OR low_stock.meta_value <= '') AND lookup.stock_quantity <= %d)
			)
			ORDER BY lookup.stock_quantity ASC, posts.post_title ASC{$limit_sql}",
			$no_stock,
			$threshold
		),
		ARRAY_A
	);
}

function despace_store_admin_low_stock_count() {
	global $wpdb;

	if ( empty( $wpdb->wc_product_meta_lookup ) ) {
		return 0;
	}

	$threshold = absint( max( get_option( 'woocommerce_notify_low_stock_amount', 2 ), 1 ) );
	$no_stock  = absint( max( get_option( 'woocommerce_notify_no_stock_amount', 0 ), 0 ) );
	$count     = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(DISTINCT posts.ID)
			FROM {$wpdb->wc_product_meta_lookup} AS lookup
			INNER JOIN {$wpdb->posts} AS posts ON lookup.product_id = posts.ID
			LEFT JOIN {$wpdb->postmeta} AS low_stock ON posts.ID = low_stock.post_id AND low_stock.meta_key = '_low_stock_amount'
			WHERE posts.post_type IN ('product', 'product_variation')
			AND posts.post_status = 'publish'
			AND lookup.stock_quantity IS NOT NULL
			AND lookup.stock_status = 'instock'
			AND lookup.stock_quantity > %d
			AND (
				(low_stock.meta_value > '' AND lookup.stock_quantity <= CAST(low_stock.meta_value AS SIGNED))
				OR ((low_stock.meta_value IS NULL OR low_stock.meta_value <= '') AND lookup.stock_quantity <= %d)
			)",
			$no_stock,
			$threshold
		)
	);

	return absint( $count );
}

function despace_admin_get_inquiry_count() {
	$inquiries = get_option( 'despace_contact_inquiries', array() );
	return is_array( $inquiries ) ? count( $inquiries ) : 0;
}

/* ==========================================================================
   3. UNIFIED DESPACE SIDEBAR MENU (6 MODULES) & LEGACY REDIRECTS
   ========================================================================== */

function despace_register_admin_hub_menu() {
	$hub_cap = despace_store_admin_hub_capability();
	if ( '' === $hub_cap ) {
		return;
	}

	add_menu_page(
		'DeSpace — Trung Tâm Điều Hành',
		'DeSpace',
		$hub_cap,
		'despace-hub',
		'despace_render_hub_overview_page',
		'dashicons-store',
		3
	);

	// 1. Tổng quan điều hành
	add_submenu_page(
		'despace-hub',
		'DeSpace — Tổng quan điều hành',
		'Tổng quan điều hành',
		$hub_cap,
		'despace-hub',
		'despace_render_hub_overview_page'
	);

	// 2. Báo cáo Doanh thu & KPI (M5)
	if ( class_exists( '\DeSpace\Reports\Admin\DashboardPage' ) ) {
		$reports_cap = current_user_can( 'view_despace_reports' ) ? 'view_despace_reports' : 'manage_options';
		add_submenu_page(
			'despace-hub',
			'DeSpace — Báo cáo Doanh thu & KPI',
			'Báo cáo Doanh thu',
			$reports_cap,
			'despace-reports',
			array( '\DeSpace\Reports\Admin\DashboardPage', 'render_page' )
		);
	}

	// 3. Nhật ký & Kiểm toán Kho (M4)
	if ( class_exists( '\DeSpace\Inventory\Admin\InventoryLogsPage' ) ) {
		$inv_cap  = current_user_can( 'view_despace_inventory_logs' ) ? 'view_despace_inventory_logs' : 'manage_woocommerce';
		$inv_hook = add_submenu_page(
			'despace-hub',
			'DeSpace — Nhật ký & Kiểm toán Kho',
			'Nhật ký Kho',
			$inv_cap,
			'despace-inventory-logs',
			array( '\DeSpace\Inventory\Admin\InventoryLogsPage', 'render_page' )
		);
		if ( $inv_hook ) {
			add_filter( "manage_{$inv_hook}_columns", array( '\DeSpace\Inventory\Admin\InventoryLogsPage', 'get_table_columns' ) );
		}
	}

	// 4. Xưởng 3D & AR Studio
	if ( function_exists( 'axgnud_render_3d_manager_page' ) ) {
		$cap_3d = current_user_can( 'manage_despace_3d' ) ? 'manage_despace_3d' : 'manage_woocommerce';
		add_submenu_page(
			'despace-hub',
			'DeSpace — Xưởng 3D & AR Studio',
			'Xưởng 3D & AR Studio',
			$cap_3d,
			'despace-3d-manager',
			'axgnud_render_3d_manager_page'
		);
	}

	// 5. Tư vấn & Showroom
	if ( function_exists( 'despace_render_contact_admin_page' ) ) {
		$lead_count   = despace_admin_get_inquiry_count();
		$lead_badge   = $lead_count > 0
			? ' <span class="update-plugins count-' . absint( $lead_count ) . '"><span class="plugin-count">' . absint( $lead_count ) . '</span></span>'
			: '';
		$showroom_cap = current_user_can( 'manage_woocommerce' ) ? 'manage_woocommerce' : 'manage_options';
		add_submenu_page(
			'despace-hub',
			'DeSpace — Tư vấn & Showroom',
			'Tư vấn & Showroom' . $lead_badge,
			$showroom_cap,
			'despace-contact-settings',
			'despace_render_contact_admin_page'
		);
	}

	// 6. Giao diện Mùa lễ / Noel (Administrators only)
	if ( class_exists( '\DeSpace\Seasonal\SeasonalAdminPage' ) ) {
		add_submenu_page(
			'despace-hub',
			'DeSpace — Giao diện Mùa lễ (Noel)',
			'Giao diện Mùa lễ',
			'manage_options',
			'despace-seasonal',
			array( '\DeSpace\Seasonal\SeasonalAdminPage', 'render_page' )
		);
	}

	// 7. Hệ thống & Phân quyền (Administrators only)
	add_submenu_page(
		'despace-hub',
		'DeSpace — Hệ thống & Phân quyền',
		'Hệ thống & Phân quyền',
		'manage_options',
		'despace-system',
		'despace_render_system_admin_page'
	);
}
add_action( 'admin_menu', 'despace_register_admin_hub_menu', 8 );

/**
 * Remove duplicate standalone DeSpace menu entries once consolidated under DeSpace.
 */
function despace_consolidate_duplicate_admin_menus() {
	if ( ! despace_store_admin_can_access_hub() ) {
		return;
	}

	global $admin_page_hooks, $_registered_pages;

	if ( class_exists( '\DeSpace\Reports\Admin\DashboardPage' ) ) {
		remove_menu_page( 'despace-reports' );
		remove_submenu_page( 'woocommerce', 'despace-reports' );
		unset( $admin_page_hooks['despace-reports'], $_registered_pages['toplevel_page_despace-reports'] );
	}

	if ( class_exists( '\DeSpace\Inventory\Admin\InventoryLogsPage' ) ) {
		remove_submenu_page( 'woocommerce', 'despace-inventory-logs' );
	}

	if ( function_exists( 'axgnud_render_3d_manager_page' ) ) {
		remove_menu_page( 'despace-3d-manager' );
		unset( $admin_page_hooks['despace-3d-manager'], $_registered_pages['toplevel_page_despace-3d-manager'] );
	}

	if ( function_exists( 'despace_render_contact_admin_page' ) ) {
		remove_submenu_page( 'options-general.php', 'despace-contact-settings' );
	}

	if ( function_exists( 'axgnud_3d_ai_settings_page' ) ) {
		remove_submenu_page( 'options-general.php', 'axgnud-3d-ai-settings' );
	}
}
add_action( 'admin_menu', 'despace_consolidate_duplicate_admin_menus', 999 );

/**
 * Backward-compatible redirects for legacy DeSpace admin URLs & tabs.
 */
function despace_redirect_legacy_admin_routes() {
	if ( ! is_admin() || wp_doing_ajax() ) {
		return;
	}

	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
	if ( '' === $page ) {
		return;
	}

	if ( 'despace-showroom-info' === $page && despace_store_admin_can_manage() ) {
		wp_safe_redirect( admin_url( 'admin.php?page=despace-contact-settings&tab=showroom' ) );
		exit;
	}

	if ( 'axgnud-3d-ai-settings' === $page && current_user_can( 'manage_options' ) ) {
		wp_safe_redirect( admin_url( 'admin.php?page=despace-3d-manager&tab=ai' ) );
		exit;
	}

	if ( 'despace-hub' === $page && isset( $_GET['tab'] ) && despace_store_admin_can_access_hub() ) {
		$tab = sanitize_key( wp_unslash( $_GET['tab'] ) );
		$map = array(
			'assets'    => array( 'url' => 'admin.php?page=despace-system&tab=tools', 'cap' => 'manage_options' ),
			'rbac'      => array( 'url' => 'admin.php?page=despace-system&tab=rbac', 'cap' => 'manage_options' ),
			'3d'        => array( 'url' => 'admin.php?page=despace-3d-manager', 'cap' => '' ),
			'inventory' => array( 'url' => 'admin.php?page=despace-inventory-logs', 'cap' => '' ),
			'showroom'  => array( 'url' => 'admin.php?page=despace-contact-settings&tab=showroom', 'cap' => '' ),
		);
		if ( isset( $map[ $tab ] ) ) {
			if ( '' !== $map[ $tab ]['cap'] && ! current_user_can( $map[ $tab ]['cap'] ) ) {
				wp_die( 'Bạn không có quyền truy cập mục này.', 'Truy cập bị từ chối', array( 'response' => 403 ) );
			}
			$target = admin_url( $map[ $tab ]['url'] );
			if ( isset( $_GET['unzipped'] ) ) {
				$target = add_query_arg( 'unzipped', sanitize_key( wp_unslash( $_GET['unzipped'] ) ), $target );
			}
			if ( isset( $_GET['repaired'] ) ) {
				$target = add_query_arg( 'repaired', absint( $_GET['repaired'] ), $target );
			}
			wp_safe_redirect( $target );
			exit;
		}
	}
}
add_action( 'admin_menu', 'despace_redirect_legacy_admin_routes', 1000 );

/* ==========================================================================
   4. SHARED SVG ICONS & COCKPIT HEADER COMPONENT
   ========================================================================== */

function despace_admin_svg_icon( string $name ): string {
	$icons = array(
		'overview'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/></svg>',
		'reports'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 3v18h18"/><path d="M7 15l4-4 3 3 6-7"/></svg>',
		'inventory' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-4"/></svg>',
		'studio3d'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2l9 5v10l-9 5-9-5V7l9-5z"/><path d="M12 22V12"/><path d="M21 7l-9 5-9-5"/></svg>',
		'showroom'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>',
		'seasonal'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="12" y1="2" x2="12" y2="22"/><line x1="2" y1="12" x2="22" y2="12"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/><line x1="19.07" y1="4.93" x2="4.93" y2="19.07"/></svg>',
		'system'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>',
		'revenue'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>',
		'orders'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>',
		'plus'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>',
	);

	return $icons[ $name ] ?? '';
}

function despace_render_admin_cockpit_header( string $active_slug, string $page_title, string $page_subtitle = '', array $actions = array() ): void {
	$logo_url     = content_url( '/themes/despace-child/assets/images/despace-logo-header.png' );
	$lead_count   = despace_admin_get_inquiry_count();
	$env_type     = function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production';
	$current_user = wp_get_current_user();
	$role_label   = 'Quản trị viên';
	if ( $current_user instanceof WP_User && ! empty( $current_user->roles ) ) {
		$first_role = reset( $current_user->roles );
		$role_names = array(
			'administrator' => 'Administrator',
			'shop_manager'  => 'Shop Manager',
			'despace_staff' => 'DeSpace Staff',
		);
		$role_label = $role_names[ $first_role ] ?? ucfirst( (string) $first_role );
	}

	$nav_items = array(
		array(
			'slug'    => 'despace-hub',
			'label'   => 'Tổng quan',
			'icon'    => 'overview',
			'url'     => admin_url( 'admin.php?page=despace-hub' ),
			'visible' => despace_store_admin_can_access_hub(),
			'badge'   => 0,
		),
		array(
			'slug'    => 'despace-reports',
			'label'   => 'Báo cáo Doanh thu',
			'icon'    => 'reports',
			'url'     => admin_url( 'admin.php?page=despace-reports' ),
			'visible' => class_exists( '\DeSpace\Reports\Admin\DashboardPage' ) && despace_store_admin_can_view_reports(),
			'badge'   => 0,
		),
		array(
			'slug'    => 'despace-inventory-logs',
			'label'   => 'Nhật ký Kho',
			'icon'    => 'inventory',
			'url'     => admin_url( 'admin.php?page=despace-inventory-logs' ),
			'visible' => class_exists( '\DeSpace\Inventory\Admin\InventoryLogsPage' ) && despace_store_admin_can_view_inventory(),
			'badge'   => 0,
		),
		array(
			'slug'    => 'despace-3d-manager',
			'label'   => 'Xưởng 3D & AR',
			'icon'    => 'studio3d',
			'url'     => admin_url( 'admin.php?page=despace-3d-manager' ),
			'visible' => function_exists( 'axgnud_render_3d_manager_page' ) && despace_store_admin_can_manage_3d(),
			'badge'   => 0,
		),
		array(
			'slug'    => 'despace-contact-settings',
			'label'   => 'Tư vấn & Showroom',
			'icon'    => 'showroom',
			'url'     => admin_url( 'admin.php?page=despace-contact-settings' ),
			'visible' => function_exists( 'despace_render_contact_admin_page' ) && despace_store_admin_can_manage(),
			'badge'   => $lead_count,
		),
		array(
			'slug'    => 'despace-seasonal',
			'label'   => 'Giao diện Mùa lễ',
			'icon'    => 'seasonal',
			'url'     => admin_url( 'admin.php?page=despace-seasonal' ),
			'visible' => class_exists( '\DeSpace\Seasonal\SeasonalAdminPage' ) && current_user_can( 'manage_options' ),
			'badge'   => 0,
		),
		array(
			'slug'    => 'despace-system',
			'label'   => 'Hệ thống & Phân quyền',
			'icon'    => 'system',
			'url'     => admin_url( 'admin.php?page=despace-system' ),
			'visible' => current_user_can( 'manage_options' ),
			'badge'   => 0,
		),
	);

	if ( empty( $actions ) ) {
		if ( despace_store_admin_can_manage() ) {
			$actions[] = array(
				'label' => '+ Thêm sản phẩm',
				'url'   => admin_url( 'post-new.php?post_type=product' ),
				'style' => 'primary',
			);
			$actions[] = array(
				'label' => 'Đơn hàng (HPOS)',
				'url'   => despace_store_admin_orders_url(),
				'style' => 'ghost',
			);
		}
		$actions[] = array(
			'label'  => 'Xem cửa hàng ↗',
			'url'    => home_url( '/' ),
			'style'  => 'ghost',
			'target' => '_blank',
		);
	}
	?>
	<header class="ds-cockpit-header" role="banner">
		<div class="ds-cockpit-banner">
			<div class="ds-cockpit-banner__left">
				<div class="ds-cockpit-brand-row">
					<img src="<?php echo esc_url( $logo_url ); ?>" alt="DeSpace" class="ds-cockpit-logo" />
					<span class="ds-cockpit-divider" aria-hidden="true"></span>
					<span class="ds-cockpit-badge">Cockpit v4.0</span>
					<span class="ds-cockpit-badge ds-cockpit-badge--live"><span class="ds-live-dot" aria-hidden="true"></span> Cửa hàng trực tuyến</span>
					<?php if ( 'local' === $env_type ) : ?>
						<span class="ds-cockpit-badge">Local Docker</span>
					<?php endif; ?>
				</div>
				<h1><?php echo esc_html( $page_title ); ?></h1>
				<?php if ( '' !== $page_subtitle ) : ?>
					<p class="ds-cockpit-subtitle"><?php echo esc_html( $page_subtitle ); ?></p>
				<?php endif; ?>
				<div class="ds-cockpit-meta-line">
					<span><?php echo esc_html( date_i18n( 'l, d/m/Y' ) ); ?></span>
					<span aria-hidden="true">·</span>
					<span>Tài khoản: <strong><?php echo esc_html( $current_user->display_name ?: $current_user->user_login ); ?></strong> (<?php echo esc_html( $role_label ); ?>)</span>
				</div>
			</div>

			<div class="ds-cockpit-actions">
				<button type="button" class="ds-cmd-trigger" id="ds-open-cmd" aria-label="Mở bảng điều hướng nhanh">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
					<span>Tìm nhanh...</span>
					<kbd class="ds-kbd">⌘K</kbd>
				</button>
				<?php foreach ( $actions as $action ) : ?>
					<?php
					$style_class = 'ds-btn--ghost';
					if ( isset( $action['style'] ) && 'primary' === $action['style'] ) {
						$style_class = 'ds-btn--primary';
					} elseif ( isset( $action['style'] ) && 'dark' === $action['style'] ) {
						$style_class = 'ds-btn--dark';
					}
					$btn_id      = ! empty( $action['id'] ) ? ' id="' . esc_attr( $action['id'] ) . '"' : '';
					$onclick     = ! empty( $action['onclick'] ) ? ' onclick="' . esc_attr( $action['onclick'] ) . '"' : '';
					$btn_type    = $action['type'] ?? 'link';
					if ( 'button' === $btn_type ) :
						?>
						<button type="button"<?php echo $btn_id . $onclick; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> class="ds-btn <?php echo esc_attr( $style_class ); ?>">
							<?php echo esc_html( $action['label'] ?? '' ); ?>
						</button>
					<?php else : ?>
						<?php
						$target_attr = ! empty( $action['target'] ) ? ' target="' . esc_attr( $action['target'] ) . '" rel="noopener noreferrer"' : '';
						?>
						<a href="<?php echo esc_url( $action['url'] ?? '#' ); ?>"<?php echo $btn_id . $target_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> class="ds-btn <?php echo esc_attr( $style_class ); ?>">
							<?php echo esc_html( $action['label'] ?? '' ); ?>
						</a>
					<?php endif; ?>
				<?php endforeach; ?>
			</div>
		</div>

		<nav class="ds-cockpit-nav" aria-label="Điều hướng trung tâm quản trị DeSpace">
			<?php foreach ( $nav_items as $item ) : ?>
				<?php
				if ( empty( $item['visible'] ) ) {
					continue;
				}
				$is_active = ( $item['slug'] === $active_slug );
				?>
				<a href="<?php echo esc_url( $item['url'] ); ?>" class="ds-cockpit-nav__item <?php echo $is_active ? 'is-active' : ''; ?>"<?php echo $is_active ? ' aria-current="page"' : ''; ?>>
					<span class="ds-cockpit-nav__icon"><?php echo despace_admin_svg_icon( $item['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<span><?php echo esc_html( $item['label'] ); ?></span>
					<?php if ( ! empty( $item['badge'] ) ) : ?>
						<span class="ds-cockpit-nav__badge"><?php echo esc_html( number_format_i18n( (int) $item['badge'] ) ); ?></span>
					<?php endif; ?>
				</a>
			<?php endforeach; ?>
		</nav>
	</header>

	<?php
	// Build capability-gated Command Palette items (⌘K / Ctrl+K).
	$cmd_items = array();
	foreach ( $nav_items as $ni ) {
		if ( ! empty( $ni['visible'] ) ) {
			$cmd_items[] = array( 'label' => $ni['label'], 'group' => 'DeSpace Cockpit', 'url' => $ni['url'] );
		}
	}
	if ( despace_store_admin_can_manage() ) {
		$cmd_items[] = array( 'label' => 'Thêm sản phẩm nội thất mới', 'group' => 'Thao tác nhanh', 'url' => admin_url( 'post-new.php?post_type=product' ) );
		$cmd_items[] = array( 'label' => 'Quản lý Đơn hàng (WooCommerce HPOS)', 'group' => 'Thao tác nhanh', 'url' => despace_store_admin_orders_url() );
		$cmd_items[] = array( 'label' => 'Đơn hàng chờ đối soát VietQR (On-hold)', 'group' => 'Bộ lọc nhanh', 'url' => despace_store_admin_orders_status_url( 'on-hold' ) );
		$cmd_items[] = array( 'label' => 'Đơn hàng đang chờ giao (Processing)', 'group' => 'Bộ lọc nhanh', 'url' => despace_store_admin_orders_status_url( 'processing' ) );
		$cmd_items[] = array( 'label' => 'Cấu hình Thông tin Showroom', 'group' => 'Tư vấn & Showroom', 'url' => admin_url( 'admin.php?page=despace-contact-settings&tab=showroom' ) );
	}
	if ( despace_store_admin_can_manage_3d() ) {
		$cmd_items[] = array( 'label' => 'Sản phẩm chưa có mô hình 3D (Missing)', 'group' => 'Bộ lọc nhanh', 'url' => admin_url( 'admin.php?page=despace-3d-manager&f_status=missing' ) );
	}
	if ( current_user_can( 'manage_options' ) ) {
		$cmd_items[] = array( 'label' => 'Kết nối Desktop 3D Studio & Khóa API', 'group' => 'Xưởng 3D & AR', 'url' => admin_url( 'admin.php?page=despace-3d-manager&tab=desktop' ) );
		$cmd_items[] = array( 'label' => 'Cài đặt AI Concierge & Cloud 3D', 'group' => 'Xưởng 3D & AR', 'url' => admin_url( 'admin.php?page=despace-3d-manager&tab=ai' ) );
		$cmd_items[] = array( 'label' => 'Ma trận Phân quyền (Live RBAC)', 'group' => 'Hệ thống', 'url' => admin_url( 'admin.php?page=despace-system&tab=rbac' ) );
		$cmd_items[] = array( 'label' => 'Công cụ Tài sản & Hàng đợi Action Scheduler', 'group' => 'Hệ thống', 'url' => admin_url( 'admin.php?page=despace-system&tab=tools' ) );
	}
	$cmd_items[] = array( 'label' => 'Mở cửa hàng Storefront', 'group' => 'Website', 'url' => home_url( '/' ) );
	?>
	<div id="ds-toast-stack" class="ds-toast-stack" aria-live="polite" aria-atomic="true"></div>
	<button type="button" id="ds-scroll-top" class="ds-scroll-top" aria-label="Lên đầu trang" title="Lên đầu trang">
		<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="18 15 12 9 6 15"/></svg>
	</button>

	<div id="ds-cmd-overlay" class="ds-cmd-overlay" role="dialog" aria-modal="true" aria-label="Điều hướng nhanh DeSpace">
		<div class="ds-cmd-dialog">
			<div class="ds-cmd-search">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
				<input type="text" id="ds-cmd-input" placeholder="Nhập tên màn hình hoặc thao tác (VD: VietQR, Báo cáo, 3D, Showroom, Kho)..." autocomplete="off" />
				<button type="button" id="ds-cmd-close" style="background:none; border:none; color:#64748b; cursor:pointer; font-size:12px; font-weight:700;">ESC</button>
			</div>
			<div class="ds-cmd-list" id="ds-cmd-list">
				<?php foreach ( $cmd_items as $idx => $ci ) : ?>
					<a href="<?php echo esc_url( $ci['url'] ); ?>" class="ds-cmd-item<?php echo 0 === $idx ? ' is-selected' : ''; ?>" data-search="<?php echo esc_attr( mb_strtolower( $ci['label'] . ' ' . $ci['group'] ) ); ?>">
						<span><?php echo esc_html( $ci['label'] ); ?></span>
						<small><?php echo esc_html( $ci['group'] ); ?></small>
					</a>
				<?php endforeach; ?>
			</div>
			<div class="ds-cmd-footer">
				<span>↑↓ Di chuyển · Enter để mở · ⌘K Bật/tắt</span>
				<span>DeSpace Command Palette</span>
			</div>
		</div>
	</div>
	<script>
	(function(){
		if (window.__dsCmdInit) return;
		window.__dsCmdInit = true;

		// 1. Non-blocking Toast Notification System
		window.dsToast = function(message, type) {
			var stack = document.getElementById('ds-toast-stack');
			if (!stack) return;
			var el = document.createElement('div');
			el.className = 'ds-toast ds-toast--' + (type || 'info');
			var span = document.createElement('span');
			span.textContent = String(message || '');
			var close = document.createElement('button');
			close.type = 'button';
			close.className = 'ds-toast__close';
			close.setAttribute('aria-label', 'Đóng thông báo');
			close.textContent = '×';
			close.addEventListener('click', function(){ el.remove(); });
			el.appendChild(span);
			el.appendChild(close);
			stack.appendChild(el);
			setTimeout(function(){
				if (el.parentNode) el.remove();
			}, 3600);
		};

		// 2. Reusable 1-Click Copy Helper with auto-resetting label + Toast
		window.dsCopyText = function(text, label, btnEl) {
			if (!text) return;
			var done = function() {
				window.dsToast('Đã sao chép ' + (label || 'nội dung'), 'success');
				if (btnEl) {
					var orig = btnEl.getAttribute('data-orig-label') || btnEl.textContent;
					btnEl.setAttribute('data-orig-label', orig);
					btnEl.textContent = '✓ Đã sao chép';
					setTimeout(function(){ btnEl.textContent = orig; }, 1800);
				}
			};
			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(text).then(done).catch(done);
			} else {
				var ta = document.createElement('textarea');
				ta.value = text;
				document.body.appendChild(ta);
				ta.select();
				try { document.execCommand('copy'); } catch (e) {}
				ta.remove();
				done();
			}
		};

		// 3. Smooth Back-to-Top Floating Button
		var topBtn = document.getElementById('ds-scroll-top');
		if (topBtn) {
			window.addEventListener('scroll', function(){
				topBtn.classList.toggle('is-visible', window.scrollY > 360);
			}, { passive: true });
			topBtn.addEventListener('click', function(){
				window.scrollTo({ top: 0, behavior: 'smooth' });
			});
		}

		// 4. Command Palette (⌘K / Ctrl+K)
		var overlay = document.getElementById('ds-cmd-overlay');
		var openBtn = document.getElementById('ds-open-cmd');
		var closeBtn = document.getElementById('ds-cmd-close');
		var input = document.getElementById('ds-cmd-input');
		var list = document.getElementById('ds-cmd-list');
		if (!overlay || !input || !list) return;
		var items = Array.prototype.slice.call(list.querySelectorAll('.ds-cmd-item'));
		var visible = items.slice();
		var activeIdx = 0;

		function renderSel() {
			items.forEach(function(el){ el.classList.remove('is-selected'); });
			if (visible[activeIdx]) {
				visible[activeIdx].classList.add('is-selected');
				visible[activeIdx].scrollIntoView({ block: 'nearest' });
			}
		}
		function openCmd() {
			overlay.classList.add('is-open');
			input.value = '';
			visible = items.slice();
			items.forEach(function(el){ el.style.display = 'flex'; });
			activeIdx = 0;
			renderSel();
			setTimeout(function(){ input.focus(); }, 20);
		}
		function closeCmd() { overlay.classList.remove('is-open'); }

		if (openBtn) openBtn.addEventListener('click', openCmd);
		if (closeBtn) closeBtn.addEventListener('click', closeCmd);
		overlay.addEventListener('click', function(e){ if (e.target === overlay) closeCmd(); });

		document.addEventListener('keydown', function(e){
			if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
				e.preventDefault();
				overlay.classList.contains('is-open') ? closeCmd() : openCmd();
				return;
			}
			if (!overlay.classList.contains('is-open')) return;
			if (e.key === 'Escape') { closeCmd(); }
			else if (e.key === 'ArrowDown') { e.preventDefault(); activeIdx = Math.min(visible.length - 1, activeIdx + 1); renderSel(); }
			else if (e.key === 'ArrowUp') { e.preventDefault(); activeIdx = Math.max(0, activeIdx - 1); renderSel(); }
			else if (e.key === 'Enter' && visible[activeIdx]) { e.preventDefault(); window.location.href = visible[activeIdx].href; }
		});

		input.addEventListener('input', function(){
			var q = input.value.trim().toLowerCase();
			visible = [];
			items.forEach(function(el){
				var match = !q || (el.getAttribute('data-search') || '').indexOf(q) !== -1;
				el.style.display = match ? 'flex' : 'none';
				if (match) visible.push(el);
			});
			activeIdx = 0;
			renderSel();
		});
	})();
	</script>
	<?php
}

/* ==========================================================================
   5. ADMIN POST HANDLERS (ZIP UNPACK, URL REPAIR, KEY ROTATION, QUEUE DRAIN)
   ========================================================================== */

function despace_safe_extract_zip_archive( ZipArchive $zip, string $target_dir ): int {
	$allowed_exts   = array( 'jpg', 'jpeg', 'png', 'webp', 'gif', 'glb', 'gltf', 'usdz', 'json', 'csv', 'txt' );
	$max_files      = 500;
	$max_file_bytes = 25 * 1024 * 1024;
	$max_total      = 200 * 1024 * 1024;
	$total_bytes    = 0;
	$extracted      = 0;
	$target_base    = rtrim( wp_normalize_path( $target_dir ), '/' );

	for ( $i = 0; $i < $zip->numFiles && $extracted < $max_files; $i++ ) {
		$entry_name = $zip->getNameIndex( $i );
		if ( false === $entry_name ) {
			continue;
		}

		$normalized = ltrim( wp_normalize_path( $entry_name ), '/' );
		if (
			'' === $normalized ||
			str_contains( $normalized, '../' ) ||
			str_starts_with( $normalized, '..' ) ||
			str_starts_with( $normalized, '__MACOSX/' ) ||
			str_contains( $normalized, '/.' ) ||
			str_starts_with( $normalized, '.' )
		) {
			continue;
		}

		if ( str_ends_with( $normalized, '/' ) ) {
			wp_mkdir_p( $target_base . '/' . rtrim( $normalized, '/' ) );
			continue;
		}

		$ext = strtolower( pathinfo( $normalized, PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, $allowed_exts, true ) ) {
			continue;
		}

		$stat = $zip->statIndex( $i );
		if ( is_array( $stat ) && (int) ( $stat['size'] ?? 0 ) > $max_file_bytes ) {
			continue;
		}

		$dest_path = $target_base . '/' . $normalized;
		$dest_dir  = dirname( $dest_path );
		if ( ! is_dir( $dest_dir ) ) {
			wp_mkdir_p( $dest_dir );
		}

		$stream = $zip->getStream( $entry_name );
		if ( $stream ) {
			$out = fopen( $dest_path, 'wb' );
			if ( $out ) {
				$written = 0;
				$aborted = false;
				while ( ! feof( $stream ) ) {
					$chunk = fread( $stream, 65536 );
					if ( false === $chunk ) {
						$aborted = true;
						break;
					}
					$len          = strlen( $chunk );
					$written     += $len;
					$total_bytes += $len;
					if ( $written > $max_file_bytes || $total_bytes > $max_total ) {
						$aborted = true;
						break;
					}
					fwrite( $out, $chunk );
				}
				fclose( $out );
				if ( $aborted ) {
					@unlink( $dest_path );
				} else {
					$extracted++;
				}
			}
			fclose( $stream );
			if ( $total_bytes > $max_total ) {
				break;
			}
		}
	}

	return $extracted;
}

add_action( 'admin_post_despace_hub_unzip_uploads', 'despace_handle_hub_unzip_uploads' );
function despace_handle_hub_unzip_uploads() {
	check_admin_referer( 'despace_hub_unzip_nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Bạn không có quyền thực hiện thao tác này.', 'Truy cập bị từ chối', array( 'response' => 403 ) );
	}

	$target_dir = WP_CONTENT_DIR . '/uploads';
	if ( ! is_dir( $target_dir ) ) {
		wp_mkdir_p( $target_dir );
	}

	if ( ! empty( $_FILES['upload_zip_file']['tmp_name'] ) && is_uploaded_file( $_FILES['upload_zip_file']['tmp_name'] ) ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			wp_die( 'Máy chủ không hỗ trợ tiện ích PHP ZipArchive.' );
		}
		$zip = new ZipArchive();
		if ( true === $zip->open( $_FILES['upload_zip_file']['tmp_name'] ) ) {
			despace_safe_extract_zip_archive( $zip, $target_dir );
			$zip->close();
			wp_safe_redirect( admin_url( 'admin.php?page=despace-system&tab=tools&unzipped=1' ) );
			exit;
		}
		wp_die( 'Không thể giải nén tệp zip vừa tải lên.' );
	}

	$candidates = array(
		dirname( ABSPATH ) . '/uploads.zip',
		ABSPATH . 'uploads.zip',
		WP_CONTENT_DIR . '/uploads.zip',
		$target_dir . '/uploads.zip',
		dirname( get_stylesheet_directory(), 4 ) . '/uploads.zip',
	);

	$found_zip = null;
	foreach ( $candidates as $candidate ) {
		if ( file_exists( $candidate ) && filesize( $candidate ) > 1000 ) {
			$found_zip = $candidate;
			break;
		}
	}

	if ( ! $found_zip ) {
		wp_die( 'Không tìm thấy tệp uploads.zip trên hệ thống máy chủ.' );
	}
	if ( ! class_exists( 'ZipArchive' ) ) {
		wp_die( 'Máy chủ không hỗ trợ tiện ích ZipArchive của PHP.' );
	}

	$zip = new ZipArchive();
	if ( true === $zip->open( $found_zip ) ) {
		despace_safe_extract_zip_archive( $zip, $target_dir );
		$zip->close();
		wp_safe_redirect( admin_url( 'admin.php?page=despace-system&tab=tools&unzipped=1' ) );
		exit;
	}
	wp_die( 'Lỗi khi mở tệp ZIP: ' . esc_html( $found_zip ) );
}

add_action( 'admin_post_despace_hub_repair_urls', 'despace_handle_hub_repair_urls' );
function despace_handle_hub_repair_urls() {
	check_admin_referer( 'despace_hub_repair_nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Bạn không có quyền thực hiện thao tác này.', 'Truy cập bị từ chối', array( 'response' => 403 ) );
	}

	global $wpdb;
	$current_home = home_url();
	$old_hosts    = array(
		'http://localhost:8088',
		'http://localhost:8090',
		'http://localhost:10016',
		'http://localhost:8080',
		'https://axgnud-luxury.infinityfree.io',
	);

	$updated_rows = 0;
	foreach ( $old_hosts as $old ) {
		if ( $old === $current_home ) {
			continue;
		}

		$r1 = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->posts} SET post_content = REPLACE(post_content, %s, %s) WHERE post_content LIKE %s",
				$old,
				$current_home,
				'%' . $wpdb->esc_like( $old ) . '%'
			)
		);
		$r2 = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->posts} SET guid = REPLACE(guid, %s, %s) WHERE guid LIKE %s",
				$old,
				$current_home,
				'%' . $wpdb->esc_like( $old ) . '%'
			)
		);
		$r3 = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->postmeta} SET meta_value = REPLACE(meta_value, %s, %s) WHERE meta_value LIKE %s AND meta_value NOT REGEXP '^(a|O|s):[0-9]+:'",
				$old,
				$current_home,
				'%' . $wpdb->esc_like( $old ) . '%'
			)
		);

		if ( $r1 ) {
			$updated_rows += (int) $r1;
		}
		if ( $r2 ) {
			$updated_rows += (int) $r2;
		}
		if ( $r3 ) {
			$updated_rows += (int) $r3;
		}
	}

	wp_cache_flush();
	$transient_like = '%' . $wpdb->esc_like( '_transient_' ) . '%';
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $transient_like ) );

	wp_safe_redirect( admin_url( 'admin.php?page=despace-system&tab=tools&repaired=' . intval( $updated_rows ) ) );
	exit;
}

add_action( 'admin_post_despace_rotate_desktop_key', 'despace_handle_rotate_desktop_key' );
function despace_handle_rotate_desktop_key() {
	check_admin_referer( 'despace_rotate_desktop_key_nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Bạn không có quyền xoay khóa kết nối Desktop 3D Studio.', 'Truy cập bị từ chối', array( 'response' => 403 ) );
	}

	try {
		$new_key = bin2hex( random_bytes( 32 ) );
	} catch ( Exception $e ) {
		$new_key = hash( 'sha256', wp_generate_password( 64, true, true ) . microtime( true ) );
	}

	update_option( 'despace_desktop_sync_key', $new_key, false );
	wp_safe_redirect( admin_url( 'admin.php?page=despace-3d-manager&tab=desktop&key_rotated=1' ) );
	exit;
}

add_action( 'admin_post_despace_run_action_scheduler', 'despace_handle_run_action_scheduler' );
function despace_handle_run_action_scheduler() {
	check_admin_referer( 'despace_run_as_nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Bạn không có quyền chạy hàng đợi Action Scheduler.', 'Truy cập bị từ chối', array( 'response' => 403 ) );
	}

	global $wpdb;
	$tbl       = $wpdb->prefix . 'actionscheduler_actions';
	$processed = 0;

	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $tbl ) ) ) === $tbl ) {
		// Cancel orphaned hooks left over from inactive third-party plugins.
		$orphans      = array( 'mailpoet/cron/daemon-trigger', 'gla/jobs/create_youtube_order_ids_cache/start', 'wc_gla_cron_daily_notes' );
		$placeholders = implode( ',', array_fill( 0, count( $orphans ), '%s' ) );
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$tbl} SET status = 'canceled' WHERE status = 'pending' AND hook IN ({$placeholders})",
				...$orphans
			)
		);

		if ( class_exists( 'ActionScheduler_QueueRunner' ) ) {
			$processed = (int) ActionScheduler_QueueRunner::instance()->run( 'DeSpace Admin Cockpit' );
		}
		delete_transient( 'action_scheduler_last_pastdue_actions_check' );
	}

	wp_safe_redirect( admin_url( 'admin.php?page=despace-system&tab=tools&queue_ran=' . absint( $processed ) ) );
	exit;
}

/* ==========================================================================
   6. SCREEN 1: TỔNG QUAN ĐIỀU HÀNH (EXECUTIVE OVERVIEW)
   ========================================================================== */

function despace_render_hub_overview_page() {
	if ( ! despace_store_admin_can_access_hub() ) {
		wp_die( 'Bạn không có quyền truy cập Trung tâm Điều hành DeSpace.', 'Truy cập bị từ chối', array( 'response' => 403 ) );
	}

	global $wpdb;

	$can_manage    = despace_store_admin_can_manage();
	$can_reports   = despace_store_admin_can_view_reports();
	$can_inventory = despace_store_admin_can_view_inventory();
	$can_3d        = despace_store_admin_can_manage_3d();
	$can_system    = current_user_can( 'manage_options' );

	$product_count    = (int) ( wp_count_posts( 'product' )->publish ?? 0 );
	$processing       = function_exists( 'wc_orders_count' ) ? (int) wc_orders_count( 'processing' ) : 0;
	$on_hold          = function_exists( 'wc_orders_count' ) ? (int) wc_orders_count( 'on-hold' ) : 0;
	$pending          = function_exists( 'wc_orders_count' ) ? (int) wc_orders_count( 'pending' ) : 0;
	$completed        = function_exists( 'wc_orders_count' ) ? (int) wc_orders_count( 'completed' ) : 0;
	$cancelled_orders = function_exists( 'wc_orders_count' ) ? (int) wc_orders_count( 'cancelled' ) : 0;
	$hold_total       = $on_hold + $pending;
	$action_orders    = $processing + $hold_total;
	$low_stock        = function_exists( 'despace_store_admin_low_stock_count' ) ? (int) despace_store_admin_low_stock_count() : 0;
	$lead_count       = despace_admin_get_inquiry_count();

	// 30-day HPOS net revenue & 7-day daily trend buckets.
	$revenue_30d      = 0.0;
	$valid_orders_30d = 0;
	$daily_7d         = array();
	for ( $d = 6; $d >= 0; $d-- ) {
		$day_key              = gmdate( 'Y-m-d', strtotime( "-{$d} days" ) );
		$daily_7d[ $day_key ] = array(
			'label'   => gmdate( 'd/m', strtotime( "-{$d} days" ) ),
			'revenue' => 0.0,
			'orders'  => 0,
		);
	}

	if ( function_exists( 'wc_get_orders' ) ) {
		$since_30d  = gmdate( 'Y-m-d', strtotime( '-30 days' ) );
		$orders_30d = wc_get_orders(
			array(
				'status'       => array( 'wc-processing', 'wc-completed' ),
				'date_created' => '>=' . $since_30d,
				'limit'        => -1,
				'return'       => 'objects',
			)
		);
		if ( is_array( $orders_30d ) ) {
			foreach ( $orders_30d as $ord ) {
				if ( $ord instanceof WC_Order ) {
					$ord_total    = (float) $ord->get_total();
					$revenue_30d += $ord_total;
					$valid_orders_30d++;
					$dc = $ord->get_date_created();
					if ( $dc ) {
						$dk = $dc->date( 'Y-m-d' );
						if ( isset( $daily_7d[ $dk ] ) ) {
							$daily_7d[ $dk ]['revenue'] += $ord_total;
							$daily_7d[ $dk ]['orders']++;
						}
					}
				}
			}
		}
	}

	$max_day_rev = 0.0;
	foreach ( $daily_7d as $bucket ) {
		if ( $bucket['revenue'] > $max_day_rev ) {
			$max_day_rev = $bucket['revenue'];
		}
	}

	// 3D & AR coverage.
	$uploads_info    = wp_upload_dir();
	$uploads_basedir = $uploads_info['basedir'];
	$glb_files       = is_dir( $uploads_basedir . '/3d' ) ? glob( $uploads_basedir . '/3d/*/model.glb' ) : array();
	$usdz_files      = is_dir( $uploads_basedir . '/3d' ) ? glob( $uploads_basedir . '/3d/*/model.usdz' ) : array();
	$glb_count       = is_array( $glb_files ) ? count( $glb_files ) : 0;
	$usdz_count      = is_array( $usdz_files ) ? count( $usdz_files ) : 0;
	$missing_3d      = max( 0, $product_count - $glb_count );

	// M4 Inventory Logs count.
	$log_table  = $wpdb->prefix . 'despace_inventory_logs';
	$has_logs   = ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $log_table ) ) ) === $log_table );
	$log_count  = $has_logs ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$log_table}" ) : 0;
	$today_str  = current_time( 'Y-m-d' );
	$logs_today = $has_logs ? (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$log_table} WHERE DATE(created_at) = %s", $today_str ) ) : 0;

	// Reviews summary.
	$review_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_type = 'review' AND comment_approved = '1'" );
	$avg_rating   = 5.0;
	if ( $review_count > 0 ) {
		$rating_sum = (float) $wpdb->get_var( "SELECT SUM(meta_value) FROM {$wpdb->commentmeta} cm JOIN {$wpdb->comments} c ON c.comment_ID = cm.comment_id WHERE c.comment_type = 'review' AND c.comment_approved = '1' AND cm.meta_key = 'rating'" );
		$avg_rating = $rating_sum > 0 ? round( $rating_sum / $review_count, 1 ) : 5.0;
	}

	// Recent 10 orders & Low-stock list.
	$recent_orders      = function_exists( 'wc_get_orders' ) ? wc_get_orders(
		array(
			'limit'   => 10,
			'orderby' => 'date',
			'order'   => 'DESC',
		)
	) : array();
	$low_stock_products = function_exists( 'wc_get_product' ) ? despace_store_admin_low_stock_products( 5 ) : array();

	$hpos_active = class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' )
		&& \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();

	$status_map = array(
		'processing' => array( 'Đang xử lý', 'ds-pill--info' ),
		'on-hold'    => array( 'Chờ đối soát', 'ds-pill--warning' ),
		'pending'    => array( 'Chờ thanh toán', 'ds-pill--warning' ),
		'completed'  => array( 'Hoàn thành', 'ds-pill--success' ),
		'cancelled'  => array( 'Đã hủy', 'ds-pill--danger' ),
		'refunded'   => array( 'Đã hoàn tiền', 'ds-pill--neutral' ),
		'failed'     => array( 'Thất bại', 'ds-pill--danger' ),
	);

	// Build smart operational attention items.
	$attention_items = array();
	if ( $hold_total > 0 && $can_manage ) {
		$attention_items[] = array(
			'label' => sprintf( '%d đơn chờ đối soát VietQR / thanh toán', $hold_total ),
			'url'   => despace_store_admin_orders_status_url( 'on-hold' ),
			'tone'  => 'warn',
		);
	}
	if ( $processing > 0 && $can_manage ) {
		$attention_items[] = array(
			'label' => sprintf( '%d đơn đang chờ xuất kho & giao hàng', $processing ),
			'url'   => despace_store_admin_orders_status_url( 'processing' ),
			'tone'  => 'info',
		);
	}
	if ( $low_stock > 0 && $can_inventory ) {
		$attention_items[] = array(
			'label' => sprintf( '%d sản phẩm sắp hết tồn kho', $low_stock ),
			'url'   => admin_url( 'admin.php?page=despace-inventory-logs' ),
			'tone'  => 'danger',
		);
	}
	if ( $missing_3d > 0 && $can_3d ) {
		$attention_items[] = array(
			'label' => sprintf( '%d sản phẩm chưa gắn mô hình 3D', $missing_3d ),
			'url'   => admin_url( 'admin.php?page=despace-3d-manager&f_status=missing' ),
			'tone'  => 'warn',
		);
	}
	if ( $lead_count > 0 && $can_manage ) {
		$attention_items[] = array(
			'label' => sprintf( '%d khách đăng ký tư vấn Showroom', $lead_count ),
			'url'   => admin_url( 'admin.php?page=despace-contact-settings&tab=leads' ),
			'tone'  => 'info',
		);
	}

	$total_orders_bar = max( 1, $completed + $processing + $hold_total + $cancelled_orders );
	$pct_completed    = round( ( $completed / $total_orders_bar ) * 100 );
	$pct_processing   = round( ( $processing / $total_orders_bar ) * 100 );
	$pct_hold         = round( ( $hold_total / $total_orders_bar ) * 100 );
	$pct_cancelled    = max( 0, 100 - $pct_completed - $pct_processing - $pct_hold );
	$denom_3d         = max( 1, $product_count );
	$glb_pct          = min( 100, round( ( $glb_count / $denom_3d ) * 100 ) );
	$usdz_pct         = min( 100, round( ( $usdz_count / $denom_3d ) * 100 ) );
	?>
	<div class="wrap ds-cockpit-wrap">
		<?php
		despace_render_admin_cockpit_header(
			'despace-hub',
			'Tổng quan điều hành DeSpace',
			'Theo dõi doanh thu 30 ngày, xu hướng 7 ngày, đơn hàng HPOS, tồn kho, tài sản 3D/AR và yêu cầu tư vấn showroom tại một bảng điều khiển duy nhất.'
		);
		?>

		<!-- Smart Action-Required Attention Strip -->
		<?php if ( ! empty( $attention_items ) ) : ?>
			<div class="ds-attention-strip" role="region" aria-label="Việc cần xử lý ưu tiên">
				<span class="ds-attention-strip__title">Cần chú ý ngay:</span>
				<div class="ds-attention-pills">
					<?php foreach ( $attention_items as $att ) : ?>
						<a href="<?php echo esc_url( $att['url'] ); ?>" class="ds-attention-chip ds-attention-chip--<?php echo esc_attr( $att['tone'] ); ?>">
							<span><?php echo esc_html( $att['label'] ); ?></span>
							<span aria-hidden="true">→</span>
						</a>
					<?php endforeach; ?>
				</div>
			</div>
		<?php else : ?>
			<div class="ds-attention-strip ds-attention-strip--clear" role="status">
				<span style="color:#166534; font-weight:600;">✓ Mọi chỉ số vận hành đều ổn định — không có đơn chờ đối soát, không có cảnh báo tồn kho.</span>
			</div>
		<?php endif; ?>

		<!-- 5 Core Live KPI Cards -->
		<section class="ds-kpi-grid" aria-label="Chỉ số điều hành chính">
			<a class="ds-kpi-card ds-kpi-card--accent" href="<?php echo esc_url( $can_reports ? admin_url( 'admin.php?page=despace-reports' ) : '#' ); ?>">
				<div class="ds-kpi-card__top">
					<span class="ds-kpi-card__label">Doanh thu 30 ngày</span>
					<span class="ds-kpi-card__icon"><?php echo despace_admin_svg_icon( 'revenue' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				</div>
				<div class="ds-kpi-card__value">
					<?php echo function_exists( 'wc_price' ) ? wp_kses_post( wc_price( $revenue_30d, array( 'decimals' => 0 ) ) ) : esc_html( number_format_i18n( $revenue_30d ) . ' ₫' ); ?>
				</div>
				<span class="ds-kpi-card__meta"><?php echo esc_html( sprintf( '%s đơn hợp lệ (HPOS)', number_format_i18n( $valid_orders_30d ) ) ); ?></span>
			</a>

			<a class="ds-kpi-card ds-kpi-card--warning" href="<?php echo esc_url( $can_manage ? despace_store_admin_orders_url() : admin_url( 'admin.php?page=despace-reports' ) ); ?>">
				<div class="ds-kpi-card__top">
					<span class="ds-kpi-card__label">Đơn hàng cần xử lý</span>
					<span class="ds-kpi-card__icon"><?php echo despace_admin_svg_icon( 'orders' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				</div>
				<div class="ds-kpi-card__value"><?php echo esc_html( number_format_i18n( $action_orders ) ); ?></div>
				<span class="ds-kpi-card__meta">
					<?php echo esc_html( sprintf( '%d đang xử lý · %d chờ đối soát · %d hoàn thành', $processing, $hold_total, $completed ) ); ?>
				</span>
			</a>

			<a class="ds-kpi-card ds-kpi-card--success" href="<?php echo esc_url( $can_3d ? admin_url( 'admin.php?page=despace-3d-manager' ) : admin_url( 'edit.php?post_type=product' ) ); ?>">
				<div class="ds-kpi-card__top">
					<span class="ds-kpi-card__label">Sản phẩm &amp; 3D/AR</span>
					<span class="ds-kpi-card__icon"><?php echo despace_admin_svg_icon( 'studio3d' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				</div>
				<div class="ds-kpi-card__value"><?php echo esc_html( number_format_i18n( $product_count ) ); ?></div>
				<span class="ds-kpi-card__meta">
					<?php
					echo esc_html( sprintf( '%d GLB · %d USDZ', $glb_count, $usdz_count ) );
					if ( $low_stock > 0 ) {
						echo esc_html( sprintf( ' · %d sắp hết kho', $low_stock ) );
					}
					?>
				</span>
			</a>

			<a class="ds-kpi-card ds-kpi-card--info" href="<?php echo esc_url( $can_inventory ? admin_url( 'admin.php?page=despace-inventory-logs' ) : '#' ); ?>">
				<div class="ds-kpi-card__top">
					<span class="ds-kpi-card__label">Kiểm toán Kho (M4)</span>
					<span class="ds-kpi-card__icon"><?php echo despace_admin_svg_icon( 'inventory' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				</div>
				<div class="ds-kpi-card__value"><?php echo esc_html( number_format_i18n( $log_count ) ); ?></div>
				<span class="ds-kpi-card__meta"><?php echo esc_html( sprintf( '+%s biến động hôm nay · Chuẩn đối soát', number_format_i18n( $logs_today ) ) ); ?></span>
			</a>

			<a class="ds-kpi-card" href="<?php echo esc_url( $can_manage ? admin_url( 'admin.php?page=despace-contact-settings&tab=leads' ) : '#' ); ?>">
				<div class="ds-kpi-card__top">
					<span class="ds-kpi-card__label">Tư vấn Showroom</span>
					<span class="ds-kpi-card__icon"><?php echo despace_admin_svg_icon( 'showroom' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				</div>
				<div class="ds-kpi-card__value"><?php echo esc_html( number_format_i18n( $lead_count ) ); ?></div>
				<span class="ds-kpi-card__meta"><?php echo esc_html( sprintf( '★ %s/5.0 từ %s đánh giá đã duyệt', number_format( $avg_rating, 1 ), number_format_i18n( $review_count ) ) ); ?></span>
			</a>
		</section>

		<!-- Bento Visual Analytics Strip: Smooth SVG Area Chart + 3D/AR Circular Rings -->
		<?php
		$daily_list = array_values( $daily_7d );
		$svg_w      = 560;
		$svg_h      = 148;
		$pad_x      = 24;
		$pad_top    = 18;
		$pad_bot    = 14;
		$plot_h     = $svg_h - $pad_top - $pad_bot;
		$step_x     = ( $svg_w - 2 * $pad_x ) / 6;
		$pts        = array();
		foreach ( $daily_list as $i => $drow ) {
			$x     = round( $pad_x + $i * $step_x, 1 );
			$ratio = $max_day_rev > 0 ? ( $drow['revenue'] / $max_day_rev ) : 0;
			$y     = round( $pad_top + $plot_h * ( 1 - $ratio ), 1 );
			$pts[] = array( 'x' => $x, 'y' => $y, 'row' => $drow );
		}
		$line_d = '';
		foreach ( $pts as $i => $pt ) {
			if ( 0 === $i ) {
				$line_d .= "M {$pt['x']} {$pt['y']}";
			} else {
				$prev   = $pts[ $i - 1 ];
				$cx     = round( ( $prev['x'] + $pt['x'] ) / 2, 1 );
				$line_d .= " C {$cx} {$prev['y']}, {$cx} {$pt['y']}, {$pt['x']} {$pt['y']}";
			}
		}
		$base_y = $svg_h - $pad_bot;
		$area_d = $line_d . " L {$pts[6]['x']} {$base_y} L {$pts[0]['x']} {$base_y} Z";
		$ring_c = 150.8; // 2 * pi * 24
		$glb_off  = round( $ring_c * ( 1 - ( $glb_pct / 100 ) ), 1 );
		$usdz_off = round( $ring_c * ( 1 - ( $usdz_pct / 100 ) ), 1 );
		?>
		<section class="ds-visual-strip" aria-label="Biểu đồ vận hành">
			<div class="ds-panel" style="margin-bottom:0;">
				<div class="ds-panel__header" style="margin-bottom:10px; padding-bottom:12px;">
					<div>
						<h2 class="ds-panel__title">Xu hướng doanh thu 7 ngày gần nhất</h2>
						<p class="ds-panel__desc">Biểu đồ đường mượt theo đơn hàng hợp lệ từ WooCommerce HPOS.</p>
					</div>
					<?php if ( $can_reports ) : ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=despace-reports' ) ); ?>" class="ds-btn ds-btn--outline ds-btn--sm">Báo cáo M5 →</a>
					<?php endif; ?>
				</div>
				<div class="ds-area-chart-wrap" id="ds-area-chart-wrap">
					<div id="ds-chart-tooltip" class="ds-chart-tooltip" aria-hidden="true"></div>
					<svg class="ds-area-chart" viewBox="0 0 <?php echo esc_attr( $svg_w ); ?> <?php echo esc_attr( $svg_h ); ?>" preserveAspectRatio="none" role="img" aria-label="Biểu đồ doanh thu 7 ngày">
						<defs>
							<linearGradient id="dsAreaGrad" x1="0" y1="0" x2="0" y2="1">
								<stop offset="0%" stop-color="#4f46e5" stop-opacity="0.28"/>
								<stop offset="100%" stop-color="#4f46e5" stop-opacity="0.01"/>
							</linearGradient>
						</defs>
						<line x1="<?php echo esc_attr( $pad_x ); ?>" y1="<?php echo esc_attr( $pad_top ); ?>" x2="<?php echo esc_attr( $svg_w - $pad_x ); ?>" y2="<?php echo esc_attr( $pad_top ); ?>" stroke="#e2e8f0" stroke-dasharray="4 4"/>
						<line x1="<?php echo esc_attr( $pad_x ); ?>" y1="<?php echo esc_attr( round( $pad_top + $plot_h / 2, 1 ) ); ?>" x2="<?php echo esc_attr( $svg_w - $pad_x ); ?>" y2="<?php echo esc_attr( round( $pad_top + $plot_h / 2, 1 ) ); ?>" stroke="#e2e8f0" stroke-dasharray="4 4"/>
						<line x1="<?php echo esc_attr( $pad_x ); ?>" y1="<?php echo esc_attr( $base_y ); ?>" x2="<?php echo esc_attr( $svg_w - $pad_x ); ?>" y2="<?php echo esc_attr( $base_y ); ?>" stroke="#e2e8f0"/>
						<path d="<?php echo esc_attr( $area_d ); ?>" fill="url(#dsAreaGrad)"/>
						<path d="<?php echo esc_attr( $line_d ); ?>" fill="none" stroke="#4f46e5" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/>
						<?php foreach ( $pts as $pt ) : ?>
							<circle class="ds-chart-dot" tabindex="0" cx="<?php echo esc_attr( $pt['x'] ); ?>" cy="<?php echo esc_attr( $pt['y'] ); ?>" r="5" fill="#ffffff" stroke="#4f46e5" stroke-width="2.5" data-tip="<?php echo esc_attr( sprintf( '%s · %s ₫ · %d đơn', $pt['row']['label'], number_format_i18n( $pt['row']['revenue'] ), $pt['row']['orders'] ) ); ?>" data-px="<?php echo esc_attr( round( ( $pt['x'] / $svg_w ) * 100, 1 ) ); ?>" data-py="<?php echo esc_attr( round( ( $pt['y'] / $svg_h ) * 100, 1 ) ); ?>" />
						<?php endforeach; ?>
					</svg>
					<div class="ds-area-chart-labels">
						<?php foreach ( $daily_list as $day_item ) : ?>
							<?php
							$short_val = $day_item['revenue'] >= 1000000
								? number_format( $day_item['revenue'] / 1000000, 1 ) . 'tr'
								: ( $day_item['revenue'] > 0 ? number_format( $day_item['revenue'] / 1000, 0 ) . 'k' : '0' );
							?>
							<div class="ds-area-chart-day">
								<span class="ds-area-chart-val"><?php echo esc_html( $short_val ); ?></span>
								<?php echo esc_html( $day_item['label'] ); ?>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
				<script>
				(function(){
					var wrap = document.getElementById('ds-area-chart-wrap');
					var tip = document.getElementById('ds-chart-tooltip');
					if (!wrap || !tip) return;
					wrap.querySelectorAll('.ds-chart-dot').forEach(function(dot){
						var show = function(){
							tip.textContent = dot.getAttribute('data-tip') || '';
							tip.style.left = (dot.getAttribute('data-px') || 50) + '%';
							tip.style.top = Math.max(12, parseFloat(dot.getAttribute('data-py') || 40) * 1.45) + 'px';
							tip.classList.add('is-visible');
						};
						var hide = function(){ tip.classList.remove('is-visible'); };
						dot.addEventListener('mouseenter', show);
						dot.addEventListener('focus', show);
						dot.addEventListener('mouseleave', hide);
						dot.addEventListener('blur', hide);
					});
				})();
				</script>
			</div>

			<div class="ds-panel" style="margin-bottom:0;">
				<div class="ds-panel__header" style="margin-bottom:14px; padding-bottom:12px;">
					<div>
						<h2 class="ds-panel__title">Độ phủ 3D/AR &amp; Trạng thái đơn</h2>
						<p class="ds-panel__desc">Chỉ số số hóa sản phẩm WebXR / Apple AR và tỷ trọng đơn HPOS.</p>
					</div>
				</div>

				<div class="ds-rings-grid">
					<div class="ds-ring-card">
						<svg class="ds-ring-svg" viewBox="0 0 60 60" aria-hidden="true">
							<circle class="ds-ring-track" cx="30" cy="30" r="24"/>
							<circle class="ds-ring-progress" cx="30" cy="30" r="24" stroke-dasharray="<?php echo esc_attr( $ring_c ); ?>" stroke-dashoffset="<?php echo esc_attr( $glb_off ); ?>"/>
						</svg>
						<div>
							<div class="ds-ring-pct"><?php echo esc_html( $glb_pct ); ?>%</div>
							<div class="ds-ring-label">Web 3D (.GLB)</div>
							<div class="ds-ring-sub"><?php echo esc_html( sprintf( '%d / %d sản phẩm', $glb_count, $product_count ) ); ?></div>
						</div>
					</div>
					<div class="ds-ring-card">
						<svg class="ds-ring-svg" viewBox="0 0 60 60" aria-hidden="true">
							<circle class="ds-ring-track" cx="30" cy="30" r="24"/>
							<circle class="ds-ring-progress ds-ring-progress--emerald" cx="30" cy="30" r="24" stroke-dasharray="<?php echo esc_attr( $ring_c ); ?>" stroke-dashoffset="<?php echo esc_attr( $usdz_off ); ?>"/>
						</svg>
						<div>
							<div class="ds-ring-pct"><?php echo esc_html( $usdz_pct ); ?>%</div>
							<div class="ds-ring-label">Apple AR (.USDZ)</div>
							<div class="ds-ring-sub"><?php echo esc_html( sprintf( '%d / %d sản phẩm', $usdz_count, $product_count ) ); ?></div>
						</div>
					</div>
				</div>

				<div class="ds-segmented-bar" role="img" aria-label="Biểu đồ tỷ lệ trạng thái đơn hàng">
					<?php if ( $completed > 0 ) : ?><div class="ds-segmented-bar__seg ds-segmented-bar__seg--completed" style="width:<?php echo esc_attr( $pct_completed ); ?>%;" title="Hoàn thành: <?php echo esc_attr( $completed ); ?>"></div><?php endif; ?>
					<?php if ( $processing > 0 ) : ?><div class="ds-segmented-bar__seg ds-segmented-bar__seg--processing" style="width:<?php echo esc_attr( $pct_processing ); ?>%;" title="Đang xử lý: <?php echo esc_attr( $processing ); ?>"></div><?php endif; ?>
					<?php if ( $hold_total > 0 ) : ?><div class="ds-segmented-bar__seg ds-segmented-bar__seg--hold" style="width:<?php echo esc_attr( $pct_hold ); ?>%;" title="Chờ đối soát: <?php echo esc_attr( $hold_total ); ?>"></div><?php endif; ?>
					<?php if ( $cancelled_orders > 0 ) : ?><div class="ds-segmented-bar__seg ds-segmented-bar__seg--cancelled" style="width:<?php echo esc_attr( $pct_cancelled ); ?>%;" title="Đã hủy: <?php echo esc_attr( $cancelled_orders ); ?>"></div><?php endif; ?>
				</div>
				<div class="ds-legend-row">
					<span class="ds-legend-item"><span class="ds-legend-dot" style="background:#10b981;"></span>Hoàn thành: <strong><?php echo esc_html( $completed ); ?></strong></span>
					<span class="ds-legend-item"><span class="ds-legend-dot" style="background:#3b82f6;"></span>Đang xử lý: <strong><?php echo esc_html( $processing ); ?></strong></span>
					<span class="ds-legend-item"><span class="ds-legend-dot" style="background:#f59e0b;"></span>Chờ đối soát: <strong><?php echo esc_html( $hold_total ); ?></strong></span>
					<span class="ds-legend-item"><span class="ds-legend-dot" style="background:#ef4444;"></span>Đã hủy: <strong><?php echo esc_html( $cancelled_orders ); ?></strong></span>
				</div>
			</div>
		</section>

		<!-- Main Operational Workspace -->
		<div class="ds-cockpit-grid">
			<div>
				<section class="ds-panel" aria-labelledby="ds-recent-orders-heading">
					<div class="ds-panel__header">
						<div>
							<h2 id="ds-recent-orders-heading" class="ds-panel__title">Đơn hàng mới nhất</h2>
							<p class="ds-panel__desc">Lọc nhanh tức thì theo trạng thái — bấm số điện thoại để sao chép nhanh.</p>
						</div>
						<div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
							<div class="ds-inline-tabs" id="ds-order-filter-tabs" role="tablist" aria-label="Lọc nhanh đơn hàng">
								<button type="button" class="ds-inline-tab is-active" data-filter="all">Tất cả</button>
								<button type="button" class="ds-inline-tab" data-filter="action">Cần xử lý</button>
								<button type="button" class="ds-inline-tab" data-filter="completed">Hoàn thành</button>
							</div>
							<?php if ( $can_manage ) : ?>
								<a href="<?php echo esc_url( despace_store_admin_orders_url() ); ?>" class="ds-btn ds-btn--outline ds-btn--sm">Tất cả →</a>
							<?php endif; ?>
						</div>
					</div>

					<?php if ( ! empty( $recent_orders ) ) : ?>
						<div class="ds-table-wrap">
							<table class="ds-table" id="ds-recent-orders-table">
								<thead>
									<tr>
										<th>Mã đơn</th>
										<th>Khách hàng</th>
										<th>Thanh toán</th>
										<th>Ngày đặt</th>
										<th>Tổng tiền</th>
										<th>Trạng thái</th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $recent_orders as $order ) : ?>
										<?php
										if ( ! ( $order instanceof WC_Order ) ) {
											continue;
										}
										$st_key       = $order->get_status();
										$st_group     = in_array( $st_key, array( 'processing', 'on-hold', 'pending' ), true ) ? 'action' : ( 'completed' === $st_key ? 'completed' : 'other' );
										$st_label     = $status_map[ $st_key ][0] ?? wc_get_order_status_name( $st_key );
										$st_class     = $status_map[ $st_key ][1] ?? 'ds-pill--neutral';
										$date_created = $order->get_date_created();
										$customer     = trim( (string) $order->get_formatted_billing_full_name() );
										$edit_url     = $can_manage ? $order->get_edit_order_url() : '';
										$pay_title    = $order->get_payment_method_title() ?: strtoupper( (string) $order->get_payment_method() );
										$cust_phone   = (string) $order->get_billing_phone();
										?>
										<tr data-status-group="<?php echo esc_attr( $st_group ); ?>">
											<td>
												<?php if ( $edit_url ) : ?>
													<a href="<?php echo esc_url( $edit_url ); ?>" style="font-weight:700; color:#4f46e5; text-decoration:none;">
														#<?php echo esc_html( $order->get_order_number() ); ?>
													</a>
												<?php else : ?>
													<strong>#<?php echo esc_html( $order->get_order_number() ); ?></strong>
												<?php endif; ?>
												<div style="font-size:12px; color:#64748b;"><?php echo esc_html( sprintf( '%d sản phẩm', $order->get_item_count() ) ); ?></div>
											</td>
											<td>
												<?php
												$cust_label = $customer ?: 'Khách lẻ';
												$words      = preg_split( '/\s+/', trim( $cust_label ) );
												$initials   = mb_substr( $words[0] ?? 'K', 0, 1 ) . mb_substr( end( $words ) ?: '', 0, 1 );
												?>
												<div class="ds-customer-cell">
													<span class="ds-avatar" aria-hidden="true"><?php echo esc_html( $initials ); ?></span>
													<div>
														<strong><?php echo esc_html( $cust_label ); ?></strong>
														<?php if ( '' !== $cust_phone ) : ?>
															<div>
																<button type="button" class="ds-copy-inline" onclick="window.dsCopyText && window.dsCopyText(<?php echo esc_attr( wp_json_encode( $cust_phone ) ); ?>, 'SĐT ' + <?php echo esc_attr( wp_json_encode( $cust_phone ) ); ?>);" title="Bấm để sao chép SĐT">
																	<?php echo esc_html( $cust_phone ); ?> ⧉
																</button>
															</div>
														<?php endif; ?>
													</div>
												</div>
											</td>
											<td><span class="ds-pill ds-pill--neutral"><?php echo esc_html( wp_trim_words( (string) $pay_title, 3, '' ) ?: '—' ); ?></span></td>
											<td><?php echo $date_created ? esc_html( date_i18n( 'd/m/Y H:i', $date_created->getTimestamp() ) ) : '—'; ?></td>
											<td><strong><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></strong></td>
											<td><span class="ds-pill <?php echo esc_attr( $st_class ); ?>"><?php echo esc_html( $st_label ); ?></span></td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
						<script>
						(function(){
							var tabs = document.getElementById('ds-order-filter-tabs');
							var tbl = document.getElementById('ds-recent-orders-table');
							if (!tabs || !tbl) return;
							tabs.querySelectorAll('.ds-inline-tab').forEach(function(btn){
								btn.addEventListener('click', function(){
									tabs.querySelectorAll('.ds-inline-tab').forEach(function(b){ b.classList.remove('is-active'); });
									btn.classList.add('is-active');
									var f = btn.getAttribute('data-filter');
									tbl.querySelectorAll('tbody tr').forEach(function(tr){
										tr.style.display = (f === 'all' || tr.getAttribute('data-status-group') === f) ? '' : 'none';
									});
								});
							});
						})();
						</script>
					<?php else : ?>
						<p style="margin:0; color:#64748b;">Chưa có đơn hàng nào trong hệ thống.</p>
					<?php endif; ?>
				</section>

				<section class="ds-panel" aria-labelledby="ds-low-stock-heading">
					<div class="ds-panel__header">
						<div>
							<h2 id="ds-low-stock-heading" class="ds-panel__title">Cảnh báo tồn kho thấp</h2>
							<p class="ds-panel__desc">Các sản phẩm đang dưới ngưỡng tồn kho WooCommerce cần nhập bổ sung.</p>
						</div>
						<?php if ( $can_inventory ) : ?>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=despace-inventory-logs' ) ); ?>" class="ds-btn ds-btn--outline ds-btn--sm">Mở Nhật ký Kho →</a>
						<?php endif; ?>
					</div>

					<?php if ( ! empty( $low_stock_products ) ) : ?>
						<div class="ds-table-wrap">
							<table class="ds-table">
								<thead>
									<tr>
										<th>Sản phẩm</th>
										<th>SKU</th>
										<th>Còn lại</th>
										<th>Ngưỡng</th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $low_stock_products as $stock_row ) : ?>
										<?php
										$prod = wc_get_product( (int) $stock_row['ID'] );
										if ( ! $prod ) {
											continue;
										}
										$edit_id   = $prod->is_type( 'variation' ) && $prod->get_parent_id() ? $prod->get_parent_id() : $prod->get_id();
										$edit_link = $can_manage ? get_edit_post_link( $edit_id, '' ) : '';
										$threshold = $prod->get_low_stock_amount();
										if ( '' === $threshold ) {
											$threshold = absint( max( get_option( 'woocommerce_notify_low_stock_amount', 2 ), 1 ) );
										}
										?>
										<tr>
											<td>
												<?php if ( $edit_link ) : ?>
													<a href="<?php echo esc_url( $edit_link ); ?>" style="font-weight:600; color:#1c1917; text-decoration:none;"><?php echo esc_html( $prod->get_name() ); ?></a>
												<?php else : ?>
													<strong><?php echo esc_html( $prod->get_name() ); ?></strong>
												<?php endif; ?>
											</td>
											<td><code><?php echo esc_html( $prod->get_sku() ?: '—' ); ?></code></td>
											<td><span class="ds-pill ds-pill--danger"><?php echo esc_html( (string) $prod->get_stock_quantity() ); ?></span></td>
											<td><?php echo esc_html( (string) $threshold ); ?></td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					<?php else : ?>
						<div style="display:flex; align-items:center; gap:12px; padding:14px 16px; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:10px; color:#166534;">
							<strong>✓ Tồn kho đang an toàn</strong> — Hiện không có sản phẩm nào xuống dưới ngưỡng cảnh báo.
						</div>
					<?php endif; ?>
				</section>
			</div>

			<div>
				<aside class="ds-panel" aria-labelledby="ds-quick-ops-heading">
					<div class="ds-panel__header">
						<div>
							<h2 id="ds-quick-ops-heading" class="ds-panel__title">Lối tắt nghiệp vụ</h2>
							<p class="ds-panel__desc">Truy cập nhanh các phân hệ quản trị cốt lõi theo quyền tài khoản.</p>
						</div>
					</div>
					<div class="ds-action-list">
						<?php if ( $can_manage ) : ?>
							<a class="ds-action-item" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=product' ) ); ?>">
								<span class="ds-action-item__lead">
									<span class="ds-action-item__icon"><?php echo despace_admin_svg_icon( 'plus' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
									<span><strong>Thêm sản phẩm nội thất</strong><small>Tạo SKU, giá bán, ảnh và biến thể gỗ</small></span>
								</span>
								<span class="ds-action-item__arrow" aria-hidden="true">→</span>
							</a>
							<a class="ds-action-item" href="<?php echo esc_url( despace_store_admin_orders_url() ); ?>">
								<span class="ds-action-item__lead">
									<span class="ds-action-item__icon"><?php echo despace_admin_svg_icon( 'orders' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
									<span><strong>Quản lý Đơn hàng (HPOS)</strong><small>Xác nhận VietQR, cập nhật trạng thái giao hàng</small></span>
								</span>
								<span class="ds-action-item__arrow" aria-hidden="true">→</span>
							</a>
						<?php endif; ?>
						<?php if ( $can_reports ) : ?>
							<a class="ds-action-item" href="<?php echo esc_url( admin_url( 'admin.php?page=despace-reports' ) ); ?>">
								<span class="ds-action-item__lead">
									<span class="ds-action-item__icon"><?php echo despace_admin_svg_icon( 'reports' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
									<span><strong>Báo cáo Doanh thu &amp; KPI</strong><small>Lọc theo ngày, AOV, Top 10 sản phẩm bán chạy</small></span>
								</span>
								<span class="ds-action-item__arrow" aria-hidden="true">→</span>
							</a>
						<?php endif; ?>
						<?php if ( $can_inventory ) : ?>
							<a class="ds-action-item" href="<?php echo esc_url( admin_url( 'admin.php?page=despace-inventory-logs' ) ); ?>">
								<span class="ds-action-item__lead">
									<span class="ds-action-item__icon"><?php echo despace_admin_svg_icon( 'inventory' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
									<span><strong>Nhật ký &amp; Kiểm toán Kho</strong><small>Truy vết biến động tồn kho &amp; xuất CSV</small></span>
								</span>
								<span class="ds-action-item__arrow" aria-hidden="true">→</span>
							</a>
						<?php endif; ?>
						<?php if ( $can_3d ) : ?>
							<a class="ds-action-item" href="<?php echo esc_url( admin_url( 'admin.php?page=despace-3d-manager' ) ); ?>">
								<span class="ds-action-item__lead">
									<span class="ds-action-item__icon"><?php echo despace_admin_svg_icon( 'studio3d' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
									<span><strong>Xưởng 3D &amp; AR Studio</strong><small>Quản lý GLB/USDZ, Desktop Sync &amp; AI 3D</small></span>
								</span>
								<span class="ds-action-item__arrow" aria-hidden="true">→</span>
							</a>
						<?php endif; ?>
						<?php if ( $can_manage ) : ?>
							<a class="ds-action-item" href="<?php echo esc_url( admin_url( 'admin.php?page=despace-contact-settings&tab=leads' ) ); ?>">
								<span class="ds-action-item__lead">
									<span class="ds-action-item__icon"><?php echo despace_admin_svg_icon( 'showroom' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
									<span><strong>Tư vấn &amp; Showroom</strong><small>Theo dõi <?php echo esc_html( number_format_i18n( $lead_count ) ); ?> yêu cầu tư vấn &amp; sửa thông tin showroom</small></span>
								</span>
								<span class="ds-action-item__arrow" aria-hidden="true">→</span>
							</a>
						<?php endif; ?>
					</div>
				</aside>

				<section class="ds-panel" aria-labelledby="ds-sys-brief-heading">
					<div class="ds-panel__header">
						<h2 id="ds-sys-brief-heading" class="ds-panel__title">Tình trạng hạ tầng</h2>
						<?php if ( $can_system ) : ?>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=despace-system&tab=health' ) ); ?>" style="color:#8c3e1f; font-weight:600; text-decoration:none; font-size:13px;">Chi tiết →</a>
						<?php endif; ?>
					</div>
					<div>
						<div class="ds-health-row">
							<span class="ds-health-row__label">PHP / Memory</span>
							<span class="ds-health-row__val"><?php echo esc_html( PHP_VERSION . ' · ' . ini_get( 'memory_limit' ) ); ?></span>
						</div>
						<div class="ds-health-row">
							<span class="ds-health-row__label">WooCommerce HPOS</span>
							<span class="ds-health-row__val">
								<span class="ds-pill <?php echo $hpos_active ? 'ds-pill--success' : 'ds-pill--warning'; ?>">
									<?php echo $hpos_active ? 'Đang bật ✓' : 'Legacy CPT'; ?>
								</span>
							</span>
						</div>
						<div class="ds-health-row">
							<span class="ds-health-row__label">Mô hình 3D &amp; AR</span>
							<span class="ds-health-row__val"><?php echo esc_html( sprintf( '%d GLB / %d USDZ', $glb_count, $usdz_count ) ); ?></span>
						</div>
						<div class="ds-health-row">
							<span class="ds-health-row__label">Môi trường</span>
							<span class="ds-health-row__val"><code><?php echo esc_html( function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production' ); ?></code></span>
						</div>
					</div>
				</section>
			</div>
		</div>
	</div>
	<?php
}

/* ==========================================================================
   7. SCREEN 6: HỆ THỐNG & PHÂN QUYỀN (HEALTH | RBAC | TOOLS)
   ========================================================================== */

function despace_render_system_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Bạn không có quyền truy cập trang Hệ thống & Phân quyền.', 'Truy cập bị từ chối', array( 'response' => 403 ) );
	}

	global $wpdb;

	$active_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'health';
	if ( ! in_array( $active_tab, array( 'health', 'rbac', 'tools' ), true ) ) {
		$active_tab = 'health';
	}

	$uploads_info    = wp_upload_dir();
	$uploads_basedir = $uploads_info['basedir'];
	$has_3d_dir      = is_dir( $uploads_basedir . '/3d' );
	$has_catalog_dir = is_dir( $uploads_basedir . '/despace-catalog' ) || is_dir( $uploads_basedir . '/furniva-real' );
	$glb_files       = $has_3d_dir ? glob( $uploads_basedir . '/3d/*/model.glb' ) : array();
	$usdz_files      = $has_3d_dir ? glob( $uploads_basedir . '/3d/*/model.usdz' ) : array();
	$glb_count       = is_array( $glb_files ) ? count( $glb_files ) : 0;
	$usdz_count      = is_array( $usdz_files ) ? count( $usdz_files ) : 0;

	$theme_dir       = get_stylesheet_directory();
	$has_mv_local    = file_exists( $theme_dir . '/assets/vendor/model-viewer/model-viewer.min.js' );
	$has_three_local = file_exists( $theme_dir . '/assets/vendor/three/three.module.min.js' );
	$hpos_active     = class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' )
		&& \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
	$has_vietqr_sec  = ( defined( 'DESPACE_VIETQR_SECRET' ) && '' !== trim( (string) DESPACE_VIETQR_SECRET ) )
		|| '' !== trim( (string) get_option( 'despace_vietqr_secret', '' ) );
	$has_sync_key    = ( defined( 'DESPACE_DESKTOP_SYNC_KEY' ) && '' !== trim( (string) DESPACE_DESKTOP_SYNC_KEY ) )
		|| '' !== trim( (string) get_option( 'despace_desktop_sync_key', '' ) );
	$env_type        = function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production';

	// Action Scheduler Queue health metrics.
	$as_table     = $wpdb->prefix . 'actionscheduler_actions';
	$has_as_table = ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $as_table ) ) ) === $as_table );
	$as_past_due  = $has_as_table ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$as_table} WHERE status = 'pending' AND scheduled_date_gmt < UTC_TIMESTAMP()" ) : 0;
	$as_pending   = $has_as_table ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$as_table} WHERE status = 'pending'" ) : 0;
	$as_failed    = $has_as_table ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$as_table} WHERE status = 'failed'" ) : 0;

	// Core DeSpace & HPOS database tables integrity check.
	$core_tables = array(
		$wpdb->prefix . 'despace_inventory_logs'    => 'Nhật ký Kiểm toán Kho (M4)',
		$wpdb->prefix . 'despace_product_3d_models' => 'Danh mục Tài sản 3D/AR',
		$wpdb->prefix . 'despace_3d_sync_jobs'      => 'Lịch sử Đồng bộ Desktop 3D',
		$wpdb->prefix . 'wc_orders'                 => 'Đơn hàng WooCommerce HPOS',
	);
	$db_status = array();
	foreach ( $core_tables as $tbl_name => $tbl_label ) {
		$exists = ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $tbl_name ) ) ) === $tbl_name );
		$rows   = $exists ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tbl_name}" ) : 0;
		$db_status[] = array(
			'name'   => $tbl_name,
			'label'  => $tbl_label,
			'exists' => $exists,
			'rows'   => $rows,
		);
	}

	// Detect uploads.zip on server for Tools tab.
	$zip_candidates = array(
		dirname( ABSPATH ) . '/uploads.zip',
		ABSPATH . 'uploads.zip',
		WP_CONTENT_DIR . '/uploads.zip',
		$uploads_basedir . '/uploads.zip',
		dirname( get_stylesheet_directory(), 4 ) . '/uploads.zip',
	);
	$detected_zip = null;
	foreach ( $zip_candidates as $candidate ) {
		if ( file_exists( $candidate ) && filesize( $candidate ) > 1000 ) {
			$detected_zip = $candidate;
			break;
		}
	}
	?>
	<div class="wrap ds-cockpit-wrap">
		<?php
		despace_render_admin_cockpit_header(
			'despace-system',
			'Hệ thống & Phân quyền',
			'Kiểm tra sức khỏe hạ tầng, hàng đợi Action Scheduler, toàn vẹn bảng CSDL, ma trận RBAC thực tế và công cụ bảo trì.',
			array(
				array(
					'label' => 'Quản lý Người dùng',
					'url'   => admin_url( 'users.php' ),
					'style' => 'primary',
				),
				array(
					'label' => 'WooCommerce Status',
					'url'   => admin_url( 'admin.php?page=wc-status' ),
					'style' => 'ghost',
				),
				array(
					'label'  => 'Xem cửa hàng ↗',
					'url'    => home_url( '/' ),
					'style'  => 'ghost',
					'target' => '_blank',
				),
			)
		);
		?>

		<?php if ( isset( $_GET['unzipped'] ) && '1' === $_GET['unzipped'] ) : ?>
			<div class="notice notice-success is-dismissible">
				<p><strong>Khôi phục tài sản thành công!</strong> Thư mục <code>wp-content/uploads/</code> đã được giải nén an toàn.</p>
			</div>
		<?php endif; ?>

		<?php if ( isset( $_GET['repaired'] ) ) : ?>
			<div class="notice notice-success is-dismissible">
				<p><strong>Đồng bộ đường dẫn hoàn tất!</strong> Đã cập nhật <strong><?php echo esc_html( number_format_i18n( absint( $_GET['repaired'] ) ) ); ?></strong> bản ghi khớp với <code><?php echo esc_html( home_url() ); ?></code>.</p>
			</div>
		<?php endif; ?>

		<?php if ( isset( $_GET['queue_ran'] ) ) : ?>
			<div class="notice notice-success is-dismissible">
				<p><strong>Hàng đợi Action Scheduler đã được dọn sạch!</strong> Đã xử lý <strong><?php echo esc_html( number_format_i18n( absint( $_GET['queue_ran'] ) ) ); ?></strong> tác vụ nền và hủy các hook mồ côi không còn plugin.</p>
			</div>
		<?php endif; ?>

		<!-- Segmented Sub-navigation Tabs -->
		<nav class="ds-subtabs" aria-label="Phân mục Hệ thống & Phân quyền">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=despace-system&tab=health' ) ); ?>" class="ds-subtabs__item <?php echo 'health' === $active_tab ? 'is-active' : ''; ?>">
				Sức khỏe Hệ thống &amp; CSDL
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=despace-system&tab=rbac' ) ); ?>" class="ds-subtabs__item <?php echo 'rbac' === $active_tab ? 'is-active' : ''; ?>">
				Ma trận Phân quyền (Live RBAC)
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=despace-system&tab=tools' ) ); ?>" class="ds-subtabs__item <?php echo 'tools' === $active_tab ? 'is-active' : ''; ?>">
				Công cụ Tài sản &amp; Bảo trì
			</a>
		</nav>

		<?php if ( 'health' === $active_tab ) : ?>
			<div class="ds-cockpit-grid ds-cockpit-grid--equal">
				<section class="ds-panel">
					<div class="ds-panel__header">
						<h2 class="ds-panel__title">Môi trường máy chủ &amp; WooCommerce</h2>
					</div>
					<div>
						<div class="ds-health-row">
							<span class="ds-health-row__label">Phiên bản PHP / Memory</span>
							<span class="ds-health-row__val"><?php echo esc_html( PHP_VERSION . ' · ' . ini_get( 'memory_limit' ) ); ?></span>
						</div>
						<div class="ds-health-row">
							<span class="ds-health-row__label">Tải tệp tối đa (upload / post)</span>
							<span class="ds-health-row__val"><?php echo esc_html( ini_get( 'upload_max_filesize' ) . ' / ' . ini_get( 'post_max_size' ) ); ?></span>
						</div>
						<div class="ds-health-row">
							<span class="ds-health-row__label">Cơ sở dữ liệu (MySQL)</span>
							<span class="ds-health-row__val"><?php echo esc_html( $wpdb->db_version() ); ?></span>
						</div>
						<div class="ds-health-row">
							<span class="ds-health-row__label">WordPress / WooCommerce</span>
							<span class="ds-health-row__val">
								<?php echo esc_html( get_bloginfo( 'version' ) . ' / ' . ( defined( 'WC_VERSION' ) ? WC_VERSION : 'N/A' ) ); ?>
							</span>
						</div>
						<div class="ds-health-row">
							<span class="ds-health-row__label">WooCommerce HPOS</span>
							<span class="ds-health-row__val">
								<span class="ds-pill <?php echo $hpos_active ? 'ds-pill--success' : 'ds-pill--warning'; ?>">
									<?php echo $hpos_active ? 'Đang bật ✓' : 'Chưa bật'; ?>
								</span>
							</span>
						</div>
						<div class="ds-health-row">
							<span class="ds-health-row__label">Action Scheduler Queue</span>
							<span class="ds-health-row__val">
								<span class="ds-pill <?php echo 0 === $as_past_due ? 'ds-pill--success' : 'ds-pill--warning'; ?>">
									<?php echo esc_html( sprintf( '%d quá hạn · %d chờ · %d lỗi', $as_past_due, $as_pending, $as_failed ) ); ?>
								</span>
							</span>
						</div>
						<div class="ds-health-row">
							<span class="ds-health-row__label">WP_ENVIRONMENT_TYPE</span>
							<span class="ds-health-row__val"><code><?php echo esc_html( $env_type ); ?></code></span>
						</div>
					</div>
				</section>

				<section class="ds-panel">
					<div class="ds-panel__header">
						<h2 class="ds-panel__title">Tài sản 3D/AR, Thư viện nội bộ &amp; Bảo mật</h2>
					</div>
					<div>
						<div class="ds-health-row">
							<span class="ds-health-row__label">Thư mục ảnh danh mục</span>
							<span class="ds-health-row__val">
								<span class="ds-pill <?php echo $has_catalog_dir ? 'ds-pill--success' : 'ds-pill--warning'; ?>">
									<?php echo $has_catalog_dir ? 'Sẵn sàng ✓' : 'Chưa thấy thư mục'; ?>
								</span>
							</span>
						</div>
						<div class="ds-health-row">
							<span class="ds-health-row__label">Tệp 3D Web (.glb) &amp; iOS AR (.usdz)</span>
							<span class="ds-health-row__val">
								<span class="ds-pill <?php echo ( $glb_count >= 30 && $usdz_count >= 30 ) ? 'ds-pill--success' : 'ds-pill--warning'; ?>">
									<?php echo esc_html( sprintf( '%d GLB · %d USDZ', $glb_count, $usdz_count ) ); ?>
								</span>
							</span>
						</div>
						<div class="ds-health-row">
							<span class="ds-health-row__label">Self-hosted model-viewer &amp; Three.js</span>
							<span class="ds-health-row__val">
								<span class="ds-pill <?php echo ( $has_mv_local && $has_three_local ) ? 'ds-pill--success' : 'ds-pill--danger'; ?>">
									<?php echo ( $has_mv_local && $has_three_local ) ? 'Nội bộ (Không CDN) ✓' : 'Thiếu tệp vendor'; ?>
								</span>
							</span>
						</div>
						<div class="ds-health-row">
							<span class="ds-health-row__label">Khóa ký VietQR Webhook</span>
							<span class="ds-health-row__val">
								<span class="ds-pill <?php echo $has_vietqr_sec ? 'ds-pill--success' : 'ds-pill--warning'; ?>">
									<?php echo $has_vietqr_sec ? 'Đã cấu hình ✓' : 'Chưa cấu hình'; ?>
								</span>
							</span>
						</div>
						<div class="ds-health-row">
							<span class="ds-health-row__label">Khóa kết nối Desktop 3D Studio</span>
							<span class="ds-health-row__val">
								<span class="ds-pill <?php echo $has_sync_key ? 'ds-pill--success' : 'ds-pill--danger'; ?>">
									<?php echo $has_sync_key ? 'Đã cấp khóa 64-hex ✓' : 'Chưa cấp khóa'; ?>
								</span>
							</span>
						</div>
						<div class="ds-health-row">
							<span class="ds-health-row__label">Địa chỉ gốc website (home_url)</span>
							<span class="ds-health-row__val"><code><?php echo esc_html( home_url() ); ?></code></span>
						</div>
					</div>
				</section>
			</div>

			<!-- Database Tables Integrity -->
			<section class="ds-panel">
				<div class="ds-panel__header">
					<div>
						<h2 class="ds-panel__title">Toàn vẹn bảng cơ sở dữ liệu DeSpace &amp; HPOS</h2>
						<p class="ds-panel__desc">Kiểm tra tồn tại và số lượng bản ghi thực tế của 4 bảng dữ liệu nghiệp vụ cốt lõi.</p>
					</div>
				</div>
				<div class="ds-db-grid">
					<?php foreach ( $db_status as $tbl ) : ?>
						<div class="ds-db-card">
							<div style="display:flex; justify-content:space-between; align-items:center; gap:8px; margin-bottom:6px;">
								<strong style="font-size:13px; color:#1c1917;"><?php echo esc_html( $tbl['label'] ); ?></strong>
								<span class="ds-pill <?php echo $tbl['exists'] ? 'ds-pill--success' : 'ds-pill--danger'; ?>">
									<?php echo $tbl['exists'] ? 'OK ✓' : 'Thiếu'; ?>
								</span>
							</div>
							<div class="ds-db-card__rows"><?php echo esc_html( number_format_i18n( $tbl['rows'] ) ); ?></div>
							<div class="ds-db-card__name"><?php echo esc_html( $tbl['name'] ); ?></div>
						</div>
					<?php endforeach; ?>
				</div>
			</section>

			<!-- 7 Canonical Policy & Info Pages Audit -->
			<section class="ds-panel">
				<div class="ds-panel__header">
					<div>
						<h2 class="ds-panel__title">Kiểm tra 7 trang Chính sách &amp; Thông tin chuẩn</h2>
						<p class="ds-panel__desc">Đảm bảo các liên kết footer và trang thanh toán luôn trả về HTTP 200 (không 404).</p>
					</div>
					<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=page' ) ); ?>" class="ds-btn ds-btn--outline ds-btn--sm">Tất cả Trang →</a>
				</div>
				<?php
				$canonical_slugs = array(
					'gioi-thieu'                    => 'Giới thiệu DeSpace',
					'lien-he'                       => 'Liên hệ & Showroom',
					'chinh-sach-giao-hang'          => 'Chính sách giao hàng & lắp đặt',
					'chinh-sach-doi-tra-bao-hanh'   => 'Chính sách đổi trả & bảo hành',
					'chinh-sach-bao-mat'            => 'Chính sách bảo mật',
					'dieu-khoan-mua-ban'            => 'Điều khoản mua bán',
					'huong-dan-mua-hang-thanh-toan' => 'Hướng dẫn mua hàng & thanh toán',
				);
				?>
				<div class="ds-table-wrap">
					<table class="ds-table">
						<thead>
							<tr>
								<th>Trang nội dung</th>
								<th>Đường dẫn chuẩn</th>
								<th>Trạng thái</th>
								<th style="text-align:right;">Thao tác</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $canonical_slugs as $slug => $label ) : ?>
								<?php
								$page_obj = get_page_by_path( $slug, OBJECT, 'page' );
								$is_pub   = ( $page_obj instanceof WP_Post && 'publish' === $page_obj->post_status );
								?>
								<tr>
									<td><strong><?php echo esc_html( $label ); ?></strong></td>
									<td><code>/<?php echo esc_html( $slug ); ?>/</code></td>
									<td>
										<span class="ds-pill <?php echo $is_pub ? 'ds-pill--success' : 'ds-pill--danger'; ?>">
											<?php echo $is_pub ? 'Đã xuất bản ✓' : 'Chưa có / Nháp'; ?>
										</span>
									</td>
									<td style="text-align:right;">
										<?php if ( $page_obj instanceof WP_Post ) : ?>
											<a href="<?php echo esc_url( get_edit_post_link( $page_obj->ID, '' ) ); ?>" class="ds-btn ds-btn--outline ds-btn--sm">Sửa</a>
											<a href="<?php echo esc_url( get_permalink( $page_obj->ID ) ); ?>" target="_blank" rel="noopener noreferrer" class="ds-btn ds-btn--outline ds-btn--sm">Xem ↗</a>
										<?php else : ?>
											<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=page' ) ); ?>" class="ds-btn ds-btn--outline ds-btn--sm">Tạo mới</a>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</section>
		<?php endif; ?>

		<?php if ( 'rbac' === $active_tab ) : ?>
			<?php
			$wp_roles_obj = wp_roles();
			$user_counts  = count_users();
			$avail_roles  = $user_counts['avail_roles'] ?? array();
			$tracked_caps = array(
				'manage_options'              => 'Quản trị hệ thống',
				'manage_woocommerce'          => 'Cửa hàng & Đơn',
				'view_despace_reports'        => 'Báo cáo (M5)',
				'view_despace_inventory_logs' => 'Nhật ký Kho (M4)',
				'manage_despace_3d'           => '3D & AR Studio',
				'edit_despace_products'       => 'Sửa SP DeSpace',
			);
			$role_order = array(
				'administrator' => 'Quản trị viên cấp cao (Toàn quyền hệ thống, bảo mật, cài đặt API)',
				'shop_manager'  => 'Quản lý cửa hàng WooCommerce (Sản phẩm, đơn hàng, showroom)',
				'despace_staff' => 'Nhân viên vận hành DeSpace (Báo cáo M5, Nhật ký Kho M4, Danh mục 3D)',
				'customer'      => 'Khách hàng (Chỉ truy cập tài khoản cá nhân & đơn đã mua)',
			);
			?>
			<section class="ds-panel">
				<div class="ds-panel__header">
					<div>
						<h2 class="ds-panel__title">Ma trận Phân quyền Thực tế (Đọc trực tiếp từ WordPress RBAC)</h2>
						<p class="ds-panel__desc">Hiển thị vai trò, số lượng tài khoản thực tế và quyền hạn (capabilities) đang áp dụng trong cơ sở dữ liệu.</p>
					</div>
					<a href="<?php echo esc_url( admin_url( 'users.php' ) ); ?>" class="ds-btn ds-btn--primary ds-btn--sm">Quản lý &amp; Gán vai trò người dùng →</a>
				</div>

				<div class="ds-table-wrap">
					<table class="ds-table">
						<thead>
							<tr>
								<th>Vai trò (Role)</th>
								<th style="text-align:center;">Tài khoản</th>
								<?php foreach ( $tracked_caps as $cap_label ) : ?>
									<th style="text-align:center;"><?php echo esc_html( $cap_label ); ?></th>
								<?php endforeach; ?>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $role_order as $role_slug => $role_desc ) : ?>
								<?php
								$role_obj   = $wp_roles_obj ? $wp_roles_obj->get_role( $role_slug ) : null;
								$role_name  = $wp_roles_obj->role_names[ $role_slug ] ?? $role_slug;
								$role_count = (int) ( $avail_roles[ $role_slug ] ?? 0 );
								?>
								<tr>
									<td>
										<strong><?php echo esc_html( translate_user_role( $role_name ) ); ?></strong>
										<code style="margin-left:6px; font-size:12px;"><?php echo esc_html( $role_slug ); ?></code>
										<div style="font-size:12.5px; color:#5c564e; margin-top:3px;"><?php echo esc_html( $role_desc ); ?></div>
									</td>
									<td style="text-align:center;">
										<a href="<?php echo esc_url( admin_url( 'users.php?role=' . rawurlencode( $role_slug ) ) ); ?>" class="ds-pill ds-pill--neutral" style="text-decoration:none;">
											<?php echo esc_html( number_format_i18n( $role_count ) ); ?> tài khoản
										</a>
									</td>
									<?php foreach ( $tracked_caps as $cap_key => $cap_label ) : ?>
										<?php $has_cap = $role_obj && $role_obj->has_cap( $cap_key ); ?>
										<td style="text-align:center;">
											<?php if ( $has_cap ) : ?>
												<span class="ds-pill ds-pill--success" aria-label="Có quyền">✓ Có</span>
											<?php else : ?>
												<span style="color:#a8a29e;" aria-label="Không có quyền">—</span>
											<?php endif; ?>
										</td>
									<?php endforeach; ?>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</section>
		<?php endif; ?>

		<?php if ( 'tools' === $active_tab ) : ?>
			<section class="ds-panel">
				<div class="ds-panel__header">
					<div>
						<h2 class="ds-panel__title">1. Khôi phục Ảnh sản phẩm &amp; Mô hình 3D từ uploads.zip</h2>
						<p class="ds-panel__desc">Giải nén an toàn vào <code>wp-content/uploads/</code> (chỉ cho phép tệp ảnh, GLB, USDZ; tự động chặn Zip Slip và mã thực thi).</p>
					</div>
				</div>

				<div class="ds-kpi-grid" style="margin-bottom:18px;">
					<div class="ds-kpi-card">
						<span class="ds-kpi-card__label">Ảnh danh mục</span>
						<div class="ds-kpi-card__value" style="font-size:21px;"><?php echo $has_catalog_dir ? 'Đã có đầy đủ ✓' : 'Chưa có thư mục'; ?></div>
						<span class="ds-kpi-card__meta"><code>wp-content/uploads/despace-catalog/</code></span>
					</div>
					<div class="ds-kpi-card">
						<span class="ds-kpi-card__label">Mô hình 3D &amp; AR</span>
						<div class="ds-kpi-card__value" style="font-size:21px;"><?php echo esc_html( sprintf( '%d GLB · %d USDZ', $glb_count, $usdz_count ) ); ?></div>
						<span class="ds-kpi-card__meta"><code>wp-content/uploads/3d/</code></span>
					</div>
					<div class="ds-kpi-card">
						<span class="ds-kpi-card__label">Gói uploads.zip trên máy chủ</span>
						<div class="ds-kpi-card__value" style="font-size:21px;"><?php echo $detected_zip ? 'Đã phát hiện ✓' : 'Chưa đặt sẵn'; ?></div>
						<span class="ds-kpi-card__meta">
							<?php echo $detected_zip ? esc_html( basename( $detected_zip ) . ' (' . size_format( filesize( $detected_zip ) ) . ')' ) : 'Có thể tải tệp .zip trực tiếp bên dưới'; ?>
						</span>
					</div>
				</div>

				<?php if ( $detected_zip ) : ?>
					<div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px; padding:18px 20px; background:#f0fdf4; border:1px solid #86efac; border-radius:10px;">
						<div>
							<strong style="color:#166534; font-size:15px;">Phát hiện gói tài sản: <?php echo esc_html( basename( $detected_zip ) ); ?> (<?php echo esc_html( size_format( filesize( $detected_zip ) ) ); ?>)</strong>
							<p style="margin:4px 0 0; color:#15803d; font-size:13.5px;">Bấm nút bên phải để giải nén an toàn vào <code>wp-content/uploads/</code>.</p>
						</div>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<?php wp_nonce_field( 'despace_hub_unzip_nonce' ); ?>
							<input type="hidden" name="action" value="despace_hub_unzip_uploads" />
							<button type="submit" class="ds-btn ds-btn--primary" onclick="this.textContent='Đang giải nén...';">
								Giải nén &amp; Nạp Tài sản ngay
							</button>
						</form>
					</div>
				<?php else : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" style="display:flex; align-items:center; gap:12px; flex-wrap:wrap; padding:16px 18px; background:#faf8f4; border:1px solid #e6e0d6; border-radius:10px;">
						<?php wp_nonce_field( 'despace_hub_unzip_nonce' ); ?>
						<input type="hidden" name="action" value="despace_hub_unzip_uploads" />
						<input type="file" name="upload_zip_file" accept=".zip" required />
						<button type="submit" class="ds-btn ds-btn--dark">Tải lên &amp; Giải nén an toàn</button>
					</form>
				<?php endif; ?>
			</section>

			<section class="ds-panel">
				<div class="ds-panel__header" style="margin-bottom:0; padding-bottom:0; border-bottom:0;">
					<div>
						<h2 class="ds-panel__title">2. Chạy &amp; Dọn hàng đợi Action Scheduler (Tác vụ nền WooCommerce)</h2>
						<p class="ds-panel__desc">Hiện tại: <strong><?php echo esc_html( $as_past_due ); ?> quá hạn</strong> · <strong><?php echo esc_html( $as_pending ); ?> đang chờ</strong> · Tự động dọn hook mồ côi và chạy tác vụ WooCommerce tồn đọng.</p>
					</div>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( 'despace_run_as_nonce' ); ?>
						<input type="hidden" name="action" value="despace_run_action_scheduler" />
						<button type="submit" class="ds-btn ds-btn--dark">Chạy &amp; Dọn Hàng đợi</button>
					</form>
				</div>
			</section>

			<section class="ds-panel">
				<div class="ds-panel__header" style="margin-bottom:0; padding-bottom:0; border-bottom:0;">
					<div>
						<h2 class="ds-panel__title">3. Đồng bộ đường dẫn URL sau khi đổi cổng / tên miền</h2>
						<p class="ds-panel__desc">Quét và thay thế các URL localhost cũ (8088, 8090, 10016, 8080) về đúng tên miền hiện tại <code><?php echo esc_html( home_url() ); ?></code>.</p>
					</div>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( 'despace_hub_repair_nonce' ); ?>
						<input type="hidden" name="action" value="despace_hub_repair_urls" />
						<button type="submit" class="ds-btn ds-btn--outline">Chạy đồng bộ đường dẫn</button>
					</form>
				</div>
			</section>

			<?php if ( 'local' === $env_type ) : ?>
				<section class="ds-panel">
					<div class="ds-panel__header">
						<div>
							<h2 class="ds-panel__title">4. Tiện ích Môi trường Local (Docker)</h2>
							<p class="ds-panel__desc">Chỉ hiển thị khi <code>WP_ENVIRONMENT_TYPE === 'local'</code> — tự động ẩn trên Production.</p>
						</div>
					</div>
					<div style="display:flex; gap:10px; flex-wrap:wrap;">
						<a href="http://localhost:8026/" target="_blank" rel="noopener noreferrer" class="ds-btn ds-btn--outline">Mở Mailpit (Hộp thư test) ↗</a>
						<a href="<?php echo esc_url( admin_url( 'site-health.php' ) ); ?>" class="ds-btn ds-btn--outline">WordPress Site Health</a>
						<a href="<?php echo esc_url( admin_url( 'options-permalink.php' ) ); ?>" class="ds-btn ds-btn--outline">Làm mới Permalinks</a>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-status&tab=tools' ) ); ?>" class="ds-btn ds-btn--outline">Công cụ WooCommerce</a>
					</div>
				</section>
			<?php endif; ?>
		<?php endif; ?>
	</div>
	<?php
}

function despace_render_showroom_info_page() {
	if ( function_exists( 'despace_render_contact_admin_page' ) ) {
		$_GET['tab'] = 'showroom';
		despace_render_contact_admin_page();
		return;
	}
	wp_safe_redirect( admin_url( 'admin.php?page=despace-contact-settings&tab=showroom' ) );
	exit;
}
