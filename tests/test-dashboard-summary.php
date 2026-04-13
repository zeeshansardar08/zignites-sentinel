<?php
/**
 * Focused tests for dashboard summary payload composition.
 */

require_once __DIR__ . '/bootstrap.php';

use Zignites\Sentinel\Admin\Admin;
use Zignites\Sentinel\Admin\DashboardSummaryPresenter;
use Zignites\Sentinel\Admin\DashboardSummaryStateBuilder;
use Zignites\Sentinel\Admin\RestoreCheckpointPresenter;

class ZNTS_Fake_Dashboard_Snapshot_Repository {
	public $recent = array();

	public function get_recent( $limit = 5 ) {
		return array_slice( $this->recent, 0, (int) $limit );
	}
}

class ZNTS_Fake_Dashboard_Health_Score {
	public $result = array();

	public function calculate() {
		return $this->result;
	}
}

class ZNTS_Fake_Dashboard_Status_Resolver {
	public $status_index = array();
	public $site_status_card = array();
	public $received_snapshots = array();
	public $received_health_score = array();

	public function build_snapshot_status_index( array $snapshots ) {
		$this->received_snapshots = $snapshots;

		return $this->status_index;
	}

	public function build_site_status_card( array $health_score, array $recent_snapshots, array $status_index ) {
		$this->received_health_score = $health_score;

		return $this->site_status_card;
	}
}

class ZNTS_Testable_Dashboard_Admin extends Admin {
	public $fixture = array();

	public function __construct() {
		$this->snapshots                = new ZNTS_Fake_Dashboard_Snapshot_Repository();
		$this->health_score             = new ZNTS_Fake_Dashboard_Health_Score();
		$this->snapshot_status_resolver = new ZNTS_Fake_Dashboard_Status_Resolver();
		$this->status_presenter         = new \Zignites\Sentinel\Admin\StatusPresenter();
		$this->restore_checkpoint_presenter = new RestoreCheckpointPresenter( $this->status_presenter );
		$this->dashboard_summary_state_builder = new DashboardSummaryStateBuilder();
		$this->dashboard_summary_presenter = new DashboardSummaryPresenter();
	}

	public function build_dashboard_summary_payload( $limit = 5 ) {
		return $this->get_dashboard_summary_payload( $limit );
	}

	public function build_restore_dashboard_summary() {
		return $this->get_restore_dashboard_summary();
	}

	public function build_restore_dashboard_health_strip() {
		return parent::get_restore_dashboard_health_strip();
	}

	public function set_recent_snapshots( array $snapshots ) {
		$this->snapshots->recent = $snapshots;
	}

	public function set_health_score_result( array $result ) {
		$this->health_score->result = $result;
	}

	public function set_status_index( array $status_index ) {
		$this->snapshot_status_resolver->status_index = $status_index;
	}

	public function set_site_status_card( array $site_status_card ) {
		$this->snapshot_status_resolver->site_status_card = $site_status_card;
	}

	protected function get_restore_dashboard_health_strip() {
		return isset( $this->fixture['health_strip'] ) ? $this->fixture['health_strip'] : array();
	}

	protected function get_settings() {
		return isset( $this->fixture['settings'] ) ? $this->fixture['settings'] : array(
			'restore_checkpoint_max_age_hours' => 24,
		);
	}

	protected function get_snapshot_detail_by_id( $snapshot_id ) {
		return isset( $this->fixture['snapshot_detail'][ (int) $snapshot_id ] ) ? $this->fixture['snapshot_detail'][ (int) $snapshot_id ] : null;
	}

	protected function get_snapshot_artifacts( $snapshot ) {
		return isset( $this->fixture['artifacts'] ) ? $this->fixture['artifacts'] : array();
	}

	protected function get_restore_operator_checklist( $snapshot, $artifacts = null ) {
		return isset( $this->fixture['operator_checklist'] ) ? $this->fixture['operator_checklist'] : array();
	}

	protected function get_snapshot_health_baseline( $snapshot ) {
		return array_key_exists( 'baseline', $this->fixture ) ? $this->fixture['baseline'] : null;
	}

	protected function get_restore_stage_checkpoint( $snapshot ) {
		return array_key_exists( 'stage_checkpoint', $this->fixture ) ? $this->fixture['stage_checkpoint'] : null;
	}

	protected function get_restore_plan_checkpoint( $snapshot ) {
		return array_key_exists( 'plan_checkpoint', $this->fixture ) ? $this->fixture['plan_checkpoint'] : null;
	}

	protected function get_last_restore_execution( $snapshot ) {
		return isset( $this->fixture['last_execution'] ) ? $this->fixture['last_execution'] : array();
	}

	protected function get_last_restore_rollback( $snapshot ) {
		return isset( $this->fixture['last_rollback'] ) ? $this->fixture['last_rollback'] : array();
	}

	protected function get_checkpoint_timing_summary( array $checkpoint ) {
		if ( isset( $checkpoint['timing'] ) && is_array( $checkpoint['timing'] ) ) {
			return $checkpoint['timing'];
		}

		return parent::get_checkpoint_timing_summary( $checkpoint );
	}

	protected function get_snapshot_activity_url( $snapshot_id ) {
		return 'http://example.test/wp-admin/admin.php?page=zignites-sentinel-event-logs&snapshot_id=' . (int) $snapshot_id;
	}

	protected function get_snapshot_health_comparison( $snapshot ) {
		return isset( $this->fixture['health_comparison'] ) ? $this->fixture['health_comparison'] : array();
	}
}

