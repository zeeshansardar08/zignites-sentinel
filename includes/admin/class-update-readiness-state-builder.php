<?php
/**
 * Read-only helper for Update Readiness screen state assembly.
 *
 * @package ZignitesSentinel
 */

namespace Zignites\Sentinel\Admin;

defined( 'ABSPATH' ) || exit;

class UpdateReadinessStateBuilder {

	/**
	 * Build normalized view state for the Update Readiness screen.
	 *
	 * @param array $state Raw screen state assembled by the admin controller.
	 * @return array
	 */
	public function build_screen_state( array $state ) {
		$snapshot_list_state = $this->array_value( $state, 'snapshot_list_state' );

		$view_data = array(
			'last_preflight'          => $this->array_value( $state, 'last_preflight' ),
			'last_update_plan'        => $this->array_value( $state, 'last_update_plan' ),
			'last_restore_check'      => $this->array_value( $state, 'last_restore_check' ),
			'settings'                => $this->array_value( $state, 'settings' ),
			'update_candidates'       => $this->array_value( $state, 'update_candidates' ),
			'recent_snapshots'        => $this->array_value( $snapshot_list_state, 'items' ),
			'snapshot_search'         => isset( $state['snapshot_search'] ) ? (string) $state['snapshot_search'] : '',
			'snapshot_status_filter'  => isset( $state['snapshot_status_filter'] ) ? (string) $state['snapshot_status_filter'] : '',
			'snapshot_status_filter_options' => $this->array_value( $state, 'snapshot_status_filter_options' ),
			'snapshot_status_index'   => $this->array_value( $snapshot_list_state, 'status_index' ),
			'snapshot_pagination'     => $this->array_value( $snapshot_list_state, 'pagination' ),
			'snapshot_detail'         => $this->nullable_array_value( $state, 'snapshot_detail' ),
			'snapshot_comparison'     => $this->array_value( $state, 'snapshot_comparison' ),
			'snapshot_artifacts'      => $this->array_value( $state, 'snapshot_artifacts' ),
			'artifact_diff'           => $this->array_value( $state, 'artifact_diff' ),
			'last_restore_dry_run'    => $this->array_value( $state, 'last_restore_dry_run' ),
			'last_restore_stage'      => $this->array_value( $state, 'last_restore_stage' ),
			'last_restore_plan'       => $this->array_value( $state, 'last_restore_plan' ),
			'last_restore_execution'  => $this->array_value( $state, 'last_restore_execution' ),
			'last_restore_rollback'   => $this->array_value( $state, 'last_restore_rollback' ),
			'stage_checkpoint'        => $this->array_value( $state, 'stage_checkpoint' ),
			'plan_checkpoint'         => $this->array_value( $state, 'plan_checkpoint' ),
			'execution_checkpoint'    => $this->array_value( $state, 'execution_checkpoint' ),
			'execution_checkpoint_summary' => $this->array_value( $state, 'execution_checkpoint_summary' ),
			'rollback_checkpoint'     => $this->array_value( $state, 'rollback_checkpoint' ),
			'rollback_checkpoint_summary' => $this->array_value( $state, 'rollback_checkpoint_summary' ),
			'restore_resume_context'  => $this->array_value( $state, 'restore_resume_context' ),
			'restore_rollback_resume_context' => $this->array_value( $state, 'restore_rollback_resume_context' ),
			'restore_run_cards'       => $this->array_value( $state, 'restore_run_cards' ),
			'snapshot_health_baseline' => $this->array_value( $state, 'snapshot_health_baseline' ),
			'snapshot_health_comparison' => $this->array_value( $state, 'snapshot_health_comparison' ),
			'snapshot_summary'        => $this->array_value( $state, 'snapshot_summary' ),
			'operator_checklist'      => $this->array_value( $state, 'operator_checklist' ),
			'restore_impact_summary'  => $this->array_value( $state, 'restore_impact_summary' ),
			'audit_report_verification' => $this->array_value( $state, 'audit_report_verification' ),
			'snapshot_activity'       => $this->array_value( $state, 'snapshot_activity' ),
			'snapshot_activity_url'   => isset( $state['snapshot_activity_url'] ) ? (string) $state['snapshot_activity_url'] : '',
			'notice'                  => $this->array_value( $state, 'notice' ),
		);

		$view_data = $this->with_workspace_state( $view_data );
		$view_data = $this->with_snapshot_list_state( $view_data );
		$view_data = $this->with_settings_form_state( $view_data );
		$view_data = $this->with_health_state( $view_data );
		$view_data = $this->with_snapshot_detail_state( $view_data );
		$view_data = $this->with_activity_navigation_state( $view_data );
		$view_data = $this->with_status_section_state( $view_data );
		$view_data = $this->with_restore_result_state( $view_data );
		$view_data = $this->with_checkpoint_summary_state( $view_data );
		$view_data = $this->with_form_state( $view_data );

		return $view_data;
	}

	/**
	 * Add derived snapshot list and pagination state.
	 *
	 * @param array $view_data Normalized screen state.
	 * @return array
	 */
	protected function with_snapshot_list_state( array $view_data ) {
		$snapshot_detail        = $this->nullable_array_value( $view_data, 'snapshot_detail' );
		$snapshot_search        = isset( $view_data['snapshot_search'] ) ? (string) $view_data['snapshot_search'] : '';
		$snapshot_status_filter = isset( $view_data['snapshot_status_filter'] ) ? (string) $view_data['snapshot_status_filter'] : '';
		$snapshot_pagination    = $this->array_value( $view_data, 'snapshot_pagination' );

		$view_data['recent_snapshot_rows'] = $this->build_recent_snapshot_rows(
			$this->array_value( $view_data, 'recent_snapshots' ),
			$this->array_value( $view_data, 'snapshot_status_index' )
		);
		$view_data['snapshot_empty_message'] = '' !== $snapshot_search || '' !== $snapshot_status_filter
			? __( 'No snapshots matched the current filters.', 'zignites-sentinel' )
			: __( 'No snapshot metadata has been recorded yet.', 'zignites-sentinel' );
		$view_data['snapshot_pagination_summary'] = $this->build_snapshot_pagination_summary( $snapshot_pagination );
		$view_data['show_snapshot_filter_clear'] = '' !== $snapshot_search || '' !== $snapshot_status_filter;
		$view_data['snapshot_filter_clear_url'] = $this->build_snapshot_filter_clear_url( $snapshot_detail );
		$view_data['snapshot_pagination_links_args'] = $this->build_snapshot_pagination_links_args(
			$snapshot_pagination,
			$snapshot_detail,
			$snapshot_search,
			$snapshot_status_filter
		);

		return $view_data;
	}

	/**
	 * Add derived settings form state.
	 *
	 * @param array $view_data Normalized screen state.
	 * @return array
	 */
	protected function with_settings_form_state( array $view_data ) {
		$view_data['settings_form_state'] = $this->build_settings_form_state( $this->array_value( $view_data, 'settings' ) );

		return $view_data;
	}

