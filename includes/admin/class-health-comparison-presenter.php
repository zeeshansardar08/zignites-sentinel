<?php
/**
 * Read-only presentation helper for snapshot health comparison payloads.
 *
 * @package ZignitesSentinel
 */

namespace Zignites\Sentinel\Admin;

defined( 'ABSPATH' ) || exit;

class HealthComparisonPresenter {

	/**
	 * Shared status presenter.
	 *
	 * @var StatusPresenter
	 */
	protected $status_presenter;

	/**
	 * Constructor.
	 *
	 * @param StatusPresenter|null $status_presenter Optional shared status presenter.
	 */
	public function __construct( StatusPresenter $status_presenter = null ) {
		$this->status_presenter = $status_presenter ? $status_presenter : new StatusPresenter();
	}

	/**
	 * Build the full health comparison row set for a snapshot.
	 *
	 * @param array|null $baseline  Baseline health payload.
	 * @param array|null $execution Last execution payload.
	 * @param array|null $rollback  Last rollback payload.
	 * @return array
	 */
	public function build_comparison( $baseline, $execution, $rollback ) {
		$rows = array();

		if ( is_array( $baseline ) ) {
			$rows[] = $this->build_row( __( 'Baseline', 'zignites-sentinel' ), $baseline, array() );
		}

		if ( is_array( $execution ) && ! empty( $execution['health_verification'] ) && is_array( $execution['health_verification'] ) ) {
			$rows[] = $this->build_row( __( 'Post-Restore', 'zignites-sentinel' ), $execution['health_verification'], is_array( $baseline ) ? $baseline : array() );
		}

		if ( is_array( $rollback ) && ! empty( $rollback['health_verification'] ) && is_array( $rollback['health_verification'] ) ) {
			$rows[] = $this->build_row( __( 'Post-Rollback', 'zignites-sentinel' ), $rollback['health_verification'], is_array( $baseline ) ? $baseline : array() );
		}

		return $rows;
	}

	/**
	 * Build a normalized health comparison row.
	 *
	 * @param string $label    Row label.
	 * @param array  $health   Health result.
	 * @param array  $baseline Baseline result.
	 * @return array
	 */
	public function build_row( $label, array $health, array $baseline = array() ) {
		$summary          = isset( $health['summary'] ) && is_array( $health['summary'] ) ? $health['summary'] : array();
		$baseline_summary = isset( $baseline['summary'] ) && is_array( $baseline['summary'] ) ? $baseline['summary'] : array();
		$status_payload   = $this->status_presenter->present_health( isset( $health['status'] ) ? $health['status'] : '' );

		return array(
			'label'        => $label,
			'status'       => isset( $health['status'] ) ? (string) $health['status'] : '',
			'status_pill'  => isset( $status_payload['pill'] ) ? (string) $status_payload['pill'] : 'info',
			'status_label' => isset( $status_payload['label'] ) ? (string) $status_payload['label'] : '',
			'generated_at' => isset( $health['generated_at'] ) ? (string) $health['generated_at'] : '',
			'summary'      => array(
				'pass'    => isset( $summary['pass'] ) ? (int) $summary['pass'] : 0,
				'warning' => isset( $summary['warning'] ) ? (int) $summary['warning'] : 0,
				'fail'    => isset( $summary['fail'] ) ? (int) $summary['fail'] : 0,
			),
			'delta'        => empty( $baseline_summary ) ? '' : $this->build_delta_summary( $summary, $baseline_summary ),
			'note'         => isset( $health['note'] ) ? (string) $health['note'] : '',
		);
	}

	/**
	 * Build a compact delta summary against the baseline health summary.
	 *
	 * @param array $summary          Current summary.
	 * @param array $baseline_summary Baseline summary.
	 * @return string
	 */
	public function build_delta_summary( array $summary, array $baseline_summary ) {
		$parts = array();

		foreach ( array( 'pass', 'warning', 'fail' ) as $key ) {
			$delta = ( isset( $summary[ $key ] ) ? (int) $summary[ $key ] : 0 ) - ( isset( $baseline_summary[ $key ] ) ? (int) $baseline_summary[ $key ] : 0 );

			if ( 0 === $delta ) {
				continue;
			}

			$parts[] = sprintf( '%s %s%d', $key, $delta > 0 ? '+' : '', $delta );
		}

		return empty( $parts ) ? __( 'No change', 'zignites-sentinel' ) : implode( ', ', $parts );
	}
}
