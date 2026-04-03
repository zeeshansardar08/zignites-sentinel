<?php
/**
 * Update readiness preflight evaluation.
 *
 * @package ZignitesSentinel
 */

namespace Zignites\Sentinel\Updates;

defined( 'ABSPATH' ) || exit;

class PreflightChecker {

	/**
	 * Run preflight checks.
	 *
	 * @return array
	 */
	public function run() {
		$checks = array(
			$this->check_filesystem_access(),
			$this->check_wp_cron(),
			$this->check_update_queue(),
			$this->check_component_load(),
			$this->check_runtime_versions(),
			$this->check_maintenance_state(),
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

		return array(
			'generated_at' => current_time( 'mysql', true ),
			'readiness'    => $this->determine_readiness( $summary ),
			'summary'      => $summary,
			'checks'       => $checks,
			'note'         => $this->build_note( $summary ),
		);
	}

	/**
	 * Check filesystem writability relevant to updates.
	 *
	 * @return array
	 */
	protected function check_filesystem_access() {
		$content_writable = wp_is_writable( WP_CONTENT_DIR );
		$plugin_writable  = wp_is_writable( WP_PLUGIN_DIR );

		if ( ! $content_writable || ! $plugin_writable ) {
			return array(
				'key'     => 'filesystem_access',
				'label'   => __( 'Filesystem access', 'zignites-sentinel' ),
				'status'  => 'fail',
				'message' => __( 'WordPress cannot reliably write to required directories for plugin updates.', 'zignites-sentinel' ),
				'details' => array(
					'wp_content_dir' => (bool) $content_writable,
					'wp_plugin_dir'  => (bool) $plugin_writable,
				),
			);
		}

		return array(
			'key'     => 'filesystem_access',
			'label'   => __( 'Filesystem access', 'zignites-sentinel' ),
			'status'  => 'pass',
			'message' => __( 'Required WordPress content directories appear writable.', 'zignites-sentinel' ),
			'details' => array(),
		);
	}

	/**
	 * Check WP-Cron configuration.
	 *
	 * @return array
	 */
	protected function check_wp_cron() {
		if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
			return array(
				'key'     => 'wp_cron',
				'label'   => __( 'WP-Cron availability', 'zignites-sentinel' ),
				'status'  => 'warning',
				'message' => __( 'WP-Cron is disabled. Scheduled diagnostics and staged background checks may not run automatically.', 'zignites-sentinel' ),
				'details' => array(),
			);
		}

		return array(
			'key'     => 'wp_cron',
			'label'   => __( 'WP-Cron availability', 'zignites-sentinel' ),
			'status'  => 'pass',
			'message' => __( 'WP-Cron is available for scheduled diagnostics.', 'zignites-sentinel' ),
			'details' => array(),
		);
	}

	/**
	 * Check pending update volume.
	 *
	 * @return array
	 */
	protected function check_update_queue() {
		if ( ! function_exists( 'get_core_updates' ) ) {
			require_once ABSPATH . 'wp-admin/includes/update.php';
		}

		$plugin_updates = get_site_transient( 'update_plugins' );
		$theme_updates  = get_site_transient( 'update_themes' );
		$core_updates   = get_core_updates();

		$plugin_count = ( isset( $plugin_updates->response ) && is_array( $plugin_updates->response ) ) ? count( $plugin_updates->response ) : 0;
		$theme_count  = ( isset( $theme_updates->response ) && is_array( $theme_updates->response ) ) ? count( $theme_updates->response ) : 0;
		$core_count   = is_array( $core_updates ) ? count( $core_updates ) : 0;
		$total        = $plugin_count + $theme_count + $core_count;

		if ( $total > 10 ) {
			return array(
				'key'     => 'update_queue',
				'label'   => __( 'Pending updates', 'zignites-sentinel' ),
				'status'  => 'warning',
				'message' => __( 'There are many pending updates. Consider staging changes in smaller batches.', 'zignites-sentinel' ),
				'details' => array(
					'plugins' => $plugin_count,
					'themes'  => $theme_count,
					'core'    => $core_count,
				),
			);
		}

		return array(
			'key'     => 'update_queue',
			'label'   => __( 'Pending updates', 'zignites-sentinel' ),
			'status'  => 'pass',
			'message' => __( 'Pending update volume is within a manageable range.', 'zignites-sentinel' ),
			'details' => array(
				'plugins' => $plugin_count,
				'themes'  => $theme_count,
				'core'    => $core_count,
			),
		);
	}

