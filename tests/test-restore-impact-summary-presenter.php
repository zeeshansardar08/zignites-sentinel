<?php
/**
 * Focused tests for restore impact summary presenter payload composition.
 */

require_once __DIR__ . '/bootstrap.php';

use Zignites\Sentinel\Admin\RestoreImpactSummaryPresenter;

function znts_test_restore_impact_summary_presenter_builds_blocked_summary_and_blockers() {
	$presenter = new RestoreImpactSummaryPresenter();

	$summary = $presenter->build_summary(
		55,
		array(
			'status'              => 'blocked',
			'confirmation_phrase' => 'RESTORE SNAPSHOT 55',
			'summary'             => array(
				'create'    => 1,
				'replace'   => 3,
				'reuse'     => 2,
				'blocked'   => 1,
				'conflicts' => 4,
			),
		),
		array(),
		array(
			'can_execute' => false,
			'checks'      => array(
				array(
					'label'   => 'Baseline status',
					'status'  => 'fail',
					'message' => 'Capture a baseline before execution.',
				),
				array(
					'label'   => 'Stage gate',
					'status'  => 'warning',
					'message' => 'Refresh the stage checkpoint.',
				),
			),
		),
		array(),
		'Backup root will be created.',
		'Expired 1h ago.',
		'Expires in 8h.'
	);

	znts_assert_same( 'critical', $summary['status'], 'Restore impact summary presenter should use critical status when execution remains blocked.' );
	znts_assert_same( 'Restore blocked', $summary['title'], 'Restore impact summary presenter should use the blocked title when execution remains blocked.' );
	znts_assert_same( 'Backup root will be created.', $summary['rows'][2]['value'], 'Restore impact summary presenter should preserve the supplied backup summary.' );
	znts_assert_same( 2, count( $summary['blockers'] ), 'Restore impact summary presenter should include failing and warning checklist items as blockers.' );
	znts_assert_same( 'Blocked plan items', $summary['rows'][7]['label'], 'Restore impact summary presenter should append blocked-plan-item counts when present.' );
}

function znts_test_restore_impact_summary_presenter_adds_resume_state_and_default_confirmation_phrase() {
	$presenter = new RestoreImpactSummaryPresenter();

	$summary = $presenter->build_summary(
		101,
		array(
			'status'  => 'ready',
			'summary' => array(
				'create'    => 0,
				'replace'   => 1,
				'reuse'     => 5,
				'blocked'   => 0,
				'conflicts' => 0,
			),
		),
		array(
			'status'       => 'healthy',
			'generated_at' => '2025-01-03 12:00:00',
		),
		array(
			'can_execute' => true,
			'checks'      => array(),
		),
		array(
			'can_resume'           => true,
			'completed_item_count' => 4,
			'entry_count'          => 9,
		),
		'Resume will reuse the existing backup root at D:/uploads/zignites-sentinel/backups/run-90.',
		'Expires in 3h.',
		'Expires in 3h.'
	);

	znts_assert_same( 'warning', $summary['status'], 'Restore impact summary presenter should use warning status when replacements are planned.' );
	znts_assert_true( false !== strpos( $summary['rows'][3]['value'], 'Healthy captured at 2025-01-03 12:00:00' ), 'Restore impact summary presenter should format baseline capture details when present.' );
	znts_assert_same( 'RESTORE SNAPSHOT 101', $summary['rows'][6]['value'], 'Restore impact summary presenter should fall back to the default confirmation phrase when the plan does not provide one.' );
	znts_assert_same( 'Resume state', $summary['rows'][7]['label'], 'Restore impact summary presenter should append resume details when resume is available.' );
}
