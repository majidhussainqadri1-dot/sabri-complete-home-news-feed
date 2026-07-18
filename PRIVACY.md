# Privacy

## Foundations

Phase 2 keeps Phase 1 personal data exporter and eraser hooks for plugin-owned social data and adds public composer safeguards for patient privacy.

The exporter emits one WordPress-compatible export item per personal-data row. Each item contains a flat list of `name` and `value` entries.

## Saved Posts

Saved-post data is private to the saving user. The plugin does not publicly expose who saved a post.

## Follows

Follow data is exportable for the requesting user and can be marked removed or anonymized during erasure.

## Reports

Report data remains confidential and is restricted to authorized moderation and administration contexts.

Personal-data exports do not expose confidential moderation notes, duplicate hashes, raw internal secrets, or unrelated private user data.

## Clinical Cases

Clinical Case composer validation rejects direct patient identifiers such as patient full name, national ID, passport, phone number, complete residential address, and raw confidential identifiers. Patient consent and anonymization confirmation can be required by settings.

## Visibility

Feed queries, single-post checks, and REST post response filters enforce Phase 2 visibility modes. Followers visibility remains disabled until the Phase 3 follow runtime exists.

## Audit Log

Audit logs are restricted administrative records. Erasure anonymizes actor references where supported while retaining accountability records.

## Views

Anonymous views are minimized with hashed identity fields and date-level aggregation.

## Uninstall

Default uninstall behavior retains plugin data. An administrator may explicitly choose non-retention before uninstall; even then, WordPress content and companion-plugin data remain preserved.
