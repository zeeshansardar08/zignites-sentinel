<?php
/**
 * Shared operator-facing status resolution for snapshots and dashboard summaries.
 *
 * @package ZignitesSentinel
 */

namespace Zignites\Sentinel\Admin;

use Zignites\Sentinel\Logging\LogRepository;
use Zignites\Sentinel\Snapshots\RestoreCheckpointStore;
use Zignites\Sentinel\Snapshots\RestoreExecutor;
use Zignites\Sentinel\Snapshots\RestoreJournalRecorder;
use Zignites\Sentinel\Snapshots\RestoreRollbackManager;
use Zignites\Sentinel\Snapshots\SnapshotArtifactRepository;

defined( 'ABSPATH' ) || exit;

class SnapshotStatusResolver {

	/**
	 * Log repository.
	 *
	 * @var LogRepository
	 */
	protected $logs;

	/**
	 * Restore checkpoint store.
	 *
	 * @var RestoreCheckpointStore
	 */
	protected $checkpoint_store;

	/**
	 * Restore journal recorder.
	 *
	 * @var RestoreJournalRecorder
	 */
	protected $journal_recorder;

	/**
	 * Snapshot artifact repository.
	 *
	 * @var SnapshotArtifactRepository
	 */
	protected $artifacts;

	/**
	 * Constructor.
	 *
	 * @param LogRepository             $logs             Log repository.
	 * @param RestoreCheckpointStore    $checkpoint_store Restore checkpoint store.
	 * @param RestoreJournalRecorder    $journal_recorder Restore journal recorder.
	 * @param SnapshotArtifactRepository $artifacts       Snapshot artifact repository.
	 */
	public function __construct( LogRepository $logs, RestoreCheckpointStore $checkpoint_store, RestoreJournalRecorder $journal_recorder, SnapshotArtifactRepository $artifacts ) {
		$this->logs             = $logs;
		$this->checkpoint_store = $checkpoint_store;
		$this->journal_recorder = $journal_recorder;
		$this->artifacts        = $artifacts;
	}

	/**
	 * Resolve normalized operator-facing status for a snapshot collection.
	 *
	 * @param array $snapshots Snapshot rows.
	 * @return array
	 */
	public function build_snapshot_status_index( array $snapshots ) {
		$snapshot_ids = array();

		foreach ( $snapshots as $snapshot ) {
			if ( ! empty( $snapshot['id'] ) ) {
				$snapshot_ids[] = (int) $snapshot['id'];
			}
		}

		$snapshot_ids = array_values( array_unique( $snapshot_ids ) );

		if ( empty( $snapshot_ids ) ) {
			return array();
		}

		$baseline_events = $this->index_latest_events_by_snapshot(
			$this->logs->get_recent_by_sources( array( 'snapshot-health' ), 300 ),
			$snapshot_ids,
			array( 'snapshot_health_baseline_captured' )
		);
		$stage_events    = $this->index_latest_events_by_snapshot(
			$this->logs->get_recent_by_sources( array( 'restore-stage' ), 300 ),
			$snapshot_ids,
			array( 'restore_stage_completed' )
		);
		$plan_events     = $this->index_latest_events_by_snapshot(
			$this->logs->get_recent_by_sources( array( 'restore-plan' ), 300 ),
			$snapshot_ids,
			array( 'restore_plan_created' )
		);
		$artifact_index  = $this->index_artifacts_by_snapshot( $this->artifacts->get_by_snapshot_ids( $snapshot_ids ) );
		$run_summaries   = $this->index_latest_runs_by_snapshot( $snapshot_ids );
		$current_baseline = get_option( ZNTS_OPTION_LAST_SNAPSHOT_HEALTH_BASELINE, array() );

		$index = array();

		foreach ( $snapshots as $snapshot ) {
			$snapshot_id = isset( $snapshot['id'] ) ? (int) $snapshot['id'] : 0;

			if ( $snapshot_id < 1 ) {
				continue;
			}

			$baseline = $this->resolve_baseline_state(
				$snapshot_id,
				is_array( $current_baseline ) ? $current_baseline : array(),
				isset( $baseline_events[ $snapshot_id ] ) ? $baseline_events[ $snapshot_id ] : array()
			);
			$stage    = $this->resolve_checkpoint_state(
				'stage',
				$snapshot_id,
				$this->checkpoint_store->get_stage_checkpoint( $snapshot_id ),
				isset( $stage_events[ $snapshot_id ] ) ? $stage_events[ $snapshot_id ] : array()
			);
			$plan     = $this->resolve_checkpoint_state(
				'plan',
				$snapshot_id,
				$this->checkpoint_store->get_plan_checkpoint( $snapshot_id ),
				isset( $plan_events[ $snapshot_id ] ) ? $plan_events[ $snapshot_id ] : array()
			);
			$artifacts = $this->resolve_artifact_state(
				isset( $artifact_index[ $snapshot_id ] ) ? $artifact_index[ $snapshot_id ] : array()
			);
			$activity  = $this->resolve_activity_state(
				isset( $run_summaries[ $snapshot_id ] ) ? $run_summaries[ $snapshot_id ] : array()
			);
			$freshness = $this->resolve_snapshot_freshness_state( $snapshot );

			$restore_ready = $baseline['present'] && 'current' === $stage['key'] && 'current' === $plan['key'];
			$trust         = $this->resolve_snapshot_trust_state( $freshness, $baseline, $artifacts, $stage, $plan, $activity );
			$risks         = $this->build_snapshot_risk_indicators( $freshness, $baseline, $artifacts, $stage, $plan, $activity, $restore_ready, $trust );
			$badges        = array(
				$this->build_badge( $freshness['badge'], $freshness['label'] ),
				$this->build_badge( $baseline['badge'], $baseline['label'] ),
				$this->build_badge( $artifacts['badge'], $artifacts['label'] ),
				$this->build_badge( $stage['badge'], $stage['label'] ),
				$this->build_badge( $plan['badge'], $plan['label'] ),
			);

			if ( ! empty( $activity['show_badge'] ) ) {
				$badges[] = $this->build_badge( $activity['badge'], $activity['label'] );
			}

			$badges[] = $this->build_badge(
				$restore_ready ? 'info' : 'critical',
				$restore_ready ? __( 'Restore ready', 'zignites-sentinel' ) : __( 'Restore blocked', 'zignites-sentinel' )
			);

			$index[ $snapshot_id ] = array(
				'snapshot_id'            => $snapshot_id,
				'created_at'             => isset( $snapshot['created_at'] ) ? (string) $snapshot['created_at'] : '',
				'baseline'               => $baseline,
				'artifacts'              => $artifacts,
				'stage'                  => $stage,
				'plan'                   => $plan,
				'activity'               => $activity,
				'freshness'              => $freshness,
				'restore_ready'          => $restore_ready,
				'has_recent_activity'    => ! empty( $activity['is_recent'] ),
				'has_stale_checkpoint'   => 'stale' === $stage['key'] || 'stale' === $plan['key'],
				'trust'                  => $trust,
				'validated'              => $restore_ready && ! empty( $artifacts['package_present'] ),
				'is_safe_candidate'      => 'safe' === $trust['key'],
				'is_last_known_good_candidate' => $this->is_last_known_good_candidate( $restore_ready, $artifacts, $activity ),
				'risk_indicators'        => $risks,
				'confidence_message'     => $this->build_snapshot_confidence_message( $trust, $freshness, $activity, $restore_ready ),
				'status_badges'          => $badges,
			);
		}

		return $index;
	}

	/**
	 * Filter a snapshot list by label and normalized status.
	 *
	 * @param array  $snapshots     Snapshot rows.
	 * @param array  $status_index  Resolved status index.
	 * @param string $label_search  Label search term.
	 * @param string $status_filter Status filter key.
	 * @param int    $limit         Max rows to return.
	 * @return array
	 */
	public function filter_snapshots( array $snapshots, array $status_index, $label_search = '', $status_filter = '', $limit = 12 ) {
		$label_search = sanitize_text_field( (string) $label_search );
		$status_filter = sanitize_key( (string) $status_filter );
		$limit         = max( 1, absint( $limit ) );

		$filtered = array_values(
			array_filter(
				$snapshots,
				function ( $snapshot ) use ( $status_index, $label_search, $status_filter ) {
					$snapshot_id = isset( $snapshot['id'] ) ? (int) $snapshot['id'] : 0;
					$status      = isset( $status_index[ $snapshot_id ] ) ? $status_index[ $snapshot_id ] : array();

					if ( '' !== $label_search ) {
						$label = isset( $snapshot['label'] ) ? (string) $snapshot['label'] : '';

						if ( false === stripos( $label, $label_search ) ) {
							return false;
						}
					}

					return $this->matches_status_filter( $status, $status_filter );
				}
			)
		);

		return array_slice( $filtered, 0, $limit );
	}

