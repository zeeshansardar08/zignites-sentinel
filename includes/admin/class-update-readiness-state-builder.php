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

		return array(
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
