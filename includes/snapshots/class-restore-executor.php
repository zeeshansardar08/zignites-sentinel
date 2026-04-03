<?php
/**
 * Guarded live restore execution.
 *
 * @package ZignitesSentinel
 */

namespace Zignites\Sentinel\Snapshots;

use Zignites\Sentinel\Logging\Logger;

defined( 'ABSPATH' ) || exit;

class RestoreExecutor {

	/**
	 * Relative backup directory under uploads.
	 *
	 * @var string
	 */
	const BACKUP_DIRECTORY = 'zignites-sentinel/restore-backups';

	/**
	 * Journal source for execution runs.
	 *
	 * @var string
	 */
	const JOURNAL_SOURCE = 'restore-execution-journal';

	/**
	 * Staging manager.
	 *
	 * @var RestoreStagingManager
	 */
	protected $staging_manager;

	/**
	 * Execution planner.
	 *
	 * @var RestoreExecutionPlanner
	 */
	protected $planner;

	/**
	 * Logger.
	 *
	 * @var Logger|null
	 */
	protected $logger;

	/**
	 * Health verifier.
	 *
	 * @var RestoreHealthVerifier
	 */
	protected $health_verifier;

	/**
	 * Journal recorder.
	 *
	 * @var RestoreJournalRecorder|null
	 */
	protected $journal_recorder;

	/**
	 * Checkpoint store.
	 *
	 * @var RestoreCheckpointStore|null
	 */
	protected $checkpoint_store;

	/**
	 * Constructor.
	 *
	 * @param RestoreStagingManager       $staging_manager  Staging manager.
	 * @param RestoreExecutionPlanner     $planner          Execution planner.
	 * @param RestoreHealthVerifier       $health_verifier  Health verifier.
	 * @param Logger|null                 $logger           Logger.
	 * @param RestoreJournalRecorder|null $journal_recorder Journal recorder.
	 * @param RestoreCheckpointStore|null $checkpoint_store Checkpoint store.
	 */
	public function __construct( RestoreStagingManager $staging_manager, RestoreExecutionPlanner $planner, RestoreHealthVerifier $health_verifier, Logger $logger = null, RestoreJournalRecorder $journal_recorder = null, RestoreCheckpointStore $checkpoint_store = null ) {
		$this->staging_manager  = $staging_manager;
		$this->planner          = $planner;
		$this->health_verifier  = $health_verifier;
		$this->logger           = $logger;
		$this->journal_recorder = $journal_recorder;
		$this->checkpoint_store = $checkpoint_store;
	}

