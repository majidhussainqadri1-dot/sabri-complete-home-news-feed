# File 21 Harmonized Release — Live Visual and Functional Acceptance Checklist

This checklist is mandatory before File 22 begins or any live deployment is authorized. Source merge, plugin version promotion and automated CI do not replace real WordPress evidence.

## Evidence identity

Record for every screenshot and test session:

- exact 40-character File 21 commit SHA;
- exact 40-character companion File 20 commit SHA;
- immutable ZIP filenames;
- ZIP SHA-256 values;
- plugin version `1.0.1` and schema version `1.0.0`;
- WordPress version;
- PHP version;
- environment URL;
- date and UTC time;
- user role, identity authority and login state;
- enabled Phase 4 gates;
- enabled Phase 5 gates;
- corrective public-component settings;
- detected existing Feed shortcode and controlled-replacement state;
- File 04 candidate/mapping counts;
- companion integration registry statuses.

## Required screenshots

1. Home — logged out — 1440px desktop.
2. Home — Founder logged in — 1440px desktop.
3. Home — institutionally trusted verified Doctor logged in.
4. Home — unverified Doctor logged in.
5. Home — 768px tablet.
6. Home — 390px mobile.
7. Home — 320px mobile.
8. Home — 200% browser zoom.
9. Home — reduced-motion preference.
10. Home — forced-colors/high-contrast mode.
11. Home with a legacy Feed shortcode — duplicate guard blocking state.
12. Home with controlled runtime replacement — exactly one File 21 surface.
13. File 20 official `home-main` slot containing File 21, with no legacy Latest duplicate.
14. File 20 official Home right-sidebar slot.
15. Complete Home control bar, including Most Viral and all cross-module destinations.
16. Most Viral and For You rows with truthful bounded data.
17. Founder Profile — existing File 03 profile plus integrated Timeline.
18. Member/Doctor Profile — existing File 03 profile plus integrated Timeline.
19. Hidden or unverified-profile notice without a Timeline.
20. Founder Timeline — first page and empty state fixture.
21. Feed card showing public profile URL, specialty, country and clinic.
22. News archive `/news/` through the official File 20 News slot.
23. Single News article.
24. Active Breaking News strip.
25. Corrected article with disclosure.
26. Retracted article public projection.
27. RSS `/news/feed/` when enabled.
28. Sitemap `/news-sitemap.xml` when enabled.
29. Comprehensive Activation Wizard: identity, legacy content, components, duplicates, integrations, Search and gates.
30. Founder pending-post restoration preview.
31. File 04 selected migration preview.
32. File 04 successful selected migration mapping.
33. Legacy File 04 URL performing the expected 301 redirect.
34. File 04 non-destructive rollback showing private target and disabled redirect.
35. Global Search showing authorized Posts and approved News only.

## Functional acceptance

- Founder post publishes as `publish` plus review state `approved`.
- Administrator post publishes as `publish` plus review state `approved`.
- Institutionally trusted verified Doctor publishes immediately.
- Verified but untrusted Doctor follows the configured trusted-only policy.
- Unverified Doctor remains review-required.
- Students and Patients cannot use the professional social Composer.
- Editorial Newsroom roles do not receive implicit social-Composer authority.
- Founder/Administrator Composer does not display **Submit for Review**.
- No existing post, page, user, comment, media item, permalink or legacy publication is deleted.
- Founder restoration publishes only individually selected valid candidates.
- Privacy-held and protected moderation states remain blocked.
- File 04 migration copies only individually selected candidates.
- File 04 source publications remain intact after migration.
- Dates, author, featured image, approved comments and mapped topics are preserved where available.
- Migration creates one auditable source-to-target mapping and no duplicate target.
- Rollback makes the target private, disables redirect, preserves both records and records audit evidence.
- Corrective Home auto-mount is blocked when an existing Feed shortcode is detected and controlled replacement is disabled.
- Controlled replacement substitutes the first known Feed shortcode at render time, removes additional duplicate Feed shortcodes from the request and does not alter saved page content.
- At most one File 21 Feed surface renders per request.
- File 20 owns placement and global navigation; File 21 inserts no primary, sidebar or mobile navigation.
- Official File 20 Home, News and right-sidebar slots render only when providers return content.
- The legacy File 20 Latest Feed is suppressed when File 21 owns Home.
- Complete Home control bar destinations resolve correctly.
- Most Viral uses bounded views, reactions, comments, saves, shares, watch time, freshness, quality and report penalties without fabricated values.
- Cross-module rows show normalized provider items or one truthful module entry card; they never fabricate content.
- Existing File 03 Founder and Member/Doctor profiles receive the functional Timeline when enabled.
- Profile Timeline exposes only posts the current viewer may access.
- Profile Timeline does not expose WordPress `found_posts` counts for restricted content.
- Timeline page size is bounded to 20 and scanning is bounded to 500 posts.
- Feed cards expose only approved public author metadata.
- Global Search is bounded and object-authorized.
- Editorial News, Breaking News, corrections, RSS, schema, sitemap and notifications remain independently observable and reversible.
- Disabling Editorial News disables dependent public distribution gates.
- Emergency Disable closes public writes and public corrective components without deleting data.

## Defect gate

Acceptance requires:

- Fatal errors: 0
- Critical defects: 0
- High defects: 0
- Duplicate Feed surfaces: 0
- Duplicate navigation inserted by File 21: 0
- Unapproved privileged posts created during acceptance: 0
- Restricted Timeline or Search count disclosures: 0
- Fabricated cross-module items: 0
- Broken File 04 mappings or redirects: 0
- Data-loss events: 0
- Version-identity mismatches: 0

Any runtime, test, workflow, package or File 20 integration correction invalidates previous elapsed continuous-QA time and requires the complete final QA protocol to restart from zero.