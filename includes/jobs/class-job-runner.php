<?php
/**
 * Async job runner.
 *
 * @package ZignitesSentinel
 */

namespace Zignites\Sentinel\Jobs;

use Exception;
use Throwable;
use Zignites\Sentinel\Core\OperationLock;
use Zignites\Sentinel\Integrations\AlertNotifier;
use Zignites\Sentinel\Logging\Logger;
use Zignites\Sentinel\Snapshots\RestoreCheckpointStore;
use Zignites\Sentinel\Snapshots\RestoreExecutionPlanner;
use Zignites\Sentinel\Snapshots\RestoreStagingManager;
use Zignites\Sentinel\Snapshots\SnapshotArtifactRepository;
use Zignites\Sentinel\Snapshots\SnapshotManager;
use Zignites\Sentinel\Snapshots\SnapshotRepository;

defined( 'ABSPATH' ) || exit;

class JobRunner {

	const TYPE_SNAPSHOT_CREATE = 'snapshot_create';
	const TYPE_RESTORE_STAGE   = 'restore_stage';
	const TYPE_RESTORE_PLAN    = 'restore_plan';

	/**
	 * Job store.
	 *
	 * @var JobStore
	 */
	protected $store;

	/**
	 * Snapshot manager.
	 *
	 * @var SnapshotManager
	 */
	protected $snapshot_manager;

	/**
	 * Snapshot repository.
	 *
	 * @var SnapshotRepository
	 */
	protected $snapshots;

	/**
	 * Artifact repository.
	 *
	 * @var SnapshotArtifactRepository
	 */
	protected $artifacts;

	/**
	 * Restore staging manager.
	 *
	 * @var RestoreStagingManager
	 */
	protected $restore_staging_manager;

	/**
	 * Restore plan builder.
	 *
	 * @var RestoreExecutionPlanner
	 */
	protected $restore_execution_planner;

	/**
	 * Restore checkpoint store.
	 *
	 * @var RestoreCheckpointStore
	 */
	protected $restore_checkpoint_store;

	/**
	 * Operation lock.
	 *
	 * @var OperationLock
	 */
	protected $operation_lock;

	/**
	 * Logger.
	 *
	 * @var Logger
	 */
	protected $logger;

	/**
	 * Alert notifier.
	 *
	 * @var AlertNotifier
	 */
	protected $alert_notifier;

	/**
	 * Constructor.
	 */
	public function __construct(
		JobStore $store,
		SnapshotManager $snapshot_manager,
		SnapshotRepository $snapshots,
		SnapshotArtifactRepository $artifacts,
		RestoreStagingManager $restore_staging_manager,
		RestoreExecutionPlanner $restore_execution_planner,
		RestoreCheckpointStore $restore_checkpoint_store,
		OperationLock $operation_lock,
		Logger $logger,
		AlertNotifier $alert_notifier = null
	) {
		$this->store                     = $store;
		$this->snapshot_manager          = $snapshot_manager;
		$this->snapshots                  = $snapshots;
		$this->artifacts                  = $artifacts;
		$this->restore_staging_manager    = $restore_staging_manager;
		$this->restore_execution_planner  = $restore_execution_planner;
		$this->restore_checkpoint_store   = $restore_checkpoint_store;
		$this->operation_lock             = $operation_lock;
		$this->logger                     = $logger;
		$this->alert_notifier             = $alert_notifier ? $alert_notifier : new AlertNotifier();
	}

	/**
	 * Register cron hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( ZNTS_CRON_ASYNC_JOBS, array( $this, 'process_next' ) );
	}

	/**
	 * Enqueue a job and schedule the runner.
	 *
	 * @param string $type    Job type.
	 * @param array  $payload Job payload.
	 * @param array  $args    Job options.
	 * @return array
	 */
	public function enqueue( $type, array $payload = array(), array $args = array() ) {
		$job = $this->store->enqueue( $type, $payload, $args );
		$this->schedule();

		$this->logger->log(
			'async_job_queued',
			'info',
			'async-jobs',
			__( 'Sentinel queued a background job.', 'zignites-sentinel' ),
			array(
				'job_id' => $job['id'],
				'type'   => $job['type'],
			)
		);

		return $job;
	}

