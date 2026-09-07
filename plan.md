# طرح پیاده‌سازی (نسخه ۲): PressAgent 🚀
### The Autonomous AI Ops Bridge for WordPress

> این نسخه بر اساس بازبینی و بحث فنی روی طرح اولیه به‌روزرسانی شده — تفاوت اصلی: معماری از «adapter اختصاصی برای هر پلاگین» به «ابزارهای عمومی + adapter اختصاصی فقط برای موارد پیچیده (المنتور)» تغییر کرده، و یک لایه‌ی ایمنی برای اکشن‌های برگشت‌ناپذیر اضافه شده.

---

## موقعیت‌یابی پروژه (Positioning)

**این چیه:** نه یک کانکتور عمومی «وردپرس به MCP» (که چند نمونه‌ی آن — elementor-mcp، mcp-api-for-elementor، mcp-wordpress، elementify-mcp — از قبل وجود دارند)، بلکه یک **لایه‌ی AI Ops امن برای وردپرس**: ایجنت debug log می‌خونه، بین چند پلاگین (المنتور، Rocket، Wordfence و…) هماهنگ عمل می‌کنه، و مشکلات رو با کنترل دسترسی سطح‌بندی‌شده رفع می‌کنه.

**تمایز واقعی نسبت به رقبا:**
- **RBAC به‌عنوان محور طراحی**، نه یک افزوده — اکثر ابزارهای مشابه یک توکن با دسترسی کامل می‌دن.
- **گیت reversible/non-reversible** برای اکشن‌های خودکار (بخش پایین‌تر) — چیزی که در هیچ‌کدوم از پروژه‌های مشابه دیده نشد.
- اتصال به اکوسیستم موجود (**console.wphb.org** برای اسکن امنیتی) به‌جای بازسازی از صفر.
- **بدون فروش** — گیت‌هاب، اوپن‌سورس، مسیر باز برای همکاری شرکت‌های صاحب پلاگین.

---

## معماری کلی سیستم

```
+-------------------------------------------------------------+
|                     سیستم لوکال توسعه‌دهنده                 |
|       [Claude Code / Antigravity / Cursor IDE]              |
|                           | (Stdio / JSON-RPC)              |
|                           v                                 |
|               [PressAgent MCP Server (Node.js)]             |
+-------------------------------------------------------------+
                            |
                            | (HTTPS + Bearer Token + RBAC scope)
                            v
+-------------------------------------------------------------+
|                      سرور / هاست وردپرس                     |
|                   [پلاگین رسمی: PressAgent]                 |
|                           |                                 |
|   +------------+----------+----------+------------------+   |
|   |            |                     |                  |   |
|   v            v                     v                  v   |
| [Elementor   [Generic Settings   [Debug Log       [Action    |
|  Adapter]     Reader/Writer]      Parser]          Guard]    |
| - save() مسیر  - allow-listed     - استخراج و       - snapshot|
|   داخلی        wp_options         تحلیل با LLM       قبل هر  |
| - cache-safe   - Rocket/Wordfence                    write   |
+-------------------------------------------------------------+
```

---

## اصول معماری کلیدی (از جلسه‌ی بازبینی)

### ۱. ابزارهای عمومی به‌جای adapter برای هر پلاگین
اکثر پلاگین‌ها (Rocket، Wordfence، AIOS و…) تنظیماتشون رو در `wp_options` نگه می‌دارن و پیشخوان خودشون مستقیم همون را می‌خونه — بدون لایه‌ی کش جداگانه بین نوشتن و دیدن. پس به‌جای adapter اختصاصی برای هر پلاگین:
- **`pressagent_read_option` / `pressagent_write_option`**: خواندن/نوشتن روی یک allow-list از option keys، مشخص‌شده در تنظیمات هر ماژول (بخشی از RBAC).
- بعد از نوشتن روی option مرتبط با کش، هوک purge خود پلاگین (اگر وجود داشت) صدا زده می‌شود.

فقط **المنتور** به‌خاطر ساختار JSON پیچیده و کش چندلایه‌اش adapter اختصاصی می‌گیرد.

