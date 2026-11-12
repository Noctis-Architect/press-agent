---
name: pressagent-plugins
description: >-
  Use when configuring or auditing WordPress plugins through PressAgent: SEO (Yoast/Rank Math),
  security (Wordfence/AIOS/Really Simple Security), LiteSpeed cache, Contact Form 7/WPForms,
  WooCommerce, Redirection and WP Mail SMTP.
---

# مدیریت پلاگین‌ها با PressAgent

## ۱. قاعده طلایی

هرگز کلید option/meta را حدس نزن. مسیر همیشه:

1. `pressagent_plugins({ action: "list" })` → نسخه و فعال بودن.
2. `pressagent_plugin({ action: "catalog", plugin })` → فقط تنظیمات/اکشن‌های مجاز همان نسخه.
3. `get` با `ids` و `fields` محدود → فقط داده لازم.
4. `set` کوچک‌ترین تغییر ممکن → `snapshot_ids` را نگه دار.
5. `pressagent_purge_cache` + اسکرین‌شات/آزمون → تایید.
6. در صورت مشکل: `pressagent_plugin({ action: "restore", snapshot_id })`.

نوشتن مستقیم با `pressagent_write_option` برای پلاگین‌ها ممنوع است (Sanitization و side-effectها دور زده می‌شوند).

## ۲. نقشه ابزارها

| ابزار | کاربرد |
|---|---|
| `pressagent_plugins` | جست‌وجو و نصب از WordPress.org (با اسلاگ/URL/نام)، فعال/غیرفعال، آپدیت. نسخه Pro فقط با لایسنس. |
| `pressagent_plugin` | catalog/get/set/restore + اجرای اکشن‌های ثبت‌شده. |
| `pressagent_seo` | متای سئوی برگه/ترم با Yoast یا Rank Math. |
| `pressagent_forms` | CF7 کامل، WPForms فقط خواندن. |
| `pressagent_commerce` | محصولات/سفارش‌های WooCommerce. |
| `pressagent_theme` | child theme و حالت ظاهری. |

### نصب از مخزن (مثل Plugins → Add New)

1. اگر اسلاگ دقیق را نمی‌دانی: `pressagent_plugins({ action:"search", query:"نام پلاگین" })`.
2. نصب: `pressagent_plugins({ action:"install", slug:"<slug|URL|نام>", activate:true })`.
3. اگر پاسخ `ambiguous_plugin` بود، `candidates` را به کاربر نشان بده و اسلاگ را بپرس؛ حدس نزن.
4. اگر `already_installed: true` بود، یعنی نصب لازم نیست؛ با `activate:true` همان‌جا فعال می‌شود.
5. فقط مخزن رسمی WordPress.org؛ Pro/نال‌شده را sideload نکن. بعد از نصب، `list` و سپس `catalog` را بگیر.

## ۳. SEO

- `pressagent_seo({ action:"providers" })` ابتدا؛ اگر هر دو پلاگین فعال‌اند `provider` را صریح بده.
- فیلدها: `title, description, focus_keyword, canonical, noindex, nofollow, advanced_robots, og_title, og_description, og_image, twitter_title, twitter_description, twitter_image, primary_category, schema_page_type, schema_article_type, cornerstone`.
- ترم‌ها نیاز به `taxonomy` دارند (مثلاً `category`).
- Yoast: ترم‌ها در option `wpseo_taxonomy_meta` ذخیره می‌شوند، نه term_meta. اگر پاسخ `indexable_rebuilt:false` داشت، هشدار را به کاربر بگو.
- Rank Math: robots آرایه است؛ `noindex/nofollow` به‌عنوان عضو آرایه ذخیره می‌شوند.
- تنظیمات سراسری: Yoast → `wpseo_titles`, `wpseo`, `wpseo_social`, `wpseo_llmstxt`؛ Rank Math → `rank-math-options-titles/general/sitemap`.
- Rank Math extras: `list_modules` / `set_module` برای فعال/غیرفعال کردن ماژول‌ها؛ `list_redirections` / `create_redirection` / `update_redirection` / `delete_redirection`؛ `list_404_errors` برای لاگ 404. تنظیمات `404_monitor_*` و `redirections_*` در `rank-math-options-general` هستند.

## ۴. امنیت

### Wordfence
- تنظیمات کلیدی: `firewallEnabled`, `loginSecurityEnabled`, `alertOn_block`, `loginSec_maxFailures`, `loginSec_lockoutMins`, `autoUpdate`, `liveTrafficEnabled`, `scan_include_extra`, `alertEmails`, `whitelisted`, `scanType`, `schedMode`, `schedStartHour`, `liveTraf_ignore*`, `loginSec_strongPasswds`, `loginSec_breachPasswds`, `autoBlockScanners`.
- اکشن‌ها: `scan_status`, `start_scan`, `get_issues`.
- تنظیمات در جدول اختصاصی `wfConfig` است؛ از طریق رجیستری انجام می‌شود.

