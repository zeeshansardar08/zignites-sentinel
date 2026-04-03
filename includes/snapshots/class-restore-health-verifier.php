<?php
/**
 * Post-restore health verification.
 *
 * @package ZignitesSentinel
 */

namespace Zignites\Sentinel\Snapshots;

defined( 'ABSPATH' ) || exit;

class RestoreHealthVerifier {

	/**
	 * Verify basic site health immediately after restore execution.
	 *
	 * @param array $snapshot Snapshot row.
	 * @return array
	 */
	public function verify( array $snapshot ) {
		$checks    = array();
		$endpoints = array(
			array(
				'url'               => home_url( '/' ),
				'label'             => __( 'Front-end', 'zignites-sentinel' ),
				'allow_empty_body'  => false,
				'expected_types'    => array( 'html', 'text' ),
			),
			array(
				'url'               => wp_login_url(),
				'label'             => __( 'Login', 'zignites-sentinel' ),
				'allow_empty_body'  => false,
				'expected_types'    => array( 'html', 'text' ),
			),
			array(
				'url'               => rest_url(),
				'label'             => __( 'REST API', 'zignites-sentinel' ),
				'allow_empty_body'  => false,
				'expected_types'    => array( 'json' ),
			),
		);

		foreach ( $endpoints as $endpoint ) {
			foreach ( $this->verify_endpoint( $endpoint ) as $check ) {
				$checks[] = $check;
			}
		}

		return $this->finalize_result( $checks );
	}

	/**
	 * Verify a single endpoint.
	 *
	 * @param array $endpoint Endpoint descriptor.
	 * @return array
	 */
	protected function verify_endpoint( array $endpoint ) {
		$url = isset( $endpoint['url'] ) ? (string) $endpoint['url'] : '';
		$label = isset( $endpoint['label'] ) ? (string) $endpoint['label'] : __( 'Endpoint', 'zignites-sentinel' );
		$checks = array();

		$checks[] = array(
			'label'   => sprintf(
				/* translators: %s = endpoint label */
				__( '%s target', 'zignites-sentinel' ),
				$label
			),
			'status'  => '' !== $url ? 'pass' : 'fail',
			'message' => '' !== $url
				? sprintf(
					/* translators: 1: endpoint label, 2: url */
					__( '%1$s verification will request %2$s', 'zignites-sentinel' ),
					$label,
					$url
				)
				: sprintf(
					/* translators: %s = endpoint label */
					__( 'The %s URL could not be determined for health verification.', 'zignites-sentinel' ),
					strtolower( $label )
				),
		);

		if ( '' === $url ) {
			return $checks;
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => 10,
				'redirection' => 3,
				'user-agent'  => 'ZignitesSentinel/1.5.0',
			)
		);

		if ( is_wp_error( $response ) ) {
			$checks[] = array(
				'label'   => sprintf(
					/* translators: %s = endpoint label */
					__( '%s request', 'zignites-sentinel' ),
					$label
				),
				'status'  => 'fail',
				'message' => $response->get_error_message(),
			);

			return $checks;
		}

		$status_code  = (int) wp_remote_retrieve_response_code( $response );
		$body         = (string) wp_remote_retrieve_body( $response );
		$content_type = (string) wp_remote_retrieve_header( $response, 'content-type' );

		$checks[] = array(
			'label'   => sprintf(
				/* translators: %s = endpoint label */
				__( '%s response code', 'zignites-sentinel' ),
				$label
			),
			'status'  => $status_code >= 200 && $status_code < 400 ? 'pass' : 'fail',
			'message' => sprintf(
				/* translators: 1: endpoint label, 2: status code */
				__( '%1$s returned HTTP %2$d.', 'zignites-sentinel' ),
				$label,
				$status_code
			),
		);

		$checks[] = array(
			'label'   => sprintf(
				/* translators: %s = endpoint label */
				__( '%s response body', 'zignites-sentinel' ),
				$label
			),
			'status'  => ! empty( $endpoint['allow_empty_body'] ) || '' !== trim( wp_strip_all_tags( $body ) ) ? 'pass' : 'warning',
			'message' => '' !== trim( wp_strip_all_tags( $body ) )
				? sprintf(
					/* translators: %s = endpoint label */
					__( '%s returned non-empty content.', 'zignites-sentinel' ),
					$label
				)
				: sprintf(
					/* translators: %s = endpoint label */
					__( '%s returned an empty or nearly empty response body.', 'zignites-sentinel' ),
					$label
				),
		);

