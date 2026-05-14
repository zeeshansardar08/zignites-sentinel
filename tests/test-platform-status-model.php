<?php
/**
 * Focused tests for platform status payload foundations.
 */

require_once __DIR__ . '/bootstrap.php';

use Zignites\Sentinel\Platform\OutboundSyncBoundary;
use Zignites\Sentinel\Platform\SiteStatusModel;

function znts_test_site_status_model_builds_future_dashboard_payload() {
	$model = new SiteStatusModel();

	$payload = $model->build_payload(
		array(
			'recent_snapshots' => array(
				array(
					'id'         => 51,
					'label'      => 'Before WooCommerce updates',
					'created_at' => '2026-05-14 10:00:00',
				),
			),
			'snapshot_status_index' => array(
				51 => array(
					'status'        => 'stable',
					'restore_ready' => true,
				),
			),
			'site_status_card' => array(
				'status'             => 'stable',
				'status_label'       => 'Stable',
				'recommended_action' => 'Keep checkpoint evidence fresh before updates.',
			),
			'health_score' => array(
				'score'   => 96,
				'details' => array(
					'open_conflicts' => array(
						'warning'  => 1,
						'critical' => 0,
					),
				),
			),
			'system_health' => array(
				'status'       => 'safe',
				'status_label' => 'Safe',
			),
		),
		array(
			'site_url'         => 'http://example.test',
			'wordpress'        => '6.8.1',
			'php'              => '8.2.0',
			'plugin_version'   => '1.33.0',
			'db_version'       => '1.4.0',
			'artifact_storage' => array(
				'status'  => 'pass',
				'label'   => 'Guarded',
				'message' => 'Artifact path is blocked.',
			),
		),
		array(
			'snapshot_id'  => 51,
			'status'       => 'healthy',
			'confirmed'    => true,
			'checked_at'   => '2026-05-14 10:30:00',
			'completed_at' => '2026-05-14 10:35:00',
		)
	);

	znts_assert_same( '1.0', $payload['schema_version'], 'Status payload should expose a stable schema version.' );
	znts_assert_same( '1.33.0', $payload['site']['plugin_version'], 'Status payload should include plugin identity.' );
	znts_assert_same( 'stable', $payload['status']['state'], 'Status payload should preserve the operator-facing site state.' );
	znts_assert_same( 51, $payload['checkpoint']['id'], 'Status payload should expose the latest checkpoint ID.' );
	znts_assert_same( true, $payload['checkpoint']['restore_ready'], 'Status payload should expose restore readiness.' );
	znts_assert_same( true, $payload['last_update_window']['confirmed'], 'Status payload should expose Safe Update Window confirmation.' );
	znts_assert_same( 'pass', $payload['storage']['status'], 'Status payload should expose artifact storage state.' );
	znts_assert_same( 96, $payload['health']['score'], 'Status payload should expose the health score.' );
	znts_assert_same( array(), $payload['warnings'], 'Stable status payload should not create warnings.' );
}

function znts_test_site_status_model_collects_compact_warnings() {
	$model = new SiteStatusModel();

	$payload = $model->build_payload(
		array(
			'site_status_card' => array(
				'status'             => 'at_risk',
				'recommended_action' => 'Review checkpoint evidence before updates.',
			),
			'health_score' => array(
				'details' => array(
					'open_conflicts' => array(
						'critical' => 2,
					),
				),
			),
		),
		array(
			'artifact_storage' => array(
				'status'  => 'warning',
				'message' => 'Artifact exposure check was inconclusive.',
			),
		),
		array(
			'status' => 'degraded',
		)
	);

	znts_assert_same( 4, count( $payload['warnings'] ), 'Status payload should collect site, storage, health, and update-window warnings.' );
	znts_assert_same( 'site_status', $payload['warnings'][0]['key'], 'Status warning payloads should use stable keys.' );
	znts_assert_same( 'safe_update_window', $payload['warnings'][3]['key'], 'Safe Update Window warnings should be represented for future dashboards.' );
}

function znts_test_outbound_sync_boundary_stays_disabled_by_default() {
	$boundary = new OutboundSyncBoundary();
	$state    = $boundary->build_state( array() );
	$result   = $boundary->sync_status( array( 'schema_version' => '1.0' ), array() );

	znts_assert_same( false, $state['enabled'], 'Outbound sync should be disabled by default.' );
	znts_assert_same( false, $state['configured'], 'Outbound sync should not be configured by default.' );
	znts_assert_same( true, $result['skipped'], 'Disabled outbound sync should skip delivery.' );
	znts_assert_same( false, $result['sent'], 'Disabled outbound sync should not send anything.' );
}

function znts_test_outbound_sync_boundary_rejects_non_https_endpoint() {
	$boundary = new OutboundSyncBoundary();
	$settings = $boundary->normalize_settings(
		array(
			'enabled'      => 1,
			'endpoint_url' => 'http://platform.example.test/sync',
		)
	);

	znts_assert_same( 1, $settings['enabled'], 'Outbound sync settings should preserve enabled state for future use.' );
	znts_assert_same( '', $settings['endpoint_url'], 'Outbound sync should reject non-HTTPS endpoints.' );
}