	/**
	 * Return supported snapshot status filters.
	 *
	 * @return array
	 */
	public function get_snapshot_filter_options() {
		return array(
			'' => __( 'All snapshots', 'zignites-sentinel' ),
			'baseline-present' => __( 'Baseline present', 'zignites-sentinel' ),
			'rollback-package' => __( 'Rollback package saved', 'zignites-sentinel' ),
			'stage-current' => __( 'Stage fresh', 'zignites-sentinel' ),
			'plan-current' => __( 'Plan fresh', 'zignites-sentinel' ),
			'recent-restore-activity' => __( 'Recent restore activity', 'zignites-sentinel' ),
			'restore-ready' => __( 'Restore ready', 'zignites-sentinel' ),
			'checkpoint-stale' => __( 'Stage or plan stale', 'zignites-sentinel' ),
			'checkpoint-missing' => __( 'Stage or plan missing', 'zignites-sentinel' ),
		);
	}

	/**
	 * Build a top-level site status card payload for the dashboard.
	 *
	 * @param array $health_score   Health score payload.
	 * @param array $recent_snapshots Recent snapshots.
	 * @param array $status_index   Snapshot status index.
	 * @return array
	 */
	public function build_site_status_card( array $health_score, array $recent_snapshots, array $status_index ) {
		$latest_snapshot = ! empty( $recent_snapshots[0] ) ? $recent_snapshots[0] : array();
		$latest_status   = ( ! empty( $latest_snapshot['id'] ) && isset( $status_index[ (int) $latest_snapshot['id'] ] ) ) ? $status_index[ (int) $latest_snapshot['id'] ] : array();
		$system_health   = $this->build_system_health_card( $health_score, $recent_snapshots, $status_index );
		$intelligence    = $this->build_snapshot_intelligence( $recent_snapshots, $status_index );
		$conflicts       = isset( $health_score['details']['open_conflicts'] ) && is_array( $health_score['details']['open_conflicts'] ) ? $health_score['details']['open_conflicts'] : array();
		$critical_count  = isset( $conflicts['critical'] ) ? (int) $conflicts['critical'] : 0;
		$error_count     = isset( $conflicts['error'] ) ? (int) $conflicts['error'] : 0;
		$warning_count   = isset( $conflicts['warning'] ) ? (int) $conflicts['warning'] : 0;
		$recent_failure  = ! empty( $latest_status['activity']['has_failure'] );
		$has_snapshot    = ! empty( $latest_snapshot );
		$restore_ready   = ! empty( $latest_status['restore_ready'] );

		$status = 'stable';

		if ( $critical_count > 0 || $recent_failure ) {
			$status = 'at_risk';
		} elseif ( ! $has_snapshot || $error_count > 0 || $warning_count > 0 || empty( $latest_status['baseline']['present'] ) || empty( $latest_status['artifacts']['package_present'] ) || empty( $restore_ready ) || ! empty( $latest_status['has_stale_checkpoint'] ) ) {
			$status = 'needs_attention';
		}

		if ( ! empty( $system_health['status'] ) && 'risky' === $system_health['status'] ) {
			$status = 'at_risk';
		} elseif ( ! empty( $system_health['status'] ) && 'warning' === $system_health['status'] && 'at_risk' !== $status ) {
			$status = 'needs_attention';
		}

		$signals = array();
		$signals[] = sprintf(
			/* translators: 1: system health label, 2: system health summary */
			__( 'System health: %1$s. %2$s', 'zignites-sentinel' ),
			isset( $system_health['label'] ) ? (string) $system_health['label'] : __( 'Unknown', 'zignites-sentinel' ),
			isset( $system_health['summary'] ) ? (string) $system_health['summary'] : ''
		);
		$signals[] = sprintf(
			/* translators: 1: warning count, 2: error count, 3: critical count */
			__( 'Open conflicts: %1$d warning, %2$d error, %3$d critical.', 'zignites-sentinel' ),
			$warning_count,
			$error_count,
			$critical_count
		);

		if ( $has_snapshot ) {
			$signals[] = sprintf(
				/* translators: %s: snapshot label */
				__( 'Latest snapshot: %s.', 'zignites-sentinel' ),
				isset( $latest_snapshot['label'] ) ? (string) $latest_snapshot['label'] : __( 'Unknown snapshot', 'zignites-sentinel' )
			);
			$signals[] = ! empty( $latest_status['baseline']['present'] )
				? __( 'Baseline status: present.', 'zignites-sentinel' )
				: __( 'Baseline status: missing.', 'zignites-sentinel' );
			$signals[] = ! empty( $latest_status['artifacts']['package_present'] )
				? __( 'Rollback package: saved.', 'zignites-sentinel' )
				: __( 'Rollback package: missing.', 'zignites-sentinel' );
			$signals[] = sprintf(
				/* translators: 1: stage label, 2: plan label */
				__( 'Restore gates: %1$s, %2$s.', 'zignites-sentinel' ),
				isset( $latest_status['stage']['label'] ) ? (string) $latest_status['stage']['label'] : __( 'No stage', 'zignites-sentinel' ),
				isset( $latest_status['plan']['label'] ) ? (string) $latest_status['plan']['label'] : __( 'No plan', 'zignites-sentinel' )
			);
			$signals[] = ! empty( $latest_status['restore_ready'] )
				? __( 'Restore status: ready.', 'zignites-sentinel' )
				: __( 'Restore status: blocked.', 'zignites-sentinel' );

			if ( ! empty( $latest_status['activity']['message'] ) ) {
				$signals[] = $latest_status['activity']['message'];
			}
		} else {
			$signals[] = __( 'No snapshot is currently available for restore planning.', 'zignites-sentinel' );
		}

		return array(
			'status'             => $status,
			'label'              => $this->map_site_status_label( $status ),
			'badge'              => $this->map_site_status_badge( $status ),
			'recommended_action' => $this->build_recommended_action( $status, $latest_snapshot, $latest_status, $critical_count, $error_count, $warning_count ),
			'primary_action'     => $this->build_primary_action( $status, $latest_snapshot, $latest_status, $critical_count, $error_count, $warning_count ),
			'signals'            => $signals,
			'insights'           => isset( $intelligence['insights'] ) && is_array( $intelligence['insights'] ) ? $intelligence['insights'] : array(),
			'confidence_message' => ! empty( $intelligence['selected_snapshot']['message'] )
				? (string) $intelligence['selected_snapshot']['message']
				: ( isset( $system_health['confidence_message'] ) ? (string) $system_health['confidence_message'] : '' ),
			'system_health'      => $system_health,
			'snapshot_intelligence' => $intelligence,
			'latest_snapshot'    => $latest_snapshot,
			'latest_status'      => $latest_status,
		);
	}

