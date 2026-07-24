# Phase 5 Final Completion Plan
## Complete Editorial News, Global Newsroom, Release, and Operations

**Target release:** `1.2.0`  
**Planning branch:** `build/phase-5-final-completion-1.2.0`  
**Base exact head:** `f83a92d191d1d33dac0d080bef9b82a4be36ead2`  
**Document status:** Final comprehensive planning contract only. This document does not authorize coding, merge, version promotion, public-gate activation, Hostinger staging activation, or live deployment.

---

# 1. Final-phase declaration

Phase 5 is the **last implementation phase** for the Sabri Complete Home & News Feed plugin.

There will be no Phase 6, Phase 7, or later development phase for completing this plugin file.

All work previously described as Phase 4D, 4E, 4F, 4G, 4H, and 4I is consolidated into this one Phase 5 plan. Internal delivery units are called **workstreams**, not phases.

Any defect, omission, incomplete contract, missing migration, missing test, missing document, compatibility problem, security weakness, privacy weakness, accessibility defect, performance regression, staging defect, or release blocker discovered during implementation remains inside Phase 5 and must be corrected before final acceptance. It may not be postponed by renaming it as future work.

---

# 2. Final objective

Phase 5 completes the Editorial News and Global Newsroom subsystem from end to end while preserving the accepted Phase 2, Phase 3, Phase 4A, Phase 4B, and Phase 4C behavior.

The final system must provide:

1. a complete normalized source and evidence registry;
2. immutable editorial, fact-check, medical, and translation review ledgers;
3. a secure doctor/contributor submission portal;
4. operational Breaking News controls and an accessible public breaking strip;
5. complete correction, clarification, update, and retraction administration;
6. approved public source citations and correction history;
7. structured data, canonical metadata, Open Graph, RSS, News sitemap, robots, and multilingual `hreflang` behavior;
8. privacy export, erase, retention, minimization, and consent handling;
9. final security, upload, preview, abuse, and rate-limit hardening;
10. performance, cache, query, database-index, and load-test acceptance;
11. observability, diagnostics, audit integrity, cron monitoring, and operator alerts;
12. complete administration and public interfaces;
13. safe fresh installation, upgrade, migration, deactivation, reactivation, rollback, and uninstall behavior;
14. exact-head CI, immutable packages, browser and packaged matrices, one-hour soak, staging validation, release candidate, rollback package, merge validation, and post-deployment checks;
15. complete technical, editorial, privacy, release, and operator documentation.

---

# 3. Scope boundary

## 3.1 Included

Phase 5 completes the **Home & News Feed plugin and its Editorial News/Global Newsroom domain**.

It includes all remaining Newsroom, Editorial News, Home Feed News-card integration, public News distribution, admin, REST, migration, security, privacy, performance, operations, and release work described in this document.

## 3.2 Permanently outside this plugin’s scope

The following remain separate platform modules and are not missing Phase 5 work:

- Marketplace;
- Appointments;
- phone-number-based WhatsApp-alternative messaging;
- Video Wall, Reels, livestreaming, or PDF Library;
- University LMS;
- payments;
- complete Profiles redesign;
- AI assistant rebuild;
- a general political or entertainment newsroom.

Phase 5 must not create hidden coupling with these modules.

---

# 4. Definition of complete

Phase 5 is complete only when all requirements below pass on **one exact commit**:

1. Every requirement in this document has an implementation file and test/evidence mapping.
2. All source, review, submission, breaking, correction, retraction, SEO, language, security, privacy, performance, observability, migration, release, rollback, and documentation systems are complete.
3. Fresh installation works.
4. Upgrade from the accepted `1.0.0` code and data state works.
5. Re-running migrations causes no duplicate data or schema damage.
6. Partial migrations are detected and safely resumed or halted.
7. No private draft, source, reviewer, submitter, patient, account, preview, security-token, or operational data leaks through HTML, REST, JavaScript, feeds, schema, sitemap, search, caches, logs, exports, errors, or notifications.
8. Every disabled gate fails closed without deleting data.
9. Emergency Disable closes all relevant public and write surfaces immediately.
10. No automatic publication is introduced.
11. All migrations are additive, idempotent, auditable, non-destructive, and rollback-aware.
12. Source and packaged CI matrices pass.
13. Security, privacy, accessibility, performance, migration, and browser tests pass.
14. Mandatory uninterrupted QA passes for at least 3,900 seconds and exactly 13 complete cycles.
15. Staging acceptance passes with approved gate-by-gate activation.
16. Rollback is proven from the release candidate.
17. Final release, migration, staging, rollback, and post-merge evidence artifacts are retained.
18. There are zero known Critical or High defects.
19. No defect remains unclassified.
20. Any accepted Medium or Low defect has a written owner decision, bounded impact, workaround, and follow-up that does not represent missing core scope.
21. Final version promotion occurs only in a dedicated release-candidate commit after all preceding acceptance gates pass.

---

# 5. Frozen development invariants

Until the final release-candidate promotion commit:

```text
Plugin version: 1.0.0
Schema version: 1.0.0
Phase4Contracts::CHECKPOINT: 4A
All Phase 4/5 public gates: 0 by default
Automatic publication: disabled
```

Additional invariants:

- no direct write to `main`;
- all work remains on a Phase 5 feature branch and Draft PR;
- no merge without explicit owner action;
- no Hostinger staging activation until the staging gate;
- no live deployment without explicit owner authorization;
- no destructive table or data operation;
- no silent material rewriting of public articles;
- no test weakening to manufacture a pass;
- no combining evidence from different commits or workflow attempts;
- any correction changes the exact head and restarts mandatory acceptance from zero.

Final promotion values after full acceptance:

```text
Plugin version: 1.2.0
Schema version: 1.2.0
Completion checkpoint: 5
```

---

# 6. Canonical architecture

The final implementation preserves this layered structure:

```text
Bootstrap and Registration
        ↓
Domain Contracts, Policies, and State Machines
        ↓
Application Services
        ↓
Repositories and WordPress Adapters
        ↓
Private Admin / Authenticated REST / Public REST / Public Runtime
        ↓
Templates and Assets
        ↓
Diagnostics, Privacy, Audit, Release, and Operations
```

Rules:

- controllers coordinate and never contain direct persistence logic;
- repositories persist and never decide authorization;
- policies decide permissions, transitions, and public eligibility but never render HTML;
- templates receive allow-listed projections and never query or write the database;
- services return stable result objects;
- public serializers are inclusion-based;
- each write is authenticated, capability-checked, validated, audited, and idempotency-aware;
- browser-originated writes also require nonce and strict HTTP method validation;
- no module writes directly into another module’s private tables;
- optional Phase 3 integrations use adapters and cannot become hard boot dependencies;
- missing optional dependencies and disabled gates fail safely;
- public caches are treated as public audiences;
- one canonical source of truth is defined for every state and field.

---

# 7. Final feature gates

The final controlled gates remain:

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

Rules:

- unknown gates are rejected;
- defaults remain disabled;
- an unchecked administrator setting is stored disabled;
- each gate closes its public and write surfaces without deleting data;
- Emergency Disable overrides every public and write surface;
- staging enables one gate at a time before testing the approved combined matrix;
- activation, upgrade, tests, package build, merge, and deployment never enable gates automatically;
- gate changes are audited and invalidate only relevant caches;
- gate status never leaks hidden capabilities or private object existence.

---

# 8. Roles and capabilities

The final logical roles remain compatible with existing WordPress roles:

- Administrator;
- Founder;
- Editor-in-Chief;
- Managing Editor;
- Section Editor;
- Medical/Scientific Reviewer;
- Reporter/Contributor;
- Verified Doctor Submitter;
- Translator;
- Reader.

Required capabilities include:

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

Phase 5 adds narrow capabilities where required rather than overloading broad administrator permissions, including submission assignment, review assignment, privacy operations, release-readiness inspection, and observability access.

Authorization rules:

- actor identity comes only from the authenticated session;
- submitted user, author, reviewer, or owner IDs never replace the current actor;
- assignment of another user requires a separate capability;
- contributors and verified doctor submitters cannot self-publish;
- translators cannot alter the source-language article;
- medical reviewers do not automatically gain publishing or settings authority;
- reviewer, editor, and submitter object-level ownership/state checks are mandatory;
- UI hiding is never authorization;
- private object authorization failures avoid existence disclosure.

---

# 9. Final canonical file responsibility map

Files may be consolidated only where responsibilities remain explicit. Expected final responsibilities include:

```text
includes/
  class-news-source-repository.php
  class-news-review-repository.php
  class-news-correction-repository.php
  class-news-submission-repository.php
  class-news-submission-service.php
  class-news-breaking-service.php
  class-news-schema.php
  class-news-social-meta.php
  class-news-rss.php
  class-news-sitemap.php
  class-news-language-service.php
  class-news-privacy.php
  class-news-rate-limit.php
  class-news-observability.php
  class-news-release-readiness.php
  class-news-migrations.php
  class-rest-news-sources.php
  class-rest-news-reviews.php
  class-rest-news-submissions.php
  class-rest-news-operations.php

admin/
  class-news-submission-list-table.php
  class-news-review-list-table.php
  class-news-source-admin.php
  class-news-correction-admin.php
  class-news-breaking-admin.php
  class-news-release-admin.php
  views/newsroom-sources.php
  views/newsroom-review.php
  views/newsroom-submissions.php
  views/newsroom-breaking.php
  views/newsroom-corrections.php
  views/newsroom-retractions.php
  views/newsroom-release-readiness.php

public/
  existing public News runtime and routing
  controlled feed, sitemap, schema, breaking, and language adapters

assets/css/
  final newsroom additions
  submission form
  breaking strip
  source/citation presentation
  correction/retraction history

assets/js/
  source editor
  review forms
  submission form
  breaking controls
  release-readiness diagnostics

templates/
  news-sources.php
  news-correction-history.php
  news-breaking-strip.php
  news-submission-form.php
  news-submission-status.php
  language/canonical adapters where required

tests/
  phase5 source/review tests
  phase5 submission tests
  phase5 breaking/correction tests
  phase5 SEO/language tests
  phase5 security/privacy tests
  phase5 migration/upgrade tests
  phase5 performance/observability tests
  phase5 browser/accessibility tests
  phase5 packaged integration tests
  phase5 final soak and staging workflows
```

A final traceability document must map every numbered Phase 5 requirement to at least one source file and one automated or manual evidence item.

---

# 10. Database and migration contract

## 10.1 Required normalized tables

The final schema includes:

```text
{$wpdb->prefix}sabri_news_sources
{$wpdb->prefix}sabri_news_reviews
{$wpdb->prefix}sabri_news_corrections
{$wpdb->prefix}sabri_news_submissions
{$wpdb->prefix}sabri_news_submission_files
```

Breaking state may remain canonical controlled metadata plus append-only audit records only if lifecycle, concurrency, expiry, and history tests prove one unambiguous source of truth.

## 10.2 Source table minimum fields

- primary key;
- News article ID;
- source title;
- publisher/institution;
- normalized URL;
- URL hash;
- source type;
- evidence class;
- primary-source flag;
- publication date;
- accessed date;
- DOI/ISBN/reference identifier;
- approved public citation;
- private editorial note;
- creator ID;
- verifier ID;
- verification state;
- affiliation/sponsorship/conflict fields where material;
- created/updated UTC;
- retirement state without destructive deletion.

Required indexes include News ID, URL hash, evidence class, verifier, verification state, and duplicate-prevention keys.

## 10.3 Review table minimum fields

- review ID;
- News ID;
- review type: editorial, fact-check, medical, translation;
- decision;
- reviewer ID;
- private note;
- approved public disclosure;
- source revision ID;
- checklist version;
- created/updated UTC;
- superseded or withdrawn state;
- optimistic-lock version or equivalent concurrency field.

## 10.4 Correction table minimum fields

- correction ID;
- News ID;
- correction type: minor, clarification, material, medical/safety, evidence update, retraction;
- public note;
- private reason;
- reason category;
- previous revision ID;
- corrected/retracted revision ID;
- requester ID;
- approver ID;
- effective UTC;
- created UTC;
- immutable public-history sequence.

## 10.5 Submission table minimum fields

