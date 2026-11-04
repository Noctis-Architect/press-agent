import { WPClient } from "../wp-client.js";
import { z } from "zod";
import {
  normalizeSectionData,
  WIDGET_REFERENCE_GUIDE,
  FLEXBOX_RECIPES_GUIDE,
  WORKFLOW_RULES_GUIDE
} from "../templates/elementor-widgets.js";
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
    description: `Retrieve the complete Elementor JSON layout tree and widget settings for a WordPress page, plus the active Kit design tokens.

📋 USAGE GUIDELINES FOR AI AGENTS:
1. Always inspect the layout with this tool BEFORE editing or adding widgets.
2. Examine the returned tree structure:
   - Identify whether the page uses modern Flexbox Containers (elType: "container") or legacy Sections (elType: "section").
   - Locate target widget IDs ("id"), types ("widgetType"), and existing configuration ("settings").
3. Use the discovered widget IDs when calling "pressagent_update_widget".
4. Reuse "design_tokens" (colors, spacing, typography, radii) instead of hardcoding raw hex values.`,
    inputSchema: {
      type: "object",
      properties: {
        page_id: {
          type: "number",
          description: "WordPress post/page ID (e.g. 2, 14, 105)"
        }
      },
      required: ["page_id"]
    }
  });

  handlers.set("pressagent_get_elementor_layout", async (args) => {
    const schema = z.object({
      page_id: z.number()
    });
    const parsed = schema.parse(args);

    const [layout, kit] = await Promise.all([
      client.getElementorLayout(parsed.page_id),
      client.getElementorKit().catch(() => null)
    ]);

    return {
      page_id: parsed.page_id,
      elements: layout,
      kit: kit,
      design_tokens: {
        color_tokens: COLOR_TOKENS,
        spacing_tokens: SPACING_TOKENS,
        typography_tokens: TYPOGRAPHY_TOKENS,
        border_radius_tokens: BORDER_RADIUS_TOKENS
      }
    };
  });

  // 2. Update Widget
  tools.push({
    name: "pressagent_update_widget",
    description: `Update settings of an existing native Elementor widget on a WordPress page in-place.

📋 USAGE GUIDELINES & BEST PRACTICES:
1. Call "pressagent_get_elementor_layout" first to obtain the exact widget "id" and inspect current settings.
2. Only supply the settings keys you want to update (they will be deeply merged).
3. TWO SETTING MODES:
   - Token mode (recommended): provide "widget_type" and use design tokens: color: "primary"|"secondary"|"text"|"accent"|..., padding/margin: "none"|"xs"|"sm"|"md"|"lg"|"xl"|"2xl", typography: "primary"|"secondary"|"text"|"accent". PressAgent resolves tokens against the active Kit and writes native Elementor settings.
   - Raw mode (advanced): omit "widget_type" and pass native Elementor controls directly.
4. USE NATIVE CONTROLS:
   - For "heading": { "title": "New Title", "header_size": "h2", "align": "center", "title_color": "#1e293b" }
   - For "button": { "text": "Contact Us", "link": { "url": "/contact/" }, "align": "center" }
   - For "text-editor": { "editor": "<p>Refined paragraph copy...</p>" }
   - For "icon-box": { "title_text": "Feature Name", "description_text": "Details..." }
5. AUTOMATION: PressAgent purges site cache and returns a desktop screenshot automatically after the update.`,
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
          description: "Native Elementor setting key-value pairs, or token-mode settings (color, typography, align, size, padding, margin).",
          properties: {
            title: { type: "string", description: "Title or heading text" },
            header_size: { type: "string", enum: HEADER_SIZES },
            editor: { type: "string", description: "Rich text content for text-editor (no script/style tags)" },
            align: { type: "string", enum: TEXT_ALIGN },
            color: { type: "string", enum: COLOR_TOKENS, description: "Color token (primary, secondary, text, accent, muted, white, black)" },
            typography: { type: "string", enum: TYPOGRAPHY_TOKENS, description: "Typography token (primary, secondary, text, accent)" },
            background_color: { type: "string", enum: COLOR_TOKENS },
            text: { type: "string", description: "Button text" },
            link: { type: "string", description: "URL link for button" },
            size: { type: "string", enum: BUTTON_SIZES },
            icon: { type: "string", description: "FontAwesome icon class (e.g. 'fas fa-arrow-left')" },
            url: { type: "string", description: "Image URL" },
            alt: { type: "string", description: "Image alt text" },
            title_text: { type: "string", description: "Icon box title" },
            description_text: { type: "string", description: "Icon box description" },
            space: { type: "string", enum: SPACING_TOKENS, description: "Spacer height token" },
            padding: { type: "string", enum: SPACING_TOKENS },
            margin: { type: "string", enum: SPACING_TOKENS }
          }
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
    description: `Add a new Elementor Flexbox Container / Section to a page, or completely rebuild the page layout.

🚨 CRITICAL ARCHITECTURAL RULES (MANDATORY FOR ALL AI AGENTS):
1. **NO RAW HTML WIDGETS**: Never use widgetType "html" or "shortcode" for standard page content, cards, or hero banners! Admins cannot visually edit raw HTML in Elementor's drag-and-drop editor. Always use native widgets: 'heading', 'text-editor', 'button', 'image', 'icon-box', 'counter', 'accordion', etc.
2. **SCHEMA CONSTRAINTS**:
   - Every node must contain: "id" (unique 8-char hex, auto-repaired if omitted), "elType" ("container" or "widget"), "isInner" (boolean), "settings" (object), "elements" (array).
   - Widgets must contain "widgetType" (e.g. "heading").
   - Dimensional values (padding, margin, width) must be structured objects: { "unit": "px", "size": 1200 } or { "unit": "px", "top": "20", "right": "20", "bottom": "20", "left": "20", "isLinked": true }.
3. **HORIZONTAL ROW RECIPE (PREVENTS VERTICAL STACKING BUG)**:
   - In Elementor, containers default to flex-direction: column!
   - To align cards horizontally, set the parent container:
     { "content_width": "boxed", "boxed_width": { "unit": "px", "size": 1280 }, "flex_direction": "row", "flex_wrap": "wrap", "flex_justify_content": "space-between", "flex_gap": { "unit": "px", "size": 24, "column": "24", "row": "24" } }
   - And set each child card container: { "content_width": "full", "width": { "unit": "%", "size": 31 }, "flex_direction": "column" }.
4. **TOKEN MODE (RECOMMENDED)**: Instead of raw "section_data", pass "container" with design tokens:
   { "direction": "row", "width": "boxed", "boxed_width": 1280, "gap": "lg", "padding": "xl", "background_color": "primary", "elements": [ { "el_type": "widget", "widget_type": "heading", "settings": { "title": "Hello", "color": "text" } } ] }
   PressAgent validates the tree, resolves tokens against the active Kit, and writes native Elementor JSON.
5. **FULL PAGE REBUILD MODE**:
   - Token mode: pass { "replace_all": true, "container": <Container> }.
   - Raw mode: pass { "section_data": { "_replace_all": true, "elements": [ <Container 1>, <Container 2>, ... ] } }.
6. **POST-WRITE PROTOCOL**:
   - PressAgent automatically purges cache and returns a screenshot. Inspect it, then capture mobile if needed.
   - Check "pressagent_read_debug_log" for any PHP issues.

${WIDGET_REFERENCE_GUIDE}
${FLEXBOX_RECIPES_GUIDE}`,
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
          description: "Structured Flexbox Container with native widgets and design tokens.",
          properties: {
            id: { type: "string", description: "Optional 8-char hex ID. Auto-generated if omitted." },
            direction: { type: "string", enum: FLEX_DIRECTIONS, description: "Flex direction: 'column', 'row', 'column-reverse', 'row-reverse'" },
            width: { type: "string", enum: ["boxed", "full"], description: "Container width: 'boxed' or 'full'" },
            boxed_width: { type: "number", description: "Max width in px when boxed (e.g. 1140 or 1280)" },
            wrap: { type: "string", enum: ["nowrap", "wrap"], description: "Flex wrap" },
            justify_content: { type: "string", enum: FLEX_JUSTIFY },
            align_items: { type: "string", enum: FLEX_ALIGN },
            gap: { type: "string", enum: SPACING_TOKENS, description: "Spacing token: none, xs, sm, md, lg, xl, 2xl" },
            padding: { type: "string", enum: SPACING_TOKENS },
            margin: { type: "string", enum: SPACING_TOKENS },
            background_color: { type: "string", enum: COLOR_TOKENS },
            border_radius: { type: "string", enum: BORDER_RADIUS_TOKENS },
            elements: {
              type: "array",
              description: "Array of child containers or native widgets.",
              items: {
                type: "object",
                properties: {
                  id: { type: "string" },
                  el_type: { type: "string", enum: ["container", "widget"] },
                  widget_type: { type: "string", enum: ALLOWED_WIDGET_TYPES },
                  settings: { type: "object" }
                }
              }
            }
          },
          required: ["direction"]
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