### ۲. Elementor Adapter — ایمن نسبت به کش
نوشتن مستقیم روی `_elementor_data` (postmeta) بدون باطل‌سازی درست کش، باعث می‌شود صفحه در پیشخوان خالی یا قدیمی دیده شود — این باگ در پروژه‌های مشابه واقعاً رخ داده (کش HTML رندرشده در `_elementor_element_cache`، مستقل از فایل CSS، در نسخه‌های ۴.۲+ المنتور). راه‌حل:
- ذخیره از طریق **مسیر داخلی `save()` خود داکیومنت المنتور**، نه `update_post_meta` خام — تا همان مسیری طی شود که کلیک دکمه‌ی «Update» طی می‌کند و همه‌ی کش‌ها به‌طور خودکار باطل شوند.
- این کار سازگاری با نسخه‌های آینده‌ی المنتور را هم بیشتر تضمین می‌کند (وابستگی کمتر به جزئیات داخلی schema).
- نتیجه: ادمین همیشه می‌تواند صفحه‌ی ویرایش‌شده توسط AI را با «ویرایش با المنتور» به‌صورت عادی باز و ادامه دهد.

### ۳. Debug Log Parser عمومی
به‌جای دیتابیس hardcoded از الگوهای خطا، لاگ خام (`WP_DEBUG_LOG`) استخراج و به مدل زبانی سپرده می‌شود برای تشخیص علت (fatal error، تداخل پلاگین، deprecated notice) و پیشنهاد راه‌حل. قابل استفاده در همه‌ی پلاگین‌ها بدون دانش اختصاصی از هرکدام.

### ۴. Action Guard — گیت Reversible / Non-Reversible
مهم‌ترین تغییر امنیتی نسخه‌ی ۲:

| نوع اکشن | نمونه | رفتار |
|---|---|---|
| **Reversible** | پاک‌سازی کش، غیرفعال‌سازی موقت پلاگین خراب، تغییر یک option | خودکار انجام می‌شود، اما قبلش snapshot گرفته می‌شود تا rollback ممکن باشد |
| **Non-reversible / پرریسک** | تغییر کد، فعال‌سازی پلاگین جدید، تغییر تنظیمات امنیتی حساس | فقط **پیشنهاد** می‌شود؛ اجرا منوط به تایید صریح کاربر |

بدون این جداسازی، اولین باری که ایجنت یک تنظیم اشتباه را روی سایت پروداکشن اعمال کند، اعتماد به کل ابزار از بین می‌رود.

### ۵. Developer / God Mode — محدودشده
ساخت و فعال‌سازی خودکار پلاگین PHP روی سایت لایو معادل RCE به‌عنوان یک فیچر رسمی است. این ماژول:
- پیش‌فرض **غیرفعال** است.
- توصیه می‌شود فقط روی محیط **staging** فعال شود، یا پشت تاییدیه‌ی دستی سخت‌گیرانه (نه یک تیک ساده در پیشخوان).
- کد تولیدشده همیشه با `php -l` قبل از فعال‌سازی بررسی می‌شود؛ فعال‌سازی خودش هم در دسته‌ی «non-reversible» قرار می‌گیرد و طبق قانون بالا نیاز به تایید دارد.

---

## ساختار فایل‌ها در ریپازیتوری (`/home/mr-noctis/projects/pressagent`)

```
pressagent/
├── plugin/                               # پلاگین وردپرس (PHP)
│   ├── pressagent.php                    # فایل اصلی، هوک‌ها و هدر پلاگین
│   ├── includes/
│   │   ├── class-auth.php                # احراز هویت توکن، RBAC scopes
│   │   ├── class-rest-api.php            # ثبت روت‌های REST API
│   │   ├── class-action-guard.php        # 🆕 snapshot/rollback + گیت تاییدیه
│   │   ├── class-pages-controller.php    # مدیریت ساخت، حذف و فهرست برگه‌ها
│   │   ├── class-elementor-adapter.php   # ذخیره از طریق مسیر save() داخلی + cache-invalidation
│   │   ├── class-generic-settings.php    # 🆕 خواندن/نوشتن allow-listed wp_options (Rocket, Wordfence, ...)
│   │   ├── class-debug-log-parser.php    # 🆕 استخراج لاگ + آماده‌سازی برای تحلیل LLM
│   │   ├── class-security-audit.php      # اتصال به موتور اسکن console.wphb.org
│   │   └── class-code-executor.php       # ساخت پلاگین جدید (God Mode) + php -l، فقط staging/تاییدیه
│   └── templates/
│       └── admin-settings.php            # پیشخوان: توکن‌ها، RBAC scopes، allow-list آپشن‌ها، فعال/غیرفعال ماژول‌ها
├── mcp-server/                           # سرور لوکال MCP (TypeScript)
│   ├── package.json
│   ├── tsconfig.json
│   └── src/
│       ├── index.ts
│       ├── wp-client.ts
│       ├── tools/
│       │   ├── pages.ts
│       │   ├── elementor.ts
│       │   ├── settings.ts               # 🆕 ابزار عمومی تنظیمات
│       │   ├── debug-log.ts              # 🆕 ابزار عمومی لاگ
│       │   ├── security.ts               # اتصال به WPHB
│       │   └── dev.ts                    # God Mode
│       └── templates/
│           └── elementor-widgets.ts
├── README.md                             # معرفی، وضعیت رقبا و تمایز، راهنمای مشارکت
└── .gitignore
```

