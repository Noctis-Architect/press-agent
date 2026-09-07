import { WPClient } from "../wp-client.js";
import { z } from "zod";

export function registerPagesTools(tools: any[], handlers: Map<string, (args: any) => Promise<any>>, client: WPClient) {
  tools.push({
    name: "pressagent_list_pages",
    description: "List all WordPress pages",
    inputSchema: {
      type: "object",
      properties: {}
    }
  });

  handlers.set("pressagent_list_pages", async () => {
    return client.listPages();
  });

  tools.push({
    name: "pressagent_create_page",
    description: "Create a new page",
    inputSchema: {
      type: "object",
      properties: {
        title: { type: "string" },
        content: { type: "string" },
        status: { type: "string", enum: ["draft", "publish", "private"] }
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

  tools.push({
    name: "pressagent_delete_page",
    description: "Delete a page",
    inputSchema: {
      type: "object",
      properties: {
        page_id: { type: "number" }
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
