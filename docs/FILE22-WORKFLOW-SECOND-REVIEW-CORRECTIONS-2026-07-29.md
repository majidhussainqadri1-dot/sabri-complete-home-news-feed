# File 21 → File 22 Workflow Second Independent Review and Corrections — 2026-07-29

## Governance status

This review supersedes the earlier statement that no code-level blocker remained. PR #19 had already merged the route adapter into `main`, while PR #20 merged the direct workflow only into its feature base. The corrective work therefore moved to Draft PR #21, which targets `main` and remains unmerged.

## Decision before correction

`REQUEST CHANGES / REJECTED FOR RELEASE`

## Review findings and implemented corrections

### 1. Pending moderation posts could be demoted to draft

The previous adapter treated WordPress `pending` as a mutable draft and passed `draft` back to the native Composer during resume or preview.

**Correction:** only the exact WordPress `draft` status is mutable through `create_draft`, `preview`, or a reference-bearing final submission. Pending, scheduled, published, trashed, missing, foreign, and non-post references fail closed. Tests verify that previewing a pending post does not call the Composer.

### 2. Preview expiry was advisory rather than enforced

The previous result returned `expires_at`, but the ordinary WordPress preview URL had no signature and no request-time expiry decision.

**Correction:** File 21 now signs the post ID, authenticated user ID, and absolute expiry with a WordPress-auth-salt HMAC. A high-priority `template_redirect` guard rejects forged, expired, cross-user, non-draft, and unauthorized File 22 preview URLs with a no-cache 403 response. The returned lifetime is ten minutes and File 22's maximum-TTL validation remains controlling.

### 3. A successful native write could be stranded after option persistence failure

The previous processing record did not reliably retain or rediscover the native post after it reached a final state but completion persistence failed.

**Correction:** File 21 stores only one-way hashes as native post markers, persists the opaque reference before final status mutation, and reconciles the final native status on retry without another Composer call. Raw idempotency keys and payload bodies are never stored.

### 4. Idempotency records had no bounded retention

Completed and stale records could remain in `wp_options` indefinitely.

**Correction:** processing leases expire after 15 minutes, completed records after 30 days, and recoverable drafts after one seven-day recovery interval. A bounded daily batch and explicit Administrator repair reconcile or delete records. The report contains aggregate counts only.

### 5. First-pass maintenance renewed expired records forever

A post-correction review found that the first maintenance implementation called `complete_record()` again for an already-completed expired record and reset a recoverable draft's expiry repeatedly.

**Correction:** corrected maintenance deletes expired `completed` and already-`recoverable` records and removes their matching native markers. Only an expired `processing` record may be reconciled into a new completed interval or receive one recoverable-draft interval. Regression tests prove that completed and recoverable records are not renewed.

### 6. Concurrent retries could repeat Composer side effects on one draft

A post-correction review found that an active processing record with a known draft reference could be accepted by another request before the first request completed.

**Correction:** an atomic per-user/per-key execution option serializes native mutation. Active processing records and active execution locks return `temporarily_unavailable`; only an expired or recoverable lease may retry. The owner token is checked before release. Crash-left execution locks have a short expiry and separate bounded cleanup.

### 7. Crash-left execution locks could accumulate

The atomic lock corrected concurrency but introduced a new retention surface if PHP terminated before `finally` released it.

**Correction:** expired or malformed execution locks are removed in bounded cron batches. Active locks and unrelated options remain untouched. Destructive uninstall cleanup covers both idempotency and execution-lock prefixes.

### 8. Institutional content types were visible to unauthorized users

The global schema could advertise Founder Update or Platform News before rejecting the user on validation.

**Correction:** schema choices now use the current authenticated subject and omit these types unless File 21 identifies the subject as Founder or Administrator. Server-side authorization remains independently enforced.

### 9. Integration evidence used only local contract stubs

The first focused test did not load the actual File 22 Workflow Coordinator.

**Correction:** a dedicated GitHub Actions workflow checks out exact reviewed File 22 Phase 22E source and loads its real Adapter interfaces and Workflow Coordinator. The test runs schema, draft, signed preview, draft-referenced submit, status, and canonical-URL operations against File 21. WordPress runtime collaborators remain controlled test doubles; the File 22 contract and coordinator are actual source.

### 10. Branch and merge state was inaccurate

PR #19 and PR #20 were described as Draft/unmerged after they had already merged, and PR #20 had not landed in `main`.

**Correction:** PR #21 targets `main` from the final workflow feature head and contains the direct adapter plus all corrections in one Draft, unmerged review line. No new merge or deployment was performed.

### 11. New-post submission still contained a process-termination recovery gap

The first corrective adapter created a new post during final `submit()` when no draft reference was supplied, then attached the recovery marker after the Composer returned. A process termination inside that narrow interval could still leave an unmarked final post.

**Correction:** final File 22 submission now requires the opaque native draft reference returned by `create_draft()`. File 21 attaches the one-way recovery marker and persists the native reference before changing that draft to pending, scheduled, or published. Reference-less final submission fails before the Composer is called. This removes the post-create/pre-marker crash window from the final workflow.

### 12. Static release assertions interpolated variables

A CI syntax failure exposed double-quoted test literals containing array access and PHP variables.

**Correction:** release assertions now use literal-safe search strings. The failed CI run is retained as evidence that syntax checks caught the defect before acceptance.

## Privacy and ownership boundaries after correction

- File 21 remains the only native post, draft, metadata, moderation, and canonical-record owner.
- File 22 receives no duplicate post body, patient narrative, media copy, or shadow moderation record.
- Recovery metadata contains hashes, timestamps, state, and an opaque native reference only.
- Administrator recovery output contains no post ID, user ID, native reference, raw key, payload, or content.
- Native marker deletion requires the expected hashes.
- Preview signatures bind to the authenticated subject and expire at request time.
- Final submission cannot create an unreferenced native post; it must transition an already-owned File 21 draft.

## Remaining acceptance gates

The corrections are not a release authorization. PR #21 must remain Draft and unmerged until fresh exact-head CI, post-correction verification, File 22 dependency order, controlled Files 00/20/21/22 staging, full role/reference matrix, browser/accessibility/mobile/RTL checks, backup and rollback proof, and explicit Founder authorization are complete.
