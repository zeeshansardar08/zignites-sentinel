<?php
/**
 * Sample config for tests/export-event-logs-live.php.
 *
 * Copy this file outside version control or pass values directly with CLI flags.
 * Do not commit real browser cookies.
 */

return array(
	'base_url'      => 'http://example.test/wp-admin/',
	'cookie_header' => 'wordpress_logged_in_example=replace-me; wordpress_sec_example=replace-me;',
	'path'          => 'admin.php?page=zignites-sentinel-event-logs&source=restore-execution-journal&run_id=run-42&snapshot_id=205',
	'timeout'       => 20,
);
