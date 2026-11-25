<?php
/**
 * Standalone Test Runner for Product3DTest.
 */

require_once '/var/www/html/wp-load.php';
require_once __DIR__ . '/Product3DTest.php';

use DeSpace\Tests\Product3DTest;

echo "=== Running DeSpace Product 3D (Issue #6) Test Suite ===\n";

$test = new Product3DTest();
$results = $test->run();

$all_passed = true;
foreach ( $results as $name => $passed ) {
	$status = $passed ? 'PASS' : 'FAIL';
	echo sprintf( "  [%s] %s\n", $status, $name );
	if ( ! $passed ) {
		$all_passed = false;
	}
}

echo "========================================================\n";
if ( $all_passed ) {
	echo sprintf( "SUCCESS: All %d/%d tests passed successfully!\n", count( $results ), count( $results ) );
	exit( 0 );
} else {
	echo "FAILURE: One or more tests failed!\n";
	exit( 1 );
}
