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

## First implementation checkpoint

- privileged publishing policy;
- Founder/Admin review-submission removal;
- server-side `submit` normalization to `publish` for privileged users;
- published privileged-post review-state synchronization;
- regression tests before migration and wizard work.
