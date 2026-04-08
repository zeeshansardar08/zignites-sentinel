<?php
/**
 * Read-only presentation helper for restore checkpoint and run summary payloads.
 *
 * @package ZignitesSentinel
 */

namespace Zignites\Sentinel\Admin;

defined( 'ABSPATH' ) || exit;

use Zignites\Sentinel\Snapshots\RestoreExecutor;

class RestoreCheckpointPresenter {

	/**
	 * Shared status presenter.
	 *
	 * @var StatusPresenter
	 */
	protected $status_presenter;

	/**
	 * Constructor.
	 *
	 * @param StatusPresenter|null $status_presenter Optional shared status presenter.
	 */
	public function __construct( StatusPresenter $status_presenter = null ) {
		$this->status_presenter = $status_presenter ? $status_presenter : new StatusPresenter();
	}

	/**
	 * Build a checkpoint summary card.
	 *
	 * @param string $title        Card title.
	 * @param array  $checkpoint   Checkpoint data.
	 * @param string $summary_line Summary line.
	 * @param array  $timing       Timing summary payload.
	 * @return array
	 */
	public function build_checkpoint_card( $title, array $checkpoint, $summary_line, array $timing = array() ) {
		$fingerprint = isset( $checkpoint['package_fingerprint'] ) && is_array( $checkpoint['package_fingerprint'] ) ? $checkpoint['package_fingerprint'] : array();
		$source_path = isset( $fingerprint['source_path'] ) ? (string) $fingerprint['source_path'] : '';
		$secondary   = array();
		$status      = $this->status_presenter->present_readiness( isset( $checkpoint['status'] ) ? $checkpoint['status'] : '' );

		if ( '' !== $source_path ) {
			$secondary[] = sprintf( __( 'Package: %s', 'zignites-sentinel' ), $source_path );
		}

		if ( ! empty( $timing['label'] ) ) {
			$secondary[] = (string) $timing['label'];
		}

		return array(
			'title'        => $title,
			'status'       => isset( $checkpoint['status'] ) ? (string) $checkpoint['status'] : '',
			'badge'        => isset( $status['pill'] ) ? (string) $status['pill'] : 'info',
			'status_label' => isset( $status['label'] ) ? (string) $status['label'] : '',
			'timestamp'    => isset( $checkpoint['generated_at'] ) ? (string) $checkpoint['generated_at'] : '',
			'primary'      => (string) $summary_line,
			'secondary'    => implode( ' ', $secondary ),
			'link_url'     => '',
			'link_label'   => '',
		);
	}

	/**
	 * Build checkpoint timing metadata from the configured freshness window.
	 *
	 * @param array $checkpoint    Checkpoint data.
	 * @param int   $max_age_hours Maximum checkpoint age in hours.
	 * @param int   $current_ts    Current timestamp.
	 * @return array
	 */
	public function build_timing_summary( array $checkpoint, $max_age_hours, $current_ts ) {
		$generated_at  = isset( $checkpoint['generated_at'] ) ? (string) $checkpoint['generated_at'] : '';
		$generated_ts  = '' !== $generated_at ? strtotime( $generated_at ) : false;
		$max_age_hours = max( 1, (int) $max_age_hours );

		if ( false === $generated_ts ) {
			return array(
				'is_fresh'      => false,
				'generated_at'  => $generated_at,
				'expires_at'    => '',
				'seconds_until' => 0,
				'label'         => __( 'Checkpoint timestamp is invalid.', 'zignites-sentinel' ),
			);
		}

		$expires_ts    = $generated_ts + ( $max_age_hours * HOUR_IN_SECONDS );
		$seconds_until = $expires_ts - (int) $current_ts;
		$duration      = $this->format_duration_seconds( abs( $seconds_until ) );
		$label         = $seconds_until >= 0
			? sprintf(
				/* translators: %s: remaining duration */
				__( 'Expires in %s.', 'zignites-sentinel' ),
				$duration
			)
			: sprintf(
				/* translators: %s: expired duration */
				__( 'Expired %s ago.', 'zignites-sentinel' ),
				$duration
			);

		return array(
			'is_fresh'      => $seconds_until >= 0,
			'generated_at'  => $generated_at,
			'expires_at'    => gmdate( 'Y-m-d H:i:s', $expires_ts ),
			'seconds_until' => (int) $seconds_until,
			'label'         => $label,
		);
	}

	/**
	 * Build a readable gate summary line.
	 *
	 * @param string     $missing_message Fallback message when no checkpoint exists.
	 * @param array|null $checkpoint      Checkpoint data.
	 * @param array      $timing          Timing summary payload.
	 * @return string
	 */
	public function build_gate_summary( $missing_message, $checkpoint, array $timing = array() ) {
		if ( ! is_array( $checkpoint ) || empty( $checkpoint ) ) {
			return $missing_message;
		}

		return sprintf(
			/* translators: 1: checkpoint status, 2: timing label */
			__( '%1$s. %2$s', 'zignites-sentinel' ),
			isset( $checkpoint['status'] ) ? $this->status_presenter->format_status_label( (string) $checkpoint['status'] ) : __( 'Stored', 'zignites-sentinel' ),
			isset( $timing['label'] ) ? (string) $timing['label'] : ''
		);
	}

