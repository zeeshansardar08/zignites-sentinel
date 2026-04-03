<?php
/**
 * Repository for snapshot metadata.
 *
 * @package ZignitesSentinel
 */

namespace Zignites\Sentinel\Snapshots;

use Zignites\Sentinel\Core\Installer;

defined( 'ABSPATH' ) || exit;

class SnapshotRepository {

	/**
	 * Insert a snapshot record.
	 *
	 * @param array $data Snapshot data.
	 * @return int|false
	 */
	public function insert( array $data ) {
		global $wpdb;

		$inserted = $wpdb->insert(
			Installer::get_snapshots_table_name(),
			array(
				'snapshot_type'   => $data['snapshot_type'],
				'status'          => $data['status'],
				'label'           => $data['label'],
				'description'     => $data['description'],
				'core_version'    => $data['core_version'],
				'php_version'     => $data['php_version'],
				'theme_stylesheet'=> $data['theme_stylesheet'],
				'active_plugins'  => $data['active_plugins'],
				'metadata'        => $data['metadata'],
				'created_by'      => $data['created_by'],
				'created_at'      => $data['created_at'],
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
		);

		if ( false === $inserted ) {
			return false;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Fetch recent snapshots.
	 *
	 * @param int $limit Number of rows.
	 * @return array
	 */
	public function get_recent( $limit = 5 ) {
		global $wpdb;

		$limit = max( 1, absint( $limit ) );
		$table = Installer::get_snapshots_table_name();
		$sql   = "SELECT id, snapshot_type, status, label, description, core_version, php_version, theme_stylesheet, created_by, created_at
			FROM {$table}
			ORDER BY created_at DESC, id DESC
			LIMIT {$limit}";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Integer limit is sanitized before use.
		$results = $wpdb->get_results( $sql, ARRAY_A );

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Fetch a snapshot by ID.
	 *
	 * @param int $snapshot_id Snapshot ID.
	 * @return array|null
	 */
	public function get_by_id( $snapshot_id ) {
		global $wpdb;

		$snapshot_id = absint( $snapshot_id );

		if ( $snapshot_id < 1 ) {
			return null;
		}

		$table = Installer::get_snapshots_table_name();
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d LIMIT 1",
				$snapshot_id
			),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Delete snapshots older than the provided cutoff.
	 *
	 * @param string $cutoff Cutoff datetime in UTC mysql format.
	 * @return int|false
	 */
	public function delete_older_than( $cutoff ) {
		global $wpdb;

		$table = Installer::get_snapshots_table_name();

		return $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE created_at < %s",
				$cutoff
			)
		);
	}

	/**
	 * Fetch snapshot IDs older than the provided cutoff.
	 *
	 * @param string $cutoff Cutoff datetime in UTC mysql format.
	 * @return array
	 */
	public function get_ids_older_than( $cutoff ) {
		global $wpdb;

		$table = Installer::get_snapshots_table_name();
		$rows  = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE created_at < %s",
				$cutoff
			)
		);

		if ( ! is_array( $rows ) ) {
			return array();
		}

		return array_map( 'absint', $rows );
	}
}
