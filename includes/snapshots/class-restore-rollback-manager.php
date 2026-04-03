<?php
/**
 * Roll back a live restore from backup storage.
 *
 * @package ZignitesSentinel
 */

namespace Zignites\Sentinel\Snapshots;

use Zignites\Sentinel\Logging\Logger;

defined( 'ABSPATH' ) || exit;

class RestoreRollbackManager {

	/**
	 * Journal source for rollback runs.
	 *
	 * @var string
	 */
	const JOURNAL_SOURCE = 'restore-rollback-journal';

	/**
	 * Logger.
	 *
	 * @var Logger|null
	 */
	protected $logger;

	/**
	 * Journal recorder.
	 *
	 * @var RestoreJournalRecorder|null
	 */
	protected $journal_recorder;

	/**
	 * Constructor.
	 *
	 * @param Logger|null                 $logger           Logger.
	 * @param RestoreJournalRecorder|null $journal_recorder Journal recorder.
	 */
	public function __construct( Logger $logger = null, RestoreJournalRecorder $journal_recorder = null ) {
		$this->logger           = $logger;
		$this->journal_recorder = $journal_recorder;
	}

	/**
	 * Roll back a restore execution from backup storage.
	 *
	 * @param array  $snapshot              Snapshot row.
	 * @param array  $restore_execution     Restore execution result.
	 * @param string $confirmation_phrase   Operator confirmation phrase.
	 * @return array
	 */
	public function rollback( array $snapshot, array $restore_execution, $confirmation_phrase ) {
		return $this->rollback_with_run( $snapshot, $restore_execution, $confirmation_phrase, '' );
	}

	/**
	 * Resume a previously interrupted rollback run.
	 *
	 * @param array  $snapshot            Snapshot row.
	 * @param array  $restore_execution   Restore execution result.
	 * @param string $confirmation_phrase Operator confirmation phrase.
	 * @param array  $resume_context      Resume context.
	 * @return array
	 */
	public function resume( array $snapshot, array $restore_execution, $confirmation_phrase, array $resume_context = array() ) {
		$run_id = ! empty( $resume_context['run_id'] ) ? sanitize_text_field( (string) $resume_context['run_id'] ) : '';

		return $this->rollback_with_run( $snapshot, $restore_execution, $confirmation_phrase, $run_id );
	}

	/**
	 * Execute rollback using an optional existing run ID.
	 *
	 * @param array  $snapshot            Snapshot row.
	 * @param array  $restore_execution   Restore execution result.
	 * @param string $confirmation_phrase Operator confirmation phrase.
	 * @param string $existing_run_id     Existing run ID.
	 * @return array
	 */
	protected function rollback_with_run( array $snapshot, array $restore_execution, $confirmation_phrase, $existing_run_id = '' ) {
		$snapshot_id = isset( $snapshot['id'] ) ? (int) $snapshot['id'] : 0;
		$run_id      = $this->journal_recorder ? $this->journal_recorder->start_run( self::JOURNAL_SOURCE, $snapshot_id, $existing_run_id ) : '';
		$journal     = array(
			$this->build_journal_entry( 'gate', 'confirmation', 'completed', 'pending', __( 'Rollback confirmation has not been evaluated yet.', 'zignites-sentinel' ) ),
		);
		$checks = array(
			$this->check_confirmation_phrase( $snapshot, $confirmation_phrase ),
			$this->check_execution_context( $snapshot, $restore_execution ),
		);
		$journal[0] = $this->build_journal_entry( 'gate', 'confirmation', 'completed', isset( $checks[0]['status'] ) ? $checks[0]['status'] : 'fail', isset( $checks[0]['message'] ) ? $checks[0]['message'] : '' );
		$journal[]  = $this->build_journal_entry( 'gate', 'execution_context', 'completed', isset( $checks[1]['status'] ) ? $checks[1]['status'] : 'fail', isset( $checks[1]['message'] ) ? $checks[1]['message'] : '' );
		$this->persist_journal_entries( $snapshot_id, $run_id, $journal );

		foreach ( $checks as $check ) {
			if ( 'fail' === $check['status'] ) {
				$result = $this->finalize_result( $snapshot, $checks, array(), true, $journal, $run_id );
				$this->append_run_completion_entry( $journal, $snapshot_id, $run_id, $result );
				$result['journal'] = $journal;
				return $result;
			}
		}

		$resume_state = $this->get_resume_state( $snapshot_id, $run_id );
		$item_results = array();
		$items        = $this->get_rollback_items( $restore_execution );

		foreach ( $items as $item ) {
			$item_result = $this->rollback_item( $item, $resume_state );
			$item_results[] = $item_result;

			if ( ! empty( $item_result['journal'] ) && is_array( $item_result['journal'] ) ) {
				foreach ( $item_result['journal'] as $entry ) {
					$journal[] = $entry;
				}

				$this->persist_journal_entries( $snapshot_id, $run_id, $item_result['journal'] );
			}
		}

		$this->log_rollback( $snapshot, $item_results, isset( $restore_execution['backup_root'] ) ? $restore_execution['backup_root'] : '' );

		$result = $this->finalize_result( $snapshot, $checks, $item_results, false, $journal, $run_id );
		$this->append_run_completion_entry( $journal, $snapshot_id, $run_id, $result );
		$result['journal'] = $journal;

		return $result;
	}

