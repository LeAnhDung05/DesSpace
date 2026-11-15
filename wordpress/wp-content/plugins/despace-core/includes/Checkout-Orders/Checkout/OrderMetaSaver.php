<?php
/**
 * HPOS-Safe DeSpace Order Address Metadata Saver (M3 Phase 2A / Issue #17).
 *
 * Persists canonical 2-tier Vietnam administrative metadata onto WooCommerce orders
 * exclusively through WooCommerce CRUD (`$order->update_meta_data()`, `$order->get_meta()`,
 * `$order->save()`). Strictly compatible with High-Performance Order Storage (HPOS).
 * Never uses legacy postmeta helpers, raw SQL, or M4 inventory metadata keys.
 *
 * @package DeSpace\CheckoutOrders\Checkout
 */

namespace DeSpace\CheckoutOrders\Checkout;

defined( 'ABSPATH' ) || exit;

/**
 * Class OrderMetaSaver
 */
class OrderMetaSaver {

	public const META_SHIPPING_REGION_CODE  = '_shipping_despace_region_code';
	public const META_SHIPPING_COMMUNE_CODE = '_shipping_despace_commune_code';
	public const META_SHIPPING_REGION_NAME  = '_shipping_despace_region_name';
	public const META_SHIPPING_COMMUNE_NAME = '_shipping_despace_commune_name';

	public const META_BILLING_REGION_CODE   = '_billing_despace_region_code';
	public const META_BILLING_COMMUNE_CODE  = '_billing_despace_commune_code';
	public const META_BILLING_REGION_NAME   = '_billing_despace_region_name';
	public const META_BILLING_COMMUNE_NAME  = '_billing_despace_commune_name';

	public const META_ADDRESS_VERIFICATION  = '_despace_address_verification';

	/**
	 * Prevents duplicate hook registration.
	 *
	 * @var bool
	 */
	private static bool $hooks_registered = false;

	/**
	 * Attach HPOS-safe checkout order creation hook.
	 */
	public static function init(): void {
		if ( self::$hooks_registered ) {
			return;
		}

		self::$hooks_registered = true;

		add_action( 'woocommerce_checkout_create_order', array( __CLASS__, 'attach_order_metadata_on_create' ), 20, 2 );
	}

	/**
	 * Check whether hooks have been registered.
	 *
	 * @return bool
	 */
	public static function are_hooks_registered(): bool {
		return self::$hooks_registered;
	}

	/**
	 * Hook callback for `woocommerce_checkout_create_order`.
	 *
	 * Runs before WooCommerce invokes `$order->save()`, so metadata is persisted atomically
	 * in WooCommerce's single order save transaction without extra queries or stock interference.
	 *
	 * @param object $order Newly created WC_Order instance.
	 * @param array  $data  Posted checkout data.
	 */
	public static function attach_order_metadata_on_create( object $order, array $data ): void {
		$validated = AddressValidator::validate_checkout_addresses( $data );
		if ( is_wp_error( $validated ) ) {
			return;
		}

		self::sync_canonical_order_address_columns( $order, $validated );
		self::save_order_metadata( $order, $validated, false );
	}

	/**
	 * Persist sanitized DeSpace administrative metadata onto an HPOS-compatible order object.
	 *
	 * Only writes non-empty sanitized values; leaves legacy `address_2` and unrelated order meta untouched.
	 *
	 * @param object $order               WC_Order instance supporting CRUD meta methods.
	 * @param array  $validated_addresses Output from AddressValidator::validate_checkout_addresses().
	 * @param bool   $persist             Whether to call `$order->save()` immediately (false inside create_order hook).
	 * @return bool True when metadata was written via WooCommerce CRUD, false if order API missing.
	 */
	public static function save_order_metadata(
		object $order,
		array $validated_addresses,
		bool $persist = false
	): bool {
		if ( ! method_exists( $order, 'update_meta_data' ) ) {
			return false;
		}

		$billing  = is_array( $validated_addresses['billing'] ?? null ) ? $validated_addresses['billing'] : array();
		$shipping = is_array( $validated_addresses['shipping'] ?? null ) ? $validated_addresses['shipping'] : $billing;

		$meta_map = array(
			self::META_BILLING_REGION_CODE   => self::sanitize_code( (string) ( $billing['region_code'] ?? '' ) ),
			self::META_BILLING_COMMUNE_CODE  => self::sanitize_code( (string) ( $billing['commune_code'] ?? '' ) ),
			self::META_BILLING_REGION_NAME   => self::sanitize_text( (string) ( $billing['region_name'] ?? '' ) ),
			self::META_BILLING_COMMUNE_NAME  => self::sanitize_text( (string) ( $billing['commune_name'] ?? '' ) ),
			self::META_SHIPPING_REGION_CODE  => self::sanitize_code( (string) ( $shipping['region_code'] ?? '' ) ),
			self::META_SHIPPING_COMMUNE_CODE => self::sanitize_code( (string) ( $shipping['commune_code'] ?? '' ) ),
			self::META_SHIPPING_REGION_NAME  => self::sanitize_text( (string) ( $shipping['region_name'] ?? '' ) ),
			self::META_SHIPPING_COMMUNE_NAME => self::sanitize_text( (string) ( $shipping['commune_name'] ?? '' ) ),
			self::META_ADDRESS_VERIFICATION  => self::sanitize_text( (string) ( $shipping['verification_mode'] ?? ( $billing['verification_mode'] ?? '' ) ) ),
		);

		foreach ( $meta_map as $meta_key => $meta_value ) {
			if ( '' !== $meta_value ) {
				$order->update_meta_data( $meta_key, $meta_value );
			}
		}

		if ( $persist && method_exists( $order, 'save' ) ) {
			$order->save();
		}

		return true;
	}

