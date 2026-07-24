# Phase 4B Mandatory One-Hour QA Protocol

## Purpose

This protocol governs the mandatory long-duration acceptance test for the private Phase 4B Editorial News newsroom and composer implementation.

## Exact-commit rule

The complete test must run against one exact 40-character commit SHA from `build/phase-4b-newsroom-composer-1.2.0`. Any implementation, test, workflow, or documentation correction creates a new head commit and restarts the complete QA from zero.

## Required duration and cycles

A passing soak must:

- remain uninterrupted for at least 3,900 seconds;
- complete exactly 13 recorded cycles;
- run the final complete verification after the minimum duration has elapsed;
- preserve identical initial and final SHA-256 manifests for every tracked repository file.

Elapsed time from cancelled, failed, or superseded attempts must never be combined.

## Preflight acceptance

Before the soak begins, the exact commit must pass:

- complete Phase 4B PHP lint and JavaScript syntax checks;
- Phase 4B newsroom behavior tests;
- Phase 4B UI completeness tests;
- Phase 4A content-model regressions;
- the complete core regression suite;
- repository static checks;
- source WordPress Playground integration on latest WordPress/PHP 8.3;
- source WordPress Playground integration on WordPress 6.8/PHP 8.1;
- immutable package build, checksum, ZIP integrity, and required-file structure;
- packaged WordPress Playground integration on both supported matrices.

## Every soak cycle

Each of the 13 cycles must repeat:

- Phase 4B behavior tests;
- Phase 4B UI completeness tests;
- Phase 4A regressions;
- complete core regressions;
- JavaScript syntax validation;
- complete Phase 4B PHP lint;
- repository static checks;
- fresh package build;
- package checksum and ZIP-integrity verification;
- required Phase 4B package-entry verification;
- exact-commit and clean tracked-worktree checks.

## Final verification

After at least 3,900 seconds, the workflow must repeat the complete local test surface, rebuild and verify the final package, compare the final tracked-file manifest with the initial manifest, record the actual duration and cycle count, and upload a passed evidence artifact.

## Failure policy

- Infrastructure failures may be retried on the same exact commit only when no repository test exposed a defect.
- A repository defect must be corrected only on the Phase 4B feature branch.
- Tests must not be weakened, skipped, shortened, or converted into non-blocking checks.
- After any correction, the complete preflight and 3,900-second soak restart from zero on the new exact commit.

## Safety boundaries

During this QA:

- PR #4 remains Draft, open, and unmerged;
- no direct commit is made to `main`;
- plugin and schema versions remain unchanged;
- all public Phase 4 gates remain disabled;
- automatic publication remains disabled;
- Hostinger staging, version promotion, merge approval, and live deployment remain separate later decisions.

## Passing evidence

A valid pass must retain an artifact named:

`sabri-phase4b-ONE-HOUR-QA-PASSED-{exact-head-sha}`

The evidence must include the exact commit, actual duration, 13-cycle record, initial/final manifest digest, final package SHA-256, and explicit confirmation that public gates and automatic publication remained disabled.
