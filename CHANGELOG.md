# Changelog

## 1.0.6 — Forty-Round Sequential Corrective Review — 2026-08-07

- Completed forty sequential review/fix cycles against the governing central plans and File 21 specification; every defect-bearing round was corrected before the next round.
- Corrected a post-merge PHP syntax defect that caused PHP 8.1/8.3 exact-head CI to fail at lint.
- Hardened File 17 relationship consumption with cursor pagination and fail-closed canonical-owner semantics.
- Corrected stale release/contract tests and documentation drift discovered by fresh regressions.
- Removed string-built `innerHTML` from the Newsroom media preview and now construct the preview DOM safely.
- Added an executable forty-round review contract and ledger.
- Package identity advances to `1.0.6`; runtime/API remains `1.0.3` and schema remains `1.0.0`.
- Staging, live deployment and operational acceptance remain separate external gates.

## 1.0.5 — Four-Plan Current-Wave Reconciliation — 2026-08-07

- Preserved the exact fourteen canonical Home controls and ten truthful provider-backed Home rows while adding Following as an auxiliary user-choice Feed mode.
- Added explainable Feed agency: Why am I seeing this, reduced personalization, Not interested, author/topic snooze or reduction, and reset controls.
- Consumed File 17 canonical relationship/block state without direct File 17 storage writes and disabled authenticated Feed transient caching to avoid stale viewer-specific privacy state.
- Added saved collections/folders with private notes, tags and bounded export on the existing File 21 saves truth, plus deterministic Oldest/Newest comments, visible-parent context and textual mentions.
- Removed Founder/donor/payment/paid-promotion organic ranking advantages while preserving File 26 as canonical global Search/Discovery/Recommendations/Ranking owner.
- Enforced current green presentation and 44px accessibility/keyboard/reduced-motion/forced-colors safeguards.
- Added executable four-plan current-wave regression gates and deterministic package identity 1.0.5; runtime/API remains 1.0.3 and schema remains 1.0.0.
- Hostinger staging, installed-companion acceptance, backup/restore, rollback rehearsal, live deployment and operational acceptance remain external gates.

## 1.0.4 — Final Governing-Plan and Cross-Owner Reconciliation — 2026-08-07

- Corrected the File 21 → File 22 creation gateway so File 22 performs only a coarse authenticated prefilter while File 21 native `can_create()` remains the final File 00-aware authorization owner.
- Preserved File 23 Adapter Contract 2.0.0 inventory, workspace, review and calendar projections with direct File 23 writes fail-closed.
- Added canonical File 26 Search/Discovery/Recommendations/Ranking integration through the `file21-publication` connector.
- Kept the File 26 connector `proposed` by default; File 21 cannot self-promote or self-activate global Search/Ranking.
- Added bounded shadow-reindex batches, public-only projection documents, click-time/native visibility revalidation and source restriction/deletion tombstones.
- Kept global File 26 ranking neutral from File 21 by sending zero authority/popularity weighting and no donation, payment, paid-promotion, purchased-engagement or Founder-favoritism signal.
- Retained green File 21 public Feed action/accent presentation and preserved File 25 as the canonical visual/design-system owner and File 20 as the Shell owner.
- Added `run-file21-file26-connector-contract-tests.php` and `run-file21-four-plan-final-completion-tests.php`; strengthened File 22 Founder gateway, File 23 provider, File 26 ownership and exact-head release gates.
- Promoted WordPress package identity to `1.0.4` while preserving File 21 runtime/API `1.0.3` and schema `1.0.0`; no database migration is introduced.
- Canonical deterministic candidate is `21-sabri-complete-home-news-feed-1.0.4-CONTROLLED-STAGING-CANDIDATE.zip`; Hostinger staging, live deployment and operational acceptance remain separate external gates.

## 1.0.3.3 — File 23 Provider Integration

