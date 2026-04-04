<?php
/**
 * Focused tests for resume-path admin presentation payloads.
 */

require_once __DIR__ . '/bootstrap.php';

use Zignites\Sentinel\Admin\Admin;
use Zignites\Sentinel\Snapshots\RestoreExecutor;
use Zignites\Sentinel\Snapshots\RestoreRollbackManager;

class ZNTS_Fake_Resume_Admin_Journal_Recorder {
	public $contexts = array();

	public function get_resume_context( $source, $snapshot_id, array $result = array() ) {
		$key = sanitize_text_field( (string) $source ) . ':' . (int) $snapshot_id;

		return isset( $this->contexts[ $key ] ) ? $this->contexts[ $key ] : array();
	}

	public function get_latest_run_id( $source, $snapshot_id ) {
		$key = sanitize_text_field( (string) $source ) . ':' . (int) $snapshot_id;

		if ( ! empty( $this->contexts[ $key ]['run_id'] ) ) {
			return (string) $this->contexts[ $key ]['run_id'];
		}

		return '';
	}
}

class ZNTS_Fake_Resume_Admin_Checkpoint_Store {
	public $execution = array();
	public $rollback  = array();

	public function get_execution_checkpoint( $snapshot_id, $run_id ) {
		$key = (int) $snapshot_id . ':' . (string) $run_id;

		return isset( $this->execution[ $key ] ) ? $this->execution[ $key ] : array();
	}

	public function get_rollback_checkpoint( $snapshot_id, $run_id ) {
		$key = (int) $snapshot_id . ':' . (string) $run_id;

		return isset( $this->rollback[ $key ] ) ? $this->rollback[ $key ] : array();
	}
}

class ZNTS_Testable_Resume_Admin_Presentation extends Admin {
	public $fixture = array();

	public function __construct() {
		$this->restore_journal_recorder = new ZNTS_Fake_Resume_Admin_Journal_Recorder();
		$this->restore_checkpoint_store = new ZNTS_Fake_Resume_Admin_Checkpoint_Store();
		$this->status_presenter         = new \Zignites\Sentinel\Admin\StatusPresenter();
	}

	public function set_resume_context( $source, $snapshot_id, array $context ) {
		$key = sanitize_text_field( (string) $source ) . ':' . (int) $snapshot_id;
		$this->restore_journal_recorder->contexts[ $key ] = $context;
	}

	public function set_execution_checkpoint( $snapshot_id, $run_id, array $checkpoint ) {
		$key = (int) $snapshot_id . ':' . (string) $run_id;
		$this->restore_checkpoint_store->execution[ $key ] = $checkpoint;
	}

	public function set_rollback_checkpoint( $snapshot_id, $run_id, array $checkpoint ) {
		$key = (int) $snapshot_id . ':' . (string) $run_id;
		$this->restore_checkpoint_store->rollback[ $key ] = $checkpoint;
	}

	public function build_execution_checkpoint_summary( $snapshot ) {
		return $this->get_restore_execution_checkpoint_summary( $snapshot );
	}

	public function build_rollback_checkpoint_summary( $snapshot ) {
		return $this->get_restore_rollback_checkpoint_summary( $snapshot );
	}

	public function build_rollback_resume_context( $snapshot ) {
		return $this->get_restore_rollback_resume_context( $snapshot );
	}

	public function build_run_cards( $snapshot ) {
		return $this->get_restore_run_cards( $snapshot );
	}

	protected function get_last_restore_execution( $snapshot ) {
		return array_key_exists( 'last_execution', $this->fixture ) ? $this->fixture['last_execution'] : null;
	}

	protected function get_last_restore_rollback( $snapshot ) {
		return array_key_exists( 'last_rollback', $this->fixture ) ? $this->fixture['last_rollback'] : null;
	}

	protected function get_restore_stage_checkpoint( $snapshot ) {
		return array_key_exists( 'stage_checkpoint', $this->fixture ) ? $this->fixture['stage_checkpoint'] : null;
	}

	protected function get_restore_plan_checkpoint( $snapshot ) {
		return array_key_exists( 'plan_checkpoint', $this->fixture ) ? $this->fixture['plan_checkpoint'] : null;
	}

