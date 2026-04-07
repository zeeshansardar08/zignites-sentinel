<?php
/**
 * Live authenticated admin smoke runner for Sentinel wp-admin surfaces.
 *
 * Usage:
 * php tests/smoke-admin-live.php --base-url=http://example.test/wp-admin/ --cookie="wordpress_logged_in_...=..."
 * php tests/smoke-admin-live.php --config=tests/admin-smoke-config.sample.php
 */

require_once __DIR__ . '/class-admin-smoke-runner.php';

if ( 'cli' !== PHP_SAPI ) {
	fwrite( STDERR, "This script must be run from the command line.\n" );
	exit( 1 );
}

$options = getopt( '', array( 'base-url::', 'cookie::', 'config::', 'timeout::' ) );
$runner  = new ZNTS_Admin_Smoke_Runner();
$config  = array();

if ( ! empty( $options['config'] ) ) {
	$config = $runner->load_config( (string) $options['config'] );
}

$base_url = isset( $options['base-url'] ) ? (string) $options['base-url'] : '';
$cookie   = isset( $options['cookie'] ) ? (string) $options['cookie'] : '';
$timeout  = isset( $options['timeout'] ) ? (int) $options['timeout'] : 20;

if ( '' === $base_url && ! empty( $config['base_url'] ) ) {
	$base_url = (string) $config['base_url'];
}

if ( '' === $cookie && ! empty( $config['cookie_header'] ) ) {
	$cookie = (string) $config['cookie_header'];
}

$checks = isset( $config['checks'] ) && is_array( $config['checks'] ) ? $config['checks'] : $runner->get_default_checks();

if ( '' === trim( $base_url ) ) {
	fwrite( STDERR, "Missing --base-url. Expected a wp-admin base URL such as http://example.test/wp-admin/.\n" );
	exit( 1 );
}

if ( '' === trim( $cookie ) ) {
	fwrite( STDERR, "Missing --cookie. Provide the authenticated browser Cookie header value for wp-admin.\n" );
	exit( 1 );
}

$base_url = $runner->normalize_base_url( $base_url );
$failures = 0;

echo 'Sentinel live admin smoke' . PHP_EOL;
echo 'Base URL: ' . $base_url . PHP_EOL;
echo 'Checks: ' . count( $checks ) . PHP_EOL . PHP_EOL;

foreach ( $checks as $check ) {
	$label     = isset( $check['label'] ) ? (string) $check['label'] : 'Smoke check';
	$resolved  = $runner->resolve_check( $check, $base_url, $cookie, $timeout );
	$url       = isset( $resolved['url'] ) ? (string) $resolved['url'] : $runner->build_url( $base_url, isset( $check['path'] ) ? (string) $check['path'] : '' );

	if ( ! empty( $resolved['skipped'] ) ) {
		echo '[SKIP] ' . $label . PHP_EOL;
		echo '  Source URL: ' . ( isset( $resolved['source_url'] ) ? (string) $resolved['source_url'] : '' ) . PHP_EOL;
		echo '  Reason: ' . ( isset( $resolved['skip_reason'] ) ? (string) $resolved['skip_reason'] : 'Optional check was not applicable.' ) . PHP_EOL;
		continue;
	}

	if ( ! empty( $resolved['resolve_error'] ) ) {
		$failures++;
		echo '[FAIL] ' . $label . ' [resolve]' . PHP_EOL;
		echo '  Source URL: ' . ( isset( $resolved['source_url'] ) ? (string) $resolved['source_url'] : '' ) . PHP_EOL;
		echo '  Resolve error: ' . (string) $resolved['resolve_error'] . PHP_EOL;

		if ( ! empty( $resolved['source_auth_fallback'] ) ) {
			echo '  Source auth fallback: response looks like wp-login.' . PHP_EOL;
		}

		if ( ! empty( $resolved['source_error'] ) ) {
			echo '  Source transport error: ' . (string) $resolved['source_error'] . PHP_EOL;
		}

		continue;
	}

	$http = $runner->fetch( $url, $cookie, $timeout );
	$eval = $runner->evaluate_response( $check, isset( $http['status_code'] ) ? (int) $http['status_code'] : 0, isset( $http['body'] ) ? (string) $http['body'] : '' );

	if ( $eval['passed'] ) {
		echo '[PASS] ' . $label . ' [' . $eval['status_code'] . ']' . PHP_EOL;

		if ( ! empty( $eval['observed_optional_markers'] ) ) {
			echo '  Optional markers found: ' . implode( ', ', $eval['observed_optional_markers'] ) . PHP_EOL;
		}

		if ( ! empty( $eval['missing_optional_markers'] ) ) {
			echo '  Optional markers missing: ' . implode( ', ', $eval['missing_optional_markers'] ) . PHP_EOL;
		}

		continue;
	}

	$failures++;
	echo '[FAIL] ' . $label . ' [' . $eval['status_code'] . ']' . PHP_EOL;
	echo '  URL: ' . $url . PHP_EOL;

	if ( ! empty( $eval['auth_fallback'] ) ) {
		echo '  Auth fallback: response looks like wp-login.' . PHP_EOL;
	}

	if ( ! empty( $eval['missing_markers'] ) ) {
		echo '  Missing markers: ' . implode( ', ', $eval['missing_markers'] ) . PHP_EOL;
	}

	if ( ! empty( $eval['observed_optional_markers'] ) ) {
		echo '  Optional markers found: ' . implode( ', ', $eval['observed_optional_markers'] ) . PHP_EOL;
	}

	if ( ! empty( $eval['missing_optional_markers'] ) ) {
		echo '  Optional markers missing: ' . implode( ', ', $eval['missing_optional_markers'] ) . PHP_EOL;
	}

	if ( ! empty( $http['error'] ) ) {
		echo '  Transport error: ' . $http['error'] . PHP_EOL;
	}
}

echo PHP_EOL;

if ( $failures > 0 ) {
	echo 'Smoke run failed: ' . $failures . ' page(s) did not meet the expected markers.' . PHP_EOL;
	exit( 1 );
}

echo 'Smoke run passed.' . PHP_EOL;
