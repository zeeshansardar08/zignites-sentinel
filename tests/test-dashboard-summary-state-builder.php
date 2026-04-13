<?php
/**
 * Focused tests for dashboard summary state building.
 */

require_once __DIR__ . '/bootstrap.php';

use Zignites\Sentinel\Admin\DashboardSummaryStateBuilder;

function znts_test_dashboard_summary_state_builder_normalizes_dashboard_inputs() {
	$builder  = new DashboardSummaryStateBuilder();
	$repo     = new ZNTS_Fake_Dashboard_Snapshot_Repository();
	$health   = new ZNTS_Fake_Dashboard_Health_Score();
	$resolver = new ZNTS_Fake_Dashboard_Status_Resolver();

	$repo->recent = array(
		array(
			'id'    => 91,
			'label' => 'Latest snapshot',
		),
	);
	$health->result = array(
		'details' => array(
			'open_conflicts' => array(
				'warning' => 0,
			),
		),
	);
	$resolver->status_index = array(
		91 => array(
			'restore_ready' => true,
		),
	);
	$resolver->site_status_card = array(
		'status' => 'stable',
		'latest_snapshot' => array(
			'id' => 91,
		),
	);
	$resolver->system_health_card = array(
		'status' => 'safe',
	);
	$resolver->snapshot_intelligence = array(
		'recommended_snapshot' => array(
			'id' => 91,
		),
	);
	$resolver->operator_timeline = array(
		'items' => array(
			array(
				'title' => 'Snapshot taken',
			),
		),
	);

	$state = $builder->build_summary_state(
		1,
		$repo,
		$health,
		$resolver,
		'http://example.test/wp-admin/admin.php?page=zignites-sentinel-event-logs&snapshot_id=91'
	);

	znts_assert_same( 1, count( $state['recent_snapshots'] ), 'Dashboard summary state builder should preserve the requested recent snapshot window.' );
	znts_assert_same( 'stable', $state['site_status_card']['status'], 'Dashboard summary state builder should preserve the site-status card payload.' );
	znts_assert_same( true, $state['snapshot_status_index'][91]['restore_ready'], 'Dashboard summary state builder should preserve the snapshot status index payload.' );
	znts_assert_same( 'safe', $state['system_health']['status'], 'Dashboard summary state builder should preserve the system-health payload.' );
	znts_assert_same( 91, $state['snapshot_intelligence']['recommended_snapshot']['id'], 'Dashboard summary state builder should preserve the snapshot intelligence payload.' );
	znts_assert_same( 'Snapshot taken', $state['operator_timeline']['items'][0]['title'], 'Dashboard summary state builder should preserve the operator timeline payload.' );
	znts_assert_same( array(), $state['help_panels'], 'Dashboard summary state builder should default optional help panels to an empty array before presentation.' );
	znts_assert_same( array(), $state['empty_states'], 'Dashboard summary state builder should default optional empty states to an empty array before presentation.' );
	znts_assert_same( 'http://example.test/wp-admin/admin.php?page=zignites-sentinel-event-logs&snapshot_id=91', $state['activity_url'], 'Dashboard summary state builder should preserve the supplied activity URL.' );
}

function znts_test_dashboard_summary_state_builder_normalizes_restore_summary_inputs() {
	$builder = new DashboardSummaryStateBuilder();
	$state   = $builder->build_restore_summary_state(
		array(
			'id'    => 92,
			'label' => 'Restore snapshot',
		),
		array(
			'can_execute' => false,
		),
		null,
		array(
			'status' => 'ready',
		),
		null,
		array(
			'run_id' => 'restore-1',
		),
		array(
			'run_id' => 'rollback-1',
		),
		array(
			'is_fresh' => false,
		),
		array(
			'is_fresh' => true,
		),
		'http://example.test/wp-admin/admin.php?page=zignites-sentinel-event-logs&snapshot_id=92'
	);

	znts_assert_same( 92, $state['snapshot']['id'], 'Dashboard restore summary state builder should preserve the selected snapshot.' );
	znts_assert_same( false, $state['checklist']['can_execute'], 'Dashboard restore summary state builder should preserve the operator checklist payload.' );
	znts_assert_same( null, $state['baseline'], 'Dashboard restore summary state builder should preserve a missing baseline as null so the presenter can report the gate as absent.' );
	znts_assert_same( 'ready', $state['stage']['status'], 'Dashboard restore summary state builder should preserve the stage checkpoint payload.' );
	znts_assert_same( null, $state['plan'], 'Dashboard restore summary state builder should preserve a missing plan checkpoint as null so the presenter can report the gate as absent.' );
	znts_assert_same( 'restore-1', $state['execution']['run_id'], 'Dashboard restore summary state builder should preserve the execution payload.' );
	znts_assert_same( 'rollback-1', $state['rollback']['run_id'], 'Dashboard restore summary state builder should preserve the rollback payload.' );
	znts_assert_same( false, $state['stage_timing']['is_fresh'], 'Dashboard restore summary state builder should preserve the stage timing payload.' );
	znts_assert_same( true, $state['plan_timing']['is_fresh'], 'Dashboard restore summary state builder should preserve the plan timing payload.' );
	znts_assert_same( 'http://example.test/wp-admin/admin.php?page=zignites-sentinel-event-logs&snapshot_id=92', $state['activity_url'], 'Dashboard restore summary state builder should preserve the supplied activity URL.' );
}

