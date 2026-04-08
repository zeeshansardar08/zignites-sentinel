<?php
/**
 * Read-only helper for restore operator checklist state assembly.
 *
 * @package ZignitesSentinel
 */

namespace Zignites\Sentinel\Admin;

defined( 'ABSPATH' ) || exit;

class RestoreOperatorChecklistStateBuilder {

	/**
	 * Build the normalized evaluator context for the restore operator checklist.
	 *
	 * @param array|null $baseline         Snapshot health baseline payload.
	 * @param array|null $stage_result     Fresh staged validation payload.
	 * @param array|null $plan_result      Fresh restore plan payload.
	 * @param array|null $stage_checkpoint Stage checkpoint payload.
	 * @param array|null $plan_checkpoint  Plan checkpoint payload.
	 * @param array|null $settings         Current Sentinel settings.
	 * @return array
	 */
	public function build_context( $baseline, $stage_result, $plan_result, $stage_checkpoint, $plan_checkpoint, $settings ) {
		$settings      = is_array( $settings ) ? $settings : array();
		$max_age_hours = isset( $settings['restore_checkpoint_max_age_hours'] ) ? (int) $settings['restore_checkpoint_max_age_hours'] : 24;

		return array(
			'baseline_present'        => is_array( $baseline ),
			'stage_result_ready'      => ! empty( $stage_result ),
			'plan_result_ready'       => ! empty( $plan_result ),
			'stage_checkpoint_exists' => ! empty( $stage_checkpoint ),
			'plan_checkpoint_exists'  => ! empty( $plan_checkpoint ),
			'max_age_hours'           => $max_age_hours,
		);
	}
}
