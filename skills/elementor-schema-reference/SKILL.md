---
name: elementor-schema-reference
description: راهنمای فنی کامل ساختار JSON المنتور — برای هر تسک ساخت/ویرایش صفحه با PressAgent باید رعایت شود تا المانها همیشه بهعنوان المان معتبر و قابلویرایش شناسایی شوند، نه بهصورت خراب یا ناشناخته.
---

# چرا این راهنما لازمه

المنتور یک schema سختگیرانه داره. اگه یک کلید اجباری جا بیفته، نوع داده اشتباه باشه (مثلاً string بهجای object برای یک مقدار ابعادی)، یا nesting غلط باشه (مثلاً widget مستقیم زیر سند بدون container/section)، نتیجه یکی از اینهاست:
- صفحه در پیشخوان **خالی یا سفید** رندر میشه
- المان بهصورت یک بلوک **"Invalid" / خراب** نمایش داده میشه
- المنتور در پسزمینه صفحه رو **بازسازی (regenerate)** میکنه و تغییرات AI از بین میره

این راهنما schema رو دقیق پوشش میده تا این اتفاقها نیفته.

---

## ۱. تشخیص نسخهی schema قبل از هر write (مرحلهی اول، همیشه)

المنتور از یک نسخهی خاص به بعد (Atomic Widgets architecture، اواخر ۲۰۲۵/۲۰۲۶) یک ساختار دادهی جدید و متفاوت معرفی کرده. **قبل از هر عملیات write، نسخهی المنتور سایت هدف رو استعلام بگیر** (از طریق ابزار `pressagent_get_cache_status` یا یک ابزار مشابه که ورژن رو برمیگردونه) و بر اساسش تصمیم بگیر کدوم schema زیر رو استفاده کنی. **هیچوقت فرض نکن schema قدیمیه یا جدیده** — این خودش یکی از رایجترین دلایل «المان شناسایی نشد» هست.

---

## ۲. ساختار سند (Document-level) — کلاسیک/Container

هر صفحه در سطح بالا این کلیدها رو لازم داره:

```json
{
  "title": "عنوان صفحه",
  "type": "page",
  "version": "0.4",
  "page_settings": [],
  "content": [ /* آرایهی المانهای سطح بالا */ ]
}
```

- `content` هیچوقت نباید حذف بشه؛ اگه صفحه خالیه، آرایهی خالی `[]` بذار، نه `null`.
- `version` رشتهست، نه عدد.

---

## ۳. ساختار پایهی هر المان (مشترک بین همه)

```json
{
  "id": "6af611eb",
  "elType": "...",
  "isInner": false,
  "settings": [],
  "elements": []
}
```

| کلید | نوع | نکتهی حیاتی |
|---|---|---|
| `id` | string | باید **یکتا** در کل صفحه باشه. از یک رشتهی هگزادسیمال ۸ کاراکتری تصادفی استفاده کن (مثل `6af611eb`). هیچوقت ID تکراری یا خالی نذار — باعث تداخل و خرابی رندر میشه. |
| `elType` | string | یکی از: `container`، `section`، `column`، `widget`. هیچ مقدار دیگهای معتبر نیست. |
| `isInner` | boolean | برای المان سطح بالا `false`. برای container/column داخل یک container/section دیگه، `true`. جا انداختنش معمولاً باعث مشکل نمایش تو دیدگاه ادیتور میشه (نه همیشه fatal، ولی همیشه ست کن تا مطمئن باشی). |
| `settings` | array/object | اگه تنظیمی نداره، **آرایهی خالی `[]`** (نه object خالی `{}`) — این تفاوت ظریف رو المنتور بهخصوص در نسخههای قدیمیتر جدی میگیره. اگه تنظیم داره، object با کلید-مقدار. |
| `elements` | array | آرایهی المانهای فرزند. برای widget های ساده (بدون nesting)، `[]`. |

---

## ۴. Widget — کلید اضافهی اجباری

```json
{
  "id": "6a637978",
  "elType": "widget",
  "widgetType": "heading",
  "isInner": false,
  "settings": { "title": "متن تیتر", "header_size": "h2" },
  "elements": []
}
```

`widgetType` **اجباریه** وقتی `elType: "widget"` — بدون این کلید، المنتور نمیفهمه چه ویجتی رندر کنه و المان رو نامعتبر میشناسه.

---

## ۵. ساختار Container (توصیهشده — بهجای section/column قدیمی)

```json
{
  "id": "458aabdc",
  "elType": "container",
  "isInner": false,
  "settings": {
    "content_width": "boxed",
    "flex_direction": "column",
    "padding": {"unit": "px", "top": "20", "right": "20", "bottom": "20", "left": "20", "isLinked": true}
  },
  "elements": [ /* container یا widget های تودرتو */ ]
}
```

