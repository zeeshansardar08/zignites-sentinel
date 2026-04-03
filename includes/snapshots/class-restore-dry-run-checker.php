<?php
/**
 * Non-destructive restore dry-run validation.
 *
 * @package ZignitesSentinel
 */

namespace Zignites\Sentinel\Snapshots;

defined( 'ABSPATH' ) || exit;

class RestoreDryRunChecker {

	/**
	 * Package manager.
	 *
	 * @var SnapshotPackageManager
	 */
	protected $package_manager;

	/**
	 * Constructor.
	 *
	 * @param SnapshotPackageManager $package_manager Package manager.
	 */
	public function __construct( SnapshotPackageManager $package_manager ) {
		$this->package_manager = $package_manager;
	}

	/**
	 * Run a restore dry-run for a snapshot and its artifacts.
	 *
	 * @param array $snapshot  Snapshot row.
	 * @param array $artifacts Artifact rows.
	 * @return array
	 */
	public function run( array $snapshot, array $artifacts ) {
		$package_artifact = $this->find_package_artifact( $artifacts );
		$checks           = array(
			$this->check_snapshot_baseline( $snapshot ),
			$this->check_package_presence( $package_artifact ),
		);

		if ( is_array( $package_artifact ) ) {
			$package_run = $this->package_manager->dry_run_package( $package_artifact, $snapshot );

			foreach ( $package_run['checks'] as $check ) {
				$checks[] = $check;
			}
		} else {
			$package_run = array(
				'status'  => 'fail',
				'message' => __( 'No snapshot ZIP package is available for restore dry-run validation.', 'zignites-sentinel' ),
				'summary' => array(
					'pass'    => 0,
					'warning' => 0,
					'fail'    => 1,
				),
			);
		}

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

		$status = 'ready';

		if ( ! empty( $summary['fail'] ) ) {
			$status = 'blocked';
		} elseif ( ! empty( $summary['warning'] ) ) {
			$status = 'caution';
		}

		return array(
			'generated_at' => current_time( 'mysql', true ),
			'status'       => $status,
			'checks'       => $checks,
			'summary'      => $summary,
			'package_run'  => $package_run,
			'note'         => $this->build_note( $status ),
		);
	}

	/**
	 * Check the snapshot row has baseline fields needed for dry-run validation.
	 *
	 * @param array $snapshot Snapshot row.
	 * @return array
	 */
	protected function check_snapshot_baseline( array $snapshot ) {
		if ( empty( $snapshot['id'] ) || empty( $snapshot['theme_stylesheet'] ) || empty( $snapshot['active_plugins'] ) ) {
			return array(
				'label'   => __( 'Snapshot dry-run baseline', 'zignites-sentinel' ),
				'status'  => 'fail',
				'message' => __( 'The snapshot is missing fields required for restore dry-run validation.', 'zignites-sentinel' ),
			);
		}

		return array(
			'label'   => __( 'Snapshot dry-run baseline', 'zignites-sentinel' ),
			'status'  => 'pass',
			'message' => __( 'The snapshot contains the fields required for restore dry-run validation.', 'zignites-sentinel' ),
		);
	}

	/**
	 * Check that a package artifact exists.
	 *
	 * @param array|null $package_artifact Package artifact row.
	 * @return array
	 */
	protected function check_package_presence( $package_artifact ) {
		if ( ! is_array( $package_artifact ) ) {
			return array(
				'label'   => __( 'Snapshot ZIP package', 'zignites-sentinel' ),
				'status'  => 'fail',
				'message' => __( 'No snapshot ZIP package was found for dry-run validation.', 'zignites-sentinel' ),
			);
		}

		return array(
			'label'   => __( 'Snapshot ZIP package', 'zignites-sentinel' ),
			'status'  => 'pass',
			'message' => __( 'A snapshot ZIP package is available for dry-run validation.', 'zignites-sentinel' ),
		);
	}

	/**
	 * Find the package artifact from the artifact set.
	 *
	 * @param array $artifacts Artifact rows.
	 * @return array|null
	 */
	protected function find_package_artifact( array $artifacts ) {
		foreach ( $artifacts as $artifact ) {
			if ( isset( $artifact['artifact_type'] ) && 'package' === $artifact['artifact_type'] ) {
				return $artifact;
			}
		}

		return null;
	}

	/**
	 * Build a note for the dry-run result.
	 *
	 * @param string $status Result status.
	 * @return string
	 */
	protected function build_note( $status ) {
		if ( 'blocked' === $status ) {
			return __( 'Restore dry-run validation found blocking issues in the stored snapshot package.', 'zignites-sentinel' );
		}

		if ( 'caution' === $status ) {
			return __( 'Restore dry-run validation passed with warnings. Review the package contents before any future restore workflow.', 'zignites-sentinel' );
		}

		return __( 'Restore dry-run validation passed without modifying the site.', 'zignites-sentinel' );
	}
}
