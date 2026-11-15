<?php
/**
 * DeSpace M4 — Live Integration Test Suite.
 *
 * Runs real integration tests against the active WordPress & WooCommerce installation.
 * Execute via CLI: php tests/test-inventory-integration-live.php
 *
 * @package DeSpace\Tests
 */

// Find active WordPress environment.
$possible_paths = array(
	'C:/xampp/htdocs/despace/wp-load.php',
	__DIR__ . '/../wordpress/wp-load.php',
);

$wp_load = null;
foreach ( $possible_paths as $path ) {
	if ( file_exists( $path ) ) {
		$wp_load = $path;
		break;
	}
}

if ( ! $wp_load ) {
	fwrite( STDERR, "Error: WordPress wp-load.php not found. Please verify local environment.\n" );
	exit( 1 );
}

require_once $wp_load;

if ( ! class_exists( 'WooCommerce' ) ) {
	fwrite( STDERR, "Error: WooCommerce is not active.\n" );
	exit( 1 );
}

// Ensure M4 autoloader is active.
require_once __DIR__ . '/../wordpress/wp-content/plugins/despace-core/includes/Inventory/InventoryModule.php';
\DeSpace\Inventory\InventoryModule::init();

use DeSpace\Inventory\Database\InventoryLogInstaller;
use DeSpace\Inventory\Events\InventoryEvent;
use DeSpace\Inventory\Events\InventoryEventRecorder;
use DeSpace\Inventory\Audit\InventoryReconciler;
use DeSpace\Inventory\Admin\InventoryExportService;
use DeSpace\Inventory\REST\InventoryLogsController;

class LiveIntegrationTestRunner {

	private array $results = array();
	private ?int $test_product_id = null;

	public function run(): void {
		try {
			echo "========================================================================\n";
			echo "      DeSpace M4 — LIVE WOOCOMMERCE & MYSQL INTEGRATION TESTS          \n";
			echo "========================================================================\n\n";

			global $wpdb;
			echo "Target DB: " . DB_NAME . " | Table Prefix: " . $wpdb->prefix . "\n";
			echo "WooCommerce Version: " . ( defined( 'WC_VERSION' ) ? WC_VERSION : 'Unknown' ) . "\n\n";

			$this->test_INT01_table_and_schema();
			$this->test_INT02_real_product_initial_stock();
			$this->test_INT03_real_product_stock_reduction();
			$this->test_INT04_real_product_stock_increase();
			$this->test_INT05_zero_delta_no_log();
			$this->test_INT06_live_reconciliation();
			$this->test_INT07_idempotency_duplicate_key();
			$this->test_INT08_csv_formula_injection_defense();
			$this->test_INT09_rest_date_range_validation();
			$this->cleanup();

			$this->print_summary();
		} catch ( \Throwable $e ) {
			$this->cleanup();
			fwrite( STDERR, "\nFATAL EXCEPTION in LiveIntegrationTestRunner: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n" );
			exit( 1 );
		}
	}

	private function assert_test( string $id, string $desc, bool $passed, string $details = '' ): void {
		$status = $passed ? "\033[32mPASS\033[0m" : "\033[31mFAIL\033[0m";
		printf( "[%s] %-50s : %s %s\n", $id, $desc, $status, $details );
		$this->results[ $id ] = $passed;
	}

	private function test_INT01_table_and_schema(): void {
		global $wpdb;
		$table = InventoryLogInstaller::get_table_name();
		$exists = InventoryLogInstaller::verify_table_exists();

		// Check UNIQUE key on event_key.
		$indices = $wpdb->get_results( "SHOW INDEX FROM {$table} WHERE Key_name = 'event_key'" );
		$has_unique = ! empty( $indices ) && 0 === (int) $indices[0]->Non_unique;

		$this->assert_test( 'M4-INT-01', 'Database table & UNIQUE event_key verified', $exists && $has_unique );
	}

