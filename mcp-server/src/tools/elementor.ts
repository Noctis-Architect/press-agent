import { WPClient } from "../wp-client.js";
import { z } from "zod";
import { normalizeSectionData } from "../templates/elementor-widgets.js";
import {
  ALLOWED_WIDGET_TYPES,
  COLOR_TOKENS,
  SPACING_TOKENS,
  TYPOGRAPHY_TOKENS,
  BORDER_RADIUS_TOKENS,
  FLEX_DIRECTIONS,
  FLEX_ALIGN,
  FLEX_JUSTIFY,
  HEADER_SIZES,
  TEXT_ALIGN,
  BUTTON_SIZES,
  WidgetSettingsSchema
} from "./elementor-types.js";
import {
  validateAndSanitizeElementorTree,
  transformAndResolveToElementor,
  KitData
} from "./elementor-validator.js";
import { captureScreenshotInternal } from "./screenshot.js";

function summarizeElementorLayout(elements: any[], maxNodes = 400) {
  const nodes: any[] = [];
  let truncated = false;

  const walk = (items: any[], depth: number) => {
    for (const el of items) {
      if (nodes.length >= maxNodes) {
        truncated = true;
        return;
      }
      if (!el || typeof el !== "object") continue;
      const widgetType = el.widgetType || el.widget_type;
      const type = el.elType || el.el_type || (widgetType ? "widget" : "container");
      nodes.push({
        id: el.id,
        type,
        widget_type: widgetType || undefined,
        depth,
        children: Array.isArray(el.elements) ? el.elements.length : 0,
        text: elementorTextPreview(el.settings || {})
      });
      if (Array.isArray(el.elements) && el.elements.length) {
        walk(el.elements, depth + 1);
      }
    }
  };

  walk(elements, 0);
  return { node_count: nodes.length, truncated, nodes };
}

function elementorTextPreview(settings: Record<string, any>): string | undefined {
  const raw = settings.title || settings.title_text || settings.text || settings.editor || settings.description_text;
  if (typeof raw !== "string") return undefined;
  const text = raw.replace(/<[^>]*>/g, " ").replace(/\s+/g, " ").trim();
  if (!text) return undefined;
  return text.length > 90 ? text.slice(0, 87) + "..." : text;
}

function assertNoForbiddenWidgets(node: any): void {
  if (!node || typeof node !== "object") return;
  if (Array.isArray(node)) {
    node.forEach(assertNoForbiddenWidgets);
    return;
  }
  const type = node.widgetType || node.widget_type;
  if (type === "html" || type === "shortcode") {
    throw new Error(`Security violation: widgetType "${type}" is strictly forbidden. Native Elementor widgets only.`);
  }
  if (Array.isArray(node.elements)) {
    node.elements.forEach(assertNoForbiddenWidgets);
  }
}

