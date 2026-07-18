# Migration

Migrations are versioned by `sabri_feed_schema_version`.

## Current Target

`1.0.0`

## Preview

The Migration admin page previews:

- current schema version;
- target schema version;
- expected custom tables;
- expected indexes;
- whether the migration is destructive.

Phase 1 migrations are non-destructive.

## Execution

Migration execution:

1. Captures a snapshot unless already running inside activation.
2. Runs dbDelta-compatible schema creation or update.
3. Stores the target schema version.
4. Returns a structured report.

Migration does not delete WordPress posts, pages, users, comments, media, URLs, or companion-plugin data.
