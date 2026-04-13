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
		$system_health         = isset( $site_status_card['system_health'] ) && is_array( $site_status_card['system_health'] )
			? $site_status_card['system_health']
			: ( method_exists( $status_resolver, 'build_system_health_card' ) ? $status_resolver->build_system_health_card( $health_score_payload, $recent_snapshots, $snapshot_status_index ) : array() );
		$snapshot_intelligence = isset( $site_status_card['snapshot_intelligence'] ) && is_array( $site_status_card['snapshot_intelligence'] )
			? $site_status_card['snapshot_intelligence']
			: ( method_exists( $status_resolver, 'build_snapshot_intelligence' ) ? $status_resolver->build_snapshot_intelligence( $recent_snapshots, $snapshot_status_index ) : array() );
		$operator_timeline     = method_exists( $status_resolver, 'build_operator_timeline' ) ? $status_resolver->build_operator_timeline( $recent_snapshots ) : array();
		$site_status_card['system_health']         = is_array( $system_health ) ? $system_health : array();
		$site_status_card['snapshot_intelligence'] = is_array( $snapshot_intelligence ) ? $snapshot_intelligence : array();
		$site_status_card['operator_timeline']     = is_array( $operator_timeline ) ? $operator_timeline : array();

		return array(
			'recent_snapshots'      => is_array( $recent_snapshots ) ? $recent_snapshots : array(),
			'health_score'          => is_array( $health_score_payload ) ? $health_score_payload : array(),
			'snapshot_status_index' => is_array( $snapshot_status_index ) ? $snapshot_status_index : array(),
			'site_status_card'      => is_array( $site_status_card ) ? $site_status_card : array(),
			'system_health'         => is_array( $system_health ) ? $system_health : array(),
			'snapshot_intelligence' => is_array( $snapshot_intelligence ) ? $snapshot_intelligence : array(),
			'operator_timeline'     => is_array( $operator_timeline ) ? $operator_timeline : array(),
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

	/**
	 * Build normalized state for the shared dashboard summary presenter handoff.
	 *
	 * @param array  $summary_state        Shared dashboard summary state.
	 * @param array  $restore_health_strip Dashboard health-strip payload.
	 * @param string $activity_url         Latest snapshot activity URL.
	 * @return array
	 */
	public function build_summary_view_state( array $summary_state, array $restore_health_strip, $activity_url = '' ) {
		return array(
			'recent_snapshots'      => isset( $summary_state['recent_snapshots'] ) && is_array( $summary_state['recent_snapshots'] ) ? $summary_state['recent_snapshots'] : array(),
			'health_score'          => isset( $summary_state['health_score'] ) && is_array( $summary_state['health_score'] ) ? $summary_state['health_score'] : array(),
			'snapshot_status_index' => isset( $summary_state['snapshot_status_index'] ) && is_array( $summary_state['snapshot_status_index'] ) ? $summary_state['snapshot_status_index'] : array(),
			'site_status_card'      => isset( $summary_state['site_status_card'] ) && is_array( $summary_state['site_status_card'] ) ? $summary_state['site_status_card'] : array(),
			'system_health'         => isset( $summary_state['system_health'] ) && is_array( $summary_state['system_health'] ) ? $summary_state['system_health'] : array(),
			'snapshot_intelligence' => isset( $summary_state['snapshot_intelligence'] ) && is_array( $summary_state['snapshot_intelligence'] ) ? $summary_state['snapshot_intelligence'] : array(),
			'operator_timeline'     => isset( $summary_state['operator_timeline'] ) && is_array( $summary_state['operator_timeline'] ) ? $summary_state['operator_timeline'] : array(),
			'restore_health_strip'  => $restore_health_strip,
			'activity_url'          => (string) $activity_url,
		);
	}

	/**
	 * Build normalized view state for the full dashboard screen.
	 *
	 * @param array $summary Shared dashboard summary payload.
	 * @param array $context Dashboard screen context payload.
	 * @return array
	 */
	public function build_dashboard_screen_state( array $summary, array $context ) {
		return array(
			'plugin_version'        => isset( $context['plugin_version'] ) ? (string) $context['plugin_version'] : '',
			'db_version'            => isset( $context['db_version'] ) ? (string) $context['db_version'] : '',
			'logs_table'            => isset( $context['logs_table'] ) ? (string) $context['logs_table'] : '',
			'conflicts_table'       => isset( $context['conflicts_table'] ) ? (string) $context['conflicts_table'] : '',
			'wordpress'             => isset( $context['wordpress'] ) ? (string) $context['wordpress'] : '',
			'php'                   => isset( $context['php'] ) ? (string) $context['php'] : '',
			'site_url'              => isset( $context['site_url'] ) ? (string) $context['site_url'] : '',
			'recent_logs'           => isset( $context['recent_logs'] ) && is_array( $context['recent_logs'] ) ? $context['recent_logs'] : array(),
			'recent_conflicts'      => isset( $context['recent_conflicts'] ) && is_array( $context['recent_conflicts'] ) ? $context['recent_conflicts'] : array(),
			'recent_snapshots'      => isset( $summary['recent_snapshots'] ) && is_array( $summary['recent_snapshots'] ) ? $summary['recent_snapshots'] : array(),
			'health_score'          => isset( $summary['health_score'] ) && is_array( $summary['health_score'] ) ? $summary['health_score'] : array(),
			'restore_health_strip'  => isset( $summary['restore_health_strip'] ) && is_array( $summary['restore_health_strip'] ) ? $summary['restore_health_strip'] : array(),
			'snapshot_status_index' => isset( $summary['snapshot_status_index'] ) && is_array( $summary['snapshot_status_index'] ) ? $summary['snapshot_status_index'] : array(),
			'site_status_card'      => isset( $summary['site_status_card'] ) && is_array( $summary['site_status_card'] ) ? $summary['site_status_card'] : array(),
			'system_health'         => isset( $summary['system_health'] ) && is_array( $summary['system_health'] ) ? $summary['system_health'] : array(),
			'snapshot_intelligence' => isset( $summary['snapshot_intelligence'] ) && is_array( $summary['snapshot_intelligence'] ) ? $summary['snapshot_intelligence'] : array(),
			'operator_timeline'     => isset( $summary['operator_timeline'] ) && is_array( $summary['operator_timeline'] ) ? $summary['operator_timeline'] : array(),
		);
	}
}
