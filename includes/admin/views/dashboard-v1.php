<?php
/**
 * Simplified dashboard view.
 *
 * @package ZignitesSentinel
 */

defined( 'ABSPATH' ) || exit;

$site_status_card      = isset( $view_data['site_status_card'] ) && is_array( $view_data['site_status_card'] ) ? $view_data['site_status_card'] : array();
$snapshot_status_index = isset( $view_data['snapshot_status_index'] ) && is_array( $view_data['snapshot_status_index'] ) ? $view_data['snapshot_status_index'] : array();
$recent_snapshots      = isset( $view_data['recent_snapshots'] ) && is_array( $view_data['recent_snapshots'] ) ? $view_data['recent_snapshots'] : array();
$recent_logs           = isset( $view_data['recent_logs'] ) && is_array( $view_data['recent_logs'] ) ? $view_data['recent_logs'] : array();
$async_jobs            = isset( $view_data['async_jobs'] ) && is_array( $view_data['async_jobs'] ) ? $view_data['async_jobs'] : array();
$artifact_storage      = isset( $view_data['artifact_storage'] ) && is_array( $view_data['artifact_storage'] ) ? $view_data['artifact_storage'] : array();
$alert_integrations    = isset( $view_data['alert_integrations'] ) && is_array( $view_data['alert_integrations'] ) ? $view_data['alert_integrations'] : array();
$woocommerce_guardrails = isset( $view_data['woocommerce_guardrails'] ) && is_array( $view_data['woocommerce_guardrails'] ) ? $view_data['woocommerce_guardrails'] : array();
$notice                = isset( $view_data['notice'] ) && is_array( $view_data['notice'] ) ? $view_data['notice'] : array();
$latest_snapshot       = ! empty( $recent_snapshots[0] ) ? $recent_snapshots[0] : array();
$latest_snapshot_state = ( ! empty( $latest_snapshot['id'] ) && isset( $snapshot_status_index[ (int) $latest_snapshot['id'] ] ) ) ? $snapshot_status_index[ (int) $latest_snapshot['id'] ] : array();
$primary_action        = isset( $site_status_card['primary_action'] ) && is_array( $site_status_card['primary_action'] ) ? $site_status_card['primary_action'] : array();
$primary_action_title  = isset( $primary_action['title'] ) ? (string) $primary_action['title'] : __( 'Create a safe-update checkpoint before updates.', 'zignites-sentinel' );
$primary_action_note   = isset( $primary_action['description'] ) ? (string) $primary_action['description'] : __( 'Capture a checkpoint of your active plugins and theme before updating anything.', 'zignites-sentinel' );
$primary_action_label  = isset( $primary_action['button_label'] ) ? (string) $primary_action['button_label'] : __( 'Open Before Update', 'zignites-sentinel' );
$primary_action_url    = isset( $primary_action['url'] ) ? (string) $primary_action['url'] : '';
$help_panels           = isset( $view_data['help_panels'] ) && is_array( $view_data['help_panels'] ) ? $view_data['help_panels'] : array();
$positioning_note      = isset( $view_data['positioning_note'] ) && is_array( $view_data['positioning_note'] ) ? $view_data['positioning_note'] : array();
$admin_page_url        = \Zignites\Sentinel\Admin\znts_admin_url( 'admin.php' );
$admin_post_url        = \Zignites\Sentinel\Admin\znts_admin_url( 'admin-post.php' );
$first_run_cta_url     = add_query_arg(
	array(
		'page' => 'zignites-sentinel-update-readiness',
	),
	$admin_page_url
);
?>
<div class="wrap znts-admin-page">
	<div class="znts-page-header">
		<h1><?php echo esc_html__( 'Zignites Sentinel', 'zignites-sentinel' ); ?></h1>
		<p class="znts-page-intro"><?php echo esc_html__( 'Safe Update Checkpoints and Rollback for WordPress plugin/theme code changes.', 'zignites-sentinel' ); ?></p>
	</div>

	<?php if ( ! empty( $notice ) ) : ?>
		<div class="notice notice-<?php echo esc_attr( isset( $notice['type'] ) ? $notice['type'] : 'info' ); ?>">
			<p><?php echo esc_html( isset( $notice['message'] ) ? $notice['message'] : '' ); ?></p>
		</div>
	<?php endif; ?>

	<section class="znts-summary-hero">
		<span class="znts-eyebrow"><?php echo esc_html__( 'Before Update', 'zignites-sentinel' ); ?></span>
		<?php if ( empty( $site_status_card ) ) : ?>
			<h2 class="znts-hero-title"><?php echo esc_html__( 'Create Your First Checkpoint', 'zignites-sentinel' ); ?></h2>
			<p class="znts-hero-subtitle"><?php echo esc_html__( 'Before updating your site, save a checkpoint of the active theme and plugins so you can restore that code layer if an update breaks it.', 'zignites-sentinel' ); ?></p>
			<div class="znts-quick-actions znts-dashboard-actions">
				<p><a class="button button-primary" href="<?php echo esc_url( $first_run_cta_url ); ?>"><?php echo esc_html__( 'Create Your First Checkpoint', 'zignites-sentinel' ); ?></a></p>
				<p><a class="button button-secondary" href="<?php echo esc_url( add_query_arg( array( 'page' => 'zignites-sentinel-event-logs' ), $admin_page_url ) ); ?>"><?php echo esc_html__( 'Open History', 'zignites-sentinel' ); ?></a></p>
			</div>
		<?php else : ?>
			<div class="znts-readiness-row">
				<span class="znts-pill znts-pill-<?php echo esc_attr( isset( $site_status_card['badge'] ) ? $site_status_card['badge'] : 'info' ); ?>">
					<?php echo esc_html( isset( $site_status_card['label'] ) ? $site_status_card['label'] : '' ); ?>
				</span>
				<?php if ( ! empty( $site_status_card['latest_snapshot']['label'] ) ) : ?>
					<span><?php echo esc_html( $site_status_card['latest_snapshot']['label'] ); ?></span>
				<?php endif; ?>
			</div>
			<h2 class="znts-hero-title"><?php echo esc_html( $primary_action_title ); ?></h2>
			<p class="znts-hero-subtitle"><?php echo esc_html( $primary_action_note ); ?></p>
			<div class="znts-quick-actions znts-dashboard-actions">
				<?php if ( '' !== $primary_action_url ) : ?>
					<p><a class="button button-primary" href="<?php echo esc_url( $primary_action_url ); ?>"><?php echo esc_html( $primary_action_label ); ?></a></p>
				<?php endif; ?>
				<?php if ( ! empty( $site_status_card['activity_url'] ) ) : ?>
					<p><a class="button button-secondary" href="<?php echo esc_url( $site_status_card['activity_url'] ); ?>"><?php echo esc_html__( 'Open History', 'zignites-sentinel' ); ?></a></p>
				<?php endif; ?>
			</div>
		<?php endif; ?>
		<div class="znts-flow-note">
			<strong><?php echo esc_html__( 'Restore boundary', 'zignites-sentinel' ); ?></strong>
			<span><?php echo esc_html__( 'Sentinel restores the active theme and active plugins only. It does not restore the database, uploads/media, WordPress core, or WooCommerce order/payment state, and it does not perform malware cleanup. Use dedicated backup and security tools for those needs.', 'zignites-sentinel' ); ?></span>
		</div>
		<div class="znts-flow-note">
			<strong><?php echo esc_html__( 'Best fit', 'zignites-sentinel' ); ?></strong>
			<span><?php echo esc_html__( 'Built for agencies, freelancers, and production maintainers who want a safe-update checkpoint before risky plugin or theme updates.', 'zignites-sentinel' ); ?></span>
		</div>
	</section>

	<div class="znts-admin-grid znts-readiness-grid">
		<?php if ( ! empty( $help_panels ) ) : ?>
			<section class="znts-card znts-card-full znts-card-flat">
				<h2><?php echo esc_html__( 'Start Here', 'zignites-sentinel' ); ?></h2>
				<div class="znts-summary-strip">
					<?php foreach ( $help_panels as $panel ) : ?>
						<div class="znts-summary-item">
							<span><?php echo esc_html( isset( $panel['title'] ) ? $panel['title'] : '' ); ?></span>
							<p><?php echo esc_html( isset( $panel['body'] ) ? $panel['body'] : '' ); ?></p>
						</div>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endif; ?>

		<?php if ( ! empty( $positioning_note ) ) : ?>
			<section class="znts-card znts-card-full znts-card-flat">
				<h2><?php echo esc_html( isset( $positioning_note['title'] ) ? $positioning_note['title'] : __( 'What Sentinel is designed to do', 'zignites-sentinel' ) ); ?></h2>
				<p><?php echo esc_html( isset( $positioning_note['body'] ) ? $positioning_note['body'] : '' ); ?></p>
			</section>
		<?php endif; ?>

		<?php if ( ! empty( $async_jobs ) ) : ?>
			<section class="znts-card znts-card-full znts-card-flat">
				<h2><?php echo esc_html__( 'Background Jobs', 'zignites-sentinel' ); ?></h2>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php echo esc_html__( 'Job', 'zignites-sentinel' ); ?></th>
							<th><?php echo esc_html__( 'Status', 'zignites-sentinel' ); ?></th>
							<th><?php echo esc_html__( 'Progress', 'zignites-sentinel' ); ?></th>
							<th><?php echo esc_html__( 'Updated', 'zignites-sentinel' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( array_slice( $async_jobs, 0, 5 ) as $job ) : ?>
							<?php $progress = isset( $job['progress'] ) && is_array( $job['progress'] ) ? $job['progress'] : array(); ?>
							<tr>
								<td><?php echo esc_html( isset( $job['type'] ) ? str_replace( '_', ' ', $job['type'] ) : '' ); ?></td>
								<td>
									<span class="znts-pill znts-pill-<?php echo esc_attr( isset( $job['status'] ) && 'failed' === $job['status'] ? 'error' : ( isset( $job['status'] ) && 'completed' === $job['status'] ? 'success' : 'info' ) ); ?>">
										<?php echo esc_html( isset( $job['status'] ) ? ucfirst( (string) $job['status'] ) : '' ); ?>
									</span>
								</td>
								<td><?php echo esc_html( isset( $job['error'] ) && '' !== $job['error'] ? $job['error'] : ( isset( $progress['message'] ) ? $progress['message'] : '' ) ); ?></td>
								<td><?php echo esc_html( isset( $job['updated_at'] ) ? $job['updated_at'] : '' ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</section>
		<?php endif; ?>

		<?php if ( ! empty( $artifact_storage ) ) : ?>
			<section class="znts-card znts-card-full znts-card-flat">
				<div class="znts-readiness-row">
					<span class="znts-pill znts-pill-<?php echo esc_attr( isset( $artifact_storage['badge'] ) ? $artifact_storage['badge'] : 'warning' ); ?>">
						<?php echo esc_html( isset( $artifact_storage['label'] ) ? $artifact_storage['label'] : __( 'Needs host review', 'zignites-sentinel' ) ); ?>
					</span>
				</div>
				<h2><?php echo esc_html__( 'Artifact Storage Exposure', 'zignites-sentinel' ); ?></h2>
				<p><?php echo esc_html( isset( $artifact_storage['message'] ) ? $artifact_storage['message'] : '' ); ?></p>
				<?php if ( ! empty( $artifact_storage['warning'] ) ) : ?>
					<div class="znts-flow-note">
						<strong><?php echo esc_html__( 'Sensitive artifacts', 'zignites-sentinel' ); ?></strong>
						<span><?php echo esc_html( $artifact_storage['warning'] ); ?></span>
					</div>
				<?php endif; ?>
			</section>
		<?php endif; ?>

		<?php if ( ! empty( $woocommerce_guardrails['active'] ) ) : ?>
			<section class="znts-card znts-card-full znts-card-flat">
				<div class="znts-readiness-row">
					<span class="znts-pill znts-pill-warning"><?php echo esc_html( isset( $woocommerce_guardrails['label'] ) ? $woocommerce_guardrails['label'] : __( 'WooCommerce detected', 'zignites-sentinel' ) ); ?></span>
				</div>
				<h2><?php echo esc_html__( 'WooCommerce Guardrails', 'zignites-sentinel' ); ?></h2>
				<p><?php echo esc_html( isset( $woocommerce_guardrails['message'] ) ? $woocommerce_guardrails['message'] : '' ); ?></p>
				<p><a href="<?php echo esc_url( add_query_arg( array( 'page' => 'zignites-sentinel-update-readiness' ), $admin_page_url ) . '#znts-woocommerce-guardrails' ); ?>"><?php echo esc_html__( 'Review WooCommerce Safe Update Mode', 'zignites-sentinel' ); ?></a></p>
			</section>
		<?php endif; ?>

		<section id="znts-alert-integrations" class="znts-card znts-card-full znts-card-flat">
			<?php
			$alert_settings = isset( $alert_integrations['settings'] ) && is_array( $alert_integrations['settings'] ) ? $alert_integrations['settings'] : array();
			$alert_summary  = isset( $alert_integrations['summary'] ) && is_array( $alert_integrations['summary'] ) ? $alert_integrations['summary'] : array();
			$channels       = isset( $alert_settings['channels'] ) && is_array( $alert_settings['channels'] ) ? $alert_settings['channels'] : array();
			$events         = isset( $alert_settings['events'] ) && is_array( $alert_settings['events'] ) ? $alert_settings['events'] : array();
			$event_labels   = isset( $alert_summary['event_labels'] ) && is_array( $alert_summary['event_labels'] ) ? $alert_summary['event_labels'] : array();
			$external_links = isset( $alert_settings['external_links'] ) && is_array( $alert_settings['external_links'] ) ? $alert_settings['external_links'] : array();
			$last_test      = isset( $alert_summary['last_test_result'] ) && is_array( $alert_summary['last_test_result'] ) ? $alert_summary['last_test_result'] : array();
			?>
			<div class="znts-readiness-row">
				<span class="znts-pill znts-pill-<?php echo ! empty( $alert_summary['enabled'] ) && ! empty( $alert_summary['channel_count'] ) ? 'success' : 'warning'; ?>">
					<?php echo esc_html( ! empty( $alert_summary['enabled'] ) && ! empty( $alert_summary['channel_count'] ) ? __( 'Alerts active', 'zignites-sentinel' ) : __( 'Alerts not active', 'zignites-sentinel' ) ); ?>
				</span>
				<span><?php echo esc_html( sprintf( __( '%d channels configured', 'zignites-sentinel' ), isset( $alert_summary['channel_count'] ) ? (int) $alert_summary['channel_count'] : 0 ) ); ?></span>
			</div>
			<h2><?php echo esc_html__( 'Alerts and Integrations', 'zignites-sentinel' ); ?></h2>
			<form method="post" action="<?php echo esc_url( $admin_post_url ); ?>">
				<input type="hidden" name="action" value="znts_save_alert_integrations" />
				<?php wp_nonce_field( 'znts_save_alert_integrations_action' ); ?>
				<p>
					<label><input type="checkbox" name="alerts_enabled" value="1" <?php echo ! empty( $alert_settings['enabled'] ) ? 'checked="checked"' : ''; ?> /> <?php echo esc_html__( 'Enable operation alerts', 'zignites-sentinel' ); ?></label>
				</p>
				<div class="znts-summary-strip">
					<div class="znts-summary-item">
						<span><?php echo esc_html__( 'Generic webhook', 'zignites-sentinel' ); ?></span>
						<p><input type="url" name="generic_webhook_url" class="regular-text" value="<?php echo esc_attr( isset( $channels['generic']['url'] ) ? $channels['generic']['url'] : '' ); ?>" /></p>
					</div>
					<div class="znts-summary-item">
						<span><?php echo esc_html__( 'Slack', 'zignites-sentinel' ); ?></span>
						<p><input type="url" name="slack_webhook_url" class="regular-text" value="<?php echo esc_attr( isset( $channels['slack']['url'] ) ? $channels['slack']['url'] : '' ); ?>" /></p>
					</div>
					<div class="znts-summary-item">
						<span><?php echo esc_html__( 'Teams', 'zignites-sentinel' ); ?></span>
						<p><input type="url" name="teams_webhook_url" class="regular-text" value="<?php echo esc_attr( isset( $channels['teams']['url'] ) ? $channels['teams']['url'] : '' ); ?>" /></p>
					</div>
					<div class="znts-summary-item">
						<span><?php echo esc_html__( 'Discord', 'zignites-sentinel' ); ?></span>
						<p><input type="url" name="discord_webhook_url" class="regular-text" value="<?php echo esc_attr( isset( $channels['discord']['url'] ) ? $channels['discord']['url'] : '' ); ?>" /></p>
					</div>
					<div class="znts-summary-item">
						<span><?php echo esc_html__( 'Telegram', 'zignites-sentinel' ); ?></span>
						<p><input type="url" name="telegram_webhook_url" class="regular-text" value="<?php echo esc_attr( isset( $channels['telegram']['url'] ) ? $channels['telegram']['url'] : '' ); ?>" /></p>
						<p><input type="text" name="telegram_chat_id" class="regular-text" value="<?php echo esc_attr( isset( $channels['telegram']['chat_id'] ) ? $channels['telegram']['chat_id'] : '' ); ?>" placeholder="<?php echo esc_attr__( 'Chat ID', 'zignites-sentinel' ); ?>" /></p>
					</div>
				</div>
				<p><strong><?php echo esc_html__( 'Alert events', 'zignites-sentinel' ); ?></strong></p>
				<p>
					<?php foreach ( $event_labels as $event_key => $event_label ) : ?>
						<label><input type="checkbox" name="alert_events[<?php echo esc_attr( $event_key ); ?>]" value="1" <?php echo ! empty( $events[ $event_key ] ) ? 'checked="checked"' : ''; ?> /> <?php echo esc_html( $event_label ); ?></label><br />
					<?php endforeach; ?>
				</p>
				<p><strong><?php echo esc_html__( 'Monitoring links', 'zignites-sentinel' ); ?></strong></p>
				<div class="znts-summary-strip">
					<div class="znts-summary-item">
						<span><?php echo esc_html__( 'UptimeRobot', 'zignites-sentinel' ); ?></span>
						<p><input type="url" name="uptime_robot_url" class="regular-text" value="<?php echo esc_attr( isset( $external_links['uptime_robot'] ) ? $external_links['uptime_robot'] : '' ); ?>" /></p>
					</div>
					<div class="znts-summary-item">
						<span><?php echo esc_html__( 'Sentry', 'zignites-sentinel' ); ?></span>
						<p><input type="url" name="sentry_url" class="regular-text" value="<?php echo esc_attr( isset( $external_links['sentry'] ) ? $external_links['sentry'] : '' ); ?>" /></p>
					</div>
					<div class="znts-summary-item">
						<span><?php echo esc_html__( 'New Relic', 'zignites-sentinel' ); ?></span>
						<p><input type="url" name="new_relic_url" class="regular-text" value="<?php echo esc_attr( isset( $external_links['new_relic'] ) ? $external_links['new_relic'] : '' ); ?>" /></p>
					</div>
					<div class="znts-summary-item">
						<span><?php echo esc_html__( 'Datadog', 'zignites-sentinel' ); ?></span>
						<p><input type="url" name="datadog_url" class="regular-text" value="<?php echo esc_attr( isset( $external_links['datadog'] ) ? $external_links['datadog'] : '' ); ?>" /></p>
					</div>
				</div>
				<?php submit_button( __( 'Save Integrations', 'zignites-sentinel' ), 'secondary', 'submit', false ); ?>
			</form>
			<form method="post" action="<?php echo esc_url( $admin_post_url ); ?>">
				<input type="hidden" name="action" value="znts_send_test_alert" />
				<?php wp_nonce_field( 'znts_send_test_alert_action' ); ?>
				<?php submit_button( __( 'Send Test Alert', 'zignites-sentinel' ), 'secondary', 'submit', false ); ?>
			</form>
			<?php if ( ! empty( $last_test ) ) : ?>
				<div class="znts-flow-note">
					<strong><?php echo esc_html__( 'Last test', 'zignites-sentinel' ); ?></strong>
					<span><?php echo esc_html( sprintf( __( '%1$d sent, %2$d failed', 'zignites-sentinel' ), isset( $last_test['sent'] ) ? (int) $last_test['sent'] : 0, isset( $last_test['failed'] ) ? (int) $last_test['failed'] : 0 ) ); ?></span>
				</div>
			<?php endif; ?>
		</section>

		<section class="znts-card znts-card-full znts-card-primary">
			<h2><?php echo esc_html__( 'Latest Checkpoint', 'zignites-sentinel' ); ?></h2>
			<?php if ( empty( $latest_snapshot ) ) : ?>
				<div class="znts-empty-state">
					<strong><?php echo esc_html__( 'No checkpoints have been saved yet.', 'zignites-sentinel' ); ?></strong>
					<p><?php echo esc_html__( 'Create a checkpoint before making risky plugin or theme updates.', 'zignites-sentinel' ); ?></p>
					<p><a href="<?php echo esc_url( $first_run_cta_url ); ?>"><?php echo esc_html__( 'Open Before Update', 'zignites-sentinel' ); ?></a></p>
				</div>
			<?php else : ?>
				<div class="znts-snapshot-overview">
					<div class="znts-overview-block">
						<strong><?php echo esc_html__( 'Checkpoint', 'zignites-sentinel' ); ?></strong>
						<span><?php echo esc_html( $latest_snapshot['label'] ); ?></span>
					</div>
					<div class="znts-overview-block">
						<strong><?php echo esc_html__( 'Created', 'zignites-sentinel' ); ?></strong>
						<span><?php echo esc_html( $latest_snapshot['created_at'] ); ?></span>
					</div>
					<div class="znts-overview-block">
						<strong><?php echo esc_html__( 'Restore state', 'zignites-sentinel' ); ?></strong>
						<span><?php echo esc_html( ! empty( $latest_snapshot_state['restore_ready'] ) ? __( 'Ready to restore', 'zignites-sentinel' ) : __( 'Needs validation', 'zignites-sentinel' ) ); ?></span>
					</div>
				</div>
				<?php if ( ! empty( $latest_snapshot_state['status_badges'] ) ) : ?>
					<div class="znts-badge-row">
						<?php foreach ( $latest_snapshot_state['status_badges'] as $badge ) : ?>
							<span class="znts-pill znts-pill-<?php echo esc_attr( isset( $badge['badge'] ) ? $badge['badge'] : 'info' ); ?>">
								<?php echo esc_html( isset( $badge['label'] ) ? $badge['label'] : '' ); ?>
							</span>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
				<p><a href="<?php echo esc_url( add_query_arg( array( 'page' => 'zignites-sentinel-update-readiness', 'snapshot_id' => (int) $latest_snapshot['id'] ), $admin_page_url ) ); ?>"><?php echo esc_html__( 'Open in Before Update', 'zignites-sentinel' ); ?></a></p>
			<?php endif; ?>
		</section>

		<section class="znts-card znts-card-full znts-card-flat">
			<h2><?php echo esc_html__( 'Recent History', 'zignites-sentinel' ); ?></h2>
			<?php if ( empty( $recent_logs ) ) : ?>
				<div class="znts-empty-state">
					<strong><?php echo esc_html__( 'No recent checkpoint or restore history yet.', 'zignites-sentinel' ); ?></strong>
					<p><?php echo esc_html__( 'History will start filling in after you create checkpoints, run restores, or roll back the last restore.', 'zignites-sentinel' ); ?></p>
					<p><a href="<?php echo esc_url( add_query_arg( array( 'page' => 'zignites-sentinel-event-logs' ), $admin_page_url ) ); ?>"><?php echo esc_html__( 'Open Full History', 'zignites-sentinel' ); ?></a></p>
				</div>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php echo esc_html__( 'Time', 'zignites-sentinel' ); ?></th>
							<th><?php echo esc_html__( 'Event', 'zignites-sentinel' ); ?></th>
							<th><?php echo esc_html__( 'Message', 'zignites-sentinel' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( array_slice( $recent_logs, 0, 5 ) as $log ) : ?>
							<tr>
								<td><?php echo esc_html( isset( $log['created_at'] ) ? $log['created_at'] : '' ); ?></td>
								<td><?php echo esc_html( isset( $log['event_type'] ) ? $log['event_type'] : '' ); ?></td>
								<td><?php echo esc_html( isset( $log['message'] ) ? $log['message'] : '' ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<p><a href="<?php echo esc_url( add_query_arg( array( 'page' => 'zignites-sentinel-event-logs' ), $admin_page_url ) ); ?>"><?php echo esc_html__( 'Open Full History', 'zignites-sentinel' ); ?></a></p>
			<?php endif; ?>
		</section>

		<section class="znts-card znts-card-full znts-card-flat">
			<h2><?php echo esc_html__( 'Saved Checkpoints', 'zignites-sentinel' ); ?></h2>
			<?php if ( empty( $recent_snapshots ) ) : ?>
				<div class="znts-empty-state">
					<strong><?php echo esc_html__( 'No saved checkpoints yet.', 'zignites-sentinel' ); ?></strong>
					<p><?php echo esc_html__( 'Use Before Update to save a safe-update checkpoint before plugin or theme updates.', 'zignites-sentinel' ); ?></p>
					<p><a href="<?php echo esc_url( $first_run_cta_url ); ?>"><?php echo esc_html__( 'Create a Checkpoint', 'zignites-sentinel' ); ?></a></p>
				</div>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php echo esc_html__( 'Checkpoint', 'zignites-sentinel' ); ?></th>
							<th><?php echo esc_html__( 'Created', 'zignites-sentinel' ); ?></th>
							<th><?php echo esc_html__( 'Actions', 'zignites-sentinel' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( array_slice( $recent_snapshots, 0, 5 ) as $snapshot ) : ?>
							<tr>
								<td><?php echo esc_html( $snapshot['label'] ); ?></td>
								<td><?php echo esc_html( $snapshot['created_at'] ); ?></td>
								<td><a href="<?php echo esc_url( add_query_arg( array( 'page' => 'zignites-sentinel-update-readiness', 'snapshot_id' => (int) $snapshot['id'] ), $admin_page_url ) ); ?>"><?php echo esc_html__( 'Restore / Rollback', 'zignites-sentinel' ); ?></a></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</section>
	</div>
</div>
