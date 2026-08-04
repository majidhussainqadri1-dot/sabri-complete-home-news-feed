# Sabri Complete Home and News Feed

Version: 1.0.3

Plugin slug: `sabri-complete-home-news-feed`

Text domain: `sabri-complete-home-news-feed`

Sabri Complete Home and News Feed is the canonical public Home, social Feed, Profile Timeline, Editorial News, Newsroom, publishing, interaction, migration, diagnostics, and distribution module for the Sabri Social Homeopathy Platform.

## File 23 provider integration in package 1.0.3.3

File 21 now registers a load-order-safe Adapter Contract 2.0.0 provider with File 23. The provider supplies bounded, authorization-filtered native inventory, Founder/Doctor workspace counts, review-queue projections and publishing-calendar projections. File 21 remains the canonical owner and exposes no direct File 23 write operations. Package identity 1.0.3.3 invalidates prior provider-acceptance evidence; production writes remain fail-closed pending new staging/production acceptance.

## Production-Rejection Corrective Release in 1.0.3

Version 1.0.3 closes the live-deployment defects found after 1.0.2: public GET requests no longer write recovery metadata; File 21 registers and renders the native News slot; canonical and legacy News pages are routed or redirected safely after explicit gate activation; all ten Home rows remain observable with honest unavailable states; Breaking News is main-loop/context/render-once guarded; core-post visibility is applied before pagination; recursive Home Feed rendering is blocked while WordPress applies `the_content`; Shell diagnostics no longer claim Connected without all five native slots; Safe Boot keeps authenticated status/schema diagnostics available; and duplicate plugin copies resolve to the highest canonical version with one controlled administrator reload.

Editorial News gates remain fail-closed until the Activation Wizard is completed on staging. File 20 native-slot availability remains an external dependency and is reported truthfully; File 21 compatibility mounts do not modify File 20.

## Corrected File 22 Workflow Boundary

The `social_publication` adapter keeps File 21 as the only native post owner while allowing File 22 Workflow API `1.0.0` to coordinate a deliberately text-first workflow.

- Only exact WordPress drafts are mutable; pending-review, scheduled, published, rejected, foreign, and non-post references fail closed.
- Preview URLs are HMAC-signed for the authenticated subject and receive request-time ten-minute expiry enforcement.
- Raw idempotency keys and payload bodies are not stored. File 21 keeps one-way hashes, bounded leases, and opaque native references for reconciliation.
- Atomic processing records and execution locks prevent duplicate native mutation and repeated Composer side effects.
- Completed records expire after 30 days; a stranded draft receives one seven-day recovery interval; stale locks expire after two minutes.
- Daily bounded maintenance and `Tools → File 22 Workflow Recovery` provide privacy-safe reconciliation with aggregate-only output.
- Founder Update and Platform News are hidden from and rejected for non-Founder/non-Administrator subjects.
- Actual contract CI loads the exact reviewed File 22 Phase 22E Workflow Coordinator rather than replacing that coordinator with a local stub.

Structured Clinical Case, Research, Poll, upload, Video, and PDF workflows remain on their complete native owner routes. The direct adapter does not flatten protected structures into a generic File 22 payload.

## Public Visibility Recovery in 1.0.2

Version 1.0.2 fixes the production-blocking condition in which File 21 could be installed and active while the public website continued to show only a legacy Feed.

The recovery is deliberately bounded:

- the read-only Home surface and Profile Timeline become observable when no completed administrator Wizard decision exists;
- a detected legacy Feed shortcode is replaced only at render time, without changing saved Page content;
- native File 20 slots are preferred when available;
- static front-page content and posts-index loop keep File 21 observable when the current Shell/theme does not expose the preferred slot;
- only one complete File 21 Home surface and one News surface may render per request;
- Founder posts are queried by canonical Founder identity;
- already-published privileged posts with blank File 21 metadata are normalized only through the explicit administrator recovery action;
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

Companion modules keep ownership of their data. When no item provider is available, File 21 renders an honest unavailable state; it does not fabricate items or metrics.

## Safety Boundaries

- Existing WordPress posts, pages, users, comments, media, URLs, and companion-plugin data are preserved.
- Activation, repair, migration, rollback, deactivation, and default uninstall behavior are non-destructive.
- No fake doctors, fake news, fake engagement, fake analytics, or placeholder production records are created.
- Public page rendering performs no recovery database writes.
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

Editorial News adds its documented source, review, submission, correction, Breaking News, translation, preview, rate-limit, and audit-integrity tables. The schema remains version `1.0.0`; runtime version `1.0.3` is an additive corrective release.

See [ARCHITECTURE.md](ARCHITECTURE.md), [DATABASE-SCHEMA.md](DATABASE-SCHEMA.md), [CAPABILITIES.md](CAPABILITIES.md), [FILE-21-HARMONIZATION-COMPLETION-PLAN.md](FILE-21-HARMONIZATION-COMPLETION-PLAN.md), [FILE-21-PUBLIC-VISIBILITY-RECOVERY-1.0.2.md](FILE-21-PUBLIC-VISIBILITY-RECOVERY-1.0.2.md), [FILE-21-PRODUCTION-REJECTION-CORRECTIVE-1.0.3.md](FILE-21-PRODUCTION-REJECTION-CORRECTIVE-1.0.3.md), and [docs/FILE22-SOCIAL-PUBLICATION-ADAPTER.md](docs/FILE22-SOCIAL-PUBLICATION-ADAPTER.md).

## Administration

The **Home & News Feed** administration area includes settings, Composer policy, roles and capabilities, integrations, System Check, Repair, Migration, Rollback, Staging Preview, Newsroom, Release Readiness, and the comprehensive Activation Wizard. Workflow recovery is available under **Tools → File 22 Workflow Recovery**.

Read-only Home and Profile Timeline compatibility defaults do not activate Editorial News. The administrator must use the Activation Wizard, flush rewrite rules, and run staging acceptance before `/news/`, Breaking News, corrections, RSS, schema, sitemap, notifications, or submissions are declared operational.

## Public Runtime

- Home Feed shortcode: `[sabri_complete_home_feed]`
- Public Composer shortcode: `[sabri_public_post_composer]`
- Profile Timeline shortcode: `[sabri_profile_timeline]`
- Profile Timeline action: `sabri_profile_timeline`
- File 21 fallback hook: `sabri_feed_home_center_content`
- Preferred File 20 Home slot: `sabri_shell_home_main`
- Preferred File 20 News slot: `sabri_shell_news_main`

The complete surfaces carry `data-sabri-hnf-surface`, the active runtime version, and the mount source so staging and production screenshots can prove which renderer is active.

## Release Artifact

The canonical 1.0.3 release builders create one candidate identity:

- `21-sabri-complete-home-news-feed-1.0.3.3-CONTROLLED-STAGING-CANDIDATE.zip`
- matching `.sha256`
- runtime manifest
- exact test report

Historical misleading aliases are not generated.

## QA Policy

The repository owner explicitly disabled the three former one-hour/3,900-second soak jobs. Their workflow files now execute only fast disabled-state confirmation and must not be cited as long-duration evidence.

All required short source, packaged WordPress, PHP 8.1/8.3, security, migration, public visibility, routing, pagination, Phase 4A/4B/4C, harmonization, corrective, UI, File 22 real-contract, and WordPress Playground gates must succeed on one unchanged exact head before merge.
