# Zignites Sentinel Progress Audit

## Current State
- Plugin version: `1.32.0`
- Database version: `1.4.0`
- Status: advanced MVP / controlled-restore product foundation
- Current objective achieved: the plugin now covers snapshot capture, advisory readiness, staged validation, restore planning, guarded live restore, guarded rollback, health verification, audit reporting, checkpointing, resumability, and operator-facing admin workflows

## What Is Implemented

### 1. Diagnostics and Core Admin Foundation
- Dashboard with:
  - site stability score
  - environment snapshot
  - recent conflict signals
  - recent event logs
  - recent snapshots
- Structured event logging with source/severity/context support
- Conflict logging repository and health score service
- Admin menu with dedicated screens:
  - Dashboard
  - Update Readiness
  - Event Logs
- Non-destructive settings portability:
  - JSON export of Sentinel preferences
  - JSON import of Sentinel preferences
  - import sanitization against supported keys only

### 2. Snapshot System
- Manual snapshot metadata creation
- Snapshot records store:
  - core version
  - PHP version
  - active theme stylesheet
  - active plugin list
  - metadata payload
- Snapshot metadata includes a component manifest
- Snapshot retention cleanup exists
- Snapshot artifact rows are stored in a dedicated table

### 3. Rollback Artifact Model
- Snapshot artifacts are persisted in `znts_snapshot_artifacts`
- Artifacts include:
  - component entries
  - JSON snapshot export
  - ZIP rollback package
- JSON export is written under uploads
- ZIP package is written under uploads
- Artifact inspection compares stored artifact records against current disk state

### 4. Advisory Readiness Layer
- Preflight update-readiness scan
- Snapshot-to-current comparison:
  - theme drift
  - plugin drift
  - version drift
  - core/PHP drift
- Restore-readiness assessment:
  - snapshot completeness
  - filesystem readiness
  - theme alignment
  - plugin drift
  - runtime drift
  - source availability
- Update plan validation for plugin/theme/core sources and package metadata

### 5. Restore Validation Layers
- Restore dry-run validation
- Staged restore validation:
  - extract package into temporary staging area
  - validate manifests
  - validate package structure
  - validate payload presence
  - clean staging when appropriate
- Restore execution planning:
  - create/replace/reuse actions
  - conflict reporting
  - itemized plan summaries

### 6. Live Restore Execution
- Live restore exists and is real
- It is operator-gated and guarded by:
  - baseline capture requirement
  - fresh staged-validation checkpoint
  - fresh restore-plan checkpoint
  - explicit confirmation phrase
- Restore execution currently restores packaged plugin/theme payloads
- Existing live payloads are backed up before replacement
- Restore execution results are persisted

### 7. Rollback
- Rollback from backup exists
- Rollback is also guarded by explicit confirmation
- Rollback uses stored backup context from the exact restore execution
- Rollback can remove newly created restore targets and restore replaced ones
- Rollback results are persisted

### 8. Health Verification
- Snapshot health baseline capture
- Post-restore health verification
- Post-rollback health verification
- Health verifier checks:
  - front-end
  - login endpoint
  - REST API endpoint
  - authenticated admin endpoint
  - status codes
  - body presence
  - content sanity
  - obvious fatal-error signatures
- Health comparison matrix exists for:
  - baseline
  - post-restore
  - post-rollback

### 9. Journaling and Resume
- Restore execution journaling
- Rollback journaling
- Journal entries are persisted into event logs
- Restore execution can resume from persisted journal state
- Rollback can resume from persisted journal state
- Rollback checkpoints can now preserve per-item completion state and backup-root context
- Mixed resume state now merges persisted journal progress with per-item checkpoint state for both restore and rollback
- Execution checkpoints can preserve:
  - stage directory
  - stage reuse state
  - health verification reuse state
- Resume logic skips already completed items where possible

### 10. Checkpoints and Gate Control
- Stage checkpoint store
- Plan checkpoint store
- Execution checkpoint store
- Checkpoints are tied to rollback package fingerprints
- Checkpoint age limit is configurable in hours
- Expired checkpoints are logged once with dedupe
- Operator checklist exists and is enforced server-side
- Non-destructive `Refresh Checklist Gates` action reruns:
  - staged validation
  - restore plan generation

