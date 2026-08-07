# File 21 — Four-Plan Audit and Corrective Register

**Date:** 2026-08-07 — Pakistan Standard Time  
**Corrective package:** 1.0.5  
**Stable runtime/API:** 1.0.3  
**Schema:** 1.0.0 — unchanged; no database migration introduced by this correction  
**Canonical module owner:** File 21 — Home, social posts, Editorial News, publication lifecycle, local Feed policy/interactions/comments and permanent publication routes.

## Governing corpus and precedence

This review treats the following four documents as the governing corpus, while preserving the platform rule that a later explicit Founder amendment prevails over conflicting historical text:

1. **Definitive Integrated Master Plan v3.0 / consolidated governing central plan** — File 21 ownership, fourteen Home controls, publication/review law, canonical cross-file boundaries, seven release statuses, security/privacy and release gates.
2. **Recovered Founder Directives v2.1/v2.2 current amendments** — central green identity, one complete top navigation, RTL/right-priority, 44×44 minimum targets, no donor advantage, zero-known-defect review/fix doctrine and staging/rollback evidence requirements.
3. **Continuous Value / Global Top-20 Superset v1.0** — user agency, explainable feeds, Following/chronological choice, healthy-use controls, saved collections, social/comment interaction maturity, manipulation resistance and File 26 global Search/Discovery/Recommendations/Ranking ownership.
4. **File 21 Final Encyclopedic Master Plan** — File 21's exact domain model, Home/News architecture, 14-control bar, ten base discovery rows, social + Editorial News separation, Feed/comment/save/follow contracts, migration, security, accessibility, package and release evidence.

**Applied precedence:** definitive Islamic/safety constraint and latest explicit Founder decision → latest approved central amendment/directive → dedicated File 21 requirement where non-conflicting → verified implementation evidence. Historical orange branding, Founder-favoring ranking or duplicate ownership cannot survive a later governing rule merely because an older code path contains them.

---

## Review Round 1 — Constitution, canonical ownership, business law and brand

### Defects found

1. **R1-D01 — High:** Home composition CSS still contained the superseded orange primary accent while current governance makes green the central identity.
2. **R1-D02 — High:** local Feed ranking still granted a Founder organic score bonus / retained `founder_priority`, conflicting with the later rule that Founder status, donation, payment and paid promotion must not buy organic rank.
3. **R1-D03 — High:** File 21's historical local follow service could remain the relationship writer even when File 17 is installed, conflicting with File 17's canonical relationship/block ownership.
4. **R1-D04 — Medium:** no executable 1.0.5 four-plan acceptance contract tied the new central/Top-20 requirements to exact source/package evidence.

### Corrections applied

- Replaced Home orange accents with governing green and forced effective settings to `#1f7a55`.
- Removed Founder organic ranking advantage; Founder identity remains publication authority only. Verified-doctor authority is contextually bounded to the explicit Doctors feed.
- Added `NetworkRelationshipBridge`: it consumes File 17 `SN_Relationships` state/list/follow/unfollow contracts when available, does not query File 17 tables, and leaves File 21's historical follow store only as a fail-soft compatibility fallback for installations not yet cut over.
- Added executable `tests/run-file21-four-plan-audit-1.0.5.php` and this corrective register.

**Round 1 disposition:** corrected; no known repository-owned current-wave governance/ownership blocker remains.

---

## Review Round 2 — File 21 functional completeness, data and user journeys

### Defects found

1. **R2-D01 — High:** a historical Following list existed, but there was no actual **Following Feed** user-choice surface.
2. **R2-D02 — High:** the canonical saves table already had `collection_key`, but File 21 lacked a complete folders/collections + tags + private notes + bounded export workflow/API.
3. **R2-D03 — Medium:** the Feed did not expose a natural stopping point / healthy-session boundary.
4. **R2-D04 — Medium:** comment threads supported reply/edit/delete but lacked deterministic user-selected chronological sorting, visible parent/reply context, textual mention metadata and collapsible deeper threads required by the current-wave Top-20 interaction inventory.

### Corrections applied

- Added auxiliary **Following / Latest / For You** feed choices without changing the canonical fourteen Home controls. Following consumes File 17 relationship truth when available.
- Added `SavedCollectionService` on the existing File 21 saves truth: named collections, private notes, tags, visibility-safe listing, bounded export and audit. No second bookmark database was introduced.
- Added a visible natural stopping-point message after each page rather than endless engagement pressure.
- Added deterministic Oldest/Newest comment sort, visible safe parent quote context generated only from already-visible comments, non-resolving textual `@mention` tokens, and collapsible deeper reply groups.

**Round 2 disposition:** corrected; no known repository-owned current-wave File 21 functional/data blocker remains.

---

## Review Round 3 — Top-20 continuous value, feed agency, ranking and healthy engagement

### Defects found

1. **R3-D01 — High:** no **“Why am I seeing this?”** explanation existed for local Feed modes.
2. **R3-D02 — High:** no explicit **reduced personalization** control existed.
3. **R3-D03 — High:** no per-card **Not interested / snooze author / hide author / snooze topic / reduce topic** controls existed.
4. **R3-D04 — Medium:** no one-action **Reset Feed preferences** control existed.
5. **R3-D05 — High:** authenticated Feed output could be transient-cached, allowing stale private preference or relationship/block state to survive long enough to violate the current-action privacy expectation.

### Corrections applied

