<?php
/**
 * Runtime diagnostics and signal collection.
 *
 * @package ZignitesSentinel
 */

namespace Zignites\Sentinel\Diagnostics;

use Zignites\Sentinel\Logging\Logger;

defined( 'ABSPATH' ) || exit;

class Monitor {

	/**
	 * Logger service.
	 *
	 * @var Logger
	 */
	protected $logger;

	/**
	 * Conflict repository.
	 *
	 * @var ConflictRepository
	 */
	protected $conflicts;

	/**
	 * Constructor.
	 *
	 * @param Logger             $logger    Logger service.
	 * @param ConflictRepository $conflicts Conflict repository.
	 */
	public function __construct( Logger $logger, ConflictRepository $conflicts ) {
		$this->logger    = $logger;
		$this->conflicts = $conflicts;
	}

	/**
	 * Register runtime monitoring hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'activated_plugin', array( $this, 'handle_plugin_activation' ), 10, 2 );
		add_action( 'deactivated_plugin', array( $this, 'handle_plugin_deactivation' ), 10, 2 );
		add_action( 'upgrader_process_complete', array( $this, 'handle_upgrader_process_complete' ), 10, 2 );
		add_action( 'deprecated_function_run', array( $this, 'handle_deprecated_function' ), 10, 3 );
		add_action( 'deprecated_argument_run', array( $this, 'handle_deprecated_argument' ), 10, 3 );
		add_action( 'doing_it_wrong_run', array( $this, 'handle_doing_it_wrong' ), 10, 3 );
		add_action( 'znts_collect_diagnostics', array( $this, 'collect_environment_snapshot' ) );
		add_action( 'shutdown', array( $this, 'capture_shutdown_error' ) );
	}

	/**
	 * Log a plugin activation event.
	 *
	 * @param string $plugin       Plugin basename.
	 * @param bool   $network_wide Whether activation is network-wide.
	 * @return void
	 */
	public function handle_plugin_activation( $plugin, $network_wide ) {
		$this->logger->log(
			'plugin_activated',
			'info',
			$this->get_plugin_source_label( $plugin ),
			__( 'Plugin activated.', 'zignites-sentinel' ),
			array(
				'plugin'       => $plugin,
				'network_wide' => (bool) $network_wide,
			)
		);
	}

	/**
	 * Log a plugin deactivation event.
	 *
	 * @param string $plugin       Plugin basename.
	 * @param bool   $network_wide Whether deactivation is network-wide.
	 * @return void
	 */
	public function handle_plugin_deactivation( $plugin, $network_wide ) {
		$this->logger->log(
			'plugin_deactivated',
			'info',
			$this->get_plugin_source_label( $plugin ),
			__( 'Plugin deactivated.', 'zignites-sentinel' ),
			array(
				'plugin'       => $plugin,
				'network_wide' => (bool) $network_wide,
			)
		);
	}

	/**
	 * Log update activity.
	 *
	 * @param \WP_Upgrader $upgrader Upgrade instance.
	 * @param array        $options  Upgrade options.
	 * @return void
	 */
	public function handle_upgrader_process_complete( $upgrader, $options ) {
		if ( empty( $options['action'] ) || empty( $options['type'] ) ) {
			return;
		}

		$action = sanitize_key( $options['action'] );
		$type   = sanitize_key( $options['type'] );

		if ( 'update' !== $action || ! in_array( $type, array( 'plugin', 'theme', 'core' ), true ) ) {
			return;
		}

		$targets = array();

		if ( ! empty( $options['plugins'] ) && is_array( $options['plugins'] ) ) {
			$targets = $options['plugins'];
		} elseif ( ! empty( $options['themes'] ) && is_array( $options['themes'] ) ) {
			$targets = $options['themes'];
		} elseif ( 'core' === $type ) {
			$targets = array( 'wordpress-core' );
		}

		$this->logger->log(
			'update_completed',
			'info',
			$type,
			__( 'WordPress update process completed.', 'zignites-sentinel' ),
			array(
				'action'  => $action,
				'type'    => $type,
				'targets' => $targets,
			)
		);
	}