### 11. Audit and Traceability
- Snapshot audit report export as JSON
- Snapshot summary export as Markdown for operator/client handoff
- Audit report includes:
  - snapshot data
  - artifact data
  - activity
  - checkpoints
  - readiness
  - health sections
  - integrity metadata
- Audit report integrity includes:
  - payload hash
  - site signature
- Audit report verification exists on-screen by pasting JSON back in
- Audit integrity and verification logic is now extracted into a dedicated helper:
  - `includes/admin/class-audit-report-verifier.php`

### 12. Event Logs and Activity Views
- Event Logs screen supports:
  - severity filter
  - source filter
  - run ID filter
  - snapshot ID filter
  - text search
  - pagination
  - filtered CSV export
- Run journal view exists
- Run summaries exist
- Snapshot activity timeline exists on Update Readiness
- Dashboard now deep-links into snapshot-scoped Event Logs
- Event Logs export/filter path now has focused local regression coverage for:
  - filter normalization
  - shared WHERE-clause generation
  - CSV row formatting

### 13. Dashboard Operator UX
- Restore Readiness summary for latest snapshot
- Latest Snapshot Health strip
- Latest snapshot quick actions:
  - Capture Baseline
  - Export Audit
- Countdown/expiry messaging for stage and plan checkpoints
- Compact WordPress Dashboard widget for:
  - site status
  - recommended action
  - latest snapshot readiness badges
  - latest health summary
- Premium admin UI refinement now applied across:
  - Dashboard
  - Update Readiness
  - Event Logs
  - with softer card hierarchy, calmer spacing, unified badge treatment, and denser technical sections grouped more cleanly
- Progressive-disclosure pass now further reduces operator scan load by:
  - making snapshot next-step and risk callouts dominant
  - collapsing secondary health, audit, activity, and technical-detail sections behind native disclosure panels
  - collapsing long activity and log messages into previews with opt-in expansion
  - reducing dashboard pill and activity noise so status and next action stay visually dominant
- Final UI polish now further strengthens operator confidence by:
  - increasing contrast between dominant next-step/risk blocks and secondary evidence
  - making risk and danger states feel more urgent through accent borders and stronger row emphasis
  - renaming dense snapshot-detail sections to more human-readable labels
  - slightly increasing breathing room and line-height in dense areas without changing behavior

### 14. Update Readiness Operator UX
- Snapshot detail view
- Snapshot summary card with:
  - overview
  - evidence
  - current risks
  - recommended next steps
- Artifact detail and diff
- Health baseline section
- Operator checklist section
- Audit verification section
- Restore control summary
- Snapshot list label filter
- Final restore impact summary before live execution/resume:
  - planned create/replace/reuse counts
  - conflict count
  - backup storage behavior
  - baseline status
  - stage/plan gate freshness
  - confirmation phrase
  - current execution blockers
- Update Readiness now surfaces both execution and rollback checkpoint summaries:
  - tracked item counts
  - completion counts
  - phase distribution
  - backup-root context for rollback
- Dense snapshot detail sections now use grouped disclosure panels to reduce scan fatigue without hiding technical data

### 15. Local Regression Harness
- Local PHP test runner exists at:
  - `tests/run.php`
- Current focused coverage now includes:
  - snapshot status resolution
  - snapshot filters and site-status derivation
  - restore execution checkpoint storage
  - rollback checkpoint storage
  - mixed journal-plus-checkpoint resume state
  - settings export/import sanitization
  - audit report integrity and verification
  - restore operator checklist evaluation
  - Event Logs export filter/query behavior
  - Event Logs CSV row formatting
  - Event Logs presentation payloads
  - snapshot summary composition and Markdown export
  - restore impact summary composition
  - dashboard site-status and summary payload behavior
  - health comparison rows and dashboard health-strip behavior
  - live admin smoke runner helper behavior
  - resume-path admin presentation payloads
  - event log presentation payloads
  - shared cross-screen status presentation
- Main is now current through the merged read-only presentation cleanup for:
  - Event Logs presenter extraction
  - cross-screen status presenter extraction
  - snapshot summary presenter extraction
  - dashboard summary presenter extraction
  - presenter-focused regression coverage
- Current branch extends that read-only presentation cleanup with:
  - restore impact summary state extraction into a dedicated helper ahead of the existing restore impact summary presenter
  - presenter-focused regression coverage for the restore-impact summary state seam
