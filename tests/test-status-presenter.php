<?php
/**
 * Focused tests for shared admin status presentation.
 */

require_once __DIR__ . '/bootstrap.php';

use Zignites\Sentinel\Admin\StatusPresenter;

function znts_test_status_presenter_formats_readiness_health_and_severity_states() {
	$presenter = new StatusPresenter();

	$readiness = $presenter->present_readiness( 'caution' );
	$health    = $presenter->present_health( 'unhealthy' );
	$severity  = $presenter->present_severity( 'error' );

	znts_assert_same( 'warning', $readiness['pill'], 'Status presenter should map caution readiness states to the warning pill.' );
	znts_assert_same( 'Caution', $readiness['label'], 'Status presenter should format readable readiness labels.' );
	znts_assert_same( 'critical', $health['pill'], 'Status presenter should map unhealthy health states to the critical pill.' );
	znts_assert_same( 'Unhealthy', $health['label'], 'Status presenter should format readable health labels.' );
	znts_assert_same( 'error', $severity['pill'], 'Status presenter should preserve the native severity pill for error events.' );
	znts_assert_same( 'Error', $severity['label'], 'Status presenter should format readable severity labels.' );
}
