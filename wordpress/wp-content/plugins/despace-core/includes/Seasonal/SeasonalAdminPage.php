<?php
/**
 * DeSpace Seasonal Mode (Noel) Admin Settings Page.
 *
 * Accessible strictly to Administrators (manage_options) under
 * DeSpace -> Giao diện Mùa lễ, harmonized with the Editorial Luxury Admin Cockpit.
 *
 * @package DeSpace\Seasonal
 */

namespace DeSpace\Seasonal;

defined( 'ABSPATH' ) || exit;

/**
 * Class SeasonalAdminPage
 */
class SeasonalAdminPage {

	/**
	 * Admin page slug.
	 */
	public const PAGE_SLUG = 'despace-seasonal';

	/**
	 * Nonce action & field name.
	 */
	public const NONCE_ACTION = 'despace_save_seasonal_settings';
	public const NONCE_FIELD  = 'despace_seasonal_nonce';

	/**
	 * Register hooks.
	 */
	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'register_fallback_menu' ), 30 );
		add_action( 'admin_post_' . self::NONCE_ACTION, array( __CLASS__, 'handle_form_submit' ) );
	}

	/**
	 * Register submenu under DeSpace Hub if not already registered by despace-store-admin,
	 * or under Appearance as a clean fallback when running standalone.
	 */
	public static function register_fallback_menu(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// If despace-store-admin already mounted it under 'despace-hub', skip duplicate registration.
		global $submenu;
		if ( isset( $submenu['despace-hub'] ) ) {
			foreach ( $submenu['despace-hub'] as $item ) {
				if ( isset( $item[2] ) && self::PAGE_SLUG === $item[2] ) {
					return;
				}
			}
			add_submenu_page(
				'despace-hub',
				__( 'DeSpace — Giao diện Mùa lễ (Noel)', 'despace-core' ),
				__( 'Giao diện Mùa lễ', 'despace-core' ),
				'manage_options',
				self::PAGE_SLUG,
				array( __CLASS__, 'render_page' )
			);
			return;
		}

		add_theme_page(
			__( 'DeSpace — Giao diện Mùa lễ (Noel)', 'despace-core' ),
			__( 'DeSpace Mùa lễ', 'despace-core' ),
			'manage_options',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Handle settings form submission with strict RBAC + nonce verification.
	 */
	public static function handle_form_submit(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'Bạn không có quyền thay đổi cài đặt Giao diện Mùa lễ DeSpace.', 'despace-core' ),
				esc_html__( 'Truy cập bị từ chối', 'despace-core' ),
				array( 'response' => 403 )
			);
		}

		check_admin_referer( self::NONCE_ACTION, self::NONCE_FIELD );

		$raw = array(
			'status'           => isset( $_POST['status'] ) ? wp_unslash( $_POST['status'] ) : 'off',
			'preset'           => isset( $_POST['preset'] ) ? wp_unslash( $_POST['preset'] ) : 'nordic',
			'snow_enabled'     => ! empty( $_POST['snow_enabled'] ),
			'snow_density'     => isset( $_POST['snow_density'] ) ? wp_unslash( $_POST['snow_density'] ) : 'light',
			'header_accent'    => ! empty( $_POST['header_accent'] ),
			'marquee_vi'       => isset( $_POST['marquee_vi'] ) ? wp_unslash( $_POST['marquee_vi'] ) : '',
			'marquee_en'       => isset( $_POST['marquee_en'] ) ? wp_unslash( $_POST['marquee_en'] ) : '',
			'promo_code'       => isset( $_POST['promo_code'] ) ? wp_unslash( $_POST['promo_code'] ) : '',
			'promo_banner_vi'  => isset( $_POST['promo_banner_vi'] ) ? wp_unslash( $_POST['promo_banner_vi'] ) : '',
			'promo_banner_en'  => isset( $_POST['promo_banner_en'] ) ? wp_unslash( $_POST['promo_banner_en'] ) : '',
			'schedule_enabled' => ! empty( $_POST['schedule_enabled'] ),
			'start_date'       => isset( $_POST['start_date'] ) ? wp_unslash( $_POST['start_date'] ) : '',
			'end_date'         => isset( $_POST['end_date'] ) ? wp_unslash( $_POST['end_date'] ) : '',
		);

		SeasonalSettings::save( $raw );

		// Flush caches if available so storefront reflects changes immediately.
		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
		}

		$redirect_url = add_query_arg(
			array(
				'page'  => self::PAGE_SLUG,
				'saved' => '1',
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Render the Seasonal Mode Settings interface.
	 */
	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'Bạn không có quyền truy cập trang Giao diện Mùa lễ DeSpace.', 'despace-core' ),
				esc_html__( 'Truy cập bị từ chối', 'despace-core' ),
				array( 'response' => 403 )
			);
		}

		$state       = SeasonalSettings::get_effective_state( true, null, false );
		$settings    = $state['settings'];
		$presets     = SeasonalSettings::preset_catalog();
		$has_cockpit = function_exists( 'despace_render_admin_cockpit_header' );
		$saved       = isset( $_GET['saved'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['saved'] ) );
		$tz_label    = function_exists( 'wp_timezone_string' ) ? wp_timezone_string() : 'UTC';
		$today_wp    = SeasonalSettings::today_wp_date();

		$status_badges = array(
			'off'               => array( 'label' => 'Đang tắt — Giao diện gốc (0 KB Noel)', 'cls' => 'ds-seasonal-pill--off' ),
			'preview'           => array( 'label' => 'Xem trước — Chỉ Admin nhìn thấy', 'cls' => 'ds-seasonal-pill--preview' ),
			'active'            => array( 'label' => 'Đang bật công khai (Live)', 'cls' => 'ds-seasonal-pill--live' ),
			'scheduled_pending' => array( 'label' => 'Đã lên lịch — Chưa đến ngày bắt đầu', 'cls' => 'ds-seasonal-pill--preview' ),
			'scheduled_expired' => array( 'label' => 'Đã qua ngày kết thúc — Tự động tắt', 'cls' => 'ds-seasonal-pill--off' ),
		);
		$curr_badge = $status_badges[ $state['mode'] ] ?? $status_badges['off'];
		?>
		<div class="wrap <?php echo $has_cockpit ? 'ds-cockpit-wrap ' : ''; ?>ds-seasonal-admin">
			<?php if ( $has_cockpit ) : ?>
				<?php
				despace_render_admin_cockpit_header(
					self::PAGE_SLUG,
					__( 'Giao diện Mùa lễ (Noel)', 'despace-core' ),
					__( 'Bật/tắt chế độ trang trí Giáng sinh Editorial Luxury hoàn toàn tách biệt (Zero-Footprint khi tắt), hỗ trợ xem trước cho Admin và hẹn lịch tự động.', 'despace-core' ),
					array(
						array(
							'label'  => 'Xem trước nhanh ↗',
							'url'    => home_url( '/' ),
							'style'  => 'primary',
							'target' => '_blank',
						),
						array(
							'label'  => 'Xem bản EN ↗',
							'url'    => home_url( '/en/' ),
							'style'  => 'ghost',
							'target' => '_blank',
						),
					)
				);
				?>
			<?php else : ?>
				<h1><?php esc_html_e( 'DeSpace — Giao diện Mùa lễ (Noel)', 'despace-core' ); ?></h1>
			<?php endif; ?>

			<?php if ( $saved ) : ?>
				<div class="notice notice-success is-dismissible ds-seasonal-notice">
					<p><strong>✦ <?php esc_html_e( 'Đã lưu cấu hình Giao diện Mùa lễ thành công.', 'despace-core' ); ?></strong>
						<a href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener noreferrer" style="margin-left:8px;font-weight:600;color:#9A6745;">
							<?php esc_html_e( 'Mở trang chủ kiểm tra ngay ↗', 'despace-core' ); ?>
						</a>
					</p>
				</div>
			<?php endif; ?>

			<!-- Live Status Strip -->
			<div class="ds-seasonal-status-bar">
				<div class="ds-seasonal-status-bar__left">
					<span class="ds-seasonal-pill <?php echo esc_attr( $curr_badge['cls'] ); ?>">
						<span class="ds-seasonal-pill__dot" aria-hidden="true"></span>
						<?php echo esc_html( $curr_badge['label'] ); ?>
					</span>
					<span class="ds-seasonal-meta">
						<?php
						printf(
							/* translators: 1: WP local date, 2: WP timezone */
							esc_html__( 'Ngày máy chủ (%2$s): %1$s', 'despace-core' ),
							'<strong>' . esc_html( $today_wp ) . '</strong>',
							esc_html( $tz_label )
						);
						?>
					</span>
				</div>
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener noreferrer" class="ds-btn ds-btn--outline ds-seasonal-preview-btn">
					❄ <?php esc_html_e( 'Xem trước nhanh trên Storefront ↗', 'despace-core' ); ?>
				</a>
			</div>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ds-seasonal-form">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::NONCE_ACTION ); ?>" />
				<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD ); ?>

				<div class="ds-seasonal-grid">
					<!-- Panel 1: Trạng thái hoạt động -->
					<section class="ds-panel ds-seasonal-panel ds-seasonal-panel--full">
						<div class="ds-panel__head">
							<div>
								<h2><?php esc_html_e( '1. Trạng thái hoạt động (Feature Flag)', 'despace-core' ); ?></h2>
								<p><?php esc_html_e( 'Khi chọn Tắt hoàn toàn, hệ thống không nạp bất kỳ tệp CSS/JS hay DOM trang trí nào (0 KB tài nguyên thừa).', 'despace-core' ); ?></p>
							</div>
						</div>
						<div class="ds-seasonal-modes">
							<label class="ds-mode-card <?php echo 'off' === $settings['status'] ? 'is-selected' : ''; ?>">
								<input type="radio" name="status" value="off" <?php checked( $settings['status'], 'off' ); ?> />
								<span class="ds-mode-card__title"><?php esc_html_e( 'Tắt hoàn toàn', 'despace-core' ); ?></span>
								<span class="ds-mode-card__code">off</span>
								<span class="ds-mode-card__desc"><?php esc_html_e( 'Quay về giao diện Editorial Luxury gốc 100%, không load file Noel.', 'despace-core' ); ?></span>
							</label>
							<label class="ds-mode-card <?php echo 'preview' === $settings['status'] ? 'is-selected' : ''; ?>">
								<input type="radio" name="status" value="preview" <?php checked( $settings['status'], 'preview' ); ?> />
								<span class="ds-mode-card__title"><?php esc_html_e( 'Chế độ Xem trước (Chỉ Admin)', 'despace-core' ); ?></span>
								<span class="ds-mode-card__code">preview</span>
								<span class="ds-mode-card__desc"><?php esc_html_e( 'Chỉ tài khoản Quản trị viên thấy giao diện Noel để kiểm duyệt; khách ngoài vẫn thấy bản gốc.', 'despace-core' ); ?></span>
							</label>
							<label class="ds-mode-card <?php echo 'active' === $settings['status'] ? 'is-selected' : ''; ?>">
								<input type="radio" name="status" value="active" <?php checked( $settings['status'], 'active' ); ?> />
								<span class="ds-mode-card__title"><?php esc_html_e( 'Bật công khai toàn trang', 'despace-core' ); ?></span>
								<span class="ds-mode-card__code">active</span>
								<span class="ds-mode-card__desc"><?php esc_html_e( 'Hiển thị không khí Giáng sinh tới toàn bộ khách truy cập (tuân thủ hẹn lịch nếu có).', 'despace-core' ); ?></span>
							</label>
						</div>
					</section>

					<!-- Panel 2: Chọn phong cách Preset -->
					<section class="ds-panel ds-seasonal-panel ds-seasonal-panel--full">
						<div class="ds-panel__head">
							<div>
								<h2><?php esc_html_e( '2. Phong cách Noel Luxury (Preset)', 'despace-core' ); ?></h2>
								<p><?php esc_html_e( 'Mọi preset đều giữ nền khung ảnh sản phẩm & không gian 3D/AR sạch sáng để tôn trung thực vân gỗ tự nhiên.', 'despace-core' ); ?></p>
							</div>
						</div>
						<div class="ds-seasonal-presets">
							<?php foreach ( $presets as $slug => $meta ) : ?>
								<label class="ds-preset-card <?php echo $slug === $settings['preset'] ? 'is-selected' : ''; ?>">
									<input type="radio" name="preset" value="<?php echo esc_attr( $slug ); ?>" <?php checked( $settings['preset'], $slug ); ?> />
									<div class="ds-preset-card__top">
										<strong><?php echo esc_html( $meta['name'] ); ?></strong>
										<span class="ds-preset-card__badge"><?php echo esc_html( $meta['badge'] ); ?></span>
									</div>
									<div class="ds-preset-card__swatches" aria-hidden="true">
										<?php foreach ( $meta['swatches'] as $hex ) : ?>
											<span class="ds-swatch-chip" style="background: <?php echo esc_attr( $hex ); ?>;"></span>
										<?php endforeach; ?>
									</div>
									<p class="ds-preset-card__desc"><?php echo esc_html( $meta['desc'] ); ?></p>
								</label>
							<?php endforeach; ?>
						</div>
					</section>

					<!-- Panel 3: Hiệu ứng & Tiểu tiết -->
					<section class="ds-panel ds-seasonal-panel">
						<div class="ds-panel__head">
							<div>
								<h2><?php esc_html_e( '3. Hiệu ứng Tuyết rơi & Tiểu tiết', 'despace-core' ); ?></h2>
								<p><?php esc_html_e( 'Canvas siêu nhẹ (pointer-events: none), tự dừng khi chuyển tab hoặc mở 3D/AR và có nút để khách tự tắt.', 'despace-core' ); ?></p>
							</div>
						</div>
						<div class="ds-seasonal-fields">
							<label class="ds-toggle-row">
								<input type="checkbox" name="snow_enabled" value="1" <?php checked( ! empty( $settings['snow_enabled'] ) ); ?> />
								<div>
									<strong><?php esc_html_e( 'Bật hiệu ứng tuyết rơi / hạt ánh sáng Champagne', 'despace-core' ); ?></strong>
									<span><?php esc_html_e( 'Tôn trọng cài đặt giảm chuyển động (prefers-reduced-motion) của hệ điều hành.', 'despace-core' ); ?></span>
								</div>
							</label>

							<div class="ds-field-group">
								<label for="ds-snow-density"><?php esc_html_e( 'Mật độ hạt tuyết / bokeh:', 'despace-core' ); ?></label>
								<select id="ds-snow-density" name="snow_density">
									<option value="light" <?php selected( $settings['snow_density'], 'light' ); ?>><?php esc_html_e( 'Nhẹ — Thoáng & tối giản (Khuyên dùng)', 'despace-core' ); ?></option>
									<option value="medium" <?php selected( $settings['snow_density'], 'medium' ); ?>><?php esc_html_e( 'Vừa — Đậm không khí đêm Giáng sinh', 'despace-core' ); ?></option>
								</select>
							</div>

							<label class="ds-toggle-row">
								<input type="checkbox" name="header_accent" value="1" <?php checked( ! empty( $settings['header_accent'] ) ); ?> />
								<div>
									<strong><?php esc_html_e( 'Bật tiểu tiết Noel nét mảnh trên Logo & Header', 'despace-core' ); ?></strong>
									<span><?php esc_html_e( 'Thêm biểu tượng cành thông & ngôi sao 4 cánh SVG mảnh tinh tế, không clipart rườm rà.', 'despace-core' ); ?></span>
								</div>
							</label>
						</div>
					</section>

					<!-- Panel 4: Hẹn lịch tự động -->
					<section class="ds-panel ds-seasonal-panel">
						<div class="ds-panel__head">
							<div>
								<h2><?php esc_html_e( '4. Hẹn lịch tự động (Auto-Schedule)', 'despace-core' ); ?></h2>
								<p><?php esc_html_e( 'Tự động bật và tắt chế độ công khai đúng ngày theo múi giờ WordPress, không cần thao tác thủ công sau lễ.', 'despace-core' ); ?></p>
							</div>
						</div>
						<div class="ds-seasonal-fields">
							<label class="ds-toggle-row">
								<input type="checkbox" name="schedule_enabled" value="1" <?php checked( ! empty( $settings['schedule_enabled'] ) ); ?> />
								<div>
									<strong><?php esc_html_e( 'Kích hoạt khung ngày tự động cho chế độ Công khai', 'despace-core' ); ?></strong>
									<span><?php esc_html_e( 'Hết ngày kết thúc, storefront tự động trở lại giao diện mặc định 0 KB Noel.', 'despace-core' ); ?></span>
								</div>
							</label>

							<div class="ds-date-pair">
								<div class="ds-field-group">
									<label for="ds-start-date"><?php esc_html_e( 'Ngày bắt đầu:', 'despace-core' ); ?></label>
									<input type="date" id="ds-start-date" name="start_date" value="<?php echo esc_attr( $settings['start_date'] ); ?>" />
								</div>
								<div class="ds-field-group">
									<label for="ds-end-date"><?php esc_html_e( 'Ngày kết thúc:', 'despace-core' ); ?></label>
									<input type="date" id="ds-end-date" name="end_date" value="<?php echo esc_attr( $settings['end_date'] ); ?>" />
								</div>
							</div>
						</div>
					</section>

					<!-- Panel 5: Nội dung Marquee song ngữ & Mã ưu đãi Noel -->
					<section class="ds-panel ds-seasonal-panel ds-seasonal-panel--full">
						<div class="ds-panel__head">
							<div>
								<h2><?php esc_html_e( '5. Nội dung Announcement Marquee (VI / EN) & Mã ưu đãi Noel', 'despace-core' ); ?></h2>
								<p><?php esc_html_e( 'Chỉ hiển thị khi chế độ Noel đang hoạt động; khi tắt tự động trả về Marquee và mã chào mừng gốc.', 'despace-core' ); ?></p>
							</div>
						</div>
						<div class="ds-seasonal-fields ds-seasonal-fields--two-col">
							<div class="ds-field-group">
								<label for="ds-marquee-vi"><?php esc_html_e( 'Announcement Marquee Noel (Tiếng Việt):', 'despace-core' ); ?></label>
								<input type="text" id="ds-marquee-vi" name="marquee_vi" maxlength="240" value="<?php echo esc_attr( $settings['marquee_vi'] ); ?>" placeholder="Mùa Giáng Sinh An Lành — Ưu đãi lễ hội lên đến 20%..." />
							</div>
							<div class="ds-field-group">
								<label for="ds-marquee-en"><?php esc_html_e( 'Announcement Marquee Noel (English):', 'despace-core' ); ?></label>
								<input type="text" id="ds-marquee-en" name="marquee_en" maxlength="240" value="<?php echo esc_attr( $settings['marquee_en'] ); ?>" placeholder="Season of Warmth — Holiday savings up to 20%..." />
							</div>
							<div class="ds-field-group">
								<label for="ds-promo-code"><?php esc_html_e( 'Mã ưu đãi Noel tùy chọn (ví dụ: NOEL2026):', 'despace-core' ); ?></label>
								<input type="text" id="ds-promo-code" name="promo_code" maxlength="32" value="<?php echo esc_attr( $settings['promo_code'] ); ?>" placeholder="NOEL2026" style="text-transform:uppercase;font-weight:700;letter-spacing:0.06em;" />
							</div>
							<div class="ds-field-group">
								<label for="ds-promo-vi"><?php esc_html_e( 'Thông điệp Banner ưu đãi (Tiếng Việt):', 'despace-core' ); ?></label>
								<input type="text" id="ds-promo-vi" name="promo_banner_vi" maxlength="240" value="<?php echo esc_attr( $settings['promo_banner_vi'] ); ?>" />
							</div>
							<div class="ds-field-group ds-field-group--span2">
								<label for="ds-promo-en"><?php esc_html_e( 'Thông điệp Banner ưu đãi (English):', 'despace-core' ); ?></label>
								<input type="text" id="ds-promo-en" name="promo_banner_en" maxlength="240" value="<?php echo esc_attr( $settings['promo_banner_en'] ); ?>" />
							</div>
						</div>
					</section>
				</div>

				<div class="ds-seasonal-submit-bar">
					<button type="submit" class="ds-btn ds-btn--primary ds-seasonal-save-btn">
						✦ <?php esc_html_e( 'Lưu cài đặt Giao diện Mùa lễ', 'despace-core' ); ?>
					</button>
					<a href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener noreferrer" class="ds-btn ds-btn--outline">
						<?php esc_html_e( 'Xem trước nhanh ↗', 'despace-core' ); ?>
					</a>
				</div>
			</form>
		</div>

		<style>
			.ds-seasonal-admin .ds-seasonal-status-bar {
				display: flex;
				align-items: center;
				justify-content: space-between;
				flex-wrap: wrap;
				gap: 14px;
				background: #FFFFFF;
				border: 1px solid #E7E0D7;
				border-radius: 16px;
				padding: 14px 20px;
				margin-bottom: 20px;
				box-shadow: 0 2px 10px rgba(28, 25, 23, 0.04);
			}
			.ds-seasonal-admin .ds-seasonal-status-bar__left {
				display: flex;
				align-items: center;
				flex-wrap: wrap;
				gap: 12px;
			}
			.ds-seasonal-admin .ds-seasonal-pill {
				display: inline-flex;
				align-items: center;
				gap: 7px;
				padding: 5px 13px;
				border-radius: 999px;
				font-size: 12.5px;
				font-weight: 700;
			}
			.ds-seasonal-admin .ds-seasonal-pill__dot {
				width: 8px;
				height: 8px;
				border-radius: 50%;
				background: currentColor;
			}
			.ds-seasonal-admin .ds-seasonal-pill--off { background: #F2ECE3; color: #69645D; }
			.ds-seasonal-admin .ds-seasonal-pill--preview { background: #FEF5E7; color: #B45309; }
			.ds-seasonal-admin .ds-seasonal-pill--live { background: #E9F3EC; color: #1B5E38; }
			.ds-seasonal-admin .ds-seasonal-meta { font-size: 13px; color: #69645D; }
			.ds-seasonal-admin .ds-seasonal-grid {
				display: grid;
				grid-template-columns: repeat(2, minmax(0, 1fr));
				gap: 20px;
			}
			.ds-seasonal-admin .ds-seasonal-panel--full { grid-column: 1 / -1; }
			.ds-seasonal-admin .ds-seasonal-modes,
			.ds-seasonal-admin .ds-seasonal-presets {
				display: grid;
				grid-template-columns: repeat(3, minmax(0, 1fr));
				gap: 14px;
			}
			.ds-seasonal-admin .ds-mode-card,
			.ds-seasonal-admin .ds-preset-card {
				position: relative;
				display: flex;
				flex-direction: column;
				gap: 6px;
				padding: 16px 18px;
				border: 1.5px solid #E7E0D7;
				border-radius: 14px;
				background: #FAF7F2;
				cursor: pointer;
				transition: border-color 0.16s ease, background-color 0.16s ease, box-shadow 0.16s ease;
			}
			.ds-seasonal-admin .ds-mode-card:hover,
			.ds-seasonal-admin .ds-preset-card:hover {
				border-color: #C9A96E;
				background: #FFFFFF;
			}
			.ds-seasonal-admin .ds-mode-card.is-selected,
			.ds-seasonal-admin .ds-preset-card.is-selected {
				border-color: #9A6745;
				background: #FFFFFF;
				box-shadow: 0 0 0 3px rgba(154, 103, 69, 0.14);
			}
			.ds-seasonal-admin .ds-mode-card input[type="radio"],
			.ds-seasonal-admin .ds-preset-card input[type="radio"] {
				position: absolute;
				top: 14px;
				right: 14px;
				accent-color: #9A6745;
			}
			.ds-seasonal-admin .ds-mode-card__title { font-size: 14.5px; font-weight: 700; color: #1C1917; padding-right: 22px; }
			.ds-seasonal-admin .ds-mode-card__code { font-family: monospace; font-size: 11px; color: #9A6745; background: #F5ECE2; padding: 2px 7px; border-radius: 6px; align-self: flex-start; }
			.ds-seasonal-admin .ds-mode-card__desc,
			.ds-seasonal-admin .ds-preset-card__desc { font-size: 12.5px; color: #69645D; line-height: 1.5; margin: 2px 0 0; }
			.ds-seasonal-admin .ds-preset-card__top { display: flex; flex-direction: column; gap: 3px; padding-right: 24px; }
			.ds-seasonal-admin .ds-preset-card__top strong { font-size: 15px; color: #1C1917; }
			.ds-seasonal-admin .ds-preset-card__badge { font-size: 11px; font-weight: 600; color: #9A6745; }
			.ds-seasonal-admin .ds-preset-card__swatches { display: flex; gap: 6px; margin: 6px 0 4px; }
			.ds-seasonal-admin .ds-swatch-chip { width: 28px; height: 18px; border-radius: 6px; border: 1px solid rgba(28, 25, 23, 0.16); }
			.ds-seasonal-admin .ds-seasonal-fields { display: flex; flex-direction: column; gap: 14px; }
			.ds-seasonal-admin .ds-seasonal-fields--two-col { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
			.ds-seasonal-admin .ds-field-group--span2 { grid-column: 1 / -1; }
			.ds-seasonal-admin .ds-toggle-row {
				display: flex;
				align-items: flex-start;
				gap: 10px;
				padding: 12px 14px;
				border-radius: 12px;
				background: #FAF7F2;
				border: 1px solid #E7E0D7;
				cursor: pointer;
			}
			.ds-seasonal-admin .ds-toggle-row input[type="checkbox"] { margin-top: 3px; accent-color: #9A6745; }
			.ds-seasonal-admin .ds-toggle-row strong { display: block; font-size: 13.5px; color: #1C1917; }
			.ds-seasonal-admin .ds-toggle-row span { display: block; font-size: 12px; color: #69645D; margin-top: 2px; }
			.ds-seasonal-admin .ds-field-group { display: flex; flex-direction: column; gap: 6px; }
			.ds-seasonal-admin .ds-field-group label { font-size: 13px; font-weight: 600; color: #1C1917; }
			.ds-seasonal-admin .ds-field-group input,
			.ds-seasonal-admin .ds-field-group select { width: 100%; max-width: 100%; min-height: 38px; }
			.ds-seasonal-admin .ds-date-pair { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
			.ds-seasonal-admin .ds-seasonal-submit-bar {
				display: flex;
				align-items: center;
				gap: 12px;
				margin-top: 22px;
				padding-top: 18px;
				border-top: 1px solid #E7E0D7;
			}
			@media (max-width: 960px) {
				.ds-seasonal-admin .ds-seasonal-grid,
				.ds-seasonal-admin .ds-seasonal-modes,
				.ds-seasonal-admin .ds-seasonal-presets,
				.ds-seasonal-admin .ds-seasonal-fields--two-col {
					grid-template-columns: 1fr;
				}
			}
		</style>
		<script>
		(function () {
			document.querySelectorAll('.ds-seasonal-admin .ds-mode-card input[type="radio"], .ds-seasonal-admin .ds-preset-card input[type="radio"]').forEach(function (radio) {
				radio.addEventListener('change', function () {
					var name = this.name;
					document.querySelectorAll('.ds-seasonal-admin input[name="' + name + '"]').forEach(function (r) {
						var card = r.closest('.ds-mode-card, .ds-preset-card');
						if (card) card.classList.toggle('is-selected', r.checked);
					});
				});
			});
		})();
		</script>
		<?php
	}
}
