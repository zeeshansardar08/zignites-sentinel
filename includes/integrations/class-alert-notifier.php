<?php
/**
 * Alert notification delivery for team integrations.
 *
 * @package ZignitesSentinel
 */

namespace Zignites\Sentinel\Integrations;

defined( 'ABSPATH' ) || exit;

class AlertNotifier {

	/**
	 * Return supported event labels.
	 *
	 * @return array
	 */
	public function get_event_labels() {
		return array(
			'checkpoint_created'  => __( 'Checkpoint created', 'zignites-sentinel' ),
			'restore_started'     => __( 'Restore started', 'zignites-sentinel' ),
			'restore_failed'      => __( 'Restore failed', 'zignites-sentinel' ),
			'rollback_completed'  => __( 'Rollback completed', 'zignites-sentinel' ),
			'health_check_failed' => __( 'Health check failed', 'zignites-sentinel' ),
		);
	}

	/**
	 * Return supported external monitoring labels.
	 *
	 * @return array
	 */
	public function get_external_tool_labels() {
		return array(
			'uptime_robot' => __( 'UptimeRobot', 'zignites-sentinel' ),
			'sentry'       => __( 'Sentry', 'zignites-sentinel' ),
			'new_relic'    => __( 'New Relic', 'zignites-sentinel' ),
			'datadog'      => __( 'Datadog', 'zignites-sentinel' ),
		);
	}

	/**
	 * Return default alert settings.
	 *
	 * @return array
	 */
	public function get_default_settings() {
		return array(
			'enabled'          => 0,
			'channels'         => array(
				'generic'  => array( 'url' => '' ),
				'slack'    => array( 'url' => '' ),
				'teams'    => array( 'url' => '' ),
				'telegram' => array(
					'url'     => '',
					'chat_id' => '',
				),
				'discord'  => array( 'url' => '' ),
			),
			'events'           => array_fill_keys( array_keys( $this->get_event_labels() ), 1 ),
			'external_links'   => array_fill_keys( array_keys( $this->get_external_tool_labels() ), '' ),
			'last_test_result' => array(),
		);
	}

	/**
	 * Normalize saved alert settings.
	 *
	 * @param array $settings Raw settings.
	 * @return array
	 */
	public function normalize_settings( array $settings ) {
		$defaults   = $this->get_default_settings();
		$normalized = $defaults;

		$normalized['enabled'] = ! empty( $settings['enabled'] ) ? 1 : 0;

		foreach ( $defaults['channels'] as $channel => $channel_defaults ) {
			$raw_channel = isset( $settings['channels'][ $channel ] ) && is_array( $settings['channels'][ $channel ] ) ? $settings['channels'][ $channel ] : array();

			foreach ( $channel_defaults as $key => $default ) {
				if ( 'url' === $key ) {
					$normalized['channels'][ $channel ][ $key ] = $this->sanitize_webhook_url( isset( $raw_channel[ $key ] ) ? $raw_channel[ $key ] : $default );
				} else {
					$normalized['channels'][ $channel ][ $key ] = sanitize_text_field( isset( $raw_channel[ $key ] ) ? (string) $raw_channel[ $key ] : (string) $default );
				}
			}
		}

		foreach ( $defaults['events'] as $event => $default ) {
			$normalized['events'][ $event ] = ! empty( $settings['events'][ $event ] ) ? 1 : 0;
		}

		foreach ( $defaults['external_links'] as $tool => $default ) {
			$normalized['external_links'][ $tool ] = $this->sanitize_webhook_url( isset( $settings['external_links'][ $tool ] ) ? $settings['external_links'][ $tool ] : $default );
		}

		if ( isset( $settings['last_test_result'] ) && is_array( $settings['last_test_result'] ) ) {
			$normalized['last_test_result'] = $settings['last_test_result'];
		}

		return $normalized;
	}

