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
}
