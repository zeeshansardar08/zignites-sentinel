<?php
/**
 * Lightweight live admin smoke runner for authenticated wp-admin checks.
 */

class ZNTS_Admin_Smoke_Runner {

	/**
	 * Return default Sentinel admin smoke checks.
	 *
	 * @return array
	 */
	public function get_default_checks() {
		return array(
			array(
				'label'   => 'Sentinel Dashboard',
				'path'    => 'admin.php?page=zignites-sentinel',
				'markers' => array(
					'Site Status',
					'Recent Snapshots',
					'Recommended action',
				),
			),
			array(
				'label'   => 'Update Readiness',
				'path'    => 'admin.php?page=zignites-sentinel-update-readiness',
				'markers' => array(
					'Update Readiness',
					'Recent Snapshot Metadata',
					'Sentinel Settings',
				),
			),
			array(
				'label'   => 'Event Logs',
				'path'    => 'admin.php?page=zignites-sentinel-event-logs',
				'markers' => array(
					'Event Logs',
					'Export Filtered CSV',
					'Filter',
				),
			),
			array(
				'label'   => 'WordPress Dashboard Widget',
				'path'    => 'index.php',
				'markers' => array(
					'Zignites Sentinel',
					'Recommended action',
				),
			),
		);
	}

	/**
	 * Normalize a wp-admin base URL.
	 *
	 * @param string $base_url Base URL.
	 * @return string
	 */
	public function normalize_base_url( $base_url ) {
		$base_url = trim( (string) $base_url );

		if ( '' === $base_url ) {
			return '';
		}

		return rtrim( $base_url, '/' ) . '/';
	}

	/**
	 * Build the full request URL for a wp-admin path.
	 *
	 * @param string $base_url Base wp-admin URL.
	 * @param string $path     Relative wp-admin path.
	 * @return string
	 */
	public function build_url( $base_url, $path ) {
		$base_url = $this->normalize_base_url( $base_url );
		$path     = ltrim( (string) $path, '/' );

		return $base_url . $path;
	}

	/**
	 * Evaluate an HTTP response against required page markers.
	 *
	 * @param array $check       Check definition.
	 * @param int   $status_code HTTP status code.
	 * @param string $body       Response body.
	 * @return array
	 */
	public function evaluate_response( array $check, $status_code, $body ) {
		$body            = (string) $body;
		$status_code     = (int) $status_code;
		$missing_markers = array();
		$markers         = isset( $check['markers'] ) && is_array( $check['markers'] ) ? $check['markers'] : array();
		$auth_fallback   = $this->detect_login_fallback( $body );

		foreach ( $markers as $marker ) {
			if ( false === stripos( $body, (string) $marker ) ) {
				$missing_markers[] = (string) $marker;
			}
		}

		return array(
			'label'            => isset( $check['label'] ) ? (string) $check['label'] : 'Smoke check',
			'path'             => isset( $check['path'] ) ? (string) $check['path'] : '',
			'status_code'      => $status_code,
			'missing_markers'  => $missing_markers,
			'auth_fallback'    => $auth_fallback,
			'passed'           => 200 === $status_code && empty( $missing_markers ) && ! $auth_fallback,
		);
	}