	private function test_INT02_real_product_initial_stock(): void {
		$product = new \WC_Product_Simple();
		$product->set_name( 'Test Stool ' . time() );
		$product->set_regular_price( '1500000' );
		$product->set_manage_stock( true );
		$product->set_stock_quantity( 10 );
		$this->test_product_id = $product->save();

		global $wpdb;
		$table = InventoryLogInstaller::get_table_name();
		$log   = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE product_id = %d ORDER BY id DESC LIMIT 1",
			$this->test_product_id
		), ARRAY_A );

		$passed = ! empty( $log ) && 10.0 === (float) $log['quantity_delta'] && 10.0 === (float) $log['stock_after'];
		$this->assert_test( 'M4-INT-02', 'Create product initial stock (10) recorded in DB', $passed );
	}

	private function test_INT03_real_product_stock_reduction(): void {
		$product = wc_get_product( $this->test_product_id );
		$product->set_stock_quantity( 7 );
		$product->save();

		global $wpdb;
		$table = InventoryLogInstaller::get_table_name();
		$log   = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE product_id = %d ORDER BY id DESC LIMIT 1",
			$this->test_product_id
		), ARRAY_A );

		$passed = ! empty( $log ) && -3.0 === (float) $log['quantity_delta'] && 7.0 === (float) $log['stock_after'];
		$this->assert_test( 'M4-INT-03', 'Stock reduction 10 -> 7 recorded (delta -3.0)', $passed );
	}

	private function test_INT04_real_product_stock_increase(): void {
		$product = wc_get_product( $this->test_product_id );
		$product->set_stock_quantity( 12 );
		$product->save();

		global $wpdb;
		$table = InventoryLogInstaller::get_table_name();
		$log   = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE product_id = %d ORDER BY id DESC LIMIT 1",
			$this->test_product_id
		), ARRAY_A );

		$passed = ! empty( $log ) && 5.0 === (float) $log['quantity_delta'] && 12.0 === (float) $log['stock_after'];
		$this->assert_test( 'M4-INT-04', 'Stock increase 7 -> 12 recorded (delta +5.0)', $passed );
	}

	private function test_INT05_zero_delta_no_log(): void {
		global $wpdb;
		$table = InventoryLogInstaller::get_table_name();
		$count_before = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE product_id = %d", $this->test_product_id ) );

		// Save unchanged stock 12 -> 12.
		$product = wc_get_product( $this->test_product_id );
		$product->set_stock_quantity( 12 );
		$product->save();

		$count_after = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE product_id = %d", $this->test_product_id ) );
		$this->assert_test( 'M4-INT-05', 'Zero stock delta (12 -> 12) ignored, no duplicate log', $count_before === $count_after );
	}

	private function test_INT06_live_reconciliation(): void {
		$reconcile = InventoryReconciler::reconcile( $this->test_product_id );
		$passed    = $reconcile['is_reconciled'] === true && $reconcile['total_events'] >= 3;
		$this->assert_test( 'M4-INT-06', 'Stock reconciliation matches formula on live MySQL', $passed );
	}

	private function test_INT07_idempotency_duplicate_key(): void {
		$event_key = hash( 'sha256', 'live_idempotency_test_' . microtime( true ) );

		$event1 = new InventoryEvent( array(
			'event_key'       => $event_key,
			'product_id'      => $this->test_product_id,
			'quantity_delta'  => 1.0,
			'stock_after'     => 13.0,
		) );

		$event2 = new InventoryEvent( array(
			'event_key'       => $event_key,
			'product_id'      => $this->test_product_id,
			'quantity_delta'  => 1.0,
			'stock_after'     => 13.0,
		) );

		$id1 = InventoryEventRecorder::record( $event1 );
		$id2 = InventoryEventRecorder::record( $event2 );

		// id1 should be valid integer insert_id, id2 should be false due to duplicate key suppression.
		$passed = is_int( $id1 ) && false === $id2;
		$this->assert_test( 'M4-INT-07', 'Duplicate event_key safely handled without exception', $passed );
	}

	private function test_INT08_csv_formula_injection_defense(): void {
		$formula1 = '=SUM(A1:A10)';
		$formula2 = '@malicious';
		$formula3 = '+cmd|';
		$escaped1 = InventoryExportService::escape_csv_cell( $formula1 );
		$escaped2 = InventoryExportService::escape_csv_cell( $formula2 );
		$escaped3 = InventoryExportService::escape_csv_cell( $formula3 );

		$passed = strpos( $escaped1, "'=" ) === 0 && strpos( $escaped2, "'@" ) === 0 && strpos( $escaped3, "'+cmd" ) === 0;
		$this->assert_test( 'M4-INT-08', 'CSV export formula injection properly escaped', $passed );
	}

	private function test_INT09_rest_date_range_validation(): void {
		$req = new \WP_REST_Request( 'GET', '/despace/v1/inventory/logs' );
		$req->set_param( 'date_from', '2026-12-31' );
		$req->set_param( 'date_to', '2026-01-01' );

		// Simulate authorized user.
		wp_set_current_user( 1 );
		$response = InventoryLogsController::get_items( $req );

		$passed = is_wp_error( $response ) && 'despace_invalid_date_range' === $response->get_error_code();
		$this->assert_test( 'M4-INT-09', 'REST API rejects invalid date range (from > to)', $passed );
	}

	private function cleanup(): void {
		if ( $this->test_product_id ) {
			wp_delete_post( $this->test_product_id, true );
		}
	}

	private function print_summary(): void {
		$total  = count( $this->results );
		$passed = count( array_filter( $this->results ) );
		$failed = $total - $passed;

		echo "\n========================================================================\n";
		printf( "SUMMARY: %d Live Integration Tests | \033[32m%d Passed\033[0m | \033[31m%d Failed\033[0m\n", $total, $passed, $failed );
		echo "========================================================================\n";

		if ( 0 === $failed && 9 === $total ) {
			echo "\n>>> ALL LIVE INTEGRATION TESTS PASSED ON REAL WOOCOMMERCE & MYSQL! <<<\n\n";
			exit( 0 );
		} else {
			echo "\n>>> LIVE INTEGRATION TESTS FAILED (failures or incomplete count)! <<<\n\n";
			exit( 1 );
		}
	}
}

$runner = new LiveIntegrationTestRunner();
$runner->run();
