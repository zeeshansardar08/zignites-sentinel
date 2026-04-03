<?php
/**
 * Focused tests for RestoreCheckpointStore execution state behavior.
 */

require_once __DIR__ . '/bootstrap.php';

use Zignites\Sentinel\Snapshots\RestoreCheckpointStore;

function znts_test_execution_checkpoint_merges_item_state_with_stage_and_health() {
	$GLOBALS['znts_test_options'] = array();

	$store = new RestoreCheckpointStore();
	$snapshot = array(
		'id' => 44,
	);
	$artifacts = array(
		array(
			'artifact_type' => 'package',
			'source_path'   => 'uploads/snapshot-44.zip',
			'metadata'      => wp_json_encode(
				array(
					'sha256'          => 'package-hash',
					'manifest_sha256' => 'manifest-hash',
					'size_bytes'      => 2048,
				)
			),
		),
	);

	$store->store_execution_checkpoint(
		$snapshot,
		$artifacts,
		'run-44',
		array(
			'stage_ready' => true,
			'stage_path'  => 'C:\\stage\\snapshot-44',
		)
	);

	$store->store_execution_item_checkpoint(
		$snapshot,
		$artifacts,
		'run-44',
		'item-1',
		array(
			'phase'            => 'backup_moved',
			'status'           => 'pass',
			'backup_completed' => true,
			'write_completed'  => false,
			'backup_path'      => 'C:\\backup\\plugin-a',
			'target_path'      => 'C:\\live\\plugin-a',
		)
	);

	$store->store_execution_checkpoint(
		$snapshot,
		$artifacts,
		'run-44',
		array(
			'health_completed'    => true,
			'health_verification' => array(
				'status' => 'healthy',
			),
		)
	);

	$checkpoint = $store->get_matching_execution_checkpoint( $snapshot, $artifacts, 'run-44' );

	znts_assert_true( ! empty( $checkpoint['stage_ready'] ), 'Stage-ready state should survive later checkpoint updates.' );
	znts_assert_true( ! empty( $checkpoint['health_completed'] ), 'Health-completed state should be stored.' );
	znts_assert_true( ! empty( $checkpoint['items']['item-1']['backup_completed'] ), 'Per-item backup checkpoint should be preserved.' );
	znts_assert_same( 'backup_moved', $checkpoint['items']['item-1']['phase'], 'Stored item phase should remain available.' );
}

function znts_test_execution_checkpoint_updates_item_write_completion() {
	$GLOBALS['znts_test_options'] = array();

	$store = new RestoreCheckpointStore();
	$snapshot = array(
		'id' => 45,
	);
	$artifacts = array(
		array(
			'artifact_type' => 'package',
			'source_path'   => 'uploads/snapshot-45.zip',
			'metadata'      => wp_json_encode(
				array(
					'sha256'          => 'package-hash-45',
					'manifest_sha256' => 'manifest-hash-45',
					'size_bytes'      => 4096,
				)
			),
		),
	);

	$store->store_execution_item_checkpoint(
		$snapshot,
		$artifacts,
		'run-45',
		'item-1',
		array(
			'phase'            => 'backup_moved',
			'status'           => 'pass',
			'backup_completed' => true,
			'write_completed'  => false,
			'backup_path'      => 'C:\\backup\\theme-a',
			'target_path'      => 'C:\\live\\theme-a',
		)
	);

	$store->store_execution_item_checkpoint(
		$snapshot,
		$artifacts,
		'run-45',
		'item-1',
		array(
			'phase'            => 'payload_written',
			'status'           => 'pass',
			'backup_completed' => true,
			'write_completed'  => true,
			'backup_path'      => 'C:\\backup\\theme-a',
			'target_path'      => 'C:\\live\\theme-a',
		)
	);

	$checkpoint = $store->get_matching_execution_checkpoint( $snapshot, $artifacts, 'run-45' );

	znts_assert_true( ! empty( $checkpoint['items']['item-1']['write_completed'] ), 'Later item checkpoints should update write completion.' );
	znts_assert_same( 'payload_written', $checkpoint['items']['item-1']['phase'], 'Latest item phase should replace the previous phase.' );
}

