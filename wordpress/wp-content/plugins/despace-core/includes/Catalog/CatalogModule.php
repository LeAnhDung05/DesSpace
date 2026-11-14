<?php
/**
 * DeSpace Catalog and Product Filter Extensions Module.
 *
 * Provides query optimization, multi-attribute filter aggregation,
 * and catalog utilities for DeSpace luxury furniture store.
 *
 * @package DeSpace\Catalog
 */

namespace DeSpace\Catalog;

defined( 'ABSPATH' ) || exit;

/**
 * Class CatalogModule
 */
class CatalogModule {

	/**
	 * Singleton instance.
	 *
	 * @var CatalogModule|null
	 */
	private static ?CatalogModule $instance = null;

	/**
	 * Initialize module hooks.
	 */
	public static function init(): void {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'woocommerce_product_query', array( $this, 'optimize_catalog_queries' ) );
		add_filter( 'woocommerce_get_catalog_ordering_args', array( $this, 'custom_catalog_ordering' ) );
	}

	/**
	 * Optimize main shop query parameters.
	 */
	public function optimize_catalog_queries( $query ): void {
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}

		// Ensure proper post types and statuses
		$query->set( 'post_type', 'product' );
		$query->set( 'post_status', 'publish' );
	}

	/**
	 * Custom ordering options.
	 */
	public function custom_catalog_ordering( array $args ): array {
		$orderby_value = isset( $_GET['orderby'] ) ? wc_clean( (string) wp_unslash( $_GET['orderby'] ) ) : '';

		if ( 'furniture_new' === $orderby_value ) {
			$args['orderby'] = 'date';
			$args['order']   = 'DESC';
		} elseif ( 'price_asc' === $orderby_value ) {
			$args['orderby']  = 'meta_value_num';
			$args['order']    = 'ASC';
			$args['meta_key'] = '_price';
		} elseif ( 'price_desc' === $orderby_value ) {
			$args['orderby']  = 'meta_value_num';
			$args['order']    = 'DESC';
			$args['meta_key'] = '_price';
		}

		return $args;
	}
}
