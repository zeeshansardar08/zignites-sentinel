<?php
/**
 * Client-friendly incident summary foundation.
 *
 * @package ZignitesSentinel
 */

namespace Zignites\Sentinel\Platform;

defined( 'ABSPATH' ) || exit;

class IncidentSummaryModel {

	/**
	 * Build a client-friendly incident summary.
	 *
	 * @param array $incident        Incident context.
	 * @param array $failure_summary Deterministic failure summary.
	 * @param array $journal_entries Restore journal entries.
	 * @param array $options         Report options.
	 * @return array
	 */
	public function build_summary( array $incident, array $failure_summary, array $journal_entries, array $options = array() ) {
		$agency  = $this->normalize_agency_options( $options );
		$outcome = $this->resolve_outcome( $failure_summary, $journal_entries );

		return array(
			'schema_version'     => '1.0',
			'generated_at'       => current_time( 'mysql', true ),
			'summary_type'       => 'client_incident_summary',
			'agency'             => $agency,
			'incident'           => $this->normalize_incident( $incident ),
			'outcome'            => $outcome,
			'plain_language'     => $this->build_plain_language_summary( $incident, $failure_summary, $outcome ),
			'impact'             => $this->build_impact_summary( $failure_summary ),
			'timeline'           => $this->build_timeline( $journal_entries ),
			'actions_taken'      => $this->build_actions_taken( $journal_entries ),
			'next_steps'         => $this->build_next_steps( $failure_summary, $outcome ),
			'boundaries'         => $this->build_boundaries(),
			'ai_assistive_only'  => true,
			'deterministic_only' => true,
		);
	}

	/**
	 * Render incident summary as plain text.
	 *
	 * @param array $summary Summary payload.
	 * @return string
	 */
	public function render_text( array $summary ) {
		$agency   = isset( $summary['agency'] ) && is_array( $summary['agency'] ) ? $summary['agency'] : array();
		$incident = isset( $summary['incident'] ) && is_array( $summary['incident'] ) ? $summary['incident'] : array();
		$outcome  = isset( $summary['outcome'] ) && is_array( $summary['outcome'] ) ? $summary['outcome'] : array();

		$lines = array(
			isset( $agency['report_title'] ) && '' !== $agency['report_title'] ? $agency['report_title'] : 'Incident Summary',
			'Generated: ' . ( isset( $summary['generated_at'] ) ? (string) $summary['generated_at'] : '' ),
			'Prepared by: ' . ( isset( $agency['name'] ) && '' !== $agency['name'] ? $agency['name'] : 'Zignites Sentinel' ),
			'Incident: ' . ( isset( $incident['title'] ) ? (string) $incident['title'] : '' ),
			'Status: ' . ( isset( $outcome['status_label'] ) ? (string) $outcome['status_label'] : '' ),
			'Summary: ' . ( isset( $summary['plain_language'] ) ? (string) $summary['plain_language'] : '' ),
			'Impact: ' . ( isset( $summary['impact'] ) ? (string) $summary['impact'] : '' ),
			'',
			'Timeline',
		);

		$timeline = isset( $summary['timeline'] ) && is_array( $summary['timeline'] ) ? $summary['timeline'] : array();
		if ( empty( $timeline ) ) {
			$lines[] = '- No restore journal entries were supplied for this incident.';
		} else {
			foreach ( $timeline as $item ) {
				$lines[] = '- ' . ( isset( $item['time'] ) && '' !== $item['time'] ? $item['time'] . ': ' : '' ) . ( isset( $item['message'] ) ? (string) $item['message'] : '' );
			}
		}

		$lines[] = '';
		$lines[] = 'Actions Taken';
		$actions = isset( $summary['actions_taken'] ) && is_array( $summary['actions_taken'] ) ? $summary['actions_taken'] : array();
		foreach ( $actions as $action ) {
			$lines[] = '- ' . (string) $action;
		}

		$lines[] = '';
		$lines[] = 'Next Steps';
		$next_steps = isset( $summary['next_steps'] ) && is_array( $summary['next_steps'] ) ? $summary['next_steps'] : array();
		foreach ( $next_steps as $step ) {
			$lines[] = '- ' . (string) $step;
		}

		$lines[] = '';
		$lines[] = 'Boundaries';
		$boundaries = isset( $summary['boundaries'] ) && is_array( $summary['boundaries'] ) ? $summary['boundaries'] : array();
		foreach ( $boundaries as $boundary ) {
			$lines[] = '- ' . (string) $boundary;
		}

		return implode( "\n", $lines ) . "\n";
	}

