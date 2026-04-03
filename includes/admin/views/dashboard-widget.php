<?php
/**
 * Compact WordPress dashboard widget view.
 *
 * @package ZignitesSentinel
 */

defined( 'ABSPATH' ) || exit;

$site_status_card     = isset( $view_data['site_status_card'] ) && is_array( $view_data['site_status_card'] ) ? $view_data['site_status_card'] : array();
$restore_health_strip = isset( $view_data['restore_health_strip'] ) && is_array( $view_data['restore_health_strip'] ) ? $view_data['restore_health_strip'] : array();
$snapshot_status      = isset( $view_data['snapshot_status'] ) && is_array( $view_data['snapshot_status'] ) ? $view_data['snapshot_status'] : array();
$latest_snapshot      = isset( $view_data['latest_snapshot'] ) && is_array( $view_data['latest_snapshot'] ) ? $view_data['latest_snapshot'] : array();
$widget_signals       = ! empty( $site_status_card['signals'] ) && is_array( $site_status_card['signals'] ) ? array_slice( $site_status_card['signals'], 0, 3 ) : array();
$health_rows          = ! empty( $restore_health_strip['rows'] ) && is_array( $restore_health_strip['rows'] ) ? array_slice( $restore_health_strip['rows'], 0, 3 ) : array();
?>
<div class="znts-dashboard-widget">
	<?php if ( empty( $site_status_card ) ) : ?>
		<p><?php echo esc_html__( 'Sentinel site status is not available yet.', 'zignites-sentinel' ); ?></p>
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
						: __( 'No snapshot available', 'zignites-sentinel' )
				);
				?>
			</span>
		</div>

		<p class="znts-widget-summary">
			<strong><?php echo esc_html__( 'Recommended action:', 'zignites-sentinel' ); ?></strong>
			<?php echo esc_html( isset( $site_status_card['recommended_action'] ) ? $site_status_card['recommended_action'] : '' ); ?>
		</p>

		<?php if ( ! empty( $snapshot_status['status_badges'] ) ) : ?>
			<div class="znts-badge-row">
				<?php foreach ( array_slice( $snapshot_status['status_badges'], 0, 5 ) as $badge ) : ?>
					<span class="znts-pill znts-pill-<?php echo esc_attr( isset( $badge['badge'] ) ? $badge['badge'] : 'info' ); ?>">
						<?php echo esc_html( isset( $badge['label'] ) ? $badge['label'] : '' ); ?>
					</span>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $widget_signals ) ) : ?>
			<ul class="znts-list znts-signal-list">
				<?php foreach ( $widget_signals as $signal ) : ?>
					<li><?php echo esc_html( $signal ); ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<?php if ( ! empty( $health_rows ) ) : ?>
			<div class="znts-widget-health">
				<p><strong><?php echo esc_html__( 'Latest health checks', 'zignites-sentinel' ); ?></strong></p>
				<div class="znts-badge-row">
					<?php foreach ( $health_rows as $row ) : ?>
						<?php $status = isset( $row['status'] ) ? (string) $row['status'] : ''; ?>
						<span class="znts-pill znts-pill-<?php echo esc_attr( 'unhealthy' === $status ? 'critical' : ( 'degraded' === $status ? 'warning' : 'info' ) ); ?>">
							<?php
							echo esc_html(
								sprintf(
									/* translators: 1: row label, 2: health status */
									__( '%1$s: %2$s', 'zignites-sentinel' ),
									isset( $row['label'] ) ? (string) $row['label'] : __( 'Health', 'zignites-sentinel' ),
									ucfirst( $status )
								)
							);
							?>
						</span>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endif; ?>

		<div class="znts-widget-links">
			<?php if ( ! empty( $site_status_card['detail_url'] ) ) : ?>
				<a href="<?php echo esc_url( $site_status_card['detail_url'] ); ?>"><?php echo esc_html__( 'Open Update Readiness', 'zignites-sentinel' ); ?></a>
			<?php endif; ?>
			<?php if ( ! empty( $site_status_card['activity_url'] ) ) : ?>
				<a href="<?php echo esc_url( $site_status_card['activity_url'] ); ?>"><?php echo esc_html__( 'Open Snapshot Activity', 'zignites-sentinel' ); ?></a>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</div>
