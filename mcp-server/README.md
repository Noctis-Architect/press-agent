# PressAgent MCP Server

Official Model Context Protocol (MCP) server for PressAgent WordPress Autonomous AI Ops.

## 🚀 Quickstart

1. **Install dependencies:**
   ```bash
   npm install
   ```

2. **Build (optional, pre-built in `dist/`):**
   ```bash
   npm run build
   ```

3. **Configure your AI client (Cursor, Claude Desktop, Antigravity, Windsurf):**

```json
{
  "mcpServers": {
    "pressagent": {
      "command": "node",
      "args": ["/absolute/path/to/pressagent-mcp-server/dist/index.js"],
      "env": {
        "PRESSAGENT_WP_URL": "https://your-wordpress-site.com",
        "PRESSAGENT_TOKEN": "your_pressagent_secret_token_here"
      }
    }
  }
}
```

## 🛠️ Features
- Manage WordPress Pages & Elementor Containers/Widgets
- Cache Purge & Diagnostic Parsing
- Developer God Mode (plugin scaffolding & execution)
- Action Guard automated rollback snapshots
