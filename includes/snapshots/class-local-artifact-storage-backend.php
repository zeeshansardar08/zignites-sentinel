<?php
/**
 * Local uploads-backed storage backend for Sentinel artifacts.
 *
 * @package ZignitesSentinel
 */

namespace Zignites\Sentinel\Snapshots;

defined( 'ABSPATH' ) || exit;

class LocalArtifactStorageBackend implements ArtifactStorageBackend {

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
	 * Return a stable backend key.
	 *
	 * @return string
	 */
	public function get_backend_key() {
		return 'local_uploads';
	}

	/**
	 * Whether artifacts are stored outside the local filesystem.
	 *
	 * @return bool
	 */
	public function is_remote() {
		return false;
	}

	/**
	 * Ensure a relative storage directory exists and is guarded.
	 *
	 * @param string $relative_directory Relative directory under uploads.
	 * @return string Absolute directory path, or empty string on failure.
	 */
	public function ensure_directory( $relative_directory ) {
		$relative_directory = trim( wp_normalize_path( (string) $relative_directory ), '/' );
		$absolute_directory = $this->storage_guard->resolve_storage_path( $relative_directory );

		if ( '' === $absolute_directory ) {
			return '';
		}

		return $this->storage_guard->protect_directory( $absolute_directory ) ? $absolute_directory : '';
	}

	/**
	 * Resolve a relative artifact path to an absolute path.
	 *
	 * @param string $relative_path   Relative path under uploads.
	 * @param string $expected_prefix Optional expected prefix.
	 * @return string
	 */
	public function resolve_path( $relative_path, $expected_prefix = '' ) {
		return $this->storage_guard->resolve_storage_path( $relative_path, $expected_prefix );
	}

	/**
	 * Build a public URL candidate for an artifact path.
	 *
	 * @param string $relative_path Relative path under uploads.
	 * @return string
	 */
	public function build_url( $relative_path ) {
		return $this->storage_guard->build_storage_url( $relative_path );
	}

	/**
	 * Return the local storage root when available.
	 *
	 * @return string
	 */
	public function get_storage_root() {
		return $this->storage_guard->get_storage_root();
	}
}