	/**
	 * Build dashboard state for configured integrations.
	 *
	 * @param array $settings Normalized settings.
	 * @return array
	 */
	public function build_settings_summary( array $settings ) {
		$settings            = $this->normalize_settings( $settings );
		$configured_channels = array();
		$external_links      = array();

		foreach ( $settings['channels'] as $channel => $config ) {
			if ( ! empty( $config['url'] ) ) {
				$configured_channels[] = $channel;
			}
		}

		foreach ( $this->get_external_tool_labels() as $tool => $label ) {
			$external_links[] = array(
				'key'   => $tool,
				'label' => $label,
				'url'   => isset( $settings['external_links'][ $tool ] ) ? $settings['external_links'][ $tool ] : '',
			);
		}

		return array(
			'enabled'             => ! empty( $settings['enabled'] ),
			'configured_channels' => $configured_channels,
			'channel_count'       => count( $configured_channels ),
			'events'              => $settings['events'],
			'event_labels'        => $this->get_event_labels(),
			'external_links'      => $external_links,
			'last_test_result'    => isset( $settings['last_test_result'] ) && is_array( $settings['last_test_result'] ) ? $settings['last_test_result'] : array(),
		);
	}

	/**
	 * Send a test alert to configured channels.
	 *
	 * @param array $settings Alert settings.
	 * @return array
	 */
	public function send_test( array $settings ) {
		return $this->notify_event(
			'checkpoint_created',
			array(
				'snapshot_id' => 0,
				'status'      => 'test',
				'message'     => __( 'This is a Sentinel test alert.', 'zignites-sentinel' ),
			),
			$settings,
			true
		);
	}

	/**
	 * Send a configured alert event.
	 *
	 * @param string $event_type Event type.
	 * @param array  $context    Event context.
	 * @param array  $settings   Alert settings.
	 * @param bool   $force      Whether to ignore enabled/event toggles.
	 * @return array
	 */
	public function notify_event( $event_type, array $context, array $settings, $force = false ) {
		$event_type = sanitize_key( (string) $event_type );
		$settings   = $this->normalize_settings( $settings );
		$result     = array(
			'event_type' => $event_type,
			'sent'       => 0,
			'failed'     => 0,
			'skipped'    => false,
			'channels'   => array(),
			'checked_at' => current_time( 'mysql', true ),
		);

		if ( ! $force && ( empty( $settings['enabled'] ) || empty( $settings['events'][ $event_type ] ) ) ) {
			$result['skipped'] = true;
			$result['reason']  = __( 'Alerts are disabled for this event.', 'zignites-sentinel' );
			return $result;
		}

		$payload = $this->build_event_payload( $event_type, $context );

		foreach ( $settings['channels'] as $channel => $config ) {
			if ( empty( $config['url'] ) ) {
				continue;
			}

			$delivery = $this->deliver_channel( $channel, $config, $payload );
			$result['channels'][] = $delivery;

			if ( ! empty( $delivery['success'] ) ) {
				$result['sent']++;
			} else {
				$result['failed']++;
			}
		}

		if ( empty( $result['channels'] ) ) {
			$result['skipped'] = true;
			$result['reason']  = __( 'No alert channels are configured.', 'zignites-sentinel' );
		}

		return $result;
	}

	/**
	 * Build a normalized event payload.
	 *
	 * @param string $event_type Event type.
	 * @param array  $context    Event context.
	 * @return array
	 */
	public function build_event_payload( $event_type, array $context = array() ) {
		$event_type = sanitize_key( (string) $event_type );
		$labels     = $this->get_event_labels();
		$title      = isset( $labels[ $event_type ] ) ? $labels[ $event_type ] : ucwords( str_replace( '_', ' ', $event_type ) );
		$site       = function_exists( 'get_bloginfo' ) ? get_bloginfo( 'name' ) : '';
		$message    = isset( $context['message'] ) ? sanitize_text_field( (string) $context['message'] ) : $title;

		return array(
			'plugin'     => 'Zignites Sentinel',
			'site'       => $site,
			'site_url'   => function_exists( 'home_url' ) ? home_url( '/' ) : '',
			'event_type' => $event_type,
			'title'      => $title,
			'message'    => $message,
			'context'    => $this->sanitize_payload_context( $context ),
			'created_at' => current_time( 'mysql', true ),
		);
	}

