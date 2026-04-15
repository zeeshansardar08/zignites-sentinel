<?php
/**
 * Protect filesystem artifact directories created under uploads.
 *
 * @package ZignitesSentinel
 */

namespace Zignites\Sentinel\Snapshots;

defined( 'ABSPATH' ) || exit;

class ArtifactStorageGuard {

	/**
	 * Relative storage root under uploads.
	 *
	 * @var string
	 */
	const STORAGE_ROOT = 'zignites-sentinel';

	/**
	 * Ensure storage directories exist and include basic web-access guards.
	 *
	 * @param string $directory Absolute directory path.
	 * @return bool
	 */
	public function protect_directory( $directory ) {
		$directory = rtrim( wp_normalize_path( (string) $directory ), '/' );

		if ( '' === $directory ) {
			return false;
		}

		if ( ! is_dir( $directory ) && ! wp_mkdir_p( $directory ) ) {
			return false;
		}

		$storage_root = $this->get_storage_root();

		if ( '' === $storage_root ) {
			return false;
		}

		$this->write_guard_files( $storage_root );

		if ( $directory !== $storage_root ) {
			$this->write_guard_files( $directory );
		}

		return true;
	}

	/**
	 * Return the absolute uploads storage root.
	 *
	 * @return string
	 */
	public function get_storage_root() {
		$uploads = wp_upload_dir();

		if ( ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) ) {
			return '';
		}

		return trailingslashit( wp_normalize_path( $uploads['basedir'] ) ) . self::STORAGE_ROOT;
	}

	/**
	 * Resolve a relative uploads artifact path and ensure it stays inside the expected storage prefix.
	 *
	 * @param string $relative_path   Relative path under uploads.
	 * @param string $expected_prefix Expected prefix under uploads.
	 * @return string
	 */
	public function resolve_storage_path( $relative_path, $expected_prefix = '' ) {
		$relative_path = ltrim( wp_normalize_path( (string) $relative_path ), '/' );
		$expected_prefix = trim( wp_normalize_path( (string) $expected_prefix ), '/' );
		$uploads         = wp_upload_dir();

		if ( '' === $relative_path || ! $this->is_safe_relative_path( $relative_path ) ) {
			return '';
		}

		if ( ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) ) {
			return '';
		}

		if (
			'' !== $expected_prefix &&
			$relative_path !== $expected_prefix &&
			0 !== strpos( $relative_path, $expected_prefix . '/' )
		) {
			return '';
		}

		return trailingslashit( wp_normalize_path( $uploads['basedir'] ) ) . $relative_path;
	}

	/**
	 * Determine whether a path stays within an allowed root.
	 *
	 * @param string $path Path to validate.
	 * @param string $root Allowed root.
	 * @return bool
	 */
	public function is_path_within( $path, $root ) {
		$path = rtrim( wp_normalize_path( (string) $path ), '/' );
		$root = rtrim( wp_normalize_path( (string) $root ), '/' );

		if ( '' === $path || '' === $root ) {
			return false;
		}

		return $path === $root || 0 === strpos( $path, $root . '/' );
	}

	/**
	 * Validate a relative storage path.
	 *
	 * @param string $relative_path Relative storage path.
	 * @return bool
	 */
	public function is_safe_relative_path( $relative_path ) {
		$relative_path = ltrim( wp_normalize_path( (string) $relative_path ), '/' );

		if ( '' === $relative_path || false !== strpos( $relative_path, ':' ) ) {
			return false;
		}

		return ! preg_match( '#(^|/)\.\.(/|$)#', $relative_path );
	}

	/**
	 * Write filesystem guard files for a directory.
	 *
	 * @param string $directory Absolute directory path.
	 * @return void
	 */
	protected function write_guard_files( $directory ) {
		$directory = rtrim( wp_normalize_path( (string) $directory ), '/' );

		if ( '' === $directory || ! is_dir( $directory ) ) {
			return;
		}

		$this->write_file_if_missing(
			trailingslashit( $directory ) . 'index.php',
			"<?php\n// Silence is golden.\n"
		);
		$this->write_file_if_missing(
			trailingslashit( $directory ) . '.htaccess',
			"Options -Indexes\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n"
		);
		$this->write_file_if_missing(
			trailingslashit( $directory ) . 'web.config',
			"<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration>\n\t<system.webServer>\n\t\t<authorization>\n\t\t\t<remove users=\"*\" roles=\"\" verbs=\"\" />\n\t\t\t<add accessType=\"Deny\" users=\"*\" />\n\t\t</authorization>\n\t</system.webServer>\n</configuration>\n"
		);
	}

	/**
	 * Write a file once without overwriting existing host-specific rules.
	 *
	 * @param string $path     Absolute file path.
	 * @param string $contents File contents.
	 * @return void
	 */
	protected function write_file_if_missing( $path, $contents ) {
		$path = wp_normalize_path( (string) $path );

		if ( '' === $path || file_exists( $path ) ) {
			return;
		}

		file_put_contents( $path, $contents );
	}
}
