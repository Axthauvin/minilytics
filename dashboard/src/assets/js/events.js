/**
 * Minilytics Events Page Controller
 * Uses Lucide icons (no emojis) and clean stream card formatting.
 */

const EventsPage = {
    filters: {
        range: '7d',
        page: 1,
        limit: 50,
        search: '',
        eventName: 'all',
        sessionId: ''
    },
    types: [],
    searchDebounce: null,
    eventsMap: {},

    init() {
        this.bindEvents();
    },

    bindEvents() {
        const searchInput = document.getElementById('eventSearch');
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                clearTimeout(this.searchDebounce);
                this.searchDebounce = setTimeout(() => {
                    this.filters.search = e.target.value.trim();
                    this.filters.page = 1;
                    this.load();
                }, 300);
            });
        }

        const typeSelect = document.getElementById('eventTypeFilter');
        if (typeSelect) {
            typeSelect.addEventListener('change', (e) => {
                this.filters.eventName = e.target.value;
                this.filters.page = 1;
                this.load();
            });
        }

        const prevBtn = document.getElementById('eventsPrevPage');
        if (prevBtn) {
            prevBtn.addEventListener('click', () => {
                if (this.filters.page > 1) {
                    this.filters.page--;
                    this.load();
                }
            });
        }

        const nextBtn = document.getElementById('eventsNextPage');
        if (nextBtn) {
            nextBtn.addEventListener('click', () => {
                this.filters.page++;
                this.load();
            });
        }

        const clearFilterBtn = document.getElementById('clearSessionFilterBtn');
        if (clearFilterBtn) {
            clearFilterBtn.addEventListener('click', () => {
                this.filters.sessionId = '';
                document.getElementById('activeSessionFilterBox').style.display = 'none';
                this.filters.page = 1;
                this.load();
            });
        }
    },

    filterBySession(sessionId) {
        this.filters.sessionId = sessionId;
        this.filters.page = 1;
        const box = document.getElementById('activeSessionFilterBox');
        if (box) {
            box.style.display = 'inline-flex';
            document.getElementById('activeSessionIdLabel').textContent = sessionId.substring(0, 10) + '...';
        }
        this.load();
    },

    async load(range, siteId) {
        if (range) this.filters.range = range;
        if (siteId !== undefined) this.filters.siteId = siteId;

        try {
            const data = await Api.getEvents(this.filters);
            this.types = data.types || [];
            this.renderTypeOptions(this.types);
            this.renderEvents(data.events || []);
            this.renderPagination(data);
        } catch (err) {
            console.error('Error loading events:', err);
        }
    },

    renderTypeOptions(types) {
        const select = document.getElementById('eventTypeFilter');
        if (!select) return;

        const currentVal = this.filters.eventName || 'all';
        let html = `<option value="all" ${currentVal === 'all' ? 'selected' : ''}>All Events</option>`;

        types.forEach(t => {
            const sel = t.name === currentVal ? 'selected' : '';
            html += `<option value="${this.escapeHtml(t.name)}" ${sel}>${this.escapeHtml(t.name)} (${t.count})</option>`;
        });

        select.innerHTML = html;
    },

    renderEvents(events) {
        const container = document.getElementById('eventsListContainer');
        const countBadge = document.getElementById('eventsTotalCount');
        if (!container) return;

        if (countBadge) {
            countBadge.textContent = `${events.length} event${events.length === 1 ? '' : 's'}`;
        }

        if (events.length === 0) {
            container.innerHTML = `
                <div class="empty-state" style="padding: 48px; text-align: center;">
                    <h3 style="font-size: 15px; font-weight: 600; margin-bottom: 4px;">No events recorded</h3>
                    <p style="font-size: 13px; color: var(--text-muted);">No activity matches your current filters.</p>
                </div>
            `;
            return;
        }

        this.eventsMap = {};
        events.forEach(e => {
            this.eventsMap[e.id] = e;
        });

        container.innerHTML = events.map(e => {
            const data = e.data || {};
            const isPv = e.name === 'pageview';
            const country = data.country || '';
            const countryCode = data.country_code || 'UN';
            const browser = data.browser || '';
            const os = data.os || '';
            const shortSession = (e.session_id || '').substring(0, 8);
            const timeAgo = e.time_ago || e.timestamp;

            // Clean concise summary line
            let summaryHtml = '';
            if (isPv) {
                summaryHtml = `
                    <span class="event-summary-path font-mono">${this.escapeHtml(data.path || '/')}</span>
                    ${data.title ? `<span class="event-summary-muted">· ${this.escapeHtml(data.title)}</span>` : ''}
                `;
            } else {
                const details = [];
                if (data.path) details.push(`path: ${data.path}`);
                if (data.source) details.push(`source: ${data.source}`);
                if (data.count !== undefined) details.push(`count: ${data.count}`);
                
                // Add any other custom key not in standard browser env
                const ignoredKeys = ['path', 'title', 'url', 'referrer', 'hostname', 'search', 'hash', 'screen', 'viewport', 'device', 'language', 'browser', 'os', 'country_code', 'country'];
                Object.keys(data).forEach(k => {
                    if (!ignoredKeys.includes(k) && k !== 'source' && k !== 'count') {
                        details.push(`${k}: ${JSON.stringify(data[k])}`);
                    }
                });

                summaryHtml = `
                    <span class="event-summary-desc">${details.length > 0 ? this.escapeHtml(details.join(' · ')) : this.escapeHtml(e.name)}</span>
                `;
            }

            const badgeClass = isPv ? 'badge-pageview' : (e.name.includes('click') ? 'badge-click' : 'badge-custom');
            const badgeIconSvg = isPv 
                ? Icons.get('file-text', { size: 12 }) 
                : (e.name.includes('click') ? Icons.get('zap', { size: 12 }) : Icons.get('activity', { size: 12 }));

            const userIconSvg = Icons.get('user', { size: 12 });

            return `
                <div class="event-stream-row">
                    <div class="event-stream-left">
                        <span class="event-badge ${badgeClass}">${badgeIconSvg} ${this.escapeHtml(e.name)}</span>
                        <div class="event-details-col">
                            <div class="event-main-line">${summaryHtml}</div>
                            <div class="event-meta-line">
                                <span class="event-meta-tag session-tag" onclick="EventsPage.filterBySession('${e.session_id}')" title="Filter by session ${e.session_id}">${userIconSvg} ${shortSession}</span>
                                ${country ? `<span class="event-meta-tag">${Icons.getCountryFlag(countryCode, { size: 12 })} <span>${this.escapeHtml(country)}</span></span>` : ''}
                                ${browser ? `<span class="event-meta-tag">${Icons.getBrowserIcon(browser, 13)} <span>${this.escapeHtml(browser)}</span></span>` : ''}
                                ${os ? `<span class="event-meta-tag">${Icons.getOsIcon(os, 13)} <span>${this.escapeHtml(os)}</span></span>` : ''}
                            </div>
                        </div>
                    </div>
                    <div class="event-stream-right">
                        <span class="event-time" title="${e.timestamp}">${timeAgo}</span>
                        <button type="button" class="btn-inspect-json" onclick="EventsPage.inspectEvent(${e.id})">JSON</button>
                    </div>
                </div>
            `;
        }).join('');
    },

    renderPagination(data) {
        const info = document.getElementById('eventsPageInfo');
        const footerCount = document.getElementById('eventsFooterCount');
        const prev = document.getElementById('eventsPrevPage');
        const next = document.getElementById('eventsNextPage');

        if (info) {
            info.textContent = `Page ${data.page} of ${data.total_pages}`;
        }

        if (footerCount) {
            footerCount.textContent = `Showing ${data.events ? data.events.length : 0} of ${data.total} recorded events`;
        }

        if (prev) prev.disabled = data.page <= 1;
        if (next) next.disabled = data.page >= data.total_pages;
    },

    inspectEvent(id) {
        const event = this.eventsMap[id];
        if (!event) return;

        const modal = document.getElementById('payloadModal');
        const jsonBox = document.getElementById('modalJsonContent');
        if (!modal || !jsonBox) return;

        jsonBox.textContent = JSON.stringify(event.data, null, 2);
        modal.classList.add('active');
    },

    escapeHtml(str) {
        if (!str) return '';
        return String(str).replace(/[&<>"']/g, m => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;'
        })[m]);
    }
};

window.EventsPage = EventsPage;
