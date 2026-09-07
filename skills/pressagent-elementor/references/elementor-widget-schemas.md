# ساختار داده و الگوهای ویجت‌های المنتور (Elementor Widget Schemas)

المنتور ساختار برگه را به عنوان یک آرایه تو در تو از اشیاء JSON ذخیره می‌کند. هر المان دارای فیلدهای الزامی زیر است:
- `id`: یک رشته تصادفی ۶ تا ۸ کاراکتری از حروف و اعداد هگزادسیمال یکتا (مانند `"4f8a12"`)
- `elType`: نوع گره (`container`, `section`, `column`, `widget`)
- `widgetType`: در صورت `elType === "widget"` نام نوع ویجت قرار می‌گیرد.
- `settings`: آبجکت تنظیمات و مقادیر ویجت
- `elements`: آرایه فرزندان (برای کانتینرها، سکشن‌ها و ستون‌ها)

---

## ۱. کانتینرها (Flexbox Container)

کانتینرهای مدرن المنتور بر مبنای CSS Flexbox کار می‌کنند:

```json
{
  "id": "c_a1b2c3",
  "elType": "container",
  "isInner": false,
  "settings": {
    "content_width": "boxed",
    "boxed_width": { "unit": "px", "size": 1140 },
    "flex_direction": "column",
    "flex_justify_content": "center",
    "flex_align_items": "center",
    "flex_gap": { "unit": "px", "size": 20, "column": "20", "row": "20" },
    "padding": {
      "unit": "px",
      "top": "60",
      "right": "20",
      "bottom": "60",
      "left": "20",
      "isLinked": false
    },
    "background_background": "classic",
    "background_color": "#ffffff"
  },
  "elements": []
}
```

---

## ۲. ویجت سرتیتر (Heading Widget)

```json
{
  "id": "w_h1a2b3",
  "elType": "widget",
  "widgetType": "heading",
  "settings": {
    "title": "عنوان جذاب برای برگه",
    "link": { "url": "", "is_external": "", "nofollow": "" },
    "header_size": "h2",
    "align": "right",
    "title_color": "#1e293b",
    "typography_typography": "custom",
    "typography_font_size": { "unit": "px", "size": 32 },
    "typography_font_weight": "700"
  },
  "elements": []
}
```

---

## ۳. ویجت ویرایشگر متن (Text Editor Widget)

```json
{
  "id": "w_t1a2b3",
  "elType": "widget",
  "widgetType": "text-editor",
  "settings": {
    "editor": "<p>این یک پاراگراف متنی نمونه با رعایت قواعد نگارشی و سئوی محتوا است.</p>",
    "align": "right",
    "text_color": "#475569",
    "typography_typography": "custom",
    "typography_font_size": { "unit": "px", "size": 16 },
    "typography_line_height": { "unit": "em", "size": 1.7 }
  },
  "elements": []
}
```

---

## ۴. ویجت دکمه (Button Widget)

```json
{
  "id": "w_b1a2b3",
  "elType": "widget",
  "widgetType": "button",
  "settings": {
    "text": "شروع همکاری",
    "link": {
      "url": "https://example.com/contact",
      "is_external": "on",
      "nofollow": ""
    },
    "align": "center",
    "size": "md",
    "button_text_color": "#ffffff",
    "background_color": "#2563eb",
    "border_radius": {
      "unit": "px",
      "top": "8",
      "right": "8",
      "bottom": "8",
      "left": "8",
      "isLinked": true
    },
    "text_padding": {
      "unit": "px",
      "top": "12",
      "right": "24",
      "bottom": "12",
      "left": "24",
      "isLinked": false
    }
  },
  "elements": []
}
```

---

## ۵. ویجت تصویر (Image Widget)

```json
{
  "id": "w_i1a2b3",
  "elType": "widget",
  "widgetType": "image",
  "settings": {
    "image": {
      "url": "https://example.com/wp-content/uploads/2026/01/banner.jpg",
      "id": 45
    },
    "image_size": "large",
    "align": "center",
    "caption_source": "none",
    "link_to": "none"
  },
  "elements": []
}
```

---

## ۶. ویجت فاصله و جداکننده (Spacer & Divider)

```json
// Spacer
{
  "id": "w_s1a2b3",
  "elType": "widget",
  "widgetType": "spacer",
  "settings": {
    "space": { "unit": "px", "size": 40 }
  },
  "elements": []
}

// Divider
{
  "id": "w_d1a2b3",
  "elType": "widget",
  "widgetType": "divider",
  "settings": {
    "style": "solid",
    "weight": { "unit": "px", "size": 1 },
    "color": "#e2e8f0",
    "width": { "unit": "%", "size": 100 },
    "align": "center"
  },
  "elements": []
}
```

---

## ۷. ویجت HTML سفارشی (Custom HTML Widget)

برای افزودن نشانه‌گذاری‌های خاص، کدهای اسکریپت تایید شده یا اسکیماهای سئو:

```json
{
  "id": "w_html1a2",
  "elType": "widget",
  "widgetType": "html",
  "settings": {
    "html": "<div class=\"custom-alert\">پیام مهم اطلاع‌رسانی</div>"
  },
  "elements": []
}
```

---

## ۸. ساخت شناسه یکتا (Unique IDs Generator)

المنتور روی تکراری نبودن `id` المان‌ها در صفحه بسیار حساس است. برای تولید شناسه جدید در اسکریپت یا در فرمت JSON، از یک رشته تصادفی هگزادسیمال به طول ۶ تا ۷ کاراکتر (مانند `Math.random().toString(36).substring(2, 9)`) استفاده کنید.
