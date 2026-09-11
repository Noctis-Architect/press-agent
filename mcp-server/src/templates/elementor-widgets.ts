/**
 * PressAgent Elementor Widget Templates, Normalizers, and Layout Recipes
 * 
 * Embedded knowledge base ensuring AI agents generate 100% valid, visual,
 * native Elementor JSON structures without crashing the editor.
 */

export function generateElementorId(): string {
  return Math.random().toString(16).substring(2, 10);
}

/**
 * Normalizes and validates an Elementor node recursively.
 * Auto-repairs missing IDs, handles dimensional units, and guarantees schema integrity.
 */
export function normalizeElementorNode(node: any, isChild = false): any {
  if (!node || typeof node !== "object") {
    throw new Error("Invalid Elementor node: must be an object.");
  }

  // 1. Ensure unique 8-char hex ID
  const id = (typeof node.id === "string" && node.id.trim().length >= 4)
    ? node.id.trim()
    : generateElementorId();

  // 2. Determine & validate elType
  let elType = node.elType;
  if (!elType) {
    if (node.widgetType) {
      elType = "widget";
    } else if (Array.isArray(node.elements)) {
      elType = "container";
    } else {
      elType = "container";
    }
  }

  const validElTypes = ["container", "section", "column", "widget"];
  if (!validElTypes.includes(elType)) {
    throw new Error(
      `Invalid elType "${elType}". Must be one of: ${validElTypes.join(", ")}.`
    );
  }

  // 3. Ensure isInner flag
  const isInner = node.isInner !== undefined ? Boolean(node.isInner) : isChild;

  // 4. Normalize settings
  const rawSettings = (node.settings && typeof node.settings === "object" && !Array.isArray(node.settings))
    ? { ...node.settings }
    : {};

  // Auto-convert any string dimensions e.g. "20px" -> { unit: "px", size: 20 }
  for (const [k, v] of Object.entries(rawSettings)) {
    if (typeof v === "string" && /^-?\d+(\.\d+)?(px|%|em|rem|vh|vw)$/.test(v)) {
      const match = v.match(/^(-?\d+(\.\d+)?)(px|%|em|rem|vh|vw)$/);
      if (match) {
        rawSettings[k] = { unit: match[3], size: parseFloat(match[1]) };
      }
    }
  }

  const normalized: any = {
    id,
    elType,
    isInner,
    settings: rawSettings,
    elements: []
  };

  // 5. Widget-specific properties
  if (elType === "widget") {
    if (!node.widgetType || typeof node.widgetType !== "string") {
      throw new Error(
        `Widget node (id: ${id}) is missing required "widgetType" (e.g. "heading", "text-editor", "button", "image", "icon-box").`
      );
    }
    normalized.widgetType = node.widgetType;
  }

  // 6. Recursively normalize children
  if (Array.isArray(node.elements)) {
    normalized.elements = node.elements.map((child: any) =>
      normalizeElementorNode(child, true)
    );
  }

  return normalized;
}

/**
 * Normalizes entire section_data passed to pressagent_add_elementor_section.
 * Supports single container, array of containers, or { _replace_all: true, elements: [...] }.
 */
export function normalizeSectionData(sectionData: any): any {
  if (!sectionData || typeof sectionData !== "object") {
    throw new Error("section_data must be a valid Elementor container object or array of containers.");
  }

  // If replacing all page elements
  if (sectionData._replace_all === true) {
    if (!Array.isArray(sectionData.elements)) {
      throw new Error("When _replace_all is true, section_data.elements must be an array of containers.");
    }
    return {
      _replace_all: true,
      elements: sectionData.elements.map((node: any) => normalizeElementorNode(node, false))
    };
  }

  // If array of containers
  if (Array.isArray(sectionData)) {
    return sectionData.map((node: any) => normalizeElementorNode(node, false));
  }

  // Single top-level container
  return normalizeElementorNode(sectionData, false);
}

/* ==========================================================================
   NATIVE ELEMENTOR WIDGET TEMPLATES & PRESETS
   ========================================================================== */

export const headingTemplate = {
  type: "heading",
  description: "Heading widget",
  defaultSettings: {
    title: "Add Your Heading Text Here",
    header_size: "h2",
    align: "center"
  }
};

export const textEditorTemplate = {
  type: "text-editor",
  description: "Text Editor widget",
  defaultSettings: {
    editor: "<p>I am text block. Click edit button to change this text.</p>"
  }
};

export const buttonTemplate = {
  type: "button",
  description: "Button widget",
  defaultSettings: {
    text: "Click Here",
    link: { url: "#" },
    align: "center"
  }
};

export const imageTemplate = {
  type: "image",
  description: "Image widget",
  defaultSettings: {
    image: { url: "" },
    image_size: "large"
  }
};

export const elementorTemplates = {
  heading: headingTemplate,
  "text-editor": textEditorTemplate,
  button: buttonTemplate,
  image: imageTemplate
};

