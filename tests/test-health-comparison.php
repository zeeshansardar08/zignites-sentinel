<?php
/**
 * Focused tests for health comparison rows and dashboard health strip output.
 */

require_once __DIR__ . '/bootstrap.php';

use Zignites\Sentinel\Admin\Admin;
use Zignites\Sentinel\Admin\DashboardSummaryPresenter;

class ZNTS_Fake_Health_Comparison_Snapshot_Repository {
	public $recent = array();

	public function get_recent( $limit = 5 ) {
		return array_slice( $this->recent, 0, (int) $limit );
	}
}

class ZNTS_Testable_Health_Comparison_Admin extends Admin {
	public $fixture = array();

	public function __construct() {
		$this->snapshots = new ZNTS_Fake_Health_Comparison_Snapshot_Repository();
		$this->status_presenter = new \Zignites\Sentinel\Admin\StatusPresenter();
		$this->dashboard_summary_presenter = new DashboardSummaryPresenter();
	}

	public function build_health_delta( array $summary, array $baseline_summary ) {
		return $this->build_health_delta_summary( $summary, $baseline_summary );
	}

	public function build_health_row( $label, array $health, array $baseline = array() ) {
		return $this->build_health_snapshot_row( $label, $health, $baseline );
	}

	public function build_health_comparison( $snapshot ) {
		return $this->get_snapshot_health_comparison( $snapshot );
	}

	public function build_dashboard_health_strip() {
		return $this->get_restore_dashboard_health_strip();
	}

	public function set_recent_snapshots( array $snapshots ) {
		$this->snapshots->recent = $snapshots;
	}

	protected function get_snapshot_health_baseline( $snapshot ) {
		return array_key_exists( 'baseline', $this->fixture ) ? $this->fixture['baseline'] : null;
	}

	protected function get_last_restore_execution( $snapshot ) {
		return isset( $this->fixture['last_execution'] ) ? $this->fixture['last_execution'] : array();
	}

	protected function get_last_restore_rollback( $snapshot ) {
		return isset( $this->fixture['last_rollback'] ) ? $this->fixture['last_rollback'] : array();
	}

	protected function get_snapshot_detail_by_id( $snapshot_id ) {
		return isset( $this->fixture['snapshot_detail'][ (int) $snapshot_id ] ) ? $this->fixture['snapshot_detail'][ (int) $snapshot_id ] : null;
	}
}

function znts_test_health_delta_summary_reports_changes_in_fixed_order() {
	$admin = new ZNTS_Testable_Health_Comparison_Admin();

	$delta = $admin->build_health_delta(
		array(
			'pass'    => 5,
			'warning' => 1,
			'fail'    => 0,
		),
		array(
			'pass'    => 4,
			'warning' => 2,
			'fail'    => 1,
		)
	);

	znts_assert_same( 'pass +1, warning -1, fail -1', $delta, 'Health delta summary should report pass, warning, and fail changes in a stable order.' );
}

function znts_test_health_delta_summary_returns_no_change_when_counts_match() {
	$admin = new ZNTS_Testable_Health_Comparison_Admin();

	$delta = $admin->build_health_delta(
		array(
			'pass'    => 3,
			'warning' => 1,
			'fail'    => 0,
		),
		array(
			'pass'    => 3,
			'warning' => 1,
			'fail'    => 0,
		)
	);

	znts_assert_same( 'No change', $delta, 'Health delta summary should use the no-change fallback when counts are unchanged.' );
}