function znts_test_execution_checkpoint_requires_matching_package_fingerprint() {
	$GLOBALS['znts_test_options'] = array();

	$store = new RestoreCheckpointStore();
	$snapshot = array(
		'id' => 46,
	);
	$stored_artifacts = array(
		array(
			'artifact_type' => 'package',
			'source_path'   => 'uploads/snapshot-46.zip',
			'metadata'      => wp_json_encode(
				array(
					'sha256'          => 'package-hash-46',
					'manifest_sha256' => 'manifest-hash-46',
					'size_bytes'      => 8192,
				)
			),
		),
	);
	$changed_artifacts = array(
		array(
			'artifact_type' => 'package',
			'source_path'   => 'uploads/snapshot-46.zip',
			'metadata'      => wp_json_encode(
				array(
					'sha256'          => 'package-hash-46-new',
					'manifest_sha256' => 'manifest-hash-46',
					'size_bytes'      => 8192,
				)
			),
		),
	);

	$store->store_execution_checkpoint(
		$snapshot,
		$stored_artifacts,
		'run-46',
		array(
			'stage_ready' => true,
			'items'       => array(
				'item-1' => array(
					'write_completed' => true,
				),
			),
		)
	);

	$checkpoint = $store->get_matching_execution_checkpoint( $snapshot, $changed_artifacts, 'run-46' );

	znts_assert_same( array(), $checkpoint, 'Execution checkpoints should not be reused when the package fingerprint has changed.' );
}

function znts_test_rollback_checkpoint_merges_item_completion_state() {
	$GLOBALS['znts_test_options'] = array();

	$store = new RestoreCheckpointStore();
	$snapshot = array(
		'id' => 47,
	);

	$store->store_rollback_checkpoint(
		$snapshot,
		'rollback-47',
		array(
			'backup_root' => 'C:\\backup\\snapshot-47',
		)
	);

	$store->store_rollback_item_checkpoint(
		$snapshot,
		'rollback-47',
		'item-rollback-1',
		array(
			'phase'       => 'target_removed',
			'status'      => 'pass',
			'completed'   => false,
			'target_path' => 'C:\\live\\plugin-b',
		)
	);

	$store->store_rollback_item_checkpoint(
		$snapshot,
		'rollback-47',
		'item-rollback-1',
		array(
			'phase'       => 'completed',
			'status'      => 'pass',
			'completed'   => true,
			'target_path' => 'C:\\live\\plugin-b',
			'backup_path' => 'C:\\backup\\plugin-b',
		)
	);

	$checkpoint = $store->get_rollback_checkpoint( 47, 'rollback-47' );

	znts_assert_same( 'C:\\backup\\snapshot-47', $checkpoint['checkpoint']['backup_root'], 'Rollback checkpoint should preserve backup root context.' );
	znts_assert_true( ! empty( $checkpoint['checkpoint']['items']['item-rollback-1']['completed'] ), 'Rollback item completion should be merged into the checkpoint.' );
	znts_assert_same( 'completed', $checkpoint['checkpoint']['items']['item-rollback-1']['phase'], 'Rollback item phase should update to the latest checkpoint.' );
}

function znts_test_rollback_checkpoint_can_be_cleared_by_snapshot_and_run() {
	$GLOBALS['znts_test_options'] = array();

	$store = new RestoreCheckpointStore();
	$snapshot = array(
		'id' => 48,
	);

	$store->store_rollback_checkpoint(
		$snapshot,
		'rollback-48',
		array(
			'backup_root' => 'C:\\backup\\snapshot-48',
		)
	);

	znts_assert_true( ! empty( $store->get_rollback_checkpoint( 48, 'rollback-48' ) ), 'Rollback checkpoint should exist before clearing.' );

	$store->clear_rollback_checkpoint( 48, 'rollback-48' );

	znts_assert_same( array(), $store->get_rollback_checkpoint( 48, 'rollback-48' ), 'Rollback checkpoint should be cleared for the matching snapshot and run.' );
}
