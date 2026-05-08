<?php
/**
 * Disk capacity checks for filesystem-heavy workflows.
 *
 * @package ZignitesSentinel
 */

namespace Zignites\Sentinel\Core;

defined( 'ABSPATH' ) || exit;

class DiskSpacePreflight {

	/**
	 * Baseline overhead for manifests, guards, and filesystem variance.
	 *
	 * @var int
	 */
	const MIN_OVERHEAD_BYTES = 10485760;

	/**
	 * Check capacity for creating a checkpoint export and ZIP package.
	 *
	 * @param array $paths Optional source paths.
	 * @return array
	 */
	public function check_snapshot_capacity( array $paths = array() ) {
		if ( empty( $paths ) ) {
			$paths = $this->get_active_component_paths();
		}

		$source_bytes   = $this->sum_paths( $paths );
		$required_bytes = $this->add_overhead( (int) ceil( $source_bytes * 1.5 ) );

		return $this->check_required_space(
			$this->get_upload_base_dir(),
			$required_bytes,
			'snapshot',
			array(
				'source_bytes' => $source_bytes,
				'path_count'    => count( $paths ),
			)
		);
	}

	/**
	 * Check capacity for staging a snapshot package.
	 *
	 * @param array $artifact   Package artifact.
	 * @param array $inspection Optional package inspection result.
	 * @return array
	 */
	public function check_staging_capacity( array $artifact, array $inspection = array() ) {
		$package_bytes  = $this->get_package_size_bytes( $artifact, $inspection );
		$required_bytes = $this->add_overhead( (int) ceil( $package_bytes * 2 ) );

		return $this->check_required_space(
			$this->get_upload_base_dir(),
			$required_bytes,
			'stage',
			array(
				'package_bytes' => $package_bytes,
			)
		);
	}

	/**
	 * Check capacity before live restore execution.
	 *
	 * @param array $snapshot Snapshot row.
	 * @param array $artifacts Artifact rows.
	 * @param array $items    Restore plan items.
	 * @return array
	 */
	public function check_restore_capacity( array $snapshot, array $artifacts = array(), array $items = array() ) {
		$package_bytes = $this->get_package_size_bytes( $this->find_package_artifact( $artifacts ) );
		$target_bytes  = ! empty( $items ) ? $this->sum_plan_target_sizes( $items ) : $this->sum_paths( $this->get_snapshot_component_paths( $snapshot ) );
		$required      = $this->add_overhead( (int) ceil( ( $package_bytes * 2 ) + $target_bytes ) );

		return $this->check_required_space(
			$this->get_upload_base_dir(),
			$required,
			'restore',
			array(
				'package_bytes' => $package_bytes,
				'target_bytes'  => $target_bytes,
				'item_count'    => count( $items ),
			)
		);
	}

	/**
	 * Check capacity before rollback restores backup payloads to live paths.
	 *
	 * @param array $items Rollback items.
	 * @return array
	 */
	public function check_rollback_capacity( array $items ) {
		$backup_bytes = $this->sum_backup_sizes( $items );
		$required     = $this->add_overhead( $backup_bytes );

		return $this->check_required_space(
			$this->get_upload_base_dir(),
			$required,
			'rollback',
			array(
				'backup_bytes' => $backup_bytes,
				'item_count'   => count( $items ),
			)
		);
	}

	/**
	 * Check available disk space for a required byte count.
	 *
	 * @param string $target_directory Directory expected to receive writes.
	 * @param int    $required_bytes   Required free bytes.
	 * @param string $operation        Operation key.
	 * @param array  $details          Additional detail payload.
	 * @return array
	 */
	public function check_required_space( $target_directory, $required_bytes, $operation, array $details = array() ) {
		$target_directory = $this->resolve_existing_directory( $target_directory );
		$required_bytes   = max( 0, (int) $required_bytes );
		$available_bytes  = '' !== $target_directory ? @disk_free_space( $target_directory ) : false;

		if ( false === $available_bytes ) {
			return array(
				'status'          => 'warning',
				'operation'       => sanitize_key( (string) $operation ),
				'message'         => __( 'Available disk space could not be determined for this operation.', 'zignites-sentinel' ),
				'required_bytes'  => $required_bytes,
				'available_bytes' => null,
				'details'         => $details,
			);
		}

		$available_bytes = (int) $available_bytes;

		if ( $required_bytes > 0 && $available_bytes < $required_bytes ) {
			return array(
				'status'          => 'fail',
				'operation'       => sanitize_key( (string) $operation ),
				'message'         => __( 'Available disk space is below the safe threshold for this Sentinel operation.', 'zignites-sentinel' ),
				'required_bytes'  => $required_bytes,
				'available_bytes' => $available_bytes,
				'details'         => $details,
			);
		}

		return array(
			'status'          => 'pass',
			'operation'       => sanitize_key( (string) $operation ),
			'message'         => __( 'Available disk space meets the safe threshold for this Sentinel operation.', 'zignites-sentinel' ),
			'required_bytes'  => $required_bytes,
			'available_bytes' => $available_bytes,
			'details'         => $details,
		);
	}