- submission ID;
- submitter user ID;
- submitter role snapshot;
- headline, summary, body, proposed taxonomies;
- language and article type;
- authorship/rights declaration;
- conflict disclosure;
- sponsorship/paid-content declaration;
- AI-assistance declaration;
- patient-identification declaration;
- emergency-content declaration;
- workflow state;
- assigned editor;
- request-information message;
- rejection category;
- converted News ID;
- created/updated/submitted UTC;
- retention/erase state;
- optimistic-lock version.

## 10.6 Submission-file table minimum fields

- file ID;
- submission ID;
- attachment ID;
- original sanitized filename;
- MIME type;
- size;
- checksum;
- uploader ID;
- validation state;
- rights/consent status;
- created UTC.

## 10.7 Migration rules

- additive and `dbDelta` compatible where appropriate;
- exact schema manifest and checksum;
- idempotent re-entry;
- resumable batches;
- migration lock/concurrency protection;
- no unrelated table changes;
- no destructive down-migration;
- database capability preflight;
- required columns, indexes, defaults, and constraints verified after migration;
- partial migration detected and safely resumed or halted;
- migration audit retained;
- schema version changes only after post-migration verification;
- backup and rollback instructions generated before staging migration;
- fresh-install and upgrade paths use the same canonical schema definitions;
- multisite behavior is explicitly supported or explicitly rejected with a safe diagnostic;
- no activation request performs long unbounded migration work synchronously.

---

# 11. Source registry and evidence ledger

## 11.1 Controlled source types

- primary research;
- systematic review/meta-analysis;
- guideline/consensus;
- official public-health/government release;
- institutional statement;
- book/reference work;
- classical homeopathic text;
- direct interview;
- press release;
- reputable secondary reporting;
- internal platform announcement;
- controlled administrator-defined other type.

## 11.2 Evidence classes

The frozen minimum evidence classes remain:

```text
primary
authoritative-secondary
supporting
press-release
unverified
rejected
```

Evidence labels must not imply certainty beyond the editorial policy. Public disclosure labels and private editorial classifications remain separate.

## 11.3 Source operations

- create, edit, verify, retire, restore;
- duplicate detection by normalized URL hash and identifier;
- DOI and canonical URL normalization;
- bounded source lists and search;
- capability and article-ownership validation;
- rejected sources cannot satisfy publication prerequisites;
- private notes never enter public output;
- a source referenced by published/corrected/retracted content cannot be hard-deleted;
- source changes invalidate only affected public caches;
- every material change is audited;
- source records store metadata and citation, not copied third-party articles.

## 11.4 Optional metadata fetching

No arbitrary server-side URL fetch is enabled by default.

Any optional fetch service must implement:

- HTTP/HTTPS allow-list;
- blocked private, loopback, link-local, reserved, and metadata-service addresses;
- DNS rebinding checks before and after resolution;
- redirect count limit;
- content-length and streamed-size limit;
- MIME allow-list;
- short connect/read timeout;
- no credentials or cookies;
- no automatic execution or parsing of active content;
- safe user-agent;
- audit and rate limit;
- no fetch result becoming publication evidence without human verification.

## 11.5 Public source projection

Public output may expose only approved:

- citation label;
- title;
- publisher;
- safe HTTP(S) URL;
- publication/access date;
- DOI/reference identifier;
- public evidence/disclosure label.

It must exclude private notes, verifier accounts, anonymous-source identity, internal confidence, private contact data, and unpublished verification material.

---

# 12. Review ledgers

## 12.1 Review types

```text
editorial
fact-check
medical
translation
```

## 12.2 Required behavior

- assignment requires explicit capability;
- reviewers cannot impersonate another reviewer;
- decisions and checklists are allow-listed and versioned;
- decisions link to an exact article revision;
- later material revisions invalidate earlier approvals where policy requires;
- withdrawn and superseded reviews remain in history;
- private notes remain private;
- only approved reviewer identity/disclosure may become public;
- publication prerequisites resolve from authoritative ledger state;
- duplicate review submissions are idempotent;
- concurrent decisions use optimistic locking or equivalent conflict protection;
- separation-of-duty rules are enforced;
- review decisions, assignment, withdrawal, and invalidation are audited.

## 12.3 Medical-review enforcement

Medical review is mandatory when controlled article-type, content, claim, or safety rules require it.

Missing, failed, expired, withdrawn, superseded, or revision-invalidated medical review must block publication or return the article to the required review state.

## 12.4 Translation review

- source and translation revisions are linked;
- translator cannot modify the source article;
- medical terminology requires competent review where material;
- correction/retraction of the source article marks translations for synchronized review;
- machine translation may assist drafting but never self-publishes.

---

# 13. Doctor and contributor submission portal

## 13.1 Eligibility

Only authenticated users with an approved submission capability may submit.

Anonymous public submissions are excluded unless explicitly added inside Phase 5 with a complete privacy, abuse, moderation, and test contract.

## 13.2 Submission states

```text
draft
submitted
needs-information
under-editorial-assessment
accepted-for-conversion
converted-to-news-draft
rejected
withdrawn
archived
```

Unknown transitions fail closed.

## 13.3 Submitter experience

- create and edit own draft;
- preview only own submission;
- submit after prerequisites pass;
- view safe status;
- receive and answer requests for information;
- withdraw before conversion where policy permits;
- view the converted public article only after publication;
- never view private editor/reviewer notes;
- never enumerate another user’s submissions.

## 13.4 Required declarations

- authorship and publication rights;
- media rights/permission;
- conflicts of interest;
- sponsorship/payment;
- absence of unauthorized patient identifiers;
- emergency-content declaration;
- AI assistance or AI-generated media disclosure;
- content-policy acceptance.

## 13.5 Input and file validation

- bounded title, summary, body, taxonomy, and language fields;
- stricter HTML allow-list than trusted editors;
- actual MIME verification;
- extension allow-list;
- file count and size limits;
- checksum and duplicate control;
- safe generated filenames;
- executable, scriptable, archive, polyglot, and ambiguous files rejected;
- SVG disabled unless a dedicated sanitizer and test suite are completed;
- patient/contact/identity-document patterns blocked or escalated;
- metadata cleaning where appropriate;
- identifiable clinical media prohibited until documented consent workflow is complete;
- per-user and per-IP abuse controls without exposing IPs publicly;
- duplicate submission protection.

## 13.6 Editorial conversion

