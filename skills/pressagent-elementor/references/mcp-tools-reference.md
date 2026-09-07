# مرجع ابزارهای MCP در PressAgent

پروژه **PressAgent** یک بستر AI Ops برای وردپرس است که ابزارهای MCP زیر را در اختیار ایجنت قرار می‌دهد. در اینجا جزئیات دقیق، پارامترهای ورودی و خروجی و خطاهای متداول برای هر ابزار آورده شده است.

---

## ۱. ابزارهای المنتور (Elementor Tools)

### `pressagent_get_elementor_layout`
دریافت ساختار درختی المان‌های برگه المنتور به صورت آرایه ساخت‌یافته JSON.

- **پارامترها:**
  - `page_id` (number, required): شناسه برگه وردپرس
- **نمونه فراخوانی:**
  ```json
  {
    "page_id": 105
  }
  ```
- **ساختار بازگشتی:**
  آرایه‌ای از عناصر سطح اول (Sections یا Flex Containers). هر المان شامل:
  ```json
  [
    {
      "id": "2d1f7c8",
      "elType": "container",
      "isInner": false,
      "settings": {
        "content_width": "boxed",
        "flex_direction": "row"
      },
      "elements": [
        {
          "id": "e93a0b1",
          "elType": "widget",
          "widgetType": "heading",
          "settings": {
            "title": "عنوان اصلی سایت",
            "header_size": "h1",
            "align": "right"
          },
          "elements": []
        }
      ]
    }
  ]
  ```
- **خطاهای احتمالی:**
  - `elementor_missing`: افزونه Elementor روی وردپرس فعال نیست.
  - `invalid_page`: شناسه برگه نامعتبر است یا این برگه با المنتور ساخته نشده است.

---

### `pressagent_update_widget`
بروزرسانی مستقیم تنظیمات یک ویجت بدون برهم‌زدن سایر بخش‌های درخت المنتور.

- **پارامترها:**
  - `page_id` (number, required): شناسه برگه
  - `widget_id` (string, required): شناسه یکتای ویجت هدف
  - `settings` (object, required): کلیدها و مقادیر جدید تنظیمات ویجت
- **نحوه عملکرد در سمت سرور:**
  1. یک اسنپ‌شات امنیتی (Action Guard Snapshot) از وضعیت برگه در دیتابیس ثبت می‌شود.
  2. درخت المان‌ها به صورت بازگشتی (Recursive) جستجو شده تا `widget_id` تطبیق داده شود.
  3. تنظیمات جدید با تنظیمات قبلی ترکیب (Merge) می‌شوند.
  4. متد رسمی ذخیره داکیومنت المنتور (`$document->save()`) فراخوانی می‌شود.
  5. کش‌های المنتور (`_elementor_element_cache` و CSS) و کش سایت پاکسازی می‌شوند.
- **خطاهای احتمالی:**
  - `widget_not_found`: ویجتی با این شناسه در این صفحه یافت نشد.

---

### `pressagent_add_elementor_section`
افزودن یک کانتینر/سکشن جدید به انتهای برگه یا بازنویسی کامل چیدمان.

- **پارامترها:**
  - `page_id` (number, required): شناسه برگه
  - `section_data` (object, required): اطلاعات کانتینر یا شیء حاوی `_replace_all`
- **حالت الف: اضافه کردن به انتهای صفحه (Append):**
  ```json
  {
    "page_id": 105,
    "section_data": {
      "id": "cont_88f9a2",
      "elType": "container",
      "settings": {
        "content_width": "boxed"
      },
      "elements": [ ... ]
    }
  }
  ```
- **حالت ب: بازنویسی چیدمان کامل برگه (Replace All):**
  ```json
  {
    "page_id": 105,
    "section_data": {
      "_replace_all": true,
      "elements": [
        /* آرایه کامل سکشن‌ها */
      ]
    }
  }
  ```

---

## ۲. ابزارهای مدیریت برگه‌ها (Pages Tools)

### `pressagent_list_pages`
دریافت فهرست تمام برگه‌های سایت همراه با متادیتای المنتور.
- **پارامترها:** ندارد.
- **خروجی:**
  ```json
  [
    {
      "id": 2,
      "title": "صفحه نمونه",
      "slug": "sample-page",
      "status": "publish",
      "date": "2026-01-01 12:00:00",
      "elementor_enabled": true
    }
  ]
  ```

### `pressagent_create_page`
ایجاد یک برگه جدید با قابلیت فعال‌سازی آنی ویرایشگر المنتور.
- **پارامترها:**
  - `title` (string, required): نام برگه
  - `content` (string, optional): محتوای اولیه وردپرس
  - `status` (string, optional): وضعیت برگه (`publish` یا `draft`، پیش‌فرض `draft`)
  - `elementor_enabled` (boolean, optional): تنظیم متای ویرایشگر بر روی `builder`

### `pressagent_delete_page`
حذف برگه به صورت دائمی. نیازمند توکن تاییدیه در صورت حساس بودن اکشن.

---

## ۳. ابزارهای تنظیمات، کش و لاگ (Settings, Cache & Logs)

### `pressagent_read_option` / `pressagent_write_option`
خواندن یا نوشتن در `wp_options` برای افزونه‌های لیست سفید (مانند WP Rocket, Wordfence, ...). در هر نوشتن، قبل از اعمال تغییر اسنپ‌شات تهیه می‌شود.

### `pressagent_purge_cache`
پاک‌کردن کش سراسری سایت یا افزونه‌ای مشخص:
- `plugin_slug`: مقادیری مانند `"all"`, `"wp-rocket"`, `"litespeed"`

### `pressagent_get_cache_status`
بررسی فعال بودن ماژول‌های کش در سایت وردپرسی.

### `pressagent_read_debug_log`
استخراج آخرین رکوردهای ثبت شده در `wp-content/debug.log`.
- `lines` (number, optional): تعداد خطوط پایانی (پیش‌فرض ۵۰)
- `level` (string, optional): فیلتر بر اساس سطح خطا (مانند `"error"`, `"warning"`, `"notice"`)

### `pressagent_diagnose_error`
تحلیل خطای لاگ توسط هوش مصنوعی و ارائه راهکار رفع اشکال.

---

## ۴. دسترسی‌های مورد نیاز (RBAC Scopes)

برای اجرای روان متدها، توکن صادر شده در پیشخوان وردپرس باید اسکوپ‌های زیر را داشته باشد:
- `pages:read` و `pages:write`
- `elementor:read` و `elementor:write`
- `settings:write` (برای پاکسازی کش)
- `debug:read` (برای نظارت بر لاگ‌ها)
