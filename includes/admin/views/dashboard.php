<?php
/**
 * Dashboard view.
 *
 * @package ZignitesSentinel
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap znts-admin-page">
	<h1><?php echo esc_html__( 'Zignites Sentinel', 'zignites-sentinel' ); ?></h1>
	<?php $site_status_card = isset( $view_data['site_status_card'] ) && is_array( $view_data['site_status_card'] ) ? $view_data['site_status_card'] : array(); ?>
	<?php $restore_health_strip = isset( $view_data['restore_health_strip'] ) && is_array( $view_data['restore_health_strip'] ) ? $view_data['restore_health_strip'] : array(); ?>
	<?php $snapshot_status_index = isset( $view_data['snapshot_status_index'] ) && is_array( $view_data['snapshot_status_index'] ) ? $view_data['snapshot_status_index'] : array(); ?>

	<div class="znts-admin-grid">
		<section class="znts-card">
			<div class="znts-score-header">
				<div>
					<h2><?php echo esc_html__( 'Site Stability Score', 'zignites-sentinel' ); ?></h2>
					<p><?php echo esc_html( $view_data['health_score']['summary'] ); ?></p>
				</div>
				<div class="znts-score-badge znts-score-<?php echo esc_attr( $view_data['health_score']['label'] ); ?>">
					<span class="znts-score-value"><?php echo esc_html( (string) $view_data['health_score']['score'] ); ?></span>
					<span class="znts-score-label"><?php echo esc_html( ucfirst( $view_data['health_score']['label'] ) ); ?></span>
				</div>
			</div>
			<table class="widefat striped">
				<tbody>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Plugin version', 'zignites-sentinel' ); ?></th>
						<td><?php echo esc_html( $view_data['plugin_version'] ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Database version', 'zignites-sentinel' ); ?></th>
						<td><?php echo esc_html( $view_data['db_version'] ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Logs table', 'zignites-sentinel' ); ?></th>
						<td><code><?php echo esc_html( $view_data['logs_table'] ); ?></code></td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Conflicts table', 'zignites-sentinel' ); ?></th>
						<td><code><?php echo esc_html( $view_data['conflicts_table'] ); ?></code></td>
					</tr>
				</tbody>
			</table>
		</section>

		<section class="znts-card">
			<h2><?php echo esc_html__( 'Environment Snapshot', 'zignites-sentinel' ); ?></h2>
			<table class="widefat striped">
				<tbody>
					<tr>
						<th scope="row"><?php echo esc_html__( 'WordPress', 'zignites-sentinel' ); ?></th>
						<td><?php echo esc_html( $view_data['wordpress'] ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'PHP', 'zignites-sentinel' ); ?></th>
						<td><?php echo esc_html( $view_data['php'] ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Site URL', 'zignites-sentinel' ); ?></th>
						<td><?php echo esc_html( $view_data['site_url'] ); ?></td>
					</tr>
				</tbody>
			</table>
		</section>

		<section class="znts-card">
			<h2><?php echo esc_html__( 'Recent Conflict Signals', 'zignites-sentinel' ); ?></h2>
			<?php if ( empty( $view_data['recent_conflicts'] ) ) : ?>
				<p><?php echo esc_html__( 'No conflict signals have been recorded yet.', 'zignites-sentinel' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php echo esc_html__( 'Severity', 'zignites-sentinel' ); ?></th>
							<th><?php echo esc_html__( 'Summary', 'zignites-sentinel' ); ?></th>
							<th><?php echo esc_html__( 'Source', 'zignites-sentinel' ); ?></th>
							<th><?php echo esc_html__( 'Seen', 'zignites-sentinel' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $view_data['recent_conflicts'] as $conflict ) : ?>
							<tr>
								<td>
									<span class="znts-pill znts-pill-<?php echo esc_attr( $conflict['severity'] ); ?>">
										<?php echo esc_html( ucfirst( $conflict['severity'] ) ); ?>
									</span>
								</td>
								<td><?php echo esc_html( $conflict['summary'] ); ?></td>
								<td><?php echo esc_html( $conflict['source_a'] ); ?></td>
								<td><?php echo esc_html( $conflict['last_seen_at'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</section>

		<section class="znts-card">
			<h2><?php echo esc_html__( 'Site Status', 'zignites-sentinel' ); ?></h2>
			<?php if ( empty( $site_status_card ) ) : ?>
				<p><?php echo esc_html__( 'Site status is not available yet.', 'zignites-sentinel' ); ?></p>
			<?php else : ?>
				<div class="znts-readiness-row">
					<span class="znts-pill znts-pill-<?php echo esc_attr( isset( $site_status_card['badge'] ) ? $site_status_card['badge'] : 'info' ); ?>">
						<?php echo esc_html( isset( $site_status_card['label'] ) ? $site_status_card['label'] : '' ); ?>
					</span>
					<span>
						<?php
						echo esc_html(
							! empty( $site_status_card['latest_snapshot']['label'] )
								? (string) $site_status_card['latest_snapshot']['label']
								: __( 'No snapshot selected yet', 'zignites-sentinel' )
						);
						?>
					</span>
				</div>
				<p><?php echo esc_html( isset( $site_status_card['recommended_action'] ) ? $site_status_card['recommended_action'] : '' ); ?></p>
				<ul class="znts-list znts-signal-list">
					<?php foreach ( isset( $site_status_card['signals'] ) && is_array( $site_status_card['signals'] ) ? $site_status_card['signals'] : array() as $signal ) : ?>
						<li><?php echo esc_html( $signal ); ?></li>
					<?php endforeach; ?>
				</ul>
				<p class="znts-card-note">
					<?php echo esc_html__( 'Recommended action', 'zignites-sentinel' ); ?>:
					<?php echo esc_html( isset( $site_status_card['recommended_action'] ) ? $site_status_card['recommended_action'] : '' ); ?>
				</p>
				<div class="znts-dashboard-links">
					<?php if ( ! empty( $site_status_card['detail_url'] ) ) : ?>
						<p><a href="<?php echo esc_url( $site_status_card['detail_url'] ); ?>"><?php echo esc_html__( 'Open Update Readiness', 'zignites-sentinel' ); ?></a></p>
					<?php endif; ?>
					<?php if ( ! empty( $site_status_card['activity_url'] ) ) : ?>
						<p><a href="<?php echo esc_url( $site_status_card['activity_url'] ); ?>"><?php echo esc_html__( 'Open Snapshot Activity', 'zignites-sentinel' ); ?></a></p>
					<?php endif; ?>
				</div>
				<?php if ( ! empty( $site_status_card['latest_snapshot']['id'] ) ) : ?>
					<div class="znts-actions znts-dashboard-actions">
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="znts_capture_snapshot_health_baseline" />
							<input type="hidden" name="snapshot_id" value="<?php echo esc_attr( (string) $site_status_card['latest_snapshot']['id'] ); ?>" />
							<?php wp_nonce_field( 'znts_capture_snapshot_health_baseline_action' ); ?>
							<?php submit_button( __( 'Capture Baseline', 'zignites-sentinel' ), 'secondary', 'submit', false ); ?>
						</form>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="znts_download_snapshot_audit_report" />
							<input type="hidden" name="snapshot_id" value="<?php echo esc_attr( (string) $site_status_card['latest_snapshot']['id'] ); ?>" />
							<?php wp_nonce_field( 'znts_download_snapshot_audit_report_action' ); ?>
							<?php submit_button( __( 'Export Audit', 'zignites-sentinel' ), 'secondary', 'submit', false ); ?>
						</form>
					</div>
				<?php endif; ?>
			<?php endif; ?>
		</section>

		<section class="znts-card">
			<h2><?php echo esc_html__( 'Latest Snapshot Health', 'zignites-sentinel' ); ?></h2>
			<?php if ( empty( $restore_health_strip ) ) : ?>
				<p><?php echo esc_html__( 'No snapshot health comparison is available yet.', 'zignites-sentinel' ); ?></p>
			<?php else : ?>
				<p><?php echo esc_html( isset( $restore_health_strip['snapshot']['label'] ) ? $restore_health_strip['snapshot']['label'] : '' ); ?></p>
				<?php if ( empty( $restore_health_strip['rows'] ) ) : ?>
					<p><?php echo esc_html__( 'Capture a health baseline or complete a restore/rollback verification to populate this strip.', 'zignites-sentinel' ); ?></p>
				<?php else : ?>
					<div class="znts-status-grid">
						<?php foreach ( $restore_health_strip['rows'] as $row ) : ?>
							<div class="znts-status-card">
								<h3><?php echo esc_html( isset( $row['label'] ) ? $row['label'] : '' ); ?></h3>
								<p>
									<span class="znts-pill znts-pill-<?php echo esc_attr( 'unhealthy' === ( isset( $row['status'] ) ? $row['status'] : '' ) ? 'critical' : ( 'degraded' === ( isset( $row['status'] ) ? $row['status'] : '' ) ? 'warning' : 'info' ) ); ?>">
										<?php echo esc_html( ucfirst( isset( $row['status'] ) ? $row['status'] : '' ) ); ?>
									</span>
								</p>
								<p><?php echo esc_html( isset( $row['delta'] ) ? $row['delta'] : '' ); ?></p>
								<p class="description">
									<?php
									echo esc_html(
										sprintf(
											/* translators: 1: pass count, 2: warning count, 3: fail count */
											__( '%1$d pass, %2$d warning, %3$d fail.', 'zignites-sentinel' ),
											isset( $row['summary']['pass'] ) ? (int) $row['summary']['pass'] : 0,
											isset( $row['summary']['warning'] ) ? (int) $row['summary']['warning'] : 0,
											isset( $row['summary']['fail'] ) ? (int) $row['summary']['fail'] : 0
										)
									);
									?>
								</p>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
				<?php if ( ! empty( $restore_health_strip['detail_url'] ) ) : ?>
					<p class="znts-card-note"><a href="<?php echo esc_url( $restore_health_strip['detail_url'] ); ?>"><?php echo esc_html__( 'Open health comparison', 'zignites-sentinel' ); ?></a></p>
				<?php endif; ?>
			<?php endif; ?>
		</section>

		<section class="znts-card">
			<h2><?php echo esc_html__( 'Recent Event Logs', 'zignites-sentinel' ); ?></h2>
			<?php if ( empty( $view_data['recent_logs'] ) ) : ?>
				<p><?php echo esc_html__( 'No events have been logged yet.', 'zignites-sentinel' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php echo esc_html__( 'Time', 'zignites-sentinel' ); ?></th>
							<th><?php echo esc_html__( 'Severity', 'zignites-sentinel' ); ?></th>
							<th><?php echo esc_html__( 'Event', 'zignites-sentinel' ); ?></th>
							<th><?php echo esc_html__( 'Source', 'zignites-sentinel' ); ?></th>
							<th><?php echo esc_html__( 'Message', 'zignites-sentinel' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $view_data['recent_logs'] as $log ) : ?>
							<tr>
								<td>
									<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'zignites-sentinel-event-logs', 'log_id' => (int) $log['id'] ), admin_url( 'admin.php' ) ) ); ?>">
										<?php echo esc_html( $log['created_at'] ); ?>
									</a>
								</td>
								<td>
									<span class="znts-pill znts-pill-<?php echo esc_attr( $log['severity'] ); ?>">
										<?php echo esc_html( ucfirst( $log['severity'] ) ); ?>
									</span>
								</td>
								<td><?php echo esc_html( $log['event_type'] ); ?></td>
								<td><?php echo esc_html( $log['source'] ); ?></td>
								<td><?php echo esc_html( $log['message'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</section>

		<section class="znts-card">
			<h2><?php echo esc_html__( 'Recent Snapshots', 'zignites-sentinel' ); ?></h2>
			<?php if ( empty( $view_data['recent_snapshots'] ) ) : ?>
				<p><?php echo esc_html__( 'No snapshot metadata has been created yet.', 'zignites-sentinel' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php echo esc_html__( 'Created', 'zignites-sentinel' ); ?></th>
							<th><?php echo esc_html__( 'Type', 'zignites-sentinel' ); ?></th>
							<th><?php echo esc_html__( 'Status', 'zignites-sentinel' ); ?></th>
							<th><?php echo esc_html__( 'Readiness', 'zignites-sentinel' ); ?></th>
							<th><?php echo esc_html__( 'Label', 'zignites-sentinel' ); ?></th>
							<th><?php echo esc_html__( 'Activity', 'zignites-sentinel' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $view_data['recent_snapshots'] as $snapshot ) : ?>
							<?php $snapshot_status = isset( $snapshot_status_index[ (int) $snapshot['id'] ] ) ? $snapshot_status_index[ (int) $snapshot['id'] ] : array(); ?>
							<tr>
								<td><?php echo esc_html( $snapshot['created_at'] ); ?></td>
								<td><?php echo esc_html( ucfirst( $snapshot['snapshot_type'] ) ); ?></td>
								<td>
									<span class="znts-pill znts-pill-info">
										<?php echo esc_html( ucfirst( $snapshot['status'] ) ); ?>
									</span>
								</td>
								<td>
									<div class="znts-badge-row">
										<?php foreach ( isset( $snapshot_status['status_badges'] ) && is_array( $snapshot_status['status_badges'] ) ? $snapshot_status['status_badges'] : array() as $badge ) : ?>
											<span class="znts-pill znts-pill-<?php echo esc_attr( isset( $badge['badge'] ) ? $badge['badge'] : 'info' ); ?>">
												<?php echo esc_html( isset( $badge['label'] ) ? $badge['label'] : '' ); ?>
											</span>
										<?php endforeach; ?>
									</div>
								</td>
								<td>
									<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'zignites-sentinel-update-readiness', 'snapshot_id' => (int) $snapshot['id'] ), admin_url( 'admin.php' ) ) ); ?>">
										<?php echo esc_html( $snapshot['label'] ); ?>
									</a>
								</td>
								<td>
									<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'zignites-sentinel-event-logs', 'snapshot_id' => (int) $snapshot['id'] ), admin_url( 'admin.php' ) ) ); ?>">
										<?php echo esc_html__( 'Event Logs', 'zignites-sentinel' ); ?>
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</section>

		<section class="znts-card">
			<h2><?php echo esc_html__( 'MVP Scope Notes', 'zignites-sentinel' ); ?></h2>
			<ul class="znts-list">
				<li><?php echo esc_html__( 'Safe update simulation and rollback remain roadmap items until filesystem handling and snapshot validation are robust enough for controlled use.', 'zignites-sentinel' ); ?></li>
				<li><?php echo esc_html__( 'Current conflict detection focuses on realistic signals such as fatal errors, deprecated API usage, incorrect API usage, and update activity.', 'zignites-sentinel' ); ?></li>
				<li><?php echo esc_html__( 'JavaScript conflict detection is not yet implemented because site-wide capture requires a more deliberate data and privacy model.', 'zignites-sentinel' ); ?></li>
			</ul>
		</section>
	</div>
</div>
