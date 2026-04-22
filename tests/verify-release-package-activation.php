<?php
/**
 * Live packaged-plugin activation helper using authenticated wp-admin requests.
 */

require_once __DIR__ . '/class-admin-smoke-runner.php';
require_once __DIR__ . '/class-local-admin-auth-helper.php';
require_once __DIR__ . '/class-release-package-install-verifier.php';

function znts_release_http_fail( $message, array $context = array(), $exit_code = 1 ) {
	$payload = array(
		'ok'      => false,
		'message' => (string) $message,
		'context' => $context,
	);

	fwrite( STDERR, json_encode( $payload, JSON_PRETTY_PRINT ) . PHP_EOL );
	exit( (int) $exit_code );
}

function znts_release_http_emit_json( array $payload ) {
	echo '__ZNTS_JSON_START__' . PHP_EOL;
	echo json_encode( $payload, JSON_PRETTY_PRINT ) . PHP_EOL;
	echo '__ZNTS_JSON_END__' . PHP_EOL;
}

function znts_release_http_sanitize_key( $value ) {
	$value = strtolower( trim( (string) $value ) );

	return preg_replace( '/[^a-z0-9_\-]/', '', $value );
}

function znts_release_http_parse_args( array $argv ) {
	$args = array(
		'mode'            => 'activate',
		'base-url'        => '',
		'local-user'      => '',
		'wp-root'         => '',
		'plugin'          => '',
		'original-plugin' => '',
		'state-file'      => '',
	);

	foreach ( array_slice( $argv, 1 ) as $argument ) {
		if ( 0 !== strpos( $argument, '--' ) ) {
			continue;
		}

		$pair  = explode( '=', substr( $argument, 2 ), 2 );
		$key   = isset( $pair[0] ) ? (string) $pair[0] : '';
		$value = isset( $pair[1] ) ? (string) $pair[1] : '1';

		if ( array_key_exists( $key, $args ) ) {
			$args[ $key ] = $value;
		}
	}

	return $args;
}

function znts_release_http_write_state_file( $state_file, array $state ) {
	if ( '' === $state_file ) {
		return;
	}

	$directory = dirname( $state_file );

	if ( ! is_dir( $directory ) ) {
		mkdir( $directory, 0777, true );
	}

	file_put_contents( $state_file, json_encode( $state, JSON_PRETTY_PRINT ) );
}

function znts_release_http_read_state_file( $state_file ) {
	if ( '' === $state_file || ! file_exists( $state_file ) ) {
		return array();
	}

	$decoded = json_decode( (string) file_get_contents( $state_file ), true );

	return is_array( $decoded ) ? $decoded : array();
}

function znts_release_http_fetch_body_or_fail( ZNTS_Admin_Smoke_Runner $runner, $url, $cookie_header, $context_label ) {
	$response = $runner->fetch( $url, $cookie_header, 20 );
	$status   = isset( $response['status_code'] ) ? (int) $response['status_code'] : 0;
	$body     = isset( $response['body'] ) ? (string) $response['body'] : '';
	$error    = isset( $response['error'] ) ? (string) $response['error'] : '';

	if ( 200 !== $status ) {
		znts_release_http_fail(
			'Unexpected HTTP status while handling release package verification.',
			array(
				'context' => $context_label,
				'url'     => $url,
				'status'  => $status,
				'error'   => $error,
			)
		);
	}

	if ( false !== stripos( $body, 'wp-login.php' ) && false !== stripos( $body, 'user_login' ) ) {
		znts_release_http_fail(
			'Authenticated package verification request resolved to wp-login.',
			array(
				'context' => $context_label,
				'url'     => $url,
			)
		);
	}

	return $body;
}

function znts_release_http_plugins_page_state( $body, $plugin_basename ) {
	$plugin_basename = preg_quote( $plugin_basename, '#' );
	$state           = array(
		'present'  => false,
		'active'   => false,
		'inactive' => false,
	);

	if ( preg_match( '#<tr class="([^"]*)"[^>]*data-plugin="' . $plugin_basename . '"#', $body, $matches ) ) {
		$classes           = isset( $matches[1] ) ? (string) $matches[1] : '';
		$state['present']  = true;
		$state['inactive'] = false !== strpos( $classes, 'inactive' );
		$state['active']   = false !== strpos( $classes, 'active' ) && ! $state['inactive'];
	}

	return $state;
}

function znts_release_http_plugin_states_payload( $body, $plugin_basename, $original_plugin ) {
	return array(
		'packaged' => znts_release_http_plugins_page_state( $body, $plugin_basename ),
		'original' => znts_release_http_plugins_page_state( $body, $original_plugin ),
	);
}

