# Zignites Sentinel Implementation Plan

## Phase 0: Product Repositioning and Safety Boundaries

Goal:
Narrow the product from broad "security plugin" messaging to "Safe Update Checkpoints and Rollback for WordPress."

Tasks:

1. Update product positioning
   - Replace broad security language with safe-update operations language.
   - Use "Safe Update Checkpoints and Rollback for WordPress."
   - Clarify that Sentinel protects plugin/theme code update workflows.
   - Remove or soften unsupported claims around malware, firewall, full backup, AI ops, and full monitoring.
   Acceptance criteria:
   - Admin UI, plugin headers, readme, settings copy, and onboarding text use the new positioning.
   - No misleading security-suite language remains.

2. Add limitation notices
   - Clearly state that DB, uploads, WordPress core, WooCommerce order/payment state, and malware cleanup are not covered.
   - Add notices in dashboard, Before Update screen, restore flow, and documentation.
   Acceptance criteria:
   - Users see clear limitation warnings before checkpoint and restore actions.

3. Remove or feature-flag hidden legacy admin actions
   - Audit all admin handlers.
   - Remove unused handlers from simplified UI.
   - Feature-flag experimental or deprecated actions.
   Acceptance criteria:
   - No unused admin action remains publicly registered unless explicitly feature-flagged.

## Phase 1: Core Reliability Hardening

Goal:
Make snapshot, package, stage, restore, and rollback workflows safer on real production sites.

Tasks:

1. Add operation mutex/locking
   - Create a lock service for snapshot, package, stage, restore, rollback, and cleanup operations.
   - Prevent overlapping operations from multiple admins, browser tabs, cron, or retries.
   - Add lock timeout and stale-lock recovery.
   Acceptance criteria:
   - Starting a second heavy operation returns a safe error.
   - Locks clear after success, failure, or timeout.

2. Add disk/quota preflight
   - Estimate required space for package, staging, backup, and restore overhead.
   - Check available disk space before checkpoint and restore.
   - Warn or block if space is insufficient.
   Acceptance criteria:
   - Large operations are blocked before starting when disk space is unsafe.

3. Fix PHP requirement mismatch
   - Align preflight checks with actual PHP 8.0 requirement.
   - Update admin notices and readme requirement text.
   Acceptance criteria:
   - No PHP 7.4 messaging remains if plugin requires PHP 8.0.

4. Fix stale internal version strings
   - Replace stale user-agent text such as ZignitesSentinel/1.21.0.
   - Centralize plugin version reference.
   Acceptance criteria:
   - Internal version references use one source of truth.

5. Add retention controls
   - Add settings for log retention, snapshot retention, package retention, restore backup retention, and failed staging retention.
   - Extend cleanup cron to enforce these policies.
   Acceptance criteria:
   - Old artifacts and logs are cleaned according to configured retention rules.

## Phase 2: Secure Artifact Handling

Goal:
Treat snapshot packages and restore artifacts as sensitive backups.

Tasks:

1. Add artifact exposure scanner
   - Generate a test artifact or test URL.
   - Check whether uploads-based artifact paths are publicly accessible.
   - Surface warnings for Nginx, CDN, object storage, or misconfigured hosts.
   Acceptance criteria:
   - Admin dashboard reports whether artifact storage appears publicly exposed.

2. Strengthen artifact storage protection
   - Ensure index.php, .htaccess, and web.config guards are present.
   - Add clearer warnings that these do not fully protect all hosts.
   - Consider moving artifacts outside public uploads when possible.
   Acceptance criteria:
   - Artifact directories are guarded and exposure risk is visible.

3. Add sensitive artifact warnings
   - Warn that plugin/theme code may contain secrets.
   - Warn before exporting/downloading packages or logs.
   Acceptance criteria:
   - Users receive explicit warnings before handling sensitive artifact exports.

4. Add optional offsite storage design placeholder
   - Create interface/service boundary for future encrypted offsite storage.
   - Do not fully implement SaaS storage yet.
   Acceptance criteria:
   - Storage layer can later support local and remote encrypted backends.

## Phase 3: Async Job System

Goal:
Move heavy filesystem operations out of synchronous admin requests.

Tasks:

1. Evaluate and integrate Action Scheduler or equivalent queue
   - Add dependency or internal queue abstraction.
   - Define job statuses: pending, running, completed, failed, cancelled.
   - Store job metadata and progress.
   Acceptance criteria:
   - Plugin can enqueue a background job and display status.

2. Convert snapshot/package creation to async job
   - Move hashing, ZIP creation, and artifact writing into background processing.
   - Add progress checkpoints.
   Acceptance criteria:
   - Large snapshot/package operations no longer rely on one admin request.

3. Convert staging/restore preparation to async job
   - Move validation, extraction, checksum verification, and staging into background processing.
   Acceptance criteria:
   - Restore preparation can run safely in the background with progress state.

4. Add job retry and failure reporting
   - Capture error details.
   - Add retry-safe behavior.
   - Prevent duplicate destructive actions.
   Acceptance criteria:
   - Failed jobs provide actionable error messages and do not corrupt state.

## Phase 4: Guided Safe Update Window Workflow

Goal:
Turn technical snapshot/restore features into a usable agency workflow.

Tasks:

1. Create "Safe Update Window" wizard
   - Step 1: environment preflight.
   - Step 2: disk/artifact/storage checks.
   - Step 3: create checkpoint.
   - Step 4: user performs plugin/theme updates.
   - Step 5: run post-update health checks.
   - Step 6: confirm success or rollback.
   Acceptance criteria:
   - User can follow one guided workflow from checkpoint to post-update verification.

2. Add health check configuration
   - Homepage check.
   - Admin check.
   - Optional custom URLs.
   - Fatal error detection.
   - Recent log regression summary.
   Acceptance criteria:
   - Post-update status is summarized clearly.

3. Improve History screen
   - Surface journal entries, validation output, checkpoint state, rollback status, and failure cause.
   Acceptance criteria:
   - History explains what happened, not just that an event occurred.

4. Add client-readable report
   - Generate simple update-window summary.
   - Include checkpoint time, components changed, health result, rollback status, and warnings.
   Acceptance criteria:
   - Agencies can export or copy a client-friendly maintenance report.

## Phase 5: Alerts and Integrations

Goal:
Make Sentinel useful for teams and agencies.

Tasks:

1. Add webhook notification service
   - Support generic webhook first.
   - Add event payloads for checkpoint created, restore started, restore failed, rollback completed, health check failed.
   Acceptance criteria:
   - Webhook notifications can be configured and tested.

2. Add Slack/Teams/Telegram/Discord adapters
   - Build on generic webhook service.
   - Add test notification button.
   Acceptance criteria:
   - Users can send test alerts and receive real operation alerts.

3. Add external uptime/error tool links
   - Add placeholder/deep-link fields for UptimeRobot, Sentry, New Relic, Datadog.
   - Do not overbuild full integrations yet.
   Acceptance criteria:
   - Update incidents can be correlated with external monitoring tools.

## Phase 6: WooCommerce Guardrails

Goal:
Avoid false confidence on WooCommerce stores while adding practical update-risk controls.

Tasks:

1. Detect WooCommerce
   - Detect active WooCommerce.
   - Display stronger warnings about DB migrations, orders, payments, and schema changes.
   Acceptance criteria:
   - WooCommerce stores receive explicit risk warnings.

2. Add WooCommerce-safe update mode
   - Recommend maintenance window.
   - Warn about active carts/orders if detectable.
   - Encourage external DB backup before update.
   - Block restore claims from implying DB rollback.
   Acceptance criteria:
   - WooCommerce workflow reduces risk without pretending to solve DB recovery.

3. Add WooCommerce report section
   - Include WooCommerce-specific warnings and checks in update reports.
   Acceptance criteria:
   - Client report clearly states WooCommerce limitations.

## Phase 7: Multisite and Hosting Compatibility

Goal:
Avoid unsupported claims and prepare for more complex WordPress environments.

Tasks:

1. Audit multisite assumptions
   - Network-active plugins.
   - Per-site options.
   - Network options.
   - Site iteration.
   - Network admin workflows.
   Acceptance criteria:
   - Plugin either safely supports multisite basics or clearly disables unsupported features.

2. Add read-only filesystem detection
   - Detect locked plugin/theme directories.
   - Detect ephemeral uploads risk where possible.
   - Warn for container/cloud deployments.
   Acceptance criteria:
   - Plugin does not silently attempt unsupported restore flows.

3. WordPress Filesystem API assessment
   - Identify where direct filesystem writes are used.
   - Decide whether to wrap or document limitations.
   Acceptance criteria:
   - Filesystem strategy is documented and major compatibility risks are visible.

## Phase 8: SaaS/Agency Platform Preparation

Goal:
Prepare architecture for future centralized agency dashboard without prematurely building it.

Tasks:

1. Add site identity and health summary model
   - Create local status payload shape.
   - Include checkpoint status, last update window, storage status, health status, and warnings.
   Acceptance criteria:
   - A future SaaS dashboard can consume a consistent local status model.

2. Add API boundary
   - Create internal service for future outbound sync.
   - Keep disabled by default.
   Acceptance criteria:
   - No SaaS dependency exists yet, but architecture is ready.

3. Add agency report foundations
   - White-label-ready report data structure.
   - Exportable summaries.
   Acceptance criteria:
   - Reports can later be centralized.

## Phase 9: AI-Assisted Features Later

Goal:
Use AI only after the operational foundation is reliable.

Tasks:

1. Add failure summary interface
   - Summarize logs, health checks, and restore journal.
   - Keep deterministic fallback summary.
   Acceptance criteria:
   - AI summary is assistive and never required for rollback safety.

2. Add update risk summary
   - Summarize changelog/version/context if available.
   - Do not auto-update or auto-rollback based only on AI.
   Acceptance criteria:
   - AI helps explain risk but does not perform unsafe autonomous actions.

3. Add client-friendly incident summary
   - Convert technical journal into plain-language report.
   Acceptance criteria:
   - Agencies can produce understandable incident summaries.

## Execution Rules

Work phase by phase and task by task.

For every task:

1. Inspect the relevant files first.
2. Create a short implementation plan.
3. Modify only the files needed.
4. Add or update tests where practical.
5. Run PHP lint and the existing test suite.
6. Report files changed, what was implemented, tests run, and risks or follow-up items.

Start with Phase 0, Task 1 only.

Do not jump ahead to SaaS, AI, WooCommerce, or integrations until P0 reliability and security hardening are complete.
