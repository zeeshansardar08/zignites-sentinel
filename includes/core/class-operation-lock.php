<?php
/**
 * Global mutex for heavy Sentinel operations.
 *
 * @package ZignitesSentinel
 */

namespace Zignites\Sentinel\Core;

defined( 'ABSPATH' ) || exit;

class OperationLock {

	/**
	 * Default lock timeout in seconds.
	 *
	 * @var int
	 */
	const DEFAULT_TIMEOUT_SECONDS = 900;

	/**
	 * Acquire the global operation lock.
	 *
	 * @param string $operation       Operation key.
	 * @param string $owner           Optional owner context.
	 * @param int    $timeout_seconds Timeout in seconds.
	 * @return array
	 */
	public function acquire( $operation, $owner = '', $timeout_seconds = self::DEFAULT_TIMEOUT_SECONDS ) {
		$operation       = sanitize_key( (string) $operation );
		$owner           = sanitize_text_field( (string) $owner );
		$timeout_seconds = max( 60, absint( $timeout_seconds ) );
		$now             = time();
		$current         = $this->get_lock();

		if ( ! empty( $current ) && $this->is_stale( $current, $now ) ) {
			$this->force_release();
			$current = array();
		}

		if ( ! empty( $current ) ) {
			return array(
				'acquired' => false,
				'lock'     => $current,
				'message'  => $this->build_locked_message( $current ),
			);
		}

		$lock = array(
			'token'      => $this->generate_token( $operation, $owner, $now ),
			'operation'  => '' !== $operation ? $operation : 'operation',
			'owner'      => $owner,
			'acquired_at'=> $now,
			'expires_at' => $now + $timeout_seconds,
			'timeout'    => $timeout_seconds,
		);

		if ( $this->add_lock( $lock ) ) {
			return array(
				'acquired' => true,
				'lock'     => $lock,
				'message'  => '',
			);
		}

		$current = $this->get_lock();

		return array(
			'acquired' => false,
			'lock'     => $current,
			'message'  => $this->build_locked_message( $current ),
		);
	}

	/**
	 * Release a lock when the stored token still matches.
	 *
	 * @param array|string $lock_or_token Lock payload or token.
	 * @return bool
	 */
	public function release( $lock_or_token ) {
		$token = is_array( $lock_or_token ) && ! empty( $lock_or_token['token'] )
			? sanitize_text_field( (string) $lock_or_token['token'] )
			: sanitize_text_field( (string) $lock_or_token );

		if ( '' === $token ) {
			return false;
		}

		$current = $this->get_lock();

		if ( empty( $current['token'] ) || $token !== sanitize_text_field( (string) $current['token'] ) ) {
			return false;
		}

		$this->force_release();

		return true;
	}

	/**
	 * Return the current non-stale lock payload.
	 *
	 * @return array
	 */
	public function get_current_lock() {
		$lock = $this->get_lock();

		if ( ! empty( $lock ) && $this->is_stale( $lock, time() ) ) {
			$this->force_release();
			return array();
		}

		return $lock;
	}

	/**
	 * Remove the lock regardless of token.
	 *
	 * @return void
	 */
	public function force_release() {
		delete_option( ZNTS_OPTION_OPERATION_LOCK );
	}

	/**
	 * Read the raw lock option.
	 *
	 * @return array
	 */
	protected function get_lock() {
		$lock = get_option( ZNTS_OPTION_OPERATION_LOCK, array() );

		return is_array( $lock ) ? $lock : array();
	}

	/**
	 * Atomically add a lock option when WordPress provides add_option().
	 *
	 * @param array $lock Lock payload.
	 * @return bool
	 */
	protected function add_lock( array $lock ) {
		if ( function_exists( 'add_option' ) ) {
			return (bool) add_option( ZNTS_OPTION_OPERATION_LOCK, $lock, '', false );
		}

		if ( false !== get_option( ZNTS_OPTION_OPERATION_LOCK, false ) ) {
			return false;
		}

		return (bool) update_option( ZNTS_OPTION_OPERATION_LOCK, $lock, false );
	}

	/**
	 * Determine whether a lock has exceeded its timeout.
	 *
	 * @param array $lock Lock payload.
	 * @param int   $now  Current Unix timestamp.
	 * @return bool
	 */
	protected function is_stale( array $lock, $now ) {
		$expires_at = isset( $lock['expires_at'] ) ? (int) $lock['expires_at'] : 0;

		return $expires_at > 0 && $expires_at <= (int) $now;
	}

	/**
	 * Generate a unique lock token.
	 *
	 * @param string $operation Operation key.
	 * @param string $owner     Owner context.
	 * @param int    $now       Current Unix timestamp.
	 * @return string
	 */
	protected function generate_token( $operation, $owner, $now ) {
		try {
			$random = bin2hex( random_bytes( 16 ) );
		} catch ( \Exception $exception ) {
			$random = uniqid( 'znts-lock-', true );
		}

		return hash( 'sha256', $operation . '|' . $owner . '|' . (string) $now . '|' . $random );
	}

	/**
	 * Build a human-readable lock conflict message.
	 *
	 * @param array $lock Current lock.
	 * @return string
	 */
	protected function build_locked_message( array $lock ) {
		$operation = ! empty( $lock['operation'] ) ? sanitize_text_field( (string) $lock['operation'] ) : __( 'another operation', 'zignites-sentinel' );

		return sprintf(
			/* translators: %s = active operation key */
			__( 'Sentinel is already running %s. Wait for it to finish or for the lock timeout to expire before starting another heavy operation.', 'zignites-sentinel' ),
			$operation
		);
	}
}
