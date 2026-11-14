# File 21 — 2026-11-12 Exact-Head Pull Request Reconciliation Audit
## زیرِ التوا اصلاحات کی سورس ملکیت، متعارض فائلیں، محفوظ انضمام اور قبولیتی مراحل

**مرکزی منصوبہ:** «صابری سوشل ہومیوپیتھی پلیٹ فارم — جامع مرکزی ماسٹر پلان V12.1»، File 21 Home / News / Feed / Publishing۔  
**بنیادی `main` SHA:** `626b380328d4400014504e679032291cd023bbdb`۔  
**رپورٹ کی نوعیت:** صرف GitHub repository source + موجودہ PR metadata / changed filenames، نہ staging/Live/DB parity۔  
**تاریخ:** 12 نومبر 2026ء۔

## ۱۔ کھلی درخواستوں کی اصلی حالت

| PR | مرکز | GitHub mergeable (اس snapshot میں) | اصل target/main سے صورت |
|---|---|---|---|
| #32 | وسیع 1.0.6، 40-round review | **نہیں** | پرانا base SHA؛ حقیقی باقی مفید delta الگ کرنا لازم |
| #41 | 80-round review | **نہیں** | پرانا base؛ متعدد runtime changes |
| #44 | جدید cross-owner contracts + hardening | **نہیں** | File26 registry اور File03 timeline کے ساتھ overlap |
| #48 | File04 migration + File26 parity | **نہیں** | File26 registry اور build workflow overlap |
| #50 | File03 owner timeline | **نہیں** | موجودہ `main` میں interface موجود؛ پرانی PR کو blind merge کرنا غلط |
| #54 | Post-merge release evidence / File19 | **ہاں، اس snapshot میں** | وسیع workflow/document overlap؛ تازہ HEAD پر دوبارہ مطابقت لازم |
| #59 | File26 durable monotonic owner events (#58) | **ہاں، اس snapshot میں** | source `includes/class-search-provider-registry.php` کے ساتھ #61/#44/#48 overlap |
| #61 | File26 v1:id keyset shadow pagination (#60) | **ہاں، اس snapshot میں** | registry + File26 exact workflow، #59 سے overlap |
| #63 | File03 global signed cursor + local profile author policy (#62/#64) | **ہاں، اس snapshot میں** | profile timeline کے ساتھ #41/#44/#50 overlap |

**احتیاط:** GitHub کا `mergeable=false` ہر صورت source conflict کا واحد ثبوت نہیں؛ `changed filenames` کا تقابل محض overlap بتاتا ہے، لازماً line-level تعارض نہیں۔ پھر بھی پرانے bases پر بغیر diff/CI validation انضمام جائز نہیں۔

## ۲۔ شناخت شدہ source-overlap، جو merge order بدلنے پر test assumptions بدل سکتے ہیں

| مشترک فائل | PR جو اسے بدلتے ہیں | لازمی فیصلہ |
|---|---|---|
| `includes/class-search-provider-registry.php` | #44، #48، #59، #61 | حقیقی File26 contract اور ہر tested fix کو merged سورس میں **ایک** مرتبہ برقرار رکھنا؛ stale methods overwrite نہ ہوں |
| `.github/workflows/file21-file26-real-contract.yml` | #32، #44، #54، #59، #61 | محفوظ exact-source checks، monotonic owner sequence + keyset + current File26 API ایک مشترک exact workflow میں |
| `includes/class-profile-timeline.php` | #41، #44، #50، #63 | File03 provider hooks، signed cursor، immutable owner checks، local shortcode safe query، File25 UI ownership |
| `tests/run-file21-file03-profile-timeline-contract.php` | #50، #63 | نئی source query invariant کا regression؛ پرانا token-only assertion نہ لوٹے |
| `.github/workflows/build-test-home-news-feed.yml` | #32، #48، #50، #54، #63 | اصل 1.0.5 package identity، تازہ tests اور prior required gates مکمل محفوظ |

