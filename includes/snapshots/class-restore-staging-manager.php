<?php
/**
 * Non-destructive staged restore validation.
 *
 * @package ZignitesSentinel
 */

namespace Zignites\Sentinel\Snapshots;

defined( 'ABSPATH' ) || exit;

class RestoreStagingManager {

	/**
	 * Relative staging directory under uploads.
	 *
	 * @var string
	 */
	const STAGING_DIRECTORY = 'zignites-sentinel/staging';

	/**
	 * Package manager.
	 *
	 * @var SnapshotPackageManager
	 */
	protected $package_manager;

	/**
	 * Artifact storage guard.
	 *
	 * @var ArtifactStorageGuard
	 */
	protected $storage_guard;

	/**
	 * Constructor.
	 *
	 * @param SnapshotPackageManager $package_manager Package manager.
	 */
	public function __construct( SnapshotPackageManager $package_manager, ArtifactStorageGuard $storage_guard = null ) {
		$this->package_manager = $package_manager;
		$this->storage_guard   = $storage_guard ? $storage_guard : new ArtifactStorageGuard();
	}

	/**
	 * Extract a snapshot package into a temporary stage and validate it.
	 *
	 * @param array $snapshot  Snapshot row.
	 * @param array $artifacts Artifact rows.
	 * @return array
	 */
	public function stage_and_validate( array $snapshot, array $artifacts ) {
		$checks           = array();
		$package_artifact = $this->find_package_artifact( $artifacts );
		$stage_path       = '';

		$checks[] = $this->build_stage_baseline_check( $snapshot );
		$checks[] = $this->build_package_availability_check( $package_artifact );

		if ( ! is_array( $package_artifact ) ) {
			return $this->finalize_result( $checks, '', true );
		}

		$inspection = $this->package_manager->inspect_package_artifact( $package_artifact );
		$checks[]   = array(
			'label'   => __( 'Package integrity inspection', 'zignites-sentinel' ),
			'status'  => isset( $inspection['status'] ) ? $inspection['status'] : 'fail',
			'message' => isset( $inspection['message'] ) ? $inspection['message'] : __( 'Package inspection failed.', 'zignites-sentinel' ),
		);

		if ( empty( $inspection['details']['absolute_path'] ) || 'pass' !== $inspection['status'] ) {
			return $this->finalize_result( $checks, '', true );
		}

		if ( ! class_exists( 'ZipArchive' ) ) {
			$checks[] = array(
				'label'   => __( 'Zip extraction support', 'zignites-sentinel' ),
				'status'  => 'fail',
				'message' => __( 'The ZipArchive extension is unavailable, so staged restore validation cannot run.', 'zignites-sentinel' ),
			);
			return $this->finalize_result( $checks, '', true );
		}

		$stage = $this->extract_package_to_stage( $snapshot, $artifacts, $inspection );

		$checks[] = $stage['stage_check'];

		if ( isset( $stage['extraction_check'] ) ) {
			$checks[] = $stage['extraction_check'];
		}

		if ( empty( $stage['success'] ) ) {
			return $this->finalize_result( $checks, isset( $stage['stage_path'] ) ? $stage['stage_path'] : '', empty( $stage['stage_created'] ) );
		}

		$stage_path = $stage['stage_path'];
		$manifest   = $stage['manifest'];

		$checks[] = $this->build_extracted_file_check( $stage_path, 'snapshot.json', __( 'Staged snapshot manifest', 'zignites-sentinel' ) );
		$checks[] = $this->build_extracted_file_check( $stage_path, 'artifacts.json', __( 'Staged artifact manifest', 'zignites-sentinel' ) );
		$checks[] = $this->build_extracted_file_check( $stage_path, 'checksums.json', __( 'Staged checksum manifest', 'zignites-sentinel' ) );
		$checks[] = $this->build_staged_snapshot_match_check( $stage_path, $snapshot );
		$checks[] = $this->build_staged_checksum_validation_check( $stage_path, $manifest );

		if ( ! empty( $snapshot['theme_stylesheet'] ) ) {
			$checks[] = $this->build_extracted_directory_check(
				$stage_path,
				'themes/' . sanitize_text_field( (string) $snapshot['theme_stylesheet'] ),
				__( 'Staged theme payload', 'zignites-sentinel' )
			);
		}

		if ( ! empty( $snapshot['active_plugins_decoded'] ) && is_array( $snapshot['active_plugins_decoded'] ) ) {
			foreach ( $snapshot['active_plugins_decoded'] as $plugin_state ) {
				if ( empty( $plugin_state['plugin'] ) ) {
					continue;
				}

				$checks[] = $this->build_staged_plugin_check( $stage_path, $plugin_state );
			}
		}

		return $this->finalize_result( $checks, $stage_path, false );
	}

