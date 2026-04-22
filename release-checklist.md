# Zignites Sentinel Release Checklist

Purpose: provide one repeatable release-prep path for the narrowed rollback-checkpoint product.

Use this checklist before tagging or publishing a new plugin version.

## 1. Branch and Scope Check

- Confirm the intended release branch is fully merged into `main`.
- Confirm `main` is pushed to `origin/main`.
- Confirm the working tree is clean before packaging.
- Confirm there is no unfinished release-facing copy, screenshot, or smoke work sitting on a side branch.

## 2. Version Sync

- Update the plugin version in [zignites-sentinel.php](/D:/laragon/www/zee-dev/wp-content/plugins/zignites-sentinel/zignites-sentinel.php).
- Update `ZNTS_VERSION` in [zignites-sentinel.php](/D:/laragon/www/zee-dev/wp-content/plugins/zignites-sentinel/zignites-sentinel.php).
- Update `Stable tag` in [readme.txt](/D:/laragon/www/zee-dev/wp-content/plugins/zignites-sentinel/readme.txt).
- Add or update the matching `Changelog` entry in [readme.txt](/D:/laragon/www/zee-dev/wp-content/plugins/zignites-sentinel/readme.txt).
- Add or update the matching `Upgrade Notice` entry in [readme.txt](/D:/laragon/www/zee-dev/wp-content/plugins/zignites-sentinel/readme.txt).
- If the database schema changed, update `ZNTS_DB_VERSION` and confirm the installer/upgrade path is still correct.

## 3. Product Promise Check

- Confirm the public and in-product wording still agree on what Sentinel restores:
  - active plugins
  - active theme
- Confirm the public and in-product wording still agree on what Sentinel does not restore:
  - database
  - uploads/media
  - WordPress core
- Confirm no copy implies atomic restore behavior, full-site disaster recovery, or off-site backup coverage.
- Confirm `readme.txt`, `README.md`, and the admin screens all still describe Sentinel as a narrow rollback-checkpoint workflow for technical operators.

## 4. Automated Verification

Run locally:

```powershell
php tests/run.php
php -l zignites-sentinel.php
php -l includes/admin/class-admin.php
php -l includes/admin/views/dashboard-v1.php
php -l includes/admin/views/before-update.php
php -l includes/admin/views/history.php
```

Confirm:

- the focused test suite passes
- touched PHP files pass syntax checks
- any recurring CLI noise is understood and unrelated before release notes are written

## 5. Live Admin Verification

Run the authenticated Sentinel admin smoke helper:

```powershell
php tests/smoke-admin-live.php --base-url=http://zee-dev.test/wp-admin/ --local-user=1
```

Confirm:

- `Dashboard` passes
- `Dashboard First Run` passes
- `Before Update` passes
- `Selected Snapshot Detail` passes
- `History` passes
- `History Empty State` passes
- `Event Log Detail` passes
- `WordPress Dashboard Widget` passes
- the final smoke summary reports zero failures

Run the History export verification:

```powershell
php tests/export-event-logs-live.php --base-url=http://zee-dev.test/wp-admin/ --local-user=1 --path="admin.php?page=zignites-sentinel-event-logs"
```

Confirm:

- the export request succeeds
- the export action points to the expected admin-post route
- the export returns rows for the current filtered view when activity exists

Optional update-surface smoke:

```powershell
php tests/smoke-admin-live.php --config=tests/admin-smoke-update-surfaces.sample.php
```

Confirm:

- single-site update screens pass where pending updates exist
- multisite/network checks only run on environments where `network admin` is available
- any skipped checks are expected and documented

## 6. Manual UI Check

Walk through [manual-smoke-checklist.md](/D:/laragon/www/zee-dev/wp-content/plugins/zignites-sentinel/manual-smoke-checklist.md).

Pay extra attention to:

- Dashboard first-run and empty-state links
- Before Update first-run guidance and create-checkpoint emphasis
- History filter/export layout
- restore boundary honesty
- widget readability
- narrow-width layout behavior

## 7. Screenshot Capture

Capture or recapture the current screenshot set so it matches the live product:

1. Dashboard showing latest checkpoint, next step, and boundary guidance.
2. Before Update first-run checkpoint guidance and checkpoint creation.
3. Before Update selected checkpoint with validation, restore, and rollback controls.
4. History with filtered activity review and the current-view export panel.

Before accepting screenshots:

- confirm the copy matches [readme.txt](/D:/laragon/www/zee-dev/wp-content/plugins/zignites-sentinel/readme.txt) captions
- confirm no stale headings from older UI phases remain visible
- confirm the screenshots reflect the narrowed three-screen product

## 8. WordPress.org Asset Check

- Confirm screenshot captions in [readme.txt](/D:/laragon/www/zee-dev/wp-content/plugins/zignites-sentinel/readme.txt) match the latest captures.
- Confirm the plugin page description, FAQs, and upgrade notice still match the current product scope.
- Confirm any WordPress.org banner, icon, or screenshot assets to be uploaded are current and not showing stale UI.

## 9. Packaging Check

- Confirm [.distignore](/D:/laragon/www/zee-dev/wp-content/plugins/zignites-sentinel/.distignore) still matches the files that should stay out of the release zip.
- Build the release package with:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\build-release.ps1
```

Default output:

- `build/zignites-sentinel.zip`

- Confirm local smoke config files and local export config files are not included in the release package.
- Confirm gitignored environment-specific files are still excluded.
- Confirm internal repo-only docs such as progress notes and strategy docs are not included in the release package.
- Confirm the `tests/` directory is excluded from the release package unless there is a deliberate reason to ship it.
- Confirm release packaging does not include transient test outputs or local secrets.
- Confirm the generated zip still contains the expected runtime entry points such as `readme.txt`, `zignites-sentinel.php`, `includes/`, and `assets/`.
- Confirm the plugin activates from a clean package on the target WordPress version if a packaging/install dry run is available.

## 10. Release Handoff

- Write a short release summary covering:
  - user-facing changes
  - verification run
  - known non-blocking noise such as the local `pdo_snowflake` CLI warning if it still appears
- Push the final release commit to `main`.
- Tag the release only after the checklist above is complete.
- Keep the release note honest about narrow restore scope and verification expectations.
