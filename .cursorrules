# PressAgent AI Operations & Elementor Design Guide

This file automatically guides Claude Code, Cursor, Windsurf, and other autonomous agents when interacting with WordPress via PressAgent MCP.

---

## ⛔ Absolute Rules & Prohibitions

1. **NO RAW HTML WIDGETS**: Never insert `widgetType: "html"` or `<style>` blocks for ordinary page elements. Content MUST be created using native Elementor widgets (`heading`, `text-editor`, `button`, `image`, `icon-box`, `counter`, `accordion`). Admins must be able to click and visually edit every element in Elementor's editor.
2. **VALID ELEMENTOR JSON SCHEMA**:
   - Every node must have: `id` (unique 8-char hex, e.g. `c4f82a1b`), `elType` (`container` or `widget`), `isInner` (boolean), `settings` (object), `elements` (array).
   - Widgets must specify `widgetType`.
   - Dimension settings MUST be objects: `{ "unit": "px", "size": 24 }` or `{ "unit": "px", "top": "20", "right": "20", "bottom": "20", "left": "20", "isLinked": true }`. Never raw strings like `"20px"`.
3. **PREVENT VERTICAL STACKING IN CARDS**:
   - Elementor containers default to `flex_direction: column`.
   - For horizontal cards, the parent container MUST have:
     `flex_direction: "row"`, `flex_wrap: "wrap"`, `flex_justify_content: "space-between"`, `flex_gap: { "unit": "px", "size": 24, "column": "24", "row": "24" }`.
   - Each child card container MUST have: `width: { "unit": "%", "size": 31 }` (for 3 columns) and `flex_direction: "column"`.

---

## 🔄 Standard 5-Step Workflow

1. **Discover**: Call `pressagent_list_pages` to locate the target page ID.
2. **Inspect**: Call `pressagent_get_elementor_layout({ page_id })` to understand existing container structure and widget IDs.
3. **Build / Update**:
   - Update existing widget: `pressagent_update_widget({ page_id, widget_id, settings })`
   - Add or rebuild page: `pressagent_add_elementor_section({ page_id, section_data: { _replace_all: true, elements: [...] } })`
4. **Purge Cache**: Call `pressagent_purge_cache({ plugin_slug: "all" })` (MANDATORY — Elementor caches static CSS).
5. **Visual QA Loop**:
   - Desktop: `pressagent_capture_screenshot({ page_id, viewport: "desktop" })`
   - Mobile: `pressagent_capture_screenshot({ page_id, viewport: "mobile" })`
   - Inspect the screenshot images visually to catch stacking bugs, overflow, and contrast issues.
   - Verify health: `pressagent_read_debug_log({ lines: 30 })`

---

## 🛡️ Plugin Scaffolding Standards (God Mode)

When using `pressagent_create_plugin`:
- Must include standard WP header comments (Plugin Name, Description, Version, Author, License).
- Top line after `<?php` must be `defined('ABSPATH') || exit;`.
- Sanitize all inputs (`sanitize_text_field`, `absint`, `wp_unslash`).
- Escape all outputs (`esc_html`, `esc_attr`, `esc_url`).
- Verify nonces and user capabilities (`current_user_can('manage_options')`).
- PressAgent automatically runs `php -l` syntax validation before writing files.
