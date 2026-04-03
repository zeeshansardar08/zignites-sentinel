<?php
/**
 * Persistent checkpoint storage for restore stage and plan reuse.
 *
 * @package ZignitesSentinel
 */

namespace Zignites\Sentinel\Snapshots;

defined( 'ABSPATH' ) || exit;

class RestoreCheckpointStore {

	/**
	 * Store the latest staged-validation checkpoint.
	 *
	 * @param array $snapshot  Snapshot row.
	 * @param array $artifacts Artifact rows.
	 * @param array $result    Stage result.
	 * @return void
	 */
	public function store_stage_checkpoint( array $snapshot, array $artifacts, array $result ) {
		$this->store_checkpoint( ZNTS_OPTION_RESTORE_STAGE_CHECKPOINT, 'stage', $snapshot, $artifacts, $result );
	}

	/**
	 * Store the latest restore-plan checkpoint.
	 *
	 * @param array $snapshot  Snapshot row.
	 * @param array $artifacts Artifact rows.
	 * @param array $result    Plan result.
	 * @return void
	 */
	public function store_plan_checkpoint( array $snapshot, array $artifacts, array $result ) {
		$this->store_checkpoint( ZNTS_OPTION_RESTORE_PLAN_CHECKPOINT, 'plan', $snapshot, $artifacts, $result );
	}

	/**
	 * Fetch a staged-validation checkpoint for a snapshot.
	 *
	 * @param int $snapshot_id Snapshot ID.
	 * @return array
	 */
	public function get_stage_checkpoint( $snapshot_id ) {
		return $this->get_checkpoint( ZNTS_OPTION_RESTORE_STAGE_CHECKPOINT, $snapshot_id );
	}

	/**
	 * Fetch a restore-plan checkpoint for a snapshot.
	 *
	 * @param int $snapshot_id Snapshot ID.
	 * @return array
	 */
	public function get_plan_checkpoint( $snapshot_id ) {
		return $this->get_checkpoint( ZNTS_OPTION_RESTORE_PLAN_CHECKPOINT, $snapshot_id );
	}

	/**
	 * Get a stage result only when the current package fingerprint still matches.
	 *
	 * @param array $snapshot  Snapshot row.
	 * @param array $artifacts Artifact rows.
	 * @return array
	 */
	public function get_matching_stage_result( array $snapshot, array $artifacts ) {
		$checkpoint = $this->get_stage_checkpoint( isset( $snapshot['id'] ) ? (int) $snapshot['id'] : 0 );
		$result     = isset( $checkpoint['result'] ) && is_array( $checkpoint['result'] ) ? $checkpoint['result'] : array();

		if ( empty( $result['status'] ) || 'ready' !== $result['status'] ) {
			return array();
		}

		if ( ! $this->matches_package_fingerprint( $checkpoint, $artifacts ) ) {
			return array();
		}

		return $result;
	}

	/**
	 * Get a restore plan only when the current package fingerprint still matches.
	 *
	 * @param array $snapshot  Snapshot row.
	 * @param array $artifacts Artifact rows.
	 * @return array
	 */
	public function get_matching_plan_result( array $snapshot, array $artifacts ) {
		$checkpoint = $this->get_plan_checkpoint( isset( $snapshot['id'] ) ? (int) $snapshot['id'] : 0 );
		$result     = isset( $checkpoint['result'] ) && is_array( $checkpoint['result'] ) ? $checkpoint['result'] : array();

		if (
			empty( $result['status'] ) ||
			'blocked' === $result['status'] ||
			empty( $result['items'] ) ||
			! is_array( $result['items'] )
		) {
			return array();
		}

		if ( ! $this->matches_package_fingerprint( $checkpoint, $artifacts ) ) {
			return array();
		}

		return $result;
	}

	/**
	 * Store an execution checkpoint for a specific run.
	 *
	 * @param array  $snapshot   Snapshot row.
	 * @param array  $artifacts  Artifact rows.
	 * @param string $run_id     Run ID.
	 * @param array  $checkpoint Execution checkpoint data.
	 * @return void
	 */
	public function store_execution_checkpoint( array $snapshot, array $artifacts, $run_id, array $checkpoint ) {
		$snapshot_id = isset( $snapshot['id'] ) ? absint( $snapshot['id'] ) : 0;
		$run_id      = sanitize_text_field( (string) $run_id );

		if ( $snapshot_id < 1 || '' === $run_id ) {
			return;
		}

		$existing          = $this->get_execution_checkpoint( $snapshot_id, $run_id );
		$existing_state    = isset( $existing['checkpoint'] ) && is_array( $existing['checkpoint'] ) ? $existing['checkpoint'] : array();
		$merged_checkpoint = array_replace_recursive( $existing_state, $checkpoint );

		update_option(
			ZNTS_OPTION_RESTORE_EXECUTION_CHECKPOINT,
			array(
				'snapshot_id'         => $snapshot_id,
				'run_id'              => $run_id,
				'generated_at'        => current_time( 'mysql', true ),
				'package_fingerprint' => $this->build_package_fingerprint( $artifacts ),
				'checkpoint'          => $merged_checkpoint,
			),
			false
		);
	}

