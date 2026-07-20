# Phase 3 Rollback Runbook

Applies to: Sabri Complete Home and News Feed Phase 3 staging acceptance

Target release: `1.1.0`

Principle: **restore service safely, preserve content and evidence, and never improvise destructive cleanup.**

## 1. When to stop testing and roll back

Begin rollback immediately on Hostinger staging after any of these events:

- WordPress dashboard or public staging site becomes inaccessible;
- PHP fatal error, white screen, repeated database error, or activation loop;
- Feed or direct-post visibility leak;
- another user’s private Saves, Following list, Reports, Poll vote, or view history is exposed;
- Followers-only content appears to a non-follower or logged-out visitor;
- Emergency Disable does not close public Phase 3 writes;
- existing posts, media, settings, or plugin-owned records disappear unexpectedly;
- schema verification reports missing tables or required unique indexes;
- upgrade creates an irreversible data mutation;
- notification callback failure changes or reverses the originating social action;
- any test requires editing the live site to continue.

Do not continue testing merely to collect more failures after a privacy, authorization, or destructive-data defect is confirmed.

## 2. Immediate containment

1. Confirm again that the affected environment is staging.
2. Record:
   - current UTC time;
   - exact tested commit SHA;
   - active plugin version;
   - feature flags that were enabled;
   - last successful action;
   - first observed failure;
   - relevant screenshot or error text without patient or account secrets.
3. Activate Emergency Disable when WordPress admin remains accessible.
4. Disable the specific new feature gates involved in the failure.
5. Do not delete posts, comments, users, media, tables, or social rows.
6. Do not run manual SQL cleanup.
7. Do not merge the Draft PR or upload anything to live.

## 3. Choose the rollback level

Use the least destructive level that safely restores the accepted staging baseline.

### Level 1 — Feature-gate rollback

Use when plugin files and schema are healthy but one Phase 3 feature misbehaves.

- Set the affected feature flags to `0`.
- Prefer Emergency Disable first when authorization or privacy is uncertain.
- Clear plugin Feed/engagement transients through approved WordPress/cache controls.
- Re-test the accepted Phase 2 Feed, Composer, and direct-post route.
- Preserve all social rows for investigation.

Do not change stored Followers-only posts to Public as a shortcut. With the feature disabled, they must fail closed for ordinary users.

### Level 2 — Plugin file rollback

Use when new PHP, JavaScript, CSS, templates, or runtime registration causes the defect but the database is intact.

- Deactivate the tested staging plugin only when deactivation is safe and admin remains accessible.
- Restore the known-good accepted plugin package or file snapshot.
- Do not use WordPress “Delete plugin” because uninstall behavior and retained data must not be guessed.
- Reactivate the known-good plugin.
- Confirm plugin version and checksums match the accepted package.
- Verify the seven plugin-owned social tables still exist.
- Re-test Phase 2 Feed, Composer, Read More, posts, media, and admin access.

### Level 3 — Database backup restoration

Use when settings, schema state, indexes, or stored data were changed incorrectly and file rollback alone is insufficient.

- Confirm the selected backup predates the Phase 3 staging test.
- Record the backup identifier and timestamp.
- Restore the staging database through Hostinger’s supported restoration process.
- Restore plugin files to the matching known-good package.
- Do not restore a staging database over live.
- Verify WordPress site URL and environment-specific settings after restoration.
- Verify all required tables and indexes.
- Verify user accounts and permissions.
- Re-test the accepted Phase 2 baseline.

### Level 4 — Full staging clone replacement

Use when staging integrity is uncertain or multiple components cannot be reconciled confidently.

- Preserve failure evidence before replacement.
- Remove or archive only the damaged staging instance using Hostinger-supported controls.
- Create a fresh staging clone from the verified live/accepted baseline.
- Confirm that the live site was not altered.
- Repeat environment and backup checks before any further testing.

## 4. Data that must be preserved

Rollback must not intentionally delete:

- WordPress posts, drafts, revisions, and approved content;
- media and attachments;
- comments and reply structure;
- users, roles, and profiles;
- reactions;
- follows, blocked relationships, and removed relationship records;
- private Saves;
- confidential Reports and moderator accountability notes;
- Poll definitions and vote accountability rows;
- view aggregates and minimized identities;
- audit logs;
- settings evidence needed to diagnose the failure.

A cleanup proposal that deletes data requires a separate reviewed migration plan and is outside this runbook.

## 5. Cache and routing verification after rollback

After any rollback level:

- purge only supported WordPress/Hostinger/LiteSpeed caches;
- verify Home Feed as logged-out visitor;
- verify Home Feed as at least two different logged-in roles;
- verify Read More opens the selected single post;
- verify pending, private, member-only, doctor-only, and Followers-only content does not leak;
- verify REST responses follow the same visibility decisions;
- verify cached output changes after Follow/Unfollow and feature-gate changes;
- verify no stale nonce or user-specific HTML is served to another account.

## 6. Schema verification

Confirm all expected plugin-owned tables:

- `sabri_feed_reactions`
- `sabri_feed_follows`
- `sabri_feed_saves`
- `sabri_feed_reports`
- `sabri_feed_views`
- `sabri_feed_poll_votes`
- `sabri_feed_audit_log`

Confirm required unique indexes, including:

- reaction user/post identity;
- follower/target identity;
- save user/post/collection identity;
- report duplicate control;
- view identity;
- Poll vote identity.

Missing runtime tables or indexes require supported idempotent repair or backup restoration. They do not justify ad-hoc destructive SQL.

## 7. Functional verification after restoration

Minimum pass criteria:

- dashboard accessible;
- public staging site accessible;
- no PHP fatal errors;
- Phase 2 Home Feed works;
- Composer role policy works;
- Read More single-post isolation works;
- posts and media are present;
- privacy validation remains active;
- Emergency Disable is available;
- unaccepted Phase 3 feature flags are disabled;
- plugin/schema versions match the restored baseline;
- no unresolved authorization or privacy leak remains.

## 8. Rollback evidence record

Record:

- rollback trigger;
- affected staging URL;
- exact failed commit SHA;
- rollback level used;
- backup/package restored;
- rollback start UTC;
- rollback completion UTC;
- administrator performing rollback;
- tables/indexes verified;
- Phase 2 tests performed;
- screenshots/log locations;
- remaining defect and reproduction steps;
- confirmation that live remained unchanged.

## 9. Acceptance impact

After any rollback:

- the previous staging acceptance attempt is invalid;
- do not create or retain a valid `sabri_hnf_phase3_staging_acceptance` record for the failed commit;
- fix the defect only on the integration branch;
- run full CI again;
- create a fresh backup and repeat the complete staging checklist on the new exact commit SHA;
- do not reuse evidence from a different commit as final acceptance evidence.

## 10. Release boundary

Rollback success means the accepted staging baseline has been restored. It does **not** mean Phase 3 is accepted or ready to merge.

Release `1.1.0` remains blocked until a later exact commit passes:

- full automated CI;
- complete Hostinger staging checklist;
- backup verification;
- rollback verification;
- explicit administrator acceptance;
- final release-promotion review.
