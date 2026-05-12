<?php
/**
 * Seed a minimal checkpoint/history state for live admin smoke checks.
 *
 * Usage:
 * php tests/seed-admin-smoke.php --base-url=http://zee-dev.test/wp-admin/ --local-user=1
 */

require_once __DIR__ . '/class-local-admin-auth-helper.php';

if ( 'cli' !== PHP_SAPI ) {
	fwrite( STDERR, "This script must be run from the command line.\n" );
	exit( 1 );
}

$options    = getopt( '', array( 'base-url::', 'local-user::', 'wp-root::' ) );
$base_url   = isset( $options['base-url'] ) ? trim( (string) $options['base-url'] ) : 'http://zee-dev.test/wp-admin/';
$local_user = isset( $options['local-user'] ) ? trim( (string) $options['local-user'] ) : '1';
$wp_root    = isset( $options['wp-root'] ) ? trim( (string) $options['wp-root'] ) : '';
$helper     = new ZNTS_Local_Admin_Auth_Helper();

if ( '' === $wp_root ) {
	$wp_root = $helper->find_wordpress_root( __DIR__ );
}

if ( '' === $wp_root || ! is_file( $wp_root . DIRECTORY_SEPARATOR . 'wp-load.php' ) ) {
	fwrite( STDERR, "Could not locate wp-load.php. Pass --wp-root explicitly.\n" );
	exit( 1 );
}

$url_context = $helper->parse_base_url_context( $base_url );
$_SERVER['REQUEST_SCHEME'] = isset( $url_context['scheme'] ) ? $url_context['scheme'] : 'http';
$_SERVER['HTTP_HOST']      = isset( $url_context['host'] ) ? $url_context['host'] : '';
$_SERVER['HTTPS']          = isset( $url_context['https'] ) ? $url_context['https'] : 'off';
$_SERVER['REQUEST_METHOD'] = 'GET';

require_once $wp_root . DIRECTORY_SEPARATOR . 'wp-load.php';

if ( ! class_exists( '\Zignites\Sentinel\Core\Installer' ) ) {
	fwrite( STDERR, "Zignites Sentinel must be active before seeding smoke data.\n" );
	exit( 1 );
}

$user = false;

if ( is_numeric( $local_user ) ) {
	$user = get_user_by( 'id', (int) $local_user );
} elseif ( false !== strpos( $local_user, '@' ) ) {
	$user = get_user_by( 'email', $local_user );
} else {
	$user = get_user_by( 'login', $local_user );
}

if ( ! $user || empty( $user->ID ) ) {
	fwrite( STDERR, "Could not resolve local user for smoke seed.\n" );
	exit( 1 );
}

wp_set_current_user( (int) $user->ID );

if ( function_exists( 'current_user_can' ) && ! current_user_can( 'manage_options' ) ) {
	fwrite( STDERR, "The smoke seed user must be an administrator.\n" );
	exit( 1 );
}

\Zignites\Sentinel\Core\Installer::install();

global $wpdb;

$created_at     = current_time( 'mysql', true );
$active_plugins = get_option( 'active_plugins', array() );
$theme          = function_exists( 'wp_get_theme' ) ? wp_get_theme() : null;
$stylesheet     = function_exists( 'get_stylesheet' ) ? get_stylesheet() : '';
$label          = 'Smoke checkpoint ' . gmdate( 'Y-m-d H:i:s' );
$metadata       = array(
	'smoke_seed'          => true,
	'smoke_seed_version'  => defined( 'ZNTS_VERSION' ) ? ZNTS_VERSION : '',
	'component_manifest'  => array(
		'theme'   => array(
			'stylesheet' => $stylesheet,
			'name'       => $theme && method_exists( $theme, 'get' ) ? (string) $theme->get( 'Name' ) : $stylesheet,
		),
		'plugins' => is_array( $active_plugins ) ? array_values( $active_plugins ) : array(),
	),
);
$snapshots_table = \Zignites\Sentinel\Core\Installer::get_snapshots_table_name();
$logs_table      = \Zignites\Sentinel\Core\Installer::get_logs_table_name();

$inserted = $wpdb->insert(
	$snapshots_table,
	array(
		'snapshot_type'    => 'manual',
		'status'           => 'ready',
		'label'            => $label,
		'description'      => 'Smoke checkpoint seeded for release live admin validation.',
		'core_version'     => function_exists( 'get_bloginfo' ) ? (string) get_bloginfo( 'version' ) : '',
		'php_version'      => PHP_VERSION,
		'theme_stylesheet' => $stylesheet,
		'active_plugins'   => wp_json_encode( is_array( $active_plugins ) ? array_values( $active_plugins ) : array() ),
		'metadata'         => wp_json_encode( $metadata ),
		'created_by'       => (int) $user->ID,
		'created_at'       => $created_at,
	),
	array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
);

if ( false === $inserted ) {
	fwrite( STDERR, "Could not insert smoke checkpoint.\n" );
	exit( 1 );
}

$snapshot_id = (int) $wpdb->insert_id;

$wpdb->insert(
	$logs_table,
	array(
		'event_type' => 'smoke_checkpoint_seeded',
		'severity'   => 'info',
		'source'     => 'smoke',
		'message'    => 'Seeded checkpoint for live admin smoke validation.',
		'context'    => wp_json_encode( array( 'snapshot_id' => $snapshot_id ) ),
		'created_at' => $created_at,
	),
	array( '%s', '%s', '%s', '%s', '%s', '%s' )
);

echo 'Seeded smoke checkpoint: ' . $snapshot_id . PHP_EOL;
