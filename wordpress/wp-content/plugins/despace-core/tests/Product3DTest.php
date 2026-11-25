<?php
/**
 * Comprehensive Test Suite for DeSpace Product 3D & AR Module (Issue #6).
 *
 * Verifies MIME types, GLB/USDZ binary magic byte inspection, upload size guard (20MB),
 * metadata attachment ID handling, poster fallback logic with source flag,
 * nonce and capability security guards, physical dimensions, and REST API contract compliance.
 *
 * @package DeSpace\Tests
 */

namespace DeSpace\Tests;

use DeSpace\Product3D\Product3DModule;
use DeSpace\REST\RestApiModule;
use WP_REST_Request;

/**
 * Class Product3DTest
 */
class Product3DTest {

	/**
	 * Run all tests in suite.
	 */
	public function run(): array {
		$results = array(
			'test_3d_mime_types'                     => $this->test_3d_mime_types(),
			'test_glb_magic_bytes_validation'        => $this->test_glb_magic_bytes_validation(),
			'test_usdz_magic_bytes_validation'       => $this->test_usdz_magic_bytes_validation(),
			'test_reject_fake_glb'                   => $this->test_reject_fake_glb(),
			'test_max_filesize_guard'                => $this->test_max_filesize_guard(),
			'test_meta_box_attachment_and_dimensions'=> $this->test_meta_box_attachment_and_dimensions(),
			'test_rest_api_contract_structure'       => $this->test_rest_api_contract_structure(),
			'test_poster_fallback_to_product_image'  => $this->test_poster_fallback_to_product_image(),
			'test_save_meta_box_nonce_and_permissions'=> $this->test_save_meta_box_nonce_and_permissions(),
		);

		return $results;
	}

	/**
	 * Test 1: Verify 3D/AR MIME types are registered for privileged users.
	 */
	public function test_3d_mime_types(): bool {
		if ( function_exists( 'wp_set_current_user' ) ) {
			wp_set_current_user( 1 ); // Authenticate as admin with upload capabilities
		}

		$module = new Product3DModule();
		$mimes  = $module->register_mime_types( array( 'jpg' => 'image/jpeg' ) );

		assert( isset( $mimes['glb'] ), 'GLB MIME type must be registered.' );
		assert( 'model/gltf-binary' === $mimes['glb'], 'GLB MIME must be model/gltf-binary.' );
		assert( isset( $mimes['usdz'] ), 'USDZ MIME type must be registered.' );
		assert( 'model/vnd.usdz+zip' === $mimes['usdz'], 'USDZ MIME must be model/vnd.usdz+zip.' );
		assert( isset( $mimes['gltf'] ), 'GLTF MIME type must be registered.' );
		assert( 'model/gltf+json' === $mimes['gltf'], 'GLTF MIME must be model/gltf+json.' );

		return true;
	}

	/**
	 * Test 2: Verify binary inspection of valid glTF 2.0 header ('glTF' + version 2).
	 */
	public function test_glb_magic_bytes_validation(): bool {
		$module = new Product3DModule();

		// Construct valid glTF 2.0 binary header: 4 bytes magic 'glTF', 4 bytes version (2), 4 bytes length
		$valid_glb = "glTF" . pack( 'V', 2 ) . pack( 'V', 100 ) . str_repeat( "\0", 88 );
		$tmp_file  = tempnam( sys_get_temp_dir(), 'glb_test_' );
		file_put_contents( $tmp_file, $valid_glb );

		$file_arr = array(
			'name'     => 'test-model.glb',
			'type'     => 'application/octet-stream',
			'tmp_name' => $tmp_file,
			'error'    => 0,
			'size'     => strlen( $valid_glb ),
		);

		$validated = $module->validate_3d_upload_file( $file_arr );
		@unlink( $tmp_file );

		assert( empty( $validated['error'] ), 'Valid glTF 2.0 header must pass validation without error.' );
		return true;
	}

	/**
	 * Test 3: Verify binary inspection of valid USDZ header ("PK\x03\x04").
	 */
	public function test_usdz_magic_bytes_validation(): bool {
		$module = new Product3DModule();

		// Construct valid USDZ zip package header
		$valid_usdz = "PK\x03\x04" . str_repeat( "\0", 36 );
		$tmp_file   = tempnam( sys_get_temp_dir(), 'usdz_test_' );
		file_put_contents( $tmp_file, $valid_usdz );

		$file_arr = array(
			'name'     => 'test-model.usdz',
			'type'     => 'application/octet-stream',
			'tmp_name' => $tmp_file,
			'error'    => 0,
			'size'     => strlen( $valid_usdz ),
		);

		$validated = $module->validate_3d_upload_file( $file_arr );
		@unlink( $tmp_file );

		assert( empty( $validated['error'] ), 'Valid USDZ header must pass validation without error.' );
		return true;
	}

