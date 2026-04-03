<?php
/**
 * Structured logging service.
 *
 * @package ZignitesSentinel
 */

namespace Zignites\Sentinel\Logging;

defined( 'ABSPATH' ) || exit;

class Logger {

	/**
	 * Supported severities.
	 *
	 * @var string[]
	 */
	protected $severities = array( 'info', 'warning', 'error', 'critical' );

	/**
	 * Repository instance.
	 *
	 * @var LogRepository
	 */
	protected $repository;

	/**
	 * Constructor.
	 *
	 * @param LogRepository $repository Log repository.
	 */
	public function __construct( LogRepository $repository ) {
		$this->repository = $repository;
	}

	/**
	 * Persist a structured log entry.
	 *
	 * @param string       $event_type Event key.
	 * @param string       $severity   Log severity.
	 * @param string       $source     Component source.
	 * @param string       $message    Human-readable message.
	 * @param array|string $context    Optional context data.
	 * @return int|false
	 */
	public function log( $event_type, $severity, $source, $message, $context = array() ) {
		$settings = get_option( ZNTS_OPTION_SETTINGS, array() );

		if ( isset( $settings['logging_enabled'] ) && ! $settings['logging_enabled'] ) {
			return false;
		}

		$event_type = sanitize_key( $event_type );
		$severity   = $this->normalize_severity( $severity );
		$source     = substr( sanitize_text_field( $source ), 0, 100 );
		$message    = sanitize_text_field( $message );
		$context    = $this->prepare_context( $context );

		return $this->repository->insert(
			array(
				'event_type' => $event_type,
				'severity'   => $severity,
				'source'     => $source,
				'message'    => $message,
				'context'    => $context,
				'created_at' => current_time( 'mysql', true ),
			)
		);
	}

	/**
	 * Normalize the requested severity.
	 *
	 * @param string $severity Raw severity value.
	 * @return string
	 */
	protected function normalize_severity( $severity ) {
		$severity = sanitize_key( $severity );

		if ( ! in_array( $severity, $this->severities, true ) ) {
			return 'info';
		}

		return $severity;
	}

	/**
	 * Prepare context for JSON storage.
	 *
	 * @param array|string $context Context data.
	 * @return string
	 */
	protected function prepare_context( $context ) {
		if ( is_string( $context ) ) {
			return wp_json_encode(
				array(
					'message' => sanitize_text_field( $context ),
				)
			);
		}

		if ( ! is_array( $context ) ) {
			return wp_json_encode( array() );
		}

		return wp_json_encode( $this->sanitize_context_value( $context ) );
	}

	/**
	 * Sanitize nested context data.
	 *
	 * @param mixed $value Raw context value.
	 * @return mixed
	 */
	protected function sanitize_context_value( $value ) {
		if ( is_array( $value ) ) {
			$sanitized = array();

			foreach ( $value as $key => $item ) {
				$sanitized_key               = is_string( $key ) ? sanitize_key( $key ) : $key;
				$sanitized[ $sanitized_key ] = $this->sanitize_context_value( $item );
			}

			return $sanitized;
		}

		if ( is_bool( $value ) || is_null( $value ) || is_int( $value ) || is_float( $value ) ) {
			return $value;
		}

		return sanitize_text_field( (string) $value );
	}
}