export function registerElementorTools(tools: any[], handlers: Map<string, (args: any) => Promise<any>>, client: WPClient) {
  // 1. Get Elementor Layout
  tools.push({
    name: "pressagent_get_elementor_layout",
    description: `Inspect a page's Elementor layout and available design tokens.

Defaults to a compact node summary (id, type, widget_type, depth, text preview) — enough to locate targets for pressagent_update_widget. Use detail:"full" only when you need raw settings/JSON. Set include_kit:true to include the resolved Kit values.`,
    inputSchema: {
      type: "object",
      properties: {
        page_id: {
          type: "number",
          description: "WordPress post/page ID (e.g. 2, 14, 105)"
        },
        detail: {
          type: "string",
          enum: ["summary", "full"],
          description: "summary (default): compact node list; full: complete Elementor JSON including settings."
        },
        include_kit: {
          type: "boolean",
          description: "Include resolved Kit colors/typography values (default false)."
        }
      },
      required: ["page_id"]
    }
  });

  handlers.set("pressagent_get_elementor_layout", async (args) => {
    const schema = z.object({
      page_id: z.number(),
      detail: z.enum(["summary", "full"]).optional().default("summary"),
      include_kit: z.boolean().optional().default(false)
    });
    const parsed = schema.parse(args);

    const [layout, kit] = await Promise.all([
      client.getElementorLayout(parsed.page_id),
      parsed.include_kit ? client.getElementorKit().catch(() => null) : Promise.resolve(null)
    ]);

    const elements = Array.isArray(layout) ? layout : (layout as any)?.elements || [];
    const designTokens = {
      color_tokens: COLOR_TOKENS,
      spacing_tokens: SPACING_TOKENS,
      typography_tokens: TYPOGRAPHY_TOKENS,
      border_radius_tokens: BORDER_RADIUS_TOKENS
    };

    if (parsed.detail === "full") {
      return {
        page_id: parsed.page_id,
        detail: "full",
        elements: layout,
        ...(parsed.include_kit ? { kit } : {}),
        design_tokens: designTokens
      };
    }

    return {
      page_id: parsed.page_id,
      detail: "summary",
      ...summarizeElementorLayout(elements),
      ...(parsed.include_kit ? { kit } : {}),
      design_tokens: designTokens
    };
  });

  // 2. Update Widget
  tools.push({
    name: "pressagent_update_widget",
    description: `Update an existing native Elementor widget in place (settings merge).

Token mode (recommended): pass widget_type + tokens (color, typography, padding, margin, size); resolved against the active Kit. Raw mode: omit widget_type and pass native controls (title_color, link:{url}, editor). Validates, purges cache and returns a desktop screenshot. Get widget IDs from pressagent_get_elementor_layout.`,
    inputSchema: {
      type: "object",
      properties: {
        page_id: {
          type: "number",
          description: "WordPress page ID containing the widget"
        },
        widget_id: {
          type: "string",
          description: "Unique 7-8 character alphanumeric ID of the target widget (e.g. 'c4f82a1b')"
        },
        widget_type: {
          type: "string",
          enum: ALLOWED_WIDGET_TYPES,
          description: "Optional. Widget type for token-mode validation and token resolution."
        },
        settings: {
          type: "object",
          description: `Token mode keys: title, header_size (h1..h6), editor, align (left|center|right|justify), color/background_color (${COLOR_TOKENS.join("|")}), typography (${TYPOGRAPHY_TOKENS.join("|")}), text, link, size (${BUTTON_SIZES.join("|")}), icon, url, alt, title_text, description_text, space/padding/margin (${SPACING_TOKENS.join("|")}). Raw mode: pass native Elementor controls; they are merged.`
        }
      },
      required: ["page_id", "widget_id", "settings"]
    }
  });

  handlers.set("pressagent_update_widget", async (args) => {
    const schema = z.object({
      page_id: z.number(),
      widget_id: z.string(),
      widget_type: z.enum(ALLOWED_WIDGET_TYPES).optional(),
      settings: z.record(z.any())
    });
    const parsed = schema.parse(args);

    let settingsToWrite: Record<string, any> = parsed.settings;

    if (parsed.widget_type) {
      // Tagged settings are validated and token-resolved against the active Kit
      const typedSettings = WidgetSettingsSchema.parse(parsed.settings);

      let kitData: KitData | undefined;
      try {
        kitData = (await client.getElementorKit()) as KitData;
      } catch {
        // Use defaults if kit endpoint fails
      }

      const pseudoWidget = {
        id: parsed.widget_id,
        el_type: "widget",
        widget_type: parsed.widget_type,
        settings: typedSettings
      };
      validateAndSanitizeElementorTree([pseudoWidget]);
      settingsToWrite = transformAndResolveToElementor(pseudoWidget, kitData).settings;
    }

    await client.updateWidget(parsed.page_id, parsed.widget_id, settingsToWrite);

    // Invalidate site cache
    await client.purgeCache("all").catch(() => {});

    // Automatic visual verification screenshot
    const screenshot = await captureScreenshotInternal({
      page_id: parsed.page_id,
      viewport: "desktop",
      wait_ms: 1500
    });

    return {
      success: true,
      page_id: parsed.page_id,
      widget_id: parsed.widget_id,
      widget_type: parsed.widget_type || null,
      message: "Widget updated successfully and cache purged.",
      screenshot: screenshot.success ? {
        image_path: screenshot.image_path,
        preview_symlink: screenshot.preview_symlink,
        target_url: screenshot.target_url,
        instruction: `Use view_file on "${screenshot.image_path}" to visually inspect the updated widget.`
      } : null
    };
  });

  // 3. Add / Rebuild Elementor Section or Container
  tools.push({
    name: "pressagent_add_elementor_section",
    description: `Add a Flexbox container or rebuild the page layout.

Token mode (preferred): pass "container" {direction, width, gap, padding, background_color, elements:[native widgets]}; set replace_all:true to rebuild. Raw mode: pass "section_data" (auto-normalized Elementor JSON). Never use html/shortcode widgets; ids are 8-char hex; dimensions are structured objects. Containers default to column — use direction:"row" + wrap:"wrap" for rows.

PressAgent validates, resolves Kit tokens, purges cache and returns a desktop screenshot. Recipes: "elementor-page-builder" prompt or pressagent-elementor skill.`,
    inputSchema: {
      type: "object",
      properties: {
        page_id: {
          type: "number",
          description: "WordPress page ID"
        },
        replace_all: {
          type: "boolean",
          description: "Token mode only. Set to true to completely replace the page layout. Default is false (appends to end)."
        },
        section_data: {
          type: "object",
          description: "Raw Elementor container node object, array of containers, or { _replace_all: true, elements: [...] }. Auto-normalized before saving."
        },
        container: {
          type: "object",
          description: `Token-mode container: {id?, direction:"${FLEX_DIRECTIONS.join("|")}", width:"boxed|full", boxed_width?, wrap:"nowrap|wrap", justify_content?, align_items?, gap?, padding?, margin?, background_color?, border_radius?, elements:[...]}. Each child: {el_type:"container"|"widget", widget_type?:${ALLOWED_WIDGET_TYPES.join("|")}, settings:{...}}. Direction is required.`
        }
      },
      required: ["page_id"]
    }
  });

  handlers.set("pressagent_add_elementor_section", async (args) => {
    const schema = z.object({
      page_id: z.number(),
      replace_all: z.boolean().optional().default(false),
      section_data: z.any().optional(),
      container: z.any().optional()
    });
    const parsed = schema.parse(args);

    if (!parsed.section_data && !parsed.container) {
      throw new Error('Provide either "section_data" (raw Elementor JSON) or "container" (token mode).');
    }

    let saved: any;
    let elementId: string | null = null;

    if (parsed.section_data) {
      // Raw mode: auto-normalize structure; block only dangerous widget types
      const normalizedData = normalizeSectionData(parsed.section_data);
      assertNoForbiddenWidgets(normalizedData);
      saved = await client.addElementorSection(parsed.page_id, normalizedData);
      elementId = !Array.isArray(normalizedData) && !normalizedData._replace_all ? normalizedData.id : null;
    } else {
      // Token mode: validate, resolve tokens, then save
      const containerSchema = z.record(z.any());
      const container = containerSchema.parse(parsed.container);

      let kitData: KitData | undefined;
      try {
        kitData = (await client.getElementorKit()) as KitData;
      } catch {
        // Use fallback defaults if kit endpoint fails
      }

      validateAndSanitizeElementorTree([container]);
      const resolvedElement = transformAndResolveToElementor(container, kitData, false);
      elementId = resolvedElement.id;

      const payload: any = parsed.replace_all
        ? { _replace_all: true, elements: [resolvedElement] }
        : resolvedElement;

      saved = await client.addElementorSection(parsed.page_id, payload);
    }

    // Invalidate cache automatically
    await client.purgeCache("all").catch(() => {});

    // Automatic screenshot capture
    const screenshot = await captureScreenshotInternal({
      page_id: parsed.page_id,
      viewport: "desktop",
      wait_ms: 2000
    });

    return {
      success: true,
      page_id: parsed.page_id,
      element_id: elementId,
      replace_all: parsed.replace_all,
      mode: parsed.section_data ? "raw" : "token",
      saved: saved,
      message: "Layout saved successfully and cache purged.",
      screenshot: screenshot.success ? {
        image_path: screenshot.image_path,
        preview_symlink: screenshot.preview_symlink,
        target_url: screenshot.target_url,
        instruction: `Use view_file on "${screenshot.image_path}" to visually inspect the rendered page layout.`
      } : null
    };
  });
}
