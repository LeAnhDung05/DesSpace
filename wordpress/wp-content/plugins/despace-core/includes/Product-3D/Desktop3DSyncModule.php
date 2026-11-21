<?php
/**
 * DeSpace Desktop 3D Model Synchronization Module.
 *
 * Facilitates multi-angle product photo capture (3-5 views),
 * job dispatching to the DeSpace 3D Studio Desktop Application,
 * bidirectional sync, real-time progress tracking, and automated GLB publication.
 *
 * @package DeSpace\Product3D
 */

namespace DeSpace\Product3D;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Class Desktop3DSyncModule
 */
class Desktop3DSyncModule {

	/**
	 * Singleton instance.
	 *
	 * @var Desktop3DSyncModule|null
	 */
	private static ?Desktop3DSyncModule $instance = null;

	/**
	 * Table name without prefix.
	 */
	public const TABLE_JOBS = 'despace_3d_sync_jobs';

	/**
	 * Default Desktop App Local Port.
	 */
	public const DEFAULT_DESKTOP_PORT = 58300;

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
		// 1. Ensure jobs table exists
		add_action( 'init', array( $this, 'ensure_tables' ), 25 );

		// 2. Admin meta boxes and scripts for Product Edit
		add_action( 'add_meta_boxes_product', array( $this, 'register_meta_box' ), 15 );
		add_action( 'save_post_product', array( $this, 'save_meta_box' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );

		// 3. AJAX Endpoints for admin UI
		add_action( 'wp_ajax_despace_dispatch_desktop_3d', array( $this, 'ajax_dispatch_job' ) );
		add_action( 'wp_ajax_despace_check_job_status', array( $this, 'ajax_check_job_status' ) );
	}

