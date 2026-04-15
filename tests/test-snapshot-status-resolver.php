<?php
/**
 * Focused tests for SnapshotStatusResolver.
 */

require_once __DIR__ . '/bootstrap.php';

use Zignites\Sentinel\Admin\SnapshotStatusResolver;
use Zignites\Sentinel\Logging\LogRepository;
use Zignites\Sentinel\Snapshots\RestoreCheckpointStore;
use Zignites\Sentinel\Snapshots\RestoreExecutor;
use Zignites\Sentinel\Snapshots\RestoreJournalRecorder;
use Zignites\Sentinel\Snapshots\SnapshotArtifactRepository;

class ZNTS_Fake_Log_Repository extends LogRepository {
	public $rows_by_source = array();

	public function get_recent_by_sources( array $sources, $limit = 10 ) {
		$rows = array();

		foreach ( $sources as $source ) {
			if ( isset( $this->rows_by_source[ $source ] ) ) {
				$rows = array_merge( $rows, $this->rows_by_source[ $source ] );
			}
		}

		usort(
			$rows,
			function ( $left, $right ) {
				return strcmp( (string) $right['created_at'], (string) $left['created_at'] );
			}
		);

		return array_slice( $rows, 0, (int) $limit );
	}
}

class ZNTS_Fake_Checkpoint_Store extends RestoreCheckpointStore {
	public $stage = array();
	public $plan  = array();

	public function get_stage_checkpoint( $snapshot_id ) {
		return isset( $this->stage[ (int) $snapshot_id ] ) ? $this->stage[ (int) $snapshot_id ] : array();
	}

	public function get_plan_checkpoint( $snapshot_id ) {
		return isset( $this->plan[ (int) $snapshot_id ] ) ? $this->plan[ (int) $snapshot_id ] : array();
	}
}

class ZNTS_Fake_Journal_Recorder extends RestoreJournalRecorder {
	public $runs = array();

	public function summarize_recent_runs( $source = '', $limit = 250 ) {
		$runs = array_values(
			array_filter(
				$this->runs,
				function ( $run ) use ( $source ) {
					return '' === $source || ( isset( $run['source'] ) && $source === $run['source'] );
				}
			)
		);

		usort(
			$runs,
			function ( $left, $right ) {
				return strcmp( (string) $right['latest_timestamp'], (string) $left['latest_timestamp'] );
			}
		);

		return array_slice( $runs, 0, (int) $limit );
	}
}

class ZNTS_Fake_Artifact_Repository extends SnapshotArtifactRepository {
	public $artifacts_by_snapshot = array();

	public function get_by_snapshot_ids( array $snapshot_ids ) {
		$rows = array();

		foreach ( $snapshot_ids as $snapshot_id ) {
			if ( isset( $this->artifacts_by_snapshot[ (int) $snapshot_id ] ) ) {
				$rows = array_merge( $rows, $this->artifacts_by_snapshot[ (int) $snapshot_id ] );
			}
		}

		return $rows;
	}
}

function znts_assert_true( $condition, $message ) {
	if ( ! $condition ) {
		throw new Exception( $message );
	}
}

function znts_assert_same( $expected, $actual, $message ) {
	if ( $expected !== $actual ) {
		throw new Exception( $message . ' Expected: ' . var_export( $expected, true ) . ' Actual: ' . var_export( $actual, true ) );
	}
}

function znts_build_resolver_fixture() {
	$logs        = new ZNTS_Fake_Log_Repository();
	$checkpoints = new ZNTS_Fake_Checkpoint_Store();
	$journals    = new ZNTS_Fake_Journal_Recorder();
	$artifacts   = new ZNTS_Fake_Artifact_Repository();

	return array(
		'logs'        => $logs,
		'checkpoints' => $checkpoints,
		'journals'    => $journals,
		'artifacts'   => $artifacts,
		'resolver'    => new SnapshotStatusResolver( $logs, $checkpoints, $journals, $artifacts ),
	);
}