	/**
	 * Execute a guarded live restore.
	 *
	 * @param array  $snapshot            Snapshot row.
	 * @param array  $artifacts           Artifact rows.
	 * @param array  $last_stage_result   Last staged validation result.
	 * @param array  $last_plan_result    Last restore plan result.
	 * @param string $confirmation_phrase Operator confirmation phrase.
	 * @param string $resume_run_id       Optional existing run ID.
	 * @return array
	 */
	public function execute( array $snapshot, array $artifacts, array $last_stage_result, array $last_plan_result, $confirmation_phrase, $resume_run_id = '' ) {
		$snapshot_id  = isset( $snapshot['id'] ) ? (int) $snapshot['id'] : 0;
		$run_id       = $this->journal_recorder ? $this->journal_recorder->start_run( self::JOURNAL_SOURCE, $snapshot_id, $resume_run_id ) : '';
		$resume_state = $this->get_resume_state( $snapshot_id, $run_id );
		$execution_checkpoint = $this->get_execution_checkpoint( $snapshot, $artifacts, $run_id );
		$journal      = array();
		$checks       = array(
			$this->check_confirmation_phrase( $snapshot, $confirmation_phrase ),
			$this->check_stage_result( $snapshot, $last_stage_result ),
			$this->check_plan_result( $snapshot, $last_plan_result ),
		);

		$this->append_journal_entry( $journal, $snapshot_id, $run_id, $this->build_journal_entry( 'gate', 'confirmation', 'completed', isset( $checks[0]['status'] ) ? $checks[0]['status'] : 'fail', isset( $checks[0]['message'] ) ? $checks[0]['message'] : '' ) );
		$this->append_journal_entry( $journal, $snapshot_id, $run_id, $this->build_journal_entry( 'gate', 'staged_validation', 'completed', isset( $checks[1]['status'] ) ? $checks[1]['status'] : 'fail', isset( $checks[1]['message'] ) ? $checks[1]['message'] : '' ) );
		$this->append_journal_entry( $journal, $snapshot_id, $run_id, $this->build_journal_entry( 'gate', 'restore_plan', 'completed', isset( $checks[2]['status'] ) ? $checks[2]['status'] : 'fail', isset( $checks[2]['message'] ) ? $checks[2]['message'] : '' ) );

		if ( ! empty( $resume_state['entries'] ) && isset( $checks[1]['status'] ) && 'pass' === $checks[1]['status'] ) {
			$this->append_journal_entry( $journal, $snapshot_id, $run_id, $this->build_journal_entry( 'stage', 'validation_checkpoint', 'resume_reuse', 'pass', __( 'The existing staged validation checkpoint was reused for resume execution.', 'zignites-sentinel' ) ) );
		}

		foreach ( $checks as $check ) {
			if ( 'fail' === $check['status'] ) {
				$result = $this->finalize_result( $snapshot, $checks, array(), '', '', true, array(), $journal, $run_id, ! empty( $resume_state['entries'] ) );
				$this->append_run_completion_entry( $journal, $snapshot_id, $run_id, $result );
				$result['journal'] = $journal;
				return $result;
			}
		}

		$stage    = $this->resolve_execution_stage( $snapshot, $artifacts, $execution_checkpoint );
		$checks[] = $stage['stage_check'];
		$this->append_journal_entry( $journal, $snapshot_id, $run_id, $this->build_journal_entry( 'stage', 'stage_directory', ! empty( $stage['reused'] ) ? 'resume_reuse' : 'completed', isset( $stage['stage_check']['status'] ) ? $stage['stage_check']['status'] : 'fail', isset( $stage['stage_check']['message'] ) ? $stage['stage_check']['message'] : '' ) );

		if ( isset( $stage['extraction_check'] ) ) {
			$checks[] = $stage['extraction_check'];
			$this->append_journal_entry( $journal, $snapshot_id, $run_id, $this->build_journal_entry( 'stage', 'package_extraction', ! empty( $stage['reused'] ) ? 'resume_reuse' : 'completed', isset( $stage['extraction_check']['status'] ) ? $stage['extraction_check']['status'] : 'fail', isset( $stage['extraction_check']['message'] ) ? $stage['extraction_check']['message'] : '' ) );
		}

		if ( empty( $stage['success'] ) ) {
			if ( $this->checkpoint_store ) {
				$this->checkpoint_store->clear_execution_checkpoint( $snapshot_id, $run_id );
			}
			$result = $this->finalize_result( $snapshot, $checks, array(), '', '', true, array(), $journal, $run_id, ! empty( $resume_state['entries'] ) );
			$this->append_run_completion_entry( $journal, $snapshot_id, $run_id, $result );
			$result['journal'] = $journal;
			return $result;
		}

		$this->store_execution_checkpoint(
			$snapshot,
			$artifacts,
			$run_id,
			array(
				'stage_ready'         => true,
				'stage_path'          => isset( $stage['stage_path'] ) ? $stage['stage_path'] : '',
				'health_completed'    => ! empty( $execution_checkpoint['health_completed'] ),
				'health_verification' => ! empty( $execution_checkpoint['health_verification'] ) && is_array( $execution_checkpoint['health_verification'] ) ? $execution_checkpoint['health_verification'] : array(),
			)
		);

		$plan = $this->resolve_execution_plan( $snapshot, $artifacts, $last_plan_result, $resume_state );
		$checks[] = array(
			'label'   => __( 'Restore plan refresh', 'zignites-sentinel' ),
			'status'  => isset( $plan['status'] ) && 'blocked' !== $plan['status'] ? 'pass' : 'fail',
			'message' => isset( $plan['note'] ) ? $plan['note'] : __( 'The restore plan could not be refreshed.', 'zignites-sentinel' ),
		);

		$plan_phase = ! empty( $plan['resumed_plan'] ) ? 'resume_reuse' : 'completed';
		$plan_note  = ! empty( $plan['resumed_plan'] )
			? __( 'The existing restore plan checkpoint was reused for resume execution.', 'zignites-sentinel' )
			: ( isset( $plan['note'] ) ? $plan['note'] : '' );

		$this->append_journal_entry(
			$journal,
			$snapshot_id,
			$run_id,
			$this->build_journal_entry(
				'plan',
				'refresh',
				$plan_phase,
				isset( $plan['status'] ) && 'blocked' !== $plan['status'] ? 'pass' : 'fail',
				$plan_note,
				array(
					'item_count' => isset( $plan['items'] ) && is_array( $plan['items'] ) ? count( $plan['items'] ) : 0,
					'status'     => isset( $plan['status'] ) ? $plan['status'] : '',
					'resumed'    => ! empty( $plan['resumed_plan'] ),
				)
			)
		);

		if ( isset( $plan['status'] ) && 'blocked' === $plan['status'] ) {
			$this->staging_manager->cleanup_stage_directory( $stage['stage_path'] );
			if ( $this->checkpoint_store ) {
				$this->checkpoint_store->clear_execution_checkpoint( $snapshot_id, $run_id );
			}
			$result = $this->finalize_result( $snapshot, $checks, array(), '', '', true, array(), $journal, $run_id, ! empty( $resume_state['entries'] ) );
			$this->append_run_completion_entry( $journal, $snapshot_id, $run_id, $result );
			$result['journal'] = $journal;
			return $result;
		}

		$backup_root = $this->resolve_backup_root( $snapshot_id, $resume_state );

		if ( '' === $backup_root ) {
			$this->staging_manager->cleanup_stage_directory( $stage['stage_path'] );
			$checks[] = array(
				'label'   => __( 'Backup root', 'zignites-sentinel' ),
				'status'  => 'fail',
				'message' => __( 'A restore backup directory could not be created.', 'zignites-sentinel' ),
			);
			$this->append_journal_entry( $journal, $snapshot_id, $run_id, $this->build_journal_entry( 'backup', 'root', 'completed', 'fail', __( 'A restore backup directory could not be created.', 'zignites-sentinel' ) ) );
			$result = $this->finalize_result( $snapshot, $checks, array(), '', '', true, array(), $journal, $run_id, ! empty( $resume_state['entries'] ) );
			$this->append_run_completion_entry( $journal, $snapshot_id, $run_id, $result );
			$result['journal'] = $journal;
			return $result;
		}

		$backup_message = ! empty( $resume_state['backup_root'] ) && $resume_state['backup_root'] === $backup_root
			? __( 'The existing restore backup directory was reused.', 'zignites-sentinel' )
			: __( 'A restore backup directory was created.', 'zignites-sentinel' );

		$checks[] = array(
			'label'   => __( 'Backup root', 'zignites-sentinel' ),
			'status'  => 'pass',
			'message' => $backup_message,
		);
		$this->append_journal_entry( $journal, $snapshot_id, $run_id, $this->build_journal_entry( 'backup', 'root', 'completed', 'pass', $backup_message, array( 'backup_root' => $backup_root ) ) );

		$item_results = array();
		$pending_item_count = $this->count_pending_plan_items( $plan['items'], $resume_state );

		foreach ( $plan['items'] as $item ) {
			$item_result   = $this->execute_plan_item( $item, $stage['stage_path'], $backup_root, $resume_state );
			$item_results[] = $item_result;

			if ( ! empty( $item_result['journal'] ) && is_array( $item_result['journal'] ) ) {
				foreach ( $item_result['journal'] as $entry ) {
					$this->append_journal_entry( $journal, $snapshot_id, $run_id, $entry );
				}
			}
		}

		$health = array();

		if ( 0 === $pending_item_count && ! empty( $execution_checkpoint['health_completed'] ) && ! empty( $execution_checkpoint['health_verification'] ) && is_array( $execution_checkpoint['health_verification'] ) ) {
			$health = $execution_checkpoint['health_verification'];
			$this->append_journal_entry( $journal, $snapshot_id, $run_id, $this->build_journal_entry( 'health', 'verification', 'resume_reuse', $this->map_health_status_to_check_status( isset( $health['status'] ) ? $health['status'] : '' ), __( 'The previous health verification checkpoint was reused for resume execution.', 'zignites-sentinel' ) ) );
		} else {
			$health = $this->health_verifier->verify( $snapshot );
			$this->append_journal_entry( $journal, $snapshot_id, $run_id, $this->build_journal_entry( 'health', 'verification', 'completed', $this->map_health_status_to_check_status( isset( $health['status'] ) ? $health['status'] : '' ), isset( $health['note'] ) ? $health['note'] : '' ) );
		}

		$preliminary_result = $this->finalize_result( $snapshot, $checks, $item_results, $backup_root, $stage['stage_path'], false, $health, $journal, $run_id, ! empty( $resume_state['entries'] ) );
		$cleanup_ok = $this->finalize_stage_checkpoint( $snapshot, $artifacts, $run_id, $stage['stage_path'], $preliminary_result, $health );
		$checks[]   = array(
			'label'   => __( 'Execution stage cleanup', 'zignites-sentinel' ),
			'status'  => $cleanup_ok['status'],
			'message' => $cleanup_ok['message'],
		);
		$this->append_journal_entry( $journal, $snapshot_id, $run_id, $this->build_journal_entry( 'stage', 'cleanup', $cleanup_ok['phase'], $cleanup_ok['status'], $cleanup_ok['message'] ) );
		$result = $this->finalize_result( $snapshot, $checks, $item_results, $backup_root, $stage['stage_path'], false, $health, $journal, $run_id, ! empty( $resume_state['entries'] ) );
		$this->append_run_completion_entry( $journal, $snapshot_id, $run_id, $result );
		$result['journal'] = $journal;

		$this->log_execution( $snapshot, $item_results, $backup_root, $run_id, ! empty( $resume_state['entries'] ) );

		return $result;
	}

