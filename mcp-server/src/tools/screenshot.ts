import { WPClient } from "../wp-client.js";
import { z } from "zod";
import { execFile } from "child_process";
import { promisify } from "util";
import fs from "fs";
import path from "path";

const execFileAsync = promisify(execFile);

function findChromiumPath(): string {
  const candidates = [
    "/usr/bin/chromium",
    "/usr/bin/chromium-browser",
    "/usr/bin/google-chrome",
    "/usr/bin/google-chrome-stable"
  ];
  for (const p of candidates) {
    if (fs.existsSync(p)) return p;
  }
  return "chromium";
}

export function registerScreenshotTools(
  tools: any[],
  handlers: Map<string, (args: any) => Promise<any>>,
  client: WPClient
) {
  tools.push({
    name: "pressagent_capture_screenshot",
    description: "Capture a visual screenshot of a WordPress page (desktop, mobile, or tablet) using headless browser to inspect layout, styling, and visual rendering.",
    inputSchema: {
      type: "object",
      properties: {
        page_id: {
          type: "number",
          description: "WordPress page ID to preview (e.g. 20, 105)"
        },
        url: {
          type: "string",
          description: "Relative path (e.g. '/' or '/cart/') or full URL"
        },
        viewport: {
          type: "string",
          enum: ["desktop", "mobile", "tablet"],
          description: "Viewport size: 'desktop' (1280x800), 'mobile' (375x812), or 'tablet' (768x1024). Default is 'desktop'."
        },
        full_page: {
          type: "boolean",
          description: "Whether to capture a tall full-page screenshot (e.g. 1280x2400)"
        },
        wait_ms: {
          type: "number",
          description: "Milliseconds to wait for scripts, fonts, and animations before screenshot. Default is 2500."
        }
      }
    }
  });

  handlers.set("pressagent_capture_screenshot", async (args) => {
    const schema = z.object({
      page_id: z.number().optional(),
      url: z.string().optional(),
      viewport: z.enum(["desktop", "mobile", "tablet"]).optional().default("desktop"),
      full_page: z.boolean().optional().default(false),
      wait_ms: z.number().optional().default(2500)
    });

    const parsed = schema.parse(args || {});
    const wpBaseUrl = process.env.PRESSAGENT_WP_URL || "http://localhost:8000";

    // Determine target URL
    let targetUrl = wpBaseUrl;
    if (parsed.page_id) {
      targetUrl = `${wpBaseUrl.replace(/\/$/, "")}/?p=${parsed.page_id}`;
    } else if (parsed.url) {
      if (parsed.url.startsWith("http://") || parsed.url.startsWith("https://")) {
        // SSRF guard: only allow URLs that belong to the configured WordPress origin.
        const wpOrigin = new URL(wpBaseUrl).origin;
        let candidate: URL;
        try {
          candidate = new URL(parsed.url);
        } catch {
          throw new Error("Invalid url provided.");
        }
        if (candidate.origin !== wpOrigin) {
          throw new Error("Only URLs on the configured WordPress site are allowed.");
        }
        targetUrl = candidate.toString();
      } else {
        const cleanPath = parsed.url.startsWith("/") ? parsed.url : `/${parsed.url}`;
        targetUrl = `${wpBaseUrl.replace(/\/$/, "")}${cleanPath}`;
      }
    }

    // Viewport resolution
    let width = 1280;
    let height = parsed.full_page ? 2400 : 800;
    let userAgent: string | undefined;

    if (parsed.viewport === "mobile") {
      width = 375;
      height = parsed.full_page ? 2200 : 812;
      userAgent = "Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.0 Mobile/15E148 Safari/604.1";
    } else if (parsed.viewport === "tablet") {
      width = 768;
      height = parsed.full_page ? 2400 : 1024;
      userAgent = "Mozilla/5.0 (iPad; CPU OS 16_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.0 Mobile/15E148 Safari/604.1";
    }

    const timestamp = Date.now();
    const outputFilename = `pressagent_${parsed.viewport}_${timestamp}.png`;
    const outputPath = path.join("/tmp", outputFilename);
    const symlinkPath = path.join("/tmp", `pressagent_preview_${parsed.viewport}.png`);

    const chromiumBin = findChromiumPath();
    const chromeArgs = [
      "--headless",
      "--disable-gpu",
      "--no-sandbox",
      "--hide-scrollbars",
      `--virtual-time-budget=${parsed.wait_ms}`,
      `--window-size=${width},${height}`,
      `--screenshot=${outputPath}`
    ];

    if (userAgent) {
      chromeArgs.push(`--user-agent=${userAgent}`);
    }

    chromeArgs.push(targetUrl);

    try {
      await execFileAsync(chromiumBin, chromeArgs, { timeout: 30000 });

      // Create/update friendly symlink
      try {
        if (fs.existsSync(symlinkPath)) {
          fs.unlinkSync(symlinkPath);
        }
        fs.symlinkSync(outputPath, symlinkPath);
      } catch (e) {
        // Ignore symlink failure
      }

      return {
        success: true,
        target_url: targetUrl,
        viewport: parsed.viewport,
        width,
        height,
        image_path: outputPath,
        preview_symlink: symlinkPath,
        message: `Screenshot captured successfully. Use view_file on "${outputPath}" to visually inspect the rendered page layout.`
      };
    } catch (err: any) {
      throw new Error(`Failed to capture screenshot with chromium: ${err.message || err}`);
    }
  });
}
