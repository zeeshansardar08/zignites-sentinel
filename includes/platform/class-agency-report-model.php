<?php
/**
 * White-label-ready agency report payload foundations.
 *
 * @package ZignitesSentinel
 */

namespace Zignites\Sentinel\Platform;

defined( 'ABSPATH' ) || exit;

class AgencyReportModel {

	/**
	 * Build a structured agency report payload from existing operational state.
	 *
	 * @param array $snapshot           Snapshot payload.
	 * @param array $safe_update_window Safe Update Window state.
	 * @param array $site_status        Local platform status payload.
	 * @param array $options            Report options.
	 * @return array
	 */
	public function build_report( array $snapshot, array $safe_update_window, array $site_status, array $options = array() ) {
		$agency = $this->normalize_agency_options( $options );
		$health = isset( $safe_update_window['health'] ) && is_array( $safe_update_window['health'] ) ? $safe_update_window['health'] : array();

		return array(
			'schema_version' => '1.0',
			'generated_at'   => current_time( 'mysql', true ),
			'agency'         => $agency,
			'site'           => isset( $site_status['site'] ) && is_array( $site_status['site'] ) ? $site_status['site'] : array(),
			'snapshot'       => $this->build_snapshot_summary( $snapshot ),
			'update_window'  => $this->build_update_window_summary( $safe_update_window, $health ),
			'health'         => $this->build_health_summary( $health, $site_status ),
			'storage'        => isset( $site_status['storage'] ) && is_array( $site_status['storage'] ) ? $site_status['storage'] : array(),
			'warnings'       => isset( $site_status['warnings'] ) && is_array( $site_status['warnings'] ) ? $site_status['warnings'] : array(),
			'boundaries'     => $this->build_boundaries(),
		);
	}

	/**
	 * Render an exportable plain-text agency report.
	 *
	 * @param array $report Structured report payload.
	 * @return string
	 */
	public function render_text( array $report ) {
		$agency        = isset( $report['agency'] ) && is_array( $report['agency'] ) ? $report['agency'] : array();
		$site          = isset( $report['site'] ) && is_array( $report['site'] ) ? $report['site'] : array();
		$snapshot      = isset( $report['snapshot'] ) && is_array( $report['snapshot'] ) ? $report['snapshot'] : array();
		$update_window = isset( $report['update_window'] ) && is_array( $report['update_window'] ) ? $report['update_window'] : array();
		$health        = isset( $report['health'] ) && is_array( $report['health'] ) ? $report['health'] : array();
		$warnings      = isset( $report['warnings'] ) && is_array( $report['warnings'] ) ? $report['warnings'] : array();
		$boundaries    = isset( $report['boundaries'] ) && is_array( $report['boundaries'] ) ? $report['boundaries'] : array();

		$lines = array(
			isset( $agency['report_title'] ) && '' !== $agency['report_title'] ? $agency['report_title'] : 'Safe Update Window Report',
			'Generated: ' . ( isset( $report['generated_at'] ) ? (string) $report['generated_at'] : '' ),
			'Prepared by: ' . ( isset( $agency['name'] ) && '' !== $agency['name'] ? $agency['name'] : 'Zignites Sentinel' ),
			'Site: ' . ( isset( $site['url'] ) ? (string) $site['url'] : '' ),
			'Checkpoint: ' . ( isset( $snapshot['label'] ) ? (string) $snapshot['label'] : '' ),
			'Checkpoint ID: ' . ( isset( $snapshot['id'] ) ? (int) $snapshot['id'] : 0 ),
			'Update window status: ' . ( isset( $update_window['status'] ) ? $this->humanize_status( $update_window['status'] ) : 'Not run' ),
			'Operator confirmed: ' . ( ! empty( $update_window['confirmed'] ) ? 'Yes' : 'No' ),
			'Health: ' . ( isset( $health['status'] ) ? $this->humanize_status( $health['status'] ) : 'Not run' ),
			'Health summary: pass ' . ( isset( $health['summary']['pass'] ) ? (int) $health['summary']['pass'] : 0 ) . ', warning ' . ( isset( $health['summary']['warning'] ) ? (int) $health['summary']['warning'] : 0 ) . ', fail ' . ( isset( $health['summary']['fail'] ) ? (int) $health['summary']['fail'] : 0 ),
		);

		if ( ! empty( $warnings ) ) {
			$lines[] = '';
			$lines[] = 'Warnings';
			foreach ( $warnings as $warning ) {
				if ( is_array( $warning ) && ! empty( $warning['message'] ) ) {
					$lines[] = '- ' . (string) $warning['message'];
				}
			}
		}

		$lines[] = '';
		$lines[] = 'Boundaries';
		foreach ( $boundaries as $boundary ) {
			$lines[] = '- ' . (string) $boundary;
		}

		return implode( "\n", $lines ) . "\n";
	}

