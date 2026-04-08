<?php
/**
 * Focused tests for event log CSV export row formatting.
 */

require_once __DIR__ . '/bootstrap.php';

use Zignites\Sentinel\Admin\EventLogPresenter;

function znts_test_event_log_export_row_extracts_context_and_journal_fields() {
	$presenter = new EventLogPresenter();

	$row = $presenter->build_export_row(
		array(
			'id'         => 14,
			'created_at' => '2025-01-01 12:00:00',
			'severity'   => 'warning',
			'source'     => 'restore-execution-journal',
			'event_type' => 'restore_item_written',
			'message'    => 'Payload written.',
			'context'    => wp_json_encode(
				array(
					'snapshot_id' => 77,
					'run_id'      => 'run-77',
					'entry'       => array(
						'scope'  => 'item',
						'phase'  => 'payload_written',
						'status' => 'pass',
					),
				)
			),
		),
		array(
			'snapshot_id' => 77,
			'run_id'      => 'run-77',
			'entry'       => array(
				'scope'  => 'item',
				'phase'  => 'payload_written',
				'status' => 'pass',
			),
		)
	);

	znts_assert_same( 14, $row[0], 'CSV export row should include the log ID.' );
	znts_assert_same( '2025-01-01 12:00:00', $row[1], 'CSV export row should include the log timestamp.' );
	znts_assert_same( 'warning', $row[2], 'CSV export row should include severity.' );
	znts_assert_same( 'restore-execution-journal', $row[3], 'CSV export row should include source.' );
	znts_assert_same( 'restore_item_written', $row[4], 'CSV export row should include event type.' );
	znts_assert_same( 'Payload written.', $row[5], 'CSV export row should include message.' );
	znts_assert_same( 77, $row[6], 'CSV export row should expose snapshot ID from context.' );
	znts_assert_same( 'run-77', $row[7], 'CSV export row should expose run ID from context.' );
	znts_assert_same( 'item', $row[8], 'CSV export row should expose journal scope from nested context.' );
	znts_assert_same( 'payload_written', $row[9], 'CSV export row should expose journal phase from nested context.' );
	znts_assert_same( 'pass', $row[10], 'CSV export row should expose journal status from nested context.' );
	znts_assert_true( false !== strpos( $row[11], '"snapshot_id":77' ), 'CSV export row should preserve the encoded JSON context.' );
}

function znts_test_event_log_export_row_handles_invalid_context_payloads() {
	$presenter = new EventLogPresenter();

	$row = $presenter->build_export_row(
		array(
			'id'         => 15,
			'created_at' => '2025-01-01 13:00:00',
			'severity'   => 'info',
			'source'     => 'snapshot-audit',
			'event_type' => 'snapshot_audit_report_downloaded',
			'message'    => 'Snapshot audit report downloaded.',
			'context'    => '{invalid-json',
		),
		array()
	);

	znts_assert_same( 0, $row[6], 'Invalid context should yield a zero snapshot ID.' );
	znts_assert_same( '', $row[7], 'Invalid context should yield an empty run ID.' );
	znts_assert_same( '', $row[8], 'Invalid context should yield an empty journal scope.' );
	znts_assert_same( '', $row[11], 'Invalid context should yield an empty context JSON cell.' );
}
