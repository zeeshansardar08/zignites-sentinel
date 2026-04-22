# Zignites Sentinel

Zignites Sentinel is a rollback checkpoint plugin for technical WordPress users.

It helps you:

- create a checkpoint of the active theme and active plugins before updates
- validate that checkpoint before you rely on it
- restore that checkpoint if an update breaks the code layer
- roll back the last restore if needed

## Why Use Sentinel

- It is built for plugin and theme update risk, not broad disaster recovery.
- It gives developers and maintainers a faster rollback checkpoint workflow than full-site backup tools.
- It emphasizes validation before restore, not just checkpoint creation.
- It keeps the operator focused on the next safe step instead of scattering recovery tasks across WordPress admin.

## What It Is

Sentinel is not a full backup plugin and not a full-site restore system.

It restores:

- the active theme
- active plugins

It does not restore:

- the database
- uploads or media
- WordPress core

Use a full backup solution for full-site recovery.

## Artifact Storage Protection

Sentinel stores checkpoint packages, exports, temporary stage files, and restore backups under `uploads/zignites-sentinel/`.

It writes:

- `index.php` guards
- `.htaccess` deny rules for Apache
- `web.config` deny rules for IIS

That reduces direct access on common hosts, but it is not absolute on every stack. If a host serves uploads directly and ignores those rules, artifact files may still be reachable by URL. Stronger protection requires server-level deny rules or keeping those artifacts outside public uploads.

## Product Scope

The narrowed v1 focuses on three user jobs:

1. Save a rollback checkpoint before risky updates.
2. Validate that checkpoint.
3. Restore or roll back that code-layer checkpoint when needed.

## Best Fit

Sentinel is a strong fit when:

- you manage risky plugin or theme updates regularly
- you need a rollback path for the active code layer
- you already understand that database and media recovery need a different tool
- you want a safety-focused workflow for technical operators

Sentinel is a weak fit when:

- you need full-site disaster recovery
- you expect one-click transactional restore behavior
- your main problem is backup retention, migration, or off-site storage

## Core Workflow

1. Open `Before Update` before changing plugins or themes.
2. Create a checkpoint of the active theme and active plugins.
3. Run the validation steps before trusting that checkpoint.
4. Restore the checkpoint only when an update breaks the code layer.
5. Review `History` to confirm what happened.

## Core Screens

- `Dashboard`: what Sentinel does, the latest checkpoint, and the next step
- `Before Update`: create checkpoints, validate them, restore them, or roll back the last restore
- `History`: recent checkpoint, restore, and rollback events

## Notes

- Sentinel is designed for technical WordPress users, developers, and agencies.
- It does not claim atomic restore behavior.
- This phase intentionally narrows the product instead of adding new features.

## Repo Checklists

- [manual-smoke-checklist.md](/D:/laragon/www/zee-dev/wp-content/plugins/zignites-sentinel/manual-smoke-checklist.md): screen-by-screen UI and workflow verification.
- [release-checklist.md](/D:/laragon/www/zee-dev/wp-content/plugins/zignites-sentinel/release-checklist.md): version sync, smoke, screenshots, packaging, and final release handoff.

## Packaging

- [.distignore](/D:/laragon/www/zee-dev/wp-content/plugins/zignites-sentinel/.distignore) defines repo-only files and folders that should stay out of the release zip.
