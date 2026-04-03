<?php
/**
 * Persistent restore journal recording and lookup.
 *
 * @package ZignitesSentinel
 */

namespace Zignites\Sentinel\Snapshots;

use Zignites\Sentinel\Logging\Logger;
use Zignites\Sentinel\Logging\LogRepository;

defined( 'ABSPATH' ) || exit;

class RestoreJournalRecorder {

	/**
	 * Event type used for persisted journal rows.
	 *
	 * @var string
	 */
	const EVENT_TYPE = 'restore_journal_entry';

	/**
	 * Logger.
	 *
	 * @var Logger|null
	 */
	protected $logger;

	/**
	 * Log repository.
	 *
	 * @var LogRepository|null
	 */
	protected $repository;

	/**
	 * Constructor.
	 *
	 * @param Logger|null        $logger     Logger.
	 * @param LogRepository|null $repository Repository.
	 */
	public function __construct( Logger $logger = null, LogRepository $repository = null ) {
		$this->logger     = $logger;
		$this->repository = $repository;
	}

	/**
	 * Start or reuse a restore journal run ID.
	 *
	 * @param string $source      Journal source.
	 * @param int    $snapshot_id Snapshot ID.
	 * @param string $run_id      Optional existing run ID.
	 * @return string
	 */
	public function start_run( $source, $snapshot_id, $run_id = '' ) {
		$run_id = sanitize_text_field( (string) $run_id );

		if ( '' !== $run_id ) {
			return $run_id;
		}

		if ( function_exists( 'wp_generate_uuid4' ) ) {
			return sanitize_text_field( wp_generate_uuid4() );
		}

		return sanitize_text_field( sprintf( '%s-%d-%s', $source, absint( $snapshot_id ), gmdate( 'YmdHis' ) ) );
	}

	/**
	 * Persist a single journal entry.
	 *
	 * @param string $source      Journal source.
	 * @param int    $snapshot_id Snapshot ID.
	 * @param string $run_id      Run ID.
	 * @param array  $entry       Journal entry.
	 * @return int|false
	 */
	public function record( $source, $snapshot_id, $run_id, array $entry ) {
		if ( ! $this->logger ) {
			return false;
		}

		$message = isset( $entry['message'] ) ? (string) $entry['message'] : __( 'Restore journal entry recorded.', 'zignites-sentinel' );

		return $this->logger->log(
			self::EVENT_TYPE,
			$this->map_status_to_severity( isset( $entry['status'] ) ? $entry['status'] : '' ),
			$source,
			$message,
			array(
				'snapshot_id' => absint( $snapshot_id ),
				'run_id'      => sanitize_text_field( (string) $run_id ),
				'entry'       => $entry,
			)
		);
	}

	/**
	 * Fetch persisted journal entries for a run.
	 *
	 * @param string $source      Journal source.
	 * @param int    $snapshot_id Snapshot ID.
	 * @param string $run_id      Run ID.
	 * @return array
	 */
	public function get_entries( $source, $snapshot_id, $run_id ) {
		if ( ! $this->repository || '' === $run_id ) {
			return array();
		}

		$rows    = $this->repository->get_restore_journal_entries( $source, $snapshot_id, $run_id );
		$entries = array();

		foreach ( $rows as $row ) {
			$context = isset( $row['context'] ) ? json_decode( (string) $row['context'], true ) : array();

			if ( ! is_array( $context ) || empty( $context['entry'] ) || ! is_array( $context['entry'] ) ) {
				continue;
			}

			$entry              = $context['entry'];
			$entry['log_id']    = isset( $row['id'] ) ? (int) $row['id'] : 0;
			$entry['logged_at'] = isset( $row['created_at'] ) ? (string) $row['created_at'] : '';
			$entries[]          = $entry;
		}

		return $entries;
	}

