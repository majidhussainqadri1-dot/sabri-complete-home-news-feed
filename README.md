# Sabri Complete Home and News Feed

Package Version: 1.0.4  
Stable Runtime/API: 1.0.3  
Schema: 1.0.0

Plugin slug: `sabri-complete-home-news-feed`

Text domain: `sabri-complete-home-news-feed`

Sabri Complete Home and News Feed is the canonical public Home, social Feed, Editorial News, Newsroom, publication lifecycle, interaction, migration, diagnostics and distribution module for the Sabri Social Homeopathy Platform. File 20 remains the application-shell owner, File 22 the role-aware creation facade, File 23 the publishing workspace/dashboard, File 25 the global visual-system owner, and File 26 the canonical federated Search/Discovery/Recommendations/Ranking owner.

## Governing-plan reconciliation in package 1.0.4

Package 1.0.4 closes the remaining cross-owner implementation gaps identified after the File 23 integration release:

- File 22 now uses only a coarse authenticated `read` registry prefilter for File 21 Social Publication; final create authorization remains File 21 native `can_create()` with File 00 current-subject, suspension, current-session assurance, Safe Mode and publication-policy checks.
- File 23 Adapter Contract 2.0.0 projections remain bounded and read/review/calendar oriented; direct File 23 writes remain fail-closed and File 21 remains the native publication owner.
- File 21 registers `file21-publication` with File 26 as a public, derivative source connector. It begins in `proposed` state and cannot be self-activated by File 21.
- File 26 receives only allowlisted public post/news projections. Public social posts require approved review state and public visibility; Editorial News uses the approved public projector. Source restriction/deletion produces File 26 tombstones.
- File 21 sends neutral global ranking inputs and does not inject donation, payment, paid-promotion, purchased-engagement or Founder-favoritism advantages into File 26 organic ranking.
- File 21 Feed assets retain green public action/accent presentation; File 25 remains the design-token and visual owner.
- Package identity is promoted to `1.0.4` while stable runtime/API remains `1.0.3` and schema remains `1.0.0`; no database migration is introduced by this package correction.

The full correction record is in `docs/FILE21-FOUR-PLAN-FINAL-COMPLETION-2026-08-07.md`.

## File 23 provider integration

File 21 registers a load-order-safe Adapter Contract 2.0.0 provider with File 23. The provider supplies bounded, authorization-filtered native inventory, Founder/Doctor workspace counts, review-queue projections and publishing-calendar projections. File 21 remains the canonical owner and exposes no direct File 23 write operations.

## Production-Rejection Corrective Runtime 1.0.3

Runtime/API 1.0.3 closes the live-deployment defects found after 1.0.2: public GET requests no longer write recovery metadata; File 21 registers and renders the native News slot; canonical and legacy News pages are routed or redirected safely after explicit gate activation; all ten Home rows remain observable with honest unavailable states; Breaking News is main-loop/context/render-once guarded; core-post visibility is applied before pagination; recursive Home Feed rendering is blocked while WordPress applies `the_content`; Shell diagnostics do not claim Connected without native slot readiness; Safe Boot keeps authenticated status/schema diagnostics available; and duplicate plugin copies resolve to the highest canonical version with one controlled administrator reload.

Editorial News gates remain fail-closed until the Activation Wizard is completed on staging. File 20 native-slot availability remains an external dependency and is reported truthfully; File 21 compatibility mounts do not modify File 20.

## Corrected File 22 workflow boundary

The `social_publication` adapter keeps File 21 as the only native post owner while allowing File 22 Workflow API `1.0.0` to coordinate a deliberately text-first workflow.

- Only exact WordPress drafts are mutable; pending-review, scheduled, published, rejected, foreign and non-post references fail closed.
- Preview URLs are HMAC-signed for the authenticated subject and receive request-time ten-minute expiry enforcement.
- Raw idempotency keys and payload bodies are not stored. File 21 keeps one-way hashes, bounded leases and opaque native references for reconciliation.
- Atomic processing records and execution locks prevent duplicate native mutation and repeated Composer side effects.
- Completed records expire after 30 days; a stranded draft receives one seven-day recovery interval; stale locks expire after two minutes.
- Daily bounded maintenance and `Tools → File 22 Workflow Recovery` provide privacy-safe reconciliation with aggregate-only output.
- Founder Update and Platform News are hidden from and rejected for non-Founder/non-Administrator subjects.
- Actual contract CI loads the exact reviewed File 22 Workflow Coordinator rather than replacing that coordinator with a local stub.

Structured Clinical Case, Research, Poll, upload, Video and PDF workflows remain on their complete native-owner routes. The direct adapter does not flatten protected structures into a generic File 22 payload.

## Complete public Home contract

The Home surface includes the fourteen approved controls: For You, Most Viral, Latest, Founder Posts, Doctors Posts, Classical Learning, Remedies, Diseases, Clinical Cases, Videos, Reels, PDF Books, Clinics and Marketplace.

It also provides ten bounded Home rows: Most Viral Now, Latest News, From the Founder, From Verified Doctors, Learn Sabri Classical Homeopathy, Videos, Reels, PDF Books, Worldwide Clinics and Marketplace.

Companion modules keep ownership of their data. When no item provider is available, File 21 renders an honest unavailable state; it does not fabricate items or metrics.

## Search and discovery boundary

