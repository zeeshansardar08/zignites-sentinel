<?php
/**
 * Focused tests for snapshot summary composition and Markdown export.
 */

require_once __DIR__ . '/bootstrap.php';

use Zignites\Sentinel\Admin\Admin;
use Zignites\Sentinel\Admin\SnapshotSummaryPresenter;

class ZNTS_Fake_Snapshot_Summary_Resolver {
	public $status_index = array();

	public function build_snapshot_status_index( array $snapshots ) {
		$index = array();

		foreach ( $snapshots as $snapshot ) {
			$snapshot_id = isset( $snapshot['id'] ) ? (int) $snapshot['id'] : 0;

			if ( isset( $this->status_index[ $snapshot_id ] ) ) {
				$index[ $snapshot_id ] = $this->status_index[ $snapshot_id ];
			}
		}

		return $index;
	}
}

class ZNTS_Testable_Snapshot_Summary_Admin extends Admin {
	public $fixture = array();

	public function __construct() {
		$this->snapshot_status_resolver = new ZNTS_Fake_Snapshot_Summary_Resolver();
		$this->status_presenter         = new \Zignites\Sentinel\Admin\StatusPresenter();
		$this->snapshot_summary_presenter = new SnapshotSummaryPresenter();
	}

	public function set_status_index( array $status_index ) {
		$this->snapshot_status_resolver->status_index = $status_index;
	}

	public function build_summary( array $snapshot ) {
		return $this->get_snapshot_summary( $snapshot );
	}

	public function build_markdown( array $summary ) {
		return $this->build_snapshot_summary_markdown( $summary );
	}

	protected function get_snapshot_artifacts( $snapshot ) {
		return isset( $this->fixture['artifacts'] ) ? $this->fixture['artifacts'] : array();
	}

	protected function get_snapshot_activity( $snapshot ) {
		return isset( $this->fixture['activity'] ) ? $this->fixture['activity'] : array();
	}

	protected function get_restore_operator_checklist( $snapshot, $artifacts = null ) {
		return array_key_exists( 'operator_checklist', $this->fixture ) ? $this->fixture['operator_checklist'] : array();
	}

	protected function get_last_restore_check( $snapshot ) {
		return array_key_exists( 'restore_check', $this->fixture ) ? $this->fixture['restore_check'] : array();
	}

	protected function get_last_restore_stage( $snapshot ) {
		return array_key_exists( 'restore_stage', $this->fixture ) ? $this->fixture['restore_stage'] : array();
	}

	protected function get_last_restore_plan( $snapshot ) {
		return array_key_exists( 'restore_plan', $this->fixture ) ? $this->fixture['restore_plan'] : array();
	}

	protected function get_last_restore_execution( $snapshot ) {
		return array_key_exists( 'last_execution', $this->fixture ) ? $this->fixture['last_execution'] : array();
	}

	protected function get_last_restore_rollback( $snapshot ) {
		return array_key_exists( 'last_rollback', $this->fixture ) ? $this->fixture['last_rollback'] : array();
	}

	protected function get_snapshot_health_baseline( $snapshot ) {
		return array_key_exists( 'baseline', $this->fixture ) ? $this->fixture['baseline'] : array();
	}

	protected function get_restore_stage_checkpoint( $snapshot ) {
		return isset( $this->fixture['stage_checkpoint'] ) ? $this->fixture['stage_checkpoint'] : array();
	}

	protected function get_restore_plan_checkpoint( $snapshot ) {
		return isset( $this->fixture['plan_checkpoint'] ) ? $this->fixture['plan_checkpoint'] : array();
	}

	protected function get_checkpoint_timing_summary( array $checkpoint ) {
		if ( isset( $checkpoint['timing'] ) && is_array( $checkpoint['timing'] ) ) {
			return $checkpoint['timing'];
		}

		return parent::get_checkpoint_timing_summary( $checkpoint );
	}
}

