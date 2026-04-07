<?php
/**
 * Focused tests for event log CSV export filename formatting.
 */

require_once __DIR__ . '/bootstrap.php';

use Zignites\Sentinel\Admin\Admin;

function znts_test_event_log_export_filename_reflects_filtered_scope() {
	$reflection = new ReflectionClass( Admin::class );
	$instance   = $reflection->newInstanceWithoutConstructor();
	$method     = $reflection->getMethod( 'build_event_log_export_filename' );
	$method->setAccessible( true );

	$filename = $method->invoke(
		$instance,
		array(
			'source'      => 'restore-execution-journal',
			'run_id'      => 'Run 42 / Blue',
			'snapshot_id' => 205,
		)
	);

	znts_assert_true( 0 === strpos( $filename, 'znts-event-logs-restore-execution-journal-run-42-blue-snapshot-205-' ), 'CSV export filenames should include source, run, and snapshot scope before the timestamp suffix.' );
	znts_assert_true( 1 === preg_match( '/\.csv$/', $filename ), 'CSV export filenames should always end with .csv.' );
}

function znts_test_event_log_export_filename_omits_empty_filters() {
	$reflection = new ReflectionClass( Admin::class );
	$instance   = $reflection->newInstanceWithoutConstructor();
	$method     = $reflection->getMethod( 'build_event_log_export_filename' );
	$method->setAccessible( true );

	$filename = $method->invoke(
		$instance,
		array(
			'source'      => '',
			'run_id'      => '',
			'snapshot_id' => 0,
		)
	);

	znts_assert_true( 0 === strpos( $filename, 'znts-event-logs-' ), 'CSV export filenames should retain the base event-log prefix when no filters are applied.' );
	znts_assert_true( false === strpos( $filename, 'snapshot-' ), 'CSV export filenames should not include a snapshot fragment when no snapshot filter is active.' );
}