	/**
	 * Build a global trust indicator from snapshot freshness, readiness, failures, and outcome history.
	 *
	 * @param array      $health_score     Diagnostic health score payload.
	 * @param array      $snapshots        Candidate snapshot rows.
	 * @param array      $status_index     Snapshot status index.
	 * @param array|null $selected_snapshot Optional selected snapshot.
	 * @return array
	 */
	public function build_system_health_card( array $health_score, array $snapshots, array $status_index, $selected_snapshot = null ) {
		$latest_snapshot = ! empty( $snapshots[0] ) ? $snapshots[0] : array();
		$latest_status   = $this->resolve_snapshot_status_reference( $latest_snapshot, $status_index );
		$selected_status = $this->resolve_snapshot_status_reference( $selected_snapshot, $status_index );
		$active_status   = ! empty( $selected_status ) ? $selected_status : $latest_status;
		$active_snapshot = ! empty( $selected_status ) && is_array( $selected_snapshot ) ? $selected_snapshot : $latest_snapshot;
		$outcome_label   = $this->format_last_outcome_label( $active_status );
		$freshness_key   = isset( $active_status['freshness']['key'] ) ? (string) $active_status['freshness']['key'] : 'missing';
		$readiness_key   = $this->resolve_system_readiness_key( $active_status );
		$failure_key     = ! empty( $active_status['activity']['has_failure'] ) ? 'failure' : 'clear';
		$outcome_key     = $this->resolve_system_outcome_key( $active_status );
		$status          = 'safe';

		if ( empty( $active_snapshot ) || in_array( $freshness_key, array( 'stale', 'unknown', 'missing' ), true ) || 'failure' === $failure_key || 'failure' === $outcome_key ) {
			$status = 'risky';
		} elseif ( in_array( $freshness_key, array( 'aging' ), true ) || 'warning' === $readiness_key || 'warning' === $outcome_key ) {
			$status = 'warning';
		}

		$summary = __( 'System is safe to proceed.', 'zignites-sentinel' );

		if ( 'risky' === $status ) {
			if ( 'failure' === $failure_key ) {
				$summary = __( 'Recent failure detected - review before continuing.', 'zignites-sentinel' );
			} elseif ( empty( $active_snapshot ) ) {
				$summary = __( 'No snapshot evidence is available yet.', 'zignites-sentinel' );
			} elseif ( in_array( $freshness_key, array( 'stale', 'unknown', 'missing' ), true ) ) {
				$summary = __( 'Snapshot evidence is stale or incomplete.', 'zignites-sentinel' );
			} else {
				$summary = __( 'Current restore evidence is not strong enough to trust the workspace yet.', 'zignites-sentinel' );
			}
		} elseif ( 'warning' === $status ) {
			$summary = __( 'System trust needs review before continuing.', 'zignites-sentinel' );
		}

		return array(
			'status'             => $status,
			'label'              => 'risky' === $status ? __( 'Risky', 'zignites-sentinel' ) : ( 'warning' === $status ? __( 'Warning', 'zignites-sentinel' ) : __( 'Safe', 'zignites-sentinel' ) ),
			'badge'              => 'risky' === $status ? 'critical' : ( 'warning' === $status ? 'warning' : 'info' ),
			'title'              => __( 'System Health', 'zignites-sentinel' ),
			'summary'            => $summary,
			'confidence_message' => 'safe' === $status
				? __( 'System is safe to proceed.', 'zignites-sentinel' )
				: ( 'warning' === $status
					? __( 'System needs a quick trust review before continuing.', 'zignites-sentinel' )
					: __( 'Recent failure detected - review before continuing.', 'zignites-sentinel' ) ),
			'rows'               => array(
				array(
					'label' => __( 'Snapshot freshness', 'zignites-sentinel' ),
					'value' => ! empty( $active_status['freshness']['label'] ) ? (string) $active_status['freshness']['label'] : __( 'No snapshot evidence', 'zignites-sentinel' ),
				),
				array(
					'label' => __( 'Readiness status', 'zignites-sentinel' ),
					'value' => $this->format_system_readiness_label( $readiness_key ),
				),
				array(
					'label' => __( 'Unresolved failures', 'zignites-sentinel' ),
					'value' => 'failure' === $failure_key ? __( 'Detected', 'zignites-sentinel' ) : __( 'None detected', 'zignites-sentinel' ),
				),
				array(
					'label' => __( 'Last restore outcome', 'zignites-sentinel' ),
					'value' => $outcome_label,
				),
			),
			'logic'              => array(
				'freshness'           => $freshness_key,
				'readiness'           => $readiness_key,
				'unresolved_failures' => 'failure' === $failure_key,
				'last_outcome'        => $outcome_key,
			),
			'diagnostic_score'    => isset( $health_score['score'] ) ? (int) $health_score['score'] : 0,
		);
	}

	/**
	 * Build recommendation, last-known-good, and insight state for a snapshot collection.
	 *
	 * @param array      $snapshots         Candidate snapshots.
	 * @param array      $status_index      Snapshot status index.
	 * @param array|null $selected_snapshot Optional selected snapshot.
	 * @return array
	 */
	public function build_snapshot_intelligence( array $snapshots, array $status_index, $selected_snapshot = null ) {
		$candidates          = array();
		$safe_candidates     = array();
		$validated_candidates = array();
		$known_good_candidates = array();

		foreach ( $snapshots as $snapshot ) {
			$status = $this->resolve_snapshot_status_reference( $snapshot, $status_index );

			if ( empty( $status ) ) {
				continue;
			}

			$candidate = $this->build_snapshot_reference( $snapshot, $status );
			$candidates[] = $candidate;

			if ( ! empty( $status['is_safe_candidate'] ) ) {
				$safe_candidates[] = $candidate;
			}

			if ( ! empty( $status['validated'] ) ) {
				$validated_candidates[] = $candidate;
			}

			if ( ! empty( $status['is_last_known_good_candidate'] ) ) {
				$known_good_candidates[] = $candidate;
			}
		}

		$recommended_snapshot = ! empty( $safe_candidates ) ? $safe_candidates[0] : array();
		$recommendation_mode  = 'safe';

		if ( empty( $recommended_snapshot ) && ! empty( $validated_candidates ) ) {
			$recommended_snapshot = $validated_candidates[0];
			$recommendation_mode  = 'fallback';
		}

		$last_known_good = ! empty( $known_good_candidates ) ? $known_good_candidates[0] : array();

		if ( empty( $last_known_good ) && ! empty( $recommended_snapshot ) ) {
			$last_known_good = $recommended_snapshot;
		}

		$selected_status = $this->resolve_snapshot_status_reference( $selected_snapshot, $status_index );
		$selected        = $this->build_selected_snapshot_reference( $selected_snapshot, $selected_status, $recommended_snapshot, $last_known_good );
		$warnings        = $this->build_snapshot_intelligence_warnings( $snapshots, $recommended_snapshot, $selected, $safe_candidates, $validated_candidates );
		$insights        = $this->build_contextual_insights( $snapshots, $recommended_snapshot, $last_known_good, $selected, $warnings );

		if ( ! empty( $recommended_snapshot ) ) {
			$recommended_snapshot['recommendation_mode'] = $recommendation_mode;
			$recommended_snapshot['reason']              = 'safe' === $recommendation_mode
				? __( 'Latest safe and validated snapshot.', 'zignites-sentinel' )
				: __( 'Most recent validated snapshot, but it still needs review before being treated as fully safe.', 'zignites-sentinel' );
		}

		if ( ! empty( $last_known_good ) && empty( $last_known_good['reason'] ) ) {
			$last_known_good['reason'] = ! empty( $last_known_good['last_outcome_label'] )
				? sprintf(
					/* translators: %s: last outcome label */
					__( 'Last known good state is based on %s.', 'zignites-sentinel' ),
					$last_known_good['last_outcome_label']
				)
				: __( 'Last known good state is based on the latest validated snapshot.', 'zignites-sentinel' );
		}

		return array(
			'recommended_snapshot' => $recommended_snapshot,
			'last_known_good'      => $last_known_good,
			'selected_snapshot'    => $selected,
			'warnings'             => $warnings,
			'insights'             => $insights,
		);
	}

	/**
	 * Build a condensed chronological execution history from snapshots and run summaries.
	 *
	 * @param array $snapshots Snapshot rows.
	 * @param int   $limit     Maximum timeline items.
	 * @return array
	 */
	public function build_operator_timeline( array $snapshots, $limit = 8 ) {
		$limit         = max( 1, absint( $limit ) );
		$snapshot_ids  = array();
		$snapshot_map  = array();
		$timeline_rows = array();

		foreach ( $snapshots as $snapshot ) {
			$snapshot_id = isset( $snapshot['id'] ) ? (int) $snapshot['id'] : 0;

			if ( $snapshot_id < 1 ) {
				continue;
			}

			$snapshot_ids[]            = $snapshot_id;
			$snapshot_map[ $snapshot_id ] = $snapshot;
			$timeline_rows[]           = $this->build_snapshot_timeline_item( $snapshot );
		}

		if ( ! empty( $snapshot_ids ) ) {
			$runs = array_merge(
				$this->journal_recorder->summarize_recent_runs( RestoreExecutor::JOURNAL_SOURCE, 100 ),
				$this->journal_recorder->summarize_recent_runs( RestoreRollbackManager::JOURNAL_SOURCE, 100 )
			);

			foreach ( $runs as $run ) {
				$snapshot_id = isset( $run['snapshot_id'] ) ? absint( $run['snapshot_id'] ) : 0;

				if ( $snapshot_id < 1 || ! isset( $snapshot_map[ $snapshot_id ] ) ) {
					continue;
				}

				$timeline_rows[] = $this->build_run_timeline_item( $run, $snapshot_map[ $snapshot_id ] );
			}
		}

		$timeline_rows = array_values(
			array_filter(
				$timeline_rows,
				function ( $item ) {
					return ! empty( $item['timestamp'] );
				}
			)
		);

		usort(
			$timeline_rows,
			function ( $left, $right ) {
				return strcmp( (string) $right['timestamp'], (string) $left['timestamp'] );
			}
		);

		$timeline_rows = array_slice( $timeline_rows, 0, $limit );

		return array(
			'items'         => $timeline_rows,
			'summary'       => empty( $timeline_rows )
				? __( 'No recent site history is available yet.', 'zignites-sentinel' )
				: sprintf(
					/* translators: %d: item count */
					__( 'Recent site history is condensed into %d operator-facing milestones.', 'zignites-sentinel' ),
					count( $timeline_rows )
				),
			'empty_message' => __( 'No recent site history is available yet.', 'zignites-sentinel' ),
		);
	}