function znts_test_snapshot_status_restores_ready_when_all_gates_are_current() {
	$GLOBALS['znts_test_options'] = array(
		ZNTS_OPTION_SETTINGS => array(
			'restore_checkpoint_max_age_hours' => 24,
		),
		ZNTS_OPTION_LAST_SNAPSHOT_HEALTH_BASELINE => array(
			'snapshot_id'   => 10,
			'generated_at'  => gmdate( 'Y-m-d H:i:s', time() - 600 ),
			'status'        => 'healthy',
		),
	);

	$fixture = znts_build_resolver_fixture();

	$fixture['checkpoints']->stage[10] = array(
		'snapshot_id'  => 10,
		'generated_at' => gmdate( 'Y-m-d H:i:s', time() - 300 ),
		'status'       => 'ready',
	);
	$fixture['checkpoints']->plan[10] = array(
		'snapshot_id'  => 10,
		'generated_at' => gmdate( 'Y-m-d H:i:s', time() - 300 ),
		'status'       => 'ready',
	);
	$fixture['artifacts']->artifacts_by_snapshot[10] = array(
		array(
			'snapshot_id'   => 10,
			'artifact_type' => 'package',
		),
	);

	$index = $fixture['resolver']->build_snapshot_status_index(
		array(
			array(
				'id'    => 10,
				'label' => 'Snapshot 10',
			),
		)
	);

	znts_assert_true( ! empty( $index[10]['restore_ready'] ), 'Snapshot should be restore ready when baseline, package, stage, and plan are present.' );
	znts_assert_same( 'current', $index[10]['stage']['key'], 'Stage checkpoint should be current.' );
	znts_assert_same( 'current', $index[10]['plan']['key'], 'Plan checkpoint should be current.' );
	znts_assert_true( ! empty( $index[10]['artifacts']['package_present'] ), 'Package artifact should be detected.' );
}

function znts_test_snapshot_filters_and_site_status_detect_missing_package() {
	$GLOBALS['znts_test_options'] = array(
		ZNTS_OPTION_SETTINGS => array(
			'restore_checkpoint_max_age_hours' => 24,
		),
		ZNTS_OPTION_LAST_SNAPSHOT_HEALTH_BASELINE => array(
			'snapshot_id'   => 21,
			'generated_at'  => gmdate( 'Y-m-d H:i:s', time() - 900 ),
			'status'        => 'healthy',
		),
	);

	$fixture = znts_build_resolver_fixture();

	$fixture['checkpoints']->stage[21] = array(
		'snapshot_id'  => 21,
		'generated_at' => gmdate( 'Y-m-d H:i:s', time() - 300 ),
		'status'       => 'ready',
	);
	$fixture['checkpoints']->plan[21] = array(
		'snapshot_id'  => 21,
		'generated_at' => gmdate( 'Y-m-d H:i:s', time() - 300 ),
		'status'       => 'ready',
	);

	$snapshots = array(
		array( 'id' => 21, 'label' => 'No package snapshot', 'created_at' => gmdate( 'Y-m-d H:i:s', time() - 600 ) ),
		array( 'id' => 22, 'label' => 'Fresh package snapshot', 'created_at' => gmdate( 'Y-m-d H:i:s', time() - 1200 ) ),
	);

	$fixture['artifacts']->artifacts_by_snapshot[22] = array(
		array(
			'snapshot_id'   => 22,
			'artifact_type' => 'package',
		),
	);

	$index = $fixture['resolver']->build_snapshot_status_index( $snapshots );

	$filtered = $fixture['resolver']->filter_snapshots( $snapshots, $index, '', 'checkpoint-missing', 12 );
	znts_assert_same( 1, count( $filtered ), 'Only snapshots without a current or historical stage/plan should match the checkpoint-missing filter.' );
	znts_assert_same( 22, (int) $filtered[0]['id'], 'Checkpoint-missing filter should return the snapshot with no stored gates.' );

	$package_filtered = $fixture['resolver']->filter_snapshots( $snapshots, $index, '', 'rollback-package', 12 );
	znts_assert_same( 1, count( $package_filtered ), 'Only snapshots with a package should match the rollback-package filter.' );
	znts_assert_same( 22, (int) $package_filtered[0]['id'], 'Package filter should return the packaged snapshot.' );

	$site_card = $fixture['resolver']->build_site_status_card(
		array(
			'details' => array(
				'open_conflicts' => array(
					'warning'  => 0,
					'error'    => 0,
					'critical' => 0,
				),
			),
		),
		array( $snapshots[0] ),
		$index
	);

	znts_assert_same( 'needs_attention', $site_card['status'], 'Latest snapshot without package should move site status to needs attention.' );
	znts_assert_true(
		false !== strpos( $site_card['recommended_action'], 'Create a Fresh Checkpoint' ),
		'Recommended action should tell operators to capture a fresh snapshot when package protection is missing.'
	);
	znts_assert_same( 'detail', $site_card['primary_action']['target'], 'Primary action should direct the operator back into Before Update when a fresh checkpoint is required.' );
}

