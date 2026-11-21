<?php
/**
 * DeSpace Luxury — Centralized 3D & AR Catalog Management Dashboard
 *
 * Provides a unified admin interface for managing 3D models (GLB, glTF, USDZ),
 * AR configurations, physical dimensions, and status across the entire product catalog.
 *
 * @package DeSpace_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Helper: Check whether the current user can manage 3D & AR catalog items.
 */
function axgnud_user_can_manage_3d() {
	return current_user_can( 'manage_despace_3d' )
		|| current_user_can( 'manage_woocommerce' )
		|| current_user_can( 'manage_options' );
}

/**
 * 1. Register Admin Menu Item (standalone fallback when unified DeSpace Cockpit is not active).
 */
add_action( 'admin_menu', 'axgnud_register_3d_admin_menu' );
function axgnud_register_3d_admin_menu() {
	if ( function_exists( 'despace_register_admin_hub_menu' ) ) {
		return;
	}
	$cap = current_user_can( 'manage_despace_3d' ) ? 'manage_despace_3d' : 'manage_woocommerce';
	add_menu_page(
		'3D & AR Management',
		'3D & AR Studio',
		$cap,
		'despace-3d-manager',
		'axgnud_render_3d_manager_page',
		'dashicons-visibility',
		57
	);
}

/**
 * 2. Enqueue Media & Model Viewer for Admin Page
 */
add_action( 'admin_enqueue_scripts', 'axgnud_enqueue_3d_admin_assets' );
function axgnud_enqueue_3d_admin_assets( $hook ) {
	if ( false === strpos( (string) $hook, 'despace-3d-manager' ) ) {
		return;
	}

	wp_enqueue_media();
	wp_enqueue_script(
		'google-model-viewer',
		get_stylesheet_directory_uri() . '/assets/vendor/model-viewer/model-viewer.min.js',
		array(),
		'3.5.0',
		true
	);
	add_filter( 'script_loader_tag', function( $tag, $handle, $src ) {
		if ( 'google-model-viewer' === $handle ) {
			return '<script type="module" src="' . esc_url( $src ) . '"></script>';
		}
		return $tag;
	}, 10, 3 );
}

/**
 * Early permission guard for admin-only tabs inside Xưởng 3D & AR Studio
 * so unauthorized requests receive a true HTTP 403 before admin headers are emitted.
 */
add_action( 'admin_init', 'axgnud_guard_3d_manager_tabs', 4 );
function axgnud_guard_3d_manager_tabs() {
	if ( ! is_admin() || wp_doing_ajax() ) {
		return;
	}
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
	if ( 'despace-3d-manager' !== $page ) {
		return;
	}
	$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'catalog';
	if ( in_array( $tab, array( 'desktop', 'ai' ), true ) && ! current_user_can( 'manage_options' ) ) {
		wp_die(
			'Bạn cần quyền Quản trị viên (manage_options) để truy cập mục này.',
			'Truy cập bị từ chối',
			array( 'response' => 403 )
		);
	}
}

/**
 * 3. Handle CSV Export
 */
add_action( 'admin_init', 'axgnud_handle_3d_csv_export' );
function axgnud_handle_3d_csv_export() {
	if ( ! isset( $_GET['page'] ) || 'despace-3d-manager' !== $_GET['page'] || ! isset( $_GET['action'] ) || 'export_csv' !== $_GET['action'] ) {
		return;
	}

	if ( ! axgnud_user_can_manage_3d() ) {
		wp_die( 'Access denied', 403 );
	}

	check_admin_referer( 'axgnud_export_csv_nonce' );

	global $wpdb;
	$t_despace = $wpdb->prefix . 'despace_product_3d_models'; $t_axgnud = $wpdb->prefix . 'axgnud_product_3d_models'; $table = ( $wpdb->get_var( "SHOW TABLES LIKE '$t_despace'" ) === $t_despace ) ? $t_despace : $t_axgnud;
	$rows  = $wpdb->get_results( "SELECT * FROM $table ORDER BY product_id ASC" );

	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=despace_3d_catalog_mapping_' . date( 'Ymd_His' ) . '.csv' );

	$output = fopen( 'php://output', 'w' );
	fputcsv( $output, array( 'product_id', 'sku', 'product_name', 'category', 'length', 'width', 'height', 'unit', 'glb_url', 'usdz_url', 'model_status', 'ar_enabled', 'version' ) );

	$csv_safe = static function ( $val ) {
		$s = (string) $val;
		if ( '' !== $s && preg_match( '/^[=+\-@\t\r]/', $s ) ) {
			return "'" . $s;
		}
		return $s;
	};

	foreach ( $rows as $r ) {
		$prod = wc_get_product( $r->product_id );
		$name = $prod ? $prod->get_name() : '';
		$cats = $prod ? wp_get_post_terms( $r->product_id, 'product_cat', array( 'fields' => 'names' ) ) : array();
		$cat_str = is_array( $cats ) ? implode( ', ', $cats ) : '';

		fputcsv( $output, array(
			$r->product_id,
			$csv_safe( $r->sku ),
			$csv_safe( $name ),
			$csv_safe( $cat_str ),
			$r->width,  // width corresponds to length/face in db
			$r->depth,
			$r->height,
			$csv_safe( $r->unit ),
			$csv_safe( $r->glb_url ),
			$csv_safe( $r->usdz_url ),
			$csv_safe( $r->model_status ),
			$r->ar_enabled,
			$csv_safe( $r->version )
		) );
	}

	fclose( $output );
	exit;
}

/**
 * 4. AJAX Handlers: Save 3D Data
 */
