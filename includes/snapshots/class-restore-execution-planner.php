<?php
/**
 * Build a restore execution plan from a staged snapshot package.
 *
 * @package ZignitesSentinel
 */

namespace Zignites\Sentinel\Snapshots;

defined( 'ABSPATH' ) || exit;

class RestoreExecutionPlanner {

	/**
	 * Staging manager.
	 *
	 * @var RestoreStagingManager
	 */
	protected $staging_manager;

	/**
	 * Constructor.
	 *
	 * @param RestoreStagingManager $staging_manager Staging manager.
	 */
	public function __construct( RestoreStagingManager $staging_manager ) {
		$this->staging_manager = $staging_manager;
	}

	/**
	 * Build a restore plan for a snapshot package.
	 *
	 * @param array $snapshot  Snapshot row.
	 * @param array $artifacts Artifact rows.
	 * @return array
	 */
	public function build_plan( array $snapshot, array $artifacts ) {
		$stage = $this->staging_manager->extract_package_to_stage( $snapshot, $artifacts );
		$items = array();
		$checks = array( $stage['stage_check'] );

		if ( isset( $stage['extraction_check'] ) ) {
			$checks[] = $stage['extraction_check'];
		}

		if ( empty( $stage['success'] ) ) {
			return $this->finalize_plan( $snapshot, $items, $checks, '', false );
		}

		$stage_path = $stage['stage_path'];
		$items[]    = $this->build_theme_item( $stage_path, $snapshot );

		if ( ! empty( $snapshot['active_plugins_decoded'] ) && is_array( $snapshot['active_plugins_decoded'] ) ) {
			foreach ( $snapshot['active_plugins_decoded'] as $plugin_state ) {
				if ( empty( $plugin_state['plugin'] ) ) {
					continue;
				}

				$items[] = $this->build_plugin_item( $stage_path, $plugin_state );
			}
		}

		$cleanup_ok = $this->staging_manager->cleanup_stage_directory( $stage_path );
		$checks[]   = array(
			'label'   => __( 'Plan stage cleanup', 'zignites-sentinel' ),
			'status'  => $cleanup_ok ? 'pass' : 'warning',
			'message' => $cleanup_ok
				? __( 'The temporary plan stage directory was removed.', 'zignites-sentinel' )
				: __( 'The temporary plan stage directory could not be fully removed.', 'zignites-sentinel' ),
		);

		return $this->finalize_plan( $snapshot, $items, $checks, $stage_path, $cleanup_ok );
	}

	/**
	 * Build a restore item for the theme payload.
	 *
	 * @param string $stage_path Stage path.
	 * @param array  $snapshot   Snapshot row.
	 * @return array
	 */
	protected function build_theme_item( $stage_path, array $snapshot ) {
		$stylesheet = isset( $snapshot['theme_stylesheet'] ) ? sanitize_text_field( (string) $snapshot['theme_stylesheet'] ) : '';

		if ( ! $this->is_safe_theme_stylesheet( $stylesheet ) ) {
			return array(
				'type'           => 'theme',
				'label'          => __( 'Theme', 'zignites-sentinel' ),
				'package_path'   => '',
				'target_path'    => '',
				'status'         => 'fail',
				'action'         => 'blocked',
				'message'        => __( 'The snapshot theme path is invalid and cannot be restored safely.', 'zignites-sentinel' ),
				'conflict_count' => 0,
				'file_count'     => 0,
			);
		}

		$package_path = 'themes/' . $stylesheet;
		$target_path  = trailingslashit( wp_normalize_path( get_theme_root() ) ) . $stylesheet;

		return $this->build_component_item(
			'theme',
			isset( $snapshot['metadata_decoded']['theme_name'] ) ? sanitize_text_field( (string) $snapshot['metadata_decoded']['theme_name'] ) : $stylesheet,
			$package_path,
			$target_path,
			$stage_path
		);
	}

