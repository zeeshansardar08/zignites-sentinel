<?php
/**
 * Read-only presentation helper for snapshot summary payloads and exports.
 *
 * @package ZignitesSentinel
 */

namespace Zignites\Sentinel\Admin;

defined( 'ABSPATH' ) || exit;

class SnapshotSummaryPresenter {

	/**
	 * Build a compact human-readable snapshot summary payload.
	 *
	 * @param array $snapshot           Snapshot detail.
	 * @param array $snapshot_status    Snapshot status payload.
	 * @param array $artifact_counts    Snapshot artifact counts.
	 * @param array $activity           Recent snapshot activity.
	 * @param array $operator_checklist Restore operator checklist payload.
	 * @param array $baseline           Baseline payload.
	 * @param array $restore_check      Restore readiness payload.
	 * @param array $stage_timing       Stage checkpoint timing summary.
	 * @param array $plan_timing        Plan checkpoint timing summary.
	 * @param array $last_execution     Last restore execution payload.
	 * @param array $last_rollback      Last rollback payload.
	 * @param array $restore_stage      Restore stage payload.
	 * @param array $restore_plan       Restore plan payload.
	 * @return array
	 */
	public function build_summary( array $snapshot, array $snapshot_status, array $artifact_counts, array $activity, array $operator_checklist, array $baseline, array $restore_check, array $stage_timing, array $plan_timing, array $last_execution, array $last_rollback, array $restore_stage, array $restore_plan ) {
		$plugin_count = ! empty( $snapshot['active_plugins_decoded'] ) && is_array( $snapshot['active_plugins_decoded'] ) ? count( $snapshot['active_plugins_decoded'] ) : 0;

		return array(
			'title'         => isset( $snapshot['label'] ) ? (string) $snapshot['label'] : '',
			'snapshot_id'   => isset( $snapshot['id'] ) ? (int) $snapshot['id'] : 0,
			'generated_at'  => current_time( 'mysql', true ),
			'created_at'    => isset( $snapshot['created_at'] ) ? (string) $snapshot['created_at'] : '',
			'theme'         => isset( $snapshot['theme_stylesheet'] ) ? (string) $snapshot['theme_stylesheet'] : '',
			'core_version'  => isset( $snapshot['core_version'] ) ? (string) $snapshot['core_version'] : '',
			'php_version'   => isset( $snapshot['php_version'] ) ? (string) $snapshot['php_version'] : '',
			'plugin_count'  => $plugin_count,
			'status_badges' => isset( $snapshot_status['status_badges'] ) && is_array( $snapshot_status['status_badges'] ) ? $snapshot_status['status_badges'] : array(),
			'overview'      => array(
				array(
					'label' => __( 'Restore status', 'zignites-sentinel' ),
					'value' => ! empty( $snapshot_status['restore_ready'] ) ? __( 'Restore ready', 'zignites-sentinel' ) : __( 'Restore blocked', 'zignites-sentinel' ),
					'note'  => ! empty( $operator_checklist['can_execute'] ) ? __( 'All current restore gates pass for this snapshot.', 'zignites-sentinel' ) : __( 'One or more current restore gates still require attention.', 'zignites-sentinel' ),
				),
				array(
					'label' => __( 'Runtime', 'zignites-sentinel' ),
					'value' => sprintf( __( 'WordPress %1$s / PHP %2$s', 'zignites-sentinel' ), isset( $snapshot['core_version'] ) ? (string) $snapshot['core_version'] : '-', isset( $snapshot['php_version'] ) ? (string) $snapshot['php_version'] : '-' ),
					'note'  => sprintf( __( 'Theme: %1$s. Active plugins: %2$d.', 'zignites-sentinel' ), isset( $snapshot['theme_stylesheet'] ) ? (string) $snapshot['theme_stylesheet'] : '-', $plugin_count ),
				),
				array(
					'label' => __( 'Artifacts', 'zignites-sentinel' ),
					'value' => sprintf( __( '%1$d total artifacts', 'zignites-sentinel' ), isset( $artifact_counts['total'] ) ? (int) $artifact_counts['total'] : 0 ),
					'note'  => sprintf( __( 'Package: %1$d. Export: %2$d. Components: %3$d.', 'zignites-sentinel' ), isset( $artifact_counts['package'] ) ? (int) $artifact_counts['package'] : 0, isset( $artifact_counts['export'] ) ? (int) $artifact_counts['export'] : 0, isset( $artifact_counts['component'] ) ? (int) $artifact_counts['component'] : 0 ),
				),
				array(
					'label' => __( 'Recent activity', 'zignites-sentinel' ),
					'value' => sprintf( __( '%d recent events', 'zignites-sentinel' ), count( $activity ) ),
					'note'  => ! empty( $activity[0]['message'] ) ? (string) $activity[0]['message'] : __( 'No recent snapshot-scoped events were recorded.', 'zignites-sentinel' ),
				),
			),
			'evidence'      => array(
				array(
					'label' => __( 'Baseline status', 'zignites-sentinel' ),
					'value' => ! empty( $baseline['status'] ) ? (string) $baseline['status'] : __( 'Not captured', 'zignites-sentinel' ),
					'note'  => ! empty( $baseline['generated_at'] ) ? sprintf( __( 'Captured on %s.', 'zignites-sentinel' ), (string) $baseline['generated_at'] ) : __( 'No baseline is currently stored for this snapshot.', 'zignites-sentinel' ),
				),
				array(
					'label' => __( 'Restore readiness', 'zignites-sentinel' ),
					'value' => ! empty( $restore_check['status'] ) ? (string) $restore_check['status'] : __( 'Not evaluated', 'zignites-sentinel' ),
					'note'  => ! empty( $restore_check['note'] ) ? (string) $restore_check['note'] : __( 'Run restore readiness when you need a current advisory check.', 'zignites-sentinel' ),
				),
				array(
					'label' => __( 'Stage checkpoint', 'zignites-sentinel' ),
					'value' => ! empty( $snapshot_status['stage']['label'] ) ? (string) $snapshot_status['stage']['label'] : __( 'Stage missing', 'zignites-sentinel' ),
					'note'  => ! empty( $stage_timing['label'] ) ? (string) $stage_timing['label'] : __( 'No staged validation checkpoint is currently available.', 'zignites-sentinel' ),
				),
				array(
					'label' => __( 'Restore plan', 'zignites-sentinel' ),
					'value' => ! empty( $snapshot_status['plan']['label'] ) ? (string) $snapshot_status['plan']['label'] : __( 'Plan missing', 'zignites-sentinel' ),
					'note'  => ! empty( $plan_timing['label'] ) ? (string) $plan_timing['label'] : __( 'No current restore plan checkpoint is currently available.', 'zignites-sentinel' ),
				),
			),
			'risks'         => $this->build_risks( $snapshot_status, $baseline, $restore_check, $last_execution, $last_rollback ),
			'next_steps'    => $this->build_next_steps( $snapshot_status, $baseline, $restore_check, $restore_stage, $restore_plan, $last_execution, $last_rollback ),
			'activity'      => $activity,
		);
	}

