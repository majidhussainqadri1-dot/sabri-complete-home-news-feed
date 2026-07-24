# Phase 4 Security and Privacy Model — Editorial News and Global Newsroom

Target development line: `1.2.0`

Branch: `build/phase-4-editorial-news-1.2.0`

Dependencies:

- `PHASE-4-CONTRACTS.md`
- `PHASE-4-ARCHITECTURE.md`

Status: **security and privacy design — implementation and staging acceptance still required**

## 1. Security objective

Phase 4 must provide a trustworthy editorial publishing system without exposing drafts, private sources, reviewer notes, patient identifiers, account data, security tokens, or unpublished decisions.

Security is fail-closed:

- a missing permission denies the action;
- an unknown workflow state denies the transition;
- a disabled feature gate closes its surface;
- an unavailable dependency does not silently widen access;
- a cache uncertainty favors privacy over availability;
- an invalid identifier does not fall back to another object.

## 2. Protected assets

The system must protect:

- WordPress accounts and current-session identity;
- unpublished Editorial News drafts;
- contributor submissions;
- private preview links/tokens;
- editor, fact-checker, and medical-review notes;
- unpublished source records and source-confidence notes;
- correction and retraction deliberations before publication;
- patient and clinical identifiers;
- media consent and licensing records;
- REST nonces and authentication material;
- audit integrity;
- feature settings, Safe Mode, and Emergency Disable;
- backup and rollback evidence;
- public article integrity and correction history.

## 3. Threat model

Phase 4 assumes threats from:

- logged-out visitors;
- authenticated users with limited roles;
- contributors attempting privilege escalation;
- one user attempting to access another user’s drafts, submissions, Following/Saved state, or private editorial material;
- forged REST requests;
- CSRF;
- IDOR/object-enumeration attacks;
- stored and reflected XSS;
- SQL injection;
- malicious or misleading uploads;
- duplicate/race requests;
- cache poisoning or private-response caching;
- leaked preview URLs;
- excessive requests and workflow abuse;
- compromised or conflicting sources;
- accidental publication of patient or contact information;
- silent factual alteration after publication;
- stale Breaking News or retracted content remaining in cache;
- third-party notification or analytics callbacks receiving excessive data.

## 4. Trust boundaries

### 4.1 Browser to WordPress

All browser-supplied values are untrusted, including:

- user IDs;
- article IDs;
- author/reviewer IDs;
- workflow states;
- capabilities;
- source classifications;
- visibility;
- publication time;
- URLs;
- file names and MIME declarations;
- preview tokens;
- redirect destinations.

### 4.2 WordPress to database

Database output is not assumed safe for HTML. Every output context requires escaping.

### 4.3 WordPress to third parties

Notification, link-preview, analytics, schema, RSS, and other integrations receive only explicit allow-listed public or privacy-minimized data.

### 4.4 Public cache

A public cache is an external audience. No current-user, preview, nonce, draft, private source, or editorial-note data may enter a shared cache object.

## 5. Identity and authorization

### 5.1 Current-session identity

The actor is always resolved from the authenticated WordPress session.

Rules:

- submitted `user_id`, `author_id`, `reviewer_id`, or `owner_id` never replaces the current actor;
- assigning an author/reviewer requires a separate capability and validation that the target account exists;
- unauthenticated writes are rejected except explicitly public non-mutating actions;
- REST writes require a valid session-bound nonce.

### 5.2 Capability enforcement

Every administrative screen, action handler, REST permission callback, and service write checks the narrowest capability required.

UI hiding is not authorization.

Examples:

- seeing no Publish button does not replace `publish_editorial_news` validation;
- a contributor who can edit a draft cannot manage Breaking News;
- a medical reviewer cannot alter site settings merely because they can approve a medical review;
- a translator cannot edit the source-language article.

### 5.3 Object-level authorization

Authorization considers both capability and object ownership/state.

Checks include:

- actor owns draft or has edit-others capability;
- submission belongs to the current submitter or an authorized editor;
- source belongs to the requested article;
- correction belongs to the requested article;
- article is in an allowed transition state;
- reviewer is assigned or globally authorized;
- deleted/retracted/private parent objects cannot be used to bypass policy.

