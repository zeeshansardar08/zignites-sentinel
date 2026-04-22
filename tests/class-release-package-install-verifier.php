<?php
/**
 * Helpers for release package install verification.
 */

class ZNTS_Release_Package_Install_Verifier {

	/**
	 * Normalize a plugin basename for comparison and validation.
	 *
	 * @param string $plugin Plugin basename.
	 * @return string
	 */
	public static function normalize_plugin_basename( $plugin ) {
		$plugin = str_replace( '\\', '/', trim( (string) $plugin ) );
		$plugin = preg_replace( '#/+#', '/', $plugin );

		return trim( (string) $plugin, '/' );
	}

	/**
	 * Build the plugins directory for a WordPress root.
	 *
	 * @param string $wp_root WordPress root path.
	 * @return string
	 */
	public static function build_plugins_directory( $wp_root ) {
		return rtrim( str_replace( '\\', '/', (string) $wp_root ), '/' ) . '/wp-content/plugins';
	}

	/**
	 * Build a plugin basename from a slug and main file.
	 *
	 * @param string $plugin_slug Plugin directory slug.
	 * @param string $main_file   Main plugin file.
	 * @return string
	 */
	public static function build_plugin_basename( $plugin_slug, $main_file = 'zignites-sentinel.php' ) {
		$plugin_slug = sanitize_title( (string) $plugin_slug );
		$main_file   = basename( (string) $main_file );

		return self::normalize_plugin_basename( $plugin_slug . '/' . $main_file );
	}

	/**
	 * Validate that a plugin basename stays inside the plugins directory.
	 *
	 * @param string $plugin_basename Plugin basename.
	 * @return bool
	 */
	public static function is_valid_plugin_basename( $plugin_basename ) {
		$plugin_basename = self::normalize_plugin_basename( $plugin_basename );

		if ( 1 !== preg_match( '#^[A-Za-z0-9._-]+/[A-Za-z0-9._-]+\.php$#', $plugin_basename ) ) {
			return false;
		}

		$segments = explode( '/', $plugin_basename );

		foreach ( $segments as $segment ) {
			if ( '.' === $segment || '..' === $segment || '' === $segment ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Resolve a plugin basename to an absolute plugin file path.
	 *
	 * @param string $wp_root          WordPress root path.
	 * @param string $plugin_basename  Plugin basename.
	 * @return string
	 */
	public static function resolve_plugin_file( $wp_root, $plugin_basename ) {
		$plugin_basename = self::normalize_plugin_basename( $plugin_basename );

		if ( ! self::is_valid_plugin_basename( $plugin_basename ) ) {
			return '';
		}

		return self::build_plugins_directory( $wp_root ) . '/' . $plugin_basename;
	}
}