		$checks[] = array(
			'label'   => sprintf(
				/* translators: %s = endpoint label */
				__( '%s content type', 'zignites-sentinel' ),
				$label
			),
			'status'  => $this->matches_expected_content_type( $content_type, isset( $endpoint['expected_types'] ) ? (array) $endpoint['expected_types'] : array() ) ? 'pass' : 'warning',
			'message' => '' !== $content_type
				? sprintf(
					/* translators: 1: endpoint label, 2: content type */
					__( '%1$s returned content type %2$s.', 'zignites-sentinel' ),
					$label,
					$content_type
				)
				: sprintf(
					/* translators: %s = endpoint label */
					__( '%s did not return a content type header.', 'zignites-sentinel' ),
					$label
				),
		);

		if ( $this->contains_fatal_signature( $body ) ) {
			$checks[] = array(
				'label'   => sprintf(
					/* translators: %s = endpoint label */
					__( '%s fatal signature scan', 'zignites-sentinel' ),
					$label
				),
				'status'  => 'fail',
				'message' => sprintf(
					/* translators: %s = endpoint label */
					__( '%s appears to contain a fatal error signature.', 'zignites-sentinel' ),
					$label
				),
			);
		} else {
			$checks[] = array(
				'label'   => sprintf(
					/* translators: %s = endpoint label */
					__( '%s fatal signature scan', 'zignites-sentinel' ),
					$label
				),
				'status'  => 'pass',
				'message' => sprintf(
					/* translators: %s = endpoint label */
					__( 'No obvious fatal error signature was found in the %s response.', 'zignites-sentinel' ),
					strtolower( $label )
				),
			);
		}

		return $checks;
	}

	/**
	 * Finalize health verification result.
	 *
	 * @param array $checks Verification checks.
	 * @return array
	 */
	protected function finalize_result( array $checks ) {
		$summary = array(
			'pass'    => 0,
			'warning' => 0,
			'fail'    => 0,
		);

		foreach ( $checks as $check ) {
			if ( isset( $summary[ $check['status'] ] ) ) {
				++$summary[ $check['status'] ];
			}
		}

		$status = 'healthy';

		if ( ! empty( $summary['fail'] ) ) {
			$status = 'unhealthy';
		} elseif ( ! empty( $summary['warning'] ) ) {
			$status = 'degraded';
		}

		return array(
			'generated_at' => current_time( 'mysql', true ),
			'status'       => $status,
			'checks'       => $checks,
			'summary'      => $summary,
			'note'         => $this->build_note( $status ),
		);
	}

	/**
	 * Build a health verification note.
	 *
	 * @param string $status Verification status.
	 * @return string
	 */
	protected function build_note( $status ) {
		if ( 'unhealthy' === $status ) {
			return __( 'The site did not pass basic post-restore health verification.', 'zignites-sentinel' );
		}

		if ( 'degraded' === $status ) {
			return __( 'The site responded after restore execution, but the health verification detected warnings.', 'zignites-sentinel' );
		}

		return __( 'The site passed the basic post-restore health verification checks.', 'zignites-sentinel' );
	}

	/**
	 * Determine whether a response content type matches expectations.
	 *
	 * @param string $content_type   Response content type.
	 * @param array  $expected_types Expected type fragments.
	 * @return bool
	 */
	protected function matches_expected_content_type( $content_type, array $expected_types ) {
		if ( '' === $content_type || empty( $expected_types ) ) {
			return false;
		}

		$content_type = strtolower( $content_type );

		foreach ( $expected_types as $expected ) {
			if ( false !== strpos( $content_type, strtolower( (string) $expected ) ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Detect common fatal error signatures in a response body.
	 *
	 * @param string $body Response body.
	 * @return bool
	 */
	protected function contains_fatal_signature( $body ) {
		$body = strtolower( (string) $body );

		return false !== strpos( $body, 'fatal error' )
			|| false !== strpos( $body, 'there has been a critical error' )
			|| false !== strpos( $body, 'uncaught error' )
			|| false !== strpos( $body, 'parse error' );
	}
}