## 6. CSRF and request integrity

All state-changing browser actions require:

- WordPress nonce;
- authenticated session;
- capability check;
- strict HTTP method;
- server-side object validation;
- safe redirect handling where applicable.

REST routes require `X-WP-Nonce` or the approved WordPress authentication mechanism.

Nonces:

- are never stored in public cache;
- are never returned in public visitor markup;
- are never written to logs or audit metadata;
- are not accepted as proof of authorization without a current capability check.

## 7. Input validation and sanitization

Validation occurs before persistence.

### 7.1 Identifiers

- IDs must be strict positive integers.
- Arrays of IDs have bounded length and unique values.
- Negative, zero, floating, scientific-notation, malformed, or foreign-type IDs fail closed.
- Parent/child records must belong to the same expected article.

### 7.2 Text

- Headline, subtitle, summary, notes, citations, and labels have explicit maximum lengths.
- Article HTML uses an allow-list appropriate to trusted editorial content and user capability.
- Contributor submissions receive stricter HTML filtering than high-trust editor content.
- Script, event-handler, dangerous URI, iframe, and unsupported embed content are removed or rejected.

### 7.3 URLs

- Only approved schemes are accepted.
- URLs are normalized for duplicate detection.
- Public redirects are restricted to safe local or validated destinations.
- Source URLs are not fetched automatically during a write unless a separate hardened fetch service is approved.
- URL validation must resist userinfo tricks, encoded hosts, invalid ports, and javascript/data schemes.

### 7.4 Enumerations

Article type, status, evidence class, review decision, correction type, retraction reason, language, and feature keys are allow-listed.

Unknown values are rejected, not silently mapped.

## 8. Output encoding and XSS prevention

- Text output uses HTML escaping.
- Attribute values use attribute escaping.
- URLs use URL escaping and approved schemes.
- JSON uses the WordPress JSON response APIs.
- Rich article HTML is sanitized on write and rendered through an approved content path.
- Private notes are never inserted into public DOM data attributes, comments, hidden fields, or JavaScript configuration.
- Error messages do not echo unsafe raw input.

The security scanner and tests must check templates, REST serializers, admin notices, data attributes, and script localization.

## 9. SQL and repository safety

- All custom-table access occurs through repositories.
- Variable SQL values use prepared statements.
- Table/column/order identifiers come only from internal allow-lists.
- Pagination uses bounded integers.
- Search terms are escaped for LIKE operations.
- Templates and REST controllers do not execute raw SQL.
- Bulk operations remain bounded and capability-protected.
- No activation or repair action truncates or drops unrelated data.

## 10. File-upload security

### 10.1 General controls

Uploads require:

- authenticated authorized user;
- nonce;
- WordPress upload capability where appropriate;
- actual MIME/type verification;
- extension allow-list;
- bounded file size;
- malware/host scanning where available;
- safe generated filenames;
- attachment ownership and audit metadata.

### 10.2 Initial allow-list

The initial implementation should prefer common safe editorial media and documents supported by site policy. Executable, scriptable, archive, and ambiguous polyglot files are rejected unless separately reviewed.

### 10.3 Patient and identity documents

The following must be blocked from public editorial use unless a future explicit secure workflow is approved:

- CNIC/passport images;
- prescriptions containing identifiers;
- medical reports containing identifiers;
- screenshots exposing phone, WhatsApp, email, address, account, or patient details;
- identifiable clinical photographs without documented consent and authorization.

### 10.4 SVG

SVG must remain disabled unless a dedicated sanitizer, policy, and test suite are implemented.

## 11. Clinical and personal-data privacy

### 11.1 Direct identifiers

The privacy scanner and editorial review must detect and block or require removal of obvious identifiers, including:

- email addresses;
- phone and WhatsApp numbers;
- CNIC/national identity numbers;
- passport identifiers;
- explicit patient full names;
- residential addresses;
- medical-record/registration numbers;
- identifiable prescription or report images.

### 11.2 Data minimization

Editorial articles should contain only information necessary for public understanding.

