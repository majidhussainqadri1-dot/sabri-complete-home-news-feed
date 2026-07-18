# Architecture

## Scope

Phase 1 creates the foundation for future Home Feed, Composer, Social Interactions, News, Moderation, and Analytics phases. It intentionally avoids superficial frontend implementation.

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
- `Assets`: local admin assets only.
- `Admin`: wp-admin menu and screens.

## Public Rendering

Phase 1 does not render a complete Home feed, Composer, News center, moderation UI, or analytics UI. It does not add a second global navigation and does not wrap the whole theme between `wp_body_open` and `wp_footer`.

## Settings Namespaces

`general`, `feed`, `news`, `composer`, `capabilities`, `moderation`, `media`, `performance`, `privacy`, `integrations`, and `advanced`.

Unknown future keys are preserved during tab updates.

## Future Phase Boundary

Phase 2 should begin at the central feature gate and implement real Home Feed and Composer runtime behavior against the existing schema, settings, capability policy, Shell contract, Safe Mode, and audit foundations.
