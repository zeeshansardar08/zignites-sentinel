# Zignites Sentinel Progress Audit

## Current State
- Plugin version: `1.16.0`
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

### 12. Event Logs and Activity Views
- Event Logs screen supports:
  - severity filter
  - source filter
  - run ID filter
  - snapshot ID filter
  - text search
  - pagination
- Run journal view exists
- Run summaries exist
- Snapshot activity timeline exists on Update Readiness
- Dashboard now deep-links into snapshot-scoped Event Logs

### 13. Dashboard Operator UX
- Restore Readiness summary for latest snapshot
- Latest Snapshot Health strip
- Latest snapshot quick actions:
  - Capture Baseline
  - Export Audit
- Countdown/expiry messaging for stage and plan checkpoints

### 14. Update Readiness Operator UX
- Snapshot detail view
- Artifact detail and diff
- Health baseline section
- Operator checklist section
- Audit verification section
- Restore control summary
- Snapshot list label filter

## Important Safety Characteristics
- Most destructive operations are guarded by nonce + capability + explicit operator confirmation
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
- No browser-level validation was run during recent development
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

## What I Would Do Next

### Immediate Next Steps
1. Add snapshot status filters in Update Readiness
- Filter snapshots by operational state, not just label
- Examples:
  - has fresh stage checkpoint
  - has fresh plan checkpoint
  - has baseline
  - has recent restore activity
- Reason: once the snapshot list grows, label-only filtering is not enough

2. Add a compact dashboard widget variant
- Reuse the existing restore summary and health strip logic
- Keep it read-only
- Reason: make the product useful without opening the full plugin screen

3. Add per-snapshot readiness badges in the snapshot list
- Show at-a-glance markers for:
  - baseline exists
  - stage fresh
  - plan fresh
  - live restore offerable
- Reason: reduce clicks and operator ambiguity

### Safety-Focused Next Steps
4. Add stronger post-restore validation
- Extend beyond front-end/login/REST viability
- Check admin page load or a lightweight authenticated admin endpoint
- Reason: current health verification is good but still not full application validation

5. Add restore execution item checkpoints beyond current phase reuse
- Persist finer-grained file/item completion state
- Reason: improve resume reliability for large restores

6. Add explicit pre-execution impact summary
- Show overwrite counts, new items, backup location, and health baseline summary in one final confirmation panel
- Reason: reduce operator error before destructive execution

### Product Maturity Next Steps
7. Add export/import of configuration and non-destructive preferences
- Keep restore execution state out of config export
- Reason: operational portability

8. Add reporting polish
- downloadable CSV or HTML summary for logs/run journals
- snapshot summary print/export view
- Reason: agency/client handoff use case

9. Add automated tests
- Highest-value targets:
  - checkpoint freshness logic
  - audit report verification
  - snapshot filtering
  - restore gate computation
  - journal resume logic
- Reason: the feature surface is now large enough that regression risk is real

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

## Handoff Note
- If work resumes later, treat the current product as a safety-first restore control panel with real restore/rollback capability, not just an advisory plugin
- The next work should emphasize operator clarity, regression resistance, and validation depth more than new destructive features
