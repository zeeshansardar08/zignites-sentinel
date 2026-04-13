<?php
/**
 * Read-only presentation helper for the Event Logs admin screen.
 *
 * @package ZignitesSentinel
 */

namespace Zignites\Sentinel\Admin;

defined( 'ABSPATH' ) || exit;

class EventLogPresenter {

	/**
	 * Shared status presenter.
	 *
	 * @var StatusPresenter
	 */
	protected $status_presenter;

	/**
	 * Constructor.
	 *
	 * @param StatusPresenter|null $status_presenter Optional status presenter.
	 */
	public function __construct( StatusPresenter $status_presenter = null ) {
		$this->status_presenter = $status_presenter ? $status_presenter : new StatusPresenter();
	}

	/**
	 * Build prepared UI payload for the Event Logs screen.
	 *
	 * @param array $recent_logs        Current paginated log rows.
	 * @param array $log_filters        Active log filters.
	 * @param array $run_summaries      Run summaries.
	 * @param array $operational_events Operational events.
	 * @param array $run_journal        Run journal payload.
	 * @param array $pagination         Pagination payload.
	 * @return array
	 */
	public function build_view_payload( array $recent_logs, array $log_filters, array $run_summaries, array $operational_events, array $run_journal, array $pagination ) {
		$active_filter_count = $this->count_active_filters( $log_filters );
		$total_logs          = isset( $pagination['total_logs'] ) ? (int) $pagination['total_logs'] : 0;

		return array(
			'base_args'           => $this->build_base_args( $log_filters ),
			'active_filter_count' => $active_filter_count,
			'severity_counts'     => $this->count_severity_rows( $recent_logs ),
			'recent_logs'         => $this->decorate_log_rows( $recent_logs ),
			'operational_events'  => $this->decorate_log_rows( $operational_events ),
			'run_summaries'       => $this->decorate_run_summaries( $run_summaries ),
			'run_journal'         => $this->decorate_run_journal( $run_journal ),
			'run_outcome_summary' => $this->build_run_outcome_summary( $run_journal ),
			'guidance_panels'     => $this->build_guidance_panels( $active_filter_count, $total_logs ),
			'empty_state'         => $this->build_empty_state( $active_filter_count, $total_logs ),
			'history_empty_state' => $this->build_history_empty_state( $operational_events, $run_summaries, $run_journal ),
			'positioning_note'    => $this->build_positioning_note(),
			'summary_tiles'       => array(
				array(
					'label' => __( 'Matching Events', 'zignites-sentinel' ),
					'value' => (string) $total_logs,
				),
				array(
					'label' => __( 'Active Filters', 'zignites-sentinel' ),
					'value' => (string) $active_filter_count,
				),
				array(
					'label' => __( 'Run Summaries', 'zignites-sentinel' ),
					'value' => (string) count( $run_summaries ),
				),
				array(
					'label' => __( 'Operational Events', 'zignites-sentinel' ),
					'value' => (string) count( $operational_events ),
				),
			),
		);
	}

	/**
	 * Build concise Event Logs guidance panels.
	 *
	 * @param int $active_filter_count Active filter count.
	 * @param int $total_logs          Total matching logs.
	 * @return array
	 */
	protected function build_guidance_panels( $active_filter_count, $total_logs ) {
		return array(
			array(
				'title' => __( 'How to use this screen', 'zignites-sentinel' ),
				'body'  => $total_logs > 0
					? __( 'Start with the filter bar, review the top matching events, then open a run journal only when you need the full execution trail.', 'zignites-sentinel' )
					: __( 'This screen becomes your investigation trail once Sentinel starts recording restore, rollback, and readiness activity.', 'zignites-sentinel' ),
			),
			array(
				'title' => __( 'What this status means', 'zignites-sentinel' ),
				'body'  => __( 'Event Logs stores structured evidence around readiness checks, restores, rollbacks, and supporting maintenance actions so operators can review what happened with context.', 'zignites-sentinel' ),
			),
			array(
				'title' => __( 'What to do next', 'zignites-sentinel' ),
				'body'  => $active_filter_count > 0
					? __( 'Keep the current filters if they isolate the issue, or reset them to return to the full event stream.', 'zignites-sentinel' )
					: __( 'Use Update Readiness to create a snapshot and run the first checks. Event history will become more useful as Sentinel records activity.', 'zignites-sentinel' ),
			),
		);
	}

