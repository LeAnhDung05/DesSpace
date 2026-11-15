<?php
/**
 * Entry point for DeSpace Checkout, Cart & Orders Module (M3).
 *
 * @package DeSpace\CheckoutOrders
 */

namespace DeSpace\CheckoutOrders;

use DeSpace\CheckoutOrders\Account\AccountAddressHandler;
use DeSpace\CheckoutOrders\Account\AccountDataPresenter;
use DeSpace\CheckoutOrders\Account\AccountProfileHandler;
use DeSpace\CheckoutOrders\Cart\CartHandler;
use DeSpace\CheckoutOrders\Cart\CouponValidator;
use DeSpace\CheckoutOrders\Checkout\AddressValidator;
use DeSpace\CheckoutOrders\Checkout\CheckoutValidator;
use DeSpace\CheckoutOrders\Checkout\OrderMetaSaver;
use DeSpace\CheckoutOrders\Email\EmailCustomizer;
use DeSpace\CheckoutOrders\Orders\GuestOrderTracker;
use DeSpace\CheckoutOrders\Payment\BacsInstructions;
use DeSpace\CheckoutOrders\Payment\PaymentMethodValidator;
use DeSpace\CheckoutOrders\Payment\PaymentStatusGuard;
use DeSpace\CheckoutOrders\Shipping\VietnamRegions;
use DeSpace\CheckoutOrders\Shipping\ShippingNoticeHelper;

defined( 'ABSPATH' ) || exit;

/**
 * Class CheckoutOrdersModule
 *
 * Initializes M3 submodules safely without touching global plugin bootstrap
 * or M1/M2/M4/M5 modules. Designed to be invoked by M5 in despace-core.php.
 */
class CheckoutOrdersModule {

	/**
	 * Tracks whether the module has already been initialized.
	 *
	 * @var bool
	 */
	private static bool $initialized = false;

	/**
	 * Tracks whether the PSR-4 autoloader has been registered.
	 *
	 * @var bool
	 */
	private static bool $autoload_registered = false;

	/**
	 * List of M3 submodules that successfully completed `init()`.
	 *
	 * @var array<int, string>
	 */
	private static array $initialized_submodules = array();

	/**
	 * Map of failed submodule class => failure message recorded during bootstrap.
	 *
	 * @var array<string, string>
	 */
	private static array $failed_submodules = array();

	/**
	 * Bootstrap M3 Checkout & Orders Module.
	 *
	 * Safe to call repeatedly; prevents duplicate initialization, guards against missing
	 * WooCommerce runtime without causing fatal errors, and isolates per-submodule failures
	 * so one broken submodule cannot silently abort all remaining M3 submodules.
	 */
	public static function init(): void {
		if ( self::$initialized ) {
			return;
		}

		self::register_autoloader();

		if ( ! self::is_woocommerce_active() ) {
			return;
		}

		self::$initialized            = true;
		self::$initialized_submodules = array();
		self::$failed_submodules      = array();

		$submodules = array(
			CartHandler::class,
			CouponValidator::class,
			VietnamRegions::class,
			ShippingNoticeHelper::class,
			AddressValidator::class,
			OrderMetaSaver::class,
			CheckoutValidator::class,
			PaymentMethodValidator::class,
			PaymentStatusGuard::class,
			BacsInstructions::class,
			GuestOrderTracker::class,
			AccountProfileHandler::class,
			AccountAddressHandler::class,
			AccountDataPresenter::class,
			EmailCustomizer::class,
		);

		if ( function_exists( 'apply_filters' ) ) {
			$submodules = (array) apply_filters( 'despace_m3_bootstrap_submodules', $submodules );
		}

		foreach ( $submodules as $submodule ) {
			$submodule = (string) $submodule;
			try {
				if ( '' === $submodule || ! class_exists( $submodule ) || ! method_exists( $submodule, 'init' ) ) {
					throw new \RuntimeException( sprintf( 'Missing M3 submodule init() entrypoint: %s', $submodule ) );
				}

				$submodule::init();
				self::$initialized_submodules[] = $submodule;
			} catch ( \Throwable $e ) {
				self::$failed_submodules[ $submodule ] = $e->getMessage();
				if ( function_exists( 'error_log' ) ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional bootstrap failure telemetry.
					error_log( sprintf( '[DeSpace M3 Bootstrap Error] Submodule "%s" failed to initialize: %s', $submodule, $e->getMessage() ) );
				}
			}
		}

		if ( ! empty( self::$failed_submodules ) && function_exists( 'add_action' ) ) {
			add_action( 'admin_notices', array( __CLASS__, 'render_submodule_failure_notice' ) );
		}
	}

	/**
	 * Register PSR-4 compatible autoloader for DeSpace\CheckoutOrders\*.
	 */
	public static function register_autoloader(): void {
		if ( self::$autoload_registered ) {
			return;
		}

		spl_autoload_register( array( __CLASS__, 'autoload' ) );
		self::$autoload_registered = true;
	}

	/**
	 * Internal autoloader for DeSpace\CheckoutOrders\* classes.
	 *
	 * @param string $class Fully-qualified class name.
	 */
	public static function autoload( string $class ): void {
		$prefix = 'DeSpace\\CheckoutOrders\\';
		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}

		$relative_class = substr( $class, strlen( $prefix ) );
		$file           = __DIR__ . '/' . str_replace( '\\', '/', $relative_class ) . '.php';

		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}

	/**
	 * Check whether WooCommerce is loaded and its global helper is available.
	 *
	 * @return bool
	 */
	public static function is_woocommerce_active(): bool {
		return class_exists( 'WooCommerce' ) && function_exists( 'WC' );
	}

	/**
	 * Return initialization state.
	 *
	 * @return bool
	 */
	public static function is_initialized(): bool {
		return self::$initialized;
	}

	/**
	 * Report whether all M3 submodules initialized cleanly without recorded errors.
	 *
	 * @return bool
	 */
	public static function is_healthy(): bool {
		return self::$initialized && empty( self::$failed_submodules );
	}

	/**
	 * List M3 submodule class names that initialized cleanly.
	 *
	 * @return array<int, string>
	 */
	public static function get_initialized_submodules(): array {
		return self::$initialized_submodules;
	}

	/**
	 * Return map of submodule class => error message for any submodule that failed during `init()`.
	 *
	 * @return array<string, string>
	 */
	public static function get_failed_submodules(): array {
		return self::$failed_submodules;
	}

	/**
	 * Render an admin notice when one or more M3 submodules fail initialization.
	 */
	public static function render_submodule_failure_notice(): void {
		if ( empty( self::$failed_submodules ) ) {
			return;
		}
		if ( function_exists( 'current_user_can' ) && ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		$names = implode( ', ', array_keys( self::$failed_submodules ) );
		$msg   = sprintf(
			/* translators: %s: comma-separated list of failed submodule classes */
			'DeSpace M3 Checkout-Orders gặp lỗi khi khởi tạo submodule: %s',
			$names
		);

		$escaped = function_exists( 'esc_html' ) ? esc_html( $msg ) : htmlspecialchars( $msg, ENT_QUOTES, 'UTF-8' );
		echo '<div class="notice notice-error"><p>' . $escaped . '</p></div>';
	}
}