	/**
	 * Build a restore item for a plugin payload.
	 *
	 * @param string $stage_path   Stage path.
	 * @param array  $plugin_state Plugin state row.
	 * @return array
	 */
	protected function build_plugin_item( $stage_path, array $plugin_state ) {
		$plugin_file = sanitize_text_field( (string) $plugin_state['plugin'] );
		$directory   = dirname( $plugin_file );
		$label       = isset( $plugin_state['name'] ) ? sanitize_text_field( (string) $plugin_state['name'] ) : $plugin_file;

		if ( ! $this->is_safe_plugin_reference( $plugin_file ) ) {
			return array(
				'type'           => 'plugin',
				'label'          => $label,
				'package_path'   => '',
				'target_path'    => '',
				'status'         => 'fail',
				'action'         => 'blocked',
				'message'        => __( 'The snapshot plugin path is invalid and cannot be restored safely.', 'zignites-sentinel' ),
				'conflict_count' => 0,
				'file_count'     => 0,
			);
		}

		if ( '.' !== $directory ) {
			return $this->build_component_item(
				'plugin',
				$label,
				'plugins/' . wp_normalize_path( $directory ),
				trailingslashit( wp_normalize_path( WP_PLUGIN_DIR ) ) . wp_normalize_path( $directory ),
				$stage_path
			);
		}

		return $this->build_component_item(
			'plugin',
			$label,
			'plugins/' . basename( $plugin_file ),
			trailingslashit( wp_normalize_path( WP_PLUGIN_DIR ) ) . basename( $plugin_file ),
			$stage_path
		);
	}

	/**
	 * Build a restore component item.
	 *
	 * @param string $type         Component type.
	 * @param string $label        Component label.
	 * @param string $package_path Package relative path.
	 * @param string $target_path  Live target path.
	 * @param string $stage_path   Stage path.
	 * @return array
	 */
	protected function build_component_item( $type, $label, $package_path, $target_path, $stage_path ) {
		if ( ! $this->is_safe_package_path( $package_path ) || ! $this->is_allowed_target_path( $type, $target_path ) ) {
			return array(
				'type'           => $type,
				'label'          => $label,
				'package_path'   => $package_path,
				'target_path'    => $target_path,
				'status'         => 'fail',
				'action'         => 'blocked',
				'message'        => __( 'The restore target is outside the allowed plugin or theme scope.', 'zignites-sentinel' ),
				'conflict_count' => 0,
				'file_count'     => 0,
			);
		}

		$staged_path = trailingslashit( wp_normalize_path( $stage_path ) ) . ltrim( wp_normalize_path( $package_path ), '/' );

		if ( ! file_exists( $staged_path ) ) {
			return array(
				'type'           => $type,
				'label'          => $label,
				'package_path'   => $package_path,
				'target_path'    => $target_path,
				'status'         => 'fail',
				'action'         => 'blocked',
				'message'        => __( 'The packaged component payload is missing from the staged snapshot.', 'zignites-sentinel' ),
				'conflict_count' => 0,
				'file_count'     => 0,
			);
		}

		$staged_hashes = is_dir( $staged_path )
			? $this->get_directory_hashes( $staged_path )
			: array( basename( $staged_path ) => hash_file( 'sha256', $staged_path ) );

		$file_count = count( $staged_hashes );

		if ( ! file_exists( $target_path ) ) {
			return array(
				'type'           => $type,
				'label'          => $label,
				'package_path'   => $package_path,
				'target_path'    => $target_path,
				'status'         => 'pass',
				'action'         => 'create',
				'message'        => __( 'The component does not exist on the live site and would be created.', 'zignites-sentinel' ),
				'conflict_count' => 0,
				'file_count'     => $file_count,
			);
		}

		$current_hashes = is_dir( $target_path )
			? $this->get_directory_hashes( $target_path )
			: array( basename( $target_path ) => hash_file( 'sha256', $target_path ) );

		$conflicts = $this->count_hash_conflicts( $staged_hashes, $current_hashes );
		$extra     = max( 0, count( $current_hashes ) - count( array_intersect_key( $current_hashes, $staged_hashes ) ) );

		if ( 0 === $conflicts && 0 === $extra && count( $staged_hashes ) === count( $current_hashes ) ) {
			return array(
				'type'           => $type,
				'label'          => $label,
				'package_path'   => $package_path,
				'target_path'    => $target_path,
				'status'         => 'pass',
				'action'         => 'reuse',
				'message'        => __( 'The live component already matches the snapshot payload.', 'zignites-sentinel' ),
				'conflict_count' => 0,
				'file_count'     => $file_count,
			);
		}

		return array(
			'type'           => $type,
			'label'          => $label,
			'package_path'   => $package_path,
			'target_path'    => $target_path,
			'status'         => 'warning',
			'action'         => 'replace',
			'message'        => __( 'The live component differs from the snapshot payload and would be replaced.', 'zignites-sentinel' ),
			'conflict_count' => $conflicts + $extra,
			'file_count'     => $file_count,
		);
	}

