# File 21 → File 22 First Post-Correction Verification — Historical Evidence — 2026-07-29

> **Superseded:** A later independent review found pending-state regression, unenforced preview expiry, incomplete idempotency recovery and retention, concurrency, integration-evidence, and branch-governance defects. The earlier no-blocker conclusion below is withdrawn. Current corrective evidence is recorded in `FILE22-WORKFLOW-SECOND-REVIEW-CORRECTIONS-2026-07-29.md` and Draft PR #21.

## Historical verification purpose

This record documents the first verification cycle only. It must not be cited as current release, merge, staging, or production evidence.

## Historical corrected runtime head

`fc6c2777c18b79b28ae380cf58210306f7c20335`

## Findings that were considered verified in that cycle

- File 21 implemented File 22 Workflow API `1.0.0` and Diagnostic Adapter API `1.0.0` on the existing `social_publication` adapter.
- File 21 remained the native post, draft, metadata, moderation, status, and canonical-record writer.
- Direct schema choices intersected the approved text-first allowlist and File 21 Composer settings.
- Structured Clinical Case, Research, Poll, upload, Video, and PDF workflows were not advertised by the direct adapter.
- Founder Update and Platform News submissions had a server-side Founder or Administrator restriction.
- Submission performed native validation before acquiring an idempotency record.
- Raw idempotency keys and normalized payload bodies were not stored in the option record.
- Status required native edit/moderation authority.
- Canonical URL required a published post and File 21 visibility.
- Registration diagnostics did not expose exception class names.
- `/create-post/` remained the fallback route.

## Historical automated evidence

- workflow: `Build and Test Home News Feed`;
- run number: `1510`;
- run ID: `30414458724`;
- exact head: `fc6c2777c18b79b28ae380cf58210306f7c20335`;
- conclusion: `success`.

The historical jobs covered the complete File 21 regression suite, JavaScript and static checks, package/checksum/manifest checks, and WordPress Playground activation on PHP 8.3 and PHP 8.1.

## Withdrawn conclusion

The former statement that no additional code-level blocker remained is withdrawn. The old text also incorrectly described PR #20 as Draft and unmerged after its state changed. Only the second-review correction line in Draft PR #21 may be used for current assessment, and it still requires fresh exact-head verification and staging acceptance.