نکتهی مهم دربارهی مقادیر ابعادی (padding, margin, width, height و…): همیشه **object با `unit` و `size`** هستن، نه رشتهی خام مثل `"20px"`. این رایجترین اشتباهیه که باعث میشه مقدار در ادیتور المنتور نادیده گرفته بشه یا ارور بده:

```json
"custom_height": {"unit": "vh", "size": 70, "sizes": []}
```

**همیشه از `container` بهجای ساختار قدیمی section/column استفاده کن** مگر صفحهی موجود از قبل با section/column ساخته شده باشه (در اون صورت باید با همون ساختار ادامه بدی تا سازگار بمونه — بخش ۶ رو ببین).

---

## ۶. ساختار قدیمی (Section → Column → Widget) — فقط برای سازگاری

اگه صفحهای که ویرایش میکنی از قبل با این ساختار ساخته شده:

```json
{
  "id": "123ab956",
  "elType": "section",
  "isInner": false,
  "settings": [],
  "elements": [
    {
      "id": "col001",
      "elType": "column",
      "isInner": false,
      "settings": {"_column_size": 50},
      "elements": [
        { "id": "w001", "elType": "widget", "widgetType": "heading", "settings": {...}, "elements": []}
      ]
    }
  ]
}
```

**قانون nesting سختگیرانه:** `widget` هیچوقت نباید مستقیم زیر `section` باشه — باید حتماً داخل یک `column` باشه. این یکی از رایجترین علتهای «المان بهدرستی نمایش داده نمیشه» در ساختار قدیمیه.

**هیچوقت ساختار قدیمی و container رو در یک صفحه مخلوط نکن** مگر مطمئنی نسخهی المنتور از هر دو پشتیبانی میکنه (اکثر نسخههای جدید میکنن، ولی سازگاری بصری بینشون گاهی مشکل ایجاد میکنه).

---

## ۷. جدول ویجتهای رایج و کلیدهای settings

| ویجت | `widgetType` | کلیدهای settings مهم |
|---|---|---|
| تیتر | `heading` | `title`, `header_size` (h1-h6), `align` |
| متن | `text-editor` | `editor` (محتوای HTML داخلی خود ویجت — این جزو استثناهای مجاز HTML هست چون بومی خود ویجته) |
| دکمه | `button` | `text`, `link` (object: `{url, is_external, nofollow}`), `size` |
| تصویر | `image` | `image` (object: `{url, id, alt}`) |
| Icon List | `icon-list` | `icon_list` (آرایهی object) |
| Icon Box | `icon-box` | `title_text`, `description_text`, `selected_icon` |
| فرم | `form` | `form_fields`, `email_to` (نیازمند Elementor Pro) |
| ویدیو | `video` | `youtube_url` یا `vimeo_url` |
| Divider | `divider` | `style`, `weight` |
| Spacer | `spacer` | `space` (object: `{unit, size}`) |

---

## ۸. نسخهی جدید — Atomic Widgets (اگر سایت هدف روی این نسخه است)

از نسخههای جدید المنتور، معماری Atomic معرفی شده که schema متفاوتی داره — نام ویجتها با پیشوند `e-` میاد (`e-heading` بهجای `heading`) و settings دیگه ساده key-value نیست:

```json
{
  "id": "12345678",
  "version": "0.0",
  "elType": "widget",
  "widgetType": "e-heading",
  "isInner": false,
  "settings": [],
  "editor_settings": [],
  "interactions": [],
  "styles": [],
  "elements": []
}
```

مقادیر settings در این معماری بهصورت **typed object** با کلید `$$type` و `value` ذخیره میشن (نه مقدار خام). **قبل از نوشتن روی سایتی که این معماری رو داره، schema دقیق هر کنترل رو استعلام بگیر — حدس نزن**، چون ساختار typed این نسخه با نسخهی کلاسیک اصلاً یکی نیست و اگه اشتباه بزنی، المان بهطور کامل نامعتبر میشه.

---

## ۹. چکلیست نهایی قبل از هر save

- [ ] نسخه/معماری المنتور سایت هدف رو چک کردم (بخش ۱)
- [ ] همهی `id` ها یکتا هستن
- [ ] هر `widget` دارای `widgetType` هست
- [ ] هیچ `widget` مستقیم زیر `section` نیست (باید زیر `column` باشه)
- [ ] مقادیر ابعادی (padding/margin/size) بهصورت object با `unit`/`size` هستن، نه رشته
- [ ] `settings` خالی = `[]`، نه `{}`
- [ ] ذخیره از طریق مسیر `save()` داخلی داکیومنت المنتور انجام شده (نه `update_post_meta` خام) — طبق skill `elementor-native-widgets` و بحث کش
- [ ] اگه ابزار اسکرینشات در دسترسه، بعد از save صفحه رو چک بصری کن
