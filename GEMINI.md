# راهنمای توسعه و یکپارچه‌سازی PressAgent با Antigravity IDE

این پروژه پل ارتباطی هوش مصنوعی (AI Ops Bridge) برای وردپرس و المنتور است که سایت **مبل اشرافی** (`https://moblashrafi.com`) را مدیریت می‌کند.

## تنظیمات اتصال MCP
سرور MCP از طریق فایل سراسری `~/.gemini/config/mcp_config.json` پیکربندی شده و روی سایت زیر تنظیم گردیده است:
- **آدرس سایت وردپرس**: `https://moblashrafi.com`
- **توکن دسترسی**: احراز هویت با Bearer Token امن
- **دستور اجرایی سرور**: `npx -y pressagent` (متصل به سورس محلی کامپایل‌شده در `mcp-server/dist/index.js`)

## دستورالعمل‌های کلیدی کاری (Rules & Guidelines)
1. **استفاده انحصاری از ویجت‌های بومی المنتور (Elementor-Native Only):**
   - از نوشتن کدهای خام HTML/CSS یا شورت‌کدهای غیرضروری خودداری کنید. تمام المان‌ها باید از طریق ویجت‌های استاندارد المنتور (`heading`, `text-editor`, `image`, `button`, `container`, ...) درج و ویرایش شوند.
2. **بررسی ساختار قبل از ویرایش:**
   - قبل از هرگونه تغییر روی برگه‌ها، ابتدا درخت المان‌ها را با ابزار `pressagent_get_elementor_layout` بخوانید.
3. **تطهیر کش (Purge Cache):**
   - پس از هر ویرایش یا افزودن سکشن، کش لایت‌اسپید/راکت را با `pressagent_purge_cache` پاک کنید تا تغییرات بلافاصله منعکس شوند.
4. **بازرسی بصری (Visual QA):**
   - برای اطمینان از خروجی پیکسل‌پرفکت و عدم شکستگی استایل‌ها در حالت‌های دسکتاپ و موبایل، از `pressagent_capture_screenshot` استفاده کنید.
5. **رعایت اصول تایپوگرافی فارسی و راست‌چین (RTL):**
   - هماهنگی با فونت‌های استاندارد فارسی، تنظیم فواصل و چیدمان استاندارد Flexbox کانتینرها.

## مهارت‌های فعال (Active Skills)
تمام مهارت‌های مورد نیاز در مسیرهای `.agents/skills/` و `~/.gemini/config/skills/` ثبت شده و در دسترس هستند:
- `pressagent-elementor`: مدیریت و ویرایش استاندارد برگه‌های المنتور.
- `elementor-design-system`: الگوهای پیشرفته کانتینر و فلکس‌باکس در المنتور.
- `elementor-visual-inspector`: چرخه اعتبارسنجی بصری با اسکرین‌شات.
- `elementor-native-widgets`: انتخاب بهینه ویجت‌های بومی متناسب با نیاز کاربر.
- `elementor-schema-reference`: مرجع اسکیما و ساختار داخلی JSON گره‌های المنتور.
- `pressagent-child-theme`: انتخاب حالت ظاهری (المنتور/چایلد/هیبرید)، ساخت و ویرایش امن چایلد تم.
- `pressagent-plugins`: مدیریت دقیق SEO، امنیت، کش، فرم‌ها، ووکامرس، ریدایرکت و SMTP.
- `pressagent-performance`: آنالیز سرعت سرور/صفحه، رفع امن کش و بهینه‌سازی، و تنظیمات هسته وردپرس.
- سایر اسکیل‌های مهندسی: `wordpress-pro`, `php-pro`, `mcp-developer`, `playwright-expert`, `secure-code-guardian`, `security-reviewer`, `test-master`.

## ابزارهای جدید نسخه ۱.۱
- `pressagent_theme`: وضعیت/ساخت/سوییچ چایلد و ویرایش امن فایل‌ها.
- `pressagent_plugins` و `pressagent_plugin`: چرخه پلاگین و تنظیمات معتبرسازي‌شده.
- `pressagent_seo`: متای Yoast / Rank Math برای برگه و ترم.
- `pressagent_forms`: فرم‌های CF7 و WPForms.
- `pressagent_commerce`: محصولات و سفارش‌های ووکامرس.
- `pressagent_performance`: آنالیز سرعت و رفع امن مشکلات کش/فشرده‌سازی.
- `pressagent_wp_settings`: تنظیمات هسته وردپرس با اعتبارسنجی و rollback.
