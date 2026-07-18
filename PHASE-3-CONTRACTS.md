# Phase 3 Social Interactions — Checkpoint 3.0 Contract Freeze

Target release: `1.1.0`

Integration branch: `build/phase-3-social-interactions-1.1.0`

This checkpoint freezes the Phase 3 service, settings, REST, result, and safety contracts without enabling any public social-interaction runtime. The accepted Phase 2 plugin and schema versions remain `1.0.0`.

## Scope

Checkpoint 3.0 adds only:

- fail-closed feature-flag names, all disabled by default;
- settings-name contracts for social, moderation, performance, and privacy behavior;
- the existing REST namespace and proposed route map;
- the stable service result shape `ok`, `code`, `message`, `data`, `status`;
- reaction, report-state, report-reason, and poll-policy allow-lists;
- an isolated contract test runner in CI.

It does not register Phase 3 routes, render action buttons, mutate the Phase 2 settings option, alter the database schema, or enable likes, dislikes, comments, saves, follows, reports, polls, notifications, views, or followers-only visibility.

## Feature Flags

All flags default to disabled:

- `reactions_enabled`
- `dislikes_enabled`
- `comments_enabled`
- `saves_enabled`
- `follows_enabled`
- `followers_visibility_enabled`
- `reports_enabled`
- `polls_enabled`
- `notification_bridge_enabled`
- `view_logging_enabled`

Unknown feature names fail closed.

## Settings Contract

### Social

- `reactions_enabled`
- `dislikes_enabled`
- `comments_enabled`
- `max_comment_length`
- `max_reply_depth`
- `comment_edit_minutes`
- `saves_enabled`
- `follows_enabled`
- `followers_visibility_enabled`
- `polls_enabled`
- `show_public_reaction_counts`
- `show_public_follower_counts`

### Moderation

- `reports_enabled`
- `allowed_report_reasons`
- `clinical_comment_privacy_scan`
- `new_comment_policy`
- `rate_limit_window_seconds`
- `rate_limit_max_actions`

### Performance

- `engagement_cache_seconds`
- `log_views`
- `view_deduplication_days`

### Privacy

- `export_private_saves`
- `export_follows`
- `retain_reports_for_accountability`
- `anonymize_views`

Settings integration is intentionally deferred to Checkpoint 3A, after shared permissions, repository boundaries, schema audit, and rate limiting are implemented and tested.

## REST Contract

Namespace: `sabri-home-news-feed/v1`

Proposed routes are frozen in `Phase3Contracts::rest_routes()`. No Phase 3 route is registered at Checkpoint 3.0.

Every later write route must require authentication, a valid REST nonce, centralized object visibility or ownership authorization, strict allow-list validation, bounded payloads, rate limiting, and no-store responses.

## Service Result Contract

Every Phase 3 service will return exactly:

```php
array(
    'ok'      => true|false,
    'code'    => 'machine_code',
    'message' => 'Safe public message',
    'data'    => array(),
    'status'  => 200,
)
```

`InteractionResult` provides the frozen builder. REST controllers must not expose raw SQL, exception details, private report notes, user email, phone, patient identifiers, or internal integration errors.

## Checkpoint Exit

Checkpoint 3.0 exits only when:

- the Phase 2 behavior suite remains green;
- the Phase 3 contract suite is green;
- PHP lint and static checks are green;
- the branch remains non-destructive and has no public Phase 3 UI;
- GitHub Actions is green.

The next permitted work is Checkpoint 3A: shared permissions, repository wrappers, rate limits, and schema audit. Phase 3B and later runtime work must not start before Checkpoint 3A passes.
