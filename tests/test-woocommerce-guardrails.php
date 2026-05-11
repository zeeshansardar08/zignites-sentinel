<?php
/**
 * Focused tests for WooCommerce update guardrails.
 */

require_once __DIR__ . '/bootstrap.php';

use Zignites\Sentinel\Diagnostics\WooCommerceGuardrails;

if ( ! function_exists( 'WC' ) ) {
	function WC() {
		return isset( $GLOBALS['znts_test_wc_runtime'] ) ? $GLOBALS['znts_test_wc_runtime'] : null;
	}
}

if ( ! function_exists( 'wc_get_orders' ) ) {
	function wc_get_orders( $args = array() ) {
		return isset( $GLOBALS['znts_test_wc_orders'] ) ? $GLOBALS['znts_test_wc_orders'] : array();
	}
}

class ZNTS_Test_WC_Cart {
	public $count = 0;

	public function __construct( $count ) {
		$this->count = (int) $count;
	}

	public function get_cart_contents_count() {
		return $this->count;
	}
}

function znts_test_woocommerce_guardrails_detects_active_store_from_plugin_option() {
	$GLOBALS['znts_test_options']['active_plugins'] = array( 'woocommerce/woocommerce.php' );
	$GLOBALS['znts_test_wc_runtime'] = (object) array(
		'cart' => new ZNTS_Test_WC_Cart( 2 ),
	);
	$GLOBALS['znts_test_wc_orders'] = array( 1001, 1002, 1003 );

	$guardrails = new WooCommerceGuardrails();
	$state      = $guardrails->build_state(
		array(
			'maintenance_window_confirmed' => 0,
			'external_db_backup_confirmed' => 0,
		)
	);

	unset( $GLOBALS['znts_test_options']['active_plugins'], $GLOBALS['znts_test_wc_runtime'], $GLOBALS['znts_test_wc_orders'] );

	znts_assert_same( true, $state['active'], 'WooCommerce guardrails should detect an active WooCommerce plugin.' );
	znts_assert_same( true, $state['safe_mode_on'], 'WooCommerce safe update mode should be enabled by default.' );
	znts_assert_same( 2, $state['detected']['cart_count'], 'WooCommerce guardrails should expose visible cart item count.' );
	znts_assert_same( 3, $state['detected']['open_order_count'], 'WooCommerce guardrails should expose open order count.' );
	znts_assert_true( false !== strpos( implode( ' ', $state['warnings'] ), 'database backup' ), 'WooCommerce guardrails should encourage external DB backup confirmation.' );
}

function znts_test_woocommerce_guardrails_report_lines_state_limitations() {
	$GLOBALS['znts_test_options']['active_plugins'] = array( 'woocommerce/woocommerce.php' );
	$GLOBALS['znts_test_wc_runtime'] = (object) array(
		'cart' => new ZNTS_Test_WC_Cart( 0 ),
	);
	$GLOBALS['znts_test_wc_orders'] = array();

	$guardrails = new WooCommerceGuardrails();
	$state      = $guardrails->build_state(
		array(
			'maintenance_window_confirmed' => 1,
			'external_db_backup_confirmed' => 1,
		)
	);

	unset( $GLOBALS['znts_test_options']['active_plugins'], $GLOBALS['znts_test_wc_runtime'], $GLOBALS['znts_test_wc_orders'] );

	$report = implode( "\n", $state['report_lines'] );

	znts_assert_true( false !== strpos( $report, 'WooCommerce: Active store detected.' ), 'WooCommerce report lines should identify active stores.' );
	znts_assert_true( false !== strpos( $report, 'does not roll back orders, payments' ), 'WooCommerce report lines should state rollback limitations.' );
	znts_assert_true( false !== strpos( $report, 'External DB backup: Confirmed' ), 'WooCommerce report lines should include DB backup acknowledgement.' );
}

function znts_test_woocommerce_guardrails_stays_quiet_when_not_active() {
	$GLOBALS['znts_test_options']['active_plugins'] = array();
	$GLOBALS['znts_test_wc_orders'] = array();

	$guardrails = new WooCommerceGuardrails();
	$state      = $guardrails->build_state();

	unset( $GLOBALS['znts_test_options']['active_plugins'], $GLOBALS['znts_test_wc_orders'] );

	znts_assert_same( false, $state['active'], 'WooCommerce guardrails should not activate without WooCommerce.' );
	znts_assert_same( array(), $state['warnings'], 'Inactive WooCommerce guardrails should not produce warnings.' );
}