function znts_test_site_status_becomes_at_risk_on_recent_failure() {
	$GLOBALS['znts_test_options'] = array(
		ZNTS_OPTION_SETTINGS => array(
			'restore_checkpoint_max_age_hours' => 24,
		),
	);

	$fixture = znts_build_resolver_fixture();

	$fixture['journals']->runs = array(
		array(
			'run_id'           => 'run-1',
			'snapshot_id'      => 30,
			'source'           => RestoreExecutor::JOURNAL_SOURCE,
			'latest_timestamp' => gmdate( 'Y-m-d H:i:s', time() - 120 ),
			'terminal_status'  => 'fail',
			'last_message'     => 'Restore failed.',
		),
	);

	$index = $fixture['resolver']->build_snapshot_status_index(
		array(
			array(
				'id'    => 30,
				'label' => 'Failing snapshot',
			),
		)
	);

	$site_card = $fixture['resolver']->build_site_status_card(
		array(
			'details' => array(
				'open_conflicts' => array(
					'warning'  => 0,
					'error'    => 0,
					'critical' => 0,
				),
			),
		),
		array(
			array(
				'id'    => 30,
				'label' => 'Failing snapshot',
			),
		),
		$index
	);

	znts_assert_same( 'at_risk', $site_card['status'], 'Recent failed restore activity should mark the site as at risk.' );
}

function znts_test_baseline_falls_back_to_snapshot_health_logs() {
	$GLOBALS['znts_test_options'] = array(
		ZNTS_OPTION_SETTINGS => array(
			'restore_checkpoint_max_age_hours' => 24,
		),
		ZNTS_OPTION_LAST_SNAPSHOT_HEALTH_BASELINE => array(),
	);

	$fixture = znts_build_resolver_fixture();

	$fixture['logs']->rows_by_source['snapshot-health'] = array(
		array(
			'event_type' => 'snapshot_health_baseline_captured',
			'created_at' => gmdate( 'Y-m-d H:i:s', time() - 600 ),
			'context'    => wp_json_encode(
				array(
					'snapshot_id' => 41,
					'status'      => 'healthy',
				)
			),
		),
	);

	$index = $fixture['resolver']->build_snapshot_status_index(
		array(
			array(
				'id'    => 41,
				'label' => 'Logged baseline snapshot',
			),
		)
	);

	znts_assert_true( ! empty( $index[41]['baseline']['present'] ), 'Baseline should be considered present when a baseline capture event exists in logs.' );
	znts_assert_same( 'Baseline present', $index[41]['baseline']['label'], 'Baseline label should reflect the fallback log state.' );
}

function znts_test_stale_checkpoint_state_respects_age_limit() {
	$GLOBALS['znts_test_options'] = array(
		ZNTS_OPTION_SETTINGS => array(
			'restore_checkpoint_max_age_hours' => 24,
		),
		ZNTS_OPTION_LAST_SNAPSHOT_HEALTH_BASELINE => array(
			'snapshot_id'  => 51,
			'generated_at' => gmdate( 'Y-m-d H:i:s', time() - 300 ),
			'status'       => 'healthy',
		),
	);

	$fixture = znts_build_resolver_fixture();

	$fixture['checkpoints']->stage[51] = array(
		'snapshot_id'  => 51,
		'generated_at' => gmdate( 'Y-m-d H:i:s', time() - ( 30 * HOUR_IN_SECONDS ) ),
		'status'       => 'ready',
	);
	$fixture['artifacts']->artifacts_by_snapshot[51] = array(
		array(
			'snapshot_id'   => 51,
			'artifact_type' => 'package',
		),
	);

	$index = $fixture['resolver']->build_snapshot_status_index(
		array(
			array(
				'id'    => 51,
				'label' => 'Stale stage snapshot',
			),
		)
	);

	znts_assert_same( 'stale', $index[51]['stage']['key'], 'Expired stage checkpoints should be marked stale.' );
	znts_assert_true( ! empty( $index[51]['has_stale_checkpoint'] ), 'Snapshot should report stale checkpoints when stage has expired.' );

	$filtered = $fixture['resolver']->filter_snapshots(
		array(
			array(
				'id'    => 51,
				'label' => 'Stale stage snapshot',
			),
		),
		$index,
		'',
		'checkpoint-stale',
		12
	);

	znts_assert_same( 1, count( $filtered ), 'Expired checkpoints should match the checkpoint-stale filter.' );
}