	/**
	 * Resume a previously interrupted or partial restore execution.
	 *
	 * @param array  $snapshot            Snapshot row.
	 * @param array  $artifacts           Artifact rows.
	 * @param array  $last_stage_result   Last staged validation result.
	 * @param array  $last_plan_result    Last restore plan result.
	 * @param string $confirmation_phrase Operator confirmation phrase.
	 * @param array  $resume_context      Resume context.
	 * @return array
	 */
	public function resume( array $snapshot, array $artifacts, array $last_stage_result, array $last_plan_result, $confirmation_phrase, array $resume_context = array() ) {
		$run_id = ! empty( $resume_context['run_id'] ) ? sanitize_text_field( (string) $resume_context['run_id'] ) : '';

		return $this->execute( $snapshot, $artifacts, $last_stage_result, $last_plan_result, $confirmation_phrase, $run_id );
	}

	/**
	 * Delete the restore backup root directory.
	 *
	 * @return void
	 */
	public function delete_backup_directory_root() {
		$base_dir = $this->get_backup_root_directory();

		if ( '' === $base_dir || ! is_dir( $base_dir ) ) {
			return;
		}

		$this->delete_directory_recursive( $base_dir );
	}

	/**
	 * Check the operator confirmation phrase.
	 *
	 * @param array  $snapshot Snapshot row.
	 * @param string $phrase   Provided phrase.
	 * @return array
	 */
	protected function check_confirmation_phrase( array $snapshot, $phrase ) {
		$expected = sprintf( 'RESTORE SNAPSHOT %d', isset( $snapshot['id'] ) ? (int) $snapshot['id'] : 0 );
		$actual   = trim( (string) $phrase );

		if ( $expected !== $actual ) {
			return array(
				'label'   => __( 'Operator confirmation', 'zignites-sentinel' ),
				'status'  => 'fail',
				'message' => __( 'The required restore confirmation phrase did not match.', 'zignites-sentinel' ),
			);
		}

		return array(
			'label'   => __( 'Operator confirmation', 'zignites-sentinel' ),
			'status'  => 'pass',
			'message' => __( 'The required restore confirmation phrase was provided.', 'zignites-sentinel' ),
		);
	}

