<?php
/**
 * Manual update-plan builder.
 *
 * @package ZignitesSentinel
 */

namespace Zignites\Sentinel\Updates;

use Zignites\Sentinel\Logging\Logger;
use Zignites\Sentinel\Snapshots\SnapshotManager;

defined( 'ABSPATH' ) || exit;

class UpdatePlanner {

	/**
	 * Snapshot manager.
	 *
	 * @var SnapshotManager
	 */
	protected $snapshot_manager;

	/**
	 * Source validator.
	 *
	 * @var SourceValidator
	 */
	protected $source_validator;

	/**
	 * Structured logger.
	 *
	 * @var Logger
	 */
	protected $logger;

	/**
	 * Constructor.
	 *
	 * @param SnapshotManager $snapshot_manager Snapshot manager.
	 * @param SourceValidator $source_validator Source validator.
	 * @param Logger          $logger           Structured logger.
	 */
	public function __construct( SnapshotManager $snapshot_manager, SourceValidator $source_validator, Logger $logger ) {
		$this->snapshot_manager = $snapshot_manager;
		$this->source_validator = $source_validator;
		$this->logger           = $logger;
	}

	/**
	 * Build a manual review plan from selected targets.
	 *
	 * @param array $input   Raw selected targets.
	 * @param int   $user_id Current user ID.
	 * @return array
	 */
	public function build_plan( array $input, $user_id ) {
		$candidates = $this->get_candidates_indexed();
		$selected   = array();

		foreach ( $input as $target_key ) {
			$target_key = sanitize_text_field( wp_unslash( $target_key ) );

			if ( isset( $candidates[ $target_key ] ) ) {
				$selected[] = $candidates[ $target_key ];
			}
		}

		if ( empty( $selected ) ) {
			$empty_plan = array(
				'created_at' => current_time( 'mysql', true ),
				'status'     => 'empty',
				'snapshot_id'=> 0,
				'targets'    => array(),
				'note'       => __( 'No valid update targets were selected.', 'zignites-sentinel' ),
			);

			$this->logger->log(
				'update_plan_created',
				'warning',
				'update-readiness',
				__( 'Manual update review plan was empty.', 'zignites-sentinel' ),
				array(
					'status' => $empty_plan['status'],
				)
			);

			return $empty_plan;
		}

		$settings    = get_option( ZNTS_OPTION_SETTINGS, array() );
		$snapshot_id = 0;

		if ( ! empty( $settings['auto_snapshot_on_plan'] ) ) {
			$snapshot_id = (int) $this->snapshot_manager->create_manual_snapshot( $user_id );
		}

		$summary = array(
			'plugin' => 0,
			'theme'  => 0,
			'core'   => 0,
		);

		foreach ( $selected as $target ) {
			if ( isset( $summary[ $target['type'] ] ) ) {
				++$summary[ $target['type'] ];
			}
		}

		$validation = $this->source_validator->validate_update_targets( $selected );
		$plan       = array(
			'created_at'  => current_time( 'mysql', true ),
			'status'      => $this->determine_plan_status( $validation ),
			'snapshot_id' => $snapshot_id,
			'targets'     => $selected,
			'summary'     => $summary,
			'validation'  => $validation,
			'note'        => $this->build_note( $validation ),
		);

		$this->logger->log(
			'update_plan_created',
			$this->map_validation_status_to_severity( $validation['status'] ),
			'update-readiness',
			__( 'Manual update review plan created.', 'zignites-sentinel' ),
			array(
				'status'      => $plan['status'],
				'snapshot_id' => $snapshot_id,
				'summary'     => $summary,
				'validation'  => isset( $validation['summary'] ) ? $validation['summary'] : array(),
			)
		);

		return $plan;
	}

	/**
	 * Get pending update candidates.
	 *
	 * @return array
	 */
	public function get_candidates() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( ! function_exists( 'get_core_updates' ) ) {
			require_once ABSPATH . 'wp-admin/includes/update.php';
		}

