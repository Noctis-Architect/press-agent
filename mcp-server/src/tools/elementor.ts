import { WPClient } from "../wp-client.js";
import { z } from "zod";

export function registerElementorTools(tools: any[], handlers: Map<string, (args: any) => Promise<any>>, client: WPClient) {
  tools.push({
    name: "pressagent_get_elementor_layout",
    description: "Get Elementor layout for a page",
    inputSchema: {
      type: "object",
      properties: {
        page_id: { type: "number" }
      },
      required: ["page_id"]
    }
  });

  handlers.set("pressagent_get_elementor_layout", async (args) => {
    const schema = z.object({
      page_id: z.number()
    });
    const parsed = schema.parse(args);
    return client.getElementorLayout(parsed.page_id);
  });

  tools.push({
    name: "pressagent_update_widget",
    description: "Update a widget's settings",
    inputSchema: {
      type: "object",
      properties: {
        page_id: { type: "number" },
        widget_id: { type: "string" },
        settings: { type: "object" }
      },
      required: ["page_id", "widget_id", "settings"]
    }
  });

  handlers.set("pressagent_update_widget", async (args) => {
    const schema = z.object({
      page_id: z.number(),
      widget_id: z.string(),
      settings: z.any()
    });
    const parsed = schema.parse(args);
    return client.updateWidget(parsed.page_id, parsed.widget_id, parsed.settings);
  });

  tools.push({
    name: "pressagent_add_elementor_section",
    description: "Add a section/container",
    inputSchema: {
      type: "object",
      properties: {
        page_id: { type: "number" },
        section_data: { type: "object" }
      },
      required: ["page_id", "section_data"]
    }
  });

  handlers.set("pressagent_add_elementor_section", async (args) => {
    const schema = z.object({
      page_id: z.number(),
      section_data: z.any()
    });
    const parsed = schema.parse(args);
    return client.addElementorSection(parsed.page_id, parsed.section_data);
  });
}