function znts_test_dashboard_summary_state_builder_normalizes_health_strip_inputs() {
	$builder = new DashboardSummaryStateBuilder();
	$state   = $builder->build_health_strip_state(
		array(
			'id'    => 93,
			'label' => 'Health snapshot',
		),
		array(
			array(
				'label'  => 'Baseline',
				'status' => 'healthy',
			),
			array(
				'label'  => 'Post-Restore',
				'status' => 'warning',
			),
		)
	);

	znts_assert_same( 93, $state['snapshot']['id'], 'Dashboard health strip state builder should preserve the selected snapshot.' );
	znts_assert_same( 2, count( $state['rows'] ), 'Dashboard health strip state builder should preserve the supplied health comparison rows.' );
	znts_assert_same( 'warning', $state['rows'][1]['status'], 'Dashboard health strip state builder should preserve the row payload order and values.' );
}

function znts_test_dashboard_summary_state_builder_normalizes_widget_view_state() {
	$builder = new DashboardSummaryStateBuilder();
	$state   = $builder->build_widget_state(
		array(
			'site_status_card' => array(
				'status' => 'stable',
			),
			'restore_health_strip' => array(
				'rows' => array(
					array( 'label' => 'Baseline' ),
				),
			),
			'recent_snapshots' => array(
				array(
					'id'    => 94,
					'label' => 'Widget snapshot',
				),
			),
			'snapshot_status_index' => array(
				94 => array(
					'restore_ready' => true,
				),
			),
		)
	);

	znts_assert_same( 'stable', $state['site_status_card']['status'], 'Dashboard widget state builder should preserve the site-status card payload.' );
	znts_assert_same( 'Widget snapshot', $state['latest_snapshot']['label'], 'Dashboard widget state builder should preserve the latest snapshot payload.' );
	znts_assert_same( true, $state['snapshot_status']['restore_ready'], 'Dashboard widget state builder should resolve the latest snapshot status payload.' );
	znts_assert_same( 'Baseline', $state['restore_health_strip']['rows'][0]['label'], 'Dashboard widget state builder should preserve the health strip payload.' );
}

