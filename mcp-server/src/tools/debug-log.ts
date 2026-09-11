import { WPClient } from "../wp-client.js";
import { z } from "zod";

export function registerDebugLogTools(tools: any[], handlers: Map<string, (args: any) => Promise<any>>, client: WPClient) {
  // 1. Read Debug Log
  tools.push({
    name: "pressagent_read_debug_log",
    description: `Read recent entries from WordPress debug log (wp-content/debug.log).

🩺 AI SELF-HEALING & HEALTH MONITORING:
Use this tool proactively after performing page layout modifications, option changes, or plugin scaffolding. It helps autonomous agents verify that no PHP Fatal Errors, Parse Errors, Uncaught Exceptions, or database errors were silently triggered.

Filter by severity: 'error' (fatal errors, exceptions), 'warning' (runtime warnings), 'notice' (notices/deprecations), or 'all'.`,
    inputSchema: {
      type: "object",
      properties: {
        lines: {
          type: "number",
          description: "Number of tail lines to inspect (default: 100, max: 500)"
        },
        level: {
          type: "string",
          enum: ["all", "error", "warning", "notice"],
          description: "Filter level: 'all', 'error', 'warning', or 'notice' (default: 'all')"
        }
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

  // 2. Diagnose Error
  tools.push({
    name: "pressagent_diagnose_error",
    description: `Analyze a PHP error stack trace or message and get actionable troubleshooting and remediation recommendations.

Use this tool when encountering Elementor white screen of death, memory exhaustion, undefined function calls, or database lockouts.`,
    inputSchema: {
      type: "object",
      properties: {
        error_text: {
          type: "string",
          description: "Raw PHP error string or stack trace snippet to diagnose"
        }
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

