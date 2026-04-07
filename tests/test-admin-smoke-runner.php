<?php
/**
 * Focused tests for the live admin smoke runner helper.
 */

require_once __DIR__ . '/class-admin-smoke-runner.php';

class ZNTS_Test_Admin_Smoke_Runner extends ZNTS_Admin_Smoke_Runner {
	public $responses = array();

	public function fetch( $url, $cookie_header, $timeout = 20 ) {
		if ( isset( $this->responses[ $url ] ) ) {
			return $this->responses[ $url ];
		}

		return array(
			'status_code' => 404,
			'body'        => '',
			'error'       => 'Missing fixture response.',
		);
	}
}

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

	znts_assert_same(
		'http://example.test/wp-admin/admin.php?page=zignites-sentinel-update-readiness&snapshot_id=12',
		$runner->build_url( 'http://example.test/wp-admin/', 'http://example.test/wp-admin/admin.php?page=zignites-sentinel-update-readiness&snapshot_id=12' ),
		'Admin smoke runner should preserve already absolute admin URLs when a resolved check returns one.'
	);

	$checks              = $runner->get_default_checks();
	$detail_check           = $checks[2];
	$snapshot_logs_check    = $checks[3];
	$run_journal_check      = $checks[4];
	$dashboard_logs_check   = $checks[6];
	$widget_activity_check  = $checks[7];
	$widget_check           = $checks[8];
	$widget_markers      = isset( $widget_check['markers'] ) && is_array( $widget_check['markers'] ) ? $widget_check['markers'] : array();
	$contains_sentinel   = in_array( 'Sentinel', $widget_markers, true );
	$contains_old_marker = in_array( 'Zignites Sentinel', $widget_markers, true );
	$detail_markers      = isset( $detail_check['markers'] ) && is_array( $detail_check['markers'] ) ? $detail_check['markers'] : array();
	$detail_optional     = isset( $detail_check['optional_markers'] ) && is_array( $detail_check['optional_markers'] ) ? $detail_check['optional_markers'] : array();
	$snapshot_logs_resolve = isset( $snapshot_logs_check['resolve'] ) && is_array( $snapshot_logs_check['resolve'] ) ? $snapshot_logs_check['resolve'] : array();
	$run_journal_resolve   = isset( $run_journal_check['resolve'] ) && is_array( $run_journal_check['resolve'] ) ? $run_journal_check['resolve'] : array();
	$dashboard_logs_resolve = isset( $dashboard_logs_check['resolve'] ) && is_array( $dashboard_logs_check['resolve'] ) ? $dashboard_logs_check['resolve'] : array();
	$widget_activity_resolve = isset( $widget_activity_check['resolve'] ) && is_array( $widget_activity_check['resolve'] ) ? $widget_activity_check['resolve'] : array();

	znts_assert_true( $contains_sentinel, 'Admin smoke runner should expect the current dashboard widget heading marker.' );
	znts_assert_true( ! $contains_old_marker, 'Admin smoke runner should not require the stale dashboard widget heading marker.' );
	znts_assert_true( in_array( 'Snapshot Summary', $detail_markers, true ), 'Admin smoke runner should include a selected snapshot detail check for snapshot-scoped surfaces.' );
	znts_assert_true( in_array( 'Restore Impact Summary', $detail_optional, true ), 'Admin smoke runner should report optional restore impact markers on the selected snapshot detail page.' );
	znts_assert_same( 'admin.php?page=zignites-sentinel-update-readiness', isset( $snapshot_logs_resolve['path'] ) ? $snapshot_logs_resolve['path'] : '', 'Admin smoke runner should discover snapshot-scoped Event Logs from the selected snapshot screen.' );
	znts_assert_true( ! empty( $run_journal_check['resolve_optional'] ), 'Admin smoke runner should treat selected snapshot run-journal discovery as optional when no journal links are present.' );
	znts_assert_same( 'admin.php?page=zignites-sentinel-update-readiness', isset( $run_journal_resolve['path'] ) ? $run_journal_resolve['path'] : '', 'Admin smoke runner should discover run-journal links from the selected snapshot screen.' );
	znts_assert_same( 'admin.php?page=zignites-sentinel', isset( $dashboard_logs_resolve['path'] ) ? $dashboard_logs_resolve['path'] : '', 'Admin smoke runner should discover snapshot-scoped Event Logs from the dashboard.' );
	znts_assert_true( ! empty( $widget_activity_check['resolve_optional'] ), 'Admin smoke runner should treat widget snapshot activity discovery as optional when the widget has no latest snapshot.' );
	znts_assert_same( 'index.php', isset( $widget_activity_resolve['path'] ) ? $widget_activity_resolve['path'] : '', 'Admin smoke runner should discover widget snapshot activity links from the WordPress dashboard.' );
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

