<?php
/**
 * DeSpace REST API Module.
 *
 * Exposes standardized endpoints for product filtering, 3D model metadata,
 * reports, and desktop 3D studio synchronization under namespace /despace/v1.
 *
 * @package DeSpace\Rest
 */

namespace DeSpace\Rest;

use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use DeSpace\Product3D\Product3DModule;
use DeSpace\Product3D\Desktop3DSyncModule;
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
		// 1. 3D Model Metadata endpoints (Legacy & Contract routes)
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

		register_rest_route(
			self::REST_NAMESPACE,
			'/products/(?P<id>\d+)/3d',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_product_3d' ),
					'permission_callback' => '__return_true',
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'update_product_3d' ),
					'permission_callback' => array( RoleManager::class, 'rest_check_3d_permission' ),
				),
			)
		);

		// 2. Product filter & Catalog endpoints
		register_rest_route(
			self::REST_NAMESPACE,
			'/products/filter',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'filter_products' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/catalog',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'filter_products' ),
				'permission_callback' => '__return_true',
			)
		);

		// 3. Reports endpoints (API Contract M5)
		register_rest_route(
			self::REST_NAMESPACE,
			'/reports/overview',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_reports_overview' ),
				'permission_callback' => array( RoleManager::class, 'rest_check_reports_permission' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/reports/top-products',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_reports_top_products' ),
				'permission_callback' => array( RoleManager::class, 'rest_check_reports_permission' ),
			)
		);

		// =================================================================
		// 4. DESKTOP 3D STUDIO SYNC ENDPOINTS (/desktop-3d/...)
		// =================================================================

		// 4.1 Health check
		register_rest_route(
			self::REST_NAMESPACE,
			'/desktop-3d/health',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'desktop_health_check' ),
				'permission_callback' => '__return_true',
			)
		);

		// 4.2 Dispatch new 3D sync job
		register_rest_route(
			self::REST_NAMESPACE,
			'/desktop-3d/jobs/dispatch',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'desktop_dispatch_job' ),
				'permission_callback' => array( $this, 'check_desktop_api_permission' ),
			)
		);

		// 4.3 List jobs (for desktop polling)
		register_rest_route(
			self::REST_NAMESPACE,
			'/desktop-3d/jobs',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'desktop_list_jobs' ),
				'permission_callback' => array( $this, 'check_desktop_api_permission' ),
			)
		);

		// 4.4 Get single job detail
		register_rest_route(
			self::REST_NAMESPACE,
			'/desktop-3d/jobs/(?P<uuid>[a-zA-Z0-9\-_]+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'desktop_get_job' ),
				'permission_callback' => array( $this, 'check_desktop_api_permission' ),
			)
		);

		// 4.5 Update job progress (step ticker)
		register_rest_route(
			self::REST_NAMESPACE,
			'/desktop-3d/jobs/(?P<uuid>[a-zA-Z0-9\-_]+)/progress',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'desktop_update_progress' ),
				'permission_callback' => array( $this, 'check_desktop_api_permission' ),
			)
		);

		// 4.6 Upload completed 3D model & metadata
		register_rest_route(
			self::REST_NAMESPACE,
			'/desktop-3d/jobs/(?P<uuid>[a-zA-Z0-9\-_]+)/upload',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'desktop_upload_completed_model' ),
				'permission_callback' => array( $this, 'check_desktop_api_permission' ),
			)
		);

		// 4.7 Excel batch registration & ingestion
		register_rest_route(
			self::REST_NAMESPACE,
			'/desktop-3d/batch-sync',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'desktop_batch_sync_excel' ),
				'permission_callback' => array( $this, 'check_desktop_api_permission' ),
			)
		);
	}

	/**
	 * Permission callback for desktop API calls.
	 *
	 * Requires either:
	 * 1. Authenticated WordPress user with 3D / WooCommerce management capabilities, OR
	 * 2. Valid X-DeSpace-Desktop-Key header matching stored option.
	 *
	 * @param WP_REST_Request $request
	 * @return true|WP_Error
	 */
	public function check_desktop_api_permission( WP_REST_Request $request ) {
		// 1. Role-based permission if logged in
		if ( class_exists( 'DeSpace\\Security\\RoleManager' ) && RoleManager::current_user_can_manage_3d() ) {
			return true;
		}

		if ( function_exists( 'current_user_can' ) && ( current_user_can( 'manage_woocommerce' ) || current_user_can( 'manage_despace_3d' ) ) ) {
			return true;
		}

		// 2. Secret Key Header verification (sync_client.py always sends X-DeSpace-Desktop-Key)
		$header_key = $request->get_header( 'x_despace_desktop_key' ) ?: $request->get_header( 'x-despace-desktop-key' );
		$stored_key = get_option( 'despace_desktop_sync_key', 'despace-desktop-studio-secret-2026' );
		if ( ! empty( $header_key ) && hash_equals( (string) $stored_key, (string) $header_key ) ) {
			return true;
		}

		return new WP_Error(
			'despace_rest_forbidden',
			__( 'Bạn không có quyền truy cập API đồng bộ Desktop 3D.', 'despace-core' ),
			array( 'status' => 403 )
		);
	}

	/**
	 * Health check endpoint for Desktop App.
	 */
	public function desktop_health_check(): WP_REST_Response {
		return new WP_REST_Response( array(
			'status'       => 'online',
			'service'      => 'DeSpace Desktop 3D Model Sync API',
			'version'      => '2.5.0',
			'site_url'     => home_url(),
			'site_name'    => get_bloginfo( 'name' ),
			'server_time'  => current_time( 'mysql' ),
			'capabilities' => array(
				'multi_angle_capture' => true,
				'max_angles'          => 5,
				'glb_upload'          => true,
				'excel_batch'         => true,
			),
		), 200 );
	}

	/**
	 * Dispatch job endpoint.
	 */
	public function desktop_dispatch_job( WP_REST_Request $request ): WP_REST_Response {
		$product_id = absint( $request->get_param( 'product_id' ) );
		$source     = sanitize_text_field( $request->get_param( 'source' ) ?: 'rest_api' );

		if ( ! $product_id ) {
			return new WP_REST_Response( array(
				'code'    => 'invalid_product_id',
				'message' => 'Product ID is required.',
			), 400 );
		}

		$job_data = Desktop3DSyncModule::dispatch_sync_job( $product_id, $source );
		return new WP_REST_Response( $job_data, 201 );
	}

	/**
	 * List jobs endpoint.
	 */
	public function desktop_list_jobs( WP_REST_Request $request ): WP_REST_Response {
		$status = sanitize_text_field( $request->get_param( 'status' ) ?: '' );
		$limit  = absint( $request->get_param( 'limit' ) ?: 50 );

		$jobs = Desktop3DSyncModule::list_jobs( $status, $limit );

		// Decode JSON payload for desktop consumption
		foreach ( $jobs as &$j ) {
			$j['multi_angles'] = ! empty( $j['multi_angles_json'] ) ? json_decode( $j['multi_angles_json'], true ) : array();
			$j['dimensions']   = ! empty( $j['dimensions_json'] ) ? json_decode( $j['dimensions_json'], true ) : array();
		}

		return new WP_REST_Response( array(
			'count' => count( $jobs ),
			'jobs'  => $jobs,
		), 200 );
	}

	/**
	 * Get single job endpoint.
	 */
	public function desktop_get_job( WP_REST_Request $request ): WP_REST_Response {
		$uuid = sanitize_text_field( $request->get_param( 'uuid' ) );
		$job  = Desktop3DSyncModule::get_job_by_uuid( $uuid );

		if ( ! $job ) {
			return new WP_REST_Response( array(
				'code'    => 'not_found',
				'message' => 'Job not found.',
			), 404 );
		}

		$job['multi_angles'] = ! empty( $job['multi_angles_json'] ) ? json_decode( $job['multi_angles_json'], true ) : array();
		$job['dimensions']   = ! empty( $job['dimensions_json'] ) ? json_decode( $job['dimensions_json'], true ) : array();

		return new WP_REST_Response( $job, 200 );
	}

	/**
	 * Update progress endpoint.
	 */
	public function desktop_update_progress( WP_REST_Request $request ): WP_REST_Response {
		$uuid     = sanitize_text_field( $request->get_param( 'uuid' ) );
		$progress = intval( $request->get_param( 'progress' ) ?? 0 );
		$message  = sanitize_text_field( $request->get_param( 'message' ) ?? '' );
		$status   = sanitize_text_field( $request->get_param( 'status' ) ?? 'processing' );

		$updated = Desktop3DSyncModule::update_job_progress( $uuid, $progress, $message, $status );
		if ( ! $updated ) {
			return new WP_REST_Response( array(
				'code'    => 'update_failed',
				'message' => 'Failed to update job progress.',
			), 400 );
		}

		return new WP_REST_Response( array(
			'success'  => true,
			'job_uuid' => $uuid,
			'progress' => $progress,
			'status'   => $status,
		), 200 );
	}

	/**
	 * Upload completed 3D model endpoint.
	 *
	 * Accepts multipart file or base64 data, saves GLB to uploads directory,
	 * registers media attachment, and binds to product.
	 * Also auto-resolves synthetic EXCEL-<SKU> UUIDs if dispatched directly from Excel batch.
	 */
	public function desktop_upload_completed_model( WP_REST_Request $request ): WP_REST_Response {
		$uuid = sanitize_text_field( $request->get_param( 'uuid' ) );
		$job  = Desktop3DSyncModule::get_job_by_uuid( $uuid );

		// Auto-resolve synthetic EXCEL-{SKU} UUIDs when Desktop App runs Excel Batch directly
		if ( ! $job && 0 === strpos( $uuid, 'EXCEL-' ) ) {
			$extracted_sku = sanitize_text_field( substr( $uuid, 6 ) );
			$product_id    = function_exists( 'wc_get_product_id_by_sku' ) ? wc_get_product_id_by_sku( $extracted_sku ) : 0;
			if ( ! $product_id && isset( $GLOBALS['wpdb'] ) ) {
				global $wpdb;
				$product_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_sku' AND meta_value = %s LIMIT 1", $extracted_sku ) );
			}
			if ( $product_id ) {
				$created_job = Desktop3DSyncModule::dispatch_sync_job( (int) $product_id, 'excel_batch' );
				$uuid        = $created_job['job_uuid'];
				$job         = Desktop3DSyncModule::get_job_by_uuid( $uuid );
			}
		}

		if ( ! $job ) {
			return new WP_REST_Response( array(
				'code'    => 'not_found',
				'message' => 'Job not found.',
			), 404 );
		}

		$product_id = (int) $job['product_id'];
		$sku        = ! empty( $job['sku'] ) ? $job['sku'] : ( 'PROD-' . $product_id );

		$glb_url   = '';
		$files     = $request->get_file_params();

		$upload_dir    = wp_upload_dir();
		$target_3d_dir = $upload_dir['basedir'] . '/3d/' . sanitize_file_name( $sku );
		if ( ! is_dir( $target_3d_dir ) ) {
			wp_mkdir_p( $target_3d_dir );
		}

		// Option A: Multipart uploaded file (validate glTF binary magic header)
		if ( ! empty( $files['file'] ) && ! empty( $files['file']['tmp_name'] ) ) {
			$uploaded    = $files['file'];
			$target_file = $target_3d_dir . '/model.glb';
			if ( is_readable( $uploaded['tmp_name'] ) && filesize( $uploaded['tmp_name'] ) >= 4 ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
				$magic = file_get_contents( $uploaded['tmp_name'], false, null, 0, 4 );
				if ( 'glTF' !== $magic ) {
					return new WP_REST_Response( array(
						'code'    => 'invalid_glb_header',
						'message' => 'Uploaded file is not a valid binary glTF (.glb) model.',
					), 400 );
				}
			}
			if ( move_uploaded_file( $uploaded['tmp_name'], $target_file ) || copy( $uploaded['tmp_name'], $target_file ) ) {
				$glb_url = $upload_dir['baseurl'] . '/3d/' . sanitize_file_name( $sku ) . '/model.glb';
			}
		} elseif ( ! empty( $request->get_param( 'glb_base64' ) ) ) {
			// Option B: Base64 payload
			$binary_data = base64_decode( (string) $request->get_param( 'glb_base64' ), true );
			if ( $binary_data && strlen( $binary_data ) >= 4 && 'glTF' === substr( $binary_data, 0, 4 ) ) {
				$target_file = $target_3d_dir . '/model.glb';
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
				file_put_contents( $target_file, $binary_data );
				$glb_url = $upload_dir['baseurl'] . '/3d/' . sanitize_file_name( $sku ) . '/model.glb';
			}
		} elseif ( ! empty( $request->get_param( 'glb_url' ) ) ) {
			// Option C: Direct external/internal URL
			$glb_url = esc_url_raw( $request->get_param( 'glb_url' ) );
		}

		if ( empty( $glb_url ) ) {
			return new WP_REST_Response( array(
				'code'    => 'missing_file',
				'message' => 'No valid 3D GLB file content was received.',
			), 400 );
		}

		$metadata = array(
			'vertex_count'    => intval( $request->get_param( 'vertex_count' ) ?? 0 ),
			'face_count'      => intval( $request->get_param( 'face_count' ) ?? 0 ),
			'file_size_bytes' => intval( $request->get_param( 'file_size' ) ?? ( file_exists( $target_3d_dir . '/model.glb' ) ? filesize( $target_3d_dir . '/model.glb' ) : 0 ) ),
			'usdz_url'        => esc_url_raw( (string) ( $request->get_param( 'usdz_url' ) ?? '' ) ),
			'thumbnail_url'   => esc_url_raw( (string) ( $request->get_param( 'thumbnail_url' ) ?? '' ) ),
		);

		Desktop3DSyncModule::complete_job( $uuid, $glb_url, $metadata );

		return new WP_REST_Response( array(
			'success'    => true,
			'message'    => '3D Model successfully received, saved, and published to website.',
			'job_uuid'   => $uuid,
			'product_id' => $product_id,
			'sku'        => $sku,
			'glb_url'    => $glb_url,
			'metadata'   => $metadata,
		), 200 );
	}

	/**
	 * Ingest and queue batch 3D items from Excel.
	 */
	public function desktop_batch_sync_excel( WP_REST_Request $request ): WP_REST_Response {
		$items = $request->get_param( 'items' );
		if ( ! is_array( $items ) || empty( $items ) ) {
			return new WP_REST_Response( array(
				'code'    => 'invalid_payload',
				'message' => 'Array of items is required.',
			), 400 );
		}

		$queued_jobs = array();
		foreach ( $items as $item ) {
			$sku = sanitize_text_field( $item['sku'] ?? '' );
			if ( ! $sku ) {
				continue;
			}

			// Find product by SKU
			$product_id = function_exists( 'wc_get_product_id_by_sku' ) ? wc_get_product_id_by_sku( $sku ) : 0;
			if ( ! $product_id ) {
				// Search by meta
				global $wpdb;
				$product_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_sku' AND meta_value = %s LIMIT 1", $sku ) );
			}

			if ( $product_id ) {
				// Save multi-angle image URLs from excel row if provided
				if ( ! empty( $item['front_url'] ) ) {
					update_post_meta( $product_id, '_despace_3d_angle_front_url', esc_url_raw( $item['front_url'] ) );
				}
				if ( ! empty( $item['perspective_url'] ) ) {
					update_post_meta( $product_id, '_despace_3d_angle_perspective_url', esc_url_raw( $item['perspective_url'] ) );
				}
				if ( ! empty( $item['side_url'] ) ) {
					update_post_meta( $product_id, '_despace_3d_angle_side_url', esc_url_raw( $item['side_url'] ) );
				}
				if ( ! empty( $item['back_url'] ) ) {
					update_post_meta( $product_id, '_despace_3d_angle_back_url', esc_url_raw( $item['back_url'] ) );
				}
				if ( ! empty( $item['top_url'] ) ) {
					update_post_meta( $product_id, '_despace_3d_angle_top_url', esc_url_raw( $item['top_url'] ) );
				}

				if ( ! empty( $item['width'] ) ) {
					update_post_meta( $product_id, '_width', floatval( $item['width'] ) );
				}
				if ( ! empty( $item['depth'] ) ) {
					update_post_meta( $product_id, '_length', floatval( $item['depth'] ) );
				}
				if ( ! empty( $item['height'] ) ) {
					update_post_meta( $product_id, '_height', floatval( $item['height'] ) );
				}

				$job           = Desktop3DSyncModule::dispatch_sync_job( $product_id, 'excel_batch' );
				$queued_jobs[] = $job;
			}
		}

		return new WP_REST_Response( array(
			'success'      => true,
			'queued_count' => count( $queued_jobs ),
			'jobs'         => $queued_jobs,
		), 200 );
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
	 * Update 3D model metadata for a product (POST /products/{id}/3d).
	 */
	public function update_product_3d( WP_REST_Request $request ): WP_REST_Response {
		$product_id = absint( $request->get_param( 'id' ) );
		if ( ! $product_id ) {
			return new WP_REST_Response( array(
				'code'    => 'despace_invalid_product',
				'message' => 'Invalid product ID.',
			), 400 );
		}

		$model_id = absint( $request->get_param( 'model_attachment_id' ) ?? 0 );
		if ( $model_id ) {
			update_post_meta( $product_id, '_despace_glb_attachment_id', $model_id );
		}

		$glb_url = esc_url_raw( (string) ( $request->get_param( 'glb_url' ) ?? '' ) );
		if ( $glb_url ) {
			update_post_meta( $product_id, '_despace_glb_url', $glb_url );
			update_post_meta( $product_id, '_axgnud_model_glb', $glb_url );
		}

		$ar_enabled = $request->get_param( 'ar_enabled' );
		if ( null !== $ar_enabled ) {
			update_post_meta( $product_id, '_despace_enable_ar', $ar_enabled ? 'yes' : 'no' );
			update_post_meta( $product_id, '_ar_enabled', $ar_enabled ? 'yes' : 'no' );
		}

		return new WP_REST_Response( array(
			'product_id' => $product_id,
			'updated'    => true,
			'data'       => Product3DModule::get_product_3d_info( $product_id ),
		), 200 );
	}

	/**
	 * Filter products REST handler (supports both /products/filter and /catalog).
	 */
	public function filter_products( WP_REST_Request $request ): WP_REST_Response {
		$keyword   = sanitize_text_field( (string) ( $request->get_param( 'q' ) ?? $request->get_param( 'keyword' ) ?? '' ) );
		$category  = sanitize_text_field( (string) ( $request->get_param( 'category' ) ?? '' ) );
		$min_price = floatval( $request->get_param( 'min_price' ) ?? 0 );
		$max_price = floatval( $request->get_param( 'max_price' ) ?? 0 );
		$page      = max( 1, absint( $request->get_param( 'page' ) ?: 1 ) );
		$per_page  = min( 48, max( 1, absint( $request->get_param( 'per_page' ) ?: 20 ) ) );

		$args = array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'paged'          => $page,
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
					'value'   => array( $min_price, $max_price > 0 ? $max_price : 999999999 ),
					'type'    => 'NUMERIC',
					'compare' => 'BETWEEN',
				),
			);
		}

		$query   = new \WP_Query( $args );
		$results = array();

		foreach ( $query->posts as $post ) {
			$prod = function_exists( 'wc_get_product' ) ? wc_get_product( $post->ID ) : null;
			if ( $prod ) {
				$results[] = array(
					'id'        => $prod->get_id(),
					'name'      => $prod->get_name(),
					'price'     => (float) $prod->get_price(),
					'sku'       => $prod->get_sku(),
					'permalink' => $prod->get_permalink(),
					'link'      => $prod->get_permalink(),
					'image'     => wp_get_attachment_image_url( $prod->get_image_id(), 'medium' ),
				);
			}
		}

		return new WP_REST_Response( array(
			'found'      => (int) $query->found_posts,
			'results'    => $results,
			'items'      => $results,
			'pagination' => array(
				'page'        => $page,
				'per_page'    => $per_page,
				'total_items' => (int) $query->found_posts,
				'total_pages' => (int) $query->max_num_pages,
			),
		), 200 );
	}

	/**
	 * Reports overview endpoint (GET /reports/overview).
	 */
	public function get_reports_overview( WP_REST_Request $request ): WP_REST_Response {
		global $wpdb;
		$total_products = (int) ( wp_count_posts( 'product' )->publish ?? 0 );
		$table_logs     = $wpdb->prefix . 'despace_inventory_logs';
		$total_logs     = 0;
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_logs ) ) === $table_logs ) {
			$total_logs = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_logs}" );
		}

		return new WP_REST_Response( array(
			'range'   => array(
				'date_from' => sanitize_text_field( (string) ( $request->get_param( 'date_from' ) ?? '' ) ),
				'date_to'   => sanitize_text_field( (string) ( $request->get_param( 'date_to' ) ?? '' ) ),
			),
			'metrics' => array(
				'total_products' => $total_products,
				'inventory_logs' => $total_logs,
			),
		), 200 );
	}

	/**
	 * Reports top products endpoint (GET /reports/top-products).
	 */
	public function get_reports_top_products( WP_REST_Request $request ): WP_REST_Response {
		$limit = min( 50, max( 1, absint( $request->get_param( 'limit' ) ?: 10 ) ) );
		return new WP_REST_Response( array(
			'limit' => $limit,
			'items' => array(),
		), 200 );
	}
}
