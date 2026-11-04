import { WPClient } from "../wp-client.js";
import { z } from "zod";
import {
  COLOR_TOKENS,
  SPACING_TOKENS,
  TYPOGRAPHY_TOKENS,
  BORDER_RADIUS_TOKENS,
  FLEX_DIRECTIONS,
  FLEX_ALIGN,
  FLEX_JUSTIFY,
  ALLOWED_WIDGET_TYPES,
  ElementorContainerInputSchema
} from "./elementor-types.js";
import {
  validateAndSanitizeElementorTree,
  transformAndResolveToElementor,
  KitData
} from "./elementor-validator.js";
import { captureScreenshotInternal } from "./screenshot.js";

export function registerPagesTools(tools: any[], handlers: Map<string, (args: any) => Promise<any>>, client: WPClient) {
  // 1. List Pages
  tools.push({
    name: "pressagent_list_pages",
    description: `List all WordPress pages with their IDs, titles, slugs, publish status, and Elementor status.

Use this as the first discovery step to locate target page IDs before inspecting layouts or editing content.`,
    inputSchema: {
      type: "object",
      properties: {}
    }
  });

  handlers.set("pressagent_list_pages", async () => {
    return client.listPages();
  });

  // 2. Create Page
  tools.push({
    name: "pressagent_create_page",
    description: `Create a WordPress page, optionally with an initial token-mode Elementor container (pressagent resolves Kit tokens, saves, purges and returns a screenshot). Without container, design later with pressagent_add_elementor_section.`,
    inputSchema: {
      type: "object",
      properties: {
        title: {
          type: "string",
          description: "Title of the page (e.g. 'درباره ما' or 'About Us')"
        },
        content: {
          type: "string",
          description: "Optional initial HTML/plain-text content (leave empty if designing with Elementor)"
        },
        status: {
          type: "string",
          enum: ["draft", "publish", "private"],
          description: "Publication status: 'publish' (default), 'draft', or 'private'. Draft pages cannot be rendered in screenshots."
        },
        container: {
          type: "object",
          description: `Optional initial token-mode container: {direction, width?, boxed_width?, gap?, padding?, background_color?, elements:[{el_type:"widget"|"container", widget_type?, settings?}]}. Same shape as pressagent_add_elementor_section.container.`
        }
      },
      required: ["title"]
    }
  });

  handlers.set("pressagent_create_page", async (args) => {
    const schema = z.object({
      title: z.string(),
      content: z.string().optional(),
      status: z.enum(["draft", "publish", "private"]).optional().default("publish"),
      container: ElementorContainerInputSchema.optional()
    });
    const parsed = schema.parse(args);

    // 1. Create page with Elementor edit mode enabled
    const res: any = await client.createPage(parsed.title, parsed.content || "", parsed.status);
    const pageId = res.id;

    let screenshotResult: any = null;
    let kitData: KitData | undefined;

    // 2. If an initial layout is provided, validate, resolve, save, purge, and screenshot
    if (parsed.container) {
      try {
        kitData = (await client.getElementorKit()) as KitData;
      } catch {
        // Fallback to default tokens
      }

      validateAndSanitizeElementorTree([parsed.container]);
      const resolved = transformAndResolveToElementor(parsed.container, kitData, false);
      await client.addElementorSection(pageId, {
        _replace_all: true,
        elements: [resolved]
      });
      await client.purgeCache("all").catch(() => {});

      const screenshot = await captureScreenshotInternal({
        page_id: pageId,
        viewport: "desktop",
        wait_ms: 2000
      });

      if (screenshot.success) {
        screenshotResult = {
          image_path: screenshot.image_path,
          preview_symlink: screenshot.preview_symlink,
          target_url: screenshot.target_url,
          instruction: `Use view_file on "${screenshot.image_path}" to visually inspect the new page.`
        };
      }
    }

    return {
      success: true,
      page_id: pageId,
      title: parsed.title,
      status: parsed.status,
      elementor_enabled: true,
      kit: parsed.container ? kitData : undefined,
      design_tokens: parsed.container ? {
        color_tokens: COLOR_TOKENS,
        spacing_tokens: SPACING_TOKENS,
        typography_tokens: TYPOGRAPHY_TOKENS,
        border_radius_tokens: BORDER_RADIUS_TOKENS
      } : undefined,
      screenshot: screenshotResult,
      message: "Page created successfully with Elementor builder enabled."
    };
  });

  // 3. Delete Page
  tools.push({
    name: "pressagent_delete_page",
    description: "Permanently delete a WordPress page and clean up its associated Elementor metadata.",
    inputSchema: {
      type: "object",
      properties: {
        page_id: {
          type: "number",
          description: "ID of the WordPress page to delete"
        }
      },
      required: ["page_id"]
    }
  });

  handlers.set("pressagent_delete_page", async (args) => {
    const schema = z.object({
      page_id: z.number()
    });
    const parsed = schema.parse(args);
    return client.deletePage(parsed.page_id);
  });
}
