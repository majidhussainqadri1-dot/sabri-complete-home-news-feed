# Phase 4 Hostinger Staging Acceptance Checklist — Editorial News and Global Newsroom

Target development line: `1.2.0`

Environment: **Hostinger native staging only**

Live site: **must remain unchanged during this checklist**

## Operating rules

- Confirm the browser address is the Hostinger staging domain before every write action.
- Create and verify a fresh full backup before installation, upgrade, feature-gate, migration, repair, or rollback testing.
- Test the exact 40-character commit SHA and exact packaged artifact digest.
- Use clearly labelled staging accounts and non-sensitive test content.
- Never use real patient records, phone numbers, identity documents, prescriptions, or confidential source identities.
- Enable one Phase 4 feature gate at a time, then test the approved combined matrix.
- Keep Emergency Disable and rollback instructions immediately accessible.
- Stop testing after any fatal error, inaccessible dashboard, destructive migration, privacy leak, private-route leak, cache leak, or unexplained data loss.
- Do not merge, deploy live, promote the plugin version, or delete rollback evidence merely because this checklist passes.

## Evidence record

Record before testing:

- Staging URL:
- WordPress administrator tester:
- Acceptance reviewer:
- Test start UTC:
- Test end UTC:
- Exact branch:
- Exact 40-character commit SHA:
- Artifact filename:
- Artifact SHA-256:
- Plugin version before installation:
- Plugin version under test:
- Schema version before installation:
- Schema version under test:
- Backup identifier and creation UTC:
- Backup components included:
- Release-readiness/checklist hash:
- Browser/device matrix:
- Accessibility tools used:
- Evidence location:

## 1. Environment and backup

- [ ] Active tab is Hostinger staging, not live.
- [ ] Staging is a current clone of the intended baseline.
- [ ] Full backup includes database, plugins, themes, uploads, and WordPress configuration.
- [ ] Backup is identifiable and selectable for restoration.
- [ ] WordPress Site Health and existing critical errors are recorded.
- [ ] PHP, WordPress, database, and browser requirements are met.
- [ ] Existing Phase 2 and Phase 3 behavior works before Phase 4 installation.

Checklist key: `phase4_environment_backup`

## 2. Clean installation

Perform only on a disposable staging clone or after preserving the upgrade-test clone.

- [ ] Exact package installs without fatal error or white screen.
- [ ] Plugin activation succeeds.
- [ ] Editorial post type, taxonomies, roles/capabilities, options, and required tables are present.
- [ ] Required indexes and constraints are present.
- [ ] No unrelated WordPress table or role is destructively modified.
- [ ] All Phase 4 feature gates default to disabled.
- [ ] Dashboard and public site remain accessible.

Checklist key: `phase4_clean_install`

## 3. Upgrade installation

- [ ] Start from the accepted pre-Phase-4 plugin state with staging content preserved.
- [ ] Install the exact tested artifact over the existing installation.
- [ ] Additive migration completes without deleting or rewriting unrelated data.
- [ ] Existing posts, media, settings, social interactions, and Phase 2/3 tables remain present.
- [ ] Schema audit reports expected Phase 4 additions only.
- [ ] Repeated activation/upgrade is idempotent.

Checklist key: `phase4_upgrade_install`

## 4. Phase 2 regression

- [ ] Home Feed renders permitted real posts.
- [ ] Latest/category filters work.
- [ ] Load More does not duplicate or skip ordinary posts.
- [ ] Read More opens the correct single post.
- [ ] Composer role policy remains enforced.
- [ ] Clinical Case privacy validation remains active.
- [ ] Existing media and attachments render correctly.

Checklist key: `phase4_phase2_regression`

## 5. Phase 3 regression

- [ ] Reactions and public counts follow existing gates.
- [ ] Private Saves remain isolated by account.
- [ ] Comments, replies, moderation, edit window, and soft deletion still work.
- [ ] Follow/Following and private Following state remain isolated.
- [ ] Reports remain confidential.
- [ ] Polls remain bounded and aggregate-only.
- [ ] Notification and privacy-safe View services retain their existing policies.
- [ ] Emergency Disable still closes Phase 3 writes as designed.

Checklist key: `phase4_phase3_regression`

## 6. Content model, roles, and capabilities

- [ ] `sabri_news` and all frozen taxonomies are registered correctly.
- [ ] Frozen sections and article types validate and unknown values fail closed.
- [ ] Administrator, Founder, Editor-in-Chief, Managing Editor, Section Editor, Medical Reviewer, Reporter, Verified Doctor Submitter, Translator, and Reader matrix is tested.
- [ ] Reporter/submitter/translator cannot self-publish.
- [ ] Section-scoped authority cannot escape its assigned section.
- [ ] Submitted user IDs cannot impersonate another actor.
- [ ] Existing WordPress roles are not destructively replaced.

