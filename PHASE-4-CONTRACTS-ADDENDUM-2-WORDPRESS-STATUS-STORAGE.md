# Phase 4 Contract Addendum 2 — WordPress Workflow-State Storage

Target development line: `1.2.0`

Development branch: `build/phase-4-editorial-news-1.2.0`

Normative parents:

- `PHASE-4-CONTRACTS.md`
- `PHASE-4-CONTRACTS-ADDENDUM-1.md`

Status: **approved Phase 4A compatibility amendment; implementation and staging acceptance remain required**

## 1. Defect discovered during Phase 4A

The frozen editorial state `ready-for-publication` contains 21 characters. WordPress stores `wp_posts.post_status` in a field whose established practical limit is 20 characters. Registering the complete frozen state directly as a WordPress post status risks truncation, failed comparisons, inconsistent queries, or a state that cannot be recovered reliably.

Changing the frozen editorial state name merely to fit storage would create undocumented contract drift. Storing an unsafe truncated value would violate the fail-closed and audit requirements.

## 2. Frozen dual-layer storage decision

The complete Editorial News workflow state is stored in the private metadata key:

```text
_sabri_news_workflow_state
```

This metadata value is the canonical source of truth for the Phase 4 editorial workflow.

WordPress `post_status` is used only for its compatible core publication lifecycle:

| Editorial domain state | WordPress core status |
|---|---|
| `draft` | `draft` |
| `needs-sources` | `draft` |
| `editorial-review` | `pending` |
| `fact-check` | `pending` |
| `medical-review` | `pending` |
| `ready-for-publication` | `pending` |
| `scheduled` | `future` |
| `published` | `publish` |
| `updated` | `publish` |
| `correction-pending` | `publish` |
| `corrected` | `publish` |
| `retracted` | `private` |
| `archived` | `private` |

## 3. Mandatory rules

- The full domain state must never be truncated to fit `post_status`.
- Unknown domain states fail validation and must not fall back silently to `draft` or `publish`.
- Publication eligibility is decided from the domain state, prerequisites, capability, exact revision, feature gate, and policy—not from WordPress `post_status` alone.
- Public queries require both an approved public domain state and the compatible public core status.
- A `publish` core status does not make `correction-pending` or another domain state automatically eligible for every public projection.
- Retraction and archive projections are governed by Phase 4 policy even though their core storage status is `private`.
- Scheduling callbacks must revalidate the canonical domain state before changing the core status to `publish`.
- Workflow-state metadata is private and is never exposed through uncontrolled native WordPress REST fields.
- Every material domain-state transition remains audit-recorded.

## 4. Migration and rollback

Phase 4A does not alter the existing WordPress posts table and does not require a destructive database migration.

A future implementation that previously stored a Phase 4 workflow state in `post_status` must use an explicit, idempotent migration to populate the canonical metadata key. No migration may infer `published` merely from `post_status = publish` without reviewing Phase 4 metadata and audit context.

Rollback closes Phase 4 gates and preserves the metadata. It does not delete Editorial News records merely because older plugin code does not interpret the Phase 4 metadata.

## 5. Test requirements

Automated tests must prove that:

- every frozen editorial state has a defined compatible core status;
- every mapped core status fits the WordPress storage limit;
- `ready-for-publication` remains intact in the canonical metadata value;
- unknown states fail closed;
- the canonical source-of-truth key is stable;
- plugin and schema versions remain unchanged during Phase 4A;
- no native uncontrolled REST exposure is introduced.

## 6. Precedence

This addendum governs the storage implementation wherever earlier Phase 4 documents use the general phrase “custom statuses.” Those phrases refer to the Editorial News domain workflow and its administrative presentation, not a requirement to place every full domain-state slug directly in `wp_posts.post_status`.
