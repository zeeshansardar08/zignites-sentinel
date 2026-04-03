<?php
/**
 * Advisory validation for component sources and update packages.
 *
 * @package ZignitesSentinel
 */

namespace Zignites\Sentinel\Updates;

use Zignites\Sentinel\Snapshots\SnapshotArtifactRepository;
use Zignites\Sentinel\Snapshots\SnapshotExportManager;
use Zignites\Sentinel\Snapshots\SnapshotPackageManager;

defined( 'ABSPATH' ) || exit;

class SourceValidator {

	/**
	 * Snapshot artifact repository.
	 *
	 * @var SnapshotArtifactRepository|null
	 */
	protected $artifact_repository;

	/**
	 * Snapshot export manager.
	 *
	 * @var SnapshotExportManager|null
	 */
	protected $export_manager;

	/**
	 * Snapshot package manager.
	 *
	 * @var SnapshotPackageManager|null
	 */
	protected $package_manager;

	/**
	 * Constructor.
	 *
	 * @param SnapshotArtifactRepository|null $artifact_repository Artifact repository.
	 */
	public function __construct(
		SnapshotArtifactRepository $artifact_repository = null,
		SnapshotExportManager $export_manager = null,
		SnapshotPackageManager $package_manager = null
	) {
		$this->artifact_repository = $artifact_repository;
		$this->export_manager      = $export_manager;
		$this->package_manager     = $package_manager;
	}

	/**
	 * Validate that snapshot-referenced components still exist locally.
	 *
	 * @param array $snapshot Snapshot row with decoded plugin state when available.
	 * @return array
	 */
	public function validate_snapshot_sources( array $snapshot ) {
		$plugins   = $this->get_snapshot_plugins( $snapshot );
		$manifest  = $this->get_component_manifest( $snapshot );
		$artifacts = $this->get_stored_artifacts( $snapshot );
		$checks    = array(
			$this->build_theme_source_check( $snapshot ),
			$this->build_snapshot_plugin_source_check( $plugins ),
		);

		if ( ! empty( $artifacts ) ) {
			$checks[] = $this->build_artifact_record_presence_check( $artifacts );
			$checks[] = $this->build_stored_artifact_check( $artifacts );
		} else {
			$checks[] = $this->build_artifact_record_presence_check( array() );
			$checks[] = $this->build_manifest_presence_check( $manifest );
			$checks[] = $this->build_manifest_artifact_check( $manifest );
		}

		return $this->build_assessment(
			$checks,
			__( 'All snapshot component sources are still present on disk.', 'zignites-sentinel' ),
			__( 'Some snapshot component sources require review before any future restore workflow.', 'zignites-sentinel' ),
			__( 'One or more snapshot component sources are missing from disk.', 'zignites-sentinel' )
		);
	}

	/**
	 * Validate selected update targets against local sources and package metadata.
	 *
	 * @param array $targets Selected update targets.
	 * @return array
	 */
	public function validate_update_targets( array $targets ) {
		$checks = array();

		if ( ! function_exists( 'get_core_updates' ) ) {
			require_once ABSPATH . 'wp-admin/includes/update.php';
		}

		$plugin_updates = get_site_transient( 'update_plugins' );
		$theme_updates  = get_site_transient( 'update_themes' );
		$core_updates   = get_core_updates();

		foreach ( $targets as $target ) {
			$type = isset( $target['type'] ) ? (string) $target['type'] : '';

			if ( 'plugin' === $type ) {
				$checks[] = $this->build_plugin_target_check( $target, $plugin_updates );
				continue;
			}

			if ( 'theme' === $type ) {
				$checks[] = $this->build_theme_target_check( $target, $theme_updates );
				continue;
			}

			if ( 'core' === $type ) {
				$checks[] = $this->build_core_target_check( $target, $core_updates );
			}
		}

		return $this->build_assessment(
			$checks,
			__( 'Selected update targets have package metadata and local component sources available.', 'zignites-sentinel' ),
			__( 'Selected update targets are usable for review, but source/package issues were detected.', 'zignites-sentinel' ),
			__( 'One or more selected update targets are missing package metadata or local component sources.', 'zignites-sentinel' )
		);
	}

