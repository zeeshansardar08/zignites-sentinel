<?php
/**
 * Read-only helper for dashboard summary state assembly.
 *
 * @package ZignitesSentinel
 */

namespace Zignites\Sentinel\Admin;

defined( 'ABSPATH' ) || exit;

class DashboardSummaryStateBuilder {

	/**
	 * Build the normalized state used by the dashboard summary presenter.
	 *
	 * @param int    $snapshot_limit     Number of recent snapshots to include.
	 * @param object $snapshot_repo      Snapshot repository.
	 * @param object $health_score       Health score service.
	 * @param object $status_resolver    Snapshot status resolver.
	 * @param string $activity_url       Snapshot activity URL.
	 * @return array
	 */
	public function build_summary_state( $snapshot_limit, $snapshot_repo, $health_score, $status_resolver, $activity_url = '' ) {
		$recent_snapshots      = $snapshot_repo->get_recent( max( 1, absint( $snapshot_limit ) ) );
		$health_score_payload  = $health_score->calculate();
		$snapshot_status_index = $status_resolver->build_snapshot_status_index( $recent_snapshots );
		$site_status_card      = $status_resolver->build_site_status_card( $health_score_payload, $recent_snapshots, $snapshot_status_index );

		return array(
			'recent_snapshots'      => is_array( $recent_snapshots ) ? $recent_snapshots : array(),
			'health_score'          => is_array( $health_score_payload ) ? $health_score_payload : array(),
			'snapshot_status_index' => is_array( $snapshot_status_index ) ? $snapshot_status_index : array(),
			'site_status_card'      => is_array( $site_status_card ) ? $site_status_card : array(),
			'activity_url'          => (string) $activity_url,
		);
	}

	/**
	 * Build the normalized state used by the dashboard restore summary presenter.
	 *
	 * @param array|null $snapshot     Latest snapshot detail.
	 * @param array      $checklist    Restore operator checklist.
	 * @param array|null $baseline     Health baseline payload.
	 * @param array|null $stage        Stage checkpoint payload.
	 * @param array|null $plan         Plan checkpoint payload.
	 * @param array      $execution    Last restore execution payload.
	 * @param array      $rollback     Last restore rollback payload.
	 * @param array      $stage_timing Stage timing payload.
	 * @param array      $plan_timing  Plan timing payload.
	 * @param string     $activity_url Snapshot activity URL.
	 * @return array
	 */
	public function build_restore_summary_state( $snapshot, array $checklist, $baseline, $stage, $plan, array $execution, array $rollback, array $stage_timing, array $plan_timing, $activity_url = '' ) {
		if ( ! is_array( $snapshot ) || empty( $snapshot['id'] ) ) {
			return array();
		}

		return array(
			'snapshot'      => $snapshot,
			'checklist'     => $checklist,
			'baseline'      => is_array( $baseline ) ? $baseline : null,
			'stage'         => is_array( $stage ) ? $stage : null,
			'plan'          => is_array( $plan ) ? $plan : null,
			'execution'     => $execution,
			'rollback'      => $rollback,
			'stage_timing'  => $stage_timing,
			'plan_timing'   => $plan_timing,
			'activity_url'  => (string) $activity_url,
		);
	}

	/**
	 * Build the normalized state used by the dashboard health-strip presenter.
	 *
	 * @param array|null $snapshot Latest snapshot detail.
	 * @param array      $rows     Health comparison rows.
	 * @return array
	 */
	public function build_health_strip_state( $snapshot, array $rows ) {
		if ( ! is_array( $snapshot ) || empty( $snapshot['id'] ) ) {
			return array();
		}

		return array(
			'snapshot' => $snapshot,
			'rows'     => $rows,
		);
	}

	/**
	 * Build normalized widget view state from the shared dashboard summary payload.
	 *
	 * @param array $summary Shared dashboard summary payload.
	 * @return array
	 */
	public function build_widget_state( array $summary ) {
		$recent_snapshots      = isset( $summary['recent_snapshots'] ) && is_array( $summary['recent_snapshots'] ) ? $summary['recent_snapshots'] : array();
		$snapshot_status_index = isset( $summary['snapshot_status_index'] ) && is_array( $summary['snapshot_status_index'] ) ? $summary['snapshot_status_index'] : array();
		$latest_snapshot       = ! empty( $recent_snapshots[0] ) && is_array( $recent_snapshots[0] ) ? $recent_snapshots[0] : array();
		$latest_snapshot_id    = ! empty( $latest_snapshot['id'] ) ? (int) $latest_snapshot['id'] : 0;

		return array(
			'site_status_card'     => isset( $summary['site_status_card'] ) && is_array( $summary['site_status_card'] ) ? $summary['site_status_card'] : array(),
			'restore_health_strip' => isset( $summary['restore_health_strip'] ) && is_array( $summary['restore_health_strip'] ) ? $summary['restore_health_strip'] : array(),
			'latest_snapshot'      => $latest_snapshot,
			'snapshot_status'      => $latest_snapshot_id > 0 && isset( $snapshot_status_index[ $latest_snapshot_id ] ) && is_array( $snapshot_status_index[ $latest_snapshot_id ] )
				? $snapshot_status_index[ $latest_snapshot_id ]
				: array(),
		);
	}
}
