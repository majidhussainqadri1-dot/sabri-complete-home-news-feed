# File 21 → File 26: Keyset Shadow Source Cursor v1 — Evidence, Migration and Acceptance Runbook

**Date:** 12 November 2026 (PKT). **Incident classification:** repository-confirmed source pagination defect, **not** a verified Live incident.  
**Canonical source:** File 21 (Home, Social Publishing, Editorial News). **Derivative index:** File 26 (Search/Discovery/Ranking).  
**Governing plan:** *صابری سوشل ہومیوپیتھی پلیٹ فارم — جامع مرکزی ماسٹر پلان V12.1*, File 21 charter and File 26 data-authorization boundary.  
**Frozen source main at defect reproduction:** `626b380328d4400014504e679032291cd023bbdb`. Corrective review: independent Draft PR #61, Issue #60.  
**File 26 baseline:** main `1b064c6ea9f81195611ed001d5457e8ced65460a`; proposed shadow v12 is separate Draft PR #22. Do not assume either branch is deployed.

## 1. Source defect and exact reproduction

Original `includes/class-search-provider-registry.php::file26_list_batch()` paginated the shifting `post_status=publish` set using `offset`. With IDs `[10,20,30,40]` and page size 2, the first page `[10,20]` returned numeric offset 2. If ID 10 was unpublished before the next request, the eligible set became `[20,30,40]` and offset 2 returned only ID 40, silently omitting eligible ID 30.

File 26's `Indexer::process_queue()` only verifies that the returned cursor changes when `done=false`. It does not prove full source key coverage. A successful job can therefore have an incomplete index.

## 2. Candidate correction and source contract

- Initial cursor: empty string (`''`), as File 26 uses for new jobs.
- Subsequent cursor: `v1:id:<last_scanned_positive_WordPress_ID>`; the version and semantic type are explicit.
- Read canonical WordPress posts by `ID > cursor`, `post_type IN ('post', File21 News CPT)`, `post_status='publish'`, sorted strictly `ID ASC`, limited to 1–100. This is a **read-only** WordPress core posts-table query.
- Every candidate remains subject to File 21's current status, review/visibility and Editorial News public projector. An excluded document still advances the last-scanned ID, preventing indefinite rescanning.
- Source data, privacy and publishing state stay in File 21; File 26 owns only derivative shadow/index state. No new tables, back-end search service or ownership transfer.
- Legacy numeric cursors, malformed/overflow cursors, unordered IDs, missing database adapter, failed SQL, foreign connector scopes and unsupported partial-object scopes must fail closed with `WP_Error`, never report `done=true`.
- A newly published post with a larger ID can appear in a later page; a previously passed ID republished **behind** the cursor cannot be guaranteed in that pass and requires separately tested owner-event delivery and source-to-shadow reconciliation. Issue #58 / Draft PR #59 covers durable owner event sequence. This keyset fix alone does **not** prove a consistent concurrent snapshot.

## 3. Migration/restart of pre-existing jobs — mandatory before rollout

**Do not** convert a persisted numeric `cursor_value` to `v1:id:N`; numeric offset and WordPress ID are not interchangeable.

1. In staging, freeze **exact deployed** File 21 and 26 packages/checksums, plugin active state, File 26 DB schema/job table version, current migration/activation state, and real queue/worker logs. Repository HEAD is not deployed reality.
2. Identify source-scan jobs using the *actual* File 26 job table resolved by its DB abstraction. Distinguish empty, numeric legacy offset, current `v1:id:` and unrecognized values. Record each job UUID, connector, state, cursor, creation time, generation and previous release checksum in an access-limited audit record.
3. Disable workers while assessing any in-flight numeric job. Use the File 26 **approved** job cancellation/discard/next-generation workflow; do not directly rewrite cursor columns, update job status with raw SQL or mutate active index/generation. For unknown or absent lifecycle APIs, stop and request owner migration support.
4. Start a **fresh** explicitly approved shadow generation with an empty cursor only after backup+restore capability and version compatibility are verified. Never reinterpret a resume as a continuation of the old generation.
5. Compare sorted source public canonical keys with shadow-generation keys, exclusions, latest source event versions, tombstones and correction/retraction states; **counts alone are insufficient**.
6. Carry out independently authorized cutover and rollback rehearsals under File 26's own v12 requirements, then verify old public results, removed results and restored public results.
7. Record Founder/operator sign-off and actual Live re-test before closing #60.

