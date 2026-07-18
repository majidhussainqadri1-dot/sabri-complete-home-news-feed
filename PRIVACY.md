# Privacy

## Foundations

Phase 1 registers personal data exporter and eraser hooks for plugin-owned social data.

## Saved Posts

Saved-post data is private to the saving user. The plugin does not publicly expose who saved a post.

## Follows

Follow data is exportable for the requesting user and can be marked removed or anonymized during erasure.

## Reports

Report data remains confidential and is restricted to authorized moderation and administration contexts.

## Audit Log

Audit logs are restricted administrative records. Erasure anonymizes actor references where supported while retaining accountability records.

## Views

Anonymous views are minimized with hashed identity fields and date-level aggregation.

## Uninstall

Default uninstall behavior retains plugin data. An administrator may explicitly choose non-retention before uninstall; even then, WordPress content and companion-plugin data remain preserved.
