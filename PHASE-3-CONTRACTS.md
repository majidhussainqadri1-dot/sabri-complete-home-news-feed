# Phase 3 Social Interactions — Checkpoint 3.0 Contract Freeze

Target release: `1.1.0`

Integration branch: `build/phase-3-social-interactions-1.1.0`

This checkpoint froze the Phase 3 service, settings, REST, result, and safety contracts without enabling public social-interaction runtime. The accepted Phase 2 plugin and schema versions remain `1.0.0` throughout the integration branch until the release checkpoint.

## Scope

Checkpoint 3.0 added only:

- fail-closed feature-flag names, all disabled by default;
- settings-name contracts for social, moderation, performance, and privacy behavior;
- the existing REST namespace and proposed route map;
- the stable service result shape `ok`, `code`, `message`, `data`, `status`;
- reaction, report-state, report-reason, and poll-policy allow-lists;
- an isolated contract test runner in CI.

It did not register Phase 3 routes, render action buttons, mutate the Phase 2 settings option, alter the database schema, or enable likes, dislikes, comments, saves, follows, reports, polls, notifications, views, or followers-only visibility.

## Feature Flags

All frozen flags default to disabled:

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

## REST Contract

Namespace: `sabri-home-news-feed/v1`

Every write route requires authentication, a valid REST nonce, centralized object visibility or ownership authorization, strict allow-list validation, bounded payloads, rate limiting, and no-store responses.

## Service Result Contract

Every Phase 3 service returns exactly:

```php
array(
    'ok'      => true|false,
    'code'    => 'machine_code',
    'message' => 'Safe public message',
    'data'    => array(),
    'status'  => 200,
)
```

`InteractionResult` provides the frozen builder. REST controllers must not expose raw SQL, exception details, private report notes, user email, phone, patient identifiers, voter identities, or internal integration errors.

## Implemented bounded checkpoints

- 3A: shared permissions, repository boundaries, rate limits, and schema audit.
- 3B: reactions and private saves.
- 3C: comments and replies.
- 3D: Follow and Following.
- 3E: reports and confidential moderation queue.
- 3F: bounded Poll creation, authenticated voting, results policies, privacy lifecycle, and accessible rendering.

Each completed surface remains controlled by isolated feature settings and the Draft PR remains unmerged pending the remaining Phase 3 plan verification and Hostinger staging acceptance.

## Remaining frozen scope

The following frozen runtime areas remain deliberately unimplemented and disabled:

- notification bridge;
- view logging and analytics;
- followers-only visibility.

Their checkpoint names and order must be verified against the approved Phase 3 plan rather than inferred.
