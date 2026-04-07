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
				'label'   => 'Selected Snapshot Detail',
				'resolve' => array(
					'path'       => 'admin.php?page=zignites-sentinel-update-readiness',
					'query_args' => array(
						'page'        => 'zignites-sentinel-update-readiness',
						'snapshot_id' => true,
					),
				),
				'markers' => array(
					'Snapshot Summary',
					'Download Summary',
					'Snapshot Health Baseline',
				),
				'optional_markers' => array(
					'Health Comparison',
					'Restore Impact Summary',
				),
			),
			array(
				'label'   => 'Selected Snapshot Event Logs',
				'resolve' => array(
					'path'       => 'admin.php?page=zignites-sentinel-update-readiness',
					'query_args' => array(
						'page'        => 'zignites-sentinel-event-logs',
						'snapshot_id' => true,
					),
				),
				'markers' => array(
					'Event Logs',
					'Export Filtered CSV',
					'Filter',
					'Current filters are active.',
				),
			),
			array(
				'label'            => 'Selected Snapshot Run Journal',
				'resolve'          => array(
					'path'       => 'admin.php?page=zignites-sentinel-update-readiness',
					'query_args' => array(
						'page'   => 'zignites-sentinel-event-logs',
						'source' => true,
						'run_id' => true,
					),
				),
				'resolve_optional' => true,
				'markers'          => array(
					'Event Logs',
					'Export Filtered CSV',
					'Filter',
					'Current filters are active.',
					'Run Journal',
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
				'label'   => 'Event Logs Empty State',
				'path'    => 'admin.php?page=zignites-sentinel-event-logs&log_search=znts-smoke-empty-state-token-9f3a0d66',
				'markers' => array(
					'Event Logs',
					'Export Filtered CSV',
					'Apply Filters',
					'Reset',
					'Current filters are active.',
					'No event logs match the current filters.',
				),
			),
			array(
				'label'            => 'Event Log Run Summary Journal',
				'resolve'          => array(
					'path'       => 'admin.php?page=zignites-sentinel-event-logs',
					'query_args' => array(
						'page'   => 'zignites-sentinel-event-logs',
						'source' => true,
						'run_id' => true,
					),
					'source_markers' => array(
						'Run Summaries',
					),
				),
				'resolve_optional' => true,
				'markers'          => array(
					'Event Logs',
					'Export Filtered CSV',
					'Filter',
					'Current filters are active.',
					'Run Journal',
				),
			),
			array(
				'label'            => 'Event Log Run Summary Snapshot',
				'resolve'          => array(
					'path'       => 'admin.php?page=zignites-sentinel-event-logs',
					'query_args' => array(
						'page'        => 'zignites-sentinel-update-readiness',
						'snapshot_id' => true,
					),
					'source_markers' => array(
						'Run Summaries',
					),
				),
				'resolve_optional' => true,
				'markers'          => array(
					'Update Readiness',
					'Recent Snapshot Metadata',
					'Sentinel Settings',
					'Snapshot Summary',
				),
			),
			array(
				'label'   => 'Dashboard Snapshot Event Logs',
				'resolve' => array(
					'path'       => 'admin.php?page=zignites-sentinel',
					'query_args' => array(
						'page'        => 'zignites-sentinel-event-logs',
						'snapshot_id' => true,
					),
				),
				'markers' => array(
					'Event Logs',
					'Export Filtered CSV',
					'Filter',
				),
			),
			array(
				'label'            => 'Widget Snapshot Activity',
				'resolve'          => array(
					'path'       => 'index.php',
					'query_args' => array(
						'page'        => 'zignites-sentinel-event-logs',
						'snapshot_id' => true,
					),
				),
				'resolve_optional' => true,
				'markers'          => array(
					'Event Logs',
					'Export Filtered CSV',
					'Filter',
					'Current filters are active.',
				),
			),
			array(
				'label'   => 'WordPress Dashboard Widget',
				'path'    => 'index.php',
				'markers' => array(
					'Sentinel',
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
		$path = trim( (string) $path );

		if ( preg_match( '#^https?://#i', $path ) ) {
			return $path;
		}

		$base_url = $this->normalize_base_url( $base_url );
		$path     = ltrim( $path, '/' );

		return $base_url . $path;
	}

	/**
	 * Resolve a smoke check to a concrete request URL.
	 *
	 * @param array  $check         Check definition.
	 * @param string $base_url      Base wp-admin URL.
	 * @param string $cookie_header Browser cookie header value.
	 * @param int    $timeout       Timeout in seconds.
	 * @return array
	 */
	public function resolve_check( array $check, $base_url, $cookie_header, $timeout = 20 ) {
		$path             = isset( $check['path'] ) ? (string) $check['path'] : '';
		$resolve_optional = ! empty( $check['resolve_optional'] );

		if ( empty( $check['resolve'] ) || ! is_array( $check['resolve'] ) ) {
			return array(
				'url'                  => $this->build_url( $base_url, $path ),
				'path'                 => $path,
				'source_url'           => '',
				'source_status_code'   => 0,
				'source_error'         => '',
				'source_auth_fallback' => false,
				'resolve_error'        => '',
				'skipped'              => false,
				'skip_reason'          => '',
			);
		}

		$resolve     = $check['resolve'];
		$source_path = isset( $resolve['path'] ) ? (string) $resolve['path'] : $path;
		$source_url  = $this->build_url( $base_url, $source_path );
		$http        = $this->fetch( $source_url, $cookie_header, $timeout );
		$body        = isset( $http['body'] ) ? (string) $http['body'] : '';
		$source_code = isset( $http['status_code'] ) ? (int) $http['status_code'] : 0;
		$source_auth = $this->detect_login_fallback( $body );
		$query_args            = isset( $resolve['query_args'] ) && is_array( $resolve['query_args'] ) ? $resolve['query_args'] : array();
		$source_markers        = isset( $resolve['source_markers'] ) && is_array( $resolve['source_markers'] ) ? $resolve['source_markers'] : array();
		$target_path           = $this->find_link_by_query_args( $body, $query_args );
		$source_missing_markers = array();
		$error                 = '';

		if ( '' !== $target_path ) {
			foreach ( $source_markers as $marker ) {
				if ( false === stripos( $body, (string) $marker ) ) {
					$source_missing_markers[] = (string) $marker;
				}
			}
		}

		if ( '' !== (string) ( isset( $http['error'] ) ? $http['error'] : '' ) ) {
			$error = (string) $http['error'];
		} elseif ( $source_auth ) {
			$error = 'Source page resolved to wp-login.';
		} elseif ( 200 !== $source_code ) {
			$error = 'Source page returned HTTP ' . $source_code . '.';
		} elseif ( ! empty( $source_missing_markers ) ) {
			$error = 'Source page missing markers: ' . implode( ', ', $source_missing_markers ) . '.';
		} elseif ( '' === $target_path && ! $resolve_optional ) {
			$error = 'Could not resolve a matching admin link from the source page.';
		}

		return array(
			'url'                  => '' !== $target_path ? $this->build_url( $base_url, $target_path ) : '',
			'path'                 => $target_path,
			'source_url'           => $source_url,
			'source_status_code'   => $source_code,
			'source_error'         => isset( $http['error'] ) ? (string) $http['error'] : '',
			'source_auth_fallback' => $source_auth,
			'source_missing_markers' => $source_missing_markers,
			'resolve_error'        => $error,
			'skipped'              => '' === $error && '' === $target_path && $resolve_optional,
			'skip_reason'          => '' === $error && '' === $target_path && $resolve_optional ? 'No matching optional admin link was present on the source page.' : '',
		);
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
		$body                     = (string) $body;
		$status_code              = (int) $status_code;
		$missing_markers          = array();
		$observed_optional_markers = array();
		$missing_optional_markers = array();
		$markers                  = isset( $check['markers'] ) && is_array( $check['markers'] ) ? $check['markers'] : array();
		$optional_markers         = isset( $check['optional_markers'] ) && is_array( $check['optional_markers'] ) ? $check['optional_markers'] : array();
		$auth_fallback            = $this->detect_login_fallback( $body );

		foreach ( $markers as $marker ) {
			if ( false === stripos( $body, (string) $marker ) ) {
				$missing_markers[] = (string) $marker;
			}
		}

		foreach ( $optional_markers as $marker ) {
			if ( false === stripos( $body, (string) $marker ) ) {
				$missing_optional_markers[] = (string) $marker;
				continue;
			}

			$observed_optional_markers[] = (string) $marker;
		}

		return array(
			'label'                   => isset( $check['label'] ) ? (string) $check['label'] : 'Smoke check',
			'path'                    => isset( $check['path'] ) ? (string) $check['path'] : '',
			'status_code'             => $status_code,
			'missing_markers'         => $missing_markers,
			'observed_optional_markers' => $observed_optional_markers,
			'missing_optional_markers' => $missing_optional_markers,
			'auth_fallback'           => $auth_fallback,
			'passed'                  => 200 === $status_code && empty( $missing_markers ) && ! $auth_fallback,
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

	/**
	 * Find the first link whose query args match the requested values.
	 *
	 * @param string $body                Response body.
	 * @param array  $required_query_args Query args to match.
	 * @return string
	 */
	protected function find_link_by_query_args( $body, array $required_query_args ) {
		foreach ( $this->extract_href_values( $body ) as $href ) {
			$query_string = (string) parse_url( $href, PHP_URL_QUERY );

			if ( '' === $query_string ) {
				continue;
			}

			parse_str( $query_string, $query_args );

			if ( $this->query_args_match( $query_args, $required_query_args ) ) {
				return $href;
			}
		}

		return '';
	}

	/**
	 * Extract href attribute values from HTML.
	 *
	 * @param string $body Response body.
	 * @return array
	 */
	protected function extract_href_values( $body ) {
		$matches = array();
		$values  = array();

		if ( ! preg_match_all( '/href=(["\'])(.*?)\1/i', (string) $body, $matches ) ) {
			return array();
		}

		foreach ( isset( $matches[2] ) && is_array( $matches[2] ) ? $matches[2] : array() as $href ) {
			$decoded = html_entity_decode( (string) $href, ENT_QUOTES, 'UTF-8' );

			if ( '' !== $decoded ) {
				$values[] = $decoded;
			}
		}

		return $values;
	}

	/**
	 * Determine whether parsed query args satisfy a required set.
	 *
	 * @param array $query_args          Parsed query args.
	 * @param array $required_query_args Query args to match.
	 * @return bool
	 */
	protected function query_args_match( array $query_args, array $required_query_args ) {
		foreach ( $required_query_args as $key => $expected_value ) {
			if ( ! array_key_exists( $key, $query_args ) ) {
				return false;
			}

			if ( true === $expected_value || null === $expected_value || '*' === $expected_value ) {
				if ( '' === trim( (string) $query_args[ $key ] ) ) {
					return false;
				}

				continue;
			}

			if ( (string) $query_args[ $key ] !== (string) $expected_value ) {
				return false;
			}
		}

		return true;
	}
}
