<?php
/**
 * Snapshot retention maintenance.
 *
 * @package ZignitesSentinel
 */

namespace Zignites\Sentinel\Snapshots;

use Zignites\Sentinel\Core\OperationLock;
use Zignites\Sentinel\Logging\Logger;
use Zignites\Sentinel\Logging\LogRepository;

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
	 * Operation lock.
	 *
	 * @var OperationLock
	 */
	protected $operation_lock;

	/**
	 * Log repository.
	 *
	 * @var LogRepository|null
	 */
	protected $log_repository;

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
		Logger $logger = null,
		OperationLock $operation_lock = null,
		LogRepository $log_repository = null
	) {
		$this->repository          = $repository;
		$this->artifact_repository = $artifact_repository;
		$this->export_manager      = $export_manager;
		$this->package_manager     = $package_manager;
		$this->restore_staging_manager = $restore_staging_manager;
		$this->restore_checkpoint_store = $restore_checkpoint_store;
		$this->logger              = $logger;
		$this->operation_lock      = $operation_lock ? $operation_lock : new OperationLock();
		$this->log_repository      = $log_repository;
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
		$lock = $this->operation_lock->acquire( 'cleanup', 'cron' );

		if ( empty( $lock['acquired'] ) ) {
			$this->log_maintenance_event(
				'snapshot_cleanup_locked',
				'warning',
				__( 'Snapshot cleanup was skipped because another Sentinel operation is active.', 'zignites-sentinel' ),
				array(
					'lock' => isset( $lock['lock'] ) ? $lock['lock'] : array(),
				)
			);

			return;
		}

		try {
			$this->cleanup_old_snapshots_without_lock();
		} finally {
			if ( ! empty( $lock['lock'] ) ) {
				$this->operation_lock->release( $lock['lock'] );
			}
		}
	}

	/**
	 * Remove retained data while the operation lock is held.
	 *
	 * @return void
	 */
	protected function cleanup_old_snapshots_without_lock() {
		$settings       = $this->get_retention_settings();
		$retention_days = $settings['snapshot_retention_days'];

		if ( $retention_days < 1 ) {
			return;
		}

		$this->cleanup_old_logs( $settings['log_retention_days'] );
		$this->cleanup_old_packages( $settings['package_retention_days'] );
		$this->cleanup_old_restore_backups( $settings['restore_backup_retention_days'] );

		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $retention_days * DAY_IN_SECONDS ) );
		$ids    = $this->repository->get_ids_older_than( $cutoff );

		if ( ! empty( $ids ) ) {
			$artifacts = $this->artifact_repository->get_by_snapshot_ids( $ids );
			$this->export_manager->delete_artifact_files( $artifacts );
			$this->package_manager->delete_artifact_files( $artifacts );
			$this->artifact_repository->delete_by_snapshot_ids( $ids );
		}

		$this->repository->delete_older_than( $cutoff );
		$this->cleanup_abandoned_restore_stages( $settings['failed_stage_retention_days'] );
	}

	/**
	 * Normalize retention settings.
	 *
	 * @return array
	 */
	protected function get_retention_settings() {
		$settings = get_option( ZNTS_OPTION_SETTINGS, array() );
		$settings = is_array( $settings ) ? $settings : array();

		return array(
			'log_retention_days'            => isset( $settings['log_retention_days'] ) ? max( 1, (int) $settings['log_retention_days'] ) : 90,
			'snapshot_retention_days'       => isset( $settings['snapshot_retention_days'] ) ? max( 1, (int) $settings['snapshot_retention_days'] ) : 30,
			'package_retention_days'        => isset( $settings['package_retention_days'] ) ? max( 1, (int) $settings['package_retention_days'] ) : 30,
			'restore_backup_retention_days' => isset( $settings['restore_backup_retention_days'] ) ? max( 1, (int) $settings['restore_backup_retention_days'] ) : 14,
			'failed_stage_retention_days'   => isset( $settings['failed_stage_retention_days'] ) ? max( 1, (int) $settings['failed_stage_retention_days'] ) : 7,
		);
	}

	/**
	 * Remove logs older than the configured retention window.
	 *
	 * @param int $retention_days Retention in days.
	 * @return void
	 */
	protected function cleanup_old_logs( $retention_days ) {
		if ( ! $this->log_repository || $retention_days < 1 ) {
			return;
		}

		$cutoff  = gmdate( 'Y-m-d H:i:s', time() - ( absint( $retention_days ) * DAY_IN_SECONDS ) );
		$deleted = $this->log_repository->delete_older_than( $cutoff );

		$this->log_maintenance_event(
			'log_retention_cleanup_completed',
			'info',
			__( 'Old Sentinel log entries were removed according to retention settings.', 'zignites-sentinel' ),
			array(
				'retention_days' => absint( $retention_days ),
				'deleted_count'  => false === $deleted ? 0 : (int) $deleted,
			)
		);
	}

	/**
	 * Remove package artifacts older than the configured package retention window.
	 *
	 * @param int $retention_days Retention in days.
	 * @return void
	 */
	protected function cleanup_old_packages( $retention_days ) {
		if ( $retention_days < 1 ) {
			return;
		}

		$cutoff    = gmdate( 'Y-m-d H:i:s', time() - ( absint( $retention_days ) * DAY_IN_SECONDS ) );
		$artifacts = $this->artifact_repository->get_by_type_older_than( 'package', $cutoff );

		if ( empty( $artifacts ) ) {
			return;
		}

		$this->package_manager->delete_artifact_files( $artifacts );
		$this->artifact_repository->delete_by_ids( $this->pluck_artifact_ids( $artifacts ) );

		$this->log_maintenance_event(
			'package_retention_cleanup_completed',
			'info',
			__( 'Old snapshot ZIP packages were removed according to retention settings.', 'zignites-sentinel' ),
			array(
				'retention_days' => absint( $retention_days ),
				'deleted_count'  => count( $artifacts ),
			)
		);
	}

	/**
	 * Remove restore backup directories older than the configured retention window.
	 *
	 * @param int $retention_days Retention in days.
	 * @return void
	 */
	protected function cleanup_old_restore_backups( $retention_days ) {
		if ( $retention_days < 1 ) {
			return;
		}

		$root = $this->get_uploads_child_directory( RestoreExecutor::BACKUP_DIRECTORY );

		if ( '' === $root || ! is_dir( $root ) ) {
			return;
		}

		$result = $this->cleanup_directory_children_older_than(
			$root,
			absint( $retention_days ) * DAY_IN_SECONDS,
			$this->get_preserved_restore_backup_paths()
		);

		$this->log_directory_cleanup_result(
			'restore_backup_retention_cleanup',
			__( 'Old restore backup directories were removed according to retention settings.', 'zignites-sentinel' ),
			$result
		);
	}

	/**
	 * Remove preserved stage directories that do not belong to the active execution checkpoint.
	 *
	 * @param int $retention_days Retention in days.
	 * @return void
	 */
	protected function cleanup_abandoned_restore_stages( $retention_days ) {
		$checkpoint = $this->restore_checkpoint_store->get_execution_checkpoint( 0 );
		$preserve   = array();

		if ( ! empty( $checkpoint['checkpoint']['stage_path'] ) && is_dir( $checkpoint['checkpoint']['stage_path'] ) ) {
			$preserve[] = $checkpoint['checkpoint']['stage_path'];
		}

		$result = $this->restore_staging_manager->cleanup_abandoned_stage_directories( $preserve, absint( $retention_days ) * DAY_IN_SECONDS );

		$this->log_directory_cleanup_result(
			'restore_stage_retention_cleanup',
			__( 'Abandoned restore stage directories were removed according to retention settings.', 'zignites-sentinel' ),
			$result
		);
	}

	/**
	 * Extract artifact IDs.
	 *
	 * @param array $artifacts Artifact rows.
	 * @return array
	 */
	protected function pluck_artifact_ids( array $artifacts ) {
		$ids = array();

		foreach ( $artifacts as $artifact ) {
			if ( ! empty( $artifact['id'] ) ) {
				$ids[] = absint( $artifact['id'] );
			}
		}

		return $ids;
	}

	/**
	 * Return active restore backup paths that must not be cleaned.
	 *
	 * @return array
	 */
	protected function get_preserved_restore_backup_paths() {
		$preserve   = array();
		$checkpoints = array(
			$this->restore_checkpoint_store->get_execution_checkpoint( 0 ),
			$this->restore_checkpoint_store->get_rollback_checkpoint( 0 ),
		);

		foreach ( $checkpoints as $checkpoint ) {
			if ( ! is_array( $checkpoint ) ) {
				continue;
			}

			if ( ! empty( $checkpoint['checkpoint']['backup_root'] ) ) {
				$preserve[] = wp_normalize_path( (string) $checkpoint['checkpoint']['backup_root'] );
			}
		}

		return array_values( array_unique( array_filter( $preserve ) ) );
	}

	/**
	 * Resolve a child directory under uploads.
	 *
	 * @param string $relative Relative directory.
	 * @return string
	 */
	protected function get_uploads_child_directory( $relative ) {
		$uploads = wp_upload_dir();

		if ( ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) ) {
			return '';
		}

		return trailingslashit( wp_normalize_path( $uploads['basedir'] ) ) . trim( wp_normalize_path( (string) $relative ), '/' );
	}

	/**
	 * Delete direct child directories older than a minimum age.
	 *
	 * @param string $root            Root directory.
	 * @param int    $min_age_seconds Minimum age in seconds.
	 * @param array  $preserve_paths  Paths to preserve.
	 * @return array
	 */
	protected function cleanup_directory_children_older_than( $root, $min_age_seconds, array $preserve_paths = array() ) {
		$root            = rtrim( wp_normalize_path( (string) $root ), '/' );
		$min_age_seconds = max( 0, absint( $min_age_seconds ) );
		$cutoff          = time() - $min_age_seconds;
		$preserve_map    = array();
		$deleted         = array();
		$failed          = array();

		foreach ( $preserve_paths as $path ) {
			$path = rtrim( wp_normalize_path( (string) $path ), '/' );

			if ( '' !== $path ) {
				$preserve_map[ $path ] = true;
			}
		}

		$entries = '' !== $root && is_dir( $root ) ? glob( trailingslashit( $root ) . '*' ) : false;

		if ( false === $entries ) {
			return array(
				'deleted' => array(),
				'failed'  => array(),
			);
		}

		foreach ( $entries as $entry ) {
			$entry = rtrim( wp_normalize_path( $entry ), '/' );

			if ( ! is_dir( $entry ) || isset( $preserve_map[ $entry ] ) ) {
				continue;
			}

			$modified_at = filemtime( $entry );

			if ( false !== $modified_at && $modified_at > $cutoff ) {
				continue;
			}

			if ( $this->delete_directory_recursive( $entry ) ) {
				$deleted[] = $entry;
				continue;
			}

			$failed[] = $entry;
		}

		return array(
			'deleted' => $deleted,
			'failed'  => $failed,
		);
	}

	/**
	 * Delete a directory recursively.
	 *
	 * @param string $path Directory path.
	 * @return bool
	 */
	protected function delete_directory_recursive( $path ) {
		if ( '' === (string) $path || ! is_dir( $path ) ) {
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
	 * Log a directory cleanup result.
	 *
	 * @param string $event_prefix Event prefix.
	 * @param string $message      Success message.
	 * @param array  $result       Cleanup result.
	 * @return void
	 */
	protected function log_directory_cleanup_result( $event_prefix, $message, array $result ) {
		if ( ! empty( $result['deleted'] ) ) {
			$this->log_maintenance_event(
				$event_prefix . '_completed',
				'info',
				$message,
				array(
					'deleted_count' => count( $result['deleted'] ),
					'deleted_paths' => $result['deleted'],
				)
			);
		}

		if ( ! empty( $result['failed'] ) ) {
			$this->log_maintenance_event(
				$event_prefix . '_failed',
				'warning',
				__( 'One or more retained filesystem directories could not be removed.', 'zignites-sentinel' ),
				array(
					'failed_count' => count( $result['failed'] ),
					'failed_paths' => $result['failed'],
				)
			);
		}
	}

	/**
	 * Log maintenance events when logging is available.
	 *
	 * @param string $event_type Event type.
	 * @param string $severity   Severity.
	 * @param string $message    Message.
	 * @param array  $context    Context.
	 * @return void
	 */
	protected function log_maintenance_event( $event_type, $severity, $message, array $context ) {
		if ( ! $this->logger ) {
			return;
		}

		$this->logger->log(
			$event_type,
			$severity,
			'restore-maintenance',
			$message,
			$context
		);
	}
}
