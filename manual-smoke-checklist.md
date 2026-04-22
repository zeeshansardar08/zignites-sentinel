# Zignites Sentinel Manual Smoke Checklist

Branch scope: narrowed rollback-checkpoint product

Purpose: verify the current Dashboard, Before Update, History, and widget experience before merge or release.

## Preconditions

- WordPress admin is accessible.
- The plugin is active.
- Ideally test with:
  - no checkpoints yet
  - one fresh checkpoint
  - one checkpoint that is missing validation
  - one checkpoint with restore or rollback history

## Dashboard

1. Open `Sentinel` in wp-admin.
2. Confirm the hero explains the product in plain language.
3. On a first-run site, confirm the hero shows both:
   - `Create Your First Checkpoint`
   - `Open History`
4. Confirm the dashboard sections render in the current simplified order:
   - `Start Here`
   - `What Sentinel is designed to do`
   - `Latest Checkpoint`
   - `Recent History`
5. Confirm both flow notes are readable:
   - `Restore boundary`
   - `Best fit`
6. Confirm empty-state cards include clear next links for:
   - latest checkpoint
   - recent history
   - saved checkpoints
7. Confirm the `Start Here` guidance cards render cleanly.
8. Confirm the product-positioning note clearly says Sentinel is a rollback checkpoint tool.
9. Confirm the primary action still points to `Before Update`.
10. Confirm `Open History` still works when recent activity exists.
11. At narrower widths, confirm the hero, guidance cards, and tables remain readable.

## WordPress Dashboard Widget

1. Open `/wp-admin/index.php`.
2. Confirm the `Sentinel` widget renders without layout breakage.
3. Confirm it shows:
   - site status
   - next step
   - latest snapshot state when available
4. Confirm widget links open correctly:
   - `Open Before Update`
   - `Open History`

## Before Update First Run

1. Open `Sentinel > Before Update` on a site with no prior Sentinel activity.
2. Confirm the hero clearly explains the checkpoint workflow.
3. Confirm the `Create a checkpoint before you update.` first-run card appears.
4. Confirm the first-run notice now surfaces:
   - `Next Step`
   - `Best First Use`
5. Confirm the `How Sentinel Works` guidance cards render.
6. Confirm the product-positioning note is visible and accurate.
7. Confirm the `Adoption Guide` cards explain missing validation and missing restore history.
8. Confirm the `Create Checkpoint` action is the most visually prominent control.
9. Confirm the create-checkpoint card includes the `Recommended moment` guidance note.

## Before Update With Saved Checkpoint

1. Open `Sentinel > Before Update` with a saved checkpoint selected.
2. Confirm the hero shows:
   - selected checkpoint label
   - workspace status pill
   - next step
3. Confirm `Saved Checkpoints` still links into the selected checkpoint workspace.
4. Confirm `Validate Checkpoint` explains that the checks should be run in order.
5. Confirm each validation action still renders:
   - `Check Restore Readiness`
   - `Validate Checkpoint Package`
   - `Run Staged Validation`
   - `Build Restore Plan`
6. Confirm status bullets under validation remain readable when some checks are missing.

## Restore And Rollback Controls

1. For a checkpoint that is not ready, confirm restore remains blocked with a clear message.
2. For a checkpoint that is ready, confirm the restore description says it is not a full-site restore.
3. Confirm the confirmation phrase input still renders for restore.
4. If a resumable restore exists, confirm `Resume Restore` still appears.
5. After a restore result exists, confirm the rollback section appears with its new explanatory copy.
6. If a resumable rollback exists, confirm `Resume Rollback` still appears.

## History

1. Open `Sentinel > History`.
2. Confirm the screen reads like an activity review surface, not a generic debug page.
3. Confirm filtering still works for:
   - severity
   - source
   - run ID
   - checkpoint ID
   - search
4. Confirm the toolbar now clearly separates:
   - filter controls
   - export panel
5. Confirm the `Export CSV` action is visible and exports the current filtered view.
6. Confirm empty-state text is readable when filters return no rows and offers `Reset Filters`.
7. Confirm event detail still opens from a row click.
8. Confirm pagination still works when enough rows exist.

## Native Update Surfaces

1. Open `/wp-admin/plugins.php` during a window where plugin updates are pending.
2. Confirm Sentinel surfaces the current pre-update cue cleanly:
   - `Create Fresh Checkpoint` when the latest checkpoint is stable
   - `Review Before Update` when Sentinel needs attention first