	/**
	 * Build the primary Event Explorer empty state.
	 *
	 * @param int $active_filter_count Active filter count.
	 * @param int $total_logs          Total matching logs.
	 * @return array
	 */
	protected function build_empty_state( $active_filter_count, $total_logs ) {
		if ( $active_filter_count > 0 ) {
			return array(
				'title'       => __( 'No events match the current filters.', 'zignites-sentinel' ),
				'description' => __( 'The filters are working, but nothing in the recorded history matches this exact view yet.', 'zignites-sentinel' ),
				'next_step'   => __( 'Reset or broaden the filters to bring more of the event stream back into view.', 'zignites-sentinel' ),
			);
		}

		if ( $total_logs < 1 ) {
			return array(
				'title'       => __( 'No event history has been recorded yet.', 'zignites-sentinel' ),
				'description' => __( 'This screen will show the operator trail for readiness checks, restores, rollbacks, and supporting maintenance events once Sentinel has activity to record.', 'zignites-sentinel' ),
				'next_step'   => __( 'Create a snapshot and run the first readiness checks to start building an investigation history.', 'zignites-sentinel' ),
			);
		}

		return array(
			'title'       => __( 'No events are visible right now.', 'zignites-sentinel' ),
			'description' => __( 'The event stream is available, but nothing is showing in the current result set.', 'zignites-sentinel' ),
			'next_step'   => __( 'Review the filters or page selection to return to the main event stream.', 'zignites-sentinel' ),
		);
	}

	/**
	 * Build an empty state for timeline/history sections.
	 *
	 * @param array $operational_events Operational events.
	 * @param array $run_summaries      Run summaries.
	 * @param array $run_journal        Run journal payload.
	 * @return array
	 */
	protected function build_history_empty_state( array $operational_events, array $run_summaries, array $run_journal ) {
		$has_journal = ! empty( $run_journal['entries'] ) && is_array( $run_journal['entries'] );

		if ( ! empty( $operational_events ) || ! empty( $run_summaries ) || $has_journal ) {
			return array();
		}

		return array(
			'title'       => __( 'No restore or rollback history yet.', 'zignites-sentinel' ),
			'description' => __( 'Run summaries and journals appear here after Sentinel records a restore or rollback attempt, so you can review the story without digging through raw entries first.', 'zignites-sentinel' ),
			'next_step'   => __( 'Use snapshots and readiness checks first. This history layer will fill in as the controlled restore workflow is used.', 'zignites-sentinel' ),
		);
	}

	/**
	 * Build a concise Event Logs positioning note.
	 *
	 * @return array
	 */
	protected function build_positioning_note() {
		return array(
			'title' => __( 'What this history is for', 'zignites-sentinel' ),
			'body'  => __( 'Sentinel records controlled restore evidence so operators can review readiness and recovery decisions with confidence. The journal is for traceability, not a claim of atomic restore execution.', 'zignites-sentinel' ),
		);
	}

	/**
	 * Build stable base query args for Event Logs links.
	 *
	 * @param array $log_filters Active log filters.
	 * @return array
	 */
	public function build_base_args( array $log_filters ) {
		return array(
			'page'        => 'zignites-sentinel-event-logs',
			'severity'    => isset( $log_filters['severity'] ) ? (string) $log_filters['severity'] : '',
			'source'      => isset( $log_filters['source'] ) ? (string) $log_filters['source'] : '',
			'run_id'      => isset( $log_filters['run_id'] ) ? (string) $log_filters['run_id'] : '',
			'snapshot_id' => isset( $log_filters['snapshot_id'] ) ? (int) $log_filters['snapshot_id'] : 0,
			'log_search'  => isset( $log_filters['search'] ) ? (string) $log_filters['search'] : '',
		);
	}

