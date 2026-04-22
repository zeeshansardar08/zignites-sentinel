<?php
/**
 * Live authenticated admin smoke runner for Sentinel wp-admin surfaces.
 *
 * Usage:
 * php tests/smoke-admin-live.php --base-url=http://example.test/wp-admin/ --cookie="wordpress_logged_in_...=..."
 * php tests/smoke-admin-live.php --base-url=http://zee-dev.test/wp-admin/ --local-user=1
 * php tests/smoke-admin-live.php --config=tests/admin-smoke-config.sample.php
 *
 * Local config discovery:
 * - tests/admin-smoke-config.php
 * - tests/admin-smoke-config.local.php
 */

require_once __DIR__ . '/class-admin-smoke-runner.php';
require_once __DIR__ . '/class-local-admin-auth-helper.php';

if ( 'cli' !== PHP_SAPI ) {
	fwrite( STDERR, "This script must be run from the command line.\n" );
	exit( 1 );
}

$options = getopt( '', array( 'base-url::', 'cookie::', 'local-user::', 'wp-root::', 'config::', 'timeout::' ) );
$runner      = new ZNTS_Admin_Smoke_Runner();
$auth_helper = new ZNTS_Local_Admin_Auth_Helper();
$config      = array();
$config_path = isset( $options['config'] ) ? trim( (string) $options['config'] ) : '';

if ( '' === $config_path ) {
	$config_path = $runner->find_existing_config_path(
		array(
			__DIR__ . '/admin-smoke-config.php',
			__DIR__ . '/admin-smoke-config.local.php',
		)
	);
}

if ( '' !== $config_path ) {
	$config = $runner->load_config( $config_path );
}

$base_url   = isset( $options['base-url'] ) ? (string) $options['base-url'] : '';
$cookie     = isset( $options['cookie'] ) ? (string) $options['cookie'] : '';
$local_user = isset( $options['local-user'] ) ? (string) $options['local-user'] : '';
$wp_root    = isset( $options['wp-root'] ) ? (string) $options['wp-root'] : '';
$timeout    = isset( $options['timeout'] ) ? (int) $options['timeout'] : 20;
$auth_label = '';

if ( '' === trim( $base_url ) ) {
	$base_url = $runner->get_environment_value( array( 'ZNTS_SMOKE_BASE_URL' ) );
}

if ( '' === trim( $base_url ) && ! empty( $config['base_url'] ) ) {
	$base_url = (string) $config['base_url'];
}

if ( '' === trim( $cookie ) ) {
	$cookie = $runner->get_environment_value( array( 'ZNTS_SMOKE_COOKIE_HEADER' ) );
}

if ( '' === trim( $cookie ) && ! empty( $config['cookie_header'] ) ) {
	$cookie = (string) $config['cookie_header'];
}

if ( '' === trim( $local_user ) ) {
	$local_user = $runner->get_environment_value( array( 'ZNTS_SMOKE_LOCAL_USER' ) );
}

if ( '' === trim( $local_user ) && ! empty( $config['local_user'] ) ) {
	$local_user = (string) $config['local_user'];
}

if ( '' === trim( $wp_root ) ) {
	$wp_root = $runner->get_environment_value( array( 'ZNTS_SMOKE_WORDPRESS_ROOT' ) );
}

if ( '' === trim( $wp_root ) && ! empty( $config['wordpress_root'] ) ) {
	$wp_root = (string) $config['wordpress_root'];
}

if ( empty( $options['timeout'] ) ) {
	$timeout_env = $runner->get_environment_value( array( 'ZNTS_SMOKE_TIMEOUT' ) );

	if ( '' !== $timeout_env ) {
		$timeout = (int) $timeout_env;
	}
}

if ( empty( $options['timeout'] ) && ! empty( $config['timeout'] ) ) {
	$timeout = (int) $config['timeout'];
}

$checks = isset( $config['checks'] ) && is_array( $config['checks'] ) ? $config['checks'] : $runner->get_default_checks();
$prerequisites = isset( $config['prerequisites'] ) && is_array( $config['prerequisites'] ) ? $config['prerequisites'] : array();

