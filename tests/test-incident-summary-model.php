<?php
/**
 * Focused tests for client-friendly incident summaries.
 */

require_once __DIR__ . '/bootstrap.php';

use Zignites\Sentinel\Platform\FailureSummaryModel;
use Zignites\Sentinel\Platform\IncidentSummaryModel;

function znts_test_incident_summary_model_builds_client_readable_attention_summary() {
	$failure_model  = new FailureSummaryModel();
	$incident_model = new IncidentSummaryModel();
	$journal        = array(
		array(
			'timestamp' => '2026-05-15 09:00:00',
			'phase'     => 'backup_moved',
			'status'    => 'pass',
			'message'   => 'Live plugin backup preserved.',
		),
		array(
			'timestamp' => '2026-05-15 09:01:00',
			'phase'     => 'payload_written',
			'status'    => 'fail',
			'message'   => 'Plugin file write failed.',
		),
	);
	$failure = $failure_model->build_summary(
		array(
			array(
				'severity' => 'error',
				'message'  => 'Restore failed.',
			),
		),
		array(
			array(
				'label'  => 'Homepage check',
				'status' => 'fail',
			),
		),
		$journal,
		array(
			'label' => 'Restore incident',
		)
	);

	$summary = $incident_model->build_summary(
		array(
			'title'       => 'Plugin restore incident',
			'snapshot_id' => 44,
		),
		$failure,
		$journal,
		array(
			'agency_name'  => 'Example Agency',
			'report_title' => 'Client Incident Summary',
		)
	);

	znts_assert_same( '1.0', $summary['schema_version'], 'Incident summary should expose a stable schema version.' );
	znts_assert_same( 'client_incident_summary', $summary['summary_type'], 'Incident summary should expose its payload type.' );
	znts_assert_same( 'Example Agency', $summary['agency']['name'], 'Incident summary should preserve agency metadata.' );
	znts_assert_same( 'needs_attention', $summary['outcome']['status'], 'Failed deterministic evidence should keep the incident open.' );
	znts_assert_true( false !== strpos( $summary['plain_language'], 'needs operator review' ), 'Incident summary should explain the outcome in client-readable language.' );
	znts_assert_same( 2, count( $summary['timeline'] ), 'Incident summary should preserve journal timeline entries.' );
	znts_assert_true( in_array( 'Sentinel preserved a live-code backup before replacement work.', $summary['actions_taken'], true ), 'Incident summary should convert backup journal steps into action lines.' );
	znts_assert_true( false !== strpos( $summary['impact'], '1 failed health check' ), 'Incident summary should summarize deterministic impact counts.' );
	znts_assert_same( true, $summary['ai_assistive_only'], 'Incident summary should keep AI assistance optional.' );
}

function znts_test_incident_summary_model_marks_completed_run_resolved() {
	$failure_model  = new FailureSummaryModel();
	$incident_model = new IncidentSummaryModel();
	$journal        = array(
		array(
			'phase'   => 'completed',
			'scope'   => 'run',
			'status'  => 'pass',
			'message' => 'Restore completed.',
		),
	);
	$failure = $failure_model->build_summary( array(), array(), $journal );
	$summary = $incident_model->build_summary(
		array(
			'title' => 'Restore review',
		),
		$failure,
		$journal
	);

	znts_assert_same( 'resolved', $summary['outcome']['status'], 'Passing terminal journal state should resolve the incident summary.' );
	znts_assert_true( false !== strpos( $summary['plain_language'], 'finished successfully' ), 'Resolved incident summary should use plain successful outcome language.' );
}

function znts_test_incident_summary_model_renders_plain_text_report() {
	$incident_model = new IncidentSummaryModel();
	$text           = $incident_model->render_text(
		$incident_model->build_summary(
			array(
				'title' => 'Quiet restore review',
			),
			array(
				'severity'   => 'low',
				'overview'   => 'No deterministic failure signal was detected.',
				'health'     => array(),
				'journal'    => array(),
				'logs'       => array(),
				'next_steps' => array(),
			),
			array(),
			array(
				'agency_name' => 'Example Agency',
			)
		)
	);

	znts_assert_true( false !== strpos( $text, 'Incident Summary' ), 'Incident summary text should include a readable title.' );
	znts_assert_true( false !== strpos( $text, 'Prepared by: Example Agency' ), 'Incident summary text should include agency metadata.' );
	znts_assert_true( false !== strpos( $text, 'No restore journal entries were supplied' ), 'Incident summary text should include an empty-journal fallback.' );
	znts_assert_true( false !== strpos( $text, 'Database, uploads/media' ), 'Incident summary text should include restore boundary language.' );
}
