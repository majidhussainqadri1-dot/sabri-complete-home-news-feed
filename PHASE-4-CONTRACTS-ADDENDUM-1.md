# Phase 4 Contract Addendum 1 — Editorial Completeness Freeze

Target development line: `1.2.0`

Development branch: `build/phase-4-editorial-news-1.2.0`

Normative parent: `PHASE-4-CONTRACTS.md`

Status: **approved planning addendum; implementation still requires checkpoint tests and staging acceptance**

## 1. Purpose and precedence

This addendum closes planning gaps discovered during the completeness audit of the Phase 4 Editorial News and Global Newsroom plan.

It is normative. Where it is more specific than `PHASE-4-CONTRACTS.md`, the more specific rule in this addendum governs. It does not weaken any security, privacy, accessibility, rollback, or release gate in the parent contract.

## 2. Frozen editorial sections

The initial `sabri_news_section` allow-list is:

| Slug | American English label |
|---|---|
| `platform-news` | Platform News |
| `classical-homeopathy` | Classical Homeopathy |
| `homeopathy-research` | Homeopathy Research |
| `clinical-education` | Clinical Education |
| `materia-medica` | Materia Medica |
| `repertory` | Repertory |
| `public-health` | Public Health |
| `medical-research` | Medical Research |
| `pathology-anatomy` | Pathology and Anatomy |
| `nutrition-hygiene` | Nutrition and Hygiene |
| `homeopathy-education` | Homeopathy Education |
| `universities-conferences` | Universities and Conferences |
| `doctors-global-clinics` | Doctors and Global Clinics |
| `professional-regulatory` | Professional and Regulatory News |
| `islamic-spiritual-healing` | Islamic Spiritual Healing |
| `founder-updates` | Founder Updates |
| `research-center-news` | Research Center News |
| `worldwide-health-developments` | Worldwide Health Developments |

Rules:

- Administrators may add terms only through the controlled taxonomy capability.
- Unknown submitted section slugs fail validation; they are not silently created by contributors.
- Political, entertainment, celebrity, gambling, pornography, hate, weapons, unrelated commercial promotion, and other off-mission material are not valid editorial sections.
- Public-health or regulatory reporting may mention political or legal actors only when directly necessary to the health, professional, institutional, or regulatory story.

## 3. Frozen content-policy boundary

Permitted Editorial News must have a direct and demonstrable relationship to one or more approved sections and must be one of the frozen article types.

Prohibited publication includes:

- fabricated or knowingly misleading information;
- unsupported miracle, guaranteed, or universal-cure claims;
- individualized diagnosis, emergency treatment, or personal prescribing presented as News;
- identifiable patient records or contact details;
- copied third-party articles or images without lawful permission;
- undisclosed advertising, affiliate promotion, or paid placement;
- content whose dominant purpose is unrelated politics, entertainment, sensationalism, harassment, hatred, obscenity, or spam;
- machine-generated or machine-translated content published without human editorial review;
- anonymous allegations lacking the heightened verification and legal/editorial approval required by policy.

## 4. Frozen role-to-capability matrix

Capability assignments are additive and must not destructively replace existing WordPress roles. Implementations may use companion role mapping or filters, but the effective authority must not exceed this matrix by default.

Legend: `A` allowed by default, `S` allowed only within assigned section/object, `O` own objects only, `N` not granted by default.

| Capability | Administrator | Founder | Editor-in-Chief | Managing Editor | Section Editor | Medical Reviewer | Reporter | Verified Doctor Submitter | Translator | Reader |
|---|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|
| `read_editorial_news` | A | A | A | A | A | A | A | A | A | A |
| `create_editorial_news` | A | A | A | A | S | O | O | N | O | N |
| `edit_own_editorial_news` | A | A | A | A | A | O | O | N | O | N |
| `edit_others_editorial_news` | A | A | A | A | S | N | N | N | N | N |
| `submit_editorial_news` | A | A | A | A | A | A | A | A | A | N |
| `review_editorial_news` | A | A | A | A | S | N | N | N | N | N |
| `fact_check_editorial_news` | A | A | A | A | S | N | N | N | N | N |
| `medical_review_editorial_news` | A | A | A | A | N | A | N | N | N | N |
| `publish_editorial_news` | A | A | A | N | N | N | N | N | N | N |
| `schedule_editorial_news` | A | A | A | A | S when separately granted | N | N | N | N | N |
| `manage_breaking_news` | A | A | A | N | N | N | N | N | N | N |
| `manage_news_sources` | A | A | A | A | S | S for assigned medical review | O | O submission only | O translation sources only | N |
| `manage_news_corrections` | A | A | A | A | S request/recommend | N | N | N | N | N |
| `retract_editorial_news` | A | A | A | N | N | N | N | N | N | N |
| `translate_editorial_news` | A | A | A | A | S | terminology review only | N | N | A | N |
| `manage_news_taxonomies` | A | A | A | A | N | N | N | N | N | N |
| `manage_news_settings` | A | A | N | N | N | N | N | N | N | N |

