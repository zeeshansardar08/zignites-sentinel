<?php
/**
 * Focused tests for snapshot summary state building.
 */

require_once __DIR__ . '/bootstrap.php';

use Zignites\Sentinel\Admin\SnapshotSummaryPresenter;
use Zignites\Sentinel\Admin\SnapshotSummaryStateBuilder;

function znts_test_snapshot_summary_state_builder_trims_activity_and_summarizes_artifacts() {
	$builder   = new SnapshotSummaryStateBuilder();
	$presenter = new SnapshotSummaryPresenter();

	$state = $builder->build_summary_state(
		array(
			'id' => 88,
		),
		array(
			array( 'artifact_type' => 'package' ),
			array( 'artifact_type' => 'export' ),
			array( 'artifact_type' => 'component' ),
		),
		array(
			array( 'message' => 'one' ),
			array( 'message' => 'two' ),
			array( 'message' => 'three' ),
			array( 'message' => 'four' ),
			array( 'message' => 'five' ),
			array( 'message' => 'six' ),
		),
		array(
			'can_execute' => false,
		),
		array(
			'status' => 'caution',
		),
		array(
			'status' => 'caution',
		),
		array(
			'status' => 'ready',
		),
		array(
			'status' => 'partial',
		),
		array(),
		array(),
		array(
			'timing' => array(
				'label' => 'Expired 2h ago.',
			),
		),
		array(
			'timing' => array(
				'label' => 'Expires in 20h.',
			),
		),
		array(
			88 => array(
				'restore_ready' => false,
			),
		),
		array(
			'label' => 'Expired 2h ago.',
		),
		array(
			'label' => 'Expires in 20h.',
		),
		$presenter
	);

	znts_assert_same( 3, $state['artifact_counts']['total'], 'Snapshot summary state builder should summarize total artifacts.' );
	znts_assert_same( 1, $state['artifact_counts']['package'], 'Snapshot summary state builder should summarize package artifacts.' );
	znts_assert_same( 5, count( $state['activity'] ), 'Snapshot summary state builder should trim activity rows to the latest five entries.' );
	znts_assert_same( 'five', $state['activity'][4]['message'], 'Snapshot summary state builder should preserve the first five activity entries in order.' );
	znts_assert_true( empty( $state['baseline'] ), 'Snapshot summary state builder should preserve normalized baseline payloads.' );
	znts_assert_same( 'Expired 2h ago.', $state['stage_timing']['label'], 'Snapshot summary state builder should preserve stage timing when a checkpoint exists.' );
}

function znts_test_snapshot_summary_state_builder_omits_timing_without_checkpoints() {
	$builder   = new SnapshotSummaryStateBuilder();
	$presenter = new SnapshotSummaryPresenter();

	$state = $builder->build_summary_state(
		array(
			'id' => 91,
		),
		array(),
		array(),
		array(),
		array(),
		array(),
		array(),
		array(),
		array(),
		array(),
		array(),
		array(),
		array(),
		array(
			'label' => 'Expired 2h ago.',
		),
		array(
			'label' => 'Expires in 20h.',
		),
		$presenter
	);

	znts_assert_same( array(), $state['stage_timing'], 'Snapshot summary state builder should omit stage timing when no stage checkpoint exists.' );
	znts_assert_same( array(), $state['plan_timing'], 'Snapshot summary state builder should omit plan timing when no plan checkpoint exists.' );
	znts_assert_same( array(), $state['snapshot_status'], 'Snapshot summary state builder should default missing snapshot status payloads to an empty array.' );
}
