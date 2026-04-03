<?php
/**
 * Lightweight autoloader for plugin classes.
 *
 * @package ZignitesSentinel
 */

namespace Zignites\Sentinel;

defined( 'ABSPATH' ) || exit;

class Autoloader {

	/**
	 * Namespace prefix.
	 *
	 * @var string
	 */
	const PREFIX = __NAMESPACE__ . '\\';

	/**
	 * Register the autoloader.
	 *
	 * @return void
	 */
	public static function register() {
		spl_autoload_register( array( __CLASS__, 'autoload' ) );
	}

	/**
	 * Load a namespaced class file.
	 *
	 * @param string $class Class name.
	 * @return void
	 */
	public static function autoload( $class ) {
		if ( 0 !== strpos( $class, self::PREFIX ) ) {
			return;
		}

		$relative_class = substr( $class, strlen( self::PREFIX ) );
		$parts          = explode( '\\', $relative_class );
		$class_name     = array_pop( $parts );
		$directory      = '';

		if ( ! empty( $parts ) ) {
			$directory = strtolower( implode( '/', $parts ) ) . '/';
		}

		$file_name = 'class-' . self::normalize_class_name( $class_name ) . '.php';
		$file_path = ZNTS_PLUGIN_DIR . 'includes/' . $directory . $file_name;

		if ( file_exists( $file_path ) ) {
			require_once $file_path;
		}
	}

	/**
	 * Convert a class basename to a WordPress-style filename.
	 *
	 * @param string $class_name Class basename.
	 * @return string
	 */
	protected static function normalize_class_name( $class_name ) {
		$normalized = preg_replace( '/(?<!^)[A-Z]/', '-$0', $class_name );

		return strtolower( $normalized );
	}
}
