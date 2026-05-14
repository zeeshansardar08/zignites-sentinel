<?php
/**
 * Minimal test bootstrap for local Sentinel unit-style checks.
 */

define( 'ABSPATH', __DIR__ . '/../' );
define( 'DAY_IN_SECONDS', 86400 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'ZNTS_VERSION', '1.29.0-test' );
define( 'ZNTS_MINIMUM_PHP_VERSION', '8.0' );
define( 'ZNTS_OPTION_SETTINGS', 'znts_settings' );
define( 'ZNTS_OPTION_OPERATION_LOCK', 'znts_operation_lock' );
define( 'ZNTS_OPTION_LAST_SNAPSHOT_HEALTH_BASELINE', 'znts_last_snapshot_health_baseline' );
define( 'ZNTS_OPTION_RESTORE_EXECUTION_CHECKPOINT', 'znts_restore_execution_checkpoint' );
define( 'ZNTS_OPTION_RESTORE_ROLLBACK_CHECKPOINT', 'znts_restore_rollback_checkpoint' );
define( 'ZNTS_OPTION_LAST_RESTORE_STAGE', 'znts_last_restore_stage' );
define( 'ZNTS_OPTION_LAST_RESTORE_PLAN', 'znts_last_restore_plan' );
define( 'ZNTS_OPTION_RESTORE_STAGE_CHECKPOINT', 'znts_restore_stage_checkpoint' );
define( 'ZNTS_OPTION_RESTORE_PLAN_CHECKPOINT', 'znts_restore_plan_checkpoint' );
define( 'ZNTS_OPTION_ASYNC_JOBS', 'znts_async_jobs' );
define( 'ZNTS_CRON_ASYNC_JOBS', 'znts_process_async_jobs' );
define( 'ZNTS_OPTION_SAFE_UPDATE_WINDOW_SETTINGS', 'znts_safe_update_window_settings' );
define( 'ZNTS_OPTION_LAST_SAFE_UPDATE_WINDOW', 'znts_last_safe_update_window' );
define( 'ZNTS_OPTION_ALERT_INTEGRATIONS', 'znts_alert_integrations' );
define( 'ZNTS_OPTION_WOOCOMMERCE_GUARDRAILS', 'znts_woocommerce_guardrails' );

if ( ! defined( 'WP_PLUGIN_DIR' ) ) {
	define( 'WP_PLUGIN_DIR', 'D:/plugins' );
}

if ( ! defined( 'WP_CONTENT_DIR' ) ) {
	define( 'WP_CONTENT_DIR', 'D:/content' );
}

$GLOBALS['znts_test_options'] = array();
$GLOBALS['znts_test_scheduled_events'] = array();

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = '' ) {
		return $text;
	}
}