- Live authenticated admin smoke validation has now been run successfully against a real wp-admin session for:
  - Sentinel Dashboard
  - Update Readiness
  - Event Logs
  - WordPress Dashboard widget
- Test bootstrap now includes minimal WordPress stubs required by read-only admin/reporting logic:
  - `wp_salt()`
  - `wp_upload_dir()`
  - `trailingslashit()`

## Important Safety Characteristics
- Most destructive operations are guarded by nonce + capability + explicit operator confirmation
- Settings export/import only touches `znts_settings` and explicitly excludes runtime restore state
- Live restore is blocked if baseline/stage/plan gates are incomplete or stale
- Resume uses persisted journal and checkpoint state instead of guessing
- Checkpoints are fingerprint-bound to package artifacts
- Rollback is bounded to execution-specific backup context
- Non-destructive refresh flow exists so operators do not need to force execution to refresh stale gates

## Important Constraints
- This is not transactional restore
- There is no cross-item atomic filesystem swap
- A failed live restore can still leave the system in a partial state, though backup + rollback + journaling reduce the blast radius
- Core restore is not implemented
- The package system is plugin/theme focused
- Full browser automation still does not exist, but the live authenticated admin smoke helper has passed against a real local wp-admin session
- Local CLI linting is clean, but PHP CLI emits an unrelated machine-level `pdo_snowflake` startup warning

## Storage and Persistence

### Tables
- `znts_logs`
- `znts_conflicts`
- `znts_snapshots`
- `znts_snapshot_artifacts`

### Important Options
- `znts_last_preflight`
- `znts_last_update_plan`
- `znts_last_restore_check`
- `znts_last_restore_dry_run`
- `znts_last_restore_stage`
- `znts_restore_stage_checkpoint`
- `znts_last_restore_plan`
- `znts_restore_plan_checkpoint`
- `znts_last_restore_execution`
- `znts_restore_execution_checkpoint`
- `znts_last_restore_rollback`
- `znts_last_snapshot_health_baseline`
- `znts_last_audit_report_verification`
- `znts_restore_checkpoint_expiry_log`

## Key Files
- `zignites-sentinel.php`
- `includes/class-plugin.php`
- `includes/admin/class-admin.php`
- `includes/admin/class-audit-report-verifier.php`
- `includes/admin/class-dashboard-summary-presenter.php`
- `includes/admin/class-event-log-presenter.php`
- `includes/admin/class-health-comparison-presenter.php`
- `includes/admin/class-restore-checkpoint-presenter.php`
- `includes/admin/class-restore-impact-summary-presenter.php`
- `includes/admin/class-snapshot-audit-report-presenter.php`
- `includes/admin/class-restore-operator-checklist-evaluator.php`
- `includes/admin/class-settings-portability.php`
- `includes/admin/class-snapshot-summary-presenter.php`
- `includes/admin/class-status-presenter.php`
- `includes/admin/views/dashboard.php`
- `includes/admin/views/update-readiness.php`
- `includes/admin/views/event-logs.php`
- `includes/logging/class-log-repository.php`
- `includes/snapshots/class-snapshot-manager.php`
- `includes/snapshots/class-snapshot-artifact-repository.php`
- `includes/snapshots/class-snapshot-export-manager.php`
- `includes/snapshots/class-snapshot-package-manager.php`
- `includes/snapshots/class-snapshot-artifact-inspector.php`
- `includes/snapshots/class-restore-readiness-checker.php`
- `includes/snapshots/class-restore-dry-run-checker.php`
- `includes/snapshots/class-restore-staging-manager.php`
- `includes/snapshots/class-restore-execution-planner.php`
- `includes/snapshots/class-restore-executor.php`
- `includes/snapshots/class-restore-rollback-manager.php`
- `includes/snapshots/class-restore-health-verifier.php`
- `includes/snapshots/class-restore-journal-recorder.php`
- `includes/snapshots/class-restore-checkpoint-store.php`
- `tests/bootstrap.php`
- `tests/run.php`
- `tests/test-dashboard-summary-presenter.php`
- `tests/test-event-log-presenter.php`
- `tests/test-health-comparison-presenter.php`
- `tests/test-restore-checkpoint-presenter.php`
- `tests/test-restore-impact-summary-presenter.php`
- `tests/test-snapshot-audit-report-presenter.php`
- `tests/test-snapshot-audit-report.php`
- `tests/test-snapshot-summary-presenter.php`
- `tests/test-status-presenter.php`

