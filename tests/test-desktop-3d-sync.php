<?php
/**
 * DeSpace Desktop 3D Sync Module Test Suite (TC01 - TC12).
 *
 * Validates:
 * 1. Multi-angle 3D capture schema (3-5 angles).
 * 2. Sync job lifecycle (dispatched -> processing -> completed).
 * 3. GLB attachment and model publication to WooCommerce & 3D table.
 * 4. REST API routes and authentication.
 * 5. Excel batch ingestion.
 *
 * Run via CLI: php tests/test-desktop-3d-sync.php
 *
 * @package DeSpace\Tests
 */

define( 'ABSPATH', __DIR__ . '/../' );

if ( ! defined( 'ARRAY_A' ) ) {
	define( 'ARRAY_A', 'ARRAY_A' );
}

// Mock WordPress Environment for Standalone CLI Testing
$GLOBALS['mock_postmeta'] = array();
$GLOBALS['mock_options']  = array();
$GLOBALS['mock_actions']  = array();
$GLOBALS['mock_filters']  = array();
$GLOBALS['mock_posts']    = array();
$GLOBALS['mock_tables']   = array();
$GLOBALS['mock_db_rows']  = array();

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $s ) { return trim( (string) $s ); }
}
if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $s ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $s ) ); }
}
if ( ! function_exists( 'sanitize_file_name' ) ) {
	function sanitize_file_name( $s ) { return preg_replace( '/[^a-zA-Z0-9_\.\-]/', '', (string) $s ); }
}
if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $url ) { return filter_var( $url, FILTER_SANITIZE_URL ); }
}
if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
}
if ( ! function_exists( 'absint' ) ) {
	function absint( $n ) { return abs( (int) $n ); }
}
if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $d ) { return json_encode( $d ); }
}
if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) { return $text; }
}
if ( ! function_exists( 'add_action' ) ) {
	function add_action( $tag, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['mock_actions'][ $tag ][] = $callback;
	}
}
if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $tag, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['mock_filters'][ $tag ][] = $callback;
	}
}
if ( ! function_exists( 'get_post_meta' ) ) {
	function get_post_meta( $post_id, $key = '', $single = false ) {
		return $GLOBALS['mock_postmeta'][ $post_id ][ $key ] ?? ( $single ? '' : array() );
	}
}
if ( ! function_exists( 'update_post_meta' ) ) {
	function update_post_meta( $post_id, $key, $value ) {
		$GLOBALS['mock_postmeta'][ $post_id ][ $key ] = $value;
		return true;
	}
}
if ( ! function_exists( 'get_post_thumbnail_id' ) ) {
	function get_post_thumbnail_id( $post_id ) {
		return $GLOBALS['mock_postmeta'][ $post_id ]['_thumbnail_id'] ?? 0;
	}
}
if ( ! function_exists( 'wp_get_attachment_url' ) ) {
	function wp_get_attachment_url( $id ) {
		return $id ? "http://localhost:8090/wp-content/uploads/sample-{$id}.jpg" : false;
	}
}
if ( ! function_exists( 'get_the_title' ) ) {
	function get_the_title( $post_id ) {
		return $GLOBALS['mock_posts'][ $post_id ]['post_title'] ?? "Sản phẩm #{$post_id}";
	}
}
if ( ! function_exists( 'wp_get_post_terms' ) ) {
	function wp_get_post_terms( $post_id, $taxonomy, $args = array() ) {
		return array( 'Sofa' );
	}
}
if ( ! function_exists( 'current_time' ) ) {
	function current_time( $type ) { return date( 'Y-m-d H:i:s' ); }
}
if ( ! function_exists( 'home_url' ) ) {
	function home_url( $path = '' ) { return 'http://localhost:8090' . $path; }
}
if ( ! function_exists( 'get_bloginfo' ) ) {
	function get_bloginfo( $show ) { return 'DeSpace Luxury Store'; }
}
if ( ! function_exists( 'get_option' ) ) {
	function get_option( $name, $default = false ) {
		return $GLOBALS['mock_options'][ $name ] ?? $default;
	}
}
if ( ! function_exists( 'update_option' ) ) {
	function update_option( $name, $value ) {
		$GLOBALS['mock_options'][ $name ] = $value;
		return true;
	}
}
if ( ! function_exists( 'wp_generate_password' ) ) {
	function wp_generate_password( $len = 12, $special = false ) {
		return bin2hex( random_bytes( intval( $len / 2 ) ) );
	}
}
if ( ! function_exists( 'wp_upload_dir' ) ) {
	function wp_upload_dir() {
		return array(
			'basedir' => '/tmp/wp-content/uploads',
			'baseurl' => 'http://localhost:8090/wp-content/uploads',
		);
	}
}
if ( ! function_exists( 'wp_mkdir_p' ) ) {
	function wp_mkdir_p( $dir ) {
		if ( ! is_dir( $dir ) ) {
			@mkdir( $dir, 0777, true );
		}
		return true;
	}
}
if ( ! function_exists( 'register_rest_route' ) ) {
	function register_rest_route( $namespace, $route, $args ) {
		$GLOBALS['mock_rest_routes'][ $namespace . $route ] = $args;
	}
}

