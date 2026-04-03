<?php
/**
 * Filesystem export support for snapshot rollback payloads.
 *
 * @package ZignitesSentinel
 */

namespace Zignites\Sentinel\Snapshots;

defined( 'ABSPATH' ) || exit;

class SnapshotExportManager {

	/**
	 * Relative export directory under uploads.
	 *
	 * @var string
	 */
	const EXPORT_DIRECTORY = 'zignites-sentinel/snapshots';

	/**
	 * Create a JSON export for a snapshot and return its artifact row.
	 *
	 * @param int   $snapshot_id Snapshot ID.
	 * @param array $snapshot    Snapshot payload.
	 * @param array $artifacts   Component artifact rows.
	 * @return array|null
	 */
	public function create_snapshot_export( $snapshot_id, array $snapshot, array $artifacts ) {
		$snapshot_id = absint( $snapshot_id );

		if ( $snapshot_id < 1 ) {
			return null;
		}

		$base_dir = $this->ensure_export_directory();

		if ( empty( $base_dir ) ) {
			return null;
		}

		$relative_path = trailingslashit( self::EXPORT_DIRECTORY ) . 'snapshot-' . $snapshot_id . '.json';
		$absolute_path = $this->resolve_export_path( $relative_path );

		if ( empty( $absolute_path ) ) {
			return null;
		}

		$payload = array(
			'generated_at' => current_time( 'mysql', true ),
			'snapshot'     => $snapshot,
			'artifacts'    => $artifacts,
		);

		$encoded = wp_json_encode( $payload, JSON_PRETTY_PRINT );

		if ( ! is_string( $encoded ) || '' === $encoded ) {
			return null;
		}

		$written = file_put_contents( $absolute_path, $encoded );

		if ( false === $written ) {
			return null;
		}

		return array(
			'artifact_type' => 'export',
			'artifact_key'  => 'snapshot-export',
			'label'         => __( 'Snapshot export JSON', 'zignites-sentinel' ),
			'version'       => '',
			'source_path'   => $relative_path,
			'created_at'    => current_time( 'mysql', true ),
			'metadata'      => wp_json_encode(
				array(
					'sha256'     => hash_file( 'sha256', $absolute_path ),
					'size_bytes' => filesize( $absolute_path ),
				)
			),
		);
	}

	/**
	 * Resolve a stored export path to an absolute path.
	 *
	 * @param string $relative_path Relative export path.
	 * @return string
	 */
	public function resolve_export_path( $relative_path ) {
		$relative_path = ltrim( wp_normalize_path( (string) $relative_path ), '/' );
		$uploads       = wp_upload_dir();

		if ( ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) ) {
			return '';
		}

		return trailingslashit( wp_normalize_path( $uploads['basedir'] ) ) . $relative_path;
	}

	/**
	 * Inspect a stored export artifact.
	 *
	 * @param array $artifact Export artifact row.
	 * @return array
	 */
	public function inspect_export_artifact( array $artifact ) {
		$relative_path = isset( $artifact['source_path'] ) ? sanitize_text_field( (string) $artifact['source_path'] ) : '';
		$absolute_path = $this->resolve_export_path( $relative_path );
		$metadata      = array();

		if ( ! empty( $artifact['metadata'] ) ) {
			$decoded = json_decode( (string) $artifact['metadata'], true );
			$metadata = is_array( $decoded ) ? $decoded : array();
		}

		if ( empty( $absolute_path ) || ! file_exists( $absolute_path ) ) {
			return array(
				'status'  => 'fail',
				'message' => __( 'The stored snapshot export file is missing.', 'zignites-sentinel' ),
				'details' => array(
					'absolute_path' => $absolute_path,
				),
			);
		}

		if ( ! is_readable( $absolute_path ) ) {
			return array(
				'status'  => 'fail',
				'message' => __( 'The stored snapshot export file is not readable.', 'zignites-sentinel' ),
				'details' => array(
					'absolute_path' => $absolute_path,
				),
			);
		}

		$expected_hash = isset( $metadata['sha256'] ) ? sanitize_text_field( (string) $metadata['sha256'] ) : '';
		$current_hash  = hash_file( 'sha256', $absolute_path );

		if ( ! empty( $expected_hash ) && is_string( $current_hash ) && $expected_hash !== $current_hash ) {
			return array(
				'status'  => 'fail',
				'message' => __( 'The stored snapshot export file hash no longer matches the recorded payload.', 'zignites-sentinel' ),
				'details' => array(
					'absolute_path' => $absolute_path,
					'expected_hash' => $expected_hash,
					'current_hash'  => $current_hash,
				),
			);
		}

		return array(
			'status'  => 'pass',
			'message' => __( 'The stored snapshot export file is present and readable.', 'zignites-sentinel' ),
			'details' => array(
				'absolute_path' => $absolute_path,
				'current_hash'  => is_string( $current_hash ) ? $current_hash : '',
				'size_bytes'    => filesize( $absolute_path ),
			),
		);
	}

	/**
	 * Delete filesystem exports referenced by artifact rows.
	 *
	 * @param array $artifacts Artifact rows.
	 * @return void
	 */
	public function delete_artifact_files( array $artifacts ) {
		foreach ( $artifacts as $artifact ) {
			if ( empty( $artifact['artifact_type'] ) || 'export' !== $artifact['artifact_type'] ) {
				continue;
			}

			$absolute_path = $this->resolve_export_path( isset( $artifact['source_path'] ) ? $artifact['source_path'] : '' );

			if ( ! empty( $absolute_path ) && file_exists( $absolute_path ) ) {
				wp_delete_file( $absolute_path );
			}
		}
	}

	/**
	 * Delete the plugin export directory from uploads.
	 *
	 * @return void
	 */
	public function delete_export_directory() {
		$base_dir = $this->get_export_directory();

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
	 * Ensure the export directory exists.
	 *
	 * @return string
	 */
	protected function ensure_export_directory() {
		$base_dir = $this->get_export_directory();

		if ( empty( $base_dir ) ) {
			return '';
		}

		if ( is_dir( $base_dir ) ) {
			return $base_dir;
		}

		if ( wp_mkdir_p( $base_dir ) ) {
			return $base_dir;
		}

		return '';
	}

	/**
	 * Get the absolute export directory.
	 *
	 * @return string
	 */
	protected function get_export_directory() {
		$uploads = wp_upload_dir();

		if ( ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) ) {
			return '';
		}

		return trailingslashit( wp_normalize_path( $uploads['basedir'] ) ) . self::EXPORT_DIRECTORY;
	}
}
