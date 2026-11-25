<?php
/**
 * DeSpace 3D and AR Showroom Module.
 *
 * Encapsulates Google model-viewer, GLB/USDZ asset pipeline, product meta boxes,
 * AR QR codes, and 3D Studio administrative management.
 *
 * @package DeSpace\Product3D
 */

namespace DeSpace\Product3D;

defined( 'ABSPATH' ) || exit;

/**
 * Class Product3DModule
 */
class Product3DModule {

	/**
	 * Model viewer version.
	 */
	public const MODEL_VIEWER_VERSION = '4.4.0';

	/**
	 * Singleton instance.
	 *
	 * @var Product3DModule|null
	 */
	private static ?Product3DModule $instance = null;

	/**
	 * Initialize module hooks.
	 */
	public static function init(): void {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		if ( class_exists( 'DeSpace\\Product3D\\Desktop3DSyncModule' ) ) {
			Desktop3DSyncModule::init();
		}
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		// 1. MIME type permissions for GLB / GLTF / USDZ
		add_filter( 'upload_mimes', array( $this, 'register_mime_types' ), 10, 2 );
		add_filter( 'wp_check_filetype_and_ext', array( $this, 'correct_filetype_ext' ), 10, 4 );
		add_filter( 'wp_handle_upload_prefilter', array( $this, 'validate_3d_upload_file' ) );

		// 2. Admin Meta Box for 3D / AR
		add_action( 'add_meta_boxes_product', array( $this, 'register_meta_box' ) );
		add_action( 'save_post_product', array( $this, 'save_meta_box' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );

		// 3. Frontend model-viewer enqueue and hooks
		add_action( 'wp_enqueue_scripts', array( $this, 'frontend_enqueue_scripts' ) );
		add_action( 'woocommerce_single_product_summary', array( $this, 'render_single_product_3d_button' ), 35 );
		add_action( 'wp_footer', array( $this, 'render_global_3d_modal' ) );

		// 4. Shortcode for embedding 3D viewer anywhere
		add_shortcode( 'despace_3d', array( $this, 'shortcode_viewer' ) );

		// 5. Dual-table compatibility: Ensure wp_despace_product_3d_models exists or falls back to wp_axgnud_product_3d_models
		add_action( 'init', array( $this, 'ensure_3d_table' ), 20 );
	}

	/**
	 * Allow 3D model uploads in WordPress Media Library.
	 */
	public function register_mime_types( $mimes, $user = null ) {
		if ( current_user_can( 'manage_woocommerce' ) || current_user_can( 'upload_files' ) || current_user_can( 'manage_despace_3d' ) ) {
			$mimes['glb']  = 'model/gltf-binary';
			$mimes['gltf'] = 'model/gltf+json';
			$mimes['usdz'] = 'model/vnd.usdz+zip';
		}
		return $mimes;
	}

	/**
	 * Bypass false-positive extension checks for GLB.
	 */
	public function correct_filetype_ext( $data, $file, $filename, $mimes ) {
		if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'upload_files' ) && ! current_user_can( 'manage_despace_3d' ) ) {
			return $data;
		}
		if ( ! is_string( $file ) || ! is_readable( $file ) ) {
			return $data;
		}

		$ext   = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
		$fsize = (int) filesize( $file );

