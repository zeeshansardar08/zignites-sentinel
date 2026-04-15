<?php
/**
 * ZIP package creation and validation for snapshots.
 *
 * @package ZignitesSentinel
 */

namespace Zignites\Sentinel\Snapshots;

defined( 'ABSPATH' ) || exit;

class SnapshotPackageManager {

	/**
	 * Artifact storage guard.
	 *
	 * @var ArtifactStorageGuard
	 */
	protected $storage_guard;

	/**
	 * Constructor.
	 *
	 * @param ArtifactStorageGuard|null $storage_guard Artifact storage guard.
	 */
	public function __construct( ArtifactStorageGuard $storage_guard = null ) {
		$this->storage_guard = $storage_guard ? $storage_guard : new ArtifactStorageGuard();
	}

	/**
	 * Relative package directory under uploads.
	 *
	 * @var string
	 */
	const PACKAGE_DIRECTORY = 'zignites-sentinel/packages';

	/**
	 * Create a ZIP package for a snapshot and return its artifact row.
	 *
	 * @param int   $snapshot_id Snapshot ID.
	 * @param array $snapshot    Snapshot payload.
	 * @param array $artifacts   Component artifact rows.
	 * @return array|null
	 */
	public function create_snapshot_package( $snapshot_id, array $snapshot, array $artifacts ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return null;
		}

		$snapshot_id = absint( $snapshot_id );

		if ( $snapshot_id < 1 ) {
			return null;
		}

		$base_dir = $this->ensure_package_directory();

		if ( empty( $base_dir ) ) {
			return null;
		}

		$relative_path = trailingslashit( self::PACKAGE_DIRECTORY ) . 'snapshot-' . $snapshot_id . '.zip';
		$absolute_path = $this->resolve_package_path( $relative_path );

		if ( empty( $absolute_path ) ) {
			return null;
		}

		$zip    = new \ZipArchive();
		$opened = $zip->open( $absolute_path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE );

		if ( true !== $opened ) {
			return null;
		}

		$snapshot_json = wp_json_encode( $snapshot, JSON_PRETTY_PRINT );
		$artifacts_json = wp_json_encode( $artifacts, JSON_PRETTY_PRINT );

		if ( ! is_string( $snapshot_json ) || ! is_string( $artifacts_json ) ) {
			$zip->close();
			return null;
		}

		$checksums = array(
			'snapshot.json'  => hash( 'sha256', $snapshot_json ),
			'artifacts.json' => hash( 'sha256', $artifacts_json ),
		);

		$zip->addFromString( 'snapshot.json', $snapshot_json );
		$zip->addFromString( 'artifacts.json', $artifacts_json );

		if ( ! empty( $snapshot['theme_stylesheet'] ) ) {
			$this->add_theme_to_zip( $zip, sanitize_text_field( (string) $snapshot['theme_stylesheet'] ), $checksums );
		}

		if ( ! empty( $snapshot['active_plugins'] ) && is_array( $snapshot['active_plugins'] ) ) {
			foreach ( $snapshot['active_plugins'] as $plugin_state ) {
				if ( empty( $plugin_state['plugin'] ) ) {
					continue;
				}

				$this->add_plugin_to_zip( $zip, sanitize_text_field( (string) $plugin_state['plugin'] ), $checksums );
			}
		}

		$checksum_json = wp_json_encode(
			array(
				'generated_at' => current_time( 'mysql', true ),
				'entries'      => $checksums,
			),
			JSON_PRETTY_PRINT
		);

		if ( ! is_string( $checksum_json ) ) {
			$zip->close();
			return null;
		}

		$zip->addFromString( 'checksums.json', $checksum_json );

		$zip->close();

		if ( ! file_exists( $absolute_path ) ) {
			return null;
		}

