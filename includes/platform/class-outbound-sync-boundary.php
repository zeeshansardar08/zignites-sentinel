<?php
/**
 * Disabled-by-default boundary for future outbound platform sync.
 *
 * @package ZignitesSentinel
 */

namespace Zignites\Sentinel\Platform;

defined( 'ABSPATH' ) || exit;

class OutboundSyncBoundary {

	/**
	 * Return default sync settings.
	 *
	 * @return array
	 */
	public function get_default_settings() {
		return array(
			'enabled'       => 0,
			'endpoint_url'  => '',
			'last_sync'     => array(),
			'sync_interval' => 'manual',
		);
	}

	/**
	 * Normalize future sync settings without enabling network behavior by default.
	 *
	 * @param array $settings Raw settings.
	 * @return array
	 */
	public function normalize_settings( array $settings ) {
		$normalized                  = $this->get_default_settings();
		$normalized['enabled']       = ! empty( $settings['enabled'] ) ? 1 : 0;
		$normalized['endpoint_url']  = $this->sanitize_endpoint_url( isset( $settings['endpoint_url'] ) ? $settings['endpoint_url'] : '' );
		$normalized['sync_interval'] = isset( $settings['sync_interval'] ) && 'manual' !== $settings['sync_interval'] ? sanitize_key( (string) $settings['sync_interval'] ) : 'manual';

		if ( isset( $settings['last_sync'] ) && is_array( $settings['last_sync'] ) ) {
			$normalized['last_sync'] = $settings['last_sync'];
		}

		return $normalized;
	}

	/**
	 * Build read-only service state for admin/API consumers.
	 *
	 * @param array $settings Sync settings.
	 * @return array
	 */
	public function build_state( array $settings ) {
		$settings = $this->normalize_settings( $settings );

		return array(
			'enabled'      => ! empty( $settings['enabled'] ),
			'configured'   => '' !== $settings['endpoint_url'],
			'endpoint_url' => $settings['endpoint_url'],
			'last_sync'    => isset( $settings['last_sync'] ) && is_array( $settings['last_sync'] ) ? $settings['last_sync'] : array(),
			'message'      => empty( $settings['enabled'] )
				? __( 'Outbound platform sync is disabled.', 'zignites-sentinel' )
				: __( 'Outbound platform sync is configured but not scheduled by Sentinel yet.', 'zignites-sentinel' ),
		);
	}

	/**
	 * Explicitly skip sync unless a future caller enables and implements delivery.
	 *
	 * @param array $payload Status payload.
	 * @param array $settings Sync settings.
	 * @return array
	 */
	public function sync_status( array $payload, array $settings ) {
		$settings = $this->normalize_settings( $settings );

		if ( empty( $settings['enabled'] ) ) {
			return array(
				'sent'       => false,
				'skipped'    => true,
				'checked_at' => current_time( 'mysql', true ),
				'reason'     => __( 'Outbound platform sync is disabled.', 'zignites-sentinel' ),
			);
		}

		if ( '' === $settings['endpoint_url'] ) {
			return array(
				'sent'       => false,
				'skipped'    => true,
				'checked_at' => current_time( 'mysql', true ),
				'reason'     => __( 'No outbound platform endpoint is configured.', 'zignites-sentinel' ),
			);
		}

		return array(
			'sent'       => false,
			'skipped'    => true,
			'checked_at' => current_time( 'mysql', true ),
			'reason'     => __( 'Outbound platform delivery is reserved for a future release.', 'zignites-sentinel' ),
			'payload'    => $payload,
		);
	}

	/**
	 * Sanitize a future endpoint URL.
	 *
	 * @param string $url Raw URL.
	 * @return string
	 */
	protected function sanitize_endpoint_url( $url ) {
		$url    = esc_url_raw( trim( (string) $url ) );
		$scheme = '' !== $url ? strtolower( (string) parse_url( $url, PHP_URL_SCHEME ) ) : '';

		return in_array( $scheme, array( 'https' ), true ) ? $url : '';
	}
}
