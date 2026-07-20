# Phase 4 Contract Freeze — Editorial News and Global Newsroom

Target development line: `1.2.0`

Development branch: `build/phase-4-editorial-news-1.2.0`

Status: **contract freeze only — implementation is not yet authorized by this document**

## 1. Purpose

Phase 4 adds a distinct editorial-news system to the existing Home and News Feed plugin. It must preserve the separation between community content and institutionally reviewed journalism.

Core invariant:

> Every Editorial News article may be distributed into the Home Feed, but an ordinary Home Feed post must never become Editorial News merely because it appears in the same feed.

Phase 4 must not weaken Phase 2 feed behavior or Phase 3 social, moderation, privacy, accessibility, safety, or rollback contracts.

## 2. Scope

Phase 4 includes:

- Editorial News custom content model.
- Global Newsroom administration.
- Editorial submissions, assignments, review, fact checking, medical/scientific review, scheduling, publication, correction, retraction, and archiving.
- Source registry and evidence classification.
- Public News archive, section, topic, country, region, type, and single-article routes.
- Home Feed integration through a dedicated News card.
- Breaking News with bounded priority and expiry.
- Search, filters, canonical routing, structured data, sitemap, RSS, social metadata, and translation-ready foundations.
- Accessibility, performance, privacy, security, audit, emergency-disable, backup, and rollback requirements.

## 3. Explicit non-scope

Phase 4 does not implement or redesign:

- Marketplace.
- Appointments.
- WhatsApp-alternative messaging.
- Video Wall, Reels, livestreaming, or PDF Library.
- University LMS.
- Payments.
- Full Profiles redesign.
- AI assistant rebuild.
- A general political or entertainment newsroom.
- Automatic publication of machine-generated or machine-translated content.
- Production deployment merely because a branch or pull request is merged.

## 4. Content separation contract

### 4.1 Community content

Community content remains the existing social and educational post system. It may use Phase 3 interactions subject to its own gates and policies.

### 4.2 Editorial News

Editorial News is a separate content class with:

- defined article type;
- assigned editorial ownership;
- source records;
- review state;
- publication authority;
- correction and retraction history;
- public provenance and update information.

### 4.3 Distribution

A published Editorial News article may be represented in the Home Feed by a News card. The card must link to the canonical single News route and must not duplicate the full article in the feed.

## 5. Frozen public identifiers

Custom post type:

```text
sabri_news
```

Primary public route:

```text
/news/
```

Public single route:

```text
/news/{article-slug}/
```

Taxonomies:

```text
sabri_news_section
sabri_news_topic
sabri_news_country
sabri_news_region
sabri_news_type
```

REST namespace remains:

```text
sabri-home-news-feed/v1
```

## 6. Frozen article types

The initial allow-list is:

- `breaking-news`
- `standard-news`
- `research-news`
- `editorial`
- `analysis`
- `interview`
- `event-report`
- `official-announcement`
- `correction-notice`
- `retraction-notice`

Unknown types must fail validation and must not be silently converted.

## 7. Frozen editorial states

Pre-publication states:

```text
draft
needs-sources
editorial-review
fact-check
medical-review
ready-for-publication
scheduled
```

Published lifecycle states:

```text
published
updated
correction-pending
corrected
retracted
archived
```

Rules:

- A contributor cannot jump directly from draft to published.
- A medical/scientific claim cannot bypass the required review policy.
- A retracted article is not deleted as a substitute for a public accountability record.
- Unsupported or malformed transitions fail closed.
- Every material transition creates an audit record.

## 8. Feature-gate contract

The initial Phase 4 gates are:

```text
editorial_news_enabled
news_submissions_enabled
breaking_news_enabled
scheduled_news_enabled
news_corrections_enabled
news_rss_enabled
news_schema_enabled
news_notifications_enabled
```

Frozen defaults: all Phase 4 gates are `0`.

Rules:

- A disabled gate closes its related public and write surfaces without deleting data.
- Emergency Disable overrides all Phase 4 public writes.
- Phase 4 settings are isolated from Phase 2 and Phase 3 options.
- Unknown gates are rejected.
- An unchecked administrator checkbox is stored as disabled, not restored to a default-enabled value.
- Staging acceptance enables one gated system at a time before testing the approved combined matrix.

## 9. Role and capability contract

### 9.1 Roles

The workflow recognizes these logical roles without requiring destructive replacement of existing WordPress roles:

- Administrator
- Founder
- Editor-in-Chief
- Managing Editor
- Section Editor
- Medical/Scientific Reviewer
- Reporter/Contributor
- Verified Doctor Submitter
- Translator
- Reader

### 9.2 Capabilities

```text
read_editorial_news
create_editorial_news
edit_own_editorial_news
edit_others_editorial_news
submit_editorial_news
review_editorial_news
fact_check_editorial_news
medical_review_editorial_news
publish_editorial_news
schedule_editorial_news
manage_breaking_news
manage_news_sources
manage_news_corrections
retract_editorial_news
translate_editorial_news
manage_news_taxonomies
manage_news_settings
```

Rules:

- Permission derives from the authenticated current session, never a submitted user ID.
- Reporter/Contributor and Verified Doctor Submitter cannot self-publish.
- Translators cannot alter the source-language article.
- Medical reviewers can review medical claims without automatically receiving all administrative powers.
- Only authorized administrators/editors may manage breaking status, corrections, and retractions.
- Public readers never receive editorial-private capabilities through REST serialization.

## 10. Required article fields

Before publication, the article must have:

- headline;
- summary;
- full article body;
- article type;
- section;
- language;
- assigned author or institutional author;
- at least one acceptable source when factual claims require sourcing;
- fact-check state;
- medical-review state where required;
- featured-image alt text when a featured image exists;
- publication decision and responsible editor;
- applicable disclaimer and conflict disclosure.

A missing mandatory field produces a field-specific error and does not partially publish.

## 11. Source contract

Each source record must be owned by exactly one News article and may contain:

- public source title;
- publisher or institution;
- normalized URL and URL hash;
- source type;
- primary/secondary designation;
- publication date;
- access date;
- evidence classification;
- public citation text;
- private editorial note;
- creator and verifier identifiers.

Evidence classes:

```text
primary
authoritative-secondary
supporting
press-release
unverified
rejected
```

Rules:

- Private editorial notes never appear in public REST, HTML, feeds, schema, sitemap, exports belonging to another user, or notification payloads.
- Rejected sources cannot satisfy publication requirements.
- Duplicate normalized URLs for the same article are rejected or idempotently reused.
- The system stores source metadata, not copied third-party articles.

## 12. Correction and retraction contract

### 12.1 Minor correction

Formatting or typographical changes remain in revision history. A public notice is optional when meaning is unchanged.

### 12.2 Material correction

A factual, contextual, attribution, date, name, result, or conclusion change requires:

- public correction note;
- correction time;
- responsible approver;
- previous and corrected revision linkage;
- cache invalidation;
- updated structured metadata where enabled.

### 12.3 Retraction

Retraction requires:

- retraction reason category;
- public notice;
- responsible approver;
- removal from normal feed promotion and recommendations;
- preservation of the accountability record;
- immediate cache invalidation.

Silent material rewriting is prohibited.

## 13. Breaking News contract

Breaking News requires:

- authorized capability;
- start time;
- expiry time;
- bounded priority;
- active-story limit;
- accessible presentation;
- automatic expiry behavior.

Rules:

- A breaking label is never permanent by default.
- Expiry removes breaking presentation without deleting or unpublishing the article.
- Reduced-motion preference must be honored.
- A moving strip, if implemented, requires a pause mechanism and must not cause repetitive screen-reader announcements.

## 14. REST contract

Initial route map:

```text
GET    /news
GET    /news/{id}
POST   /news
PATCH  /news/{id}
DELETE /news/{id}
POST   /news/{id}/submit
POST   /news/{id}/review
POST   /news/{id}/publish
POST   /news/{id}/schedule
POST   /news/{id}/correct
POST   /news/{id}/retract
GET    /news/{id}/sources
POST   /news/{id}/sources
GET    /news/submissions/me
```

Write routes require:

- current authenticated session;
- valid WordPress REST nonce;
- exact capability;
- enabled gate;
- strict positive integer identifiers;
- bounded text and list sizes;
- sanitization and output escaping;
- rate limiting where abuse is possible;
- audit logging;
- fail-closed policy evaluation.

Public responses must omit:

- email addresses;
- phone and WhatsApp numbers;
- private editorial notes;
- unpublished source notes;
- capabilities and login names;
- IP addresses and user agents;
- nonces and security tokens;
- internal moderation hashes;
- patient identifiers.

## 15. Stable error contract

REST and service errors use a stable machine-readable shape:

```json
{
  "success": false,
  "code": "news_error_code",
  "message": "Safe user-facing message.",
  "field": "optional_field_name",
  "status": 400
}
```

