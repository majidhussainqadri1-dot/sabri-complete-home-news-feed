# Phase 4 Rollback Runbook — Editorial News and Global Newsroom

Target development line: `1.2.0`

Environment: Hostinger native staging first; production use requires a separately approved production change plan.

Dependencies:

- `PHASE-4-CONTRACTS.md`
- `PHASE-4-CONTRACTS-ADDENDUM-1.md`
- `PHASE-4-ARCHITECTURE.md`
- `PHASE-4-SECURITY-PRIVACY.md`
- `PHASE-4-HOSTINGER-STAGING-ACCEPTANCE-CHECKLIST.md`

## 1. Purpose

This runbook restores the site to a known-good state after a Phase 4 installation, migration, configuration, privacy, security, cache, scheduling, or publication failure.

Rollback is a controlled recovery action. It is not ordinary troubleshooting, and it must not be used to conceal a correction, retraction, privacy incident, or audit record.

## 2. Immediate rollback triggers

Stop testing and begin containment when any of the following occurs:

- fatal PHP error, white screen, or inaccessible WordPress dashboard;
- database error, failed additive migration, missing required table/index, or unexplained schema change;
- deletion, truncation, renaming, or destructive modification of unrelated data;
- public exposure of drafts, submissions, preview content, private sources, reviewer notes, nonces, tokens, or patient identifiers;
- IDOR or capability bypass;
- stale cache exposing retracted, private, expired Breaking, or unauthorized content;
- scheduled publication that bypasses prerequisites;
- uncontrolled duplicate publication, correction, source, or review records;
- Emergency Disable failure;
- severe performance degradation that makes dashboard or public site unusable;
- inability to identify or restore the verified backup;
- any critical security/privacy defect.

## 3. Pre-rollback evidence

Before changing files or data, record where safely possible:

- staging URL;
- operator name and WordPress user ID;
- rollback start UTC;
- exact current 40-character commit SHA;
- installed artifact filename and SHA-256;
- plugin and schema versions;
- active Phase 4 feature gates;
- Emergency Disable state;
- backup identifier and creation UTC;
- visible symptoms and affected routes;
- sanitized screenshots/log references;
- whether public/private data exposure is suspected.

Do not copy patient data, preview tokens, passwords, nonces, private source identities, or full confidential notes into GitHub issues or public evidence.

## 4. Containment sequence

Use the least destructive effective action first.

1. Confirm the active browser tab is staging.
2. Activate Emergency Disable when the dashboard remains accessible.
3. Disable all Phase 4 feature gates without deleting data.
4. Purge only the relevant public/object cache when a cache leak or stale public state is suspected.
5. Revoke affected preview tokens.
6. Pause or unschedule affected Phase 4 publication jobs where safe.
7. Preserve sanitized evidence.
8. Select the correct rollback scenario below.

If the dashboard is inaccessible, use Hostinger file management or the approved server recovery path to rename/disable the plugin only on staging, then continue with file restoration.

## 5. Rollback levels

### Level 1 — Feature-gate rollback

Use when code loads safely and data is intact but one Phase 4 surface is defective.

Actions:

- close the affected Phase 4 gate;
- verify Emergency Disable override;
- purge affected derived caches;
- confirm ordinary Home Feed and Phase 3 interactions remain operational;
- leave underlying editorial data intact;
- record the defect and do not re-enable until corrected and retested.

Level 1 is preferred for a localized Breaking, RSS, schema, submission, correction, notification, or scheduling defect that does not require file/database restoration.

### Level 2 — Plugin-file rollback

Use when Phase 4 code causes fatal errors, routing failure, incompatible behavior, or serious regression but the database remains trustworthy.

Actions:

1. Keep Phase 4 gates closed.
2. Restore the exact prior known-good plugin package or plugin directory backup.
3. Do not mix individual files from different commits.
4. Clear PHP opcode cache where the hosting environment requires it.
5. Flush rewrite rules once through the approved activation/repair path, not on every request.
6. Purge plugin/page/object caches relevant to the restored package.
7. Verify dashboard and public site.
8. Run the post-rollback verification matrix.

Phase 4 additive tables and data may remain dormant if the prior package ignores them safely. They must not be deleted merely to complete Level 2.

### Level 3 — Database rollback

Use when migration, settings, workflow, publication, correction, retraction, or data integrity is corrupted and file rollback alone is insufficient.

Actions:

1. Keep public Phase 4 writes closed.
2. Record the current database state and incident scope.
3. Restore the verified pre-test database backup through Hostinger's approved restoration method.
4. Restore matching plugin files if the database backup predates the tested package.
5. Do not restore only selected tables unless a separately reviewed recovery plan proves referential integrity.
6. Verify WordPress users, options, posts, media references, comments, Phase 3 tables, and Phase 4 tables.
7. Purge relevant caches and run the complete post-rollback matrix.

Database restoration can remove legitimate changes made after the backup. Therefore this level is staging-only during acceptance testing and requires explicit production authorization outside this runbook.

### Level 4 — Privacy/security incident recovery

Use when unauthorized disclosure, privilege escalation, token leakage, patient-identifying content, or malicious publication is suspected.

Actions:

- activate Emergency Disable and close relevant routes;
- revoke preview tokens and affected sessions/credentials through approved mechanisms;
- remove exposed content from public projection without destroying evidence;
- invalidate CDN/page/object/RSS/schema/sitemap/feed caches;
- create correction or retraction notices where public accountability requires them;
- preserve restricted evidence;
- restore known-good files/data where necessary;
- perform root-cause analysis and targeted regression tests;
- do not re-enable until security acceptance is recorded.

## 6. Scenario-specific recovery

### 6.1 Fatal activation or dashboard failure

