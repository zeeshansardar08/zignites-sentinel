<?php
/**
 * Focused tests for snapshot summary presenter helpers.
 */

require_once __DIR__ . '/bootstrap.php';

use Zignites\Sentinel\Admin\SnapshotSummaryPresenter;

function znts_test_snapshot_summary_presenter_summarizes_artifacts_by_type() {
	$presenter = new SnapshotSummaryPresenter();

	$summary = $presenter->summarize_artifacts(
		array(
			array( 'artifact_type' => 'package' ),
			array( 'artifact_type' => 'export' ),
			array( 'artifact_type' => 'component' ),
			array( 'artifact_type' => 'theme-file' ),
		)
	);

	znts_assert_same( 4, $summary['total'], 'Snapshot summary presenter should count all artifacts.' );
	znts_assert_same( 1, $summary['package'], 'Snapshot summary presenter should count package artifacts separately.' );
	znts_assert_same( 1, $summary['export'], 'Snapshot summary presenter should count export artifacts separately.' );
	znts_assert_same( 2, $summary['component'], 'Snapshot summary presenter should fold non-package/export artifacts into the component bucket.' );
}

function znts_test_snapshot_summary_presenter_deduplicates_risks_and_next_steps() {
	$presenter = new SnapshotSummaryPresenter();

	$risks = $presenter->build_risks(
		array(
			'artifacts' => array(
				'package_present' => false,
			),
			'stage' => array(
				'key' => 'stale',
			),
			'plan' => array(
				'key' => 'stale',
			),
		),
		array(),
		array(
			'status' => 'blocked',
			'note'   => 'Resolve blockers first.',
		),
		array(
			'status' => 'blocked',
			'note'   => 'Resolve blockers first.',
		),
		array()
	);

	$steps = $presenter->build_next_steps(
		array(
			'stage' => array(
				'key' => 'stale',
			),
			'plan' => array(
				'key' => 'stale',
			),
		),
		array(),
		array(
			'status' => 'blocked',
		),
		array(),
		array(),
		array(
			'status' => 'partial',
		),
		array(
			'status' => 'partial',
		)
	);

	znts_assert_same( 5, count( $risks ), 'Snapshot summary presenter should deduplicate repeated risk notes while preserving distinct risks.' );
	znts_assert_true( in_array( 'Resolve blockers first.', $risks, true ), 'Snapshot summary presenter should preserve explicit blocker notes in the risk list.' );
	znts_assert_same( 6, count( $steps ), 'Snapshot summary presenter should return each distinct recommended next step only once.' );
	znts_assert_true( in_array( 'Review the last rollback result before treating this snapshot as stable again.', $steps, true ), 'Snapshot summary presenter should include rollback review guidance when the last rollback was partial.' );
}
