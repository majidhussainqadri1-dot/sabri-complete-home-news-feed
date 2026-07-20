# Phase 3C — Comments and Replies

Target Phase 3 release: `1.1.0`

Integration branch: `build/phase-3-social-interactions-1.1.0`

Status: implementation and automated validation complete on the integration branch. The runtime remains disabled by default until Hostinger staging acceptance. The pull request remains Draft and must not be merged.

## Storage model

Checkpoint 3C uses the WordPress-native comments table rather than introducing another plugin table.

Plugin comments are isolated with:

- `comment_type = sabri_feed_comment`;
- authenticated WordPress `user_id` ownership;
- WordPress parent-comment relationships;
- native approval state;
- plugin-owned metadata for soft deletion, edit time, and privacy-scan state.

The service deliberately stores an empty IP address and user-agent value. REST and HTML serializers do not expose author email, IP address, user-agent, private moderation data, or matched patient identifiers.

## Comment policy

Frozen defaults:

- maximum comment length: 2,000 characters;
- maximum reply depth: three;
- owner edit window: 15 minutes;
- ordinary new comments: held for review;
- authorized moderators: immediate approval;
- comment rate limit: 10 writes per user/post per 10 minutes;
- clinical-case privacy scan: enabled;
- public comment count: approved plugin comments only.

Comments may be created only by an authenticated current-session user with a valid REST nonce against a visible, published, approved post whose comments are open.

## Replies

A reply parent must:

- exist;
- belong to the same post;
- use the plugin-owned comment type;
- not be soft-deleted;
- remain within the configured nesting depth.

Cross-post parents, foreign comment types, deleted parents, negative IDs, malformed IDs, and excessive nesting fail closed.

## Moderation and visibility

- approved comments are public to users who may view the post;
- pending comments are visible only to their author and authorized moderators;
- pending comments do not increase the public count;
- ordinary users cannot edit or delete another member’s comment;
- moderators may edit or soft-delete comments;
- owners may edit during the 15-minute window;
- owners may soft-delete their own comments;
- deletion replaces content with a neutral placeholder while preserving replies and accountability.

## Clinical privacy scanner

For `clinical-case` posts, the service blocks obvious patient-identifying information before storage. High-confidence categories include:

- email addresses;
- phone and WhatsApp numbers;
- CNIC and national-ID patterns;
- passport identifiers;
- explicit patient-name labels;
- residential-address labels;
- medical-record identifiers.

The error response contains category names only. It never returns the detected private value. Non-clinical posts are not silently subjected to the clinical identifier rule.

## REST endpoints

Namespace: `sabri-home-news-feed/v1`

- `GET /posts/{id}/comments`
- `POST /posts/{id}/comments`
- `PATCH /comments/{id}`
- `DELETE /comments/{id}`

Write endpoints require:

- current WordPress session;
- valid `X-WP-Nonce`;
- post visibility and approval;
- ownership or moderation authorization;
- strict IDs and bounded content;
- rate limiting;
- enabled Checkpoint 3C feature flag.

Read and write permissions both fail closed while the comments feature flag is disabled. Comment responses use `Cache-Control: no-store, private` where the WordPress REST response API is available because a response may include current-user pending state.

## User interface

The feed action bar includes a Comment link and approved count when the staging flag is enabled. The link opens the direct single-post thread rather than expanding a duplicate feed instance.

The direct post interface includes:

- server-rendered approved thread;
- current-user pending rows;
- authenticated comment form;
- reply, edit, cancel, and delete controls;
- live status region;
- keyboard focus indicators;
- 44-pixel primary controls;
- mobile nesting reduction;
- reduced-motion handling;
- progressive enhancement through `assets/js/comments.js`.

If JavaScript fails, the direct post, action link, approved comments, and login state remain readable. No AJAX-only placeholder replaces the server-rendered thread.

## Feature gate

`comments_enabled` remains `0` in the safe default feature option. Automated tests explicitly enable it in isolation. Hostinger staging acceptance is required before changing the default or enabling the option in the staging environment.

This prevents an unreviewed public comment surface from appearing automatically while preserving the completed code and tests in the Phase 3 branch.

## Automated coverage

`tests/run-phase3c-comments-tests.php` covers:

- native custom comment type;
- empty IP and user-agent storage;
- pending-by-default behavior;
- moderator immediate approval;
- author-only pending visibility;
- public approved-only visibility;
- public count isolation;
- no private-field serialization;
- clinical privacy scanning and non-echo of detected identifiers;
- non-clinical content behavior;
- minimum and maximum length;
- closed comments;
- pending-post rejection;
- strict REST IDs;
- same-post parent validation;
- foreign-type rejection;
- reply depth;
- edit window;
- moderator override;
- forged-user rejection;
- cross-user delete rejection;
- soft deletion with replies retained;
- deleted-parent rejection;
- rate limiting;
- REST route and nonce checks;
- action-bar link and count;
- visitor login state without nonce leakage;
- direct-route visibility;
- single-post duplicate guard;
- feature-flag fail-closed behavior;
- preservation of plugin and schema version `1.0.0`.

## Deliberately deferred

Checkpoint 3C does not implement:

- follows or followers-only visibility;
- reports and moderation queue UI;
- notifications;
- polls;
- view analytics;
- Phase 4 Editorial News;
- production deployment;
- merge to `main`.

## Next permitted checkpoint

Checkpoint 3D — Follow and Following:

- Follow/Unfollow users;
- self-follow prevention;
- active/removed relationship state;
- private Following list;
- bounded public counts where policy allows;
- profile-link integration without modifying the Profiles repository;
- two-user, block-state, IDOR, rate-limit, and privacy tests.
