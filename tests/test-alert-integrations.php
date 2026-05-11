<?php
/**
 * Focused tests for alert integration delivery.
 */

require_once __DIR__ . '/bootstrap.php';

use Zignites\Sentinel\Integrations\AlertNotifier;

function znts_test_alert_notifier_normalizes_channels_events_and_links() {
	$notifier = new AlertNotifier();
	$settings = $notifier->normalize_settings(
		array(
			'enabled' => 1,
			'channels' => array(
				'generic' => array(
					'url' => 'https://hooks.example.test/generic',
				),
				'slack' => array(
					'url' => 'javascript:alert(1)',
				),
			),
			'events' => array(
				'checkpoint_created' => 1,
				'restore_failed'     => 0,
			),
			'external_links' => array(
				'sentry' => 'https://sentry.example.test/issues',
			),
		)
	);

	znts_assert_same( 1, $settings['enabled'], 'Alert settings should preserve enabled state.' );
	znts_assert_same( 'https://hooks.example.test/generic', $settings['channels']['generic']['url'], 'Generic webhook URL should be preserved.' );
	znts_assert_same( '', $settings['channels']['slack']['url'], 'Invalid webhook schemes should be rejected.' );
	znts_assert_same( 1, $settings['events']['checkpoint_created'], 'Enabled alert events should be preserved.' );
	znts_assert_same( 0, $settings['events']['restore_failed'], 'Disabled alert events should be preserved.' );
	znts_assert_same( 'https://sentry.example.test/issues', $settings['external_links']['sentry'], 'External monitoring links should be preserved.' );
}

function znts_test_alert_notifier_sends_generic_payload() {
	$notifier = new AlertNotifier();
	$result   = $notifier->notify_event(
		'restore_failed',
		array(
			'snapshot_id' => 42,
			'message'     => 'Restore failed during file replacement.',
		),
		array(
			'enabled' => 1,
			'channels' => array(
				'generic' => array(
					'url' => 'https://hooks.example.test/generic',
				),
			),
			'events' => array(
				'restore_failed' => 1,
			),
		)
	);

	$posted = isset( $GLOBALS['znts_test_last_http_post'] ) ? $GLOBALS['znts_test_last_http_post'] : array();
	$body   = isset( $posted['args']['body'] ) ? json_decode( $posted['args']['body'], true ) : array();

	unset( $GLOBALS['znts_test_last_http_post'] );

	znts_assert_same( 1, $result['sent'], 'Configured generic webhook should be sent.' );
	znts_assert_same( 'restore_failed', $body['event_type'], 'Generic webhook payload should include the event type.' );
	znts_assert_same( 42, $body['context']['snapshot_id'], 'Generic webhook payload should include event context.' );
}

function znts_test_alert_notifier_formats_discord_adapter_payload() {
	$notifier = new AlertNotifier();
	$result   = $notifier->notify_event(
		'checkpoint_created',
		array(
			'message' => 'Checkpoint ready.',
		),
		array(
			'enabled' => 1,
			'channels' => array(
				'discord' => array(
					'url' => 'https://discord.example.test/webhook',
				),
			),
			'events' => array(
				'checkpoint_created' => 1,
			),
		)
	);

	$posted = isset( $GLOBALS['znts_test_last_http_post'] ) ? $GLOBALS['znts_test_last_http_post'] : array();
	$body   = isset( $posted['args']['body'] ) ? json_decode( $posted['args']['body'], true ) : array();

	unset( $GLOBALS['znts_test_last_http_post'] );

	znts_assert_same( 1, $result['sent'], 'Configured Discord webhook should be sent.' );
	znts_assert_true( isset( $body['content'] ), 'Discord adapter should send a content payload.' );
	znts_assert_true( false !== strpos( $body['content'], 'Checkpoint created' ), 'Discord payload should include the event title.' );
}

function znts_test_alert_notifier_skips_disabled_events() {
	$notifier = new AlertNotifier();
	$result   = $notifier->notify_event(
		'health_check_failed',
		array(),
		array(
			'enabled' => 1,
			'channels' => array(
				'generic' => array(
					'url' => 'https://hooks.example.test/generic',
				),
			),
			'events' => array(
				'health_check_failed' => 0,
			),
		)
	);

	znts_assert_true( ! empty( $result['skipped'] ), 'Disabled alert events should be skipped.' );
	znts_assert_same( 0, $result['sent'], 'Skipped alert events should not send delivery attempts.' );
}
