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
			'log_retention_days'               => 120,
			'snapshot_retention_days'          => 45,
			'package_retention_days'           => 21,
			'restore_backup_retention_days'    => 14,
			'failed_stage_retention_days'      => 3,
			'restore_checkpoint_max_age_hours' => 12,
			'unexpected_key'                   => 'ignored',
		)
	);

	znts_assert_same( 'znts_settings', $payload['export_type'], 'Settings export should declare the expected export type.' );
	znts_assert_true( isset( $payload['settings']['logging_enabled'] ), 'Exported settings should include supported keys.' );
	znts_assert_same( 120, $payload['settings']['log_retention_days'], 'Exported settings should include log retention.' );
	znts_assert_same( 21, $payload['settings']['package_retention_days'], 'Exported settings should include package retention.' );
	znts_assert_same( 14, $payload['settings']['restore_backup_retention_days'], 'Exported settings should include restore backup retention.' );
	znts_assert_same( 3, $payload['settings']['failed_stage_retention_days'], 'Exported settings should include failed stage retention.' );
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
					'log_retention_days'               => -10,
					'snapshot_retention_days'          => 0,
					'package_retention_days'           => -20,
					'restore_backup_retention_days'    => 0,
					'failed_stage_retention_days'      => -3,
					'restore_checkpoint_max_age_hours' => -5,
					'unknown_key'                      => 'ignored',
				),
			)
		)
	);

	znts_assert_true( ! empty( $result['success'] ), 'A valid settings export should import successfully.' );
	znts_assert_same( 1, $result['settings']['log_retention_days'], 'Log retention days should be clamped to a minimum of 1.' );
	znts_assert_same( 1, $result['settings']['snapshot_retention_days'], 'Retention days should be clamped to a minimum of 1.' );
	znts_assert_same( 1, $result['settings']['package_retention_days'], 'Package retention days should be clamped to a minimum of 1.' );
	znts_assert_same( 1, $result['settings']['restore_backup_retention_days'], 'Restore backup retention days should be clamped to a minimum of 1.' );
	znts_assert_same( 1, $result['settings']['failed_stage_retention_days'], 'Failed stage retention days should be clamped to a minimum of 1.' );
	znts_assert_same( 1, $result['settings']['restore_checkpoint_max_age_hours'], 'Checkpoint max age should be clamped to a minimum of 1.' );
	znts_assert_true( ! isset( $result['settings']['unknown_key'] ), 'Unknown imported keys should be ignored.' );
}

function znts_test_settings_import_rejects_invalid_payloads() {
	$portability = new SettingsPortability();
	$result      = $portability->import_payload( '{"invalid":true}' );

	znts_assert_true( empty( $result['success'] ), 'Invalid settings payloads should be rejected.' );
	znts_assert_true( ! empty( $result['error'] ), 'Invalid settings payloads should include an error message.' );
}
