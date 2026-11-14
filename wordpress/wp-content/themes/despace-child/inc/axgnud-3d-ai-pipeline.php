<?php
/**
 * DeSpace Studio — 3D Model & AI Render Pipeline
 *
 * @package DeSpace_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 1. Cho phép tải file .glb và .usdz lên WordPress Media
 */
add_filter( 'upload_mimes', function( $mimes ) {
	$mimes['glb']  = 'model/gltf-binary';
	$mimes['gltf'] = 'model/gltf+json';
	$mimes['usdz'] = 'model/vnd.usdz+zip';
	return $mimes;
} );

add_filter( 'wp_check_filetype_and_ext', function( $data, $file, $filename, $mimes ) {
	$ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
	if ( 'glb' === $ext ) {
		return array( 'ext' => 'glb', 'type' => 'model/gltf-binary', 'proper_filename' => $filename );
	}
	if ( 'usdz' === $ext ) {
		return array( 'ext' => 'usdz', 'type' => 'model/vnd.usdz+zip', 'proper_filename' => $filename );
	}
	return $data;
}, 10, 4 );

/**
 * Đảm bảo nạp wp.media trên trang sửa sản phẩm trong WP Admin
 */
add_action( 'admin_enqueue_scripts', function( $hook ) {
	global $post;
	if ( ( 'post.php' === $hook || 'post-new.php' === $hook ) && isset( $post->post_type ) && 'product' === $post->post_type ) {
		wp_enqueue_media();
	}
} );

/**
 * 2. Đăng ký Meta Box 3D & AR Management trong trang Admin Sản phẩm
 */
add_action( 'add_meta_boxes', function() {
	add_meta_box(
		'axgnud_3d_ai_metabox',
		'DeSpace Studio — Quản Lý Mô Hình 3D & Augmented Reality (AR)',
		'axgnud_render_3d_metabox_html',
		'product',
		'normal',
		'high'
	);
} );