	/**
	 * Get the latest run ID for a snapshot/source pair.
	 *
	 * @param string $source      Journal source.
	 * @param int    $snapshot_id Snapshot ID.
	 * @return string
	 */
	public function get_latest_run_id( $source, $snapshot_id ) {
		if ( ! $this->repository ) {
			return '';
		}

		return $this->repository->get_latest_restore_journal_run( $source, $snapshot_id );
	}

	/**
	 * Build resumable execution context from persisted journal rows.
	 *
	 * @param string $source       Journal source.
	 * @param int    $snapshot_id  Snapshot ID.
	 * @param array  $last_result  Last stored execution result.
	 * @return array
	 */
	public function get_resume_context( $source, $snapshot_id, array $last_result = array() ) {
		$run_id             = '';
		$selected_from_last = false;

		if (
			! empty( $last_result['run_id'] ) &&
			! empty( $last_result['snapshot_id'] ) &&
			(int) $last_result['snapshot_id'] === absint( $snapshot_id ) &&
			! empty( $last_result['status'] ) &&
			'completed' !== $last_result['status']
		) {
			$run_id             = sanitize_text_field( (string) $last_result['run_id'] );
			$selected_from_last = true;
		}

		if ( '' === $run_id ) {
			$run_id = $this->get_latest_run_id( $source, $snapshot_id );
		}

		if ( '' === $run_id ) {
			return array();
		}

		$entries = $this->get_entries( $source, $snapshot_id, $run_id );

		if ( empty( $entries ) ) {
			return array();
		}

		$completed_item_count = 0;
		$backed_up_item_count = 0;
		$terminal_status      = '';
		$last_message         = '';

		foreach ( $entries as $entry ) {
			if ( ! empty( $entry['phase'] ) && 'payload_written' === $entry['phase'] && ! empty( $entry['status'] ) && 'pass' === $entry['status'] ) {
				++$completed_item_count;
			}

			if ( ! empty( $entry['phase'] ) && 'backup_moved' === $entry['phase'] && ! empty( $entry['status'] ) && 'pass' === $entry['status'] ) {
				++$backed_up_item_count;
			}

			if ( ! empty( $entry['scope'] ) && 'run' === $entry['scope'] && ! empty( $entry['phase'] ) && 'completed' === $entry['phase'] ) {
				$terminal_status = isset( $entry['status'] ) ? sanitize_key( $entry['status'] ) : '';
			}

			$last_message = isset( $entry['message'] ) ? (string) $entry['message'] : $last_message;
		}

		$can_resume = true;

		if (
			$selected_from_last &&
			! empty( $last_result['status'] ) &&
			'completed' === $last_result['status'] &&
			! empty( $last_result['run_id'] ) &&
			sanitize_text_field( (string) $last_result['run_id'] ) === $run_id
		) {
			$can_resume = false;
		}

		if ( 'completed' === $terminal_status ) {
			$can_resume = false;
		}

		return array(
			'can_resume'           => $can_resume,
			'run_id'               => $run_id,
			'entry_count'          => count( $entries ),
			'completed_item_count' => $completed_item_count,
			'backed_up_item_count' => $backed_up_item_count,
			'terminal_status'      => $terminal_status,
			'last_message'         => $last_message,
			'summary'              => $this->summarize_entries( $entries ),
			'entries'              => $entries,
		);
	}