export const WIDGET_REFERENCE_GUIDE = `
### 🎨 Native Elementor Widgets Reference (USE THESE INSTEAD OF RAW HTML):

1. **heading**:
   - settings: { title: "Text", header_size: "h1"|"h2"|"h3"|"h4", align: "right"|"left"|"center", title_color: "#1e293b", typography_typography: "custom", typography_font_size: { unit: "px", size: 36 }, typography_font_weight: "700" }
2. **text-editor**:
   - settings: { editor: "<p>Your paragraph text...</p>", align: "right"|"left"|"center", text_color: "#475569" }
3. **button**:
   - settings: { text: "Button Label", link: { url: "https://...", is_external: true }, align: "center", size: "md"|"lg", background_color: "#3b82f6", button_text_color: "#ffffff", border_radius: { unit: "px", top: 8, right: 8, bottom: 8, left: 8, isLinked: true } }
4. **image**:
   - settings: { image: { url: "https://.../img.jpg" }, image_size: "large"|"full", align: "center" }
5. **icon-box**:
   - settings: { selected_icon: { value: "fas fa-star", library: "fa-solid" }, title_text: "Card Title", description_text: "Card description text", position: "top"|"right"|"left", primary_color: "#3b82f6" }
6. **counter**:
   - settings: { starting_number: 0, ending_number: 100, prefix: "", suffix: "+", title: "Happy Clients" }
7. **accordion**:
   - settings: { tabs: [ { tab_title: "Question 1", tab_content: "Answer 1" }, { tab_title: "Question 2", tab_content: "Answer 2" } ] }
8. **icon-list**:
   - settings: { icon_list: [ { text: "Feature 1", selected_icon: { value: "fas fa-check", library: "fa-solid" } } ] }
9. **divider**:
   - settings: { style: "solid", weight: { unit: "px", size: 1 }, color: "#e2e8f0" }
10. **spacer**:
   - settings: { space: { unit: "px", size: 40 } }
`;

export const FLEXBOX_RECIPES_GUIDE = `
### 📐 Elementor Flexbox Container Recipes:

1. **Top-Level Boxed Container**:
\`\`\`json
{
  "elType": "container",
  "isInner": false,
  "settings": {
    "content_width": "boxed",
    "boxed_width": { "unit": "px", "size": 1280 },
    "flex_direction": "column",
    "padding": { "unit": "px", "top": "60", "right": "24", "bottom": "60", "left": "24", "isLinked": false }
  },
  "elements": [ ... ]
}
\`\`\`

2. **Horizontal Cards Row (3-Columns Grid - PREVENTS VERTICAL STACKING BUG)**:
- Parent Container (Row):
\`\`\`json
{
  "elType": "container",
  "isInner": false,
  "settings": {
    "content_width": "boxed",
    "boxed_width": { "unit": "px", "size": 1280 },
    "flex_direction": "row",
    "flex_wrap": "wrap",
    "flex_justify_content": "space-between",
    "flex_align_items": "stretch",
    "flex_gap": { "unit": "px", "size": 24, "column": "24", "row": "24" }
  },
  "elements": [
    {
      "elType": "container",
      "isInner": true,
      "settings": {
        "content_width": "full",
        "width": { "unit": "%", "size": 31 },
        "flex_direction": "column",
        "padding": { "unit": "px", "top": "32", "right": "24", "bottom": "32", "left": "24", "isLinked": false },
        "background_background": "classic",
        "background_color": "#ffffff",
        "border_border": "solid",
        "border_width": { "unit": "px", "top": "1", "right": "1", "bottom": "1", "left": "1", "isLinked": true },
        "border_color": "#e2e8f0",
        "border_radius": { "unit": "px", "top": "16", "right": "16", "bottom": "16", "left": "16", "isLinked": true }
      },
      "elements": [ ...widgets inside card... ]
    }
  ]
}
\`\`\`

3. **Two-Column Hero Split (Left Text, Right Image)**:
- Parent Container: \`flex_direction: "row"\`, \`flex_wrap: "wrap"\`, \`flex_align_items: "center"\`.
- Left Child Container: \`width: { unit: "%", size: 50 }\`, containing heading, text-editor, button.
- Right Child Container: \`width: { unit: "%", size: 50 }\`, containing image widget.
`;

export const WORKFLOW_RULES_GUIDE = `
### ⚡ CRITICAL WORKFLOW RULES FOR AI AGENTS:
1. **NEVER USE RAW HTML OR SHORTCODE WIDGETS** unless explicitly instructed by user. Admins cannot edit raw HTML in Elementor visual editor. Always use native Elementor widgets.
2. **ALWAYS PURGE CACHE AFTER WRITING:** Call \`pressagent_purge_cache({ plugin_slug: "all" })\` immediately after adding sections or updating widgets. Elementor compiles static CSS files in uploads/elementor/css/ that must be invalidated.
3. **HEADLESS VISUAL QA IS MANDATORY:** Call \`pressagent_capture_screenshot({ page_id: <ID>, viewport: "desktop" })\` and \`viewport: "mobile"\`, then inspect the image file to verify styling, alignment, and responsiveness.
4. **CHECK DEBUG LOG FOR ERRORS:** Call \`pressagent_read_debug_log({ lines: 30 })\` to ensure no PHP fatal errors or warnings were triggered.
`;