function znts_test_snapshot_summary_collects_risks_and_next_steps_from_current_state() {
	$admin = new ZNTS_Testable_Snapshot_Summary_Admin();
	$admin->fixture = array(
		'artifacts' => array(
			array( 'artifact_type' => 'export' ),
			array( 'artifact_type' => 'component' ),
		),
		'activity' => array(
			array(
				'created_at' => '2025-01-02 09:00:00',
				'source'     => 'restore-readiness',
				'message'    => 'Restore readiness found warnings.',
			),
		),
		'operator_checklist' => array(
			'can_execute' => false,
		),
		'restore_check' => array(
			'status' => 'caution',
			'note'   => 'Filesystem drift still needs review.',
		),
		'restore_stage' => array(
			'status' => 'caution',
		),
		'restore_plan' => array(
			'status' => 'ready',
		),
		'last_execution' => array(
			'status' => 'partial',
			'note'   => 'Previous restore stopped after writing some payloads.',
		),
		'last_rollback' => array(),
		'baseline' => array(),
		'stage_checkpoint' => array(
			'timing' => array(
				'label' => 'Expired 2h ago.',
			),
		),
		'plan_checkpoint' => array(
			'timing' => array(
				'label' => 'Expires in 20h.',
			),
		),
	);
	$admin->set_status_index(
		array(
		88 => array(
			'restore_ready' => false,
			'status_badges' => array(
				array( 'badge' => 'warning', 'label' => 'Baseline missing' ),
				array( 'badge' => 'warning', 'label' => 'Stage stale' ),
			),
			'artifacts' => array(
				'package_present' => false,
			),
			'stage' => array(
				'key'   => 'stale',
				'label' => 'Stage stale',
			),
			'plan' => array(
				'key'   => 'current',
				'label' => 'Plan fresh',
			),
		),
		)
	);

	$snapshot = array(
		'id'                     => 88,
		'label'                  => 'Snapshot 88',
		'created_at'             => '2025-01-01 08:00:00',
		'theme_stylesheet'       => 'sentinel-theme',
		'core_version'           => '6.7.1',
		'php_version'            => '8.2.0',
		'active_plugins_decoded' => array(
			array( 'plugin' => 'a/a.php' ),
			array( 'plugin' => 'b/b.php' ),
		),
	);

	$summary = $admin->build_summary( $snapshot );

	znts_assert_same( 'Snapshot 88', $summary['title'], 'Snapshot summary should use the snapshot label as its title.' );
	znts_assert_same( 88, $summary['snapshot_id'], 'Snapshot summary should expose the snapshot ID.' );
	znts_assert_same( 2, $summary['plugin_count'], 'Snapshot summary should count decoded active plugins.' );
	znts_assert_true( false !== strpos( $summary['overview'][2]['value'], '2 total artifacts' ), 'Snapshot summary should summarize artifact counts.' );
	znts_assert_true( in_array( 'No rollback package is currently saved for this snapshot.', $summary['risks'], true ), 'Snapshot summary should flag a missing rollback package as a risk.' );
	znts_assert_true( in_array( 'No health baseline has been captured for this snapshot yet.', $summary['risks'], true ), 'Snapshot summary should flag a missing baseline as a risk.' );
	znts_assert_true( in_array( 'The staged validation checkpoint is missing or stale.', $summary['risks'], true ), 'Snapshot summary should flag stale stage checkpoints as a risk.' );
	znts_assert_true( in_array( 'Filesystem drift still needs review.', $summary['risks'], true ), 'Snapshot summary should carry forward restore readiness notes as risks.' );
	znts_assert_true( in_array( 'Previous restore stopped after writing some payloads.', $summary['risks'], true ), 'Snapshot summary should surface partial restore notes as risks.' );
	znts_assert_true( in_array( 'Capture a health baseline before any guarded restore work.', $summary['next_steps'], true ), 'Snapshot summary should recommend capturing a baseline when it is missing.' );
	znts_assert_true( in_array( 'Run staged restore validation to refresh the stage checkpoint.', $summary['next_steps'], true ), 'Snapshot summary should recommend refreshing the stage checkpoint when stale.' );
	znts_assert_true( in_array( 'Review the last restore readiness findings and resolve the flagged issues.', $summary['next_steps'], true ), 'Snapshot summary should recommend reviewing readiness warnings.' );
	znts_assert_true( in_array( 'Review the last restore execution result and its backup context before running anything again.', $summary['next_steps'], true ), 'Snapshot summary should recommend reviewing partial execution results.' );
}

