<?php
/**
 * Plugin Name: Dynamic Tunnel & Host URL Support for DeSpace
 * Description: Enables dynamic domain support for local development tunnels (explicitly opt-in).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Security gate: NEVER run in production. Require local/development environment AND explicit opt-in.
$despace_env_type = function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production';
$despace_tunnel_enabled = (
	( defined( 'DESPACE_ENABLE_DYNAMIC_TUNNEL' ) && true === DESPACE_ENABLE_DYNAMIC_TUNNEL ) ||
	'1' === (string) getenv( 'DESPACE_ENABLE_DYNAMIC_TUNNEL' ) ||
	'true' === strtolower( (string) getenv( 'DESPACE_ENABLE_DYNAMIC_TUNNEL' ) )
);

if ( ! in_array( $despace_env_type, array( 'local', 'development' ), true ) || ! $despace_tunnel_enabled ) {
	return;
}

if ( ! empty( $_SERVER['HTTP_HOST'] ) ) {
	$raw_host  = sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) );
	$http_host = preg_replace( '/[^a-zA-Z0-9.\-:\[\]]/', '', $raw_host );

	// Explicitly configured tunnel host takes precedence over wildcard tunnel domains.
	$configured_tunnel = defined( 'DESPACE_TUNNEL_HOST' )
		? (string) DESPACE_TUNNEL_HOST
		: (string) ( getenv( 'DESPACE_TUNNEL_HOST' ) ?: '' );
	$configured_tunnel = preg_replace( '/[^a-zA-Z0-9.\-:\[\]]/', '', trim( $configured_tunnel ) );

	$is_local_or_lan = (bool) preg_match(
		'/^(?:localhost|127\.0\.0\.1|\[::1\]|192\.168\.\d{1,3}\.\d{1,3}|10\.\d{1,3}\.\d{1,3}\.\d{1,3}|172\.(?:1[6-9]|2\d|3[01])\.\d{1,3}\.\d{1,3}|[a-z0-9\-]+\.local)(?::\d{1,5})?$/i',
		$http_host
	);

	$is_allowed_tunnel_host = $is_local_or_lan || (
		'' !== $configured_tunnel &&
		0 === strcasecmp( $http_host, $configured_tunnel )
	);

	if ( ! $is_allowed_tunnel_host ) {
		return;
	}

	// Detect SSL / reverse proxy only on verified local/explicit tunnel hosts.
	$is_ssl = (
		( isset( $_SERVER['HTTPS'] ) && ( 'on' === $_SERVER['HTTPS'] || 1 == $_SERVER['HTTPS'] ) ) ||
		( isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) && 'https' === strtolower( (string) $_SERVER['HTTP_X_FORWARDED_PROTO'] ) ) ||
		( isset( $_SERVER['HTTP_X_FORWARDED_SSL'] ) && 'on' === strtolower( (string) $_SERVER['HTTP_X_FORWARDED_SSL'] ) ) ||
		( isset( $_SERVER['SERVER_PORT'] ) && 443 == $_SERVER['SERVER_PORT'] )
	);

	if ( $is_ssl ) {
		$_SERVER['HTTPS']       = 'on';
		$_SERVER['SERVER_PORT'] = '443';
	}

	$scheme           = $is_ssl ? 'https://' : 'http://';
	$current_site_url = $scheme . $http_host;

	add_filter( 'pre_option_siteurl', static function () use ( $current_site_url ) {
		return $current_site_url;
	} );

	add_filter( 'pre_option_home', static function () use ( $current_site_url ) {
		return $current_site_url;
	} );

	add_filter( 'wp_redirect', static function ( $location ) use ( $current_site_url, $http_host ) {
		if ( ! is_string( $location ) || empty( $location ) ) {
			return $location;
		}
		$location = preg_replace( '#https?://localhost(?::\d+)?#i', $current_site_url, $location );
		$location = preg_replace( '#//localhost(?::\d+)?#i', '//' . $http_host, $location );
		return $location;
	}, 999 );

	add_filter( 'wp_resource_hints', static function ( $hints, $relation_type ) {
		if ( ! is_array( $hints ) ) {
			return $hints;
		}
		return array_values( array_filter( $hints, static function ( $hint ) {
			return is_string( $hint ) && false === strpos( $hint, 'localhost' );
		} ) );
	}, 999, 2 );

	// Output buffering only when accessing from an explicit tunnel/LAN host (not localhost) on text/HTML responses.
	if ( false === strpos( $http_host, 'localhost' ) && false === strpos( $http_host, '127.0.0.1' ) ) {
		$json_current = str_replace( '/', '\/', $current_site_url );
		add_action( 'template_redirect', static function () use ( $current_site_url, $http_host, $json_current ) {
			ob_start( static function ( $buffer ) use ( $current_site_url, $http_host, $json_current ) {
				if ( ! is_string( $buffer ) || '' === $buffer ) {
					return $buffer;
				}
				foreach ( headers_list() as $header ) {
					if ( 0 === stripos( $header, 'Content-Type:' ) && false === stripos( $header, 'text/html' ) && false === stripos( $header, 'application/json' ) ) {
						return $buffer;
					}
				}
				$buffer = preg_replace( '/<link\s+[^>]*href=["\']\/\/(?:localhost(?::\d+)?|127\.0\.0\.1(?::\d+)?)["\'][^>]*>\s*/i', '', $buffer );
				$buffer = preg_replace( '/<link\s+[^>]*rel=["\']dns-prefetch["\'][^>]*href=["\'][^"\']*localhost[^"\']*["\'][^>]*>\s*/i', '', $buffer );
				$buffer = preg_replace( '/<link\s+[^>]*href=["\'][^"\']*localhost[^"\']*["\'][^>]*rel=["\']dns-prefetch["\'][^>]*>\s*/i', '', $buffer );

				$buffer = preg_replace( '#https?://localhost(?::\d+)?#i', $current_site_url, $buffer );
				$buffer = preg_replace( '#(?<!:)//localhost(?::\d+)?#i', '//' . $http_host, $buffer );
				$buffer = preg_replace( '#https?:\\\\/\\\\/localhost(?:\\\\:\d+)?#i', $json_current, $buffer );
				$buffer = preg_replace( '#\\\\/\\\\/localhost(?:\\\\:\d+)?#i', '\/\/' . $http_host, $buffer );

				return $buffer;
			} );
		}, 1 );
	}
}
