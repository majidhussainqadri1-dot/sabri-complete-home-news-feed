# File 21 Corrective Release 1.0.1 — Historical Baseline

## Status

This document records the first corrective line created after live publishing regressions were discovered. Its completed safety contracts remain binding, but its completion matrix is superseded by `FILE-21-HARMONIZATION-COMPLETION-PLAN.md` wherever the later architecture audit found an omission, conflict, incorrect integration contract, incomplete Home specification, version mismatch, or missing migration.

## Frozen historical purpose

The corrective line repaired live production regressions and made existing public functionality observable without duplicating the complete visual redesign assigned to File 22.

## Historical mandatory workstreams

1. Founder and Administrator posts resolve to `publish` plus `_sabri_feed_review_state=approved` unless an explicit privacy hold or protected moderation state applies.
2. Founder and Administrator Composer UI exposes **Publish** as the primary action and does not expose **Submit for Review**.
3. Existing pending Founder posts are restored only through a bounded preview-and-select migration with audit and rollback evidence; no blind bulk publication is allowed.
4. An activation wizard exposes dependency, existing-content, public-component, duplicate-protection, preview, and activation steps.
5. File 21 public components are visibly mounted without replacing the Unified Shell or duplicating the old Feed/navigation.
6. A visibility-safe Profile Timeline data foundation is provided for File 22.
7. Editorial News, Breaking News, and Corrections remain independently gated and observable.
8. Acceptance requires desktop/mobile screenshots tied to commit SHA, package SHA-256, environment, role, URL, and gate state.

## Boundary with File 22

File 21 owns publishing correctness, identity authority, data migration, functional mounting, Home composition, duplicate guards, News gates, Search and Timeline data contracts. File 22 owns the complete public visual system, premium cards, profile cover/tabs, responsive visual polish, and the final visual experience.

## Historical implementation matrix

| Workstream | Historical status | Current authority |
|---|---|---|
| Founder/Administrator immediate publishing | Implemented | Retained and extended by canonical identity adapter |
| Remove privileged Submit for Review action | Implemented | Retained |
| Preserve/restore legacy Founder posts | Implemented | Retained |
| Activation Wizard | Implemented | Replaced by comprehensive seven-step Wizard |
| Observable public mounting | Implemented | Retained as fallback; official File 20 slots are now required |
| Duplicate Feed protection | Implemented | Retained |
| Duplicate navigation protection | Implemented | Retained; File 20 remains authoritative |
| Profile Timeline foundation | Implemented | Retained |
| News/Breaking/Corrections gates | Implemented | Retained |
| Screenshot-bound acceptance | Contract implemented | Expanded by current live acceptance checklist |
| Exact-head corrective CI | Implemented | Superseded by harmonization CI and final continuous QA |
| Continuous corrective QA | Historical evidence retained | Must restart on the final harmonized exact head |

## Current release boundaries

Coding and automated checks do not by themselves authorize production deployment. The current harmonization line additionally requires:

- canonical identity and trusted verified-doctor tests;
- selected File 04 migration and non-destructive rollback tests;
- complete Home controls, ranking and row-provider tests;
- official File 20 Home/News/sidebar slot tests;
- plugin `1.0.1` and schema `1.0.0` identity consistency;
- exact-head source and immutable package checks;
- uninterrupted final continuous QA after the last code change;
- real WordPress/hosting screenshots and Founder acceptance;
- separate merge and live-deployment authorization.

File 22 must not begin until the current harmonized File 21 and companion File 20 acceptance gates are satisfied.