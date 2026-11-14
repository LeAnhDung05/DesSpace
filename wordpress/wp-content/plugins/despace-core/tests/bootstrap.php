<?php
/**
 * PHPUnit Test Suite Bootstrap for DeSpace Core.
 *
 * @package DeSpace\Tests
 */

namespace DeSpace\Tests;

// Load WordPress environment if available.
if ( file_exists( dirname( __DIR__, 4 ) . '/wp-load.php' ) ) {
	require_once dirname( __DIR__, 4 ) . '/wp-load.php';
} elseif ( file_exists( '/var/www/html/wp-load.php' ) ) {
	require_once '/var/www/html/wp-load.php';
}

if ( ! defined( 'DESPACE_CORE_VERSION' ) ) {
	require_once dirname( __DIR__ ) . '/despace-core.php';
}