	/**
	 * Finalize the restore plan result.
	 *
	 * @param array  $snapshot    Snapshot row.
	 * @param array  $items       Plan items.
	 * @param array  $checks      Plan checks.
	 * @param string $stage_path  Stage path.
	 * @param bool   $cleanup_ok  Cleanup result.
	 * @return array
	 */
	protected function finalize_plan( array $snapshot, array $items, array $checks, $stage_path, $cleanup_ok ) {
		$summary = array(
			'create'   => 0,
			'replace'  => 0,
			'reuse'    => 0,
			'blocked'  => 0,
			'conflicts'=> 0,
		);

		foreach ( $items as $item ) {
			if ( isset( $summary[ $item['action'] ] ) ) {
				++$summary[ $item['action'] ];
			}

			if ( isset( $item['conflict_count'] ) ) {
				$summary['conflicts'] += (int) $item['conflict_count'];
			}
		}

		$status = 'ready';

		foreach ( $checks as $check ) {
			if ( isset( $check['status'] ) && 'fail' === $check['status'] ) {
				$status = 'blocked';
				break;
			}

			if ( isset( $check['status'] ) && 'warning' === $check['status'] && 'blocked' !== $status ) {
				$status = 'caution';
			}
		}

		if ( 'blocked' !== $status ) {
			foreach ( $items as $item ) {
				if ( 'fail' === $item['status'] ) {
					$status = 'blocked';
					break;
				}

				if ( 'warning' === $item['status'] && 'blocked' !== $status ) {
					$status = 'caution';
				}
			}
		}

		return array(
			'generated_at' => current_time( 'mysql', true ),
			'snapshot_id'  => isset( $snapshot['id'] ) ? (int) $snapshot['id'] : 0,
			'status'       => $status,
			'checks'       => $checks,
			'items'        => $items,
			'summary'      => $summary,
			'stage_path'   => $stage_path,
			'cleanup_completed' => $cleanup_ok,
			'note'         => $this->build_note( $status, $summary ),
			'confirmation_phrase' => sprintf( 'RESTORE SNAPSHOT %d', isset( $snapshot['id'] ) ? (int) $snapshot['id'] : 0 ),
		);
	}

	/**
	 * Build a plan note.
	 *
	 * @param string $status  Plan status.
	 * @param array  $summary Plan summary.
	 * @return string
	 */
	protected function build_note( $status, array $summary ) {
		if ( 'blocked' === $status ) {
			return __( 'The restore plan is blocked because the snapshot package could not be staged cleanly.', 'zignites-sentinel' );
		}

		if ( 'caution' === $status ) {
			return sprintf(
				/* translators: %d = conflict count */
				__( 'The restore plan would overwrite %d conflicting live payloads. Explicit confirmation is required before execution.', 'zignites-sentinel' ),
				isset( $summary['conflicts'] ) ? (int) $summary['conflicts'] : 0
			);
		}

		return __( 'The restore plan is ready. The live site already matches or can safely receive the snapshot payloads.', 'zignites-sentinel' );
	}