---

## ابزارهای MCP (به‌روزشده)

### ۱. المنتور
* `pressagent_list_pages`
* `pressagent_create_page`
* `pressagent_get_elementor_layout`
* `pressagent_update_widget` — از مسیر `save()` داخلی، نه postmeta خام
* `pressagent_add_elementor_section`

### ۲. تنظیمات عمومی (جایگزین adapter اختصاصی برای اکثر پلاگین‌ها)
* `pressagent_read_option(plugin_slug, key)`
* `pressagent_write_option(plugin_slug, key, value)` — فقط روی allow-list، در دسته‌ی reversible با snapshot خودکار
* `pressagent_purge_cache` / `pressagent_get_cache_status` — برای WP Rocket و LiteSpeed

### ۳. امنیت
* `pressagent_security_summary` — از console.wphb.org

### ۴. تشخیص و رفع مشکل
* `pressagent_read_debug_log` — استخراج خطاهای اخیر
* `pressagent_diagnose_error` — تحلیل با LLM و پیشنهاد راه‌حل (اجرا فقط پس از تایید اگر non-reversible باشد)

### ۵. Developer / God Mode (پیش‌فرض غیرفعال)
* `pressagent_create_plugin` — ساخت + `php -l` + نیاز به تاییدیه‌ی صریح برای فعال‌سازی

---

## نقشه‌ی راه فازبندی‌شده

| فاز | محتوا | هدف |
|---|---|---|
| **فاز ۱** | Core (auth/RBAC) + Action Guard + Generic Settings + Debug Log Parser | نسخه‌ی اول قابل نصب و تست، بدون پیچیدگی المنتور |
| **فاز ۲** | Elementor Adapter (با مسیر save داخلی و cache-safety) | پرریسک‌ترین و پرکاربردترین بخش، جدا تست می‌شود |
| **فاز ۳** | Security Audit (اتصال به WPHB) + God Mode (staging-only) | تکمیل اکوسیستم، آخرین اولویت چون کم‌ریسک‌تر یا محدودتر است |

این فازبندی اجازه می‌دهد از همان فاز ۱ روی گیت‌هاب فیدبک بگیری، بدون اینکه منتظر یک محصول کامل بمانی.

---

## طرح تست و اعتبارسنجی

1. **احراز هویت و RBAC:** درخواست با توکن نامعتبر یا خارج از scope رد شود (`401 / 403`).
2. **Action Guard:** یک اکشن reversible (مثل تغییر یک option) → snapshot گرفته شود → rollback تست شود. یک اکشن non-reversible → بدون تایید صریح اجرا نشود.
3. **چرخه‌ی المنتور:**
   - ساخت یک برگه‌ی تست.
   - افزودن یک کانتینر شامل ویجت تیتر و دکمه از طریق ابزار.
   - باز کردن همان صفحه در پیشخوان با «ویرایش با المنتور» و تایید عدم خالی/قدیمی بودن نمایش (تست مستقیم سناریوی باگ کش).
4. **Generic Settings:** تغییر یک آپشن Rocket از طریق ابزار → تایید بازتاب فوری در پیشخوان Rocket بدون نیاز به عملیات دستی اضافه.
5. **Debug Log:** تزریق یک خطای عمدی (مثل تداخل پلاگین) → تایید تشخیص و پیشنهاد صحیح.
6. **اتصال MCP از Claude Code:** اتصال stdio و دریافت لیست کامل ابزارهای فاز ۱.
