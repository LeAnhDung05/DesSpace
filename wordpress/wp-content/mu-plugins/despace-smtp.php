<?php
/**
 * Plugin Name: DeSpace Mailpit SMTP
 * Description: Routes WordPress and WooCommerce transactional emails to Mailpit in non-production environments only.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'wp_mail_from', static function ( $from ) {
	return 'noreply@despace.vn';
} );

add_filter( 'wp_mail_from_name', static function ( $name ) {
	$env = function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production';
	return 'production' === $env ? 'DeSpace' : 'DeSpace Staging';
} );

add_action( 'phpmailer_init', static function ( $phpmailer ) {
	$env = function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production';
	if ( 'production' === $env ) {
		return;
	}

	static $resolved_host = null;
	$mailpit_host         = getenv( 'DESPACE_MAILPIT_HOST' ) ?: 'despace-mailpit';
	$mailpit_port         = (int) ( getenv( 'DESPACE_MAILPIT_PORT' ) ?: 1025 );

	if ( null === $resolved_host ) {
		$is_ip         = (bool) filter_var( $mailpit_host, FILTER_VALIDATE_IP );
		$resolved_host = ( $is_ip || gethostbyname( $mailpit_host ) !== $mailpit_host ) ? $mailpit_host : '';
	}

	if ( '' === $resolved_host ) {
		return;
	}

	$phpmailer->isSMTP();
	$phpmailer->Host       = $resolved_host;
	$phpmailer->Port       = $mailpit_port;
	$phpmailer->SMTPAuth   = false;
	$phpmailer->SMTPSecure = '';
} );