	/**
	 * Check rollback confirmation phrase.
	 *
	 * @param array  $snapshot Snapshot row.
	 * @param string $phrase   Operator phrase.
	 * @return array
	 */
	protected function check_confirmation_phrase( array $snapshot, $phrase ) {
		$expected = sprintf( 'ROLLBACK SNAPSHOT %d', isset( $snapshot['id'] ) ? (int) $snapshot['id'] : 0 );
		$actual   = trim( (string) $phrase );

		if ( $expected !== $actual ) {
			return array(
				'label'   => __( 'Rollback confirmation', 'zignites-sentinel' ),
				'status'  => 'fail',
				'message' => __( 'The required rollback confirmation phrase did not match.', 'zignites-sentinel' ),
			);
		}

		return array(
			'label'   => __( 'Rollback confirmation', 'zignites-sentinel' ),
			'status'  => 'pass',
			'message' => __( 'The required rollback confirmation phrase was provided.', 'zignites-sentinel' ),
		);
	}

	/**
	 * Check that rollback context exists.
	 *
	 * @param array $snapshot          Snapshot row.
	 * @param array $restore_execution Restore execution result.
	 * @return array
	 */
	protected function check_execution_context( array $snapshot, array $restore_execution ) {
		if (
			empty( $restore_execution['snapshot_id'] ) ||
			(int) $restore_execution['snapshot_id'] !== (int) $snapshot['id'] ||
			empty( $restore_execution['backup_root'] ) ||
			empty( $restore_execution['items'] ) ||
			! is_array( $restore_execution['items'] )
		) {
			return array(
				'label'   => __( 'Rollback source context', 'zignites-sentinel' ),
				'status'  => 'fail',
				'message' => __( 'No usable restore execution backup context is available for rollback.', 'zignites-sentinel' ),
			);
		}

		return array(
			'label'   => __( 'Rollback source context', 'zignites-sentinel' ),
			'status'  => 'pass',
			'message' => __( 'Restore execution backup context is available for rollback.', 'zignites-sentinel' ),
		);
	}

