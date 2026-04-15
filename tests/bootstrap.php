<?php
/**
 * Minimal test bootstrap for local Sentinel unit-style checks.
 */

define( 'ABSPATH', __DIR__ . '/../' );
define( 'DAY_IN_SECONDS', 86400 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'ZNTS_VERSION', '1.29.0-test' );
define( 'ZNTS_OPTION_SETTINGS', 'znts_settings' );
define( 'ZNTS_OPTION_LAST_SNAPSHOT_HEALTH_BASELINE', 'znts_last_snapshot_health_baseline' );
define( 'ZNTS_OPTION_RESTORE_EXECUTION_CHECKPOINT', 'znts_restore_execution_checkpoint' );
define( 'ZNTS_OPTION_RESTORE_ROLLBACK_CHECKPOINT', 'znts_restore_rollback_checkpoint' );

if ( ! defined( 'WP_PLUGIN_DIR' ) ) {
	define( 'WP_PLUGIN_DIR', 'D:/plugins' );
}

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

if ( ! function_exists( 'sanitize_title' ) ) {
	function sanitize_title( $title ) {
		$title = strtolower( trim( (string) $title ) );
		$title = preg_replace( '/[^a-z0-9]+/', '-', $title );

		return trim( (string) $title, '-' );
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

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $value, $flags = 0, $depth = 512 ) {
		return json_encode( $value, $flags, $depth );
	}
}

if ( ! function_exists( 'wp_salt' ) ) {
	function wp_salt( $scheme = 'auth' ) {
		return 'znts-test-salt-' . (string) $scheme;
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $name, $default = false ) {
		return array_key_exists( $name, $GLOBALS['znts_test_options'] ) ? $GLOBALS['znts_test_options'][ $name ] : $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( $name, $value, $autoload = null ) {
		$GLOBALS['znts_test_options'][ $name ] = $value;

		return true;
	}
}

if ( ! function_exists( 'delete_option' ) ) {
	function delete_option( $name ) {
		unset( $GLOBALS['znts_test_options'][ $name ] );

		return true;
	}
}

if ( ! function_exists( 'current_time' ) ) {
	function current_time( $type, $gmt = 0 ) {
		return gmdate( 'Y-m-d H:i:s' );
	}
}

if ( ! function_exists( 'wp_normalize_path' ) ) {
	function wp_normalize_path( $path ) {
		return str_replace( '\\', '/', (string) $path );
	}
}

if ( ! function_exists( 'wp_upload_dir' ) ) {
	function wp_upload_dir() {
		return array(
			'basedir' => 'D:/uploads',
			'baseurl' => 'http://example.test/uploads',
			'error'   => false,
		);
	}
}

if ( ! function_exists( 'trailingslashit' ) ) {
	function trailingslashit( $value ) {
		return rtrim( (string) $value, "/\\" ) . '/';
	}
}

if ( ! function_exists( 'wp_mkdir_p' ) ) {
	function wp_mkdir_p( $target ) {
		return is_dir( $target ) || mkdir( $target, 0777, true );
	}
}

if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( $path = '' ) {
		$path = ltrim( (string) $path, '/' );

		return 'http://example.test/wp-admin/' . $path;
	}
}

if ( ! function_exists( 'get_theme_root' ) ) {
	function get_theme_root() {
		return 'D:/themes';
	}
}

if ( ! function_exists( 'wp_delete_file' ) ) {
	function wp_delete_file( $path ) {
		if ( ! file_exists( $path ) ) {
			return true;
		}

		return unlink( $path );
	}
}