function axgnud_render_3d_metabox_html( $post ) {
	if ( ! current_user_can( 'edit_products' ) ) {
		echo '<p>Bạn không có quyền chỉnh sửa thông tin 3D của sản phẩm.</p>';
		return;
	}

	wp_nonce_field( 'axgnud_save_3d_meta', 'axgnud_3d_nonce' );
	
	$glb_url   = get_post_meta( $post->ID, '_axgnud_model_glb', true )
		?: get_post_meta( $post->ID, '_glb_file', true )
		?: get_post_meta( $post->ID, '_ar_glb_url', true )
		?: get_post_meta( $post->ID, '_model_3d', true );
	$usdz_url  = get_post_meta( $post->ID, '_axgnud_model_usdz', true )
		?: get_post_meta( $post->ID, '_usdz_file', true )
		?: get_post_meta( $post->ID, '_ar_usdz_url', true );
	
	// Safety: Clear fake models
	if ( strpos( $glb_url, 'GlamVelvetSofa' ) !== false || strpos( $glb_url, 'SheenChair' ) !== false ) {
		$glb_url = '';
	}
	if ( strpos( $usdz_url, 'GlamVelvetSofa' ) !== false || strpos( $usdz_url, 'SheenChair' ) !== false ) {
		$usdz_url = '';
	}

	$ar_enabled = get_post_meta( $post->ID, '_ar_enabled', true );
	if ( '' === $ar_enabled ) {
		$ar_enabled = ! empty( $glb_url ) ? 'yes' : 'no';
	}
	
	$model_status = get_post_meta( $post->ID, '_axgnud_3d_status', true ) ?: ( ! empty( $glb_url ) ? 'published' : 'draft' );
	
	$width  = get_post_meta( $post->ID, '_width', true );
	$depth  = get_post_meta( $post->ID, '_length', true );
	$height = get_post_meta( $post->ID, '_height', true );

	$full_glb = ( $glb_url && strpos( $glb_url, 'http' ) !== 0 ) ? home_url( '/' . ltrim( $glb_url, '/' ) ) : $glb_url;
	?>
	<div style="padding: 16px 8px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
		<p style="margin-top:0; color:#57534e; font-size:13.5px; line-height:1.5;">
			Quản lý liên kết mô hình 3D (.GLB), Apple AR Quick Look (.USDZ) và cấu hình kích thước vật lý chuẩn xác cho từng sản phẩm. Mỗi sản phẩm được gắn định danh 3D độc lập, tuyệt đối không dùng model mặc định.
		</p>
		
		<table class="form-table" style="margin-top:0;">
			<!-- GLB File -->
			<tr>
				<th style="width:220px;"><label for="axgnud_model_glb"><strong>File 3D Model (.GLB)</strong></label></th>
				<td>
					<input type="text" id="axgnud_model_glb" name="axgnud_model_glb" value="<?php echo esc_attr( $glb_url ); ?>" class="regular-text" style="width:70%;" placeholder="/wp-content/uploads/3d/AXG-METRO-001/model.glb" />
					<button type="button" class="button" id="axgnud_upload_glb_btn">📁 Chọn / Tải file .GLB</button>
					<p class="description" style="margin-top:4px;">
						Định dạng chuẩn WebGL &amp; Android Scene Viewer / WebXR. Đường dẫn tương đối hoặc URL tuyệt đối.
					</p>
				</td>
			</tr>

			<!-- USDZ File -->
			<tr>
				<th><label for="axgnud_model_usdz"><strong>File Apple AR (.USDZ)</strong></label></th>
				<td>
					<input type="text" id="axgnud_model_usdz" name="axgnud_model_usdz" value="<?php echo esc_attr( $usdz_url ); ?>" class="regular-text" style="width:70%;" placeholder="/wp-content/uploads/3d/AXG-METRO-001/model.usdz" />
					<button type="button" class="button" id="axgnud_upload_usdz_btn">📁 Chọn / Tải file .USDZ</button>
					<p class="description" style="margin-top:4px;">
						Định dạng dành riêng cho Apple AR Quick Look trên iPhone / iPad (Safari).
					</p>
				</td>
			</tr>

			<!-- Physical Dimensions (cm) -->
			<tr>
				<th><strong>Kích Thước Thực Tế (cm)</strong></th>
				<td>
					<div style="display:flex; gap:16px; align-items:center;">
						<div>
							<label style="font-size:12px; color:#69645D;">Chiều Rộng (W):</label><br>
							<input type="number" step="any" name="axgnud_dim_width" value="<?php echo esc_attr( $width ); ?>" style="width:90px;" placeholder="114" /> cm
						</div>
						<div>
							<label style="font-size:12px; color:#69645D;">Chiều Sâu (D):</label><br>
							<input type="number" step="any" name="axgnud_dim_depth" value="<?php echo esc_attr( $depth ); ?>" style="width:90px;" placeholder="33" /> cm
						</div>
						<div>
							<label style="font-size:12px; color:#69645D;">Chiều Cao (H):</label><br>
							<input type="number" step="any" name="axgnud_dim_height" value="<?php echo esc_attr( $height ); ?>" style="width:90px;" placeholder="76" /> cm
						</div>
					</div>
					<p class="description" style="margin-top:4px;">
						Đồng bộ với thông số kỹ thuật và bounding box thực tế của mô hình trong AR.
					</p>
				</td>
			</tr>

			<!-- AR Enabled & Model Status -->
			<tr>
				<th><strong>Cấu Hình Kích Hoạt</strong></th>
				<td>
					<label style="margin-right:24px;">
						<input type="checkbox" name="axgnud_ar_enabled" value="yes" <?php checked( $ar_enabled, 'yes' ); ?> />
						<strong>Bật chức năng AR (Xem trong phòng)</strong>
					</label>

					<label style="margin-left:12px;">
						<strong>Trạng thái Model:</strong>
						<select name="axgnud_model_status" style="margin-left:6px;">
							<option value="published" <?php selected( $model_status, 'published' ); ?>>✓ Đã xuất bản (Published)</option>
							<option value="draft" <?php selected( $model_status, 'draft' ); ?>>⏳ Đang hoàn thiện (Draft / Coming Soon)</option>
							<option value="disabled" <?php selected( $model_status, 'disabled' ); ?>>✕ Tắt hiển thị (Disabled)</option>
						</select>
					</label>
				</td>
			</tr>

			<!-- 3D Preview Box -->
			<?php if ( ! empty( $full_glb ) ) : ?>
			<tr>
				<th><strong>Xem Trước Mô Hình 3D</strong></th>
				<td>
					<div style="width:100%; max-width:540px; height:280px; background:#FAF7F2; border:1px solid #D5C9BC; border-radius:12px; overflow:hidden;">
						<model-viewer src="<?php echo esc_url( $full_glb ); ?>"
							camera-controls
							auto-rotate
							shadow-intensity="1.1"
							exposure="1.0"
							style="width:100%; height:100%;">
						</model-viewer>
					</div>
					<p class="description" style="margin-top:6px; color:#15803d; font-weight:600;">
						✓ Mô hình đã được gắn kết và sẵn sàng hiển thị cho khách hàng.
					</p>
				</td>
			</tr>
			<?php endif; ?>
		</table>
	</div>

	<script>
	jQuery(function($){
		// Upload GLB handler
		$('#axgnud_upload_glb_btn').on('click', function(e){
			e.preventDefault();
			var frame = wp.media({ title: 'Chọn hoặc tải lên file 3D Model (.GLB)', multiple: false });
			frame.on('select', function(){
				var attachment = frame.state().get('selection').first().toJSON();
				$('#axgnud_model_glb').val(attachment.url);
			});
			frame.open();
		});

		// Upload USDZ handler
		$('#axgnud_upload_usdz_btn').on('click', function(e){
			e.preventDefault();
			var frame = wp.media({ title: 'Chọn hoặc tải lên file Apple AR (.USDZ)', multiple: false });
			frame.on('select', function(){
				var attachment = frame.state().get('selection').first().toJSON();
				$('#axgnud_model_usdz').val(attachment.url);
			});
			frame.open();
		});
	});
	</script>
	<?php
}

