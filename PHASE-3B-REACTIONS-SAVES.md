# Phase 3B — Reactions and Private Saves

Target Phase 3 release: `1.1.0`

Integration branch: `build/phase-3-social-interactions-1.1.0`

Status: Checkpoint 3B implementation and automated validation are complete on the integration branch. The pull request remains draft and must not be merged before the remaining Phase 3 checkpoints and Hostinger staging acceptance.

## Implemented runtime

### Reactions

- authenticated Like;
- authenticated Dislike;
- one active reaction per user and post;
- selecting the active reaction removes it;
- Like-to-Dislike and Dislike-to-Like switching;
- public Like and Dislike counts when enabled;
- private current-user reaction state;
- visibility and approved-state checks inherited from Phase 2;
- current-session-bound identity and REST nonce checks;
- per-user/per-post reaction rate limiting;
- engagement cache invalidation after mutation;
- audit events for set and remove actions;
- unique-row race recovery after a concurrent insert.

### Private saves

- authenticated Save;
- authenticated Unsave;
- idempotent repeated Save;
- private current-user saved state;
- current-user-only REST Saved Posts list;
- server-rendered `[sabri_saved_posts]` shortcode;
- visibility re-check before listing each saved post;
- visitor login state without leaking saved content or a REST nonce;
- per-user/per-post save rate limiting;
- privacy exporter and eraser behavior inherited from the existing Data Retention service;
- unique-row race recovery after a concurrent insert.

### Engagement summary

The summary contains only:

- post ID;
- Like count;
- Dislike count;
- combined reaction count;
- current authenticated user's reaction;
- current authenticated user's private saved state.

Public counts are cached with a bounded transient lifetime. Private user state is never stored in the shared count cache. Interaction mutation invalidates the post count cache.

## REST endpoints

Namespace: `sabri-home-news-feed/v1`

- `GET /posts/{id}/engagement`
- `POST /posts/{id}/reaction`
- `DELETE /posts/{id}/reaction`
- `POST /posts/{id}/save`
- `DELETE /posts/{id}/save`
- `GET /me/saves`

Write and private routes require:

- current WordPress session;
- valid `X-WP-Nonce`;
- current-session identity;
- object visibility;
- published and approved post state;
- feature flag;
- rate limit;
- allow-listed payload;
- no-store private response behavior.

## User interface

The action bar is rendered on:

- Home Feed post cards;
- direct single-post content.

It includes:

- Like;
- Dislike;
- Save/Saved;
- accessible `aria-pressed` state;
- 44-pixel minimum controls;
- keyboard focus indicators;
- live status messages;
- busy-state duplicate-click prevention;
- delegated events for posts appended by Load More;
- visitor redirect to sign-in;
- responsive mobile layout;
- reduced-motion support.

The frontend does not expose comments, follows, reports, polls, notifications, or follower visibility because those checkpoints are not implemented yet.

## Feature settings

Checkpoint 3.0 frozen all Phase 3 flags off. Checkpoint 3B introduces an isolated runtime option, `sabri_hnf_phase3_features`, so implemented features can become active without rewriting the accepted Phase 2 settings option.

Enabled runtime defaults:

- `reactions_enabled`
- `dislikes_enabled`
- `saves_enabled`
- `show_public_reaction_counts`

Still disabled:

- comments;
- follows;
- followers-only visibility;
- reports;
- polls;
- notification bridge;
- view logging.

Safe Mode and Emergency Disable override every Phase 3 runtime flag.

## Database behavior

Checkpoint 3B reuses the accepted Phase 2 schema version `1.0.0`.

- reactions use the plugin-owned reactions table;
- removal deletes the active reaction row, avoiding conflicts with the existing unique active-row index;
- saves reuse one unique user/post/collection row and change its active/removed state;
- prepared, allow-listed repository queries are used;
- no WordPress post, comment, user, media, URL, or companion-plugin data is modified;
- no schema version bump is required;
- no fake data is created.

## Automated validation

### Phase 3B functional suite

Covers:

- Like creation;
- active-reaction toggle removal;
- Like/Dislike switching;
- aggregate counts across two users;
- private current-user state;
- invalid reaction rejection;
- pending-post rejection;
- forged-user rejection;
- Save idempotency;
- Unsave;
- private Saved Posts list;
- cross-user privacy isolation;
- visitor action-bar state;
- REST route registration;
- REST nonce permission;
- duplicate active-row prevention.

### Race and shortcode suite

Covers:

- reaction unique-insert race recovery;
- save unique-insert race recovery;
- one-row persistence after race recovery;
- Saved Posts shortcode registration;
- current-user rendering;
- second-user isolation;
- visitor login state;
- explicit login redirect construction.

### Full CI

The latest successful workflow validates:

1. PHP syntax lint;
2. complete Phase 2 regression suite;
3. Phase 3 contract suite;
4. Phase 3A infrastructure suite;
5. Phase 3B functional suite;
6. Phase 3B race/shortcode suite;
7. JavaScript syntax;
8. CSS, JSON, security, and whitespace checks;
9. existing artifact build;
10. ZIP structure;
11. SHA-256 integrity.

## Deliberately deferred

Checkpoint 3B does not implement:

- comments or replies;
- follows;
- reports;
- polls;
- notifications;
- follower-only visibility;
- view analytics;
- moderation UI;
- Phase 4 Editorial News;
- production release packaging;
- merge to `main`;
- Hostinger staging deployment.

## Next permitted checkpoint

Checkpoint 3C — Comments and Replies:

- WordPress-native comment storage;
- authenticated comment and reply creation;
- edit window;
- soft delete;
- bounded reply depth;
- approved-state and post-comment settings;
- clinical-case privacy scan;
- moderation status;
- accessible thread UI;
- two-user, privacy, rate-limit, edit-window, and direct-URL tests.
