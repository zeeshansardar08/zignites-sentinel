<?php
/**
 * Admin bootstrap and screen registration.
 *
 * @package ZignitesSentinel
 */

namespace Zignites\Sentinel\Admin;

use Zignites\Sentinel\Diagnostics\ConflictRepository;
use Zignites\Sentinel\Diagnostics\HealthScore;
use Zignites\Sentinel\Core\Installer;
use Zignites\Sentinel\Logging\Logger;
use Zignites\Sentinel\Logging\LogRepository;
use Zignites\Sentinel\Snapshots\RestoreReadinessChecker;
use Zignites\Sentinel\Snapshots\RestoreDryRunChecker;
use Zignites\Sentinel\Snapshots\RestoreCheckpointStore;
use Zignites\Sentinel\Snapshots\RestoreExecutionPlanner;
use Zignites\Sentinel\Snapshots\RestoreExecutor;
use Zignites\Sentinel\Snapshots\RestoreHealthVerifier;
use Zignites\Sentinel\Snapshots\RestoreJournalRecorder;
use Zignites\Sentinel\Snapshots\RestoreStagingManager;
use Zignites\Sentinel\Snapshots\RestoreRollbackManager;
use Zignites\Sentinel\Snapshots\SnapshotArtifactInspector;
use Zignites\Sentinel\Snapshots\SnapshotComparator;
use Zignites\Sentinel\Snapshots\SnapshotArtifactRepository;
use Zignites\Sentinel\Snapshots\SnapshotManager;
use Zignites\Sentinel\Snapshots\SnapshotRepository;
use Zignites\Sentinel\Updates\PreflightChecker;
use Zignites\Sentinel\Updates\UpdatePlanner;

defined( 'ABSPATH' ) || exit;

class Admin {

	/**
	 * Admin page slug.
	 *
	 * @var string
	 */
	const MENU_SLUG = 'zignites-sentinel';

	/**
	 * Update readiness page slug.
	 *
	 * @var string
	 */
	const UPDATE_PAGE_SLUG = 'zignites-sentinel-update-readiness';

	/**
	 * Event logs page slug.
	 *
	 * @var string
	 */
	const LOGS_PAGE_SLUG = 'zignites-sentinel-event-logs';

	/**
	 * Screen hook suffix.
	 *
	 * @var string
	 */
	protected $hook_suffixes = array();

	/**
	 * Structured logger.
	 *
	 * @var Logger
	 */
	protected $logger;

	/**
	 * Log repository.
	 *
	 * @var LogRepository
	 */
	protected $logs;

	/**
	 * Conflict repository.
	 *
	 * @var ConflictRepository
	 */
	protected $conflicts;

	/**
	 * Health score service.
	 *
	 * @var HealthScore
	 */
	protected $health_score;

	/**
	 * Snapshot repository.
	 *
	 * @var SnapshotRepository
	 */
	protected $snapshots;

	/**
	 * Snapshot artifact repository.
	 *
	 * @var SnapshotArtifactRepository
	 */
	protected $artifacts;

	/**
	 * Snapshot artifact inspector.
	 *
	 * @var SnapshotArtifactInspector
	 */
	protected $artifact_inspector;

	/**
	 * Preflight checker.
	 *
	 * @var PreflightChecker
	 */
	protected $preflight_checker;

	/**
	 * Snapshot manager.
	 *
	 * @var SnapshotManager
	 */
	protected $snapshot_manager;

	/**
	 * Update planner.
	 *
	 * @var UpdatePlanner
	 */
	protected $update_planner;

	/**
	 * Snapshot comparator.
	 *
	 * @var SnapshotComparator
	 */
	protected $snapshot_comparator;

	/**
	 * Restore readiness checker.
	 *
	 * @var RestoreReadinessChecker
	 */
	protected $restore_checker;

	/**
	 * Restore dry-run checker.
	 *
	 * @var RestoreDryRunChecker
	 */
	protected $restore_dry_run_checker;

	/**
	 * Restore staging manager.
	 *
	 * @var RestoreStagingManager
	 */
	protected $restore_staging_manager;

	/**
	 * Restore execution planner.
	 *
	 * @var RestoreExecutionPlanner
	 */
	protected $restore_execution_planner;

	/**
	 * Restore executor.
	 *
	 * @var RestoreExecutor
	 */
	protected $restore_executor;

	/**
	 * Restore health verifier.
	 *
	 * @var RestoreHealthVerifier
	 */
	protected $restore_health_verifier;

	/**
	 * Restore rollback manager.
	 *
	 * @var RestoreRollbackManager
	 */
	protected $restore_rollback_manager;

	/**
	 * Restore journal recorder.
	 *
	 * @var RestoreJournalRecorder
	 */
	protected $restore_journal_recorder;

	/**
	 * Restore checkpoint store.
	 *
	 * @var RestoreCheckpointStore
	 */
	protected $restore_checkpoint_store;

	/**
	 * Constructor.
	 *
	 * @param Logger                   $logger              Structured logger.
	 * @param LogRepository            $logs                Log repository.
	 * @param ConflictRepository       $conflicts           Conflict repository.
	 * @param SnapshotRepository       $snapshots           Snapshot repository.
	 * @param SnapshotArtifactRepository $artifacts         Snapshot artifact repository.
	 * @param HealthScore               $health_score        Health score service.
	 * @param PreflightChecker          $preflight_checker   Preflight checker.
	 * @param SnapshotManager           $snapshot_manager    Snapshot manager.
	 * @param UpdatePlanner             $update_planner      Update planner.
	 * @param SnapshotComparator        $snapshot_comparator Snapshot comparator.
	 * @param RestoreReadinessChecker   $restore_checker     Restore readiness checker.
	 * @param SnapshotArtifactInspector $artifact_inspector   Snapshot artifact inspector.
	 * @param RestoreDryRunChecker      $restore_dry_run_checker Restore dry-run checker.
	 * @param RestoreStagingManager     $restore_staging_manager Restore staging manager.
	 * @param RestoreExecutionPlanner   $restore_execution_planner Restore execution planner.
	 * @param RestoreExecutor           $restore_executor Restore executor.
	 * @param RestoreHealthVerifier     $restore_health_verifier Restore health verifier.
	 * @param RestoreRollbackManager    $restore_rollback_manager Restore rollback manager.
	 * @param RestoreJournalRecorder    $restore_journal_recorder Restore journal recorder.
	 * @param RestoreCheckpointStore    $restore_checkpoint_store Restore checkpoint store.
	 */
	public function __construct(
		Logger $logger,
		LogRepository $logs,
		ConflictRepository $conflicts,
		SnapshotRepository $snapshots,
		SnapshotArtifactRepository $artifacts,
		HealthScore $health_score,
		PreflightChecker $preflight_checker,
		SnapshotManager $snapshot_manager,
		UpdatePlanner $update_planner,
		SnapshotComparator $snapshot_comparator,
		RestoreReadinessChecker $restore_checker,
		SnapshotArtifactInspector $artifact_inspector,
		RestoreDryRunChecker $restore_dry_run_checker,
		RestoreStagingManager $restore_staging_manager,
		RestoreExecutionPlanner $restore_execution_planner,
		RestoreExecutor $restore_executor,
		RestoreHealthVerifier $restore_health_verifier,
		RestoreRollbackManager $restore_rollback_manager,
		RestoreJournalRecorder $restore_journal_recorder,
		RestoreCheckpointStore $restore_checkpoint_store
	) {
		$this->logger            = $logger;
		$this->logs              = $logs;
		$this->conflicts         = $conflicts;
		$this->snapshots         = $snapshots;
		$this->artifacts         = $artifacts;
		$this->health_score      = $health_score;
		$this->preflight_checker = $preflight_checker;
		$this->snapshot_manager    = $snapshot_manager;
		$this->update_planner      = $update_planner;
		$this->snapshot_comparator = $snapshot_comparator;
		$this->restore_checker     = $restore_checker;
		$this->artifact_inspector  = $artifact_inspector;
		$this->restore_dry_run_checker = $restore_dry_run_checker;
		$this->restore_staging_manager = $restore_staging_manager;
		$this->restore_execution_planner = $restore_execution_planner;
		$this->restore_executor = $restore_executor;
		$this->restore_health_verifier = $restore_health_verifier;
		$this->restore_rollback_manager = $restore_rollback_manager;
		$this->restore_journal_recorder = $restore_journal_recorder;
		$this->restore_checkpoint_store = $restore_checkpoint_store;
	}

