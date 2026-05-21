=== Zignites Sentinel - Safe Update Checkpoints & Rollback ===
Contributors: zignites
Tags: rollback, restore, checkpoint, plugins, theme
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 8.0
Stable tag: 1.33.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Safe-update checkpoints and validated code rollback for WordPress plugin and theme updates. Built for developers and agencies.

== Description ==

**Stop fearing the Update button.**

A plugin or theme update can break a live site in seconds. Zignites Sentinel gives you a fast, surgical way to undo it - without restoring an entire full-site backup.

Before you update, Sentinel captures a **checkpoint** of your active plugins and active theme. If the update breaks the code layer, you restore that checkpoint and your site is back. If the restore itself goes wrong, you roll it back.

Sentinel is not another full-backup plugin. It is a focused **update-safety workflow**: checkpoint the code, *prove the checkpoint actually works* before you trust it, then restore in minutes if you need to - with a full audit trail you can hand to a client.

= The 3-step workflow =

1. **Checkpoint** - Capture the active theme and active plugins right before an update window.
2. **Validate** - Run the built-in dry-run and staged validation so you *know* the checkpoint is restorable. Most tools skip this step. Sentinel makes it the point.
3. **Restore** - If the update breaks the site, restore the checkpoint. Restore is operator-driven and gated by current validation evidence plus an explicit confirmation phrase.

= Why developers and agencies use Sentinel =

* **Validation before restore.** A checkpoint you cannot verify is not a safety net. Sentinel keeps validation in front of every restore.
* **Code-layer focus.** It restores exactly what plugin and theme updates change - nothing more - so recovery is fast and predictable.
* **An audit trail for client work.** Every checkpoint, restore, and rollback is logged. Review it in History or export it as CSV for client handoff.
* **Reliability controls built in.** A shared operation lock prevents overlapping heavy operations. Disk-space preflight blocks unsafe runs before they start. Retention cleanup keeps stored artifacts under control.
* **Resumable restores.** Live restore and rollback are journaled per item, so an interrupted operation can be safely resumed instead of restarted.

= What Sentinel restores =

* Active plugins
* Active theme

= What Sentinel does NOT do =

Sentinel is deliberately narrow. It is **not** a replacement for a full backup solution. It does not restore:

* The database
* Uploads or media
* WordPress core
* WooCommerce order, payment, cart, or session state

It is also not a malware scanner, firewall, or cleanup tool. Use a full backup solution for complete site recovery, and a dedicated security tool for infected sites.

= Who it is for =

* WordPress developers
* Agencies maintaining client sites
* Technical site maintainers who run deliberate, batched plugin and theme updates

= WooCommerce guardrails =

When WooCommerce is active, Sentinel surfaces stronger update-window warnings, because store updates can change orders, payments, carts, sessions, scheduled actions, and database schema that live *outside* code-layer rollback coverage.

WooCommerce Safe Update Mode encourages a low-traffic maintenance window, active cart/order review where detectable, and external database backup confirmation before WooCommerce or extension updates. These guardrails reduce false confidence - they do not turn Sentinel into a WooCommerce database or order/payment rollback system.

= Team alerts =

Sentinel can notify your team when key events happen (checkpoint created, restore started, restore failed, rollback completed, health check failed) through generic webhooks, Slack, Microsoft Teams, Discord, or Telegram.

= Artifact storage and protection =

Sentinel stores checkpoint packages, exports, temporary stage files, and restore backups under a protected `wp-content/uploads/zignites-sentinel/` directory. It adds `index.php`, `.htaccess`, and `web.config` guard files to reduce direct web access on common Apache and IIS setups.

This is not a perfect guarantee on every stack. If your host serves uploads directly without honoring `.htaccess` or `web.config` (some Nginx, CDN, or object-storage setups), apply server-level deny rules as well. The Dashboard runs an artifact exposure probe and reports whether the artifact path appears blocked, publicly readable, or inconclusive.