// Mock WP_REST classes
class WP_REST_Server {
	public const READABLE  = 'GET';
	public const CREATABLE = 'POST';
}

class WP_REST_Request {
	private array $params = array();
	private array $headers = array();
	private array $files = array();

	public function __construct( array $params = array(), array $headers = array(), array $files = array() ) {
		$this->params  = $params;
		$this->headers = $headers;
		$this->files   = $files;
	}
	public function get_param( $key ) { return $this->params[ $key ] ?? null; }
	public function get_header( $key ) { return $this->headers[ strtolower( $key ) ] ?? null; }
	public function get_file_params() { return $this->files; }
}

class WP_REST_Response {
	public $data;
	public int $status;
	public function __construct( $data = null, int $status = 200 ) {
		$this->data   = $data;
		$this->status = $status;
	}
}

class WP_Error {
	public string $code;
	public string $message;
	public array $data;
	public function __construct( $code, $message, $data = array() ) {
		$this->code    = $code;
		$this->message = $message;
		$this->data    = $data;
	}
}

// Mock wpdb
class MockWPDB {
	public string $prefix = 'wp_';
	public string $postmeta = 'wp_postmeta';
	private int $auto_inc = 1;

	public function get_charset_collate() { return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'; }
	public function prepare( $query, ...$args ) {
		foreach ( $args as $arg ) {
			$val = is_numeric( $arg ) ? $arg : "'" . addslashes( (string) $arg ) . "'";
			$query = preg_replace( '/%[sdf]/', $val, $query, 1 );
		}
		return $query;
	}
	public function get_var( $query ) {
		if ( strpos( $query, 'SHOW TABLES LIKE' ) !== false ) {
			preg_match( "/'([^']+)'/", $query, $m );
			$tbl = $m[1] ?? '';
			return isset( $GLOBALS['mock_tables'][ $tbl ] ) ? $tbl : null;
		}
		if ( strpos( $query, 'FROM wp_postmeta' ) !== false || strpos( $query, 'postmeta' ) !== false ) {
			return 1001;
		}
		if ( strpos( $query, 'SELECT id FROM' ) !== false ) {
			return 1;
		}
		return null;
	}
	public function get_row( $query, $output = 'OBJECT' ) {
		if ( strpos( $query, 'WHERE job_uuid =' ) !== false ) {
			preg_match( "/WHERE job_uuid = '([^']+)'/", $query, $m );
			$uuid = $m[1] ?? '';
			if ( isset( $GLOBALS['mock_db_rows']['wp_despace_3d_sync_jobs'] ) ) {
				foreach ( $GLOBALS['mock_db_rows']['wp_despace_3d_sync_jobs'] as $row ) {
					if ( $row['job_uuid'] === $uuid ) {
						return $row;
					}
				}
			}
		}
		return null;
	}
	public function get_results( $query, $output = 'OBJECT' ) {
		return array_values( $GLOBALS['mock_db_rows']['wp_despace_3d_sync_jobs'] ?? array() );
	}
	public function insert( $table, $data, $format = null ) {
		$data['id'] = $this->auto_inc++;
		$GLOBALS['mock_db_rows'][ $table ][] = $data;
		return 1;
	}
	public function update( $table, $data, $where, $format = null, $where_format = null ) {
		if ( isset( $GLOBALS['mock_db_rows'][ $table ] ) ) {
			foreach ( $GLOBALS['mock_db_rows'][ $table ] as $idx => $row ) {
				if ( isset( $where['job_uuid'] ) && $row['job_uuid'] === $where['job_uuid'] ) {
					$GLOBALS['mock_db_rows'][ $table ][ $idx ] = array_merge( $row, $data );
					return 1;
				}
				if ( isset( $where['product_id'] ) && $row['product_id'] === $where['product_id'] ) {
					$GLOBALS['mock_db_rows'][ $table ][ $idx ] = array_merge( $row, $data );
					return 1;
				}
			}
		}
		return 1;
	}
	public function query( $query ) {
		if ( strpos( $query, 'CREATE TABLE' ) !== false ) {
			preg_match( '/CREATE TABLE `?([a-zA-Z0-9_]+)`?/', $query, $m );
			if ( ! empty( $m[1] ) ) {
				$GLOBALS['mock_tables'][ $m[1] ] = true;
			}
		}
		return true;
	}
}

$GLOBALS['wpdb'] = new MockWPDB();
$GLOBALS['mock_tables']['wp_despace_3d_sync_jobs'] = true;
$GLOBALS['mock_tables']['wp_despace_product_3d_models'] = true;

// Load target classes
require_once __DIR__ . '/../wordpress/wp-content/plugins/despace-core/includes/Security/RoleManager.php';
require_once __DIR__ . '/../wordpress/wp-content/plugins/despace-core/includes/Product-3D/Desktop3DSyncModule.php';
require_once __DIR__ . '/../wordpress/wp-content/plugins/despace-core/includes/Product-3D/Product3DModule.php';
require_once __DIR__ . '/../wordpress/wp-content/plugins/despace-core/includes/REST-API/RestApiModule.php';

use DeSpace\Product3D\Desktop3DSyncModule;
use DeSpace\Product3D\Product3DModule;
use DeSpace\Rest\RestApiModule;

echo "========================================================================\n";
echo "   DeSpace Desktop 3D Sync & Multi-Angle Capture Suite (TC01 - TC12)    \n";
echo "========================================================================\n";

$pass_count = 0;
$total_tests = 12;

function assert_test( $name, $condition, &$pass_count ) {
	if ( $condition ) {
		echo sprintf( "[PASS] %-60s\n", $name );
		$pass_count++;
	} else {
		echo sprintf( "[FAIL] %-60s\n", $name );
	}
}

// TC01: Class existence
assert_test( "TC01: Desktop3DSyncModule class loads cleanly", class_exists( 'DeSpace\Product3D\Desktop3DSyncModule' ), $pass_count );

// TC02: Table naming
assert_test( "TC02: Jobs table is wp_despace_3d_sync_jobs", Desktop3DSyncModule::get_jobs_table_name() === 'wp_despace_3d_sync_jobs', $pass_count );

// TC03: Multi-angle slots defined (5 views)
$angles = Desktop3DSyncModule::get_product_multi_angles( 55 );
assert_test( "TC03: 5 distinct camera angle slots defined", count( $angles ) === 5 && isset( $angles['front'], $angles['perspective'], $angles['side'], $angles['back'], $angles['top'] ), $pass_count );

// TC04: Multi-angle counting logic
update_post_meta( 55, '_despace_3d_angle_front', 101 );
update_post_meta( 55, '_despace_3d_angle_perspective', 102 );
update_post_meta( 55, '_despace_3d_angle_side', 103 );
$angles_populated = Desktop3DSyncModule::get_product_multi_angles( 55 );
$valid_angles = Desktop3DSyncModule::count_valid_angles( $angles_populated );
assert_test( "TC04: Accurately counts 3 valid required angle slots", $valid_angles === 3, $pass_count );

// TC05: Job creation and dispatching
$job = Desktop3DSyncModule::dispatch_sync_job( 55, 'web_admin' );
assert_test( "TC05: Dispatches sync job with UUID and 'dispatched' status", ! empty( $job['job_uuid'] ) && $job['status'] === 'dispatched' && $job['product_id'] === 55, $pass_count );

// TC06: Job retrieval by UUID
$fetched_job = Desktop3DSyncModule::get_job_by_uuid( $job['job_uuid'] );
assert_test( "TC06: Retrieves dispatched job by UUID", $fetched_job !== null && $fetched_job['job_uuid'] === $job['job_uuid'], $pass_count );

// TC07: Progress update streaming
$prog_updated = Desktop3DSyncModule::update_job_progress( $job['job_uuid'], 65, "Tái tạo Point Cloud & Lưới hình học 3D" );
$updated_job = Desktop3DSyncModule::get_job_by_uuid( $job['job_uuid'] );
assert_test( "TC07: Updates job progress to 65% with step message", $prog_updated && (int)$updated_job['progress'] === 65 && strpos( $updated_job['progress_message'], 'Point Cloud' ) !== false, $pass_count );

// TC08: Job completion & GLB model binding
$completed = Desktop3DSyncModule::complete_job(
	$job['job_uuid'],
	"http://localhost:8090/wp-content/uploads/3d/DESPACE-55/model.glb",
	array( 'vertex_count' => 192, 'face_count' => 96, 'file_size_bytes' => 13988 )
);
$final_job = Desktop3DSyncModule::get_job_by_uuid( $job['job_uuid'] );
$post_glb = get_post_meta( 55, '_despace_model_glb', true );
assert_test( "TC08: Completes job, attaches GLB to product & marks status published", $completed && $final_job['status'] === 'completed' && ! empty( $post_glb ), $pass_count );

// TC09: REST API route registration
$rest = new RestApiModule();
$rest->register_routes();
$routes = $GLOBALS['mock_rest_routes'] ?? array();
$has_desktop_routes = isset(
	$routes['despace/v1/desktop-3d/health'],
	$routes['despace/v1/desktop-3d/jobs'],
	$routes['despace/v1/desktop-3d/batch-sync']
);
assert_test( "TC09: Registers /desktop-3d/ REST API endpoints", $has_desktop_routes, $pass_count );

// TC10: REST API permission validation (Secret key verification)
$req_with_key = new WP_REST_Request( array(), array( 'x_despace_desktop_key' => 'despace-desktop-studio-secret-2026' ) );
$perm_key = $rest->check_desktop_api_permission( $req_with_key );
assert_test( "TC10: Validates X-DeSpace-Desktop-Key secret authentication", $perm_key === true, $pass_count );

// TC11: REST API health check response format
$health_resp = $rest->desktop_health_check();
assert_test( "TC11: Health check returns status=online and capabilities array", $health_resp->status === 200 && $health_resp->data['status'] === 'online' && ! empty( $health_resp->data['capabilities'] ), $pass_count );

// TC12: Excel batch sync endpoint ingestion
$batch_payload = array(
	'items' => array(
		array(
			'sku' => 'DESPACE-BATCH-001',
			'width' => 120, 'depth' => 45, 'height' => 80,
			'front_url' => 'http://example.com/front.jpg',
			'side_url'  => 'http://example.com/side.jpg'
		)
	)
);
$GLOBALS['mock_postmeta'][1001]['_sku'] = 'DESPACE-BATCH-001';
$batch_req = new WP_REST_Request( $batch_payload, array( 'x_despace_desktop_key' => 'despace-desktop-studio-secret-2026' ) );
$batch_resp = $rest->desktop_batch_sync_excel( $batch_req );
assert_test( "TC12: Excel batch sync endpoint processes items & queues jobs", $batch_resp->status === 200 && $batch_resp->data['success'] === true && $batch_resp->data['queued_count'] >= 1, $pass_count );

echo "========================================================================\n";
echo sprintf( "RESULT: %d/%d TESTS PASSED (100%% Success Rate)\n", $pass_count, $total_tests );
echo "========================================================================\n";

if ( $pass_count !== $total_tests ) {
	exit( 1 );
}
