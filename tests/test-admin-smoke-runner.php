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

	$checks                   = $runner->get_default_checks();
	$dashboard_markers        = isset( $checks[0]['markers'] ) && is_array( $checks[0]['markers'] ) ? $checks[0]['markers'] : array();
	$first_run_markers        = isset( $checks[1]['markers'] ) && is_array( $checks[1]['markers'] ) ? $checks[1]['markers'] : array();
	$update_readiness_markers = isset( $checks[2]['markers'] ) && is_array( $checks[2]['markers'] ) ? $checks[2]['markers'] : array();
	$detail_markers           = isset( $checks[3]['markers'] ) && is_array( $checks[3]['markers'] ) ? $checks[3]['markers'] : array();
	$detail_optional          = isset( $checks[3]['optional_markers'] ) && is_array( $checks[3]['optional_markers'] ) ? $checks[3]['optional_markers'] : array();
	$snapshot_history_resolve = isset( $checks[4]['resolve'] ) && is_array( $checks[4]['resolve'] ) ? $checks[4]['resolve'] : array();
	$history_markers          = isset( $checks[5]['markers'] ) && is_array( $checks[5]['markers'] ) ? $checks[5]['markers'] : array();
	$empty_history_path       = isset( $checks[6]['path'] ) ? (string) $checks[6]['path'] : '';
	$event_detail_resolve     = isset( $checks[7]['resolve'] ) && is_array( $checks[7]['resolve'] ) ? $checks[7]['resolve'] : array();
	$widget_activity_resolve  = isset( $checks[8]['resolve'] ) && is_array( $checks[8]['resolve'] ) ? $checks[8]['resolve'] : array();
	$widget_markers           = isset( $checks[9]['markers'] ) && is_array( $checks[9]['markers'] ) ? $checks[9]['markers'] : array();

	znts_assert_true( in_array( 'Start Here', $dashboard_markers, true ), 'Admin smoke runner should require the dashboard guidance section.' );
	znts_assert_true( in_array( 'Latest Checkpoint', $dashboard_markers, true ), 'Admin smoke runner should require the dashboard latest-checkpoint section.' );
	znts_assert_true( in_array( 'Create Your First Checkpoint', $first_run_markers, true ), 'Admin smoke runner should expose the deterministic first-run dashboard capture state.' );
	znts_assert_true( in_array( 'Before Update', $update_readiness_markers, true ), 'Admin smoke runner should target the current Before Update heading.' );
	znts_assert_true( in_array( 'Validate Checkpoint', $detail_markers, true ), 'Admin smoke runner should require the selected checkpoint validation section.' );
	znts_assert_true( in_array( 'Rollback Last Restore', $detail_optional, true ), 'Admin smoke runner should track optional rollback controls on the selected checkpoint page.' );
	znts_assert_same( 'admin.php?page=zignites-sentinel', isset( $snapshot_history_resolve['path'] ) ? $snapshot_history_resolve['path'] : '', 'Admin smoke runner should discover snapshot-scoped History links from the dashboard.' );
	znts_assert_true( in_array( 'Checkpoint ID', $history_markers, true ), 'Admin smoke runner should require the checkpoint filter control on History.' );
	znts_assert_true( false !== strpos( $empty_history_path, 'znts-smoke-empty-state-token-9f3a0d66' ), 'Admin smoke runner should keep the deterministic empty-state History query.' );
	znts_assert_same( array( 'Recent History' ), isset( $event_detail_resolve['source_markers'] ) ? $event_detail_resolve['source_markers'] : array(), 'Admin smoke runner should require the History hero before resolving log detail links.' );
	znts_assert_same( 'index.php', isset( $widget_activity_resolve['path'] ) ? $widget_activity_resolve['path'] : '', 'Admin smoke runner should discover widget snapshot activity links from the WordPress dashboard.' );
	znts_assert_true( in_array( 'Next step', $widget_markers, true ), 'Admin smoke runner should require the current widget call-to-action copy.' );
}

