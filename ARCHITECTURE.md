# Architecture

## Scope

Package 1.0.5 implements the governed Home, social Feed, Profile Timeline provider, Editorial News, publication lifecycle, interactions, moderation, analytics foundations, next-generation feature set, and bounded companion integrations. File 20 remains the application-shell owner, File 22 the role-aware creation facade, File 23 the publishing workspace, File 25 the visual-system owner, and File 26 the global Search/Discovery/Recommendations/Ranking owner.

## Bootstrap

`sabri-complete-home-news-feed.php` defines plugin identity constants, compatibility guards, autoloading, activation/deactivation hooks, translation loading, and runtime bootstrap.

The namespace is `Sabri\HomeNewsFeed`.

## Class Boundaries

- `Plugin`: coordinates runtime registration.
- `Activator` and `Deactivator`: activation and deactivation boundaries.
- `Settings`: structured settings, defaults, sanitization, and tab isolation.
- `Capabilities`: reversible capability policy.
- `PostTypes`: WordPress core post usage and post metadata.
- `Taxonomies`: feed taxonomies and default terms.
- `Database`: dbDelta-compatible schema.
- `Migrations`: schema version and migration preview/execution.
- `Snapshot`: activation and migration snapshots.
- `Rollback`: plugin-owned rollback.
- `SafeMode`: Safe Mode, Emergency Disable, and feature gate.
- `Integrations`: Unified Shell detection and hook contract.
- `SystemCheck`: diagnostics.
- `Repair`: non-destructive repairs.
- `AuditLog`: plugin-owned administrative audit records.
- `DataRetention`: privacy export and erasure foundations.
- `RestFoundation`: authenticated diagnostics routes.
- `FeedContext`, `FeedQuery`, `FeedRanking`, and `FeedRenderer`: safe feed modes, bounded queries, explainable ranking, rendering, pagination, and Load More enhancement.
- `Composer`, `ComposerPermissions`, and `ComposerValidation`: public composer rendering, server-side publishing policy, and structured Clinical Case/Research validation.
- `PostMetadata` and `MediaHandler`: visibility enforcement, structured metadata, single-post context, upload validation, and attachment ownership.
- `HomeIntegration`, `Shortcodes`, `RestFeed`, and `RestComposer`: shortcode fallback, plugin-owned rendering hook, public feed REST, and authenticated composer REST.
- `Assets`: local admin and conditional public assets.
- `Admin`: wp-admin menu and screens.

## Public Rendering

Phase 2 renders the Home Feed and public Composer through `[sabri_complete_home_feed]`, `[sabri_public_post_composer]`, and the plugin-owned `sabri_feed_home_center_content` hook. It does not add a second global navigation, does not replace the Shell header/sidebar/layout resolver, and does not wrap the whole theme between `wp_body_open` and `wp_footer`.

## Settings Namespaces

`general`, `feed`, `news`, `composer`, `capabilities`, `moderation`, `media`, `performance`, `privacy`, `integrations`, and `advanced`.

Unknown future keys are preserved during tab updates.

## Current Governance Boundary

Future work must extend the existing native owners and versioned contracts. File 21 must not create a second application shell, global visual system, universal composer, publishing dashboard, identity authority, notification transport, or global search/ranking backend. Repository completion remains distinct from staging acceptance, deployed/live state, database state, migration completion, rollback rehearsal, and operational acceptance.
