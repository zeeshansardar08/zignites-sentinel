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

= What Sentinel Is Not =

* Not a full backup plugin
* Not a disaster recovery system
* Not an off-site backup service
* Not an atomic restore engine

== Installation ==

1. Upload the plugin to `/wp-content/plugins/` or install it through the WordPress admin.
2. Activate **Zignites Sentinel**.
3. Open **Sentinel > Before Update**.
4. Create a checkpoint before plugin or theme updates.

== Frequently Asked Questions ==

= What should I do first? =

Create a checkpoint before risky plugin or theme updates. Then run the built-in validation steps so you know whether that checkpoint is usable for restore.

= What does Sentinel restore? =

Sentinel restores the active theme and active plugins captured in a checkpoint.

= Does Sentinel restore the database or media library? =

No. Sentinel does not restore the database, uploads/media, or WordPress core.

= Is Sentinel a full backup plugin? =

No. Use a full backup solution for complete site recovery.

== Screenshots ==

1. Dashboard showing the latest checkpoint and the next step.
2. Before Update showing checkpoint creation and validation.
3. Before Update showing restore and rollback actions for a selected checkpoint.
4. History showing recent checkpoint, restore, and rollback events.

== Changelog ==

= 1.32.0 =

* Narrowed the product around pre-update rollback checkpoints for the active theme and active plugins.
* Simplified the admin UI to Dashboard, Before Update, and History.
* Added artifact directory guards for stored packages, exports, stage files, and restore backups under uploads.

== Upgrade Notice ==

= 1.32.0 =

Sentinel now positions itself as a narrower rollback checkpoint tool for plugin and theme updates.