Checklist key: `phase4_roles_content_model`

## 7. Editorial workflow and composer

- [ ] Allowed article-state transitions work.
- [ ] Invalid transition, missing prerequisite, and stale transition fail closed.
- [ ] Required fields produce field-specific errors.
- [ ] Autosave/revisions do not publish incomplete content.
- [ ] Duplicate headline/source warnings work according to policy.
- [ ] Article HTML, embeds, taxonomies, and field limits are enforced.
- [ ] Editor assignment and separation of duties are respected.
- [ ] Publication records the responsible editor and exact revision.

Checklist key: `phase4_workflow_composer`

## 8. Sources and fact checking

- [ ] Source add/edit/remove respects article ownership and capability.
- [ ] Duplicate normalized URLs are rejected or reused idempotently.
- [ ] Rejected/unverified sources cannot satisfy publication requirements.
- [ ] Public and private source projections are separate.
- [ ] Fact-check checklist requires a decision for every applicable item.
- [ ] Commercial conflicts and press-release origin are recorded.
- [ ] Another article's source ID cannot be reused through IDOR.

Checklist key: `phase4_sources_factcheck`

## 9. Medical review and clinical privacy

- [ ] Medical/scientific claims trigger review according to policy.
- [ ] Reviewer can act only within assigned authority.
- [ ] Headline and article wording preserve evidence type and limitations.
- [ ] Email, phone/WhatsApp, CNIC, passport, patient name, address, medical-record number, and identifier-bearing media are blocked or held safely.
- [ ] Scanner errors return categories only and do not echo identifiers.
- [ ] Required disclaimer appears.
- [ ] Emergency or individualized-care content is rejected or corrected.

Checklist key: `phase4_medical_privacy`

## 10. Submissions

- [ ] Verified Doctor/Contributor can submit when gate is enabled.
- [ ] Submission states follow the frozen state machine.
- [ ] Submitter sees only their own safe projection.
- [ ] Internal notes and confidential source information do not leak.
- [ ] Attachments obey MIME, extension, size, rights, and privacy rules.
- [ ] Accepted submission converts to at most one linked News draft.
- [ ] Rejection and request-information reasons are safe.

Checklist key: `phase4_submissions`

## 11. Public routes and Home Feed integration

- [ ] `/news/` opens the News archive.
- [ ] Single, section, topic, country, region, and type routes are canonical.
- [ ] Draft/private objects return safe not-found/forbidden behavior without enumeration.
- [ ] News cards use globally distinct item keys.
- [ ] Load More does not duplicate or skip News cards.
- [ ] Read Full Story opens the canonical single News route.
- [ ] Full article body is not duplicated in the Home Feed.
- [ ] Disabled News gate removes News cards without damaging ordinary feed data.

Checklist key: `phase4_public_routes_feed`

## 12. Search, filters, and cache isolation

- [ ] Keyword, section, topic, country, region, type, date, author, research, corrected, and retracted-notice filters work within bounds.
- [ ] Invalid filters do not widen queries to private states.
- [ ] Drafts, previews, submissions, queues, private sources, and notes never appear in search or archives.
- [ ] Public cache keys include language, page/filter, gate, correction, and retraction dimensions.
- [ ] Private/current-user responses use no-store/private behavior.
- [ ] Publication, correction, retraction, taxonomy, breaking, and gate changes invalidate relevant caches.

Checklist key: `phase4_search_cache`

## 13. Breaking News and scheduling

- [ ] Only authorized roles can set Breaking News.
- [ ] Start, expiry, priority, and active-story limit are enforced.
- [ ] Expired Breaking status disappears even when cron cleanup is delayed.
- [ ] Breaking presentation is keyboard accessible and honors reduced motion.
- [ ] Scheduled publication stores UTC and displays site timezone correctly.
- [ ] Duplicate cron execution does not publish twice.
- [ ] Revoked prerequisites prevent stale scheduled publication.
- [ ] Missed/failed schedule creates a safe diagnostic and audit record.

Checklist key: `phase4_breaking_scheduling`

## 14. Corrections and retractions

- [ ] Minor corrections retain revision history.
- [ ] Material correction requires public note, reason, approver, and revision linkage.
- [ ] Retraction preserves accountability notice while hiding unsafe/unreliable body according to policy.
- [ ] Retracted articles leave normal promotion and related recommendations.
- [ ] Public article, Home Feed card, schema, RSS, sitemap, and caches agree after correction/retraction.
- [ ] Another article's correction or revision ID cannot be attached.
- [ ] Silent material rewriting is not possible through approved UI/API.

