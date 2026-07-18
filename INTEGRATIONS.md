# Integrations

The sibling Sabri Unified Application Shell repository was inspected read-only at:

`C:\Users\usman computerss\OneDrive\Documents\Documents\ChatGPT Codex\sabri-unified-application-shell`

No files in the sibling repository were modified.

## Confirmed Existing Shell Hooks

From the inspected Shell 1.0.0 code:

- `sabri_shell_navigation_destinations` filter
- `sabri_shell_home_feed_post_types` filter
- `sabri_shell_create_url` filter
- `sabri_shell_layout_mode` filter
- `sabri_shell_system_check_report` filter
- `sabri_shell_complete_repair_ran` action

## Plugin-Owned Fallback Hooks

These belong to this plugin and are safe to use before a Shell update:

- `sabri_feed_home_center_content`
- `sabri_feed_home_contextual_sidebar`
- `sabri_feed_news_center_content`
- `sabri_feed_news_contextual_sidebar`
- `sabri_feed_post_detail_context`
- `sabri_feed_mobile_context_drawer_modules`

## Proposed Future Shell Contracts

These are desired integration slots and are not confirmed in Shell 1.0.0:

- Home center content
- Home contextual sidebar
- News center content
- News contextual sidebar
- Post detail context
- Mobile context drawer modules

## Safety Contract

This plugin:

- Detects whether the Shell is active.
- Does not fatal when the Shell is absent.
- Does not create fake Shell integration.
- Avoids duplicate Home feed output.
- Does not replace the Shell header or sidebars.
- Does not render a second global navigation.
- Does not change the Shell layout resolver.
