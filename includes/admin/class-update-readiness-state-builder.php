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

		return $this->with_workspace_state( $view_data );
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
				'status_label' => ucfirst( $status ),
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
