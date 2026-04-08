<?php
/**
 * Read-only helper for snapshot health comparison state assembly.
 *
 * @package ZignitesSentinel
 */

namespace Zignites\Sentinel\Admin;

defined( 'ABSPATH' ) || exit;

class HealthComparisonStateBuilder {

	/**
	 * Build the normalized state used by the health comparison presenter.
	 *
	 * @param array|null $snapshot  Snapshot detail.
	 * @param array|null $baseline  Baseline health payload.
	 * @param array|null $execution Last restore execution payload.
	 * @param array|null $rollback  Last restore rollback payload.
	 * @return array
	 */
	public function build_comparison_state( $snapshot, $baseline, $execution, $rollback ) {
		if ( ! is_array( $snapshot ) || empty( $snapshot['id'] ) ) {
			return array();
		}

		return array(
			'baseline'  => is_array( $baseline ) ? $baseline : null,
			'execution' => is_array( $execution ) ? $execution : array(),
			'rollback'  => is_array( $rollback ) ? $rollback : array(),
		);
	}
}
