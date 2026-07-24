# Phase 4 QA Status — Editorial News and Global Newsroom

Target development line: `1.2.0`

Branch: `build/phase-4-editorial-news-1.2.0`

Current checkpoint: **Phase 4A — Content Model, Roles, Capabilities, Statuses, and Safe Feature Settings**

## Planning QA

The Phase 4 planning, contract, architecture, security/privacy, editorial-policy, Hostinger staging, and rollback documents completed a successful uninterrupted 3,900-second repeated QA run on commit:

```text
4675208130aeb4faa9c5f11cb02123f2aab21920
```

That planning QA included thirteen cycles, final verification, unchanged document manifest, existing repository regression tests, and uploaded evidence.

Planning QA remains valid for the exact planning document set tested at that commit. It does not by itself validate later runtime implementation commits.

## Phase 4A implementation status

Phase 4A now contains executable runtime foundations:

- Phase 4 contract registry;
- isolated feature settings;
- Editorial News post type;
- Editorial News taxonomies and default terms;
- workflow-state storage model;
- Editorial News capabilities;
- activation integration;
- Phase 4-aware snapshot and rollback;
- Phase 4A behavior tests;
- dedicated Phase 4A CI workflow.

The plugin display version and schema version remain `1.0.0`. All Phase 4 public gates remain disabled by default.

## Defects corrected during Phase 4A

1. The frozen `ready-for-publication` workflow state exceeds the WordPress `post_status` storage limit. The full state is now stored in private canonical metadata and mapped to a compatible core status.
2. The existing activation snapshot did not include Phase 4 settings or capabilities. Snapshot and rollback now include both, so Phase 4 permission mutations are reversible.
3. Verified Doctor submitters do not receive unrestricted source-management capability merely because later object-scoped source submission is planned.
4. Native WordPress REST exposure remains disabled until controlled Phase 4 REST projections and permission callbacks are implemented.

## Current automated acceptance requirement

The exact latest branch commit must pass:

- Phase 4 planning contract tests;
- Phase 4 document audit;
- Phase 4A content-model tests;
- PHP syntax lint;
- complete Phase 2 and Safe Boot regression;
- complete Phase 3 regression matrix;
- static security and whitespace checks;
- unchanged-version package build;
- Phase 4A package-content verification.

The authoritative workflow is:

```text
Phase 4A Content Model Tests
```

## Boundaries

Phase 4A is not:

- Phase 4B Newsroom implementation;
- a complete Editorial News system;
- Hostinger staging acceptance;
- version promotion;
- merge approval;
- live deployment approval.

The Draft pull request must remain open and unmerged while Phase 4 checkpoints continue.
