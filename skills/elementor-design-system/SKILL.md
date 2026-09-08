---
name: elementor-design-system
description: >-
  Provides modern design patterns, Elementor Flexbox Container recipes, and Hybrid HTML/CSS
  techniques for creating stunning WordPress pages. Solves layout stacking bugs, vertical misalignment,
  and empowers LLMs to use their full CSS/HTML design creativity inside Elementor.
---

# Elementor Design System & Layout Recipes

این اسکیل مشکل طراحی انتزاعی و باگ‌های چینش در المنتور (مانند انباشته شدن عمودی کارت‌ها به‌جای افقی قرار گرفتن، یا ظاهر خشک ویجت‌ها) را از طریق الگوهای تست‌شده و تکنیک **ترکیب قدرت CSS خام با المنتور** حل می‌کند.

---

## ۱. تکنیک طلایی: هیبرید HTML/CSS در المنتور (Hybrid Technique)

مدل‌های زبانی در نوشتن HTML/CSS و دیزاین‌های مدرن (Glassmorphism، CSS Grid، انیمیشن‌های نرم، گرادینت‌های لوکس) فوق‌العاده آموزش دیده‌اند. اما در ویرایشگر المنتور، محدود شدن صرف به تنظیمات محدود ویجت‌ها دست طراح را می‌بندد.

### راهکار هیبرید:
می‌توان در ساختار المنتور یک کانتینر مادر تعریف کرد و درون آن یک ویجت `html` قرار داد. با این روش:
1. المان در قالب المنتور ذخیره می‌شود و ادمین می‌تواند جابجایش کند.
2. تمام قدرت استایل‌دهی مدرن، فلکس‌باکس و ریسپانسیو CSS در اختیار شماست.

```json
{
  "id": "c_features_wrap",
  "elType": "container",
  "settings": {
    "content_width": "boxed",
    "boxed_width": { "unit": "px", "size": 1200 }
  },
  "elements": [
    {
      "id": "w_features_html",
      "elType": "widget",
      "widgetType": "html",
      "settings": {
        "html": "<div class=\"pro-cards-grid\">\n  <div class=\"pro-card\">\n    <div class=\"card-badge\">ویژه</div>\n    <div class=\"card-icon\"><svg ...></svg></div>\n    <h3 class=\"card-title\">عنوان اشتراک</h3>\n    <p class=\"card-desc\">توضیحات کوتاه محصول</p>\n    <div class=\"card-price\">۹۹,۰۰۰ <span>تومان / ماه</span></div>\n    <a href=\"/buy\" class=\"card-btn\">خرید اشتراک</a>\n  </div>\n</div>\n<style>\n.pro-cards-grid {\n  display: grid;\n  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));\n  gap: 24px;\n  direction: rtl;\n}\n.pro-card {\n  background: rgba(255, 255, 255, 0.04);\n  border: 1px solid rgba(255, 255, 255, 0.1);\n  border-radius: 16px;\n  padding: 24px;\n  backdrop-filter: blur(12px);\n  transition: all 0.3s ease;\n}\n.pro-card:hover {\n  transform: translateY(-6px);\n  border-color: #ec4899;\n}\n</style>"
      },
      "elements": []
    }
  ]
}
```

---

## ۲. فرمول قطعی چیدمان افقی کارت‌ها در کانتینرهای بومی (Flexbox Recipes)

علت اصلی باگ «عمودی شدن کارت‌ها به‌جای افقی»:
در المنتور کانتینر به‌صورت پیش‌فرض `flex_direction: column` است. برای قرار گرفتن کارت‌ها در یک ردیف افقی، تنظیمات زیر در کانتینر مادر الزامی است:

### کانتینر ردیفی (Row Container):
```json
{
  "id": "c_row_parent",
  "elType": "container",
  "settings": {
    "content_width": "boxed",
    "boxed_width": { "unit": "px", "size": 1280 },
    "flex_direction": "row",
    "flex_wrap": "wrap",
    "flex_justify_content": "space-between",
    "flex_align_items": "stretch",
    "flex_gap": { "unit": "px", "size": 24, "column": "24", "row": "24" }
  },
  "elements": [
    /* کارت‌های ستونی در اینجا قرار می‌گیرند */
  ]
}
```

### تنظیمات هر کارت فرزند (Child Card Container):
هر کارت درون این ردیف باید عرض مشخص داشته باشد:
```json
{
  "id": "c_card_item_1",
  "elType": "container",
  "settings": {
    "content_width": "full",
    "width": { "unit": "%", "size": 31 },
    "width_tablet": { "unit": "%", "size": 48 },
    "width_mobile": { "unit": "%", "size": 100 },
    "padding": { "unit": "px", "top": "24", "right": "24", "bottom": "24", "left": "24", "isLinked": true },
    "background_background": "classic",
    "background_color": "#111827",
    "border_border": "solid",
    "border_width": { "unit": "px", "top": "1", "right": "1", "bottom": "1", "left": "1", "isLinked": true },
    "border_color": "rgba(255, 255, 255, 0.1)",
    "border_radius": { "unit": "px", "top": "16", "right": "16", "bottom": "16", "left": "16", "isLinked": true }
  },
  "elements": [ ... ]
}
```

---

## ۳. الگوی هدر مدرن و ریسپانسیو (Header Recipe)

هدر در زبان فارسی (RTL) باید ۳ بخش مجزا در یک ردیف افقی داشته باشد:
1. **راست:** لوگو و نام برند
2. **وسط:** منوی ناوبری اصلی (لینک‌های تمیز)
3. **چپ:** دکمه ورود/عضویت و سبد خرید

```json
{
  "id": "hdr_outer",
  "elType": "container",
  "settings": {
    "content_width": "full",
    "flex_direction": "row",
    "flex_justify_content": "space-between",
    "flex_align_items": "center",
    "padding": { "unit": "px", "top": "16", "right": "32", "bottom": "16", "left": "32", "isLinked": false }
  },
  "elements": [
    /* ۳ کانتینر فرزند با flex_direction: row برای لوگو، منو و دکمه‌ها */
  ]
}
```

---

## ۴. چک‌لیست جلوگیری از خطاهای رایج بصری

- [ ] **هیچ کارتی با عرض ۱۰۰٪ بدون نیاز در دسکتاپ رها نشود.**
- [ ] **برای صفحات تاریک (Dark Mode):** از متن‌های با کنتراست بالا (`#f8fafc` برای عناوین و `#94a3b8` برای توضیحات) استفاده کنید.
- [ ] **فاصله مناسب بین سکشن‌ها:** هر سکشن اصلی در دسکتاپ حداقل `80px` تا `100px` پدینگ بالا و پایین داشته باشد.
- [ ] **عدم استفاده بیش از حد از ایموجی:** به جای ایموجی، از آیکون‌های SVG استاندارد FontAwesome یا استروک‌های مینیمال استفاده کنید.
