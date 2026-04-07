<?php
/**
 * Live authenticated Event Logs CSV export verifier.
 *
 * Usage:
 * php tests/export-event-logs-live.php --base-url=http://example.test/wp-admin/ --cookie="wordpress_logged_in_...=..." --path="admin.php?page=zignites-sentinel-event-logs&source=restore-execution-journal&run_id=run-42"
 * php tests/export-event-logs-live.php --config=tests/event-log-export-config.sample.php
 */

require_once __DIR__ . '/class-admin-smoke-runner.php';

if ( 'cli' !== PHP_SAPI ) {
	fwrite( STDERR, "This script must be run from the command line.\n" );
	exit( 1 );
}

$options = getopt( '', array( 'base-url::', 'cookie::', 'path::', 'config::', 'timeout::' ) );
$runner  = new ZNTS_Admin_Smoke_Runner();
$config  = array();

if ( ! empty( $options['config'] ) ) {
	$config = $runner->load_config( (string) $options['config'] );
}

$base_url = isset( $options['base-url'] ) ? (string) $options['base-url'] : '';
$cookie   = isset( $options['cookie'] ) ? (string) $options['cookie'] : '';
$path     = isset( $options['path'] ) ? (string) $options['path'] : '';
$timeout  = isset( $options['timeout'] ) ? (int) $options['timeout'] : 20;

if ( '' === $base_url && ! empty( $config['base_url'] ) ) {
	$base_url = (string) $config['base_url'];
}

if ( '' === $cookie && ! empty( $config['cookie_header'] ) ) {
	$cookie = (string) $config['cookie_header'];
}

if ( '' === $path && ! empty( $config['path'] ) ) {
	$path = (string) $config['path'];
}

if ( empty( $options['timeout'] ) && ! empty( $config['timeout'] ) ) {
	$timeout = (int) $config['timeout'];
}

if ( '' === trim( $base_url ) ) {
	fwrite( STDERR, "Missing --base-url. Expected a wp-admin base URL such as http://example.test/wp-admin/.\n" );
	exit( 1 );
}

if ( '' === trim( $cookie ) ) {
	fwrite( STDERR, "Missing --cookie. Provide the authenticated browser Cookie header value for wp-admin.\n" );
	exit( 1 );
}

if ( '' === trim( $path ) ) {
	fwrite( STDERR, "Missing --path. Expected an Event Logs admin path with the filters you want to export.\n" );
	exit( 1 );
}

$base_url   = $runner->normalize_base_url( $base_url );
$source_url = $runner->build_url( $base_url, $path );

echo 'Sentinel Event Log export check' . PHP_EOL;
echo 'Base URL: ' . $base_url . PHP_EOL;
echo 'Source URL: ' . $source_url . PHP_EOL;

$page = znts_export_request( $source_url, $cookie, $timeout );

if ( 200 !== $page['status_code'] ) {
	fwrite( STDERR, 'Source page failed with HTTP ' . $page['status_code'] . '.' . PHP_EOL );
	exit( 1 );
}

if ( '' !== $page['error'] ) {
	fwrite( STDERR, 'Source transport error: ' . $page['error'] . PHP_EOL );
	exit( 1 );
}

if ( $runner->detect_login_fallback( $page['body'] ) ) {
	fwrite( STDERR, "Source page resolved to wp-login. Refresh the admin cookie and retry.\n" );
	exit( 1 );
}

$form = znts_find_event_log_export_form( $page['body'] );

if ( empty( $form['action'] ) || empty( $form['fields'] ) ) {
	fwrite( STDERR, "Could not find the Event Logs export form on the source page.\n" );
	exit( 1 );
}

$action_url = $runner->build_url( $base_url, (string) $form['action'] );
$filters    = array(
	'severity'    => isset( $form['fields']['severity'] ) ? (string) $form['fields']['severity'] : '',
	'source'      => isset( $form['fields']['source'] ) ? (string) $form['fields']['source'] : '',
	'run_id'      => isset( $form['fields']['run_id'] ) ? (string) $form['fields']['run_id'] : '',
	'snapshot_id' => isset( $form['fields']['snapshot_id'] ) ? (string) $form['fields']['snapshot_id'] : '',
	'log_search'  => isset( $form['fields']['log_search'] ) ? (string) $form['fields']['log_search'] : '',
);

