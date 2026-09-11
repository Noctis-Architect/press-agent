import { WPClient } from "../wp-client.js";
import { z } from "zod";

export function registerSettingsTools(tools: any[], handlers: Map<string, (args: any) => Promise<any>>, client: WPClient) {
  // 1. Read Option
  tools.push({
    name: "pressagent_read_option",
    description: `Read an option value from the WordPress options table for a specific plugin or core setting.

🔒 SECURITY NOTE:
Options are protected by strict allowlists to prevent privilege escalation. Critical WordPress options require administrator permissions.`,
    inputSchema: {
      type: "object",
      properties: {
        plugin_slug: {
          type: "string",
          description: "Slug of the plugin or 'general' / 'core' for WordPress options (e.g. 'pressagent', 'elementor', 'general')"
        },
        key: {
          type: "string",
          description: "Option key to read (e.g. 'blogname', 'blogdescription', 'active_plugins')"
        }
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

  // 2. Write Option
  tools.push({
    name: "pressagent_write_option",
    description: `Safely update a WordPress or plugin option.

🛡️ ACTION GUARD™ INTEGRATION:
Every write operation automatically generates a cryptographically verified rollback snapshot in the database. If an option change produces unexpected results, it can be reverted cleanly.`,
    inputSchema: {
      type: "object",
      properties: {
        plugin_slug: {
          type: "string",
          description: "Slug of the plugin or 'general' / 'core' (e.g. 'pressagent', 'elementor')"
        },
        key: {
          type: "string",
          description: "Option key to update"
        },
        value: {
          description: "New value to store (string, number, boolean, or serializable object)"
        }
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

  // 3. Purge Cache
  tools.push({
    name: "pressagent_purge_cache",
    description: `Flush all active WordPress caches, Elementor compiled CSS, and page caching plugins.

⚡ CRITICAL RULE FOR ALL AI ASSISTANTS:
Whenever you modify an Elementor page layout or update widget settings, Elementor stores compiled CSS in 'wp-content/uploads/elementor/css/'. Without running this tool, your visual and CSS changes will NOT appear on the frontend!
Always execute this tool before taking screenshots with "pressagent_capture_screenshot".`,
    inputSchema: {
      type: "object",
      properties: {
        plugin_slug: {
          type: "string",
          description: "Specific cache to purge ('all', 'elementor', 'litespeed', 'wprocket', 'w3tc', 'supercache'). Defaults to 'all'."
        }
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

  // 4. Get Cache Status
  tools.push({
    name: "pressagent_get_cache_status",
    description: "Inspect active caching mechanisms (Redis, Memcached, Elementor CSS cache, third-party cache plugins) and environment stats.",
    inputSchema: {
      type: "object",
      properties: {}
    }
  });

  handlers.set("pressagent_get_cache_status", async () => {
    return client.getCacheStatus();
  });
}

