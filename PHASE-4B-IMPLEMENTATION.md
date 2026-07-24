# Phase 4B Implementation — Newsroom and Composer Foundations

Target development line: `1.2.0`

Branch: `build/phase-4b-newsroom-composer-1.2.0`

Base: `main`

Status: **implementation scope complete — exact-head automated acceptance required — no production authorization**

## 1. Objective

Phase 4B implements the internal Editorial News newsroom and composer foundations on top of the Phase 4A content model. It provides controlled administration screens, role-specific queues, secure article creation and editing, workflow-aware validation, reviewer assignment, UTC scheduling foundations, private audit records, and read-only diagnostics without enabling public News routes or uncontrolled publication.

## 2. Implemented deliverables

Phase 4B now includes:

1. a dedicated Newsroom administration entry point;
2. a dedicated secure Editorial News composer;
3. capability-aware own-draft, submitted, editorial-review, fact-check, medical-review, changes-requested, approved, publication-ready, scheduled, published-placeholder, and accountability-placeholder queues;
4. bounded server-side validation for title, body, subtitle, summary, language, section, type, taxonomies, priority, featured image, review requirements, reviewer IDs, state, and scheduling fields;
5. an explicit allow-list for every frozen workflow state;
6. object-aware policy checks and restricted-reviewer self-assignment prevention;
7. application-service coordination for create, update, transition, taxonomy assignment, featured image, scheduling, and audit operations;
8. POST-method, nonce, identity, ownership, and capability enforcement;
9. UTC-normalized scheduling with duplicate prevention, missing-event detection, repair, cancellation, failure records, and preparation-only due events;
10. append-only private audit records for consequential actions and failures;
11. read-only newsroom diagnostics that do not repair or mutate data;
12. Safe Boot registration and Emergency Disable write closure;
13. source and packaged WordPress Playground matrices;
14. Phase 4B behavior tests, Phase 4A regression, core regression, static checks, package checksum and structure verification, exact-head manifests, and retained acceptance artifacts.

## 3. Implementation map

### Policies and domain behavior

- `includes/class-news-policy.php`
- `includes/class-news-workflow.php`
- `includes/class-news-composer-validator.php`

### Application and private newsroom services

- `includes/class-news-service.php`
- `includes/class-news-queue-service.php`
- `includes/class-news-scheduling-service.php`
- `includes/class-news-audit.php`
- `includes/class-newsroom-diagnostics.php`

### Administration

- `admin/class-newsroom-admin.php`

### Registration and private metadata

- `includes/class-plugin.php`
- `includes/class-editorial-news-post-type.php`

### Automated evidence

- `tests/run-phase4b-newsroom-tests.php`
- `tests/phase4b-stubs.php`
- `tests/run-phase4b-playground-tests.mjs`
- `.github/workflows/phase4b-newsroom-tests.yml`

## 4. Explicitly out of scope

The following remain outside Phase 4B and are not partially exposed:

- public News archive and single routes;
- Home Feed distribution of Editorial News;
- source-registry persistence and public citations;
- full fact-check and medical-review ledgers;
- doctor/contributor public submission forms;
- Breaking News strip;
- correction and retraction public notices;
- RSS, sitemap, schema, translation linkage, and public SEO;
- automatic publication from a due schedule;
- version promotion;
- live deployment.

## 5. Security boundaries

- Every write operation is authorized server-side.
- Form fields never establish identity, ownership, reviewer authority, or publication authority.
- State-changing administration requests require POST and verified nonces.
- Ordinary contributors receive no self-publication authority.
- Reviewer assignment is capability-controlled; restricted reviewers cannot self-assign.
- Emergency Disable closes Phase 4B writes while preserving safe read-only diagnostics.
- Core destructive deletion remains denied.
- Unknown fields, queues, metadata, states, transitions, attachment types, and malformed values fail closed.
- Public REST exposure remains disabled.
- Public queryability remains controlled by the disabled-by-default Phase 4 gate.
- Due schedules record publication preparation only and never publish in Phase 4B.

## 6. Administrative architecture

The implementation preserves separated responsibilities:

- `NewsroomAdmin` coordinates screens, POST handlers, capability checks, nonces, escaping, and safe redirects.
- `NewsPolicy` decides write, edit, preview, queue, and reviewer-assignment authority.
- `NewsWorkflow` defines exact transitions and required capabilities.
- `NewsComposerValidator` allow-lists and validates submitted fields.
- `NewsService` coordinates persistence and consequential operations.
- `NewsQueueService` produces bounded private query projections without unauthorized counts.
- `NewsSchedulingService` controls UTC schedules and preparation-only due events.
- `NewsAudit` appends private accountability records.
- `NewsroomDiagnostics` reports health without mutating state.

Controllers do not decide domain authority, templates do not persist, and services do not trust submitted identities.

## 7. Queue isolation

The newsroom implements these isolated queues:

- `own-drafts` — current author only;
- `submitted` — current submitter only and read-only after submission;
- `editorial-review` — editorial reviewers only;
- `fact-check` — fact-check authority only;
- `medical-review` — medical-review authority only;
- `changes-requested` — current author only;
- `approved` — review authority;
- `publication-ready` — publication authority;
- `scheduled` — scheduling authority;
- `published` — read-only placeholder for authorized editorial users;
- `accountability` — read-only correction/retraction placeholder.