## What I Would Do Next

### Immediate Next Steps
1. Continue the read-only presentation extraction work now that manual/admin validation is current
- Likely candidates:
  - remaining Update Readiness formatter helpers outside snapshot-list state, snapshot-summary state, dashboard-summary state, and checkpoint payloads
  - compact dashboard/update-readiness presenter seams still embedded in `includes/admin/class-admin.php`, especially the operator-checklist/readiness summary assembly paths and remaining dashboard/readiness state normalization
- Reason: the manual/admin pass and live smoke/export checks are current, so the next highest-value work is reducing presentation logic still concentrated in the admin controller

2. Keep the manual/admin validation current after each read-only extraction
- Re-run the authenticated smoke helper and the targeted manual path for:
  - Dashboard
  - Update Readiness
  - Event Logs
  - WordPress dashboard widget when touched
- Reason: the remaining work is mostly presentation cleanup, so regression discipline matters more than adding new restore behavior

### Product Maturity Next Steps
3. Add broader reporting/test coverage for any newly extracted presentation helper
- Likely seams:
  - future update-readiness formatter helpers
  - remaining dashboard/readiness presenter helpers after restore-impact summary state extraction

4. Consider a compact printable operator handoff report later
- Only after the current reporting surfaces are better covered by tests
- Reason: operator/client handoff is already improving, but another export surface should not outpace regression coverage

## What I Would Not Do Next
- I would not expand core restore next without stronger transactional guarantees
- I would not widen destructive behavior until more automated validation exists
- I would not add background async workers yet unless resume/state handling is tightened further

## Recommended Restart Point
- Start from `includes/admin/class-admin.php`
- The admin class is now the main orchestration layer for:
  - restore gate control
  - checkpoint logic
  - health baseline/audit flow
  - dashboard summary data
  - Update Readiness view state
- For current test work, also start from:
  - `tests/run.php`
  - `tests/bootstrap.php`
- `includes/admin/class-dashboard-summary-presenter.php`
- `includes/admin/class-dashboard-summary-state-builder.php`
  - `includes/admin/class-event-log-presenter.php`
  - `includes/admin/class-health-comparison-presenter.php`
  - `includes/admin/class-restore-checkpoint-presenter.php`
  - `includes/admin/class-restore-impact-summary-presenter.php`
- `includes/admin/class-snapshot-audit-report-presenter.php`
- `includes/admin/class-snapshot-list-state-builder.php`
- `includes/admin/class-snapshot-summary-state-builder.php`
  - `includes/admin/class-status-presenter.php`
  - `includes/admin/class-snapshot-summary-presenter.php`
  - `tests/test-health-comparison.php`
  - `tests/test-health-comparison-presenter.php`
  - `tests/class-admin-smoke-runner.php`
  - `tests/smoke-admin-live.php`
- `tests/test-dashboard-summary-presenter.php`
- `tests/test-dashboard-summary-state-builder.php`
  - `tests/test-event-log-presenter.php`
  - `tests/test-restore-checkpoint-presenter.php`
  - `tests/test-restore-impact-summary-presenter.php`
- `tests/test-snapshot-audit-report-presenter.php`
- `tests/test-snapshot-audit-report.php`
- `tests/test-snapshot-list-state-builder.php`
- `tests/test-snapshot-summary-state-builder.php`
- `tests/test-snapshot-summary-presenter.php`
  - `tests/test-snapshot-summary-export.php`
  - `tests/test-status-presenter.php`
  - the latest focused test file for the seam being covered

## Handoff Note
- If work resumes later, treat the current product as a safety-first restore control panel with real restore/rollback capability, not just an advisory plugin
- The next work should emphasize operator clarity, regression resistance, and validation depth more than new destructive features
- Current branch prepared for merge:
  - `feature/restore-impact-summary-state-builder-extraction`
- Next likely restart task after this branch merges:
  - extract the next update-readiness/dashboard read-only formatter/presenter seam from `includes/admin/class-admin.php`, likely the operator-checklist/readiness helper path or remaining dashboard/readiness state normalization



