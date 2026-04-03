# Zignites Sentinel Manual Smoke Checklist

Branch scope: current operator-safety phase

Purpose: quick admin verification for the operator-clarity phase before merge.

## Preconditions

- WordPress admin is accessible.
- The plugin is active.
- At least one snapshot exists.
- Ideally test with:
  - one latest snapshot with a baseline
  - one snapshot with a rollback package
  - one snapshot with missing or stale checkpoints

## Dashboard

1. Open `Sentinel` in wp-admin.
2. Confirm the `Site Status` card renders without PHP warnings or broken layout.
3. Confirm the card shows one of:
   - `Stable`
   - `Needs Attention`
   - `At Risk`
4. Confirm `Recommended action` is present and readable.
5. Confirm the card links open correctly:
   - `Open Update Readiness`
   - `Open Snapshot Activity`
6. If a latest snapshot exists, confirm both quick actions render:
   - `Capture Baseline`
   - `Export Audit`

## Dashboard Snapshot List

1. In `Recent Snapshots`, confirm each row still links to the snapshot detail screen.
2. Confirm the `Readiness` column renders compact badges.
3. Confirm badge wording is understandable:
   - `Baseline present` or `Baseline missing`
   - `Package saved` or `No package`
   - `Stage fresh`, `Stage stale`, or `Stage missing`
   - `Plan fresh`, `Plan stale`, or `Plan missing`
   - `Restore ready` or `Restore blocked`
4. Confirm the `Event Logs` link still opens snapshot-scoped logs.

## Update Readiness Snapshot List

1. Open `Sentinel > Update Readiness`.
2. Confirm the `Recent Snapshot Metadata` section loads without layout issues.
3. Confirm the snapshot filter form shows:
   - label search
   - status filter
   - filter submit
   - clear action when filters are active
4. Confirm the `Status guide` is visible and readable.

## Snapshot Filters

Run each filter individually and verify results are plausible:

1. `Baseline present`
2. `Stage fresh`
3. `Plan fresh`
4. `Rollback package saved`
5. `Recent restore activity`
6. `Restore ready`
7. `Stage or plan stale`
8. `Stage or plan missing`

For each filter:

1. Confirm the list updates.
2. Confirm pagination links preserve the active filter.
3. Confirm the empty-state message is clear when nothing matches.

## Combined Filters

1. Use label search with a status filter together.
2. Confirm the result set narrows correctly.
3. Click `Clear`.
4. Confirm both label and status filters reset.

## Selected Snapshot Detail

1. Open a snapshot from the filtered list.
2. Confirm the selected snapshot detail still renders.
3. Confirm the same centralized readiness badges appear for the selected snapshot.
4. Confirm existing operator actions still render normally:
   - baseline capture
   - audit export
   - checklist refresh
   - non-destructive validation actions

## Restore Impact Summary

1. Open a snapshot that has a restore plan.
2. Confirm the `Restore Impact Summary` appears before live restore execution.
3. Confirm it shows:
   - create/replace/unchanged counts
   - conflict count
   - backup storage summary
   - baseline status
   - stage gate summary
   - restore plan summary
   - confirmation phrase
4. If the checklist is blocked, confirm `Execution blockers` is shown and readable.
5. If a resumable execution exists, confirm the impact summary mentions the resume state.

## Health Verification

1. Open a snapshot that already has baseline, post-restore, or post-rollback health data.
2. Confirm health verification still reports the existing probes:
   - front-end
   - login
   - REST API
3. Confirm health verification now also reports an authenticated `Admin` probe.
4. Confirm the admin probe does not report a login form when the current admin session is valid.
5. Confirm the health status and summary counts still render without layout regressions.

## Pagination

1. If enough snapshots exist, move between pages.
2. Confirm:
   - current page changes correctly
   - label search is preserved
   - status filter is preserved
   - selected snapshot context is not broken

## Event Logs Links

1. Open snapshot-scoped Event Logs from:
   - Dashboard
   - Update Readiness activity links
2. Confirm the log screen still respects snapshot scoping.
3. Confirm the logs page loads without malformed query behavior.

## Event Log Export

1. Apply one or more Event Log filters.
2. Click `Export Filtered CSV`.
3. Confirm the downloaded filename reflects the filtered scope.
4. Confirm the CSV contains:
   - log metadata columns
   - snapshot ID when present
   - run ID when present
   - journal scope/phase/status for journal rows
   - JSON context
5. Confirm the export still works when a run journal filter is active.

## Safety Regression Checks

1. Confirm no destructive action is auto-triggered by loading admin screens.
2. Confirm restore execution buttons remain gated behind the existing checklist.
3. Confirm this phase does not loosen confirmation phrase requirements.

## Local Developer Checks

Run locally before merge:

```powershell
php tests/run.php
php -l zignites-sentinel.php
php -l includes/admin/class-admin.php
php -l includes/admin/class-snapshot-status-resolver.php
php -l includes/snapshots/class-snapshot-repository.php
php -l includes/admin/views/dashboard.php
php -l includes/admin/views/update-readiness.php
```

Known local issue:

- PHP CLI may still emit the unrelated `pdo_snowflake` startup warning.

## Merge Gate

This branch is ready to merge when:

1. The dashboard `Site Status` card is readable and accurate enough for operator use.
2. Snapshot badges remain compact and understandable.
3. Snapshot filters and pagination behave consistently together.
4. The local test harness passes.
5. No restore, rollback, or audit flows regress.