	/**
	 * Add derived workspace and hero state used by the Update Readiness view.
	 *
	 * @param array $view_data Normalized screen state.
	 * @return array
	 */
	protected function with_workspace_state( array $view_data ) {
		$snapshot_detail          = $this->nullable_array_value( $view_data, 'snapshot_detail' );
		$snapshot_status_index    = $this->array_value( $view_data, 'snapshot_status_index' );
		$last_plan                = $this->array_value( $view_data, 'last_update_plan' );
		$last_restore_check       = $this->array_value( $view_data, 'last_restore_check' );
		$snapshot_pagination      = $this->array_value( $view_data, 'snapshot_pagination' );
		$recent_snapshots         = $this->array_value( $view_data, 'recent_snapshots' );
		$operator_checklist       = $this->array_value( $view_data, 'operator_checklist' );
		$snapshot_summary         = $this->array_value( $view_data, 'snapshot_summary' );
		$snapshot_health_baseline = $this->array_value( $view_data, 'snapshot_health_baseline' );
		$snapshot_id              = is_array( $snapshot_detail ) && ! empty( $snapshot_detail['id'] ) ? (int) $snapshot_detail['id'] : 0;
		$health_attention_state   = empty( $snapshot_health_baseline ) ? 'critical' : ( isset( $snapshot_health_baseline['status_pill'] ) ? (string) $snapshot_health_baseline['status_pill'] : 'info' );

		$view_data['selected_snapshot_status'] = $snapshot_id > 0 && isset( $snapshot_status_index[ $snapshot_id ] ) && is_array( $snapshot_status_index[ $snapshot_id ] ) ? $snapshot_status_index[ $snapshot_id ] : array();
		$view_data['plan_validation'] = isset( $last_plan['validation'] ) && is_array( $last_plan['validation'] ) ? $last_plan['validation'] : array();
		$view_data['restore_source_validation'] = isset( $last_restore_check['source_validation'] ) && is_array( $last_restore_check['source_validation'] ) ? $last_restore_check['source_validation'] : array();
		$view_data['plan_validation_check_rows'] = $this->build_check_rows( $view_data['plan_validation'] );
		$view_data['restore_readiness_status'] = $this->build_restore_result_status( $last_restore_check );
		$view_data['restore_readiness_check_rows'] = $this->build_check_rows( $last_restore_check );
		$view_data['restore_source_validation_check_rows'] = $this->build_check_rows( $view_data['restore_source_validation'] );
		$view_data['restore_source_missing_plugins'] = $this->build_missing_plugin_labels( $view_data['restore_source_validation'] );
		$view_data['restore_source_missing_artifacts'] = $this->build_missing_artifact_labels( $view_data['restore_source_validation'] );
		$view_data['component_manifest'] = is_array( $snapshot_detail ) && ! empty( $snapshot_detail['metadata_decoded']['component_manifest'] ) && is_array( $snapshot_detail['metadata_decoded']['component_manifest'] ) ? $snapshot_detail['metadata_decoded']['component_manifest'] : array();
		$view_data['selected_snapshot_label'] = is_array( $snapshot_detail ) && ! empty( $snapshot_detail['label'] ) ? (string) $snapshot_detail['label'] : __( 'No snapshot selected', 'zignites-sentinel' );
		$view_data['selected_snapshot_note'] = is_array( $snapshot_detail )
			? sprintf(
				/* translators: 1: snapshot id, 2: created at */
				__( 'Snapshot #%1$d captured on %2$s is the active restore workspace.', 'zignites-sentinel' ),
				$snapshot_id,
				isset( $snapshot_detail['created_at'] ) ? (string) $snapshot_detail['created_at'] : ''
			)
			: __( 'Choose a snapshot from the list below to inspect readiness, planning, and restore controls.', 'zignites-sentinel' );
		$view_data['snapshot_match_count'] = isset( $snapshot_pagination['total_items'] ) ? (int) $snapshot_pagination['total_items'] : count( $recent_snapshots );
		$view_data['workspace_status_label'] = ! empty( $operator_checklist['can_execute'] ) ? __( 'Restore ready', 'zignites-sentinel' ) : ( is_array( $snapshot_detail ) ? __( 'Needs attention', 'zignites-sentinel' ) : __( 'Awaiting snapshot', 'zignites-sentinel' ) );
		$view_data['workspace_status_badge'] = ! empty( $operator_checklist['can_execute'] ) ? 'info' : ( is_array( $snapshot_detail ) ? 'warning' : 'critical' );
		$view_data['workspace_next_action'] = ! empty( $operator_checklist['can_execute'] )
			? __( 'Review the impact summary, then continue with guarded restore only if the plan still matches your intent.', 'zignites-sentinel' )
			: ( is_array( $snapshot_detail ) ? __( 'Complete the missing checklist items or refresh restore gates before continuing.', 'zignites-sentinel' ) : __( 'Run a preflight scan or create a snapshot to begin update-readiness work.', 'zignites-sentinel' ) );
		$view_data['snapshot_primary_risk'] = ! empty( $snapshot_summary['risks'][0] ) ? (string) $snapshot_summary['risks'][0] : __( 'No active risk callouts are currently highlighted for this snapshot.', 'zignites-sentinel' );
		$view_data['snapshot_primary_step'] = ! empty( $snapshot_summary['next_steps'][0] ) ? (string) $snapshot_summary['next_steps'][0] : __( 'No immediate follow-up step is currently required.', 'zignites-sentinel' );
		$view_data['health_attention_state'] = $health_attention_state;
		$view_data['health_attention_message'] = empty( $snapshot_health_baseline )
			? __( 'Restore not safe yet: capture a baseline before any guarded restore work.', 'zignites-sentinel' )
			: ( 'critical' === $health_attention_state
				? __( 'Restore not safe yet: the current health baseline is unhealthy and should be reviewed before any restore decision.', 'zignites-sentinel' )
				: ( 'warning' === $health_attention_state
					? __( 'Restore preparation needs attention: the current health baseline is degraded.', 'zignites-sentinel' )
					: __( 'Baseline status is recorded for this snapshot.', 'zignites-sentinel' ) ) );
		$view_data['open_health_validation'] = empty( $operator_checklist['can_execute'] );
		$view_data['workspace_flow_message'] = ! empty( $operator_checklist['can_execute'] )
			? __( 'Next: confirm the impact summary, verify the checklist is still current, and only then move into guarded restore review.', 'zignites-sentinel' )
			: ( is_array( $snapshot_detail )
				? __( 'Next: focus on the highlighted risk and next-step guidance below, then open detail panels only where you need deeper proof.', 'zignites-sentinel' )
				: __( 'Next: run a scan or choose a snapshot, then let the summary below guide the next safe step.', 'zignites-sentinel' ) );
		$view_data['workspace_confidence'] = ! empty( $operator_checklist['can_execute'] )
			? __( 'Checklist gates are currently satisfied for this snapshot.', 'zignites-sentinel' )
			: __( 'The workspace is showing the shortest safe path, not every technical detail at once.', 'zignites-sentinel' );

		return $view_data;
	}

	/**
	 * Add derived snapshot detail, artifact, and comparison rows.
	 *
	 * @param array $view_data Normalized screen state.
	 * @return array
	 */
	protected function with_snapshot_detail_state( array $view_data ) {
		$snapshot_detail     = $this->nullable_array_value( $view_data, 'snapshot_detail' );
		$snapshot_artifacts  = $this->array_value( $view_data, 'snapshot_artifacts' );
		$artifact_diff       = $this->array_value( $view_data, 'artifact_diff' );
		$snapshot_comparison = $this->array_value( $view_data, 'snapshot_comparison' );

		$view_data['snapshot_basic_rows'] = $this->build_snapshot_basic_rows( $snapshot_detail );
		$view_data['snapshot_metadata_rows'] = $this->build_snapshot_metadata_rows( $snapshot_detail );
		$view_data['component_manifest_rows'] = $this->build_component_manifest_rows( $this->array_value( $view_data, 'component_manifest' ) );
		$view_data['snapshot_artifact_rows'] = $this->build_snapshot_artifact_rows( $snapshot_artifacts );
		$view_data['artifact_diff_state'] = $this->build_artifact_diff_state( $artifact_diff );
		$view_data['active_plugin_rows'] = $this->build_active_plugin_rows( $snapshot_detail );
		$view_data['snapshot_comparison_rows'] = $this->build_snapshot_comparison_rows( $snapshot_comparison );
		$view_data['missing_snapshot_plugin_labels'] = $this->build_plugin_state_labels( $this->array_value( $snapshot_comparison, 'missing_plugins' ) );
		$view_data['new_current_plugin_labels'] = $this->build_plugin_state_labels( $this->array_value( $snapshot_comparison, 'new_plugins' ) );
		$view_data['plugin_version_change_rows'] = $this->build_plugin_version_change_rows( $snapshot_comparison );

		return $view_data;
	}

	/**
	 * Add derived health baseline and comparison rows.
	 *
	 * @param array $view_data Normalized screen state.
	 * @return array
	 */
	protected function with_health_state( array $view_data ) {
		$view_data['snapshot_health_baseline_status'] = $this->build_snapshot_health_baseline_status( $this->array_value( $view_data, 'snapshot_health_baseline' ) );
		$view_data['snapshot_health_comparison_rows'] = $this->build_snapshot_health_comparison_rows( $this->array_value( $view_data, 'snapshot_health_comparison' ) );

		return $view_data;
	}

	/**
	 * Add derived snapshot activity and restore action navigation state.
	 *
	 * @param array $view_data Normalized screen state.
	 * @return array
	 */
	protected function with_activity_navigation_state( array $view_data ) {
		$view_data['restore_action_jump_links'] = $this->build_restore_action_jump_links(
			$this->array_value( $view_data, 'last_restore_dry_run' ),
			$this->array_value( $view_data, 'last_restore_stage' ),
			$this->array_value( $view_data, 'last_restore_plan' )
		);
		$view_data['snapshot_activity_rows'] = $this->build_snapshot_activity_rows( $this->array_value( $view_data, 'snapshot_activity' ) );

		return $view_data;
	}

