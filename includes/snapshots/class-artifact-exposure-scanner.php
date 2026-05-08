<?php
/**
 * Checks whether uploads-backed Sentinel artifacts appear publicly reachable.
 *
 * @package ZignitesSentinel
 */

namespace Zignites\Sentinel\Snapshots;

defined( 'ABSPATH' ) || exit;

class ArtifactExposureScanner {

	/**
	 * Probe file relative path.
	 *
	 * @var string
	 */
	const PROBE_PATH = 'zignites-sentinel/znts-exposure-check.txt';

	/**
	 * Artifact storage backend.
	 *
	 * @var ArtifactStorageBackend
	 */
	protected $storage_backend;

	/**
	 * Artifact storage guard.
	 *
	 * @var ArtifactStorageGuard
	 */
	protected $storage_guard;

	/**
	 * Constructor.
	 *
	 * @param ArtifactStorageBackend|null $storage_backend Artifact storage backend.
	 * @param ArtifactStorageGuard|null   $storage_guard   Artifact storage guard.
	 */
	public function __construct( ArtifactStorageBackend $storage_backend = null, ArtifactStorageGuard $storage_guard = null ) {
		$this->storage_guard   = $storage_guard ? $storage_guard : new ArtifactStorageGuard();
		$this->storage_backend = $storage_backend ? $storage_backend : new LocalArtifactStorageBackend( $this->storage_guard );
	}

	/**
	 * Run the artifact exposure scan.
	 *
	 * @return array
	 */
	public function scan() {
		$directories = $this->ensure_guarded_directories();
		$guard_check = $this->summarize_guard_files( $directories );

		if ( $this->storage_backend->is_remote() ) {
			return $this->build_result(
				'warning',
				__( 'Artifact storage uses a non-local backend. Verify bucket, CDN, and object permissions outside WordPress.', 'zignites-sentinel' ),
				$guard_check,
				array(
					'backend' => $this->storage_backend->get_backend_key(),
				)
			);
		}

		$probe = $this->run_http_probe();
		$status = $probe['status'];

		if ( 'pass' === $guard_check['status'] && 'pass' === $probe['status'] ) {
			$status = 'pass';
		} elseif ( 'fail' === $guard_check['status'] || 'fail' === $probe['status'] ) {
			$status = 'fail';
		} else {
			$status = 'warning';
		}

		$message = 'pass' === $status
			? __( 'Sentinel artifact directories are guarded and the public probe was not readable.', 'zignites-sentinel' )
			: ( 'fail' === $status
				? __( 'Sentinel artifact storage appears publicly readable. Add server-level deny rules or move artifacts outside public uploads before relying on these packages.', 'zignites-sentinel' )
				: __( 'Sentinel could not fully verify artifact exposure. Review host, CDN, Nginx, and object-storage rules before treating artifacts as private.', 'zignites-sentinel' ) );

		return $this->build_result( $status, $message, $guard_check, $probe );
	}

	/**
	 * Ensure known artifact directories exist with guard files.
	 *
	 * @return array
	 */
	protected function ensure_guarded_directories() {
		$relative_directories = array(
			ArtifactStorageGuard::STORAGE_ROOT,
			SnapshotExportManager::EXPORT_DIRECTORY,
			SnapshotPackageManager::PACKAGE_DIRECTORY,
			RestoreStagingManager::STAGING_DIRECTORY,
			RestoreExecutor::BACKUP_DIRECTORY,
		);
		$directories = array();

		foreach ( $relative_directories as $relative_directory ) {
			$absolute_directory = $this->storage_backend->ensure_directory( $relative_directory );

			$directories[] = array(
				'relative_path' => $relative_directory,
				'absolute_path' => $absolute_directory,
				'created'       => '' !== $absolute_directory,
			);
		}

		return $directories;
	}

	/**
	 * Summarize guard file presence.
	 *
	 * @param array $directories Directory rows.
	 * @return array
	 */
	protected function summarize_guard_files( array $directories ) {
		$missing = array();
		$rows    = array();

		foreach ( $directories as $directory ) {
			$status = $this->storage_guard->get_guard_file_status( isset( $directory['absolute_path'] ) ? $directory['absolute_path'] : '' );

			$row = array(
				'relative_path' => isset( $directory['relative_path'] ) ? (string) $directory['relative_path'] : '',
				'absolute_path' => isset( $directory['absolute_path'] ) ? (string) $directory['absolute_path'] : '',
				'guard_files'   => $status,
			);
			$rows[] = $row;

			foreach ( $status as $file => $present ) {
				if ( ! $present ) {
					$missing[] = $row['relative_path'] . ':' . $file;
				}
			}
		}

		return array(
			'status'  => empty( $missing ) ? 'pass' : 'fail',
			'message' => empty( $missing )
				? __( 'Standard guard files exist in Sentinel artifact directories.', 'zignites-sentinel' )
				: __( 'One or more Sentinel artifact directories are missing standard guard files.', 'zignites-sentinel' ),
			'rows'    => $rows,
			'missing' => $missing,
		);
	}

