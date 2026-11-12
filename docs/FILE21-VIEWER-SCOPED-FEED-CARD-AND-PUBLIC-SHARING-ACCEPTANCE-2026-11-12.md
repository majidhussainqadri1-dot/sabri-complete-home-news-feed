# فائل 21 — مجاز صارف کے Feed کارڈ اور عوامی شیئرنگ کی حدود: اصلاحی روداد
## Sabri Social Homeopathy Platform — مرکزی منصوبہ V12.1، F21-CEN-01، Following / Latest / For You

**تاریخ:** 12 نومبر 2026ء۔ **نوعیت:** Repository Source / Isolated WordPress integration audit؛ Hostinger Live incident نہیں۔  
**اصل main/source freeze:** `626b380328d4400014504e679032291cd023bbdb`۔  
**GitHub Issue:** #65؛ **اصلاحی برانچ:** `fix/file21-feed-viewer-scoped-card-issue65-20261112`۔  
**حقیقی تعینات پلگ اِن، DB schema/migrations، staging، Live runtime اور deployed checksum:** تصدیق باقی۔

## ۱۔ اصل نقص، اور اس کی موجودگی کا سورس ثبوت

`FeedQuery::wp_query_args()` موجودہ viewer کے لیے `FeedContext::visible_feed_scopes_for_user()` سے `public`، `members`، اور متعلقہ کردار یا relationship کی visibility scopes اخذ کرتا ہے۔ `PostMetadata::user_can_view()` اصل شائع شدہ اور approved پوسٹ کو صرف مجاز viewer کے لیے جائز قرار دیتا ہے۔

لیکن `FeedRenderer::render_card()` ہر کارڈ کی ابتدا میں `NextGenerationFeed::strict_public_item()` چلاتا تھا، جو `FourthFreshReviewHardening::public_source_is_shareable()` سے `visibility === public` اور **anonymous** sharing authorization لازم کرتا ہے۔ اس اشتراک کے نتیجے میں مجاز viewer کے سامنے اس کا درست `members` post بھی غائب ہوجاتا تھا، حالاں کہ اس کی real Feed query اسے جائز طور پر منتخب کرتی تھی۔

**حاکم علّی فرق:** 
- `can_render_for_viewer(post, viewer)`: محدود اشاعت سے مجاز ناظر کو پڑھنے کا حق۔
- `can_share_with_world(post)`: ہر غیر متعلق/مہمان شخص کو پوسٹ دستیاب ہونے کا مستقل اور زیادہ سخت حق۔
- **پہلا حق دوسرے کا ثبوت نہیں؛ دوسرے کے لیے اولیٰ طور پر پہلی شرط کافی نہیں۔**

## ۲۔ محدود اصلاح

`includes/class-feed-renderer.php` میں صرف **Home Feed card eligibility** کے لیے `viewer_can_render_post()` شامل ہوا؛ بنیادی post reader `render_card()` اب اس طریقے سے اجازت لیتا ہے۔

لازمی داخلی شرطیں: حقیقی WordPress Post ID/author موجود، `post_type='post'`، `post_status='publish'`، جائز `review_state_publicly_visible()`، موجودہ viewer کی `FeedContext::visible_feed_scopes_for_user()` میں پوسٹ کی scope، `PostMetadata::user_can_view()`، اور `NetworkRelationshipBridge::author_allowed()`۔

اس سے:
- مجاز `members` اور `students` post کو اصل Feed card میں جگہ ملتی ہے۔
- مہمان، غیر متعلق رکن، moderator کے Draft، pending/removed/rejected اور غلط post type خارج ہوتے ہیں۔
- File 17 کے موجودہ block rule کی خلاف ورزی نہیں ہوتی۔
- `NextGenerationFeed::strict_public_item()` **تبدیل نہیں ہوا**۔ اس لیے Repost/Quote اور world-public source/related links کا سخت gate برقرار ہے۔ Restricted card میں Next-Generation **public-only** extension بطور قاعدہ نہیں دکھایا جاتا۔
- Non-public Home feed کے permission rules پر admin-only editor privilege استعمال نہیں ہوتا؛ اصل selected audience ہی decisive ہے۔