	/**
	 * Normalize white-label options.
	 *
	 * @param array $options Raw options.
	 * @return array
	 */
	protected function normalize_agency_options( array $options ) {
		return array(
			'name'         => isset( $options['agency_name'] ) ? sanitize_text_field( (string) $options['agency_name'] ) : '',
			'logo_url'     => isset( $options['agency_logo_url'] ) ? esc_url_raw( (string) $options['agency_logo_url'] ) : '',
			'contact'      => isset( $options['agency_contact'] ) ? sanitize_text_field( (string) $options['agency_contact'] ) : '',
			'report_title' => isset( $options['report_title'] ) && '' !== trim( (string) $options['report_title'] )
				? sanitize_text_field( (string) $options['report_title'] )
				: __( 'Safe Update Window Report', 'zignites-sentinel' ),
		);
	}

	/**
	 * Build compact snapshot summary.
	 *
	 * @param array $snapshot Snapshot payload.
	 * @return array
	 */
	protected function build_snapshot_summary( array $snapshot ) {
		return array(
			'id'         => isset( $snapshot['id'] ) ? absint( $snapshot['id'] ) : 0,
			'label'      => isset( $snapshot['label'] ) ? sanitize_text_field( (string) $snapshot['label'] ) : '',
			'created_at' => isset( $snapshot['created_at'] ) ? sanitize_text_field( (string) $snapshot['created_at'] ) : '',
			'status'     => isset( $snapshot['status'] ) ? sanitize_key( (string) $snapshot['status'] ) : '',
		);
	}

	/**
	 * Build update-window summary.
	 *
	 * @param array $safe_update_window Safe Update Window state.
	 * @param array $health             Health payload.
	 * @return array
	 */
	protected function build_update_window_summary( array $safe_update_window, array $health ) {
		return array(
			'status'       => isset( $health['status'] ) ? sanitize_key( (string) $health['status'] ) : ( isset( $safe_update_window['status'] ) ? sanitize_key( (string) $safe_update_window['status'] ) : '' ),
			'confirmed'    => ! empty( $safe_update_window['confirmed'] ),
			'checked_at'   => isset( $safe_update_window['checked_at'] ) ? sanitize_text_field( (string) $safe_update_window['checked_at'] ) : '',
			'completed_at' => isset( $safe_update_window['completed_at'] ) ? sanitize_text_field( (string) $safe_update_window['completed_at'] ) : '',
		);
	}

	/**
	 * Build health summary.
	 *
	 * @param array $health      Health payload.
	 * @param array $site_status Site status payload.
	 * @return array
	 */
	protected function build_health_summary( array $health, array $site_status ) {
		$summary = isset( $health['summary'] ) && is_array( $health['summary'] ) ? $health['summary'] : array();

		return array(
			'status'  => isset( $health['status'] ) ? sanitize_key( (string) $health['status'] ) : ( isset( $site_status['health']['status'] ) ? sanitize_key( (string) $site_status['health']['status'] ) : '' ),
			'score'   => isset( $site_status['health']['score'] ) ? (int) $site_status['health']['score'] : 0,
			'summary' => array(
				'pass'    => isset( $summary['pass'] ) ? absint( $summary['pass'] ) : 0,
				'warning' => isset( $summary['warning'] ) ? absint( $summary['warning'] ) : 0,
				'fail'    => isset( $summary['fail'] ) ? absint( $summary['fail'] ) : 0,
			),
		);
	}

	/**
	 * Build stable limitation lines for client reports.
	 *
	 * @return array
	 */
	protected function build_boundaries() {
		return array(
			__( 'Sentinel covers active plugin/theme code checkpoints only.', 'zignites-sentinel' ),
			__( 'Sentinel does not restore database, uploads/media, WordPress core, WooCommerce orders/payments, or malware cleanup state.', 'zignites-sentinel' ),
			__( 'Review failed health checks, storage exposure warnings, and recent error logs before closing the maintenance window.', 'zignites-sentinel' ),
		);
	}

	/**
	 * Convert a status key to a readable label.
	 *
	 * @param string $status Status key.
	 * @return string
	 */
	protected function humanize_status( $status ) {
		$status = trim( str_replace( array( '-', '_' ), ' ', (string) $status ) );

		return '' === $status ? '' : ucwords( $status );
	}
}
