=== Sabri Complete Home and News Feed ===
Contributors: sabri
Tags: home feed, editorial news, profile timeline, moderation, homeopathy
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 1.0.4
License: GPLv2 or later
Text Domain: sabri-complete-home-news-feed

Canonical public Home, social Feed, Profile Timeline, Editorial News, publishing, migration, safety, and integration runtime for the Sabri Social Homeopathy Platform.

== Description ==

Version 1.0.4 corrects mixed-role authority precedence in the public Social Composer. A legacy Founder, Administrator, verified Doctor, or unverified Doctor may also carry an older Subscriber/Patient role; that lower-authority role no longer cancels the explicit institutional or professional creation grant. Student-only, Patient-only, and Subscriber-only accounts remain denied, and Safe Mode/Emergency Disable remain controlling.

Version 1.0.3 remains the production-rejection corrective foundation. It removes front-end database recovery writes, adds native News-slot rendering and legacy News-page handling, keeps exactly ten Home rows observable, guards Breaking News to the main Home/News loop, applies public-post eligibility before pagination, reports File 20 native-slot readiness truthfully, exposes authenticated Safe Boot diagnostics, and resolves duplicate File 21 folders through a controlled administrator reload.

The File 22 `social_publication` workflow keeps File 21 as the only native post owner. Only exact drafts are mutable; previews are HMAC-signed and expire at request time; idempotency uses hashed markers, atomic processing and execution leases, bounded retention, automatic reconciliation, and aggregate-only Administrator repair. Structured Clinical Case, Research, Poll, upload, Video, and PDF workflows remain on their complete native owner routes.

Editorial News gates remain disabled until an authorized administrator completes the Activation Wizard on staging. Automatic publication and automatic File 04 migration remain disabled. Existing posts, pages, users, media, comments, URLs, and legacy sources are preserved.

== Installation ==

1. Take a verified files-and-database backup.
2. Install on Hostinger staging first.
3. Replace the existing plugin with the exact 1.0.4 ZIP and confirm that WordPress displays Version 1.0.4.
4. If Safe Boot is recorded, select Retry Safe Boot after replacement.
5. Visit one WordPress administration page so the bounded File 21 capability reconciliation runs.
6. Confirm System Check, duplicate-copy status, File 20 native-slot status, authenticated REST status/schema routes, and File 22 workflow health.
7. Complete the Activation Wizard and enable Editorial News gates only after staging acceptance.
8. Flush scheduled rewrite rules and verify /news/; legacy /sabri-news/ and /blog/ redirect only after News activation.
9. Run fresh-install, upgrade, rollback, mixed-role permissions, idempotency recovery, preview expiry, pagination, mobile, accessibility, cache, and visual acceptance before production deployment.

== Safety ==

Public GET requests do not perform recovery migrations or post-meta normalization. Explicit recovery is capability-checked, nonce-protected, bounded, audited, and repeatable. No draft, pending, private, password-protected, rejected, removed, or archived post is automatically published. File 22 workflow recovery output contains no raw key, native reference, post content, or patient data.

== Changelog ==

= 1.0.4 =
* Corrected Social Composer role precedence so Founder, Administrator, verified Doctor, and unverified Doctor authority is not cancelled by a legacy Subscriber/Patient role.
* Preserved Student-only, Patient-only, Subscriber-only, Safe Mode, and Emergency Disable denials.
* Added mixed-role Create, publish, and review regression tests.
* Retained the File 21-owned `sabri_feed_create_posts` reconciliation required by File 22.

= 1.0.3 =
* Corrected File 22 draft-state safety, signed preview expiry, idempotency recovery, concurrency, retention, stale-lock cleanup, and actual Workflow Coordinator integration tests.
* Removed public-request recovery writes.
* Added Home and News native-slot contracts and guarded compatibility mounting.
* Added canonical /news/ handling and legacy News redirects after gate activation.
* Preserved all ten Home rows with explicit unavailable states.
* Added Breaking News main-loop, context, and render-once guards.
* Added SQL-stage visibility/review clauses before pagination, with object-level defense.
* Replaced false Connected diagnostics with explicit native-slot audit.
* Added authenticated Safe Boot /status and /schema diagnostics.
* Unified package/version identity and improved duplicate-folder recovery.

= 1.0.2 =
* Added public Home visibility recovery, Profile Timeline, legacy Feed replacement, and Safe Boot recursion correction.

= 1.0.1 =
* Added comprehensive architecture harmonization and corrective publishing workflows.

= 1.0.0 =
* Added the initial foundation, Home Feed, Composer, social interactions, and Editorial News phases.
