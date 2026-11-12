# فائل 21 — Home Feed کے ماخذی ضوابط، تاخیری فلٹرز اور رازداری کی اصلاح
## مرکزی منصوبہ V12.1؛ Issue #67؛ Exact-Repository Source Audit و قبولیتی امتحانات

**تاریخ:** 12 نومبر 2026ء۔ **موجودہ GitHub main source freeze:** `626b380328d4400014504e679032291cd023bbdb`۔  
**نوعیت:** کوڈ، تقابلی معاہدہ اور عارضی WordPress / PHP QA؛ Live defect کی تشخیص یا Hostinger کا نفاذ نہیں۔  
**اصل منصوباتی تعلق:** F21-CEN-01 Following، Latest، For You؛ صریح Mute/Reset، غلط Review/Visibility سے اخراج، مستقل صارف کی agency اور File00/17/24/26 مالکِ حقیقت کے قواعد۔

## ۱۔ اصل سبب

`FeedQuery::wp_query_args()` پہلے صرف مجاز WordPress core `post`، `publish`، password-free، محدود `posts_per_page`، page، approved review-state، viewer visibility، post type/taxonomy اور مخصوص Founder/Doctor membership بناتا تھا۔ اس کے بعد اس entire authorization policy کو `sabri_hnf_feed_query_args` filter کے نتیجے سے **بلا تصدیق بدل دیتا تھا**۔

- کوئی بعد کا callback `post_status` کو `draft`، `post_type` کو `any`، `meta_query` کو خالی، `suppress_filters` کو `true`، `author__in` کو غیر متعلق ID، اور `posts_per_page` کو بڑا کرسکتا تھا۔
- `FeedQuery::query` کے حقیقی `WP_Query` راستے میں اس پر دوبارہ source-owner guard نافذ نہیں تھا؛ `filter_posts_for_tests` صرف fake WP route میں چلتا تھا۔
- `PublicQueryGuard::filter_public_queries` اصل WordPress main query کے لیے ہے؛ File21 کی secondary FeedQuery سے یکساں ہونا لازم نہیں۔
- صرف FeedRenderer میں پوشیدہ post کو suppress کرنا کافی نہیں؛ raw query rows اور `found_posts` کی privacy/accuracy پہلے متاثر ہوچکی ہوتی ہے۔

**علمی فرق:** Extension کا جائز وظیفہ `narrowing` ہے، source-owner `authorization widening` نہیں۔

## ۲۔ کوڈ کی بنیادی اصلاح

`FeedQuery::enforce_source_query_contract( $required, $extended, ... )` کل غیر معتبر feed extension callbacks کے **بعد** canonical owner gates بحال کرتا ہے:

1. `post_type='post'`, `post_status=['publish']`, `has_password=false`, `ignore_sticky_posts=true`, bounded `posts_per_page` اور `paged`، canonical sort؛ `suppress_filters=false`, `nopaging=false`, `fields='all'`, نہ `offset` نہ archive pagination bypass۔
2. `meta_query`: owner review/visibility **AND** custom filter's additional restriction۔ کسی nested `OR` سے approved-source gate ختم نہیں ہوتا۔
3. `tax_query`: canonical allowed topic/type restrictions **AND** optional additional filter؛ موجودہ user mute-topic `NOT IN` ایک علیحدہ لازمی AND layer۔
4. `founder-updates` اور `doctors-posts`: Source File00/09 verified author IDs کی فہرست کے باہر کسی extension author کو اجازت نہیں؛ صرف اصل و اضافی پابندی کا intersection۔
5. `following`: File17-backed `NetworkRelationshipBridge` سے موجودہ following IDs دوبارہ؛ late filter ان میں غیر مجاز author شامل نہیں کرسکتا۔
6. `post__not_in`، `author__not_in`: موجودہ صارف کی private hide/mute/snooze پالیسی کو late extension کے بعد بھی union کرکے محفوظ رکھتا ہے۔
7. مکمل response غیر-array ہو، nested `meta_query/tax_query` malformed ہوں، ID lists مقررہ 500/1000 حدود سے بڑھ جائیں، یا filter exception دے تو محفوظ `post__in=[0]` empty query؛ source publishing gates برقرار۔

