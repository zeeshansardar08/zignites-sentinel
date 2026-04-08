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
