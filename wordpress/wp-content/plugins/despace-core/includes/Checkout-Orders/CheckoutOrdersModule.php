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
	 * Bootstrap M3 Checkout & Orders Module.
	 *
	 * Safe to call repeatedly; prevents duplicate initialization and guards
	 * against missing WooCommerce runtime without causing fatal errors.
	 */
	public static function init(): void {
		if ( self::$initialized ) {
			return;
		}

		self::register_autoloader();

		if ( ! self::is_woocommerce_active() ) {
			return;
		}

		self::$initialized = true;

		CartHandler::init();
		CouponValidator::init();
		VietnamRegions::init();
		ShippingNoticeHelper::init();
		AddressValidator::init();
		OrderMetaSaver::init();
		CheckoutValidator::init();
		PaymentMethodValidator::init();
		PaymentStatusGuard::init();
		BacsInstructions::init();
		GuestOrderTracker::init();
		AccountProfileHandler::init();
		AccountAddressHandler::init();
		AccountDataPresenter::init();
		EmailCustomizer::init();
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
}

