# Security

## Principles

- Administrator actions require a capability check and nonce.
- REST diagnostic routes require authenticated administrative permission.
- Public feed REST is read-only and has an explicit permission callback.
- Composer REST routes require an authenticated authorized user, REST nonce, capability policy checks, allowed status transitions, sanitization, validation, media ownership checks, and structured errors.
- No raw host-header redirects are used.
- Safe Mode uses `?sabri_feed_safe=1` and requires an administrator.
- Emergency Disable uses a central feature gate, disables the public composer/custom feed runtime, and preserves data.
- No external CDNs, remote fonts, or runtime third-party JavaScript are used.
- No hard-coded secrets are included.

## Data Access

Social tables reference posts and users without database-level foreign keys to avoid host conflicts with WordPress-managed data.

Runtime user-data SQL uses prepared statements or internally generated identifiers from whitelisted table and column names.

## Admin Styling

The brand accent is `#f26100`. Phase 2 uses local administration, feed, and composer styling only.

## Known Limits

Phase 2 is a development runtime. Hostinger staging, browser, database, upload, Shell, and workflow acceptance are still required before any production claim.
