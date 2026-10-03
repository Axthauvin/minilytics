/**
 * Minilytics API Client Helper
 */

const Api = {
  base: "/dashboard/src/api",

  async getStats(range = "7d", siteId = "", customDates = null) {
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
    const dates = customDates || (window.App && window.App.customDates);
    if (range === "custom" && dates) {
      if (dates.from) params.append("from", dates.from);
      if (dates.to) params.append("to", dates.to);
    }
    window.Filters?.appendTo(params);
    const res = await fetch(`${this.base}/stats.php?${params.toString()}`);
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    return await res.json();
  },

  async getAcquisition(range = "7d", siteId = "", customDates = null) {
    const params = new URLSearchParams({ range, site_id: siteId || window.App?.currentSiteId || "" });
    const dates = customDates || window.App?.customDates;
    if (range === "custom" && dates?.from && dates?.to) { params.append("from", dates.from); params.append("to", dates.to); }
    const res = await fetch(`${this.base}/acquisition.php?${params}`); const data = await res.json();
    if (!res.ok || data.error) throw new Error(data.error || `HTTP ${res.status}`); return data;
  },

  async getEvents({
    range = "7d",
    page = 1,
    limit = 50,
    search = "",
    eventName = "",
    sessionId = "",
    siteId = "",
    customDates = null,
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
    const dates = customDates || (window.App && window.App.customDates);
    if (range === "custom" && dates) {
      if (dates.from) params.append("from", dates.from);
      if (dates.to) params.append("to", dates.to);
    }

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
    customDates = null,
    date = "",
    eventName = "",
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
    if (date) params.append("date", date);
    if (eventName && eventName !== "all") params.append("event_name", eventName);
    if (targetSite) params.append("site_id", targetSite);
    const dates = customDates || (window.App && window.App.customDates);
    if (range === "custom" && dates) {
      if (dates.from) params.append("from", dates.from);
      if (dates.to) params.append("to", dates.to);
    }
    window.Filters?.appendTo(params);

    const res = await fetch(`${this.base}/sessions.php?${params.toString()}`);
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    return await res.json();
  },

  async getSessionDetails(sessionId, siteId = "") {
    const params = new URLSearchParams({ session_id: sessionId });
    if (siteId) params.append("site_id", siteId);
    const res = await fetch(`${this.base}/sessions.php?${params.toString()}`);
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    return await res.json();
  },

  async getFunnels({ range = "7d", siteId = "", customDates = null } = {}) {
    const targetSite = siteId || window.App?.currentSiteId || "";
    const params = new URLSearchParams({ range });
    if (targetSite) params.append("site_id", targetSite);
    const dates = customDates || window.App?.customDates;
    if (range === "custom" && dates?.from && dates?.to) {
      params.append("from", dates.from); params.append("to", dates.to);
    }
    const res = await fetch(`${this.base}/funnels.php?${params}`);
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    return res.json();
  },

  async saveFunnel(funnel, siteId = "") {
    const res = await fetch(`${this.base}/funnels.php`, {
      method: "POST", headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ site_id: siteId || window.App?.currentSiteId, ...funnel }),
    });
    const data = await res.json();
    if (!res.ok || data.error) throw new Error(data.error || `HTTP ${res.status}`);
    return data;
  },

  async deleteFunnel(id, siteId = "") {
    const params = new URLSearchParams({ id, site_id: siteId || window.App?.currentSiteId });
    const res = await fetch(`${this.base}/funnels.php?${params}`, { method: "DELETE" });
    const data = await res.json();
    if (!res.ok || data.error) throw new Error(data.error || `HTTP ${res.status}`);
    return data;
  },

  async getSites() {
    const res = await fetch(`${this.base}/sites.php`);
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    return await res.json();
  },

  async getTrackingConfig(id) {
    const res = await fetch(`${this.base}/sites.php?action=tracking-config&id=${encodeURIComponent(id)}`); const data = await res.json();
    if (!res.ok || data.error) throw new Error(data.error || `HTTP ${res.status}`); return data;
  },

  async updateSiteConfig(config) {
    const res = await fetch(`${this.base}/sites.php`, {method:'PATCH', headers:{'Content-Type':'application/json'}, body:JSON.stringify(config)}); const data=await res.json();
    if (!res.ok || data.error) throw new Error(data.error || `HTTP ${res.status}`); return data;
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

  async getUsers() {
    const res = await fetch(`${this.base}/users.php`);
    const data = await res.json().catch(() => ({}));
    if (!res.ok || data.error) {
      throw new Error(data.error || `HTTP ${res.status}`);
    }
    return data;
  },

  async inviteUser(email) {
    const res = await fetch(`${this.base}/users.php`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ email }),
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok || data.error) {
      throw new Error(data.error || `HTTP ${res.status}`);
    }
    return data;
  },

  async updateUserRole(id, role) {
    const res = await fetch(`${this.base}/users.php`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ action: "update_role", id, role }),
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok || data.error) {
      throw new Error(data.error || `HTTP ${res.status}`);
    }
    return data;
  },

  async deleteUser(id) {
    const res = await fetch(`${this.base}/users.php`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ action: "delete", id }),
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok || data.error) {
      throw new Error(data.error || `HTTP ${res.status}`);
    }
    return data;
  },
};

window.Api = Api;
