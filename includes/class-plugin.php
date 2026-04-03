<?php
/**
 * Main plugin orchestration.
 *
 * @package ZignitesSentinel
 */

namespace Zignites\Sentinel;

use Zignites\Sentinel\Admin\Admin;
use Zignites\Sentinel\Diagnostics\ConflictRepository;
use Zignites\Sentinel\Diagnostics\HealthScore;
use Zignites\Sentinel\Diagnostics\Monitor;
use Zignites\Sentinel\Core\Installer;
use Zignites\Sentinel\Logging\Logger;
use Zignites\Sentinel\Logging\LogRepository;
use Zignites\Sentinel\Snapshots\RestoreReadinessChecker;
use Zignites\Sentinel\Snapshots\RestoreDryRunChecker;
use Zignites\Sentinel\Snapshots\RestoreExecutionPlanner;
use Zignites\Sentinel\Snapshots\RestoreCheckpointStore;
use Zignites\Sentinel\Snapshots\RestoreExecutor;
use Zignites\Sentinel\Snapshots\RestoreHealthVerifier;
use Zignites\Sentinel\Snapshots\RestoreJournalRecorder;
use Zignites\Sentinel\Snapshots\RestoreRollbackManager;
use Zignites\Sentinel\Snapshots\RestoreStagingManager;
use Zignites\Sentinel\Snapshots\SnapshotArtifactInspector;
use Zignites\Sentinel\Snapshots\SnapshotComparator;
use Zignites\Sentinel\Snapshots\SnapshotArtifactRepository;
use Zignites\Sentinel\Snapshots\SnapshotExportManager;
use Zignites\Sentinel\Snapshots\SnapshotPackageManager;
use Zignites\Sentinel\Snapshots\SnapshotManager;
use Zignites\Sentinel\Snapshots\SnapshotMaintenance;
use Zignites\Sentinel\Snapshots\SnapshotRepository;
use Zignites\Sentinel\Updates\PreflightChecker;
use Zignites\Sentinel\Updates\SourceValidator;
use Zignites\Sentinel\Updates\UpdatePlanner;

defined( 'ABSPATH' ) || exit;

class Plugin {

	/**
	 * Admin bootstrapper.
	 *
	 * @var Admin
	 */
	protected $admin;

	/**
	 * Diagnostics monitor.
	 *
	 * @var Monitor
	 */
	protected $monitor;

	/**
	 * Snapshot maintenance service.
	 *
	 * @var SnapshotMaintenance
	 */
	protected $snapshot_maintenance;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$log_repository      = new LogRepository();
		$conflict_repository = new ConflictRepository();
		$snapshot_repository = new SnapshotRepository();
		$artifact_repository = new SnapshotArtifactRepository();
		$logger              = new Logger( $log_repository );
		$journal_recorder    = new RestoreJournalRecorder( $logger, $log_repository );
		$health_score        = new HealthScore( $conflict_repository, $log_repository );
		$snapshot_comparator = new SnapshotComparator();
		$export_manager      = new SnapshotExportManager();
		$package_manager     = new SnapshotPackageManager();
		$restore_dry_run     = new RestoreDryRunChecker( $package_manager );
		$restore_staging     = new RestoreStagingManager( $package_manager );
		$restore_planner     = new RestoreExecutionPlanner( $restore_staging );
		$checkpoint_store    = new RestoreCheckpointStore();
		$restore_verifier    = new RestoreHealthVerifier();
		$restore_executor    = new RestoreExecutor( $restore_staging, $restore_planner, $restore_verifier, $logger, $journal_recorder, $checkpoint_store );
		$restore_rollback    = new RestoreRollbackManager( $logger, $journal_recorder, $checkpoint_store );
		$artifact_inspector  = new SnapshotArtifactInspector( $export_manager, $package_manager );
		$source_validator    = new SourceValidator( $artifact_repository, $export_manager, $package_manager );
		$snapshot_manager    = new SnapshotManager( $snapshot_repository, $artifact_repository, null, $export_manager, $package_manager, $logger );
		$snapshot_maintenance = new SnapshotMaintenance( $snapshot_repository, $artifact_repository, $export_manager, $package_manager, $restore_staging, $checkpoint_store, $logger );
		$restore_checker     = new RestoreReadinessChecker( $snapshot_comparator, $source_validator );
		$preflight_checker   = new PreflightChecker();
		$update_planner      = new UpdatePlanner( $snapshot_manager, $source_validator, $logger );

		$this->monitor = new Monitor( $logger, $conflict_repository );
		$this->snapshot_maintenance = $snapshot_maintenance;
		$this->admin   = new Admin(
			$logger,
			$log_repository,
			$conflict_repository,
			$snapshot_repository,
			$artifact_repository,
			$health_score,
			$preflight_checker,
			$snapshot_manager,
			$update_planner,
			$snapshot_comparator,
			$restore_checker,
			$artifact_inspector,
			$restore_dry_run,
			$restore_staging,
			$restore_planner,
			$restore_executor,
			$restore_verifier,
			$restore_rollback,
			$journal_recorder,
			$checkpoint_store
		);
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function run() {
		add_action( 'plugins_loaded', array( $this, 'maybe_upgrade' ), 5 );
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		$this->monitor->register_hooks();
		$this->snapshot_maintenance->register_hooks();

		if ( is_admin() ) {
			$this->admin->register_hooks();
		}
	}

	/**
	 * Run schema upgrades when plugin or database versions change.
	 *
	 * @return void
	 */
	public function maybe_upgrade() {
		$current_db_version = get_option( ZNTS_OPTION_DB_VERSION, '' );

		if ( version_compare( (string) $current_db_version, ZNTS_DB_VERSION, '<' ) ) {
			Installer::install();
		}
	}

	/**
	 * Load plugin translations.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'zignites-sentinel',
			false,
			dirname( plugin_basename( ZNTS_PLUGIN_FILE ) ) . '/languages'
		);
	}
}
