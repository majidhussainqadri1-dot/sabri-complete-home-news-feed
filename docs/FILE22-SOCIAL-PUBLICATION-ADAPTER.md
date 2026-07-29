# File 21 → File 22 Social Publication Workflow Adapter

## Purpose

File 21 exposes its native social Composer to File 22 as `social_publication`. File 22 provides the universal gateway and guarded orchestration boundary; File 21 remains the canonical owner of every post, draft, moderation state, native marker, and canonical URL.

## Ownership

File 21 continues to own:

- `/create-post/` and the complete native Composer UI;
- `ComposerPermissions` authorization policy;
- native drafts and publications;
- validation, media, review, scheduling, and moderation states;
- Home, News, profile-timeline, search, and canonical post projections;
- hashed native idempotency markers, reconciliation, retention, and repair;
- native-reference ownership and visibility decisions.

File 22 receives approved adapter metadata, strict schema metadata, opaque native references, controlled status codes, same-origin URLs, and privacy-safe diagnostics. It does not create a duplicate post, body, media copy, patient record, moderation record, or canonical record.

## Adapter identity

| Field | Value |
|---|---|
| Adapter key | `social_publication` |
| Adapter API | `1.0.0` |
| Workflow API | `1.0.0` |
| Schema version | `1.0.1` |
| Minimum File 21 version | `1.0.3` |
| Central capability | `sabri_feed_create_posts` |
| Contract types | `Workflow_Adapter`, `Diagnostic_Adapter` |
| Group | `publishing` |
| Priority | `10` |
| Privacy class | `public` |
| Native route | `/create-post/` |

## Authorization sequence

1. File 22 binds the operation to the authenticated subject.
2. File 22 applies Membership Core account eligibility and `sabri_feed_create_posts`.
3. File 21 applies `ComposerPermissions::user_can_create()`.
4. Reference-sensitive operations recheck File 21 post type, exact status, ownership, edit authority, or visibility.
5. Founder Update and Platform News are omitted from the schema and rejected server-side unless File 21 identifies the subject as Founder or Administrator.
6. Student, patient, suspended, rejected, expired-document, logged-out, and Safe Mode denials remain controlling.

An adapter may narrow the central decision; it never expands it.

## Direct workflow scope

The direct File 22 workflow is deliberately text-first. It supports:

- create or resume an actual File 21 draft;
- side-effect-free validation;
- update an existing draft and return a signed private preview URL;
- idempotent submit, publish, or schedule;
- owner/moderator status retrieval;
- canonical URL retrieval after File 21 visibility checks.

The strict schema exposes only approved scalar fields: native reference, title, content, approved Feed type, topic, visibility, language, country/region, comments flag, disclaimer/privacy confirmations, scheduled date, and publication action.

### Native-route-only workflows

The direct workflow excludes structured Clinical and Patient Case payloads, structured Research, Poll definitions, file/media uploads, Video, and PDF workflows. These remain on File 21's complete route or their dedicated native owner. Protected structures are not flattened into a generic File 22 payload.

## Draft and moderation-state rules

- A native reference uses `post-<positive-id>`.
- Only a WordPress post of type `post` with exact status `draft` is mutable through `create_draft`, `preview`, or a reference-bearing final submission.
- `pending`, `future`, `publish`, `trash`, missing, foreign, and non-post references are not draft-resume targets.
- A pending moderation item therefore cannot be demoted to draft by preview or resume.
- Status lookup remains available only to the native owner or moderator after File 21 authorization.

## Signed preview rules

File 21 returns a same-origin preview URL containing:

- a File 22 preview marker;
- an absolute ten-minute expiry;
- an HMAC signature over post ID, authenticated user ID, and expiry.

A high-priority request-time guard verifies the signature, expiry, authenticated subject, exact draft status, and native edit authority. Forged, expired, cross-user, non-draft, and unauthorized preview requests receive a no-cache 403 response. File 22 stores no preview HTML or protected content body.

## Idempotent submission and recovery

File 22 supplies a two-UUID-v4 idempotency key. File 21:

- stores only a SHA-256 key hash and normalized-payload fingerprint;
- never stores the raw key, post body, patient narrative, or complete form payload in the option record;
- acquires an atomic processing record before native mutation;
- serializes Composer mutation with a separate atomic per-user/per-key execution lease;
- returns `temporarily_unavailable` while another active request owns the lease;
- attaches matching one-way native post markers;
- persists the opaque native reference as soon as it can be proven;
- reconciles a final native post after option-persistence failure without another Composer call;
- returns the existing result for exact replay;
- rejects the same key with a different payload as `conflict`;
- removes a newly-created post if both recovery-marker persistence and safe reconciliation fail, avoiding a knowingly untraceable duplicate candidate.

The native Composer remains the only writer of the post and File 21 content metadata. Idempotency marker metadata is owned by File 21 solely for recovery.

## Concurrency

- `add_option()` provides the atomic processing-record boundary.
- A separate execution lock prevents concurrent retries from invoking the Composer twice on the same mutable draft.
- Lock release verifies the request's owner token.
- A crashed lock expires after two minutes, is reclaimable by a later request, and is removed by bounded maintenance.
- Active locks are never removed by maintenance.

## Retention and repair

- processing lease: 15 minutes;
- completed record: 30 days;
- recoverable draft: one seven-day recovery interval;
- execution lock: two minutes.

Daily bounded maintenance processes at most 100 oldest records per callback. An expired processing record may be reconciled to the current final native status or receive one recoverable-draft interval. Expired completed and already-recoverable records are deleted rather than renewed. Matching native markers are removed when retention ends.

Administrators have `Tools → File 22 Workflow Recovery`. The action is capability- and nonce-protected and reports aggregate counts only. It does not display post IDs, user IDs, native references, raw keys, content, or patient data.

The uninstall boundary clears the scheduled hook. Workflow options, execution locks, and native markers are removed only when the plugin's existing explicit destructive data-retention policy authorizes deletion.

## Status and canonical URL

| WordPress | File 22 |
|---|---|
| `draft` | `draft` |
| `pending` | `pending_review` |
| `future` | `scheduled` |
| `publish` | `published` |
| `trash` | `rejected` |

A canonical URL is returned only for a published native post after `PostMetadata::user_can_view()` confirms visibility for the authenticated subject.

## Controlled errors

File 21 returns only File 22-approved codes such as `permission_denied`, `validation_failed`, `conflict`, `rate_limited`, `temporarily_unavailable`, `not_found`, and `invalid_reference`. Raw Composer messages, validation narratives, stack traces, exception classes, option names, keys, and payload values are not returned through the File 22 boundary.

## Fail-soft behavior

If File 22 is absent, incompatible, disabled, or lacks Workflow API `1.0.0`, File 21 continues to use `/create-post/` and its Home/News fallback CTA. The fallback is removed only when the exact File 21 adapter registered successfully, File 22's Create page and Safe Mode state are acceptable, `supc_adapter_matches()` confirms current ownership/availability, and File 20 confirms its versioned Create producer contract and current-user visibility.

A duplicate or foreign adapter key is not successful File 21 registration. During partial rollout, collision, incompatible upgrade, Safe Mode, or rollback, the native fallback remains available.

## Diagnostics

The privacy-safe health report includes adapter/native identity, versions, capability, privacy class, Workflow/schema versions, native-draft support, preview-expiry enforcement, recovery API readiness, retention days, feature settings, native-route availability, and current availability. It includes no user, post, draft, reference, key, media, patient, or unpublished content.

## Automated evidence boundary

The focused runtime tests cover pending-state immutability, signed expiry, persistence-failure reconciliation, replay, conflicts, visibility, and privacy-safe health. Separate maintenance tests cover one-way retention and stale execution-lock cleanup.

A dedicated workflow checks out exact File 22 Phase 22E source `9aed674344c33b8756b65e7bc58c223ac6ffc4ae`, loads its real interfaces and Workflow Coordinator, and runs File 21 schema, draft, signed preview, submission, status, and canonical-URL operations through that coordinator. This is contract integration evidence, not a substitute for WordPress staging with Files 00, 20, 21, and 22.

## Remaining acceptance

- full Founder, Administrator, verified-doctor, permitted unverified-doctor, student, patient, suspended, rejected, expired-document, and logged-out staging matrix;
- cross-user reference and private canonical-URL checks;
- desktop/mobile Shell agreement;
- browser, keyboard, zoom, screen-reader, RTL, reduced-motion, and forced-colors acceptance;
- backup and rollback proof;
- File 22 dependency merge order;
- explicit Founder merge authorization.