	/**
	 * Build a readable backup storage summary before execution.
	 *
	 * @param array $snapshot       Snapshot detail.
	 * @param array $execution      Last execution result.
	 * @param array $resume_context Resume context.
	 * @param array $uploads        Upload directory payload.
	 * @return string
	 */
	public function build_backup_summary( array $snapshot, array $execution, array $resume_context, array $uploads ) {
		if ( ! empty( $resume_context['can_resume'] ) && ! empty( $execution['backup_root'] ) ) {
			return sprintf(
				/* translators: %s: backup root path */
				__( 'Resume will reuse the existing backup root at %s', 'zignites-sentinel' ),
				(string) $execution['backup_root']
			);
		}

		if ( empty( $uploads['basedir'] ) || ! empty( $uploads['error'] ) ) {
			return __( 'Backup storage path will be resolved under uploads at execution time.', 'zignites-sentinel' );
		}

		$base_path = trailingslashit( wp_normalize_path( $uploads['basedir'] ) ) . RestoreExecutor::BACKUP_DIRECTORY;

		return sprintf(
			/* translators: 1: backup root base path, 2: snapshot ID */
			__( 'A new run-specific backup directory will be created under %1$s for snapshot %2$d', 'zignites-sentinel' ),
			$base_path,
			isset( $snapshot['id'] ) ? (int) $snapshot['id'] : 0
		);
	}

	/**
	 * Build a restore or rollback run summary card.
	 *
	 * @param string     $title                Card title.
	 * @param array      $result               Result payload.
	 * @param array      $resume_context       Resume context.
	 * @param array|null $execution_checkpoint Optional execution checkpoint payload.
	 * @param string     $journal_url          Resolved run journal URL.
	 * @return array
	 */
	public function build_run_card( $title, array $result, array $resume_context, $execution_checkpoint = null, $journal_url = '' ) {
		$execution_checkpoint = is_array( $execution_checkpoint ) ? $execution_checkpoint : array();
		$run_id               = isset( $result['run_id'] ) ? (string) $result['run_id'] : '';
		$summary              = isset( $result['summary'] ) && is_array( $result['summary'] ) ? $result['summary'] : array();
		$status               = $this->status_presenter->present_run( isset( $result['status'] ) ? $result['status'] : '' );
		$primary              = sprintf(
			/* translators: 1: pass count, 2: warning count, 3: fail count */
			__( '%1$d pass, %2$d warning, %3$d fail.', 'zignites-sentinel' ),
			isset( $summary['pass'] ) ? (int) $summary['pass'] : 0,
			isset( $summary['warning'] ) ? (int) $summary['warning'] : 0,
			isset( $summary['fail'] ) ? (int) $summary['fail'] : 0
		);
		$secondary            = ! empty( $resume_context['can_resume'] )
			? sprintf(
				/* translators: %d: completed item count */
				__( 'Resume available with %d completed items.', 'zignites-sentinel' ),
				isset( $resume_context['completed_item_count'] ) ? (int) $resume_context['completed_item_count'] : 0
			)
			: __( 'No resume action is currently required.', 'zignites-sentinel' );

		if ( ! empty( $result['health_verification']['status'] ) ) {
			$secondary = sprintf(
				/* translators: %s: health status */
				__( 'Health: %s', 'zignites-sentinel' ),
				(string) $result['health_verification']['status']
			);
		}

		if ( ! empty( $execution_checkpoint['checkpoint'] ) && is_array( $execution_checkpoint['checkpoint'] ) ) {
			$checkpoint_state = $execution_checkpoint['checkpoint'];
			$stage_reuse      = ! empty( $checkpoint_state['stage_ready'] ) ? __( 'Stage reuse ready.', 'zignites-sentinel' ) : __( 'No preserved stage.', 'zignites-sentinel' );
			$health_reuse     = ! empty( $checkpoint_state['health_completed'] ) ? __( 'Health reuse ready.', 'zignites-sentinel' ) : __( 'Health will rerun.', 'zignites-sentinel' );
			$secondary        = $stage_reuse . ' ' . $health_reuse;
		}

		return array(
			'title'        => $title,
			'status'       => isset( $result['status'] ) ? (string) $result['status'] : '',
			'badge'        => isset( $status['pill'] ) ? (string) $status['pill'] : 'info',
			'status_label' => isset( $status['label'] ) ? (string) $status['label'] : '',
			'timestamp'    => isset( $result['generated_at'] ) ? (string) $result['generated_at'] : '',
			'primary'      => $primary,
			'secondary'    => $secondary,
			'link_url'     => (string) $journal_url,
			'link_label'   => '' !== $run_id ? sprintf( __( 'Run ID: %s', 'zignites-sentinel' ), $run_id ) : '',
		);
	}

	/**
	 * Format a short human-readable duration.
	 *
	 * @param int $seconds Duration in seconds.
	 * @return string
	 */
	protected function format_duration_seconds( $seconds ) {
		$seconds = max( 0, (int) $seconds );
		$hours   = (int) floor( $seconds / HOUR_IN_SECONDS );
		$minutes = (int) floor( ( $seconds % HOUR_IN_SECONDS ) / MINUTE_IN_SECONDS );

		if ( $hours > 0 ) {
			return sprintf(
				/* translators: 1: hours, 2: minutes */
				__( '%1$dh %2$dm', 'zignites-sentinel' ),
				$hours,
				$minutes
			);
		}

		return sprintf(
			/* translators: %d: minutes */
			__( '%dm', 'zignites-sentinel' ),
			max( 1, $minutes )
		);
	}
}
