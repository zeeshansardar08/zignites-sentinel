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