add_action( 'save_post_product', function( $post_id ) {
	if ( ! current_user_can( 'edit_products' ) ) {
		return;
	}
	if ( ! isset( $_POST['axgnud_3d_nonce'] ) || ! wp_verify_nonce( $_POST['axgnud_3d_nonce'], 'axgnud_save_3d_meta' ) ) {
		return;
	}

	if ( isset( $_POST['axgnud_model_glb'] ) ) {
		$glb = sanitize_text_field( wp_unslash( $_POST['axgnud_model_glb'] ) );
		// Discard fake models
		if ( strpos( $glb, 'GlamVelvetSofa' ) !== false || strpos( $glb, 'SheenChair' ) !== false ) {
			$glb = '';
		}
		update_post_meta( $post_id, '_axgnud_model_glb', $glb );
	}

	if ( isset( $_POST['axgnud_model_usdz'] ) ) {
		$usdz = sanitize_text_field( wp_unslash( $_POST['axgnud_model_usdz'] ) );
		if ( strpos( $usdz, 'GlamVelvetSofa' ) !== false || strpos( $usdz, 'SheenChair' ) !== false ) {
			$usdz = '';
		}
		update_post_meta( $post_id, '_axgnud_model_usdz', $usdz );
	}

	$ar_enabled = isset( $_POST['axgnud_ar_enabled'] ) ? 'yes' : 'no';
	update_post_meta( $post_id, '_ar_enabled', $ar_enabled );

	if ( isset( $_POST['axgnud_model_status'] ) ) {
		update_post_meta( $post_id, '_axgnud_3d_status', sanitize_text_field( $_POST['axgnud_model_status'] ) );
	}

	if ( isset( $_POST['axgnud_dim_width'] ) && '' !== $_POST['axgnud_dim_width'] ) {
		update_post_meta( $post_id, '_width', wc_clean( $_POST['axgnud_dim_width'] ) );
	}
	if ( isset( $_POST['axgnud_dim_depth'] ) && '' !== $_POST['axgnud_dim_depth'] ) {
		update_post_meta( $post_id, '_length', wc_clean( $_POST['axgnud_dim_depth'] ) );
	}
	if ( isset( $_POST['axgnud_dim_height'] ) && '' !== $_POST['axgnud_dim_height'] ) {
		update_post_meta( $post_id, '_height', wc_clean( $_POST['axgnud_dim_height'] ) );
	}

	if ( isset( $_POST['axgnud_fal_api_key'] ) ) {
		update_option( 'axgnud_fal_api_key', sanitize_text_field( $_POST['axgnud_fal_api_key'] ) );
	}
} );

