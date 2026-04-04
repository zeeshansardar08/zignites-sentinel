<?php
/**
 * Focused tests for the live admin smoke runner helper.
 */

require_once __DIR__ . '/class-admin-smoke-runner.php';

function znts_test_admin_smoke_runner_normalizes_base_url_and_builds_paths() {
	$runner = new ZNTS_Admin_Smoke_Runner();

	znts_assert_same(
		'http://example.test/wp-admin/',
		$runner->normalize_base_url( 'http://example.test/wp-admin' ),
		'Admin smoke runner should normalize the wp-admin base URL with a trailing slash.'
	);

	znts_assert_same(
		'http://example.test/wp-admin/admin.php?page=zignites-sentinel',
		$runner->build_url( 'http://example.test/wp-admin/', 'admin.php?page=zignites-sentinel' ),
		'Admin smoke runner should build request URLs relative to the normalized wp-admin base URL.'
	);
}

function znts_test_admin_smoke_runner_detects_login_fallback_and_missing_markers() {
	$runner = new ZNTS_Admin_Smoke_Runner();
	$check  = array(
		'label'   => 'Dashboard',
		'path'    => 'admin.php?page=zignites-sentinel',
		'markers' => array( 'Site Status', 'Recommended action' ),
	);

	$result = $runner->evaluate_response(
		$check,
		200,
		'<html><body><form action="wp-login.php"><input name="log" /><input name="pwd" /></form></body></html>'
	);

	znts_assert_true( ! $result['passed'], 'Admin smoke runner should fail when the authenticated page falls back to the login form.' );
	znts_assert_true( ! empty( $result['auth_fallback'] ), 'Admin smoke runner should flag wp-login fallback markers.' );
	znts_assert_same( 2, count( $result['missing_markers'] ), 'Admin smoke runner should report missing required page markers.' );
}

function znts_test_admin_smoke_runner_passes_when_status_and_markers_match() {
	$runner = new ZNTS_Admin_Smoke_Runner();
	$check  = array(
		'label'   => 'Event Logs',
		'path'    => 'admin.php?page=zignites-sentinel-event-logs',
		'markers' => array( 'Event Logs', 'Export Filtered CSV' ),
	);

	$result = $runner->evaluate_response(
		$check,
		200,
		'<html><body><h1>Event Logs</h1><button>Export Filtered CSV</button></body></html>'
	);

	znts_assert_true( $result['passed'], 'Admin smoke runner should pass when the response is HTTP 200 and all expected markers are present.' );
	znts_assert_same( 0, count( $result['missing_markers'] ), 'Admin smoke runner should not report missing markers on a passing response.' );
}
