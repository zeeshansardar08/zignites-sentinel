<?php
/**
 * Focused tests for Sentinel operation locking.
 */

require_once __DIR__ . '/bootstrap.php';

use Zignites\Sentinel\Core\OperationLock;

function znts_test_operation_lock_blocks_overlapping_operations() {
	$GLOBALS['znts_test_options'] = array();

	$lock_service = new OperationLock();
	$first        = $lock_service->acquire( 'restore', 'user:1', 300 );
	$second       = $lock_service->acquire( 'snapshot', 'user:2', 300 );

	znts_assert_true( ! empty( $first['acquired'] ), 'First operation should acquire the lock.' );
	znts_assert_true( empty( $second['acquired'] ), 'Second operation should be blocked while a lock is active.' );
	znts_assert_same( 'restore', $second['lock']['operation'], 'Blocked response should expose the active operation.' );

	$lock_service->release( $first['lock'] );
	$third = $lock_service->acquire( 'snapshot', 'user:2', 300 );

	znts_assert_true( ! empty( $third['acquired'] ), 'A new operation should acquire the lock after release.' );
}

function znts_test_operation_lock_recovers_stale_locks() {
	$GLOBALS['znts_test_options'] = array();

	update_option(
		ZNTS_OPTION_OPERATION_LOCK,
		array(
			'token'      => 'stale-token',
			'operation'  => 'cleanup',
			'owner'      => 'cron',
			'acquired_at'=> time() - 3600,
			'expires_at' => time() - 60,
			'timeout'    => 300,
		),
		false
	);

	$lock_service = new OperationLock();
	$result       = $lock_service->acquire( 'restore', 'user:3', 300 );

	znts_assert_true( ! empty( $result['acquired'] ), 'Stale locks should be cleared before acquiring a new lock.' );
	znts_assert_same( 'restore', $result['lock']['operation'], 'Recovered lock should record the new operation.' );
}
