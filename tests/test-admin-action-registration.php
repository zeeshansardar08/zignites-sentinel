<?php
/**
 * Focused tests for admin action registration boundaries.
 */

require_once __DIR__ . '/bootstrap.php';

use Zignites\Sentinel\Admin\Admin;

function znts_test_admin_default_actions_match_simplified_ui() {
	$actions = Admin::get_default_admin_post_actions();

	$expected = array(
		'znts_export_event_logs',
		'znts_create_snapshot',
		'znts_check_restore_readiness',
		'znts_run_restore_dry_run',
		'znts_run_restore_stage',
		'znts_build_restore_plan',
		'znts_save_safe_update_window',
		'znts_run_update_window_health',
		'znts_confirm_update_window_success',
		'znts_download_update_window_report',
		'znts_save_alert_integrations',
		'znts_send_test_alert',
		'znts_execute_restore',
		'znts_resume_restore',
		'znts_rollback_restore',
		'znts_resume_restore_rollback',
	);

	znts_assert_same( $expected, array_keys( $actions ), 'Default admin-post actions should match the simplified UI forms and update-screen checkpoint action.' );
}

function znts_test_admin_legacy_actions_are_feature_flagged() {
	$default_actions = Admin::get_default_admin_post_actions();
	$legacy_actions  = Admin::get_legacy_admin_post_actions();

	foreach ( array_keys( $legacy_actions ) as $action ) {
		znts_assert_true( ! isset( $default_actions[ $action ] ), 'Legacy admin-post actions should not be registered by default.' );
	}

	znts_assert_true( isset( $legacy_actions['znts_run_preflight'] ), 'Preflight should be treated as a hidden legacy admin action until the simplified UI exposes it again.' );
	znts_assert_true( isset( $legacy_actions['znts_save_settings'] ), 'Settings persistence should be treated as a hidden legacy admin action until settings UI returns.' );
	znts_assert_true( isset( $legacy_actions['znts_capture_snapshot_health_baseline'] ), 'Snapshot health baseline capture should be explicitly feature-flagged while hidden from the simplified UI.' );
	znts_assert_true( isset( $legacy_actions['znts_download_snapshot_audit_report'] ), 'Snapshot audit downloads should be explicitly feature-flagged while hidden from the simplified UI.' );
}
