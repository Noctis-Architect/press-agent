import { WPClient } from "../wp-client.js";
import { z } from "zod";

export function registerSettingsTools(tools: any[], handlers: Map<string, (args: any) => Promise<any>>, client: WPClient) {
  tools.push({
    name: "pressagent_read_option",
    description: "Read a plugin option",
    inputSchema: {
      type: "object",
      properties: {
        plugin_slug: { type: "string" },
        key: { type: "string" }
      },
      required: ["plugin_slug", "key"]
    }
  });

  handlers.set("pressagent_read_option", async (args) => {
    const schema = z.object({
      plugin_slug: z.string(),
      key: z.string()
    });
    const parsed = schema.parse(args);
    return client.readOption(parsed.plugin_slug, parsed.key);
  });

  tools.push({
    name: "pressagent_write_option",
    description: "Write a plugin option",
    inputSchema: {
      type: "object",
      properties: {
        plugin_slug: { type: "string" },
        key: { type: "string" },
        value: {}
      },
      required: ["plugin_slug", "key", "value"]
    }
  });

  handlers.set("pressagent_write_option", async (args) => {
    const schema = z.object({
      plugin_slug: z.string(),
      key: z.string(),
      value: z.any()
    });
    const parsed = schema.parse(args);
    return client.writeOption(parsed.plugin_slug, parsed.key, parsed.value);
  });

  tools.push({
    name: "pressagent_purge_cache",
    description: "Purge cache",
    inputSchema: {
      type: "object",
      properties: {
        plugin_slug: { type: "string" }
      }
    }
  });

  handlers.set("pressagent_purge_cache", async (args) => {
    const schema = z.object({
      plugin_slug: z.string().optional()
    });
    const parsed = schema.parse(args);
    return client.purgeCache(parsed.plugin_slug);
  });

  tools.push({
    name: "pressagent_get_cache_status",
    description: "Get cache status",
    inputSchema: {
      type: "object",
      properties: {}
    }
  });

  handlers.set("pressagent_get_cache_status", async () => {
    return client.getCacheStatus();
  });
}
