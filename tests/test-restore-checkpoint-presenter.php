<?php
/**
 * Focused tests for restore checkpoint presenter payloads.
 */

require_once __DIR__ . '/bootstrap.php';

use Zignites\Sentinel\Admin\RestoreCheckpointPresenter;
use Zignites\Sentinel\Admin\StatusPresenter;

function znts_test_restore_checkpoint_presenter_builds_timing_and_gate_summaries() {
	$presenter = new RestoreCheckpointPresenter( new StatusPresenter() );

	$timing = $presenter->build_timing_summary(
		array(
			'generated_at' => '2025-01-06 08:00:00',
			'status'       => 'ready',
		),
		6,
		strtotime( '2025-01-06 10:00:00' )
	);

	$summary = $presenter->build_gate_summary(
		'Missing checkpoint.',
		array(
			'status' => 'ready',
		),
		$timing
	);

	znts_assert_true( $timing['is_fresh'], 'Restore checkpoint presenter should mark checkpoints fresh when they are still within the configured window.' );
	znts_assert_same( 'Expires in 4h 0m.', $timing['label'], 'Restore checkpoint presenter should report remaining freshness in a compact duration format.' );
	znts_assert_same( 'Ready. Expires in 4h 0m.', $summary, 'Restore checkpoint presenter should combine formatted status and timing labels in the gate summary.' );
}

function znts_test_restore_checkpoint_presenter_builds_backup_and_checkpoint_cards() {
	$presenter = new RestoreCheckpointPresenter( new StatusPresenter() );

	$backup_summary = $presenter->build_backup_summary(
		array(
			'id' => 42,
		),
		array(),
		array(),
		array(
			'basedir' => 'D:/uploads',
			'error'   => false,
		)
	);

	$card = $presenter->build_checkpoint_card(
		'Stage Checkpoint',
		array(
			'status'       => 'ready',
			'generated_at' => '2025-01-06 08:00:00',
			'package_fingerprint' => array(
				'source_path' => 'D:/uploads/zignites-sentinel/snapshots/snapshot-42.zip',
			),
		),
		'8 passing checks recorded.',
		array(
			'label' => 'Expires in 4h.',
		)
	);

	znts_assert_true( false !== strpos( $backup_summary, 'snapshot 42' ), 'Restore checkpoint presenter should describe the snapshot-scoped backup directory target.' );
	znts_assert_same( 'info', $card['badge'], 'Restore checkpoint presenter should reuse readiness badge mapping for checkpoint cards.' );
	znts_assert_same( 'Package: D:/uploads/zignites-sentinel/snapshots/snapshot-42.zip Expires in 4h.', $card['secondary'], 'Restore checkpoint presenter should combine package fingerprint and timing details in the card secondary text.' );
}

function znts_test_restore_checkpoint_presenter_builds_run_cards_with_checkpoint_override() {
	$presenter = new RestoreCheckpointPresenter( new StatusPresenter() );

	$card = $presenter->build_run_card(
		'Latest Restore Run',
		array(
			'run_id'       => 'run-exec-9',
			'status'       => 'partial',
			'generated_at' => '2025-01-06 09:00:00',
			'summary'      => array(
				'pass'    => 3,
				'warning' => 1,
				'fail'    => 1,
			),
			'health_verification' => array(
				'status' => 'degraded',
			),
		),
		array(
			'can_resume'           => true,
			'completed_item_count' => 4,
		),
		array(
			'checkpoint' => array(
				'stage_ready'      => true,
				'health_completed' => false,
			),
		),
		'http://example.test/wp-admin/admin.php?page=zignites-sentinel-event-logs&source=restore-execution-journal&run_id=run-exec-9'
	);

	znts_assert_same( 'warning', $card['badge'], 'Restore checkpoint presenter should preserve partial-run badge mapping.' );
	znts_assert_same( 'Stage reuse ready. Health will rerun.', $card['secondary'], 'Restore checkpoint presenter should prioritize execution checkpoint reuse messaging over generic health or resume text.' );
	znts_assert_same( 'Run ID: run-exec-9', $card['link_label'], 'Restore checkpoint presenter should surface the run ID as the card link label.' );
}

function znts_test_restore_checkpoint_presenter_builds_execution_checkpoint_summaries() {
	$presenter = new RestoreCheckpointPresenter( new StatusPresenter() );

	$summary = $presenter->build_execution_checkpoint_summary(
		array(
			'run_id'       => 'run-exec-1',
			'generated_at' => '2025-01-06 10:00:00',
			'checkpoint'   => array(
				'stage_ready'         => true,
				'stage_path'          => 'D:/uploads/zignites-sentinel/staging/run-exec-1',
				'health_completed'    => true,
				'health_verification' => array(
					'status' => 'healthy',
				),
				'items'               => array(
					'plugin-a' => array(
						'phase'            => 'payload_written',
						'backup_completed' => true,
						'write_completed'  => true,
					),
					'plugin-b' => array(
						'phase'            => 'backup_moved',
						'backup_completed' => true,
					),
					'theme-a' => array(
						'phase'  => 'target_reset',
						'status' => 'fail',
					),
				),
			),
		)
	);

	znts_assert_same( 'run-exec-1', $summary['run_id'], 'Restore checkpoint presenter should preserve execution checkpoint run IDs.' );
	znts_assert_true( $summary['stage_ready'], 'Restore checkpoint presenter should preserve stage reuse state in execution summaries.' );
	znts_assert_same( 3, $summary['item_count'], 'Restore checkpoint presenter should count execution checkpoint items.' );
	znts_assert_same( 2, $summary['backup_count'], 'Restore checkpoint presenter should count completed execution backups.' );
	znts_assert_same( 1, $summary['write_count'], 'Restore checkpoint presenter should count completed execution writes.' );
	znts_assert_same( 1, $summary['failed_count'], 'Restore checkpoint presenter should count failed execution items.' );
}

function znts_test_restore_checkpoint_presenter_builds_rollback_checkpoint_summaries() {
	$presenter = new RestoreCheckpointPresenter( new StatusPresenter() );

	$summary = $presenter->build_rollback_checkpoint_summary(
		array(
			'run_id'       => 'run-rollback-7',
			'generated_at' => '2025-01-06 11:00:00',
			'checkpoint'   => array(
				'backup_root' => 'D:/uploads/zignites-sentinel/backups/run-rollback-7',
				'items'       => array(
					'plugin-a' => array(
						'phase'     => 'target_removed',
						'completed' => true,
					),
					'plugin-b' => array(
						'phase'     => 'restore_completed',
						'completed' => true,
					),
					'theme-a' => array(
						'phase'  => 'restore_completed',
						'status' => 'fail',
					),
				),
			),
		)
	);

	znts_assert_same( 'run-rollback-7', $summary['run_id'], 'Restore checkpoint presenter should preserve rollback checkpoint run IDs.' );
	znts_assert_same( 'D:/uploads/zignites-sentinel/backups/run-rollback-7', $summary['backup_root'], 'Restore checkpoint presenter should preserve rollback backup roots.' );
	znts_assert_same( 3, $summary['item_count'], 'Restore checkpoint presenter should count rollback checkpoint items.' );
	znts_assert_same( 2, $summary['completed_count'], 'Restore checkpoint presenter should count completed rollback items.' );
	znts_assert_same( 1, $summary['failed_count'], 'Restore checkpoint presenter should count failed rollback items.' );
}
