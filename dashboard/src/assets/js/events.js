/**
 * Minilytics – Events Page Controller
 */

const EventsPage = {
  filters: {
    range: "7d",
    page: 1,
    limit: 50,
    search: "",
    eventName: "all",
    sessionId: "",
  },
  searchDebounce: null,
  eventsMap: {},

  // ── Init ────────────────────────────────────────────────────────────────

  init() {
    this.bindEvents();
  },

  bindEvents() {
    document.getElementById("eventSearch")?.addEventListener("input", (e) => {
      clearTimeout(this.searchDebounce);
      this.searchDebounce = setTimeout(() => {
        this.filters.search = e.target.value.trim();
        this.filters.page = 1;
        this.load();
      }, 300);
    });

    document
      .getElementById("eventTypeFilter")
      ?.addEventListener("change", (e) => {
        this.filters.eventName = e.target.value;
        this.filters.page = 1;
        this.load();
      });

    document.getElementById("eventsPrevPage")?.addEventListener("click", () => {
      if (this.filters.page > 1) {
        this.filters.page--;
        this.load();
      }
    });
    document.getElementById("eventsNextPage")?.addEventListener("click", () => {
      this.filters.page++;
      this.load();
    });

    document
      .getElementById("clearSessionFilterBtn")
      ?.addEventListener("click", () => {
        this.filters.sessionId = "";
        document.getElementById("activeSessionFilterBox").style.display =
          "none";
        this.filters.page = 1;
        this.load();
      });
  },

  // ── Public API ───────────────────────────────────────────────────────────

  filterBySession(sessionId) {
    this.filters.sessionId = sessionId;
    this.filters.page = 1;
    const box = document.getElementById("activeSessionFilterBox");
    if (box) {
      box.style.display = "inline-flex";
      document.getElementById("activeSessionIdLabel").textContent =
        sessionId.substring(0, 10) + "…";
    }
    this.load();
  },

  filterByType(name) {
    this.filters.eventName = name;
    this.filters.page = 1;
    const sel = document.getElementById("eventTypeFilter");
    if (sel) sel.value = name;
    this.load();
  },

  // ── Data loading ─────────────────────────────────────────────────────────

  async load(range, siteId) {
    if (range) this.filters.range = range;
    if (siteId !== undefined) this.filters.siteId = siteId;

    try {
      const data = await Api.getEvents(this.filters);
      this.renderTypeOptions(data.types || []);
      this.renderDistribution(data.types || [], data.total || 0);
      this.renderEvents(data.events || []);
      this.renderPagination(data);
    } catch (err) {
      window.App.displayNoDataMessage(siteId);
      console.error("Error loading events:", err);
    }
  },

  // ── Rendering ────────────────────────────────────────────────────────────

  renderDistribution(types, total) {
    const container = document.getElementById("eventsDistribution");
    const countEl = document.getElementById("eventsTotalCount");
    if (!container) return;

    if (countEl)
      countEl.textContent = `${total.toLocaleString()} event${total === 1 ? "" : "s"}`;

    if (!types.length) {
      container.innerHTML = `<div style="padding: 32px; text-align: center; color: var(--text-muted); font-size: 13px;">No data for this period.</div>`;
      return;
    }

    const COLORS = [
      "#2563eb",
      "#8b5cf6",
      "#06b6d4",
      "#f59e0b",
      "#10b981",
      "#ef4444",
      "#f97316",
      "#ec4899",
      "#64748b",
      "#0ea5e9",
    ];

    const maxCount = types[0].count;
    const active = this.filters.eventName;

    container.innerHTML = types
      .map((t, i) => {
        const pct = maxCount > 0 ? (t.count / maxCount) * 100 : 0;
        const totalPct = total > 0 ? ((t.count / total) * 100).toFixed(1) : "0";
        const color = COLORS[i % COLORS.length];
        const isActive = active !== "all" && active === t.name;

        return `
                <div class="evt-dist-row${isActive ? " evt-dist-row--active" : ""}"
                     onclick="EventsPage.filterByType('${isActive ? "all" : this.esc(t.name)}')"
                     title="${isActive ? "Remove filter" : `Filter: ${this.esc(t.name)}`}">
                    <div class="evt-dist-label">
                        <span class="evt-dist-dot" style="background:${color};"></span>
                        <span class="evt-dist-name">${this.esc(t.name)}</span>
                    </div>
                    <div class="evt-dist-bar-wrap">
                        <div class="evt-dist-bar" style="width:${pct.toFixed(1)}%; background:${color};"></div>
                    </div>
                    <div class="evt-dist-stats">
                        <span class="evt-dist-count">${t.count.toLocaleString()}</span>
                        <span class="evt-dist-pct">${totalPct}%</span>
                    </div>
                </div>`;
      })
      .join("");
  },

  renderTypeOptions(types) {
    const select = document.getElementById("eventTypeFilter");
    if (!select) return;
    const cur = this.filters.eventName || "all";
    select.innerHTML =
      `<option value="all" ${cur === "all" ? "selected" : ""}>All Events</option>` +
      types
        .map(
          (t) =>
            `<option value="${this.esc(t.name)}" ${t.name === cur ? "selected" : ""}>${this.esc(t.name)} (${t.count})</option>`,
        )
        .join("");
  },

  renderEvents(events) {
    const container = document.getElementById("eventsListContainer");
    const badge = document.getElementById("eventsTotalCount");
    if (!container) return;

    if (badge)
      badge.textContent = `${events.length} event${events.length === 1 ? "" : "s"}`;

    if (events.length === 0) {
      container.innerHTML = `
                <div class="empty-state" style="padding: 48px; text-align: center;">
                    <h3 style="font-size: 15px; font-weight: 600; margin-bottom: 4px;">No events recorded</h3>
                    <p style="font-size: 13px; color: var(--text-muted);">No activity matches your current filters.</p>
                </div>`;
      return;
    }

    this.eventsMap = Object.fromEntries(events.map((e) => [e.id, e]));

    const EXCLUDE_KEYS = new Set([
      "browser",
      "os",
      "device",
      "screen",
      "viewport",
      "language",
      "country",
      "country_code",
      "hostname",
      "site_id",
      "session_id",
      "path",
      "title",
      "url",
      "referrer",
      "search",
      "hash",
    ]);

    const rows = events
      .map((e, idx) => {
        const d = e.data || {};
        const isPv = e.name === "pageview";
        const iconSvg = isPv
          ? Icons.get("file-text", { size: 15, color: "#f59e0b" })
          : Icons.get("zap", { size: 15, color: "#f59e0b" });
        const title = isPv ? d.path || "/" : e.name;
        const subtitle = isPv ? d.title || "" : d.path || "";
        const shortSession = (e.session_id || "").substring(0, 8);
        const country = d.country || "";

        // Custom payload for non-pv events
        const customPayload = {};
        if (!isPv && d && typeof d === "object") {
          Object.entries(d).forEach(([k, v]) => {
            if (
              EXCLUDE_KEYS.has(k) ||
              v === null ||
              v === undefined ||
              v === ""
            )
              return;
            customPayload[k] = v;
          });
        }
        const customKeys = Object.keys(customPayload);
        const hasCustom = customKeys.length > 0;
        const propId = `evtProps_${e.id}_${idx}`;

        const metaItems = [
          `<span class="journey-step-dur" style="cursor:pointer;" onclick="EventsPage.filterBySession('${e.session_id}')" title="Filter by session">
                    ${Icons.get("user", { size: 12, color: "#64748b" })}
                    <span>${this.esc(shortSession)}</span>
                 </span>`,
          country
            ? `<span class="journey-step-dur">${Icons.getCountryFlag(d.country_code || "UN", { size: 12 })} <span>${this.esc(country)}</span></span>`
            : "",
          d.browser
            ? `<span class="journey-step-dur">${Icons.getBrowserIcon(d.browser, 13)} <span>${this.esc(d.browser)}</span></span>`
            : "",
          d.os
            ? `<span class="journey-step-dur">${Icons.getOsIcon(d.os, 13)} <span>${this.esc(d.os)}</span></span>`
            : "",
        ]
          .filter(Boolean)
          .join("");

        return `
                <div class="journey-step-item">
                    <div class="journey-step-left">
                        <div class="journey-step-circle">${idx + 1}</div>
                        <div class="journey-step-line"></div>
                    </div>
                    <div class="journey-step-content">
                        <div class="journey-step-top">
                            <div class="journey-step-title-wrap">
                                <span class="journey-step-icon">${iconSvg}</span>
                                <span class="journey-step-path">${this.esc(title)}</span>
                                ${subtitle ? `<span class="journey-step-subtitle font-mono">${this.esc(subtitle)}</span>` : ""}
                            </div>
                            <span class="journey-step-time" title="${e.timestamp}">${e.time_ago || e.timestamp}</span>
                        </div>
                        <div class="journey-step-meta-row">
                            ${metaItems}
                            ${
                              hasCustom
                                ? `
                                <button type="button" class="btn-step-props active" onclick="EventsPage.toggleStepProps('${propId}', this)">
                                    <span class="props-text">Properties (${customKeys.length})</span>
                                    <span class="props-chevron">${Icons.get("chevron-down", { size: 12, color: "#64748b" })}</span>
                                </button>`
                                : ""
                            }
                            <button type="button" class="btn-inspect-json" onclick="EventsPage.inspectEvent(${e.id})">JSON</button>
                        </div>
                        ${
                          hasCustom
                            ? `
                            <div class="step-props-card" id="${propId}">
                                <div class="props-table">
                                    ${customKeys
                                      .map((k) => {
                                        const v = customPayload[k];
                                        const formatted =
                                          typeof v === "object"
                                            ? JSON.stringify(v)
                                            : String(v);
                                        return `<div class="props-row">
                                            <span class="props-key font-mono">${this.esc(k)}</span>
                                            <span class="props-val font-mono">${this.esc(formatted)}</span>
                                        </div>`;
                                      })
                                      .join("")}
                                </div>
                            </div>`
                            : ""
                        }
                    </div>
                </div>`;
      })
      .join("");

    const endMarker = `
            <div class="journey-step-item journey-step-end">
                <div class="journey-step-left">
                    <div class="journey-step-circle journey-circle-dot">
                        <span class="journey-inner-dot"></span>
                    </div>
                </div>
                <div class="journey-step-content" style="padding-bottom:0; display:flex; align-items:center; min-height:28px;">
                    <span class="journey-end-text">${events.length} event${events.length === 1 ? "" : "s"} — page ${this.filters.page}</span>
                </div>
            </div>`;

    container.innerHTML = `<div class="journey-steps-container" style="padding: 20px 24px;">${rows}${endMarker}</div>`;
  },

  toggleStepProps(id, btn) {
    const el = document.getElementById(id);
    if (!el) return;
    const isHidden = window.getComputedStyle(el).display === "none";
    el.style.display = isHidden ? "block" : "none";
    if (btn) btn.classList.toggle("active", isHidden);
  },

  renderPagination(data) {
    const info = document.getElementById("eventsPageInfo");
    const footer = document.getElementById("eventsFooterCount");
    const prev = document.getElementById("eventsPrevPage");
    const next = document.getElementById("eventsNextPage");
    if (info) info.textContent = `Page ${data.page} of ${data.total_pages}`;
    if (footer)
      footer.textContent = `Showing ${(data.events || []).length} of ${data.total} recorded events`;
    if (prev) prev.disabled = data.page <= 1;
    if (next) next.disabled = data.page >= data.total_pages;
  },

  inspectEvent(id) {
    const event = this.eventsMap[id];
    if (!event) return;
    const modal = document.getElementById("payloadModal");
    const jsonBox = document.getElementById("modalJsonContent");
    if (!modal || !jsonBox) return;
    jsonBox.textContent = JSON.stringify(event.data, null, 2);
    modal.classList.add("active");
  },

  esc(str) {
    if (!str) return "";
    return String(str).replace(
      /[&<>"']/g,
      (m) =>
        ({
          "&": "&amp;",
          "<": "&lt;",
          ">": "&gt;",
          '"': "&quot;",
          "'": "&#39;",
        })[m],
    );
  },
};

window.EventsPage = EventsPage;