	/**
	 * Summarize artifact counts by type for a snapshot.
	 *
	 * @param array $artifacts Snapshot artifacts.
	 * @return array
	 */
	public function summarize_artifacts( array $artifacts ) {
		$summary = array(
			'total'     => count( $artifacts ),
			'package'   => 0,
			'export'    => 0,
			'component' => 0,
		);

		foreach ( $artifacts as $artifact ) {
			$type = isset( $artifact['artifact_type'] ) ? sanitize_key( (string) $artifact['artifact_type'] ) : '';

			if ( isset( $summary[ $type ] ) ) {
				++$summary[ $type ];
				continue;
			}

			if ( 'component' !== $type ) {
				++$summary['component'];
			}
		}

		return $summary;
	}

	/**
	 * Build human-readable risk bullets for a snapshot summary.
	 *
	 * @param array $snapshot_status Snapshot status payload.
	 * @param array $baseline        Baseline payload.
	 * @param array $restore_check   Restore readiness payload.
	 * @param array $last_execution  Last restore execution payload.
	 * @param array $last_rollback   Last rollback payload.
	 * @return array
	 */
	public function build_risks( array $snapshot_status, array $baseline, array $restore_check, array $last_execution, array $last_rollback ) {
		$risks = array();

		if ( empty( $snapshot_status['artifacts']['package_present'] ) ) {
			$risks[] = __( 'No rollback package is currently saved for this snapshot.', 'zignites-sentinel' );
		}

		if ( empty( $baseline ) ) {
			$risks[] = __( 'No health baseline has been captured for this snapshot yet.', 'zignites-sentinel' );
		}

		if ( ! empty( $snapshot_status['stage']['key'] ) && 'current' !== $snapshot_status['stage']['key'] ) {
			$risks[] = __( 'The staged validation checkpoint is missing or stale.', 'zignites-sentinel' );
		}

		if ( ! empty( $snapshot_status['plan']['key'] ) && 'current' !== $snapshot_status['plan']['key'] ) {
			$risks[] = __( 'The restore plan checkpoint is missing or stale.', 'zignites-sentinel' );
		}

		if ( ! empty( $restore_check['status'] ) && in_array( $restore_check['status'], array( 'blocked', 'caution' ), true ) ) {
			$risks[] = ! empty( $restore_check['note'] ) ? (string) $restore_check['note'] : __( 'The last restore readiness assessment reported warnings or blockers.', 'zignites-sentinel' );
		}

		if ( ! empty( $last_execution['status'] ) && in_array( $last_execution['status'], array( 'blocked', 'partial' ), true ) ) {
			$risks[] = ! empty( $last_execution['note'] ) ? (string) $last_execution['note'] : __( 'The last restore execution did not finish cleanly.', 'zignites-sentinel' );
		}

		if ( ! empty( $last_rollback['status'] ) && in_array( $last_rollback['status'], array( 'blocked', 'partial' ), true ) ) {
			$risks[] = ! empty( $last_rollback['note'] ) ? (string) $last_rollback['note'] : __( 'The last rollback did not finish cleanly.', 'zignites-sentinel' );
		}

		return array_values( array_unique( $risks ) );
	}

