<?php
/**
 * Read-only helper for restore impact summary state assembly.
 *
 * @package ZignitesSentinel
 */

namespace Zignites\Sentinel\Admin;

defined( 'ABSPATH' ) || exit;

class RestoreImpactSummaryStateBuilder {

	/**
	 * Build the normalized state used by the restore impact summary presenter.
	 *
	 * @param array|null $snapshot        Snapshot detail.
	 * @param array|null $plan            Restore plan payload.
	 * @param array|null $baseline        Baseline payload.
	 * @param array|null $checklist       Restore operator checklist payload.
	 * @param array|null $resume_context  Restore resume context.
	 * @param string     $backup_summary  Backup summary string.
	 * @param string     $stage_summary   Stage gate summary string.
	 * @param string     $plan_summary    Plan gate summary string.
	 * @return array
	 */
	public function build_summary_state( $snapshot, $plan, $baseline, $checklist, $resume_context, $backup_summary, $stage_summary, $plan_summary ) {
		if ( ! is_array( $snapshot ) || empty( $snapshot['id'] ) ) {
			return array();
		}

		return array(
			'snapshot_id'    => (int) $snapshot['id'],
			'plan'           => is_array( $plan ) ? $plan : array(),
			'baseline'       => is_array( $baseline ) ? $baseline : array(),
			'checklist'      => is_array( $checklist ) ? $checklist : array(),
			'resume_context' => is_array( $resume_context ) ? $resume_context : array(),
			'backup_summary' => (string) $backup_summary,
			'stage_summary'  => (string) $stage_summary,
			'plan_summary'   => (string) $plan_summary,
		);
	}
}
