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
			'label'   => 'Selected Snapshot Detail',
			'resolve' => array(
				'path'       => 'admin.php?page=zignites-sentinel-update-readiness',
				'query_args' => array(
					'page'        => 'zignites-sentinel-update-readiness',
					'snapshot_id' => true,
				),
			),
			'markers' => array( 'Snapshot Summary', 'Download Summary', 'Snapshot Health Baseline' ),
			'optional_markers' => array( 'Health Comparison', 'Restore Impact Summary' ),
		),
		array(
			'label'   => 'Selected Snapshot Event Logs',
			'resolve' => array(
				'path'       => 'admin.php?page=zignites-sentinel-update-readiness',
				'query_args' => array(
					'page'        => 'zignites-sentinel-event-logs',
					'snapshot_id' => true,
				),
			),
			'markers' => array( 'Event Logs', 'Export Filtered CSV', 'Filter', 'Current filters are active.' ),
		),
		array(
			'label'            => 'Selected Snapshot Run Journal',
			'resolve'          => array(
				'path'       => 'admin.php?page=zignites-sentinel-update-readiness',
				'query_args' => array(
					'page'   => 'zignites-sentinel-event-logs',
					'source' => true,
					'run_id' => true,
				),
			),
			'resolve_optional' => true,
			'markers'          => array( 'Event Logs', 'Export Filtered CSV', 'Filter', 'Current filters are active.', 'Run Journal' ),
		),
		array(
			'label'   => 'Event Logs',
			'path'    => 'admin.php?page=zignites-sentinel-event-logs',
			'markers' => array( 'Event Logs', 'Export Filtered CSV', 'Filter' ),
		),
		array(
			'label'   => 'Event Logs Empty State',
			'path'    => 'admin.php?page=zignites-sentinel-event-logs&log_search=znts-smoke-empty-state-token-9f3a0d66',
			'markers' => array( 'Event Logs', 'Export Filtered CSV', 'Apply Filters', 'Reset', 'Current filters are active.', 'No event logs match the current filters.' ),
		),
		array(
			'label'            => 'Event Log Detail',
			'resolve'          => array(
				'path'       => 'admin.php?page=zignites-sentinel-event-logs',
				'query_args' => array(
					'page'   => 'zignites-sentinel-event-logs',
					'log_id' => true,
				),
				'source_markers' => array( 'Event Explorer' ),
			),
			'resolve_optional' => true,
			'markers'          => array( 'Event Logs', 'Event Detail', 'Context' ),
		),
		array(
			'label'            => 'Event Log Run Summary Journal',
			'resolve'          => array(
				'path'       => 'admin.php?page=zignites-sentinel-event-logs',
				'query_args' => array(
					'page'   => 'zignites-sentinel-event-logs',
					'source' => true,
					'run_id' => true,
				),
				'source_markers' => array( 'Run Summaries' ),
			),
			'resolve_optional' => true,
			'markers'          => array( 'Event Logs', 'Export Filtered CSV', 'Filter', 'Current filters are active.', 'Run Journal' ),
		),
		array(
			'label'            => 'Event Log Run Summary Snapshot',
			'resolve'          => array(
				'path'       => 'admin.php?page=zignites-sentinel-event-logs',
				'query_args' => array(
					'page'        => 'zignites-sentinel-update-readiness',
					'snapshot_id' => true,
				),
				'source_markers' => array( 'Run Summaries' ),
			),
			'resolve_optional' => true,
			'markers'          => array( 'Update Readiness', 'Recent Snapshot Metadata', 'Sentinel Settings', 'Snapshot Summary' ),
		),
		array(
			'label'   => 'Dashboard Snapshot Event Logs',
			'resolve' => array(
				'path'       => 'admin.php?page=zignites-sentinel',
				'query_args' => array(
					'page'        => 'zignites-sentinel-event-logs',
					'snapshot_id' => true,
				),
			),
			'markers' => array( 'Event Logs', 'Export Filtered CSV', 'Filter' ),
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
			'markers'          => array( 'Event Logs', 'Export Filtered CSV', 'Filter', 'Current filters are active.' ),
		),
		array(
			'label'   => 'WordPress Dashboard Widget',
			'path'    => 'index.php',
			'markers' => array( 'Sentinel', 'Recommended action' ),
		),
	),
);
