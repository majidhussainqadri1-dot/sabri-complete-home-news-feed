# Phase 5 Defect Register

This register remains authoritative through final acceptance.

## Severity

- Critical: immediate security/privacy/data-loss/publication-integrity failure.
- High: major authorization, migration, rollback, public-distribution, or critical-journey defect.
- Medium: bounded functional/accessibility/performance defect with no core safety failure.
- Low: minor polish/documentation issue with no material safety or workflow impact.

## Rules

1. Every discovered issue receives an ID, exact head, source, severity, root cause, correction commit, regression test, and final disposition.
2. Critical and High issues block acceptance.
3. Unclassified issues block acceptance.
4. A fix restarts affected exact-head evidence; the final two-hour acceptance always restarts from zero after any code/test/workflow change.
5. No issue is deferred to a future phase.

## Closed entries

| ID | Severity | Finding and root cause | Correction evidence | Regression evidence | Disposition |
|---|---|---|---|---|---|
| P5-D001 | High | Phase 5 bootstrap treated utility-only contracts/security classes as runtime modules requiring `register()`, which could halt WordPress registration. | Included in clean runtime commit `60dece203522fae9e93399ae90ad13f969bb9ad8`. | Phase 5 local acceptance, WordPress bootstrap lint, and legacy core registration tests. | Closed |
| P5-D002 | High | Submission create/update integrity defects included an undefined row reference and unsafe partial-update handling. | Included in clean runtime commit `60dece203522fae9e93399ae90ad13f969bb9ad8`. | Phase 5 final, security/privacy, and Playground submission lifecycle tests. | Closed |
| P5-D003 | High | Review/source/translation state transitions could permit verification or approval ordering inconsistencies. | Included in clean runtime commit `60dece203522fae9e93399ae90ad13f969bb9ad8`. | Review ledger, publication prerequisite, source verification, translation, and Playground lifecycle tests. | Closed |
| P5-D004 | High | Breaking/correction boundaries needed public-article eligibility, duplicate-pending prevention, and revision ownership enforcement. | Included in clean runtime commit `60dece203522fae9e93399ae90ad13f969bb9ad8`. | Breaking, correction, public-history, object-policy, and security-negative tests. | Closed |
| P5-D005 | High | Authenticated REST writes required uniform nonce, capability/object policy, strict JSON allow-list, no-store response, and trusted actor identity. | Included in clean runtime commit `60dece203522fae9e93399ae90ad13f969bb9ad8`. | Phase 5 REST and security/privacy negative tests. | Closed |
| P5-D006 | Medium | Public Breaking output registered on `wp_body_open`, conflicting with the accepted no-whole-page-wrapper boundary. | Corrections `7a5d94e93bfd712fbfcfcc66a47ff7fa9cf60034` and `8630003b00704d03b438a33ace31e7e0ca6596c5`; delivered in `60dece203522fae9e93399ae90ad13f969bb9ad8`. | Legacy `sabri_test_bootstrap_no_wrappers` and Phase 5 UI tests. | Closed |
| P5-D007 | High | Retention-off uninstall did not reliably remove the canonical settings option before later cleanup. | Correction `8630003b00704d03b438a33ace31e7e0ca6596c5`; delivered in `60dece203522fae9e93399ae90ad13f969bb9ad8`. | Legacy uninstall capability/data-retention tests and Phase 5 migration tests. | Closed |
| P5-D008 | Low | Security-negative tests embedded literal forbidden-function scanner tokens and produced false positives. | Corrections `7a5d94e93bfd712fbfcfcc66a47ff7fa9cf60034`, `8630003b00704d03b438a33ace31e7e0ca6596c5`, and `93783b1ce399d154afe157069e8883c522128492`; delivered in clean runtime. | Legacy static safety and Phase 5 security/privacy suites. | Closed |
| P5-D009 | Low | Legacy WPDB test stub lacked array-style `prepare()` and `insert_id` compatibility needed by new repositories. | Corrections through `93783b1ce399d154afe157069e8883c522128492`; delivered in clean runtime. | Phase 5 repository/migration tests plus all legacy core tests. | Closed |
| P5-D010 | Medium | Package verification selected the schema manifest checksum rather than the package checksum because both used `.sha256`. | Workflow correction `ba534f2f861bd8fe81aae719b2b0d07111497326`. | Bootstrap run `30154816477`: immutable package checksum, ZIP integrity, and required runtime entries passed. | Closed |
| P5-D011 | High | The first mandatory two-hour soak run `30155015983` failed after the full pre-soak matrices but did not retain partial cycle/failure evidence because the artifact upload was skipped on step failure; its 155-minute job budget also left insufficient diagnostic margin around a 7,200-second test. The exact failing assertion therefore could not be trusted or reconstructed. | v1 workflow retired in `7e616c17dcc5947a08d6bed0d1d275e04672f960`; resilient v2 workflow added in `7be7bdc463ec07d1ad1c6fedcecea83c0bc1801c` with 210-minute budget, per-cycle logs, ERR trap/failure record, and `if: always()` soak evidence upload. | All ordinary and two-hour acceptance must restart from zero on the new exact head. v2 requires 24 fresh cycles, 7,200 seconds, exact manifests, final package verification, and final packaged matrices. | Closed; prior run evidence invalidated |

## Current open defects

No known Critical, High, Medium, Low, or unclassified implementation defect is open. The new exact head must independently pass ordinary acceptance and the complete v2 two-hour acceptance; evidence from `d7c5b9cae1554e53e244cabc9d2c4e0503fa544a` is not combined with the restarted run.