- Register bounded File 21 inventory, workspace, review and calendar projections with File 23.
- Preserve File 21 native ownership and deny direct File 23 writes.
- Bind acceptance evidence to the new package/provider version.
- Add load-order, authorization, privacy and no-duplicate-backend regression gates.

## 1.0.3.2 Public Rendering R3 — 2026-08-04

- Normalize raw Markdown heading markers in the queried singular title without altering stored content.
- Render conservative bold and italic Markdown in legacy public articles while preserving code/pre blocks and existing HTML.
- Add `sabri-hnf-content-integrity-single` for non-owned legacy articles so File 20 can recover the correct theme content column without false ownership.
- Load containment CSS independently and add an explicit cache-busting asset identity.
- Preserve File 21 ownership, taxonomy, authorization, privacy and runtime/API boundaries.

## 1.0.3.2 - File 00 Authorization and Public Projection Integrity

### File 00 authority hardening
- Requires subject-bound File 00 contract 1.1.2 for all privileged and private writes.
- Denies stale File 21 capabilities, suspended identities, missing current-session 2FA, cross-subject assertions, and untrusted public projection fields.
- Applies the same fail-closed identity gate to social interactions, Newsroom, Phase 5 REST, diagnostics, migration, repair, rollback, and administrator capability recovery.

- Replaced legacy role/meta publishing trust with subject-bound File 00 `SMC_Contracts::assertions()` contract `1.1.2` or later.
- Made File 00 denial, suspension, expired evidence, appeal review, erasure, invalid application, missing 2FA setup, and missing current-session challenge fail closed.
- Required the exact current actor and matching File 21 capability for create, publish, submit-for-review, moderation, and institutional content-type access.
- Prevented File 22, a role slug, or a stale capability from elevating a principal denied by File 00.
- Removed raw Membership and legacy verification metadata from public author projections; only File 03 approved fields may populate professional data.
- Revalidated Founder and verified-Doctor query candidates through current canonical contracts.
- Preserved runtime/API `1.0.3` and schema `1.0.0`; this is a package-level corrective release with no database migration.

## 1.0.3.1 - Mixed-Role Authority Hotfix

- Corrected the public Social Composer authorization order so an explicit Founder, Administrator, verified Doctor, unverified Doctor, or plugin-owned `sabri_feed_create_posts` grant is evaluated before legacy Student/Patient/Subscriber-role denial.
- Preserved File 21 runtime and cross-plugin API compatibility at `1.0.3`; WordPress package identity is `1.0.3.1` for safe replacement and rollback recognition.
- Preserved Safe Mode and Emergency Disable as absolute creation gates.
- Preserved denial for Student-only, Patient-only, Subscriber-only, and unrelated Editorial-only accounts.
- Corrected immediate-publish precedence for Founder/Administrator accounts carrying a lower-authority legacy role.
- Corrected review-submission precedence for verified or unverified Doctors carrying a legacy Subscriber/Patient role.
- Added exact mixed-role regressions for Create, publish, submit-for-review, and Emergency Disable.
- Retained the administration-only File 21 capability reconciliation introduced for in-place ZIP replacement.

## 1.0.3 - Production-Rejection Corrective Candidate