	/**
	 * Build the theme source validation check for a snapshot.
	 *
	 * @param array $snapshot Snapshot row.
	 * @return array
	 */
	protected function build_theme_source_check( array $snapshot ) {
		$stylesheet = isset( $snapshot['theme_stylesheet'] ) ? sanitize_text_field( (string) $snapshot['theme_stylesheet'] ) : '';
		$path       = trailingslashit( wp_normalize_path( get_theme_root() ) ) . $stylesheet;
		$exists     = ! empty( $stylesheet ) && is_dir( $path );

		if ( ! $exists ) {
			return array(
				'key'     => 'snapshot_theme_source',
				'label'   => __( 'Snapshot theme source', 'zignites-sentinel' ),
				'status'  => 'fail',
				'message' => __( 'The theme recorded in the snapshot is not currently available on disk.', 'zignites-sentinel' ),
				'details' => array(
					'theme_stylesheet' => $stylesheet,
				),
			);
		}

		return array(
			'key'     => 'snapshot_theme_source',
			'label'   => __( 'Snapshot theme source', 'zignites-sentinel' ),
			'status'  => 'pass',
			'message' => __( 'The snapshot theme is available on disk.', 'zignites-sentinel' ),
			'details' => array(
				'theme_stylesheet' => $stylesheet,
			),
		);
	}

	/**
	 * Check that component manifest data exists in snapshot metadata.
	 *
	 * @param array $manifest Manifest data.
	 * @return array
	 */
	protected function build_manifest_presence_check( array $manifest ) {
		if ( empty( $manifest ) ) {
			return array(
				'key'     => 'snapshot_manifest',
				'label'   => __( 'Snapshot component manifest', 'zignites-sentinel' ),
				'status'  => 'fail',
				'message' => __( 'The snapshot does not include a stored component manifest for rollback artifact review.', 'zignites-sentinel' ),
				'details' => array(),
			);
		}

		return array(
			'key'     => 'snapshot_manifest',
			'label'   => __( 'Snapshot component manifest', 'zignites-sentinel' ),
			'status'  => 'pass',
			'message' => __( 'The snapshot includes stored component manifest data.', 'zignites-sentinel' ),
			'details' => array(
				'generated_at' => isset( $manifest['generated_at'] ) ? sanitize_text_field( (string) $manifest['generated_at'] ) : '',
			),
		);
	}

	/**
	 * Check that dedicated artifact rows exist for a snapshot.
	 *
	 * @param array $artifacts Stored artifact rows.
	 * @return array
	 */
	protected function build_artifact_record_presence_check( array $artifacts ) {
		if ( empty( $artifacts ) ) {
			return array(
				'key'     => 'snapshot_artifact_records',
				'label'   => __( 'Snapshot artifact records', 'zignites-sentinel' ),
				'status'  => 'warning',
				'message' => __( 'No dedicated rollback artifact records were stored for this snapshot. Falling back to metadata checks.', 'zignites-sentinel' ),
				'details' => array(),
			);
		}

		return array(
			'key'     => 'snapshot_artifact_records',
			'label'   => __( 'Snapshot artifact records', 'zignites-sentinel' ),
			'status'  => 'pass',
			'message' => __( 'Dedicated rollback artifact records are available for this snapshot.', 'zignites-sentinel' ),
			'details' => array(
				'artifact_count' => count( $artifacts ),
			),
		);
	}

