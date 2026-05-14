<?php
/**
 * Focused tests for deterministic update risk summaries.
 */

require_once __DIR__ . '/bootstrap.php';

use Zignites\Sentinel\Platform\UpdateRiskSummaryModel;

function znts_test_update_risk_summary_model_flags_major_and_source_risk() {
	$model = new UpdateRiskSummaryModel();

	$summary = $model->build_summary(
		array(
			array(
				'key'             => 'plugin:example/example.php',
				'type'            => 'plugin',
				'slug'            => 'example/example.php',
				'label'           => 'Example Plugin',
				'current_version' => '1.9.0',
				'new_version'     => '2.0.0',
			),
			array(
				'key'             => 'theme:example-theme',
				'type'            => 'theme',
				'slug'            => 'example-theme',
				'label'           => 'Example Theme',
				'current_version' => '3.0.0',
				'new_version'     => '3.1.0',
			),
		),
		array(
			'status'  => 'warning',
			'summary' => array(
				'pass'    => 1,
				'warning' => 1,
				'fail'    => 0,
			),
		),
		array(
			'scope' => 'Plugin and theme update window',
		),
		array(
			'plugin:example/example.php' => 'Major release with breaking template changes.',
		)
	);

	znts_assert_same( '1.0', $summary['schema_version'], 'Update risk summary should expose a stable schema version.' );
	znts_assert_same( 'high', $summary['risk_level'], 'Major updates or breaking changelog language should raise high risk.' );
	znts_assert_same( 'Plugin and theme update window: High', $summary['title'], 'Update risk summary should include the supplied scope label.' );
	znts_assert_same( 2, $summary['target_count'], 'Update risk summary should count update targets.' );
	znts_assert_same( 1, $summary['type_counts']['plugin'], 'Update risk summary should count plugin targets.' );
	znts_assert_same( 1, count( $summary['version_risk']['major_updates'] ), 'Update risk summary should detect major version jumps.' );
	znts_assert_same( true, $summary['source_risk']['needs_review'], 'Update risk summary should preserve source validation review state.' );
	znts_assert_same( 1, count( $summary['changelog']['breaking_terms'] ), 'Update risk summary should detect breaking changelog terms.' );
	znts_assert_same( false, $summary['auto_update_allowed'], 'Update risk summary should never allow auto updates.' );
	znts_assert_same( false, $summary['auto_rollback_allowed'], 'Update risk summary should never allow auto rollbacks.' );
}

function znts_test_update_risk_summary_model_flags_core_boundary() {
	$model = new UpdateRiskSummaryModel();

	$summary = $model->build_summary(
		array(
			array(
				'key'             => 'core:wordpress',
				'type'            => 'core',
				'label'           => 'WordPress Core',
				'current_version' => '6.8.0',
				'new_version'     => '6.8.1',
			),
		)
	);

	znts_assert_same( 'high', $summary['risk_level'], 'Core update context should be high risk because Sentinel does not roll back core.' );
	znts_assert_same( 1, $summary['type_counts']['core'], 'Update risk summary should count core updates.' );
	znts_assert_same( 'core_boundary', $summary['findings'][0]['key'], 'Update risk summary should include a core rollback boundary finding.' );
}

function znts_test_update_risk_summary_model_keeps_low_risk_single_minor_update() {
	$model = new UpdateRiskSummaryModel();

	$summary = $model->build_summary(
		array(
			array(
				'key'             => 'plugin:small/small.php',
				'type'            => 'plugin',
				'label'           => 'Small Plugin',
				'current_version' => '1.0.0',
				'new_version'     => '1.0.1',
			),
		),
		array(
			'status'  => 'pass',
			'summary' => array(
				'pass' => 1,
			),
		),
		array(),
		array(
			'plugin:small/small.php' => 'Bug fix release.',
		)
	);

	znts_assert_same( 'low', $summary['risk_level'], 'Single minor update with passing validation and changelog context should remain low risk.' );
	znts_assert_same( 0, count( $summary['findings'] ), 'Low-risk update summary should not invent findings.' );
	znts_assert_same( true, $summary['ai_assistive_only'], 'Update risk summary should keep AI explicitly assistive only.' );
}
