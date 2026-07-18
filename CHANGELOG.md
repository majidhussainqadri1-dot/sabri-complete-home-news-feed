# Changelog

## 1.0.0 - Phase 2 Home Feed and Composer Runtime

- Added real Home Feed runtime using existing WordPress posts, feed taxonomies, visibility metadata, bounded pagination, explainable Phase 2 ranking, server-rendered filters, and accessible Load More enhancement.
- Added public Composer runtime with capability-based access, draft, preview, submit-for-review, publish, and schedule status policy.
- Added Clinical Case and Research structured metadata validation, including patient-identifier blocking and controlled evidence levels.
- Added WordPress media handling with MIME, size, executable-file, and attachment ownership validation.
- Hardened Phase 2 composer validation, pending-media privacy, failed-upload cleanup, attachment reuse, author privacy, settings gates, and disabled feed-type enforcement after full staging audit.
- Prevented plugin-owned hooks and shortcode fallbacks from rendering Home Feed on single-post permalinks, preserving the post detail view behind every Read More link.
- Added shortcode fallbacks `[sabri_complete_home_feed]` and `[sabri_public_post_composer]`, plus a plugin-owned Home rendering hook with duplicate protection.
- Added REST feed and composer routes with explicit permission callbacks and shared server-side composer policy.
- Added feed/composer public assets, templates, Phase 2 admin settings, behavior tests, workflow updates, and Phase 2 release-builder labels.
- Deferred likes, dislikes, comments, replies, saves, follows, reports, polls, complete editorial News, moderation workflow UI, and analytics.

## 1.0.0 - Phase 1 Foundation

- Added independent WordPress plugin bootstrap with PHP 8.1 and WordPress 6.0 guards.
- Added deterministic namespaced loader, activation, deactivation, and uninstall boundaries.
- Added versioned settings architecture with safe defaults and per-tab sanitizers.
- Added reversible plugin-specific capability policy.
- Added core post usage policy, feed taxonomies, post metadata, and default feed type terms.
- Added custom social-data schema for reactions, follows, saves, reports, views, poll votes, and audit logs.
- Added activation snapshots, idempotent migrations, rollback, and non-destructive repair.
- Added Safe Mode and Emergency Disable foundations.
- Added Unified Shell adapter documentation and detection.
- Added privacy export and erasure foundations.
- Added authenticated REST diagnostics.
- Added admin screens, local assets, stub behavior tests, static checks, GitHub Actions workflow, and Phase 1 release-builder tooling.
- Fixed Phase 1 safety details for schema-version verification, nested integration settings preservation, privacy export format, and uninstall capability cleanup.

This is not the final complete Home and News Feed feature release.