	/**
	 * Check the last staged validation result.
	 *
	 * @param array $snapshot          Snapshot row.
	 * @param array $last_stage_result Last stage result.
	 * @return array
	 */
	protected function check_stage_result( array $snapshot, array $last_stage_result ) {
		if (
			empty( $last_stage_result['snapshot_id'] ) ||
			(int) $last_stage_result['snapshot_id'] !== (int) $snapshot['id'] ||
			empty( $last_stage_result['status'] ) ||
			'ready' !== $last_stage_result['status']
		) {
			return array(
				'label'   => __( 'Staged validation gate', 'zignites-sentinel' ),
				'status'  => 'fail',
				'message' => __( 'A successful staged restore validation for this snapshot is required before live restore execution.', 'zignites-sentinel' ),
			);
		}

		return array(
			'label'   => __( 'Staged validation gate', 'zignites-sentinel' ),
			'status'  => 'pass',
			'message' => __( 'A successful staged restore validation exists for this snapshot.', 'zignites-sentinel' ),
		);
	}

	/**
	 * Check the last restore plan result.
	 *
	 * @param array $snapshot         Snapshot row.
	 * @param array $last_plan_result Last plan result.
	 * @return array
	 */
	protected function check_plan_result( array $snapshot, array $last_plan_result ) {
		if (
			empty( $last_plan_result['snapshot_id'] ) ||
			(int) $last_plan_result['snapshot_id'] !== (int) $snapshot['id'] ||
			empty( $last_plan_result['status'] ) ||
			'blocked' === $last_plan_result['status']
		) {
			return array(
				'label'   => __( 'Restore plan gate', 'zignites-sentinel' ),
				'status'  => 'fail',
				'message' => __( 'A current non-blocked restore plan for this snapshot is required before live restore execution.', 'zignites-sentinel' ),
			);
		}

		return array(
			'label'   => __( 'Restore plan gate', 'zignites-sentinel' ),
			'status'  => 'pass',
			'message' => __( 'A current restore plan exists for this snapshot.', 'zignites-sentinel' ),
		);
	}