	/**
	 * Add derived state for general Update Readiness status sections.
	 *
	 * @param array $view_data Normalized screen state.
	 * @return array
	 */
	protected function with_status_section_state( array $view_data ) {
		$preflight                 = $this->array_value( $view_data, 'last_preflight' );
		$last_plan                 = $this->array_value( $view_data, 'last_update_plan' );
		$operator_checklist        = $this->array_value( $view_data, 'operator_checklist' );
		$audit_report_verification = $this->array_value( $view_data, 'audit_report_verification' );
		$update_candidates         = $this->array_value( $view_data, 'update_candidates' );

		$view_data['operator_checklist_status'] = $this->build_operator_checklist_status( $operator_checklist );
		$view_data['operator_checklist_check_rows'] = $this->build_check_rows( $operator_checklist );
		$view_data['audit_report_verification_status'] = $this->build_restore_result_status( $audit_report_verification );
		$view_data['audit_report_verification_check_rows'] = $this->build_check_rows( $audit_report_verification );
		$view_data['preflight_status'] = $this->build_readiness_status( $preflight, 'readiness', 'generated_at' );
		$view_data['preflight_check_rows'] = $this->build_check_rows( $preflight );
		$view_data['update_candidate_rows'] = $this->build_update_candidate_rows( $update_candidates );
		$view_data['last_update_plan_status'] = $this->build_readiness_status( $last_plan, 'status', 'created_at' );
		$view_data['last_update_plan_target_rows'] = $this->build_update_plan_target_rows( $last_plan );

		return $view_data;
	}

	/**
	 * Add derived restore result state used by validation and planning sections.
	 *
	 * @param array $view_data Normalized screen state.
	 * @return array
	 */
	protected function with_restore_result_state( array $view_data ) {
		$last_restore_dry_run = $this->array_value( $view_data, 'last_restore_dry_run' );
		$last_restore_stage   = $this->array_value( $view_data, 'last_restore_stage' );
		$last_restore_plan    = $this->array_value( $view_data, 'last_restore_plan' );
		$last_restore_execution = $this->array_value( $view_data, 'last_restore_execution' );
		$last_restore_rollback = $this->array_value( $view_data, 'last_restore_rollback' );

		$view_data['restore_dry_run_status'] = $this->build_restore_result_status( $last_restore_dry_run );
		$view_data['restore_dry_run_check_rows'] = $this->build_check_rows( $last_restore_dry_run );
		$view_data['restore_stage_status'] = $this->build_restore_result_status( $last_restore_stage );
		$view_data['restore_stage_check_rows'] = $this->build_check_rows( $last_restore_stage );
		$view_data['restore_plan_status'] = $this->build_restore_result_status( $last_restore_plan );
		$view_data['restore_plan_check_rows'] = $this->build_check_rows( $last_restore_plan );
		$view_data['restore_plan_item_rows'] = $this->build_restore_plan_item_rows( $last_restore_plan );
		$view_data['restore_impact_summary_state'] = $this->build_restore_impact_summary_state( $this->array_value( $view_data, 'restore_impact_summary' ) );
		$view_data['restore_execution_status'] = $this->build_execution_result_status( $last_restore_execution );
		$view_data['restore_execution_meta'] = $this->build_restore_run_meta( $last_restore_execution, 'restore-execution-journal' );
		$view_data['restore_execution_health_status'] = $this->build_health_verification_status( $this->array_value( $last_restore_execution, 'health_verification' ) );
		$view_data['restore_execution_health_check_rows'] = $this->build_check_rows( $this->array_value( $last_restore_execution, 'health_verification' ) );
		$view_data['restore_execution_check_rows'] = $this->build_check_rows( $last_restore_execution );
		$view_data['restore_execution_item_rows'] = $this->build_execution_item_rows( $last_restore_execution );
		$view_data['restore_execution_journal_rows'] = $this->build_journal_rows( $last_restore_execution );
		$view_data['restore_rollback_status'] = $this->build_execution_result_status( $last_restore_rollback );
		$view_data['restore_rollback_meta'] = $this->build_restore_run_meta( $last_restore_rollback, 'restore-rollback-journal' );
		$view_data['restore_rollback_health_status'] = $this->build_health_verification_status( $this->array_value( $last_restore_rollback, 'health_verification' ) );
		$view_data['restore_rollback_check_rows'] = $this->build_check_rows( $last_restore_rollback );
		$view_data['restore_rollback_item_rows'] = $this->build_execution_item_rows( $last_restore_rollback );
		$view_data['restore_rollback_journal_rows'] = $this->build_journal_rows( $last_restore_rollback );

		return $view_data;
	}

	/**
	 * Add derived checkpoint summary rows.
	 *
	 * @param array $view_data Normalized screen state.
	 * @return array
	 */
	protected function with_checkpoint_summary_state( array $view_data ) {
		$view_data['execution_checkpoint_summary_rows'] = $this->build_execution_checkpoint_summary_rows( $this->array_value( $view_data, 'execution_checkpoint_summary' ) );
		$view_data['rollback_checkpoint_summary_rows']  = $this->build_rollback_checkpoint_summary_rows( $this->array_value( $view_data, 'rollback_checkpoint_summary' ) );

		return $view_data;
	}

	/**
	 * Add derived restore form and resume presentation state.
	 *
	 * @param array $view_data Normalized screen state.
	 * @return array
	 */
	protected function with_form_state( array $view_data ) {
		$snapshot_detail                 = $this->nullable_array_value( $view_data, 'snapshot_detail' );
		$last_restore_plan               = $this->array_value( $view_data, 'last_restore_plan' );
		$last_restore_execution          = $this->array_value( $view_data, 'last_restore_execution' );
		$execution_checkpoint            = $this->array_value( $view_data, 'execution_checkpoint' );
		$restore_resume_context          = $this->array_value( $view_data, 'restore_resume_context' );
		$restore_rollback_resume_context = $this->array_value( $view_data, 'restore_rollback_resume_context' );
		$snapshot_id                     = is_array( $snapshot_detail ) && ! empty( $snapshot_detail['id'] ) ? (int) $snapshot_detail['id'] : 0;

		$restore_confirmation_phrase  = isset( $last_restore_plan['confirmation_phrase'] ) ? (string) $last_restore_plan['confirmation_phrase'] : ( $snapshot_id > 0 ? sprintf( 'RESTORE SNAPSHOT %d', $snapshot_id ) : '' );
		$rollback_confirmation_phrase = isset( $last_restore_execution['rollback_confirmation_phrase'] ) ? (string) $last_restore_execution['rollback_confirmation_phrase'] : ( $snapshot_id > 0 ? sprintf( 'ROLLBACK SNAPSHOT %d', $snapshot_id ) : '' );

		$view_data['restore_form_state'] = array(
			'restore_confirmation_phrase'  => $restore_confirmation_phrase,
			'rollback_confirmation_phrase' => $rollback_confirmation_phrase,
			'restore_resume_message'       => $this->build_restore_resume_message( $restore_resume_context ),
			'restore_resume_run_label'     => $this->build_run_label( $restore_resume_context ),
			'rollback_resume_message'      => $this->build_rollback_resume_message( $restore_rollback_resume_context ),
			'rollback_checkpoint_message'  => $this->build_rollback_checkpoint_message( $restore_rollback_resume_context ),
			'rollback_resume_run_label'    => $this->build_run_label( $restore_rollback_resume_context ),
			'has_execution_checkpoint'     => isset( $execution_checkpoint['checkpoint'] ) && is_array( $execution_checkpoint['checkpoint'] ),
		);

		return $view_data;
	}

	/**
	 * Build the restore execution resume summary.
	 *
	 * @param array $resume_context Resume context payload.
	 * @return string
	 */
	protected function build_restore_resume_message( array $resume_context ) {
		return sprintf(
			/* translators: 1: completed item count, 2: journal entry count */
			__( 'A resumable execution journal exists with %1$d completed items across %2$d persisted entries.', 'zignites-sentinel' ),
			isset( $resume_context['completed_item_count'] ) ? (int) $resume_context['completed_item_count'] : 0,
			isset( $resume_context['entry_count'] ) ? (int) $resume_context['entry_count'] : 0
		);
	}

