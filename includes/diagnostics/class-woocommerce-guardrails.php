<?php
/**
 * WooCommerce-specific update risk guardrails.
 *
 * @package ZignitesSentinel
 */

namespace Zignites\Sentinel\Diagnostics;

defined( 'ABSPATH' ) || exit;

class WooCommerceGuardrails {

	/**
	 * Return default guardrail settings.
	 *
	 * @return array
	 */
	public function get_default_settings() {
		return array(
			'safe_update_mode'              => 1,
			'maintenance_window_confirmed' => 0,
			'external_db_backup_confirmed' => 0,
		);
	}

	/**
	 * Normalize guardrail settings.
	 *
	 * @param array $settings Raw settings.
	 * @return array
	 */
	public function normalize_settings( array $settings ) {
		$defaults = $this->get_default_settings();

		return array(
			'safe_update_mode'              => ! array_key_exists( 'safe_update_mode', $settings ) ? $defaults['safe_update_mode'] : ( ! empty( $settings['safe_update_mode'] ) ? 1 : 0 ),
			'maintenance_window_confirmed' => ! empty( $settings['maintenance_window_confirmed'] ) ? 1 : 0,
			'external_db_backup_confirmed' => ! empty( $settings['external_db_backup_confirmed'] ) ? 1 : 0,
		);
	}

	/**
	 * Build current WooCommerce guardrail state.
	 *
	 * @param array $settings Saved settings.
	 * @return array
	 */
	public function build_state( array $settings = array() ) {
		$settings = $this->normalize_settings( $settings );
		$detected = $this->detect();
		$checks   = $this->build_checks( $detected, $settings );

		return array(
			'active'       => ! empty( $detected['active'] ),
			'detected'     => $detected,
			'settings'     => $settings,
			'safe_mode_on' => ! empty( $detected['active'] ) && ! empty( $settings['safe_update_mode'] ),
			'badge'        => ! empty( $detected['active'] ) ? 'warning' : 'info',
			'label'        => ! empty( $detected['active'] ) ? __( 'WooCommerce detected', 'zignites-sentinel' ) : __( 'WooCommerce not detected', 'zignites-sentinel' ),
			'message'      => ! empty( $detected['active'] )
				? __( 'Use a maintenance window, confirm an external database backup, and treat carts, orders, payments, migrations, and schema changes as outside Sentinel rollback coverage.', 'zignites-sentinel' )
				: __( 'WooCommerce-specific update guardrails will appear when WooCommerce is active.', 'zignites-sentinel' ),
			'checks'       => $checks,
			'warnings'     => $this->build_warnings( $detected, $settings ),
			'report_lines' => $this->build_report_lines( $detected, $settings, $checks ),
		);
	}

	/**
	 * Detect active WooCommerce and runtime signals.
	 *
	 * @return array
	 */
	public function detect() {
		$active_plugins = get_option( 'active_plugins', array() );
		$active_plugins = is_array( $active_plugins ) ? $active_plugins : array();
		$network_active = function_exists( 'get_site_option' ) ? get_site_option( 'active_sitewide_plugins', array() ) : array();
		$network_active = is_array( $network_active ) ? $network_active : array();
		$active         = class_exists( 'WooCommerce' ) || in_array( 'woocommerce/woocommerce.php', $active_plugins, true ) || isset( $network_active['woocommerce/woocommerce.php'] );
		$cart_count     = $this->detect_cart_count();
		$order_count    = $this->detect_open_order_count();

		return array(
			'active'               => $active,
			'plugin_active'        => in_array( 'woocommerce/woocommerce.php', $active_plugins, true ),
			'network_active'       => isset( $network_active['woocommerce/woocommerce.php'] ),
			'class_loaded'         => class_exists( 'WooCommerce' ),
			'cart_count'           => $cart_count,
			'cart_count_available' => null !== $cart_count,
			'open_order_count'     => $order_count,
			'order_count_available'=> null !== $order_count,
		);
	}

