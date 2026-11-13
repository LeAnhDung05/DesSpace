<?php
/**
 * Entry point for DeSpace Inventory Traceability Module (M4).
 *
 * @package DeSpace\Inventory
 */

namespace DeSpace\Inventory;

use DeSpace\Inventory\Database\InventoryLogInstaller;
use DeSpace\Inventory\Events\InventoryStockObserver;
use DeSpace\Inventory\REST\InventoryLogsController;
use DeSpace\Inventory\Admin\InventoryLogsPage;

defined( 'ABSPATH' ) || exit;

/**
 * Class InventoryModule
 */
class InventoryModule {

	/**
	 * Has module been initialized.
	 *
	 * @var bool
	 */
	private static bool $initialized = false;

	/**
	 * Bootstrap M4 Inventory Module.
	 */
	public static function init(): void {
		if ( self::$initialized ) {
			return;
		}

		self::$initialized = true;

		// 1. Register PSR-4 compatible autoloader for DeSpace\Inventory namespace.
		spl_autoload_register( array( __CLASS__, 'autoload' ) );

		// 2. Ensure Database table exists and is up to date.
		InventoryLogInstaller::install();

		// 3. Register WooCommerce stock lifecycle observers.
		InventoryStockObserver::init();

		// 4. Register REST API routes.
		add_action( 'rest_api_init', array( InventoryLogsController::class, 'register_routes' ) );

		// 5. Register Admin menu & UI if in admin context.
		if ( is_admin() ) {
			InventoryLogsPage::init();
		}
	}

	/**
	 * Internal autoloader for DeSpace\Inventory\* classes.
	 *
	 * @param string $class
	 */
	public static function autoload( string $class ): void {
		$prefix = 'DeSpace\\Inventory\\';
		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}

		$relative_class = substr( $class, strlen( $prefix ) );
		$file           = __DIR__ . '/' . str_replace( '\\', '/', $relative_class ) . '.php';

		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
}

