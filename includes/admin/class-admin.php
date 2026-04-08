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
	 * Shared snapshot status resolver.
	 *
	 * @var SnapshotStatusResolver
	 */
	protected $snapshot_status_resolver;

	/**
	 * Event Logs presenter.
	 *
	 * @var EventLogPresenter
	 */
	protected $event_log_presenter;

	/**
	 * Shared status presenter.
	 *
	 * @var StatusPresenter
	 */
	protected $status_presenter;

	/**
	 * Health comparison presenter.
	 *
	 * @var HealthComparisonPresenter
	 */
	protected $health_comparison_presenter;

	/**
	 * Restore checkpoint presenter.
	 *
	 * @var RestoreCheckpointPresenter
	 */
	protected $restore_checkpoint_presenter;

	/**
	 * Snapshot summary presenter.
	 *
	 * @var SnapshotSummaryPresenter
	 */
	protected $snapshot_summary_presenter;

	/**
	 * Dashboard summary presenter.
	 *
	 * @var DashboardSummaryPresenter
	 */
	protected $dashboard_summary_presenter;

	/**
	 * Restore impact summary presenter.
	 *
	 * @var RestoreImpactSummaryPresenter
	 */
	protected $restore_impact_summary_presenter;

	/**
	 * Settings portability helper.
	 *
	 * @var SettingsPortability
	 */
	protected $settings_portability;

	/**
	 * Audit report verifier.
	 *
	 * @var AuditReportVerifier
	 */
	protected $audit_report_verifier;

	/**
	 * Snapshot audit report presenter.
	 *
	 * @var SnapshotAuditReportPresenter
	 */
	protected $snapshot_audit_report_presenter;

	/**
	 * Restore operator checklist evaluator.
	 *
	 * @var RestoreOperatorChecklistEvaluator
	 */
	protected $restore_operator_checklist_evaluator;

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
		$this->settings_portability     = new SettingsPortability();
		$this->audit_report_verifier    = new AuditReportVerifier();
		$this->restore_operator_checklist_evaluator = new RestoreOperatorChecklistEvaluator();
		$this->status_presenter             = new StatusPresenter();
		$this->health_comparison_presenter  = new HealthComparisonPresenter( $this->status_presenter );
		$this->restore_checkpoint_presenter = new RestoreCheckpointPresenter( $this->status_presenter );
		$this->event_log_presenter          = new EventLogPresenter();
		$this->snapshot_summary_presenter   = new SnapshotSummaryPresenter();
		$this->snapshot_audit_report_presenter = new SnapshotAuditReportPresenter( $this->audit_report_verifier );
		$this->dashboard_summary_presenter  = new DashboardSummaryPresenter();
		$this->restore_impact_summary_presenter = new RestoreImpactSummaryPresenter();
		$this->snapshot_status_resolver = new SnapshotStatusResolver(
			$logs,
			$restore_checkpoint_store,
			$restore_journal_recorder,
			$artifacts
		);
	}

	/**
	 * Register admin hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_dashboard_setup', array( $this, 'register_dashboard_widget' ) );
		add_action( 'admin_post_znts_run_preflight', array( $this, 'handle_run_preflight' ) );
		add_action( 'admin_post_znts_export_event_logs', array( $this, 'handle_export_event_logs' ) );
		add_action( 'admin_post_znts_create_snapshot', array( $this, 'handle_create_snapshot' ) );
		add_action( 'admin_post_znts_download_settings_export', array( $this, 'handle_download_settings_export' ) );
		add_action( 'admin_post_znts_import_settings', array( $this, 'handle_import_settings' ) );
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
		add_action( 'admin_post_znts_download_snapshot_summary', array( $this, 'handle_download_snapshot_summary' ) );
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
		if ( ! in_array( $hook_suffix, $this->hook_suffixes, true ) && 'index.php' !== $hook_suffix ) {
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

		$summary = $this->get_dashboard_summary_payload( 5 );

		$view_data = array(
			'plugin_version'        => ZNTS_VERSION,
			'db_version'            => get_option( ZNTS_OPTION_DB_VERSION, ZNTS_DB_VERSION ),
			'logs_table'            => Installer::get_logs_table_name(),
			'conflicts_table'       => Installer::get_conflicts_table_name(),
			'wordpress'             => get_bloginfo( 'version' ),
			'php'                   => PHP_VERSION,
			'site_url'              => home_url(),
			'recent_logs'           => $this->logs->get_recent( 8 ),
			'recent_conflicts'      => $this->conflicts->get_recent_open( 6 ),
			'recent_snapshots'      => $summary['recent_snapshots'],
			'health_score'          => $summary['health_score'],
			'restore_health_strip'  => $summary['restore_health_strip'],
			'snapshot_status_index' => $summary['snapshot_status_index'],
			'site_status_card'      => $summary['site_status_card'],
		);

		require ZNTS_PLUGIN_DIR . 'includes/admin/views/dashboard.php';
	}

	/**
	 * Register a compact Sentinel widget on the core WordPress dashboard.
	 *
	 * @return void
	 */
	public function register_dashboard_widget() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		wp_add_dashboard_widget(
			'znts-site-status-widget',
			__( 'Sentinel Site Status', 'zignites-sentinel' ),
			array( $this, 'render_dashboard_widget' ),
			null,
			null,
			'side',
			'high'
		);
	}

	/**
	 * Render the compact WordPress dashboard widget.
	 *
	 * @return void
	 */
	public function render_dashboard_widget() {
		$summary = $this->get_dashboard_summary_payload( 1 );
		$view_data = array(
			'site_status_card'     => $summary['site_status_card'],
			'restore_health_strip' => $summary['restore_health_strip'],
			'latest_snapshot'      => ! empty( $summary['recent_snapshots'][0] ) ? $summary['recent_snapshots'][0] : array(),
			'snapshot_status'      => ! empty( $summary['recent_snapshots'][0]['id'] ) && isset( $summary['snapshot_status_index'][ (int) $summary['recent_snapshots'][0]['id'] ] )
				? $summary['snapshot_status_index'][ (int) $summary['recent_snapshots'][0]['id'] ]
				: array(),
		);

		require ZNTS_PLUGIN_DIR . 'includes/admin/views/dashboard-widget.php';
	}

	/**
	 * Build shared dashboard summary payloads for full and compact dashboard views.
	 *
	 * @param int $snapshot_limit Number of recent snapshots to include.
	 * @return array
	 */
	protected function get_dashboard_summary_payload( $snapshot_limit = 5 ) {
		$recent_snapshots      = $this->snapshots->get_recent( max( 1, absint( $snapshot_limit ) ) );
		$health_score          = $this->health_score->calculate();
		$snapshot_status_index = $this->snapshot_status_resolver->build_snapshot_status_index( $recent_snapshots );
		$site_status_card      = $this->snapshot_status_resolver->build_site_status_card( $health_score, $recent_snapshots, $snapshot_status_index );
		$activity_url          = ! empty( $site_status_card['latest_snapshot']['id'] ) ? $this->get_snapshot_activity_url( (int) $site_status_card['latest_snapshot']['id'] ) : '';

		return $this->dashboard_summary_presenter->build_summary_payload(
			$recent_snapshots,
			is_array( $health_score ) ? $health_score : array(),
			is_array( $snapshot_status_index ) ? $snapshot_status_index : array(),
			is_array( $site_status_card ) ? $site_status_card : array(),
			$this->get_restore_dashboard_health_strip(),
			self::UPDATE_PAGE_SLUG,
			$activity_url
		);
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

		$snapshot_detail        = $this->get_snapshot_detail();
		$snapshot_search        = $this->get_snapshot_search_term();
		$snapshot_status_filter = $this->get_snapshot_status_filter();
		$snapshot_list_state    = $this->get_snapshot_list_state( $snapshot_search, $snapshot_status_filter );

		$view_data = array(
			'last_preflight'         => get_option( ZNTS_OPTION_LAST_PREFLIGHT, array() ),
			'last_update_plan'       => get_option( ZNTS_OPTION_UPDATE_PLAN, array() ),
			'last_restore_check'     => $this->get_last_restore_check( $snapshot_detail ),
			'settings'               => $this->get_settings(),
			'update_candidates'      => $this->update_planner->get_candidates(),
			'recent_snapshots'       => isset( $snapshot_list_state['items'] ) ? $snapshot_list_state['items'] : array(),
			'snapshot_search'        => $snapshot_search,
			'snapshot_status_filter' => $snapshot_status_filter,
			'snapshot_status_filter_options' => $this->snapshot_status_resolver->get_snapshot_filter_options(),
			'snapshot_status_index'  => isset( $snapshot_list_state['status_index'] ) ? $snapshot_list_state['status_index'] : array(),
			'snapshot_pagination'    => isset( $snapshot_list_state['pagination'] ) ? $snapshot_list_state['pagination'] : array(),
			'snapshot_detail'        => $snapshot_detail,
			'snapshot_comparison'    => $this->get_snapshot_comparison( $snapshot_detail ),
			'snapshot_artifacts'     => $this->get_snapshot_artifacts( $snapshot_detail ),
			'artifact_diff'          => $this->get_artifact_diff( $snapshot_detail ),
			'last_restore_dry_run'   => $this->get_last_restore_dry_run( $snapshot_detail ),
			'last_restore_stage'     => $this->get_last_restore_stage( $snapshot_detail ),
			'last_restore_plan'      => $this->get_last_restore_plan( $snapshot_detail ),
			'last_restore_execution' => $this->get_last_restore_execution( $snapshot_detail ),
			'last_restore_rollback'  => $this->get_last_restore_rollback( $snapshot_detail ),
			'stage_checkpoint'       => $this->get_restore_stage_checkpoint( $snapshot_detail ),
			'plan_checkpoint'        => $this->get_restore_plan_checkpoint( $snapshot_detail ),
			'execution_checkpoint'   => $this->get_restore_execution_checkpoint( $snapshot_detail ),
			'execution_checkpoint_summary' => $this->get_restore_execution_checkpoint_summary( $snapshot_detail ),
			'rollback_checkpoint'    => $this->get_restore_rollback_checkpoint( $snapshot_detail ),
			'rollback_checkpoint_summary' => $this->get_restore_rollback_checkpoint_summary( $snapshot_detail ),
			'restore_resume_context' => $this->get_restore_resume_context( $snapshot_detail ),
			'restore_rollback_resume_context' => $this->get_restore_rollback_resume_context( $snapshot_detail ),
			'restore_run_cards'      => $this->get_restore_run_cards( $snapshot_detail ),
			'snapshot_health_baseline' => $this->get_snapshot_health_baseline( $snapshot_detail ),
			'snapshot_health_comparison' => $this->get_snapshot_health_comparison( $snapshot_detail ),
			'snapshot_summary'       => $this->get_snapshot_summary( $snapshot_detail ),
			'operator_checklist'     => $this->get_restore_operator_checklist( $snapshot_detail ),
			'restore_impact_summary' => $this->get_restore_impact_summary( $snapshot_detail ),
			'audit_report_verification' => $this->get_audit_report_verification( $snapshot_detail ),
			'snapshot_activity'      => $this->get_snapshot_activity( $snapshot_detail ),
			'snapshot_activity_url'  => $this->get_snapshot_activity_url( is_array( $snapshot_detail ) && ! empty( $snapshot_detail['id'] ) ? (int) $snapshot_detail['id'] : 0 ),
			'notice'                 => $this->get_notice_message(),
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

		$log_detail         = $this->get_log_detail();
		$recent_logs        = $this->logs->get_paginated( $log_filters );
		$operational_events = $this->get_operational_events( $log_filters );
		$run_summaries      = $this->get_run_summaries( $log_filters );
		$run_journal        = $this->get_run_journal( $log_filters );
		$event_log_ui       = $this->event_log_presenter->build_view_payload(
			$recent_logs,
			$log_filters,
			$run_summaries,
			$operational_events,
			$run_journal,
			array(
				'current_page' => $current_page,
				'per_page'     => $per_page,
				'total_logs'   => $total_logs,
				'total_pages'  => $total_pages,
			)
		);

		$view_data = array(
			'recent_logs' => $recent_logs,
			'log_detail'  => $log_detail,
			'log_filters' => $log_filters,
			'operational_events' => $operational_events,
			'run_summaries' => $event_log_ui['run_summaries'],
			'run_journal' => $event_log_ui['run_journal'],
			'event_log_ui' => $event_log_ui,
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
	 * Export filtered event logs as CSV.
	 *
	 * @return void
	 */
	public function handle_export_event_logs() {
		$this->assert_admin_action_permissions( 'znts_export_event_logs_action' );

		$filters = $this->get_log_filters_from_request( 'post' );
		$rows    = $this->logs->get_filtered_for_export( $filters, 5000 );
		$handle  = fopen( 'php://output', 'w' );

		if ( false === $handle ) {
			wp_die( esc_html__( 'The event log export could not be created.', 'zignites-sentinel' ) );
		}

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $this->build_event_log_export_filename( $filters ) . '"' );

		fputcsv(
			$handle,
			array(
				'id',
				'created_at',
				'severity',
				'source',
				'event_type',
				'message',
				'snapshot_id',
				'run_id',
				'journal_scope',
				'journal_phase',
				'journal_status',
				'context_json',
			)
		);

		foreach ( $rows as $row ) {
			fputcsv( $handle, $this->build_event_log_export_row( $row ) );
		}

		fclose( $handle );
		exit;
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

		$settings = $this->sanitize_settings_input( wp_unslash( $_POST ) );

		update_option( ZNTS_OPTION_SETTINGS, $settings, false );
		$this->redirect_with_notice( 'settings-saved' );
	}

	/**
	 * Handle download of a non-destructive settings export.
	 *
	 * @return void
	 */
	public function handle_download_settings_export() {
		$this->assert_admin_action_permissions( 'znts_download_settings_export_action' );

		$payload  = $this->settings_portability->build_export_payload( $this->get_settings() );
		$filename = sprintf( 'znts-settings-%s.json', gmdate( 'Ymd-His' ) );

		$this->logger->log(
			'settings_export_downloaded',
			'info',
			'settings',
			__( 'Sentinel settings export downloaded.', 'zignites-sentinel' ),
			array(
				'filename' => $filename,
			)
		);

		nocache_headers();
		header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) );
		header( 'Content-Disposition: attachment; filename=' . $filename );

		echo wp_json_encode( $payload, JSON_PRETTY_PRINT );
		exit;
	}

	/**
	 * Handle import of a non-destructive settings export.
	 *
	 * @return void
	 */
	public function handle_import_settings() {
		$this->assert_admin_action_permissions( 'znts_import_settings_action' );

		$payload = isset( $_POST['settings_import_payload'] ) ? wp_unslash( $_POST['settings_import_payload'] ) : '';
		$result  = $this->settings_portability->import_payload( $payload );

		if ( empty( $result['success'] ) ) {
			$this->logger->log(
				'settings_import_failed',
				'warning',
				'settings',
				__( 'Sentinel settings import failed.', 'zignites-sentinel' ),
				array(
					'error' => isset( $result['error'] ) ? (string) $result['error'] : '',
				)
			);
			$this->redirect_with_notice( 'settings-import-invalid' );
		}

		update_option( ZNTS_OPTION_SETTINGS, $result['settings'], false );

		$this->logger->log(
			'settings_import_completed',
			'info',
			'settings',
			__( 'Sentinel settings import completed.', 'zignites-sentinel' ),
			array(
				'settings' => $result['settings'],
			)
		);

		$this->redirect_with_notice( 'settings-imported' );
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
		) . '#znts-restore-dry-run';

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
		) . '#znts-restore-stage';

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
		) . '#znts-restore-plan';

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
	 * Handle download of a human-readable snapshot summary.
	 *
	 * @return void
	 */
	public function handle_download_snapshot_summary() {
		$this->assert_admin_action_permissions( 'znts_download_snapshot_summary_action' );

		$snapshot_id = isset( $_POST['snapshot_id'] ) ? absint( wp_unslash( $_POST['snapshot_id'] ) ) : 0;
		$snapshot    = $this->get_snapshot_detail_by_id( $snapshot_id );

		if ( ! is_array( $snapshot ) ) {
			$this->redirect_with_notice( 'snapshot-summary-missing' );
		}

		$summary  = $this->get_snapshot_summary( $snapshot );
		$markdown = $this->build_snapshot_summary_markdown( $summary );
		$filename = sprintf( 'znts-snapshot-%d-summary-%s.md', $snapshot_id, gmdate( 'Ymd-His' ) );

		$this->logger->log(
			'snapshot_summary_downloaded',
			'info',
			'snapshots',
			__( 'Snapshot summary downloaded.', 'zignites-sentinel' ),
			array(
				'snapshot_id' => $snapshot_id,
				'filename'    => $filename,
			)
		);

		nocache_headers();
		header( 'Content-Type: text/markdown; charset=' . get_option( 'blog_charset' ) );
		header( 'Content-Disposition: attachment; filename=' . $filename );

		echo $markdown; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Markdown export output.
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
			'settings-imported' => array(
				'type'    => 'success',
				'message' => __( 'Sentinel settings imported.', 'zignites-sentinel' ),
			),
			'settings-import-invalid' => array(
				'type'    => 'error',
				'message' => __( 'The provided Sentinel settings export could not be imported.', 'zignites-sentinel' ),
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
			'snapshot-summary-missing' => array(
				'type'    => 'error',
				'message' => __( 'The selected snapshot could not be found for summary export.', 'zignites-sentinel' ),
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

		return $this->settings_portability->normalize_settings(
			$settings,
			$this->settings_portability->get_default_settings()
		);
	}

	/**
	 * Sanitize posted settings form values.
	 *
	 * @param array $raw_input Raw form input.
	 * @return array
	 */
	protected function sanitize_settings_input( array $raw_input ) {
		return $this->settings_portability->normalize_settings(
			array(
				'logging_enabled'                  => isset( $raw_input['logging_enabled'] ) ? 1 : 0,
				'delete_data_on_uninstall'         => isset( $raw_input['delete_data_on_uninstall'] ) ? 1 : 0,
				'auto_snapshot_on_plan'            => isset( $raw_input['auto_snapshot_on_plan'] ) ? 1 : 0,
				'snapshot_retention_days'          => isset( $raw_input['snapshot_retention_days'] ) ? $raw_input['snapshot_retention_days'] : null,
				'restore_checkpoint_max_age_hours' => isset( $raw_input['restore_checkpoint_max_age_hours'] ) ? $raw_input['restore_checkpoint_max_age_hours'] : null,
			),
			$this->settings_portability->get_default_settings()
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
	 * Get the current snapshot status filter from the request.
	 *
	 * @return string
	 */
	protected function get_snapshot_status_filter() {
		return isset( $_GET['snapshot_status_filter'] ) ? sanitize_key( wp_unslash( $_GET['snapshot_status_filter'] ) ) : '';
	}

	/**
	 * Get the current snapshot list page from the request.
	 *
	 * @return int
	 */
	protected function get_snapshot_list_page() {
		return isset( $_GET['snapshot_paged'] ) ? max( 1, absint( wp_unslash( $_GET['snapshot_paged'] ) ) ) : 1;
	}

	/**
	 * Build paginated snapshot list state for Update Readiness.
	 *
	 * @param string $search        Label search term.
	 * @param string $status_filter Status filter key.
	 * @return array
	 */
	protected function get_snapshot_list_state( $search = '', $status_filter = '' ) {
		$search         = sanitize_text_field( (string) $search );
		$status_filter  = sanitize_key( (string) $status_filter );
		$per_page       = 12;
		$current_page   = $this->get_snapshot_list_page();
		$base_total     = $this->snapshots->count_filtered( $search );
		$status_index   = array();
		$items          = array();
		$total_matches  = 0;

		if ( '' === $status_filter ) {
			$offset       = ( $current_page - 1 ) * $per_page;
			$items        = $this->snapshots->get_filtered(
				array(
					'search' => $search,
					'limit'  => $per_page,
					'offset' => $offset,
				)
			);
			$status_index = $this->snapshot_status_resolver->build_snapshot_status_index( $items );
			$total_matches = $base_total;
		} else {
			$batch_size     = 50;
			$match_start    = ( $current_page - 1 ) * $per_page;
			$match_end      = $match_start + $per_page;

			for ( $offset = 0; $offset < $base_total; $offset += $batch_size ) {
				$batch = $this->snapshots->get_filtered(
					array(
						'search' => $search,
						'limit'  => $batch_size,
						'offset' => $offset,
					)
				);

				if ( empty( $batch ) ) {
					break;
				}

				$batch_status_index = $this->snapshot_status_resolver->build_snapshot_status_index( $batch );
				$matched_batch      = $this->snapshot_status_resolver->filter_snapshots( $batch, $batch_status_index, '', $status_filter, $batch_size );

				foreach ( $matched_batch as $matched_snapshot ) {
					if ( $total_matches >= $match_start && $total_matches < $match_end ) {
						$items[] = $matched_snapshot;
					}

					++$total_matches;
				}
			}

			$status_index = $this->snapshot_status_resolver->build_snapshot_status_index( $items );
		}

		$total_pages = max( 1, (int) ceil( $total_matches / $per_page ) );

		return array(
			'items'        => $items,
			'status_index' => $status_index,
			'pagination'   => array(
				'current_page' => min( $current_page, $total_pages ),
				'per_page'     => $per_page,
				'total_items'  => $total_matches,
				'total_pages'  => $total_pages,
			),
		);
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
	 * Build a compact human-readable snapshot summary for operator handoff.
	 *
	 * @param array|null $snapshot Snapshot detail.
	 * @return array
	 */
	protected function get_snapshot_summary( $snapshot ) {
		if ( ! is_array( $snapshot ) || empty( $snapshot['id'] ) ) {
			return array();
		}

		$artifacts          = $this->get_snapshot_artifacts( $snapshot );
		$artifacts          = is_array( $artifacts ) ? $artifacts : array();
		$activity           = $this->get_snapshot_activity( $snapshot );
		$activity           = is_array( $activity ) ? array_slice( $activity, 0, 5 ) : array();
		$operator_checklist = $this->get_restore_operator_checklist( $snapshot );
		$operator_checklist = is_array( $operator_checklist ) ? $operator_checklist : array();
		$restore_check      = $this->get_last_restore_check( $snapshot );
		$restore_check      = is_array( $restore_check ) ? $restore_check : array();
		$restore_stage      = $this->get_last_restore_stage( $snapshot );
		$restore_stage      = is_array( $restore_stage ) ? $restore_stage : array();
		$restore_plan       = $this->get_last_restore_plan( $snapshot );
		$restore_plan       = is_array( $restore_plan ) ? $restore_plan : array();
		$last_execution     = $this->get_last_restore_execution( $snapshot );
		$last_execution     = is_array( $last_execution ) ? $last_execution : array();
		$last_rollback      = $this->get_last_restore_rollback( $snapshot );
		$last_rollback      = is_array( $last_rollback ) ? $last_rollback : array();
		$baseline           = $this->get_snapshot_health_baseline( $snapshot );
		$baseline           = is_array( $baseline ) ? $baseline : array();
		$stage_checkpoint   = $this->get_restore_stage_checkpoint( $snapshot );
		$stage_checkpoint   = is_array( $stage_checkpoint ) ? $stage_checkpoint : array();
		$plan_checkpoint    = $this->get_restore_plan_checkpoint( $snapshot );
		$plan_checkpoint    = is_array( $plan_checkpoint ) ? $plan_checkpoint : array();
		$status_index       = $this->snapshot_status_resolver->build_snapshot_status_index( array( $snapshot ) );
		$snapshot_status    = isset( $status_index[ (int) $snapshot['id'] ] ) ? $status_index[ (int) $snapshot['id'] ] : array();
		$artifact_counts    = $this->summarize_snapshot_artifacts( $artifacts );
		$stage_timing       = ! empty( $stage_checkpoint ) ? $this->get_checkpoint_timing_summary( $stage_checkpoint ) : array();
		$plan_timing        = ! empty( $plan_checkpoint ) ? $this->get_checkpoint_timing_summary( $plan_checkpoint ) : array();

		return $this->snapshot_summary_presenter->build_summary( $snapshot, $snapshot_status, $artifact_counts, $activity, $operator_checklist, $baseline, $restore_check, $stage_timing, $plan_timing, $last_execution, $last_rollback, $restore_stage, $restore_plan );
	}

	/**
	 * Summarize artifact counts by type for a snapshot.
	 *
	 * @param array $artifacts Snapshot artifacts.
	 * @return array
	 */
	protected function summarize_snapshot_artifacts( array $artifacts ) {
		return $this->snapshot_summary_presenter->summarize_artifacts( $artifacts );
	}

	/**
	 * Build human-readable risk bullets for a snapshot summary.
	 *
	 * @param array $snapshot_status Snapshot status payload.
	 * @param array $baseline        Baseline payload.
	 * @param array $restore_check   Restore readiness payload.
	 * @param array $last_execution  Last restore execution payload.
	 * @param array $last_rollback   Last rollback payload.
	 * @return array
	 */
	protected function build_snapshot_summary_risks( array $snapshot_status, array $baseline, array $restore_check, array $last_execution, array $last_rollback ) {
		return $this->snapshot_summary_presenter->build_risks( $snapshot_status, $baseline, $restore_check, $last_execution, $last_rollback );
	}

	/**
	 * Build recommended next steps for a snapshot summary.
	 *
	 * @param array $snapshot_status Snapshot status payload.
	 * @param array $baseline        Baseline payload.
	 * @param array $restore_check   Restore readiness payload.
	 * @param array $restore_stage   Restore stage payload.
	 * @param array $restore_plan    Restore plan payload.
	 * @param array $last_execution  Last restore execution payload.
	 * @param array $last_rollback   Last rollback payload.
	 * @return array
	 */
	protected function build_snapshot_summary_next_steps( array $snapshot_status, array $baseline, array $restore_check, array $restore_stage, array $restore_plan, array $last_execution, array $last_rollback ) {
		return $this->snapshot_summary_presenter->build_next_steps( $snapshot_status, $baseline, $restore_check, $restore_stage, $restore_plan, $last_execution, $last_rollback );
	}

	/**
	 * Build Markdown output for a snapshot summary.
	 *
	 * @param array $summary Snapshot summary payload.
	 * @return string
	 */
	protected function build_snapshot_summary_markdown( array $summary ) {
		return $this->snapshot_summary_presenter->build_markdown( $summary );
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
	 * Return the persisted rollback checkpoint when it matches the selected snapshot.
	 *
	 * @param array|null $snapshot Snapshot detail.
	 * @return array|null
	 */
	protected function get_restore_rollback_checkpoint( $snapshot ) {
		if ( ! is_array( $snapshot ) || empty( $snapshot['id'] ) ) {
			return null;
		}

		$last_rollback = $this->get_last_restore_rollback( $snapshot );
		$run_id        = is_array( $last_rollback ) && ! empty( $last_rollback['run_id'] ) ? (string) $last_rollback['run_id'] : '';

		if ( '' === $run_id ) {
			$run_id = $this->restore_journal_recorder->get_latest_run_id(
				RestoreRollbackManager::JOURNAL_SOURCE,
				(int) $snapshot['id']
			);
		}

		$checkpoint = $this->restore_checkpoint_store->get_rollback_checkpoint( (int) $snapshot['id'], $run_id );

		return ! empty( $checkpoint ) ? $checkpoint : null;
	}

	/**
	 * Build a compact summary for the persisted execution checkpoint.
	 *
	 * @param array|null $snapshot Snapshot detail.
	 * @return array
	 */
	protected function get_restore_execution_checkpoint_summary( $snapshot ) {
		$checkpoint = $this->get_restore_execution_checkpoint( $snapshot );

		if ( ! is_array( $checkpoint ) ) {
			return array();
		}

		$checkpoint_state = isset( $checkpoint['checkpoint'] ) && is_array( $checkpoint['checkpoint'] ) ? $checkpoint['checkpoint'] : array();
		$item_summary     = $this->summarize_execution_checkpoint_items(
			isset( $checkpoint_state['items'] ) && is_array( $checkpoint_state['items'] ) ? $checkpoint_state['items'] : array()
		);

		return array_merge(
			array(
				'run_id'           => isset( $checkpoint['run_id'] ) ? (string) $checkpoint['run_id'] : '',
				'generated_at'     => isset( $checkpoint['generated_at'] ) ? (string) $checkpoint['generated_at'] : '',
				'stage_ready'      => ! empty( $checkpoint_state['stage_ready'] ),
				'stage_path'       => isset( $checkpoint_state['stage_path'] ) ? (string) $checkpoint_state['stage_path'] : '',
				'health_completed' => ! empty( $checkpoint_state['health_completed'] ),
				'health_status'    => isset( $checkpoint_state['health_verification']['status'] ) ? (string) $checkpoint_state['health_verification']['status'] : '',
			),
			$item_summary
		);
	}

	/**
	 * Build a compact summary for the persisted rollback checkpoint.
	 *
	 * @param array|null $snapshot Snapshot detail.
	 * @return array
	 */
	protected function get_restore_rollback_checkpoint_summary( $snapshot ) {
		$checkpoint = $this->get_restore_rollback_checkpoint( $snapshot );

		if ( ! is_array( $checkpoint ) ) {
			return array();
		}

		$checkpoint_state = isset( $checkpoint['checkpoint'] ) && is_array( $checkpoint['checkpoint'] ) ? $checkpoint['checkpoint'] : array();
		$item_summary     = $this->summarize_rollback_checkpoint_items(
			isset( $checkpoint_state['items'] ) && is_array( $checkpoint_state['items'] ) ? $checkpoint_state['items'] : array()
		);

		return array_merge(
			array(
				'run_id'       => isset( $checkpoint['run_id'] ) ? (string) $checkpoint['run_id'] : '',
				'generated_at' => isset( $checkpoint['generated_at'] ) ? (string) $checkpoint['generated_at'] : '',
				'backup_root'  => isset( $checkpoint_state['backup_root'] ) ? (string) $checkpoint_state['backup_root'] : '',
			),
			$item_summary
		);
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

		$status_payload         = $this->status_presenter->present_health( isset( $check['status'] ) ? $check['status'] : '' );
		$check['status_pill']  = isset( $status_payload['pill'] ) ? (string) $status_payload['pill'] : 'info';
		$check['status_label'] = isset( $status_payload['label'] ) ? (string) $status_payload['label'] : '';

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
		return $this->health_comparison_presenter->build_comparison( $baseline, $execution, $rollback );
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

		return $this->restore_operator_checklist_evaluator->evaluate(
			array(
				'baseline_present'        => is_array( $baseline ),
				'stage_result_ready'      => ! empty( $stage_result ),
				'plan_result_ready'       => ! empty( $plan_result ),
				'stage_checkpoint_exists' => ! empty( $stage_checkpoint ),
				'plan_checkpoint_exists'  => ! empty( $plan_checkpoint ),
				'max_age_hours'           => $max_age_hours,
			)
		);
	}

	/**
	 * Build a final pre-execution impact summary for live restore.
	 *
	 * @param array|null $snapshot Snapshot detail.
	 * @return array
	 */
	protected function get_restore_impact_summary( $snapshot ) {
		if ( ! is_array( $snapshot ) || empty( $snapshot['id'] ) ) {
			return array();
		}

		$artifacts        = $this->get_snapshot_artifacts( $snapshot );
		$artifacts        = is_array( $artifacts ) ? $artifacts : array();
		$plan             = $this->get_last_restore_plan( $snapshot );
		$plan             = is_array( $plan ) ? $plan : array();
		$baseline         = $this->get_snapshot_health_baseline( $snapshot );
		$baseline         = is_array( $baseline ) ? $baseline : array();
		$checklist        = $this->get_restore_operator_checklist( $snapshot, $artifacts );
		$checklist        = is_array( $checklist ) ? $checklist : array();
		$resume_context   = $this->get_restore_resume_context( $snapshot );
		$resume_context   = is_array( $resume_context ) ? $resume_context : array();
		$execution        = $this->get_last_restore_execution( $snapshot );
		$execution        = is_array( $execution ) ? $execution : array();
		$stage_checkpoint = $this->get_restore_stage_checkpoint( $snapshot );
		$stage_checkpoint = is_array( $stage_checkpoint ) ? $stage_checkpoint : array();
		$plan_checkpoint  = $this->get_restore_plan_checkpoint( $snapshot );
		$plan_checkpoint  = is_array( $plan_checkpoint ) ? $plan_checkpoint : array();
		return $this->restore_impact_summary_presenter->build_summary(
			(int) $snapshot['id'],
			$plan,
			$baseline,
			$checklist,
			$resume_context,
			$this->build_restore_backup_summary( $snapshot, $execution, $resume_context ),
			$this->build_restore_gate_summary( __( 'No staged validation checkpoint is available.', 'zignites-sentinel' ), $stage_checkpoint ),
			$this->build_restore_gate_summary( __( 'No restore plan checkpoint is available.', 'zignites-sentinel' ), $plan_checkpoint )
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

		return $this->dashboard_summary_presenter->build_restore_summary(
			$snapshot,
			is_array( $checklist ) ? $checklist : array(),
			$baseline,
			$stage,
			$plan,
			is_array( $execution ) ? $execution : array(),
			is_array( $rollback ) ? $rollback : array(),
			$stage_timing,
			$plan_timing,
			self::UPDATE_PAGE_SLUG,
			$this->get_snapshot_activity_url( (int) $snapshot['id'] )
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

		return $this->dashboard_summary_presenter->build_health_strip( $snapshot, is_array( $rows ) ? $rows : array(), self::UPDATE_PAGE_SLUG );
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

		$rollback_checkpoint = $this->restore_checkpoint_store->get_rollback_checkpoint(
			(int) $snapshot['id'],
			isset( $context['run_id'] ) ? (string) $context['run_id'] : ''
		);
		$checkpoint_state    = isset( $rollback_checkpoint['checkpoint'] ) && is_array( $rollback_checkpoint['checkpoint'] ) ? $rollback_checkpoint['checkpoint'] : array();
		$checkpoint_items    = isset( $checkpoint_state['items'] ) && is_array( $checkpoint_state['items'] ) ? $checkpoint_state['items'] : array();

		$context['checkpoint_generated_at'] = isset( $rollback_checkpoint['generated_at'] ) ? (string) $rollback_checkpoint['generated_at'] : '';
		$context['checkpoint_item_count']   = count( $checkpoint_items );
		$context['checkpoint_completed_count'] = count(
			array_filter(
				$checkpoint_items,
				static function ( $item ) {
					return ! empty( $item['completed'] );
				}
			)
		);

		return $context;
	}

	/**
	 * Summarize execution checkpoint items for operator display.
	 *
	 * @param array $items Execution checkpoint items.
	 * @return array
	 */
	protected function summarize_execution_checkpoint_items( array $items ) {
		$summary = array(
			'item_count'        => count( $items ),
			'backup_count'      => 0,
			'write_count'       => 0,
			'failed_count'      => 0,
			'phase_counts'      => array(),
		);

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			if ( ! empty( $item['backup_completed'] ) ) {
				++$summary['backup_count'];
			}

			if ( ! empty( $item['write_completed'] ) ) {
				++$summary['write_count'];
			}

			if ( ! empty( $item['status'] ) && 'fail' === sanitize_key( (string) $item['status'] ) ) {
				++$summary['failed_count'];
			}

			$phase = ! empty( $item['phase'] ) ? sanitize_key( (string) $item['phase'] ) : 'unknown';

			if ( ! isset( $summary['phase_counts'][ $phase ] ) ) {
				$summary['phase_counts'][ $phase ] = 0;
			}

			++$summary['phase_counts'][ $phase ];
		}

		arsort( $summary['phase_counts'] );

		return $summary;
	}

	/**
	 * Summarize rollback checkpoint items for operator display.
	 *
	 * @param array $items Rollback checkpoint items.
	 * @return array
	 */
	protected function summarize_rollback_checkpoint_items( array $items ) {
		$summary = array(
			'item_count'        => count( $items ),
			'completed_count'   => 0,
			'failed_count'      => 0,
			'phase_counts'      => array(),
		);

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			if ( ! empty( $item['completed'] ) ) {
				++$summary['completed_count'];
			}

			if ( ! empty( $item['status'] ) && 'fail' === sanitize_key( (string) $item['status'] ) ) {
				++$summary['failed_count'];
			}

			$phase = ! empty( $item['phase'] ) ? sanitize_key( (string) $item['phase'] ) : 'unknown';

			if ( ! isset( $summary['phase_counts'][ $phase ] ) ) {
				$summary['phase_counts'][ $phase ] = 0;
			}

			++$summary['phase_counts'][ $phase ];
		}

		arsort( $summary['phase_counts'] );

		return $summary;
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
				$restore_resume,
				$execution_checkpoint,
				$this->get_run_journal_url( RestoreExecutor::JOURNAL_SOURCE, isset( $last_execution['run_id'] ) ? (string) $last_execution['run_id'] : '', $snapshot_id )
			);
		}

		if ( is_array( $last_rollback ) ) {
			$cards[] = $this->build_run_card(
				__( 'Latest Rollback Run', 'zignites-sentinel' ),
				$last_rollback,
				$rollback_resume,
				array(),
				$this->get_run_journal_url( RestoreRollbackManager::JOURNAL_SOURCE, isset( $last_rollback['run_id'] ) ? (string) $last_rollback['run_id'] : '', $snapshot_id )
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
		return $this->restore_checkpoint_presenter->build_checkpoint_card(
			$title,
			$checkpoint,
			$summary_line,
			$this->get_checkpoint_timing_summary( $checkpoint )
		);
	}

	/**
	 * Build checkpoint timing metadata from the configured freshness window.
	 *
	 * @param array $checkpoint Checkpoint data.
	 * @return array
	 */
	protected function get_checkpoint_timing_summary( array $checkpoint ) {
		$settings     = $this->get_settings();
		$max_age_hours = isset( $settings['restore_checkpoint_max_age_hours'] ) ? max( 1, (int) $settings['restore_checkpoint_max_age_hours'] ) : 24;

		return $this->restore_checkpoint_presenter->build_timing_summary( $checkpoint, $max_age_hours, time() );
	}

	/**
	 * Build a readable gate summary line.
	 *
	 * @param string     $missing_message Fallback message when no checkpoint exists.
	 * @param array|null $checkpoint      Checkpoint data.
	 * @return string
	 */
	protected function build_restore_gate_summary( $missing_message, $checkpoint ) {
		return $this->restore_checkpoint_presenter->build_gate_summary(
			$missing_message,
			$checkpoint,
			( is_array( $checkpoint ) && ! empty( $checkpoint ) ) ? $this->get_checkpoint_timing_summary( $checkpoint ) : array()
		);
	}

	/**
	 * Build a readable backup storage summary before execution.
	 *
	 * @param array $snapshot       Snapshot detail.
	 * @param array $execution      Last execution result.
	 * @param array $resume_context Resume context.
	 * @return string
	 */
	protected function build_restore_backup_summary( array $snapshot, array $execution, array $resume_context ) {
		return $this->restore_checkpoint_presenter->build_backup_summary( $snapshot, $execution, $resume_context, wp_upload_dir() );
	}

	/**
	 * Format a short human-readable duration.
	 *
	 * @param int $seconds Duration in seconds.
	 * @return string
	 */
	protected function build_run_card( $title, array $result, array $resume_context, $execution_checkpoint = null, $journal_url = '' ) {
		return $this->restore_checkpoint_presenter->build_run_card( $title, $result, $resume_context, $execution_checkpoint, $journal_url );
	}

	/**
	 * Map readiness-style statuses to a badge class.
	 *
	 * @param string $status Status value.
	 * @return string
	 */
	protected function map_readiness_badge( $status ) {
		$presented = $this->status_presenter->present_readiness( $status );

		return isset( $presented['pill'] ) ? (string) $presented['pill'] : 'info';
	}

	/**
	 * Map run statuses to a badge class.
	 *
	 * @param string $status Status value.
	 * @return string
	 */
	protected function map_run_badge( $status ) {
		$presented = $this->status_presenter->present_run( $status );

		return isset( $presented['pill'] ) ? (string) $presented['pill'] : 'info';
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
		return $this->get_log_filters_from_request( 'get' );
	}

	/**
	 * Collect filters for the event log screen from GET or POST.
	 *
	 * @param string $source Request source: get or post.
	 * @return array
	 */
	protected function get_log_filters_from_request( $source = 'get' ) {
		$request = 'post' === strtolower( (string) $source ) ? $_POST : $_GET;
		$severity    = isset( $request['severity'] ) ? sanitize_key( wp_unslash( $request['severity'] ) ) : '';
		$source_name = isset( $request['source'] ) ? sanitize_text_field( wp_unslash( $request['source'] ) ) : '';
		$run_id      = isset( $request['run_id'] ) ? sanitize_text_field( wp_unslash( $request['run_id'] ) ) : '';
		$snapshot_id = isset( $request['snapshot_id'] ) ? absint( wp_unslash( $request['snapshot_id'] ) ) : 0;
		$search      = isset( $request['log_search'] ) ? sanitize_text_field( wp_unslash( $request['log_search'] ) ) : '';
		$paged       = isset( $request['paged'] ) ? absint( wp_unslash( $request['paged'] ) ) : 1;

		if ( ! in_array( $severity, array( '', 'info', 'warning', 'error', 'critical' ), true ) ) {
			$severity = '';
		}

		return array(
			'severity'    => $severity,
			'source'      => $source_name,
			'run_id'      => $run_id,
			'snapshot_id' => $snapshot_id,
			'search'      => $search,
			'paged'       => max( 1, $paged ),
		);
	}

	/**
	 * Build a CSV export filename for the current event log filters.
	 *
	 * @param array $filters Current log filters.
	 * @return string
	 */
	protected function build_event_log_export_filename( array $filters ) {
		$parts = array( 'znts-event-logs' );

		if ( ! empty( $filters['source'] ) ) {
			$parts[] = sanitize_title( (string) $filters['source'] );
		}

		if ( ! empty( $filters['run_id'] ) ) {
			$parts[] = sanitize_title( (string) $filters['run_id'] );
		}

		if ( ! empty( $filters['snapshot_id'] ) ) {
			$parts[] = 'snapshot-' . absint( $filters['snapshot_id'] );
		}

		$parts[] = gmdate( 'Ymd-His' );

		return implode( '-', array_filter( $parts ) ) . '.csv';
	}

	/**
	 * Build a CSV row for an event log export.
	 *
	 * @param array $row Log row.
	 * @return array
	 */
	protected function build_event_log_export_row( array $row ) {
		$context = $this->decode_json_field( isset( $row['context'] ) ? $row['context'] : '' );

		return $this->event_log_presenter->build_export_row( $row, $context );
	}

	/**
	 * Build a snapshot activity row for the update readiness screen.
	 *
	 * @param array $row         Log row.
	 * @param int   $snapshot_id Snapshot ID.
	 * @return array
	 */
	protected function build_snapshot_activity_entry( array $row, $snapshot_id ) {
		$context = $this->decode_json_field( isset( $row['context'] ) ? $row['context'] : '' );
		$run_id  = isset( $context['run_id'] ) ? sanitize_text_field( (string) $context['run_id'] ) : '';
		$source  = isset( $row['source'] ) ? sanitize_text_field( (string) $row['source'] ) : '';
		$is_run  = in_array( $source, array( RestoreExecutor::JOURNAL_SOURCE, RestoreRollbackManager::JOURNAL_SOURCE ), true );
		$journal = ( $is_run && '' !== $run_id ) ? $this->get_run_journal_url( $source, $run_id, $snapshot_id ) : '';

		return $this->event_log_presenter->build_snapshot_activity_entry( $row, $context, $snapshot_id, self::LOGS_PAGE_SLUG, $journal );
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
		return $this->health_comparison_presenter->build_row( $label, $health, $baseline );
	}

	/**
	 * Build a compact delta summary against the baseline health summary.
	 *
	 * @param array $summary          Current summary.
	 * @param array $baseline_summary Baseline summary.
	 * @return string
	 */
	protected function build_health_delta_summary( array $summary, array $baseline_summary ) {
		return $this->health_comparison_presenter->build_delta_summary( $summary, $baseline_summary );
	}

	/**
	 * Build a structured snapshot audit report.
	 *
	 * @param array $snapshot Snapshot detail.
	 * @return array
	 */
	protected function build_snapshot_audit_report( array $snapshot ) {
		return $this->snapshot_audit_report_presenter->build_report(
			$snapshot,
			(array) $this->get_snapshot_comparison( $snapshot ),
			(array) $this->get_snapshot_artifacts( $snapshot ),
			(array) $this->get_artifact_diff( $snapshot ),
			(array) $this->get_snapshot_health_baseline( $snapshot ),
			(array) $this->get_snapshot_health_comparison( $snapshot ),
			(array) $this->get_last_restore_check( $snapshot ),
			(array) $this->get_last_restore_dry_run( $snapshot ),
			(array) $this->get_last_restore_stage( $snapshot ),
			(array) $this->get_last_restore_plan( $snapshot ),
			(array) $this->get_last_restore_execution( $snapshot ),
			(array) $this->get_last_restore_rollback( $snapshot ),
			(array) $this->get_restore_stage_checkpoint( $snapshot ),
			(array) $this->get_restore_plan_checkpoint( $snapshot ),
			(array) $this->get_restore_execution_checkpoint( $snapshot ),
			(array) $this->get_restore_operator_checklist( $snapshot ),
			(array) $this->get_snapshot_activity( $snapshot )
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
		return $this->audit_report_verifier->verify_payload( $payload_text, $snapshot_id );
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
