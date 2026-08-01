# File 21 — Public Content Integrity Correction 1.0.3.1 R2

## Confirmed staging defects

Controlled File 22 acceptance testing proved that the File 21 native post record stored the correct body, while the Home Feed card paired that post title with unrelated Global Clinic text. A separate long-form publication exposed raw Markdown emphasis markers and a public single-post layout that could extend beneath Shell sidebars.

The same acceptance post also displayed WordPress's automatically assigned default Category as if it were author-selected File 21 taxonomy, although the File 21 Composer exposes the separate `sabri_feed_topic` field rather than a core Category selector.

## Correction

This corrective build:

- binds excerpt generation to the explicit `WP_Post` supplied for the File 21 card instead of accepting a polluted global-loop excerpt;
- uses the canonical post excerpt or canonical post body directly, strips shortcodes and tags, normalizes whitespace, and applies a bounded 45-word projection;
- leaves ordinary WordPress posts unchanged;
- suppresses only the automatically assigned WordPress default Category on File 21 managed posts while preserving explicit File 21 topics and all non-default categories;
- converts a conservative legacy/plain-text Markdown subset (`#` headings, `**bold**`, and `__bold__`) only on File 21 managed single-post output;
- processes text nodes rather than HTML attributes and introduces only fixed semantic heading/strong tags;
- preserves legitimate WordPress block, embed, and shortcode output rather than re-sanitizing the entire rendered document;
- adds one idempotent content wrapper and a stable body class for File 20/File 25 integration;
- constrains managed single-post content, action rows, comments, media, tables, and long words to the available content column;
- introduces no database migration, no public GET write, no post mutation, no taxonomy deletion, and no duplicate content record.

## Release identity

- WordPress package identity remains `1.0.3.1` for compatibility with the current mixed-role authority hotfix.
- Corrective build identity: `1.0.3.1-PUBLIC-CONTENT-INTEGRITY-R2`.
- File 21 runtime/API compatibility remains `1.0.3`.
- Schema remains `1.0.0`.
- CSS/cache corrective marker: `1.0.3.2`.

The distinct R2 artifact name and checksum are mandatory. A historical 1.0.3.1 package must not be represented as this correction.

## Safety boundaries

- File 21 remains the sole native publication owner.
- File 22 stores no shadow body and receives no new content ownership.
- File 20 remains owner of the global Shell grid, header, navigation, and sidebars.
- File 25 remains owner of public profile/timeline visual composition.
- The File 21 CSS provides containment only; it does not attempt to reposition or replace Shell columns.
- Explicit disciplinary, moderation, privacy, visibility, and publication rules are unchanged.

## Mandatory staging acceptance

1. Replace File 21 on staging with the exact R2 artifact.
2. Purge LiteSpeed, Hostinger, object, and browser caches.
3. Re-open `[FILE 22 TEST] Founder Publishing Acceptance` in Home Feed.
4. Verify the card excerpt matches the controlled canonical body and contains no Global Clinic text.
5. Verify `Platform Testing` remains while the automatic default Category chip is absent.
6. Open the controlled single post and the long cholesterol publication.
7. Verify Markdown emphasis/headings render semantically and raw `**` markers are absent.
8. Verify the content, action row, comments, images, and tables remain within the content column.
9. Verify ordinary WordPress posts and explicit non-default categories remain unchanged.
10. Complete mobile, tablet, desktop, keyboard, RTL, rollback, and duplicate-copy checks before live promotion.

Automated checks and a successful package build do not by themselves authorize production deployment.