	/**
	 * Store or update a per-item execution checkpoint.
	 *
	 * @param array  $snapshot        Snapshot row.
	 * @param array  $artifacts       Artifact rows.
	 * @param string $run_id          Run ID.
	 * @param string $item_key        Stable item key.
	 * @param array  $item_checkpoint Item checkpoint payload.
	 * @return void
	 */
	public function store_execution_item_checkpoint( array $snapshot, array $artifacts, $run_id, $item_key, array $item_checkpoint ) {
		$item_key = sanitize_text_field( (string) $item_key );

		if ( '' === $item_key ) {
			return;
		}

		$this->store_execution_checkpoint(
			$snapshot,
			$artifacts,
			$run_id,
			array(
				'items' => array(
					$item_key => $item_checkpoint,
				),
			)
		);
	}

	/**
	 * Get a matching execution checkpoint for a run.
	 *
	 * @param array  $snapshot  Snapshot row.
	 * @param array  $artifacts Artifact rows.
	 * @param string $run_id    Run ID.
	 * @return array
	 */
	public function get_matching_execution_checkpoint( array $snapshot, array $artifacts, $run_id ) {
		$snapshot_id = isset( $snapshot['id'] ) ? absint( $snapshot['id'] ) : 0;
		$run_id      = sanitize_text_field( (string) $run_id );
		$stored      = get_option( ZNTS_OPTION_RESTORE_EXECUTION_CHECKPOINT, array() );

		if (
			$snapshot_id < 1 ||
			'' === $run_id ||
			! is_array( $stored ) ||
			empty( $stored['snapshot_id'] ) ||
			(int) $stored['snapshot_id'] !== $snapshot_id ||
			empty( $stored['run_id'] ) ||
			$run_id !== sanitize_text_field( (string) $stored['run_id'] ) ||
			empty( $stored['checkpoint'] ) ||
			! is_array( $stored['checkpoint'] )
		) {
			return array();
		}

		if ( ! $this->matches_package_fingerprint( $stored, $artifacts ) ) {
			return array();
		}

		return $stored['checkpoint'];
	}

	/**
	 * Get the stored execution checkpoint for a snapshot and optional run.
	 *
	 * @param int    $snapshot_id Snapshot ID.
	 * @param string $run_id      Optional run ID.
	 * @return array
	 */
	public function get_execution_checkpoint( $snapshot_id, $run_id = '' ) {
		$stored      = get_option( ZNTS_OPTION_RESTORE_EXECUTION_CHECKPOINT, array() );
		$snapshot_id = absint( $snapshot_id );
		$run_id      = sanitize_text_field( (string) $run_id );

		if (
			! is_array( $stored ) ||
			empty( $stored['snapshot_id'] )
		) {
			return array();
		}

		if ( $snapshot_id > 0 && (int) $stored['snapshot_id'] !== $snapshot_id ) {
			return array();
		}

		if ( '' !== $run_id && ( empty( $stored['run_id'] ) || $run_id !== sanitize_text_field( (string) $stored['run_id'] ) ) ) {
			return array();
		}

		return $stored;
	}

	/**
	 * Clear the stored execution checkpoint for a run or snapshot.
	 *
	 * @param int    $snapshot_id Snapshot ID.
	 * @param string $run_id      Optional run ID.
	 * @return void
	 */
	public function clear_execution_checkpoint( $snapshot_id = 0, $run_id = '' ) {
		$stored      = get_option( ZNTS_OPTION_RESTORE_EXECUTION_CHECKPOINT, array() );
		$snapshot_id = absint( $snapshot_id );
		$run_id      = sanitize_text_field( (string) $run_id );

		if ( ! is_array( $stored ) ) {
			return;
		}

		if ( $snapshot_id > 0 && ( empty( $stored['snapshot_id'] ) || (int) $stored['snapshot_id'] !== $snapshot_id ) ) {
			return;
		}

		if ( '' !== $run_id && ( empty( $stored['run_id'] ) || $run_id !== sanitize_text_field( (string) $stored['run_id'] ) ) ) {
			return;
		}

		delete_option( ZNTS_OPTION_RESTORE_EXECUTION_CHECKPOINT );
	}