	/**
	 * Delete the staging directory root from uploads.
	 *
	 * @return void
	 */
	public function delete_stage_directory_root() {
		$base_dir = $this->get_stage_root();

		if ( '' === $base_dir || ! is_dir( $base_dir ) ) {
			return;
		}

		$this->delete_directory_recursive( $base_dir );
	}

	/**
	 * Extract a validated snapshot package to a temporary stage.
	 *
	 * @param array $snapshot   Snapshot row.
	 * @param array $artifacts  Artifact rows.
	 * @param array $inspection Optional package inspection result.
	 * @return array
	 */
	public function extract_package_to_stage( array $snapshot, array $artifacts, array $inspection = array() ) {
		$package_artifact = $this->find_package_artifact( $artifacts );

		if ( ! is_array( $package_artifact ) ) {
			return array(
				'success'       => false,
				'stage_created' => false,
				'stage_path'    => '',
				'manifest'      => array(),
				'stage_check'   => array(
					'label'   => __( 'Stage directory', 'zignites-sentinel' ),
					'status'  => 'fail',
					'message' => __( 'No snapshot ZIP package is available for staged extraction.', 'zignites-sentinel' ),
				),
			);
		}

		if ( empty( $inspection ) ) {
			$inspection = $this->package_manager->inspect_package_artifact( $package_artifact );
		}

		if ( 'pass' !== ( isset( $inspection['status'] ) ? $inspection['status'] : 'fail' ) || empty( $inspection['details']['absolute_path'] ) ) {
			return array(
				'success'       => false,
				'stage_created' => false,
				'stage_path'    => '',
				'manifest'      => array(),
				'stage_check'   => array(
					'label'   => __( 'Stage directory', 'zignites-sentinel' ),
					'status'  => 'fail',
					'message' => __( 'The snapshot package could not be prepared for staged extraction.', 'zignites-sentinel' ),
				),
			);
		}

		$stage_path = $this->create_stage_directory( isset( $snapshot['id'] ) ? (int) $snapshot['id'] : 0 );

		if ( '' === $stage_path ) {
			return array(
				'success'       => false,
				'stage_created' => false,
				'stage_path'    => '',
				'manifest'      => array(),
				'stage_check'   => array(
					'label'   => __( 'Stage directory', 'zignites-sentinel' ),
					'status'  => 'fail',
					'message' => __( 'A temporary stage directory could not be created.', 'zignites-sentinel' ),
				),
			);
		}

		$zip    = new \ZipArchive();
		$opened = $zip->open( $inspection['details']['absolute_path'] );

		if ( true !== $opened ) {
			return array(
				'success'           => false,
				'stage_created'     => true,
				'stage_path'        => $stage_path,
				'manifest'          => array(),
				'stage_check'       => array(
					'label'   => __( 'Stage directory', 'zignites-sentinel' ),
					'status'  => 'pass',
					'message' => __( 'A temporary stage directory was created.', 'zignites-sentinel' ),
				),
				'extraction_check'  => array(
					'label'   => __( 'Package extraction', 'zignites-sentinel' ),
					'status'  => 'fail',
					'message' => __( 'The snapshot package could not be opened for staged extraction.', 'zignites-sentinel' ),
				),
			);
		}

		$entry_validation = $this->validate_archive_entries( $zip );

		if ( 'pass' !== $entry_validation['status'] ) {
			$zip->close();

			return array(
				'success'           => false,
				'stage_created'     => true,
				'stage_path'        => $stage_path,
				'manifest'          => array(),
				'stage_check'       => array(
					'label'   => __( 'Stage directory', 'zignites-sentinel' ),
					'status'  => 'pass',
					'message' => __( 'A temporary stage directory was created.', 'zignites-sentinel' ),
				),
				'extraction_check'  => $entry_validation,
			);
		}

		$extracted = $zip->extractTo( $stage_path );
		$manifest  = $this->package_manager->get_checksum_manifest( $zip );
		$zip->close();

		return array(
			'success'       => $extracted,
			'stage_created' => true,
			'stage_path'    => $stage_path,
			'manifest'      => $manifest,
			'stage_check'   => array(
				'label'   => __( 'Stage directory', 'zignites-sentinel' ),
				'status'  => 'pass',
				'message' => __( 'A temporary stage directory was created.', 'zignites-sentinel' ),
			),
			'extraction_check' => array(
				'label'   => __( 'Package extraction', 'zignites-sentinel' ),
				'status'  => $extracted ? 'pass' : 'fail',
				'message' => $extracted
					? __( 'The snapshot package was extracted into the temporary stage.', 'zignites-sentinel' )
					: __( 'The snapshot package could not be extracted into the temporary stage.', 'zignites-sentinel' ),
			),
		);
	}

