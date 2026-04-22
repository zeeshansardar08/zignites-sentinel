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
	 * @param int   $user_id Current user ID.
	 * @param array $context Optional capture context.
	 * @return int|false
	 */
	public function create_manual_snapshot( $user_id, array $context = array() ) {
		$state         = $this->collect_site_state();
		$snapshot_data = $this->build_manual_snapshot_record_data( $state, $user_id, $context );
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
	 * Build the stored snapshot record payload.
	 *
	 * @param array $state   Collected site state.
	 * @param int   $user_id Current user ID.
	 * @param array $context Optional capture context.
	 * @return array
	 */
	protected function build_manual_snapshot_record_data( array $state, $user_id, array $context = array() ) {
		$context     = $this->normalize_manual_snapshot_context( $context );
		$timestamp   = wp_date( 'Y-m-d H:i:s' );
		$metadata    = isset( $state['metadata'] ) && is_array( $state['metadata'] ) ? $state['metadata'] : array();
		$label       = sprintf(
			/* translators: %s = date/time */
			__( 'Manual snapshot %s', 'zignites-sentinel' ),
			$timestamp
		);
		$description = __( 'Metadata snapshot captured before manual review or update activity.', 'zignites-sentinel' );

		if ( ! empty( $context['targets'] ) ) {
			$label       = $this->build_contextual_snapshot_label( $context, $timestamp );
			$description = $this->build_contextual_snapshot_description( $context );
			$metadata['capture_context'] = $context;
		}

		return array(
			'snapshot_type'    => 'manual',
			'status'           => 'ready',
			'label'            => $label,
			'description'      => $description,
			'core_version'     => isset( $state['core_version'] ) ? $state['core_version'] : '',
			'php_version'      => isset( $state['php_version'] ) ? $state['php_version'] : '',
			'theme_stylesheet' => isset( $state['theme_stylesheet'] ) ? $state['theme_stylesheet'] : '',
			'active_plugins'   => wp_json_encode( isset( $state['active_plugins'] ) ? $state['active_plugins'] : array() ),
			'metadata'         => wp_json_encode( $metadata ),
			'created_by'       => absint( $user_id ),
			'created_at'       => current_time( 'mysql', true ),
		);
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
	 * Normalize optional capture context for pre-update checkpoints.
	 *
	 * @param array $context Raw capture context.
	 * @return array
	 */
	protected function normalize_manual_snapshot_context( array $context ) {
		$normalized = array(
			'source'       => isset( $context['source'] ) ? sanitize_key( (string) $context['source'] ) : '',
			'return_screen'=> isset( $context['return_screen'] ) ? sanitize_key( (string) $context['return_screen'] ) : '',
			'screen_id'    => isset( $context['screen_id'] ) ? sanitize_key( (string) $context['screen_id'] ) : '',
			'capture_mode' => isset( $context['capture_mode'] ) ? sanitize_key( (string) $context['capture_mode'] ) : '',
			'scope'        => isset( $context['scope'] ) ? sanitize_key( (string) $context['scope'] ) : 'manual',
			'target_count' => isset( $context['target_count'] ) ? absint( $context['target_count'] ) : 0,
			'targets'      => array(),
		);

		if ( empty( $context['targets'] ) || ! is_array( $context['targets'] ) ) {
			return $normalized;
		}

		foreach ( $context['targets'] as $target ) {
			if ( ! is_array( $target ) ) {
				continue;
			}

			$normalized['targets'][] = array(
				'key'             => isset( $target['key'] ) ? sanitize_text_field( (string) $target['key'] ) : '',
				'type'            => isset( $target['type'] ) ? sanitize_key( (string) $target['type'] ) : '',
				'slug'            => isset( $target['slug'] ) ? sanitize_text_field( (string) $target['slug'] ) : '',
				'label'           => isset( $target['label'] ) ? sanitize_text_field( (string) $target['label'] ) : '',
				'current_version' => isset( $target['current_version'] ) ? sanitize_text_field( (string) $target['current_version'] ) : '',
				'new_version'     => isset( $target['new_version'] ) ? sanitize_text_field( (string) $target['new_version'] ) : '',
			);
		}

		if ( empty( $normalized['targets'] ) ) {
			$normalized['target_count'] = 0;
			$normalized['scope']        = 'manual';
		} else {
			$normalized['target_count'] = count( $normalized['targets'] );
		}

		return $normalized;
	}

	/**
	 * Build a contextual label for update-aware checkpoints.
	 *
	 * @param array  $context   Normalized capture context.
	 * @param string $timestamp Current timestamp.
	 * @return string
	 */
	protected function build_contextual_snapshot_label( array $context, $timestamp ) {
		$primary_target = $this->get_primary_context_target( $context );
		$scope          = isset( $context['scope'] ) ? sanitize_key( (string) $context['scope'] ) : 'manual';

		if ( ! empty( $primary_target['label'] ) && in_array( $scope, array( 'plugin', 'theme' ), true ) ) {
			return sprintf(
				/* translators: 1: target label, 2: timestamp */
				__( 'Pre-update checkpoint: %1$s %2$s', 'zignites-sentinel' ),
				$primary_target['label'],
				$timestamp
			);
		}

		if ( 'plugins' === $scope ) {
			return sprintf(
				/* translators: %s: timestamp */
				__( 'Pre-update plugins checkpoint %s', 'zignites-sentinel' ),
				$timestamp
			);
		}

		if ( 'themes' === $scope ) {
			return sprintf(
				/* translators: %s: timestamp */
				__( 'Pre-update themes checkpoint %s', 'zignites-sentinel' ),
				$timestamp
			);
		}

		return sprintf(
			/* translators: %s: timestamp */
			__( 'Pre-update code checkpoint %s', 'zignites-sentinel' ),
			$timestamp
		);
	}

	/**
	 * Build a contextual description for update-aware checkpoints.
	 *
	 * @param array $context Normalized capture context.
	 * @return string
	 */
	protected function build_contextual_snapshot_description( array $context ) {
		$screen_label   = $this->get_context_screen_label( $context );
		$primary_target = $this->get_primary_context_target( $context );
		$scope          = isset( $context['scope'] ) ? sanitize_key( (string) $context['scope'] ) : 'manual';

		if ( ! empty( $primary_target['label'] ) && in_array( $scope, array( 'plugin', 'theme' ), true ) ) {
			return sprintf(
				/* translators: 1: source screen label, 2: target label */
				__( 'Checkpoint captured from the %1$s before updating %2$s.', 'zignites-sentinel' ),
				$screen_label,
				$primary_target['label']
			);
		}

		if ( 'plugins' === $scope ) {
			return sprintf(
				/* translators: %s: source screen label */
				__( 'Checkpoint captured from the %s before a plugin update window.', 'zignites-sentinel' ),
				$screen_label
			);
		}

		if ( 'themes' === $scope ) {
			return sprintf(
				/* translators: %s: source screen label */
				__( 'Checkpoint captured from the %s before a theme update window.', 'zignites-sentinel' ),
				$screen_label
			);
		}

		return sprintf(
			/* translators: %s: source screen label */
			__( 'Checkpoint captured from the %s before a mixed plugin and theme update window.', 'zignites-sentinel' ),
			$screen_label
		);
	}

	/**
	 * Return the primary target from a contextual capture.
	 *
	 * @param array $context Normalized capture context.
	 * @return array
	 */
	protected function get_primary_context_target( array $context ) {
		if ( empty( $context['targets'] ) || ! is_array( $context['targets'] ) ) {
			return array();
		}

		return is_array( $context['targets'][0] ) ? $context['targets'][0] : array();
	}

	/**
	 * Build a human-readable source screen label for capture descriptions.
	 *
	 * @param array $context Normalized capture context.
	 * @return string
	 */
	protected function get_context_screen_label( array $context ) {
		$return_screen = isset( $context['return_screen'] ) ? sanitize_key( (string) $context['return_screen'] ) : '';

		if ( in_array( $return_screen, array( 'plugins', 'plugins-network' ), true ) ) {
			return __( 'plugins update screen', 'zignites-sentinel' );
		}

		if ( in_array( $return_screen, array( 'themes', 'themes-network' ), true ) ) {
			return __( 'themes update screen', 'zignites-sentinel' );
		}

		if ( in_array( $return_screen, array( 'update-core', 'update-core-network' ), true ) ) {
			return __( 'Updates screen', 'zignites-sentinel' );
		}

		return __( 'update workflow', 'zignites-sentinel' );
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