**قطعی احتیاط:** File21 source owner بدلتا نہیں؛ File26 Ranking/Search، File00 Membership اور File17 Follow/Block کی canonical حقیقت اس میں نقل نہیں کی گئی۔ Founder، ادائیگی یا عطیات کو ranking کا فائدہ نہیں ملتا۔

## ۳۔ اصل تجربات

| صورت | کس جگہ جانچی گئی | ضروری نتیجہ |
|---|---|---|
| Filter `post_type=any/page`, `status=draft/private` | Runtime PHP 8.1/8.3 | `post` اور `publish` غیر قابلِ تبدیلی |
| `posts_per_page=10000`، `paged=9876`، `offset=500` | Runtime | محدود اصل page اور پرانی pagination کی بحالی |
| Review/visibility `meta_query=[]` یا `relation=OR` | Runtime اور real WordPress SQL | اصل approved + privacy AND شرط باقی |
| `has_password=true`, `suppress_filters=true` | Runtime اور WordPress | خفیہ passworded posts/followers SQL bypass ممنوع |
| Founder/Doctor author list جعلسازی | Runtime | صرف File00/09 canonical IDs |
| Following غیر متعلق author IDs | Runtime | File17 current follow list یا empty `author__in=[0]` |
| صارف کے hidden posts/muted authors/topics | Runtime + WordPress | Late filter کے باوجود متعلقہ exclusion باقی |
| Malformed string/nested metadata/oversized list | Runtime | `post__in=[0]`، غیر محدود تلاش نہ ہو |
| Throwing extension callback | Runtime | query crash کے بجائے fail-closed |
| Anonymous / signed-in SQL fetch | WordPress Playground 6.8 و latest | approved public؛ member صرف allowed، draft/pending/private نہیں |
| Multi-session full page/CDN/browser caching | Hostinger staging | **باقی**، متبادل runtime evidence نہیں |

**ٹیسٹ فائلیں:** `tests/run-file21-feed-source-policy-runtime.php`، `tests/run-file21-feed-source-policy-playground.mjs`؛ مستقل CI: `.github/workflows/file21-feed-source-policy.yml` اور canonical build `.github/workflows/build-test-home-news-feed.yml`۔

## ۴۔ مکمل قبولیت کے لیے ابھی لازم

- Issue #65/PR #66 (authorized viewer کے لیے card render، public-only share restriction) اس سے الگ tested ہے۔ دونوں کو ایک PR میں غیر آزمودہ patch stack نہ کریں؛ future merged HEAD پر combined WP/browser/source audit چاہیے۔
- Actual File00/17 member state، role revocation، following/unfollow/block، guardian/minor consent، File24 safety، File25 Home cards اور File26 owner indices staged journeys ضروری ہیں۔
- Mixed Content: File21 core posts + File21 editorial `sabri_news` کے الگ policy gates محفوظ۔ `NewsFeedIntegration` کی mixed-source pagination اور UI counters کو مستقل جانچ چاہیے۔
- External WordPress `posts_results/the_posts` سے source کے بعد آنے والی forged objects، stale cache، `found_posts` overshoot اور custom plugin callbacks کی full adversarial integration قبولیت ابھی باقی ہے؛ immutable arguments کو ان کا مکمل بدل نہ سمجھا جائے۔
- Full release: exact source SHA → test → package/ZIP checksum → staging actual packages/DB/options/migration parity → rollback/restore → deploy → Live re-test → deployed checksum confirmation۔

## ۵۔ لازمی حیثیت

| خانہ | نتیجہ |
|---|---|
| Repository main source freeze | `626b380328d4400014504e679032291cd023bbdb` |
| Branch candidate | `fix/file21-feed-query-source-policy-issue67-20261112` |
| Tested PHP versions | 8.1 اور 8.3، exact SHA per GitHub Actions |
| Tested ephemeral WordPress | 6.8 / latest، exact SHA per GitHub Actions |
| Deployed Version | غیر مصدقہ |
| Actual DB Version | غیر مصدقہ |
| Migration State | غیر مصدقہ |
| Live Verification Status | غیر مصدقہ |

**نتیجہ:** اس اصلاح میں اصل source query کا authentication/authorization حصہ late plugin extensions سے مستقل محفوظ کیا گیا ہے؛ Live پر یہ تبدیلی نافذ ہونے یا مسئلہ حل ہونے کا دعویٰ نہیں۔

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**
