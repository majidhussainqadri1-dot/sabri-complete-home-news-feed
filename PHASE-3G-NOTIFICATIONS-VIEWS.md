# Phase 3G — Notification Bridge and Views

Target Phase 3 release: `1.1.0`

Integration branch: `build/phase-3-social-interactions-1.1.0`

Status: implementation and automated validation complete on the integration branch. `notification_bridge_enabled` and `view_logging_enabled` remain disabled by default until Hostinger staging acceptance. The pull request remains Draft and must not be merged.

## Scope

Checkpoint 3G implements two bounded systems:

1. a privacy-minimized event bridge from successful social interactions to an optional external Notifications system;
2. server-side, deduplicated, privacy-minimized view logging for visible direct single-post requests.

It does not create a notification inbox, notification delivery table, public view-write endpoint, per-view public history, or cross-repository dependency.

## Notification bridge architecture

The Home and News Feed plugin remains the source of social events but does not become the owner of notification storage or delivery.

The bridge exposes:

- plugin-owned action: `sabri_feed_notification_event`;
- safe failure action: `sabri_feed_notification_bridge_failed`;
- optional one-argument callback configured through the existing Notifications integration function setting;
- a five-minute privacy-safe transient deduplication key.

A notification callback failure never rolls back or changes the originating Like, Comment, Follow, or Poll Vote. The bridge catches connector exceptions and returns only a generic failure state without exposing internal exception text.

### Allow-listed events

- `post_reaction`
- `post_comment`
- `comment_reply`
- `user_follow`
- `poll_vote`

### Recipient rules

- a successful Like or Dislike notifies the post author;
- an approved top-level plugin comment notifies the post author;
- an approved plugin reply notifies the parent-comment author;
- a newly active Follow notifies the followed account;
- a newly active Poll Vote notifies the Poll author.

The bridge deliberately does not notify for:

- Save or Unsave;
- reaction removal;
- comment edit or soft deletion;
- Unfollow;
- confidential Report submission or moderation;
- Poll vote replacement or removal;
- repeated idempotent state requests.

Self-notifications are suppressed. Pending comments notify only when approved. Immediate approved comments are covered by `comment_post`; later approvals are covered by `transition_comment_status`. The bounded dedupe key prevents the two WordPress hooks from producing duplicate delivery.

### Payload contract

The bridge payload contains only:

- event key;
- actor user ID;
- recipient user ID;
- object type;
- object ID;
- related post ID where applicable;
- safe destination URL;
- UTC creation timestamp.

It never contains:

- email address;
- phone or WhatsApp number;
- user roles;
- display name;
- comment or post content;
- report reason or confidential note;
- Poll option or vote choice;
- IP address;
- user agent;
- database row or internal exception detail.

## View logging architecture

No public REST endpoint accepts view writes. Views are recorded only from an eligible server-side `template_redirect` request for a direct standard WordPress post.

A request is countable only when:

- `view_logging_enabled = 1`;
- the route is a direct single post;
- the post is published, approved, and visible to the requester;
- the request is not an admin, preview, feed, robots, or trackback request;
- Do Not Track is not set to `1`;
- the user agent is not an obvious bot, crawler, monitor, preview fetcher, or headless browser;
- the optional `sabri_feed_should_count_view` policy filter permits it.

### Identity minimization

Authenticated requests store only the current WordPress user ID. Request data cannot select another account.

Guest requests store:

- `user_id = 0`;
- a 64-character HMAC-SHA-256 identity derived from the request IP and bounded user agent with the WordPress nonce salt.

Raw IP addresses and user agents are never stored. A guest request without a valid IP identity is not counted.

### Deduplication

The accepted `sabri_feed_views` table and its unique identity index are reused. No new table or migration is required.

The deduplication window:

- defaults to one UTC day;
- is bounded between 1 and 30 days;
- may be configured through `view_deduplication_days` or the `sabri_feed_view_deduplication_days` policy filter;
- applies per post and authenticated user or guest HMAC identity;
- recovers safely when a concurrent request wins the unique insert.

Each accepted row contributes one counted view. Repeated requests inside the window do not increment or create another row.

## Public view output

Public surfaces receive only aggregate `view_count` for a visible post.

The aggregate count is included in the existing engagement summary and rendered as a non-interactive, accessible `Views` label in the Feed and single-post action bar when view logging is enabled.

No public response exposes:

- viewer IDs;
- guest hashes;
- individual view dates;
- raw view rows;
- per-user viewing history.

View inserts invalidate the existing engagement cache so the next aggregate read is fresh.

## Privacy export and erasure

Authenticated view history is personal data.

The WordPress privacy exporter includes only the requesting account’s:

- viewed post ID;
- view date;
- counted value;
- row status.

It does not export guest hashes, raw request identifiers, or another account’s history.

Account erasure:

- sets the matching view row’s `user_id` to zero;
- replaces the identity with a deterministic per-row SHA-256 erased-view hash;
- retains the aggregate counted event without retaining the WordPress account identity;
- uses the row ID, post ID, view date, user ID, and site salt to prevent unique-index collisions between multiple erased rows.

Guest HMAC identities remain minimized and are not reversible by this plugin.

## Schema and version boundary

Checkpoint 3G reuses the existing:

- `sabri_feed_views` table;
- `view_identity` unique index;
- counted/ignored status allow-list;
- repository column allow-list;
- privacy exporter and eraser framework;
- integration function setting.

No destructive migration, table change, or schema-version increase is required.

The accepted plugin and schema versions remain `1.0.0` until the Phase 3 release checkpoint.

## Automated coverage

`tests/run-phase3g-notifications-views-tests.php` covers:

- default-disabled notification and view flags;
- disabled bridge behavior;
- event allow-list and recipient rules;
- self-notification suppression;
- duplicate notification suppression;
- real ReactionService bridge invocation;
- approved top-level comment and reply recipients;
- pending comment approval behavior;
- notification payload secrecy;
- connector exception isolation;
- first authenticated view;
- authenticated duplicate suppression;
- forged user identity rejection;
- two-user isolation;
- guest HMAC identity;
- raw IP and user-agent non-retention;
- guest duplicate suppression;
- Do Not Track and bot exclusion;
- hidden/pending post rejection;
- aggregate view count;
- engagement and accessible action-bar integration;
- configurable multi-day deduplication;
- concurrent insert recovery;
- privacy export;
- collision-safe account erasure;
- direct single-post runtime logging;
- preservation of plugin and schema version `1.0.0`.

The complete workflow also validates Phase 2 and checkpoints 3.0–3F, PHP and JavaScript syntax, static security, CSS/JSON/whitespace, artifact build, ZIP structure, and SHA-256 integrity.

## Deliberately deferred

Checkpoint 3G does not include:

- an internal notification inbox;
- email, push, SMS, or WhatsApp delivery;
- notification read/unread state;
- notification preferences;
- public view-write REST routes;
- live real-time analytics;
- public viewer lists;
- per-user public view history;
- Phase 4 Editorial News;
- production deployment;
- merge to `main`.

## Next permitted checkpoint

The next approved checkpoint is **3H — Full Hardening, Accessibility, Staging, and Release Readiness**.

Checkpoint 3H must audit the complete Phase 3 surface, keep all unaccepted feature gates fail-closed, run cross-feature accessibility and security testing, verify installation and rollback on Hostinger staging, and prepare release `1.1.0` only after explicit acceptance. It must not merge or deploy automatically.
