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
	 * Parse a GET filter parameter that may be an array or comma-separated string.
	 *
	 * @param mixed $raw Raw GET value.
	 * @return array<string>
	 */
	private function parse_slug_list( $raw ): array {
		if ( empty( $raw ) ) {
			return array();
		}
		$items = is_array( $raw ) ? $raw : explode( ',', (string) wp_unslash( $raw ) );
		$clean = array();
		foreach ( $items as $item ) {
			$slug = sanitize_title( trim( (string) $item ) );
			if ( '' !== $slug ) {
				$clean[] = $slug;
			}
		}
		return array_values( array_unique( $clean ) );
	}

	/**
	 * Optimize main shop query parameters and support URL/no-JS catalog filters.
	 */
	public function optimize_catalog_queries( $query ): void {
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}

		// Ensure proper post types and statuses
		$query->set( 'post_type', 'product' );
		$query->set( 'post_status', 'publish' );

		$tax_query  = (array) $query->get( 'tax_query' );
		$meta_query = (array) $query->get( 'meta_query' );

		// Category filter
		$cats = $this->parse_slug_list( $_GET['category'] ?? '' );
		if ( ! empty( $cats ) ) {
			$tax_query[] = array(
				'taxonomy' => 'product_cat',
				'field'    => 'slug',
				'terms'    => $cats,
			);
		}

		// Attribute & brand taxonomy filters (brand / color / size / material)
		$attr_map = array(
			'brand'    => 'product_brand',
			'color'    => 'pa_color',
			'size'     => 'pa_size',
			'material' => 'pa_material',
		);
		foreach ( $attr_map as $param => $taxonomy ) {
			if ( ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}
			$terms = $this->parse_slug_list( $_GET[ $param ] ?? '' );
			if ( ! empty( $terms ) ) {
				$tax_query[] = array(
					'taxonomy' => $taxonomy,
					'field'    => 'slug',
					'terms'    => $terms,
				);
			}
		}

		// Availability filter (instock, onsale)
		$availability = $this->parse_slug_list( $_GET['availability'] ?? '' );
		if ( in_array( 'instock', $availability, true ) ) {
			$meta_query[] = array(
				'key'   => '_stock_status',
				'value' => 'instock',
			);
		}

		// On sale filter
		$on_sale_param = isset( $_GET['on_sale'] ) ? sanitize_text_field( (string) wp_unslash( $_GET['on_sale'] ) ) : '';
		if ( in_array( 'onsale', $availability, true ) || in_array( strtolower( $on_sale_param ), array( '1', 'true', 'yes' ), true ) ) {
			$sale_ids = function_exists( 'wc_get_product_ids_on_sale' ) ? wc_get_product_ids_on_sale() : array();
			$sale_ids = ! empty( $sale_ids ) ? array_map( 'intval', $sale_ids ) : array( 0 );
			$existing = (array) $query->get( 'post__in' );
			if ( ! empty( $existing ) ) {
				$intersect = array_values( array_intersect( array_map( 'intval', $existing ), $sale_ids ) );
				$query->set( 'post__in', ! empty( $intersect ) ? $intersect : array( 0 ) );
			} else {
				$query->set( 'post__in', $sale_ids );
			}
		}

		// 3D / AR filter
		$has_3d_param = isset( $_GET['has_3d'] ) ? sanitize_text_field( (string) wp_unslash( $_GET['has_3d'] ) ) : '';
		if ( in_array( strtolower( $has_3d_param ), array( '1', 'true', 'yes' ), true ) ) {
			$meta_query[] = array(
				'key'     => '_despace_glb_attachment_id',
				'value'   => 0,
				'compare' => '>',
				'type'    => 'NUMERIC',
			);
		}

		// Price range filter
		$raw_min = $_GET['price_min'] ?? $_GET['min_price'] ?? '';
		$raw_max = $_GET['price_max'] ?? $_GET['max_price'] ?? '';
		$min     = ( '' !== $raw_min && is_numeric( $raw_min ) ) ? max( 0, (float) $raw_min ) : null;
		$max     = ( '' !== $raw_max && is_numeric( $raw_max ) ) ? max( 0, (float) $raw_max ) : null;
		if ( null !== $min && null !== $max && $min > $max ) {
			$tmp = $min;
			$min = $max;
			$max = $tmp;
		}
		if ( null !== $min || null !== $max ) {
			$price_clause = array( 'relation' => 'AND' );
			if ( null !== $min ) {
				$price_clause[] = array(
					'key'     => '_price',
					'value'   => $min,
					'compare' => '>=',
					'type'    => 'NUMERIC',
				);
			}
			if ( null !== $max ) {
				$price_clause[] = array(
					'key'     => '_price',
					'value'   => $max,
					'compare' => '<=',
					'type'    => 'NUMERIC',
				);
			}
			$meta_query[] = $price_clause;
		}

		if ( ! empty( $tax_query ) ) {
			$query->set( 'tax_query', $tax_query );
		}
		if ( ! empty( $meta_query ) ) {
			$query->set( 'meta_query', $meta_query );
		}
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
