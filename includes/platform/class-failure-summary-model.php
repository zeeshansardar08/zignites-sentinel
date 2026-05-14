<?php
/**
 * Deterministic failure summary model for future AI-assisted workflows.
 *
 * @package ZignitesSentinel
 */

namespace Zignites\Sentinel\Platform;

defined( 'ABSPATH' ) || exit;

class FailureSummaryModel {

	/**
	 * Build a deterministic summary from logs, health checks, and journal entries.
	 *
	 * @param array $logs            Recent log rows.
	 * @param array $health_checks   Health check rows.
	 * @param array $journal_entries Restore journal entries.
	 * @param array $context         Optional summary context.
	 * @return array
	 */
	public function build_summary( array $logs, array $health_checks, array $journal_entries, array $context = array() ) {
		$log_summary     = $this->summarize_logs( $logs );
		$health_summary  = $this->summarize_health_checks( $health_checks );
		$journal_summary = $this->summarize_journal_entries( $journal_entries );
		$severity        = $this->resolve_severity( $log_summary, $health_summary, $journal_summary );

		return array(
			'schema_version'     => '1.0',
			'generated_at'       => current_time( 'mysql', true ),
			'summary_type'       => 'deterministic_failure_summary',
			'severity'           => $severity,
			'title'              => $this->build_title( $severity, $context ),
			'overview'           => $this->build_overview( $severity, $log_summary, $health_summary, $journal_summary ),
			'logs'               => $log_summary,
			'health'             => $health_summary,
			'journal'            => $journal_summary,
			'findings'           => $this->build_findings( $log_summary, $health_summary, $journal_summary ),
			'next_steps'         => $this->build_next_steps( $severity, $log_summary, $health_summary, $journal_summary ),
			'ai_assistive_only'  => true,
			'deterministic_only' => true,
		);
	}

	/**
	 * Render a readable deterministic summary.
	 *
	 * @param array $summary Summary payload.
	 * @return string
	 */
	public function render_text( array $summary ) {
		$lines = array(
			isset( $summary['title'] ) ? (string) $summary['title'] : __( 'Failure Summary', 'zignites-sentinel' ),
			'Generated: ' . ( isset( $summary['generated_at'] ) ? (string) $summary['generated_at'] : '' ),
			'Severity: ' . ( isset( $summary['severity'] ) ? $this->humanize( $summary['severity'] ) : 'Unknown' ),
			'Overview: ' . ( isset( $summary['overview'] ) ? (string) $summary['overview'] : '' ),
			'',
			'Findings',
		);

		$findings = isset( $summary['findings'] ) && is_array( $summary['findings'] ) ? $summary['findings'] : array();
		if ( empty( $findings ) ) {
			$lines[] = '- No deterministic failure findings were detected.';
		} else {
			foreach ( $findings as $finding ) {
				$lines[] = '- ' . ( isset( $finding['message'] ) ? (string) $finding['message'] : '' );
			}
		}

		$lines[] = '';
		$lines[] = 'Next Steps';
		$next_steps = isset( $summary['next_steps'] ) && is_array( $summary['next_steps'] ) ? $summary['next_steps'] : array();
		foreach ( $next_steps as $step ) {
			$lines[] = '- ' . (string) $step;
		}

		$lines[] = '';
		$lines[] = 'AI assistance is optional. Rollback safety decisions must use the deterministic Sentinel checks above.';

		return implode( "\n", $lines ) . "\n";
	}

	/**
	 * Summarize recent logs.
	 *
	 * @param array $logs Recent log rows.
	 * @return array
	 */
	protected function summarize_logs( array $logs ) {
		$summary = array(
			'total'        => 0,
			'info'         => 0,
			'warning'      => 0,
			'error'        => 0,
			'critical'     => 0,
			'latest_error' => '',
			'latest_at'    => '',
		);

		foreach ( $logs as $log ) {
			if ( ! is_array( $log ) ) {
				continue;
			}

			++$summary['total'];
			$severity = isset( $log['severity'] ) ? sanitize_key( (string) $log['severity'] ) : 'info';
			if ( ! isset( $summary[ $severity ] ) ) {
				$severity = 'info';
			}
			++$summary[ $severity ];

			if ( in_array( $severity, array( 'critical', 'error' ), true ) && '' === $summary['latest_error'] ) {
				$summary['latest_error'] = isset( $log['message'] ) ? sanitize_text_field( (string) $log['message'] ) : '';
				$summary['latest_at']    = isset( $log['created_at'] ) ? sanitize_text_field( (string) $log['created_at'] ) : '';
			}
		}

		return $summary;
	}

