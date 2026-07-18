# Security

## Principles

- Administrator actions require a capability check and nonce.
- REST diagnostic routes require authenticated administrative permission.
- No public write REST routes are registered in Phase 1.
- No raw host-header redirects are used.
- Safe Mode uses `?sabri_feed_safe=1` and requires an administrator.
- Emergency Disable uses a central feature gate and preserves data.
- No external CDNs, remote fonts, or runtime third-party JavaScript are used.
- No hard-coded secrets are included.

## Data Access

Social tables reference posts and users without database-level foreign keys to avoid host conflicts with WordPress-managed data.

Runtime user-data SQL uses prepared statements or internally generated identifiers from whitelisted table and column names.

## Admin Styling

The brand accent is `#f26100`. Phase 1 uses minimal administration styling only.

## Known Limits

Phase 1 is a foundation. Hostinger staging, browser, database, and workflow acceptance are still required before any production claim.