	/**
	 * Detect whether wp-admin appears to have fallen back to the login screen.
	 *
	 * @param string $body Response body.
	 * @return bool
	 */
	public function detect_login_fallback( $body ) {
		$body = (string) $body;

		$markers = array(
			'name="log"',
			'name="pwd"',
			'wp-submit',
			'Lost your password?',
			'user_login',
		);

		foreach ( $markers as $marker ) {
			if ( false !== stripos( $body, $marker ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Fetch a wp-admin page using the provided cookie header.
	 *
	 * @param string $url           Full URL.
	 * @param string $cookie_header Browser cookie header value.
	 * @param int    $timeout       Timeout in seconds.
	 * @return array
	 */
	public function fetch( $url, $cookie_header, $timeout = 20 ) {
		$url           = (string) $url;
		$cookie_header = trim( (string) $cookie_header );
		$timeout       = max( 1, (int) $timeout );

		if ( function_exists( 'curl_init' ) ) {
			return $this->fetch_with_curl( $url, $cookie_header, $timeout );
		}

		return $this->fetch_with_streams( $url, $cookie_header, $timeout );
	}

	/**
	 * Load optional smoke config from disk.
	 *
	 * @param string $config_path Config path.
	 * @return array
	 */
	public function load_config( $config_path ) {
		$config_path = trim( (string) $config_path );

		if ( '' === $config_path || ! is_file( $config_path ) ) {
			return array();
		}

		$config = require $config_path;

		return is_array( $config ) ? $config : array();
	}

	/**
	 * Fetch using curl when available.
	 *
	 * @param string $url           Full URL.
	 * @param string $cookie_header Browser cookie header value.
	 * @param int    $timeout       Timeout in seconds.
	 * @return array
	 */
	protected function fetch_with_curl( $url, $cookie_header, $timeout ) {
		$headers = array(
			'Accept: text/html,application/xhtml+xml',
			'User-Agent: Zignites-Sentinel-Smoke/1.0',
		);

		if ( '' !== $cookie_header ) {
			$headers[] = 'Cookie: ' . $cookie_header;
		}

		$handle = curl_init( $url );
		curl_setopt( $handle, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $handle, CURLOPT_FOLLOWLOCATION, true );
		curl_setopt( $handle, CURLOPT_CONNECTTIMEOUT, $timeout );
		curl_setopt( $handle, CURLOPT_TIMEOUT, $timeout );
		curl_setopt( $handle, CURLOPT_SSL_VERIFYPEER, false );
		curl_setopt( $handle, CURLOPT_SSL_VERIFYHOST, 0 );
		curl_setopt( $handle, CURLOPT_HTTPHEADER, $headers );

		$body   = curl_exec( $handle );
		$error  = curl_error( $handle );
		$code   = (int) curl_getinfo( $handle, CURLINFO_RESPONSE_CODE );
		curl_close( $handle );

		return array(
			'status_code' => $code,
			'body'        => false === $body ? '' : (string) $body,
			'error'       => (string) $error,
		);
	}

	/**
	 * Fetch using PHP streams when curl is unavailable.
	 *
	 * @param string $url           Full URL.
	 * @param string $cookie_header Browser cookie header value.
	 * @param int    $timeout       Timeout in seconds.
	 * @return array
	 */
	protected function fetch_with_streams( $url, $cookie_header, $timeout ) {
		$headers = array(
			'Accept: text/html,application/xhtml+xml',
			'User-Agent: Zignites-Sentinel-Smoke/1.0',
		);

		if ( '' !== $cookie_header ) {
			$headers[] = 'Cookie: ' . $cookie_header;
		}

		$context = stream_context_create(
			array(
				'http' => array(
					'method'        => 'GET',
					'timeout'       => $timeout,
					'ignore_errors' => true,
					'header'        => implode( "\r\n", $headers ),
				),
				'ssl'  => array(
					'verify_peer'      => false,
					'verify_peer_name' => false,
				),
			)
		);

		$body    = @file_get_contents( $url, false, $context );
		$error   = '';
		$code    = 0;
		$headers = isset( $http_response_header ) && is_array( $http_response_header ) ? $http_response_header : array();

		if ( false === $body ) {
			$last_error = error_get_last();
			$error      = isset( $last_error['message'] ) ? (string) $last_error['message'] : 'Unknown stream error.';
			$body       = '';
		}

		if ( ! empty( $headers[0] ) && preg_match( '/\s(\d{3})\s/', $headers[0], $matches ) ) {
			$code = (int) $matches[1];
		}

		return array(
			'status_code' => $code,
			'body'        => (string) $body,
			'error'       => $error,
		);
	}
}
