<?php
/**
 * Focused tests for restore impact summary state building.
 */

require_once __DIR__ . '/bootstrap.php';

use Zignites\Sentinel\Admin\RestoreImpactSummaryStateBuilder;

function znts_test_restore_impact_summary_state_builder_normalizes_inputs() {
	$builder = new RestoreImpactSummaryStateBuilder();
	$state   = $builder->build_summary_state(
		array(
			'id' => 55,
		),
		null,
		null,
		array(
			'can_execute' => false,
		),
		null,
		'Backup summary',
		'Stage summary',
		'Plan summary'
	);

	znts_assert_same( 55, $state['snapshot_id'], 'Restore impact summary state builder should preserve the selected snapshot ID.' );
	znts_assert_same( array(), $state['plan'], 'Restore impact summary state builder should normalize missing plan payloads to an empty array.' );
	znts_assert_same( array(), $state['baseline'], 'Restore impact summary state builder should normalize missing baseline payloads to an empty array.' );
	znts_assert_same( false, $state['checklist']['can_execute'], 'Restore impact summary state builder should preserve the operator checklist payload.' );
	znts_assert_same( array(), $state['resume_context'], 'Restore impact summary state builder should normalize missing resume context to an empty array.' );
	znts_assert_same( 'Backup summary', $state['backup_summary'], 'Restore impact summary state builder should preserve the supplied backup summary.' );
	znts_assert_same( 'Stage summary', $state['stage_summary'], 'Restore impact summary state builder should preserve the supplied stage summary.' );
	znts_assert_same( 'Plan summary', $state['plan_summary'], 'Restore impact summary state builder should preserve the supplied plan summary.' );
}
