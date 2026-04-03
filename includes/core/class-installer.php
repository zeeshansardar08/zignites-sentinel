<?php
/**
 * Installation and schema management.
 *
 * @package ZignitesSentinel
 */

namespace Zignites\Sentinel\Core;

defined( 'ABSPATH' ) || exit;

class Installer {

	/**
	 * Create schema and seed options.
	 *
	 * @return void
	 */
	public static function install() {
		self::create_tables();
		self::seed_options();
		self::schedule_events();
		update_option( ZNTS_OPTION_DB_VERSION, ZNTS_DB_VERSION );
	}

	/**
	 * Create required custom tables.
	 *
	 * @return void
	 */
	protected static function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$logs_table_name      = self::get_logs_table_name();
		$conflicts_table_name = self::get_conflicts_table_name();
		$snapshots_table_name = self::get_snapshots_table_name();
		$artifacts_table_name = self::get_snapshot_artifacts_table_name();
		$charset_collate      = $wpdb->get_charset_collate();

		$logs_sql = "CREATE TABLE {$logs_table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			event_type varchar(50) NOT NULL DEFAULT '',
			severity varchar(20) NOT NULL DEFAULT 'info',
			source varchar(100) NOT NULL DEFAULT '',
			message text NOT NULL,
			context longtext NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY event_type (event_type),
			KEY severity (severity),
			KEY created_at (created_at)
		) {$charset_collate};";

		$conflicts_sql = "CREATE TABLE {$conflicts_table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			conflict_key varchar(191) NOT NULL DEFAULT '',
			signal_type varchar(50) NOT NULL DEFAULT '',
			severity varchar(20) NOT NULL DEFAULT 'warning',
			status varchar(20) NOT NULL DEFAULT 'open',
			source_a varchar(191) NOT NULL DEFAULT '',
			source_b varchar(191) NOT NULL DEFAULT '',
			summary text NOT NULL,
			details longtext NULL,
			occurrence_count bigint(20) unsigned NOT NULL DEFAULT 1,
			first_seen_at datetime NOT NULL,
			last_seen_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY conflict_key (conflict_key),
			KEY signal_type (signal_type),
			KEY severity (severity),
			KEY status (status),
			KEY last_seen_at (last_seen_at)
		) {$charset_collate};";

		$snapshots_sql = "CREATE TABLE {$snapshots_table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			snapshot_type varchar(50) NOT NULL DEFAULT 'manual',
			status varchar(20) NOT NULL DEFAULT 'ready',
			label varchar(191) NOT NULL DEFAULT '',
			description text NULL,
			core_version varchar(20) NOT NULL DEFAULT '',
			php_version varchar(20) NOT NULL DEFAULT '',
			theme_stylesheet varchar(191) NOT NULL DEFAULT '',
			active_plugins longtext NULL,
			metadata longtext NULL,
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY snapshot_type (snapshot_type),
			KEY status (status),
			KEY created_at (created_at)
		) {$charset_collate};";

		$artifacts_sql = "CREATE TABLE {$artifacts_table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			snapshot_id bigint(20) unsigned NOT NULL DEFAULT 0,
			artifact_type varchar(20) NOT NULL DEFAULT '',
			artifact_key varchar(191) NOT NULL DEFAULT '',
			label varchar(191) NOT NULL DEFAULT '',
			version varchar(50) NOT NULL DEFAULT '',
			source_path varchar(255) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			metadata longtext NULL,
			PRIMARY KEY  (id),
			KEY snapshot_id (snapshot_id),
			KEY artifact_type (artifact_type),
			KEY artifact_key (artifact_key)
		) {$charset_collate};";

		dbDelta( $logs_sql );
		dbDelta( $conflicts_sql );
		dbDelta( $snapshots_sql );
		dbDelta( $artifacts_sql );
	}

	/**
	 * Seed default options.
	 *
	 * @return void
	 */
	protected static function seed_options() {
		$defaults = array(
			'delete_data_on_uninstall' => 1,
			'logging_enabled'          => 1,
			'snapshot_retention_days'  => 30,
			'auto_snapshot_on_plan'    => 1,
		);

		if ( false === get_option( ZNTS_OPTION_SETTINGS ) ) {
			add_option( ZNTS_OPTION_SETTINGS, $defaults, '', false );
			return;
		}

		$current = get_option( ZNTS_OPTION_SETTINGS, array() );
		$current = is_array( $current ) ? $current : array();

		update_option( ZNTS_OPTION_SETTINGS, wp_parse_args( $current, $defaults ), false );
	}

	/**
	 * Retrieve the logs table name.
	 *
	 * @return string
	 */
	public static function get_logs_table_name() {
		global $wpdb;

		return $wpdb->prefix . 'znts_logs';
	}

	/**
	 * Retrieve the conflicts table name.
	 *
	 * @return string
	 */
	public static function get_conflicts_table_name() {
		global $wpdb;

		return $wpdb->prefix . 'znts_conflicts';
	}

	/**
	 * Retrieve the snapshots table name.
	 *
	 * @return string
	 */
	public static function get_snapshots_table_name() {
		global $wpdb;

		return $wpdb->prefix . 'znts_snapshots';
	}

	/**
	 * Retrieve the snapshot artifacts table name.
	 *
	 * @return string
	 */
	public static function get_snapshot_artifacts_table_name() {
		global $wpdb;

		return $wpdb->prefix . 'znts_snapshot_artifacts';
	}

	/**
	 * Schedule recurring diagnostics collection.
	 *
	 * @return void
	 */
	protected static function schedule_events() {
		if ( ! wp_next_scheduled( 'znts_collect_diagnostics' ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, 'hourly', 'znts_collect_diagnostics' );
		}

		if ( ! wp_next_scheduled( 'znts_cleanup_snapshots' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'znts_cleanup_snapshots' );
		}
	}
}
