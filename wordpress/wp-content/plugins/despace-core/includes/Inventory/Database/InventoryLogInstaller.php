<?php
/**
 * DeSpace Inventory Log Table Installer / Migrator.
 *
 * @package DeSpace\Inventory\Database
 */

namespace DeSpace\Inventory\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Class InventoryLogInstaller
 *
 * Responsible for creating and updating the despace_inventory_logs table schema
 * using WordPress dbDelta().
 */
class InventoryLogInstaller {

	/**
	 * Current schema version.
	 */
	const DB_VERSION = '1.0.0';

	/**
	 * Option name storing installed schema version.
	 */
	const OPTION_NAME = 'despace_inventory_logs_db_version';

	/**
	 * Base table name (without prefix).
	 */
	const TABLE_NAME = 'despace_inventory_logs';

	/**
	 * Get the fully prefixed table name.
	 *
	 * @return string
	 */
	public static function get_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_NAME;
	}

	/**
	 * Get the current schema version constant.
	 *
	 * @return string
	 */
	public static function get_schema_version(): string {
		return self::DB_VERSION;
	}

	/**
	 * Run the migration if needed.
	 *
	 * @param bool $force Force re-run migration even if version matches.
	 * @return bool True if migration was executed and successful, false if skipped or failed.
	 */
	public static function install( bool $force = false ): bool {
		$installed_version = get_option( self::OPTION_NAME, '0.0.0' );

		if ( ! $force && version_compare( $installed_version, self::DB_VERSION, '>=' ) ) {
			return true;
		}

		$created = self::create_table();

		if ( $created ) {
			update_option( self::OPTION_NAME, self::DB_VERSION );
			return true;
		}

		return false;
	}

	/**
	 * Create or upgrade the inventory logs table using dbDelta.
	 *
	 * @return bool True if table exists after migration.
	 */
	public static function create_table(): bool {
		global $wpdb;

		$table_name      = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		// Notice: dbDelta requires strict syntax rules:
		// - Two spaces after PRIMARY KEY
		// - Each column and key on its own line
		// - Lowercase field types
		// - No quotes around field or table names
		$sql = "CREATE TABLE {$table_name} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  event_key char(64) NOT NULL,
  event_type varchar(40) NOT NULL,
  event_source varchar(40) NOT NULL,
  product_id bigint(20) unsigned NOT NULL,
  variation_id bigint(20) unsigned DEFAULT NULL,
  order_id bigint(20) unsigned DEFAULT NULL,
  order_item_id bigint(20) unsigned DEFAULT NULL,
  refund_id bigint(20) unsigned DEFAULT NULL,
  actor_user_id bigint(20) unsigned DEFAULT NULL,
  quantity_before decimal(20,6) DEFAULT NULL,
  quantity_delta decimal(20,6) NOT NULL,
  stock_after decimal(20,6) DEFAULT NULL,
  created_at datetime NOT NULL,
  context_json longtext DEFAULT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY event_key (event_key),
  KEY product_id (product_id),
  KEY variation_id (variation_id),
  KEY order_id (order_id),
  KEY created_at (created_at),
  KEY event_type_created_at (event_type, created_at)
) {$charset_collate};";

		if ( ! function_exists( 'dbDelta' ) ) {
			$upgrade_path = ABSPATH . 'wp-admin/includes/upgrade.php';
			if ( file_exists( $upgrade_path ) ) {
				require_once $upgrade_path;
			} else {
				return false;
			}
		}

		dbDelta( $sql );

		return self::verify_table_exists();
	}

	/**
	 * Verify that the table exists in the database.
	 *
	 * @return bool
	 */
	public static function verify_table_exists(): bool {
		global $wpdb;
		$table_name = self::get_table_name();
		$query      = $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name );
		return $wpdb->get_var( $query ) === $table_name;
	}
}

