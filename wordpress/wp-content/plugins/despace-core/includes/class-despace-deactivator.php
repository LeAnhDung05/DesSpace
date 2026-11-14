<?php
/**
 * Fired during plugin deactivation.
 *
 * @package DeSpace_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DeSpace_Deactivator {

	/**
	 * Run on plugin deactivation.
	 */
	public static function deactivate() {
		// Clean up scheduled cron jobs if any, but preserve inventory log table.
	}
}