	/**
	 * Index latest matching events by snapshot ID.
	 *
	 * @param array $rows         Log rows.
	 * @param array $snapshot_ids Snapshot IDs.
	 * @param array $event_types  Allowed event types.
	 * @return array
	 */
	protected function index_latest_events_by_snapshot( array $rows, array $snapshot_ids, array $event_types ) {
		$snapshot_ids = array_map( 'absint', $snapshot_ids );
		$indexed      = array();

		foreach ( $rows as $row ) {
			$event_type = isset( $row['event_type'] ) ? (string) $row['event_type'] : '';

			if ( ! empty( $event_types ) && ! in_array( $event_type, $event_types, true ) ) {
				continue;
			}

			$context     = ! empty( $row['context'] ) ? json_decode( (string) $row['context'], true ) : array();
			$snapshot_id = isset( $context['snapshot_id'] ) ? absint( $context['snapshot_id'] ) : 0;

			if ( $snapshot_id < 1 || ! in_array( $snapshot_id, $snapshot_ids, true ) || isset( $indexed[ $snapshot_id ] ) ) {
				continue;
			}

			$row['context_decoded'] = is_array( $context ) ? $context : array();
			$indexed[ $snapshot_id ] = $row;
		}

		return $indexed;
	}

	/**
	 * Index latest restore or rollback runs by snapshot ID.
	 *
	 * @param array $snapshot_ids Snapshot IDs.
	 * @return array
	 */
	protected function index_latest_runs_by_snapshot( array $snapshot_ids ) {
		$rows    = array_merge(
			$this->journal_recorder->summarize_recent_runs( RestoreExecutor::JOURNAL_SOURCE, 300 ),
			$this->journal_recorder->summarize_recent_runs( RestoreRollbackManager::JOURNAL_SOURCE, 300 )
		);
		$indexed = array();

		foreach ( $rows as $row ) {
			$snapshot_id = isset( $row['snapshot_id'] ) ? absint( $row['snapshot_id'] ) : 0;

			if ( $snapshot_id < 1 || ! in_array( $snapshot_id, $snapshot_ids, true ) || isset( $indexed[ $snapshot_id ] ) ) {
				continue;
			}

			$indexed[ $snapshot_id ] = $row;
		}

		return $indexed;
	}

	/**
	 * Group artifact rows by snapshot ID.
	 *
	 * @param array $artifacts Artifact rows.
	 * @return array
	 */
	protected function index_artifacts_by_snapshot( array $artifacts ) {
		$indexed = array();

		foreach ( $artifacts as $artifact ) {
			$snapshot_id = isset( $artifact['snapshot_id'] ) ? absint( $artifact['snapshot_id'] ) : 0;

			if ( $snapshot_id < 1 ) {
				continue;
			}

			if ( ! isset( $indexed[ $snapshot_id ] ) ) {
				$indexed[ $snapshot_id ] = array();
			}

			$indexed[ $snapshot_id ][] = $artifact;
		}

		return $indexed;
	}

	/**
	 * Resolve baseline state.
	 *
	 * @param int   $snapshot_id      Snapshot ID.
	 * @param array $current_baseline Stored baseline option.
	 * @param array $baseline_event   Latest baseline event.
	 * @return array
	 */
	protected function resolve_baseline_state( $snapshot_id, array $current_baseline, array $baseline_event ) {
		$present      = ! empty( $current_baseline['snapshot_id'] ) && (int) $current_baseline['snapshot_id'] === $snapshot_id;
		$generated_at = $present && ! empty( $current_baseline['generated_at'] ) ? (string) $current_baseline['generated_at'] : '';
		$status       = $present && ! empty( $current_baseline['status'] ) ? sanitize_key( (string) $current_baseline['status'] ) : '';

		if ( ! $present && ! empty( $baseline_event ) ) {
			$context      = isset( $baseline_event['context_decoded'] ) && is_array( $baseline_event['context_decoded'] ) ? $baseline_event['context_decoded'] : array();
			$present      = true;
			$generated_at = isset( $baseline_event['created_at'] ) ? (string) $baseline_event['created_at'] : '';
			$status       = isset( $context['status'] ) ? sanitize_key( (string) $context['status'] ) : '';
		}

		return array(
			'present'      => $present,
			'status'       => $status,
			'generated_at' => $generated_at,
			'badge'        => $present ? 'info' : 'warning',
			'label'        => $present ? __( 'Baseline present', 'zignites-sentinel' ) : __( 'Baseline missing', 'zignites-sentinel' ),
		);
	}

	/**
	 * Resolve rollback artifact state.
	 *
	 * @param array $artifacts Artifact rows for a snapshot.
	 * @return array
	 */
	protected function resolve_artifact_state( array $artifacts ) {
		$package_present = false;
		$export_present  = false;

		foreach ( $artifacts as $artifact ) {
			$type = isset( $artifact['artifact_type'] ) ? sanitize_key( (string) $artifact['artifact_type'] ) : '';

			if ( 'package' === $type ) {
				$package_present = true;
			}

			if ( 'export' === $type ) {
				$export_present = true;
			}
		}

		return array(
			'package_present' => $package_present,
			'export_present'  => $export_present,
			'badge'           => $package_present ? 'info' : 'warning',
			'label'           => $package_present ? __( 'Package saved', 'zignites-sentinel' ) : __( 'No package', 'zignites-sentinel' ),
		);
	}

	/**
	 * Resolve a checkpoint state.
	 *
	 * @param string $type          Checkpoint type.
	 * @param int    $snapshot_id   Snapshot ID.
	 * @param array  $checkpoint    Active checkpoint.
	 * @param array  $latest_event  Latest historical event.
	 * @return array
	 */
	protected function resolve_checkpoint_state( $type, $snapshot_id, array $checkpoint, array $latest_event ) {
		$type        = sanitize_key( (string) $type );
		$is_current  = ! empty( $checkpoint['snapshot_id'] ) && (int) $checkpoint['snapshot_id'] === $snapshot_id;
		$fresh       = $is_current ? $this->is_checkpoint_fresh( $checkpoint ) : false;
		$status      = $is_current && ! empty( $checkpoint['status'] ) ? sanitize_key( (string) $checkpoint['status'] ) : '';
		$generated_at = $is_current && ! empty( $checkpoint['generated_at'] ) ? (string) $checkpoint['generated_at'] : '';

		if ( $is_current && $fresh && ( 'stage' !== $type || 'ready' === $status ) && ( 'plan' !== $type || 'blocked' !== $status ) ) {
			return array(
				'key'          => 'current',
				'generated_at' => $generated_at,
				'badge'        => 'info',
				'label'        => 'stage' === $type ? __( 'Stage fresh', 'zignites-sentinel' ) : __( 'Plan fresh', 'zignites-sentinel' ),
			);
		}

		if ( $is_current || ! empty( $latest_event ) ) {
			return array(
				'key'          => 'stale',
				'generated_at' => $generated_at ? $generated_at : ( isset( $latest_event['created_at'] ) ? (string) $latest_event['created_at'] : '' ),
				'badge'        => 'warning',
				'label'        => 'stage' === $type ? __( 'Stage stale', 'zignites-sentinel' ) : __( 'Plan stale', 'zignites-sentinel' ),
			);
		}

		return array(
			'key'          => 'missing',
			'generated_at' => '',
			'badge'        => 'critical',
			'label'        => 'stage' === $type ? __( 'Stage missing', 'zignites-sentinel' ) : __( 'Plan missing', 'zignites-sentinel' ),
		);
	}

