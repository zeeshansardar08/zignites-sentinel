<?php
/**
 * Sample config for optional native WordPress update-surface smoke checks.
 *
 * Use this alongside tests/smoke-admin-live.php when you want to verify that
 * plugins.php, themes.php, and update-core.php still surface Sentinel cues
 * cleanly during a real update window.
 *
 * The network-admin checks in this sample are only expected to pass on a
 * multisite install where network admin is enabled for the authenticated user.
 *
 * Copy this file outside version control or pass values directly with CLI flags.
 * Do not commit real browser cookies.
 */

return array(
	'base_url'      => 'http://example.test/wp-admin/',
	'cookie_header' => 'wordpress_logged_in_example=replace-me; wordpress_sec_example=replace-me;',
	'checks'        => array(
		array(
			'label'   => 'Plugins Update Surface',
			'path'    => 'plugins.php',
			'markers' => array( 'Plugins' ),
			'optional_markers' => array(
				'Create Fresh Checkpoint',
				'Review Before Update',
				'Open History',
			),
		),
		array(
			'label'   => 'Network Plugins Update Surface',
			'path'    => 'network/plugins.php',
			'markers' => array( 'Plugins' ),
			'skip_on_status_codes' => array( 403, 404, 500 ),
			'skip_reason' => 'Skipped because network admin is not available on this install or for this user.',
			'optional_markers' => array(
				'Create Fresh Checkpoint',
				'Review Before Update',
				'Open History',
			),
		),
		array(
			'label'   => 'Themes Update Surface',
			'path'    => 'themes.php',
			'markers' => array( 'Themes' ),
			'optional_markers' => array(
				'Create Fresh Checkpoint',
				'Review Before Update',
				'Open History',
			),
		),
		array(
			'label'   => 'Network Themes Update Surface',
			'path'    => 'network/themes.php',
			'markers' => array( 'Themes' ),
			'skip_on_status_codes' => array( 403, 404, 500 ),
			'skip_reason' => 'Skipped because network admin is not available on this install or for this user.',
			'optional_markers' => array(
				'Create Fresh Checkpoint',
				'Review Before Update',
				'Open History',
			),
		),
		array(
			'label'   => 'Core Update Surface',
			'path'    => 'update-core.php',
			'markers' => array( 'WordPress Updates' ),
			'optional_markers' => array(
				'Create Fresh Checkpoint',
				'Review Before Update',
				'Open Before Update',
				'Open History',
				'Sentinel does not restore WordPress core updates.',
			),
		),
		array(
			'label'   => 'Network Core Update Surface',
			'path'    => 'network/update-core.php',
			'markers' => array( 'WordPress Updates' ),
			'skip_on_status_codes' => array( 403, 404, 500 ),
			'skip_reason' => 'Skipped because network admin is not available on this install or for this user.',
			'optional_markers' => array(
				'Create Fresh Checkpoint',
				'Review Before Update',
				'Open Before Update',
				'Open History',
				'Sentinel does not restore WordPress core updates.',
			),
		),
	),
);