	/**
	 * Get full table name for jobs.
	 */
	public static function get_jobs_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_JOBS;
	}

	/**
	 * Ensure table schema for 3D sync jobs.
	 */
	public function ensure_tables(): void {
		if ( function_exists( 'get_option' ) && '1' === (string) get_option( 'despace_3d_jobs_table_ready_v1', '' ) ) {
			return;
		}
		global $wpdb;
		$table_name      = self::get_jobs_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table_name ) ) ) !== $table_name ) {
			$sql = "CREATE TABLE {$table_name} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  job_uuid varchar(64) NOT NULL,
  product_id bigint(20) unsigned NOT NULL,
  sku varchar(100) DEFAULT '',
  product_name varchar(255) DEFAULT '',
  status varchar(50) NOT NULL DEFAULT 'pending',
  progress int(3) NOT NULL DEFAULT 0,
  progress_message varchar(255) DEFAULT '',
  multi_angles_json longtext DEFAULT NULL,
  dimensions_json text DEFAULT NULL,
  category varchar(100) DEFAULT '',
  glb_url text,
  usdz_url text,
  thumbnail_url text,
  vertex_count int(11) DEFAULT 0,
  face_count int(11) DEFAULT 0,
  file_size_bytes bigint(20) DEFAULT 0,
  source varchar(50) DEFAULT 'web_admin',
  error_message text,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  completed_at datetime DEFAULT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY job_uuid (job_uuid),
  KEY product_id (product_id),
  KEY sku (sku),
  KEY status (status),
  KEY created_at (created_at)
) {$charset_collate};";

			if ( ! function_exists( 'dbDelta' ) && defined( 'ABSPATH' ) && file_exists( ABSPATH . 'wp-admin/includes/upgrade.php' ) ) {
				require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			}

			if ( function_exists( 'dbDelta' ) ) {
				dbDelta( $sql );
			} else {
				$wpdb->query( $sql );
			}
		}
		if ( function_exists( 'update_option' ) ) {
			update_option( 'despace_3d_jobs_table_ready_v1', '1', false );
		}
	}

	/**
	 * Register Product Meta Box for Multi-Angle 3D Capture & Desktop Sync.
	 */
	public function register_meta_box(): void {
		add_meta_box(
			'despace_multi_angle_3d_box',
			__( 'Bộ Ảnh Đa Góc & Đồng Bộ Ứng Dụng Desktop 3D (DeSpace Studio)', 'despace-core' ),
			array( $this, 'render_meta_box' ),
			'product',
			'normal',
			'high'
		);
	}

	/**
	 * Retrieve multi-angle image data for a product.
	 *
	 * @param int $product_id Product ID.
	 * @return array Array of angle images with metadata.
	 */
	public static function get_product_multi_angles( int $product_id ): array {
		$angles = array(
			'front'       => array(
				'label'       => __( 'Mặt Trước (0° Front)', 'despace-core' ),
				'required'    => true,
				'description' => __( 'Góc nhìn trực diện chính diện sản phẩm', 'despace-core' ),
				'id'          => absint( get_post_meta( $product_id, '_despace_3d_angle_front', true ) ),
				'url'         => '',
			),
			'perspective' => array(
				'label'       => __( 'Góc Phối Cảnh (45° Perspective)', 'despace-core' ),
				'required'    => true,
				'description' => __( 'Góc nghiêng 3/4 giúp nhận diện chiều sâu và khối', 'despace-core' ),
				'id'          => absint( get_post_meta( $product_id, '_despace_3d_angle_perspective', true ) ),
				'url'         => '',
			),
			'side'        => array(
				'label'       => __( 'Mặt Bên (90° Side View)', 'despace-core' ),
				'required'    => true,
				'description' => __( 'Cạnh bên trái hoặc phải của sản phẩm', 'despace-core' ),
				'id'          => absint( get_post_meta( $product_id, '_despace_3d_angle_side', true ) ),
				'url'         => '',
			),
			'back'        => array(
				'label'       => __( 'Mặt Sau (180° Back View)', 'despace-core' ),
				'required'    => false,
				'description' => __( 'Mặt sau hoàn thiện của sản phẩm', 'despace-core' ),
				'id'          => absint( get_post_meta( $product_id, '_despace_3d_angle_back', true ) ),
				'url'         => '',
			),
			'top'         => array(
				'label'       => __( 'Mặt Trên (Top / Down View)', 'despace-core' ),
				'required'    => false,
				'description' => __( 'Góc nhìn từ trên xuống để định hình mặt phẳng', 'despace-core' ),
				'id'          => absint( get_post_meta( $product_id, '_despace_3d_angle_top', true ) ),
				'url'         => '',
			),
		);

		foreach ( $angles as $key => &$data ) {
			if ( $data['id'] ) {
				$data['url'] = wp_get_attachment_url( $data['id'] ) ?: '';
			} else {
				// Fallback to direct URL meta if saved as text
				$direct_url = get_post_meta( $product_id, "_despace_3d_angle_{$key}_url", true );
				if ( $direct_url ) {
					$data['url'] = esc_url_raw( $direct_url );
				}
			}
		}

		// Fallback: If front angle is empty, use product featured image
		if ( empty( $angles['front']['url'] ) ) {
			$thumb_id = get_post_thumbnail_id( $product_id );
			if ( $thumb_id ) {
				$angles['front']['id']  = $thumb_id;
				$angles['front']['url'] = wp_get_attachment_url( $thumb_id ) ?: '';
			}
		}

		return $angles;
	}

	/**
	 * Count valid angle images uploaded.
	 *
	 * @param array $angles Angles array.
	 * @return int Number of present images.
	 */
	public static function count_valid_angles( array $angles ): int {
		$count = 0;
		foreach ( $angles as $item ) {
			if ( ! empty( $item['url'] ) || ! empty( $item['id'] ) ) {
				$count++;
			}
		}
		return $count;
	}

	/**
	 * Render Meta Box HTML.
	 */
	public function render_meta_box( $post ): void {
		wp_nonce_field( 'despace_save_multi_angle_meta', 'despace_multi_angle_nonce' );

		$angles      = self::get_product_multi_angles( $post->ID );
		$valid_count = self::count_valid_angles( $angles );
		$sync_status = get_post_meta( $post->ID, '_despace_3d_sync_status', true ) ?: 'idle';
		$last_job_id = get_post_meta( $post->ID, '_despace_3d_last_job_id', true );
		$auto_sync   = get_post_meta( $post->ID, '_despace_3d_auto_sync', true );
		if ( '' === $auto_sync ) {
			$auto_sync = 'yes';
		}

		$sku = get_post_meta( $post->ID, '_sku', true );
		if ( ! $sku && function_exists( 'wc_get_product' ) ) {
			$prod = wc_get_product( $post->ID );
			$sku  = $prod ? $prod->get_sku() : '';
		}

		// Retrieve latest job info if exists
		$latest_job = null;
		if ( $last_job_id ) {
			$latest_job = self::get_job_by_uuid( $last_job_id );
		}
		?>
		<div class="despace-multi-angle-box-wrap" style="padding:14px 4px; font-family:-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
			
			<!-- Header Banner & Guidance -->
			<div style="background:#FAF8F5; border:1px solid #E7E3DC; border-radius:12px; padding:16px 20px; margin-bottom:20px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
				<div>
					<h4 style="margin:0 0 6px; font-size:15px; font-weight:700; color:#1C1917;">
						📸 Quy Chuẩn 3–5 Ảnh Đa Góc Dành Cho Xây Dựng Mô Hình 3D (Multi-Angle 3D Pipeline)
					</h4>
					<p style="margin:0; font-size:13px; color:#6B635B; line-height:1.5;">
						Tải lên tối thiểu <strong>3 ảnh góc chuẩn</strong> (Mặt trước, Góc nghiêng 45°, Mặt bên) và khuyến khích bổ sung Mặt sau, Mặt trên.
						Khi người quản lý bấm <strong>"Gửi Sang Desktop Dựng 3D"</strong>, hệ thống sẽ đẩy yêu cầu sang <strong>Ứng Dụng Desktop Chuyên Xây Dựng 3D</strong> để tái tạo mô hình PBR chi tiết chuẩn nhất rồi tự động trả về website.
					</p>
				</div>
				<div style="text-align:right;">
					<span id="despace-desktop-agent-pill" style="display:inline-flex; align-items:center; gap:6px; background:#F3F4F6; border:1px solid #D1D5DB; color:#374151; font-size:12px; font-weight:700; padding:6px 14px; border-radius:99px;">
						<span style="width:8px; height:8px; border-radius:50%; background:#9CA3AF; display:inline-block;" class="status-dot"></span>
						<span class="status-label">Đang kiểm tra Desktop App...</span>
					</span>
				</div>
			</div>

			<!-- 5 Angle Image Slots Grid -->
			<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:16px; margin-bottom:24px;">
				<?php foreach ( $angles as $key => $angle_info ) : 
					$has_img = ! empty( $angle_info['url'] );
				?>
					<div class="despace-angle-card" data-angle="<?php echo esc_attr( $key ); ?>" style="background:#fff; border:1px solid <?php echo $has_img ? '#10B981' : '#D1D5DB'; ?>; border-radius:12px; padding:12px; display:flex; flex-direction:column; justify-content:space-between; position:relative; box-shadow:0 2px 6px rgba(0,0,0,0.03);">
						
						<!-- Badge -->
						<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
							<span style="font-size:11px; font-weight:700; text-transform:uppercase; color:<?php echo $angle_info['required'] ? '#DC2626' : '#6B7280'; ?>;">
								<?php echo $angle_info['required'] ? '★ Bắt buộc' : '○ Khuyến nghị'; ?>
							</span>
							<span class="badge-status" style="font-size:11px; font-weight:700; color:<?php echo $has_img ? '#059669' : '#9CA3AF'; ?>;">
								<?php echo $has_img ? '✓ Đã có ảnh' : 'Chưa có ảnh'; ?>
							</span>
						</div>

						<!-- Title -->
						<div style="font-weight:700; font-size:13.5px; color:#1C1917; margin-bottom:4px;">
							<?php echo esc_html( $angle_info['label'] ); ?>
						</div>
						<div style="font-size:11.5px; color:#78716C; margin-bottom:10px; min-height:28px;">
							<?php echo esc_html( $angle_info['description'] ); ?>
						</div>

						<!-- Image Preview Box -->
						<div class="despace-angle-preview-box" style="width:100%; height:130px; background:#F9FAFB; border:1px dashed <?php echo $has_img ? '#10B981' : '#CBD5E1'; ?>; border-radius:8px; display:flex; align-items:center; justify-content:center; overflow:hidden; margin-bottom:10px; cursor:pointer;" onclick="despaceOpenMediaPicker('<?php echo esc_js( $key ); ?>');">
							<img src="<?php echo esc_url( $angle_info['url'] ); ?>" class="despace-angle-img" style="max-width:100%; max-height:100%; object-fit:contain; <?php echo $has_img ? '' : 'display:none;'; ?>" alt="" />
							<div class="despace-angle-placeholder" style="text-align:center; color:#94A3B8; <?php echo $has_img ? 'display:none;' : ''; ?>">
								<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="margin:0 auto 4px; display:block;">
									<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M17 8l-5-5-5 5M12 3v12"/>
								</svg>
								<span style="font-size:11.5px; font-weight:600;">Chọn ảnh tải lên</span>
							</div>
						</div>

						<!-- Hidden Inputs -->
						<input type="hidden" id="despace_angle_<?php echo esc_attr( $key ); ?>_id" name="despace_angle_<?php echo esc_attr( $key ); ?>_id" value="<?php echo esc_attr( $angle_info['id'] ); ?>" />
						<input type="hidden" id="despace_angle_<?php echo esc_attr( $key ); ?>_url" name="despace_angle_<?php echo esc_attr( $key ); ?>_url" value="<?php echo esc_attr( $angle_info['url'] ); ?>" />

						<!-- Actions -->
						<div style="display:flex; gap:6px;">
							<button type="button" class="button button-small" style="flex:1; font-size:11.5px; font-weight:600;" onclick="despaceOpenMediaPicker('<?php echo esc_js( $key ); ?>');">
								<?php echo $has_img ? 'Đổi ảnh' : 'Chọn ảnh'; ?>
							</button>
							<button type="button" class="button button-small despace-btn-remove-angle" style="color:#DC2626; font-size:11.5px; <?php echo $has_img ? '' : 'display:none;'; ?>" onclick="despaceRemoveAngle('<?php echo esc_js( $key ); ?>');">
								Gỡ
							</button>
						</div>

					</div>
				<?php endforeach; ?>
			</div>

			<!-- Synchronization & Dispatch Action Bar -->
			<div style="background:#fff; border:2px solid #E7E3DC; border-radius:14px; padding:20px 24px; box-shadow:0 4px 14px rgba(0,0,0,0.04);">
				<div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:18px;">
					<div>
						<div style="display:flex; align-items:center; gap:10px;">
							<span style="font-size:18px;">🧊</span>
							<h4 style="margin:0; font-size:16px; font-weight:700; color:#1C1917;">Trạng Thái Tiến Độ Xây Dựng Mô Hình 3D</h4>
							<span id="despace-sync-badge" style="background:#F3F4F6; color:#4B5563; font-weight:700; font-size:12px; padding:3px 10px; border-radius:99px;">
								<?php echo esc_html( strtoupper( $sync_status ) ); ?>
							</span>
						</div>
						<p id="despace-sync-status-msg" style="margin:6px 0 0; font-size:13px; color:#57534E;">
							<?php
							if ( $latest_job && ! empty( $latest_job['progress_message'] ) ) {
								echo esc_html( $latest_job['progress_message'] );
							} elseif ( $valid_count >= 3 ) {
								echo esc_html( sprintf( 'Đã sẵn sàng %d/5 ảnh góc. Bạn có thể gửi lệnh sang Desktop App để tự động dựng mô hình 3D.', $valid_count ) );
							} else {
								echo esc_html( sprintf( 'Hiện có %d/3 góc bắt buộc. Vui lòng nạp đủ tối thiểu 3 ảnh để đạt độ chi tiết cao nhất.', $valid_count ) );
							}
							?>
						</p>
					</div>

					<div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
						<label style="display:flex; align-items:center; gap:6px; font-size:13px; color:#44403C; cursor:pointer;">
							<input type="checkbox" name="despace_3d_auto_sync" value="yes" <?php checked( $auto_sync, 'yes' ); ?> />
							<span>Tự động gửi sang Desktop khi lưu sản phẩm</span>
						</label>

						<button type="button" id="despace-dispatch-desktop-btn" class="button button-primary button-hero" style="background:#C25E3A; border-color:#C25E3A; font-weight:700; font-size:14px; border-radius:8px; box-shadow:0 4px 12px rgba(194,94,58,0.28);" onclick="despaceDispatchToDesktop(<?php echo (int) $post->ID; ?>);">
							🚀 GỬI SANG DESKTOP DỰNG 3D NGAY
						</button>
					</div>
				</div>

				<!-- Live Progress Bar -->
				<div id="despace-progress-container" style="margin-top:16px; <?php echo ( 'processing' === $sync_status || 'dispatched' === $sync_status ) ? '' : 'display:none;'; ?>">
					<div style="display:flex; justify-content:space-between; font-size:12px; font-weight:700; color:#44403C; margin-bottom:6px;">
						<span id="despace-progress-step-text">Đang kết nối Desktop 3D Builder...</span>
						<span id="despace-progress-percent">0%</span>
					</div>
					<div style="width:100%; height:10px; background:#E5E7EB; border-radius:99px; overflow:hidden;">
						<div id="despace-progress-bar" style="width:0%; height:100%; background:linear-gradient(90deg, #C25E3A, #10B981); transition:width 0.4s ease;"></div>
					</div>
				</div>

				<!-- Recent Completed 3D Model Info -->
				<?php if ( $latest_job && 'completed' === $latest_job['status'] && ! empty( $latest_job['glb_url'] ) ) : ?>
					<div style="margin-top:16px; background:#ECFDF5; border:1px solid #10B981; border-radius:10px; padding:12px 16px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
						<div>
							<span style="color:#065F46; font-weight:700; font-size:13px;">✓ Mô hình 3D hoàn thiện đã được tải lên website & xuất bản:</span>
							<div style="font-family:monospace; font-size:12px; color:#047857; margin-top:2px;">
								<?php echo esc_html( $latest_job['glb_url'] ); ?>
								<?php if ( ! empty( $latest_job['vertex_count'] ) ) : ?>
									— (<?php echo number_format( (int) $latest_job['vertex_count'] ); ?> vertices, <?php echo number_format( (int) $latest_job['face_count'] ); ?> faces)
								<?php endif; ?>
							</div>
						</div>
						<a href="<?php echo esc_url( get_permalink( $post->ID ) ); ?>" target="_blank" class="button" style="background:#059669; color:#fff; border-color:#059669; font-weight:700;">
							Xem Trực Tiếp Trên Web ↗
						</a>
					</div>
				<?php endif; ?>

			</div>

		</div>

		<!-- Embedded Client Script -->
		<script>
		(function($){
			const DESKTOP_LOCAL_URL = 'http://127.0.0.1:<?php echo (int) self::DEFAULT_DESKTOP_PORT; ?>';
			let activeJobUuid = '<?php echo esc_js( $last_job_id ?: '' ); ?>';
			let pollingInterval = null;

			// Check if desktop agent is running locally
			function checkDesktopAgent() {
				const pill = $('#despace-desktop-agent-pill');
				const dot = pill.find('.status-dot');
				const label = pill.find('.status-label');

				fetch(DESKTOP_LOCAL_URL + '/api/health', { method: 'GET', mode: 'cors' })
					.then(res => res.json())
					.then(data => {
						if (data && data.status === 'online') {
							pill.css({ background: '#ECFDF5', borderColor: '#10B981', color: '#065F46' });
							dot.css('background', '#10B981');
							label.text('🟢 Desktop 3D Studio Online (Port <?php echo (int) self::DEFAULT_DESKTOP_PORT; ?>)');
						}
					})
					.catch(() => {
						pill.css({ background: '#FEF3C7', borderColor: '#F59E0B', color: '#92400E' });
						dot.css('background', '#F59E0B');
						label.text('🟡 Chế độ Đồng Bộ Hàng Đợi (REST API Queue)');
					});
			}

			// Open WP Media Picker for specific angle
			window.despaceOpenMediaPicker = function(angleKey) {
				const frame = wp.media({
					title: 'Chọn ảnh sản phẩm: ' + angleKey.toUpperCase(),
					multiple: false,
					library: { type: 'image' }
				});

				frame.on('select', function(){
					const attachment = frame.state().get('selection').first().toJSON();
					$('#despace_angle_' + angleKey + '_id').val(attachment.id);
					$('#despace_angle_' + angleKey + '_url').val(attachment.url);

					const card = $('.despace-angle-card[data-angle="' + angleKey + '"]');
					card.css('borderColor', '#10B981');
					card.find('.despace-angle-img').attr('src', attachment.url).show();
					card.find('.despace-angle-placeholder').hide();
					card.find('.despace-btn-remove-angle').show();
					card.find('.badge-status').css('color', '#059669').text('✓ Đã có ảnh');
				});

				frame.open();
			};

			// Remove angle image
			window.despaceRemoveAngle = function(angleKey) {
				$('#despace_angle_' + angleKey + '_id').val('');
				$('#despace_angle_' + angleKey + '_url').val('');

				const card = $('.despace-angle-card[data-angle="' + angleKey + '"]');
				card.css('borderColor', '#D1D5DB');
				card.find('.despace-angle-img').attr('src', '').hide();
				card.find('.despace-angle-placeholder').show();
				card.find('.despace-btn-remove-angle').hide();
				card.find('.badge-status').css('color', '#9CA3AF').text('Chưa có ảnh');
			};

			// Dispatch job to Desktop App
			window.despaceDispatchToDesktop = function(productId) {
				const btn = $('#despace-dispatch-desktop-btn');
				btn.prop('disabled', true).text('⏳ Đang gửi yêu cầu sang Desktop App...');

				$('#despace-progress-container').show();
				$('#despace-progress-bar').css('width', '10%');
				$('#despace-progress-percent').text('10%');
				$('#despace-progress-step-text').text('Đang tạo lệnh và đóng gói dữ liệu đa góc...');

				// 1. Send via WordPress AJAX to create sync job in DB
				$.post(ajaxurl, {
					action: 'despace_dispatch_desktop_3d',
					product_id: productId,
					nonce: '<?php echo esc_js( wp_create_nonce( 'despace_dispatch_3d_action' ) ); ?>'
				}, function(resp){
					if (resp && resp.success && resp.data) {
						activeJobUuid = resp.data.job_uuid;
						$('#despace-sync-badge').css({ background: '#EFF6FF', color: '#1E40AF' }).text('DISPATCHED');
						$('#despace-sync-status-msg').text('Đã dispatch tác vụ [' + activeJobUuid + ']. Desktop App đang nhận lệnh...');

						// 2. Also try zero-latency direct push to local Desktop HTTP listener if online
						fetch(DESKTOP_LOCAL_URL + '/api/task', {
							method: 'POST',
							headers: { 'Content-Type': 'application/json' },
							mode: 'cors',
							body: JSON.stringify(resp.data)
						}).catch(err => {
							console.log('Direct push to local desktop port deferred to polling:', err);
						});

						// Start polling status
						startPollingJob(activeJobUuid);
					} else {
						btn.prop('disabled', false).text('🚀 GỬI SANG DESKTOP DỰNG 3D NGAY');
						alert(resp && resp.data && resp.data.message ? resp.data.message : 'Có lỗi xảy ra khi tạo tác vụ.');
					}
				}).fail(function(){
					btn.prop('disabled', false).text('🚀 GỬI SANG DESKTOP DỰNG 3D NGAY');
					alert('Lỗi kết nối máy chủ.');
				});
			};

			function startPollingJob(jobUuid) {
				if (pollingInterval) clearInterval(pollingInterval);

				pollingInterval = setInterval(function(){
					$.get(ajaxurl, {
						action: 'despace_check_job_status',
						job_uuid: jobUuid,
						nonce: nonce
					}, function(resp){
						if (resp && resp.success && resp.data) {
							const job = resp.data;
							const progress = parseInt(job.progress || 0);

							$('#despace-progress-bar').css('width', progress + '%');
							$('#despace-progress-percent').text(progress + '%');
							$('#despace-progress-step-text').text(job.progress_message || 'Đang xử lý...');
							$('#despace-sync-status-msg').text(job.progress_message);

							if (job.status === 'completed') {
								clearInterval(pollingInterval);
								$('#despace-sync-badge').css({ background: '#ECFDF5', color: '#065F46' }).text('COMPLETED');
								$('#despace-dispatch-desktop-btn').prop('disabled', false).text('✓ ĐÃ HOÀN TẤT & XUẤT BẢN 3D');
								setTimeout(function(){ location.reload(); }, 1500);
							} else if (job.status === 'failed') {
								clearInterval(pollingInterval);
								$('#despace-sync-badge').css({ background: '#FEF2F2', color: '#991B1B' }).text('FAILED');
								$('#despace-dispatch-desktop-btn').prop('disabled', false).text('Thử lại');
								alert('Quá trình dựng 3D gặp lỗi: ' + (job.error_message || 'Không xác định'));
							}
						}
					});
				}, 2000);
			}

			// On ready
			$(document).ready(function(){
				checkDesktopAgent();
				setInterval(checkDesktopAgent, 8000);

				if (activeJobUuid && ('<?php echo esc_js( $sync_status ); ?>' === 'processing' || '<?php echo esc_js( $sync_status ); ?>' === 'dispatched')) {
					startPollingJob(activeJobUuid);
				}
			});
		})(jQuery);
		</script>
		<?php
	}

	/**
	 * Save Meta Box data from product edit.
	 */
	public function save_meta_box( $post_id ): void {
		if ( ! isset( $_POST['despace_multi_angle_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['despace_multi_angle_nonce'] ) ), 'despace_save_multi_angle_meta' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$angles = array( 'front', 'perspective', 'side', 'back', 'top' );
		foreach ( $angles as $key ) {
			if ( isset( $_POST["despace_angle_{$key}_id"] ) ) {
				update_post_meta( $post_id, "_despace_3d_angle_{$key}", absint( $_POST["despace_angle_{$key}_id"] ) );
			}
			if ( isset( $_POST["despace_angle_{$key}_url"] ) ) {
				update_post_meta( $post_id, "_despace_3d_angle_{$key}_url", esc_url_raw( wp_unslash( $_POST["despace_angle_{$key}_url"] ) ) );
			}
		}

		$auto_sync = isset( $_POST['despace_3d_auto_sync'] ) ? 'yes' : 'no';
		update_post_meta( $post_id, '_despace_3d_auto_sync', $auto_sync );

		// If auto-sync is enabled, only dispatch when 3D inputs changed and no active job is already running.
		if ( 'yes' === $auto_sync ) {
			$angles_data = self::get_product_multi_angles( $post_id );
			if ( self::count_valid_angles( $angles_data ) >= 1 ) {
				$input_sig = md5( wp_json_encode( array(
					'angles' => wp_list_pluck( $angles_data, 'url' ),
					'w'      => get_post_meta( $post_id, 'axgnud_dim_width', true ) ?: get_post_meta( $post_id, '_length', true ),
					'd'      => get_post_meta( $post_id, 'axgnud_dim_depth', true ) ?: get_post_meta( $post_id, '_width', true ),
					'h'      => get_post_meta( $post_id, 'axgnud_dim_height', true ) ?: get_post_meta( $post_id, '_height', true ),
				) ) );
				$prev_sig      = (string) get_post_meta( $post_id, '_despace_3d_input_hash', true );
				$cur_status    = (string) get_post_meta( $post_id, '_despace_3d_sync_status', true );
				$is_in_flight  = in_array( $cur_status, array( 'dispatched', 'processing' ), true );

				if ( ! $is_in_flight && $input_sig !== $prev_sig ) {
					update_post_meta( $post_id, '_despace_3d_input_hash', $input_sig );
					self::dispatch_sync_job( $post_id, 'auto_save' );
				}
			}
		}
	}

	/**
	 * Enqueue admin scripts for product page.
	 */
	public function admin_enqueue_scripts( $hook ): void {
		if ( 'post.php' === $hook || 'post-new.php' === $hook ) {
			global $post;
			if ( $post && 'product' === $post->post_type ) {
				wp_enqueue_media();
			}
		}
	}

	/**
	 * Create and dispatch a 3D sync job for a product.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $source     Source of dispatch (web_admin, auto_save, excel_batch).
	 * @return array Job details array.
	 */
	public static function dispatch_sync_job( int $product_id, string $source = 'web_admin' ): array {
		global $wpdb;

		$table_name = self::get_jobs_table_name();
		$job_uuid   = 'JOB-3D-' . strtoupper( wp_generate_password( 12, false ) );
		$product    = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;

		$sku  = $product ? $product->get_sku() : ( get_post_meta( $product_id, '_sku', true ) ?: 'DESPACE-' . $product_id );
		$name = $product ? $product->get_name() : get_the_title( $product_id );

		// Gather dimensions (prioritize explicit axgnud_dim_* before WC _length/_width)
		$dimensions = array(
			'width'  => floatval( get_post_meta( $product_id, 'axgnud_dim_width', true ) ?: ( get_post_meta( $product_id, '_length', true ) ?: 100 ) ),
			'depth'  => floatval( get_post_meta( $product_id, 'axgnud_dim_depth', true ) ?: ( get_post_meta( $product_id, '_width', true ) ?: 80 ) ),
			'height' => floatval( get_post_meta( $product_id, 'axgnud_dim_height', true ) ?: ( get_post_meta( $product_id, '_height', true ) ?: 75 ) ),
			'unit'   => 'cm',
		);

		// Multi-angle images
		$angles_raw = self::get_product_multi_angles( $product_id );
		$angles_payload = array();
		foreach ( $angles_raw as $k => $item ) {
			if ( ! empty( $item['url'] ) ) {
				$angles_payload[ $k ] = array(
					'id'    => $item['id'],
					'url'   => $item['url'],
					'label' => $item['label'],
				);
			}
		}

		// Category
		$terms    = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'names' ) );
		$category = is_array( $terms ) && ! empty( $terms ) ? $terms[0] : 'Furniture';

		$now = current_time( 'mysql' );
		$wpdb->insert(
			$table_name,
			array(
				'job_uuid'           => $job_uuid,
				'product_id'         => $product_id,
				'sku'                => $sku,
				'product_name'       => $name,
				'status'             => 'dispatched',
				'progress'           => 10,
				'progress_message'   => __( 'Đã gửi yêu cầu sang Desktop App, đang xếp hàng xử lý...', 'despace-core' ),
				'multi_angles_json'  => wp_json_encode( $angles_payload ),
				'dimensions_json'    => wp_json_encode( $dimensions ),
				'category'           => $category,
				'source'             => $source,
				'created_at'         => $now,
				'updated_at'         => $now,
			),
			array( '%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		// Update product meta
		update_post_meta( $product_id, '_despace_3d_sync_status', 'dispatched' );
		update_post_meta( $product_id, '_despace_3d_last_job_id', $job_uuid );

		return array(
			'job_uuid'     => $job_uuid,
			'product_id'   => $product_id,
			'sku'          => $sku,
			'product_name' => $name,
			'category'     => $category,
			'dimensions'   => $dimensions,
			'angles'       => $angles_payload,
			'multi_angles' => $angles_payload,
			'status'       => 'dispatched',
			'progress'     => 10,
			'site_url'     => home_url(),
			'created_at'   => $now,
		);
	}

	/**
	 * Retrieve job by UUID.
	 */
	public static function get_job_by_uuid( string $job_uuid ): ?array {
		global $wpdb;
		$table_name = self::get_jobs_table_name();
		$mode       = defined( 'ARRAY_A' ) ? \ARRAY_A : 'ARRAY_A';
		$row        = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_name} WHERE job_uuid = %s LIMIT 1", $job_uuid ), $mode );
		return $row ?: null;
	}

	/**
	 * List jobs with filter.
	 */
	public static function list_jobs( string $status = '', int $limit = 50 ): array {
		global $wpdb;
		$table_name = self::get_jobs_table_name();
		$limit      = max( 1, min( 200, $limit ) );
		$mode       = defined( 'ARRAY_A' ) ? \ARRAY_A : 'ARRAY_A';

		if ( $status ) {
			$results = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table_name} WHERE status = %s ORDER BY id DESC LIMIT %d", $status, $limit ), $mode );
		} else {
			$results = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table_name} ORDER BY id DESC LIMIT %d", $limit ), $mode );
		}

		return $results ?: array();
	}

	/**
	 * Update job progress.
	 */
	public static function update_job_progress( string $job_uuid, int $progress, string $message, string $status = 'processing' ): bool {
		global $wpdb;
		$table_name = self::get_jobs_table_name();
		$progress   = max( 0, min( 100, $progress ) );
		$now        = current_time( 'mysql' );

		$job = self::get_job_by_uuid( $job_uuid );
		if ( ! $job ) {
			return false;
		}

		$updated = $wpdb->update(
			$table_name,
			array(
				'status'           => $status,
				'progress'         => $progress,
				'progress_message' => sanitize_text_field( $message ),
				'updated_at'       => $now,
			),
			array( 'job_uuid' => $job_uuid ),
			array( '%s', '%d', '%s', '%s' ),
			array( '%s' )
		);

		update_post_meta( (int) $job['product_id'], '_despace_3d_sync_status', $status );

		return false !== $updated;
	}

	/**
	 * Complete a job with uploaded GLB model and metadata.
	 *
	 * @param string $job_uuid Job UUID.
	 * @param string $glb_url  Final public URL to GLB file.
	 * @param array  $metadata Additional technical metadata (vertex_count, face_count, usdz_url, thumbnail_url, etc.).
	 * @return bool True on success.
	 */
	public static function complete_job( string $job_uuid, string $glb_url, array $metadata = array() ): bool {
		global $wpdb;
		$table_name = self::get_jobs_table_name();
		$now        = current_time( 'mysql' );

		$job = self::get_job_by_uuid( $job_uuid );
		if ( ! $job ) {
			return false;
		}

		$product_id = (int) $job['product_id'];
		$sku        = $job['sku'] ?: 'DESPACE-' . $product_id;

		$usdz_url       = sanitize_text_field( $metadata['usdz_url'] ?? '' );
		$thumbnail_url  = sanitize_text_field( $metadata['thumbnail_url'] ?? '' );
		$vertex_count   = intval( $metadata['vertex_count'] ?? 0 );
		$face_count     = intval( $metadata['face_count'] ?? 0 );
		$file_size      = intval( $metadata['file_size_bytes'] ?? 0 );

		// 1. Update jobs table
		$wpdb->update(
			$table_name,
			array(
				'status'           => 'completed',
				'progress'         => 100,
				'progress_message' => __( 'Hoàn thành xây dựng mô hình 3D & đã xuất bản lên Website!', 'despace-core' ),
				'glb_url'          => esc_url_raw( $glb_url ),
				'usdz_url'         => esc_url_raw( $usdz_url ),
				'thumbnail_url'    => esc_url_raw( $thumbnail_url ),
				'vertex_count'     => $vertex_count,
				'face_count'       => $face_count,
				'file_size_bytes'  => $file_size,
				'completed_at'     => $now,
				'updated_at'       => $now,
			),
			array( 'job_uuid' => $job_uuid ),
			array( '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s' ),
			array( '%s' )
		);

		// 2. Update WooCommerce product postmeta
		update_post_meta( $product_id, '_despace_model_glb', $glb_url );
		update_post_meta( $product_id, '_despace_glb_url', $glb_url );
		update_post_meta( $product_id, '_axgnud_model_glb', $glb_url );
		update_post_meta( $product_id, '_glb_file', $glb_url );
		update_post_meta( $product_id, '_ar_glb_url', $glb_url );
		if ( $usdz_url ) {
			update_post_meta( $product_id, '_despace_model_usdz', $usdz_url );
			update_post_meta( $product_id, '_axgnud_model_usdz', $usdz_url );
			update_post_meta( $product_id, '_usdz_file', $usdz_url );
			update_post_meta( $product_id, '_ar_usdz_url', $usdz_url );
		}
		update_post_meta( $product_id, '_ar_enabled', 'yes' );
		update_post_meta( $product_id, '_despace_enable_ar', 'yes' );
		update_post_meta( $product_id, '_axgnud_3d_status', 'published' );
		update_post_meta( $product_id, '_despace_3d_status', 'published' );
		update_post_meta( $product_id, '_despace_3d_sync_status', 'completed' );
		update_post_meta( $product_id, '_has_3d_model', 'yes' );

		// 3. Update or Insert into wp_despace_product_3d_models (and legacy wp_axgnud_product_3d_models if present)
		$dims   = json_decode( $job['dimensions_json'] ?? '{}', true );
		$width  = floatval( $dims['width'] ?? 0 );
		$depth  = floatval( $dims['depth'] ?? 0 );
		$height = floatval( $dims['height'] ?? 0 );

		$candidate_tables = array(
			$wpdb->prefix . 'despace_product_3d_models',
			$wpdb->prefix . 'axgnud_product_3d_models',
		);

		foreach ( $candidate_tables as $table_models ) {
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_models ) ) !== $table_models ) {
				continue;
			}

			$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table_models} WHERE product_id = %d LIMIT 1", $product_id ) );
			if ( $exists ) {
				$wpdb->update(
					$table_models,
					array(
						'sku'           => $sku,
						'glb_url'       => $glb_url,
						'usdz_url'      => $usdz_url,
						'thumbnail_url' => $thumbnail_url,
						'width'         => $width,
						'depth'         => $depth,
						'height'        => $height,
						'model_status'  => 'published',
						'ar_enabled'    => 1,
						'updated_at'    => $now,
					),
					array( 'product_id' => $product_id ),
					array( '%s', '%s', '%s', '%s', '%f', '%f', '%f', '%s', '%d', '%s' ),
					array( '%d' )
				);
			} else {
				$wpdb->insert(
					$table_models,
					array(
						'product_id'    => $product_id,
						'sku'           => $sku,
						'glb_url'       => $glb_url,
						'usdz_url'      => $usdz_url,
						'thumbnail_url' => $thumbnail_url,
						'width'         => $width,
						'depth'         => $depth,
						'height'        => $height,
						'unit'          => 'cm',
						'model_status'  => 'published',
						'ar_enabled'    => 1,
						'created_at'    => $now,
						'updated_at'    => $now,
					),
					array( '%d', '%s', '%s', '%s', '%s', '%f', '%f', '%f', '%s', '%s', '%d', '%s', '%s' )
				);
			}
		}

		return true;
	}

	/**
	 * Create or update a WooCommerce product directly from DeSpace Desktop Studio,
	 * import product photos, attach auto-constructed GLB + USDZ 3D models, and publish.
	 *
	 * @param array $data Product, media, and 3D model payload.
	 * @return array Result summary with product_id, permalink, glb_url, usdz_url.
	 */
	public static function publish_product_from_desktop( array $data ): array {
		global $wpdb;

		$raw_sku = sanitize_text_field( (string) ( $data['sku'] ?? '' ) );
		if ( '' === $raw_sku ) {
			$raw_sku = 'DSP-' . strtoupper( wp_generate_password( 6, false ) );
		}

		$name        = sanitize_text_field( (string) ( $data['name'] ?? $data['product_name'] ?? $raw_sku ) );
		$name_en     = sanitize_text_field( (string) ( $data['name_en'] ?? '' ) );
		$reg_price   = isset( $data['regular_price'] ) && '' !== (string) $data['regular_price'] ? floatval( $data['regular_price'] ) : 0.0;
		$sale_price  = isset( $data['sale_price'] ) && '' !== (string) $data['sale_price'] ? floatval( $data['sale_price'] ) : 0.0;
		$stock_qty   = isset( $data['stock_quantity'] ) && '' !== (string) $data['stock_quantity'] ? intval( $data['stock_quantity'] ) : 20;
		$short_desc  = isset( $data['short_description'] ) ? wp_kses_post( (string) $data['short_description'] ) : '';
		$description = isset( $data['description'] ) ? wp_kses_post( (string) $data['description'] ) : '';
		$category_in = sanitize_text_field( (string) ( $data['category'] ?? '' ) );
		$badge       = sanitize_text_field( (string) ( $data['badge'] ?? 'New' ) );
		$finish_col  = sanitize_text_field( (string) ( $data['color'] ?? $data['material'] ?? '' ) );

		$dims_in = is_array( $data['dimensions'] ?? null ) ? $data['dimensions'] : array();
		$width   = max( 10.0, floatval( $dims_in['width'] ?? $data['width'] ?? 100.0 ) );
		$depth   = max( 10.0, floatval( $dims_in['depth'] ?? $data['depth'] ?? 80.0 ) );
		$height  = max( 10.0, floatval( $dims_in['height'] ?? $data['height'] ?? 75.0 ) );

		// 1. Locate existing product by ID or SKU, or create a new WooCommerce Simple Product
		$product_id = absint( $data['product_id'] ?? 0 );
		if ( ! $product_id && function_exists( 'wc_get_product_id_by_sku' ) ) {
			$product_id = (int) wc_get_product_id_by_sku( $raw_sku );
		}
		if ( ! $product_id && isset( $wpdb->postmeta ) ) {
			$product_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_sku' AND meta_value = %s LIMIT 1", $raw_sku ) );
		}

		$is_new_product = ( 0 === $product_id );

		if ( class_exists( 'WC_Product_Simple' ) ) {
			$wc_product = $product_id ? wc_get_product( $product_id ) : null;
			if ( ! $wc_product || ! is_a( $wc_product, 'WC_Product' ) ) {
				$wc_product     = new \WC_Product_Simple();
				$is_new_product = true;
			}

			$wc_product->set_name( $name );
			$wc_product->set_status( 'publish' );
			$wc_product->set_catalog_visibility( 'visible' );

			try {
				$wc_product->set_sku( $raw_sku );
			} catch ( \Throwable $e ) {
				// Keep existing SKU if duplicate check throws
			}

			if ( $reg_price > 0 ) {
				$wc_product->set_regular_price( (string) $reg_price );
			} elseif ( $is_new_product && 0.0 === $reg_price ) {
				$wc_product->set_regular_price( '2500000' );
			}

			$effective_reg = $reg_price > 0 ? $reg_price : (float) $wc_product->get_regular_price();
			if ( $sale_price > 0 && ( $effective_reg <= 0.0 || $sale_price < $effective_reg ) ) {
				$wc_product->set_sale_price( (string) $sale_price );
			}

			if ( '' !== $short_desc ) {
				$wc_product->set_short_description( $short_desc );
			} elseif ( $is_new_product ) {
				$wc_product->set_short_description(
					sprintf(
						'%s thiết kế chuẩn kiến trúc DeSpace (Rộng %s × Sâu %s × Cao %s cm), tích hợp sẵn mô hình 3D 360° & Camera AR tỷ lệ thực 1:1.',
						$name,
						$width,
						$depth,
						$height
					)
				);
			}

			if ( '' !== $description ) {
				$wc_product->set_description( $description );
			}

			$wc_product->set_manage_stock( true );
			$wc_product->set_stock_quantity( max( 0, $stock_qty ) );
			$wc_product->set_stock_status( $stock_qty > 0 ? 'instock' : 'outofstock' );

			// Map physical dimensions consistently across WooCommerce & AR Visualizer
			$wc_product->set_length( (string) $width );
			$wc_product->set_width( (string) $depth );
			$wc_product->set_height( (string) $height );

			$product_id = (int) $wc_product->save();
		} else {
			// Standalone / Mock CLI fallback when WooCommerce runtime is not loaded
			if ( ! $product_id ) {
				if ( function_exists( 'wp_insert_post' ) ) {
					$product_id = (int) wp_insert_post( array(
						'post_title'   => $name,
						'post_content' => $description,
						'post_excerpt' => $short_desc,
						'post_status'  => 'publish',
						'post_type'    => 'product',
					) );
				} else {
					$product_id = random_int( 1000, 99999 );
					$GLOBALS['mock_posts'][ $product_id ] = array( 'post_title' => $name );
				}
			}
			update_post_meta( $product_id, '_sku', $raw_sku );
			if ( $reg_price > 0 ) {
				update_post_meta( $product_id, '_regular_price', (string) $reg_price );
				update_post_meta( $product_id, '_price', (string) ( $sale_price > 0 ? $sale_price : $reg_price ) );
			}
			update_post_meta( $product_id, '_stock', (string) $stock_qty );
		}

		// Sync explicit dimension & bilingual metadata
		update_post_meta( $product_id, '_length', (string) $width );
		update_post_meta( $product_id, '_width', (string) $depth );
		update_post_meta( $product_id, '_height', (string) $height );
		update_post_meta( $product_id, 'axgnud_dim_width', (string) $width );
		update_post_meta( $product_id, 'axgnud_dim_depth', (string) $depth );
		update_post_meta( $product_id, 'axgnud_dim_height', (string) $height );

		if ( $name_en ) {
			update_post_meta( $product_id, '_despace_title_en', $name_en );
		}
		if ( $badge ) {
			update_post_meta( $product_id, '_despace_badge', $badge );
		}
		if ( $finish_col ) {
			update_post_meta( $product_id, '_despace_finish_color', $finish_col );
		}

		// 2. Assign WooCommerce Category (product_cat)
		if ( $category_in && function_exists( 'wp_set_object_terms' ) && function_exists( 'get_term_by' ) ) {
			$term_id = 0;
			if ( is_numeric( $category_in ) ) {
				$term_id = absint( $category_in );
			} else {
				$term = get_term_by( 'slug', sanitize_title( $category_in ), 'product_cat' );
				if ( ! $term ) {
					$term = get_term_by( 'name', $category_in, 'product_cat' );
				}
				if ( ! $term ) {
					// Map common Desktop archetype / Vietnamese slugs to store categories if available
					$archetype_cat_map = array(
						'shoe_cabinet'         => 'shoe-racks-storage',
						'tu-giay-luu-tru'      => 'shoe-racks-storage',
						'cabinet'              => 'chest-of-drawers-sideboards',
						'tu-bep'               => 'kitchen-cabinets',
						'wardrobe'             => 'wardrobes',
						'tu-quan-ao'           => 'wardrobes',
						'bookcase'             => 'bookshelves',
						'ke-sach-tu-trung-bay' => 'bookshelves',
					);
					$mapped_slug = $archetype_cat_map[ strtolower( $category_in ) ] ?? '';
					if ( $mapped_slug ) {
						$term = get_term_by( 'slug', $mapped_slug, 'product_cat' );
					}
				}
				if ( $term && ! is_wp_error( $term ) ) {
					$term_id = (int) $term->term_id;
				} elseif ( function_exists( 'wp_insert_term' ) ) {
					$inserted = wp_insert_term( $category_in, 'product_cat' );
					if ( ! is_wp_error( $inserted ) && ! empty( $inserted['term_id'] ) ) {
						$term_id = (int) $inserted['term_id'];
					}
				}
			}
			if ( $term_id > 0 ) {
				wp_set_object_terms( $product_id, array( $term_id ), 'product_cat' );
			}
		}

		// 3. Ingest Product Photos (Base64 or URLs) into Media Library & Multi-Angle Meta
		$images_in      = is_array( $data['images'] ?? null ) ? $data['images'] : array();
		$attachment_ids = array();
		$angle_order    = array( 'front', 'perspective', 'side', 'back', 'top' );
		$upload_dir     = wp_upload_dir();

		foreach ( $images_in as $idx => $img_item ) {
			if ( ! is_array( $img_item ) ) {
				continue;
			}
			$angle_key = sanitize_key( $img_item['angle'] ?? ( $angle_order[ $idx ] ?? 'extra' ) );
			$att_id    = 0;
			$att_url   = '';

			if ( ! empty( $img_item['data_base64'] ) ) {
				if ( count( $attachment_ids ) >= 10 ) {
					continue;
				}
				$raw_bytes = base64_decode( (string) $img_item['data_base64'], true );
				$max_img   = 5 * 1024 * 1024;
				$img_info  = ( false !== $raw_bytes && strlen( $raw_bytes ) > 32 && strlen( $raw_bytes ) <= $max_img && function_exists( 'getimagesizefromstring' ) )
					? @getimagesizefromstring( $raw_bytes )
					: false;
				$img_mime  = is_array( $img_info ) ? (string) ( $img_info['mime'] ?? '' ) : '';
				$mime_ext  = array(
					'image/jpeg' => 'jpg',
					'image/png'  => 'png',
					'image/webp' => 'webp',
				);
				if ( isset( $mime_ext[ $img_mime ] ) ) {
					$ext         = $mime_ext[ $img_mime ];
					$img_dir     = $upload_dir['basedir'] . '/despace-desktop-products';
					wp_mkdir_p( $img_dir );
					$safe_prefix = preg_replace( '/[^A-Za-z0-9_-]/', '', sanitize_file_name( (string) $raw_sku ) ) ?: ( 'product-' . $product_id );
					$unique_name = sanitize_file_name( $safe_prefix . '-' . $angle_key . '-' . time() . '-' . $idx . '.' . $ext );
					$dest_path   = $img_dir . '/' . $unique_name;
					// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
					if ( false !== file_put_contents( $dest_path, $raw_bytes ) ) {
						$att_url = $upload_dir['baseurl'] . '/despace-desktop-products/' . $unique_name;

						if ( function_exists( 'wp_insert_attachment' ) ) {
							$att_id = (int) wp_insert_attachment(
								array(
									'guid'           => $att_url,
									'post_mime_type' => $img_mime,
									'post_title'     => $name . ' (' . strtoupper( $angle_key ) . ')',
									'post_content'   => '',
									'post_status'    => 'inherit',
								),
								$dest_path,
								$product_id
							);
							if ( $att_id > 0 ) {
								if ( ! function_exists( 'wp_generate_attachment_metadata' ) && defined( 'ABSPATH' ) && file_exists( ABSPATH . 'wp-admin/includes/image.php' ) ) {
									require_once ABSPATH . 'wp-admin/includes/image.php';
								}
								if ( function_exists( 'wp_generate_attachment_metadata' ) && function_exists( 'wp_update_attachment_metadata' ) ) {
									$meta_data = wp_generate_attachment_metadata( $att_id, $dest_path );
									wp_update_attachment_metadata( $att_id, $meta_data );
								}
							}
						}
					}
				}
			} elseif ( ! empty( $img_item['url'] ) ) {
				$att_url = esc_url_raw( (string) $img_item['url'] );
			}

			if ( $att_id > 0 ) {
				$attachment_ids[] = $att_id;
			}
			if ( in_array( $angle_key, $angle_order, true ) ) {
				if ( $att_id > 0 ) {
					update_post_meta( $product_id, "_despace_3d_angle_{$angle_key}", $att_id );
				}
				if ( $att_url ) {
					update_post_meta( $product_id, "_despace_3d_angle_{$angle_key}_url", $att_url );
				}
			}
		}

		if ( ! empty( $attachment_ids ) ) {
			update_post_meta( $product_id, '_thumbnail_id', $attachment_ids[0] );
			if ( count( $attachment_ids ) > 1 ) {
				update_post_meta( $product_id, '_product_image_gallery', implode( ',', array_slice( $attachment_ids, 1 ) ) );
			}
		}

		// 4. Save Auto-Constructed 3D Assets (.GLB + .USDZ + Thumbnail) & Complete Job
		$safe_sku      = preg_replace( '/[^A-Za-z0-9_-]/', '', sanitize_file_name( (string) $raw_sku ) ) ?: ( 'product-' . $product_id );
		$target_3d_dir = $upload_dir['basedir'] . '/3d/' . $safe_sku;
		wp_mkdir_p( $target_3d_dir );

		$max_model_bytes = 25 * 1024 * 1024;
		$max_thumb_bytes = 5 * 1024 * 1024;

		$glb_url   = '';
		$usdz_url  = '';
		$thumb_url = '';
		$glb_size  = 0;

		if ( ! empty( $data['glb_base64'] ) ) {
			$glb_bin = base64_decode( (string) $data['glb_base64'], true );
			if ( false !== $glb_bin && strlen( $glb_bin ) >= 12 && strlen( $glb_bin ) <= $max_model_bytes && 'glTF' === substr( $glb_bin, 0, 4 ) ) {
				$glb_file = $target_3d_dir . '/model.glb';
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
				if ( false !== file_put_contents( $glb_file, $glb_bin ) ) {
					$glb_url  = $upload_dir['baseurl'] . '/3d/' . $safe_sku . '/model.glb';
					$glb_size = strlen( $glb_bin );
				}
			}
		} elseif ( ! empty( $data['glb_url'] ) ) {
			$glb_url = esc_url_raw( (string) $data['glb_url'] );
		}

		if ( ! empty( $data['usdz_base64'] ) ) {
			$usdz_bin = base64_decode( (string) $data['usdz_base64'], true );
			if ( false !== $usdz_bin && strlen( $usdz_bin ) >= 30 && strlen( $usdz_bin ) <= $max_model_bytes && "PK\x03\x04" === substr( $usdz_bin, 0, 4 ) ) {
				$usdz_file = $target_3d_dir . '/model.usdz';
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
				if ( false !== file_put_contents( $usdz_file, $usdz_bin ) ) {
					$usdz_url = $upload_dir['baseurl'] . '/3d/' . $safe_sku . '/model.usdz';
				}
			}
		} elseif ( ! empty( $data['usdz_url'] ) ) {
			$usdz_url = esc_url_raw( (string) $data['usdz_url'] );
		}

		if ( ! empty( $data['thumbnail_base64'] ) ) {
			$thumb_bin  = base64_decode( (string) $data['thumbnail_base64'], true );
			$thumb_info = ( false !== $thumb_bin && strlen( $thumb_bin ) > 32 && strlen( $thumb_bin ) <= $max_thumb_bytes && function_exists( 'getimagesizefromstring' ) )
				? @getimagesizefromstring( $thumb_bin )
				: false;
			$thumb_mime = is_array( $thumb_info ) ? (string) ( $thumb_info['mime'] ?? '' ) : '';
			$thumb_exts = array( 'image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp' );
			if ( isset( $thumb_exts[ $thumb_mime ] ) ) {
				$thumb_name = 'thumb.' . $thumb_exts[ $thumb_mime ];
				$thumb_file = $target_3d_dir . '/' . $thumb_name;
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
				if ( false !== file_put_contents( $thumb_file, $thumb_bin ) ) {
					$thumb_url = $upload_dir['baseurl'] . '/3d/' . $safe_sku . '/' . $thumb_name;
				}
			}
		}

		$job      = self::dispatch_sync_job( $product_id, 'desktop_publish' );
		$job_uuid = $job['job_uuid'];

		if ( $glb_url ) {
			self::complete_job(
				$job_uuid,
				$glb_url,
				array(
					'usdz_url'        => $usdz_url,
					'thumbnail_url'   => $thumb_url,
					'vertex_count'    => intval( $data['vertex_count'] ?? 0 ),
					'face_count'      => intval( $data['face_count'] ?? 0 ),
					'file_size_bytes' => intval( $data['file_size'] ?? $glb_size ),
				)
			);
		}

		if ( function_exists( 'wc_delete_product_transients' ) ) {
			wc_delete_product_transients( $product_id );
		}

		$permalink = function_exists( 'get_permalink' ) ? get_permalink( $product_id ) : home_url( '/?p=' . $product_id );

		return array(
			'success'        => true,
			'is_new_product' => $is_new_product,
			'product_id'     => $product_id,
			'sku'            => $raw_sku,
			'name'           => $name,
			'permalink'      => $permalink,
			'job_uuid'       => $job_uuid,
			'glb_url'        => $glb_url,
			'usdz_url'       => $usdz_url,
			'thumbnail_url'  => $thumb_url,
			'ar_enabled'     => ! empty( $glb_url ) && ! empty( $usdz_url ),
		);
	}

	/**
	 * AJAX Handler: Dispatch 3D sync job from product edit screen.
	 */
	public function ajax_dispatch_job(): void {
		check_ajax_referer( 'despace_dispatch_3d_action', 'nonce' );

		if ( ! current_user_can( 'edit_products' ) && ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_despace_3d' ) && ! current_user_can( 'edit_despace_products' ) ) {
			wp_send_json_error( array( 'message' => __( 'Bạn không có quyền quản lý mô hình 3D.', 'despace-core' ) ) );
		}

		$product_id = absint( $_POST['product_id'] ?? 0 );
		if ( ! $product_id || ( function_exists( 'get_post_type' ) && 'product' !== get_post_type( $product_id ) ) ) {
			wp_send_json_error( array( 'message' => __( 'Product ID không hợp lệ.', 'despace-core' ) ), 400 );
		}
		if ( ! current_user_can( 'edit_post', $product_id ) && ! current_user_can( 'manage_despace_3d' ) && ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Bạn không có quyền chỉnh sửa sản phẩm này.', 'despace-core' ) ), 403 );
		}

		$angles = self::get_product_multi_angles( $product_id );
		$valid_count = self::count_valid_angles( $angles );
		if ( $valid_count < 1 ) {
			wp_send_json_error( array( 'message' => __( 'Sản phẩm chưa có ảnh nào. Vui lòng tải lên tối thiểu 3 ảnh góc chụp sản phẩm.', 'despace-core' ) ) );
		}

		$job_data = self::dispatch_sync_job( $product_id, 'web_admin' );
		wp_send_json_success( $job_data );
	}

	/**
	 * AJAX Handler: Check job progress.
	 */
	public function ajax_check_job_status(): void {
		check_ajax_referer( 'despace_dispatch_3d_action', 'nonce' );

		if ( ! current_user_can( 'edit_products' ) && ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_despace_3d' ) && ! current_user_can( 'edit_despace_products' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
		}

		$job_uuid = sanitize_text_field( wp_unslash( $_GET['job_uuid'] ?? '' ) );
		if ( ! $job_uuid ) {
			wp_send_json_error( array( 'message' => 'Missing job_uuid' ) );
		}

		$job = self::get_job_by_uuid( $job_uuid );
		if ( ! $job ) {
			wp_send_json_error( array( 'message' => 'Job not found' ) );
		}

		wp_send_json_success( $job );
	}
}
