<?php
/**
 * Storage backend contract for sensitive Sentinel artifacts.
 *
 * @package ZignitesSentinel
 */

namespace Zignites\Sentinel\Snapshots;

defined( 'ABSPATH' ) || exit;

interface ArtifactStorageBackend {

	/**
	 * Return a stable backend key.
	 *
	 * @return string
	 */
	public function get_backend_key();

	/**
	 * Whether artifacts are stored outside the local filesystem.
	 *
	 * @return bool
	 */
	public function is_remote();

	/**
	 * Ensure a relative storage directory exists and is guarded.
	 *
	 * @param string $relative_directory Relative directory under uploads.
	 * @return string Absolute directory path, or empty string on failure.
	 */
	public function ensure_directory( $relative_directory );

	/**
	 * Resolve a relative artifact path to an absolute path.
	 *
	 * @param string $relative_path   Relative path under uploads.
	 * @param string $expected_prefix Optional expected prefix.
	 * @return string
	 */
	public function resolve_path( $relative_path, $expected_prefix = '' );

	/**
	 * Build a public URL candidate for an artifact path.
	 *
	 * @param string $relative_path Relative path under uploads.
	 * @return string
	 */
	public function build_url( $relative_path );

	/**
	 * Return the local storage root when available.
	 *
	 * @return string
	 */
	public function get_storage_root();
}
