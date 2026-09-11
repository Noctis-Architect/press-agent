import { Server } from "@modelcontextprotocol/sdk/server/index.js";
import {
  ListPromptsRequestSchema,
  GetPromptRequestSchema
} from "@modelcontextprotocol/sdk/types.js";
import {
  WIDGET_REFERENCE_GUIDE,
  FLEXBOX_RECIPES_GUIDE,
  WORKFLOW_RULES_GUIDE
} from "../templates/elementor-widgets.js";

export function registerPrompts(server: Server) {
  server.setRequestHandler(ListPromptsRequestSchema, async () => {
    return {
      prompts: [
        {
          name: "elementor-page-builder",
          description: "Master instructions and strict guidelines for building high-converting, native Elementor pages with PressAgent MCP",
          arguments: [
            {
              name: "task_description",
              description: "What page or section are you designing (e.g. 'SaaS Landing Page', 'Pricing Table', 'Contact Section')?",
              required: false
            }
          ]
        },
        {
          name: "elementor-visual-qa",
          description: "Standard operating procedure for headless visual regression inspection, responsive testing, and cache invalidation",
          arguments: [
            {
              name: "page_id",
              description: "WordPress page ID being inspected",
              required: true
            }
          ]
        },
        {
          name: "wordpress-plugin-architect",
          description: "Strict WordPress engineering guidelines for creating custom plugins with God Mode and PressAgent",
          arguments: [
            {
              name: "plugin_purpose",
              description: "Objective and features of the custom plugin",
              required: false
            }
          ]
        }
      ]
    };
  });

  server.setRequestHandler(GetPromptRequestSchema, async (request) => {
    const { name, arguments: args } = request.params;

    if (name === "elementor-page-builder") {
      const task = args?.task_description || "WordPress page creation or redesign";
      return {
        description: "Master Elementor Page Builder Guidelines",
        messages: [
          {
            role: "user",
            content: {
              type: "text",
              text: `TASK: ${task}

# 🚀 PRESSAGENT ELEMENTOR BUILDER MASTER DIRECTIVE

You are an elite WordPress & Elementor engineer. You interact directly with a live WordPress environment via PressAgent MCP tools.

## ⛔ STRICT PROHIBITIONS & RED LINES:
1. **NEVER USE RAW HTML WIDGETS OR SHORTCODES** for regular layout elements (headings, text, buttons, cards).
   - Admins CANNOT edit raw HTML inside Elementor's visual drag-and-drop editor.
   - Always use native Elementor widgets: 'heading', 'text-editor', 'button', 'image', 'icon-box', 'counter', 'accordion', etc.
2. **NEVER FORGET UNIQUE 8-CHAR HEX IDS**:
   - Every node (container or widget) MUST have a unique ID like "c1a2b3d4" or "f8e7d6c5". Duplicate or missing IDs break the Elementor editor.
3. **NEVER USE UNSTRUCTURED DIMENSIONS**:
   - Do not pass "20px" as a string. Use structured objects: { "unit": "px", "size": 20 } or { "unit": "px", "top": "20", "right": "20", "bottom": "20", "left": "20", "isLinked": true }.

## 📐 FLEXBOX CONTAINER ROW RECIPE (AVOIDING VERTICAL STACKING):
Containers default to flex_direction: column. To make cards sit side-by-side:
- Parent Container:
  - content_width: "boxed"
  - boxed_width: { unit: "px", size: 1280 }
  - flex_direction: "row"
  - flex_wrap: "wrap"
  - flex_justify_content: "space-between"
  - flex_gap: { unit: "px", size: 24, column: "24", row: "24" }
- Child Cards:
  - content_width: "full"
  - width: { unit: "%", size: 31 } (for 3 cards per row)
  - flex_direction: "column"

${WIDGET_REFERENCE_GUIDE}
${FLEXBOX_RECIPES_GUIDE}
${WORKFLOW_RULES_GUIDE}

## 🔄 EXECUTION CYCLE:
1. Discover page: pressagent_list_pages
2. Inspect existing tree: pressagent_get_elementor_layout({ page_id })
3. Execute layout update: pressagent_add_elementor_section({ page_id, section_data })
4. Flush cache: pressagent_purge_cache({ plugin_slug: 'all' })
5. Inspect visually: pressagent_capture_screenshot({ page_id, viewport: 'desktop' })
6. Verify mobile responsiveness: pressagent_capture_screenshot({ page_id, viewport: 'mobile' })
7. Check health: pressagent_read_debug_log({ lines: 30 })`
            }
          }
        ]
      };
    }

    if (name === "elementor-visual-qa") {
      const pageId = args?.page_id || "TARGET_PAGE_ID";
      return {
        description: "Visual QA & Screenshot Inspection Workflow",
        messages: [
          {
            role: "user",
            content: {
              type: "text",
              text: `# 👁️ PRESSAGENT VISUAL INSPECTION WORKFLOW FOR PAGE ${pageId}

AI models are visually blind without checking rendered screenshots. Follow this exact QA cycle:

1. **Flush Live Cache**:
   Call \`pressagent_purge_cache({ plugin_slug: "all" })\` so compiled Elementor CSS is rebuilt.

2. **Capture Desktop Screenshot**:
   Call \`pressagent_capture_screenshot({ page_id: ${pageId}, viewport: "desktop", wait_ms: 2500 })\`.
   Inspect the returned image file using your file inspection tool.
   Verify:
   - Are cards arranged horizontally in rows instead of stacking vertically?
   - Is hero typography bold, clear, and balanced?
   - Is color contrast accessible?

3. **Capture Mobile Screenshot**:
   Call \`pressagent_capture_screenshot({ page_id: ${pageId}, viewport: "mobile", wait_ms: 2500 })\`.
   Verify:
   - Do cards stack neatly on mobile screens without horizontal scrollbars?
   - Are touch targets (buttons) at least 44px tall?

4. **Iterate**:
   If any visual defects are found, adjust settings with \`pressagent_update_widget\` or \`pressagent_add_elementor_section\`, purge cache, and re-capture until flawless.`
            }
          }
        ]
      };
    }

    if (name === "wordpress-plugin-architect") {
      const purpose = args?.plugin_purpose || "Custom WordPress Plugin Development";
      return {
        description: "WordPress Plugin Engineering Standards",
        messages: [
          {
            role: "user",
            content: {
              type: "text",
              text: `# 🛡️ WORDPRESS PLUGIN ENGINEERING STANDARDS (GOD MODE)

Objective: ${purpose}

When generating plugins with \`pressagent_create_plugin\`:
1. **Plugin Headers**: Include Plugin Name, Description, Version (1.0.0), Author, License (GPLv2+).
2. **Security Barrier**: Top line after <?php must be: \`defined('ABSPATH') || exit;\`
3. **Data Sanitization**:
   - Text inputs: \`sanitize_text_field(wp_unslash($_POST['key']))\`
   - Integers: \`absint(...)\`
   - Emails: \`sanitize_email(...)\`
4. **Data Escaping**:
   - HTML output: \`esc_html(...)\`
   - Attributes: \`esc_attr(...)\`
   - URLs: \`esc_url(...)\`
5. **Authorization**: Check \`current_user_can('manage_options')\` before running administrative actions.
6. **Nonces**: Protect forms and AJAX handlers with \`check_admin_referer()\` or \`check_ajax_referer()\`.
7. **Database Safety**: Never concatenate raw input into SQL. Use \`$wpdb->prepare()\`.`
            }
          }
        ]
      };
    }

    throw new Error(`Prompt ${name} not found`);
  });
}
