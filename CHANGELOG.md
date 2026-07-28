# Changelog

## 1.0.2 - Production Recovery and Native Home/News Integration

- Fixed harmonized-settings recursion and retained explicit Safe Boot recovery.
- Added truthful File 20 rendering-slot detection and official Home plus News slot registration.
- Preserved all ten mandatory Home rows with explicit empty/unavailable states.
- Removed automatic public-request recovery writes from `init` and frontend rendering.
- Scoped Breaking News to the main Home/News loop with a render-once guard.
- Moved public post eligibility into unambiguous list queries so pagination totals remain accurate.
- Added controlled legacy News Page replacement and an identifiable File 21 News surface.
- Aligned release metadata and builders on version 1.0.2.

## 1.0.0 - Phase 2 Home Feed and Composer Runtime

- Added real Home Feed runtime using existing WordPress posts, feed taxonomies, visibility metadata, bounded pagination, explainable Phase 2 ranking, server-rendered filters, and accessible Load More enhancement.
- Added public Composer runtime with capability-based access, draft, preview, submit-for-review, publish, and schedule status policy.
- Added Clinical Case and Research structured metadata validation, including patient-identifier blocking and controlled evidence levels.
- Added WordPress media handling with MIME, size, executable-file, and attachment ownership validation.
- Hardened composer validation, pending-media privacy, failed-upload cleanup, attachment reuse, author privacy, settings gates, and disabled feed-type enforcement after full staging audit.
- Prevented plugin-owned hooks and shortcode fallbacks from rendering Home Feed on single-post permalinks, preserving the post detail view behind every Read More link.
- Added shortcode fallbacks `[sabri_complete_home_feed]` and `[sabri_public_post_composer]`, plus a plugin-owned Home rendering hook with duplicate protection.
- Added REST feed and composer routes with explicit permission callbacks and shared server-side composer policy.

## 1.0.0 - Phase 1 Foundation

- Added independent WordPress plugin bootstrap with PHP 8.1 and WordPress 6.0 guards.
- Added deterministic namespaced loader, activation, deactivation, and uninstall boundaries.
- Added versioned settings architecture with safe defaults and per-tab sanitizers.
- Added reversible plugin-specific capability policy.
- Added core post usage policy, feed taxonomies, post metadata, and default feed type terms.
- Added custom social-data schema for reactions, follows, saves, reports, views, poll votes, and audit logs.
- Added activation snapshots, idempotent migrations, rollback, and non-destructive repair.
- Added Safe Mode, Emergency Disable, privacy, authenticated diagnostics, and Unified Shell adapter foundations.
