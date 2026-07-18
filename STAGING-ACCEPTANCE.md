# Staging Acceptance

No production claim is allowed until GitHub Actions and Hostinger staging acceptance pass.

## Required Checks

- Plugin activates without fatal errors on PHP 8.1+ and WordPress 6.0+.
- Existing posts, pages, users, comments, media, URLs, and companion-plugin data remain unchanged.
- Admin menu pages load and contain Phase 1 information.
- System Check reports PHP, WordPress, database, tables, indexes, options, cron, REST, media directory, Unified Shell detection, companion states, and filesystem status.
- Safe Mode works for administrators with `?sabri_feed_safe=1`.
- Emergency Disable disables future public actions and can be re-enabled.
- Repair actions require capability, nonce, and confirmation.
- Migration preview and execution are idempotent.
- Rollback preview and execution preserve site content.
- Privacy exporter and eraser hooks register.
- REST diagnostic routes require permission callbacks.
- Release ZIP has exactly one top-level folder.
- Release ZIP excludes development files.
- SHA-256 file matches the ZIP.

## Manual Acceptance

- Test on Hostinger staging before production activation.
- Confirm Shell integration status with the actual deployed Shell plugin.
- Confirm no duplicate global navigation or Shell layout replacement appears.
- Confirm no fake content, engagement, analytics, or doctor data is created.
