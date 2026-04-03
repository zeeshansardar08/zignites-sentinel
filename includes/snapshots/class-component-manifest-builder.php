<?php
/**
 * Builds component manifests for snapshot metadata.
 *
 * @package ZignitesSentinel
 */

namespace Zignites\Sentinel\Snapshots;

defined( 'ABSPATH' ) || exit;

class ComponentManifestBuilder {

	/**
	 * Build a manifest for the current active theme and plugins.
	 *
	 * @param array $active_plugins Active plugin state rows.
	 * @return array
	 */
	public function build( array $active_plugins ) {
		$theme      = wp_get_theme();
		$theme_path = trailingslashit( wp_normalize_path( get_theme_root() ) ) . $theme->get_stylesheet();

		$manifest = array(
			'generated_at' => current_time( 'mysql', true ),
			'theme'        => array(
				'stylesheet'    => sanitize_text_field( $theme->get_stylesheet() ),
				'name'          => sanitize_text_field( $theme->get( 'Name' ) ),
				'version'       => sanitize_text_field( $theme->get( 'Version' ) ),
				'source_exists' => is_dir( $theme_path ),
				'source_path'   => sanitize_text_field( $this->normalize_relative_path( $theme_path, wp_normalize_path( get_theme_root() ) ) ),
				'last_modified' => is_dir( $theme_path ) ? gmdate( 'Y-m-d H:i:s', (int) filemtime( $theme_path ) ) : '',
			),
			'plugins'      => array(),
		);

		foreach ( $active_plugins as $plugin_state ) {
			$plugin_file = isset( $plugin_state['plugin'] ) ? sanitize_text_field( (string) $plugin_state['plugin'] ) : '';
			$plugin_path = trailingslashit( wp_normalize_path( WP_PLUGIN_DIR ) ) . ltrim( wp_normalize_path( $plugin_file ), '/' );
			$exists      = ! empty( $plugin_file ) && file_exists( $plugin_path );

			$manifest['plugins'][] = array(
				'plugin'        => $plugin_file,
				'name'          => isset( $plugin_state['name'] ) ? sanitize_text_field( (string) $plugin_state['name'] ) : '',
				'version'       => isset( $plugin_state['version'] ) ? sanitize_text_field( (string) $plugin_state['version'] ) : '',
				'source_exists' => $exists,
				'source_path'   => sanitize_text_field( $plugin_file ),
				'last_modified' => $exists ? gmdate( 'Y-m-d H:i:s', (int) filemtime( $plugin_path ) ) : '',
			);
		}

		return $manifest;
	}

	/**
	 * Convert a component manifest into dedicated artifact rows.
	 *
	 * @param array $manifest Stored component manifest.
	 * @return array
	 */
	public function build_artifact_rows( array $manifest ) {
		$artifacts  = array();
		$created_at = isset( $manifest['generated_at'] ) ? sanitize_text_field( (string) $manifest['generated_at'] ) : current_time( 'mysql', true );

		if ( ! empty( $manifest['theme']['stylesheet'] ) ) {
			$artifacts[] = array(
				'artifact_type' => 'theme',
				'artifact_key'  => sanitize_text_field( (string) $manifest['theme']['stylesheet'] ),
				'label'         => isset( $manifest['theme']['name'] ) ? sanitize_text_field( (string) $manifest['theme']['name'] ) : sanitize_text_field( (string) $manifest['theme']['stylesheet'] ),
				'version'       => isset( $manifest['theme']['version'] ) ? sanitize_text_field( (string) $manifest['theme']['version'] ) : '',
				'source_path'   => isset( $manifest['theme']['source_path'] ) ? sanitize_text_field( (string) $manifest['theme']['source_path'] ) : '',
				'created_at'    => $created_at,
				'metadata'      => wp_json_encode(
					array(
						'last_modified' => isset( $manifest['theme']['last_modified'] ) ? sanitize_text_field( (string) $manifest['theme']['last_modified'] ) : '',
						'source_exists' => ! empty( $manifest['theme']['source_exists'] ),
					)
				),
			);
		}

		if ( ! empty( $manifest['plugins'] ) && is_array( $manifest['plugins'] ) ) {
			foreach ( $manifest['plugins'] as $plugin_state ) {
				if ( empty( $plugin_state['plugin'] ) ) {
					continue;
				}

				$artifacts[] = array(
					'artifact_type' => 'plugin',
					'artifact_key'  => sanitize_text_field( (string) $plugin_state['plugin'] ),
					'label'         => isset( $plugin_state['name'] ) ? sanitize_text_field( (string) $plugin_state['name'] ) : sanitize_text_field( (string) $plugin_state['plugin'] ),
					'version'       => isset( $plugin_state['version'] ) ? sanitize_text_field( (string) $plugin_state['version'] ) : '',
					'source_path'   => isset( $plugin_state['source_path'] ) ? sanitize_text_field( (string) $plugin_state['source_path'] ) : sanitize_text_field( (string) $plugin_state['plugin'] ),
					'created_at'    => $created_at,
					'metadata'      => wp_json_encode(
						array(
							'last_modified' => isset( $plugin_state['last_modified'] ) ? sanitize_text_field( (string) $plugin_state['last_modified'] ) : '',
							'source_exists' => ! empty( $plugin_state['source_exists'] ),
						)
					),
				);
			}
		}

		return $artifacts;
	}

	/**
	 * Normalize a child path relative to a base path.
	 *
	 * @param string $path Full path.
	 * @param string $base Base path.
	 * @return string
	 */
	protected function normalize_relative_path( $path, $base ) {
		$path = wp_normalize_path( $path );
		$base = trailingslashit( wp_normalize_path( $base ) );

		if ( 0 === strpos( $path, $base ) ) {
			return ltrim( substr( $path, strlen( $base ) ), '/' );
		}

		return $path;
	}
}
