<?php
/**
 * Focused tests for health comparison state building.
 */

require_once __DIR__ . '/bootstrap.php';

use Zignites\Sentinel\Admin\HealthComparisonStateBuilder;

function znts_test_health_comparison_state_builder_normalizes_inputs() {
	$builder = new HealthComparisonStateBuilder();
	$state   = $builder->build_comparison_state(
		array(
			'id' => 120,
		),
		null,
		null,
		array(
			'health_verification' => array(
				'status' => 'healthy',
			),
		)
	);

	znts_assert_same( null, $state['baseline'], 'Health comparison state builder should preserve a missing baseline as null.' );
	znts_assert_same( array(), $state['execution'], 'Health comparison state builder should normalize missing execution payloads to an empty array.' );
	znts_assert_same( 'healthy', $state['rollback']['health_verification']['status'], 'Health comparison state builder should preserve the rollback payload.' );
}
