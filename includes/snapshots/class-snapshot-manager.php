<?php
/**
 * Snapshot metadata creation.
 *
 * @package ZignitesSentinel
 */

namespace Zignites\Sentinel\Snapshots;

use Zignites\Sentinel\Logging\Logger;

defined( 'ABSPATH' ) || exit;

class SnapshotManager {

	/**
	 * Repository.
	 *
	 * @var SnapshotRepository
	 */
	protected $repository;

	/**
	 * Manifest builder.
	 *
	 * @var ComponentManifestBuilder
	 */
	protected $manifest_builder;

	/**
	 * Artifact repository.
	 *
	 * @var SnapshotArtifactRepository
	 */
	protected $artifact_repository;

	/**
	 * Export manager.
	 *
	 * @var SnapshotExportManager
	 */
	protected $export_manager;

	/**
	 * Package manager.
	 *
	 * @var SnapshotPackageManager
	 */
	protected $package_manager;

	/**
	 * Structured logger.
	 *
	 * @var Logger|null
	 */
	protected $logger;

	/**
	 * Constructor.
	 *
	 * @param SnapshotRepository $repository Snapshot repository.
	 */
	public function __construct(
		SnapshotRepository $repository,
		SnapshotArtifactRepository $artifact_repository,
		ComponentManifestBuilder $manifest_builder = null,
		SnapshotExportManager $export_manager = null,
		SnapshotPackageManager $package_manager = null,
		Logger $logger = null
	) {
		$this->repository          = $repository;
		$this->artifact_repository = $artifact_repository;
		$this->manifest_builder    = $manifest_builder ? $manifest_builder : new ComponentManifestBuilder();
		$this->export_manager      = $export_manager ? $export_manager : new SnapshotExportManager();
		$this->package_manager     = $package_manager ? $package_manager : new SnapshotPackageManager();
		$this->logger              = $logger;
	}

	/**
	 * Create a manual metadata snapshot.
	 *
	 * @param int $user_id Current user ID.
	 * @return int|false
	 */
	public function create_manual_snapshot( $user_id ) {
		$state         = $this->collect_site_state();
		$snapshot_data = array(
			'snapshot_type'    => 'manual',
			'status'           => 'ready',
			'label'            => sprintf(
				/* translators: %s = date/time */
				__( 'Manual snapshot %s', 'zignites-sentinel' ),
				wp_date( 'Y-m-d H:i:s' )
			),
			'description'      => __( 'Metadata snapshot captured before manual review or update activity.', 'zignites-sentinel' ),
			'core_version'     => $state['core_version'],
			'php_version'      => $state['php_version'],
			'theme_stylesheet' => $state['theme_stylesheet'],
			'active_plugins'   => wp_json_encode( $state['active_plugins'] ),
			'metadata'         => wp_json_encode( $state['metadata'] ),
			'created_by'       => absint( $user_id ),
			'created_at'       => current_time( 'mysql', true ),
		);
		$inserted      = $this->repository->insert( $snapshot_data );

		if ( false === $inserted ) {
			return false;
		}

		$artifacts = array();

		if ( ! empty( $state['metadata']['component_manifest'] ) && is_array( $state['metadata']['component_manifest'] ) ) {
			$artifacts = $this->manifest_builder->build_artifact_rows( $state['metadata']['component_manifest'] );
		}

		$export_artifact = $this->export_manager->create_snapshot_export(
			$inserted,
			array(
				'id'               => $inserted,
				'label'            => $snapshot_data['label'],
				'description'      => $snapshot_data['description'],
				'core_version'     => $snapshot_data['core_version'],
				'php_version'      => $snapshot_data['php_version'],
				'theme_stylesheet' => $snapshot_data['theme_stylesheet'],
				'active_plugins'   => $state['active_plugins'],
				'metadata'         => $state['metadata'],
				'created_by'       => $snapshot_data['created_by'],
				'created_at'       => $snapshot_data['created_at'],
			),
			$artifacts
		);

		if ( is_array( $export_artifact ) ) {
			$artifacts[] = $export_artifact;
			$this->log_snapshot_event(
				'snapshot_export_created',
				'info',
				__( 'Snapshot export payload created.', 'zignites-sentinel' ),
				array(
					'snapshot_id' => $inserted,
					'path'        => isset( $export_artifact['source_path'] ) ? $export_artifact['source_path'] : '',
				)
			);
		} else {
			$this->log_snapshot_event(
				'snapshot_export_failed',
				'warning',
				__( 'Snapshot export payload could not be created.', 'zignites-sentinel' ),
				array(
					'snapshot_id' => $inserted,
				)
			);
		}

		$package_artifact = $this->package_manager->create_snapshot_package(
			$inserted,
			array(
				'id'                     => $inserted,
				'label'                  => $snapshot_data['label'],
				'description'            => $snapshot_data['description'],
				'core_version'           => $snapshot_data['core_version'],
				'php_version'            => $snapshot_data['php_version'],
				'theme_stylesheet'       => $snapshot_data['theme_stylesheet'],
				'active_plugins'         => $state['active_plugins'],
				'active_plugins_decoded' => $state['active_plugins'],
				'metadata'               => $state['metadata'],
				'created_by'             => $snapshot_data['created_by'],
				'created_at'             => $snapshot_data['created_at'],
			),
			$artifacts
		);

		if ( is_array( $package_artifact ) ) {
			$artifacts[] = $package_artifact;
			$this->log_snapshot_event(
				'snapshot_package_created',
				'info',
				__( 'Snapshot ZIP package created.', 'zignites-sentinel' ),
				array(
					'snapshot_id' => $inserted,
					'path'        => isset( $package_artifact['source_path'] ) ? $package_artifact['source_path'] : '',
				)
			);
		} else {
			$this->log_snapshot_event(
				'snapshot_package_failed',
				'warning',
				__( 'Snapshot ZIP package could not be created.', 'zignites-sentinel' ),
				array(
					'snapshot_id' => $inserted,
				)
			);
		}

		$this->artifact_repository->replace_for_snapshot( $inserted, $artifacts );

		return $inserted;
	}

