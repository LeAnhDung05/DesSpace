<?php
/**
 * DeSpace Seasonal Mode (Noel) Settings & Evaluator.
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
	 * Allowed activation statuses.
	 */
	public const VALID_STATUSES = array( 'off', 'preview', 'active' );

	/**
	 * Allowed visual presets.
	 */
	public const VALID_PRESETS = array( 'nordic', 'midnight', 'burgundy' );

	/**
	 * Allowed snow densities.
	 */
	public const VALID_DENSITIES = array( 'light', 'medium' );

	/**
	 * Default seasonal settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array(
			'status'           => 'off',
			'preset'           => 'nordic',
			'snow_enabled'     => true,
			'snow_density'     => 'light',
			'header_accent'    => true,
			'marquee_vi'       => 'Mùa Giáng Sinh An Lành — Ưu đãi lễ hội lên đến 20% & Quà tặng bài trí phòng từ DeSpace',
			'marquee_en'       => 'Season of Warmth — Holiday savings up to 20% & complimentary festive room styling',
			'promo_code'       => 'NOEL2026',
			'promo_banner_vi'  => 'Đặc quyền mùa lễ hội: Nhập mã NOEL2026 nhận ngay ưu đãi Giáng sinh cho không gian sống của bạn.',
			'promo_banner_en'  => 'Festive Privilege: Apply code NOEL2026 for exclusive holiday savings on handcrafted furniture.',
			'schedule_enabled' => false,
			'start_date'       => '',
			'end_date'         => '',
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
			// Ensure logical date bounds if admin entered inverted range.
			$tmp        = $start_date;
			$start_date = $end_date;
			$end_date   = $tmp;
		}

		$promo_raw  = strtoupper( trim( (string) ( $raw['promo_code'] ?? '' ) ) );
		$promo_code = substr( (string) preg_replace( '/[^A-Z0-9_-]/', '', $promo_raw ), 0, 32 );

		$to_bool = static function ( $val ): bool {
			if ( is_bool( $val ) ) {
				return $val;
			}
			return in_array( (string) $val, array( '1', 'true', 'yes', 'on' ), true );
		};

		return array(
			'status'           => $status,
			'preset'           => $preset,
			'snow_enabled'     => $to_bool( $raw['snow_enabled'] ?? false ),
			'snow_density'     => $density,
			'header_accent'    => $to_bool( $raw['header_accent'] ?? false ),
			'marquee_vi'       => $sanitize_text( $raw['marquee_vi'] ?? $defaults['marquee_vi'], 240 ),
			'marquee_en'       => $sanitize_text( $raw['marquee_en'] ?? $defaults['marquee_en'], 240 ),
			'promo_code'       => $promo_code,
			'promo_banner_vi'  => $sanitize_text( $raw['promo_banner_vi'] ?? $defaults['promo_banner_vi'], 240 ),
			'promo_banner_en'  => $sanitize_text( $raw['promo_banner_en'] ?? $defaults['promo_banner_en'], 240 ),
			'schedule_enabled' => $to_bool( $raw['schedule_enabled'] ?? false ),
			'start_date'       => $start_date,
			'end_date'         => $end_date,
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
	 * Resolve current local date (YYYY-MM-DD) in WordPress configured timezone.
	 *
	 * @return string
	 */
	public static function today_wp_date(): string {
		if ( function_exists( 'wp_date' ) ) {
			return (string) wp_date( 'Y-m-d' );
		}
		if ( function_exists( 'current_time' ) ) {
			return (string) current_time( 'Y-m-d' );
		}
		return gmdate( 'Y-m-d' );
	}

	/**
	 * Evaluate auto-schedule window for given settings.
	 *
	 * @param array<string, mixed> $settings  Sanitized settings.
	 * @param string|null          $today_ymd Optional override for deterministic testing.
	 * @return string 'open' | 'pending' | 'expired'
	 */
	public static function evaluate_schedule( array $settings, ?string $today_ymd = null ): string {
		if ( empty( $settings['schedule_enabled'] ) ) {
			return 'open';
		}

		$today = ( null !== $today_ymd && '' !== $today_ymd ) ? $today_ymd : self::today_wp_date();
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
	 * Human-readable preset metadata for Admin UI.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function preset_catalog(): array {
		return array(
			'nordic'   => array(
				'name'     => 'Nordic Hygge',
				'badge'    => 'Mặc định · Khuyên dùng',
				'desc'     => 'Nền kem ấm Bắc Âu kết hợp xanh thông trầm và vàng Champagne — tôn vân gỗ tự nhiên tối đa.',
				'swatches' => array( '#FAF7F2', '#1B3B2B', '#C9A96E' ),
			),
			'midnight' => array(
				'name'     => 'Midnight Gold',
				'badge'    => 'Đêm Đông Sang Trọng',
				'desc'     => 'Header, Hero và Footer sắc đêm đông xanh rêu trầm điểm vàng kim; lưới sản phẩm giữ nền sáng sạch.',
				'swatches' => array( '#0E1A16', '#D4AF37', '#FAF7F2' ),
			),
			'burgundy' => array(
				'name'     => 'Velvet Burgundy',
				'badge'    => 'Lễ Hội Nồng Ấm',
				'desc'     => 'Đỏ rượu vang trầm phối xanh thông cổ điển và kem sữa — đậm không khí ưu đãi Noel nhưng vẫn tinh tế.',
				'swatches' => array( '#6E1D27', '#1F3A2E', '#FBF8F3' ),
			),
		);
	}
}