function znts_release_http_plugins_page_url( ZNTS_Admin_Smoke_Runner $runner, $base_url ) {
	return $runner->build_url( $base_url, 'plugins.php' );
}

function znts_release_http_plugin_action_url( ZNTS_Admin_Smoke_Runner $runner, $base_url, $action, $plugin_basename, $nonce ) {
	return $runner->build_url(
		$base_url,
		'plugins.php?action=' . rawurlencode( $action ) . '&plugin=' . rawurlencode( $plugin_basename ) . '&_wpnonce=' . rawurlencode( $nonce )
	);
}

$args            = znts_release_http_parse_args( $argv );
$mode            = isset( $args['mode'] ) ? znts_release_http_sanitize_key( $args['mode'] ) : 'activate';
$base_url        = isset( $args['base-url'] ) ? trim( (string) $args['base-url'] ) : '';
$local_user      = isset( $args['local-user'] ) ? trim( (string) $args['local-user'] ) : '';
$wp_root         = isset( $args['wp-root'] ) ? trim( (string) $args['wp-root'] ) : '';
$plugin_basename = isset( $args['plugin'] ) ? ZNTS_Release_Package_Install_Verifier::normalize_plugin_basename( $args['plugin'] ) : '';
$original_plugin = isset( $args['original-plugin'] ) ? ZNTS_Release_Package_Install_Verifier::normalize_plugin_basename( $args['original-plugin'] ) : '';
$state_file      = isset( $args['state-file'] ) ? str_replace( '\\', '/', (string) $args['state-file'] ) : '';

if ( '' === $base_url ) {
	znts_release_http_fail( 'A valid --base-url is required.' );
}

if ( '' === $local_user ) {
	znts_release_http_fail( 'A valid --local-user is required for package activation verification.' );
}

if ( ! ZNTS_Release_Package_Install_Verifier::is_valid_plugin_basename( $plugin_basename ) ) {
	znts_release_http_fail( 'A valid packaged --plugin basename is required.', array( 'plugin' => $plugin_basename ) );
}

if ( ! ZNTS_Release_Package_Install_Verifier::is_valid_plugin_basename( $original_plugin ) ) {
	znts_release_http_fail( 'A valid --original-plugin basename is required.', array( 'original_plugin' => $original_plugin ) );
}

$runner      = new ZNTS_Admin_Smoke_Runner();
$auth_helper = new ZNTS_Local_Admin_Auth_Helper();
$base_url    = $runner->normalize_base_url( $base_url );

try {
	$auth_context = $auth_helper->build_cookie_context( $base_url, $local_user, $wp_root );
} catch ( Throwable $throwable ) {
	znts_release_http_fail( 'Local package verification auth setup failed.', array( 'error' => $throwable->getMessage() ) );
}

$cookie_header     = isset( $auth_context['cookie_header'] ) ? (string) $auth_context['cookie_header'] : '';
$user_id           = isset( $auth_context['user_id'] ) ? (int) $auth_context['user_id'] : 0;
$logged_in_cookie  = isset( $auth_context['logged_in_cookie'] ) ? (string) $auth_context['logged_in_cookie'] : '';
$plugins_page_body = znts_release_http_fetch_body_or_fail( $runner, znts_release_http_plugins_page_url( $runner, $base_url ), $cookie_header, 'plugins-page' );

