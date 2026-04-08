<?php
/**
 * Focused tests for health comparison presenter payloads.
 */

require_once __DIR__ . '/bootstrap.php';

use Zignites\Sentinel\Admin\HealthComparisonPresenter;
use Zignites\Sentinel\Admin\StatusPresenter;

function znts_test_health_comparison_presenter_builds_delta_summary_in_fixed_order() {
	$presenter = new HealthComparisonPresenter( new StatusPresenter() );

	$delta = $presenter->build_delta_summary(
		array(
			'pass'    => 5,
			'warning' => 1,
			'fail'    => 0,
		),
		array(
			'pass'    => 4,
			'warning' => 2,
			'fail'    => 1,
		)
	);

	znts_assert_same( 'pass +1, warning -1, fail -1', $delta, 'Health comparison presenter should report summary deltas in a stable order.' );
}

function znts_test_health_comparison_presenter_builds_normalized_rows() {
	$presenter = new HealthComparisonPresenter( new StatusPresenter() );

	$row = $presenter->build_row(
		'Post-Restore',
		array(
			'status'       => 'degraded',
			'generated_at' => '2025-01-04 10:00:00',
			'summary'      => array(
				'pass'    => 6,
				'warning' => 2,
				'fail'    => 1,
			),
			'note'         => 'Admin probe returned a warning.',
		),
		array(
			'summary' => array(
				'pass'    => 7,
				'warning' => 1,
				'fail'    => 1,
			),
		)
	);

	znts_assert_same( 'warning', $row['status_pill'], 'Health comparison presenter should expose the degraded health pill.' );
	znts_assert_same( 'pass -1, warning +1', $row['delta'], 'Health comparison presenter should attach the compact delta string when baseline data exists.' );
	znts_assert_same( 1, $row['summary']['fail'], 'Health comparison presenter should normalize fail counts to integers.' );
}

function znts_test_health_comparison_presenter_builds_comparison_rows() {
	$presenter = new HealthComparisonPresenter( new StatusPresenter() );

	$rows = $presenter->build_comparison(
		array(
			'status'       => 'healthy',
			'generated_at' => '2025-01-05 09:00:00',
			'summary'      => array(
				'pass'    => 7,
				'warning' => 0,
				'fail'    => 0,
			),
		),
		array(
			'health_verification' => array(
				'status'       => 'degraded',
				'generated_at' => '2025-01-05 10:00:00',
				'summary'      => array(
					'pass'    => 6,
					'warning' => 1,
					'fail'    => 0,
				),
			),
		),
		array(
			'health_verification' => array(
				'status'       => 'healthy',
				'generated_at' => '2025-01-05 11:00:00',
				'summary'      => array(
					'pass'    => 7,
					'warning' => 0,
					'fail'    => 0,
				),
			),
		)
	);

	znts_assert_same( 3, count( $rows ), 'Health comparison presenter should build baseline, post-restore, and post-rollback rows when all inputs exist.' );
	znts_assert_same( 'Baseline', $rows[0]['label'], 'Health comparison presenter should start with the baseline row.' );
	znts_assert_same( 'No change', $rows[2]['delta'], 'Health comparison presenter should report no change when rollback summary matches baseline.' );
}