	/**
	 * Check plugin/theme load complexity.
	 *
	 * @return array
	 */
	protected function check_component_load() {
		$active_plugins = (array) get_option( 'active_plugins', array() );
		$theme          = wp_get_theme();

		if ( count( $active_plugins ) > 30 ) {
			return array(
				'key'     => 'component_load',
				'label'   => __( 'Active component load', 'zignites-sentinel' ),
				'status'  => 'warning',
				'message' => __( 'The site has a high number of active plugins, which increases update and conflict risk.', 'zignites-sentinel' ),
				'details' => array(
					'active_plugin_count' => count( $active_plugins ),
					'theme'               => $theme->get_stylesheet(),
				),
			);
		}

		return array(
			'key'     => 'component_load',
			'label'   => __( 'Active component load', 'zignites-sentinel' ),
			'status'  => 'pass',
			'message' => __( 'Active plugin count is within the current advisory threshold.', 'zignites-sentinel' ),
			'details' => array(
				'active_plugin_count' => count( $active_plugins ),
				'theme'               => $theme->get_stylesheet(),
			),
		);
	}

	/**
	 * Check runtime versions.
	 *
	 * @return array
	 */
	protected function check_runtime_versions() {
		if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
			return array(
				'key'     => 'runtime_versions',
				'label'   => __( 'Runtime versions', 'zignites-sentinel' ),
				'status'  => 'fail',
				'message' => __( 'PHP is below the advisory baseline for safer update handling.', 'zignites-sentinel' ),
				'details' => array(
					'php_version' => PHP_VERSION,
					'wordpress'   => get_bloginfo( 'version' ),
				),
			);
		}

		return array(
			'key'     => 'runtime_versions',
			'label'   => __( 'Runtime versions', 'zignites-sentinel' ),
			'status'  => 'pass',
			'message' => __( 'WordPress and PHP versions meet the current advisory baseline for this plugin.', 'zignites-sentinel' ),
			'details' => array(
				'php_version' => PHP_VERSION,
				'wordpress'   => get_bloginfo( 'version' ),
			),
		);
	}

	/**
	 * Check for maintenance lock conditions.
	 *
	 * @return array
	 */
	protected function check_maintenance_state() {
		$maintenance_file = trailingslashit( ABSPATH ) . '.maintenance';
		$updater_lock     = get_option( 'auto_updater.lock' );

		if ( file_exists( $maintenance_file ) || ! empty( $updater_lock ) ) {
			return array(
				'key'     => 'maintenance_state',
				'label'   => __( 'Maintenance state', 'zignites-sentinel' ),
				'status'  => 'warning',
				'message' => __( 'The site appears to be in or near a maintenance/update lock state.', 'zignites-sentinel' ),
				'details' => array(
					'maintenance_file' => file_exists( $maintenance_file ),
					'updater_lock'     => ! empty( $updater_lock ),
				),
			);
		}

		return array(
			'key'     => 'maintenance_state',
			'label'   => __( 'Maintenance state', 'zignites-sentinel' ),
			'status'  => 'pass',
			'message' => __( 'No update lock or maintenance flag was detected.', 'zignites-sentinel' ),
			'details' => array(),
		);
	}

	/**
	 * Determine readiness level from check summary.
	 *
	 * @param array $summary Check summary.
	 * @return string
	 */
	protected function determine_readiness( array $summary ) {
		if ( ! empty( $summary['fail'] ) ) {
			return 'blocked';
		}

		if ( ! empty( $summary['warning'] ) ) {
			return 'caution';
		}

		return 'ready';
	}

	/**
	 * Build a short note for operators.
	 *
	 * @param array $summary Check summary.
	 * @return string
	 */
	protected function build_note( array $summary ) {
		if ( ! empty( $summary['fail'] ) ) {
			return __( 'One or more blocking conditions were detected. Resolve them before attempting update workflows.', 'zignites-sentinel' );
		}

		if ( ! empty( $summary['warning'] ) ) {
			return __( 'The site is usable for manual review, but caution flags were found. Review the warnings before proceeding.', 'zignites-sentinel' );
		}

		return __( 'No blocking conditions were found in this advisory preflight scan.', 'zignites-sentinel' );
	}
}