	/**
	 * Resolve recent restore activity state.
	 *
	 * @param array $summary Latest run summary.
	 * @return array
	 */
	protected function resolve_activity_state( array $summary ) {
		if ( empty( $summary ) ) {
			return array(
				'is_recent'        => false,
				'has_failure'      => false,
				'badge'            => 'warning',
				'label'            => __( 'No recent restore', 'zignites-sentinel' ),
				'message'          => __( 'No recent restore or rollback activity was recorded for this snapshot.', 'zignites-sentinel' ),
				'show_badge'       => false,
				'latest_timestamp' => '',
				'terminal_status'  => '',
				'source'           => '',
				'outcome_key'      => 'unknown',
				'outcome_label'    => __( 'Unknown', 'zignites-sentinel' ),
			);
		}

		$latest_timestamp = isset( $summary['latest_timestamp'] ) ? (string) $summary['latest_timestamp'] : '';
		$latest_ts        = '' !== $latest_timestamp ? strtotime( $latest_timestamp ) : false;
		$is_recent        = false !== $latest_ts && $latest_ts >= ( time() - ( 14 * DAY_IN_SECONDS ) );
		$terminal_status  = isset( $summary['terminal_status'] ) ? sanitize_key( (string) $summary['terminal_status'] ) : '';
		$source           = isset( $summary['source'] ) ? sanitize_key( (string) $summary['source'] ) : '';
		$has_failure      = in_array( $terminal_status, array( 'fail', 'blocked', 'partial' ), true );
		$label            = $is_recent ? __( 'Recent restore', 'zignites-sentinel' ) : __( 'Past restore', 'zignites-sentinel' );
		$message          = $is_recent
			? __( 'Recent restore or rollback activity was recorded for this snapshot.', 'zignites-sentinel' )
			: __( 'Restore or rollback activity exists for this snapshot, but it is not recent.', 'zignites-sentinel' );
		$outcome_key      = 'success';
		$outcome_label    = __( 'Successful restore', 'zignites-sentinel' );

		if ( $has_failure ) {
			$label   = __( 'Recent failure', 'zignites-sentinel' );
			$message = __( 'A recent restore or rollback run for this snapshot finished with a failure or partial state.', 'zignites-sentinel' );
			$outcome_key   = in_array( $terminal_status, array( 'fail', 'blocked' ), true ) ? 'failure' : 'warning';
			$outcome_label = 'failure' === $outcome_key ? __( 'Failed restore', 'zignites-sentinel' ) : __( 'Partial restore', 'zignites-sentinel' );
		} elseif ( RestoreRollbackManager::JOURNAL_SOURCE === $source ) {
			$outcome_key   = 'rollback_success';
			$outcome_label = __( 'Successful rollback', 'zignites-sentinel' );
		} elseif ( ! in_array( $terminal_status, array( 'pass', 'ready', '' ), true ) ) {
			$outcome_key   = 'warning';
			$outcome_label = __( 'Restore finished with warnings', 'zignites-sentinel' );
		}

		return array(
			'is_recent'        => $is_recent,
			'has_failure'      => $has_failure,
			'badge'            => $has_failure ? 'warning' : 'info',
			'label'            => $label,
			'message'          => $message,
			'show_badge'       => true,
			'latest_timestamp' => $latest_timestamp,
			'terminal_status'  => $terminal_status,
			'source'           => $source,
			'outcome_key'      => $outcome_key,
			'outcome_label'    => $outcome_label,
		);
	}

	/**
	 * Resolve snapshot freshness from the recorded capture timestamp.
	 *
	 * @param array $snapshot Snapshot row.
	 * @return array
	 */
	protected function resolve_snapshot_freshness_state( array $snapshot ) {
		$created_at = isset( $snapshot['created_at'] ) ? (string) $snapshot['created_at'] : '';

		if ( '' === $created_at ) {
			return array(
				'key'         => 'unknown',
				'badge'       => 'warning',
				'label'       => __( 'Snapshot age unknown', 'zignites-sentinel' ),
				'created_at'  => '',
				'age_days'    => 0,
				'age_seconds' => 0,
			);
		}

		$created_ts = strtotime( $created_at );

		if ( false === $created_ts ) {
			return array(
				'key'         => 'unknown',
				'badge'       => 'warning',
				'label'       => __( 'Snapshot age unknown', 'zignites-sentinel' ),
				'created_at'  => $created_at,
				'age_days'    => 0,
				'age_seconds' => 0,
			);
		}

		$age_seconds = max( 0, time() - $created_ts );
		$age_days    = (int) floor( $age_seconds / DAY_IN_SECONDS );
		$key         = 'fresh';
		$badge       = 'info';
		$label       = __( 'Fresh snapshot', 'zignites-sentinel' );

		if ( $age_seconds > ( 21 * DAY_IN_SECONDS ) ) {
			$key   = 'stale';
			$badge = 'critical';
			$label = __( 'Stale snapshot', 'zignites-sentinel' );
		} elseif ( $age_seconds > ( 7 * DAY_IN_SECONDS ) ) {
			$key   = 'aging';
			$badge = 'warning';
			$label = __( 'Aging snapshot', 'zignites-sentinel' );
		}

		return array(
			'key'         => $key,
			'badge'       => $badge,
			'label'       => $label,
			'created_at'  => $created_at,
			'age_days'    => $age_days,
			'age_seconds' => $age_seconds,
		);
	}

	/**
	 * Resolve trust state for a snapshot from freshness, validation, and outcome history.
	 *
	 * @param array $freshness Freshness state.
	 * @param array $baseline  Baseline state.
	 * @param array $artifacts Artifact state.
	 * @param array $stage     Stage state.
	 * @param array $plan      Plan state.
	 * @param array $activity  Activity state.
	 * @return array
	 */
	protected function resolve_snapshot_trust_state( array $freshness, array $baseline, array $artifacts, array $stage, array $plan, array $activity ) {
		$key = 'safe';

		if ( ! empty( $activity['has_failure'] ) || in_array( isset( $freshness['key'] ) ? $freshness['key'] : '', array( 'stale', 'unknown' ), true ) || empty( $baseline['present'] ) || empty( $artifacts['package_present'] ) || 'missing' === ( isset( $stage['key'] ) ? $stage['key'] : '' ) || 'missing' === ( isset( $plan['key'] ) ? $plan['key'] : '' ) ) {
			$key = 'risky';
		} elseif ( 'aging' === ( isset( $freshness['key'] ) ? $freshness['key'] : '' ) || 'stale' === ( isset( $stage['key'] ) ? $stage['key'] : '' ) || 'stale' === ( isset( $plan['key'] ) ? $plan['key'] : '' ) || in_array( isset( $activity['outcome_key'] ) ? $activity['outcome_key'] : '', array( 'warning', 'unknown' ), true ) ) {
			$key = 'warning';
		}

		return array(
			'key'   => $key,
			'badge' => 'risky' === $key ? 'critical' : ( 'warning' === $key ? 'warning' : 'info' ),
			'label' => 'risky' === $key ? __( 'Risky snapshot', 'zignites-sentinel' ) : ( 'warning' === $key ? __( 'Review snapshot', 'zignites-sentinel' ) : __( 'Safe snapshot', 'zignites-sentinel' ) ),
		);
	}

	/**
	 * Build visible risk indicators for a snapshot.
	 *
	 * @param array $freshness     Freshness state.
	 * @param array $baseline      Baseline state.
	 * @param array $artifacts     Artifact state.
	 * @param array $stage         Stage state.
	 * @param array $plan          Plan state.
	 * @param array $activity      Activity state.
	 * @param bool  $restore_ready Restore readiness flag.
	 * @param array $trust         Trust state.
	 * @return array
	 */
	protected function build_snapshot_risk_indicators( array $freshness, array $baseline, array $artifacts, array $stage, array $plan, array $activity, $restore_ready, array $trust ) {
		$indicators = array();

		if ( 'aging' === $freshness['key'] || 'stale' === $freshness['key'] || 'unknown' === $freshness['key'] ) {
			$indicators[] = $this->build_badge( $freshness['badge'], $freshness['label'] );
		}

		if ( empty( $baseline['present'] ) ) {
			$indicators[] = $this->build_badge( 'warning', __( 'Unverified baseline', 'zignites-sentinel' ) );
		}

		if ( empty( $artifacts['package_present'] ) ) {
			$indicators[] = $this->build_badge( 'critical', __( 'No rollback package', 'zignites-sentinel' ) );
		}

		if ( 'stale' === $stage['key'] || 'stale' === $plan['key'] ) {
			$indicators[] = $this->build_badge( 'warning', __( 'Validation evidence stale', 'zignites-sentinel' ) );
		}

		if ( ! $restore_ready && ( 'missing' === $stage['key'] || 'missing' === $plan['key'] ) ) {
			$indicators[] = $this->build_badge( 'critical', __( 'Validation incomplete', 'zignites-sentinel' ) );
		}

		if ( ! empty( $activity['has_failure'] ) ) {
			$indicators[] = $this->build_badge( 'critical', __( 'Recent failure', 'zignites-sentinel' ) );
		}

		if ( empty( $activity['latest_timestamp'] ) ) {
			$indicators[] = $this->build_badge( 'warning', __( 'No journal history', 'zignites-sentinel' ) );
		}

		if ( 'safe' === $trust['key'] ) {
			$indicators[] = $this->build_badge( 'info', __( 'Validated', 'zignites-sentinel' ) );
		}

		return $this->dedupe_badges( $indicators );
	}

