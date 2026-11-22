<?php
/**
 * Unit tests for DeSpace Seasonal Mode (Noel) Feature Flag System.
 *
 * Tests:
 *  TC-SN-01: Default settings contract (status=off, preset=nordic)
 *  TC-SN-02: Strict sanitization (invalid status/preset/density/date/promo XSS)
 *  TC-SN-03: Inverted start_date > end_date auto-normalization
 *  TC-SN-04: Mode 'off' => inactive for both guests and admins (Zero-Footprint)
 *  TC-SN-05: Mode 'preview' => active ONLY for admins (manage_options), hidden from guests
 *  TC-SN-06: Mode 'active' (no schedule) => active for all frontend visitors, inactive inside wp-admin
 *  TC-SN-07: Auto-schedule 'pending' (today < start_date) disables public display
 *  TC-SN-08: Auto-schedule 'open' (start_date <= today <= end_date) activates public display
 *  TC-SN-09: Auto-schedule 'expired' (today > end_date) auto-disables public display
 *  TC-SN-10: filter_body_class adds ZERO classes when off / non-admin preview
 *  TC-SN-11: filter_body_class adds despace-theme-noel & preset class for all 3 presets
 *  TC-SN-12: Zero-Footprint asset enqueue (0 styles/scripts when off; both when active)
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/../wordpress/' );
}

// Lightweight WP test stubs.
$mock_options   = array();
$mock_is_admin  = false;
$mock_has_cap   = false;
$mock_styles    = array();
$mock_scripts   = array();
$mock_localized = array();

if ( ! function_exists( 'get_option' ) ) {
	function get_option( string $key, $default = false ) {
		global $mock_options;
		return array_key_exists( $key, $mock_options ) ? $mock_options[ $key ] : $default;
	}
}
if ( ! function_exists( 'update_option' ) ) {
	function update_option( string $key, $value, $autoload = null ): bool {
		global $mock_options;
		$mock_options[ $key ] = $value;
		return true;
	}
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( string $str ): string {
		return trim( preg_replace( '/\s+/', ' ', strip_tags( $str ) ) );
	}
}
if ( ! function_exists( 'sanitize_html_class' ) ) {
	function sanitize_html_class( string $class ): string {
		return (string) preg_replace( '/[^A-Za-z0-9_-]/', '', $class );
	}
}
if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( string $cap ): bool {
		global $mock_has_cap;
		return 'manage_options' === $cap && $mock_has_cap;
	}
}
if ( ! function_exists( 'is_admin' ) ) {
	function is_admin(): bool {
		global $mock_is_admin;
		return $mock_is_admin;
	}
}
if ( ! function_exists( 'wp_date' ) ) {
	function wp_date( string $fmt ): string {
		return gmdate( $fmt );
	}
}
if ( ! function_exists( 'get_stylesheet_directory' ) ) {
	function get_stylesheet_directory(): string {
		return dirname( __DIR__ ) . '/wordpress/wp-content/themes/despace-child';
	}
}
if ( ! function_exists( 'get_stylesheet_directory_uri' ) ) {
	function get_stylesheet_directory_uri(): string {
		return 'http://localhost:8090/wp-content/themes/despace-child';
	}
}
if ( ! function_exists( 'wp_enqueue_style' ) ) {
	function wp_enqueue_style( string $handle, string $src = '', array $deps = array(), $ver = false ): void {
		global $mock_styles;
		$mock_styles[ $handle ] = array( 'src' => $src, 'deps' => $deps, 'ver' => $ver );
	}
}
if ( ! function_exists( 'wp_enqueue_script' ) ) {
	function wp_enqueue_script( string $handle, string $src = '', array $deps = array(), $ver = false, bool $in_footer = false ): void {
		global $mock_scripts;
		$mock_scripts[ $handle ] = array( 'src' => $src, 'deps' => $deps, 'ver' => $ver, 'footer' => $in_footer );
	}
}
if ( ! function_exists( 'wp_localize_script' ) ) {
	function wp_localize_script( string $handle, string $name, array $data ): bool {
		global $mock_localized;
		$mock_localized[ $handle ][ $name ] = $data;
		return true;
	}
}

require_once dirname( __DIR__ ) . '/wordpress/wp-content/plugins/despace-core/includes/Seasonal/SeasonalSettings.php';
require_once dirname( __DIR__ ) . '/wordpress/wp-content/plugins/despace-core/includes/Seasonal/SeasonalModule.php';

use DeSpace\Seasonal\SeasonalModule;
use DeSpace\Seasonal\SeasonalSettings;

$passed = 0;
$failed = 0;
$assert = static function ( string $code, string $label, bool $cond ) use ( &$passed, &$failed ): void {
	if ( $cond ) {
		$passed++;
		printf( "[%-8s] %-64s : PASS\n", $code, $label );
	} else {
		$failed++;
		printf( "[%-8s] %-64s : FAIL\n", $code, $label );
	}
};

// TC-SN-01
$mock_options = array();
$def = SeasonalSettings::get();
$assert( 'TC-SN-01', 'Default seasonal config is status=off & preset=nordic', 'off' === $def['status'] && 'nordic' === $def['preset'] && false === $def['schedule_enabled'] );

// TC-SN-02
$clean = SeasonalSettings::sanitize( array(
	'status'       => 'evil_mode',
	'preset'       => 'neon_pink',
	'snow_density' => 'blizzard',
	'promo_code'   => 'noel 2026<script>!',
	'marquee_vi'   => '<b>Giáng sinh</b> an lành',
	'start_date'   => '2026-02-31',
	'end_date'     => 'not-a-date',
) );
$assert(
	'TC-SN-02',
	'Strict whitelist sanitization strips XSS & invalid enums/dates',
	'off' === $clean['status']
	&& 'nordic' === $clean['preset']
	&& 'light' === $clean['snow_density']
	&& 'NOEL2026SCRIPT' === $clean['promo_code']
	&& 'Giáng sinh an lành' === $clean['marquee_vi']
	&& '' === $clean['start_date']
	&& '' === $clean['end_date']
);

// TC-SN-03
$swapped = SeasonalSettings::sanitize( array(
	'schedule_enabled' => true,
	'start_date'       => '2026-12-31',
	'end_date'         => '2026-12-01',
) );
$assert( 'TC-SN-03', 'Inverted start_date > end_date normalized cleanly', '2026-12-01' === $swapped['start_date'] && '2026-12-31' === $swapped['end_date'] );

// TC-SN-04
SeasonalSettings::save( array( 'status' => 'off', 'preset' => 'nordic' ) );
$assert(
	'TC-SN-04',
	'Mode off is inactive for both guests and admins',
	false === SeasonalSettings::is_active_for_request( false, '2026-12-20', false )
	&& false === SeasonalSettings::is_active_for_request( true, '2026-12-20', false )
);

// TC-SN-05
SeasonalSettings::save( array( 'status' => 'preview', 'preset' => 'midnight' ) );
$assert(
	'TC-SN-05',
	'Mode preview is active ONLY for Admin (manage_options), hidden from guest',
	true === SeasonalSettings::is_active_for_request( true, '2026-12-20', false )
	&& false === SeasonalSettings::is_active_for_request( false, '2026-12-20', false )
);

// TC-SN-06
SeasonalSettings::save( array( 'status' => 'active', 'preset' => 'burgundy', 'schedule_enabled' => false ) );
$assert(
	'TC-SN-06',
	'Mode active shows to frontend guests & never triggers inside wp-admin',
	true === SeasonalSettings::is_active_for_request( false, '2026-12-20', false )
	&& false === SeasonalSettings::is_active_for_request( true, '2026-12-20', true )
);

// TC-SN-07, TC-SN-08, TC-SN-09: Auto-Schedule window
SeasonalSettings::save( array(
	'status'           => 'active',
	'preset'           => 'nordic',
	'schedule_enabled' => true,
	'start_date'       => '2026-12-05',
	'end_date'         => '2026-12-28',
) );
$st_pending = SeasonalSettings::get_effective_state( false, '2026-12-01', false );
$st_open    = SeasonalSettings::get_effective_state( false, '2026-12-24', false );
$st_expired = SeasonalSettings::get_effective_state( false, '2026-12-29', false );
$assert( 'TC-SN-07', 'Auto-schedule pending before start_date disables Noel', false === $st_pending['active'] && 'scheduled_pending' === $st_pending['mode'] );
$assert( 'TC-SN-08', 'Auto-schedule open within [start, end] activates Noel', true === $st_open['active'] && 'active' === $st_open['mode'] );
$assert( 'TC-SN-09', 'Auto-schedule expired after end_date auto-disables Noel', false === $st_expired['active'] && 'scheduled_expired' === $st_expired['mode'] );

// TC-SN-10: Zero-footprint body_class when off or guest in preview
$mock_is_admin = false;
$mock_has_cap  = false;
SeasonalSettings::save( array( 'status' => 'off', 'preset' => 'nordic' ) );
$cls_off = SeasonalModule::filter_body_class( array( 'home', 'despace-site' ) );
SeasonalSettings::save( array( 'status' => 'preview', 'preset' => 'midnight' ) );
$cls_guest_preview = SeasonalModule::filter_body_class( array( 'home', 'despace-site' ) );
$assert(
	'TC-SN-10',
	'body_class leaves classes untouched when off or guest in preview',
	$cls_off === array( 'home', 'despace-site' ) && $cls_guest_preview === array( 'home', 'despace-site' )
);

// TC-SN-11: All 3 presets apply proper body classes
$preset_ok = true;
foreach ( SeasonalSettings::VALID_PRESETS as $preset ) {
	SeasonalSettings::save( array( 'status' => 'active', 'preset' => $preset, 'header_accent' => true ) );
	$cls = SeasonalModule::filter_body_class( array( 'despace-site' ) );
	if ( ! in_array( 'despace-theme-noel', $cls, true ) || ! in_array( 'despace-noel--' . $preset, $cls, true ) ) {
		$preset_ok = false;
	}
}
$assert( 'TC-SN-11', 'All 3 presets emit despace-theme-noel & preset modifier class', $preset_ok );

// TC-SN-12: Zero-footprint enqueue check
$mock_styles = $mock_scripts = $mock_localized = array();
SeasonalSettings::save( array( 'status' => 'off', 'preset' => 'nordic' ) );
SeasonalModule::enqueue_frontend_assets();
$zero_when_off = empty( $mock_styles ) && empty( $mock_scripts ) && empty( $mock_localized );

SeasonalSettings::save( array( 'status' => 'active', 'preset' => 'nordic', 'snow_enabled' => true ) );
SeasonalModule::enqueue_frontend_assets();
$loaded_when_on = isset( $mock_styles['despace-noel'], $mock_scripts['despace-noel'], $mock_localized['despace-noel']['DESPACE_NOEL'] );
$assert( 'TC-SN-12', 'Zero-Footprint enqueue (0 assets when off; CSS+JS+config when on)', $zero_when_off && $loaded_when_on );

// TC-SN-13: 4th Luxury Preset (Alpine Frost) supported & emits body class
SeasonalSettings::save( array( 'status' => 'active', 'preset' => 'frost', 'header_accent' => true ) );
$cls_frost = SeasonalModule::filter_body_class( array( 'despace-site' ) );
$assert(
	'TC-SN-13',
	'4th Luxury Preset Alpine Frost emits despace-noel--frost class',
	in_array( 'despace-theme-noel', $cls_frost, true ) && in_array( 'despace-noel--frost', $cls_frost, true )
);

// TC-SN-14: snow_style whitelist sanitization (snow_bokeh | crystals | champagne)
$s_valid = SeasonalSettings::sanitize( array( 'snow_style' => 'champagne' ) );
$s_bad   = SeasonalSettings::sanitize( array( 'snow_style' => 'confetti_attack' ) );
$assert( 'TC-SN-14', 'snow_style accepts champagne & rejects invalid values', 'champagne' === $s_valid['snow_style'] && 'snow_bokeh' === $s_bad['snow_style'] );

// TC-SN-15: Interactive v2 toggles (countdown, gift box, opt-in audio) localized cleanly
$mock_styles = $mock_scripts = $mock_localized = array();
SeasonalSettings::save( array(
	'status'            => 'active',
	'preset'            => 'frost',
	'snow_style'        => 'crystals',
	'countdown_enabled' => true,
	'gift_box_enabled'  => true,
	'audio_enabled'     => false,
	'promo_code'        => 'WINTER26',
) );
SeasonalModule::enqueue_frontend_assets();
$loc = $mock_localized['despace-noel']['DESPACE_NOEL'] ?? array();
$assert(
	'TC-SN-15',
	'v2 localized payload carries snowStyle, countdown, giftBox, audio & promoCode',
	'crystals' === ( $loc['snowStyle'] ?? '' )
	&& true === ( $loc['countdownEnabled'] ?? null )
	&& true === ( $loc['giftBoxEnabled'] ?? null )
	&& false === ( $loc['audioEnabled'] ?? null )
	&& 'WINTER26' === ( $loc['promoCode'] ?? '' )
);

// TC-SN-16: All 4 presets expose complete live-preview palette metadata
$cat = SeasonalSettings::preset_catalog();
$cat_ok = count( $cat ) === 4;
foreach ( array( 'nordic', 'midnight', 'burgundy', 'frost' ) as $k ) {
	if ( empty( $cat[ $k ]['preview']['marqueeBg'] ) || empty( $cat[ $k ]['preview']['heroBg'] ) || empty( $cat[ $k ]['preview']['accent'] ) ) {
		$cat_ok = false;
	}
}
$assert( 'TC-SN-16', 'All 4 presets include complete live-preview simulator palettes', $cat_ok );

// TC-SN-17: Centralized christmas-2026 campaign ID & Asia/Ho_Chi_Minh timezone defaults + sanitization
$def2 = SeasonalSettings::defaults();
$bad_tz = SeasonalSettings::sanitize( array( 'campaign_id' => 'Christmas 2026!!', 'timezone' => 'Mars/Olympus_Mons' ) );
$assert(
	'TC-SN-17',
	'Campaign defaults to christmas-2026 & Asia/Ho_Chi_Minh with strict TZ validation',
	'christmas-2026' === $def2['campaign_id']
	&& 'Asia/Ho_Chi_Minh' === $def2['timezone']
	&& 'christmas2026' === $bad_tz['campaign_id']
	&& 'Asia/Ho_Chi_Minh' === $bad_tz['timezone']
);

// TC-SN-18: Exact Christmas 2026 window (2026-11-22 -> 2026-12-26 inclusive, auto-off on 2026-12-27)
SeasonalSettings::save( array(
	'campaign_id'      => 'christmas-2026',
	'timezone'         => 'Asia/Ho_Chi_Minh',
	'status'           => 'active',
	'preset'           => 'nordic',
	'schedule_enabled' => true,
	'start_date'       => '2026-11-22',
	'end_date'         => '2026-12-26',
) );
$d_before = SeasonalSettings::get_effective_state( false, '2026-11-21', false );
$d_start  = SeasonalSettings::get_effective_state( false, '2026-11-22', false );
$d_end    = SeasonalSettings::get_effective_state( false, '2026-12-26', false );
$d_after  = SeasonalSettings::get_effective_state( false, '2026-12-27', false );
$assert(
	'TC-SN-18',
	'christmas-2026 window active [2026-11-22..2026-12-26] & auto-reverts 2026-12-27',
	false === $d_before['active']
	&& true === $d_start['active']
	&& true === $d_end['active']
	&& false === $d_after['active']
	&& 'scheduled_expired' === $d_after['mode']
);

// TC-SN-19: Winter Atelier 2026 Nordic palette carries exact brand hexes
$nordic_swatches = $cat['nordic']['swatches'] ?? array();
$assert(
	'TC-SN-19',
	'Winter Atelier Nordic preset carries exact Ivory/Pine/Brass/Berry/Ice tokens',
	in_array( '#F4F0E8', $nordic_swatches, true )
	&& in_array( '#183B36', $nordic_swatches, true )
	&& in_array( '#BD9A62', $nordic_swatches, true )
	&& in_array( '#A9443F', $nordic_swatches, true )
	&& in_array( '#D9E2E6', $nordic_swatches, true )
);

// TC-SN-20: Localized payload exposes campaignId, timezone, heroScopedSnow & astroImageUrl
$mock_styles = $mock_scripts = $mock_localized = array();
SeasonalSettings::save( array(
	'campaign_id'      => 'christmas-2026',
	'timezone'         => 'Asia/Ho_Chi_Minh',
	'status'           => 'active',
	'preset'           => 'nordic',
	'schedule_enabled' => false,
) );
SeasonalModule::enqueue_frontend_assets();
$loc2 = $mock_localized['despace-noel']['DESPACE_NOEL'] ?? array();
$assert(
	'TC-SN-20',
	'Localized DESPACE_NOEL carries campaignId, timezone, heroScopedSnow & astroImageUrl',
	'christmas-2026' === ( $loc2['campaignId'] ?? '' )
	&& 'Asia/Ho_Chi_Minh' === ( $loc2['timezone'] ?? '' )
	&& true === ( $loc2['heroScopedSnow'] ?? false )
	&& false !== strpos( (string) ( $loc2['astroImageUrl'] ?? '' ), 'astro-winter-atelier.webp' )
);

echo "\n========================================================================\n";
printf( "SUMMARY: %d Total | %d Passed | %d Failed\n", $passed + $failed, $passed, $failed );
echo "========================================================================\n";
exit( $failed > 0 ? 1 : 0 );
