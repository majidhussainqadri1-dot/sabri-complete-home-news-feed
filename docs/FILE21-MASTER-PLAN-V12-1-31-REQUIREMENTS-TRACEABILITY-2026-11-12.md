# File 21 — Master Plan V12.1 / Exact-Source 31-Requirement Traceability
## «صابری سوشل ہومیوپیتھی پلیٹ فارم — جامع مرکزی ماسٹر پلان V12.1» کے مطابق فائل 21 کا تقاضہ وار حتمی نہیں بلکہ جاری ماخذی آڈٹ

**تاریخ:** 12 نومبر 2026ء۔ **سورس فریز:** GitHub File21 `main` `626b380328d4400014504e679032291cd023bbdb`؛ master plan V12.1 میں F21-CEN-01 اور F21-NG-01 تا F21-NG-30۔  
**اصولِ ثبوت:** Manifest یا ٹیسٹ میں نشان، مکمل عملی فیچر نہیں۔ GitHub green CI = repository evidence؛ deployed version، DB/migration، Live verification کے ثبوت نہیں۔ PR #63 کے بعد نئی HEAD اور تمام amendments کی جانچ لازم۔

## ۱۔ مجموعی تفریق

- **31 منفرد منصوبہ شناختیں:** F21-CEN-01 + 30 NG features۔ مرکزی Charter میں Home/Following/Latest/For You/Editorial News، Why This، Mute، Reset، correction/retraction propagation، Healthy Use یکجا لازم۔
- **30/30 manifest IDs:** `includes/class-next-generation-feed.php::feature_manifest()` میں موجود؛ `tests/run-file21-next-generation-30-tests.php` موجودگی، بیسک API، guard tokens اور assets جانچتا ہے۔ یہ مکمل WordPress end-to-end یا ہر خارجی owner کی دستیابی ثابت نہیں کرتا۔
- **File21 local-owned 23 IDs / other canonical owners 7 IDs:** 12–14 (File16)، 25–26 (File26)، 28 (File25)، 30 (File19)۔ Manifest میں external owners واضح ہیں۔
- **کم از کم 5 مقامی NG IDs میں موجودہ source amendment زیرِ نظر:** 01/02 Repost/Quote، 07 Expert Context، 20 Low Bandwidth اور 22 Data Saver؛ PR #44 میں تازہ public-original، source-aware notes اور media suppression کی اصلاح زیرِ نظر ہے، `main` میں نافذ ثابت نہیں۔
- **دو اضافی F21 Profile Timeline source defects:** Issue #62 cursor pagination، Issue #64 shortcode query author spoof؛ دونوں کا اصلاحی سورس Draft PR #63 میں۔
- **دو اضافی File26 cross-boundary source defects:** Issue #58 metadata-only version sequence (Draft PR #59) اور Issue #60 unstable offset shadow-pagination (Draft PR #61)؛ یہ File21 source fixes ہیں مگر main/Live پر نہیں۔
- مکمل موافقت کا قطعی عدد **31 میں 31 مکمل** قرار نہیں دیا جاسکتا؛ unit/source tests، external providers، migration اور Live gates الگ ہیں۔

## ۲۔ منصوبہ بمقابلہ موجودہ Repository کا تفصیلی رجسٹر

| شناخت | ماسٹر پلان کا اصل تقاضہ / مصدر | GitHub میں پہچانی گئی سورس بنیاد | غیر مصدقہ یا باقی قبولیت |
|---|---|---|---|
| F21-CEN-01 | Following / Latest / For You / Editorial News تفریق، Why This، mute/reset، retraction propagation، healthy-use | File21 local feed + editorial + feed controls | تمام modes، correction/retraction downstream delivery، mobile/RTL اور Live کا مشترک امتحان |
| NG-01 | Repost | `NextGenerationFeed` action + original relation | PR #44 original public-state hardening، real publication tests |
| NG-02 | Quote Post | `NextGenerationFeed` quote source/context | PR #44 strict original relation، quote author attribution tests |
| NG-03 | Threads / Series | `META_THREAD_ID`, `META_THREAD_ORDER` | Stable Previous/Next، edits/withdrawals اور author authorization |
| NG-04 | Collaborative Posts | `META_COAUTHORS` | File00/09 canonical identities، current verified attribution، no delegated overreach |
| NG-05 | 24h Professional Stories | `META_STORY_EXPIRES`, `DAY_IN_SECONDS` | Founder/professional authority، expiry and cache invalidation |
| NG-06 | Developing Story Timeline | source story grouping | Chronology، approved corrections and stale-source revocation |
| NG-07 | Expert Context / Notes | verified-expert source path | PR #44 source-aware bounded references، File00/09/24 assurance |
| NG-08 | Evidence Card | evidence-card projection | Original sources، uncertainty، qualification and review-date accuracy |
| NG-09 | Source Diversity | source diversity payload | Independently sourced domains/links/count and no fabrication |
| NG-10 | Full Edit & Correction History | WordPress revisions + `CorrectionLedger` | Actual corrected/retracted state propagation |
| NG-11 | Smart Share Warning | source warning builder and UI | Retraction/stale suppression and user-visible consent |
| NG-12 | AI 30-second Summary | File16 adapter contract | Actual File16 provider, AI label, source context, unavailable path |
| NG-13 | Ask This Article | File16 article-context adapter | Article-only scope, no autonomous clinical prescription |
| NG-14 | Intelligent Translation | File16/service relation | Original always available, machine/human method, Urdu/Arabic RTL |
| NG-15 | Follow Topics | private topic-follow state | Identity/nonce, unlimited/noisy group controls, privacy |
| NG-16 | My Topics Feed | local chronological topic feed | Owner feed vs File26 global ranking separation |
| NG-17 | Catch-Up | last marker and bounded recap | Correction/removal invalidation, healthy-use behavior |
| NG-18 | Continue Reading | private reading progress | Read state privacy, 100% bounded cleanup |
| NG-19 | Read Later Queue | private queue and click-time gate | Reauthorization after item withdrawal |
| NG-20 | Low Bandwidth | body class / CSS mode | PR #44 server-transfer avoidance beyond hidden heavy media |
| NG-21 | Offline Feed Pack | authorized text/evidence/URL pack | Export re-authorization and modified/version freshness |
| NG-22 | Data Saver | explicit state / CSS | PR #44 heavy video/iframe suppression at transfer level |
| NG-23 | Doctor Answer Badge | Q&A and canonical verification path | Exact File00/09 current verification; role revocation test |
| NG-24 | Structured Q&A | bounded authenticated Q&A | Moderation, audit and false-answer controls |
| NG-25 | Why Trending | File26 filter/render | Actual File26 reason/window/source count and truthful unavailable |
| NG-26 | Related Graph Cards | File26 related-knowledge filter | Source owners, patient/privacy-safe graph |
| NG-27 | News Compare | 2–4 public item payload | Correction dates, evidence/source diversity accuracy |
| NG-28 | Shareable Knowledge Cards | File21 semantic payload + File25 renderer | File25 actual visual compatibility and stale/retracted warning |
| NG-29 | Personal Feed Recipe | user preference blend/local feed | No donor/payment/Founder advantage, no File26 ownership theft |
| NG-30 | Daily/Weekly Digest | candidate provider + File19 adapter | Actual File19 deliver/quiet hours/retries/opt-out; no duplicate transport |