### AIOS
- همه تنظیمات در `aio_wp_security_configs`؛ مقادیر چک‌باکس `'1'` یا `''`.
- نمونه‌ها: `aiowps_enable_login_lockdown`, `aiowps_max_login_attempts`, `aiowps_enable_basic_firewall`, `aiowps_disable_xmlrpc_pingback_methods`, `aiowps_enable_404_IP_lockout`, `aiowps_enable_rename_login_page`.
- 6G firewall در تنظیم `aios_firewall_config` است: `aiowps_6g_block_request/query/referrers/agents`, `aiowps_6g_block_request_methods`, blacklistها و IP rangeها. کلید اصلی `aiowps_enable_6g_firewall` در کانفیگ اصلی است.
- پس از تغییرات فایروال: اکشن `rebuild_htaccess` و سپس تست دسترسی سایت.
- برای بررسی رویدادهای امنیتی: اکشن `list_audit_events`.

### Really Simple Security
- تنظیمات: `redirect`, `mixed_content_fixer`, `login_protection_enabled`, `enable_limited_login_attempts`, `enable_firewall`, `enable_vulnerability_scanner`, `disable_xmlrpc`, `disable_file_editing`, `hsts`, `notifications_email_address`.
- اکشن‌ها: `rebuild_rules`, `send_test_mail`.
- فعال‌سازی SSL عمداً در MCP نیست؛ از پنل پلاگین انجام شود.

## ۵. کش و عملکرد (LiteSpeed)

- کلیدها: `cache`, `cache-ttl_pub`, `cache-mobile`, `cache-exc`, `optm-css_min`, `optm-js_min`, `optm-js_defer`, `optm-html_min`, `media-lazy`, `img_optm-webp`.
- مقادیر multi-switch: `cache` (0/1/2)، `optm-js_defer` (0/1/2)، `img_optm-webp` (0/1/2).
- آرایه‌ها (exclude listها) جایگزین می‌شوند، نه merge.
- اکشن‌ها: `purge_all`, `purge_url`, `purge_post`, `purge_posttype`.
- بعد از تغییر minify/combine حتماً purge و اسکرین‌شات بگیر؛ ترکیب CSS/JS می‌تواند ظاهر را بشکند.

## ۶. فرم‌ها

- CF7: `list`, `get`, `create_cf7`, `update_cf7`, `delete_cf7`.
  - قالب فرم با تگ‌هایی مثل `[text* your-name]`.
  - `mail` شیء با `subject`, `sender`, `recipient`, `body`, `additional_headers`, `use_html`.
  - پاسخ شامل `shortcode` و `config_errors` است؛ خطاها را به کاربر گزارش کن.
- WPForms Lite: فقط `list/get`؛ ورودی‌ها ذخیره نمی‌شوند و چند اعلان ایمیلی Pro است.

## ۷. ووکامرس

- `list_products`, `get_product`, `create_product`, `update_product`, `list_orders`, `get_order`, `update_order_status`, `add_order_note`.
- قیمت/موجودی محصول متغیر روی variationهاست؛ ابزار برای محصول متغیر خطا می‌دهد.
- تغییر وضعیت سفارش ممکن است ایمیل به مشتری بفرستد؛ `customer_note` را فقط با تایید کاربر true کن.
- HPOS: همیشه از APIهای WC استفاده می‌شود، نه SQL.

## ۸. ریدایرکت و SMTP

### Redirection
- اکشن‌ها: `list_redirects`, `create_redirect`, `update_redirect`, `delete_redirect`, `set_redirect_enabled`, `lookup_404s`, `list_groups`, `create_group`, `update_group`, `delete_group`, `lookup_logs`, `flush_cache`.
- ساخت: `url` + `target` (+ `action_code` پیش‌فرض 301). `group_id` اگر خالی باشد اولین گروه استفاده می‌شود.
- `delete_redirect` مخرب است؛ `confirm:true` بعد از تایید کاربر.
- قبل از تغییر اسلاگ برگه، 404های اخیر را با `lookup_404s` ببین.

### WP Mail SMTP
- ساختار: `wp_mail_smtp.mail` (mailer/from)، `wp_mail_smtp.smtp` (host/port/encryption/auth/user/pass)، `wp_mail_smtp.general`.
- رمز SMTP فقط نوشتنی است؛ خواندن `pass_set` برمی‌گرداند.
- ثابت‌های `WPMS_*` بر DB اولویت دارند؛ اگر تغییر اثر نکرد، ثابت‌ها را بررسی کن.
- `send_test_email` و `list_debug_events` برای عیب‌یابی.

## ۹. انضباط توکن

- `catalog` را یک بار بگیر و خلاصه نگه دار؛ هر بار تکرار نکن.
- برای آرایه‌های بزرگ (`wpseo_titles`, AIOS config) از `ids` و `fields` استفاده کن.
- از خواندن full form data یا product description تا وقتی لازم نیست خودداری کن (`include_data:false`, `include_description:false`).
- پاسخ‌های طولانی را برای کاربر خلاصه کن: کلید تغییر یافته، مقدار قبلی/جدید، snapshot، نتیجه بصری.

## ۱۰. مرجع دقیق

جزئیات هر کلید با ارجاع به سورس: `docs/plugin-capabilities/<slug>.json` (نسخه‌های تحلیل‌شده در `plugin-sources.json`).
