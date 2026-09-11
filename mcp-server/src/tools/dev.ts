import { WPClient } from "../wp-client.js";
import { z } from "zod";

export function registerDevTools(tools: any[], handlers: Map<string, (args: any) => Promise<any>>, client: WPClient) {
  // 1. Create Plugin
  tools.push({
    name: "pressagent_create_plugin",
    description: "Create and scaffold a new custom WordPress plugin (God Mode)",
    inputSchema: {
      type: "object",
      properties: {
        name: { type: "string", description: "Display name or slug of the plugin" },
        code: { type: "string", description: "Full PHP code of the plugin" },
        description: { type: "string", description: "Optional description of the plugin" },
        activate: { type: "boolean", description: "Whether to activate immediately (default: true)" }
      },
      required: ["name", "code"]
    }
  });

  handlers.set("pressagent_create_plugin", async (args) => {
    const schema = z.object({
      name: z.string(),
      code: z.string(),
      description: z.string().optional(),
      activate: z.boolean().optional()
    });
    const parsed = schema.parse(args);
    return client.createPlugin(parsed.name, parsed.code, parsed.description, parsed.activate);
  });

  // 2. Delete Plugin
  tools.push({
    name: "pressagent_delete_plugin",
    description: "Delete an existing custom WordPress plugin (God Mode)",
    inputSchema: {
      type: "object",
      properties: {
        slug: { type: "string", description: "Slug/folder name of the plugin to delete" }
      },
      required: ["slug"]
    }
  });

  handlers.set("pressagent_delete_plugin", async (args) => {
    const schema = z.object({
      slug: z.string()
    });
    const parsed = schema.parse(args);
    return client.deletePlugin(parsed.slug);
  });

  // 3. Toggle Plugin
  tools.push({
    name: "pressagent_toggle_plugin",
    description: "Activate or deactivate a WordPress plugin (God Mode)",
    inputSchema: {
      type: "object",
      properties: {
        slug: { type: "string", description: "Slug/folder name of the plugin" },
        action: { type: "string", enum: ["activate", "deactivate"], description: "Action to perform" }
      },
      required: ["slug", "action"]
    }
  });

  handlers.set("pressagent_toggle_plugin", async (args) => {
    const schema = z.object({
      slug: z.string(),
      action: z.enum(["activate", "deactivate"])
    });
    const parsed = schema.parse(args);
    return client.togglePlugin(parsed.slug, parsed.action);
  });

  // 4. Run Snippet
  tools.push({
    name: "pressagent_run_snippet",
    description: "Execute a raw PHP snippet inside WordPress environment and capture output (God Mode)",
    inputSchema: {
      type: "object",
      properties: {
        code: { type: "string", description: "PHP code snippet to execute" }
      },
      required: ["code"]
    }
  });

  handlers.set("pressagent_run_snippet", async (args) => {
    const schema = z.object({
      code: z.string()
    });
    const parsed = schema.parse(args);
    return client.runSnippet(parsed.code);
  });
}
