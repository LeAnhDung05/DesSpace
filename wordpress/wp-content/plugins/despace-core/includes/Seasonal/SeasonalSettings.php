<?php
/**
 * DeSpace Seasonal Mode (Noel) Settings & Evaluator (v2.0 — Winter Atelier).
 *
 * Manages wp_options persistence, strict sanitization, WordPress-timezone
 * auto-scheduling, and zero-footprint activation rules (off / preview / active).
 *
 * @package DeSpace\Seasonal
 */

namespace DeSpace\Seasonal;

defined( 'ABSPATH' ) || exit;

/**
 * Class SeasonalSettings
 */
class SeasonalSettings {

	/**
	 * WordPress option key.
	 */
	public const OPTION_KEY = 'despace_seasonal_settings';

	/**
	 * Centralized Christmas 2026 — Winter Atelier campaign defaults.
	 */
	public const DEFAULT_CAMPAIGN_ID = 'christmas-2026';
	public const DEFAULT_TIMEZONE    = 'Asia/Ho_Chi_Minh';
	public const DEFAULT_START_DATE  = '2026-11-22';
	public const DEFAULT_END_DATE    = '2026-12-26';

	/**
	 * Allowed activation statuses.
	 */
	public const VALID_STATUSES = array( 'off', 'preview', 'active' );

	/**
	 * Allowed visual presets.
	 */
	public const VALID_PRESETS = array( 'nordic', 'midnight', 'burgundy', 'frost' );

	/**
	 * Allowed snow densities.
	 */
	public const VALID_DENSITIES = array( 'light', 'medium' );

	/**
	 * Allowed atmospheric particle styles.
	 */
	public const VALID_SNOW_STYLES = array( 'snow_bokeh', 'crystals', 'champagne' );

	/**
	 * Default seasonal settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array(
			'campaign_id'       => self::DEFAULT_CAMPAIGN_ID,
			'timezone'          => self::DEFAULT_TIMEZONE,
			'status'            => 'off',
			'preset'            => 'nordic',
			'snow_enabled'      => true,
			'snow_density'      => 'light',
			'snow_style'        => 'snow_bokeh',
			'header_accent'     => true,
			'countdown_enabled' => true,
			'gift_box_enabled'  => true,
			'audio_enabled'     => true,
			'marquee_vi'        => 'DESPACE WINTER ATELIER 2026 — Bộ sưu tập nội thất mùa lễ hội & Trải nghiệm 3D/AR 1:1',
			'marquee_en'        => 'DESPACE WINTER ATELIER 2026 — Holiday Furniture Edit & True-to-Scale 3D/AR Room Preview',
			'promo_code'        => 'DESPACE10',
			'promo_banner_vi'   => 'Đặc quyền Winter Atelier 2026: Mở phong bao nhận mã DESPACE10 (giảm 10% đơn hàng đang hoạt động tại cửa hàng).',
			'promo_banner_en'   => 'Winter Atelier 2026 Privilege: Unwrap active store code DESPACE10 for 10% off your order at checkout.',
			'schedule_enabled'  => false,
			'start_date'        => '',
			'end_date'          => '',
		);
	}

	/**
	 * Retrieve merged & normalized settings from wp_options.
	 *
	 * @return array<string, mixed>
	 */
	public static function get(): array {
		$defaults = self::defaults();
		$stored   = function_exists( 'get_option' ) ? get_option( self::OPTION_KEY, array() ) : array();
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		return self::sanitize( array_merge( $defaults, $stored ) );
	}

