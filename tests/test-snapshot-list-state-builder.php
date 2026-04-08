<?php
/**
 * Focused tests for Update Readiness snapshot list state building.
 */

require_once __DIR__ . '/bootstrap.php';

use Zignites\Sentinel\Admin\SnapshotListStateBuilder;

class ZNTS_Fake_Snapshot_List_Repository {
	public $snapshots = array();
	public $last_calls = array();

	public function count_filtered( $search ) {
		$matches = $this->filter_rows( $search );

		return count( $matches );
	}

	public function get_filtered( array $args ) {
		$this->last_calls[] = $args;
		$matches            = $this->filter_rows( isset( $args['search'] ) ? $args['search'] : '' );
		$offset             = isset( $args['offset'] ) ? (int) $args['offset'] : 0;
		$limit              = isset( $args['limit'] ) ? (int) $args['limit'] : count( $matches );

		return array_slice( $matches, $offset, $limit );
	}

	protected function filter_rows( $search ) {
		$search = strtolower( (string) $search );

		if ( '' === $search ) {
			return $this->snapshots;
		}

		return array_values(
			array_filter(
				$this->snapshots,
				function ( $snapshot ) use ( $search ) {
					$label = isset( $snapshot['label'] ) ? strtolower( (string) $snapshot['label'] ) : '';

					return false !== strpos( $label, $search );
				}
			)
		);
	}
}

class ZNTS_Fake_Snapshot_List_Status_Resolver {
	public $status_index = array();

	public function build_snapshot_status_index( array $snapshots ) {
		$index = array();

		foreach ( $snapshots as $snapshot ) {
			$snapshot_id = isset( $snapshot['id'] ) ? (int) $snapshot['id'] : 0;
			$index[ $snapshot_id ] = isset( $this->status_index[ $snapshot_id ] ) ? $this->status_index[ $snapshot_id ] : array();
		}

		return $index;
	}

	public function filter_snapshots( array $snapshots, array $status_index, $search = '', $status_filter = '', $limit = 50 ) {
		return array_values(
			array_filter(
				$snapshots,
				function ( $snapshot ) use ( $status_index, $status_filter ) {
					$snapshot_id = isset( $snapshot['id'] ) ? (int) $snapshot['id'] : 0;
					$status      = isset( $status_index[ $snapshot_id ]['filter_key'] ) ? (string) $status_index[ $snapshot_id ]['filter_key'] : '';

					return '' === $status_filter || $status_filter === $status;
				}
			)
		);
	}
}

function znts_test_snapshot_list_state_builder_returns_unfiltered_pages() {
	$builder   = new SnapshotListStateBuilder();
	$repo      = new ZNTS_Fake_Snapshot_List_Repository();
	$resolver  = new ZNTS_Fake_Snapshot_List_Status_Resolver();

	$repo->snapshots = array(
		array( 'id' => 1, 'label' => 'Snapshot A' ),
		array( 'id' => 2, 'label' => 'Snapshot B' ),
		array( 'id' => 3, 'label' => 'Snapshot C' ),
	);
	$resolver->status_index = array(
		1 => array( 'filter_key' => 'ready' ),
		2 => array( 'filter_key' => 'warning' ),
		3 => array( 'filter_key' => 'ready' ),
	);

	$state = $builder->build_state( 'Snapshot', '', 1, 2, $repo, $resolver );

	znts_assert_same( 2, count( $state['items'] ), 'Snapshot list state builder should page unfiltered snapshot rows.' );
	znts_assert_same( 3, $state['pagination']['total_items'], 'Snapshot list state builder should report total unfiltered matches.' );
	znts_assert_same( 2, $state['pagination']['total_pages'], 'Snapshot list state builder should compute total pages from unfiltered matches.' );
	znts_assert_same( 2, count( $state['status_index'] ), 'Snapshot list state builder should build status index rows for the paged items.' );
}

function znts_test_snapshot_list_state_builder_filters_snapshot_statuses_across_batches() {
	$builder   = new SnapshotListStateBuilder();
	$repo      = new ZNTS_Fake_Snapshot_List_Repository();
	$resolver  = new ZNTS_Fake_Snapshot_List_Status_Resolver();

	for ( $i = 1; $i <= 60; $i++ ) {
		$repo->snapshots[] = array(
			'id'    => $i,
			'label' => 'Snapshot ' . $i,
		);
		$resolver->status_index[ $i ] = array(
			'filter_key' => 0 === $i % 2 ? 'ready' : 'warning',
		);
	}

	$state = $builder->build_state( 'Snapshot', 'ready', 2, 12, $repo, $resolver );

	znts_assert_same( 12, count( $state['items'] ), 'Snapshot list state builder should page filtered snapshot matches.' );
	znts_assert_same( 30, $state['pagination']['total_items'], 'Snapshot list state builder should count all filtered matches across batches.' );
	znts_assert_same( 3, $state['pagination']['total_pages'], 'Snapshot list state builder should compute total pages from filtered matches.' );
	znts_assert_same( 26, isset( $state['items'][0]['id'] ) ? (int) $state['items'][0]['id'] : 0, 'Snapshot list state builder should return the second page of filtered matches.' );
}