	/**
	 * Build the plugin source validation check for a snapshot.
	 *
	 * @param array $plugins Snapshot plugin state.
	 * @return array
	 */
	protected function build_snapshot_plugin_source_check( array $plugins ) {
		$missing_plugins = array();

		foreach ( $plugins as $plugin_state ) {
			$plugin_file = isset( $plugin_state['plugin'] ) ? sanitize_text_field( (string) $plugin_state['plugin'] ) : '';

			if ( empty( $plugin_file ) || $this->plugin_file_exists( $plugin_file ) ) {
				continue;
			}

			$missing_plugins[] = array(
				'plugin'  => $plugin_file,
				'name'    => isset( $plugin_state['name'] ) ? sanitize_text_field( (string) $plugin_state['name'] ) : $plugin_file,
				'version' => isset( $plugin_state['version'] ) ? sanitize_text_field( (string) $plugin_state['version'] ) : '',
			);
		}

		if ( ! empty( $missing_plugins ) ) {
			return array(
				'key'     => 'snapshot_plugin_sources',
				'label'   => __( 'Snapshot plugin sources', 'zignites-sentinel' ),
				'status'  => 'fail',
				'message' => sprintf(
					/* translators: %d = number of missing plugins */
					_n(
						'%d snapshot plugin source is missing from disk.',
						'%d snapshot plugin sources are missing from disk.',
						count( $missing_plugins ),
						'zignites-sentinel'
					),
					count( $missing_plugins )
				),
				'details' => array(
					'missing_plugins' => $missing_plugins,
				),
			);
		}

		return array(
			'key'     => 'snapshot_plugin_sources',
			'label'   => __( 'Snapshot plugin sources', 'zignites-sentinel' ),
			'status'  => 'pass',
			'message' => __( 'All snapshot plugins are still available on disk.', 'zignites-sentinel' ),
			'details' => array(
				'plugin_count' => count( $plugins ),
			),
		);
	}

	/**
	 * Build validation against stored manifest artifacts.
	 *
	 * @param array $manifest Stored component manifest.
	 * @return array
	 */
	protected function build_manifest_artifact_check( array $manifest ) {
		if ( empty( $manifest ) ) {
			return array(
				'key'     => 'snapshot_artifacts',
				'label'   => __( 'Stored artifact manifest', 'zignites-sentinel' ),
				'status'  => 'warning',
				'message' => __( 'No stored artifact manifest is available for deeper rollback validation.', 'zignites-sentinel' ),
				'details' => array(),
			);
		}

		$missing = array();

		if ( ! empty( $manifest['theme']['stylesheet'] ) ) {
			$theme_path = trailingslashit( wp_normalize_path( get_theme_root() ) ) . sanitize_text_field( (string) $manifest['theme']['stylesheet'] );

			if ( ! is_dir( $theme_path ) ) {
				$missing[] = array(
					'type'  => 'theme',
					'label' => sanitize_text_field( (string) $manifest['theme']['stylesheet'] ),
				);
			}
		}

		if ( ! empty( $manifest['plugins'] ) && is_array( $manifest['plugins'] ) ) {
			foreach ( $manifest['plugins'] as $plugin_state ) {
				$plugin_file = isset( $plugin_state['plugin'] ) ? sanitize_text_field( (string) $plugin_state['plugin'] ) : '';

				if ( empty( $plugin_file ) || $this->plugin_file_exists( $plugin_file ) ) {
					continue;
				}

				$missing[] = array(
					'type'  => 'plugin',
					'label' => isset( $plugin_state['name'] ) && $plugin_state['name'] ? sanitize_text_field( (string) $plugin_state['name'] ) : $plugin_file,
				);
			}
		}

		if ( ! empty( $missing ) ) {
			return array(
				'key'     => 'snapshot_artifacts',
				'label'   => __( 'Stored artifact manifest', 'zignites-sentinel' ),
				'status'  => 'fail',
				'message' => __( 'Components recorded in the stored artifact manifest are no longer fully available.', 'zignites-sentinel' ),
				'details' => array(
					'missing_artifacts' => $missing,
				),
			);
		}

		return array(
			'key'     => 'snapshot_artifacts',
			'label'   => __( 'Stored artifact manifest', 'zignites-sentinel' ),
			'status'  => 'pass',
			'message' => __( 'Components recorded in the stored artifact manifest are still available.', 'zignites-sentinel' ),
			'details' => array(),
		);
	}

