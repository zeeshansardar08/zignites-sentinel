<?php
/**
 * Focused tests for restore impact summary composition.
 */

require_once __DIR__ . '/bootstrap.php';

use Zignites\Sentinel\Admin\Admin;

class ZNTS_Testable_Restore_Impact_Admin extends Admin {
	public $fixture = array();

	public function __construct() {
		$this->status_presenter = new \Zignites\Sentinel\Admin\StatusPresenter();
	}

	public function build_impact_summary( array $snapshot ) {
		return $this->get_restore_impact_summary( $snapshot );
	}

	protected function get_snapshot_artifacts( $snapshot ) {
		return isset( $this->fixture['artifacts'] ) ? $this->fixture['artifacts'] : array();
	}

	protected function get_last_restore_plan( $snapshot ) {
		return isset( $this->fixture['restore_plan'] ) ? $this->fixture['restore_plan'] : array();
	}

	protected function get_snapshot_health_baseline( $snapshot ) {
		return array_key_exists( 'baseline', $this->fixture ) ? $this->fixture['baseline'] : null;
	}

	protected function get_restore_operator_checklist( $snapshot, $artifacts = null ) {
		return isset( $this->fixture['operator_checklist'] ) ? $this->fixture['operator_checklist'] : array();
	}

	protected function get_restore_resume_context( $snapshot ) {
		return isset( $this->fixture['resume_context'] ) ? $this->fixture['resume_context'] : array();
	}

	protected function get_last_restore_execution( $snapshot ) {
		return isset( $this->fixture['last_execution'] ) ? $this->fixture['last_execution'] : array();
	}

	protected function get_restore_stage_checkpoint( $snapshot ) {
		return isset( $this->fixture['stage_checkpoint'] ) ? $this->fixture['stage_checkpoint'] : array();
	}

	protected function get_restore_plan_checkpoint( $snapshot ) {
		return isset( $this->fixture['plan_checkpoint'] ) ? $this->fixture['plan_checkpoint'] : array();
	}

	protected function get_checkpoint_timing_summary( array $checkpoint ) {
		if ( isset( $checkpoint['timing'] ) && is_array( $checkpoint['timing'] ) ) {
			return $checkpoint['timing'];
		}

		return parent::get_checkpoint_timing_summary( $checkpoint );
	}
}

function znts_test_restore_impact_summary_reports_blocked_state_and_blockers() {
	$admin = new ZNTS_Testable_Restore_Impact_Admin();
	$admin->fixture = array(
		'restore_plan' => array(
			'status'              => 'blocked',
			'confirmation_phrase' => 'RESTORE SNAPSHOT 55',
			'summary'             => array(
				'create'    => 1,
				'replace'   => 3,
				'reuse'     => 2,
				'blocked'   => 1,
				'conflicts' => 4,
			),
		),
		'operator_checklist' => array(
			'can_execute' => false,
			'checks'      => array(
				array(
					'label'   => 'Baseline status',
					'status'  => 'fail',
					'message' => 'Capture a baseline before execution.',
				),
				array(
					'label'   => 'Stage gate',
					'status'  => 'warning',
					'message' => 'Refresh the stage checkpoint.',
				),
				array(
					'label'   => 'Plan gate',
					'status'  => 'pass',
					'message' => 'Plan is current.',
				),
			),
		),
		'resume_context' => array(),
		'last_execution' => array(),
		'stage_checkpoint' => array(
			'status' => 'caution',
			'timing' => array(
				'label' => 'Expired 1h ago.',
			),
		),
		'plan_checkpoint' => array(
			'status' => 'ready',
			'timing' => array(
				'label' => 'Expires in 8h.',
			),
		),
	);

	$snapshot = array(
		'id' => 55,
	);

	$summary = $admin->build_impact_summary( $snapshot );

	znts_assert_same( 'critical', $summary['status'], 'Blocked restore impact summaries should use the critical status.' );
	znts_assert_same( 'Restore blocked', $summary['title'], 'Blocked restore impact summaries should use the blocked title.' );
	znts_assert_true( false !== strpos( $summary['message'], 'blocked' ), 'Blocked restore impact summaries should explain that execution is blocked.' );
	znts_assert_same( '1 create, 3 replace, 2 unchanged', $summary['rows'][0]['value'], 'Impact summary should report create, replace, and reuse counts.' );
	znts_assert_same( '4 planned file conflicts', $summary['rows'][1]['value'], 'Impact summary should report the planned conflict count.' );
	znts_assert_same( 'No baseline captured yet', $summary['rows'][3]['value'], 'Impact summary should report when baseline is missing.' );
	znts_assert_true( false !== strpos( $summary['rows'][4]['value'], 'Expired 1h ago.' ), 'Impact summary should include stale stage checkpoint timing.' );
	znts_assert_true( false !== strpos( $summary['rows'][5]['value'], 'Expires in 8h.' ), 'Impact summary should include plan checkpoint timing.' );
	znts_assert_same( 'RESTORE SNAPSHOT 55', $summary['rows'][6]['value'], 'Impact summary should surface the restore confirmation phrase.' );
	znts_assert_same( 2, count( $summary['blockers'] ), 'Impact summary should include one row per failing or warning checklist gate.' );
	znts_assert_same( 'Baseline status', $summary['blockers'][0]['label'], 'Impact summary blockers should preserve checklist labels.' );
	znts_assert_same( 'Capture a baseline before execution.', $summary['blockers'][0]['message'], 'Impact summary blockers should preserve checklist messages.' );
	znts_assert_same( 'Blocked plan items', $summary['rows'][7]['label'], 'Impact summary should append blocked plan item counts when present.' );
}

