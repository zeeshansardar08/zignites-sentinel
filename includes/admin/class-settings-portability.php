<?php
/**
 * Portable import/export handling for non-destructive plugin settings.
 *
 * @package ZignitesSentinel
 */

namespace Zignites\Sentinel\Admin;

defined( 'ABSPATH' ) || exit;

class SettingsPortability {

	/**
	 * Build a portable settings export payload.
	 *
	 * @param array $settings Sanitized settings.
	 * @return array
	 */
	public function build_export_payload( array $settings ) {
		return array(
			'export_type'  => 'znts_settings',
			'plugin'       => 'Zignites Sentinel',
			'version'      => defined( 'ZNTS_VERSION' ) ? ZNTS_VERSION : '',
			'generated_at' => current_time( 'mysql', true ),
			'settings'     => $this->normalize_settings( $settings, $this->get_default_settings() ),
		);
	}

	/**
	 * Parse and sanitize an imported settings payload.
	 *
	 * @param string $raw_payload Raw JSON payload.
	 * @return array
	 */
	public function import_payload( $raw_payload ) {
		$decoded = json_decode( (string) $raw_payload, true );

		if ( ! is_array( $decoded ) ) {
			return array(
				'success' => false,
				'error'   => __( 'The provided settings payload is not valid JSON.', 'zignites-sentinel' ),
			);
		}

		if ( empty( $decoded['export_type'] ) || 'znts_settings' !== sanitize_key( (string) $decoded['export_type'] ) ) {
			return array(
				'success' => false,
				'error'   => __( 'The provided JSON is not a Sentinel settings export.', 'zignites-sentinel' ),
			);
		}

		$settings = isset( $decoded['settings'] ) && is_array( $decoded['settings'] ) ? $decoded['settings'] : array();

		if ( empty( $settings ) ) {
			return array(
				'success' => false,
				'error'   => __( 'The settings export does not include a settings payload.', 'zignites-sentinel' ),
			);
		}

		return array(
			'success'  => true,
			'settings' => $this->normalize_settings( $settings, $this->get_default_settings() ),
		);
	}

	/**
	 * Sanitize a settings array against supported keys only.
	 *
	 * @param array $settings Settings payload.
	 * @param array $defaults Default settings.
	 * @return array
	 */
	public function normalize_settings( array $settings, array $defaults ) {
		$normalized = $defaults;

		$normalized['logging_enabled']                 = ! empty( $settings['logging_enabled'] ) ? 1 : 0;
		$normalized['delete_data_on_uninstall']        = ! empty( $settings['delete_data_on_uninstall'] ) ? 1 : 0;
		$normalized['auto_snapshot_on_plan']           = ! empty( $settings['auto_snapshot_on_plan'] ) ? 1 : 0;
		$normalized['snapshot_retention_days']         = isset( $settings['snapshot_retention_days'] ) ? max( 1, (int) $settings['snapshot_retention_days'] ) : (int) $defaults['snapshot_retention_days'];
		$normalized['restore_checkpoint_max_age_hours'] = isset( $settings['restore_checkpoint_max_age_hours'] ) ? max( 1, (int) $settings['restore_checkpoint_max_age_hours'] ) : (int) $defaults['restore_checkpoint_max_age_hours'];

		return $normalized;
	}

	/**
	 * Return the supported settings defaults.
	 *
	 * @return array
	 */
	public function get_default_settings() {
		return array(
			'delete_data_on_uninstall'         => 1,
			'logging_enabled'                  => 1,
			'snapshot_retention_days'          => 30,
			'auto_snapshot_on_plan'            => 1,
			'restore_checkpoint_max_age_hours' => 24,
		);
	}
}
