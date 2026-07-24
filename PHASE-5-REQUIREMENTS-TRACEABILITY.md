# Phase 5 Requirement Traceability Framework

Target branch: `build/phase-5-final-completion-1.2.0`

This document is the mandatory traceability index for the final Phase 5 completion. Every requirement in `PHASE-5-FINAL-COMPLETION-PLAN.md` must map to implementation files, tests, CI evidence, migration evidence, staging evidence where authorized, and release/rollback evidence. A requirement may not be marked complete through documentation alone.

## Status values

- `planned`
- `implemented`
- `tested`
- `accepted`
- `blocked`

## Requirement groups

| ID range | Domain |
|---|---|
| P5-001–P5-049 | contracts, invariants, gates, result/error shapes |
| P5-050–P5-099 | schema, repositories, migrations, install/upgrade/rollback |
| P5-100–P5-149 | source registry and evidence classification |
| P5-150–P5-199 | editorial, fact-check, medical, translation review ledgers |
| P5-200–P5-249 | doctor/contributor submissions and secure files |
| P5-250–P5-299 | breaking lifecycle and accessible public strip |
| P5-300–P5-349 | corrections, clarifications, updates, retractions |
| P5-350–P5-399 | Newsroom administration and REST writes |
| P5-400–P5-449 | public sources/history, SEO, schema, social, RSS, sitemap |
| P5-450–P5-499 | languages, translations, canonical and hreflang relationships |
| P5-500–P5-549 | security, preview, upload, SSRF, abuse, rate limiting |
| P5-550–P5-599 | privacy, consent, export, erase, retention, minimization |
| P5-600–P5-649 | performance, indexes, cache, large-dataset/concurrency testing |
| P5-650–P5-699 | observability, diagnostics, cron, alerts, audit integrity |
| P5-700–P5-749 | UX, accessibility, no-JavaScript and browser journeys |
| P5-750–P5-799 | packaging, exact-head CI, soak, staging, release, rollback, post-merge |
| P5-800–P5-849 | final documentation, defect register, operator runbooks |

## Mandatory mapping columns

The implementation completion document must contain one row per requirement with:

```text
Requirement ID
Requirement summary
Implementation file(s)
Service/policy/repository boundary
Unit/service test(s)
Security/privacy test(s)
WordPress/packaged/browser test(s)
Migration/staging evidence where applicable
Artifact/run identifier
Final status
```

## No-defer enforcement

A row cannot be classified as future work. A missing implementation or test remains a Phase 5 blocker. External platform-module non-scope requires explicit evidence that the requirement is outside this plugin. Medium/Low risk acceptance requires a recorded owner decision and cannot cover missing security, privacy, migration, accessibility, rollback, or core functionality.
