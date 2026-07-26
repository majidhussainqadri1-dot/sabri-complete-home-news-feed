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
- corrective public-component settings.

## Required screenshots

1. Home — logged out — 1440px desktop.
2. Home — Founder logged in — 1440px desktop.
3. Home — 768px tablet.
4. Home — 390px mobile.
5. Home — 320px mobile.
6. Home — 200% browser zoom.
7. Founder Profile — profile header plus Timeline.
8. Founder Timeline — first page and empty state fixture.
9. News archive `/news/`.
10. Single News article.
11. Active Breaking News strip.
12. Corrected article with correction disclosure.
13. Retracted article public projection.
14. RSS `/news/feed/` when enabled.
15. Sitemap `/news-sitemap.xml` when enabled.
16. Activation Wizard dependency and duplicate-protection sections.
17. Migration screen legacy Founder candidate preview.

## Functional acceptance

- Founder post publishes as `publish` plus review state `approved`.
- Administrator post publishes as `publish` plus review state `approved`.
- Founder/Administrator Composer does not display **Submit for Review**.
- Unverified Doctor remains review-required.
- No existing post, page, user, comment, media item, or permalink is deleted.
- Legacy restoration publishes only individually selected valid candidates.
- Privacy-held and protected moderation states remain blocked.
- Corrective Home auto-mount is blocked when an existing Feed shortcode is detected.
- At most one File 21 Feed surface renders per request.
- File 21 inserts no primary, sidebar, or mobile navigation.
- Unified Shell duplicate navigation destinations are reported.
- Profile Timeline exposes only posts the current viewer may access.
- Profile Timeline pagination is bounded to 20 items per request.
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
- Data-loss events: 0

Any runtime code correction invalidates previous elapsed continuous-QA time and requires the complete QA protocol to restart from zero.
