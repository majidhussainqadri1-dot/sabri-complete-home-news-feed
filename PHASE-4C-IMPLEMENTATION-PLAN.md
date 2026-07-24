# Phase 4C Implementation Plan — Public News, Discovery, and Home Feed Integration

Target development line: `1.2.0`

Branch: `build/phase-4c-public-news-1.2.0`

Base commit: `e2b595de9f3b4e6cc7c41bfb342ded02aa39015f`

Dependencies:

- Phase 4A content model merged and accepted.
- Phase 4B Newsroom/composer/workflow merged and accepted.
- `PHASE-4-CONTRACTS.md`.
- `PHASE-4-CONTRACTS-ADDENDUM-1.md`.
- `PHASE-4-ARCHITECTURE.md`.
- `PHASE-4-SECURITY-PRIVACY.md`.
- `PHASE-4-EDITORIAL-POLICY.md`.

Status: **implementation plan only — no merge, version promotion, staging activation, or live deployment authorization**.

---

## 1. Objective

Phase 4C creates the first controlled public Editorial News experience. It exposes only approved public projections of Editorial News, provides canonical News routes and bounded discovery, introduces dedicated News cards into the existing Home Feed, and preserves strict separation between Editorial News and ordinary community posts.

The central invariant is:

> A published Editorial News article may appear in the Home Feed as a dedicated News card, but the feed card always links to the canonical News article and never converts ordinary community content into Editorial News.

Phase 4C must remain feature-gated, fail closed, reversible, cache-safe, accessible, and compatible with existing Phase 2 and Phase 3 behavior.

---

## 2. Exact Phase 4C scope

Phase 4C includes:

1. Public News runtime coordinator.
2. Canonical public News archive route.
3. Canonical single News article route.
4. Section, topic, country, region, and type archive routes.
5. Public-only News query service.
6. Separate public article, card, taxonomy, and retraction projections.
7. Bounded keyword search and allow-listed filters.
8. Pagination and deterministic ordering.
9. Dedicated News card templates.
10. Home Feed integration through normalized feed items.
11. Public read-only REST endpoints required by the archive, single page, and progressive enhancement.
12. Safe empty, disabled, not-found, retracted, and error states.
13. Public cache keys, invalidation hooks, and privacy isolation.
14. Responsive and accessible CSS/JavaScript for public News only.
15. Automated source, packaged, regression, security, accessibility-contract, and exact-head acceptance tests.

---

## 3. Explicit exclusions from Phase 4C

The following remain outside this checkpoint:

- source-registry administration and full evidence ledger implementation — Phase 4D;
- normalized editorial/medical/fact-check review ledgers beyond the already merged workflow foundations — Phase 4D;
- external doctor/contributor submission portal — Phase 4E;
- Breaking News public strip and operational breaking controls — Phase 4F;
- material correction and retraction administration workflows — Phase 4F;
- full schema, News sitemap, RSS, social metadata, translation, and `hreflang` adapters — Phase 4G;
- final security hardening, load testing, rate-limit tuning, observability, and privacy export/erase review — Phase 4H;
- version promotion, Hostinger staging acceptance, release candidate, merge approval, and live deployment — Phase 4I.

Phase 4C may render already-existing approved public correction or retraction state safely, but it must not implement the later correction-management workflow prematurely.

---

## 4. Frozen public routes

Phase 4C implements these routes:

```text
/news/
/news/{article-slug}/
/news/section/{slug}/
/news/topic/{slug}/
/news/country/{slug}/
/news/region/{slug}/
/news/type/{slug}/
```

Rules:

- `/news/{article-slug}/` is the canonical article route.
- Feed cards and archive cards always link to the canonical single route.
- Unknown, malformed, disabled, private, scheduled, or otherwise non-public objects must not be enumerable.
- Invalid objects return a safe 404 response unless an authenticated authorized preview flow applies.
- Retracted objects expose only the approved public retraction projection.
- Rewrite flushing occurs only on activation or a versioned migration, never during ordinary requests.
- `/news/feed/`, section feeds, and `/news-sitemap.xml` remain separately gated for Phase 4G.

---

## 5. Feature-gate behavior

Primary gate:

```text
editorial_news_enabled
```

Frozen default remains `0`.

When disabled:

- public News routes fail closed;
- public News REST reads fail closed;
- News cards are absent from the Home Feed;
- no public News assets are enqueued;
- no News data is deleted or modified;
- authorized Newsroom administration remains governed by its existing private policy;
- ordinary Home Feed behavior remains unchanged.

Emergency Disable overrides all public and write behavior.

Phase 4C must not enable any gate automatically during activation, upgrade, tests, package build, merge, or deployment.

---

## 6. Public visibility matrix

Only explicitly public article states may enter public queries.

| Domain state | Archive/search | Single route | Home Feed | Public REST |
|---|---:|---:|---:|---:|
| `draft` | No | No | No | No |
| `needs-sources` | No | No | No | No |
| `editorial-review` | No | No | No | No |
| `fact-check` | No | No | No | No |
| `medical-review` | No | No | No | No |
| `ready-for-publication` | No | No | No | No |
| `scheduled` | No | No | No | No |
| `published` | Yes | Yes | Yes | Yes |
| `updated` | Yes | Yes | Yes | Yes |
| `correction-pending` | Existing public version only; no pending private detail | Existing public version only | Existing public version only | Existing public version only |
| `corrected` | Yes with visible correction state | Yes with notice when available | Yes | Yes |
| `retracted` | Approved retraction result only | Approved retraction notice only | No normal promotion | Approved retraction projection only |
| `archived` | No normal archive promotion unless an explicit safe archive query is later approved | Safe canonical archive notice or 404 according to frozen policy | No | No normal collection output |

Core WordPress status may support storage, but Phase 4 domain workflow metadata remains the authoritative public-state decision.

---

## 7. Public projection contract

### 7.1 Article projection

The public article serializer may expose only allow-listed fields:

```php
array(
    'id'                 => 123,
    'slug'               => 'example-story',
    'canonical_url'      => '/news/example-story/',
    'headline'           => '...',
    'subtitle'           => '...',
    'summary'            => '...',
    'body_html'          => '...',
    'language'           => 'en-US',
    'article_type'       => 'research-news',
    'public_label'       => 'Research News',
    'section'            => array(),
    'topics'             => array(),
    'country'            => array(),
    'region'             => array(),
    'public_author'      => array(),
    'reviewing_editor'   => array(),
    'featured_media'     => array(),
    'published_at'       => 'UTC timestamp',
    'updated_at'         => 'UTC timestamp',
    'reading_time'       => 0,
    'disclaimer'         => '...',
    'conflict_disclosure'=> '...',
    'correction_state'   => 'none',
    'retraction_notice'  => null,
    'interaction_id'     => 123,
)
```

### 7.2 Card projection

Cards use a smaller immutable public projection:

```php
array(
    'item_type'      => 'editorial_news',
    'global_key'     => 'news:123',
    'object_id'      => 123,
    'headline'       => '...',
    'summary'        => '...',
    'canonical_url'  => '/news/example-story/',
    'published_at'   => 'UTC timestamp',
    'updated_at'     => 'UTC timestamp',
    'public_label'   => 'News',
    'section'        => array(),
    'image'          => array(),
    'reading_time'   => 0,
    'interaction_id' => 123,
)
```

### 7.3 Forbidden public fields

The following must never enter public HTML, REST, JavaScript configuration, schema placeholders, cache objects, cards, or logs:

- private editor notes;
- reviewer notes and confidential reviewer identities;
- unpublished review decisions;
- private source notes or anonymous-source identities;
- preview tokens or hashes;
- nonces;
- internal scheduling failure records;
- submitted author/reviewer identifiers that are not approved public identities;
- patient, account, phone, WhatsApp, email, CNIC/passport, address, or medical-record identifiers;
- hidden original body of a retracted article.

Public serializers must be built by inclusion, never by serializing all fields and removing some afterward.

---

## 8. Query-service plan

Create a dedicated `NewsQueryService` with no rendering responsibility.

Responsibilities:

- resolve public eligibility through `NewsPolicy`;
- perform bounded public archive queries;
- perform canonical single lookup by sanitized slug;
- query controlled taxonomy archives;
- execute bounded public search and filters;
- select related articles;
- create Home Feed item candidates;
- generate deterministic pagination/cursor inputs;
- exclude all private states at query level and again at projection level;
- provide cache-key dimensions;
- return stable success/error results.

The service must not:

- accept raw order-by SQL identifiers;
- infer visibility from browser parameters;
- fall back from an invalid slug or ID to another article;
- include drafts merely because the current visitor is logged in;
- expose private preview behavior through a public cache path.

---

## 9. Public archive plan

The main `/news/` page will be assembled from bounded components:

1. Featured public story.
2. Latest News.
3. Editor’s picks foundation when approved metadata exists.
4. Research News.
5. Classical Homeopathy.
6. Public Health.
7. Homeopathy Education.
8. Platform News and Founder Updates.
9. Worldwide Health Developments.
10. Recently updated/corrected public articles.
11. Search and filter entry point.
12. Safe empty state.

Breaking strip remains disabled until Phase 4F.

Each section must:

- use a bounded independent query;
- avoid duplicate article cards within the same rendered page;
- use stable ordering;
- include a safe “View all” taxonomy link where applicable;
- render no private count or workflow signal;
- remain usable without JavaScript.

---

## 10. Single article plan

The public single template includes:

- disclosure label;
- headline;
- subtitle when present;
- summary;
- approved public author/institution identity;
- reviewing editor only where policy permits;
- publication and update times;
- reading time;
- section/topic/country/region/type links;
- featured image with alt text, caption, and approved credit;
- sanitized article body;
- applicable medical/public-information disclaimer;
- applicable conflict disclosure;
- public correction or retraction notice;
- related News;
- approved Phase 3 interactions where the relevant gates and object-type policies permit;
- canonical local sharing URL.

The template must not query the database directly. It receives one public projection from the controller/service boundary.

---

## 11. Taxonomy archive plan

Controlled archives:

- section;
- topic;
- country;
- region;
- article type.

Rules:

- only known public terms are accepted;
- unknown slugs return safe 404;
- taxonomy terms do not get silently created from requests;
- counts include only public eligible articles;
- empty public terms produce a controlled empty state or 404 according to routing policy;
- country and region are editorial discoverability fields, not user-location tracking;
- pagination and filters remain bounded.

The initial eighteen controlled section slugs remain those frozen in Contract Addendum 1.

---

## 12. Search and filter plan

Supported public filters:

- keyword;
- section;
- topic;
- country;
- region;
- article type;
- publication date range;
- approved public author/institution;
- research label;
- active or historical Breaking label only when later policy permits;
- corrected status;
- retracted-notice status.

Phase 4C initially enables only filters backed by merged and tested public data. Unsupported later filters remain rejected rather than partially implemented.

Rules:

- keyword length is bounded;
- each taxonomy filter accepts controlled slugs only;
- dates are strict calendar dates and normalized;
- date range has a maximum permitted span;
- page size has a small frozen maximum;
- invalid combinations return a field-specific safe error or an empty public result, never a widened private query;
- search uses escaped `LIKE` operations or WordPress-safe query APIs;
- search facets never include drafts, submissions, previews, queues, source confidence, reviewer identities, or private notes;
- retracted results contain only the approved public notice projection;
- filter dimensions are included in cache keys.

---

## 13. Home Feed integration plan

Create `NewsFeedIntegration` as a narrow adapter.

Responsibilities:

- request eligible public News candidates from `NewsQueryService`;
- convert each candidate to the normalized News feed-item shape;
- preserve the existing feed ordering contract;
- use `global_key = news:{id}` to prevent object collision;
- deduplicate News across initial feed and Load More;
- never insert the full News article body into the feed;
- link every card to its canonical News single route;
- consult Phase 3 interaction allow-lists before exposing reactions, saves, comments, reports, or view recording;
- remove all News cards when `editorial_news_enabled` is disabled without altering ordinary feed records.

Integration must not modify Phase 2 ordinary-post persistence or convert a community post into News.

---

## 14. Public REST plan

Phase 4C implements public read-only routes under:

```text
sabri-home-news-feed/v1
```

Initial public reads:

```text
GET /news
GET /news/{id}
```

Public REST requirements:

- use the same public projections as visible HTML;
- accept only allow-listed query arguments;
- use strict schemas and bounded arrays/page size;
- reject invalid IDs and unknown filters;
- return no private fields;
- return safe 404 for non-public objects without revealing whether a private object exists;
- use cache-safe public headers only for truly public responses;
- never include current-user interaction state in a shared public cache response;
- support separate authenticated current-user interaction calls only through existing Phase 3 boundaries;
- expose no write endpoint in Phase 4C.

