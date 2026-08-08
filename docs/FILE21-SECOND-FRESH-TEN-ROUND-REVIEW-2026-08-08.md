# File 21 — Second Fresh Ten-Round Review, Fix and Retest — 2026-08-08

## Governing basis

This is a second independent review wave performed after the earlier ten-round reconciliation. It re-audits the current File 21 repository against the current consolidated central governance, the File 21 NG30 amended master plan, and the current cross-owner contracts. It does not reuse the first ten-round result as proof.

The release-state law remains unchanged: repository/code completion and automated QA are distinct from Hostinger staging acceptance, production deployment and operational acceptance.

## Ten fresh rounds

| Round | Area | Defect found? | Correction / evidence |
|---|---|---:|---|
| 1 | Release/version/ownership consistency | No | Package 1.0.5, runtime/API 1.0.3 and schema 1.0.0 remain aligned; File 26 global Search/Discovery/Recommendations/Ranking ownership remains explicit. |
| 2 | Authentication/current assurance/authorization | No | File 00 subject-bound assertions, current-session 2FA/session assurance, current-actor binding and File 21 capability gates remain fail-closed. |
| 3 | Privacy/visibility/cache/indexing | **Yes** | Fixed three escaped defects: mixed post/news NG30 REST context now uses the canonical cross-domain visibility gate; privacy export no longer emits synthetic default state for a user with no stored NG30 state; destructive uninstall now removes File 21-owned `_sabri_hnf_ng_user_v1` when retention is explicitly disabled. Existing private REST no-store behavior remains intact. |
| 4 | Frozen Home/ranking/user-agency invariants | **Yes** | The base Home registries still exposed whole-registry filters that could alter the governing fourteen controls or ten rows. Added a final `PHP_INT_MAX` contract guard that restores the exact canonical controls/rows while leaving item/provider adapters usable. |
| 5 | Editorial News lifecycle | No | Public states, retraction handling, object-level edit/preview gates and reviewer capability checks remain correctly owned by File 21 Editorial News policy. |
| 6 | NG30 social semantics | **Yes** | Added canonical-public-identity validation and metadata sanitization for co-authors. Added explicit authorized-professional/moderator gating for Story enablement and a defense-in-depth 24-hour maximum expiry clamp. |
| 7 | Cross-owner integrations | No | File 16 AI/translation, File 19 notification ingestion, File 25 visual rendering and File 26 discovery remain versioned adapters; no duplicate foreign backend was introduced. |
| 8 | Security/abuse/input hardening | **Yes** | Added a 32 KiB maximum NG30 mutation body and a 5,000-character maximum for quote/expert/Q&A free text, on top of the existing nonce, current assurance and persistent rate-limit gates. |
| 9 | Accessibility/i18n/performance/resilience | No | 44px targets, reduced-motion behavior, low-bandwidth/data-saver behavior and request-scoped corrective assets remain present. |
| 10 | Packaging/CI/test-design drift | **Yes** | Added a permanent second-review source/package workflow and executable ten-round gate. The first run exposed a test-design interpolation defect in the new gate itself; it was corrected immediately and the exact-head rerun passed. The workflow also verifies the new hardening runtime, privacy runtime and uninstall boundary are inside the deterministic package. |

## Code corrections

- Added `includes/class-second-fresh-review-hardening.php` and registered it through the canonical plugin coordinator.
- Corrected the NG30 mixed-domain REST post-context path without replacing File 21/News ownership.
- Hardened co-author and professional Story metadata at both request and metadata-sanitization boundaries.
- Added explicit mutation payload/text bounds.
- Corrected `includes/class-next-generation-privacy.php` so export is evidence-based on stored user metadata rather than generated defaults.
- Corrected `uninstall.php` so File 21-owned private NG30 user metadata follows the explicit retain/delete setting independently from separately protected Phase 5 accountability data.
- Added `tests/run-file21-second-fresh-ten-review-tests.php`.
- Added `.github/workflows/file21-second-fresh-ten-review-gate.yml` and deterministic-package inventory checks.

## Review accounting

**Defects were found in rounds 3, 4, 6, 8 and 10.**

**No new defect was found in rounds 1, 2, 5, 7 and 9.**

Round 10 includes the newly discovered test-design defect from the first execution of this second-review CI gate; it was fixed before acceptance.

## Release identity

- WordPress/package identity: `1.0.5`
- Stable runtime/API: `1.0.3`
- Database schema: `1.0.0`
- Database migration introduced by this review: **No**
- Hostinger staging accepted: **No claim; separate external gate**
- Production live: **No claim; separate external gate**
- Operational acceptance: **No claim; separate external gate**

Final acceptance requires the exact PR head and the post-merge `main` head to remain green on the repository-owned quality, contract, browser/Playground and deterministic-package gates.
