<?php
/**
 * Site stability score calculation.
 *
 * @package ZignitesSentinel
 */

namespace Zignites\Sentinel\Diagnostics;

use Zignites\Sentinel\Logging\LogRepository;

defined( 'ABSPATH' ) || exit;

class HealthScore {

	/**
	 * Conflict repository.
	 *
	 * @var ConflictRepository
	 */
	protected $conflicts;

	/**
	 * Log repository.
	 *
	 * @var LogRepository
	 */
	protected $logs;

	/**
	 * Constructor.
	 *
	 * @param ConflictRepository $conflicts Conflict repository.
	 * @param LogRepository      $logs      Log repository.
	 */
	public function __construct( ConflictRepository $conflicts, LogRepository $logs ) {
		$this->conflicts = $conflicts;
		$this->logs      = $logs;
	}

	/**
	 * Build score data for the dashboard.
	 *
	 * @return array
	 */
	public function calculate() {
		$conflict_counts = $this->conflicts->count_open_by_severity();
		$log_counts      = $this->logs->count_recent_by_severity( 7 );
		$score           = 100;

		$score -= min( 45, $conflict_counts['critical'] * 18 );
		$score -= min( 25, $conflict_counts['error'] * 10 );
		$score -= min( 15, $conflict_counts['warning'] * 5 );
		$score -= min( 10, $log_counts['critical'] * 4 );
		$score -= min( 10, $log_counts['error'] * 2 );

		$score = max( 0, $score );

		return array(
			'score'   => $score,
			'label'   => $this->get_label( $score ),
			'details' => array(
				'open_conflicts' => $conflict_counts,
				'recent_logs'    => $log_counts,
			),
			'summary' => $this->get_summary( $score, $conflict_counts, $log_counts ),
		);
	}

	/**
	 * Get the score status label.
	 *
	 * @param int $score Stability score.
	 * @return string
	 */
	protected function get_label( $score ) {
		if ( $score < 50 ) {
			return 'critical';
		}

		if ( $score < 80 ) {
			return 'warning';
		}

		return 'stable';
	}

	/**
	 * Build a human-readable summary.
	 *
	 * @param int   $score           Stability score.
	 * @param array $conflict_counts Conflict counts.
	 * @param array $log_counts      Log counts.
	 * @return string
	 */
	protected function get_summary( $score, array $conflict_counts, array $log_counts ) {
		if ( $score < 50 ) {
			return sprintf(
				/* translators: %1$d = critical conflict count, %2$d = recent critical log count */
				__( 'Critical attention needed. %1$d open critical signals and %2$d critical logs were recorded recently.', 'zignites-sentinel' ),
				(int) $conflict_counts['critical'],
				(int) $log_counts['critical']
			);
		}

		if ( $score < 80 ) {
			return sprintf(
				/* translators: %1$d = warning conflict count, %2$d = recent error log count */
				__( 'Warnings detected. %1$d warning signals and %2$d recent error-level logs are affecting stability.', 'zignites-sentinel' ),
				(int) $conflict_counts['warning'],
				(int) $log_counts['error']
			);
		}

		return __( 'The site appears stable based on currently captured signals, but this score is diagnostic guidance rather than a guarantee.', 'zignites-sentinel' );
	}
}
