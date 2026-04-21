<?php
/**
 * Focused tests for row-level Sentinel handoff cues on update lists.
 */

require_once __DIR__ . '/bootstrap.php';

use Zignites\Sentinel\Admin\Admin;

class ZNTS_Fake_Update_Row_Handoff_Planner {
	public $candidates = array();

	public function get_candidates() {
		return $this->candidates;
	}
}

class ZNTS_Testable_Update_Row_Handoff_Admin extends Admin {
	public $fixture = array();

	public function __construct() {
		$this->update_planner = new ZNTS_Fake_Update_Row_Handoff_Planner();
	}

	public function expose_filter_plugin_update_handoff_meta( array $plugin_meta, $plugin_file, array $plugin_data = array(), $status = 'all' ) {
		return $this->filter_plugin_update_handoff_meta( $plugin_meta, $plugin_file, $plugin_data, $status );
	}

	public function expose_filter_theme_update_handoff_links( array $actions, $theme, $context = 'all' ) {
		return $this->filter_theme_update_handoff_links( $actions, $theme, $context );
	}

	public function set_candidates( array $candidates ) {
		$this->update_planner->candidates = $candidates;
	}

	protected function get_update_screen_row_handoff_screen() {
		return isset( $this->fixture['screen_id'] ) ? (string) $this->fixture['screen_id'] : '';
	}

	protected function get_dashboard_summary_payload( $snapshot_limit = 1 ) {
		return isset( $this->fixture['summary'] ) && is_array( $this->fixture['summary'] ) ? $this->fixture['summary'] : array();
	}
}

class ZNTS_Fake_Update_Row_Handoff_Theme {
	protected $stylesheet;

	public function __construct( $stylesheet ) {
		$this->stylesheet = (string) $stylesheet;
	}

	public function get_stylesheet() {
		return $this->stylesheet;
	}
}

function znts_test_plugin_update_row_handoff_adds_checkpoint_action_when_no_snapshot_exists() {
	$admin = new ZNTS_Testable_Update_Row_Handoff_Admin();
	$admin->fixture['screen_id'] = 'plugins';
	$admin->set_candidates(
		array(
			array(
				'type' => 'plugin',
				'slug' => 'example/example.php',
			),
		)
	);

	$meta = $admin->expose_filter_plugin_update_handoff_meta( array(), 'example/example.php' );

	znts_assert_same( 1, count( $meta ), 'Plugin row handoff should append a single Sentinel cue for updateable plugins.' );
	znts_assert_true( false !== strpos( $meta[0], 'Sentinel: create checkpoint now' ), 'Plugin row handoff should offer checkpoint creation when no latest snapshot exists.' );
	znts_assert_true( false !== strpos( $meta[0], 'znts_return_screen=plugins' ), 'Plugin row handoff should preserve the plugin update screen as the checkpoint return target.' );
}

function znts_test_plugin_update_row_handoff_uses_review_label_when_checkpoint_needs_attention() {
	$admin = new ZNTS_Testable_Update_Row_Handoff_Admin();
	$admin->fixture['screen_id'] = 'plugins';
	$admin->fixture['summary']   = array(
		'site_status_card' => array(
			'status'         => 'needs_attention',
			'detail_url'     => 'http://example.test/wp-admin/admin.php?page=zignites-sentinel-update-readiness&snapshot_id=21',
			'latest_snapshot'=> array(
				'id' => 21,
			),
		),
	);
	$admin->set_candidates(
		array(
			array(
				'type' => 'plugin',
				'slug' => 'example/example.php',
			),
		)
	);

	$meta = $admin->expose_filter_plugin_update_handoff_meta( array(), 'example/example.php' );

	znts_assert_true( false !== strpos( $meta[0], 'Sentinel: review checkpoint' ), 'Plugin row handoff should point to review when the latest checkpoint needs attention.' );
	znts_assert_true( false !== strpos( $meta[0], 'snapshot_id=21' ), 'Plugin row handoff should link to the latest checkpoint workspace when review is needed.' );
}

function znts_test_plugin_update_row_handoff_skips_plugins_without_pending_updates() {
	$admin = new ZNTS_Testable_Update_Row_Handoff_Admin();
	$admin->fixture['screen_id'] = 'plugins';
	$admin->set_candidates( array() );

	$meta = $admin->expose_filter_plugin_update_handoff_meta( array( 'Existing' ), 'example/example.php' );

	znts_assert_same( array( 'Existing' ), $meta, 'Plugin row handoff should leave unrelated plugin rows untouched.' );
}

function znts_test_theme_update_row_handoff_adds_checkpoint_link_to_theme_actions() {
	$admin = new ZNTS_Testable_Update_Row_Handoff_Admin();
	$admin->fixture['screen_id'] = 'themes';
	$admin->fixture['summary']   = array(
		'site_status_card' => array(
			'status'         => 'stable',
			'detail_url'     => 'http://example.test/wp-admin/admin.php?page=zignites-sentinel-update-readiness&snapshot_id=34',
			'latest_snapshot'=> array(
				'id' => 34,
			),
		),
	);
	$admin->set_candidates(
		array(
			array(
				'type' => 'theme',
				'slug' => 'example-theme',
			),
		)
	);

	$actions = $admin->expose_filter_theme_update_handoff_links( array(), new ZNTS_Fake_Update_Row_Handoff_Theme( 'example-theme' ) );

	znts_assert_true( isset( $actions['znts_sentinel'] ), 'Theme row handoff should add a Sentinel action for updateable themes.' );
	znts_assert_true( false !== strpos( $actions['znts_sentinel'], 'Sentinel: checkpoint ready' ), 'Theme row handoff should show the ready label when the latest checkpoint is stable.' );
}