	/**
	 * Schedule processing through WP-Cron.
	 *
	 * @return void
	 */
	public function schedule() {
		if ( function_exists( 'wp_next_scheduled' ) && function_exists( 'wp_schedule_single_event' ) && ! wp_next_scheduled( ZNTS_CRON_ASYNC_JOBS ) ) {
			wp_schedule_single_event( time() + 5, ZNTS_CRON_ASYNC_JOBS );
		}
	}

	/**
	 * Process one queued job.
	 *
	 * @return array|null
	 */
	public function process_next() {
		$job = $this->store->claim_next(
			array(
				self::TYPE_SNAPSHOT_CREATE,
				self::TYPE_RESTORE_STAGE,
				self::TYPE_RESTORE_PLAN,
			)
		);

		if ( ! is_array( $job ) ) {
			return null;
		}

		try {
			$result = $this->dispatch( $job );
			$job    = $this->store->complete( $job['id'], is_array( $result ) ? $result : array() );

			$this->logger->log(
				'async_job_completed',
				'info',
				'async-jobs',
				__( 'Sentinel completed a background job.', 'zignites-sentinel' ),
				array(
					'job_id' => isset( $job['id'] ) ? $job['id'] : '',
					'type'   => isset( $job['type'] ) ? $job['type'] : '',
					'result' => isset( $job['result'] ) ? $job['result'] : array(),
				)
			);
		} catch ( Throwable $exception ) {
			$job = $this->store->fail( $job['id'], $exception->getMessage() );
			$this->logger->log(
				'async_job_failed',
				'error',
				'async-jobs',
				__( 'Sentinel background job failed.', 'zignites-sentinel' ),
				array(
					'job_id' => isset( $job['id'] ) ? $job['id'] : '',
					'type'   => isset( $job['type'] ) ? $job['type'] : '',
					'error'  => $exception->getMessage(),
				)
			);
		}

		if ( $this->store->has_pending_jobs() ) {
			$this->schedule();
		}

		return $job;
	}

	/**
	 * Dispatch a claimed job.
	 *
	 * @param array $job Job.
	 * @return array
	 * @throws Exception When the job cannot complete.
	 */
	protected function dispatch( array $job ) {
		switch ( isset( $job['type'] ) ? $job['type'] : '' ) {
			case self::TYPE_SNAPSHOT_CREATE:
				return $this->run_snapshot_create_job( $job );
			case self::TYPE_RESTORE_STAGE:
				return $this->run_restore_stage_job( $job );
			case self::TYPE_RESTORE_PLAN:
				return $this->run_restore_plan_job( $job );
		}

		throw new Exception( __( 'Unknown background job type.', 'zignites-sentinel' ) );
	}

	/**
	 * Run a snapshot capture job.
	 *
	 * @param array $job Job.
	 * @return array
	 * @throws Exception When snapshot creation fails.
	 */
	protected function run_snapshot_create_job( array $job ) {
		$payload = isset( $job['payload'] ) && is_array( $job['payload'] ) ? $job['payload'] : array();
		$user_id = isset( $payload['user_id'] ) ? absint( $payload['user_id'] ) : 0;
		$context = isset( $payload['context'] ) && is_array( $payload['context'] ) ? $payload['context'] : array();

		$this->store->update_progress( $job['id'], 1, 3, __( 'Creating checkpoint metadata and artifacts.', 'zignites-sentinel' ) );
		$snapshot_id = $this->snapshot_manager->create_manual_snapshot( $user_id, $context );

		if ( false === $snapshot_id ) {
			$failure_reason = method_exists( $this->snapshot_manager, 'get_last_failure_reason' ) ? $this->snapshot_manager->get_last_failure_reason() : '';

			throw new Exception( $this->get_snapshot_failure_message( $failure_reason ) );
		}

		$this->store->update_progress( $job['id'], 3, 3, __( 'Checkpoint created.', 'zignites-sentinel' ) );
		$this->alert_notifier->notify_event(
			'checkpoint_created',
			array(
				'snapshot_id' => absint( $snapshot_id ),
				'job_id'      => isset( $job['id'] ) ? $job['id'] : '',
				'message'     => __( 'Checkpoint artifacts were created successfully.', 'zignites-sentinel' ),
			),
			get_option( ZNTS_OPTION_ALERT_INTEGRATIONS, array() )
		);

		return array(
			'snapshot_id' => absint( $snapshot_id ),
		);
	}