	/**
	 * Normalize incident context.
	 *
	 * @param array $incident Incident context.
	 * @return array
	 */
	protected function normalize_incident( array $incident ) {
		return array(
			'title'       => isset( $incident['title'] ) && '' !== trim( (string) $incident['title'] ) ? sanitize_text_field( (string) $incident['title'] ) : __( 'Update or restore incident', 'zignites-sentinel' ),
			'snapshot_id' => isset( $incident['snapshot_id'] ) ? absint( $incident['snapshot_id'] ) : 0,
			'started_at'  => isset( $incident['started_at'] ) ? sanitize_text_field( (string) $incident['started_at'] ) : '',
			'ended_at'    => isset( $incident['ended_at'] ) ? sanitize_text_field( (string) $incident['ended_at'] ) : '',
		);
	}

	/**
	 * Normalize agency options.
	 *
	 * @param array $options Report options.
	 * @return array
	 */
	protected function normalize_agency_options( array $options ) {
		return array(
			'name'         => isset( $options['agency_name'] ) ? sanitize_text_field( (string) $options['agency_name'] ) : '',
			'contact'      => isset( $options['agency_contact'] ) ? sanitize_text_field( (string) $options['agency_contact'] ) : '',
			'report_title' => isset( $options['report_title'] ) && '' !== trim( (string) $options['report_title'] )
				? sanitize_text_field( (string) $options['report_title'] )
				: __( 'Incident Summary', 'zignites-sentinel' ),
		);
	}

	/**
	 * Resolve client-facing outcome.
	 *
	 * @param array $failure_summary Failure summary.
	 * @param array $journal_entries Journal entries.
	 * @return array
	 */
	protected function resolve_outcome( array $failure_summary, array $journal_entries ) {
		$severity = isset( $failure_summary['severity'] ) ? sanitize_key( (string) $failure_summary['severity'] ) : 'low';
		$status   = 'review';

		foreach ( array_reverse( $journal_entries ) as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			if ( ! empty( $entry['scope'] ) && 'run' === $entry['scope'] && ! empty( $entry['phase'] ) && 'completed' === $entry['phase'] ) {
				$entry_status = isset( $entry['status'] ) ? sanitize_key( (string) $entry['status'] ) : '';
				if ( 'pass' === $entry_status ) {
					$status = 'resolved';
				} elseif ( in_array( $entry_status, array( 'fail', 'blocked', 'partial' ), true ) ) {
					$status = 'needs_attention';
				}
				break;
			}
		}

		if ( 'review' === $status && in_array( $severity, array( 'critical', 'high' ), true ) ) {
			$status = 'needs_attention';
		}

		return array(
			'status'       => $status,
			'status_label' => $this->humanize( $status ),
			'severity'     => $severity,
		);
	}

	/**
	 * Build plain-language incident summary.
	 *
	 * @param array $incident Incident context.
	 * @param array $failure_summary Failure summary.
	 * @param array $outcome Outcome payload.
	 * @return string
	 */
	protected function build_plain_language_summary( array $incident, array $failure_summary, array $outcome ) {
		$title    = isset( $incident['title'] ) && '' !== trim( (string) $incident['title'] ) ? sanitize_text_field( (string) $incident['title'] ) : __( 'The update or restore incident', 'zignites-sentinel' );
		$overview = isset( $failure_summary['overview'] ) ? sanitize_text_field( (string) $failure_summary['overview'] ) : '';

		if ( 'resolved' === $outcome['status'] ) {
			return sprintf(
				/* translators: 1: incident title, 2: deterministic overview */
				__( '%1$s was completed and the latest Sentinel journal indicates the run finished successfully. %2$s', 'zignites-sentinel' ),
				$title,
				$overview
			);
		}

		if ( 'needs_attention' === $outcome['status'] ) {
			return sprintf(
				/* translators: 1: incident title, 2: deterministic overview */
				__( '%1$s needs operator review before the maintenance window is considered closed. %2$s', 'zignites-sentinel' ),
				$title,
				$overview
			);
		}

		return sprintf(
			/* translators: 1: incident title, 2: deterministic overview */
			__( '%1$s has no completed restore outcome in the supplied journal. %2$s', 'zignites-sentinel' ),
			$title,
			$overview
		);
	}

