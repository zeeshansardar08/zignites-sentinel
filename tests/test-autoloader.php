<?php
/**
 * Focused tests for the plugin autoloader.
 */

use Zignites\Sentinel\Autoloader;

require_once __DIR__ . '/../includes/class-autoloader.php';

function znts_test_autoloader_normalizes_woocommerce_class_names() {
	$method = new ReflectionMethod( Autoloader::class, 'normalize_class_name' );
	$method->setAccessible( true );

	znts_assert_same( 'woocommerce-guardrails', $method->invoke( null, 'WooCommerceGuardrails' ), 'Autoloader should map WooCommerceGuardrails to the existing class-woocommerce-guardrails.php file.' );
}
