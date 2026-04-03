<?php
/**
 * Focused tests for mixed journal and checkpoint resume state resolution.
 */

require_once __DIR__ . '/bootstrap.php';

use Zignites\Sentinel\Logging\Logger;
use Zignites\Sentinel\Snapshots\RestoreCheckpointStore;
use Zignites\Sentinel\Snapshots\RestoreExecutionPlanner;
use Zignites\Sentinel\Snapshots\RestoreExecutor;
use Zignites\Sentinel\Snapshots\RestoreHealthVerifier;
use Zignites\Sentinel\Snapshots\RestoreJournalRecorder;
use Zignites\Sentinel\Snapshots\RestoreRollbackManager;
use Zignites\Sentinel\Snapshots\RestoreStagingManager;

class ZNTS_Fake_Resume_Journal_Recorder extends RestoreJournalRecorder {
	public $entries = array();

	public function get_entries( $source, $snapshot_id, $run_id ) {
		return $this->entries;
	}
}

class ZNTS_Testable_Restore_Executor extends RestoreExecutor {
	public function __construct( RestoreJournalRecorder $journal_recorder, RestoreCheckpointStore $checkpoint_store ) {
		parent::__construct(
			new RestoreStagingManager(),
			new RestoreExecutionPlanner(),
			new RestoreHealthVerifier(),
			new Logger(),
			$journal_recorder,
			$checkpoint_store
		);
	}

	public function expose_get_resume_state( $snapshot_id, $run_id, array $execution_checkpoint = array() ) {
		return $this->get_resume_state( $snapshot_id, $run_id, $execution_checkpoint );
	}
}

class ZNTS_Testable_Restore_Rollback_Manager extends RestoreRollbackManager {
	public function __construct( RestoreJournalRecorder $journal_recorder, RestoreCheckpointStore $checkpoint_store ) {
		parent::__construct( new Logger(), $journal_recorder, $checkpoint_store );
	}

	public function expose_get_resume_state( $snapshot_id, $run_id, array $rollback_checkpoint = array() ) {
		return $this->get_resume_state( $snapshot_id, $run_id, $rollback_checkpoint );
	}
}

function znts_test_restore_resume_state_merges_journal_and_checkpoint_items() {
	$GLOBALS['znts_test_options'] = array();

	$store      = new RestoreCheckpointStore();
	$journal    = new ZNTS_Fake_Resume_Journal_Recorder();
	$executor   = new ZNTS_Testable_Restore_Executor( $journal, $store );
	$snapshot   = array( 'id' => 61 );
	$artifacts  = array(
		array(
			'artifact_type' => 'package',
			'source_path'   => 'uploads/snapshot-61.zip',
			'metadata'      => wp_json_encode(
				array(
					'sha256'          => 'package-hash-61',
					'manifest_sha256' => 'manifest-hash-61',
					'size_bytes'      => 6144,
				)
			),
		),
	);

	$store->store_execution_checkpoint(
		$snapshot,
		$artifacts,
		'run-61',
		array(
			'backup_root' => 'C:\\backup\\checkpoint-root',
			'items'       => array(
				'item-checkpoint' => array(
					'item_key'          => 'item-checkpoint',
					'phase'             => 'payload_written',
					'status'            => 'pass',
					'backup_completed'  => true,
					'write_completed'   => true,
					'backup_path'       => 'C:\\backup\\plugin-checkpoint',
					'target_path'       => 'C:\\live\\plugin-checkpoint',
				),
			),
		)
	);

	$journal->entries = array(
		array(
			'scope'   => 'backup',
			'phase'   => 'root',
			'status'  => 'pass',
			'context' => array(
				'backup_root' => 'C:\\backup\\journal-root',
			),
		),
		array(
			'scope'   => 'item',
			'phase'   => 'backup_moved',
			'status'  => 'pass',
			'context' => array(
				'item_key'    => 'item-journal-backed-up',
				'backup_path' => 'C:\\backup\\plugin-journal',
				'target_path' => 'C:\\live\\plugin-journal',
			),
		),
		array(
			'scope'   => 'item',
			'phase'   => 'payload_written',
			'status'  => 'pass',
			'context' => array(
				'item_key'    => 'item-journal-complete',
				'backup_path' => 'C:\\backup\\theme-journal',
				'target_path' => 'C:\\live\\theme-journal',
			),
		),
	);

	$checkpoint = $store->get_matching_execution_checkpoint( $snapshot, $artifacts, 'run-61' );
	$state      = $executor->expose_get_resume_state( 61, 'run-61', $checkpoint );

	znts_assert_same( 'C:/backup/journal-root', $state['backup_root'], 'Journal backup root should take precedence over checkpoint fallback.' );
	znts_assert_true( isset( $state['completed_items']['item-journal-complete'] ), 'Completed journal items should remain resumable.' );
	znts_assert_true( isset( $state['completed_items']['item-checkpoint'] ), 'Checkpoint-completed items should supplement journal state.' );
	znts_assert_true( isset( $state['backed_up_items']['item-journal-backed-up'] ), 'Journal backup progress should be preserved.' );
	znts_assert_true( isset( $state['checkpoint_items']['item-checkpoint'] ), 'Raw checkpoint item state should remain available.' );
}

function znts_test_rollback_resume_state_merges_journal_and_checkpoint_items() {
	$GLOBALS['znts_test_options'] = array();

	$store    = new RestoreCheckpointStore();
	$journal  = new ZNTS_Fake_Resume_Journal_Recorder();
	$rollback = new ZNTS_Testable_Restore_Rollback_Manager( $journal, $store );
	$snapshot = array( 'id' => 62 );

	$store->store_rollback_checkpoint(
		$snapshot,
		'rollback-62',
		array(
			'backup_root' => 'C:\\backup\\rollback-62',
			'items'       => array(
				'item-checkpoint' => array(
					'item_key'    => 'item-checkpoint',
					'phase'       => 'completed',
					'status'      => 'pass',
					'completed'   => true,
					'backup_path' => 'C:\\backup\\plugin-checkpoint',
					'target_path' => 'C:\\live\\plugin-checkpoint',
				),
			),
		)
	);

	$journal->entries = array(
		array(
			'scope'   => 'item',
			'phase'   => 'completed',
			'status'  => 'pass',
			'context' => array(
				'item_key'    => 'item-journal-complete',
				'backup_path' => 'C:\\backup\\theme-journal',
				'target_path' => 'C:\\live\\theme-journal',
			),
		),
	);

	$checkpoint = $store->get_rollback_checkpoint( 62, 'rollback-62' );
	$state      = $rollback->expose_get_resume_state( 62, 'rollback-62', $checkpoint );

	znts_assert_true( isset( $state['completed_items']['item-journal-complete'] ), 'Rollback journal completions should remain resumable.' );
	znts_assert_true( isset( $state['completed_items']['item-checkpoint'] ), 'Rollback checkpoint completions should supplement journal state.' );
	znts_assert_true( isset( $state['checkpoint_items']['item-checkpoint'] ), 'Rollback checkpoint item state should remain available.' );
}
