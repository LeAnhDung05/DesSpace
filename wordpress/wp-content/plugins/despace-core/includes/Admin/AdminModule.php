<?php
/**
 * DeSpace Admin Module (Module 08).
 *
 * Provides central executive administration, asset recovery tools,
 * 3D/AR pipeline overview, and RBAC matrix visualization.
 *
 * @package DeSpace\Admin
 */

namespace DeSpace\Admin;

defined( 'ABSPATH' ) || exit;

class AdminModule {

	public static function init(): void {
		// Registered and coordinated via despace-store-admin and DeSpace Core.
	}

	public static function get_status(): array {
		global $wpdb;

		$uploads_dir = wp_upload_dir()['basedir'];
		$has_3d      = is_dir( $uploads_dir . '/3d' );
		$has_catalog = is_dir( $uploads_dir . '/despace-catalog' ) || is_dir( $uploads_dir . '/furniva-real' );

		// Count models
		$models_count = 0;
		if ( $has_3d ) {
			$subdirs = glob( $uploads_dir . '/3d/*', GLOB_ONLYDIR );
			$models_count = $subdirs ? count( $subdirs ) : 0;
		}

		$product_count = wp_count_posts( 'product' )->publish ?? 0;
		$log_table     = $wpdb->prefix . 'despace_inventory_logs';
		$log_count     = $wpdb->get_var( "SHOW TABLES LIKE '$log_table'" ) ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM $log_table" ) : 0;

		return array(
			'products'     => (int) $product_count,
			'has_3d'       => $has_3d,
			'models_count' => $models_count,
			'has_catalog'  => $has_catalog,
			'audit_logs'   => $log_count,
		);
	}
}
