import { WPClient } from "../wp-client.js";
import { z } from "zod";

const ACTIONS = ["catalog", "get", "set", "restore", "action"] as const;

const SUPPORTED = [
  "wordpress-seo",
  "seo-by-rank-math",
  "wordfence",
  "all-in-one-wp-security-and-firewall",
  "elementor",
  "litespeed-cache",
  "contact-form-7",
  "wpforms-lite",
  "woocommerce",
  "redirection",
  "wp-mail-smtp",
  "really-simple-ssl"
];

export function registerPluginConfigTools(
  tools: any[],
  handlers: Map<string, (args: any) => Promise<any>>,
  client: WPClient
) {
  tools.push({
    name: "pressagent_plugin",
    description: `Validated settings and actions for supported plugins. Call action:"catalog" first; never guess option keys.

Actions: catalog {plugin} | get {plugin, ids?, fields?} | set {plugin, values} | restore {snapshot_id} | action {plugin, plugin_action, params, confirm}. Arrays merge per declared fields; set returns snapshot_ids. Destructive actions need confirm:true.

Supported: ${SUPPORTED.join(", ")}. Use pressagent_seo for page/term SEO and pressagent_plugins for install/update.`,
    inputSchema: {
      type: "object",
      properties: {
        action: { type: "string", enum: ACTIONS },
        plugin: { type: "string", description: "Plugin slug" },
        ids: { type: "array", items: { type: "string" }, description: "get: setting ids (omit = all)" },
        fields: { type: "array", items: { type: "string" }, description: "get: limit array values to these fields" },
        values: { type: "object", description: "set: {setting_id: partial_value}" },
        plugin_action: { type: "string", description: "action: name from catalog" },
        params: { type: "object", description: "action: params from catalog" },
        confirm: { type: "boolean", description: "Required for destructive actions" },
        snapshot_id: { type: "string", description: "restore: id from set" }
      },
      required: ["action"]
    }
  });

  handlers.set("pressagent_plugin", async (args) => {
    const schema = z.object({
      action: z.enum(ACTIONS),
      plugin: z.string().optional(),
      ids: z.array(z.string()).optional().default([]),
      fields: z.array(z.string()).optional().default([]),
      values: z.record(z.any()).optional(),
      plugin_action: z.string().optional(),
      params: z.record(z.any()).optional().default({}),
      confirm: z.boolean().optional().default(false),
      snapshot_id: z.string().optional()
    });
    const parsed = schema.parse(args);

    const requirePlugin = () => {
      if (!parsed.plugin) throw new Error(`"plugin" is required for action "${parsed.action}".`);
      return parsed.plugin;
    };

    switch (parsed.action) {
      case "catalog":
        return client.getPluginCatalog(requirePlugin());
      case "get":
        return client.getPluginSettings(requirePlugin(), parsed.ids, parsed.fields);
      case "set":
        if (!parsed.values) throw new Error('"values" is required for action "set".');
        return client.setPluginSettings(requirePlugin(), parsed.values);
      case "restore":
        if (!parsed.snapshot_id) throw new Error('"snapshot_id" is required for action "restore".');
        return client.restorePluginSetting(parsed.snapshot_id);
      case "action":
        if (!parsed.plugin_action) throw new Error('"plugin_action" is required for action "action".');
        return client.runPluginAction(requirePlugin(), parsed.plugin_action, parsed.params, parsed.confirm);
    }
  });
}
