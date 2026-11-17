<?php
/**
 * Plugin Name: DeSpace Mailpit SMTP
 * Description: Routes all WordPress and WooCommerce transactional emails to Mailpit for local staging testing.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'wp_mail_from', function( $from ) {
	return 'noreply@despace.vn';
} );

add_filter( 'wp_mail_from_name', function( $name ) {
	return 'DeSpace Staging';
} );

add_action( 'phpmailer_init', function( $phpmailer ) {
	$mailpit_host = getenv( 'DESPACE_MAILPIT_HOST' ) ?: 'despace-mailpit';
	$mailpit_port = (int) ( getenv( 'DESPACE_MAILPIT_PORT' ) ?: 1025 );

	// Only route through Mailpit when its container hostname resolves in the current environment.
	if ( gethostbyname( $mailpit_host ) === $mailpit_host && ! filter_var( $mailpit_host, FILTER_VALIDATE_IP ) ) {
		return;
	}

	$phpmailer->isSMTP();
	$phpmailer->Host       = $mailpit_host;
	$phpmailer->Port       = $mailpit_port;
	$phpmailer->SMTPAuth   = false;
	$phpmailer->SMTPSecure = '';
} );
