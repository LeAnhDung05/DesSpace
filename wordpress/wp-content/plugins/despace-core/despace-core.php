<?php
/**
 * Plugin Name: DeSpace Core
 * Plugin URI:  https://despace.vn
 * Description: Core business logic, 3D/AR model-viewer integration, REST API filter endpoints, and inventory audit logs for DeSpace Furniture E-commerce.
 * Version:     1.2.0
 * Author:      DeSpace Engineering & PAIC
 * Author URI:  https://despace.vn
 * Text Domain: despace-core
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * WC requires at least: 8.0
 * WC tested up to: 9.3
 *
 * @package DeSpace_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DESPACE_CORE_VERSION', '1.2.0' );
define( 'DESPACE_CORE_FILE', __FILE__ );
define( 'DESPACE_CORE_PATH', plugin_dir_path( __FILE__ ) );
define( 'DESPACE_CORE_URL', plugin_dir_url( __FILE__ ) );

/**
 * Declare HPOS (High-Performance Order Storage) Compatibility.
 */
add_action( 'before_woocommerce_init', function() {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );

/**
 * Require Component Classes.
 */
require_once DESPACE_CORE_PATH . 'includes/class-despace-activator.php';
require_once DESPACE_CORE_PATH . 'includes/class-despace-deactivator.php';
require_once DESPACE_CORE_PATH . 'includes/class-despace-3d.php';
require_once DESPACE_CORE_PATH . 'includes/class-despace-rest-api.php';
require_once DESPACE_CORE_PATH . 'includes/class-despace-inventory.php';
require_once DESPACE_CORE_PATH . 'includes/class-despace-admin.php';

/**
 * Activation & Deactivation Hooks.
 */
register_activation_hook( __FILE__, array( 'DeSpace_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'DeSpace_Deactivator', 'deactivate' ) );

/**
 * Initialize DeSpace Core Plugin.
 */
function despace_core_init() {
	// Initialize 3D & AR
	new DeSpace_3D();

	// Initialize REST API
	new DeSpace_REST_API();

	// Initialize Inventory Logging
	new DeSpace_Inventory();

	// Initialize Admin & Dashboard
	if ( is_admin() ) {
		new DeSpace_Admin();
	}
}
add_action( 'plugins_loaded', 'despace_core_init' );
