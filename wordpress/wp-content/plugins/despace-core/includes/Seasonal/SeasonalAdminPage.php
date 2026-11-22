<?php
/**
 * DeSpace Seasonal Mode (Noel) Admin Studio Page (v2.0 — Winter Atelier).
 *
 * Accessible strictly to Administrators (manage_options) under
 * DeSpace -> Giao diện Mùa lễ, featuring a real-time interactive mini-preview,
 * 1-click quick scenarios, 4 Luxury presets, atmospheric controls, bilingual
 * Marquee & Gift Box editor, and WordPress-timezone Auto-Schedule.
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
			'campaign_id'       => isset( $_POST['campaign_id'] ) ? wp_unslash( $_POST['campaign_id'] ) : SeasonalSettings::DEFAULT_CAMPAIGN_ID,
			'timezone'          => isset( $_POST['timezone'] ) ? wp_unslash( $_POST['timezone'] ) : SeasonalSettings::DEFAULT_TIMEZONE,
			'status'            => isset( $_POST['status'] ) ? wp_unslash( $_POST['status'] ) : 'off',
			'preset'            => isset( $_POST['preset'] ) ? wp_unslash( $_POST['preset'] ) : 'nordic',
			'snow_enabled'      => ! empty( $_POST['snow_enabled'] ),
			'snow_density'      => isset( $_POST['snow_density'] ) ? wp_unslash( $_POST['snow_density'] ) : 'light',
			'snow_style'        => isset( $_POST['snow_style'] ) ? wp_unslash( $_POST['snow_style'] ) : 'snow_bokeh',
			'header_accent'     => ! empty( $_POST['header_accent'] ),
			'countdown_enabled' => ! empty( $_POST['countdown_enabled'] ),
			'gift_box_enabled'  => ! empty( $_POST['gift_box_enabled'] ),
			'audio_enabled'     => ! empty( $_POST['audio_enabled'] ),
			'marquee_vi'        => isset( $_POST['marquee_vi'] ) ? wp_unslash( $_POST['marquee_vi'] ) : '',
			'marquee_en'        => isset( $_POST['marquee_en'] ) ? wp_unslash( $_POST['marquee_en'] ) : '',
			'promo_code'        => isset( $_POST['promo_code'] ) ? wp_unslash( $_POST['promo_code'] ) : '',
			'promo_banner_vi'   => isset( $_POST['promo_banner_vi'] ) ? wp_unslash( $_POST['promo_banner_vi'] ) : '',
			'promo_banner_en'   => isset( $_POST['promo_banner_en'] ) ? wp_unslash( $_POST['promo_banner_en'] ) : '',
			'schedule_enabled'  => ! empty( $_POST['schedule_enabled'] ),
			'start_date'        => isset( $_POST['start_date'] ) ? wp_unslash( $_POST['start_date'] ) : '',
			'end_date'          => isset( $_POST['end_date'] ) ? wp_unslash( $_POST['end_date'] ) : '',
		);

		SeasonalSettings::save( $raw );

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
	 * Render the Interactive Winter Atelier Admin Studio.
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
		$tz_label    = (string) ( $settings['timezone'] ?? SeasonalSettings::DEFAULT_TIMEZONE );
		$campaign_id = (string) ( $settings['campaign_id'] ?? SeasonalSettings::DEFAULT_CAMPAIGN_ID );
		$today_wp    = SeasonalSettings::today_wp_date( $tz_label );

		$status_badges = array(
			'off'               => array( 'label' => 'Đang tắt — Giao diện gốc (0 KB Noel)', 'cls' => 'ds-seasonal-pill--off' ),
			'preview'           => array( 'label' => 'Xem trước — Chỉ Admin nhìn thấy', 'cls' => 'ds-seasonal-pill--preview' ),
			'active'            => array( 'label' => 'Đang bật công khai (Live)', 'cls' => 'ds-seasonal-pill--live' ),
			'scheduled_pending' => array( 'label' => 'Đã lên lịch — Chưa đến ngày bắt đầu', 'cls' => 'ds-seasonal-pill--preview' ),
			'scheduled_expired' => array( 'label' => 'Đã qua ngày kết thúc — Tự động tắt', 'cls' => 'ds-seasonal-pill--off' ),
		);
		$curr_badge   = $status_badges[ $state['mode'] ] ?? $status_badges['off'];
		$active_prev  = $presets[ $settings['preset'] ]['preview'] ?? $presets['nordic']['preview'];
		$presets_json = wp_json_encode( $presets );
		?>
		<div class="wrap <?php echo $has_cockpit ? 'ds-cockpit-wrap ' : ''; ?>ds-seasonal-admin">
			<?php if ( $has_cockpit ) : ?>
				<?php
				despace_render_admin_cockpit_header(
					self::PAGE_SLUG,
					__( 'Winter Atelier — Giao diện Mùa lễ (Noel)', 'despace-core' ),
					__( 'Điều khiển toàn diện không gian Giáng sinh Luxury: 4 preset nghệ thuật, tuyết pha lê & bokeh 3 tầng, dải đèn thông Header, đếm ngược, hộp quà mở niêm phong & hẹn lịch tự động — hoàn toàn 0 KB khi tắt.', 'despace-core' ),
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
				<h1><?php esc_html_e( 'DeSpace — Winter Atelier (Giao diện Mùa lễ)', 'despace-core' ); ?></h1>
			<?php endif; ?>

			<?php if ( $saved ) : ?>
				<div class="notice notice-success is-dismissible ds-seasonal-notice">
					<p><strong>✦ <?php esc_html_e( 'Đã lưu cấu hình Winter Atelier thành công.', 'despace-core' ); ?></strong>
						<a href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener noreferrer" style="margin-left:8px;font-weight:600;color:#9A6745;">
							<?php esc_html_e( 'Mở trang chủ kiểm tra ngay ↗', 'despace-core' ); ?>
						</a>
					</p>
				</div>
			<?php endif; ?>

			<!-- Live Status & Quick Scenarios Strip -->
			<div class="ds-seasonal-status-bar">
				<div class="ds-seasonal-status-bar__left">
					<span class="ds-seasonal-pill <?php echo esc_attr( $curr_badge['cls'] ); ?>">
						<span class="ds-seasonal-pill__dot" aria-hidden="true"></span>
						<?php echo esc_html( $curr_badge['label'] ); ?>
					</span>
					<span class="ds-seasonal-meta">
						<?php
						printf(
							/* translators: 1: campaign ID, 2: WP local date, 3: timezone */
							esc_html__( 'Chiến dịch: %1$s · Ngày (%3$s): %2$s', 'despace-core' ),
							'<code>' . esc_html( $campaign_id ) . '</code>',
							'<strong>' . esc_html( $today_wp ) . '</strong>',
							esc_html( $tz_label )
						);
						?>
					</span>
				</div>
				<div class="ds-seasonal-quick-scenarios" role="group" aria-label="<?php esc_attr_e( 'Kịch bản bật nhanh', 'despace-core' ); ?>">
					<button type="button" class="ds-quick-chip" data-scenario="nordic_live">✦ Bật Noel Nordic</button>
					<button type="button" class="ds-quick-chip" data-scenario="midnight_preview">❄ Xem trước Midnight</button>
					<button type="button" class="ds-quick-chip" data-scenario="frost_live">✧ Alpine Frost</button>
					<button type="button" class="ds-quick-chip ds-quick-chip--muted" data-scenario="turn_off">Tắt hoàn toàn</button>
				</div>
			</div>

			<!-- Live Real-Time Interactive Storefront Mini-Preview -->
			<section class="ds-panel ds-seasonal-preview-panel" aria-label="<?php esc_attr_e( 'Xem trước trực tiếp giao diện mùa lễ', 'despace-core' ); ?>">
				<div class="ds-seasonal-preview-head">
					<div>
						<span class="ds-seasonal-kicker">LIVE ATELIER SIMULATOR</span>
						<h2><?php esc_html_e( 'Mô phỏng trực tiếp Storefront (Cập nhật tức thì khi chỉnh)', 'despace-core' ); ?></h2>
					</div>
					<div class="ds-preview-lang-switch" role="group" aria-label="Ngôn ngữ mô phỏng">
						<button type="button" class="ds-prev-lang-btn is-active" data-prev-lang="vi">VI</button>
						<button type="button" class="ds-prev-lang-btn" data-prev-lang="en">EN</button>
					</div>
				</div>
				<div class="ds-mini-browser" id="dsSeasonalMiniPreview" style="background: <?php echo esc_attr( $active_prev['pageBg'] ); ?>;">
					<div class="ds-mini-marquee" id="dsPrevMarquee" style="background: <?php echo esc_attr( $active_prev['marqueeBg'] ); ?>; color: <?php echo esc_attr( $active_prev['marqueeFg'] ); ?>;">
						<span class="ds-mini-star" style="color: <?php echo esc_attr( $active_prev['accent'] ); ?>;">❄</span>
						<span id="dsPrevMarqueeText"><?php echo esc_html( $settings['marquee_vi'] ); ?></span>
						<span class="ds-mini-star" style="color: <?php echo esc_attr( $active_prev['accent'] ); ?>;">❄</span>
					</div>
					<div class="ds-mini-header" id="dsPrevHeader" style="background: <?php echo esc_attr( $active_prev['headerBg'] ); ?>; color: <?php echo esc_attr( $active_prev['headerFg'] ); ?>;">
						<span class="ds-mini-brand">DeSpace <span id="dsPrevAccentStar" style="color: <?php echo esc_attr( $active_prev['accent'] ); ?>; <?php echo empty( $settings['header_accent'] ) ? 'display:none;' : ''; ?>">✦</span></span>
						<span class="ds-mini-nav">
							<span>Trang chủ</span>
							<span>Cửa hàng</span>
							<span>3D · AR</span>
						</span>
						<span class="ds-mini-badge" id="dsPrevOfferBadge" style="background: <?php echo esc_attr( $active_prev['marqueeBg'] ); ?>; color: <?php echo esc_attr( $active_prev['marqueeFg'] ); ?>;">✦ Ưu đãi Noel</span>
					</div>
					<div class="ds-mini-garland" id="dsPrevGarland" style="<?php echo empty( $settings['header_accent'] ) ? 'display:none;' : ''; ?>">
						<span class="ds-mini-bulb"></span><span class="ds-mini-bulb"></span><span class="ds-mini-bulb"></span><span class="ds-mini-bulb"></span><span class="ds-mini-bulb"></span><span class="ds-mini-bulb"></span><span class="ds-mini-bulb"></span>
					</div>
					<div class="ds-mini-stage">
						<div class="ds-mini-hero" id="dsPrevHero" style="background: <?php echo esc_attr( $active_prev['heroBg'] ); ?>;">
							<div class="ds-mini-hero-copy">
								<span class="ds-mini-eyebrow" id="dsPrevEyebrow" style="color: <?php echo esc_attr( $active_prev['accent'] ); ?>;">✦ WINTER ATELIER EDITION</span>
								<strong id="dsPrevHeroTitle">Tổ ấm nồng ấm cho mùa Giáng sinh an lành.</strong>
								<div class="ds-mini-countdown" id="dsPrevCountdown" style="<?php echo empty( $settings['countdown_enabled'] ) ? 'display:none;' : ''; ?>">
									<span><strong>32</strong><small>Ngày</small></span>
									<span><strong>14</strong><small>Giờ</small></span>
									<span><strong>28</strong><small>Phút</small></span>
								</div>
							</div>
							<div class="ds-mini-gift" id="dsPrevGift" style="border-color: <?php echo esc_attr( $active_prev['accent'] ); ?>; <?php echo empty( $settings['gift_box_enabled'] ) ? 'display:none;' : ''; ?>">
								<span class="ds-mini-gift-seal" style="background: <?php echo esc_attr( $active_prev['accent'] ); ?>;">❄</span>
								<small>Phong bao ưu đãi</small>
								<code id="dsPrevPromoCode"><?php echo esc_html( $settings['promo_code'] ?: 'DESPACE10' ); ?></code>
							</div>
						</div>
						<div class="ds-mini-cards">
							<div class="ds-mini-card">
								<div class="ds-mini-card-img">
									<span class="ds-mini-card-pill" id="dsPrevCardPill" style="background: <?php echo esc_attr( $active_prev['marqueeBg'] ); ?>; color: <?php echo esc_attr( $active_prev['marqueeFg'] ); ?>;">❄ Tuyển chọn Noel</span>
									<span class="ds-mini-card-3d" id="dsPrevCard3d" style="border-color: <?php echo esc_attr( $active_prev['accent'] ); ?>;">3D · AR</span>
								</div>
								<div class="ds-mini-card-lines"><i></i><b></b></div>
							</div>
							<div class="ds-mini-card">
								<div class="ds-mini-card-img">
									<span class="ds-mini-card-pill" id="dsPrevCardPill2" style="background: <?php echo esc_attr( $active_prev['marqueeBg'] ); ?>; color: <?php echo esc_attr( $active_prev['marqueeFg'] ); ?>;">Noel Sale -15%</span>
									<span class="ds-mini-card-3d" id="dsPrevCard3d2" style="border-color: <?php echo esc_attr( $active_prev['accent'] ); ?>;">3D · AR</span>
								</div>
								<div class="ds-mini-card-lines"><i></i><b></b></div>
							</div>
						</div>
					</div>
					<div class="ds-mini-dock">
						<span id="dsPrevSnowPill" style="<?php echo empty( $settings['snow_enabled'] ) ? 'opacity:.35;' : ''; ?>">❄ <em id="dsPrevSnowMeta"><?php echo esc_html( $settings['snow_style'] ); ?></em></span>
						<span id="dsPrevAudioPill" style="<?php echo empty( $settings['audio_enabled'] ) ? 'display:none;' : ''; ?>">♪ Nhạc lễ (tắt mặc định)</span>
					</div>
				</div>
			</section>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ds-seasonal-form" id="dsSeasonalForm">
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
								<span class="ds-mode-card__desc"><?php esc_html_e( 'Chỉ tài khoản Quản trị viên thấy giao diện Noel để duyệt trước; khách ngoài vẫn thấy bản gốc.', 'despace-core' ); ?></span>
							</label>
							<label class="ds-mode-card <?php echo 'active' === $settings['status'] ? 'is-selected' : ''; ?>">
								<input type="radio" name="status" value="active" <?php checked( $settings['status'], 'active' ); ?> />
								<span class="ds-mode-card__title"><?php esc_html_e( 'Bật công khai toàn trang', 'despace-core' ); ?></span>
								<span class="ds-mode-card__code">active</span>
								<span class="ds-mode-card__desc"><?php esc_html_e( 'Hiển thị trải nghiệm Winter Atelier tới toàn bộ khách truy cập (tuân thủ hẹn lịch nếu có).', 'despace-core' ); ?></span>
							</label>
						</div>
					</section>

					<!-- Panel 2: Chọn phong cách Preset (4 Presets) -->
					<section class="ds-panel ds-seasonal-panel ds-seasonal-panel--full">
						<div class="ds-panel__head">
							<div>
								<h2><?php esc_html_e( '2. Bảng màu Nghệ thuật Mùa lễ (4 Luxury Presets)', 'despace-core' ); ?></h2>
								<p><?php esc_html_e( 'Mọi preset đều giữ khung ảnh nội thất & studio 3D/AR sáng trung thực để màu vân gỗ không bao giờ bị ám sắc.', 'despace-core' ); ?></p>
							</div>
						</div>
						<div class="ds-seasonal-presets ds-seasonal-presets--four">
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

					<!-- Panel 3: Không khí, Tuyết 3 tầng & Trải nghiệm tương tác -->
					<section class="ds-panel ds-seasonal-panel">
						<div class="ds-panel__head">
							<div>
								<h2><?php esc_html_e( '3. Không khí Tuyết 3 tầng & Tương tác Lễ hội', 'despace-core' ); ?></h2>
								<p><?php esc_html_e( 'Canvas siêu nhẹ tự dừng khi mở 3D/AR hoặc chuyển tab; mọi trải nghiệm đều tôn trọng prefers-reduced-motion.', 'despace-core' ); ?></p>
							</div>
						</div>
						<div class="ds-seasonal-fields">
							<label class="ds-toggle-row">
								<input type="checkbox" name="snow_enabled" id="ds-snow-enabled" value="1" <?php checked( ! empty( $settings['snow_enabled'] ) ); ?> />
								<div>
									<strong><?php esc_html_e( 'Bật hiệu ứng tuyết tinh thể / ánh sáng Champagne 3 tầng', 'despace-core' ); ?></strong>
									<span><?php esc_html_e( 'Tự động phản ứng nhẹ theo làn gió con trỏ chuột và kèm nút tắt nhanh ở góc trái.', 'despace-core' ); ?></span>
								</div>
							</label>

							<div class="ds-date-pair">
								<div class="ds-field-group">
									<label for="ds-snow-style"><?php esc_html_e( 'Phong cách hạt không khí:', 'despace-core' ); ?></label>
									<select id="ds-snow-style" name="snow_style">
										<option value="snow_bokeh" <?php selected( $settings['snow_style'], 'snow_bokeh' ); ?>><?php esc_html_e( 'Tuyết tinh thể + Bokeh Champagne', 'despace-core' ); ?></option>
										<option value="crystals" <?php selected( $settings['snow_style'], 'crystals' ); ?>><?php esc_html_e( 'Tuyết pha lê Bắc Âu tinh khiết', 'despace-core' ); ?></option>
										<option value="champagne" <?php selected( $settings['snow_style'], 'champagne' ); ?>><?php esc_html_e( 'Hạt sáng Champagne ấm (không tuyết)', 'despace-core' ); ?></option>
									</select>
								</div>
								<div class="ds-field-group">
									<label for="ds-snow-density"><?php esc_html_e( 'Mật độ hạt:', 'despace-core' ); ?></label>
									<select id="ds-snow-density" name="snow_density">
										<option value="light" <?php selected( $settings['snow_density'], 'light' ); ?>><?php esc_html_e( 'Nhẹ — Thoáng & sang (Khuyên dùng)', 'despace-core' ); ?></option>
										<option value="medium" <?php selected( $settings['snow_density'], 'medium' ); ?>><?php esc_html_e( 'Vừa — Đậm chất đêm lễ hội', 'despace-core' ); ?></option>
									</select>
								</div>
							</div>

							<label class="ds-toggle-row">
								<input type="checkbox" name="header_accent" id="ds-header-accent" value="1" <?php checked( ! empty( $settings['header_accent'] ) ); ?> />
								<div>
									<strong><?php esc_html_e( 'Dải dây đèn Champagne & tiểu tiết sao mảnh trên Header', 'despace-core' ); ?></strong>
									<span><?php esc_html_e( 'Viền ánh sáng thở nhẹ tinh tế dưới thanh điều hướng & biểu tượng sao cạnh Logo.', 'despace-core' ); ?></span>
								</div>
							</label>

							<label class="ds-toggle-row">
								<input type="checkbox" name="countdown_enabled" id="ds-countdown-enabled" value="1" <?php checked( ! empty( $settings['countdown_enabled'] ) ); ?> />
								<div>
									<strong><?php esc_html_e( 'Đồng hồ đếm ngược đến Giáng sinh (25/12) + Gợi ý quà tặng 3 tầng', 'despace-core' ); ?></strong>
									<span><?php esc_html_e( 'Hiển thị tinh tế trên trang chủ, liên kết trực tiếp tới bộ lọc giá / 3D / ưu đãi thực tế.', 'despace-core' ); ?></span>
								</div>
							</label>

							<label class="ds-toggle-row">
								<input type="checkbox" name="gift_box_enabled" id="ds-gift-enabled" value="1" <?php checked( ! empty( $settings['gift_box_enabled'] ) ); ?> />
								<div>
									<strong><?php esc_html_e( 'Phong bao Mở quà Giáng sinh tương tác (Wax-Seal Unwrap)', 'despace-core' ); ?></strong>
									<span><?php esc_html_e( 'Khách chạm con dấu sáp vàng để mở ưu đãi kèm hiệu ứng tia sáng Champagne và sao chép mã 1 chạm.', 'despace-core' ); ?></span>
								</div>
							</label>

							<label class="ds-toggle-row">
								<input type="checkbox" name="audio_enabled" id="ds-audio-enabled" value="1" <?php checked( ! empty( $settings['audio_enabled'] ) ); ?> />
								<div>
									<strong><?php esc_html_e( 'Nút giai điệu hộp âm Giáng sinh êm dịu (Web Audio — tắt mặc định)', 'despace-core' ); ?></strong>
									<span><?php esc_html_e( 'Không tự phát âm thanh; khách tự chọn bật/tắt tiếng chuông celesta êm ái (0 file MP3 nặng máy).', 'despace-core' ); ?></span>
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
									<label for="ds-campaign-id"><?php esc_html_e( 'Mã chiến dịch (Campaign ID):', 'despace-core' ); ?></label>
									<input type="text" id="ds-campaign-id" name="campaign_id" maxlength="48" value="<?php echo esc_attr( $campaign_id ); ?>" placeholder="christmas-2026" />
								</div>
								<div class="ds-field-group">
									<label for="ds-timezone"><?php esc_html_e( 'Múi giờ chiến dịch (Timezone):', 'despace-core' ); ?></label>
									<input type="text" id="ds-timezone" name="timezone" maxlength="64" value="<?php echo esc_attr( $tz_label ); ?>" placeholder="Asia/Ho_Chi_Minh" />
								</div>
							</div>

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

							<div class="ds-seasonal-tip-card">
								<strong>✦ Gợi ý vận hành chuẩn Showroom Luxury</strong>
								<ul>
									<li><strong>Bước 1:</strong> Chọn <em>Chế độ Xem trước (preview)</em> + Preset mong muốn và lưu để tự kiểm duyệt trên cả Desktop &amp; Mobile.</li>
									<li><strong>Bước 2:</strong> Chuyển sang <em>Bật công khai (active)</em>, bật Hẹn lịch (ví dụ <code>01/12</code> → <code>02/01</code>) để tự động trở về giao diện gốc sau mùa lễ.</li>
									<li><strong>Zero-Footprint:</strong> Khi tắt hoặc hết hạn lịch, mọi CSS/JS/DOM Noel biến mất hoàn toàn (0 KB tải thừa).</li>
								</ul>
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
								<label for="ds-promo-code"><?php esc_html_e( 'Mã ưu đãi WooCommerce đang hoạt động (ví dụ: DESPACE10):', 'despace-core' ); ?></label>
								<input type="text" id="ds-promo-code" name="promo_code" maxlength="32" value="<?php echo esc_attr( $settings['promo_code'] ); ?>" placeholder="DESPACE10" style="text-transform:uppercase;font-weight:700;letter-spacing:0.06em;" />
							</div>
							<div class="ds-field-group">
								<label for="ds-promo-vi"><?php esc_html_e( 'Thông điệp Banner & Phong bao (Tiếng Việt):', 'despace-core' ); ?></label>
								<input type="text" id="ds-promo-vi" name="promo_banner_vi" maxlength="240" value="<?php echo esc_attr( $settings['promo_banner_vi'] ); ?>" />
							</div>
							<div class="ds-field-group ds-field-group--span2">
								<label for="ds-promo-en"><?php esc_html_e( 'Thông điệp Banner & Phong bao (English):', 'despace-core' ); ?></label>
								<input type="text" id="ds-promo-en" name="promo_banner_en" maxlength="240" value="<?php echo esc_attr( $settings['promo_banner_en'] ); ?>" />
							</div>
						</div>
					</section>
				</div>

				<div class="ds-seasonal-submit-bar">
					<button type="submit" class="ds-btn ds-btn--primary ds-seasonal-save-btn">
						✦ <?php esc_html_e( 'Lưu cài đặt Winter Atelier', 'despace-core' ); ?>
					</button>
					<a href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener noreferrer" class="ds-btn ds-btn--outline">
						<?php esc_html_e( 'Xem trước nhanh ↗', 'despace-core' ); ?>
					</a>
				</div>
			</form>
		</div>

		<style>
			.ds-seasonal-admin .ds-seasonal-status-bar {
				display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px;
				background: #FFFFFF; border: 1px solid #E7E0D7; border-radius: 16px; padding: 14px 20px;
				margin-bottom: 18px; box-shadow: 0 2px 10px rgba(28, 25, 23, 0.04);
			}
			.ds-seasonal-admin .ds-seasonal-status-bar__left { display: flex; align-items: center; flex-wrap: wrap; gap: 12px; }
			.ds-seasonal-admin .ds-seasonal-pill { display: inline-flex; align-items: center; gap: 7px; padding: 5px 13px; border-radius: 999px; font-size: 12.5px; font-weight: 700; }
			.ds-seasonal-admin .ds-seasonal-pill__dot { width: 8px; height: 8px; border-radius: 50%; background: currentColor; }
			.ds-seasonal-admin .ds-seasonal-pill--off { background: #F2ECE3; color: #69645D; }
			.ds-seasonal-admin .ds-seasonal-pill--preview { background: #FEF5E7; color: #B45309; }
			.ds-seasonal-admin .ds-seasonal-pill--live { background: #E9F3EC; color: #1B5E38; }
			.ds-seasonal-admin .ds-seasonal-meta { font-size: 13px; color: #69645D; }
			.ds-seasonal-admin .ds-seasonal-quick-scenarios { display: flex; flex-wrap: wrap; gap: 7px; }
			.ds-seasonal-admin .ds-quick-chip {
				border: 1px solid #D8C4AE; background: #FAF7F2; color: #1C1917; border-radius: 999px;
				padding: 6px 12px; font-size: 12px; font-weight: 600; cursor: pointer; transition: all 0.16s ease;
			}
			.ds-seasonal-admin .ds-quick-chip:hover { background: #9A6745; border-color: #9A6745; color: #FFFFFF; }
			.ds-seasonal-admin .ds-quick-chip--muted { border-color: #E7E0D7; color: #69645D; background: #FFFFFF; }
			.ds-seasonal-admin .ds-quick-chip--muted:hover { background: #1C1917; border-color: #1C1917; color: #FAF7F2; }

			/* Live Mini-Preview Simulator */
			.ds-seasonal-admin .ds-seasonal-preview-panel { margin-bottom: 20px; padding: 18px 20px; }
			.ds-seasonal-admin .ds-seasonal-preview-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 14px; flex-wrap: wrap; }
			.ds-seasonal-admin .ds-seasonal-kicker { font-size: 10.5px; font-weight: 700; letter-spacing: 0.11em; color: #9A6745; display: block; }
			.ds-seasonal-admin .ds-seasonal-preview-head h2 { margin: 2px 0 0; font-size: 16px; color: #1C1917; }
			.ds-seasonal-admin .ds-preview-lang-switch { display: inline-flex; padding: 3px; background: #F2ECE3; border-radius: 999px; gap: 3px; }
			.ds-seasonal-admin .ds-prev-lang-btn { border: 0; background: transparent; padding: 4px 11px; border-radius: 999px; font-size: 11.5px; font-weight: 700; color: #69645D; cursor: pointer; }
			.ds-seasonal-admin .ds-prev-lang-btn.is-active { background: #1C1917; color: #FAF7F2; }

			.ds-seasonal-admin .ds-mini-browser {
				border: 1px solid #DDD4C8; border-radius: 14px; overflow: hidden;
				box-shadow: 0 12px 32px -12px rgba(28, 25, 23, 0.14); transition: background 0.25s ease;
			}
			.ds-seasonal-admin .ds-mini-marquee {
				display: flex; align-items: center; justify-content: center; gap: 10px; padding: 7px 14px;
				font-size: 11.5px; font-weight: 500; text-align: center; transition: all 0.25s ease;
			}
			.ds-seasonal-admin .ds-mini-header {
				display: flex; align-items: center; justify-content: space-between; padding: 10px 18px;
				border-bottom: 1px solid rgba(201, 169, 110, 0.28); transition: all 0.25s ease;
			}
			.ds-seasonal-admin .ds-mini-brand { font-family: Georgia, serif; font-size: 17px; font-weight: 700; letter-spacing: 0.02em; }
			.ds-seasonal-admin .ds-mini-nav { display: flex; gap: 16px; font-size: 12px; opacity: 0.85; }
			.ds-seasonal-admin .ds-mini-badge { font-size: 10.5px; font-weight: 700; padding: 3px 9px; border-radius: 999px; }
			.ds-seasonal-admin .ds-mini-garland {
				display: flex; justify-content: space-around; padding: 3px 20px; background: linear-gradient(90deg, rgba(201,169,110,0.08), rgba(201,169,110,0.22), rgba(201,169,110,0.08));
			}
			.ds-seasonal-admin .ds-mini-bulb {
				width: 6px; height: 6px; border-radius: 50%; background: #E7C873; box-shadow: 0 0 8px #D4AF37;
			}
			.ds-seasonal-admin .ds-mini-stage { padding: 14px 18px; display: grid; grid-template-columns: 1.55fr 1fr; gap: 14px; align-items: stretch; }
			.ds-seasonal-admin .ds-mini-hero {
				border-radius: 12px; padding: 16px 18px; color: #FAF7F2; display: flex; align-items: center; justify-content: space-between; gap: 14px;
			}
			.ds-seasonal-admin .ds-mini-eyebrow { display: block; font-size: 9.5px; font-weight: 700; letter-spacing: 0.14em; margin-bottom: 4px; }
			.ds-seasonal-admin .ds-mini-hero-copy strong { display: block; font-family: Georgia, serif; font-size: 17px; line-height: 1.3; font-weight: 500; }
			.ds-seasonal-admin .ds-mini-countdown { display: flex; gap: 8px; margin-top: 10px; }
			.ds-seasonal-admin .ds-mini-countdown span { background: rgba(255,255,255,0.12); border: 1px solid rgba(255,255,255,0.2); border-radius: 8px; padding: 4px 8px; text-align: center; }
			.ds-seasonal-admin .ds-mini-countdown strong { display: block; font-size: 13px; font-family: inherit; }
			.ds-seasonal-admin .ds-mini-countdown small { font-size: 9.5px; opacity: 0.8; }
			.ds-seasonal-admin .ds-mini-gift {
				flex-shrink: 0; background: rgba(255,255,255,0.1); border: 1px solid #C9A96E; border-radius: 10px;
				padding: 10px 12px; text-align: center; min-width: 112px;
			}
			.ds-seasonal-admin .ds-mini-gift-seal {
				display: inline-flex; width: 24px; height: 24px; border-radius: 50%; align-items: center; justify-content: center;
				color: #1C1917; font-size: 12px; margin-bottom: 4px;
			}
			.ds-seasonal-admin .ds-mini-gift small { display: block; font-size: 10px; opacity: 0.88; }
			.ds-seasonal-admin .ds-mini-gift code { display: inline-block; margin-top: 4px; background: rgba(0,0,0,0.25); color: #FAF7F2; border: 0; font-weight: 700; }
			.ds-seasonal-admin .ds-mini-cards { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
			.ds-seasonal-admin .ds-mini-card { background: #FFFFFF; border: 1px solid #E7E0D7; border-radius: 10px; padding: 8px; }
			.ds-seasonal-admin .ds-mini-card-img { position: relative; height: 74px; border-radius: 7px; background: linear-gradient(135deg, #F5EFE6 0%, #EAE0D2 100%); padding: 6px; display: flex; justify-content: space-between; align-items: flex-start; }
			.ds-seasonal-admin .ds-mini-card-pill { font-size: 8.5px; font-weight: 700; padding: 2px 6px; border-radius: 999px; }
			.ds-seasonal-admin .ds-mini-card-3d { font-size: 8.5px; font-weight: 700; padding: 2px 5px; border-radius: 999px; background: #FFFFFF; color: #1C1917; border: 1px solid #C9A96E; }
			.ds-seasonal-admin .ds-mini-card-lines { margin-top: 8px; display: flex; flex-direction: column; gap: 5px; }
			.ds-seasonal-admin .ds-mini-card-lines i { display: block; height: 7px; width: 80%; border-radius: 4px; background: #E7E0D7; }
			.ds-seasonal-admin .ds-mini-card-lines b { display: block; height: 7px; width: 45%; border-radius: 4px; background: #D8C4AE; }
			.ds-seasonal-admin .ds-mini-dock {
				display: flex; gap: 8px; padding: 8px 18px 10px; font-size: 11px; color: #69645D;
				border-top: 1px dashed rgba(28, 25, 23, 0.08);
			}
			.ds-seasonal-admin .ds-mini-dock span { background: #FFFFFF; border: 1px solid #E7E0D7; border-radius: 999px; padding: 2px 9px; }
			.ds-seasonal-admin .ds-mini-dock em { font-style: normal; font-weight: 600; color: #1C1917; }

			.ds-seasonal-admin .ds-seasonal-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 20px; }
			.ds-seasonal-admin .ds-seasonal-panel--full { grid-column: 1 / -1; }
			.ds-seasonal-admin .ds-seasonal-modes { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; }
			.ds-seasonal-admin .ds-seasonal-presets--four { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; }
			.ds-seasonal-admin .ds-mode-card,
			.ds-seasonal-admin .ds-preset-card {
				position: relative; display: flex; flex-direction: column; gap: 6px; padding: 16px 18px;
				border: 1.5px solid #E7E0D7; border-radius: 14px; background: #FAF7F2; cursor: pointer;
				transition: border-color 0.16s ease, background-color 0.16s ease, box-shadow 0.16s ease;
			}
			.ds-seasonal-admin .ds-mode-card:hover,
			.ds-seasonal-admin .ds-preset-card:hover { border-color: #C9A96E; background: #FFFFFF; }
			.ds-seasonal-admin .ds-mode-card.is-selected,
			.ds-seasonal-admin .ds-preset-card.is-selected {
				border-color: #9A6745; background: #FFFFFF; box-shadow: 0 0 0 3px rgba(154, 103, 69, 0.14);
			}
			.ds-seasonal-admin .ds-mode-card input[type="radio"],
			.ds-seasonal-admin .ds-preset-card input[type="radio"] { position: absolute; top: 14px; right: 14px; accent-color: #9A6745; }
			.ds-seasonal-admin .ds-mode-card__title { font-size: 14.5px; font-weight: 700; color: #1C1917; padding-right: 22px; }
			.ds-seasonal-admin .ds-mode-card__code { font-family: monospace; font-size: 11px; color: #9A6745; background: #F5ECE2; padding: 2px 7px; border-radius: 6px; align-self: flex-start; }
			.ds-seasonal-admin .ds-mode-card__desc,
			.ds-seasonal-admin .ds-preset-card__desc { font-size: 12.5px; color: #69645D; line-height: 1.5; margin: 2px 0 0; }
			.ds-seasonal-admin .ds-preset-card__top { display: flex; flex-direction: column; gap: 3px; padding-right: 24px; }
			.ds-seasonal-admin .ds-preset-card__top strong { font-size: 15px; color: #1C1917; }
			.ds-seasonal-admin .ds-preset-card__badge { font-size: 11px; font-weight: 600; color: #9A6745; }
			.ds-seasonal-admin .ds-preset-card__swatches { display: flex; gap: 6px; margin: 6px 0 4px; }
			.ds-seasonal-admin .ds-swatch-chip { width: 28px; height: 18px; border-radius: 6px; border: 1px solid rgba(28, 25, 23, 0.16); }
			.ds-seasonal-admin .ds-seasonal-fields { display: flex; flex-direction: column; gap: 12px; }
			.ds-seasonal-admin .ds-seasonal-fields--two-col { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
			.ds-seasonal-admin .ds-field-group--span2 { grid-column: 1 / -1; }
			.ds-seasonal-admin .ds-toggle-row {
				display: flex; align-items: flex-start; gap: 10px; padding: 11px 14px; border-radius: 12px;
				background: #FAF7F2; border: 1px solid #E7E0D7; cursor: pointer;
			}
			.ds-seasonal-admin .ds-toggle-row input[type="checkbox"] { margin-top: 3px; accent-color: #9A6745; }
			.ds-seasonal-admin .ds-toggle-row strong { display: block; font-size: 13.5px; color: #1C1917; }
			.ds-seasonal-admin .ds-toggle-row span { display: block; font-size: 12px; color: #69645D; margin-top: 2px; }
			.ds-seasonal-admin .ds-field-group { display: flex; flex-direction: column; gap: 6px; }
			.ds-seasonal-admin .ds-field-group label { font-size: 13px; font-weight: 600; color: #1C1917; }
			.ds-seasonal-admin .ds-field-group input,
			.ds-seasonal-admin .ds-field-group select { width: 100%; max-width: 100%; min-height: 38px; }
			.ds-seasonal-admin .ds-date-pair { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
			.ds-seasonal-admin .ds-seasonal-tip-card {
				margin-top: 6px; padding: 14px 16px; border-radius: 12px; background: #FAF7F2; border: 1px dashed #D8C4AE;
				font-size: 12.5px; color: #5C554D;
			}
			.ds-seasonal-admin .ds-seasonal-tip-card strong { color: #1C1917; }
			.ds-seasonal-admin .ds-seasonal-tip-card ul { margin: 8px 0 0 18px; padding: 0; line-height: 1.65; }
			.ds-seasonal-admin .ds-seasonal-submit-bar { display: flex; align-items: center; gap: 12px; margin-top: 22px; padding-top: 18px; border-top: 1px solid #E7E0D7; }
			@media (max-width: 1120px) {
				.ds-seasonal-admin .ds-seasonal-presets--four { grid-template-columns: repeat(2, minmax(0, 1fr)); }
				.ds-seasonal-admin .ds-mini-stage { grid-template-columns: 1fr; }
			}
			@media (max-width: 960px) {
				.ds-seasonal-admin .ds-seasonal-grid,
				.ds-seasonal-admin .ds-seasonal-modes,
				.ds-seasonal-admin .ds-seasonal-presets--four,
				.ds-seasonal-admin .ds-seasonal-fields--two-col,
				.ds-seasonal-admin .ds-date-pair { grid-template-columns: 1fr; }
			}
		</style>
		<script>
		(function () {
			var presets = <?php echo $presets_json ?: '{}'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>;
			var prevLang = 'vi';
			var form = document.getElementById('dsSeasonalForm');
			if (!form) return;

			function syncSelectedCards() {
				form.querySelectorAll('.ds-mode-card input[type="radio"], .ds-preset-card input[type="radio"]').forEach(function (r) {
					var card = r.closest('.ds-mode-card, .ds-preset-card');
					if (card) card.classList.toggle('is-selected', r.checked);
				});
			}

			function updateMiniPreview() {
				var checkedPreset = form.querySelector('input[name="preset"]:checked');
				var slug = checkedPreset ? checkedPreset.value : 'nordic';
				var p = (presets[slug] && presets[slug].preview) || (presets.nordic && presets.nordic.preview);
				if (!p) return;

				var box = document.getElementById('dsSeasonalMiniPreview');
				var mq = document.getElementById('dsPrevMarquee');
				var hd = document.getElementById('dsPrevHeader');
				var hero = document.getElementById('dsPrevHero');
				var badge = document.getElementById('dsPrevOfferBadge');
				var eyebrow = document.getElementById('dsPrevEyebrow');
				var gift = document.getElementById('dsPrevGift');
				var pill1 = document.getElementById('dsPrevCardPill');
				var pill2 = document.getElementById('dsPrevCardPill2');
				var c3d1 = document.getElementById('dsPrevCard3d');
				var c3d2 = document.getElementById('dsPrevCard3d2');
				var star = document.getElementById('dsPrevAccentStar');

				if (box) box.style.background = p.pageBg;
				if (mq) { mq.style.background = p.marqueeBg; mq.style.color = p.marqueeFg; }
				if (hd) { hd.style.background = p.headerBg; hd.style.color = p.headerFg; }
				if (hero) hero.style.background = p.heroBg;
				if (badge) { badge.style.background = p.marqueeBg; badge.style.color = p.marqueeFg; }
				if (eyebrow) eyebrow.style.color = p.accent;
				if (gift) gift.style.borderColor = p.accent;
				if (star) star.style.color = p.accent;
				[pill1, pill2].forEach(function (el) { if (el) { el.style.background = p.marqueeBg; el.style.color = p.marqueeFg; } });
				[c3d1, c3d2].forEach(function (el) { if (el) el.style.borderColor = p.accent; });
				document.querySelectorAll('#dsPrevMarquee .ds-mini-star').forEach(function (s) { s.style.color = p.accent; });
				var seal = document.querySelector('#dsPrevGift .ds-mini-gift-seal');
				if (seal) seal.style.background = p.accent;

				var mqInput = document.getElementById(prevLang === 'en' ? 'ds-marquee-en' : 'ds-marquee-vi');
				var mqText = document.getElementById('dsPrevMarqueeText');
				if (mqText && mqInput) mqText.textContent = mqInput.value || 'DeSpace Winter Atelier';

				var heroTitle = document.getElementById('dsPrevHeroTitle');
				if (heroTitle) {
					heroTitle.textContent = prevLang === 'en'
						? 'A Season to Feel at Home.'
						: 'Mùa lễ hội, chạm vào cảm giác an yên';
				}

				var codeInput = document.getElementById('ds-promo-code');
				var codeEl = document.getElementById('dsPrevPromoCode');
				if (codeEl && codeInput) codeEl.textContent = (codeInput.value || 'DESPACE10').toUpperCase();

				var hasAccent = document.getElementById('ds-header-accent') && document.getElementById('ds-header-accent').checked;
				var garland = document.getElementById('dsPrevGarland');
				if (garland) garland.style.display = hasAccent ? 'flex' : 'none';
				if (star) star.style.display = hasAccent ? 'inline' : 'none';

				var hasCd = document.getElementById('ds-countdown-enabled') && document.getElementById('ds-countdown-enabled').checked;
				var cdEl = document.getElementById('dsPrevCountdown');
				if (cdEl) cdEl.style.display = hasCd ? 'flex' : 'none';

				var hasGift = document.getElementById('ds-gift-enabled') && document.getElementById('ds-gift-enabled').checked;
				if (gift) gift.style.display = hasGift ? 'block' : 'none';

				var hasSnow = document.getElementById('ds-snow-enabled') && document.getElementById('ds-snow-enabled').checked;
				var snowPill = document.getElementById('dsPrevSnowPill');
				var snowMeta = document.getElementById('dsPrevSnowMeta');
				var styleSel = document.getElementById('ds-snow-style');
				if (snowPill) snowPill.style.opacity = hasSnow ? '1' : '0.35';
				if (snowMeta && styleSel) snowMeta.textContent = styleSel.options[styleSel.selectedIndex].text;

				var hasAudio = document.getElementById('ds-audio-enabled') && document.getElementById('ds-audio-enabled').checked;
				var audioPill = document.getElementById('dsPrevAudioPill');
				if (audioPill) audioPill.style.display = hasAudio ? 'inline-block' : 'none';
			}

			form.addEventListener('change', function () { syncSelectedCards(); updateMiniPreview(); });
			form.addEventListener('input', updateMiniPreview);

			document.querySelectorAll('.ds-prev-lang-btn').forEach(function (btn) {
				btn.addEventListener('click', function () {
					prevLang = this.getAttribute('data-prev-lang') || 'vi';
					document.querySelectorAll('.ds-prev-lang-btn').forEach(function (b) { b.classList.toggle('is-active', b === btn); });
					updateMiniPreview();
				});
			});

			document.querySelectorAll('.ds-quick-chip').forEach(function (btn) {
				btn.addEventListener('click', function () {
					var sc = this.getAttribute('data-scenario');
					var setRadio = function (name, val) {
						var r = form.querySelector('input[name="' + name + '"][value="' + val + '"]');
						if (r) r.checked = true;
					};
					if (sc === 'nordic_live') { setRadio('status', 'active'); setRadio('preset', 'nordic'); }
					if (sc === 'midnight_preview') { setRadio('status', 'preview'); setRadio('preset', 'midnight'); }
					if (sc === 'frost_live') { setRadio('status', 'active'); setRadio('preset', 'frost'); }
					if (sc === 'turn_off') { setRadio('status', 'off'); }
					syncSelectedCards();
					updateMiniPreview();
				});
			});

			syncSelectedCards();
			updateMiniPreview();
		})();
		</script>
		<?php
	}
}
