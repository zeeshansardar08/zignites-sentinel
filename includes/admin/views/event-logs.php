<?php
/**
 * Event logs admin view.
 *
 * @package ZignitesSentinel
 */

defined( 'ABSPATH' ) || exit;

$recent_logs = isset( $view_data['recent_logs'] ) && is_array( $view_data['recent_logs'] ) ? $view_data['recent_logs'] : array();
$log_detail  = isset( $view_data['log_detail'] ) && is_array( $view_data['log_detail'] ) ? $view_data['log_detail'] : null;
$log_filters = isset( $view_data['log_filters'] ) && is_array( $view_data['log_filters'] ) ? $view_data['log_filters'] : array();
$operational_events = isset( $view_data['operational_events'] ) && is_array( $view_data['operational_events'] ) ? $view_data['operational_events'] : array();
$run_summaries = isset( $view_data['run_summaries'] ) && is_array( $view_data['run_summaries'] ) ? $view_data['run_summaries'] : array();
$run_journal = isset( $view_data['run_journal'] ) && is_array( $view_data['run_journal'] ) ? $view_data['run_journal'] : array();
$pagination  = isset( $view_data['pagination'] ) && is_array( $view_data['pagination'] ) ? $view_data['pagination'] : array();
$current_page = isset( $pagination['current_page'] ) ? (int) $pagination['current_page'] : 1;
$total_pages  = isset( $pagination['total_pages'] ) ? (int) $pagination['total_pages'] : 1;
$base_args    = array(
	'page'       => 'zignites-sentinel-event-logs',
	'severity'   => isset( $log_filters['severity'] ) ? $log_filters['severity'] : '',
	'source'     => isset( $log_filters['source'] ) ? $log_filters['source'] : '',
	'run_id'     => isset( $log_filters['run_id'] ) ? $log_filters['run_id'] : '',
	'snapshot_id'=> isset( $log_filters['snapshot_id'] ) ? $log_filters['snapshot_id'] : 0,
	'log_search' => isset( $log_filters['search'] ) ? $log_filters['search'] : '',
);
?>
<div class="wrap znts-admin-page">
	<h1><?php echo esc_html__( 'Event Logs', 'zignites-sentinel' ); ?></h1>
	<p><?php echo esc_html__( 'Inspect recent Sentinel events and review structured context captured for update planning, diagnostics, and restore assessments.', 'zignites-sentinel' ); ?></p>

	<div class="znts-admin-grid">
		<section class="znts-card znts-card-full">
			<h2><?php echo esc_html__( 'Event Log Explorer', 'zignites-sentinel' ); ?></h2>
			<form method="get" class="znts-filter-form">
				<input type="hidden" name="page" value="zignites-sentinel-event-logs" />
				<label for="znts-log-severity"><?php echo esc_html__( 'Severity', 'zignites-sentinel' ); ?></label>
				<select id="znts-log-severity" name="severity">
					<option value=""><?php echo esc_html__( 'All severities', 'zignites-sentinel' ); ?></option>
					<option value="info" <?php selected( isset( $log_filters['severity'] ) ? $log_filters['severity'] : '', 'info' ); ?>><?php echo esc_html__( 'Info', 'zignites-sentinel' ); ?></option>
					<option value="warning" <?php selected( isset( $log_filters['severity'] ) ? $log_filters['severity'] : '', 'warning' ); ?>><?php echo esc_html__( 'Warning', 'zignites-sentinel' ); ?></option>
					<option value="error" <?php selected( isset( $log_filters['severity'] ) ? $log_filters['severity'] : '', 'error' ); ?>><?php echo esc_html__( 'Error', 'zignites-sentinel' ); ?></option>
					<option value="critical" <?php selected( isset( $log_filters['severity'] ) ? $log_filters['severity'] : '', 'critical' ); ?>><?php echo esc_html__( 'Critical', 'zignites-sentinel' ); ?></option>
				</select>
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
				<label for="znts-log-run-id"><?php echo esc_html__( 'Run ID', 'zignites-sentinel' ); ?></label>
				<input id="znts-log-run-id" type="text" name="run_id" value="<?php echo esc_attr( isset( $log_filters['run_id'] ) ? $log_filters['run_id'] : '' ); ?>" />
				<label for="znts-log-snapshot-id"><?php echo esc_html__( 'Snapshot ID', 'zignites-sentinel' ); ?></label>
				<input id="znts-log-snapshot-id" type="number" min="1" name="snapshot_id" value="<?php echo esc_attr( ! empty( $log_filters['snapshot_id'] ) ? (string) $log_filters['snapshot_id'] : '' ); ?>" class="small-text" />
				<label for="znts-log-search"><?php echo esc_html__( 'Search', 'zignites-sentinel' ); ?></label>
				<input id="znts-log-search" type="search" name="log_search" value="<?php echo esc_attr( isset( $log_filters['search'] ) ? $log_filters['search'] : '' ); ?>" />
				<?php submit_button( __( 'Filter Logs', 'zignites-sentinel' ), 'secondary', '', false ); ?>
				<a class="button button-link" href="<?php echo esc_url( add_query_arg( array( 'page' => 'zignites-sentinel-event-logs' ), admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html__( 'Reset', 'zignites-sentinel' ); ?></a>
			</form>
			<p class="description">
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: current page, 2: total pages, 3: total logs */
						__( 'Page %1$d of %2$d, %3$d matching events.', 'zignites-sentinel' ),
						max( 1, $current_page ),
						max( 1, $total_pages ),
						isset( $pagination['total_logs'] ) ? (int) $pagination['total_logs'] : 0
					)
				);
				?>
			</p>
			<?php if ( empty( $recent_logs ) ) : ?>
				<p><?php echo esc_html__( 'No event logs match the current filters.', 'zignites-sentinel' ); ?></p>
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
								<td>
									<a href="<?php echo esc_url( add_query_arg( array_merge( $base_args, array( 'paged' => $current_page, 'log_id' => (int) $log['id'] ) ), admin_url( 'admin.php' ) ) ); ?>">
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
			<section class="znts-card znts-card-full">
				<h2><?php echo esc_html__( 'Restore Operational Events', 'zignites-sentinel' ); ?></h2>
				<p><?php echo esc_html__( 'Recent checkpoint invalidations, health baseline captures, stage cleanup operations, and other non-journal restore maintenance activity.', 'zignites-sentinel' ); ?></p>
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
								<td>
									<a href="<?php echo esc_url( add_query_arg( array_merge( $base_args, array( 'log_id' => (int) $event['id'] ) ), admin_url( 'admin.php' ) ) ); ?>">
										<?php echo esc_html( $event['created_at'] ); ?>
									</a>
								</td>
								<td>
									<span class="znts-pill znts-pill-<?php echo esc_attr( $event['severity'] ); ?>">
										<?php echo esc_html( ucfirst( $event['severity'] ) ); ?>
									</span>
								</td>
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
			<section class="znts-card znts-card-full">
				<h2><?php echo esc_html__( 'Run Summaries', 'zignites-sentinel' ); ?></h2>
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
								<td>
									<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'zignites-sentinel-event-logs', 'source' => isset( $summary['source'] ) ? $summary['source'] : '', 'run_id' => isset( $summary['run_id'] ) ? $summary['run_id'] : '', 'snapshot_id' => isset( $summary['snapshot_id'] ) ? (int) $summary['snapshot_id'] : 0 ), admin_url( 'admin.php' ) ) ); ?>">
										<?php echo esc_html( isset( $summary['run_id'] ) ? $summary['run_id'] : '' ); ?>
									</a>
								</td>
								<td><?php echo esc_html( isset( $summary['source'] ) ? $summary['source'] : '' ); ?></td>
								<td>
									<?php if ( ! empty( $summary['snapshot_id'] ) ) : ?>
										<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'zignites-sentinel-update-readiness', 'snapshot_id' => (int) $summary['snapshot_id'] ), admin_url( 'admin.php' ) ) ); ?>">
											<?php echo esc_html( (string) $summary['snapshot_id'] ); ?>
										</a>
									<?php endif; ?>
								</td>
								<td>
									<span class="znts-pill znts-pill-<?php echo esc_attr( 'fail' === ( isset( $summary['status_badge'] ) ? $summary['status_badge'] : '' ) || 'blocked' === ( isset( $summary['status_badge'] ) ? $summary['status_badge'] : '' ) ? 'critical' : ( 'partial' === ( isset( $summary['status_badge'] ) ? $summary['status_badge'] : '' ) || 'warning' === ( isset( $summary['status_badge'] ) ? $summary['status_badge'] : '' ) ? 'warning' : 'info' ) ); ?>">
										<?php echo esc_html( ucfirst( isset( $summary['status_badge'] ) ? $summary['status_badge'] : '' ) ); ?>
									</span>
								</td>
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
			<section class="znts-card znts-card-full">
				<h2><?php echo esc_html__( 'Run Journal', 'zignites-sentinel' ); ?></h2>
				<p>
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: journal source, 2: run id */
							__( 'Viewing persisted journal entries for %1$s, run %2$s.', 'zignites-sentinel' ),
							isset( $run_journal['source'] ) ? $run_journal['source'] : '',
							isset( $run_journal['run_id'] ) ? $run_journal['run_id'] : ''
						)
					);
					?>
				</p>
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
								<td>
									<span class="znts-pill znts-pill-<?php echo esc_attr( 'fail' === ( isset( $entry['status'] ) ? $entry['status'] : '' ) ? 'critical' : ( isset( $entry['status'] ) ? $entry['status'] : 'info' ) ); ?>">
										<?php echo esc_html( ucfirst( isset( $entry['status'] ) ? $entry['status'] : '' ) ); ?>
									</span>
								</td>
								<td><?php echo esc_html( isset( $entry['message'] ) ? $entry['message'] : '' ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</section>
		<?php endif; ?>

		<?php if ( $log_detail ) : ?>
			<section class="znts-card znts-card-full">
				<h2><?php echo esc_html__( 'Event Detail', 'zignites-sentinel' ); ?></h2>
				<table class="widefat striped">
					<tbody>
						<tr>
							<th scope="row"><?php echo esc_html__( 'Time', 'zignites-sentinel' ); ?></th>
							<td><?php echo esc_html( $log_detail['created_at'] ); ?></td>
						</tr>
						<tr>
							<th scope="row"><?php echo esc_html__( 'Severity', 'zignites-sentinel' ); ?></th>
							<td><?php echo esc_html( ucfirst( $log_detail['severity'] ) ); ?></td>
						</tr>
						<tr>
							<th scope="row"><?php echo esc_html__( 'Event', 'zignites-sentinel' ); ?></th>
							<td><?php echo esc_html( $log_detail['event_type'] ); ?></td>
						</tr>
						<tr>
							<th scope="row"><?php echo esc_html__( 'Source', 'zignites-sentinel' ); ?></th>
							<td><?php echo esc_html( $log_detail['source'] ); ?></td>
						</tr>
						<tr>
							<th scope="row"><?php echo esc_html__( 'Message', 'zignites-sentinel' ); ?></th>
							<td><?php echo esc_html( $log_detail['message'] ); ?></td>
						</tr>
					</tbody>
				</table>

				<h3><?php echo esc_html__( 'Context', 'zignites-sentinel' ); ?></h3>
				<?php if ( empty( $log_detail['context_decoded'] ) ) : ?>
					<p><?php echo esc_html__( 'No structured context was captured for this event.', 'zignites-sentinel' ); ?></p>
				<?php else : ?>
					<pre class="znts-json-block"><?php echo esc_html( wp_json_encode( $log_detail['context_decoded'], JSON_PRETTY_PRINT ) ); ?></pre>
				<?php endif; ?>
			</section>
		<?php endif; ?>
	</div>
</div>
