import { WPClient } from "../wp-client.js";
import { z } from "zod";

const ACTIONS = ["list", "install", "activate", "deactivate", "update"] as const;

export function registerPluginTools(
  tools: any[],
  handlers: Map<string, (args: any) => Promise<any>>,
  client: WPClient
) {
  tools.push({
    name: "pressagent_plugins",
    description: `Plugin inventory and lifecycle.

Actions: list (version/active/update) | install (free WordPress.org slug, activate?) | activate / deactivate (plugin = slug or folder/file.php) | update. Premium plugins require a license and cannot be installed here. Common slugs: wordpress-seo, seo-by-rank-math, wordfence, all-in-one-wp-security-and-firewall, elementor, litespeed-cache, contact-form-7, wpforms-lite, woocommerce, redirection, wp-mail-smtp, really-simple-ssl. Use pressagent_plugin for validated configuration and pressagent_seo for SEO metadata.`,
    inputSchema: {
      type: "object",
      properties: {
        action: { type: "string", enum: ACTIONS },
        slug: { type: "string", description: "WordPress.org slug for install" },
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
      plugin: z.string().optional(),
      activate: z.boolean().optional().default(false),
      include_inactive: z.boolean().optional().default(true)
    });
    const parsed = schema.parse(args);

    switch (parsed.action) {
      case "list":
        return client.listPlugins(parsed.include_inactive);
      case "install":
        if (!parsed.slug) throw new Error('"slug" is required for action "install".');
        return client.installPlugin(parsed.slug, parsed.activate);
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
