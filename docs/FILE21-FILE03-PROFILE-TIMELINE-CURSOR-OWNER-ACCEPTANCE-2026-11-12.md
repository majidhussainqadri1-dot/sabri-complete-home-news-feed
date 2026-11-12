# File 21 — Profile Timeline Exact Owner/Cursor Correction and Acceptance Ledger
## فائل 21 سے فائل 03 تک حقیقی ٹائم لائن، مصنف کی ملکیت، اشاعت و رازداری کی جامع اصلاحی روداد

**مورخہ:** 12 نومبر 2026ء۔ **نوعیت:** Source / Repository Correction؛ Live Incident نہیں۔  
**مرکزی منصوبہ:** «صابری سوشل ہومیوپیتھی پلیٹ فارم — جامع مرکزی ماسٹر پلان V12.1»؛ فائل 21 اشاعت و خبر، فائل 03 Profile Federation، فائل 25 Visual، فائل 24 Assurance۔

**حاکم اصول:** ہر مواد، اس کی حقیقی مصنفیت، اشاعت، سماجی شناخت اور اجازت کا مالک File 21 ہے۔ File 03 صرف شناخت شدہ پروفائل کے لیے محدود اور مجاز آئٹمز جمع کرتا ہے؛ File 25 ظاہری نظام رکھتا ہے۔ کسی Provider کے دعوے سے اصل اجازت تبدیل نہیں ہوتی۔

| حیثیت | ثبوت یا حد |
|---|---|
| File21 Repository Main at source freeze | `626b380328d4400014504e679032291cd023bbdb` |
| File03 exact consumer baseline | `bf317e5521ec79f9f16d21353f402571fcd4f84c` |
| Existing interface legacy PR | #50 — old PR; source hooks already in frozen main |
| New scoped corrective PR | #63 — exact current SHA must be re-frozen before action |
| Defect 1 | Issue #62 — signed cursor not consumed; second/third page missing |
| Defect 2 | Issue #64 — extension-filtered local shortcode query can misattribute another author's post |
| Actual deployed plugin / package checksums | Unverified |
| Actual DB version / schema / migration | Unverified |
| Staging, live runtime and rollback acceptance | Not performed |

## ۱۔ نقص نمبر 62: حقیقی Provider Cursor ضائع ہونا

File03 کی `SPD_Timeline::query` سائن شدہ cursor decode کرنے کے بعد ہر Provider کو `$args['cursor'] = array('t'=>GMT date,'i'=>provider:canonical_id)` فراہم کرتی ہے۔ فائل 03 تمام Providers کو `published_at DESC` اور پھر `provider:canonical_id DESC` کی **lexical** ترتیب میں ملاتی ہے۔ سابق File21 `file03_provider_items` اس cursor کو نظرانداز کرکے ہر بار صرف `$limit` تازہ ترین پوسٹس واپس کرتی تھی، لہٰذا اگلے صفحے میں File03 وہی پہلی پوسٹس خارج کرکے اصل پرانی پوسٹس تک نہیں پہنچ سکتی تھی۔

**اصلاح:** File21 میں حقیقی read-only، prepared WordPress posts query مقرر ہوئی: مکمل `post_author`, `post_type='post'`, `post_status='publish'` اور وقت/lexical-ID keyset order۔ دوسرا/تیسرا صفحہ اسی signed cursor کی ترتیب سے نکلتا ہے۔ برابر وقت میں `ID=9` اور `ID=10` کا تقابل **عددی نہیں بلکہ lexical** ہے، کیونکہ File03 ایسا ہی کرتی ہے۔ File10، File11 یا File05 وغیرہ کے global cursor کا مساوی وقتی tie بھی اسی عالمی ترتیب پر پرکھا جاتا ہے۔

**Fail-closed:** غلط/مستقبل کی تاریخ، malformed sort ID، DB غائب یا query failure، unreadable source object، out-of-order source row اور ایسے 500 scanned candidates جو حقیقی صفحہ مکمل نہ کرسکیں، جھوٹا empty page نہیں بناتے؛ Owner error File03 میں degraded provider بنتا ہے۔

## ۲۔ نقص نمبر 64: Local Profile Shortcode Query میں مصنف کا غلط انتساب

پہلے local `ProfileTimeline::query` میں author، status، type، review/visibility اور scan limit پہلے محدود ہوتے، پھر `sabri_hnf_profile_timeline_query_args` فلٹر ان کو بدل سکتا تھا۔ اس کے بعد صرف `PostMetadata::user_can_view` دیکھا جاتا، جو کسی **دوسرے مصنف کی public post** کو بھی true دے سکتا ہے۔ اس سے پروفائل A کے نیچے B کی public post غلط انتساب سے آسکتی تھی۔

**اصلاح:** فلٹر کے بعد صرف array محفوظ؛ author، publish، post type، posts limit، first-page bound، no-found-rows، sticky-ignore، review اور visibility meta guards دوبارہ لازم۔ ہر candidate کو canonical `get_post` سے دوبارہ پڑھ کر ID/author/type/status/review/current viewer authorization کی تصدیق۔ صرف واقعتاً مجاز source object serialise کیا جاتا ہے۔ موضوعی/ترتیبی توسع جو اصل اجازت نہ بدلے وہ برقرار رہ سکتا ہے۔

## ۳۔ کس فیچر کو کس فائل کی ملکیت حاصل ہے؟

