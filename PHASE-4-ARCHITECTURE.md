# Phase 4 Architecture — Editorial News and Global Newsroom

Target development line: `1.2.0`

Branch: `build/phase-4-editorial-news-1.2.0`

Dependency: `PHASE-4-CONTRACTS.md`

Status: **architecture design — no production authorization**

## 1. Architectural objective

Phase 4 introduces a complete Editorial News subsystem without turning the existing social feed into an unstructured publishing system.

The design must provide:

- a separate editorial content model;
- clear workflow ownership;
- trustworthy source and correction records;
- controlled distribution into the Home Feed;
- public News routes and discovery;
- strict privacy and security boundaries;
- compatibility with existing Phase 2 and Phase 3 behavior;
- incremental, feature-gated delivery;
- reversible installation and upgrade paths.

## 2. System boundaries

### 2.1 Existing systems that remain authoritative

The following existing systems remain authoritative and must be reused rather than duplicated:

- WordPress authentication and current-session identity.
- Existing plugin Safe Mode and Emergency Disable behavior.
- Existing Phase 3 reactions, saves, comments, reports, notification bridge, and view services where integration is permitted.
- WordPress Media Library.
- WordPress revisions and scheduled events where they meet the contract.
- Existing plugin audit and release-readiness foundations.

### 2.2 New Phase 4 domain

The new domain owns:

- Editorial News content type.
- Editorial workflow and role-specific queues.
- Source registry.
- Medical/scientific review records.
- Correction and retraction ledger.
- Breaking News state.
- News-specific public queries, routes, cards, schema, RSS, and sitemap behavior.
- News submission workflow.

### 2.3 Prohibited coupling

Phase 4 must not:

- write directly into another module’s private tables;
- infer permission from frontend fields;
- depend on a Profiles implementation to produce safe fallback URLs;
- add News logic inside unrelated templates when a service or integration boundary is available;
- require all Phase 3 gates to be active;
- modify live content merely because the code branch exists.

## 3. Architectural style

The implementation follows a layered modular structure:

```text
Bootstrap / Registration
        ↓
Domain Policies and Workflow
        ↓
Application Services
        ↓
Repositories and WordPress Adapters
        ↓
REST / Admin / Public Runtime Controllers
        ↓
Templates and Assets
```

Rules:

- Controllers coordinate; they do not contain persistence logic.
- Repositories persist; they do not decide authorization.
- Policies decide permission and transitions; they do not render HTML.
- Templates render escaped data; they do not query or write the database.
- Services return stable result objects rather than terminating requests unpredictably.

## 4. Proposed file map

```text
includes/
  class-editorial-news-post-type.php
  class-news-taxonomies.php
  class-news-capabilities.php
  class-news-statuses.php
  class-news-feature-settings.php
  class-news-policy.php
  class-news-workflow.php
  class-news-service.php
  class-news-query-service.php
  class-news-source-repository.php
  class-news-correction-repository.php
  class-news-review-repository.php
  class-news-breaking-service.php
  class-news-scheduling-service.php
  class-news-submission-service.php
  class-news-feed-integration.php
  class-news-schema.php
  class-news-rss.php
  class-news-sitemap.php
  class-news-cache.php
  class-news-audit.php
  class-rest-news.php
  class-rest-news-sources.php
  class-rest-news-submissions.php
  class-news-release-readiness.php

admin/
  class-newsroom-admin.php
  class-news-list-table.php
  class-news-submission-list-table.php
  class-news-review-list-table.php
  class-news-source-admin.php
  class-news-correction-admin.php
  views/
    newsroom-overview.php
    newsroom-editor.php
    newsroom-review.php
    newsroom-fact-check.php
    newsroom-medical-review.php
    newsroom-calendar.php
    newsroom-sources.php
    newsroom-corrections.php
    newsroom-retractions.php
    newsroom-settings.php

public/
  class-news-public-runtime.php
  class-news-routing.php

assets/css/
  news.css
  newsroom-admin.css

assets/js/
  news.js
  newsroom-editor.js
  breaking-news.js

assets/build/
  news.asset.php
  newsroom-editor.asset.php

templates/
  news-archive.php
  news-card.php
  news-single.php
  news-breaking-strip.php
  news-sources.php
  news-correction-notice.php
  news-retraction-notice.php
  news-submission-form.php
  news-empty-state.php

tests/
  run-phase4-contract-tests.php
  run-phase4a-content-model-tests.php
  run-phase4b-newsroom-tests.php
  run-phase4c-public-news-tests.php
  run-phase4d-sources-review-tests.php
  run-phase4e-submissions-tests.php
  run-phase4f-breaking-corrections-tests.php
  run-phase4g-seo-language-tests.php
  run-phase4h-hardening-tests.php
  run-phase4-packaged-integration-tests.mjs
  run-phase4-one-hour-soak-qa.sh
```