	/**
	 * Roll back a single execution item.
	 *
	 * @param array $item         Execution item.
	 * @param array $resume_state Resume state.
	 * @return array
	 */
	protected function rollback_item( array $item, array $resume_state = array() ) {
		$label       = isset( $item['label'] ) ? $item['label'] : '';
		$target_path = isset( $item['target_path'] ) ? wp_normalize_path( $item['target_path'] ) : '';
		$backup_path = isset( $item['backup_path'] ) ? wp_normalize_path( $item['backup_path'] ) : '';
		$action      = isset( $item['action'] ) ? $item['action'] : '';
		$journal     = array();
		$item        = $this->normalize_item( $item );
		$item_key    = isset( $item['item_key'] ) ? $item['item_key'] : '';

		if ( ! empty( $resume_state['completed_items'][ $item_key ] ) ) {
			return array(
				'label'   => $label,
				'status'  => 'pass',
				'action'  => $action,
				'message' => __( 'This rollback item was already completed in the persisted rollback journal.', 'zignites-sentinel' ),
				'resumed' => true,
				'journal' => array(
					$this->build_journal_entry( 'item', $label, 'resume_skip', 'pass', __( 'This rollback item was already completed in the persisted rollback journal.', 'zignites-sentinel' ), $item )
				),
			);
		}

		if ( 'reuse' === $action ) {
			return array(
				'label'   => $label,
				'status'  => 'pass',
				'action'  => 'reuse',
				'message' => __( 'No rollback was needed because no live payload was changed.', 'zignites-sentinel' ),
				'journal' => array(
					$this->build_journal_entry( 'item', $label, 'completed', 'pass', __( 'No rollback was needed because no live payload was changed.', 'zignites-sentinel' ), $item )
				),
			);
		}

		if ( 'create' === $action ) {
			if ( '' === $target_path || ! file_exists( $target_path ) ) {
				return array(
					'label'   => $label,
					'status'  => 'pass',
					'action'  => 'remove-created',
					'message' => __( 'The created live payload is already absent.', 'zignites-sentinel' ),
					'journal' => array(
						$this->build_journal_entry( 'item', $label, 'completed', 'pass', __( 'The created live payload is already absent.', 'zignites-sentinel' ), $item )
					),
				);
			}

			$removed = is_dir( $target_path ) ? $this->delete_directory_recursive( $target_path ) : wp_delete_file( $target_path );

			return array(
				'label'   => $label,
				'status'  => $removed ? 'pass' : 'fail',
				'action'  => 'remove-created',
				'message' => $removed
					? __( 'The created live payload was removed during rollback.', 'zignites-sentinel' )
					: __( 'The created live payload could not be removed during rollback.', 'zignites-sentinel' ),
				'journal' => array(
					$this->build_journal_entry( 'item', $label, 'completed', $removed ? 'pass' : 'fail', $removed ? __( 'The created live payload was removed during rollback.', 'zignites-sentinel' ) : __( 'The created live payload could not be removed during rollback.', 'zignites-sentinel' ), $item )
				),
			);
		}

		if ( '' === $backup_path || ! file_exists( $backup_path ) ) {
			return array(
				'label'   => $label,
				'status'  => 'fail',
				'action'  => 'restore-backup',
				'message' => __( 'The backup payload required for rollback is missing.', 'zignites-sentinel' ),
				'journal' => array(
					$this->build_journal_entry( 'item', $label, 'completed', 'fail', __( 'The backup payload required for rollback is missing.', 'zignites-sentinel' ), $item )
				),
			);
		}

		if ( '' !== $target_path && file_exists( $target_path ) ) {
			$removed = is_dir( $target_path ) ? $this->delete_directory_recursive( $target_path ) : wp_delete_file( $target_path );

			if ( ! $removed && file_exists( $target_path ) ) {
				return array(
					'label'   => $label,
					'status'  => 'fail',
					'action'  => 'restore-backup',
					'message' => __( 'The live payload could not be removed before rollback.', 'zignites-sentinel' ),
					'journal' => array(
						$this->build_journal_entry( 'item', $label, 'completed', 'fail', __( 'The live payload could not be removed before rollback.', 'zignites-sentinel' ), $item )
					),
				);
			}

			$journal[] = $this->build_journal_entry( 'item', $label, 'target_removed', 'pass', __( 'The live payload was removed before rollback restore.', 'zignites-sentinel' ), $item );
		}

		$target_parent = dirname( $target_path );

		if ( '' !== $target_parent && ! is_dir( $target_parent ) && ! wp_mkdir_p( $target_parent ) ) {
			return array(
				'label'   => $label,
				'status'  => 'fail',
				'action'  => 'restore-backup',
				'message' => __( 'The rollback target directory could not be prepared.', 'zignites-sentinel' ),
				'journal' => array_merge(
					$journal,
					array(
						$this->build_journal_entry( 'item', $label, 'completed', 'fail', __( 'The rollback target directory could not be prepared.', 'zignites-sentinel' ), $item )
					)
				),
			);
		}

		$restored = @rename( $backup_path, $target_path );

		if ( ! $restored ) {
			$restored = is_dir( $backup_path )
				? $this->copy_directory_recursive( $backup_path, $target_path )
				: copy( $backup_path, $target_path );
		}

		return array(
			'label'   => $label,
			'status'  => $restored ? 'pass' : 'fail',
			'action'  => 'restore-backup',
			'message' => $restored
				? __( 'The backup payload was restored to the live path.', 'zignites-sentinel' )
				: __( 'The backup payload could not be restored to the live path.', 'zignites-sentinel' ),
			'journal' => array_merge(
				$journal,
				array(
					$this->build_journal_entry( 'item', $label, 'completed', $restored ? 'pass' : 'fail', $restored ? __( 'The backup payload was restored to the live path.', 'zignites-sentinel' ) : __( 'The backup payload could not be restored to the live path.', 'zignites-sentinel' ), $item )
				)
			),
		);
	}

