import crypto from "crypto";
import {
  ALLOWED_WIDGET_TYPES,
  ColorToken,
  SpacingToken,
  BorderRadiusToken,
  ElementorContainerInput,
  ElementorWidgetInput
} from "./elementor-types.js";

export const SPACING_PIXEL_MAP: Record<SpacingToken, number> = {
  none: 0,
  xs: 8,
  sm: 12,
  md: 16,
  lg: 24,
  xl: 32,
  "2xl": 48
};

export const BORDER_RADIUS_PIXEL_MAP: Record<BorderRadiusToken, number> = {
  none: 0,
  sm: 4,
  md: 8,
  lg: 16,
  xl: 24,
  full: 9999
};

export const DEFAULT_COLOR_FALLBACKS: Record<ColorToken, string> = {
  primary: "#6EC1E4",
  secondary: "#54595F",
  text: "#7A7A7A",
  accent: "#61CE70",
  muted: "#94a3b8",
  white: "#ffffff",
  black: "#0f172a",
  transparent: "transparent"
};

export interface KitData {
  kit_id?: number | null;
  colors?: Record<string, { title?: string; value: string }>;
  typography?: Record<string, { title?: string; font_family?: string; font_weight?: string }>;
  container_width?: number;
  space_between_widgets?: number;
}

export function generateElementorId(): string {
  return crypto.randomBytes(4).toString("hex");
}

export function resolveColorValue(token?: ColorToken, kitData?: KitData): string | undefined {
  if (!token) return undefined;
  if (kitData?.colors && kitData.colors[token]?.value) {
    return kitData.colors[token].value;
  }
  return DEFAULT_COLOR_FALLBACKS[token] || undefined;
}

/**
 * Validates an element tree against PressAgent security and structural rules:
 * 1. Forbidden widget types (html, shortcode).
 * 2. Script and Style tag injections.
 * 3. Element IDs (format & uniqueness).
 * 4. Nesting rules.
 * 5. Dimension structures.
 */
export function validateAndSanitizeElementorTree(
  elements: any[],
  seenIds: Set<string> = new Set(),
  parentType: string = "root"
): any[] {
  if (!Array.isArray(elements)) {
    throw new Error("Invalid layout structure: elements must be an array.");
  }

  return elements.map((el) => {
    if (!el || typeof el !== "object") {
      throw new Error("Invalid element: each element in layout must be an object.");
    }

    let elType = el.el_type || el.elType;
    if (!elType) {
      if (el.widget_type || el.widgetType) {
        elType = "widget";
      } else {
        elType = "container";
      }
      el.el_type = elType;
    }
    if (!["container", "section", "column", "widget"].includes(elType)) {
      throw new Error(
        `Invalid element structure: "elType" must be one of "container", "section", "column", or "widget". Received: "${elType}".`
      );
    }

    // Generate or validate ID
    let id = el.id;
    if (!id) {
      id = generateElementorId();
      el.id = id;
    } else {
      if (typeof id !== "string" || !/^[a-f0-9]{8}$/i.test(id)) {
        throw new Error(`Invalid element ID: "${id}". Elementor requires an 8-character hex string.`);
      }
      if (seenIds.has(id)) {
        throw new Error(`Duplicate element ID: "${id}". Every element on the page must have a unique ID.`);
      }
    }
    seenIds.add(id);

    // Widget specific validation
    if (elType === "widget") {
      const widgetType = el.widget_type || el.widgetType;
      if (!widgetType) {
        throw new Error(`Widget missing "widget_type". Every widget must declare its type.`);
      }

      // 1. Strict forbidden widget check
      if (widgetType === "html" || widgetType === "shortcode") {
        throw new Error(
          `Security violation: widgetType "${widgetType}" is strictly forbidden in PressAgent. Native Elementor widgets only.`
        );
      }

      if (!ALLOWED_WIDGET_TYPES.includes(widgetType as any)) {
        throw new Error(
          `Unsupported widgetType "${widgetType}". Allowed widgets: ${ALLOWED_WIDGET_TYPES.join(", ")}.`
        );
      }

      // Nesting rule: widget cannot contain children
      if (Array.isArray(el.elements) && el.elements.length > 0) {
        throw new Error(`Nesting violation: Widget (${widgetType}#${id}) cannot have child elements.`);
      }

      // Nesting rule: widget cannot be direct child of section in legacy mode
      if (parentType === "section") {
        throw new Error(
          `Nesting violation: Widget (${widgetType}#${id}) cannot be directly placed under a "section". Legacy Elementor requires a "column" between a "section" and a "widget".`
        );
      }

      // 2. Script & style check in settings
      const settingsStr = JSON.stringify(el.settings || {});
      if (/<script\b|<style\b/i.test(settingsStr)) {
        throw new Error(
          `Security violation: Raw <script> or <style> tags are not allowed in widget settings (Widget #${id}). Use native Elementor controls.`
        );
      }
    }

    // Container / Section nesting validation
    if (elType === "container" || elType === "section" || elType === "column") {
      if (el.elements && Array.isArray(el.elements)) {
        el.elements = validateAndSanitizeElementorTree(el.elements, seenIds, elType);
      } else {
        el.elements = [];
      }
    }

    return el;
  });
}