function znts_test_dashboard_summary_payload_adds_latest_snapshot_links() {
	$admin = new ZNTS_Testable_Dashboard_Admin();
	$admin->set_recent_snapshots(
		array(
			array(
				'id'    => 91,
				'label' => 'Latest snapshot',
			),
		)
	);
	$admin->set_health_score_result(
		array(
			'details' => array(
				'open_conflicts' => array(
					'warning'  => 0,
					'error'    => 0,
					'critical' => 0,
				),
			),
		)
	);
	$admin->set_status_index(
		array(
			91 => array(
				'restore_ready' => true,
			),
		)
	);
	$admin->set_site_status_card(
		array(
			'status'             => 'stable',
			'label'              => 'Stable',
			'recommended_action' => 'No immediate action needed.',
			'primary_action'     => array(
				'title'        => 'Safe to Proceed with Restore Plan',
				'button_label' => 'Open Update Readiness',
				'target'       => 'detail',
			),
			'latest_snapshot'    => array(
				'id'    => 91,
				'label' => 'Latest snapshot',
			),
		)
	);
	$admin->fixture['health_strip'] = array(
		'rows' => array(
			array( 'label' => 'Baseline', 'status' => 'healthy' ),
		),
	);

	$payload = $admin->build_dashboard_summary_payload( 1 );

	znts_assert_same( 1, count( $payload['recent_snapshots'] ), 'Dashboard payload should include the requested number of recent snapshots.' );
	znts_assert_same( 'Stable', $payload['site_status_card']['label'], 'Dashboard payload should preserve the resolver site-status label.' );
	znts_assert_true( false !== strpos( $payload['site_status_card']['detail_url'], 'page=zignites-sentinel-update-readiness' ), 'Dashboard payload should add an update-readiness detail URL for the latest snapshot.' );
	znts_assert_true( false !== strpos( $payload['site_status_card']['detail_url'], 'snapshot_id=91' ), 'Dashboard payload should add the latest snapshot ID to the detail URL.' );
	znts_assert_true( false !== strpos( $payload['site_status_card']['activity_url'], 'snapshot_id=91' ), 'Dashboard payload should add the snapshot activity URL for the latest snapshot.' );
	znts_assert_same( $payload['site_status_card']['detail_url'], $payload['site_status_card']['primary_action']['url'], 'Dashboard payload should resolve the primary action URL from the target metadata.' );
	znts_assert_same( $admin->fixture['health_strip'], $payload['restore_health_strip'], 'Dashboard payload should include the health strip payload.' );
}

function znts_test_restore_dashboard_summary_reports_blocked_operator_state() {
	$admin = new ZNTS_Testable_Dashboard_Admin();
	$admin->set_recent_snapshots(
		array(
			array(
				'id'    => 92,
				'label' => 'Blocked snapshot',
			),
		)
	);
	$admin->fixture['snapshot_detail'] = array(
		92 => array(
			'id'    => 92,
			'label' => 'Blocked snapshot',
		),
	);
	$admin->fixture['operator_checklist'] = array(
		'can_execute' => false,
	);
	$admin->fixture['baseline'] = null;
	$admin->fixture['stage_checkpoint'] = array(
		'status' => 'ready',
		'timing' => array(
			'is_fresh' => false,
			'label'    => 'Expired 2h ago.',
		),
	);

	$summary = $admin->build_restore_dashboard_summary();

	znts_assert_same( 'blocked', $summary['status'], 'Dashboard restore summary should be blocked when checklist gates fail.' );
	znts_assert_true( empty( $summary['can_execute'] ), 'Dashboard restore summary should report can_execute false when checklist gates fail.' );
	znts_assert_same( 'fail', $summary['summary_rows'][0]['status'], 'Dashboard restore summary should flag missing baseline as fail.' );
	znts_assert_same( 'warning', $summary['summary_rows'][1]['status'], 'Dashboard restore summary should flag stale stage checkpoints as warning.' );
	znts_assert_same( 'fail', $summary['summary_rows'][2]['status'], 'Dashboard restore summary should flag missing plan checkpoints as fail.' );
	znts_assert_true( false !== strpos( $summary['detail_url'], 'snapshot_id=92' ), 'Dashboard restore summary should link to the selected snapshot detail.' );
	znts_assert_true( false !== strpos( $summary['activity_url'], 'snapshot_id=92' ), 'Dashboard restore summary should link to snapshot activity.' );
}

function znts_test_restore_dashboard_health_strip_returns_rows_and_detail_url() {
	$admin = new ZNTS_Testable_Dashboard_Admin();
	$admin->set_recent_snapshots(
		array(
			array(
				'id'    => 93,
				'label' => 'Health snapshot',
			),
		)
	);
	$admin->fixture['snapshot_detail'] = array(
		93 => array(
			'id'    => 93,
			'label' => 'Health snapshot',
		),
	);
	$admin->fixture['health_comparison'] = array(
		array(
			'label'  => 'Baseline',
			'status' => 'healthy',
		),
		array(
			'label'  => 'Post-Restore',
			'status' => 'warning',
		),
	);

	$strip = $admin->build_restore_dashboard_health_strip();

	znts_assert_same( 2, count( $strip['rows'] ), 'Dashboard health strip should preserve the health comparison rows.' );
	znts_assert_true( false !== strpos( $strip['detail_url'], 'snapshot_id=93' ), 'Dashboard health strip should link back to the selected snapshot detail.' );
}
