# Zignites Sentinel 1.33.0 Release Summary Draft

## User-Facing Changes

- Added WooCommerce guardrails that detect active WooCommerce stores and warn about update risks outside Sentinel rollback coverage.
- Added WooCommerce Safe Update Mode acknowledgements for maintenance windows, active cart/order review where detectable, and external database backup confirmation.
- Added WooCommerce-specific report lines so operator/client handoff notes clearly state that Sentinel does not roll back orders, payments, carts, database migrations, scheduled actions, or schema changes.
- Extended Dashboard, Before Update, and native update-screen guidance so WooCommerce stores get stronger boundary messaging before plugin/theme updates.

## Platform and Reporting Foundations

- Added a read-only site status payload model for future agency dashboard consumers without adding a SaaS dependency.
- Added a disabled-by-default outbound sync boundary that normalizes future settings but never sends by default.
- Added a structured, white-label-ready agency report model and plain-text rendering path for client handoff.
- Added deterministic Phase 9 summary models for failure summaries, update risk summaries, and client-friendly incident summaries.
- Kept AI assistance explicitly optional and non-authoritative; all new summaries include deterministic fallback payloads and avoid autonomous update or rollback behavior.

## Verification To Run Before Tagging

```powershell
php tests/run.php
php -l zignites-sentinel.php
php -l includes/admin/class-admin.php
php -l includes/admin/views/dashboard-v1.php
php -l includes/admin/views/before-update.php
php -l includes/admin/views/history.php
```

Recommended live checks:

```powershell
php tests/seed-admin-smoke.php --base-url=http://zee-dev.test/wp-admin/ --local-user=1
php tests/smoke-admin-live.php --base-url=http://zee-dev.test/wp-admin/ --local-user=1
php tests/export-event-logs-live.php --base-url=http://zee-dev.test/wp-admin/ --local-user=1 --path="admin.php?page=zignites-sentinel-event-logs"
php tests/smoke-admin-live.php --config=tests/admin-smoke-update-surfaces.sample.php
```

Package checks:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\build-release.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\verify-release-package.ps1 -BaseUrl http://zee-dev.test/wp-admin/ -LocalUser 1
```

## Verification Run

- `php tests/run.php`: passed on `main` after Phase 9 merge, including platform status, agency report, failure summary, update risk, and incident summary coverage.
- `php tests/seed-admin-smoke.php --base-url=http://zee-dev.test/wp-admin/ --local-user=1`: seeded checkpoint `1` for local smoke coverage.
- `php tests/smoke-admin-live.php --base-url=http://zee-dev.test/wp-admin/ --local-user=1`: passed with `Summary: 10 passed, 0 skipped, 0 failed.`
- `php tests/export-event-logs-live.php --base-url=http://zee-dev.test/wp-admin/ --local-user=1 --path="admin.php?page=zignites-sentinel-event-logs"`: passed with 176 export rows.
- `php tests/smoke-admin-live.php --config=tests/admin-smoke-update-surfaces.sample.php --base-url=http://zee-dev.test/wp-admin/ --local-user=1`: passed with `Summary: 3 passed, 3 skipped, 0 failed.`
- `powershell -ExecutionPolicy Bypass -File .\scripts\verify-release-package.ps1 -BaseUrl http://zee-dev.test/wp-admin/ -LocalUser 1`: passed, including temporary packaged-plugin activation and embedded admin smoke.
- The seeded live smoke, export, update-surface, and package-install checks above were last confirmed during launch-gate hardening before the Phase 8/9 platform/reporting foundations. Re-run them before tagging if the public release package is being cut from the current `main`.

## Screenshot Asset Check

- No repo-local WordPress.org screenshot, banner, or icon image assets are currently tracked under `assets/`; only runtime admin CSS is present.
- WordPress.org screenshot captions in `readme.txt` are aligned to the current Dashboard, Before Update, WooCommerce guardrail, and History surfaces.
- Final screenshot files still need to be captured or uploaded through the WordPress.org asset workflow before publishing.

## Launch Gate Decision

- Do not treat `1.33.0` as a broad public WordPress.org launch until a current package install verification, one clean seeded live smoke run, and final screenshots are complete.
- The right release posture remains a controlled beta or private agency/developer release first, because the plugin solves a real update-window rollback problem but still needs final install, live-state, and screenshot validation.
- Public launch is appropriate after the beta path confirms package activation, checkpoint/history smoke coverage, final screenshot assets, and no misleading restore expectations remain in the UI or listing copy.

## Known Non-Blocking Notes

- The release remains intentionally narrow: active plugin/theme code checkpoints only, not database, uploads/media, WordPress core, WooCommerce order/payment rollback, malware cleanup, or off-site backup.
- Local PHP CLI may emit the unrelated `pdo_snowflake` startup warning noted in the smoke checklist; treat it as non-blocking only if the actual tests and lint commands pass.
