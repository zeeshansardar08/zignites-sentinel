<?php
/**
 * Focused tests for Sentinel async jobs.
 */

require_once __DIR__ . '/bootstrap.php';

use Zignites\Sentinel\Core\OperationLock;
use Zignites\Sentinel\Jobs\JobRunner;
use Zignites\Sentinel\Jobs\JobStore;
use Zignites\Sentinel\Logging\Logger;
use Zignites\Sentinel\Snapshots\RestoreCheckpointStore;
use Zignites\Sentinel\Snapshots\RestoreExecutionPlanner;
use Zignites\Sentinel\Snapshots\RestoreStagingManager;
use Zignites\Sentinel\Snapshots\SnapshotArtifactRepository;
use Zignites\Sentinel\Snapshots\SnapshotManager;
use Zignites\Sentinel\Snapshots\SnapshotRepository;

class Znts_Test_Snapshot_Manager_For_Jobs extends SnapshotManager {
	public $created = array();
	public $result = 123;
	public $failure_reason = '';

	public function __construct() {}

	public function create_manual_snapshot( $user_id, array $context = array() ) {
		$this->created[] = array(
			'user_id' => $user_id,
			'context' => $context,
		);

		return $this->result;
	}

	public function get_last_failure_reason() {
		return $this->failure_reason;
	}
}

class Znts_Test_Snapshot_Repository_For_Jobs extends SnapshotRepository {
	public function get_by_id( $snapshot_id ) {
		return array(
			'id'             => (int) $snapshot_id,
			'active_plugins' => '[]',
			'metadata'       => '{}',
		);
	}
}

class Znts_Test_Artifact_Repository_For_Jobs extends SnapshotArtifactRepository {
	public function get_by_snapshot_id( $snapshot_id ) {
		return array();
	}
}

class Znts_Test_Restore_Staging_For_Jobs extends RestoreStagingManager {
	public function stage_and_validate( array $snapshot, array $artifacts ) {
		return array(
			'status'            => 'ready',
			'summary'           => array(),
			'cleanup_completed' => true,
		);
	}
}

class Znts_Test_Restore_Planner_For_Jobs extends RestoreExecutionPlanner {
	public function build_plan( array $snapshot, array $artifacts ) {
		return array(
			'status'  => 'ready',
			'summary' => array(),
			'items'   => array(),
		);
	}
}

function znts_test_job_store_tracks_lifecycle_and_retry() {
	$GLOBALS['znts_test_options'] = array();

	$store = new JobStore();
	$job   = $store->enqueue(
		JobRunner::TYPE_SNAPSHOT_CREATE,
		array(
			'user_id' => 7,
		),
		array(
			'max_attempts' => 2,
		)
	);

	znts_assert_same( JobStore::STATUS_PENDING, $job['status'], 'New async jobs should start pending.' );
	znts_assert_true( $store->has_pending_jobs(), 'Pending jobs should be discoverable for runner scheduling.' );

	$claimed = $store->claim_next();
	znts_assert_same( JobStore::STATUS_RUNNING, $claimed['status'], 'Claimed jobs should be marked running.' );
	znts_assert_same( 1, $claimed['attempts'], 'Claiming should increment the attempt counter.' );

	$failed = $store->fail( $claimed['id'], 'Temporary failure.' );
	znts_assert_same( JobStore::STATUS_FAILED, $failed['status'], 'Failed jobs should preserve failed status.' );

	$retry = $store->retry( $failed['id'] );
	znts_assert_same( JobStore::STATUS_PENDING, $retry['status'], 'Retryable failed jobs should return to pending.' );
}

function znts_test_job_runner_completes_snapshot_jobs() {
	$GLOBALS['znts_test_options']          = array();
	$GLOBALS['znts_test_scheduled_events'] = array();

	$store            = new JobStore();
	$snapshot_manager = new Znts_Test_Snapshot_Manager_For_Jobs();
	$runner           = new JobRunner(
		$store,
		$snapshot_manager,
		new Znts_Test_Snapshot_Repository_For_Jobs(),
		new Znts_Test_Artifact_Repository_For_Jobs(),
		new Znts_Test_Restore_Staging_For_Jobs(),
		new Znts_Test_Restore_Planner_For_Jobs(),
		new RestoreCheckpointStore(),
		new OperationLock(),
		new Logger()
	);

	$job = $runner->enqueue(
		JobRunner::TYPE_SNAPSHOT_CREATE,
		array(
			'user_id' => 9,
			'context' => array(
				'source' => 'test',
			),
		)
	);

	znts_assert_true( ! empty( $GLOBALS['znts_test_scheduled_events'][ ZNTS_CRON_ASYNC_JOBS ] ), 'Enqueue should schedule the async job runner.' );

	$completed = $runner->process_next();
	znts_assert_same( JobStore::STATUS_COMPLETED, $completed['status'], 'Successful runner jobs should complete.' );
	znts_assert_same( 123, $completed['result']['snapshot_id'], 'Snapshot jobs should persist the created snapshot ID.' );
	znts_assert_same( 9, $snapshot_manager->created[0]['user_id'], 'Snapshot jobs should run under the queued user ID.' );
	znts_assert_same( 'test', $snapshot_manager->created[0]['context']['source'], 'Snapshot jobs should preserve capture context.' );
	znts_assert_same( false, $store->has_pending_jobs(), 'Completed queues should not leave pending jobs behind.' );
}

function znts_test_job_runner_completes_restore_preparation_jobs() {
	$GLOBALS['znts_test_options'] = array();

	$store  = new JobStore();
	$runner = new JobRunner(
		$store,
		new Znts_Test_Snapshot_Manager_For_Jobs(),
		new Znts_Test_Snapshot_Repository_For_Jobs(),
		new Znts_Test_Artifact_Repository_For_Jobs(),
		new Znts_Test_Restore_Staging_For_Jobs(),
		new Znts_Test_Restore_Planner_For_Jobs(),
		new RestoreCheckpointStore(),
		new OperationLock(),
		new Logger()
	);

	$runner->enqueue(
		JobRunner::TYPE_RESTORE_STAGE,
		array(
			'snapshot_id' => 55,
		)
	);
	$stage = $runner->process_next();

	znts_assert_same( JobStore::STATUS_COMPLETED, $stage['status'], 'Restore staging jobs should complete.' );
	znts_assert_same( 55, $stage['result']['snapshot_id'], 'Restore staging jobs should return their snapshot ID.' );
	znts_assert_same( 55, get_option( ZNTS_OPTION_LAST_RESTORE_STAGE, array() )['snapshot_id'], 'Restore staging jobs should persist the last stage result.' );

	$runner->enqueue(
		JobRunner::TYPE_RESTORE_PLAN,
		array(
			'snapshot_id' => 56,
		)
	);
	$plan = $runner->process_next();

	znts_assert_same( JobStore::STATUS_COMPLETED, $plan['status'], 'Restore plan jobs should complete.' );
	znts_assert_same( 56, $plan['result']['snapshot_id'], 'Restore plan jobs should return their snapshot ID.' );
	znts_assert_same( 56, get_option( ZNTS_OPTION_LAST_RESTORE_PLAN, array() )['snapshot_id'], 'Restore plan jobs should persist the last plan result.' );
}