Checkpoint packages and exports can contain plugin/theme source code, configuration files, license keys, or API tokens stored inside the active code layer. Treat generated artifacts and exported logs as sensitive operational data.

== Installation ==

1. Upload the plugin to `/wp-content/plugins/`, or install it through the WordPress admin.
2. Activate **Zignites Sentinel**.
3. Open **Sentinel > Before Update**.
4. Create a checkpoint before your next plugin or theme update.

== Frequently Asked Questions ==

= What should I do first? =

Open **Sentinel > Before Update** and create a checkpoint immediately before a risky plugin or theme update. Then run the built-in validation steps so you know the checkpoint is usable for restore.

= When should I create a checkpoint? =

Right before the update window you want covered. A checkpoint created much earlier may no longer represent the live code you are about to change.

= Do I need to validate before restoring? =

Yes - that is the core idea. Sentinel keeps a dry-run and staged validation in front of restore so you can confirm the checkpoint package and restore plan are still current before writing back to live plugin or theme paths.

= Is Sentinel a replacement for my backup plugin? =

No. Sentinel is a narrow safe-update checkpoint and rollback tool for active plugins and the active theme. Use a full backup solution for the database, media, WooCommerce order/payment state, and full-site recovery.

= Does Sentinel restore the database, media library, or WooCommerce orders? =

No. Sentinel does not restore the database, uploads/media, WordPress core, or WooCommerce order/payment state.

= Does Sentinel detect or clean malware? =

No. Sentinel is not a malware scanner, firewall, or cleanup tool.

= What happens if a restore is interrupted? =

Live restore and rollback are journaled per item. An interrupted operation can be resumed from where it stopped instead of restarted, and Sentinel backs up the existing payload before replacing it.

= Where does Sentinel store checkpoint packages and restore backups? =

Under `wp-content/uploads/zignites-sentinel/`. Sentinel adds `index.php`, `.htaccess`, and `web.config` guard files where relevant, but you should still treat those artifacts as sensitive operational files.

= What should I review after a restore or rollback? =

Open **Sentinel > History** to review the recorded events, then confirm the site behaves as expected. Sentinel covers the code-layer rollback workflow; it does not replace normal post-change verification.

== Screenshots ==

1. Dashboard showing the latest checkpoint, next safe step, restore boundary guidance, and WooCommerce guardrails when relevant.
2. Before Update showing first-run checkpoint guidance and checkpoint creation.
3. Before Update showing validation, WooCommerce Safe Update Mode, restore, and rollback actions for a selected checkpoint.
4. History showing filtered activity review and CSV export for the current view.

== Changelog ==

= 1.33.0 =

* Added WooCommerce guardrails that detect active WooCommerce stores and surface stronger update-window warnings.
* Added WooCommerce Safe Update Mode acknowledgements for maintenance windows, active cart/order review, and external database backup confirmation.
* Added WooCommerce-specific report lines so client handoff notes state the order/payment/database rollback boundary clearly.
* Extended update-screen notices, Dashboard guidance, and Before Update guidance to avoid false confidence on WooCommerce stores.
* Hardened CSV log exports against spreadsheet formula injection.
* Added private/reserved network address validation for outbound alert webhooks.

= 1.32.0 =

* Narrowed the product around Safe Update Checkpoints and Rollback for the active theme and active plugins.
* Simplified the admin UI to Dashboard, Before Update, and History.
* Added artifact directory guards for stored packages, exports, stage files, and restore backups under uploads.
* Added artifact exposure reporting and stronger sensitive-artifact warnings for checkpoint and export workflows.
* Clarified public plugin-page copy around validation flow, artifact handling, and restore boundaries.

== Upgrade Notice ==

= 1.33.0 =

Adds WooCommerce-specific update-window guardrails and hardens CSV exports and outbound webhooks, while keeping the same narrow plugin/theme code rollback boundary.

= 1.32.0 =

Sentinel now positions itself around Safe Update Checkpoints and Rollback for plugin and theme updates.
