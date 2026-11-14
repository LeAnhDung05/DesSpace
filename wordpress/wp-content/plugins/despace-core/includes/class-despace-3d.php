<?php
/**
 * 3D and AR integration using Google model-viewer and GLB models.
 * Strictly adheres to DeSpace SADD v1.2 Sections 2.3, 4.2.5, 6.1.
 *
 * @package DeSpace_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DeSpace_3D {

	const MODEL_VIEWER_VERSION = '4.4.0';

	public function __construct() {
		// MIME type permissions for GLB / GLTF / USDZ
		add_filter( 'upload_mimes', array( $this, 'register_mime_types' ), 10, 2 );
		add_filter( 'wp_check_filetype_and_ext', array( $this, 'correct_filetype_ext' ), 10, 4 );

		// Admin Meta Box for 3D / AR
		add_action( 'add_meta_boxes_product', array( $this, 'register_meta_box' ) );
		add_action( 'save_post_product', array( $this, 'save_meta_box' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );

		// Frontend model-viewer enqueue and hooks
		add_action( 'wp_enqueue_scripts', array( $this, 'frontend_enqueue_scripts' ) );
		add_action( 'woocommerce_single_product_summary', array( $this, 'render_single_product_3d_button' ), 35 );
		add_action( 'wp_footer', array( $this, 'render_global_3d_modal' ) );

		// Shortcode for embedding 3D viewer anywhere
		add_shortcode( 'despace_3d', array( $this, 'shortcode_viewer' ) );
	}

	/**
	 * Allow 3D model uploads in WordPress Media Library.
	 */
	public function register_mime_types( $mimes, $user = null ) {
		if ( current_user_can( 'manage_woocommerce' ) || current_user_can( 'upload_files' ) ) {
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
		$ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
		if ( 'glb' === $ext ) {
			$data['ext']  = 'glb';
			$data['type'] = 'model/gltf-binary';
		} elseif ( 'gltf' === $ext ) {
			$data['ext']  = 'gltf';
			$data['type'] = 'model/gltf+json';
		} elseif ( 'usdz' === $ext ) {
			$data['ext']  = 'usdz';
			$data['type'] = 'model/vnd.usdz+zip';
		}
		return $data;
	}

	/**
	 * Register Product Meta Box.
	 */
	public function register_meta_box() {
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
	public function render_meta_box( $post ) {
		wp_nonce_field( 'despace_save_3d_meta', 'despace_3d_nonce' );

		$attachment_id = absint( get_post_meta( $post->ID, '_despace_glb_attachment_id', true ) );
		$glb_url       = get_post_meta( $post->ID, '_despace_glb_url', true );
		$poster_id     = absint( get_post_meta( $post->ID, '_despace_poster_attachment_id', true ) );
		$enable_ar     = get_post_meta( $post->ID, '_despace_enable_ar', true );

		// Fallback detection from legacy meta if new fields are empty
		if ( ! $attachment_id && empty( $glb_url ) ) {
			$legacy = get_post_meta( $post->ID, '_axgnud_model_glb', true )
				?: get_post_meta( $post->ID, '_glb_file', true );
			if ( $legacy ) {
				if ( is_numeric( $legacy ) ) {
					$attachment_id = absint( $legacy );
				} else {
					$glb_url = esc_url_raw( $legacy );
				}
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
				.despace-meta-row { margin-bottom: 15px; }
				.despace-meta-row label { display: block; font-weight: 600; margin-bottom: 5px; }
				.despace-meta-row .description { color: #666; font-size: 12px; margin-top: 4px; }
				.despace-media-preview { margin-top: 8px; font-family: monospace; background: #f0f0f1; padding: 6px 10px; border-radius: 4px; display: inline-block; word-break: break-all; }
			</style>

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
				<p class="description">
					<?php esc_html_e( 'Khuyến nghị tệp định dạng .glb đã nén Dracos/KTX2 dung lượng < 5MB để tối ưu thời gian tải trang.', 'despace-core' ); ?>
				</p>
			</div>

			<div class="despace-meta-row">
				<label for="despace_glb_url"><?php esc_html_e( 'Hoặc đường dẫn GLB trực tiếp (URL dự phòng)', 'despace-core' ); ?></label>
				<input type="url" class="regular-text" id="despace_glb_url" name="despace_glb_url" value="<?php echo esc_attr( $glb_url ); ?>" placeholder="https://..." />
			</div>

			<div class="despace-meta-row">
				<label for="despace_poster_attachment_id"><?php esc_html_e( 'Ảnh Poster 3D (Tùy chọn)', 'despace-core' ); ?></label>
				<input type="hidden" id="despace_poster_attachment_id" name="despace_poster_attachment_id" value="<?php echo esc_attr( $poster_id ); ?>" />
				<button type="button" class="button button-secondary" id="despace_select_poster_btn">
					<?php echo $poster_id ? esc_html__( 'Thay ảnh Poster', 'despace-core' ) : esc_html__( 'Chọn ảnh Poster từ Media Library', 'despace-core' ); ?>
				</button>
				<button type="button" class="button" id="despace_remove_poster_btn" style="<?php echo $poster_id ? '' : 'display:none;'; ?>">
					<?php esc_html_e( 'Gỡ poster', 'despace-core' ); ?>
				</button>
				<div id="despace_poster_preview" style="margin-top: 8px;">
					<?php if ( $poster_preview_url ) : ?>
						<img src="<?php echo esc_url( $poster_preview_url ); ?>" style="max-height: 100px; border-radius: 4px; border: 1px solid #ddd;" />
					<?php endif; ?>
				</div>
				<p class="description">
					<?php esc_html_e( 'Ảnh tải trước trong khi mô hình 3D đang nạp. Nếu để trống, hệ thống tự động sử dụng ảnh đại diện sản phẩm.', 'despace-core' ); ?>
				</p>
			</div>

			<div class="despace-meta-row" style="margin-top: 15px;">
				<label>
					<input type="checkbox" name="despace_enable_ar" value="1" <?php checked( '1' === $enable_ar || ( '' === $enable_ar && $current_glb_preview ) ); ?> />
					<strong><?php esc_html_e( 'Kích hoạt tính năng AR (Thực tế tăng cường)', 'despace-core' ); ?></strong>
				</label>
				<p class="description">
					<?php esc_html_e( 'Hỗ trợ WebXR trên Android và Quick Look / Scene Viewer trên iOS / Chrome tương thích.', 'despace-core' ); ?>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Save Meta Box.
	 */
	public function save_meta_box( $post_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( ! isset( $_POST['despace_3d_nonce'] ) ) {
			return;
		}
		$nonce = sanitize_text_field( wp_unslash( $_POST['despace_3d_nonce'] ) );
		if ( ! wp_verify_nonce( $nonce, 'despace_save_3d_meta' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Save GLB Attachment ID
		if ( ! empty( $_POST['despace_glb_attachment_id'] ) ) {
			$attachment_id = absint( $_POST['despace_glb_attachment_id'] );
			update_post_meta( $post_id, '_despace_glb_attachment_id', $attachment_id );
		} else {
			delete_post_meta( $post_id, '_despace_glb_attachment_id' );
		}

		// Save Direct GLB URL
		if ( ! empty( $_POST['despace_glb_url'] ) ) {
			update_post_meta( $post_id, '_despace_glb_url', esc_url_raw( trim( $_POST['despace_glb_url'] ) ) );
		} else {
			delete_post_meta( $post_id, '_despace_glb_url' );
		}

		// Save Poster Attachment ID
		if ( ! empty( $_POST['despace_poster_attachment_id'] ) ) {
			$poster_id = absint( $_POST['despace_poster_attachment_id'] );
			update_post_meta( $post_id, '_despace_poster_attachment_id', $poster_id );
		} else {
			delete_post_meta( $post_id, '_despace_poster_attachment_id' );
		}

		// Save Enable AR
		$enable_ar = ! empty( $_POST['despace_enable_ar'] ) ? '1' : '0';
		update_post_meta( $post_id, '_despace_enable_ar', $enable_ar );
	}

	/**
	 * Enqueue admin scripts for Media Uploader in product edit screen.
	 */
	public function admin_enqueue_scripts( $hook ) {
		global $post_type;
		if ( 'product' !== $post_type ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_script(
			'despace-admin-3d',
			plugins_url( 'assets/js/despace-admin-3d.js', dirname( __FILE__ ) ),
			array( 'jquery' ),
			'1.2.0',
			true
		);
	}

	/**
	 * Frontend Enqueue model-viewer as modern ES module.
	 */
	public function frontend_enqueue_scripts() {
		// Enqueue model-viewer only when needed or on product pages / catalog
		if ( is_product() || is_shop() || is_product_category() || is_front_page() ) {
			wp_enqueue_script(
				'google-model-viewer',
				'https://cdn.jsdelivr.net/npm/@google/model-viewer@' . self::MODEL_VIEWER_VERSION . '/dist/model-viewer.min.js',
				array(),
				self::MODEL_VIEWER_VERSION,
				false
			);

			// Add type="module" to model-viewer script tag
			add_filter( 'script_loader_tag', function( $tag, $handle, $src ) {
				if ( 'google-model-viewer' === $handle ) {
					return '<script type="module" src="' . esc_url( $src ) . '"></script>' . "\n";
				}
				return $tag;
			}, 10, 3 );
		}

		wp_enqueue_style(
			'despace-3d-styles',
			plugins_url( 'assets/css/despace-3d.css', dirname( __FILE__ ) ),
			array(),
			'1.2.0'
		);
	}

	/**
	 * Retrieve normalized 3D/AR data for a product.
	 */
	public static function get_product_3d_data( $product_id ) {
		$attachment_id = absint( get_post_meta( $product_id, '_despace_glb_attachment_id', true ) );
		$glb_url       = get_post_meta( $product_id, '_despace_glb_url', true );
		$poster_id     = absint( get_post_meta( $product_id, '_despace_poster_attachment_id', true ) );
		$enable_ar_meta= get_post_meta( $product_id, '_despace_enable_ar', true );

		// Legacy fallbacks
		if ( ! $attachment_id && empty( $glb_url ) ) {
			$legacy = get_post_meta( $product_id, '_axgnud_model_glb', true )
				?: get_post_meta( $product_id, '_glb_file', true )
				?: get_post_meta( $product_id, '_ar_glb_url', true )
				?: get_post_meta( $product_id, '_model_3d', true );
			if ( $legacy ) {
				if ( is_numeric( $legacy ) ) {
					$attachment_id = absint( $legacy );
				} else {
					$glb_url = $legacy;
				}
			}
		}

		$final_glb_url = '';
		if ( $attachment_id ) {
			$final_glb_url = wp_get_attachment_url( $attachment_id );
		} elseif ( ! empty( $glb_url ) ) {
			$final_glb_url = $glb_url;
		}

		$poster_url = '';
		if ( $poster_id ) {
			$poster_url = wp_get_attachment_image_url( $poster_id, 'large' );
		} elseif ( has_post_thumbnail( $product_id ) ) {
			$poster_url = get_the_post_thumbnail_url( $product_id, 'large' );
		}

		$has_3d = ! empty( $final_glb_url );
		$enable_ar = ( '1' === $enable_ar_meta || ( '' === $enable_ar_meta && $has_3d ) );

		return array(
			'has_3d'        => $has_3d,
			'model_url'     => $final_glb_url,
			'poster_url'    => $poster_url,
			'enable_ar'     => $enable_ar,
			'attachment_id' => $attachment_id,
		);
	}

	/**
	 * Single product 3D trigger button.
	 */
	public function render_single_product_3d_button() {
		global $product;
		if ( ! $product instanceof WC_Product ) {
			return;
		}

		$data = self::get_product_3d_data( $product->get_id() );
		if ( ! $data['has_3d'] ) {
			return;
		}

		?>
		<div class="despace-product-3d-action" style="margin: 20px 0;">
			<button type="button" class="despace-btn-open-3d" data-product-id="<?php echo esc_attr( $product->get_id() ); ?>" data-model="<?php echo esc_url( $data['model_url'] ); ?>" data-poster="<?php echo esc_url( $data['poster_url'] ); ?>" data-ar="<?php echo $data['enable_ar'] ? '1' : '0'; ?>" data-name="<?php echo esc_attr( $product->get_name() ); ?>">
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 8px;">
					<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
					<polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
					<line x1="12" y1="22.08" x2="12" y2="12"></line>
				</svg>
				<span><?php esc_html_e( 'Xem Mô Hình 3D & AR Không Gian', 'despace-core' ); ?></span>
			</button>
		</div>
		<?php
	}

	/**
	 * Global 3D & AR Modal rendered in footer.
	 */
	public function render_global_3d_modal() {
		?>
		<div id="despace-3d-modal" class="despace-modal" aria-hidden="true" style="display:none;">
			<div class="despace-modal-backdrop" id="despace-modal-backdrop"></div>
			<div class="despace-modal-dialog">
				<div class="despace-modal-header">
					<div class="despace-modal-title-group">
						<span class="despace-badge-3d">3D & AR EXPERIENCE</span>
						<h3 id="despace-modal-product-title">Tên sản phẩm</h3>
					</div>
					<button type="button" class="despace-modal-close" id="despace-modal-close" aria-label="Đóng">&times;</button>
				</div>
				<div class="despace-modal-body">
					<div class="despace-model-container" id="despace-model-container">
						<!-- model-viewer will be injected dynamically -->
					</div>
				</div>
				<div class="despace-modal-footer">
					<div class="despace-controls-hint">
						<span>🖱️ Kéo để xoay 360° | Cuộn để phóng to | Chạm để tương tác</span>
					</div>
					<div class="despace-ar-action-wrap" id="despace-ar-action-wrap"></div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Shortcode [despace_3d id="123" height="480px"]
	 */
	public function shortcode_viewer( $atts ) {
		$atts = shortcode_atts( array(
			'id'     => 0,
			'height' => '460px',
		), $atts, 'despace_3d' );

		$product_id = absint( $atts['id'] );
		if ( ! $product_id ) {
			global $product;
			if ( $product instanceof WC_Product ) {
				$product_id = $product->get_id();
			}
		}

		if ( ! $product_id ) {
			return '';
		}

		$data = self::get_product_3d_data( $product_id );
		if ( ! $data['has_3d'] ) {
			return '';
		}

		$height = esc_attr( $atts['height'] );
		$product = wc_get_product( $product_id );
		$name = $product ? $product->get_name() : 'DeSpace Product';

		ob_start();
		?>
		<div class="despace-3d-viewer-wrapper" style="width: 100%; height: <?php echo $height; ?>; position: relative;">
			<model-viewer
				src="<?php echo esc_url( $data['model_url'] ); ?>"
				<?php if ( ! empty( $data['poster_url'] ) ) : ?>poster="<?php echo esc_url( $data['poster_url'] ); ?>"<?php endif; ?>
				alt="<?php echo esc_attr( $name ); ?>"
				loading="lazy"
				camera-controls
				auto-rotate
				shadow-intensity="1"
				exposure="1"
				<?php if ( $data['enable_ar'] ) : ?>ar ar-modes="webxr scene-viewer quick-look"<?php endif; ?>
				style="width: 100%; height: 100%; background: #F8F7F4; border-radius: 8px;">
				<?php if ( $data['enable_ar'] ) : ?>
					<button slot="ar-button" class="despace-ar-btn">
						📱 <?php esc_html_e( 'Xem trong không gian thực tế (AR)', 'despace-core' ); ?>
					</button>
				<?php endif; ?>
			</model-viewer>
		</div>
		<?php
		return ob_get_clean();
	}
}
