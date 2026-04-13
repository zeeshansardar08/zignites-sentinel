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
			$activity = $this->resolve_activity_state(
				isset( $run_summaries[ $snapshot_id ] ) ? $run_summaries[ $snapshot_id ] : array()
			);

			$restore_ready = $baseline['present'] && 'current' === $stage['key'] && 'current' === $plan['key'];
			$badges        = array(
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
				'baseline'               => $baseline,
				'artifacts'              => $artifacts,
				'stage'                  => $stage,
				'plan'                   => $plan,
				'activity'               => $activity,
				'restore_ready'          => $restore_ready,
				'has_recent_activity'    => ! empty( $activity['is_recent'] ),
				'has_stale_checkpoint'   => 'stale' === $stage['key'] || 'stale' === $plan['key'],
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

		$signals = array();
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
			'latest_snapshot'    => $latest_snapshot,
			'latest_status'      => $latest_status,
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
				'is_recent'   => false,
				'has_failure' => false,
				'badge'       => 'info',
				'label'       => __( 'No recent restore', 'zignites-sentinel' ),
				'message'     => __( 'No recent restore or rollback activity was recorded for this snapshot.', 'zignites-sentinel' ),
				'show_badge'  => false,
			);
		}

		$latest_timestamp = isset( $summary['latest_timestamp'] ) ? (string) $summary['latest_timestamp'] : '';
		$latest_ts        = '' !== $latest_timestamp ? strtotime( $latest_timestamp ) : false;
		$is_recent        = false !== $latest_ts && $latest_ts >= ( time() - ( 14 * DAY_IN_SECONDS ) );
		$terminal_status  = isset( $summary['terminal_status'] ) ? sanitize_key( (string) $summary['terminal_status'] ) : '';
		$has_failure      = in_array( $terminal_status, array( 'fail', 'blocked', 'partial' ), true );
		$label            = $is_recent ? __( 'Recent restore', 'zignites-sentinel' ) : __( 'Past restore', 'zignites-sentinel' );
		$message          = $is_recent
			? __( 'Recent restore or rollback activity was recorded for this snapshot.', 'zignites-sentinel' )
			: __( 'Restore or rollback activity exists for this snapshot, but it is not recent.', 'zignites-sentinel' );

		if ( $has_failure ) {
			$label   = __( 'Recent failure', 'zignites-sentinel' );
			$message = __( 'A recent restore or rollback run for this snapshot finished with a failure or partial state.', 'zignites-sentinel' );
		}

		return array(
			'is_recent'   => $is_recent,
			'has_failure' => $has_failure,
			'badge'       => $has_failure ? 'warning' : 'info',
			'label'       => $label,
			'message'     => $message,
			'show_badge'  => true,
		);
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