		$plugin_updates = get_site_transient( 'update_plugins' );
		$theme_updates  = get_site_transient( 'update_themes' );
		$core_updates   = get_core_updates();
		$plugins        = get_plugins();
		$candidates     = array();

		if ( isset( $plugin_updates->response ) && is_array( $plugin_updates->response ) ) {
			foreach ( $plugin_updates->response as $plugin_file => $plugin_update ) {
				$plugin_data   = isset( $plugins[ $plugin_file ] ) ? $plugins[ $plugin_file ] : array();
				$plugin_name   = isset( $plugin_data['Name'] ) ? $plugin_data['Name'] : $plugin_file;
				$current       = isset( $plugin_data['Version'] ) ? $plugin_data['Version'] : '';
				$new_version   = isset( $plugin_update->new_version ) ? $plugin_update->new_version : '';
				$candidates[]  = array(
					'key'             => 'plugin:' . $plugin_file,
					'type'            => 'plugin',
					'slug'            => sanitize_text_field( $plugin_file ),
					'label'           => sanitize_text_field( $plugin_name ),
					'current_version' => sanitize_text_field( $current ),
					'new_version'     => sanitize_text_field( $new_version ),
				);
			}
		}

		if ( isset( $theme_updates->response ) && is_array( $theme_updates->response ) ) {
			foreach ( $theme_updates->response as $stylesheet => $theme_update ) {
				$candidates[] = array(
					'key'             => 'theme:' . $stylesheet,
					'type'            => 'theme',
					'slug'            => sanitize_text_field( $stylesheet ),
					'label'           => sanitize_text_field( $stylesheet ),
					'current_version' => '',
					'new_version'     => isset( $theme_update['new_version'] ) ? sanitize_text_field( $theme_update['new_version'] ) : '',
				);
			}
		}

		if ( is_array( $core_updates ) ) {
			foreach ( $core_updates as $core_update ) {
				if ( empty( $core_update->current ) ) {
					continue;
				}

				$candidates[] = array(
					'key'             => 'core:wordpress',
					'type'            => 'core',
					'slug'            => 'wordpress',
					'label'           => __( 'WordPress Core', 'zignites-sentinel' ),
					'current_version' => get_bloginfo( 'version' ),
					'new_version'     => sanitize_text_field( $core_update->current ),
				);
				break;
			}
		}

		return $candidates;
	}

	/**
	 * Index candidates by key.
	 *
	 * @return array
	 */
	protected function get_candidates_indexed() {
		$indexed = array();

		foreach ( $this->get_candidates() as $candidate ) {
			$indexed[ $candidate['key'] ] = $candidate;
		}

		return $indexed;
	}

	/**
	 * Determine the plan status from target validation results.
	 *
	 * @param array $validation Validation result.
	 * @return string
	 */
	protected function determine_plan_status( array $validation ) {
		if ( isset( $validation['status'] ) && 'fail' === $validation['status'] ) {
			return 'blocked_for_review';
		}

		if ( isset( $validation['status'] ) && 'warning' === $validation['status'] ) {
			return 'caution';
		}

		return 'ready_for_review';
	}

	/**
	 * Build the plan note from target validation results.
	 *
	 * @param array $validation Validation result.
	 * @return string
	 */
	protected function build_note( array $validation ) {
		if ( isset( $validation['status'] ) && 'fail' === $validation['status'] ) {
			return __( 'Manual review plan created, but one or more selected targets are missing source or package readiness.', 'zignites-sentinel' );
		}

		if ( isset( $validation['status'] ) && 'warning' === $validation['status'] ) {
			return __( 'Manual review plan created with cautionary source/package signals.', 'zignites-sentinel' );
		}

		return __( 'Manual review plan created. This does not execute updates.', 'zignites-sentinel' );
	}

	/**
	 * Map validation status to logger severity.
	 *
	 * @param string $status Validation status.
	 * @return string
	 */
	protected function map_validation_status_to_severity( $status ) {
		if ( 'fail' === $status || 'warning' === $status ) {
			return 'warning';
		}

		return 'info';
	}
}