	/**
	 * Run a restore staging job.
	 *
	 * @param array $job Job.
	 * @return array
	 * @throws Exception When staging cannot complete.
	 */
	protected function run_restore_stage_job( array $job ) {
		$payload     = isset( $job['payload'] ) && is_array( $job['payload'] ) ? $job['payload'] : array();
		$snapshot_id = isset( $payload['snapshot_id'] ) ? absint( $payload['snapshot_id'] ) : 0;
		$resolved    = $this->resolve_snapshot_and_artifacts( $snapshot_id );
		$lock        = $this->operation_lock->acquire( 'stage', 'async-job-' . $job['id'] );

		if ( empty( $lock['acquired'] ) ) {
			throw new Exception( __( 'Another Sentinel operation is active. Retry this job after the active operation finishes.', 'zignites-sentinel' ) );
		}

		try {
			$this->store->update_progress( $job['id'], 1, 3, __( 'Staging checkpoint artifacts.', 'zignites-sentinel' ) );
			$stage_run                = $this->restore_staging_manager->stage_and_validate( $resolved['snapshot'], $resolved['artifacts'] );
			$stage_run['snapshot_id'] = $snapshot_id;

			update_option( ZNTS_OPTION_LAST_RESTORE_STAGE, $stage_run, false );
			$this->restore_checkpoint_store->store_stage_checkpoint( $resolved['snapshot'], $resolved['artifacts'], $stage_run );
			$this->log_restore_stage_completed( $snapshot_id, $stage_run );
			$this->store->update_progress( $job['id'], 3, 3, __( 'Staged validation completed.', 'zignites-sentinel' ) );
		} finally {
			if ( ! empty( $lock['lock'] ) ) {
				$this->operation_lock->release( $lock['lock'] );
			}
		}

		return array(
			'snapshot_id' => $snapshot_id,
			'status'      => isset( $stage_run['status'] ) ? $stage_run['status'] : '',
		);
	}

	/**
	 * Run a restore plan job.
	 *
	 * @param array $job Job.
	 * @return array
	 * @throws Exception When planning cannot complete.
	 */
	protected function run_restore_plan_job( array $job ) {
		$payload     = isset( $job['payload'] ) && is_array( $job['payload'] ) ? $job['payload'] : array();
		$snapshot_id = isset( $payload['snapshot_id'] ) ? absint( $payload['snapshot_id'] ) : 0;
		$resolved    = $this->resolve_snapshot_and_artifacts( $snapshot_id );
		$lock        = $this->operation_lock->acquire( 'stage', 'async-job-' . $job['id'] );

		if ( empty( $lock['acquired'] ) ) {
			throw new Exception( __( 'Another Sentinel operation is active. Retry this job after the active operation finishes.', 'zignites-sentinel' ) );
		}

		try {
			$this->store->update_progress( $job['id'], 1, 3, __( 'Building restore execution plan.', 'zignites-sentinel' ) );
			$plan                = $this->restore_execution_planner->build_plan( $resolved['snapshot'], $resolved['artifacts'] );
			$plan['snapshot_id'] = $snapshot_id;

			update_option( ZNTS_OPTION_LAST_RESTORE_PLAN, $plan, false );
			$this->restore_checkpoint_store->store_plan_checkpoint( $resolved['snapshot'], $resolved['artifacts'], $plan );
			$this->log_restore_plan_created( $snapshot_id, $plan );
			$this->store->update_progress( $job['id'], 3, 3, __( 'Restore plan completed.', 'zignites-sentinel' ) );
		} finally {
			if ( ! empty( $lock['lock'] ) ) {
				$this->operation_lock->release( $lock['lock'] );
			}
		}

		return array(
			'snapshot_id' => $snapshot_id,
			'status'      => isset( $plan['status'] ) ? $plan['status'] : '',
		);
	}

