# Changelog

## 1.0.3 - Production-Rejection Corrective Candidate

- Corrected File 22 workflow safety: only exact native drafts are mutable, so pending-review, scheduled, published, and rejected posts cannot be demoted by resume or preview.
- Added HMAC-signed, subject-bound preview URLs with request-time ten-minute expiry enforcement and no-cache 403 denial.
- Added recoverable native idempotency markers, atomic per-key execution locks, option-persistence reconciliation, bounded 15-minute processing leases, 30-day completed retention, one seven-day recoverable interval, daily cleanup, and nonce-protected aggregate-only Administrator repair controls.
- Added stale execution-lock cleanup and destructive-uninstall cleanup for workflow records and markers under the existing explicit retention policy.
- Added capability-aware File 22 schema choices and actual integration testing against the exact reviewed File 22 Phase 22E Workflow Coordinator.
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
