<?php
/**
 * Snapshot audit report integrity generation and verification.
 *
 * @package ZignitesSentinel
 */

namespace Zignites\Sentinel\Admin;

defined( 'ABSPATH' ) || exit;

class AuditReportVerifier {

	/**
	 * Build integrity metadata for an audit report payload.
	 *
	 * @param array $report Structured report payload.
	 * @return array
	 */
	public function build_integrity( array $report ) {
		$payload_hash = hash( 'sha256', (string) wp_json_encode( $report ) );

		return array(
			'algorithm'      => 'sha256',
			'payload_hash'   => $payload_hash,
			'site_signature' => function_exists( 'wp_salt' ) ? hash_hmac( 'sha256', $payload_hash, wp_salt( 'auth' ) ) : '',
		);
	}

	/**
	 * Verify a pasted audit report payload for the selected snapshot.
	 *
	 * @param string $payload_text Raw JSON payload.
	 * @param int    $snapshot_id  Expected snapshot ID.
	 * @return array
	 */
	public function verify_payload( $payload_text, $snapshot_id ) {
		$decoded = json_decode( (string) $payload_text, true );
		$checks  = array();

		if ( ! is_array( $decoded ) ) {
			return array(
				'generated_at' => current_time( 'mysql', true ),
				'snapshot_id'  => absint( $snapshot_id ),
				'status'       => 'blocked',
				'summary'      => array(
					'pass'    => 0,
					'warning' => 0,
					'fail'    => 1,
				),
				'checks'       => array(
					array(
						'label'   => __( 'Report payload', 'zignites-sentinel' ),
						'status'  => 'fail',
						'message' => __( 'The provided audit report is not valid JSON.', 'zignites-sentinel' ),
					),
				),
				'note'         => __( 'Audit report verification failed.', 'zignites-sentinel' ),
			);
		}

		$report             = isset( $decoded['report'] ) && is_array( $decoded['report'] ) ? $decoded['report'] : array();
		$integrity          = isset( $decoded['integrity'] ) && is_array( $decoded['integrity'] ) ? $decoded['integrity'] : array();
		$algorithm          = isset( $integrity['algorithm'] ) ? sanitize_text_field( (string) $integrity['algorithm'] ) : '';
		$stored_hash        = isset( $integrity['payload_hash'] ) ? sanitize_text_field( (string) $integrity['payload_hash'] ) : '';
		$stored_signature   = isset( $integrity['site_signature'] ) ? sanitize_text_field( (string) $integrity['site_signature'] ) : '';
		$expected_id        = absint( $snapshot_id );
		$report_snapshot_id = isset( $report['snapshot']['id'] ) ? absint( $report['snapshot']['id'] ) : 0;
		$computed_hash      = ( 'sha256' === $algorithm && ! empty( $report ) ) ? hash( 'sha256', (string) wp_json_encode( $report ) ) : '';
		$computed_signature = ( 'sha256' === $algorithm && '' !== $computed_hash && function_exists( 'wp_salt' ) ) ? hash_hmac( 'sha256', $computed_hash, wp_salt( 'auth' ) ) : '';

		$checks[] = array(
			'label'   => __( 'Report envelope', 'zignites-sentinel' ),
			'status'  => ( ! empty( $report ) && ! empty( $integrity ) ) ? 'pass' : 'fail',
			'message' => ( ! empty( $report ) && ! empty( $integrity ) )
				? __( 'The audit report contains report and integrity sections.', 'zignites-sentinel' )
				: __( 'The audit report is missing report or integrity sections.', 'zignites-sentinel' ),
		);
		$checks[] = array(
			'label'   => __( 'Integrity algorithm', 'zignites-sentinel' ),
			'status'  => 'sha256' === $algorithm ? 'pass' : 'fail',
			'message' => 'sha256' === $algorithm
				? __( 'The audit report uses the supported SHA-256 integrity algorithm.', 'zignites-sentinel' )
				: __( 'The audit report uses an unsupported integrity algorithm.', 'zignites-sentinel' ),
		);
		$checks[] = array(
			'label'   => __( 'Snapshot match', 'zignites-sentinel' ),
			'status'  => $expected_id > 0 && $report_snapshot_id === $expected_id ? 'pass' : 'fail',
			'message' => $expected_id > 0 && $report_snapshot_id === $expected_id
				? __( 'The audit report matches the selected snapshot.', 'zignites-sentinel' )
				: __( 'The audit report does not match the selected snapshot.', 'zignites-sentinel' ),
		);
		$checks[] = array(
			'label'   => __( 'Payload hash', 'zignites-sentinel' ),
			'status'  => '' !== $computed_hash && hash_equals( $stored_hash, $computed_hash ) ? 'pass' : 'fail',
			'message' => '' !== $computed_hash && hash_equals( $stored_hash, $computed_hash )
				? __( 'The audit report payload hash matches.', 'zignites-sentinel' )
				: __( 'The audit report payload hash does not match.', 'zignites-sentinel' ),
		);
		$checks[] = array(
			'label'   => __( 'Site signature', 'zignites-sentinel' ),
			'status'  => '' !== $computed_signature && hash_equals( $stored_signature, $computed_signature ) ? 'pass' : 'fail',
			'message' => '' !== $computed_signature && hash_equals( $stored_signature, $computed_signature )
				? __( 'The audit report site signature matches this site.', 'zignites-sentinel' )
				: __( 'The audit report site signature does not match this site.', 'zignites-sentinel' ),
		);

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

		$status = ! empty( $summary['fail'] ) ? 'blocked' : ( ! empty( $summary['warning'] ) ? 'caution' : 'ready' );

		return array(
			'generated_at' => current_time( 'mysql', true ),
			'snapshot_id'  => $expected_id,
			'status'       => $status,
			'summary'      => $summary,
			'checks'       => $checks,
			'note'         => 'ready' === $status ? __( 'Audit report verification passed for this site and snapshot.', 'zignites-sentinel' ) : __( 'Audit report verification found integrity or snapshot mismatches.', 'zignites-sentinel' ),
		);
	}
}
