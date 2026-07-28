# File 21 → File 22 Social Publication Adapter

## Purpose

File 21 exposes its existing public social Composer to File 22 as the `social_publication` adapter. File 22 provides the universal content-type gateway; File 21 remains the canonical owner of the resulting social publication.

## Ownership

File 21 continues to own:

- `/create-post/` and the native Composer UI;
- `ComposerPermissions` authorization policy;
- native draft and publication records;
- validation, moderation, media, review, scheduling, and publication states;
- Home, News, profile-timeline, search, and canonical post projections.

File 22 receives only adapter metadata and a safe native start route. It does not create a duplicate post, metadata record, media copy, moderation queue, or canonical URL.

## Adapter identity

| Field | Value |
|---|---|
| Adapter key | `social_publication` |
| Adapter API | `1.0.0` |
| Minimum File 21 | `1.0.3` |
| Group | `publishing` |
| Priority | `10` |
| Privacy class | `public` |
| Native route | `/create-post/` |

## Authorization sequence

1. File 22 applies Membership Core status and central capability checks.
2. File 21 applies `ComposerPermissions::user_can_create()`.
3. Founder, Administrator, verified doctor, and permitted unverified doctor policies remain native to File 21.
4. Student, patient, suspended, rejected, expired-document, logged-out, and Safe Mode denials remain controlling.

An adapter may restrict the central decision; it never expands it.

## Fail-soft behavior

If File 22 is absent, incompatible, or disabled, File 21 continues to use its existing public `/create-post/` route and Home/News fallback CTA.

The fallback CTA is removed only when all of the following are true:

- File 22 adapter API `1.0.0` is available;
- File 22 Create page is ready;
- File 22 is not in Safe Mode;
- File 20 declares version `1.0.1` or later with the official Create-visibility producer contract.

When the complete gateway is available, the Shell Create URL is corrected at priority 100 to the universal File 22 Create page. Selecting **Social Post** then opens File 21's native `/create-post/` route.

## Non-goals

This route-only adapter does not yet implement File 22's optional `Workflow_Adapter`. File 22 therefore does not directly autosave, validate, preview, submit, schedule, or reconcile File 21 drafts in this phase. Those operations continue inside File 21 until a separately versioned workflow adapter is approved and tested.

## Acceptance requirements

- one adapter registration per request;
- one native File 21 record per successful submission;
- no File 22 duplicate content storage;
- no loss of File 21 fallback when File 20 or File 22 is incomplete;
- universal Create URL wins only when the complete gateway is operational;
- Founder, Administrator, verified-doctor, unverified-doctor, student, patient, suspended-user, logged-out, and Safe Mode matrix tested on staging;
- desktop and mobile Shell outputs agree;
- rollback by deactivating File 22 restores File 21 fallback behavior without content migration.