	/**
	 * Validate a stored theme stylesheet value.
	 *
	 * @param string $stylesheet Theme stylesheet.
	 * @return bool
	 */
	protected function is_safe_theme_stylesheet( $stylesheet ) {
		$stylesheet = trim( wp_normalize_path( (string) $stylesheet ), '/' );

		if ( '' === $stylesheet || false !== strpos( $stylesheet, '/' ) || false !== strpos( $stylesheet, ':' ) ) {
			return false;
		}

		return ! preg_match( '#(^|/)\.\.(/|$)#', $stylesheet );
	}

	/**
	 * Validate a stored plugin basename.
	 *
	 * @param string $plugin_file Plugin basename.
	 * @return bool
	 */
	protected function is_safe_plugin_reference( $plugin_file ) {
		$plugin_file = ltrim( wp_normalize_path( (string) $plugin_file ), '/' );

		if ( '' === $plugin_file || false !== strpos( $plugin_file, ':' ) ) {
			return false;
		}

		return ! preg_match( '#(^|/)\.\.(/|$)#', $plugin_file );
	}

	/**
	 * Validate a package-relative path inside the staged payload.
	 *
	 * @param string $package_path Package-relative path.
	 * @return bool
	 */
	protected function is_safe_package_path( $package_path ) {
		$package_path = ltrim( wp_normalize_path( (string) $package_path ), '/' );

		if ( '' === $package_path || false !== strpos( $package_path, ':' ) ) {
			return false;
		}

		if ( preg_match( '#(^|/)\.\.(/|$)#', $package_path ) ) {
			return false;
		}

		return 0 === strpos( $package_path, 'themes/' ) || 0 === strpos( $package_path, 'plugins/' );
	}

	/**
	 * Ensure a live target path remains inside the theme or plugin roots.
	 *
	 * @param string $type        Component type.
	 * @param string $target_path Live target path.
	 * @return bool
	 */
	protected function is_allowed_target_path( $type, $target_path ) {
		$target_path = wp_normalize_path( (string) $target_path );
		$plugin_root = trailingslashit( wp_normalize_path( WP_PLUGIN_DIR ) );
		$theme_root  = trailingslashit( wp_normalize_path( get_theme_root() ) );

		if ( '' === $target_path ) {
			return false;
		}

		if ( 'theme' === $type ) {
			return 0 === strpos( $target_path, $theme_root );
		}

		return 0 === strpos( $target_path, $plugin_root );
	}

	/**
	 * Get normalized hashes for a directory tree.
	 *
	 * @param string $directory Directory path.
	 * @return array
	 */
	protected function get_directory_hashes( $directory ) {
		$directory = trailingslashit( wp_normalize_path( $directory ) );
		$hashes    = array();
		$iterator  = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $directory, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ( $iterator as $item ) {
			if ( $item->isDir() ) {
				continue;
			}

			$path     = wp_normalize_path( $item->getPathname() );
			$relative = ltrim( substr( $path, strlen( $directory ) ), '/' );
			$hashes[ $relative ] = hash_file( 'sha256', $path );
		}

		ksort( $hashes );

		return $hashes;
	}

	/**
	 * Count differing file hashes between staged and current content.
	 *
	 * @param array $staged_hashes  Staged file hashes.
	 * @param array $current_hashes Current file hashes.
	 * @return int
	 */
	protected function count_hash_conflicts( array $staged_hashes, array $current_hashes ) {
		$conflicts = 0;

		foreach ( $staged_hashes as $relative => $hash ) {
			if ( isset( $current_hashes[ $relative ] ) && $current_hashes[ $relative ] !== $hash ) {
				++$conflicts;
			}
		}

		return $conflicts;
	}
}
