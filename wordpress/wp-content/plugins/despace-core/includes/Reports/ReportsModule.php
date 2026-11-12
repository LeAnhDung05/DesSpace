<?php
/**
 * Entry point for DeSpace Dashboard & Reports Module (M5).
 *
 * @package DeSpace\Reports
 */

namespace DeSpace\Reports;

use DeSpace\Reports\REST\DashboardMetricsController;
use DeSpace\Reports\Admin\DashboardPage;

defined( 'ABSPATH' ) || exit;

/**
 * Class ReportsModule
 */
class ReportsModule {

	/**
	 * Has module been initialized.
	 *
	 * @var bool
	 */
	private static bool $initialized = false;

	/**
	 * Bootstrap M5 Dashboard & Reports Module.
	 */
	public static function init(): void {
		if ( self::$initialized ) {
			return;
		}

		self::$initialized = true;

		// 1. Register PSR-4 compatible autoloader for DeSpace\Reports namespace.
		spl_autoload_register( array( __CLASS__, 'autoload' ) );

		// 2. Register REST API routes.
		add_action( 'rest_api_init', array( DashboardMetricsController::class, 'register_routes' ) );

		// 3. Register Admin menu & UI in admin context.
		if ( is_admin() ) {
			DashboardPage::init();
		}
	}

	/**
	 * Internal autoloader for DeSpace\Reports\* classes.
	 *
	 * @param string $class
	 */
	public static function autoload( string $class ): void {
		$prefix = 'DeSpace\\Reports\\';
		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}

		$relative_class = substr( $class, strlen( $prefix ) );
		$file           = __DIR__ . '/' . str_replace( '\\', '/', $relative_class ) . '.php';

		if ( file_exists( $file ) ) {
			require_once $file;
			return;
		}

		// Handle REST-API folder mapping fallback.
		$mapped_file = __DIR__ . '/' . str_replace( array( '\\', 'REST/' ), array( '/', 'REST-API/' ), $relative_class ) . '.php';
		if ( file_exists( $mapped_file ) ) {
			require_once $mapped_file;
		}
	}
}

