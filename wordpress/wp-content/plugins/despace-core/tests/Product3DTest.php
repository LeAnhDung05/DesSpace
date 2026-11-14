<?php
/**
 * Test case for DeSpace Product 3D Module.
 *
 * @package DeSpace\Tests
 */

namespace DeSpace\Tests;

use DeSpace\Product3D\Product3DModule;

/**
 * Class Product3DTest
 */
class Product3DTest {

	/**
	 * Test 3D and AR MIME type filtering.
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

		return true;
	}
}