function znts_test_dashboard_summary_state_builder_normalizes_summary_view_state() {
	$builder = new DashboardSummaryStateBuilder();
	$state   = $builder->build_summary_view_state(
		array(
			'recent_snapshots' => array(
				array(
					'id' => 95,
				),
			),
			'health_score' => array(
				'details' => array(),
			),
			'snapshot_status_index' => array(
				95 => array(
					'restore_ready' => true,
				),
			),
			'site_status_card' => array(
				'status' => 'stable',
			),
			'system_health' => array(
				'status' => 'safe',
			),
			'snapshot_intelligence' => array(
				'recommended_snapshot' => array(
					'id' => 95,
				),
			),
			'operator_timeline' => array(
				'items' => array(
					array( 'title' => 'Snapshot taken' ),
				),
			),
		),
		array(
			'rows' => array(
				array( 'label' => 'Baseline' ),
			),
		),
		'http://example.test/wp-admin/admin.php?page=zignites-sentinel-event-logs&snapshot_id=95'
	);

	znts_assert_same( 1, count( $state['recent_snapshots'] ), 'Dashboard summary view-state builder should preserve recent snapshots.' );
	znts_assert_same( 'stable', $state['site_status_card']['status'], 'Dashboard summary view-state builder should preserve the site-status card payload.' );
	znts_assert_same( true, $state['snapshot_status_index'][95]['restore_ready'], 'Dashboard summary view-state builder should preserve the snapshot status index payload.' );
	znts_assert_same( 'Baseline', $state['restore_health_strip']['rows'][0]['label'], 'Dashboard summary view-state builder should preserve the health strip payload.' );
	znts_assert_same( 'safe', $state['system_health']['status'], 'Dashboard summary view-state builder should preserve the system-health payload.' );
	znts_assert_same( 95, $state['snapshot_intelligence']['recommended_snapshot']['id'], 'Dashboard summary view-state builder should preserve the snapshot intelligence payload.' );
	znts_assert_same( 'Snapshot taken', $state['operator_timeline']['items'][0]['title'], 'Dashboard summary view-state builder should preserve the operator timeline payload.' );
	znts_assert_same( array(), $state['help_panels'], 'Dashboard summary view-state builder should preserve optional help panels as arrays.' );
	znts_assert_same( array(), $state['positioning_note'], 'Dashboard summary view-state builder should preserve optional positioning notes as arrays.' );
	znts_assert_same( 'http://example.test/wp-admin/admin.php?page=zignites-sentinel-event-logs&snapshot_id=95', $state['activity_url'], 'Dashboard summary view-state builder should preserve the supplied activity URL.' );
}

function znts_test_dashboard_summary_state_builder_normalizes_dashboard_screen_state() {
	$builder = new DashboardSummaryStateBuilder();
	$state   = $builder->build_dashboard_screen_state(
		array(
			'recent_snapshots' => array(
				array(
					'id' => 96,
				),
			),
			'health_score' => array(
				'score' => 98,
			),
			'restore_health_strip' => array(
				'rows' => array(
					array( 'label' => 'Baseline' ),
				),
			),
			'snapshot_status_index' => array(
				96 => array(
					'restore_ready' => true,
				),
			),
			'site_status_card' => array(
				'status' => 'stable',
			),
			'system_health' => array(
				'status' => 'safe',
			),
			'snapshot_intelligence' => array(
				'recommended_snapshot' => array(
					'id' => 96,
				),
			),
			'operator_timeline' => array(
				'items' => array(
					array( 'title' => 'Snapshot taken' ),
				),
			),
		),
		array(
			'plugin_version'  => '1.32.0',
			'db_version'      => '1.4.0',
			'logs_table'      => 'wp_znts_logs',
			'conflicts_table' => 'wp_znts_conflicts',
			'wordpress'       => '6.8.1',
			'php'             => '8.1.10',
			'site_url'        => 'http://example.test',
			'recent_logs'     => array(
				array( 'message' => 'Latest log' ),
			),
			'recent_conflicts' => array(
				array( 'summary' => 'Latest conflict' ),
			),
		)
	);

	znts_assert_same( '1.32.0', $state['plugin_version'], 'Dashboard screen state builder should preserve plugin version metadata.' );
	znts_assert_same( 'wp_znts_logs', $state['logs_table'], 'Dashboard screen state builder should preserve logs table metadata.' );
	znts_assert_same( 'Latest log', $state['recent_logs'][0]['message'], 'Dashboard screen state builder should preserve recent logs.' );
	znts_assert_same( 'Latest conflict', $state['recent_conflicts'][0]['summary'], 'Dashboard screen state builder should preserve recent conflicts.' );
	znts_assert_same( 98, $state['health_score']['score'], 'Dashboard screen state builder should preserve the summary health score payload.' );
	znts_assert_same( 'stable', $state['site_status_card']['status'], 'Dashboard screen state builder should preserve the summary site-status payload.' );
	znts_assert_same( 'safe', $state['system_health']['status'], 'Dashboard screen state builder should preserve the system-health payload.' );
	znts_assert_same( 96, $state['snapshot_intelligence']['recommended_snapshot']['id'], 'Dashboard screen state builder should preserve the snapshot intelligence payload.' );
	znts_assert_same( 'Snapshot taken', $state['operator_timeline']['items'][0]['title'], 'Dashboard screen state builder should preserve the operator timeline payload.' );
	znts_assert_same( array(), $state['help_panels'], 'Dashboard screen state builder should preserve optional help panels as arrays.' );
}
