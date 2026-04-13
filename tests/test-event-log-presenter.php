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
	znts_assert_same( 'Warning', $payload['recent_logs'][0]['severity_label'], 'Event log presenter should decorate recent log rows with readable severity labels.' );
	znts_assert_same( 'warning', $payload['recent_logs'][0]['severity_pill'], 'Event log presenter should preserve warning severity pill variants for recent logs.' );
	znts_assert_same( 'How to use this screen', $payload['guidance_panels'][0]['title'], 'Event log presenter should expose inline guidance panels.' );
	znts_assert_same( 'What this history is for', $payload['positioning_note']['title'], 'Event log presenter should expose product-positioning guidance.' );
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

function znts_test_event_log_presenter_builds_run_outcome_summary() {
	$presenter = new EventLogPresenter();

	$payload = $presenter->build_view_payload(
		array(),
		array(),
		array(),
		array(),
		array(
			'source'  => 'restore-execution-journal',
			'run_id'  => 'run-42',
			'entries' => array(
				array(
					'timestamp' => '2026-04-09 10:00:00',
					'scope'     => 'gate',
					'phase'     => 'confirmation',
					'status'    => 'pass',
				),
				array(
					'timestamp' => '2026-04-09 10:01:30',
					'scope'     => 'plugin',
					'phase'     => 'write_file',
					'status'    => 'fail',
				),
			),
		),
		array()
	);

	znts_assert_same( 'critical', $payload['run_outcome_summary']['badge'], 'Event log presenter should mark failed run journals as critical in the run outcome summary.' );
	znts_assert_same( 'Failed', $payload['run_outcome_summary']['status_label'], 'Event log presenter should expose a readable failed status label in the run outcome summary.' );
	znts_assert_same( '1m 30s', $payload['run_outcome_summary']['duration'], 'Event log presenter should calculate run duration from the first and last journal timestamps.' );
	znts_assert_true( false !== strpos( $payload['run_outcome_summary']['rows'][3]['value'], 'Gate confirmation' ), 'Event log presenter should summarize key actions performed in the run outcome summary.' );
	znts_assert_true( false !== strpos( $payload['run_outcome_summary']['story'], '1 fail' ), 'Event log presenter should narrate pass/fail counts in the run outcome summary story.' );
}

function znts_test_event_log_presenter_builds_first_run_empty_states() {
	$presenter = new EventLogPresenter();

	$payload = $presenter->build_view_payload(
		array(),
		array(),
		array(),
		array(),
		array(),
		array(
			'total_logs' => 0,
		)
	);

	znts_assert_same( 'No event history has been recorded yet.', $payload['empty_state']['title'], 'Event log presenter should expose a first-run empty state when no event history exists.' );
	znts_assert_same( 'No restore or rollback history yet.', $payload['history_empty_state']['title'], 'Event log presenter should expose a restore-history empty state when no run history exists.' );
}

function znts_test_event_log_presenter_builds_snapshot_activity_entries() {
	$presenter = new EventLogPresenter();

	$entry = $presenter->build_snapshot_activity_entry(
		array(
			'id'         => 91,
			'created_at' => '2025-01-06 12:00:00',
			'severity'   => 'warning',
			'source'     => 'restore-execution-journal',
			'event_type' => 'restore_item_written',
			'message'    => 'Payload written.',
		),
		array(
			'run_id' => 'run-91',
		),
		205,
		'zignites-sentinel-event-logs',
		'http://example.test/wp-admin/admin.php?page=zignites-sentinel-event-logs&source=restore-execution-journal&run_id=run-91&snapshot_id=205'
	);

	znts_assert_same( 'restore-execution-journal', $entry['source'], 'Event log presenter should preserve the activity source.' );
	znts_assert_same( 'run-91', $entry['run_id'], 'Event log presenter should expose the activity run ID from decoded context.' );
	znts_assert_same( 'Run run-91', $entry['journal_label'], 'Event log presenter should build a stable run-journal label.' );
	znts_assert_same( 'http://example.test/wp-admin/admin.php?page=zignites-sentinel-event-logs&snapshot_id=205&log_id=91', $entry['detail_url'], 'Event log presenter should build snapshot-scoped detail links for activity rows.' );
	znts_assert_same( 'http://example.test/wp-admin/admin.php?page=zignites-sentinel-event-logs&source=restore-execution-journal&run_id=run-91&snapshot_id=205', $entry['journal_url'], 'Event log presenter should preserve the supplied journal URL for run-scoped activity rows.' );
}
