<?php
/**
 * Fired during plugin activation.
 *
 * @package DeSpace_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DeSpace_Activator {

	/**
	 * Run on plugin activation.
	 */
	public static function activate() {
		self::create_tables();
		self::set_default_options();
	}

	/**
	 * Create custom tables (such as inventory logs).
	 * Adheres strictly to Section 6.3 of DeSpace SADD v1.2.
	 */
	public static function create_tables() {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'despace_inventory_logs';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			log_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			product_id bigint(20) unsigned NOT NULL,
			variation_id bigint(20) unsigned NULL,
			order_id bigint(20) unsigned NULL,
			actor_user_id bigint(20) unsigned NULL,
			event_type varchar(24) NOT NULL,
			event_key varchar(100) NOT NULL,
			quantity_delta int NOT NULL,
			stock_after int NULL,
			note text NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (log_id),
			UNIQUE KEY event_key (event_key),
			KEY product_variation (product_id, variation_id),
			KEY order_id (order_id),
			KEY created_at (created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'despace_db_version', '1.2.0' );
	}

	/**
	 * Set default options if needed.
	 */
	private static function set_default_options() {
		if ( ! get_option( 'despace_installed_at' ) ) {
			update_option( 'despace_installed_at', current_time( 'mysql' ) );
		}
	}
}
