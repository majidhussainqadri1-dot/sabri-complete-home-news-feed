=== Sabri Complete Home and News Feed ===
Contributors: sabri
Tags: home feed, editorial news, profile timeline, moderation, homeopathy
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 1.0.5
License: GPLv2 or later
Text Domain: sabri-complete-home-news-feed

Canonical public Home, social Feed, Editorial News, publication lifecycle, safety and integration runtime for the Sabri Social Homeopathy Platform.

== Description ==

Package 1.0.5 is the current four-plan/current-wave governing reconciliation release. Stable File 21 runtime/API remains 1.0.3 and schema remains 1.0.0; this package introduces no database migration.

File 21 remains the canonical owner of Home, social posts, Editorial News, publication lifecycle and native interactions. File 20 owns the application shell, File 22 the role-aware creation facade, File 23 the publishing workspace/dashboard, File 25 the global visual system and File 26 the canonical federated Search/Discovery/Recommendations/Ranking layer.

Package 1.0.5 completes the current-wave corrections:

* Preserves exactly fourteen canonical Home controls while Following remains an auxiliary user-choice Feed mode.
* Adds Following / Latest / For You choices, “Why am I seeing this?”, reduced personalization, Not interested, bounded author/topic snoozes, topic reduction and one-action Feed preference reset.
* Consumes File 17 relationship/block truth through a versioned bridge without querying or writing File 17 storage directly; the historical local follow store remains fail-soft compatibility only.
* Adds saved collections, tags, private notes and bounded export on the existing canonical File 21 saves truth.
* Adds a visible natural stopping point, deterministic Oldest/Newest comments, safe visible parent/reply context, textual mention metadata and collapsible deeper reply groups.
* Prevents authenticated Feed output from entering transient cache so current private preference, relationship and block state is not served stale.
* Keeps central green public action/accent presentation and removes Founder, donation, payment, paid-promotion and purchased-engagement organic ranking advantage.
* File 22 uses only a coarse authenticated registry gate; native File 21 can_create() remains the final authorization boundary with File 00 subject-state and current-session checks.
* File 23 receives bounded inventory, workspace, review and calendar projections while direct File 23 writes remain fail-closed.
* File 21 registers the public derivative `file21-publication` connector with File 26. It starts in `proposed` state and File 21 cannot activate it.
* File 26 receives only approved public post/news projections and tombstones when source objects become restricted or are deleted.
* Deterministic release evidence keeps Hostinger staging, live deployment and operational acceptance explicitly separate from code/package/CI completion.

Runtime/API 1.0.3 remains the production-rejection corrective foundation. It removes front-end database recovery writes, adds native News-slot rendering and legacy News-page handling, keeps exactly ten Home rows observable, guards Breaking News to the main Home/News loop, applies public-post eligibility before pagination, reports File 20 native-slot readiness truthfully, exposes authenticated Safe Boot diagnostics and resolves duplicate File 21 folders through a controlled administrator reload.

The File 22 `social_publication` workflow keeps File 21 as the only native post owner. Only exact drafts are mutable; previews are HMAC-signed and expire at request time; idempotency uses hashed markers, atomic processing and execution leases, bounded retention, automatic reconciliation and aggregate-only Administrator repair. Structured Clinical Case, Research, Poll, upload, Video and PDF workflows remain on their complete native-owner routes.

Editorial News gates remain disabled until an authorized administrator completes the Activation Wizard on staging. Automatic publication and automatic File 04 migration remain disabled. Existing posts, pages, users, media, comments, URLs and legacy sources are preserved.

== Installation ==

1. Take a verified files-and-database backup.
2. Install the exact package 1.0.5 candidate on Hostinger staging first.
3. Confirm that WordPress displays Version 1.0.5.
4. If Safe Boot is recorded, select Retry Safe Boot after replacement.
5. Visit one WordPress administration page so bounded File 21 capability reconciliation runs.
6. Confirm System Check, duplicate-copy status, File 20 native-slot status, authenticated REST status/schema routes, File 22 workflow health, File 23 provider health and File 26 connector registration.
7. File 26 must keep the File 21 connector fail-closed until its own contract-tested/shadow/approved/active governance gates are completed.
8. Complete the Activation Wizard and enable Editorial News gates only after staging acceptance.
9. Flush scheduled rewrite rules and verify /news/; legacy /sabri-news/ and /blog/ redirect only after News activation.
10. Run fresh-install, upgrade, rollback, permissions, idempotency recovery, preview expiry, pagination, mobile, accessibility, cache, File 22/23/26 integration and visual acceptance before production deployment.

