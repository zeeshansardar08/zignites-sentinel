<?php
/**
 * Local status payload for future agency dashboard sync.
 *
 * @package ZignitesSentinel
 */

namespace Zignites\Sentinel\Platform;

defined( 'ABSPATH' ) || exit;

class SiteStatusModel {

	/**
	 * Build a stable, read-only status payload from existing dashboard state.
	 *
	 * @param array $summary Dashboard summary payload.
	 * @param array $context Dashboard context payload.
	 * @param array $safe_update_window Last Safe Update Window payload.
	 * @return array
	 */
	public function build_payload( array $summary, array $context, array $safe_update_window = array() ) {
		$site_status_card      = $this->array_value( $summary, 'site_status_card' );
		$health_score          = $this->array_value( $summary, 'health_score' );
		$system_health         = $this->array_value( $summary, 'system_health' );
		$snapshot_status_index = $this->array_value( $summary, 'snapshot_status_index' );
		$recent_snapshots      = $this->array_value( $summary, 'recent_snapshots' );
		$artifact_storage      = $this->array_value( $context, 'artifact_storage' );
		$latest_snapshot       = $this->resolve_latest_snapshot( $site_status_card, $recent_snapshots );
		$latest_snapshot_id    = ! empty( $latest_snapshot['id'] ) ? (int) $latest_snapshot['id'] : 0;

		return array(
			'schema_version'     => '1.0',
			'generated_at'       => current_time( 'mysql', true ),
			'site'               => $this->build_site_identity( $context ),
			'status'             => $this->build_status_summary( $site_status_card ),
			'checkpoint'         => $this->build_checkpoint_summary( $latest_snapshot, $latest_snapshot_id, $snapshot_status_index ),
			'last_update_window' => $this->build_update_window_summary( $safe_update_window ),
			'storage'            => $this->build_storage_summary( $artifact_storage ),
			'health'             => $this->build_health_summary( $health_score, $system_health ),
			'warnings'           => $this->build_warnings( $site_status_card, $artifact_storage, $health_score, $safe_update_window ),
		);
	}

	/**
	 * Build site identity metadata.
	 *
	 * @param array $context Dashboard context payload.
	 * @return array
	 */
	protected function build_site_identity( array $context ) {
		return array(
			'name'             => function_exists( 'get_bloginfo' ) ? sanitize_text_field( (string) get_bloginfo( 'name' ) ) : '',
			'url'              => isset( $context['site_url'] ) ? esc_url_raw( (string) $context['site_url'] ) : ( function_exists( 'home_url' ) ? esc_url_raw( home_url( '/' ) ) : '' ),
			'wordpress'        => isset( $context['wordpress'] ) ? sanitize_text_field( (string) $context['wordpress'] ) : '',
			'php'              => isset( $context['php'] ) ? sanitize_text_field( (string) $context['php'] ) : PHP_VERSION,
			'plugin_version'   => isset( $context['plugin_version'] ) ? sanitize_text_field( (string) $context['plugin_version'] ) : ( defined( 'ZNTS_VERSION' ) ? ZNTS_VERSION : '' ),
			'database_version' => isset( $context['db_version'] ) ? sanitize_text_field( (string) $context['db_version'] ) : '',
		);
	}

	/**
	 * Build top-level operator status.
	 *
	 * @param array $site_status_card Site status card payload.
	 * @return array
	 */
	protected function build_status_summary( array $site_status_card ) {
		return array(
			'state'              => isset( $site_status_card['status'] ) ? sanitize_key( (string) $site_status_card['status'] ) : 'unknown',
			'label'              => isset( $site_status_card['status_label'] ) ? sanitize_text_field( (string) $site_status_card['status_label'] ) : '',
			'recommended_action' => isset( $site_status_card['recommended_action'] ) ? sanitize_text_field( (string) $site_status_card['recommended_action'] ) : '',
		);
	}

	/**
	 * Build latest checkpoint summary.
	 *
	 * @param array $latest_snapshot Latest snapshot payload.
	 * @param int   $snapshot_id Latest snapshot ID.
	 * @param array $snapshot_status_index Snapshot status index.
	 * @return array
	 */
	protected function build_checkpoint_summary( array $latest_snapshot, $snapshot_id, array $snapshot_status_index ) {
		$status = $snapshot_id > 0 && isset( $snapshot_status_index[ $snapshot_id ] ) && is_array( $snapshot_status_index[ $snapshot_id ] )
			? $snapshot_status_index[ $snapshot_id ]
			: array();

		return array(
			'id'            => $snapshot_id,
			'label'         => isset( $latest_snapshot['label'] ) ? sanitize_text_field( (string) $latest_snapshot['label'] ) : '',
			'created_at'    => isset( $latest_snapshot['created_at'] ) ? sanitize_text_field( (string) $latest_snapshot['created_at'] ) : '',
			'state'         => isset( $status['status'] ) ? sanitize_key( (string) $status['status'] ) : '',
			'restore_ready' => ! empty( $status['restore_ready'] ),
		);
	}

