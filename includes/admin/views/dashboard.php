<?php
/**
 * Dashboard view.
 *
 * @package ZignitesSentinel
 */

defined( 'ABSPATH' ) || exit;

$site_status_card      = isset( $view_data['site_status_card'] ) && is_array( $view_data['site_status_card'] ) ? $view_data['site_status_card'] : array();
$restore_health_strip  = isset( $view_data['restore_health_strip'] ) && is_array( $view_data['restore_health_strip'] ) ? $view_data['restore_health_strip'] : array();
$snapshot_status_index = isset( $view_data['snapshot_status_index'] ) && is_array( $view_data['snapshot_status_index'] ) ? $view_data['snapshot_status_index'] : array();
$recent_snapshots      = isset( $view_data['recent_snapshots'] ) && is_array( $view_data['recent_snapshots'] ) ? $view_data['recent_snapshots'] : array();
$recent_logs           = isset( $view_data['recent_logs'] ) && is_array( $view_data['recent_logs'] ) ? $view_data['recent_logs'] : array();
$recent_conflicts      = isset( $view_data['recent_conflicts'] ) && is_array( $view_data['recent_conflicts'] ) ? $view_data['recent_conflicts'] : array();
$health_score          = isset( $view_data['health_score'] ) && is_array( $view_data['health_score'] ) ? $view_data['health_score'] : array();
$latest_snapshot       = ! empty( $recent_snapshots[0] ) ? $recent_snapshots[0] : array();
$latest_snapshot_state = ( ! empty( $latest_snapshot['id'] ) && isset( $snapshot_status_index[ (int) $latest_snapshot['id'] ] ) ) ? $snapshot_status_index[ (int) $latest_snapshot['id'] ] : array();
?>
<div class="wrap znts-admin-page">
	<h1><?php echo esc_html__( 'Zignites Sentinel', 'zignites-sentinel' ); ?></h1>
	<p class="znts-page-intro"><?php echo esc_html__( 'Monitor site stability, track restore readiness, and move to the next safe operator action without digging through every panel.', 'zignites-sentinel' ); ?></p>

	<div class="znts-dashboard-shell">
		<div class="znts-hero-grid">
			<section class="znts-hero-main">
				<span class="znts-eyebrow"><?php echo esc_html__( 'Site Status', 'zignites-sentinel' ); ?></span>
				<?php if ( empty( $site_status_card ) ) : ?>
					<h2 class="znts-hero-title"><?php echo esc_html__( 'No site summary is available yet.', 'zignites-sentinel' ); ?></h2>
					<p class="znts-hero-subtitle"><?php echo esc_html__( 'Create a snapshot and capture baseline data to populate the operational dashboard.', 'zignites-sentinel' ); ?></p>
				<?php else : ?>
					<div class="znts-readiness-row">
						<span class="znts-pill znts-pill-<?php echo esc_attr( isset( $site_status_card['badge'] ) ? $site_status_card['badge'] : 'info' ); ?>">
							<?php echo esc_html( isset( $site_status_card['label'] ) ? $site_status_card['label'] : '' ); ?>
						</span>
						<?php if ( ! empty( $site_status_card['latest_snapshot']['label'] ) ) : ?>
							<span class="znts-inline-note"><?php echo esc_html( $site_status_card['latest_snapshot']['label'] ); ?></span>
						<?php endif; ?>
					</div>
					<h2 class="znts-hero-title"><?php echo esc_html( isset( $site_status_card['recommended_action'] ) ? $site_status_card['recommended_action'] : '' ); ?></h2>
					<p class="znts-hero-subtitle"><?php echo esc_html__( 'This summary reflects current conflict signals, the latest snapshot posture, and whether restore preparation is in a usable state.', 'zignites-sentinel' ); ?></p>
					<div class="znts-hero-recommendation">
						<strong><?php echo esc_html__( 'Recommended Action', 'zignites-sentinel' ); ?></strong>
						<?php echo esc_html( isset( $site_status_card['recommended_action'] ) ? $site_status_card['recommended_action'] : '' ); ?>
					</div>
					<?php if ( ! empty( $site_status_card['signals'] ) ) : ?>
						<div class="znts-hero-signals">
							<?php foreach ( $site_status_card['signals'] as $signal ) : ?>
								<div class="znts-signal-chip"><?php echo esc_html( $signal ); ?></div>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				<?php endif; ?>
			</section>

			<div class="znts-hero-side">
				<section class="znts-stat-tile">
					<span class="znts-stat-label"><?php echo esc_html__( 'Stability Score', 'zignites-sentinel' ); ?></span>
					<strong class="znts-stat-value"><?php echo esc_html( isset( $health_score['score'] ) ? (string) $health_score['score'] : '0' ); ?></strong>
					<p class="znts-stat-note"><?php echo esc_html( isset( $health_score['summary'] ) ? $health_score['summary'] : '' ); ?></p>
				</section>
				<section class="znts-stat-tile">
					<span class="znts-stat-label"><?php echo esc_html__( 'Latest Snapshot', 'zignites-sentinel' ); ?></span>
					<strong class="znts-stat-value"><?php echo esc_html( ! empty( $latest_snapshot['label'] ) ? $latest_snapshot['label'] : __( 'None yet', 'zignites-sentinel' ) ); ?></strong>
					<p class="znts-stat-note"><?php echo esc_html( ! empty( $latest_snapshot['created_at'] ) ? $latest_snapshot['created_at'] : __( 'Create a snapshot before planning update or restore work.', 'zignites-sentinel' ) ); ?></p>
				</section>
				<section class="znts-card znts-card-soft">
					<span class="znts-stat-label"><?php echo esc_html__( 'Quick Actions', 'zignites-sentinel' ); ?></span>
					<div class="znts-quick-actions znts-dashboard-actions">
						<?php if ( ! empty( $site_status_card['detail_url'] ) ) : ?>
							<p><a class="button button-primary" href="<?php echo esc_url( $site_status_card['detail_url'] ); ?>"><?php echo esc_html__( 'Open Update Readiness', 'zignites-sentinel' ); ?></a></p>
						<?php endif; ?>
						<?php if ( ! empty( $site_status_card['activity_url'] ) ) : ?>
							<p><a class="button button-secondary" href="<?php echo esc_url( $site_status_card['activity_url'] ); ?>"><?php echo esc_html__( 'Open Snapshot Activity', 'zignites-sentinel' ); ?></a></p>
						<?php endif; ?>
						<?php if ( ! empty( $site_status_card['latest_snapshot']['id'] ) ) : ?>
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
						<?php endif; ?>
					</div>
				</section>
			</div>
		</div>

		<div class="znts-dashboard-secondary">
			<section class="znts-card znts-card-soft">
				<div class="znts-table-header">
					<div>
						<h2><?php echo esc_html__( 'Restore Readiness Summary', 'zignites-sentinel' ); ?></h2>
						<p><?php echo esc_html__( 'Use the latest snapshot as your working restore reference.', 'zignites-sentinel' ); ?></p>
					</div>
				</div>
				<?php if ( empty( $latest_snapshot ) ) : ?>
					<div class="znts-empty-state">
						<strong><?php echo esc_html__( 'No snapshot is available.', 'zignites-sentinel' ); ?></strong>
						<p><?php echo esc_html__( 'Create a snapshot before running staged validation, restore planning, or baseline capture.', 'zignites-sentinel' ); ?></p>
					</div>
				<?php else : ?>
					<div class="znts-snapshot-overview">
						<div class="znts-overview-block">
							<strong><?php echo esc_html__( 'Snapshot', 'zignites-sentinel' ); ?></strong>
							<span><?php echo esc_html( $latest_snapshot['label'] ); ?></span>
						</div>
						<div class="znts-overview-block">
							<strong><?php echo esc_html__( 'Created', 'zignites-sentinel' ); ?></strong>
							<span><?php echo esc_html( $latest_snapshot['created_at'] ); ?></span>
						</div>
						<div class="znts-overview-block">
							<strong><?php echo esc_html__( 'Restore State', 'zignites-sentinel' ); ?></strong>
							<span><?php echo esc_html( ! empty( $latest_snapshot_state['restore_ready'] ) ? __( 'Ready for guarded restore review', 'zignites-sentinel' ) : __( 'Needs more preparation', 'zignites-sentinel' ) ); ?></span>
						</div>
					</div>
					<div class="znts-badge-row">
						<?php foreach ( isset( $latest_snapshot_state['status_badges'] ) && is_array( $latest_snapshot_state['status_badges'] ) ? $latest_snapshot_state['status_badges'] : array() as $badge ) : ?>
							<span class="znts-pill znts-pill-<?php echo esc_attr( isset( $badge['badge'] ) ? $badge['badge'] : 'info' ); ?>">
								<?php echo esc_html( isset( $badge['label'] ) ? $badge['label'] : '' ); ?>
							</span>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</section>

			<section class="znts-card znts-card-soft">
				<div class="znts-table-header">
					<div>
						<h2><?php echo esc_html__( 'Recent Critical Activity', 'zignites-sentinel' ); ?></h2>
						<p><?php echo esc_html__( 'Review the latest conflicts and high-severity events before making changes.', 'zignites-sentinel' ); ?></p>
					</div>
				</div>
				<?php if ( empty( $recent_conflicts ) && empty( $recent_logs ) ) : ?>
					<div class="znts-empty-state">
						<strong><?php echo esc_html__( 'Nothing urgent is recorded.', 'zignites-sentinel' ); ?></strong>
						<p><?php echo esc_html__( 'Conflict signals and recent events will appear here when Sentinel captures them.', 'zignites-sentinel' ); ?></p>
					</div>
				<?php else : ?>
					<ul class="znts-activity-list">
						<?php foreach ( array_slice( $recent_conflicts, 0, 3 ) as $conflict ) : ?>
							<li class="znts-activity-item">
								<div class="znts-activity-meta">
									<span class="znts-pill znts-pill-<?php echo esc_attr( $conflict['severity'] ); ?>"><?php echo esc_html( ucfirst( $conflict['severity'] ) ); ?></span>
									<span><?php echo esc_html( $conflict['last_seen_at'] ); ?></span>
								</div>
								<h3><?php echo esc_html( $conflict['summary'] ); ?></h3>
								<p><?php echo esc_html( $conflict['source_a'] ); ?></p>
							</li>
						<?php endforeach; ?>
						<?php foreach ( array_slice( $recent_logs, 0, 3 ) as $log ) : ?>
							<li class="znts-activity-item">
								<div class="znts-activity-meta">
									<span class="znts-pill znts-pill-<?php echo esc_attr( $log['severity'] ); ?>"><?php echo esc_html( ucfirst( $log['severity'] ) ); ?></span>
									<span><?php echo esc_html( $log['created_at'] ); ?></span>
								</div>
								<h3><?php echo esc_html( $log['event_type'] ); ?></h3>
								<p><?php echo esc_html( $log['message'] ); ?></p>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</section>

			<section class="znts-card znts-card-soft">
				<div class="znts-table-header">
					<div>
						<h2><?php echo esc_html__( 'Snapshot Health', 'zignites-sentinel' ); ?></h2>
						<p><?php echo esc_html__( 'Compare baseline, restore, and rollback health snapshots at a glance.', 'zignites-sentinel' ); ?></p>
					</div>
				</div>
				<?php if ( empty( $restore_health_strip ) || empty( $restore_health_strip['rows'] ) ) : ?>
					<div class="znts-empty-state">
						<strong><?php echo esc_html__( 'No comparison is ready yet.', 'zignites-sentinel' ); ?></strong>
						<p><?php echo esc_html__( 'Capture a baseline or complete restore verification to populate this health summary.', 'zignites-sentinel' ); ?></p>
					</div>
				<?php else : ?>
					<div class="znts-status-grid">
						<?php foreach ( $restore_health_strip['rows'] as $row ) : ?>
							<div class="znts-status-card">
								<div class="znts-readiness-row">
									<span class="znts-pill znts-pill-<?php echo esc_attr( 'unhealthy' === ( isset( $row['status'] ) ? $row['status'] : '' ) ? 'critical' : ( 'degraded' === ( isset( $row['status'] ) ? $row['status'] : '' ) ? 'warning' : 'info' ) ); ?>">
										<?php echo esc_html( ucfirst( isset( $row['status'] ) ? $row['status'] : '' ) ); ?>
									</span>
								</div>
								<h3><?php echo esc_html( isset( $row['label'] ) ? $row['label'] : '' ); ?></h3>
								<p><?php echo esc_html( isset( $row['delta'] ) ? $row['delta'] : '' ); ?></p>
								<p class="description"><?php echo esc_html( sprintf( __( '%1$d pass, %2$d warning, %3$d fail.', 'zignites-sentinel' ), isset( $row['summary']['pass'] ) ? (int) $row['summary']['pass'] : 0, isset( $row['summary']['warning'] ) ? (int) $row['summary']['warning'] : 0, isset( $row['summary']['fail'] ) ? (int) $row['summary']['fail'] : 0 ) ); ?></p>
							</div>
						<?php endforeach; ?>
					</div>
					<?php if ( ! empty( $restore_health_strip['detail_url'] ) ) : ?>
						<p class="znts-card-note"><a href="<?php echo esc_url( $restore_health_strip['detail_url'] ); ?>"><?php echo esc_html__( 'Open health comparison', 'zignites-sentinel' ); ?></a></p>
					<?php endif; ?>
				<?php endif; ?>
			</section>
		</div>

		<div class="znts-dashboard-support">
			<section class="znts-card znts-card-soft">
				<div class="znts-table-header">
					<div>
						<h2><?php echo esc_html__( 'Event Activity Timeline', 'zignites-sentinel' ); ?></h2>
						<p><?php echo esc_html__( 'Recent Sentinel events, ordered by time, with direct links to the full investigation view.', 'zignites-sentinel' ); ?></p>
					</div>
					<p><a href="<?php echo esc_url( add_query_arg( array( 'page' => 'zignites-sentinel-event-logs' ), admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html__( 'Open Event Logs', 'zignites-sentinel' ); ?></a></p>
				</div>
				<?php if ( empty( $recent_logs ) ) : ?>
					<div class="znts-empty-state">
						<strong><?php echo esc_html__( 'No recent events are available.', 'zignites-sentinel' ); ?></strong>
						<p><?php echo esc_html__( 'Sentinel event activity will appear here after scans, restore checks, and logging operations run.', 'zignites-sentinel' ); ?></p>
					</div>
				<?php else : ?>
					<ul class="znts-timeline-list">
						<?php foreach ( $recent_logs as $log ) : ?>
							<li class="znts-timeline-item">
								<div class="znts-timeline-meta">
									<span class="znts-pill znts-pill-<?php echo esc_attr( $log['severity'] ); ?>"><?php echo esc_html( ucfirst( $log['severity'] ) ); ?></span>
									<span><?php echo esc_html( $log['created_at'] ); ?></span>
									<span><?php echo esc_html( $log['source'] ); ?></span>
								</div>
								<h3><a href="<?php echo esc_url( add_query_arg( array( 'page' => 'zignites-sentinel-event-logs', 'log_id' => (int) $log['id'] ), admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html( $log['event_type'] ); ?></a></h3>
								<p><?php echo esc_html( $log['message'] ); ?></p>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</section>

			<section class="znts-card znts-card-muted">
				<div class="znts-table-header">
					<div>
						<h2><?php echo esc_html__( 'Environment Summary', 'zignites-sentinel' ); ?></h2>
						<p><?php echo esc_html__( 'Technical details stay available, but secondary to operational decisions.', 'zignites-sentinel' ); ?></p>
					</div>
				</div>
				<div class="znts-kpi-grid">
					<div class="znts-kpi">
						<span class="znts-kpi-label"><?php echo esc_html__( 'WordPress', 'zignites-sentinel' ); ?></span>
						<strong class="znts-kpi-value"><?php echo esc_html( $view_data['wordpress'] ); ?></strong>
					</div>
					<div class="znts-kpi">
						<span class="znts-kpi-label"><?php echo esc_html__( 'PHP', 'zignites-sentinel' ); ?></span>
						<strong class="znts-kpi-value"><?php echo esc_html( $view_data['php'] ); ?></strong>
					</div>
					<div class="znts-kpi">
						<span class="znts-kpi-label"><?php echo esc_html__( 'Plugin', 'zignites-sentinel' ); ?></span>
						<strong class="znts-kpi-value"><?php echo esc_html( $view_data['plugin_version'] ); ?></strong>
						<p class="znts-kpi-note"><?php echo esc_html( sprintf( __( 'DB %s', 'zignites-sentinel' ), $view_data['db_version'] ) ); ?></p>
					</div>
				</div>
				<table class="widefat striped">
					<tbody>
						<tr>
							<th scope="row"><?php echo esc_html__( 'Site URL', 'zignites-sentinel' ); ?></th>
							<td><?php echo esc_html( $view_data['site_url'] ); ?></td>
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
		</div>

		<div class="znts-dashboard-support">
			<section class="znts-card znts-card-soft znts-card-full">
				<div class="znts-table-header">
					<div>
						<h2><?php echo esc_html__( 'Recent Snapshots', 'zignites-sentinel' ); ?></h2>
						<p><?php echo esc_html__( 'Snapshot readiness badges show which baseline, package, and restore-gate signals are already in place.', 'zignites-sentinel' ); ?></p>
					</div>
				</div>
				<?php if ( empty( $recent_snapshots ) ) : ?>
					<div class="znts-empty-state">
						<strong><?php echo esc_html__( 'No snapshots have been captured yet.', 'zignites-sentinel' ); ?></strong>
						<p><?php echo esc_html__( 'Create a snapshot from Update Readiness before using restore planning or baseline capture.', 'zignites-sentinel' ); ?></p>
					</div>
				<?php else : ?>
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php echo esc_html__( 'Created', 'zignites-sentinel' ); ?></th>
								<th><?php echo esc_html__( 'Label', 'zignites-sentinel' ); ?></th>
								<th><?php echo esc_html__( 'Readiness', 'zignites-sentinel' ); ?></th>
								<th><?php echo esc_html__( 'Type', 'zignites-sentinel' ); ?></th>
								<th><?php echo esc_html__( 'Activity', 'zignites-sentinel' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $recent_snapshots as $snapshot ) : ?>
								<?php $snapshot_status = isset( $snapshot_status_index[ (int) $snapshot['id'] ] ) ? $snapshot_status_index[ (int) $snapshot['id'] ] : array(); ?>
								<tr>
									<td><?php echo esc_html( $snapshot['created_at'] ); ?></td>
									<td><a href="<?php echo esc_url( add_query_arg( array( 'page' => 'zignites-sentinel-update-readiness', 'snapshot_id' => (int) $snapshot['id'] ), admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html( $snapshot['label'] ); ?></a></td>
									<td>
										<div class="znts-badge-row">
											<?php foreach ( isset( $snapshot_status['status_badges'] ) && is_array( $snapshot_status['status_badges'] ) ? $snapshot_status['status_badges'] : array() as $badge ) : ?>
												<span class="znts-pill znts-pill-<?php echo esc_attr( isset( $badge['badge'] ) ? $badge['badge'] : 'info' ); ?>"><?php echo esc_html( isset( $badge['label'] ) ? $badge['label'] : '' ); ?></span>
											<?php endforeach; ?>
										</div>
									</td>
									<td><?php echo esc_html( ucfirst( $snapshot['snapshot_type'] ) ); ?></td>
									<td><a href="<?php echo esc_url( add_query_arg( array( 'page' => 'zignites-sentinel-event-logs', 'snapshot_id' => (int) $snapshot['id'] ), admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html__( 'Event Logs', 'zignites-sentinel' ); ?></a></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</section>
		</div>
	</div>
</div>
