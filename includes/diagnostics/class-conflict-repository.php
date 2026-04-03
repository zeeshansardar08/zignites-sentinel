<?php
/**
 * Repository for normalized conflict signals.
 *
 * @package ZignitesSentinel
 */

namespace Zignites\Sentinel\Diagnostics;

use Zignites\Sentinel\Core\Installer;

defined( 'ABSPATH' ) || exit;

class ConflictRepository {

	/**
	 * Insert or update a conflict signal.
	 *
	 * @param array $data Conflict data.
	 * @return int|false
	 */
	public function upsert( array $data ) {
		global $wpdb;

		$table    = Installer::get_conflicts_table_name();
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, occurrence_count, first_seen_at FROM {$table} WHERE conflict_key = %s LIMIT 1",
				$data['conflict_key']
			),
			ARRAY_A
		);

		if ( $existing ) {
			$updated = $wpdb->update(
				$table,
				array(
					'signal_type'      => $data['signal_type'],
					'severity'         => $data['severity'],
					'status'           => $data['status'],
					'source_a'         => $data['source_a'],
					'source_b'         => $data['source_b'],
					'summary'          => $data['summary'],
					'details'          => $data['details'],
					'last_seen_at'     => $data['last_seen_at'],
					'occurrence_count' => (int) $existing['occurrence_count'] + 1,
				),
				array( 'id' => (int) $existing['id'] ),
				array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d' ),
				array( '%d' )
			);

			if ( false === $updated ) {
				return false;
			}

			return (int) $existing['id'];
		}

		$inserted = $wpdb->insert(
			$table,
			array(
				'conflict_key'     => $data['conflict_key'],
				'signal_type'      => $data['signal_type'],
				'severity'         => $data['severity'],
				'status'           => $data['status'],
				'source_a'         => $data['source_a'],
				'source_b'         => $data['source_b'],
				'summary'          => $data['summary'],
				'details'          => $data['details'],
				'first_seen_at'    => $data['first_seen_at'],
				'last_seen_at'     => $data['last_seen_at'],
				'occurrence_count' => 1,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d' )
		);

		if ( false === $inserted ) {
			return false;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Fetch recent open conflict signals.
	 *
	 * @param int $limit Number of rows.
	 * @return array
	 */
	public function get_recent_open( $limit = 5 ) {
		global $wpdb;

		$limit = max( 1, absint( $limit ) );
		$table = Installer::get_conflicts_table_name();
		$sql   = "SELECT id, signal_type, severity, source_a, source_b, summary, occurrence_count, last_seen_at
			FROM {$table}
			WHERE status = 'open'
			ORDER BY last_seen_at DESC, id DESC
			LIMIT {$limit}";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Integer limit is sanitized before use.
		$results = $wpdb->get_results( $sql, ARRAY_A );

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Count open conflicts by severity.
	 *
	 * @return array
	 */
	public function count_open_by_severity() {
		global $wpdb;

		$table = Installer::get_conflicts_table_name();
		$rows  = $wpdb->get_results(
			"SELECT severity, COUNT(*) AS total FROM {$table} WHERE status = 'open' GROUP BY severity",
			ARRAY_A
		);

		$counts = array(
			'warning'  => 0,
			'error'    => 0,
			'critical' => 0,
		);

		if ( ! is_array( $rows ) ) {
			return $counts;
		}

		foreach ( $rows as $row ) {
			$severity = isset( $row['severity'] ) ? (string) $row['severity'] : 'warning';

			if ( isset( $counts[ $severity ] ) ) {
				$counts[ $severity ] = (int) $row['total'];
			}
		}

		return $counts;
	}
}