	/**
	 * Build checklist rows for WooCommerce-safe updates.
	 *
	 * @param array $detected Detection state.
	 * @param array $settings Settings.
	 * @return array
	 */
	protected function build_checks( array $detected, array $settings ) {
		if ( empty( $detected['active'] ) ) {
			return array();
		}

		$cart_count  = isset( $detected['cart_count'] ) ? $detected['cart_count'] : null;
		$order_count = isset( $detected['open_order_count'] ) ? $detected['open_order_count'] : null;

		return array(
			array(
				'label'   => __( 'Maintenance window', 'zignites-sentinel' ),
				'status'  => ! empty( $settings['maintenance_window_confirmed'] ) ? 'pass' : 'warning',
				'message' => ! empty( $settings['maintenance_window_confirmed'] )
					? __( 'A maintenance window has been acknowledged for this update.', 'zignites-sentinel' )
					: __( 'Schedule the update during a low-traffic maintenance window before changing WooCommerce code.', 'zignites-sentinel' ),
			),
			array(
				'label'   => __( 'External database backup', 'zignites-sentinel' ),
				'status'  => ! empty( $settings['external_db_backup_confirmed'] ) ? 'pass' : 'warning',
				'message' => ! empty( $settings['external_db_backup_confirmed'] )
					? __( 'An external database backup has been acknowledged.', 'zignites-sentinel' )
					: __( 'Confirm a host or backup-platform database backup before updates that may touch orders, payments, or schema.', 'zignites-sentinel' ),
			),
			array(
				'label'   => __( 'Active carts', 'zignites-sentinel' ),
				'status'  => null === $cart_count ? 'warning' : ( $cart_count > 0 ? 'warning' : 'pass' ),
				'message' => null === $cart_count
					? __( 'Cart activity could not be detected in this context.', 'zignites-sentinel' )
					: sprintf(
						/* translators: %d: active cart item count */
						_n( '%d cart item is currently visible to this session.', '%d cart items are currently visible to this session.', (int) $cart_count, 'zignites-sentinel' ),
						(int) $cart_count
					),
			),
			array(
				'label'   => __( 'Open orders', 'zignites-sentinel' ),
				'status'  => null === $order_count ? 'warning' : ( $order_count > 0 ? 'warning' : 'pass' ),
				'message' => null === $order_count
					? __( 'Open order count could not be detected without WooCommerce order APIs.', 'zignites-sentinel' )
					: sprintf(
						/* translators: %d: open order count */
						_n( '%d pending, processing, or on-hold order was detected.', '%d pending, processing, or on-hold orders were detected.', (int) $order_count, 'zignites-sentinel' ),
						(int) $order_count
					),
			),
			array(
				'label'   => __( 'Rollback boundary', 'zignites-sentinel' ),
				'status'  => 'warning',
				'message' => __( 'Sentinel can restore plugin/theme code checkpoints only. It cannot roll back WooCommerce orders, payments, database migrations, or schema changes.', 'zignites-sentinel' ),
			),
		);
	}

	/**
	 * Build warning strings.
	 *
	 * @param array $detected Detection state.
	 * @param array $settings Settings.
	 * @return array
	 */
	protected function build_warnings( array $detected, array $settings ) {
		if ( empty( $detected['active'] ) ) {
			return array();
		}

		$warnings = array(
			__( 'WooCommerce updates can change database schema, order state, payment state, scheduled actions, and customer sessions outside Sentinel rollback coverage.', 'zignites-sentinel' ),
		);

		if ( empty( $settings['maintenance_window_confirmed'] ) ) {
			$warnings[] = __( 'Confirm a maintenance window before running plugin or theme updates on this store.', 'zignites-sentinel' );
		}

		if ( empty( $settings['external_db_backup_confirmed'] ) ) {
			$warnings[] = __( 'Confirm an external database backup before updating WooCommerce or extensions.', 'zignites-sentinel' );
		}

		if ( isset( $detected['cart_count'] ) && $detected['cart_count'] > 0 ) {
			$warnings[] = __( 'Visible cart activity exists in this session. Consider waiting until active shoppers are clear.', 'zignites-sentinel' );
		}

		if ( isset( $detected['open_order_count'] ) && $detected['open_order_count'] > 0 ) {
			$warnings[] = __( 'Open orders are present. Avoid updates while fulfillment or payment flows are active.', 'zignites-sentinel' );
		}

		return array_values( array_unique( $warnings ) );
	}

	/**
	 * Build report-ready lines.
	 *
	 * @param array $detected Detection state.
	 * @param array $settings Settings.
	 * @param array $checks   Check rows.
	 * @return array
	 */
	protected function build_report_lines( array $detected, array $settings, array $checks ) {
		if ( empty( $detected['active'] ) ) {
			return array(
				__( 'WooCommerce: Not detected during this report.', 'zignites-sentinel' ),
			);
		}

		$lines = array(
			__( 'WooCommerce: Active store detected.', 'zignites-sentinel' ),
			__( 'WooCommerce limitation: Sentinel does not roll back orders, payments, carts, database migrations, scheduled actions, or schema changes.', 'zignites-sentinel' ),
			! empty( $settings['maintenance_window_confirmed'] ) ? __( 'Maintenance window: Confirmed by operator.', 'zignites-sentinel' ) : __( 'Maintenance window: Not confirmed in Sentinel.', 'zignites-sentinel' ),
			! empty( $settings['external_db_backup_confirmed'] ) ? __( 'External DB backup: Confirmed by operator.', 'zignites-sentinel' ) : __( 'External DB backup: Not confirmed in Sentinel.', 'zignites-sentinel' ),
		);

		foreach ( $checks as $check ) {
			$lines[] = ( isset( $check['label'] ) ? (string) $check['label'] : '' ) . ': ' . ( isset( $check['message'] ) ? (string) $check['message'] : '' );
		}

		return $lines;
	}

	/**
	 * Detect cart item count when WooCommerce runtime is available.
	 *
	 * @return int|null
	 */
	protected function detect_cart_count() {
		if ( ! function_exists( 'WC' ) ) {
			return null;
		}

		$woocommerce = WC();

		if ( ! is_object( $woocommerce ) || empty( $woocommerce->cart ) || ! is_object( $woocommerce->cart ) || ! method_exists( $woocommerce->cart, 'get_cart_contents_count' ) ) {
			return null;
		}

		return max( 0, (int) $woocommerce->cart->get_cart_contents_count() );
	}

	/**
	 * Detect open order count when WooCommerce order APIs are available.
	 *
	 * @return int|null
	 */
	protected function detect_open_order_count() {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return null;
		}

		$orders = wc_get_orders(
			array(
				'status' => array( 'pending', 'processing', 'on-hold' ),
				'limit'  => 25,
				'return' => 'ids',
			)
		);

		return is_array( $orders ) ? count( $orders ) : null;
	}
}
