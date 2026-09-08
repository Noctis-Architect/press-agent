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
      version: "1.0.0"
    },
    {
      capabilities: {
        tools: {}
      }
    }
  );

  const tools: any[] = [];
  const handlers = new Map<string, (args: any) => Promise<any>>();

  // Register all tools
  registerPagesTools(tools, handlers, client);
  registerElementorTools(tools, handlers, client);
  registerScreenshotTools(tools, handlers, client);
  registerSettingsTools(tools, handlers, client);
  registerDebugLogTools(tools, handlers, client);
  registerSecurityTools(tools, handlers, client);
  registerDevTools(tools, handlers, client);

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
        content: [{ type: "text", text: JSON.stringify(result, null, 2) }]
      };
    } catch (error: any) {
      return {
        content: [{ type: "text", text: JSON.stringify({ error: error.message }, null, 2) }],
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
