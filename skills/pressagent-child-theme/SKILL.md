---
name: pressagent-child-theme
description: >-
  Use whenever a site needs a child theme, theme switching, appearance-mode decisions, or safe
  PHP/CSS file editing for WordPress. Covers Elementor-only, child-theme-only and hybrid setups.
---

# Child Theme و حالت ظاهری سایت با PressAgent

این مهارت مرجع تصمیم‌گیری و اجرای امن لایه ظاهری سایت است. ابزار اصلی: `pressagent_theme`.

## ۱. همیشه با وضعیت شروع کن

```
pressagent_theme({ action: "status" })
```

خروجی شامل: قالب فعال، والد، child بودن، block یا classic بودن، Elementor و Kit، تعداد قالب‌های Theme Builder، حالت ظاهری فعلی، راهنمای همان حالت، بکاپ‌های اخیر و هشدارهاست.

بر اساس `recommendations` تصمیم بگیر:
- اگر child نیست و کاربر می‌خواهد فایل ویرایش شود → اول `create_child`.
- اگر حالت با واقعیت سایت نمی‌خواند → `set_mode` مناسب را پیشنهاد بده (تغییر حالت را به کاربر اطلاع بده).

## ۲. سه حالت ظاهری

| حالت | مالک چیدمان | child theme برای | مناسب چه زمانی |
|---|---|---|---|
| `elementor` | Elementor (Kit + Theme Builder) | فقط هوک PHP و اصلاح CSS جزئی | سایت کاملاً المنتوری |
| `child-theme` | قالب PHP + CSS | هدر/فوتر/آرشیو/تک‌قالب | سایت کلاسیک بدون وابستگی به المنتور |
| `hybrid` | Elementor برای برگه‌ها/هدر/فوتر | توکن‌ها، CSS سراسری، هوک‌ها و override ووکامرس | پیشنهاد پیش‌فرض برای فروشگاه‌های فارسی |

تغییر حالت:

```
pressagent_theme({ action: "set_mode", mode: "hybrid" })
```

حالت فقط یک قرارداد کاری است؛ چیزی را در سایت تغییر نمی‌دهد. ایجنت باید بعد از آن مطابق جدول رفتار کند.

## ۳. ساخت و آماده‌سازی Child Theme

```
pressagent_theme({ action: "create_child", parent_slug: "astra", slug: "astra-child", activate: true })
pressagent_theme({ action: "setup", mode: "hybrid" })
```

`setup` این کارها را انجام می‌دهد:
- ساخت `assets/css/pressagent.css` اگر نبود.
- افزودن یک بلوک idempotent «PressAgent child enqueue» به `functions.php` (با بکاپ و `php -l`).
- ست کردن حالت ظاهری در صورت ارسال `mode`.

قوانین:
- فقط child theme فعال قابل ویرایش است؛ قالب والد read-only است.
- نام‌گذاری: `<parent>-child` مگر اینکه کاربر نام دیگری بخواهد.
- قبل از `overwrite: true` حتماً از کاربر تایید بگیر (فایل‌های موجود بازنویسی می‌شوند).

## ۴. ویرایش امن فایل

```
pressagent_theme({ action: "read_file", path: "functions.php" })
pressagent_theme({ action: "write_file", path: "assets/css/pressagent.css", content: "..." })
```

- همیشه **کل فایل** را بنویس، نه diff.
- برای فایل‌های PHP، سرور خودکار `php -l` اجرا می‌کند؛ سینتکس خراب ذخیره نمی‌شود.
- هر نوشتن روی فایل موجود یک backup می‌سازد؛ `backup_id` را نگه دار.
- برای بازگردانی: `{ action: "restore", backup_id: "..." }`.
- مسیرهای مجاز: `style.css`, `functions.php`, `assets/css/*.css`, `assets/js/*.js`, فایل‌های template PHP. از نوشتن `.htaccess`, فایل باینری و مسیر خارج از پوشه child خودداری کن.
- فایل را بیش از حد لازم نخوان (سقف ۲۵۶KB) و برای کشف ساختار از `list_files` استفاده کن.

