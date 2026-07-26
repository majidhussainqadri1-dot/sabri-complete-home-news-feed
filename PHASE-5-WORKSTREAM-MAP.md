# Phase 5 Workstream Implementation Map

This document converts the final plan into implementation ownership boundaries. These are workstreams inside one final Phase 5, not additional phases.

## A — Contracts and traceability

Files: final plan, traceability matrix, implementation status, defect register, error/gate/role/route manifests.

## B — Schema, repositories, and migrations

Files: schema coordinator, migration runner, source/review/correction/submission repositories, schema manifest, install/upgrade/rollback tests.

## C — Sources and review ledgers

Files: source service/admin/REST/public projection; editorial/fact-check/medical/translation review service/admin/REST; publication-prerequisite integration.

## D — Submission portal

Files: submission policy/service/repository/REST/admin/public form/file validator/privacy adapter/notifications/tests.

## E — Breaking, corrections, and retractions

Files: breaking service/admin/public strip/cron; correction/retraction repositories/services/admin/public history/cache/feed/schema integration/tests.

## F — SEO, feeds, sitemap, social, and languages

Files: canonical/robots/schema/Open Graph/RSS/sitemap/translation relationship services, public adapters, validation tests.

## G — Security, privacy, and abuse hardening

Files: preview tokens, upload security, SSRF guard, rate limiter, privacy scanner, consent/retention/export/erase adapters, negative tests.

## H — Performance and observability

Files: index audit, cache/query profiling, diagnostics, cron health, alerting, audit-integrity verification, load/concurrency tests.

## I — UX and accessibility

Files: complete Newsroom screens, public/source/history/submission/breaking templates, CSS/JS, browser/no-JS/accessibility tests.

## J — Package, migration, staging, release, and rollback

Files: exact-head workflows, immutable package/manifest, two-hour visible QA, staging checklist, release readiness, version-promotion gate, rollback runbook, post-merge validation.

## Completion rule

A workstream remains incomplete until its assigned requirements have code, tests, security/privacy evidence, packaged WordPress evidence, and traceability rows. No workstream may defer missing work to a later phase.
