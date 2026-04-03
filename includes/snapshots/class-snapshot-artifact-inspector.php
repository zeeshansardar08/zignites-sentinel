<?php
/**
 * Compare stored rollback artifacts against current site state.
 *
 * @package ZignitesSentinel
 */

namespace Zignites\Sentinel\Snapshots;

defined( 'ABSPATH' ) || exit;

class SnapshotArtifactInspector {

	/**
	 * Export manager.
	 *
	 * @var SnapshotExportManager
	 */
	protected $export_manager;

	/**
	 * Package manager.
	 *
	 * @var SnapshotPackageManager|null
	 */
	protected $package_manager;

	/**
	 * Constructor.
	 *
	 * @param SnapshotExportManager $export_manager Export manager.
	 */
	public function __construct( SnapshotExportManager $export_manager, SnapshotPackageManager $package_manager = null ) {
		$this->export_manager  = $export_manager;
		$this->package_manager = $package_manager;
	}

	/**
	 * Inspect stored artifact rows.
	 *
	 * @param array $artifacts Artifact rows.
	 * @return array
	 */
	public function inspect( array $artifacts ) {
		$items = array();

		foreach ( $artifacts as $artifact ) {
			$items[] = $this->inspect_artifact( $artifact );
		}

		$summary = array(
			'pass'    => 0,
			'warning' => 0,
			'fail'    => 0,
		);

		foreach ( $items as $item ) {
			if ( isset( $summary[ $item['status'] ] ) ) {
				++$summary[ $item['status'] ];
			}
		}

		$status = 'pass';

		if ( ! empty( $summary['fail'] ) ) {
			$status = 'fail';
		} elseif ( ! empty( $summary['warning'] ) ) {
			$status = 'warning';
		}

		return array(
			'status'  => $status,
			'summary' => $summary,
			'items'   => $items,
			'message' => $this->build_message( $status ),
		);
	}

	/**
	 * Inspect an individual artifact row.
	 *
	 * @param array $artifact Artifact row.
	 * @return array
	 */
	protected function inspect_artifact( array $artifact ) {
		$type = isset( $artifact['artifact_type'] ) ? sanitize_key( $artifact['artifact_type'] ) : '';

		if ( 'export' === $type ) {
			return $this->inspect_export_artifact( $artifact );
		}

		if ( 'package' === $type ) {
			return $this->inspect_package_artifact( $artifact );
		}

		if ( 'theme' === $type ) {
			return $this->inspect_theme_artifact( $artifact );
		}

		if ( 'plugin' === $type ) {
			return $this->inspect_plugin_artifact( $artifact );
		}

		return array(
			'type'            => $type,
			'label'           => isset( $artifact['label'] ) ? sanitize_text_field( (string) $artifact['label'] ) : '',
			'stored_version'  => isset( $artifact['version'] ) ? sanitize_text_field( (string) $artifact['version'] ) : '',
			'current_version' => '',
			'status'          => 'warning',
			'message'         => __( 'Unknown artifact type.', 'zignites-sentinel' ),
		);
	}

	/**
	 * Inspect a stored export artifact.
	 *
	 * @param array $artifact Artifact row.
	 * @return array
	 */
	protected function inspect_export_artifact( array $artifact ) {
		$inspection = $this->export_manager->inspect_export_artifact( $artifact );

		return array(
			'type'            => 'export',
			'label'           => isset( $artifact['label'] ) ? sanitize_text_field( (string) $artifact['label'] ) : '',
			'stored_version'  => '',
			'current_version' => '',
			'status'          => isset( $inspection['status'] ) ? $inspection['status'] : 'warning',
			'message'         => isset( $inspection['message'] ) ? $inspection['message'] : '',
			'details'         => isset( $inspection['details'] ) ? $inspection['details'] : array(),
		);
	}

	/**
	 * Inspect a stored package artifact.
	 *
	 * @param array $artifact Artifact row.
	 * @return array
	 */
	protected function inspect_package_artifact( array $artifact ) {
		$inspection = $this->package_manager
			? $this->package_manager->inspect_package_artifact( $artifact )
			: array(
				'status'  => 'warning',
				'message' => __( 'No package manager is available for package inspection.', 'zignites-sentinel' ),
				'details' => array(),
			);

		return array(
			'type'            => 'package',
			'label'           => isset( $artifact['label'] ) ? sanitize_text_field( (string) $artifact['label'] ) : '',
			'stored_version'  => '',
			'current_version' => '',
			'status'          => isset( $inspection['status'] ) ? $inspection['status'] : 'warning',
			'message'         => isset( $inspection['message'] ) ? $inspection['message'] : '',
			'details'         => isset( $inspection['details'] ) ? $inspection['details'] : array(),
		);
	}

