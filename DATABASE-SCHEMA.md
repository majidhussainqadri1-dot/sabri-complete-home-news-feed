# Database Schema

The schema is versioned by `sabri_feed_schema_version` and targets version `1.0.0`.

Custom tables use the actual WordPress prefix dynamically. The examples below use `wp_`.

## Tables

### `wp_sabri_feed_reactions`

Stores one active reaction state per user per post.

- Unique: `user_post_status (user_id, post_id, status)`
- Statuses: `active`, `removed`
- No database-level foreign keys.

### `wp_sabri_feed_follows`

Stores one follow relation per follower and target pair.

- Unique: `follower_target (follower_user_id, target_user_id, target_type)`
- Statuses: `active`, `blocked`, `removed`
- No database-level foreign keys.

### `wp_sabri_feed_saves`

Stores private saved-post records.

- Unique: `user_post_collection (user_id, post_id, collection_key)`
- Statuses: `active`, `removed`
- Saved-post ownership is not publicly exposed.

### `wp_sabri_feed_reports`

Stores confidential reports for moderation.

- Unique: `duplicate_control (reporter_user_id, object_type, object_id, duplicate_hash)`
- Statuses: `open`, `triaged`, `resolved`, `dismissed`, `duplicate`

### `wp_sabri_feed_views`

Stores minimized view aggregates.

- Unique: `view_identity (post_id, user_id, anonymous_hash, view_date)`
- Statuses: `counted`, `ignored`

### `wp_sabri_feed_poll_votes`

Stores poll vote identity according to poll settings.

- Unique: `vote_identity (poll_post_id, user_id, anonymous_hash, vote_group_key)`
- Statuses: `active`, `replaced`, `removed`

### `wp_sabri_feed_audit_log`

Stores restricted administrative audit events.

- Indexes: `action_created`, `actor_created`, `object_lookup`
- Status is implicit as recorded append-only events.

## Migration Rules

- SQL is dbDelta-compatible.
- Activation is non-destructive.
- Migration preview is available before execution.
- A snapshot is captured before schema mutation.
- Runtime user data SQL uses prepared statements or internally constructed, whitelisted identifiers.
- Rollback does not delete WordPress content or companion-plugin data.

## Uninstall Policy

Default uninstall behavior retains plugin data. If an administrator explicitly disables retention before uninstall, only plugin-owned options and custom plugin tables may be removed. WordPress posts, pages, users, comments, media, URLs, and companion-plugin data are never deleted by uninstall.