File 26 is the canonical global Search/Discovery/Recommendations/Classification/Knowledge-Graph/Ranking owner. File 21 therefore exposes source projections rather than a second global search backend. The older `sabri_search_providers` and `sabri_shell_search_providers` hooks remain only as fail-soft compatibility while File 26 connector acceptance is completed.

The File 26 connector:

- is identified as `file21-publication` and owned by File 21;
- starts `proposed` and requires File 26 governance for lifecycle promotion;
- supports bounded shadow reindex batches;
- revalidates visibility through native File 21 policy;
- sends title/summary, canonical URL, public topics, locale, author key and limited public metadata only;
- does not copy private bodies, evidence, identity records or protected drafts;
- emits tombstones for restricted/deleted source objects;
- keeps global organic ranking policy with File 26.

## Safety boundaries

- Existing WordPress posts, pages, users, comments, media, URLs and companion-plugin data are preserved.
- Activation, repair, migration, rollback, deactivation and default uninstall behavior are non-destructive.
- No fake doctors, fake news, fake engagement, fake analytics or placeholder production records are created.
- Public page rendering performs no recovery database writes.
- File 04 migration is selected, bounded, source-preserving, audited and reversible.
- File 20 remains the owner of the global header, navigation, sidebars, mobile navigation and layout resolver.
- File 25 remains the owner of global visual tokens and final presentation.
- Donation/payment status must not create an undisclosed organic ranking advantage or degrade core access.
- No production-complete claim is made until controlled WordPress/Hostinger installation, visual acceptance, rollback evidence, monitoring and Founder sign-off are complete.

## Architecture summary

The plugin uses deterministic autoloading under the `Sabri\HomeNewsFeed` namespace. Core responsibilities are split across focused classes in `includes/`, administration screens live under `admin/views/`, public templates live under `templates/`, and runtime assets are local to `assets/`.

Social data WordPress core does not model safely is stored in seven plugin-owned tables:

- `wp_sabri_feed_reactions`
- `wp_sabri_feed_follows`
- `wp_sabri_feed_saves`
- `wp_sabri_feed_reports`
- `wp_sabri_feed_views`
- `wp_sabri_feed_poll_votes`
- `wp_sabri_feed_audit_log`

Editorial News adds its documented source, review, submission, correction, Breaking News, translation, preview, rate-limit and audit-integrity tables. The schema remains version `1.0.0`; runtime/API remains `1.0.3` and package identity is `1.0.4`.

See `ARCHITECTURE.md`, `DATABASE-SCHEMA.md`, `CAPABILITIES.md`, `FILE-21-HARMONIZATION-COMPLETION-PLAN.md`, `FILE-21-PUBLIC-VISIBILITY-RECOVERY-1.0.2.md`, `FILE-21-PRODUCTION-REJECTION-CORRECTIVE-1.0.3.md`, `docs/FILE22-SOCIAL-PUBLICATION-ADAPTER.md`, and `docs/FILE21-FOUR-PLAN-FINAL-COMPLETION-2026-08-07.md`.

## Administration

The **Home & News Feed** administration area includes settings, Composer policy, roles and capabilities, integrations, System Check, Repair, Migration, Rollback, Staging Preview, Newsroom, Release Readiness and the comprehensive Activation Wizard. Workflow recovery is available under **Tools → File 22 Workflow Recovery**.

Read-only Home and Profile Timeline compatibility defaults do not activate Editorial News. The administrator must use the Activation Wizard, flush rewrite rules and run staging acceptance before `/news/`, Breaking News, corrections, RSS, schema, sitemap, notifications or submissions are declared operational.

## Public runtime

- Home Feed shortcode: `[sabri_complete_home_feed]`
- Public Composer shortcode: `[sabri_public_post_composer]`
- Profile Timeline shortcode: `[sabri_profile_timeline]`
- Profile Timeline action: `sabri_profile_timeline`
- File 21 fallback hook: `sabri_feed_home_center_content`
- Preferred File 20 Home slot: `sabri_shell_home_main`
- Preferred File 20 News slot: `sabri_shell_news_main`

The complete surfaces carry `data-sabri-hnf-surface`, the active runtime version and the mount source so staging and production screenshots can prove which renderer is active.

## Release artifact

The canonical package 1.0.4 builder creates one candidate identity:

- `21-sabri-complete-home-news-feed-1.0.4-CONTROLLED-STAGING-CANDIDATE.zip`
- matching `.sha256`
- embedded and external runtime manifest
- exact-head test report

Historical misleading aliases are not generated.

## QA policy

The repository owner explicitly disabled the former one-hour/3,900-second soak jobs. Their workflow files perform only fast disabled-state confirmation and must not be cited as long-duration evidence.

All required source, packaged WordPress, PHP 8.1/8.3, security, migration, public visibility, routing, pagination, Phase 4A/4B/4C, harmonization, corrective, File 00 authorization, File 22 real-contract, File 23 provider, File 26 connector, official PHP quality and ten WordPress Playground/Playwright browser gates must succeed on one unchanged exact head before merge.

## Truthful lifecycle status

Package 1.0.4 may be called **coded**, **packaged** and **Automated-QA Green** only after the exact-head workflows succeed. **Staging-Accepted**, **Live-Deployed** and **Operational** remain separate external gates and must never be inferred from a merge, ZIP or CI result.