	/**
	 * Sanitize incoming settings payload.
	 *
	 * @param array<string, mixed> $raw Raw input values.
	 * @return array<string, mixed>
	 */
	public static function sanitize( array $raw ): array {
		$defaults = self::defaults();

		$status = isset( $raw['status'] ) ? strtolower( trim( (string) $raw['status'] ) ) : $defaults['status'];
		if ( ! in_array( $status, self::VALID_STATUSES, true ) ) {
			$status = 'off';
		}

		$preset = isset( $raw['preset'] ) ? strtolower( trim( (string) $raw['preset'] ) ) : $defaults['preset'];
		if ( ! in_array( $preset, self::VALID_PRESETS, true ) ) {
			$preset = 'nordic';
		}

		$density = isset( $raw['snow_density'] ) ? strtolower( trim( (string) $raw['snow_density'] ) ) : $defaults['snow_density'];
		if ( ! in_array( $density, self::VALID_DENSITIES, true ) ) {
			$density = 'light';
		}

		$snow_style = isset( $raw['snow_style'] ) ? strtolower( trim( (string) $raw['snow_style'] ) ) : $defaults['snow_style'];
		if ( ! in_array( $snow_style, self::VALID_SNOW_STYLES, true ) ) {
			$snow_style = 'snow_bokeh';
		}

		$sanitize_text = static function ( $val, int $max_len = 240 ): string {
			$str = (string) $val;
			if ( function_exists( 'sanitize_text_field' ) ) {
				$str = sanitize_text_field( $str );
			} else {
				$str = trim( strip_tags( $str ) );
			}
			if ( function_exists( 'mb_substr' ) ) {
				return mb_substr( $str, 0, $max_len, 'UTF-8' );
			}
			return substr( $str, 0, $max_len );
		};

		$sanitize_date = static function ( $val ): string {
			$d = trim( (string) $val );
			if ( '' === $d || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ) ) {
				return '';
			}
			$parts = explode( '-', $d );
			return checkdate( (int) $parts[1], (int) $parts[2], (int) $parts[0] ) ? $d : '';
		};

		$start_date = $sanitize_date( $raw['start_date'] ?? '' );
		$end_date   = $sanitize_date( $raw['end_date'] ?? '' );
		if ( '' !== $start_date && '' !== $end_date && $start_date > $end_date ) {
			$tmp        = $start_date;
			$start_date = $end_date;
			$end_date   = $tmp;
		}

		$promo_raw  = strtoupper( trim( (string) ( $raw['promo_code'] ?? '' ) ) );
		$promo_code = substr( (string) preg_replace( '/[^A-Z0-9_-]/', '', $promo_raw ), 0, 32 );

		$campaign_raw = strtolower( trim( (string) ( $raw['campaign_id'] ?? $defaults['campaign_id'] ) ) );
		$campaign_id  = substr( (string) preg_replace( '/[^a-z0-9_-]/', '', $campaign_raw ), 0, 48 );
		if ( '' === $campaign_id ) {
			$campaign_id = self::DEFAULT_CAMPAIGN_ID;
		}

		$tz_raw   = trim( (string) ( $raw['timezone'] ?? $defaults['timezone'] ) );
		$timezone = self::DEFAULT_TIMEZONE;
		if ( '' !== $tz_raw ) {
			try {
				new \DateTimeZone( $tz_raw );
				$timezone = $tz_raw;
			} catch ( \Exception $e ) {
				$timezone = self::DEFAULT_TIMEZONE;
			}
		}

		$to_bool = static function ( $val ): bool {
			if ( is_bool( $val ) ) {
				return $val;
			}
			return in_array( (string) $val, array( '1', 'true', 'yes', 'on' ), true );
		};