	/**
	 * Build Safe Update Window summary.
	 *
	 * @param array $safe_update_window Last Safe Update Window payload.
	 * @return array
	 */
	protected function build_update_window_summary( array $safe_update_window ) {
		return array(
			'snapshot_id'  => isset( $safe_update_window['snapshot_id'] ) ? absint( $safe_update_window['snapshot_id'] ) : 0,
			'status'       => isset( $safe_update_window['status'] ) ? sanitize_key( (string) $safe_update_window['status'] ) : '',
			'confirmed'    => ! empty( $safe_update_window['confirmed'] ),
			'checked_at'   => isset( $safe_update_window['checked_at'] ) ? sanitize_text_field( (string) $safe_update_window['checked_at'] ) : '',
			'completed_at' => isset( $safe_update_window['completed_at'] ) ? sanitize_text_field( (string) $safe_update_window['completed_at'] ) : '',
		);
	}

	/**
	 * Build artifact storage summary.
	 *
	 * @param array $artifact_storage Artifact exposure payload.
	 * @return array
	 */
	protected function build_storage_summary( array $artifact_storage ) {
		return array(
			'status'  => isset( $artifact_storage['status'] ) ? sanitize_key( (string) $artifact_storage['status'] ) : 'unknown',
			'label'   => isset( $artifact_storage['label'] ) ? sanitize_text_field( (string) $artifact_storage['label'] ) : '',
			'message' => isset( $artifact_storage['message'] ) ? sanitize_text_field( (string) $artifact_storage['message'] ) : '',
		);
	}

	/**
	 * Build health summary.
	 *
	 * @param array $health_score Health score payload.
	 * @param array $system_health System health card.
	 * @return array
	 */
	protected function build_health_summary( array $health_score, array $system_health ) {
		return array(
			'score'         => isset( $health_score['score'] ) ? (int) $health_score['score'] : 0,
			'status'        => isset( $system_health['status'] ) ? sanitize_key( (string) $system_health['status'] ) : '',
			'status_label'  => isset( $system_health['status_label'] ) ? sanitize_text_field( (string) $system_health['status_label'] ) : '',
			'open_warnings' => isset( $health_score['details']['open_conflicts']['warning'] ) ? absint( $health_score['details']['open_conflicts']['warning'] ) : 0,
			'open_critical' => isset( $health_score['details']['open_conflicts']['critical'] ) ? absint( $health_score['details']['open_conflicts']['critical'] ) : 0,
		);
	}

	/**
	 * Build compact warnings list.
	 *
	 * @param array $site_status_card Site status card payload.
	 * @param array $artifact_storage Artifact exposure payload.
	 * @param array $health_score Health score payload.
	 * @param array $safe_update_window Last Safe Update Window payload.
	 * @return array
	 */
	protected function build_warnings( array $site_status_card, array $artifact_storage, array $health_score, array $safe_update_window ) {
		$warnings = array();

		if ( isset( $site_status_card['status'] ) && in_array( (string) $site_status_card['status'], array( 'attention', 'at_risk', 'blocked' ), true ) ) {
			$warnings[] = array(
				'key'     => 'site_status',
				'message' => isset( $site_status_card['recommended_action'] ) ? sanitize_text_field( (string) $site_status_card['recommended_action'] ) : __( 'Site status needs review.', 'zignites-sentinel' ),
			);
		}

		if ( isset( $artifact_storage['status'] ) && in_array( (string) $artifact_storage['status'], array( 'warning', 'fail', 'blocked' ), true ) ) {
			$warnings[] = array(
				'key'     => 'artifact_storage',
				'message' => isset( $artifact_storage['message'] ) ? sanitize_text_field( (string) $artifact_storage['message'] ) : __( 'Artifact storage needs review.', 'zignites-sentinel' ),
			);
		}

		if ( ! empty( $health_score['details']['open_conflicts']['critical'] ) ) {
			$warnings[] = array(
				'key'     => 'health_critical',
				'message' => __( 'Critical health conflicts are open.', 'zignites-sentinel' ),
			);
		}

		if ( isset( $safe_update_window['status'] ) && in_array( (string) $safe_update_window['status'], array( 'degraded', 'failed', 'blocked' ), true ) ) {
			$warnings[] = array(
				'key'     => 'safe_update_window',
				'message' => __( 'The latest Safe Update Window needs review.', 'zignites-sentinel' ),
			);
		}

		return $warnings;
	}

	/**
	 * Resolve latest snapshot from site-status or recent list.
	 *
	 * @param array $site_status_card Site status card payload.
	 * @param array $recent_snapshots Recent snapshots.
	 * @return array
	 */
	protected function resolve_latest_snapshot( array $site_status_card, array $recent_snapshots ) {
		if ( isset( $site_status_card['latest_snapshot'] ) && is_array( $site_status_card['latest_snapshot'] ) ) {
			return $site_status_card['latest_snapshot'];
		}

		return ! empty( $recent_snapshots[0] ) && is_array( $recent_snapshots[0] ) ? $recent_snapshots[0] : array();
	}

	/**
	 * Safely fetch an array value.
	 *
	 * @param array  $data Source data.
	 * @param string $key  Data key.
	 * @return array
	 */
	protected function array_value( array $data, $key ) {
		return isset( $data[ $key ] ) && is_array( $data[ $key ] ) ? $data[ $key ] : array();
	}
}