## ۳۔ محفوظ عارضی امتزاج: #59 + #61

Test-only branch `audit/file21-file26-pr59-pr61-integration-20261112` PR #61 کے exact source head سے بنایا گیا اور اس کی registry میں صرف PR #59 کے tested durable event-version segments کو ساتھ جوڑا گیا؛ دونوں کی سابق الگ PRs، `main` اور Live کو نہیں بدلا۔

- Combination: PR #59 کے `file26_advance_object_version()` اور `file26_document( $post, $version )` owner event semantics؛ PR #61 کے `file26_list_batch()` `v1:id:` cursor + legacy rejection۔
- First exact composite source audit: `0a7feb7557a8e39a67d75e6dc93ae0220bf455cf`؛ تینوں jobs کامیاب: PHP tests، ephemeral MariaDB، ephemeral WordPress۔
- Additional audit job at next exact head confirms File26 main `1b064c6ea9f81195611ed001d5457e8ced65460a` public API, source-event precedence, callbacks/lifecycle; اس کے نتائج **صرف نئی exact CI** کے بعد درج کیے جائیں۔
- This **does not prove** actual File26 v12 Draft PR #22 shadow index job, DB/job migration, atomic cutover, deleted/republished source-to-index exact-key equality, or Live re-test۔

## ۴۔ Source-first محفوظ کارروائی کی ترتیب

1. Freeze deployed reality separately from GitHub؛ Live plugin ZIP/checksum، DB schema/version، migration/options، job cursor values، logs اور staging artifact۔ اگر Live access نہیں تو اس کی عدمِ تصدیق واضح رکھیں؛ GitHub findings کو Live bug نہ کہیں۔
2. Close **source gap** #58 and #60 only after integrated candidate branch, exact independent File26 source API, author policy and real staging File26 shadow-job acceptance (not simply standalone PR green).
3. Rebase/reconcile #63 with current main and #41/#44/#50 profile timeline delta؛ assert all File03 current contract user/author/source bounds and four-page signed-cursor tests on new **merged** HEAD۔
4. Separate #44/#48 features against current master plan and actual File04/File22/File24 contracts؛ avoid reintroducing deprecated adapters or old ranking boosts۔
5. Reconcile workflows/test inventories; retain pinned source interfaces, build manifest, security gates and current 1.0.5 package identity (do not silently claim 1.0.6 Live).
6. Run source PHP unit/runtime/integration + ephemeral MariaDB and WordPress + ten browser suites, and future File26+03/21 **real WordPress/MariaDB co-installation** staging tests.
7. Package, verify source SHA, ZIP hash and staging rollback. Then authorized production deployment → Live real-world retest → checksum parity → release sign-off.

## ۵۔ نقص و ذمہ داری حساب

| طبقہ | معلوم سورس مسئلے | درستی کی حیثیت |
|---|---:|---|
| File26 owner metadata version #58 | 1 | Draft PR #59؛ source CI کامیاب، Live pending |
| File26 shifting offset reindex #60 | 1 | Draft PR #61؛ source CI کامیاب، Live pending |
| File03 signed timeline cursor #62 | 1 | Draft PR #63؛ owner/consumer source آزمائش، Live pending |
| File21 local shortcode wrong-author projection #64 | 1 | Draft PR #63؛ source guards/test، Live pending |
| دیگر 30 NG feature و 26 charter/shared CV acceptance | **حتمی نہیں** | الگ requirement-by-requirement acceptance |

**حاکم نتیجہ:** Source میں code موجود ہونا، CI green ہونا، PR mergeable ہونا یا GitHub README میں fixed لکھا ہونا **Live نفاذ کا ثبوت نہیں**۔

**Repository HEAD / Deployed Version / DB Version / Migration State / Live Verification Status:** Main `626b380328d4400014504e679032291cd023bbdb` at freeze / unverified / unverified / unverified / unverified۔

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**
