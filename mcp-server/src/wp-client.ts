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
      options.body = typeof body === "string" ? body : JSON.stringify(body);
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
  async getElementorKit() {
    return this.request("/pressagent/v1/elementor/kit");
  }

  async getElementorLayout(pageId: number) {
    return this.request(`/pressagent/v1/elementor/${pageId}`);
  }

  async updateWidget(pageId: number, widgetId: string, settings: any) {
    return this.request(`/pressagent/v1/elementor/${pageId}/widget`, "POST", { widget_id: widgetId, settings });
  }

  async addElementorSection(pageId: number, sectionData: any) {
    return this.request(`/pressagent/v1/elementor/${pageId}/section`, "POST", sectionData);
  }

  // Performance
  async performanceAudit() {
    return this.request("/pressagent/v1/performance/audit");
  }

  async performancePage(pageId?: number, url?: string) {
    const params = new URLSearchParams();
    if (pageId) params.set("page_id", String(pageId));
    if (url) params.set("url", url);
    const query = params.toString();
    return this.request(`/pressagent/v1/performance/page${query ? `?${query}` : ""}`);
  }

  async performanceFix(fixId: string, params: Record<string, any>, confirm: boolean) {
    return this.request("/pressagent/v1/performance/fix", "POST", {
      fix_id: fixId,
      confirm,
      ...params
    });
  }

  // WordPress core settings
  async getWpSettings(groups: string[] = []) {
    const query = groups.length ? `?groups=${encodeURIComponent(groups.join(","))}` : "";
    return this.request(`/pressagent/v1/wp-settings${query}`);
  }

  async setWpSettings(values: Record<string, any>, confirm: boolean) {
    return this.request("/pressagent/v1/wp-settings", "POST", { values, confirm });
  }

  async rollbackWpSetting(snapshotId: string) {
    return this.request("/pressagent/v1/wp-settings/rollback", "POST", { snapshot_id: snapshotId });
  }

  // Plugin lifecycle
  async listPlugins(includeInactive: boolean = true) {
    return this.request(`/pressagent/v1/plugins?include_inactive=${includeInactive ? "1" : "0"}`);
  }

  async installPlugin(slug: string, activate: boolean = false) {
    return this.request("/pressagent/v1/plugins/install", "POST", { slug, activate });
  }

  async searchPlugins(query: string, limit: number = 10) {
    return this.request(`/pressagent/v1/plugins/search?q=${encodeURIComponent(query)}&limit=${limit}`);
  }

  async togglePluginByIdentifier(plugin: string, action: "activate" | "deactivate") {
    return this.request("/pressagent/v1/plugins/toggle", "POST", { plugin, action });
  }

  async updatePlugin(plugin: string) {
    return this.request("/pressagent/v1/plugins/update", "POST", { plugin });
  }

  async getPluginCatalog(plugin: string) {
    return this.request(`/pressagent/v1/plugins/${encodeURIComponent(plugin)}/settings`);
  }

  async getPluginSettings(plugin: string, ids: string[], fields: string[]) {
    const params = new URLSearchParams();
    if (ids.length) params.set("ids", ids.join(","));
    if (fields.length) params.set("fields", fields.join(","));
    const query = params.toString();
    return this.request(`/pressagent/v1/plugins/${encodeURIComponent(plugin)}/settings${query ? `?${query}` : ""}`);
  }

  async setPluginSettings(plugin: string, values: Record<string, any>) {
    return this.request(`/pressagent/v1/plugins/${encodeURIComponent(plugin)}/settings`, "POST", { values });
  }

  async restorePluginSetting(snapshotId: string) {
    return this.request("/pressagent/v1/plugins/settings/restore", "POST", { snapshot_id: snapshotId });
  }

  async runPluginAction(plugin: string, action: string, params: Record<string, any>, confirm: boolean) {
    return this.request(`/pressagent/v1/plugins/${encodeURIComponent(plugin)}/action`, "POST", {
      action,
      params,
      confirm
    });
  }

  // SEO
  async getSeoProviders() {
    return this.request("/pressagent/v1/seo/providers");
  }

  async getSeoMeta(objectType: "post" | "term", objectId: number, taxonomy: string = "", provider: string = "auto") {
    const params = new URLSearchParams();
    if (taxonomy) params.set("taxonomy", taxonomy);
    params.set("provider", provider);
    return this.request(`/pressagent/v1/seo/${objectType}/${objectId}?${params.toString()}`);
  }

  async setSeoMeta(
    objectType: "post" | "term",
    objectId: number,
    fields: Record<string, any>,
    taxonomy: string = "",
    provider: string = "auto"
  ) {
    return this.request(`/pressagent/v1/seo/${objectType}/${objectId}`, "POST", {
      fields,
      taxonomy,
      provider
    });
  }

  // Forms
  async listForms(provider: string = "auto") {
    return this.request(`/pressagent/v1/forms?provider=${encodeURIComponent(provider)}`);
  }

  async getForm(formId: number, provider: string = "auto", includeData: boolean = true) {
    return this.request(`/pressagent/v1/forms/${formId}?provider=${encodeURIComponent(provider)}&include_data=${includeData ? "1" : "0"}`);
  }

  async saveCf7Form(data: Record<string, any>) {
    return this.request("/pressagent/v1/forms/cf7", "POST", data);
  }

  async updateCf7Form(formId: number, data: Record<string, any>) {
    return this.request(`/pressagent/v1/forms/cf7/${formId}`, "POST", data);
  }

  async deleteCf7Form(formId: number, confirm: boolean) {
    return this.request(`/pressagent/v1/forms/cf7/${formId}/delete`, "POST", { confirm });
  }

  // WooCommerce
  async listProducts(args: { limit?: number; page?: number; search?: string; status?: string }) {
    const params = new URLSearchParams();
    if (args.limit) params.set("limit", String(args.limit));
    if (args.page) params.set("page", String(args.page));
    if (args.search) params.set("search", args.search);
    if (args.status) params.set("status", args.status);
    const query = params.toString();
    return this.request(`/pressagent/v1/commerce/products${query ? `?${query}` : ""}`);
  }

  async getProduct(productId: number, includeDescription: boolean = false) {
    return this.request(`/pressagent/v1/commerce/products/${productId}?include_description=${includeDescription ? "1" : "0"}`);
  }

  async createProduct(data: Record<string, any>) {
    return this.request("/pressagent/v1/commerce/products", "POST", data);
  }

  async updateProduct(productId: number, data: Record<string, any>) {
    return this.request(`/pressagent/v1/commerce/products/${productId}`, "POST", data);
  }

  async listOrders(args: { limit?: number; page?: number; status?: string }) {
    const params = new URLSearchParams();
    if (args.limit) params.set("limit", String(args.limit));
    if (args.page) params.set("page", String(args.page));
    if (args.status) params.set("status", args.status);
    const query = params.toString();
    return this.request(`/pressagent/v1/commerce/orders${query ? `?${query}` : ""}`);
  }

  async getOrder(orderId: number) {
    return this.request(`/pressagent/v1/commerce/orders/${orderId}`);
  }

  async updateOrderStatus(orderId: number, status: string, note: string = "", customerNote: boolean = false) {
    return this.request(`/pressagent/v1/commerce/orders/${orderId}/status`, "POST", {
      status,
      note,
      customer_note: customerNote
    });
  }

  async addOrderNote(orderId: number, note: string, customerNote: boolean = false) {
    return this.request(`/pressagent/v1/commerce/orders/${orderId}/note`, "POST", {
      note,
      customer_note: customerNote
    });
  }

  // Appearance / Child theme
  async getThemeStatus() {
    return this.request("/pressagent/v1/theme/status");
  }

  async listThemeFiles(limit?: number) {
    const query = limit ? `?limit=${limit}` : "";
    return this.request(`/pressagent/v1/theme/files${query}`);
  }

  async readThemeFile(path: string) {
    return this.request(`/pressagent/v1/theme/file?path=${encodeURIComponent(path)}`);
  }

  async writeThemeFile(path: string, content: string) {
    return this.request("/pressagent/v1/theme/file", "POST", { path, content });
  }

  async createChildTheme(parentSlug: string, slug: string, activate: boolean, overwrite: boolean) {
    return this.request("/pressagent/v1/theme/create-child", "POST", {
      parent_slug: parentSlug,
      slug,
      activate,
      overwrite
    });
  }

  async switchTheme(slug: string) {
    return this.request("/pressagent/v1/theme/switch", "POST", { slug });
  }

  async setAppearanceMode(mode: string) {
    return this.request("/pressagent/v1/theme/mode", "POST", { mode });
  }

  async setupChildTheme(mode?: string) {
    return this.request("/pressagent/v1/theme/setup", "POST", { mode });
  }

  async listThemeBackups() {
    return this.request("/pressagent/v1/theme/backups");
  }

  async restoreThemeBackup(backupId: string) {
    return this.request("/pressagent/v1/theme/restore", "POST", { backup_id: backupId });
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