== Safety ==

Public GET requests do not perform recovery migrations or post-meta normalization. Explicit recovery is capability-checked, nonce-protected, bounded, audited and repeatable. No draft, pending, private, password-protected, rejected, removed or archived post is automatically published. File 22 workflow recovery output contains no raw key, native reference, post content or patient data. File 26 receives derivative public projections rather than canonical File 21 source ownership.

Donation/payment status does not create an undisclosed organic ranking advantage or reduce core access. File 21 does not create a second global search backend, Shell, Composer, Publishing Dashboard or visual design system.

== Changelog ==

= 1.0.5 =
* Reconciled File 21 with the current consolidated governing plan, recovered Founder directives, Continuous Value / Top-20 superset and File 21 encyclopedic master plan.
* Added Feed user agency, explainability, reduced personalization, bounded hide/snooze controls and healthy-use stopping points.
* Added File 17 relationship ownership bridge, saved collections/tags/private notes/export and current-wave comment experience improvements.
* Disabled authenticated Feed transient caching and preserved privacy/click-time revalidation boundaries.
* Preserved exactly fourteen canonical Home controls, central green identity and neutral organic ranking without Founder/donor/commercial advantage.
* Preserved File 23 fail-closed direct writes and File 26 proposed-by-default connector lifecycle.
* Promoted package identity to 1.0.5 while preserving runtime/API 1.0.3 and schema 1.0.0.

= 1.0.4 =
* Corrected the File 22 Founder/Administrator creation gateway while preserving File 21 native final authorization.
* Preserved File 23 Adapter Contract 2.0.0 projections and fail-closed direct writes.
* Added the canonical File 26 `file21-publication` public projection connector with proposed-by-default lifecycle, bounded shadow reindex, native visibility revalidation and deletion tombstones.
* Kept File 26 organic ranking free of donor/payment/paid-promotion/Founder-favoritism inputs from File 21.
* Added governing-plan regression tests, File 26 connector contract tests and exact-head workflow enforcement.
* Promoted package identity to 1.0.4 while preserving runtime/API 1.0.3 and schema 1.0.0.

= 1.0.3.3 =
* Registered bounded File 21 inventory, workspace, review and calendar projections with File 23.
* Preserved File 21 native ownership and denied direct File 23 writes.

= 1.0.3.2 =
* Required subject-bound File 00 contract 1.1.2+ assertions for privileged File 21 social publishing actions.
* Required current-session two-factor assurance and exact current-actor capability checks.
* Removed stale role, verification-meta, trusted-publisher-meta and raw profile-data fallbacks.
* Revalidated Founder and verified-Doctor query IDs through current File 00/File 03 truth.

= 1.0.3.1 =
* Corrected Social Composer role precedence for Founder, Administrator and Doctor authority.
* Preserved Student-only, Patient-only, Subscriber-only, Safe Mode and Emergency Disable denials.

= 1.0.3 =
* Corrected File 22 draft-state safety, signed preview expiry, idempotency recovery, concurrency and retention.
* Removed public-request recovery writes.
* Added Home and News native-slot contracts and guarded compatibility mounting.
* Added canonical /news/ handling and legacy News redirects after gate activation.
* Preserved all ten Home rows with explicit unavailable states.
* Added Breaking News, pagination, Safe Boot and duplicate-folder safeguards.

= 1.0.2 =
* Added public Home visibility recovery, Profile Timeline, legacy Feed replacement and Safe Boot recursion correction.

= 1.0.1 =
* Added comprehensive architecture harmonization and corrective publishing workflows.

= 1.0.0 =
* Added the initial foundation, Home Feed, Composer, social interactions and Editorial News phases.
