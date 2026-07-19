# Hostinger Staging Acceptance Checklist — Phase 3

Target release: `1.1.0`

Environment: Hostinger native staging only

Live site: **must remain unchanged during this checklist**

Pull request: Draft PR #2

## Operating rules

- Confirm the browser address is the Hostinger staging domain before every write action.
- Create and verify a fresh full backup before installing or changing feature flags.
- Record the exact Git commit SHA being tested.
- Do not use fake production-facing posts, patients, identities, phone numbers, or medical records.
- Use clearly labelled staging test accounts and non-sensitive test content.
- Test one feature gate at a time, then test the approved combined matrix.
- Keep Emergency Disable immediately accessible.
- Stop testing and use the rollback runbook after any fatal error, inaccessible dashboard, database error, route leak, privacy leak, or destructive behavior.
- Do not merge, deploy live, or promote the version during this checklist.

## Evidence record

Record these before testing:

- Staging URL:
- WordPress administrator tester:
- Test start UTC:
- Exact branch:
- Exact 40-character commit SHA:
- Plugin version shown before install:
- Schema version shown before install:
- Backup identifier and creation time:
- Checklist hash reported by `ReleaseReadiness`:

Screenshots, exported test logs, and concise observations should be retained with the acceptance record.

## A. Environment and backup

- [ ] The active browser tab is the Hostinger staging site, not the live domain.
- [ ] Staging is a current clone of the intended live baseline.
- [ ] A complete backup exists for database, plugins, themes, uploads, and WordPress configuration.
- [ ] The backup can be identified and selected for restoration.
- [ ] WordPress Site Health has been reviewed for pre-existing critical errors.
- [ ] PHP and WordPress meet the plugin minimum requirements.
- [ ] Existing Phase 2 Home Feed and Composer work before installation.
- [ ] Read More opens the correct single post and does not return to the Home Feed.

Checklist key: `backup_verified`

## B. Clean installation path

Perform this only on a disposable staging clone or after preserving the upgrade-test clone.

- [ ] Install the tested plugin package on a staging environment without an existing plugin installation.
- [ ] Activation completes without a fatal error or white screen.
- [ ] All seven plugin-owned social tables are present.
- [ ] Required indexes and unique constraints are present.
- [ ] No unrelated WordPress tables are modified or deleted.
- [ ] Dashboard and public staging site remain accessible.
- [ ] All unaccepted Phase 3 feature gates remain disabled after activation.

Checklist key: `clean_install`

## C. Upgrade installation path

- [ ] Start from the accepted `1.0.0` plugin state with real staging content preserved.
- [ ] Install the exact tested branch artifact over the existing installation.
- [ ] Activation and schema verification complete without destructive migration.
- [ ] Existing posts, media, settings, reactions, saves, and Phase 2 content remain present.
- [ ] Existing Home Feed ordering, filters, Load More, Composer, moderation, and single-post routes remain functional.
- [ ] No plugin-owned table is dropped or recreated destructively.

Checklist key: `upgrade_install`

## D. Phase 2 regression

Test before enabling any new Phase 3 flag:

- [ ] Home Feed renders real posts only.
- [ ] Latest and category filters work.
- [ ] Load More does not duplicate or skip posts.
- [ ] Read More opens the selected single post.
- [ ] Public visitors see only permitted content.
- [ ] Doctors can access Composer according to role policy.
- [ ] Students and patients cannot publish general posts.
- [ ] Clinical Case privacy validation still blocks direct identifiers.
- [ ] Research evidence controls remain intact.
- [ ] Existing media and attachments render correctly.

Checklist key: `phase2_regression`

## E. Role and access matrix

Use at least these staging accounts: administrator, founder, verified doctor, unverified doctor, student, patient/subscriber, and logged-out visitor.

- [ ] Administrator retains settings, moderation, reports, diagnostics, backup, and Emergency Disable access.
- [ ] Founder publishing policy works as configured.
- [ ] Verified doctor policy works as configured.
- [ ] Unverified doctor submissions remain pending where required.
- [ ] Students and patients can read and interact only within their permissions.
- [ ] A request cannot impersonate another user by supplying another user ID.
- [ ] Private lists and current-user states never leak across two logged-in test accounts.

