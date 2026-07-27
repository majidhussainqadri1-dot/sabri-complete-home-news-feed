# Sabri Complete Home and News Feed

Version: 1.0.2

Plugin slug: `sabri-complete-home-news-feed`

Text domain: `sabri-complete-home-news-feed`

Sabri Complete Home and News Feed is the canonical public Home, social Feed, Profile Timeline, Editorial News, Newsroom, publishing, interaction, migration, diagnostics, and distribution module for the Sabri Social Homeopathy Platform.

## Public Visibility Recovery in 1.0.2

Version 1.0.2 fixes the production-blocking condition in which File 21 could be installed and active while the public website continued to show only a legacy Feed.

The recovery is deliberately bounded:

- the read-only Home surface and Profile Timeline become observable when no completed administrator Wizard decision exists;
- a detected legacy Feed shortcode is replaced only at render time, without changing saved Page content;
- native File 20 slots are preferred when available;
- static front-page content, posts-index loop, and a guarded last-resort public fallback keep File 21 observable when the current Shell/theme does not expose the preferred slot;
- only one complete File 21 surface may render per request;
- Founder posts are queried by canonical Founder identity;
- already-published privileged posts with blank File 21 metadata receive only missing public visibility and review metadata;
- password-protected posts remain excluded;
- Editorial News gates, automatic publication, and File 04 migration remain closed.

## Complete Public Home Contract

The Home surface includes the exact fourteen approved controls:

1. For You
2. Most Viral
3. Latest
4. Founder Posts
5. Doctors Posts
6. Classical Learning
7. Remedies
8. Diseases
9. Clinical Cases
10. Videos
11. Reels
12. PDF Books
13. Clinics
14. Marketplace

It also provides ten bounded Home rows: Most Viral Now, Latest News, From the Founder, From Verified Doctors, Learn Sabri Classical Homeopathy, Videos, Reels, PDF Books, Worldwide Clinics, and Marketplace.

Companion modules keep ownership of their data. When no item provider is available, File 21 may show only an honest landing destination; it does not fabricate items or metrics.

## Safety Boundaries

- Existing WordPress posts, pages, users, comments, media, URLs, and companion-plugin data are preserved.
- Activation, repair, migration, rollback, deactivation, and default uninstall behavior are non-destructive.
- No fake doctors, fake news, fake engagement, fake analytics, or placeholder production records are created.
- Public-surface recovery does not publish drafts, enable News gates, or run legacy migration.
- File 04 migration is selected, bounded, source-preserving, audited, and reversible.
- File 20 remains the owner of the global header, navigation, sidebars, mobile navigation, and layout resolver.
- No production-complete claim is made until controlled WordPress/Hostinger installation, visual acceptance, rollback evidence, monitoring, and Founder sign-off are complete.

## Architecture Summary

The plugin uses deterministic autoloading under the `Sabri\HomeNewsFeed` namespace. Core responsibilities are split across focused classes in `includes/`, administration screens live under `admin/views/`, public templates live under `templates/`, and runtime assets are local to `assets/`.

Social data WordPress core does not model safely is stored in seven plugin-owned tables:

- `wp_sabri_feed_reactions`
- `wp_sabri_feed_follows`
- `wp_sabri_feed_saves`
- `wp_sabri_feed_reports`
- `wp_sabri_feed_views`
- `wp_sabri_feed_poll_votes`
- `wp_sabri_feed_audit_log`

Editorial News adds its documented source, review, submission, correction, Breaking News, translation, preview, rate-limit, and audit-integrity tables. The schema remains version `1.0.0`; runtime version `1.0.2` is an additive corrective release.

See [ARCHITECTURE.md](ARCHITECTURE.md), [DATABASE-SCHEMA.md](DATABASE-SCHEMA.md), [CAPABILITIES.md](CAPABILITIES.md), [FILE-21-HARMONIZATION-COMPLETION-PLAN.md](FILE-21-HARMONIZATION-COMPLETION-PLAN.md), and [FILE-21-PUBLIC-VISIBILITY-RECOVERY-1.0.2.md](FILE-21-PUBLIC-VISIBILITY-RECOVERY-1.0.2.md).

## Administration

The **Home & News Feed** administration area includes settings, Composer policy, roles and capabilities, integrations, System Check, Repair, Migration, Rollback, Staging Preview, Newsroom, Release Readiness, and the comprehensive Activation Wizard.

When File 21 is active but not publicly observable, administrators receive an explicit **Recover File 21 Public Surface** action. The report confirms that News gates, automatic publication, and legacy migration were unchanged.

## Public Runtime

- Home Feed shortcode: `[sabri_complete_home_feed]`
- Public Composer shortcode: `[sabri_public_post_composer]`
- Profile Timeline shortcode: `[sabri_profile_timeline]`
- Profile Timeline action: `sabri_profile_timeline`
- File 21 fallback hook: `sabri_feed_home_center_content`
- Preferred File 20 Home slot: `sabri_shell_home_main`
- Preferred File 20 News slot: `sabri_shell_news_main`

The complete surface carries `data-sabri-hnf-surface="file-21-corrective"`, the active runtime version, and the mount source so staging and production screenshots can prove which renderer is active.

## Release Artifact

The canonical 1.0.2 release builder creates:

```powershell
.\tools\build-release.ps1
```

Canonical candidate:

- `21-sabri-complete-home-news-feed-1.0.2-PUBLIC-VISIBILITY-CANDIDATE.zip`
- matching `.sha256`
- runtime manifest
- exact test report

All source, packaged WordPress, security, migration, public visibility, Phase 4A/4B/4C, harmonization, corrective, and continuous QA gates must succeed on one unchanged exact head before merge.
