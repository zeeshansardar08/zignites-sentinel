<?php
/**
 * Deactivation routines.
 *
 * @package ZignitesSentinel
 */

namespace Zignites\Sentinel\Core;

defined( 'ABSPATH' ) || exit;

class Deactivator {

	/**
	 * Run plugin deactivation tasks.
	 *
	 * @return void
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( 'znts_collect_diagnostics' );
		wp_clear_scheduled_hook( 'znts_cleanup_snapshots' );
	}
}