	/**
	 * Delete a specific stage directory.
	 *
	 * @param string $stage_path Stage path.
	 * @return bool
	 */
	public function cleanup_stage_directory( $stage_path ) {
		if ( ! $this->is_valid_stage_path( $stage_path ) ) {
			return false;
		}

		return $this->delete_directory_recursive( $stage_path );
	}

	/**
	 * Delete preserved stage directories except for explicitly preserved paths.
	 *
	 * @param array $preserve_paths Absolute paths to preserve.
	 * @return array
	 */
	public function cleanup_abandoned_stage_directories( array $preserve_paths = array() ) {
		$root = $this->get_stage_root();

		if ( '' === $root || ! is_dir( $root ) ) {
			return array(
				'deleted' => array(),
				'failed'  => array(),
			);
		}

		$preserve_map = array();

		foreach ( $preserve_paths as $path ) {
			$path = wp_normalize_path( (string) $path );

			if ( '' !== $path ) {
				$preserve_map[ rtrim( $path, '/' ) ] = true;
			}
		}

		$entries = glob( trailingslashit( $root ) . '*' );
		$deleted = array();
		$failed  = array();

		if ( false === $entries ) {
			return array(
				'deleted' => array(),
				'failed'  => array(),
			);
		}

		foreach ( $entries as $entry ) {
			$entry = wp_normalize_path( $entry );

			if ( ! is_dir( $entry ) ) {
				continue;
			}

			if ( isset( $preserve_map[ rtrim( $entry, '/' ) ] ) ) {
				continue;
			}

			if ( $this->delete_directory_recursive( $entry ) ) {
				$deleted[] = $entry;
				continue;
			}

			$failed[] = $entry;
		}

		return array(
			'deleted' => $deleted,
			'failed'  => $failed,
		);
	}

	/**
	 * Build a baseline check for staged validation.
	 *
	 * @param array $snapshot Snapshot row.
	 * @return array
	 */
	protected function build_stage_baseline_check( array $snapshot ) {
		if ( empty( $snapshot['id'] ) || empty( $snapshot['theme_stylesheet'] ) || empty( $snapshot['active_plugins'] ) ) {
			return array(
				'label'   => __( 'Restore stage baseline', 'zignites-sentinel' ),
				'status'  => 'fail',
				'message' => __( 'The snapshot is missing fields required for staged restore validation.', 'zignites-sentinel' ),
			);
		}

		return array(
			'label'   => __( 'Restore stage baseline', 'zignites-sentinel' ),
			'status'  => 'pass',
			'message' => __( 'The snapshot contains the fields required for staged restore validation.', 'zignites-sentinel' ),
		);
	}

	/**
	 * Build a package availability check.
	 *
	 * @param array|null $package_artifact Package artifact row.
	 * @return array
	 */
	protected function build_package_availability_check( $package_artifact ) {
		if ( ! is_array( $package_artifact ) ) {
			return array(
				'label'   => __( 'Staged snapshot package', 'zignites-sentinel' ),
				'status'  => 'fail',
				'message' => __( 'No snapshot ZIP package is available for staged validation.', 'zignites-sentinel' ),
			);
		}

		return array(
			'label'   => __( 'Staged snapshot package', 'zignites-sentinel' ),
			'status'  => 'pass',
			'message' => __( 'A snapshot ZIP package is available for staged validation.', 'zignites-sentinel' ),
		);
	}