Every queue is bounded to at most 50 records per page. Unknown or unauthorized queues return no query structure and no count.

## 8. Composer contract

The composer supports:

- title and body;
- subtitle and bounded summary;
- validated BCP-47-style language tags;
- frozen section and article type;
- bounded topic, country, and region slug lists under taxonomy authority;
- validated image attachments through WordPress thumbnail storage;
- priority and review-requirement fields;
- capability-controlled reviewer assignment;
- explicit workflow target;
- timezone-bearing schedule input normalized to UTC;
- WordPress revision-compatible post saves;
- server-returned validation errors;
- no public route or publication shortcut.

Unknown submitted fields are discarded before persistence and malformed known fields reject the request.

## 9. Workflow rules

All frozen states have an explicit transition entry. Each transition verifies:

- exact current and target state;
- allow-listed transition;
- required capability;
- object edit authority;
- required content, classification, summary, reviewer, and schedule prerequisites;
- Emergency Disable state;
- idempotent same-state behavior.

Direct `draft → published` and all other review-bypass transitions are denied. Phase 4B rejects every attempt to target `published` through its composer or transition service.

## 10. Scheduling foundations

Scheduling behavior now:

- accepts only explicitly zoned values;
- stores canonical UTC values;
- rejects malformed, ambiguous, expired, or near-immediate values;
- prevents duplicate cron events;
- treats an exact repeated request as a no-op;
- detects stored schedules whose cron event is missing;
- repairs a missing event without creating a publication path;
- records create, update, repair, cancellation, due, and failure events;
- exposes read-only event-missing and missed-schedule diagnostics;
- never publishes automatically.

## 11. Audit and diagnostics

Consequential actions append bounded private audit events containing action, user, UTC time, and sanitized context. Audit data is neither public nor REST-exposed.

Diagnostics verify class availability, post-type state, feature flags, public-query closure, REST closure, visible queue names, and automatic-publication closure. Diagnostics are read-only and report `mutated=false`.

## 12. Accessibility and performance

- Semantic headings, labels, captions, table headers, descriptions, and screen-reader text are used.
- Queue navigation uses actual links and queue tables remain keyboard-operable.
- State is expressed in text rather than color alone.
- Queue queries are paginated and bounded.
- Private queue projections are never public-cached.
- No large newsroom state is stored in autoloaded options.
- Phase 4B introduces no public asset bundle or public route.

A later staging acceptance must still include manual keyboard and screen-reader verification.

## 13. Automated acceptance matrix

The dedicated workflow requires one exact pull-request head commit to pass:

1. PHP and JavaScript syntax checks;
2. Phase 4B newsroom behavior tests;
3. Phase 4A regression tests;
4. the core plugin regression suite;
5. plugin static checks;
6. source WordPress Playground on WordPress latest/PHP 8.3;
7. source WordPress Playground on WordPress 6.8/PHP 8.1;
8. immutable package build and SHA-256 verification;
9. required package-entry verification;
10. packaged WordPress Playground on WordPress latest/PHP 8.3;
11. packaged WordPress Playground on WordPress 6.8/PHP 8.1;
12. unchanged tracked-file manifest evidence;
13. retained final artifact named `sabri-phase4b-NEWSROOM-QA-PASSED-{exact-head-sha}`.

The final artifact records the exact head SHA, source and packaged matrix counts, public-gate state, automatic-publication state, package SHA-256, and tracked-manifest SHA-256.

## 14. Completion audit

| Planned requirement | Implementation state |
|---|---|
| Dedicated Newsroom entry | Implemented |
| Role-aware queues | Implemented |
| Secure composer | Implemented |
| Server-side validation | Implemented |
| Allow-listed workflow | Implemented |
| Reviewer controls | Implemented |
| UTC scheduling foundations | Implemented |
| Duplicate/missing event handling | Implemented |
| Private append-only audit | Implemented |
| Read-only diagnostics | Implemented |
| Emergency Disable closure | Implemented |
| No public News exposure | Enforced and tested |
| Phase 4A/core regressions | Required by CI |
| Source runtime matrices | Required by CI |
| Packaged runtime matrices | Required by CI |
| Package checksum/structure | Required by CI |
| Exact-head retained evidence | Required by CI |
| Hostinger staging acceptance | Later explicit decision |
| Version promotion | Not authorized |
| Live deployment | Not authorized |

## 15. Acceptance boundary

The Phase 4B **implementation scope** is complete on this Draft branch. Phase 4B is accepted only when the dedicated exact-head workflow finishes successfully and retains the final passed artifact.

Until that evidence exists:

- PR #4 remains Draft, open, and unmerged;
- no direct commit is made to `main`;
- no public News gate is enabled;
- no version is promoted;
- no Hostinger staging acceptance is claimed;
- no live deployment is authorized.

Hostinger staging, backup restoration, rollback verification, merge approval, and later Phase 4 checkpoints remain separate decisions.