	/**
	 * Inspect a stored theme artifact.
	 *
	 * @param array $artifact Artifact row.
	 * @return array
	 */
	protected function inspect_theme_artifact( array $artifact ) {
		$stylesheet      = isset( $artifact['artifact_key'] ) ? sanitize_text_field( (string) $artifact['artifact_key'] ) : '';
		$stored_version  = isset( $artifact['version'] ) ? sanitize_text_field( (string) $artifact['version'] ) : '';
		$theme           = wp_get_theme( $stylesheet );
		$current_version = $theme->exists() ? sanitize_text_field( (string) $theme->get( 'Version' ) ) : '';

		if ( ! $theme->exists() ) {
			return array(
				'type'            => 'theme',
				'label'           => isset( $artifact['label'] ) ? sanitize_text_field( (string) $artifact['label'] ) : $stylesheet,
				'stored_version'  => $stored_version,
				'current_version' => '',
				'status'          => 'fail',
				'message'         => __( 'The stored theme artifact is no longer available.', 'zignites-sentinel' ),
			);
		}

		if ( '' !== $stored_version && $stored_version !== $current_version ) {
			return array(
				'type'            => 'theme',
				'label'           => isset( $artifact['label'] ) ? sanitize_text_field( (string) $artifact['label'] ) : $stylesheet,
				'stored_version'  => $stored_version,
				'current_version' => $current_version,
				'status'          => 'warning',
				'message'         => __( 'The stored theme artifact version differs from the current theme version.', 'zignites-sentinel' ),
			);
		}

		return array(
			'type'            => 'theme',
			'label'           => isset( $artifact['label'] ) ? sanitize_text_field( (string) $artifact['label'] ) : $stylesheet,
			'stored_version'  => $stored_version,
			'current_version' => $current_version,
			'status'          => 'pass',
			'message'         => __( 'The stored theme artifact matches the current installed theme.', 'zignites-sentinel' ),
		);
	}

	/**
	 * Inspect a stored plugin artifact.
	 *
	 * @param array $artifact Artifact row.
	 * @return array
	 */
	protected function inspect_plugin_artifact( array $artifact ) {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugin_file      = isset( $artifact['artifact_key'] ) ? sanitize_text_field( (string) $artifact['artifact_key'] ) : '';
		$stored_version   = isset( $artifact['version'] ) ? sanitize_text_field( (string) $artifact['version'] ) : '';
		$current_plugins  = get_plugins();
		$current_version  = isset( $current_plugins[ $plugin_file ]['Version'] ) ? sanitize_text_field( (string) $current_plugins[ $plugin_file ]['Version'] ) : '';
		$plugin_full_path = trailingslashit( wp_normalize_path( WP_PLUGIN_DIR ) ) . ltrim( wp_normalize_path( $plugin_file ), '/' );

		if ( empty( $plugin_file ) || ! file_exists( $plugin_full_path ) ) {
			return array(
				'type'            => 'plugin',
				'label'           => isset( $artifact['label'] ) ? sanitize_text_field( (string) $artifact['label'] ) : $plugin_file,
				'stored_version'  => $stored_version,
				'current_version' => '',
				'status'          => 'fail',
				'message'         => __( 'The stored plugin artifact is no longer available.', 'zignites-sentinel' ),
			);
		}

		if ( '' !== $stored_version && '' !== $current_version && $stored_version !== $current_version ) {
			return array(
				'type'            => 'plugin',
				'label'           => isset( $artifact['label'] ) ? sanitize_text_field( (string) $artifact['label'] ) : $plugin_file,
				'stored_version'  => $stored_version,
				'current_version' => $current_version,
				'status'          => 'warning',
				'message'         => __( 'The stored plugin artifact version differs from the current plugin version.', 'zignites-sentinel' ),
			);
		}

		return array(
			'type'            => 'plugin',
			'label'           => isset( $artifact['label'] ) ? sanitize_text_field( (string) $artifact['label'] ) : $plugin_file,
			'stored_version'  => $stored_version,
			'current_version' => $current_version,
			'status'          => 'pass',
			'message'         => __( 'The stored plugin artifact matches the current installed plugin.', 'zignites-sentinel' ),
		);
	}

	/**
	 * Build a summary message.
	 *
	 * @param string $status Overall status.
	 * @return string
	 */
	protected function build_message( $status ) {
		if ( 'fail' === $status ) {
			return __( 'One or more rollback artifacts are missing or invalid.', 'zignites-sentinel' );
		}

		if ( 'warning' === $status ) {
			return __( 'Rollback artifacts are present, but some differ from the current site state.', 'zignites-sentinel' );
		}

		return __( 'Stored rollback artifacts align with the current site state.', 'zignites-sentinel' );
	}
}