function znts_test_admin_smoke_runner_tracks_optional_markers_without_failing_required_checks() {
	$runner = new ZNTS_Admin_Smoke_Runner();
	$check  = array(
		'label'            => 'Selected Snapshot Detail',
		'path'             => 'admin.php?page=zignites-sentinel-update-readiness&snapshot_id=12',
		'markers'          => array( 'Snapshot Summary', 'Snapshot Health Baseline' ),
		'optional_markers' => array( 'Health Comparison', 'Restore Impact Summary' ),
	);

	$result = $runner->evaluate_response(
		$check,
		200,
		'<html><body><h2>Snapshot Summary</h2><h2>Snapshot Health Baseline</h2><details><summary>Health Comparison</summary></details></body></html>'
	);

	znts_assert_true( $result['passed'], 'Admin smoke runner should not fail when optional markers are absent but required markers are present.' );
	znts_assert_same( array( 'Health Comparison' ), $result['observed_optional_markers'], 'Admin smoke runner should report optional markers that were found on the page.' );
	znts_assert_same( array( 'Restore Impact Summary' ), $result['missing_optional_markers'], 'Admin smoke runner should separately report optional markers that were not found.' );
}

function znts_test_admin_smoke_runner_requires_filtered_state_markers_for_snapshot_logs() {
	$runner = new ZNTS_Admin_Smoke_Runner();
	$check  = array(
		'label'   => 'Selected Snapshot Event Logs',
		'path'    => 'admin.php?page=zignites-sentinel-event-logs&snapshot_id=205',
		'markers' => array( 'Event Logs', 'Export Filtered CSV', 'Filter', 'Current filters are active.' ),
	);

	$result = $runner->evaluate_response(
		$check,
		200,
		'<html><body><h1>Event Logs</h1><button>Export Filtered CSV</button><button>Filter</button><p>Current filters are active. Scan the highlighted rows first.</p></body></html>'
	);

	znts_assert_true( $result['passed'], 'Admin smoke runner should treat the filtered-state guidance as required on snapshot-scoped Event Logs pages.' );
}

function znts_test_admin_smoke_runner_resolves_selected_snapshot_links_from_update_readiness() {
	$runner = new ZNTS_Test_Admin_Smoke_Runner();
	$runner->responses['http://example.test/wp-admin/admin.php?page=zignites-sentinel-update-readiness'] = array(
		'status_code' => 200,
		'body'        => '<html><body><a href="http://example.test/wp-admin/admin.php?page=zignites-sentinel-update-readiness&amp;snapshot_id=205">Open snapshot</a></body></html>',
		'error'       => '',
	);

	$resolved = $runner->resolve_check(
		array(
			'label'   => 'Selected Snapshot Detail',
			'resolve' => array(
				'path'       => 'admin.php?page=zignites-sentinel-update-readiness',
				'query_args' => array(
					'page'        => 'zignites-sentinel-update-readiness',
					'snapshot_id' => true,
				),
			),
			'markers' => array( 'Snapshot Summary' ),
		),
		'http://example.test/wp-admin/',
		'wordpress_logged_in_example=abc'
	);

	znts_assert_same( '', $resolved['resolve_error'], 'Admin smoke runner should resolve selected snapshot links from the update-readiness list.' );
	znts_assert_same( 'http://example.test/wp-admin/admin.php?page=zignites-sentinel-update-readiness&snapshot_id=205', $resolved['url'], 'Admin smoke runner should return the first matching selected-snapshot URL.' );
	znts_assert_same( 'http://example.test/wp-admin/admin.php?page=zignites-sentinel-update-readiness', $resolved['source_url'], 'Admin smoke runner should preserve the source page used for snapshot discovery.' );
}

function znts_test_admin_smoke_runner_resolves_snapshot_scoped_event_logs_from_update_readiness() {
	$runner = new ZNTS_Test_Admin_Smoke_Runner();
	$runner->responses['http://example.test/wp-admin/admin.php?page=zignites-sentinel-update-readiness'] = array(
		'status_code' => 200,
		'body'        => '<html><body><a href="http://example.test/wp-admin/admin.php?page=zignites-sentinel-event-logs&amp;snapshot_id=205">View full event history</a></body></html>',
		'error'       => '',
	);

	$resolved = $runner->resolve_check(
		array(
			'label'   => 'Selected Snapshot Event Logs',
			'resolve' => array(
				'path'       => 'admin.php?page=zignites-sentinel-update-readiness',
				'query_args' => array(
					'page'        => 'zignites-sentinel-event-logs',
					'snapshot_id' => true,
				),
			),
			'markers' => array( 'Event Logs' ),
		),
		'http://example.test/wp-admin/',
		'wordpress_logged_in_example=abc'
	);

	znts_assert_same( '', $resolved['resolve_error'], 'Admin smoke runner should resolve snapshot-scoped Event Logs links from the selected snapshot screen.' );
	znts_assert_same( 'http://example.test/wp-admin/admin.php?page=zignites-sentinel-event-logs&snapshot_id=205', $resolved['url'], 'Admin smoke runner should preserve the snapshot-scoped Event Logs URL resolved from Update Readiness.' );
	znts_assert_same( 'http://example.test/wp-admin/admin.php?page=zignites-sentinel-update-readiness', $resolved['source_url'], 'Admin smoke runner should record the selected snapshot screen as the source page for snapshot-scoped Event Logs discovery.' );
}

