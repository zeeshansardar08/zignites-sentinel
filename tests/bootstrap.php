<?php
/**
 * Minimal test bootstrap for local Sentinel unit-style checks.
 */

define( 'ABSPATH', __DIR__ . '/../' );
define( 'DAY_IN_SECONDS', 86400 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'ZNTS_OPTION_SETTINGS', 'znts_settings' );
define( 'ZNTS_OPTION_LAST_SNAPSHOT_HEALTH_BASELINE', 'znts_last_snapshot_health_baseline' );

$GLOBALS['znts_test_options'] = array();

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = '' ) {
		return $text;
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $value ) {
		return is_scalar( $value ) ? trim( (string) $value ) : '';
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		$key = strtolower( (string) $key );

		return preg_replace( '/[^a-z0-9_\-]/', '', $key );
	}
}

if ( ! function_exists( 'sanitize_html_class' ) ) {
	function sanitize_html_class( $class ) {
		return preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $class );
	}
}

if ( ! function_exists( 'absint' ) ) {
	function absint( $value ) {
		return abs( (int) $value );
	}
}

if ( ! function_exists( 'wp_parse_args' ) ) {
	function wp_parse_args( $args, $defaults = array() ) {
		return array_merge( $defaults, is_array( $args ) ? $args : array() );
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $name, $default = false ) {
		return array_key_exists( $name, $GLOBALS['znts_test_options'] ) ? $GLOBALS['znts_test_options'][ $name ] : $default;
	}
}

if ( ! class_exists( 'Zignites\\Sentinel\\Snapshots\\RestoreExecutor' ) ) {
	eval(
		'namespace Zignites\\Sentinel\\Snapshots; class RestoreExecutor { const JOURNAL_SOURCE = "restore-execution"; }'
	);
}

if ( ! class_exists( 'Zignites\\Sentinel\\Snapshots\\RestoreRollbackManager' ) ) {
	eval(
		'namespace Zignites\\Sentinel\\Snapshots; class RestoreRollbackManager { const JOURNAL_SOURCE = "restore-rollback"; }'
	);
}

require_once __DIR__ . '/../includes/logging/class-log-repository.php';
require_once __DIR__ . '/../includes/snapshots/class-restore-checkpoint-store.php';
require_once __DIR__ . '/../includes/snapshots/class-restore-journal-recorder.php';
require_once __DIR__ . '/../includes/snapshots/class-snapshot-artifact-repository.php';
require_once __DIR__ . '/../includes/admin/class-snapshot-status-resolver.php';
