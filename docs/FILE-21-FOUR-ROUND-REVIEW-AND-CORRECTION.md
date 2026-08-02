# File 21 — Four Sequential Review-and-Correction Rounds

## Governing sequence

This corrective cycle was performed in the required order:

1. Review Round 1 → Correction Round 1
2. Review Round 2 → Correction Round 2
3. Review Round 3 → Correction Round 3
4. Review Round 4 → Correction Round 4
5. Fresh exact-head comprehensive retest

A prior green package was not treated as proof of completion. Package identity remains `1.0.3.2`; stable runtime/API remains `1.0.3`; schema remains `1.0.0`.

## Review Round 1 → Correction Round 1

### Findings

- Development tooling still claimed PHP 7.4 although the plugin and File 22 contract use PHP 8.1 language/runtime features.
- PHPStan did not load static declarations for the external File 22 workflow and diagnostic interfaces.
- The WordPress Coding Standards ruleset was malformed and therefore could not serve as valid evidence.

### Corrections

- Composer minimum PHP aligned to 8.1.
- Static-analysis-only File 22 contract declarations added and scanned.
- The PHPCS ruleset was repaired and later narrowed to an explicit release-blocking WordPress runtime-safety scope; formatting/documentation conformance remains a separate quality concern and is not misrepresented as full-codebase proof.

## Review Round 2 → Correction Round 2

### Finding

Legacy Founder/Administrator restoration rechecked capabilities but did not independently require a current, exact-subject File 00 action assurance at the mutation boundary.

### Correction

Legacy restoration now requires the exact signed-in actor, active File 00 assertions, and current-session two-factor assurance before a selected post can be restored.

## Review Round 3 → Correction Round 3

### Findings

The File 04 compatibility paths for publication migration, interaction migration, and rollback could reach mutation logic through legacy WordPress capabilities without independently revalidating the exact current File 00 actor.

### Corrections

All three boundaries now require:

- the explicit current actor;
- exact actor/session equality;
- active subject-bound File 00 assertions;
- current-session two-factor assurance;
- the relevant migration/administrator capability;
- target provenance before mutation.

## Review Round 4 → Correction Round 4

### Findings

Several administrative surfaces still depended on stale capabilities after entry:

- Newsroom diagnostics;
- explicit public-surface recovery;
- File 22 workflow reconciliation and recovery.

The Home composition guard could also suppress the ten-row composition when one accepted mount path built the rows before another path embedded the final surface.

### Corrections

- Newsroom diagnostics, public recovery, and workflow recovery now revalidate the exact current File 00 actor and current-session assurance at their action/render boundaries.
- Home composition now builds one deterministic request-local ten-row markup value that can be embedded by the accepted mount path without an earlier build suppressing the final surface.
- Browser acceptance now exercises the canonical `FeedQuery::wp_query_args()` pagination contract and confirms hidden pending/private posts do not enter public pages.
- Safe Boot diagnostic tests explicitly verify fail-closed behavior when File 00 assurance is absent.

## Fresh exact-head comprehensive retest gates

The final head must pass all of the following before merge consideration:

- exact-head checkout verification;
- PHP syntax and repository regression suites;
- File 00 authorization/projection regression;
- File 21 ↔ File 22 real contract tests;
- official PHPUnit;
- official PHPStan within the declared critical surface;
- scoped official WordPress runtime-safety standards;
- deterministic package build;
- embedded and external SHA-256 manifest verification;
- ZIP integrity and stale-artifact rejection;
- all ten mandatory Playground/Playwright suites.

## Truthful release status

This record supports `Coded`, `Packaged`, and `Automated-QA Green` only after the fresh exact-head workflows pass. It does not establish `Staging-Accepted`, `Live-Deployed`, or `Operational` status. Hostinger staging, real-role workflows, accessibility, active-theme/cache compatibility, backup/restore, rollback rehearsal, Founder acceptance, and post-deployment monitoring remain separate gates.

Absolute defect-free or infallibility claims are prohibited. Any new defect, dependency change, security advisory, staging failure, or user evidence reopens review.
