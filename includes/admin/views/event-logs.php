<?php
/**
 * Event logs admin view.
 *
 * @package ZignitesSentinel
 */

defined( 'ABSPATH' ) || exit;

$recent_logs         = isset( $view_data['recent_logs'] ) && is_array( $view_data['recent_logs'] ) ? $view_data['recent_logs'] : array();
$log_detail          = isset( $view_data['log_detail'] ) && is_array( $view_data['log_detail'] ) ? $view_data['log_detail'] : null;
$log_filters         = isset( $view_data['log_filters'] ) && is_array( $view_data['log_filters'] ) ? $view_data['log_filters'] : array();
$operational_events  = isset( $view_data['operational_events'] ) && is_array( $view_data['operational_events'] ) ? $view_data['operational_events'] : array();
$run_summaries       = isset( $view_data['run_summaries'] ) && is_array( $view_data['run_summaries'] ) ? $view_data['run_summaries'] : array();
$run_journal         = isset( $view_data['run_journal'] ) && is_array( $view_data['run_journal'] ) ? $view_data['run_journal'] : array();
$event_log_ui        = isset( $view_data['event_log_ui'] ) && is_array( $view_data['event_log_ui'] ) ? $view_data['event_log_ui'] : array();
$recent_logs         = isset( $event_log_ui['recent_logs'] ) && is_array( $event_log_ui['recent_logs'] ) ? $event_log_ui['recent_logs'] : $recent_logs;
$operational_events  = isset( $event_log_ui['operational_events'] ) && is_array( $event_log_ui['operational_events'] ) ? $event_log_ui['operational_events'] : $operational_events;
$pagination          = isset( $view_data['pagination'] ) && is_array( $view_data['pagination'] ) ? $view_data['pagination'] : array();
$current_page        = isset( $pagination['current_page'] ) ? (int) $pagination['current_page'] : 1;
$total_pages         = isset( $pagination['total_pages'] ) ? (int) $pagination['total_pages'] : 1;
$base_args           = isset( $event_log_ui['base_args'] ) && is_array( $event_log_ui['base_args'] ) ? $event_log_ui['base_args'] : array();
$active_filter_count = isset( $event_log_ui['active_filter_count'] ) ? (int) $event_log_ui['active_filter_count'] : 0;
$severity_counts     = isset( $event_log_ui['severity_counts'] ) && is_array( $event_log_ui['severity_counts'] ) ? $event_log_ui['severity_counts'] : array();
$summary_tiles       = isset( $event_log_ui['summary_tiles'] ) && is_array( $event_log_ui['summary_tiles'] ) ? $event_log_ui['summary_tiles'] : array();
?>
<div class="wrap znts-admin-page">
	<h1><?php echo esc_html__( 'Event Logs', 'zignites-sentinel' ); ?></h1>
	<p class="znts-page-intro"><?php echo esc_html__( 'Investigate Sentinel activity with filters, run history, and structured event context in one place.', 'zignites-sentinel' ); ?></p>

	<div class="znts-log-stack">
		<section class="znts-summary-hero">
			<span class="znts-eyebrow"><?php echo esc_html__( 'Investigation Console', 'zignites-sentinel' ); ?></span>
			<h2 class="znts-hero-title"><?php echo esc_html__( 'Filter, inspect, and export operational evidence.', 'zignites-sentinel' ); ?></h2>
			<p class="znts-hero-subtitle"><?php echo esc_html__( 'Use severity, source, run, snapshot, and text filters to narrow the event stream, then jump into run journals or single-event detail.', 'zignites-sentinel' ); ?></p>
			<div class="znts-investigation-grid">
				<?php foreach ( $summary_tiles as $tile ) : ?>
					<div class="znts-investigation-tile">
						<span class="znts-stat-label"><?php echo esc_html( isset( $tile['label'] ) ? $tile['label'] : '' ); ?></span>
						<strong><?php echo esc_html( isset( $tile['value'] ) ? $tile['value'] : '0' ); ?></strong>
					</div>
				<?php endforeach; ?>
			</div>
			<div class="znts-badge-row">
				<?php foreach ( $severity_counts as $severity => $count ) : ?>
					<?php if ( $count < 1 ) : ?>
						<?php continue; ?>
					<?php endif; ?>
					<span class="znts-pill znts-pill-<?php echo esc_attr( $severity ); ?>">
						<?php echo esc_html( sprintf( __( '%1$s %2$d', 'zignites-sentinel' ), ucfirst( $severity ), $count ) ); ?>
					</span>
				<?php endforeach; ?>
			</div>
		</section>

		<section class="znts-card znts-card-full znts-card-soft">
			<div class="znts-toolbar">
				<div class="znts-toolbar-head">
					<div>
						<h2><?php echo esc_html__( 'Event Explorer', 'zignites-sentinel' ); ?></h2>
						<p><?php echo esc_html__( 'Refine the event stream, then export the current result set without leaving the screen.', 'zignites-sentinel' ); ?></p>
					</div>
					<p class="znts-inline-note">
						<?php
						echo esc_html(
							sprintf(
								/* translators: 1: current page, 2: total pages */
								__( 'Page %1$d of %2$d', 'zignites-sentinel' ),
								max( 1, $current_page ),
								max( 1, $total_pages )
							)
						);
						?>
					</p>
				</div>
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
						<select id="znts-log-source" name="source">
							<option value=""><?php echo esc_html__( 'All sources', 'zignites-sentinel' ); ?></option>
							<option value="restore-execution-journal" <?php selected( isset( $log_filters['source'] ) ? $log_filters['source'] : '', 'restore-execution-journal' ); ?>><?php echo esc_html__( 'Execution Journal', 'zignites-sentinel' ); ?></option>
							<option value="restore-rollback-journal" <?php selected( isset( $log_filters['source'] ) ? $log_filters['source'] : '', 'restore-rollback-journal' ); ?>><?php echo esc_html__( 'Rollback Journal', 'zignites-sentinel' ); ?></option>
							<option value="restore-checkpoint" <?php selected( isset( $log_filters['source'] ) ? $log_filters['source'] : '', 'restore-checkpoint' ); ?>><?php echo esc_html__( 'Checkpoint Events', 'zignites-sentinel' ); ?></option>
							<option value="restore-maintenance" <?php selected( isset( $log_filters['source'] ) ? $log_filters['source'] : '', 'restore-maintenance' ); ?>><?php echo esc_html__( 'Maintenance Events', 'zignites-sentinel' ); ?></option>
							<option value="snapshot-health" <?php selected( isset( $log_filters['source'] ) ? $log_filters['source'] : '', 'snapshot-health' ); ?>><?php echo esc_html__( 'Snapshot Health', 'zignites-sentinel' ); ?></option>
							<option value="snapshot-audit" <?php selected( isset( $log_filters['source'] ) ? $log_filters['source'] : '', 'snapshot-audit' ); ?>><?php echo esc_html__( 'Snapshot Audit', 'zignites-sentinel' ); ?></option>
						</select>
					</p>
					<p>
						<label for="znts-log-run-id"><?php echo esc_html__( 'Run ID', 'zignites-sentinel' ); ?></label>
						<input id="znts-log-run-id" type="text" name="run_id" value="<?php echo esc_attr( isset( $log_filters['run_id'] ) ? $log_filters['run_id'] : '' ); ?>" />
					</p>
					<p>
						<label for="znts-log-snapshot-id"><?php echo esc_html__( 'Snapshot ID', 'zignites-sentinel' ); ?></label>
						<input id="znts-log-snapshot-id" type="number" min="1" name="snapshot_id" value="<?php echo esc_attr( ! empty( $log_filters['snapshot_id'] ) ? (string) $log_filters['snapshot_id'] : '' ); ?>" class="small-text" />
					</p>
					<p>
						<label for="znts-log-search"><?php echo esc_html__( 'Search', 'zignites-sentinel' ); ?></label>
						<input id="znts-log-search" type="search" name="log_search" value="<?php echo esc_attr( isset( $log_filters['search'] ) ? $log_filters['search'] : '' ); ?>" />
					</p>
					<p class="znts-filter-actions">
						<?php submit_button( __( 'Apply Filters', 'zignites-sentinel' ), 'secondary', '', false ); ?>
						<a class="button button-link" href="<?php echo esc_url( add_query_arg( array( 'page' => 'zignites-sentinel-event-logs' ), admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html__( 'Reset', 'zignites-sentinel' ); ?></a>
					</p>
				</form>
				<div class="znts-log-toolbar-actions">
					<p class="znts-inline-note"><?php echo esc_html__( 'CSV export includes the current filters and up to 5,000 matching rows, including run and snapshot context when available.', 'zignites-sentinel' ); ?></p>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="znts_export_event_logs" />
						<input type="hidden" name="severity" value="<?php echo esc_attr( isset( $log_filters['severity'] ) ? $log_filters['severity'] : '' ); ?>" />
						<input type="hidden" name="source" value="<?php echo esc_attr( isset( $log_filters['source'] ) ? $log_filters['source'] : '' ); ?>" />
						<input type="hidden" name="run_id" value="<?php echo esc_attr( isset( $log_filters['run_id'] ) ? $log_filters['run_id'] : '' ); ?>" />
						<input type="hidden" name="snapshot_id" value="<?php echo esc_attr( ! empty( $log_filters['snapshot_id'] ) ? (string) $log_filters['snapshot_id'] : '' ); ?>" />
						<input type="hidden" name="log_search" value="<?php echo esc_attr( isset( $log_filters['search'] ) ? $log_filters['search'] : '' ); ?>" />
						<?php wp_nonce_field( 'znts_export_event_logs_action' ); ?>
						<?php submit_button( __( 'Export Filtered CSV', 'zignites-sentinel' ), 'secondary', 'submit', false ); ?>
					</form>
				</div>
			</div>

			<?php if ( empty( $recent_logs ) ) : ?>
				<div class="znts-empty-state">
					<strong><?php echo esc_html__( 'No event logs match the current filters.', 'zignites-sentinel' ); ?></strong>
					<p><?php echo esc_html__( 'Clear the active filters or broaden the search to bring events back into view.', 'zignites-sentinel' ); ?></p>
				</div>
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
						<?php foreach ( $recent_logs as $log ) : ?>
							<tr>
								<td><a href="<?php echo esc_url( add_query_arg( array_merge( $base_args, array( 'paged' => $current_page, 'log_id' => (int) $log['id'] ) ), admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html( $log['created_at'] ); ?></a></td>
								<td><span class="znts-pill znts-pill-<?php echo esc_attr( isset( $log['severity_pill'] ) ? $log['severity_pill'] : 'info' ); ?>"><?php echo esc_html( isset( $log['severity_label'] ) ? $log['severity_label'] : '' ); ?></span></td>
								<td><?php echo esc_html( $log['event_type'] ); ?></td>
								<td><?php echo esc_html( $log['source'] ); ?></td>
								<td><?php echo esc_html( $log['message'] ); ?></td>
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
										'base'      => add_query_arg( array_merge( $base_args, array( 'paged' => '%#%' ) ), admin_url( 'admin.php' ) ),
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
		</section>

		<?php if ( ! empty( $operational_events ) ) : ?>
			<section class="znts-card znts-card-full znts-card-soft">
				<div class="znts-table-header">
					<div>
						<h2><?php echo esc_html__( 'Operational Events', 'zignites-sentinel' ); ?></h2>
						<p><?php echo esc_html__( 'Checkpoint invalidations, baseline captures, stage cleanup, and other supporting restore-control activity.', 'zignites-sentinel' ); ?></p>
					</div>
				</div>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php echo esc_html__( 'Time', 'zignites-sentinel' ); ?></th>
							<th><?php echo esc_html__( 'Severity', 'zignites-sentinel' ); ?></th>
							<th><?php echo esc_html__( 'Source', 'zignites-sentinel' ); ?></th>
							<th><?php echo esc_html__( 'Event', 'zignites-sentinel' ); ?></th>
							<th><?php echo esc_html__( 'Message', 'zignites-sentinel' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $operational_events as $event ) : ?>
							<tr>
								<td><a href="<?php echo esc_url( add_query_arg( array_merge( $base_args, array( 'log_id' => (int) $event['id'] ) ), admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html( $event['created_at'] ); ?></a></td>
								<td><span class="znts-pill znts-pill-<?php echo esc_attr( isset( $event['severity_pill'] ) ? $event['severity_pill'] : 'info' ); ?>"><?php echo esc_html( isset( $event['severity_label'] ) ? $event['severity_label'] : '' ); ?></span></td>
								<td><?php echo esc_html( $event['source'] ); ?></td>
								<td><?php echo esc_html( $event['event_type'] ); ?></td>
								<td><?php echo esc_html( $event['message'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</section>
		<?php endif; ?>

		<?php if ( ! empty( $run_summaries ) ) : ?>
			<section class="znts-card znts-card-full znts-card-soft">
				<div class="znts-table-header">
					<div>
						<h2><?php echo esc_html__( 'Run Summaries', 'zignites-sentinel' ); ?></h2>
						<p><?php echo esc_html__( 'Use run summaries to jump from a restore or rollback run into its persisted journal trail.', 'zignites-sentinel' ); ?></p>
					</div>
				</div>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php echo esc_html__( 'Run ID', 'zignites-sentinel' ); ?></th>
							<th><?php echo esc_html__( 'Source', 'zignites-sentinel' ); ?></th>
							<th><?php echo esc_html__( 'Snapshot', 'zignites-sentinel' ); ?></th>
							<th><?php echo esc_html__( 'Status', 'zignites-sentinel' ); ?></th>
							<th><?php echo esc_html__( 'Completed Items', 'zignites-sentinel' ); ?></th>
							<th><?php echo esc_html__( 'Entries', 'zignites-sentinel' ); ?></th>
							<th><?php echo esc_html__( 'Last Seen', 'zignites-sentinel' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $run_summaries as $summary ) : ?>
							<tr>
								<td><a href="<?php echo esc_url( add_query_arg( array( 'page' => 'zignites-sentinel-event-logs', 'source' => isset( $summary['source'] ) ? $summary['source'] : '', 'run_id' => isset( $summary['run_id'] ) ? $summary['run_id'] : '', 'snapshot_id' => isset( $summary['snapshot_id'] ) ? (int) $summary['snapshot_id'] : 0 ), admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html( isset( $summary['run_id'] ) ? $summary['run_id'] : '' ); ?></a></td>
								<td><?php echo esc_html( isset( $summary['source'] ) ? $summary['source'] : '' ); ?></td>
								<td>
									<?php if ( ! empty( $summary['snapshot_id'] ) ) : ?>
										<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'zignites-sentinel-update-readiness', 'snapshot_id' => (int) $summary['snapshot_id'] ), admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html( (string) $summary['snapshot_id'] ); ?></a>
									<?php endif; ?>
								</td>
								<td><span class="znts-pill znts-pill-<?php echo esc_attr( isset( $summary['status_pill'] ) ? $summary['status_pill'] : 'info' ); ?>"><?php echo esc_html( isset( $summary['status_label'] ) ? $summary['status_label'] : '' ); ?></span></td>
								<td><?php echo esc_html( isset( $summary['completed_item_count'] ) ? (string) $summary['completed_item_count'] : '0' ); ?></td>
								<td><?php echo esc_html( isset( $summary['entry_count'] ) ? (string) $summary['entry_count'] : '0' ); ?></td>
								<td><?php echo esc_html( isset( $summary['latest_timestamp'] ) ? $summary['latest_timestamp'] : '' ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</section>
		<?php endif; ?>

		<?php if ( ! empty( $run_journal['entries'] ) ) : ?>
			<section class="znts-card znts-card-full znts-card-soft">
				<div class="znts-table-header">
					<div>
						<h2><?php echo esc_html__( 'Run Journal', 'zignites-sentinel' ); ?></h2>
						<p><?php echo esc_html( sprintf( __( 'Viewing persisted journal entries for %1$s, run %2$s.', 'zignites-sentinel' ), isset( $run_journal['source'] ) ? $run_journal['source'] : '', isset( $run_journal['run_id'] ) ? $run_journal['run_id'] : '' ) ); ?></p>
					</div>
				</div>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php echo esc_html__( 'Time', 'zignites-sentinel' ); ?></th>
							<th><?php echo esc_html__( 'Scope', 'zignites-sentinel' ); ?></th>
							<th><?php echo esc_html__( 'Label', 'zignites-sentinel' ); ?></th>
							<th><?php echo esc_html__( 'Phase', 'zignites-sentinel' ); ?></th>
							<th><?php echo esc_html__( 'Status', 'zignites-sentinel' ); ?></th>
							<th><?php echo esc_html__( 'Message', 'zignites-sentinel' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $run_journal['entries'] as $entry ) : ?>
							<tr>
								<td><?php echo esc_html( isset( $entry['timestamp'] ) ? $entry['timestamp'] : '' ); ?></td>
								<td><?php echo esc_html( isset( $entry['scope'] ) ? $entry['scope'] : '' ); ?></td>
								<td><?php echo esc_html( isset( $entry['label'] ) ? $entry['label'] : '' ); ?></td>
								<td><?php echo esc_html( isset( $entry['phase'] ) ? $entry['phase'] : '' ); ?></td>
								<td><span class="znts-pill znts-pill-<?php echo esc_attr( isset( $entry['status_pill'] ) ? $entry['status_pill'] : 'info' ); ?>"><?php echo esc_html( isset( $entry['status_label'] ) ? $entry['status_label'] : '' ); ?></span></td>
								<td><?php echo esc_html( isset( $entry['message'] ) ? $entry['message'] : '' ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</section>
		<?php endif; ?>

		<?php if ( $log_detail ) : ?>
			<section class="znts-card znts-card-full znts-card-muted">
				<div class="znts-table-header">
					<div>
						<h2><?php echo esc_html__( 'Event Detail', 'zignites-sentinel' ); ?></h2>
						<p><?php echo esc_html__( 'Review the full event record and structured JSON context captured at the time of the event.', 'zignites-sentinel' ); ?></p>
					</div>
				</div>
				<table class="widefat striped">
					<tbody>
						<tr><th scope="row"><?php echo esc_html__( 'Time', 'zignites-sentinel' ); ?></th><td><?php echo esc_html( $log_detail['created_at'] ); ?></td></tr>
						<tr><th scope="row"><?php echo esc_html__( 'Severity', 'zignites-sentinel' ); ?></th><td><?php echo esc_html( ucfirst( $log_detail['severity'] ) ); ?></td></tr>
						<tr><th scope="row"><?php echo esc_html__( 'Event', 'zignites-sentinel' ); ?></th><td><?php echo esc_html( $log_detail['event_type'] ); ?></td></tr>
						<tr><th scope="row"><?php echo esc_html__( 'Source', 'zignites-sentinel' ); ?></th><td><?php echo esc_html( $log_detail['source'] ); ?></td></tr>
						<tr><th scope="row"><?php echo esc_html__( 'Message', 'zignites-sentinel' ); ?></th><td><?php echo esc_html( $log_detail['message'] ); ?></td></tr>
					</tbody>
				</table>
				<h3><?php echo esc_html__( 'Context', 'zignites-sentinel' ); ?></h3>
				<?php if ( empty( $log_detail['context_decoded'] ) ) : ?>
					<div class="znts-empty-state">
						<strong><?php echo esc_html__( 'No structured context was captured.', 'zignites-sentinel' ); ?></strong>
						<p><?php echo esc_html__( 'Some events are intentionally lightweight and only store the primary message.', 'zignites-sentinel' ); ?></p>
					</div>
				<?php else : ?>
					<pre class="znts-json-block"><?php echo esc_html( wp_json_encode( $log_detail['context_decoded'], JSON_PRETTY_PRINT ) ); ?></pre>
				<?php endif; ?>
			</section>
		<?php endif; ?>
	</div>
</div>