	/**
	 * Summarize health checks.
	 *
	 * @param array $health_checks Health check rows.
	 * @return array
	 */
	protected function summarize_health_checks( array $health_checks ) {
		$summary = array(
			'total'          => 0,
			'pass'           => 0,
			'warning'        => 0,
			'fail'           => 0,
			'blocked'        => 0,
			'failed_checks'  => array(),
			'warning_checks' => array(),
		);

		foreach ( $health_checks as $check ) {
			if ( ! is_array( $check ) ) {
				continue;
			}

			++$summary['total'];
			$status = isset( $check['status'] ) ? sanitize_key( (string) $check['status'] ) : 'pass';
			if ( ! isset( $summary[ $status ] ) ) {
				$status = 'warning';
			}
			++$summary[ $status ];

			if ( in_array( $status, array( 'fail', 'blocked' ), true ) ) {
				$summary['failed_checks'][] = $this->label_for_row( $check );
			} elseif ( 'warning' === $status ) {
				$summary['warning_checks'][] = $this->label_for_row( $check );
			}
		}

		return $summary;
	}

	/**
	 * Summarize restore journal entries.
	 *
	 * @param array $journal_entries Restore journal entries.
	 * @return array
	 */
	protected function summarize_journal_entries( array $journal_entries ) {
		$summary = array(
			'total'           => 0,
			'pass'            => 0,
			'warning'         => 0,
			'fail'            => 0,
			'blocked'         => 0,
			'partial'         => 0,
			'terminal_status' => '',
			'last_message'    => '',
			'failed_entries'  => array(),
		);

		foreach ( $journal_entries as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			++$summary['total'];
			$status = isset( $entry['status'] ) ? sanitize_key( (string) $entry['status'] ) : 'pass';
			if ( ! isset( $summary[ $status ] ) ) {
				$status = 'warning';
			}
			++$summary[ $status ];

			if ( ! empty( $entry['scope'] ) && 'run' === $entry['scope'] && ! empty( $entry['phase'] ) && 'completed' === $entry['phase'] ) {
				$summary['terminal_status'] = $status;
			}

			if ( isset( $entry['message'] ) ) {
				$summary['last_message'] = sanitize_text_field( (string) $entry['message'] );
			}

			if ( in_array( $status, array( 'fail', 'blocked', 'partial' ), true ) ) {
				$summary['failed_entries'][] = $this->label_for_row( $entry );
			}
		}

		return $summary;
	}

	/**
	 * Resolve overall severity.
	 *
	 * @param array $logs    Log summary.
	 * @param array $health  Health summary.
	 * @param array $journal Journal summary.
	 * @return string
	 */
	protected function resolve_severity( array $logs, array $health, array $journal ) {
		if ( ! empty( $logs['critical'] ) || ! empty( $health['blocked'] ) || 'blocked' === $journal['terminal_status'] ) {
			return 'critical';
		}

		if ( ! empty( $logs['error'] ) || ! empty( $health['fail'] ) || ! empty( $journal['fail'] ) || 'fail' === $journal['terminal_status'] || 'partial' === $journal['terminal_status'] ) {
			return 'high';
		}

		if ( ! empty( $logs['warning'] ) || ! empty( $health['warning'] ) || ! empty( $journal['warning'] ) || ! empty( $journal['partial'] ) ) {
			return 'medium';
		}

		return 'low';
	}

	/**
	 * Build summary title.
	 *
	 * @param string $severity Summary severity.
	 * @param array  $context  Summary context.
	 * @return string
	 */
	protected function build_title( $severity, array $context ) {
		$label = isset( $context['label'] ) && '' !== trim( (string) $context['label'] ) ? sanitize_text_field( (string) $context['label'] ) : __( 'Failure Summary', 'zignites-sentinel' );

		return $label . ': ' . $this->humanize( $severity );
	}

	/**
	 * Build one-line overview.
	 *
	 * @param string $severity Severity.
	 * @param array  $logs Log summary.
	 * @param array  $health Health summary.
	 * @param array  $journal Journal summary.
	 * @return string
	 */
	protected function build_overview( $severity, array $logs, array $health, array $journal ) {
		if ( 'low' === $severity ) {
			return __( 'No deterministic failure signal was detected in the supplied logs, health checks, or restore journal.', 'zignites-sentinel' );
		}

		return sprintf(
			/* translators: 1: error count, 2: failed health count, 3: failed journal count */
			__( 'Detected %1$d error or critical logs, %2$d failed or blocked health checks, and %3$d failed, blocked, or partial journal entries.', 'zignites-sentinel' ),
			(int) $logs['error'] + (int) $logs['critical'],
			(int) $health['fail'] + (int) $health['blocked'],
			(int) $journal['fail'] + (int) $journal['blocked'] + (int) $journal['partial']
		);
	}