echo 'Export action: ' . $action_url . PHP_EOL;
echo 'Resolved filters: ' . json_encode( $filters ) . PHP_EOL;

$export = znts_export_request(
	$action_url,
	$cookie,
	$timeout,
	array(
		'method'       => 'POST',
		'headers'      => array(
			'Accept: text/csv,text/plain,*/*',
			'Content-Type: application/x-www-form-urlencoded',
		),
		'body'         => http_build_query( $form['fields'] ),
		'content_type' => 'application/x-www-form-urlencoded',
	)
);

if ( 200 !== $export['status_code'] ) {
	fwrite( STDERR, 'Export failed with HTTP ' . $export['status_code'] . '.' . PHP_EOL );
	exit( 1 );
}

if ( '' !== $export['error'] ) {
	fwrite( STDERR, 'Export transport error: ' . $export['error'] . PHP_EOL );
	exit( 1 );
}

if ( $runner->detect_login_fallback( $export['body'] ) ) {
	fwrite( STDERR, "Export response resolved to wp-login. Refresh the admin cookie and retry.\n" );
	exit( 1 );
}

$content_type = znts_find_response_header( $export['headers'], 'Content-Type' );

if ( false === stripos( $content_type, 'text/csv' ) ) {
	fwrite( STDERR, 'Export response did not return CSV content. Content-Type: ' . $content_type . PHP_EOL );
	exit( 1 );
}

$content_disposition = znts_find_response_header( $export['headers'], 'Content-Disposition' );
$filename            = znts_extract_filename_from_disposition( $content_disposition );
$expected_prefix     = znts_build_expected_export_filename_prefix( $filters );

if ( '' === $filename ) {
	fwrite( STDERR, "Export response did not include a downloadable filename.\n" );
	exit( 1 );
}

if ( 0 !== strpos( $filename, $expected_prefix ) ) {
	fwrite( STDERR, 'Export filename did not reflect the filtered scope. Saw "' . $filename . '", expected prefix "' . $expected_prefix . '"' . PHP_EOL );
	exit( 1 );
}

$rows = znts_parse_csv_rows( $export['body'] );

if ( empty( $rows ) ) {
	fwrite( STDERR, "Export response was empty.\n" );
	exit( 1 );
}

$header = $rows[0];
$expected_header = array(
	'id',
	'created_at',
	'severity',
	'source',
	'event_type',
	'message',
	'snapshot_id',
	'run_id',
	'journal_scope',
	'journal_phase',
	'journal_status',
	'context_json',
);

if ( $header !== $expected_header ) {
	fwrite( STDERR, 'Export CSV header did not match the expected Sentinel schema.' . PHP_EOL );
	exit( 1 );
}

$data_rows = array_slice( $rows, 1 );
echo 'Export rows: ' . count( $data_rows ) . PHP_EOL;

$validation_error = znts_validate_export_rows_against_filters( $data_rows, $filters );

if ( '' !== $validation_error ) {
	fwrite( STDERR, $validation_error . PHP_EOL );
	exit( 1 );
}

echo 'Export check passed.' . PHP_EOL;

function znts_export_request( $url, $cookie_header, $timeout, array $args = array() ) {
	$url           = (string) $url;
	$cookie_header = trim( (string) $cookie_header );
	$timeout       = max( 1, (int) $timeout );
	$method        = isset( $args['method'] ) ? strtoupper( (string) $args['method'] ) : 'GET';
	$body          = isset( $args['body'] ) ? (string) $args['body'] : '';
	$headers       = isset( $args['headers'] ) && is_array( $args['headers'] ) ? $args['headers'] : array();

	$headers[] = 'User-Agent: Zignites-Sentinel-Export-Check/1.0';

	if ( '' !== $cookie_header ) {
		$headers[] = 'Cookie: ' . $cookie_header;
	}

	if ( function_exists( 'curl_init' ) ) {
		return znts_export_request_with_curl( $url, $timeout, $method, $headers, $body );
	}

	return znts_export_request_with_streams( $url, $timeout, $method, $headers, $body );
}