Names may be consolidated where justified, but responsibilities must remain separated.

## 5. Bootstrap and registration

The existing plugin bootstrap registers one Phase 4 coordinator only when the code is present and safe boot succeeds.

Registration order:

1. feature settings;
2. post type and taxonomies;
3. statuses and capabilities;
4. repositories;
5. policies and services;
6. REST routes;
7. admin screens;
8. public routing and templates;
9. feed integration;
10. schema/RSS/sitemap adapters;
11. audit, privacy, diagnostics, and release-readiness hooks.

All registration must tolerate disabled gates and unavailable optional dependencies.

## 6. Content model

### 6.1 Custom post type

```text
sabri_news
```

Recommended registration properties:

- public queryable only through the approved gate;
- REST enabled through controlled fields and custom permission callbacks;
- revisions enabled;
- author support enabled;
- featured image enabled;
- excerpts supported for summaries where appropriate;
- comments not inherited blindly; Phase 3 comment policy must authorize them;
- custom capabilities mapped instead of generic post capabilities;
- canonical rewrite base `/news/`.

### 6.2 Taxonomies

```text
sabri_news_section
sabri_news_topic
sabri_news_country
sabri_news_region
sabri_news_type
```

Architecture rules:

- terms are validated against administrator-controlled taxonomies;
- public archives include only public articles;
- term-management capabilities are separate from article-writing capabilities;
- country and region are taxonomic discoverability, not sensitive user-location tracking;
- article type may be a taxonomy or controlled metadata, but there must be one canonical source of truth.

### 6.3 Metadata

Proposed metadata keys use a plugin prefix, for example:

```text
_sabri_news_subtitle
_sabri_news_summary
_sabri_news_language
_sabri_news_priority
_sabri_news_fact_check_status
_sabri_news_medical_review_status
_sabri_news_breaking_status
_sabri_news_breaking_starts_at
_sabri_news_breaking_expires_at
_sabri_news_last_verified_at
_sabri_news_correction_status
_sabri_news_retraction_status
_sabri_news_reviewing_editor_id
_sabri_news_medical_reviewer_id
_sabri_news_source_article_id
```

Public REST exposure must be explicitly allow-listed and context-aware.

## 7. Dedicated persistence

### 7.1 Source table

Proposed table:

```text
{$wpdb->prefix}sabri_news_sources
```

Core columns:

```text
id BIGINT UNSIGNED PRIMARY KEY
news_id BIGINT UNSIGNED NOT NULL
source_title TEXT NOT NULL
publisher VARCHAR(255) NOT NULL
normalized_url TEXT NULL
url_hash CHAR(64) NULL
source_type VARCHAR(64) NOT NULL
evidence_class VARCHAR(64) NOT NULL
is_primary TINYINT(1) NOT NULL DEFAULT 0
publication_date DATETIME NULL
accessed_at DATETIME NULL
public_citation TEXT NULL
private_note LONGTEXT NULL
created_by BIGINT UNSIGNED NOT NULL
verified_by BIGINT UNSIGNED NULL
created_at DATETIME NOT NULL
updated_at DATETIME NOT NULL
```

