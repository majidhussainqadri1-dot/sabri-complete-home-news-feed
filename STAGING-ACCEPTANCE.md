# Staging Acceptance

No production claim is allowed until GitHub Actions and Hostinger staging acceptance pass.

## Required Checks

- Plugin activates without fatal errors on PHP 8.1+ and WordPress 6.0+.
- Existing posts, pages, users, comments, media, URLs, and companion-plugin data remain unchanged.
- Admin menu pages load and expose functional Phase 2 Feed and Composer settings.
- System Check reports PHP, WordPress, database, tables, indexes, options, cron, REST, media directory, Unified Shell detection, companion states, and filesystem status.
- Safe Mode works for administrators with `?sabri_feed_safe=1`.
- Emergency Disable disables the public composer/custom feed runtime and can be re-enabled.
- Repair actions require capability, nonce, and confirmation.
- Migration preview and execution are idempotent.
- Rollback preview and execution preserve site content.
- Privacy exporter and eraser hooks register.
- REST diagnostic routes require permission callbacks.
- Feed REST route is read-only and returns only content visible to the requester.
- Composer REST routes require authentication, nonce, capability checks, status-transition checks, and structured errors.
- `[sabri_complete_home_feed]` renders existing WordPress posts without duplication.
- `[sabri_public_post_composer]` appears only for authorized users.
- Students and patients cannot publish general posts.
- Clinical Case validation blocks direct patient identifiers.
- Research posts use controlled evidence levels only.
- Media uploads enforce MIME, size, executable-file, and ownership validation.
- Release ZIP has exactly one top-level folder.
- Release ZIP excludes development files.
- SHA-256 file matches the ZIP.

## Manual Acceptance

- Test on Hostinger staging before production activation.
- Use **Home & News Feed → Staging Preview** as an administrator to verify the real feed and composer without creating WordPress test pages.
- Confirm Shell integration status with the actual deployed Shell plugin.
- Confirm no duplicate global navigation or Shell layout replacement appears.
- Confirm no fake content, engagement, analytics, or doctor data is created.
- Confirm no Phase 3 social controls or Phase 4 complete News claims appear.
