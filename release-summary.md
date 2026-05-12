# Zignites Sentinel 1.33.0 Release Summary Draft

## User-Facing Changes

- Added WooCommerce guardrails that detect active WooCommerce stores and warn about update risks outside Sentinel rollback coverage.
- Added WooCommerce Safe Update Mode acknowledgements for maintenance windows, active cart/order review where detectable, and external database backup confirmation.
- Added WooCommerce-specific report lines so operator/client handoff notes clearly state that Sentinel does not roll back orders, payments, carts, database migrations, scheduled actions, or schema changes.
- Extended Dashboard, Before Update, and native update-screen guidance so WooCommerce stores get stronger boundary messaging before plugin/theme updates.

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
php tests/smoke-admin-live.php --base-url=http://zee-dev.test/wp-admin/ --local-user=1
php tests/export-event-logs-live.php --base-url=http://zee-dev.test/wp-admin/ --local-user=1 --path="admin.php?page=zignites-sentinel-event-logs"
php tests/smoke-admin-live.php --config=tests/admin-smoke-update-surfaces.sample.php
```

Package checks:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\build-release.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\verify-release-package.ps1 -BaseUrl http://zee-dev.test/wp-admin/ -LocalUser 1
```

## Screenshot Asset Check

- No repo-local WordPress.org screenshot, banner, or icon image assets are currently tracked under `assets/`; only runtime admin CSS is present.
- WordPress.org screenshot captions in `readme.txt` are aligned to the current Dashboard, Before Update, WooCommerce guardrail, and History surfaces.
- Final screenshot files still need to be captured or uploaded through the WordPress.org asset workflow before publishing.

## Known Non-Blocking Notes

- The release remains intentionally narrow: active plugin/theme code checkpoints only, not database, uploads/media, WordPress core, WooCommerce order/payment rollback, malware cleanup, or off-site backup.
- Local PHP CLI may emit the unrelated `pdo_snowflake` startup warning noted in the smoke checklist; treat it as non-blocking only if the actual tests and lint commands pass.