Indexes:

- `news_id`
- `url_hash`
- `(news_id, url_hash)` unique where supported by the final normalized design
- `evidence_class`
- `verified_by`

### 7.2 Review table

A normalized review ledger is preferred over scattered metadata when multiple review events must be preserved.

Proposed table:

```text
{$wpdb->prefix}sabri_news_reviews
```

Core fields:

- review ID;
- news ID;
- review type (`editorial`, `fact-check`, `medical`, `translation`);
- decision;
- reviewer ID;
- private note;
- public disclosure where applicable;
- source revision ID;
- created/updated UTC.

### 7.3 Correction table

Proposed table:

```text
{$wpdb->prefix}sabri_news_corrections
```

Core fields:

- correction ID;
- news ID;
- correction type;
- public note;
- reason category;
- previous revision ID;
- corrected revision ID;
- requested by;
- approved by;
- created UTC.

### 7.4 Migration strategy

- Tables are created additively with `dbDelta`-compatible definitions where appropriate.
- Schema version is stored separately from plugin display version.
- Migration routines are idempotent.
- No unrelated table is dropped, truncated, renamed, or rewritten.
- Schema audit verifies required columns, indexes, and constraints.

## 8. Domain services

### 8.1 `NewsPolicy`

Responsibilities:

- current-user capability checks;
- field-level permission;
- public visibility;
- preview authorization;
- article-type restrictions;
- medical-review requirement;
- breaking eligibility;
- correction/retraction authority.

### 8.2 `NewsWorkflow`

Responsibilities:

- allow-listed state transitions;
- prerequisite validation;
- transition audit records;
- scheduling transition;
- correction/retraction lifecycle;
- idempotency.

### 8.3 `NewsService`

Responsibilities:

- create/update article operations;
- coordinate policy, validation, repositories, revisions, cache, and audit;
- return stable results;
- never trust submitted author or reviewer identity without policy resolution.

### 8.4 `NewsQueryService`

Responsibilities:

- public list queries;
- admin queue queries;
- related articles;
- Home Feed item selection;
- cache-key dimensions;
- strict exclusion of private states.

### 8.5 `NewsSourceRepository`

Responsibilities:

- normalized source writes;
- duplicate control;
- bounded source lists;
- public/private projections;
- safe deletion rules;
- no policy decisions.

### 8.6 `NewsCorrectionRepository`

Responsibilities:

- immutable correction ledger;
- revision linkage;
- public notice projection;
- correction history pagination.

### 8.7 `NewsBreakingService`

Responsibilities:

- active-story limit;
- start/expiry validation;
- priority ordering;
- expiry cleanup;
- cache invalidation;
- emergency-disable closure.

### 8.8 `NewsSchedulingService`

Responsibilities:

- UTC-normalized scheduling;
- WordPress cron integration;
- missed-schedule detection;
- retry policy;
- failure audit and administrator notice;
- no duplicate publication.

### 8.9 `NewsSubmissionService`

Responsibilities:

- contributor/doctor submissions;
- submitter-only visibility;
- attachment validation;
- editorial conversion to draft;
- request-information and rejection states;
- submitter-safe status projection.

## 9. Result object

Services should return a consistent result object, for example:

```php
array(
    'success' => true,
    'code'    => 'news_updated',
    'data'    => array(),
)
```

or:

```php
array(
    'success' => false,
    'code'    => 'news_transition_not_allowed',
    'message' => 'This article cannot move to the requested state.',
    'field'   => 'status',
    'status'  => 409,
)
```

The REST layer maps this result without exposing private internals.

## 10. Administrative architecture

### 10.1 Menu structure

```text
Home & News Feed
└── Newsroom
    ├── Overview
    ├── All News
    ├── Add News
    ├── Submissions
    ├── Editorial Review
    ├── Fact Check Queue
    ├── Medical Review Queue
    ├── Editorial Calendar
    ├── Scheduled News
    ├── Breaking News
    ├── Sources
    ├── Corrections
    ├── Retracted News
    ├── Taxonomies
    ├── Settings
    ├── System Check
    └── Audit Log
```