- conversion creates a separate News draft;
- the original submission remains immutable and linked;
- no automatic publication;
- public authorship is resolved by policy, not raw submitted identity fields;
- files are copied or linked only after validation;
- conversion is idempotent;
- request-information, rejection, acceptance, conversion, and withdrawal are audited;
- submitter notifications contain safe status only.

---

# 14. Breaking News system

## 14.1 States

```text
inactive
scheduled
active
expired
cancelled
superseded
```

## 14.2 Administrative controls

- breaking eligibility requires a publicly eligible article;
- explicit `manage_breaking_news` capability;
- confirmation for activation/cancellation;
- UTC start and expiry;
- bounded priority;
- maximum active-story count;
- deterministic ordering;
- no duplicate active record per article;
- no activation outside the approved window;
- expiry cleanup is idempotent;
- missed expiry is detected and repaired;
- stale read paths reject expired breaking state even if cron fails;
- retraction immediately removes breaking presentation;
- gate off and Emergency Disable immediately hide the public strip;
- article publication remains a separate explicit operation;
- cache, RSS, schema, and public strip invalidate together;
- activation, cancellation, expiry, and supersession are audited.

## 14.3 Public breaking strip

- accessible landmark and heading;
- visible text label, not color alone;
- keyboard-safe canonical links;
- reduced-motion compliance;
- no uncontrolled auto-rotating carousel;
- pause mechanism if any motion exists;
- no repetitive screen-reader announcements;
- bounded number of stories;
- no private schedule, editor identity, or internal priority;
- works without JavaScript;
- safe empty, disabled, expired, and emergency states.

---

# 15. Correction, update, and retraction administration

## 15.1 Classes

```text
minor
clarification
material
medical-safety
evidence-update
retraction
```

## 15.2 Correction workflow

1. request correction;
2. freeze last-approved public snapshot;
3. record private reason and affected claim;
4. assign required review;
5. prepare corrected revision;
6. compare previous and corrected revisions;
7. approve or reject;
8. publish approved public note and corrected projection;
9. invalidate public cache, RSS, schema, sitemap modification time, related content, and Home Feed cards;
10. retain immutable history.

## 15.3 Retraction workflow

- explicit high-level capability;
- mandatory reason category;
- mandatory safe public notice;
- responsible approver;
- hidden original body remains private;
- normal Home Feed and recommendation promotion removed;
- canonical route preserves accountability notice or follows the frozen safe policy;
- RSS, sitemap, schema, search, and interactions follow explicit retraction rules;
- no silent undo;
- restoration requires a new audited decision and revision relationship;
- historical records are not hard-deleted.

## 15.4 Public history projection

Public history may expose only approved:

- correction/retraction class;
- date/time;
- public note;
- safe revision relationship;
- responsible public institution where policy permits.

It must not expose private deliberation, requester identity, reviewer notes, hidden harmful text, or account identifiers.

---

# 16. Complete administrative Newsroom

The final menu is:

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
    ├── Translation Review
    ├── Editorial Calendar
    ├── Scheduled News
    ├── Breaking News
    ├── Sources
    ├── Corrections
    ├── Retracted News
    ├── Taxonomies
    ├── Settings
    ├── System Check
    ├── Audit Log
    └── Release Readiness
