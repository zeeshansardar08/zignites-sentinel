<?php
/**
 * Focused tests for restore operator checklist evaluation.
 */

require_once __DIR__ . '/bootstrap.php';

use Zignites\Sentinel\Admin\RestoreOperatorChecklistEvaluator;

function znts_test_restore_operator_checklist_ready_when_all_gates_pass() {
	$evaluator = new RestoreOperatorChecklistEvaluator();
	$result    = $evaluator->evaluate(
		array(
			'baseline_present'        => true,
			'stage_result_ready'      => true,
			'plan_result_ready'       => true,
			'stage_checkpoint_exists' => true,
			'plan_checkpoint_exists'  => true,
			'max_age_hours'           => 24,
		)
	);

	znts_assert_same( 'ready', $result['status'], 'Checklist should be ready when all gates pass.' );
	znts_assert_true( ! empty( $result['can_execute'] ), 'Checklist should allow execution when all gates pass.' );
	znts_assert_same( 3, count( $result['checks'] ), 'Checklist should include the expected three gates.' );
}

function znts_test_restore_operator_checklist_fails_without_baseline() {
	$evaluator = new RestoreOperatorChecklistEvaluator();
	$result    = $evaluator->evaluate(
		array(
			'baseline_present'        => false,
			'stage_result_ready'      => true,
			'plan_result_ready'       => true,
			'stage_checkpoint_exists' => true,
			'plan_checkpoint_exists'  => true,
			'max_age_hours'           => 24,
		)
	);

	znts_assert_same( 'blocked', $result['status'], 'Checklist should block when baseline is missing.' );
	znts_assert_same( 'fail', $result['checks'][0]['status'], 'Baseline gate should fail when no baseline exists.' );
}

function znts_test_restore_operator_checklist_uses_stale_messages_when_checkpoints_exist() {
	$evaluator = new RestoreOperatorChecklistEvaluator();
	$result    = $evaluator->evaluate(
		array(
			'baseline_present'        => true,
			'stage_result_ready'      => false,
			'plan_result_ready'       => false,
			'stage_checkpoint_exists' => true,
			'plan_checkpoint_exists'  => true,
			'max_age_hours'           => 12,
		)
	);

	znts_assert_same( 'blocked', $result['status'], 'Checklist should block when stale checkpoints are not currently reusable.' );
	znts_assert_true( false !== strpos( $result['checks'][1]['message'], 'stale' ), 'Stage gate should explain stale checkpoint reuse failure.' );
	znts_assert_true( false !== strpos( $result['checks'][2]['message'], 'stale' ), 'Plan gate should explain stale checkpoint reuse failure.' );
}

function znts_test_restore_operator_checklist_uses_missing_messages_without_checkpoints() {
	$evaluator = new RestoreOperatorChecklistEvaluator();
	$result    = $evaluator->evaluate(
		array(
			'baseline_present'        => true,
			'stage_result_ready'      => false,
			'plan_result_ready'       => false,
			'stage_checkpoint_exists' => false,
			'plan_checkpoint_exists'  => false,
			'max_age_hours'           => 24,
		)
	);

	znts_assert_true( false !== strpos( $result['checks'][1]['message'], 'Run staged restore validation' ), 'Stage gate should direct the operator to create the missing checkpoint.' );
	znts_assert_true( false !== strpos( $result['checks'][2]['message'], 'Build a restore plan' ), 'Plan gate should direct the operator to create the missing checkpoint.' );
}
