<?php
/**
 * Simplified Before Update view.
 *
 * @package ZignitesSentinel
 */

defined( 'ABSPATH' ) || exit;

$admin_page_url        = \Zignites\Sentinel\Admin\znts_admin_url( 'admin.php' );
$admin_post_url        = \Zignites\Sentinel\Admin\znts_admin_url( 'admin-post.php' );
$notice                = isset( $view_data['notice'] ) && is_array( $view_data['notice'] ) ? $view_data['notice'] : array();
$recent_snapshot_rows  = isset( $view_data['recent_snapshot_rows'] ) && is_array( $view_data['recent_snapshot_rows'] ) ? $view_data['recent_snapshot_rows'] : array();
$snapshot_empty_message = isset( $view_data['snapshot_empty_message'] ) ? (string) $view_data['snapshot_empty_message'] : '';
$restore_form_state    = isset( $view_data['restore_form_state'] ) && is_array( $view_data['restore_form_state'] ) ? $view_data['restore_form_state'] : array();
$selected_snapshot_label = isset( $view_data['selected_snapshot_label'] ) ? (string) $view_data['selected_snapshot_label'] : '';
$selected_snapshot_note = isset( $view_data['selected_snapshot_note'] ) ? (string) $view_data['selected_snapshot_note'] : '';
$workspace_status_label = isset( $view_data['workspace_status_label'] ) ? (string) $view_data['workspace_status_label'] : '';
$workspace_status_badge = isset( $view_data['workspace_status_badge'] ) ? (string) $view_data['workspace_status_badge'] : 'info';
$workspace_next_action = isset( $view_data['workspace_next_action'] ) ? (string) $view_data['workspace_next_action'] : '';
$first_run_notice      = isset( $view_data['first_run_notice'] ) && is_array( $view_data['first_run_notice'] ) ? $view_data['first_run_notice'] : array();
$readiness_history_empty_state = isset( $view_data['readiness_history_empty_state'] ) && is_array( $view_data['readiness_history_empty_state'] ) ? $view_data['readiness_history_empty_state'] : array();
$restore_history_empty_state = isset( $view_data['restore_history_empty_state'] ) && is_array( $view_data['restore_history_empty_state'] ) ? $view_data['restore_history_empty_state'] : array();
$workspace_help_panels = isset( $view_data['workspace_help_panels'] ) && is_array( $view_data['workspace_help_panels'] ) ? $view_data['workspace_help_panels'] : array();
$workspace_positioning_note = isset( $view_data['workspace_positioning_note'] ) && is_array( $view_data['workspace_positioning_note'] ) ? $view_data['workspace_positioning_note'] : array();
$restore_readiness_status = isset( $view_data['restore_readiness_status'] ) && is_array( $view_data['restore_readiness_status'] ) ? $view_data['restore_readiness_status'] : array();
$restore_dry_run_status = isset( $view_data['restore_dry_run_status'] ) && is_array( $view_data['restore_dry_run_status'] ) ? $view_data['restore_dry_run_status'] : array();
$restore_stage_status   = isset( $view_data['restore_stage_status'] ) && is_array( $view_data['restore_stage_status'] ) ? $view_data['restore_stage_status'] : array();
$restore_plan_status    = isset( $view_data['restore_plan_status'] ) && is_array( $view_data['restore_plan_status'] ) ? $view_data['restore_plan_status'] : array();
$restore_execution_status = isset( $view_data['restore_execution_status'] ) && is_array( $view_data['restore_execution_status'] ) ? $view_data['restore_execution_status'] : array();
$restore_execution_health_status = isset( $view_data['restore_execution_health_status'] ) && is_array( $view_data['restore_execution_health_status'] ) ? $view_data['restore_execution_health_status'] : array();
$restore_rollback_status = isset( $view_data['restore_rollback_status'] ) && is_array( $view_data['restore_rollback_status'] ) ? $view_data['restore_rollback_status'] : array();
$restore_rollback_health_status = isset( $view_data['restore_rollback_health_status'] ) && is_array( $view_data['restore_rollback_health_status'] ) ? $view_data['restore_rollback_health_status'] : array();
$snapshot_activity_url  = isset( $view_data['snapshot_activity_url'] ) ? (string) $view_data['snapshot_activity_url'] : '';
$has_form_snapshot      = ! empty( $restore_form_state['has_selected_snapshot'] );
$form_snapshot_id       = isset( $restore_form_state['selected_snapshot_id'] ) ? (string) $restore_form_state['selected_snapshot_id'] : '';
$can_execute_restore    = ! empty( $restore_form_state['can_execute_restore'] );
$can_resume_restore     = ! empty( $restore_form_state['can_resume_restore'] );
$can_resume_rollback    = ! empty( $restore_form_state['can_resume_rollback'] );
?>
<div class="wrap znts-admin-page">
	<div class="znts-page-header">
		<h1><?php echo esc_html__( 'Before Update', 'zignites-sentinel' ); ?></h1>
		<p class="znts-page-intro"><?php echo esc_html__( 'Create a rollback checkpoint of your active plugins and theme before updates, then restore it if an update breaks the code layer.', 'zignites-sentinel' ); ?></p>
	</div>

	<?php if ( ! empty( $notice ) ) : ?>
		<div class="notice notice-<?php echo esc_attr( isset( $notice['type'] ) ? $notice['type'] : 'info' ); ?> is-dismissible">
			<p><?php echo esc_html( isset( $notice['message'] ) ? $notice['message'] : '' ); ?></p>
		</div>
	<?php endif; ?>

	<section class="znts-summary-hero">
		<span class="znts-eyebrow"><?php echo esc_html__( 'Checkpoint Workflow', 'zignites-sentinel' ); ?></span>
		<div class="znts-readiness-row">
			<span class="znts-pill znts-pill-<?php echo esc_attr( $workspace_status_badge ); ?>">
				<?php echo esc_html( $workspace_status_label ); ?>
			</span>
		</div>
		<h2 class="znts-hero-title"><?php echo esc_html( $has_form_snapshot ? $selected_snapshot_label : __( 'Create a checkpoint before you update.', 'zignites-sentinel' ) ); ?></h2>
		<p class="znts-hero-subtitle"><?php echo esc_html( $has_form_snapshot ? $selected_snapshot_note : __( 'Save the current active theme and plugins so you can restore that code layer if an update breaks it.', 'zignites-sentinel' ) ); ?></p>
		<div class="znts-flow-note">
			<strong><?php echo esc_html__( 'Next step', 'zignites-sentinel' ); ?></strong>
			<span><?php echo esc_html( $workspace_next_action ); ?></span>
		</div>
		<div class="znts-flow-note">
			<strong><?php echo esc_html__( 'Restore boundary', 'zignites-sentinel' ); ?></strong>
			<span><?php echo esc_html__( 'This plugin restores the active theme and active plugins only. It does not restore the database, uploads/media, or WordPress core. Use a full backup solution for full-site recovery.', 'zignites-sentinel' ); ?></span>
		</div>
		<div class="znts-flow-note">
			<strong><?php echo esc_html__( 'Best fit', 'zignites-sentinel' ); ?></strong>
			<span><?php echo esc_html__( 'Use Sentinel when you want a rollback checkpoint for plugin and theme updates. Do not treat it as a full backup replacement.', 'zignites-sentinel' ); ?></span>
		</div>
	</section>

	<div class="znts-admin-grid znts-readiness-grid">
		<?php if ( ! empty( $first_run_notice ) ) : ?>
			<section class="znts-card znts-card-full znts-card-primary">
				<h2><?php echo esc_html( isset( $first_run_notice['title'] ) ? $first_run_notice['title'] : '' ); ?></h2>
				<p><?php echo esc_html( isset( $first_run_notice['description'] ) ? $first_run_notice['description'] : '' ); ?></p>
				<div class="znts-flow-note">
					<strong><?php echo esc_html__( 'Next step', 'zignites-sentinel' ); ?></strong>
					<span><?php echo esc_html( isset( $first_run_notice['next_step'] ) ? $first_run_notice['next_step'] : '' ); ?></span>
				</div>
			</section>
		<?php endif; ?>

		<?php if ( ! empty( $workspace_help_panels ) ) : ?>
			<section class="znts-card znts-card-full znts-card-flat">
				<h2><?php echo esc_html__( 'How Sentinel Works', 'zignites-sentinel' ); ?></h2>
				<div class="znts-summary-strip">
					<?php foreach ( $workspace_help_panels as $panel ) : ?>
						<div class="znts-summary-item">
							<span><?php echo esc_html( isset( $panel['title'] ) ? $panel['title'] : '' ); ?></span>
							<p><?php echo esc_html( isset( $panel['body'] ) ? $panel['body'] : '' ); ?></p>
						</div>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endif; ?>

		<?php if ( ! empty( $workspace_positioning_note ) ) : ?>
			<section class="znts-card znts-card-full znts-card-flat">
				<h2><?php echo esc_html( isset( $workspace_positioning_note['title'] ) ? $workspace_positioning_note['title'] : __( 'What Sentinel is designed to do', 'zignites-sentinel' ) ); ?></h2>
				<p><?php echo esc_html( isset( $workspace_positioning_note['body'] ) ? $workspace_positioning_note['body'] : '' ); ?></p>
			</section>
		<?php endif; ?>

		<?php if ( ! empty( $readiness_history_empty_state ) || ! empty( $restore_history_empty_state ) ) : ?>
			<section class="znts-card znts-card-full znts-card-flat">
				<h2><?php echo esc_html__( 'Adoption Guide', 'zignites-sentinel' ); ?></h2>
				<div class="znts-summary-strip">
					<?php if ( ! empty( $readiness_history_empty_state ) ) : ?>
						<div class="znts-summary-item">
							<span><?php echo esc_html( isset( $readiness_history_empty_state['title'] ) ? $readiness_history_empty_state['title'] : '' ); ?></span>
							<p><?php echo esc_html( isset( $readiness_history_empty_state['description'] ) ? $readiness_history_empty_state['description'] : '' ); ?></p>
							<p><?php echo esc_html( isset( $readiness_history_empty_state['next_step'] ) ? $readiness_history_empty_state['next_step'] : '' ); ?></p>
						</div>
					<?php endif; ?>
					<?php if ( ! empty( $restore_history_empty_state ) ) : ?>
						<div class="znts-summary-item">
							<span><?php echo esc_html( isset( $restore_history_empty_state['title'] ) ? $restore_history_empty_state['title'] : '' ); ?></span>
							<p><?php echo esc_html( isset( $restore_history_empty_state['description'] ) ? $restore_history_empty_state['description'] : '' ); ?></p>
							<p><?php echo esc_html( isset( $restore_history_empty_state['next_step'] ) ? $restore_history_empty_state['next_step'] : '' ); ?></p>
						</div>
					<?php endif; ?>
				</div>
			</section>
		<?php endif; ?>

		<section class="znts-card znts-card-full znts-card-primary">
			<h2><?php echo esc_html__( 'Create Checkpoint', 'zignites-sentinel' ); ?></h2>
			<p><?php echo esc_html__( 'Use this before plugin or theme updates. Sentinel saves rollback artifacts for the active theme and active plugins.', 'zignites-sentinel' ); ?></p>
			<form method="post" action="<?php echo esc_url( $admin_post_url ); ?>">
				<input type="hidden" name="action" value="znts_create_snapshot" />
				<?php wp_nonce_field( 'znts_create_snapshot_action' ); ?>
				<?php submit_button( __( 'Create Checkpoint', 'zignites-sentinel' ), 'primary', 'submit', false ); ?>
			</form>
		</section>

		<section class="znts-card znts-card-full znts-card-flat">
			<h2><?php echo esc_html__( 'Saved Checkpoints', 'zignites-sentinel' ); ?></h2>
			<?php if ( empty( $recent_snapshot_rows ) ) : ?>
				<p><?php echo esc_html( $snapshot_empty_message ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php echo esc_html__( 'Checkpoint', 'zignites-sentinel' ); ?></th>
							<th><?php echo esc_html__( 'Captured', 'zignites-sentinel' ); ?></th>
							<th><?php echo esc_html__( 'Status', 'zignites-sentinel' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $recent_snapshot_rows as $snapshot ) : ?>
							<tr>
								<td><a href="<?php echo esc_url( isset( $snapshot['detail_url'] ) ? $snapshot['detail_url'] : '' ); ?>"><?php echo esc_html( isset( $snapshot['label'] ) ? $snapshot['label'] : '' ); ?></a></td>
								<td><?php echo esc_html( isset( $snapshot['created_at'] ) ? $snapshot['created_at'] : '' ); ?></td>
								<td><?php echo esc_html( isset( $snapshot['relationship'] ) && '' !== $snapshot['relationship'] ? $snapshot['relationship'] : ( isset( $snapshot['trust_label'] ) ? $snapshot['trust_label'] : '' ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</section>

		<?php if ( $has_form_snapshot ) : ?>
			<section class="znts-card znts-card-full znts-card-flat">
				<h2><?php echo esc_html__( 'Validate Checkpoint', 'zignites-sentinel' ); ?></h2>
				<p><?php echo esc_html__( 'Run these checks in order so the checkpoint is easier to trust before any live restore decision.', 'zignites-sentinel' ); ?></p>
				<div class="znts-actions">
					<form method="post" action="<?php echo esc_url( $admin_post_url ); ?>">
						<input type="hidden" name="action" value="znts_check_restore_readiness" />
						<input type="hidden" name="snapshot_id" value="<?php echo esc_attr( $form_snapshot_id ); ?>" />
						<?php wp_nonce_field( 'znts_check_restore_readiness_action' ); ?>
						<?php submit_button( __( 'Check Restore Readiness', 'zignites-sentinel' ), 'secondary', 'submit', false ); ?>
					</form>
					<form method="post" action="<?php echo esc_url( $admin_post_url ); ?>">
						<input type="hidden" name="action" value="znts_run_restore_dry_run" />
						<input type="hidden" name="snapshot_id" value="<?php echo esc_attr( $form_snapshot_id ); ?>" />
						<?php wp_nonce_field( 'znts_run_restore_dry_run_action' ); ?>
						<?php submit_button( __( 'Validate Checkpoint Package', 'zignites-sentinel' ), 'secondary', 'submit', false ); ?>
					</form>
					<form method="post" action="<?php echo esc_url( $admin_post_url ); ?>">
						<input type="hidden" name="action" value="znts_run_restore_stage" />
						<input type="hidden" name="snapshot_id" value="<?php echo esc_attr( $form_snapshot_id ); ?>" />
						<?php wp_nonce_field( 'znts_run_restore_stage_action' ); ?>
						<?php submit_button( __( 'Run Staged Validation', 'zignites-sentinel' ), 'secondary', 'submit', false ); ?>
					</form>
					<form method="post" action="<?php echo esc_url( $admin_post_url ); ?>">
						<input type="hidden" name="action" value="znts_build_restore_plan" />
						<input type="hidden" name="snapshot_id" value="<?php echo esc_attr( $form_snapshot_id ); ?>" />
						<?php wp_nonce_field( 'znts_build_restore_plan_action' ); ?>
						<?php submit_button( __( 'Build Restore Plan', 'zignites-sentinel' ), 'secondary', 'submit', false ); ?>
					</form>
				</div>
				<ul class="znts-list">
					<?php if ( ! empty( $restore_readiness_status ) ) : ?><li><?php echo esc_html( __( 'Restore readiness:', 'zignites-sentinel' ) . ' ' . $restore_readiness_status['status_label'] ); ?></li><?php endif; ?>
					<?php if ( ! empty( $restore_dry_run_status ) ) : ?><li><?php echo esc_html( __( 'Checkpoint package:', 'zignites-sentinel' ) . ' ' . $restore_dry_run_status['status_label'] ); ?></li><?php endif; ?>
					<?php if ( ! empty( $restore_stage_status ) ) : ?><li><?php echo esc_html( __( 'Staged validation:', 'zignites-sentinel' ) . ' ' . $restore_stage_status['status_label'] ); ?></li><?php endif; ?>
					<?php if ( ! empty( $restore_plan_status ) ) : ?><li><?php echo esc_html( __( 'Restore plan:', 'zignites-sentinel' ) . ' ' . $restore_plan_status['status_label'] ); ?></li><?php endif; ?>
				</ul>
			</section>

			<section class="znts-card znts-card-full znts-card-primary">
				<h2><?php echo esc_html__( 'Restore Checkpoint', 'zignites-sentinel' ); ?></h2>
				<p class="description"><?php echo esc_html__( 'Use this only after validation. Sentinel writes the checkpoint payload into live plugin and theme paths. It is not a full-site restore.', 'zignites-sentinel' ); ?></p>
				<?php if ( $can_execute_restore ) : ?>
					<form method="post" action="<?php echo esc_url( $admin_post_url ); ?>">
						<input type="hidden" name="action" value="znts_execute_restore" />
						<input type="hidden" name="snapshot_id" value="<?php echo esc_attr( $form_snapshot_id ); ?>" />
						<?php wp_nonce_field( 'znts_execute_restore_action' ); ?>
						<p>
							<label for="znts-restore-confirmation"><?php echo esc_html__( 'Type confirmation phrase', 'zignites-sentinel' ); ?></label><br />
							<input id="znts-restore-confirmation" type="text" name="restore_confirmation_phrase" class="regular-text" placeholder="<?php echo esc_attr( isset( $restore_form_state['restore_confirmation_phrase'] ) ? $restore_form_state['restore_confirmation_phrase'] : '' ); ?>" />
						</p>
						<?php submit_button( __( 'Restore Checkpoint', 'zignites-sentinel' ), 'primary', 'submit', false ); ?>
					</form>
				<?php else : ?>
					<p><?php echo esc_html( ! empty( $restore_form_state['pre_restore_block_message'] ) ? $restore_form_state['pre_restore_block_message'] : __( 'Restore stays blocked until the required validation and planning checks are current for this checkpoint.', 'zignites-sentinel' ) ); ?></p>
				<?php endif; ?>
				<?php if ( $can_resume_restore ) : ?>
					<form method="post" action="<?php echo esc_url( $admin_post_url ); ?>">
						<input type="hidden" name="action" value="znts_resume_restore" />
						<input type="hidden" name="snapshot_id" value="<?php echo esc_attr( $form_snapshot_id ); ?>" />
						<?php wp_nonce_field( 'znts_resume_restore_action' ); ?>
						<?php submit_button( __( 'Resume Restore', 'zignites-sentinel' ), 'secondary', 'submit', false ); ?>
					</form>
				<?php endif; ?>
			</section>
		<?php endif; ?>

		<?php if ( ! empty( $restore_execution_status ) ) : ?>
			<section class="znts-card znts-card-full znts-card-flat">
				<h2><?php echo esc_html__( 'Restore Result', 'zignites-sentinel' ); ?></h2>
				<p><?php echo esc_html( $restore_execution_status['status_label'] . ': ' . $restore_execution_status['note'] ); ?></p>
				<?php if ( ! empty( $restore_execution_health_status ) ) : ?>
					<p><?php echo esc_html__( 'Post-restore health:', 'zignites-sentinel' ); ?> <?php echo esc_html( $restore_execution_health_status['status_label'] ); ?></p>
				<?php endif; ?>
			</section>
		<?php endif; ?>

		<?php if ( $has_form_snapshot && ! empty( $restore_execution_status ) ) : ?>
			<section class="znts-card znts-card-full znts-card-primary">
				<h2><?php echo esc_html__( 'Rollback Last Restore', 'zignites-sentinel' ); ?></h2>
				<p class="description"><?php echo esc_html__( 'Use rollback when the last restore introduced a new problem and Sentinel still has the related backup context.', 'zignites-sentinel' ); ?></p>
				<form method="post" action="<?php echo esc_url( $admin_post_url ); ?>">
					<input type="hidden" name="action" value="znts_rollback_restore" />
					<input type="hidden" name="snapshot_id" value="<?php echo esc_attr( $form_snapshot_id ); ?>" />
					<?php wp_nonce_field( 'znts_rollback_restore_action' ); ?>
					<p>
						<label for="znts-rollback-confirmation"><?php echo esc_html__( 'Type rollback confirmation phrase', 'zignites-sentinel' ); ?></label><br />
						<input id="znts-rollback-confirmation" type="text" name="rollback_confirmation_phrase" class="regular-text" placeholder="<?php echo esc_attr( isset( $restore_form_state['rollback_confirmation_phrase'] ) ? $restore_form_state['rollback_confirmation_phrase'] : '' ); ?>" />
					</p>
					<?php submit_button( __( 'Rollback Last Restore', 'zignites-sentinel' ), 'secondary', 'submit', false ); ?>
				</form>
				<?php if ( $can_resume_rollback ) : ?>
					<form method="post" action="<?php echo esc_url( $admin_post_url ); ?>">
						<input type="hidden" name="action" value="znts_resume_restore_rollback" />
						<input type="hidden" name="snapshot_id" value="<?php echo esc_attr( $form_snapshot_id ); ?>" />
						<?php wp_nonce_field( 'znts_resume_restore_rollback_action' ); ?>
						<?php submit_button( __( 'Resume Rollback', 'zignites-sentinel' ), 'secondary', 'submit', false ); ?>
					</form>
				<?php endif; ?>
			</section>
		<?php endif; ?>

		<?php if ( ! empty( $restore_rollback_status ) ) : ?>
			<section class="znts-card znts-card-full znts-card-flat">
				<h2><?php echo esc_html__( 'Rollback Result', 'zignites-sentinel' ); ?></h2>
				<p><?php echo esc_html( $restore_rollback_status['status_label'] . ': ' . $restore_rollback_status['note'] ); ?></p>
				<?php if ( ! empty( $restore_rollback_health_status ) ) : ?>
					<p><?php echo esc_html__( 'Post-rollback health:', 'zignites-sentinel' ); ?> <?php echo esc_html( $restore_rollback_health_status['status_label'] ); ?></p>
				<?php endif; ?>
			</section>
		<?php endif; ?>

		<?php if ( '' !== $snapshot_activity_url ) : ?>
			<section class="znts-card znts-card-full znts-card-flat">
				<h2><?php echo esc_html__( 'Recent History', 'zignites-sentinel' ); ?></h2>
				<p><a href="<?php echo esc_url( $snapshot_activity_url ); ?>"><?php echo esc_html__( 'Open Full History', 'zignites-sentinel' ); ?></a></p>
			</section>
		<?php endif; ?>
	</div>
</div>
