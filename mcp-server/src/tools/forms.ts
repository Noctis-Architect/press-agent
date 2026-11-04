import { WPClient } from "../wp-client.js";
import { z } from "zod";

const ACTIONS = ["list", "get", "create_cf7", "update_cf7", "delete_cf7"] as const;

export function registerFormsTools(
  tools: any[],
  handlers: Map<string, (args: any) => Promise<any>>,
  client: WPClient
) {
  tools.push({
    name: "pressagent_forms",
    description: `Contact forms: CF7 full CRUD, WPForms Lite read-only.

Actions: list {provider} | get {form_id, provider, include_data} | create_cf7 / update_cf7 {title, form, mail?, mail_2?, messages?, additional_settings?, locale?} | delete_cf7 {form_id, confirm}.

CF7 form strings use tags like [text* your-name]; mail is an object (subject, sender, recipient, body, additional_headers, use_html). WPForms Lite stores counters only, not entries.`,
    inputSchema: {
      type: "object",
      properties: {
        action: { type: "string", enum: ACTIONS },
        provider: { type: "string", enum: ["auto", "cf7", "wpforms"] },
        form_id: { type: "number" },
        title: { type: "string" },
        form: { type: "string", description: "CF7 form template" },
        mail: { type: "object", description: "CF7 primary mail" },
        mail_2: { type: "object", description: "CF7 auto-reply mail" },
        messages: { type: "object", description: "CF7 messages" },
        additional_settings: { type: "string", description: "CF7 settings lines" },
        locale: { type: "string" },
        include_data: { type: "boolean", description: "get: include full data" },
        confirm: { type: "boolean", description: "Required for delete_cf7" }
      },
      required: ["action"]
    }
  });

  handlers.set("pressagent_forms", async (args) => {
    const schema = z.object({
      action: z.enum(ACTIONS),
      provider: z.enum(["auto", "cf7", "wpforms"]).optional().default("auto"),
      form_id: z.number().int().positive().optional(),
      title: z.string().optional(),
      form: z.string().optional(),
      mail: z.record(z.any()).optional(),
      mail_2: z.record(z.any()).optional(),
      messages: z.record(z.any()).optional(),
      additional_settings: z.string().optional(),
      locale: z.string().optional(),
      include_data: z.boolean().optional().default(true),
      confirm: z.boolean().optional().default(false)
    });
    const parsed = schema.parse(args);

    const payload: Record<string, any> = {};
    for (const key of ["title", "form", "mail", "mail_2", "messages", "additional_settings", "locale"] as const) {
      if (parsed[key] !== undefined) payload[key] = parsed[key];
    }

    switch (parsed.action) {
      case "list":
        return client.listForms(parsed.provider);
      case "get":
        if (!parsed.form_id) throw new Error('"form_id" is required for get.');
        return client.getForm(parsed.form_id, parsed.provider, parsed.include_data);
      case "create_cf7":
        return client.saveCf7Form(payload);
      case "update_cf7":
        if (!parsed.form_id) throw new Error('"form_id" is required for update_cf7.');
        return client.updateCf7Form(parsed.form_id, payload);
      case "delete_cf7":
        if (!parsed.form_id) throw new Error('"form_id" is required for delete_cf7.');
        return client.deleteCf7Form(parsed.form_id, parsed.confirm);
    }
  });
}