	/**
	 * Resolve a snapshot and its artifacts for restore preparation.
	 *
	 * @param int $snapshot_id Snapshot ID.
	 * @return array
	 * @throws Exception When the snapshot is missing.
	 */
	protected function resolve_snapshot_and_artifacts( $snapshot_id ) {
		$snapshot = $this->snapshots->get_by_id( absint( $snapshot_id ) );

		if ( ! is_array( $snapshot ) ) {
			throw new Exception( __( 'The selected checkpoint could not be found.', 'zignites-sentinel' ) );
		}

		$snapshot['active_plugins_decoded'] = $this->decode_json_field( isset( $snapshot['active_plugins'] ) ? $snapshot['active_plugins'] : '' );
		$snapshot['metadata_decoded']       = $this->decode_json_field( isset( $snapshot['metadata'] ) ? $snapshot['metadata'] : '' );

		return array(
			'snapshot'  => $snapshot,
			'artifacts' => $this->artifacts->get_by_snapshot_id( absint( $snapshot_id ) ),
		);
	}

	/**
	 * Decode a JSON database field.
	 *
	 * @param string $value Raw JSON.
	 * @return array
	 */
	protected function decode_json_field( $value ) {
		$decoded = json_decode( (string) $value, true );

		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * Map assessment status to log severity.
	 *
	 * @param string $status Assessment status.
	 * @return string
	 */
	protected function map_assessment_status_to_severity( $status ) {
		if ( 'blocked' === $status || 'fail' === $status ) {
			return 'error';
		}

		if ( 'caution' === $status || 'warning' === $status ) {
			return 'warning';
		}

		return 'info';
	}

	/**
	 * Build a snapshot failure message.
	 *
	 * @param string $failure_reason Failure reason.
	 * @return string
	 */
	protected function get_snapshot_failure_message( $failure_reason ) {
		if ( 'operation_locked' === $failure_reason ) {
			return __( 'Another Sentinel operation is active. Retry this checkpoint after the active operation finishes.', 'zignites-sentinel' );
		}

		if ( 'disk_space' === $failure_reason ) {
			return __( 'Available disk space is below the safe threshold for checkpoint creation.', 'zignites-sentinel' );
		}

		return __( 'Checkpoint creation failed.', 'zignites-sentinel' );
	}

	/**
	 * Log restore stage completion.
	 *
	 * @param int   $snapshot_id Snapshot ID.
	 * @param array $stage_run   Stage result.
	 * @return void
	 */
	protected function log_restore_stage_completed( $snapshot_id, array $stage_run ) {
		$this->logger->log(
			'restore_stage_completed',
			$this->map_assessment_status_to_severity( isset( $stage_run['status'] ) ? $stage_run['status'] : '' ),
			'restore-stage',
			__( 'Staged restore validation completed.', 'zignites-sentinel' ),
			array(
				'snapshot_id'       => $snapshot_id,
				'status'            => isset( $stage_run['status'] ) ? $stage_run['status'] : '',
				'summary'           => isset( $stage_run['summary'] ) ? $stage_run['summary'] : array(),
				'cleanup_completed' => ! empty( $stage_run['cleanup_completed'] ),
			)
		);
	}

	/**
	 * Log restore plan completion.
	 *
	 * @param int   $snapshot_id Snapshot ID.
	 * @param array $plan        Plan result.
	 * @return void
	 */
	protected function log_restore_plan_created( $snapshot_id, array $plan ) {
		$this->logger->log(
			'restore_plan_created',
			$this->map_assessment_status_to_severity( isset( $plan['status'] ) ? $plan['status'] : '' ),
			'restore-plan',
			__( 'Restore execution plan created.', 'zignites-sentinel' ),
			array(
				'snapshot_id' => $snapshot_id,
				'status'      => isset( $plan['status'] ) ? $plan['status'] : '',
				'summary'     => isset( $plan['summary'] ) ? $plan['summary'] : array(),
				'item_count'  => isset( $plan['items'] ) && is_array( $plan['items'] ) ? count( $plan['items'] ) : 0,
			)
		);
	}
}
