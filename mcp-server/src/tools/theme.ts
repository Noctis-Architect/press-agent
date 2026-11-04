import { WPClient } from "../wp-client.js";
import { z } from "zod";

const ACTIONS = [
  "status",
  "list_files",
  "read_file",
  "write_file",
  "create_child",
  "switch",
  "set_mode",
  "setup",
  "list_backups",
  "restore"
] as const;

const MODES = ["elementor", "child-theme", "hybrid"] as const;

export function registerThemeTools(
  tools: any[],
  handlers: Map<string, (args: any) => Promise<any>>,
  client: WPClient
) {
  tools.push({
    name: "pressagent_theme",
    description: `Child theme and appearance-mode management.

Actions: status (START HERE) | create_child {parent_slug, slug, overwrite} | setup {mode} | switch {slug} | set_mode {mode} | list_files | read_file {path} | write_file {path, content} | list_backups | restore {backup_id}.

Only the ACTIVE CHILD theme is editable. write_file backs up and lints PHP. Modes: elementor (layout in Elementor) | child-theme (PHP/CSS owns layout) | hybrid (Elementor pages/header/footer + child tokens/CSS/hooks). Purge cache and screenshot desktop+mobile after writes.`,
    inputSchema: {
      type: "object",
      properties: {
        action: { type: "string", enum: ACTIONS },
        path: { type: "string", description: "Relative path (style.css, functions.php, assets/css/pressagent.css)" },
        content: { type: "string", description: "Full file content for write_file" },
        parent_slug: { type: "string", description: "Parent theme slug for create_child" },
        slug: { type: "string", description: "Theme slug for create_child/switch" },
        activate: { type: "boolean", description: "create_child: activate (default true)" },
        overwrite: { type: "boolean", description: "create_child: overwrite scaffold" },
        mode: { type: "string", enum: MODES, description: "Appearance mode" },
        backup_id: { type: "string", description: "Backup id for restore" },
        limit: { type: "number", description: "list_files limit (default 200)" }
      },
      required: ["action"]
    }
  });

  handlers.set("pressagent_theme", async (args) => {
    const schema = z.object({
      action: z.enum(ACTIONS),
      path: z.string().optional(),
      content: z.string().optional(),
      parent_slug: z.string().optional().default(""),
      slug: z.string().optional().default(""),
      activate: z.boolean().optional().default(true),
      overwrite: z.boolean().optional().default(false),
      mode: z.enum(MODES).optional(),
      backup_id: z.string().optional(),
      limit: z.number().int().positive().max(500).optional()
    });
    const parsed = schema.parse(args);

    const requireString = (value: string | undefined, field: string) => {
      if (value === undefined || value === "") {
        throw new Error(`"${field}" is required for action "${parsed.action}".`);
      }
      return value;
    };

    switch (parsed.action) {
      case "status":
        return client.getThemeStatus();
      case "list_files":
        return client.listThemeFiles(parsed.limit);
      case "read_file":
        return client.readThemeFile(requireString(parsed.path, "path"));
      case "write_file":
        return client.writeThemeFile(
          requireString(parsed.path, "path"),
          requireString(parsed.content, "content")
        );
      case "create_child":
        return client.createChildTheme(parsed.parent_slug, parsed.slug, parsed.activate, parsed.overwrite);
      case "switch":
        return client.switchTheme(requireString(parsed.slug, "slug"));
      case "set_mode":
        return client.setAppearanceMode(requireString(parsed.mode, "mode"));
      case "setup":
        return client.setupChildTheme(parsed.mode);
      case "list_backups":
        return client.listThemeBackups();
      case "restore":
        return client.restoreThemeBackup(requireString(parsed.backup_id, "backup_id"));
    }
  });
}