add_action( 'wp_ajax_despace_save_product_3d', 'axgnud_ajax_save_product_3d' );
add_action( 'wp_ajax_axgnud_save_product_3d', 'axgnud_ajax_save_product_3d' );
function axgnud_ajax_save_product_3d() {
	if ( ! check_ajax_referer( 'despace_3d_manager_nonce', 'nonce', false ) && ! check_ajax_referer( 'axgnud_3d_manager_nonce', 'nonce', false ) ) { wp_send_json_error( array( 'message' => 'Invalid security token' ) ); }

	if ( ! axgnud_user_can_manage_3d() ) {
		wp_send_json_error( array( 'message' => 'Quyền hạn không hợp lệ.' ), 403 );
	}

	global $wpdb;
	$t_despace = $wpdb->prefix . 'despace_product_3d_models'; $t_axgnud = $wpdb->prefix . 'axgnud_product_3d_models'; $table = ( $wpdb->get_var( "SHOW TABLES LIKE '$t_despace'" ) === $t_despace ) ? $t_despace : $t_axgnud;

	$product_id   = intval( $_POST['product_id'] ?? 0 );
	$glb_url      = sanitize_text_field( wp_unslash( $_POST['glb_url'] ?? '' ) );
	$usdz_url     = sanitize_text_field( wp_unslash( $_POST['usdz_url'] ?? '' ) );
	$width        = floatval( $_POST['width'] ?? 0 );
	$depth        = floatval( $_POST['depth'] ?? 0 );
	$height       = floatval( $_POST['height'] ?? 0 );
	$unit         = sanitize_text_field( $_POST['unit'] ?? 'cm' );
	$material     = sanitize_text_field( $_POST['material'] ?? '' );
	$color        = sanitize_text_field( $_POST['color'] ?? '' );
	$version      = sanitize_text_field( $_POST['version'] ?? '1.0' );
	$model_status = sanitize_text_field( $_POST['model_status'] ?? 'published' );
	$ar_enabled   = ! empty( $_POST['ar_enabled'] ) ? 1 : 0;

	if ( ! $product_id ) {
		wp_send_json_error( array( 'message' => 'Product ID không hợp lệ.' ) );
	}

	// Safety: Reject fake sofa/chair
	if ( strpos( $glb_url, 'GlamVelvetSofa' ) !== false || strpos( $glb_url, 'SheenChair' ) !== false ) {
		$glb_url = '';
	}
	if ( strpos( $usdz_url, 'GlamVelvetSofa' ) !== false || strpos( $usdz_url, 'SheenChair' ) !== false ) {
		$usdz_url = '';
	}

	// Update Post Meta (WooCommerce Cache)
	update_post_meta( $product_id, '_despace_model_glb', $glb_url );
	update_post_meta( $product_id, '_axgnud_model_glb', $glb_url );
	update_post_meta( $product_id, '_despace_model_usdz', $usdz_url );
	update_post_meta( $product_id, '_axgnud_model_usdz', $usdz_url );
	update_post_meta( $product_id, '_ar_enabled', $ar_enabled ? 'yes' : 'no' );
	update_post_meta( $product_id, '_despace_3d_status', $model_status );
	update_post_meta( $product_id, '_axgnud_3d_status', $model_status );
	update_post_meta( $product_id, '_despace_3d_version', $version );
	update_post_meta( $product_id, '_axgnud_3d_version', $version );
	if ( $width > 0 ) update_post_meta( $product_id, '_length', $width );
	if ( $depth > 0 ) update_post_meta( $product_id, '_width', $depth );
	if ( $height > 0 ) update_post_meta( $product_id, '_height', $height );

	// Update Custom Table
	$existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE product_id = %d", $product_id ) );
	$current_user_id = get_current_user_id();

	if ( $existing ) {
		$wpdb->update( $table, array(
			'glb_url'      => $glb_url,
			'usdz_url'     => $usdz_url,
			'width'        => $width,
			'depth'        => $depth,
			'height'       => $height,
			'unit'         => $unit,
			'material'     => $material,
			'color'        => $color,
			'version'      => $version,
			'model_status' => $model_status,
			'ar_enabled'   => $ar_enabled,
			'updated_by'   => $current_user_id
		), array( 'id' => $existing ) );
	} else {
		$prod = wc_get_product( $product_id );
		$sku  = $prod ? $prod->get_sku() : 'AXG-PROD-' . $product_id;
		$wpdb->insert( $table, array(
			'product_id'   => $product_id,
			'variant_id'   => 0,
			'sku'          => $sku,
			'glb_url'      => $glb_url,
			'usdz_url'     => $usdz_url,
			'width'        => $width,
			'depth'        => $depth,
			'height'       => $height,
			'unit'         => $unit,
			'material'     => $material,
			'color'        => $color,
			'version'      => $version,
			'model_status' => $model_status,
			'ar_enabled'   => $ar_enabled,
			'updated_by'   => $current_user_id
		) );
	}

	wp_send_json_success( array(
		'message'    => 'Đã lưu cấu hình 3D cho sản phẩm #' . $product_id . ' thành công!',
		'product_id' => $product_id
	) );
}

/**
 * 5. AJAX Handlers: Sync Catalog (Auto-discover new products)
 */
add_action( 'wp_ajax_despace_sync_catalog_3d', 'axgnud_ajax_sync_catalog_3d' );
add_action( 'wp_ajax_axgnud_sync_catalog_3d', 'axgnud_ajax_sync_catalog_3d' );
function axgnud_ajax_sync_catalog_3d() {
	if ( ! check_ajax_referer( 'despace_3d_manager_nonce', 'nonce', false ) && ! check_ajax_referer( 'axgnud_3d_manager_nonce', 'nonce', false ) ) { wp_send_json_error( array( 'message' => 'Invalid security token' ) ); }

	if ( ! axgnud_user_can_manage_3d() ) {
		wp_send_json_error( array( 'message' => 'Quyền hạn không hợp lệ.' ), 403 );
	}

	global $wpdb;
	$t_despace = $wpdb->prefix . 'despace_product_3d_models'; $t_axgnud = $wpdb->prefix . 'axgnud_product_3d_models'; $table = ( $wpdb->get_var( "SHOW TABLES LIKE '$t_despace'" ) === $t_despace ) ? $t_despace : $t_axgnud;

	$products = wc_get_products( array( 'limit' => -1, 'status' => 'publish' ) );
	$inserted = 0;
	$updated  = 0;

	foreach ( $products as $p ) {
		$pid    = $p->get_id();
		$sku    = $p->get_sku() ?: ( 'AXG-PROD-' . $pid );
		$width  = floatval( $p->get_length() ?: 0 );
		$depth  = floatval( $p->get_width() ?: 0 );
		$height = floatval( $p->get_height() ?: 0 );

		$glb        = get_post_meta( $pid, '_axgnud_model_glb', true ) ?: '';
		$usdz       = get_post_meta( $pid, '_axgnud_model_usdz', true ) ?: '';
		$ar_meta    = get_post_meta( $pid, '_ar_enabled', true );
		$ar_enabled = ( ! empty( $glb ) && ( 'yes' === $ar_meta || '1' === $ar_meta || true === $ar_meta ) ) ? 1 : 0;
		$status     = ! empty( $glb ) ? 'published' : 'missing';

		$existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE product_id = %d", $pid ) );
		if ( $existing ) {
			$wpdb->update( $table, array(
				'sku'   => $sku,
				'width' => $width,
				'depth' => $depth,
				'height'=> $height
			), array( 'id' => $existing ) );
			$updated++;
		} else {
			$wpdb->insert( $table, array(
				'product_id'   => $pid,
				'variant_id'   => 0,
				'sku'          => $sku,
				'glb_url'      => $glb,
				'usdz_url'     => $usdz,
				'width'        => $width,
				'depth'        => $depth,
				'height'       => $height,
				'unit'         => 'cm',
				'model_status' => $status,
				'ar_enabled'   => $ar_enabled
			) );
			$inserted++;
		}
	}

	wp_send_json_success( array(
		'message' => "Đồng bộ hoàn tất: Đã kiểm tra " . count($products) . " sản phẩm ($inserted mới, $updated cập nhật)!"
	) );
}

