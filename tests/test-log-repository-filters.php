<?php
/**
 * Focused tests for event log filter normalization and export query behavior.
 */

require_once __DIR__ . '/bootstrap.php';

use Zignites\Sentinel\Logging\LogRepository;

class ZNTS_Testable_Log_Repository extends LogRepository {
	public function expose_normalize_query_args( array $args ) {
		return $this->normalize_query_args( $args );
	}

	public function expose_build_where_sql( array $args, &$values ) {
		return $this->build_where_sql( $args, $values );
	}
}

class ZNTS_Fake_WPDB_Log_Filters {
	public function esc_like( $text ) {
		return addcslashes( (string) $text, '_%\\' );
	}
}

function znts_test_log_repository_normalizes_query_args_for_export_filters() {
	$repository = new ZNTS_Testable_Log_Repository();
	$args       = $repository->expose_normalize_query_args(
		array(
			'severity'    => 'Warning!!',
			'source'      => ' snapshot-audit ',
			'run_id'      => ' run-1 ',
			'snapshot_id' => '18abc',
			'search'      => ' restore ',
			'paged'       => 0,
			'per_page'    => 0,
		)
	);

	znts_assert_same( 'warning', $args['severity'], 'Severity should be sanitized to a key-safe value.' );
	znts_assert_same( 'snapshot-audit', $args['source'], 'Source should be trimmed and sanitized.' );
	znts_assert_same( 'run-1', $args['run_id'], 'Run ID should be trimmed and sanitized.' );
	znts_assert_same( 18, $args['snapshot_id'], 'Snapshot ID should be normalized with absint.' );
	znts_assert_same( 'restore', $args['search'], 'Search text should be trimmed and sanitized.' );
	znts_assert_same( 1, $args['paged'], 'Paged should be clamped to a minimum of 1.' );
	znts_assert_same( 1, $args['per_page'], 'Per-page should be clamped to a minimum of 1.' );
}

function znts_test_log_repository_builds_where_sql_with_all_export_filters() {
	global $wpdb;

	$wpdb       = new ZNTS_Fake_WPDB_Log_Filters();
	$repository = new ZNTS_Testable_Log_Repository();
	$args       = $repository->expose_normalize_query_args(
		array(
			'severity'    => 'critical',
			'source'      => 'restore-execution-journal',
			'run_id'      => 'run-55',
			'snapshot_id' => 42,
			'search'      => 'payload write',
		)
	);
	$values     = array();
	$where_sql  = $repository->expose_build_where_sql( $args, $values );

	znts_assert_true( false !== strpos( $where_sql, 'severity = %s' ), 'Severity filter should be included in the WHERE clause.' );
	znts_assert_true( false !== strpos( $where_sql, 'source = %s' ), 'Source filter should be included in the WHERE clause.' );
	znts_assert_true( false !== strpos( $where_sql, 'context LIKE %s' ), 'Run ID filter should be included in the WHERE clause.' );
	znts_assert_true( false !== strpos( $where_sql, '(context LIKE %s OR context LIKE %s)' ), 'Snapshot ID filter should include both supported context patterns.' );
	znts_assert_true( false !== strpos( $where_sql, '(event_type LIKE %s OR source LIKE %s OR message LIKE %s OR context LIKE %s)' ), 'Search filter should scan event, source, message, and context.' );
	znts_assert_same( 9, count( $values ), 'All export filters should contribute the expected number of prepared values.' );
	znts_assert_same( '%"run_id":"run-55"%', $values[2], 'Run ID filter should target the serialized context value.' );
	znts_assert_same( '%"snapshot_id":42,%', $values[3], 'Snapshot filter should support context entries followed by another field.' );
	znts_assert_same( '%"snapshot_id":42}%', $values[4], 'Snapshot filter should support context entries that terminate the JSON object.' );
	znts_assert_same( '%payload write%', $values[5], 'Search filters should use the same escaped LIKE pattern.' );
	znts_assert_same( '%payload write%', $values[8], 'Search filters should reuse the same LIKE pattern for context searching.' );
}

function znts_test_log_repository_builds_empty_where_clause_without_filters() {
	global $wpdb;

	$wpdb       = new ZNTS_Fake_WPDB_Log_Filters();
	$repository = new ZNTS_Testable_Log_Repository();
	$values     = array();
	$where_sql  = $repository->expose_build_where_sql( $repository->expose_normalize_query_args( array() ), $values );

	znts_assert_same( '', $where_sql, 'No filters should produce an empty WHERE clause.' );
	znts_assert_same( array(), $values, 'No filters should produce no prepared values.' );
}
