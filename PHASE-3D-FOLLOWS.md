# Phase 3D — Follow and Following

Target Phase 3 release: `1.1.0`

Integration branch: `build/phase-3-social-interactions-1.1.0`

Status: implementation and automated validation complete on the integration branch. The runtime remains disabled by default until Hostinger staging acceptance. The pull request remains Draft and must not be merged.

## Storage model

Checkpoint 3D reuses the accepted plugin-owned `sabri_feed_follows` table and schema version `1.0.0`.

Each relationship contains:

- authenticated `follower_user_id`;
- existing WordPress `target_user_id`;
- fixed `target_type = user`;
- `active`, `removed`, or reserved `blocked` status;
- created and updated UTC timestamps;
- one unique natural-key row per follower, target, and target type.

No schema migration or destructive cleanup is required.

## Follow policy

A Follow write requires:

- the current WordPress session;
- a valid `X-WP-Nonce`;
- an existing target account;
- a target different from the current account;
- the `follows_enabled` feature flag;
- Safe Mode and Emergency Disable clearance;
- the `sabri_feed_user_followable` policy filter;
- no blocked relationship state;
- a maximum of 30 attempts per user/target per 10 minutes.

Request data cannot select another existing follower identity. Self-follow, malformed IDs, missing users, invalid nonces, blocked relationships, and disabled features fail closed.

## Relationship behavior

- Follow creates one active relationship.
- Repeated Follow is idempotent.
- Unfollow changes the existing relationship to `removed` rather than deleting it.
- A removed relationship may be safely reactivated.
- A blocked relationship cannot be silently reactivated or overwritten.
- Concurrent unique inserts recover through a safe re-read and bounded update.
- Audit events record Follow and Unfollow actions without exposing private account fields.

## REST endpoints

Namespace: `sabri-home-news-feed/v1`

- `POST /users/{id}/follow`
- `DELETE /users/{id}/follow`
- `GET /me/following`

All three routes require the current session, a valid REST nonce, the enabled feature flag, strict IDs, bounded list sizes, and no-store private responses.

## Private Following list

The current user may access their private Following list through:

- `GET /me/following`;
- the server-rendered `[sabri_following]` shortcode.

The list contains only:

- target user ID;
- safe public display name;
- profile URL;
- avatar markup.

It does not serialize email addresses, phone numbers, roles, capabilities, login names, or another user’s private relationships. Missing/deleted target accounts are omitted.

## Profile integration boundary

This repository does not modify or depend directly on the Profiles repository.

`ProfileLinkResolver` provides:

- a WordPress author-archive fallback;
- the `sabri_feed_profile_url` filter for a companion Profiles module;
- invalid-value fallback rather than arbitrary URL output;
- safe public display-name handling.

The post action bar includes `View Profile` when Follow is enabled and a safe profile URL is available.

## Counts policy

Public follower counts are controlled separately by `show_public_follower_counts`.

- the default is disabled;
- when disabled, the real count is not returned in the public summary;
- when enabled, only the aggregate active follower count is shown;
- private Following counts remain current-user-only;
- follower identities are never publicly listed by this checkpoint.

## User interface

When the staging flag is enabled, feed cards and direct posts provide:

- Follow/Following button state;
- Unfollow by selecting an active Following button;
- visitor sign-in redirect;
- View Profile link;
- optional policy-approved follower count;
- `aria-pressed` state;
- 44-pixel minimum controls;
- keyboard focus indicators;
- live status messages;
- duplicate-click busy state;
- responsive mobile layout;
- delegated events for Load More posts.

A user’s own post does not render a self-follow button.

## Privacy and retention

The existing WordPress privacy exporter now includes the followed target user ID so an exported relationship is meaningful to its owner.

Account erasure handling:

- marks outgoing Follow rows removed;
- marks incoming rows targeting the erased account removed;
- does not delete WordPress users or content;
- preserves the plugin’s non-destructive retention model.

## Feature gate

`follows_enabled` and `show_public_follower_counts` remain `0` in safe defaults. Automated tests explicitly enable them in isolation.

Hostinger staging acceptance is required before enabling the Follow surface. This prevents an unreviewed relationship system or follower count from appearing automatically.

## Automated coverage

`tests/run-phase3d-follows-tests.php` covers:

- default feature gating;
- private-by-default follower counts;
- Follow creation;
- repeated Follow idempotency;
- two-user aggregate counts;
- current-user state isolation;
- self-follow rejection;
- missing-target rejection;
- forged-user IDOR rejection;
- followable-account policy filter;
- blocked-state preservation;
- concurrent unique-insert recovery;
- one-row natural-key persistence;
- private Following list isolation;
- profile URL bridge;
- no email or role serialization;
- Unfollow removed state;
- public count policy;
- rate limiting;
- REST route registration;
- strict IDs and list limits;
- nonce permissions;
- accessible action-bar state;
- self-post UI exclusion;
- shortcode registration and rendering;
- visitor login state without nonce leakage;
- fail-closed disabled runtime;
- preservation of plugin and schema version `1.0.0`.

The full GitHub Actions workflow also validates all Phase 2, 3.0, 3A, 3B, and 3C suites, JavaScript syntax, CSS/JSON/security/whitespace checks, artifact build, ZIP structure, and SHA-256 integrity.

## Deliberately deferred

Checkpoint 3D does not implement:

- a public follower directory;
- followers-only post visibility;
- user blocking UI;
- reports or moderation queue UI;
- notifications;
- polls;
- view analytics;
- Phase 4 Editorial News;
- production deployment;
- merge to `main`.

## Next permitted checkpoint

Checkpoint 3E — Reports and Moderation Queue:

- authenticated post/comment reporting;
- allow-listed report reasons;
- duplicate-report control;
- confidential report records;
- open, triaged, resolved, dismissed, and duplicate states;
- moderator-only queue and notes;
- no public reporter identity or report details;
- comprehensive privacy, IDOR, rate-limit, moderation, and two-user tests.