```

Every screen requires:

- direct capability check;
- object-level policy check;
- nonce-protected state changes;
- bounded queries and pagination;
- accessible headings, labels, tables, controls, errors, notices, and focus behavior;
- no private data in URLs or browser history where avoidable;
- no raw SQL in view code;
- no action based solely on hidden form fields;
- clear empty, disabled, loading, error, conflict, and permission states.

The Overview includes bounded cards for drafts, needs sources, reviews, schedules, correction pending, active/expired breaking state, submissions, migration health, cron failures, cache health, and release blockers.

---

# 17. Complete public News experience

The accepted Phase 4C public routes remain authoritative:

```text
/news/
/news/{article-slug}/
/news/section/{slug}/
/news/topic/{slug}/
/news/country/{slug}/
/news/region/{slug}/
/news/type/{slug}/
```

Phase 5 completes public presentation with:

- approved source/citation section;
- public evidence/limitation labels;
- complete correction/retraction history;
- active Breaking News strip;
- language/translation links;
- canonical and social metadata;
- RSS and sitemap behavior;
- most-read/trending only if implemented without privacy-invasive tracking and with a bounded, documented signal;
- safe handling of corrected, retracted, archived, translated, and superseded content;
- approved Phase 3 interactions;
- no private or personalized data in shared caches.

Public output must remain usable without JavaScript.

---

# 18. SEO, canonical, schema, RSS, sitemap, and social metadata

## 18.1 Canonical and robots

- one canonical URL per public article and language;
- explicit pagination canonical rules;
- private, preview, submitted, scheduled, and internal states never canonicalize publicly;
- safe 404 or `noindex` behavior where required;
- query filters do not create uncontrolled indexable duplicates;
- correction, retraction, archive, and translation canonical behavior is explicit;
- canonical output is tested against duplicate SEO-plugin output.

## 18.2 Structured data

Allowed structures may include:

- `NewsArticle` or appropriate subtype;
- `Organization`;
- approved `Person`;
- `ImageObject`;
- `BreadcrumbList`;
- `WebPage` or collection structures.

Schema must never expose private notes, unapproved people, patient/account data, preview links, hidden retracted body, unsupported claims, internal IDs, nonces, or reviewer-private data.

## 18.3 Social metadata

- Open Graph title, description, image, URL, and type;
- Twitter/X-compatible card metadata;
- safe fallback image and summary;
- image rights and credit policy;
- corrected/retracted state reflected where policy requires;
- no duplicate competing tags;
- no external tracking scripts.

## 18.4 RSS

Controlled routes:

```text
/news/feed/
/news/section/{slug}/feed/
```

Rules:

- gate-controlled;
- public eligible projections only;
- bounded item count;
- canonical links;
- safe HTML and XML escaping;
- corrected update dates;
- explicit retraction behavior;
- no drafts, submissions, schedules, private sources, or reviewer data;
- safe conditional requests and cache headers;
- feed validation tests.

## 18.5 News sitemap

Controlled route:

```text
/news-sitemap.xml
```

Rules:

- public eligible canonical URLs only;
- bounded index/chunk strategy;
- last-modified reflects approved public changes;
- translations and `hreflang` relationships are consistent;
- retracted/archived behavior is explicit;
- no private, preview, submission, scheduled, or gate-disabled URLs;
- XML escaping and cache invalidation tested.

---

# 19. Translation and multilingual relationships

- controlled BCP 47 language allow-list;
- one source-language article relationship;
- translation group identifier;
- translator and reviewer identity resolved by policy;
- translation draft cannot self-publish;
- source facts, citations, disclaimers, correction history, labels, and evidence state remain linked;
- material source correction/retraction flags translations for review;
- `hreflang` links are reciprocal and canonical;
- missing or private translation does not produce a public alternate link;
- fallback language behavior is explicit and never leaks private translations;
- machine translation is drafting assistance only;
- medical terminology receives required review;
- translation search/feed/sitemap behavior is bounded and tested.

---

# 20. REST API completion

The public read routes remain projection-only.

Authenticated/private routes may include controlled operations for:

- article create/update/archive;
- submit/review/fact-check/medical-review/translation-review;
- publish and schedule;
- source list/create/update/verify/retire;
- correction request/approve/reject;
- retraction request/approve;
- breaking schedule/activate/cancel;
- current user’s submissions;
- submission create/update/submit/withdraw/files;
- diagnostics and release readiness for authorized administrators.

Every write route requires:

- authenticated session;
- valid WordPress REST nonce or approved authentication;
- exact capability;
- object-level policy;
- enabled gate;
- strict HTTP method;
- strict positive identifiers;
- bounded input sizes;
- allow-listed enumerations;
- rate limiting where abuse is possible;
- idempotency or concurrency protection;
- safe audit record;
- stable error response;
- no private-object enumeration.

Public responses omit emails, phones, WhatsApp numbers, addresses, login names, capabilities, IPs, user agents, nonces, hashes, patient identifiers, private notes, and unpublished decisions.

---

# 21. Stable result and error contract

Services return:

```php
array(
    'success' => true,
    'code'    => 'stable_success_code',
    'data'    => array(),
)
```

or:

```php
array(
    'success' => false,
    'code'    => 'stable_error_code',
    'message' => 'Safe user-facing message.',
    'field'   => 'optional_field',
    'status'  => 400,
)
```

Required error families include:

- gate disabled;
- permission denied without enumeration;
- object not found;
- invalid identifier;
- invalid state transition;
- missing prerequisite;
- source invalid/duplicate/rejected;
- review missing/conflict/superseded;
- submission invalid/not-owned/conflict/rate-limited;
- upload invalid/too-large/type-blocked/privacy-blocked;
- breaking invalid/expired/limit-reached/conflict;
- correction/retraction invalid/conflict;
- migration locked/incomplete/failed;
- cache/cron/diagnostic safe failure;
- release readiness blocked.

Messages never expose SQL, filesystem paths, stack traces, private notes, secret tokens, raw personal data, or hidden object existence.

---

# 22. Security hardening

## 22.1 Threat coverage

Tests and code must cover:

- authentication and privilege escalation;
- CSRF;
- IDOR and object enumeration;
- stored and reflected XSS;
- SQL injection;
- unsafe redirects;
- SSRF;
- malicious uploads and polyglots;
- MIME spoofing;
- path traversal;
- duplicate/race requests;
- cache poisoning and private-response caching;
- preview-token leakage;
- stale breaking/retraction caches;
- excessive requests and workflow abuse;
- direct database edits bypassing workflow where detectable;
- notification or schema data overexposure.

## 22.2 Preview security

Unpublished preview requires an authenticated authorized user or a short-lived purpose-bound token.

Token requirements:

- cryptographically random;
- stored as one-way hash;
- article and scope bound;
- expiry bound;
- revocable;
- invalidated by relevant state changes;
- excluded from analytics, public caches, logs, notifications, referrers, and share links;
- preview response uses private/no-store and `noindex` headers.

## 22.3 Rate limiting

Rate limits apply to submission, file upload, preview token generation, review writes, source metadata fetch, breaking actions, correction requests, and other abuse-prone operations.

Limits must be:

- actor/action scoped;
- bounded and configurable;
- privacy-minimized;
- fail-safe;
- compatible with administrators and automated cron callbacks;
- tested for reset, concurrency, bypass attempts, and safe error output.

## 22.4 Secrets and logging

- no nonces, tokens, cookies, passwords, source-private identities, patient data, or raw request bodies in logs;
- operational logs use safe event IDs and category summaries;
- debug output is disabled in public responses;
- error logs cannot become a shadow private database;
- retention and purge rules are documented.

---

# 23. Clinical and personal-data privacy

## 23.1 Scanner categories

The privacy scanner detects or flags:

- email addresses;
- phone and WhatsApp numbers;
- CNIC/national identity numbers;
- passport identifiers;
- full patient names where detectable;
- residential addresses;
- medical-record or registration numbers;
- prescriptions/reports/screenshots with identifiers;
- identifiable clinical photographs;
- hidden metadata in uploads.

Scanner messages expose category names only, not matched private values.

Automated scanning is not publication approval. Human review remains required.

## 23.2 Consent and media rights

Every public media item requires:

- source/photographer;
- copyright owner;
- license or permission basis;
- caption;
- descriptive alt text;
- consent status where relevant;
- AI-generated or materially altered disclosure where applicable.

Identifiable clinical media remains prohibited unless a complete private consent workflow is implemented with scope, duration, withdrawal, authority, secure storage, privacy approval, and correction/removal procedure.

## 23.3 Privacy export

WordPress privacy export integration includes only the requesting user’s eligible submission/account-linked data.

It excludes:

- another user’s data;
- private reviewer/editor notes not owned by the requester;
- confidential source identity;
- security and abuse signals that would enable bypass;
- audit data that must be retained for integrity where policy/law permits.

## 23.4 Erase and retention

- draft/withdrawn/rejected submission retention is documented and configurable;
- erase requests anonymize or remove eligible personal fields;
- published accountability, correction, retraction, legal-hold, security, and audit records follow explicit retention exceptions;
- erase operations are idempotent and audited;
- attachments are removed only when not shared or legally retained;
- no erase action corrupts article/source/review relationships.

---

# 24. Accessibility and responsive behavior

All admin and public additions must meet the project’s accessibility contract:

- semantic headings and landmarks;
- complete keyboard operation;
- visible focus;
- correct labels, descriptions, errors, and status messages;
- accessible tables and bulk actions;
- no color-only meaning;
- reduced-motion support;
- forced-colors support;
- high contrast;
- 200% and 400% zoom usability;
- responsive mobile layouts;
- no keyboard traps;
- no focus loss after async operations;
- meaningful image alt text;
- accessible correction/retraction and evidence labels;
- no uncontrolled screen-reader announcements;
- JavaScript enhancements preserve no-JavaScript functionality.

Automated accessibility tests are supplemented by keyboard and screen-reader-oriented manual staging checks.

---

# 25. Performance, cache, and database acceptance

## 25.1 Query rules

- no N+1 source, review, taxonomy, or author queries;
- bounded pagination everywhere;
- allow-listed sort fields;
- prepared SQL values;
- escaped LIKE search;
- appropriate composite indexes;
- aggregate dashboard queries are bounded;
- templates never query directly;
- large migrations and maintenance run in resumable batches.

## 25.2 Cache dimensions

Public cache keys include, where relevant:

- site/blog ID;
- plugin/public-state generation;
- gate generation;
- Emergency Disable generation;
- language;
- article/public revision generation;
- taxonomy/filter/page dimensions;
- breaking generation;
- correction/retraction generation;
- RSS/sitemap/schema generation.

No current-user, nonce, preview, submission, reviewer, or private-source data enters a shared cache.

## 25.3 Invalidation

Publication, update, correction, retraction, source-public projection change, taxonomy change, language relation change, breaking state, gate change, and Emergency Disable invalidate all affected surfaces consistently.

## 25.4 Load-test datasets

Acceptance includes at least:

- normal dataset;
- 1,000 News articles with realistic sources/reviews;
- 10,000 News articles with bounded archive/search/sitemap tests;
- submission and audit backlogs;
- concurrent public reads;
- concurrent authorized writes on conflict-sensitive operations.

## 25.5 Performance budgets

Budgets are measured on controlled CI and Hostinger staging against the accepted Phase 4C baseline.

Required outcomes:

- no material regression in ordinary Home Feed behavior;
- no unbounded memory or execution growth;
- warm-cache public News p95 remains within the approved staging ceiling;
- cold-cache behavior remains within a documented ceiling;
- error rate remains below the approved threshold;
- database query counts remain bounded;
- cache invalidation completes without stale private/public state;
- RSS and sitemap generation remain chunked and bounded;
- admin queues remain usable at the large dataset tier.

Exact numeric staging ceilings are recorded before implementation QA and cannot be relaxed after seeing a failure without an explicit owner-approved risk decision.

---

# 26. Observability, diagnostics, and audit integrity

## 26.1 Health signals

Diagnostics report safely:

- schema and migration status;
- required table/column/index status;
- gate and Emergency Disable state;
- scheduled publication queue health;
- missed schedules and retry state;
- breaking expiry health;
- cache generation and invalidation health;
- source/review/correction consistency;
- submission queue backlog;
- privacy scanner status;
- RSS/sitemap/schema status;
- last successful cron runs;
- package/version/checkpoint consistency;
- release blockers.

## 26.2 Operator alerts

Authorized administrators receive safe notices for:

- failed or partial migration;
- repeated cron failure;
- missed schedule;
- stale breaking state;
- invalid correction/retraction linkage;
- missing required indexes;
- cache inconsistency;
- release manifest mismatch;
- privacy/security scanner failure.

Alerts never expose private notes or personal data.

## 26.3 Audit integrity

- append-only records for material actions;
- authenticated actor and UTC time;
- object and action type;
- safe before/after state identifiers;
- no secret or private-body snapshots in generic audit metadata;
- bounded admin viewing;
- export restricted by capability;
- integrity manifest or chained digest where practical;
- audit failures block high-risk transitions where integrity is required;
- audit retention and privacy exceptions documented.

---

# 27. Notifications and Phase 3 integrations

Where approved gates and adapters exist:

- publication, correction, retraction, request-information, submission status, assignment, and review events may create privacy-minimized notifications;
- notification payloads use public/safe labels and object links only;
- no private notes, source-private data, patient identifiers, nonces, or tokens;
- duplicate events are idempotent;
- failure to notify does not roll back an otherwise valid editorial transaction unless policy explicitly requires it;
- retracted or private objects do not remain accessible through stale notifications;
- comments, reactions, saves, reports, and views remain governed by Phase 3 object-type policies;
- comments are never treated as verified sources.

---

# 28. Installation, upgrade, deactivation, reactivation, rollback, and uninstall

## 28.1 Fresh install

- registers code safely with all gates disabled;
- creates canonical schema once;
- verifies schema;
- does not create public News content;
- does not enable public routes beyond disabled-safe behavior;
- records no fake acceptance state.

## 28.2 Upgrade from accepted `1.0.0`

- detects current schema/version/checkpoint;
- creates new tables/indexes additively;
- migrates any canonical metadata safely;
- preserves all existing articles, schedules, snapshots, audit, and Phase 2/3 data;
- leaves all new gates disabled;
- produces migration report;
- supports interruption and resume;
- verifies exact counts and relationships.

## 28.3 Deactivation/reactivation

- deactivation stops cron and runtime surfaces safely;
- data is preserved;
- reactivation restores registration and schedules without duplicates;
- expired breaking and missed schedule state is repaired safely;
- no automatic publication or gate activation.

## 28.4 Rollback

The release package includes:

- previous accepted plugin ZIP;
- new release candidate ZIP;
- SHA-256 files;
- tracked-file manifests;
- schema manifest;
- migration report;
- rollback runbook;
- data compatibility statement;
- gate shutdown procedure;
- Emergency Disable procedure.

Code rollback must not require destructive database rollback. New tables/columns may remain dormant under the old code where compatibility is proven.

## 28.5 Uninstall

Uninstall is conservative:

- ordinary plugin removal does not silently delete editorial/accountability data;
- destructive removal requires an explicit separately confirmed administrator policy;
- published correction/retraction and audit records are protected;
- multisite and shared-attachment relationships are respected;
- uninstall behavior is documented and tested.

---

# 29. Documentation deliverables

Required final documents:

1. complete implementation document;
2. architecture and data-flow diagram;
3. database schema manifest;
4. migration and upgrade guide;
5. role/capability matrix;
6. editorial workflow manual;
7. source and evidence manual;
8. review and medical-safety manual;
9. submission-portal manual;
10. breaking-news manual;
11. correction/retraction manual;
12. privacy and retention guide;
13. security threat model and incident response;
14. cache and performance guide;
15. observability and cron runbook;
16. staging acceptance checklist;
17. release and rollback runbook;
18. post-merge and post-deployment validation checklist;
19. requirement-to-code-to-test traceability matrix;
20. known-defect register showing zero unclassified blockers.

Documentation must match the exact release candidate and may not describe nonexistent controls.

---

# 30. Workstream execution order

These are workstreams inside the single Phase 5, not additional phases.

## Workstream A — Contract and traceability freeze

- convert this plan into requirement IDs;
- map existing Phase 4 contracts and accepted Phase 4C behavior;
- define exact final file map, schema manifest, error codes, gates, roles, routes, and acceptance evidence;
- no coding before contradictions are resolved.

## Workstream B — Schema, repositories, and migrations

- create normalized tables and repositories;
- implement idempotent migrations, locks, resume, verification, and reports;
- complete fresh-install and upgrade tests.

## Workstream C — Sources and review ledgers

- source registry;
- editorial, fact-check, medical, translation ledgers;
- publication prerequisite integration;
- admin UI, REST, privacy, audit, and tests.

## Workstream D — Submission portal

- submitter UI and REST;
- file security;
- state machine;
- editor queues;
- conversion and notifications;
- privacy export/erase and tests.

## Workstream E — Breaking, corrections, and retractions

- full lifecycle services and admin UI;
- public strip and history;
- cache/feed/schema integration;
- concurrency, expiry, and accountability tests.

## Workstream F — SEO, feeds, sitemap, social, and languages

- canonical/robots;
- structured data;
- Open Graph/social metadata;
- RSS and section feeds;
- News sitemap;
- translation relationships and `hreflang`;
- validation and privacy tests.

## Workstream G — Security, privacy, and abuse hardening

- preview tokens;
- upload security;
- SSRF policy;
- rate limits;
- privacy scanner;
- retention/export/erase;
- complete negative test matrix.

## Workstream H — Performance and observability

- index audit;
- query/cache optimization;
- large-dataset and concurrency tests;
- diagnostics, alerts, cron health, and audit integrity.

## Workstream I — Complete UX and accessibility

- admin and public completeness;
- keyboard, zoom, screen-reader, reduced-motion, responsive behavior;
- no-JavaScript fallbacks;
- browser tests.

## Workstream J — Package, migration, staging, release, and rollback

- immutable package;
- exact-head CI;
- mandatory soak;
- staging migration and gate matrix;
- release candidate promotion;
- rollback proof;
- merge and post-merge validation;
- live deployment only after separate explicit authorization.

A workstream cannot be marked complete while any requirement assigned to it lacks code and evidence.

---

# 31. Automated test matrix

## 31.1 Unit and service tests

- validation and sanitization;
- state transitions;
- capability and ownership;
- source duplicate/verification rules;
- review invalidation and concurrency;
- submission ownership and conversion;
- breaking timing/limit/expiry;
- correction/retraction revision linkage;
- language relationships;
- cache keys/invalidation;
- privacy export/erase;
- migration idempotency and resume;
- rate limits;
- stable errors.

## 31.2 Security-negative tests

- all private states and object types;
- cross-user IDOR;
- forged actor/reviewer/author IDs;
- missing/invalid nonces;
- CSRF;
- SQL injection strings;
- XSS and unsafe URL schemes;
- path traversal;
- MIME spoofing/polyglots;
- SSRF private-network and redirect attacks;
- preview token replay/expiry/revocation;
- cache-user-state leakage;
- private notes in HTML/REST/schema/RSS/sitemap/logs;
- rate-limit bypass;
- duplicate/race requests;
- stale breaking/retraction output;
- error-message information leakage.

## 31.3 Privacy tests

- patient/contact identifiers in text and media metadata;
- scanner redaction;
- export ownership;
- erase idempotency;
- retention exceptions;
- submission attachment cleanup;
- anonymous source protection;
- audit/log minimization;
- notification minimization.

## 31.4 Accessibility tests

- keyboard-only journeys;
- focus management;
- labels and errors;
- admin tables and bulk controls;
- 200%/400% zoom;
- reduced motion;
- forced colors;
- no-JavaScript public and submission flows;
- correction/retraction and breaking announcements.

## 31.5 Migration tests

- fresh install;
- accepted `1.0.0` upgrade;
- repeated migration;
- interrupted/partial migration;
- lock contention;
- missing index repair;
- large batch resume;
- deactivation/reactivation;
- code rollback with new schema present;
- data preservation counts and hashes.

## 31.6 WordPress/PHP matrices

At minimum:

```text
WordPress latest / PHP 8.3
WordPress 6.8 / PHP 8.1
```

Both source and immutable packaged ZIP are tested.

## 31.7 Browser matrices

At minimum current stable Chromium and Firefox automation for critical public/admin journeys, plus targeted WebKit/Safari-compatible checks where the available runner supports them.

## 31.8 Regression suites

All accepted Phase 2, Phase 3, Phase 4A, Phase 4B, and Phase 4C tests remain mandatory.

---

# 32. Mandatory QA and evidence

## 32.1 Ordinary exact-head acceptance

One exact commit must pass:

- full lint;
- all Phase 5 tests;
- all prior regressions;
- static security checks;
- source WordPress matrices;
- immutable package build and SHA-256;
- required-file and forbidden-file structure checks;
- packaged WordPress matrices;
- browser/accessibility tests;
- migration/fresh-install/upgrade/rollback tests;
- performance/load tests;
- release-readiness diagnostics.

## 32.2 One-hour exact-head soak

- uninterrupted minimum: 3,900 seconds;
- exactly 13 complete cycles;
- every cycle runs behavior, security, privacy, migration consistency, regressions, static checks, and fresh package verification;
- initial/final tracked-file manifests must match;
- final package checksum verified;
- final packaged WordPress matrices pass;
- no evidence combined across attempts.

## 32.3 Extended staging observation

On Hostinger staging only and only after explicit staging authorization:

- backup verified;
- migration dry run and real staging migration;
- gates enabled one at a time;
- approved combined gate matrix;
- cron/scheduling/breaking expiry observation;
- source/review/submission/correction journeys;
- RSS/schema/sitemap validation;
- cache purge and Emergency Disable;
- performance and error-log observation;
- deactivation/reactivation;
- rollback rehearsal;
- data-preservation verification;
- no live publication side effects.

The observation period and exact operational thresholds are frozen before staging starts.

## 32.4 Post-merge validation

After owner-authorized merge:

- exact merge commit recorded;
- clean checkout and immutable package rebuild;
- checksum compared to approved source state;
- core regression and package tests rerun;
- version/checkpoint/gates verified;
- no deployment occurs merely because merge passed.

## 32.5 Post-deployment validation

Only after separate live-deployment authorization:

- backup and rollback package confirmed;
- maintenance/traffic plan followed;
- migration and schema verification;
- gate-by-gate controlled activation;
- canonical routes, feeds, sitemap, schema, and Home Feed checks;
- privacy/security smoke tests;
- cron and cache checks;
- error-rate and performance observation;
- immediate Emergency Disable/rollback criteria;
- final operator sign-off.

---

# 33. Required final artifacts

Artifact names use the exact release-candidate SHA:

```text
sabri-phase5-FINAL-COMPLETION-QA-PASSED-{sha}
sabri-phase5-ONE-HOUR-VISIBLE-QA-PASSED-{sha}
sabri-phase5-MIGRATION-QA-PASSED-{sha}
sabri-phase5-SECURITY-PRIVACY-QA-PASSED-{sha}
sabri-phase5-PERFORMANCE-QA-PASSED-{sha}
sabri-phase5-STAGING-ACCEPTANCE-PASSED-{sha}
sabri-phase5-ROLLBACK-PROVEN-{sha}
sabri-phase5-RELEASE-CANDIDATE-1.2.0-{sha}
sabri-phase5-POST-MERGE-VALIDATION-PASSED-{merge-sha}
```

Each acceptance record includes:

- exact SHA;
- run ID;
- source and packaged matrix results;
- package SHA-256;
- tracked-file manifest digest;
- schema manifest digest;
- migration report digest;
- duration and cycle count where applicable;
- test counts;
- defect list and corrections;
- gate/version/checkpoint state;
- PR or merge state;
- evidence creation/expiry details.

---

# 34. Release-readiness blocker rules

Release is blocked by:

- any Critical or High defect;
- any unclassified defect;
- private-data leak;
- authorization, IDOR, CSRF, XSS, SQL injection, SSRF, upload, preview-token, or cache-isolation failure;
- destructive or non-idempotent migration;
- missing rollback proof;
- stale breaking or retracted public output;
- source/review/correction ledger inconsistency;
- automatic publication;
- accessibility blocker in a critical journey;
- failed source or packaged matrix;
- failed one-hour soak;
- failed staging gate;
- manifest/checksum mismatch;
- version/schema/checkpoint inconsistency;
- missing required documentation or traceability;
- public gate enabled by default.

No schedule pressure overrides a blocker.

---

# 35. Final acceptance checklist

Phase 5 may be declared complete only after confirming:

## Domain and data

- [ ] canonical tables and indexes exist;
- [ ] migrations are idempotent, resumable, and audited;
- [ ] source registry is complete;
- [ ] review ledgers are complete;
- [ ] correction/retraction ledger is complete;
- [ ] submission data and files are complete;
- [ ] no ambiguous duplicate source of truth remains.

## Workflow

- [ ] publication prerequisites use authoritative ledger state;
- [ ] medical-review rules work;
- [ ] translation review works;
- [ ] submission conversion is idempotent;
- [ ] breaking lifecycle works;
- [ ] corrections and retractions preserve accountability;
- [ ] scheduling remains safe and non-duplicating;
- [ ] no automatic publication exists.

## Public distribution

- [ ] source citations are safe;
- [ ] correction/retraction history is safe;
- [ ] breaking strip is accessible;
- [ ] canonical, robots, schema, social metadata work;
- [ ] RSS and sitemap work;
- [ ] languages and `hreflang` work;
- [ ] Home Feed integration remains deterministic;
- [ ] all disabled gates fail closed.

## Security and privacy

- [ ] capability and object-level authorization pass;
- [ ] CSRF, IDOR, XSS, SQL injection, SSRF, upload, preview, and cache tests pass;
- [ ] privacy scanner and human-review boundaries work;
- [ ] export, erase, retention, and consent rules work;
- [ ] logs and notifications are minimized;
- [ ] Emergency Disable works.

## Accessibility and performance

- [ ] keyboard, focus, zoom, reduced motion, forced colors, and responsive checks pass;
- [ ] large dataset and concurrency checks pass;
- [ ] cache invalidation is consistent;
- [ ] no N+1 or unbounded queries remain;
- [ ] staging performance budgets pass.

## Operations and release

- [ ] diagnostics and alerts work;
- [ ] cron/schedule/breaking health works;
- [ ] audit integrity passes;
- [ ] fresh install and upgrade pass;
- [ ] deactivation/reactivation pass;
- [ ] rollback is proven;
- [ ] documentation and traceability are complete;
- [ ] ordinary exact-head QA passes;
- [ ] one-hour QA passes;
- [ ] staging acceptance passes;
- [ ] release candidate artifacts are retained;
- [ ] post-merge validation passes;
- [ ] live deployment remains separately authorized.

---

# 36. No-defer rule

During Phase 5 implementation, every newly discovered issue is classified as one of:

1. **Required Phase 5 correction** — must be completed before acceptance.
2. **Confirmed external module non-scope** — documented with proof that it is not part of this plugin.
3. **Owner risk decision** — only for a bounded Medium/Low issue that does not represent missing core functionality, security, privacy, migration, accessibility, or release safety.

“Future phase” is not a valid classification.

---

# 37. Final planning result

This document is the single final completion plan for the plugin.

It absorbs all previously deferred Editorial News and Global Newsroom work and defines the complete path from implementation through migration, security, privacy, performance, staging, release, rollback, merge validation, and post-deployment operations.

No additional development phase is planned after Phase 5. The plugin is complete only when this entire plan passes its exact-head acceptance contract.
