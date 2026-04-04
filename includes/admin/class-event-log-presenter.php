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
		return array(
			'base_args'           => $this->build_base_args( $log_filters ),
			'active_filter_count' => $this->count_active_filters( $log_filters ),
			'severity_counts'     => $this->count_severity_rows( $recent_logs ),
			'run_summaries'       => $this->decorate_run_summaries( $run_summaries ),
			'run_journal'         => $this->decorate_run_journal( $run_journal ),
			'summary_tiles'       => array(
				array(
					'label' => __( 'Matching Events', 'zignites-sentinel' ),
					'value' => isset( $pagination['total_logs'] ) ? (string) $pagination['total_logs'] : '0',
				),
				array(
					'label' => __( 'Active Filters', 'zignites-sentinel' ),
					'value' => (string) $this->count_active_filters( $log_filters ),
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

			$summary['status_pill']  = $this->map_status_pill( $status );
			$summary['status_label'] = $this->format_status_label( $status );
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

			$entry['status_pill']  = $this->map_status_pill( $status );
			$entry['status_label'] = $this->format_status_label( $status );
			$entries[]             = $entry;
		}

		$run_journal['entries'] = $entries;

		return $run_journal;
	}

	/**
	 * Map run/journal status values to a pill variant.
	 *
	 * @param string $status Status value.
	 * @return string
	 */
	public function map_status_pill( $status ) {
		$status = sanitize_key( (string) $status );

		if ( in_array( $status, array( 'fail', 'blocked', 'critical', 'error' ), true ) ) {
			return 'critical';
		}

		if ( in_array( $status, array( 'partial', 'warning', 'caution' ), true ) ) {
			return 'warning';
		}

		return 'info';
	}

	/**
	 * Format a readable status label.
	 *
	 * @param string $status Status value.
	 * @return string
	 */
	public function format_status_label( $status ) {
		$status = (string) $status;

		if ( '' === $status ) {
			return '';
		}

		return ucfirst( str_replace( '_', ' ', $status ) );
	}
}
