<?php
/**
 * Focused tests for the Sentinel notice shown on WordPress update screens.
 */

require_once __DIR__ . '/bootstrap.php';

use Zignites\Sentinel\Admin\Admin;

class ZNTS_Testable_Update_Screen_Notice_Admin extends Admin {
	public function __construct() {}

	public function expose_build_update_screen_notice_payload( array $candidates, array $summary, $screen_id, $notice_key = '' ) {
		return $this->build_update_screen_notice_payload( $candidates, $summary, $screen_id, $notice_key );
	}

	public function expose_build_update_screen_snapshot_action_url( $screen_id ) {
		return $this->build_update_screen_snapshot_action_url( $screen_id );
	}

	public function expose_get_allowed_update_screen_url( $screen_id ) {
		return $this->get_allowed_update_screen_url( $screen_id );
	}
}

function znts_test_update_screen_notice_warns_when_plugin_updates_have_no_checkpoint() {
	$admin   = new ZNTS_Testable_Update_Screen_Notice_Admin();
	$payload = $admin->expose_build_update_screen_notice_payload(
		array(
			array(
				'type' => 'plugin',
				'key'  => 'plugin:example/example.php',
			),
			array(
				'type' => 'plugin',
				'key'  => 'plugin:example-two/example-two.php',
			),
		),
		array(),
		'plugins'
	);

	znts_assert_same( 'warning', $payload['type'], 'Update-screen notice should warn when plugin updates exist but no checkpoint is available.' );
	znts_assert_same( 'Create a rollback checkpoint before updating 2 plugins.', $payload['title'], 'Update-screen notice should explain the immediate pre-update action when no checkpoint exists.' );
	znts_assert_same( 'Create Checkpoint Now', $payload['actions'][0]['label'], 'Update-screen notice should offer a one-click checkpoint action when no checkpoint exists.' );
	znts_assert_true( false !== strpos( $payload['actions'][0]['url'], 'action=znts_create_snapshot' ), 'Update-screen notice should link to the snapshot action for fast checkpoint creation.' );
	znts_assert_true( false !== strpos( $payload['actions'][0]['url'], 'znts_return_screen=plugins' ), 'Update-screen notice should keep the operator on the plugin update surface after snapshot capture.' );
	znts_assert_same( 'Open Before Update', $payload['actions'][1]['label'], 'Update-screen notice should still provide a path into Before Update.' );
}

function znts_test_update_screen_notice_uses_latest_checkpoint_when_attention_is_needed() {
	$admin   = new ZNTS_Testable_Update_Screen_Notice_Admin();
	$payload = $admin->expose_build_update_screen_notice_payload(
		array(
			array(
				'type' => 'plugin',
				'key'  => 'plugin:example/example.php',
			),
		),
		array(
			'site_status_card' => array(
				'status'      => 'needs_attention',
				'detail_url'  => 'http://example.test/wp-admin/admin.php?page=zignites-sentinel-update-readiness&snapshot_id=14',
				'activity_url'=> 'http://example.test/wp-admin/admin.php?page=zignites-sentinel-event-logs&snapshot_id=14',
				'latest_snapshot' => array(
					'id'    => 14,
					'label' => 'Checkpoint 14',
				),
			),
		),
		'plugins'
	);

	znts_assert_same( 'warning', $payload['type'], 'Update-screen notice should stay warning-level when the latest checkpoint needs attention.' );
	znts_assert_same( 'Latest checkpoint needs review before updating 1 plugin.', $payload['title'], 'Update-screen notice should frame the attention state around the current update action.' );
	znts_assert_same( 'Review Before Update', $payload['actions'][0]['label'], 'Update-screen notice should send the operator to the current checkpoint workspace when evidence needs review.' );
	znts_assert_same( 'Open History', $payload['actions'][1]['label'], 'Update-screen notice should preserve a quick path to related history.' );
}

function znts_test_update_screen_notice_explains_core_boundary_on_core_only_updates() {
	$admin   = new ZNTS_Testable_Update_Screen_Notice_Admin();
	$payload = $admin->expose_build_update_screen_notice_payload(
		array(
			array(
				'type' => 'core',
				'key'  => 'core:wordpress',
			),
		),
		array(),
		'update-core'
	);

	znts_assert_same( 'info', $payload['type'], 'Update-screen notice should stay informational for core-only updates.' );
	znts_assert_same( 'Sentinel does not restore WordPress core updates.', $payload['title'], 'Update-screen notice should explain the core boundary clearly.' );
	znts_assert_same( 'Open Before Update', $payload['actions'][0]['label'], 'Core boundary notice should still give the operator a way back into Sentinel.' );
}

function znts_test_update_screen_notice_skips_irrelevant_screens() {
	$admin   = new ZNTS_Testable_Update_Screen_Notice_Admin();
	$payload = $admin->expose_build_update_screen_notice_payload(
		array(
			array(
				'type' => 'plugin',
				'key'  => 'plugin:example/example.php',
			),
		),
		array(),
		'themes'
	);

	znts_assert_same( array(), $payload, 'Update-screen notice should not render on the theme screen when only plugin updates are pending.' );
}

function znts_test_update_screen_notice_reports_recent_snapshot_creation() {
	$admin   = new ZNTS_Testable_Update_Screen_Notice_Admin();
	$payload = $admin->expose_build_update_screen_notice_payload(
		array(
			array(
				'type' => 'plugin',
				'key'  => 'plugin:example/example.php',
			),
		),
		array(
			'site_status_card' => array(
				'status'      => 'stable',
				'detail_url'  => 'http://example.test/wp-admin/admin.php?page=zignites-sentinel-update-readiness&snapshot_id=18',
				'activity_url'=> 'http://example.test/wp-admin/admin.php?page=zignites-sentinel-event-logs&snapshot_id=18',
				'latest_snapshot' => array(
					'id'    => 18,
					'label' => 'Checkpoint 18',
				),
			),
		),
		'plugins',
		'snapshot-created'
	);

	znts_assert_same( 'success', $payload['type'], 'Update-screen notice should confirm a newly created checkpoint on return to the update screen.' );
	znts_assert_same( 'Sentinel created a checkpoint before updating 1 plugin.', $payload['title'], 'Update-screen notice should confirm the capture in update-oriented language.' );
}

function znts_test_update_screen_notice_builds_allowed_return_urls() {
	$admin = new ZNTS_Testable_Update_Screen_Notice_Admin();

	znts_assert_same( 'http://example.test/wp-admin/plugins.php', $admin->expose_get_allowed_update_screen_url( 'plugins' ), 'Allowed update screen URLs should map the plugin screen correctly.' );
	znts_assert_same( 'http://example.test/wp-admin/themes.php', $admin->expose_get_allowed_update_screen_url( 'themes' ), 'Allowed update screen URLs should map the theme screen correctly.' );
	znts_assert_same( 'http://example.test/wp-admin/update-core.php', $admin->expose_get_allowed_update_screen_url( 'update-core' ), 'Allowed update screen URLs should map the core update screen correctly.' );
	znts_assert_same( '', $admin->expose_get_allowed_update_screen_url( 'users' ), 'Only supported update screens should be accepted as snapshot return targets.' );
}
