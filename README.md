# PressAgent 🚀

### The Autonomous AI Ops & Design Bridge for WordPress

<p align="center">
  <a href="#pressagent-"><b>English</b></a> • <a href="#-پرسایجنت-pressagent"><b>فارسی</b></a>
</p>

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![Version](https://img.shields.io/badge/version-1.0.1-green.svg)](https://github.com/Noctis-Architect/press-agent)
[![WordPress](https://img.shields.io/badge/WordPress-5.8%2B-blue.svg)](https://wordpress.org)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://php.net)
[![TypeScript](https://img.shields.io/badge/TypeScript-5.3%2B-blue.svg)](https://www.typescriptlang.org)
[![MCP](https://img.shields.io/badge/MCP-1.0%2B-orange.svg)](https://modelcontextprotocol.io)

> **PressAgent** connects AI coding assistants and autonomous agents (**Claude Code**, **Antigravity**, **Cursor**, **Windsurf**, **Cline**) directly to live WordPress environments.
>
> Unlike simple REST wrappers or generic MCP tools that dump raw, un-editable HTML, PressAgent is a **battle-tested AI Ops & Builder Bridge**. It builds native **Elementor Flexbox Containers**, takes headless visual regression screenshots, parses PHP debug logs for self-healing, provides automated snapshots with one-click rollbacks via **Action Guard™**, and enforces cryptographically hashed, role-based access control (RBAC).

---

## 🌟 Key Capabilities

### 🎨 1. Native Elementor Page & Layout Engine
- **100% Visual Drag-and-Drop Compatibility:** PressAgent does **not** inject raw HTML or opaque shortcodes. It creates and manipulates native Elementor widgets (`heading`, `text-editor`, `button`, `image`, `icon-box`, `counter`, etc.) within modern Elementor Flexbox Containers.
- **Full WP Admin Editability:** Site administrators and clients can open the page in Elementor and visually customize colors, typography, paddings, and content without breaking the layout.
- **Deep Core Integration:** Interacts directly with `Elementor\Plugin::$instance->documents->get($page_id)` and `$document->save()`, automatically invalidating Elementor CSS files (`_elementor_css`), widget element caches, and theme builder templates.

### 📸 2. Headless Visual QA & Screenshot Engine
- **AI Eyes for Pixel-Perfect Iteration:** Integrated headless browser screenshot tool (`pressagent_capture_screenshot`) enables the AI assistant to inspect rendered desktop, tablet, and mobile layouts visually.
- **SSRF-Hardened:** Strictly validates target URLs against the configured WordPress host origin to prevent Server-Side Request Forgery.
- **Configurable Captures:** Supports custom viewport widths/heights, full-page screenshots, delay timers (for JavaScript/animations), and targeted CSS selectors.

### 🛡️ 3. Action Guard™: Automated Snapshots & One-Click Rollbacks
- **Zero-Risk Operations:** Every destructive or layout-altering action (`update_elementor`, `update_option`, page deletions) automatically records an encrypted snapshot in `wp_pressagent_snapshots` before executing.
- **One-Click Instant Rollback:** If an AI change causes a regression or styling conflict, revert immediately from the WordPress admin dashboard (`Settings → PressAgent`) or via the API.
- **Two-Tier Safety Protocol:**
  - **Reversible actions:** Automatically executed with pre-execution snapshots.
  - **Non-reversible actions:** Require explicit confirmation tokens from the human operator before execution.
- **Tamper-Proof:** Disallows rolling back security allowlists or escalating privileges through snapshot restoration.

### 🔐 4. Enterprise RBAC & WordPress Authentication Pipeline
- **Cryptographic Token Security:** Tokens are generated with high entropy (48-character passwords) and stored as salted hashes using WordPress's native `phpass` (`PasswordHash`). Raw tokens are never persisted in the database.
- **Native User Context Mapping:** Hooks directly into WordPress core's `determine_current_user` authentication filter. REST API requests are authenticated and mapped to the authorized administrator user context, allowing standard capabilities (`edit_posts`, `edit_pages`, `manage_options`) to function seamlessly.
- **Granular Least-Privilege Scopes:** Restrict access to specific functions (`pages:read`, `elementor:write`, `settings:read`, `cache:purge`, etc.).
- **In-Memory Cache:** Request-level token caching delivers sub-millisecond authentication verification.

### ⚙️ 5. Safe Cross-Plugin & Settings Management
- **Strict Settings Allowlist:** Access to `wp_options` is strictly limited through a granular allowlist (`pressagent_settings_allowlist`) with wildcard pattern support (`wordfence*`, `litespeed.conf.*`).
- **Pre-Configured Plugin Integrations:** Out-of-the-box support for popular caching and security plugins: **WP Rocket**, **LiteSpeed Cache**, and **Wordfence**.
- **Privilege Escalation Defense:** Critical WordPress options (`users_can_register`, `default_role`, `active_plugins`, `siteurl`) are strictly guarded against unauthorized modification.
- **Automatic Cache Invalidation:** Automatically triggers cache cleans across WP Rocket, LiteSpeed, and Elementor when settings or layouts are updated.

### 🔍 6. Intelligent Debug Log Parser & Diagnostics
- **Structured Error Extraction:** Automatically reads and parses `WP_DEBUG_LOG` (`debug.log`), extracting timestamps, error levels (`error`, `warning`, `notice`), culprit filenames, and line numbers into structured JSON.
- **Path-Traversal Guard:** Strictly validates log paths to prevent arbitrary file reading outside `WP_CONTENT_DIR`.
- **AI-Powered Diagnostics:** The `pressagent_diagnose_error` tool analyzes stack traces and conflict logs to suggest actionable remediation steps.

### ⚡ 7. Developer / God Mode Scaffolding (Staging Only)
- **Plugin Scaffolding:** Allows AI agents to generate and scaffold custom WordPress plugins (`pressagent_create_plugin`) on development and staging sites.
- **Strictly Isolated:** Requires the `code:execute` scope, disabled by default in production.

---

## 🏗️ Architecture

```
+-------------------------------------------------------------------------+
|                        AI Assistant / MCP Client                        |
|       [Claude Code]  [Antigravity IDE]  [Cursor]  [Windsurf]  [Cline]   |
|                                     |                                   |
|                                     | (Stdio / JSON-RPC Protocol)       |
|                                     v                                   |
|                   [PressAgent MCP Server (Node.js/TS)]                  |
+-------------------------------------------------------------------------+
                                      |
                                      | (HTTPS + Bearer Token Header)
                                      v
+-------------------------------------------------------------------------+
|                       Target WordPress Environment                       |
|                        [PressAgent Plugin (PHP)]                        |
|                                     |                                   |
|       +-----------------------------+-----------------------------+     |
|       |                             |                             |     |
|       v                             v                             v     |
| [PressAgent_Auth]        [PressAgent_REST_API]         [Action Guard]   |
| - determine_current_user - /pressagent/v1/pages        - DB Snapshots   |
| - Scoped RBAC Validation - /pressagent/v1/elementor    - One-Click      |
| - phpass Hashed Tokens   - /pressagent/v1/settings       Rollbacks      |
| - In-Memory Cache        - /pressagent/v1/debug-log    - Confirmation   |
|                          - /pressagent/v1/cache          Tokens         |
|                                     |                                   |
|       +-----------------------------+-----------------------------+     |
|       |                             |                             |     |
|       v                             v                             v     |
| [Elementor Adapter]       [Generic Settings]       [Debug Log Parser]   |
| - Container Tree Builder  - Whitelist Validator    - Structured Parsing |
| - Native Widget Injection - WP Rocket / LiteSpeed  - Traversal Shield   |
| - Internal save() Path    - Privilege Guard        - AI Diagnostic      |
| - Elementor CSS Clearing  - Auto Cache Purge         Remediation        |
+-------------------------------------------------------------------------+
```

---

## 🧰 Complete MCP Tools Reference

| Category | Tool Name | Description | Required Scope |
|---|---|---|---|
| **Pages & Content** | `pressagent_list_pages` | List all pages with ID, slug, title, status, and Elementor edit mode | `pages:read` |
| | `pressagent_create_page` | Create new pages (draft/publish) with optional Elementor builder mode | `pages:write` |
| | `pressagent_delete_page` | Safely delete a page (Action Guard confirmation token required) | `pages:write` |
| **Elementor Builder** | `pressagent_get_elementor_layout` | Retrieve the complete hierarchical JSON element tree of any Elementor page | `elementor:read` |
| | `pressagent_update_widget` | Update settings/content of a specific Elementor widget by its ID | `elementor:write` |
| | `pressagent_add_elementor_section` | Add new containers/sections or replace entire layout with native widgets | `elementor:write` |
| **Visual Inspection** | `pressagent_capture_screenshot` | Capture headless browser screenshots (Desktop/Tablet/Mobile) with SSRF guard | `pages:read` |
| **Settings & Cache** | `pressagent_read_option` | Read allow-listed WordPress or plugin options | `settings:read` |
| | `pressagent_write_option` | Safely update allow-listed options with automatic pre-snapshots | `settings:write` |
| | `pressagent_purge_cache` | Clear site caches (WP Rocket, LiteSpeed Cache, Elementor files) | `settings:write` |
| | `pressagent_get_cache_status` | Check active caching systems, versions, and directory status | `settings:read` |
| **Diagnostics & Logs** | `pressagent_read_debug_log` | Read recent parsed `debug.log` entries (filtered by error/warning/notice) | `debug:read` |
| | `pressagent_diagnose_error` | Get structured AI-guided diagnostics and resolution steps for an error | `debug:read` |
| **Security & Audits** | `pressagent_security_summary` | Inspect WordPress version, PHP version, writable permissions, and plugins | `security:read` |
| **Dev / Scaffolding** | `pressagent_create_plugin` | Scaffold and generate custom WordPress plugins (Staging only) | `code:execute` |

---

## 🔒 Security Architecture

Security is at the foundation of PressAgent:

1. **No Plaintext Tokens:** All API tokens are hashed using `PasswordHash` (WordPress core phpass) before storage. Stolen database dumps cannot expose raw tokens.
2. **True WordPress RBAC Integration:** PressAgent integrates directly into WordPress's `determine_current_user` hook. The token resolves to an actual administrator account, so native WordPress permission gates (`edit_posts`, `edit_pages`, `manage_options`) work out of the box.
3. **SSRF Guarding:** Headless screenshot requests are strictly verified against the configured `PRESSAGENT_WP_URL` origin. Attempting to target private IPs or external hosts will be rejected.
4. **Path-Traversal Guards:** Debug log inspection is locked to the content directory via `realpath` validation. Reading arbitrary files (like `wp-config.php` or `/etc/passwd`) is strictly impossible.
5. **Settings Allowlist & Privilege Escalation Protection:** Only explicitly allowed options can be read or written. Critical options like `users_can_register`, `active_plugins`, and `siteurl` require explicit administrator privileges and cannot be hijacked by lower scopes.
6. **Action Guard Rollback Verification:** Rollbacks can only restore allowlisted options and cannot restore the allowlist itself to an insecure state.

---

## 🚀 Quickstart Guide

### 📦 Pre-Packaged Downloads (Direct Release ZIPs)

If you prefer ready-to-use packages without building from source or cloning the repository, download the compiled assets from the [GitHub Releases (v1.0.1)](https://github.com/Noctis-Architect/press-agent/releases/tag/v1.0.1):

| Package | Download Link | Description | How to Install |
| :--- | :--- | :--- | :--- |
| **WordPress Plugin** | [📥 `pressagent-wordpress-plugin.zip`](https://github.com/Noctis-Architect/press-agent/releases/download/v1.0.1/pressagent-wordpress-plugin.zip) | Ready-to-install WordPress plugin zip (includes auto-updater) | In WP Admin: **Plugins → Add New → Upload Plugin** |
| **MCP Server** | [📥 `pressagent-mcp-server.zip`](https://github.com/Noctis-Architect/press-agent/releases/download/v1.0.1/pressagent-mcp-server.zip) | Standalone pre-compiled TypeScript MCP Server with `dist/` | Unpack zip, run `npm install --omit=dev`, and point client to `dist/index.js` |

---

### Step 1: Install the WordPress Plugin

#### Option A: 1-Click Upload (Recommended)
1. Download [`pressagent-wordpress-plugin.zip`](https://github.com/Noctis-Architect/press-agent/releases/download/v1.0.1/pressagent-wordpress-plugin.zip) from the latest release.
2. In WP Admin, navigate to **Plugins → Add New Plugin → Upload Plugin**, choose the zip file, and click **Install Now**.
3. Click **Activate Plugin**.

#### Option B: From Source / Git Clone
1. Clone or copy the `plugin/` directory into your WordPress plugins folder:
   ```bash
   cd /path/to/wordpress/wp-content/plugins
   git clone https://github.com/Noctis-Architect/press-agent.git
   mv press-agent/plugin pressagent
   ```
2. Activate the plugin via WP Admin (**Plugins → Installed Plugins**) or via WP-CLI (`wp plugin activate pressagent`).

#### Configuration:
3. Click on the dedicated **PressAgent** menu item in your WordPress admin sidebar:
   - Click **Quick Setup (کلید پیش‌فرض)** to generate a standard access token with full capabilities.
   - Copy the generated token string.
   - *(Note: PressAgent also includes an **Auto-Updater** tab to check and pull the latest releases directly from GitHub with 1-click).*

### Step 2: Build the MCP Server

1. Navigate to the `mcp-server` directory:
   ```bash
   cd /path/to/press-agent/mcp-server
   npm install
   npm run build
   ```
2. Test the build:
   ```bash
   node dist/index.js
   ```

### Step 3: Configure Your AI Assistant / IDE

Add PressAgent to your MCP client configuration file:

#### 🟣 Claude Desktop
Edit `~/.config/Claude/claude_desktop_config.json` (Linux) or `~/Library/Application Support/Claude/claude_desktop_config.json` (macOS):
```json
{
  "mcpServers": {
    "pressagent": {
      "command": "node",
      "args": ["/absolute/path/to/press-agent/mcp-server/dist/index.js"],
      "env": {
        "PRESSAGENT_WP_URL": "https://your-wordpress-site.com",
        "PRESSAGENT_TOKEN": "your-generated-token-here"
      }
    }
  }
}
```

#### 🌐 Antigravity IDE / CLI
Edit `/home/noctis/.gemini/config/mcp_config.json`:
```json
{
  "mcpServers": {
    "pressagent": {
      "command": "node",
      "args": ["/home/noctis/press-agent/mcp-server/dist/index.js"],
      "env": {
        "PRESSAGENT_WP_URL": "https://your-wordpress-site.com",
        "PRESSAGENT_TOKEN": "your-generated-token-here"
      }
    }
  }
}
```

#### ⚡ Cursor / Windsurf
In `.cursor/mcp.json` or your MCP workspace settings:
```json
{
  "mcpServers": {
    "pressagent": {
      "command": "node",
      "args": ["/absolute/path/to/press-agent/mcp-server/dist/index.js"],
      "env": {
        "PRESSAGENT_WP_URL": "https://your-wordpress-site.com",
        "PRESSAGENT_TOKEN": "your-generated-token-here"
      }
    }
  }
}
```

---

## 🌐 Connecting Local or Remote WordPress Instances

### Scenario A: Remote WordPress Site
If your WordPress site is hosted on a public domain with HTTPS (e.g. `https://example.com`), simply provide the URL in `PRESSAGENT_WP_URL`.

### Scenario B: Local WordPress via SSH Reverse Tunnel
If your WordPress site is running on your local machine (e.g. `http://localhost:8000`) while your AI assistant / Antigravity is running on a remote server:
1. Run a reverse SSH tunnel from your local machine:
   ```bash
   ssh -R 8888:localhost:8000 user@remote-server-ip
   ```
2. Set your environment variable:
   ```bash
   PRESSAGENT_WP_URL="http://localhost:8888"
   ```

### Scenario C: Local WordPress via Cloudflare Tunnel
```bash
npx cloudflared tunnel --url http://localhost:8000
```
Use the resulting public HTTPS URL in `PRESSAGENT_WP_URL`.

---

## 📋 RBAC Scopes Reference

| Scope | Category | Description |
|---|---|---|
| `pages:read` | Content | Read and list WordPress pages and metadata |
| `pages:write` | Content | Create, update status, and delete pages |
| `elementor:read` | Builder | Read Elementor layout JSON trees and widget settings |
| `elementor:write` | Builder | Add sections/containers, update widgets, and rebuild designs |
| `appearance:css` | Design | Manage custom CSS styles and page templates |
| `settings:read` | Settings | Read allowlisted options for core and plugins |
| `settings:write` | Settings | Update allowlisted options (creates Action Guard snapshot) |
| `cache:purge` | Cache | Invalidate and purge cache for WP Rocket, LiteSpeed, and Elementor |
| `debug:read` | Diagnostics | Read and parse recent debug log errors |
| `debug:write` | Diagnostics | Run diagnostic and troubleshooting commands |
| `security:read` | Security | Inspect site health, plugin versions, and configuration status |
| `code:execute` | Developer | God Mode: execute code and scaffold plugins (staging only) |
| `*` or `god_mode` | Full Admin | Unrestricted access across all PressAgent endpoints |

---

## 🧪 Testing & Verification

PressAgent includes comprehensive syntax checks and unit tests:

```bash
# Verify PHP plugin syntax
find plugin/ -name "*.php" -exec php -l {} \;

# Build TypeScript MCP server
cd mcp-server && npm run build
```

---

## 🛣️ Roadmap

- [x] **Phase 1: Core Foundation** — Cryptographic RBAC, `determine_current_user` WordPress auth bridge, Action Guard™ snapshots & rollback, Settings allowlist.
- [x] **Phase 2: Elementor Native Engine** — Native widget generation (no raw HTML), container hierarchy, Elementor internal save path, CSS cache purging.
- [x] **Phase 3: Visual Inspection** — Headless browser screenshotting, multi-viewport previews (Desktop/Tablet/Mobile), SSRF protection.
- [x] **Phase 4: Diagnostics & Self-Healing** — Regex-based debug log parsing, log traversal guards, AI diagnostic remediation.
- [ ] **Phase 5: WooCommerce Deep Integration** — Direct manipulation of WooCommerce product tabs, custom attributes, variation pricing, and checkout builders.
- [ ] **Phase 6: Gutenberg Block Support** — Native block template generation and block pattern transformation alongside Elementor.

---

## 🤝 Contributing

Contributions, issues, and feature requests are welcome!
Feel free to check the [issues page](https://github.com/Noctis-Architect/press-agent/issues).

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'feat: add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

---

## 📄 License

This project is licensed under the **MIT License** — see the [LICENSE](LICENSE) file for details.

<p align="center">
  Built with ❤️ for the WordPress and AI developer communities.
</p>

---

<a name="-پرسایجنت-pressagent"></a>
# پرس‌ایجنت (PressAgent) 🚀

### پل ارتباطی هوشمند و امن برای مدیریت، طراحی و عملیات وردپرس با هوش مصنوعی (MCP)

<p align="center">
  <a href="#pressagent-"><b>English</b></a> • <a href="#-پرسایجنت-pressagent"><b>فارسی</b></a>
</p>

> **PressAgent** دستیارهای کدنویسی و ایجنت‌های هوش مصنوعی (**Claude Code**، **Antigravity**، **Cursor**، **Windsurf** و **Cline**) را مستقیماً، با ساختاری بومی و تحت استانداردهای امنیتی بالا به سایت‌های زنده وردپرسی متصل می‌کند.
>
> بر خلاف افزونه‌ها و MCPهای ساده که صرفاً کدهای خام HTML غیرقابل ویرایش را به دیتابیس تزریق می‌کنند، **PressAgent** یک بستر مهندسی‌شده برای عملیات خودکار و طراحی استاندارد است. این ابزار صفحات را با **کانتینرهای فلکس‌باکس و ابزارک‌های بومی المنتور** می‌سازد، به کمک **مرورگر بدون سر (Headless Browser)** اسکرین‌شات‌های تست ظاهر صفحه می‌گیرد، خطاهای `debug.log` را استخراج و آنالیز می‌کند، دارای سیستم ثبت اسنپ‌شات و بازگردانی سریع با **Action Guard™** است و از کنترل دسترسی مبتنی بر نقش (RBAC) با توکن‌های هش‌شده بهره می‌برد.

---

## 🌟 قابلیت‌های کلیدی

### 🎨 ۱. موتور طراحی بومی المنتور (Native Elementor Engine)
- **سازگاری ۱۰۰٪ با ویرایشگر دیداری (Drag & Drop):** پرس‌ایجنت هرگز کد خام HTML یا شورت‌کدهای غیرقابل ویرایش درج نمی‌کند؛ بلکه تمامی المان‌ها را در قالب ابزارک‌های واقعی المنتور (`heading`، `text-editor`، `button`، `image`، `icon-box`، `counter` و...) درون کانتینرهای مدرن Flexbox ایجاد می‌کند.
- **امکان ویرایش کامل در پیشخوان وردپرس:** پس از ساخت صفحه توسط هوش مصنوعی، مدیر سایت یا کاربر می‌تواند وارد ویرایشگر المنتور شده و متن‌ها، رنگ‌ها، پدینگ‌ها و استایل‌ها را به‌صورت بصری تغییر دهد بدون اینکه ساختار صفحه دچار به‌هم‌ریختگی شود.
- **اتصال عمیق به هسته المنتور:** ذخیره‌سازی مستقیماً از طریق متد داخلی `$document->save()` در هسته المنتور انجام شده و فایل‌های CSS کش‌شده (`_elementor_css`) و کش تمپلیت‌ها به‌صورت خودکار بازتولید می‌شوند.

### 📸 ۲. موتور تست ظاهر و ثبت اسکرین‌شات (Visual QA & Headless Screenshots)
- **چشم‌های هوش مصنوعی برای ویرایش پیکسل به پیکسل:** ابزار داخلی ثبت اسکرین‌شات (`pressagent_capture_screenshot`) به ایجنت این امکان را می‌دهد که خروجی بصری صفحه را در ابعاد دسکتاپ، تبلت و موبایل بررسی کرده و تا رسیدن به طراحی بی‌نقص خطاها را اصلاح کند.
- **مقاوم در برابر حملات SSRF:** آدرس‌های ارسالی دقیقاً با دامنه مبدأ سایت وردپرس مطابقت داده می‌شوند تا از سوءاستفاده‌های امنیتی یا پویش شبکه‌های داخلی جلوگیری شود.
- **تنظیمات پیشرفته:** پشتیبانی از عرض و ارتفاع سفارشی، اسکرین‌شات تمام‌صفحه (Full-page)، تاخیر زمانی (برای لود اسکریپت‌ها) و انتخاب‌گر اختصاصی CSS.

### 🛡️ ۳. محافظ هوشمند عملیات (Action Guard™: اسنپ‌شات و بازگردانی آنی)
- **عملیات بدون ریسک و دلهره:** پیش از هرگونه تغییر اساسی روی ساختار المنتور، ویرایش آپشن‌های وردپرس یا حذف برگه، یک نسخه کامل و رمزنگاری‌شده در جدول دیتابیس `wp_pressagent_snapshots` ذخیره می‌شود.
- **بازگردانی سریع تنها با ۱ کلیک:** در صورت بروز تداخل ظاهری یا اشتباه هوش مصنوعی، وضعیت برگه یا تنظیمات بلافاصله از داخل پنل مدیریت وردپرس (`تنظیمات ← PressAgent`) یا از طریق API به حالت قبل بازمی‌گردد.
- **پروتکل ایمنی دومرحله‌ای:**
  - **عملیات برگشت‌پذیر (Reversible):** به صورت خودکار با ثبت اسنپ‌شات قبلی اجرا می‌شوند.
  - **عملیات غیرقابل برگشت (Non-Reversible):** نیازمند توکن تاییدیه صریح از سمت کاربر انسانی هستند.
- **جلوگیری از دور زدن امنیت:** سیستم بازگردانی اجازه سوءاستفاده یا تغییر لیست سفید امنیتی از طریق اسنپ‌شات‌ها را نمی‌دهد.

### 🔐 ۴. پایپ‌لاین احراز هویت سازمانی و سطوح دسترسی (RBAC)
- **امنیت بر پایه رمزنگاری توکن‌ها:** کلیدهای دسترسی دارای انتروپی بالا (رمزهای ۴۸ کاراکتری) بوده و تنها هش‌های امن آن‌ها با الگوریتم phpass وردپرس ذخیره می‌شوند؛ توکن‌های خام هرگز در دیتابیس باقی نمی‌مانند.
- **اتصال به هوک استاندارد `determine_current_user` هسته وردپرس:** درخواست‌های REST API با توکن معتبر، به کانتکست حساب کاربری ادمین متصل می‌شوند تا دسترسی‌های پایه‌ای وردپرس (`edit_posts`، `edit_pages`، `manage_options`) به‌درستی اعتبارسنجی شوند.
- **محدودسازی دسترسی (Least Privilege Scopes):** امکان صدور کلیدهای اختصاصی با دسترسی‌های جزئی و مشخص (مانند فقط خواندن صفحات یا فقط تخلیه کش).
- **کش حافظه‌ای سریع:** اعتبارسنجی در کسری از میلی‌ثانیه به کمک کش استاتیک در چرخه هر درخواست PHP انجام می‌پذیرد.

### ⚙️ ۵. مدیریت امن تنظیمات و کش افزونه‌ها
- **لیست سفید سخت‌گیرانه تنظیمات:** دسترسی به جدول `wp_options` فقط محدود به کلیدهای تعریف‌شده در لیست سفید (`pressagent_settings_allowlist`) با قابلیت استفاده از الگوهای وایلدکارت (`wordfence*` و `litespeed.conf.*`) است.
- **پشتیبانی پیش‌فرض از افزونه‌های محبوب:** سازگاری توکار با پلاگین‌های سرعت و امنیت نظیر **WP Rocket**، **LiteSpeed Cache** و **Wordfence**.
- **سد دفاعی در برابر ارتقای غیرمجاز دسترسی:** آپشن‌های حساس هسته وردپرس مانند `users_can_register`، `default_role`، `active_plugins` و `siteurl` به طور ویژه در برابر تغییرات محافظت می‌شوند.
- **تخلیه خودکار کش:** با تغییر هر برگه یا تنظیم، کش افزونه‌های لایت‌اسپید و راکت به طور خودکار پاکسازی می‌شوند.

### 🔍 ۶. پارسر هوشمند لاگ‌های خطا و عیب‌یابی (Debug Log Parser)
- **استخراج ساختاریافته خطاها:** فایل `debug.log` وردپرس پردازش شده و زمان، سطح خطا (`error`، `warning`، `notice`)، فایل مربوطه و شماره سطر به فرمت خوانای JSON استخراج می‌شود.
- **مسدودسازی حملات پیمایش مسیر (Path-Traversal):** دسترسی به فایل‌ها دقیقاً در محدوده `WP_CONTENT_DIR` با تابع `realpath` محدود می‌شود و خواندن سایر فایل‌های حساس سیستم غیرممکن است.
- **راهنمای عیب‌یابی مبتنی بر AI:** ابزار `pressagent_diagnose_error` متن خطا را بررسی کرده و گام‌های عملی برای رفع تداخل را پیشنهاد می‌دهد.

### ⚡ ۷. ساخت و توسعه پلاگین (God Mode / Scaffolding)
- **ساخت سریع افزونه‌ها:** ایجنت هوش مصنوعی می‌تواند ساختار پلاگین‌های جدید وردپرسی را اسکلت‌بندی و تولید کند (`pressagent_create_plugin`).
- **محدود به محیط استیجینگ:** این ابزار تحت Scope اختصاصی `code:execute` قرار داشته و در محیط‌های پروداکشن به طور پیش‌فرض غیرفعال است.

---

## 🏗️ معماری ارتباطی

```
+-------------------------------------------------------------------------+
|                      دستیار هوش مصنوعی / کلاینت MCP                     |
|       [Claude Code]  [Antigravity IDE]  [Cursor]  [Windsurf]  [Cline]   |
|                                     |                                   |
|                                     | (پروتکل Stdio / JSON-RPC)         |
|                                     v                                   |
|                   [سرور MCP پرِس‌ایجنت (Node.js/TS)]                   |
+-------------------------------------------------------------------------+
                                      |
                                      | (درخواست HTTPS + هدر توکن Bearer)
                                      v
+-------------------------------------------------------------------------+
|                            سایت مقصد وردپرسی                            |
|                        [افزونه PressAgent (PHP)]                        |
|                                     |                                   |
|       +-----------------------------+-----------------------------+     |
|       |                             |                             |     |
|       v                             v                             v     |
| [ماژول احراز هویت Auth]     [کنترلر REST API]             [گارد Action]  |
| - هوک determine_current_user - /pressagent/v1/pages       - اسنپ‌شات DB |
| - اعتبارسنجی Scopeها         - /pressagent/v1/elementor   - بازگردانی   |
| - هش‌های امن phpass          - /pressagent/v1/settings      با ۱ کلیک   |
| - کش حافظه‌ای درخواست        - /pressagent/v1/debug-log   - توکن‌های    |
|                             - /pressagent/v1/cache         تاییدیه      |
|                                     |                                   |
|       +-----------------------------+-----------------------------+     |
|       |                             |                             |     |
|       v                             v                             v     |
| [آداپتور المنتور]           [مدیریت تنظیمات عمومی]        [پارسر لاگ خطا] |
| - درخت کانتینرهای فلکس‌باکس  - اعتبارسنجی لیست سفید        - پردازش JSON |
| - ساخت ویجت‌های بومی        - کش‌های WP Rocket و لایت‌اسپید - سد امنیت  |
| - متد داخلی save()          - مهار Privilege Escalation    مسیر لاگ     |
| - تخلیه کش CSS المنتور       - تخلیه خودکار کش             - راهنمای رفع|
+-------------------------------------------------------------------------+
```

---

## 🧰 فهرست کامل ابزارهای MCP

| دسته‌بندی | نام ابزار | توضیحات عملکرد | سطح دسترسی مورد نیاز |
|---|---|---|---|
| **برگه‌ها و محتوا** | `pressagent_list_pages` | دریافت لیست برگه‌ها به همراه شناسه، نامک، عنوان، وضعیت و وضعیت المنتور | `pages:read` |
| | `pressagent_create_page` | ایجاد برگه جدید با قابلیت فعال‌سازی مستقیم حالت ویرایشگر المنتور | `pages:write` |
| | `pressagent_delete_page` | حذف ایمن برگه با دریافت توکن تاییدیه از Action Guard | `pages:write` |
| **طراحی با المنتور** | `pressagent_get_elementor_layout` | دریافت درخت ساختار سلسله‌مراتبی JSON المان‌ها و ابزارک‌های برگه | `elementor:read` |
| | `pressagent_update_widget` | ویرایش محتوا و تنظیمات استایل یک ابزارک خاص بر اساس شناسه آن | `elementor:write` |
| | `pressagent_add_elementor_section` | افزودن بخش/کانتینر جدید یا بازسازی کامل طراحی با ویجت‌های بومی | `elementor:write` |
| **بازرسی بصری** | `pressagent_capture_screenshot` | ثبت اسکرین‌شات از دید کاربر با مرورگر بدون سر (دسکتاپ/تبلت/موبایل) | `pages:read` |
| **تنظیمات و کش** | `pressagent_read_option` | خواندن مقادیر آپشن‌های مجاز موجود در لیست سفید | `settings:read` |
| | `pressagent_write_option` | ذخیره مقدار جدید در آپشن‌های مجاز با ثبت خودکار اسنپ‌شات پشتیبان | `settings:write` |
| | `pressagent_purge_cache` | تخلیه کامل کش‌های سایت (راکت، لایت‌اسپید، فایل‌های CSS المنتور) | `settings:write` |
| | `pressagent_get_cache_status` | بررسی وضعیت سیستم‌های کش فعال روی وردپرس و دایرکتوری‌های آن‌ها | `settings:read` |
| **لاگ و عیب‌یابی** | `pressagent_read_debug_log` | خواندن لاگ‌های خطای وردپرس با فیلتر سطح خطا (Error/Warning/Notice) | `debug:read` |
| | `pressagent_diagnose_error` | تحلیل خطا و ارائه راهکار گام‌به‌گام برای حل مشکل تداخل یا کرش | `debug:read` |
| **امنیت و وضعیت** | `pressagent_security_summary` | خلاصه وضعیت امنیت سایت شامل نسخه PHP، نسخه وردپرس و دسترسی فایل‌ها | `security:read` |
| **توسعه و پلاگین** | `pressagent_create_plugin` | اسکلت‌بندی و ایجاد پلاگین‌های جدید سفارشی (مخصوص محیط استیجینگ) | `code:execute` |

---

## 🔒 لایه‌های امنیتی پیاده‌سازی شده

۱. **عدم ذخیره توکن خام در دیتابیس:** توکن‌ها بلافاصله پس از ایجاد با ابزار امنیتی phpass هسته وردپرس هش شده و در صورت نفوذ به دیتابیس، امکان بازیابی رمزها وجود ندارد.  
۲. **اتصال اصولی به نشست کاربری وردپرس:** استفاده از هوک استاندارد `determine_current_user` باعث می‌شود تمام دسترسی‌های داخلی وردپرس بدون نیاز به کدهای ناامن اعمال شوند.  
۳. **حفاظت کامل در برابر SSRF:** ابزار اسکرین‌شات تنها مجاز به بازدید از دامنه‌ای است که در تنظیمات `PRESSAGENT_WP_URL` ثبت شده و از بررسی شبکه داخلی جلوگیری می‌کند.  
۴. **سد دفاعی در برابر Path-Traversal:** فایل‌های لاگ با تابع `realpath` اعتبارسنجی شده و دسترسی به فایل‌های حیاتی نظیر `wp-config.php` مسدود است.  
۵. **لیست سفید سخت‌گیرانه برای گزینه‌ها:** ویرایش متغیرهای هسته وردپرس تنها در صورت داشتن دسترسی مدیریت امکان‌پذیر است.  
۶. **امنیت در بازگردانی اسنپ‌شات‌ها:** بازگردانی اسنپ‌شات نمی‌تواند لیست سفید امنیتی را دستکاری یا دور بزند.

---

## 🚀 راهنمای راه‌اندازی سریع

### 📦 دانلود بسته‌های آماده (فایل‌های زیپ ریلیز)

اگر نمی‌خواهید سورس پروژه را با Git کلون کنید یا سرور MCP را خودتان بیلد نمایید، بسته‌های آماده و کامپایل‌شده را مستقیماً از [صفحه ریلیزهای گیت‌هاب (v1.0.1)](https://github.com/Noctis-Architect/press-agent/releases/tag/v1.0.1) دانلود کنید:

| بسته | لینک دانلود مستقیم | توضیحات | روش راه‌اندازی |
| :--- | :--- | :--- | :--- |
| **افزونه وردپرس** | [📥 `pressagent-wordpress-plugin.zip`](https://github.com/Noctis-Architect/press-agent/releases/download/v1.0.1/pressagent-wordpress-plugin.zip) | فایل زیپ استاندارد افزونه آماده آپلود (مجهز به سیستم خودکار آپدیت از گیت‌هاب) | در پیشخوان وردپرس: **افزونه‌ها ← افزودن افزونه تازه ← بارگذاری افزونه** |
| **سرور MCP** | [📥 `pressagent-mcp-server.zip`](https://github.com/Noctis-Architect/press-agent/releases/download/v1.0.1/pressagent-mcp-server.zip) | بسته سرور پیش‌کامپایل‌شده تایپ‌اسکریپت همراه با پوشه `dist/` | آنزیپ کنید، دستور `npm install --omit=dev` را بزنید و در هوش مصنوعی به `dist/index.js` اشاره کنید |

---

### گام اول: نصب افزونه روی وردپرس

#### روش اول: بارگذاری مستقیم فایل زیپ (پیشنهادی و آسان)
۱. فایل [`pressagent-wordpress-plugin.zip`](https://github.com/Noctis-Architect/press-agent/releases/download/v1.0.1/pressagent-wordpress-plugin.zip) را دانلود کنید.
۲. در پیشخوان وردپرس به مسیر **افزونه‌ها ← افزودن افزونه تازه ← بارگذاری افزونه** رفته، فایل را انتخاب و دکمه **نصب** را بزنید.
۳. روی دکمه **فعال‌سازی افزونه** کلیک کنید.

#### روش دوم: از طریق Git و سورس‌کد
۱. فایل‌های دایرکتوری `plugin/` را درون پوشه افزونه‌های وردپرس کلون یا کپی کنید:
```bash
cd /path/to/wordpress/wp-content/plugins
git clone https://github.com/Noctis-Architect/press-agent.git
mv press-agent/plugin pressagent
```
۲. افزونه را فعال کنید:
- از طریق پیشخوان وردپرس بخش **افزونه‌ها**، گزینه **PressAgent** را فعال کنید.
- یا با دستور WP-CLI: `wp plugin activate pressagent`

#### پیکربندی اولیه:
۳. وارد منوی اختصاصی **PressAgent** در سایدبار اصلی پیشخوان وردپرس شوید:
- روی دکمه **کلید پیش‌فرض (Quick Setup)** کلیک کنید تا یک کلید استاندارد با تمامی دسترسی‌های لازم صادر شود.
- توکن تولیدشده را کپی کنید.
- *(نکته: افزونه دارای تب **بروزرسانی (Updater)** اختصاصی با هشدار بج آپدیت است که در صورت انتشار نسخه جدید در گیت‌هاب به شما هشدار داده و با یک کلیک آپدیت می‌کند).*

### گام دوم: بیلد و کامپایل سرور MCP

۱. وارد پوشه `mcp-server` شوید:
```bash
cd /path/to/press-agent/mcp-server
npm install
npm run build
```
۲. فایل اجرایی کامپایل‌شده در مسیر `dist/index.js` آماده خواهد بود.

### گام سوم: تنظیم در ادیتور هوش مصنوعی

#### 🟣 Claude Desktop
فایل تنظیمات `claude_desktop_config.json` را باز کنید:
```json
{
  "mcpServers": {
    "pressagent": {
      "command": "node",
      "args": ["/مسیر_کامل/press-agent/mcp-server/dist/index.js"],
      "env": {
        "PRESSAGENT_WP_URL": "https://your-wordpress-site.com",
        "PRESSAGENT_TOKEN": "توکن_صادر_شده"
      }
    }
  }
}
```

#### 🌐 Antigravity IDE / CLI
فایل `/home/noctis/.gemini/config/mcp_config.json` را ویرایش نمایید:
```json
{
  "mcpServers": {
    "pressagent": {
      "command": "node",
      "args": ["/home/noctis/press-agent/mcp-server/dist/index.js"],
      "env": {
        "PRESSAGENT_WP_URL": "https://your-wordpress-site.com",
        "PRESSAGENT_TOKEN": "توکن_صادر_شده"
      }
    }
  }
}
```

#### ⚡ Cursor / Windsurf
در تنظیمات MCP ادیتور (فایل `.cursor/mcp.json`):
```json
{
  "mcpServers": {
    "pressagent": {
      "command": "node",
      "args": ["/مسیر_کامل/press-agent/mcp-server/dist/index.js"],
      "env": {
        "PRESSAGENT_WP_URL": "https://your-wordpress-site.com",
        "PRESSAGENT_TOKEN": "توکن_صادر_شده"
      }
    }
  }
}
```

---

## 🌐 روش‌های اتصال وردپرس محلی و سرور ابری

- **سایت آنلاین:** در صورتی که وردپرس شما روی یک هاست یا سرور با آدرس اینترنتی مشخص بالا باشد، کافیست آدرس آن را در `PRESSAGENT_WP_URL` قرار دهید.
- **وردپرس لوکال از طریق تونل معکوس SSH:** اگر وردپرس روی سیستم شخصی شما (مثلاً پورت ۸۰۰۰) و دستیار هوش مصنوعی روی سرور ابری است:
  ```bash
  ssh -R 8888:localhost:8000 user@remote-server-ip
  ```
  و سپس مقدار `PRESSAGENT_WP_URL` را برابر `http://localhost:8888` قرار دهید.
- **استفاده از Cloudflare Tunnel:**
  ```bash
  npx cloudflared tunnel --url http://localhost:8000
  ```

---

## 📋 جدول دسترسی‌ها (RBAC Scopes)

| دسترسی (Scope) | دسته‌بندی | توضیحات |
|---|---|---|
| `pages:read` | محتوا | مشاهده و خواندن لیست برگه‌ها و ویژگی‌های آن‌ها |
| `pages:write` | محتوا | ساخت برگه جدید، ویرایش وضعیت و حذف برگه‌ها |
| `elementor:read` | طراحی | دریافت ساختار JSON کانتینرها و تنظیمات ابزارک‌های المنتور |
| `elementor:write` | طراحی | افزودن سکشن، تغییر طراحی ابزارک‌ها و بازسازی صفحات با ویجت‌های بومی |
| `appearance:css` | قالب | مدیریت قالب برگه و اعمال استایل‌های CSS اختصاصی |
| `settings:read` | تنظیمات | خواندن آپشن‌های مجاز هسته و افزونه‌ها از دیتابیس |
| `settings:write` | تنظیمات | ذخیره مقادیر جدید در آپشن‌های مجاز با ایجاد اسنپ‌شات خودکار |
| `cache:purge` | بهینه‌سازی | پاکسازی و تخلیه کش راکت، لایت‌اسپید و کش المنتور |
| `debug:read` | عیب‌یابی | خواندن لاگ‌های خطای اخیر سیستم به همراه جزییات |
| `debug:write` | عیب‌یابی | اجرای دستورات تشخیصی و عیب‌یابی |
| `security:read` | امنیت | گزارش وضعیت سلامت، افزونه‌های فعال و مجوز فایل‌ها |
| `code:execute` | گاد مود | ساخت و اسکلت‌بندی افزونه‌های جدید (مخصوص محیط استیجینگ) |
| `*` یا `god_mode` | دسترسی کل | دسترسی نامحدود به تمامی اندپوینت‌های سیستم |

---

## 🛣️ نقشه راه توسعه (Roadmap)

- [x] **فاز ۱: هسته پایه‌ای** — کنترل دسترسی رمزنگاری‌شده، هوک ورود استاندارد وردپرس، اسنپ‌شات و بازگردانی Action Guard، لیست سفید تنظیمات.
- [x] **فاز ۲: موتور بومی المنتور** — ساخت ویجت‌های کامپوننتی استاندارد (بدون کد خام HTML)، کانتینرهای فلکس‌باکس، ذخیره‌سازی از طریق متد داخلی المنتور، پاکسازی خودکار کش‌های CSS.
- [x] **فاز ۳: سیستم تست و بازرسی بصری** — مرورگر بدون سر برای اسکرین‌شات از ظاهر سایت، بررسی در ابعاد دسکتاپ/تبلت/موبایل، فیلتر ایمنی در برابر SSRF.
- [x] **فاز ۴: خودترمیمی و آنالیز خطاها** — استخراج ساختاریافته لاگ‌های PHP، مهار دسترسی غیرمجاز به فایل‌ها، راهکارهای پیشنهادی هوش مصنوعی.
- [ ] **فاز ۵: یکپارچه‌سازی ووکامرس** — امکان طراحی و مدیریت تب‌های محصول، ویژگی‌های پیشرفته، قیمت‌گذاری متغیر و سفارشی‌سازی صفحات تسویه‌حساب با هوش مصنوعی.
- [ ] **فاز ۶: پشتیبانی از بلوک‌های گوتنبرگ** — ساخت تمپلیت‌ها و الگوهای بومی بلوک‌های وردپرس در کنار المنتور.

---

## 🤝 مشارکت در پروژه

از هرگونه مشارکت، ارسال Issue و پیشنهادات بهبود صمیمانه استقبال می‌کنیم!
صفحه [Issues در گیت‌هاب](https://github.com/Noctis-Architect/press-agent/issues) آماده دریافت بازخوردهای شماست.

---

## 📄 مجوز (License)

این پروژه تحت مجوز متن‌باز **MIT License** منتشر شده است.

