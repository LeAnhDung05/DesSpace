<?php
/**
 * DeSpace Seasonal Mode (Noel) Module Coordinator (v2.0 — Winter Atelier).
 *
 * Enforces strict Zero-Footprint architecture:
 * - When status = 'off', or status = 'preview' for non-admin visitors, or
 *   outside the configured schedule window: ZERO CSS, ZERO JS, ZERO body
 *   classes, and ZERO festive DOM are emitted on the storefront.
 * - When active: registers body classes, enqueues dedicated despace-noel.css
 *   & despace-noel.js with interactive countdown, gift-unwrap, opt-in Web Audio
 *   winter chime & 3-layer snow/bokeh engine, and shows Admin Bar status badge.
 *
 * @package DeSpace\Seasonal
 */

namespace DeSpace\Seasonal;

defined( 'ABSPATH' ) || exit;

/**
 * Class SeasonalModule
 */
class SeasonalModule {

	/**
	 * Prevent double bootstrap.
	 *
	 * @var bool
	 */
	private static bool $initialized = false;

	/**
	 * Initialize module hooks.
	 */
	public static function init(): void {
		if ( self::$initialized ) {
			return;
		}
		self::$initialized = true;

		// 1. Admin Settings Studio (RBAC: manage_options only).
		SeasonalAdminPage::init();

		// 2. Admin Bar Live / Preview Status Indicator (Admins only).
		add_action( 'admin_bar_menu', array( __CLASS__, 'register_admin_bar_indicator' ), 95 );

		// 3. Frontend Zero-Footprint Hooks.
		add_filter( 'body_class', array( __CLASS__, 'filter_body_class' ), 20 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_frontend_assets' ), 30 );
	}

	/**
	 * Append Noel theme body classes strictly when Seasonal Mode is active for this request.
	 *
	 * @param string[] $classes Existing body classes.
	 * @return string[]
	 */
	public static function filter_body_class( array $classes ): array {
		$state = SeasonalSettings::get_effective_state();
		if ( empty( $state['active'] ) ) {
			return $classes;
		}

		$settings  = $state['settings'];
		$classes[] = 'despace-theme-noel';
		$classes[] = 'despace-noel--' . sanitize_html_class( (string) $settings['preset'] );
		if ( ! empty( $settings['header_accent'] ) ) {
			$classes[] = 'despace-noel--header-accent';
		}

		return array_values( array_unique( $classes ) );
	}

	/**
	 * Enqueue despace-noel.css & despace-noel.js ONLY when active for this visitor.
	 */
	public static function enqueue_frontend_assets(): void {
		$state = SeasonalSettings::get_effective_state();
		if ( empty( $state['active'] ) ) {
			return;
		}

		$settings  = $state['settings'];
		$theme_dir = function_exists( 'get_stylesheet_directory' ) ? get_stylesheet_directory() : '';
		$theme_uri = function_exists( 'get_stylesheet_directory_uri' ) ? get_stylesheet_directory_uri() : '';
		$css_path  = $theme_dir . '/assets/css/despace-noel.css';
		$js_path   = $theme_dir . '/assets/js/despace-noel.js';

		if ( '' !== $theme_dir && file_exists( $css_path ) ) {
			wp_enqueue_style(
				'despace-noel',
				$theme_uri . '/assets/css/despace-noel.css',
				array( 'furniva' ),
				(string) filemtime( $css_path )
			);
		}

		if ( '' !== $theme_dir && file_exists( $js_path ) ) {
			wp_enqueue_script(
				'despace-noel',
				$theme_uri . '/assets/js/despace-noel.js',
				array(),
				(string) filemtime( $js_path ),
				true
			);

			$is_vi      = function_exists( 'furniva_is_vi' ) ? (bool) furniva_is_vi() : true;
			$promo_code = trim( (string) ( $settings['promo_code'] ?? '' ) );

			wp_localize_script(
				'despace-noel',
				'DESPACE_NOEL',
				array(
					'active'           => true,
					'mode'             => $state['mode'],
					'preset'           => $settings['preset'],
					'snowEnabled'      => ! empty( $settings['snow_enabled'] ),
					'snowDensity'      => $settings['snow_density'],
					'snowStyle'        => $settings['snow_style'],
					'countdownEnabled' => ! empty( $settings['countdown_enabled'] ),
					'giftBoxEnabled'   => ! empty( $settings['gift_box_enabled'] ),
					'audioEnabled'     => ! empty( $settings['audio_enabled'] ),
					'promoCode'        => $promo_code,
					'isVi'             => $is_vi,
					'labels'           => array(
						'pauseSnow'   => $is_vi ? 'Tắt hiệu ứng tuyết & ánh sáng' : 'Pause snow & light effect',
						'resumeSnow'  => $is_vi ? 'Bật hiệu ứng tuyết & ánh sáng' : 'Resume snow & light effect',
						'snowOn'      => $is_vi ? 'Tuyết' : 'Snow',
						'snowOff'     => $is_vi ? 'Đã tắt' : 'Paused',
						'musicOn'     => $is_vi ? 'Tắt giai điệu hộp âm Giáng sinh' : 'Mute winter music box',
						'musicOff'    => $is_vi ? 'Bật giai điệu hộp âm Giáng sinh êm dịu' : 'Play gentle winter music box',
						'musicLabel'  => $is_vi ? 'Nhạc lễ' : 'Chime',
						'copyCode'    => $is_vi ? 'Sao chép mã ưu đãi mùa lễ' : 'Copy holiday promo code',
						'copiedToast' => $is_vi ? '✦ Đã sao chép mã Giáng sinh {code}!' : '✦ Copied Holiday Code {code}!',
						'unwrapped'   => $is_vi ? '✦ Phong bao ưu đãi đã mở — chúc bạn mùa lễ an lành!' : '✦ Holiday privilege unwrapped — warm season wishes!',
						'merryNow'    => $is_vi ? 'Chúc Mừng Giáng Sinh An Lành!' : 'Merry Christmas from DeSpace!',
					),
				)
			);
		}
	}

	/**
	 * Display a subtle Admin Bar indicator whenever Noel mode is Preview or Active (Admins only).
	 *
	 * @param \WP_Admin_Bar $wp_admin_bar WordPress Admin Bar instance.
	 */
	public static function register_admin_bar_indicator( $wp_admin_bar ): void {
		if ( ! is_object( $wp_admin_bar ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$state    = SeasonalSettings::get_effective_state( true, null, false );
		$settings = $state['settings'];
		if ( 'off' === $settings['status'] ) {
			return;
		}

		$catalog      = SeasonalSettings::preset_catalog();
		$preset_label = $catalog[ $settings['preset'] ]['name'] ?? ucfirst( (string) $settings['preset'] );

		if ( 'preview' === $state['mode'] ) {
			$title = sprintf( '❄ Noel: Xem trước (%s)', $preset_label );
			$bg    = '#B45309';
		} elseif ( 'active' === $state['mode'] ) {
			$title = sprintf( '❄ Noel: Live (%s)', $preset_label );
			$bg    = '#1B5E38';
		} elseif ( 'scheduled_pending' === $state['mode'] ) {
			$title = '❄ Noel: Chờ lịch';
			$bg    = '#69645D';
		} else {
			$title = '❄ Noel: Hết hạn lịch';
			$bg    = '#69645D';
		}

		$wp_admin_bar->add_node(
			array(
				'id'    => 'despace-seasonal-status',
				'title' => '<span style="display:inline-flex;align-items:center;gap:5px;padding:1px 9px;border-radius:999px;background:' . esc_attr( $bg ) . ';color:#FAF7F2;font-size:11.5px;font-weight:600;line-height:20px;">' . esc_html( $title ) . '</span>',
				'href'  => admin_url( 'admin.php?page=' . SeasonalAdminPage::PAGE_SLUG ),
				'meta'  => array(
					'title' => __( 'Quản lý Giao diện Mùa lễ (Noel) DeSpace', 'despace-core' ),
				),
			)
		);
	}
}