	/**
	 * Probe whether a temporary token file is publicly readable.
	 *
	 * @return array
	 */
	protected function run_http_probe() {
		$probe_path = $this->storage_backend->resolve_path( self::PROBE_PATH, ArtifactStorageGuard::STORAGE_ROOT );
		$probe_url  = $this->storage_backend->build_url( self::PROBE_PATH );
		$token      = 'znts-probe-' . wp_generate_password( 24, false, false );

		if ( '' === $probe_path || '' === $probe_url ) {
			return array(
				'status'  => 'warning',
				'message' => __( 'Sentinel could not build an artifact exposure probe path or URL.', 'zignites-sentinel' ),
				'url'     => $probe_url,
			);
		}

		if ( false === file_put_contents( $probe_path, $token ) ) {
			return array(
				'status'  => 'warning',
				'message' => __( 'Sentinel could not write the temporary artifact exposure probe file.', 'zignites-sentinel' ),
				'url'     => $probe_url,
			);
		}

		try {
			$response = function_exists( 'wp_remote_get' )
				? wp_remote_get(
					$probe_url,
					array(
						'timeout'     => 5,
						'redirection' => 0,
						'user-agent'  => 'ZignitesSentinel/' . ( defined( 'ZNTS_VERSION' ) ? ZNTS_VERSION : 'unknown' ),
					)
				)
				: null;
		} finally {
			wp_delete_file( $probe_path );
		}

		if ( function_exists( 'is_wp_error' ) && is_wp_error( $response ) ) {
			return array(
				'status'  => 'warning',
				'message' => __( 'The artifact exposure probe could not complete an HTTP request.', 'zignites-sentinel' ),
				'url'     => $probe_url,
			);
		}

		$code = function_exists( 'wp_remote_retrieve_response_code' ) ? (int) wp_remote_retrieve_response_code( $response ) : 0;
		$body = function_exists( 'wp_remote_retrieve_body' ) ? (string) wp_remote_retrieve_body( $response ) : '';

		if ( 200 === $code && false !== strpos( $body, $token ) ) {
			return array(
				'status'        => 'fail',
				'message'       => __( 'The temporary artifact probe was publicly readable.', 'zignites-sentinel' ),
				'url'           => $probe_url,
				'response_code' => $code,
			);
		}

		if ( in_array( $code, array( 401, 403, 404 ), true ) ) {
			return array(
				'status'        => 'pass',
				'message'       => __( 'The temporary artifact probe was blocked or hidden from public HTTP access.', 'zignites-sentinel' ),
				'url'           => $probe_url,
				'response_code' => $code,
			);
		}

		return array(
			'status'        => 'warning',
			'message'       => __( 'The artifact exposure probe returned an inconclusive HTTP response.', 'zignites-sentinel' ),
			'url'           => $probe_url,
			'response_code' => $code,
		);
	}

	/**
	 * Build a normalized scan result.
	 *
	 * @param string $status      Status key.
	 * @param string $message     Message.
	 * @param array  $guard_check Guard file check.
	 * @param array  $probe       Probe result.
	 * @return array
	 */
	protected function build_result( $status, $message, array $guard_check, array $probe ) {
		return array(
			'status'      => sanitize_key( (string) $status ),
			'label'       => $this->status_label( $status ),
			'badge'       => $this->status_badge( $status ),
			'message'     => (string) $message,
			'backend'     => $this->storage_backend->get_backend_key(),
			'guard_check' => $guard_check,
			'probe'       => $probe,
			'warning'     => __( 'Snapshot packages and restore artifacts can contain plugin/theme source code and secrets. Server-level deny rules are stronger than WordPress-only checks.', 'zignites-sentinel' ),
		);
	}

	/**
	 * Human status label.
	 *
	 * @param string $status Status key.
	 * @return string
	 */
	protected function status_label( $status ) {
		if ( 'pass' === $status ) {
			return __( 'Guarded', 'zignites-sentinel' );
		}

		if ( 'fail' === $status ) {
			return __( 'Public exposure risk', 'zignites-sentinel' );
		}

		return __( 'Needs host review', 'zignites-sentinel' );
	}

	/**
	 * Dashboard badge key.
	 *
	 * @param string $status Status key.
	 * @return string
	 */
	protected function status_badge( $status ) {
		if ( 'pass' === $status ) {
			return 'info';
		}

		if ( 'fail' === $status ) {
			return 'critical';
		}

		return 'warning';
	}
}
