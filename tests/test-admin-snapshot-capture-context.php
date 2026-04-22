<?php
/**
 * Focused tests for contextual snapshot capture from native update screens.
 */

require_once __DIR__ . '/bootstrap.php';

use Zignites\Sentinel\Admin\Admin;

class ZNTS_Fake_Snapshot_Capture_Context_Planner {
	public $candidates = array();

	public function get_candidates() {
		return $this->candidates;
	}
}

class ZNTS_Testable_Snapshot_Capture_Context_Admin extends Admin {
	public function __construct() {
		$this->update_planner = new ZNTS_Fake_Snapshot_Capture_Context_Planner();
	}

	public function set_candidates( array $candidates ) {
		$this->update_planner->candidates = $candidates;
	}

	public function expose_build_snapshot_creation_context_from_request( $return_screen ) {
		return $this->build_snapshot_creation_context_from_request( $return_screen );
	}
}

function znts_test_snapshot_capture_context_targets_single_plugin_from_row_handoff() {
	$admin = new ZNTS_Testable_Snapshot_Capture_Context_Admin();
	$admin->set_candidates(
		array(
			array(
				'key'             => 'plugin:example/example.php',
				'type'            => 'plugin',
				'slug'            => 'example/example.php',
				'label'           => 'Example Plugin',
				'current_version' => '1.0.0',
				'new_version'     => '1.1.0',
			),
			array(
				'key'             => 'plugin:other/other.php',
				'type'            => 'plugin',
				'slug'            => 'other/other.php',
				'label'           => 'Other Plugin',
				'current_version' => '2.0.0',
				'new_version'     => '2.1.0',
			),
		)
	);

	$_REQUEST['znts_update_target'] = 'plugin:example/example.php';
	$context                        = $admin->expose_build_snapshot_creation_context_from_request( 'plugins' );
	unset( $_REQUEST['znts_update_target'] );

	znts_assert_same( 'update_screen', $context['source'], 'Snapshot context should mark native update-screen captures explicitly.' );
	znts_assert_same( 'targeted', $context['capture_mode'], 'Row-level handoff captures should be marked as targeted.' );
	znts_assert_same( 'plugin', $context['scope'], 'Single plugin captures should use the plugin scope.' );
	znts_assert_same( 1, $context['target_count'], 'Row-level handoff captures should keep only the requested plugin target.' );
	znts_assert_same( 'plugin:example/example.php', $context['targets'][0]['key'], 'Row-level handoff captures should preserve the requested plugin target key.' );
	znts_assert_same( 'Example Plugin', $context['targets'][0]['label'], 'Row-level handoff captures should preserve the human-readable target label.' );
}

function znts_test_snapshot_capture_context_uses_plugin_and_theme_targets_on_update_core_screen() {
	$admin = new ZNTS_Testable_Snapshot_Capture_Context_Admin();
	$admin->set_candidates(
		array(
			array(
				'key'             => 'plugin:example/example.php',
				'type'            => 'plugin',
				'slug'            => 'example/example.php',
				'label'           => 'Example Plugin',
				'current_version' => '1.0.0',
				'new_version'     => '1.1.0',
			),
			array(
				'key'             => 'theme:example-theme',
				'type'            => 'theme',
				'slug'            => 'example-theme',
				'label'           => 'Example Theme',
				'current_version' => '3.0.0',
				'new_version'     => '3.1.0',
			),
			array(
				'key'             => 'core:wordpress',
				'type'            => 'core',
				'slug'            => 'wordpress',
				'label'           => 'WordPress Core',
				'current_version' => '6.8.0',
				'new_version'     => '6.8.1',
			),
		)
	);

	$context = $admin->expose_build_snapshot_creation_context_from_request( 'update-core' );

	znts_assert_same( 'screen', $context['capture_mode'], 'Screen-level update captures should be marked as screen-scoped.' );
	znts_assert_same( 'mixed', $context['scope'], 'Update-core captures with plugin and theme targets should use the mixed scope.' );
	znts_assert_same( 2, $context['target_count'], 'Update-core captures should include only plugin and theme targets.' );
	znts_assert_same( 'plugin', $context['targets'][0]['type'], 'Update-core captures should retain plugin targets.' );
	znts_assert_same( 'theme', $context['targets'][1]['type'], 'Update-core captures should retain theme targets.' );
}
