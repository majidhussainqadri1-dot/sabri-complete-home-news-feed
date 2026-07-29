# File 21 → File 22 Workflow Second Post-Correction Verification — 2026-07-29

## Purpose

This record verifies the corrected runtime after the second independent review, correction of every recorded finding, and an additional post-correction review of concurrency, retention, recovery ordering, integration evidence, and branch state.

It does not authorize merge, staging promotion, production packaging, or live deployment.

## Exact verified runtime head

`e96e79952268c7b7b4dd09698400775f1dfd1184`

## Verified runtime conclusions

1. Only an exact native WordPress `draft` is mutable through create, resume, preview, or first final transition.
2. Pending-review, scheduled, published, rejected, missing, foreign, and non-post references are not draft-mutation targets.
3. A final idempotent replay may reconcile an already-final manageable reference without demoting or mutating it.
4. Reference-less final submission fails before the native Composer runs; File 22 must first obtain a native draft reference.
5. File 21 attaches one-way recovery markers and persists the opaque native reference before changing the native draft to a final state.
6. Preview links bind post ID, authenticated subject, and absolute expiry through an HMAC and are rejected at request time after expiry or on forgery, user mismatch, non-draft status, or permission failure.
7. Completion-persistence failure after the native mutation is recoverable on retry without a second Composer call.
8. Same-key/same-payload replay returns the existing native result; same-key/different-payload replay fails with `conflict`.
9. Active processing leases and active per-key execution locks prevent concurrent duplicate mutation.
10. Lock release is owner-token checked; expired or malformed crash-left locks are removed in bounded maintenance.
11. Expired completed and already-recoverable records are deleted rather than renewed indefinitely.
12. An expired processing draft receives at most one seven-day recoverable interval; a final processing record is reconciled to a bounded completed interval.
13. Administrator repair is capability- and nonce-protected and exposes aggregate counts only.
14. Founder Update and Platform News are absent from a non-Founder/non-Administrator schema and remain server-side restricted.
15. File 21 remains the sole native post, draft, metadata, moderation, and canonical-record owner.
16. Raw idempotency keys, post bodies, patient narratives, and complete payloads are not stored in reconciliation options or health output.
17. Structured Clinical Case, Research, Poll, upload, Video, and PDF workflows remain on their native owner routes.
18. The actual reviewed File 22 Phase 22E interfaces and Workflow Coordinator accept File 21 schema, draft, signed preview, draft-referenced submission, status, and canonical URL results.
19. PR #21 targets `main`, remains Draft and unmerged, and no live or staging deployment was performed.

## Exact automated evidence

All workflow families below completed successfully on the unchanged runtime head `e96e79952268c7b7b4dd09698400775f1dfd1184`:

| Workflow | Run | Run ID | Result |
|---|---:|---:|---|
| Build and Test Home News Feed | 1524 | 30434181237 | success |
| Test Packaged Home News Feed | 1014 | 30434181124 | success |
| File 21 Corrective Release Tests | 228 | 30434181245 | success |
| File 21 Comprehensive Harmonization Tests | 633 | 30434181260 | success |
| Phase 4A Content Model Tests | 579 | 30434181325 | success |
| Phase 4B Newsroom Tests | 673 | 30434181253 | success |
| Phase 4C Public News Tests | 709 | 30434181341 | success |
| File 21 File 22 Real Contract | 27 | 30434181328 | success |

The build family includes the complete File 21 behavioral and phase regression suite, PHP and JavaScript syntax checks, static security checks, canonical package/checksum/manifest validation, and WordPress Playground activation/deactivation/reactivation on the configured PHP and WordPress matrices.

The real-contract workflow pins File 22 to exact reviewed Phase 22E source:

`9aed674344c33b8756b65e7bc58c223ac6ffc4ae`

It loads File 22's actual Adapter interfaces and actual `Workflow_Coordinator`. WordPress runtime collaborators remain controlled test doubles; this evidence is therefore an actual cross-repository contract test, not a substitute for controlled multi-plugin WordPress staging.

## Historical failed-run evidence

Earlier corrective heads produced real failures that were retained and corrected rather than hidden:

- static release-test variable interpolation caused a PHP parse failure;
- the first recovery test incorrectly failed option persistence before native mutation instead of at completion;
- final replay was initially rejected because draft eligibility was checked before existing idempotency reconciliation;
- first-pass retention could renew expired completed/recoverable records;
- active processing and execution-lock scenarios required explicit regression coverage;
- reference-less final submission left a process-termination recovery gap.

Each defect was corrected before this exact-head verification.

## Independent post-correction assessment

No additional known code-level blocker remains within the corrected File 21 direct workflow scope at the exact verified runtime head.

This conclusion is limited. The following remain mandatory before merge or release:

- File 22 Phase 22E dependency and approved merge order;
- File 20 complete Create producer implementation;
- controlled Files 00, 20, 21, and 22 installation on staging;
- Founder, Administrator, verified doctor, permitted unverified doctor, patient, student, suspended, rejected, expired-document, and logged-out role matrix;
- cross-user references, private canonical URLs, Safe Mode, collision, fallback, and rollback tests;
- real browser, mobile, keyboard, 200%/400% zoom, screen-reader, Urdu RTL, reduced-motion, and forced-colors acceptance;
- backup and rollback proof;
- explicit Founder authorization.
