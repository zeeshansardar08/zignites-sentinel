<?php
/**
 * Focused tests for restore operator checklist state building.
 */

require_once __DIR__ . '/bootstrap.php';

use Zignites\Sentinel\Admin\RestoreOperatorChecklistStateBuilder;

function znts_test_restore_operator_checklist_state_builder_normalizes_evaluator_context() {
	$builder = new RestoreOperatorChecklistStateBuilder();
	$context = $builder->build_context(
		null,
		array(
			'status' => 'ready',
		),
		array(),
		array(
			'status' => 'stale',
		),
		null,
		array(
			'restore_checkpoint_max_age_hours' => '12',
		)
	);

	znts_assert_same( false, $context['baseline_present'], 'Restore operator checklist state builder should treat missing baseline payloads as absent.' );
	znts_assert_same( true, $context['stage_result_ready'], 'Restore operator checklist state builder should mark non-empty stage results as ready.' );
	znts_assert_same( false, $context['plan_result_ready'], 'Restore operator checklist state builder should treat empty plan results as not ready.' );
	znts_assert_same( true, $context['stage_checkpoint_exists'], 'Restore operator checklist state builder should mark non-empty stage checkpoints as existing.' );
	znts_assert_same( false, $context['plan_checkpoint_exists'], 'Restore operator checklist state builder should treat empty plan checkpoints as missing.' );
	znts_assert_same( 12, $context['max_age_hours'], 'Restore operator checklist state builder should normalize checkpoint age settings to integers.' );
}
