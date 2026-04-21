# Zignites Sentinel Product Strategy

## Product Promise

Zignites Sentinel helps WordPress operators take a rollback checkpoint before risky plugin or theme updates, validate that checkpoint, and restore the active code layer if the update goes wrong.

This is a narrow recovery tool, not a full backup system.

## Primary Audience

- WordPress developers who update client sites and need a faster rollback safety layer than full backups
- Agencies and technical maintainers managing many plugin and theme updates
- Technical site owners who understand the difference between code rollback and full-site recovery

## Secondary Audience

- QA and release managers who want a clearer pre-update checklist
- Support engineers who need an audit trail for recent checkpoint and restore actions

## Users We Should Not Optimize For First

- Non-technical users expecting one-click full-site recovery
- Site owners who mainly need database, media, or off-site backup protection
- Hosts or enterprise teams needing transactional restore guarantees

## Core Pain Points

1. Full backups are too heavy for routine plugin and theme updates.
2. WordPress updates often happen without a clean rollback checkpoint.
3. When a site breaks after an update, teams lose time figuring out what changed and what can be safely restored.
4. Existing recovery workflows mix backup, debugging, and rollback into one messy operator experience.

## Jobs To Be Done

### Functional Jobs

- Before an update, save the current active theme and plugin code state
- Check whether that checkpoint is complete enough to trust
- Restore that checkpoint if the update breaks the code layer
- Roll back the last restore if the recovery path makes things worse
- Review a short history of what happened

### Emotional Jobs

- Feel safer before clicking update
- Reduce panic during post-update failure handling
- Show clients or teammates that recovery work follows a disciplined process

## Product Positioning

### Category

Rollback checkpoint and guarded code-restore plugin for WordPress.

### Positioning Statement

For WordPress developers and technical maintainers who need a safer update workflow, Zignites Sentinel is a rollback checkpoint plugin that captures the active plugin and theme code state before updates, validates that checkpoint, and supports guarded restore and rollback review. Unlike full backup plugins, Sentinel focuses on fast pre-update recovery for the code layer instead of promising full-site disaster recovery.

## Why Someone Should Use Sentinel

- It is faster and narrower than full backup tooling for routine plugin and theme updates
- It makes rollback preparation explicit instead of relying on memory
- It adds validation before restore, not just archive creation
- It keeps a restore and rollback trail that is easier to review than generic logs
- It reduces operator ambiguity by focusing on one workflow: before update, validate, restore if needed

## Current Product Principles

1. Narrow scope wins.
   Sentinel should be excellent at code-layer rollback checkpoints before it attempts broader recovery claims.

2. Safety messaging must stay honest.
   The product should clearly say what it restores and what it does not.

3. The workflow must feel operational.
   Users should always know the next safest action.

4. Recovery confidence matters more than feature count.
   Validation, visibility, and traceability are more important than adding more destructive power.

## Current Product Risks

1. The workflow is still too manual at the moment the user actually runs updates.
2. Artifact storage still lives under uploads, which is acceptable for MVP but not ideal for long-term trust.
3. The product is strong for technical users but still needs clearer onboarding for mixed-skill teams.

## Next Strategic Priorities

### Priority 1: Own The Pre-Update Moment

- Add stronger reminders or tighter integration with the plugin/theme update workflow
- Make it harder to forget checkpoint creation before a risky update
- Reduce the gap between "I need to update now" and "I should open Sentinel first"

### Priority 2: Clarify Product Fit In-App

- Keep the Dashboard focused on readiness and next action
- Use Before Update as the operational workspace
- Explain clearly who Sentinel is for and when a full backup tool is still required

### Priority 3: Increase Trust

- Improve artifact storage hardening beyond public uploads when possible
- Keep validation and restore gate coverage expanding
- Maintain the live smoke path so UI simplification does not outpace confidence

## Near-Term Roadmap

### Phase A

- Tighten product positioning across docs and admin UI
- Align smoke checklist and operator documentation with the narrowed product
- Surface better onboarding and "best fit" guidance in the Dashboard and Before Update screens

### Phase B

- Add pre-update workflow integration or stronger update interception cues
- Add better first-run guidance for technical site owners
- Refine restore-result storytelling in History

### Phase C

- Revisit storage architecture for stronger artifact protection
- Add richer operator handoff/export surfaces only after validation coverage stays strong

## Success Signals

- More checkpoints are created before updates, not after failures
- Operators can understand the next step without reading external docs
- Restore misuse drops because boundaries are clearer
- The product is easier to recommend to developers and agencies as a narrow recovery layer

## Product Guardrails

- Do not market Sentinel as a full backup plugin
- Do not imply database or media recovery
- Do not add broader destructive behavior before validation and rollback confidence improve
- Do not optimize for beginner-first simplicity by hiding critical safety boundaries
