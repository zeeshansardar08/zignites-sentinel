<?php
/**
 * Advisory restore-readiness evaluation for stored snapshots.
 *
 * @package ZignitesSentinel
 */

namespace Zignites\Sentinel\Snapshots;

use Zignites\Sentinel\Updates\SourceValidator;

defined( 'ABSPATH' ) || exit;

class RestoreReadinessChecker {

	/**
	 * Comparator service.
	 *
	 * @var SnapshotComparator
	 */
	protected $comparator;

	/**
	 * Source validator.
	 *
	 * @var SourceValidator
	 */
	protected $source_validator;

	/**
	 * Constructor.
	 *
	 * @param SnapshotComparator $comparator       Snapshot comparator.
	 * @param SourceValidator    $source_validator Source validator.
	 */
	public function __construct( SnapshotComparator $comparator, SourceValidator $source_validator ) {
		$this->comparator       = $comparator;
		$this->source_validator = $source_validator;
	}

	/**
	 * Evaluate restore readiness for a snapshot.
	 *
	 * @param array $snapshot Snapshot row.
	 * @return array
	 */
	public function assess( array $snapshot ) {
		$comparison        = $this->comparator->compare( $snapshot );
		$source_validation = $this->source_validator->validate_snapshot_sources( $snapshot );
		$checks            = array(
			$this->check_snapshot_integrity( $snapshot ),
			$this->check_filesystem_state(),
			$this->check_theme_availability( $comparison ),
			$this->check_plugin_drift( $comparison ),
			$this->check_runtime_drift( $comparison ),
			$this->check_source_availability( $source_validation ),
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

		return array(
			'generated_at' => current_time( 'mysql', true ),
			'status'       => $this->determine_status( $summary ),
			'summary'      => $summary,
			'checks'       => $checks,
			'comparison'   => $comparison,
			'source_validation' => $source_validation,
			'note'         => $this->build_note( $summary ),
		);
	}

	/**
	 * Check whether the snapshot contains core metadata.
	 *
	 * @param array $snapshot Snapshot row.
	 * @return array
	 */
	protected function check_snapshot_integrity( array $snapshot ) {
		$has_plugins = isset( $snapshot['active_plugins'] ) && '' !== (string) $snapshot['active_plugins'];
		$has_theme   = ! empty( $snapshot['theme_stylesheet'] );
		$has_core    = ! empty( $snapshot['core_version'] );

		if ( ! $has_plugins || ! $has_theme || ! $has_core ) {
			return array(
				'key'     => 'snapshot_integrity',
				'label'   => __( 'Snapshot completeness', 'zignites-sentinel' ),
				'status'  => 'fail',
				'message' => __( 'The snapshot does not contain enough metadata for meaningful restore-readiness analysis.', 'zignites-sentinel' ),
			);
		}

		return array(
			'key'     => 'snapshot_integrity',
			'label'   => __( 'Snapshot completeness', 'zignites-sentinel' ),
			'status'  => 'pass',
			'message' => __( 'Snapshot metadata is complete enough for advisory comparison.', 'zignites-sentinel' ),
		);
	}

	/**
	 * Check filesystem write access.
	 *
	 * @return array
	 */
	protected function check_filesystem_state() {
		if ( ! wp_is_writable( WP_CONTENT_DIR ) || ! wp_is_writable( WP_PLUGIN_DIR ) ) {
			return array(
				'key'     => 'filesystem_state',
				'label'   => __( 'Filesystem readiness', 'zignites-sentinel' ),
				'status'  => 'fail',
				'message' => __( 'The WordPress content directories are not writable enough for controlled file operations.', 'zignites-sentinel' ),
			);
		}

		return array(
			'key'     => 'filesystem_state',
			'label'   => __( 'Filesystem readiness', 'zignites-sentinel' ),
			'status'  => 'pass',
			'message' => __( 'Content directories appear writable for future controlled file operations.', 'zignites-sentinel' ),
		);
	}

	/**
	 * Check whether the snapshot theme differs from the current theme.
	 *
	 * @param array $comparison Comparison data.
	 * @return array
	 */
	protected function check_theme_availability( array $comparison ) {
		if ( ! empty( $comparison['theme_changed'] ) ) {
			return array(
				'key'     => 'theme_alignment',
				'label'   => __( 'Theme alignment', 'zignites-sentinel' ),
				'status'  => 'warning',
				'message' => __( 'The active theme differs from the snapshot theme. Restoring site state later may require theme review.', 'zignites-sentinel' ),
			);
		}

		return array(
			'key'     => 'theme_alignment',
			'label'   => __( 'Theme alignment', 'zignites-sentinel' ),
			'status'  => 'pass',
			'message' => __( 'The current active theme matches the snapshot theme.', 'zignites-sentinel' ),
		);
	}

	/**
	 * Check plugin drift between the snapshot and the current site.
	 *
	 * @param array $comparison Comparison data.
	 * @return array
	 */
	protected function check_plugin_drift( array $comparison ) {
		$missing_count = count( $comparison['missing_plugins'] );
		$new_count     = count( $comparison['new_plugins'] );
		$changed_count = count( $comparison['version_changes'] );

		if ( $missing_count > 0 ) {
			return array(
				'key'     => 'plugin_drift',
				'label'   => __( 'Plugin drift', 'zignites-sentinel' ),
				'status'  => 'fail',
				'message' => __( 'Some plugins present in the snapshot are no longer active now. Any future restore workflow would need manual validation.', 'zignites-sentinel' ),
			);
		}

		if ( $new_count > 0 || $changed_count > 0 ) {
			return array(
				'key'     => 'plugin_drift',
				'label'   => __( 'Plugin drift', 'zignites-sentinel' ),
				'status'  => 'warning',
				'message' => __( 'Plugin versions or active plugin sets have changed since the snapshot.', 'zignites-sentinel' ),
			);
		}

		return array(
			'key'     => 'plugin_drift',
			'label'   => __( 'Plugin drift', 'zignites-sentinel' ),
			'status'  => 'pass',
			'message' => __( 'The current active plugin set matches the snapshot closely.', 'zignites-sentinel' ),
		);
	}

	/**
	 * Check runtime drift since the snapshot.
	 *
	 * @param array $comparison Comparison data.
	 * @return array
	 */
	protected function check_runtime_drift( array $comparison ) {
		if (
			(string) $comparison['snapshot_core_version'] !== (string) $comparison['current_core_version'] ||
			(string) $comparison['snapshot_php_version'] !== (string) $comparison['current_php_version']
		) {
			return array(
				'key'     => 'runtime_drift',
				'label'   => __( 'Runtime drift', 'zignites-sentinel' ),
				'status'  => 'warning',
				'message' => __( 'WordPress core or PHP version differs from the snapshot baseline.', 'zignites-sentinel' ),
			);
		}

		return array(
			'key'     => 'runtime_drift',
			'label'   => __( 'Runtime drift', 'zignites-sentinel' ),
			'status'  => 'pass',
			'message' => __( 'WordPress core and PHP versions match the snapshot baseline.', 'zignites-sentinel' ),
		);
	}

	/**
	 * Check whether snapshot component sources are still available on disk.
	 *
	 * @param array $source_validation Source validation result.
	 * @return array
	 */
	protected function check_source_availability( array $source_validation ) {
		$status = isset( $source_validation['status'] ) ? (string) $source_validation['status'] : 'warning';
		$mapped = 'warning';

		if ( 'pass' === $status ) {
			$mapped = 'pass';
		} elseif ( 'fail' === $status ) {
			$mapped = 'fail';
		}

		return array(
			'key'     => 'source_availability',
			'label'   => __( 'Snapshot source availability', 'zignites-sentinel' ),
			'status'  => $mapped,
			'message' => isset( $source_validation['message'] ) ? (string) $source_validation['message'] : __( 'Snapshot component source availability could not be determined.', 'zignites-sentinel' ),
			'details' => isset( $source_validation['checks'] ) ? $source_validation['checks'] : array(),
		);
	}

	/**
	 * Determine overall status from a check summary.
	 *
	 * @param array $summary Check summary.
	 * @return string
	 */
	protected function determine_status( array $summary ) {
		if ( ! empty( $summary['fail'] ) ) {
			return 'blocked';
		}

		if ( ! empty( $summary['warning'] ) ) {
			return 'caution';
		}

		return 'ready';
	}

	/**
	 * Build a human-readable note.
	 *
	 * @param array $summary Check summary.
	 * @return string
	 */
	protected function build_note( array $summary ) {
		if ( ! empty( $summary['fail'] ) ) {
			return __( 'Blocking conditions were found. A future restore workflow would require manual intervention and validation.', 'zignites-sentinel' );
		}

		if ( ! empty( $summary['warning'] ) ) {
			return __( 'The snapshot is usable for review, but environment drift means any future restore workflow should be treated carefully.', 'zignites-sentinel' );
		}

		return __( 'No major restore-readiness issues were detected in this advisory assessment.', 'zignites-sentinel' );
	}
}