		if ( 'glb' === $ext ) {
			if ( $fsize < 12 ) {
				return $data;
			}
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$header = file_get_contents( $file, false, null, 0, 8 );
			$magic  = substr( $header, 0, 4 );
			$ver    = unpack( 'V', substr( $header, 4, 4 ) )[1] ?? 0;
			if ( 'glTF' !== $magic || 2 !== $ver ) {
				return $data;
			}
			$data['ext']  = 'glb';
			$data['type'] = 'model/gltf-binary';
		} elseif ( 'gltf' === $ext ) {
			if ( $fsize < 2 || $fsize > 10 * 1024 * 1024 ) {
				return $data;
			}
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$raw     = file_get_contents( $file );
			$decoded = is_string( $raw ) ? json_decode( $raw, true ) : null;
			if ( ! is_array( $decoded ) || ! isset( $decoded['asset'] ) ) {
				return $data;
			}
			$data['ext']  = 'gltf';
			$data['type'] = 'model/gltf+json';
		} elseif ( 'usdz' === $ext ) {
			if ( $fsize < 30 ) {
				return $data;
			}
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$magic = file_get_contents( $file, false, null, 0, 4 );
			if ( "PK\x03\x04" !== $magic ) {
				return $data;
			}
			$data['ext']  = 'usdz';
			$data['type'] = 'model/vnd.usdz+zip';
		}
		return $data;
	}

	/**
	 * Validate 3D upload file size (<= 20MB) and binary magic bytes prior to WordPress storage.
	 *
	 * @param array $file Array of $_FILE data from wp_handle_upload.
	 * @return array
	 */
	public function validate_3d_upload_file( array $file ): array {
		if ( ! empty( $file['error'] ) ) {
			return $file;
		}

		$filename = $file['name'] ?? '';
		$ext      = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
		$fsize    = (int) ( $file['size'] ?? 0 );

		if ( in_array( $ext, array( 'glb', 'usdz', 'gltf' ), true ) ) {
			// 1. Enforce 20MB limit per DeSpace API Contract spec (API_CONTRACT.md section 3.3)
			$max_bytes = 20 * 1024 * 1024;
			if ( $fsize > $max_bytes ) {
				$file['error'] = sprintf(
					__( 'Tệp mô hình 3D (%s) vượt quá giới hạn tối đa cho phép là 20MB theo chuẩn DeSpace.', 'despace-core' ),
					esc_html( $filename )
				);
				return $file;
			}

			// 2. Validate binary magic bytes if temporary file is readable
			$tmp = $file['tmp_name'] ?? '';
			if ( is_string( $tmp ) && is_readable( $tmp ) ) {
				if ( 'glb' === $ext ) {
					if ( $fsize < 12 ) {
						$file['error'] = __( 'Tệp GLB không hợp lệ hoặc bị hỏng (kích thước quá nhỏ).', 'despace-core' );
						return $file;
					}
					// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
					$header = file_get_contents( $tmp, false, null, 0, 8 );
					$magic  = substr( $header, 0, 4 );
					$ver    = unpack( 'V', substr( $header, 4, 4 ) )[1] ?? 0;
					if ( 'glTF' !== $magic || 2 !== $ver ) {
						$file['error'] = __( 'Tệp GLB không đúng chuẩn glTF 2.0 Binary (Magic header không khớp).', 'despace-core' );
						return $file;
					}
				} elseif ( 'usdz' === $ext ) {
					if ( $fsize < 30 ) {
						$file['error'] = __( 'Tệp USDZ không hợp lệ hoặc bị hỏng.', 'despace-core' );
						return $file;
					}
					// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
					$magic = file_get_contents( $tmp, false, null, 0, 4 );
					if ( "PK\x03\x04" !== $magic ) {
						$file['error'] = __( 'Tệp USDZ không hợp lệ (cần định dạng package zip chuẩn của Apple AR).', 'despace-core' );
						return $file;
					}
				}
			}
		}

		return $file;
	}

	/**
	 * Register Product Meta Box.
	 */
	public function register_meta_box(): void {
		add_meta_box(
			'despace_3d_model_box',
			__( 'Cấu hình Mô hình 3D / AR (DeSpace 3D)', 'despace-core' ),
			array( $this, 'render_meta_box' ),
			'product',
			'normal',
			'high'
		);
	}

	/**
	 * Render 3D / AR Admin Meta Box.
	 */
	public function render_meta_box( $post ): void {
		wp_nonce_field( 'despace_save_3d_meta', 'despace_3d_nonce' );

		$attachment_id = absint( get_post_meta( $post->ID, '_despace_glb_attachment_id', true ) );
		$glb_url       = get_post_meta( $post->ID, '_despace_glb_url', true );
		$poster_id     = absint( get_post_meta( $post->ID, '_despace_poster_attachment_id', true ) );
		$enable_ar     = get_post_meta( $post->ID, '_despace_enable_ar', true );

		// 3D physical dimensions & camera controls (per API_CONTRACT.md section 3)
		$dimensions = get_post_meta( $post->ID, '_despace_3d_dimensions', true );
		if ( ! is_array( $dimensions ) ) {
			$dimensions = array();
		}
		$dim_length    = $dimensions['length'] ?? 0;
		$dim_width     = $dimensions['width'] ?? 0;
		$dim_height    = $dimensions['height'] ?? 0;
		$dim_unit      = ! empty( $dimensions['unit'] ) ? $dimensions['unit'] : 'cm';
		$camera_orbit  = get_post_meta( $post->ID, '_despace_3d_camera_orbit', true ) ?: '45deg 75deg 2.5m';
		$field_of_view = get_post_meta( $post->ID, '_despace_3d_field_of_view', true ) ?: '30deg';

		// Fallback detection from legacy meta or 3D table if new fields are empty
		if ( ! $attachment_id && empty( $glb_url ) ) {
			$info = self::get_product_3d_info( $post->ID );
			if ( ! empty( $info['glb_url'] ) ) {
				$glb_url = $info['glb_url'];
			}
			if ( empty( $poster_id ) && ! empty( $info['poster_attachment_id'] ) ) {
				$poster_id = absint( $info['poster_attachment_id'] );
			}
			if ( 0 == $dim_length && 0 == $dim_width && 0 == $dim_height ) {
				$dim_length = $info['depth'] ?? 0;
				$dim_width  = $info['width'] ?? 0;
				$dim_height = $info['height'] ?? 0;
				$dim_unit   = $info['unit'] ?? 'cm';
			}
		}

		$current_glb_preview = '';
		if ( $attachment_id ) {
			$current_glb_preview = wp_get_attachment_url( $attachment_id );
		} elseif ( $glb_url ) {
			$current_glb_preview = $glb_url;
		}

		$poster_preview_url = $poster_id ? wp_get_attachment_image_url( $poster_id, 'medium' ) : '';
		?>
		<div class="despace-3d-meta-wrapper" style="padding: 12px 0;">
			<style>
				.despace-meta-row { margin-bottom: 18px; }
				.despace-meta-row label { display: block; font-weight: 600; margin-bottom: 5px; color: #1e1e1e; }
				.despace-meta-row .description { color: #666; font-size: 12px; margin-top: 4px; line-height: 1.4; }
				.despace-media-preview { margin-top: 8px; font-family: monospace; background: #f0f0f1; padding: 6px 10px; border-radius: 4px; display: inline-block; word-break: break-all; }
				.despace-dim-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; max-width: 600px; }
				.despace-dim-grid input, .despace-dim-grid select { width: 100%; }
			</style>

			<!-- 1. GLB 3D Binary File -->
			<div class="despace-meta-row">
				<label for="despace_glb_attachment_id"><?php esc_html_e( 'Tệp mô hình 3D (.glb / GLTF Binary)', 'despace-core' ); ?></label>
				<input type="hidden" id="despace_glb_attachment_id" name="despace_glb_attachment_id" value="<?php echo esc_attr( $attachment_id ); ?>" />
				<button type="button" class="button button-secondary" id="despace_select_glb_btn">
					<?php echo $attachment_id ? esc_html__( 'Thay đổi tệp GLB', 'despace-core' ) : esc_html__( 'Chọn tệp GLB từ Media Library', 'despace-core' ); ?>
				</button>
				<button type="button" class="button" id="despace_remove_glb_btn" style="<?php echo $attachment_id ? '' : 'display:none;'; ?>">
					<?php esc_html_e( 'Gỡ tệp', 'despace-core' ); ?>
				</button>
				<div id="despace_glb_preview" class="despace-media-preview" style="<?php echo $current_glb_preview ? '' : 'display:none;'; ?>">
					<?php echo esc_html( $current_glb_preview ); ?>
				</div>
				<p class="description"><?php esc_html_e( 'Định dạng chuẩn Binary glTF 2.0 (.glb), dung lượng tối đa <= 20MB (khuyến nghị <= 12MB). Tỷ lệ scale: 1 đơn vị 3D = 1 mét thực tế.', 'despace-core' ); ?></p>
			</div>

			<!-- 2. Fallback GLB URL -->
			<div class="despace-meta-row">
				<label for="despace_glb_url"><?php esc_html_e( 'Đường dẫn GLB trực tiếp (URL dự phòng nếu không dùng Attachment ID)', 'despace-core' ); ?></label>
				<input type="url" class="regular-text" id="despace_glb_url" name="despace_glb_url" value="<?php echo esc_attr( $glb_url ); ?>" placeholder="https://..." />
			</div>

			<!-- 3. Poster Image Picker -->
			<div class="despace-meta-row">
				<label for="despace_poster_attachment_id"><?php esc_html_e( 'Ảnh Poster 3D (Thumbnail tải trước)', 'despace-core' ); ?></label>
				<input type="hidden" id="despace_poster_attachment_id" name="despace_poster_attachment_id" value="<?php echo esc_attr( $poster_id ); ?>" />
				<button type="button" class="button button-secondary" id="despace_select_poster_btn">
					<?php echo $poster_id ? esc_html__( 'Thay ảnh Poster', 'despace-core' ) : esc_html__( 'Chọn ảnh Poster từ Media Library', 'despace-core' ); ?>
				</button>
				<button type="button" class="button" id="despace_remove_poster_btn" style="<?php echo $poster_id ? '' : 'display:none;'; ?>">
					<?php esc_html_e( 'Gỡ Poster', 'despace-core' ); ?>
				</button>
				<div id="despace_poster_preview" style="margin-top: 8px; <?php echo $poster_preview_url ? '' : 'display:none;'; ?>">
					<?php if ( $poster_preview_url ) : ?>
						<img src="<?php echo esc_url( $poster_preview_url ); ?>" style="max-height: 100px; border-radius: 4px; border: 1px solid #ddd;" alt="Poster preview" />
					<?php endif; ?>
				</div>
				<p class="description"><?php esc_html_e( 'Hình ảnh tĩnh nhẹ (WebP/JPG) hiển thị trong khi model 3D đang được tải về thiết bị.', 'despace-core' ); ?></p>
			</div>

			<!-- 4. AR Toggle -->
			<div class="despace-meta-row">
				<label>
					<input type="checkbox" name="despace_enable_ar" value="yes" <?php checked( 'yes', $enable_ar ? $enable_ar : 'yes' ); ?> />
					<?php esc_html_e( 'Kích hoạt thực tế ảo tăng cường AR (WebXR / SceneViewer / QuickLook)', 'despace-core' ); ?>
				</label>
			</div>

			<!-- 5. Physical Dimensions -->
			<div class="despace-meta-row">
				<label><?php esc_html_e( 'Kích thước vật lý thực tế (Physical Dimensions)', 'despace-core' ); ?></label>
				<div class="despace-dim-grid">
					<div>
						<span style="font-size:11px;color:#666;"><?php esc_html_e( 'Chiều dài (Length)', 'despace-core' ); ?></span>
						<input type="number" step="0.1" min="0" name="despace_3d_length" value="<?php echo esc_attr( $dim_length ); ?>" placeholder="210" />
					</div>
					<div>
						<span style="font-size:11px;color:#666;"><?php esc_html_e( 'Chiều rộng (Width)', 'despace-core' ); ?></span>
						<input type="number" step="0.1" min="0" name="despace_3d_width" value="<?php echo esc_attr( $dim_width ); ?>" placeholder="90" />
					</div>
					<div>
						<span style="font-size:11px;color:#666;"><?php esc_html_e( 'Chiều cao (Height)', 'despace-core' ); ?></span>
						<input type="number" step="0.1" min="0" name="despace_3d_height" value="<?php echo esc_attr( $dim_height ); ?>" placeholder="85" />
					</div>
					<div>
						<span style="font-size:11px;color:#666;"><?php esc_html_e( 'Đơn vị (Unit)', 'despace-core' ); ?></span>
						<select name="despace_3d_unit">
							<option value="cm" <?php selected( 'cm', $dim_unit ); ?>>cm</option>
							<option value="mm" <?php selected( 'mm', $dim_unit ); ?>>mm</option>
							<option value="m" <?php selected( 'm', $dim_unit ); ?>>m</option>
						</select>
					</div>
				</div>
				<p class="description"><?php esc_html_e( 'Kích thước vật lý thực tế chuẩn hóa theo API Contract M1 để đối soát không gian AR chính xác.', 'despace-core' ); ?></p>
			</div>

			<!-- 6. Camera Controls -->
			<div class="despace-meta-row">
				<label><?php esc_html_e( 'Cấu hình góc nhìn khởi tạo (Camera Orbit & Field of View)', 'despace-core' ); ?></label>
				<div style="display:flex; gap:12px; max-width:600px;">
					<div style="flex:1;">
						<span style="font-size:11px;color:#666;">Camera Orbit (VD: 45deg 75deg 2.5m)</span>
						<input type="text" class="regular-text" style="width:100%;" name="despace_3d_camera_orbit" value="<?php echo esc_attr( $camera_orbit ); ?>" />
					</div>
					<div style="flex:1;">
						<span style="font-size:11px;color:#666;">Field of View (VD: 30deg)</span>
						<input type="text" class="regular-text" style="width:100%;" name="despace_3d_field_of_view" value="<?php echo esc_attr( $field_of_view ); ?>" />
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Save Meta Box data.
	 */
	public function save_meta_box( $post_id ): void {
		// 1. Verify CSRF Nonce
		if ( ! isset( $_POST['despace_3d_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['despace_3d_nonce'] ) ), 'despace_save_3d_meta' ) ) {
			return;
		}

		// 2. Ignore autosave requests
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// 3. Strict RBAC capability check: edit_post, manage_despace_3d, or manage_woocommerce (per SECURITY_AUDIT.md)
		$can_manage_3d = current_user_can( 'edit_post', $post_id )
			|| current_user_can( 'manage_despace_3d' )
			|| current_user_can( 'manage_woocommerce' );

		if ( ! $can_manage_3d ) {
			return;
		}

		// 1. GLB Attachment ID (Golden Rule: Attachment ID > 0)
		if ( isset( $_POST['despace_glb_attachment_id'] ) ) {
			$att_id = absint( $_POST['despace_glb_attachment_id'] );
			if ( $att_id > 0 ) {
				$mime = get_post_mime_type( $att_id );
				$file = get_attached_file( $att_id );
				$ext  = strtolower( pathinfo( $file ?: '', PATHINFO_EXTENSION ) );
				if ( in_array( $mime, array( 'model/gltf-binary', 'model/gltf+json' ), true ) || 'glb' === $ext || 'gltf' === $ext ) {
					update_post_meta( $post_id, '_despace_glb_attachment_id', $att_id );
				}
			} else {
				delete_post_meta( $post_id, '_despace_glb_attachment_id' );
			}
		}

		// 2. Poster Attachment ID
		if ( isset( $_POST['despace_poster_attachment_id'] ) ) {
			$poster_id = absint( $_POST['despace_poster_attachment_id'] );
			if ( $poster_id > 0 ) {
				update_post_meta( $post_id, '_despace_poster_attachment_id', $poster_id );
			} else {
				delete_post_meta( $post_id, '_despace_poster_attachment_id' );
			}
		}

		// 3. Fallback GLB URL
		if ( isset( $_POST['despace_glb_url'] ) ) {
			$raw_url = sanitize_text_field( wp_unslash( $_POST['despace_glb_url'] ) );
			if ( '' !== $raw_url ) {
				update_post_meta( $post_id, '_despace_glb_url', esc_url_raw( $raw_url ) );
			} else {
				delete_post_meta( $post_id, '_despace_glb_url' );
			}
		}

		// 4. AR Toggle
		$enable_ar = isset( $_POST['despace_enable_ar'] ) ? 'yes' : 'no';
		update_post_meta( $post_id, '_despace_enable_ar', $enable_ar );

		// 5. 3D Dimensions
		$dimensions = array(
			'length' => isset( $_POST['despace_3d_length'] ) ? floatval( $_POST['despace_3d_length'] ) : 0,
			'width'  => isset( $_POST['despace_3d_width'] ) ? floatval( $_POST['despace_3d_width'] ) : 0,
			'height' => isset( $_POST['despace_3d_height'] ) ? floatval( $_POST['despace_3d_height'] ) : 0,
			'unit'   => isset( $_POST['despace_3d_unit'] ) ? sanitize_text_field( wp_unslash( $_POST['despace_3d_unit'] ) ) : 'cm',
		);
		update_post_meta( $post_id, '_despace_3d_dimensions', $dimensions );

		// 6. Camera Orbit & FOV
		if ( isset( $_POST['despace_3d_camera_orbit'] ) ) {
			update_post_meta( $post_id, '_despace_3d_camera_orbit', sanitize_text_field( wp_unslash( $_POST['despace_3d_camera_orbit'] ) ) );
		}
		if ( isset( $_POST['despace_3d_field_of_view'] ) ) {
			update_post_meta( $post_id, '_despace_3d_field_of_view', sanitize_text_field( wp_unslash( $_POST['despace_3d_field_of_view'] ) ) );
		}
	}

	/**
	 * Enqueue admin scripts.
	 */
	public function admin_enqueue_scripts( $hook ): void {
		if ( 'post.php' === $hook || 'post-new.php' === $hook ) {
			global $post;
			if ( $post && 'product' === $post->post_type ) {
				wp_enqueue_media();
				$js_path = DESPACE_CORE_PATH . 'assets/js/despace-admin-3d.js';
				if ( file_exists( $js_path ) ) {
					wp_enqueue_script(
						'despace-admin-3d',
						DESPACE_CORE_URL . 'assets/js/despace-admin-3d.js',
						array( 'jquery' ),
						DESPACE_CORE_VERSION,
						true
					);
				}
			}
		}
	}

	/**
	 * Enqueue frontend scripts and styles.
	 */
	public function frontend_enqueue_scripts(): void {
		$css_path = DESPACE_CORE_PATH . 'assets/css/despace-3d.css';
		if ( file_exists( $css_path ) ) {
			wp_enqueue_style(
				'despace-3d-styles',
				DESPACE_CORE_URL . 'assets/css/despace-3d.css',
				array(),
				DESPACE_CORE_VERSION
			);
		}

		// Enqueue self-hosted <model-viewer> on relevant pages
		if ( is_product() || is_page( 'ar-room-visualizer' ) || is_page_template( 'page-ar-visualizer.php' ) ) {
			$local_mv_path = get_stylesheet_directory() . '/assets/vendor/model-viewer/model-viewer.min.js';
			if ( file_exists( $local_mv_path ) ) {
				wp_enqueue_script(
					'google-model-viewer',
					get_stylesheet_directory_uri() . '/assets/vendor/model-viewer/model-viewer.min.js',
					array(),
					'3.5.0',
					true
				);
			}
			add_filter( 'script_loader_tag', function( $tag, $handle, $src ) {
				if ( 'google-model-viewer' === $handle ) {
					return '<script type="module" src="' . esc_url( $src ) . '"></script>';
				}
				return $tag;
			}, 10, 3 );
		}
	}

	/**
	 * Per-request memoized table existence and preloaded 3D row cache (eliminates N+1 SHOW TABLES/SELECT storms).
	 *
	 * @var array<string, bool>
	 */
	private static array $table_exists_cache = array();

	/**
	 * @var array<int, array|null>
	 */
	private static array $row_cache = array();

	/**
	 * Check once per request whether a 3D table exists.
	 */
	private static function table_exists_cached( string $table_name ): bool {
		if ( array_key_exists( $table_name, self::$table_exists_cache ) ) {
			return self::$table_exists_cache[ $table_name ];
		}
		global $wpdb;
		$like = method_exists( $wpdb, 'esc_like' ) ? $wpdb->esc_like( $table_name ) : $table_name;
		$ok   = ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $like ) ) === $table_name );
		self::$table_exists_cache[ $table_name ] = $ok;
		return $ok;
	}

	/**
	 * Batch-preload 3D rows for a list of product IDs in a single SQL query.
	 *
	 * @param int[] $product_ids
	 */
	public static function preload_products_3d_info( array $product_ids ): void {
		global $wpdb;
		$ids = array_values( array_unique( array_filter( array_map( 'absint', $product_ids ) ) ) );
		$ids = array_values( array_diff( $ids, array_keys( self::$row_cache ) ) );
		if ( empty( $ids ) ) {
			return;
		}

		foreach ( $ids as $id ) {
			self::$row_cache[ $id ] = null;
		}

		$table_despace = $wpdb->prefix . 'despace_product_3d_models';
		$table_axgnud  = $wpdb->prefix . 'axgnud_product_3d_models';
		$placeholders  = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		if ( self::table_exists_cached( $table_despace ) ) {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table_despace} WHERE product_id IN ({$placeholders})", $ids ), ARRAY_A );
			foreach ( (array) $rows as $r ) {
				$pid = absint( $r['product_id'] ?? 0 );
				if ( $pid && null === self::$row_cache[ $pid ] ) {
					self::$row_cache[ $pid ] = $r;
				}
			}
		}

		$missing = array_values( array_filter( $ids, static fn( $id ) => null === self::$row_cache[ $id ] ) );
		if ( ! empty( $missing ) && self::table_exists_cached( $table_axgnud ) ) {
			$ph2  = implode( ',', array_fill( 0, count( $missing ), '%d' ) );
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table_axgnud} WHERE product_id IN ({$ph2})", $missing ), ARRAY_A );
			foreach ( (array) $rows as $r ) {
				$pid = absint( $r['product_id'] ?? 0 );
				if ( $pid && null === self::$row_cache[ $pid ] ) {
					self::$row_cache[ $pid ] = $r;
				}
			}
		}
	}

	/**
	 * Retrieve 3D model info with fallback hierarchy.
	 */
	public static function get_product_3d_info( int $product_id ): array {
		global $wpdb;

		// 1. Check custom 3D tables (cached per request to avoid N+1 SHOW TABLES / SELECT)
		$table_despace = $wpdb->prefix . 'despace_product_3d_models';
		$table_axgnud  = $wpdb->prefix . 'axgnud_product_3d_models';

		if ( array_key_exists( $product_id, self::$row_cache ) ) {
			$row = self::$row_cache[ $product_id ];
		} else {
			$row = null;
			if ( self::table_exists_cached( $table_despace ) ) {
				$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_despace} WHERE product_id = %d LIMIT 1", $product_id ), ARRAY_A );
			}
			if ( ! $row && self::table_exists_cached( $table_axgnud ) ) {
				$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_axgnud} WHERE product_id = %d LIMIT 1", $product_id ), ARRAY_A );
			}
			self::$row_cache[ $product_id ] = $row ?: null;
		}

		$resolve_asset_url = static function( string $raw ): string {
			$raw = trim( $raw );
			if ( '' === $raw ) {
				return '';
			}
			$wp_content_pos = strpos( $raw, '/wp-content/' );
			if ( false !== $wp_content_pos ) {
				$rel_after_content = substr( $raw, $wp_content_pos + strlen( '/wp-content/' ) );
				return content_url( ltrim( $rel_after_content, '/' ) );
			}
			if ( 0 === strpos( $raw, 'http://' ) || 0 === strpos( $raw, 'https://' ) ) {
				return $raw;
			}
			return content_url( ltrim( str_replace( '/wp-content', '', $raw ), '/' ) );
		};

		$append_mtime_version = static function( string $url ): string {
			if ( '' === $url || false !== strpos( $url, '?' ) ) {
				return $url;
			}
			$uploads = wp_get_upload_dir();
			if ( ! empty( $uploads['baseurl'] ) && ! empty( $uploads['basedir'] ) && 0 === strpos( $url, $uploads['baseurl'] ) ) {
				$rel  = substr( $url, strlen( $uploads['baseurl'] ) );
				$disk = wp_normalize_path( $uploads['basedir'] . $rel );
				if ( file_exists( $disk ) ) {
					return add_query_arg( 'v', (string) filemtime( $disk ), $url );
				}
			}
			return $url;
		};

		// Helper to resolve poster thumbnail with fallback to product featured image (per Issue #6)
		$resolve_poster = static function( int $pid, int $custom_poster_id, string $legacy_thumb = '' ) use ( $resolve_asset_url ): array {
			// 1. Check custom poster attachment if valid and exists
			if ( $custom_poster_id > 0 ) {
				$custom_url = wp_get_attachment_image_url( $custom_poster_id, 'full' );
				if ( ! empty( $custom_url ) ) {
					return array(
						'id'     => $custom_poster_id,
						'url'    => $custom_url,
						'source' => 'custom',
					);
				}
			}

			// 2. Fallback to product featured image (get_post_thumbnail_id)
			if ( function_exists( 'get_post_thumbnail_id' ) ) {
				$thumb_id = absint( get_post_thumbnail_id( $pid ) );
				if ( $thumb_id > 0 ) {
					$thumb_url = wp_get_attachment_image_url( $thumb_id, 'full' );
					if ( ! empty( $thumb_url ) ) {
						return array(
							'id'     => $thumb_id,
							'url'    => $thumb_url,
							'source' => 'product_image',
						);
					}
				}
			}

			// 3. Fallback to legacy seed thumbnail url if present
			if ( ! empty( $legacy_thumb ) ) {
				return array(
					'id'     => 0,
					'url'    => $resolve_asset_url( $legacy_thumb ),
					'source' => 'custom',
				);
			}

			return array(
				'id'     => 0,
				'url'    => '',
				'source' => 'none',
			);
		};

		if ( $row && ! empty( $row['glb_url'] ) ) {
			$glb_att_db    = absint( get_post_meta( $product_id, '_despace_glb_attachment_id', true ) );
			$poster_att_db = absint( get_post_meta( $product_id, '_despace_poster_attachment_id', true ) );
			$poster_info   = $resolve_poster( $product_id, $poster_att_db, (string) ( $row['thumbnail_url'] ?? '' ) );
			$orbit_db      = get_post_meta( $product_id, '_despace_3d_camera_orbit', true ) ?: '45deg 75deg 2.5m';
			$fov_db        = get_post_meta( $product_id, '_despace_3d_field_of_view', true ) ?: '30deg';

			return array(
				'has_3d'               => true,
				'glb_url'              => $append_mtime_version( $resolve_asset_url( (string) $row['glb_url'] ) ),
				'usdz_url'             => ! empty( $row['usdz_url'] ) ? $append_mtime_version( $resolve_asset_url( (string) $row['usdz_url'] ) ) : '',
				'thumbnail'            => $poster_info['url'],
				'poster_url'           => $poster_info['url'],
				'poster_attachment_id' => $poster_info['id'],
				'poster_source'        => $poster_info['source'],
				'ar_enabled'           => (bool) ( $row['ar_enabled'] ?? 1 ),
				'width'                => $row['width'] ?? 0,
				'depth'                => $row['depth'] ?? 0,
				'height'               => $row['height'] ?? 0,
				'unit'                 => $row['unit'] ?? 'cm',
				'glb_attachment_id'    => $glb_att_db,
				'camera_orbit'         => $orbit_db,
				'field_of_view'        => $fov_db,
			);
		}

		// 2. Postmeta fallbacks
		$glb_att   = absint( get_post_meta( $product_id, '_despace_glb_attachment_id', true ) );
		$glb_url   = get_post_meta( $product_id, '_despace_glb_url', true )
			?: get_post_meta( $product_id, '_axgnud_model_glb', true )
			?: get_post_meta( $product_id, '_glb_file', true );

		if ( $glb_att ) {
			$glb_url = wp_get_attachment_url( $glb_att );
		}

		if ( $glb_url ) {
			$poster_att  = absint( get_post_meta( $product_id, '_despace_poster_attachment_id', true ) );
			$poster_info = $resolve_poster( $product_id, $poster_att, '' );
			$dimensions  = get_post_meta( $product_id, '_despace_3d_dimensions', true );
			if ( ! is_array( $dimensions ) ) {
				$dimensions = array();
			}
			$enable_ar = get_post_meta( $product_id, '_despace_enable_ar', true );

			return array(
				'has_3d'               => true,
				'glb_url'              => $glb_url,
				'usdz_url'             => '',
				'thumbnail'            => $poster_info['url'],
				'poster_url'           => $poster_info['url'],
				'poster_attachment_id' => $poster_info['id'],
				'poster_source'        => $poster_info['source'],
				'ar_enabled'           => 'no' !== $enable_ar,
				'width'                => $dimensions['width'] ?? 0,
				'depth'                => $dimensions['length'] ?? 0,
				'height'               => $dimensions['height'] ?? 0,
				'unit'                 => $dimensions['unit'] ?? 'cm',
				'glb_attachment_id'    => $glb_att,
				'camera_orbit'         => get_post_meta( $product_id, '_despace_3d_camera_orbit', true ) ?: '45deg 75deg 2.5m',
				'field_of_view'        => get_post_meta( $product_id, '_despace_3d_field_of_view', true ) ?: '30deg',
			);
		}

		return array(
			'has_3d'               => false,
			'glb_url'              => '',
			'usdz_url'             => '',
			'thumbnail'            => '',
			'poster_url'           => '',
			'poster_attachment_id' => 0,
			'poster_source'        => 'none',
			'ar_enabled'           => false,
		);
	}

	/**
	 * Render single product 3D action button.
	 */
	public function render_single_product_3d_button(): void {
		global $product;
		if ( ! $product ) {
			return;
		}

		$info = self::get_product_3d_info( $product->get_id() );
		if ( ! $info['has_3d'] ) {
			return;
		}

		echo sprintf(
			'<div class="despace-3d-action-wrapper" style="margin: 16px 0;">
				<button type="button" class="despace-btn-view-3d f-btn-3d" data-glb="%s" data-usdz="%s" data-poster="%s" data-product-id="%d" data-title="%s">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:8px; vertical-align:middle;">
						<path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
					</svg>
					<span>%s</span>
				</button>
			</div>',
			esc_url( $info['glb_url'] ),
			esc_url( $info['usdz_url'] ),
			esc_url( $info['poster_url'] ?? $info['thumbnail'] ?? '' ),
			esc_attr( $product->get_id() ),
			esc_attr( $product->get_name() ),
			esc_html__( 'Trải nghiệm Không gian 3D & AR', 'despace-core' )
		);
	}

	/**
	 * Render global 3D modal in footer.
	 */
	public function render_global_3d_modal(): void {
		?>
		<div id="despace-3d-modal" class="despace-3d-modal-overlay" style="display:none;" role="dialog" aria-modal="true">
			<div class="despace-3d-modal-container">
				<div class="despace-3d-modal-header">
					<h3 id="despace-3d-modal-title" class="despace-3d-title"><?php esc_html_e( 'Mô hình 3D & Thực tế ảo AR', 'despace-core' ); ?></h3>
					<button type="button" class="despace-3d-modal-close" aria-label="<?php esc_attr_e( 'Đóng', 'despace-core' ); ?>">✕</button>
				</div>
				<div class="despace-3d-modal-body">
					<model-viewer id="despace-active-model-viewer"
						src=""
						poster=""
						alt="DeSpace 3D Model"
						auto-rotate
						camera-controls
						ar
						ar-modes="webxr scene-viewer quick-look"
						shadow-intensity="1"
						exposure="1"
						interaction-prompt="auto"
						style="width: 100%; height: 500px; background-color: #f7f4ef; border-radius: 16px;">
						<button slot="ar-button" class="despace-ar-launch-btn">
							✦ <?php esc_html_e( 'Xem trong không gian phòng (AR)', 'despace-core' ); ?>
						</button>
					</model-viewer>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Shortcode viewer.
	 */
	public function shortcode_viewer( $atts ): string {
		$atts = shortcode_atts(
			array(
				'id'     => 0,
				'src'    => '',
				'height' => '450px',
			),
			$atts,
			'despace_3d'
		);

		$src = $atts['src'];
		if ( ! $src && $atts['id'] ) {
			$info = self::get_product_3d_info( absint( $atts['id'] ) );
			$src  = $info['glb_url'];
		}

		if ( ! $src ) {
			return '';
		}

		$height = trim( (string) $atts['height'] );
		if ( ! preg_match( '/^\d+(?:\.\d+)?(?:px|%|vh|rem|em)$/', $height ) ) {
			$height = '450px';
		}

		return sprintf(
			'<div class="despace-shortcode-viewer">
				<model-viewer src="%s" auto-rotate camera-controls ar style="width:100%%; height:%s; background-color:#faf8f5; border-radius:16px;"></model-viewer>
			</div>',
			esc_url( $src ),
			esc_attr( $height )
		);
	}

	/**
	 * Ensure wp_despace_product_3d_models exists or is synchronized from wp_axgnud_product_3d_models.
	 */
	public function ensure_3d_table(): void {
		if ( function_exists( 'get_option' ) && '1' === (string) get_option( 'despace_3d_table_ready_v1', '' ) ) {
			return;
		}
		global $wpdb;
		$table_despace = $wpdb->prefix . 'despace_product_3d_models';
		$table_axgnud  = $wpdb->prefix . 'axgnud_product_3d_models';

		// If despace table doesn't exist, create it matching axgnud schema
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table_despace ) ) ) !== $table_despace ) {
			$charset_collate = $wpdb->get_charset_collate();
			$sql = "CREATE TABLE `{$table_despace}` (
				`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				`product_id` bigint(20) unsigned NOT NULL,
				`variant_id` bigint(20) unsigned DEFAULT 0,
				`sku` varchar(100) DEFAULT '',
				`glb_url` text,
				`usdz_url` text,
				`thumbnail_url` text,
				`width` decimal(10,2) DEFAULT 0.00,
				`depth` decimal(10,2) DEFAULT 0.00,
				`height` decimal(10,2) DEFAULT 0.00,
				`unit` varchar(20) DEFAULT 'cm',
				`material` varchar(255) DEFAULT '',
				`color` varchar(255) DEFAULT '',
				`version` varchar(50) DEFAULT '1.0',
				`model_status` varchar(50) DEFAULT 'missing',
				`ar_enabled` tinyint(1) DEFAULT 0,
				`created_at` datetime DEFAULT current_timestamp(),
				`updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
				`updated_by` bigint(20) unsigned DEFAULT 1,
				PRIMARY KEY (`id`),
				KEY `product_id` (`product_id`),
				KEY `sku` (`sku`),
				KEY `model_status` (`model_status`)
			) {$charset_collate};";

			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta( $sql );

			// If source table axgnud exists and has data, copy over to despace table
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table_axgnud ) ) ) === $table_axgnud ) {
				$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table_despace}`" );
				if ( 0 === $count ) {
					$wpdb->query( "INSERT IGNORE INTO `{$table_despace}` SELECT * FROM `{$table_axgnud}`" );
				}
			}
		}
		if ( function_exists( 'update_option' ) ) {
			update_option( 'despace_3d_table_ready_v1', '1', false );
		}
	}
}