function znts_test_admin_smoke_runner_skips_optional_run_journal_when_no_link_exists() {
	$runner = new ZNTS_Test_Admin_Smoke_Runner();
	$runner->responses['http://example.test/wp-admin/admin.php?page=zignites-sentinel-update-readiness'] = array(
		'status_code' => 200,
		'body'        => '<html><body><p>No run journal links are currently present.</p></body></html>',
		'error'       => '',
	);

	$resolved = $runner->resolve_check(
		array(
			'label'            => 'Selected Snapshot Run Journal',
			'resolve'          => array(
				'path'       => 'admin.php?page=zignites-sentinel-update-readiness',
				'query_args' => array(
					'page'   => 'zignites-sentinel-event-logs',
					'source' => true,
					'run_id' => true,
				),
			),
			'resolve_optional' => true,
			'markers'          => array( 'Event Logs' ),
		),
		'http://example.test/wp-admin/',
		'wordpress_logged_in_example=abc'
	);

	znts_assert_true( ! empty( $resolved['skipped'] ), 'Admin smoke runner should skip optional run-journal checks when no matching journal link is present.' );
	znts_assert_same( 'No matching optional admin link was present on the source page.', $resolved['skip_reason'], 'Admin smoke runner should explain why an optional run-journal check was skipped.' );
	znts_assert_same( '', $resolved['resolve_error'], 'Admin smoke runner should not treat missing optional run-journal links as a resolve failure.' );
}

function znts_test_admin_smoke_runner_resolves_run_journal_from_update_readiness_when_present() {
	$runner = new ZNTS_Test_Admin_Smoke_Runner();
	$runner->responses['http://example.test/wp-admin/admin.php?page=zignites-sentinel-update-readiness'] = array(
		'status_code' => 200,
		'body'        => '<html><body><a href="http://example.test/wp-admin/admin.php?page=zignites-sentinel-event-logs&amp;source=restore-execution-journal&amp;run_id=run-42&amp;snapshot_id=205">Run run-42</a></body></html>',
		'error'       => '',
	);

	$resolved = $runner->resolve_check(
		array(
			'label'            => 'Selected Snapshot Run Journal',
			'resolve'          => array(
				'path'       => 'admin.php?page=zignites-sentinel-update-readiness',
				'query_args' => array(
					'page'   => 'zignites-sentinel-event-logs',
					'source' => true,
					'run_id' => true,
				),
			),
			'resolve_optional' => true,
			'markers'          => array( 'Event Logs' ),
		),
		'http://example.test/wp-admin/',
		'wordpress_logged_in_example=abc'
	);

	znts_assert_true( empty( $resolved['skipped'] ), 'Admin smoke runner should execute optional run-journal checks when a matching link is present.' );
	znts_assert_same( '', $resolved['resolve_error'], 'Admin smoke runner should resolve the run-journal link when it is present.' );
	znts_assert_same( 'http://example.test/wp-admin/admin.php?page=zignites-sentinel-event-logs&source=restore-execution-journal&run_id=run-42&snapshot_id=205', $resolved['url'], 'Admin smoke runner should preserve the resolved run-journal URL from Update Readiness.' );
}

function znts_test_admin_smoke_runner_requires_run_journal_markers_when_link_is_present() {
	$runner = new ZNTS_Admin_Smoke_Runner();
	$check  = array(
		'label'   => 'Selected Snapshot Run Journal',
		'path'    => 'admin.php?page=zignites-sentinel-event-logs&source=restore-execution-journal&run_id=run-42&snapshot_id=205',
		'markers' => array( 'Event Logs', 'Export Filtered CSV', 'Filter', 'Current filters are active.', 'Run Journal' ),
	);

	$result = $runner->evaluate_response(
		$check,
		200,
		'<html><body><h1>Event Logs</h1><button>Export Filtered CSV</button><button>Filter</button><p>Current filters are active. Scan the highlighted rows first.</p><h2>Run Journal</h2></body></html>'
	);

	znts_assert_true( $result['passed'], 'Admin smoke runner should require the Run Journal section when a run-journal link resolves successfully.' );
}

