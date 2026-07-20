# Phase 3A — Shared Permissions, Repository Boundaries, Rate Limits, and Schema Audit

Target Phase 3 release: `1.1.0`

Integration branch: `build/phase-3-social-interactions-1.1.0`

Status: implementation complete on the integration branch; draft PR review remains open. No Phase 3 runtime feature is enabled.

## Implemented

### Shared authorization

`InteractionPermissions` centralizes:

- current-session authentication;
- fail-closed WordPress REST nonce validation;
- reuse of the accepted Phase 2 post visibility service;
- approved/published post interaction eligibility;
- private-resource ownership checks;
- report-management capability checks;
- Safe Mode and Emergency Disable write closure.

An explicit user ID must match the current WordPress session user. Request data cannot select another existing account.

### Rate limiting

`InteractionRateLimiter` provides privacy-safe, transient-backed buckets by:

- authenticated user;
- allow-listed action;
- object ID;
- bounded limit;
- bounded time window.

Unknown actions, missing users, unavailable storage, and failed writes are denied. There is no automatic administrator bypass.

Frozen defaults:

- reactions: 60 actions per 5 minutes;
- saves: 60 per 5 minutes;
- follows: 30 per 10 minutes;
- comments/replies: 10 per 10 minutes;
- reports: 5 per hour;
- poll votes/changes: 20 per 10 minutes.

### Repository boundary

`InteractionRepository` restricts database writes to:

- plugin-owned social tables;
- allow-listed columns;
- allow-listed statuses;
- strict non-negative integer values;
- validated 64-character hashes;
- valid UTC timestamps and dates;
- bounded natural or primary-key update identities.

Unknown tables, unknown columns, malformed identifiers, status-only mass updates, empty update conditions, and invalid states fail closed. The audit log is append-only.

The repository does not make permission decisions. Feature services must authorize first through `InteractionPermissions`.

### Schema audit

`Phase3SchemaAudit` compares the existing accepted Phase 2 schema with the frozen Phase 3 requirements.

Result:

- the existing seven plugin-owned tables contain the required Phase 3A columns and unique-index contracts;
- no schema version bump is required at Checkpoint 3A;
- missing runtime tables are reported as repair/install state, not silently treated as a migration;
- no automatic destructive cleanup is performed;
- WordPress posts, comments, users, media, URLs, and companion data remain outside rollback deletion scope.

## Tests

`tests/run-phase3-infrastructure-tests.php` covers:

- logged-out write rejection;
- missing and invalid nonce rejection;
- visible approved post authorization;
- pending-post rejection;
- forged existing-user ID rejection;
- private-resource IDOR rejection;
- report-management capability checks;
- Emergency Disable behavior;
- action/object-isolated rate limits;
- unknown and anonymous limiter rejection;
- schema contract versus runtime repair state;
- non-destructive schema installation verification;
- unknown table/column/status rejection;
- negative identifier rejection;
- bounded repository updates;
- status-only mass-update rejection;
- append-only audit log enforcement;
- invalid privacy-safe hash rejection;
- absence of public Phase 3 REST routes;
- preservation of Phase 2 plugin and schema version `1.0.0`.

GitHub Actions runs:

1. PHP syntax lint;
2. complete Phase 2 behavior regression suite;
3. Phase 3 contract suite;
4. Phase 3A infrastructure suite;
5. JavaScript syntax validation;
6. static, security, JSON, CSS, and whitespace checks;
7. existing Phase 2 artifact build;
8. ZIP structure verification;
9. SHA-256 verification.

A concise Phase 3A test log is uploaded on every CI attempt for evidence-based debugging.

## Deliberately not enabled

Checkpoint 3A does not:

- register reaction, comment, save, follow, report, or poll routes;
- render Like, Dislike, Comment, Save, Follow, Report, or Poll controls;
- mutate the live Phase 2 settings option;
- activate followers-only visibility;
- create social engagement records;
- add notification runtime;
- alter Unified Shell, Profiles, or Network code;
- start Phase 4 Editorial News;
- merge to `main`.

## Next permitted checkpoint

Checkpoint 3B — Reactions and Saves:

- authenticated Like/Dislike toggle;
- private Save/Unsave;
- engagement summary;
- feed and single-post action bar;
- private Saved Posts list;
- cache invalidation;
- behavior, race, duplicate, privacy, and two-user staging tests.

Checkpoint 3B must consume the shared 3A permissions, repository, rate-limit, schema, and structured-result contracts rather than duplicate them.
