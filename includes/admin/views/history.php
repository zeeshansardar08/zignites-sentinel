<?php
/**
 * Simplified History view.
 *
 * @package ZignitesSentinel
 */

defined( 'ABSPATH' ) || exit;

$recent_logs        = isset( $view_data['recent_logs'] ) && is_array( $view_data['recent_logs'] ) ? $view_data['recent_logs'] : array();
$log_detail         = isset( $view_data['log_detail'] ) && is_array( $view_data['log_detail'] ) ? $view_data['log_detail'] : null;
$log_filters        = isset( $view_data['log_filters'] ) && is_array( $view_data['log_filters'] ) ? $view_data['log_filters'] : array();
$event_log_ui       = isset( $view_data['event_log_ui'] ) && is_array( $view_data['event_log_ui'] ) ? $view_data['event_log_ui'] : array();
$recent_logs        = isset( $event_log_ui['recent_logs'] ) && is_array( $event_log_ui['recent_logs'] ) ? $event_log_ui['recent_logs'] : $recent_logs;
$pagination         = isset( $view_data['pagination'] ) && is_array( $view_data['pagination'] ) ? $view_data['pagination'] : array();
$current_page       = isset( $pagination['current_page'] ) ? (int) $pagination['current_page'] : 1;
$total_pages        = isset( $pagination['total_pages'] ) ? (int) $pagination['total_pages'] : 1;
$base_args          = isset( $event_log_ui['base_args'] ) && is_array( $event_log_ui['base_args'] ) ? $event_log_ui['base_args'] : array();
$admin_page_url     = \Zignites\Sentinel\Admin\znts_admin_url( 'admin.php' );
?>
<div class="wrap znts-admin-page">
	<div class="znts-page-header">
		<h1><?php echo esc_html__( 'History', 'zignites-sentinel' ); ?></h1>
		<p class="znts-page-intro"><?php echo esc_html__( 'Review recent checkpoint, restore, and rollback activity. Start here when you need to confirm what happened, then open a specific run only if you need more detail.', 'zignites-sentinel' ); ?></p>
	</div>

	<section class="znts-summary-hero">
		<span class="znts-eyebrow"><?php echo esc_html__( 'Recent History', 'zignites-sentinel' ); ?></span>
		<h2 class="znts-hero-title"><?php echo esc_html__( 'Checkpoint, restore, and rollback events in one place.', 'zignites-sentinel' ); ?></h2>
		<p class="znts-hero-subtitle"><?php echo esc_html__( 'This is the audit trail for the narrowed v1 workflow. It supports review, not full operational forensics.', 'zignites-sentinel' ); ?></p>
	</section>

	<section class="znts-card znts-card-full znts-card-primary">
		<div class="znts-toolbar">
			<form method="get" class="znts-filter-form">
				<input type="hidden" name="page" value="zignites-sentinel-event-logs" />
				<p>
					<label for="znts-log-severity"><?php echo esc_html__( 'Severity', 'zignites-sentinel' ); ?></label>
					<select id="znts-log-severity" name="severity">
						<option value=""><?php echo esc_html__( 'All severities', 'zignites-sentinel' ); ?></option>
						<option value="info" <?php selected( isset( $log_filters['severity'] ) ? $log_filters['severity'] : '', 'info' ); ?>><?php echo esc_html__( 'Info', 'zignites-sentinel' ); ?></option>
						<option value="warning" <?php selected( isset( $log_filters['severity'] ) ? $log_filters['severity'] : '', 'warning' ); ?>><?php echo esc_html__( 'Warning', 'zignites-sentinel' ); ?></option>
						<option value="error" <?php selected( isset( $log_filters['severity'] ) ? $log_filters['severity'] : '', 'error' ); ?>><?php echo esc_html__( 'Error', 'zignites-sentinel' ); ?></option>
						<option value="critical" <?php selected( isset( $log_filters['severity'] ) ? $log_filters['severity'] : '', 'critical' ); ?>><?php echo esc_html__( 'Critical', 'zignites-sentinel' ); ?></option>
					</select>
				</p>
				<p>
					<label for="znts-log-source"><?php echo esc_html__( 'Source', 'zignites-sentinel' ); ?></label>
					<input id="znts-log-source" type="text" name="source" value="<?php echo esc_attr( isset( $log_filters['source'] ) ? $log_filters['source'] : '' ); ?>" />
				</p>
				<p>
					<label for="znts-log-run-id"><?php echo esc_html__( 'Run ID', 'zignites-sentinel' ); ?></label>
					<input id="znts-log-run-id" type="text" name="run_id" value="<?php echo esc_attr( isset( $log_filters['run_id'] ) ? $log_filters['run_id'] : '' ); ?>" />
				</p>
				<p>
					<label for="znts-log-snapshot-id"><?php echo esc_html__( 'Checkpoint ID', 'zignites-sentinel' ); ?></label>
					<input id="znts-log-snapshot-id" type="number" min="1" name="snapshot_id" value="<?php echo esc_attr( ! empty( $log_filters['snapshot_id'] ) ? (string) $log_filters['snapshot_id'] : '' ); ?>" class="small-text" />
				</p>
				<p>
					<label for="znts-log-search"><?php echo esc_html__( 'Search', 'zignites-sentinel' ); ?></label>
					<input id="znts-log-search" type="search" name="log_search" value="<?php echo esc_attr( isset( $log_filters['search'] ) ? $log_filters['search'] : '' ); ?>" />
				</p>
				<p class="znts-filter-actions">
					<?php submit_button( __( 'Filter', 'zignites-sentinel' ), 'secondary', '', false ); ?>
					<a class="button button-link" href="<?php echo esc_url( add_query_arg( array( 'page' => 'zignites-sentinel-event-logs' ), $admin_page_url ) ); ?>"><?php echo esc_html__( 'Reset', 'zignites-sentinel' ); ?></a>
				</p>
			</form>

			<?php if ( empty( $recent_logs ) ) : ?>
				<p><?php echo esc_html__( 'No history entries match the current filters.', 'zignites-sentinel' ); ?></p>
			<?php else : ?>
				<table class="widefat striped znts-log-table">
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
						<?php foreach ( $recent_logs as $log ) : ?>
							<tr>
								<td><a href="<?php echo esc_url( add_query_arg( array_merge( $base_args, array( 'paged' => $current_page, 'log_id' => (int) $log['id'] ) ), $admin_page_url ) ); ?>"><?php echo esc_html( isset( $log['created_at'] ) ? $log['created_at'] : '' ); ?></a></td>
								<td><span class="znts-pill znts-pill-<?php echo esc_attr( isset( $log['severity_pill'] ) ? $log['severity_pill'] : 'info' ); ?>"><?php echo esc_html( isset( $log['severity_label'] ) ? $log['severity_label'] : '' ); ?></span></td>
								<td><?php echo esc_html( isset( $log['event_type'] ) ? $log['event_type'] : '' ); ?></td>
								<td><?php echo esc_html( isset( $log['source'] ) ? $log['source'] : '' ); ?></td>
								<td><?php echo esc_html( isset( $log['message'] ) ? $log['message'] : '' ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<?php if ( $total_pages > 1 ) : ?>
					<div class="tablenav">
						<div class="tablenav-pages">
							<?php
							echo wp_kses_post(
								paginate_links(
									array(
										'base'      => add_query_arg( array_merge( $base_args, array( 'paged' => '%#%' ) ), $admin_page_url ),
										'format'    => '',
										'current'   => $current_page,
										'total'     => $total_pages,
										'prev_text' => __( '&laquo;', 'zignites-sentinel' ),
										'next_text' => __( '&raquo;', 'zignites-sentinel' ),
									)
								)
							);
							?>
						</div>
					</div>
				<?php endif; ?>
			<?php endif; ?>
		</div>
	</section>

	<?php if ( $log_detail ) : ?>
		<section class="znts-card znts-card-full znts-card-flat">
			<h2><?php echo esc_html__( 'Event Detail', 'zignites-sentinel' ); ?></h2>
			<table class="widefat striped">
				<tbody>
					<tr><th scope="row"><?php echo esc_html__( 'Time', 'zignites-sentinel' ); ?></th><td><?php echo esc_html( $log_detail['created_at'] ); ?></td></tr>
					<tr><th scope="row"><?php echo esc_html__( 'Severity', 'zignites-sentinel' ); ?></th><td><?php echo esc_html( ucfirst( $log_detail['severity'] ) ); ?></td></tr>
					<tr><th scope="row"><?php echo esc_html__( 'Event', 'zignites-sentinel' ); ?></th><td><?php echo esc_html( $log_detail['event_type'] ); ?></td></tr>
					<tr><th scope="row"><?php echo esc_html__( 'Source', 'zignites-sentinel' ); ?></th><td><?php echo esc_html( $log_detail['source'] ); ?></td></tr>
					<tr><th scope="row"><?php echo esc_html__( 'Message', 'zignites-sentinel' ); ?></th><td><?php echo esc_html( $log_detail['message'] ); ?></td></tr>
				</tbody>
			</table>
		</section>
	<?php endif; ?>
</div>
