# Phase 4 Review Notes

Target development line: `1.2.0`

Branch: `build/phase-4-editorial-news-1.2.0`

## Planning review

The planning documents completed a successful 65-minute repeated QA on exact commit `4675208130aeb4faa9c5f11cb02123f2aab21920`.

## Phase 4A review findings

### Finding 1 — WordPress workflow-state storage

Severity: High if implemented without correction.

The frozen state `ready-for-publication` is longer than the practical WordPress `post_status` storage limit. Direct registration would risk truncation and inconsistent workflow comparisons.

Correction:

- preserve the complete state in `_sabri_news_workflow_state`;
- use only compatible WordPress core statuses for broad storage lifecycle;
- treat the private domain-state metadata as authoritative;
- reject unknown states;
- document the decision in `PHASE-4-CONTRACTS-ADDENDUM-2-WORDPRESS-STATUS-STORAGE.md`;
- add automated mapping and source-of-truth tests.

### Finding 2 — Phase 4 rollback coverage

Severity: High.

The existing activation snapshot captured only pre-Phase-4 settings and capabilities. Adding Phase 4 role permissions without expanding snapshot and rollback would leave new permissions behind after rollback.

Correction:

- snapshot the Phase 4 option before activation mutation;
- snapshot Phase 4 capabilities for every candidate existing role;
- include Phase 4 taxonomy, section, and article-type identities in snapshot context;
- restore Phase 4 settings and permissions during rollback;
- preserve Editorial News content and metadata;
- add automated activation and rollback tests.

### Finding 3 — Submitter source authority

Severity: Medium.

The editorial matrix describes source management for a Verified Doctor only within their own submission. A broad WordPress capability cannot express that object-scoped boundary safely at Phase 4A.

Correction:

- grant Verified Doctor only read and submit capabilities at this checkpoint;
- defer submission-owned source writes to a later object-policy service;
- do not grant unrestricted `manage_news_sources` authority.

### Finding 4 — Native REST exposure

Severity: High if exposed prematurely.

Registering the post type or metadata with uncontrolled native REST output before custom serializers and permission callbacks could leak workflow or private metadata.

Correction:

- keep native post-type and taxonomy REST exposure disabled;
- keep private metadata out of native REST;
- implement public, contributor, reviewer, and administrator projections only in the dedicated REST checkpoint.

## Phase 4A code review boundary

Implemented:

- content model;
- taxonomies and frozen terms;
- workflow-state model;
- feature gates;
- role capabilities;
- activation snapshot;
- rollback restoration;
- automated tests and CI.

Not yet implemented:

- Newsroom user interface;
- workflow transition service;
- public News routes/templates;
- source/review/correction persistence;
- custom REST controllers;
- submissions, breaking, scheduling, corrections, RSS, schema, sitemap, or translation runtime.

## Acceptance decision

Phase 4A may advance only after its dedicated exact-commit CI passes together with Phase 2 and Phase 3 regressions. It remains Draft, unmerged, unpromoted, and not approved for live deployment.