	/**
	 * Test 4: Reject fake/malicious file masquerading with a .glb extension.
	 */
	public function test_reject_fake_glb(): bool {
		$module = new Product3DModule();

		// Fake PHP or text file disguised as .glb
		$fake_content = "<?php echo 'malicious'; ?>";
		$tmp_file     = tempnam( sys_get_temp_dir(), 'fake_glb_' );
		file_put_contents( $tmp_file, $fake_content );

		$file_arr = array(
			'name'     => 'shell.glb',
			'type'     => 'application/octet-stream',
			'tmp_name' => $tmp_file,
			'error'    => 0,
			'size'     => strlen( $fake_content ),
		);

		$validated = $module->validate_3d_upload_file( $file_arr );
		@unlink( $tmp_file );

		assert( ! empty( $validated['error'] ), 'Fake GLB file without glTF header must be rejected.' );
		return true;
	}

	/**
	 * Test 5: Verify upload size guard rejects files exceeding 20MB limit.
	 */
	public function test_max_filesize_guard(): bool {
		$module = new Product3DModule();

		$oversized_bytes = 21 * 1024 * 1024; // 21MB > 20MB limit
		$file_arr = array(
			'name'     => 'huge-furniture.glb',
			'type'     => 'model/gltf-binary',
			'tmp_name' => '/tmp/dummy',
			'error'    => 0,
			'size'     => $oversized_bytes,
		);

		$validated = $module->validate_3d_upload_file( $file_arr );

		assert( ! empty( $validated['error'] ), '3D files larger than 20MB must be rejected by upload guard.' );
		assert( false !== strpos( $validated['error'], '20MB' ), 'Error message must clearly state the 20MB limit.' );
		return true;
	}

	/**
	 * Test 6: Verify attachment ID storage and physical dimensions metadata.
	 */
	public function test_meta_box_attachment_and_dimensions(): bool {
		$test_post_id = 999999;

		$dims = array(
			'length' => 210.0,
			'width'  => 90.0,
			'height' => 85.0,
			'unit'   => 'cm',
		);

		update_post_meta( $test_post_id, '_despace_glb_attachment_id', 501 );
		update_post_meta( $test_post_id, '_despace_poster_attachment_id', 502 );
		update_post_meta( $test_post_id, '_despace_enable_ar', 'yes' );
		update_post_meta( $test_post_id, '_despace_3d_dimensions', $dims );
		update_post_meta( $test_post_id, '_despace_3d_camera_orbit', '45deg 75deg 2.5m' );
		update_post_meta( $test_post_id, '_despace_3d_field_of_view', '30deg' );

		$info = Product3DModule::get_product_3d_info( $test_post_id );

		assert( 501 === absint( $info['glb_attachment_id'] ?? 0 ), 'GLB attachment ID must be preserved.' );
		assert( ! empty( $info['ar_enabled'] ), 'AR flag must be true.' );
		assert( 210.0 === (float) ( $info['depth'] ?? 0 ), 'Length/depth must match 210.' );
		assert( 90.0 === (float) ( $info['width'] ?? 0 ), 'Width must match 90.' );
		assert( 85.0 === (float) ( $info['height'] ?? 0 ), 'Height must match 85.' );
		assert( 'cm' === ( $info['unit'] ?? '' ), 'Unit must match cm.' );

		// Clean up
		delete_post_meta( $test_post_id, '_despace_glb_attachment_id' );
		delete_post_meta( $test_post_id, '_despace_poster_attachment_id' );
		delete_post_meta( $test_post_id, '_despace_enable_ar' );
		delete_post_meta( $test_post_id, '_despace_3d_dimensions' );
		delete_post_meta( $test_post_id, '_despace_3d_camera_orbit' );
		delete_post_meta( $test_post_id, '_despace_3d_field_of_view' );

		return true;
	}