/**
 * Transforms high-level container & widget inputs into Elementor's native JSON representation,
 * resolving tokens from kitData.
 */
export function transformAndResolveToElementor(
  element: ElementorContainerInput | ElementorWidgetInput | any,
  kitData?: KitData,
  isInner: boolean = false
): any {
  const elType = element.el_type || element.elType || "container";
  const id = element.id || generateElementorId();

  if (elType === "widget") {
    const widgetType = element.widget_type || element.widgetType;
    const rawSettings = element.settings || {};

    const settings: Record<string, any> = {};
    const globals: Record<string, string> = {};

    // Heading
    if (widgetType === "heading") {
      settings.title = rawSettings.title || "";
      settings.header_size = rawSettings.header_size || "h2";
      if (rawSettings.align) settings.align = rawSettings.align;
      if (rawSettings.color) {
        const hex = resolveColorValue(rawSettings.color, kitData);
        if (hex) settings.title_color = hex;
        if (["primary", "secondary", "text", "accent"].includes(rawSettings.color)) {
          globals.title_color = `globals/colors?id=${rawSettings.color}`;
        }
      }
      if (rawSettings.typography && ["primary", "secondary", "text", "accent"].includes(rawSettings.typography)) {
        globals.typography_typography = `globals/typography?id=${rawSettings.typography}`;
      }
    }

    // Text Editor
    else if (widgetType === "text-editor") {
      settings.editor = rawSettings.editor || "";
      if (rawSettings.align) settings.align = rawSettings.align;
      if (rawSettings.color) {
        const hex = resolveColorValue(rawSettings.color, kitData);
        if (hex) settings.text_color = hex;
        if (["primary", "secondary", "text", "accent"].includes(rawSettings.color)) {
          globals.text_color = `globals/colors?id=${rawSettings.color}`;
        }
      }
    }

    // Button
    else if (widgetType === "button") {
      settings.text = rawSettings.text || "Click Here";
      settings.link = typeof rawSettings.link === "string" ? { url: rawSettings.link } : rawSettings.link || { url: "#" };
      if (rawSettings.size) settings.size = rawSettings.size;
      if (rawSettings.align) settings.align = rawSettings.align;
      if (rawSettings.button_type) settings.button_type = rawSettings.button_type;
      if (rawSettings.icon) {
        settings.selected_icon = { value: rawSettings.icon, library: "fa-solid" };
      }
      if (rawSettings.color) {
        const hex = resolveColorValue(rawSettings.color, kitData);
        if (hex) settings.button_text_color = hex;
      }
      if (rawSettings.background_color) {
        const hex = resolveColorValue(rawSettings.background_color, kitData);
        if (hex) {
          settings.background_color = hex;
          if (["primary", "secondary", "text", "accent"].includes(rawSettings.background_color)) {
            globals.background_color = `globals/colors?id=${rawSettings.background_color}`;
          }
        }
      }
      if (rawSettings.border_radius) {
        const r = BORDER_RADIUS_PIXEL_MAP[rawSettings.border_radius as BorderRadiusToken] ?? 4;
        settings.border_radius = { unit: "px", top: `${r}`, right: `${r}`, bottom: `${r}`, left: `${r}`, isLinked: true };
      }
    }

    // Image
    else if (widgetType === "image") {
      settings.image = {
        url: rawSettings.url || (rawSettings.image && rawSettings.image.url) || "",
        alt: rawSettings.alt || ""
      };
      settings.image_size = rawSettings.image_size || "large";
      if (rawSettings.align) settings.align = rawSettings.align;
    }

    // Icon Box
    else if (widgetType === "icon-box") {
      settings.title_text = rawSettings.title_text || "";
      settings.description_text = rawSettings.description_text || "";
      settings.position = rawSettings.position || "top";
      if (rawSettings.icon) {
        settings.selected_icon = { value: rawSettings.icon, library: "fa-solid" };
      }
      if (rawSettings.color) {
        const hex = resolveColorValue(rawSettings.color, kitData);
        if (hex) settings.primary_color = hex;
      }
    }

    // Spacer
    else if (widgetType === "spacer") {
      const spToken = (rawSettings.space as SpacingToken) || "md";
      const px = SPACING_PIXEL_MAP[spToken] ?? 20;
      settings.space = { unit: "px", size: px };
    }

    // Divider
    else if (widgetType === "divider") {
      settings.style = rawSettings.divider_style || "solid";
      settings.weight = { unit: "px", size: rawSettings.weight || 1 };
      if (rawSettings.color) {
        const hex = resolveColorValue(rawSettings.color, kitData);
        if (hex) settings.color = hex;
      }
    }

    // Icon List
    else if (widgetType === "icon-list") {
      settings.icon_list = (rawSettings.items || []).map((item: any) => ({
        text: item.text || "",
        selected_icon: item.icon ? { value: item.icon, library: "fa-solid" } : { value: "fas fa-check", library: "fa-solid" }
      }));
    }

    // Video
    else if (widgetType === "video") {
      settings.youtube_url = rawSettings.url || "";
    }

    // Counter
    else if (widgetType === "counter") {
      settings.starting_number = rawSettings.starting_number || 0;
      settings.ending_number = rawSettings.ending_number || 100;
      if (rawSettings.prefix) settings.prefix = rawSettings.prefix;
      if (rawSettings.suffix) settings.suffix = rawSettings.suffix;
      if (rawSettings.title) settings.title = rawSettings.title;
    }

    // Accordion / Tabs
    else if (widgetType === "accordion" || widgetType === "tabs") {
      const itemsKey = widgetType === "accordion" ? "tabs" : "tabs";
      settings[itemsKey] = (rawSettings.items || []).map((item: any) => ({
        tab_title: item.title || "Tab Title",
        tab_content: item.content || ""
      }));
    }

    // Pass through any other valid settings
    for (const [k, v] of Object.entries(rawSettings)) {
      if (!(k in settings) && k !== "color" && k !== "typography" && k !== "background_color") {
        settings[k] = v;
      }
    }

    if (Object.keys(globals).length > 0) {
      settings.__globals__ = globals;
    }

    return {
      id,
      elType: "widget",
      widgetType,
      isInner: false,
      settings,
      elements: []
    };
  }

  // Container
  const containerSettings: Record<string, any> = {
    content_width: element.width || "boxed",
    flex_direction: element.direction || "column",
    flex_wrap: element.wrap || "nowrap"
  };

  if (element.width === "boxed" && element.boxed_width) {
    containerSettings.boxed_width = { unit: "px", size: element.boxed_width };
  }

  if (element.justify_content) {
    containerSettings.flex_justify_content = element.justify_content;
  }
  if (element.align_items) {
    containerSettings.flex_align_items = element.align_items;
  }

  if (element.gap) {
    const gapPx = SPACING_PIXEL_MAP[element.gap as SpacingToken] ?? 16;
    containerSettings.flex_gap = { unit: "px", size: gapPx, column: `${gapPx}`, row: `${gapPx}` };
  }

  if (element.padding) {
    const padPx = SPACING_PIXEL_MAP[element.padding as SpacingToken] ?? 16;
    containerSettings.padding = {
      unit: "px",
      top: `${padPx}`,
      right: `${padPx}`,
      bottom: `${padPx}`,
      left: `${padPx}`,
      isLinked: true
    };
  }

  if (element.margin && element.margin !== "none") {
    const marPx = SPACING_PIXEL_MAP[element.margin as SpacingToken] ?? 0;
    containerSettings.margin = {
      unit: "px",
      top: `${marPx}`,
      right: `${marPx}`,
      bottom: `${marPx}`,
      left: `${marPx}`,
      isLinked: true
    };
  }

  if (element.background_color) {
    const bgHex = resolveColorValue(element.background_color, kitData);
    if (bgHex) {
      containerSettings.background_background = "classic";
      containerSettings.background_color = bgHex;
    }
  }

  if (element.border_radius && element.border_radius !== "none") {
    const radPx = BORDER_RADIUS_PIXEL_MAP[element.border_radius as BorderRadiusToken] ?? 8;
    containerSettings.border_radius = {
      unit: "px",
      top: `${radPx}`,
      right: `${radPx}`,
      bottom: `${radPx}`,
      left: `${radPx}`,
      isLinked: true
    };
  }

  const childElements = (element.elements || []).map((child: any) =>
    transformAndResolveToElementor(child, kitData, true)
  );

  return {
    id,
    elType: "container",
    isInner,
    settings: containerSettings,
    elements: childElements
  };
}
