# Phase 3F — Polls

Target Phase 3 release: `1.1.0`

Integration branch: `build/phase-3-social-interactions-1.1.0`

Status: implementation and automated validation complete on the integration branch. `polls_enabled` remains disabled by default until Hostinger staging acceptance. The pull request remains Draft and must not be merged.

## Storage model

Checkpoint 3F reuses the accepted plugin-owned `sabri_feed_poll_votes` table and schema version `1.0.0`.

Poll definitions are stored as sanitized post metadata under `_sabri_hnf_poll`. A definition contains only:

- a plain-text question;
- two to eight distinct plain-text options;
- stable bounded option keys;
- results policy: `after_vote`, `after_close`, or `always`;
- optional UTC closing time;
- whether an authenticated member may change or remove a vote while the Poll is open;
- the frozen vote group key `default`.

Vote rows contain the Poll post ID, selected option key, authenticated user ID, empty authenticated anonymous hash, group key, bounded status, and timestamps. No email, phone, IP address, user-agent, display name, or free-text answer is stored with a vote.

No new table, destructive migration, or schema-version increase is required.

## Poll creation

Poll is available as a Composer feed type only while `polls_enabled = 1`.

Composer validation requires:

- an authorized post creator;
- a non-empty question, maximum 240 characters;
- two to eight non-empty, distinct options;
- maximum 120 characters per option;
- an allow-listed results policy;
- a valid future UTC closing time when one is supplied.

Duplicate options and more than eight supplied options fail validation before normalization. An unchecked vote-change setting is stored as disabled rather than silently defaulting to enabled.

After the first active vote, the Poll definition is locked. A closed Poll definition cannot be edited. Non-Poll posts have stale Poll metadata removed.

## Voting policy

A vote mutation requires:

- `polls_enabled = 1`;
- current WordPress session authentication;
- a valid REST nonce;
- a visible, published, approved Poll post;
- a valid option key belonging to that Poll;
- an open Poll;
- the bounded `poll_votes` rate limit: 20 actions per user/Poll per 10 minutes.

The natural vote identity is one row per Poll, authenticated user, empty authenticated anonymous hash, and vote group.

Behavior:

- the first vote creates one active row;
- repeating the same vote is idempotent;
- changing the selected option updates the same row when change is allowed;
- removing the vote marks the same row removed when change is allowed;
- a removed row may be reactivated while the Poll remains open;
- when change is disabled, replacement and removal are both rejected;
- all vote mutations are rejected after closing;
- concurrent unique inserts recover through a safe natural-key re-read and bounded update;
- forged user IDs, pending posts, hidden posts, non-Poll posts, malformed IDs, and unknown options fail closed.

## Results policy

Public results are aggregate-only. No route or rendered surface returns voter identities or raw vote rows.

- `after_vote`: aggregate counts are shown only to a current authenticated voter.
- `after_close`: aggregate counts are shown only after the UTC close time.
- `always`: aggregate counts are visible whenever the Poll post itself is visible.

When results are hidden, counts and total votes are returned as `null`, not zero. This prevents a hidden-results policy from being confused with a Poll that has no votes.

When visible, each option includes only:

- option key;
- option label;
- whether it is the current user’s selection;
- aggregate count;
- aggregate percentage.

The current user’s selected option is private per-user state and is never exposed for another account.

## REST endpoints

Namespace: `sabri-home-news-feed/v1`

- `POST /polls/{id}/vote`
- `DELETE /polls/{id}/vote`
- `GET /polls/{id}/results`

POST and DELETE require login, nonce, feature gate, centralized post authorization, strict ID and option validation, and rate limiting. GET requires a visible valid Poll and follows the results policy. Responses are no-store/private when supported.

## Accessible interface

Visible Polls render in both Feed cards and direct single posts.

The progressively enhanced interface provides:

- semantic fieldset and radio controls;
- keyboard-accessible Vote, Update Vote, and Remove Vote actions;
- open, closed, and UTC close-time states;
- selected-option state;
- aggregate bars, counts, and percentages only when policy permits;
- sign-in state without nonce leakage for visitors;
- final-vote notice when replacement/removal is disabled;
- live status announcements;
- responsive mobile layout;
- reduced-motion support;
- delegated JavaScript compatible with Load More Feed cards;
- restoration of originally disabled controls after failed requests.

JavaScript failure does not prevent reading the Poll definition or its policy-permitted aggregate results.

## Privacy lifecycle

Poll choices are personal data.

The WordPress privacy exporter includes only the requesting user’s Poll post ID, option key, status, and creation time.

Account erasure:

- removes the active user identity from matching Poll vote rows;
- replaces it with a deterministic SHA-256 erased-vote hash;
- marks the vote removed;
- retains no active public contribution to aggregate results;
- does not delete the Poll post or other WordPress content.

Public results remain aggregate-only throughout the lifecycle.

## Automated coverage

`tests/run-phase3f-polls-tests.php` covers:

- default-disabled feature gate;
- question and option cardinality validation;
- duplicate and excess option rejection;
- future close-time validation;
- disabled and enabled Composer behavior;
- persisted Poll metadata and feed type;
- authenticated vote creation;
- idempotent repeated vote;
- two-user aggregate counts;
- voter-identity secrecy;
- replacement and removal;
- final-vote replacement/removal denial;
- `after_vote`, `after_close`, and `always` policies;
- closed-Poll mutation denial;
- invalid option, pending Poll, non-Poll, and forged-user denial;
- concurrent insert recovery;
- per-user/per-Poll rate limiting;
- definition lock after voting begins;
- closed-definition immutability;
- REST route registration, nonce permissions, and strict validators;
- visitor nonce and account-data secrecy;
- Feed card integration;
- privacy export and erasure;
- preservation of plugin and schema version `1.0.0`.

The complete workflow also validates Phase 2 and checkpoints 3.0–3E, PHP and JavaScript syntax, static security, CSS/JSON/whitespace, artifact build, ZIP structure, and SHA-256 integrity.

## Deliberately deferred

Checkpoint 3F does not include:

- anonymous voting;
- multiple-choice Polls;
- free-text Poll answers;
- public voter lists;
- public per-user vote history;
- automatic notifications;
- view logging or analytics;
- followers-only visibility;
- Phase 4 Editorial News;
- production deployment;
- merge to `main`.

## Next planning gate

Before another runtime checkpoint begins, the remaining frozen flags—`notification_bridge_enabled`, `view_logging_enabled`, and `followers_visibility_enabled`—must be reconciled with the approved Phase 3 plan and assigned to bounded, testable checkpoints. No feature name or deployment sequence should be invented without that plan verification.