	/**
	 * Count active filter values.
	 *
	 * @param array $log_filters Active log filters.
	 * @return int
	 */
	public function count_active_filters( array $log_filters ) {
		$count = 0;

		foreach ( array( 'severity', 'source', 'run_id', 'snapshot_id', 'search' ) as $filter_key ) {
			if ( ! empty( $log_filters[ $filter_key ] ) ) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Count severities in the current paginated log rows.
	 *
	 * @param array $recent_logs Current log rows.
	 * @return array
	 */
	public function count_severity_rows( array $recent_logs ) {
		$counts = array(
			'info'     => 0,
			'warning'  => 0,
			'error'    => 0,
			'critical' => 0,
		);

		foreach ( $recent_logs as $log_row ) {
			$severity = isset( $log_row['severity'] ) ? sanitize_key( (string) $log_row['severity'] ) : 'info';

			if ( isset( $counts[ $severity ] ) ) {
				++$counts[ $severity ];
			}
		}

		return $counts;
	}

	/**
	 * Decorate run summaries with stable badge metadata.
	 *
	 * @param array $run_summaries Run summaries.
	 * @return array
	 */
	public function decorate_run_summaries( array $run_summaries ) {
		$rows = array();

		foreach ( $run_summaries as $summary ) {
			$status = isset( $summary['status_badge'] ) ? (string) $summary['status_badge'] : ( isset( $summary['status'] ) ? (string) $summary['status'] : '' );
			$presented              = $this->status_presenter->present_run( $status );
			$summary['status_pill']  = isset( $presented['pill'] ) ? (string) $presented['pill'] : 'info';
			$summary['status_label'] = isset( $presented['label'] ) ? (string) $presented['label'] : '';
			$rows[]                  = $summary;
		}

		return $rows;
	}

	/**
	 * Decorate run journal entries with stable badge metadata.
	 *
	 * @param array $run_journal Run journal payload.
	 * @return array
	 */
	public function decorate_run_journal( array $run_journal ) {
		if ( empty( $run_journal['entries'] ) || ! is_array( $run_journal['entries'] ) ) {
			return $run_journal;
		}

		$entries = array();

		foreach ( $run_journal['entries'] as $entry ) {
			$status = isset( $entry['status'] ) ? (string) $entry['status'] : '';
			$presented             = $this->status_presenter->present_run( $status );
			$entry['status_pill']  = isset( $presented['pill'] ) ? (string) $presented['pill'] : 'info';
			$entry['status_label'] = isset( $presented['label'] ) ? (string) $presented['label'] : '';
			$entries[]             = $entry;
		}

		$run_journal['entries'] = $entries;

		return $run_journal;
	}

	/**
	 * Build a compact operator-facing summary for the selected run journal.
	 *
	 * @param array $run_journal Run journal payload.
	 * @return array
	 */
	protected function build_run_outcome_summary( array $run_journal ) {
		$entries = isset( $run_journal['entries'] ) && is_array( $run_journal['entries'] ) ? $run_journal['entries'] : array();

		if ( empty( $entries ) ) {
			return array();
		}

		$pass_count    = 0;
		$warning_count = 0;
		$fail_count    = 0;
		$action_labels = array();
		$first_ts      = false;
		$last_ts       = false;

		foreach ( $entries as $entry ) {
			$status = isset( $entry['status'] ) ? sanitize_key( (string) $entry['status'] ) : '';

			if ( in_array( $status, array( 'fail', 'blocked' ), true ) ) {
				++$fail_count;
			} elseif ( in_array( $status, array( 'warning', 'partial' ), true ) ) {
				++$warning_count;
			} else {
				++$pass_count;
			}

			$timestamp = isset( $entry['timestamp'] ) ? strtotime( (string) $entry['timestamp'] ) : false;

			if ( false !== $timestamp ) {
				$first_ts = false === $first_ts ? $timestamp : min( $first_ts, $timestamp );
				$last_ts  = false === $last_ts ? $timestamp : max( $last_ts, $timestamp );
			}

			$scope = isset( $entry['scope'] ) ? trim( (string) $entry['scope'] ) : '';
			$phase = isset( $entry['phase'] ) ? trim( (string) $entry['phase'] ) : '';
			$label = trim( ucfirst( $scope ) . ' ' . str_replace( '_', ' ', $phase ) );

			if ( '' !== $label && ! in_array( $label, $action_labels, true ) ) {
				$action_labels[] = $label;
			}
		}

		$outcome = 'completed';

		if ( $fail_count > 0 ) {
			$outcome = 'failed';
		} elseif ( $warning_count > 0 ) {
			$outcome = 'warning';
		}

		return array(
			'badge'        => 'failed' === $outcome ? 'critical' : ( 'warning' === $outcome ? 'warning' : 'info' ),
			'status_label' => 'failed' === $outcome ? __( 'Failed', 'zignites-sentinel' ) : ( 'warning' === $outcome ? __( 'Warning', 'zignites-sentinel' ) : __( 'Success', 'zignites-sentinel' ) ),
			'title'        => __( 'Run Outcome Summary', 'zignites-sentinel' ),
			'message'      => 'failed' === $outcome
				? __( 'This run ended with failed work. Review the timeline and recovery context below before repeating the action.', 'zignites-sentinel' )
				: ( 'warning' === $outcome
					? __( 'This run completed with warnings or partial work. Review the affected phases before continuing.', 'zignites-sentinel' )
					: __( 'This run completed without recorded failures in the persisted journal.', 'zignites-sentinel' ) ),
			'duration'     => $this->format_duration( $first_ts, $last_ts ),
			'rows'         => array(
				array(
					'label' => __( 'Started', 'zignites-sentinel' ),
					'value' => false !== $first_ts ? gmdate( 'Y-m-d H:i:s', $first_ts ) : __( 'Unknown', 'zignites-sentinel' ),
				),
				array(
					'label' => __( 'Finished', 'zignites-sentinel' ),
					'value' => false !== $last_ts ? gmdate( 'Y-m-d H:i:s', $last_ts ) : __( 'Unknown', 'zignites-sentinel' ),
				),
				array(
					'label' => __( 'Duration', 'zignites-sentinel' ),
					'value' => $this->format_duration( $first_ts, $last_ts ),
				),
				array(
					'label' => __( 'Key actions performed', 'zignites-sentinel' ),
					'value' => ! empty( $action_labels ) ? implode( ', ', array_slice( $action_labels, 0, 4 ) ) : __( 'No labeled phases were captured.', 'zignites-sentinel' ),
				),
			),
			'story'        => sprintf(
				/* translators: 1: pass count, 2: warning count, 3: fail count, 4: entry count */
				__( 'Execution story: %1$d pass, %2$d warning, %3$d fail across %4$d journal entries.', 'zignites-sentinel' ),
				$pass_count,
				$warning_count,
				$fail_count,
				count( $entries )
			),
		);
	}

	/**
	 * Format a duration between two timestamps.
	 *
	 * @param int|false $first_ts First timestamp.
	 * @param int|false $last_ts  Last timestamp.
	 * @return string
	 */
	protected function format_duration( $first_ts, $last_ts ) {
		if ( false === $first_ts || false === $last_ts || $last_ts < $first_ts ) {
			return __( 'Unknown', 'zignites-sentinel' );
		}

		$seconds = max( 0, (int) $last_ts - (int) $first_ts );
		$hours   = (int) floor( $seconds / HOUR_IN_SECONDS );
		$minutes = (int) floor( ( $seconds % HOUR_IN_SECONDS ) / MINUTE_IN_SECONDS );
		$remain  = $seconds % MINUTE_IN_SECONDS;

		if ( $hours > 0 ) {
			return sprintf(
				/* translators: 1: hour count, 2: minute count */
				__( '%1$dh %2$dm', 'zignites-sentinel' ),
				$hours,
				$minutes
			);
		}

		if ( $minutes > 0 ) {
			return sprintf(
				/* translators: 1: minute count, 2: second count */
				__( '%1$dm %2$ds', 'zignites-sentinel' ),
				$minutes,
				$remain
			);
		}

		return sprintf(
			/* translators: %d: second count */
			__( '%ds', 'zignites-sentinel' ),
			$remain
		);
	}

	/**
	 * Map run/journal status values to a pill variant.
	 *
	 * @param string $status Status value.
	 * @return string
	 */
	public function decorate_log_rows( array $rows ) {
		$decorated = array();

		foreach ( $rows as $row ) {
			$presented             = $this->status_presenter->present_severity( isset( $row['severity'] ) ? $row['severity'] : '' );
			$row['severity_pill']  = isset( $presented['pill'] ) ? (string) $presented['pill'] : 'info';
			$row['severity_label'] = isset( $presented['label'] ) ? (string) $presented['label'] : '';
			$decorated[]           = $row;
		}

		return $decorated;
	}

	/**
	 * Build a CSV row for an event log export.
	 *
	 * @param array $row     Log row.
	 * @param array $context Decoded log context.
	 * @return array
	 */
	public function build_export_row( array $row, array $context = array() ) {
		$journal_entry = isset( $context['entry'] ) && is_array( $context['entry'] ) ? $context['entry'] : array();

		return array(
			isset( $row['id'] ) ? (int) $row['id'] : 0,
			isset( $row['created_at'] ) ? (string) $row['created_at'] : '',
			isset( $row['severity'] ) ? (string) $row['severity'] : '',
			isset( $row['source'] ) ? (string) $row['source'] : '',
			isset( $row['event_type'] ) ? (string) $row['event_type'] : '',
			isset( $row['message'] ) ? (string) $row['message'] : '',
			isset( $context['snapshot_id'] ) ? absint( $context['snapshot_id'] ) : 0,
			isset( $context['run_id'] ) ? (string) $context['run_id'] : '',
			isset( $journal_entry['scope'] ) ? (string) $journal_entry['scope'] : '',
			isset( $journal_entry['phase'] ) ? (string) $journal_entry['phase'] : '',
			isset( $journal_entry['status'] ) ? (string) $journal_entry['status'] : '',
			! empty( $context ) ? wp_json_encode( $context ) : '',
		);
	}

	/**
	 * Build a snapshot activity row for the update readiness screen.
	 *
	 * @param array  $row          Log row.
	 * @param array  $context      Decoded log context.
	 * @param int    $snapshot_id  Snapshot ID.
	 * @param string $logs_page    Event Logs page slug.
	 * @param string $journal_url  Optional run journal URL.
	 * @return array
	 */
	public function build_snapshot_activity_entry( array $row, array $context, $snapshot_id, $logs_page, $journal_url = '' ) {
		$run_id = isset( $context['run_id'] ) ? sanitize_text_field( (string) $context['run_id'] ) : '';
		$source = isset( $row['source'] ) ? sanitize_text_field( (string) $row['source'] ) : '';
		$detail = add_query_arg(
			array(
				'page'        => (string) $logs_page,
				'snapshot_id' => absint( $snapshot_id ),
				'log_id'      => isset( $row['id'] ) ? (int) $row['id'] : 0,
			),
			admin_url( 'admin.php' )
		);

		return array(
			'created_at'    => isset( $row['created_at'] ) ? (string) $row['created_at'] : '',
			'severity'      => isset( $row['severity'] ) ? (string) $row['severity'] : 'info',
			'source'        => $source,
			'event_type'    => isset( $row['event_type'] ) ? (string) $row['event_type'] : '',
			'message'       => isset( $row['message'] ) ? (string) $row['message'] : '',
			'run_id'        => $run_id,
			'detail_url'    => $detail,
			'journal_url'   => (string) $journal_url,
			'journal_label' => '' !== $run_id ? sprintf( __( 'Run %s', 'zignites-sentinel' ), $run_id ) : '',
		);
	}
}