if ( ! function_exists( 'add_query_arg' ) ) {
	function add_query_arg( $args, $url = '' ) {
		$url   = (string) $url;
		$args  = is_array( $args ) ? $args : array();
		$parts = parse_url( $url );
		$query = array();

		if ( ! empty( $parts['query'] ) ) {
			parse_str( $parts['query'], $query );
		}

		foreach ( $args as $key => $value ) {
			$query[ $key ] = $value;
		}

		$scheme   = isset( $parts['scheme'] ) ? $parts['scheme'] . '://' : '';
		$host     = isset( $parts['host'] ) ? $parts['host'] : '';
		$port     = isset( $parts['port'] ) ? ':' . $parts['port'] : '';
		$path     = isset( $parts['path'] ) ? $parts['path'] : '';
		$fragment = isset( $parts['fragment'] ) ? '#' . $parts['fragment'] : '';
		$query    = http_build_query( $query );

		return $scheme . $host . $port . $path . ( '' !== $query ? '?' . $query : '' ) . $fragment;
	}
}

if ( ! class_exists( 'Zignites\\Sentinel\\Logging\\Logger' ) ) {
	eval(
		'namespace Zignites\\Sentinel\\Logging; class Logger { public function log() { return true; } }'
	);
}

if ( ! class_exists( 'Zignites\\Sentinel\\Snapshots\\RestoreStagingManager' ) ) {
	eval(
		'namespace Zignites\\Sentinel\\Snapshots; class RestoreStagingManager { const STAGING_DIRECTORY = "zignites-sentinel/staging"; }'
	);
}

if ( ! class_exists( 'Zignites\\Sentinel\\Snapshots\\RestoreExecutionPlanner' ) ) {
	eval(
		'namespace Zignites\\Sentinel\\Snapshots; class RestoreExecutionPlanner {}'
	);
}

require_once __DIR__ . '/../includes/snapshots/class-artifact-storage-guard.php';
require_once __DIR__ . '/../includes/snapshots/class-restore-health-verifier.php';
require_once __DIR__ . '/../includes/logging/class-log-repository.php';
require_once __DIR__ . '/../includes/snapshots/class-restore-checkpoint-store.php';
require_once __DIR__ . '/../includes/snapshots/class-restore-journal-recorder.php';
require_once __DIR__ . '/../includes/snapshots/class-restore-executor.php';
require_once __DIR__ . '/../includes/snapshots/class-restore-rollback-manager.php';
require_once __DIR__ . '/../includes/snapshots/class-snapshot-artifact-repository.php';
require_once __DIR__ . '/../includes/admin/class-audit-report-verifier.php';
require_once __DIR__ . '/../includes/admin/class-status-presenter.php';
require_once __DIR__ . '/../includes/admin/class-event-log-presenter.php';
require_once __DIR__ . '/../includes/admin/class-dashboard-summary-presenter.php';
require_once __DIR__ . '/../includes/admin/class-health-comparison-state-builder.php';
require_once __DIR__ . '/../includes/admin/class-health-comparison-presenter.php';
require_once __DIR__ . '/../includes/admin/class-restore-checkpoint-presenter.php';
require_once __DIR__ . '/../includes/admin/class-restore-impact-summary-presenter.php';
require_once __DIR__ . '/../includes/admin/class-restore-impact-summary-state-builder.php';
require_once __DIR__ . '/../includes/admin/class-snapshot-audit-report-presenter.php';
require_once __DIR__ . '/../includes/admin/class-restore-operator-checklist-state-builder.php';
require_once __DIR__ . '/../includes/admin/class-snapshot-list-state-builder.php';
require_once __DIR__ . '/../includes/admin/class-dashboard-summary-state-builder.php';
require_once __DIR__ . '/../includes/admin/class-update-readiness-state-builder.php';
require_once __DIR__ . '/../includes/admin/class-snapshot-summary-state-builder.php';
require_once __DIR__ . '/../includes/admin/class-snapshot-summary-presenter.php';
require_once __DIR__ . '/../includes/admin/class-restore-operator-checklist-evaluator.php';
require_once __DIR__ . '/../includes/admin/class-settings-portability.php';
require_once __DIR__ . '/../includes/admin/class-snapshot-status-resolver.php';
require_once __DIR__ . '/../includes/admin/class-admin.php';