	/**
	 * Build client-readable impact line.
	 *
	 * @param array $failure_summary Failure summary.
	 * @return string
	 */
	protected function build_impact_summary( array $failure_summary ) {
		$health  = isset( $failure_summary['health'] ) && is_array( $failure_summary['health'] ) ? $failure_summary['health'] : array();
		$journal = isset( $failure_summary['journal'] ) && is_array( $failure_summary['journal'] ) ? $failure_summary['journal'] : array();
		$logs    = isset( $failure_summary['logs'] ) && is_array( $failure_summary['logs'] ) ? $failure_summary['logs'] : array();

		return sprintf(
			/* translators: 1: failed health count, 2: failed journal count, 3: error log count */
			__( 'Sentinel found %1$d failed health check(s), %2$d failed or partial journal step(s), and %3$d error or critical log event(s).', 'zignites-sentinel' ),
			isset( $health['fail'] ) ? (int) $health['fail'] + ( isset( $health['blocked'] ) ? (int) $health['blocked'] : 0 ) : 0,
			( isset( $journal['fail'] ) ? (int) $journal['fail'] : 0 ) + ( isset( $journal['blocked'] ) ? (int) $journal['blocked'] : 0 ) + ( isset( $journal['partial'] ) ? (int) $journal['partial'] : 0 ),
			( isset( $logs['error'] ) ? (int) $logs['error'] : 0 ) + ( isset( $logs['critical'] ) ? (int) $logs['critical'] : 0 )
		);
	}

	/**
	 * Build plain-language timeline from journal entries.
	 *
	 * @param array $journal_entries Journal entries.
	 * @return array
	 */
	protected function build_timeline( array $journal_entries ) {
		$timeline = array();

		foreach ( $journal_entries as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			$timeline[] = array(
				'time'    => isset( $entry['timestamp'] ) ? sanitize_text_field( (string) $entry['timestamp'] ) : ( isset( $entry['logged_at'] ) ? sanitize_text_field( (string) $entry['logged_at'] ) : '' ),
				'status'  => isset( $entry['status'] ) ? sanitize_key( (string) $entry['status'] ) : '',
				'phase'   => isset( $entry['phase'] ) ? sanitize_text_field( (string) $entry['phase'] ) : '',
				'message' => isset( $entry['message'] ) ? sanitize_text_field( (string) $entry['message'] ) : __( 'Sentinel recorded a restore journal step.', 'zignites-sentinel' ),
			);
		}

		return $timeline;
	}

	/**
	 * Build action lines from journal entries.
	 *
	 * @param array $journal_entries Journal entries.
	 * @return array
	 */
	protected function build_actions_taken( array $journal_entries ) {
		$actions = array();

		foreach ( $journal_entries as $entry ) {
			if ( ! is_array( $entry ) || empty( $entry['phase'] ) ) {
				continue;
			}

			$phase = sanitize_key( (string) $entry['phase'] );

			if ( 'backup_moved' === $phase ) {
				$actions['backup_moved'] = __( 'Sentinel preserved a live-code backup before replacement work.', 'zignites-sentinel' );
			} elseif ( 'payload_written' === $phase ) {
				$actions['payload_written'] = __( 'Sentinel attempted to write checkpoint payload files back to the site.', 'zignites-sentinel' );
			} elseif ( 'completed' === $phase ) {
				$actions['completed'] = __( 'Sentinel recorded a terminal restore or rollback outcome.', 'zignites-sentinel' );
			}
		}

		if ( empty( $actions ) ) {
			$actions[] = __( 'No restore journal actions were supplied for this incident.', 'zignites-sentinel' );
		}

		return array_values( $actions );
	}

	/**
	 * Build next steps.
	 *
	 * @param array $failure_summary Failure summary.
	 * @param array $outcome Outcome.
	 * @return array
	 */
	protected function build_next_steps( array $failure_summary, array $outcome ) {
		$steps = isset( $failure_summary['next_steps'] ) && is_array( $failure_summary['next_steps'] ) ? $failure_summary['next_steps'] : array();

		if ( 'resolved' === $outcome['status'] ) {
			array_unshift( $steps, __( 'Confirm the site owner accepts the current post-incident state before closing the ticket.', 'zignites-sentinel' ) );
		} elseif ( 'needs_attention' === $outcome['status'] ) {
			array_unshift( $steps, __( 'Keep the incident open until the failed Sentinel checks are reviewed and resolved.', 'zignites-sentinel' ) );
		}

		if ( empty( $steps ) ) {
			$steps[] = __( 'Review Sentinel history and confirm whether any manual follow-up is needed.', 'zignites-sentinel' );
		}

		return array_values( array_unique( array_map( 'strval', $steps ) ) );
	}

	/**
	 * Build stable boundary lines.
	 *
	 * @return array
	 */
	protected function build_boundaries() {
		return array(
			__( 'This summary is based on deterministic Sentinel logs, health checks, and restore journal entries.', 'zignites-sentinel' ),
			__( 'Sentinel covers active plugin/theme code checkpoints only.', 'zignites-sentinel' ),
			__( 'Database, uploads/media, WordPress core, WooCommerce orders/payments, and malware cleanup require separate review.', 'zignites-sentinel' ),
		);
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