/**
 * 6. Public REST/AJAX endpoint for Dynamic Single Product 3D fetch
 */
add_action( 'wp_ajax_despace_get_product_3d_dynamic', 'axgnud_ajax_get_product_3d_dynamic' );
add_action( 'wp_ajax_axgnud_get_product_3d_dynamic', 'axgnud_ajax_get_product_3d_dynamic' );
add_action( 'wp_ajax_nopriv_despace_get_product_3d_dynamic', 'axgnud_ajax_get_product_3d_dynamic' );
add_action( 'wp_ajax_nopriv_axgnud_get_product_3d_dynamic', 'axgnud_ajax_get_product_3d_dynamic' );
function axgnud_ajax_get_product_3d_dynamic() {
	$product_id = absint( $_GET['product_id'] ?? 0 );
	if ( ! $product_id || 'product' !== get_post_type( $product_id ) ) {
		wp_send_json_error( array( 'message' => 'Product not found' ), 404 );
	}
	if ( 'publish' !== get_post_status( $product_id ) && ! current_user_can( 'read_post', $product_id ) ) {
		wp_send_json_error( array( 'message' => 'Product not found' ), 404 );
	}

	$data = function_exists( 'ax_get_product_3d_info' ) ? ax_get_product_3d_info( $product_id ) : array();
	wp_send_json_success( $data );
}

/**
 * 7. Render Admin Dashboard Page (Xưởng 3D & AR Studio: 3 tabs — catalog | desktop | ai)
 */
