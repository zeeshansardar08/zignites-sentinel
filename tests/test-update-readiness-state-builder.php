<?php
/**
 * Focused tests for Update Readiness screen state building.
 */

require_once __DIR__ . '/bootstrap.php';

use Zignites\Sentinel\Admin\UpdateReadinessStateBuilder;

function znts_test_update_readiness_state_builder_normalizes_screen_state() {
	$builder = new UpdateReadinessStateBuilder();
	$state   = $builder->build_screen_state(
		array(
			'last_preflight' => array(
				'status' => 'warning',
			),
			'last_update_plan' => array(
				'status' => 'ready',
			),
			'last_restore_check' => array(
				'status' => 'blocked',
			),
			'settings' => array(
				'retention' => 5,
			),
			'update_candidates' => array(
				array(
					'type' => 'plugin',
				),
			),
			'snapshot_list_state' => array(
				'items' => array(
					array(
						'id'    => 101,
						'label' => 'Release snapshot',
					),
				),
				'status_index' => array(
					101 => array(
						'restore_ready' => true,
					),
				),
				'pagination' => array(
					'current_page' => 1,
					'total_items'  => 1,
				),
			),
			'snapshot_search' => 'Release',
			'snapshot_status_filter' => 'ready',
			'snapshot_status_filter_options' => array(
				'ready' => 'Ready',
			),
			'snapshot_detail' => array(
				'id' => 101,
			),
			'snapshot_comparison' => array(
				'status' => 'match',
			),
			'snapshot_artifacts' => array(
				array(
					'type' => 'json',
				),
			),
			'artifact_diff' => array(
				'missing' => array(),
			),
			'last_restore_dry_run' => array(
				'status' => 'ready',
			),
			'last_restore_stage' => array(
				'status' => 'ready',
			),
			'last_restore_plan' => array(
				'status' => 'ready',
			),
			'last_restore_execution' => array(
				'run_id' => 'restore-101',
			),
			'last_restore_rollback' => array(
				'run_id' => 'rollback-101',
			),
			'stage_checkpoint' => array(
				'status' => 'fresh',
			),
			'plan_checkpoint' => array(
				'status' => 'fresh',
			),
			'execution_checkpoint' => array(
				'run_id' => 'execution-checkpoint',
			),
			'execution_checkpoint_summary' => array(
				'item_count' => 2,
			),
			'rollback_checkpoint' => array(
				'run_id' => 'rollback-checkpoint',
			),
			'rollback_checkpoint_summary' => array(
				'item_count' => 1,
			),
			'restore_resume_context' => array(
				'can_resume' => true,
			),
			'restore_rollback_resume_context' => array(
				'can_resume' => false,
			),
			'restore_run_cards' => array(
				array(
					'title' => 'Plan',
				),
			),
			'snapshot_health_baseline' => array(
				'status' => 'healthy',
			),
			'snapshot_health_comparison' => array(
				array(
					'label' => 'Baseline',
				),
			),
			'snapshot_summary' => array(
				'risks' => array(
					'No active blockers',
				),
			),
			'operator_checklist' => array(
				'can_execute' => true,
			),
			'restore_impact_summary' => array(
				'planned_total' => 2,
			),
			'audit_report_verification' => array(
				'status' => 'verified',
			),
			'snapshot_activity' => array(
				array(
					'message' => 'Snapshot created',
				),
			),
			'snapshot_activity_url' => 'http://example.test/wp-admin/admin.php?page=zignites-sentinel-event-logs&snapshot_id=101',
			'notice' => array(
				'type'    => 'success',
				'message' => 'Saved',
			),
		)
	);

	znts_assert_same( 'warning', $state['last_preflight']['status'], 'Update Readiness state builder should preserve preflight state.' );
	znts_assert_same( 'Release snapshot', $state['recent_snapshots'][0]['label'], 'Update Readiness state builder should expose snapshot list items as recent snapshots.' );
	znts_assert_same( true, $state['snapshot_status_index'][101]['restore_ready'], 'Update Readiness state builder should expose the snapshot status index from list state.' );
	znts_assert_same( 1, $state['snapshot_pagination']['total_items'], 'Update Readiness state builder should expose snapshot pagination from list state.' );
	znts_assert_same( 101, $state['snapshot_detail']['id'], 'Update Readiness state builder should preserve the selected snapshot detail.' );
	znts_assert_same( 'restore-101', $state['last_restore_execution']['run_id'], 'Update Readiness state builder should preserve restore execution state.' );
	znts_assert_same( true, $state['operator_checklist']['can_execute'], 'Update Readiness state builder should preserve operator checklist state.' );
	znts_assert_same( 'http://example.test/wp-admin/admin.php?page=zignites-sentinel-event-logs&snapshot_id=101', $state['snapshot_activity_url'], 'Update Readiness state builder should preserve the activity URL.' );
	znts_assert_same( 'success', $state['notice']['type'], 'Update Readiness state builder should preserve notice state.' );
}

function znts_test_update_readiness_state_builder_defaults_missing_inputs() {
	$builder = new UpdateReadinessStateBuilder();
	$state   = $builder->build_screen_state(
		array(
			'snapshot_search'        => 123,
			'snapshot_status_filter' => null,
			'snapshot_detail'        => false,
		)
	);

	znts_assert_same( array(), $state['last_preflight'], 'Update Readiness state builder should default missing array payloads to empty arrays.' );
	znts_assert_same( array(), $state['recent_snapshots'], 'Update Readiness state builder should default missing snapshot list rows to an empty array.' );
	znts_assert_same( array(), $state['snapshot_status_index'], 'Update Readiness state builder should default missing status index payloads to an empty array.' );
	znts_assert_same( array(), $state['snapshot_pagination'], 'Update Readiness state builder should default missing pagination payloads to an empty array.' );
	znts_assert_same( '123', $state['snapshot_search'], 'Update Readiness state builder should normalize snapshot search as a string.' );
	znts_assert_same( '', $state['snapshot_status_filter'], 'Update Readiness state builder should normalize a missing snapshot status filter to an empty string.' );
	znts_assert_same( null, $state['snapshot_detail'], 'Update Readiness state builder should normalize missing snapshot detail to null.' );
	znts_assert_same( '', $state['snapshot_activity_url'], 'Update Readiness state builder should default missing activity URL to an empty string.' );
}