3. Confirm any row-level Sentinel link keeps you on the originating update screen after checkpoint capture.
4. Open `/wp-admin/themes.php` when theme updates are pending and confirm the same row-level cue behavior.
5. Open `/wp-admin/update-core.php` and confirm Sentinel stays honest about core recovery boundaries.
6. If plugin or theme updates are also pending on the core updates screen, confirm the Sentinel notice still points back into `Before Update` or fresh checkpoint capture as appropriate.

## Network Update Surfaces

Run this section only on multisite installs where network admin is enabled.

1. In multisite, open `/wp-admin/network/plugins.php` during a window where network-visible plugin updates are pending.
2. Confirm Sentinel cues still render without layout breakage and preserve the network update surface on return after checkpoint capture.
3. Open `/wp-admin/network/themes.php` and confirm the same row-level and notice behavior for network theme updates.
4. Open `/wp-admin/network/update-core.php` and confirm Sentinel keeps the core recovery boundary explicit on the network updates screen as well.

## Product Boundaries

1. Confirm the Dashboard and Before Update screens both state the restore boundary accurately.
2. Confirm the product never claims database, media, or core recovery.
3. Confirm the product still feels designed for developers, agencies, and technical maintainers.
4. Confirm the product does not present itself as a full backup replacement.

## Local Developer Checks

Run locally before merge:

```powershell
php tests/run.php
php -l zignites-sentinel.php
php -l includes/admin/class-admin.php
php -l includes/admin/views/dashboard-v1.php
php -l includes/admin/views/before-update.php
php -l includes/admin/views/history.php
```

Optional live wp-admin smoke helper:

```powershell
php tests/smoke-admin-live.php --base-url=http://example.test/wp-admin/ --cookie="wordpress_logged_in_example=...; wordpress_sec_example=..."
```

Local config alternative:

```powershell
Copy-Item tests/admin-smoke-config.sample.php tests/admin-smoke-config.php
php tests/smoke-admin-live.php
```

Local WordPress auth alternative:

```powershell
php tests/smoke-admin-live.php --base-url=http://zee-dev.test/wp-admin/ --local-user=1
```

Optional native update-surface smoke helper:

```powershell
php tests/smoke-admin-live.php --config=tests/admin-smoke-update-surfaces.sample.php
```

Optional live Event Logs export verifier:

```powershell
php tests/export-event-logs-live.php --base-url=http://zee-dev.test/wp-admin/ --local-user=1 --path="admin.php?page=zignites-sentinel-event-logs"
```

Notes:

- Use a real authenticated admin browser cookie header.
- The page smoke helper is read-only and only performs GET requests.
- A sample config is available at `tests/admin-smoke-config.sample.php`.
- The default smoke helper now auto-loads `tests/admin-smoke-config.php` or `tests/admin-smoke-config.local.php` when present.
- A separate sample config for native WordPress update surfaces is available at `tests/admin-smoke-update-surfaces.sample.php`.
- Optional local update-surface config names are `tests/admin-smoke-update-surfaces.php` and `tests/admin-smoke-update-surfaces.local.php`.
- The export verifier can also auto-load `tests/event-log-export-config.php` or `tests/event-log-export-config.local.php`.
- Local auth is also available through `--local-user` plus optional `--wp-root`, or config/env equivalents when the repo sits inside a local WordPress install.
- Environment overrides are available for `ZNTS_SMOKE_BASE_URL`, `ZNTS_SMOKE_COOKIE_HEADER`, `ZNTS_SMOKE_LOCAL_USER`, `ZNTS_SMOKE_WORDPRESS_ROOT`, `ZNTS_SMOKE_TIMEOUT`, `ZNTS_EVENT_LOG_EXPORT_PATH`, and `ZNTS_EVENT_LOG_EXPORT_TIMEOUT`.
- That update-surface config also includes explicit network-admin checks for multisite verification.
- The current smoke helper targets the simplified `Dashboard`, `Before Update`, `History`, selected-checkpoint, and widget surfaces.
- PHP CLI may still emit the unrelated `pdo_snowflake` startup warning.

## Merge Gate

This branch is ready when:

1. The product promise is clear on Dashboard and Before Update.
2. The narrow scope stays explicit and honest.
3. The primary workflow remains easy to follow for technical operators.
4. The current local test harness passes.
5. No restore or rollback controls regress.
