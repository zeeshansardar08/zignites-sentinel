<?php
/**
 * Focused tests for update-aware snapshot labels and metadata.
 */

require_once __DIR__ . '/bootstrap.php';

use Zignites\Sentinel\Snapshots\ComponentManifestBuilder;
use Zignites\Sentinel\Snapshots\SnapshotArtifactRepository;
use Zignites\Sentinel\Snapshots\SnapshotExportManager;
use Zignites\Sentinel\Snapshots\SnapshotManager;
use Zignites\Sentinel\Snapshots\SnapshotPackageManager;
use Zignites\Sentinel\Snapshots\SnapshotRepository;

class ZNTS_Testable_Update_Aware_Snapshot_Manager extends SnapshotManager {
	public function expose_build_manual_snapshot_record_data( array $state, $user_id, array $context = array() ) {
		return $this->build_manual_snapshot_record_data( $state, $user_id, $context );
	}
}

function znts_get_snapshot_manager_test_state() {
	return array(
		'core_version'     => '6.8.0',
		'php_version'      => '8.2.15',
		'theme_stylesheet' => 'example-theme',
		'active_plugins'   => array(
			array(
				'plugin'  => 'example/example.php',
				'name'    => 'Example Plugin',
				'version' => '1.0.0',
			),
		),
		'metadata'         => array(
			'site_url'            => 'http://example.test',
			'is_multisite'        => false,
			'active_plugin_count' => 1,
			'theme_name'          => 'Example Theme',
			'theme_version'       => '3.0.0',
			'component_manifest'  => array(),
		),
	);
}

function znts_make_test_snapshot_manager() {
	return new ZNTS_Testable_Update_Aware_Snapshot_Manager(
		new SnapshotRepository(),
		new SnapshotArtifactRepository(),
		new ComponentManifestBuilder(),
		new SnapshotExportManager(),
		new SnapshotPackageManager()
	);
}

function znts_test_update_aware_snapshot_context_preserves_manual_defaults_without_targets() {
	$manager = znts_make_test_snapshot_manager();
	$record  = $manager->expose_build_manual_snapshot_record_data( znts_get_snapshot_manager_test_state(), 9 );

	znts_assert_true( 0 === strpos( $record['label'], 'Manual snapshot ' ), 'Snapshots without context should keep the generic manual label.' );
	znts_assert_same( 'Metadata snapshot captured before manual review or update activity.', $record['description'], 'Snapshots without context should keep the generic manual description.' );
	znts_assert_same( false, false !== strpos( $record['metadata'], 'capture_context' ), 'Snapshots without context should not inject capture-context metadata.' );
}

function znts_test_update_aware_snapshot_context_builds_targeted_plugin_checkpoint_labels() {
	$manager = znts_make_test_snapshot_manager();
	$record  = $manager->expose_build_manual_snapshot_record_data(
		znts_get_snapshot_manager_test_state(),
		9,
		array(
			'source'       => 'update_screen',
			'return_screen'=> 'plugins',
			'screen_id'    => 'plugins',
			'capture_mode' => 'targeted',
			'scope'        => 'plugin',
			'target_count' => 1,
			'targets'      => array(
				array(
					'key'             => 'plugin:example/example.php',
					'type'            => 'plugin',
					'slug'            => 'example/example.php',
					'label'           => 'Example Plugin',
					'current_version' => '1.0.0',
					'new_version'     => '1.1.0',
				),
			),
		)
	);
	$metadata = json_decode( $record['metadata'], true );

	znts_assert_true( 0 === strpos( $record['label'], 'Pre-update checkpoint: Example Plugin ' ), 'Targeted plugin captures should use the plugin label in the checkpoint title.' );
	znts_assert_same( 'Checkpoint captured from the plugins update screen before updating Example Plugin.', $record['description'], 'Targeted plugin captures should describe the native source screen and target.' );
	znts_assert_same( 'plugin', $metadata['capture_context']['scope'], 'Targeted plugin captures should retain plugin scope metadata.' );
	znts_assert_same( 'plugin:example/example.php', $metadata['capture_context']['targets'][0]['key'], 'Targeted plugin captures should persist the exact target key in metadata.' );
}

function znts_test_update_aware_snapshot_context_builds_mixed_update_window_labels() {
	$manager = znts_make_test_snapshot_manager();
	$record  = $manager->expose_build_manual_snapshot_record_data(
		znts_get_snapshot_manager_test_state(),
		9,
		array(
			'source'       => 'update_screen',
			'return_screen'=> 'update-core',
			'screen_id'    => 'update-core',
			'capture_mode' => 'screen',
			'scope'        => 'mixed',
			'target_count' => 2,
			'targets'      => array(
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
			),
		)
	);
	$metadata = json_decode( $record['metadata'], true );

	znts_assert_true( 0 === strpos( $record['label'], 'Pre-update code checkpoint ' ), 'Mixed captures should use a broader code-window checkpoint label.' );
	znts_assert_same( 'Checkpoint captured from the Updates screen before a mixed plugin and theme update window.', $record['description'], 'Mixed captures should explain the combined update scope clearly.' );
	znts_assert_same( 2, count( $metadata['capture_context']['targets'] ), 'Mixed captures should persist every targeted plugin and theme in metadata.' );
}
