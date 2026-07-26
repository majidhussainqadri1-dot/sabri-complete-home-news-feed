# Phase 5 Final Acceptance Contract

Phase 5 is accepted only on one exact commit after all implementation gaps close.

## Required acceptance layers

1. full PHP/JavaScript/CSS/YAML/JSON lint and static checks;
2. all Phase 5 service, policy, repository, migration, admin, public, REST, security, privacy, accessibility, browser, performance, concurrency, install/upgrade/rollback tests;
3. all accepted Phase 2, Phase 3, Phase 4A, Phase 4B, and Phase 4C regressions;
4. source WordPress latest/PHP 8.3 and WordPress 6.8/PHP 8.1 matrices;
5. immutable package build, SHA-256, required/forbidden structure, version/schema/checkpoint/gate assertions;
6. packaged WordPress matrices on both environments;
7. browser journeys in Chromium and Firefox, plus supported WebKit-compatible checks;
8. migration fresh-install, accepted-1.0.0-upgrade, repeat, interruption/resume, deactivation/reactivation, rollback, and data-preservation evidence;
9. security/privacy and performance artifacts;
10. complete plan-to-code-to-test traceability audit showing no missing requirement;
11. uninterrupted final QA of at least 7,200 seconds and exactly 24 full cycles;
12. every final-QA cycle runs behavior, security, privacy, migration consistency, regressions, static checks, and fresh immutable package verification;
13. identical initial/final tracked-file manifests and verified final package/schema/migration digests;
14. final packaged matrices after the two-hour run;
15. zero open Critical/High or unclassified defects;
16. staging, release, rollback, merge, and deployment actions remain separately gated as defined by the final plan.

## Required artifacts

```text
sabri-phase5-FINAL-COMPLETION-QA-PASSED-{sha}
sabri-phase5-TWO-HOUR-VISIBLE-QA-PASSED-{sha}
sabri-phase5-MIGRATION-QA-PASSED-{sha}
sabri-phase5-SECURITY-PRIVACY-QA-PASSED-{sha}
sabri-phase5-PERFORMANCE-QA-PASSED-{sha}
```

Staging, rollback, release-candidate, and post-merge artifacts are created only after their separately authorized operational gates.

## Restart rule

Any implementation, test, workflow, package, migration, or documentation correction after final QA begins creates a new exact head. The entire two-hour QA restarts from zero. Elapsed time and evidence are never combined across attempts.