	/**
	 * Test 7: Verify REST API contract structure (/products/{id}/model-3d).
	 */
	public function test_rest_api_contract_structure(): bool {
		$test_post_id = 999998;
		update_post_meta( $test_post_id, '_despace_glb_attachment_id', 701 );
		update_post_meta( $test_post_id, '_despace_glb_url', 'https://despace.test/test.glb' );
		update_post_meta( $test_post_id, '_despace_poster_attachment_id', 702 );
		update_post_meta( $test_post_id, '_despace_enable_ar', 'yes' );

		$rest    = new RestApiModule();
		$request = new WP_REST_Request( 'GET', '/despace/v1/products/' . $test_post_id . '/model-3d' );
		$request->set_url_params( array( 'id' => $test_post_id ) );

		$response = $rest->get_product_3d( $request );
		$data     = $response->get_data();

		assert( isset( $data['product_id'] ), 'Response must have product_id.' );
		assert( isset( $data['has_3d'] ), 'Response must have has_3d.' );
		assert( isset( $data['glb'] ), 'Response must have glb object.' );
		assert( isset( $data['glb']['attachment_id'] ), 'GLB object must have attachment_id.' );
		assert( isset( $data['glb']['url'] ), 'GLB object must have url.' );
		assert( isset( $data['poster'] ), 'Response must have poster object.' );
		assert( isset( $data['poster']['source'] ), 'Poster object must have source flag.' );
		assert( isset( $data['config'] ), 'Response must have config object.' );
		assert( isset( $data['config']['enable_ar'] ), 'Config object must have enable_ar.' );
		assert( isset( $data['config']['dimensions'] ), 'Config object must have dimensions.' );

		delete_post_meta( $test_post_id, '_despace_glb_attachment_id' );
		delete_post_meta( $test_post_id, '_despace_glb_url' );
		delete_post_meta( $test_post_id, '_despace_poster_attachment_id' );
		delete_post_meta( $test_post_id, '_despace_enable_ar' );

		return true;
	}

	/**
	 * Test 8: Verify poster fallback to product featured image and poster_source flag.
	 */
	public function test_poster_fallback_to_product_image(): bool {
		$test_post_id = 999997;
		update_post_meta( $test_post_id, '_despace_glb_url', 'https://despace.test/chair.glb' );

		// 1. Case: No custom poster, but product has featured image (_thumbnail_id)
		delete_post_meta( $test_post_id, '_despace_poster_attachment_id' );
		update_post_meta( $test_post_id, '_thumbnail_id', 888 );

		$info = Product3DModule::get_product_3d_info( $test_post_id );
		assert( 888 === absint( $info['poster_attachment_id'] ?? 0 ), 'Poster ID must fallback to featured image thumbnail ID.' );
		assert( 'product_image' === ( $info['poster_source'] ?? '' ), 'Poster source must be product_image when fallback occurs.' );

		// 2. Case: Custom poster attachment is deleted or invalid -> must fallback to product_image
		update_post_meta( $test_post_id, '_despace_poster_attachment_id', 9999999 ); // non-existent attachment
		$info_broken = Product3DModule::get_product_3d_info( $test_post_id );
		assert( 888 === absint( $info_broken['poster_attachment_id'] ?? 0 ), 'Non-existent custom poster must trigger fallback to featured image.' );
		assert( 'product_image' === ( $info_broken['poster_source'] ?? '' ), 'Source must be product_image for broken custom poster.' );

		// Clean up
		delete_post_meta( $test_post_id, '_despace_glb_url' );
		delete_post_meta( $test_post_id, '_despace_poster_attachment_id' );
		delete_post_meta( $test_post_id, '_thumbnail_id' );

		return true;
	}

	/**
	 * Test 9: Verify nonce and capability checks in save_meta_box().
	 */
	public function test_save_meta_box_nonce_and_permissions(): bool {
		$module       = new Product3DModule();
		$test_post_id = 999996;

		// 1. Missing or invalid nonce -> must abort without updating
		$_POST = array(
			'despace_3d_nonce'             => 'invalid_nonce_token',
			'despace_enable_ar'            => 'yes',
			'despace_poster_attachment_id' => 555,
		);
		$module->save_meta_box( $test_post_id );
		assert( empty( get_post_meta( $test_post_id, '_despace_poster_attachment_id', true ) ), 'Invalid nonce must prevent save.' );

		// 2. Valid nonce, but user lacks edit_post / manage_despace_3d -> must abort
		if ( function_exists( 'wp_create_nonce' ) ) {
			$_POST['despace_3d_nonce'] = wp_create_nonce( 'despace_save_3d_meta' );
		}

		if ( function_exists( 'wp_set_current_user' ) ) {
			wp_set_current_user( 0 ); // Logged out / unauthenticated user
		}
		$module->save_meta_box( $test_post_id );
		assert( empty( get_post_meta( $test_post_id, '_despace_poster_attachment_id', true ) ), 'User without permissions must be denied.' );

		// 3. User with admin / manage_despace_3d permission -> successfully saves
		if ( function_exists( 'wp_set_current_user' ) ) {
			wp_set_current_user( 1 ); // Admin user
		}
		$module->save_meta_box( $test_post_id );
		assert( 555 === absint( get_post_meta( $test_post_id, '_despace_poster_attachment_id', true ) ), 'Privileged user must successfully save.' );

		// Clean up
		delete_post_meta( $test_post_id, '_despace_poster_attachment_id' );
		delete_post_meta( $test_post_id, '_despace_enable_ar' );
		$_POST = array();

		return true;
	}
}
