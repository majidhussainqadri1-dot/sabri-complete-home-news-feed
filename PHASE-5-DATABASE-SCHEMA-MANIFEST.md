# Phase 5 Database Schema Manifest

Internal target: `phase5-final-1`. Plugin/schema constants remain `1.0.0` until the separately gated release-candidate promotion commit.

Tables:

1. `sabri_news_sources`
2. `sabri_news_reviews`
3. `sabri_news_submissions`
4. `sabri_news_submission_files`
5. `sabri_news_corrections`
6. `sabri_news_breaking`
7. `sabri_news_translations`
8. `sabri_news_preview_tokens`
9. `sabri_news_rate_limits`
10. `sabri_news_audit_integrity`

All tables use the dynamic WordPress prefix, additive `dbDelta` creation, internal identifier allow-lists, bounded repositories, verification of required indexes, and no foreign-key dependency that would make code rollback destructive.