if ( ! function_exists( '_n' ) ) {
	function _n( $single, $plural, $number, $domain = '' ) {
		return 1 === (int) $number ? $single : $plural;
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return (string) $text;
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url ) {
		return (string) $url;
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $url ) {
		return filter_var( (string) $url, FILTER_VALIDATE_URL ) ? (string) $url : '';
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

if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		if ( is_array( $value ) ) {
			return array_map( 'wp_unslash', $value );
		}

		return is_string( $value ) ? stripslashes( $value ) : $value;
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

if ( ! function_exists( 'add_option' ) ) {
	function add_option( $name, $value = '', $deprecated = '', $autoload = 'yes' ) {
		if ( array_key_exists( $name, $GLOBALS['znts_test_options'] ) ) {
			return false;
		}

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

if ( ! function_exists( 'wp_date' ) ) {
	function wp_date( $format, $timestamp = null, $timezone = null ) {
		return gmdate( $format, null === $timestamp ? time() : (int) $timestamp );
	}
}

if ( ! function_exists( 'wp_normalize_path' ) ) {
	function wp_normalize_path( $path ) {
		return str_replace( '\\', '/', (string) $path );
	}
}

if ( ! function_exists( 'wp_upload_dir' ) ) {
	function wp_upload_dir() {
		if ( ! empty( $GLOBALS['znts_test_upload_dir'] ) && is_array( $GLOBALS['znts_test_upload_dir'] ) ) {
			return array_merge(
				array(
					'basedir' => 'D:/uploads',
					'baseurl' => 'http://example.test/uploads',
					'error'   => false,
				),
				$GLOBALS['znts_test_upload_dir']
			);
		}

		return array(
			'basedir' => 'D:/uploads',
			'baseurl' => 'http://example.test/uploads',
			'error'   => false,
		);
	}
}

if ( ! function_exists( 'wp_generate_password' ) ) {
	function wp_generate_password( $length = 12, $special_chars = true, $extra_special_chars = false ) {
		return substr( str_repeat( 'a1b2c3d4', 8 ), 0, max( 1, (int) $length ) );
	}
}

if ( ! function_exists( 'wp_remote_get' ) ) {
	function wp_remote_get( $url, $args = array() ) {
		if ( isset( $GLOBALS['znts_test_http_response'] ) ) {
			return $GLOBALS['znts_test_http_response'];
		}

		return array(
			'response' => array(
				'code' => 403,
			),
			'body'     => '',
		);
	}
}

if ( ! function_exists( 'wp_remote_post' ) ) {
	function wp_remote_post( $url, $args = array() ) {
		$GLOBALS['znts_test_last_http_post'] = array(
			'url'  => $url,
			'args' => $args,
		);

		if ( isset( $GLOBALS['znts_test_http_post_response'] ) ) {
			return $GLOBALS['znts_test_http_post_response'];
		}

		return array(
			'response' => array(
				'code' => 200,
			),
			'body'     => '',
		);
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) {
		return false;
	}
}

if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
	function wp_remote_retrieve_response_code( $response ) {
		return isset( $response['response']['code'] ) ? (int) $response['response']['code'] : 0;
	}
}

if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
	function wp_remote_retrieve_body( $response ) {
		return isset( $response['body'] ) ? (string) $response['body'] : '';
	}
}

if ( ! function_exists( 'wp_remote_retrieve_header' ) ) {
	function wp_remote_retrieve_header( $response, $header ) {
		$header = strtolower( (string) $header );

		return isset( $response['headers'][ $header ] ) ? (string) $response['headers'][ $header ] : '';
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $text ) {
		return strip_tags( (string) $text );
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

if ( ! function_exists( 'get_site_option' ) ) {
	function get_site_option( $name, $default = false ) {
		return isset( $GLOBALS['znts_test_site_options'][ $name ] ) ? $GLOBALS['znts_test_site_options'][ $name ] : $default;
	}
}

if ( ! function_exists( 'home_url' ) ) {
	function home_url( $path = '' ) {
		return 'http://example.test/' . ltrim( (string) $path, '/' );
	}
}

if ( ! function_exists( 'rest_url' ) ) {
	function rest_url( $path = '' ) {
		return 'http://example.test/wp-json/' . ltrim( (string) $path, '/' );
	}
}

if ( ! function_exists( 'wp_login_url' ) ) {
	function wp_login_url() {
		return 'http://example.test/wp-login.php';
	}
}

if ( ! function_exists( 'is_user_logged_in' ) ) {
	function is_user_logged_in() {
		return ! empty( $GLOBALS['znts_test_is_user_logged_in'] );
	}
}

if ( ! class_exists( 'WP_Http_Cookie' ) ) {
	class WP_Http_Cookie {
		public $name;
		public $value;

		public function __construct( array $args = array() ) {
			$this->name  = isset( $args['name'] ) ? (string) $args['name'] : '';
			$this->value = isset( $args['value'] ) ? (string) $args['value'] : '';
		}
	}
}

if ( ! function_exists( 'nocache_headers' ) ) {
	function nocache_headers() {
		return true;
	}
}

if ( ! function_exists( 'network_admin_url' ) ) {
	function network_admin_url( $path = '' ) {
		$path = ltrim( (string) $path, '/' );

		return 'http://example.test/wp-admin/network/' . $path;
	}
}

if ( ! function_exists( 'wp_create_nonce' ) ) {
	function wp_create_nonce( $action = -1 ) {
		return 'nonce-' . sanitize_key( (string) $action );
	}
}

if ( ! function_exists( 'wp_next_scheduled' ) ) {
	function wp_next_scheduled( $hook ) {
		return isset( $GLOBALS['znts_test_scheduled_events'][ $hook ] ) ? $GLOBALS['znts_test_scheduled_events'][ $hook ] : false;
	}
}

if ( ! function_exists( 'wp_schedule_single_event' ) ) {
	function wp_schedule_single_event( $timestamp, $hook, $args = array() ) {
		$GLOBALS['znts_test_scheduled_events'][ $hook ] = (int) $timestamp;

		return true;
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
require_once __DIR__ . '/../includes/snapshots/class-artifact-storage-backend.php';
require_once __DIR__ . '/../includes/snapshots/class-local-artifact-storage-backend.php';
require_once __DIR__ . '/../includes/snapshots/class-artifact-exposure-scanner.php';
require_once __DIR__ . '/../includes/core/class-operation-lock.php';
require_once __DIR__ . '/../includes/core/class-disk-space-preflight.php';
require_once __DIR__ . '/../includes/diagnostics/class-woocommerce-guardrails.php';
require_once __DIR__ . '/../includes/integrations/class-alert-notifier.php';
require_once __DIR__ . '/../includes/platform/class-site-status-model.php';
require_once __DIR__ . '/../includes/platform/class-outbound-sync-boundary.php';
require_once __DIR__ . '/../includes/platform/class-agency-report-model.php';
require_once __DIR__ . '/../includes/jobs/class-job-store.php';
require_once __DIR__ . '/../includes/jobs/class-job-runner.php';
require_once __DIR__ . '/../includes/snapshots/class-component-manifest-builder.php';
require_once __DIR__ . '/../includes/snapshots/class-restore-health-verifier.php';
require_once __DIR__ . '/../includes/logging/class-log-repository.php';
require_once __DIR__ . '/../includes/snapshots/class-restore-checkpoint-store.php';
require_once __DIR__ . '/../includes/snapshots/class-restore-journal-recorder.php';
require_once __DIR__ . '/../includes/snapshots/class-restore-executor.php';
require_once __DIR__ . '/../includes/snapshots/class-restore-rollback-manager.php';
require_once __DIR__ . '/../includes/snapshots/class-snapshot-artifact-repository.php';
require_once __DIR__ . '/../includes/snapshots/class-snapshot-export-manager.php';
require_once __DIR__ . '/../includes/snapshots/class-snapshot-package-manager.php';
require_once __DIR__ . '/../includes/snapshots/class-snapshot-repository.php';
require_once __DIR__ . '/../includes/snapshots/class-snapshot-manager.php';
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
