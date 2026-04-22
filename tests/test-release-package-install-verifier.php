<?php
/**
 * Focused tests for release package install verification helpers.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/class-release-package-install-verifier.php';

function znts_test_release_package_install_verifier_builds_plugin_paths_from_wp_root() {
	$plugins_dir = ZNTS_Release_Package_Install_Verifier::build_plugins_directory( 'D:\\laragon\\www\\zee-dev' );
	$plugin_file = ZNTS_Release_Package_Install_Verifier::resolve_plugin_file( 'D:\\laragon\\www\\zee-dev', 'zignites-sentinel-release-smoke/zignites-sentinel.php' );

	znts_assert_same( 'D:/laragon/www/zee-dev/wp-content/plugins', $plugins_dir, 'Release package install verifier should derive the plugins directory from the WordPress root.' );
	znts_assert_same( 'D:/laragon/www/zee-dev/wp-content/plugins/zignites-sentinel-release-smoke/zignites-sentinel.php', $plugin_file, 'Release package install verifier should resolve plugin basenames under the plugins directory.' );
}

function znts_test_release_package_install_verifier_rejects_unsafe_plugin_basenames() {
	znts_assert_same( false, ZNTS_Release_Package_Install_Verifier::is_valid_plugin_basename( '../zignites-sentinel.php' ), 'Release package install verifier should reject parent-directory plugin paths.' );
	znts_assert_same( false, ZNTS_Release_Package_Install_Verifier::is_valid_plugin_basename( 'zignites sentinel/zignites-sentinel.php' ), 'Release package install verifier should reject plugin basenames with spaces.' );
	znts_assert_same( '', ZNTS_Release_Package_Install_Verifier::resolve_plugin_file( 'D:/laragon/www/zee-dev', '../zignites-sentinel.php' ), 'Release package install verifier should refuse to resolve invalid plugin basenames.' );
}

function znts_test_release_package_install_verifier_builds_temp_plugin_basename() {
	$basename = ZNTS_Release_Package_Install_Verifier::build_plugin_basename( 'Zignites Sentinel Release Smoke' );

	znts_assert_same( 'zignites-sentinel-release-smoke/zignites-sentinel.php', $basename, 'Release package install verifier should normalize temporary plugin basenames from slugs.' );
}
