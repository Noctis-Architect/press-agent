#!/usr/bin/env node
/**
 * Measure the fixed token cost of the MCP tool schema surface.
 * Usage: node scripts/token-report.mjs [toolset,toolset,...]
 */
import { registerPagesTools } from "../mcp-server/dist/tools/pages.js";
import { registerElementorTools } from "../mcp-server/dist/tools/elementor.js";
import { registerScreenshotTools } from "../mcp-server/dist/tools/screenshot.js";
import { registerSettingsTools } from "../mcp-server/dist/tools/settings.js";
import { registerDebugLogTools } from "../mcp-server/dist/tools/debug-log.js";
import { registerSecurityTools } from "../mcp-server/dist/tools/security.js";
import { registerDevTools } from "../mcp-server/dist/tools/dev.js";
import { registerThemeTools } from "../mcp-server/dist/tools/theme.js";
import { registerPluginTools } from "../mcp-server/dist/tools/plugins.js";
import { registerPluginConfigTools } from "../mcp-server/dist/tools/plugin-config.js";
import { registerCommerceTools } from "../mcp-server/dist/tools/commerce.js";
import { registerFormsTools } from "../mcp-server/dist/tools/forms.js";
import { registerSeoTools } from "../mcp-server/dist/tools/seo.js";
import { registerPerformanceTools } from "../mcp-server/dist/tools/performance.js";
import { registerWpSettingsTools } from "../mcp-server/dist/tools/wp-settings.js";

const registry = {
  pages: registerPagesTools,
  elementor: registerElementorTools,
  screenshot: registerScreenshotTools,
  settings: registerSettingsTools,
  debug: registerDebugLogTools,
  security: registerSecurityTools,
  dev: registerDevTools,
  theme: registerThemeTools,
  plugins: registerPluginTools,
  "plugin-config": registerPluginConfigTools,
  commerce: registerCommerceTools,
  forms: registerFormsTools,
  seo: registerSeoTools,
  performance: registerPerformanceTools,
  "wp-settings": registerWpSettingsTools
};

const requested = process.argv[2]
  ? process.argv[2].split(",").map((s) => s.trim()).filter(Boolean)
  : ["all"];

// Mirror the server: plugin-config and SEO are part of the "plugins" profile,
// and SEO can also be loaded alone.
if (requested.includes("plugins")) {
  if (!requested.includes("plugin-config")) requested.push("plugin-config");
  if (!requested.includes("seo")) requested.push("seo");
}
if (requested.includes("settings") && !requested.includes("wp-settings")) {
  requested.push("wp-settings");
}

const tools = [];
const handlers = new Map();
const client = new Proxy({}, { get: () => () => Promise.resolve({}) });

for (const [name, register] of Object.entries(registry)) {
  if (requested.includes("all") || requested.includes(name)) {
    register(tools, handlers, client);
  }
}

const approxTokens = (text) => Math.ceil(text.length / 4);
const total = JSON.stringify(tools);
const rows = tools.map((tool) => ({
  name: tool.name,
  chars: JSON.stringify(tool).length,
  tokens: approxTokens(JSON.stringify(tool))
}));

rows.sort((a, b) => b.tokens - a.tokens);

console.log(`toolsets: ${requested.join(",")} | tools: ${tools.length}`);
console.log(`fixed schema: ${total.length} chars (~${approxTokens(total)} tokens)`);
console.log("");
for (const row of rows) {
  console.log(`${String(row.tokens).padStart(6)} tok  ${String(row.chars).padStart(7)} chars  ${row.name}`);
}
