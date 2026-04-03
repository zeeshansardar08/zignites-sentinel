<?php
/**
 * Snapshot retention maintenance.
 *
 * @package ZignitesSentinel
 */

namespace Zignites\Sentinel\Snapshots;

use Zignites\Sentinel\Logging\Logger;

defined( 'ABSPATH' ) || exit;

class SnapshotMaintenance {

	/**
	 * Snapshot repository.
	 *
	 * @var SnapshotRepository
	 */
	protected $repository;

	/**
	 * Snapshot artifact repository.
	 *
	 * @var SnapshotArtifactRepository
	 */
	protected $artifact_repository;

	/**
	 * Export manager.
	 *
	 * @var SnapshotExportManager
	 */
	protected $export_manager;

	/**
	 * Package manager.
	 *
	 * @var SnapshotPackageManager
	 */
	protected $package_manager;

	/**
	 * Restore staging manager.
	 *
	 * @var RestoreStagingManager
	 */
	protected $restore_staging_manager;

	/**
	 * Restore checkpoint store.
	 *
	 * @var RestoreCheckpointStore
	 */
	protected $restore_checkpoint_store;

	/**
	 * Logger.
	 *
	 * @var Logger|null
	 */
	protected $logger;

	/**
	 * Constructor.
	 *
	 * @param SnapshotRepository $repository Snapshot repository.
	 */
	public function __construct(
		SnapshotRepository $repository,
		SnapshotArtifactRepository $artifact_repository,
		SnapshotExportManager $export_manager,
		SnapshotPackageManager $package_manager,
		RestoreStagingManager $restore_staging_manager,
		RestoreCheckpointStore $restore_checkpoint_store,
		Logger $logger = null
	) {
		$this->repository          = $repository;
		$this->artifact_repository = $artifact_repository;
		$this->export_manager      = $export_manager;
		$this->package_manager     = $package_manager;
		$this->restore_staging_manager = $restore_staging_manager;
		$this->restore_checkpoint_store = $restore_checkpoint_store;
		$this->logger              = $logger;
	}

	/**
	 * Register cleanup hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'znts_cleanup_snapshots', array( $this, 'cleanup_old_snapshots' ) );
	}

	/**
	 * Remove snapshots older than the configured retention window.
	 *
	 * @return void
	 */
	public function cleanup_old_snapshots() {
		$settings       = get_option( ZNTS_OPTION_SETTINGS, array() );
		$retention_days = isset( $settings['snapshot_retention_days'] ) ? absint( $settings['snapshot_retention_days'] ) : 30;

		if ( $retention_days < 1 ) {
			return;
		}

		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $retention_days * DAY_IN_SECONDS ) );
		$ids    = $this->repository->get_ids_older_than( $cutoff );

		if ( ! empty( $ids ) ) {
			$artifacts = $this->artifact_repository->get_by_snapshot_ids( $ids );
			$this->export_manager->delete_artifact_files( $artifacts );
			$this->package_manager->delete_artifact_files( $artifacts );
			$this->artifact_repository->delete_by_snapshot_ids( $ids );
		}

		$this->repository->delete_older_than( $cutoff );
		$this->cleanup_abandoned_restore_stages();
	}

	/**
	 * Remove preserved stage directories that do not belong to the active execution checkpoint.
	 *
	 * @return void
	 */
	protected function cleanup_abandoned_restore_stages() {
		$checkpoint = $this->restore_checkpoint_store->get_execution_checkpoint( 0 );
		$preserve   = array();

		if ( ! empty( $checkpoint['checkpoint']['stage_path'] ) && is_dir( $checkpoint['checkpoint']['stage_path'] ) ) {
			$preserve[] = $checkpoint['checkpoint']['stage_path'];
		}

		$result = $this->restore_staging_manager->cleanup_abandoned_stage_directories( $preserve );

		if ( ! $this->logger ) {
			return;
		}

		if ( ! empty( $result['deleted'] ) ) {
			$this->logger->log(
				'restore_stage_cleanup_completed',
				'info',
				'restore-maintenance',
				__( 'Abandoned restore stage directories were removed.', 'zignites-sentinel' ),
				array(
					'deleted_count' => count( $result['deleted'] ),
					'deleted_paths' => $result['deleted'],
				)
			);
		}

		if ( ! empty( $result['failed'] ) ) {
			$this->logger->log(
				'restore_stage_cleanup_failed',
				'warning',
				'restore-maintenance',
				__( 'One or more abandoned restore stage directories could not be removed.', 'zignites-sentinel' ),
				array(
					'failed_count' => count( $result['failed'] ),
					'failed_paths' => $result['failed'],
				)
			);
		}
	}
}