	/**
	 * Build validation against stored artifact rows.
	 *
	 * @param array $artifacts Stored artifact rows.
	 * @return array
	 */
	protected function build_stored_artifact_check( array $artifacts ) {
		$missing = array();

		foreach ( $artifacts as $artifact ) {
			$type = isset( $artifact['artifact_type'] ) ? sanitize_key( $artifact['artifact_type'] ) : '';
			$key  = isset( $artifact['artifact_key'] ) ? sanitize_text_field( (string) $artifact['artifact_key'] ) : '';

			if ( 'export' === $type && $this->export_manager ) {
				$inspection = $this->export_manager->inspect_export_artifact( $artifact );
				$exists     = isset( $inspection['status'] ) && 'pass' === $inspection['status'];
				$reason     = isset( $inspection['message'] ) ? $inspection['message'] : '';
			} elseif ( 'package' === $type && $this->package_manager ) {
				$inspection = $this->package_manager->inspect_package_artifact( $artifact );
				$exists     = isset( $inspection['status'] ) && 'pass' === $inspection['status'];
				$reason     = isset( $inspection['message'] ) ? $inspection['message'] : '';
			} elseif ( 'theme' === $type && ! empty( $key ) ) {
				$exists = is_dir( trailingslashit( wp_normalize_path( get_theme_root() ) ) . $key );
				$reason = '';
			} elseif ( 'plugin' === $type && ! empty( $key ) ) {
				$exists = $this->plugin_file_exists( $key );
				$reason = '';
			} else {
				$exists = false;
				$reason = __( 'Unknown artifact type.', 'zignites-sentinel' );
			}

			if ( $exists ) {
				continue;
			}

			$missing[] = array(
				'type'  => $type,
				'label' => isset( $artifact['label'] ) ? sanitize_text_field( (string) $artifact['label'] ) : $key,
				'reason'=> $reason,
			);
		}

		if ( ! empty( $missing ) ) {
			return array(
				'key'     => 'stored_artifact_rows',
				'label'   => __( 'Stored rollback artifacts', 'zignites-sentinel' ),
				'status'  => 'fail',
				'message' => __( 'Some dedicated rollback artifact records point to components that are no longer available.', 'zignites-sentinel' ),
				'details' => array(
					'missing_artifacts' => $missing,
				),
			);
		}

		return array(
			'key'     => 'stored_artifact_rows',
			'label'   => __( 'Stored rollback artifacts', 'zignites-sentinel' ),
			'status'  => 'pass',
			'message' => __( 'All dedicated rollback artifact records still resolve to available components.', 'zignites-sentinel' ),
			'details' => array(
				'artifact_count' => count( $artifacts ),
			),
		);
	}

	/**
	 * Build validation for a selected plugin update target.
	 *
	 * @param array       $target         Target data.
	 * @param object|bool $plugin_updates Plugin update transient.
	 * @return array
	 */
	protected function build_plugin_target_check( array $target, $plugin_updates ) {
		$plugin_file = isset( $target['slug'] ) ? sanitize_text_field( (string) $target['slug'] ) : '';
		$label       = isset( $target['label'] ) ? sanitize_text_field( (string) $target['label'] ) : $plugin_file;
		$source_ok   = ! empty( $plugin_file ) && $this->plugin_file_exists( $plugin_file );
		$package     = '';

		if ( isset( $plugin_updates->response[ $plugin_file ]->package ) ) {
			$package = (string) $plugin_updates->response[ $plugin_file ]->package;
		}

		if ( ! $source_ok || ! $this->has_package_source( $package ) ) {
			return array(
				'key'     => 'update_target_' . md5( 'plugin:' . $plugin_file ),
				'label'   => sprintf(
					/* translators: %s = plugin name */
					__( 'Plugin source: %s', 'zignites-sentinel' ),
					$label
				),
				'status'  => 'fail',
				'message' => __( 'The selected plugin is missing local files or update package metadata.', 'zignites-sentinel' ),
				'details' => array(
					'plugin_file'   => $plugin_file,
					'package_host'  => $this->extract_host( $package ),
					'local_source'  => $source_ok,
					'package_ready' => $this->has_package_source( $package ),
				),
			);
		}

		return array(
			'key'     => 'update_target_' . md5( 'plugin:' . $plugin_file ),
			'label'   => sprintf(
				/* translators: %s = plugin name */
				__( 'Plugin source: %s', 'zignites-sentinel' ),
				$label
			),
			'status'  => 'pass',
			'message' => __( 'Local plugin files and update package metadata are available.', 'zignites-sentinel' ),
			'details' => array(
				'plugin_file'  => $plugin_file,
				'package_host' => $this->extract_host( $package ),
			),
		);
	}

