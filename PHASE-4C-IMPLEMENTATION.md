# Phase 4C Implementation — Public News, Discovery, and Home Feed Integration

Target development line: `1.2.0`

Branch: `build/phase-4c-public-news-1.2.0`

Status: **A-to-Z implementation and code-gap audit completed on an isolated Draft PR; exact-head source/package matrices and mandatory one-hour acceptance govern final QA completion; all public gates remain disabled by default**

## Delivered public runtime

- Fail-closed public Editorial News policy with Emergency Disable precedence.
- Exact canonical routes for News archive, single article, and section/topic/country/region/type archives.
- Inclusion-only article, card, approved-correction, and retraction projections.
- Last-approved public snapshot that prevents private `correction-pending` title, body, summary, media, taxonomy, author, or reviewer changes from leaking.
- Private pending-correction payload with explicit approved promotion to the canonical public record.
- Complete bounded News landing: Featured Story, Latest News, Editor’s Picks, Research News, Classical Homeopathy, Public Health, Homeopathy Education, Platform News, Founder Updates, Worldwide Health Developments, and Recently Updated/Corrected.
- Bounded archive/search filters for keyword, section, topic, country, region, article type, strict date range, approved author, approved institution, research, corrected, and retracted notices.
- Controlled-term validation for every public taxonomy filter.
- Complete single article presentation with approved author/institution, optionally approved reviewing editor, published/updated times, reading time, image attribution, correction notice, disclaimer, conflict disclosure, all taxonomy links, canonical sharing, related News, and allowed Phase 3 interactions.
- Dedicated normalized Home Feed News items with `global_key = news:{id}`, canonical links, deterministic insertion, cross-type deduplication, and actual-card-only News asset loading.
- Strict GET-only public REST collection and single routes with typed schemas, bounded values, unknown-parameter rejection, shared public projections, safe headers, and no writes.
- Versioned, site/language/gate/emergency-aware public cache with plugin-owned invalidation and purge boundaries.
- Responsive, keyboard-accessible, high-zoom, reduced-motion, forced-colors, and progressive-enhancement public News assets.
- Protocol-level protection against unsafe public author, institution, media, taxonomy, body-link, and body-image URL schemes.

## Closed implementation gaps

The final code-gap audit explicitly closed:

1. private `correction-pending` leakage through canonical post fields;
2. incomplete multi-section News landing composition;
3. missing single-article editor/update/taxonomy/interaction/share presentation;
4. non-frozen public error codes;
5. partial public filter form and incomplete controlled-term checks;
6. permissive REST schemas or unknown request parameters;
7. unapproved public author/institution identity exposure;
8. incomplete cache dimensions and invalidation;
9. gate-wide rather than rendered-card-aware News assets;
10. unsafe URL protocols in public identity, media, taxonomy, or rich article HTML;
11. incomplete security-negative, UI, and WordPress Playground coverage;
12. absence of permanent exact-head and mandatory one-hour Phase 4C workflows.

## Frozen public states

Normal public promotion permits only:

```text
published
updated
correction-pending (last approved public snapshot only)
corrected
```

`retracted` exposes only an approved public accountability notice. Draft, needs-sources, editorial-review, fact-check, medical-review, ready-for-publication, scheduled, archived, malformed, foreign, or missing objects fail closed.

## Frozen public error codes

```text
editorial_news_disabled
public_news_not_found
public_news_filter_invalid
public_news_page_invalid
public_news_taxonomy_invalid
public_news_retracted
public_news_query_failed
```

## Public REST boundary

```text
GET /sabri-home-news-feed/v1/news
GET /sabri-home-news-feed/v1/news/{id}
```

No public create, update, delete, publish, schedule, correction, retraction, source, submission, or reviewer endpoint is opened by Phase 4C.

## Permanent acceptance workflows

```text
.github/workflows/phase4c-public-news-tests.yml
.github/workflows/phase4c-one-hour-visible-qa.yml
```

Required retained artifacts:

```text
sabri-phase4c-PUBLIC-NEWS-QA-PASSED-{exact-head-sha}
sabri-phase4c-ONE-HOUR-VISIBLE-QA-PASSED-{exact-head-sha}
```

Temporary overlay, diagnostic, transport, and correction machinery is not part of the completed implementation.

## Acceptance coverage

- Phase 4C behavior and contract tests.
- Dedicated security-negative and privacy tests.
- UI/accessibility/completeness tests.
- Phase 4A and Phase 4B regressions.
- Phase 2 and Phase 3 core regressions and static checks.
- Source WordPress matrices: WordPress latest/PHP 8.3 and WordPress 6.8/PHP 8.1.
- Immutable ZIP SHA-256 and required-file verification.
- Packaged WordPress matrices on both environments.
- Observable exact-head one-hour QA: at least 3,900 seconds, exactly 13 complete cycles, final packaged matrices, initial/final tracked-manifest comparison, and retained evidence artifact.

## Frozen safety boundaries

- Plugin version remains `1.0.0`.
- Schema version remains `1.0.0`.
- `Phase4Contracts::CHECKPOINT` remains `4A`.
- Every Phase 4 public feature gate remains disabled by default.
- Automatic publication remains disabled.
- No write to `main`, merge, version promotion, Hostinger staging activation, or live deployment is authorized by this implementation.
- Phase 4D–4I remain separate later checkpoints.