	/**
	 * Get active theme/plugin source paths.
	 *
	 * @return array
	 */
	protected function get_active_component_paths() {
		$paths = array();

		if ( function_exists( 'wp_get_theme' ) && function_exists( 'get_theme_root' ) ) {
			$theme = wp_get_theme();

			if ( is_object( $theme ) && method_exists( $theme, 'get_stylesheet' ) ) {
				$stylesheet = sanitize_text_field( (string) $theme->get_stylesheet() );
				$theme_path = trailingslashit( wp_normalize_path( get_theme_root() ) ) . $stylesheet;

				if ( '' !== $stylesheet ) {
					$paths[] = $theme_path;
				}
			}
		}

		$active_plugins = (array) get_option( 'active_plugins', array() );

		foreach ( $active_plugins as $plugin_file ) {
			$plugin_file = ltrim( wp_normalize_path( sanitize_text_field( (string) $plugin_file ) ), '/' );
			$full_path   = trailingslashit( wp_normalize_path( WP_PLUGIN_DIR ) ) . $plugin_file;
			$directory   = dirname( $plugin_file );

			if ( '.' !== $directory && is_dir( trailingslashit( wp_normalize_path( WP_PLUGIN_DIR ) ) . $directory ) ) {
				$paths[] = trailingslashit( wp_normalize_path( WP_PLUGIN_DIR ) ) . $directory;
				continue;
			}

			$paths[] = $full_path;
		}

		return array_values( array_unique( array_filter( $paths ) ) );
	}

	/**
	 * Resolve snapshot component paths from stored snapshot data.
	 *
	 * @param array $snapshot Snapshot row.
	 * @return array
	 */
	protected function get_snapshot_component_paths( array $snapshot ) {
		$paths = array();

		if ( ! empty( $snapshot['theme_stylesheet'] ) && function_exists( 'get_theme_root' ) ) {
			$paths[] = trailingslashit( wp_normalize_path( get_theme_root() ) ) . sanitize_text_field( (string) $snapshot['theme_stylesheet'] );
		}

		$plugins = array();

		if ( ! empty( $snapshot['active_plugins_decoded'] ) && is_array( $snapshot['active_plugins_decoded'] ) ) {
			$plugins = $snapshot['active_plugins_decoded'];
		} elseif ( ! empty( $snapshot['active_plugins'] ) ) {
			$decoded = json_decode( (string) $snapshot['active_plugins'], true );
			$plugins = is_array( $decoded ) ? $decoded : array();
		}

		foreach ( $plugins as $plugin_state ) {
			if ( empty( $plugin_state['plugin'] ) ) {
				continue;
			}

			$plugin_file = ltrim( wp_normalize_path( sanitize_text_field( (string) $plugin_state['plugin'] ) ), '/' );
			$directory   = dirname( $plugin_file );

			if ( '.' !== $directory && is_dir( trailingslashit( wp_normalize_path( WP_PLUGIN_DIR ) ) . $directory ) ) {
				$paths[] = trailingslashit( wp_normalize_path( WP_PLUGIN_DIR ) ) . $directory;
				continue;
			}

			$paths[] = trailingslashit( wp_normalize_path( WP_PLUGIN_DIR ) ) . $plugin_file;
		}

		return array_values( array_unique( array_filter( $paths ) ) );
	}

	/**
	 * Find the package artifact row.
	 *
	 * @param array $artifacts Artifact rows.
	 * @return array
	 */
	protected function find_package_artifact( array $artifacts ) {
		foreach ( $artifacts as $artifact ) {
			if ( ! empty( $artifact['artifact_type'] ) && 'package' === $artifact['artifact_type'] ) {
				return $artifact;
			}
		}

		return array();
	}

