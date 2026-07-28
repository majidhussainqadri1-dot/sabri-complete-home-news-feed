# Editorial News Public Visibility Hotfix

## Confirmed defect

Editorial News created in the private News Composer could not become publicly visible because the Phase 4B validator and application service intentionally kept the `published` state closed, while the public projector required both workflow state `published` and WordPress status `publish`.

## Corrective boundary

- Founder and Administrator accounts receive an explicit **Published — visible on public News** Composer option.
- Existing hidden Founder/Administrator News records receive a bounded **Publish publicly** bulk action.
- Every mutation requires POST, nonce verification, exact Editorial News capabilities, canonical immediate-publisher identity, explicit confirmation, and a trusted article author.
- Successful publication sets WordPress status `publish`, canonical workflow state `published`, creates the approved public snapshot, opens the Editorial News public gate, flushes rewrite rules once, and purges File 21 News caches.
- Missing summary, section, or article-type classification is repaired only during the explicit trusted publication action from the existing article content and fixed canonical defaults.
- Protected retracted, archived, and correction-pending records are never force-published.
- Snapshot failure rolls the post status and workflow state back.
- No public GET request performs publication or database repair.
