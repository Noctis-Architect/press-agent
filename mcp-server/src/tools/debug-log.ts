import { WPClient } from "../wp-client.js";
import { z } from "zod";

export function registerDebugLogTools(tools: any[], handlers: Map<string, (args: any) => Promise<any>>, client: WPClient) {
  tools.push({
    name: "pressagent_read_debug_log",
    description: "Read recent debug log entries",
    inputSchema: {
      type: "object",
      properties: {
        lines: { type: "number" },
        level: { type: "string", enum: ["all", "error", "warning", "notice"] }
      }
    }
  });

  handlers.set("pressagent_read_debug_log", async (args) => {
    const schema = z.object({
      lines: z.number().optional().default(100),
      level: z.enum(["all", "error", "warning", "notice"]).optional().default("all")
    });
    const parsed = schema.parse(args);
    return client.readDebugLog(parsed.lines, parsed.level);
  });

  tools.push({
    name: "pressagent_diagnose_error",
    description: "Diagnose an error",
    inputSchema: {
      type: "object",
      properties: {
        error_text: { type: "string" }
      },
      required: ["error_text"]
    }
  });

  handlers.set("pressagent_diagnose_error", async (args) => {
    const schema = z.object({
      error_text: z.string()
    });
    const parsed = schema.parse(args);
    return client.diagnoseError(parsed.error_text);
  });
}
