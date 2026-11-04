import { WPClient } from "../wp-client.js";
import { z } from "zod";

const ACTIONS = ["providers", "get", "set"] as const;
const PROVIDERS = ["auto", "yoast", "rankmath"] as const;

const FIELD_KEYS = [
  "title",
  "description",
  "focus_keyword",
  "canonical",
  "noindex",
  "nofollow",
  "advanced_robots",
  "og_title",
  "og_description",
  "og_image",
  "twitter_title",
  "twitter_description",
  "twitter_image",
  "primary_category",
  "schema_page_type",
  "schema_article_type",
  "cornerstone"
];

export function registerSeoTools(
  tools: any[],
  handlers: Map<string, (args: any) => Promise<any>>,
  client: WPClient
) {
  tools.push({
    name: "pressagent_seo",
    description: `Post/term SEO metadata via Yoast or Rank Math (auto-detected).

Actions: providers | get {object_type, object_id, taxonomy?, provider?} | set {object_type, object_id, fields, taxonomy?, provider?}. Fields: ${FIELD_KEYS.join(", ")}. noindex/nofollow/cornerstone are booleans; primary_category is a term ID; terms need taxonomy. Yoast uses its WP 6.9+ ability when available to rebuild indexables, otherwise a warning is returned. Never write SEO meta via pressagent_write_option.`,
    inputSchema: {
      type: "object",
      properties: {
        action: { type: "string", enum: ACTIONS },
        object_type: { type: "string", enum: ["post", "term"] },
        object_id: { type: "number", description: "Post or term ID" },
        taxonomy: { type: "string", description: "Required for terms (e.g. category)" },
        provider: { type: "string", enum: PROVIDERS, description: "auto fails if both plugins are active" },
        fields: {
          type: "object",
          description: `Fields: {${FIELD_KEYS.join(", ")}}. null clears where supported.`
        }
      },
      required: ["action"]
    }
  });

  handlers.set("pressagent_seo", async (args) => {
    const schema = z.object({
      action: z.enum(ACTIONS),
      object_type: z.enum(["post", "term"]).optional(),
      object_id: z.number().int().positive().optional(),
      taxonomy: z.string().optional().default(""),
      provider: z.enum(PROVIDERS).optional().default("auto"),
      fields: z.record(z.any()).optional()
    });
    const parsed = schema.parse(args);

    switch (parsed.action) {
      case "providers":
        return client.getSeoProviders();
      case "get": {
        if (!parsed.object_type || !parsed.object_id) {
          throw new Error('"object_type" and "object_id" are required for get.');
        }
        if (parsed.object_type === "term" && !parsed.taxonomy) {
          throw new Error('"taxonomy" is required for term SEO.');
        }
        return client.getSeoMeta(parsed.object_type, parsed.object_id, parsed.taxonomy, parsed.provider);
      }
      case "set": {
        if (!parsed.object_type || !parsed.object_id || !parsed.fields) {
          throw new Error('"object_type", "object_id" and "fields" are required for set.');
        }
        if (parsed.object_type === "term" && !parsed.taxonomy) {
          throw new Error('"taxonomy" is required for term SEO.');
        }
        return client.setSeoMeta(parsed.object_type, parsed.object_id, parsed.fields, parsed.taxonomy, parsed.provider);
      }
    }
  });
}