function znts_test_admin_smoke_runner_resolves_snapshot_scoped_event_logs_from_dashboard() {
	$runner = new ZNTS_Test_Admin_Smoke_Runner();
	$runner->responses['http://example.test/wp-admin/admin.php?page=zignites-sentinel'] = array(
		'status_code' => 200,
		'body'        => '<html><body><a href="http://example.test/wp-admin/admin.php?page=zignites-sentinel-event-logs&amp;snapshot_id=88">Event Logs</a></body></html>',
		'error'       => '',
	);

	$resolved = $runner->resolve_check(
		array(
			'label'   => 'Dashboard Snapshot Event Logs',
			'resolve' => array(
				'path'       => 'admin.php?page=zignites-sentinel',
				'query_args' => array(
					'page'        => 'zignites-sentinel-event-logs',
					'snapshot_id' => true,
				),
			),
			'markers' => array( 'Event Logs' ),
		),
		'http://example.test/wp-admin/',
		'wordpress_logged_in_example=abc'
	);

	znts_assert_same( '', $resolved['resolve_error'], 'Admin smoke runner should resolve snapshot-scoped Event Logs links from the dashboard.' );
	znts_assert_same( 'http://example.test/wp-admin/admin.php?page=zignites-sentinel-event-logs&snapshot_id=88', $resolved['url'], 'Admin smoke runner should preserve the snapshot-scoped Event Logs URL resolved from the dashboard.' );
	znts_assert_same( 'http://example.test/wp-admin/admin.php?page=zignites-sentinel', $resolved['source_url'], 'Admin smoke runner should record the dashboard as the source page for snapshot-scoped Event Logs discovery.' );
}

function znts_test_admin_smoke_runner_skips_widget_snapshot_activity_when_no_link_exists() {
	$runner = new ZNTS_Test_Admin_Smoke_Runner();
	$runner->responses['http://example.test/wp-admin/index.php'] = array(
		'status_code' => 200,
		'body'        => '<html><body><div class="znts-dashboard-widget"><a href="http://example.test/wp-admin/admin.php?page=zignites-sentinel-update-readiness">Open Update Readiness</a></div></body></html>',
		'error'       => '',
	);

	$resolved = $runner->resolve_check(
		array(
			'label'            => 'Widget Snapshot Activity',
			'resolve'          => array(
				'path'       => 'index.php',
				'query_args' => array(
					'page'        => 'zignites-sentinel-event-logs',
					'snapshot_id' => true,
				),
			),
			'resolve_optional' => true,
			'markers'          => array( 'Event Logs' ),
		),
		'http://example.test/wp-admin/',
		'wordpress_logged_in_example=abc'
	);

	znts_assert_true( ! empty( $resolved['skipped'] ), 'Admin smoke runner should skip optional widget snapshot activity checks when the widget has no snapshot activity link.' );
	znts_assert_same( '', $resolved['resolve_error'], 'Admin smoke runner should not treat a missing optional widget snapshot activity link as a resolve failure.' );
}

function znts_test_admin_smoke_runner_resolves_widget_snapshot_activity_when_present() {
	$runner = new ZNTS_Test_Admin_Smoke_Runner();
	$runner->responses['http://example.test/wp-admin/index.php'] = array(
		'status_code' => 200,
		'body'        => '<html><body><div class="znts-dashboard-widget"><a href="http://example.test/wp-admin/admin.php?page=zignites-sentinel-event-logs&amp;snapshot_id=205">Open Snapshot Activity</a></div></body></html>',
		'error'       => '',
	);

	$resolved = $runner->resolve_check(
		array(
			'label'            => 'Widget Snapshot Activity',
			'resolve'          => array(
				'path'       => 'index.php',
				'query_args' => array(
					'page'        => 'zignites-sentinel-event-logs',
					'snapshot_id' => true,
				),
			),
			'resolve_optional' => true,
			'markers'          => array( 'Event Logs' ),
		),
		'http://example.test/wp-admin/',
		'wordpress_logged_in_example=abc'
	);

	znts_assert_true( empty( $resolved['skipped'] ), 'Admin smoke runner should execute widget snapshot activity checks when a matching link is present.' );
	znts_assert_same( '', $resolved['resolve_error'], 'Admin smoke runner should resolve the widget snapshot activity link when it is present.' );
	znts_assert_same( 'http://example.test/wp-admin/admin.php?page=zignites-sentinel-event-logs&snapshot_id=205', $resolved['url'], 'Admin smoke runner should preserve the widget snapshot activity URL when it is present.' );
}
