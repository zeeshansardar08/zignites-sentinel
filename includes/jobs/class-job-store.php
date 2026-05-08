<?php
/**
 * Option-backed async job storage.
 *
 * @package ZignitesSentinel
 */

namespace Zignites\Sentinel\Jobs;

defined( 'ABSPATH' ) || exit;

class JobStore {

	const STATUS_PENDING   = 'pending';
	const STATUS_RUNNING   = 'running';
	const STATUS_COMPLETED = 'completed';
	const STATUS_FAILED    = 'failed';
	const STATUS_CANCELLED = 'cancelled';

	/**
	 * Persist a new queued job.
	 *
	 * @param string $type    Job type.
	 * @param array  $payload Job payload.
	 * @param array  $args    Job options.
	 * @return array
	 */
	public function enqueue( $type, array $payload = array(), array $args = array() ) {
		$jobs = $this->load_jobs();
		$now  = current_time( 'mysql', true );
		$id   = $this->generate_job_id( $type );

		$job = array(
			'id'           => $id,
			'type'         => sanitize_key( (string) $type ),
			'status'       => self::STATUS_PENDING,
			'payload'      => $this->sanitize_payload( $payload ),
			'progress'     => array(
				'current' => 0,
				'total'   => 1,
				'message' => isset( $args['message'] ) ? sanitize_text_field( (string) $args['message'] ) : __( 'Queued.', 'zignites-sentinel' ),
			),
			'result'       => array(),
			'error'        => '',
			'attempts'     => 0,
			'max_attempts' => isset( $args['max_attempts'] ) ? max( 1, absint( $args['max_attempts'] ) ) : 1,
			'created_by'   => isset( $args['created_by'] ) ? absint( $args['created_by'] ) : 0,
			'created_at'   => $now,
			'updated_at'   => $now,
			'started_at'   => '',
			'completed_at' => '',
		);

		array_unshift( $jobs, $job );
		$this->save_jobs( $jobs );

		return $job;
	}

	/**
	 * Get a job by ID.
	 *
	 * @param string $id Job ID.
	 * @return array|null
	 */
	public function get( $id ) {
		foreach ( $this->load_jobs() as $job ) {
			if ( isset( $job['id'] ) && (string) $job['id'] === (string) $id ) {
				return $this->normalize_job( $job );
			}
		}

		return null;
	}

	/**
	 * Get recent jobs, optionally filtered by type.
	 *
	 * @param int    $limit Job limit.
	 * @param string $type  Optional job type.
	 * @return array
	 */
	public function get_recent( $limit = 5, $type = '' ) {
		$limit = max( 1, absint( $limit ) );
		$type  = sanitize_key( (string) $type );
		$jobs  = array();

		foreach ( $this->load_jobs() as $job ) {
			$job = $this->normalize_job( $job );

			if ( '' !== $type && $type !== $job['type'] ) {
				continue;
			}

			$jobs[] = $job;

			if ( count( $jobs ) >= $limit ) {
				break;
			}
		}

		return $jobs;
	}

