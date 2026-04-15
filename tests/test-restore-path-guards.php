<?php
/**
 * Focused tests for restore and rollback path boundary guards.
 */

require_once __DIR__ . '/bootstrap.php';

use Zignites\Sentinel\Logging\Logger;
use Zignites\Sentinel\Snapshots\ArtifactStorageGuard;
use Zignites\Sentinel\Snapshots\RestoreExecutionPlanner;
use Zignites\Sentinel\Snapshots\RestoreExecutor;
use Zignites\Sentinel\Snapshots\RestoreHealthVerifier;
use Zignites\Sentinel\Snapshots\RestoreRollbackManager;
use Zignites\Sentinel\Snapshots\RestoreStagingManager;

class ZNTS_Testable_Restore_Path_Guard_Executor extends RestoreExecutor {
	public function __construct() {
		parent::__construct(
			new RestoreStagingManager(),
			new RestoreExecutionPlanner(),
			new RestoreHealthVerifier(),
			new Logger(),
			null,
			null,
			new ArtifactStorageGuard()
		);
	}

	public function expose_validate_execution_item_paths( array $item, $stage_path, $backup_root ) {
		return $this->validate_execution_item_paths( $item, $stage_path, $backup_root );
	}
}

class ZNTS_Testable_Restore_Path_Guard_Rollback_Manager extends RestoreRollbackManager {
	public function __construct() {
		parent::__construct( new Logger(), null, null, new ArtifactStorageGuard() );
	}

	public function expose_validate_rollback_item_paths( array $item ) {
		return $this->validate_rollback_item_paths( $item );
	}

	public function expose_check_execution_context( array $snapshot, array $restore_execution ) {
		return $this->check_execution_context( $snapshot, $restore_execution );
	}
}

function znts_test_restore_executor_rejects_targets_outside_plugin_and_theme_roots() {
	$executor = new ZNTS_Testable_Restore_Path_Guard_Executor();
	$item     = array(
		'type'         => 'plugin',
		'label'        => 'Unsafe Plugin',
		'package_path' => 'plugins/example-plugin',
		'target_path'  => 'D:/outside/unsafe-plugin',
	);

	$error = $executor->expose_validate_execution_item_paths(
		$item,
		'D:/uploads/zignites-sentinel/staging/snapshot-9-20260415120000',
		'D:/uploads/zignites-sentinel/restore-backups/snapshot-9-20260415120000'
	);

	znts_assert_same(
		'The restore target is outside the active plugin or theme directories.',
		$error,
		'Restore executor should reject target paths outside the live plugin and theme roots.'
	);
}

function znts_test_restore_executor_rejects_backup_roots_outside_storage() {
	$executor = new ZNTS_Testable_Restore_Path_Guard_Executor();
	$item     = array(
		'type'         => 'theme',
		'label'        => 'Example Theme',
		'package_path' => 'themes/example-theme',
		'target_path'  => 'D:/themes/example-theme',
	);

	$error = $executor->expose_validate_execution_item_paths(
		$item,
		'D:/uploads/zignites-sentinel/staging/snapshot-9-20260415120000',
		'D:/outside/restore-backups'
	);

	znts_assert_same(
		'The restore backup directory is outside Sentinel storage and cannot be used.',
		$error,
		'Restore executor should reject backup roots outside the protected restore-backup directory.'
	);
}

function znts_test_restore_rollback_manager_rejects_backup_paths_outside_storage() {
	$manager = new ZNTS_Testable_Restore_Path_Guard_Rollback_Manager();
	$item    = array(
		'label'       => 'Example Plugin',
		'action'      => 'restore-backup',
		'target_path' => 'D:/plugins/example-plugin',
		'backup_path' => 'D:/outside/restore-backups/example-plugin',
	);

	znts_assert_same(
		'The rollback backup path is outside Sentinel storage and cannot be used.',
		$manager->expose_validate_rollback_item_paths( $item ),
		'Rollback manager should reject backup paths outside Sentinel storage.'
	);
}

function znts_test_restore_rollback_manager_blocks_invalid_execution_context() {
	$manager = new ZNTS_Testable_Restore_Path_Guard_Rollback_Manager();
	$result  = $manager->expose_check_execution_context(
		array( 'id' => 15 ),
		array(
			'snapshot_id' => 15,
			'backup_root' => 'D:/outside/restore-backups',
			'items'       => array(
				array(
					'target_path' => 'D:/plugins/example-plugin',
					'backup_path' => 'D:/outside/restore-backups/example-plugin',
				),
			),
		)
	);

	znts_assert_same(
		'fail',
		$result['status'],
		'Rollback manager should fail execution-context checks when the stored backup root is outside Sentinel storage.'
	);
}