	/**
	 * Determine whether a checkpoint matches the current package fingerprint.
	 *
	 * @param array $checkpoint Stored checkpoint.
	 * @param array $artifacts  Current artifact rows.
	 * @return bool
	 */
	public function checkpoint_matches_artifacts( array $checkpoint, array $artifacts ) {
		return $this->matches_package_fingerprint( $checkpoint, $artifacts );
	}

	/**
	 * Persist a checkpoint payload.
	 *
	 * @param string $option_name Option name.
	 * @param string $type        Checkpoint type.
	 * @param array  $snapshot    Snapshot row.
	 * @param array  $artifacts   Artifact rows.
	 * @param array  $result      Result payload.
	 * @return void
	 */
	protected function store_checkpoint( $option_name, $type, array $snapshot, array $artifacts, array $result ) {
		$snapshot_id = isset( $snapshot['id'] ) ? absint( $snapshot['id'] ) : 0;

		if ( $snapshot_id < 1 ) {
			return;
		}

		update_option(
			$option_name,
			array(
				'type'                => sanitize_key( $type ),
				'snapshot_id'         => $snapshot_id,
				'generated_at'        => isset( $result['generated_at'] ) ? sanitize_text_field( (string) $result['generated_at'] ) : current_time( 'mysql', true ),
				'status'              => isset( $result['status'] ) ? sanitize_key( (string) $result['status'] ) : '',
				'package_fingerprint' => $this->build_package_fingerprint( $artifacts ),
				'result'              => $result,
			),
			false
		);
	}

	/**
	 * Fetch a checkpoint by option and snapshot ID.
	 *
	 * @param string $option_name Option name.
	 * @param int    $snapshot_id Snapshot ID.
	 * @return array
	 */
	protected function get_checkpoint( $option_name, $snapshot_id ) {
		$snapshot_id = absint( $snapshot_id );
		$checkpoint  = get_option( $option_name, array() );

		if ( $snapshot_id < 1 || ! is_array( $checkpoint ) ) {
			return array();
		}

		if ( empty( $checkpoint['snapshot_id'] ) || (int) $checkpoint['snapshot_id'] !== $snapshot_id ) {
			return array();
		}

		return $checkpoint;
	}

	/**
	 * Determine whether a stored checkpoint still matches the current package artifact.
	 *
	 * @param array $checkpoint Stored checkpoint.
	 * @param array $artifacts  Current artifact rows.
	 * @return bool
	 */
	protected function matches_package_fingerprint( array $checkpoint, array $artifacts ) {
		$stored  = isset( $checkpoint['package_fingerprint'] ) && is_array( $checkpoint['package_fingerprint'] ) ? $checkpoint['package_fingerprint'] : array();
		$current = $this->build_package_fingerprint( $artifacts );

		if ( empty( $stored ) || empty( $current ) ) {
			return false;
		}

		return $stored === $current;
	}

	/**
	 * Build a stable package fingerprint from artifact metadata.
	 *
	 * @param array $artifacts Artifact rows.
	 * @return array
	 */
	protected function build_package_fingerprint( array $artifacts ) {
		$artifact = $this->find_package_artifact( $artifacts );

		if ( ! is_array( $artifact ) ) {
			return array();
		}

		$metadata = array();

		if ( ! empty( $artifact['metadata'] ) ) {
			$decoded  = json_decode( (string) $artifact['metadata'], true );
			$metadata = is_array( $decoded ) ? $decoded : array();
		}

		return array(
			'source_path'     => isset( $artifact['source_path'] ) ? sanitize_text_field( (string) $artifact['source_path'] ) : '',
			'sha256'          => isset( $metadata['sha256'] ) ? sanitize_text_field( (string) $metadata['sha256'] ) : '',
			'manifest_sha256' => isset( $metadata['manifest_sha256'] ) ? sanitize_text_field( (string) $metadata['manifest_sha256'] ) : '',
			'size_bytes'      => isset( $metadata['size_bytes'] ) ? (int) $metadata['size_bytes'] : 0,
		);
	}

	/**
	 * Find the package artifact row.
	 *
	 * @param array $artifacts Artifact rows.
	 * @return array|null
	 */
	protected function find_package_artifact( array $artifacts ) {
		foreach ( $artifacts as $artifact ) {
			if ( ! empty( $artifact['artifact_type'] ) && 'package' === $artifact['artifact_type'] ) {
				return $artifact;
			}
		}

		return null;
	}
}
