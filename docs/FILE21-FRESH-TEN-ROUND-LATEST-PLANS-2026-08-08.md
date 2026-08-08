# File 21 — Fresh Ten-Round Latest-Plan Review and Correction Ledger — 2026-08-08

## Governing basis
This review re-opened File 21 after newer Founder-approved/current planning material became available. It evaluates the current File 21 NG30 implementation against the consolidated governing plan and the current companion boundaries for File 00, File 01, File 19, File 20, File 24, File 25 and File 26.

Completion states remain separate. This ledger can establish repository/code, deterministic-package and automated-QA evidence only. Hostinger staging acceptance, production deployment and operational acceptance remain external gates.

## Ten fresh review rounds

| Round | Focus | Defect? | Correction / outcome |
|---|---|---:|---|
| 1 | File 00 Advanced Trust/current action assurance, identity subject binding and professional verification | No | Existing File 00 assertion contract binding, active-state checks, current-session 2FA assurance and File 09 professional verification handoff remain fail-closed. |
| 2 | NG30 private-user state privacy lifecycle | Yes | Added WordPress personal-data exporter/eraser for followed topics, reading progress, Read Later, Offline Pack selection, data-saving preferences, catch-up marker and Personal Feed Recipe. |
| 3 | Cross-domain visibility for Social Post + Editorial News | Yes | Replaced NG30 social-only visibility checks with the canonical `InteractionPermissions::can_view_post()` gate, which delegates Editorial News to `NewsPolicy` and social posts to `PostMetadata`. |
| 4 | Abuse/resource protection of read-heavy NG30 REST surfaces | Yes | Added bounded rate-limit buckets for post context, compare, share card, stories, My Topics, catch-up, Offline Pack and digest preview. |
| 5 | File 19 Intelligent Attention / notification event contract | Yes | Digest candidate handoff now carries deterministic event ID/idempotency key, trace ID, candidate window and versioned event type while File 19 remains delivery/policy owner. |
| 6 | File 20 future-shell asset loading and File 01 route-specific performance boundary | Yes | NG30 corrective CSS/JS now load only on applicable Home/News/article/archive/shortcode contexts, with an explicit integration filter for approved custom mounts. |
| 7 | File 25 visual-system/share-card ownership | No | File 21 remains semantic payload/context owner; File 25 remains optional visual renderer through its dedicated adapter hook. No duplicate visual backend found. |
| 8 | File 26 global Search/Discovery/Ranking ownership | No | File 21 continues to consume File 26 Why-Trending/Related-Knowledge adapters and does not create a second global ranking/discovery backend. |
| 9 | State-changing NG30 authorization/nonce/assurance path | No | Current action assurance, capability/object policy, nonce checks, mutation rate limits and canonical topic validation remain present after corrections. |
| 10 | Fresh retest, regression permanence and deterministic packaging | Yes | Added a dedicated latest-plan ten-round source gate and made the new privacy runtime package-mandatory. The first automated application attempt also exposed a workflow-token permission issue; the correction was applied through a permitted path and the one-shot workflow was removed afterward. |

## Defect-round count
Defects were found in rounds **2, 3, 4, 5, 6 and 10**. No new product/code defect was found in rounds **1, 7, 8 and 9**.

## Release identity
- Package: 1.0.5
- Runtime/API: 1.0.3
- Schema: 1.0.0
- Database migration introduced by this correction: none
- Base main before this fresh cycle: `ac83c987ab39d599ec4f5f092f1a1dedcaeaa1ee`
- Corrected source commit produced by the deterministic patch pass: `98741475169e3a8d4393ef6cc0f1f96698fe6655`

## Acceptance rule
Repository completion may be claimed only after the final PR exact head and the resulting main merge head pass the governing build/regression/package, authorization/cross-owner, File 22/File 26 contract, official PHPUnit/PHPStan/WPCS and mandatory browser/Playground gates, plus the dedicated latest-plan gate. Staging/production/operational completion must not be inferred from CI.