function znts_test_restore_impact_summary_reports_resume_backup_reuse() {
	$admin = new ZNTS_Testable_Restore_Impact_Admin();
	$admin->fixture = array(
		'restore_plan' => array(
			'status'  => 'ready',
			'summary' => array(
				'create'    => 0,
				'replace'   => 1,
				'reuse'     => 5,
				'blocked'   => 0,
				'conflicts' => 0,
			),
		),
		'baseline' => array(
			'status'       => 'healthy',
			'generated_at' => '2025-01-03 12:00:00',
		),
		'operator_checklist' => array(
			'can_execute' => true,
			'checks'      => array(),
		),
		'resume_context' => array(
			'can_resume'            => true,
			'completed_item_count'  => 4,
			'entry_count'           => 9,
		),
		'last_execution' => array(
			'backup_root' => 'D:/uploads/zignites-sentinel/backups/run-90',
		),
		'stage_checkpoint' => array(
			'status' => 'ready',
			'timing' => array(
				'label' => 'Expires in 3h.',
			),
		),
		'plan_checkpoint' => array(
			'status' => 'ready',
			'timing' => array(
				'label' => 'Expires in 3h.',
			),
		),
	);

	$summary = $admin->build_impact_summary( array( 'id' => 90 ) );

	znts_assert_same( 'warning', $summary['status'], 'Replace-heavy restore impact summaries should use warning status.' );
	znts_assert_same( 'Review impact', $summary['title'], 'Replace-heavy restore impact summaries should use the review title.' );
	znts_assert_true( false !== strpos( $summary['rows'][2]['value'], 'Resume will reuse the existing backup root' ), 'Impact summary should report backup-root reuse when resume is available.' );
	znts_assert_true( false !== strpos( $summary['rows'][3]['value'], 'Healthy captured at 2025-01-03 12:00:00' ), 'Impact summary should report baseline capture details when present.' );
	znts_assert_same( 'Resume state', $summary['rows'][7]['label'], 'Impact summary should append resume-state details when resume is available.' );
	znts_assert_true( false !== strpos( $summary['rows'][7]['value'], '4 completed items already recorded across 9 journal entries' ), 'Impact summary should summarize resume progress.' );
	znts_assert_same( array(), $summary['blockers'], 'Impact summary should omit blockers when checklist gates pass.' );
}
