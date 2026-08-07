# File 21 — Forty-Round Review and Corrective Ledger — 2026-08-07

Baseline main: `4c7784b5d20bc685aeca439e5917fd2582e3beeb`
Audit branch: `audit/file21-forty-round-review-2026-08-07`

This ledger records forty sequential review cycles. A round is marked **DEFECT** only when that fresh review identified at least one actionable repository-owned defect. Every defect-bearing round is corrected and retested before the next round begins. External staging/live gates are not reclassified as source-code defects.

| Round | Focus | Result | Correction / evidence |
|---:|---|---|---|
| 1 | Exact-head PHP syntax / post-merge CI truth | DEFECT | Fixed missing quote in `tests/run-file21-harmonization-tests.php`; all PHP lint passed afterward. |
| 2 | Core behavior runner | CLEAN | `php tools/run-tests.php`: 39 behavior groups + Safe Boot passed. |
| 3 | Full exact-head PHP workflow regression suite | DEFECT | Corrected stale release docs, exact 14-control invariant/evidence, duplicate-copy test fixture, File 26 false-positive ranking assertion and File 23 fail-closed test target; all 27 workflow PHP regressions passed. |
| 4 | File 17 relationship ownership/pagination/failure semantics | DEFECT | Added cursor pagination up to bounded 200 follows and fail-closed behavior when canonical File 17 is present but unavailable; added behavior regression. |
| 5 | REST permission callbacks and public-mutating route exposure | CLEAN | 48 route registrations inspected; no missing permission callback or public mutating route found. |
| 6 | CSRF/capability coverage of admin-post actions | CLEAN | Registered admin-post handlers inspected against nonce + native capability/current-assurance guards; no actionable bypass found. |
| 7 | SQL/request injection and direct DB query review | CLEAN | 41 DB read/query call sites scanned; no direct request-data use in an unprepared query found. |
| 8 | Template/public projection XSS and rich-HTML escaping | CLEAN | Raw-markup candidates traced to controlled renderers/`wp_kses_post`/WordPress media HTML; no untrusted direct output defect confirmed. |
| 9 | Redirect safety / open redirect review | CLEAN | No `wp_redirect()` or raw Location redirect found; application redirects use `wp_safe_redirect()`/allowlisted destinations. |
| 10 | Dynamic code execution / unsafe deserialization review | CLEAN | No runtime `eval`, unsafe `unserialize`, shell execution or process-spawn primitive found in plugin-owned PHP. |
| 11 | Upload/privacy hardening + Phase 5 release-contract replay | DEFECT | Found current Phase 5 final-contract test loading a historical `1.0.0` stub while asserting runtime `1.0.3`; pinned the test to current runtime/schema before stubs. Phase 5 final, security/privacy and Phase 3H hardening tests then passed. |
| 12 | Viewer-specific/private cache and no-store boundaries | CLEAN | Private `/me/*` and viewer-specific REST surfaces expose `no-store, private`; Phase 4C security replay passed. |
| 13 | IDOR/object authorization/current File 00 assurance | CLEAN | `run-file00-authorization-integrity.php`: 23 PASS / 0 FAIL; Phase 4C and Phase 3H negative-path suites passed. |
| 14 | Current File 00 subject/session assurance on protected publishing actions | CLEAN | File 00 integrity suite: 23 PASS / 0 FAIL; composer authority precedence passed; 29 current-action assurance integration sites found. |
| 15 | File 17 block/follow bridge after corrective pagination | CLEAN | Real current File 17 contract shape was checked; File 21 bridge behavior test and four-plan audit passed after Round 4 correction. |
| 16 | File 19 notification ownership and privacy minimization | CLEAN | Notification bridge owns no inbox/delivery store, emits bounded domain events, suppresses self/duplicates; Phase 3G tests passed. |
| 17 | File 20 shell ownership / no duplicate global navigation | CLEAN | File 21 mounts only declared Home/News shell slots with truthful compatibility fallback; harmonization suite passed. |
| 18 | File 22 composer/orchestration boundary | CLEAN | Native File 21 final authorization and corrected workflow adapter/founder gateway regressions passed; no duplicate native insert path found. |
| 19 | File 23 dashboard projection / fail-closed writes | CLEAN | Provider integration: 28 PASS / 0 FAIL; operation definitions remain empty and direct dashboard writes return `file21_spdb_write_not_accepted`. |
| 20 | File 25 visual ownership / current green identity | CLEAN | Deprecated orange absent from primary Home/Feed identity surfaces; green `#1f7a55` effective; four-plan completion regression passed. |
| 21 | File 26 canonical Search/Discovery/Ranking boundary | CLEAN | Connector contract passed; File 21 connector remains `proposed`, global owner is File 26, authority/popularity projections remain neutral. |
| 22 | Exact fourteen canonical Home controls | CLEAN | Literal `data-sabri-home-control-count="14"` present with the exact approved control set; four-plan audit passed. |
| 23 | Ten base Home rows and truthful provider degradation | CLEAN | Literal row count 10, provider state markers and honest unavailable state verified; harmonization suite passed. |
| 24 | Feed user agency / Top-20 controls | CLEAN | Following/Latest/For You, Why-this, reduced personalization, Not Interested, snooze/mute and reset controls verified; relationship bridge test passed. |
| 25 | Ranking manipulation / Founder-donor-paid bias | CLEAN | Founder authority is not an organic bonus; no donor/payment/paid-promotion score fields; bounded quality/engagement and abuse penalties verified. |
| 26 | Comment chronology, reply context and privacy | CLEAN | Phase 3C passed; deterministic Oldest/Newest, visible-parent quote context and non-resolving textual mentions verified. |
| 27 | Saved collections, private notes/tags and export | CLEAN | Existing saves truth reused; limits, visibility checks, current assurance/nonce and private no-store REST verified; Phase 3B passed. |
| 28 | Editorial News publication/correction/retraction safety | CLEAN | Phase 4 contract, 4A content model, 4B Newsroom, 4C public News and 4C security suites all passed. |
| 29 | File 04 legacy migration non-destructive/reversible | CLEAN | Legacy interaction migration behavior passed; source deletion SQL/post deletion absent; rollback privatizes owned targets. |
| 30 | Rollback/uninstall retention and explicit destructive gate | CLEAN | Rollback-edge and File 22 maintenance/lock-retention suites passed; destructive Phase 5 uninstall requires explicit confirmation. |
| 31 | Accessibility: 44px targets, focus, reduced motion, forced colors | CLEAN | Current Home/Feed/Comments controls demonstrate minimum targets, focus-visible, reduced-motion and forced-colors support. |
| 32 | Responsive/RTL-safe layout and overflow risk | CLEAN | Current Home/Feed/Comments use bounded grids/mobile breakpoints/logical spacing; no large fixed minimum-width contract found in these surfaces. |
| 33 | JavaScript sink/progressive-enhancement hardening | DEFECT | Newsroom media preview concatenated attachment URL into `innerHTML`; replaced with `createElement('img')`, property assignment and DOM replacement; `node --check` passed and dangerous sink removed from that path. |
| 34 | Release identity, documentation and contract-test truth | DEFECT | README/PHPUnit/current-package assertions lagged behind the corrective source and the new fixes required a distinct release identity. Advanced package to 1.0.6, preserved runtime/API 1.0.3 + schema 1.0.0, corrected current docs/tests/workflows and restored 1.0.5 as historical changelog truth. |
| 35 | Official PHPStan/WPCS critical-surface coverage | DEFECT | Current-wave relationship, Feed-agency, saved-collection and comment-experience classes were outside the official critical static-analysis lists; added all four to `phpstan.neon.dist` and `phpcs.xml.dist`. |
| 36 | Exact-head CI regression coverage for File 17 bridge fix | DEFECT | New canonical relationship bridge behavior test was not wired into exact-head workflows; added it to build/authorization/official and File 22/File 26 contract workflows. |
| 37 | Deterministic release build | CLEAN | Clean builder produced package 1.0.6 with two-build determinism, manifest and SHA-256; 321 manifest-covered files. |
| 38 | Source/package parity, manifest and ZIP CRC | CLEAN | External SHA-256 verified, ZIP integrity passed, embedded `MANIFEST.sha256` verified for all 321 entries, packaged bootstrap reports 1.0.6. |
| 39 | Full local exact-head regression replay after all corrections | CLEAN | 275 PHP files linted; core 39 behavior groups + Safe Boot passed; 28 exact-head workflow regression files passed. |
| 40 | Final exact-head release evidence and forty-round closure | DEFECT | Final review found no executable gate proving all 40 rounds/counts, no builder evidence line for this audit, and fresh exact-head official PHPUnit exposed stale 1.0.4 package assertions. Added `run-file21-forty-round-review-tests.php`, wired it into exact-head workflows, bound build evidence to the 40/40 ledger, corrected PHPUnit release identity to 1.0.6, then required fresh GitHub exact-head CI before acceptance. |