if ( 'restore' === $mode ) {
	$state = znts_release_http_read_state_file( $state_file );
	$reactivate_original = array_key_exists( 'original_was_active', $state ) ? ! empty( $state['original_was_active'] ) : true;
	$packaged_state      = znts_release_http_plugins_page_state( $plugins_page_body, $plugin_basename );

	if ( ! empty( $packaged_state['active'] ) ) {
		$deactivate_nonce = $auth_helper->build_nonce( 'deactivate-plugin_' . $plugin_basename, $user_id, $logged_in_cookie );
		znts_release_http_fetch_body_or_fail(
			$runner,
			znts_release_http_plugin_action_url( $runner, $base_url, 'deactivate', $plugin_basename, $deactivate_nonce ),
			$cookie_header,
			'deactivate-packaged-plugin'
		);
	}

	if ( $reactivate_original ) {
		$activate_nonce = $auth_helper->build_nonce( 'activate-plugin_' . $original_plugin, $user_id, $logged_in_cookie );
		znts_release_http_fetch_body_or_fail(
			$runner,
			znts_release_http_plugin_action_url( $runner, $base_url, 'activate', $original_plugin, $activate_nonce ),
			$cookie_header,
			'activate-original-plugin'
		);
	}

	$plugins_page_body = znts_release_http_fetch_body_or_fail( $runner, znts_release_http_plugins_page_url( $runner, $base_url ), $cookie_header, 'plugins-page-after-restore' );
	$final_packaged    = znts_release_http_plugins_page_state( $plugins_page_body, $plugin_basename );
	$final_original    = znts_release_http_plugins_page_state( $plugins_page_body, $original_plugin );

	if ( ! empty( $final_packaged['active'] ) ) {
		znts_release_http_fail( 'Packaged plugin is still active after restore.', array( 'plugin' => $plugin_basename ) );
	}

	if ( $reactivate_original && empty( $final_original['active'] ) ) {
		znts_release_http_fail( 'Original plugin did not return to the active state after restore.', array( 'plugin' => $original_plugin ) );
	}

	if ( '' !== $state_file && file_exists( $state_file ) ) {
		unlink( $state_file );
	}

	znts_release_http_emit_json(
		array(
			'ok'                   => true,
			'mode'                 => 'restore',
			'plugin'               => $plugin_basename,
			'original_plugin'      => $original_plugin,
			'original_reactivated' => $reactivate_original,
		)
	);
	exit( 0 );
}

if ( 'status' === $mode ) {
	znts_release_http_emit_json(
		array(
			'ok'             => true,
			'mode'           => 'status',
			'plugin_states'  => znts_release_http_plugin_states_payload( $plugins_page_body, $plugin_basename, $original_plugin ),
			'plugin'         => $plugin_basename,
			'original_plugin'=> $original_plugin,
		)
	);
	exit( 0 );
}

$original_state    = znts_release_http_plugins_page_state( $plugins_page_body, $original_plugin );
$original_was_active = ! empty( $original_state['active'] );

znts_release_http_write_state_file(
	$state_file,
	array(
		'plugin'              => $plugin_basename,
		'original_plugin'     => $original_plugin,
		'original_was_active' => $original_was_active,
	)
);

if ( $original_was_active ) {
	$deactivate_nonce = $auth_helper->build_nonce( 'deactivate-plugin_' . $original_plugin, $user_id, $logged_in_cookie );
	znts_release_http_fetch_body_or_fail(
		$runner,
		znts_release_http_plugin_action_url( $runner, $base_url, 'deactivate', $original_plugin, $deactivate_nonce ),
		$cookie_header,
		'deactivate-original-plugin'
	);
}

$activate_nonce = $auth_helper->build_nonce( 'activate-plugin_' . $plugin_basename, $user_id, $logged_in_cookie );
znts_release_http_fetch_body_or_fail(
	$runner,
	znts_release_http_plugin_action_url( $runner, $base_url, 'activate', $plugin_basename, $activate_nonce ),
	$cookie_header,
	'activate-packaged-plugin'
);

$plugins_page_body = znts_release_http_fetch_body_or_fail( $runner, znts_release_http_plugins_page_url( $runner, $base_url ), $cookie_header, 'plugins-page-after-activate' );
$packaged_state    = znts_release_http_plugins_page_state( $plugins_page_body, $plugin_basename );
$final_original    = znts_release_http_plugins_page_state( $plugins_page_body, $original_plugin );

if ( empty( $packaged_state['active'] ) ) {
	if ( $original_was_active && empty( $final_original['active'] ) ) {
		$restore_nonce = $auth_helper->build_nonce( 'activate-plugin_' . $original_plugin, $user_id, $logged_in_cookie );
		znts_release_http_fetch_body_or_fail(
			$runner,
			znts_release_http_plugin_action_url( $runner, $base_url, 'activate', $original_plugin, $restore_nonce ),
			$cookie_header,
			'reactivate-original-after-packaged-failure'
		);
	}

	znts_release_http_fail(
		'Packaged plugin did not reach the active state after activation.',
		array(
			'plugin'        => $plugin_basename,
			'plugin_states' => znts_release_http_plugin_states_payload( $plugins_page_body, $plugin_basename, $original_plugin ),
		)
	);
}

if ( $original_was_active && empty( $final_original['inactive'] ) ) {
	znts_release_http_fail( 'Original plugin did not reach the inactive state after packaged activation.', array( 'plugin' => $original_plugin ) );
}

znts_release_http_emit_json(
	array(
		'ok'                  => true,
		'mode'                => 'activate',
		'plugin'              => $plugin_basename,
		'original_plugin'     => $original_plugin,
		'original_was_active' => $original_was_active,
		'activated'           => true,
	)
);