- Do not publish private contact details merely because they appear in a source.
- Do not copy entire clinical records.
- Use generalized age ranges or locations when exact detail is unnecessary.
- Public author profiles use approved public fields only.
- Internal consent/licensing records are not public article metadata.

### 11.3 Scanner limitations

Automated scanning is a safety layer, not proof of privacy.

- Human review remains required for clinical content.
- Scanner errors return category names only, never the matched private value.
- A clean scan does not authorize publication without editorial review.

## 12. Source privacy and integrity

### 12.1 Public source projection

Public source data may include:

- title;
- publisher/institution;
- public URL;
- publication date;
- public citation;
- broad evidence classification where policy allows.

### 12.2 Private source projection

Private fields include:

- editorial confidence notes;
- anonymous-source identity;
- private contact information;
- reviewer comments;
- unpublished verification material;
- internal conflict or legal notes.

These fields are restricted to authorized editorial roles and excluded from public REST, HTML, RSS, schema, sitemap, notifications, and unrelated privacy exports.

### 12.3 Source conflict and commercial influence

The workflow records:

- affiliation;
- sponsorship;
- product ownership;
- paid promotion;
- research authorship;
- institutional interest.

A commercial source alone cannot automatically satisfy a medical-evidence requirement.

## 13. Preview security

Unpublished previews require either:

- authenticated authorized user; or
- a short-lived, purpose-bound, revocable preview token.

Token requirements:

- cryptographically random;
- stored as a one-way hash;
- article-bound;
- scope-bound;
- expiry-bound;
- revocable;
- invalid after publication-state changes where appropriate;
- not included in analytics, referrer-sharing links, public cache keys, logs, or notifications.

Preview responses use private/no-store cache headers and `noindex` directives.

## 14. Workflow integrity

### 14.1 State transitions

Every transition is checked server-side against an allow-listed transition map and prerequisites.

Examples:

- draft cannot publish without required sources/reviews;
- scheduled cannot publish after a retraction decision;
- a contributor cannot approve their own article where separation-of-duty policy applies;
- a reviewer cannot forge another reviewer’s decision;
- a correction cannot point to a foreign article revision.

### 14.2 Concurrency and idempotency

Operations vulnerable to duplicate requests use:

- unique natural keys;
- state comparison;
- idempotency tokens where appropriate;
- bounded retry after unique-insert races;
- atomic or compensating logic around article/review/correction changes.

Repeated Publish, Follow-like integrations, source insertion, correction, and schedule callbacks must not create uncontrolled duplicates.

## 15. Publication integrity

- Published content is tied to a specific revision and responsible editor.
- Material changes create revisions and, where required, public correction records.
- Retraction is an explicit state, not silent deletion.
- Direct database edits that bypass workflow are detectable through diagnostics where possible.
- Structured data, RSS, sitemap, caches, and Home Feed cards must update consistently after publication/correction/retraction.

## 16. Breaking News safety

- Only authorized roles can mark Breaking News.
- Start and expiry are server-validated UTC values.
- Expired Breaking status is rejected at read time even if cron fails.
- Active-story count and priority are bounded.
- Breaking presentation cannot bypass normal article visibility.
- Emergency Disable removes the Breaking surface immediately.
- A stale cache cannot preserve an expired or retracted breaking banner.

## 17. Scheduling safety

- Scheduling requires `schedule_editorial_news` plus publication eligibility.
- Cron callbacks re-check gate, state, prerequisites, and article visibility.
- A schedule callback cannot publish a draft that became ineligible after scheduling.
- Duplicate cron execution is idempotent.
- Failed scheduling produces an audit event and administrator-visible diagnostic without exposing private notes publicly.
- Stored times are UTC; display conversion uses the configured site timezone.

## 18. REST API security

### 18.1 Public routes

Public GET routes expose only public projections and bounded lists.

They must:

- exclude drafts and private states;
- avoid existence leaks for private IDs;
- enforce pagination limits;
- return cache-safe headers;
- never expose nonces or current-user private state in shared responses.

### 18.2 Private routes

Private/current-user routes use:

- authentication;
- nonce;
- object/capability checks;
- `Cache-Control: no-store, private` where supported;
- private serializers.

### 18.3 Mass assignment

Controllers construct allow-listed input arrays. They never pass raw request bodies directly into post/meta/table writes.

Fields such as author, reviewer, status, breaking priority, correction approval, and publication time require separate capability-aware handling.

## 19. IDOR prevention matrix

At minimum, tests must prove that:

- User A cannot read or edit User B’s draft.
- Submitter A cannot read Submitter B’s submission.
- Reporter cannot assign themselves as approving editor.
- A source ID from Article A cannot be read/updated through Article B.
- A correction ID from Article A cannot be applied to Article B.
- A review decision cannot be submitted on behalf of another reviewer.
- A user ID supplied in REST cannot impersonate the current actor.
- A private preview token cannot open another article.
- A public interaction cannot reveal an unpublished News object.

## 20. Rate limiting and abuse controls

Rate limits are applied to actions such as:

- article creation/submission;
- repeated save/update;
- source addition;
- preview-token generation;
- review decisions;
- report/comment integration;
- search endpoints where abuse threatens availability.

Rate limits:

- are actor/object aware;
- have bounded windows;
- fail with safe messages;
- do not expose internal counters unnecessarily;
- do not use raw IP as persistent identity where privacy-safe alternatives exist.

Administrative emergency access must remain available when public writes are rate-limited.

## 21. Cache security

### 21.1 Public cache rules

Public cache entries may contain only data that every intended recipient may see.

Cache keys include relevant dimensions:

- site/blog ID;
- language;
- article/taxonomy/page;
- publication/correction generation;
- gate generation;
- retraction/archive state;
- pagination/filter values.

### 21.2 Private response rules

Never public-cache:

- drafts;
- submissions;
- previews;
- review queues;
- editor notes;
- current-user capability/state;
- nonces;
- pending corrections;
- private source data.

### 21.3 Invalidation

Immediate invalidation is required after:

- publication/unpublication;
- material update;
- correction;
- retraction;
- Breaking start/expiry;
- taxonomy reassignment;
- source-publication change;
- feature-gate change;
- Emergency Disable.

## 22. Notification privacy

The existing notification bridge receives only event-minimized payloads.

Allowed fields may include:

- event type;
- recipient user ID;
- actor user ID where policy allows;
- News article ID;
- safe public title or generic label;
- event time;
- deduplication key.

Prohibited fields:

- email;
- phone/WhatsApp;
- private notes;
- source contact details;
- patient information;
- report/correction deliberation text;
- authentication tokens;
- IP/user agent;
- unpublished article body.

Notification failure does not roll back a successful primary editorial operation.

## 23. Analytics privacy

- Reuse the existing privacy-safe view architecture only after News object support is explicitly tested.
- Do not persist raw IP addresses or full user agents.
- Honor the established Do Not Track/bot/preview/admin exclusions.
- Public UI shows aggregates only.
- Editor analytics do not become per-user surveillance.
- Source click measurement must not leak private source URLs or cross-site identifiers.

## 24. Audit security

Audit records must be append-oriented and capability-restricted.

Include:

- actor ID;
- action code;
- object type/ID;
- safe state transition;
- UTC time;
- bounded non-sensitive metadata.

Do not include:

- raw article body;
- patient identifiers;
- full private notes;
- passwords/nonces/tokens;
- raw IP/user agent;
- anonymous-source identity.

Audit-view and export actions are themselves audited where practical.

## 25. Privacy export and erasure

### 25.1 Export

A user’s export may include their own:

- submissions;
- authorship/assignment records where appropriate;
- review decisions and safe notes according to policy;
- interactions and views under existing contracts.

It must not disclose:

- another user’s private notes;
- moderator-confidential material;
- anonymous-source identity;
- unrelated editorial deliberations;
- patient identifiers that should not have been stored.

### 25.2 Erasure

Erasure follows role and accountability requirements:

- remove or anonymize personal identifiers where permitted;
- preserve public articles and correction history when deletion would damage the public record;
- remove access tokens and private contact data;
- avoid deleting unrelated users/content;
- preserve referential integrity through neutralized identifiers where required.