	/**
	 * Build the restore rollback resume summary.
	 *
	 * @param array $resume_context Resume context payload.
	 * @return string
	 */
	protected function build_rollback_resume_message( array $resume_context ) {
		return sprintf(
			/* translators: 1: completed item count, 2: journal entry count */
			__( 'A resumable rollback journal exists with %1$d completed items across %2$d persisted entries.', 'zignites-sentinel' ),
			isset( $resume_context['completed_item_count'] ) ? (int) $resume_context['completed_item_count'] : 0,
			isset( $resume_context['entry_count'] ) ? (int) $resume_context['entry_count'] : 0
		);
	}

	/**
	 * Build the rollback checkpoint resume summary.
	 *
	 * @param array $resume_context Resume context payload.
	 * @return string
	 */
	protected function build_rollback_checkpoint_message( array $resume_context ) {
		if ( empty( $resume_context['checkpoint_item_count'] ) ) {
			return '';
		}

		return sprintf(
			/* translators: 1: completed count, 2: tracked item count */
			__( 'Rollback checkpoint state currently tracks %1$d completed items across %2$d item checkpoints.', 'zignites-sentinel' ),
			isset( $resume_context['checkpoint_completed_count'] ) ? (int) $resume_context['checkpoint_completed_count'] : 0,
			(int) $resume_context['checkpoint_item_count']
		);
	}

	/**
	 * Build a display label for a resumable run id.
	 *
	 * @param array $resume_context Resume context payload.
	 * @return string
	 */
	protected function build_run_label( array $resume_context ) {
		if ( empty( $resume_context['run_id'] ) ) {
			return '';
		}

		return sprintf(
			/* translators: %s: restore run id */
			__( 'Run ID: %s', 'zignites-sentinel' ),
			(string) $resume_context['run_id']
		);
	}

	/**
	 * Build normalized operator checklist status.
	 *
	 * @param array $operator_checklist Operator checklist payload.
	 * @return array
	 */
	protected function build_operator_checklist_status( array $operator_checklist ) {
		$can_execute = ! empty( $operator_checklist['can_execute'] );

		return array(
			'badge'   => $can_execute ? 'info' : 'critical',
			'label'   => $can_execute ? __( 'Ready', 'zignites-sentinel' ) : __( 'Blocked', 'zignites-sentinel' ),
			'check_count_label' => ! empty( $operator_checklist['checks'] ) && is_array( $operator_checklist['checks'] )
				? sprintf(
					/* translators: %d: checklist check count */
					__( '%d checks', 'zignites-sentinel' ),
					count( $operator_checklist['checks'] )
				)
				: __( 'Not started', 'zignites-sentinel' ),
			'message' => sprintf(
				/* translators: %d: max age in hours */
				__( 'Live restore is offered only when all checklist gates pass and checkpoints are no older than %d hours.', 'zignites-sentinel' ),
				isset( $operator_checklist['max_age_hours'] ) ? (int) $operator_checklist['max_age_hours'] : 24
			),
		);
	}

	/**
	 * Build normalized status state for readiness-style result payloads.
	 *
	 * @param array  $payload      Result payload.
	 * @param string $status_key   Status key to read.
	 * @param string $timestamp_key Timestamp key to read.
	 * @return array
	 */
	protected function build_readiness_status( array $payload, $status_key, $timestamp_key ) {
		$status = isset( $payload[ $status_key ] ) ? (string) $payload[ $status_key ] : '';

		return array(
			'status'       => $status,
			'status_label' => $this->humanize_status( $status ),
			'badge'        => 'blocked' === $status || 'blocked_for_review' === $status ? 'critical' : ( 'caution' === $status ? 'warning' : 'info' ),
			'generated_at' => isset( $payload[ $timestamp_key ] ) ? (string) $payload[ $timestamp_key ] : '',
			'note'         => isset( $payload['note'] ) ? (string) $payload['note'] : '',
		);
	}

	/**
	 * Build normalized pending update candidate rows.
	 *
	 * @param array $candidates Update candidates.
	 * @return array
	 */
	protected function build_update_candidate_rows( array $candidates ) {
		$rows = array();

		foreach ( $candidates as $candidate ) {
			$type = isset( $candidate['type'] ) ? (string) $candidate['type'] : '';

			$rows[] = array(
				'key'             => isset( $candidate['key'] ) ? (string) $candidate['key'] : '',
				'type_label'      => ucfirst( $type ),
				'label'           => isset( $candidate['label'] ) ? (string) $candidate['label'] : '',
				'current_version' => isset( $candidate['current_version'] ) ? (string) $candidate['current_version'] : '',
				'new_version'     => isset( $candidate['new_version'] ) ? (string) $candidate['new_version'] : '',
			);
		}

		return $rows;
	}

	/**
	 * Build normalized update plan target rows.
	 *
	 * @param array $last_plan Last update plan payload.
	 * @return array
	 */
	protected function build_update_plan_target_rows( array $last_plan ) {
		$targets = isset( $last_plan['targets'] ) && is_array( $last_plan['targets'] ) ? $last_plan['targets'] : array();
		$rows    = array();

		foreach ( $targets as $target ) {
			$type = isset( $target['type'] ) ? (string) $target['type'] : '';

			$rows[] = array(
				'type_label'      => ucfirst( $type ),
				'label'           => isset( $target['label'] ) ? (string) $target['label'] : '',
				'current_version' => isset( $target['current_version'] ) ? (string) $target['current_version'] : '',
				'new_version'     => isset( $target['new_version'] ) ? (string) $target['new_version'] : '',
			);
		}

		return $rows;
	}

	/**
	 * Build snapshot basics rows.
	 *
	 * @param array|null $snapshot_detail Snapshot detail payload.
	 * @return array
	 */
	protected function build_snapshot_basic_rows( $snapshot_detail ) {
		if ( ! is_array( $snapshot_detail ) ) {
			return array();
		}

		return array(
			array(
				'label' => __( 'Label', 'zignites-sentinel' ),
				'value' => isset( $snapshot_detail['label'] ) ? (string) $snapshot_detail['label'] : '',
			),
			array(
				'label' => __( 'Created', 'zignites-sentinel' ),
				'value' => isset( $snapshot_detail['created_at'] ) ? (string) $snapshot_detail['created_at'] : '',
			),
			array(
				'label' => __( 'Theme', 'zignites-sentinel' ),
				'value' => isset( $snapshot_detail['theme_stylesheet'] ) ? (string) $snapshot_detail['theme_stylesheet'] : '',
			),
			array(
				'label' => __( 'Core Version', 'zignites-sentinel' ),
				'value' => isset( $snapshot_detail['core_version'] ) ? (string) $snapshot_detail['core_version'] : '',
			),
			array(
				'label' => __( 'PHP Version', 'zignites-sentinel' ),
				'value' => isset( $snapshot_detail['php_version'] ) ? (string) $snapshot_detail['php_version'] : '',
			),
		);
	}

	/**
	 * Build stored snapshot metadata rows.
	 *
	 * @param array|null $snapshot_detail Snapshot detail payload.
	 * @return array
	 */
	protected function build_snapshot_metadata_rows( $snapshot_detail ) {
		$metadata = is_array( $snapshot_detail ) && isset( $snapshot_detail['metadata_decoded'] ) && is_array( $snapshot_detail['metadata_decoded'] ) ? $snapshot_detail['metadata_decoded'] : array();
		$rows     = array();

		foreach ( $metadata as $meta_key => $meta_value ) {
			$rows[] = array(
				'label' => ucwords( str_replace( '_', ' ', (string) $meta_key ) ),
				'value' => is_scalar( $meta_value ) ? (string) $meta_value : wp_json_encode( $meta_value ),
			);
		}

		return $rows;
	}

	/**
	 * Build component manifest rows.
	 *
	 * @param array $component_manifest Component manifest payload.
	 * @return array
	 */
	protected function build_component_manifest_rows( array $component_manifest ) {
		if ( empty( $component_manifest ) ) {
			return array();
		}

		return array(
			array(
				'label' => __( 'Generated', 'zignites-sentinel' ),
				'value' => isset( $component_manifest['generated_at'] ) ? (string) $component_manifest['generated_at'] : '',
			),
			array(
				'label' => __( 'Theme Source', 'zignites-sentinel' ),
				'value' => isset( $component_manifest['theme']['source_path'] ) ? (string) $component_manifest['theme']['source_path'] : '',
			),
			array(
				'label' => __( 'Plugin Entries', 'zignites-sentinel' ),
				'value' => isset( $component_manifest['plugins'] ) && is_array( $component_manifest['plugins'] ) ? (string) count( $component_manifest['plugins'] ) : '0',
			),
		);
	}

