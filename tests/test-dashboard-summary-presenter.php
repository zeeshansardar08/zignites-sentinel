<?php
/**
 * Focused tests for dashboard summary presenter payload composition.
 */

require_once __DIR__ . '/bootstrap.php';

use Zignites\Sentinel\Admin\DashboardSummaryPresenter;

function znts_test_dashboard_summary_presenter_adds_latest_snapshot_links() {
	$presenter = new DashboardSummaryPresenter();

	$payload = $presenter->build_summary_payload(
		array(
			array(
				'id'    => 91,
				'label' => 'Latest snapshot',
			),
		),
		array(
			'details' => array(),
		),
		array(
			91 => array(
				'restore_ready' => true,
			),
		),
		array(
			'status'          => 'stable',
			'label'           => 'Stable',
			'system_health'   => array(
				'status' => 'safe',
				'label'  => 'Safe',
			),
			'snapshot_intelligence' => array(
				'recommended_snapshot' => array(
					'id'    => 91,
					'label' => 'Latest snapshot',
				),
			),
			'operator_timeline' => array(
				'items' => array(
					array( 'title' => 'Snapshot taken' ),
				),
			),
			'latest_snapshot' => array(
				'id' => 91,
			),
		),
		array(
			'rows' => array(),
		),
		'zignites-sentinel-update-readiness',
		'http://example.test/wp-admin/admin.php?page=zignites-sentinel-event-logs&snapshot_id=91'
	);

	znts_assert_true( false !== strpos( $payload['site_status_card']['detail_url'], 'page=zignites-sentinel-update-readiness' ), 'Dashboard summary presenter should add an update-readiness detail URL for the latest snapshot.' );
	znts_assert_true( false !== strpos( $payload['site_status_card']['detail_url'], 'snapshot_id=91' ), 'Dashboard summary presenter should add the latest snapshot ID to the detail URL.' );
	znts_assert_same( 'http://example.test/wp-admin/admin.php?page=zignites-sentinel-event-logs&snapshot_id=91', $payload['site_status_card']['activity_url'], 'Dashboard summary presenter should preserve the snapshot activity URL for the latest snapshot.' );
	znts_assert_same( 'Safe', $payload['system_health']['label'], 'Dashboard summary presenter should expose the system-health payload.' );
	znts_assert_true( false !== strpos( $payload['snapshot_intelligence']['recommended_snapshot']['detail_url'], 'snapshot_id=91' ), 'Dashboard summary presenter should add readiness links to recommended snapshots.' );
	znts_assert_same( 'Snapshot taken', $payload['operator_timeline']['items'][0]['title'], 'Dashboard summary presenter should preserve the operator timeline payload.' );
}

function znts_test_dashboard_summary_presenter_builds_restore_summary_rows_and_urls() {
	$presenter = new DashboardSummaryPresenter();

	$summary = $presenter->build_restore_summary(
		array(
			'id'    => 92,
			'label' => 'Blocked snapshot',
		),
		array(
			'can_execute' => false,
		),
		null,
		array(
			'timing' => array(),
		),
		null,
		array(),
		array(),
		array(
			'is_fresh' => false,
			'label'    => 'Expired 2h ago.',
		),
		array(),
		'zignites-sentinel-update-readiness',
		'http://example.test/wp-admin/admin.php?page=zignites-sentinel-event-logs&snapshot_id=92'
	);

	znts_assert_same( 'blocked', $summary['status'], 'Dashboard summary presenter should mark the restore summary blocked when checklist gates fail.' );
	znts_assert_same( 'fail', $summary['summary_rows'][0]['status'], 'Dashboard summary presenter should flag a missing baseline as fail.' );
	znts_assert_same( 'warning', $summary['summary_rows'][1]['status'], 'Dashboard summary presenter should flag a stale stage checkpoint as warning.' );
	znts_assert_same( 'fail', $summary['summary_rows'][2]['status'], 'Dashboard summary presenter should flag a missing plan checkpoint as fail.' );
	znts_assert_true( false !== strpos( $summary['detail_url'], 'snapshot_id=92' ), 'Dashboard summary presenter should include the selected snapshot ID in the detail URL.' );
	znts_assert_same( 'http://example.test/wp-admin/admin.php?page=zignites-sentinel-event-logs&snapshot_id=92', $summary['activity_url'], 'Dashboard summary presenter should preserve the snapshot activity URL.' );
}

function znts_test_dashboard_summary_presenter_builds_health_strip_with_empty_rows() {
	$presenter = new DashboardSummaryPresenter();

	$strip = $presenter->build_health_strip(
		array(
			'id'    => 121,
			'label' => 'Health snapshot',
		),
		array(),
		'zignites-sentinel-update-readiness'
	);

	znts_assert_same( 121, $strip['snapshot']['id'], 'Dashboard summary presenter should preserve the selected snapshot on the health strip payload.' );
	znts_assert_same( 0, count( $strip['rows'] ), 'Dashboard summary presenter should preserve empty health comparison rows.' );
	znts_assert_true( false !== strpos( $strip['detail_url'], 'snapshot_id=121' ), 'Dashboard summary presenter should include the selected snapshot ID in the health-strip detail URL.' );
}
