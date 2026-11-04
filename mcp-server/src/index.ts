#!/usr/bin/env node
import { Server } from "@modelcontextprotocol/sdk/server/index.js";
import { StdioServerTransport } from "@modelcontextprotocol/sdk/server/stdio.js";
import { ListToolsRequestSchema, CallToolRequestSchema } from "@modelcontextprotocol/sdk/types.js";
import { WPClient } from "./wp-client.js";
import { registerPagesTools } from "./tools/pages.js";
import { registerElementorTools } from "./tools/elementor.js";
import { registerSettingsTools } from "./tools/settings.js";
import { registerDebugLogTools } from "./tools/debug-log.js";
import { registerSecurityTools } from "./tools/security.js";
import { registerDevTools } from "./tools/dev.js";
import { registerScreenshotTools } from "./tools/screenshot.js";
import { registerThemeTools } from "./tools/theme.js";
import { registerPluginTools } from "./tools/plugins.js";
import { registerPluginConfigTools } from "./tools/plugin-config.js";
import { registerCommerceTools } from "./tools/commerce.js";
import { registerFormsTools } from "./tools/forms.js";
import { registerSeoTools } from "./tools/seo.js";
import { registerPerformanceTools } from "./tools/performance.js";
import { registerWpSettingsTools } from "./tools/wp-settings.js";
import { registerPrompts } from "./prompts/index.js";

async function main() {
  const wpUrl = process.env.PRESSAGENT_WP_URL;
  const wpToken = process.env.PRESSAGENT_TOKEN;

  if (!wpUrl || !wpToken) {
    console.error("Error: PRESSAGENT_WP_URL and PRESSAGENT_TOKEN environment variables must be set.");
    process.exit(1);
  }

  const client = new WPClient(wpUrl, wpToken);
  
  const server = new Server(
    {
      name: "pressagent",
      version: "1.2.0"
    },
    {
      capabilities: {
        tools: {},
        prompts: {}
      }
    }
  );

  const tools: any[] = [];
  const handlers = new Map<string, (args: any) => Promise<any>>();

  // Optional tool profiles keep the fixed tool schema small for token-sensitive clients.
  // Example: PRESSAGENT_TOOLSETS=pages,elementor,screenshot
  const enabled = (process.env.PRESSAGENT_TOOLSETS || "all")
    .split(",")
    .map((s) => s.trim().toLowerCase())
    .filter(Boolean);
  const enabledAll = enabled.includes("all");
  const toolset = (name: string) => enabledAll || enabled.includes(name);

  if (toolset("pages")) registerPagesTools(tools, handlers, client);
  if (toolset("elementor")) registerElementorTools(tools, handlers, client);
  if (toolset("screenshot")) registerScreenshotTools(tools, handlers, client);
  if (toolset("theme")) registerThemeTools(tools, handlers, client);
  if (toolset("plugins")) registerPluginTools(tools, handlers, client);
  if (toolset("plugins") || toolset("plugin-config")) registerPluginConfigTools(tools, handlers, client);
  if (toolset("commerce")) registerCommerceTools(tools, handlers, client);
  if (toolset("forms")) registerFormsTools(tools, handlers, client);
  if (toolset("plugins") || toolset("seo")) registerSeoTools(tools, handlers, client);
  if (toolset("performance")) registerPerformanceTools(tools, handlers, client);
  if (toolset("settings")) registerWpSettingsTools(tools, handlers, client);
  if (toolset("settings")) registerSettingsTools(tools, handlers, client);
  if (toolset("debug")) registerDebugLogTools(tools, handlers, client);
  if (toolset("security")) registerSecurityTools(tools, handlers, client);
  if (toolset("dev")) registerDevTools(tools, handlers, client);

  // Register MCP Prompts
  registerPrompts(server);

  server.setRequestHandler(ListToolsRequestSchema, async () => ({
    tools: tools
  }));

  server.setRequestHandler(CallToolRequestSchema, async (request) => {
    const { name, arguments: args } = request.params;
    const handler = handlers.get(name);
    
    if (!handler) {
      throw new Error(`Tool ${name} not found`);
    }

    try {
      const result = await handler(args);
      return {
        content: [{ type: "text", text: JSON.stringify(result) }]
      };
    } catch (error: any) {
      return {
        content: [{ type: "text", text: JSON.stringify({ error: error.message }) }],
        isError: true
      };
    }
  });

  const transport = new StdioServerTransport();
  await server.connect(transport);
  
  console.error("PressAgent MCP server running");

  process.on("SIGINT", async () => {
    await server.close();
    process.exit(0);
  });
}

main().catch((error) => {
  console.error("Fatal error:", error);
  process.exit(1);
});
