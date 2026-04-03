<?php
/**
 * Repository for event log storage.
 *
 * @package ZignitesSentinel
 */

namespace Zignites\Sentinel\Logging;

use Zignites\Sentinel\Core\Installer;

defined( 'ABSPATH' ) || exit;

class LogRepository {

	/**
	 * Insert a log record.
	 *
	 * @param array $data Log data.
	 * @return int|false
	 */
	public function insert( array $data ) {
		global $wpdb;

		$inserted = $wpdb->insert(
			Installer::get_logs_table_name(),
			array(
				'event_type' => $data['event_type'],
				'severity'   => $data['severity'],
				'source'     => $data['source'],
				'message'    => $data['message'],
				'context'    => $data['context'],
				'created_at' => $data['created_at'],
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return false;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Fetch recent logs.
	 *
	 * @param int $limit Number of rows.
	 * @return array
	 */
	public function get_recent( $limit = 10 ) {
		global $wpdb;

		$limit = max( 1, absint( $limit ) );
		$table = Installer::get_logs_table_name();
		$sql   = "SELECT id, event_type, severity, source, message, created_at
			FROM {$table}
			ORDER BY created_at DESC, id DESC
			LIMIT {$limit}";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Integer limit is sanitized before use.
		$results = $wpdb->get_results( $sql, ARRAY_A );

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Fetch recent logs for one or more exact sources.
	 *
	 * @param array $sources Source names.
	 * @param int   $limit   Number of rows.
	 * @return array
	 */
	public function get_recent_by_sources( array $sources, $limit = 10 ) {
		global $wpdb;

		$sources = array_values(
			array_filter(
				array_map( 'sanitize_text_field', $sources )
			)
		);
		$limit = max( 1, absint( $limit ) );

		if ( empty( $sources ) ) {
			return array();
		}

		$table        = Installer::get_logs_table_name();
		$placeholders = implode( ',', array_fill( 0, count( $sources ), '%s' ) );
		$query        = $wpdb->prepare(
			"SELECT id, event_type, severity, source, message, context, created_at
			FROM {$table}
			WHERE source IN ({$placeholders})
			ORDER BY created_at DESC, id DESC
			LIMIT %d",
			array_merge( $sources, array( $limit ) )
		);
		$results      = $wpdb->get_results( $query, ARRAY_A );

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Fetch filtered logs with pagination.
	 *
	 * @param array $args Query arguments.
	 * @return array
	 */
	public function get_paginated( array $args = array() ) {
		global $wpdb;

		$args      = $this->normalize_query_args( $args );
		$table     = Installer::get_logs_table_name();
		$where     = $this->build_where_sql( $args, $values );
		$offset    = ( $args['paged'] - 1 ) * $args['per_page'];
		$values[]  = $args['per_page'];
		$values[]  = $offset;
		$query     = "SELECT id, event_type, severity, source, message, context, created_at
			FROM {$table}
			{$where}
			ORDER BY created_at DESC, id DESC
			LIMIT %d OFFSET %d";
		$prepared  = $wpdb->prepare( $query, $values );
		$results   = $wpdb->get_results( $prepared, ARRAY_A );

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Count logs by severity within a date window.
	 *
	 * @param int $days Number of days to inspect.
	 * @return array
	 */
	public function count_recent_by_severity( $days = 7 ) {
		global $wpdb;

		$days   = max( 1, absint( $days ) );
		$table  = Installer::get_logs_table_name();
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );

		$query = $wpdb->prepare(
			"SELECT severity, COUNT(*) AS total
			FROM {$table}
			WHERE created_at >= %s
			GROUP BY severity",
			$cutoff
		);

		$rows   = $wpdb->get_results( $query, ARRAY_A );
		$counts = array(
			'info'     => 0,
			'warning'  => 0,
			'error'    => 0,
			'critical' => 0,
		);

		if ( ! is_array( $rows ) ) {
			return $counts;
		}

		foreach ( $rows as $row ) {
			$severity = isset( $row['severity'] ) ? (string) $row['severity'] : 'info';

			if ( isset( $counts[ $severity ] ) ) {
				$counts[ $severity ] = (int) $row['total'];
			}
		}

		return $counts;
	}

	/**
	 * Count logs matching the provided filters.
	 *
	 * @param array $args Query arguments.
	 * @return int
	 */
	public function count_filtered( array $args = array() ) {
		global $wpdb;

		$args     = $this->normalize_query_args( $args );
		$table    = Installer::get_logs_table_name();
		$where    = $this->build_where_sql( $args, $values );
		$query    = "SELECT COUNT(*) FROM {$table} {$where}";
		$prepared = empty( $values ) ? $query : $wpdb->prepare( $query, $values );
		$total    = $wpdb->get_var( $prepared );

		return (int) $total;
	}

	/**
	 * Fetch a single log row by ID.
	 *
	 * @param int $log_id Log ID.
	 * @return array|null
	 */
	public function get_by_id( $log_id ) {
		global $wpdb;

		$log_id = absint( $log_id );

		if ( $log_id < 1 ) {
			return null;
		}

		$table = Installer::get_logs_table_name();
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, event_type, severity, source, message, context, created_at
				FROM {$table}
				WHERE id = %d
				LIMIT 1",
				$log_id
			),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Fetch restore journal rows for a given source and snapshot.
	 *
	 * @param string $source      Journal source.
	 * @param int    $snapshot_id Snapshot ID.
	 * @param string $run_id      Optional run ID.
	 * @param int    $limit       Max rows.
	 * @return array
	 */
	public function get_restore_journal_entries( $source, $snapshot_id, $run_id = '', $limit = 500 ) {
		global $wpdb;

		$source        = sanitize_text_field( (string) $source );
		$snapshot_id   = absint( $snapshot_id );
		$run_id        = sanitize_text_field( (string) $run_id );
		$limit         = max( 1, absint( $limit ) );
		$table         = Installer::get_logs_table_name();
		$snapshot_like = $this->get_snapshot_context_like_values( $snapshot_id );

		if ( '' !== $run_id ) {
			$run_like = '%"run_id":"' . $wpdb->esc_like( $run_id ) . '"%';

			$query = $wpdb->prepare(
				"SELECT id, event_type, severity, source, message, context, created_at
				FROM {$table}
				WHERE event_type = %s
					AND source = %s
					AND ( context LIKE %s OR context LIKE %s )
					AND context LIKE %s
				ORDER BY id ASC
				LIMIT %d",
				'restore_journal_entry',
				$source,
				$snapshot_like[0],
				$snapshot_like[1],
				$run_like,
				$limit
			);
		} else {
			$query = $wpdb->prepare(
				"SELECT id, event_type, severity, source, message, context, created_at
				FROM {$table}
				WHERE event_type = %s
					AND source = %s
					AND ( context LIKE %s OR context LIKE %s )
				ORDER BY id ASC
				LIMIT %d",
				'restore_journal_entry',
				$source,
				$snapshot_like[0],
				$snapshot_like[1],
				$limit
			);
		}

		$rows = $wpdb->get_results( $query, ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Get the latest restore journal run ID for a source and snapshot.
	 *
	 * @param string $source      Journal source.
	 * @param int    $snapshot_id Snapshot ID.
	 * @return string
	 */
	public function get_latest_restore_journal_run( $source, $snapshot_id ) {
		global $wpdb;

		$source        = sanitize_text_field( (string) $source );
		$snapshot_id   = absint( $snapshot_id );
		$table         = Installer::get_logs_table_name();
		$snapshot_like = $this->get_snapshot_context_like_values( $snapshot_id );
		$row           = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT context
				FROM {$table}
				WHERE event_type = %s
					AND source = %s
					AND ( context LIKE %s OR context LIKE %s )
				ORDER BY id DESC
				LIMIT 1",
				'restore_journal_entry',
				$source,
				$snapshot_like[0],
				$snapshot_like[1]
			),
			ARRAY_A
		);

		if ( empty( $row['context'] ) ) {
			return '';
		}

		$context = json_decode( (string) $row['context'], true );

		if ( ! is_array( $context ) || empty( $context['run_id'] ) ) {
			return '';
		}

		return sanitize_text_field( (string) $context['run_id'] );
	}

	/**
	 * Fetch recent restore journal rows for run aggregation.
	 *
	 * @param string $source Journal source.
	 * @param int    $limit  Max rows.
	 * @return array
	 */
	public function get_recent_restore_journal_rows( $source = '', $limit = 250 ) {
		global $wpdb;

		$source = sanitize_text_field( (string) $source );
		$limit  = max( 1, absint( $limit ) );
		$table  = Installer::get_logs_table_name();

		if ( '' !== $source ) {
			$query = $wpdb->prepare(
				"SELECT id, event_type, severity, source, message, context, created_at
				FROM {$table}
				WHERE event_type = %s
					AND source = %s
				ORDER BY id DESC
				LIMIT %d",
				'restore_journal_entry',
				$source,
				$limit
			);
		} else {
			$query = $wpdb->prepare(
				"SELECT id, event_type, severity, source, message, context, created_at
				FROM {$table}
				WHERE event_type = %s
				ORDER BY id DESC
				LIMIT %d",
				'restore_journal_entry',
				$limit
			);
		}

		$rows = $wpdb->get_results( $query, ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Normalize log query arguments.
	 *
	 * @param array $args Raw arguments.
	 * @return array
	 */
	protected function normalize_query_args( array $args ) {
		$defaults = array(
			'severity'    => '',
			'source'      => '',
			'run_id'      => '',
			'snapshot_id' => 0,
			'search'      => '',
			'paged'       => 1,
			'per_page'    => 20,
		);
		$args     = wp_parse_args( $args, $defaults );

		return array(
			'severity'    => sanitize_key( $args['severity'] ),
			'source'      => sanitize_text_field( $args['source'] ),
			'run_id'      => sanitize_text_field( $args['run_id'] ),
			'snapshot_id' => absint( $args['snapshot_id'] ),
			'search'      => sanitize_text_field( $args['search'] ),
			'paged'       => max( 1, absint( $args['paged'] ) ),
			'per_page'    => max( 1, absint( $args['per_page'] ) ),
		);
	}

	/**
	 * Build the filtered WHERE SQL clause.
	 *
	 * @param array $args   Normalized arguments.
	 * @param array $values Prepared values output.
	 * @return string
	 */
	protected function build_where_sql( array $args, &$values ) {
		global $wpdb;

		$clauses = array();
		$values  = array();

		if ( ! empty( $args['severity'] ) ) {
			$clauses[] = 'severity = %s';
			$values[]  = $args['severity'];
		}

		if ( '' !== $args['source'] ) {
			$clauses[] = 'source = %s';
			$values[]  = $args['source'];
		}

		if ( '' !== $args['run_id'] ) {
			$clauses[] = 'context LIKE %s';
			$values[]  = '%"run_id":"' . $wpdb->esc_like( $args['run_id'] ) . '"%';
		}

		if ( ! empty( $args['snapshot_id'] ) ) {
			$snapshot_like = $this->get_snapshot_context_like_values( $args['snapshot_id'] );
			$clauses[]     = '(context LIKE %s OR context LIKE %s)';
			$values[]      = $snapshot_like[0];
			$values[]      = $snapshot_like[1];
		}

		if ( '' !== $args['search'] ) {
			$like      = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$clauses[] = '(event_type LIKE %s OR source LIKE %s OR message LIKE %s OR context LIKE %s)';
			$values[]  = $like;
			$values[]  = $like;
			$values[]  = $like;
			$values[]  = $like;
		}

		if ( empty( $clauses ) ) {
			return '';
		}

		return 'WHERE ' . implode( ' AND ', $clauses );
	}

	/**
	 * Build context LIKE patterns for a snapshot ID.
	 *
	 * @param int $snapshot_id Snapshot ID.
	 * @return array
	 */
	protected function get_snapshot_context_like_values( $snapshot_id ) {
		$snapshot_id = absint( $snapshot_id );

		return array(
			'%"snapshot_id":' . $snapshot_id . ',%',
			'%"snapshot_id":' . $snapshot_id . '}%',
		);
	}
}
