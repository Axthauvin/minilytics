/**
 * Minilytics – Events Page Controller
 */

const EventsPage = {
  filters: {
    range: "7d",
    page: 1,
    limit: 50,
    eventName: "all",
    sessionId: "",
  },
  eventsMap: {},

  // ── Init ────────────────────────────────────────────────────────────────

  init() {
    this.trendChart = new MinilyticsChart("eventsTrendChart", "eventsTrendTooltip", {
      activeSeries: ["events"],
      sortTooltipMetrics: true,
      hideZeroTooltipMetrics: true,
      lockTooltipOnClick: true,
      highlightAnomalies: true,
      onPointClick: (item) => this.openSessionsForPoint(item),
      onTooltipMetricClick: (item, seriesKey) => this.openSessionsForPoint(item, seriesKey),
      seriesConfig: {
        events: {
          key: "events", label: "Events", singular: "event", color: "#f59e0b",
          gradientStart: "rgba(245, 158, 11, 0.24)", gradientEnd: "rgba(245, 158, 11, 0.03)", lineWidth: 2.75,
        },
      },
    });
    this.bindEvents();
  },

  bindEvents() {
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

    document.addEventListener("click", (event) => {
      const combobox = document.getElementById("eventsSeriesCombobox");
      const menu = document.getElementById("eventsSeriesMenu");
      if (combobox && menu && !combobox.contains(event.target) && !menu.contains(event.target)) {
        this.setSeriesMenuOpen(false);
      }
    });
    window.addEventListener("resize", () => this.positionSeriesMenu());
    document.addEventListener("scroll", () => this.positionSeriesMenu(), true);
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

  /** Open the sessions recorded on the day (or hour) represented by a chart point. */
  openSessionsForPoint(item, seriesKey = "") {
    const timestamp = Number(item?.timestamp);
    if (!Number.isFinite(timestamp)) return;
    const day = new Date(timestamp * 1000).toISOString().slice(0, 10);
    const sessions = window.SessionsPage;
    if (sessions?.filters) {
      sessions.filters.date = day;
      sessions.filters.eventName = seriesKey
        ? this.trendChart?.seriesConfig?.[seriesKey]?.label || "all"
        : "all";
      sessions.filters.page = 1;
    }
    const dateInput = document.getElementById("sessionDateFilter");
    if (dateInput) window.SessionsPage?.datePicker?.setValue(day);
    const eventSelect = document.getElementById("sessionEventFilter");
    if (eventSelect && sessions?.filters?.eventName) eventSelect.value = sessions.filters.eventName;
    const clearButton = document.getElementById("clearDateFilterBtn");
    if (clearButton) clearButton.style.display = "inline-flex";
    window.Filters?.openSessions();
  },

  // ── Data loading ─────────────────────────────────────────────────────────

  async load(range, siteId, customDates) {
    if (range) this.filters.range = range;
    if (customDates !== undefined) this.filters.customDates = customDates;
    const activeSite = siteId || window.App?.currentSiteId || this.filters.siteId || "";
    if (activeSite) this.filters.siteId = activeSite;

    try {
      if (window.App && typeof window.App.setLoading === "function") {
        window.App.setLoading(true, "Loading events stream...");
      }

      const data = await Api.getEvents(this.filters);
      this.renderInsights(data.total || 0, data.types || [], data.chart_data || [], data.series || []);
      this.renderTrend(data.chart_data || [], data.series || []);
      this.renderEvents(data.events || []);
      this.renderPagination(data);
    } catch (err) {
      window.App?.displayNoDataMessage(siteId);
      console.error("Error loading events:", err);
    } finally {
      if (window.App && typeof window.App.setLoading === "function") {
        window.App.setLoading(false);
      }
    }
  },

  // ── Rendering ────────────────────────────────────────────────────────────

  renderInsights(total, types, points, series) {
    const container = document.getElementById("eventInsightsStrip");
    if (!container) return;
    const topEvent = types[0];
    let peak = null;
    const namesByKey = new Map(series.map((item) => [item.key, item.name]));
    points.forEach((point) => {
      series.forEach((item) => {
        const count = Number(point[item.key]) || 0;
        if (!peak || count > peak.count) {
          peak = { count, name: namesByKey.get(item.key) || item.name, label: point.full_label || point.label || "" };
        }
      });
    });

    const metrics = [
      { label: "Events tracked", value: Number(total).toLocaleString(), detail: "in this period" },
      { label: "Event types", value: types.length.toLocaleString(), detail: "distinct series" },
      topEvent
        ? { label: "Top event", value: Number(topEvent.count).toLocaleString(), detail: topEvent.name }
        : { label: "Top event", value: "—", detail: "No events" },
      peak && peak.count > 0
        ? { label: "Largest peak", value: peak.count.toLocaleString(), detail: `${peak.name} · ${peak.label}` }
        : { label: "Largest peak", value: "—", detail: "No events" },
    ];
    container.innerHTML = metrics.map((metric) => `
      <div class="event-insight">
        <span class="event-insight-label">${this.esc(metric.label)}</span>
        <strong class="event-insight-value">${this.esc(metric.value)}</strong>
        <span class="event-insight-detail" title="${this.esc(metric.detail)}">${this.esc(metric.detail)}</span>
      </div>`).join("");
  },

  renderTrend(points, series) {
    const label = document.getElementById("eventsTrendLabel");
    const palette = ["#f59e0b", "#2563eb", "#8b5cf6", "#10b981", "#ef4444", "#06b6d4", "#ec4899", "#84cc16", "#f97316", "#6366f1"];
    const available = new Set(series.map((item) => item.key));
    const isFirstRender = this.activeSeries === undefined;
    const previous = this.activeSeries || available;
    const active = [...previous].filter((key) => available.has(key));
    // “All events” is an intent, not the finite list returned for the last
    // range. New series must therefore be selected when the range changes.
    this.activeSeries = (isFirstRender || this.allSeriesSelected)
      ? new Set(available)
      : new Set(active);
    if (isFirstRender) this.allSeriesSelected = true;
    if (label) label.textContent = `${series.length} event series monitored`;
    this.trendPoints = points;
    this.trendSeries = series;
    this.trendPalette = palette;

    this.trendChart.seriesConfig = Object.fromEntries(series.map((item, index) => [item.key, {
      key: item.key, label: item.name, singular: "event", color: palette[index % palette.length],
      gradientStart: "rgba(255, 255, 255, 0)", gradientEnd: "rgba(255, 255, 255, 0)", lineWidth: 2.4,
    }]));
    this.trendChart?.setData(points, [...this.activeSeries]);
    this.renderSeriesCombobox();
  },

  renderSeriesCombobox() {
    const trigger = document.getElementById("eventsSeriesTrigger");
    const search = document.getElementById("eventsSeriesSearch");
    const selectAll = document.getElementById("eventsSeriesSelectAll");
    const clear = document.getElementById("eventsSeriesClear");
    if (!trigger || !search || !selectAll || !clear) return;

    trigger.onclick = () => this.setSeriesMenuOpen(trigger.getAttribute("aria-expanded") !== "true");
    search.oninput = () => this.renderSeriesOptions(search.value);
    selectAll.onclick = () => {
      this.activeSeries = new Set(this.trendSeries.map((item) => item.key));
      this.allSeriesSelected = true;
      this.applySeriesSelection(search.value);
    };
    clear.onclick = () => {
      this.activeSeries = new Set();
      this.allSeriesSelected = false;
      this.applySeriesSelection(search.value);
    };
    this.updateSeriesSummary();
    this.renderSeriesOptions(search.value);
  },

  renderSeriesOptions(query = "") {
    const options = document.getElementById("eventsSeriesOptions");
    if (!options) return;
    const normalizedQuery = query.trim().toLowerCase();
    const matches = this.trendSeries.filter((item) => item.name.toLowerCase().includes(normalizedQuery));
    options.innerHTML = matches.length
      ? matches.map((item, index) => {
        const seriesIndex = this.trendSeries.findIndex((series) => series.key === item.key);
        const color = this.trendPalette[seriesIndex % this.trendPalette.length];
        return `<label class="event-series-option">
          <input type="checkbox" value="${this.esc(item.key)}" ${this.activeSeries.has(item.key) ? "checked" : ""}>
          <span class="chart-series-dot" style="background:${color}"></span>
          <span class="event-series-option-name">${this.esc(item.name)}</span>
          <span class="event-series-option-count">${item.count}</span>
        </label>`;
      }).join("")
      : '<p class="event-series-empty">No matching events</p>';
    options.querySelectorAll("input[type=checkbox]").forEach((input) => input.addEventListener("change", () => {
      if (input.checked) this.activeSeries.add(input.value);
      else this.activeSeries.delete(input.value);
      this.applySeriesSelection(query);
    }));
  },

  applySeriesSelection(query = "") {
    this.allSeriesSelected = this.trendSeries.length > 0
      && this.activeSeries.size === this.trendSeries.length;
    this.trendChart?.setData(this.trendPoints || [], [...this.activeSeries]);
    this.updateSeriesSummary();
    this.renderSeriesOptions(query);
  },

  updateSeriesSummary() {
    const summary = document.getElementById("eventsSeriesSummary");
    if (!summary) return;
    const total = this.trendSeries?.length || 0;
    const selected = this.activeSeries?.size || 0;
    if (selected === total) {
      summary.textContent = `All events (${total})`;
    } else if (selected === 1) {
      const selectedEvent = this.trendSeries.find((item) => this.activeSeries.has(item.key));
      summary.textContent = selectedEvent?.name || "1 event selected";
    } else if (selected === 0) {
      summary.textContent = "No events selected";
    } else {
      summary.textContent = `${selected} events selected`;
    }
  },

  setSeriesMenuOpen(open) {
    const combobox = document.getElementById("eventsSeriesCombobox");
    const trigger = document.getElementById("eventsSeriesTrigger");
    const menu = document.getElementById("eventsSeriesMenu");
    const search = document.getElementById("eventsSeriesSearch");
    if (!combobox || !trigger || !menu) return;
    trigger.setAttribute("aria-expanded", String(open));
    if (open) {
      // A canvas can create its own compositing layer. Portalling the popup to
      // <body> guarantees that it is painted above the chart, not inside it.
      if (menu.parentElement !== document.body) document.body.appendChild(menu);
      menu.hidden = false;
      menu.classList.add("event-series-combobox-menu-portal");
      this.positionSeriesMenu();
      search?.focus();
      return;
    }

    menu.hidden = true;
    menu.classList.remove("event-series-combobox-menu-portal");
    menu.style.top = "";
    menu.style.right = "";
    menu.style.left = "";
    if (menu.parentElement !== combobox) combobox.appendChild(menu);
  },

  positionSeriesMenu() {
    const trigger = document.getElementById("eventsSeriesTrigger");
    const menu = document.getElementById("eventsSeriesMenu");
    if (!trigger || !menu || menu.hidden || !menu.classList.contains("event-series-combobox-menu-portal")) return;
    const triggerRect = trigger.getBoundingClientRect();
    menu.style.top = `${Math.max(8, triggerRect.bottom + 7)}px`;
    menu.style.right = `${Math.max(12, window.innerWidth - triggerRect.right)}px`;
  },

  renderEvents(events) {
    const container = document.getElementById("eventsListContainer");
    if (!container) return;

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
      "region",
      "city",
      "hostname",
      "site_id",
      "session_id",
      "tracking_mode",
      "_ml_tracking_mode",
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
                    <span class="journey-end-text">${events.length} event${events.length === 1 ? "" : "s"}, page ${this.filters.page}</span>
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
    const rawJson = JSON.stringify(event.data, null, 2);
    jsonBox.dataset.rawText = rawJson;
    if (window.ClipboardHelper && typeof window.ClipboardHelper.highlightJson === "function") {
      jsonBox.innerHTML = window.ClipboardHelper.highlightJson(rawJson);
    } else {
      jsonBox.textContent = rawJson;
    }
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
