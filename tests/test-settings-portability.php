<?php
/**
 * Focused tests for non-destructive settings export/import behavior.
 */

require_once __DIR__ . '/bootstrap.php';

use Zignites\Sentinel\Admin\SettingsPortability;

function znts_test_settings_export_payload_contains_supported_settings_only() {
	$portability = new SettingsPortability();
	$payload     = $portability->build_export_payload(
		array(
			'logging_enabled'                  => 1,
			'delete_data_on_uninstall'         => 0,
			'auto_snapshot_on_plan'            => 1,
			'snapshot_retention_days'          => 45,
			'restore_checkpoint_max_age_hours' => 12,
			'unexpected_key'                   => 'ignored',
		)
	);

	znts_assert_same( 'znts_settings', $payload['export_type'], 'Settings export should declare the expected export type.' );
	znts_assert_true( isset( $payload['settings']['logging_enabled'] ), 'Exported settings should include supported keys.' );
	znts_assert_true( ! isset( $payload['settings']['unexpected_key'] ), 'Exported settings should omit unknown keys.' );
}

function znts_test_settings_import_sanitizes_supported_values() {
	$portability = new SettingsPortability();
	$result      = $portability->import_payload(
		wp_json_encode(
			array(
				'export_type' => 'znts_settings',
				'settings'    => array(
					'logging_enabled'                  => 1,
					'delete_data_on_uninstall'         => 0,
					'auto_snapshot_on_plan'            => 1,
					'snapshot_retention_days'          => 0,
					'restore_checkpoint_max_age_hours' => -5,
					'unknown_key'                      => 'ignored',
				),
			)
		)
	);

	znts_assert_true( ! empty( $result['success'] ), 'A valid settings export should import successfully.' );
	znts_assert_same( 1, $result['settings']['snapshot_retention_days'], 'Retention days should be clamped to a minimum of 1.' );
	znts_assert_same( 1, $result['settings']['restore_checkpoint_max_age_hours'], 'Checkpoint max age should be clamped to a minimum of 1.' );
	znts_assert_true( ! isset( $result['settings']['unknown_key'] ), 'Unknown imported keys should be ignored.' );
}

function znts_test_settings_import_rejects_invalid_payloads() {
	$portability = new SettingsPortability();
	$result      = $portability->import_payload( '{"invalid":true}' );

	znts_assert_true( empty( $result['success'] ), 'Invalid settings payloads should be rejected.' );
	znts_assert_true( ! empty( $result['error'] ), 'Invalid settings payloads should include an error message.' );
}
