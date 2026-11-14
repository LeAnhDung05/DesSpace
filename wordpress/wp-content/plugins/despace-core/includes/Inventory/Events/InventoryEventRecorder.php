<?php
/**
 * Idempotent Inventory Event Recorder.
 *
 * @package DeSpace\Inventory\Events
 */

namespace DeSpace\Inventory\Events;

use DeSpace\Inventory\Database\InventoryLogInstaller;

defined( 'ABSPATH' ) || exit;

/**
 * Class InventoryEventRecorder
 *
 * Responsible for inserting validated events into despace_inventory_logs
 * with idempotency protection via UNIQUE event_key.
 */
class InventoryEventRecorder {

	/**
	 * Record a single inventory event idempotently.
	 *
	 * @param InventoryEvent $event
	 * @return int|false Inserted row ID on success, false if duplicate or invalid.
	 */
	public static function record( InventoryEvent $event ) {
		global $wpdb;

		// 1. Validation check.
		if ( ! $event->is_valid() ) {
			return false;
		}

		$table_name = InventoryLogInstaller::get_table_name();
		$data       = $event->to_db_array();

		$format = array(
			'%s', // event_key
			'%s', // event_type
			'%s', // event_source
			'%d', // product_id
			$data['variation_id'] !== null ? '%d' : null,
			$data['order_id'] !== null ? '%d' : null,
			$data['order_item_id'] !== null ? '%d' : null,
			$data['refund_id'] !== null ? '%d' : null,
			$data['actor_user_id'] !== null ? '%d' : null,
			$data['quantity_before'] !== null ? '%f' : null,
			'%f', // quantity_delta
			$data['stock_after'] !== null ? '%f' : null,
			'%s', // created_at
			$data['context_json'] !== null ? '%s' : null,
		);

		// Filter out null formats and corresponding data.
		$insert_data   = array();
		$insert_format = array();
		$keys          = array_keys( $data );

		for ( $i = 0; $i < count( $keys ); $i++ ) {
			$k = $keys[ $i ];
			if ( null !== $data[ $k ] ) {
				$insert_data[ $k ] = $data[ $k ];
				$insert_format[]   = $format[ $i ];
			}
		}

		// Suppress database errors temporarily to handle duplicate key cleanly.
		$suppress = $wpdb->suppress_errors( true );

		$result = $wpdb->insert( $table_name, $insert_data, $insert_format );

		$last_error = $wpdb->last_error;
		$wpdb->suppress_errors( $suppress );

		if ( false === $result ) {
			// Check if duplicate key violation (MySQL error 1062).
			if ( false !== stripos( $last_error, 'Duplicate entry' ) || false !== stripos( $last_error, 'event_key' ) ) {
				// Idempotent duplicate: event was already recorded previously, safely ignore.
				return false;
			}

			// For any other unexpected DB errors, log for diagnosis without crashing checkout/admin flow.
			self::log_error( 'Failed to record inventory log: ' . $last_error, array( 'event_key' => $data['event_key'] ) );
			return false;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Safe internal error logger.
	 *
	 * @param string $message
	 * @param array $context
	 */
	private static function log_error( string $message, array $context = array() ): void {
		if ( function_exists( 'wc_get_logger' ) ) {
			$logger = wc_get_logger();
			$logger->error( $message, array_merge( array( 'source' => 'despace-inventory' ), $context ) );
		} else {
			error_log( '[DeSpace Inventory] ' . $message . ' ' . wp_json_encode( $context ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}
}

