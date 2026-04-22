<?php
/**
 * Shared helper for generating local authenticated wp-admin cookie headers.
 */

class ZNTS_Local_Admin_Auth_Helper {

	/**
	 * Walk upward from a starting path until a WordPress root is found.
	 *
	 * @param string $start_path Starting path.
	 * @return string
	 */
	public function find_wordpress_root( $start_path ) {
		$path = $this->normalize_path( $start_path );

		if ( '' === $path ) {
			return '';
		}

		if ( is_file( $path ) ) {
			$path = dirname( $path );
		}

		while ( '' !== $path && dirname( $path ) !== $path ) {
			if ( is_file( $path . DIRECTORY_SEPARATOR . 'wp-load.php' ) ) {
				return $path;
			}

			$path = dirname( $path );
		}

		if ( '' !== $path && is_file( $path . DIRECTORY_SEPARATOR . 'wp-load.php' ) ) {
			return $path;
		}

		return '';
	}

	/**
	 * Parse a wp-admin base URL into the server context needed by WordPress auth.
	 *
	 * @param string $base_url Base wp-admin URL.
	 * @return array
	 */
	public function parse_base_url_context( $base_url ) {
		$base_url = trim( (string) $base_url );
		$parts    = parse_url( $base_url );
		$scheme   = isset( $parts['scheme'] ) ? strtolower( (string) $parts['scheme'] ) : 'http';
		$host     = isset( $parts['host'] ) ? (string) $parts['host'] : '';
		$port     = isset( $parts['port'] ) ? (int) $parts['port'] : 0;

		if ( '' === $host ) {
			return array(
				'scheme'  => '',
				'host'    => '',
				'https'   => 'off',
				'base_url' => '',
			);
		}

		if ( $port > 0 ) {
			$host .= ':' . $port;
		}

		return array(
			'scheme'   => $scheme,
			'host'     => $host,
			'https'    => 'https' === $scheme ? 'on' : 'off',
			'base_url' => $base_url,
		);
	}

	/**
	 * Build a local authenticated cookie header for a given user.
	 *
	 * @param string $base_url       Base wp-admin URL.
	 * @param string $user_identifier User ID, login, or email.
	 * @param string $wordpress_root  WordPress root path.
	 * @return array
	 */
	public function build_cookie_context( $base_url, $user_identifier, $wordpress_root = '' ) {
		$user_identifier = trim( (string) $user_identifier );
		$wordpress_root  = $this->normalize_path( $wordpress_root );
		$url_context     = $this->parse_base_url_context( $base_url );

		if ( '' === $user_identifier ) {
			throw new RuntimeException( 'Missing local admin user identifier.' );
		}

		if ( '' === $wordpress_root ) {
			$wordpress_root = $this->find_wordpress_root( __DIR__ );
		}

		if ( '' === $wordpress_root ) {
			throw new RuntimeException( 'Could not locate a WordPress root for local admin auth.' );
		}

		if ( ! is_file( $wordpress_root . DIRECTORY_SEPARATOR . 'wp-load.php' ) ) {
			throw new RuntimeException( 'WordPress root does not contain wp-load.php: ' . $wordpress_root );
		}

		if ( '' === $url_context['host'] || '' === $url_context['scheme'] ) {
			throw new RuntimeException( 'Base URL must include a valid scheme and host for local admin auth.' );
		}

		$_SERVER['REQUEST_SCHEME'] = $url_context['scheme'];
		$_SERVER['HTTP_HOST']      = $url_context['host'];
		$_SERVER['HTTPS']          = $url_context['https'];
		$_SERVER['REQUEST_METHOD'] = 'GET';

		require_once $wordpress_root . DIRECTORY_SEPARATOR . 'wp-load.php';

		$user = $this->resolve_user( $user_identifier );

		if ( ! $user || empty( $user->ID ) ) {
			throw new RuntimeException( 'Could not resolve a local WordPress admin user from "' . $user_identifier . '".' );
		}

		$expiration       = time() + HOUR_IN_SECONDS;
		$manager          = WP_Session_Tokens::get_instance( (int) $user->ID );
		$token            = $manager->create( $expiration );
		$auth_scheme      = force_ssl_admin() || is_ssl() ? 'secure_auth' : 'auth';
		$auth_cookie_name = 'secure_auth' === $auth_scheme ? SECURE_AUTH_COOKIE : AUTH_COOKIE;
		$auth_cookie      = wp_generate_auth_cookie( (int) $user->ID, $expiration, $auth_scheme, $token );
		$logged_in_cookie = wp_generate_auth_cookie( (int) $user->ID, $expiration, 'logged_in', $token );
		$cookie_header    = $auth_cookie_name . '=' . $auth_cookie . '; ' . LOGGED_IN_COOKIE . '=' . $logged_in_cookie;
		$_COOKIE[ $auth_cookie_name ] = $auth_cookie;
		$_COOKIE[ LOGGED_IN_COOKIE ]  = $logged_in_cookie;

		return array(
			'cookie_header'  => $cookie_header,
			'user_id'        => (int) $user->ID,
			'user_login'     => isset( $user->user_login ) ? (string) $user->user_login : $user_identifier,
			'wordpress_root' => $wordpress_root,
			'auth_scheme'    => $auth_scheme,
			'auth_cookie'    => $auth_cookie,
			'auth_cookie_name' => $auth_cookie_name,
			'logged_in_cookie' => $logged_in_cookie,
		);
	}

	/**
	 * Build a nonce for an authenticated local user after WordPress is loaded.
	 *
	 * @param string $action  Nonce action.
	 * @param int    $user_id User ID.
	 * @return string
	 */
	public function build_nonce( $action, $user_id, $logged_in_cookie = '' ) {
		$action           = trim( (string) $action );
		$user_id          = (int) $user_id;
		$logged_in_cookie = trim( (string) $logged_in_cookie );

		if ( '' === $action || $user_id <= 0 ) {
			return '';
		}

		if ( '' !== $logged_in_cookie ) {
			$_COOKIE[ LOGGED_IN_COOKIE ] = $logged_in_cookie;
		}

		wp_set_current_user( $user_id );

		return (string) wp_create_nonce( $action );
	}

	/**
	 * Normalize a local path without requiring it to exist.
	 *
	 * @param string $path Path string.
	 * @return string
	 */
	public function normalize_path( $path ) {
		$path = trim( (string) $path );

		if ( '' === $path ) {
			return '';
		}

		return rtrim( $path, "\\/" );
	}

	/**
	 * Resolve a WordPress user by ID, login, or email.
	 *
	 * @param string $user_identifier User identifier.
	 * @return WP_User|false
	 */
	protected function resolve_user( $user_identifier ) {
		if ( is_numeric( $user_identifier ) ) {
			$user = get_user_by( 'id', (int) $user_identifier );

			if ( $user ) {
				return $user;
			}
		}

		$user = get_user_by( 'login', $user_identifier );

		if ( $user ) {
			return $user;
		}

		return get_user_by( 'email', $user_identifier );
	}
}