		return array(
			'artifact_type' => 'package',
			'artifact_key'  => 'snapshot-package',
			'label'         => __( 'Snapshot rollback ZIP', 'zignites-sentinel' ),
			'version'       => '',
			'source_path'   => $relative_path,
			'created_at'    => current_time( 'mysql', true ),
			'metadata'      => wp_json_encode(
				array(
					'sha256'          => hash_file( 'sha256', $absolute_path ),
					'manifest_sha256' => hash( 'sha256', $checksum_json ),
					'size_bytes'      => filesize( $absolute_path ),
					'entry_count'     => count( $checksums ) + 1,
				)
			),
		);
	}

	/**
	 * Inspect a package artifact.
	 *
	 * @param array $artifact Package artifact row.
	 * @return array
	 */
	public function inspect_package_artifact( array $artifact ) {
		$relative_path = isset( $artifact['source_path'] ) ? sanitize_text_field( (string) $artifact['source_path'] ) : '';
		$absolute_path = $this->resolve_package_path( $relative_path );
		$metadata      = array();

		if ( ! empty( $artifact['metadata'] ) ) {
			$decoded  = json_decode( (string) $artifact['metadata'], true );
			$metadata = is_array( $decoded ) ? $decoded : array();
		}

		if ( empty( $absolute_path ) || ! file_exists( $absolute_path ) ) {
			return array(
				'status'  => 'fail',
				'message' => __( 'The stored snapshot ZIP package is missing.', 'zignites-sentinel' ),
				'details' => array(
					'absolute_path' => $absolute_path,
				),
			);
		}

		if ( ! class_exists( 'ZipArchive' ) ) {
			return array(
				'status'  => 'fail',
				'message' => __( 'The ZipArchive extension is unavailable, so the snapshot ZIP package cannot be inspected.', 'zignites-sentinel' ),
				'details' => array(
					'absolute_path' => $absolute_path,
				),
			);
		}

		$current_hash  = hash_file( 'sha256', $absolute_path );
		$expected_hash = isset( $metadata['sha256'] ) ? sanitize_text_field( (string) $metadata['sha256'] ) : '';

		if ( ! empty( $expected_hash ) && is_string( $current_hash ) && $expected_hash !== $current_hash ) {
			return array(
				'status'  => 'fail',
				'message' => __( 'The stored snapshot ZIP package hash no longer matches the recorded payload.', 'zignites-sentinel' ),
				'details' => array(
					'absolute_path' => $absolute_path,
					'expected_hash' => $expected_hash,
					'current_hash'  => $current_hash,
				),
			);
		}

		$zip    = new \ZipArchive();
		$opened = $zip->open( $absolute_path );

		if ( true !== $opened ) {
			return array(
				'status'  => 'fail',
				'message' => __( 'The stored snapshot ZIP package could not be opened.', 'zignites-sentinel' ),
				'details' => array(
					'absolute_path' => $absolute_path,
				),
			);
		}

		$has_snapshot   = false !== $zip->locateName( 'snapshot.json' );
		$has_artifacts  = false !== $zip->locateName( 'artifacts.json' );
		$has_checksums  = false !== $zip->locateName( 'checksums.json' );
		$num_files      = $zip->numFiles;
		$checksum_state = $this->inspect_checksum_manifest( $zip, $metadata );

		$zip->close();

		if ( ! $has_snapshot || ! $has_artifacts || ! $has_checksums ) {
			return array(
				'status'  => 'fail',
				'message' => __( 'The stored snapshot ZIP package is missing required manifest files.', 'zignites-sentinel' ),
				'details' => array(
					'absolute_path' => $absolute_path,
					'has_snapshot'  => $has_snapshot,
					'has_artifacts' => $has_artifacts,
					'has_checksums' => $has_checksums,
				),
			);
		}

		if ( 'pass' !== $checksum_state['status'] ) {
			return array(
				'status'  => $checksum_state['status'],
				'message' => $checksum_state['message'],
				'details' => array_merge(
					array(
						'absolute_path' => $absolute_path,
						'num_files'     => $num_files,
					),
					isset( $checksum_state['details'] ) && is_array( $checksum_state['details'] ) ? $checksum_state['details'] : array()
				),
			);
		}

		return array(
			'status'  => 'pass',
			'message' => __( 'The stored snapshot ZIP package is present, readable, and matches its checksum manifest.', 'zignites-sentinel' ),
			'details' => array(
				'absolute_path' => $absolute_path,
				'current_hash'  => is_string( $current_hash ) ? $current_hash : '',
				'size_bytes'    => filesize( $absolute_path ),
				'num_files'     => $num_files,
				'entry_count'   => isset( $checksum_state['details']['entry_count'] ) ? (int) $checksum_state['details']['entry_count'] : 0,
			),
		);
	}

	/**
	 * Perform a restore dry-run against a package artifact.
	 *
	 * @param array $artifact Package artifact row.
	 * @param array $snapshot Snapshot row with decoded metadata.
	 * @return array
	 */
	public function dry_run_package( array $artifact, array $snapshot ) {
		$inspection = $this->inspect_package_artifact( $artifact );

		if ( 'pass' !== $inspection['status'] ) {
			return array(
				'status'  => 'fail',
				'message' => isset( $inspection['message'] ) ? $inspection['message'] : __( 'The snapshot ZIP package could not be validated.', 'zignites-sentinel' ),
				'checks'  => array(),
			);
		}

		$absolute_path = isset( $inspection['details']['absolute_path'] ) ? $inspection['details']['absolute_path'] : '';
		$zip           = new \ZipArchive();
		$opened        = $zip->open( $absolute_path );

		if ( true !== $opened ) {
			return array(
				'status'  => 'fail',
				'message' => __( 'The snapshot ZIP package could not be opened for dry-run validation.', 'zignites-sentinel' ),
				'checks'  => array(),
			);
		}

		$checks = array(
			$this->build_required_entry_check( $zip, 'snapshot.json', __( 'Snapshot manifest entry', 'zignites-sentinel' ) ),
			$this->build_required_entry_check( $zip, 'artifacts.json', __( 'Artifact manifest entry', 'zignites-sentinel' ) ),
			$this->build_required_entry_check( $zip, 'checksums.json', __( 'Checksum manifest entry', 'zignites-sentinel' ) ),
			$this->build_checksum_validation_check( $zip ),
		);

		if ( ! empty( $snapshot['theme_stylesheet'] ) ) {
			$checks[] = $this->build_directory_presence_check(
				$zip,
				'themes/' . sanitize_text_field( (string) $snapshot['theme_stylesheet'] ) . '/',
				__( 'Theme payload', 'zignites-sentinel' )
			);
		}

		if ( ! empty( $snapshot['active_plugins_decoded'] ) && is_array( $snapshot['active_plugins_decoded'] ) ) {
			foreach ( $snapshot['active_plugins_decoded'] as $plugin_state ) {
				if ( empty( $plugin_state['plugin'] ) ) {
					continue;
				}

				$checks[] = $this->build_plugin_entry_check( $zip, $plugin_state );
			}
		}

		$zip->close();

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
			'message' => 'fail' === $status
				? __( 'The snapshot ZIP package failed restore dry-run validation.', 'zignites-sentinel' )
				: ( 'warning' === $status
					? __( 'The snapshot ZIP package passed basic dry-run checks with warnings.', 'zignites-sentinel' )
					: __( 'The snapshot ZIP package passed restore dry-run validation.', 'zignites-sentinel' ) ),
			'checks'  => $checks,
			'summary' => $summary,
		);
	}

	/**
	 * Delete package files referenced by artifact rows.
	 *
	 * @param array $artifacts Artifact rows.
	 * @return void
	 */
	public function delete_artifact_files( array $artifacts ) {
		foreach ( $artifacts as $artifact ) {
			if ( empty( $artifact['artifact_type'] ) || 'package' !== $artifact['artifact_type'] ) {
				continue;
			}

			$absolute_path = $this->resolve_package_path( isset( $artifact['source_path'] ) ? $artifact['source_path'] : '' );

			if ( ! empty( $absolute_path ) && file_exists( $absolute_path ) ) {
				wp_delete_file( $absolute_path );
			}
		}
	}

	/**
	 * Delete the package directory from uploads.
	 *
	 * @return void
	 */
	public function delete_package_directory() {
		$base_dir = $this->get_package_directory();

		if ( empty( $base_dir ) || ! is_dir( $base_dir ) ) {
			return;
		}

		$items = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $base_dir, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $items as $item ) {
			if ( $item->isDir() ) {
				rmdir( $item->getPathname() );
				continue;
			}

			wp_delete_file( $item->getPathname() );
		}

		rmdir( $base_dir );
	}

	/**
	 * Get the stored checksum manifest from a ZIP archive.
	 *
	 * @param \ZipArchive $zip ZIP instance.
	 * @return array
	 */
	public function get_checksum_manifest( \ZipArchive $zip ) {
		$manifest = $zip->getFromName( 'checksums.json' );

		if ( ! is_string( $manifest ) || '' === $manifest ) {
			return array();
		}

		$decoded = json_decode( $manifest, true );

		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * Resolve a stored package path to an absolute path.
	 *
	 * @param string $relative_path Relative package path.
	 * @return string
	 */
	public function resolve_package_path( $relative_path ) {
		$relative_path = ltrim( wp_normalize_path( (string) $relative_path ), '/' );
		$uploads       = wp_upload_dir();

		if ( ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) ) {
			return '';
		}

		return trailingslashit( wp_normalize_path( $uploads['basedir'] ) ) . $relative_path;
	}

	/**
	 * Add the active theme directory to a ZIP package.
	 *
	 * @param \ZipArchive $zip        ZIP instance.
	 * @param string      $stylesheet Theme stylesheet.
	 * @return void
	 */
	protected function add_theme_to_zip( \ZipArchive $zip, $stylesheet, array &$checksums ) {
		$theme_path = trailingslashit( wp_normalize_path( get_theme_root() ) ) . $stylesheet;

		if ( is_dir( $theme_path ) ) {
			$this->add_directory_to_zip( $zip, $theme_path, 'themes/' . $stylesheet, $checksums );
		}
	}

	/**
	 * Add a plugin payload to a ZIP package.
	 *
	 * @param \ZipArchive $zip         ZIP instance.
	 * @param string      $plugin_file Plugin basename.
	 * @return void
	 */
	protected function add_plugin_to_zip( \ZipArchive $zip, $plugin_file, array &$checksums ) {
		$normalized = ltrim( wp_normalize_path( $plugin_file ), '/' );
		$full_path  = trailingslashit( wp_normalize_path( WP_PLUGIN_DIR ) ) . $normalized;

		if ( file_exists( $full_path ) ) {
			$directory = dirname( $normalized );

			if ( '.' !== $directory && is_dir( trailingslashit( wp_normalize_path( WP_PLUGIN_DIR ) ) . $directory ) ) {
				$this->add_directory_to_zip( $zip, trailingslashit( wp_normalize_path( WP_PLUGIN_DIR ) ) . $directory, 'plugins/' . $directory, $checksums );
				return;
			}

			$zip->addFile( $full_path, 'plugins/' . basename( $normalized ) );
			$checksums[ 'plugins/' . basename( $normalized ) ] = hash_file( 'sha256', $full_path );
		}
	}

	/**
	 * Recursively add a directory to a ZIP package.
	 *
	 * @param \ZipArchive $zip          ZIP instance.
	 * @param string      $source_path  Absolute source path.
	 * @param string      $archive_root Archive root path.
	 * @return void
	 */
	protected function add_directory_to_zip( \ZipArchive $zip, $source_path, $archive_root, array &$checksums ) {
		$source_path  = wp_normalize_path( $source_path );
		$archive_root = trim( wp_normalize_path( $archive_root ), '/' );
		$iterator     = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $source_path, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ( $iterator as $item ) {
			$full_path = wp_normalize_path( $item->getPathname() );
			$relative  = ltrim( substr( $full_path, strlen( trailingslashit( $source_path ) ) ), '/' );
			$archive   = $archive_root . ( '' !== $relative ? '/' . $relative : '' );

			if ( $item->isDir() ) {
				$zip->addEmptyDir( $archive );
				continue;
			}

			$zip->addFile( $full_path, $archive );
			$checksums[ $archive ] = hash_file( 'sha256', $full_path );
		}
	}

	/**
	 * Validate checksum manifest entries inside a ZIP archive.
	 *
	 * @param \ZipArchive $zip ZIP instance.
	 * @return array
	 */
	protected function build_checksum_validation_check( \ZipArchive $zip ) {
		$manifest = $this->get_checksum_manifest( $zip );

		if ( empty( $manifest['entries'] ) || ! is_array( $manifest['entries'] ) ) {
			return array(
				'label'   => __( 'Checksum manifest validation', 'zignites-sentinel' ),
				'status'  => 'fail',
				'message' => __( 'The package checksum manifest is missing or invalid.', 'zignites-sentinel' ),
			);
		}

		$mismatches = 0;

		foreach ( $manifest['entries'] as $path => $expected_hash ) {
			$contents = $zip->getFromName( $path );

			if ( ! is_string( $contents ) || ! is_string( $expected_hash ) || hash( 'sha256', $contents ) !== $expected_hash ) {
				++$mismatches;
				break;
			}
		}

		if ( $mismatches > 0 ) {
			return array(
				'label'   => __( 'Checksum manifest validation', 'zignites-sentinel' ),
				'status'  => 'fail',
				'message' => __( 'One or more package entries do not match the stored checksum manifest.', 'zignites-sentinel' ),
			);
		}

		return array(
			'label'   => __( 'Checksum manifest validation', 'zignites-sentinel' ),
			'status'  => 'pass',
			'message' => __( 'Package entries match the stored checksum manifest.', 'zignites-sentinel' ),
		);
	}

	/**
	 * Inspect checksum manifest integrity.
	 *
	 * @param \ZipArchive $zip      ZIP instance.
	 * @param array       $metadata Package artifact metadata.
	 * @return array
	 */
	protected function inspect_checksum_manifest( \ZipArchive $zip, array $metadata ) {
		$manifest = $zip->getFromName( 'checksums.json' );

		if ( ! is_string( $manifest ) || '' === $manifest ) {
			return array(
				'status'  => 'fail',
				'message' => __( 'The stored snapshot ZIP package does not include a readable checksum manifest.', 'zignites-sentinel' ),
				'details' => array(),
			);
		}

		$expected_hash = isset( $metadata['manifest_sha256'] ) ? sanitize_text_field( (string) $metadata['manifest_sha256'] ) : '';
		$current_hash  = hash( 'sha256', $manifest );

		if ( '' !== $expected_hash && $expected_hash !== $current_hash ) {
			return array(
				'status'  => 'fail',
				'message' => __( 'The stored checksum manifest no longer matches the recorded package metadata.', 'zignites-sentinel' ),
				'details' => array(
					'expected_manifest_hash' => $expected_hash,
					'current_manifest_hash'  => $current_hash,
				),
			);
		}

		$decoded = json_decode( $manifest, true );

		if ( ! is_array( $decoded ) || empty( $decoded['entries'] ) || ! is_array( $decoded['entries'] ) ) {
			return array(
				'status'  => 'fail',
				'message' => __( 'The stored checksum manifest is not valid JSON.', 'zignites-sentinel' ),
				'details' => array(),
			);
		}

		return array(
			'status'  => 'pass',
			'message' => __( 'The stored checksum manifest is present and readable.', 'zignites-sentinel' ),
			'details' => array(
				'entry_count' => count( $decoded['entries'] ),
			),
		);
	}

	/**
	 * Build a required entry check.
	 *
	 * @param \ZipArchive $zip   ZIP instance.
	 * @param string      $path  Archive path.
	 * @param string      $label Check label.
	 * @return array
	 */
	protected function build_required_entry_check( \ZipArchive $zip, $path, $label ) {
		if ( false === $zip->locateName( $path ) ) {
			return array(
				'label'   => $label,
				'status'  => 'fail',
				'message' => sprintf(
					/* translators: %s = archive path */
					__( 'Missing required package entry: %s', 'zignites-sentinel' ),
					$path
				),
			);
		}

		return array(
			'label'   => $label,
			'status'  => 'pass',
			'message' => sprintf(
				/* translators: %s = archive path */
				__( 'Found package entry: %s', 'zignites-sentinel' ),
				$path
			),
		);
	}

	/**
	 * Build a directory presence check within the package.
	 *
	 * @param \ZipArchive $zip   ZIP instance.
	 * @param string      $path  Directory prefix.
	 * @param string      $label Check label.
	 * @return array
	 */
	protected function build_directory_presence_check( \ZipArchive $zip, $path, $label ) {
		for ( $index = 0; $index < $zip->numFiles; $index++ ) {
			$name = $zip->getNameIndex( $index );

			if ( is_string( $name ) && 0 === strpos( $name, $path ) ) {
				return array(
					'label'   => $label,
					'status'  => 'pass',
					'message' => sprintf(
						/* translators: %s = archive directory */
						__( 'Found package payload under %s', 'zignites-sentinel' ),
						$path
					),
				);
			}
		}

		return array(
			'label'   => $label,
			'status'  => 'fail',
			'message' => sprintf(
				/* translators: %s = archive directory */
				__( 'Missing package payload under %s', 'zignites-sentinel' ),
				$path
			),
		);
	}

	/**
	 * Build a plugin payload check within the package.
	 *
	 * @param \ZipArchive $zip         ZIP instance.
	 * @param array       $plugin_state Plugin state row.
	 * @return array
	 */
	protected function build_plugin_entry_check( \ZipArchive $zip, array $plugin_state ) {
		$plugin_file = sanitize_text_field( (string) $plugin_state['plugin'] );
		$directory   = dirname( $plugin_file );

		if ( '.' !== $directory ) {
			return $this->build_directory_presence_check(
				$zip,
				'plugins/' . wp_normalize_path( $directory ) . '/',
				sprintf(
					/* translators: %s = plugin name */
					__( 'Plugin payload: %s', 'zignites-sentinel' ),
					isset( $plugin_state['name'] ) ? $plugin_state['name'] : $plugin_file
				)
			);
		}

		return $this->build_required_entry_check(
			$zip,
			'plugins/' . basename( $plugin_file ),
			sprintf(
				/* translators: %s = plugin name */
				__( 'Plugin payload: %s', 'zignites-sentinel' ),
				isset( $plugin_state['name'] ) ? $plugin_state['name'] : $plugin_file
			)
		);
	}

	/**
	 * Ensure the package directory exists.
	 *
	 * @return string
	 */
	protected function ensure_package_directory() {
		$base_dir = $this->get_package_directory();

		if ( empty( $base_dir ) ) {
			return '';
		}

		if ( is_dir( $base_dir ) && $this->storage_guard->protect_directory( $base_dir ) ) {
			return $base_dir;
		}

		if ( $this->storage_guard->protect_directory( $base_dir ) ) {
			return $base_dir;
		}

		return '';
	}

	/**
	 * Get the absolute package directory.
	 *
	 * @return string
	 */
	protected function get_package_directory() {
		$uploads = wp_upload_dir();

		if ( ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) ) {
			return '';
		}

		return trailingslashit( wp_normalize_path( $uploads['basedir'] ) ) . self::PACKAGE_DIRECTORY;
	}
}
