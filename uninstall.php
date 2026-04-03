<?php
/**
 * Uninstall logic for Zignites Sentinel.
 *
 * @package ZignitesSentinel
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

if ( ! defined( 'ZNTS_PLUGIN_FILE' ) ) {
	define( 'ZNTS_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'ZNTS_PLUGIN_DIR' ) ) {
	define( 'ZNTS_PLUGIN_DIR', plugin_dir_path( ZNTS_PLUGIN_FILE ) );
}

require_once __DIR__ . '/includes/class-autoloader.php';
\Zignites\Sentinel\Autoloader::register();

$settings = get_option( 'znts_settings', array() );
$settings = is_array( $settings ) ? $settings : array();

if ( empty( $settings['delete_data_on_uninstall'] ) ) {
	delete_option( 'znts_settings' );
	delete_option( 'znts_db_version' );
	delete_option( 'znts_last_preflight' );
	delete_option( 'znts_last_update_plan' );
	delete_option( 'znts_last_restore_check' );
	delete_option( 'znts_last_restore_dry_run' );
	delete_option( 'znts_last_restore_stage' );
	delete_option( 'znts_restore_stage_checkpoint' );
	delete_option( 'znts_last_restore_plan' );
	delete_option( 'znts_restore_plan_checkpoint' );
	delete_option( 'znts_last_restore_execution' );
	delete_option( 'znts_restore_execution_checkpoint' );
	delete_option( 'znts_last_restore_rollback' );
	delete_option( 'znts_restore_rollback_checkpoint' );
	delete_option( 'znts_last_snapshot_health_baseline' );
	delete_option( 'znts_last_audit_report_verification' );
	delete_option( 'znts_restore_checkpoint_expiry_log' );
	return;
}

delete_option( 'znts_settings' );
delete_option( 'znts_db_version' );
delete_option( 'znts_last_preflight' );
delete_option( 'znts_last_update_plan' );
delete_option( 'znts_last_restore_check' );
delete_option( 'znts_last_restore_dry_run' );
delete_option( 'znts_last_restore_stage' );
delete_option( 'znts_restore_stage_checkpoint' );
delete_option( 'znts_last_restore_plan' );
delete_option( 'znts_restore_plan_checkpoint' );
delete_option( 'znts_last_restore_execution' );
delete_option( 'znts_restore_execution_checkpoint' );
delete_option( 'znts_last_restore_rollback' );
delete_option( 'znts_restore_rollback_checkpoint' );
delete_option( 'znts_last_snapshot_health_baseline' );
delete_option( 'znts_last_audit_report_verification' );
delete_option( 'znts_restore_checkpoint_expiry_log' );

$tables = array(
	$wpdb->prefix . 'znts_logs',
	$wpdb->prefix . 'znts_conflicts',
	$wpdb->prefix . 'znts_snapshots',
	$wpdb->prefix . 'znts_snapshot_artifacts',
);

foreach ( $tables as $table ) {
	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Table names cannot be parametrized with $wpdb->prepare().
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
}

$export_manager = new \Zignites\Sentinel\Snapshots\SnapshotExportManager();
$export_manager->delete_export_directory();
$package_manager = new \Zignites\Sentinel\Snapshots\SnapshotPackageManager();
$package_manager->delete_package_directory();
$staging_manager = new \Zignites\Sentinel\Snapshots\RestoreStagingManager( $package_manager );
$staging_manager->delete_stage_directory_root();
$restore_planner = new \Zignites\Sentinel\Snapshots\RestoreExecutionPlanner( $staging_manager );
$restore_executor = new \Zignites\Sentinel\Snapshots\RestoreExecutor( $staging_manager, $restore_planner );
$restore_executor->delete_backup_directory_root();
