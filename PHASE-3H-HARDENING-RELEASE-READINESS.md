# Phase 3H — Full Hardening, Accessibility, Staging, and Release Readiness

Target Phase 3 release: `1.1.0`

Integration branch: `build/phase-3-social-interactions-1.1.0`

Current accepted plugin version: `1.0.0`

Current accepted schema version: `1.0.0`

Status: **code hardening and automated validation complete; Hostinger staging acceptance is still required.** The pull request remains Draft and must not be merged.

## 1. Scope completed in 3H

Checkpoint 3H audits the combined Phase 3 surface rather than introducing an unrelated product area. It covers:

- cross-feature security and regression validation;
- the previously frozen Followers-only visibility contract;
- accessibility contract checks across Composer and interaction interfaces;
- an explicit release-readiness gate;
- a manual Hostinger staging acceptance checklist;
- a non-destructive rollback runbook;
- preservation of the accepted `1.0.0` plugin and schema versions until staging acceptance.

No Phase 4 Editorial News work is included.

## 2. Followers-only visibility

The stored visibility value is `followers`.

### Feature gate

`followers_visibility_enabled` remains `0` by default.

When the gate is disabled:

- the Composer does not offer Followers visibility;
- a forged Followers visibility submission is rejected;
- an existing Followers-only post fails closed for ordinary visitors and members;
- the author and authorized moderators retain accountable access.

When the gate is enabled on staging:

- the Composer may store `followers`;
- an authenticated viewer must actively follow the post author;
- a removed or blocked relationship does not grant access;
- self-follow is not used as an authorization shortcut;
- the author and authorized moderators retain access.

### Enforcement boundaries

Followers-only access is not implemented as cosmetic template hiding. It is enforced through:

- metadata visibility checks used by direct posts and REST responses;
- Feed query candidate scopes;
- a prepared SQL `EXISTS` relationship guard against the plugin-owned follows table;
- current-session user binding;
- Safe Mode and Emergency Disable;
- per-user Feed cache keys and cache invalidation when the feature gate changes.

The query guard fails closed when required database interfaces or plugin-owned tables are unavailable.

## 3. Cross-feature security hardening

The final automated matrix verifies that:

- feature flags cannot be bypassed through forged request values;
- request data cannot choose another authenticated user identity;
- object visibility is checked before interaction data is returned or changed;
- private Saves, Following lists, Reports, Poll choices, notification payloads, and view identities remain private;
- duplicate and concurrent requests preserve natural-key uniqueness;
- Safe Mode and Emergency Disable override public feature flags;
- Reports do not remove content automatically;
- notification delivery failure does not roll back the originating social action;
- public view counts expose aggregates only;
- the accepted Phase 2 Feed, Composer, single-post route, and Read More behavior remain unchanged.

## 4. Accessibility hardening

Automated structural checks cover the following shipped templates:

- action bar;
- comments and replies;
- Polls;
- confidential Report control;
- Composer.

The checked contracts include:

- semantic labels and legends;
- keyboard-operable native controls;
- `aria-pressed` for toggle state;
- `aria-live="polite"` status regions;
- screen-reader-only contextual text;
- explicit field length limits;
- responsive and reduced-motion CSS already covered by earlier checkpoint tests.

Automated structural checks do not replace real browser, keyboard, screen-reader, zoom, contrast, and mobile testing. Those tests are mandatory in Hostinger staging.

## 5. Release-readiness gate

`ReleaseReadiness` provides a read-only report and never merges, deploys, promotes a version, or creates an acceptance record.

Release readiness requires all of the following:

1. verified code and schema audit;
2. the exact frozen staging checklist hash;
3. every required staging checklist item completed;
4. explicit acceptance by an identified WordPress administrator;
5. an exact tested 40-character Git commit SHA;
6. a valid UTC acceptance timestamp;
7. verified backup;
8. verified rollback restoration;
9. plugin version promoted to the Phase 3 target only after acceptance;
10. Safe Mode and Emergency Disable inactive at the final release checkpoint.

Automated tests alone therefore cannot make `release_ready = true`.

## 6. Version and schema decision

Checkpoint 3H does not require a destructive schema migration.

The existing seven plugin-owned social tables and indexes remain sufficient for:

- reactions;
- follows and Followers-only authorization;
- saves;
- reports;
- views;
- Poll votes;
- audit log.

Until Hostinger staging acceptance:

- plugin version remains `1.0.0`;
- schema version remains `1.0.0`;
- target release remains `1.1.0`;
- no production Phase 3 release ZIP is created;
- the existing CI artifact continues to be identified as a Phase 2-safe artifact.

## 7. Automated validation

The branch-head workflow validates:

- PHP syntax;
- complete Phase 2 behavior regression;
- Phase 3 contract freeze;
- 3A infrastructure;
- 3B reactions, saves, race recovery, and Saved Posts;
- 3C comments and replies;
- 3D Follow and Following;
- 3E Reports and moderation;
- 3F Polls;
- 3G Notification Bridge and Views;
- 3H Followers-only visibility, release gate, emergency override, and accessibility contracts;
- JavaScript syntax;
- CSS, JSON, static security, and whitespace;
- package structure;
- SHA-256 integrity;
- diagnostic test-log artifact upload.

## 8. Staging status

No claim is made that Hostinger staging installation, browser testing, accessibility testing, integration testing, database backup restoration, or rollback restoration has already occurred.

Those actions require access to the actual Hostinger staging WordPress environment and must be performed using `HOSTINGER-STAGING-ACCEPTANCE-CHECKLIST.md`.

## 9. Deliberately blocked

Until staging acceptance is complete:

- do not merge Draft PR #2;
- do not mark the PR ready for review;
- do not upload a Phase 3 ZIP to the live site;
- do not activate all Phase 3 feature flags together;
- do not change the plugin version to `1.1.0`;
- do not create the staging acceptance option manually without completing the checklist;
- do not delete existing plugin tables or content;
- do not begin Phase 4 Editorial News in this repository.

## 10. Next operational step

The next step is **manual Hostinger staging acceptance**, not further ungated feature coding.

Use:

- `HOSTINGER-STAGING-ACCEPTANCE-CHECKLIST.md`
- `PHASE-3-ROLLBACK-RUNBOOK.md`

After every required staging item and rollback test passes, the exact tested commit SHA and checklist hash may be recorded. Only then may a separate release-promotion commit update the plugin version to `1.1.0`, create the final release artifact, run final CI, and prepare the Draft PR for review. Merge and live deployment still require an explicit separate decision.
