<?php
/**
 * Read-only presentation helper for dashboard summary payloads.
 *
 * @package ZignitesSentinel
 */

namespace Zignites\Sentinel\Admin;

defined( 'ABSPATH' ) || exit;

class DashboardSummaryPresenter {

	/**
	 * Build shared dashboard summary payloads for full and compact dashboard views.
	 *
	 * @param array  $recent_snapshots      Recent snapshots.
	 * @param array  $health_score          Health score payload.
	 * @param array  $snapshot_status_index Snapshot status index.
	 * @param array  $site_status_card      Site status card payload.
	 * @param array  $restore_health_strip  Restore health strip payload.
	 * @param string $update_page_slug      Update readiness page slug.
	 * @param string $activity_url          Activity URL for the latest snapshot.
	 * @return array
	 */
	public function build_summary_payload( array $recent_snapshots, array $health_score, array $snapshot_status_index, array $site_status_card, array $restore_health_strip, $update_page_slug, $activity_url = '' ) {
		if ( ! empty( $site_status_card['latest_snapshot']['id'] ) ) {
			$site_status_card['detail_url'] = add_query_arg(
				array(
					'page'        => (string) $update_page_slug,
					'snapshot_id' => (int) $site_status_card['latest_snapshot']['id'],
				),
				admin_url( 'admin.php' )
			);
			$site_status_card['activity_url'] = (string) $activity_url;
		}

		return array(
			'recent_snapshots'      => $recent_snapshots,
			'health_score'          => $health_score,
			'restore_health_strip'  => $restore_health_strip,
			'snapshot_status_index' => $snapshot_status_index,
			'site_status_card'      => $site_status_card,
		);
	}

	/**
	 * Build dashboard restore readiness summary for the latest snapshot.
	 *
	 * @param array|null $snapshot        Snapshot detail.
	 * @param array      $checklist       Restore operator checklist.
	 * @param array|null $baseline        Baseline payload.
	 * @param array|null $stage           Stage checkpoint payload.
	 * @param array|null $plan            Plan checkpoint payload.
	 * @param array      $execution       Last execution payload.
	 * @param array      $rollback        Last rollback payload.
	 * @param array      $stage_timing    Stage timing summary.
	 * @param array      $plan_timing     Plan timing summary.
	 * @param string     $update_page_slug Update readiness page slug.
	 * @param string     $activity_url    Snapshot activity URL.
	 * @return array
	 */
	public function build_restore_summary( $snapshot, array $checklist, $baseline, $stage, $plan, array $execution, array $rollback, array $stage_timing, array $plan_timing, $update_page_slug, $activity_url = '' ) {
		if ( ! is_array( $snapshot ) || empty( $snapshot['id'] ) ) {
			return array();
		}

		$stage_fresh = ! empty( $stage_timing['is_fresh'] );
		$plan_fresh  = ! empty( $plan_timing['is_fresh'] );

		return array(
			'snapshot'         => $snapshot,
			'checklist'        => $checklist,
			'baseline'         => $baseline,
			'stage_checkpoint' => $stage,
			'plan_checkpoint'  => $plan,
			'last_execution'   => $execution,
			'last_rollback'    => $rollback,
			'summary_rows'     => array(
				array(
					'label'   => __( 'Health baseline', 'zignites-sentinel' ),
					'status'  => is_array( $baseline ) ? 'pass' : 'fail',
					'message' => is_array( $baseline )
						? __( 'Captured for the latest snapshot.', 'zignites-sentinel' )
						: __( 'Not captured yet.', 'zignites-sentinel' ),
				),
				array(
					'label'   => __( 'Stage checkpoint', 'zignites-sentinel' ),
					'status'  => $stage_fresh ? 'pass' : ( is_array( $stage ) ? 'warning' : 'fail' ),
					'message' => $stage_fresh
						? sprintf(
							/* translators: %s: checkpoint freshness label */
							__( 'Fresh staged validation is available. %s', 'zignites-sentinel' ),
							isset( $stage_timing['label'] ) ? $stage_timing['label'] : ''
						)
						: ( is_array( $stage )
							? sprintf(
								/* translators: %s: checkpoint freshness label */
								__( 'Stored checkpoint exists but is expired or no longer reusable. %s', 'zignites-sentinel' ),
								isset( $stage_timing['label'] ) ? $stage_timing['label'] : ''
							)
							: __( 'No staged validation checkpoint is available.', 'zignites-sentinel' ) ),
				),
				array(
					'label'   => __( 'Restore plan', 'zignites-sentinel' ),
					'status'  => $plan_fresh ? 'pass' : ( is_array( $plan ) ? 'warning' : 'fail' ),
					'message' => $plan_fresh
						? sprintf(
							/* translators: %s: checkpoint freshness label */
							__( 'Fresh restore plan is available. %s', 'zignites-sentinel' ),
							isset( $plan_timing['label'] ) ? $plan_timing['label'] : ''
						)
						: ( is_array( $plan )
							? sprintf(
								/* translators: %s: checkpoint freshness label */
								__( 'Stored plan exists but is expired or blocked. %s', 'zignites-sentinel' ),
								isset( $plan_timing['label'] ) ? $plan_timing['label'] : ''
							)
							: __( 'No restore plan checkpoint is available.', 'zignites-sentinel' ) ),
				),
			),
			'can_execute'      => ! empty( $checklist['can_execute'] ),
			'status'           => ! empty( $checklist['can_execute'] ) ? 'ready' : 'blocked',
			'status_badge'     => ! empty( $checklist['can_execute'] ) ? 'info' : 'critical',
			'detail_url'       => add_query_arg(
				array(
					'page'        => (string) $update_page_slug,
					'snapshot_id' => (int) $snapshot['id'],
				),
				admin_url( 'admin.php' )
			),
			'activity_url'     => (string) $activity_url,
		);
	}

	/**
	 * Build a compact dashboard health strip payload.
	 *
	 * @param array|null $snapshot         Snapshot detail.
	 * @param array      $health_rows      Health comparison rows.
	 * @param string     $update_page_slug Update readiness page slug.
	 * @return array
	 */
	public function build_health_strip( $snapshot, array $health_rows, $update_page_slug ) {
		if ( ! is_array( $snapshot ) || empty( $snapshot['id'] ) ) {
			return array();
		}

		return array(
			'snapshot'   => $snapshot,
			'rows'       => $health_rows,
			'detail_url' => add_query_arg(
				array(
					'page'        => (string) $update_page_slug,
					'snapshot_id' => (int) $snapshot['id'],
				),
				admin_url( 'admin.php' )
			),
		);
	}
}