The remaining News write routes stay private/closed until their relevant checkpoints.

---

## 15. Routing and controller architecture

Proposed classes:

```text
includes/class-news-query-service.php
includes/class-news-public-projection.php
includes/class-news-feed-integration.php
includes/class-news-cache.php
includes/class-rest-news.php
public/class-news-public-runtime.php
public/class-news-routing.php
```

Rules:

- routing resolves route intent only;
- controllers coordinate request, service, projection, template, and response;
- query service retrieves public domain data;
- policy decides visibility;
- projection classes allow-list public fields;
- templates render escaped values only;
- cache adapter owns cache keys and invalidation;
- feed integration owns only the adapter boundary to the existing Home Feed.

---

## 16. Templates and assets

Proposed templates:

```text
templates/news-archive.php
templates/news-card.php
templates/news-single.php
templates/news-taxonomy.php
templates/news-search-form.php
templates/news-empty-state.php
templates/news-correction-notice.php
templates/news-retraction-notice.php
```

Proposed assets:

```text
assets/css/news.css
assets/js/news.js
```

Asset rules:

- enqueue only on relevant public News routes or when a News card exists in the Home Feed;
- no inline private JSON;
- no public nonce unless a separately authorized interaction requires it;
- JavaScript is progressive enhancement, not required for core navigation/search;
- CSS respects the established global visual system and does not duplicate the site framework;
- reduced-motion, high zoom, keyboard, and screen-reader behavior are first-class requirements.

---

## 17. Accessibility requirements

Phase 4C must provide:

- semantic landmarks and heading hierarchy;
- one visible page-level `h1`;
- keyboard-operable search/filter controls;
- programmatically associated labels and instructions;
- focus visibility;
- logical focus order;
- no keyboard traps;
- accessible pagination with current-page state;
- article labels conveyed in text, not color alone;
- meaningful image alt text;
- decorative images with empty alt text;
- no auto-moving Breaking strip in this checkpoint;
- no repetitive live-region announcements;
- responsive layouts at narrow mobile widths;
- usable presentation at 200% and 400% zoom;
- compatibility with reduced-motion preference;
- safe empty/error messages announced without exposing raw input.

Automated accessibility-contract tests are required, followed by manual staging evidence in Phase 4I.

---

## 18. Security and privacy requirements

Phase 4C is fail closed.

Required controls:

- public eligibility enforced in query and projection layers;
- strict positive integer validation;
- strict slug validation;
- object-level state validation;
- output escaping by context;
- sanitized rich HTML rendering;
- prepared/WordPress-safe search queries;
- bounded pagination and filters;
- no open redirects;
- no unsafe URL schemes;
- no private preview tokens in public markup, logs, analytics, referrers, or caches;
- no private editorial/source/reviewer fields in public data;
- no patient/contact/account identifiers;
- retraction projection cannot expose hidden original content;
- public cache contains no user-specific state;
- cache uncertainty favors privacy;
- Emergency Disable closes the public surface.

Security tests must attempt IDOR, enumeration, malformed IDs/slugs, XSS payloads, unsupported filters, cache leakage, private-state access, and retraction-body disclosure.

---

## 19. Cache and invalidation plan

Cacheable objects:

- public article projection;
- public archive result;
- public taxonomy archive;
- public search result with normalized safe filters;
- related News;
- normalized Home Feed News candidate set.

Cache-key dimensions include:

- site/blog ID;
- public gate generation;
- Emergency Disable generation;
- query type;
- language;
- taxonomy/filter dimensions;
- page/cursor;
- public-state generation;
- article modified/publication generation.

Invalidation events include:

- publication;
- public update;
- correction/retraction state change;
- archive state change;
- taxonomy change;
- featured media/public label change;
- gate change;
- Emergency Disable change.

Private preview and current-user interaction responses use private/no-store behavior and never share public cache entries.

---

## 20. Performance requirements

- no unbounded `posts_per_page`;
- no N+1 public author, taxonomy, image, or interaction query pattern;
- archive components use bounded independently cacheable queries;
- single articles use one prepared projection path;
- related News has a small fixed maximum;
- public REST has strict page-size maximums;
- feed integration preserves deterministic pagination and does not requery the complete News archive;
- assets load only when needed;
- no rewrite flush per request;
- no external source URL fetch during ordinary public rendering;
- no synchronous expensive analytics aggregation in templates;
- tests include query-count ceilings where the runtime permits reliable measurement.