- Added a transparent “Why am I seeing this?” explanation for Latest, Following, Most Viral and For You.
- Added reduced-personalization mode. In reduced mode, local For You stops blending engagement-derived viral score and relies on eligible quality/freshness/diversity/safety.
- Added private user-owned hide/mute/snooze preferences with bounded storage and audit; these are presentation preferences and do not duplicate File 17's block/restrict relationship truth.
- Added Reset Feed preferences.
- Disabled transient caching for authenticated Feed output; public guest Feed cache remains. This prevents stale block/relationship/private-agency state from being served from File 21 cache.
- Retained File 26 as the only canonical global Search/Discovery/Recommendations/Ranking owner; File 21's connector remains `proposed` and cannot self-activate.

**Round 3 disposition:** corrected; no known repository-owned current-wave Top-20/user-agency/ranking blocker remains.

---

## Review Round 4 — Security, privacy, accessibility, regression and release truth

### Defects found during fresh adversarial re-review

1. **R4-D01 — Critical:** the first new Feed-preference REST callback passed an empty nonce into the write service even though the UI sent a nonce; this would have made the new protected write fail incorrectly. Corrected before release.
2. **R4-D02 — High:** the first preference persistence implementation treated `update_user_meta() === false` as an unconditional failure even when the desired value was already stored; corrected to verified idempotent semantics.
3. **R4-D03 — High:** the first saved-collection metadata path did not verify notes/tags persistence after the canonical save row succeeded; corrected with persisted-state verification and auditable failure.
4. **R4-D04 — Medium:** Feed/comment controls had inherited controls smaller than the current 44px minimum touch-target rule; corrected across Feed agency, comments, sorting and collapse controls.
5. **R4-D05 — Medium:** the first comment REST sort validator normalized an unknown value to `oldest` and then accepted it; corrected to validate the exact sanitized supported key before normalization.
6. **R4-D06 — Medium:** package/build identity still targeted 1.0.4 while the four-plan corrective scope had materially changed; advanced to package 1.0.5 while preserving runtime/API 1.0.3 and schema 1.0.0.

### Corrections applied

- Feed preference writes are authenticated, current-assurance checked and nonce-bound.
- User-meta preference and collection metadata writes are idempotently verified.
- 44px minimum controls, keyboard focus, forced-colors and reduced-motion behavior are explicit in corrected CSS.
- Comment sort rejects unknown keys rather than silently coercing them.
- Deterministic package builder is bound to 1.0.5 and requires the new current-wave runtime files.
- Release evidence explicitly records `Hostinger staging accepted: NO`, `Live deployed: NO`, and `Operational acceptance: NO` until those external gates are actually proven.

**Round 4 disposition before CI:** source corrections complete. Final repository acceptance is conditional on fresh exact-head CI, deterministic package verification, official quality checks and browser/Playground gates. Any defect produced by those gates reopens this round and must be corrected before merge.

---

## Cross-file invariants after correction

- **File 17:** canonical platform relationship/block owner. File 21 consumes its in-process contract; no direct File 17 SQL/table write.
- **File 20:** one application shell/navigation/layout owner.
- **File 21:** Home/Feed/Editorial News/publication lifecycle and local post-interaction owner.
- **File 22:** role-aware authoring/orchestration surface; no shadow File 21 content database.
- **File 23:** publishing workspace/dashboard projection; direct File 21 publication writes remain fail-closed unless separately accepted by the native contract.
- **File 25:** global visual system owner; File 21 uses current approved green semantics without becoming a second theme/shell.
- **File 26:** global Search/Discovery/Recommendations/Ranking orchestration. File 21 supplies bounded public projections and cannot self-activate the connector.
- Donation/payment/paid promotion/purchased engagement/Founder status do not create organic ranking advantage.

## Exact current-wave Home law preserved

The governing Home control bar remains exactly fourteen controls: **For You, Most Viral, Latest, Founder Posts, Doctors Posts, Classical Learning, Remedies, Diseases, Clinical Cases, Videos, Reels, PDF Books, Clinics, Marketplace**. Following is an auxiliary user-choice Feed mode, not a fifteenth canonical Home control. Companion rows remain provider projections/fallbacks; no fake content or fabricated counts are created.

## Future / NEXT / SCALE truth ledger

Plan compliance does not mean silently promoting every future-labeled capability into the current release. In particular:

- **Repost / Quote-post** is retained as a documented **NEXT/P1** capability; File 21 1.0.5 does not falsely claim it as a current implemented backend.
- File 26 global connector activation/ranking maturity is a File 26 governance/staging decision; File 21 remains `proposed`.
- Hostinger staging, installed-companion acceptance, real-role/browser/device visual acceptance, backup/restore, rollback rehearsal, Founder acceptance, production deployment and operational monitoring are external release statuses and remain unclaimed until evidenced.

## Final acceptance rule for this branch

This corrective branch may be described as **repository-owned current-wave complete** only after:

1. every PHP file parses;
2. all existing regression suites plus `run-file21-four-plan-audit-1.0.5.php` pass;
3. JavaScript/static security checks pass;
4. deterministic 1.0.5 package, embedded manifest, external checksum and ZIP CRC pass;
5. official PHPUnit, PHPStan and WordPress Coding Standards pass;
6. mandatory browser/WordPress Playground suites pass on the exact PR head;
7. no known Critical/High repository-owned defect remains;
8. the tested exact head is merged to `main` without untested code changes.

**Truthful completion boundary:** GitHub/source/package/automated-QA completion is not Hostinger staging acceptance, live deployment or operational completion.