	/**
	 * Build rollback package artifact rows.
	 *
	 * @param array $snapshot_artifacts Snapshot artifact rows.
	 * @return array
	 */
	protected function build_snapshot_artifact_rows( array $snapshot_artifacts ) {
		$rows = array();

		foreach ( $snapshot_artifacts as $artifact ) {
			$type = isset( $artifact['artifact_type'] ) ? (string) $artifact['artifact_type'] : '';

			$rows[] = array(
				'type_label'  => ucfirst( $type ),
				'label'       => isset( $artifact['label'] ) ? (string) $artifact['label'] : '',
				'key'         => isset( $artifact['artifact_key'] ) ? (string) $artifact['artifact_key'] : '',
				'version'     => isset( $artifact['version'] ) ? (string) $artifact['version'] : '',
				'source_path' => isset( $artifact['source_path'] ) ? (string) $artifact['source_path'] : '',
			);
		}

		return $rows;
	}

	/**
	 * Build artifact diff display state.
	 *
	 * @param array $artifact_diff Artifact diff payload.
	 * @return array
	 */
	protected function build_artifact_diff_state( array $artifact_diff ) {
		$items = isset( $artifact_diff['items'] ) && is_array( $artifact_diff['items'] ) ? $artifact_diff['items'] : array();
		$rows  = array();

		foreach ( $items as $item ) {
			$status = isset( $item['status'] ) ? (string) $item['status'] : '';

			$rows[] = array(
				'type_label'      => ucfirst( isset( $item['type'] ) ? (string) $item['type'] : '' ),
				'label'           => isset( $item['label'] ) ? (string) $item['label'] : '',
				'stored_version'  => isset( $item['stored_version'] ) ? (string) $item['stored_version'] : '',
				'current_version' => isset( $item['current_version'] ) ? (string) $item['current_version'] : '',
				'status'          => $status,
				'status_label'    => $this->humanize_status( $status ),
				'badge'           => 'fail' === $status ? 'critical' : $status,
				'message'         => isset( $item['message'] ) ? (string) $item['message'] : '',
			);
		}

		return array(
			'message' => isset( $artifact_diff['message'] ) ? (string) $artifact_diff['message'] : '',
			'rows'    => $rows,
		);
	}

	/**
	 * Build active plugin rows from the selected snapshot.
	 *
	 * @param array|null $snapshot_detail Snapshot detail payload.
	 * @return array
	 */
	protected function build_active_plugin_rows( $snapshot_detail ) {
		$plugins = is_array( $snapshot_detail ) && isset( $snapshot_detail['active_plugins_decoded'] ) && is_array( $snapshot_detail['active_plugins_decoded'] ) ? $snapshot_detail['active_plugins_decoded'] : array();
		$rows    = array();

		foreach ( $plugins as $plugin_state ) {
			$rows[] = array(
				'plugin'  => isset( $plugin_state['plugin'] ) ? (string) $plugin_state['plugin'] : '',
				'name'    => isset( $plugin_state['name'] ) ? (string) $plugin_state['name'] : '',
				'version' => isset( $plugin_state['version'] ) ? (string) $plugin_state['version'] : '',
			);
		}

		return $rows;
	}

	/**
	 * Build snapshot comparison rows.
	 *
	 * @param array $snapshot_comparison Snapshot comparison payload.
	 * @return array
	 */
	protected function build_snapshot_comparison_rows( array $snapshot_comparison ) {
		if ( empty( $snapshot_comparison ) ) {
			return array();
		}

		return array(
			array(
				'label' => __( 'Snapshot Theme', 'zignites-sentinel' ),
				'value' => isset( $snapshot_comparison['snapshot_theme'] ) ? (string) $snapshot_comparison['snapshot_theme'] : '',
			),
			array(
				'label' => __( 'Current Theme', 'zignites-sentinel' ),
				'value' => isset( $snapshot_comparison['current_theme'] ) ? (string) $snapshot_comparison['current_theme'] : '',
			),
			array(
				'label' => __( 'Snapshot Core', 'zignites-sentinel' ),
				'value' => isset( $snapshot_comparison['snapshot_core_version'] ) ? (string) $snapshot_comparison['snapshot_core_version'] : '',
			),
			array(
				'label' => __( 'Current Core', 'zignites-sentinel' ),
				'value' => isset( $snapshot_comparison['current_core_version'] ) ? (string) $snapshot_comparison['current_core_version'] : '',
			),
			array(
				'label' => __( 'Snapshot PHP', 'zignites-sentinel' ),
				'value' => isset( $snapshot_comparison['snapshot_php_version'] ) ? (string) $snapshot_comparison['snapshot_php_version'] : '',
			),
			array(
				'label' => __( 'Current PHP', 'zignites-sentinel' ),
				'value' => isset( $snapshot_comparison['current_php_version'] ) ? (string) $snapshot_comparison['current_php_version'] : '',
			),
		);
	}

	/**
	 * Build compact labels from plugin state rows.
	 *
	 * @param array $plugin_states Plugin state rows.
	 * @return array
	 */
	protected function build_plugin_state_labels( array $plugin_states ) {
		$labels = array();

		foreach ( $plugin_states as $plugin_state ) {
			$labels[] = isset( $plugin_state['name'] ) && $plugin_state['name'] ? (string) $plugin_state['name'] : ( isset( $plugin_state['plugin'] ) ? (string) $plugin_state['plugin'] : '' );
		}

		return array_values( array_filter( $labels, 'strlen' ) );
	}

	/**
	 * Build plugin version-change rows from snapshot comparison.
	 *
	 * @param array $snapshot_comparison Snapshot comparison payload.
	 * @return array
	 */
	protected function build_plugin_version_change_rows( array $snapshot_comparison ) {
		$changes = isset( $snapshot_comparison['version_changes'] ) && is_array( $snapshot_comparison['version_changes'] ) ? $snapshot_comparison['version_changes'] : array();
		$rows    = array();

		foreach ( $changes as $change ) {
			$rows[] = array(
				'name'             => isset( $change['name'] ) ? (string) $change['name'] : '',
				'snapshot_version' => isset( $change['snapshot_version'] ) ? (string) $change['snapshot_version'] : '',
				'current_version'  => isset( $change['current_version'] ) ? (string) $change['current_version'] : '',
			);
		}

		return $rows;
	}

	/**
	 * Build normalized health baseline status.
	 *
	 * @param array $baseline Snapshot health baseline payload.
	 * @return array
	 */
	protected function build_snapshot_health_baseline_status( array $baseline ) {
		$status = isset( $baseline['status'] ) ? (string) $baseline['status'] : '';

		return array(
			'status'       => $status,
			'status_label' => isset( $baseline['status_label'] ) ? (string) $baseline['status_label'] : $this->humanize_status( $status ),
			'badge'        => isset( $baseline['status_pill'] ) ? (string) $baseline['status_pill'] : ( 'unhealthy' === $status ? 'critical' : ( 'degraded' === $status ? 'warning' : 'info' ) ),
			'generated_at' => isset( $baseline['generated_at'] ) ? (string) $baseline['generated_at'] : '',
			'note'         => isset( $baseline['note'] ) ? (string) $baseline['note'] : '',
		);
	}

	/**
	 * Build normalized health comparison rows.
	 *
	 * @param array $comparison_rows Raw health comparison rows.
	 * @return array
	 */
	protected function build_snapshot_health_comparison_rows( array $comparison_rows ) {
		$rows = array();

		foreach ( $comparison_rows as $row ) {
			$summary = isset( $row['summary'] ) && is_array( $row['summary'] ) ? $row['summary'] : array();

			$rows[] = array(
				'label'        => isset( $row['label'] ) ? (string) $row['label'] : '',
				'status_label' => isset( $row['status_label'] ) ? (string) $row['status_label'] : '',
				'badge'        => isset( $row['status_pill'] ) ? (string) $row['status_pill'] : 'info',
				'generated_at' => isset( $row['generated_at'] ) ? (string) $row['generated_at'] : '',
				'pass_count'   => isset( $summary['pass'] ) ? (string) $summary['pass'] : '0',
				'warning_count' => isset( $summary['warning'] ) ? (string) $summary['warning'] : '0',
				'fail_count'   => isset( $summary['fail'] ) ? (string) $summary['fail'] : '0',
				'delta'        => isset( $row['delta'] ) ? (string) $row['delta'] : '',
			);
		}

		return $rows;
	}