function znts_export_request_with_curl( $url, $timeout, $method, array $headers, $body ) {
	$handle = curl_init( $url );
	curl_setopt( $handle, CURLOPT_RETURNTRANSFER, true );
	curl_setopt( $handle, CURLOPT_FOLLOWLOCATION, true );
	curl_setopt( $handle, CURLOPT_CONNECTTIMEOUT, $timeout );
	curl_setopt( $handle, CURLOPT_TIMEOUT, $timeout );
	curl_setopt( $handle, CURLOPT_SSL_VERIFYPEER, false );
	curl_setopt( $handle, CURLOPT_SSL_VERIFYHOST, 0 );
	curl_setopt( $handle, CURLOPT_HTTPHEADER, $headers );
	curl_setopt( $handle, CURLOPT_HEADER, true );

	if ( 'POST' === $method ) {
		curl_setopt( $handle, CURLOPT_POST, true );
		curl_setopt( $handle, CURLOPT_POSTFIELDS, $body );
	}

	$response    = curl_exec( $handle );
	$error       = curl_error( $handle );
	$status_code = (int) curl_getinfo( $handle, CURLINFO_RESPONSE_CODE );
	$header_size = (int) curl_getinfo( $handle, CURLINFO_HEADER_SIZE );
	curl_close( $handle );

	if ( false === $response ) {
		return array(
			'status_code' => $status_code,
			'body'        => '',
			'error'       => (string) $error,
			'headers'     => array(),
		);
	}

	$raw_headers = substr( $response, 0, $header_size );
	$body_text   = substr( $response, $header_size );

	return array(
		'status_code' => $status_code,
		'body'        => false === $body_text ? '' : (string) $body_text,
		'error'       => (string) $error,
		'headers'     => znts_extract_last_header_block( (string) $raw_headers ),
	);
}

function znts_export_request_with_streams( $url, $timeout, $method, array $headers, $body ) {
	$context = stream_context_create(
		array(
			'http' => array(
				'method'        => $method,
				'timeout'       => $timeout,
				'ignore_errors' => true,
				'header'        => implode( "\r\n", $headers ),
				'content'       => $body,
			),
			'ssl'  => array(
				'verify_peer'      => false,
				'verify_peer_name' => false,
			),
		)
	);

	$response_body = @file_get_contents( $url, false, $context );
	$error         = '';
	$status_code   = 0;
	$response_headers = isset( $http_response_header ) && is_array( $http_response_header ) ? $http_response_header : array();

	if ( false === $response_body ) {
		$last_error = error_get_last();
		$error      = isset( $last_error['message'] ) ? (string) $last_error['message'] : 'Unknown stream error.';
		$response_body = '';
	}

	if ( ! empty( $response_headers[0] ) && preg_match( '/\s(\d{3})\s/', $response_headers[0], $matches ) ) {
		$status_code = (int) $matches[1];
	}

	return array(
		'status_code' => $status_code,
		'body'        => (string) $response_body,
		'error'       => $error,
		'headers'     => $response_headers,
	);
}

function znts_extract_last_header_block( $raw_headers ) {
	$raw_headers = trim( (string) $raw_headers );

	if ( '' === $raw_headers ) {
		return array();
	}

	$blocks = preg_split( "/\r\n\r\n|\n\n|\r\r/", $raw_headers );
	$blocks = array_values(
		array_filter(
			$blocks,
			function ( $block ) {
				return '' !== trim( (string) $block );
			}
		)
	);

	if ( empty( $blocks ) ) {
		return array();
	}

	return preg_split( "/\r\n|\n|\r/", trim( (string) $blocks[ count( $blocks ) - 1 ] ) );
}

function znts_find_event_log_export_form( $body ) {
	$body = (string) $body;

	if ( ! preg_match_all( '#<form\b[^>]*action=(["\'])(.*?)\1[^>]*>(.*?)</form>#is', $body, $forms, PREG_SET_ORDER ) ) {
		return array();
	}

	foreach ( $forms as $form_match ) {
		$action    = html_entity_decode( (string) $form_match[2], ENT_QUOTES, 'UTF-8' );
		$form_body = (string) $form_match[3];

		if ( false === stripos( $form_body, 'znts_export_event_logs' ) ) {
			continue;
		}

		$fields = array();

		if ( preg_match_all( '#<input\b[^>]*>#is', $form_body, $inputs ) ) {
			foreach ( $inputs[0] as $input_html ) {
				$attrs = znts_parse_html_attributes( $input_html );
				$name  = isset( $attrs['name'] ) ? $attrs['name'] : '';
				$type  = isset( $attrs['type'] ) ? strtolower( $attrs['type'] ) : 'text';

				if ( '' === $name || 'submit' === $type ) {
					continue;
				}

				$fields[ $name ] = isset( $attrs['value'] ) ? $attrs['value'] : '';
			}
		}

		if ( isset( $fields['action'] ) && 'znts_export_event_logs' === $fields['action'] ) {
			return array(
				'action' => $action,
				'fields' => $fields,
			);
		}
	}

	return array();
}

