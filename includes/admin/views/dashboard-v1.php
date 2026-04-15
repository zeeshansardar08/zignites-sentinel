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
$latest_snapshot       = ! empty( $recent_snapshots[0] ) ? $recent_snapshots[0] : array();
$latest_snapshot_state = ( ! empty( $latest_snapshot['id'] ) && isset( $snapshot_status_index[ (int) $latest_snapshot['id'] ] ) ) ? $snapshot_status_index[ (int) $latest_snapshot['id'] ] : array();
$primary_action        = isset( $site_status_card['primary_action'] ) && is_array( $site_status_card['primary_action'] ) ? $site_status_card['primary_action'] : array();
$primary_action_title  = isset( $primary_action['title'] ) ? (string) $primary_action['title'] : __( 'Create a rollback checkpoint before updates.', 'zignites-sentinel' );
$primary_action_note   = isset( $primary_action['description'] ) ? (string) $primary_action['description'] : __( 'Capture a checkpoint of your active plugins and theme before updating anything.', 'zignites-sentinel' );
$primary_action_label  = isset( $primary_action['button_label'] ) ? (string) $primary_action['button_label'] : __( 'Open Before Update', 'zignites-sentinel' );
$primary_action_url    = isset( $primary_action['url'] ) ? (string) $primary_action['url'] : '';
$admin_page_url        = \Zignites\Sentinel\Admin\znts_admin_url( 'admin.php' );
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
		<p class="znts-page-intro"><?php echo esc_html__( 'Create a rollback checkpoint of your active plugins and theme before updates, then restore it if an update breaks the code layer.', 'zignites-sentinel' ); ?></p>
	</div>

	<section class="znts-summary-hero">
		<span class="znts-eyebrow"><?php echo esc_html__( 'Before Update', 'zignites-sentinel' ); ?></span>
		<?php if ( empty( $site_status_card ) ) : ?>
			<h2 class="znts-hero-title"><?php echo esc_html__( 'Create Your First Checkpoint', 'zignites-sentinel' ); ?></h2>
			<p class="znts-hero-subtitle"><?php echo esc_html__( 'Before updating your site, save a checkpoint of the active theme and plugins so you can restore that code layer if an update breaks it.', 'zignites-sentinel' ); ?></p>
			<p><a class="button button-primary" href="<?php echo esc_url( $first_run_cta_url ); ?>"><?php echo esc_html__( 'Create Your First Checkpoint', 'zignites-sentinel' ); ?></a></p>
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
			<span><?php echo esc_html__( 'Sentinel restores the active theme and active plugins only. It does not restore the database, uploads/media, or WordPress core. Use a full backup solution for full-site recovery.', 'zignites-sentinel' ); ?></span>
		</div>
	</section>

	<div class="znts-admin-grid znts-readiness-grid">
		<section class="znts-card znts-card-full znts-card-primary">
			<h2><?php echo esc_html__( 'Latest Checkpoint', 'zignites-sentinel' ); ?></h2>
			<?php if ( empty( $latest_snapshot ) ) : ?>
				<div class="znts-empty-state">
					<strong><?php echo esc_html__( 'No checkpoints have been saved yet.', 'zignites-sentinel' ); ?></strong>
					<p><?php echo esc_html__( 'Create a checkpoint before making risky plugin or theme updates.', 'zignites-sentinel' ); ?></p>
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
					<p><?php echo esc_html__( 'Use Before Update to save a rollback checkpoint before plugin or theme updates.', 'zignites-sentinel' ); ?></p>
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
