# Sabri Complete Home and News Feed

Version: 1.0.0

Plugin slug: `sabri-complete-home-news-feed`

Text domain: `sabri-complete-home-news-feed`

Sabri Complete Home and News Feed is an independent WordPress plugin for the Sabri Social Homeopathy Platform. Phase 2 adds a real Home Feed and public Composer runtime on top of the Phase 1 bootstrap, settings architecture, capability policy, custom social-data schema, Safe Mode, repair, migration, rollback, privacy, REST diagnostics, admin screens, CI workflow, and release-builder foundation.

Phase 2 completes the foundation Home Feed and public Composer runtime only. Likes, dislikes, comments, replies, saves, follows, reports, polls, complete editorial News workflows, moderation workflows, and analytics dashboards remain deferred.

## Safety Boundaries

- Existing WordPress posts, pages, users, comments, media, URLs, and companion-plugin data are preserved.
- Activation, repair, migration, rollback, deactivation, and default uninstall behavior are non-destructive.
- No fake doctors, fake news, fake engagement, fake analytics, or placeholder production records are created.
- No messaging, calls, end-to-end encryption, live streaming, AI recommendations, or appointment functionality is claimed.
- No external CDNs, remote fonts, or runtime third-party JavaScript are used.
- The plugin does not wrap the whole theme between `wp_body_open` and `wp_footer` and does not use whole-page output buffering.
- No production claim is made until GitHub Actions and Hostinger staging acceptance pass.

## Architecture Summary

The plugin uses deterministic autoloading under the `Sabri\HomeNewsFeed` namespace. Core responsibilities are split across focused classes in `includes/`, admin screens live under `admin/views/`, and runtime assets are local to `assets/`.

Custom tables are reserved only for social data WordPress core does not model safely:

- `wp_sabri_feed_reactions`
- `wp_sabri_feed_follows`
- `wp_sabri_feed_saves`
- `wp_sabri_feed_reports`
- `wp_sabri_feed_views`
- `wp_sabri_feed_poll_votes`
- `wp_sabri_feed_audit_log`

See [ARCHITECTURE.md](ARCHITECTURE.md), [DATABASE-SCHEMA.md](DATABASE-SCHEMA.md), and [CAPABILITIES.md](CAPABILITIES.md).

## Admin

The admin menu is **Home & News Feed** with:

Overview, Feed Settings, News Settings, Composer, Roles & Capabilities, Integrations, System Check, Repair, Migration, Rollback, and Help.

Feed and Composer settings are functional for the Phase 2 runtime. Deferred Phase 3 and Phase 4 functions remain labelled as future work.

## Public Runtime

- Home Feed shortcode: `[sabri_complete_home_feed]`
- Public Composer shortcode: `[sabri_public_post_composer]`
- Plugin-owned fallback hook: `sabri_feed_home_center_content`

The Home Feed reuses existing WordPress posts with feed taxonomies and metadata. It does not copy posts into a new post type and does not create placeholder production data.

## Unified Shell Integration

The sibling Sabri Unified Application Shell repository was inspected read-only. Confirmed Shell hooks are documented in [INTEGRATIONS.md](INTEGRATIONS.md). This plugin detects the Shell when present, does not fatal when absent, does not render a second global navigation, and does not alter the Shell layout resolver.

## Local Checks

```powershell
.\tools\run-local-static-checks.ps1
```

If PHP, Node, npm, Python, or GitHub CLI are unavailable locally, the script reports that instead of fabricating results. GitHub Actions runs the full intended test and packaging workflow.

## Phase 2 Release Artifact

The release builder creates a Phase 2 development artifact:

```powershell
.\tools\build-release.ps1
```

The official final filenames remain reserved for the complete future release:

- `21-sabri-complete-home-news-feed-1.0.0.zip`
- `21-sabri-complete-home-news-feed-1.0.0.sha256`
- `21-sabri-complete-home-news-feed-1.0.0-TEST-REPORT.md`