function znts_test_admin_url_normalizer_preserves_subdirectory_wp_admin_paths() {
	$_SERVER['HTTP_HOST']      = 'example.test';
	$_SERVER['REQUEST_SCHEME'] = 'http';
	$_SERVER['REQUEST_URI']    = '/zee-dev/wp-admin/admin.php?page=zignites-sentinel';

	znts_assert_same(
		'http://example.test/zee-dev/wp-admin/admin.php?page=zignites-sentinel-event-logs&snapshot_id=2',
		\Zignites\Sentinel\Admin\znts_normalize_admin_url( 'http://example.test/wp-admin/admin.php?page=zignites-sentinel-event-logs&snapshot_id=2' ),
		'Sentinel should normalize admin URLs against the current subdirectory wp-admin path.'
	);

	unset( $_SERVER['HTTP_HOST'], $_SERVER['REQUEST_SCHEME'], $_SERVER['REQUEST_URI'] );
}

function znts_test_admin_smoke_runner_detects_login_fallback_and_missing_markers() {
	$runner = new ZNTS_Admin_Smoke_Runner();
	$check  = array(
		'label'   => 'Dashboard',
		'path'    => 'admin.php?page=zignites-sentinel',
		'markers' => array( 'Zignites Sentinel', 'Start Here' ),
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
		'label'   => 'History',
		'path'    => 'admin.php?page=zignites-sentinel-event-logs',
		'markers' => array( 'History', 'Recent History', 'Filter', 'Checkpoint ID', 'Reset' ),
	);

	$result = $runner->evaluate_response(
		$check,
		200,
		'<html><body><h1>History</h1><h2>Recent History</h2><button>Filter</button><label>Checkpoint ID</label><a>Reset</a></body></html>'
	);

	znts_assert_true( $result['passed'], 'Admin smoke runner should pass when the response is HTTP 200 and all expected markers are present.' );
	znts_assert_same( 0, count( $result['missing_markers'] ), 'Admin smoke runner should not report missing markers on a passing response.' );
}

function znts_test_admin_smoke_runner_tracks_optional_markers_without_failing_required_checks() {
	$runner = new ZNTS_Admin_Smoke_Runner();
	$check  = array(
		'label'            => 'Selected Snapshot Detail',
		'path'             => 'admin.php?page=zignites-sentinel-update-readiness&snapshot_id=12',
		'markers'          => array( 'Before Update', 'Validate Checkpoint', 'Restore Checkpoint', 'Saved Checkpoints', 'Recent History' ),
		'optional_markers' => array( 'Restore Result', 'Rollback Last Restore', 'Rollback Result' ),
	);

	$result = $runner->evaluate_response(
		$check,
		200,
		'<html><body><h1>Before Update</h1><h2>Validate Checkpoint</h2><h2>Restore Checkpoint</h2><h2>Saved Checkpoints</h2><h2>Recent History</h2><h2>Rollback Last Restore</h2></body></html>'
	);

	znts_assert_true( $result['passed'], 'Admin smoke runner should not fail when optional markers are absent but required markers are present.' );
	znts_assert_same( array( 'Rollback Last Restore' ), $result['observed_optional_markers'], 'Admin smoke runner should report optional markers that were found on the page.' );
	znts_assert_same( array( 'Restore Result', 'Rollback Result' ), $result['missing_optional_markers'], 'Admin smoke runner should separately report optional checkpoint markers that were not found.' );
}

function znts_test_admin_smoke_runner_requires_empty_state_markers_for_empty_history_query() {
	$runner = new ZNTS_Admin_Smoke_Runner();
	$check  = array(
		'label'   => 'History Empty State',
		'path'    => 'admin.php?page=zignites-sentinel-event-logs&log_search=znts-smoke-empty-state-token-9f3a0d66',
		'markers' => array( 'History', 'Recent History', 'Filter', 'Reset', 'No history entries match the current filters.' ),
	);

	$result = $runner->evaluate_response(
		$check,
		200,
		'<html><body><h1>History</h1><h2>Recent History</h2><button>Filter</button><a>Reset</a><strong>No history entries match the current filters.</strong></body></html>'
	);

	znts_assert_true( $result['passed'], 'Admin smoke runner should require the History empty-state copy and reset affordance for the deterministic no-match query.' );
}

function znts_test_admin_smoke_runner_skips_event_log_detail_when_no_link_exists() {
	$runner = new ZNTS_Test_Admin_Smoke_Runner();
	$runner->responses['http://example.test/wp-admin/admin.php?page=zignites-sentinel-event-logs'] = array(
		'status_code' => 200,
		'body'        => '<html><body><h1>History</h1><h2>Recent History</h2><p>No detail rows are available.</p></body></html>',
		'error'       => '',
	);

	$resolved = $runner->resolve_check(
		array(
			'label'            => 'Event Log Detail',
			'resolve'          => array(
				'path'       => 'admin.php?page=zignites-sentinel-event-logs',
				'query_args' => array(
					'page'   => 'zignites-sentinel-event-logs',
					'log_id' => true,
				),
				'source_markers' => array(
					'Recent History',
				),
			),
			'resolve_optional' => true,
			'markers'          => array( 'History' ),
		),
		'http://example.test/wp-admin/',
		'wordpress_logged_in_example=abc'
	);

	znts_assert_true( ! empty( $resolved['skipped'] ), 'Admin smoke runner should skip Event Log detail checks when no detail link is present.' );
}