	/**
	 * Finalize rollback result.
	 *
	 * @param array $snapshot      Snapshot row.
	 * @param array $checks        Rollback checks.
	 * @param array $item_results  Rollback item results.
	 * @param bool  $blocked_early Whether rollback was blocked early.
	 * @return array
	 */
	protected function finalize_result( array $snapshot, array $checks, array $item_results, $blocked_early, array $journal = array(), $run_id = '' ) {
		$summary = array(
			'pass'    => 0,
			'warning' => 0,
			'fail'    => 0,
		);

		foreach ( $checks as $check ) {
			if ( isset( $summary[ $check['status'] ] ) ) {
				++$summary[ $check['status'] ];
			}
		}

		foreach ( $item_results as $item ) {
			if ( isset( $summary[ $item['status'] ] ) ) {
				++$summary[ $item['status'] ];
			}
		}

		$status = 'completed';

		if ( ! empty( $summary['fail'] ) ) {
			$status = $blocked_early ? 'blocked' : 'partial';
		}

		return array(
			'generated_at' => current_time( 'mysql', true ),
			'snapshot_id'  => isset( $snapshot['id'] ) ? (int) $snapshot['id'] : 0,
			'status'       => $status,
			'checks'       => $checks,
			'items'        => $item_results,
			'summary'      => $summary,
			'journal'      => $journal,
			'run_id'       => sanitize_text_field( (string) $run_id ),
			'note'         => $this->build_note( $status ),
		);
	}

	/**
	 * Persist journal entries when a recorder is available.
	 *
	 * @param int    $snapshot_id Snapshot ID.
	 * @param string $run_id      Run ID.
	 * @param array  $entries     Journal entries.
	 * @return void
	 */
	protected function persist_journal_entries( $snapshot_id, $run_id, array $entries ) {
		if ( ! $this->journal_recorder || '' === $run_id ) {
			return;
		}

		foreach ( $entries as $entry ) {
			$this->journal_recorder->record( self::JOURNAL_SOURCE, $snapshot_id, $run_id, $entry );
		}
	}