		return array(
			'campaign_id'       => $campaign_id,
			'timezone'          => $timezone,
			'status'            => $status,
			'preset'            => $preset,
			'snow_enabled'      => $to_bool( $raw['snow_enabled'] ?? false ),
			'snow_density'      => $density,
			'snow_style'        => $snow_style,
			'header_accent'     => $to_bool( $raw['header_accent'] ?? false ),
			'countdown_enabled' => $to_bool( $raw['countdown_enabled'] ?? false ),
			'gift_box_enabled'  => $to_bool( $raw['gift_box_enabled'] ?? false ),
			'audio_enabled'     => $to_bool( $raw['audio_enabled'] ?? false ),
			'marquee_vi'        => $sanitize_text( $raw['marquee_vi'] ?? $defaults['marquee_vi'], 240 ),
			'marquee_en'        => $sanitize_text( $raw['marquee_en'] ?? $defaults['marquee_en'], 240 ),
			'promo_code'        => $promo_code,
			'promo_banner_vi'   => $sanitize_text( $raw['promo_banner_vi'] ?? $defaults['promo_banner_vi'], 240 ),
			'promo_banner_en'   => $sanitize_text( $raw['promo_banner_en'] ?? $defaults['promo_banner_en'], 240 ),
			'schedule_enabled'  => $to_bool( $raw['schedule_enabled'] ?? false ),
			'start_date'        => $start_date,
			'end_date'          => $end_date,
		);
	}

	/**
	 * Persist sanitized settings.
	 *
	 * @param array<string, mixed> $raw Raw form values.
	 * @return array<string, mixed> Sanitized saved settings.
	 */
	public static function save( array $raw ): array {
		$clean = self::sanitize( $raw );
		if ( function_exists( 'update_option' ) ) {
			update_option( self::OPTION_KEY, $clean, true );
		}
		return $clean;
	}

	/**
	 * Resolve current local date (YYYY-MM-DD) in configured campaign timezone
	 * (defaults to Asia/Ho_Chi_Minh).
	 *
	 * @param string|null $timezone Optional IANA timezone string.
	 * @return string
	 */
	public static function today_wp_date( ?string $timezone = null ): string {
		$tz_name = ( null !== $timezone && '' !== $timezone ) ? $timezone : self::DEFAULT_TIMEZONE;
		try {
			$dt = new \DateTimeImmutable( 'now', new \DateTimeZone( $tz_name ) );
			return $dt->format( 'Y-m-d' );
		} catch ( \Exception $e ) {
			if ( function_exists( 'wp_date' ) ) {
				return (string) wp_date( 'Y-m-d' );
			}
			if ( function_exists( 'current_time' ) ) {
				return (string) current_time( 'Y-m-d' );
			}
			return gmdate( 'Y-m-d' );
		}
	}

	/**
	 * Evaluate auto-schedule window for given settings (inclusive of start_date & end_date).
	 *
	 * @param array<string, mixed> $settings  Sanitized settings.
	 * @param string|null          $today_ymd Optional override for deterministic testing.
	 * @return string 'open' | 'pending' | 'expired'
	 */
	public static function evaluate_schedule( array $settings, ?string $today_ymd = null ): string {
		if ( empty( $settings['schedule_enabled'] ) ) {
			return 'open';
		}

		$tz    = (string) ( $settings['timezone'] ?? self::DEFAULT_TIMEZONE );
		$today = ( null !== $today_ymd && '' !== $today_ymd ) ? $today_ymd : self::today_wp_date( $tz );
		$start = (string) ( $settings['start_date'] ?? '' );
		$end   = (string) ( $settings['end_date'] ?? '' );

		if ( '' !== $start && $today < $start ) {
			return 'pending';
		}
		if ( '' !== $end && $today > $end ) {
			return 'expired';
		}

		return 'open';
	}

	/**
	 * Compute complete effective state for the current visitor / admin.
	 *
	 * @param bool|null   $is_admin_user Optional override (defaults to current_user_can('manage_options')).
	 * @param string|null $today_ymd     Optional YYYY-MM-DD override for testing.
	 * @param bool|null   $in_wp_admin   Optional override (defaults to is_admin()).
	 * @return array<string, mixed>
	 */
	public static function get_effective_state( ?bool $is_admin_user = null, ?string $today_ymd = null, ?bool $in_wp_admin = null ): array {
		$settings       = self::get();
		$admin_cap      = null !== $is_admin_user ? $is_admin_user : ( function_exists( 'current_user_can' ) && current_user_can( 'manage_options' ) );
		$is_admin_area  = null !== $in_wp_admin ? $in_wp_admin : ( function_exists( 'is_admin' ) && is_admin() );
		$schedule_state = self::evaluate_schedule( $settings, $today_ymd );

		$active = false;
		$mode   = 'off';

		if ( 'preview' === $settings['status'] ) {
			$mode   = 'preview';
			$active = ! $is_admin_area && $admin_cap;
		} elseif ( 'active' === $settings['status'] ) {
			if ( 'open' === $schedule_state ) {
				$mode   = 'active';
				$active = ! $is_admin_area;
			} elseif ( 'pending' === $schedule_state ) {
				$mode   = 'scheduled_pending';
				$active = false;
			} else {
				$mode   = 'scheduled_expired';
				$active = false;
			}
		}

		return array(
			'active'         => $active,
			'mode'           => $mode,
			'schedule_state' => $schedule_state,
			'preset'         => $settings['preset'],
			'settings'       => $settings,
		);
	}

	/**
	 * Shortcut: should Noel theme assets and markup render on this frontend request?
	 *
	 * @param bool|null   $is_admin_user Optional admin capability override.
	 * @param string|null $today_ymd     Optional date override.
	 * @param bool|null   $in_wp_admin   Optional admin screen override.
	 * @return bool
	 */
	public static function is_active_for_request( ?bool $is_admin_user = null, ?string $today_ymd = null, ?bool $in_wp_admin = null ): bool {
		$state = self::get_effective_state( $is_admin_user, $today_ymd, $in_wp_admin );
		return ! empty( $state['active'] );
	}

	/**
	 * Human-readable preset metadata for Admin UI & live preview.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function preset_catalog(): array {
		return array(
			'nordic'   => array(
				'name'     => 'Winter Atelier 2026 (Nordic Hygge)',
				'badge'    => 'Christmas 2026 · Chủ đạo',
				'desc'     => 'Ivory (#F4F0E8), Ice (#D9E2E6), Pine (#183B36), Berry (#A9443F) & Brass (#BD9A62) — thanh lịch Bắc Âu, tôn vân gỗ nguyên bản.',
				'swatches' => array( '#F4F0E8', '#183B36', '#BD9A62', '#A9443F', '#D9E2E6' ),
				'preview'  => array(
					'marqueeBg' => '#183B36',
					'marqueeFg' => '#F4F0E8',
					'headerBg'  => '#F4F0E8',
					'headerFg'  => '#183B36',
					'heroBg'    => 'linear-gradient(135deg, #183B36 0%, #102824 100%)',
					'accent'    => '#BD9A62',
					'pageBg'    => '#F4F0E8',
				),
			),
			'midnight' => array(
				'name'     => 'Midnight Gold',
				'badge'    => 'Đêm Đông Đẳng Cấp',
				'desc'     => 'Header, Hero & Footer sắc đêm đông xanh rêu trầm ánh vàng kim; lưới sản phẩm giữ nền sáng sạch.',
				'swatches' => array( '#0E1A16', '#D4AF37', '#FAF7F2' ),
				'preview'  => array(
					'marqueeBg' => '#09120F',
					'marqueeFg' => '#F7EFE0',
					'headerBg'  => '#0E1A16',
					'headerFg'  => '#F5EFE3',
					'heroBg'    => 'linear-gradient(135deg, #0E1A16 0%, #172B24 100%)',
					'accent'    => '#D4AF37',
					'pageBg'    => '#F7F5F0',
				),
			),
			'burgundy' => array(
				'name'     => 'Velvet Burgundy',
				'badge'    => 'Lễ Hội Nồng Ấm',
				'desc'     => 'Đỏ rượu vang nhung trầm phối xanh thông cổ điển & kem sữa — nổi bật ưu đãi lễ hội nhưng vẫn thanh lịch.',
				'swatches' => array( '#6E1D27', '#1F3A2E', '#FBF8F3' ),
				'preview'  => array(
					'marqueeBg' => '#6E1D27',
					'marqueeFg' => '#FBF8F3',
					'headerBg'  => '#FBF8F3',
					'headerFg'  => '#2B1618',
					'heroBg'    => 'linear-gradient(135deg, #6E1D27 0%, #4A1118 100%)',
					'accent'    => '#D6B47A',
					'pageBg'    => '#FBF8F3',
				),
			),
			'frost'    => array(
				'name'     => 'Alpine Frost',
				'badge'    => 'Mới · Biệt Thự Tuyết Thụy Sĩ',
				'desc'     => 'Sương băng thanh khiết kết hợp xanh vân sam trầm, ánh bạc mịn & đồng thau ấm chuẩn resort vùng Alps.',
				'swatches' => array( '#F3F6F9', '#1E3342', '#C7A66B' ),
				'preview'  => array(
					'marqueeBg' => '#1E3342',
					'marqueeFg' => '#F3F6F9',
					'headerBg'  => '#F7FAFC',
					'headerFg'  => '#1B2A35',
					'heroBg'    => 'linear-gradient(135deg, #1E3342 0%, #13222E 100%)',
					'accent'    => '#C7A66B',
					'pageBg'    => '#F3F6F9',
				),
			),
		);
	}
}
