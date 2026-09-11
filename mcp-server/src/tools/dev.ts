import { WPClient } from "../wp-client.js";
import { z } from "zod";

export function registerDevTools(tools: any[], handlers: Map<string, (args: any) => Promise<any>>, client: WPClient) {
  // 1. Create Plugin
  tools.push({
    name: "pressagent_create_plugin",
    description: `Create and scaffold a production-grade WordPress plugin directly into wp-content/plugins/ (God Mode).

🛡️ STRICT WORDPRESS & SECURITY STANDARDS (MANDATORY):
1. HEADER: Must include standard WordPress plugin header comments:
   /*
    * Plugin Name: My Custom Feature
    * Description: Purpose of plugin
    * Version: 1.0.0
    * Author: AI Engineer
    * License: GPL v2 or later
    */
2. SECURITY GUARD: Always prevent direct script access at top of file:
   defined('ABSPATH') || exit;
3. DATA SANITIZATION & ESCAPING:
   - Always sanitize inputs: sanitize_text_field(), absint(), sanitize_email(), wp_unslash().
   - Always escape outputs: esc_html(), esc_attr(), esc_url().
   - Use parameterized queries with $wpdb->prepare() for custom SQL.
4. AUTHORIZATION: Protect admin actions with current_user_can('manage_options') and verify nonces.
5. PRE-VALIDATION: PressAgent automatically performs syntax validation via 'php -l' before saving. If a syntax error is detected, the operation aborts to protect the site from downtime.`,
    inputSchema: {
      type: "object",
      properties: {
        name: {
          type: "string",
          description: "Human-readable plugin name (e.g. 'PressAgent Analytics Hub')"
        },
        code: {
          type: "string",
          description: "Complete PHP code for the plugin, including <?php, plugin header, and secure implementation"
        },
        description: {
          type: "string",
          description: "Brief description of the plugin functionality"
        },
        activate: {
          type: "boolean",
          description: "Whether to immediately activate the plugin after creation (default: true)"
        }
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
    description: "Delete an existing custom WordPress plugin directory and files (God Mode / Scaffolding cleanup).",
    inputSchema: {
      type: "object",
      properties: {
        slug: {
          type: "string",
          description: "Slug/folder name of the custom plugin to delete (e.g. 'my-custom-plugin')"
        }
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
    description: "Activate or deactivate a WordPress plugin safely via WordPress core API.",
    inputSchema: {
      type: "object",
      properties: {
        slug: {
          type: "string",
          description: "Slug or relative file path of the plugin (e.g. 'woocommerce/woocommerce.php' or 'pressagent-custom')"
        },
        action: {
          type: "string",
          enum: ["activate", "deactivate"],
          description: "Target action: 'activate' or 'deactivate'"
        }
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
    description: `Execute an isolated PHP snippet inside the live WordPress runtime and capture returned stdout/stderr (God Mode).

Use for diagnostics, testing hooks, inspecting WP options, querying $wpdb, or testing integrations.`,
    inputSchema: {
      type: "object",
      properties: {
        code: {
          type: "string",
          description: "PHP code to run inside WordPress runtime (without enclosing <?php tags or with them)"
        }
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

