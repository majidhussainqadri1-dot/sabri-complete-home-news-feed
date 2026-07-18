# Capabilities

The plugin uses plugin-specific capabilities only:

- `sabri_feed_create_posts`
- `sabri_feed_publish_posts`
- `sabri_feed_submit_for_review`
- `sabri_feed_moderate_posts`
- `sabri_feed_manage_news`
- `sabri_feed_manage_breaking_news`
- `sabri_feed_manage_settings`
- `sabri_feed_view_analytics`
- `sabri_feed_manage_reports`
- `sabri_feed_run_repairs`
- `sabri_feed_run_migrations`
- `sabri_feed_run_rollbacks`

## Default Policy

- Administrator receives complete plugin management capabilities.
- Editor receives editorial capabilities only: create, publish, submit, moderate, manage news, and manage reports.
- Existing founder roles may publish immediately when configured and detected.
- Existing verified doctor roles publish or submit according to settings. Default is submit.
- Existing unverified doctor roles may create and submit for review.
- Students and patients receive no general publishing capability.
- Visitors receive no write capability.

The plugin does not create broad site roles and does not alter unrelated WordPress capabilities.

## Reversibility

Activation captures a snapshot before capability mutation. Rollback restores plugin capability assignments from that snapshot.

Emergency Disable centrally blocks future public create, publish, and submit behavior while preserving data and admin access.
