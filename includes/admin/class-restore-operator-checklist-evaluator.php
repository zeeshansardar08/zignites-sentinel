<?php
/**
 * Pure evaluator for restore operator checklist gates.
 *
 * @package ZignitesSentinel
 */

namespace Zignites\Sentinel\Admin;

defined( 'ABSPATH' ) || exit;

class RestoreOperatorChecklistEvaluator {

	/**
	 * Build the operator checklist required before live restore execution.
	 *
	 * @param array $context Gate context.
	 * @return array
	 */
	public function evaluate( array $context ) {
		$baseline_present        = ! empty( $context['baseline_present'] );
		$stage_result_ready      = ! empty( $context['stage_result_ready'] );
		$plan_result_ready       = ! empty( $context['plan_result_ready'] );
		$stage_checkpoint_exists = ! empty( $context['stage_checkpoint_exists'] );
		$plan_checkpoint_exists  = ! empty( $context['plan_checkpoint_exists'] );
		$max_age_hours           = isset( $context['max_age_hours'] ) ? max( 1, (int) $context['max_age_hours'] ) : 24;

		$checks = array(
			array(
				'label'   => __( 'Health baseline captured', 'zignites-sentinel' ),
				'status'  => $baseline_present ? 'pass' : 'fail',
				'message' => $baseline_present
					? __( 'A health baseline exists for this snapshot.', 'zignites-sentinel' )
					: __( 'Capture a snapshot health baseline before offering live restore.', 'zignites-sentinel' ),
			),
			array(
				'label'   => __( 'Staged validation current', 'zignites-sentinel' ),
				'status'  => $stage_result_ready ? 'pass' : 'fail',
				'message' => $stage_result_ready
					? sprintf(
						/* translators: %d: max age in hours */
						__( 'A matching staged validation checkpoint is ready and no older than %d hours.', 'zignites-sentinel' ),
						$max_age_hours
					)
					: ( $stage_checkpoint_exists
						? __( 'The stored staged validation checkpoint is stale, expired, or no longer ready.', 'zignites-sentinel' )
						: __( 'Run staged restore validation for this snapshot package.', 'zignites-sentinel' ) ),
			),
			array(
				'label'   => __( 'Restore plan current', 'zignites-sentinel' ),
				'status'  => $plan_result_ready ? 'pass' : 'fail',
				'message' => $plan_result_ready
					? sprintf(
						/* translators: %d: max age in hours */
						__( 'A matching restore plan checkpoint is ready and no older than %d hours.', 'zignites-sentinel' ),
						$max_age_hours
					)
					: ( $plan_checkpoint_exists
						? __( 'The stored restore plan checkpoint is stale, expired, or blocked.', 'zignites-sentinel' )
						: __( 'Build a restore plan for this snapshot package.', 'zignites-sentinel' ) ),
			),
		);

		$can_execute = true;

		foreach ( $checks as $check ) {
			if ( 'pass' !== $check['status'] ) {
				$can_execute = false;
				break;
			}
		}

		return array(
			'status'        => $can_execute ? 'ready' : 'blocked',
			'can_execute'   => $can_execute,
			'max_age_hours' => $max_age_hours,
			'checks'        => $checks,
		);
	}
}