	/**
	 * Log deprecated function use as a warning signal.
	 *
	 * @param string $function    Deprecated function name.
	 * @param string $replacement Replacement function.
	 * @param string $version     Deprecated since version.
	 * @return void
	 */
	public function handle_deprecated_function( $function, $replacement, $version ) {
		$source = $this->detect_component_from_backtrace();

		$this->logger->log(
			'deprecated_function',
			'warning',
			$source['label'],
			__( 'Deprecated function usage detected.', 'zignites-sentinel' ),
			array(
				'function'    => $function,
				'replacement' => $replacement,
				'version'     => $version,
			)
		);

		$this->record_conflict_signal(
			'deprecated_function',
			'warning',
			$source['label'],
			'',
			sprintf(
				/* translators: %s = deprecated function name */
				__( 'Deprecated function usage detected: %s', 'zignites-sentinel' ),
				$function
			),
			array(
				'function'    => $function,
				'replacement' => $replacement,
				'version'     => $version,
			)
		);
	}

	/**
	 * Log deprecated argument use as a warning signal.
	 *
	 * @param string $function Function name.
	 * @param string $message  Warning message.
	 * @param string $version  Deprecated since version.
	 * @return void
	 */
	public function handle_deprecated_argument( $function, $message, $version ) {
		$source = $this->detect_component_from_backtrace();

		$this->logger->log(
			'deprecated_argument',
			'warning',
			$source['label'],
			__( 'Deprecated argument usage detected.', 'zignites-sentinel' ),
			array(
				'function' => $function,
				'message'  => $message,
				'version'  => $version,
			)
		);
	}

	/**
	 * Log incorrect API usage as a signal.
	 *
	 * @param string $function Function name.
	 * @param string $message  Warning message.
	 * @param string $version  Related version.
	 * @return void
	 */
	public function handle_doing_it_wrong( $function, $message, $version ) {
		$source = $this->detect_component_from_backtrace();

		$this->logger->log(
			'doing_it_wrong',
			'warning',
			$source['label'],
			__( 'Potential hook or API misuse detected.', 'zignites-sentinel' ),
			array(
				'function' => $function,
				'message'  => $message,
				'version'  => $version,
			)
		);

		$this->record_conflict_signal(
			'doing_it_wrong',
			'warning',
			$source['label'],
			'',
			sprintf(
				/* translators: %s = function name */
				__( 'Potential API misuse detected in %s', 'zignites-sentinel' ),
				$function
			),
			array(
				'message' => $message,
				'version' => $version,
			)
		);
	}

	/**
	 * Collect a lightweight environment snapshot.
	 *
	 * @return void
	 */
	public function collect_environment_snapshot() {
		$active_plugins = (array) get_option( 'active_plugins', array() );
		$theme          = wp_get_theme();

		$this->logger->log(
			'environment_snapshot',
			'info',
			'site',
			__( 'Scheduled environment snapshot recorded.', 'zignites-sentinel' ),
			array(
				'wordpress'           => get_bloginfo( 'version' ),
				'php'                 => PHP_VERSION,
				'active_plugin_count' => count( $active_plugins ),
				'active_theme'        => $theme->get_stylesheet(),
			)
		);
	}

	/**
	 * Capture fatal errors at shutdown when possible.
	 *
	 * @return void
	 */
	public function capture_shutdown_error() {
		$error = error_get_last();

		if ( empty( $error['type'] ) || ! $this->is_fatal_error_type( (int) $error['type'] ) ) {
			return;
		}

		$component = $this->detect_component_from_file( isset( $error['file'] ) ? $error['file'] : '' );
		$message   = isset( $error['message'] ) ? wp_strip_all_tags( $error['message'] ) : __( 'Fatal error detected.', 'zignites-sentinel' );

		$this->logger->log(
			'php_fatal',
			'critical',
			$component['label'],
			$message,
			array(
				'file' => isset( $error['file'] ) ? $error['file'] : '',
				'line' => isset( $error['line'] ) ? (int) $error['line'] : 0,
				'type' => (int) $error['type'],
			)
		);

		$this->record_conflict_signal(
			'php_fatal',
			'critical',
			$component['label'],
			'',
			sprintf(
				/* translators: %s = component label */
				__( 'Fatal error detected in %s', 'zignites-sentinel' ),
				$component['label']
			),
			array(
				'message' => $message,
				'file'    => isset( $error['file'] ) ? $error['file'] : '',
				'line'    => isset( $error['line'] ) ? (int) $error['line'] : 0,
			)
		);
	}

	/**
	 * Determine whether an error type is fatal.
	 *
	 * @param int $type PHP error type.
	 * @return bool
	 */
	protected function is_fatal_error_type( $type ) {
		return in_array(
			$type,
			array( E_ERROR, E_PARSE, E_COMPILE_ERROR, E_CORE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR ),
			true
		);
	}

