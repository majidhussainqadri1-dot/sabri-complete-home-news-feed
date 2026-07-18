# Privacy

## Foundations

Phase 1 registers personal data exporter and eraser hooks for plugin-owned social data.

The exporter emits one WordPress-compatible export item per personal-data row. Each item contains a flat list of `name` and `value` entries.

## Saved Posts

Saved-post data is private to the saving user. The plugin does not publicly expose who saved a post.

## Follows

Follow data is exportable for the requesting user and can be marked removed or anonymized during erasure.

## Reports

Report data remains confidential and is restricted to authorized moderation and administration contexts.

Personal-data exports do not expose confidential moderation notes, duplicate hashes, raw internal secrets, or unrelated private user data.

## Audit Log

Audit logs are restricted administrative records. Erasure anonymizes actor references where supported while retaining accountability records.

## Views

Anonymous views are minimized with hashed identity fields and date-level aggregation.

## Uninstall

Default uninstall behavior retains plugin data. An administrator may explicitly choose non-retention before uninstall; even then, WordPress content and companion-plugin data remain preserved.