## 4. Acceptance matrix

| Case | Expected |
|---|---|
| Initial IDs 10/20/30/40, batch size 2 | first page 10,20; `v1:id:20` |
| Withdraw ID10 between pages | second page 30,40; no skip |
| Delete ID10 between pages | second page 30,40; no skip |
| Metadata-only restricted item in later page | omitted but cursor advances |
| Insert later ID50 | eventually included in higher-ID page |
| Retry identical cursor in stable source | same ordered records and next cursor |
| Numeric old cursor `2` | explicit failure, never cast as ID |
| Old unversioned `id:20`, malformed, overflow | explicit failure |
| Partial `object_id` scope | explicit unsupported failure, no accidental full scan |
| Failed DB read / unordered IDs | fail-closed, no false completed job |
| Current File 26 v12 shadow staging | actual source/target key reconciliation and no serving-table mutation |
| Concurrent older-ID restore behind cursor | tested owner event delivery and/or second consistent reconciliation, NOT guaranteed by keyset alone |
| Deploy and migration | verified exact package, DB/schema, job state, backup, rollback, Live re-test |

## 5. Repository QA evidence and limits

- Local behavior regression: `tests/run-file21-file26-keyset-source-tests.php` using a deterministic WordPress-DB simulation; covers keyset movement, guards and failure scenarios.
- Exact-head integration/static gate: `.github/workflows/file21-file26-real-contract.yml` lints and runs the new test alongside File 26 contract checks.
- Real ephemeral WordPress acceptance: `tests/run-file21-file26-keyset-playground-tests.mjs`, launched by `isolated-wordpress-keyset`. It activates the actual File21 plugin under WordPress Playground, creates approved public posts through WordPress APIs, removes an earlier published post from the candidate set, checks later IDs are not skipped, checks repeatability, and asserts rejection of legacy and partial cursors. The new job's first exact-source execution passed on commit `3611e724f4af2e701a75402ccad086582c4bd3bd`. Playground uses a disposable WordPress/SQLite environment; it does not constitute installed File26+MariaDB, Hostinger staging or Live evidence.
- Ephemeral real-SQL acceptance: `tests/integration/file21-file26-keyset-mariadb.php`, launched by the independent `isolated-mariadb-keyset` job under the same File21/File26 workflow. The job exercises the actual File21 source method with an ephemeral MariaDB 11.4 WordPress-shaped posts table and a deterministic PHP WordPress adapter. It verifies draft/delete/privacy/review transitions, cursor advancement, legacy rejection and SQL error containment. Its first exact-source execution was successful on corrective commit `72ca09cb5ede4bf320262fdb4ee0539f010d3d11`; require fresh success at final PR HEAD before promotion.
- **Not proved by these tests:** installed unmodified WordPress runtime with real File26 indexing, concurrent multi-transaction source updates, File 26 v12 PR #22 staging, legacy job remediation, source-to-shadow exact-key equivalence, real server cache behavior, restore drills or deployed parity. An ephemeral MariaDB query test is genuine SQL evidence but not full WordPress/Hostinger acceptance.
- Any CI pass is repository-only evidence. Promotion requires separate staging and Live evidence.

## 6. Mandatory status ledger (update at every next session)

| Field | Current evidenced state |
|---|---|
| Repository main HEAD | `626b380328d4400014504e679032291cd023bbdb` at start |
| Corrective source | isolated Draft PR #61; re-freeze current PR SHA before action |
| File 21 source package / runtime / schema | 1.0.5 / 1.0.3 / 1.0.0 in GitHub main |
| File 26 source schema / contract | GitHub main 1.0.0 / 1.3 (not Live proof) |
| Deployed File 21 and 26 artifacts/checksums | Unverified |
| DB version, actual job table and in-flight cursor state | Unverified |
| Migration/rollback | Not executed |
| Staging acceptance | Not executed |
| Live re-test | Not executed |
| Issue #60 | Source candidate only; keep open |

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**
