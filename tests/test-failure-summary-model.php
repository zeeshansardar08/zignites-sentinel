<?php
/**
 * Focused tests for deterministic failure summaries.
 */

require_once __DIR__ . '/bootstrap.php';

use Zignites\Sentinel\Platform\FailureSummaryModel;

function znts_test_failure_summary_model_builds_deterministic_high_summary() {
	$model = new FailureSummaryModel();

	$summary = $model->build_summary(
		array(
			array(
				'severity'   => 'error',
				'message'    => 'Restore failed while writing plugin file.',
				'created_at' => '2026-05-14 12:00:00',
			),
			array(
				'severity' => 'warning',
				'message'  => 'Health check warning.',
			),
		),
		array(
			array(
				'label'  => 'Homepage check',
				'status' => 'fail',
			),
			array(
				'label'  => 'Admin check',
				'status' => 'pass',
			),
		),
		array(
			array(
				'phase'   => 'payload_written',
				'status'  => 'fail',
				'message' => 'Could not write plugin file.',
			),
		),
		array(
			'label' => 'Restore incident',
		)
	);

	znts_assert_same( '1.0', $summary['schema_version'], 'Failure summary should expose a stable schema version.' );
	znts_assert_same( 'high', $summary['severity'], 'Failure summary should become high when errors, failed health, or failed journal entries exist.' );
	znts_assert_same( 'Restore incident: High', $summary['title'], 'Failure summary should include the supplied context label.' );
	znts_assert_same( 1, $summary['logs']['error'], 'Failure summary should count error logs.' );
	znts_assert_same( 1, $summary['health']['fail'], 'Failure summary should count failed health checks.' );
	znts_assert_same( 1, $summary['journal']['fail'], 'Failure summary should count failed journal entries.' );
	znts_assert_same( true, $summary['ai_assistive_only'], 'Failure summary should make AI assistance explicitly optional.' );
	znts_assert_same( true, $summary['deterministic_only'], 'Failure summary should identify the deterministic fallback path.' );
	znts_assert_true( count( $summary['findings'] ) >= 3, 'Failure summary should include log, health, and journal findings.' );
	znts_assert_true( count( $summary['next_steps'] ) >= 3, 'Failure summary should include actionable deterministic next steps.' );
}

function znts_test_failure_summary_model_prioritizes_critical_blockers() {
	$model = new FailureSummaryModel();

	$summary = $model->build_summary(
		array(
			array(
				'severity' => 'critical',
				'message'  => 'Critical fatal error detected.',
			),
		),
		array(
			array(
				'label'  => 'Storage check',
				'status' => 'blocked',
			),
		),
		array(
			array(
				'scope'   => 'run',
				'phase'   => 'completed',
				'status'  => 'blocked',
				'message' => 'Restore blocked before completion.',
			),
		)
	);

	znts_assert_same( 'critical', $summary['severity'], 'Failure summary should prioritize critical logs and blocked terminal journal state.' );
	znts_assert_same( 1, $summary['logs']['critical'], 'Failure summary should count critical logs.' );
	znts_assert_same( 1, $summary['health']['blocked'], 'Failure summary should count blocked health checks.' );
	znts_assert_same( 'blocked', $summary['journal']['terminal_status'], 'Failure summary should preserve terminal journal status.' );
}

function znts_test_failure_summary_model_renders_plain_text_fallback() {
	$model = new FailureSummaryModel();
	$text  = $model->render_text(
		$model->build_summary(
			array(),
			array(),
			array(),
			array(
				'label' => 'Quiet window',
			)
		)
	);

	znts_assert_true( false !== strpos( $text, 'Quiet window: Low' ), 'Failure summary text should include title and severity.' );
	znts_assert_true( false !== strpos( $text, 'No deterministic failure findings were detected.' ), 'Failure summary text should include a no-failure fallback.' );
	znts_assert_true( false !== strpos( $text, 'AI assistance is optional.' ), 'Failure summary text should state that AI is optional.' );
}
