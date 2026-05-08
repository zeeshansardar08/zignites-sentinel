<?php
/**
 * Focused tests for artifact exposure checks.
 */

require_once __DIR__ . '/bootstrap.php';

use Zignites\Sentinel\Snapshots\ArtifactExposureScanner;
use Zignites\Sentinel\Snapshots\ArtifactStorageGuard;
use Zignites\Sentinel\Snapshots\LocalArtifactStorageBackend;
use Zignites\Sentinel\Snapshots\SnapshotPackageManager;

function znts_test_artifact_exposure_scanner_passes_when_probe_is_blocked() {
	$root = znts_create_temp_upload_root();
	$GLOBALS['znts_test_upload_dir'] = array(
		'basedir' => $root,
		'baseurl' => 'http://example.test/uploads',
	);
	$GLOBALS['znts_test_http_response'] = array(
		'response' => array(
			'code' => 403,
		),
		'body'     => '',
	);

	$guard   = new ArtifactStorageGuard();
	$backend = new LocalArtifactStorageBackend( $guard );
	$scanner = new ArtifactExposureScanner( $backend, $guard );
	$result  = $scanner->scan();

	znts_assert_same( 'pass', $result['status'], 'Artifact exposure scanner should pass when the probe is blocked.' );
	znts_assert_same( 'pass', $result['guard_check']['status'], 'Artifact exposure scanner should verify guard files.' );
	znts_assert_true( file_exists( trailingslashit( $root ) . SnapshotPackageManager::PACKAGE_DIRECTORY . '/.htaccess' ), 'Artifact exposure scanner should ensure package guard files exist.' );

	znts_remove_directory_recursive( $root );
	unset( $GLOBALS['znts_test_upload_dir'], $GLOBALS['znts_test_http_response'] );
}

function znts_test_artifact_exposure_scanner_fails_when_probe_token_is_public() {
	$root = znts_create_temp_upload_root();
	$GLOBALS['znts_test_upload_dir'] = array(
		'basedir' => $root,
		'baseurl' => 'http://example.test/uploads',
	);
	$GLOBALS['znts_test_http_response'] = array(
		'response' => array(
			'code' => 200,
		),
		'body'     => 'znts-probe-a1b2c3d4a1b2c3d4a1b2c3d4',
	);

	$guard   = new ArtifactStorageGuard();
	$backend = new LocalArtifactStorageBackend( $guard );
	$scanner = new ArtifactExposureScanner( $backend, $guard );
	$result  = $scanner->scan();

	znts_assert_same( 'fail', $result['status'], 'Artifact exposure scanner should fail when the probe token is publicly readable.' );
	znts_assert_same( 'fail', $result['probe']['status'], 'Artifact exposure scanner should expose the failed probe state.' );
	znts_assert_true( false !== strpos( $result['warning'], 'plugin/theme source code' ), 'Artifact exposure scanner should warn that artifacts can contain sensitive source code.' );

	znts_remove_directory_recursive( $root );
	unset( $GLOBALS['znts_test_upload_dir'], $GLOBALS['znts_test_http_response'] );
}

function znts_create_temp_upload_root() {
	$root = sys_get_temp_dir() . '/znts-exposure-' . uniqid();
	wp_mkdir_p( $root );

	return str_replace( '\\', '/', $root );
}

function znts_remove_directory_recursive( $path ) {
	if ( ! is_dir( $path ) ) {
		return;
	}

	$items = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $path, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST
	);

	foreach ( $items as $item ) {
		if ( $item->isDir() ) {
			rmdir( $item->getPathname() );
			continue;
		}

		unlink( $item->getPathname() );
	}

	rmdir( $path );
}
