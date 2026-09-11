import { WPClient } from "../wp-client.js";
import { z } from "zod";
import {
  normalizeSectionData,
  WIDGET_REFERENCE_GUIDE,
  FLEXBOX_RECIPES_GUIDE,
  WORKFLOW_RULES_GUIDE
} from "../templates/elementor-widgets.js";

export function registerElementorTools(tools: any[], handlers: Map<string, (args: any) => Promise<any>>, client: WPClient) {
  // 1. Get Elementor Layout
  tools.push({
    name: "pressagent_get_elementor_layout",
    description: `Retrieve the complete Elementor JSON layout tree and widget settings for a WordPress page.

📋 USAGE GUIDELINES FOR AI AGENTS:
1. Always inspect the layout with this tool BEFORE editing or adding widgets.
2. Examine the returned tree structure:
   - Identify whether the page uses modern Flexbox Containers (elType: "container") or legacy Sections (elType: "section").
   - Locate target widget IDs ("id"), types ("widgetType"), and existing configuration ("settings").
3. Use the discovered widget IDs when calling "pressagent_update_widget".`,
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
    return client.getElementorLayout(parsed.page_id);
  });

  // 2. Update Widget
  tools.push({
    name: "pressagent_update_widget",
    description: `Update settings of an existing native Elementor widget on a WordPress page in-place.

📋 USAGE GUIDELINES & BEST PRACTICES:
1. Call "pressagent_get_elementor_layout" first to obtain the exact widget "id" and inspect current settings.
2. Only supply the settings keys you want to update (they will be deeply merged).
3. USE NATIVE CONTROLS:
   - For "heading": { "title": "New Title", "header_size": "h2", "align": "center", "title_color": "#1e293b" }
   - For "button": { "text": "Contact Us", "link": { "url": "/contact/" }, "align": "center" }
   - For "text-editor": { "editor": "<p>Refined paragraph copy...</p>" }
   - For "icon-box": { "title_text": "Feature Name", "description_text": "Details..." }
4. CACHE INVALIDATION: After updating widgets, always call "pressagent_purge_cache" to ensure live CSS and element caches are flushed.`,
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
        settings: {
          type: "object",
          description: "Object containing native Elementor setting key-value pairs to update"
        }
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
4. **FULL PAGE REBUILD MODE**:
   - To replace the entire page cleanly without appending to old content, pass:
     { "_replace_all": true, "elements": [ <Container 1>, <Container 2>, ... ] }
5. **POST-WRITE PROTOCOL**:
   - Step 1: Purge cache using "pressagent_purge_cache({ plugin_slug: 'all' })".
   - Step 2: Take visual QA screenshot using "pressagent_capture_screenshot({ page_id, viewport: 'desktop' })" and inspect it visually.
   - Step 3: Check "pressagent_read_debug_log" for any PHP issues.

${WIDGET_REFERENCE_GUIDE}
${FLEXBOX_RECIPES_GUIDE}`,
    inputSchema: {
      type: "object",
      properties: {
        page_id: {
          type: "number",
          description: "WordPress page ID"
        },
        section_data: {
          type: "object",
          description: "Elementor container node object, array of containers, or { _replace_all: true, elements: [...] }"
        }
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

    // Auto-normalize and validate schema structure before sending to WordPress
    const normalizedData = normalizeSectionData(parsed.section_data);
    return client.addElementorSection(parsed.page_id, normalizedData);
  });
}

