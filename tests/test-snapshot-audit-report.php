<?php
/**
 * Focused tests for admin-level snapshot audit report generation.
 */

require_once __DIR__ . '/bootstrap.php';

use Zignites\Sentinel\Admin\Admin;
use Zignites\Sentinel\Admin\AuditReportVerifier;
use Zignites\Sentinel\Admin\SnapshotAuditReportPresenter;

class ZNTS_Testable_Snapshot_Audit_Report_Admin extends Admin {
	public $fixture = array();

	public function __construct() {
		$this->audit_report_verifier = new AuditReportVerifier();
		$this->snapshot_audit_report_presenter = new SnapshotAuditReportPresenter( $this->audit_report_verifier );
	}

	public function build_audit_report( array $snapshot ) {
		return $this->build_snapshot_audit_report( $snapshot );
	}

	public function verify_audit_report_payload( $payload_text, $snapshot_id ) {
		return $this->verify_snapshot_audit_report_payload( $payload_text, $snapshot_id );
	}

	protected function get_snapshot_comparison( $snapshot ) {
		return array_key_exists( 'comparison', $this->fixture ) ? $this->fixture['comparison'] : array();
	}

	protected function get_snapshot_artifacts( $snapshot ) {
		return array_key_exists( 'artifacts', $this->fixture ) ? $this->fixture['artifacts'] : array();
	}

	protected function get_artifact_diff( $snapshot ) {
		return array_key_exists( 'artifact_diff', $this->fixture ) ? $this->fixture['artifact_diff'] : array();
	}

	protected function get_snapshot_health_baseline( $snapshot ) {
		return array_key_exists( 'baseline', $this->fixture ) ? $this->fixture['baseline'] : array();
	}

	protected function get_snapshot_health_comparison( $snapshot ) {
		return array_key_exists( 'health_comparison', $this->fixture ) ? $this->fixture['health_comparison'] : array();
	}

	protected function get_last_restore_check( $snapshot ) {
		return array_key_exists( 'restore_check', $this->fixture ) ? $this->fixture['restore_check'] : array();
	}

	protected function get_last_restore_dry_run( $snapshot ) {
		return array_key_exists( 'restore_dry_run', $this->fixture ) ? $this->fixture['restore_dry_run'] : array();
	}

	protected function get_last_restore_stage( $snapshot ) {
		return array_key_exists( 'restore_stage', $this->fixture ) ? $this->fixture['restore_stage'] : array();
	}

	protected function get_last_restore_plan( $snapshot ) {
		return array_key_exists( 'restore_plan', $this->fixture ) ? $this->fixture['restore_plan'] : array();
	}

	protected function get_last_restore_execution( $snapshot ) {
		return array_key_exists( 'restore_execution', $this->fixture ) ? $this->fixture['restore_execution'] : array();
	}

	protected function get_last_restore_rollback( $snapshot ) {
		return array_key_exists( 'restore_rollback', $this->fixture ) ? $this->fixture['restore_rollback'] : array();
	}

	protected function get_restore_stage_checkpoint( $snapshot ) {
		return array_key_exists( 'stage_checkpoint', $this->fixture ) ? $this->fixture['stage_checkpoint'] : array();
	}

	protected function get_restore_plan_checkpoint( $snapshot ) {
		return array_key_exists( 'plan_checkpoint', $this->fixture ) ? $this->fixture['plan_checkpoint'] : array();
	}

	protected function get_restore_execution_checkpoint( $snapshot ) {
		return array_key_exists( 'execution_checkpoint', $this->fixture ) ? $this->fixture['execution_checkpoint'] : array();
	}

	protected function get_restore_operator_checklist( $snapshot, $artifacts = null ) {
		return array_key_exists( 'operator_checklist', $this->fixture ) ? $this->fixture['operator_checklist'] : array();
	}

	protected function get_snapshot_activity( $snapshot ) {
		return array_key_exists( 'activity', $this->fixture ) ? $this->fixture['activity'] : array();
	}
}

function znts_test_snapshot_audit_report_builds_verifyable_payload() {
	$admin = new ZNTS_Testable_Snapshot_Audit_Report_Admin();
	$admin->fixture = array(
		'comparison' => array(
			'plugin_delta' => 2,
		),
		'artifacts' => array(
			array( 'artifact_type' => 'package' ),
		),
		'artifact_diff' => array(
			array( 'path' => 'wp-content/plugins/example' ),
		),
		'baseline' => array(
			'status' => 'healthy',
		),
		'health_comparison' => array(
			array( 'label' => 'Baseline' ),
		),
		'restore_check' => array( 'status' => 'ready' ),
		'restore_dry_run' => array( 'status' => 'ready' ),
		'restore_stage' => array( 'status' => 'ready' ),
		'restore_plan' => array( 'status' => 'ready' ),
		'restore_execution' => array( 'status' => 'pass' ),
		'restore_rollback' => array( 'status' => 'pass' ),
		'stage_checkpoint' => array( 'status' => 'ready' ),
		'plan_checkpoint' => array( 'status' => 'ready' ),
		'execution_checkpoint' => array( 'status' => 'partial' ),
		'operator_checklist' => array( 'can_execute' => true ),
		'activity' => array(
			array( 'message' => 'Snapshot audit report downloaded.' ),
		),
	);

	$payload = $admin->build_audit_report(
		array(
			'id'    => 81,
			'label' => 'Snapshot 81',
		)
	);

	$verification = $admin->verify_audit_report_payload( wp_json_encode( $payload ), 81 );

	znts_assert_same( 81, $payload['report']['snapshot']['id'], 'Admin audit report generation should preserve the selected snapshot ID.' );
	znts_assert_same( 'Snapshot audit report downloaded.', $payload['report']['activity'][0]['message'], 'Admin audit report generation should include snapshot activity payloads.' );
	znts_assert_same( 'ready', $verification['status'], 'Admin audit report generation should produce a payload that verifies successfully for the matching snapshot.' );
}