	/**
	 * Read DeSpace address metadata cleanly from an order via HPOS-compatible `$order->get_meta()`.
	 *
	 * Never accesses legacy postmeta tables or modifies historical order fields (`address_2` preserved).
	 *
	 * @param object $order WC_Order instance.
	 * @return array
	 */
	public static function read_order_metadata( object $order ): array {
		if ( ! method_exists( $order, 'get_meta' ) ) {
			return array();
		}

		return array(
			'billing'  => array(
				'region_code'  => (string) $order->get_meta( self::META_BILLING_REGION_CODE, true ),
				'commune_code' => (string) $order->get_meta( self::META_BILLING_COMMUNE_CODE, true ),
				'region_name'  => (string) $order->get_meta( self::META_BILLING_REGION_NAME, true ),
				'commune_name' => (string) $order->get_meta( self::META_BILLING_COMMUNE_NAME, true ),
			),
			'shipping' => array(
				'region_code'  => (string) $order->get_meta( self::META_SHIPPING_REGION_CODE, true ),
				'commune_code' => (string) $order->get_meta( self::META_SHIPPING_COMMUNE_CODE, true ),
				'region_name'  => (string) $order->get_meta( self::META_SHIPPING_REGION_NAME, true ),
				'commune_name' => (string) $order->get_meta( self::META_SHIPPING_COMMUNE_NAME, true ),
			),
			'verification_mode' => (string) $order->get_meta( self::META_ADDRESS_VERIFICATION, true ),
		);
	}

	/**
	 * Synchronize WooCommerce core state/city/address_1 setters on newly created order.
	 *
	 * Preserves any legacy address_2 line untouched.
	 *
	 * @param object $order               WC_Order instance.
	 * @param array  $validated_addresses Normalized billing & shipping structures.
	 */
	private static function sync_canonical_order_address_columns( object $order, array $validated_addresses ): void {
		$billing  = $validated_addresses['billing']['wc_mapping'] ?? array();
		$shipping = $validated_addresses['shipping']['wc_mapping'] ?? $billing;

		if ( ! empty( $billing ) ) {
			if ( method_exists( $order, 'set_billing_country' ) ) {
				$order->set_billing_country( (string) ( $billing['country'] ?? 'VN' ) );
			}
			if ( method_exists( $order, 'set_billing_state' ) ) {
				$order->set_billing_state( (string) ( $billing['state'] ?? '' ) );
			}
			if ( method_exists( $order, 'set_billing_city' ) ) {
				$order->set_billing_city( (string) ( $billing['city'] ?? '' ) );
			}
			if ( method_exists( $order, 'set_billing_address_1' ) ) {
				$order->set_billing_address_1( (string) ( $billing['address_1'] ?? '' ) );
			}
		}

		if ( ! empty( $shipping ) ) {
			if ( method_exists( $order, 'set_shipping_country' ) ) {
				$order->set_shipping_country( (string) ( $shipping['country'] ?? 'VN' ) );
			}
			if ( method_exists( $order, 'set_shipping_state' ) ) {
				$order->set_shipping_state( (string) ( $shipping['state'] ?? '' ) );
			}
			if ( method_exists( $order, 'set_shipping_city' ) ) {
				$order->set_shipping_city( (string) ( $shipping['city'] ?? '' ) );
			}
			if ( method_exists( $order, 'set_shipping_address_1' ) ) {
				$order->set_shipping_address_1( (string) ( $shipping['address_1'] ?? '' ) );
			}
		}
	}

	/**
	 * Sanitize alphanumeric code preserving leading zeros.
	 *
	 * @param string $code Raw code.
	 * @return string
	 */
	private static function sanitize_code( string $code ): string {
		$clean = self::sanitize_text( $code );
		return preg_replace( '/[^A-Za-z0-9_\-]/', '', $clean ) ?? '';
	}

	/**
	 * Sanitize UTF-8 Vietnamese text cleanly.
	 *
	 * @param string $text Raw string.
	 * @return string
	 */
	private static function sanitize_text( string $text ): string {
		if ( function_exists( 'sanitize_text_field' ) ) {
			return sanitize_text_field( $text );
		}
		return trim( strip_tags( $text ) );
	}
}
