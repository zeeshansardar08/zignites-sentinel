<?php
/**
 * Repository for snapshot rollback artifacts.
 *
 * @package ZignitesSentinel
 */

namespace Zignites\Sentinel\Snapshots;

use Zignites\Sentinel\Core\Installer;

defined( 'ABSPATH' ) || exit;

class SnapshotArtifactRepository {

	/**
	 * Replace artifacts for a snapshot.
	 *
	 * @param int   $snapshot_id Snapshot ID.
	 * @param array $artifacts   Artifact rows.
	 * @return void
	 */
	public function replace_for_snapshot( $snapshot_id, array $artifacts ) {
		global $wpdb;

		$snapshot_id = absint( $snapshot_id );

		if ( $snapshot_id < 1 ) {
			return;
		}

		$this->delete_by_snapshot_ids( array( $snapshot_id ) );

		if ( empty( $artifacts ) ) {
			return;
		}

		$table = Installer::get_snapshot_artifacts_table_name();

		foreach ( $artifacts as $artifact ) {
			$wpdb->insert(
				$table,
				array(
					'snapshot_id'   => $snapshot_id,
					'artifact_type' => isset( $artifact['artifact_type'] ) ? sanitize_key( $artifact['artifact_type'] ) : '',
					'artifact_key'  => isset( $artifact['artifact_key'] ) ? sanitize_text_field( $artifact['artifact_key'] ) : '',
					'label'         => isset( $artifact['label'] ) ? sanitize_text_field( $artifact['label'] ) : '',
					'version'       => isset( $artifact['version'] ) ? sanitize_text_field( $artifact['version'] ) : '',
					'source_path'   => isset( $artifact['source_path'] ) ? sanitize_text_field( $artifact['source_path'] ) : '',
					'created_at'    => isset( $artifact['created_at'] ) ? sanitize_text_field( $artifact['created_at'] ) : current_time( 'mysql', true ),
					'metadata'      => isset( $artifact['metadata'] ) ? wp_json_encode( json_decode( (string) $artifact['metadata'], true ) ) : '',
				),
				array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
			);
		}
	}

	/**
	 * Fetch artifacts for a snapshot.
	 *
	 * @param int $snapshot_id Snapshot ID.
	 * @return array
	 */
	public function get_by_snapshot_id( $snapshot_id ) {
		global $wpdb;

		$snapshot_id = absint( $snapshot_id );

		if ( $snapshot_id < 1 ) {
			return array();
		}

		$table = Installer::get_snapshot_artifacts_table_name();
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, snapshot_id, artifact_type, artifact_key, label, version, source_path, created_at, metadata
				FROM {$table}
				WHERE snapshot_id = %d
				ORDER BY artifact_type ASC, label ASC, id ASC",
				$snapshot_id
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Fetch artifacts for one or more snapshot IDs.
	 *
	 * @param array $snapshot_ids Snapshot IDs.
	 * @return array
	 */
	public function get_by_snapshot_ids( array $snapshot_ids ) {
		global $wpdb;

		$snapshot_ids = array_filter( array_map( 'absint', $snapshot_ids ) );

		if ( empty( $snapshot_ids ) ) {
			return array();
		}

		$table        = Installer::get_snapshot_artifacts_table_name();
		$placeholders = implode( ',', array_fill( 0, count( $snapshot_ids ), '%d' ) );
		$query        = $wpdb->prepare(
			"SELECT id, snapshot_id, artifact_type, artifact_key, label, version, source_path, created_at, metadata
			FROM {$table}
			WHERE snapshot_id IN ({$placeholders})
			ORDER BY snapshot_id ASC, artifact_type ASC, label ASC, id ASC",
			$snapshot_ids
		);
		$rows         = $wpdb->get_results( $query, ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Delete artifacts for one or more snapshots.
	 *
	 * @param array $snapshot_ids Snapshot IDs.
	 * @return int|false
	 */
	public function delete_by_snapshot_ids( array $snapshot_ids ) {
		global $wpdb;

		$snapshot_ids = array_filter( array_map( 'absint', $snapshot_ids ) );

		if ( empty( $snapshot_ids ) ) {
			return 0;
		}

		$table        = Installer::get_snapshot_artifacts_table_name();
		$placeholders = implode( ',', array_fill( 0, count( $snapshot_ids ), '%d' ) );
		$query        = $wpdb->prepare(
			"DELETE FROM {$table} WHERE snapshot_id IN ({$placeholders})",
			$snapshot_ids
		);

		return $wpdb->query( $query );
	}
}