Additional rules:

- Founder immediate publishing is an explicit institutional policy, not a bypass of privacy, source, correction, or audit rules.
- Editor-in-Chief may publish only after mandatory prerequisites are satisfied.
- Managing and Section Editors may recommend publication but cannot publish unless separately granted through an audited administrator decision.
- A Medical Reviewer may create notes or a review record for an assigned article but cannot rewrite unrelated editorial content by default.
- A Reporter, Verified Doctor Submitter, or Translator cannot approve or publish their own work.
- Students and patients are Readers unless another role is explicitly and lawfully assigned.

## 5. Frozen submission state machine

The News submission workflow uses these states:

```text
submitted
initial-review
needs-more-information
accepted-for-editing
rejected
converted-to-news-draft
published
```

Allowed transitions:

```text
submitted -> initial-review
initial-review -> needs-more-information
needs-more-information -> submitted
initial-review -> accepted-for-editing
initial-review -> rejected
accepted-for-editing -> converted-to-news-draft
converted-to-news-draft -> published
```

Rules:

- `published` is a terminal submission projection pointing to the published Editorial News object; it does not replace the article workflow.
- Submitters can read only their own safe submission projection.
- Internal editor notes, reviewer identities where confidential, source-confidence notes, and moderation deliberations are excluded from submitter projections.
- Rejection requires a safe reason category; private legal or moderation notes remain private.
- Conversion is idempotent and creates at most one linked News draft per accepted submission unless an administrator performs an audited recovery action.

## 6. Frozen fact-check checklist

Before `ready-for-publication`, the responsible editorial workflow must record a decision for every applicable item:

1. Headline accurately reflects the verified source material.
2. Names, titles, institutions, dates, countries, and locations are correct.
3. Direct quotations are authentic, accurately attributed, and not materially removed from context.
4. Primary sources were sought and used where reasonably available.
5. Medical or scientific claims match the study design and evidence strength.
6. Association is not rewritten as causation.
7. Sample size, population, limitations, uncertainty, and material conflicting evidence are represented fairly.
8. Commercial sponsorship, institutional affiliation, authorship, product ownership, and other conflicts are disclosed.
9. Patient, contact, account, and identity data have been removed or lawfully authorized under a separately approved consent policy.
10. Images and documents have verified ownership, license, credit, alt text, and consent status where relevant.
11. The article is original editorial work and does not copy a protected third-party article.
12. Source URLs and citations are valid, safe, and linked to the correct claim.
13. Opinion, analysis, press release, sponsored, and partner content are clearly labelled and cannot imitate straight News.
14. Emergency medical advice, individualized diagnosis, or unsafe personal prescribing is absent.
15. Required medical/public-information disclaimer is present.
16. Known corrections, retractions, regulatory warnings, or conflicting authoritative reports were checked.

A missing applicable decision prevents publication. A checklist value may be `pass`, `not-applicable-with-reason`, or `fail`; `fail` prevents publication.

## 7. Frozen headline policy

Headlines must:

- be factually supported by the article and its sources;
- distinguish preliminary, observational, preprint, animal, laboratory, survey, trial, review, and regulatory evidence where material;
- label opinion, analysis, editorial, interview, press release, sponsored, or partner content visibly;
- avoid clickbait, false urgency, and misleading omission;
- avoid `miracle`, `guaranteed`, `100% cure`, `proven cure`, or equivalent language unless the extraordinary claim is both accurate and approved under the medical-evidence policy;
- avoid claiming that a study proves more than its design permits;
- avoid naming an identifiable patient without separately approved lawful authority;
- remain within the configured bounded length.

Breaking status does not relax the headline policy.

## 8. Frozen medical and clinical safety policy

Medical or health-related Editorial News must:

- distinguish reporting, institutional opinion, and individualized clinical advice;
- state the evidence type and material limitations;
- undergo medical/scientific review when a treatment, diagnosis, safety, outcome, or public-health claim requires it;
- avoid personal prescriptions, emergency treatment, or instructions that could replace professional assessment;
- direct emergencies to appropriate local emergency or in-person care rather than to online News interaction;
- preserve uncertainty and conflicting evidence honestly;
- comply with patient-identifier and media-consent rules.

Default public-information disclaimer when applicable:

```text
This article is for education and public information. It does not replace individualized medical diagnosis, emergency care, or professional consultation.
```

The disclaimer is not a substitute for accurate evidence, privacy, or safe wording.

## 9. Frozen composer validation

Publication validation requires the parent-contract fields plus:

- source-backed factual assertions;
- a controlled section and article type;
- image credit, license, alt text, and consent fields when media exists;
- conflict-of-interest decision;
- completed fact-check checklist;
- medical-review decision where required;
- public label for editorial/opinion/analysis/press-release/sponsored/partner content;
- correction/retraction relationship when applicable;
- safe canonical slug;
- bounded SEO title and meta description;
- breaking start/expiry when breaking is enabled;
- scheduling time in the configured site timezone with UTC storage.

The composer must warn or block, according to policy, for:

- duplicate or near-duplicate headline;
- duplicate normalized source URL;
- invalid, unsafe, or broken source URL where validation is available;
- missing source for a factual claim;
- excessive headline, summary, body, taxonomy, source, or attachment limits;
- missing image rights/alt text;
- patient/contact identifiers;
- unsupported embeds or HTML;
- scheduled publication in the past or with revoked prerequisites;
- breaking expiry not later than its start.

Warnings never silently transform invalid values into publishable values.

## 10. Frozen public search and filter contract

Public News search may expose only public, indexable projections and supports bounded combinations of:

- keyword;
- section;
- topic;
- country;
- region;
- article type;
- publication date range;
- public author/institution;
- research label;
- active or historical Breaking label where policy permits;
- corrected status;
- retracted-notice status.

Rules:

- Draft, submission, preview, review, private source, and internal-note values never become search facets.
- Retraction search results expose only the approved public retraction projection, not the hidden original body.
- Filters are allow-listed, normalized, bounded, and included in safe cache keys.
- Empty or invalid filters fail safely without widening the query to private states.

## 11. Frozen public distribution routes

The initial exact public routes are:

```text
/news/
/news/{article-slug}/
/news/section/{slug}/
/news/topic/{slug}/
/news/country/{slug}/
/news/region/{slug}/
/news/type/{slug}/
/news/feed/
/news/section/{slug}/feed/
/news-sitemap.xml
```

Rules:

- The sitemap and RSS routes are separately gated where specified.
- Canonical, Open Graph, structured data, sitemap, RSS, and visible page state must agree.
- Drafts, previews, submissions, queues, unpublished translations, and hidden retracted bodies are `noindex` and excluded.
- Published translations may use language-specific canonical URLs and `hreflang` only after translation review.
- Google News or other publisher integrations are not automatically enabled; eligibility and policy compliance require a separate reviewed adapter and staging validation.

## 12. Frozen public article labels

The initial public disclosure labels are:

```text
News
Breaking News
Research News
Editorial
Opinion
Analysis
Interview
Event Report
Official Announcement
Press Release
Sponsored
Partner Content
Correction
Retraction
AI-generated illustration
```

The visible label, REST projection, feed card, schema, RSS, and social metadata must not contradict one another.

## 13. Copyright, attribution, and media rights

- Full third-party articles must not be copied into the Newsroom.
- Quotations are limited to what is necessary, accurately attributed, and compliant with applicable rights policy.
- Press releases remain labelled as press releases unless independently reported and rewritten with additional verification.
- Every public image requires source/photographer, copyright owner, license or permission basis, alt text, and applicable consent state.
- Watermarks, credits, or ownership notices must not be removed to disguise origin.
- AI-generated media must be clearly disclosed and must not be used to fabricate a real medical event, patient, document, or quotation.
- Contributor ownership declarations are retained privately and audited.

## 14. Conflict-of-interest contract

The workflow records, where applicable:

- institutional affiliation;
- clinic or professional affiliation;
- product or service ownership;
- financial interest;
- paid promotion;
- research authorship;
- conference or event sponsorship;
- supplied content or press-release origin.

Material conflicts are publicly disclosed. Undisclosed commercial influence may trigger revision request, rejection, correction, contributor restriction, or retraction according to severity.

## 15. Interaction boundary

Published News may use existing Phase 3 Like, Dislike, Save, Share, Comment, Report, Notification, and privacy-safe View services only after:

- the News object type is explicitly allow-listed;
- permission and visibility tests pass;
- private News states cannot be discovered through interactions;
- public counts contain aggregate data only;
- a disabled Phase 3 gate removes the corresponding control without breaking News rendering.

## 16. Completion definition

Phase 4 planning is contract-complete only when the following documents exist and agree:

- `PHASE-4-CONTRACTS.md`;
- this addendum;
- `PHASE-4-ARCHITECTURE.md`;
- `PHASE-4-SECURITY-PRIVACY.md`;
- `PHASE-4-EDITORIAL-POLICY.md`;
- `PHASE-4-HOSTINGER-STAGING-ACCEPTANCE-CHECKLIST.md`;
- `PHASE-4-ROLLBACK-RUNBOOK.md`;
- `PHASE-4-COMPLETENESS-AUDIT.md`.

Planning completeness does not mean implementation, staging acceptance, merge approval, version promotion, or live deployment.