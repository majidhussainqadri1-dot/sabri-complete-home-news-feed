# Phase 4A Implementation — Editorial News Content Model

Target development line: `1.2.0`

Development branch: `build/phase-4-editorial-news-1.2.0`

Pull request: Draft PR #3

Status: **Phase 4A implementation committed; automated CI and Hostinger staging acceptance remain required**

## 1. Implemented scope

Phase 4A adds the executable foundation for Editorial News while preserving the accepted plugin and schema versions at `1.0.0`.

Implemented components:

- executable Phase 4 contract registry;
- eight isolated, fail-closed feature gates;
- separate `sabri_news` content type;
- five frozen Editorial News taxonomies;
- eighteen initial section terms;
- ten initial article-type terms;
- private Editorial News metadata definitions;
- canonical editorial workflow-state model;
- WordPress-compatible workflow storage mapping;
- seventeen Editorial News capabilities;
- additive capability assignment to existing roles only;
- Emergency Disable enforcement for Phase 4 writes;
- Phase 4-aware activation snapshot and rollback restoration;
- Phase 4A automated behavior and regression tests;
- dedicated Phase 4A CI workflow.

## 2. Feature-gate behavior

All Phase 4 feature gates remain disabled by default:

```text
editorial_news_enabled
news_submissions_enabled
breaking_news_enabled
scheduled_news_enabled
news_corrections_enabled
news_rss_enabled
news_schema_enabled
news_notifications_enabled
```

Unknown keys are rejected. Missing checkbox values are stored as disabled. Phase 4 settings use their own option and do not modify Phase 2 or Phase 3 settings.

Emergency Disable overrides every Phase 4 write capability and public Phase 4 gate while preserving read and administrator recovery access.

## 3. Content-type exposure

The `sabri_news` content type is registered separately from ordinary Home Feed posts.

While the master Editorial News gate is disabled:

- public queryability is disabled;
- public search inclusion is disabled;
- rewrite rules are disabled;
- the public archive is disabled;
- uncontrolled native WordPress REST exposure is disabled;
- no new public News card or page is produced.

When the gate is enabled in an approved staging checkpoint, the content model may use the canonical `/news/` rewrite and archive. Public templates, custom REST projections, query guards, and feed integration still belong to later checkpoints and are not implied by this registration.

## 4. Workflow-state compatibility correction

Phase 4A discovered that `ready-for-publication` exceeds the practical WordPress `post_status` storage limit.

The complete editorial state is therefore stored in:

```text
_sabri_news_workflow_state
```

The full state remains the source of truth. Compatible WordPress core statuses provide only the broad storage lifecycle:

- draft states use `draft`;
- review states use `pending`;
- scheduled uses `future`;
- public states use `publish`;
- retracted and archived states use `private`.

The normative correction is recorded in `PHASE-4-CONTRACTS-ADDENDUM-2-WORDPRESS-STATUS-STORAGE.md`.

## 5. Capability model

Phase 4A does not destructively replace WordPress roles and does not create broad roles automatically. It assigns frozen capabilities only to matching existing role slugs.

Default policy preserves these boundaries:

- Administrator and Founder retain explicit institutional authority.
- Editor-in-Chief may publish after prerequisites are met.
- Managing Editor and ordinary Editor do not receive publication authority by default.
- Section Editor remains subject to section/object policy in later services.
- Medical Reviewer receives medical-review authority without site-settings authority.
- Reporter cannot self-publish.
- Verified Doctor is a submitter and does not receive unrestricted source-management authority.
- Translator cannot self-publish or alter the source-language article through publication authority.
- Student, Patient, and Subscriber receive read authority only.

UI visibility is not authorization. Later workflow services must enforce object, section, assignment, and state restrictions server-side.

## 6. Snapshot and rollback correction

An integration review found that the pre-existing activation snapshot captured only Phase 2 and Phase 3 capabilities. That would have left Phase 4 capability mutations outside rollback.

Phase 4A corrects this by recording:

- the Phase 4 feature option before mutation;
- all Phase 4 capability values for candidate existing roles;
- Phase 4 taxonomy identifiers, section slugs, and article-type slugs;
- existing plugin and schema versions.

Rollback now:

- restores the previous Phase 4 gate state;
- restores or removes Phase 4 capabilities according to the snapshot;
- preserves Editorial News content and metadata;
- schedules a rewrite refresh;
- does not delete posts, users, media, comments, or companion-module data.

## 7. Automated coverage

`tests/run-phase4a-content-model-tests.php` verifies:

- accepted plugin and schema versions remain unchanged;
- all frozen identifiers and counts;
- all gates default disabled;
- unknown and omitted feature values fail closed;
- content type is private when disabled;
- canonical rewrite appears only when explicitly enabled;
- native REST exposure remains closed;
- exact taxonomy and term registration;
- idempotent default-term creation;
- safe workflow-state storage and mapping;
- metadata source of truth;
- role-to-capability boundaries;
- self-publication prevention;
- Emergency Disable behavior;
- activation snapshot completeness;
- Phase 4 setting and capability rollback;
- preservation of the twenty Hostinger acceptance keys.

The dedicated Phase 4A CI workflow also runs:

- PHP syntax checks;
- planning contract tests;
- document audit;
- complete Phase 2 and Safe Boot regression;
- complete Phase 3 regression matrix;
- static security and whitespace checks;
- unchanged-version package build;
- package verification for all Phase 4A runtime files.

## 8. Files added

```text
includes/class-phase4-contracts.php
includes/class-news-feature-settings.php
includes/class-news-statuses.php
includes/class-news-capabilities.php
includes/class-editorial-news-post-type.php
includes/class-news-taxonomies.php
tests/run-phase4a-content-model-tests.php
.github/workflows/phase4a-content-model-tests.yml
PHASE-4-CONTRACTS-ADDENDUM-2-WORDPRESS-STATUS-STORAGE.md
PHASE-4A-IMPLEMENTATION.md
```

Existing integration files updated:

```text
includes/class-plugin.php
includes/class-activator.php
includes/class-snapshot.php
includes/class-rollback.php
```

## 9. Explicitly not implemented

Phase 4A does not implement:

- Newsroom administration screens;
- article composer UI;
- workflow transition services;
- source, review, or correction database tables;
- public News archive or single templates;
- Home Feed News cards;
- custom Phase 4 REST controllers;
- submissions;
- Breaking News runtime;
- scheduling runtime;
- correction/retraction services;
- schema, RSS, sitemap, analytics, or notifications;
- version promotion;
- live deployment.

## 10. Acceptance boundary

Phase 4A is accepted for progression only after:

- the exact branch-head CI passes;
- no Phase 2 or Phase 3 regression is present;
- package structure contains the new runtime classes;
- failures are corrected and retested;
- Draft PR review records the checkpoint status.

This checkpoint does not make the plugin release-ready, merge-approved, or live-ready. Hostinger staging acceptance and rollback restoration remain later requirements.
