# File 21 Corrective Release — Live Visual Acceptance Checklist

This checklist is mandatory before version promotion, merge authorization, or live deployment. Automated CI does not replace this evidence.

## Evidence identity

Record for every screenshot and test session:

- exact 40-character commit SHA;
- immutable ZIP filename;
- ZIP SHA-256;
- WordPress version;
- PHP version;
- environment URL;
- date and UTC time;
- user role and login state;
- enabled Phase 4 gates;
- enabled Phase 5 gates;
- corrective public-component settings;
- detected existing Feed shortcode and whether controlled replacement is enabled.

## Required screenshots

1. Home — logged out — 1440px desktop.
2. Home — Founder logged in — 1440px desktop.
3. Home — 768px tablet.
4. Home — 390px mobile.
5. Home — 320px mobile.
6. Home — 200% browser zoom.
7. Home with an existing legacy Feed shortcode — duplicate guard blocking state.
8. Home with controlled runtime replacement enabled — exactly one identifiable File 21 surface.
9. Founder Profile — existing File 03 profile header plus automatically integrated Timeline.
10. Member/Doctor Profile — existing File 03 profile plus automatically integrated Timeline.
11. Founder Timeline — first page and empty state fixture.
12. News archive `/news/`.
13. Single News article.
14. Active Breaking News strip.
15. Corrected article with correction disclosure.
16. Retracted article public projection.
17. RSS `/news/feed/` when enabled.
18. Sitemap `/news-sitemap.xml` when enabled.
19. Activation Wizard dependency and duplicate-protection sections.
20. Migration screen legacy Founder candidate preview.

## Functional acceptance

- Founder post publishes as `publish` plus review state `approved`.
- Administrator post publishes as `publish` plus review state `approved`.
- Founder/Administrator Composer does not display **Submit for Review**.
- Unverified Doctor remains review-required.
- No existing post, page, user, comment, media item, or permalink is deleted.
- Legacy restoration publishes only individually selected valid candidates.
- Privacy-held and protected moderation states remain blocked.
- Corrective Home auto-mount is blocked when an existing Feed shortcode is detected and controlled replacement is not enabled.
- Controlled replacement substitutes the first known Feed shortcode at render time, removes additional duplicate Feed shortcodes from that request, and does not modify the saved page content.
- At most one File 21 Feed surface renders per request.
- File 21 inserts no primary, sidebar, or mobile navigation.
- Unified Shell duplicate navigation destinations are reported.
- The existing File 03 Founder profile automatically receives the functional Timeline when the Timeline component is enabled.
- The existing File 03 Member/Doctor profile automatically receives the functional Timeline when the Timeline component is enabled.
- Hidden or unverified-profile notice output does not receive a Timeline.
- Profile Timeline exposes only posts the current viewer may access.
- Profile Timeline does not expose WordPress `found_posts` counts for restricted content.
- Profile Timeline page size is bounded to 20 and candidate scanning is bounded to 500 posts.
- Editorial News, Breaking News, corrections, RSS, schema, sitemap, and notifications remain independently observable and reversible.
- Disabling Editorial News disables dependent public distribution gates.
- Emergency Disable closes public writes and public corrective components without deleting data.

## Defect gate

Acceptance requires:

- Fatal errors: 0
- Critical defects: 0
- High defects: 0
- Duplicate Feed surfaces: 0
- Duplicate navigation inserted by File 21: 0
- Unapproved Founder posts created during acceptance: 0
- Restricted Timeline count disclosures: 0
- Data-loss events: 0

Any runtime code correction invalidates previous elapsed continuous-QA time and requires the complete QA protocol to restart from zero.