	/**
	 * Deliver a payload to one channel.
	 *
	 * @param string $channel Channel key.
	 * @param array  $config  Channel config.
	 * @param array  $payload Event payload.
	 * @return array
	 */
	protected function deliver_channel( $channel, array $config, array $payload ) {
		$channel = sanitize_key( (string) $channel );
		$url     = isset( $config['url'] ) ? $this->sanitize_webhook_url( $config['url'] ) : '';

		if ( '' === $url ) {
			return array(
				'channel' => $channel,
				'success' => false,
				'error'   => __( 'Webhook URL is missing.', 'zignites-sentinel' ),
			);
		}

		if ( function_exists( 'wp_http_validate_url' ) && ! wp_http_validate_url( $url ) ) {
			return array(
				'channel' => $channel,
				'success' => false,
				'error'   => __( 'Webhook URL was rejected as unsafe (private or reserved network address).', 'zignites-sentinel' ),
			);
		}

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 10,
				'headers' => array(
					'Content-Type' => 'application/json; charset=utf-8',
				),
				'body'    => wp_json_encode( $this->format_channel_payload( $channel, $config, $payload ) ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'channel' => $channel,
				'success' => false,
				'error'   => $response->get_error_message(),
			);
		}

		$code = wp_remote_retrieve_response_code( $response );

		return array(
			'channel'     => $channel,
			'success'     => $code >= 200 && $code < 300,
			'status_code' => $code,
		);
	}

	/**
	 * Format a payload for an adapter.
	 *
	 * @param string $channel Channel key.
	 * @param array  $config  Channel config.
	 * @param array  $payload Normalized payload.
	 * @return array
	 */
	protected function format_channel_payload( $channel, array $config, array $payload ) {
		$text = $this->format_text_message( $payload );

		if ( 'generic' === $channel ) {
			return $payload;
		}

		if ( 'discord' === $channel ) {
			return array(
				'content' => $text,
			);
		}

		if ( 'telegram' === $channel ) {
			return array(
				'chat_id' => isset( $config['chat_id'] ) ? sanitize_text_field( (string) $config['chat_id'] ) : '',
				'text'    => $text,
			);
		}

		return array(
			'text' => $text,
		);
	}

	/**
	 * Format a short chat message.
	 *
	 * @param array $payload Event payload.
	 * @return string
	 */
	protected function format_text_message( array $payload ) {
		$site  = isset( $payload['site'] ) && '' !== $payload['site'] ? $payload['site'] : 'WordPress';
		$title = isset( $payload['title'] ) ? $payload['title'] : '';
		$msg   = isset( $payload['message'] ) ? $payload['message'] : '';

		return trim( '[' . $site . '] ' . $title . ': ' . $msg );
	}

	/**
	 * Sanitize an outbound URL.
	 *
	 * @param string $url Raw URL.
	 * @return string
	 */
	protected function sanitize_webhook_url( $url ) {
		$url    = esc_url_raw( trim( (string) $url ) );
		$scheme = '' !== $url ? strtolower( (string) parse_url( $url, PHP_URL_SCHEME ) ) : '';

		return in_array( $scheme, array( 'http', 'https' ), true ) ? $url : '';
	}

	/**
	 * Sanitize payload context recursively.
	 *
	 * @param mixed $value Raw value.
	 * @return mixed
	 */
	protected function sanitize_payload_context( $value ) {
		if ( is_array( $value ) ) {
			$sanitized = array();

			foreach ( $value as $key => $item ) {
				$sanitized[ is_string( $key ) ? sanitize_key( $key ) : $key ] = $this->sanitize_payload_context( $item );
			}

			return $sanitized;
		}

		if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) || null === $value ) {
			return $value;
		}

		return sanitize_text_field( (string) $value );
	}
}
