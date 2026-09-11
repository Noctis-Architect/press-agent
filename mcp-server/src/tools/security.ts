import { WPClient } from "../wp-client.js";

export function registerSecurityTools(tools: any[], handlers: Map<string, (args: any) => Promise<any>>, client: WPClient) {
  tools.push({
    name: "pressagent_security_summary",
    description: `Inspect WordPress security posture, active tokens, file permissions, Action Guard snapshots, and security headers.

Use this tool to audit environment safety, verify RBAC token scopes, and check for open vulnerabilities before and after site modifications.`,
    inputSchema: {
      type: "object",
      properties: {}
    }
  });

  handlers.set("pressagent_security_summary", async () => {
    return client.getSecuritySummary();
  });
}
