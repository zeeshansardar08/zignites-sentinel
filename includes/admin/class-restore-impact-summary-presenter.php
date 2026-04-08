<?php
/**
 * Read-only presentation helper for restore impact summaries.
 *
 * @package ZignitesSentinel
 */

namespace Zignites\Sentinel\Admin;

defined( 'ABSPATH' ) || exit;

class RestoreImpactSummaryPresenter {

	/**
	 * Build the final pre-execution impact summary for live restore.
	 *
	 * @param int          $snapshot_id     Snapshot ID.
	 * @param array        $plan            Restore plan payload.
	 * @param array        $baseline        Baseline payload.
	 * @param array        $checklist       Restore operator checklist payload.
	 * @param array        $resume_context  Restore resume context.
	 * @param string       $backup_summary  Backup summary string.
	 * @param string       $stage_summary   Stage gate summary string.
	 * @param string       $plan_summary    Restore plan gate summary string.
	 * @return array
	 */
	public function build_summary( $snapshot_id, array $plan, array $baseline, array $checklist, array $resume_context, $backup_summary, $stage_summary, $plan_summary ) {
		$summary        = isset( $plan['summary'] ) && is_array( $plan['summary'] ) ? $plan['summary'] : array();
		$create_count   = isset( $summary['create'] ) ? (int) $summary['create'] : 0;
		$replace_count  = isset( $summary['replace'] ) ? (int) $summary['replace'] : 0;
		$reuse_count    = isset( $summary['reuse'] ) ? (int) $summary['reuse'] : 0;
		$blocked_count  = isset( $summary['blocked'] ) ? (int) $summary['blocked'] : 0;
		$conflict_count = isset( $summary['conflicts'] ) ? (int) $summary['conflicts'] : 0;
		$status         = 'info';
		$title          = __( 'Low impact', 'zignites-sentinel' );
		$message        = __( 'The current restore plan is mostly aligned with the live site.', 'zignites-sentinel' );

		if ( empty( $checklist['can_execute'] ) || ( ! empty( $plan['status'] ) && 'blocked' === $plan['status'] ) ) {
			$status  = 'critical';
			$title   = __( 'Restore blocked', 'zignites-sentinel' );
			$message = __( 'Live restore is still blocked by checklist or plan state. Review the items below before attempting execution.', 'zignites-sentinel' );
		} elseif ( $replace_count > 0 || $conflict_count > 0 || $blocked_count > 0 || ( ! empty( $plan['status'] ) && 'caution' === $plan['status'] ) ) {
			$status  = 'warning';
			$title   = __( 'Review impact', 'zignites-sentinel' );
			$message = __( 'This restore will overwrite live payloads. Review the replacement scope, backup behavior, and baseline before executing.', 'zignites-sentinel' );
		}

		$baseline_status = ! empty( $baseline )
			? sprintf(
				/* translators: 1: health status, 2: timestamp */
				__( '%1$s captured at %2$s', 'zignites-sentinel' ),
				ucfirst( isset( $baseline['status'] ) ? (string) $baseline['status'] : __( 'Healthy', 'zignites-sentinel' ) ),
				isset( $baseline['generated_at'] ) ? (string) $baseline['generated_at'] : ''
			)
			: __( 'No baseline captured yet', 'zignites-sentinel' );

		$rows = array(
			array(
				'label' => __( 'Live changes', 'zignites-sentinel' ),
				'value' => sprintf(
					/* translators: 1: create count, 2: replace count, 3: unchanged count */
					__( '%1$d create, %2$d replace, %3$d unchanged', 'zignites-sentinel' ),
					$create_count,
					$replace_count,
					$reuse_count
				),
			),
			array(
				'label' => __( 'Conflict count', 'zignites-sentinel' ),
				'value' => sprintf(
					/* translators: %d: conflicting file count */
					__( '%d planned file conflicts', 'zignites-sentinel' ),
					$conflict_count
				),
			),
			array(
				'label' => __( 'Backup storage', 'zignites-sentinel' ),
				'value' => (string) $backup_summary,
			),
			array(
				'label' => __( 'Baseline status', 'zignites-sentinel' ),
				'value' => $baseline_status,
			),
			array(
				'label' => __( 'Stage gate', 'zignites-sentinel' ),
				'value' => (string) $stage_summary,
			),
			array(
				'label' => __( 'Restore plan', 'zignites-sentinel' ),
				'value' => (string) $plan_summary,
			),
			array(
				'label' => __( 'Confirmation phrase', 'zignites-sentinel' ),
				'value' => isset( $plan['confirmation_phrase'] ) && '' !== (string) $plan['confirmation_phrase']
					? (string) $plan['confirmation_phrase']
					: sprintf( 'RESTORE SNAPSHOT %d', (int) $snapshot_id ),
			),
		);

		$blockers = array();

		if ( ! empty( $checklist['checks'] ) && is_array( $checklist['checks'] ) ) {
			foreach ( $checklist['checks'] as $check ) {
				if ( empty( $check['status'] ) || 'pass' === $check['status'] ) {
					continue;
				}

				$blockers[] = array(
					'label'   => isset( $check['label'] ) ? (string) $check['label'] : __( 'Requirement', 'zignites-sentinel' ),
					'message' => isset( $check['message'] ) ? (string) $check['message'] : '',
				);
			}
		}

		if ( ! empty( $resume_context['can_resume'] ) ) {
			$rows[] = array(
				'label' => __( 'Resume state', 'zignites-sentinel' ),
				'value' => sprintf(
					/* translators: 1: completed item count, 2: journal entry count */
					__( '%1$d completed items already recorded across %2$d journal entries', 'zignites-sentinel' ),
					isset( $resume_context['completed_item_count'] ) ? (int) $resume_context['completed_item_count'] : 0,
					isset( $resume_context['entry_count'] ) ? (int) $resume_context['entry_count'] : 0
				),
			);
		}

		if ( $blocked_count > 0 ) {
			$rows[] = array(
				'label' => __( 'Blocked plan items', 'zignites-sentinel' ),
				'value' => sprintf(
					/* translators: %d: blocked plan item count */
					__( '%d plan items are currently blocked', 'zignites-sentinel' ),
					$blocked_count
				),
			);
		}

		return array(
			'status'   => $status,
			'title'    => $title,
			'message'  => $message,
			'rows'     => $rows,
			'blockers' => $blockers,
		);
	}
}