function znts_test_snapshot_summary_markdown_renders_sections_and_empty_fallbacks() {
	$admin    = new ZNTS_Testable_Snapshot_Summary_Admin();
	$markdown = $admin->build_markdown(
		array(
			'title'        => 'Snapshot 90',
			'snapshot_id'  => 90,
			'generated_at' => '2025-01-03 10:00:00',
			'created_at'   => '2025-01-01 10:00:00',
			'overview'     => array(
				array(
					'label' => 'Restore status',
					'value' => 'Restore ready',
					'note'  => 'All current restore gates pass.',
				),
			),
			'evidence'     => array(
				array(
					'label' => 'Baseline status',
					'value' => 'healthy',
					'note'  => 'Captured on 2025-01-03 09:00:00.',
				),
			),
			'risks'        => array(),
			'next_steps'   => array(
				'No immediate action is required. Keep the current evidence fresh before future update or restore work.',
			),
			'activity'     => array(),
		)
	);

	znts_assert_true( false !== strpos( $markdown, '# Snapshot 90' ), 'Snapshot summary markdown should render the title as an H1.' );
	znts_assert_true( false !== strpos( $markdown, '## Overview' ), 'Snapshot summary markdown should render the Overview section.' );
	znts_assert_true( false !== strpos( $markdown, '## Evidence' ), 'Snapshot summary markdown should render the Evidence section.' );
	znts_assert_true( false !== strpos( $markdown, '## Risks' ), 'Snapshot summary markdown should render the Risks section.' );
	znts_assert_true( false !== strpos( $markdown, '## Recommended Next Steps' ), 'Snapshot summary markdown should render the next steps section.' );
	znts_assert_true( false !== strpos( $markdown, '## Recent Activity' ), 'Snapshot summary markdown should render the recent activity section.' );
	znts_assert_true( false !== strpos( $markdown, 'No material risks are highlighted by the current snapshot summary.' ), 'Snapshot summary markdown should emit the empty-risk fallback when no risks are present.' );
	znts_assert_true( false !== strpos( $markdown, 'No recent snapshot-scoped events were recorded.' ), 'Snapshot summary markdown should emit the empty-activity fallback when no activity is present.' );
}

function znts_test_snapshot_summary_handles_null_baseline_without_fatal_error() {
	$admin = new ZNTS_Testable_Snapshot_Summary_Admin();
	$admin->fixture = array(
		'artifacts' => array(
			array( 'artifact_type' => 'package' ),
			array( 'artifact_type' => 'component' ),
		),
		'activity' => array(),
		'operator_checklist' => array(
			'can_execute' => false,
		),
		'restore_check' => null,
		'restore_stage' => null,
		'restore_plan' => null,
		'last_execution' => null,
		'last_rollback' => null,
		'baseline' => null,
		'stage_checkpoint' => array(),
		'plan_checkpoint' => array(),
	);
	$admin->set_status_index(
		array(
			91 => array(
				'restore_ready' => false,
				'status_badges' => array(
					array( 'badge' => 'warning', 'label' => 'Baseline missing' ),
				),
				'artifacts' => array(
					'package_present' => true,
				),
				'stage' => array(
					'key'   => 'missing',
					'label' => 'Stage missing',
				),
				'plan' => array(
					'key'   => 'missing',
					'label' => 'Plan missing',
				),
			),
		)
	);

	$snapshot = array(
		'id'                     => 91,
		'label'                  => 'Snapshot 91',
		'created_at'             => '2025-01-04 08:00:00',
		'theme_stylesheet'       => 'sentinel-theme',
		'core_version'           => '6.7.1',
		'php_version'            => '8.2.0',
		'active_plugins_decoded' => array(),
	);

	$summary = $admin->build_summary( $snapshot );

	znts_assert_same( 'Not captured', $summary['evidence'][0]['value'], 'Snapshot summary should show an uncaptured baseline when baseline data is null.' );
	znts_assert_same( 'No baseline is currently stored for this snapshot.', $summary['evidence'][0]['note'], 'Snapshot summary should explain that no baseline is stored when the baseline payload is null.' );
	znts_assert_same( 'Not evaluated', $summary['evidence'][1]['value'], 'Snapshot summary should show restore readiness as not evaluated when restore check data is null.' );
	znts_assert_same( 'Run restore readiness when you need a current advisory check.', $summary['evidence'][1]['note'], 'Snapshot summary should show the default restore readiness note when restore check data is null.' );
	znts_assert_true( in_array( 'No health baseline has been captured for this snapshot yet.', $summary['risks'], true ), 'Snapshot summary should flag a missing baseline when baseline data is null.' );
	znts_assert_true( in_array( 'Capture a health baseline before any guarded restore work.', $summary['next_steps'], true ), 'Snapshot summary should recommend capturing a baseline when baseline data is null.' );
}