	/**
	 * Build a short confidence message for a snapshot.
	 *
	 * @param array $trust         Trust state.
	 * @param array $freshness     Freshness state.
	 * @param array $activity      Activity state.
	 * @param bool  $restore_ready Restore readiness flag.
	 * @return string
	 */
	protected function build_snapshot_confidence_message( array $trust, array $freshness, array $activity, $restore_ready ) {
		if ( ! empty( $activity['has_failure'] ) ) {
			return __( 'Recent failure detected - review before continuing.', 'zignites-sentinel' );
		}

		if ( 'safe' === $trust['key'] && $restore_ready ) {
			return __( 'System is safe to proceed.', 'zignites-sentinel' );
		}

		if ( 'aging' === $freshness['key'] ) {
			return __( 'Snapshot evidence is aging and should be reviewed before use.', 'zignites-sentinel' );
		}

		if ( 'stale' === $freshness['key'] ) {
			return __( 'Snapshot evidence is stale and should not be trusted without review.', 'zignites-sentinel' );
		}

		if ( empty( $activity['latest_timestamp'] ) ) {
			return __( 'No restore history is available, so Sentinel is relying on current validation evidence only.', 'zignites-sentinel' );
		}

		return __( 'Snapshot needs review before being treated as trusted.', 'zignites-sentinel' );
	}

	/**
	 * Determine whether a snapshot can anchor the last-known-good state.
	 *
	 * @param bool  $restore_ready Restore readiness flag.
	 * @param array $artifacts     Artifact state.
	 * @param array $activity      Activity state.
	 * @return bool
	 */
	protected function is_last_known_good_candidate( $restore_ready, array $artifacts, array $activity ) {
		if ( in_array( isset( $activity['outcome_key'] ) ? $activity['outcome_key'] : '', array( 'rollback_success', 'success' ), true ) ) {
			return true;
		}

		return $restore_ready && ! empty( $artifacts['package_present'] ) && empty( $activity['has_failure'] );
	}

	/**
	 * Resolve the status payload for a provided snapshot reference.
	 *
	 * @param array|null $snapshot     Snapshot row.
	 * @param array      $status_index Snapshot status index.
	 * @return array
	 */
	protected function resolve_snapshot_status_reference( $snapshot, array $status_index ) {
		if ( ! is_array( $snapshot ) || empty( $snapshot['id'] ) ) {
			return array();
		}

		$snapshot_id = (int) $snapshot['id'];

		if ( isset( $status_index[ $snapshot_id ] ) && is_array( $status_index[ $snapshot_id ] ) ) {
			return $status_index[ $snapshot_id ];
		}

		$resolved = $this->build_snapshot_status_index( array( $snapshot ) );

		return isset( $resolved[ $snapshot_id ] ) && is_array( $resolved[ $snapshot_id ] ) ? $resolved[ $snapshot_id ] : array();
	}

	/**
	 * Build a lightweight snapshot reference for recommendation payloads.
	 *
	 * @param array $snapshot Snapshot row.
	 * @param array $status   Snapshot status.
	 * @return array
	 */
	protected function build_snapshot_reference( array $snapshot, array $status ) {
		return array(
			'id'                 => isset( $snapshot['id'] ) ? (int) $snapshot['id'] : 0,
			'label'              => isset( $snapshot['label'] ) ? (string) $snapshot['label'] : '',
			'created_at'         => isset( $snapshot['created_at'] ) ? (string) $snapshot['created_at'] : '',
			'trust_label'        => isset( $status['trust']['label'] ) ? (string) $status['trust']['label'] : '',
			'trust_badge'        => isset( $status['trust']['badge'] ) ? (string) $status['trust']['badge'] : 'info',
			'freshness_label'    => isset( $status['freshness']['label'] ) ? (string) $status['freshness']['label'] : '',
			'freshness_key'      => isset( $status['freshness']['key'] ) ? (string) $status['freshness']['key'] : '',
			'last_outcome_label' => $this->format_last_outcome_label( $status ),
			'risk_indicators'    => isset( $status['risk_indicators'] ) && is_array( $status['risk_indicators'] ) ? $status['risk_indicators'] : array(),
			'confidence_message' => isset( $status['confidence_message'] ) ? (string) $status['confidence_message'] : '',
			'status'             => $status,
		);
	}

	/**
	 * Build selected snapshot relationship and warning state.
	 *
	 * @param array|null $selected_snapshot    Selected snapshot row.
	 * @param array      $selected_status      Selected snapshot status.
	 * @param array      $recommended_snapshot Recommended snapshot reference.
	 * @param array      $last_known_good      Last-known-good reference.
	 * @return array
	 */
	protected function build_selected_snapshot_reference( $selected_snapshot, array $selected_status, array $recommended_snapshot, array $last_known_good ) {
		if ( ! is_array( $selected_snapshot ) || empty( $selected_snapshot['id'] ) || empty( $selected_status ) ) {
			return array();
		}

		$reference   = $this->build_snapshot_reference( $selected_snapshot, $selected_status );
		$relation    = 'review';
		$message     = isset( $reference['confidence_message'] ) ? (string) $reference['confidence_message'] : '';
		$snapshot_id = isset( $selected_snapshot['id'] ) ? (int) $selected_snapshot['id'] : 0;

		if ( ! empty( $recommended_snapshot['id'] ) && $snapshot_id === (int) $recommended_snapshot['id'] ) {
			$relation = 'recommended';
			$message  = __( 'System is safe to proceed.', 'zignites-sentinel' );
		} elseif ( ! empty( $last_known_good['id'] ) && $snapshot_id === (int) $last_known_good['id'] ) {
			$relation = 'last_known_good';
			$message  = __( 'Using last known good configuration.', 'zignites-sentinel' );
		} elseif ( ! empty( $recommended_snapshot['id'] ) ) {
			$relation = 'older';
			$message  = __( 'You are reviewing an older or lower-confidence snapshot than Sentinel recommends.', 'zignites-sentinel' );
		} elseif ( ! empty( $selected_status['activity']['has_failure'] ) ) {
			$relation = 'risky';
			$message  = __( 'Recent failure detected - review before continuing.', 'zignites-sentinel' );
		}

		$reference['relation'] = $relation;
		$reference['message']  = $message;

		return $reference;
	}

	/**
	 * Build warnings for recommendation edge cases.
	 *
	 * @param array $snapshots            Snapshot rows.
	 * @param array $recommended_snapshot Recommended snapshot reference.
	 * @param array $selected_snapshot    Selected snapshot reference.
	 * @param array $safe_candidates      Safe candidates.
	 * @param array $validated_candidates Validated candidates.
	 * @return array
	 */
	protected function build_snapshot_intelligence_warnings( array $snapshots, array $recommended_snapshot, array $selected_snapshot, array $safe_candidates, array $validated_candidates ) {
		$warnings = array();

		if ( empty( $snapshots ) ) {
			$warnings[] = __( 'No snapshots are available yet, so Sentinel cannot recommend a trusted restore point.', 'zignites-sentinel' );
		}

		if ( empty( $recommended_snapshot ) && ! empty( $validated_candidates ) ) {
			$warnings[] = __( 'No fully safe snapshot is available. The newest validated snapshot still needs review.', 'zignites-sentinel' );
		}

		if ( empty( $recommended_snapshot ) && empty( $validated_candidates ) && ! empty( $snapshots ) ) {
			$warnings[] = __( 'Current snapshots are missing the validation evidence needed for a trusted recommendation.', 'zignites-sentinel' );
		}

		if ( count( $safe_candidates ) > 1 || count( $validated_candidates ) > 1 ) {
			$warnings[] = __( 'Multiple validated snapshots exist. Sentinel recommends the newest one unless you have a specific rollback target in mind.', 'zignites-sentinel' );
		}

		if ( ! empty( $selected_snapshot['relation'] ) && 'older' === $selected_snapshot['relation'] ) {
			$warnings[] = __( 'The current workspace is not the recommended snapshot.', 'zignites-sentinel' );
		}

		return array_values( array_unique( array_filter( $warnings, 'strlen' ) ) );
	}