	/**
	 * Build restore action jump-link rows.
	 *
	 * @param array $last_restore_dry_run Latest dry-run payload.
	 * @param array $last_restore_stage   Latest staged validation payload.
	 * @param array $last_restore_plan    Latest restore plan payload.
	 * @return array
	 */
	protected function build_restore_action_jump_links( array $last_restore_dry_run, array $last_restore_stage, array $last_restore_plan ) {
		$links = array();

		if ( ! empty( $last_restore_dry_run ) ) {
			$links[] = array(
				'href'  => '#znts-restore-dry-run',
				'label' => __( 'Dry-Run', 'zignites-sentinel' ),
			);
		}

		if ( ! empty( $last_restore_stage ) ) {
			$links[] = array(
				'href'  => '#znts-restore-stage',
				'label' => __( 'Staged Validation', 'zignites-sentinel' ),
			);
		}

		if ( ! empty( $last_restore_plan ) ) {
			$links[] = array(
				'href'  => '#znts-restore-plan',
				'label' => __( 'Restore Plan', 'zignites-sentinel' ),
			);
		}

		return $links;
	}

	/**
	 * Build normalized snapshot activity rows.
	 *
	 * @param array $snapshot_activity Snapshot activity payload.
	 * @return array
	 */
	protected function build_snapshot_activity_rows( array $snapshot_activity ) {
		$rows = array();

		foreach ( $snapshot_activity as $activity ) {
			$severity = isset( $activity['severity'] ) ? (string) $activity['severity'] : 'info';

			$rows[] = array(
				'created_at'     => isset( $activity['created_at'] ) ? (string) $activity['created_at'] : '',
				'detail_url'     => isset( $activity['detail_url'] ) ? (string) $activity['detail_url'] : '',
				'severity'       => $severity,
				'severity_label' => $this->humanize_status( $severity ),
				'severity_badge' => 'fail' === $severity ? 'critical' : $severity,
				'source'         => isset( $activity['source'] ) ? (string) $activity['source'] : '',
				'event_type'     => isset( $activity['event_type'] ) ? (string) $activity['event_type'] : '',
				'message'        => isset( $activity['message'] ) ? (string) $activity['message'] : '',
				'journal_url'    => isset( $activity['journal_url'] ) ? (string) $activity['journal_url'] : '',
				'journal_label'  => isset( $activity['journal_label'] ) ? (string) $activity['journal_label'] : __( 'Event detail', 'zignites-sentinel' ),
			);
		}

		return $rows;
	}

	/**
	 * Build normalized snapshot list rows.
	 *
	 * @param array $recent_snapshots Snapshot list items.
	 * @param array $status_index     Snapshot status index keyed by snapshot ID.
	 * @return array
	 */
	protected function build_recent_snapshot_rows( array $recent_snapshots, array $status_index ) {
		$rows = array();

		foreach ( $recent_snapshots as $snapshot ) {
			$snapshot_id     = isset( $snapshot['id'] ) ? (int) $snapshot['id'] : 0;
			$snapshot_status = isset( $status_index[ $snapshot_id ] ) && is_array( $status_index[ $snapshot_id ] ) ? $status_index[ $snapshot_id ] : array();

			$rows[] = array(
				'id'            => $snapshot_id,
				'created_at'    => isset( $snapshot['created_at'] ) ? (string) $snapshot['created_at'] : '',
				'label'         => isset( $snapshot['label'] ) ? (string) $snapshot['label'] : '',
				'detail_url'    => $this->build_snapshot_detail_url( $snapshot_id ),
				'status_badges' => $this->build_snapshot_status_badge_rows( $snapshot_status ),
				'core_version'  => isset( $snapshot['core_version'] ) ? (string) $snapshot['core_version'] : '',
				'php_version'   => isset( $snapshot['php_version'] ) ? (string) $snapshot['php_version'] : '',
			);
		}

		return $rows;
	}

	/**
	 * Build normalized snapshot status badge rows.
	 *
	 * @param array $snapshot_status Snapshot status payload.
	 * @return array
	 */
	protected function build_snapshot_status_badge_rows( array $snapshot_status ) {
		$badges = array();
		$rows   = isset( $snapshot_status['status_badges'] ) && is_array( $snapshot_status['status_badges'] ) ? $snapshot_status['status_badges'] : array();

		foreach ( $rows as $badge ) {
			$badges[] = array(
				'badge' => isset( $badge['badge'] ) ? (string) $badge['badge'] : 'info',
				'label' => isset( $badge['label'] ) ? (string) $badge['label'] : '',
			);
		}

		return $badges;
	}

	/**
	 * Build snapshot pagination summary copy.
	 *
	 * @param array $snapshot_pagination Snapshot pagination payload.
	 * @return string
	 */
	protected function build_snapshot_pagination_summary( array $snapshot_pagination ) {
		$total_items = isset( $snapshot_pagination['total_items'] ) ? (int) $snapshot_pagination['total_items'] : 0;

		if ( $total_items <= 0 ) {
			return '';
		}

		return sprintf(
			/* translators: 1: current page, 2: total pages, 3: total items */
			__( 'Page %1$d of %2$d, %3$d snapshots matched.', 'zignites-sentinel' ),
			isset( $snapshot_pagination['current_page'] ) ? max( 1, (int) $snapshot_pagination['current_page'] ) : 1,
			isset( $snapshot_pagination['total_pages'] ) ? max( 1, (int) $snapshot_pagination['total_pages'] ) : 1,
			$total_items
		);
	}

