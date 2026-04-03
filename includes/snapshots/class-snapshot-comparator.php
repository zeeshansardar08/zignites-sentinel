<?php
/**
 * Snapshot comparison against the current site state.
 *
 * @package ZignitesSentinel
 */

namespace Zignites\Sentinel\Snapshots;

defined( 'ABSPATH' ) || exit;

class SnapshotComparator {

	/**
	 * Build a comparison between a stored snapshot and the current site.
	 *
	 * @param array $snapshot Snapshot row.
	 * @return array
	 */
	public function compare( array $snapshot ) {
		$current_state = $this->get_current_state();

		$snapshot_plugins = isset( $snapshot['active_plugins_decoded'] ) && is_array( $snapshot['active_plugins_decoded'] )
			? $snapshot['active_plugins_decoded']
			: array();

		$snapshot_plugin_map = array();

		foreach ( $snapshot_plugins as $plugin_state ) {
			if ( empty( $plugin_state['plugin'] ) ) {
				continue;
			}

			$snapshot_plugin_map[ (string) $plugin_state['plugin'] ] = $plugin_state;
		}

		$current_plugin_map = array();

		foreach ( $current_state['active_plugins'] as $plugin_state ) {
			$current_plugin_map[ (string) $plugin_state['plugin'] ] = $plugin_state;
		}

		$missing_plugins = array();
		$new_plugins     = array();
		$version_changes = array();

		foreach ( $snapshot_plugin_map as $plugin_file => $plugin_state ) {
			if ( ! isset( $current_plugin_map[ $plugin_file ] ) ) {
				$missing_plugins[] = $plugin_state;
				continue;
			}

			$current_plugin = $current_plugin_map[ $plugin_file ];

			if ( (string) $current_plugin['version'] !== (string) $plugin_state['version'] ) {
				$version_changes[] = array(
					'plugin'            => $plugin_file,
					'name'              => isset( $plugin_state['name'] ) ? (string) $plugin_state['name'] : $plugin_file,
					'snapshot_version'  => isset( $plugin_state['version'] ) ? (string) $plugin_state['version'] : '',
					'current_version'   => isset( $current_plugin['version'] ) ? (string) $current_plugin['version'] : '',
				);
			}
		}

		foreach ( $current_plugin_map as $plugin_file => $plugin_state ) {
			if ( ! isset( $snapshot_plugin_map[ $plugin_file ] ) ) {
				$new_plugins[] = $plugin_state;
			}
		}

		$snapshot_theme = isset( $snapshot['theme_stylesheet'] ) ? (string) $snapshot['theme_stylesheet'] : '';
		$current_theme  = (string) $current_state['theme_stylesheet'];

		return array(
			'snapshot_core_version' => isset( $snapshot['core_version'] ) ? (string) $snapshot['core_version'] : '',
			'current_core_version'  => (string) $current_state['core_version'],
			'snapshot_php_version'  => isset( $snapshot['php_version'] ) ? (string) $snapshot['php_version'] : '',
			'current_php_version'   => (string) $current_state['php_version'],
			'snapshot_theme'        => $snapshot_theme,
			'current_theme'         => $current_theme,
			'theme_changed'         => $snapshot_theme !== $current_theme,
			'missing_plugins'       => $missing_plugins,
			'new_plugins'           => $new_plugins,
			'version_changes'       => $version_changes,
		);
	}

	/**
	 * Collect current site state.
	 *
	 * @return array
	 */
	protected function get_current_state() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$active_plugins = (array) get_option( 'active_plugins', array() );
		$all_plugins    = get_plugins();
		$plugin_states  = array();

		foreach ( $active_plugins as $plugin_file ) {
			$plugin_data = isset( $all_plugins[ $plugin_file ] ) ? $all_plugins[ $plugin_file ] : array();

			$plugin_states[] = array(
				'plugin'  => sanitize_text_field( $plugin_file ),
				'name'    => isset( $plugin_data['Name'] ) ? sanitize_text_field( $plugin_data['Name'] ) : '',
				'version' => isset( $plugin_data['Version'] ) ? sanitize_text_field( $plugin_data['Version'] ) : '',
			);
		}

		$theme = wp_get_theme();

		return array(
			'core_version'   => get_bloginfo( 'version' ),
			'php_version'    => PHP_VERSION,
			'theme_stylesheet' => $theme->get_stylesheet(),
			'active_plugins' => $plugin_states,
		);
	}
}