	/**
	 * Execute a single plan item.
	 *
	 * @param array  $item         Plan item.
	 * @param string $stage_path   Stage path.
	 * @param string $backup_root  Backup root.
	 * @param array  $resume_state Resume state.
	 * @return array
	 */
	protected function execute_plan_item( array $item, $stage_path, $backup_root, array $resume_state = array() ) {
		$journal  = array();
		$item     = $this->normalize_item( $item );
		$item_key = isset( $item['item_key'] ) ? $item['item_key'] : '';

		if ( empty( $item['package_path'] ) || empty( $item['target_path'] ) || 'blocked' === $item['action'] ) {
			return array(
				'label'   => isset( $item['label'] ) ? $item['label'] : '',
				'status'  => 'fail',
				'action'  => isset( $item['action'] ) ? $item['action'] : 'blocked',
				'message' => __( 'The restore plan item is not executable.', 'zignites-sentinel' ),
				'journal' => array(
					$this->build_journal_entry( 'item', isset( $item['label'] ) ? $item['label'] : 'unknown', 'completed', 'fail', __( 'The restore plan item is not executable.', 'zignites-sentinel' ), $item )
				),
			);
		}

		if ( ! empty( $resume_state['completed_items'][ $item_key ] ) ) {
			return array(
				'label'       => $item['label'],
				'status'      => 'pass',
				'action'      => $item['action'],
				'message'     => __( 'This restore item was already completed in the persisted execution journal.', 'zignites-sentinel' ),
				'target_path' => isset( $item['target_path'] ) ? $item['target_path'] : '',
				'backup_path' => ! empty( $resume_state['completed_items'][ $item_key ]['backup_path'] ) ? $resume_state['completed_items'][ $item_key ]['backup_path'] : '',
				'resumed'     => true,
				'journal'     => array(
					$this->build_journal_entry( 'item', $item['label'], 'resume_skip', 'pass', __( 'This restore item was already completed in the persisted execution journal.', 'zignites-sentinel' ), $item )
				),
			);
		}

		if ( 'reuse' === $item['action'] ) {
			return array(
				'label'   => $item['label'],
				'status'  => 'pass',
				'action'  => 'reuse',
				'message' => __( 'The live payload already matches the snapshot payload. No write was needed.', 'zignites-sentinel' ),
				'target_path' => isset( $item['target_path'] ) ? $item['target_path'] : '',
				'journal' => array(
					$this->build_journal_entry( 'item', $item['label'], 'completed', 'pass', __( 'The live payload already matches the snapshot payload. No write was needed.', 'zignites-sentinel' ), $item )
				),
			);
		}

		$source_path = trailingslashit( wp_normalize_path( $stage_path ) ) . ltrim( wp_normalize_path( $item['package_path'] ), '/' );
		$target_path = wp_normalize_path( $item['target_path'] );
		$backup_path = trailingslashit( wp_normalize_path( $backup_root ) ) . trim( wp_normalize_path( $item['package_path'] ), '/' );
		$backup_already_moved = ! empty( $resume_state['backed_up_items'][ $item_key ]['backup_path'] ) && file_exists( $resume_state['backed_up_items'][ $item_key ]['backup_path'] );

		if ( ! file_exists( $source_path ) ) {
			return array(
				'label'   => $item['label'],
				'status'  => 'fail',
				'action'  => $item['action'],
				'message' => __( 'The staged source payload is missing and could not be restored.', 'zignites-sentinel' ),
				'target_path' => $target_path,
				'backup_path' => '',
				'journal' => array(
					$this->build_journal_entry( 'item', $item['label'], 'completed', 'fail', __( 'The staged source payload is missing and could not be restored.', 'zignites-sentinel' ), $item )
				),
			);
		}

		if ( $backup_already_moved ) {
			$backup_path = wp_normalize_path( $resume_state['backed_up_items'][ $item_key ]['backup_path'] );
			$journal[]   = $this->build_journal_entry( 'item', $item['label'], 'backup_reused', 'pass', __( 'The existing backup payload was reused for resume execution.', 'zignites-sentinel' ), array_merge( $item, array( 'backup_path' => $backup_path, 'target_path' => $target_path ) ) );
		} elseif ( file_exists( $target_path ) ) {
			$backup_parent = dirname( $backup_path );

			if ( ! is_dir( $backup_parent ) && ! wp_mkdir_p( $backup_parent ) ) {
				return array(
					'label'   => $item['label'],
					'status'  => 'fail',
					'action'  => $item['action'],
					'message' => __( 'The backup path could not be prepared for this restore item.', 'zignites-sentinel' ),
					'target_path' => $target_path,
					'backup_path' => $backup_path,
					'journal' => array(
						$this->build_journal_entry( 'item', $item['label'], 'completed', 'fail', __( 'The backup path could not be prepared for this restore item.', 'zignites-sentinel' ), $item )
					),
				);
			}

			if ( ! @rename( $target_path, $backup_path ) ) {
				return array(
					'label'   => $item['label'],
					'status'  => 'fail',
					'action'  => $item['action'],
					'message' => __( 'The existing live payload could not be moved into backup storage.', 'zignites-sentinel' ),
					'target_path' => $target_path,
					'backup_path' => $backup_path,
					'journal' => array(
						$this->build_journal_entry( 'item', $item['label'], 'completed', 'fail', __( 'The existing live payload could not be moved into backup storage.', 'zignites-sentinel' ), $item )
					),
				);
			}

			$journal[] = $this->build_journal_entry( 'item', $item['label'], 'backup_moved', 'pass', __( 'The existing live payload was moved into backup storage.', 'zignites-sentinel' ), array_merge( $item, array( 'backup_path' => $backup_path, 'target_path' => $target_path ) ) );
		}

		if ( $backup_already_moved && file_exists( $target_path ) ) {
			$removed_existing_target = $this->delete_path( $target_path );

			if ( ! $removed_existing_target && file_exists( $target_path ) ) {
				return array(
					'label'       => $item['label'],
					'status'      => 'fail',
					'action'      => $item['action'],
					'message'     => __( 'The partially restored live payload could not be reset before resume execution.', 'zignites-sentinel' ),
					'target_path' => $target_path,
					'backup_path' => $backup_path,
					'journal'     => array_merge(
						$journal,
						array(
							$this->build_journal_entry( 'item', $item['label'], 'completed', 'fail', __( 'The partially restored live payload could not be reset before resume execution.', 'zignites-sentinel' ), $item )
						)
					),
				);
			}

			$journal[] = $this->build_journal_entry( 'item', $item['label'], 'target_reset', 'pass', __( 'The partially restored live payload was removed before resume execution.', 'zignites-sentinel' ), array_merge( $item, array( 'target_path' => $target_path ) ) );
		}

		$target_parent = dirname( $target_path );

		if ( ! is_dir( $target_parent ) && ! wp_mkdir_p( $target_parent ) ) {
			return array(
				'label'   => $item['label'],
				'status'  => 'fail',
				'action'  => $item['action'],
				'message' => __( 'The target parent directory could not be prepared for restore execution.', 'zignites-sentinel' ),
				'target_path' => $target_path,
				'backup_path' => $backup_path,
				'journal' => array_merge(
					$journal,
					array(
						$this->build_journal_entry( 'item', $item['label'], 'completed', 'fail', __( 'The target parent directory could not be prepared for restore execution.', 'zignites-sentinel' ), $item )
					)
				),
			);
		}

		if ( ! @rename( $source_path, $target_path ) ) {
			$copied = is_dir( $source_path )
				? $this->copy_directory_recursive( $source_path, $target_path )
				: copy( $source_path, $target_path );

			if ( ! $copied ) {
				return array(
					'label'   => $item['label'],
					'status'  => 'fail',
					'action'  => $item['action'],
					'message' => __( 'The staged payload could not be written to the live target path.', 'zignites-sentinel' ),
					'target_path' => $target_path,
					'backup_path' => $backup_path,
					'journal' => array_merge(
						$journal,
						array(
							$this->build_journal_entry( 'item', $item['label'], 'completed', 'fail', __( 'The staged payload could not be written to the live target path.', 'zignites-sentinel' ), $item )
						)
					),
				);
			}
		}

		$journal[] = $this->build_journal_entry( 'item', $item['label'], 'payload_written', 'pass', __( 'The staged payload was written to the live target path.', 'zignites-sentinel' ), array_merge( $item, array( 'backup_path' => file_exists( $backup_path ) ? $backup_path : '', 'target_path' => $target_path ) ) );

		return array(
			'label'       => $item['label'],
			'status'      => 'pass',
			'action'      => $item['action'],
			'message'     => __( 'The staged payload was written to the live target path.', 'zignites-sentinel' ),
			'type'        => isset( $item['type'] ) ? $item['type'] : '',
			'target_path' => $target_path,
			'backup_path' => file_exists( $backup_path ) ? $backup_path : '',
			'journal'     => $journal,
		);
	}

