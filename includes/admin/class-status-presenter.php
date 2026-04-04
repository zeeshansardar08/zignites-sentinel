<?php
/**
 * Shared read-only status formatting for admin presentation.
 *
 * @package ZignitesSentinel
 */

namespace Zignites\Sentinel\Admin;

defined( 'ABSPATH' ) || exit;

class StatusPresenter {

	/**
	 * Present a readiness-style status.
	 *
	 * @param string $status Status value.
	 * @return array
	 */
	public function present_readiness( $status ) {
		$status = sanitize_key( (string) $status );

		if ( in_array( $status, array( 'blocked', 'fail', 'critical' ), true ) ) {
			return $this->build_presented_status( $status, 'critical' );
		}

		if ( in_array( $status, array( 'caution', 'warning', 'partial' ), true ) ) {
			return $this->build_presented_status( $status, 'warning' );
		}

		return $this->build_presented_status( $status, 'info' );
	}

	/**
	 * Present a run-style status.
	 *
	 * @param string $status Status value.
	 * @return array
	 */
	public function present_run( $status ) {
		return $this->present_readiness( $status );
	}

	/**
	 * Present a health-style status.
	 *
	 * @param string $status Status value.
	 * @return array
	 */
	public function present_health( $status ) {
		$status = sanitize_key( (string) $status );

		if ( 'unhealthy' === $status ) {
			return $this->build_presented_status( $status, 'critical' );
		}

		if ( 'degraded' === $status ) {
			return $this->build_presented_status( $status, 'warning' );
		}

		return $this->build_presented_status( $status, 'info' );
	}

	/**
	 * Present a native severity value.
	 *
	 * @param string $severity Severity value.
	 * @return array
	 */
	public function present_severity( $severity ) {
		$severity = sanitize_key( (string) $severity );

		if ( ! in_array( $severity, array( 'info', 'warning', 'error', 'critical' ), true ) ) {
			$severity = 'info';
		}

		return $this->build_presented_status( $severity, $severity );
	}

	/**
	 * Format a readable status label.
	 *
	 * @param string $status Status value.
	 * @return string
	 */
	public function format_status_label( $status ) {
		$status = (string) $status;

		if ( '' === $status ) {
			return '';
		}

		return ucfirst( str_replace( '_', ' ', $status ) );
	}

	/**
	 * Build a presented status payload.
	 *
	 * @param string $status Status value.
	 * @param string $pill   Pill class suffix.
	 * @return array
	 */
	protected function build_presented_status( $status, $pill ) {
		return array(
			'value' => (string) $status,
			'pill'  => sanitize_html_class( (string) $pill ),
			'label' => $this->format_status_label( $status ),
		);
	}
}
