<?php
/**
 * Focused tests for Safe Update Window health checks.
 */

require_once __DIR__ . '/bootstrap.php';

use Zignites\Sentinel\Snapshots\RestoreHealthVerifier;

function znts_test_safe_update_window_health_warns_when_no_checks_enabled() {
	$verifier = new RestoreHealthVerifier();
	$result   = $verifier->verify_update_window(
		array(
			'check_homepage' => false,
			'check_admin'    => false,
			'custom_urls'    => array(),
		)
	);

	znts_assert_same( 'degraded', $result['status'], 'Safe Update Window health should warn when no endpoint checks are enabled.' );
	znts_assert_same( 'Health check configuration', $result['checks'][0]['label'], 'Safe Update Window health should explain the missing configuration.' );
}

function znts_test_safe_update_window_health_checks_custom_urls() {
	$GLOBALS['znts_test_http_response'] = array(
		'response' => array(
			'code' => 200,
		),
		'headers'  => array(
			'content-type' => 'text/html',
		),
		'body'     => '<html><body>OK</body></html>',
	);

	$verifier = new RestoreHealthVerifier();
	$result   = $verifier->verify_update_window(
		array(
			'check_homepage' => false,
			'check_admin'    => false,
			'custom_urls'    => array(
				'http://example.test/status',
			),
		)
	);

	unset( $GLOBALS['znts_test_http_response'] );

	znts_assert_same( 'healthy', $result['status'], 'Safe Update Window custom URL health should pass on a healthy response.' );
	znts_assert_same( 5, $result['summary']['pass'], 'Custom URL health should record all successful endpoint checks.' );
}
