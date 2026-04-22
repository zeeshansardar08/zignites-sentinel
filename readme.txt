=== Zignites Sentinel ===
Contributors: zignites
Tags: rollback, restore, checkpoint, plugins, theme
Requires at least: 6.5
Tested up to: 6.8
Requires PHP: 8.0
Stable tag: 1.32.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Create a rollback checkpoint of your active plugins and theme before updates, then restore it if an update breaks the code layer.

== Description ==

Zignites Sentinel is a rollback checkpoint plugin for technical WordPress users.

Use it to:

* create a checkpoint of the active theme and active plugins before updates
* validate that checkpoint before you rely on it
* restore that checkpoint if an update breaks the code layer
* roll back the last restore if needed

= Why Use Sentinel =

* It is built for plugin and theme update risk, not broad disaster recovery
* It gives technical WordPress teams a faster rollback checkpoint workflow than full-site backup tools
* It emphasizes validation before restore, not just checkpoint creation
* It keeps the operator focused on the next safe step

= What Sentinel Restores =

* Active plugins
* Active theme

= What Sentinel Does Not Restore =

* Database
* Uploads or media
* WordPress core

Use a full backup solution for full-site recovery.

= Who It Is For =

* Developers
* Technical site maintainers
* Agencies managing risky plugin and theme updates

= Best Fit Use Cases =

* Preparing for risky plugin or theme updates on client sites
* Keeping a code-layer rollback path before maintenance windows
* Giving technical operators a narrower recovery workflow than a full backup suite

= What Sentinel Is Not =

* Not a full backup plugin
* Not a disaster recovery system
* Not an off-site backup service
* Not an atomic restore engine

= Typical Workflow =

1. Open **Sentinel > Before Update**.
2. Create a checkpoint before plugin or theme updates.
3. Run the validation steps before trusting that checkpoint.
4. Restore it only if the update breaks the active code layer.
5. Review **Sentinel > History** to confirm what happened.

= Restore Workflow Safeguards =

* Restore is meant to be operator-driven, not fire-and-forget
* Sentinel keeps validation steps in front of the restore action so teams can check readiness before touching live code
* Live restore is gated by current validation evidence and an explicit confirmation phrase
* Rollback is available for the last restore when the related backup context still exists

= Artifact and Backup Handling =

* Checkpoint exports, rollback packages, staged validation files, and restore backups are stored under `uploads/zignites-sentinel/`
* Sentinel writes guard files for common Apache and IIS setups to reduce direct web access to those artifacts
* During restore, Sentinel backs up the existing plugin and theme payloads it is about to replace
* Those backups are part of the narrow restore workflow, not a substitute for a full off-site backup strategy

= Artifact Storage Protection =

Sentinel stores checkpoint packages, exports, temporary stage files, and restore backups under a protected `uploads/zignites-sentinel/` directory.

It adds:

* `index.php` guards
* `.htaccess` deny rules for Apache hosts
* `web.config` deny rules for IIS hosts

This reduces direct web access and directory listing on common hosting setups, but it is not a perfect guarantee on every stack.

If your host serves uploads directly without honoring `.htaccess` or `web.config`, sensitive artifact files may still be reachable by URL. For stronger protection, use server-level deny rules or a hosting setup that does not expose these uploads paths publicly.

== Installation ==

1. Upload the plugin to `/wp-content/plugins/` or install it through the WordPress admin.
2. Activate **Zignites Sentinel**.
3. Open **Sentinel > Before Update**.
4. Create a checkpoint before plugin or theme updates.

== Frequently Asked Questions ==

= What should I do first? =

Create a checkpoint before risky plugin or theme updates. Then run the built-in validation steps so you know whether that checkpoint is usable for restore.

= When should I create a checkpoint? =

Create it immediately before the plugin or theme update window you want covered. A checkpoint created much earlier may no longer represent the live code state you are about to change.

= Do I need to run validation before restore? =

Yes. Sentinel is designed around validation before restore so you can confirm the checkpoint package, staging flow, and restore plan are still current before writing back to live plugin or theme paths.

= Is Sentinel a replacement for my backup plugin? =

No. Sentinel is a narrow rollback checkpoint tool for active plugins and the active theme. Use a full backup solution for database, media, and full-site recovery.

= What does Sentinel restore? =

Sentinel restores the active theme and active plugins captured in a checkpoint.

= Does Sentinel restore the database or media library? =

No. Sentinel does not restore the database, uploads/media, or WordPress core.

= Is Sentinel a full backup plugin? =

No. Use a full backup solution for complete site recovery.

= Where does Sentinel store checkpoint packages and restore backups? =

Sentinel stores them under `wp-content/uploads/zignites-sentinel/`. It also adds common guard files such as `index.php`, `.htaccess`, and `web.config` where relevant, but you should still treat those artifacts as sensitive operational files.

= What should I review after a restore or rollback? =

Open **Sentinel > History** to review the recorded events, then confirm the site behaves as expected. Sentinel helps with the code-layer rollback workflow, but it does not replace normal post-change verification.

== Screenshots ==

1. Dashboard showing the latest checkpoint, next step, and restore boundary guidance.
2. Before Update showing first-run checkpoint guidance and checkpoint creation.
3. Before Update showing validation, restore, and rollback actions for a selected checkpoint.
4. History showing filtered activity review and CSV export for the current view.

== Changelog ==

= 1.32.0 =

* Narrowed the product around pre-update rollback checkpoints for the active theme and active plugins.
* Simplified the admin UI to Dashboard, Before Update, and History.
* Added artifact directory guards for stored packages, exports, stage files, and restore backups under uploads.
* Clarified public plugin-page copy around validation flow, artifact handling, and restore boundaries.

== Upgrade Notice ==

= 1.32.0 =

Sentinel now positions itself as a narrower rollback checkpoint tool for plugin and theme updates.