	/**
	 * Collect current site state for metadata storage.
	 *
	 * @return array
	 */
	protected function collect_site_state() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$active_plugins       = (array) get_option( 'active_plugins', array() );
		$all_plugins          = get_plugins();
		$active_plugin_states = array();

		foreach ( $active_plugins as $plugin_file ) {
			$plugin_data = isset( $all_plugins[ $plugin_file ] ) ? $all_plugins[ $plugin_file ] : array();

			$active_plugin_states[] = array(
				'plugin'  => sanitize_text_field( $plugin_file ),
				'name'    => isset( $plugin_data['Name'] ) ? sanitize_text_field( $plugin_data['Name'] ) : '',
				'version' => isset( $plugin_data['Version'] ) ? sanitize_text_field( $plugin_data['Version'] ) : '',
			);
		}

		$theme = wp_get_theme();

		return array(
			'core_version'     => get_bloginfo( 'version' ),
			'php_version'      => PHP_VERSION,
			'theme_stylesheet' => $theme->get_stylesheet(),
			'active_plugins'   => $active_plugin_states,
			'metadata'         => array(
				'site_url'            => home_url(),
				'is_multisite'        => is_multisite(),
				'active_plugin_count' => count( $active_plugins ),
				'theme_name'          => $theme->get( 'Name' ),
				'theme_version'       => $theme->get( 'Version' ),
				'component_manifest'  => $this->manifest_builder->build( $active_plugin_states ),
			),
		);
	}

	/**
	 * Write a snapshot-related log entry when logging is available.
	 *
	 * @param string $event_type Event type.
	 * @param string $severity   Severity.
	 * @param string $message    Message.
	 * @param array  $context    Context.
	 * @return void
	 */
	protected function log_snapshot_event( $event_type, $severity, $message, array $context ) {
		if ( ! $this->logger ) {
			return;
		}

		$this->logger->log(
			$event_type,
			$severity,
			'snapshots',
			$message,
			$context
		);
	}
}
