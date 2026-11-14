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

		// 2. Product filter endpoint
		register_rest_route(
			self::REST_NAMESPACE,
			'/products/filter',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'filter_products' ),
				'permission_callback' => '__return_true',
			)
		);
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
	 * Filter products REST handler.
	 */
	public function filter_products( WP_REST_Request $request ): WP_REST_Response {
		$keyword   = sanitize_text_field( $request->get_param( 'keyword' ) ?? '' );
		$category  = sanitize_text_field( $request->get_param( 'category' ) ?? '' );
		$min_price = floatval( $request->get_param( 'min_price' ) ?? 0 );
		$max_price = floatval( $request->get_param( 'max_price' ) ?? 0 );

		$args = array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => 20,
		);

		if ( $keyword ) {
			$args['s'] = $keyword;
		}

		if ( $category ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => 'product_cat',
					'field'    => 'slug',
					'terms'    => $category,
				),
			);
		}

		if ( $min_price > 0 || $max_price > 0 ) {
			$args['meta_query'] = array(
				array(
					'key'     => '_price',
					'value'   => array( $min_price, $max_price ?: 999999 ),
					'type'    => 'NUMERIC',
					'compare' => 'BETWEEN',
				),
			);
		}

		$query = new \WP_Query( $args );
		$results = array();

		foreach ( $query->posts as $post ) {
			$prod = wc_get_product( $post->ID );
			if ( $prod ) {
				$results[] = array(
					'id'    => $prod->get_id(),
					'name'  => $prod->get_name(),
					'price' => $prod->get_price(),
					'sku'   => $prod->get_sku(),
					'link'  => $prod->get_permalink(),
					'image' => wp_get_attachment_image_url( $prod->get_image_id(), 'medium' ),
				);
			}
		}

		return new WP_REST_Response( array(
			'found'   => $query->found_posts,
			'results' => $results,
		), 200 );
	}
}
