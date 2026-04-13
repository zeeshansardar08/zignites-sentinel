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

		$primary_action = isset( $site_status_card['primary_action'] ) && is_array( $site_status_card['primary_action'] ) ? $site_status_card['primary_action'] : array();

		if ( ! empty( $primary_action ) ) {
			$target                = isset( $primary_action['target'] ) ? (string) $primary_action['target'] : 'detail';
			$primary_action['url'] = 'activity' === $target
				? ( isset( $site_status_card['activity_url'] ) ? (string) $site_status_card['activity_url'] : '' )
				: ( isset( $site_status_card['detail_url'] ) ? (string) $site_status_card['detail_url'] : '' );
			$site_status_card['primary_action'] = $primary_action;
		}

		$system_health         = isset( $site_status_card['system_health'] ) && is_array( $site_status_card['system_health'] ) ? $site_status_card['system_health'] : array();
		$snapshot_intelligence = isset( $site_status_card['snapshot_intelligence'] ) && is_array( $site_status_card['snapshot_intelligence'] ) ? $site_status_card['snapshot_intelligence'] : array();

		if ( ! empty( $snapshot_intelligence ) ) {
			$snapshot_intelligence['recommended_snapshot'] = $this->add_snapshot_reference_urls(
				isset( $snapshot_intelligence['recommended_snapshot'] ) && is_array( $snapshot_intelligence['recommended_snapshot'] ) ? $snapshot_intelligence['recommended_snapshot'] : array(),
				$update_page_slug
			);
			$snapshot_intelligence['last_known_good'] = $this->add_snapshot_reference_urls(
				isset( $snapshot_intelligence['last_known_good'] ) && is_array( $snapshot_intelligence['last_known_good'] ) ? $snapshot_intelligence['last_known_good'] : array(),
				$update_page_slug
			);
			$site_status_card['snapshot_intelligence'] = $snapshot_intelligence;
		}

		return array(
			'recent_snapshots'      => $recent_snapshots,
			'health_score'          => $health_score,
			'restore_health_strip'  => $restore_health_strip,
			'snapshot_status_index' => $snapshot_status_index,
			'system_health'         => $system_health,
			'snapshot_intelligence' => $snapshot_intelligence,
			'operator_timeline'     => isset( $site_status_card['operator_timeline'] ) && is_array( $site_status_card['operator_timeline'] ) ? $site_status_card['operator_timeline'] : array(),
			'help_panels'           => $this->build_help_panels( $recent_snapshots, $site_status_card ),
			'empty_states'          => $this->build_empty_states( $recent_snapshots, $restore_health_strip, $site_status_card ),
			'positioning_note'      => $this->build_positioning_note(),
			'site_status_card'      => $site_status_card,
		);
	}

	/**
	 * Build inline dashboard help panels.
	 *
	 * @param array $recent_snapshots Recent snapshots.
	 * @param array $site_status_card Site status card payload.
	 * @return array
	 */
	protected function build_help_panels( array $recent_snapshots, array $site_status_card ) {
		$has_snapshot = ! empty( $recent_snapshots );
		$next_step    = isset( $site_status_card['recommended_action'] ) ? (string) $site_status_card['recommended_action'] : '';

		return array(
			array(
				'title' => __( 'How to use this screen', 'zignites-sentinel' ),
				'body'  => $has_snapshot
					? __( 'Start with the hero summary, confirm the recommended snapshot, then open Update Readiness only when you need deeper proof or a guarded action review.', 'zignites-sentinel' )
					: __( 'Start here to understand whether Sentinel has enough restore evidence yet, then follow the recommended first step to begin building that evidence.', 'zignites-sentinel' ),
			),
			array(
				'title' => __( 'What this status means', 'zignites-sentinel' ),
				'body'  => __( 'Dashboard status reflects trust in the current restore evidence. It highlights whether Sentinel sees a safe path, a review step, or a risk that should be resolved first.', 'zignites-sentinel' ),
			),
			array(
				'title' => __( 'What to do next', 'zignites-sentinel' ),
				'body'  => '' !== $next_step
					? $next_step
					: __( 'Create a fresh snapshot, validate it, and return here once Sentinel can recommend a trusted restore point.', 'zignites-sentinel' ),
			),
		);
	}

	/**
	 * Build polished dashboard empty-state payloads.
	 *
	 * @param array $recent_snapshots     Recent snapshots.
	 * @param array $restore_health_strip Health strip payload.
	 * @param array $site_status_card     Site status card payload.
	 * @return array
	 */
	protected function build_empty_states( array $recent_snapshots, array $restore_health_strip, array $site_status_card ) {
		$has_snapshots = ! empty( $recent_snapshots );

		return array(
			'hero' => array(
				'title'       => __( 'Sentinel is ready to guide the first restore checkpoint.', 'zignites-sentinel' ),
				'description' => __( 'This dashboard becomes your high-level restore control panel after the first snapshot and baseline evidence are recorded.', 'zignites-sentinel' ),
				'next_step'   => __( 'Open Update Readiness and create a snapshot to establish your first controlled restore reference.', 'zignites-sentinel' ),
			),
			'readiness' => array(
				'title'       => __( 'No restore-ready snapshot yet.', 'zignites-sentinel' ),
				'description' => __( 'This section summarizes whether Sentinel has enough evidence to trust a restore review. It stays empty until the first snapshot exists.', 'zignites-sentinel' ),
				'next_step'   => __( 'Capture a snapshot, then run readiness checks to build a trusted restore path.', 'zignites-sentinel' ),
			),
			'activity' => array(
				'title'       => __( 'No recent activity to review.', 'zignites-sentinel' ),
				'description' => __( 'Critical conflicts, warnings, and recovery signals will gather here when Sentinel detects them.', 'zignites-sentinel' ),
				'next_step'   => $has_snapshots
					? __( 'Keep using snapshots and readiness checks. Sentinel will surface the first meaningful warnings here.', 'zignites-sentinel' )
					: __( 'Create your first snapshot so Sentinel can begin tracking restore-facing activity.', 'zignites-sentinel' ),
			),
			'health' => array(
				'title'       => __( 'No health comparison yet.', 'zignites-sentinel' ),
				'description' => __( 'Health comparison helps you judge whether a snapshot has a stable starting point and whether later recovery checks changed that picture.', 'zignites-sentinel' ),
				'next_step'   => __( 'Capture a baseline after taking a snapshot so Sentinel can compare future restore or rollback outcomes.', 'zignites-sentinel' ),
			),
			'timeline' => array(
				'title'       => __( 'No restore history yet.', 'zignites-sentinel' ),
				'description' => __( 'The timeline is a condensed narrative of snapshot captures, restore attempts, and rollback milestones.', 'zignites-sentinel' ),
				'next_step'   => __( 'Create and validate a snapshot first. Sentinel will start building the timeline as readiness and recovery events occur.', 'zignites-sentinel' ),
			),
			'snapshots' => array(
				'title'       => __( 'No snapshots have been captured yet.', 'zignites-sentinel' ),
				'description' => __( 'Snapshots are the starting point for trusted restore review, rollback confidence, and operator guidance across the plugin.', 'zignites-sentinel' ),
				'next_step'   => __( 'Open Update Readiness and create the first snapshot before using the restore-planning workspace.', 'zignites-sentinel' ),
			),
		);
	}

	/**
	 * Build a concise product-positioning note for dashboard surfaces.
	 *
	 * @return array
	 */
	protected function build_positioning_note() {
		return array(
			'title' => __( 'What Sentinel is designed to do', 'zignites-sentinel' ),
			'body'  => __( 'Sentinel helps operators prepare controlled restores, judge restore readiness, and recover with clearer rollback context. It does not promise a fully transactional or atomic restore.', 'zignites-sentinel' ),
		);
	}

	/**
	 * Add readiness and activity URLs to a snapshot reference payload.
	 *
	 * @param array  $snapshot         Snapshot reference payload.
	 * @param string $update_page_slug Update readiness page slug.
	 * @return array
	 */
	protected function add_snapshot_reference_urls( array $snapshot, $update_page_slug ) {
		if ( empty( $snapshot['id'] ) ) {
			return $snapshot;
		}

		$snapshot['detail_url'] = add_query_arg(
			array(
				'page'        => (string) $update_page_slug,
				'snapshot_id' => (int) $snapshot['id'],
			),
			admin_url( 'admin.php' )
		);
		$snapshot['activity_url'] = add_query_arg(
			array(
				'page'        => 'zignites-sentinel-event-logs',
				'snapshot_id' => (int) $snapshot['id'],
			),
			admin_url( 'admin.php' )
		);

		return $snapshot;
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