---

## 21. Error and result contract

Services return stable results:

```php
array(
    'success' => true,
    'code'    => 'public_news_found',
    'data'    => array(),
)
```

or:

```php
array(
    'success' => false,
    'code'    => 'public_news_not_found',
    'message' => 'The requested News article is not available.',
    'field'   => null,
    'status'  => 404,
)
```

Required public error codes include:

```text
editorial_news_disabled
public_news_not_found
public_news_filter_invalid
public_news_page_invalid
public_news_taxonomy_invalid
public_news_retracted
public_news_query_failed
```

Messages must not reveal private object existence, internal SQL, filesystem paths, capabilities, notes, tokens, or raw unsafe values.

---

## 22. Implementation sequence

### 4C.1 — Public policy and projection freeze

- freeze exact public states;
- freeze article/card/retraction projections;
- freeze public error codes;
- add contract tests before public routes.

### 4C.2 — Query service and cache adapter

- archive, single, taxonomy, search, related, and feed-candidate queries;
- state isolation;
- deterministic pagination;
- cache-key and invalidation contracts.

### 4C.3 — Routing and public runtime

- canonical route registration;
- safe 404/disabled/retracted behavior;
- activation/versioned rewrite handling;
- route collision regression tests.

### 4C.4 — Templates and public assets

- archive, card, single, taxonomy, search, empty, correction, and retraction templates;
- escaped rendering;
- responsive CSS;
- progressive-enhancement JavaScript.

### 4C.5 — Search and filters

- strict allow-list;
- bounded pagination/date ranges;
- private-state isolation;
- safe URLs and canonical filter serialization.

### 4C.6 — Home Feed integration

- normalized News item;
- canonical links;
- deduplication;
- Phase 2 feed regression protection;
- Phase 3 interaction boundary.

### 4C.7 — Public REST reads

- `GET /news`;
- `GET /news/{id}`;
- schemas, permission callbacks, serializers, cache headers, and negative tests.

### 4C.8 — Complete acceptance matrix

- source runtime matrices;
- immutable package build and checksum;
- packaged runtime matrices;
- exact-head one-hour visible QA;
- retained evidence artifact.

A later sub-checkpoint may not begin until the current sub-checkpoint’s tests pass on the exact branch head.

---

## 23. Proposed file map

```text
PHASE-4C-IMPLEMENTATION-PLAN.md
includes/class-news-query-service.php
includes/class-news-public-projection.php
includes/class-news-feed-integration.php
includes/class-news-cache.php
includes/class-rest-news.php
public/class-news-public-runtime.php
public/class-news-routing.php
templates/news-archive.php
templates/news-card.php
templates/news-single.php
templates/news-taxonomy.php
templates/news-search-form.php
templates/news-empty-state.php
templates/news-correction-notice.php
templates/news-retraction-notice.php
assets/css/news.css
assets/js/news.js
tests/run-phase4c-public-news-tests.php
tests/run-phase4c-security-tests.php
tests/run-phase4c-ui-completeness-tests.php
tests/run-phase4c-playground-tests.mjs
.github/workflows/phase4c-public-news-tests.yml
.github/workflows/phase4c-one-hour-visible-qa.yml
```

Files may be consolidated only when responsibilities remain separate and tests continue to prove the contract.

---

## 24. Automated test matrix

### Contract and unit behavior

Tests must prove:

- all frozen routes are exact;
- gate defaults remain disabled;
- unknown routes/filters/states fail closed;
- public state matrix is exact;
- serializers exclude every forbidden field;
- retracted projection hides original body;
- canonical URLs are stable;
- search filters are bounded and normalized;
- pagination is deterministic;
- feed `global_key` prevents collisions;
- feed deduplication works across initial and Load More pages;
- disabling the gate removes News without altering ordinary feed data;
- public REST is read-only;
- public cache never contains current-user/private data;
- Emergency Disable closes public News.

### Regression suites

Every Phase 4C head must rerun:

- Phase 4 contract tests;
- Phase 4A content-model tests;
- Phase 4B Newsroom/composer/workflow tests;
- Phase 4B UI completeness tests;
- Phase 2 and Phase 3 core regression suites;
- static checks and syntax lint.

