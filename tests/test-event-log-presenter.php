<?php
/**
 * Focused tests for read-only Event Logs presentation helpers.
 */

require_once __DIR__ . '/bootstrap.php';

use Zignites\Sentinel\Admin\EventLogPresenter;

function znts_test_event_log_presenter_builds_summary_tiles_and_filter_state() {
	$presenter = new EventLogPresenter();

	$payload = $presenter->build_view_payload(
		array(
			array( 'severity' => 'warning' ),
			array( 'severity' => 'warning' ),
			array( 'severity' => 'critical' ),
		),
		array(
			'severity'    => 'warning',
			'source'      => 'restore-execution-journal',
			'run_id'      => '',
			'snapshot_id' => 44,
			'search'      => 'payload',
		),
		array(
			array( 'run_id' => 'run-1' ),
		),
		array(
			array( 'id' => 91 ),
			array( 'id' => 92 ),
		),
		array(),
		array(
			'total_logs' => 27,
		)
	);

	znts_assert_same( 4, $payload['active_filter_count'], 'Event log presenter should count non-empty filters only.' );
	znts_assert_same( 2, $payload['severity_counts']['warning'], 'Event log presenter should count warning rows.' );
	znts_assert_same( 1, $payload['severity_counts']['critical'], 'Event log presenter should count critical rows.' );
	znts_assert_same( 'zignites-sentinel-event-logs', $payload['base_args']['page'], 'Event log presenter should build stable base args for filtered links.' );
	znts_assert_same( '27', $payload['summary_tiles'][0]['value'], 'Event log presenter should expose the total matching event count in the summary tiles.' );
	znts_assert_same( '2', $payload['summary_tiles'][3]['value'], 'Event log presenter should expose the operational event count in the summary tiles.' );
}

function znts_test_event_log_presenter_decorates_run_summary_and_journal_status_pills() {
	$presenter = new EventLogPresenter();

	$run_summaries = $presenter->decorate_run_summaries(
		array(
			array(
				'run_id'       => 'run-2',
				'status_badge' => 'partial',
			),
			array(
				'run_id'       => 'run-3',
				'status_badge' => 'blocked',
			),
		)
	);
	$run_journal   = $presenter->decorate_run_journal(
		array(
			'entries' => array(
				array(
					'status' => 'fail',
				),
				array(
					'status' => 'pass',
				),
			),
		)
	);

	znts_assert_same( 'warning', $run_summaries[0]['status_pill'], 'Event log presenter should map partial run summaries to the warning pill.' );
	znts_assert_same( 'Partial', $run_summaries[0]['status_label'], 'Event log presenter should format readable run summary status labels.' );
	znts_assert_same( 'critical', $run_summaries[1]['status_pill'], 'Event log presenter should map blocked run summaries to the critical pill.' );
	znts_assert_same( 'critical', $run_journal['entries'][0]['status_pill'], 'Event log presenter should map failed journal entries to the critical pill.' );
	znts_assert_same( 'Pass', $run_journal['entries'][1]['status_label'], 'Event log presenter should format readable journal status labels.' );
}
