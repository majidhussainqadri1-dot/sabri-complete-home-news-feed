=== Sabri Complete Home and News Feed ===
Contributors: sabri
Tags: feed, news, social, moderation, homeopathy
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
Text Domain: sabri-complete-home-news-feed

Phase 1 foundation for Sabri home feed, social news, publishing, safety, and data architecture.

== Description ==

Sabri Complete Home and News Feed 1.0.0 provides the production-grade foundation for later Home Feed, Composer, Social Interactions, News, Moderation, and Analytics phases.

Phase 1 includes plugin bootstrap, admin screens, versioned settings, reversible capabilities, custom social-data schema, Safe Mode, Emergency Disable, snapshots, migration, rollback, non-destructive repair, privacy foundations, REST diagnostics, CI workflow, and release-builder tooling.

Phase 1 does not complete the Home Feed, Composer, social interactions, full News workflows, moderation workflows, or analytics dashboards.

== Installation ==

1. Upload `sabri-complete-home-news-feed` to `wp-content/plugins/`.
2. Activate the plugin on staging first.
3. Open Home & News Feed in wp-admin.
4. Review System Check, Integrations, Repair, Migration, and Rollback pages.
5. Run GitHub Actions and Hostinger staging acceptance before production use.

== Safety ==

Activation, deactivation, repair, migration, rollback, and default uninstall behavior preserve WordPress content and companion-plugin data. No fake doctors, fake news, fake engagement, fake analytics, or placeholder production records are created.

== Changelog ==

= 1.0.0 =
* Added Phase 1 foundation architecture.
* Added versioned settings, capabilities, schema, admin, safety, privacy, REST, repair, migration, rollback, tests, CI, and release-builder tooling.