Checklist key: `role_access_matrix`

## F. Reactions and private Saves

Enable only the required reaction/save flags.

- [ ] Like toggles on and off.
- [ ] Dislike toggles on and off when enabled.
- [ ] Like switches to Dislike without duplicate active rows.
- [ ] Public counts update correctly.
- [ ] Save and Unsave work.
- [ ] Saved Posts are visible only to the saving account.
- [ ] Two users retain isolated reaction and save states.
- [ ] Rapid repeated clicks do not create duplicate records.
- [ ] Logged-out interaction redirects to sign-in without losing the destination.

Checklist key: `reactions_saves`

## G. Comments and replies

Enable Comments only on staging.

- [ ] Logged-in user can submit a comment.
- [ ] New comment moderation policy is respected.
- [ ] Pending comment is visible only to its author and moderators.
- [ ] Approved comment becomes public on permitted content.
- [ ] Reply nesting stops at the configured maximum depth.
- [ ] Comment edit window is enforced.
- [ ] Soft deletion removes the text but preserves thread structure.
- [ ] Clinical Case comment scanner blocks direct patient identifiers.
- [ ] Cross-post and deleted-parent replies are rejected.
- [ ] Keyboard focus and live status messages remain clear.

Checklist key: `comments_replies`

## H. Follow, Following, and Followers-only visibility

Enable Follows first, then Followers-only visibility.

- [ ] Follow and Unfollow work for two different accounts.
- [ ] Self-follow is rejected.
- [ ] Blocked relationships are not silently reactivated.
- [ ] Following list is private to the current account.
- [ ] Public follower counts remain hidden unless explicitly enabled.
- [ ] Composer offers Followers visibility only while the gate is enabled.
- [ ] A Followers-only post is visible to its author.
- [ ] A verified active follower can see it in Feed, direct post, and REST-derived surfaces.
- [ ] A non-follower cannot see it in Feed, direct post, REST, search/archive output, related posts, or cached output.
- [ ] A removed or blocked follower loses access after cache invalidation.
- [ ] Logged-out visitors cannot see Followers-only content.
- [ ] Emergency Disable closes the feature without deleting content.

Checklist keys: `follows`, `followers_visibility`, `cache_invalidation`

## I. Reports and moderation

Enable Reports only on staging.

- [ ] Eligible post can be reported.
- [ ] Eligible approved comment can be reported.
- [ ] Self-report is rejected or hidden.
- [ ] Pending, deleted, hidden, and foreign-type objects cannot be reported.
- [ ] Duplicate requests do not create uncontrolled rows.
- [ ] Public response reveals no report ID, reporter identity, hash, private note, or status.
- [ ] Only authorized moderators can open the Reports queue.
- [ ] Filters and pagination work.
- [ ] Only permitted status transitions are offered and accepted.
- [ ] Moderator notes remain private.
- [ ] Reporting does not remove content automatically.

Checklist key: `reports_moderation`

## J. Polls

Enable Polls only on staging.

- [ ] Composer requires 2–8 distinct options.
- [ ] Duplicate and excessive options are rejected, not silently truncated.
- [ ] Authenticated user can vote once.
- [ ] Repeated identical vote is idempotent.
- [ ] Vote replacement/removal follows the Poll policy.
- [ ] Closed Poll rejects mutations.
- [ ] Results obey After Vote, After Close, and Always policies.
- [ ] Hidden results are distinct from zero votes.
- [ ] Results expose aggregate counts only.
- [ ] Poll definition cannot be changed after voting begins.

Checklist key: `polls`

## K. Notification Bridge

Connect only a staging-safe notification callback.

- [ ] Like/Dislike notifies the post author.
- [ ] Approved top-level comment notifies the post author.
- [ ] Approved reply notifies the parent-comment author.
- [ ] Newly active Follow notifies the followed account.
- [ ] Newly active Poll Vote notifies the Poll author.
- [ ] Self-notifications are suppressed.
- [ ] Duplicate events are suppressed.
- [ ] Saves, removals, Unfollow, Reports, edits, and Poll choice details do not generate unintended payloads.
- [ ] Payload contains no email, phone, WhatsApp, content, report note, Poll choice, IP, or user agent.
- [ ] Callback failure does not roll back the original interaction.

