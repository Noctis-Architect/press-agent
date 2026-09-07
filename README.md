# PressAgent 🚀

### The Autonomous AI Ops Bridge for WordPress

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![Version](https://img.shields.io/badge/version-1.0.0-green.svg)]()
[![WordPress](https://img.shields.io/badge/WordPress-5.8%2B-blue.svg)]()
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)]()

> **PressAgent is NOT just another WordPress-to-MCP connector.** It's a secure AI Ops layer that reads debug logs, coordinates across plugins (Elementor, WP Rocket, Wordfence, and more), and fixes issues — all with tiered access control.

---

## Why PressAgent?

Several WordPress MCP projects already exist (`elementor-mcp`, `mcp-api-for-elementor`, `mcp-wordpress`, `elementify-mcp`). Here's what makes PressAgent different:

| Feature | Others | PressAgent |
|---|---|---|
| **RBAC** | Single token, full access | Scoped tokens as core design |
| **Action Safety** | No safeguards | Reversible/Non-reversible gate with auto-snapshots |
| **Cross-Plugin Ops** | Single plugin focus | Unified interface for Elementor + Rocket + Wordfence + more |
| **Security Audit** | None | Integration with `console.wphb.org` ecosystem |
| **Open Source** | Varies | MIT, no vendor lock-in |

---

## Architecture

```
+-------------------------------------------------------------+
|                     Local Development System                 |
|          [Claude Code / Antigravity / Cursor IDE]            |
|                           | (Stdio / JSON-RPC)              |
|                           v                                  |
|              [PressAgent MCP Server (Node.js)]               |
+-------------------------------------------------------------+
                            |
                            | (HTTPS + Bearer Token + RBAC scope)
                            v
+-------------------------------------------------------------+
|                     WordPress Server / Host                  |
|                  [PressAgent Plugin (PHP)]                   |
|                           |                                  |
|   +------------+----------+----------+------------------+    |
|   |            |                     |                  |    |
|   v            v                     v                  v    |
| [Elementor  [Generic Settings    [Debug Log       [Action    |
|  Adapter]    Reader/Writer]       Parser]          Guard]    |
| - save()    - allow-listed       - Extract &      - snapshot |
|   path       wp_options           analyze          before    |
| - cache-    - Rocket/Wordfence    with LLM         writes   |
|   safe                                                       |
+-------------------------------------------------------------+
```

---

## MCP Tools

### 1. 📄 Pages (Elementor)
- `pressagent_list_pages` — List all WordPress pages
- `pressagent_create_page` — Create a new page
- `pressagent_get_elementor_layout` — Get Elementor layout tree
- `pressagent_update_widget` — Update widget via Elementor's internal save() path
- `pressagent_add_elementor_section` — Add a new section/container

### 2. ⚙️ Generic Settings
- `pressagent_read_option` — Read allow-listed plugin options
- `pressagent_write_option` — Write options with auto-snapshot
- `pressagent_purge_cache` — Purge cache (WP Rocket, LiteSpeed)
- `pressagent_get_cache_status` — Get cache status

### 3. 🔒 Security
- `pressagent_security_summary` — Security audit summary

### 4. 🔍 Diagnostics
- `pressagent_read_debug_log` — Read recent debug log entries
- `pressagent_diagnose_error` — AI-powered error diagnosis

### 5. 🛠️ Developer / God Mode (disabled by default)
- `pressagent_create_plugin` — Create a new plugin (staging only, requires explicit confirmation)

---

## Installation

### WordPress Plugin

1. Clone this repository:
   ```bash
   git clone https://github.com/your-org/pressagent.git
   ```

2. Copy or symlink the `plugin/` directory to your WordPress plugins folder:
   ```bash
   ln -s /path/to/pressagent/plugin /path/to/wordpress/wp-content/plugins/pressagent
   ```

3. Activate the plugin in WordPress admin → Plugins

4. Go to **Settings → PressAgent** to:
   - Generate an API token
   - Configure RBAC scopes
   - Set up the settings allow-list
   - Enable/disable modules

### MCP Server

1. Install dependencies:
   ```bash
   cd mcp-server
   npm install
   ```

2. Build:
   ```bash
   npm run build
   ```

3. Configure environment variables:
   ```bash
   export PRESSAGENT_WP_URL="https://your-wordpress-site.com"
   export PRESSAGENT_TOKEN="your-api-token-from-wp-admin"
   ```

4. Add to your MCP client config (e.g., Claude Code):
   ```json
   {
     "mcpServers": {
       "pressagent": {
         "command": "node",
         "args": ["/path/to/pressagent/mcp-server/dist/index.js"],
         "env": {
           "PRESSAGENT_WP_URL": "https://your-site.com",
           "PRESSAGENT_TOKEN": "your-token"
         }
       }
     }
   }
   ```

---

## Configuration

### Environment Variables

| Variable | Description | Required |
|---|---|---|
| `PRESSAGENT_WP_URL` | WordPress site URL (with https://) | ✅ |
| `PRESSAGENT_TOKEN` | API token generated from WP admin | ✅ |

### RBAC Scopes

| Scope | Description |
|---|---|
| `pages:read` | List and read pages |
| `pages:write` | Create and delete pages |
| `elementor:read` | Read Elementor layouts |
| `elementor:write` | Modify Elementor content |
| `settings:read` | Read plugin settings |
| `settings:write` | Modify plugin settings |
| `debug:read` | Read debug logs |
| `debug:write` | Trigger diagnostics |
| `security:read` | View security reports |
| `code:execute` | God Mode (staging only) |

---

## Roadmap

| Phase | Content | Goal |
|---|---|---|
| **Phase 1** ✅ | Core (Auth/RBAC) + Action Guard + Generic Settings + Debug Log Parser | First installable & testable version |
| **Phase 2** 🔄 | Elementor Adapter (internal save path + cache-safety) | Most complex & most used component |
| **Phase 3** 📋 | Security Audit (WPHB integration) + God Mode (staging-only) | Complete ecosystem |

---

## Action Guard: Reversible vs Non-Reversible

This is a core safety feature unique to PressAgent:

| Action Type | Example | Behavior |
|---|---|---|
| **Reversible** | Cache purge, disable broken plugin, change an option | Executed automatically with pre-snapshot for rollback |
| **Non-Reversible** | Code changes, activate new plugin, security settings | Only **suggested** — requires explicit user confirmation |

---

## Contributing

Contributions are welcome! This is an open-source project designed for collaboration.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

---

## License

This project is licensed under the MIT License — see the [LICENSE](LICENSE) file for details.

---

<p align="center">
  Built with ❤️ for the WordPress community
</p>
