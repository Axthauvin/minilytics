/**
 * Minilytics Sessions Page Controller
 * Uses Lucide icons (no emojis), deterministic avatar SVGs, and real session data.
 */

const SessionsPage = {
  filters: {
    range: "7d",
    page: 1,
    limit: 25,
    search: "",
  },
  rawSessions: [],
  searchDebounce: null,
  activeSession: null,

  init() {
    this.bindEvents();
  },

  bindEvents() {
    const searchInput = document.getElementById("sessionSearch");
    if (searchInput) {
      searchInput.addEventListener("input", (e) => {
        clearTimeout(this.searchDebounce);
        this.searchDebounce = setTimeout(() => {
          this.filters.search = e.target.value.trim();
          this.filters.page = 1;
          this.load();
        }, 300);
      });
    }

    const prevBtn = document.getElementById("sessionsPrevPage");
    if (prevBtn) {
      prevBtn.addEventListener("click", () => {
        if (this.filters.page > 1) {
          this.filters.page--;
          this.load();
        }
      });
    }

    const nextBtn = document.getElementById("sessionsNextPage");
    if (nextBtn) {
      nextBtn.addEventListener("click", () => {
        this.filters.page++;
        this.load();
      });
    }

    const backBtn = document.getElementById("btnBackToSessionsList");
    if (backBtn) {
      backBtn.addEventListener("click", () => {
        this.showListView();
      });
    }
  },

  async load(range, siteId) {
    if (range) this.filters.range = range;
    if (siteId !== undefined) this.filters.siteId = siteId;

    try {
      const data = await Api.getSessions(this.filters);
      this.rawSessions = data.sessions || [];
      this.renderSessions(this.rawSessions);
      this.renderPagination(data);
    } catch (err) {
      window.App.displayNoDataMessage(siteId);
      console.error("Error loading sessions:", err);
    }
  },

  renderSessions(sessions) {
    const container = document.getElementById("sessionsListContainer");
    const countBadge = document.getElementById("sessionsTotalCount");
    if (!container) return;

    if (countBadge) {
      countBadge.textContent = `${sessions.length} session${sessions.length === 1 ? "" : "s"}`;
    }

    if (sessions.length === 0) {
      container.innerHTML = `
                <div class="data-table-card" style="padding: 48px; text-align: center;">
                    <h3 style="font-size: 15px; font-weight: 600; margin-bottom: 4px;">No sessions found</h3>
                    <p style="font-size: 13px; color: var(--text-muted);">No activity recorded for this period.</p>
                </div>
            `;
      return;
    }

    container.innerHTML = sessions
      .map((s) => {
        const avatarUrl =
          s.avatar_url || Icons.getDiceBearGlyphUrl(s.session_id);
        const fallbackSvg = Icons.getIdenticonSvgDataUri(s.session_id, 40);
        const shortId = (s.session_id || "").substring(0, 8);
        const country = s.country || "Unknown";
        const countryCode = s.country_code || "UN";
        const os = s.os || "Unknown OS";
        const browser = s.browser || "Unknown Browser";
        const timeAgo = s.time_ago || s.started_at;
        const duration = s.duration_label || "0s";
        const eventCount = s.event_count || 1;

        // Render flow chips with Lucide icons
        // const flowChips = (s.flow || []).map(f => {
        //     const isPv = f.type === 'pageview';
        //     const chipIcon = isPv ? Icons.get('file-text', { size: 11 }) : Icons.get('zap', { size: 11 });
        //     return `<span class="flow-pill ${isPv ? 'flow-pv' : 'flow-evt'}" title="${this.escapeHtml(f.label)}">${chipIcon} ${this.escapeHtml(f.label)}</span>`;
        // }).join(`<span class="flow-arrow">${Icons.get('arrow-right', { size: 10 })}</span>`);

        const flowChips = null;

        return `
                <div class="session-card-item" onclick="SessionsPage.inspectSession('${s.session_id}')" title="Inspect session journey">
                    <div class="session-card-left">
                        <div class="session-avatar-wrap">
                            <img src="${avatarUrl}" alt="Avatar" class="session-avatar-img" onerror="this.onerror=null; this.src='${fallbackSvg}';">
                            <span class="session-status-dot"></span>
                        </div>
                        <div class="session-card-info">
                            <div class="session-card-title-row">
                                <span class="session-visitor-name">Session #${shortId}</span>
                                <span class="session-badge-country">
                                    ${Icons.getCountryFlag(countryCode, { size: 14 })}
                                    <span>${this.escapeHtml(country)}</span>
                                </span>
                            </div>
                            <div class="session-card-sub-row">
                                <span class="session-meta-item">${Icons.getBrowserIcon(browser, 14)} ${this.escapeHtml(browser)}</span>
                                <span class="session-meta-divider">·</span>
                                <span class="session-meta-item">${Icons.getOsIcon(os, 14)} ${this.escapeHtml(os)}</span>
                                <span class="session-meta-divider">·</span>
                                <span class="session-meta-item">${Icons.get("activity", { size: 12 })} ${eventCount} action${eventCount === 1 ? "" : "s"}</span>
                                ${flowChips ? `<span class="session-meta-divider">|</span><div class="session-flow-bar">${flowChips}</div>` : ""}
                            </div>
                        </div>
                    </div>
                    <div class="session-card-right">
                        <div class="session-time-block">
                            <span class="session-card-time">${this.escapeHtml(timeAgo)}</span>
                            <span class="session-card-duration">Duration: ${duration}</span>
                        </div>
                        <span class="session-card-chevron">${Icons.get("chevron-right", { size: 16 })}</span>
                    </div>
                </div>
            `;
      })
      .join("");
  },

  renderPagination(data) {
    const info = document.getElementById("sessionsPageInfo");
    const prev = document.getElementById("sessionsPrevPage");
    const next = document.getElementById("sessionsNextPage");

    if (info) {
      info.textContent = `Page ${data.page} of ${data.total_pages} (${data.total} session${data.total === 1 ? "" : "s"})`;
    }

    if (prev) prev.disabled = data.page <= 1;
    if (next) next.disabled = data.page >= data.total_pages;
  },

  async inspectSession(sessionId) {
    try {
      const data = await Api.getSessionDetails(sessionId);
      this.activeSession = data.session;
      this.renderDetailView(data.session);
      this.showDetailView();
    } catch (err) {
      console.error("Failed to inspect session:", err);
    }
  },

  showDetailView() {
    const list = document.getElementById("sessionsListView");
    const detail = document.getElementById("sessionDetailView");
    if (list) list.style.display = "none";
    if (detail) {
      detail.style.display = "block";
      window.scrollTo({ top: 0, behavior: "smooth" });
    }
  },

  showListView() {
    const list = document.getElementById("sessionsListView");
    const detail = document.getElementById("sessionDetailView");
    if (detail) detail.style.display = "none";
    if (list) list.style.display = "block";
  },

  renderDetailView(session) {
    if (!session) return;

    const sId = session.session_id || "";
    const shortId =
      sId.length > 18
        ? `${sId.substring(0, 8)}...${sId.substring(sId.length - 6)}`
        : sId;
    const countryCode = session.country_code || "UN";

    // Header and metadata
    const setTxt = (id, val) => {
      const el = document.getElementById(id);
      if (el) el.textContent = val;
    };

    setTxt("detailSessionId", shortId);

    const avatarUrl = session.avatar_url || Icons.getDiceBearGlyphUrl(sId);
    const fallbackSvg = Icons.getIdenticonSvgDataUri(sId, 64);
    const avatarImg = document.getElementById("detailAvatarImg");
    if (avatarImg) {
      avatarImg.src = avatarUrl;
      avatarImg.onerror = () => {
        avatarImg.onerror = null;
        avatarImg.src = fallbackSvg;
      };
    }

    const flagEl = document.getElementById("detailCountryFlag");
    if (flagEl) {
      flagEl.innerHTML = Icons.getCountryFlag(countryCode, { size: 16 });
    }

    setTxt("detailCountryName", session.country || "Unknown");

    const techEl = document.getElementById("detailTechRow");
    if (techEl) {
      techEl.innerHTML = `
                <span class="tech-item">${Icons.getOsIcon(session.os, 14)} <span>${this.escapeHtml(session.os || "Unknown OS")}</span></span>
                <span class="tech-sep">·</span>
                <span class="tech-item">${Icons.getBrowserIcon(session.browser, 14)} <span>${this.escapeHtml(session.browser || "Unknown Browser")}</span></span>
            `;
    }

    setTxt("detailDuration", session.duration_label || "0s");
    setTxt("detailReferrer", session.referrer || "Direct");

    // Extract first event details for screen / locale
    const firstEvt =
      session.events && session.events[0] ? session.events[0].data : {};
    setTxt("detailLocale", firstEvt.language || "fr");

    const devEl = document.getElementById("detailDevice");
    const devVal = session.device || firstEvt.device || "Desktop";
    if (devEl) {
      devEl.innerHTML = `<span class="tech-item">${Icons.getDeviceIcon(devVal, 13)} <span>${this.escapeHtml(devVal)}</span></span>`;
    }
    setTxt("detailScreen", firstEvt.screen || "–");
    setTxt("detailViewport", firstEvt.viewport || "–");

    // Render Parcours de pages (Page Journey matching screenshot with clean properties card)
    const journeyContainer = document.getElementById("journeyStepsList");
    if (!journeyContainer) return;

    const events = session.events || [];
    let html = "";

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

    events.forEach((evt, idx) => {
      const isPv = evt.name === "pageview";
      const iconSvg = isPv
        ? Icons.get("file-text", { size: 15, color: "#f59e0b" })
        : Icons.get("zap", { size: 15, color: "#f59e0b" });
      const title = isPv
        ? evt.data && evt.data.path
          ? evt.data.path
          : "/"
        : evt.name;
      const subtitle =
        !isPv && evt.data && evt.data.path
          ? evt.data.path
          : evt.data && evt.data.title
            ? evt.data.title
            : "";
      const stepNum = idx + 1;
      const timeFormatted = this.formatEventTime(evt.timestamp);
      const durText = this.formatStepDuration(events, idx, session);

      // Extract custom event data payload only (no system or page context boilerplate)
      const customPayload = {};
      if (evt.data && typeof evt.data === "object") {
        Object.keys(evt.data).forEach((k) => {
          if (EXCLUDE_KEYS.has(k)) return;
          const val = evt.data[k];
          if (val === null || val === undefined || val === "") return;
          customPayload[k] = val;
        });
      }

      const customKeys = Object.keys(customPayload);
      const hasCustomData = !isPv && customKeys.length > 0;
      const propId = `stepProps_${evt.id || idx}_${idx}`;

      html += `
                <div class="journey-step-item">
                    <div class="journey-step-left">
                        <div class="journey-step-circle">${stepNum}</div>
                        <div class="journey-step-line"></div>
                    </div>
                    <div class="journey-step-content">
                        <div class="journey-step-top">
                            <div class="journey-step-title-wrap">
                                <span class="journey-step-icon">${iconSvg}</span>
                                <span class="journey-step-path">${this.escapeHtml(title)}</span>
                                ${subtitle && !isPv ? `<span class="journey-step-subtitle font-mono">${this.escapeHtml(subtitle)}</span>` : ""}
                            </div>
                            <span class="journey-step-time">${this.escapeHtml(timeFormatted)}</span>
                        </div>
                        <div class="journey-step-meta-row">
                            <span class="journey-step-dur">
                                ${Icons.get("clock", { size: 13, color: "#64748b" })}
                                <span>${durText}</span>
                            </span>

                            ${
                              hasCustomData
                                ? `
                                <button type="button" class="btn-step-props active" onclick="SessionsPage.toggleStepProps('${propId}', this)">
                                    <span class="props-pill">
                                        ${Icons.get("tag", { size: 11, color: "#475569" })}
                                        <span>${customKeys.length}</span>
                                    </span>
                                    <span class="props-text">Propriétés</span>
                                    <span class="props-chevron">${Icons.get("chevron-down", { size: 12, color: "#64748b" })}</span>
                                </button>
                            `
                                : ""
                            }
                        </div>

                        ${
                          hasCustomData
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
                                        return `
                                            <div class="props-row">
                                                <span class="props-key font-mono">${this.escapeHtml(k)}</span>
                                                <span class="props-val font-mono">${this.escapeHtml(formatted)}</span>
                                            </div>
                                        `;
                                      })
                                      .join("")}
                                </div>
                            </div>
                        `
                            : ""
                        }
                    </div>
                </div>
            `;
    });

    // End step
    html += `
            <div class="journey-step-item journey-step-end">
                <div class="journey-step-left">
                    <div class="journey-step-circle journey-circle-dot">
                        <span class="journey-inner-dot"></span>
                    </div>
                </div>
                <div class="journey-step-content" style="padding-bottom: 0; display: flex; align-items: center; min-height: 28px;">
                    <span class="journey-end-text">Fin de session</span>
                </div>
            </div>
        `;

    journeyContainer.innerHTML = html;
  },

  formatEventTime(ts) {
    if (!ts) return "";
    try {
      const d = new Date(ts.includes("T") ? ts : ts.replace(" ", "T") + "Z");
      if (isNaN(d.getTime())) return ts;
      const dayMonth = d.toLocaleDateString("fr-FR", {
        day: "numeric",
        month: "short",
      });
      const timeStr = d.toLocaleTimeString("fr-FR", {
        hour: "numeric",
        minute: "2-digit",
        second: "2-digit",
        hour12: true,
      });
      return `${dayMonth}, ${timeStr}`;
    } catch (e) {
      return ts;
    }
  },

  formatStepDuration(events, idx, session) {
    if (!events || events.length === 0) return "0s";
    const current = events[idx];
    const parseTs = (ts) =>
      new Date(ts.includes("T") ? ts : ts.replace(" ", "T") + "Z").getTime();

    let diffSec = 0;
    if (idx + 1 < events.length) {
      const t1 = parseTs(current.timestamp);
      const t2 = parseTs(events[idx + 1].timestamp);
      diffSec = Math.max(0, Math.round((t2 - t1) / 1000));
    } else {
      const totalDur =
        session && session.duration_seconds ? session.duration_seconds : 0;
      const curOffset = current.offset_seconds || 0;
      diffSec = Math.max(0, totalDur - curOffset);
    }

    if (diffSec === 0) {
      return "0s";
    }
    if (diffSec < 60) {
      return `${diffSec}s`;
    }
    const mins = Math.floor(diffSec / 60);
    const secs = diffSec % 60;
    return `${mins}m ${secs}s`;
  },

  toggleStepProps(id, btn) {
    const el = document.getElementById(id);
    if (!el) return;
    const isHidden = window.getComputedStyle(el).display === "none";
    el.style.display = isHidden ? "block" : "none";
    if (btn) {
      btn.classList.toggle("active", isHidden);
    }
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

window.SessionsPage = SessionsPage;
