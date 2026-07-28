# Changelog

## 1.0.3 - Production-Rejection Corrective Candidate

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

- Added independent plugin bootstrap, settings, database, Safe Mode, Home Feed, Composer, interactions, Editorial News, Newsroom, public routes, moderation, privacy, migration, and release tooling foundations.