	/**
	 * Build a file existence check for the extracted stage.
	 *
	 * @param string $stage_path Stage path.
	 * @param string $relative   Relative path.
	 * @param string $label      Check label.
	 * @return array
	 */
	protected function build_extracted_file_check( $stage_path, $relative, $label ) {
		$full_path = $this->resolve_stage_entry_path( $stage_path, $relative );

		if ( '' === $full_path ) {
			return array(
				'label'   => $label,
				'status'  => 'fail',
				'message' => __( 'The staged file path is invalid.', 'zignites-sentinel' ),
			);
		}

		if ( ! file_exists( $full_path ) ) {
			return array(
				'label'   => $label,
				'status'  => 'fail',
				'message' => sprintf(
					/* translators: %s = file path */
					__( 'Missing extracted file: %s', 'zignites-sentinel' ),
					$relative
				),
			);
		}

		return array(
			'label'   => $label,
			'status'  => 'pass',
			'message' => sprintf(
				/* translators: %s = file path */
				__( 'Found extracted file: %s', 'zignites-sentinel' ),
				$relative
			),
		);
	}

	/**
	 * Build a directory existence check for the extracted stage.
	 *
	 * @param string $stage_path Stage path.
	 * @param string $relative   Relative path.
	 * @param string $label      Check label.
	 * @return array
	 */
	protected function build_extracted_directory_check( $stage_path, $relative, $label ) {
		$full_path = $this->resolve_stage_entry_path( $stage_path, $relative );

		if ( '' === $full_path ) {
			return array(
				'label'   => $label,
				'status'  => 'fail',
				'message' => __( 'The staged directory path is invalid.', 'zignites-sentinel' ),
			);
		}

		if ( ! is_dir( $full_path ) ) {
			return array(
				'label'   => $label,
				'status'  => 'fail',
				'message' => sprintf(
					/* translators: %s = directory path */
					__( 'Missing extracted directory: %s', 'zignites-sentinel' ),
					$relative
				),
			);
		}

		return array(
			'label'   => $label,
			'status'  => 'pass',
			'message' => sprintf(
				/* translators: %s = directory path */
				__( 'Found extracted directory: %s', 'zignites-sentinel' ),
				$relative
			),
		);
	}

	/**
	 * Build a plugin payload check for the extracted stage.
	 *
	 * @param string $stage_path   Stage path.
	 * @param array  $plugin_state Plugin state row.
	 * @return array
	 */
	protected function build_staged_plugin_check( $stage_path, array $plugin_state ) {
		$plugin_file = sanitize_text_field( (string) $plugin_state['plugin'] );
		$directory   = dirname( $plugin_file );
		$label       = sprintf(
			/* translators: %s = plugin name */
			__( 'Staged plugin payload: %s', 'zignites-sentinel' ),
			isset( $plugin_state['name'] ) ? $plugin_state['name'] : $plugin_file
		);

		if ( '.' !== $directory ) {
			return $this->build_extracted_directory_check( $stage_path, 'plugins/' . wp_normalize_path( $directory ), $label );
		}

		return $this->build_extracted_file_check( $stage_path, 'plugins/' . basename( $plugin_file ), $label );
	}

	/**
	 * Build a snapshot manifest match check against the extracted payload.
	 *
	 * @param string $stage_path Stage path.
	 * @param array  $snapshot   Snapshot row.
	 * @return array
	 */
	protected function build_staged_snapshot_match_check( $stage_path, array $snapshot ) {
		$snapshot_path = trailingslashit( wp_normalize_path( $stage_path ) ) . 'snapshot.json';
		$contents      = file_exists( $snapshot_path ) ? file_get_contents( $snapshot_path ) : false;

		if ( ! is_string( $contents ) || '' === $contents ) {
			return array(
				'label'   => __( 'Staged snapshot identity', 'zignites-sentinel' ),
				'status'  => 'fail',
				'message' => __( 'The extracted snapshot manifest could not be read.', 'zignites-sentinel' ),
			);
		}

		$decoded = json_decode( $contents, true );

		if ( ! is_array( $decoded ) || empty( $decoded['id'] ) ) {
			return array(
				'label'   => __( 'Staged snapshot identity', 'zignites-sentinel' ),
				'status'  => 'fail',
				'message' => __( 'The extracted snapshot manifest is invalid.', 'zignites-sentinel' ),
			);
		}

		if ( (int) $decoded['id'] !== (int) $snapshot['id'] ) {
			return array(
				'label'   => __( 'Staged snapshot identity', 'zignites-sentinel' ),
				'status'  => 'fail',
				'message' => __( 'The extracted snapshot manifest does not match the selected snapshot.', 'zignites-sentinel' ),
			);
		}

		return array(
			'label'   => __( 'Staged snapshot identity', 'zignites-sentinel' ),
			'status'  => 'pass',
			'message' => __( 'The extracted snapshot manifest matches the selected snapshot.', 'zignites-sentinel' ),
		);
	}

