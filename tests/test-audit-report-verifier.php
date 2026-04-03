<?php
/**
 * Focused tests for snapshot audit report verification.
 */

require_once __DIR__ . '/bootstrap.php';

use Zignites\Sentinel\Admin\AuditReportVerifier;

function znts_test_audit_report_verifier_accepts_matching_payload() {
	$verifier = new AuditReportVerifier();
	$report   = array(
		'snapshot' => array(
			'id'    => 71,
			'label' => 'Snapshot 71',
		),
		'status' => 'ready',
	);
	$payload  = array(
		'report'    => $report,
		'integrity' => $verifier->build_integrity( $report ),
	);
	$result   = $verifier->verify_payload( wp_json_encode( $payload ), 71 );

	znts_assert_same( 'ready', $result['status'], 'Matching audit report payloads should verify successfully.' );
	znts_assert_same( 5, $result['summary']['pass'], 'Matching audit report payloads should pass all verification checks.' );
}

function znts_test_audit_report_verifier_rejects_snapshot_mismatch() {
	$verifier = new AuditReportVerifier();
	$report   = array(
		'snapshot' => array(
			'id'    => 72,
			'label' => 'Snapshot 72',
		),
	);
	$payload  = array(
		'report'    => $report,
		'integrity' => $verifier->build_integrity( $report ),
	);
	$result   = $verifier->verify_payload( wp_json_encode( $payload ), 99 );

	znts_assert_same( 'blocked', $result['status'], 'Snapshot mismatches should block audit verification.' );
}

function znts_test_audit_report_verifier_rejects_tampered_integrity() {
	$verifier = new AuditReportVerifier();
	$report   = array(
		'snapshot' => array(
			'id'    => 73,
			'label' => 'Snapshot 73',
		),
	);
	$payload  = array(
		'report'    => $report,
		'integrity' => array(
			'algorithm'      => 'sha256',
			'payload_hash'   => 'tampered-hash',
			'site_signature' => 'tampered-signature',
		),
	);
	$result   = $verifier->verify_payload( wp_json_encode( $payload ), 73 );

	znts_assert_same( 'blocked', $result['status'], 'Tampered integrity should block audit verification.' );
	znts_assert_true( $result['summary']['fail'] >= 2, 'Tampered integrity should fail both hash and signature checks.' );
}
