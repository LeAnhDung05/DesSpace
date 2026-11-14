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
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		// 1. MIME type permissions for GLB / GLTF / USDZ
		add_filter( 'upload_mimes', array( $this, 'register_mime_types' ), 10, 2 );
		add_filter( 'wp_check_filetype_and_ext', array( $this, 'correct_filetype_ext' ), 10, 4 );

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

		// Fallback detection from legacy meta or 3D table if new fields are empty
		if ( ! $attachment_id && empty( $glb_url ) ) {
			$info = self::get_product_3d_info( $post->ID );
			if ( ! empty( $info['glb_url'] ) ) {
				$glb_url = $info['glb_url'];
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
			</div>

			<div class="despace-meta-row">
				<label for="despace_glb_url"><?php esc_html_e( 'Hoặc đường dẫn GLB trực tiếp (URL dự phòng)', 'despace-core' ); ?></label>
				<input type="url" class="regular-text" id="despace_glb_url" name="despace_glb_url" value="<?php echo esc_attr( $glb_url ); ?>" placeholder="https://..." />
			</div>

			<div class="despace-meta-row">
				<label>
					<input type="checkbox" name="despace_enable_ar" value="yes" <?php checked( 'yes', $enable_ar ? 'yes' : 'yes' ); ?> />
					<?php esc_html_e( 'Kích hoạt thực tế ảo tăng cường AR (WebXR / SceneViewer / QuickLook)', 'despace-core' ); ?>
				</label>
			</div>
		</div>
		<?php
	}

	/**
	 * Save Meta Box data.
	 */
	public function save_meta_box( $post_id ): void {
		if ( ! isset( $_POST['despace_3d_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['despace_3d_nonce'] ) ), 'despace_save_3d_meta' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( isset( $_POST['despace_glb_attachment_id'] ) ) {
			update_post_meta( $post_id, '_despace_glb_attachment_id', absint( $_POST['despace_glb_attachment_id'] ) );
		}
		if ( isset( $_POST['despace_glb_url'] ) ) {
			update_post_meta( $post_id, '_despace_glb_url', esc_url_raw( wp_unslash( $_POST['despace_glb_url'] ) ) );
		}
		$enable_ar = isset( $_POST['despace_enable_ar'] ) ? 'yes' : 'no';
		update_post_meta( $post_id, '_despace_enable_ar', $enable_ar );
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

		// Enqueue Google model-viewer on relevant pages
		if ( is_product() || is_page( 'ar-room-visualizer' ) || is_page_template( 'page-ar-visualizer.php' ) ) {
			wp_enqueue_script(
				'google-model-viewer',
				'https://ajax.googleapis.com/ajax/libs/model-viewer/3.5.0/model-viewer.min.js',
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
	}

	/**
	 * Retrieve 3D model info with fallback hierarchy.
	 */
	public static function get_product_3d_info( int $product_id ): array {
		global $wpdb;

		// 1. Check custom 3D tables: check wp_despace_product_3d_models first, then wp_axgnud_product_3d_models
		$table_despace = $wpdb->prefix . 'despace_product_3d_models';
		$table_axgnud  = $wpdb->prefix . 'axgnud_product_3d_models';

		$row = null;
		if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_despace ) ) === $table_despace ) {
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_despace} WHERE product_id = %d LIMIT 1", $product_id ), ARRAY_A );
		}

		if ( ! $row && $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_axgnud ) ) === $table_axgnud ) {
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_axgnud} WHERE product_id = %d LIMIT 1", $product_id ), ARRAY_A );
		}

		if ( $row && ! empty( $row['glb_url'] ) ) {
			return array(
				'has_3d'     => true,
				'glb_url'    => content_url( ltrim( str_replace( '/wp-content', '', $row['glb_url'] ), '/' ) ),
				'usdz_url'   => ! empty( $row['usdz_url'] ) ? content_url( ltrim( str_replace( '/wp-content', '', $row['usdz_url'] ), '/' ) ) : '',
				'thumbnail'  => ! empty( $row['thumbnail_url'] ) ? content_url( ltrim( str_replace( '/wp-content', '', $row['thumbnail_url'] ), '/' ) ) : '',
				'ar_enabled' => (bool) ( $row['ar_enabled'] ?? 1 ),
				'width'      => $row['width'] ?? 0,
				'depth'      => $row['depth'] ?? 0,
				'height'     => $row['height'] ?? 0,
				'unit'       => $row['unit'] ?? 'cm',
			);
		}

		// 2. Postmeta fallbacks
		$glb_att = absint( get_post_meta( $product_id, '_despace_glb_attachment_id', true ) );
		$glb_url = get_post_meta( $product_id, '_despace_glb_url', true )
			?: get_post_meta( $product_id, '_axgnud_model_glb', true )
			?: get_post_meta( $product_id, '_glb_file', true );

		if ( $glb_att ) {
			$glb_url = wp_get_attachment_url( $glb_att );
		}

		if ( $glb_url ) {
			return array(
				'has_3d'     => true,
				'glb_url'    => $glb_url,
				'usdz_url'   => '',
				'thumbnail'  => '',
				'ar_enabled' => true,
				'width'      => 0,
				'depth'      => 0,
				'height'     => 0,
				'unit'       => 'cm',
			);
		}

		return array(
			'has_3d'     => false,
			'glb_url'    => '',
			'usdz_url'   => '',
			'thumbnail'  => '',
			'ar_enabled' => false,
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
				<button type="button" class="despace-btn-view-3d f-btn-3d" data-glb="%s" data-usdz="%s" data-product-id="%d" data-title="%s">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:8px; vertical-align:middle;">
						<path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
					</svg>
					<span>%s</span>
				</button>
			</div>',
			esc_url( $info['glb_url'] ),
			esc_url( $info['usdz_url'] ),
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

		return sprintf(
			'<div class="despace-shortcode-viewer">
				<model-viewer src="%s" auto-rotate camera-controls ar style="width:100%%; height:%s; background-color:#faf8f5; border-radius:16px;"></model-viewer>
			</div>',
			esc_url( $src ),
			esc_attr( $atts['height'] )
		);
	}

	/**
	 * Ensure wp_despace_product_3d_models exists or is synchronized from wp_axgnud_product_3d_models.
	 */
	public function ensure_3d_table(): void {
		global $wpdb;
		$table_despace = $wpdb->prefix . 'despace_product_3d_models';
		$table_axgnud  = $wpdb->prefix . 'axgnud_product_3d_models';

		// If despace table doesn't exist, create it matching axgnud schema
		if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_despace ) ) !== $table_despace ) {
			$charset_collate = $wpdb->get_charset_collate();
			$sql = "CREATE TABLE `{$table_despace}` (
				`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				`product_id` bigint(20) unsigned NOT NULL,
				`variant_id` bigint(20) unsigned DEFAULT 0,
				`sku` varchar(100) DEFAULT '',
				`glb_url` text DEFAULT '',
				`usdz_url` text DEFAULT '',
				`thumbnail_url` text DEFAULT '',
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
			if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_axgnud ) ) === $table_axgnud ) {
				$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table_despace}`" );
				if ( 0 === $count ) {
					$wpdb->query( "INSERT IGNORE INTO `{$table_despace}` SELECT * FROM `{$table_axgnud}`" );
				}
			}
		}
	}
}
