=== Sabri Complete Home and News Feed ===
Contributors: sabri
Tags: feed, news, social, moderation, homeopathy
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 1.0.2
License: GPLv2 or later
Text Domain: sabri-complete-home-news-feed

Complete Home, social Feed, Profile Timeline, Editorial News, publishing, migration, safety, and File 20 integration runtime for the Sabri Social Homeopathy Platform.

== Description ==

Version 1.0.2 is the canonical corrective release line. It includes the Home Feed, controlled publishing, social interactions, Profile Timeline, Editorial News/Newsroom foundations, public News routing, corrections and Breaking News foundations, legacy migration safeguards, Safe Boot recovery, companion integrations, and identifiable public File 21 surfaces.

File 20 owns the global application shell; File 21 owns Home and News content, queries, publishing policy, and News presentation. Editorial News and write-sensitive features remain controlled by explicit, reversible gates.

== Installation ==

1. Back up the WordPress files and database.
2. Install or update File 20 with the official Home and News rendering slots.
3. Upload this plugin to `wp-content/plugins/sabri-complete-home-news-feed/` on staging.
4. Remove or deactivate duplicate copies installed under other folder names.
5. Activate the plugin and use **Retry Safe Boot** when a previous failure is recorded.
6. Complete the Activation Wizard and verify System Check.
7. Enable Editorial News gates only after staging acceptance, then flush rewrite rules.
8. Verify `/news/`, all ten Home rows, pagination, single-post Read More, rollback, and cache clearing before production use.

== Safety ==

Public GET requests do not run recovery migrations. Recovery and post normalization run only from activation or an explicit authorized administrator action. No fake doctors, fake news, fake engagement, fake analytics, or placeholder production records are created.

== Changelog ==

= 1.0.2 =
* Fixed recursive settings normalization and retained Safe Boot recovery.
* Added truthful File 20 slot detection instead of unconditional Connected status.
* Registered official Home and News Shell slots.
* Preserved all ten mandatory Home rows with explicit empty/unavailable states.
* Removed database recovery writes from public rendering and ordinary `init` requests.
* Restricted Breaking News to the main Home/News loop with a render-once guard.
* Enforced eligible public post metadata before list queries so pagination totals remain correct.
* Added controlled replacement for legacy `/sabri-news/` Page content when Editorial News is enabled.
* Aligned release metadata and builders on version 1.0.2.

= 1.0.0 =
* Added the safety foundation and Home Feed/Composer runtime.
