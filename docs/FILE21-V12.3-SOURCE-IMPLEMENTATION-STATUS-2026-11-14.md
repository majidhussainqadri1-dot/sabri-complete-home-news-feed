# File21 — V12.3 Exact Source Implementation and Release Evidence Ledger
**Prepared:** 2026-11-14 (Asia/Karachi); **Governing:** Sabri Website Master Plan V12.3, approved 2026-11-13.  
**Repo:** `majidhussainqadri1-dot/sabri-complete-home-news-feed`; **frozen main:** `626b380328d4400014504e679032291cd023bbdb`  
**Candidate:** Draft [PR #69](https://github.com/majidhussainqadri1-dot/sabri-complete-home-news-feed/pull/69), branch `fix/file21-v12-3-cross-issue-integration-20261114`; this ledger is source-only and immutable as to its frozen main.  
**Package main:** 1.0.5; **runtime/API:** 1.0.3; **schema declaration:** 1.0.0. The live deployed/DB/migration versions are UNKNOWN.

## Strict reality boundary
- Repo main is source truth only; PR source candidate and Live deployed build differ unless checksum parity is proven.
- WPVibe has no registered connected WordPress site. Attempting to connect `https://sabrihomeopathy.com` returned HTTP 530, whose origin is NOT established by this response.
- **Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**
- Staging plugin files/version, WP options, DB table schema, migration rows, WP/PHP runtime, logs, backups and deployed checksum are **unverified**.
- **No claim of production fix, staging acceptance, release acceptance or operational readiness.**
- CI runs prior to this exact candidate SHA never certify this candidate. A queued workflow never counts as passing.

## 31 requirement IDs
Authoritative machine-readable baseline: `docs/FILE21-V12.3-31-REQUIREMENTS-TRACEABILITY.csv`, tested by `tests/run-file21-v12-3-traceability.php`.
All 31 records have source files and repository tests but **none is marked as production-accepted or Live-verified**. This is intentional.
- F21-CEN-01: Following/Latest/For You controls, explanation, mute/reset and correction propagation.
- F21-NG-01–06: Repost, Quote, Threads, Coauthors, Stories, Developing Timeline.
- F21-NG-07–11: Expert notes, Evidence Card, Source Diversity, Edit/Correction History, Smart Share Warning.
- F21-NG-12–14: File16-owned AI summary, Ask Article and translation adapters.
- F21-NG-15–19: Follow Topics, My Topics Feed, Catch Up, Continue Reading, Read Later.
- F21-NG-20–24: Low Bandwidth, Offline Pack, Data Saver, Doctor Badge, structured Q&A.
- F21-NG-25–30: File26 trend/graph, News Compare, File25 share semantics, recipe, File19 Digest.

## Root-cause-first registry: source findings
| ID | Source defect / V12.3 mismatch | Candidate changes | Acceptance and status |
|---|---|---|---|
| #58 | File26 visibility metadata may reuse old timestamp object version | durable, locked source event sequence; File26 monotonic regression | **Candidate only; no exact final CI or Live acceptance** |
| #60 | File26 mutable offset-based batch can skip published records | versioned keyset cursor, fail-closed old offset, isolated SQL tests | **Candidate only; no exact final CI or Live acceptance** |
| #62 | File03 global signed cursor ignored by File21 owner | exact cursor keyset and negative privacy regression | **Candidate only; no exact final CI or Live acceptance** |
| #64 | local profile timeline late query filter can swap post author | canonical author/type/status/review recheck after filters | **Candidate only; no exact final CI or Live acceptance** |
| #65 | Feed renders world-public-only posts, incorrectly hiding scoped authorized posts | viewer-bound exact card guard, world-public-only sharing preserved | **Candidate only; no exact final CI or Live acceptance** |
| [#72](https://github.com/majidhussainqadri1-dot/sabri-complete-home-news-feed/issues/72) | Viewer-scoped authorized Feed card still showed world-public Share action | Guard Share in SocialRuntime independently from viewer access; suppress Share URL/title, with public/restricted tests | **Source candidate; exact-head CI and browser QA pending** |
| #67 | untrusted late Feed WP_Query filter can discard owner privacy constraints | source-bound query invariant after extensions with negative tests | **Candidate only; no exact final CI or Live acceptance** |
| V12.3-PUB-01 | File00 `can_publish` is only `can_open_composer` whereas File21 used it for direct publishing; Admin had auto direct publish | File00 `can_direct_publish` + doctor verification + Founder identity gate, Admin-only and unversioned delegation denied in Composer/Social/Stories; new actor tests | **Candidate only; delegated-editor versioned scope missing** |
| [#70](https://github.com/majidhussainqadri1-dot/sabri-complete-home-news-feed/issues/70) | Newsroom default Admin and editor-in-chief roles grant direct News publish/schedule without express scoped founder delegation | NewsCapabilities role map and late capability veto + isolated role/actor tests | **Candidate only; current per-object delegation and cron release recheck require acceptance** |

| [#73a](https://github.com/majidhussainqadri1-dot/sabri-complete-home-news-feed/issues/73) | Phase4B `NewsSchedulingService::schedule()` used WordPress `future` post status although it promised preparation-only; native WP cron could auto-publish | Use private `pending` plus File21 own scheduled workflow meta; real WordPress negative test checks no `publish_future_post` event | **Source candidate; tested on earlier exact SHA, Live unverified** |
| [#73b](https://github.com/majidhussainqadri1-dot/sabri-complete-home-news-feed/issues/73) | Ordinary WordPress core post status/direct/future publication can bypass File21 Composer and `wp_insert_post_data` current-actor assurance | **Not fixed for all entrypoints:** requires root-cause-safe core/cron/REST/CLI release authority and real WP concurrency tests | **OPEN release blocker; do not deploy** |
| [#74](https://github.com/majidhussainqadri1-dot/sabri-complete-home-news-feed/issues/74) | Continue Reading accepted viewer-scoped progress on mutation but rejected it later as not world-public | Bind the private subject to current authenticated File00-ready actor and recheck exact click-time visibility | **Source candidate; negative regression added, Live unverified** |
| [#75](https://github.com/majidhussainqadri1-dot/sabri-complete-home-news-feed/issues/75) | NG30 authenticated REST output lacked an explicit source-owned `Cache-Control: private, no-store` contract | Add private authenticated success/error header with guest-public caching unchanged | **Hardening candidate only; no observed real cache leak claimed** |
| V12.3-VIS-01 | File21 Editorial News primary actions, labels and pagination retained legacy orange despite File25 semantic Sabri Green | Adopt File25 exact primary/strong/soft/on-primary colors; isolated color/4.5:1 contrast gate | **Source candidate; responsive/dark-mode visual evidence pending** |

Additional source/document discrepancies:
- V12.3 central primary color `#087A4E` vs legacy `#1F7A55` and historic admin orange. Candidate updates shared File21 CSS/tokens and corresponding structural tests. File25 must independently validate full design-token ownership and contrast.
- README previously reported 1.0.4 though plugin package declares 1.0.5; candidate corrects first-line package identity while retaining 1.0.4 historical notes.

**Count: 14 distinct source/plan findings in the registry (including #75 as an explicit hardening gap, not an observed exploit). 13 have some candidate source remediation, one (#73b) has no complete execution-path correction, and none is certified as corrected on Live.** The delegated-editor contract, independent visual acceptance and remaining whole-system gates are also open. Do not interpret a source change as a fully resolved issue.

## Pull request reconciliation
| PR | Assessment under frozen main and V12.3 |
|---|---|
| #32 | historical 40-round 1.0.6 branch; **open, draft, not mergeable**, not blindly integrated |
| #41 | historical 80-round review; **open, not mergeable**, not blindly integrated |
| #44 | past cross-owner hardening; **open, not mergeable**, investigate applicable changes separately |
| #48 | historical 1.0.6 File04 / File26 candidate; **open, not mergeable**, migration parity outstanding |
| #50 | older File03 bridge; **open, not mergeable**; later PR #63 supplies exact cursor |
| #54 | later cross-owner evidence/current refresh; **open**; possibly mergeable but context pins and color may be stale |
| #59 | source changes for #58 selected, merged with #61 in candidate |
| #61 | source changes for #60 selected, merged with #59 in candidate |
| #63 | selected fixes for #62 and #64 in candidate |
| #66 | selected source fix for #65 in candidate |
| #68 | selected source fix for #67 in candidate |
| #69 | **new draft** integration branch containing selected code + V12.3 policy + color corrections; **not accepted or merged** |

## V12.3 P00–P12 execution classification
| Stage | Repository-level status | Proof/blockers |
|---|---|---|
| P00 Baseline and Live reality freeze | Partial | exact main SHA/versions and PR list frozen; live environment inaccessible |
| P01 Requirements→Code→Test | Partial | 31 direct IDs registered; behavior and dependent acceptance unverified |
| P02 PR reconciliation | Partial | 5 fresh PRs integrated; 6 historical PRs not fully reconciled |
| P03 Six source issues | Candidate code | all six candidate fixes included; final tests pending |
| P04 Core Home/News/publication | Partial | V12.3 authority controls candidate; editorial scoped grant and lifecycle tests pending |
| P05 NG30 functional closure | Partial | NG30 manifests/code present; real WP and owner systems not accepted |
| P06 Cross-owner integration | Partial | provider contracts provided; current actual deployed companions unverified |
| P07 Security/privacy/clinical | Partial | source negative tests written; independent real WP evidence pending |
| P08 Visual/language/UX/AA | Partial | primary color tokens candidates; File25/24 & RTL/manual evidence pending |
| P09 First independent review | Not accepted | fresh post-correction exact head and defect register needed |
| P10 Second review/package | Not accepted | deterministic artifact/checksum/dual review gating missing |
| P11 Staging | Not verified | site connection, deployed files, DB, rollback rehearsal unavailable |
| P12 Live/operational | Not verified | exact active runtime and fresh Live retest unavailable |

## Unresolved hard dependencies and release blockers
1. **File00 delegation owner** must define Founder-issued versioned subject, content or section, permitted action, start/expiry, revocation and hold semantics. A generic role/SMC trusted flag is not adequate. Current candidate **fails closed** for unscoped delegates.
2. **News publication execution recheck**: actual WordPress publishing/scheduling/cron/REST must reject Admin-only, stale, cross-content, expired and revoked grants, including whether capability filters run on every path. Current source gate is not sufficient proof.
3. **File03/File26 integration**: real exact WordPress+MariaDB, pagination, event versions, concurrent writes, search purge/restore and external owner contract versions.
4. **Live Reality Freeze / Deployment parity**: active File21 plugin version/checksum, WP/PHP, File00 and File20 active versions, tables/columns/wp_options/migration ledger, error logs.
5. **V12.3 user experience**: strict RTL/LTR, `#087A4E` File25 token policy, 320/390/768/1024/1440, 200%/400% reflow, reduced motion, forced colors, keyboard, WAI WCAG 2.2 AA, two human QA passes.
6. **Immutable package/rollback**: fresh package and SHA-256 only after final source CI; backup plus staging migration, restore/rollback and Founder-approved limited deployment.
7. **Independent reviewer**: second actual review after every later candidate correction. Open PR presence or prior green workflows never constitutes approval.

## Required final incident report fields
- **Repository HEAD** = `626b380328d4400014504e679032291cd023bbdb` (frozen source main).
- **Candidate HEAD** = use current PR #69 head; this ledger is not a self-updating immutable SHA.
- **Deployed Version** = Unverified.
- **DB Version** = Unverified.
- **Migration State** = Unverified.
- **Live Verification Status** = Not verified.
- **Known source/plan findings** = 14 (including a hardening item); **source-only candidates** = 13 with incomplete acceptance; **generic core publication blocker** = #73b; **Live-resolved** = 0. **Separate release QA gap** = Issue #71. Historical README 1.0.4/1.0.5 drift remains counted separately as a documentation discrepancy.



## Two-review incremental evidence — V12.3 source and verification, 2026-11-14
- Review A (security & owner boundaries): Frozen `main@626b380328d4400014504e679032291cd023bbdb`, current File00/03/25/26 source HEADs, direct/revocable publishing, Feed/Post/News privacy, and native WordPress scheduling.
- Review B (behavior & visual/CI): isolated PHP regressions and selected real WordPress/Playwright runs, File25 exact token comparison, File21 NG30 actual PHP feature manifest. Both reviews are **assistant-led source reviews**, not a substitute for independently signed File24/human acceptance.
- Prior tested `PR#69@19fd71aacfba33a9d8d97dc051061be5bfb68425`: **15 workflows success; 1 authorization workflow still queued at the time of this audit**. This is strong historical exact-source evidence for Phase4B scheduling, browser and File26 compatibility but **cannot approve later candidate commits**.
- The standalone PHP runtime NG30 manifest test proves actual method return structure, 30 exact unique IDs, consistent owner contracts and slug mapping, **not complete 30-feature interaction journeys**.
- Latest current source and tests MUST be rerun on the exact PR head after this ledger commit. A completed earlier candidate is no substitute.
- The File25 semantic values were checked at `347a4ff4d4c233c5ea6cd82c7786ee5398ea9d1e`: primary `#087A4E`, strong `#065C3B`, soft `#E7F5EE`, on-primary `#FFFFFF`; isolated white-on-green contrast computes 5.38:1. Full current File25 browser/a11y, theme, and deployed token parity remain unverified.

**Release decision: NO-GO until V12.3 CEN-REL-001 gates are met.**
