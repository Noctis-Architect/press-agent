import { WPClient } from "../wp-client.js";
import { z } from "zod";

const ACTIONS = ["audit", "page", "fix"] as const;

export function registerPerformanceTools(
  tools: any[],
  handlers: Map<string, (args: any) => Promise<any>>,
  client: WPClient
) {
  tools.push({
    name: "pressagent_performance",
    description: `Performance audit and safe fixes.

Actions: audit | page {page_id?|url?} | fix {fix_id, confirm?, age_days?, limit?}.

audit reports PHP/OPcache/object cache/page cache, autoload size, revisions, transients, debug log, Elementor/LiteSpeed state and prioritized issues. page fetches a same-origin page and measures response time, HTML/script/style/image/font metrics and render-blocking issues.

Fix IDs: enable_litespeed_cache, enable_litespeed_minify, enable_litespeed_lazy, enable_litespeed_browser_cache, enable_elementor_external_css, enable_elementor_element_cache, enable_elementor_optimized_markup, enable_elementor_optimized_css, purge_cache, delete_expired_transients, delete_old_revisions (confirm:true), cleanup_database (confirm:true).`,
    inputSchema: {
      type: "object",
      properties: {
        action: { type: "string", enum: ACTIONS },
        page_id: { type: "number", description: "page: WordPress page ID" },
        url: { type: "string", description: "page: relative path or same-origin URL" },
        fix_id: { type: "string", description: "fix: one of the documented fix IDs" },
        confirm: { type: "boolean", description: "fix: required for delete_old_revisions" },
        age_days: { type: "number", description: "delete_old_revisions: minimum age in days (default 30)" },
        limit: { type: "number", description: "fix: batch limit" }
      },
      required: ["action"]
    }
  });

  handlers.set("pressagent_performance", async (args) => {
    const schema = z.object({
      action: z.enum(ACTIONS),
      page_id: z.number().int().positive().optional(),
      url: z.string().optional().default(""),
      fix_id: z.string().optional(),
      confirm: z.boolean().optional().default(false),
      age_days: z.number().int().positive().max(3650).optional(),
      limit: z.number().int().positive().max(2000).optional()
    });
    const parsed = schema.parse(args);

    switch (parsed.action) {
      case "audit":
        return client.performanceAudit();
      case "page":
        return client.performancePage(parsed.page_id, parsed.url);
      case "fix":
        if (!parsed.fix_id) throw new Error('"fix_id" is required for fix.');
        return client.performanceFix(parsed.fix_id, {
          age_days: parsed.age_days,
          limit: parsed.limit
        }, parsed.confirm);
    }
  });
}
