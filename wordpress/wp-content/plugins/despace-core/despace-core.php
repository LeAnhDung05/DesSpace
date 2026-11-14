<?php
/**
 * Plugin Name:       DeSpace Core
 * Plugin URI:        https://github.com/LeAnhDung05/DesSpace
 * Description:       Core architectural engine for DeSpace Premium Furniture: RBAC, 3D/AR showroom, catalog filtering, custom checkout, inventory audit logging, and reporting.
 * Version:           1.0.0
 * Author:            DeSpace Team (M1 - M5)
 * Author URI:        https://github.com/LeAnhDung05/DesSpace
 * Text Domain:       despace-core
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * WC requires at least: 7.0
 * WC tested up to:   9.0
 *
 * @package DeSpace
 */

defined( 'ABSPATH' ) || exit;

// 1. Define plugin constants.
define( 'DESPACE_CORE_VERSION', '1.0.0' );
define( 'DESPACE_CORE_FILE', __FILE__ );
define( 'DESPACE_CORE_PATH', plugin_dir_path( __FILE__ ) );
define( 'DESPACE_CORE_URL', plugin_dir_url( __FILE__ ) );
define( 'DESPACE_CORE_BASENAME', plugin_basename( __FILE__ ) );
define( 'DESPACE_CORE_INCLUDES', DESPACE_CORE_PATH . 'includes/' );

// 2. Register PSR-4 compatible autoloader for DeSpace namespace.
spl_autoload_register( function ( string $class ): void {
	$prefix = 'DeSpace\\';
	if ( 0 !== strpos( $class, $prefix ) ) {
		return;
	}

	$relative_class = substr( $class, strlen( $prefix ) );

	// 1. Direct PSR-4 path mapping: DeSpace\Sub\Class -> includes/Sub/Class.php
	$direct_file = DESPACE_CORE_INCLUDES . str_replace( '\\', '/', $relative_class ) . '.php';
	if ( file_exists( $direct_file ) ) {
		require_once $direct_file;
		return;
	}

	// 2. Compatibility alias mappings:
	// - DeSpace\Plugin / DeSpace\Core\Plugin -> includes/Core/Plugin.php
	if ( 'Plugin' === $relative_class || 'Core\\Plugin' === $relative_class ) {
		require_once DESPACE_CORE_INCLUDES . 'Core/Plugin.php';
		if ( ! class_exists( 'DeSpace\\Core\\Plugin', false ) && class_exists( 'DeSpace\\Plugin' ) ) {
			class_alias( 'DeSpace\\Plugin', 'DeSpace\\Core\\Plugin' );
		} elseif ( ! class_exists( 'DeSpace\\Plugin', false ) && class_exists( 'DeSpace\\Core\\Plugin' ) ) {
			class_alias( 'DeSpace\\Core\\Plugin', 'DeSpace\\Plugin' );
		}
		return;
	}

	// - DeSpace\CheckoutOrders\... -> includes/Integrations/WooCommerce/...
	if ( 0 === strpos( $relative_class, 'CheckoutOrders\\' ) ) {
		$co_rel = substr( $relative_class, strlen( 'CheckoutOrders\\' ) );
		$co_file = DESPACE_CORE_INCLUDES . 'Integrations/WooCommerce/' . str_replace( '\\', '/', $co_rel ) . '.php';
		if ( file_exists( $co_file ) ) {
			require_once $co_file;
			return;
		}
	}
	if ( 'CheckoutOrdersModule' === $relative_class ) {
		require_once DESPACE_CORE_INCLUDES . 'Integrations/WooCommerce/CheckoutOrdersModule.php';
		return;
	}

	// - DeSpace\RestApi\... -> includes/Rest/...
	if ( 0 === strpos( $relative_class, 'RestApi\\' ) ) {
		$rest_rel = substr( $relative_class, strlen( 'RestApi\\' ) );
		$rest_file = DESPACE_CORE_INCLUDES . 'Rest/' . str_replace( '\\', '/', $rest_rel ) . '.php';
		if ( file_exists( $rest_file ) ) {
			require_once $rest_file;
			return;
		}
	}
} );

// 3. Declare High-Performance Order Storage (HPOS) compatibility before WooCommerce loads.
add_action( 'before_woocommerce_init', array( 'DeSpace\\Plugin', 'declare_hpos_compatibility' ) );

// 4. Register activation and deactivation hooks.
register_activation_hook( __FILE__, array( 'DeSpace\\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'DeSpace\\Plugin', 'deactivate' ) );

// 5. Bootstrap plugin on plugins_loaded.
add_action( 'plugins_loaded', array( 'DeSpace\\Plugin', 'init' ) );