Every screen has a direct capability requirement and nonce-protected actions.

### 10.2 Newsroom overview

Cards and queues are built from bounded aggregate queries:

- drafts;
- needs sources;
- editorial review;
- fact check;
- medical review;
- scheduled;
- published today;
- correction pending;
- active breaking stories;
- expired breaking state needing cleanup;
- missed schedule failures.

### 10.3 Editorial composer

The composer is a controlled application screen, not a collection of unverified arbitrary post meta boxes.

Sections:

- article identity;
- headline/subtitle/summary;
- article body;
- type and taxonomies;
- featured media and rights;
- sources;
- evidence classification;
- conflict disclosure;
- fact-check checklist;
- medical-review requirement;
- related content;
- SEO/social metadata;
- scheduling/breaking controls;
- editor-only notes.

Autosave and revisions must not publish or expose incomplete data.

## 11. Public routing architecture

### 11.1 Routes

```text
/news/
/news/{article-slug}/
/news/section/{slug}/
/news/topic/{slug}/
/news/country/{slug}/
/news/region/{slug}/
/news/type/{slug}/
```

Routing rules:

- canonical single route is authoritative;
- invalid or private objects return a safe not-found/forbidden response without object enumeration;
- retracted objects render the approved public notice;
- feed-card links never route back into a duplicate feed instance;
- rewrite flushing occurs only on activation/versioned migration, never every request.

### 11.2 Main News page

Recommended composition:

- featured story;
- active breaking strip;
- latest News;
- editor’s picks;
- research;
- Classical Homeopathy;
- public health;
- global/country sections;
- platform updates;
- most read;
- recently corrected/updated.

Each section uses bounded, cached, independently invalidated queries.

### 11.3 Single News page

Public projection includes only approved fields:

- headline and subtitle;
- summary;
- article body;
- public author/institution identity;
- reviewing editor where policy allows;
- published/updated time;
- reading time;
- taxonomies;
- featured media with credit;
- sources/citations;
- evidence/disclaimer notes;
- correction/retraction history;
- related News;
- approved Phase 3 interactions.

## 12. Home Feed integration

`NewsFeedIntegration` provides a normalized feed item object rather than injecting raw `WP_Post` assumptions into existing templates.

Suggested item shape:

```php
array(
    'item_type'      => 'editorial_news',
    'global_key'     => 'news:123',
    'object_id'      => 123,
    'headline'       => '...',
    'summary'        => '...',
    'canonical_url'  => '...',
    'published_at'   => '...',
    'section'        => '...',
    'image'          => array(),
    'interaction_id' => 123,
)
```

Rules:

- `global_key` prevents collision with ordinary posts.
- pagination cursor/order remains deterministic.
- News cards are deduplicated across initial load and Load More.
- disabled Editorial News gate removes News cards without changing ordinary feed data.
- interactions consult existing Phase 3 policy and object-type allow-lists.

## 13. REST architecture

### 13.1 Controllers

Recommended controllers:

- `RestNews`
- `RestNewsSources`
- `RestNewsSubmissions`

Each route defines:

- strict argument schema;
- permission callback;
- field limits;
- public/admin context;
- no-store headers for private/current-user responses;
- cache-safe headers for public responses.

### 13.2 Serialization

Use separate projections:

- public article;
- contributor’s own draft/submission;
- reviewer projection;
- administrator projection;
- source public projection;
- source private projection.

Never serialize an all-fields domain object and attempt to remove private fields afterward.

## 14. Source and evidence architecture

### 14.1 URL normalization

Normalize for duplicate control without rewriting the public citation incorrectly:

- trim and validate scheme;
- lower-case host;
- remove fragment;
- normalize default ports;
- preserve meaningful query parameters unless an approved rule removes known trackers;
- hash the normalized URL for indexing.

