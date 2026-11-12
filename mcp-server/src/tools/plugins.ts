import { WPClient } from "../wp-client.js";
import { z } from "zod";

const ACTIONS = ["list", "search", "install", "activate", "deactivate", "update"] as const;

export function registerPluginTools(
  tools: any[],
  handlers: Map<string, (args: any) => Promise<any>>,
  client: WPClient
) {
  tools.push({
    name: "pressagent_plugins",
    description: `Plugin inventory and lifecycle, mirroring WP Admin → Plugins → Add New.

Actions: list (version/active/update) | search (query: search WordPress.org by name, author or tag) | install (slug or query = exact slug, WordPress.org URL or plugin name; activate?) | activate / deactivate (plugin = slug or folder/file.php) | update.

Install flow: if the slug is unknown, call search first, then install with the exact slug. A name query is resolved automatically when unambiguous; ambiguous queries return candidate slugs instead of guessing. Installing an already installed plugin is safe and can activate it when activate:true. Premium plugins require a license and cannot be installed here. Use pressagent_plugin for validated configuration and pressagent_seo for SEO metadata.`,
    inputSchema: {
      type: "object",
      properties: {
        action: { type: "string", enum: ACTIONS },
        slug: { type: "string", description: "install: WordPress.org slug, URL or plugin name" },
        query: { type: "string", description: "search: name/author/tag. install: alternative to slug" },
        limit: { type: "number", description: "search: max results (default 10, max 20)" },
        plugin: { type: "string", description: "Installed slug/file for activate/deactivate/update" },
        activate: { type: "boolean", description: "install: activate after install" },
        include_inactive: { type: "boolean", description: "list: include inactive (default true)" }
      },
      required: ["action"]
    }
  });

  handlers.set("pressagent_plugins", async (args) => {
    const schema = z.object({
      action: z.enum(ACTIONS),
      slug: z.string().optional(),
      query: z.string().optional(),
      limit: z.number().int().min(1).max(20).optional().default(10),
      plugin: z.string().optional(),
      activate: z.boolean().optional().default(false),
      include_inactive: z.boolean().optional().default(true)
    });
    const parsed = schema.parse(args);

    switch (parsed.action) {
      case "list":
        return client.listPlugins(parsed.include_inactive);
      case "search":
        if (!parsed.query) throw new Error('"query" is required for action "search".');
        return client.searchPlugins(parsed.query, parsed.limit);
      case "install": {
        const identifier = parsed.slug || parsed.query || parsed.plugin;
        if (!identifier) throw new Error('"slug" or "query" is required for action "install".');
        return client.installPlugin(identifier, parsed.activate);
      }
      case "activate":
        if (!parsed.plugin) throw new Error('"plugin" is required for action "activate".');
        return client.togglePluginByIdentifier(parsed.plugin, "activate");
      case "deactivate":
        if (!parsed.plugin) throw new Error('"plugin" is required for action "deactivate".');
        return client.togglePluginByIdentifier(parsed.plugin, "deactivate");
      case "update":
        if (!parsed.plugin) throw new Error('"plugin" is required for action "update".');
        return client.updatePlugin(parsed.plugin);
    }
  });
}