	/**
	 * Build dashboard-friendly insight strings.
	 *
	 * @param array $snapshots            Snapshot rows.
	 * @param array $recommended_snapshot Recommended snapshot.
	 * @param array $last_known_good      Last-known-good snapshot.
	 * @param array $selected_snapshot    Selected snapshot reference.
	 * @param array $warnings             Warning strings.
	 * @return array
	 */
	protected function build_contextual_insights( array $snapshots, array $recommended_snapshot, array $last_known_good, array $selected_snapshot, array $warnings ) {
		$insights        = array();
		$latest_snapshot = ! empty( $snapshots[0] ) ? $snapshots[0] : array();

		if ( empty( $latest_snapshot ) ) {
			$insights[] = __( 'You have not taken a snapshot recently.', 'zignites-sentinel' );
		} elseif ( ! empty( $recommended_snapshot ) && ! empty( $recommended_snapshot['status']['activity']['has_failure'] ) ) {
			$insights[] = __( 'Last restore attempt failed - review recovery before continuing.', 'zignites-sentinel' );
		} elseif ( ! empty( $recommended_snapshot ) && 'safe' === ( isset( $recommended_snapshot['status']['trust']['key'] ) ? $recommended_snapshot['status']['trust']['key'] : '' ) ) {
			$insights[] = __( 'System is ready for safe update.', 'zignites-sentinel' );
		} elseif ( empty( $recommended_snapshot ) ) {
			$insights[] = __( 'You have not taken a snapshot recently.', 'zignites-sentinel' );
		}

		if ( ! empty( $last_known_good ) ) {
			$insights[] = __( 'Using last known good configuration.', 'zignites-sentinel' );
		}

		foreach ( $warnings as $warning ) {
			$insights[] = $warning;
		}

		if ( ! empty( $selected_snapshot['message'] ) ) {
			$insights[] = (string) $selected_snapshot['message'];
		}

		return array_slice( array_values( array_unique( array_filter( $insights, 'strlen' ) ) ), 0, 4 );
	}

	/**
	 * Build a snapshot-capture milestone for the operator timeline.
	 *
	 * @param array $snapshot Snapshot row.
	 * @return array
	 */
	protected function build_snapshot_timeline_item( array $snapshot ) {
		return array(
			'type'        => 'snapshot_taken',
			'badge'       => 'info',
			'title'       => __( 'Snapshot taken', 'zignites-sentinel' ),
			'timestamp'   => isset( $snapshot['created_at'] ) ? (string) $snapshot['created_at'] : '',
			'snapshot_id' => isset( $snapshot['id'] ) ? (int) $snapshot['id'] : 0,
			'message'     => sprintf(
				/* translators: 1: snapshot label, 2: snapshot id */
				__( '%1$s (#%2$d) was captured as a restore reference.', 'zignites-sentinel' ),
				isset( $snapshot['label'] ) ? (string) $snapshot['label'] : __( 'Snapshot', 'zignites-sentinel' ),
				isset( $snapshot['id'] ) ? (int) $snapshot['id'] : 0
			),
		);
	}

	/**
	 * Build a restore or rollback milestone for the operator timeline.
	 *
	 * @param array $run      Run summary.
	 * @param array $snapshot Snapshot row.
	 * @return array
	 */
	protected function build_run_timeline_item( array $run, array $snapshot ) {
		$source          = isset( $run['source'] ) ? sanitize_key( (string) $run['source'] ) : '';
		$terminal_status = isset( $run['terminal_status'] ) ? sanitize_key( (string) $run['terminal_status'] ) : '';
		$is_failure      = in_array( $terminal_status, array( 'fail', 'blocked' ), true );
		$is_warning      = in_array( $terminal_status, array( 'partial', 'warning' ), true );
		$is_rollback     = RestoreRollbackManager::JOURNAL_SOURCE === $source;
		$title           = $is_rollback ? __( 'Rollback executed', 'zignites-sentinel' ) : __( 'Restore succeeded', 'zignites-sentinel' );
		$badge           = 'info';

		if ( $is_failure ) {
			$title = $is_rollback ? __( 'Rollback failed', 'zignites-sentinel' ) : __( 'Restore failed', 'zignites-sentinel' );
			$badge = 'critical';
		} elseif ( $is_warning ) {
			$title = $is_rollback ? __( 'Rollback finished with warnings', 'zignites-sentinel' ) : __( 'Restore finished with warnings', 'zignites-sentinel' );
			$badge = 'warning';
		}

		return array(
			'type'        => $is_rollback ? 'rollback' : 'restore',
			'badge'       => $badge,
			'title'       => $title,
			'timestamp'   => isset( $run['latest_timestamp'] ) ? (string) $run['latest_timestamp'] : '',
			'snapshot_id' => isset( $snapshot['id'] ) ? (int) $snapshot['id'] : 0,
			'run_id'      => isset( $run['run_id'] ) ? (string) $run['run_id'] : '',
			'message'     => sprintf(
				/* translators: 1: snapshot label, 2: run id */
				__( '%1$s was referenced by run %2$s.', 'zignites-sentinel' ),
				isset( $snapshot['label'] ) ? (string) $snapshot['label'] : __( 'Snapshot', 'zignites-sentinel' ),
				isset( $run['run_id'] ) ? (string) $run['run_id'] : __( 'unknown', 'zignites-sentinel' )
			),
		);
	}

	/**
	 * Format the last outcome string for a system-health or recommendation row.
	 *
	 * @param array $status Snapshot status.
	 * @return string
	 */
	protected function format_last_outcome_label( array $status ) {
		if ( ! empty( $status['activity']['outcome_label'] ) ) {
			return (string) $status['activity']['outcome_label'];
		}

		return __( 'Unknown', 'zignites-sentinel' );
	}

	/**
	 * Resolve the readiness component used by the system-health card.
	 *
	 * @param array $status Snapshot status.
	 * @return string
	 */
	protected function resolve_system_readiness_key( array $status ) {
		if ( empty( $status ) ) {
			return 'missing';
		}

		if ( ! empty( $status['restore_ready'] ) && ! empty( $status['validated'] ) ) {
			return 'validated';
		}

		if ( 'stale' === ( isset( $status['stage']['key'] ) ? $status['stage']['key'] : '' ) || 'stale' === ( isset( $status['plan']['key'] ) ? $status['plan']['key'] : '' ) ) {
			return 'warning';
		}

		return 'warning';
	}

	/**
	 * Format a readiness component label for the system-health card.
	 *
	 * @param string $readiness_key Readiness key.
	 * @return string
	 */
	protected function format_system_readiness_label( $readiness_key ) {
		if ( 'validated' === $readiness_key ) {
			return __( 'Validated restore-ready snapshot available', 'zignites-sentinel' );
		}

		if ( 'warning' === $readiness_key ) {
			return __( 'Validation evidence exists but needs refresh', 'zignites-sentinel' );
		}

		if ( 'missing' === $readiness_key ) {
			return __( 'No snapshot selected', 'zignites-sentinel' );
		}

		return __( 'Validation incomplete or blocked', 'zignites-sentinel' );
	}

	/**
	 * Resolve the outcome component used by the system-health card.
	 *
	 * @param array $status Snapshot status.
	 * @return string
	 */
	protected function resolve_system_outcome_key( array $status ) {
		if ( empty( $status['activity']['outcome_key'] ) ) {
			return 'neutral';
		}

		if ( in_array( $status['activity']['outcome_key'], array( 'failure' ), true ) ) {
			return 'failure';
		}

		if ( in_array( $status['activity']['outcome_key'], array( 'warning' ), true ) ) {
			return 'warning';
		}

		if ( 'unknown' === $status['activity']['outcome_key'] ) {
			return 'neutral';
		}

		return 'success';
	}

	/**
	 * Remove duplicate badge labels while preserving order.
	 *
	 * @param array $badges Badge rows.
	 * @return array
	 */
	protected function dedupe_badges( array $badges ) {
		$seen   = array();
		$unique = array();

		foreach ( $badges as $badge ) {
			$key = isset( $badge['badge'] ) ? (string) $badge['badge'] : '';
			$key .= '|' . ( isset( $badge['label'] ) ? (string) $badge['label'] : '' );

			if ( isset( $seen[ $key ] ) ) {
				continue;
			}

			$seen[ $key ] = true;
			$unique[]     = $badge;
		}

		return $unique;
	}

