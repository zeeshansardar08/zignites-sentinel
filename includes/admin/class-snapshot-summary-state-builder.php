<?php
/**
 * Read-only helper for snapshot summary state assembly.
 *
 * @package ZignitesSentinel
 */

namespace Zignites\Sentinel\Admin;

defined( 'ABSPATH' ) || exit;

class SnapshotSummaryStateBuilder {

	/**
	 * Build the normalized state needed by the snapshot summary presenter.
	 *
	 * @param array      $snapshot         Snapshot detail.
	 * @param array      $artifacts        Snapshot artifacts.
	 * @param array      $activity         Snapshot activity rows.
	 * @param array      $operator_checklist Operator checklist payload.
	 * @param array      $restore_check    Restore readiness payload.
	 * @param array      $restore_stage    Restore stage payload.
	 * @param array      $restore_plan     Restore plan payload.
	 * @param array      $last_execution   Last restore execution payload.
	 * @param array      $last_rollback    Last rollback payload.
	 * @param array      $baseline         Baseline payload.
	 * @param array      $stage_checkpoint Stage checkpoint payload.
	 * @param array      $plan_checkpoint  Plan checkpoint payload.
	 * @param array      $status_index     Snapshot status index.
	 * @param array      $stage_timing     Stage timing summary.
	 * @param array      $plan_timing      Plan timing summary.
	 * @param SnapshotSummaryPresenter $snapshot_summary_presenter Summary presenter.
	 * @return array
	 */
	public function build_summary_state( array $snapshot, array $artifacts, array $activity, array $operator_checklist, array $restore_check, array $restore_stage, array $restore_plan, array $last_execution, array $last_rollback, array $baseline, array $stage_checkpoint, array $plan_checkpoint, array $status_index, array $stage_timing, array $plan_timing, SnapshotSummaryPresenter $snapshot_summary_presenter ) {
		$snapshot_status = isset( $status_index[ (int) $snapshot['id'] ] ) ? $status_index[ (int) $snapshot['id'] ] : array();
		$artifact_counts = $snapshot_summary_presenter->summarize_artifacts( $artifacts );

		return array(
			'snapshot'           => $snapshot,
			'snapshot_status'    => $snapshot_status,
			'artifact_counts'    => $artifact_counts,
			'activity'           => array_slice( $activity, 0, 5 ),
			'operator_checklist' => $operator_checklist,
			'baseline'           => $baseline,
			'restore_check'      => $restore_check,
			'stage_timing'       => ! empty( $stage_checkpoint ) ? $stage_timing : array(),
			'plan_timing'        => ! empty( $plan_checkpoint ) ? $plan_timing : array(),
			'last_execution'     => $last_execution,
			'last_rollback'      => $last_rollback,
			'restore_stage'      => $restore_stage,
			'restore_plan'       => $restore_plan,
		);
	}
}