	/**
	 * Build recommended next steps for a snapshot summary.
	 *
	 * @param array $snapshot_status Snapshot status payload.
	 * @param array $baseline        Baseline payload.
	 * @param array $restore_check   Restore readiness payload.
	 * @param array $restore_stage   Restore stage payload.
	 * @param array $restore_plan    Restore plan payload.
	 * @param array $last_execution  Last restore execution payload.
	 * @param array $last_rollback   Last rollback payload.
	 * @return array
	 */
	public function build_next_steps( array $snapshot_status, array $baseline, array $restore_check, array $restore_stage, array $restore_plan, array $last_execution, array $last_rollback ) {
		$steps = array();

		if ( empty( $baseline ) ) {
			$steps[] = __( 'Capture a health baseline before any guarded restore work.', 'zignites-sentinel' );
		}

		if ( empty( $snapshot_status['stage']['key'] ) || 'current' !== $snapshot_status['stage']['key'] ) {
			$steps[] = __( 'Run staged restore validation to refresh the stage checkpoint.', 'zignites-sentinel' );
		}

		if ( empty( $snapshot_status['plan']['key'] ) || 'current' !== $snapshot_status['plan']['key'] ) {
			$steps[] = __( 'Build or refresh the restore plan before considering live restore.', 'zignites-sentinel' );
		}

		if ( ! empty( $restore_check['status'] ) && in_array( $restore_check['status'], array( 'blocked', 'caution' ), true ) ) {
			$steps[] = __( 'Review the last restore readiness findings and resolve the flagged issues.', 'zignites-sentinel' );
		}

		if ( ! empty( $last_execution['status'] ) && in_array( $last_execution['status'], array( 'blocked', 'partial' ), true ) ) {
			$steps[] = __( 'Review the last restore execution result and its backup context before running anything again.', 'zignites-sentinel' );
		}

		if ( ! empty( $last_rollback['status'] ) && in_array( $last_rollback['status'], array( 'blocked', 'partial' ), true ) ) {
			$steps[] = __( 'Review the last rollback result before treating this snapshot as stable again.', 'zignites-sentinel' );
		}

		if ( empty( $steps ) ) {
			$steps[] = __( 'No immediate action is required. Keep the current evidence fresh before future update or restore work.', 'zignites-sentinel' );
		}

		return array_values( array_unique( $steps ) );
	}

