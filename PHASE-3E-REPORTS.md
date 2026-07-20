# Phase 3E — Reports and Moderation Queue

Target Phase 3 release: `1.1.0`

Integration branch: `build/phase-3-social-interactions-1.1.0`

Status: implementation and automated validation complete on the integration branch. New public report submission remains disabled by default until Hostinger staging acceptance. The pull request remains Draft and must not be merged.

## Storage model

Checkpoint 3E reuses the accepted plugin-owned `sabri_feed_reports` table and schema version `1.0.0`. Each report contains an authenticated reporter, a `post` or plugin-owned `comment` target, an allow-listed reason and bounded state, a SHA-256 duplicate-control identity, JSON-encoded confidential notes, and UTC timestamps. No new table, schema-version increase, or destructive cleanup is required.

## Public policy

A report submission requires the current WordPress session, a valid REST nonce, `reports_enabled = 1`, Safe Mode clearance, visible approved content owned by another account, an allow-listed reason, and the five-per-user/object/hour limit.

Reasons are spam, harassment, hate or abuse, misinformation, medical safety risk, patient privacy, copyright or missing source, impersonation, and other. `Other` requires a meaningful confidential explanation. Reporter notes are plain text and bounded to 1,000 characters.

Repeated identical submissions are idempotent. Concurrent unique inserts recover through a safe re-read. The public response never reveals whether a report was new or duplicate and exposes no report ID, reporter identity, state, hash, notes, moderator, timestamp, or database detail.

## REST endpoints

Namespace: `sabri-home-news-feed/v1`

- `POST /reports`
- `GET /moderation/reports`
- `PATCH /moderation/reports/{id}`

Public submission requires login, nonce, and the feature flag. Moderator routes require login, nonce, and `sabri_feed_manage_reports` or administrator authority. Existing reports remain manageable while new submissions are disabled. Responses use private no-store cache policy when supported.

## Moderation states

States are `open`, `triaged`, `resolved`, `dismissed`, and `duplicate`.

- Open may remain Open or move to Triaged, Resolved, Dismissed, or Duplicate.
- Triaged may remain Triaged or move to Open, Resolved, Dismissed, or Duplicate.
- Resolved, Dismissed, and Duplicate may remain unchanged or return only to Triaged.

The admin status selector presents only valid transitions. Moderator notes are private, plain text, and bounded to 2,000 characters. Creation and moderation actions write plugin-owned audit events.

## Moderator queue

`Home & News Feed → Reports` is restricted to report managers. It provides filters by state, reason, and content type; bounded pagination; safe object links and excerpts; reporter public display name and ID; confidential reporter and moderator notes; valid transition controls; and confidentiality warnings.

Queue serialization excludes email, phone, login name, roles, capabilities, IP, user-agent, and duplicate hashes.

## Public interface

When enabled on staging, visible posts and approved non-deleted plugin comments provide accessible inline report controls. Own content does not render a self-report form. Visitors receive a sign-in state. Pending, foreign-type, deleted, hidden, and unavailable comments do not receive a report surface. Delegated JavaScript supports Load More content, while JavaScript failure does not affect reading.

## Privacy and feature gate

Privacy export excludes confidential notes. Account erasure anonymizes reporter identity while retaining reports for moderation accountability. Reports and audit details are never public.

`reports_enabled` remains `0` in safe defaults. Tests enable it only in isolation. Hostinger staging acceptance is required before public report forms are exposed; moderators can still process existing records while submissions are disabled.

## Automated coverage

`tests/run-phase3e-reports-tests.php` covers feature gating, authenticated post and comment reports, generic response secrecy, duplicate and race recovery, allow-lists, Other-note validation, self-report, pending/deleted/foreign content, forged reporter identity, rate limiting, moderator-only queue, filters, private notes, valid and invalid transitions, cross-user denial, disabled-submission queue access, REST permissions and validation, post/comment controls, visitor nonce privacy, audit records, and preservation of plugin/schema version `1.0.0`.

The complete workflow also validates Phase 2 and checkpoints 3.0–3D, PHP and JavaScript syntax, static security, CSS/JSON/whitespace, artifact build, ZIP structure, and SHA-256 integrity.

## Deliberately deferred

No public reporter identity, report counts, automatic content removal, blocking UI, notifications, view analytics, followers-only visibility, Phase 4 Editorial News, production deployment, or merge to `main` is included.

## Subsequent checkpoint

Checkpoint 3F — Polls was subsequently implemented and is documented in `PHASE-3F-POLLS.md`.
