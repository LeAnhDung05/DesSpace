<?php
/**
 * Main Coordinator for DeSpace Core Plugin.
 *
 * @package DeSpace
 */

namespace DeSpace;

use DeSpace\Security\RoleManager;

defined( 'ABSPATH' ) || exit;

/**
 * Class Plugin
 */
class Plugin {

	/**
	 * Plugin singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Whether the plugin has been initialized.
	 *
	 * @var bool
	 */
	private static bool $initialized = false;

	/**
	 * Get singleton instance.
	 *
	 * @return Plugin
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Initialize plugin and submodules.
	 */
	public static function init(): void {
		if ( self::$initialized ) {
			return;
		}
		self::$initialized = true;

		// 1. Load localization files.
		load_plugin_textdomain(
			'despace-core',
			false,
			dirname( plugin_basename( DESPACE_CORE_FILE ) ) . '/languages'
		);

		// 2. Check WooCommerce active dependency.
		if ( ! self::is_woocommerce_active() ) {
			add_action( 'admin_notices', array( __CLASS__, 'render_woocommerce_missing_notice' ) );
			return;
		}

		// 3. Initialize Core RBAC Module.
		RoleManager::init();

		// 4. Initialize Submodules if present (M1 - M4).
		self::init_submodules();
	}

	/**
	 * Plugin activation lifecycle hook.
	 */
	public static function activate(): void {
		// Initialize and install RBAC roles & capabilities.
		RoleManager::activate();

		// Trigger M4 inventory installer if available.
		if ( class_exists( 'DeSpace\\Inventory\\Database\\InventoryLogInstaller' ) ) {
			\DeSpace\Inventory\Database\InventoryLogInstaller::install();
		}
	}

	/**
	 * Plugin deactivation lifecycle hook.
	 */
	public static function deactivate(): void {
		RoleManager::deactivate();
	}

	/**
	 * Declare HPOS (High-Performance Order Storage) compatibility.
	 */
	public static function declare_hpos_compatibility(): void {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				DESPACE_CORE_FILE,
				true
			);
		}
	}

	/**
	 * Check if WooCommerce is installed and active.
	 *
	 * @return bool
	 */
	public static function is_woocommerce_active(): bool {
		if ( class_exists( 'WooCommerce' ) ) {
			return true;
		}

		if ( function_exists( 'is_plugin_active' ) ) {
			return is_plugin_active( 'woocommerce/woocommerce.php' );
		}

		$active_plugins = (array) get_option( 'active_plugins', array() );
		if ( is_multisite() ) {
			$network_active = (array) get_site_option( 'active_sitewide_plugins', array() );
			$active_plugins = array_merge( $active_plugins, array_keys( $network_active ) );
		}

		return in_array( 'woocommerce/woocommerce.php', $active_plugins, true );
	}

	/**
	 * Render administrative notice if WooCommerce is inactive.
	 */
	public static function render_woocommerce_missing_notice(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		?>
		<div class="notice notice-error is-dismissible">
			<p>
				<strong><?php esc_html_e( 'DeSpace Core requires WooCommerce to be installed and active.', 'despace-core' ); ?></strong>
				<?php esc_html_e( 'Please activate WooCommerce to enable furniture catalog, 3D showroom, checkout hooks, and inventory traceability.', 'despace-core' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Dynamically bootstrap submodules when their module classes are available.
	 */
	private static function init_submodules(): void {
		// M1: 3D / AR Showroom.
		if ( class_exists( 'DeSpace\\Product3D\\Product3DModule' ) && method_exists( 'DeSpace\\Product3D\\Product3DModule', 'init' ) ) {
			\DeSpace\Product3D\Product3DModule::init();
		}

		// M2: Catalog & Filter Extensions.
		if ( class_exists( 'DeSpace\\Catalog\\CatalogModule' ) && method_exists( 'DeSpace\\Catalog\\CatalogModule', 'init' ) ) {
			\DeSpace\Catalog\CatalogModule::init();
		}

		// M3: Checkout & Order Management.
		if ( class_exists( 'DeSpace\\CheckoutOrders\\CheckoutOrdersModule' ) && method_exists( 'DeSpace\\CheckoutOrders\\CheckoutOrdersModule', 'init' ) ) {
			\DeSpace\CheckoutOrders\CheckoutOrdersModule::init();
		}

		// M4: Inventory Traceability.
		if ( class_exists( 'DeSpace\\Inventory\\InventoryModule' ) && method_exists( 'DeSpace\\Inventory\\InventoryModule', 'init' ) ) {
			\DeSpace\Inventory\InventoryModule::init();
		}

		// M5: Reports & Analytics.
		if ( class_exists( 'DeSpace\\Reports\\ReportsModule' ) && method_exists( 'DeSpace\\Reports\\ReportsModule', 'init' ) ) {
			\DeSpace\Reports\ReportsModule::init();
		}
	}
}

