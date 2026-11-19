<?php
/**
 * DeSpace REST API Module.
 *
 * Exposes standardized endpoints for product filtering, 3D model metadata,
 * and inventory traceability under namespace /despace/v1.
 *
 * @package DeSpace\Rest
 */

namespace DeSpace\Rest;

use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use DeSpace\Product3D\Product3DModule;
use DeSpace\Security\RoleManager;

defined( 'ABSPATH' ) || exit;

/**
 * Class RestApiModule
 */
class RestApiModule {

	public const REST_NAMESPACE = 'despace/v1';

	/**
	 * Singleton instance.
	 *
	 * @var RestApiModule|null
	 */
	private static ?RestApiModule $instance = null;

	/**
	 * Initialize module.
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
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register custom routes.
	 */
	public function register_routes(): void {
		// 1. 3D Model Metadata endpoint
		register_rest_route(
			self::REST_NAMESPACE,
			'/3d-models/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_product_3d' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'id' => array(
						'validate_callback' => function( $param ) {
							return is_numeric( $param );
						},
					),
				),
			)
		);

		// 2. Product filter endpoint (and alias /catalog)
		$filter_args = array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'filter_products' ),
			'permission_callback' => '__return_true',
		);
		register_rest_route( self::REST_NAMESPACE, '/products/filter', $filter_args );
		register_rest_route( self::REST_NAMESPACE, '/catalog', $filter_args );
	}

	/**
	 * Retrieve 3D model metadata for a product.
	 */
	public function get_product_3d( WP_REST_Request $request ): WP_REST_Response {
		$product_id = absint( $request->get_param( 'id' ) );
		$info       = Product3DModule::get_product_3d_info( $product_id );

		return new WP_REST_Response( array(
			'product_id' => $product_id,
			'data'       => $info,
		), 200 );
	}

	/**
	 * Filter products REST handler implementing official API Contract (M2).
	 */
	public function filter_products( WP_REST_Request $request ): WP_REST_Response {
		global $wpdb;

		$keyword   = sanitize_text_field( $request->get_param( 'q' ) ?? $request->get_param( 'keyword' ) ?? $request->get_param( 's' ) ?? '' );
		$category  = sanitize_text_field( $request->get_param( 'category' ) ?? '' );
		$material  = sanitize_text_field( $request->get_param( 'material' ) ?? '' );
		$brand     = sanitize_text_field( $request->get_param( 'brand' ) ?? '' );
		$color     = sanitize_text_field( $request->get_param( 'color' ) ?? '' );
		$size      = sanitize_text_field( $request->get_param( 'size' ) ?? '' );
		$min_price = floatval( $request->get_param( 'min_price' ) ?? 0 );
		$max_price = floatval( $request->get_param( 'max_price' ) ?? 0 );
		$has_3d    = rest_sanitize_boolean( $request->get_param( 'has_3d' ) ?? false );
		$on_sale   = rest_sanitize_boolean( $request->get_param( 'on_sale' ) ?? $request->get_param( 'onsale' ) ?? false );
		$orderby   = sanitize_text_field( $request->get_param( 'orderby' ) ?? $request->get_param( 'sort' ) ?? 'date' );
		$order     = strtoupper( sanitize_text_field( $request->get_param( 'order' ) ?? 'DESC' ) );
		if ( ! in_array( $order, array( 'ASC', 'DESC' ), true ) ) {
			$order = 'DESC';
		}
		$page      = max( 1, absint( $request->get_param( 'page' ) ?? 1 ) );
		$per_page  = min( 50, max( 1, absint( $request->get_param( 'per_page' ) ?? 12 ) ) );

		$tax_query  = array( 'relation' => 'AND' );
		$meta_query = array( 'relation' => 'AND' );
		$post__in   = null;

		if ( $category ) {
			$field = is_numeric( $category ) ? 'term_id' : 'slug';
			$tax_query[] = array(
				'taxonomy' => 'product_cat',
				'field'    => $field,
				'terms'    => $category,
			);
		}

		if ( $material ) {
			$field = is_numeric( $material ) ? 'term_id' : 'slug';
			$tax_query[] = array(
				'taxonomy' => 'pa_material',
				'field'    => $field,
				'terms'    => $material,
			);
		}

		if ( $brand ) {
			$tax_query[] = array(
				'taxonomy' => 'pa_brand',
				'field'    => is_numeric( $brand ) ? 'term_id' : 'slug',
				'terms'    => $brand,
			);
		}

		if ( $color ) {
			$tax_query[] = array(
				'taxonomy' => 'pa_color',
				'field'    => is_numeric( $color ) ? 'term_id' : 'slug',
				'terms'    => $color,
			);
		}

		if ( $size ) {
			$tax_query[] = array(
				'taxonomy' => 'pa_size',
				'field'    => is_numeric( $size ) ? 'term_id' : 'slug',
				'terms'    => $size,
			);
		}

		if ( $has_3d ) {
			$meta_query[] = array(
				'key'     => '_despace_glb_attachment_id',
				'value'   => 0,
				'compare' => '>',
				'type'    => 'NUMERIC',
			);
		}

		if ( $min_price > 0 || $max_price > 0 ) {
			$price_query = array( 'key' => '_price', 'type' => 'NUMERIC' );
			if ( $min_price > 0 && $max_price > 0 ) {
				$price_query['value']   = array( $min_price, $max_price );
				$price_query['compare'] = 'BETWEEN';
			} elseif ( $min_price > 0 ) {
				$price_query['value']   = $min_price;
				$price_query['compare'] = '>=';
			} else {
				$price_query['value']   = $max_price;
				$price_query['compare'] = '<=';
			}
			$meta_query[] = $price_query;
		}

		// Search keyword matching SKU (including variation SKU mapped to parent) and title
		if ( ! empty( $keyword ) ) {
			$sku_product_ids = $wpdb->get_col( $wpdb->prepare(
				"SELECT DISTINCT CASE WHEN p.post_type = 'product_variation' THEN p.post_parent ELSE p.ID END AS product_id
				FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
				WHERE pm.meta_key = '_sku' AND pm.meta_value LIKE %s",
				'%' . $wpdb->esc_like( $keyword ) . '%'
			) );

			$title_query = new \WP_Query( array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => 200,
				'fields'         => 'ids',
				's'              => $keyword,
			) );

			$matched_ids = array_map( 'intval', array_unique( array_merge( (array) $sku_product_ids, (array) $title_query->posts ) ) );
			$matched_ids = array_values( array_filter( $matched_ids, function( $id ) { return $id > 0; } ) );
			$post__in    = ! empty( $matched_ids ) ? $matched_ids : array( 0 );
		}

		// Intersect on_sale filter
		if ( $on_sale ) {
			$sale_ids = wc_get_product_ids_on_sale();
			if ( empty( $sale_ids ) ) {
				$sale_ids = array( 0 );
			}
			if ( null !== $post__in ) {
				$intersected = array_values( array_intersect( $post__in, $sale_ids ) );
				$post__in    = ! empty( $intersected ) ? $intersected : array( 0 );
			} else {
				$post__in = $sale_ids;
			}
		}

		$args = array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'paged'          => $page,
		);

		if ( ! empty( $tax_query ) ) {
			$args['tax_query'] = $tax_query;
		}

		if ( ! empty( $meta_query ) ) {
			$args['meta_query'] = $meta_query;
		}

		if ( null !== $post__in ) {
			$args['post__in'] = $post__in;
		}

		switch ( $orderby ) {
			case 'price':
			case 'price-asc':
				$args['orderby']  = 'meta_value_num';
				$args['meta_key'] = '_price';
				$args['order']    = 'ASC';
				break;
			case 'price-desc':
				$args['orderby']  = 'meta_value_num';
				$args['meta_key'] = '_price';
				$args['order']    = 'DESC';
				break;
			case 'popularity':
				$args['orderby']  = 'meta_value_num';
				$args['meta_key'] = 'total_sales';
				$args['order']    = 'DESC';
				break;
			case 'rating':
				$args['orderby']  = 'meta_value_num';
				$args['meta_key'] = '_wc_average_rating';
				$args['order']    = 'DESC';
				break;
			case 'title':
				$args['orderby'] = 'title';
				$args['order']   = $order;
				break;
			case 'date':
			default:
				$args['orderby'] = 'date';
				$args['order']   = $order;
				break;
		}

		$query = new \WP_Query( $args );
		$items = array();

		foreach ( $query->posts as $post ) {
			$prod = wc_get_product( $post->ID );
			if ( ! $prod ) continue;

			$glb_id = (int) get_post_meta( $prod->get_id(), '_despace_glb_attachment_id', true );
			if ( ! $glb_id && function_exists( 'ax_get_product_3d_info' ) ) {
				$m_info = ax_get_product_3d_info( $prod->get_id() );
				$glb_id = $m_info['has_3d'] ? 1 : 0;
			}
			$has_prod_3d = $glb_id > 0;
			$enable_ar   = 'yes' === get_post_meta( $prod->get_id(), '_despace_enable_ar', true ) || $has_prod_3d;

			$categories_data = array();
			$term_ids = $prod->get_category_ids();
			foreach ( $term_ids as $tid ) {
				$term = get_term( $tid, 'product_cat' );
				if ( $term && ! is_wp_error( $term ) ) {
					$categories_data[] = array(
						'id'   => $term->term_id,
						'name' => $term->name,
						'slug' => $term->slug,
					);
				}
			}

			$items[] = array(
				'id'                => $prod->get_id(),
				'name'              => $prod->get_name(),
				'slug'              => $prod->get_slug(),
				'permalink'         => $prod->get_permalink(),
				'sku'               => $prod->get_sku(),
				'price'             => (float) $prod->get_price(),
				'regular_price'     => (float) $prod->get_regular_price(),
				'sale_price'        => (float) $prod->get_sale_price(),
				'price_html'        => $prod->get_price_html(),
				'stock_status'      => $prod->get_stock_status(),
				'categories'        => $categories_data,
				'thumbnail_url'     => wp_get_attachment_image_url( $prod->get_image_id(), 'medium' ) ?: '',
				'image_url'         => wp_get_attachment_image_url( $prod->get_image_id(), 'medium' ) ?: '',
				'has_3d'            => $has_prod_3d,
				'glb_attachment_id' => $glb_id,
				'enable_ar'         => $enable_ar,
			);
		}

		$total_items = (int) $query->found_posts;
		$total_pages = $per_page > 0 ? (int) ceil( $total_items / $per_page ) : 1;

		$response_data = array(
			'success'         => true,
			'items'           => $items,
			'pagination'      => array(
				'page'        => $page,
				'per_page'    => $per_page,
				'total_items' => $total_items,
				'total_pages' => $total_pages,
			),
			'applied_filters' => array(
				'keyword'   => $keyword,
				'category'  => $category,
				'material'  => $material,
				'has_3d'    => $has_3d,
				'min_price' => $min_price,
				'max_price' => $max_price,
				'on_sale'   => $on_sale,
			),
			// Backward compatibility fields
			'found'           => $total_items,
			'results'         => $items,
		);

		return new WP_REST_Response( $response_data, 200 );
	}
}