	/**
	 * Build resume state from persisted rollback journal entries.
	 *
	 * @param int    $snapshot_id Snapshot ID.
	 * @param string $run_id      Run ID.
	 * @return array
	 */
	protected function get_resume_state( $snapshot_id, $run_id ) {
		$state = array(
			'entries'         => array(),
			'completed_items' => array(),
		);

		if ( ! $this->journal_recorder || '' === $run_id ) {
			return $state;
		}

		$state['entries'] = $this->journal_recorder->get_entries( self::JOURNAL_SOURCE, $snapshot_id, $run_id );

		foreach ( $state['entries'] as $entry ) {
			$context  = isset( $entry['context'] ) && is_array( $entry['context'] ) ? $entry['context'] : array();
			$item_key = $this->extract_item_key( $context );

			if (
				'' !== $item_key &&
				! empty( $entry['phase'] ) &&
				'completed' === $entry['phase'] &&
				! empty( $entry['status'] ) &&
				'pass' === $entry['status']
			) {
				$state['completed_items'][ $item_key ] = $context;
			}
		}

		return $state;
	}

	/**
	 * Normalize an item with a stable item key.
	 *
	 * @param array $item Item data.
	 * @return array
	 */
	protected function normalize_item( array $item ) {
		$item['item_key'] = $this->build_item_key( $item );

		return $item;
	}

	/**
	 * Build a stable item key.
	 *
	 * @param array $item Item data.
	 * @return string
	 */
	protected function build_item_key( array $item ) {
		return md5(
			implode(
				'|',
				array(
					isset( $item['action'] ) ? (string) $item['action'] : '',
					isset( $item['label'] ) ? (string) $item['label'] : '',
					isset( $item['target_path'] ) ? wp_normalize_path( (string) $item['target_path'] ) : '',
					isset( $item['backup_path'] ) ? wp_normalize_path( (string) $item['backup_path'] ) : '',
				)
			)
		);
	}

	/**
	 * Extract an item key from journal context.
	 *
	 * @param array $context Journal context.
	 * @return string
	 */
	protected function extract_item_key( array $context ) {
		if ( ! empty( $context['item_key'] ) ) {
			return sanitize_text_field( (string) $context['item_key'] );
		}

		if ( empty( $context['target_path'] ) && empty( $context['backup_path'] ) ) {
			return '';
		}

		return $this->build_item_key( $context );
	}

	/**
	 * Append a terminal rollback completion entry.
	 *
	 * @param array  $journal     Journal array.
	 * @param int    $snapshot_id Snapshot ID.
	 * @param string $run_id      Run ID.
	 * @param array  $result      Rollback result.
	 * @return void
	 */
	protected function append_run_completion_entry( array &$journal, $snapshot_id, $run_id, array $result ) {
		$entry = $this->build_journal_entry(
			'run',
			'rollback',
			'completed',
			isset( $result['status'] ) ? (string) $result['status'] : 'partial',
			isset( $result['note'] ) ? (string) $result['note'] : __( 'Restore rollback finished.', 'zignites-sentinel' ),
			array(
				'summary' => isset( $result['summary'] ) && is_array( $result['summary'] ) ? $result['summary'] : array(),
			)
		);

		$journal[] = $entry;
		$this->persist_journal_entries( $snapshot_id, $run_id, array( $entry ) );
	}

	/**
	 * Build rollback items from execution journal when available.
	 *
	 * @param array $restore_execution Restore execution result.
	 * @return array
	 */
	protected function get_rollback_items( array $restore_execution ) {
		$items = array();

		if ( ! empty( $restore_execution['journal'] ) && is_array( $restore_execution['journal'] ) ) {
			for ( $index = count( $restore_execution['journal'] ) - 1; $index >= 0; $index-- ) {
				$entry = $restore_execution['journal'][ $index ];

				if (
					empty( $entry['scope'] ) ||
					'item' !== $entry['scope'] ||
					empty( $entry['phase'] ) ||
					'payload_written' !== $entry['phase'] ||
					empty( $entry['status'] ) ||
					'pass' !== $entry['status'] ||
					empty( $entry['context'] ) ||
					! is_array( $entry['context'] )
				) {
					continue;
				}

				$items[] = $entry['context'];
			}

			if ( ! empty( $items ) ) {
				return $items;
			}
		}

		return isset( $restore_execution['items'] ) && is_array( $restore_execution['items'] ) ? array_reverse( $restore_execution['items'] ) : array();
	}

