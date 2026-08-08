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
| 5 | File 19 Intelligent Attention / notification event contract | Yes | The first correction added deterministic event/dedup metadata, but direct inspection of File 19 v3 then exposed a deeper contract defect: the legacy custom action alone was not File 19's canonical ingestion API and the payload lacked the strict `sun.event.v1` envelope. The final correction now registers `file21-home-news-feed`, emits `Publishing.DigestCandidatesPrepared` with exact producer/owner/event/schema/time/recipient/idempotency/trace data, calls `sun_ingest_domain_event()`, reports unavailable/rejected states honestly, retains the old action only as a compatibility observer, and pins the File 19 contract head in a dedicated cross-repository gate. File 19 remains policy/quiet-hours/digest/retry/delivery owner. |
| 6 | File 20 future-shell asset loading and File 01 route-specific performance boundary | Yes | NG30 corrective CSS/JS now load only on applicable Home/News/article/archive/shortcode contexts, with an explicit integration filter for approved custom mounts. |
| 7 | File 25 visual-system/share-card ownership | No | File 21 remains semantic payload/context owner; File 25 remains optional visual renderer through its dedicated adapter hook. No duplicate visual backend found. |
| 8 | File 26 global Search/Discovery/Ranking ownership | No | File 21 continues to consume File 26 Why-Trending/Related-Knowledge adapters and does not create a second global ranking/discovery backend. |
| 9 | State-changing NG30 authorization/nonce/assurance path | No | Current action assurance, capability/object policy, nonce checks, mutation rate limits and canonical topic validation remain present after corrections. |
| 10 | Fresh retest, regression permanence and deterministic packaging | Yes | Added a dedicated latest-plan ten-round source gate and made the new privacy runtime package-mandatory. The initial correction workflow exposed a GitHub token permission constraint and was replaced with a permitted correction path, then removed. The first pinned File 21↔File 19 exact-contract run also exposed a test-design defect: it incorrectly assumed File 19's producer registry enforced `schema_versions`; direct source inspection proved producer authorization is event-type based while `schema_version` is validated in the event envelope. The assertion was corrected to the actual `register_runtime`/`event_types`/`authorize_type` contract, while the required event-level `schema_version` gate remains. |

## Defect-round count
Defects were found in rounds **2, 3, 4, 5, 6 and 10**. No new product/code defect was found in rounds **1, 7, 8 and 9**.

## Release identity
- Package: 1.0.5
- Runtime/API: 1.0.3
- Schema: 1.0.0
- Database migration introduced by this correction: none
- Base main before this fresh cycle: `ac83c987ab39d599ec4f5f092f1a1dedcaeaa1ee`
- Corrected source commit produced by the initial deterministic patch pass: `98741475169e3a8d4393ef6cc0f1f96698fe6655`
- File 19 v3 contract head pinned for cross-repository compatibility: `5cb83d399f35ae1636415fb83373b6ba282e3685`

## Acceptance rule
Repository completion may be claimed only after the final PR exact head and the resulting main merge head pass the governing build/regression/package, authorization/cross-owner, File 19/File 22/File 26 contract, official PHPUnit/PHPStan/WPCS and mandatory browser/Playground gates, plus the dedicated latest-plan gate. Staging/production/operational completion must not be inferred from CI.