	/**
	 * Build deterministic findings.
	 *
	 * @param array $logs Log summary.
	 * @param array $health Health summary.
	 * @param array $journal Journal summary.
	 * @return array
	 */
	protected function build_findings( array $logs, array $health, array $journal ) {
		$findings = array();

		if ( ! empty( $logs['critical'] ) || ! empty( $logs['error'] ) ) {
			$findings[] = array(
				'key'     => 'logs',
				'status'  => ! empty( $logs['critical'] ) ? 'critical' : 'warning',
				'message' => sprintf(
					/* translators: 1: critical count, 2: error count */
					__( 'Recent logs include %1$d critical and %2$d error events.', 'zignites-sentinel' ),
					(int) $logs['critical'],
					(int) $logs['error']
				),
			);
		}

		if ( ! empty( $health['fail'] ) || ! empty( $health['blocked'] ) ) {
			$findings[] = array(
				'key'     => 'health',
				'status'  => 'critical',
				'message' => sprintf(
					/* translators: 1: failed count, 2: blocked count */
					__( 'Health checks include %1$d failed and %2$d blocked checks.', 'zignites-sentinel' ),
					(int) $health['fail'],
					(int) $health['blocked']
				),
			);
		}

		if ( ! empty( $journal['fail'] ) || ! empty( $journal['blocked'] ) || ! empty( $journal['partial'] ) ) {
			$findings[] = array(
				'key'     => 'journal',
				'status'  => ! empty( $journal['blocked'] ) ? 'critical' : 'warning',
				'message' => sprintf(
					/* translators: 1: failed count, 2: blocked count, 3: partial count */
					__( 'Restore journal includes %1$d failed, %2$d blocked, and %3$d partial entries.', 'zignites-sentinel' ),
					(int) $journal['fail'],
					(int) $journal['blocked'],
					(int) $journal['partial']
				),
			);
		}

		return $findings;
	}

	/**
	 * Build deterministic next steps.
	 *
	 * @param string $severity Severity.
	 * @param array  $logs Log summary.
	 * @param array  $health Health summary.
	 * @param array  $journal Journal summary.
	 * @return array
	 */
	protected function build_next_steps( $severity, array $logs, array $health, array $journal ) {
		if ( 'low' === $severity ) {
			return array(
				__( 'Keep the current checkpoint evidence fresh before starting another update or restore action.', 'zignites-sentinel' ),
			);
		}

		$steps = array(
			__( 'Review the deterministic Sentinel checks before relying on any AI-assisted explanation.', 'zignites-sentinel' ),
		);

		if ( ! empty( $journal['failed_entries'] ) ) {
			$steps[] = __( 'Open the restore journal and resolve the failed, blocked, or partial restore step before retrying live restore.', 'zignites-sentinel' );
		}

		if ( ! empty( $health['failed_checks'] ) ) {
			$steps[] = __( 'Fix failed health checks or confirm the failure is expected before closing the update window.', 'zignites-sentinel' );
		}

		if ( ! empty( $logs['latest_error'] ) ) {
			$steps[] = __( 'Inspect recent error logs for the first concrete failure message and timestamp.', 'zignites-sentinel' );
		}

		return $steps;
	}

	/**
	 * Build a readable row label.
	 *
	 * @param array $row Source row.
	 * @return string
	 */
	protected function label_for_row( array $row ) {
		if ( ! empty( $row['label'] ) ) {
			return sanitize_text_field( (string) $row['label'] );
		}

		if ( ! empty( $row['message'] ) ) {
			return sanitize_text_field( (string) $row['message'] );
		}

		if ( ! empty( $row['phase'] ) ) {
			return sanitize_text_field( (string) $row['phase'] );
		}

		return __( 'Unlabeled check', 'zignites-sentinel' );
	}

	/**
	 * Humanize a status key.
	 *
	 * @param string $value Status key.
	 * @return string
	 */
	protected function humanize( $value ) {
		$value = trim( str_replace( array( '-', '_' ), ' ', (string) $value ) );

		return '' === $value ? '' : ucwords( $value );
	}
}