	/**
	 * Build a checksum validation check against extracted package contents.
	 *
	 * @param string $stage_path Stage path.
	 * @param array  $manifest   Checksum manifest.
	 * @return array
	 */
	protected function build_staged_checksum_validation_check( $stage_path, array $manifest ) {
		if ( empty( $manifest['entries'] ) || ! is_array( $manifest['entries'] ) ) {
			return array(
				'label'   => __( 'Staged checksum validation', 'zignites-sentinel' ),
				'status'  => 'fail',
				'message' => __( 'The extracted checksum manifest is missing or invalid.', 'zignites-sentinel' ),
			);
		}

		foreach ( $manifest['entries'] as $relative => $expected_hash ) {
			$full_path = $this->resolve_stage_entry_path( $stage_path, $relative );

			if ( '' === $full_path || ! file_exists( $full_path ) || ! is_string( $expected_hash ) || hash_file( 'sha256', $full_path ) !== $expected_hash ) {
				return array(
					'label'   => __( 'Staged checksum validation', 'zignites-sentinel' ),
					'status'  => 'fail',
					'message' => sprintf(
						/* translators: %s = file path */
						__( 'The extracted package entry failed checksum validation: %s', 'zignites-sentinel' ),
						$relative
					),
				);
			}
		}

		return array(
			'label'   => __( 'Staged checksum validation', 'zignites-sentinel' ),
			'status'  => 'pass',
			'message' => __( 'The extracted package entries match the stored checksum manifest.', 'zignites-sentinel' ),
		);
	}

	/**
	 * Finalize a staged validation result and clean up the stage directory.
	 *
	 * @param array  $checks             Check rows.
	 * @param string $stage_path         Stage path.
	 * @param bool   $stage_not_created  Whether no stage directory was created.
	 * @return array
	 */
	protected function finalize_result( array $checks, $stage_path, $stage_not_created ) {
		$cleanup_ok = true;

		if ( ! $stage_not_created && '' !== $stage_path ) {
			$cleanup_ok = $this->delete_directory_recursive( $stage_path );
			$checks[]   = array(
				'label'   => __( 'Stage cleanup', 'zignites-sentinel' ),
				'status'  => $cleanup_ok ? 'pass' : 'warning',
				'message' => $cleanup_ok
					? __( 'The temporary stage directory was removed after validation.', 'zignites-sentinel' )
					: __( 'The temporary stage directory could not be fully removed after validation.', 'zignites-sentinel' ),
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
			'generated_at'      => current_time( 'mysql', true ),
			'status'            => $status,
			'checks'            => $checks,
			'summary'           => $summary,
			'stage_path'        => $stage_path,
			'cleanup_completed' => $cleanup_ok,
			'note'              => $this->build_note( $status, $cleanup_ok ),
		);
	}

	/**
	 * Create a unique stage directory for a snapshot.
	 *
	 * @param int $snapshot_id Snapshot ID.
	 * @return string
	 */
	protected function create_stage_directory( $snapshot_id ) {
		$base_dir = $this->get_stage_root();

		if ( '' === $base_dir ) {
			return '';
		}

		if ( ! $this->storage_guard->protect_directory( $base_dir ) ) {
			return '';
		}

		$stage_path = trailingslashit( $base_dir ) . 'snapshot-' . absint( $snapshot_id ) . '-' . gmdate( 'YmdHis' );

		if ( wp_mkdir_p( $stage_path ) ) {
			return $stage_path;
		}

		return '';
	}

	/**
	 * Get the absolute staging root.
	 *
	 * @return string
	 */
	protected function get_stage_root() {
		$uploads = wp_upload_dir();

		if ( ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) ) {
			return '';
		}

		return trailingslashit( wp_normalize_path( $uploads['basedir'] ) ) . self::STAGING_DIRECTORY;
	}