	/**
	 * Persist a normalized conflict signal.
	 *
	 * @param string $signal_type Signal type.
	 * @param string $severity    Signal severity.
	 * @param string $source_a    Primary source.
	 * @param string $source_b    Secondary source.
	 * @param string $summary     Short summary.
	 * @param array  $details     Additional details.
	 * @return void
	 */
	protected function record_conflict_signal( $signal_type, $severity, $source_a, $source_b, $summary, array $details ) {
		$conflict_key = sprintf(
			'%s:%s',
			sanitize_key( $signal_type ),
			md5( wp_json_encode( array( $source_a, $source_b, $summary ) ) )
		);

		$timestamp = current_time( 'mysql', true );

		$this->conflicts->upsert(
			array(
				'conflict_key'  => $conflict_key,
				'signal_type'   => sanitize_key( $signal_type ),
				'severity'      => sanitize_key( $severity ),
				'status'        => 'open',
				'source_a'      => sanitize_text_field( $source_a ),
				'source_b'      => sanitize_text_field( $source_b ),
				'summary'       => sanitize_text_field( $summary ),
				'details'       => wp_json_encode( $details ),
				'first_seen_at' => $timestamp,
				'last_seen_at'  => $timestamp,
			)
		);
	}

	/**
	 * Detect the most relevant component from a backtrace.
	 *
	 * @return array
	 */
	protected function detect_component_from_backtrace() {
		$trace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 15 );

		foreach ( $trace as $frame ) {
			if ( empty( $frame['file'] ) || ! is_string( $frame['file'] ) ) {
				continue;
			}

			$component = $this->detect_component_from_file( $frame['file'] );

			if ( 'wordpress-core' !== $component['label'] ) {
				return $component;
			}
		}

		return array(
			'label' => 'wordpress-core',
			'type'  => 'core',
			'slug'  => 'wordpress-core',
		);
	}

	/**
	 * Detect a plugin, theme, or core component from a file path.
	 *
	 * @param string $file File path.
	 * @return array
	 */
	protected function detect_component_from_file( $file ) {
		$file = wp_normalize_path( (string) $file );

		if ( empty( $file ) ) {
			return array(
				'label' => 'wordpress-core',
				'type'  => 'core',
				'slug'  => 'wordpress-core',
			);
		}

		$plugin_dir = wp_normalize_path( WP_PLUGIN_DIR );

		if ( 0 === strpos( $file, $plugin_dir ) ) {
			$relative = ltrim( str_replace( $plugin_dir, '', $file ), '/' );
			$parts    = explode( '/', $relative );
			$slug     = ! empty( $parts[0] ) ? sanitize_title( $parts[0] ) : 'unknown-plugin';

			return array(
				'label' => 'plugin:' . $slug,
				'type'  => 'plugin',
				'slug'  => $slug,
			);
		}

		if ( defined( 'WPMU_PLUGIN_DIR' ) ) {
			$mu_plugin_dir = wp_normalize_path( WPMU_PLUGIN_DIR );

			if ( 0 === strpos( $file, $mu_plugin_dir ) ) {
				$relative = ltrim( str_replace( $mu_plugin_dir, '', $file ), '/' );
				$parts    = explode( '/', $relative );
				$slug     = ! empty( $parts[0] ) ? sanitize_title( $parts[0] ) : 'mu-plugin';

				return array(
					'label' => 'mu-plugin:' . $slug,
					'type'  => 'mu-plugin',
					'slug'  => $slug,
				);
			}
		}

		$theme_root = wp_normalize_path( get_theme_root() );

		if ( 0 === strpos( $file, $theme_root ) ) {
			$relative = ltrim( str_replace( $theme_root, '', $file ), '/' );
			$parts    = explode( '/', $relative );
			$slug     = ! empty( $parts[0] ) ? sanitize_title( $parts[0] ) : 'unknown-theme';

			return array(
				'label' => 'theme:' . $slug,
				'type'  => 'theme',
				'slug'  => $slug,
			);
		}

		return array(
			'label' => 'wordpress-core',
			'type'  => 'core',
			'slug'  => 'wordpress-core',
		);
	}

	/**
	 * Normalize a plugin basename into a readable source label.
	 *
	 * @param string $plugin Plugin basename.
	 * @return string
	 */
	protected function get_plugin_source_label( $plugin ) {
		$directory = dirname( $plugin );

		if ( '.' === $directory ) {
			$directory = basename( $plugin, '.php' );
		}

		return 'plugin:' . sanitize_title( $directory );
	}
}