	protected function get_checkpoint_timing_summary( array $checkpoint ) {
		if ( isset( $checkpoint['timing'] ) && is_array( $checkpoint['timing'] ) ) {
			return $checkpoint['timing'];
		}

		return parent::get_checkpoint_timing_summary( $checkpoint );
	}
}

function znts_test_execution_checkpoint_summary_reports_item_counts_and_health_state() {
	$admin = new ZNTS_Testable_Resume_Admin_Presentation();
	$admin->fixture['last_execution'] = array(
		'run_id' => 'run-exec-1',
	);
	$admin->set_execution_checkpoint(
		201,
		'run-exec-1',
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

	$summary = $admin->build_execution_checkpoint_summary( array( 'id' => 201 ) );

	znts_assert_same( 'run-exec-1', $summary['run_id'], 'Execution checkpoint summary should preserve the run ID.' );
	znts_assert_same( '2025-01-06 10:00:00', $summary['generated_at'], 'Execution checkpoint summary should preserve the checkpoint timestamp.' );
	znts_assert_true( $summary['stage_ready'], 'Execution checkpoint summary should report preserved stage readiness.' );
	znts_assert_same( 'D:/uploads/zignites-sentinel/staging/run-exec-1', $summary['stage_path'], 'Execution checkpoint summary should expose the preserved stage path.' );
	znts_assert_true( $summary['health_completed'], 'Execution checkpoint summary should report completed health reuse state.' );
	znts_assert_same( 'healthy', $summary['health_status'], 'Execution checkpoint summary should expose the preserved health status.' );
	znts_assert_same( 3, $summary['item_count'], 'Execution checkpoint summary should count tracked items.' );
	znts_assert_same( 2, $summary['backup_count'], 'Execution checkpoint summary should count completed backups.' );
	znts_assert_same( 1, $summary['write_count'], 'Execution checkpoint summary should count completed writes.' );
	znts_assert_same( 1, $summary['failed_count'], 'Execution checkpoint summary should count failed items.' );
	znts_assert_same( 1, $summary['phase_counts']['payload_written'], 'Execution checkpoint summary should count payload-written phases.' );
}

function znts_test_rollback_resume_context_and_summary_include_checkpoint_counts() {
	$admin = new ZNTS_Testable_Resume_Admin_Presentation();
	$admin->fixture['last_rollback'] = array(
		'run_id' => 'run-rollback-7',
	);
	$admin->set_resume_context(
		RestoreRollbackManager::JOURNAL_SOURCE,
		202,
		array(
			'can_resume'           => true,
			'run_id'               => 'run-rollback-7',
			'completed_item_count' => 2,
			'entry_count'          => 6,
		)
	);
	$admin->set_rollback_checkpoint(
		202,
		'run-rollback-7',
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

	$resume_context = $admin->build_rollback_resume_context( array( 'id' => 202 ) );
	$summary        = $admin->build_rollback_checkpoint_summary( array( 'id' => 202 ) );

	znts_assert_true( $resume_context['can_resume'], 'Rollback resume context should preserve resumable state from the journal recorder.' );
	znts_assert_same( '2025-01-06 11:00:00', $resume_context['checkpoint_generated_at'], 'Rollback resume context should add checkpoint timestamp metadata.' );
	znts_assert_same( 3, $resume_context['checkpoint_item_count'], 'Rollback resume context should expose the rollback checkpoint item count.' );
	znts_assert_same( 2, $resume_context['checkpoint_completed_count'], 'Rollback resume context should count completed rollback checkpoint items.' );
	znts_assert_same( 'run-rollback-7', $summary['run_id'], 'Rollback checkpoint summary should preserve the run ID.' );
	znts_assert_same( 'D:/uploads/zignites-sentinel/backups/run-rollback-7', $summary['backup_root'], 'Rollback checkpoint summary should expose the backup root path.' );
	znts_assert_same( 3, $summary['item_count'], 'Rollback checkpoint summary should count tracked rollback items.' );
	znts_assert_same( 2, $summary['completed_count'], 'Rollback checkpoint summary should count completed rollback items.' );
	znts_assert_same( 1, $summary['failed_count'], 'Rollback checkpoint summary should count failed rollback items.' );
	znts_assert_same( 2, $summary['phase_counts']['restore_completed'], 'Rollback checkpoint summary should aggregate rollback phases.' );
}

function znts_test_restore_run_card_secondary_prefers_execution_checkpoint_reuse_state() {
	$admin = new ZNTS_Testable_Resume_Admin_Presentation();
	$admin->fixture['stage_checkpoint'] = array(
		'status'       => 'ready',
		'generated_at' => '2025-01-06 08:00:00',
		'timing'       => array(
			'label' => 'Expires in 6h.',
		),
		'result'       => array(
			'summary' => array(
				'pass' => 8,
			),
		),
	);
	$admin->fixture['plan_checkpoint'] = array(
		'status'       => 'ready',
		'generated_at' => '2025-01-06 08:15:00',
		'timing'       => array(
			'label' => 'Expires in 6h.',
		),
		'result'       => array(
			'items' => array( 1, 2, 3 ),
		),
	);
	$admin->fixture['last_execution'] = array(
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
	);
	$admin->set_resume_context(
		RestoreExecutor::JOURNAL_SOURCE,
		203,
		array(
			'can_resume'           => true,
			'completed_item_count' => 4,
			'run_id'               => 'run-exec-9',
		)
	);
	$admin->set_execution_checkpoint(
		203,
		'run-exec-9',
		array(
			'run_id'     => 'run-exec-9',
			'checkpoint' => array(
				'stage_ready'      => true,
				'health_completed' => false,
			),
		)
	);

	$cards = $admin->build_run_cards( array( 'id' => 203 ) );
	$card  = $cards[2];

	znts_assert_same( 'Latest Restore Run', $card['title'], 'Run cards should include the latest restore run after the stage and plan checkpoint cards.' );
	znts_assert_same( 'warning', $card['badge'], 'Partial restore runs should map to the warning badge.' );
	znts_assert_same( 'Partial', $card['status_label'], 'Run cards should expose a formatted status label.' );
	znts_assert_same( '3 pass, 1 warning, 1 fail.', $card['primary'], 'Restore run cards should summarize pass, warning, and fail counts.' );
	znts_assert_same( 'Stage reuse ready. Health will rerun.', $card['secondary'], 'Execution checkpoint reuse messaging should override generic resume and health secondary text.' );
	znts_assert_true( false !== strpos( $card['link_url'], 'run_id=run-exec-9' ), 'Restore run cards should link to the run journal.' );
	znts_assert_true( false !== strpos( $card['link_url'], 'snapshot_id=203' ), 'Restore run cards should preserve snapshot scoping in the run journal link.' );
}

function znts_test_rollback_run_card_uses_resume_secondary_when_no_checkpoint_override_exists() {
	$admin = new ZNTS_Testable_Resume_Admin_Presentation();
	$admin->fixture['last_rollback'] = array(
		'run_id'       => 'run-rollback-9',
		'status'       => 'partial',
		'generated_at' => '2025-01-06 12:00:00',
		'summary'      => array(
			'pass'    => 2,
			'warning' => 1,
			'fail'    => 1,
		),
	);
	$admin->set_resume_context(
		RestoreRollbackManager::JOURNAL_SOURCE,
		204,
		array(
			'can_resume'           => true,
			'completed_item_count' => 3,
			'run_id'               => 'run-rollback-9',
		)
	);

	$cards = $admin->build_run_cards( array( 'id' => 204 ) );
	$card  = $cards[0];

	znts_assert_same( 'Latest Rollback Run', $card['title'], 'Rollback-only run cards should still render the latest rollback run.' );
	znts_assert_same( 'Resume available with 3 completed items.', $card['secondary'], 'Rollback run cards should use resume messaging when no checkpoint override exists.' );
	znts_assert_true( false !== strpos( $card['link_url'], 'source=restore-rollback' ), 'Rollback run cards should link to the rollback journal source.' );
}