Checklist key: `notification_bridge`

## L. View logging

Enable view logging only on staging.

- [ ] Direct visible single-post request records one view.
- [ ] Repeated request inside the configured window is deduplicated.
- [ ] Different authenticated users are counted independently.
- [ ] Guest identity is hashed and raw IP/user agent are not stored.
- [ ] Do Not Track request is ignored.
- [ ] Obvious bot/crawler request is ignored.
- [ ] Preview, Feed, admin, robots, and trackback requests are ignored.
- [ ] Hidden or pending post cannot receive a public view record.
- [ ] Public UI shows aggregate Views only.
- [ ] Privacy export and erasure behave as documented.

Checklist key: `view_logging`

## M. Keyboard and screen-reader testing

Test without a mouse.

- [ ] Logical Tab order across Feed, Composer, comments, Polls, reports, and Load More.
- [ ] Visible focus indicator on every interactive control.
- [ ] Enter/Space activates native buttons as expected.
- [ ] Toggle state is announced through `aria-pressed` or equivalent native state.
- [ ] Comment, report, Poll, and Composer status changes are announced.
- [ ] Form labels and error relationships are understandable.
- [ ] Poll fieldset and legend are announced correctly.
- [ ] Details/Summary Report control is usable.
- [ ] No keyboard trap exists.
- [ ] Dynamic content receives sensible focus behavior without forced focus theft.

Checklist keys: `keyboard_navigation`, `screen_reader_labels`

## N. Responsive, zoom, contrast, and motion

Test desktop and representative mobile widths.

- [ ] 320 CSS pixel viewport has no essential horizontal scrolling.
- [ ] 200% browser zoom remains usable.
- [ ] Buttons and inputs have practical touch targets.
- [ ] Nested replies remain readable on mobile.
- [ ] Poll results and action bars do not overflow.
- [ ] Text/background and focus indicator contrast are acceptable.
- [ ] Reduced-motion preference removes non-essential motion.
- [ ] Long names, translated strings, and large counts do not break layout.

Checklist key: `mobile_responsive`

## O. Privacy export and erasure

- [ ] Save export belongs only to the requesting account.
- [ ] Following export belongs only to the requesting account.
- [ ] Report export omits moderator-confidential data.
- [ ] Poll vote export reveals only the requester’s own choice.
- [ ] Authenticated view history exports only to its account.
- [ ] Erasure removes or anonymizes identity according to each data contract.
- [ ] Erasure does not delete posts or unrelated accounts.
- [ ] Multiple erased view rows do not collide under unique indexes.

Checklist key: `privacy_export_erasure`

## P. Safe Mode and Emergency Disable

- [ ] Safe Mode requires administrator authority.
- [ ] Emergency Disable can be activated from staging admin.
- [ ] Public Phase 3 writes close immediately.
- [ ] Followers-only access fails closed.
- [ ] Existing data remains present.
- [ ] Moderator/admin recovery access remains available.
- [ ] Re-enable restores only the previously configured gates.
- [ ] Audit record is created.

Checklist key: `safe_mode_emergency_disable`

## Q. Rollback restoration

Follow `PHASE-3-ROLLBACK-RUNBOOK.md`.

- [ ] Trigger rollback on a staging clone, not live.
- [ ] Restore the pre-test plugin files or known-good package.
- [ ] Restore the verified database backup when the rollback scenario requires it.
- [ ] Confirm dashboard and public staging site accessibility.
- [ ] Confirm Phase 2 Feed, Composer, Read More, posts, media, and settings.
- [ ] Confirm no posts or plugin tables were unintentionally deleted.
- [ ] Record rollback start/end UTC and evidence.

Checklist key: `rollback_restore`

## Final acceptance record

Do not complete this section until every checkbox above passes.

- Accepted: yes/no
- Accepted by WordPress user ID:
- Accepted by name:
- Acceptance UTC:
- Exact tested 40-character commit SHA:
- Checklist hash:
- Completed checklist keys: all 20 required keys
- Backup verified: yes/no
- Rollback verified: yes/no
- Open defects:
- Residual risks:
- Screenshots/evidence location:

The acceptance record must match the `ReleaseReadiness` contract. Completion of this document does not merge the PR, deploy live, or promote the plugin version automatically.