	/**
	 * Extract package size from artifact metadata or inspection details.
	 *
	 * @param array $artifact   Package artifact.
	 * @param array $inspection Optional inspection result.
	 * @return int
	 */
	protected function get_package_size_bytes( array $artifact, array $inspection = array() ) {
		if ( ! empty( $inspection['details']['size_bytes'] ) ) {
			return max( 0, (int) $inspection['details']['size_bytes'] );
		}

		if ( ! empty( $inspection['details']['absolute_path'] ) && file_exists( $inspection['details']['absolute_path'] ) ) {
			return max( 0, (int) filesize( $inspection['details']['absolute_path'] ) );
		}

		$metadata = array();

		if ( ! empty( $artifact['metadata'] ) ) {
			$decoded  = json_decode( (string) $artifact['metadata'], true );
			$metadata = is_array( $decoded ) ? $decoded : array();
		}

		if ( ! empty( $metadata['size_bytes'] ) ) {
			return max( 0, (int) $metadata['size_bytes'] );
		}

		return 0;
	}

	/**
	 * Sum target path sizes from restore plan items.
	 *
	 * @param array $items Restore plan items.
	 * @return int
	 */
	protected function sum_plan_target_sizes( array $items ) {
		$paths = array();

		foreach ( $items as $item ) {
			if ( ! empty( $item['target_path'] ) ) {
				$paths[] = $item['target_path'];
			}
		}

		return $this->sum_paths( $paths );
	}

	/**
	 * Sum backup payload sizes from rollback items.
	 *
	 * @param array $items Rollback items.
	 * @return int
	 */
	protected function sum_backup_sizes( array $items ) {
		$paths = array();

		foreach ( $items as $item ) {
			if ( ! empty( $item['backup_path'] ) ) {
				$paths[] = $item['backup_path'];
			}
		}

		return $this->sum_paths( $paths );
	}

	/**
	 * Sum file and directory sizes.
	 *
	 * @param array $paths Paths.
	 * @return int
	 */
	protected function sum_paths( array $paths ) {
		$total = 0;

		foreach ( array_unique( array_filter( $paths ) ) as $path ) {
			$total += $this->get_path_size( $path );
		}

		return $total;
	}

	/**
	 * Get size of a file or directory tree.
	 *
	 * @param string $path Path.
	 * @return int
	 */
	protected function get_path_size( $path ) {
		$path = wp_normalize_path( (string) $path );

		if ( '' === $path || ! file_exists( $path ) ) {
			return 0;
		}

		if ( is_file( $path ) ) {
			return max( 0, (int) filesize( $path ) );
		}

		if ( ! is_dir( $path ) ) {
			return 0;
		}

		$total = 0;

		try {
			$iterator = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator( $path, \FilesystemIterator::SKIP_DOTS ),
				\RecursiveIteratorIterator::SELF_FIRST
			);

			foreach ( $iterator as $item ) {
				if ( $item->isLink() || ! $item->isFile() ) {
					continue;
				}

				$total += max( 0, (int) $item->getSize() );
			}
		} catch ( \Exception $exception ) {
			return 0;
		}

		return $total;
	}

	/**
	 * Add a fixed safety overhead to a byte estimate.
	 *
	 * @param int $bytes Base bytes.
	 * @return int
	 */
	protected function add_overhead( $bytes ) {
		return max( self::MIN_OVERHEAD_BYTES, (int) $bytes + self::MIN_OVERHEAD_BYTES );
	}

	/**
	 * Get uploads base directory.
	 *
	 * @return string
	 */
	protected function get_upload_base_dir() {
		$uploads = wp_upload_dir();

		if ( ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) ) {
			return '';
		}

		return wp_normalize_path( $uploads['basedir'] );
	}

	/**
	 * Resolve an existing directory for disk_free_space().
	 *
	 * @param string $directory Directory path.
	 * @return string
	 */
	protected function resolve_existing_directory( $directory ) {
		$directory = wp_normalize_path( (string) $directory );

		while ( '' !== $directory && ! is_dir( $directory ) ) {
			$parent = dirname( $directory );

			if ( $parent === $directory ) {
				return '';
			}

			$directory = $parent;
		}

		return $directory;
	}
}
