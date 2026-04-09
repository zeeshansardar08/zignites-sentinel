<?php
/**
 * Minimal test runner for local Sentinel checks.
 */

require_once __DIR__ . '/test-snapshot-status-resolver.php';
require_once __DIR__ . '/test-restore-execution-checkpoint-store.php';
require_once __DIR__ . '/test-restore-resume-state.php';
require_once __DIR__ . '/test-settings-portability.php';
require_once __DIR__ . '/test-audit-report-verifier.php';
require_once __DIR__ . '/test-restore-operator-checklist-evaluator.php';
require_once __DIR__ . '/test-restore-operator-checklist-state-builder.php';
require_once __DIR__ . '/test-log-repository-filters.php';
require_once __DIR__ . '/test-event-log-export-row.php';
require_once __DIR__ . '/test-event-log-export-filename.php';
require_once __DIR__ . '/test-event-log-presenter.php';
require_once __DIR__ . '/test-dashboard-summary-presenter.php';
require_once __DIR__ . '/test-health-comparison-state-builder.php';
require_once __DIR__ . '/test-health-comparison-presenter.php';
require_once __DIR__ . '/test-restore-checkpoint-presenter.php';
require_once __DIR__ . '/test-restore-impact-summary-presenter.php';
require_once __DIR__ . '/test-restore-impact-summary-state-builder.php';
require_once __DIR__ . '/test-snapshot-audit-report-presenter.php';
require_once __DIR__ . '/test-snapshot-audit-report.php';
require_once __DIR__ . '/test-snapshot-list-state-builder.php';
require_once __DIR__ . '/test-dashboard-summary-state-builder.php';
require_once __DIR__ . '/test-update-readiness-state-builder.php';
require_once __DIR__ . '/test-snapshot-summary-state-builder.php';
require_once __DIR__ . '/test-snapshot-summary-presenter.php';
require_once __DIR__ . '/test-snapshot-summary-export.php';
require_once __DIR__ . '/test-restore-impact-summary.php';
require_once __DIR__ . '/test-dashboard-summary.php';
require_once __DIR__ . '/test-health-comparison.php';
require_once __DIR__ . '/test-admin-smoke-runner.php';
require_once __DIR__ . '/test-resume-admin-presentation.php';
require_once __DIR__ . '/test-status-presenter.php';

$tests = array_filter(
	get_defined_functions()['user'],
	function ( $function_name ) {
		return 0 === strpos( $function_name, 'znts_test_' );
	}
);

$failures = array();

foreach ( $tests as $test_name ) {
	try {
		$test_name();
		echo '[PASS] ' . $test_name . PHP_EOL;
	} catch ( Throwable $throwable ) {
		$failures[] = array(
			'test'    => $test_name,
			'message' => $throwable->getMessage(),
		);
		echo '[FAIL] ' . $test_name . ': ' . $throwable->getMessage() . PHP_EOL;
	}
}

if ( ! empty( $failures ) ) {
	exit( 1 );
}

echo 'All tests passed.' . PHP_EOL;