	/**
	 * Build validation for a selected theme update target.
	 *
	 * @param array       $target        Target data.
	 * @param object|bool $theme_updates Theme update transient.
	 * @return array
	 */
	protected function build_theme_target_check( array $target, $theme_updates ) {
		$stylesheet = isset( $target['slug'] ) ? sanitize_text_field( (string) $target['slug'] ) : '';
		$label      = isset( $target['label'] ) ? sanitize_text_field( (string) $target['label'] ) : $stylesheet;
		$source_ok  = ! empty( $stylesheet ) && is_dir( trailingslashit( wp_normalize_path( get_theme_root() ) ) . $stylesheet );
		$package    = '';

		if ( isset( $theme_updates->response[ $stylesheet ]['package'] ) ) {
			$package = (string) $theme_updates->response[ $stylesheet ]['package'];
		}

		if ( ! $source_ok || ! $this->has_package_source( $package ) ) {
			return array(
				'key'     => 'update_target_' . md5( 'theme:' . $stylesheet ),
				'label'   => sprintf(
					/* translators: %s = theme name */
					__( 'Theme source: %s', 'zignites-sentinel' ),
					$label
				),
				'status'  => 'fail',
				'message' => __( 'The selected theme is missing local files or update package metadata.', 'zignites-sentinel' ),
				'details' => array(
					'theme_stylesheet' => $stylesheet,
					'package_host'     => $this->extract_host( $package ),
					'local_source'     => $source_ok,
					'package_ready'    => $this->has_package_source( $package ),
				),
			);
		}

		return array(
			'key'     => 'update_target_' . md5( 'theme:' . $stylesheet ),
			'label'   => sprintf(
				/* translators: %s = theme name */
				__( 'Theme source: %s', 'zignites-sentinel' ),
				$label
			),
			'status'  => 'pass',
			'message' => __( 'Local theme files and update package metadata are available.', 'zignites-sentinel' ),
			'details' => array(
				'theme_stylesheet' => $stylesheet,
				'package_host'     => $this->extract_host( $package ),
			),
		);
	}

	/**
	 * Build validation for the WordPress core update target.
	 *
	 * @param array $target       Target data.
	 * @param array $core_updates Core update data.
	 * @return array
	 */
	protected function build_core_target_check( array $target, array $core_updates ) {
		$label   = isset( $target['label'] ) ? sanitize_text_field( (string) $target['label'] ) : __( 'WordPress Core', 'zignites-sentinel' );
		$package = '';

		foreach ( $core_updates as $core_update ) {
			if ( empty( $core_update->current ) ) {
				continue;
			}

			if ( ! empty( $core_update->packages->full ) ) {
				$package = (string) $core_update->packages->full;
			}

			break;
		}

		if ( ! $this->has_package_source( $package ) ) {
			return array(
				'key'     => 'update_target_' . md5( 'core:wordpress' ),
				'label'   => sprintf(
					/* translators: %s = component label */
					__( 'Core source: %s', 'zignites-sentinel' ),
					$label
				),
				'status'  => 'fail',
				'message' => __( 'WordPress core update package metadata is not available.', 'zignites-sentinel' ),
				'details' => array(
					'package_host' => $this->extract_host( $package ),
				),
			);
		}

		return array(
			'key'     => 'update_target_' . md5( 'core:wordpress' ),
			'label'   => sprintf(
				/* translators: %s = component label */
				__( 'Core source: %s', 'zignites-sentinel' ),
				$label
			),
			'status'  => 'pass',
			'message' => __( 'WordPress core package metadata is available.', 'zignites-sentinel' ),
			'details' => array(
				'package_host' => $this->extract_host( $package ),
			),
		);
	}