**ثبوت کا معیار:** جدول کا تیسرا ستون صرف source-code path/manifest/contract evidence ہے۔ چوتھے ستون میں "باقی" کا مطلب لازماً غیر موجود code نہیں، بلکہ اس مشترک یا حقیقی قبولیتی ثبوت کا اس آڈٹ میں نہ ملنا ہے۔ مفروضے کو ثابت شدہ defect نہ کہا جائے۔

## ۳۔ مکمل منصوباتی کارروائیوں کی ترجیحی ترتیب

| ترجیح | ذمہ داری / مسلسل شناخت | ثبوت / عملی رکاوٹ |
|---|---|---|
| P0 | File21→File26 metadata version + privacy tombstone | Issue #58 / Draft PR #59؛ true DB/Live gap |
| P0 | File21→File26 shadow source exact-key completeness | Issue #60 / Draft PR #61؛ old offsets migration and File26 v12 PR22 |
| P0 | File21→File03 signed profile cursor pages | Issue #62 / Draft PR #63؛ exact File03 current consumer |
| P0 | File21 local profile true canonical author / review guard | Issue #64 / Draft PR #63؛ hostile filter test |
| P0 | Repost, Quote, Expert Notes, Low-Bandwidth transfer | Pending PR #44; current main lacks new amendments |
| P0 | File04 migration inventory, receipts, authorization, rollback | Pending PR #48; actual DB/Legacy evidence unverified |
| P0 | File22 governance/lifecycle and File24 assurance | Pending PR #44, real current heads + tests |
| P1 | File03 timeline, File19 digest, File25 cards, File26 graph actual staging | Structural adapters present, end-to-end owner proofs pending |
| P1 | Earlier PR #32/#41/#44/#48/#50/#54 reconciliation | Base divergence, conflict/overlap, duplication inspection; do not merge blind |
| Final Gate | release/CI, old jobs, backup/rollback, Staging, Live parity | No current authenticated WordPress runtime/DB verification |

## ۴۔ لازمی تخریج اور قبولیت

1. ہر PR کے متعلقہ **اصل source root cause** کا test اور exact SHA محفوظ؛ صرف README یا CI status کافی نہیں۔
2. دوسرے owner کا interface اسی owner کی actual HEAD/contract سے جانچیں، assumed version سے نہیں۔
3. PR #63 کی source fixes کو #59/#61 کے ساتھ blind stacking نہ کریں؛ overlap اور release manifest parity پہلے۔
4. Post review publication / restriction / deletion، File26 search، File03 timeline اور File19 notifications میں same-user، anonymous، guardian/minor، suspended، corrected/retracted اور deleted object scenarios ضروری۔
5. Staging: Active plugins/versions، deployed package checksums، DB columns/schema/options، migration/worker state، logs، provider activation، cache invalidation اور rollback record کریں۔
6. Live: explicit deploy کا evidence اور live re-test؛ ناکافی ثبوت میں نتیجہ صرف Repository-candidate ہی رہے۔

**حتمی شمار:** اس دستاویز میں 31 منصوبہ شناختوں کا Source-to-Requirement ربط محفوظ ہے۔ اس review session میں **2 نئے source defects** (Issues #62/#64)، دونوں کے code candidates (PR #63)، اور 2 پہلے سے کھلے File26 defects (Issues #58/#60) درج ہیں۔ باقاعدہ بیرونی staging/Live acceptance ابھی باقی ہے۔

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**