function axgnud_render_3d_manager_page() {
	if ( ! axgnud_user_can_manage_3d() ) {
		wp_die( 'Bạn không có quyền truy cập Xưởng 3D & AR Studio.', 403 );
	}

	global $wpdb;

	$can_admin   = current_user_can( 'manage_options' );
	$active_tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'catalog';
	if ( ! in_array( $active_tab, array( 'catalog', 'desktop', 'ai' ), true ) ) {
		$active_tab = 'catalog';
	}
	if ( in_array( $active_tab, array( 'desktop', 'ai' ), true ) && ! $can_admin ) {
		wp_die( 'Bạn cần quyền Quản trị viên (manage_options) để truy cập mục này.', 403 );
	}

	$has_cockpit = function_exists( 'despace_render_admin_cockpit_header' );
	$csv_url     = wp_nonce_url( admin_url( 'admin.php?page=despace-3d-manager&action=export_csv' ), 'axgnud_export_csv_nonce' );

	$render_studio_shell = static function ( string $tab ) use ( $has_cockpit, $can_admin, $csv_url ) {
		if ( $has_cockpit ) {
			$actions = array();
			if ( 'catalog' === $tab ) {
				$actions[] = array(
					'label' => 'Đồng Bộ Danh Mục',
					'type'  => 'button',
					'id'    => 'axBtnSyncCatalog',
					'style' => 'primary',
				);
				$actions[] = array(
					'label' => 'Xuất File CSV',
					'url'   => $csv_url,
					'style' => 'ghost',
				);
			} else {
				$actions[] = array(
					'label' => '← Danh mục 3D/AR',
					'url'   => admin_url( 'admin.php?page=despace-3d-manager&tab=catalog' ),
					'style' => 'ghost',
				);
			}
			$actions[] = array(
				'label'  => 'Xem cửa hàng ↗',
				'url'    => home_url( '/' ),
				'style'  => 'ghost',
				'target' => '_blank',
			);
			despace_render_admin_cockpit_header(
				'despace-3d-manager',
				'Xưởng 3D & AR Studio',
				'Quản trị toàn bộ tài sản 3D (.GLB), Apple AR Quick Look (.USDZ), kết nối Desktop 3D Studio và cấu hình Cloud AI Image-to-3D.',
				$actions
			);
		}
		?>
		<nav class="ds-subtabs" aria-label="Phân mục Xưởng 3D & AR">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=despace-3d-manager&tab=catalog' ) ); ?>" class="ds-subtabs__item <?php echo 'catalog' === $tab ? 'is-active' : ''; ?>">
				Danh mục 3D &amp; AR (GLB/USDZ)
			</a>
			<?php if ( $can_admin ) : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=despace-3d-manager&tab=desktop' ) ); ?>" class="ds-subtabs__item <?php echo 'desktop' === $tab ? 'is-active' : ''; ?>">
					Kết nối Desktop 3D Studio
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=despace-3d-manager&tab=ai' ) ); ?>" class="ds-subtabs__item <?php echo 'ai' === $tab ? 'is-active' : ''; ?>">
					Cài đặt AI Concierge &amp; Cloud 3D
				</a>
			<?php endif; ?>
		</nav>
		<?php
	};

	// TAB 2: Desktop 3D Studio Connection & Sync Jobs (Admin only)
	if ( 'desktop' === $active_tab ) {
		$is_const_key = defined( 'DESPACE_DESKTOP_SYNC_KEY' ) && '' !== trim( (string) DESPACE_DESKTOP_SYNC_KEY );
		$sync_key     = $is_const_key ? trim( (string) DESPACE_DESKTOP_SYNC_KEY ) : trim( (string) get_option( 'despace_desktop_sync_key', '' ) );
		$rest_base    = rest_url( 'despace/v1/' );
		$jobs_table   = $wpdb->prefix . 'despace_3d_sync_jobs';
		$has_jobs_tbl = ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $jobs_table ) ) ) === $jobs_table );
		$recent_jobs  = $has_jobs_tbl ? $wpdb->get_results( "SELECT * FROM {$jobs_table} ORDER BY id DESC LIMIT 15" ) : array();
		?>
		<div class="wrap <?php echo $has_cockpit ? 'ds-cockpit-wrap ' : ''; ?>ax-admin-wrap">
			<?php $render_studio_shell( 'desktop' ); ?>

			<?php if ( isset( $_GET['key_rotated'] ) && '1' === $_GET['key_rotated'] ) : ?>
				<div class="notice notice-success is-dismissible" style="margin:0 0 18px;">
					<p><strong>Đã tạo khóa kết nối mới!</strong> Hãy cập nhật khóa <code>X-DeSpace-Desktop-Key</code> mới này vào ứng dụng DeSpace 3D Studio Desktop.</p>
				</div>
			<?php endif; ?>

			<section class="ds-panel">
				<div class="ds-panel__header">
					<div>
						<h2 class="ds-panel__title">Thông số kết nối DeSpace 3D Studio Desktop</h2>
						<p class="ds-panel__desc">Dùng URL và khóa 64-ký tự dưới đây để đồng bộ mô hình GLB/USDZ hai chiều giữa Desktop App và WordPress.</p>
					</div>
					<?php if ( ! $is_const_key ) : ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Tạo khóa mới sẽ vô hiệu hóa khóa cũ ngay lập tức. Bạn chắc chắn muốn xoay khóa?');">
							<?php wp_nonce_field( 'despace_rotate_desktop_key_nonce' ); ?>
							<input type="hidden" name="action" value="despace_rotate_desktop_key" />
							<button type="submit" class="ds-btn ds-btn--dark">Tạo khóa mới (Rotate Key)</button>
						</form>
					<?php endif; ?>
				</div>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="ds-rest-base-url">REST API Base URL</label></th>
						<td>
							<div class="ds-copy-group">
								<input type="text" id="ds-rest-base-url" value="<?php echo esc_attr( $rest_base ); ?>" readonly />
								<button type="button" class="ds-btn ds-btn--outline" onclick="navigator.clipboard && navigator.clipboard.writeText(document.getElementById('ds-rest-base-url').value); this.textContent='Đã sao chép ✓';">Sao chép URL</button>
								<button type="button" class="ds-btn ds-btn--dark" id="ds-test-desktop-health">Kiểm tra kết nối</button>
							</div>
							<div id="ds-desktop-health-result" style="margin-top:8px; font-size:13px;"></div>
							<p class="description">Health check: <code><?php echo esc_html( rest_url( 'despace/v1/desktop-3d/health' ) ); ?></code> · Jobs: <code><?php echo esc_html( rest_url( 'despace/v1/desktop-3d/jobs' ) ); ?></code></p>
							<script>
							(function(){
								var btn = document.getElementById('ds-test-desktop-health');
								var out = document.getElementById('ds-desktop-health-result');
								if (!btn || !out) return;
								btn.addEventListener('click', function(){
									var t0 = performance.now();
									out.innerHTML = '<span class="ds-pill ds-pill--info">Đang kiểm tra API...</span>';
									fetch(<?php echo wp_json_encode( esc_url_raw( rest_url( 'despace/v1/desktop-3d/health' ) ) ); ?>, {
										headers: { 'X-WP-Nonce': <?php echo wp_json_encode( wp_create_nonce( 'wp_rest' ) ); ?> }
									})
									.then(function(r){ return r.json().then(function(j){ return { ok: r.ok, status: r.status, data: j }; }); })
									.then(function(res){
										var ms = Math.round(performance.now() - t0);
										if (res.ok && res.data && res.data.status === 'online') {
											out.innerHTML = '<span class="ds-pill ds-pill--success">✓ Online (' + ms + 'ms) — ' + String(res.data.service || 'Desktop 3D Sync API').replace(/</g,'&lt;') + '</span>';
										} else {
											out.innerHTML = '<span class="ds-pill ds-pill--danger">Lỗi HTTP ' + res.status + '</span>';
										}
									})
									.catch(function(){
										out.innerHTML = '<span class="ds-pill ds-pill--danger">Không kết nối được REST endpoint</span>';
									});
								});
							})();
							</script>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="ds-sync-key-field">Sync API Key (Header <code>X-DeSpace-Desktop-Key</code>)</label></th>
						<td>
							<div class="ds-copy-group">
								<input type="password" id="ds-sync-key-field" value="<?php echo esc_attr( $sync_key ); ?>" readonly autocomplete="off" />
								<button type="button" class="ds-btn ds-btn--outline" onclick="var f=document.getElementById('ds-sync-key-field'); if(f.type==='password'){f.type='text';this.textContent='Ẩn khóa';}else{f.type='password';this.textContent='Hiện khóa';}">Hiện khóa</button>
								<button type="button" class="ds-btn ds-btn--outline" onclick="navigator.clipboard && navigator.clipboard.writeText(document.getElementById('ds-sync-key-field').value); this.textContent='Đã sao chép ✓';">Sao chép khóa</button>
							</div>
							<?php if ( $is_const_key ) : ?>
								<p class="description">Khóa đang được cấu hình cố định qua hằng số <code>DESPACE_DESKTOP_SYNC_KEY</code> trong <code>wp-config.php</code> / biến môi trường.</p>
							<?php elseif ( '' === $sync_key ) : ?>
								<p class="description" style="color:#b91c1c;">Chưa có khóa đồng bộ. Bấm <strong>Tạo khóa mới</strong> ở góc trên bên phải để khởi tạo ngay.</p>
							<?php else : ?>
								<p class="description">Khóa bảo mật 64 ký tự hex (256-bit) được lưu riêng trong tùy chọn không tự nạp (<code>autoload = false</code>).</p>
							<?php endif; ?>
						</td>
					</tr>
				</table>
			</section>

			<section class="ds-panel">
				<div class="ds-panel__header">
					<div>
						<h2 class="ds-panel__title">Lịch sử đồng bộ Desktop 3D gần đây (15 job mới nhất)</h2>
						<p class="ds-panel__desc">Theo dõi trạng thái dựng mesh, số đỉnh/mặt và kết quả xuất bản GLB/USDZ từ bảng <code><?php echo esc_html( $jobs_table ); ?></code>.</p>
					</div>
				</div>
				<?php if ( ! empty( $recent_jobs ) ) : ?>
					<div class="ds-table-wrap">
						<table class="ds-table">
							<thead>
								<tr>
									<th>Job UUID</th>
									<th>Sản phẩm / SKU</th>
									<th>Trạng thái</th>
									<th>Tiến độ</th>
									<th>Đỉnh / Mặt</th>
									<th>Nguồn</th>
									<th>Cập nhật</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $recent_jobs as $job ) : ?>
									<?php
									$st     = (string) ( $job->status ?? 'pending' );
									$st_cls = 'ds-pill--neutral';
									if ( in_array( $st, array( 'completed', 'published' ), true ) ) {
										$st_cls = 'ds-pill--success';
									} elseif ( in_array( $st, array( 'failed', 'error' ), true ) ) {
										$st_cls = 'ds-pill--danger';
									} elseif ( in_array( $st, array( 'processing', 'running', 'reconstructing' ), true ) ) {
										$st_cls = 'ds-pill--info';
									} else {
										$st_cls = 'ds-pill--warning';
									}
									?>
									<tr>
										<td><code><?php echo esc_html( substr( (string) ( $job->job_uuid ?? '' ), 0, 14 ) ); ?></code></td>
										<td>
											<strong><?php echo esc_html( $job->product_name ?: ( '#' . (int) $job->product_id ) ); ?></strong>
											<div style="font-size:12px; color:#57534e;"><?php echo esc_html( $job->sku ?: '—' ); ?></div>
										</td>
										<td><span class="ds-pill <?php echo esc_attr( $st_cls ); ?>"><?php echo esc_html( $st ); ?></span></td>
										<td><?php echo esc_html( (int) ( $job->progress ?? 0 ) . '%' ); ?></td>
										<td><?php echo esc_html( number_format_i18n( (int) ( $job->vertex_count ?? 0 ) ) . ' / ' . number_format_i18n( (int) ( $job->face_count ?? 0 ) ) ); ?></td>
										<td><code><?php echo esc_html( $job->source ?? 'web_admin' ); ?></code></td>
										<td><?php echo esc_html( $job->updated_at ?? $job->created_at ?? '—' ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php else : ?>
					<p style="margin:0; color:#57534e;">Chưa có phiên đồng bộ nào từ DeSpace 3D Studio Desktop. Khi bạn gửi job từ trang chỉnh sửa sản phẩm hoặc đẩy mô hình từ ứng dụng Desktop, lịch sử sẽ hiển thị tại đây.</p>
				<?php endif; ?>
			</section>
		</div>
		<?php
		return;
	}

	// TAB 3: AI Concierge & Cloud 3D Settings (Admin only)
	if ( 'ai' === $active_tab ) {
		?>
		<div class="wrap <?php echo $has_cockpit ? 'ds-cockpit-wrap ' : ''; ?>ax-admin-wrap">
			<?php $render_studio_shell( 'ai' ); ?>
			<?php
			if ( function_exists( 'axgnud_3d_ai_settings_page' ) ) {
				axgnud_3d_ai_settings_page( true );
			} else {
				echo '<div class="notice notice-warning"><p>Không tìm thấy module cài đặt AI 3D.</p></div>';
			}
			?>
		</div>
		<?php
		return;
	}

	// TAB 1: Catalog 3D & AR Manager (Default)
	$t_despace = $wpdb->prefix . 'despace_product_3d_models'; $t_axgnud = $wpdb->prefix . 'axgnud_product_3d_models'; $table = ( $wpdb->get_var( "SHOW TABLES LIKE '$t_despace'" ) === $t_despace ) ? $t_despace : $t_axgnud;

	// Filters
	$search_kw  = sanitize_text_field( $_GET['s_kw'] ?? '' );
	$filter_cat = sanitize_text_field( $_GET['f_cat'] ?? '' );
	$filter_st  = sanitize_text_field( $_GET['f_status'] ?? '' );

	// Metrics
	$total_count     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table" );
	$published_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE model_status = 'published'" );
	$ar_ready_count  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE ar_enabled = 1 AND glb_url != '' AND usdz_url != ''" );
	$missing_count   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE model_status = 'missing' OR glb_url = ''" );
	$pending_count   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE model_status IN ('draft', 'pending_review')" );

	// Categories list
	$cats = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => true ) );

	// Query Products
	$where = "WHERE 1=1";
	if ( $filter_st ) {
		if ( 'missing' === $filter_st ) {
			$where .= " AND (model_status = 'missing' OR glb_url = '')";
		} elseif ( 'ar_ready' === $filter_st ) {
			$where .= " AND ar_enabled = 1 AND glb_url != '' AND usdz_url != ''";
		} else {
			$where .= $wpdb->prepare( " AND model_status = %s", $filter_st );
		}
	}
	if ( $search_kw ) {
		$where .= $wpdb->prepare( " AND (sku LIKE %s OR product_id IN (SELECT ID FROM {$wpdb->posts} WHERE post_title LIKE %s))", '%' . $wpdb->esc_like( $search_kw ) . '%', '%' . $wpdb->esc_like( $search_kw ) . '%' );
	}

	$all_rows = $wpdb->get_results( "SELECT * FROM $table $where ORDER BY product_id ASC" );

	// Filter by category in PHP if requested
	if ( $filter_cat ) {
		$all_rows = array_filter( $all_rows, function( $r ) use ( $filter_cat ) {
			return has_term( $filter_cat, 'product_cat', $r->product_id );
		} );
	}

	$nonce = wp_create_nonce( 'axgnud_3d_manager_nonce' );
	?>
	<div class="wrap <?php echo $has_cockpit ? 'ds-cockpit-wrap ' : ''; ?>ax-admin-wrap">
		<?php if ( $has_cockpit ) : ?>
			<?php $render_studio_shell( 'catalog' ); ?>
		<?php else : ?>
			<!-- Standalone Header Fallback -->
			<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:12px;">
				<div>
					<h1 style="font-size:24px; font-weight:700; color:#1C1917; margin:0; display:flex; align-items:center; gap:10px;">
						<span class="dashicons dashicons-visibility" style="font-size:28px; width:28px; height:28px; color:#9A6745;"></span>
						DeSpace Studio — Quản Lý Mô Hình 3D &amp; Real AR
					</h1>
					<p style="margin:4px 0 0; color:#57534E; font-size:13.5px;">
						Hệ thống quản lý tài sản 3D (GLB/glTF), Apple AR Quick Look (.USDZ) và kích thước thực tế cho toàn bộ danh mục sản phẩm.
					</p>
				</div>

				<div style="display:flex; gap:10px;">
					<button type="button" class="button button-secondary" id="axBtnSyncCatalog" style="display:flex; align-items:center; gap:5px;">
						<span class="dashicons dashicons-update" style="font-size:16px; width:16px; height:16px;"></span>
						Đồng Bộ Danh Mục
					</button>
					<a href="<?php echo esc_url( $csv_url ); ?>" class="button button-secondary" style="display:flex; align-items:center; gap:5px;">
						<span class="dashicons dashicons-download" style="font-size:16px; width:16px; height:16px;"></span>
						Xuất File CSV
					</a>
				</div>
			</div>
		<?php endif; ?>

		<!-- Top Statistics Metric Cards (DeSpace Atelier Luxury) -->
		<section class="ds-kpi-grid" aria-label="Thống kê mô hình 3D và AR">
			<div class="ds-kpi-card">
				<span class="ds-kpi-card__label">Tổng Sản Phẩm</span>
				<div class="ds-kpi-card__value"><?php echo esc_html( number_format_i18n( $total_count ) ); ?></div>
				<span class="ds-kpi-card__meta">Toàn bộ danh mục DeSpace Luxury</span>
			</div>
			<div class="ds-kpi-card ds-kpi-card--success">
				<span class="ds-kpi-card__label">3D Sẵn Sàng (Published)</span>
				<div class="ds-kpi-card__value" style="color:#166534;"><?php echo esc_html( number_format_i18n( $published_count ) ); ?></div>
				<span class="ds-kpi-card__meta">Mô hình PBR (.GLB) đã xuất bản</span>
			</div>
			<div class="ds-kpi-card ds-kpi-card--accent">
				<span class="ds-kpi-card__label">Hỗ Trợ AR Thực Tế</span>
				<div class="ds-kpi-card__value" style="color:#8c3e1f;"><?php echo esc_html( number_format_i18n( $ar_ready_count ) ); ?></div>
				<span class="ds-kpi-card__meta">Đầy đủ GLB + USDZ cho iOS &amp; Android</span>
			</div>
			<div class="ds-kpi-card ds-kpi-card--warning">
				<span class="ds-kpi-card__label">Chưa Có Mô Hình (Missing)</span>
				<div class="ds-kpi-card__value" style="color:#92400e;"><?php echo esc_html( number_format_i18n( $missing_count ) ); ?></div>
				<span class="ds-kpi-card__meta">Đang chờ số hóa hoặc cập nhật</span>
			</div>
		</section>

		<!-- Filters & Search Toolbar -->
		<div class="ds-panel" style="padding:18px 22px; margin-bottom:20px; display:flex; gap:12px; flex-wrap:wrap; align-items:center; justify-content:space-between;">
			<form method="GET" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" style="display:flex; gap:10px; flex-wrap:wrap; align-items:center; flex:1;">
				<input type="hidden" name="page" value="despace-3d-manager" />
				
				<input type="text" name="s_kw" value="<?php echo esc_attr( $search_kw ); ?>" placeholder="Tìm tên sản phẩm hoặc SKU..." style="min-width:230px; height:38px;" />
				
				<select name="f_cat" style="height:38px;">
					<option value="">Tất cả danh mục</option>
					<?php foreach ( $cats as $c ) : ?>
						<option value="<?php echo esc_attr( $c->slug ); ?>" <?php selected( $filter_cat, $c->slug ); ?>><?php echo esc_html( $c->name ); ?></option>
					<?php endforeach; ?>
				</select>

				<select name="f_status" style="height:38px;">
					<option value="">Tất cả trạng thái</option>
					<option value="published" <?php selected( $filter_st, 'published' ); ?>>Đã xuất bản (Published)</option>
					<option value="ar_ready" <?php selected( $filter_st, 'ar_ready' ); ?>>AR Sẵn sàng (AR-Ready)</option>
					<option value="missing" <?php selected( $filter_st, 'missing' ); ?>>Chưa có mô hình (Missing)</option>
					<option value="draft" <?php selected( $filter_st, 'draft' ); ?>>Bản nháp (Draft)</option>
				</select>

				<button type="submit" class="ds-btn ds-btn--dark" style="min-height:38px;">Lọc Sản Phẩm</button>
				<?php if ( $search_kw || $filter_cat || $filter_st ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=despace-3d-manager' ) ); ?>" class="ds-btn ds-btn--outline" style="min-height:38px;">Xóa bộ lọc</a>
				<?php endif; ?>
			</form>
			
			<div style="color:#5C564E; font-size:13px; font-variant-numeric:tabular-nums;">
				Hiển thị: <strong><?php echo esc_html( number_format_i18n( count( $all_rows ) ) ); ?></strong> / <?php echo esc_html( number_format_i18n( $total_count ) ); ?> sản phẩm
			</div>
		</div>

		<!-- Products Table -->
		<div class="ds-panel" style="padding:0; overflow:hidden;">
			<table class="wp-list-table widefat fixed striped" style="border:none; border-radius:0; box-shadow:none;">
				<thead>
					<tr>
						<th style="width:60px;">Ảnh</th>
						<th style="width:260px;">Sản phẩm / SKU</th>
						<th style="width:170px;">Danh mục</th>
						<th style="width:150px;">Kích thước thực</th>
						<th style="width:130px;">Trạng thái 3D</th>
						<th style="width:110px;">Hỗ trợ AR</th>
						<th style="width:180px;">Tệp mô hình</th>
						<th style="width:160px; text-align:right;">Thao tác</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $all_rows ) ) : ?>
						<tr>
							<td colspan="8" style="text-align:center; padding:30px; color:#A8A29E;">Không tìm thấy sản phẩm nào phù hợp với bộ lọc.</td>
						</tr>
					<?php else : ?>
						<?php foreach ( $all_rows as $row ) :
							$product = wc_get_product( $row->product_id );
							if ( ! $product ) continue;
							$img_id = $product->get_image_id();
							$img_thumb = $img_id ? wp_get_attachment_image_url( $img_id, 'woocommerce_thumbnail' ) : wc_placeholder_img_src();
							$product_cats = wp_get_post_terms( $row->product_id, 'product_cat', array( 'fields' => 'names' ) );
							$cat_display = ! empty( $product_cats ) ? implode( ', ', $product_cats ) : '—';
							$has_glb = ! empty( $row->glb_url );
							$has_usdz = ! empty( $row->usdz_url );
							$is_ar_ready = $has_glb && $has_usdz && $row->ar_enabled;
						?>
						<tr id="row-prod-<?php echo esc_attr( $row->product_id ); ?>" data-product-id="<?php echo esc_attr( $row->product_id ); ?>">
							<td>
								<img src="<?php echo esc_url( $img_thumb ); ?>" style="width:48px; height:48px; object-fit:cover; border-radius:4px; border:1px solid #E7E5E4;" />
							</td>
							<td>
								<strong>
									<a href="<?php echo esc_url( get_edit_post_link( $row->product_id ) ); ?>" target="_blank" style="color:#1C1917; text-decoration:none;">
										<?php echo esc_html( $product->get_name() ); ?>
									</a>
								</strong>
								<div style="font-size:12px; color:#78716C; margin-top:2px;">
									SKU: <code><?php echo esc_html( $row->sku ?: $product->get_sku() ); ?></code> | ID: <?php echo esc_html( $row->product_id ); ?>
								</div>
							</td>
							<td style="color:#57534E; font-size:12.5px;">
								<?php echo esc_html( $cat_display ); ?>
							</td>
							<td style="font-size:12.5px;">
								<?php if ( $row->width > 0 && $row->depth > 0 && $row->height > 0 ) : ?>
									<strong><?php echo esc_html( $row->width ); ?></strong> × <?php echo esc_html( $row->depth ); ?> × <?php echo esc_html( $row->height ); ?> <?php echo esc_html( $row->unit ?: 'cm' ); ?>
								<?php else : ?>
									<span style="color:#DC2626;">Chưa có</span>
								<?php endif; ?>
							</td>
							<td>
								<?php if ( 'published' === $row->model_status && $has_glb ) : ?>
									<span class="ds-pill ds-pill--success">✓ Published</span>
								<?php elseif ( 'draft' === $row->model_status ) : ?>
									<span class="ds-pill ds-pill--warning">Bản nháp</span>
								<?php else : ?>
									<span class="ds-pill ds-pill--danger">Chưa có model</span>
								<?php endif; ?>
							</td>
							<td>
								<?php if ( $is_ar_ready ) : ?>
									<span class="ds-pill ds-pill--success">✓ AR Ready</span>
								<?php else : ?>
									<span class="ds-pill ds-pill--neutral">Chưa sẵn sàng</span>
								<?php endif; ?>
							</td>
							<td style="font-size:11.5px; color:#57534E;">
								<?php if ( $has_glb ) : ?>
									<div><span style="color:#059669; font-weight:600;">GLB:</span> Có (<?php echo esc_html( basename( $row->glb_url ) ); ?>)</div>
								<?php else : ?>
									<div style="color:#A8A29E;">GLB: Chưa có</div>
								<?php endif; ?>

								<?php if ( $has_usdz ) : ?>
									<div><span style="color:#059669; font-weight:600;">USDZ:</span> Có (<?php echo esc_html( basename( $row->usdz_url ) ); ?>)</div>
								<?php else : ?>
									<div style="color:#A8A29E;">USDZ: Chưa có</div>
								<?php endif; ?>
							</td>
							<td style="text-align:right;">
								<button type="button" class="button button-small ax-btn-edit-model"
									data-product-id="<?php echo esc_attr( $row->product_id ); ?>"
									data-name="<?php echo esc_attr( $product->get_name() ); ?>"
									data-sku="<?php echo esc_attr( $row->sku ?: $product->get_sku() ); ?>"
									data-glb="<?php echo esc_attr( $row->glb_url ); ?>"
									data-usdz="<?php echo esc_attr( $row->usdz_url ); ?>"
									data-width="<?php echo esc_attr( $row->width ); ?>"
									data-depth="<?php echo esc_attr( $row->depth ); ?>"
									data-height="<?php echo esc_attr( $row->height ); ?>"
									data-unit="<?php echo esc_attr( $row->unit ?: 'cm' ); ?>"
									data-material="<?php echo esc_attr( $row->material ); ?>"
									data-color="<?php echo esc_attr( $row->color ); ?>"
									data-status="<?php echo esc_attr( $row->model_status ); ?>"
									data-ar="<?php echo esc_attr( $row->ar_enabled ); ?>"
									data-version="<?php echo esc_attr( $row->version ?: '1.0' ); ?>">
									Chỉnh sửa 3D
								</button>
								<?php if ( $has_glb ) : ?>
									<button type="button" class="button button-small button-secondary ax-btn-preview-model"
										data-name="<?php echo esc_attr( $product->get_name() ); ?>"
										data-glb="<?php echo esc_attr( ( strpos( $row->glb_url, 'http' ) === 0 ) ? $row->glb_url : home_url( '/' . ltrim( $row->glb_url, '/' ) ) ); ?>"
										data-dims="<?php echo esc_attr( $row->width . ' × ' . $row->depth . ' × ' . $row->height . ' cm' ); ?>"
										title="Xem trước 3D Orbit">
										👁 3D
									</button>
								<?php endif; ?>
							</td>
						</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>

		<!-- Edit Modal -->
		<div id="axEdit3DModal" class="ds-modal-backdrop" style="display:none; position:fixed; z-index:100000; left:0; top:0; width:100%; height:100%; align-items:center; justify-content:center;">
			<div class="ds-modal-card" style="width:650px; max-width:92%; max-height:90vh; overflow-y:auto; padding:26px 28px;">
				<div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #E7E5E4; padding-bottom:12px; margin-bottom:16px;">
					<h3 id="axModalTitle" style="margin:0; font-size:18px; color:#1C1917;">Cập Nhật Tài Sản 3D Sản Phẩm</h3>
					<button type="button" class="ax-close-modal" style="background:none; border:none; font-size:20px; cursor:pointer; color:#78716C;">✕</button>
				</div>

				<form id="axFormEdit3D">
					<input type="hidden" name="product_id" id="edit_product_id" />
					<input type="hidden" name="nonce" value="<?php echo esc_attr( $nonce ); ?>" />

					<div style="margin-bottom:14px;">
						<label style="display:block; font-weight:600; font-size:13px; margin-bottom:4px;">Tên sản phẩm</label>
						<input type="text" id="edit_product_name" readonly style="width:100%; background:#F5F5F4;" />
					</div>

					<div style="margin-bottom:14px;">
						<label style="display:block; font-weight:600; font-size:13px; margin-bottom:4px;">GLB Model URL (glTF 2.0 Binary cho Web &amp; Android)</label>
						<div style="display:flex; gap:8px;">
							<input type="text" name="glb_url" id="edit_glb_url" placeholder="/wp-content/uploads/3d/.../model.glb" style="flex:1;" />
							<button type="button" class="button button-secondary ax-upload-btn" data-target="#edit_glb_url" data-title="Chọn file GLB">Tải lên</button>
						</div>
					</div>

					<div style="margin-bottom:14px;">
						<label style="display:block; font-weight:600; font-size:13px; margin-bottom:4px;">USDZ Model URL (Apple AR Quick Look cho iPhone/iPad)</label>
						<div style="display:flex; gap:8px;">
							<input type="text" name="usdz_url" id="edit_usdz_url" placeholder="/wp-content/uploads/3d/.../model.usdz" style="flex:1;" />
							<button type="button" class="button button-secondary ax-upload-btn" data-target="#edit_usdz_url" data-title="Chọn file USDZ">Tải lên</button>
						</div>
					</div>

					<div style="display:grid; grid-template-columns: repeat(3, 1fr) 80px; gap:10px; margin-bottom:14px;">
						<div>
							<label style="display:block; font-weight:600; font-size:12px; margin-bottom:4px;">Chiều Rộng</label>
							<input type="number" step="0.1" name="width" id="edit_width" style="width:100%;" />
						</div>
						<div>
							<label style="display:block; font-weight:600; font-size:12px; margin-bottom:4px;">Chiều Sâu</label>
							<input type="number" step="0.1" name="depth" id="edit_depth" style="width:100%;" />
						</div>
						<div>
							<label style="display:block; font-weight:600; font-size:12px; margin-bottom:4px;">Chiều Cao</label>
							<input type="number" step="0.1" name="height" id="edit_height" style="width:100%;" />
						</div>
						<div>
							<label style="display:block; font-weight:600; font-size:12px; margin-bottom:4px;">Đơn vị</label>
							<select name="unit" id="edit_unit" style="width:100%;">
								<option value="cm">cm</option>
								<option value="m">m</option>
							</select>
						</div>
					</div>

					<div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px; margin-bottom:14px;">
						<div>
							<label style="display:block; font-weight:600; font-size:12px; margin-bottom:4px;">Chất liệu (Material)</label>
							<input type="text" name="material" id="edit_material" placeholder="Gỗ óc chó, Kim loại đồng..." style="width:100%;" />
						</div>
						<div>
							<label style="display:block; font-weight:600; font-size:12px; margin-bottom:4px;">Màu sắc (Color/Finish)</label>
							<input type="text" name="color" id="edit_color" placeholder="Linear Wood, Walnut, Wenge..." style="width:100%;" />
						</div>
					</div>

					<div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px; margin-bottom:16px;">
						<div>
							<label style="display:block; font-weight:600; font-size:12px; margin-bottom:4px;">Trạng thái Model</label>
							<select name="model_status" id="edit_model_status" style="width:100%;">
								<option value="published">Xuất bản (Published)</option>
								<option value="draft">Bản nháp (Draft)</option>
								<option value="pending_review">Chờ duyệt (Pending Review)</option>
								<option value="missing">Chưa có mô hình (Missing)</option>
							</select>
						</div>
						<div>
							<label style="display:block; font-weight:600; font-size:12px; margin-bottom:4px;">Phiên bản Model</label>
							<input type="text" name="version" id="edit_version" value="1.0" style="width:100%;" />
						</div>
					</div>

					<div style="margin-bottom:20px; background:#F5F5F4; padding:12px; border-radius:6px;">
						<label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-weight:600; color:#1C1917;">
							<input type="checkbox" name="ar_enabled" id="edit_ar_enabled" value="1" />
							Kích hoạt tính năng Augmented Reality (AR Camera) cho sản phẩm này
						</label>
					</div>

					<div style="display:flex; justify-content:flex-end; gap:10px; border-top:1px solid #E7E5E4; padding-top:14px;">
						<button type="button" class="button button-secondary ax-close-modal">Hủy bỏ</button>
						<button type="submit" class="button button-primary" id="axBtnSave3D">Lưu Cấu Hình</button>
					</div>
				</form>
			</div>
		</div>

		<!-- Preview 3D Modal -->
		<div id="axPreview3DModal" class="ds-modal-backdrop" style="display:none; position:fixed; z-index:100000; left:0; top:0; width:100%; height:100%; align-items:center; justify-content:center;">
			<div class="ds-modal-card" style="width:760px; max-width:94%; height:600px; display:flex; flex-direction:column; overflow:hidden;">
				<div style="padding:14px 20px; background:#fff; border-bottom:1px solid #E7E5E4; display:flex; justify-content:space-between; align-items:center;">
					<div>
						<h3 id="axPreviewTitle" style="margin:0; font-size:16px; color:#1C1917;">Xem Trước Mô Hình 3D</h3>
						<span id="axPreviewDims" style="font-size:12px; color:#78716C;"></span>
					</div>
					<button type="button" class="ax-close-preview" style="background:none; border:none; font-size:20px; cursor:pointer; color:#78716C;">✕</button>
				</div>
				<div style="flex:1; position:relative; background:#EAE5DF;">
					<model-viewer id="axAdminModelViewer" style="width:100%; height:100%;"
						camera-controls
						auto-rotate
						shadow-intensity="1.2"
						exposure="1.0"
						interaction-prompt="auto">
					</model-viewer>
				</div>
			</div>
		</div>

	</div>

	<script>
	jQuery(document).ready(function($) {
		// Open Edit Modal
		$('.ax-btn-edit-model').on('click', function() {
			var $btn = $(this);
			$('#edit_product_id').val($btn.data('product-id'));
			$('#edit_product_name').val($btn.data('name'));
			$('#edit_glb_url').val($btn.data('glb'));
			$('#edit_usdz_url').val($btn.data('usdz'));
			$('#edit_width').val($btn.data('width'));
			$('#edit_depth').val($btn.data('depth'));
			$('#edit_height').val($btn.data('height'));
			$('#edit_unit').val($btn.data('unit') || 'cm');
			$('#edit_material').val($btn.data('material'));
			$('#edit_color').val($btn.data('color'));
			$('#edit_model_status').val($btn.data('status') || 'published');
			$('#edit_version').val($btn.data('version') || '1.0');
			$('#edit_ar_enabled').prop('checked', parseInt($btn.data('ar')) === 1);
			$('#axEdit3DModal').css('display', 'flex');
		});

		// Close Modals
		$('.ax-close-modal').on('click', function() {
			$('#axEdit3DModal').hide();
		});
		$('.ax-close-preview').on('click', function() {
			$('#axPreview3DModal').hide();
		});

		// Media Uploader
		$('.ax-upload-btn').on('click', function(e) {
			e.preventDefault();
			var target = $(this).data('target');
			var title = $(this).data('title');
			var frame = wp.media({
				title: title,
				button: { text: 'Sử dụng file này' },
				multiple: false
			});
			frame.on('select', function() {
				var attachment = frame.state().get('selection').first().toJSON();
				$(target).val(attachment.url);
			});
			frame.open();
		});

		// Submit Edit Form
		$('#axFormEdit3D').on('submit', function(e) {
			e.preventDefault();
			var $btn = $('#axBtnSave3D');
			$btn.prop('disabled', true).text('Đang lưu...');

			var data = $(this).serialize() + '&action=axgnud_save_product_3d';
			$.post(ajaxurl, data, function(res) {
				$btn.prop('disabled', false).text('Lưu Cấu Hình');
				if (res.success) {
					alert(res.data.message);
					location.reload();
				} else {
					alert(res.data.message || 'Lỗi lưu dữ liệu.');
				}
			});
		});

		// Sync Catalog Button
		$('#axBtnSyncCatalog').on('click', function() {
			if (!confirm('Bạn có muốn đồng bộ lại toàn bộ sản phẩm WooCommerce vào bảng quản lý 3D?')) return;
			var $btn = $(this);
			$btn.prop('disabled', true).text('Đang đồng bộ...');
			$.post(ajaxurl, {
				action: 'axgnud_sync_catalog_3d',
				nonce: '<?php echo esc_js( $nonce ); ?>'
			}, function(res) {
				$btn.prop('disabled', false).text('Đồng Bộ Danh Mục');
				if (res.success) {
					alert(res.data.message);
					location.reload();
				}
			});
		});

		// Preview 3D Button
		$('.ax-btn-preview-model').on('click', function() {
			var glb = $(this).data('glb');
			var name = $(this).data('name');
			var dims = $(this).data('dims');

			$('#axPreviewTitle').text(name);
			$('#axPreviewDims').text(dims);
			var mv = document.getElementById('axAdminModelViewer');
			if (mv) {
				mv.src = glb;
			}
			$('#axPreview3DModal').css('display', 'flex');
		});

		// Close modals on Escape key or backdrop click
		$(document).on('keydown', function(e) {
			if (e.key === 'Escape') {
				$('#axEdit3DModal, #axPreview3DModal').hide();
			}
		});
		$('#axEdit3DModal, #axPreview3DModal').on('click', function(e) {
			if (e.target === this) {
				$(this).hide();
			}
		});
	});
	</script>
	<?php
}


add_action( 'admin_init', function() {
	if ( isset( $_GET['page'] ) && 'axgnud-3d-manager' === $_GET['page'] ) {
		$target = admin_url( 'admin.php?page=despace-3d-manager' );
		if ( ! empty( $_GET['action'] ) ) {
			$target = add_query_arg( 'action', sanitize_text_field( $_GET['action'] ), $target );
		}
		wp_safe_redirect( $target );
		exit;
	}
} );