	/**
	 * Check whether a snapshot matches a selected status filter.
	 *
	 * @param array  $status        Snapshot status payload.
	 * @param string $status_filter Filter key.
	 * @return bool
	 */
	protected function matches_status_filter( array $status, $status_filter ) {
		if ( '' === $status_filter ) {
			return true;
		}

		switch ( $status_filter ) {
			case 'baseline-present':
				return ! empty( $status['baseline']['present'] );
			case 'rollback-package':
				return ! empty( $status['artifacts']['package_present'] );
			case 'stage-current':
				return ! empty( $status['stage']['key'] ) && 'current' === $status['stage']['key'];
			case 'plan-current':
				return ! empty( $status['plan']['key'] ) && 'current' === $status['plan']['key'];
			case 'recent-restore-activity':
				return ! empty( $status['has_recent_activity'] );
			case 'restore-ready':
				return ! empty( $status['restore_ready'] );
			case 'checkpoint-stale':
				return ! empty( $status['has_stale_checkpoint'] );
			case 'checkpoint-missing':
				return ( ! empty( $status['stage']['key'] ) && 'missing' === $status['stage']['key'] )
					|| ( ! empty( $status['plan']['key'] ) && 'missing' === $status['plan']['key'] );
		}

		return true;
	}

	/**
	 * Determine whether a checkpoint is fresh under the configured age limit.
	 *
	 * @param array $checkpoint Checkpoint data.
	 * @return bool
	 */
	protected function is_checkpoint_fresh( array $checkpoint ) {
		$generated_at = isset( $checkpoint['generated_at'] ) ? (string) $checkpoint['generated_at'] : '';

		if ( '' === $generated_at ) {
			return false;
		}

		$generated_ts = strtotime( $generated_at );

		if ( false === $generated_ts ) {
			return false;
		}

		$settings      = get_option( ZNTS_OPTION_SETTINGS, array() );
		$settings      = is_array( $settings ) ? $settings : array();
		$max_age_hours = isset( $settings['restore_checkpoint_max_age_hours'] ) ? max( 1, (int) $settings['restore_checkpoint_max_age_hours'] ) : 24;

		return $generated_ts >= ( time() - ( $max_age_hours * HOUR_IN_SECONDS ) );
	}

	/**
	 * Build a recommended action line for the dashboard site status card.
	 *
	 * @param string $status         Site status.
	 * @param array  $latest_snapshot Latest snapshot row.
	 * @param array  $latest_status  Latest snapshot status.
	 * @param int    $critical_count Critical conflict count.
	 * @param int    $error_count    Error conflict count.
	 * @param int    $warning_count  Warning conflict count.
	 * @return string
	 */
	protected function build_recommended_action( $status, array $latest_snapshot, array $latest_status, $critical_count, $error_count, $warning_count ) {
		$primary_action = $this->build_primary_action( $status, $latest_snapshot, $latest_status, $critical_count, $error_count, $warning_count );

		if ( ! empty( $primary_action['title'] ) ) {
			return (string) $primary_action['title'];
		}

		return __( 'Review current operator guidance.', 'zignites-sentinel' );
	}

	/**
	 * Build the primary operator action for the dashboard hero.
	 *
	 * @param string $status          Site status.
	 * @param array  $latest_snapshot Latest snapshot row.
	 * @param array  $latest_status   Latest snapshot status.
	 * @param int    $critical_count  Critical conflict count.
	 * @param int    $error_count     Error conflict count.
	 * @param int    $warning_count   Warning conflict count.
	 * @return array
	 */
	protected function build_primary_action( $status, array $latest_snapshot, array $latest_status, $critical_count, $error_count, $warning_count ) {
		if ( empty( $latest_snapshot ) ) {
			return array(
				'title'        => __( 'Take Snapshot Before Update', 'zignites-sentinel' ),
				'description'  => __( 'No recent snapshot is available, so update and restore planning should not continue yet.', 'zignites-sentinel' ),
				'button_label' => __( 'Open Update Readiness', 'zignites-sentinel' ),
				'target'       => 'detail',
			);
		}

		if ( $critical_count > 0 ) {
			return array(
				'title'        => __( 'Review Issues Before Continuing', 'zignites-sentinel' ),
				'description'  => __( 'Critical conflicts are open. Review evidence and recent activity before any restore or update action.', 'zignites-sentinel' ),
				'button_label' => __( 'Open Snapshot Activity', 'zignites-sentinel' ),
				'target'       => 'activity',
			);
		}

		if ( ! empty( $latest_status['activity']['has_failure'] ) ) {
			return array(
				'title'        => __( 'Review Issues Before Continuing', 'zignites-sentinel' ),
				'description'  => __( 'A recent restore or rollback run ended in a warning or failure state. Review recovery context before starting another action.', 'zignites-sentinel' ),
				'button_label' => __( 'Open Snapshot Activity', 'zignites-sentinel' ),
				'target'       => 'activity',
			);
		}

		if ( empty( $latest_status['baseline']['present'] ) ) {
			return array(
				'title'        => __( 'Run Restore Readiness Check', 'zignites-sentinel' ),
				'description'  => __( 'The latest snapshot still needs baseline and restore-readiness evidence before guarded restore review.', 'zignites-sentinel' ),
				'button_label' => __( 'Open Update Readiness', 'zignites-sentinel' ),
				'target'       => 'detail',
			);
		}

		if ( empty( $latest_status['artifacts']['package_present'] ) ) {
			return array(
				'title'        => __( 'Take Snapshot Before Update', 'zignites-sentinel' ),
				'description'  => __( 'The latest snapshot does not include a rollback package, so a fresh protected snapshot should be taken first.', 'zignites-sentinel' ),
				'button_label' => __( 'Open Update Readiness', 'zignites-sentinel' ),
				'target'       => 'detail',
			);
		}

		if ( empty( $latest_status['stage']['key'] ) || 'current' !== $latest_status['stage']['key'] ) {
			return array(
				'title'        => __( 'Run Restore Readiness Check', 'zignites-sentinel' ),
				'description'  => __( 'Staged validation is not current for the latest snapshot. Refresh restore evidence before considering live actions.', 'zignites-sentinel' ),
				'button_label' => __( 'Open Update Readiness', 'zignites-sentinel' ),
				'target'       => 'detail',
			);
		}

		if ( empty( $latest_status['plan']['key'] ) || 'current' !== $latest_status['plan']['key'] ) {
			return array(
				'title'        => __( 'Run Restore Readiness Check', 'zignites-sentinel' ),
				'description'  => __( 'The stored restore plan is stale or missing. Refresh the guarded restore evidence chain first.', 'zignites-sentinel' ),
				'button_label' => __( 'Open Update Readiness', 'zignites-sentinel' ),
				'target'       => 'detail',
			);
		}

		if ( $error_count > 0 || $warning_count > 0 || 'needs_attention' === $status ) {
			return array(
				'title'        => __( 'Review Issues Before Continuing', 'zignites-sentinel' ),
				'description'  => __( 'Warnings or errors are still open. Review them before treating the snapshot as confidently ready.', 'zignites-sentinel' ),
				'button_label' => __( 'Open Snapshot Activity', 'zignites-sentinel' ),
				'target'       => 'activity',
			);
		}

		return array(
			'title'        => __( 'Safe to Proceed with Restore Plan', 'zignites-sentinel' ),
			'description'  => __( 'The latest snapshot has current restore evidence. Review impact on Update Readiness before any guarded restore decision.', 'zignites-sentinel' ),
			'button_label' => __( 'Open Update Readiness', 'zignites-sentinel' ),
			'target'       => 'detail',
		);
	}

	/**
	 * Map a site status key to a human label.
	 *
	 * @param string $status Site status key.
	 * @return string
	 */
	protected function map_site_status_label( $status ) {
		if ( 'at_risk' === $status ) {
			return __( 'At Risk', 'zignites-sentinel' );
		}

		if ( 'needs_attention' === $status ) {
			return __( 'Needs Attention', 'zignites-sentinel' );
		}

		return __( 'Stable', 'zignites-sentinel' );
	}

	/**
	 * Map a site status key to an admin badge style.
	 *
	 * @param string $status Site status key.
	 * @return string
	 */
	protected function map_site_status_badge( $status ) {
		if ( 'at_risk' === $status ) {
			return 'critical';
		}

		if ( 'needs_attention' === $status ) {
			return 'warning';
		}

		return 'info';
	}

	/**
	 * Build a consistent badge payload.
	 *
	 * @param string $badge Badge class.
	 * @param string $label Badge label.
	 * @return array
	 */
	protected function build_badge( $badge, $label ) {
		return array(
			'badge' => sanitize_html_class( (string) $badge ),
			'label' => (string) $label,
		);
	}
}