function znts_test_admin_smoke_runner_resolves_event_log_detail_when_present() {
	$runner = new ZNTS_Test_Admin_Smoke_Runner();
	$runner->responses['http://example.test/wp-admin/admin.php?page=zignites-sentinel-event-logs'] = array(
		'status_code' => 200,
		'body'        => '<html><body><h1>History</h1><h2>Recent History</h2><table><tr><td><a href="http://example.test/wp-admin/admin.php?page=zignites-sentinel-event-logs&amp;log_id=91">2026-04-07 10:15:00</a></td></tr></table></body></html>',
		'error'       => '',
	);

	$resolved = $runner->resolve_check(
		array(
			'label'            => 'Event Log Detail',
			'resolve'          => array(
				'path'       => 'admin.php?page=zignites-sentinel-event-logs',
				'query_args' => array(
					'page'   => 'zignites-sentinel-event-logs',
					'log_id' => true,
				),
				'source_markers' => array(
					'Recent History',
				),
			),
			'resolve_optional' => true,
			'markers'          => array( 'History' ),
		),
		'http://example.test/wp-admin/',
		'wordpress_logged_in_example=abc'
	);

	znts_assert_true( empty( $resolved['skipped'] ), 'Admin smoke runner should execute Event Log detail checks when a matching detail link is present.' );
	znts_assert_same( 'http://example.test/wp-admin/admin.php?page=zignites-sentinel-event-logs&log_id=91', $resolved['url'], 'Admin smoke runner should preserve the Event Log detail URL resolved from the History table.' );
}

function znts_test_admin_smoke_runner_rejects_event_log_detail_link_without_history_hero() {
	$runner = new ZNTS_Test_Admin_Smoke_Runner();
	$runner->responses['http://example.test/wp-admin/admin.php?page=zignites-sentinel-event-logs'] = array(
		'status_code' => 200,
		'body'        => '<html><body><a href="http://example.test/wp-admin/admin.php?page=zignites-sentinel-event-logs&amp;log_id=91">2026-04-07 10:15:00</a></body></html>',
		'error'       => '',
	);

	$resolved = $runner->resolve_check(
		array(
			'label'            => 'Event Log Detail',
			'resolve'          => array(
				'path'       => 'admin.php?page=zignites-sentinel-event-logs',
				'query_args' => array(
					'page'   => 'zignites-sentinel-event-logs',
					'log_id' => true,
				),
				'source_markers' => array(
					'Recent History',
				),
			),
			'resolve_optional' => true,
			'markers'          => array( 'History' ),
		),
		'http://example.test/wp-admin/',
		'wordpress_logged_in_example=abc'
	);

	znts_assert_same( 'Source page missing markers: Recent History.', $resolved['resolve_error'], 'Admin smoke runner should reject an Event Log detail link when the source page does not expose the History hero.' );
}

function znts_test_admin_smoke_runner_requires_event_detail_markers_when_link_is_present() {
	$runner = new ZNTS_Admin_Smoke_Runner();
	$check  = array(
		'label'   => 'Event Log Detail',
		'path'    => 'admin.php?page=zignites-sentinel-event-logs&log_id=91',
		'markers' => array( 'History', 'Event Detail', 'Context' ),
	);

	$result = $runner->evaluate_response(
		$check,
		200,
		'<html><body><h1>History</h1><h2>Event Detail</h2><h3>Context</h3></body></html>'
	);

	znts_assert_true( $result['passed'], 'Admin smoke runner should require the Event Detail and Context sections when an Event Log detail link resolves successfully.' );
}

