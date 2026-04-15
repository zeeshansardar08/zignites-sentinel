# Zignites Sentinel

Zignites Sentinel is a rollback checkpoint plugin for technical WordPress users.

It helps you:

- create a checkpoint of the active theme and active plugins before updates
- validate that checkpoint before you rely on it
- restore that checkpoint if an update breaks the code layer
- roll back the last restore if needed

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

## Product Scope

The narrowed v1 focuses on three user jobs:

1. Save a rollback checkpoint before risky updates.
2. Validate that checkpoint.
3. Restore or roll back that code-layer checkpoint when needed.

## Core Screens

- `Dashboard`: what Sentinel does, the latest checkpoint, and the next step
- `Before Update`: create checkpoints, validate them, restore them, or roll back the last restore
- `History`: recent checkpoint, restore, and rollback events

## Notes

- Sentinel is designed for technical WordPress users, developers, and agencies.
- It does not claim atomic restore behavior.
- This phase intentionally narrows the product instead of adding new features.
