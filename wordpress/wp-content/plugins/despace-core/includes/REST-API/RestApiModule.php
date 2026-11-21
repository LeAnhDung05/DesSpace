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

		// 2. Product filter & Catalog endpoints (API Contract M2)
		$filter_args = array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'filter_products' ),
			'permission_callback' => '__return_true',
		);
		register_rest_route( self::REST_NAMESPACE, '/products/filter', $filter_args );
		register_rest_route( self::REST_NAMESPACE, '/catalog', $filter_args );

		// 3. Reports endpoints (API Contract M5)
		// DashboardMetricsController registers /reports/overview when M5 module is active.
		if ( ! class_exists( 'DeSpace\\Reports\\REST\\DashboardMetricsController' ) ) {
			register_rest_route(
				self::REST_NAMESPACE,
				'/reports/overview',
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_reports_overview' ),
					'permission_callback' => array( RoleManager::class, 'rest_check_reports_permission' ),
				)
			);
		}

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

		// 4.8 Fetch store product categories for Desktop App
		register_rest_route(
			self::REST_NAMESPACE,
			'/desktop-3d/categories',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'desktop_get_categories' ),
				'permission_callback' => array( $this, 'check_desktop_api_permission' ),
			)
		);

		// 4.9 One-click Create/Update Product + Upload Photos + Publish Auto-3D (.GLB & .USDZ)
		register_rest_route(
			self::REST_NAMESPACE,
			'/desktop-3d/products/publish',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'desktop_publish_product' ),
				'permission_callback' => array( $this, 'check_desktop_api_permission' ),
			)
		);

		// 5. Vietnam 2-Tier Administrative Regions (34 Provinces -> 3,321 Wards/Communes)
		register_rest_route(
			self::REST_NAMESPACE,
			'/regions/provinces',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_vn_provinces' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/regions/communes',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_vn_communes' ),
				'permission_callback' => '__return_true',
			)
		);

		// 6. AI Interior Design Concierge (Gemini 2.5 Flash RAG + Spatial Fit Engine)
		register_rest_route(
			self::REST_NAMESPACE,
			'/concierge/chat',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'concierge_chat' ),
				'permission_callback' => '__return_true',
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

		// 2. Secret Key Header verification (fail-closed: never fall back to a hardcoded default secret)
		$header_key = (string) ( $request->get_header( 'x_despace_desktop_key' ) ?: $request->get_header( 'x-despace-desktop-key' ) ?: '' );
		$stored_key = defined( 'DESPACE_DESKTOP_SYNC_KEY' ) && '' !== trim( (string) DESPACE_DESKTOP_SYNC_KEY )
			? trim( (string) DESPACE_DESKTOP_SYNC_KEY )
			: trim( (string) get_option( 'despace_desktop_sync_key', '' ) );

		if ( '' !== $stored_key && strlen( $stored_key ) >= 16 && '' !== $header_key && hash_equals( $stored_key, $header_key ) ) {
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
			'version'      => '3.0.0',
			'site_url'     => home_url(),
			'site_name'    => get_bloginfo( 'name' ),
			'server_time'  => current_time( 'mysql' ),
			'capabilities' => array(
				'multi_angle_capture' => true,
				'max_angles'          => 5,
				'glb_upload'          => true,
				'usdz_upload'         => true,
				'product_publish'     => true,
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

		$safe_sku = preg_replace( '/[^A-Za-z0-9_-]/', '', sanitize_file_name( (string) $sku ) );
		if ( '' === $safe_sku ) {
			return new WP_REST_Response( array(
				'code'    => 'invalid_sku',
				'message' => 'A valid alphanumeric SKU is required.',
			), 400 );
		}

		$max_model_bytes = 25 * 1024 * 1024; // 25 MB cap
		$max_image_bytes = 5 * 1024 * 1024;  // 5 MB cap

		$upload_dir    = wp_upload_dir();
		$target_3d_dir = $upload_dir['basedir'] . '/3d/' . $safe_sku;
		if ( ! is_dir( $target_3d_dir ) ) {
			wp_mkdir_p( $target_3d_dir );
		}

		// Option A: Multipart uploaded file (strictly require real HTTP upload & 12-byte glTF binary header)
		if ( ! empty( $files['file'] ) && ! empty( $files['file']['tmp_name'] ) ) {
			$uploaded    = $files['file'];
			$tmp_name    = (string) $uploaded['tmp_name'];
			$target_file = $target_3d_dir . '/model.glb';
			$fsize       = is_readable( $tmp_name ) ? (int) filesize( $tmp_name ) : 0;

			if ( ! is_uploaded_file( $tmp_name ) || $fsize < 12 || $fsize > $max_model_bytes ) {
				return new WP_REST_Response( array(
					'code'    => 'invalid_glb_upload',
					'message' => 'Uploaded file is missing, invalid, or exceeds the 25MB limit.',
				), 400 );
			}

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$magic = file_get_contents( $tmp_name, false, null, 0, 4 );
			if ( 'glTF' !== $magic ) {
				return new WP_REST_Response( array(
					'code'    => 'invalid_glb_header',
					'message' => 'Uploaded file is not a valid binary glTF (.glb) model.',
				), 400 );
			}

			if ( move_uploaded_file( $tmp_name, $target_file ) ) {
				$glb_url = $upload_dir['baseurl'] . '/3d/' . $safe_sku . '/model.glb';
			}
		} elseif ( ! empty( $request->get_param( 'glb_base64' ) ) ) {
			// Option B: Base64 payload
			$binary_data = base64_decode( (string) $request->get_param( 'glb_base64' ), true );
			if ( false !== $binary_data && strlen( $binary_data ) >= 12 && strlen( $binary_data ) <= $max_model_bytes && 'glTF' === substr( $binary_data, 0, 4 ) ) {
				$target_file = $target_3d_dir . '/model.glb';
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
				if ( false !== file_put_contents( $target_file, $binary_data ) ) {
					$glb_url = $upload_dir['baseurl'] . '/3d/' . $safe_sku . '/model.glb';
				}
			}
		} elseif ( ! empty( $request->get_param( 'glb_url' ) ) ) {
			// Option C: Validated http(s) URL pointing to a .glb/.gltf resource
			$candidate_url = esc_url_raw( (string) $request->get_param( 'glb_url' ) );
			$parsed_path   = (string) wp_parse_url( $candidate_url, PHP_URL_PATH );
			$parsed_scheme = strtolower( (string) wp_parse_url( $candidate_url, PHP_URL_SCHEME ) );
			if ( in_array( $parsed_scheme, array( 'http', 'https' ), true ) && preg_match( '/\.(?:glb|gltf)$/i', $parsed_path ) ) {
				$glb_url = $candidate_url;
			}
		}

		if ( empty( $glb_url ) ) {
			return new WP_REST_Response( array(
				'code'    => 'missing_file',
				'message' => 'No valid 3D GLB file content was received.',
			), 400 );
		}

		// Save accompanying USDZ (Apple AR Quick Look) and Thumbnail if transmitted
		$usdz_url = esc_url_raw( (string) ( $request->get_param( 'usdz_url' ) ?? '' ) );
		if ( ! empty( $request->get_param( 'usdz_base64' ) ) ) {
			$usdz_data = base64_decode( (string) $request->get_param( 'usdz_base64' ), true );
			if ( false !== $usdz_data && strlen( $usdz_data ) >= 30 && strlen( $usdz_data ) <= $max_model_bytes && "PK\x03\x04" === substr( $usdz_data, 0, 4 ) ) {
				$target_usdz = $target_3d_dir . '/model.usdz';
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
				if ( false !== file_put_contents( $target_usdz, $usdz_data ) ) {
					$usdz_url = $upload_dir['baseurl'] . '/3d/' . $safe_sku . '/model.usdz';
				}
			}
		}

		$thumb_url = esc_url_raw( (string) ( $request->get_param( 'thumbnail_url' ) ?? '' ) );
		if ( ! empty( $request->get_param( 'thumbnail_base64' ) ) ) {
			$thumb_data = base64_decode( (string) $request->get_param( 'thumbnail_base64' ), true );
			$img_info   = ( false !== $thumb_data && strlen( $thumb_data ) > 32 && strlen( $thumb_data ) <= $max_image_bytes && function_exists( 'getimagesizefromstring' ) )
				? @getimagesizefromstring( $thumb_data )
				: false;
			$img_mime   = is_array( $img_info ) ? (string) ( $img_info['mime'] ?? '' ) : '';
			$ext_map    = array( 'image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp' );
			if ( isset( $ext_map[ $img_mime ] ) ) {
				$thumb_file   = 'thumb.' . $ext_map[ $img_mime ];
				$target_thumb = $target_3d_dir . '/' . $thumb_file;
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
				if ( false !== file_put_contents( $target_thumb, $thumb_data ) ) {
					$thumb_url = $upload_dir['baseurl'] . '/3d/' . $safe_sku . '/' . $thumb_file;
				}
			}
		}

		$metadata = array(
			'vertex_count'    => intval( $request->get_param( 'vertex_count' ) ?? 0 ),
			'face_count'      => intval( $request->get_param( 'face_count' ) ?? 0 ),
			'file_size_bytes' => intval( $request->get_param( 'file_size' ) ?? ( file_exists( $target_3d_dir . '/model.glb' ) ? filesize( $target_3d_dir . '/model.glb' ) : 0 ) ),
			'usdz_url'        => $usdz_url,
			'thumbnail_url'   => $thumb_url,
		);

		Desktop3DSyncModule::complete_job( $uuid, $glb_url, $metadata );

		return new WP_REST_Response( array(
			'success'    => true,
			'message'    => '3D Model successfully received, saved, and published to website.',
			'job_uuid'   => $uuid,
			'product_id' => $product_id,
			'sku'        => $sku,
			'glb_url'    => $glb_url,
			'usdz_url'   => $usdz_url,
			'metadata'   => $metadata,
		), 200 );
	}

	/**
	 * Fetch store product categories for Desktop Studio dropdown.
	 */
	public function desktop_get_categories(): WP_REST_Response {
		$cats = array();
		if ( function_exists( 'get_terms' ) ) {
			$terms = get_terms( array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
			) );
			if ( is_array( $terms ) && ! is_wp_error( $terms ) ) {
				foreach ( $terms as $t ) {
					if ( 'uncategorized' === $t->slug ) {
						continue;
					}
					$cats[] = array(
						'id'    => (int) $t->term_id,
						'name'  => $t->name,
						'slug'  => $t->slug,
						'count' => (int) $t->count,
					);
				}
			}
		}

		if ( empty( $cats ) ) {
			$cats = array(
				array( 'id' => 0, 'name' => 'Tủ giày & Lưu trữ', 'slug' => 'tu-giay-luu-tru', 'count' => 0 ),
				array( 'id' => 0, 'name' => 'Tủ quần áo', 'slug' => 'tu-quan-ao', 'count' => 0 ),
				array( 'id' => 0, 'name' => 'Kệ sách & Tủ trưng bày', 'slug' => 'ke-sach-tu-trung-bay', 'count' => 0 ),
				array( 'id' => 0, 'name' => 'Tủ bếp', 'slug' => 'tu-bep', 'count' => 0 ),
				array( 'id' => 0, 'name' => 'Ghế & Đôn', 'slug' => 'ghe-don', 'count' => 0 ),
				array( 'id' => 0, 'name' => 'Sofa', 'slug' => 'sofa', 'count' => 0 ),
				array( 'id' => 0, 'name' => 'Bàn ăn & Bàn trà', 'slug' => 'table', 'count' => 0 ),
				array( 'id' => 0, 'name' => 'Giường ngủ', 'slug' => 'bed', 'count' => 0 ),
			);
		}

		return new WP_REST_Response( array(
			'success'    => true,
			'categories' => $cats,
		), 200 );
	}

	/**
	 * One-click Desktop Product Publisher: creates/updates product, imports photos,
	 * attaches auto-generated GLB + USDZ 3D models, and publishes live to Shop.
	 */
	public function desktop_publish_product( WP_REST_Request $request ): WP_REST_Response {
		$keys = array(
			'product_id', 'sku', 'name', 'product_name', 'name_en',
			'regular_price', 'sale_price', 'stock_quantity',
			'short_description', 'description', 'category', 'badge', 'color', 'material',
			'dimensions', 'width', 'depth', 'height',
			'images', 'glb_base64', 'glb_url', 'usdz_base64', 'usdz_url',
			'thumbnail_base64', 'vertex_count', 'face_count', 'file_size',
		);

		$payload = array();
		foreach ( $keys as $k ) {
			$val = $request->get_param( $k );
			if ( null !== $val ) {
				$payload[ $k ] = $val;
			}
		}

		if ( empty( $payload['name'] ) && empty( $payload['product_name'] ) && empty( $payload['sku'] ) ) {
			return new WP_REST_Response( array(
				'success' => false,
				'code'    => 'missing_product_name',
				'message' => 'Product name or SKU is required.',
			), 400 );
		}

		try {
			$result = Desktop3DSyncModule::publish_product_from_desktop( $payload );
			return new WP_REST_Response( $result, 201 );
		} catch ( \Throwable $e ) {
			error_log( '[DeSpace Desktop Publish] ' . $e->getMessage() );
			return new WP_REST_Response( array(
				'success' => false,
				'code'    => 'publish_failed',
				'message' => 'Failed to publish product due to an internal server error.',
			), 500 );
		}
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

		if ( count( $items ) > 100 ) {
			return new WP_REST_Response( array(
				'code'    => 'batch_too_large',
				'message' => 'Maximum 100 items allowed per batch request.',
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

				// Standardize physical dimension mapping consistently across Desktop Publish & Concierge
				if ( ! empty( $item['width'] ) ) {
					$w = (string) floatval( $item['width'] );
					update_post_meta( $product_id, '_length', $w );
					update_post_meta( $product_id, 'axgnud_dim_width', $w );
				}
				if ( ! empty( $item['depth'] ) ) {
					$d = (string) floatval( $item['depth'] );
					update_post_meta( $product_id, '_width', $d );
					update_post_meta( $product_id, 'axgnud_dim_depth', $d );
				}
				if ( ! empty( $item['height'] ) ) {
					$h = (string) floatval( $item['height'] );
					update_post_meta( $product_id, '_height', $h );
					update_post_meta( $product_id, 'axgnud_dim_height', $h );
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
	 * Retrieve 3D model metadata for a published product.
	 */
	public function get_product_3d( WP_REST_Request $request ): WP_REST_Response {
		$product_id = absint( $request->get_param( 'id' ) );
		if ( ! $product_id ) {
			return new WP_REST_Response( array( 'code' => 'not_found', 'message' => 'Product not found.' ), 404 );
		}

		if ( function_exists( 'get_post_type' ) && function_exists( 'get_post_status' ) ) {
			$post_type   = get_post_type( $product_id );
			$post_status = get_post_status( $product_id );
			$can_read    = function_exists( 'current_user_can' ) && current_user_can( 'read_post', $product_id );
			if ( 'product' !== $post_type || ( 'publish' !== $post_status && ! $can_read ) ) {
				return new WP_REST_Response( array( 'code' => 'not_found', 'message' => 'Product not found.' ), 404 );
			}
		}

		$info = Product3DModule::get_product_3d_info( $product_id );

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

		if ( function_exists( 'get_post_type' ) && 'product' !== get_post_type( $product_id ) ) {
			return new WP_REST_Response( array(
				'code'    => 'despace_invalid_product',
				'message' => 'Product not found.',
			), 404 );
		}

		if ( function_exists( 'current_user_can' ) && ! current_user_can( 'edit_post', $product_id ) && ! current_user_can( 'manage_despace_3d' ) && ! current_user_can( 'manage_woocommerce' ) ) {
			return new WP_REST_Response( array(
				'code'    => 'despace_rest_forbidden',
				'message' => 'Insufficient permissions for this product.',
			), 403 );
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

		// Sync custom 3D table so updates are not shadowed by stale rows.
		global $wpdb;
		if ( isset( $wpdb ) ) {
			$table_3d = $wpdb->prefix . 'despace_product_3d_models';
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table_3d ) ) ) === $table_3d ) {
				$update_fields = array();
				$update_fmt    = array();
				if ( $glb_url ) {
					$update_fields['glb_url']      = $glb_url;
					$update_fmt[]                  = '%s';
					$update_fields['model_status'] = 'published';
					$update_fmt[]                  = '%s';
				}
				if ( null !== $ar_enabled ) {
					$update_fields['ar_enabled'] = $ar_enabled ? 1 : 0;
					$update_fmt[]                = '%d';
				}
				if ( ! empty( $update_fields ) ) {
					$wpdb->update( $table_3d, $update_fields, array( 'product_id' => $product_id ), $update_fmt, array( '%d' ) );
				}
			}
		}

		return new WP_REST_Response( array(
			'product_id' => $product_id,
			'updated'    => true,
			'data'       => Product3DModule::get_product_3d_info( $product_id ),
		), 200 );
	}

	/**
	 * Filter products REST handler implementing official API Contract (M2 + /catalog).
	 */
	public function filter_products( WP_REST_Request $request ): WP_REST_Response {
		global $wpdb;

		$keyword   = sanitize_text_field( (string) ( $request->get_param( 'q' ) ?? $request->get_param( 'keyword' ) ?? $request->get_param( 's' ) ?? '' ) );
		$category  = sanitize_text_field( (string) ( $request->get_param( 'category' ) ?? '' ) );
		$material  = sanitize_text_field( (string) ( $request->get_param( 'material' ) ?? '' ) );
		$brand     = sanitize_text_field( (string) ( $request->get_param( 'brand' ) ?? '' ) );
		$color     = sanitize_text_field( (string) ( $request->get_param( 'color' ) ?? '' ) );
		$size      = sanitize_text_field( (string) ( $request->get_param( 'size' ) ?? '' ) );
		$raw_min   = $request->get_param( 'min_price' ) ?? $request->get_param( 'price_min' ) ?? '';
		$raw_max   = $request->get_param( 'max_price' ) ?? $request->get_param( 'price_max' ) ?? '';
		$min_price = ( '' !== $raw_min && is_numeric( $raw_min ) ) ? max( 0.0, (float) $raw_min ) : 0.0;
		$max_price = ( '' !== $raw_max && is_numeric( $raw_max ) ) ? max( 0.0, (float) $raw_max ) : 0.0;
		if ( $min_price > 0 && $max_price > 0 && $min_price > $max_price ) {
			$tmp       = $min_price;
			$min_price = $max_price;
			$max_price = $tmp;
		}
		$has_3d   = rest_sanitize_boolean( $request->get_param( 'has_3d' ) ?? false );
		$on_sale  = rest_sanitize_boolean( $request->get_param( 'on_sale' ) ?? $request->get_param( 'onsale' ) ?? false );
		$orderby  = sanitize_text_field( (string) ( $request->get_param( 'orderby' ) ?? $request->get_param( 'sort' ) ?? 'date' ) );
		$order    = strtoupper( sanitize_text_field( (string) ( $request->get_param( 'order' ) ?? 'DESC' ) ) );
		$order    = in_array( $order, array( 'ASC', 'DESC' ), true ) ? $order : 'DESC';
		$page     = max( 1, absint( $request->get_param( 'page' ) ?: 1 ) );
		$per_page = min( 50, max( 1, absint( $request->get_param( 'per_page' ) ?: 12 ) ) );

		$tax_query  = array();
		$meta_query = array();
		$post__in   = null;

		$parse_terms = static function ( string $raw ): array {
			$parts = array_values( array_filter( array_map( 'trim', explode( ',', $raw ) ) ) );
			if ( 1 === count( $parts ) && is_numeric( $parts[0] ) ) {
				return array( 'field' => 'term_id', 'terms' => array( (int) $parts[0] ) );
			}
			return array( 'field' => 'slug', 'terms' => array_map( 'sanitize_title', $parts ) );
		};

		if ( '' !== $category ) {
			$parsed      = $parse_terms( $category );
			$tax_query[] = array(
				'taxonomy' => 'product_cat',
				'field'    => $parsed['field'],
				'terms'    => $parsed['terms'],
			);
		}
		if ( '' !== $material && taxonomy_exists( 'pa_material' ) ) {
			$parsed      = $parse_terms( $material );
			$tax_query[] = array(
				'taxonomy' => 'pa_material',
				'field'    => $parsed['field'],
				'terms'    => $parsed['terms'],
			);
		}
		if ( '' !== $brand ) {
			$brand_tax = taxonomy_exists( 'product_brand' ) ? 'product_brand' : ( taxonomy_exists( 'pa_brand' ) ? 'pa_brand' : '' );
			if ( '' !== $brand_tax ) {
				$parsed      = $parse_terms( $brand );
				$tax_query[] = array(
					'taxonomy' => $brand_tax,
					'field'    => $parsed['field'],
					'terms'    => $parsed['terms'],
				);
			}
		}
		if ( '' !== $color && taxonomy_exists( 'pa_color' ) ) {
			$parsed      = $parse_terms( $color );
			$tax_query[] = array(
				'taxonomy' => 'pa_color',
				'field'    => $parsed['field'],
				'terms'    => $parsed['terms'],
			);
		}
		if ( '' !== $size && taxonomy_exists( 'pa_size' ) ) {
			$parsed      = $parse_terms( $size );
			$tax_query[] = array(
				'taxonomy' => 'pa_size',
				'field'    => $parsed['field'],
				'terms'    => $parsed['terms'],
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

		if ( $has_3d ) {
			$meta_query[] = array(
				'relation' => 'OR',
				array( 'key' => '_despace_glb_attachment_id', 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC' ),
				array( 'key' => '_despace_model_glb', 'value' => '', 'compare' => '!=' ),
				array( 'key' => '_despace_glb_url', 'value' => '', 'compare' => '!=' ),
				array( 'key' => '_axgnud_model_glb', 'value' => '', 'compare' => '!=' ),
			);
		}

		// Search keyword matching SKU (including variation SKU mapped to parent) and title
		if ( '' !== $keyword ) {
			$sku_product_ids = isset( $wpdb ) ? $wpdb->get_col( $wpdb->prepare(
				"SELECT DISTINCT CASE WHEN p.post_type = 'product_variation' THEN p.post_parent ELSE p.ID END AS product_id
				FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
				WHERE pm.meta_key = '_sku' AND pm.meta_value LIKE %s",
				'%' . $wpdb->esc_like( $keyword ) . '%'
			) ) : array();

			$title_query = new \WP_Query( array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => 200,
				'fields'         => 'ids',
				's'              => $keyword,
			) );

			$matched_ids = array_map( 'intval', array_unique( array_merge( (array) $sku_product_ids, (array) $title_query->posts ) ) );
			$matched_ids = array_values( array_filter( $matched_ids, static fn( $id ) => $id > 0 ) );
			$post__in    = ! empty( $matched_ids ) ? $matched_ids : array( 0 );
		}

		// Intersect on_sale filter
		if ( $on_sale && function_exists( 'wc_get_product_ids_on_sale' ) ) {
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
			$args['tax_query'] = count( $tax_query ) > 1 ? array_merge( array( 'relation' => 'AND' ), $tax_query ) : $tax_query;
		}
		if ( ! empty( $meta_query ) ) {
			$args['meta_query'] = count( $meta_query ) > 1 ? array_merge( array( 'relation' => 'AND' ), $meta_query ) : $meta_query;
		}
		if ( null !== $post__in ) {
			$args['post__in'] = $post__in;
		}

		switch ( $orderby ) {
			case 'price':
			case 'price-asc':
				$args['orderby']  = array( 'meta_value_num' => 'ASC', 'ID' => 'ASC' );
				$args['meta_key'] = '_price';
				break;
			case 'price-desc':
				$args['orderby']  = array( 'meta_value_num' => 'DESC', 'ID' => 'DESC' );
				$args['meta_key'] = '_price';
				break;
			case 'popularity':
				$args['orderby']  = array( 'meta_value_num' => 'DESC', 'ID' => 'DESC' );
				$args['meta_key'] = 'total_sales';
				break;
			case 'rating':
				$args['orderby']  = array( 'meta_value_num' => 'DESC', 'ID' => 'DESC' );
				$args['meta_key'] = '_wc_average_rating';
				break;
			case 'title':
				$args['orderby'] = array( 'title' => $order, 'ID' => $order );
				break;
			case 'date':
			default:
				$args['orderby'] = array( 'date' => $order, 'ID' => $order );
				break;
		}

		$query    = new \WP_Query( $args );
		$post_ids = wp_list_pluck( $query->posts, 'ID' );
		if ( ! empty( $post_ids ) && method_exists( Product3DModule::class, 'preload_products_3d_info' ) ) {
			Product3DModule::preload_products_3d_info( array_map( 'absint', $post_ids ) );
		}

		$items = array();
		foreach ( $query->posts as $post ) {
			$prod = function_exists( 'wc_get_product' ) ? wc_get_product( $post->ID ) : null;
			if ( ! $prod ) {
				continue;
			}

			$info_3d     = Product3DModule::get_product_3d_info( (int) $prod->get_id() );
			$glb_id      = (int) get_post_meta( $prod->get_id(), '_despace_glb_attachment_id', true );
			$has_prod_3d = $glb_id > 0 || ! empty( $info_3d['glb_url'] );
			$enable_ar   = ! empty( $info_3d['ar_enabled'] ) || 'yes' === get_post_meta( $prod->get_id(), '_despace_enable_ar', true ) || $has_prod_3d;

			$categories_data = array();
			$term_ids        = $prod->get_category_ids();
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

			$img_url = wp_get_attachment_image_url( $prod->get_image_id(), 'medium' ) ?: '';
			$items[] = array(
				'id'                => $prod->get_id(),
				'name'              => $prod->get_name(),
				'slug'              => $prod->get_slug(),
				'permalink'         => $prod->get_permalink(),
				'link'              => $prod->get_permalink(),
				'sku'               => $prod->get_sku(),
				'price'             => (float) $prod->get_price(),
				'regular_price'     => (float) $prod->get_regular_price(),
				'sale_price'        => (float) $prod->get_sale_price(),
				'price_html'        => $prod->get_price_html(),
				'stock_status'      => $prod->get_stock_status(),
				'categories'        => $categories_data,
				'thumbnail_url'     => $img_url,
				'image'             => $img_url,
				'image_url'         => $img_url,
				'has_3d'            => $has_prod_3d,
				'glb_attachment_id' => $glb_id,
				'model_url'         => $info_3d['glb_url'] ?? '',
				'enable_ar'         => $enable_ar,
				'ar_enabled'        => $enable_ar,
			);
		}

		$total_items = (int) $query->found_posts;
		$total_pages = $per_page > 0 ? (int) ceil( $total_items / $per_page ) : 1;

		return new WP_REST_Response( array(
			'success'         => true,
			'items'           => $items,
			'pagination'      => array(
				'page'        => $page,
				'per_page'    => $per_page,
				'total_items' => $total_items,
				'total_pages' => $total_pages,
			),
			'applied_filters' => array(
				'q'         => $keyword,
				'keyword'   => $keyword,
				'category'  => $category,
				'material'  => $material,
				'brand'     => $brand,
				'color'     => $color,
				'size'      => $size,
				'has_3d'    => $has_3d,
				'min_price' => $min_price,
				'max_price' => $max_price,
				'on_sale'   => $on_sale,
				'orderby'   => $orderby,
				'order'     => $order,
			),
			// Backward compatibility fields
			'found'           => $total_items,
			'results'         => $items,
		), 200 );
	}

	/**
	 * Reports overview endpoint (GET /reports/overview).
	 */
	public function get_reports_overview( WP_REST_Request $request ): WP_REST_Response {
		global $wpdb;
		$date_from      = sanitize_text_field( (string) ( $request->get_param( 'date_from' ) ?? '' ) );
		$date_to        = sanitize_text_field( (string) ( $request->get_param( 'date_to' ) ?? '' ) );
		$total_products = (int) ( wp_count_posts( 'product' )->publish ?? 0 );
		$table_logs     = $wpdb->prefix . 'despace_inventory_logs';
		$total_logs     = 0;

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table_logs ) ) ) === $table_logs ) {
			$where = array( '1=1' );
			$vals  = array();
			if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_from ) ) {
				$where[] = 'created_at >= %s';
				$vals[]  = $date_from . ' 00:00:00';
			}
			if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_to ) ) {
				$where[] = 'created_at <= %s';
				$vals[]  = $date_to . ' 23:59:59';
			}
			$sql        = "SELECT COUNT(*) FROM {$table_logs} WHERE " . implode( ' AND ', $where );
			$total_logs = (int) ( ! empty( $vals ) ? $wpdb->get_var( $wpdb->prepare( $sql, $vals ) ) : $wpdb->get_var( $sql ) );
		}

		return new WP_REST_Response( array(
			'range'   => array(
				'date_from' => $date_from,
				'date_to'   => $date_to,
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
		global $wpdb;
		$limit     = min( 50, max( 1, absint( $request->get_param( 'limit' ) ?: 10 ) ) );
		$date_from = sanitize_text_field( (string) ( $request->get_param( 'date_from' ) ?? '' ) );
		$date_to   = sanitize_text_field( (string) ( $request->get_param( 'date_to' ) ?? '' ) );
		$items     = array();

		$lookup_table = $wpdb->prefix . 'wc_order_product_lookup';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $lookup_table ) ) ) === $lookup_table ) {
			$where = array( '1=1' );
			$vals  = array();
			if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_from ) ) {
				$where[] = 'date_created >= %s';
				$vals[]  = $date_from . ' 00:00:00';
			}
			if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_to ) ) {
				$where[] = 'date_created <= %s';
				$vals[]  = $date_to . ' 23:59:59';
			}
			$vals[] = $limit;
			$sql    = "SELECT product_id, SUM(product_qty) AS units_sold, SUM(product_net_revenue) AS net_revenue
				FROM {$lookup_table}
				WHERE " . implode( ' AND ', $where ) . '
				GROUP BY product_id
				ORDER BY units_sold DESC, net_revenue DESC
				LIMIT %d';
			$rows = $wpdb->get_results( $wpdb->prepare( $sql, $vals ), ARRAY_A );
			foreach ( (array) $rows as $row ) {
				$pid  = absint( $row['product_id'] ?? 0 );
				$prod = ( $pid && function_exists( 'wc_get_product' ) ) ? wc_get_product( $pid ) : null;
				$items[] = array(
					'product_id'  => $pid,
					'sku'         => $prod ? $prod->get_sku() : '',
					'name'        => $prod ? $prod->get_name() : get_the_title( $pid ),
					'units_sold'  => (int) ( $row['units_sold'] ?? 0 ),
					'net_revenue' => round( (float) ( $row['net_revenue'] ?? 0 ), 2 ),
				);
			}
		} else {
			// Fallback to total_sales postmeta when WC analytics lookup table is absent
			$q = new \WP_Query( array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => $limit,
				'meta_key'       => 'total_sales',
				'orderby'        => 'meta_value_num',
				'order'          => 'DESC',
			) );
			foreach ( $q->posts as $p ) {
				$prod    = function_exists( 'wc_get_product' ) ? wc_get_product( $p->ID ) : null;
				$sold    = (int) get_post_meta( $p->ID, 'total_sales', true );
				$items[] = array(
					'product_id'  => (int) $p->ID,
					'sku'         => $prod ? $prod->get_sku() : '',
					'name'        => $prod ? $prod->get_name() : $p->post_title,
					'units_sold'  => $sold,
					'net_revenue' => $prod ? round( (float) $prod->get_price() * $sold, 2 ) : 0.0,
				);
			}
		}

		return new WP_REST_Response( array(
			'limit' => $limit,
			'items' => $items,
		), 200 );
	}

	/**
	 * GET /despace/v1/regions/provinces — Return 34 Vietnam provinces/municipalities.
	 */
	public function get_vn_provinces(): WP_REST_Response {
		if ( ! class_exists( 'DeSpace\\CheckoutOrders\\Shipping\\VietnamRegions' ) ) {
			return new WP_REST_Response( array( 'provinces' => array() ), 200 );
		}
		$provs = array_values( \DeSpace\CheckoutOrders\Shipping\VietnamRegions::get_provinces() );
		return new WP_REST_Response( array(
			'count'     => count( $provs ),
			'provinces' => $provs,
		), 200 );
	}

	/**
	 * GET /despace/v1/regions/communes?province={code} — Return wards/communes/special zones for a province.
	 */
	public function get_vn_communes( WP_REST_Request $request ): WP_REST_Response {
		$prov_code = sanitize_text_field( (string) ( $request->get_param( 'province' ) ?? '' ) );
		if ( '' === $prov_code || ! class_exists( 'DeSpace\\CheckoutOrders\\Shipping\\VietnamRegions' ) ) {
			return new WP_REST_Response( array( 'province_code' => $prov_code, 'count' => 0, 'communes' => array() ), 200 );
		}
		$communes = array_values( \DeSpace\CheckoutOrders\Shipping\VietnamRegions::get_communes( $prov_code ) );
		return new WP_REST_Response( array(
			'province_code' => $prov_code,
			'count'         => count( $communes ),
			'communes'      => $communes,
		), 200 );
	}

	/**
	 * POST /despace/v1/concierge/chat — Grounded AI Interior Design Concierge.
	 */
	public function concierge_chat( WP_REST_Request $request ): WP_REST_Response {
		$message = trim( sanitize_text_field( (string) ( $request->get_param( 'message' ) ?? '' ) ) );
		$lang    = sanitize_key( (string) ( $request->get_param( 'language' ) ?? 'vi' ) );

		if ( '' === $message ) {
			return new WP_REST_Response( array(
				'code'    => 'empty_message',
				'message' => 'Message is required.',
			), 400 );
		}

		if ( function_exists( 'mb_substr' ) ) {
			$message = mb_substr( $message, 0, 500, 'UTF-8' );
		} else {
			$message = substr( $message, 0, 500 );
		}

		// Per-IP rate limiting (15 requests / 60s) to protect Gemini quota and MySQL.
		if ( function_exists( 'get_transient' ) && function_exists( 'set_transient' ) ) {
			$ip        = trim( (string) ( $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0' ) );
			$rate_key  = 'despace_concierge_rl_' . md5( $ip );
			$rate_hits = (int) get_transient( $rate_key );
			if ( $rate_hits >= 15 ) {
				return new WP_REST_Response( array(
					'code'    => 'rate_limited',
					'message' => 'Bạn đã gửi quá nhiều yêu cầu. Vui lòng thử lại sau 1 phút.',
				), 429 );
			}
			set_transient( $rate_key, $rate_hits + 1, 60 );
		}

		if ( ! class_exists( 'DeSpace\\Catalog\\ConciergeService' ) ) {
			$svc_path = dirname( __DIR__ ) . '/Catalog/ConciergeService.php';
			if ( file_exists( $svc_path ) ) {
				require_once $svc_path;
			}
		}

		$result = \DeSpace\Catalog\ConciergeService::answer( $message, $lang );
		return new WP_REST_Response( $result, 200 );
	}
}
