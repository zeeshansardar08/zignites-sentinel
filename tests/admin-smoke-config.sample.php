<?php
/**
 * Sample config for tests/smoke-admin-live.php.
 *
 * Copy this file to tests/admin-smoke-config.php or tests/admin-smoke-config.local.php,
 * or pass values directly with CLI flags.
 * Do not commit real browser cookies.
 */

return array(
	'base_url'      => 'http://example.test/wp-admin/',
	'cookie_header' => 'wordpress_logged_in_example=replace-me; wordpress_sec_example=replace-me;',
	'timeout'       => 20,
	'checks'        => array(
		array(
			'label'   => 'Sentinel Dashboard',
			'path'    => 'admin.php?page=zignites-sentinel',
			'markers' => array( 'Zignites Sentinel', 'Start Here', 'What Sentinel is designed to do', 'Latest Checkpoint', 'Recent History' ),
		),
		array(
			'label'   => 'Sentinel Dashboard First Run',
			'path'    => 'admin.php?page=zignites-sentinel&znts_capture=first-run',
			'markers' => array( 'Zignites Sentinel', 'Create Your First Checkpoint', 'Start Here', 'No checkpoints' ),
		),
		array(
			'label'   => 'Update Readiness',
			'path'    => 'admin.php?page=zignites-sentinel-update-readiness',
			'markers' => array( 'Before Update', 'How Sentinel Works', 'What Sentinel is designed to do', 'Create Checkpoint', 'Saved Checkpoints' ),
		),
		array(
			'label'   => 'Selected Snapshot Detail',
			'resolve' => array(
				'path'       => 'admin.php?page=zignites-sentinel-update-readiness',
				'query_args' => array(
					'page'        => 'zignites-sentinel-update-readiness',
					'snapshot_id' => true,
				),
			),
			'markers' => array( 'Before Update', 'Validate Checkpoint', 'Restore Checkpoint', 'Saved Checkpoints', 'Recent History' ),
			'optional_markers' => array( 'Restore Result', 'Rollback Last Restore', 'Rollback Result' ),
		),
		array(
			'label'   => 'Dashboard Snapshot History',
			'resolve' => array(
				'path'       => 'admin.php?page=zignites-sentinel',
				'query_args' => array(
					'page'        => 'zignites-sentinel-event-logs',
					'snapshot_id' => true,
				),
			),
			'markers' => array( 'History', 'Recent History', 'Filter', 'Checkpoint ID', 'Reset' ),
		),
		array(
			'label'   => 'History',
			'path'    => 'admin.php?page=zignites-sentinel-event-logs',
			'markers' => array( 'History', 'Recent History', 'Filter', 'Checkpoint ID', 'Reset' ),
		),
		array(
			'label'   => 'History Empty State',
			'path'    => 'admin.php?page=zignites-sentinel-event-logs&log_search=znts-smoke-empty-state-token-9f3a0d66',
			'markers' => array( 'History', 'Recent History', 'Filter', 'Reset', 'No history entries match the current filters.' ),
		),
		array(
			'label'            => 'Event Log Detail',
			'resolve'          => array(
				'path'       => 'admin.php?page=zignites-sentinel-event-logs',
				'query_args' => array(
					'page'   => 'zignites-sentinel-event-logs',
					'log_id' => true,
				),
				'source_markers' => array( 'Recent History' ),
			),
			'resolve_optional' => true,
			'markers'          => array( 'History', 'Event Detail', 'Context' ),
		),
		array(
			'label'            => 'Widget Snapshot Activity',
			'resolve'          => array(
				'path'       => 'index.php',
				'query_args' => array(
					'page'        => 'zignites-sentinel-event-logs',
					'snapshot_id' => true,
				),
			),
			'resolve_optional' => true,
			'markers'          => array( 'History', 'Recent History', 'Filter', 'Checkpoint ID', 'Reset' ),
		),
		array(
			'label'   => 'WordPress Dashboard Widget',
			'path'    => 'index.php',
			'markers' => array( 'Sentinel', 'Next step' ),
		),
	),
);
