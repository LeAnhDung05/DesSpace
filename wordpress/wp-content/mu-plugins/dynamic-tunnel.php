<?php
/**
 * Plugin Name: Dynamic Tunnel & Host URL Support for Furniva
 * Description: Enables dynamic domain support for Cloudflare Tunnel / Ngrok / Localhost.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! empty( $_SERVER['HTTP_HOST'] ) ) {
	$http_host = $_SERVER['HTTP_HOST'];

	// Detect SSL / Cloudflare reverse proxy
	$is_ssl = (
		( isset( $_SERVER['HTTPS'] ) && ( 'on' === $_SERVER['HTTPS'] || 1 == $_SERVER['HTTPS'] ) ) ||
		( isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) && 'https' === $_SERVER['HTTP_X_FORWARDED_PROTO'] ) ||
		( isset( $_SERVER['HTTP_X_FORWARDED_SSL'] ) && 'on' === $_SERVER['HTTP_X_FORWARDED_SSL'] ) ||
		( isset( $_SERVER['SERVER_PORT'] ) && 443 == $_SERVER['SERVER_PORT'] )
	);

	if ( $is_ssl ) {
		$_SERVER['HTTPS'] = 'on';
		$_SERVER['SERVER_PORT'] = '443';
	}

	$scheme = $is_ssl ? 'https://' : 'http://';
	$current_site_url = $scheme . $http_host;

	// Override dynamic siteurl and home options
	add_filter( 'pre_option_siteurl', function() use ( $current_site_url ) {
		return $current_site_url;
	} );

	add_filter( 'pre_option_home', function() use ( $current_site_url ) {
		return $current_site_url;
	} );

	// Filter redirects to keep users on the tunnel host
	add_filter( 'wp_redirect', function( $location ) use ( $current_site_url, $http_host ) {
		if ( ! is_string( $location ) || empty( $location ) ) {
			return $location;
		}
		$location = str_replace( 'https://localhost:8088', $current_site_url, $location );
		$location = str_replace( 'http://localhost:8088', $current_site_url, $location );
		$location = str_replace( '//localhost:8088', '//' . $http_host, $location );
		return $location;
	}, 999 );

	// Remove any localhost from resource hints (dns-prefetch, preconnect)
	add_filter( 'wp_resource_hints', function( $hints, $relation_type ) {
		if ( ! is_array( $hints ) ) {
			return $hints;
		}
		return array_values( array_filter( $hints, function( $hint ) {
			return is_string( $hint ) && strpos( $hint, 'localhost' ) === false;
		} ) );
	}, 999, 2 );

	// Output buffering only when accessing from a tunnel domain (not localhost:8088)
	if ( strpos( $http_host, 'localhost' ) === false ) {
		$json_current = str_replace( '/', '\/', $current_site_url );
		add_action( 'template_redirect', function() use ( $current_site_url, $http_host, $json_current ) {
			ob_start( function( $buffer ) use ( $current_site_url, $http_host, $json_current ) {
				if ( ! is_string( $buffer ) || empty( $buffer ) ) {
					return $buffer;
				}
				// Remove any residual dns-prefetch to localhost
				$buffer = preg_replace( '/<link\s+[^>]*href=["\']\/\/(?:localhost(?::\d+)?|127\.0\.0\.1(?::\d+)?)["\'][^>]*>\s*/i', '', $buffer );
				$buffer = preg_replace( '/<link\s+[^>]*rel=["\']dns-prefetch["\'][^>]*href=["\'][^"\']*localhost[^"\']*["\'][^>]*>\s*/i', '', $buffer );
				$buffer = preg_replace( '/<link\s+[^>]*href=["\'][^"\']*localhost[^"\']*["\'][^>]*rel=["\']dns-prefetch["\'][^>]*>\s*/i', '', $buffer );

				$buffer = str_replace( 'https://localhost:8088', $current_site_url, $buffer );
				$buffer = str_replace( 'http://localhost:8088', $current_site_url, $buffer );
				$buffer = str_replace( '//localhost:8088', '//' . $http_host, $buffer );
				$buffer = str_replace( 'https://localhost', $current_site_url, $buffer );
				$buffer = str_replace( 'http://localhost', $current_site_url, $buffer );
				$buffer = str_replace( '//localhost', '//' . $http_host, $buffer );
				$buffer = str_replace( 'https:\/\/localhost:8088', $json_current, $buffer );
				$buffer = str_replace( 'http:\/\/localhost:8088', $json_current, $buffer );
				$buffer = str_replace( '\/\/localhost:8088', '\/\/' . $http_host, $buffer );
				$buffer = str_replace( 'https:\/\/localhost', $json_current, $buffer );
				$buffer = str_replace( 'http:\/\/localhost', $json_current, $buffer );
				$buffer = str_replace( '\/\/localhost', '\/\/' . $http_host, $buffer );
				return $buffer;
			} );
		}, 1 );
	}
}