	/**
	 * Finalize the execution result.
	 *
	 * @param array  $snapshot      Snapshot row.
	 * @param array  $checks        Execution checks.
	 * @param array  $item_results  Item results.
	 * @param string $backup_root   Backup root.
	 * @param string $stage_path    Stage path.
	 * @param bool   $blocked_early Whether execution was blocked before writes.
	 * @return array
	 */
	protected function finalize_result( array $snapshot, array $checks, array $item_results, $backup_root, $stage_path, $blocked_early, array $health = array(), array $journal = array(), $run_id = '', $resumed_run = false ) {
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

		if ( ! empty( $health['checks'] ) && is_array( $health['checks'] ) ) {
			foreach ( $health['checks'] as $check ) {
				if ( isset( $summary[ $check['status'] ] ) ) {
					++$summary[ $check['status'] ];
				}
			}
		}

		$status = 'completed';

		if ( ! empty( $summary['fail'] ) ) {
			$status = $blocked_early ? 'blocked' : 'partial';
		} elseif ( ! empty( $summary['warning'] ) ) {
			$status = 'partial';
		}

		return array(
			'generated_at' => current_time( 'mysql', true ),
			'snapshot_id'  => isset( $snapshot['id'] ) ? (int) $snapshot['id'] : 0,
			'status'       => $status,
			'checks'       => $checks,
			'items'        => $item_results,
			'summary'      => $summary,
			'backup_root'  => $backup_root,
			'stage_path'   => $stage_path,
			'health_verification' => $health,
			'journal'      => $journal,
			'run_id'       => sanitize_text_field( (string) $run_id ),
			'resumed_run'  => (bool) $resumed_run,
			'rollback_confirmation_phrase' => sprintf( 'ROLLBACK SNAPSHOT %d', isset( $snapshot['id'] ) ? (int) $snapshot['id'] : 0 ),
			'note'         => $this->build_note( $status, $health, $resumed_run ),
		);
	}

	/**
	 * Create a backup root for a live restore execution.
	 *
	 * @param int $snapshot_id Snapshot ID.
	 * @return string
	 */
	protected function create_backup_root( $snapshot_id ) {
		$base_dir = $this->get_backup_root_directory();

		if ( '' === $base_dir ) {
			return '';
		}

		if ( ! is_dir( $base_dir ) && ! wp_mkdir_p( $base_dir ) ) {
			return '';
		}

		$path = trailingslashit( $base_dir ) . 'snapshot-' . absint( $snapshot_id ) . '-' . gmdate( 'YmdHis' );

		if ( wp_mkdir_p( $path ) ) {
			return $path;
		}

		return '';
	}

	/**
	 * Resolve a backup root for execution or resume.
	 *
	 * @param int   $snapshot_id  Snapshot ID.
	 * @param array $resume_state Resume state.
	 * @return string
	 */
	protected function resolve_backup_root( $snapshot_id, array $resume_state ) {
		if ( ! empty( $resume_state['backup_root'] ) && is_dir( $resume_state['backup_root'] ) ) {
			return wp_normalize_path( $resume_state['backup_root'] );
		}

		return $this->create_backup_root( $snapshot_id );
	}

	/**
	 * Get the backup root directory.
	 *
	 * @return string
	 */
	protected function get_backup_root_directory() {
		$uploads = wp_upload_dir();

		if ( ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) ) {
			return '';
		}

