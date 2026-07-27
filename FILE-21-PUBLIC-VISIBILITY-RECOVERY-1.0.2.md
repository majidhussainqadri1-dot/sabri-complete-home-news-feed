# File 21 Public Visibility Recovery — Release 1.0.2

## Governing defect

File 21 version 1.0.1 could be installed and active while no new File 21 surface appeared publicly. The failure was caused by a combined activation and mounting deadlock:

1. the identifiable Home surface and Profile Timeline were disabled by default;
2. a detected legacy Feed shortcode caused the duplicate guard to refuse mounting;
3. the installed File 20 release did not yet expose the preferred native Home slot;
4. the remaining fallback depended on a static front-page content loop;
5. existing published Founder or trusted-author posts with blank File 21 review metadata were excluded from the new query;
6. the rendered filter navigation did not use the final fourteen-control Home specification.

## Corrective outcome

Release 1.0.2 adds a bounded and non-destructive public-surface recovery:

- read-only Home and Profile Timeline surfaces become observable when no administrator-completed Wizard decision exists;
- detected legacy Feed shortcodes are replaced only at render time, with saved Page content unchanged;
- File 21 uses the native File 20 slot when present, the static front-page content loop when available, the posts-index loop when necessary, and a guarded final public fallback for themes/builders that expose none of those positions;
- only one complete File 21 Home surface may render per request;
- the public marker reports `File 21 public surface is active` and records the mount source;
- the final fourteen-item Home Control Bar and ten Home rows are rendered by the same canonical surface;
- Founder posts are selected by canonical Founder identity rather than only by a `founder-update` content type;
- password-protected posts are excluded;
- already-published Founder, Administrator, and explicitly trusted verified-Doctor posts with blank File 21 metadata receive only the missing public visibility/review metadata;
- drafts are not published, News gates are not enabled, automatic publication is not enabled, and File 04 migration is not run.

## Safety boundaries

The recovery MUST NOT:

- publish draft, pending, rejected, removed, archived, limited, private, or password-protected content;
- enable Editorial News, Breaking News, corrections, RSS, schema, sitemap, previews, notifications, or submission gates;
- run legacy File 04 migration;
- delete or rewrite legacy source content;
- mutate the saved front-page shortcode content;
- insert global navigation or replace the File 20 Shell;
- claim live visual acceptance before staging or production evidence exists.

## Administrator control

A completed Activation Wizard remains authoritative and is never silently overridden. Administrators receive an explicit recovery action when File 21 is active but its public Home surface is still not observable. The recovery report and audit event include the before/after component state, duplicate diagnostics, bounded post normalization, and confirmation that News gates, publication policy, and legacy migration were unchanged.

## Plan conformance

This release directly closes the public-observability defect against the final File 21 master specification:

- complete Home surface;
- fourteen approved controls;
- ten approved rows;
- canonical Founder/Doctor authority;
- Profile Timeline bridge;
- duplicate Feed protection without database mutation;
- truthful companion fallbacks;
- fail-closed Editorial News and migration systems;
- observable diagnostics and manual recovery;
- File 20 ownership preserved.

The following remain external release gates rather than hidden code claims:

- installation of a future File 20 release with the preferred native slots;
- controlled WordPress/Hostinger staging installation;
- real legacy data dry-run and selected migration;
- mobile, browser, accessibility, and visual screenshots;
- gate-by-gate Editorial News activation;
- production monitoring and Founder sign-off.

## Version contract

- Plugin runtime: `1.0.2`
- Database schema: `1.0.0`
- Migration behavior: additive and source-preserving
- Public read recovery: automatic only when no explicit completed Wizard decision exists
- Public write and Editorial News gates: unchanged and fail-closed
