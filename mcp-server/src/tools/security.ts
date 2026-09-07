import { WPClient } from "../wp-client.js";

export function registerSecurityTools(tools: any[], handlers: Map<string, (args: any) => Promise<any>>, client: WPClient) {
  tools.push({
    name: "pressagent_security_summary",
    description: "Get security audit summary",
    inputSchema: {
      type: "object",
      properties: {}
    }
  });

  handlers.set("pressagent_security_summary", async () => {
    return client.getSecuritySummary();
  });
}
