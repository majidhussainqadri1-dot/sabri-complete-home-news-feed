# Phase 4C Implementation — Public News and Home Feed Integration

Target development line: `1.2.0`

Branch: `build/phase-4c-public-news-1.2.0`

Status: **implemented on an isolated Draft PR; all public gates remain disabled by default; automated and staging acceptance remain required**

## Delivered runtime

- Fail-closed public visibility policy for Editorial News.
- Inclusion-only public article, card, and retraction projections.
- Bounded public collection and single query service.
- Exact `/news/` archive, canonical single, and taxonomy routes.
- Public archive, single, card, empty-state, and retraction templates.
- Keyword, section, topic, country, region, type, date, author, research, corrected, and retracted filters.
- Dedicated normalized Home Feed News items with `global_key = news:{id}`.
- Cross-type deduplication and deterministic bounded feed insertion.
- GET-only public REST collection and single routes.
- Versioned public News caching and feed-cache invalidation.
- Public News CSS and progressive JavaScript with keyboard, focus, responsive, zoom, and reduced-motion foundations.
- Autoloader and coordinator registration for the new public runtime.
- Release-package required-file enforcement for Phase 4C.

## Public projection rules

Normal archive promotion permits only the authoritative workflow states:

```text
published
updated
correction-pending
corrected
```

`correction-pending` exposes only the last approved public article version and does not expose the private pending decision. `retracted` is excluded from normal promotion and may expose only the approved public retraction notice. Draft, review, fact-check, medical-review, ready, scheduled, archived, malformed, foreign, or missing states fail closed.

Public projections are built by inclusion. They do not serialize private editorial notes, reviewer deliberations, source-confidence notes, account emails, non-public user identifiers, preview data, nonces, or hidden retracted content.

## Routes

```text
/news/
/news/{article-slug}/
/news/section/{slug}/
/news/topic/{slug}/
/news/country/{slug}/
/news/region/{slug}/
/news/type/{slug}/
```

Routes are registered only when `editorial_news_enabled = 1`. The default remains `0`. Disabling the gate closes the public route, REST, query, rendering, and feed-integration surfaces without deleting data.

## Home Feed boundary

Editorial News remains a separate content class. It is converted to a normalized feed item and never converted into an ordinary WordPress community post. Ordinary identities use `post:{id}`; Editorial News uses `news:{id}`. Each supported feed page receives at most one News item, preserving the configured total page size.

## REST boundary

Phase 4C exposes only:

```text
GET /sabri-home-news-feed/v1/news
GET /sabri-home-news-feed/v1/news/{id}
```

No Phase 4C public write, publish, schedule, correction, retraction, source, submission, or reviewer endpoint is opened.

## Automated acceptance

The Phase 4C test matrix includes:

- public behavior, privacy, strict validation, routing, cache, REST, and feed-integration tests;
- source-level UI and security completeness checks;
- Phase 4A and Phase 4B regressions;
- core Phase 2 and Phase 3 regressions;
- source WordPress Playground on WordPress latest/PHP 8.3 and WordPress 6.8/PHP 8.1;
- immutable ZIP checksum and required-structure verification;
- packaged WordPress Playground on both matrices;
- exact-head acceptance artifact;
- a separate observable 3,900-second, 13-cycle QA with final packaged matrices.

## Frozen boundaries

- Plugin version remains `1.0.0`.
- Schema version remains `1.0.0`.
- `Phase4Contracts::CHECKPOINT` remains `4A`.
- All Phase 4 gates remain disabled by default.
- No automatic publication is enabled.
- Sources/review ledgers, submissions, Breaking News administration, correction administration, complete SEO/RSS/sitemap/translation, version promotion, Hostinger staging, and live deployment remain later checkpoints.
