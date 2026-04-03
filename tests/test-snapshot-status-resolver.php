<?php
/**
 * Focused tests for SnapshotStatusResolver.
 */

require_once __DIR__ . '/bootstrap.php';

use Zignites\Sentinel\Admin\SnapshotStatusResolver;
use Zignites\Sentinel\Logging\LogRepository;
use Zignites\Sentinel\Snapshots\RestoreCheckpointStore;
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
		array( 'id' => 21, 'label' => 'No package snapshot' ),
		array( 'id' => 22, 'label' => 'Fresh package snapshot' ),
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
		false !== strpos( $site_card['recommended_action'], 'rollback package' ),
		'Recommended action should mention creating a fresh snapshot when package is missing.'
	);
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
			'source'           => 'restore-execution',
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
