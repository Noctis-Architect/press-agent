import { WPClient } from "../wp-client.js";
import { z } from "zod";

const ACTIONS = ["get", "set", "rollback"] as const;
const GROUPS = ["general", "reading", "discussion", "media", "permalinks", "privacy"] as const;

export function registerWpSettingsTools(
  tools: any[],
  handlers: Map<string, (args: any) => Promise<any>>,
  client: WPClient
) {
  tools.push({
    name: "pressagent_wp_settings",
    description: `WordPress core settings with validation and snapshots.

Groups: general (site title/tagline, siteurl/home, admin email, registration role, timezone, date/time, language, search visibility) | reading (front page, posts page, posts_per_page) | discussion (comments/pings) | media (image sizes, cropping, upload folders) | permalinks (structure, category/tag base) | privacy (privacy page, site icon).

Actions: get {groups?} | set {values, confirm?} | rollback {snapshot_id}. siteurl/home require confirm:true and return warnings. Permalink changes flush rewrite rules automatically.`,
    inputSchema: {
      type: "object",
      properties: {
        action: { type: "string", enum: ACTIONS },
        groups: { type: "array", items: { type: "string", enum: GROUPS }, description: "get: groups to read (omit = all)" },
        values: { type: "object", description: "set: {group: {option: value}}" },
        confirm: { type: "boolean", description: "Required for siteurl/home changes" },
        snapshot_id: { type: "string", description: "rollback: id returned by set" }
      },
      required: ["action"]
    }
  });

  handlers.set("pressagent_wp_settings", async (args) => {
    const schema = z.object({
      action: z.enum(ACTIONS),
      groups: z.array(z.enum(GROUPS)).optional().default([]),
      values: z.record(z.record(z.any())).optional(),
      confirm: z.boolean().optional().default(false),
      snapshot_id: z.string().optional()
    });
    const parsed = schema.parse(args);

    switch (parsed.action) {
      case "get":
        return client.getWpSettings(parsed.groups);
      case "set":
        if (!parsed.values) throw new Error('"values" is required for set.');
        return client.setWpSettings(parsed.values, parsed.confirm);
      case "rollback":
        if (!parsed.snapshot_id) throw new Error('"snapshot_id" is required for rollback.');
        return client.rollbackWpSetting(parsed.snapshot_id);
    }
  });
}
