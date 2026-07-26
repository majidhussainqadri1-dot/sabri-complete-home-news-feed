# Phase 5 Final Implementation and Traceability

## Frozen boundaries

- Plugin version: `1.0.0`
- Schema version: `1.0.0`
- `Phase4Contracts::CHECKPOINT`: `4A`
- All Phase 4/5 gates disabled by default
- Automatic publication disabled

## Requirement-to-code-to-test map

| Requirement group | Implementation | Primary tests/evidence |
|---|---|---|
| Contracts, gates, capabilities | `class-phase5-contracts.php`, `class-phase5-feature-settings.php`, `class-phase5-capabilities.php` | final, security/privacy, traceability |
| Schema, repositories, migrations | `class-phase5-database.php`, `class-phase5-repository.php`, `class-phase5-migrations.php` | migration/lifecycle, WordPress source/package |
| Sources and evidence | `class-source-registry.php` | final, security/privacy, Playground |
| Review ledgers | `class-review-ledger.php`, `class-phase5-publication-policy.php` | final, migration, Playground |
| Submissions and files | `class-submission-service.php`, `class-upload-security.php`, submission portal | final, security/privacy, browser, Playground |
| Breaking News | `class-breaking-news-service.php`, public strip | final, UI, browser, Playground |
| Corrections/retractions | `class-correction-ledger.php`, sources/history template | final, security/privacy, Playground |
| Translation/hreflang | `class-translation-service.php`, `class-news-distribution.php` | final, distribution, Playground |
| SEO/schema/social/RSS/sitemap | `class-news-distribution.php` | final, security/privacy, browser, Playground |
| Authenticated REST | `class-phase5-rest.php` | security/privacy, Playground |
| Preview/upload/SSRF/rate limit | preview, upload, SSRF, rate-limiter classes | security/privacy |
| Privacy/consent/export/erase/retention | privacy scanner/operations, upload policy | security/privacy, migration |
| Cache/performance/index/load | `class-phase5-performance.php` | performance Playground, diagnostics |
| Observability/audit/cron/alerts | diagnostics and audit-integrity classes | final, migration, Playground |
| Newsroom/public UX/accessibility | Phase 5 admin/public runtimes, templates/assets | UI, browser/no-JS |
| Install/upgrade/deactivation/rollback/uninstall | activator, deactivator, migrations, uninstall, runbooks | migration, packaged WordPress |
| Packaging and exact-head QA | build script and permanent workflows | immutable artifacts, 24-cycle two-hour QA |

## Completion rule

The final exact head must pass all local and previous regressions, source and packaged WordPress matrices, browser/no-JavaScript journeys, 10,000-record load and concurrency tests, immutable package verification, machine traceability audit, and an uninterrupted minimum 7,200-second exactly 24-cycle QA. Any correction restarts the final QA from zero.