	/**
	 * Summarize journal entries for a single run.
	 *
	 * @param array $entries Journal entries.
	 * @return array
	 */
	public function summarize_entries( array $entries ) {
		$summary = array(
			'entry_count'          => count( $entries ),
			'completed_item_count' => 0,
			'backed_up_item_count' => 0,
			'pass'                 => 0,
			'warning'              => 0,
			'fail'                 => 0,
			'latest_timestamp'     => '',
			'terminal_status'      => '',
			'last_message'         => '',
		);

		foreach ( $entries as $entry ) {
			$status = isset( $entry['status'] ) ? sanitize_key( (string) $entry['status'] ) : '';

			if ( isset( $summary[ $status ] ) ) {
				++$summary[ $status ];
			}

			if ( ! empty( $entry['phase'] ) && 'payload_written' === $entry['phase'] && 'pass' === $status ) {
				++$summary['completed_item_count'];
			}

			if ( ! empty( $entry['phase'] ) && 'backup_moved' === $entry['phase'] && 'pass' === $status ) {
				++$summary['backed_up_item_count'];
			}

			if ( ! empty( $entry['scope'] ) && 'run' === $entry['scope'] && ! empty( $entry['phase'] ) && 'completed' === $entry['phase'] ) {
				$summary['terminal_status'] = $status;
			}

			$summary['latest_timestamp'] = isset( $entry['timestamp'] ) ? (string) $entry['timestamp'] : $summary['latest_timestamp'];
			$summary['last_message']     = isset( $entry['message'] ) ? (string) $entry['message'] : $summary['last_message'];
		}

		$summary['status_badge'] = $this->map_summary_status( $summary );

		return $summary;
	}

	/**
	 * Build summaries for recent runs from persisted journal rows.
	 *
	 * @param string $source Journal source.
	 * @param int    $limit  Max source rows to inspect.
	 * @return array
	 */
	public function summarize_recent_runs( $source = '', $limit = 250 ) {
		if ( ! $this->repository ) {
			return array();
		}

		$rows    = $this->repository->get_recent_restore_journal_rows( $source, $limit );
		$grouped = array();

		foreach ( array_reverse( $rows ) as $row ) {
			$context = isset( $row['context'] ) ? json_decode( (string) $row['context'], true ) : array();

			if ( ! is_array( $context ) || empty( $context['run_id'] ) || empty( $context['entry'] ) || ! is_array( $context['entry'] ) ) {
				continue;
			}

			$run_id = sanitize_text_field( (string) $context['run_id'] );

			if ( ! isset( $grouped[ $run_id ] ) ) {
				$grouped[ $run_id ] = array(
					'run_id'      => $run_id,
					'snapshot_id' => isset( $context['snapshot_id'] ) ? (int) $context['snapshot_id'] : 0,
					'source'      => isset( $row['source'] ) ? (string) $row['source'] : '',
					'entries'     => array(),
				);
			}

			$grouped[ $run_id ]['entries'][] = $context['entry'];
		}

		$summaries = array();

		foreach ( $grouped as $run ) {
			$summaries[] = array_merge(
				array(
					'run_id'      => $run['run_id'],
					'snapshot_id' => $run['snapshot_id'],
					'source'      => $run['source'],
				),
				$this->summarize_entries( $run['entries'] )
			);
		}

		usort(
			$summaries,
			function ( $left, $right ) {
				return strcmp(
					isset( $right['latest_timestamp'] ) ? (string) $right['latest_timestamp'] : '',
					isset( $left['latest_timestamp'] ) ? (string) $left['latest_timestamp'] : ''
				);
			}
		);

		return $summaries;
	}

	/**
	 * Map a journal status to a log severity.
	 *
	 * @param string $status Journal status.
	 * @return string
	 */
	protected function map_status_to_severity( $status ) {
		$status = sanitize_key( (string) $status );

		if ( 'fail' === $status || 'blocked' === $status ) {
			return 'error';
		}

		if ( 'warning' === $status || 'partial' === $status || 'pending' === $status ) {
			return 'warning';
		}

		return 'info';
	}

	/**
	 * Map a run summary to a status badge.
	 *
	 * @param array $summary Run summary.
	 * @return string
	 */
	protected function map_summary_status( array $summary ) {
		if ( ! empty( $summary['terminal_status'] ) ) {
			return (string) $summary['terminal_status'];
		}

		if ( ! empty( $summary['fail'] ) ) {
			return 'partial';
		}

		if ( ! empty( $summary['warning'] ) ) {
			return 'warning';
		}

		return 'running';
	}
}