Rules:

- Error messages do not echo secrets, patient identifiers, private notes, SQL, filesystem paths, stack traces, or raw source payloads.
- Authorization failures do not reveal whether a private object exists when that would create an enumeration leak.
- Repeated identical safe operations may return an idempotent success instead of creating duplicate records.

## 16. Public query contract

Public queries may return only articles that are:

- published under the Editorial News policy;
- permitted by the relevant gate;
- not hidden by retraction/archive rules;
- visible in the requested language or taxonomy context.

Drafts, submissions, review queues, private previews, internal notes, and unpublished corrections must never leak through:

- archives;
- search;
- REST;
- feeds;
- sitemap;
- related content;
- Home Feed integration;
- caches;
- author archives;
- taxonomy archives.

## 17. Feed integration contract

A News card must have a globally unambiguous item identity distinct from ordinary post identities.

It must include:

- News label;
- headline;
- summary;
- image when available;
- section/type;
- publication or update time;
- canonical link;
- permitted Phase 3 interactions.

It must not:

- duplicate on Load More;
- alter ordinary post ordering unpredictably;
- expose draft state;
- include editor-only notes;
- render the full article body in the feed.

## 18. SEO, RSS, and translation contract

- Canonical URLs must be stable.
- Draft and private workflow pages are `noindex` and absent from sitemap/RSS.
- Structured data must describe only public facts present on the page.
- Retraction and correction metadata must match the visible public notice.
- RSS is separately gated.
- Automatic translation cannot self-publish.
- Every translation links to its source article and records translator/reviewer responsibility.
- All interface strings must be translation-ready; American English is the initial interface language.

## 19. Accessibility contract

Phase 4 public and administrative interfaces must support:

- keyboard-only operation;
- visible focus;
- semantic headings and landmarks;
- labelled controls and understandable errors;
- live regions that do not over-announce;
- 320 CSS pixel viewport without loss of essential functionality;
- 200% zoom usability;
- reduced-motion preference;
- usable nested and tabular content;
- no keyboard trap.

## 20. Performance contract

- Phase 4 assets load only where required.
- Public lists are bounded and paginated.
- Source retrieval avoids N+1 queries.
- Large options are not autoloaded without necessity.
- Images use responsive markup and lazy loading where appropriate.
- Cache keys include all dimensions required to prevent language, role, preview, correction, and retraction leakage.

## 21. Audit contract

Audit events include, where applicable:

- article creation;
- submission;
- assignment;
- review decision;
- fact-check decision;
- medical-review decision;
- scheduling;
- publication;
- breaking status change;
- correction;
- retraction;
- emergency disable;
- relevant settings changes.

Audit records must not store raw patient identifiers, authentication tokens, full private notes, raw IP addresses, or full user agents.

## 22. Version, migration, and rollback contract

- Target feature line is `1.2.0`; implementation commits do not automatically promote the plugin header.
- Schema changes must be additive, versioned, and idempotent.
- No destructive cleanup occurs during ordinary activation or upgrade.
- Deactivation does not delete editorial data.
- Uninstall deletion, if ever offered, requires an explicit separate policy and confirmation.
- Rollback must restore the prior known-good plugin package and, where required, the verified database backup.

## 23. Testing contract

Each checkpoint requires automated tests plus manual staging evidence. Required coverage includes:

- clean and upgrade installation;
- Phase 2 and Phase 3 regression;
- role/capability and IDOR tests;
- state transitions;
- source privacy;
- draft/query/cache isolation;
- corrections/retractions;
- scheduling and timezone behavior;
- accessibility structure;
- responsive behavior;
- package structure and SHA-256 integrity;
- backup and rollback restoration.

## 24. Merge and release gate

No Phase 4 pull request is approved for merge or live deployment merely because code tests pass.

Merge approval requires, at minimum:

- frozen contracts unchanged or explicitly re-approved;
- all checkpoint tests green;
- exact tested 40-character commit SHA;
- completed Hostinger staging checklist;
- verified backup;
- verified rollback restoration;
- zero open critical defects;
- explicit WordPress administrator acceptance;
- separate reviewed version-promotion commit.

Live deployment remains a separate explicit decision after merge.

## 25. Contract-change procedure

A frozen identifier, status, role boundary, public route, REST route, error shape, privacy boundary, or release gate may change only through:

1. a documented contract amendment;
2. impact analysis;
3. updated tests;
4. review before dependent implementation;
5. migration and rollback notes where applicable.

Unrecorded contract drift is a defect.
