---
name: pressagent-performance
description: >-
  Use when a WordPress site is slow, when the user asks for a speed audit, or when caching,
  compression, autoload, revisions, Elementor CSS and LiteSpeed settings need diagnosis and
  safe fixes. Also covers WordPress core settings through pressagent_wp_settings.
---

# عملکرد و تنظیمات هسته وردپرس

## ۱. چرخه کار

```
pressagent_performance({ action: "audit" })
        ↓ issueها + severity + fix_id
pressagent_performance({ action: "page", page_id یا url })
        ↓ metrics صفحه + issueهای واقعی رندر
pressagent_performance({ action: "fix", fix_id, confirm? })
        ↓ snapshot
pressagent_performance({ action: "audit" }) + pressagent_capture_screenshot
```

قبل از هر تغییر سرعتی از کاربر تایید بگیر مخصوصاً وقتی fix مخرب است. بعد از fix همیشه کش را پاک و صفحه را دوباره بسنج.

## ۲. خروجی audit

- محیط: PHP، memory_limit، OPcache، object cache، page cache.
- دیتابیس: اندازه، حجم autoload، تعداد/حجم گزینه‌های autoload، transients منقضی، تعداد revision.
- کش: LiteSpeed، Elementor CSS/experiments، وضعیت cache.
- پلاگین‌ها: تعداد فعال و پلاگین‌های شناخته‌شده.
- issueها با `severity` و `fix_id`.

نمونه issueها: `no_page_cache`, `no_object_cache`, `opcache_disabled`, `autoload_large`, `revisions_bloat`, `expired_transients`, `litespeed_minify_off`, `litespeed_lazy_off`, `elementor_css_internal`.

## ۳. خروجی page

متریک‌های واقعی صفحه: زمان پاسخ، حجم HTML، تعداد script/style، scriptهای render-blocking در head، تصاویر بدون alt/ابعاد، lazy loading، تعداد CSS المنتور، فونت خارجی، فشرده‌سازی و هدر کش.

نکته: URL باید همان دامنه سایت باشد (SSRF guard). برای لود اولیه ممکن است عدد TTFB بالا باشد؛ چند بار بسنج و مقدار پایدار را مبنا بگیر.

## ۴. Fixهای مجاز

| fix_id | اثر | تایید |
|---|---|---|
| `enable_litespeed_cache` | روشن کردن کش صفحه | لازم نیست |
| `enable_litespeed_minify` | minify CSS/JS/HTML | لازم نیست |
| `enable_litespeed_lazy` | lazy loading | لازم نیست |
| `enable_litespeed_browser_cache` | هدر کش مرورگر | لازم نیست |
| `enable_elementor_external_css` | CSS خارجی المنتور | لازم نیست |
| `enable_elementor_element_cache` | TTL کش المنتور | لازم نیست |
| `enable_elementor_optimized_markup` | experiment بهینه‌سازی markup | لازم نیست |
| `enable_elementor_optimized_css` | experiment بهینه‌سازی CSS | لازم نیست |
| `purge_cache` | پاکسازی همه کش‌ها | لازم نیست |
| `delete_expired_transients` | حذف transientهای منقضی | لازم نیست |
| `delete_old_revisions` | حذف ریبایژن‌های قدیمی | `confirm:true` |
| `cleanup_database` | حذف postmeta/term relationship یتیم، کامنت‌های trash و transient منقضی | `confirm:true` |

هر fix از رجیستری معتبر استفاده می‌کند و snapshot برمی‌گرداند. برای بازگردانی از snapshotهای همان رجیستری یا rollback تنظیمات هسته استفاده کن.

## ۵. تنظیمات هسته وردپرس

```
pressagent_wp_settings({ action: "get", groups: ["general","reading"] })
pressagent_wp_settings({ action: "set", values: { general: { blogname: "..." }, reading: { posts_per_page: 12 } } })
pressagent_wp_settings({ action: "rollback", snapshot_id: "..." })
```

گروه‌ها: `general`, `reading`, `discussion`, `media`, `permalinks`, `privacy`.

قواعد:
- `siteurl` و `home` فقط با `confirm:true` و بعد از تایید صریح کاربر.
- تغییر `permalink_structure`/`category_base`/`tag_base` خودکار rewrite rules را flush می‌کند.
- `page_on_front` و `page_for_posts` باید صفحه واقعی باشند؛ `site_icon` باید تصویر باشد.
- `default_role` باید نقش موجود باشد؛ `admin_email` معتبر باشد.
- برای هر تغییر snapshot ذخیره می‌شود؛ در صورت خرابی rollback کن.

## ۶. کارهایی که در MCP انجام نمی‌شود

- تغییر php.ini، OPcache، Redis/Memcached یا وب‌سرور (نیاز به هاست).
- تغییر wp-config.php (WP_DEBUG، WP_POST_REVISIONS، WP_CACHE) — فقط توصیه به کاربر.
- حذف گزینه‌های ناشناخته autoload — فقط گزارش و پیشنهاد بررسی دستی.

## ۷. انضباط توکن

- یک audit کافی است؛ برای هر صفحه فقط `page` همان صفحه را بگیر.
- خروجی issueها را برای کاربر خلاصه کن: severity، علت، fix پیشنهادی.
- بعد از fix، audit کامل را تکرار نکن اگر فقط یک مورد تغییر کرده؛ صفحه را دوباره بسنج.