function znts_test_admin_smoke_runner_skips_when_expected_status_code_matches_environment_limit() {
	$runner = new ZNTS_Admin_Smoke_Runner();
	$skip   = $runner->get_skip_decision(
		array(
			'skip_on_status_codes' => array( 403, 500 ),
			'skip_reason'          => 'Skipped because network admin is not available on this install or for this user.',
		),
		403,
		''
	);

	znts_assert_true( ! empty( $skip['skipped'] ), 'Admin smoke runner should skip checks when an expected environment-limitation status code is returned.' );
	znts_assert_same( 'Skipped because network admin is not available on this install or for this user.', $skip['reason'], 'Admin smoke runner should preserve the explicit skip reason for environment-limited checks.' );
}

function znts_test_admin_smoke_runner_does_not_skip_when_status_code_is_not_listed() {
	$runner = new ZNTS_Admin_Smoke_Runner();
	$skip   = $runner->get_skip_decision(
		array(
			'skip_on_status_codes' => array( 403, 500 ),
		),
		200,
		''
	);

	znts_assert_true( empty( $skip['skipped'] ), 'Admin smoke runner should not skip checks when the response status code does not match an environment-limitation status.' );
}

function znts_test_admin_smoke_runner_resolves_snapshot_scoped_event_logs_from_dashboard() {
	$runner = new ZNTS_Test_Admin_Smoke_Runner();
	$runner->responses['http://example.test/wp-admin/admin.php?page=zignites-sentinel'] = array(
		'status_code' => 200,
		'body'        => '<html><body><h1>Zignites Sentinel</h1><a href="http://example.test/wp-admin/admin.php?page=zignites-sentinel-event-logs&amp;snapshot_id=12">Open History</a></body></html>',
		'error'       => '',
	);

	$resolved = $runner->resolve_check(
		array(
			'label'   => 'Dashboard Snapshot History',
			'resolve' => array(
				'path'       => 'admin.php?page=zignites-sentinel',
				'query_args' => array(
					'page'        => 'zignites-sentinel-event-logs',
					'snapshot_id' => true,
				),
			),
			'markers' => array( 'History' ),
		),
		'http://example.test/wp-admin/',
		'wordpress_logged_in_example=abc'
	);

	znts_assert_same( 'http://example.test/wp-admin/admin.php?page=zignites-sentinel-event-logs&snapshot_id=12', $resolved['url'], 'Admin smoke runner should resolve snapshot-scoped History links from the dashboard.' );
}

function znts_test_admin_smoke_runner_skips_widget_snapshot_activity_when_no_link_exists() {
	$runner = new ZNTS_Test_Admin_Smoke_Runner();
	$runner->responses['http://example.test/wp-admin/index.php'] = array(
		'status_code' => 200,
		'body'        => '<html><body><h2>Sentinel Site Status</h2><a href="http://example.test/wp-admin/admin.php?page=zignites-sentinel-update-readiness">Open Before Update</a></body></html>',
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
			'markers'          => array( 'History' ),
		),
		'http://example.test/wp-admin/',
		'wordpress_logged_in_example=abc'
	);

	znts_assert_true( ! empty( $resolved['skipped'] ), 'Admin smoke runner should skip widget snapshot activity checks when the widget has no snapshot history link.' );
}

function znts_test_admin_smoke_runner_resolves_widget_snapshot_activity_when_present() {
	$runner = new ZNTS_Test_Admin_Smoke_Runner();
	$runner->responses['http://example.test/wp-admin/index.php'] = array(
		'status_code' => 200,
		'body'        => '<html><body><h2>Sentinel Site Status</h2><a href="http://example.test/wp-admin/admin.php?page=zignites-sentinel-event-logs&amp;snapshot_id=12">Open History</a></body></html>',
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
			'markers'          => array( 'History' ),
		),
		'http://example.test/wp-admin/',
		'wordpress_logged_in_example=abc'
	);

	znts_assert_same( 'http://example.test/wp-admin/admin.php?page=zignites-sentinel-event-logs&snapshot_id=12', $resolved['url'], 'Admin smoke runner should resolve widget snapshot activity links when present.' );
}

function znts_test_admin_smoke_runner_allows_config_prerequisite_lists() {
	$config = array(
		'prerequisites' => array(
			'Pending updates should exist on the target WordPress update screens.',
			'Network checks require multisite and network admin access.',
		),
	);

	znts_assert_same( 2, count( $config['prerequisites'] ), 'Admin smoke runner configs should support a top-level prerequisite list for operator guidance.' );
	znts_assert_same( 'Network checks require multisite and network admin access.', $config['prerequisites'][1], 'Admin smoke runner prerequisite lists should preserve explanatory environment notes.' );
}
