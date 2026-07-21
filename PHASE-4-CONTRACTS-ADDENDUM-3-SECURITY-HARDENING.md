# Phase 4 Contract Addendum 3 — Phase 4A Security Hardening Freeze

Target development line: `1.2.0`

Checkpoint: `4A`

Normative parents:

- `PHASE-4-CONTRACTS.md`
- `PHASE-4-CONTRACTS-ADDENDUM-1.md`
- `PHASE-4-CONTRACTS-ADDENDUM-2-WORDPRESS-STATUS-STORAGE.md`

Status: **normative security correction; no public release authorization**

## 1. Purpose and precedence

This addendum freezes the corrections discovered during the mandatory second review of the Phase 4A executable content model. Where a broad primitive capability could exceed an `O` own-object or `S` assigned-object/section permission in Addendum 1, this stricter fail-closed addendum governs until the relevant object-policy service exists and passes its own mandatory second QA.

Nothing in this addendum permits merge, version promotion, Hostinger staging acceptance, or live deployment.

## 2. Exact identifiers and values

Feature-gate names, editorial workflow states, protected status tokens, and other allow-list identifiers must match their frozen lowercase values exactly.

The implementation must not use sanitization to transform malformed input into a different accepted identifier. The following must fail closed:

- whitespace-padded identifiers;
- uppercase aliases;
- punctuation-appended identifiers;
- arrays or objects;
- numeric-prefix strings;
- unknown values that sanitize to a known value.

Only exact scalar values `1`, `"1"`, and `true` may enable a checkbox gate. All other values disable it.

## 3. Object capability boundary

Editorial News uses WordPress ownership-aware meta-capability mapping.

- `edit_editorial_news` is the singular edit meta capability.
- `read_editorial_news_item` is the singular read meta capability.
- `delete_editorial_news` is a unique singular delete meta capability deliberately absent from every role.
- all collection-level WordPress delete primitives map to `do_not_allow`.
- retraction is a non-destructive editorial workflow action and is not implemented by deleting or trashing the post.

An own-object capability must never authorize editing another author's article. An assigned-section or assigned-review authority must never become a global primitive permission merely because object policy has not yet been implemented.

## 4. Scoped role permissions remain closed until their policy service exists

The `O` and `S` entries in Addendum 1 are ceilings, not instructions to grant a global primitive capability.

At Phase 4A:

- Reporter, Medical Reviewer, Verified Doctor Submitter, Translator, and Section Editor do not receive global `manage_news_sources` authority.
- Section Editor does not receive global `edit_others_editorial_news`, `review_editorial_news`, `fact_check_editorial_news`, `manage_news_corrections`, or `translate_editorial_news` merely to approximate section-scoped authority.
- Medical Reviewer receives the medical-review primitive, but an article-level assignment check is still mandatory.
- own-submission and assigned-section source management remains closed until the Source Registry/object-policy checkpoint implements and tests it.

High-level roles whose Addendum 1 entry is `A` may retain the corresponding global primitive.

## 5. Protected metadata authorization

Every registered Editorial News meta key has an explicit capability policy. Unknown meta keys fail closed.

Basic author-editable metadata requires object-level `edit_editorial_news` authority:

- subtitle;
- summary;
- language.

Sensitive metadata requires both authority over the article and the relevant specialized capability:

- workflow source of truth — publication authority;
- fact-check status and last-verified time — fact-check authority;
- reviewer assignment — review authority;
- breaking state, period, and priority — breaking-news authority;
- correction state — correction authority;
- retraction state — retraction authority;
- translation source article — translation authority.

Medical-review status may be changed by:

1. an editor who can edit the article and holds review authority; or
2. the exact user recorded as that article's medical reviewer, provided the user holds the medical-review capability.

A Medical Reviewer cannot self-assign by changing reviewer-assignment metadata.

## 6. Metadata sanitization

- Workflow states and protected tokens use exact lowercase allow lists or token formats and are never repaired from malformed input.
- Priority is an integer from `0` through `100`; invalid, floating-point, signed, or out-of-range input fails to `0`.
- Date/time metadata accepts only bounded ISO/WordPress-style full date-time strings; free-form natural-language dates fail closed.
- Language tags remain bounded BCP-47-style strings; invalid input returns the safe `en-US` default.

Authorization and sanitization are separate requirements. Passing one never bypasses the other.

## 7. Taxonomy installation and upgrade boundary

Default terms are not checked or written on every frontend request.

- Activation may install and verify the frozen terms.
- An active-plugin upgrade repair runs only in an authorized administration session holding `manage_news_settings` or `manage_options`, or through explicit WP-CLI execution.
- unauthenticated and low-privilege requests cannot trigger term writes.
- a failed term lookup, insertion, or verification is a failure, not an existing-term skip.
- the term-version and Phase 4 contract-version markers advance together only after complete success.
- partial or failed installation leaves both markers unchanged.

## 8. Capability mutation ownership

The plugin may remove a stale capability only when its own persisted mutation record explicitly proves that the plugin added that capability.

The implementation must not infer plugin ownership merely because a capability is currently present and an old snapshot recorded it as absent. Administrator-added or third-party-added capabilities remain untouched unless an exact rollback baseline expressly requires restoration.

## 9. Immutable and upgradeable rollback baseline

The first complete activation baseline for a plugin version is immutable across deactivation and reactivation.

A legacy same-version snapshot may be augmented only for fields that did not exist in the old format. Augmentation must:

- preserve the original creation timestamp;
- preserve all previously captured values;
- preserve previously captured capability decisions;
- treat capabilities explicitly recorded as plugin-added as absent from the pre-plugin baseline;
- record a snapshot format version and augmentation timestamp;
- never use current plugin-mutated state to overwrite an existing baseline field.

## 10. Exact option rollback

Rollback restores both value and existence.

When a Phase 4-owned option did not exist at baseline, rollback deletes it rather than storing an empty value or defaults. This rule applies to:

- Phase 4 feature settings;
- Phase 4 contract marker;
- Phase 4 term marker;
- Phase 4 capability-mutation record.

Editorial News posts, revisions, terms, media, and metadata are preserved. Rollback remains non-destructive.

## 11. Mandatory test evidence

Phase 4A acceptance requires automated evidence covering at least:

- exact gate identifiers and strict values;
- strict workflow-state rejection;
- ownership-aware own/other/published edit behavior;
- destructive deletion rejection;
- per-field metadata authorization;
- assigned and unassigned Medical Reviewer behavior;
- self-assignment rejection;
- scoped-role global-capability absence;
- stale plugin-owned capability removal and pre-existing capability preservation;
- unauthorized taxonomy-upgrade rejection;
- partial term-installation failure and marker non-advancement;
- legacy snapshot augmentation;
- same-version reactivation immutability;
- exact option-existence rollback;
- source and packaged WordPress Playground integration;
- the mandatory independent 3,900-second second QA.

Any correction after a passed attempt invalidates that attempt for the new commit and restarts the complete second QA from zero.