	/**
	 * Build Markdown output for a snapshot summary.
	 *
	 * @param array $summary Snapshot summary payload.
	 * @return string
	 */
	public function build_markdown( array $summary ) {
		$lines   = array();
		$title   = ! empty( $summary['title'] ) ? (string) $summary['title'] : __( 'Snapshot Summary', 'zignites-sentinel' );
		$lines[] = '# ' . $title;
		$lines[] = '';
		$lines[] = '- ' . sprintf( __( 'Snapshot ID: %d', 'zignites-sentinel' ), isset( $summary['snapshot_id'] ) ? (int) $summary['snapshot_id'] : 0 );
		$lines[] = '- ' . sprintf( __( 'Generated: %s', 'zignites-sentinel' ), isset( $summary['generated_at'] ) ? (string) $summary['generated_at'] : '' );
		$lines[] = '- ' . sprintf( __( 'Created: %s', 'zignites-sentinel' ), isset( $summary['created_at'] ) ? (string) $summary['created_at'] : '' );
		$lines[] = '';

		$lines[] = '## Overview';
		$lines[] = '';

		foreach ( isset( $summary['overview'] ) && is_array( $summary['overview'] ) ? $summary['overview'] : array() as $item ) {
			$lines[] = '- **' . ( isset( $item['label'] ) ? (string) $item['label'] : '' ) . ':** ' . ( isset( $item['value'] ) ? (string) $item['value'] : '' );

			if ( ! empty( $item['note'] ) ) {
				$lines[] = '  - ' . (string) $item['note'];
			}
		}

		$lines[] = '';
		$lines[] = '## Evidence';
		$lines[] = '';

		foreach ( isset( $summary['evidence'] ) && is_array( $summary['evidence'] ) ? $summary['evidence'] : array() as $item ) {
			$lines[] = '- **' . ( isset( $item['label'] ) ? (string) $item['label'] : '' ) . ':** ' . ( isset( $item['value'] ) ? (string) $item['value'] : '' );

			if ( ! empty( $item['note'] ) ) {
				$lines[] = '  - ' . (string) $item['note'];
			}
		}

		$lines[] = '';
		$lines[] = '## Risks';
		$lines[] = '';

		foreach ( ! empty( $summary['risks'] ) ? $summary['risks'] : array( __( 'No material risks are highlighted by the current snapshot summary.', 'zignites-sentinel' ) ) as $risk ) {
			$lines[] = '- ' . (string) $risk;
		}

		$lines[] = '';
		$lines[] = '## Recommended Next Steps';
		$lines[] = '';

		foreach ( isset( $summary['next_steps'] ) && is_array( $summary['next_steps'] ) ? $summary['next_steps'] : array() as $step ) {
			$lines[] = '- ' . (string) $step;
		}

		$lines[] = '';
		$lines[] = '## Recent Activity';
		$lines[] = '';

		foreach ( isset( $summary['activity'] ) && is_array( $summary['activity'] ) ? $summary['activity'] : array() as $activity ) {
			$lines[] = '- ' . sprintf(
				'[%s] %s: %s',
				isset( $activity['created_at'] ) ? (string) $activity['created_at'] : '',
				isset( $activity['source'] ) ? (string) $activity['source'] : '',
				isset( $activity['message'] ) ? (string) $activity['message'] : ''
			);
		}

		if ( empty( $summary['activity'] ) ) {
			$lines[] = '- ' . __( 'No recent snapshot-scoped events were recorded.', 'zignites-sentinel' );
		}

		$lines[] = '';

		return implode( "\n", $lines ) . "\n";
	}
}
