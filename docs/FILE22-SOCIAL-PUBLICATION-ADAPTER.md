# File 21 → File 22 Social Publication Workflow Adapter

## Purpose

File 21 exposes its existing public social Composer to File 22 as the `social_publication` adapter. File 22 provides the universal content-type gateway and a guarded server-side orchestration boundary; File 21 remains the canonical owner of every resulting social publication.

## Ownership

File 21 continues to own:

- `/create-post/` and the complete native Composer UI;
- `ComposerPermissions` authorization policy;
- native draft and publication records;
- validation, moderation, media, review, scheduling, and publication states;
- Home, News, profile-timeline, search, and canonical post projections;
- durable idempotency reconciliation for direct File 22 submission;
- native-reference ownership and visibility decisions.

File 22 receives only approved adapter metadata, strict schema metadata, opaque native references, controlled status codes, safe internal URLs, and privacy-safe diagnostics. It does not create a duplicate post, metadata record, media copy, moderation queue, patient record, or canonical URL.

## Adapter identity

| Field | Value |
|---|---|
| Adapter key | `social_publication` |
| Adapter API | `1.0.0` |
| Workflow API | `1.0.0` |
| Schema version | `1.0.0` |
| Minimum and actual File 21 version | `1.0.3` or later |
| Central capability | `sabri_feed_create_posts` |
| Contract types | `Workflow_Adapter`, `Diagnostic_Adapter` |
| Group | `publishing` |
| Priority | `10` |
| Privacy class | `public` |
| Native route | `/create-post/` |

## Authorization sequence

1. File 22 binds the request to the currently authenticated subject.
2. File 22 applies Membership Core status and the canonical `sabri_feed_create_posts` capability.
3. File 21 applies `ComposerPermissions::user_can_create()`.
4. Reference-sensitive operations recheck File 21 ownership, edit authority, or publication visibility.
5. Founder, Administrator, verified doctor, and policy-permitted unverified doctor decisions remain native to File 21.
6. Student, patient, suspended, rejected, expired-document, logged-out, and Safe Mode denials remain controlling.

An adapter may restrict the central decision; it never expands it.

## Direct workflow scope

The approved direct File 22 workflow is deliberately text-first. It supports:

- create or resume a File 21 draft;
- side-effect-free validation;
- update an existing native draft and return a short-lived WordPress preview URL;
- idempotent submit, publish, or schedule;
- owner/moderator status retrieval;
- canonical URL retrieval after File 21 ownership or visibility checks.

The strict schema exposes only approved scalar fields:

- native reference;
- title and content;
- supported social Feed type;
- topic;
- visibility;
- language and country/region;
- comments flag;
- medical disclaimer and patient-privacy confirmations;
- scheduled date;
- publication action.

### Native-route-only workflows

The direct workflow intentionally excludes:

- structured Clinical and Patient Case payloads;
- structured Research payloads;
- Poll definitions;
- file and media uploads;
- Video and PDF native-owner workflows.

These remain available through File 21's complete `/create-post/` route or their dedicated native modules. File 22 must not flatten structured or protected data into a generic payload merely to force direct orchestration.

## Native draft and preview rules

- A draft reference uses the opaque form `post-<positive-id>`.
- Resuming or previewing requires File 21 edit authority for that native post.
- Preview updates the existing File 21 draft rather than creating an invisible File 22 copy.
- File 21 returns a same-origin WordPress preview URL with a ten-minute orchestration lifetime.
- File 22 stores no preview HTML and no protected content body.

## Idempotent submission

File 22 supplies a two-UUID-v4 idempotency key. File 21:

- hashes the key before storage;
- hashes the normalized payload before storage;
- stores no raw key, post body, patient narrative, or form payload in its idempotency record;
- creates an atomic `processing` record before invoking the native Composer;
- returns the previous completed native result for an exact replay;
- rejects the same key with a different payload as `conflict`;
- preserves a processing lock when completion persistence cannot be proven, preventing a blind retry from creating a second post.

The native Composer remains the only writer of the post and plugin-owned metadata.

## Status and canonical URL

WordPress statuses are normalized as follows:

| WordPress | File 22 |
|---|---|
| `draft` | `draft` |
| `pending` | `pending_review` |
| `future` | `scheduled` |
| `publish` | `published` |
| `trash` | `rejected` |

Status retrieval requires File 21 ownership or edit/moderation authority. A canonical URL is returned only for a published native post after `PostMetadata::user_can_view()` confirms visibility for the authenticated subject.

## Controlled native errors

File 21 returns only File 22-approved native codes:

- `permission_denied`;
- `validation_failed`;
- `conflict`;
- `rate_limited`;
- `temporarily_unavailable`;
- `not_found`;
- `invalid_reference`.

Raw Composer messages, validation narratives, payload values, filesystem paths, stack traces, exception classes, idempotency keys, and native option names are not returned through the File 22 boundary.

## Fail-soft behavior

If File 22 is absent, incompatible, disabled, or lacks Workflow API `1.0.0`, File 21 continues to use its existing public `/create-post/` route and Home/News fallback CTA.

The fallback CTA is removed only when all of the following are true:

- File 22 Adapter API `1.0.0`, Workflow API `1.0.0`, Workflow Adapter, and Diagnostic Adapter contracts are available;
- all approved File 22 workflow PHP functions are present;
- this exact File 21 adapter registered successfully;
- `supc_adapter_matches()` confirms `social_publication` belongs to File 21 and is currently available;
- the File 22 Create page is ready;
- File 22 is not in Safe Mode;
- File 20 is version `1.0.1` or later;
- File 20 declares Create contract version `1.0.0` or later;
- File 20 confirms that its producer contract is active;
- File 20 confirms that Create is visible for the current authorized user.

A File 20 or File 22 version string by itself is not sufficient. A duplicate or foreign adapter key is not treated as successful File 21 registration. During partial rollout, collision, incompatible upgrade, Safe Mode, or rollback, the File 21 fallback remains available.

## Diagnostics

The adapter provides only privacy-safe operational data:

- adapter and native-module identity;
- declared minimum and actual runtime version;
- central capability and privacy classification;
- Workflow API and schema versions;
- native-draft declaration;
- idempotency API readiness;
- Composer setting and feature-gate state;
- native-route availability;
- current adapter availability.

No user, patient, post, draft, native reference, idempotency key, media, or unpublished content is included.

## Acceptance requirements

- one successful File 21 adapter registration per request;
- duplicate or foreign adapter collision does not remove the fallback;
- one native File 21 record per successful idempotent submission;
- same-key, same-payload replay returns the existing native result;
- same-key, different-payload replay fails with `conflict`;
- failed completion persistence cannot trigger a duplicate retry;
- no File 22 duplicate content storage;
- no loss of File 21 fallback when File 20 or File 22 is incomplete;
- Founder, Administrator, verified-doctor, policy-permitted unverified-doctor, student, patient, suspended-user, rejected-user, expired-document, and logged-out matrix tested on staging;
- cross-user native-reference and private canonical-URL denial tested on staging;
- desktop and mobile Shell outputs agree;
- rollback by deactivating File 22 restores File 21 fallback behavior without content migration.