	/**
	 * Determine whether pending jobs exist.
	 *
	 * @return bool
	 */
	public function has_pending_jobs() {
		foreach ( $this->load_jobs() as $job ) {
			if ( self::STATUS_PENDING === $job['status'] ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Claim the next pending job.
	 *
	 * @param array $types Optional allowed job types.
	 * @return array|null
	 */
	public function claim_next( array $types = array() ) {
		$types = array_filter( array_map( 'sanitize_key', $types ) );
		$jobs  = $this->load_jobs();
		$now   = current_time( 'mysql', true );

		foreach ( $jobs as $index => $job ) {
			$job = $this->normalize_job( $job );

			if ( self::STATUS_PENDING !== $job['status'] ) {
				continue;
			}

			if ( ! empty( $types ) && ! in_array( $job['type'], $types, true ) ) {
				continue;
			}

			$job['status']     = self::STATUS_RUNNING;
			$job['attempts']   = (int) $job['attempts'] + 1;
			$job['started_at'] = $now;
			$job['updated_at'] = $now;
			$job['progress']   = array(
				'current' => 0,
				'total'   => 1,
				'message' => __( 'Running.', 'zignites-sentinel' ),
			);

			$jobs[ $index ] = $job;
			$this->save_jobs( $jobs );

			return $job;
		}

		return null;
	}

	/**
	 * Update progress for a running job.
	 *
	 * @param string $id       Job ID.
	 * @param int    $current  Current progress step.
	 * @param int    $total    Total progress steps.
	 * @param string $message  Progress message.
	 * @return array|null
	 */
	public function update_progress( $id, $current, $total, $message ) {
		return $this->update_job(
			$id,
			array(
				'progress' => array(
					'current' => max( 0, absint( $current ) ),
					'total'   => max( 1, absint( $total ) ),
					'message' => sanitize_text_field( (string) $message ),
				),
			)
		);
	}

	/**
	 * Mark a job as completed.
	 *
	 * @param string $id     Job ID.
	 * @param array  $result Result payload.
	 * @return array|null
	 */
	public function complete( $id, array $result = array() ) {
		return $this->update_job(
			$id,
			array(
				'status'       => self::STATUS_COMPLETED,
				'result'       => $this->sanitize_payload( $result ),
				'error'        => '',
				'completed_at' => current_time( 'mysql', true ),
				'progress'     => array(
					'current' => 1,
					'total'   => 1,
					'message' => __( 'Completed.', 'zignites-sentinel' ),
				),
			)
		);
	}

	/**
	 * Mark a job as failed.
	 *
	 * @param string $id      Job ID.
	 * @param string $message Error message.
	 * @return array|null
	 */
	public function fail( $id, $message ) {
		return $this->update_job(
			$id,
			array(
				'status'       => self::STATUS_FAILED,
				'error'        => sanitize_text_field( (string) $message ),
				'completed_at' => current_time( 'mysql', true ),
				'progress'     => array(
					'current' => 1,
					'total'   => 1,
					'message' => sanitize_text_field( (string) $message ),
				),
			)
		);
	}

	/**
	 * Requeue a failed job when attempts remain.
	 *
	 * @param string $id Job ID.
	 * @return array|null
	 */
	public function retry( $id ) {
		$job = $this->get( $id );

		if ( ! is_array( $job ) || self::STATUS_FAILED !== $job['status'] || (int) $job['attempts'] >= (int) $job['max_attempts'] ) {
			return null;
		}

		return $this->update_job(
			$id,
			array(
				'status'       => self::STATUS_PENDING,
				'error'        => '',
				'started_at'   => '',
				'completed_at' => '',
				'progress'     => array(
					'current' => 0,
					'total'   => 1,
					'message' => __( 'Queued for retry.', 'zignites-sentinel' ),
				),
			)
		);
	}

	/**
	 * Load all jobs.
	 *
	 * @return array
	 */
	protected function load_jobs() {
		$jobs = get_option( ZNTS_OPTION_ASYNC_JOBS, array() );

		if ( ! is_array( $jobs ) ) {
			return array();
		}

		return array_map( array( $this, 'normalize_job' ), $jobs );
	}

	/**
	 * Persist jobs with a bounded history.
	 *
	 * @param array $jobs Jobs.
	 * @return void
	 */
	protected function save_jobs( array $jobs ) {
		$jobs = array_slice( array_values( $jobs ), 0, 50 );

		update_option( ZNTS_OPTION_ASYNC_JOBS, $jobs, false );
	}

	/**
	 * Update a job by ID.
	 *
	 * @param string $id      Job ID.
	 * @param array  $changes Changes.
	 * @return array|null
	 */
	protected function update_job( $id, array $changes ) {
		$jobs = $this->load_jobs();
		$now  = current_time( 'mysql', true );

		foreach ( $jobs as $index => $job ) {
			if ( isset( $job['id'] ) && (string) $job['id'] === (string) $id ) {
				$job              = array_merge( $job, $changes );
				$job['updated_at'] = $now;
				$job              = $this->normalize_job( $job );
				$jobs[ $index ]   = $job;
				$this->save_jobs( $jobs );

				return $job;
			}
		}

		return null;
	}

	/**
	 * Normalize a job row.
	 *
	 * @param array $job Job row.
	 * @return array
	 */
	protected function normalize_job( array $job ) {
		$status = isset( $job['status'] ) ? sanitize_key( (string) $job['status'] ) : self::STATUS_PENDING;

		if ( ! in_array( $status, array( self::STATUS_PENDING, self::STATUS_RUNNING, self::STATUS_COMPLETED, self::STATUS_FAILED, self::STATUS_CANCELLED ), true ) ) {
			$status = self::STATUS_PENDING;
		}

		return array(
			'id'           => isset( $job['id'] ) ? sanitize_text_field( (string) $job['id'] ) : '',
			'type'         => isset( $job['type'] ) ? sanitize_key( (string) $job['type'] ) : '',
			'status'       => $status,
			'payload'      => isset( $job['payload'] ) && is_array( $job['payload'] ) ? $job['payload'] : array(),
			'progress'     => isset( $job['progress'] ) && is_array( $job['progress'] ) ? $job['progress'] : array(),
			'result'       => isset( $job['result'] ) && is_array( $job['result'] ) ? $job['result'] : array(),
			'error'        => isset( $job['error'] ) ? sanitize_text_field( (string) $job['error'] ) : '',
			'attempts'     => isset( $job['attempts'] ) ? absint( $job['attempts'] ) : 0,
			'max_attempts' => isset( $job['max_attempts'] ) ? max( 1, absint( $job['max_attempts'] ) ) : 1,
			'created_by'   => isset( $job['created_by'] ) ? absint( $job['created_by'] ) : 0,
			'created_at'   => isset( $job['created_at'] ) ? sanitize_text_field( (string) $job['created_at'] ) : '',
			'updated_at'   => isset( $job['updated_at'] ) ? sanitize_text_field( (string) $job['updated_at'] ) : '',
			'started_at'   => isset( $job['started_at'] ) ? sanitize_text_field( (string) $job['started_at'] ) : '',
			'completed_at' => isset( $job['completed_at'] ) ? sanitize_text_field( (string) $job['completed_at'] ) : '',
		);
	}

	/**
	 * Sanitize nested job payload values.
	 *
	 * @param mixed $value Value.
	 * @return mixed
	 */
	protected function sanitize_payload( $value ) {
		if ( is_array( $value ) ) {
			$sanitized = array();

			foreach ( $value as $key => $item ) {
				$sanitized[ is_string( $key ) ? sanitize_key( $key ) : $key ] = $this->sanitize_payload( $item );
			}

			return $sanitized;
		}

		if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) || null === $value ) {
			return $value;
		}

		return sanitize_text_field( (string) $value );
	}

	/**
	 * Generate a unique job ID.
	 *
	 * @param string $type Job type.
	 * @return string
	 */
	protected function generate_job_id( $type ) {
		return sanitize_key( (string) $type ) . '-' . time() . '-' . substr( md5( wp_json_encode( array( $type, microtime( true ), wp_generate_password( 8, false ) ) ) ), 0, 10 );
	}
}
