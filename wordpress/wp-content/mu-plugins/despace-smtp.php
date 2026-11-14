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
	$phpmailer->isSMTP();
	$phpmailer->Host       = 'despace-mailpit';
	$phpmailer->Port       = 1025;
	$phpmailer->SMTPAuth   = false;
	$phpmailer->SMTPSecure = '';
} );