	/**
	 * Register admin hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_znts_run_preflight', array( $this, 'handle_run_preflight' ) );
		add_action( 'admin_post_znts_create_snapshot', array( $this, 'handle_create_snapshot' ) );
		add_action( 'admin_post_znts_save_settings', array( $this, 'handle_save_settings' ) );
		add_action( 'admin_post_znts_build_update_plan', array( $this, 'handle_build_update_plan' ) );
		add_action( 'admin_post_znts_check_restore_readiness', array( $this, 'handle_check_restore_readiness' ) );
		add_action( 'admin_post_znts_run_restore_dry_run', array( $this, 'handle_run_restore_dry_run' ) );
		add_action( 'admin_post_znts_run_restore_stage', array( $this, 'handle_run_restore_stage' ) );
		add_action( 'admin_post_znts_build_restore_plan', array( $this, 'handle_build_restore_plan' ) );
		add_action( 'admin_post_znts_refresh_restore_gates', array( $this, 'handle_refresh_restore_gates' ) );
		add_action( 'admin_post_znts_execute_restore', array( $this, 'handle_execute_restore' ) );
		add_action( 'admin_post_znts_resume_restore', array( $this, 'handle_resume_restore' ) );
		add_action( 'admin_post_znts_discard_restore_execution_checkpoint', array( $this, 'handle_discard_restore_execution_checkpoint' ) );
		add_action( 'admin_post_znts_capture_snapshot_health_baseline', array( $this, 'handle_capture_snapshot_health_baseline' ) );
		add_action( 'admin_post_znts_download_snapshot_audit_report', array( $this, 'handle_download_snapshot_audit_report' ) );
		add_action( 'admin_post_znts_verify_snapshot_audit_report', array( $this, 'handle_verify_snapshot_audit_report' ) );
		add_action( 'admin_post_znts_rollback_restore', array( $this, 'handle_rollback_restore' ) );
		add_action( 'admin_post_znts_resume_restore_rollback', array( $this, 'handle_resume_restore_rollback' ) );
	}

	/**
	 * Register the primary admin page.
	 *
	 * @return void
	 */
	public function register_menu() {
		$this->hook_suffixes[] = add_menu_page(
			__( 'Zignites Sentinel', 'zignites-sentinel' ),
			__( 'Sentinel', 'zignites-sentinel' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_dashboard' ),
			'dashicons-shield-alt',
			58
		);

		$this->hook_suffixes[] = add_submenu_page(
			self::MENU_SLUG,
			__( 'Update Readiness', 'zignites-sentinel' ),
			__( 'Update Readiness', 'zignites-sentinel' ),
			'manage_options',
			self::UPDATE_PAGE_SLUG,
			array( $this, 'render_update_readiness' )
		);

		$this->hook_suffixes[] = add_submenu_page(
			self::MENU_SLUG,
			__( 'Event Logs', 'zignites-sentinel' ),
			__( 'Event Logs', 'zignites-sentinel' ),
			'manage_options',
			self::LOGS_PAGE_SLUG,
			array( $this, 'render_event_logs' )
		);
	}

	/**
	 * Load styles only on the plugin screen.
	 *
	 * @param string $hook_suffix Current admin hook suffix.
	 * @return void
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( ! in_array( $hook_suffix, $this->hook_suffixes, true ) ) {
			return;
		}

		wp_enqueue_style(
			'znts-admin',
			ZNTS_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			ZNTS_VERSION
		);
	}

	/**
	 * Render the dashboard shell.
	 *
	 * @return void
	 */
	public function render_dashboard() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'zignites-sentinel' ) );
		}

		$view_data = array(
			'plugin_version'   => ZNTS_VERSION,
			'db_version'       => get_option( ZNTS_OPTION_DB_VERSION, ZNTS_DB_VERSION ),
			'logs_table'       => Installer::get_logs_table_name(),
			'conflicts_table'  => Installer::get_conflicts_table_name(),
			'wordpress'        => get_bloginfo( 'version' ),
			'php'              => PHP_VERSION,
			'site_url'         => home_url(),
			'recent_logs'      => $this->logs->get_recent( 8 ),
			'recent_conflicts' => $this->conflicts->get_recent_open( 6 ),
			'recent_snapshots' => $this->snapshots->get_recent( 5 ),
			'health_score'     => $this->health_score->calculate(),
			'restore_dashboard_summary' => $this->get_restore_dashboard_summary(),
			'restore_health_strip' => $this->get_restore_dashboard_health_strip(),
		);

		require ZNTS_PLUGIN_DIR . 'includes/admin/views/dashboard.php';
	}

	/**
	 * Render the update readiness screen.
	 *
	 * @return void
	 */
	public function render_update_readiness() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'zignites-sentinel' ) );
		}

		$snapshot_detail = $this->get_snapshot_detail();
		$snapshot_search = $this->get_snapshot_search_term();

		$view_data = array(
			'last_preflight'    => get_option( ZNTS_OPTION_LAST_PREFLIGHT, array() ),
			'last_update_plan'  => get_option( ZNTS_OPTION_UPDATE_PLAN, array() ),
			'last_restore_check' => $this->get_last_restore_check( $snapshot_detail ),
			'settings'           => $this->get_settings(),
			'update_candidates'  => $this->update_planner->get_candidates(),
			'recent_snapshots'   => $this->get_recent_snapshots_for_update_readiness( $snapshot_search ),
			'snapshot_search'    => $snapshot_search,
			'snapshot_detail'    => $snapshot_detail,
			'snapshot_comparison' => $this->get_snapshot_comparison( $snapshot_detail ),
			'snapshot_artifacts' => $this->get_snapshot_artifacts( $snapshot_detail ),
			'artifact_diff'      => $this->get_artifact_diff( $snapshot_detail ),
			'last_restore_dry_run' => $this->get_last_restore_dry_run( $snapshot_detail ),
			'last_restore_stage' => $this->get_last_restore_stage( $snapshot_detail ),
			'last_restore_plan' => $this->get_last_restore_plan( $snapshot_detail ),
			'last_restore_execution' => $this->get_last_restore_execution( $snapshot_detail ),
			'last_restore_rollback' => $this->get_last_restore_rollback( $snapshot_detail ),
			'stage_checkpoint' => $this->get_restore_stage_checkpoint( $snapshot_detail ),
			'plan_checkpoint' => $this->get_restore_plan_checkpoint( $snapshot_detail ),
			'execution_checkpoint' => $this->get_restore_execution_checkpoint( $snapshot_detail ),
			'restore_resume_context' => $this->get_restore_resume_context( $snapshot_detail ),
			'restore_rollback_resume_context' => $this->get_restore_rollback_resume_context( $snapshot_detail ),
			'restore_run_cards' => $this->get_restore_run_cards( $snapshot_detail ),
			'snapshot_health_baseline' => $this->get_snapshot_health_baseline( $snapshot_detail ),
			'snapshot_health_comparison' => $this->get_snapshot_health_comparison( $snapshot_detail ),
			'operator_checklist' => $this->get_restore_operator_checklist( $snapshot_detail ),
			'audit_report_verification' => $this->get_audit_report_verification( $snapshot_detail ),
			'snapshot_activity' => $this->get_snapshot_activity( $snapshot_detail ),
			'snapshot_activity_url' => $this->get_snapshot_activity_url( is_array( $snapshot_detail ) && ! empty( $snapshot_detail['id'] ) ? (int) $snapshot_detail['id'] : 0 ),
			'notice'             => $this->get_notice_message(),
		);

		require ZNTS_PLUGIN_DIR . 'includes/admin/views/update-readiness.php';
	}

	/**
	 * Render the event logs screen.
	 *
	 * @return void
	 */
	public function render_event_logs() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'zignites-sentinel' ) );
		}

		$log_filters = $this->get_log_filters();
		$per_page    = 20;
		$total_logs  = $this->logs->count_filtered( $log_filters );
		$total_pages  = max( 1, (int) ceil( $total_logs / $per_page ) );
		$current_page = min( max( 1, (int) $log_filters['paged'] ), $total_pages );

		$log_filters['paged']    = $current_page;
		$log_filters['per_page'] = $per_page;

		$log_detail = $this->get_log_detail();

		$view_data = array(
			'recent_logs' => $this->logs->get_paginated( $log_filters ),
			'log_detail'  => $log_detail,
			'log_filters' => $log_filters,
			'operational_events' => $this->get_operational_events( $log_filters ),
			'run_summaries' => $this->get_run_summaries( $log_filters ),
			'run_journal' => $this->get_run_journal( $log_filters ),
			'pagination'  => array(
				'current_page' => $current_page,
				'per_page'     => $per_page,
				'total_logs'   => $total_logs,
				'total_pages'  => $total_pages,
			),
		);

		require ZNTS_PLUGIN_DIR . 'includes/admin/views/event-logs.php';
	}

	/**
	 * Handle the preflight scan action.
	 *
	 * @return void
	 */
	public function handle_run_preflight() {
		$this->assert_admin_action_permissions( 'znts_run_preflight_action' );

		update_option( ZNTS_OPTION_LAST_PREFLIGHT, $this->preflight_checker->run(), false );
		$this->redirect_with_notice( 'preflight-complete' );
	}

	/**
	 * Handle the snapshot creation action.
	 *
	 * @return void
	 */
	public function handle_create_snapshot() {
		$this->assert_admin_action_permissions( 'znts_create_snapshot_action' );

		$result = $this->snapshot_manager->create_manual_snapshot( get_current_user_id() );

		if ( false === $result ) {
			$this->redirect_with_notice( 'snapshot-failed' );
		}

		$this->redirect_with_notice( 'snapshot-created' );
	}

	/**
	 * Handle settings persistence.
	 *
	 * @return void
	 */
	public function handle_save_settings() {
		$this->assert_admin_action_permissions( 'znts_save_settings_action' );

		$settings = $this->get_settings();

		$settings['logging_enabled']         = isset( $_POST['logging_enabled'] ) ? 1 : 0;
		$settings['delete_data_on_uninstall'] = isset( $_POST['delete_data_on_uninstall'] ) ? 1 : 0;
		$settings['auto_snapshot_on_plan']   = isset( $_POST['auto_snapshot_on_plan'] ) ? 1 : 0;
		$settings['snapshot_retention_days'] = isset( $_POST['snapshot_retention_days'] ) ? max( 1, absint( wp_unslash( $_POST['snapshot_retention_days'] ) ) ) : 30;
		$settings['restore_checkpoint_max_age_hours'] = isset( $_POST['restore_checkpoint_max_age_hours'] ) ? max( 1, absint( wp_unslash( $_POST['restore_checkpoint_max_age_hours'] ) ) ) : 24;

		update_option( ZNTS_OPTION_SETTINGS, $settings, false );
		$this->redirect_with_notice( 'settings-saved' );
	}

	/**
	 * Handle manual update-plan generation.
	 *
	 * @return void
	 */
	public function handle_build_update_plan() {
		$this->assert_admin_action_permissions( 'znts_build_update_plan_action' );

		$selected_targets = isset( $_POST['update_targets'] ) && is_array( $_POST['update_targets'] )
			? wp_unslash( $_POST['update_targets'] )
			: array();

		$plan = $this->update_planner->build_plan( $selected_targets, get_current_user_id() );
		update_option( ZNTS_OPTION_UPDATE_PLAN, $plan, false );

		$notice = 'empty' === $plan['status'] ? 'update-plan-empty' : 'update-plan-created';
		$this->redirect_with_notice( $notice );
	}

	/**
	 * Handle restore-readiness evaluation for a selected snapshot.
	 *
	 * @return void
	 */
	public function handle_check_restore_readiness() {
		$this->assert_admin_action_permissions( 'znts_check_restore_readiness_action' );

		$snapshot_id = isset( $_POST['snapshot_id'] ) ? absint( wp_unslash( $_POST['snapshot_id'] ) ) : 0;
		$snapshot    = $this->snapshots->get_by_id( $snapshot_id );

		if ( ! is_array( $snapshot ) ) {
			$this->redirect_with_notice( 'restore-check-missing' );
		}

		$snapshot['active_plugins_decoded'] = $this->decode_json_field( $snapshot['active_plugins'] );
		$snapshot['metadata_decoded']       = $this->decode_json_field( $snapshot['metadata'] );

		$assessment                = $this->restore_checker->assess( $snapshot );
		$assessment['snapshot_id'] = $snapshot_id;

		update_option( ZNTS_OPTION_LAST_RESTORE_CHECK, $assessment, false );
		$this->logger->log(
			'restore_readiness_assessed',
			$this->map_assessment_status_to_severity( $assessment['status'] ),
			'restore-readiness',
			__( 'Restore-readiness assessment completed.', 'zignites-sentinel' ),
			array(
				'snapshot_id' => $snapshot_id,
				'status'      => $assessment['status'],
				'summary'     => isset( $assessment['summary'] ) ? $assessment['summary'] : array(),
				'check_count' => isset( $assessment['checks'] ) && is_array( $assessment['checks'] ) ? count( $assessment['checks'] ) : 0,
			)
		);

		$url = add_query_arg(
			array(
				'page'        => self::UPDATE_PAGE_SLUG,
				'snapshot_id' => $snapshot_id,
				'znts_notice' => 'restore-check-complete',
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Handle restore dry-run validation for a selected snapshot.
	 *
	 * @return void
	 */
	public function handle_run_restore_dry_run() {
		$this->assert_admin_action_permissions( 'znts_run_restore_dry_run_action' );

		$snapshot_id = isset( $_POST['snapshot_id'] ) ? absint( wp_unslash( $_POST['snapshot_id'] ) ) : 0;
		$snapshot    = $this->snapshots->get_by_id( $snapshot_id );

		if ( ! is_array( $snapshot ) ) {
			$this->redirect_with_notice( 'restore-dry-run-missing' );
		}

		$snapshot['active_plugins_decoded'] = $this->decode_json_field( $snapshot['active_plugins'] );
		$snapshot['metadata_decoded']       = $this->decode_json_field( $snapshot['metadata'] );
		$artifacts                          = $this->artifacts->get_by_snapshot_id( $snapshot_id );
		$dry_run                            = $this->restore_dry_run_checker->run( $snapshot, $artifacts );
		$dry_run['snapshot_id']             = $snapshot_id;

		update_option( ZNTS_OPTION_LAST_RESTORE_DRY_RUN, $dry_run, false );
		$this->logger->log(
			'restore_dry_run_completed',
			$this->map_assessment_status_to_severity( $dry_run['status'] ),
			'restore-dry-run',
			__( 'Restore dry-run validation completed.', 'zignites-sentinel' ),
			array(
				'snapshot_id' => $snapshot_id,
				'status'      => $dry_run['status'],
				'summary'     => isset( $dry_run['summary'] ) ? $dry_run['summary'] : array(),
			)
		);

		$url = add_query_arg(
			array(
				'page'        => self::UPDATE_PAGE_SLUG,
				'snapshot_id' => $snapshot_id,
				'znts_notice' => 'restore-dry-run-complete',
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Handle staged restore validation for a selected snapshot.
	 *
	 * @return void
	 */
	public function handle_run_restore_stage() {
		$this->assert_admin_action_permissions( 'znts_run_restore_stage_action' );

		$snapshot_id = isset( $_POST['snapshot_id'] ) ? absint( wp_unslash( $_POST['snapshot_id'] ) ) : 0;
		$snapshot    = $this->snapshots->get_by_id( $snapshot_id );

		if ( ! is_array( $snapshot ) ) {
			$this->redirect_with_notice( 'restore-stage-missing' );
		}

		$snapshot['active_plugins_decoded'] = $this->decode_json_field( $snapshot['active_plugins'] );
		$snapshot['metadata_decoded']       = $this->decode_json_field( $snapshot['metadata'] );
		$artifacts                          = $this->artifacts->get_by_snapshot_id( $snapshot_id );
		$stage_run                          = $this->run_restore_stage_for_snapshot( $snapshot, $artifacts );
		$stage_run['snapshot_id']           = $snapshot_id;

		$url = add_query_arg(
			array(
				'page'        => self::UPDATE_PAGE_SLUG,
				'snapshot_id' => $snapshot_id,
				'znts_notice' => 'restore-stage-complete',
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Handle restore plan generation for a selected snapshot.
	 *
	 * @return void
	 */
	public function handle_build_restore_plan() {
		$this->assert_admin_action_permissions( 'znts_build_restore_plan_action' );

		$snapshot_id = isset( $_POST['snapshot_id'] ) ? absint( wp_unslash( $_POST['snapshot_id'] ) ) : 0;
		$snapshot    = $this->snapshots->get_by_id( $snapshot_id );

		if ( ! is_array( $snapshot ) ) {
			$this->redirect_with_notice( 'restore-plan-missing' );
		}

		$snapshot['active_plugins_decoded'] = $this->decode_json_field( $snapshot['active_plugins'] );
		$snapshot['metadata_decoded']       = $this->decode_json_field( $snapshot['metadata'] );
		$artifacts                          = $this->artifacts->get_by_snapshot_id( $snapshot_id );
		$plan                               = $this->run_restore_plan_for_snapshot( $snapshot, $artifacts );

		$url = add_query_arg(
			array(
				'page'        => self::UPDATE_PAGE_SLUG,
				'snapshot_id' => $snapshot_id,
				'znts_notice' => 'restore-plan-complete',
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Refresh non-destructive restore gates for a selected snapshot.
	 *
	 * @return void
	 */
	public function handle_refresh_restore_gates() {
		$this->assert_admin_action_permissions( 'znts_refresh_restore_gates_action' );

		$snapshot_id = isset( $_POST['snapshot_id'] ) ? absint( wp_unslash( $_POST['snapshot_id'] ) ) : 0;
		$snapshot    = $this->snapshots->get_by_id( $snapshot_id );

		if ( ! is_array( $snapshot ) ) {
			$this->redirect_with_notice( 'restore-gates-missing' );
		}

		$snapshot['active_plugins_decoded'] = $this->decode_json_field( $snapshot['active_plugins'] );
		$snapshot['metadata_decoded']       = $this->decode_json_field( $snapshot['metadata'] );
		$artifacts                          = $this->artifacts->get_by_snapshot_id( $snapshot_id );
		$stage_run                          = $this->run_restore_stage_for_snapshot( $snapshot, $artifacts );
		$plan                               = $this->run_restore_plan_for_snapshot( $snapshot, $artifacts );

		$this->logger->log(
			'restore_gates_refreshed',
			$this->map_assessment_status_to_severity(
				( isset( $stage_run['status'] ) && 'blocked' === $stage_run['status'] ) || ( isset( $plan['status'] ) && 'blocked' === $plan['status'] )
					? 'blocked'
					: ( ( isset( $stage_run['status'] ) && 'caution' === $stage_run['status'] ) || ( isset( $plan['status'] ) && 'caution' === $plan['status'] ) ? 'caution' : 'ready' )
			),
			'restore-checkpoint',
			__( 'Restore checklist gates were refreshed.', 'zignites-sentinel' ),
			array(
				'snapshot_id'   => $snapshot_id,
				'stage_status'  => isset( $stage_run['status'] ) ? $stage_run['status'] : '',
				'plan_status'   => isset( $plan['status'] ) ? $plan['status'] : '',
				'stage_summary' => isset( $stage_run['summary'] ) ? $stage_run['summary'] : array(),
				'plan_summary'  => isset( $plan['summary'] ) ? $plan['summary'] : array(),
			)
		);

		$url = add_query_arg(
			array(
				'page'        => self::UPDATE_PAGE_SLUG,
				'snapshot_id' => $snapshot_id,
				'znts_notice' => 'restore-gates-refreshed',
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Handle guarded live restore execution for a selected snapshot.
	 *
	 * @return void
	 */
	public function handle_execute_restore() {
		$this->assert_admin_action_permissions( 'znts_execute_restore_action' );

		$snapshot_id = isset( $_POST['snapshot_id'] ) ? absint( wp_unslash( $_POST['snapshot_id'] ) ) : 0;
		$snapshot    = $this->snapshots->get_by_id( $snapshot_id );

		if ( ! is_array( $snapshot ) ) {
			$this->redirect_with_notice( 'restore-execution-missing' );
		}

		$snapshot['active_plugins_decoded'] = $this->decode_json_field( $snapshot['active_plugins'] );
		$snapshot['metadata_decoded']       = $this->decode_json_field( $snapshot['metadata'] );
		$artifacts                          = $this->artifacts->get_by_snapshot_id( $snapshot_id );
		$operator_checklist                 = $this->get_restore_operator_checklist( $snapshot, $artifacts );
		$last_stage                         = $this->get_restore_stage_gate_result( $snapshot, $artifacts, true );
		$last_plan                          = $this->get_restore_plan_gate_result( $snapshot, $artifacts, true );
		$confirmation_phrase                = isset( $_POST['restore_confirmation_phrase'] ) ? sanitize_text_field( wp_unslash( $_POST['restore_confirmation_phrase'] ) ) : '';

		if ( empty( $operator_checklist['can_execute'] ) ) {
			$this->logger->log(
				'restore_operator_gate_blocked',
				'warning',
				'restore-execution',
				__( 'Restore execution was blocked because the operator checklist is incomplete.', 'zignites-sentinel' ),
				array(
					'snapshot_id' => $snapshot_id,
					'checklist'   => isset( $operator_checklist['checks'] ) ? $operator_checklist['checks'] : array(),
				)
			);
			$this->redirect_with_notice( 'restore-operator-gate-blocked' );
		}

		$result                             = $this->restore_executor->execute( $snapshot, $artifacts, is_array( $last_stage ) ? $last_stage : array(), is_array( $last_plan ) ? $last_plan : array(), $confirmation_phrase );

		update_option( ZNTS_OPTION_LAST_RESTORE_EXECUTION, $result, false );
		$this->logger->log(
			'restore_execution_recorded',
			$this->map_assessment_status_to_severity( $result['status'] ),
			'restore-execution',
			__( 'Restore execution result recorded.', 'zignites-sentinel' ),
			array(
				'snapshot_id' => $snapshot_id,
				'status'      => $result['status'],
				'summary'     => isset( $result['summary'] ) ? $result['summary'] : array(),
			)
		);

		$url = add_query_arg(
			array(
				'page'        => self::UPDATE_PAGE_SLUG,
				'snapshot_id' => $snapshot_id,
				'znts_notice' => 'restore-execution-complete',
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Handle resume of a previous restore execution.
	 *
	 * @return void
	 */
	public function handle_resume_restore() {
		$this->assert_admin_action_permissions( 'znts_resume_restore_action' );

		$snapshot_id = isset( $_POST['snapshot_id'] ) ? absint( wp_unslash( $_POST['snapshot_id'] ) ) : 0;
		$snapshot    = $this->snapshots->get_by_id( $snapshot_id );

		if ( ! is_array( $snapshot ) ) {
			$this->redirect_with_notice( 'restore-resume-missing' );
		}

		$snapshot['active_plugins_decoded'] = $this->decode_json_field( $snapshot['active_plugins'] );
		$snapshot['metadata_decoded']       = $this->decode_json_field( $snapshot['metadata'] );
		$artifacts                          = $this->artifacts->get_by_snapshot_id( $snapshot_id );
		$operator_checklist                 = $this->get_restore_operator_checklist( $snapshot, $artifacts );
		$last_stage                         = $this->get_restore_stage_gate_result( $snapshot, $artifacts, true );
		$last_plan                          = $this->get_restore_plan_gate_result( $snapshot, $artifacts, true );
		$last_execution                     = $this->get_last_restore_execution( $snapshot );
		$resume_context                     = $this->get_restore_resume_context( $snapshot );
		$confirmation_phrase                = isset( $_POST['restore_confirmation_phrase'] ) ? sanitize_text_field( wp_unslash( $_POST['restore_confirmation_phrase'] ) ) : '';

		if ( empty( $resume_context['can_resume'] ) || empty( $resume_context['run_id'] ) ) {
			$this->redirect_with_notice( 'restore-resume-missing' );
		}

		if ( empty( $operator_checklist['can_execute'] ) ) {
			$this->logger->log(
				'restore_operator_gate_blocked',
				'warning',
				'restore-execution',
				__( 'Restore resume was blocked because the operator checklist is incomplete.', 'zignites-sentinel' ),
				array(
					'snapshot_id' => $snapshot_id,
					'checklist'   => isset( $operator_checklist['checks'] ) ? $operator_checklist['checks'] : array(),
					'run_id'      => isset( $resume_context['run_id'] ) ? $resume_context['run_id'] : '',
				)
			);
			$this->redirect_with_notice( 'restore-operator-gate-blocked' );
		}

		$result = $this->restore_executor->resume(
			$snapshot,
			$artifacts,
			is_array( $last_stage ) ? $last_stage : array(),
			is_array( $last_plan ) ? $last_plan : array(),
			$confirmation_phrase,
			$resume_context
		);

		update_option( ZNTS_OPTION_LAST_RESTORE_EXECUTION, $result, false );
		$this->logger->log(
			'restore_execution_resumed',
			$this->map_assessment_status_to_severity( $result['status'] ),
			'restore-execution',
			__( 'Restore execution resume recorded.', 'zignites-sentinel' ),
			array(
				'snapshot_id' => $snapshot_id,
				'status'      => $result['status'],
				'run_id'      => isset( $result['run_id'] ) ? $result['run_id'] : '',
				'previous_status' => is_array( $last_execution ) && isset( $last_execution['status'] ) ? $last_execution['status'] : '',
				'summary'     => isset( $result['summary'] ) ? $result['summary'] : array(),
			)
		);

		$url = add_query_arg(
			array(
				'page'        => self::UPDATE_PAGE_SLUG,
				'snapshot_id' => $snapshot_id,
				'znts_notice' => 'restore-resume-complete',
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Handle discard of a preserved restore execution checkpoint.
	 *
	 * @return void
	 */
	public function handle_discard_restore_execution_checkpoint() {
		$this->assert_admin_action_permissions( 'znts_discard_restore_execution_checkpoint_action' );

		$snapshot_id = isset( $_POST['snapshot_id'] ) ? absint( wp_unslash( $_POST['snapshot_id'] ) ) : 0;
		$snapshot    = $this->snapshots->get_by_id( $snapshot_id );

		if ( ! is_array( $snapshot ) ) {
			$this->redirect_with_notice( 'restore-checkpoint-discard-missing' );
		}

		$snapshot['active_plugins_decoded'] = $this->decode_json_field( $snapshot['active_plugins'] );
		$snapshot['metadata_decoded']       = $this->decode_json_field( $snapshot['metadata'] );
		$checkpoint                         = $this->get_restore_execution_checkpoint( $snapshot );

		if ( empty( $checkpoint ) ) {
			$this->redirect_with_notice( 'restore-checkpoint-discard-missing' );
		}

		$checkpoint_state = isset( $checkpoint['checkpoint'] ) && is_array( $checkpoint['checkpoint'] ) ? $checkpoint['checkpoint'] : array();
		$stage_path       = isset( $checkpoint_state['stage_path'] ) ? wp_normalize_path( (string) $checkpoint_state['stage_path'] ) : '';
		$cleanup_ok       = true;

		if ( '' !== $stage_path && is_dir( $stage_path ) ) {
			$cleanup_ok = $this->restore_staging_manager->cleanup_stage_directory( $stage_path );
		}

		if ( ! $cleanup_ok ) {
			$this->logger->log(
				'restore_execution_checkpoint_discard_failed',
				'warning',
				'restore-checkpoint',
				__( 'The preserved restore execution checkpoint could not be discarded because its stage directory could not be removed.', 'zignites-sentinel' ),
				array(
					'snapshot_id' => $snapshot_id,
					'run_id'      => isset( $checkpoint['run_id'] ) ? $checkpoint['run_id'] : '',
					'stage_path'  => $stage_path,
				)
			);
			$this->redirect_with_notice( 'restore-checkpoint-discard-failed' );
		}

		$this->restore_checkpoint_store->clear_execution_checkpoint(
			$snapshot_id,
			isset( $checkpoint['run_id'] ) ? (string) $checkpoint['run_id'] : ''
		);

		$this->logger->log(
			'restore_execution_checkpoint_discarded',
			'info',
			'restore-checkpoint',
			__( 'The preserved restore execution checkpoint was discarded.', 'zignites-sentinel' ),
			array(
				'snapshot_id' => $snapshot_id,
				'run_id'      => isset( $checkpoint['run_id'] ) ? $checkpoint['run_id'] : '',
				'stage_path'  => $stage_path,
			)
		);

		$url = add_query_arg(
			array(
				'page'        => self::UPDATE_PAGE_SLUG,
				'snapshot_id' => $snapshot_id,
				'znts_notice' => 'restore-checkpoint-discarded',
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Handle capture of a snapshot health baseline.
	 *
	 * @return void
	 */
	public function handle_capture_snapshot_health_baseline() {
		$this->assert_admin_action_permissions( 'znts_capture_snapshot_health_baseline_action' );

		$snapshot_id = isset( $_POST['snapshot_id'] ) ? absint( wp_unslash( $_POST['snapshot_id'] ) ) : 0;
		$snapshot    = $this->snapshots->get_by_id( $snapshot_id );

		if ( ! is_array( $snapshot ) ) {
			$this->redirect_with_notice( 'snapshot-health-baseline-missing' );
		}

		$snapshot['active_plugins_decoded'] = $this->decode_json_field( $snapshot['active_plugins'] );
		$snapshot['metadata_decoded']       = $this->decode_json_field( $snapshot['metadata'] );

		$baseline                = $this->restore_health_verifier->verify( $snapshot );
		$baseline['snapshot_id'] = $snapshot_id;
		$baseline['phase']       = 'baseline';

		update_option( ZNTS_OPTION_LAST_SNAPSHOT_HEALTH_BASELINE, $baseline, false );
		$this->logger->log(
			'snapshot_health_baseline_captured',
			$this->map_health_status_to_severity( isset( $baseline['status'] ) ? $baseline['status'] : '' ),
			'snapshot-health',
			__( 'Snapshot health baseline captured.', 'zignites-sentinel' ),
			array(
				'snapshot_id' => $snapshot_id,
				'status'      => isset( $baseline['status'] ) ? $baseline['status'] : '',
				'summary'     => isset( $baseline['summary'] ) ? $baseline['summary'] : array(),
			)
		);

		$url = add_query_arg(
			array(
				'page'        => self::UPDATE_PAGE_SLUG,
				'snapshot_id' => $snapshot_id,
				'znts_notice' => 'snapshot-health-baseline-captured',
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Handle download of a snapshot audit report.
	 *
	 * @return void
	 */
	public function handle_download_snapshot_audit_report() {
		$this->assert_admin_action_permissions( 'znts_download_snapshot_audit_report_action' );

		$snapshot_id = isset( $_POST['snapshot_id'] ) ? absint( wp_unslash( $_POST['snapshot_id'] ) ) : 0;
		$snapshot    = $this->get_snapshot_detail_by_id( $snapshot_id );

		if ( ! is_array( $snapshot ) ) {
			$this->redirect_with_notice( 'snapshot-audit-missing' );
		}

		$report   = $this->build_snapshot_audit_report( $snapshot );
		$filename = sprintf( 'znts-snapshot-%d-audit-%s.json', $snapshot_id, gmdate( 'Ymd-His' ) );

		$this->logger->log(
			'snapshot_audit_report_downloaded',
			'info',
			'snapshots',
			__( 'Snapshot audit report downloaded.', 'zignites-sentinel' ),
			array(
				'snapshot_id' => $snapshot_id,
				'filename'    => $filename,
			)
		);

		nocache_headers();
		header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) );
		header( 'Content-Disposition: attachment; filename=' . $filename );

		echo wp_json_encode( $report, JSON_PRETTY_PRINT );
		exit;
	}

	/**
	 * Handle verification of a pasted snapshot audit report.
	 *
	 * @return void
	 */
	public function handle_verify_snapshot_audit_report() {
		$this->assert_admin_action_permissions( 'znts_verify_snapshot_audit_report_action' );

		$snapshot_id = isset( $_POST['snapshot_id'] ) ? absint( wp_unslash( $_POST['snapshot_id'] ) ) : 0;
		$payload     = isset( $_POST['audit_report_payload'] ) ? wp_unslash( $_POST['audit_report_payload'] ) : '';
		$result      = $this->verify_snapshot_audit_report_payload( $payload, $snapshot_id );

		update_option( ZNTS_OPTION_LAST_AUDIT_REPORT_VERIFICATION, $result, false );
		$this->logger->log(
			'snapshot_audit_report_verified',
			$this->map_assessment_status_to_severity( isset( $result['status'] ) ? $result['status'] : '' ),
			'snapshot-audit',
			__( 'Snapshot audit report verification completed.', 'zignites-sentinel' ),
			array(
				'snapshot_id' => $snapshot_id,
				'status'      => isset( $result['status'] ) ? $result['status'] : '',
				'summary'     => isset( $result['summary'] ) ? $result['summary'] : array(),
			)
		);

		$url = add_query_arg(
			array(
				'page'        => self::UPDATE_PAGE_SLUG,
				'snapshot_id' => $snapshot_id,
				'znts_notice' => 'snapshot-audit-verified',
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Handle rollback of a previous restore execution.
	 *
	 * @return void
	 */
	public function handle_rollback_restore() {
		$this->assert_admin_action_permissions( 'znts_rollback_restore_action' );

		$snapshot_id = isset( $_POST['snapshot_id'] ) ? absint( wp_unslash( $_POST['snapshot_id'] ) ) : 0;
		$snapshot    = $this->snapshots->get_by_id( $snapshot_id );

		if ( ! is_array( $snapshot ) ) {
			$this->redirect_with_notice( 'restore-rollback-missing' );
		}

		$snapshot['active_plugins_decoded'] = $this->decode_json_field( $snapshot['active_plugins'] );
		$snapshot['metadata_decoded']       = $this->decode_json_field( $snapshot['metadata'] );
		$execution                          = $this->get_last_restore_execution( $snapshot );
		$confirmation_phrase                = isset( $_POST['rollback_confirmation_phrase'] ) ? sanitize_text_field( wp_unslash( $_POST['rollback_confirmation_phrase'] ) ) : '';
		$result                             = $this->restore_rollback_manager->rollback( $snapshot, is_array( $execution ) ? $execution : array(), $confirmation_phrase );
		$result                             = $this->attach_health_verification_to_result( $snapshot, $result );

		update_option( ZNTS_OPTION_LAST_RESTORE_ROLLBACK, $result, false );
		$this->logger->log(
			'restore_rollback_recorded',
			$this->map_assessment_status_to_severity( $result['status'] ),
			'restore-rollback',
			__( 'Restore rollback result recorded.', 'zignites-sentinel' ),
			array(
				'snapshot_id' => $snapshot_id,
				'status'      => $result['status'],
				'summary'     => isset( $result['summary'] ) ? $result['summary'] : array(),
			)
		);

		$url = add_query_arg(
			array(
				'page'        => self::UPDATE_PAGE_SLUG,
				'snapshot_id' => $snapshot_id,
				'znts_notice' => 'restore-rollback-complete',
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Handle resume of a previous rollback execution.
	 *
	 * @return void
	 */
	public function handle_resume_restore_rollback() {
		$this->assert_admin_action_permissions( 'znts_resume_restore_rollback_action' );

		$snapshot_id = isset( $_POST['snapshot_id'] ) ? absint( wp_unslash( $_POST['snapshot_id'] ) ) : 0;
		$snapshot    = $this->snapshots->get_by_id( $snapshot_id );

		if ( ! is_array( $snapshot ) ) {
			$this->redirect_with_notice( 'restore-rollback-resume-missing' );
		}

		$snapshot['active_plugins_decoded'] = $this->decode_json_field( $snapshot['active_plugins'] );
		$snapshot['metadata_decoded']       = $this->decode_json_field( $snapshot['metadata'] );
		$execution                          = $this->get_last_restore_execution( $snapshot );
		$resume_context                     = $this->get_restore_rollback_resume_context( $snapshot );
		$confirmation_phrase                = isset( $_POST['rollback_confirmation_phrase'] ) ? sanitize_text_field( wp_unslash( $_POST['rollback_confirmation_phrase'] ) ) : '';

		if ( empty( $resume_context['can_resume'] ) || empty( $resume_context['run_id'] ) ) {
			$this->redirect_with_notice( 'restore-rollback-resume-missing' );
		}

		$result = $this->restore_rollback_manager->resume(
			$snapshot,
			is_array( $execution ) ? $execution : array(),
			$confirmation_phrase,
			$resume_context
		);
		$result = $this->attach_health_verification_to_result( $snapshot, $result );

		update_option( ZNTS_OPTION_LAST_RESTORE_ROLLBACK, $result, false );
		$this->logger->log(
			'restore_rollback_resumed',
			$this->map_assessment_status_to_severity( $result['status'] ),
			'restore-rollback',
			__( 'Restore rollback resume recorded.', 'zignites-sentinel' ),
			array(
				'snapshot_id' => $snapshot_id,
				'status'      => $result['status'],
				'run_id'      => isset( $result['run_id'] ) ? $result['run_id'] : '',
				'summary'     => isset( $result['summary'] ) ? $result['summary'] : array(),
			)
		);

		$url = add_query_arg(
			array(
				'page'        => self::UPDATE_PAGE_SLUG,
				'snapshot_id' => $snapshot_id,
				'znts_notice' => 'restore-rollback-resume-complete',
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Validate capability and nonce checks for admin actions.
	 *
	 * @param string $nonce_action Nonce action name.
	 * @return void
	 */
	protected function assert_admin_action_permissions( $nonce_action ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'zignites-sentinel' ) );
		}

		check_admin_referer( $nonce_action );
	}

	/**
	 * Redirect back to the update readiness screen with a notice.
	 *
	 * @param string $notice Notice key.
	 * @return void
	 */
	protected function redirect_with_notice( $notice ) {
		$url = add_query_arg(
			array(
				'page'        => self::UPDATE_PAGE_SLUG,
				'znts_notice' => sanitize_key( $notice ),
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Get a screen notice message, if present.
	 *
	 * @return array
	 */
	protected function get_notice_message() {
		$notice = isset( $_GET['znts_notice'] ) ? sanitize_key( wp_unslash( $_GET['znts_notice'] ) ) : '';

		$messages = array(
			'preflight-complete' => array(
				'type'    => 'success',
				'message' => __( 'Update readiness scan completed.', 'zignites-sentinel' ),
			),
			'snapshot-created' => array(
				'type'    => 'success',
				'message' => __( 'Snapshot metadata was created successfully.', 'zignites-sentinel' ),
			),
			'snapshot-failed' => array(
				'type'    => 'error',
				'message' => __( 'Snapshot metadata could not be created.', 'zignites-sentinel' ),
			),
			'settings-saved' => array(
				'type'    => 'success',
				'message' => __( 'Sentinel settings were saved.', 'zignites-sentinel' ),
			),
			'update-plan-created' => array(
				'type'    => 'success',
				'message' => __( 'Manual update review plan created.', 'zignites-sentinel' ),
			),
			'update-plan-empty' => array(
				'type'    => 'warning',
				'message' => __( 'No valid update targets were selected.', 'zignites-sentinel' ),
			),
			'restore-check-complete' => array(
				'type'    => 'success',
				'message' => __( 'Restore-readiness assessment completed.', 'zignites-sentinel' ),
			),
			'restore-check-missing' => array(
				'type'    => 'error',
				'message' => __( 'The selected snapshot could not be found.', 'zignites-sentinel' ),
			),
			'restore-dry-run-complete' => array(
				'type'    => 'success',
				'message' => __( 'Restore dry-run validation completed.', 'zignites-sentinel' ),
			),
			'restore-dry-run-missing' => array(
				'type'    => 'error',
				'message' => __( 'The selected snapshot could not be found for restore dry-run validation.', 'zignites-sentinel' ),
			),
			'restore-stage-complete' => array(
				'type'    => 'success',
				'message' => __( 'Staged restore validation completed.', 'zignites-sentinel' ),
			),
			'restore-stage-missing' => array(
				'type'    => 'error',
				'message' => __( 'The selected snapshot could not be found for staged restore validation.', 'zignites-sentinel' ),
			),
			'restore-plan-complete' => array(
				'type'    => 'success',
				'message' => __( 'Restore execution plan created.', 'zignites-sentinel' ),
			),
			'restore-plan-missing' => array(
				'type'    => 'error',
				'message' => __( 'The selected snapshot could not be found for restore planning.', 'zignites-sentinel' ),
			),
			'restore-gates-refreshed' => array(
				'type'    => 'success',
				'message' => __( 'Restore checklist gates were refreshed for the selected snapshot.', 'zignites-sentinel' ),
			),
			'restore-gates-missing' => array(
				'type'    => 'error',
				'message' => __( 'The selected snapshot could not be found for restore gate refresh.', 'zignites-sentinel' ),
			),
			'restore-execution-complete' => array(
				'type'    => 'success',
				'message' => __( 'Restore execution attempt completed.', 'zignites-sentinel' ),
			),
			'restore-execution-missing' => array(
				'type'    => 'error',
				'message' => __( 'The selected snapshot could not be found for restore execution.', 'zignites-sentinel' ),
			),
			'restore-resume-complete' => array(
				'type'    => 'success',
				'message' => __( 'Restore execution resume attempt completed.', 'zignites-sentinel' ),
			),
			'restore-resume-missing' => array(
				'type'    => 'error',
				'message' => __( 'No resumable restore execution context is available for the selected snapshot.', 'zignites-sentinel' ),
			),
			'restore-checkpoint-discarded' => array(
				'type'    => 'success',
				'message' => __( 'The preserved restore execution checkpoint was discarded.', 'zignites-sentinel' ),
			),
			'restore-checkpoint-discard-missing' => array(
				'type'    => 'error',
				'message' => __( 'No preserved restore execution checkpoint is available for the selected snapshot.', 'zignites-sentinel' ),
			),
			'restore-checkpoint-discard-failed' => array(
				'type'    => 'error',
				'message' => __( 'The preserved restore execution checkpoint could not be discarded because its stage directory could not be removed.', 'zignites-sentinel' ),
			),
			'snapshot-health-baseline-captured' => array(
				'type'    => 'success',
				'message' => __( 'Snapshot health baseline captured.', 'zignites-sentinel' ),
			),
			'snapshot-health-baseline-missing' => array(
				'type'    => 'error',
				'message' => __( 'The selected snapshot could not be found for health baseline capture.', 'zignites-sentinel' ),
			),
			'snapshot-audit-missing' => array(
				'type'    => 'error',
				'message' => __( 'The selected snapshot could not be found for audit report export.', 'zignites-sentinel' ),
			),
			'snapshot-audit-verified' => array(
				'type'    => 'success',
				'message' => __( 'Snapshot audit report verification completed.', 'zignites-sentinel' ),
			),
			'restore-operator-gate-blocked' => array(
				'type'    => 'error',
				'message' => __( 'Live restore is blocked until the operator checklist is complete and current for this snapshot.', 'zignites-sentinel' ),
			),
			'restore-rollback-complete' => array(
				'type'    => 'success',
				'message' => __( 'Restore rollback attempt completed.', 'zignites-sentinel' ),
			),
			'restore-rollback-resume-complete' => array(
				'type'    => 'success',
				'message' => __( 'Restore rollback resume attempt completed.', 'zignites-sentinel' ),
			),
			'restore-rollback-missing' => array(
				'type'    => 'error',
				'message' => __( 'The selected snapshot could not be found for restore rollback.', 'zignites-sentinel' ),
			),
			'restore-rollback-resume-missing' => array(
				'type'    => 'error',
				'message' => __( 'No resumable restore rollback context is available for the selected snapshot.', 'zignites-sentinel' ),
			),
		);

		return isset( $messages[ $notice ] ) ? $messages[ $notice ] : array();
	}

	/**
	 * Get current settings merged with defaults.
	 *
	 * @return array
	 */
	protected function get_settings() {
		$settings = get_option( ZNTS_OPTION_SETTINGS, array() );
		$settings = is_array( $settings ) ? $settings : array();

		return wp_parse_args(
			$settings,
			array(
				'delete_data_on_uninstall' => 1,
				'logging_enabled'          => 1,
				'snapshot_retention_days'  => 30,
				'auto_snapshot_on_plan'    => 1,
				'restore_checkpoint_max_age_hours' => 24,
			)
		);
	}

	/**
	 * Get a selected snapshot detail for the current screen.
	 *
	 * @return array|null
	 */
	protected function get_snapshot_detail() {
		$snapshot_id = isset( $_GET['snapshot_id'] ) ? absint( wp_unslash( $_GET['snapshot_id'] ) ) : 0;

		if ( $snapshot_id < 1 ) {
			return null;
		}

		return $this->get_snapshot_detail_by_id( $snapshot_id );
	}

	/**
	 * Get a selected snapshot detail by ID.
	 *
	 * @param int $snapshot_id Snapshot ID.
	 * @return array|null
	 */
	protected function get_snapshot_detail_by_id( $snapshot_id ) {
		$snapshot_id = absint( $snapshot_id );

		if ( $snapshot_id < 1 ) {
			return null;
		}

		$snapshot = $this->snapshots->get_by_id( $snapshot_id );

		if ( ! is_array( $snapshot ) ) {
			return null;
		}

		$snapshot['active_plugins_decoded'] = $this->decode_json_field( $snapshot['active_plugins'] );
		$snapshot['metadata_decoded']       = $this->decode_json_field( $snapshot['metadata'] );

		return $snapshot;
	}

	/**
	 * Get the current snapshot label search term from the request.
	 *
	 * @return string
	 */
	protected function get_snapshot_search_term() {
		return isset( $_GET['snapshot_search'] ) ? sanitize_text_field( wp_unslash( $_GET['snapshot_search'] ) ) : '';
	}

	/**
	 * Return recent snapshots for Update Readiness with optional label filtering.
	 *
	 * @param string $search Search term.
	 * @return array
	 */
	protected function get_recent_snapshots_for_update_readiness( $search = '' ) {
		$snapshots = $this->snapshots->get_recent( 50 );
		$search    = sanitize_text_field( (string) $search );

		if ( '' === $search ) {
			return array_slice( $snapshots, 0, 12 );
		}

		$search_lower = function_exists( 'mb_strtolower' ) ? mb_strtolower( $search ) : strtolower( $search );
		$filtered     = array_values(
			array_filter(
				$snapshots,
				function( $snapshot ) use ( $search_lower ) {
					$label = isset( $snapshot['label'] ) ? (string) $snapshot['label'] : '';
					$label = function_exists( 'mb_strtolower' ) ? mb_strtolower( $label ) : strtolower( $label );

					return false !== strpos( $label, $search_lower );
				}
			)
		);

		return array_slice( $filtered, 0, 12 );
	}

	/**
	 * Fetch stored artifacts for the selected snapshot.
	 *
	 * @param array|null $snapshot Snapshot detail.
	 * @return array
	 */
	protected function get_snapshot_artifacts( $snapshot ) {
		if ( ! is_array( $snapshot ) || empty( $snapshot['id'] ) ) {
			return array();
		}

		return $this->artifacts->get_by_snapshot_id( (int) $snapshot['id'] );
	}

	/**
	 * Inspect stored rollback artifacts for the selected snapshot.
	 *
	 * @param array|null $snapshot Snapshot detail.
	 * @return array
	 */
	protected function get_artifact_diff( $snapshot ) {
		$artifacts = $this->get_snapshot_artifacts( $snapshot );

		if ( empty( $artifacts ) ) {
			return array();
		}

		return $this->artifact_inspector->inspect( $artifacts );
	}

	/**
	 * Build a comparison between the selected snapshot and current site state.
	 *
	 * @param array|null $snapshot Snapshot detail.
	 * @return array|null
	 */
	protected function get_snapshot_comparison( $snapshot ) {
		if ( ! is_array( $snapshot ) ) {
			return null;
		}

		return $this->snapshot_comparator->compare( $snapshot );
	}

	/**
	 * Build recent snapshot-scoped activity for the selected snapshot.
	 *
	 * @param array|null $snapshot Snapshot detail.
	 * @return array
	 */
	protected function get_snapshot_activity( $snapshot ) {
		if ( ! is_array( $snapshot ) || empty( $snapshot['id'] ) ) {
			return array();
		}

		$snapshot_id = (int) $snapshot['id'];
		$rows        = $this->logs->get_paginated(
			array(
				'snapshot_id' => $snapshot_id,
				'per_page'    => 30,
				'paged'       => 1,
			)
		);
		$activity    = array();

		foreach ( $rows as $row ) {
			$activity[] = $this->build_snapshot_activity_entry( $row, $snapshot_id );
		}

		return $activity;
	}

	/**
	 * Return the last restore check when it matches the selected snapshot.
	 *
	 * @param array|null $snapshot Snapshot detail.
	 * @return array|null
	 */
	protected function get_last_restore_check( $snapshot ) {
		$check = get_option( ZNTS_OPTION_LAST_RESTORE_CHECK, array() );

		if ( ! is_array( $check ) || ! is_array( $snapshot ) ) {
			return null;
		}

		$snapshot_id = isset( $snapshot['id'] ) ? absint( $snapshot['id'] ) : 0;
		$check_id    = isset( $check['snapshot_id'] ) ? absint( $check['snapshot_id'] ) : 0;

		if ( $snapshot_id < 1 || $snapshot_id !== $check_id ) {
			return null;
		}

		return $check;
	}

	/**
	 * Return the last restore dry-run when it matches the selected snapshot.
	 *
	 * @param array|null $snapshot Snapshot detail.
	 * @return array|null
	 */
	protected function get_last_restore_dry_run( $snapshot ) {
		$check = get_option( ZNTS_OPTION_LAST_RESTORE_DRY_RUN, array() );

		if ( ! is_array( $check ) || ! is_array( $snapshot ) ) {
			return null;
		}

		$snapshot_id = isset( $snapshot['id'] ) ? absint( $snapshot['id'] ) : 0;
		$check_id    = isset( $check['snapshot_id'] ) ? absint( $check['snapshot_id'] ) : 0;

		if ( $snapshot_id < 1 || $snapshot_id !== $check_id ) {
			return null;
		}

		return $check;
	}

	/**
	 * Return the last staged restore validation when it matches the selected snapshot.
	 *
	 * @param array|null $snapshot Snapshot detail.
	 * @return array|null
	 */
	protected function get_last_restore_stage( $snapshot ) {
		$check = get_option( ZNTS_OPTION_LAST_RESTORE_STAGE, array() );

		if ( ! is_array( $check ) || ! is_array( $snapshot ) ) {
			return null;
		}

		$snapshot_id = isset( $snapshot['id'] ) ? absint( $snapshot['id'] ) : 0;
		$check_id    = isset( $check['snapshot_id'] ) ? absint( $check['snapshot_id'] ) : 0;

		if ( $snapshot_id < 1 || $snapshot_id !== $check_id ) {
			return null;
		}

		return $check;
	}

	/**
	 * Return the persisted stage checkpoint when it matches the selected snapshot.
	 *
	 * @param array|null $snapshot Snapshot detail.
	 * @return array|null
	 */
	protected function get_restore_stage_checkpoint( $snapshot ) {
		if ( ! is_array( $snapshot ) || empty( $snapshot['id'] ) ) {
			return null;
		}

		$checkpoint = $this->restore_checkpoint_store->get_stage_checkpoint( (int) $snapshot['id'] );

		return ! empty( $checkpoint ) ? $checkpoint : null;
	}

	/**
	 * Return the last restore plan when it matches the selected snapshot.
	 *
	 * @param array|null $snapshot Snapshot detail.
	 * @return array|null
	 */
	protected function get_last_restore_plan( $snapshot ) {
		$check = get_option( ZNTS_OPTION_LAST_RESTORE_PLAN, array() );

		if ( ! is_array( $check ) || ! is_array( $snapshot ) ) {
			return null;
		}

		$snapshot_id = isset( $snapshot['id'] ) ? absint( $snapshot['id'] ) : 0;
		$check_id    = isset( $check['snapshot_id'] ) ? absint( $check['snapshot_id'] ) : 0;

		if ( $snapshot_id < 1 || $snapshot_id !== $check_id ) {
			return null;
		}

		return $check;
	}

	/**
	 * Return the persisted plan checkpoint when it matches the selected snapshot.
	 *
	 * @param array|null $snapshot Snapshot detail.
	 * @return array|null
	 */
	protected function get_restore_plan_checkpoint( $snapshot ) {
		if ( ! is_array( $snapshot ) || empty( $snapshot['id'] ) ) {
			return null;
		}

		$checkpoint = $this->restore_checkpoint_store->get_plan_checkpoint( (int) $snapshot['id'] );

		return ! empty( $checkpoint ) ? $checkpoint : null;
	}

	/**
	 * Return the persisted execution checkpoint when it matches the selected snapshot.
	 *
	 * @param array|null $snapshot Snapshot detail.
	 * @return array|null
	 */
	protected function get_restore_execution_checkpoint( $snapshot ) {
		if ( ! is_array( $snapshot ) || empty( $snapshot['id'] ) ) {
			return null;
		}

		$last_execution = $this->get_last_restore_execution( $snapshot );
		$run_id         = is_array( $last_execution ) && ! empty( $last_execution['run_id'] ) ? (string) $last_execution['run_id'] : '';
		$checkpoint     = $this->restore_checkpoint_store->get_execution_checkpoint( (int) $snapshot['id'], $run_id );

		return ! empty( $checkpoint ) ? $checkpoint : null;
	}

	/**
	 * Return the last snapshot health baseline when it matches the selected snapshot.
	 *
	 * @param array|null $snapshot Snapshot detail.
	 * @return array|null
	 */
	protected function get_snapshot_health_baseline( $snapshot ) {
		$check = get_option( ZNTS_OPTION_LAST_SNAPSHOT_HEALTH_BASELINE, array() );

		if ( ! is_array( $check ) || ! is_array( $snapshot ) ) {
			return null;
		}

		$snapshot_id = isset( $snapshot['id'] ) ? absint( $snapshot['id'] ) : 0;
		$check_id    = isset( $check['snapshot_id'] ) ? absint( $check['snapshot_id'] ) : 0;

		if ( $snapshot_id < 1 || $snapshot_id !== $check_id ) {
			return null;
		}

		return $check;
	}

	/**
	 * Build a snapshot health comparison matrix.
	 *
	 * @param array|null $snapshot Snapshot detail.
	 * @return array
	 */
	protected function get_snapshot_health_comparison( $snapshot ) {
		if ( ! is_array( $snapshot ) || empty( $snapshot['id'] ) ) {
			return array();
		}

		$baseline  = $this->get_snapshot_health_baseline( $snapshot );
		$execution = $this->get_last_restore_execution( $snapshot );
		$rollback  = $this->get_last_restore_rollback( $snapshot );
		$rows      = array();

		if ( is_array( $baseline ) ) {
			$rows[] = $this->build_health_snapshot_row( __( 'Baseline', 'zignites-sentinel' ), $baseline, array() );
		}

		if ( is_array( $execution ) && ! empty( $execution['health_verification'] ) && is_array( $execution['health_verification'] ) ) {
			$rows[] = $this->build_health_snapshot_row( __( 'Post-Restore', 'zignites-sentinel' ), $execution['health_verification'], is_array( $baseline ) ? $baseline : array() );
		}

		if ( is_array( $rollback ) && ! empty( $rollback['health_verification'] ) && is_array( $rollback['health_verification'] ) ) {
			$rows[] = $this->build_health_snapshot_row( __( 'Post-Rollback', 'zignites-sentinel' ), $rollback['health_verification'], is_array( $baseline ) ? $baseline : array() );
		}

		return $rows;
	}

	/**
	 * Return the last audit report verification when it matches the selected snapshot.
	 *
	 * @param array|null $snapshot Snapshot detail.
	 * @return array|null
	 */
	protected function get_audit_report_verification( $snapshot ) {
		$check = get_option( ZNTS_OPTION_LAST_AUDIT_REPORT_VERIFICATION, array() );

		if ( ! is_array( $check ) || ! is_array( $snapshot ) ) {
			return null;
		}

		$snapshot_id = isset( $snapshot['id'] ) ? absint( $snapshot['id'] ) : 0;
		$check_id    = isset( $check['snapshot_id'] ) ? absint( $check['snapshot_id'] ) : 0;

		if ( $snapshot_id < 1 || $snapshot_id !== $check_id ) {
			return null;
		}

		return $check;
	}

	/**
	 * Build the operator checklist required before live restore execution.
	 *
	 * @param array|null $snapshot  Snapshot detail.
	 * @param array|null $artifacts Optional artifact rows.
	 * @return array
	 */
	protected function get_restore_operator_checklist( $snapshot, $artifacts = null ) {
		if ( ! is_array( $snapshot ) || empty( $snapshot['id'] ) ) {
			return array();
		}

		$artifacts       = is_array( $artifacts ) ? $artifacts : $this->get_snapshot_artifacts( $snapshot );
		$baseline        = $this->get_snapshot_health_baseline( $snapshot );
		$stage_checkpoint = $this->get_restore_stage_checkpoint( $snapshot );
		$plan_checkpoint  = $this->get_restore_plan_checkpoint( $snapshot );
		$stage_result     = $this->get_fresh_restore_stage_result( $snapshot, $artifacts );
		$plan_result      = $this->get_fresh_restore_plan_result( $snapshot, $artifacts );
		$settings         = $this->get_settings();
		$max_age_hours    = isset( $settings['restore_checkpoint_max_age_hours'] ) ? (int) $settings['restore_checkpoint_max_age_hours'] : 24;

		if ( ! empty( $stage_checkpoint ) && empty( $stage_result ) && ! $this->is_checkpoint_fresh( $stage_checkpoint ) ) {
			$this->maybe_log_checkpoint_expiry( 'stage', $snapshot, $stage_checkpoint, $max_age_hours );
		}

		if ( ! empty( $plan_checkpoint ) && empty( $plan_result ) && ! $this->is_checkpoint_fresh( $plan_checkpoint ) ) {
			$this->maybe_log_checkpoint_expiry( 'plan', $snapshot, $plan_checkpoint, $max_age_hours );
		}

		$checks           = array(
			array(
				'label'   => __( 'Health baseline captured', 'zignites-sentinel' ),
				'status'  => is_array( $baseline ) ? 'pass' : 'fail',
				'message' => is_array( $baseline )
					? __( 'A health baseline exists for this snapshot.', 'zignites-sentinel' )
					: __( 'Capture a snapshot health baseline before offering live restore.', 'zignites-sentinel' ),
			),
			array(
				'label'   => __( 'Staged validation current', 'zignites-sentinel' ),
				'status'  => ! empty( $stage_result ) ? 'pass' : 'fail',
				'message' => ! empty( $stage_result )
					? sprintf(
						/* translators: %d: max age in hours */
						__( 'A matching staged validation checkpoint is ready and no older than %d hours.', 'zignites-sentinel' ),
						$max_age_hours
					)
					: ( ! empty( $stage_checkpoint )
						? __( 'The stored staged validation checkpoint is stale, expired, or no longer ready.', 'zignites-sentinel' )
						: __( 'Run staged restore validation for this snapshot package.', 'zignites-sentinel' ) ),
			),
			array(
				'label'   => __( 'Restore plan current', 'zignites-sentinel' ),
				'status'  => ! empty( $plan_result ) ? 'pass' : 'fail',
				'message' => ! empty( $plan_result )
					? sprintf(
						/* translators: %d: max age in hours */
						__( 'A matching restore plan checkpoint is ready and no older than %d hours.', 'zignites-sentinel' ),
						$max_age_hours
					)
					: ( ! empty( $plan_checkpoint )
						? __( 'The stored restore plan checkpoint is stale, expired, or blocked.', 'zignites-sentinel' )
						: __( 'Build a restore plan for this snapshot package.', 'zignites-sentinel' ) ),
			),
		);

		$can_execute = true;

		foreach ( $checks as $check ) {
			if ( 'pass' !== $check['status'] ) {
				$can_execute = false;
				break;
			}
		}

		return array(
			'status'      => $can_execute ? 'ready' : 'blocked',
			'can_execute' => $can_execute,
			'max_age_hours' => $max_age_hours,
			'checks'      => $checks,
		);
	}

	/**
	 * Build dashboard restore readiness summary for the latest snapshot.
	 *
	 * @return array
	 */
	protected function get_restore_dashboard_summary() {
		$recent_snapshots = $this->snapshots->get_recent( 1 );

		if ( empty( $recent_snapshots[0]['id'] ) ) {
			return array();
		}

		$snapshot = $this->get_snapshot_detail_by_id( (int) $recent_snapshots[0]['id'] );

		if ( ! is_array( $snapshot ) ) {
			return array();
		}

		$artifacts  = $this->get_snapshot_artifacts( $snapshot );
		$checklist  = $this->get_restore_operator_checklist( $snapshot, $artifacts );
		$baseline   = $this->get_snapshot_health_baseline( $snapshot );
		$stage      = $this->get_restore_stage_checkpoint( $snapshot );
		$plan       = $this->get_restore_plan_checkpoint( $snapshot );
		$execution  = $this->get_last_restore_execution( $snapshot );
		$rollback   = $this->get_last_restore_rollback( $snapshot );
		$stage_timing = is_array( $stage ) ? $this->get_checkpoint_timing_summary( $stage ) : array();
		$plan_timing  = is_array( $plan ) ? $this->get_checkpoint_timing_summary( $plan ) : array();
		$stage_fresh = ! empty( $stage_timing['is_fresh'] );
		$plan_fresh  = ! empty( $plan_timing['is_fresh'] );

		return array(
			'snapshot'        => $snapshot,
			'checklist'       => $checklist,
			'baseline'        => $baseline,
			'stage_checkpoint'=> $stage,
			'plan_checkpoint' => $plan,
			'last_execution'  => $execution,
			'last_rollback'   => $rollback,
			'summary_rows'    => array(
				array(
					'label'   => __( 'Health baseline', 'zignites-sentinel' ),
					'status'  => is_array( $baseline ) ? 'pass' : 'fail',
					'message' => is_array( $baseline )
						? __( 'Captured for the latest snapshot.', 'zignites-sentinel' )
						: __( 'Not captured yet.', 'zignites-sentinel' ),
				),
				array(
					'label'   => __( 'Stage checkpoint', 'zignites-sentinel' ),
					'status'  => $stage_fresh ? 'pass' : ( is_array( $stage ) ? 'warning' : 'fail' ),
					'message' => $stage_fresh
						? sprintf(
							/* translators: %s: countdown label */
							__( 'Fresh staged validation is available. %s', 'zignites-sentinel' ),
							isset( $stage_timing['label'] ) ? $stage_timing['label'] : ''
						)
						: ( is_array( $stage )
							? sprintf(
								/* translators: %s: countdown label */
								__( 'Stored checkpoint exists but is expired or no longer reusable. %s', 'zignites-sentinel' ),
								isset( $stage_timing['label'] ) ? $stage_timing['label'] : ''
							)
							: __( 'No staged validation checkpoint is available.', 'zignites-sentinel' ) ),
				),
				array(
					'label'   => __( 'Restore plan', 'zignites-sentinel' ),
					'status'  => $plan_fresh ? 'pass' : ( is_array( $plan ) ? 'warning' : 'fail' ),
					'message' => $plan_fresh
						? sprintf(
							/* translators: %s: countdown label */
							__( 'Fresh restore plan is available. %s', 'zignites-sentinel' ),
							isset( $plan_timing['label'] ) ? $plan_timing['label'] : ''
						)
						: ( is_array( $plan )
							? sprintf(
								/* translators: %s: countdown label */
								__( 'Stored plan exists but is expired or blocked. %s', 'zignites-sentinel' ),
								isset( $plan_timing['label'] ) ? $plan_timing['label'] : ''
							)
							: __( 'No restore plan checkpoint is available.', 'zignites-sentinel' ) ),
				),
			),
			'can_execute'     => ! empty( $checklist['can_execute'] ),
			'status'          => ! empty( $checklist['can_execute'] ) ? 'ready' : 'blocked',
			'status_badge'    => ! empty( $checklist['can_execute'] ) ? 'info' : 'critical',
			'detail_url'      => add_query_arg(
				array(
					'page'        => self::UPDATE_PAGE_SLUG,
					'snapshot_id' => (int) $snapshot['id'],
				),
				admin_url( 'admin.php' )
			),
			'activity_url'    => $this->get_snapshot_activity_url( (int) $snapshot['id'] ),
		);
	}

	/**
	 * Build a compact health strip for the latest snapshot on the dashboard.
	 *
	 * @return array
	 */
	protected function get_restore_dashboard_health_strip() {
		$recent_snapshots = $this->snapshots->get_recent( 1 );

		if ( empty( $recent_snapshots[0]['id'] ) ) {
			return array();
		}

		$snapshot = $this->get_snapshot_detail_by_id( (int) $recent_snapshots[0]['id'] );

		if ( ! is_array( $snapshot ) ) {
			return array();
		}

		$rows = $this->get_snapshot_health_comparison( $snapshot );

		if ( empty( $rows ) ) {
			return array(
				'snapshot'   => $snapshot,
				'rows'       => array(),
				'detail_url' => add_query_arg(
					array(
						'page'        => self::UPDATE_PAGE_SLUG,
						'snapshot_id' => (int) $snapshot['id'],
					),
					admin_url( 'admin.php' )
				),
			);
		}

		return array(
			'snapshot'   => $snapshot,
			'rows'       => $rows,
			'detail_url' => add_query_arg(
				array(
					'page'        => self::UPDATE_PAGE_SLUG,
					'snapshot_id' => (int) $snapshot['id'],
				),
				admin_url( 'admin.php' )
			),
		);
	}

	/**
	 * Persist a staged restore validation result for a snapshot.
	 *
	 * @param array $snapshot  Snapshot detail.
	 * @param array $artifacts Snapshot artifacts.
	 * @return array
	 */
	protected function run_restore_stage_for_snapshot( array $snapshot, array $artifacts ) {
		$snapshot_id                = isset( $snapshot['id'] ) ? (int) $snapshot['id'] : 0;
		$stage_run                  = $this->restore_staging_manager->stage_and_validate( $snapshot, $artifacts );
		$stage_run['snapshot_id']   = $snapshot_id;

		update_option( ZNTS_OPTION_LAST_RESTORE_STAGE, $stage_run, false );
		$this->restore_checkpoint_store->store_stage_checkpoint( $snapshot, $artifacts, $stage_run );
		$this->logger->log(
			'restore_stage_completed',
			$this->map_assessment_status_to_severity( $stage_run['status'] ),
			'restore-stage',
			__( 'Staged restore validation completed.', 'zignites-sentinel' ),
			array(
				'snapshot_id'       => $snapshot_id,
				'status'            => $stage_run['status'],
				'summary'           => isset( $stage_run['summary'] ) ? $stage_run['summary'] : array(),
				'cleanup_completed' => ! empty( $stage_run['cleanup_completed'] ),
			)
		);

		return $stage_run;
	}

	/**
	 * Persist a restore execution plan for a snapshot.
	 *
	 * @param array $snapshot  Snapshot detail.
	 * @param array $artifacts Snapshot artifacts.
	 * @return array
	 */
	protected function run_restore_plan_for_snapshot( array $snapshot, array $artifacts ) {
		$snapshot_id        = isset( $snapshot['id'] ) ? (int) $snapshot['id'] : 0;
		$plan               = $this->restore_execution_planner->build_plan( $snapshot, $artifacts );
		$plan['snapshot_id'] = $snapshot_id;

		update_option( ZNTS_OPTION_LAST_RESTORE_PLAN, $plan, false );
		$this->restore_checkpoint_store->store_plan_checkpoint( $snapshot, $artifacts, $plan );
		$this->logger->log(
			'restore_plan_created',
			$this->map_assessment_status_to_severity( $plan['status'] ),
			'restore-plan',
			__( 'Restore execution plan created.', 'zignites-sentinel' ),
			array(
				'snapshot_id' => $snapshot_id,
				'status'      => $plan['status'],
				'summary'     => isset( $plan['summary'] ) ? $plan['summary'] : array(),
				'item_count'  => isset( $plan['items'] ) && is_array( $plan['items'] ) ? count( $plan['items'] ) : 0,
			)
		);

		return $plan;
	}

	/**
	 * Resolve a staged-validation gate result for restore execution.
	 *
	 * @param array $snapshot  Snapshot detail.
	 * @param array $artifacts Snapshot artifacts.
	 * @return array
	 */
	protected function get_restore_stage_gate_result( array $snapshot, array $artifacts, $log_invalid = false ) {
		$checkpoint = $this->get_restore_stage_checkpoint( $snapshot );
		$checkpoint_result = $this->restore_checkpoint_store->get_matching_stage_result( $snapshot, $artifacts );

		if ( ! empty( $checkpoint_result ) ) {
			return $checkpoint_result;
		}

		if ( $log_invalid && ! empty( $checkpoint ) ) {
			$this->log_checkpoint_invalidation( 'stage', $snapshot, $checkpoint, $artifacts );
		}

		$last_stage = $this->get_last_restore_stage( $snapshot );

		return is_array( $last_stage ) ? $last_stage : array();
	}

	/**
	 * Resolve a restore-plan gate result for restore execution.
	 *
	 * @param array $snapshot  Snapshot detail.
	 * @param array $artifacts Snapshot artifacts.
	 * @return array
	 */
	protected function get_restore_plan_gate_result( array $snapshot, array $artifacts, $log_invalid = false ) {
		$checkpoint = $this->get_restore_plan_checkpoint( $snapshot );
		$checkpoint_result = $this->restore_checkpoint_store->get_matching_plan_result( $snapshot, $artifacts );

		if ( ! empty( $checkpoint_result ) ) {
			return $checkpoint_result;
		}

		if ( $log_invalid && ! empty( $checkpoint ) ) {
			$this->log_checkpoint_invalidation( 'plan', $snapshot, $checkpoint, $artifacts );
		}

		$last_plan = $this->get_last_restore_plan( $snapshot );

		return is_array( $last_plan ) ? $last_plan : array();
	}

	/**
	 * Log checkpoint invalidation when fingerprint or status no longer qualifies for resume.
	 *
	 * @param string $type       Checkpoint type.
	 * @param array  $snapshot   Snapshot detail.
	 * @param array  $checkpoint Checkpoint payload.
	 * @param array  $artifacts  Snapshot artifacts.
	 * @return void
	 */
	protected function log_checkpoint_invalidation( $type, array $snapshot, array $checkpoint, array $artifacts ) {
		$fingerprint_matches = $this->restore_checkpoint_store->checkpoint_matches_artifacts( $checkpoint, $artifacts );
		$status              = isset( $checkpoint['status'] ) ? sanitize_key( (string) $checkpoint['status'] ) : '';
		$result              = isset( $checkpoint['result'] ) && is_array( $checkpoint['result'] ) ? $checkpoint['result'] : array();
		$reason              = $fingerprint_matches
			? __( 'The stored checkpoint result no longer satisfies execution requirements.', 'zignites-sentinel' )
			: __( 'The stored checkpoint package fingerprint no longer matches the current rollback package artifact.', 'zignites-sentinel' );

		$this->logger->log(
			'restore_checkpoint_invalidated',
			'warning',
			'restore-checkpoint',
			sprintf(
				/* translators: %s: checkpoint type */
				__( 'Restore %s checkpoint could not be reused.', 'zignites-sentinel' ),
				sanitize_text_field( (string) $type )
			),
			array(
				'snapshot_id'          => isset( $snapshot['id'] ) ? (int) $snapshot['id'] : 0,
				'checkpoint_type'      => sanitize_key( (string) $type ),
				'checkpoint_status'    => $status,
				'fingerprint_matches'  => $fingerprint_matches,
				'reason'               => $reason,
				'generated_at'         => isset( $checkpoint['generated_at'] ) ? (string) $checkpoint['generated_at'] : '',
				'result_status'        => isset( $result['status'] ) ? (string) $result['status'] : '',
			)
		);
	}

	/**
	 * Log checkpoint expiry once per unique checkpoint state.
	 *
	 * @param string $type          Checkpoint type.
	 * @param array  $snapshot      Snapshot detail.
	 * @param array  $checkpoint    Checkpoint payload.
	 * @param int    $max_age_hours Max allowed age in hours.
	 * @return void
	 */
	protected function maybe_log_checkpoint_expiry( $type, array $snapshot, array $checkpoint, $max_age_hours ) {
		$snapshot_id  = isset( $snapshot['id'] ) ? (int) $snapshot['id'] : 0;
		$type         = sanitize_key( (string) $type );
		$generated_at = isset( $checkpoint['generated_at'] ) ? (string) $checkpoint['generated_at'] : '';
		$marker       = md5(
			wp_json_encode(
				array(
					'snapshot_id'   => $snapshot_id,
					'type'          => $type,
					'generated_at'  => $generated_at,
					'max_age_hours' => (int) $max_age_hours,
				)
			)
		);
		$markers      = get_option( ZNTS_OPTION_RESTORE_CHECKPOINT_EXPIRY_LOG, array() );
		$markers      = is_array( $markers ) ? $markers : array();

		if ( isset( $markers[ $marker ] ) ) {
			return;
		}

		$generated_ts = '' !== $generated_at ? strtotime( $generated_at ) : false;
		$age_seconds  = false !== $generated_ts ? max( 0, time() - $generated_ts ) : 0;
		$reason       = false === $generated_ts
			? __( 'The checkpoint timestamp is missing or invalid.', 'zignites-sentinel' )
			: sprintf(
				/* translators: %d: max age in hours */
				__( 'The checkpoint is older than the configured %d-hour age window.', 'zignites-sentinel' ),
				(int) $max_age_hours
			);

		$this->logger->log(
			'restore_checkpoint_expired',
			'warning',
			'restore-checkpoint',
			sprintf(
				/* translators: %s: checkpoint type */
				__( 'Restore %s checkpoint expired and can no longer be offered.', 'zignites-sentinel' ),
				$type
			),
			array(
				'snapshot_id'    => $snapshot_id,
				'checkpoint_type'=> $type,
				'generated_at'   => $generated_at,
				'max_age_hours'  => (int) $max_age_hours,
				'age_seconds'    => (int) $age_seconds,
				'reason'         => $reason,
			)
		);

		$markers[ $marker ] = current_time( 'mysql', true );

		if ( count( $markers ) > 50 ) {
			$markers = array_slice( $markers, -50, null, true );
		}

		update_option( ZNTS_OPTION_RESTORE_CHECKPOINT_EXPIRY_LOG, $markers, false );
	}

	/**
	 * Return the last restore execution when it matches the selected snapshot.
	 *
	 * @param array|null $snapshot Snapshot detail.
	 * @return array|null
	 */
	protected function get_last_restore_execution( $snapshot ) {
		$check = get_option( ZNTS_OPTION_LAST_RESTORE_EXECUTION, array() );

		if ( ! is_array( $check ) || ! is_array( $snapshot ) ) {
			return null;
		}

		$snapshot_id = isset( $snapshot['id'] ) ? absint( $snapshot['id'] ) : 0;
		$check_id    = isset( $check['snapshot_id'] ) ? absint( $check['snapshot_id'] ) : 0;

		if ( $snapshot_id < 1 || $snapshot_id !== $check_id ) {
			return null;
		}

		return $check;
	}

	/**
	 * Return resumable restore execution context for the selected snapshot.
	 *
	 * @param array|null $snapshot Snapshot detail.
	 * @return array
	 */
	protected function get_restore_resume_context( $snapshot ) {
		if ( ! is_array( $snapshot ) || empty( $snapshot['id'] ) ) {
			return array();
		}

		$last_execution = $this->get_last_restore_execution( $snapshot );
		$context        = $this->restore_journal_recorder->get_resume_context(
			RestoreExecutor::JOURNAL_SOURCE,
			(int) $snapshot['id'],
			is_array( $last_execution ) ? $last_execution : array()
		);

		if ( empty( $context['can_resume'] ) ) {
			return array();
		}

		return $context;
	}

	/**
	 * Return resumable rollback context for the selected snapshot.
	 *
	 * @param array|null $snapshot Snapshot detail.
	 * @return array
	 */
	protected function get_restore_rollback_resume_context( $snapshot ) {
		if ( ! is_array( $snapshot ) || empty( $snapshot['id'] ) ) {
			return array();
		}

		$last_rollback = $this->get_last_restore_rollback( $snapshot );
		$context       = $this->restore_journal_recorder->get_resume_context(
			RestoreRollbackManager::JOURNAL_SOURCE,
			(int) $snapshot['id'],
			is_array( $last_rollback ) ? $last_rollback : array()
		);

		if ( empty( $context['can_resume'] ) ) {
			return array();
		}

		return $context;
	}

	/**
	 * Build summary cards for the latest checkpoint and run state.
	 *
	 * @param array|null $snapshot Snapshot detail.
	 * @return array
	 */
	protected function get_restore_run_cards( $snapshot ) {
		if ( ! is_array( $snapshot ) || empty( $snapshot['id'] ) ) {
			return array();
		}

		$cards            = array();
		$snapshot_id      = (int) $snapshot['id'];
		$stage_checkpoint = $this->get_restore_stage_checkpoint( $snapshot );
		$plan_checkpoint  = $this->get_restore_plan_checkpoint( $snapshot );
		$last_execution   = $this->get_last_restore_execution( $snapshot );
		$last_rollback    = $this->get_last_restore_rollback( $snapshot );
		$execution_checkpoint = $this->get_restore_execution_checkpoint( $snapshot );
		$restore_resume   = $this->get_restore_resume_context( $snapshot );
		$rollback_resume  = $this->get_restore_rollback_resume_context( $snapshot );

		if ( is_array( $stage_checkpoint ) ) {
			$cards[] = $this->build_checkpoint_card(
				__( 'Stage Checkpoint', 'zignites-sentinel' ),
				$stage_checkpoint,
				sprintf(
					/* translators: %d: pass count */
					__( '%d passing checks recorded.', 'zignites-sentinel' ),
					isset( $stage_checkpoint['result']['summary']['pass'] ) ? (int) $stage_checkpoint['result']['summary']['pass'] : 0
				)
			);
		}

		if ( is_array( $plan_checkpoint ) ) {
			$cards[] = $this->build_checkpoint_card(
				__( 'Plan Checkpoint', 'zignites-sentinel' ),
				$plan_checkpoint,
				sprintf(
					/* translators: %d: item count */
					__( '%d restore items prepared.', 'zignites-sentinel' ),
					isset( $plan_checkpoint['result']['items'] ) && is_array( $plan_checkpoint['result']['items'] ) ? count( $plan_checkpoint['result']['items'] ) : 0
				)
			);
		}

		if ( is_array( $last_execution ) ) {
			$cards[] = $this->build_run_card(
				__( 'Latest Restore Run', 'zignites-sentinel' ),
				$last_execution,
				RestoreExecutor::JOURNAL_SOURCE,
				$restore_resume,
				$execution_checkpoint,
				$snapshot_id
			);
		}

		if ( is_array( $last_rollback ) ) {
			$cards[] = $this->build_run_card(
				__( 'Latest Rollback Run', 'zignites-sentinel' ),
				$last_rollback,
				RestoreRollbackManager::JOURNAL_SOURCE,
				$rollback_resume,
				array(),
				$snapshot_id
			);
		}

		return $cards;
	}

	/**
	 * Fetch recent non-journal operational restore events.
	 *
	 * @return array
	 */
	protected function get_operational_events( array $log_filters = array() ) {
		$events = $this->logs->get_recent_by_sources(
			array(
				'restore-checkpoint',
				'restore-maintenance',
				'snapshot-health',
				'snapshot-audit',
			),
			10
		);

		if ( empty( $log_filters['snapshot_id'] ) ) {
			return $events;
		}

		$snapshot_id = (int) $log_filters['snapshot_id'];

		return array_values(
			array_filter(
				$events,
				function( $event ) use ( $snapshot_id ) {
					return $snapshot_id === $this->get_snapshot_id_from_log_context( $event );
				}
			)
		);
	}

	/**
	 * Build a checkpoint summary card.
	 *
	 * @param string $title         Card title.
	 * @param array  $checkpoint    Checkpoint data.
	 * @param string $summary_line  Summary line.
	 * @return array
	 */
	protected function build_checkpoint_card( $title, array $checkpoint, $summary_line ) {
		$fingerprint = isset( $checkpoint['package_fingerprint'] ) && is_array( $checkpoint['package_fingerprint'] ) ? $checkpoint['package_fingerprint'] : array();
		$source_path = isset( $fingerprint['source_path'] ) ? (string) $fingerprint['source_path'] : '';
		$timing      = $this->get_checkpoint_timing_summary( $checkpoint );
		$secondary   = array();

		if ( '' !== $source_path ) {
			$secondary[] = sprintf( __( 'Package: %s', 'zignites-sentinel' ), $source_path );
		}

		if ( ! empty( $timing['label'] ) ) {
			$secondary[] = $timing['label'];
		}

		return array(
			'title'      => $title,
			'status'     => isset( $checkpoint['status'] ) ? (string) $checkpoint['status'] : '',
			'badge'      => $this->map_readiness_badge( isset( $checkpoint['status'] ) ? $checkpoint['status'] : '' ),
			'timestamp'  => isset( $checkpoint['generated_at'] ) ? (string) $checkpoint['generated_at'] : '',
			'primary'    => (string) $summary_line,
			'secondary'  => implode( ' ', $secondary ),
			'link_url'   => '',
			'link_label' => '',
		);
	}

	/**
	 * Build checkpoint timing metadata from the configured freshness window.
	 *
	 * @param array $checkpoint Checkpoint data.
	 * @return array
	 */
	protected function get_checkpoint_timing_summary( array $checkpoint ) {
		$generated_at = isset( $checkpoint['generated_at'] ) ? (string) $checkpoint['generated_at'] : '';
		$generated_ts = '' !== $generated_at ? strtotime( $generated_at ) : false;
		$settings     = $this->get_settings();
		$max_age_hours = isset( $settings['restore_checkpoint_max_age_hours'] ) ? max( 1, (int) $settings['restore_checkpoint_max_age_hours'] ) : 24;

		if ( false === $generated_ts ) {
			return array(
				'is_fresh'       => false,
				'generated_at'   => $generated_at,
				'expires_at'     => '',
				'seconds_until'  => 0,
				'label'          => __( 'Checkpoint timestamp is invalid.', 'zignites-sentinel' ),
			);
		}

		$expires_ts    = $generated_ts + ( $max_age_hours * HOUR_IN_SECONDS );
		$seconds_until = $expires_ts - time();
		$duration      = $this->format_duration_seconds( abs( $seconds_until ) );
		$label         = $seconds_until >= 0
			? sprintf(
				/* translators: %s: remaining duration */
				__( 'Expires in %s.', 'zignites-sentinel' ),
				$duration
			)
			: sprintf(
				/* translators: %s: expired duration */
				__( 'Expired %s ago.', 'zignites-sentinel' ),
				$duration
			);

		return array(
			'is_fresh'      => $seconds_until >= 0,
			'generated_at'  => $generated_at,
			'expires_at'    => gmdate( 'Y-m-d H:i:s', $expires_ts ),
			'seconds_until' => (int) $seconds_until,
			'label'         => $label,
		);
	}

	/**
	 * Format a short human-readable duration.
	 *
	 * @param int $seconds Duration in seconds.
	 * @return string
	 */
	protected function format_duration_seconds( $seconds ) {
		$seconds = max( 0, (int) $seconds );
		$hours   = (int) floor( $seconds / HOUR_IN_SECONDS );
		$minutes = (int) floor( ( $seconds % HOUR_IN_SECONDS ) / MINUTE_IN_SECONDS );

		if ( $hours > 0 ) {
			return sprintf(
				/* translators: 1: hours, 2: minutes */
				__( '%1$dh %2$dm', 'zignites-sentinel' ),
				$hours,
				$minutes
			);
		}

		return sprintf(
			/* translators: %d: minutes */
			__( '%dm', 'zignites-sentinel' ),
			max( 1, $minutes )
		);
	}

	/**
	 * Build a restore or rollback run summary card.
	 *
	 * @param string $title          Card title.
	 * @param array  $result         Result payload.
	 * @param string $journal_source Journal source.
	 * @param array  $resume_context Resume context.
	 * @return array
	 */
	protected function build_run_card( $title, array $result, $journal_source, array $resume_context, array $execution_checkpoint = array(), $snapshot_id = 0 ) {
		$run_id      = isset( $result['run_id'] ) ? (string) $result['run_id'] : '';
		$summary     = isset( $result['summary'] ) && is_array( $result['summary'] ) ? $result['summary'] : array();
		$primary     = sprintf(
			/* translators: 1: pass count, 2: warning count, 3: fail count */
			__( '%1$d pass, %2$d warning, %3$d fail.', 'zignites-sentinel' ),
			isset( $summary['pass'] ) ? (int) $summary['pass'] : 0,
			isset( $summary['warning'] ) ? (int) $summary['warning'] : 0,
			isset( $summary['fail'] ) ? (int) $summary['fail'] : 0
		);
		$secondary   = ! empty( $resume_context['can_resume'] )
			? sprintf(
				/* translators: %d: completed item count */
				__( 'Resume available with %d completed items.', 'zignites-sentinel' ),
				isset( $resume_context['completed_item_count'] ) ? (int) $resume_context['completed_item_count'] : 0
			)
			: __( 'No resume action is currently required.', 'zignites-sentinel' );

		if ( ! empty( $result['health_verification']['status'] ) ) {
			$secondary = sprintf(
				/* translators: %s: health status */
				__( 'Health: %s', 'zignites-sentinel' ),
				(string) $result['health_verification']['status']
			);
		}

		if ( ! empty( $execution_checkpoint['checkpoint'] ) && is_array( $execution_checkpoint['checkpoint'] ) ) {
			$checkpoint_state = $execution_checkpoint['checkpoint'];
			$stage_reuse      = ! empty( $checkpoint_state['stage_ready'] ) ? __( 'Stage reuse ready.', 'zignites-sentinel' ) : __( 'No preserved stage.', 'zignites-sentinel' );
			$health_reuse     = ! empty( $checkpoint_state['health_completed'] ) ? __( 'Health reuse ready.', 'zignites-sentinel' ) : __( 'Health will rerun.', 'zignites-sentinel' );
			$secondary        = $stage_reuse . ' ' . $health_reuse;
		}

		return array(
			'title'      => $title,
			'status'     => isset( $result['status'] ) ? (string) $result['status'] : '',
			'badge'      => $this->map_run_badge( isset( $result['status'] ) ? $result['status'] : '' ),
			'timestamp'  => isset( $result['generated_at'] ) ? (string) $result['generated_at'] : '',
			'primary'    => $primary,
			'secondary'  => $secondary,
			'link_url'   => '' !== $run_id ? $this->get_run_journal_url( $journal_source, $run_id, $snapshot_id ) : '',
			'link_label' => '' !== $run_id ? sprintf( __( 'Run ID: %s', 'zignites-sentinel' ), $run_id ) : '',
		);
	}

	/**
	 * Map readiness-style statuses to a badge class.
	 *
	 * @param string $status Status value.
	 * @return string
	 */
	protected function map_readiness_badge( $status ) {
		$status = sanitize_key( (string) $status );

		if ( 'blocked' === $status ) {
			return 'critical';
		}

		if ( 'caution' === $status ) {
			return 'warning';
		}

		return 'info';
	}

	/**
	 * Map run statuses to a badge class.
	 *
	 * @param string $status Status value.
	 * @return string
	 */
	protected function map_run_badge( $status ) {
		$status = sanitize_key( (string) $status );

		if ( 'blocked' === $status ) {
			return 'critical';
		}

		if ( 'partial' === $status ) {
			return 'warning';
		}

		return 'info';
	}

	/**
	 * Build a URL to the Event Logs journal view for a run.
	 *
	 * @param string $source Journal source.
	 * @param string $run_id Run ID.
	 * @return string
	 */
	protected function get_run_journal_url( $source, $run_id, $snapshot_id = 0 ) {
		$args = array(
			'page'   => self::LOGS_PAGE_SLUG,
			'source' => sanitize_text_field( (string) $source ),
			'run_id' => sanitize_text_field( (string) $run_id ),
		);

		if ( $snapshot_id > 0 ) {
			$args['snapshot_id'] = absint( $snapshot_id );
		}

		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	/**
	 * Return run-specific journal data for the Event Logs screen.
	 *
	 * @param array $log_filters Current log filters.
	 * @return array
	 */
	protected function get_run_journal( array $log_filters ) {
		$run_id = isset( $log_filters['run_id'] ) ? sanitize_text_field( (string) $log_filters['run_id'] ) : '';
		$source = isset( $log_filters['source'] ) ? sanitize_text_field( (string) $log_filters['source'] ) : '';

		if ( '' === $run_id || '' === $source ) {
			return array();
		}

		if ( ! in_array( $source, array( RestoreExecutor::JOURNAL_SOURCE, RestoreRollbackManager::JOURNAL_SOURCE ), true ) ) {
			return array();
		}

		$entries      = $this->logs->get_paginated( array_merge( $log_filters, array( 'per_page' => 250, 'paged' => 1 ) ) );
		$journal_rows = array();

		foreach ( $entries as $row ) {
			$context = isset( $row['context'] ) ? json_decode( (string) $row['context'], true ) : array();

			if ( ! is_array( $context ) || empty( $context['entry'] ) || ! is_array( $context['entry'] ) ) {
				continue;
			}

			$journal_rows[] = $context['entry'];
		}

		return array(
			'run_id'  => $run_id,
			'source'  => $source,
			'entries' => $journal_rows,
		);
	}

	/**
	 * Return recent run summaries for the Event Logs screen.
	 *
	 * @param array $log_filters Current log filters.
	 * @return array
	 */
	protected function get_run_summaries( array $log_filters ) {
		$source = isset( $log_filters['source'] ) ? sanitize_text_field( (string) $log_filters['source'] ) : '';
		$run_id = isset( $log_filters['run_id'] ) ? sanitize_text_field( (string) $log_filters['run_id'] ) : '';
		$snapshot_id = isset( $log_filters['snapshot_id'] ) ? absint( $log_filters['snapshot_id'] ) : 0;

		if ( '' !== $run_id ) {
			return array();
		}

		if ( '' !== $source && ! in_array( $source, array( RestoreExecutor::JOURNAL_SOURCE, RestoreRollbackManager::JOURNAL_SOURCE ), true ) ) {
			return array();
		}

		$summaries = $this->restore_journal_recorder->summarize_recent_runs( $source, 300 );

		if ( $snapshot_id < 1 ) {
			return $summaries;
		}

		return array_values(
			array_filter(
				$summaries,
				function( $summary ) use ( $snapshot_id ) {
					return isset( $summary['snapshot_id'] ) && (int) $summary['snapshot_id'] === $snapshot_id;
				}
			)
		);
	}

	/**
	 * Return the last restore rollback when it matches the selected snapshot.
	 *
	 * @param array|null $snapshot Snapshot detail.
	 * @return array|null
	 */
	protected function get_last_restore_rollback( $snapshot ) {
		$check = get_option( ZNTS_OPTION_LAST_RESTORE_ROLLBACK, array() );

		if ( ! is_array( $check ) || ! is_array( $snapshot ) ) {
			return null;
		}

		$snapshot_id = isset( $snapshot['id'] ) ? absint( $snapshot['id'] ) : 0;
		$check_id    = isset( $check['snapshot_id'] ) ? absint( $check['snapshot_id'] ) : 0;

		if ( $snapshot_id < 1 || $snapshot_id !== $check_id ) {
			return null;
		}

		return $check;
	}

	/**
	 * Decode a stored JSON field into an array.
	 *
	 * @param string $value JSON string.
	 * @return array
	 */
	protected function decode_json_field( $value ) {
		$decoded = json_decode( (string) $value, true );

		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * Get a selected log detail for the current screen.
	 *
	 * @return array|null
	 */
	protected function get_log_detail() {
		$log_id = isset( $_GET['log_id'] ) ? absint( wp_unslash( $_GET['log_id'] ) ) : 0;

		if ( $log_id < 1 ) {
			return null;
		}

		$log = $this->logs->get_by_id( $log_id );

		if ( ! is_array( $log ) ) {
			return null;
		}

		$log['context_decoded'] = $this->decode_json_field( isset( $log['context'] ) ? $log['context'] : '' );

		return $log;
	}

	/**
	 * Collect filters for the event log screen.
	 *
	 * @return array
	 */
	protected function get_log_filters() {
		$severity    = isset( $_GET['severity'] ) ? sanitize_key( wp_unslash( $_GET['severity'] ) ) : '';
		$source      = isset( $_GET['source'] ) ? sanitize_text_field( wp_unslash( $_GET['source'] ) ) : '';
		$run_id      = isset( $_GET['run_id'] ) ? sanitize_text_field( wp_unslash( $_GET['run_id'] ) ) : '';
		$snapshot_id = isset( $_GET['snapshot_id'] ) ? absint( wp_unslash( $_GET['snapshot_id'] ) ) : 0;
		$search      = isset( $_GET['log_search'] ) ? sanitize_text_field( wp_unslash( $_GET['log_search'] ) ) : '';
		$paged       = isset( $_GET['paged'] ) ? absint( wp_unslash( $_GET['paged'] ) ) : 1;

		if ( ! in_array( $severity, array( '', 'info', 'warning', 'error', 'critical' ), true ) ) {
			$severity = '';
		}

		return array(
			'severity'    => $severity,
			'source'      => $source,
			'run_id'      => $run_id,
			'snapshot_id' => $snapshot_id,
			'search'      => $search,
			'paged'       => max( 1, $paged ),
		);
	}

	/**
	 * Build a snapshot activity row for the update readiness screen.
	 *
	 * @param array $row         Log row.
	 * @param int   $snapshot_id Snapshot ID.
	 * @return array
	 */
	protected function build_snapshot_activity_entry( array $row, $snapshot_id ) {
		$context  = $this->decode_json_field( isset( $row['context'] ) ? $row['context'] : '' );
		$run_id   = isset( $context['run_id'] ) ? sanitize_text_field( (string) $context['run_id'] ) : '';
		$source   = isset( $row['source'] ) ? sanitize_text_field( (string) $row['source'] ) : '';
		$is_run   = in_array( $source, array( RestoreExecutor::JOURNAL_SOURCE, RestoreRollbackManager::JOURNAL_SOURCE ), true );
		$detail   = add_query_arg(
			array(
				'page'        => self::LOGS_PAGE_SLUG,
				'snapshot_id' => absint( $snapshot_id ),
				'log_id'      => isset( $row['id'] ) ? (int) $row['id'] : 0,
			),
			admin_url( 'admin.php' )
		);
		$journal  = ( $is_run && '' !== $run_id ) ? $this->get_run_journal_url( $source, $run_id, $snapshot_id ) : '';

		return array(
			'created_at'   => isset( $row['created_at'] ) ? (string) $row['created_at'] : '',
			'severity'     => isset( $row['severity'] ) ? (string) $row['severity'] : 'info',
			'source'       => $source,
			'event_type'   => isset( $row['event_type'] ) ? (string) $row['event_type'] : '',
			'message'      => isset( $row['message'] ) ? (string) $row['message'] : '',
			'run_id'       => $run_id,
			'detail_url'   => $detail,
			'journal_url'  => $journal,
			'journal_label'=> '' !== $run_id ? sprintf( __( 'Run %s', 'zignites-sentinel' ), $run_id ) : '',
		);
	}

	/**
	 * Build an Event Logs URL scoped to a selected snapshot.
	 *
	 * @param int $snapshot_id Snapshot ID.
	 * @return string
	 */
	protected function get_snapshot_activity_url( $snapshot_id ) {
		$snapshot_id = absint( $snapshot_id );

		return add_query_arg(
			array(
				'page'        => self::LOGS_PAGE_SLUG,
				'snapshot_id' => $snapshot_id,
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Extract a snapshot ID from a log row context.
	 *
	 * @param array $log Log row.
	 * @return int
	 */
	protected function get_snapshot_id_from_log_context( array $log ) {
		$context = $this->decode_json_field( isset( $log['context'] ) ? $log['context'] : '' );

		if ( isset( $context['snapshot_id'] ) ) {
			return absint( $context['snapshot_id'] );
		}

		return 0;
	}

	/**
	 * Attach a fresh health verification block to a result when appropriate.
	 *
	 * @param array $snapshot Snapshot row.
	 * @param array $result   Restore or rollback result.
	 * @return array
	 */
	protected function attach_health_verification_to_result( array $snapshot, array $result ) {
		if ( empty( $result['status'] ) || 'blocked' === $result['status'] ) {
			return $result;
		}

		$result['health_verification'] = $this->restore_health_verifier->verify( $snapshot );

		return $result;
	}

	/**
	 * Map health verification status to a log severity.
	 *
	 * @param string $status Health status.
	 * @return string
	 */
	protected function map_health_status_to_severity( $status ) {
		$status = sanitize_key( (string) $status );

		if ( 'unhealthy' === $status ) {
			return 'error';
		}

		if ( 'degraded' === $status ) {
			return 'warning';
		}

		return 'info';
	}

	/**
	 * Build a summarized health row for comparison output.
	 *
	 * @param string $label    Row label.
	 * @param array  $health   Health result.
	 * @param array  $baseline Baseline result.
	 * @return array
	 */
	protected function build_health_snapshot_row( $label, array $health, array $baseline = array() ) {
		$summary          = isset( $health['summary'] ) && is_array( $health['summary'] ) ? $health['summary'] : array();
		$baseline_summary = isset( $baseline['summary'] ) && is_array( $baseline['summary'] ) ? $baseline['summary'] : array();

		return array(
			'label'        => $label,
			'status'       => isset( $health['status'] ) ? (string) $health['status'] : '',
			'generated_at' => isset( $health['generated_at'] ) ? (string) $health['generated_at'] : '',
			'summary'      => array(
				'pass'    => isset( $summary['pass'] ) ? (int) $summary['pass'] : 0,
				'warning' => isset( $summary['warning'] ) ? (int) $summary['warning'] : 0,
				'fail'    => isset( $summary['fail'] ) ? (int) $summary['fail'] : 0,
			),
			'delta'        => empty( $baseline_summary ) ? '' : $this->build_health_delta_summary( $summary, $baseline_summary ),
			'note'         => isset( $health['note'] ) ? (string) $health['note'] : '',
		);
	}

	/**
	 * Build a compact delta summary against the baseline health summary.
	 *
	 * @param array $summary          Current summary.
	 * @param array $baseline_summary Baseline summary.
	 * @return string
	 */
	protected function build_health_delta_summary( array $summary, array $baseline_summary ) {
		$parts = array();

		foreach ( array( 'pass', 'warning', 'fail' ) as $key ) {
			$delta = ( isset( $summary[ $key ] ) ? (int) $summary[ $key ] : 0 ) - ( isset( $baseline_summary[ $key ] ) ? (int) $baseline_summary[ $key ] : 0 );

			if ( 0 === $delta ) {
				continue;
			}

			$parts[] = sprintf( '%s %s%d', $key, $delta > 0 ? '+' : '', $delta );
		}

		return empty( $parts ) ? __( 'No change', 'zignites-sentinel' ) : implode( ', ', $parts );
	}

	/**
	 * Build a structured snapshot audit report.
	 *
	 * @param array $snapshot Snapshot detail.
	 * @return array
	 */
	protected function build_snapshot_audit_report( array $snapshot ) {
		$payload = array(
			'generated_at' => current_time( 'mysql', true ),
			'plugin_version' => ZNTS_VERSION,
			'snapshot' => $snapshot,
			'comparison' => $this->get_snapshot_comparison( $snapshot ),
			'artifacts' => $this->get_snapshot_artifacts( $snapshot ),
			'artifact_diff' => $this->get_artifact_diff( $snapshot ),
			'health' => array(
				'baseline' => $this->get_snapshot_health_baseline( $snapshot ),
				'comparison' => $this->get_snapshot_health_comparison( $snapshot ),
			),
			'readiness' => array(
				'restore_check' => $this->get_last_restore_check( $snapshot ),
				'restore_dry_run' => $this->get_last_restore_dry_run( $snapshot ),
				'restore_stage' => $this->get_last_restore_stage( $snapshot ),
				'restore_plan' => $this->get_last_restore_plan( $snapshot ),
				'restore_execution' => $this->get_last_restore_execution( $snapshot ),
				'restore_rollback' => $this->get_last_restore_rollback( $snapshot ),
			),
			'checkpoints' => array(
				'stage' => $this->get_restore_stage_checkpoint( $snapshot ),
				'plan' => $this->get_restore_plan_checkpoint( $snapshot ),
				'execution' => $this->get_restore_execution_checkpoint( $snapshot ),
			),
			'operator_checklist' => $this->get_restore_operator_checklist( $snapshot ),
			'activity' => $this->get_snapshot_activity( $snapshot ),
		);

		$payload_hash = hash( 'sha256', (string) wp_json_encode( $payload ) );

		return array(
			'report'    => $payload,
			'integrity' => array(
				'algorithm'      => 'sha256',
				'payload_hash'   => $payload_hash,
				'site_signature' => function_exists( 'wp_salt' ) ? hash_hmac( 'sha256', $payload_hash, wp_salt( 'auth' ) ) : '',
			),
		);
	}

	/**
	 * Return a matching staged validation result only when fresh enough.
	 *
	 * @param array $snapshot  Snapshot detail.
	 * @param array $artifacts Artifact rows.
	 * @return array
	 */
	protected function get_fresh_restore_stage_result( array $snapshot, array $artifacts ) {
		$checkpoint = $this->get_restore_stage_checkpoint( $snapshot );

		if ( empty( $checkpoint ) || ! $this->is_checkpoint_fresh( $checkpoint ) ) {
			return array();
		}

		return $this->restore_checkpoint_store->get_matching_stage_result( $snapshot, $artifacts );
	}

	/**
	 * Return a matching restore plan result only when fresh enough.
	 *
	 * @param array $snapshot  Snapshot detail.
	 * @param array $artifacts Artifact rows.
	 * @return array
	 */
	protected function get_fresh_restore_plan_result( array $snapshot, array $artifacts ) {
		$checkpoint = $this->get_restore_plan_checkpoint( $snapshot );

		if ( empty( $checkpoint ) || ! $this->is_checkpoint_fresh( $checkpoint ) ) {
			return array();
		}

		return $this->restore_checkpoint_store->get_matching_plan_result( $snapshot, $artifacts );
	}

	/**
	 * Determine whether a checkpoint is still within the configured age window.
	 *
	 * @param array $checkpoint Checkpoint data.
	 * @return bool
	 */
	protected function is_checkpoint_fresh( array $checkpoint ) {
		$generated_at = isset( $checkpoint['generated_at'] ) ? (string) $checkpoint['generated_at'] : '';

		if ( '' === $generated_at ) {
			return false;
		}

		$generated_ts = strtotime( $generated_at );

		if ( false === $generated_ts ) {
			return false;
		}

		$settings      = $this->get_settings();
		$max_age_hours = isset( $settings['restore_checkpoint_max_age_hours'] ) ? max( 1, (int) $settings['restore_checkpoint_max_age_hours'] ) : 24;

		return $generated_ts >= ( time() - ( $max_age_hours * HOUR_IN_SECONDS ) );
	}

	/**
	 * Verify a pasted snapshot audit report payload.
	 *
	 * @param string $payload_text Raw JSON payload.
	 * @param int    $snapshot_id  Expected snapshot ID.
	 * @return array
	 */
	protected function verify_snapshot_audit_report_payload( $payload_text, $snapshot_id ) {
		$decoded = json_decode( (string) $payload_text, true );
		$checks  = array();

		if ( ! is_array( $decoded ) ) {
			return array(
				'generated_at' => current_time( 'mysql', true ),
				'snapshot_id'  => absint( $snapshot_id ),
				'status'       => 'blocked',
				'summary'      => array(
					'pass'    => 0,
					'warning' => 0,
					'fail'    => 1,
				),
				'checks'       => array(
					array(
						'label'   => __( 'Report payload', 'zignites-sentinel' ),
						'status'  => 'fail',
						'message' => __( 'The provided audit report is not valid JSON.', 'zignites-sentinel' ),
					),
				),
				'note'         => __( 'Audit report verification failed.', 'zignites-sentinel' ),
			);
		}

		$report            = isset( $decoded['report'] ) && is_array( $decoded['report'] ) ? $decoded['report'] : array();
		$integrity         = isset( $decoded['integrity'] ) && is_array( $decoded['integrity'] ) ? $decoded['integrity'] : array();
		$algorithm         = isset( $integrity['algorithm'] ) ? sanitize_text_field( (string) $integrity['algorithm'] ) : '';
		$stored_hash       = isset( $integrity['payload_hash'] ) ? sanitize_text_field( (string) $integrity['payload_hash'] ) : '';
		$stored_signature  = isset( $integrity['site_signature'] ) ? sanitize_text_field( (string) $integrity['site_signature'] ) : '';
		$expected_id       = absint( $snapshot_id );
		$report_snapshot_id = isset( $report['snapshot']['id'] ) ? absint( $report['snapshot']['id'] ) : 0;
		$computed_hash     = ( 'sha256' === $algorithm && ! empty( $report ) ) ? hash( 'sha256', (string) wp_json_encode( $report ) ) : '';
		$computed_signature = ( 'sha256' === $algorithm && '' !== $computed_hash && function_exists( 'wp_salt' ) ) ? hash_hmac( 'sha256', $computed_hash, wp_salt( 'auth' ) ) : '';

		$checks[] = array(
			'label'   => __( 'Report envelope', 'zignites-sentinel' ),
			'status'  => ( ! empty( $report ) && ! empty( $integrity ) ) ? 'pass' : 'fail',
			'message' => ( ! empty( $report ) && ! empty( $integrity ) )
				? __( 'The audit report contains report and integrity sections.', 'zignites-sentinel' )
				: __( 'The audit report is missing report or integrity sections.', 'zignites-sentinel' ),
		);
		$checks[] = array(
			'label'   => __( 'Integrity algorithm', 'zignites-sentinel' ),
			'status'  => 'sha256' === $algorithm ? 'pass' : 'fail',
			'message' => 'sha256' === $algorithm
				? __( 'The audit report uses the supported SHA-256 integrity algorithm.', 'zignites-sentinel' )
				: __( 'The audit report uses an unsupported integrity algorithm.', 'zignites-sentinel' ),
		);
		$checks[] = array(
			'label'   => __( 'Snapshot match', 'zignites-sentinel' ),
			'status'  => $expected_id > 0 && $report_snapshot_id === $expected_id ? 'pass' : 'fail',
			'message' => $expected_id > 0 && $report_snapshot_id === $expected_id
				? __( 'The audit report matches the selected snapshot.', 'zignites-sentinel' )
				: __( 'The audit report does not match the selected snapshot.', 'zignites-sentinel' ),
		);
		$checks[] = array(
			'label'   => __( 'Payload hash', 'zignites-sentinel' ),
			'status'  => '' !== $computed_hash && hash_equals( $stored_hash, $computed_hash ) ? 'pass' : 'fail',
			'message' => '' !== $computed_hash && hash_equals( $stored_hash, $computed_hash )
				? __( 'The audit report payload hash matches.', 'zignites-sentinel' )
				: __( 'The audit report payload hash does not match.', 'zignites-sentinel' ),
		);
		$checks[] = array(
			'label'   => __( 'Site signature', 'zignites-sentinel' ),
			'status'  => '' !== $computed_signature && hash_equals( $stored_signature, $computed_signature ) ? 'pass' : 'fail',
			'message' => '' !== $computed_signature && hash_equals( $stored_signature, $computed_signature )
				? __( 'The audit report site signature matches this site.', 'zignites-sentinel' )
				: __( 'The audit report site signature does not match this site.', 'zignites-sentinel' ),
		);

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

		$status = ! empty( $summary['fail'] ) ? 'blocked' : ( ! empty( $summary['warning'] ) ? 'caution' : 'ready' );

		return array(
			'generated_at' => current_time( 'mysql', true ),
			'snapshot_id'  => $expected_id,
			'status'       => $status,
			'summary'      => $summary,
			'checks'       => $checks,
			'note'         => 'ready' === $status ? __( 'Audit report verification passed for this site and snapshot.', 'zignites-sentinel' ) : __( 'Audit report verification found integrity or snapshot mismatches.', 'zignites-sentinel' ),
		);
	}

	/**
	 * Map an assessment status to a log severity.
	 *
	 * @param string $status Assessment status.
	 * @return string
	 */
	protected function map_assessment_status_to_severity( $status ) {
		if ( 'blocked' === $status ) {
			return 'error';
		}

		if ( 'caution' === $status || 'partial' === $status || 'unhealthy' === $status || 'degraded' === $status ) {
			return 'warning';
		}

		return 'info';
	}
}
