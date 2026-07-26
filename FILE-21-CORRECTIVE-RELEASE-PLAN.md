# File 21 Corrective Release 1.0.1

## Frozen purpose

This corrective line repairs live production regressions and makes existing public functionality observable without duplicating the complete visual redesign assigned to File 22.

## Mandatory workstreams

1. Founder and Administrator posts resolve to `publish` plus `_sabri_feed_review_state=approved` unless an explicit privacy hold or protected moderation state applies.
2. Founder and Administrator Composer UI exposes **Publish** as the primary action and does not expose **Submit for Review**.
3. Existing pending Founder posts are restored only through a bounded preview-and-select migration with audit and rollback evidence; no blind bulk publication is allowed.
4. An activation wizard must expose dependency, existing-content, public-component, duplicate-protection, preview, and activation steps.
5. File 21 public components must be visibly mounted without replacing the Unified Shell or duplicating the old Feed/navigation.
6. A visibility-safe Profile Timeline data foundation must be provided for File 22.
7. Editorial News, Breaking News, and Corrections must remain independently gated and observable.
8. Acceptance requires desktop/mobile screenshots tied to commit SHA, package SHA-256, environment, role, URL, and gate state.

## Explicit boundary with File 22

File 21 owns publishing correctness, data migration, functional mounting, duplicate guards, News gates, and Timeline data contracts. File 22 owns the complete public visual system, premium cards, profile cover/tabs, responsive visual polish, and the final visual experience.

## Implementation completion matrix

| Workstream | Code status | Evidence |
|---|---|---|
| Founder/Administrator immediate publishing | Implemented | `ComposerPermissions`, `PrivilegedPublishingPolicy`, corrective regression tests |
| Remove privileged Submit for Review action | Implemented | Composer template and server-side action normalization |
| Preserve/restore legacy Founder posts | Implemented | bounded preview-and-select migration, snapshot, audit, cache invalidation |
| Activation Wizard | Implemented | six-step dependency/content/component/duplicate/gate/acceptance screen |
| Observable public mounting | Implemented, fail closed by default | identifiable Home surface enabled only after explicit wizard review |
| Duplicate Feed protection | Implemented | known shortcode detection, one-render guards, activation blocking |
| Duplicate navigation protection | Implemented | Unified Shell destination diagnostics; File 21 inserts no navigation |
| Profile Timeline foundation | Implemented | hook, shortcode, bounded visibility scan, object authorization, REST projection |
| News/Breaking/Corrections gates | Implemented | separate Phase 4 and Phase 5 controls with parent-gate dependency closure |
| Screenshot-bound acceptance | Contract implemented | `FILE-21-LIVE-VISUAL-ACCEPTANCE-CHECKLIST.md` |
| Exact-head corrective CI | Implemented | PHP 8.1/8.3 contracts and immutable candidate workflow |
| Continuous corrective QA | Implemented and required | 3,900 seconds, exactly 13 complete cycles; code changes restart from zero |

## Release boundaries still requiring external acceptance

The implementation is not a production release merely because coding and automated checks pass. The following remain separate gates:

- exact-head corrective CI must finish Green;
- the 3,900-second/13-cycle QA artifact must finish Green;
- immutable candidate checksum and manifest must be retained;
- WordPress/hosting visual screenshots must be completed against the exact candidate;
- Founder/Administrator acceptance must be recorded;
- a separate version-promotion commit may then change the displayed plugin version to `1.0.1`;
- merge and live deployment each require separate explicit authorization.

File 22 must not begin until these File 21 release gates are satisfied.
