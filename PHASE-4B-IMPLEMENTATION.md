# Phase 4B Implementation — Newsroom and Composer Foundations

Target development line: `1.2.0`

Branch: `build/phase-4b-newsroom-composer-1.2.0`

Base: `main`

Status: **Draft implementation checkpoint — no production authorization**

## 1. Objective

Phase 4B implements the internal Editorial News newsroom and composer foundations on top of the accepted Phase 4A content model. It must provide controlled administration screens, role-specific queues, secure article creation and editing, workflow-aware validation, and scheduling foundations without enabling public News routes or uncontrolled publication.

## 2. Required deliverables

Phase 4B must add or complete:

1. a dedicated Newsroom administration entry point;
2. role-aware editorial queues;
3. a secure Editorial News composer;
4. server-side validation for title, summary, language, section, type, priority, review requirements, and scheduling fields;
5. allow-listed workflow transitions;
6. draft, review, fact-check, medical-review, approval, scheduling, and publication-preparation queues;
7. reviewer-assignment controls governed by capabilities;
8. scheduling foundations using UTC-normalized values;
9. immutable or append-only audit records for consequential editorial actions;
10. feature-gated registration with all Phase 4 public gates disabled by default;
11. full regression compatibility with Phase 2, Safe Boot, and Phase 3;
12. dedicated Phase 4B contract, behavior, capability, and packaged-runtime tests.

## 3. Explicitly out of scope

The following remain outside Phase 4B and must not be partially exposed:

- public News archive and single routes;
- Home Feed distribution of Editorial News;
- source-registry persistence and public citations;
- full fact-check and medical-review ledgers;
- doctor/contributor public submission forms;
- Breaking News strip;
- correction and retraction public notices;
- RSS, sitemap, schema, translation linkage, and public SEO;
- version promotion;
- live deployment.

## 4. Security boundaries

- Every write operation must be authorized server-side.
- Frontend or admin form fields must never establish identity, ownership, reviewer authority, or publication authority.
- Nonce verification is required for state-changing administration requests.
- Ordinary contributors must not receive self-publication authority.
- Reviewer assignment must be capability-controlled and cannot be self-assigned by restricted reviewers.
- Emergency Disable must close Phase 4B writes while preserving safe read-only diagnostics.
- Core destructive deletion must remain denied where the Phase 4 contract requires non-destructive editorial handling.
- Unknown metadata and unknown workflow states must fail closed.
- All output must be escaped and all submitted values normalized and validated.

## 5. Administrative architecture

Planned responsibilities:

- `NewsroomAdmin`: menu registration, screen coordination, capability checks, and safe notices.
- `NewsListTable`: bounded and paginated article queues with explicit filters.
- `NewsComposer`: create/edit coordination without persistence or authorization shortcuts.
- `NewsWorkflow`: allow-listed transitions and prerequisite enforcement.
- `NewsPolicy`: object-level and field-level permission decisions.
- `NewsService`: transactional coordination of validation, persistence, revisions, scheduling, cache invalidation, and audit.
- `NewsSchedulingService`: UTC normalization, duplicate prevention, missed-schedule diagnostics, and retry-safe publication preparation.

Controllers must coordinate only. Policies authorize. Services execute application operations. Repositories persist. Templates render escaped values.

## 6. Required queues

At minimum, the newsroom must support isolated, capability-aware queues for:

- own drafts;
- submitted for editorial review;
- editorial review;
- fact-check required;
- medical review required;
- changes requested;
- approved;
- scheduled;
- publication-ready;
- published or otherwise visible only where the current Phase 4 gate and capability permit;
- retracted or correction-sensitive records as read-only placeholders until their dedicated later checkpoint.

Private states must never leak through counts, filters, URLs, bulk actions, notices, REST responses, or cached fragments.

## 7. Composer requirements

The composer must provide:

- title and article body;
- subtitle and summary;
- language selection with fail-closed normalization;
- controlled section and article type;
- topic, country, and region selectors governed by taxonomy capabilities;
- featured image selection through WordPress Media Library;
- priority and review-requirement fields;
- reviewer assignment where authorized;
- scheduling date and time with explicit timezone display and UTC storage;
- revision-safe saves;
- clear validation errors without silently discarding data;
- preview only for users with explicit preview authority;
- no public route exposure merely because a draft exists.

## 8. Workflow rules

Workflow transitions must be represented by an explicit allow-list. Every consequential transition must verify:

- current object state;
- current user capability;
- object ownership where relevant;
- required metadata completeness;
- required review decisions;
- scheduling validity;
- Emergency Disable state;
- idempotency and duplicate-submit protection.

Direct arbitrary writes to the canonical workflow-state metadata are prohibited outside the authorized workflow service.

## 9. Scheduling foundations

- Store canonical schedule values in UTC.
- Display the effective site timezone and normalized UTC value before confirmation.
- Reject invalid, ambiguous, expired, or malformed schedule values.
- Prevent duplicate scheduled events for the same article and target state.
- Record schedule creation, modification, cancellation, and failure.
- Do not publish automatically until the complete Phase 4 scheduling and release gates authorize that behavior.

## 10. Accessibility and usability

- Use semantic headings, form labels, descriptions, and error associations.
- Preserve keyboard navigation and visible focus.
- Do not rely on color alone for state or error meaning.
- Queue tables require captions, headers, bounded pagination, and accessible bulk controls.
- Status changes require explicit confirmation where consequences are material.
- Reduced-motion preferences must be respected.

## 11. Performance constraints

- Every queue is paginated and bounded.
- Avoid unbounded taxonomy, author, or metadata queries.
- Avoid per-row repeated capability and metadata queries where safe preloading is possible.
- Load newsroom assets only on Phase 4B administration screens.
- Do not place large serialized newsroom state in autoloaded options.
- Cache only projections that cannot expose current-user or private editorial state.

## 12. Testing requirements

Phase 4B acceptance requires dedicated tests covering:

- menu and screen capability boundaries;
- object ownership and reviewer assignment;
- composer validation and sanitization;
- workflow allow-list and prerequisite rejection;
- Emergency Disable write closure;
- nonce and request-method enforcement;
- queue isolation and private-count non-disclosure;
- schedule normalization and duplicate prevention;
- revision behavior;
- unknown-state and unknown-field fail-closed behavior;
- Phase 2, Safe Boot, and all Phase 3 regressions;
- Phase 4A regressions;
- source and packaged WordPress Playground matrices;
- package structure and checksum validation;
- exact-commit retained QA evidence.

## 13. Implementation order

1. Freeze Phase 4B contracts and tests.
2. Implement policy and workflow transition services.
3. Implement newsroom screen registration and queues.
4. Implement composer validation and save orchestration.
5. Implement reviewer assignment.
6. Implement scheduling foundations.
7. Add diagnostics, audit, and repair-safe checks.
8. Run complete regression and packaged-runtime QA.
9. Perform Hostinger staging acceptance only after all automated evidence passes.

## 14. Acceptance boundary

Phase 4B is complete only when:

- every required queue and composer operation is capability-safe;
- all workflow transitions are allow-listed and tested;
- no private state leaks through administration or runtime surfaces;
- all Phase 2, Phase 3, and Phase 4A regressions pass;
- source and packaged runtime tests pass on the required WordPress/PHP matrix;
- exact-commit QA evidence is retained;
- the Draft PR remains unmerged until explicit acceptance;
- no public News gate, version promotion, staging publication, or live deployment occurs implicitly.

## 15. Current checkpoint

This document opens Phase 4B as a separate development checkpoint. It does not claim that newsroom code, composer code, scheduling behavior, staging acceptance, or production readiness is complete. All implementation work must remain on this branch and inside a Draft pull request until the complete acceptance record exists.
