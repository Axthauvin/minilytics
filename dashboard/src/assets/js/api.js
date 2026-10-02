/**
 * Minilytics API Client Helper
 */

const Api = {
  base: "/dashboard/src/api",

  async getStats(range = "7d", siteId = "") {
    const targetSite =
      siteId && siteId !== "all"
        ? siteId
        : window.App &&
            window.App.currentSiteId &&
            window.App.currentSiteId !== "all"
          ? window.App.currentSiteId
          : "";
    const params = new URLSearchParams({ range });
    if (targetSite) params.append("site_id", targetSite);
    const res = await fetch(`${this.base}/stats.php?${params.toString()}`);
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    return await res.json();
  },

  async getEvents({
    range = "7d",
    page = 1,
    limit = 50,
    search = "",
    eventName = "",
    sessionId = "",
    siteId = "",
  } = {}) {
    const targetSite =
      siteId && siteId !== "all"
        ? siteId
        : window.App &&
            window.App.currentSiteId &&
            window.App.currentSiteId !== "all"
          ? window.App.currentSiteId
          : "";
    const params = new URLSearchParams({
      range,
      page: page.toString(),
      limit: limit.toString(),
    });
    if (search) params.append("search", search);
    if (eventName) params.append("event_name", eventName);
    if (sessionId) params.append("session_id", sessionId);
    if (targetSite) params.append("site_id", targetSite);

    const res = await fetch(`${this.base}/events.php?${params.toString()}`);
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    return await res.json();
  },

  async getSessions({
    range = "7d",
    page = 1,
    limit = 25,
    search = "",
    siteId = "",
  } = {}) {
    const targetSite =
      siteId && siteId !== "all"
        ? siteId
        : window.App &&
            window.App.currentSiteId &&
            window.App.currentSiteId !== "all"
          ? window.App.currentSiteId
          : "";
    const params = new URLSearchParams({
      range,
      page: page.toString(),
      limit: limit.toString(),
    });
    if (search) params.append("search", search);
    if (targetSite) params.append("site_id", targetSite);

    const res = await fetch(`${this.base}/sessions.php?${params.toString()}`);
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    return await res.json();
  },

  async getSessionDetails(sessionId) {
    const params = new URLSearchParams({ session_id: sessionId });
    const res = await fetch(`${this.base}/sessions.php?${params.toString()}`);
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    return await res.json();
  },

  async getSites() {
    const res = await fetch(`${this.base}/sites.php`);
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    return await res.json();
  },

  async createSite({ id, name, domain }) {
    const res = await fetch(`${this.base}/sites.php`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ id, name, domain }),
    });
    const data = await res.json();
    if (!res.ok || data.error) {
      throw new Error(data.error || `HTTP ${res.status}`);
    }
    return data;
  },

  async deleteSite(id) {
    const res = await fetch(
      `${this.base}/sites.php?id=${encodeURIComponent(id)}`,
      {
        method: "DELETE",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ id }),
      },
    );
    const data = await res.json();
    if (!res.ok || data.error) {
      throw new Error(data.error || `HTTP ${res.status}`);
    }
    return data;
  },

  async getImportProviders() {
    const res = await fetch(`${this.base}/import.php?action=providers`);
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    return await res.json();
  },

  async inspectImport(formData) {
    const res = await fetch(`${this.base}/import.php?action=inspect`, {
      method: "POST",
      body: formData,
    });
    const data = await res.json();
    if (!res.ok || data.error) {
      throw new Error(data.error || `HTTP ${res.status}`);
    }
    return data;
  },

  async submitImport(formData) {
    const res = await fetch(`${this.base}/import.php`, {
      method: "POST",
      body: formData,
    });
    const data = await res.json();
    if (!res.ok || data.error) {
      throw new Error(data.error || `HTTP ${res.status}`);
    }
    return data;
  },
};

window.Api = Api;