function znts_test_site_status_is_stable_when_latest_snapshot_is_ready() {
	$GLOBALS['znts_test_options'] = array(
		ZNTS_OPTION_SETTINGS => array(
			'restore_checkpoint_max_age_hours' => 24,
		),
		ZNTS_OPTION_LAST_SNAPSHOT_HEALTH_BASELINE => array(
			'snapshot_id'  => 61,
			'generated_at' => gmdate( 'Y-m-d H:i:s', time() - 300 ),
			'status'       => 'healthy',
		),
	);

	$fixture = znts_build_resolver_fixture();

	$fixture['checkpoints']->stage[61] = array(
		'snapshot_id'  => 61,
		'generated_at' => gmdate( 'Y-m-d H:i:s', time() - 300 ),
		'status'       => 'ready',
	);
	$fixture['checkpoints']->plan[61] = array(
		'snapshot_id'  => 61,
		'generated_at' => gmdate( 'Y-m-d H:i:s', time() - 300 ),
		'status'       => 'ready',
	);
	$fixture['artifacts']->artifacts_by_snapshot[61] = array(
		array(
			'snapshot_id'   => 61,
			'artifact_type' => 'package',
		),
	);

	$snapshots = array(
		array(
			'id'    => 61,
			'label' => 'Stable snapshot',
			'created_at' => gmdate( 'Y-m-d H:i:s', time() - 600 ),
		),
	);
	$index     = $fixture['resolver']->build_snapshot_status_index( $snapshots );
	$site_card = $fixture['resolver']->build_site_status_card(
		array(
			'details' => array(
				'open_conflicts' => array(
					'warning'  => 0,
					'error'    => 0,
					'critical' => 0,
				),
			),
		),
		$snapshots,
		$index
	);

	znts_assert_same( 'stable', $site_card['status'], 'A fully ready latest snapshot with no conflicts should be stable.' );
	znts_assert_same( 'Stable', $site_card['label'], 'Stable site status should expose the Stable label.' );
	znts_assert_true(
		false !== strpos( $site_card['recommended_action'], 'Open Before Update' ),
		'Stable site status should surface the guarded restore review as the next operator action.'
	);
	znts_assert_same( 'Open Before Update', $site_card['primary_action']['button_label'], 'Stable status should keep Before Update as the next destination.' );
}

function znts_test_snapshot_intelligence_prefers_latest_safe_snapshot() {
	$GLOBALS['znts_test_options'] = array(
		ZNTS_OPTION_SETTINGS => array(
			'restore_checkpoint_max_age_hours' => 24,
		),
		ZNTS_OPTION_LAST_SNAPSHOT_HEALTH_BASELINE => array(
			'snapshot_id'  => 71,
			'generated_at' => gmdate( 'Y-m-d H:i:s', time() - 300 ),
			'status'       => 'healthy',
		),
	);

	$fixture = znts_build_resolver_fixture();

	$fixture['checkpoints']->stage[71] = array(
		'snapshot_id'  => 71,
		'generated_at' => gmdate( 'Y-m-d H:i:s', time() - 300 ),
		'status'       => 'ready',
	);
	$fixture['checkpoints']->plan[71] = array(
		'snapshot_id'  => 71,
		'generated_at' => gmdate( 'Y-m-d H:i:s', time() - 300 ),
		'status'       => 'ready',
	);
	$fixture['artifacts']->artifacts_by_snapshot[71] = array(
		array(
			'snapshot_id'   => 71,
			'artifact_type' => 'package',
		),
	);

	$snapshots = array(
		array(
			'id'         => 71,
			'label'      => 'Recommended snapshot',
			'created_at' => gmdate( 'Y-m-d H:i:s', time() - 600 ),
		),
		array(
			'id'         => 70,
			'label'      => 'Older snapshot',
			'created_at' => gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ),
		),
	);

	$index         = $fixture['resolver']->build_snapshot_status_index( $snapshots );
	$intelligence  = $fixture['resolver']->build_snapshot_intelligence( $snapshots, $index, $snapshots[1] );

	znts_assert_same( 71, (int) $intelligence['recommended_snapshot']['id'], 'Snapshot intelligence should recommend the latest safe snapshot.' );
	znts_assert_same( 'older', $intelligence['selected_snapshot']['relation'], 'Selecting an older snapshot should be called out as older than the recommendation.' );
	znts_assert_true( false !== strpos( $intelligence['selected_snapshot']['message'], 'older' ), 'Selected older snapshots should surface an older-workspace warning.' );
}

function znts_test_system_health_becomes_risky_without_trusted_snapshot() {
	$GLOBALS['znts_test_options'] = array(
		ZNTS_OPTION_SETTINGS => array(
			'restore_checkpoint_max_age_hours' => 24,
		),
	);

	$fixture   = znts_build_resolver_fixture();
	$snapshots = array(
		array(
			'id'         => 81,
			'label'      => 'Unverified snapshot',
			'created_at' => gmdate( 'Y-m-d H:i:s', time() - ( 30 * DAY_IN_SECONDS ) ),
		),
	);
	$index     = $fixture['resolver']->build_snapshot_status_index( $snapshots );
	$health    = $fixture['resolver']->build_system_health_card(
		array(
			'score' => 72,
		),
		$snapshots,
		$index
	);

	znts_assert_same( 'risky', $health['status'], 'System health should become risky when the only available snapshot is stale and unvalidated.' );
	znts_assert_same( 'stale', $health['logic']['freshness'], 'System health should report stale freshness in its scoring logic.' );
	znts_assert_same( 'warning', $health['logic']['readiness'], 'System health should report warning readiness when validation evidence is incomplete but the primary risk comes from stale freshness.' );
}
