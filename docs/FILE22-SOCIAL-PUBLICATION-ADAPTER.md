# File 21 → File 22 Social Publication Workflow Adapter

## Purpose and ownership

File 21 exposes its native social Composer to File 22 as `social_publication`. File 22 supplies the universal gateway and guarded orchestration boundary. File 21 remains the canonical owner of `/create-post/`, permissions, drafts, posts, media, moderation, publication status, recovery markers, and canonical URLs.

File 22 receives only approved metadata, schema declarations, opaque references, controlled statuses, safe URLs, and privacy-safe diagnostics. It creates no duplicate post, body, media copy, patient record, moderation record, or canonical record.

## Contract identity

| Field | Value |
|---|---|
| Adapter key | `social_publication` |
| Adapter API | `1.0.0` |
| Workflow API | `1.0.0` |
| Subject Schema API | `1.0.0` |
| Schema version | `1.0.1` |
| Minimum File 21 | `1.0.3` |
| Central capability | `sabri_feed_create_posts` |
| Contracts | `Workflow_Adapter`, `Diagnostic_Adapter`, subject-aware schema extension |
| Group | `publishing` |
| Priority | `10` |
| Privacy | `public` |
| Native route | `/create-post/` |

## Authorization and availability

1. File 22 binds the request to the authenticated subject.
2. Membership Core eligibility and `sabri_feed_create_posts` run first.
3. File 21 native availability is evaluated before adapter-specific authorization.
4. `ComposerPermissions::user_can_create()` may narrow but never expand permission.
5. Reference operations recheck post type, exact state, ownership/edit authority, or visibility.
6. Student, patient, suspended, rejected, expired-document, logged-out, Safe Mode, and emergency-disable denials remain controlling.

File 21's `can_create()` currently includes its own availability guard for defense in depth. Corrected File 22 does not rely on that coupling to classify state: native unavailability is reported as unavailable, never as permission denial.

## Role-neutral and subject-aware schema

`UniversalComposerSubjectSchemaAdapter` wraps the native publication adapter.

- `schema()` is role-neutral and data-free. It contains every configured supported publication type and is used only for static contract health.
- `schema_for_user( int $user_id )` is used for interactive schema and payload validation.
- Founder Update and Platform News appear only for a subject whom File 21 identifies as Founder or Administrator.
- The same restricted types remain server-side prohibited for doctors and other noninstitutional users.

This separation prevents Administrator health checks from being treated as a global role matrix while ensuring Doctor clients never receive Founder-only choices.

The direct workflow remains text-first. It declares native reference, title, content, approved Feed type, topic, visibility, language, country/region, comments flag, disclaimer/privacy confirmations, scheduled date, and publication action.

Structured Clinical/Patient Case, structured Research, Polls, uploads, Video, and PDF remain on complete native-owner routes and are not flattened into File 22.

## Schema-bound payloads

Corrected File 22 validates every supplied field against the authenticated subject's schema before File 21 mutation. Unknown fields, missing required values, wrong types, unsupported choices, invalid formats, and unsafe/oversized structures fail before native Composer execution.

File 21 continues to perform authoritative native validation and publication-policy enforcement after the File 22 contract boundary.

## Draft and moderation rules

- Native reference format is `post-<positive-id>`.
- Only a native WordPress `post` with exact status `draft` is mutable through create/resume/preview/first final transition.
- Pending, scheduled, published, rejected, trashed, missing, foreign, and non-post references cannot be demoted through preview or resume.
- Status lookup remains limited to the owner or moderator after native authorization.

## Signed preview

File 21 returns a same-origin preview URL containing a File 22 marker, absolute ten-minute expiry, and HMAC over post ID, authenticated user ID, and expiry.

A high-priority request guard rechecks signature, expiry, subject, exact draft status, and native edit authority. Forged, expired, cross-user, non-draft, and unauthorized previews receive no-cache 403. File 22 stores no preview HTML or protected body.

## Idempotency, concurrency, and recovery

File 22 supplies two UUID-v4 values separated by a colon. File 21 stores only the SHA-256 key hash, normalized payload fingerprint, bounded state/timestamps, and opaque native reference.

File 21:

- acquires an atomic processing record before native mutation;
- uses a separate per-user/per-key execution lease;
- attaches one-way native markers and persists reference before final state mutation;
- returns the same result for same key/same payload;
- returns `conflict` for same key/different payload;
- reconciles a final post after completion-write failure without another Composer call;
- removes a newly created post only when recovery identity cannot be made durable safely.

Retention:

- processing lease: 15 minutes;
- completed record: 30 days;
- recoverable draft: one seven-day interval;
- execution lock: two minutes.

Bounded daily maintenance and `Tools → File 22 Workflow Recovery` reconcile or delete stale state with aggregate-only output. Active locks and unrelated options are preserved.

## Status and canonical URL

| WordPress | File 22 |
|---|---|
| `draft` | `draft` |
| `pending` | `pending_review` |
| `future` | `scheduled` |
| `publish` | `published` |
| `trash` | `rejected` |

A canonical URL is returned only for a published native post after File 21 visibility checks for the authenticated subject.

## Controlled errors and diagnostics

File 21 returns only File 22-approved codes. Raw Composer messages, validation narratives, stack traces, exception classes/messages, option names, raw keys, IDs, references, payloads, content, patient data, and secrets do not cross the boundary.

Health output includes controlled adapter/native identity, versions, capability, privacy, Workflow/schema versions, native-draft support, subject-schema scope, preview-expiry enforcement, recovery readiness, retention, feature settings, native-route availability, and current availability.

## Fail-soft gateway

File 21 requires exact File 22 Adapter, Workflow, Subject Schema, and public API version/owner/function-ownership markers. It also requires the exact File 20 Create producer contract.

If File 22 or File 20 is absent, incompatible, colliding, disabled, unavailable, or not ready, File 21 retains `/create-post/` and Home/News fallback actions. A duplicate or foreign adapter key is not successful registration.

## Automated evidence

The dedicated real-contract workflow checks out:

- the exact current File 21 corrective PR head;
- corrected File 22 runtime `d286125e921e3a46f3272071b99eb3f9a874f0b4`.

It runs File 22's real interfaces and Workflow Coordinator against the File 21 subject-aware adapter and proves:

- static role-neutral contract health;
- Doctor exclusion and Founder inclusion of institutional choices;
- schema-bound draft, signed preview, idempotent submit, status, and canonical URL;
- bounded maintenance and stale-lock cleanup.

This remains source-contract evidence, not a substitute for real WordPress staging with Files 00, 20, 21, and 22.

## Remaining acceptance

- complete role/status/document and cross-user reference staging matrix;
- File 20 desktop/mobile parity and fallback restoration;
- cache/indexing isolation;
- browser, keyboard, 200%/400% zoom, screen reader, Urdu RTL, reduced motion, and forced colors;
- verified backup restoration and rollback;
- independent final review and explicit Founder merge authorization.