## ۵. چیدمان فایل‌ها (پیشنهادی)

```
child-theme/
├── style.css                  # هدر قالب + بازنویسی‌های محدود
├── functions.php              # هوک‌ها، enqueue، فیلترها
├── theme.json                 # فقط برای block child
├── assets/
│   ├── css/pressagent.css     # توکن‌ها و لایه سراسری
│   └── js/pressagent.js       # تعاملات سبک
├── template-parts/            # بخش‌های قابل استفاده مجدد
└── woocommerce/               # override قالب‌های ووکامرس در صورت نیاز
```

## ۶. قواعد حالت Hybrid (مهم)

1. **Elementor مالک چیدمان برگه/هدر/فوتر است.** قالب PHP هدر/فوتر نساز که با Theme Builder رقابت کند.
2. **Child theme مالک توکن و CSS سراسری است.** رنگ برند، فونت فارسی، شعاع گوشه، فاصله‌ها و کلاس‌های سراسری در `assets/css/pressagent.css`.
3. **از `!important` فقط برای غلبه بر استایل المنتور در موارد ضروری** استفاده کن و هر مورد را با کامنت دلیل‌دار بنویس.
4. **هوک‌ها در `functions.php`** با پیشوند یکتا (`pa_`) و بررسی `function_exists` برای جلوگیری از تداخل.
5. **override ووکامرس** فقط در child theme و در پوشه `woocommerce/`؛ نسخه قالب والد را کپی و سپس ویرایش کن.
6. **behaviorهایی که المنتور نمی‌تواند**: فیلتر قیمت، سفارشی‌سازی checkout، schema، هدایت کاربران، ارسال ایمیل — در `functions.php`.
7. بعد از هر تغییر: `pressagent_purge_cache` و سپس اسکرین‌شات دسکتاپ + موبایل با `pressagent_capture_screenshot`.

## ۷. دستور پخت‌های سریع

**افزودن توکن سراسری:**
```css
:root {
  --pa-primary: #ff2a5f;
  --pa-surface: #0f1117;
  --pa-text: #e2e8f0;
  --pa-radius: 14px;
  --pa-font: 'Vazirmatn', sans-serif;
}
body { font-family: var(--pa-font); }
```

**enqueue دستی (اگر بلوک خودکار موجود نیست):**
```php
add_action( 'wp_enqueue_scripts', function () {
    wp_enqueue_style( 'pa-global', get_stylesheet_directory_uri() . '/assets/css/pressagent.css', array(), '1.0.0' );
}, 20 );
```

**override آرشیو محصول ووکامرس:**
مسیر: `woocommerce/archive-product.php` — ابتدا فایل والد را در child کپی کن، بعد ویرایش.

## ۸. بهینه‌سازی مصرف توکن

- `status` را یک بار بگیر و نتیجه را خلاصه نگه دار؛ هر بار تکرار نکن.
- برای دیدن ساختار از `list_files` استفاده کن، نه خواندن همه فایل‌ها.
- فقط فایل هدف را بخوان؛ خروجی `read_file` شامل کل محتواست، پس خواندن‌های غیرضروری انجام نده.
- در توضیحات نهایی به کاربر، فقط مسیر فایل، بکاپ و نتیجه بصری را گزارش کن.

## ۹. چک‌لیست پایان کار

- [ ] child theme فعال و `status` بدون هشدار
- [ ] حالت ظاهری با تصمیم کاربر هم‌راستا
- [ ] `php -l` برای فایل‌های PHP پاس شده (سرور خودکار)
- [ ] backup_id ثبت شده
- [ ] کش پاک شده
- [ ] اسکرین‌شات دسکتاپ و موبایل بررسی شده