	/**
	 * Build snapshot list clear-filter URL.
	 *
	 * @param array|null $snapshot_detail Selected snapshot detail.
	 * @return string
	 */
	protected function build_snapshot_filter_clear_url( $snapshot_detail ) {
		$args = array(
			'page' => 'zignites-sentinel-update-readiness',
		);

		if ( is_array( $snapshot_detail ) && ! empty( $snapshot_detail['id'] ) ) {
			$args['snapshot_id'] = (int) $snapshot_detail['id'];
		}

		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	/**
	 * Build snapshot pagination link arguments.
	 *
	 * @param array      $snapshot_pagination    Snapshot pagination payload.
	 * @param array|null $snapshot_detail        Selected snapshot detail.
	 * @param string     $snapshot_search        Snapshot search filter.
	 * @param string     $snapshot_status_filter Snapshot status filter.
	 * @return array
	 */
	protected function build_snapshot_pagination_links_args( array $snapshot_pagination, $snapshot_detail, $snapshot_search, $snapshot_status_filter ) {
		$total_pages = isset( $snapshot_pagination['total_pages'] ) ? max( 1, (int) $snapshot_pagination['total_pages'] ) : 1;

		if ( $total_pages <= 1 ) {
			return array();
		}

		$base_args = array(
			'page' => 'zignites-sentinel-update-readiness',
		);

		if ( is_array( $snapshot_detail ) && ! empty( $snapshot_detail['id'] ) ) {
			$base_args['snapshot_id'] = (int) $snapshot_detail['id'];
		}

		if ( '' !== $snapshot_search ) {
			$base_args['snapshot_search'] = (string) $snapshot_search;
		}

		if ( '' !== $snapshot_status_filter ) {
			$base_args['snapshot_status_filter'] = (string) $snapshot_status_filter;
		}

		return array(
			'base'      => add_query_arg( $base_args + array( 'snapshot_paged' => '%#%' ), admin_url( 'admin.php' ) ),
			'format'    => '',
			'current'   => isset( $snapshot_pagination['current_page'] ) ? max( 1, (int) $snapshot_pagination['current_page'] ) : 1,
			'total'     => $total_pages,
			'type'      => 'plain',
			'prev_text' => __( '&laquo;', 'zignites-sentinel' ),
			'next_text' => __( '&raquo;', 'zignites-sentinel' ),
		);
	}

	/**
	 * Build snapshot detail URL.
	 *
	 * @param int $snapshot_id Snapshot ID.
	 * @return string
	 */
	protected function build_snapshot_detail_url( $snapshot_id ) {
		return add_query_arg(
			array(
				'page'        => 'zignites-sentinel-update-readiness',
				'snapshot_id' => (int) $snapshot_id,
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Build normalized settings form state.
	 *
	 * @param array $settings Current settings payload.
	 * @return array
	 */
	protected function build_settings_form_state( array $settings ) {
		return array(
			'logging_enabled'                  => array_key_exists( 'logging_enabled', $settings ) ? ! empty( $settings['logging_enabled'] ) : true,
			'delete_data_on_uninstall'         => array_key_exists( 'delete_data_on_uninstall', $settings ) ? ! empty( $settings['delete_data_on_uninstall'] ) : true,
			'auto_snapshot_on_plan'            => array_key_exists( 'auto_snapshot_on_plan', $settings ) ? ! empty( $settings['auto_snapshot_on_plan'] ) : true,
			'snapshot_retention_days'          => isset( $settings['snapshot_retention_days'] ) ? (string) max( 1, (int) $settings['snapshot_retention_days'] ) : '30',
			'restore_checkpoint_max_age_hours' => isset( $settings['restore_checkpoint_max_age_hours'] ) ? (string) max( 1, (int) $settings['restore_checkpoint_max_age_hours'] ) : '24',
		);
	}

	/**
	 * Build execution checkpoint summary rows.
	 *
	 * @param array $summary Execution checkpoint summary.
	 * @return array
	 */
	protected function build_execution_checkpoint_summary_rows( array $summary ) {
		if ( empty( $summary ) ) {
			return array();
		}

		$rows = array(
			array(
				'label' => __( 'Run ID', 'zignites-sentinel' ),
				'value' => isset( $summary['run_id'] ) ? (string) $summary['run_id'] : '',
			),
			array(
				'label' => __( 'Generated', 'zignites-sentinel' ),
				'value' => isset( $summary['generated_at'] ) ? (string) $summary['generated_at'] : '',
			),
			array(
				'label' => __( 'Stage Reuse', 'zignites-sentinel' ),
				'value' => ! empty( $summary['stage_ready'] ) ? __( 'Ready', 'zignites-sentinel' ) : __( 'Not available', 'zignites-sentinel' ),
			),
			array(
				'label' => __( 'Stage Path', 'zignites-sentinel' ),
				'value' => isset( $summary['stage_path'] ) ? (string) $summary['stage_path'] : '',
			),
			array(
				'label' => __( 'Health Reuse', 'zignites-sentinel' ),
				'value' => ! empty( $summary['health_completed'] ) ? __( 'Ready', 'zignites-sentinel' ) : __( 'Health will rerun', 'zignites-sentinel' ),
			),
			array(
				'label' => __( 'Backed-Up Items', 'zignites-sentinel' ),
				'value' => isset( $summary['backup_count'] ) ? (string) $summary['backup_count'] : '0',
			),
			array(
				'label' => __( 'Written Items', 'zignites-sentinel' ),
				'value' => isset( $summary['write_count'] ) ? (string) $summary['write_count'] : '0',
			),
			array(
				'label' => __( 'Tracked Items', 'zignites-sentinel' ),
				'value' => isset( $summary['item_count'] ) ? (string) $summary['item_count'] : '0',
			),
			array(
				'label' => __( 'Failed Items', 'zignites-sentinel' ),
				'value' => isset( $summary['failed_count'] ) ? (string) $summary['failed_count'] : '0',
			),
		);

		if ( ! empty( $summary['phase_counts'] ) && is_array( $summary['phase_counts'] ) ) {
			$rows[] = array(
				'label' => __( 'Checkpoint Phases', 'zignites-sentinel' ),
				'value' => $this->format_phase_counts( $summary['phase_counts'] ),
			);
		}

		if ( ! empty( $summary['health_status'] ) ) {
			$rows[] = array(
				'label' => __( 'Stored Health Status', 'zignites-sentinel' ),
				'value' => (string) $summary['health_status'],
			);
		}

		return $rows;
	}

	/**
	 * Build rollback checkpoint summary rows.
	 *
	 * @param array $summary Rollback checkpoint summary.
	 * @return array
	 */
	protected function build_rollback_checkpoint_summary_rows( array $summary ) {
		if ( empty( $summary ) ) {
			return array();
		}

		$rows = array(
			array(
				'label' => __( 'Run ID', 'zignites-sentinel' ),
				'value' => isset( $summary['run_id'] ) ? (string) $summary['run_id'] : '',
			),
			array(
				'label' => __( 'Generated', 'zignites-sentinel' ),
				'value' => isset( $summary['generated_at'] ) ? (string) $summary['generated_at'] : '',
			),
			array(
				'label' => __( 'Backup Root', 'zignites-sentinel' ),
				'value' => isset( $summary['backup_root'] ) ? (string) $summary['backup_root'] : '',
			),
			array(
				'label' => __( 'Tracked Items', 'zignites-sentinel' ),
				'value' => isset( $summary['item_count'] ) ? (string) $summary['item_count'] : '0',
			),
			array(
				'label' => __( 'Completed Items', 'zignites-sentinel' ),
				'value' => isset( $summary['completed_count'] ) ? (string) $summary['completed_count'] : '0',
			),
			array(
				'label' => __( 'Failed Items', 'zignites-sentinel' ),
				'value' => isset( $summary['failed_count'] ) ? (string) $summary['failed_count'] : '0',
			),
		);

		if ( ! empty( $summary['phase_counts'] ) && is_array( $summary['phase_counts'] ) ) {
			$rows[] = array(
				'label' => __( 'Checkpoint Phases', 'zignites-sentinel' ),
				'value' => $this->format_phase_counts( $summary['phase_counts'] ),
			);
		}

		return $rows;
	}

	/**
	 * Format checkpoint phase counts for display.
	 *
	 * @param array $phase_counts Phase count map.
	 * @return string
	 */
	protected function format_phase_counts( array $phase_counts ) {
		$labels = array();

		foreach ( $phase_counts as $phase => $count ) {
			$labels[] = sprintf( '%s (%d)', str_replace( '_', ' ', (string) $phase ), (int) $count );
		}

		return implode( ', ', $labels );
	}

	/**
	 * Build normalized status state for restore validation/planning payloads.
	 *
	 * @param array $payload Restore result payload.
	 * @return array
	 */
	protected function build_restore_result_status( array $payload ) {
		$status = isset( $payload['status'] ) ? (string) $payload['status'] : '';

		return array(
			'status'       => $status,
			'status_label' => $this->humanize_status( $status ),
			'badge'        => 'blocked' === $status ? 'critical' : ( 'caution' === $status ? 'warning' : 'info' ),
			'generated_at' => isset( $payload['generated_at'] ) ? (string) $payload['generated_at'] : '',
			'note'         => isset( $payload['note'] ) ? (string) $payload['note'] : '',
		);
	}

	/**
	 * Build normalized restore-plan item rows.
	 *
	 * @param array $plan Restore plan payload.
	 * @return array
	 */
	protected function build_restore_plan_item_rows( array $plan ) {
		$items = isset( $plan['items'] ) && is_array( $plan['items'] ) ? $plan['items'] : array();
		$rows  = array();

		foreach ( $items as $item ) {
			$type   = isset( $item['type'] ) ? (string) $item['type'] : '';
			$action = isset( $item['action'] ) ? (string) $item['action'] : '';

			$rows[] = array(
				'type_label'     => ucfirst( $type ),
				'label'          => isset( $item['label'] ) ? (string) $item['label'] : '',
				'action_label'   => ucfirst( $action ),
				'target_path'    => isset( $item['target_path'] ) ? (string) $item['target_path'] : '',
				'conflict_count' => isset( $item['conflict_count'] ) ? (string) $item['conflict_count'] : '0',
				'message'        => isset( $item['message'] ) ? (string) $item['message'] : '',
			);
		}

		return $rows;
	}

	/**
	 * Build normalized restore impact summary state.
	 *
	 * @param array $summary Restore impact summary payload.
	 * @return array
	 */
	protected function build_restore_impact_summary_state( array $summary ) {
		$rows     = array();
		$blockers = array();

		foreach ( isset( $summary['rows'] ) && is_array( $summary['rows'] ) ? $summary['rows'] : array() as $row ) {
			$rows[] = array(
				'label' => isset( $row['label'] ) ? (string) $row['label'] : '',
				'value' => isset( $row['value'] ) ? (string) $row['value'] : '',
			);
		}

		foreach ( isset( $summary['blockers'] ) && is_array( $summary['blockers'] ) ? $summary['blockers'] : array() as $blocker ) {
			$label   = isset( $blocker['label'] ) ? (string) $blocker['label'] : __( 'Requirement', 'zignites-sentinel' );
			$message = isset( $blocker['message'] ) ? (string) $blocker['message'] : '';

			$blockers[] = array(
				'label'   => $label,
				'message' => $message,
				'display' => sprintf(
					/* translators: 1: blocker label, 2: blocker message */
					__( '%1$s: %2$s', 'zignites-sentinel' ),
					$label,
					$message
				),
			);
		}

		return array(
			'is_visible' => ! empty( $summary ),
			'status'     => isset( $summary['status'] ) ? (string) $summary['status'] : 'info',
			'title'      => isset( $summary['title'] ) ? (string) $summary['title'] : '',
			'message'    => isset( $summary['message'] ) ? (string) $summary['message'] : '',
			'rows'       => $rows,
			'blockers'   => $blockers,
		);
	}

	/**
	 * Build restore execution or rollback run metadata.
	 *
	 * @param array  $payload Restore execution or rollback payload.
	 * @param string $source  Event Log source filter for the run.
	 * @return array
	 */
	protected function build_restore_run_meta( array $payload, $source ) {
		$run_id      = isset( $payload['run_id'] ) ? (string) $payload['run_id'] : '';
		$backup_root = isset( $payload['backup_root'] ) ? (string) $payload['backup_root'] : '';

		return array(
			'run_id'          => $run_id,
			'run_url'         => '' !== $run_id ? add_query_arg(
				array(
					'page'   => 'zignites-sentinel-event-logs',
					'source' => (string) $source,
					'run_id' => $run_id,
				),
				admin_url( 'admin.php' )
			) : '',
			'backup_root'     => $backup_root,
			'has_backup_root' => '' !== $backup_root,
			'resumed_message' => ! empty( $payload['resumed_run'] ) ? __( 'This execution reused persisted journal state from a prior run.', 'zignites-sentinel' ) : '',
		);
	}

	/**
	 * Build normalized status state for restore execution and rollback payloads.
	 *
	 * @param array $payload Restore execution or rollback payload.
	 * @return array
	 */
	protected function build_execution_result_status( array $payload ) {
		$status = isset( $payload['status'] ) ? (string) $payload['status'] : '';

		return array(
			'status'       => $status,
			'status_label' => $this->humanize_status( $status ),
			'badge'        => 'blocked' === $status ? 'critical' : ( 'partial' === $status ? 'warning' : 'info' ),
			'generated_at' => isset( $payload['generated_at'] ) ? (string) $payload['generated_at'] : '',
			'note'         => isset( $payload['note'] ) ? (string) $payload['note'] : '',
		);
	}

	/**
	 * Build normalized health verification status state.
	 *
	 * @param array $payload Health verification payload.
	 * @return array
	 */
	protected function build_health_verification_status( array $payload ) {
		$status = isset( $payload['status'] ) ? (string) $payload['status'] : '';

		return array(
			'status'       => $status,
			'status_label' => $this->humanize_status( $status ),
			'badge'        => 'unhealthy' === $status ? 'critical' : ( 'degraded' === $status ? 'warning' : 'info' ),
			'generated_at' => isset( $payload['generated_at'] ) ? (string) $payload['generated_at'] : '',
			'note'         => isset( $payload['note'] ) ? (string) $payload['note'] : '',
		);
	}

	/**
	 * Build normalized restore execution/rollback item rows.
	 *
	 * @param array $payload Restore execution or rollback payload.
	 * @return array
	 */
	protected function build_execution_item_rows( array $payload ) {
		$items = isset( $payload['items'] ) && is_array( $payload['items'] ) ? $payload['items'] : array();
		$rows  = array();

		foreach ( $items as $item ) {
			$action = isset( $item['action'] ) ? (string) $item['action'] : '';
			$status = isset( $item['status'] ) ? (string) $item['status'] : '';

			$rows[] = array(
				'label'        => isset( $item['label'] ) ? (string) $item['label'] : '',
				'action_label' => ucfirst( $action ),
				'status'       => $status,
				'status_label' => $this->humanize_status( $status ),
				'badge'        => 'fail' === $status ? 'critical' : $status,
				'message'      => isset( $item['message'] ) ? (string) $item['message'] : '',
			);
		}

		return $rows;
	}

	/**
	 * Build normalized restore execution/rollback journal rows.
	 *
	 * @param array $payload Restore execution or rollback payload.
	 * @return array
	 */
	protected function build_journal_rows( array $payload ) {
		$entries = isset( $payload['journal'] ) && is_array( $payload['journal'] ) ? $payload['journal'] : array();
		$rows    = array();

		foreach ( $entries as $entry ) {
			$status = isset( $entry['status'] ) ? (string) $entry['status'] : '';

			$rows[] = array(
				'timestamp'    => isset( $entry['timestamp'] ) ? (string) $entry['timestamp'] : '',
				'scope'        => isset( $entry['scope'] ) ? (string) $entry['scope'] : '',
				'label'        => isset( $entry['label'] ) ? (string) $entry['label'] : '',
				'phase'        => isset( $entry['phase'] ) ? (string) $entry['phase'] : '',
				'status'       => $status,
				'status_label' => $this->humanize_status( $status ),
				'badge'        => 'fail' === $status ? 'critical' : $status,
				'message'      => isset( $entry['message'] ) ? (string) $entry['message'] : '',
			);
		}

		return $rows;
	}

	/**
	 * Build normalized rows for readiness-style validation checks.
	 *
	 * @param array $validation Validation payload.
	 * @return array
	 */
	protected function build_check_rows( array $validation ) {
		$checks = isset( $validation['checks'] ) && is_array( $validation['checks'] ) ? $validation['checks'] : array();
		$rows   = array();

		foreach ( $checks as $check ) {
			$status = isset( $check['status'] ) ? (string) $check['status'] : '';

			$rows[] = array(
				'label'        => isset( $check['label'] ) ? (string) $check['label'] : '',
				'status'       => $status,
				'status_label' => $this->humanize_status( $status ),
				'badge'        => 'fail' === $status ? 'critical' : $status,
				'message'      => isset( $check['message'] ) ? (string) $check['message'] : '',
			);
		}

		return $rows;
	}

	/**
	 * Build labels for missing snapshot plugin sources.
	 *
	 * @param array $validation Validation payload.
	 * @return array
	 */
	protected function build_missing_plugin_labels( array $validation ) {
		$checks = isset( $validation['checks'] ) && is_array( $validation['checks'] ) ? $validation['checks'] : array();
		$labels = array();

		foreach ( $checks as $check ) {
			$missing_plugins = isset( $check['details']['missing_plugins'] ) && is_array( $check['details']['missing_plugins'] ) ? $check['details']['missing_plugins'] : array();

			foreach ( $missing_plugins as $plugin_state ) {
				$labels[] = isset( $plugin_state['name'] ) && $plugin_state['name'] ? (string) $plugin_state['name'] : ( isset( $plugin_state['plugin'] ) ? (string) $plugin_state['plugin'] : '' );
			}
		}

		return array_values( array_filter( $labels, 'strlen' ) );
	}

	/**
	 * Build labels for missing stored rollback artifacts.
	 *
	 * @param array $validation Validation payload.
	 * @return array
	 */
	protected function build_missing_artifact_labels( array $validation ) {
		$checks = isset( $validation['checks'] ) && is_array( $validation['checks'] ) ? $validation['checks'] : array();
		$labels = array();

		foreach ( $checks as $check ) {
			$missing_artifacts = isset( $check['details']['missing_artifacts'] ) && is_array( $check['details']['missing_artifacts'] ) ? $check['details']['missing_artifacts'] : array();

			foreach ( $missing_artifacts as $artifact ) {
				$labels[] = isset( $artifact['label'] ) ? (string) $artifact['label'] : '';
			}
		}

		return array_values( array_filter( $labels, 'strlen' ) );
	}

	/**
	 * Convert a machine status into a compact display label.
	 *
	 * @param string $status Machine status.
	 * @return string
	 */
	protected function humanize_status( $status ) {
		return ucfirst( str_replace( '_', ' ', (string) $status ) );
	}

	/**
	 * Get an array value from a payload.
	 *
	 * @param array  $payload Payload to inspect.
	 * @param string $key     Payload key.
	 * @return array
	 */
	protected function array_value( array $payload, $key ) {
		return isset( $payload[ $key ] ) && is_array( $payload[ $key ] ) ? $payload[ $key ] : array();
	}

	/**
	 * Get a nullable array value from a payload.
	 *
	 * @param array  $payload Payload to inspect.
	 * @param string $key     Payload key.
	 * @return array|null
	 */
	protected function nullable_array_value( array $payload, $key ) {
		return isset( $payload[ $key ] ) && is_array( $payload[ $key ] ) ? $payload[ $key ] : null;
	}
}