### 14.2 Evidence rules engine

A policy layer determines requirements by article type and claim category.

Examples:

- official announcement may rely on an official primary statement;
- research News requires the study or authoritative record where available;
- medical treatment claims require medical review and stronger evidence classification;
- editorial/opinion must be visibly labelled and must not imitate straight News.

The engine returns requirements and deficiencies; it does not invent an evidence grade.

## 15. Corrections and revisions architecture

WordPress revisions preserve article text history. The correction ledger preserves public accountability context.

Material-correction transaction:

1. authorize correction;
2. validate public note and reason;
3. create/update article revision;
4. insert immutable correction record;
5. update article correction state and modified time;
6. invalidate single/archive/feed/schema/RSS caches;
7. emit audit event;
8. optionally emit a privacy-safe notification event.

Retraction follows a similar transaction but changes public projection and promotional eligibility.

## 16. Breaking News architecture

Active breaking stories may be queried through a small cached collection.

Cache dimensions:

- site/blog ID;
- language;
- current UTC time bucket/expiry generation;
- feature-gate generation;
- emergency-disable generation.

Expiry may be enforced both by scheduled cleanup and read-time validation so a failed cron cannot leave an expired breaking label public indefinitely.

## 17. Scheduling architecture

All stored times use UTC. Administration displays the configured site timezone.

Scheduling requirements:

- one publication event per article/version;
- idempotency token or state check;
- missed-schedule diagnostic;
- bounded retry;
- no publication if prerequisites were revoked after scheduling;
- editorial audit record for success/failure;
- cache invalidation after publication.

## 18. Media architecture

Featured and inline media use WordPress attachment IDs.

Required attachment-level or article-level fields:

- alt text;
- caption;
- source/photographer;
- copyright owner;
- license;
- consent status where relevant;
- AI-generated illustration disclosure where applicable.

Upload validation is centralized and reused by composer and submissions.

## 19. SEO, schema, sitemap, and RSS architecture

### 19.1 Schema

A `NewsSchema` adapter generates structured data from the same public projection used by the visible page.

Eligible types:

- `NewsArticle`
- `Article`
- `BreadcrumbList`
- `Organization`
- `Person`
- `ImageObject`

No structured field may reveal data absent from the public page.

### 19.2 Sitemap

A News sitemap provider includes only canonical, public, indexable articles and approved translations.

### 19.3 RSS

RSS uses a separate gate and a public projection. It excludes:

- drafts;
- pending workflow;
- private sources/notes;
- hidden original retracted content;
- current-user interaction state.

## 20. Translation architecture

Translation relationships use explicit source article IDs and language metadata.

Workflow:

```text
source-published
→ translation-draft
→ translation-review
→ terminology-review-when-required
→ translation-published
```

Rules:

- source article remains authoritative;
- translations have their own revisions and public URLs;
- canonical/hreflang relationships are generated only for published approved versions;
- automated translation output is always a draft;
- RTL is supported at the CSS/layout layer.

## 21. Cache architecture

### 21.1 Cacheable

- public published article projection;
- public archives;
- public taxonomy lists;
- public source citations;
- active breaking collection;
- related News;
- structured data;
- public RSS/sitemap output.

### 21.2 Never public-cache

- drafts;
- submissions;
- previews;
- review queues;
- current-user permissions/state;
- private notes;
- unpublished corrections;
- REST nonce-bearing markup.

### 21.3 Invalidation events

- publication/unpublication;
- material update;
- source-publication status change;
- correction;
- retraction;
- breaking start/expiry/change;
- taxonomy change;
- language relationship change;
- feature gate change;
- emergency disable.

## 22. Notification integration

Phase 4 publishes privacy-minimized domain events to the existing notification bridge.

Allowed event examples:

- submission received;
- revision requested;
- review assigned;
- article approved;
- article published;
- scheduled publication failed;
- correction requested/published;
- article retracted.

