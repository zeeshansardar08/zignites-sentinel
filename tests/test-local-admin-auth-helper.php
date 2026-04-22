<?php
/**
 * Focused tests for the local admin auth helper.
 */

require_once __DIR__ . '/class-local-admin-auth-helper.php';

function znts_test_local_admin_auth_helper_finds_wordpress_root_from_nested_plugin_path() {
	$helper    = new ZNTS_Local_Admin_Auth_Helper();
	$base_path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'znts-local-auth-' . uniqid();
	$wp_root   = $base_path . DIRECTORY_SEPARATOR . 'site';
	$tests_dir = $wp_root . DIRECTORY_SEPARATOR . 'wp-content' . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . 'zignites-sentinel' . DIRECTORY_SEPARATOR . 'tests';

	mkdir( $tests_dir, 0777, true );
	file_put_contents( $wp_root . DIRECTORY_SEPARATOR . 'wp-load.php', "<?php\n" );

	$resolved = $helper->find_wordpress_root( $tests_dir );

	znts_local_admin_auth_cleanup( $base_path );

	znts_assert_same( $helper->normalize_path( $wp_root ), $resolved, 'Local admin auth helper should walk upward until it finds the WordPress root.' );
}

function znts_test_local_admin_auth_helper_parses_base_url_context_with_port() {
	$helper  = new ZNTS_Local_Admin_Auth_Helper();
	$context = $helper->parse_base_url_context( 'https://example.test:8443/wp-admin/' );

	znts_assert_same( 'https', $context['scheme'], 'Local admin auth helper should preserve the base URL scheme.' );
	znts_assert_same( 'example.test:8443', $context['host'], 'Local admin auth helper should preserve the host and port for the auth request context.' );
	znts_assert_same( 'on', $context['https'], 'Local admin auth helper should mark HTTPS contexts correctly.' );
}

function znts_test_local_admin_auth_helper_normalizes_blank_paths() {
	$helper = new ZNTS_Local_Admin_Auth_Helper();

	znts_assert_same( '', $helper->normalize_path( '' ), 'Local admin auth helper should return an empty string for blank paths.' );
}

function znts_local_admin_auth_cleanup( $path ) {
	$path = (string) $path;

	if ( '' === $path || ! file_exists( $path ) ) {
		return;
	}

	if ( is_file( $path ) ) {
		@unlink( $path );
		return;
	}

	$items = scandir( $path );

	if ( false === $items ) {
		return;
	}

	foreach ( $items as $item ) {
		if ( '.' === $item || '..' === $item ) {
			continue;
		}

		znts_local_admin_auth_cleanup( $path . DIRECTORY_SEPARATOR . $item );
	}

	@rmdir( $path );
}
