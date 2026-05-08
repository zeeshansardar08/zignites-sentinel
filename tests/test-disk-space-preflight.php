<?php
/**
 * Focused tests for disk capacity preflight checks.
 */

require_once __DIR__ . '/bootstrap.php';

use Zignites\Sentinel\Core\DiskSpacePreflight;

function znts_test_disk_space_preflight_blocks_when_required_space_exceeds_available() {
	$preflight = new DiskSpacePreflight();
	$result    = $preflight->check_required_space( __DIR__, PHP_INT_MAX, 'restore' );

	znts_assert_same( 'fail', $result['status'], 'Disk preflight should fail when required bytes exceed available bytes.' );
	znts_assert_same( 'restore', $result['operation'], 'Disk preflight should retain the operation key.' );
	znts_assert_true( $result['required_bytes'] > 0, 'Disk preflight should report required bytes.' );
	znts_assert_true( null !== $result['available_bytes'], 'Disk preflight should report available bytes when disk_free_space works.' );
}

function znts_test_disk_space_preflight_allows_small_requirements() {
	$preflight = new DiskSpacePreflight();
	$result    = $preflight->check_required_space( __DIR__, 1, 'snapshot' );

	znts_assert_true( in_array( $result['status'], array( 'pass', 'warning' ), true ), 'Disk preflight should not fail tiny requirements on a readable directory.' );
	znts_assert_same( 'snapshot', $result['operation'], 'Disk preflight should sanitize operation keys.' );
}
