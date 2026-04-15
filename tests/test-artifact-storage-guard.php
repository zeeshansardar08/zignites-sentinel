<?php
/**
 * Focused tests for artifact storage directory guards.
 */

require_once __DIR__ . '/../includes/snapshots/class-artifact-storage-guard.php';

use Zignites\Sentinel\Snapshots\ArtifactStorageGuard;

function znts_test_artifact_storage_guard_writes_protection_files() {
	$root = sys_get_temp_dir() . '/znts-artifact-guard-' . uniqid();
	$dir  = $root . '/packages';

	$guard = new class( $root ) extends ArtifactStorageGuard {
		private $root;

		public function __construct( $root ) {
			$this->root = str_replace( '\\', '/', (string) $root );
		}

		public function get_storage_root() {
			return $this->root;
		}
	};

	znts_assert_true( $guard->protect_directory( $dir ), 'Artifact storage guard should create and protect the requested directory.' );
	znts_assert_true( file_exists( $root . '/index.php' ), 'Artifact storage guard should write an index.php guard at the storage root.' );
	znts_assert_true( file_exists( $root . '/.htaccess' ), 'Artifact storage guard should write an .htaccess guard at the storage root.' );
	znts_assert_true( file_exists( $root . '/web.config' ), 'Artifact storage guard should write a web.config guard at the storage root.' );
	znts_assert_true( file_exists( $dir . '/index.php' ), 'Artifact storage guard should write an index.php guard in the requested directory.' );
	znts_assert_true( file_exists( $dir . '/.htaccess' ), 'Artifact storage guard should write an .htaccess guard in the requested directory.' );
	znts_assert_true( file_exists( $dir . '/web.config' ), 'Artifact storage guard should write a web.config guard in the requested directory.' );

	$items = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST
	);

	foreach ( $items as $item ) {
		if ( $item->isDir() ) {
			rmdir( $item->getPathname() );
			continue;
		}

		unlink( $item->getPathname() );
	}

	rmdir( $root );
}

function znts_test_artifact_storage_guard_rejects_unsafe_storage_paths() {
	$guard = new ArtifactStorageGuard();

	znts_assert_same(
		'D:/uploads/zignites-sentinel/packages/snapshot-5.zip',
		$guard->resolve_storage_path( 'zignites-sentinel/packages/snapshot-5.zip', 'zignites-sentinel/packages' ),
		'Artifact storage guard should resolve valid package paths inside the expected storage prefix.'
	);
	znts_assert_same(
		'',
		$guard->resolve_storage_path( '../wp-config.php', 'zignites-sentinel/packages' ),
		'Artifact storage guard should reject traversal paths.'
	);
	znts_assert_same(
		'',
		$guard->resolve_storage_path( 'zignites-sentinel/packages/../../secrets.txt', 'zignites-sentinel/packages' ),
		'Artifact storage guard should reject nested traversal paths.'
	);
	znts_assert_same(
		'',
		$guard->resolve_storage_path( 'zignites-sentinel/snapshots/snapshot-5.json', 'zignites-sentinel/packages' ),
		'Artifact storage guard should reject paths outside the expected storage prefix.'
	);
}
