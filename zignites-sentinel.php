<?php
/**
 * Plugin Name: Zignites Sentinel
 * Plugin URI:  https://zignites.com/
 * Description: Create a rollback checkpoint of your active plugins and theme before updates, then restore it if an update breaks the code layer.
 * Version:     1.32.0
 * Author:      Zignites
 * Text Domain: zignites-sentinel
 * Domain Path: /languages
 *
 * @package ZignitesSentinel
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'ZNTS_VERSION' ) ) {
	define( 'ZNTS_VERSION', '1.32.0' );
}

if ( ! defined( 'ZNTS_DB_VERSION' ) ) {
	define( 'ZNTS_DB_VERSION', '1.4.0' );
}

if ( ! defined( 'ZNTS_PLUGIN_FILE' ) ) {
	define( 'ZNTS_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'ZNTS_PLUGIN_DIR' ) ) {
	define( 'ZNTS_PLUGIN_DIR', plugin_dir_path( ZNTS_PLUGIN_FILE ) );
}

if ( ! defined( 'ZNTS_PLUGIN_URL' ) ) {
	define( 'ZNTS_PLUGIN_URL', plugin_dir_url( ZNTS_PLUGIN_FILE ) );
}

if ( ! defined( 'ZNTS_OPTION_SETTINGS' ) ) {
	define( 'ZNTS_OPTION_SETTINGS', 'znts_settings' );
}

if ( ! defined( 'ZNTS_OPTION_DB_VERSION' ) ) {
	define( 'ZNTS_OPTION_DB_VERSION', 'znts_db_version' );
}

if ( ! defined( 'ZNTS_OPTION_LAST_PREFLIGHT' ) ) {
	define( 'ZNTS_OPTION_LAST_PREFLIGHT', 'znts_last_preflight' );
}

if ( ! defined( 'ZNTS_OPTION_UPDATE_PLAN' ) ) {
	define( 'ZNTS_OPTION_UPDATE_PLAN', 'znts_last_update_plan' );
}

if ( ! defined( 'ZNTS_OPTION_LAST_RESTORE_CHECK' ) ) {
	define( 'ZNTS_OPTION_LAST_RESTORE_CHECK', 'znts_last_restore_check' );
}

if ( ! defined( 'ZNTS_OPTION_LAST_RESTORE_DRY_RUN' ) ) {
	define( 'ZNTS_OPTION_LAST_RESTORE_DRY_RUN', 'znts_last_restore_dry_run' );
}

if ( ! defined( 'ZNTS_OPTION_LAST_RESTORE_STAGE' ) ) {
	define( 'ZNTS_OPTION_LAST_RESTORE_STAGE', 'znts_last_restore_stage' );
}

if ( ! defined( 'ZNTS_OPTION_RESTORE_STAGE_CHECKPOINT' ) ) {
	define( 'ZNTS_OPTION_RESTORE_STAGE_CHECKPOINT', 'znts_restore_stage_checkpoint' );
}

if ( ! defined( 'ZNTS_OPTION_LAST_RESTORE_PLAN' ) ) {
	define( 'ZNTS_OPTION_LAST_RESTORE_PLAN', 'znts_last_restore_plan' );
}

if ( ! defined( 'ZNTS_OPTION_RESTORE_PLAN_CHECKPOINT' ) ) {
	define( 'ZNTS_OPTION_RESTORE_PLAN_CHECKPOINT', 'znts_restore_plan_checkpoint' );
}

if ( ! defined( 'ZNTS_OPTION_LAST_RESTORE_EXECUTION' ) ) {
	define( 'ZNTS_OPTION_LAST_RESTORE_EXECUTION', 'znts_last_restore_execution' );
}

if ( ! defined( 'ZNTS_OPTION_RESTORE_EXECUTION_CHECKPOINT' ) ) {
	define( 'ZNTS_OPTION_RESTORE_EXECUTION_CHECKPOINT', 'znts_restore_execution_checkpoint' );
}

if ( ! defined( 'ZNTS_OPTION_LAST_RESTORE_ROLLBACK' ) ) {
	define( 'ZNTS_OPTION_LAST_RESTORE_ROLLBACK', 'znts_last_restore_rollback' );
}

if ( ! defined( 'ZNTS_OPTION_RESTORE_ROLLBACK_CHECKPOINT' ) ) {
	define( 'ZNTS_OPTION_RESTORE_ROLLBACK_CHECKPOINT', 'znts_restore_rollback_checkpoint' );
}

if ( ! defined( 'ZNTS_OPTION_LAST_SNAPSHOT_HEALTH_BASELINE' ) ) {
	define( 'ZNTS_OPTION_LAST_SNAPSHOT_HEALTH_BASELINE', 'znts_last_snapshot_health_baseline' );
}

if ( ! defined( 'ZNTS_OPTION_LAST_AUDIT_REPORT_VERIFICATION' ) ) {
	define( 'ZNTS_OPTION_LAST_AUDIT_REPORT_VERIFICATION', 'znts_last_audit_report_verification' );
}

if ( ! defined( 'ZNTS_OPTION_RESTORE_CHECKPOINT_EXPIRY_LOG' ) ) {
	define( 'ZNTS_OPTION_RESTORE_CHECKPOINT_EXPIRY_LOG', 'znts_restore_checkpoint_expiry_log' );
}

require_once ZNTS_PLUGIN_DIR . 'includes/class-autoloader.php';

\Zignites\Sentinel\Autoloader::register();

register_activation_hook( ZNTS_PLUGIN_FILE, array( '\Zignites\Sentinel\Core\Activator', 'activate' ) );
register_deactivation_hook( ZNTS_PLUGIN_FILE, array( '\Zignites\Sentinel\Core\Deactivator', 'deactivate' ) );

/**
 * Boot the plugin.
 *
 * @return \Zignites\Sentinel\Plugin
 */
function znts_run_plugin() {
	$plugin = new \Zignites\Sentinel\Plugin();
	$plugin->run();

	return $plugin;
}

znts_run_plugin();


