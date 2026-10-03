/**
 * Minilytics Overview Page Controller
 * Umami-inspired layout featuring:
 * - Five core metric cards with trend pills
 * - Full-width chart, switchable between line and vertical bars
 * - 4 spacious analytical breakdown cards with Lucide icons (no emojis)
 */

const OverviewPage = {
  chart: null,
  currentData: null,
  chartType: "line",
  activePageTab: "paths",
  activeEnvTab: "browsers",

  init() {
    this.chart = new MinilyticsChart("overviewChart", "chartTooltip", {
      activeSeries: ["pageviews", "visitors"],
    });
    this.bindEvents();
    this.bindFilterEvents();
  },

  /**
   * Hover actions on breakdown rows:
   *  - funnel icon  -> toggle the value in the shared filters (multi-select)
   *  - people icon  -> add the value to the filters and open the related sessions
   */
  bindFilterEvents() {
    const grid = document.querySelector("#page-overview .overview-breakdowns-grid");
    grid?.addEventListener("click", (e) => {
      const btn = e.target.closest(".pill-action-btn");
      if (!btn) return;
      e.preventDefault();
      e.stopPropagation();
      const { filterDim: dim, filterValue: value } = btn.dataset;
      if (!dim) return;
      if (btn.classList.contains("pill-sessions-btn")) {
        Filters.add(dim, value, { silent: true });
        Filters.persist();
        Filters.openSessions();
      } else {
        Filters.toggle(dim, value);
      }
    });

    Filters.onChange(() => {
      if (window.App?.currentPage === "overview") {
        this.load(window.App.currentRange, window.App.currentSiteId, window.App.customDates);
      }
    });
  },

  /** Hover action buttons appended to each breakdown row. */
  rowActions(dim, value, label) {
    const active = Filters.has(dim, value);
    const v = Filters.escape(value);
    const l = Filters.escape(label || value);
    return `
      <div class="pill-actions">
        <button type="button" class="pill-action-btn pill-filter-btn${active ? " active" : ""}" data-filter-dim="${dim}" data-filter-value="${v}"
                title="${active ? `Remove filter “${l}”` : `Filter by “${l}”`}" aria-pressed="${active}">
          ${Icons.get("filter", { size: 13 })}
        </button>
        <button type="button" class="pill-action-btn pill-sessions-btn" data-filter-dim="${dim}" data-filter-value="${v}"
                title="View sessions for “${l}”">
          ${Icons.get("users", { size: 13 })}
        </button>
      </div>`;
  },

  rowClass(dim, value) {
    return Filters.has(dim, value) ? "clean-pill-row is-filterable is-filtered" : "clean-pill-row is-filterable";
  },

  bindEvents() {
    // Multi-series superposition toggles (Views / Visitors / Visits)
    const seriesBtns = document.querySelectorAll(
      "#chartSeriesToggles .chart-toggle-btn",
    );
    seriesBtns.forEach((btn) => {
      btn.addEventListener("click", () => {
        const seriesKey = btn.dataset.series;
        if (this.chart) {
          this.chart.toggleSeries(seriesKey);
          if (this.chart.isSeriesActive(seriesKey)) {
            btn.classList.add("active");
          } else {
            btn.classList.remove("active");
          }
        }
      });
    });

    const chartTypeBtns = document.querySelectorAll(
      "#chartTypeToggles .chart-toggle-btn",
    );
    chartTypeBtns.forEach((btn) => {
      btn.addEventListener("click", () => {
        this.chartType = btn.dataset.chartType;
        chartTypeBtns.forEach((b) => b.classList.toggle("active", b === btn));
        this.chart?.setChartType(this.chartType);
      });
    });

    // Top pages tabs (Paths / Titles)
    const pageTabBtns = document.querySelectorAll(
      "#pageTabs .chart-toggle-btn",
    );
    pageTabBtns.forEach((btn) => {
      btn.addEventListener("click", () => {
        pageTabBtns.forEach((b) => b.classList.remove("active"));
        btn.classList.add("active");
        this.activePageTab = btn.dataset.tab;
        this.renderPages();
      });
    });

    // Environment tabs (Browsers / OS / Devices)
    const envTabBtns = document.querySelectorAll("#envTabs .chart-toggle-btn");
    envTabBtns.forEach((btn) => {
      btn.addEventListener("click", () => {
        envTabBtns.forEach((b) => b.classList.remove("active"));
        btn.classList.add("active");
        this.activeEnvTab = btn.dataset.tab;

        const header = document.getElementById("envColumnHeader");
        if (header) {
          header.textContent =
            this.activeEnvTab === "browsers"
              ? "Browser"
              : this.activeEnvTab === "os"
                ? "Operating System"
                : "Device";
        }

        this.renderEnvironment();
      });
    });
  },

  async load(range = "7d", siteId = "", customDates = null) {
    const noDataEl = document.getElementById("no-data-yet");
    const dataContainer = document.getElementById("overviewDataContainer");
    if (noDataEl) noDataEl.style.display = "none";
    if (dataContainer) dataContainer.style.display = "block";

    const activeSite = siteId || window.App?.currentSiteId || "";
    Filters.useSite(activeSite);
    const token = (this._loadToken = (this._loadToken || 0) + 1);
    try {
      if (window.App && typeof window.App.setLoading === "function") {
        window.App.setLoading(true, "Loading analytics overview...");
      }

      const data = await Api.getStats(range, activeSite, customDates);
      // A newer request (e.g. another filter click) superseded this one
      if (token !== this._loadToken) return;
      this.currentData = data;

      // Populate sites dropdown if needed
      if (window.App && typeof window.App.updateSiteSelect === "function") {
        window.App.updateSiteSelect(data.available_sites || []);
      }

      Filters.renderBar(document.getElementById("overviewFilterBar"), {
        sessionsCount: data.summary?.session_count,
        showSessionsLink: true,
      });
      this.renderSummary(data.summary);
      this.renderChart(data.timeseries);
      this.renderPages();
      this.renderReferrers();
      this.renderEnvironment();
      this.renderCountries();
    } catch (err) {
      if (token !== this._loadToken) return;
      window.App?.displayNoDataMessage(siteId);
      console.error("Error loading overview:", err);
    } finally {
      if (token === this._loadToken && window.App && typeof window.App.setLoading === "function") {
        window.App.setLoading(false);
      }
    }
  },

  formatNumber(num) {
    if (!num) return "0";
    if (num >= 1000000) return (num / 1000000).toFixed(1) + "M";
    if (num >= 1000) return (num / 1000).toFixed(1) + "k";
    return num.toLocaleString();
  },

  formatDuration(seconds) {
    if (!seconds || seconds <= 0) return "0s";
    const mins = Math.floor(seconds / 60);
    const secs = Math.round(seconds % 60);
    if (mins === 0) return `${secs}s`;
    return `${mins}m ${secs}s`;
  },

  applyDeltaBadge(badgeId, textId, deltaStr, inverse = false) {
    const badge = document.getElementById(badgeId);
    const text = document.getElementById(textId);
    if (!badge) return;

    badge.classList.remove("positive", "negative", "neutral");
    const arrow = badge.querySelector(".delta-arrow");

    if (
      !deltaStr ||
      deltaStr === "0" ||
      deltaStr === "0%" ||
      deltaStr === "0s"
    ) {
      badge.classList.add("neutral");
      if (arrow) arrow.textContent = "–";
      if (text) text.textContent = "0%";
    } else if (deltaStr.startsWith("+")) {
      badge.classList.add(inverse ? "negative" : "positive");
      if (arrow) arrow.textContent = "↑";
      if (text) text.textContent = deltaStr.replace("+", "");
    } else if (deltaStr.startsWith("-")) {
      badge.classList.add(inverse ? "positive" : "negative");
      if (arrow) arrow.textContent = "↓";
      if (text) text.textContent = deltaStr.replace("-", "");
    } else {
      badge.classList.add("neutral");
      if (arrow) arrow.textContent = "–";
      if (text) text.textContent = deltaStr;
    }
  },

  updateMainChartNumbers(sum) {
    if (!sum) return;
    const setTxt = (id, val) => {
      const el = document.getElementById(id);
      if (el) el.textContent = this.formatNumber(val);
    };
    setTxt("chartTotalViews", sum.pageviews || 0);
    setTxt("chartTotalVisitors", sum.visitors || 0);
  },

  renderSummary(sum) {
    if (!sum) return;

    this.updateMainChartNumbers(sum);

    const setTxt = (id, val) => {
      const el = document.getElementById(id);
      if (el) el.textContent = val;
    };

    // Core overview metrics
    setTxt("statVisitors", this.formatNumber(sum.visitors || 0));
    setTxt("statSessions", this.formatNumber(sum.sessions || 0));
    setTxt("statPageviews", this.formatNumber(sum.pageviews || 0));
    setTxt("statBounceRate", `${sum.bounce_rate || 0}%`);
    setTxt("statDuration", this.formatDuration(sum.avg_duration_seconds));

    // Real deltas
    if (sum.deltas) {
      this.applyDeltaBadge(
        "deltaVisitorsBadge",
        "deltaVisitorsText",
        sum.deltas.visitors || "0",
      );
      this.applyDeltaBadge(
        "deltaSessionsBadge",
        "deltaSessionsText",
        sum.deltas.sessions || "0",
      );
      this.applyDeltaBadge(
        "deltaPageviewsBadge",
        "deltaPageviewsText",
        sum.deltas.pageviews || "0",
      );
      this.applyDeltaBadge(
        "deltaBounceBadge",
        "deltaBounceText",
        sum.deltas.bounce_rate || "0%",
        true,
      );
      this.applyDeltaBadge(
        "deltaDurationBadge",
        "deltaDurationText",
        sum.deltas.duration || "0s",
      );
    }

    // Live visitors indicator in header
    const liveElem = document.getElementById("liveVisitorsCount");
    if (liveElem) {
      const count = sum.live_visitors || 0;
      liveElem.textContent = `${count} live visitor${count === 1 ? "" : "s"}`;
    }
  },

  renderChart(timeseries) {
    if (this.chart) {
      this.chart.setData(timeseries);
    }
  },

  renderPages() {
    const list = document.getElementById("topPagesList");
    if (!list || !this.currentData || !this.currentData.top_pages) return;

    const pages = this.currentData.top_pages;
    if (pages.length === 0) {
      list.innerHTML = `<li class="clean-pill-row empty"><span class="pill-muted">No pageviews recorded yet</span></li>`;
      return;
    }

    list.innerHTML = pages
      .map((p) => {
        const label =
          this.activePageTab === "titles" ? p.title || p.path : p.path;
        const pct = p.percentage || 0;
        const iconSvg = Icons.get("file-text", { size: 14, color: "#64748b" });

        return `
                <li class="${this.rowClass("page", p.path)}">
                    <div class="pill-progress-bg" style="width: ${pct}%;"></div>
                    <div class="pill-left">
                        <span class="pill-title" title="${this.escapeHtml(p.path)}">${this.escapeHtml(label)}</span>
                    </div>
                    <div class="pill-right">
                        ${this.rowActions("page", p.path, label)}
                        <span class="pill-stat">${p.views.toLocaleString()}</span>
                        <span class="pill-pct">${pct}%</span>
                    </div>
                </li>
            `;
      })
      .join("");
  },

  renderReferrers() {
    const list = document.getElementById("topReferrersList");
    if (!list || !this.currentData) return;

    // Detect own domain of active site to exclude any self-referral
    const activeSiteId = (window.App && window.App.currentSiteId) || "";
    const siteObj = (this.currentData?.available_sites || []).find((s) => s.id === activeSiteId);
    const normalize = (d) => (d || "").toLowerCase().replace(/^https?:\/\//, "").replace(/^www\./, "").split("/")[0].split(":")[0].trim();
    const ownDomain = normalize(siteObj?.domain || "");

    const filtered = (this.currentData.top_referrers || []).filter((r) => {
      if (!ownDomain) return true;
      if (!r.domain || r.domain === "direct" || r.domain === "Direct / None") return true;
      const refDom = normalize(r.domain);
      return refDom !== ownDomain;
    });

    // Merge raw referrer URLs sharing the same domain into a single row so each
    // row maps to exactly one filter value.
    const byDomain = new Map();
    filtered.forEach((r) => {
      const isDirect = !r.domain || r.domain === "direct" || r.domain === "Direct / None";
      const key = isDirect ? "direct" : r.domain;
      const existing = byDomain.get(key);
      if (existing) {
        existing.views += r.views || 0;
        existing.percentage = Math.round((existing.percentage + (r.percentage || 0)) * 10) / 10;
      } else {
        byDomain.set(key, { ...r, domain: key, views: r.views || 0, percentage: r.percentage || 0 });
      }
    });
    const referrers = [...byDomain.values()].sort((a, b) => b.views - a.views);

    if (referrers.length === 0) {
      list.innerHTML = `<li class="clean-pill-row empty"><span class="pill-muted">No referrer data recorded yet</span></li>`;
      return;
    }

    list.innerHTML = referrers
      .map((r) => {
        const isDirect = r.domain === "direct";
        const domain = isDirect ? "Direct / None" : r.domain;
        const pct = r.percentage || 0;

        const iconHtml = isDirect
          ? `<span class="pill-icon-direct">${Icons.get("compass", { size: 15, color: "#64748b" })}</span>`
          : `<img src="https://www.google.com/s2/favicons?domain=${encodeURIComponent(domain)}&sz=32" 
                        class="pill-favicon" 
                        alt="" 
                        onerror="this.style.display='none'; this.nextElementSibling.style.display='inline-flex';" />
                   <span class="pill-icon-fallback" style="display:none;">${Icons.get("globe", { size: 15, color: "#64748b" })}</span>`;

        return `
                <li class="${this.rowClass("referrer", r.domain)}">
                    <div class="pill-progress-bg" style="width: ${pct}%;"></div>
                    <div class="pill-left">
                        ${iconHtml}
                        <span class="pill-title" title="${this.escapeHtml(isDirect ? domain : r.domain)}">${this.escapeHtml(domain)}</span>
                    </div>
                    <div class="pill-right">
                        ${this.rowActions("referrer", r.domain, domain)}
                        <span class="pill-stat">${r.views.toLocaleString()}</span>
                        <span class="pill-pct">${pct}%</span>
                    </div>
                </li>
            `;
      })
      .join("");
  },

  renderEnvironment() {
    const list = document.getElementById("envList");
    if (!list || !this.currentData || !this.currentData.environment) return;

    const env = this.currentData.environment;
    const items = env[this.activeEnvTab] || [];

    if (items.length === 0) {
      list.innerHTML = `<li class="clean-pill-row empty"><span class="pill-muted">No data recorded yet</span></li>`;
      return;
    }

    list.innerHTML = items
      .map((item) => {
        let iconSvg = "";
        if (this.activeEnvTab === "browsers") {
          iconSvg = Icons.getBrowserIcon(item.name, 14);
        } else if (this.activeEnvTab === "os") {
          iconSvg = Icons.getOsIcon(item.name, 14);
        } else {
          iconSvg = Icons.getDeviceIcon(item.name, 14);
        }

        const pct = item.percentage || 0;
        const dim = { browsers: "browser", os: "os", devices: "device" }[this.activeEnvTab] || "browser";
        return `
                <li class="${this.rowClass(dim, item.name)}">
                    <div class="pill-progress-bg" style="width: ${pct}%;"></div>
                    <div class="pill-left">
                        <span class="pill-icon-box">${iconSvg}</span>
                        <span class="pill-title">${this.escapeHtml(item.name)}</span>
                    </div>
                    <div class="pill-right">
                        ${this.rowActions(dim, item.name)}
                        <span class="pill-stat">${item.count.toLocaleString()}</span>
                        <span class="pill-pct">${pct}%</span>
                    </div>
                </li>
            `;
      })
      .join("");
  },

  renderCountries() {
    const list = document.getElementById("countriesList");
    if (!list || !this.currentData) return;

    const countries = this.currentData.countries || [];
    if (countries.length === 0) {
      list.innerHTML = `<li class="clean-pill-row empty"><span class="pill-muted">No country data recorded yet</span></li>`;
      return;
    }

    list.innerHTML = countries
      .map((c) => {
        const code = c.code || "UN";
        const pct = c.percentage || 0;
        return `
                <li class="${this.rowClass("country", c.name)}">
                    <div class="pill-progress-bg" style="width: ${pct}%;"></div>
                    <div class="pill-left">
                        ${Icons.getCountryFlag(code, { size: 14 })}
                        <span class="pill-title">${this.escapeHtml(c.name)}</span>
                        <span class="country-sub-code">${this.escapeHtml(code)}</span>
                    </div>
                    <div class="pill-right">
                        ${this.rowActions("country", c.name)}
                        <span class="pill-stat">${c.count.toLocaleString()}</span>
                        <span class="pill-pct">${pct}%</span>
                    </div>
                </li>
            `;
      })
      .join("");
  },

  escapeHtml(str) {
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

window.OverviewPage = OverviewPage;