	/**
	 * Delete a directory recursively.
	 *
	 * @param string $path Directory path.
	 * @return bool
	 */
	protected function delete_directory_recursive( $path ) {
		if ( '' === $path || ! is_dir( $path ) ) {
			return true;
		}

		$items = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $path, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $items as $item ) {
			if ( $item->isDir() ) {
				if ( ! @rmdir( $item->getPathname() ) ) {
					return false;
				}
				continue;
			}

			if ( ! wp_delete_file( $item->getPathname() ) && file_exists( $item->getPathname() ) ) {
				return false;
			}
		}

		return @rmdir( $path );
	}

	/**
	 * Validate ZIP entry names before extraction.
	 *
	 * @param \ZipArchive $zip ZIP instance.
	 * @return array
	 */
	protected function validate_archive_entries( \ZipArchive $zip ) {
		for ( $index = 0; $index < $zip->numFiles; $index++ ) {
			$name = $zip->getNameIndex( $index );

			if ( ! is_string( $name ) || ! $this->is_safe_archive_entry_name( $name ) ) {
				return array(
					'label'   => __( 'Package extraction', 'zignites-sentinel' ),
					'status'  => 'fail',
					'message' => __( 'The snapshot package includes an unsafe archive path and cannot be extracted.', 'zignites-sentinel' ),
				);
			}
		}

		return array(
			'label'   => __( 'Package extraction', 'zignites-sentinel' ),
			'status'  => 'pass',
			'message' => __( 'The snapshot package archive paths passed extraction safety checks.', 'zignites-sentinel' ),
		);
	}

	/**
	 * Determine whether an archive entry name is safe to extract.
	 *
	 * @param string $name Archive entry name.
	 * @return bool
	 */
	protected function is_safe_archive_entry_name( $name ) {
		$name = ltrim( wp_normalize_path( (string) $name ), '/' );

		if ( '' === $name || false !== strpos( $name, ':' ) || preg_match( '#(^|/)\.\.(/|$)#', $name ) ) {
			return false;
		}

		if ( in_array( $name, array( 'snapshot.json', 'artifacts.json', 'checksums.json' ), true ) ) {
			return true;
		}

		return 0 === strpos( $name, 'themes/' ) || 0 === strpos( $name, 'plugins/' );
	}

	/**
	 * Resolve a stage-relative entry path safely.
	 *
	 * @param string $stage_path Stage path.
	 * @param string $relative   Relative package path.
	 * @return string
	 */
	protected function resolve_stage_entry_path( $stage_path, $relative ) {
		$stage_path = rtrim( wp_normalize_path( (string) $stage_path ), '/' );
		$relative   = ltrim( wp_normalize_path( (string) $relative ), '/' );

		if ( '' === $stage_path || '' === $relative || ! $this->is_safe_archive_entry_name( $relative ) ) {
			return '';
		}

		return $stage_path . '/' . $relative;
	}

	/**
	 * Determine whether a stage path is inside Sentinel's staging root.
	 *
	 * @param string $path Stage path.
	 * @return bool
	 */
	protected function is_valid_stage_path( $path ) {
		$root = $this->get_stage_root();
		$path = rtrim( wp_normalize_path( (string) $path ), '/' );

		return '' !== $path && '' !== $root && $this->storage_guard->is_path_within( $path, $root ) && rtrim( $root, '/' ) !== $path;
	}

	/**
	 * Find the package artifact from artifact rows.
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
	 * Build a summary note.
	 *
	 * @param string $status     Result status.
	 * @param bool   $cleanup_ok Whether cleanup completed.
	 * @return string
	 */
	protected function build_note( $status, $cleanup_ok ) {
		if ( 'blocked' === $status ) {
			return __( 'Staged restore validation found blocking issues in the extracted package.', 'zignites-sentinel' );
		}

		if ( 'caution' === $status ) {
			return __( 'Staged restore validation passed with warnings. Review the extracted package findings before any future live restore.', 'zignites-sentinel' );
		}

		if ( ! $cleanup_ok ) {
			return __( 'Staged restore validation passed, but the temporary stage directory could not be fully cleaned up.', 'zignites-sentinel' );
		}

		return __( 'Staged restore validation extracted and verified the package without modifying the live site.', 'zignites-sentinel' );
	}
}
