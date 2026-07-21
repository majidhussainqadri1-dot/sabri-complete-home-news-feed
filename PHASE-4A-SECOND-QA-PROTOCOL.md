# Phase 4A Mandatory Second One-Hour QA Protocol

Target development line: `1.2.0`

Branch: `build/phase-4-editorial-news-1.2.0`

Pull request: `#3`

Policy authority: `MANDATORY-SECOND-QA-POLICY.md`

## Purpose

This is the separate mandatory **second QA** for the completed Phase 4A content-model checkpoint. It does not reuse or combine time from planning QA, normal Phase 4A CI, Phase 3 soak testing, package testing, or any failed/cancelled attempt.

## Exact-commit rule

One uninterrupted run must remain on one exact 40-character branch commit. Any commit or tracked-file change invalidates the run. When a defect is corrected, the complete timer and cycle count restart from zero on the corrected commit.

## Minimum duration and repetition

- Minimum elapsed duration: `3900` seconds.
- Required repeated cycles: `13`.
- Target interval between cycle start boundaries: `300` seconds.
- Final verification: mandatory after the 3900-second boundary.

A cycle that fails ends acceptance for that commit. No partial time is retained.

## Phase 4A files under direct integrity protection

- `MANDATORY-SECOND-QA-POLICY.md`
- `PHASE-4A-SECOND-QA-PROTOCOL.md`
- `PHASE-4-CONTRACTS.md`
- `PHASE-4-CONTRACTS-ADDENDUM-1.md`
- `PHASE-4-CONTRACTS-ADDENDUM-2-WORDPRESS-STATUS-STORAGE.md`
- `PHASE-4-ARCHITECTURE.md`
- `PHASE-4-SECURITY-PRIVACY.md`
- `PHASE-4-EDITORIAL-POLICY.md`
- `includes/class-phase4-contracts.php`
- `includes/class-news-feature-settings.php`
- `includes/class-editorial-news-post-type.php`
- `includes/class-news-taxonomies.php`
- `includes/class-news-statuses.php`
- `includes/class-news-capabilities.php`
- `includes/class-plugin.php`
- `includes/class-activator.php`
- `includes/class-snapshot.php`
- `includes/class-rollback.php`
- `tests/run-phase4a-content-model-tests.php`
- `tests/run-phase4a-second-one-hour-qa.sh`
- `.github/workflows/phase4a-content-model-tests.yml`
- `.github/workflows/phase4a-second-one-hour-qa.yml`

The workflow also creates and compares a SHA-256 manifest for every tracked repository file, not only this list.

## Initial complete verification

Before repeated cycles begin, the workflow must complete:

1. exact branch and commit recording;
2. complete PHP syntax lint excluding generated release/vendor directories;
3. JavaScript syntax validation;
4. Phase 4 planning contract tests;
5. Phase 4 document audit;
6. Phase 4A behavior tests;
7. complete Phase 2 behavior and Safe Boot regression;
8. all Phase 3 checkpoint and behavioral suites;
9. public routing, dynamic option-filter, and duplicate-folder regressions;
10. static security and whitespace checks;
11. real WordPress Playground integration on the supported test matrix where runner infrastructure permits it.

## Repeated-cycle verification

Every cycle must run at least:

- Phase 4 planning contracts;
- Phase 4 document audit;
- Phase 4A content-model tests;
- complete Phase 2 behavior and Safe Boot regression;
- all Phase 3 PHP regression suites;
- critical Phase 4A PHP syntax lint;
- exact commit check;
- tracked-file manifest comparison;
- tracked working-tree check.

## Final post-duration verification

After at least 3900 seconds and 13 completed cycles, the workflow must repeat the complete PHP, Phase 4, Phase 4A, Phase 2, Safe Boot, and Phase 3 regression matrix; run static checks; build the unchanged-version staging package; verify package structure; verify that all Phase 4A runtime files are included; verify the package SHA-256 file; and record final commit and manifest evidence.

## Package boundary

The package remains an unchanged-version staging candidate:

- plugin version remains `1.0.0`;
- schema version remains `1.0.0`;
- all eight Phase 4 gates remain disabled by default;
- package success does not authorize merge, version promotion, Hostinger staging acceptance, or live deployment.

## Defect correction

When a test exposes a code, contract, packaging, privacy, permission, rollback, or compatibility defect:

1. the current attempt is rejected;
2. the root cause is corrected on the Phase 4 branch;
3. affected tests and documents are updated consistently;
4. no test is weakened to hide the defect;
5. the complete 3900-second second QA restarts on the new exact commit.

Runner-level failures with no executed steps are recorded as infrastructure failures and retried without changing code unless evidence identifies a repository defect.

## Required passed evidence

The passed artifact must contain:

- `result.env`;
- start, cycle, and final logs;
- exact commit record;
- initial and final tracked-file SHA-256 manifests;
- manifest digest;
- package ZIP and package checksum;
- package structure report;
- defect/correction record, including `none found` when applicable.

Artifact name:

` sabri-phase4a-SECOND-ONE-HOUR-QA-PASSED `

## Acceptance boundary

A passed second QA validates Phase 4A only for the exact tested commit. Phase 4B coding, Hostinger staging, backup/rollback restoration, PR readiness, merge, version promotion, and live deployment remain separate later decisions.
