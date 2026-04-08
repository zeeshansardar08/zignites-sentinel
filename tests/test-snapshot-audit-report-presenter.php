<?php
/**
 * Focused tests for snapshot audit report presenter payloads.
 */

require_once __DIR__ . '/bootstrap.php';

use Zignites\Sentinel\Admin\AuditReportVerifier;
use Zignites\Sentinel\Admin\SnapshotAuditReportPresenter;

function znts_test_snapshot_audit_report_presenter_builds_enveloped_payload() {
	$presenter = new SnapshotAuditReportPresenter( new AuditReportVerifier() );

	$payload = $presenter->build_report(
		array(
			'id'    => 71,
			'label' => 'Snapshot 71',
		),
		array(
			'plugin_delta' => 2,
		),
		array(
			array( 'artifact_type' => 'package' ),
		),
		array(
			array( 'path' => 'wp-content/plugins/example' ),
		),
		array(
			'status' => 'healthy',
		),
		array(
			array( 'label' => 'Baseline' ),
		),
		array( 'status' => 'ready' ),
		array( 'status' => 'ready' ),
		array( 'status' => 'ready' ),
		array( 'status' => 'ready' ),
		array( 'status' => 'pass' ),
		array( 'status' => 'pass' ),
		array( 'status' => 'ready' ),
		array( 'status' => 'ready' ),
		array( 'status' => 'partial' ),
		array( 'can_execute' => true ),
		array(
			array( 'message' => 'Snapshot audit report downloaded.' ),
		)
	);

	znts_assert_same( 71, $payload['report']['snapshot']['id'], 'Snapshot audit report presenter should preserve the snapshot payload.' );
	znts_assert_same( '1.29.0-test', $payload['report']['plugin_version'], 'Snapshot audit report presenter should stamp the plugin version into the report payload.' );
	znts_assert_same( 'healthy', $payload['report']['health']['baseline']['status'], 'Snapshot audit report presenter should group baseline health under the health section.' );
	znts_assert_same( 'ready', $payload['report']['checkpoints']['stage']['status'], 'Snapshot audit report presenter should group stage checkpoint payloads under checkpoints.' );
	znts_assert_same( 'partial', $payload['report']['checkpoints']['execution']['status'], 'Snapshot audit report presenter should group execution checkpoint payloads under checkpoints.' );
	znts_assert_true( ! empty( $payload['integrity']['payload_hash'] ), 'Snapshot audit report presenter should include an integrity payload hash.' );
	znts_assert_same( 'sha256', $payload['integrity']['algorithm'], 'Snapshot audit report presenter should use the supported integrity algorithm.' );
}