- File 21: Post status، حقیقی مصنف، post ID، تاریخ، public/limited visibility، review، excerpt/url/thumbnail source facts۔
- File 03: پروفائل public ID، profile visibility، provider registry، signed global cursor، federation، user audience، profile UI route۔
- File 25: عالمی تصویری component / display style۔
- File 24: cross-module assurance؛ File 21 کے authorization کا بدل نہیں۔

## ۴۔ قبولیتی امتحانات

| منظر | مطلوبہ قبولیت |
|---|---|
| 65 ایک ہی timestamp والی posts | 21 candidate owner-window، پھر 20 visible اور اگلا signed cursor |
| دوسرا صفحہ | آخری دکھائی گئی پہلی صفحے کی post کے بعد 21 اگلی پوسٹس |
| تیسرا صفحہ | مزید پرانی پوسٹس، کوئی duplicate یا skip نہیں |
| مختلف Provider cursor برابر تاریخ پر | global lexical order سے درست before/after |
| ایک ہی signed cursor دوبارہ | غیر تبدیل شدہ source پر یکساں جواب |
| دوسرے user کی پوسٹ | نہ source SQL میں، نہ object-projection میں |
| published → draft | اگلی timeline میں غیر شائع شدہ پوسٹ خارج |
| approved → pending یا public → private | latest author review/visibility checks سے خارج |
| query filter author=other, draft, limit=9999 | اصل author/publish/review/max bounds دوبارہ نافذ |
| query filter غیر-array | source unavailable، خالی کامیابی نہیں |
| غلط cursor/ٹائم یا SQL failure | واضح unavailable/degraded |
| 500 candidates مسلسل نااہل | False complete نہیں؛ controlled degradation |
| Actual File03 + File21 production DB | **باقی**؛ repository-source CI کافی نہیں |
| Urdu/Arabic RTL، mobile، screen readers | **باقی**؛ File03/25 interactive journeys لازم |
| Migration/rollback، installed checksum parity | **باقی** |

## ۵۔ قابلِ اعادہ ٹیسٹ

- `tests/run-file21-file03-cursor-runtime.php`: source method کی executable fake WordPress DB tests؛ multi-page، equal-time lex، cross-provider، replay، private، draft، invalid/failure، author spoof اور saturation۔
- `tests/integration/file21-file03-exact-consumer-runtime.php`: **فائل 03 کے اصل pinned `SPD_Timeline::query` ماخذ** اور فائل 21 کے اصل `ProfileTimeline::file03_provider_items` کو ایک ہی PHP process میں چلاتا ہے؛ چار صفحات اور 65 postings، signed HMAC cursor، retry، privacy/withdrawal، اور provider degraded حالت کی جانچ؛ WordPress integration boundary کا deterministic mock ہے، Live یا staging نہیں۔
- `tests/run-file21-file03-profile-timeline-contract.php`: canonical owner/schema/hooks static guard۔
- `tests/integration/file21-file03-cursor-mariadb.php`: ephemeral **حقیقی MariaDB 11.4** کے خلاف exact query semantics؛ WordPress-shaped source table مگر production WordPress نہیں۔
- `tests/run-file21-file03-cursor-playground.mjs`: ephemeral **حقیقی WordPress Playground** پلگ اِن activation؛ 65 posts، pages، privacy، draft، malformed cursor؛ یہ MySQL production configuration نہیں۔
- `.github/workflows/file21-file03-real-cursor.yml`: File03 exact `bf317e5521ec79f9f16d21353f402571fcd4f84c`، File21 exact PR HEAD، runtime/WordPress/MariaDB gates۔ ساتھ exact source checker میں دونوں اصل classes کی **مشترک consumer-provider runtime** آزمائش بھی شامل ہے۔

ہر نئی commit پر **پھر سے** اصل SHA کے مطابق CI record لینا ضروری ہے۔ 10-round prior reports یا کسی branch کے پچھلے Green checks، نئی HEAD پر کافی نہیں۔

## ۶۔ لازمی آئندہ مراحل، اختلافات اور رکاوٹیں

1. موجودہ PR #63 کی ہر fail ہونے والی CI دوبارہ پڑھ کر **ثابت** root cause پر اصلاح کریں۔
2. Source acceptance کے بعد staged exact File03+21 integration؛ profile visibility guard، mixed provider pagination، media metadata، correction/retraction projection۔
3. GitHub legacy PR #50 موجودہ main سے stale ہے؛ پہلے اس کے باقی واقعی مختلف delta کا proof، پھر فیصلہ۔ اندھا دھند پرانا PR merge نہ کریں۔
4. دیگر source PRs #44/#48/#59/#61 کا exact HEAD اور overlap دوبارہ audit؛ File21 common code کی merged state proof۔
5. Hostinger Live Reality Freeze: active plugin version، deployed ZIP/checksum، DB schema/migration/options، runtime logs اور provider activation؛ parity mismatch نکلے تو پہلے Deployment-Parity Audit۔
6. Fix → test → package → staging → deploy → Live re-test → parity؛ آخری live retest کے بغیر Resolved نہیں۔

**نتیجہ:** ایک ہی File21 ProfileTimeline ذمہ داری میں **دو الگ ثابت شدہ source defects** شناخت ہوئے؛ ان کی code candidates اسی Draft PR میں موجود ہیں۔ دونوں کے source tests کی آخری HEAD پر حقیقی CI verification لازم ہے۔ کسی live defect کی اصلاح ثابت نہیں ہوئی۔

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**
