<?php
/**
 * Update readiness admin view.
 *
 * @package ZignitesSentinel
 */

defined( 'ABSPATH' ) || exit;

$preflight                 = isset( $view_data['last_preflight'] ) && is_array( $view_data['last_preflight'] ) ? $view_data['last_preflight'] : array();
$last_plan                 = isset( $view_data['last_update_plan'] ) && is_array( $view_data['last_update_plan'] ) ? $view_data['last_update_plan'] : array();
$last_restore_check        = isset( $view_data['last_restore_check'] ) && is_array( $view_data['last_restore_check'] ) ? $view_data['last_restore_check'] : array();
$settings                  = isset( $view_data['settings'] ) && is_array( $view_data['settings'] ) ? $view_data['settings'] : array();
$notice                    = isset( $view_data['notice'] ) && is_array( $view_data['notice'] ) ? $view_data['notice'] : array();
$snapshot_detail           = isset( $view_data['snapshot_detail'] ) && is_array( $view_data['snapshot_detail'] ) ? $view_data['snapshot_detail'] : null;
$snapshot_comparison       = isset( $view_data['snapshot_comparison'] ) && is_array( $view_data['snapshot_comparison'] ) ? $view_data['snapshot_comparison'] : array();
$snapshot_artifacts        = isset( $view_data['snapshot_artifacts'] ) && is_array( $view_data['snapshot_artifacts'] ) ? $view_data['snapshot_artifacts'] : array();
$artifact_diff            = isset( $view_data['artifact_diff'] ) && is_array( $view_data['artifact_diff'] ) ? $view_data['artifact_diff'] : array();
$last_restore_dry_run     = isset( $view_data['last_restore_dry_run'] ) && is_array( $view_data['last_restore_dry_run'] ) ? $view_data['last_restore_dry_run'] : array();
$last_restore_stage       = isset( $view_data['last_restore_stage'] ) && is_array( $view_data['last_restore_stage'] ) ? $view_data['last_restore_stage'] : array();
$last_restore_plan        = isset( $view_data['last_restore_plan'] ) && is_array( $view_data['last_restore_plan'] ) ? $view_data['last_restore_plan'] : array();
$last_restore_execution   = isset( $view_data['last_restore_execution'] ) && is_array( $view_data['last_restore_execution'] ) ? $view_data['last_restore_execution'] : array();
$last_restore_rollback    = isset( $view_data['last_restore_rollback'] ) && is_array( $view_data['last_restore_rollback'] ) ? $view_data['last_restore_rollback'] : array();
$stage_checkpoint         = isset( $view_data['stage_checkpoint'] ) && is_array( $view_data['stage_checkpoint'] ) ? $view_data['stage_checkpoint'] : array();
$plan_checkpoint          = isset( $view_data['plan_checkpoint'] ) && is_array( $view_data['plan_checkpoint'] ) ? $view_data['plan_checkpoint'] : array();
$execution_checkpoint     = isset( $view_data['execution_checkpoint'] ) && is_array( $view_data['execution_checkpoint'] ) ? $view_data['execution_checkpoint'] : array();
$execution_checkpoint_summary = isset( $view_data['execution_checkpoint_summary'] ) && is_array( $view_data['execution_checkpoint_summary'] ) ? $view_data['execution_checkpoint_summary'] : array();
$rollback_checkpoint      = isset( $view_data['rollback_checkpoint'] ) && is_array( $view_data['rollback_checkpoint'] ) ? $view_data['rollback_checkpoint'] : array();
$rollback_checkpoint_summary = isset( $view_data['rollback_checkpoint_summary'] ) && is_array( $view_data['rollback_checkpoint_summary'] ) ? $view_data['rollback_checkpoint_summary'] : array();
$restore_run_cards        = isset( $view_data['restore_run_cards'] ) && is_array( $view_data['restore_run_cards'] ) ? $view_data['restore_run_cards'] : array();
$restore_resume_context   = isset( $view_data['restore_resume_context'] ) && is_array( $view_data['restore_resume_context'] ) ? $view_data['restore_resume_context'] : array();
$restore_rollback_resume_context = isset( $view_data['restore_rollback_resume_context'] ) && is_array( $view_data['restore_rollback_resume_context'] ) ? $view_data['restore_rollback_resume_context'] : array();
$snapshot_health_baseline = isset( $view_data['snapshot_health_baseline'] ) && is_array( $view_data['snapshot_health_baseline'] ) ? $view_data['snapshot_health_baseline'] : array();
$snapshot_health_comparison = isset( $view_data['snapshot_health_comparison'] ) && is_array( $view_data['snapshot_health_comparison'] ) ? $view_data['snapshot_health_comparison'] : array();
$snapshot_summary       = isset( $view_data['snapshot_summary'] ) && is_array( $view_data['snapshot_summary'] ) ? $view_data['snapshot_summary'] : array();
$operator_checklist      = isset( $view_data['operator_checklist'] ) && is_array( $view_data['operator_checklist'] ) ? $view_data['operator_checklist'] : array();
$restore_impact_summary  = isset( $view_data['restore_impact_summary'] ) && is_array( $view_data['restore_impact_summary'] ) ? $view_data['restore_impact_summary'] : array();
$audit_report_verification = isset( $view_data['audit_report_verification'] ) && is_array( $view_data['audit_report_verification'] ) ? $view_data['audit_report_verification'] : array();
$snapshot_activity       = isset( $view_data['snapshot_activity'] ) && is_array( $view_data['snapshot_activity'] ) ? $view_data['snapshot_activity'] : array();
$snapshot_activity_url   = isset( $view_data['snapshot_activity_url'] ) ? (string) $view_data['snapshot_activity_url'] : '';
$snapshot_search         = isset( $view_data['snapshot_search'] ) ? (string) $view_data['snapshot_search'] : '';
$snapshot_status_filter  = isset( $view_data['snapshot_status_filter'] ) ? (string) $view_data['snapshot_status_filter'] : '';
$snapshot_status_filter_options = isset( $view_data['snapshot_status_filter_options'] ) && is_array( $view_data['snapshot_status_filter_options'] ) ? $view_data['snapshot_status_filter_options'] : array();
$snapshot_status_index   = isset( $view_data['snapshot_status_index'] ) && is_array( $view_data['snapshot_status_index'] ) ? $view_data['snapshot_status_index'] : array();
$snapshot_pagination     = isset( $view_data['snapshot_pagination'] ) && is_array( $view_data['snapshot_pagination'] ) ? $view_data['snapshot_pagination'] : array();
$selected_snapshot_status  = isset( $view_data['selected_snapshot_status'] ) && is_array( $view_data['selected_snapshot_status'] ) ? $view_data['selected_snapshot_status'] : array();
$plan_validation           = isset( $view_data['plan_validation'] ) && is_array( $view_data['plan_validation'] ) ? $view_data['plan_validation'] : array();
$plan_validation_check_rows = isset( $view_data['plan_validation_check_rows'] ) && is_array( $view_data['plan_validation_check_rows'] ) ? $view_data['plan_validation_check_rows'] : array();
$restore_source_validation = isset( $view_data['restore_source_validation'] ) && is_array( $view_data['restore_source_validation'] ) ? $view_data['restore_source_validation'] : array();
$restore_source_validation_check_rows = isset( $view_data['restore_source_validation_check_rows'] ) && is_array( $view_data['restore_source_validation_check_rows'] ) ? $view_data['restore_source_validation_check_rows'] : array();
$restore_source_missing_plugins = isset( $view_data['restore_source_missing_plugins'] ) && is_array( $view_data['restore_source_missing_plugins'] ) ? $view_data['restore_source_missing_plugins'] : array();
$restore_source_missing_artifacts = isset( $view_data['restore_source_missing_artifacts'] ) && is_array( $view_data['restore_source_missing_artifacts'] ) ? $view_data['restore_source_missing_artifacts'] : array();
$restore_dry_run_status  = isset( $view_data['restore_dry_run_status'] ) && is_array( $view_data['restore_dry_run_status'] ) ? $view_data['restore_dry_run_status'] : array();
$restore_dry_run_check_rows = isset( $view_data['restore_dry_run_check_rows'] ) && is_array( $view_data['restore_dry_run_check_rows'] ) ? $view_data['restore_dry_run_check_rows'] : array();
$restore_stage_status    = isset( $view_data['restore_stage_status'] ) && is_array( $view_data['restore_stage_status'] ) ? $view_data['restore_stage_status'] : array();
$restore_stage_check_rows = isset( $view_data['restore_stage_check_rows'] ) && is_array( $view_data['restore_stage_check_rows'] ) ? $view_data['restore_stage_check_rows'] : array();
$restore_plan_status     = isset( $view_data['restore_plan_status'] ) && is_array( $view_data['restore_plan_status'] ) ? $view_data['restore_plan_status'] : array();
$restore_plan_check_rows = isset( $view_data['restore_plan_check_rows'] ) && is_array( $view_data['restore_plan_check_rows'] ) ? $view_data['restore_plan_check_rows'] : array();
$restore_plan_item_rows  = isset( $view_data['restore_plan_item_rows'] ) && is_array( $view_data['restore_plan_item_rows'] ) ? $view_data['restore_plan_item_rows'] : array();
$restore_execution_status = isset( $view_data['restore_execution_status'] ) && is_array( $view_data['restore_execution_status'] ) ? $view_data['restore_execution_status'] : array();
$restore_execution_health_status = isset( $view_data['restore_execution_health_status'] ) && is_array( $view_data['restore_execution_health_status'] ) ? $view_data['restore_execution_health_status'] : array();
$restore_execution_health_check_rows = isset( $view_data['restore_execution_health_check_rows'] ) && is_array( $view_data['restore_execution_health_check_rows'] ) ? $view_data['restore_execution_health_check_rows'] : array();
$restore_execution_check_rows = isset( $view_data['restore_execution_check_rows'] ) && is_array( $view_data['restore_execution_check_rows'] ) ? $view_data['restore_execution_check_rows'] : array();
$restore_execution_item_rows = isset( $view_data['restore_execution_item_rows'] ) && is_array( $view_data['restore_execution_item_rows'] ) ? $view_data['restore_execution_item_rows'] : array();
$restore_execution_journal_rows = isset( $view_data['restore_execution_journal_rows'] ) && is_array( $view_data['restore_execution_journal_rows'] ) ? $view_data['restore_execution_journal_rows'] : array();
$restore_rollback_status = isset( $view_data['restore_rollback_status'] ) && is_array( $view_data['restore_rollback_status'] ) ? $view_data['restore_rollback_status'] : array();
$restore_rollback_health_status = isset( $view_data['restore_rollback_health_status'] ) && is_array( $view_data['restore_rollback_health_status'] ) ? $view_data['restore_rollback_health_status'] : array();
$restore_rollback_check_rows = isset( $view_data['restore_rollback_check_rows'] ) && is_array( $view_data['restore_rollback_check_rows'] ) ? $view_data['restore_rollback_check_rows'] : array();
$restore_rollback_item_rows = isset( $view_data['restore_rollback_item_rows'] ) && is_array( $view_data['restore_rollback_item_rows'] ) ? $view_data['restore_rollback_item_rows'] : array();
$restore_rollback_journal_rows = isset( $view_data['restore_rollback_journal_rows'] ) && is_array( $view_data['restore_rollback_journal_rows'] ) ? $view_data['restore_rollback_journal_rows'] : array();
$component_manifest        = isset( $view_data['component_manifest'] ) && is_array( $view_data['component_manifest'] ) ? $view_data['component_manifest'] : array();
$selected_snapshot_label   = isset( $view_data['selected_snapshot_label'] ) ? (string) $view_data['selected_snapshot_label'] : '';
$selected_snapshot_note    = isset( $view_data['selected_snapshot_note'] ) ? (string) $view_data['selected_snapshot_note'] : '';
$snapshot_match_count      = isset( $view_data['snapshot_match_count'] ) ? (int) $view_data['snapshot_match_count'] : 0;
$workspace_status_label    = isset( $view_data['workspace_status_label'] ) ? (string) $view_data['workspace_status_label'] : '';
$workspace_status_badge    = isset( $view_data['workspace_status_badge'] ) ? (string) $view_data['workspace_status_badge'] : 'info';
$workspace_next_action     = isset( $view_data['workspace_next_action'] ) ? (string) $view_data['workspace_next_action'] : '';
$snapshot_primary_risk     = isset( $view_data['snapshot_primary_risk'] ) ? (string) $view_data['snapshot_primary_risk'] : '';
$snapshot_primary_step     = isset( $view_data['snapshot_primary_step'] ) ? (string) $view_data['snapshot_primary_step'] : '';
$health_attention_state    = isset( $view_data['health_attention_state'] ) ? (string) $view_data['health_attention_state'] : 'info';
$health_attention_message  = isset( $view_data['health_attention_message'] ) ? (string) $view_data['health_attention_message'] : '';
$open_health_validation    = ! empty( $view_data['open_health_validation'] );
$workspace_flow_message    = isset( $view_data['workspace_flow_message'] ) ? (string) $view_data['workspace_flow_message'] : '';
$workspace_confidence      = isset( $view_data['workspace_confidence'] ) ? (string) $view_data['workspace_confidence'] : '';
?>
<div class="wrap znts-admin-page">
	<div class="znts-page-header">
		<h1><?php echo esc_html__( 'Update Readiness', 'zignites-sentinel' ); ?></h1>
		<p class="znts-page-intro"><?php echo esc_html__( 'Use this workspace to answer one question quickly: is the site prepared for safe update work, and what should happen next?', 'zignites-sentinel' ); ?></p>
	</div>

	<?php if ( ! empty( $notice ) ) : ?>
		<div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> is-dismissible">
			<p><?php echo esc_html( $notice['message'] ); ?></p>
		</div>
	<?php endif; ?>

	<section class="znts-summary-hero">
		<span class="znts-eyebrow"><?php echo esc_html__( 'Operational Workspace', 'zignites-sentinel' ); ?></span>
		<div class="znts-readiness-row">
			<span class="znts-pill znts-pill-<?php echo esc_attr( $workspace_status_badge ); ?>">
				<?php echo esc_html( $workspace_status_label ); ?>
			</span>
			<?php if ( $snapshot_detail && ! empty( $selected_snapshot_status['status_badges'] ) ) : ?>
				<div class="znts-badge-row">
					<?php foreach ( $selected_snapshot_status['status_badges'] as $badge ) : ?>
						<span class="znts-pill znts-pill-<?php echo esc_attr( isset( $badge['badge'] ) ? $badge['badge'] : 'info' ); ?>">
							<?php echo esc_html( isset( $badge['label'] ) ? $badge['label'] : '' ); ?>
						</span>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		<h2 class="znts-hero-title"><?php echo esc_html( $selected_snapshot_label ); ?></h2>
		<p class="znts-hero-subtitle"><?php echo esc_html( $selected_snapshot_note ); ?></p>
		<div class="znts-summary-strip">
			<div class="znts-summary-item">
				<span><?php echo esc_html__( 'Current state', 'zignites-sentinel' ); ?></span>
				<strong><?php echo esc_html( $workspace_status_label ); ?></strong>
				<p><?php echo esc_html( $workspace_next_action ); ?></p>
			</div>
			<div class="znts-summary-item">
				<span><?php echo esc_html__( 'Snapshot matches', 'zignites-sentinel' ); ?></span>
				<strong><?php echo esc_html( (string) $snapshot_match_count ); ?></strong>
				<p><?php echo esc_html__( 'Use filters to narrow the working snapshot set before reviewing details.', 'zignites-sentinel' ); ?></p>
			</div>
			<div class="znts-summary-item">
				<span><?php echo esc_html__( 'Checklist gates', 'zignites-sentinel' ); ?></span>
				<strong><?php echo esc_html( ! empty( $operator_checklist['checks'] ) ? sprintf( __( '%d checks', 'zignites-sentinel' ), count( $operator_checklist['checks'] ) ) : __( 'Not started', 'zignites-sentinel' ) ); ?></strong>
				<p><?php echo esc_html__( 'Baseline, stage validation, and plan freshness determine whether guarded restore is offered.', 'zignites-sentinel' ); ?></p>
			</div>
			<div class="znts-summary-item">
				<span><?php echo esc_html__( 'Primary path', 'zignites-sentinel' ); ?></span>
				<strong><?php echo esc_html__( 'Scan, snapshot, review', 'zignites-sentinel' ); ?></strong>
				<p><?php echo esc_html__( 'Keep live restore controls gated until readiness evidence is current.', 'zignites-sentinel' ); ?></p>
			</div>
		</div>
		<div class="znts-flow-note">
			<strong><?php echo esc_html__( 'Next in workflow', 'zignites-sentinel' ); ?></strong>
			<span><?php echo esc_html( $workspace_flow_message ); ?></span>
		</div>
		<p class="znts-summary-confidence"><?php echo esc_html( $workspace_confidence ); ?></p>
	</section>

	<div class="znts-admin-grid znts-readiness-grid">
		<?php if ( $snapshot_detail && ! empty( $restore_run_cards ) ) : ?>
			<section class="znts-card znts-card-full znts-card-primary znts-card-hero">
				<h2><?php echo esc_html__( 'Restore Control Summary', 'zignites-sentinel' ); ?></h2>
				<div class="znts-status-grid">
					<?php foreach ( $restore_run_cards as $card ) : ?>
						<section class="znts-status-card">
							<div class="znts-readiness-row">
								<span class="znts-pill znts-pill-<?php echo esc_attr( isset( $card['badge'] ) ? $card['badge'] : 'info' ); ?>">
									<?php echo esc_html( isset( $card['status_label'] ) ? (string) $card['status_label'] : '' ); ?>
								</span>
								<span><?php echo esc_html( isset( $card['timestamp'] ) ? (string) $card['timestamp'] : '' ); ?></span>
							</div>
							<h3><?php echo esc_html( isset( $card['title'] ) ? (string) $card['title'] : '' ); ?></h3>
							<p><?php echo esc_html( isset( $card['primary'] ) ? (string) $card['primary'] : '' ); ?></p>
							<?php if ( ! empty( $card['secondary'] ) ) : ?>
								<p class="description"><?php echo esc_html( (string) $card['secondary'] ); ?></p>
							<?php endif; ?>
							<?php if ( ! empty( $card['link_url'] ) && ! empty( $card['link_label'] ) ) : ?>
								<p><a href="<?php echo esc_url( $card['link_url'] ); ?>"><?php echo esc_html( (string) $card['link_label'] ); ?></a></p>
							<?php endif; ?>
						</section>
					<?php endforeach; ?>
				</div>
				<?php if ( ! empty( $stage_checkpoint ) || ! empty( $plan_checkpoint ) ) : ?>
					<p class="description"><?php echo esc_html__( 'Stage and plan checkpoints are pinned to the current snapshot package fingerprint and are reused during resume when they still match.', 'zignites-sentinel' ); ?></p>
				<?php endif; ?>
				<?php if ( ! empty( $execution_checkpoint_summary ) ) : ?>
					<h3><?php echo esc_html__( 'Execution Checkpoint', 'zignites-sentinel' ); ?></h3>
					<table class="widefat striped">
						<tbody>
							<tr>
								<th scope="row"><?php echo esc_html__( 'Run ID', 'zignites-sentinel' ); ?></th>
								<td><?php echo esc_html( isset( $execution_checkpoint_summary['run_id'] ) ? (string) $execution_checkpoint_summary['run_id'] : '' ); ?></td>
							</tr>
							<tr>
								<th scope="row"><?php echo esc_html__( 'Generated', 'zignites-sentinel' ); ?></th>
								<td><?php echo esc_html( isset( $execution_checkpoint_summary['generated_at'] ) ? (string) $execution_checkpoint_summary['generated_at'] : '' ); ?></td>
							</tr>
							<tr>
								<th scope="row"><?php echo esc_html__( 'Stage Reuse', 'zignites-sentinel' ); ?></th>
								<td><?php echo esc_html( ! empty( $execution_checkpoint_summary['stage_ready'] ) ? __( 'Ready', 'zignites-sentinel' ) : __( 'Not available', 'zignites-sentinel' ) ); ?></td>
							</tr>
							<tr>
								<th scope="row"><?php echo esc_html__( 'Stage Path', 'zignites-sentinel' ); ?></th>
								<td><?php echo esc_html( isset( $execution_checkpoint_summary['stage_path'] ) ? (string) $execution_checkpoint_summary['stage_path'] : '' ); ?></td>
							</tr>
							<tr>
								<th scope="row"><?php echo esc_html__( 'Health Reuse', 'zignites-sentinel' ); ?></th>
								<td><?php echo esc_html( ! empty( $execution_checkpoint_summary['health_completed'] ) ? __( 'Ready', 'zignites-sentinel' ) : __( 'Health will rerun', 'zignites-sentinel' ) ); ?></td>
							</tr>
							<tr>
								<th scope="row"><?php echo esc_html__( 'Backed-Up Items', 'zignites-sentinel' ); ?></th>
								<td><?php echo esc_html( isset( $execution_checkpoint_summary['backup_count'] ) ? (string) $execution_checkpoint_summary['backup_count'] : '0' ); ?></td>
							</tr>
							<tr>
								<th scope="row"><?php echo esc_html__( 'Written Items', 'zignites-sentinel' ); ?></th>
								<td><?php echo esc_html( isset( $execution_checkpoint_summary['write_count'] ) ? (string) $execution_checkpoint_summary['write_count'] : '0' ); ?></td>
							</tr>
							<tr>
								<th scope="row"><?php echo esc_html__( 'Tracked Items', 'zignites-sentinel' ); ?></th>
								<td><?php echo esc_html( isset( $execution_checkpoint_summary['item_count'] ) ? (string) $execution_checkpoint_summary['item_count'] : '0' ); ?></td>
							</tr>
							<tr>
								<th scope="row"><?php echo esc_html__( 'Failed Items', 'zignites-sentinel' ); ?></th>
								<td><?php echo esc_html( isset( $execution_checkpoint_summary['failed_count'] ) ? (string) $execution_checkpoint_summary['failed_count'] : '0' ); ?></td>
							</tr>
							<?php if ( ! empty( $execution_checkpoint_summary['phase_counts'] ) ) : ?>
								<tr>
									<th scope="row"><?php echo esc_html__( 'Checkpoint Phases', 'zignites-sentinel' ); ?></th>
									<td><?php echo esc_html( implode( ', ', array_map( static function ( $phase, $count ) { return sprintf( '%s (%d)', str_replace( '_', ' ', (string) $phase ), (int) $count ); }, array_keys( $execution_checkpoint_summary['phase_counts'] ), $execution_checkpoint_summary['phase_counts'] ) ) ); ?></td>
								</tr>
							<?php endif; ?>
							<?php if ( ! empty( $execution_checkpoint_summary['health_status'] ) ) : ?>
								<tr>
									<th scope="row"><?php echo esc_html__( 'Stored Health Status', 'zignites-sentinel' ); ?></th>
									<td><?php echo esc_html( (string) $execution_checkpoint_summary['health_status'] ); ?></td>
								</tr>
							<?php endif; ?>
						</tbody>
					</table>
				<?php endif; ?>
				<?php if ( ! empty( $rollback_checkpoint_summary ) ) : ?>
					<h3><?php echo esc_html__( 'Rollback Checkpoint', 'zignites-sentinel' ); ?></h3>
					<table class="widefat striped">
						<tbody>
							<tr>
								<th scope="row"><?php echo esc_html__( 'Run ID', 'zignites-sentinel' ); ?></th>
								<td><?php echo esc_html( isset( $rollback_checkpoint_summary['run_id'] ) ? (string) $rollback_checkpoint_summary['run_id'] : '' ); ?></td>
							</tr>
							<tr>
								<th scope="row"><?php echo esc_html__( 'Generated', 'zignites-sentinel' ); ?></th>
								<td><?php echo esc_html( isset( $rollback_checkpoint_summary['generated_at'] ) ? (string) $rollback_checkpoint_summary['generated_at'] : '' ); ?></td>
							</tr>
							<tr>
								<th scope="row"><?php echo esc_html__( 'Backup Root', 'zignites-sentinel' ); ?></th>
								<td><?php echo esc_html( isset( $rollback_checkpoint_summary['backup_root'] ) ? (string) $rollback_checkpoint_summary['backup_root'] : '' ); ?></td>
							</tr>
							<tr>
								<th scope="row"><?php echo esc_html__( 'Tracked Items', 'zignites-sentinel' ); ?></th>
								<td><?php echo esc_html( isset( $rollback_checkpoint_summary['item_count'] ) ? (string) $rollback_checkpoint_summary['item_count'] : '0' ); ?></td>
							</tr>
							<tr>
								<th scope="row"><?php echo esc_html__( 'Completed Items', 'zignites-sentinel' ); ?></th>
								<td><?php echo esc_html( isset( $rollback_checkpoint_summary['completed_count'] ) ? (string) $rollback_checkpoint_summary['completed_count'] : '0' ); ?></td>
							</tr>
							<tr>
								<th scope="row"><?php echo esc_html__( 'Failed Items', 'zignites-sentinel' ); ?></th>
								<td><?php echo esc_html( isset( $rollback_checkpoint_summary['failed_count'] ) ? (string) $rollback_checkpoint_summary['failed_count'] : '0' ); ?></td>
							</tr>
							<?php if ( ! empty( $rollback_checkpoint_summary['phase_counts'] ) ) : ?>
								<tr>
									<th scope="row"><?php echo esc_html__( 'Checkpoint Phases', 'zignites-sentinel' ); ?></th>
									<td><?php echo esc_html( implode( ', ', array_map( static function ( $phase, $count ) { return sprintf( '%s (%d)', str_replace( '_', ' ', (string) $phase ), (int) $count ); }, array_keys( $rollback_checkpoint_summary['phase_counts'] ), $rollback_checkpoint_summary['phase_counts'] ) ) ); ?></td>
								</tr>
							<?php endif; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</section>
		<?php endif; ?>

		<?php if ( $snapshot_detail ) : ?>
			<section class="znts-card znts-card-full znts-card-primary">
				<div class="znts-section-header">
					<div>
						<h2><?php echo esc_html__( 'Snapshot Summary', 'zignites-sentinel' ); ?></h2>
						<p><?php echo esc_html__( 'Use this summary for a quick operator handoff. It condenses the current snapshot state, evidence, risks, and recommended next steps into one read-only view.', 'zignites-sentinel' ); ?></p>
					</div>
					<div class="znts-actions">
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="znts_download_snapshot_summary" />
							<input type="hidden" name="snapshot_id" value="<?php echo esc_attr( (string) $snapshot_detail['id'] ); ?>" />
							<?php wp_nonce_field( 'znts_download_snapshot_summary_action' ); ?>
							<?php submit_button( __( 'Download Summary', 'zignites-sentinel' ), 'secondary', 'submit', false ); ?>
						</form>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="znts_download_snapshot_audit_report" />
							<input type="hidden" name="snapshot_id" value="<?php echo esc_attr( (string) $snapshot_detail['id'] ); ?>" />
							<?php wp_nonce_field( 'znts_download_snapshot_audit_report_action' ); ?>
							<?php submit_button( __( 'Download Audit Report', 'zignites-sentinel' ), 'secondary', 'submit', false ); ?>
						</form>
					</div>
				</div>
				<?php if ( ! empty( $snapshot_summary['status_badges'] ) ) : ?>
					<div class="znts-badge-row znts-card-note">
						<?php foreach ( $snapshot_summary['status_badges'] as $badge ) : ?>
							<span class="znts-pill znts-pill-<?php echo esc_attr( isset( $badge['badge'] ) ? $badge['badge'] : 'info' ); ?>">
								<?php echo esc_html( isset( $badge['label'] ) ? $badge['label'] : '' ); ?>
							</span>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
				<div class="znts-focus-grid">
					<section class="znts-focus-panel znts-focus-panel-primary">
						<span class="znts-focus-label"><?php echo esc_html__( 'Recommended Next Step', 'zignites-sentinel' ); ?></span>
						<h3><?php echo esc_html( $snapshot_primary_step ); ?></h3>
						<p class="znts-focus-note"><?php echo esc_html__( 'This is the shortest safe path forward from the current snapshot state.', 'zignites-sentinel' ); ?></p>
					</section>
					<section class="znts-focus-panel <?php echo esc_attr( empty( $snapshot_summary['risks'] ) ? 'znts-focus-panel-muted' : 'znts-focus-panel-warning' ); ?>">
						<span class="znts-focus-label"><?php echo esc_html__( 'Current Risk', 'zignites-sentinel' ); ?></span>
						<h3><?php echo esc_html( $snapshot_primary_risk ); ?></h3>
						<?php if ( ! empty( $snapshot_summary['risks'] ) ) : ?>
							<p class="znts-focus-note"><?php echo esc_html__( 'Resolve this before treating the snapshot as safely prepared.', 'zignites-sentinel' ); ?></p>
						<?php endif; ?>
					</section>
				</div>
				<details class="znts-disclosure" open>
					<summary><?php echo esc_html__( 'Snapshot Summary Details', 'zignites-sentinel' ); ?></summary>
					<div class="znts-disclosure-body">
						<div class="znts-snapshot-overview">
							<?php foreach ( isset( $snapshot_summary['overview'] ) && is_array( $snapshot_summary['overview'] ) ? $snapshot_summary['overview'] : array() as $item ) : ?>
								<div class="znts-overview-block">
									<strong><?php echo esc_html( isset( $item['label'] ) ? $item['label'] : '' ); ?></strong>
									<span><?php echo esc_html( isset( $item['value'] ) ? $item['value'] : '' ); ?></span>
									<?php if ( ! empty( $item['note'] ) ) : ?>
										<p class="description"><?php echo esc_html( $item['note'] ); ?></p>
									<?php endif; ?>
								</div>
							<?php endforeach; ?>
						</div>
					</div>
				</details>
				<div class="znts-dashboard-support znts-dashboard-support-tight">
					<section class="znts-helper-block znts-helper-block-summary">
						<h3><?php echo esc_html__( 'Summary Context', 'zignites-sentinel' ); ?></h3>
						<p class="znts-block-note"><?php echo esc_html__( 'Use this context to understand why the next step and risk callouts are being raised.', 'zignites-sentinel' ); ?></p>
						<details class="znts-disclosure znts-disclosure-inline">
							<summary><?php echo esc_html__( 'View evidence summary', 'zignites-sentinel' ); ?></summary>
							<div class="znts-disclosure-body">
								<ul class="znts-list">
									<?php foreach ( isset( $snapshot_summary['evidence'] ) && is_array( $snapshot_summary['evidence'] ) ? $snapshot_summary['evidence'] : array() as $item ) : ?>
										<li>
											<strong><?php echo esc_html( isset( $item['label'] ) ? $item['label'] : '' ); ?>:</strong>
											<?php echo esc_html( isset( $item['value'] ) ? $item['value'] : '' ); ?>
											<?php if ( ! empty( $item['note'] ) ) : ?>
												<span class="znts-inline-note"><?php echo esc_html( ' ' . $item['note'] ); ?></span>
											<?php endif; ?>
										</li>
									<?php endforeach; ?>
								</ul>
							</div>
						</details>
					</section>
					<section class="znts-helper-block znts-helper-block-risk">
						<h3><?php echo esc_html__( 'Current Risks', 'zignites-sentinel' ); ?></h3>
						<?php if ( empty( $snapshot_summary['risks'] ) ) : ?>
							<div class="znts-empty-state">
								<strong><?php echo esc_html__( 'No active risk callouts', 'zignites-sentinel' ); ?></strong>
								<p><?php echo esc_html__( 'The summary does not currently flag an operator-visible risk for this snapshot.', 'zignites-sentinel' ); ?></p>
							</div>
						<?php else : ?>
							<ul class="znts-list">
								<?php foreach ( $snapshot_summary['risks'] as $risk ) : ?>
									<li><?php echo esc_html( $risk ); ?></li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</section>
					<section class="znts-helper-block znts-helper-block-action">
						<h3><?php echo esc_html__( 'Full Next-Step List', 'zignites-sentinel' ); ?></h3>
						<ul class="znts-list">
							<?php foreach ( isset( $snapshot_summary['next_steps'] ) && is_array( $snapshot_summary['next_steps'] ) ? $snapshot_summary['next_steps'] : array() as $step ) : ?>
								<li><?php echo esc_html( $step ); ?></li>
							<?php endforeach; ?>
						</ul>
					</section>
				</div>
			</section>
		<?php endif; ?>

		<?php if ( $snapshot_detail ) : ?>
			<section class="znts-card znts-card-full znts-card-secondary">
				<div class="znts-section-header">
					<div>
						<h2><?php echo esc_html__( 'Snapshot Health Baseline', 'zignites-sentinel' ); ?></h2>
						<p><?php echo esc_html__( 'Capture a read-only site health snapshot before restore execution, then compare it against post-restore and post-rollback verification.', 'zignites-sentinel' ); ?></p>
					</div>
					<div class="znts-actions">
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="znts_capture_snapshot_health_baseline" />
							<input type="hidden" name="snapshot_id" value="<?php echo esc_attr( (string) $snapshot_detail['id'] ); ?>" />
							<?php wp_nonce_field( 'znts_capture_snapshot_health_baseline_action' ); ?>
							<?php submit_button( __( 'Capture Health Baseline', 'zignites-sentinel' ), 'secondary', 'submit', false ); ?>
						</form>
					</div>
				</div>
				<div class="znts-alert-panel znts-alert-panel-<?php echo esc_attr( $health_attention_state ); ?>">
					<strong><?php echo esc_html( 'info' === $health_attention_state ? __( 'Baseline ready', 'zignites-sentinel' ) : __( 'Attention required', 'zignites-sentinel' ) ); ?></strong>
					<p><?php echo esc_html( $health_attention_message ); ?></p>
				</div>
				<?php if ( empty( $snapshot_health_baseline ) ) : ?>
					<p><?php echo esc_html__( 'No health baseline has been captured for this snapshot yet.', 'zignites-sentinel' ); ?></p>
				<?php else : ?>
					<div class="znts-readiness-row">
						<span class="znts-pill znts-pill-<?php echo esc_attr( isset( $snapshot_health_baseline['status_pill'] ) ? $snapshot_health_baseline['status_pill'] : ( 'unhealthy' === $snapshot_health_baseline['status'] ? 'critical' : ( 'degraded' === $snapshot_health_baseline['status'] ? 'warning' : 'info' ) ) ); ?>">
							<?php echo esc_html( isset( $snapshot_health_baseline['status_label'] ) ? $snapshot_health_baseline['status_label'] : ucfirst( $snapshot_health_baseline['status'] ) ); ?>
						</span>
						<span><?php echo esc_html( $snapshot_health_baseline['generated_at'] ); ?></span>
					</div>
					<p><?php echo esc_html( $snapshot_health_baseline['note'] ); ?></p>
				<?php endif; ?>
				<?php if ( ! empty( $snapshot_health_comparison ) ) : ?>
					<details class="znts-disclosure" <?php echo esc_attr( $open_health_validation ? 'open' : '' ); ?>>
						<summary><?php echo esc_html__( 'Health Comparison', 'zignites-sentinel' ); ?></summary>
						<div class="znts-disclosure-body">
							<table class="widefat striped">
						<thead>
							<tr>
								<th><?php echo esc_html__( 'Reference', 'zignites-sentinel' ); ?></th>
								<th><?php echo esc_html__( 'Status', 'zignites-sentinel' ); ?></th>
								<th><?php echo esc_html__( 'Generated', 'zignites-sentinel' ); ?></th>
								<th><?php echo esc_html__( 'Pass', 'zignites-sentinel' ); ?></th>
								<th><?php echo esc_html__( 'Warning', 'zignites-sentinel' ); ?></th>
								<th><?php echo esc_html__( 'Fail', 'zignites-sentinel' ); ?></th>
								<th><?php echo esc_html__( 'Delta', 'zignites-sentinel' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $snapshot_health_comparison as $health_row ) : ?>
								<tr>
									<td><?php echo esc_html( isset( $health_row['label'] ) ? $health_row['label'] : '' ); ?></td>
									<td>
										<span class="znts-pill znts-pill-<?php echo esc_attr( isset( $health_row['status_pill'] ) ? $health_row['status_pill'] : 'info' ); ?>">
											<?php echo esc_html( isset( $health_row['status_label'] ) ? $health_row['status_label'] : '' ); ?>
										</span>
									</td>
									<td><?php echo esc_html( isset( $health_row['generated_at'] ) ? $health_row['generated_at'] : '' ); ?></td>
									<td><?php echo esc_html( isset( $health_row['summary']['pass'] ) ? (string) $health_row['summary']['pass'] : '0' ); ?></td>
									<td><?php echo esc_html( isset( $health_row['summary']['warning'] ) ? (string) $health_row['summary']['warning'] : '0' ); ?></td>
									<td><?php echo esc_html( isset( $health_row['summary']['fail'] ) ? (string) $health_row['summary']['fail'] : '0' ); ?></td>
									<td><?php echo esc_html( isset( $health_row['delta'] ) ? $health_row['delta'] : '' ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
							</table>
						</div>
					</details>
				<?php endif; ?>
				<?php if ( ! empty( $operator_checklist['checks'] ) ) : ?>
					<details class="znts-disclosure" <?php echo esc_attr( $open_health_validation ? 'open' : '' ); ?>>
						<summary><?php echo esc_html__( 'Live Restore Operator Checklist', 'zignites-sentinel' ); ?></summary>
						<div class="znts-disclosure-body">
							<div class="znts-actions">
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="znts_refresh_restore_gates" />
							<input type="hidden" name="snapshot_id" value="<?php echo esc_attr( (string) $snapshot_detail['id'] ); ?>" />
							<?php wp_nonce_field( 'znts_refresh_restore_gates_action' ); ?>
							<?php submit_button( __( 'Refresh Checklist Gates', 'zignites-sentinel' ), 'secondary', 'submit', false ); ?>
						</form>
							</div>
							<div class="znts-readiness-row">
						<span class="znts-pill znts-pill-<?php echo esc_attr( ! empty( $operator_checklist['can_execute'] ) ? 'info' : 'critical' ); ?>">
							<?php echo esc_html( ! empty( $operator_checklist['can_execute'] ) ? __( 'Ready', 'zignites-sentinel' ) : __( 'Blocked', 'zignites-sentinel' ) ); ?>
						</span>
						<span>
							<?php
							echo esc_html(
								sprintf(
									/* translators: %d: max age in hours */
									__( 'Live restore is offered only when all checklist gates pass and checkpoints are no older than %d hours.', 'zignites-sentinel' ),
									isset( $operator_checklist['max_age_hours'] ) ? (int) $operator_checklist['max_age_hours'] : 24
								)
							);
							?>
						</span>
							</div>
							<p class="description"><?php echo esc_html__( 'This reruns staged validation and restore planning only. It does not execute a restore or modify live plugin/theme files.', 'zignites-sentinel' ); ?></p>
							<table class="widefat striped">
						<thead>
							<tr>
								<th><?php echo esc_html__( 'Requirement', 'zignites-sentinel' ); ?></th>
								<th><?php echo esc_html__( 'Status', 'zignites-sentinel' ); ?></th>
								<th><?php echo esc_html__( 'Message', 'zignites-sentinel' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $operator_checklist['checks'] as $check ) : ?>
								<tr>
									<td><?php echo esc_html( isset( $check['label'] ) ? $check['label'] : '' ); ?></td>
									<td>
										<span class="znts-pill znts-pill-<?php echo esc_attr( 'fail' === ( isset( $check['status'] ) ? $check['status'] : '' ) ? 'critical' : ( isset( $check['status'] ) ? $check['status'] : 'info' ) ); ?>">
											<?php echo esc_html( ucfirst( isset( $check['status'] ) ? $check['status'] : '' ) ); ?>
										</span>
									</td>
									<td><?php echo esc_html( isset( $check['message'] ) ? $check['message'] : '' ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
							</table>
						</div>
					</details>
				<?php endif; ?>
				<details class="znts-disclosure">
					<summary><?php echo esc_html__( 'Audit Verification', 'zignites-sentinel' ); ?></summary>
					<div class="znts-disclosure-body">
						<p><?php echo esc_html__( 'Paste a previously downloaded audit report to verify its payload hash, site signature, and snapshot match against this site.', 'zignites-sentinel' ); ?></p>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="znts_verify_snapshot_audit_report" />
					<input type="hidden" name="snapshot_id" value="<?php echo esc_attr( (string) $snapshot_detail['id'] ); ?>" />
					<?php wp_nonce_field( 'znts_verify_snapshot_audit_report_action' ); ?>
					<p>
						<label for="znts-audit-report-payload"><?php echo esc_html__( 'Audit report JSON', 'zignites-sentinel' ); ?></label><br />
						<textarea id="znts-audit-report-payload" name="audit_report_payload" rows="10" class="large-text code"></textarea>
					</p>
						<?php submit_button( __( 'Verify Audit Report', 'zignites-sentinel' ), 'secondary', 'submit', false ); ?>
						</form>
				<?php if ( ! empty( $audit_report_verification ) ) : ?>
					<h3><?php echo esc_html__( 'Latest Verification Result', 'zignites-sentinel' ); ?></h3>
					<div class="znts-readiness-row">
						<span class="znts-pill znts-pill-<?php echo esc_attr( 'blocked' === $audit_report_verification['status'] ? 'critical' : ( 'caution' === $audit_report_verification['status'] ? 'warning' : 'info' ) ); ?>">
							<?php echo esc_html( ucfirst( $audit_report_verification['status'] ) ); ?>
						</span>
						<span><?php echo esc_html( isset( $audit_report_verification['generated_at'] ) ? $audit_report_verification['generated_at'] : '' ); ?></span>
					</div>
					<p><?php echo esc_html( isset( $audit_report_verification['note'] ) ? $audit_report_verification['note'] : '' ); ?></p>
					<?php if ( ! empty( $audit_report_verification['checks'] ) ) : ?>
						<table class="widefat striped">
							<thead>
								<tr>
									<th><?php echo esc_html__( 'Check', 'zignites-sentinel' ); ?></th>
									<th><?php echo esc_html__( 'Status', 'zignites-sentinel' ); ?></th>
									<th><?php echo esc_html__( 'Message', 'zignites-sentinel' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $audit_report_verification['checks'] as $check ) : ?>
									<tr>
										<td><?php echo esc_html( isset( $check['label'] ) ? $check['label'] : '' ); ?></td>
										<td>
											<span class="znts-pill znts-pill-<?php echo esc_attr( 'fail' === ( isset( $check['status'] ) ? $check['status'] : '' ) ? 'critical' : ( isset( $check['status'] ) ? $check['status'] : 'info' ) ); ?>">
												<?php echo esc_html( ucfirst( isset( $check['status'] ) ? $check['status'] : '' ) ); ?>
											</span>
										</td>
										<td><?php echo esc_html( isset( $check['message'] ) ? $check['message'] : '' ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>
				<?php endif; ?>
					</div>
				</details>
			</section>
		<?php endif; ?>

		<section class="znts-card znts-card-secondary">
			<h2><?php echo esc_html__( 'Operator Actions', 'zignites-sentinel' ); ?></h2>
			<p><?php echo esc_html__( 'Use these actions to assess readiness and capture current site state before updates.', 'zignites-sentinel' ); ?></p>
			<div class="znts-actions">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="znts_run_preflight" />
					<?php wp_nonce_field( 'znts_run_preflight_action' ); ?>
					<?php submit_button( __( 'Run Preflight Scan', 'zignites-sentinel' ), 'primary', 'submit', false ); ?>
				</form>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="znts_create_snapshot" />
					<?php wp_nonce_field( 'znts_create_snapshot_action' ); ?>
					<?php submit_button( __( 'Create Snapshot Metadata', 'zignites-sentinel' ), 'secondary', 'submit', false ); ?>
				</form>
			</div>
		</section>

		<section class="znts-card znts-card-secondary">
			<h2><?php echo esc_html__( 'Sentinel Settings', 'zignites-sentinel' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="znts_save_settings" />
				<?php wp_nonce_field( 'znts_save_settings_action' ); ?>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><?php echo esc_html__( 'Enable logging', 'zignites-sentinel' ); ?></th>
							<td><label><input type="checkbox" name="logging_enabled" value="1" <?php checked( ! empty( $settings['logging_enabled'] ) ); ?> /> <?php echo esc_html__( 'Store diagnostic events.', 'zignites-sentinel' ); ?></label></td>
						</tr>
						<tr>
							<th scope="row"><?php echo esc_html__( 'Delete data on uninstall', 'zignites-sentinel' ); ?></th>
							<td><label><input type="checkbox" name="delete_data_on_uninstall" value="1" <?php checked( ! empty( $settings['delete_data_on_uninstall'] ) ); ?> /> <?php echo esc_html__( 'Remove Sentinel tables and options when the plugin is uninstalled.', 'zignites-sentinel' ); ?></label></td>
						</tr>
						<tr>
							<th scope="row"><?php echo esc_html__( 'Auto-create snapshot on plan', 'zignites-sentinel' ); ?></th>
							<td><label><input type="checkbox" name="auto_snapshot_on_plan" value="1" <?php checked( ! empty( $settings['auto_snapshot_on_plan'] ) ); ?> /> <?php echo esc_html__( 'Create snapshot metadata automatically when generating a manual update plan.', 'zignites-sentinel' ); ?></label></td>
						</tr>
						<tr>
							<th scope="row"><label for="znts-snapshot-retention-days"><?php echo esc_html__( 'Snapshot retention (days)', 'zignites-sentinel' ); ?></label></th>
							<td><input id="znts-snapshot-retention-days" type="number" min="1" name="snapshot_retention_days" value="<?php echo esc_attr( (string) $settings['snapshot_retention_days'] ); ?>" class="small-text" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="znts-restore-checkpoint-max-age-hours"><?php echo esc_html__( 'Checkpoint max age (hours)', 'zignites-sentinel' ); ?></label></th>
							<td><input id="znts-restore-checkpoint-max-age-hours" type="number" min="1" name="restore_checkpoint_max_age_hours" value="<?php echo esc_attr( isset( $settings['restore_checkpoint_max_age_hours'] ) ? (string) $settings['restore_checkpoint_max_age_hours'] : '24' ); ?>" class="small-text" /></td>
						</tr>
					</tbody>
				</table>
				<?php submit_button( __( 'Save Settings', 'zignites-sentinel' ), 'secondary', 'submit', false ); ?>
			</form>
			<div class="znts-form-panel">
				<h3><?php echo esc_html__( 'Settings Portability', 'zignites-sentinel' ); ?></h3>
				<p><?php echo esc_html__( 'Export or import Sentinel preferences only. This excludes logs, snapshots, checkpoints, restore results, health baselines, and any live execution state.', 'zignites-sentinel' ); ?></p>
				<div class="znts-actions">
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="znts_download_settings_export" />
						<?php wp_nonce_field( 'znts_download_settings_export_action' ); ?>
						<?php submit_button( __( 'Export Settings', 'zignites-sentinel' ), 'secondary', 'submit', false ); ?>
					</form>
				</div>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="znts_import_settings" />
					<?php wp_nonce_field( 'znts_import_settings_action' ); ?>
					<p>
						<label for="znts-settings-import-payload"><?php echo esc_html__( 'Import settings JSON', 'zignites-sentinel' ); ?></label><br />
						<textarea id="znts-settings-import-payload" name="settings_import_payload" rows="8" class="large-text code"></textarea>
					</p>
					<p class="description"><?php echo esc_html__( 'Only supported Sentinel preference keys are imported. Unknown fields are ignored.', 'zignites-sentinel' ); ?></p>
					<?php submit_button( __( 'Import Settings', 'zignites-sentinel' ), 'secondary', 'submit', false ); ?>
				</form>
			</div>
		</section>

		<section class="znts-card znts-card-secondary">
			<h2><?php echo esc_html__( 'Latest Preflight Result', 'zignites-sentinel' ); ?></h2>
			<?php if ( empty( $preflight ) ) : ?>
				<p><?php echo esc_html__( 'No preflight scan has been recorded yet.', 'zignites-sentinel' ); ?></p>
			<?php else : ?>
				<div class="znts-readiness-row">
					<span class="znts-pill znts-pill-<?php echo esc_attr( 'blocked' === $preflight['readiness'] ? 'critical' : ( 'caution' === $preflight['readiness'] ? 'warning' : 'info' ) ); ?>">
						<?php echo esc_html( ucfirst( $preflight['readiness'] ) ); ?>
					</span>
					<span><?php echo esc_html( $preflight['generated_at'] ); ?></span>
				</div>
				<p><?php echo esc_html( $preflight['note'] ); ?></p>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php echo esc_html__( 'Check', 'zignites-sentinel' ); ?></th>
							<th><?php echo esc_html__( 'Status', 'zignites-sentinel' ); ?></th>
							<th><?php echo esc_html__( 'Message', 'zignites-sentinel' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $preflight['checks'] as $check ) : ?>
							<tr>
								<td><?php echo esc_html( $check['label'] ); ?></td>
								<td>
									<span class="znts-pill znts-pill-<?php echo esc_attr( 'fail' === $check['status'] ? 'critical' : $check['status'] ); ?>">
										<?php echo esc_html( ucfirst( $check['status'] ) ); ?>
									</span>
								</td>
								<td><?php echo esc_html( $check['message'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</section>

		<section class="znts-card znts-card-secondary">
			<h2><?php echo esc_html__( 'Pending Update Candidates', 'zignites-sentinel' ); ?></h2>
			<?php if ( empty( $view_data['update_candidates'] ) ) : ?>
				<p><?php echo esc_html__( 'No pending plugin, theme, or core updates were found.', 'zignites-sentinel' ); ?></p>
			<?php else : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="znts_build_update_plan" />
					<?php wp_nonce_field( 'znts_build_update_plan_action' ); ?>
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php echo esc_html__( 'Select', 'zignites-sentinel' ); ?></th>
								<th><?php echo esc_html__( 'Type', 'zignites-sentinel' ); ?></th>
								<th><?php echo esc_html__( 'Component', 'zignites-sentinel' ); ?></th>
								<th><?php echo esc_html__( 'Current', 'zignites-sentinel' ); ?></th>
								<th><?php echo esc_html__( 'Available', 'zignites-sentinel' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $view_data['update_candidates'] as $candidate ) : ?>
								<tr>
									<td><input type="checkbox" name="update_targets[]" value="<?php echo esc_attr( $candidate['key'] ); ?>" /></td>
									<td><?php echo esc_html( ucfirst( $candidate['type'] ) ); ?></td>
									<td><?php echo esc_html( $candidate['label'] ); ?></td>
									<td><?php echo esc_html( $candidate['current_version'] ); ?></td>
									<td><?php echo esc_html( $candidate['new_version'] ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					<p class="description"><?php echo esc_html__( 'This creates a manual review artifact only. It does not run updates.', 'zignites-sentinel' ); ?></p>
					<?php submit_button( __( 'Create Manual Update Plan', 'zignites-sentinel' ), 'secondary', 'submit', false ); ?>
				</form>
			<?php endif; ?>
		</section>

		<section class="znts-card znts-card-secondary">
			<h2><?php echo esc_html__( 'Last Update Plan', 'zignites-sentinel' ); ?></h2>
			<?php if ( empty( $last_plan ) ) : ?>
				<p><?php echo esc_html__( 'No update plan has been created yet.', 'zignites-sentinel' ); ?></p>
			<?php else : ?>
				<div class="znts-readiness-row">
					<span class="znts-pill znts-pill-<?php echo esc_attr( 'blocked_for_review' === $last_plan['status'] ? 'critical' : ( 'caution' === $last_plan['status'] ? 'warning' : 'info' ) ); ?>">
						<?php echo esc_html( ucfirst( str_replace( '_', ' ', $last_plan['status'] ) ) ); ?>
					</span>
					<span><?php echo esc_html( $last_plan['created_at'] ); ?></span>
				</div>
				<p><?php echo esc_html( $last_plan['note'] ); ?></p>
				<?php if ( ! empty( $last_plan['snapshot_id'] ) ) : ?>
					<p>
						<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'zignites-sentinel-update-readiness', 'snapshot_id' => (int) $last_plan['snapshot_id'] ), admin_url( 'admin.php' ) ) ); ?>">
							<?php echo esc_html__( 'View related snapshot metadata', 'zignites-sentinel' ); ?>
						</a>
					</p>
				<?php endif; ?>
				<?php if ( ! empty( $last_plan['targets'] ) ) : ?>
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php echo esc_html__( 'Type', 'zignites-sentinel' ); ?></th>
								<th><?php echo esc_html__( 'Component', 'zignites-sentinel' ); ?></th>
								<th><?php echo esc_html__( 'Current', 'zignites-sentinel' ); ?></th>
								<th><?php echo esc_html__( 'Planned', 'zignites-sentinel' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $last_plan['targets'] as $target ) : ?>
								<tr>
									<td><?php echo esc_html( ucfirst( $target['type'] ) ); ?></td>
									<td><?php echo esc_html( $target['label'] ); ?></td>
									<td><?php echo esc_html( $target['current_version'] ); ?></td>
									<td><?php echo esc_html( $target['new_version'] ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
				<?php if ( ! empty( $plan_validation ) ) : ?>
					<h3><?php echo esc_html__( 'Target Source Validation', 'zignites-sentinel' ); ?></h3>
					<p><?php echo esc_html( $plan_validation['message'] ); ?></p>
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php echo esc_html__( 'Check', 'zignites-sentinel' ); ?></th>
								<th><?php echo esc_html__( 'Status', 'zignites-sentinel' ); ?></th>
								<th><?php echo esc_html__( 'Message', 'zignites-sentinel' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $plan_validation_check_rows as $check ) : ?>
								<tr>
									<td><?php echo esc_html( $check['label'] ); ?></td>
									<td>
										<span class="znts-pill znts-pill-<?php echo esc_attr( $check['badge'] ); ?>">
											<?php echo esc_html( $check['status_label'] ); ?>
										</span>
									</td>
									<td><?php echo esc_html( $check['message'] ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			<?php endif; ?>
		</section>

		<section class="znts-card znts-card-secondary">
			<h2><?php echo esc_html__( 'Recent Snapshot Metadata', 'zignites-sentinel' ); ?></h2>
			<p class="description"><?php echo esc_html__( 'Use snapshot status filters to find snapshots with a baseline, a saved rollback package, fresh restore gates, or recent restore activity.', 'zignites-sentinel' ); ?></p>
			<details class="znts-disclosure znts-disclosure-inline">
				<summary><?php echo esc_html__( 'Status guide', 'zignites-sentinel' ); ?></summary>
				<div class="znts-disclosure-body">
					<ul class="znts-list">
						<li><?php echo esc_html__( 'Baseline present: a health baseline was captured for that snapshot.', 'zignites-sentinel' ); ?></li>
						<li><?php echo esc_html__( 'Package saved: the snapshot includes a stored rollback package.', 'zignites-sentinel' ); ?></li>
						<li><?php echo esc_html__( 'Stage fresh / Plan fresh: the latest validation and restore plan still match the current package and age window.', 'zignites-sentinel' ); ?></li>
						<li><?php echo esc_html__( 'Restore ready: the baseline is present and both restore gates are currently fresh.', 'zignites-sentinel' ); ?></li>
					</ul>
				</div>
			</details>
			<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="znts-filter-form">
				<input type="hidden" name="page" value="zignites-sentinel-update-readiness" />
				<?php if ( $snapshot_detail && ! empty( $snapshot_detail['id'] ) ) : ?>
					<input type="hidden" name="snapshot_id" value="<?php echo esc_attr( (string) $snapshot_detail['id'] ); ?>" />
				<?php endif; ?>
				<p>
					<label for="znts-snapshot-search"><?php echo esc_html__( 'Filter by label', 'zignites-sentinel' ); ?></label><br />
					<input id="znts-snapshot-search" type="search" name="snapshot_search" value="<?php echo esc_attr( $snapshot_search ); ?>" />
				</p>
				<p>
					<label for="znts-snapshot-status-filter"><?php echo esc_html__( 'Filter by status', 'zignites-sentinel' ); ?></label><br />
					<select id="znts-snapshot-status-filter" name="snapshot_status_filter">
						<?php foreach ( $snapshot_status_filter_options as $filter_value => $filter_label ) : ?>
							<option value="<?php echo esc_attr( $filter_value ); ?>" <?php selected( $snapshot_status_filter, (string) $filter_value ); ?>>
								<?php echo esc_html( $filter_label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</p>
				<p class="znts-filter-actions">
					<?php submit_button( __( 'Filter Snapshots', 'zignites-sentinel' ), 'secondary', '', false ); ?>
					<?php if ( '' !== $snapshot_search || '' !== $snapshot_status_filter ) : ?>
						<a class="button" href="<?php echo esc_url( add_query_arg( array_filter( array( 'page' => 'zignites-sentinel-update-readiness', 'snapshot_id' => $snapshot_detail && ! empty( $snapshot_detail['id'] ) ? (int) $snapshot_detail['id'] : 0 ) ), admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html__( 'Clear', 'zignites-sentinel' ); ?></a>
					<?php endif; ?>
				</p>
			</form>
			<?php if ( empty( $view_data['recent_snapshots'] ) ) : ?>
				<p><?php echo esc_html( '' !== $snapshot_search || '' !== $snapshot_status_filter ? __( 'No snapshots matched the current filters.', 'zignites-sentinel' ) : __( 'No snapshot metadata has been recorded yet.', 'zignites-sentinel' ) ); ?></p>
			<?php else : ?>
				<?php if ( ! empty( $snapshot_pagination['total_items'] ) ) : ?>
					<p class="description">
						<?php
						echo esc_html(
							sprintf(
								/* translators: 1: current page, 2: total pages, 3: total items */
								__( 'Page %1$d of %2$d, %3$d snapshots matched.', 'zignites-sentinel' ),
								isset( $snapshot_pagination['current_page'] ) ? (int) $snapshot_pagination['current_page'] : 1,
								isset( $snapshot_pagination['total_pages'] ) ? (int) $snapshot_pagination['total_pages'] : 1,
								isset( $snapshot_pagination['total_items'] ) ? (int) $snapshot_pagination['total_items'] : 0
							)
						);
						?>
					</p>
				<?php endif; ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php echo esc_html__( 'Created', 'zignites-sentinel' ); ?></th>
							<th><?php echo esc_html__( 'Label', 'zignites-sentinel' ); ?></th>
							<th><?php echo esc_html__( 'Readiness', 'zignites-sentinel' ); ?></th>
							<th><?php echo esc_html__( 'Core', 'zignites-sentinel' ); ?></th>
							<th><?php echo esc_html__( 'PHP', 'zignites-sentinel' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $view_data['recent_snapshots'] as $snapshot ) : ?>
							<?php $snapshot_status = isset( $snapshot_status_index[ (int) $snapshot['id'] ] ) ? $snapshot_status_index[ (int) $snapshot['id'] ] : array(); ?>
							<tr>
								<td><?php echo esc_html( $snapshot['created_at'] ); ?></td>
								<td>
									<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'zignites-sentinel-update-readiness', 'snapshot_id' => (int) $snapshot['id'] ), admin_url( 'admin.php' ) ) ); ?>">
										<?php echo esc_html( $snapshot['label'] ); ?>
									</a>
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
								<td><?php echo esc_html( $snapshot['core_version'] ); ?></td>
								<td><?php echo esc_html( $snapshot['php_version'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<?php if ( ! empty( $snapshot_pagination['total_pages'] ) && (int) $snapshot_pagination['total_pages'] > 1 ) : ?>
					<?php
					$pagination_base_args = array(
						'page' => 'zignites-sentinel-update-readiness',
					);

					if ( $snapshot_detail && ! empty( $snapshot_detail['id'] ) ) {
						$pagination_base_args['snapshot_id'] = (int) $snapshot_detail['id'];
					}

					if ( '' !== $snapshot_search ) {
						$pagination_base_args['snapshot_search'] = $snapshot_search;
					}

					if ( '' !== $snapshot_status_filter ) {
						$pagination_base_args['snapshot_status_filter'] = $snapshot_status_filter;
					}
					?>
					<div class="tablenav">
						<div class="tablenav-pages">
							<?php
							echo wp_kses_post(
								paginate_links(
									array(
										'base'      => add_query_arg( $pagination_base_args + array( 'snapshot_paged' => '%#%' ), admin_url( 'admin.php' ) ),
										'format'    => '',
										'current'   => isset( $snapshot_pagination['current_page'] ) ? (int) $snapshot_pagination['current_page'] : 1,
										'total'     => isset( $snapshot_pagination['total_pages'] ) ? (int) $snapshot_pagination['total_pages'] : 1,
										'type'      => 'plain',
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

		<?php if ( $snapshot_detail ) : ?>
			<section class="znts-card znts-card-full znts-card-flat">
				<div class="znts-section-header">
					<div>
						<h2><?php echo esc_html__( 'Snapshot Activity Timeline', 'zignites-sentinel' ); ?></h2>
						<p><?php echo esc_html__( 'Recent activity tied to this snapshot across readiness checks, staging, planning, execution, rollback, and maintenance events.', 'zignites-sentinel' ); ?></p>
					</div>
					<?php if ( '' !== $snapshot_activity_url ) : ?>
						<p><a href="<?php echo esc_url( $snapshot_activity_url ); ?>"><?php echo esc_html__( 'View full event history', 'zignites-sentinel' ); ?></a></p>
					<?php endif; ?>
				</div>
				<details class="znts-disclosure">
					<summary><?php echo esc_html__( 'View activity details', 'zignites-sentinel' ); ?></summary>
					<div class="znts-disclosure-body">
				<?php if ( empty( $snapshot_activity ) ) : ?>
					<p><?php echo esc_html__( 'No snapshot-scoped events have been recorded yet.', 'zignites-sentinel' ); ?></p>
				<?php else : ?>
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php echo esc_html__( 'Time', 'zignites-sentinel' ); ?></th>
								<th><?php echo esc_html__( 'Severity', 'zignites-sentinel' ); ?></th>
								<th><?php echo esc_html__( 'Source', 'zignites-sentinel' ); ?></th>
								<th><?php echo esc_html__( 'Event', 'zignites-sentinel' ); ?></th>
								<th><?php echo esc_html__( 'Message', 'zignites-sentinel' ); ?></th>
								<th><?php echo esc_html__( 'Trace', 'zignites-sentinel' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $snapshot_activity as $activity ) : ?>
								<tr>
									<td>
										<a href="<?php echo esc_url( isset( $activity['detail_url'] ) ? $activity['detail_url'] : '' ); ?>">
											<?php echo esc_html( isset( $activity['created_at'] ) ? $activity['created_at'] : '' ); ?>
										</a>
									</td>
									<td>
										<span class="znts-pill znts-pill-<?php echo esc_attr( 'fail' === ( isset( $activity['severity'] ) ? $activity['severity'] : '' ) ? 'critical' : ( isset( $activity['severity'] ) ? $activity['severity'] : 'info' ) ); ?>">
											<?php echo esc_html( ucfirst( isset( $activity['severity'] ) ? $activity['severity'] : 'info' ) ); ?>
										</span>
									</td>
									<td><?php echo esc_html( isset( $activity['source'] ) ? $activity['source'] : '' ); ?></td>
									<td><?php echo esc_html( isset( $activity['event_type'] ) ? $activity['event_type'] : '' ); ?></td>
									<td>
										<details class="znts-disclosure znts-disclosure-inline znts-log-message">
											<summary><span class="znts-message-preview"><?php echo esc_html( isset( $activity['message'] ) ? $activity['message'] : '' ); ?></span></summary>
											<div class="znts-disclosure-body">
												<p class="znts-message-full"><?php echo esc_html( isset( $activity['message'] ) ? $activity['message'] : '' ); ?></p>
											</div>
										</details>
									</td>
									<td>
										<?php if ( ! empty( $activity['journal_url'] ) ) : ?>
											<a href="<?php echo esc_url( $activity['journal_url'] ); ?>"><?php echo esc_html( isset( $activity['journal_label'] ) ? $activity['journal_label'] : '' ); ?></a>
										<?php else : ?>
											<span class="description"><?php echo esc_html__( 'Event detail', 'zignites-sentinel' ); ?></span>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
					</div>
				</details>
			</section>
		<?php endif; ?>

		<?php if ( $snapshot_detail ) : ?>
			<section class="znts-card znts-card-full znts-card-flat">
				<h2><?php echo esc_html__( 'Snapshot Detail', 'zignites-sentinel' ); ?></h2>
				<p class="znts-inline-note"><?php echo esc_html__( 'Start with the overview. Open the other sections only when you need deeper component, artifact, or environment detail.', 'zignites-sentinel' ); ?></p>
				<?php if ( ! empty( $selected_snapshot_status['status_badges'] ) ) : ?>
					<div class="znts-badge-row znts-card-note">
						<?php foreach ( $selected_snapshot_status['status_badges'] as $badge ) : ?>
							<span class="znts-pill znts-pill-<?php echo esc_attr( isset( $badge['badge'] ) ? $badge['badge'] : 'info' ); ?>">
								<?php echo esc_html( isset( $badge['label'] ) ? $badge['label'] : '' ); ?>
							</span>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
				<div class="znts-actions">
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="znts_check_restore_readiness" />
						<input type="hidden" name="snapshot_id" value="<?php echo esc_attr( (string) $snapshot_detail['id'] ); ?>" />
						<?php wp_nonce_field( 'znts_check_restore_readiness_action' ); ?>
						<?php submit_button( __( 'Evaluate Restore Readiness', 'zignites-sentinel' ), 'secondary', 'submit', false ); ?>
					</form>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="znts_run_restore_dry_run" />
						<input type="hidden" name="snapshot_id" value="<?php echo esc_attr( (string) $snapshot_detail['id'] ); ?>" />
						<?php wp_nonce_field( 'znts_run_restore_dry_run_action' ); ?>
						<?php submit_button( __( 'Run Restore Dry-Run', 'zignites-sentinel' ), 'secondary', 'submit', false ); ?>
					</form>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="znts_run_restore_stage" />
						<input type="hidden" name="snapshot_id" value="<?php echo esc_attr( (string) $snapshot_detail['id'] ); ?>" />
						<?php wp_nonce_field( 'znts_run_restore_stage_action' ); ?>
						<?php submit_button( __( 'Run Staged Restore Validation', 'zignites-sentinel' ), 'secondary', 'submit', false ); ?>
					</form>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="znts_build_restore_plan" />
						<input type="hidden" name="snapshot_id" value="<?php echo esc_attr( (string) $snapshot_detail['id'] ); ?>" />
						<?php wp_nonce_field( 'znts_build_restore_plan_action' ); ?>
						<?php submit_button( __( 'Build Restore Plan', 'zignites-sentinel' ), 'secondary', 'submit', false ); ?>
					</form>
				</div>
				<p class="znts-inline-note">
					<?php echo esc_html__( 'Each validation action saves its latest result for this snapshot and shows it below on this page.', 'zignites-sentinel' ); ?>
					<?php if ( ! empty( $last_restore_dry_run ) || ! empty( $last_restore_stage ) || ! empty( $last_restore_plan ) ) : ?>
						<span>
							<?php echo esc_html__( 'Jump to:', 'zignites-sentinel' ); ?>
							<?php
							$jump_links = array();

							if ( ! empty( $last_restore_dry_run ) ) {
								$jump_links[] = sprintf(
									'<a href="#znts-restore-dry-run">%s</a>',
									esc_html__( 'Dry-Run', 'zignites-sentinel' )
								);
							}

							if ( ! empty( $last_restore_stage ) ) {
								$jump_links[] = sprintf(
									'<a href="#znts-restore-stage">%s</a>',
									esc_html__( 'Staged Validation', 'zignites-sentinel' )
								);
							}

							if ( ! empty( $last_restore_plan ) ) {
								$jump_links[] = sprintf(
									'<a href="#znts-restore-plan">%s</a>',
									esc_html__( 'Restore Plan', 'zignites-sentinel' )
								);
							}

							echo wp_kses(
								implode( ', ', $jump_links ),
								array(
									'a' => array(
										'href' => array(),
									),
								)
							);
							?>
						</span>
					<?php endif; ?>
				</p>
				<details class="znts-disclosure" open>
					<summary><?php echo esc_html__( 'Snapshot Basics', 'zignites-sentinel' ); ?></summary>
					<div class="znts-disclosure-body">
						<table class="widefat striped">
							<tbody>
								<tr>
									<th scope="row"><?php echo esc_html__( 'Label', 'zignites-sentinel' ); ?></th>
									<td><?php echo esc_html( $snapshot_detail['label'] ); ?></td>
								</tr>
								<tr>
									<th scope="row"><?php echo esc_html__( 'Created', 'zignites-sentinel' ); ?></th>
									<td><?php echo esc_html( $snapshot_detail['created_at'] ); ?></td>
								</tr>
								<tr>
									<th scope="row"><?php echo esc_html__( 'Theme', 'zignites-sentinel' ); ?></th>
									<td><?php echo esc_html( $snapshot_detail['theme_stylesheet'] ); ?></td>
								</tr>
								<tr>
									<th scope="row"><?php echo esc_html__( 'Core Version', 'zignites-sentinel' ); ?></th>
									<td><?php echo esc_html( $snapshot_detail['core_version'] ); ?></td>
								</tr>
								<tr>
									<th scope="row"><?php echo esc_html__( 'PHP Version', 'zignites-sentinel' ); ?></th>
									<td><?php echo esc_html( $snapshot_detail['php_version'] ); ?></td>
								</tr>
							</tbody>
						</table>
					</div>
				</details>
				<?php if ( ! empty( $snapshot_detail['metadata_decoded'] ) ) : ?>
					<details class="znts-disclosure">
						<summary><?php echo esc_html__( 'Stored Snapshot Data', 'zignites-sentinel' ); ?></summary>
						<div class="znts-disclosure-body">
							<table class="widefat striped">
								<tbody>
									<?php foreach ( $snapshot_detail['metadata_decoded'] as $meta_key => $meta_value ) : ?>
										<tr>
											<th scope="row"><?php echo esc_html( ucwords( str_replace( '_', ' ', (string) $meta_key ) ) ); ?></th>
											<td><?php echo esc_html( is_scalar( $meta_value ) ? (string) $meta_value : wp_json_encode( $meta_value ) ); ?></td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					</details>
				<?php endif; ?>
				<?php if ( ! empty( $component_manifest ) ) : ?>
					<details class="znts-disclosure">
						<summary><?php echo esc_html__( 'Component Sources At Snapshot Time', 'zignites-sentinel' ); ?></summary>
						<div class="znts-disclosure-body">
							<table class="widefat striped">
								<tbody>
									<tr>
										<th scope="row"><?php echo esc_html__( 'Generated', 'zignites-sentinel' ); ?></th>
										<td><?php echo esc_html( isset( $component_manifest['generated_at'] ) ? $component_manifest['generated_at'] : '' ); ?></td>
									</tr>
									<tr>
										<th scope="row"><?php echo esc_html__( 'Theme Source', 'zignites-sentinel' ); ?></th>
										<td><?php echo esc_html( isset( $component_manifest['theme']['source_path'] ) ? $component_manifest['theme']['source_path'] : '' ); ?></td>
									</tr>
									<tr>
										<th scope="row"><?php echo esc_html__( 'Plugin Entries', 'zignites-sentinel' ); ?></th>
										<td><?php echo esc_html( isset( $component_manifest['plugins'] ) && is_array( $component_manifest['plugins'] ) ? (string) count( $component_manifest['plugins'] ) : '0' ); ?></td>
									</tr>
								</tbody>
							</table>
						</div>
					</details>
				<?php endif; ?>
				<?php if ( ! empty( $snapshot_artifacts ) ) : ?>
					<details class="znts-disclosure">
						<summary><?php echo esc_html__( 'Rollback Package Contents', 'zignites-sentinel' ); ?></summary>
						<div class="znts-disclosure-body">
							<table class="widefat striped">
								<thead>
									<tr>
										<th><?php echo esc_html__( 'Type', 'zignites-sentinel' ); ?></th>
										<th><?php echo esc_html__( 'Label', 'zignites-sentinel' ); ?></th>
										<th><?php echo esc_html__( 'Key', 'zignites-sentinel' ); ?></th>
										<th><?php echo esc_html__( 'Version', 'zignites-sentinel' ); ?></th>
										<th><?php echo esc_html__( 'Source Path', 'zignites-sentinel' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $snapshot_artifacts as $artifact ) : ?>
										<tr>
											<td><?php echo esc_html( ucfirst( isset( $artifact['artifact_type'] ) ? $artifact['artifact_type'] : '' ) ); ?></td>
											<td><?php echo esc_html( isset( $artifact['label'] ) ? $artifact['label'] : '' ); ?></td>
											<td><?php echo esc_html( isset( $artifact['artifact_key'] ) ? $artifact['artifact_key'] : '' ); ?></td>
											<td><?php echo esc_html( isset( $artifact['version'] ) ? $artifact['version'] : '' ); ?></td>
											<td><?php echo esc_html( isset( $artifact['source_path'] ) ? $artifact['source_path'] : '' ); ?></td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					</details>
				<?php endif; ?>
				<?php if ( ! empty( $artifact_diff ) ) : ?>
					<details class="znts-disclosure">
						<summary><?php echo esc_html__( 'Artifact Mismatch Review', 'zignites-sentinel' ); ?></summary>
						<div class="znts-disclosure-body">
							<p><?php echo esc_html( isset( $artifact_diff['message'] ) ? $artifact_diff['message'] : '' ); ?></p>
							<table class="widefat striped">
								<thead>
									<tr>
										<th><?php echo esc_html__( 'Type', 'zignites-sentinel' ); ?></th>
										<th><?php echo esc_html__( 'Label', 'zignites-sentinel' ); ?></th>
										<th><?php echo esc_html__( 'Stored', 'zignites-sentinel' ); ?></th>
										<th><?php echo esc_html__( 'Current', 'zignites-sentinel' ); ?></th>
										<th><?php echo esc_html__( 'Status', 'zignites-sentinel' ); ?></th>
										<th><?php echo esc_html__( 'Message', 'zignites-sentinel' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $artifact_diff['items'] as $item ) : ?>
										<tr>
											<td><?php echo esc_html( ucfirst( isset( $item['type'] ) ? $item['type'] : '' ) ); ?></td>
											<td><?php echo esc_html( isset( $item['label'] ) ? $item['label'] : '' ); ?></td>
											<td><?php echo esc_html( isset( $item['stored_version'] ) ? $item['stored_version'] : '' ); ?></td>
											<td><?php echo esc_html( isset( $item['current_version'] ) ? $item['current_version'] : '' ); ?></td>
											<td>
												<span class="znts-pill znts-pill-<?php echo esc_attr( 'fail' === $item['status'] ? 'critical' : $item['status'] ); ?>">
													<?php echo esc_html( ucfirst( $item['status'] ) ); ?>
												</span>
											</td>
											<td><?php echo esc_html( isset( $item['message'] ) ? $item['message'] : '' ); ?></td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					</details>
				<?php endif; ?>
				<?php if ( ! empty( $snapshot_detail['active_plugins_decoded'] ) ) : ?>
					<details class="znts-disclosure">
						<summary><?php echo esc_html__( 'Plugins Active At Snapshot Time', 'zignites-sentinel' ); ?></summary>
						<div class="znts-disclosure-body">
							<table class="widefat striped">
								<thead>
									<tr>
										<th><?php echo esc_html__( 'Plugin', 'zignites-sentinel' ); ?></th>
										<th><?php echo esc_html__( 'Name', 'zignites-sentinel' ); ?></th>
										<th><?php echo esc_html__( 'Version', 'zignites-sentinel' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $snapshot_detail['active_plugins_decoded'] as $plugin_state ) : ?>
										<tr>
											<td><?php echo esc_html( isset( $plugin_state['plugin'] ) ? $plugin_state['plugin'] : '' ); ?></td>
											<td><?php echo esc_html( isset( $plugin_state['name'] ) ? $plugin_state['name'] : '' ); ?></td>
											<td><?php echo esc_html( isset( $plugin_state['version'] ) ? $plugin_state['version'] : '' ); ?></td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					</details>
				<?php endif; ?>
			</section>
		<?php endif; ?>

		<?php if ( $snapshot_detail && ! empty( $snapshot_comparison ) ) : ?>
			<section class="znts-card znts-card-full znts-card-flat">
				<h2><?php echo esc_html__( 'Snapshot Comparison', 'zignites-sentinel' ); ?></h2>
				<table class="widefat striped">
					<tbody>
						<tr>
							<th scope="row"><?php echo esc_html__( 'Snapshot Theme', 'zignites-sentinel' ); ?></th>
							<td><?php echo esc_html( $snapshot_comparison['snapshot_theme'] ); ?></td>
						</tr>
						<tr>
							<th scope="row"><?php echo esc_html__( 'Current Theme', 'zignites-sentinel' ); ?></th>
							<td><?php echo esc_html( $snapshot_comparison['current_theme'] ); ?></td>
						</tr>
						<tr>
							<th scope="row"><?php echo esc_html__( 'Snapshot Core', 'zignites-sentinel' ); ?></th>
							<td><?php echo esc_html( $snapshot_comparison['snapshot_core_version'] ); ?></td>
						</tr>
						<tr>
							<th scope="row"><?php echo esc_html__( 'Current Core', 'zignites-sentinel' ); ?></th>
							<td><?php echo esc_html( $snapshot_comparison['current_core_version'] ); ?></td>
						</tr>
						<tr>
							<th scope="row"><?php echo esc_html__( 'Snapshot PHP', 'zignites-sentinel' ); ?></th>
							<td><?php echo esc_html( $snapshot_comparison['snapshot_php_version'] ); ?></td>
						</tr>
						<tr>
							<th scope="row"><?php echo esc_html__( 'Current PHP', 'zignites-sentinel' ); ?></th>
							<td><?php echo esc_html( $snapshot_comparison['current_php_version'] ); ?></td>
						</tr>
					</tbody>
				</table>
				<div class="znts-admin-grid znts-subgrid">
					<section class="znts-card znts-card-soft">
						<h3><?php echo esc_html__( 'Missing Snapshot Plugins', 'zignites-sentinel' ); ?></h3>
						<?php if ( empty( $snapshot_comparison['missing_plugins'] ) ) : ?>
							<p><?php echo esc_html__( 'None.', 'zignites-sentinel' ); ?></p>
						<?php else : ?>
							<ul class="znts-list">
								<?php foreach ( $snapshot_comparison['missing_plugins'] as $plugin_state ) : ?>
									<li><?php echo esc_html( isset( $plugin_state['name'] ) && $plugin_state['name'] ? $plugin_state['name'] : $plugin_state['plugin'] ); ?></li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</section>
					<section class="znts-card znts-card-soft">
						<h3><?php echo esc_html__( 'New Current Plugins', 'zignites-sentinel' ); ?></h3>
						<?php if ( empty( $snapshot_comparison['new_plugins'] ) ) : ?>
							<p><?php echo esc_html__( 'None.', 'zignites-sentinel' ); ?></p>
						<?php else : ?>
							<ul class="znts-list">
								<?php foreach ( $snapshot_comparison['new_plugins'] as $plugin_state ) : ?>
									<li><?php echo esc_html( isset( $plugin_state['name'] ) && $plugin_state['name'] ? $plugin_state['name'] : $plugin_state['plugin'] ); ?></li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</section>
					<section class="znts-card znts-card-soft">
						<h3><?php echo esc_html__( 'Changed Plugin Versions', 'zignites-sentinel' ); ?></h3>
						<?php if ( empty( $snapshot_comparison['version_changes'] ) ) : ?>
							<p><?php echo esc_html__( 'None.', 'zignites-sentinel' ); ?></p>
						<?php else : ?>
							<table class="widefat striped">
								<thead>
									<tr>
										<th><?php echo esc_html__( 'Plugin', 'zignites-sentinel' ); ?></th>
										<th><?php echo esc_html__( 'Snapshot', 'zignites-sentinel' ); ?></th>
										<th><?php echo esc_html__( 'Current', 'zignites-sentinel' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $snapshot_comparison['version_changes'] as $change ) : ?>
										<tr>
											<td><?php echo esc_html( $change['name'] ); ?></td>
											<td><?php echo esc_html( $change['snapshot_version'] ); ?></td>
											<td><?php echo esc_html( $change['current_version'] ); ?></td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						<?php endif; ?>
					</section>
				</div>
			</section>
		<?php endif; ?>

		<?php if ( $snapshot_detail && ! empty( $last_restore_check ) ) : ?>
			<section class="znts-card znts-card-full znts-card-flat">
				<h2><?php echo esc_html__( 'Restore Readiness Assessment', 'zignites-sentinel' ); ?></h2>
				<div class="znts-readiness-row">
					<span class="znts-pill znts-pill-<?php echo esc_attr( 'blocked' === $last_restore_check['status'] ? 'critical' : ( 'caution' === $last_restore_check['status'] ? 'warning' : 'info' ) ); ?>">
						<?php echo esc_html( ucfirst( $last_restore_check['status'] ) ); ?>
					</span>
					<span><?php echo esc_html( $last_restore_check['generated_at'] ); ?></span>
				</div>
				<p><?php echo esc_html( $last_restore_check['note'] ); ?></p>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php echo esc_html__( 'Check', 'zignites-sentinel' ); ?></th>
							<th><?php echo esc_html__( 'Status', 'zignites-sentinel' ); ?></th>
							<th><?php echo esc_html__( 'Message', 'zignites-sentinel' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $last_restore_check['checks'] as $check ) : ?>
							<tr>
								<td><?php echo esc_html( $check['label'] ); ?></td>
								<td>
									<span class="znts-pill znts-pill-<?php echo esc_attr( 'fail' === $check['status'] ? 'critical' : $check['status'] ); ?>">
										<?php echo esc_html( ucfirst( $check['status'] ) ); ?>
									</span>
								</td>
								<td><?php echo esc_html( $check['message'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<?php if ( ! empty( $restore_source_validation ) ) : ?>
					<h3><?php echo esc_html__( 'Snapshot Source Validation', 'zignites-sentinel' ); ?></h3>
					<p><?php echo esc_html( $restore_source_validation['message'] ); ?></p>
					<?php if ( ! empty( $restore_source_validation_check_rows ) ) : ?>
						<table class="widefat striped">
							<thead>
								<tr>
									<th><?php echo esc_html__( 'Check', 'zignites-sentinel' ); ?></th>
									<th><?php echo esc_html__( 'Status', 'zignites-sentinel' ); ?></th>
									<th><?php echo esc_html__( 'Message', 'zignites-sentinel' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $restore_source_validation_check_rows as $check ) : ?>
									<tr>
										<td><?php echo esc_html( $check['label'] ); ?></td>
										<td>
											<span class="znts-pill znts-pill-<?php echo esc_attr( $check['badge'] ); ?>">
												<?php echo esc_html( $check['status_label'] ); ?>
											</span>
										</td>
										<td><?php echo esc_html( $check['message'] ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>
					<?php if ( ! empty( $restore_source_missing_plugins ) ) : ?>
						<h3><?php echo esc_html__( 'Unavailable Snapshot Plugin Sources', 'zignites-sentinel' ); ?></h3>
						<ul class="znts-list">
							<?php foreach ( $restore_source_missing_plugins as $plugin_label ) : ?>
								<li><?php echo esc_html( $plugin_label ); ?></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
					<?php if ( ! empty( $restore_source_missing_artifacts ) ) : ?>
						<h3><?php echo esc_html__( 'Unavailable Stored Artifacts', 'zignites-sentinel' ); ?></h3>
						<ul class="znts-list">
							<?php foreach ( $restore_source_missing_artifacts as $artifact_label ) : ?>
								<li><?php echo esc_html( $artifact_label ); ?></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				<?php endif; ?>
			</section>
		<?php endif; ?>

		<?php if ( $snapshot_detail && ! empty( $last_restore_dry_run ) ) : ?>
			<section id="znts-restore-dry-run" class="znts-card znts-card-full znts-card-flat">
				<h2><?php echo esc_html__( 'Restore Dry-Run', 'zignites-sentinel' ); ?></h2>
				<div class="znts-readiness-row">
					<span class="znts-pill znts-pill-<?php echo esc_attr( isset( $restore_dry_run_status['badge'] ) ? $restore_dry_run_status['badge'] : 'info' ); ?>">
						<?php echo esc_html( isset( $restore_dry_run_status['status_label'] ) ? $restore_dry_run_status['status_label'] : '' ); ?>
					</span>
					<span><?php echo esc_html( isset( $restore_dry_run_status['generated_at'] ) ? $restore_dry_run_status['generated_at'] : '' ); ?></span>
				</div>
				<p><?php echo esc_html( isset( $restore_dry_run_status['note'] ) ? $restore_dry_run_status['note'] : '' ); ?></p>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php echo esc_html__( 'Check', 'zignites-sentinel' ); ?></th>
							<th><?php echo esc_html__( 'Status', 'zignites-sentinel' ); ?></th>
							<th><?php echo esc_html__( 'Message', 'zignites-sentinel' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $restore_dry_run_check_rows as $check ) : ?>
							<tr>
								<td><?php echo esc_html( $check['label'] ); ?></td>
								<td>
									<span class="znts-pill znts-pill-<?php echo esc_attr( $check['badge'] ); ?>">
										<?php echo esc_html( $check['status_label'] ); ?>
									</span>
								</td>
								<td><?php echo esc_html( $check['message'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</section>
		<?php endif; ?>

		<?php if ( $snapshot_detail && ! empty( $last_restore_stage ) ) : ?>
			<section id="znts-restore-stage" class="znts-card znts-card-full znts-card-flat">
				<h2><?php echo esc_html__( 'Staged Restore Validation', 'zignites-sentinel' ); ?></h2>
				<div class="znts-readiness-row">
					<span class="znts-pill znts-pill-<?php echo esc_attr( isset( $restore_stage_status['badge'] ) ? $restore_stage_status['badge'] : 'info' ); ?>">
						<?php echo esc_html( isset( $restore_stage_status['status_label'] ) ? $restore_stage_status['status_label'] : '' ); ?>
					</span>
					<span><?php echo esc_html( isset( $restore_stage_status['generated_at'] ) ? $restore_stage_status['generated_at'] : '' ); ?></span>
				</div>
				<p><?php echo esc_html( isset( $restore_stage_status['note'] ) ? $restore_stage_status['note'] : '' ); ?></p>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php echo esc_html__( 'Check', 'zignites-sentinel' ); ?></th>
							<th><?php echo esc_html__( 'Status', 'zignites-sentinel' ); ?></th>
							<th><?php echo esc_html__( 'Message', 'zignites-sentinel' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $restore_stage_check_rows as $check ) : ?>
							<tr>
								<td><?php echo esc_html( $check['label'] ); ?></td>
								<td>
									<span class="znts-pill znts-pill-<?php echo esc_attr( $check['badge'] ); ?>">
										<?php echo esc_html( $check['status_label'] ); ?>
									</span>
								</td>
								<td><?php echo esc_html( $check['message'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</section>
		<?php endif; ?>

		<?php if ( $snapshot_detail && ! empty( $last_restore_plan ) ) : ?>
			<section id="znts-restore-plan" class="znts-card znts-card-full znts-card-flat">
				<h2><?php echo esc_html__( 'Restore Execution Plan', 'zignites-sentinel' ); ?></h2>
				<div class="znts-readiness-row">
					<span class="znts-pill znts-pill-<?php echo esc_attr( isset( $restore_plan_status['badge'] ) ? $restore_plan_status['badge'] : 'info' ); ?>">
						<?php echo esc_html( isset( $restore_plan_status['status_label'] ) ? $restore_plan_status['status_label'] : '' ); ?>
					</span>
					<span><?php echo esc_html( isset( $restore_plan_status['generated_at'] ) ? $restore_plan_status['generated_at'] : '' ); ?></span>
				</div>
				<p><?php echo esc_html( isset( $restore_plan_status['note'] ) ? $restore_plan_status['note'] : '' ); ?></p>
				<?php if ( ! empty( $restore_plan_check_rows ) ) : ?>
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php echo esc_html__( 'Check', 'zignites-sentinel' ); ?></th>
								<th><?php echo esc_html__( 'Status', 'zignites-sentinel' ); ?></th>
								<th><?php echo esc_html__( 'Message', 'zignites-sentinel' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $restore_plan_check_rows as $check ) : ?>
								<tr>
									<td><?php echo esc_html( $check['label'] ); ?></td>
									<td>
										<span class="znts-pill znts-pill-<?php echo esc_attr( $check['badge'] ); ?>">
											<?php echo esc_html( $check['status_label'] ); ?>
										</span>
									</td>
									<td><?php echo esc_html( $check['message'] ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
				<?php if ( ! empty( $restore_plan_item_rows ) ) : ?>
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php echo esc_html__( 'Type', 'zignites-sentinel' ); ?></th>
								<th><?php echo esc_html__( 'Label', 'zignites-sentinel' ); ?></th>
								<th><?php echo esc_html__( 'Action', 'zignites-sentinel' ); ?></th>
								<th><?php echo esc_html__( 'Target Path', 'zignites-sentinel' ); ?></th>
								<th><?php echo esc_html__( 'Conflicts', 'zignites-sentinel' ); ?></th>
								<th><?php echo esc_html__( 'Message', 'zignites-sentinel' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $restore_plan_item_rows as $item ) : ?>
								<tr>
									<td><?php echo esc_html( $item['type_label'] ); ?></td>
									<td><?php echo esc_html( $item['label'] ); ?></td>
									<td><?php echo esc_html( $item['action_label'] ); ?></td>
									<td><?php echo esc_html( $item['target_path'] ); ?></td>
									<td><?php echo esc_html( $item['conflict_count'] ); ?></td>
									<td><?php echo esc_html( $item['message'] ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
				<?php if ( ! empty( $restore_impact_summary ) ) : ?>
					<h3><?php echo esc_html__( 'Restore Impact Summary', 'zignites-sentinel' ); ?></h3>
					<div class="znts-readiness-row">
						<span class="znts-pill znts-pill-<?php echo esc_attr( isset( $restore_impact_summary['status'] ) ? $restore_impact_summary['status'] : 'info' ); ?>">
							<?php echo esc_html( isset( $restore_impact_summary['title'] ) ? $restore_impact_summary['title'] : '' ); ?>
						</span>
					</div>
					<p><?php echo esc_html( isset( $restore_impact_summary['message'] ) ? $restore_impact_summary['message'] : '' ); ?></p>
					<?php if ( ! empty( $restore_impact_summary['rows'] ) ) : ?>
						<table class="widefat striped">
							<tbody>
								<?php foreach ( $restore_impact_summary['rows'] as $impact_row ) : ?>
									<tr>
										<th scope="row"><?php echo esc_html( isset( $impact_row['label'] ) ? $impact_row['label'] : '' ); ?></th>
										<td><?php echo esc_html( isset( $impact_row['value'] ) ? $impact_row['value'] : '' ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>
					<?php if ( ! empty( $restore_impact_summary['blockers'] ) ) : ?>
						<h4><?php echo esc_html__( 'Execution blockers', 'zignites-sentinel' ); ?></h4>
						<ul class="znts-list">
							<?php foreach ( $restore_impact_summary['blockers'] as $blocker ) : ?>
								<li>
									<?php
									echo esc_html(
										sprintf(
											/* translators: 1: blocker label, 2: blocker message */
											__( '%1$s: %2$s', 'zignites-sentinel' ),
											isset( $blocker['label'] ) ? (string) $blocker['label'] : __( 'Requirement', 'zignites-sentinel' ),
											isset( $blocker['message'] ) ? (string) $blocker['message'] : ''
										)
									);
									?>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				<?php endif; ?>
				<h3><?php echo esc_html__( 'Guarded Live Restore', 'zignites-sentinel' ); ?></h3>
				<p><?php echo esc_html__( 'This writes the staged snapshot payload into live theme and plugin paths. Review the impact summary first. It only runs when staged validation passed for the same snapshot and the confirmation phrase is exact.', 'zignites-sentinel' ); ?></p>
				<?php if ( ! empty( $operator_checklist['can_execute'] ) ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="znts_execute_restore" />
						<input type="hidden" name="snapshot_id" value="<?php echo esc_attr( (string) $snapshot_detail['id'] ); ?>" />
						<?php wp_nonce_field( 'znts_execute_restore_action' ); ?>
						<p>
							<label for="znts-restore-confirmation"><?php echo esc_html__( 'Type confirmation phrase', 'zignites-sentinel' ); ?></label><br />
							<input id="znts-restore-confirmation" type="text" name="restore_confirmation_phrase" class="regular-text" placeholder="<?php echo esc_attr( isset( $last_restore_plan['confirmation_phrase'] ) ? $last_restore_plan['confirmation_phrase'] : sprintf( 'RESTORE SNAPSHOT %d', (int) $snapshot_detail['id'] ) ); ?>" />
						</p>
						<p class="description"><?php echo esc_html( isset( $last_restore_plan['confirmation_phrase'] ) ? $last_restore_plan['confirmation_phrase'] : sprintf( 'RESTORE SNAPSHOT %d', (int) $snapshot_detail['id'] ) ); ?></p>
						<?php submit_button( __( 'Execute Live Restore', 'zignites-sentinel' ), 'primary', 'submit', false ); ?>
					</form>
				<?php else : ?>
					<p class="description"><?php echo esc_html__( 'Live restore remains hidden until the operator checklist is complete for this snapshot.', 'zignites-sentinel' ); ?></p>
				<?php endif; ?>
				<?php if ( ! empty( $restore_resume_context['can_resume'] ) ) : ?>
					<h3><?php echo esc_html__( 'Resume Restore Execution', 'zignites-sentinel' ); ?></h3>
					<p>
						<?php
						echo esc_html(
							sprintf(
								/* translators: 1: completed item count, 2: journal entry count */
								__( 'A resumable execution journal exists with %1$d completed items across %2$d persisted entries.', 'zignites-sentinel' ),
								isset( $restore_resume_context['completed_item_count'] ) ? (int) $restore_resume_context['completed_item_count'] : 0,
								isset( $restore_resume_context['entry_count'] ) ? (int) $restore_resume_context['entry_count'] : 0
							)
						);
						?>
					</p>
					<?php if ( ! empty( $operator_checklist['can_execute'] ) ) : ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="znts_resume_restore" />
							<input type="hidden" name="snapshot_id" value="<?php echo esc_attr( (string) $snapshot_detail['id'] ); ?>" />
							<?php wp_nonce_field( 'znts_resume_restore_action' ); ?>
							<p>
								<label for="znts-resume-confirmation"><?php echo esc_html__( 'Type confirmation phrase', 'zignites-sentinel' ); ?></label><br />
								<input id="znts-resume-confirmation" type="text" name="restore_confirmation_phrase" class="regular-text" placeholder="<?php echo esc_attr( isset( $last_restore_plan['confirmation_phrase'] ) ? $last_restore_plan['confirmation_phrase'] : sprintf( 'RESTORE SNAPSHOT %d', (int) $snapshot_detail['id'] ) ); ?>" />
							</p>
							<p class="description"><?php echo esc_html( isset( $restore_resume_context['run_id'] ) ? sprintf( __( 'Run ID: %s', 'zignites-sentinel' ), $restore_resume_context['run_id'] ) : '' ); ?></p>
							<?php submit_button( __( 'Resume Restore Execution', 'zignites-sentinel' ), 'secondary', 'submit', false ); ?>
						</form>
					<?php else : ?>
						<p class="description"><?php echo esc_html__( 'Resume remains blocked until the operator checklist is complete and current.', 'zignites-sentinel' ); ?></p>
					<?php endif; ?>
					<?php if ( ! empty( $execution_checkpoint['checkpoint'] ) && is_array( $execution_checkpoint['checkpoint'] ) ) : ?>
						<h3><?php echo esc_html__( 'Discard Preserved Checkpoint', 'zignites-sentinel' ); ?></h3>
						<p><?php echo esc_html__( 'This removes the preserved execution stage and clears the execution checkpoint. Resume will still be possible from the persisted journal, but stage extraction and health verification reuse will be lost.', 'zignites-sentinel' ); ?></p>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="znts_discard_restore_execution_checkpoint" />
							<input type="hidden" name="snapshot_id" value="<?php echo esc_attr( (string) $snapshot_detail['id'] ); ?>" />
							<?php wp_nonce_field( 'znts_discard_restore_execution_checkpoint_action' ); ?>
							<?php submit_button( __( 'Discard Execution Checkpoint', 'zignites-sentinel' ), 'delete', 'submit', false ); ?>
						</form>
					<?php endif; ?>
				<?php endif; ?>
			</section>
		<?php endif; ?>

		<?php if ( $snapshot_detail && ! empty( $last_restore_execution ) ) : ?>
			<section class="znts-card znts-card-full znts-card-flat">
				<h2><?php echo esc_html__( 'Restore Execution Result', 'zignites-sentinel' ); ?></h2>
				<div class="znts-readiness-row">
					<span class="znts-pill znts-pill-<?php echo esc_attr( isset( $restore_execution_status['badge'] ) ? $restore_execution_status['badge'] : 'info' ); ?>">
						<?php echo esc_html( isset( $restore_execution_status['status_label'] ) ? $restore_execution_status['status_label'] : '' ); ?>
					</span>
					<span><?php echo esc_html( isset( $restore_execution_status['generated_at'] ) ? $restore_execution_status['generated_at'] : '' ); ?></span>
				</div>
				<p><?php echo esc_html( isset( $restore_execution_status['note'] ) ? $restore_execution_status['note'] : '' ); ?></p>
				<?php if ( ! empty( $last_restore_execution['backup_root'] ) ) : ?>
					<p><strong><?php echo esc_html__( 'Backup Root:', 'zignites-sentinel' ); ?></strong> <?php echo esc_html( $last_restore_execution['backup_root'] ); ?></p>
				<?php endif; ?>
				<?php if ( ! empty( $last_restore_execution['run_id'] ) ) : ?>
					<p>
						<strong><?php echo esc_html__( 'Run ID:', 'zignites-sentinel' ); ?></strong>
						<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'zignites-sentinel-event-logs', 'source' => 'restore-execution-journal', 'run_id' => $last_restore_execution['run_id'] ), admin_url( 'admin.php' ) ) ); ?>">
							<?php echo esc_html( $last_restore_execution['run_id'] ); ?>
						</a>
					</p>
				<?php endif; ?>
				<?php if ( ! empty( $last_restore_execution['resumed_run'] ) ) : ?>
					<p><?php echo esc_html__( 'This execution reused persisted journal state from a prior run.', 'zignites-sentinel' ); ?></p>
				<?php endif; ?>
				<?php if ( ! empty( $last_restore_execution['health_verification'] ) ) : ?>
					<h3><?php echo esc_html__( 'Post-Restore Health Verification', 'zignites-sentinel' ); ?></h3>
					<div class="znts-readiness-row">
						<span class="znts-pill znts-pill-<?php echo esc_attr( isset( $restore_execution_health_status['badge'] ) ? $restore_execution_health_status['badge'] : 'info' ); ?>">
							<?php echo esc_html( isset( $restore_execution_health_status['status_label'] ) ? $restore_execution_health_status['status_label'] : '' ); ?>
						</span>
						<span><?php echo esc_html( isset( $restore_execution_health_status['generated_at'] ) ? $restore_execution_health_status['generated_at'] : '' ); ?></span>
					</div>
					<p><?php echo esc_html( isset( $restore_execution_health_status['note'] ) ? $restore_execution_health_status['note'] : '' ); ?></p>
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php echo esc_html__( 'Check', 'zignites-sentinel' ); ?></th>
								<th><?php echo esc_html__( 'Status', 'zignites-sentinel' ); ?></th>
								<th><?php echo esc_html__( 'Message', 'zignites-sentinel' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $restore_execution_health_check_rows as $check ) : ?>
								<tr>
									<td><?php echo esc_html( $check['label'] ); ?></td>
									<td>
										<span class="znts-pill znts-pill-<?php echo esc_attr( $check['badge'] ); ?>">
											<?php echo esc_html( $check['status_label'] ); ?>
										</span>
									</td>
									<td><?php echo esc_html( $check['message'] ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
				<?php if ( ! empty( $restore_execution_check_rows ) ) : ?>
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php echo esc_html__( 'Check', 'zignites-sentinel' ); ?></th>
								<th><?php echo esc_html__( 'Status', 'zignites-sentinel' ); ?></th>
								<th><?php echo esc_html__( 'Message', 'zignites-sentinel' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $restore_execution_check_rows as $check ) : ?>
								<tr>
									<td><?php echo esc_html( $check['label'] ); ?></td>
									<td>
										<span class="znts-pill znts-pill-<?php echo esc_attr( $check['badge'] ); ?>">
											<?php echo esc_html( $check['status_label'] ); ?>
										</span>
									</td>
									<td><?php echo esc_html( $check['message'] ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
				<?php if ( ! empty( $restore_execution_item_rows ) ) : ?>
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php echo esc_html__( 'Label', 'zignites-sentinel' ); ?></th>
								<th><?php echo esc_html__( 'Action', 'zignites-sentinel' ); ?></th>
								<th><?php echo esc_html__( 'Status', 'zignites-sentinel' ); ?></th>
								<th><?php echo esc_html__( 'Message', 'zignites-sentinel' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $restore_execution_item_rows as $item ) : ?>
								<tr>
									<td><?php echo esc_html( $item['label'] ); ?></td>
									<td><?php echo esc_html( $item['action_label'] ); ?></td>
									<td>
										<span class="znts-pill znts-pill-<?php echo esc_attr( $item['badge'] ); ?>">
											<?php echo esc_html( $item['status_label'] ); ?>
										</span>
									</td>
									<td><?php echo esc_html( $item['message'] ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
				<?php if ( ! empty( $restore_execution_journal_rows ) ) : ?>
					<h3><?php echo esc_html__( 'Execution Journal', 'zignites-sentinel' ); ?></h3>
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
							<?php foreach ( $restore_execution_journal_rows as $entry ) : ?>
								<tr>
									<td><?php echo esc_html( $entry['timestamp'] ); ?></td>
									<td><?php echo esc_html( $entry['scope'] ); ?></td>
									<td><?php echo esc_html( $entry['label'] ); ?></td>
									<td><?php echo esc_html( $entry['phase'] ); ?></td>
									<td>
										<span class="znts-pill znts-pill-<?php echo esc_attr( $entry['badge'] ); ?>">
											<?php echo esc_html( $entry['status_label'] ); ?>
										</span>
									</td>
									<td><?php echo esc_html( $entry['message'] ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
				<?php if ( ! empty( $last_restore_execution['backup_root'] ) ) : ?>
					<h3><?php echo esc_html__( 'Rollback From Backup', 'zignites-sentinel' ); ?></h3>
					<p><?php echo esc_html__( 'This restores the previously live payloads from the backup root created during restore execution.', 'zignites-sentinel' ); ?></p>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="znts_rollback_restore" />
						<input type="hidden" name="snapshot_id" value="<?php echo esc_attr( (string) $snapshot_detail['id'] ); ?>" />
						<?php wp_nonce_field( 'znts_rollback_restore_action' ); ?>
						<p>
							<label for="znts-rollback-confirmation"><?php echo esc_html__( 'Type rollback confirmation phrase', 'zignites-sentinel' ); ?></label><br />
							<input id="znts-rollback-confirmation" type="text" name="rollback_confirmation_phrase" class="regular-text" placeholder="<?php echo esc_attr( isset( $last_restore_execution['rollback_confirmation_phrase'] ) ? $last_restore_execution['rollback_confirmation_phrase'] : sprintf( 'ROLLBACK SNAPSHOT %d', (int) $snapshot_detail['id'] ) ); ?>" />
						</p>
						<p class="description"><?php echo esc_html( isset( $last_restore_execution['rollback_confirmation_phrase'] ) ? $last_restore_execution['rollback_confirmation_phrase'] : sprintf( 'ROLLBACK SNAPSHOT %d', (int) $snapshot_detail['id'] ) ); ?></p>
						<?php submit_button( __( 'Run Rollback', 'zignites-sentinel' ), 'secondary', 'submit', false ); ?>
					</form>
					<?php if ( ! empty( $restore_rollback_resume_context['can_resume'] ) ) : ?>
						<h3><?php echo esc_html__( 'Resume Rollback', 'zignites-sentinel' ); ?></h3>
						<p>
							<?php
							echo esc_html(
								sprintf(
									/* translators: 1: completed item count, 2: journal entry count */
									__( 'A resumable rollback journal exists with %1$d completed items across %2$d persisted entries.', 'zignites-sentinel' ),
									isset( $restore_rollback_resume_context['completed_item_count'] ) ? (int) $restore_rollback_resume_context['completed_item_count'] : 0,
									isset( $restore_rollback_resume_context['entry_count'] ) ? (int) $restore_rollback_resume_context['entry_count'] : 0
								)
							);
							?>
						</p>
						<?php if ( ! empty( $restore_rollback_resume_context['checkpoint_item_count'] ) ) : ?>
							<p class="description">
								<?php
								echo esc_html(
									sprintf(
										/* translators: 1: completed count, 2: tracked item count */
										__( 'Rollback checkpoint state currently tracks %1$d completed items across %2$d item checkpoints.', 'zignites-sentinel' ),
										isset( $restore_rollback_resume_context['checkpoint_completed_count'] ) ? (int) $restore_rollback_resume_context['checkpoint_completed_count'] : 0,
										(int) $restore_rollback_resume_context['checkpoint_item_count']
									)
								);
								?>
							</p>
						<?php endif; ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="znts_resume_restore_rollback" />
							<input type="hidden" name="snapshot_id" value="<?php echo esc_attr( (string) $snapshot_detail['id'] ); ?>" />
							<?php wp_nonce_field( 'znts_resume_restore_rollback_action' ); ?>
							<p>
								<label for="znts-resume-rollback-confirmation"><?php echo esc_html__( 'Type rollback confirmation phrase', 'zignites-sentinel' ); ?></label><br />
								<input id="znts-resume-rollback-confirmation" type="text" name="rollback_confirmation_phrase" class="regular-text" placeholder="<?php echo esc_attr( isset( $last_restore_execution['rollback_confirmation_phrase'] ) ? $last_restore_execution['rollback_confirmation_phrase'] : sprintf( 'ROLLBACK SNAPSHOT %d', (int) $snapshot_detail['id'] ) ); ?>" />
							</p>
							<p class="description"><?php echo esc_html( isset( $restore_rollback_resume_context['run_id'] ) ? sprintf( __( 'Run ID: %s', 'zignites-sentinel' ), $restore_rollback_resume_context['run_id'] ) : '' ); ?></p>
							<?php submit_button( __( 'Resume Rollback', 'zignites-sentinel' ), 'secondary', 'submit', false ); ?>
						</form>
					<?php endif; ?>
				<?php endif; ?>
			</section>
		<?php endif; ?>

		<?php if ( $snapshot_detail && ! empty( $last_restore_rollback ) ) : ?>
			<section class="znts-card znts-card-full znts-card-flat">
				<h2><?php echo esc_html__( 'Restore Rollback Result', 'zignites-sentinel' ); ?></h2>
				<div class="znts-readiness-row">
					<span class="znts-pill znts-pill-<?php echo esc_attr( isset( $restore_rollback_status['badge'] ) ? $restore_rollback_status['badge'] : 'info' ); ?>">
						<?php echo esc_html( isset( $restore_rollback_status['status_label'] ) ? $restore_rollback_status['status_label'] : '' ); ?>
					</span>
					<span><?php echo esc_html( isset( $restore_rollback_status['generated_at'] ) ? $restore_rollback_status['generated_at'] : '' ); ?></span>
				</div>
				<p><?php echo esc_html( isset( $restore_rollback_status['note'] ) ? $restore_rollback_status['note'] : '' ); ?></p>
				<?php if ( ! empty( $last_restore_rollback['run_id'] ) ) : ?>
					<p>
						<strong><?php echo esc_html__( 'Run ID:', 'zignites-sentinel' ); ?></strong>
						<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'zignites-sentinel-event-logs', 'source' => 'restore-rollback-journal', 'run_id' => $last_restore_rollback['run_id'] ), admin_url( 'admin.php' ) ) ); ?>">
							<?php echo esc_html( $last_restore_rollback['run_id'] ); ?>
						</a>
					</p>
				<?php endif; ?>
				<?php if ( ! empty( $last_restore_rollback['health_verification'] ) ) : ?>
					<h3><?php echo esc_html__( 'Post-Rollback Health Verification', 'zignites-sentinel' ); ?></h3>
					<div class="znts-readiness-row">
						<span class="znts-pill znts-pill-<?php echo esc_attr( isset( $restore_rollback_health_status['badge'] ) ? $restore_rollback_health_status['badge'] : 'info' ); ?>">
							<?php echo esc_html( isset( $restore_rollback_health_status['status_label'] ) ? $restore_rollback_health_status['status_label'] : '' ); ?>
						</span>
						<span><?php echo esc_html( isset( $restore_rollback_health_status['generated_at'] ) ? $restore_rollback_health_status['generated_at'] : '' ); ?></span>
					</div>
					<p><?php echo esc_html( isset( $restore_rollback_health_status['note'] ) ? $restore_rollback_health_status['note'] : '' ); ?></p>
				<?php endif; ?>
				<?php if ( ! empty( $restore_rollback_check_rows ) ) : ?>
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php echo esc_html__( 'Check', 'zignites-sentinel' ); ?></th>
								<th><?php echo esc_html__( 'Status', 'zignites-sentinel' ); ?></th>
								<th><?php echo esc_html__( 'Message', 'zignites-sentinel' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $restore_rollback_check_rows as $check ) : ?>
								<tr>
									<td><?php echo esc_html( $check['label'] ); ?></td>
									<td>
										<span class="znts-pill znts-pill-<?php echo esc_attr( $check['badge'] ); ?>">
											<?php echo esc_html( $check['status_label'] ); ?>
										</span>
									</td>
									<td><?php echo esc_html( $check['message'] ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
				<?php if ( ! empty( $restore_rollback_item_rows ) ) : ?>
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php echo esc_html__( 'Label', 'zignites-sentinel' ); ?></th>
								<th><?php echo esc_html__( 'Action', 'zignites-sentinel' ); ?></th>
								<th><?php echo esc_html__( 'Status', 'zignites-sentinel' ); ?></th>
								<th><?php echo esc_html__( 'Message', 'zignites-sentinel' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $restore_rollback_item_rows as $item ) : ?>
								<tr>
									<td><?php echo esc_html( $item['label'] ); ?></td>
									<td><?php echo esc_html( $item['action_label'] ); ?></td>
									<td>
										<span class="znts-pill znts-pill-<?php echo esc_attr( $item['badge'] ); ?>">
											<?php echo esc_html( $item['status_label'] ); ?>
										</span>
									</td>
									<td><?php echo esc_html( $item['message'] ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
				<?php if ( ! empty( $restore_rollback_journal_rows ) ) : ?>
					<h3><?php echo esc_html__( 'Rollback Journal', 'zignites-sentinel' ); ?></h3>
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
							<?php foreach ( $restore_rollback_journal_rows as $entry ) : ?>
								<tr>
									<td><?php echo esc_html( $entry['timestamp'] ); ?></td>
									<td><?php echo esc_html( $entry['scope'] ); ?></td>
									<td><?php echo esc_html( $entry['label'] ); ?></td>
									<td><?php echo esc_html( $entry['phase'] ); ?></td>
									<td>
										<span class="znts-pill znts-pill-<?php echo esc_attr( $entry['badge'] ); ?>">
											<?php echo esc_html( $entry['status_label'] ); ?>
										</span>
									</td>
									<td><?php echo esc_html( $entry['message'] ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</section>
		<?php endif; ?>

		<section class="znts-card znts-card-muted">
			<h2><?php echo esc_html__( 'Readiness Scope', 'zignites-sentinel' ); ?></h2>
			<ul class="znts-list">
				<li><?php echo esc_html__( 'This scan checks for common operational blockers and warnings before manual update activity.', 'zignites-sentinel' ); ?></li>
				<li><?php echo esc_html__( 'Snapshot records created here store metadata about the current site state, not full file backups.', 'zignites-sentinel' ); ?></li>
				<li><?php echo esc_html__( 'Manual update plans are review artifacts only and do not execute updates or restores.', 'zignites-sentinel' ); ?></li>
			</ul>
		</section>
	</div>
</div>