	/**
	 * Build an assessment wrapper from check rows.
	 *
	 * @param array  $checks        Check rows.
	 * @param string $pass_message  Pass message.
	 * @param string $warn_message  Warning message.
	 * @param string $fail_message  Fail message.
	 * @return array
	 */
	protected function build_assessment( array $checks, $pass_message, $warn_message, $fail_message ) {
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

		$status = 'pass';

		if ( ! empty( $summary['fail'] ) ) {
			$status = 'fail';
		} elseif ( ! empty( $summary['warning'] ) ) {
			$status = 'warning';
		}

		return array(
			'status'  => $status,
			'summary' => $summary,
			'message' => 'fail' === $status ? $fail_message : ( 'warning' === $status ? $warn_message : $pass_message ),
			'checks'  => $checks,
		);
	}

	/**
	 * Extract decoded plugin list from a snapshot row.
	 *
	 * @param array $snapshot Snapshot row.
	 * @return array
	 */
	protected function get_snapshot_plugins( array $snapshot ) {
		if ( isset( $snapshot['active_plugins_decoded'] ) && is_array( $snapshot['active_plugins_decoded'] ) ) {
			return $snapshot['active_plugins_decoded'];
		}

		if ( empty( $snapshot['active_plugins'] ) ) {
			return array();
		}

		$decoded = json_decode( (string) $snapshot['active_plugins'], true );

		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * Extract the stored component manifest from snapshot metadata.
	 *
	 * @param array $snapshot Snapshot row.
	 * @return array
	 */
	protected function get_component_manifest( array $snapshot ) {
		if ( isset( $snapshot['metadata_decoded']['component_manifest'] ) && is_array( $snapshot['metadata_decoded']['component_manifest'] ) ) {
			return $snapshot['metadata_decoded']['component_manifest'];
		}

		if ( empty( $snapshot['metadata'] ) ) {
			return array();
		}

		$metadata = json_decode( (string) $snapshot['metadata'], true );

		if ( ! is_array( $metadata ) || empty( $metadata['component_manifest'] ) || ! is_array( $metadata['component_manifest'] ) ) {
			return array();
		}

		return $metadata['component_manifest'];
	}

	/**
	 * Fetch dedicated artifact rows for a snapshot when available.
	 *
	 * @param array $snapshot Snapshot row.
	 * @return array
	 */
	protected function get_stored_artifacts( array $snapshot ) {
		if ( ! $this->artifact_repository || empty( $snapshot['id'] ) ) {
			return array();
		}

		return $this->artifact_repository->get_by_snapshot_id( (int) $snapshot['id'] );
	}

	/**
	 * Determine whether a plugin file exists in standard plugin locations.
	 *
	 * @param string $plugin_file Plugin basename.
	 * @return bool
	 */
	protected function plugin_file_exists( $plugin_file ) {
		$plugin_file = wp_normalize_path( $plugin_file );

		if ( file_exists( trailingslashit( wp_normalize_path( WP_PLUGIN_DIR ) ) . ltrim( $plugin_file, '/' ) ) ) {
			return true;
		}

		if ( defined( 'WPMU_PLUGIN_DIR' ) && file_exists( trailingslashit( wp_normalize_path( WPMU_PLUGIN_DIR ) ) . ltrim( $plugin_file, '/' ) ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Determine whether package metadata contains a usable source URL.
	 *
	 * @param string $package Package URL.
	 * @return bool
	 */
	protected function has_package_source( $package ) {
		return ! empty( $package ) && ! empty( wp_parse_url( (string) $package, PHP_URL_HOST ) );
	}

	/**
	 * Extract the host from a package URL.
	 *
	 * @param string $package Package URL.
	 * @return string
	 */
	protected function extract_host( $package ) {
		$host = wp_parse_url( (string) $package, PHP_URL_HOST );

		return is_string( $host ) ? $host : '';
	}
}
