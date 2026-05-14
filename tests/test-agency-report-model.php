<?php
/**
 * Focused tests for agency report payload foundations.
 */

require_once __DIR__ . '/bootstrap.php';

use Zignites\Sentinel\Platform\AgencyReportModel;

function znts_test_agency_report_model_builds_white_label_payload() {
	$model = new AgencyReportModel();

	$report = $model->build_report(
		array(
			'id'         => 77,
			'label'      => 'Before plugin update window',
			'created_at' => '2026-05-14 11:00:00',
			'status'     => 'complete',
		),
		array(
			'confirmed'    => true,
			'checked_at'   => '2026-05-14 11:30:00',
			'completed_at' => '2026-05-14 11:40:00',
			'health'       => array(
				'status'  => 'healthy',
				'summary' => array(
					'pass'    => 3,
					'warning' => 0,
					'fail'    => 0,
				),
			),
		),
		array(
			'site' => array(
				'url'            => 'http://example.test',
				'plugin_version' => '1.33.0',
			),
			'health' => array(
				'score'  => 98,
				'status' => 'safe',
			),
			'storage' => array(
				'status' => 'pass',
			),
			'warnings' => array(),
		),
		array(
			'agency_name'    => 'Example Agency',
			'agency_contact' => 'ops@example.test',
			'report_title'   => 'Maintenance Report',
		)
	);

	znts_assert_same( '1.0', $report['schema_version'], 'Agency report should expose a stable schema version.' );
	znts_assert_same( 'Example Agency', $report['agency']['name'], 'Agency report should preserve white-label agency name.' );
	znts_assert_same( 'Maintenance Report', $report['agency']['report_title'], 'Agency report should preserve white-label report title.' );
	znts_assert_same( 77, $report['snapshot']['id'], 'Agency report should include checkpoint identity.' );
	znts_assert_same( true, $report['update_window']['confirmed'], 'Agency report should include operator confirmation.' );
	znts_assert_same( 'healthy', $report['health']['status'], 'Agency report should preserve Safe Update Window health status.' );
	znts_assert_same( 3, $report['health']['summary']['pass'], 'Agency report should preserve health summary counts.' );
	znts_assert_same( 'pass', $report['storage']['status'], 'Agency report should include storage state.' );
	znts_assert_true( count( $report['boundaries'] ) >= 3, 'Agency report should include stable operational boundaries.' );
}

function znts_test_agency_report_model_renders_exportable_text() {
	$model  = new AgencyReportModel();
	$report = $model->build_report(
		array(
			'id'    => 88,
			'label' => 'Before theme update',
		),
		array(
			'confirmed' => false,
			'health'    => array(
				'status'  => 'degraded',
				'summary' => array(
					'pass'    => 2,
					'warning' => 1,
					'fail'    => 1,
				),
			),
		),
		array(
			'site' => array(
				'url' => 'http://example.test',
			),
			'warnings' => array(
				array(
					'key'     => 'safe_update_window',
					'message' => 'The latest Safe Update Window needs review.',
				),
			),
		),
		array(
			'agency_name' => 'Example Agency',
		)
	);

	$text = $model->render_text( $report );

	znts_assert_true( false !== strpos( $text, 'Safe Update Window Report' ), 'Agency report text should include a readable title.' );
	znts_assert_true( false !== strpos( $text, 'Prepared by: Example Agency' ), 'Agency report text should include white-label agency name.' );
	znts_assert_true( false !== strpos( $text, 'Checkpoint ID: 88' ), 'Agency report text should include checkpoint ID.' );
	znts_assert_true( false !== strpos( $text, 'Health summary: pass 2, warning 1, fail 1' ), 'Agency report text should include health summary counts.' );
	znts_assert_true( false !== strpos( $text, 'The latest Safe Update Window needs review.' ), 'Agency report text should include warnings.' );
	znts_assert_true( false !== strpos( $text, 'Sentinel does not restore database' ), 'Agency report text should include restore boundaries.' );
}
