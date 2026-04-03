<?php
/**
 * Activation routines.
 *
 * @package ZignitesSentinel
 */

namespace Zignites\Sentinel\Core;

defined( 'ABSPATH' ) || exit;

class Activator {

	/**
	 * Run plugin activation tasks.
	 *
	 * @return void
	 */
	public static function activate() {
		Installer::install();
	}
}
