# Zignites Sentinel Manual Smoke Checklist

Branch scope: current operator-safety phase

Purpose: quick admin verification for the current read-only operator/reporting phase before merge.

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
7. Confirm the top layout remains stable at narrower wp-admin widths:
   - status hero wraps cleanly
   - quick actions stay clickable
   - no cards collapse into unreadable vertical stacks
8. Confirm the hero shows a clear workflow hint and the next action remains visually dominant over supporting signals.
9. If `More status signals` is present, open it and confirm the disclosure expands cleanly without shifting the hero awkwardly.

## WordPress Dashboard Widget

1. Open the core WordPress dashboard (`/wp-admin/index.php`).
2. Confirm the `Sentinel` widget renders without layout breakage.
3. Confirm it shows:
   - site status
   - recommended action
   - latest snapshot readiness badges
   - health summary pills when available
4. Confirm widget links still open correctly:
   - `Open Update Readiness`
   - snapshot activity link when a latest snapshot exists

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
5. Confirm long badge combinations wrap cleanly without breaking the table row height.

## Update Readiness Snapshot List

1. Open `Sentinel > Update Readiness`.
2. Confirm the `Recent Snapshot Metadata` section loads without layout issues.
3. Confirm the snapshot filter form shows:
   - label search
   - status filter
   - filter submit
   - clear action when filters are active
4. Confirm the top hero clearly shows:
   - selected snapshot
   - current workspace state
   - workflow note
   - confidence note
5. Confirm the summary strip clearly shows:
   - selected snapshot state
   - restore readiness state
   - key next action or blocker context
6. Confirm the `Status guide` remains available behind disclosure and expands cleanly when opened.

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
   - summary export
   - audit export
   - checklist refresh
   - non-destructive validation actions
5. Confirm the execution and rollback checkpoint panels show counts without overflowing or collapsing.
6. Confirm only the top-level snapshot detail panel is open by default.
7. Open and close the secondary snapshot detail disclosures and confirm the labels are readable:
   - `Stored Snapshot Data`
   - `Component Sources At Snapshot Time`
   - `Rollback Package Contents`
   - `Artifact Mismatch Review`
   - `Plugins Active At Snapshot Time`

## Settings Portability

1. Open `Sentinel > Update Readiness`.
2. In `Sentinel Settings`, confirm the `Settings Portability` panel renders below the main settings form.
3. Click `Export Settings`.
4. Confirm a JSON file downloads.
5. Confirm the export includes only Sentinel preference keys and not:
   - snapshots
   - logs
   - checkpoints
   - restore results
   - audit verification state
6. Paste the exported JSON back into `Import settings JSON`.
7. Click `Import Settings`.
8. Confirm the success notice appears and the settings remain unchanged.
9. Try an invalid JSON payload.
10. Confirm the import is rejected cleanly with an error notice.

## Snapshot Summary

1. Open a selected snapshot in `Update Readiness`.
2. Confirm the `Snapshot Summary` card renders before the health baseline section.
3. Confirm it shows:
   - a dominant recommended next step
   - a high-priority current risk
   - summary details
   - supporting evidence/context
4. Click `Download Summary`.
5. Confirm a Markdown file downloads and includes:
   - snapshot metadata
   - overview
   - evidence
   - risks
   - recommended next steps
   - recent activity
6. Confirm the summary card remains readable even when one of the sections is empty.
7. Confirm supporting evidence/context feels visually secondary to the recommended next step and current risk.

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
6. Confirm the summary stays read-only and does not trigger any restore side effect by rendering.

## Health Verification

1. Open a snapshot that already has baseline, post-restore, or post-rollback health data.
2. Confirm health verification still reports the existing probes:
   - front-end
   - login
   - REST API
3. Confirm health verification now also reports an authenticated `Admin` probe.
4. Confirm the admin probe does not report a login form when the current admin session is valid.
5. Confirm the health status and summary counts still render without layout regressions.
6. Confirm health comparison rows render in the expected order:
   - `Baseline`
   - `Post-Restore`
   - `Post-Rollback`
7. Confirm delta text is readable:
   - `No change`
   - or compact delta strings such as `pass +1, warning -1`
8. Confirm the baseline warning surface remains visually stronger than the deeper health comparison details.

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

## Event Logs Screen

1. Open `Sentinel > Event Logs`.
2. Confirm the investigation-style top summary renders cleanly.
3. Confirm the top hero includes a clear workflow hint for filtering, scanning, and expanding logs.
3. Confirm the filter toolbar remains readable with:
   - severity
   - source
   - run ID
   - snapshot ID
   - search
4. Confirm empty states remain readable when filters return no results.
5. Confirm long log messages are previewed first and expand cleanly on demand.
6. Confirm critical and warning rows are more visually distinct than normal rows without making the table noisy.
7. Confirm run summary cards and detail links do not overflow at narrower widths.

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
4. Confirm view-only cards and summaries remain read-only:
   - dashboard widget
   - snapshot summary
   - restore impact summary
   - health comparison blocks

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
php -l includes/admin/views/event-logs.php
```

Optional live wp-admin smoke helper:

```powershell
php tests/smoke-admin-live.php --base-url=http://example.test/wp-admin/ --cookie="wordpress_logged_in_example=...; wordpress_sec_example=..."
```

Notes:

- Use a real authenticated admin browser cookie header.
- The script is read-only and only performs GET requests.
- A sample config is available at `tests/admin-smoke-config.sample.php`.

Known local issue:

- PHP CLI may still emit the unrelated `pdo_snowflake` startup warning.

## Merge Gate

This branch is ready to merge when:

1. The dashboard `Site Status` card is readable and accurate enough for operator use.
2. Snapshot badges remain compact and understandable.
3. Snapshot filters and pagination behave consistently together.
4. Dashboard widget and Event Logs summary layout remain readable.
5. The local test harness passes.
6. No restore, rollback, or audit flows regress.
