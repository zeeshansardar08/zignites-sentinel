<?php
/**
 * Read-only helper for Update Readiness snapshot list state.
 *
 * @package ZignitesSentinel
 */

namespace Zignites\Sentinel\Admin;

defined( 'ABSPATH' ) || exit;

class SnapshotListStateBuilder {

	/**
	 * Build paginated snapshot list state for Update Readiness.
	 *
	 * @param string $search           Label search term.
	 * @param string $status_filter    Status filter key.
	 * @param int    $current_page     Current snapshot page.
	 * @param int    $per_page         Items per page.
	 * @param object $snapshot_repo    Snapshot repository.
	 * @param object $status_resolver  Snapshot status resolver.
	 * @return array
	 */
	public function build_state( $search, $status_filter, $current_page, $per_page, $snapshot_repo, $status_resolver ) {
		$search         = sanitize_text_field( (string) $search );
		$status_filter  = sanitize_key( (string) $status_filter );
		$per_page       = max( 1, (int) $per_page );
		$current_page   = max( 1, (int) $current_page );
		$base_total     = (int) $snapshot_repo->count_filtered( $search );
		$status_index   = array();
		$items          = array();
		$total_matches  = 0;

		if ( '' === $status_filter ) {
			$offset       = ( $current_page - 1 ) * $per_page;
			$items        = $snapshot_repo->get_filtered(
				array(
					'search' => $search,
					'limit'  => $per_page,
					'offset' => $offset,
				)
			);
			$status_index = $status_resolver->build_snapshot_status_index( $items );
			$total_matches = $base_total;
		} else {
			$batch_size  = 50;
			$match_start = ( $current_page - 1 ) * $per_page;
			$match_end   = $match_start + $per_page;

			for ( $offset = 0; $offset < $base_total; $offset += $batch_size ) {
				$batch = $snapshot_repo->get_filtered(
					array(
						'search' => $search,
						'limit'  => $batch_size,
						'offset' => $offset,
					)
				);

				if ( empty( $batch ) ) {
					break;
				}

				$batch_status_index = $status_resolver->build_snapshot_status_index( $batch );
				$matched_batch      = $status_resolver->filter_snapshots( $batch, $batch_status_index, '', $status_filter, $batch_size );

				foreach ( $matched_batch as $matched_snapshot ) {
					if ( $total_matches >= $match_start && $total_matches < $match_end ) {
						$items[] = $matched_snapshot;
					}

					++$total_matches;
				}
			}

			$status_index = $status_resolver->build_snapshot_status_index( $items );
		}

		$total_pages = max( 1, (int) ceil( $total_matches / $per_page ) );

		return array(
			'items'        => $items,
			'status_index' => $status_index,
			'pagination'   => array(
				'current_page' => min( $current_page, $total_pages ),
				'per_page'     => $per_page,
				'total_items'  => $total_matches,
				'total_pages'  => $total_pages,
			),
		);
	}
}
