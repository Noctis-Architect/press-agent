import { WPClient } from "../wp-client.js";
import { z } from "zod";

export function registerPagesTools(tools: any[], handlers: Map<string, (args: any) => Promise<any>>, client: WPClient) {
  // 1. List Pages
  tools.push({
    name: "pressagent_list_pages",
    description: `List all WordPress pages with their IDs, titles, slugs, publish status, and Elementor status.

Use this as the first discovery step to locate target page IDs before inspecting layouts or editing content.`,
    inputSchema: {
      type: "object",
      properties: {}
    }
  });

  handlers.set("pressagent_list_pages", async () => {
    return client.listPages();
  });

  // 2. Create Page
  tools.push({
    name: "pressagent_create_page",
    description: `Create a new WordPress page.

🚀 ELEMENTOR WORKFLOW NOTE:
If you plan to design this page with Elementor:
1. Create the page here with status: "publish".
2. Take the returned "id" and call "pressagent_add_elementor_section" with your Flexbox Container layout ({ _replace_all: true, elements: [...] }).
3. Invalidate cache with "pressagent_purge_cache" and verify with "pressagent_capture_screenshot".`,
    inputSchema: {
      type: "object",
      properties: {
        title: {
          type: "string",
          description: "Title of the page"
        },
        content: {
          type: "string",
          description: "Optional initial HTML/plain-text content (leave empty if designing with Elementor)"
        },
        status: {
          type: "string",
          enum: ["draft", "publish", "private"],
          description: "Publication status: 'publish', 'draft', or 'private' (default: 'draft')"
        }
      },
      required: ["title"]
    }
  });

  handlers.set("pressagent_create_page", async (args) => {
    const schema = z.object({
      title: z.string(),
      content: z.string().optional(),
      status: z.enum(["draft", "publish", "private"]).optional().default("draft")
    });
    const parsed = schema.parse(args);
    return client.createPage(parsed.title, parsed.content, parsed.status);
  });

  // 3. Delete Page
  tools.push({
    name: "pressagent_delete_page",
    description: "Permanently delete a WordPress page and clean up its associated Elementor metadata.",
    inputSchema: {
      type: "object",
      properties: {
        page_id: {
          type: "number",
          description: "ID of the WordPress page to delete"
        }
      },
      required: ["page_id"]
    }
  });

  handlers.set("pressagent_delete_page", async (args) => {
    const schema = z.object({
      page_id: z.number()
    });
    const parsed = schema.parse(args);
    return client.deletePage(parsed.page_id);
  });
}

