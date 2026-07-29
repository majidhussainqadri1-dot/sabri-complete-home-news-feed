# File 21 → File 22 Workflow Adapter Post-Correction Verification — 2026-07-29

## Verification purpose

This verification was performed after the separate post-implementation review and after every recorded correction was applied. It verifies the corrected File 21 native `Workflow_Adapter`; it does not replace cross-plugin staging acceptance.

## Exact corrected runtime head

`fc6c2777c18b79b28ae380cf58210306f7c20335`

## Verified corrections

- File 21 implements File 22 Workflow API `1.0.0` and Diagnostic Adapter API `1.0.0` on the existing `social_publication` adapter.
- File 21 remains the only post, draft, metadata, moderation, status, and canonical-record writer.
- Direct schema choices are the intersection of the approved text-first allowlist and current File 21 Composer settings.
- Structured Clinical Case, Research, Poll, upload, Video, and PDF workflows are not advertised or accepted by this direct adapter.
- Founder Update and Platform News direct submissions require File 21 Founder or Administrator identity.
- Native draft mutation accepts only owned/editable `draft` or `pending` posts; a published or scheduled post cannot be reopened through this Create workflow.
- Preview requires an existing mutable native draft and the native `previews_enabled` setting.
- Submission performs side-effect-free native validation before acquiring an idempotency record.
- Raw idempotency keys and normalized payload bodies are not stored; only hashes, controlled state, native reference, and status metadata are retained.
- Exact replay returns the existing native reference and current native status without another Composer write.
- Same-key conflicting payload fails with `conflict`.
- Completion-persistence failure preserves the processing lock and a retry cannot create a second native record.
- Status requires File 21 edit/moderation authority.
- Canonical URL requires a published native post and File 21 visibility for the authenticated subject.
- Registration exception diagnostics use a fixed code and do not expose exception class names.
- File 21's `/create-post/` route and fallback CTA remain available when File 20 or File 22 is absent, incomplete, incompatible, in Safe Mode, or rolled back.

## Focused runtime evidence

`tests/run-file21-file22-workflow-adapter-tests.php` verifies:

- exact adapter and workflow versions;
- strict schema and direct-workflow exclusions;
- disabled feed-type omission;
- native draft creation and short-lived preview;
- institutional type denial for a non-institutional account;
- side-effect-free validation;
- idempotent submission and no duplicate Composer call;
- conflicting payload rejection;
- refusal to reopen a published post as a draft;
- cross-user status denial;
- public canonical visibility and private canonical denial;
- fail-closed completion-persistence behavior;
- privacy-safe health output.

The focused runtime test is included from `tests/run-file21-production-rejection-tests.php`, which itself remains part of the complete File 21 regression suite. It does not invoke forbidden process-execution functions.

## Automated evidence

GitHub Actions run:

- workflow: `Build and Test Home News Feed`;
- run number: `1510`;
- run ID: `30414458724`;
- exact head: `fc6c2777c18b79b28ae380cf58210306f7c20335`;
- conclusion: `success`.

Successful jobs:

1. Exact-head build, complete PHP behavior and phase regression suite, JavaScript syntax, static security and whitespace checks, canonical package build, checksum, manifest, and structure verification.
2. Real WordPress Playground activation/deactivation/reactivation on the latest WordPress runtime with PHP 8.3.
3. Real WordPress Playground activation/deactivation/reactivation on WordPress 6.8 with PHP 8.1.

## Verification conclusion

No additional known code-level blocker remains within this direct File 21 workflow-adapter scope at the reviewed runtime head.

This conclusion does **not** authorize merge, production packaging, or live deployment. PR #20 must remain Draft and unmerged until File 21 PR #19 and File 22's stacked phases pass their merge order, Files 00/20/21/22 are installed together on staging, the full role and ownership matrix passes, browser/accessibility/RTL/mobile acceptance passes, and the Founder gives explicit authorization.