### WordPress runtime matrices

At minimum:

```text
WordPress 6.8 / PHP 8.1
Latest supported WordPress / PHP 8.3
```

Each matrix tests:

- clean activation;
- merged Phase 4A/4B compatibility;
- gate-off behavior;
- controlled gate-on archive and single routes;
- taxonomy routes;
- search/filter isolation;
- Home Feed card integration;
- public REST projection;
- retracted/invalid/private object behavior;
- deactivation/reactivation without data loss.

### Package matrices

- build immutable installable ZIP;
- verify SHA-256;
- verify required file structure;
- install packaged ZIP into both WordPress matrices;
- repeat public route, feed, REST, and privacy tests against the package.

---

## 25. One-hour QA requirement

After all Phase 4C implementation and ordinary matrices pass, one observable PR-triggered exact-head workflow must run:

- at least `3,900` uninterrupted seconds;
- exactly `13` complete cycles;
- full Phase 4C tests in every cycle;
- Phase 4A and Phase 4B regressions in every cycle;
- core regressions and static checks in every cycle;
- fresh package build/checksum/structure verification in every cycle;
- initial/final tracked-file manifest comparison;
- final packaged WordPress verification on both matrices;
- retained artifact named:

```text
sabri-phase4c-ONE-HOUR-VISIBLE-QA-PASSED-{exact-head-sha}
```

A code or workflow correction creates a new exact head and restarts the entire acceptance from zero. Elapsed time from failed/cancelled attempts must never be combined.

---

## 26. Acceptance criteria

Phase 4C is complete only when all of the following are directly evidenced on one exact commit:

1. Every planned class/template/asset exists or an explicitly documented equivalent exists.
2. Public state isolation passes positive and negative tests.
3. All canonical routes pass.
4. Archive, single, taxonomy, search, and empty states work without JavaScript.
5. Public serializers expose only allow-listed fields.
6. Retraction projection cannot reveal hidden original content.
7. Home Feed cards are normalized, canonical, deterministic, and deduplicated.
8. Existing ordinary feed behavior remains unchanged when the gate is disabled.
9. Public REST GET routes pass strict schema and privacy tests.
10. Public News writes remain closed in Phase 4C.
11. Cache keys and invalidation pass privacy and staleness tests.
12. Accessibility-contract tests pass.
13. Security-negative tests pass.
14. Source WordPress matrices pass.
15. Immutable package verification passes.
16. Packaged WordPress matrices pass.
17. All Phase 4A/4B and core regressions pass.
18. The exact-head one-hour visible QA passes and retains evidence.
19. PR remains Draft and unmerged until a later explicit decision.
20. No plugin/schema version promotion, gate activation, Hostinger staging, or live deployment occurs.

---

## 27. Rollback and safety boundaries

Phase 4C implementation must be additive and reversible.

- Gate-off must immediately remove public News routes/cards without deleting data.
- Deactivation must not delete Editorial News.
- No unrelated table or option may be dropped, truncated, renamed, or rewritten.
- Existing Phase 2/3 data and routes remain authoritative.
- Cache purge must remove only plugin-owned News cache keys.
- Rewrite rollback must restore the prior plugin route state safely.
- Package rollback remains a later Hostinger staging/release operation.

---

## 28. Pull request and release policy

The Phase 4C branch and PR must remain:

- separate from `main`;
- Draft during implementation and QA;
- unmerged until exact-head acceptance evidence exists and an explicit later merge decision is made.

Phase 4C completion does **not** authorize:

- marking the PR ready;
- merging;
- promoting plugin or schema version;
- enabling public gates on staging or live;
- Hostinger installation;
- live deployment.

Those are separate controlled decisions.

---

## 29. Next checkpoint after Phase 4C

After Phase 4C is implemented, fully tested, accepted, and separately merged, the next planned checkpoint is:

```text
Phase 4D — Sources, Evidence, Fact Check, and Medical/Scientific Review Ledgers
```

Phase 5 does not begin until all remaining Phase 4 checkpoints are completed under their own contracts and acceptance evidence.

---

## 30. Planning decision

This document authorizes only orderly Phase 4C implementation on the isolated feature branch. It freezes the checkpoint’s functional scope, architecture, privacy boundary, test matrix, and release boundaries. It does not authorize merge, version promotion, staging activation, or live deployment.
