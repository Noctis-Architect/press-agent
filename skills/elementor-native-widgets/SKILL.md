---
name: elementor-native-widgets
description: >-
  Mandatory for every PressAgent Elementor page creation/editing task. Ensures all output
  uses native Elementor widgets (heading, text-editor, button, image, icon-box, etc.)
  instead of raw HTML or Shortcode widgets, so admins can visually edit every element
  in Elementor's drag-and-drop editor.
---

# Elementor Native Widgets — قانون اجباری

> [!CAUTION]
> این اسکیل یک خط‌قرمز غیرقابل‌مذاکره است. هر بار که محتوایی روی یک صفحه المنتور
> اضافه یا ویرایش می‌شود، **ممنوع** است از موارد زیر استفاده شود:
> - ویجت `html` (خروجی خام HTML/CSS/JS)
> - ویجت `shortcode`
> - نوشتن CSS دلخواه داخل بلوک `<style>` به‌جای Style controls بومی المنتور

**چرا مهم است؟** وقتی محتوا HTML خام باشد، ادمین هنگام ویرایش صفحه در المنتور فقط
یک جعبه خاکستری «HTML» یا «Shortcode» می‌بیند — نه المان‌های قابل کلیک و قابل
ویرایش. یعنی کل هدف پروژه (اینکه ادمین بتواند بعد از AI ادامه دهد) از بین می‌رود.

---

## ۱. چک‌لیست اجباری قبل از هر Write

قبل از فراخوانی `pressagent_update_widget` یا `pressagent_add_elementor_section`،
**سه سوال** را از خودت بپرس:

### سوال ۱: آیا معادل بومی وجود دارد؟

اکثر چیزهایی که فکر می‌کنی نیاز به HTML خام دارد، معادل بومی دارند.
جدول بخش ۲ را ببین.

### سوال ۲: آیا `elType` و `widgetType` درست ست شده‌اند؟

هر ویجت باید `elType: "widget"` و `widgetType` مشخص (مثل `"heading"`) داشته باشد.
**هیچ‌وقت** `widgetType: "html"` یا `"shortcode"` نباید در خروجی نهایی باشد
مگر کاربر صریحاً درخواستش کرده باشد.

### سوال ۳: آیا استایل در `settings` بومی قرار دارد؟

رنگ، فاصله، سایز فونت، پدینگ و مارجین باید از طریق کلیدهای settings همان ویجت
(مثل `title_color`, `_padding`, `_margin`, `typography_font_size`) تنظیم شوند
— **نه** در یک بلوک `<style>` جداگانه.

---

## ۲. نگاشت نیاز رایج ← ویجت بومی المنتور

| نیاز / ظاهری که می‌خواهی بسازی | ویجت بومی درست |
|---|---|
| عنوان بزرگ / تیتر صفحه | `heading` |
| پاراگراف متن | `text-editor` |
| دکمه | `button` |
| تصویر تکی | `image` |
| گالری تصاویر | `image-gallery` یا `image-carousel` |
| کارت با آیکون + متن (فیچر لیست) | `icon-box` |
| Grid چند‌ستونه از کارت‌ها | `container` با `flex_direction: row` شامل چند `icon-box` |
| Divider / خط جداکننده | `divider` |
| Spacer / فاصله خالی | `spacer` |
| فرم تماس | `form` (Elementor Pro) — اگر نبود، `text-editor` + توضیح محدودیت |
| ویدیو embed شده | `video` |
| Accordion / سوالات متداول | `accordion` |
| تب‌ها | `tabs` |
| لیست آیکون‌دار | `icon-list` |
| شمارنده عددی | `counter` |
| نوار پیشرفت | `progress` |
| ستاره / امتیاز | `star-rating` |
| لایه‌بندی چند‌ستونه | `container` با `flex_direction` — **هرگز** `div` دستی |

> [!TIP]
> اگر نیازی در این جدول نبود، اول در مستندات Elementor Widgets دنبال معادلش بگرد.
> فقط اگر واقعاً هیچ ویجت بومی یا ترکیبی از ویجت‌ها جواب نداد **و** کاربر آگاهانه
> HTML خام خواسته بود، از ویجت `html` استفاده کن — و صریحاً به کاربر اعلام کن
> که این بخش دیگر با کنترل‌های بصری المنتور قابل ویرایش نخواهد بود.

---

## ۳. نمونه ساختار درست vs غلط

### ❌ غلط — HTML خام:

```json
{
  "elType": "widget",
  "widgetType": "html",
  "settings": {
    "html": "<div style='padding:20px'><h2>عنوان</h2><p>متن توضیحی</p></div>"
  }
}
```

### ✅ درست — ویجت‌های بومی:

```json
{
  "elType": "container",
  "settings": {
    "padding": { "unit": "px", "top": "20", "bottom": "20", "left": "20", "right": "20" }
  },
  "elements": [
    {
      "elType": "widget",
      "widgetType": "heading",
      "settings": { "title": "عنوان", "header_size": "h2" }
    },
    {
      "elType": "widget",
      "widgetType": "text-editor",
      "settings": { "editor": "متن توضیحی" }
    }
  ]
}
```

### ❌ غلط — استایل در بلوک CSS:

```json
{
  "widgetType": "heading",
  "settings": {
    "title": "عنوان",
    "custom_css": "selector h2 { color: #ff0000; font-size: 32px; }"
  }
}
```

### ✅ درست — استایل در settings بومی:

```json
{
  "widgetType": "heading",
  "settings": {
    "title": "عنوان",
    "header_size": "h2",
    "title_color": "#ff0000",
    "typography_font_size": { "unit": "px", "size": 32 }
  }
}
```

---

## ۴. بررسی نهایی بعد از هر ویرایش

بعد از هر ویرایش، خروجی JSON نهایی را مرور کن و مطمئن شو:

1. **هیچ `widgetType: "html"` یا `"shortcode"` وجود ندارد** — مگر توجیه شده و
   به کاربر اعلام شده باشد.
2. **هیچ بلوک `<style>` یا `<script>` در settings هیچ ویجتی نیست** — تمام
   استایل‌ها از کنترل‌های بومی المنتور استفاده می‌کنند.
3. **همه المان‌ها `elType` و `widgetType` صحیح دارند** — container ها
   `elType: "container"` و ویجت‌ها `elType: "widget"` با `widgetType` مناسب.

> [!IMPORTANT]
> اگر ابزار `pressagent_screenshot_page` یا `pressagent_capture_screenshot` در
> دسترس است، بعد از ذخیره صفحه را اسکرین‌شات بگیر و مطمئن شو هیچ جعبه خاکستری
> «HTML» یا «Shortcode» نمایش داده نمی‌شود. هر چیزی باید بصری و قابل کلیک باشد.

---

## ۵. تنها استثنای مجاز

استفاده از ویجت `html` **فقط و فقط** در این شرایط مجاز است:

1. کاربر **صریحاً و آگاهانه** درخواست HTML خام کرده باشد.
2. قبل از اعمال، به کاربر **هشدار داده شود** که این بخش در ویرایشگر بصری المنتور
   به‌صورت جعبه خاکستری غیرقابل ویرایش نمایش داده خواهد شد.
3. در پیام پایانی، این استثنا **مستند شود** تا کاربر بداند کدام بخش‌ها HTML خام
   هستند.