The original operation is not rolled back merely because a notification callback fails.

## 23. Analytics integration

Public aggregate view counts may reuse the existing privacy-safe view service after object-type support is explicitly added and tested.

No new raw-IP analytics table is permitted.

Potential aggregates:

- article views;
- saves;
- reactions;
- shares where measurable without invasive tracking;
- source-link clicks;
- section-level totals.

## 24. Accessibility architecture

Accessibility is implemented at component level:

- semantic News cards;
- real buttons/links;
- visible focus tokens;
- properly associated editor fields/errors;
- queue tables with captions and headers;
- non-intrusive live regions;
- accessible breaking strip controls;
- responsive correction/retraction notices;
- reduced-motion styles;
- no mouse-only sorting or scheduling control.

Automated structural tests supplement, but do not replace, manual keyboard and screen-reader testing.

## 25. Performance architecture

- Conditional asset enqueueing.
- Query objects with bounded limits.
- Preloaded source counts/public citations where needed.
- No per-card repeated author/taxonomy/source queries.
- Object-cache-aware repositories.
- Pagination for every administration queue.
- Background/non-blocking maintenance for expired breaking records and diagnostic cleanup.
- Avoid large serialized arrays in autoloaded options.

## 26. Diagnostics and System Check

Phase 4 adds diagnostic checks for:

- feature option validity;
- post type/taxonomy registration;
- capabilities;
- table/schema/index presence;
- rewrite status;
- scheduled event health;
- missed schedule count;
- active breaking expiry anomalies;
- public/private cache separation;
- REST route registration;
- package/version/checklist identity.

Diagnostics must be read-only unless the administrator performs a separately confirmed repair action.

## 27. Repair and rollback architecture

Repair actions are allow-listed and non-destructive by default:

- recreate missing capabilities;
- refresh rewrite rules once;
- schedule missing maintenance event;
- rebuild derived cache;
- report schema differences.

Repair must never silently delete editorial content, sources, reviews, corrections, or WordPress users.

Rollback documentation must specify:

- plugin-file restoration;
- database-backup restoration conditions;
- gate closure;
- post-rollback Phase 2/3 regression checks;
- Phase 4 data preservation expectations;
- exact evidence and timestamps.

## 28. Development checkpoints

### 4.0 Contract freeze

Documents and automated contract assertions only.

### 4A Content model and roles

Post type, taxonomies, statuses, capabilities, feature settings, schema foundations.

### 4B Newsroom and composer

Administrative screens, workflow queues, editor, validation, scheduling foundations.

### 4C Public News and feed integration

Routes, archives, single pages, cards, search/filter, canonical behavior.

### 4D Sources and reviews

Source registry, evidence rules, fact-check and medical-review flows.

### 4E Submissions

Doctor/contributor submission and isolation.

### 4F Breaking, scheduling, corrections, retractions

Time-based and accountability features.

### 4G SEO, RSS, sitemap, translation foundations

Public distribution and multilingual linkage.

### 4H Hardening

Security, privacy, accessibility, performance, cache, race, and regression matrix.

### 4I Release readiness

Packaging, checksums, clean/upgrade install, one-hour soak, Hostinger staging, backup, rollback, and acceptance record.

## 29. Pull-request strategy

Recommended approach:

- one long-lived Phase 4 integration branch;
- checkpoint commits with independently passing tests;
- a Draft pull request opened after 4.0 documents are committed;
- no version-promotion change mixed into feature implementation;
- no direct commits to `main`;
- no live deployment from a checkpoint artifact.

## 30. Architectural acceptance criteria

The architecture is accepted for implementation only when:

- every public identifier matches the contract;
- data ownership and privacy projections are explicit;
- workflow transitions are defined;
- repository/service/controller boundaries are agreed;
- additive migration and rollback paths are defined;
- Home Feed integration cannot collide or leak;
- Phase 2 and Phase 3 regression boundaries are explicit;
- security and privacy document is approved;
- automated contract tests are planned before feature coding.
