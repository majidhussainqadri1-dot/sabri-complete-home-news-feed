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

## Entries

No open entries at initialization. Implementation and QA findings will be appended here.
