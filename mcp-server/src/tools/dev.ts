import { WPClient } from "../wp-client.js";
import { z } from "zod";

export function registerDevTools(tools: any[], handlers: Map<string, (args: any) => Promise<any>>, client: WPClient) {
  tools.push({
    name: "pressagent_create_plugin",
    description: "Create a new WordPress plugin",
    inputSchema: {
      type: "object",
      properties: {
        name: { type: "string" },
        code: { type: "string" },
        description: { type: "string" }
      },
      required: ["name", "code"]
    }
  });

  handlers.set("pressagent_create_plugin", async (args) => {
    const schema = z.object({
      name: z.string(),
      code: z.string(),
      description: z.string().optional()
    });
    const parsed = schema.parse(args);
    return client.createPlugin(parsed.name, parsed.code, parsed.description);
  });
}
