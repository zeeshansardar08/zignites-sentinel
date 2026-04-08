<?php
/**
 * Read-only presentation helper for snapshot audit report payloads.
 *
 * @package ZignitesSentinel
 */

namespace Zignites\Sentinel\Admin;

defined( 'ABSPATH' ) || exit;

class SnapshotAuditReportPresenter {

	/**
	 * Audit report verifier.
	 *
	 * @var AuditReportVerifier
	 */
	protected $audit_report_verifier;

	/**
	 * Constructor.
	 *
	 * @param AuditReportVerifier|null $audit_report_verifier Optional verifier instance.
	 */
	public function __construct( AuditReportVerifier $audit_report_verifier = null ) {
		$this->audit_report_verifier = $audit_report_verifier ? $audit_report_verifier : new AuditReportVerifier();
	}

	/**
	 * Build the structured snapshot audit report payload and integrity envelope.
	 *
	 * @param array $snapshot           Snapshot detail.
	 * @param array $comparison         Snapshot comparison payload.
	 * @param array $artifacts          Snapshot artifacts.
	 * @param array $artifact_diff      Artifact diff payload.
	 * @param array $baseline           Baseline payload.
	 * @param array $health_comparison  Health comparison rows.
	 * @param array $restore_check      Restore readiness payload.
	 * @param array $restore_dry_run    Restore dry-run payload.
	 * @param array $restore_stage      Restore stage payload.
	 * @param array $restore_plan       Restore plan payload.
	 * @param array $restore_execution  Restore execution payload.
	 * @param array $restore_rollback   Restore rollback payload.
	 * @param array $stage_checkpoint   Stage checkpoint payload.
	 * @param array $plan_checkpoint    Plan checkpoint payload.
	 * @param array $execution_checkpoint Execution checkpoint payload.
	 * @param array $operator_checklist Operator checklist payload.
	 * @param array $activity           Snapshot activity payload.
	 * @return array
	 */
	public function build_report( array $snapshot, array $comparison, array $artifacts, array $artifact_diff, array $baseline, array $health_comparison, array $restore_check, array $restore_dry_run, array $restore_stage, array $restore_plan, array $restore_execution, array $restore_rollback, array $stage_checkpoint, array $plan_checkpoint, array $execution_checkpoint, array $operator_checklist, array $activity ) {
		$payload = array(
			'generated_at'    => current_time( 'mysql', true ),
			'plugin_version'  => ZNTS_VERSION,
			'snapshot'        => $snapshot,
			'comparison'      => $comparison,
			'artifacts'       => $artifacts,
			'artifact_diff'   => $artifact_diff,
			'health'          => array(
				'baseline'   => $baseline,
				'comparison' => $health_comparison,
			),
			'readiness'       => array(
				'restore_check'     => $restore_check,
				'restore_dry_run'   => $restore_dry_run,
				'restore_stage'     => $restore_stage,
				'restore_plan'      => $restore_plan,
				'restore_execution' => $restore_execution,
				'restore_rollback'  => $restore_rollback,
			),
			'checkpoints'     => array(
				'stage'     => $stage_checkpoint,
				'plan'      => $plan_checkpoint,
				'execution' => $execution_checkpoint,
			),
			'operator_checklist' => $operator_checklist,
			'activity'           => $activity,
		);

		return array(
			'report'    => $payload,
			'integrity' => $this->audit_report_verifier->build_integrity( $payload ),
		);
	}
}