/**
 * 3. Settings menu page in Settings -> DeSpace 3D AI
 */
add_action( 'admin_menu', function() {
	add_options_page(
		'DeSpace 3D AI Settings',
		'DeSpace 3D AI',
		'manage_options',
		'axgnud-3d-ai-settings',
		'axgnud_3d_ai_settings_page'
	);
} );

function axgnud_3d_ai_settings_page() {
	if ( isset( $_POST['axgnud_save_settings_nonce'] ) && wp_verify_nonce( $_POST['axgnud_save_settings_nonce'], 'axgnud_save_settings' ) ) {
		$fal_key = sanitize_text_field( $_POST['axgnud_fal_api_key'] ?? '' );
		update_option( 'axgnud_fal_api_key', $fal_key );
		echo '<div class="notice notice-success is-dismissible"><p>Cài đặt đã được lưu thành công.</p></div>';
	}
	$fal_key = get_option( 'axgnud_fal_api_key', '' );
	?>
	<div class="wrap">
		<h1>DeSpace Studio — 3D & AI Render Settings</h1>
		<p>Cấu hình API Key cho hệ thống AI Image-to-3D (Fal.ai Trellis / Tripo3D).</p>
		<form method="post" action="">
			<?php wp_nonce_field( 'axgnud_save_settings', 'axgnud_save_settings_nonce' ); ?>
			<table class="form-table">
				<tr>
					<th><label for="axgnud_fal_api_key">Fal.ai API Key (Trellis)</label></th>
					<td>
						<input type="password" name="axgnud_fal_api_key" id="axgnud_fal_api_key" value="<?php echo esc_attr( $fal_key ); ?>" class="regular-text" />
						<p class="description">Lấy API key tại <a href="https://fal.ai" target="_blank" rel="noopener">fal.ai</a>. Khi để trống, hệ thống sẽ sử dụng Procedural 3D Mesh + HTML5 Smart Cutout tự động.</p>
					</td>
				</tr>
			</table>
			<?php submit_button( 'Lưu Cài Đặt' ); ?>
		</form>
	</div>
	<?php
}

/**
 * 4. AJAX Endpoint gọi AI Image-to-3D (Fal.ai Trellis) và tải file .glb về WordPress
 */
add_action( 'wp_ajax_axgnud_generate_3d_model', function() {
	check_ajax_referer( 'axgnud_ai_3d_ajax', 'nonce' );
	$product_id = intval( $_POST['product_id'] ?? 0 );
	$api_key    = sanitize_text_field( $_POST['api_key'] ?? get_option( 'axgnud_fal_api_key', '' ) );

	if ( ! empty( $api_key ) ) {
		update_option( 'axgnud_fal_api_key', $api_key );
	}

	$image_url = get_the_post_thumbnail_url( $product_id, 'full' );
	if ( ! $image_url ) {
		wp_send_json_error( array( 'message' => 'Sản phẩm chưa có ảnh đại diện.' ) );
	}

	if ( empty( $api_key ) ) {
		update_post_meta( $product_id, '_axgnud_3d_status', 'procedural_3d_active' );
		wp_send_json_error( array( 'message' => 'Chưa nhập FAL_KEY: Đang dùng chế độ Smart 3D Mesh + Auto Cutout tự động.' ) );
	}

	// Gọi Fal.ai Trellis Image-to-3D API
	$response = wp_remote_post( 'https://fal.run/fal-ai/trellis', array(
		'timeout' => 120,
		'headers' => array(
			'Authorization' => 'Key ' . $api_key,
			'Content-Type'  => 'application/json',
		),
		'body' => wp_json_encode( array(
			'image_url'            => $image_url,
			'ss_guidance_strength' => 7.5,
			'slat_guidance_strength' => 3.0,
			'mesh_simplify'        => 0.95,
			'texture_size'         => 1024,
		) ),
	) );

	if ( is_wp_error( $response ) ) {
		wp_send_json_error( array( 'message' => 'Lỗi kết nối API: ' . $response->get_error_message() ) );
	}

	$body = json_decode( wp_remote_retrieve_body( $response ), true );
	$remote_glb = $body['model_mesh']['url'] ?? ( $body['mesh']['url'] ?? '' );

	if ( empty( $remote_glb ) ) {
		wp_send_json_error( array( 'message' => 'API không trả về file .glb (Kiểm tra lại URL ảnh phải là public HTTPS, không phải localhost).' ) );
	}

	// Tải file .glb về thư mục uploads của WordPress
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$tmp = download_url( $remote_glb );
	if ( is_wp_error( $tmp ) ) {
		update_post_meta( $product_id, '_axgnud_model_glb', esc_url_raw( $remote_glb ) );
		wp_send_json_success( array( 'glb_url' => $remote_glb ) );
	}

	$file_array = array(
		'name'     => 'axgnud-3d-product-' . $product_id . '.glb',
		'tmp_name' => $tmp,
	);
	$attach_id = media_handle_sideload( $file_array, $product_id );
	if ( is_wp_error( $attach_id ) ) {
		@unlink( $tmp );
		update_post_meta( $product_id, '_axgnud_model_glb', esc_url_raw( $remote_glb ) );
		wp_send_json_success( array( 'glb_url' => $remote_glb ) );
	}

	$local_glb_url = wp_get_attachment_url( $attach_id );
	update_post_meta( $product_id, '_axgnud_model_glb', esc_url_raw( $local_glb_url ) );
	update_post_meta( $product_id, '_axgnud_3d_status', 'ai_glb_ready' );

	wp_send_json_success( array( 'glb_url' => $local_glb_url ) );
} );

