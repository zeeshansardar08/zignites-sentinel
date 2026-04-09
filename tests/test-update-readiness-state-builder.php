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
				'status'     => 'ready',
				'validation' => array(
					'message' => 'Plan validated',
					'checks'  => array(
						array(
							'label'   => 'Plugin package',
							'status'  => 'pass',
							'message' => 'Plugin package is available.',
						),
					),
				),
			),
			'last_restore_check' => array(
				'status'            => 'blocked',
				'source_validation' => array(
					'message' => 'Sources available',
					'checks'  => array(
						array(
							'label'   => 'Snapshot source',
							'status'  => 'fail',
							'message' => 'Snapshot source is missing.',
							'details' => array(
								'missing_plugins' => array(
									array(
										'name'   => 'Missing Plugin',
										'plugin' => 'missing-plugin/missing.php',
									),
									array(
										'plugin' => 'fallback-plugin/fallback.php',
									),
								),
								'missing_artifacts' => array(
									array(
										'label' => 'Rollback package',
									),
								),
							),
						),
					),
				),
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
						'status_badges' => array(
							array(
								'label' => 'Ready',
								'badge' => 'info',
							),
						),
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
				'id'               => 101,
				'label'            => 'Release snapshot',
				'created_at'       => '2026-04-09 10:00:00',
				'metadata_decoded' => array(
					'component_manifest' => array(
						'generated_at' => '2026-04-09 10:00:01',
					),
				),
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
				'status'      => 'healthy',
				'status_pill' => 'warning',
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
				'next_steps' => array(
					'Review restore impact',
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
	znts_assert_same( 'Ready', $state['selected_snapshot_status']['status_badges'][0]['label'], 'Update Readiness state builder should derive selected snapshot status from the status index.' );
	znts_assert_same( 'Plan validated', $state['plan_validation']['message'], 'Update Readiness state builder should derive update-plan validation state.' );
	znts_assert_same( 'Plugin package', $state['plan_validation_check_rows'][0]['label'], 'Update Readiness state builder should derive plan validation check rows.' );
	znts_assert_same( 'pass', $state['plan_validation_check_rows'][0]['badge'], 'Update Readiness state builder should preserve non-failing validation check badges.' );
	znts_assert_same( 'Sources available', $state['restore_source_validation']['message'], 'Update Readiness state builder should derive restore source validation state.' );
	znts_assert_same( 'critical', $state['restore_source_validation_check_rows'][0]['badge'], 'Update Readiness state builder should map failing restore source checks to critical badges.' );
	znts_assert_same( 'Fail', $state['restore_source_validation_check_rows'][0]['status_label'], 'Update Readiness state builder should derive human-readable validation status labels.' );
	znts_assert_same( 'Missing Plugin', $state['restore_source_missing_plugins'][0], 'Update Readiness state builder should prefer missing plugin names when available.' );
	znts_assert_same( 'fallback-plugin/fallback.php', $state['restore_source_missing_plugins'][1], 'Update Readiness state builder should fall back to missing plugin paths.' );
	znts_assert_same( 'Rollback package', $state['restore_source_missing_artifacts'][0], 'Update Readiness state builder should derive missing artifact labels.' );
	znts_assert_same( '2026-04-09 10:00:01', $state['component_manifest']['generated_at'], 'Update Readiness state builder should derive component manifest state from the selected snapshot.' );
	znts_assert_same( 'Release snapshot', $state['selected_snapshot_label'], 'Update Readiness state builder should derive the selected snapshot label.' );
	znts_assert_same( 'Snapshot #101 captured on 2026-04-09 10:00:00 is the active restore workspace.', $state['selected_snapshot_note'], 'Update Readiness state builder should derive the selected snapshot workspace note.' );
	znts_assert_same( 1, $state['snapshot_match_count'], 'Update Readiness state builder should derive snapshot match count from pagination.' );
	znts_assert_same( 'Restore ready', $state['workspace_status_label'], 'Update Readiness state builder should derive the workspace status label from checklist readiness.' );
	znts_assert_same( 'info', $state['workspace_status_badge'], 'Update Readiness state builder should derive the workspace status badge from checklist readiness.' );
	znts_assert_same( 'Review the impact summary, then continue with guarded restore only if the plan still matches your intent.', $state['workspace_next_action'], 'Update Readiness state builder should derive ready-state workspace guidance.' );
	znts_assert_same( 'No active blockers', $state['snapshot_primary_risk'], 'Update Readiness state builder should derive the primary snapshot risk.' );
	znts_assert_same( 'Review restore impact', $state['snapshot_primary_step'], 'Update Readiness state builder should derive the primary snapshot next step.' );
	znts_assert_same( 'warning', $state['health_attention_state'], 'Update Readiness state builder should derive the health attention state from baseline status pill.' );
	znts_assert_same( 'Restore preparation needs attention: the current health baseline is degraded.', $state['health_attention_message'], 'Update Readiness state builder should derive the health attention message.' );
	znts_assert_same( false, $state['open_health_validation'], 'Update Readiness state builder should close health validation details when checklist gates can execute.' );
	znts_assert_same( 'Next: confirm the impact summary, verify the checklist is still current, and only then move into guarded restore review.', $state['workspace_flow_message'], 'Update Readiness state builder should derive ready-state workflow guidance.' );
	znts_assert_same( 'Checklist gates are currently satisfied for this snapshot.', $state['workspace_confidence'], 'Update Readiness state builder should derive ready-state workspace confidence.' );
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
	znts_assert_same( array(), $state['plan_validation_check_rows'], 'Update Readiness state builder should default missing plan validation rows to an empty array.' );
	znts_assert_same( array(), $state['restore_source_validation_check_rows'], 'Update Readiness state builder should default missing restore source rows to an empty array.' );
	znts_assert_same( array(), $state['restore_source_missing_plugins'], 'Update Readiness state builder should default missing plugin labels to an empty array.' );
	znts_assert_same( array(), $state['restore_source_missing_artifacts'], 'Update Readiness state builder should default missing artifact labels to an empty array.' );
	znts_assert_same( '123', $state['snapshot_search'], 'Update Readiness state builder should normalize snapshot search as a string.' );
	znts_assert_same( '', $state['snapshot_status_filter'], 'Update Readiness state builder should normalize a missing snapshot status filter to an empty string.' );
	znts_assert_same( null, $state['snapshot_detail'], 'Update Readiness state builder should normalize missing snapshot detail to null.' );
	znts_assert_same( '', $state['snapshot_activity_url'], 'Update Readiness state builder should default missing activity URL to an empty string.' );
	znts_assert_same( 'No snapshot selected', $state['selected_snapshot_label'], 'Update Readiness state builder should derive the empty selected snapshot label.' );
	znts_assert_same( 'Awaiting snapshot', $state['workspace_status_label'], 'Update Readiness state builder should derive awaiting-snapshot workspace status.' );
	znts_assert_same( 'critical', $state['workspace_status_badge'], 'Update Readiness state builder should derive the awaiting-snapshot workspace badge.' );
	znts_assert_same( 'critical', $state['health_attention_state'], 'Update Readiness state builder should derive critical health attention state without a baseline.' );
	znts_assert_same( true, $state['open_health_validation'], 'Update Readiness state builder should open health validation details when checklist gates are incomplete.' );
}
