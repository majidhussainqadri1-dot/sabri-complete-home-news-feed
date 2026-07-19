# Phase 3E — Reports and Moderation Queue

Target Phase 3 release: `1.1.0`

Integration branch: `build/phase-3-social-interactions-1.1.0`

Status: implementation and automated validation complete on the integration branch. New public report submission remains disabled by default until Hostinger staging acceptance. The pull request remains Draft and must not be merged.

## Storage model

Checkpoint 3E reuses the accepted plugin-owned `sabri_feed_reports` table and schema version `1.0.0`.

Each confidential report contains:

- authenticated reporter user ID;
- object type: `post` or plugin-owned `comment`;
- object ID;
- allow-listed reason;
- bounded state;
- SHA-256 duplicate-control identity;
- JSON-encoded confidential reporter and moderator notes;
- created and updated UTC timestamps.

No new table, destructive cleanup, or schema-version increase is required.

## Public report policy

A report submission requires:

- the current WordPress session;
- a valid `X-WP-Nonce`;
- `reports_enabled = 1`;
- Safe Mode and Emergency Disable clearance;
- a visible, published, approved post or an authorized visible plugin comment;
- an allow-listed object type and reason;
- content owned by another account;
- no soft-deleted comment state;
- no more than five attempts per user/object per hour.

Reportable reasons:

- spam;
- harassment;
- hate or abuse;
- misinformation;
- medical safety risk;
- patient privacy;
- copyright or missing source;
- impersonation;
- other.

`Other` requires a meaningful confidential explanation. Reporter notes are plain text and bounded to 1,000 characters.

## Duplicate and concurrency behavior

The duplicate-control identity is derived from:

- reporter user ID;
- object type;
- object ID;
- reason.

Repeated identical submissions are idempotent. The public response never reveals whether the report was new or a duplicate. Concurrent unique-key insertion is recovered through a safe re-read and retains one confidential row.

## Public response secrecy

A successful public response contains only a generic submitted state. It does not expose:

- report ID;
- reporter identity;
- report status;
- duplicate hash;
- reporter note;
- moderator note;
- moderator identity;
- internal timestamps;
- database errors.

The report form warns users not to include patient-identifying information.

## REST endpoints

Namespace: `sabri-home-news-feed/v1`

Public submission:

- `POST /reports`

Moderator-only:

- `GET /moderation/reports`
- `PATCH /moderation/reports/{id}`

All responses use private, no-store cache policy where the WordPress REST response API is available.

The moderator routes require:

- the current WordPress session;
- a valid REST nonce;
- `sabri_feed_manage_reports` or administrator authority.

Existing reports remain available to authorized moderators even when new public submissions are disabled.

## Moderation states

Allowed states:

- `open`;
- `triaged`;
- `resolved`;
- `dismissed`;
- `duplicate`.

Bounded transitions:

- Open may remain Open or move to Triaged, Resolved, Dismissed, or Duplicate.
- Triaged may remain Triaged or move to Open, Resolved, Dismissed, or Duplicate.
- Resolved, Dismissed, and Duplicate may remain unchanged or return only to Triaged.

This prevents a terminal report from reopening directly without renewed triage.

Moderator notes are private, plain text, and bounded to 2,000 characters. Status changes and report creation are recorded in the plugin-owned audit log without public disclosure.

## Moderator queue

The `Home & News Feed → Reports` administration screen is restricted to report managers.

It provides:

- filters by status, reason, and content type;
- bounded pagination;
- safe reported-content links and excerpts;
- reporter public display name and user ID;
- confidential reporter note;
- private moderator note;
- bounded status controls;
- success and error states;
- a warning that queue data must not be copied into public posts or comments.

The queue serialization excludes email addresses, phone numbers, login names, roles, capabilities, IP addresses, user agents, and duplicate hashes.

## Public user interface

When the staging feature flag is enabled:

- visible posts provide an inline Report control;
- approved, non-deleted plugin comments provide a Report control;
- users do not see a report form on their own content;
- visitors receive a sign-in state;
- reason selection and confidential details are keyboard accessible;
- live submission status is announced;
- duplicate-click state is bounded;
- delegated event handling supports Load More posts;
- JavaScript failure does not affect content reading.

Pending, foreign-type, deleted, hidden, or unavailable comments do not receive a report surface.

## Privacy and retention

The existing privacy exporter exposes only bounded report ownership data to the requesting account and excludes confidential notes.

Account erasure anonymizes the reporter user ID while retaining the report for moderation accountability. Reports and audit records are not publicly searchable.

## Feature gate

`reports_enabled` remains `0` in safe runtime defaults. Automated tests enable it only in isolation.

Hostinger staging acceptance is required before exposing public report forms. Authorized moderators may still process pre-existing reports while new submissions are disabled.

## Automated coverage

`tests/run-phase3e-reports-tests.php` covers:

- default feature gating;
- authenticated post reporting;
- generic public response secrecy;
- duplicate idempotency;
- concurrent insert recovery;
- Other-reason note requirement;
- invalid reason and object type rejection;
- self-report prevention;
- pending-post rejection;
- forged reporter ID rejection;
- approved comment reporting;
- pending, foreign-type, deleted, and self-comment rejection;
- five-per-hour rate limit;
- ordinary-user queue denial;
- moderator queue access and filtering;
- no hash or email serialization;
- private moderator notes;
- valid and invalid state transitions;
- cross-user moderation denial;
- moderator access while submissions are disabled;
- REST route registration, nonce, capability, ID, reason, and pagination validation;
- post and comment report controls;
- visitor login state without nonce leakage;
- preservation of plugin and schema version `1.0.0`;
- audit record creation.

The complete GitHub Actions workflow also validates all Phase 2, 3.0, 3A, 3B, 3C, and 3D suites, PHP syntax, JavaScript syntax, CSS/JSON/security/whitespace checks, artifact build, ZIP structure, and SHA-256 integrity.

## Deliberately deferred

Checkpoint 3E does not implement:

- public reporter identities;
- public report counts or details;
- automatic content removal;
- user blocking UI;
- polls;
- notifications;
- view analytics;
- followers-only post visibility;
- Phase 4 Editorial News;
- production deployment;
- merge to `main`.

## Next permitted checkpoint

Checkpoint 3F — Polls:

- bounded poll options;
- authenticated vote and vote replacement;
- one active vote identity per user/poll;
- results policy: after vote, after close, or always;
- close time and manual close rules;
- aggregate results without voter identity disclosure;
- accessible voting interface;
- duplicate, race, IDOR, privacy, and two-user tests.
