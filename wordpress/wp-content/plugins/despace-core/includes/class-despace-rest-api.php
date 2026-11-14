<?php
/**
 * REST API Endpoints for DeSpace.
 * Strictly adheres to DeSpace SADD v1.2 Sections 4.3.4 and 4.3.5.
 *
 * @package DeSpace_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DeSpace_REST_API {

	const REST_NAMESPACE = 'despace/v1';

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register custom REST routes.
	 */
	public function register_routes() {
		// Route 1: Filter products with multi-criteria (keyword, category, price range, material, has_3d)
		register_rest_route(
			self::REST_NAMESPACE,
			'/products/filter',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'filter_products' ),
				'permission_callback' => '__return_true', // Public endpoint
				'args'                => array(
					'keyword'   => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'category'  => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'min_price' => array(
						'type'              => 'number',
						'sanitize_callback' => 'floatval',
					),
					'max_price' => array(
						'type'              => 'number',
						'sanitize_callback' => 'floatval',
					),
					'material'  => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'has_3d'    => array(
						'type'              => 'boolean',
						'sanitize_callback' => 'rest_sanitize_boolean',
					),
					'page'      => array(
						'type'              => 'integer',
						'default'           => 1,
						'sanitize_callback' => 'absint',
					),
					'per_page'  => array(
						'type'              => 'integer',
						'default'           => 12,
						'sanitize_callback' => function( $val ) {
							$v = absint( $val );
							return ( $v > 0 && $v <= 50 ) ? $v : 12;
						},
					),
					'sort'      => array(
						'type'              => 'string',
						'default'           => 'date_desc',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// Route 2: Get 3D Model metadata for a single product
		register_rest_route(
			self::REST_NAMESPACE,
			'/products/(?P<id>\d+)/model-3d',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_product_3d_model' ),
				'permission_callback' => '__return_true', // Public endpoint
				'args'                => array(
					'id' => array(
						'validate_callback' => function( $param ) {
							return is_numeric( $param ) && $param > 0;
						},
					),
				),
			)
		);
	}

	/**
	 * Callback: Filter Products Endpoint.
	 */
	public function filter_products( WP_REST_Request $request ) {
		$keyword   = $request->get_param( 'keyword' );
		$category  = $request->get_param( 'category' );
		$min_price = $request->get_param( 'min_price' );
		$max_price = $request->get_param( 'max_price' );
		$material  = $request->get_param( 'material' );
		$has_3d    = $request->get_param( 'has_3d' );
		$page      = max( 1, absint( $request->get_param( 'page' ) ) );
		$per_page  = absint( $request->get_param( 'per_page' ) );
		$sort      = $request->get_param( 'sort' );

		// Query building using WP_Query for reliable meta_query and tax_query support
		$query_args = array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'paged'          => $page,
		);

		// Keyword search
		if ( ! empty( $keyword ) ) {
			$query_args['s'] = $keyword;
		}

		// Category filter
		if ( ! empty( $category ) ) {
			$tax_query = array();
			if ( is_numeric( $category ) ) {
				$tax_query[] = array(
					'taxonomy' => 'product_cat',
					'field'    => 'term_id',
					'terms'    => absint( $category ),
				);
			} else {
				$tax_query[] = array(
					'taxonomy' => 'product_cat',
					'field'    => 'slug',
					'terms'    => sanitize_title( $category ),
				);
			}
			$query_args['tax_query'] = $tax_query;
		}

		$meta_queries = array();

		// Price Range Filter at DB Level
		if ( null !== $min_price && $min_price > 0 && null !== $max_price && $max_price > 0 ) {
			$meta_queries[] = array(
				'key'     => '_price',
				'value'   => array( $min_price, $max_price ),
				'compare' => 'BETWEEN',
				'type'    => 'NUMERIC',
			);
		} elseif ( null !== $min_price && $min_price > 0 ) {
			$meta_queries[] = array(
				'key'     => '_price',
				'value'   => $min_price,
				'compare' => '>=',
				'type'    => 'NUMERIC',
			);
		} elseif ( null !== $max_price && $max_price > 0 ) {
			$meta_queries[] = array(
				'key'     => '_price',
				'value'   => $max_price,
				'compare' => '<=',
				'type'    => 'NUMERIC',
			);
		}

		// 3D Model filter at DB Level
		if ( true === $has_3d ) {
			$meta_queries[] = array(
				'relation' => 'OR',
				array(
					'key'     => '_despace_glb_attachment_id',
					'value'   => 0,
					'compare' => '>',
					'type'    => 'NUMERIC',
				),
				array(
					'key'     => '_despace_glb_url',
					'value'   => '',
					'compare' => '!=',
				),
				array(
					'key'     => '_axgnud_model_glb',
					'value'   => '',
					'compare' => '!=',
				),
			);
		}

		if ( ! empty( $meta_queries ) ) {
			if ( count( $meta_queries ) > 1 ) {
				$meta_queries['relation'] = 'AND';
			}
			$query_args['meta_query'] = $meta_queries;
		}

		// Sorting
		switch ( $sort ) {
			case 'price_asc':
				$query_args['orderby']  = 'meta_value_num';
				$query_args['meta_key'] = '_price';
				$query_args['order']    = 'ASC';
				break;
			case 'price_desc':
				$query_args['orderby']  = 'meta_value_num';
				$query_args['meta_key'] = '_price';
				$query_args['order']    = 'DESC';
				break;
			case 'popularity':
				$query_args['orderby']  = 'meta_value_num';
				$query_args['meta_key'] = 'total_sales';
				$query_args['order']    = 'DESC';
				break;
			case 'rating':
				$query_args['orderby']  = 'meta_value_num';
				$query_args['meta_key'] = '_wc_average_rating';
				$query_args['order']    = 'DESC';
				break;
			case 'date_asc':
				$query_args['orderby']  = 'date';
				$query_args['order']    = 'ASC';
				break;
			default:
				$query_args['orderby']  = 'date';
				$query_args['order']    = 'DESC';
				break;
		}

		$wp_query = new WP_Query( $query_args );
		$total_items = $wp_query->found_posts;
		$total_pages = $wp_query->max_num_pages;

		$items = array();

		foreach ( $wp_query->posts as $post_obj ) {
			$product = wc_get_product( $post_obj->ID );
			if ( ! $product instanceof WC_Product ) {
				continue;
			}

			$product_id = $product->get_id();
			$price_num  = floatval( $product->get_price() );

			// Filter min_price / max_price
			if ( null !== $min_price && $min_price > 0 && $price_num < $min_price ) {
				continue;
			}
			if ( null !== $max_price && $max_price > 0 && $price_num > $max_price ) {
				continue;
			}

			// 3D Metadata
			$data_3d = DeSpace_3D::get_product_3d_data( $product_id );

			// Filter has_3d
			if ( true === $has_3d && ! $data_3d['has_3d'] ) {
				continue;
			}

			// Categories
			$cat_terms = get_the_terms( $product_id, 'product_cat' );
			$categories = array();
			if ( ! empty( $cat_terms ) && ! is_wp_error( $cat_terms ) ) {
				foreach ( $cat_terms as $cat ) {
					$categories[] = array(
						'id'   => $cat->term_id,
						'name' => $cat->name,
						'slug' => $cat->slug,
					);
				}
			}

			// Primary image
			$image_id  = $product->get_image_id();
			$image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'woocommerce_thumbnail' ) : wc_placeholder_img_src();

			$items[] = array(
				'id'            => $product_id,
				'name'          => html_entity_decode( $product->get_name(), ENT_QUOTES, 'UTF-8' ),
				'sku'           => $product->get_sku() ?: 'DS-' . $product_id,
				'price'         => $product->get_price(),
				'regular_price' => $product->get_regular_price(),
				'sale_price'    => $product->get_sale_price(),
				'price_html'    => $product->get_price_html(),
				'currency'      => get_woocommerce_currency(),
				'stock_status'  => $product->get_stock_status(),
				'is_in_stock'   => $product->is_in_stock(),
				'permalink'     => get_permalink( $product_id ),
				'image_url'     => $image_url,
				'has_3d'        => $data_3d['has_3d'],
				'model_url'     => $data_3d['model_url'],
				'poster_url'    => $data_3d['poster_url'],
				'enable_ar'     => $data_3d['enable_ar'],
				'categories'    => $categories,
			);
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => array(
					'items'      => $items,
					'pagination' => array(
						'page'        => $page,
						'per_page'    => $per_page,
						'total_items' => $total_items,
						'total_pages' => $total_pages,
					),
				),
			),
			200
		);
	}

	/**
	 * Callback: Single Product 3D Configuration Endpoint.
	 */
	public function get_product_3d_model( WP_REST_Request $request ) {
		$product_id = absint( $request->get_param( 'id' ) );
		$product    = wc_get_product( $product_id );

		if ( ! $product || 'publish' !== $product->get_status() ) {
			return new WP_Error(
				'despace_product_not_found',
				__( 'Sản phẩm không tồn tại hoặc không ở trạng thái công khai.', 'despace-core' ),
				array( 'status' => 404 )
			);
		}

		$data_3d = DeSpace_3D::get_product_3d_data( $product_id );

		if ( ! $data_3d['has_3d'] ) {
			return new WP_Error(
				'despace_no_3d_model',
				__( 'Sản phẩm này chưa được cấu hình mô hình 3D.', 'despace-core' ),
				array( 'status' => 404 )
			);
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => array(
					'product_id'   => $product_id,
					'product_name' => html_entity_decode( $product->get_name(), ENT_QUOTES, 'UTF-8' ),
					'model_url'    => $data_3d['model_url'],
					'poster_url'   => $data_3d['poster_url'],
					'enable_ar'    => $data_3d['enable_ar'],
				),
			),
			200
		);
	}
}