	/**
	 * Copy a directory recursively.
	 *
	 * @param string $source Source path.
	 * @param string $target Target path.
	 * @return bool
	 */
	protected function copy_directory_recursive( $source, $target ) {
		if ( ! is_dir( $source ) ) {
			return false;
		}

		if ( ! is_dir( $target ) && ! wp_mkdir_p( $target ) ) {
			return false;
		}

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $source, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ( $iterator as $item ) {
			$source_path = wp_normalize_path( $item->getPathname() );
			$relative    = ltrim( substr( $source_path, strlen( trailingslashit( wp_normalize_path( $source ) ) ) ), '/' );
			$target_path = trailingslashit( wp_normalize_path( $target ) ) . $relative;

			if ( $item->isDir() ) {
				if ( ! is_dir( $target_path ) && ! wp_mkdir_p( $target_path ) ) {
					return false;
				}
				continue;
			}

			if ( ! copy( $source_path, $target_path ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Delete a directory recursively.
	 *
	 * @param string $path Directory path.
	 * @return bool
	 */
	protected function delete_directory_recursive( $path ) {
		if ( '' === $path || ! is_dir( $path ) ) {
			return true;
		}

		$items = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $path, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $items as $item ) {
			if ( $item->isDir() ) {
				if ( ! @rmdir( $item->getPathname() ) ) {
					return false;
				}
				continue;
			}

			if ( ! wp_delete_file( $item->getPathname() ) && file_exists( $item->getPathname() ) ) {
				return false;
			}
		}

		return @rmdir( $path );
	}

	/**
	 * Log rollback result.
	 *
	 * @param array  $snapshot     Snapshot row.
	 * @param array  $item_results Rollback item results.
	 * @param string $backup_root  Backup root.
	 * @return void
	 */
	protected function log_rollback( array $snapshot, array $item_results, $backup_root ) {
		if ( ! $this->logger ) {
			return;
		}

		$failures = 0;

		foreach ( $item_results as $item ) {
			if ( isset( $item['status'] ) && 'fail' === $item['status'] ) {
				++$failures;
			}
		}

		$this->logger->log(
			'restore_rollback_completed',
			$failures > 0 ? 'warning' : 'info',
			'restore-rollback',
			__( 'Restore rollback completed.', 'zignites-sentinel' ),
			array(
				'snapshot_id' => isset( $snapshot['id'] ) ? (int) $snapshot['id'] : 0,
				'backup_root' => $backup_root,
				'item_count'  => count( $item_results ),
				'failures'    => $failures,
			)
		);
	}

	/**
	 * Build rollback note.
	 *
	 * @param string $status Rollback status.
	 * @return string
	 */
	protected function build_note( $status ) {
		if ( 'blocked' === $status ) {
			return __( 'Rollback was blocked by missing confirmation or backup context.', 'zignites-sentinel' );
		}

		if ( 'partial' === $status ) {
			return __( 'Rollback ran with failures. Review the execution and rollback logs immediately.', 'zignites-sentinel' );
		}

		return __( 'Rollback completed from the stored live-restore backup payloads.', 'zignites-sentinel' );
	}

	/**
	 * Build a rollback journal entry.
	 *
	 * @param string $scope   Journal scope.
	 * @param string $label   Journal label.
	 * @param string $phase   Journal phase.
	 * @param string $status  Journal status.
	 * @param string $message Journal message.
	 * @param array  $context Journal context.
	 * @return array
	 */
	protected function build_journal_entry( $scope, $label, $phase, $status, $message, array $context = array() ) {
		return array(
			'timestamp' => current_time( 'mysql', true ),
			'scope'     => sanitize_key( $scope ),
			'label'     => sanitize_text_field( (string) $label ),
			'phase'     => sanitize_key( $phase ),
			'status'    => sanitize_key( $status ),
			'message'   => sanitize_text_field( (string) $message ),
			'context'   => $context,
		);
	}
}
