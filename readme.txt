=== Zignites Sentinel ===
Contributors: zignites
Tags: restore, rollback, maintenance, safety, diagnostics
Requires at least: 6.5
Tested up to: 6.8
Requires PHP: 8.0
Stable tag: 1.32.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Controlled restore readiness, snapshot trust guidance, and rollback safety for WordPress operators.

== Description ==

Zignites Sentinel is a premium-feeling operator plugin for high-risk update and recovery work. It helps you prepare controlled restore evidence, judge snapshot trust, review restore readiness, and recover with clearer rollback context.

Sentinel is designed to make WordPress recovery work feel calmer, clearer, and more trustworthy. Instead of pushing operators straight into destructive actions, it keeps the focus on evidence:

* Is there a trustworthy snapshot?
* Is the current restore evidence fresh enough?
* Was there a recent failure that still needs review?
* Is rollback confidence strong enough to proceed carefully?

Sentinel combines snapshot history, restore-readiness checks, trust scoring, operator timelines, and structured logs into one admin experience.

= What Sentinel Does Well =

* Helps operators build and review controlled restore checkpoints.
* Surfaces a global System Health signal based on snapshot freshness, readiness evidence, unresolved failures, and recent recovery outcomes.
* Recommends the safest known snapshot and identifies a last known good state when history supports it.
* Guides operators with clear next steps, confidence messaging, and review warnings.
* Preserves structured history so teams can investigate what happened during readiness, restore, and rollback activity.

= What Sentinel Is Not =

* Sentinel is not a fully transactional restore engine.
* Sentinel does not claim atomic filesystem swaps.
* Sentinel does not provide SaaS, cloud orchestration, or off-site recovery infrastructure.

It is a safety-first WordPress plugin for operators who want better restore-readiness guidance and safer rollback context.

== Installation ==

1. Upload the plugin to `/wp-content/plugins/` or install it through the WordPress admin.
2. Activate **Zignites Sentinel**.
3. Open **Zignites Sentinel** in the admin menu.
4. Start with **Update Readiness** to create your first snapshot and build restore evidence.

== Frequently Asked Questions ==

= What is the first thing I should do after activating Sentinel? =

Open **Update Readiness**, create a snapshot, and run the initial readiness checks. That first snapshot gives Sentinel the evidence it needs to start trust scoring, recommendations, and history tracking.

= Does Sentinel perform fully atomic or transactional restores? =

No. Sentinel is positioned as a controlled restore and rollback-safety tool. It helps operators prepare, review, and recover more carefully, but it does not claim fully atomic or transactional restore behavior.

= What does the System Health status mean? =

System Health summarizes overall trust using snapshot freshness, readiness status, unresolved failures, and recent restore or rollback outcomes. It is meant to answer whether the current evidence looks safe, needs review, or is risky.

= What is the Recommended Snapshot? =

The Recommended Snapshot is the newest snapshot Sentinel currently considers the safest available choice based on trust signals and validation evidence. If no fully safe snapshot exists, Sentinel falls back to the most recent validated snapshot and clearly warns that further review is needed.

= What is Last Known Good? =

Last Known Good is Sentinel's best historical recovery anchor. It is usually derived from successful restore or rollback evidence, or from the most recent restore-ready snapshot with no failure signal.

= Can I still inspect technical detail? =

Yes. Sentinel keeps technical detail available through readiness sections, snapshot metadata, comparison views, and Event Logs. The interface simply tries to keep that detail secondary to the current operator decision.

== Screenshots ==

1. Dashboard hero showing System Health, confidence messaging, and the dominant next action.
2. Dashboard first-run state showing snapshot guidance and a clear starting path.
3. Update Readiness workspace showing System Trust, the recommended snapshot, and the best next step.
4. Snapshot Library showing recommended and last known good context with trust indicators.
5. Partial restore recovery guidance showing the safest recovery path before proceeding.
6. Rollback confirmation context showing recovery confidence and operator safeguards.
7. Event Logs showing the run outcome summary and the filtered event stream.

== Changelog ==

= 1.32.0 =

* Matured the controlled-restore MVP with trust scoring, recommended snapshot logic, timeline guidance, and launch-readiness polish.

== Upgrade Notice ==

= 1.32.0 =

Improves launch-facing product clarity, first-run guidance, snapshot trust messaging, and operator-friendly recovery context.
