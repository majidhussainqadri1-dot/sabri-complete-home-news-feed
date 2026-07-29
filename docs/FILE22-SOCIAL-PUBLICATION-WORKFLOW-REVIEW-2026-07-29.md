# File 21 → File 22 Workflow Adapter Post-Implementation Review — 2026-07-29

## Scope

This review was performed after the initial direct `Workflow_Adapter` implementation and before the branch could be described as complete. It reviewed the File 21 adapter, File 22 bridge handshake, native Composer reuse, native-reference ownership, idempotency, schema, diagnostics, fallback behavior, and tests.

## Decision

`REQUEST CHANGES` was the correct initial decision. The route-only adapter direction was preserved, but the first direct-workflow implementation required the corrections below before automated acceptance.

## Findings and corrections

### 1. Published or scheduled records could be reopened as drafts

The first implementation accepted any owned `post-<id>` reference for draft creation, preview, or submission. Because those operations reuse the native Composer, a published or scheduled post could have been changed back to draft.

**Correction:** reference-sensitive mutation now accepts only native `draft` or `pending` records. Published, scheduled, trashed, missing, foreign, or non-post references fail closed.

### 2. Direct schema advertised feed types disabled by File 21 settings

The first schema used a hard-coded direct type list without intersecting it with the current `allowed_feed_types` setting.

**Correction:** schema choices and payload normalization now require both the approved direct-workflow allowlist and the current File 21 Composer setting.

### 3. Institutional publication types lacked a native role restriction

`founder-update` and `platform-news` are accepted by the general native Composer configuration, but they must not become available to an ordinary doctor merely because the account can create social posts.

**Correction:** these two direct workflow types now require File 21 Founder or Administrator identity. Other accounts receive `permission_denied`. This restriction narrows central permission and never expands it.

### 4. Submission acquired an idempotency record before side-effect-free native validation

The first submit path normalized the payload and relied on the mutating native Composer to perform validation after the processing record had been acquired.

**Correction:** File 21 now runs `ComposerValidation::validate()` before acquiring the idempotency record. Invalid payloads do not create processing records.

### 5. Completed idempotency replay trusted a stored status snapshot

The first replay returned the status stored at completion even if moderation or publication state later changed.

**Correction:** replay retains the same native reference but reads and normalizes the current native WordPress status before returning the result.

### 6. Preview did not explicitly honor the native preview setting

The first direct preview path created a File 21 draft preview even when the native Composer preview setting could be disabled.

**Correction:** preview now fails closed when `previews_enabled` is off.

### 7. Runtime dependency health was incomplete

The initial availability check did not enumerate every class used by schema, policy, identity, and workflow operations.

**Correction:** availability now verifies Settings, Safe Mode, Feed Context, Canonical Identity, Composer Permissions, Composer Validation, Composer, Post Metadata, and Public Composer Surface.

### 8. Idempotency readiness diagnostics omitted failure cleanup support

The first privacy-safe health report checked add, read, and update option APIs but not the delete API used to clear a failed pre-publication processing record.

**Correction:** idempotency readiness now requires `add_option`, `get_option`, `update_option`, and `delete_option`.

### 9. Static exclusion assertions were too broad

The initial static test searched for any occurrence of the words `research` and `poll`, even though normalized payloads intentionally contain empty native structured arrays.

**Correction:** the test now checks that these types are absent specifically from the direct supported-type constant, while runtime tests verify that they are not advertised or accepted.

## Security and ownership conclusions after correction

- File 21 Composer remains the only native post and metadata writer.
- File 22 receives no duplicate body, media record, patient record, or moderation record.
- Raw idempotency keys and normalized payload bodies are not stored in the File 21 idempotency option.
- A completion-persistence failure preserves the processing lock, so a blind retry cannot create another post.
- Status requires native edit/moderation authority.
- Canonical URL resolution requires a published post and native File 21 visibility.
- Complete structured Clinical Case, Research, Poll, upload, Video, and PDF workflows remain outside this direct text-first phase.
- File 21 `/create-post/` remains the fail-soft fallback and rollback route.

## Required verification

Before this branch can pass its code scope:

1. all PHP syntax checks must pass;
2. the complete existing File 21 corrective suite must remain green;
3. the new isolated workflow runtime test must pass;
4. all existing packaging, harmonization, Phase 4A, Phase 4B, and Phase 4C workflows must remain green;
5. a separate post-correction verification record must be added against the exact tested head;
6. the PR must remain Draft and unmerged until cross-plugin staging and role/ownership acceptance.
