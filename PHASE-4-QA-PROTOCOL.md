# Phase 4 Repeated Planning QA Protocol

Target development line: `1.2.0`

Branch: `build/phase-4-editorial-news-1.2.0`

Status: **QA protocol only — no Phase 4 feature implementation or production authorization**

## Purpose

This protocol requires repeated, evidence-producing validation of the Phase 4 planning and contract documents before Phase 4A feature coding begins.

## Required automated checks

Every QA cycle must run:

1. `tests/run-phase4-contract-tests.php`;
2. `tests/run-phase4-document-audit.php`;
3. the existing repository regression runner `tools/run-tests.php`;
4. PHP syntax validation for repository PHP files outside generated/dependency directories;
5. SHA-256 verification of every normative Phase 4 planning document;
6. exact Git commit verification;
7. tracked working-tree integrity verification.

## Duration and repetition

The dedicated workflow must:

- run for at least 3,900 seconds;
- complete 13 full QA cycles;
- use 300-second observation intervals between cycles;
- perform a final post-soak verification;
- fail if the tested commit changes;
- fail if any document differs from its initial SHA-256 manifest;
- upload evidence only under an explicit passed or failure artifact name.

## Documents under test

- `PHASE-4-CONTRACTS.md`
- `PHASE-4-CONTRACTS-ADDENDUM-1.md`
- `PHASE-4-EDITORIAL-POLICY.md`
- `PHASE-4-ARCHITECTURE.md`
- `PHASE-4-SECURITY-PRIVACY.md`
- `PHASE-4-HOSTINGER-STAGING-ACCEPTANCE-CHECKLIST.md`
- `PHASE-4-ROLLBACK-RUNBOOK.md`
- `PHASE-4-COMPLETENESS-AUDIT.md`

## Failure handling

A failure is not merely reported. Before the next complete run:

1. inspect the failing cycle and exact assertion;
2. determine whether the defect is in a document, test, workflow, or existing repository compatibility;
3. correct the defect on the Phase 4 branch only;
4. preserve the stronger privacy, security, accessibility, rollback, and release rule when documents overlap;
5. restart the complete duration from the corrected commit;
6. retain failed-run evidence for diagnosis.

A partial run before a corrective commit cannot be added to a later run to satisfy the duration requirement.

## Passing evidence

A valid passed evidence package must record:

- exact 40-character commit SHA;
- UTC start and end epochs;
- duration of at least 3,900 seconds;
- 13 completed cycles;
- SHA-256 of the initial document manifest;
- per-cycle test and syntax logs;
- final post-soak test output;
- final report stating that the result covers planning/contract QA only.

## Boundary

A successful planning QA does not establish:

- Phase 4 PHP, JavaScript, CSS, database, REST, template, or UI implementation;
- Hostinger staging acceptance;
- clean or upgrade installation acceptance;
- backup or rollback restoration acceptance;
- version promotion;
- merge approval;
- live deployment.