## ۳۔ قابلِ اعادہ جانچیں

| Source test / ماحول | اصل صورت | قبولیت |
|---|---|---|
| `tests/run-file21-feed-viewer-card-runtime.php` | WordPress-style repository stubs | عوامی/محدود/مخالف role، pending/draft، private، blocked، wrong post type، اصل template |
| PHP 8.1 اور PHP 8.3 | الگ exact candidate SHA | Basic renderer, strict share policy, source guards اور legacy public-query routing |
| `tests/run-file21-feed-viewer-card-playground.mjs` | حقیقی عارضی WordPress Playground latest + PHP 8.3 | Plugin activation، anonymous/member switch، private/draft/pending، block، World public share gate |
| WordPress 6.8 + PHP 8.1 | دوسرا حقیقی عارضی ماحول | وہی End-to-end card authority |
| `.github/workflows/file21-feed-viewer-boundary.yml` | چار jobs، exact GitHub SHA | QA status ہمیشہ موجودہ commit کے Actions سے پڑھا جائے |
| Canonical Build | `.github/workflows/build-test-home-news-feed.yml` | لازمی runtime regression شامل، package/source version parity بحال |

**محدود دعویٰ:** یہ آزمائشیں سورس اور عارضی WordPress environment کی ہیں؛ اصل Hosted File00/File17 identification، حقیقی identity assurance، Home page browser screenshot یا installed File21 پیکیج کی parity نہیں۔

## ۴۔ مزید آڈٹ کے لیے الگ مسائل

1. `FeedQuery::wp_query_args` کے بعد `sabri_hnf_feed_query_args` extension کی mutable `post_status`, `post_type`, `meta_query` authorization۔ اس کے لیے **الگ root-cause finding، الگ اصلاح اور tests** ضروری؛ صرف card guard سے raw query result اور شمار مکمل محفوظ نہیں ہوتے۔
2. `FeedQuery::query` میں حقیقی WP_Query کے `found_posts` اعداد disabled/deleted/role-denied rows شمار کرسکتے ہیں، جس سے UI pagination incorrect ہو۔ Privacy کی خاطر صفحہ وار filtered count اور truthful exhaustion ثابت کریں۔
3. File00 Membership/age/guardian/verified status، File17 block/follow، File25 card UI، File26 external public-only source اور File24 assurance کے حقیقی current contracts stage separately۔
4. Theme/CDN/full-page cache/REST no-store policies میں **logged-in member** کارڈ کا **anonymous** یا دوسرے user کی HTML میں رساؤ نہ ہو؛ end-to-end multi-session اور browser cache tests واجب۔
5. Repost/Quote، Search/Graph، public/share/export، correction/retraction and media download محدود مواد کا صرف مجاز route رکھیں؛ `strict_public_item` public-only ہی رہے۔

## ۵۔ جائز نفاذ و ریلیز کا سلسلہ

`Repository source candidate` → اصل pull request review → test matrix → exact HEAD build/package/checksum → installed File00/File17/File21 contract audit → Staging two-viewer + Urdu/Arabic RTL/device tests → backup/rollback → authorized deploy → Live same sessions/DB-state verification → parity confirmation۔

**Live-First Exact-Deployed-State Rule:** GitHub HEAD/CI/PR کسی Hostinger deployed build، WordPress DB version یا migration state کا متبادل نہیں۔

**موجودہ نتیجہ:** Issue #65 کی Repository سطح کی code candidate تیار؛ PHP 8.1/8.3 اور WP Playgrounds کے جدا آزمائشی نتائج دستیاب ہیں؛ حقیقی Live حل ہونے کا دعویٰ ممنوع۔

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**
