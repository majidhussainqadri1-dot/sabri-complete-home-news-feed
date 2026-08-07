# File 21 — Final Governing-Plan Reconciliation — 2026-08-07

## Governing scope

This correction reconciles File 21 with the current consolidated central-plan governance, recovered Founder directives, the File 21 encyclopedic specification, and the canonical File 26 Search/Discovery/Recommendations/Ranking contract. It preserves File 21 as the canonical owner of Home, social posts, Editorial News, publication lifecycle, interactions and local Feed behavior without creating a duplicate Shell, Composer, Dashboard or global Search backend.

## Corrected gaps

1. **File 22 Founder gateway** — File 22's registry may use only a coarse authenticated `read` prefilter. Final create authorization remains File 21 native `can_create()`, with current File 00 subject state, suspension, current-session assurance, Safe Mode and native policy revalidated. This prevents a stale role capability from hiding Social Publication from a valid Founder/Administrator while preserving ordinary-member denial.
2. **File 23 provider integration** — the accepted Adapter Contract 2.0.0 provider supplies bounded native inventory, workspace, review and calendar projections. Direct File 23 publication writes remain unavailable and fail closed; File 21 remains the source of truth.
3. **File 26 canonical global search ownership** — File 21 now registers `file21-publication` as a File 26 connector. The connector starts `proposed`; File 21 cannot promote or activate it. File 26 governance must advance it through contract-tested/shadow/approved/active gates after owner, privacy, security and staging acceptance.
4. **Visibility and deletion integrity** — File 26 receives only allowlisted public projections. Published File 21 posts require public visibility and approved review state; Editorial News uses the approved public projector. Ineligible, restricted or deleted objects are tombstoned so stale global results cannot survive source state changes.
5. **Ranking boundary** — File 21 does not send Founder-favoritism, donation, payment, paid-promotion or purchased-engagement signals into File 26 organic ranking. Search-side authority and popularity inputs from this connector are neutral; File 26 remains the ranking-policy owner.
6. **Visual boundary** — File 21 public Feed styling retains green action/accent presentation and contains no superseded `#FF8A1F` primary token. File 25 remains the canonical visual/design-system owner; File 20 remains the application-shell owner.
7. **Truthful package identity** — package `1.0.4` preserves stable File 21 runtime/API `1.0.3` and schema `1.0.0`. This reconciliation introduces no database migration. Deterministic package evidence explicitly keeps Hostinger staging, live deployment and operational acceptance as separate statuses.

## Permanent cross-file invariants

- File 20: one canonical application shell, global navigation and layout ownership.
- File 21: Home/Feed/Editorial News/publication lifecycle and native post ownership.
- File 22: one role-aware creation/orchestration surface; no shadow File 21 content backend.
- File 23: publishing workspace/dashboard projections; no unaccepted direct File 21 writes.
- File 25: global visual system and presentation ownership.
- File 26: federated search, discovery, recommendation, classification, knowledge graph and ranking orchestration.
- Core access must not be degraded by donation status; donor/payment advantage is not an organic ranking signal.
- Public projection availability is never authorization. Click/action time revalidation remains mandatory.

## QA gates added or strengthened

- `tests/run-file21-file22-founder-gateway-regression.php`
- `tests/run-file21-file26-connector-contract-tests.php`
- `tests/run-file21-four-plan-final-completion-tests.php`
- Build/Test workflow now runs all three on the exact head and builds only the `1.0.4` deterministic candidate.
- Authorization workflow now checks File 00, File 22, File 23 and File 26 boundaries on PHP 8.1 and 8.3.
- Official PHPUnit/PHPStan/WPCS and ten-browser/Playground gates are bound to the same `1.0.4` candidate identity.

## Release truth

Repository coding, packaging and automated QA may be accepted only when all exact-head workflows are green. Hostinger staging acceptance, installed-companion integration, real browser/device visual acceptance, cache behavior, backup/restore and rollback rehearsal remain external acceptance gates. No merge or package evidence alone constitutes live or operational completion.
