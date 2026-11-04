import { z } from "zod";

export const ALLOWED_WIDGET_TYPES = [
  "heading",
  "text-editor",
  "button",
  "image",
  "icon-box",
  "icon-list",
  "counter",
  "progress",
  "accordion",
  "tabs",
  "video",
  "divider",
  "spacer"
] as const;

export type AllowedWidgetType = (typeof ALLOWED_WIDGET_TYPES)[number];

export const COLOR_TOKENS = [
  "primary",
  "secondary",
  "text",
  "accent",
  "muted",
  "white",
  "black",
  "transparent"
] as const;

export type ColorToken = (typeof COLOR_TOKENS)[number];

export const SPACING_TOKENS = [
  "none",
  "xs",
  "sm",
  "md",
  "lg",
  "xl",
  "2xl"
] as const;

export type SpacingToken = (typeof SPACING_TOKENS)[number];

export const TYPOGRAPHY_TOKENS = [
  "primary",
  "secondary",
  "text",
  "accent"
] as const;

export type TypographyToken = (typeof TYPOGRAPHY_TOKENS)[number];

export const BORDER_RADIUS_TOKENS = [
  "none",
  "sm",
  "md",
  "lg",
  "xl",
  "full"
] as const;

export type BorderRadiusToken = (typeof BORDER_RADIUS_TOKENS)[number];

export const FLEX_DIRECTIONS = [
  "column",
  "row",
  "column-reverse",
  "row-reverse"
] as const;

export const FLEX_ALIGN = [
  "start",
  "center",
  "end",
  "stretch"
] as const;

export const FLEX_JUSTIFY = [
  "start",
  "center",
  "end",
  "space-between",
  "space-around",
  "space-evenly"
] as const;

export const HEADER_SIZES = ["h1", "h2", "h3", "h4", "h5", "h6"] as const;
export const TEXT_ALIGN = ["left", "center", "right", "justify"] as const;
export const BUTTON_SIZES = ["xs", "sm", "md", "lg", "xl"] as const;
export const IMAGE_SIZES = ["thumbnail", "medium", "large", "full"] as const;
export const DIVIDER_STYLES = ["solid", "dashed", "dotted", "double"] as const;

// Zod schemas for structured settings
export const WidgetSettingsSchema = z.object({
  // Heading & Text
  title: z.string().optional(),
  header_size: z.enum(HEADER_SIZES).optional(),
  editor: z.string().optional(),
  align: z.enum(TEXT_ALIGN).optional(),

  // Color & Typography Tokens
  color: z.enum(COLOR_TOKENS).optional(),
  typography: z.enum(TYPOGRAPHY_TOKENS).optional(),
  background_color: z.enum(COLOR_TOKENS).optional(),

  // Button specific
  text: z.string().optional(),
  link: z.string().optional(),
  size: z.enum(BUTTON_SIZES).optional(),
  button_type: z.enum(["primary", "secondary", "accent", "default"]).optional(),
  icon: z.string().optional(),

  // Image specific
  url: z.string().optional(),
  alt: z.string().optional(),
  image_size: z.enum(IMAGE_SIZES).optional(),

  // Icon Box specific
  title_text: z.string().optional(),
  description_text: z.string().optional(),
  position: z.enum(["top", "left", "right"]).optional(),

  // Spacer specific
  space: z.enum(SPACING_TOKENS).optional(),

  // Divider specific
  divider_style: z.enum(DIVIDER_STYLES).optional(),
  weight: z.number().min(1).max(20).optional(),

  // Counter specific
  starting_number: z.number().optional(),
  ending_number: z.number().optional(),
  prefix: z.string().optional(),
  suffix: z.string().optional(),

  // List & Tab items
  items: z.array(
    z.object({
      text: z.string().optional(),
      title: z.string().optional(),
      content: z.string().optional(),
      icon: z.string().optional()
    })
  ).optional(),

  // Spacing & Border Tokens
  padding: z.enum(SPACING_TOKENS).optional(),
  margin: z.enum(SPACING_TOKENS).optional(),
  border_radius: z.enum(BORDER_RADIUS_TOKENS).optional()
}).passthrough();

export type WidgetSettings = z.infer<typeof WidgetSettingsSchema>;

export const ElementorWidgetInputSchema = z.object({
  id: z.string().regex(/^[a-f0-9]{8}$/i, "Widget ID must be 8 hex characters").optional(),
  el_type: z.literal("widget").optional().default("widget"),
  widget_type: z.enum(ALLOWED_WIDGET_TYPES),
  settings: WidgetSettingsSchema.default({})
});

export type ElementorWidgetInput = z.infer<typeof ElementorWidgetInputSchema>;

export interface ElementorContainerInput {
  id?: string;
  el_type?: "container";
  direction?: (typeof FLEX_DIRECTIONS)[number];
  width?: "boxed" | "full";
  boxed_width?: number;
  wrap?: "nowrap" | "wrap";
  justify_content?: (typeof FLEX_JUSTIFY)[number];
  align_items?: (typeof FLEX_ALIGN)[number];
  gap?: SpacingToken;
  padding?: SpacingToken;
  margin?: SpacingToken;
  background_color?: ColorToken;
  border_radius?: BorderRadiusToken;
  elements?: Array<ElementorContainerInput | ElementorWidgetInput>;
}

export const ElementorContainerInputSchema: z.ZodType<any> = z.lazy(() =>
  z.object({
    id: z.string().regex(/^[a-f0-9]{8}$/i, "Container ID must be 8 hex characters").optional(),
    el_type: z.literal("container").optional().default("container"),
    direction: z.enum(FLEX_DIRECTIONS).optional().default("column"),
    width: z.enum(["boxed", "full"]).optional().default("boxed"),
    boxed_width: z.number().optional(),
    wrap: z.enum(["nowrap", "wrap"]).optional().default("nowrap"),
    justify_content: z.enum(FLEX_JUSTIFY).optional(),
    align_items: z.enum(FLEX_ALIGN).optional(),
    gap: z.enum(SPACING_TOKENS).optional().default("md"),
    padding: z.enum(SPACING_TOKENS).optional().default("md"),
    margin: z.enum(SPACING_TOKENS).optional().default("none"),
    background_color: z.enum(COLOR_TOKENS).optional(),
    border_radius: z.enum(BORDER_RADIUS_TOKENS).optional(),
    elements: z.array(z.lazy(() => z.union([ElementorWidgetInputSchema, ElementorContainerInputSchema]))).optional().default([])
  }).strict()
);