- Reconciled the File 21-owned `sabri_feed_create_posts` capability on administrator-only `admin_init` after in-place ZIP replacement, so File 22 can expose the Social Publication Create card without weakening the native capability boundary.
- Corrected File 22 workflow safety: only exact native drafts are mutable, so pending-review, scheduled, published, and rejected posts cannot be demoted by resume or preview.
- Added HMAC-signed, subject-bound preview URLs with request-time ten-minute expiry enforcement and no-cache 403 denial.
- Added recoverable native idempotency markers, atomic per-key execution locks, option-persistence reconciliation, bounded 15-minute processing leases, 30-day completed retention, one seven-day recoverable interval, daily cleanup, and nonce-protected aggregate-only Administrator repair controls.
- Added stale execution-lock cleanup and destructive-uninstall cleanup for workflow records and markers under the existing explicit retention policy.
- Added `UniversalComposerSubjectSchemaAdapter`: static `schema()` is role-neutral, while interactive `schema_for_user()` removes Founder Update and Platform News for non-Founder/non-Administrator subjects.
- Updated the bridge to require exact File 22 Adapter, Workflow, Subject Schema, and public API owner/function-ownership contracts before registration or fallback removal.
- Added real File 22 Coordinator tests for role-neutral health, Doctor/Founder schema separation, schema-bound draft/preview/submit/status/canonical URL, and bounded maintenance.
- Added capability-aware File 22 schema choices and actual integration testing against the exact corrected File 22 runtime.
- Extended Sabri Universal Post Composer interoperability with a guarded File 22 `Workflow_Adapter`: File 21 owns direct native draft creation, validation, signed preview, idempotent submission, status, and subject-aware canonical URL resolution without creating any File 22 shadow record. The direct schema is deliberately text-first; structured Clinical Case, Research, Poll, upload, Video, and PDF workflows remain on their complete native owner routes.
- Added fail-soft Sabri Universal Post Composer interoperability: File 21 registers a versioned `social_publication` adapter, retains permanent ownership and native permissions, prefers the universal Create page when the complete File 20/File 22 gateway is available, and preserves its existing `/create-post/` fallback when that gateway is absent.
- Restored the public **Create Post** action on Home and News, supplied the Unified Shell Create destination, and added the canonical `/create-post/` public Composer route with login, permission, duplicate-render and one-shot rewrite safeguards.
- Restored the News Composer posting option after ZIP replacement by reconciling plugin-owned Administrator/Founder Editorial News capabilities on `admin_init`, preserving the canonical submenu, and adding a prominent **Create Editorial News** action on the Newsroom screen.
- Corrected the Integrations diagnostics Safe Boot fatal by restoring the side-effect-free `proposed_future_integrations()` contract and guarding the administration view against mixed-version method absence.
- Restored secure public visibility for Founder/Administrator Editorial News: explicit single and bulk publication now set the canonical workflow/status, create the approved public snapshot, open `/news/`, flush rewrites once, and purge File 21 News caches.
- Removed all automatic recovery writes from public GET requests; recovery is now an explicit nonce/capability-protected administrator operation.
- Added File 21 registration and rendering for `sabri_shell_news_main`, plus guarded Home/News fallback mounts.
- Added canonical `/news/` request support and safe 301 retirement of `/sabri-news/` and `/blog/` only after Editorial News activation.
- Rendered exactly ten governing Home rows, including honest unavailable states when companion providers are absent.
- Guarded Breaking News by public Home/News context, main query, and one render per request.
- Added pre-query visibility and review-state constraints so `found_posts`, page counts, and pagination match public eligibility.
- Prevented recursive Home Feed rendering while WordPress applies `the_content` to Feed cards.
- Replaced unconditional Shell “Connected” status with explicit five-slot advertisement/audit.
- Kept authenticated REST status/schema diagnostics available while Safe Boot pauses the full runtime.
- Added highest-version/canonical duplicate-folder selection and a controlled administrator reload after deactivation.
- Unified runtime, readme, changelog, builders, package name, report, and manifest on 1.0.3 while preserving schema 1.0.0.
- Disabled the three former one-hour/3,900-second soak workflows at the repository owner's direction; retained workflow files now perform fast, truthful disabled-state confirmation only.

## 1.0.2 - Public Visibility and Safe Boot Corrections

- Added public Home visibility recovery, Profile Timeline, legacy Feed replacement, Safe Boot recursion protection, author-scoped privileged publishing, and bounded explicit metadata normalization.

## 1.0.1 - Comprehensive Harmonization

- Added canonical identity, Home controls, ranking, File 04 migration/rollback, integration registry, Search providers, Profile Timeline, Activation Wizard, package QA, and release diagnostics.

## 1.0.0 - Initial Foundation through Editorial News