/**
 * 5. Helper lấy dữ liệu 3D đầy đủ của sản phẩm
 */
function axgnud_get_product_3d_data( $product_id ) {
	global $wpdb;
	$product = wc_get_product( $product_id );
	if ( ! $product ) {
		return array();
	}

	$glb_url  = '';
	$usdz_url = '';

	// 1. First priority: Check centralized wp_axgnud_product_3d_models catalog table
	$table_3d = $wpdb->prefix . 'axgnud_product_3d_models';
	if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_3d'" ) === $table_3d ) {
		$db_model = $wpdb->get_row( $wpdb->prepare( "SELECT glb_url, usdz_url, ar_enabled, model_status FROM $table_3d WHERE product_id = %d LIMIT 1", $product_id ) );
		if ( $db_model && $db_model->model_status === 'published' && ! empty( $db_model->glb_url ) ) {
			$glb_url  = $db_model->glb_url;
			$usdz_url = $db_model->usdz_url;
		}
	}

	// 2. Fallback: Check postmeta keys
	if ( empty( $glb_url ) ) {
		$glb_url = get_post_meta( $product_id, '_axgnud_model_glb', true )
			?: get_post_meta( $product_id, '_ax_model_glb_url', true )
			?: get_post_meta( $product_id, '_glb_file', true )
			?: get_post_meta( $product_id, '_ar_glb_url', true )
			?: get_post_meta( $product_id, '_model_3d', true )
			?: get_post_meta( $product_id, '_model_glb', true );
	}
	if ( is_numeric( $glb_url ) ) {
		$glb_url = wp_get_attachment_url( $glb_url );
	}

	if ( empty( $usdz_url ) ) {
		$usdz_url = get_post_meta( $product_id, '_axgnud_model_usdz', true )
			?: get_post_meta( $product_id, '_ax_model_usdz_url', true )
			?: get_post_meta( $product_id, '_usdz_file', true )
			?: get_post_meta( $product_id, '_ar_usdz_url', true )
			?: get_post_meta( $product_id, '_model_usdz', true );
	}
	if ( is_numeric( $usdz_url ) ) {
		$usdz_url = wp_get_attachment_url( $usdz_url );
	}

	// Safety: Discard fake sample models
	if ( strpos( $glb_url, 'GlamVelvetSofa' ) !== false || strpos( $glb_url, 'SheenChair' ) !== false ) {
		$glb_url = '';
	}
	if ( strpos( $usdz_url, 'GlamVelvetSofa' ) !== false || strpos( $usdz_url, 'SheenChair' ) !== false ) {
		$usdz_url = '';
	}

	if ( $glb_url && strpos( $glb_url, 'http' ) !== 0 ) {
		$glb_url = home_url( '/' . ltrim( $glb_url, '/' ) );
	}
	if ( $usdz_url && strpos( $usdz_url, 'http' ) !== 0 ) {
		$usdz_url = home_url( '/' . ltrim( $usdz_url, '/' ) );
	}

	$image_url = wp_get_attachment_image_url( $product->get_image_id(), 'full' );
	if ( ! $image_url ) {
		$image_url = wc_placeholder_img_src( 'full' );
	}

	// Xác định category type
	$slug = strtolower( $product->get_slug() );
	$title = mb_strtolower( $product->get_name(), 'UTF-8' );
	$terms = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'names' ) );
	$cat_names = is_array( $terms ) ? mb_strtolower( implode( ' ', $terms ), 'UTF-8' ) : '';
	$combined = $title . ' ' . $slug . ' ' . $cat_names;

	$cat_type = 'cabinet';
	if (
		strpos( $title, 'tủ' ) !== false ||
		strpos( $title, 'kệ' ) !== false ||
		strpos( $slug, 'cabinet' ) !== false ||
		strpos( $slug, 'storage' ) !== false ||
		strpos( $slug, 'rack' ) !== false ||
		strpos( $slug, 'metro' ) !== false ||
		strpos( $combined, 'shoe' ) !== false
	) {
		$cat_type = 'cabinet';
	} elseif (
		strpos( $title, 'ghế' ) !== false ||
		strpos( $slug, 'chair' ) !== false ||
		strpos( $slug, 'armchair' ) !== false ||
		strpos( $slug, 'lounge' ) !== false ||
		strpos( $slug, 'stool' ) !== false ||
		strpos( $title, 'chair' ) !== false ||
		strpos( $title, 'armchair' ) !== false
	) {
		if ( strpos( $title, 'sofa' ) !== false || strpos( $slug, 'sofa' ) !== false || strpos( $slug, 'sectional' ) !== false ) {
			$cat_type = 'sofa';
		} else {
			$cat_type = 'chair';
		}
	} elseif ( strpos( $title, 'sofa' ) !== false || strpos( $slug, 'sofa' ) !== false || strpos( $slug, 'couch' ) !== false ) {
		$cat_type = 'sofa';
	} elseif ( strpos( $title, 'bàn' ) !== false || strpos( $slug, 'table' ) !== false || strpos( $slug, 'desk' ) !== false ) {
		$cat_type = 'table';
	} elseif ( strpos( $title, 'đèn' ) !== false || strpos( $slug, 'lamp' ) !== false || strpos( $slug, 'light' ) !== false ) {
		$cat_type = 'lamp';
	} elseif ( strpos( $title, 'giường' ) !== false || strpos( $slug, 'bed' ) !== false ) {
		$cat_type = 'bed';
	} elseif ( strpos( $combined, 'sofa' ) !== false ) {
		$cat_type = 'sofa';
	} elseif ( strpos( $combined, 'table' ) !== false || strpos( $combined, 'desk' ) !== false ) {
		$cat_type = 'table';
	} elseif ( strpos( $combined, 'lamp' ) !== false || strpos( $combined, 'light' ) !== false ) {
		$cat_type = 'lamp';
	} elseif ( strpos( $combined, 'chair' ) !== false ) {
		$cat_type = 'chair';
	}

	// Màu sắc mặc định
	$default_color = '#1c1917';
	if ( strpos( $combined, 'white' ) !== false || strpos( $combined, 'ivory' ) !== false ) {
		$default_color = '#f5f5f0';
	} elseif ( strpos( $combined, 'brown' ) !== false || strpos( $combined, 'walnut' ) !== false || strpos( $combined, 'wood' ) !== false ) {
		$default_color = '#5c3a21';
	}

	return array(
		'product_id'    => $product_id,
		'glb_url'       => $glb_url ? esc_url( $glb_url ) : '',
		'usdz_url'      => $usdz_url ? esc_url( $usdz_url ) : '',
		'has_3d'        => ! empty( $glb_url ),
		'image_url'     => $image_url ? esc_url( $image_url ) : '',
		'category_type' => $cat_type,
		'default_color' => $default_color,
		'name'          => $product->get_name(),
		'price_html'    => $product->get_price_html(),
	);
}