		return trailingslashit( wp_normalize_path( $uploads['basedir'] ) ) . self::BACKUP_DIRECTORY;
	}

	/**
	 * Fetch a matching execution checkpoint for the current run.
	 *
	 * @param array  $snapshot  Snapshot row.
	 * @param array  $artifacts Artifact rows.
	 * @param string $run_id    Run ID.
	 * @return array
	 */
	protected function get_execution_checkpoint( array $snapshot, array $artifacts, $run_id ) {
		if ( ! $this->checkpoint_store || '' === (string) $run_id ) {
			return array();
		}

		return $this->checkpoint_store->get_matching_execution_checkpoint( $snapshot, $artifacts, $run_id );
	}

	/**
	 * Store the latest execution checkpoint state.
	 *
	 * @param array  $snapshot    Snapshot row.
	 * @param array  $artifacts   Artifact rows.
	 * @param string $run_id      Run ID.
	 * @param array  $checkpoint  Checkpoint state.
	 * @return void
	 */
	protected function store_execution_checkpoint( array $snapshot, array $artifacts, $run_id, array $checkpoint ) {
		if ( ! $this->checkpoint_store || '' === (string) $run_id ) {
			return;
		}

		$this->checkpoint_store->store_execution_checkpoint( $snapshot, $artifacts, $run_id, $checkpoint );
	}

	/**
	 * Resolve a reusable execution stage.
	 *
	 * @param array $snapshot             Snapshot row.
	 * @param array $artifacts            Artifact rows.
	 * @param array $execution_checkpoint Execution checkpoint.
	 * @return array
	 */
	protected function resolve_execution_stage( array $snapshot, array $artifacts, array $execution_checkpoint ) {
		if ( ! empty( $execution_checkpoint['stage_ready'] ) && ! empty( $execution_checkpoint['stage_path'] ) && is_dir( $execution_checkpoint['stage_path'] ) ) {
			return array(
				'success'          => true,
				'reused'           => true,
				'stage_path'       => wp_normalize_path( $execution_checkpoint['stage_path'] ),
				'stage_check'      => array(
					'label'   => __( 'Stage directory', 'zignites-sentinel' ),
					'status'  => 'pass',
					'message' => __( 'The previous execution stage directory was reused.', 'zignites-sentinel' ),
				),
				'extraction_check' => array(
					'label'   => __( 'Package extraction', 'zignites-sentinel' ),
					'status'  => 'pass',
					'message' => __( 'The previous execution stage contents were reused.', 'zignites-sentinel' ),
				),
			);
		}

		$stage           = $this->staging_manager->extract_package_to_stage( $snapshot, $artifacts );
		$stage['reused'] = false;

		return $stage;
	}

	/**
	 * Count remaining plan items not already completed in the journal.
	 *
	 * @param array $items        Plan items.
	 * @param array $resume_state Resume state.
	 * @return int
	 */
	protected function count_pending_plan_items( array $items, array $resume_state ) {
		$pending = 0;

		foreach ( $items as $item ) {
			$item_key = $this->build_item_key( $item );

			if ( empty( $resume_state['completed_items'][ $item_key ] ) ) {
				++$pending;
			}
		}

		return $pending;
	}

	/**
	 * Finalize execution stage checkpoint handling after execution.
	 *
	 * @param array  $snapshot   Snapshot row.
	 * @param array  $artifacts  Artifact rows.
	 * @param string $run_id     Run ID.
	 * @param string $stage_path Stage path.
	 * @param array  $result     Execution result.
	 * @param array  $health     Health result.
	 * @return array
	 */
	protected function finalize_stage_checkpoint( array $snapshot, array $artifacts, $run_id, $stage_path, array $result, array $health ) {
		$status = isset( $result['status'] ) ? (string) $result['status'] : 'partial';

		if ( 'partial' === $status && '' !== (string) $stage_path && is_dir( $stage_path ) ) {
			$this->store_execution_checkpoint(
				$snapshot,
				$artifacts,
				$run_id,
				array(
					'stage_ready'         => true,
					'stage_path'          => wp_normalize_path( $stage_path ),
					'health_completed'    => ! empty( $health ),
					'health_verification' => $health,
				)
			);

			return array(
				'phase'   => 'deferred',
				'status'  => 'warning',
				'message' => __( 'The execution stage directory was preserved for a future resume attempt.', 'zignites-sentinel' ),
			);
		}

		$cleanup_ok = $this->staging_manager->cleanup_stage_directory( $stage_path );

		if ( $this->checkpoint_store ) {
			$this->checkpoint_store->clear_execution_checkpoint( isset( $snapshot['id'] ) ? (int) $snapshot['id'] : 0, $run_id );
		}

		return array(
			'phase'   => 'completed',
			'status'  => $cleanup_ok ? 'pass' : 'warning',
			'message' => $cleanup_ok
				? __( 'The execution stage directory was removed.', 'zignites-sentinel' )
				: __( 'The execution stage directory could not be fully removed.', 'zignites-sentinel' ),
		);
	}

	/**
	 * Map health verification status to a check status.
	 *
	 * @param string $status Health status.
	 * @return string
	 */
	protected function map_health_status_to_check_status( $status ) {
		$status = sanitize_key( (string) $status );

		if ( 'unhealthy' === $status ) {
			return 'fail';
		}

		if ( 'degraded' === $status ) {
			return 'warning';
		}

		return 'pass';
	}

	/**
	 * Resolve the restore plan for execution or resume.
	 *
	 * @param array $snapshot         Snapshot row.
	 * @param array $artifacts        Artifact rows.
	 * @param array $last_plan_result Last restore plan result.
	 * @param array $resume_state     Resume state.
	 * @return array
	 */
	protected function resolve_execution_plan( array $snapshot, array $artifacts, array $last_plan_result, array $resume_state ) {
		$snapshot_id = isset( $snapshot['id'] ) ? (int) $snapshot['id'] : 0;

		if (
			! empty( $resume_state['entries'] ) &&
			! empty( $last_plan_result['snapshot_id'] ) &&
			(int) $last_plan_result['snapshot_id'] === $snapshot_id &&
			! empty( $last_plan_result['status'] ) &&
			'blocked' !== $last_plan_result['status'] &&
			! empty( $last_plan_result['items'] ) &&
			is_array( $last_plan_result['items'] )
		) {
			$last_plan_result['resumed_plan'] = true;

			return $last_plan_result;
		}

		$plan = $this->planner->build_plan( $snapshot, $artifacts );
		$plan['resumed_plan'] = false;

		if ( $this->checkpoint_store && ! empty( $plan['status'] ) && 'blocked' !== $plan['status'] ) {
			$this->checkpoint_store->store_plan_checkpoint( $snapshot, $artifacts, $plan );
		}

		return $plan;
	}

	/**
	 * Build resume state from persisted journal entries.
	 *
	 * @param int    $snapshot_id Snapshot ID.
	 * @param string $run_id      Run ID.
	 * @return array
	 */
	protected function get_resume_state( $snapshot_id, $run_id ) {
		$state = array(
			'entries'         => array(),
			'backup_root'     => '',
			'completed_items' => array(),
			'backed_up_items' => array(),
		);

		if ( ! $this->journal_recorder || '' === $run_id ) {
			return $state;
		}

		$state['entries'] = $this->journal_recorder->get_entries( self::JOURNAL_SOURCE, $snapshot_id, $run_id );

		foreach ( $state['entries'] as $entry ) {
			$context  = isset( $entry['context'] ) && is_array( $entry['context'] ) ? $entry['context'] : array();
			$item_key = $this->extract_item_key( $context );

			if ( ! empty( $entry['scope'] ) && 'backup' === $entry['scope'] && ! empty( $entry['phase'] ) && 'root' === $entry['phase'] && ! empty( $entry['status'] ) && 'pass' === $entry['status'] && ! empty( $context['backup_root'] ) ) {
				$state['backup_root'] = wp_normalize_path( $context['backup_root'] );
			}

			if ( '' === $item_key ) {
				continue;
			}

			if ( ! empty( $entry['phase'] ) && 'backup_moved' === $entry['phase'] && ! empty( $entry['status'] ) && 'pass' === $entry['status'] ) {
				$state['backed_up_items'][ $item_key ] = $context;
			}

			if ( ! empty( $entry['phase'] ) && 'payload_written' === $entry['phase'] && ! empty( $entry['status'] ) && 'pass' === $entry['status'] ) {
				$state['completed_items'][ $item_key ] = $context;
			}
		}

		return $state;
	}

	/**
	 * Normalize an item with a stable item key.
	 *
	 * @param array $item Plan item.
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
					isset( $item['type'] ) ? (string) $item['type'] : '',
					isset( $item['label'] ) ? (string) $item['label'] : '',
					isset( $item['package_path'] ) ? wp_normalize_path( (string) $item['package_path'] ) : '',
					isset( $item['target_path'] ) ? wp_normalize_path( (string) $item['target_path'] ) : '',
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

		if ( empty( $context['package_path'] ) && empty( $context['target_path'] ) ) {
			return '';
		}

		return $this->build_item_key( $context );
	}

	/**
	 * Append a journal entry locally and persist it when available.
	 *
	 * @param array  $journal     Journal array.
	 * @param int    $snapshot_id Snapshot ID.
	 * @param string $run_id      Run ID.
	 * @param array  $entry       Journal entry.
	 * @return void
	 */
	protected function append_journal_entry( array &$journal, $snapshot_id, $run_id, array $entry ) {
		$journal[] = $entry;

		if ( $this->journal_recorder && '' !== $run_id ) {
			$this->journal_recorder->record( self::JOURNAL_SOURCE, $snapshot_id, $run_id, $entry );
		}
	}

	/**
	 * Append a terminal run completion entry.
	 *
	 * @param array  $journal     Journal array.
	 * @param int    $snapshot_id Snapshot ID.
	 * @param string $run_id      Run ID.
	 * @param array  $result      Final execution result.
	 * @return void
	 */
	protected function append_run_completion_entry( array &$journal, $snapshot_id, $run_id, array $result ) {
		$this->append_journal_entry(
			$journal,
			$snapshot_id,
			$run_id,
			$this->build_journal_entry(
				'run',
				'execution',
				'completed',
				isset( $result['status'] ) ? (string) $result['status'] : 'partial',
				isset( $result['note'] ) ? (string) $result['note'] : __( 'Live restore execution finished.', 'zignites-sentinel' ),
				array(
					'backup_root' => isset( $result['backup_root'] ) ? (string) $result['backup_root'] : '',
					'summary'     => isset( $result['summary'] ) && is_array( $result['summary'] ) ? $result['summary'] : array(),
				)
			)
		);
	}

	/**
	 * Delete a path recursively or as a single file.
	 *
	 * @param string $path Path.
	 * @return bool
	 */
	protected function delete_path( $path ) {
		if ( '' === $path || ! file_exists( $path ) ) {
			return true;
		}

		return is_dir( $path ) ? $this->delete_directory_recursive( $path ) : ( wp_delete_file( $path ) || ! file_exists( $path ) );
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
	 * Write an execution log when logging is available.
	 *
	 * @param array  $snapshot     Snapshot row.
	 * @param array  $item_results Item results.
	 * @param string $backup_root  Backup root.
	 * @return void
	 */
	protected function log_execution( array $snapshot, array $item_results, $backup_root, $run_id, $resumed_run ) {
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
			'restore_execution_completed',
			$failures > 0 ? 'warning' : 'info',
			'restore-execution',
			$resumed_run ? __( 'Live restore execution resume completed.', 'zignites-sentinel' ) : __( 'Live restore execution completed.', 'zignites-sentinel' ),
			array(
				'snapshot_id' => isset( $snapshot['id'] ) ? (int) $snapshot['id'] : 0,
				'backup_root' => $backup_root,
				'item_count'  => count( $item_results ),
				'failures'    => $failures,
				'run_id'      => sanitize_text_field( (string) $run_id ),
				'resumed_run' => (bool) $resumed_run,
			)
		);
	}

	/**
	 * Build an execution note.
	 *
	 * @param string $status Execution status.
	 * @return string
	 */
	protected function build_note( $status, array $health, $resumed_run = false ) {
		$prefix = $resumed_run ? __( 'Resume execution: ', 'zignites-sentinel' ) : '';

		if ( 'blocked' === $status ) {
			return $prefix . __( 'Live restore execution was blocked by safety gates.', 'zignites-sentinel' );
		}

		if ( 'partial' === $status ) {
			if ( ! empty( $health['status'] ) && 'unhealthy' === $health['status'] ) {
				return $prefix . __( 'Live restore execution completed, but the site failed post-restore health verification. Use rollback immediately.', 'zignites-sentinel' );
			}

			return $prefix . __( 'Live restore execution ran with warnings or failures. Review the backup root and execution log immediately.', 'zignites-sentinel' );
		}

		if ( ! empty( $health['status'] ) && 'degraded' === $health['status'] ) {
			return $prefix . __( 'Live restore execution completed, but post-restore health verification returned warnings.', 'zignites-sentinel' );
		}

		return $prefix . __( 'Live restore execution completed and passed basic post-restore health verification.', 'zignites-sentinel' );
	}

	/**
	 * Build a journal entry for restore execution.
	 *
	 * @param string $scope   Journal scope.
	 * @param string $label   Journal label.
	 * @param string $phase   Execution phase.
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
