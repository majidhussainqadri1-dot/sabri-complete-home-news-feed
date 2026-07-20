# Phase 4 Review Notes

Target development line: `1.2.0`

Branch: `build/phase-4-editorial-news-1.2.0`

## Review scope

The repeated review examines the Phase 4 planning system from multiple angles:

- editorial completeness;
- internal contract consistency;
- role and capability separation;
- workflow and state-machine safety;
- source and evidence accountability;
- medical and patient privacy;
- public/private serialization;
- routes, SEO, RSS, sitemap, and cache isolation;
- accessibility and performance requirements;
- additive migration, backup, rollback, and release gates;
- compatibility with existing Phase 2 and Phase 3 behavior.

## Correction rule

When a defect is discovered, the correction must:

1. address the root cause rather than silence the test;
2. preserve or strengthen the strictest applicable privacy and security rule;
3. update every affected normative document and test;
4. avoid broadening scope without explicit approval;
5. keep all new Phase 4 gates disabled by default;
6. remain isolated from `main` and the live site;
7. restart the complete repeated QA on the corrected commit.

## Evidence rule

A clean result is accepted only for the exact commit tested by the complete workflow. Earlier partial runs and runs on different commits do not combine into acceptance.

## Current limitation

This review validates plans, contracts, policies, and QA evidence. It does not validate Phase 4 feature code because that code has not yet been implemented.