- Disable the plugin directory on staging through Hostinger file management.
- Confirm WordPress dashboard access.
- Restore the prior known-good package.
- Reactivate only after PHP syntax and package integrity are confirmed.
- Verify no partial migration or unrelated schema change occurred.

### 6.2 Rewrite or routing failure

- Close Editorial News gate.
- Restore known-good files if code regression is confirmed.
- Run the approved one-time rewrite refresh.
- Verify ordinary post routes, `/news/`, News single routes, feeds, and admin routes.
- Ensure live site was not touched.

### 6.3 Draft/private cache leak

- Activate Emergency Disable.
- Purge page, CDN, object, feed, sitemap, schema, and related-content caches.
- Revoke preview tokens.
- Close public News and preview surfaces.
- Preserve evidence and identify cache-key/serializer defect.
- Verify that private objects now return safe responses from logged-out and unrelated accounts.

### 6.4 Incorrect scheduled publication

- Close scheduled News gate.
- Unpublish or retract according to editorial accountability; do not silently erase a materially public article.
- Cancel duplicate cron events.
- Restore article state/revision through the approved workflow or database rollback if corrupted.
- Purge all public distribution caches.

### 6.5 Breaking News does not expire

- Close Breaking News gate.
- Remove stale presentation through read-time state correction and cache purge.
- Verify article remains published as ordinary News if otherwise valid.
- Repair cron only through an allow-listed confirmed action.

### 6.6 Correction or retraction inconsistency

- Close correction/public distribution gates as necessary.
- Preserve the correction/retraction ledger.
- Restore a consistent public projection from the authoritative revision and ledger.
- Purge single, archive, Home Feed, RSS, sitemap, schema, social-preview, and related-content caches.
- Never delete the accountability record merely to make surfaces agree.

### 6.7 Source/review ledger corruption

- Stop publication and review writes.
- Do not attempt ad hoc SQL repair from the browser.
- Export a sanitized schema/diagnostic report.
- Restore verified database backup or execute a separately reviewed repository-level repair.
- Verify article/source/review/correction ownership relationships.

## 7. Data preservation rules

Rollback must preserve unless the verified database restoration necessarily returns to an earlier snapshot:

- WordPress users and roles;
- ordinary posts and media;
- Phase 2 Feed/Composer content;
- Phase 3 reactions, saves, comments, follows, reports, polls, notifications, and views;
- Phase 4 articles, submissions, sources, reviews, corrections, and audit records where compatible;
- correction and retraction accountability.

Deactivation or file rollback must not run uninstall cleanup.

No rollback action may silently:

- drop or truncate Phase 4 tables;
- delete Editorial News to hide a defect;
- erase correction/retraction history;
- remove unrelated WordPress data;
- reopen feature gates automatically.

## 8. Post-rollback verification matrix

### 8.1 Core accessibility

- [ ] WordPress dashboard opens.
- [ ] Public staging homepage opens.
- [ ] No fatal error or repeated redirect occurs.
- [ ] Site Health and error logs show no new critical failure.

### 8.2 Phase 2

- [ ] Home Feed renders permitted posts.
- [ ] Filters and Load More work without duplicate/skip.
- [ ] Read More opens the correct single post.
- [ ] Composer role policy and privacy validation work.

### 8.3 Phase 3

- [ ] Reactions and private Saves preserve account isolation.
- [ ] Comments/replies/moderation work under configured gates.
- [ ] Follow/Following remains isolated.
- [ ] Reports/Polls/Notifications/Views remain within policy.
- [ ] Emergency Disable remains accessible.

### 8.4 Phase 4

- [ ] Phase 4 gates are closed or match the approved rollback target.
- [ ] No draft, submission, preview, source note, or patient identifier is public.
- [ ] Public News state matches the restored package/data contract.
- [ ] Breaking, scheduling, corrections, retractions, RSS, sitemap, and schema are either safely disabled or consistent.
- [ ] Existing Phase 4 data is present or its expected backup-restored state is documented.

### 8.5 Data integrity

- [ ] No unrelated table/user/post/media deletion.
- [ ] Required indexes and tables match the restored schema.
- [ ] Audit record identifies rollback without secrets.
- [ ] Cache purge and token revocation are complete where required.

## 9. Rollback evidence record

Record:

- rollback level/scenario;
- trigger;
- operator;
- start/end UTC;
- pre-rollback commit/package/schema;
- restored commit/package/schema;
- backup identifier;
- feature-gate states before/after;
- database restoration: yes/no;
- cache/token actions;
- verification checklist result;
- open defects;
- residual risks;
- evidence location;
- authorization to re-enable: yes/no and by whom.

## 10. Re-enable gate

Phase 4 may be re-enabled only after:

- root cause is identified;
- corrective code is reviewed;
- automated regression tests pass on the exact commit;
- the failed staging checklist section is repeated;
- privacy/security review passes where relevant;
- backup remains verified;
- administrator records explicit re-enable approval.

A successful rollback does not approve the defective release for merge or live deployment.

## 11. Prohibited actions

During rollback:

- do not change the live site while testing staging recovery;
- do not merge a corrective branch solely because the site appears restored;
- do not manually edit production database rows without a separately reviewed plan;
- do not upload backups to public web paths;
- do not paste sensitive logs or patient/source information into public tickets;
- do not delete evidence, correction history, or audit data to conceal the incident;
- do not re-enable all gates together.

## 12. Completion criteria

Rollback is complete only when:

- dashboard and public staging are accessible;
- Phase 2 and Phase 3 regression checks pass;
- Phase 4 public/private boundaries are safe;
- data integrity is verified;
- evidence is recorded;
- open defects and residual risks are documented;
- administrator accepts the restored state.

Rollback completion is not live deployment authorization.
