<?php
/**
 * Sample config for tests/smoke-admin-live.php.
 *
 * Copy this file outside version control or pass values directly with CLI flags.
 * Do not commit real browser cookies.
 */

return array(
	'base_url'      => 'http://example.test/wp-admin/',
	'cookie_header' => 'wordpress_logged_in_example=replace-me; wordpress_sec_example=replace-me;',
	'checks'        => array(
		array(
			'label'   => 'Sentinel Dashboard',
			'path'    => 'admin.php?page=zignites-sentinel',
			'markers' => array( 'Site Status', 'Recommended action', 'Recent Snapshots' ),
		),
		array(
			'label'   => 'Update Readiness',
			'path'    => 'admin.php?page=zignites-sentinel-update-readiness',
			'markers' => array( 'Update Readiness', 'Recent Snapshot Metadata', 'Sentinel Settings' ),
		),
		array(
			'label'   => 'Event Logs',
			'path'    => 'admin.php?page=zignites-sentinel-event-logs',
			'markers' => array( 'Event Logs', 'Export Filtered CSV', 'Filter' ),
		),
		array(
			'label'   => 'WordPress Dashboard Widget',
			'path'    => 'index.php',
			'markers' => array( 'Sentinel', 'Recommended action' ),
		),
	),
);
