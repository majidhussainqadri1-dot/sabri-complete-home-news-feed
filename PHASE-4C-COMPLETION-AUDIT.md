# Phase 4C Completion Audit

This checklist maps every frozen Phase 4C planning obligation to executable implementation or acceptance evidence.

## Functional contract

- [x] Exact seven public route families.
- [x] Gate-off and Emergency Disable fail closed.
- [x] Complete public-state matrix.
- [x] Inclusion-only article, card, correction, and retraction projections.
- [x] Last-approved snapshot for `correction-pending`.
- [x] Private pending-correction storage and approved promotion.
- [x] Complete bounded multi-section News landing.
- [x] Archive, taxonomy, search, filters, pagination, related News, and empty states.
- [x] Complete single article metadata, disclosures, taxonomy links, canonical sharing, related News, and allowed interactions.
- [x] Dedicated Home Feed News adapter, identity, ordering, deduplication, canonical linking, and card-aware assets.
- [x] Strict GET-only public REST collection and single routes.
- [x] Frozen public error codes.
- [x] Public cache dimensions, invalidation, isolation, and plugin-owned purge.
- [x] Responsive, keyboard, zoom, reduced-motion, forced-colors, and no-JavaScript core operation.

## Security and privacy contract

- [x] Query-layer and projection-layer public eligibility.
- [x] Private state ID/slug non-enumeration.
- [x] Strict IDs, slugs, dates, booleans, page bounds, term existence, and unsupported-parameter rejection.
- [x] Rich HTML sanitization and context-aware escaping.
- [x] Unsafe URL rejection.
- [x] No private notes, reviewer deliberation, source confidence, account email, nonce, preview token, patient/contact/CNIC/passport/address/medical identifier, or hidden retracted body in public projections.
- [x] Approved author/institution and reviewing-editor disclosure policy.
- [x] Public cache excludes requester/session state.
- [x] Public writes remain closed.

## Automated acceptance contract

- [x] Phase 4C behavior tests.
- [x] Phase 4C security-negative tests.
- [x] Phase 4C UI/accessibility/completeness tests.
- [x] Phase 4A/4B and core regression execution in permanent CI.
- [x] Source WordPress latest/PHP 8.3 and WordPress 6.8/PHP 8.1 matrices.
- [x] Immutable package SHA-256 and file-structure verification.
- [x] Packaged WordPress matrices.
- [x] Permanent exact-head ordinary acceptance workflow.
- [x] Permanent exact-head 3,900-second/13-cycle visible QA workflow.
- [x] Initial/final tracked-file manifest comparison.
- [x] Final packaged matrices and retained passed artifacts.

## Release boundaries

- [x] Version remains `1.0.0`.
- [x] Schema remains `1.0.0`.
- [x] Checkpoint remains `4A`.
- [x] Gates remain disabled by default.
- [x] Draft PR remains isolated and unmerged until a later explicit decision.
- [x] No Hostinger staging or live deployment authorization.