Checklist key: `phase4_corrections_retractions`

## 15. SEO, schema, sitemap, and RSS

- [ ] Canonical URLs are stable.
- [ ] Visible disclosure label matches schema and social metadata.
- [ ] `NewsArticle`/`Article`, Breadcrumb, Organization/Person, and ImageObject output only visible public facts.
- [ ] `/news-sitemap.xml` includes only public indexable articles/translations.
- [ ] `/news/feed/` and section feeds exclude private/current-user data.
- [ ] Drafts, previews, queues, unpublished translations, and hidden retracted bodies are noindex and excluded.
- [ ] Correction/retraction dates and notices match structured output.

Checklist key: `phase4_seo_distribution`

## 16. Multilingual and translation foundation

- [ ] Interface strings are translation-ready and initial interface remains American English.
- [ ] Translation draft links to the correct source article.
- [ ] Automatic translation cannot self-publish.
- [ ] Translator cannot alter source-language article.
- [ ] Medical terminology review is required where applicable.
- [ ] Published translations receive correct canonical/hreflang relationships.
- [ ] RTL layout remains usable.

Checklist key: `phase4_translation`

## 17. Accessibility

Test without a mouse and with representative assistive technology.

- [ ] Logical Tab order across archive, article, Breaking strip, forms, queues, tables, and composer.
- [ ] Visible focus on every control.
- [ ] Semantic headings, landmarks, labels, captions, legends, and error relationships.
- [ ] Live status regions do not over-announce or steal focus.
- [ ] No keyboard trap.
- [ ] 320 CSS pixel viewport retains essential functionality.
- [ ] 200% zoom remains usable.
- [ ] Long titles, translations, counts, source lists, and notices do not break layout.
- [ ] Reduced-motion preference removes non-essential motion.
- [ ] Images have meaningful alt text or correct decorative treatment.

Checklist key: `phase4_accessibility`

## 18. Performance and security hardening

- [ ] Phase 4 assets load only where required.
- [ ] Public/admin lists are bounded and paginated.
- [ ] No N+1 source/author/taxonomy queries.
- [ ] XSS, malicious URL, SQL metacharacter, unsafe redirect, mass assignment, malformed ID, and IDOR tests fail safely.
- [ ] MIME/extension mismatch, executable/script, oversized file, and SVG uploads are rejected under policy.
- [ ] Race and duplicate requests do not create uncontrolled records.
- [ ] One-hour soak QA completes without fatal error, uncontrolled growth, or privacy leak.

Checklist key: `phase4_security_performance`

## 19. Privacy export, erasure, notifications, analytics, and Emergency Disable

- [ ] User export contains only the requester's permitted Phase 4 data.
- [ ] Erasure/anonymization preserves public accountability and referential integrity.
- [ ] Notification payload excludes private notes, source contacts, patient data, tokens, IP, user agent, and unpublished body.
- [ ] Notification failure does not roll back the primary operation.
- [ ] Analytics stores no raw IP/full user agent and exposes aggregates only.
- [ ] Emergency Disable immediately closes Phase 4 public writes and configured public surfaces.
- [ ] Data and administrator recovery access remain present.
- [ ] Re-enable restores only previously configured gates.

Checklist key: `phase4_privacy_emergency`

## 20. Rollback restoration and acceptance record

Follow `PHASE-4-ROLLBACK-RUNBOOK.md` on staging.

- [ ] Pre-test known-good plugin files are restored.
- [ ] Database backup is restored when the tested scenario requires it.
- [ ] Dashboard and public staging site remain accessible.
- [ ] Phase 2 and Phase 3 regression checks pass after rollback.
- [ ] Phase 4 data preservation/compatibility matches the runbook.
- [ ] No unrelated posts, users, media, settings, or tables are deleted.
- [ ] Rollback start/end UTC, operator, backup ID, commit SHA, and evidence are recorded.

Checklist key: `phase4_rollback_acceptance`

## Final acceptance record

Do not complete until all 20 checklist keys pass.

- Accepted: yes/no
- Accepted by WordPress user ID:
- Accepted by name:
- Acceptance UTC:
- Exact tested 40-character commit SHA:
- Artifact SHA-256:
- Checklist/release-readiness hash:
- Completed checklist keys: all 20 / incomplete
- Backup verified: yes/no
- Rollback verified: yes/no
- Open critical defects:
- Open high defects:
- Residual risks:
- Feature gates accepted for staging:
- Evidence location:
- Live deployment authorized: **no by default**

Completion of this checklist does not automatically merge a pull request, promote the plugin version, or deploy to the live site.