if ( '' === trim( $base_url ) ) {
	fwrite( STDERR, "Missing --base-url. Expected a wp-admin base URL such as http://example.test/wp-admin/.\n" );
	exit( 1 );
}

if ( '' === trim( $cookie ) ) {
	if ( '' === trim( $local_user ) ) {
		fwrite( STDERR, "Missing --cookie. Provide the authenticated browser Cookie header value for wp-admin, or pass --local-user for local auth.\n" );
		exit( 1 );
	}

	try {
		$auth_context = $auth_helper->build_cookie_context( $base_url, $local_user, $wp_root );
		$cookie       = isset( $auth_context['cookie_header'] ) ? (string) $auth_context['cookie_header'] : '';
		$auth_label   = 'local user ' . ( isset( $auth_context['user_login'] ) ? (string) $auth_context['user_login'] : $local_user ) . ' via ' . ( isset( $auth_context['wordpress_root'] ) ? (string) $auth_context['wordpress_root'] : '' );
	} catch ( Throwable $throwable ) {
		fwrite( STDERR, 'Local auth setup failed: ' . $throwable->getMessage() . PHP_EOL );
		exit( 1 );
	}
}

$base_url = $runner->normalize_base_url( $base_url );
$failures = 0;
$passes   = 0;
$skips    = 0;

echo 'Sentinel live admin smoke' . PHP_EOL;
echo 'Base URL: ' . $base_url . PHP_EOL;
if ( '' !== $config_path ) {
	echo 'Config: ' . $config_path . PHP_EOL;
}
if ( '' !== $auth_label ) {
	echo 'Auth: ' . $auth_label . PHP_EOL;
}
echo 'Checks: ' . count( $checks ) . PHP_EOL . PHP_EOL;

if ( ! empty( $prerequisites ) ) {
	echo 'Prerequisites:' . PHP_EOL;

	foreach ( $prerequisites as $prerequisite ) {
		$prerequisite = trim( (string) $prerequisite );

		if ( '' === $prerequisite ) {
			continue;
		}

		echo '- ' . $prerequisite . PHP_EOL;
	}

	echo PHP_EOL;
}

foreach ( $checks as $check ) {
	$label     = isset( $check['label'] ) ? (string) $check['label'] : 'Smoke check';
	$resolved  = $runner->resolve_check( $check, $base_url, $cookie, $timeout );
	$url       = isset( $resolved['url'] ) ? (string) $resolved['url'] : $runner->build_url( $base_url, isset( $check['path'] ) ? (string) $check['path'] : '' );

	if ( ! empty( $resolved['skipped'] ) ) {
		$skips++;
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
	$skip = $runner->get_skip_decision(
		$check,
		isset( $http['status_code'] ) ? (int) $http['status_code'] : 0,
		isset( $http['error'] ) ? (string) $http['error'] : ''
	);

	if ( ! empty( $skip['skipped'] ) ) {
		$skips++;
		echo '[SKIP] ' . $label . PHP_EOL;
		echo '  URL: ' . $url . PHP_EOL;
		echo '  Reason: ' . ( isset( $skip['reason'] ) ? (string) $skip['reason'] : 'Environment prerequisite was not met.' ) . PHP_EOL;
		continue;
	}

	$eval = $runner->evaluate_response( $check, isset( $http['status_code'] ) ? (int) $http['status_code'] : 0, isset( $http['body'] ) ? (string) $http['body'] : '' );

	if ( $eval['passed'] ) {
		$passes++;
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
echo 'Summary: ' . $passes . ' passed, ' . $skips . ' skipped, ' . $failures . ' failed.' . PHP_EOL;
echo PHP_EOL;

if ( $failures > 0 ) {
	echo 'Smoke run failed: ' . $failures . ' page(s) did not meet the expected markers.' . PHP_EOL;
	exit( 1 );
}

echo 'Smoke run passed.' . PHP_EOL;