function znts_parse_html_attributes( $html ) {
	$attrs = array();

	if ( ! preg_match_all( '/([A-Za-z0-9:_-]+)\s*=\s*("([^"]*)"|\'([^\']*)\'|([^\s>]+))/', (string) $html, $matches, PREG_SET_ORDER ) ) {
		return $attrs;
	}

	foreach ( $matches as $match ) {
		$name  = strtolower( (string) $match[1] );
		$value = '';

		if ( isset( $match[3] ) && '' !== $match[3] ) {
			$value = $match[3];
		} elseif ( isset( $match[4] ) && '' !== $match[4] ) {
			$value = $match[4];
		} elseif ( isset( $match[5] ) ) {
			$value = $match[5];
		}

		$attrs[ $name ] = html_entity_decode( (string) $value, ENT_QUOTES, 'UTF-8' );
	}

	return $attrs;
}

function znts_find_response_header( array $headers, $name ) {
	$name = strtolower( (string) $name ) . ':';

	foreach ( $headers as $header_line ) {
		$header_line = (string) $header_line;

		if ( 0 === strpos( strtolower( $header_line ), $name ) ) {
			return trim( substr( $header_line, strlen( $name ) ) );
		}
	}

	return '';
}

function znts_extract_filename_from_disposition( $content_disposition ) {
	$content_disposition = (string) $content_disposition;

	if ( preg_match( '/filename="?([^";]+)"?/i', $content_disposition, $matches ) ) {
		return (string) $matches[1];
	}

	return '';
}

function znts_build_expected_export_filename_prefix( array $filters ) {
	$parts = array( 'znts-event-logs' );

	if ( ! empty( $filters['source'] ) ) {
		$parts[] = znts_slugify( (string) $filters['source'] );
	}

	if ( ! empty( $filters['run_id'] ) ) {
		$parts[] = znts_slugify( (string) $filters['run_id'] );
	}

	if ( ! empty( $filters['snapshot_id'] ) ) {
		$parts[] = 'snapshot-' . abs( (int) $filters['snapshot_id'] );
	}

	return implode( '-', array_filter( $parts ) ) . '-';
}

function znts_slugify( $value ) {
	$value = strtolower( trim( (string) $value ) );
	$value = preg_replace( '/[^a-z0-9]+/', '-', $value );

	return trim( (string) $value, '-' );
}

function znts_parse_csv_rows( $csv_body ) {
	$handle = fopen( 'php://temp', 'r+' );

	if ( false === $handle ) {
		return array();
	}

	fwrite( $handle, (string) $csv_body );
	rewind( $handle );

	$rows = array();

	while ( false !== ( $row = fgetcsv( $handle ) ) ) {
		if ( empty( $rows ) && isset( $row[0] ) ) {
			$row[0] = preg_replace( '/^\xEF\xBB\xBF/', '', (string) $row[0] );
		}

		$rows[] = $row;
	}

	fclose( $handle );

	return $rows;
}

function znts_validate_export_rows_against_filters( array $rows, array $filters ) {
	if ( empty( $rows ) ) {
		return '';
	}

	foreach ( $rows as $index => $row ) {
		$row_number = $index + 2;

		if ( count( $row ) < 12 ) {
			return 'Export row ' . $row_number . ' did not contain the full Sentinel export schema.';
		}

		if ( '' !== (string) $filters['source'] && (string) $row[3] !== (string) $filters['source'] ) {
			return 'Export row ' . $row_number . ' did not retain the filtered source scope.';
		}

		if ( '' !== (string) $filters['run_id'] && (string) $row[7] !== (string) $filters['run_id'] ) {
			return 'Export row ' . $row_number . ' did not retain the filtered run scope.';
		}

		if ( '' !== (string) $filters['snapshot_id'] && (string) $row[6] !== (string) abs( (int) $filters['snapshot_id'] ) ) {
			return 'Export row ' . $row_number . ' did not retain the filtered snapshot scope.';
		}
	}

	return '';
}
