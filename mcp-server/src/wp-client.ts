import fetch from "node-fetch";

export class WPClient {
  private baseUrl: string;
  private token: string;

  constructor(baseUrl: string, token: string) {
    this.baseUrl = baseUrl.replace(/\/$/, "");
    this.token = token;
  }

  private buildUrl(endpoint: string): string {
    const cleanEndpoint = endpoint.startsWith('/') ? endpoint : `/${endpoint}`;

    if (this.baseUrl.includes('/wp-json') || this.baseUrl.includes('rest_route=')) {
      return `${this.baseUrl}${cleanEndpoint}`;
    }

    const [path, query] = cleanEndpoint.split('?');
    const restParam = `rest_route=${path}`;
    const fullQuery = query ? `?${restParam}&${query}` : `?${restParam}`;
    return `${this.baseUrl}/index.php${fullQuery}`;
  }

  private async request<T>(endpoint: string, method: string = "GET", body?: any): Promise<T> {
    const url = this.buildUrl(endpoint);
    const headers = {
      "Authorization": `Bearer ${this.token}`,
      "Content-Type": "application/json"
    };

    const options: any = {
      method,
      headers
    };

    if (body) {
      options.body = JSON.stringify(body);
    }

    const response = await fetch(url, options);

    if (!response.ok) {
      let errorData;
      try {
        errorData = await response.json();
      } catch {
        errorData = { message: response.statusText };
      }
      throw new Error(`WordPress API Error (${response.status}): ${JSON.stringify(errorData)}`);
    }

    return response.json() as Promise<T>;
  }

  // Pages
  async listPages() {
    return this.request("/pressagent/v1/pages");
  }

  async createPage(title: string, content?: string, status: string = "draft") {
    return this.request("/pressagent/v1/pages", "POST", { title, content, status });
  }

  async deletePage(id: number) {
    return this.request(`/pressagent/v1/pages/${id}`, "DELETE");
  }

  // Elementor
  async getElementorLayout(pageId: number) {
    return this.request(`/pressagent/v1/elementor/${pageId}`);
  }

  async updateWidget(pageId: number, widgetId: string, settings: any) {
    return this.request(`/pressagent/v1/elementor/${pageId}/widget`, "POST", { widget_id: widgetId, settings });
  }

  async addElementorSection(pageId: number, sectionData: any) {
    return this.request(`/pressagent/v1/elementor/${pageId}/section`, "POST", sectionData);
  }

  // Settings
  async readOption(pluginSlug: string, key: string) {
    return this.request(`/pressagent/v1/settings/${pluginSlug}/${key}`);
  }

  async writeOption(pluginSlug: string, key: string, value: any) {
    return this.request(`/pressagent/v1/settings/${pluginSlug}/${key}`, "POST", { value });
  }

  async purgeCache(pluginSlug?: string) {
    return this.request("/pressagent/v1/cache/purge", "POST", { plugin_slug: pluginSlug });
  }

  async getCacheStatus() {
    return this.request("/pressagent/v1/cache/status");
  }

  // Debug Log
  async readDebugLog(lines?: number, level?: string) {
    let url = "/pressagent/v1/debug-log";
    const params = new URLSearchParams();
    if (lines) params.append("lines", lines.toString());
    if (level) params.append("level", level);
    if (params.toString()) url += `?${params.toString()}`;
    return this.request(url);
  }

  async diagnoseError(errorText: string) {
    return this.request("/pressagent/v1/debug-log/diagnose", "POST", { error_text: errorText });
  }

  // Security
  async getSecuritySummary() {
    return this.request("/pressagent/v1/security/summary");
  }

  // Dev
  async createPlugin(name: string, code: string, description?: string, activate: boolean = true) {
    return this.request("/pressagent/v1/code/create-plugin", "POST", { name, code, description, activate });
  }

  async deletePlugin(slug: string) {
    return this.request("/pressagent/v1/code/delete-plugin", "POST", { slug });
  }

  async togglePlugin(slug: string, action: "activate" | "deactivate" = "activate") {
    return this.request("/pressagent/v1/code/toggle-plugin", "POST", { slug, action });
  }

  async runSnippet(code: string) {
    return this.request("/pressagent/v1/code/run", "POST", { code });
  }

  async rollback(snapshotId: string) {
    return this.request(`/pressagent/v1/action-guard/rollback/${snapshotId}`, "POST");
  }
}