The precise legal/organizational retention policy must be approved before production release.

## 26. Safe Mode and Emergency Disable

Emergency Disable must:

- require administrator authority;
- immediately close Phase 4 public writes;
- close preview-token generation and new submissions where configured;
- suppress Breaking News presentation if required by incident response;
- fail closed for private visibility uncertainty;
- preserve existing data;
- preserve administrator recovery access;
- create an audit record;
- restore only previously configured gates after re-enable.

Emergency Disable is not a substitute for backup or rollback.

## 27. Logging and diagnostics privacy

Application logs and diagnostics must not contain:

- passwords;
- nonces;
- preview tokens;
- patient identifiers;
- full private notes;
- raw source-contact data;
- full request bodies;
- raw IP/user agent unless a separately approved short-lived infrastructure log policy applies outside the plugin.

Errors shown publicly are generic. Detailed diagnostics are restricted to authorized administrators and remain sanitized.

## 28. Backup and rollback security

Before staging or production changes:

- create a full identifiable backup;
- verify that it can be selected for restoration;
- record plugin/schema version and exact commit SHA;
- restrict backup access;
- do not place backup archives in public web paths.

Rollback testing occurs on staging and verifies:

- dashboard/public accessibility;
- Phase 2 feed;
- Phase 3 interactions;
- Editorial News data expectations;
- no unintended deletion;
- gate state;
- start/end UTC and evidence.

## 29. Security test plan

Automated and manual tests must cover:

### Authentication and authorization

- logged-out writes rejected;
- invalid/missing nonce rejected;
- capability matrix;
- ownership checks;
- forged identity rejected;
- self-approval restrictions where applicable.

### Input/output

- XSS payloads in every text/URL/media field;
- malicious schemes;
- oversized inputs;
- invalid enumerations;
- SQL metacharacters;
- unsafe redirects;
- malformed IDs.

### Privacy

- draft/search/archive/REST/cache leak tests;
- source-private field serialization tests;
- patient identifier tests;
- preview token isolation/expiry/revocation;
- submission isolation;
- notification payload minimization;
- privacy export/erasure.

### Workflow integrity

- invalid transitions;
- duplicate Publish/Schedule/Correction requests;
- cross-article source/review/correction attacks;
- stale scheduled publication;
- retraction/correction cache invalidation;
- Breaking expiry with cron failure.

### Uploads

- executable/script rejection;
- MIME/extension mismatch;
- oversized files;
- SVG rejection unless explicitly enabled;
- identifier-bearing clinical media review.

### Availability and abuse

- rate limits;
- pagination bounds;
- query performance;
- concurrent unique inserts;
- one-hour soak QA;
- Safe Mode/Emergency Disable.

## 30. Security acceptance gate

Phase 4 is not security-accepted until:

- all automated security suites pass on the exact commit;
- manual role/IDOR tests pass on Hostinger staging;
- private preview and cache isolation are demonstrated;
- clinical privacy scanner and human workflow are tested;
- upload restrictions are verified;
- notification and analytics payloads are inspected;
- backup and rollback restoration pass;
- open critical/high security defects are zero;
- administrator acceptance is recorded.

Merge, version promotion, and live deployment remain separate explicit decisions.

## 31. Incident-response minimum

For a suspected privacy or security incident:

1. activate Emergency Disable where appropriate;
2. preserve evidence without copying sensitive data into public issues;
3. revoke preview tokens and affected sessions/credentials through approved mechanisms;
4. invalidate relevant caches;
5. identify affected articles/users/surfaces;
6. restore known-good files/data if required;
7. correct or retract public content where required;
8. record sanitized incident and recovery evidence;
9. complete root-cause and regression tests before re-enable.

## 32. Security-change procedure

Any change to:

- authentication;
- capabilities;
- public/private serialization;
- preview tokens;
- upload allow-list;
- source privacy;
- clinical scanning;
- cache policy;
- notification/analytics payloads;
- retention/erasure;
- Emergency Disable;

requires a documented threat-impact review and updated tests before implementation is accepted.