function znts_test_health_snapshot_row_normalizes_summary_and_includes_delta_only_with_baseline() {
	$admin = new ZNTS_Testable_Health_Comparison_Admin();

	$row_with_baseline = $admin->build_health_row(
		'Post-Restore',
		array(
			'status'       => 'degraded',
			'generated_at' => '2025-01-04 10:00:00',
			'summary'      => array(
				'pass'    => 6,
				'warning' => 2,
				'fail'    => 1,
			),
			'note'         => 'Admin probe returned a warning.',
		),
		array(
			'summary' => array(
				'pass'    => 7,
				'warning' => 1,
				'fail'    => 1,
			),
		)
	);

	$row_without_baseline = $admin->build_health_row(
		'Baseline',
		array(
			'status'  => 'healthy',
			'summary' => array(
				'pass' => 7,
			),
		),
		array()
	);

	znts_assert_same( 'degraded', $row_with_baseline['status'], 'Health row should preserve the provided health status.' );
	znts_assert_same( 'warning', $row_with_baseline['status_pill'], 'Health row should expose a warning pill for degraded health results.' );
	znts_assert_same( 'Degraded', $row_with_baseline['status_label'], 'Health row should expose a formatted status label.' );
	znts_assert_same( 6, $row_with_baseline['summary']['pass'], 'Health row should normalize pass counts to integers.' );
	znts_assert_same( 2, $row_with_baseline['summary']['warning'], 'Health row should normalize warning counts to integers.' );
	znts_assert_same( 1, $row_with_baseline['summary']['fail'], 'Health row should normalize fail counts to integers.' );
	znts_assert_same( 'pass -1, warning +1', $row_with_baseline['delta'], 'Health row should include a compact delta when a baseline summary exists.' );
	znts_assert_same( 'Admin probe returned a warning.', $row_with_baseline['note'], 'Health row should preserve the supplied note.' );
	znts_assert_same( '', $row_without_baseline['delta'], 'Health row should omit the delta when no baseline summary exists.' );
	znts_assert_same( 0, $row_without_baseline['summary']['warning'], 'Health row should default missing warning counts to zero.' );
	znts_assert_same( 0, $row_without_baseline['summary']['fail'], 'Health row should default missing fail counts to zero.' );
}

function znts_test_snapshot_health_comparison_collects_baseline_restore_and_rollback_rows() {
	$admin = new ZNTS_Testable_Health_Comparison_Admin();
	$admin->fixture = array(
		'baseline' => array(
			'status'       => 'healthy',
			'generated_at' => '2025-01-05 09:00:00',
			'summary'      => array(
				'pass'    => 7,
				'warning' => 0,
				'fail'    => 0,
			),
		),
		'last_execution' => array(
			'health_verification' => array(
				'status'       => 'degraded',
				'generated_at' => '2025-01-05 10:00:00',
				'summary'      => array(
					'pass'    => 6,
					'warning' => 1,
					'fail'    => 0,
				),
				'note'         => 'REST probe returned a warning.',
			),
		),
		'last_rollback' => array(
			'health_verification' => array(
				'status'       => 'healthy',
				'generated_at' => '2025-01-05 11:00:00',
				'summary'      => array(
					'pass'    => 7,
					'warning' => 0,
					'fail'    => 0,
				),
				'note'         => 'Checks recovered after rollback.',
			),
		),
	);

	$rows = $admin->build_health_comparison(
		array(
			'id'    => 120,
			'label' => 'Snapshot 120',
		)
	);

	znts_assert_same( 3, count( $rows ), 'Health comparison should include baseline, post-restore, and post-rollback rows when all are available.' );
	znts_assert_same( 'Baseline', $rows[0]['label'], 'Health comparison should start with the baseline row.' );
	znts_assert_same( 'Post-Restore', $rows[1]['label'], 'Health comparison should include the post-restore row second.' );
	znts_assert_same( 'pass -1, warning +1', $rows[1]['delta'], 'Post-restore row should compare itself to the baseline summary.' );
	znts_assert_same( 'Post-Rollback', $rows[2]['label'], 'Health comparison should include the post-rollback row third.' );
	znts_assert_same( 'No change', $rows[2]['delta'], 'Post-rollback row should report no change when it matches the baseline summary.' );
}

function znts_test_restore_dashboard_health_strip_returns_empty_rows_payload_when_snapshot_has_no_health_rows() {
	$admin = new ZNTS_Testable_Health_Comparison_Admin();
	$admin->set_recent_snapshots(
		array(
			array(
				'id'    => 121,
				'label' => 'Snapshot 121',
			),
		)
	);
	$admin->fixture['snapshot_detail'] = array(
		121 => array(
			'id'    => 121,
			'label' => 'Snapshot 121',
		),
	);
	$admin->fixture['baseline'] = null;
	$admin->fixture['last_execution'] = array();
	$admin->fixture['last_rollback'] = array();

	$strip = $admin->build_dashboard_health_strip();

	znts_assert_same( 121, $strip['snapshot']['id'], 'Dashboard health strip should preserve the selected latest snapshot when health rows are empty.' );
	znts_assert_same( 0, count( $strip['rows'] ), 'Dashboard health strip should return an empty rows array when no health comparison rows exist.' );
	znts_assert_true( false !== strpos( $strip['detail_url'], 'snapshot_id=121' ), 'Dashboard health strip should still link to the latest snapshot detail when no health rows exist.' );
}
