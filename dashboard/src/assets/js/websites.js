/**
 * Minilytics Websites Management Page Controller
 * Inspired by Image 1 table aesthetic: Favicon retrieval, 7d sparklines, relative creation date.
 */

const WebsitesPage = {
    sites: [],
    searchQuery: '',

    init() {
        this.bindEvents();
    },

    bindEvents() {
        const searchInput = document.getElementById('websiteSearchInput');
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                this.searchQuery = e.target.value.toLowerCase().trim();
                this.render();
            });
        }

        const addBtn = document.getElementById('btnWebsitesAddSite');
        if (addBtn) {
            addBtn.addEventListener('click', () => {
                const headerAddBtn = document.getElementById('btnOpenAddSiteModal');
                if (headerAddBtn) {
                    headerAddBtn.click();
                } else {
                    const modal = document.getElementById('addSiteModal');
                    if (modal) modal.classList.add('active');
                }
            });
        }
    },

    async load() {
        try {
            const data = await Api.getSites();
            this.sites = data.sites || [];
            this.renderSummary();
            this.render();
        } catch (err) {
            console.error('Failed to load websites:', err);
        }
    },

    renderSummary() {
        const totalSites = this.sites.length;
        let totalViews = 0;
        let liveVisitors = 0;

        this.sites.forEach(s => {
            totalViews += (s.pageviews || 0);
            liveVisitors += (s.live_visitors || 0);
        });

        const totalSitesElem = document.getElementById('globalTotalSites');
        if (totalSitesElem) totalSitesElem.textContent = totalSites.toLocaleString();

        const totalViewsElem = document.getElementById('globalTotalViews');
        if (totalViewsElem) totalViewsElem.textContent = totalViews.toLocaleString();

        const liveVisitorsElem = document.getElementById('globalLiveVisitors');
        if (liveVisitorsElem) liveVisitorsElem.textContent = `${liveVisitors.toLocaleString()} active`;
    },

    formatTimeAgo(dateStr) {
        if (!dateStr) return 'recently';
        const d = new Date(dateStr.replace(' ', 'T') + 'Z');
        const now = new Date();
        const diffSecs = Math.floor((now - d) / 1000);

        if (diffSecs < 60) return 'just now';
        if (diffSecs < 3600) return `${Math.floor(diffSecs / 60)} minutes ago`;
        if (diffSecs < 86400) return `${Math.floor(diffSecs / 3600)} hours ago`;
        const days = Math.floor(diffSecs / 86400);
        if (days === 1) return 'about 1 day ago';
        if (days < 30) return `${days} days ago`;
        const months = Math.floor(days / 30);
        if (months === 1) return 'about 1 month ago';
        if (months < 12) return `${months} months ago`;
        const years = Math.floor(days / 365);
        return years <= 1 ? 'about 1 year ago' : `${years} years ago`;
    },

    getFallbackIconDataUri() {
        const svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#475569" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"/><path d="M2 12h20"/></svg>';
        return 'data:image/svg+xml;utf8,' + encodeURIComponent(svg);
    },

    getFaviconUrl(domain) {
        if (!domain) {
            return this.getFallbackIconDataUri();
        }
        const clean = domain.replace(/^https?:\/\//, '').replace(/\/.*$/, '').split(':')[0].trim();
        if (!clean || clean === 'localhost' || clean === '127.0.0.1' || !clean.includes('.')) {
            return this.getFallbackIconDataUri();
        }
        return `https://www.google.com/s2/favicons?domain=${encodeURIComponent(clean)}&sz=64`;
    },

    handleFaviconError(img) {
        if (!img) return;
        img.onerror = null;
        img.src = this.getFallbackIconDataUri();
    },

    generateSparklineSvg(pointsCount = 8) {
        // Generate an inline mini trend sparkline (Image 1 aesthetic)
        const heights = [10, 16, 12, 19, 14, 21, 16, 22];
        const width = 80;
        const height = 24;
        const step = width / (heights.length - 1);

        let path = `M 0 ${height - heights[0]}`;
        for (let i = 1; i < heights.length; i++) {
            path += ` L ${i * step} ${height - heights[i]}`;
        }

        return `
            <svg width="${width}" height="${height}" viewBox="0 0 ${width} ${height}" fill="none" style="vertical-align: middle;">
                <path d="${path}" stroke="#3b82f6" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
        `;
    },

    render() {
        const tbody = document.getElementById('websitesTableBody');
        const countFooter = document.getElementById('websitesRecordsCount');
        if (!tbody) return;

        let filtered = this.sites;
        if (this.searchQuery) {
            filtered = this.sites.filter(s => 
                (s.name && s.name.toLowerCase().includes(this.searchQuery)) ||
                (s.id && s.id.toLowerCase().includes(this.searchQuery)) ||
                (s.domain && s.domain.toLowerCase().includes(this.searchQuery))
            );
        }

        if (countFooter) {
            countFooter.textContent = `${filtered.length} record${filtered.length === 1 ? '' : 's'}`;
        }

        if (filtered.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="5" class="empty-state">
                        <h3 style="font-size: 15px; font-weight: 600; margin-bottom: 4px;">No websites found</h3>
                        <p style="font-size: 13px; color: var(--text-muted);">${this.searchQuery ? 'No websites match your search.' : 'You have not added any website yet.'}</p>
                    </td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = filtered.map(site => {
            const domain = site.domain || '';
            const favicon = this.getFaviconUrl(domain);
            const timeAgo = this.formatTimeAgo(site.created_at);
            const sparkline = this.generateSparklineSvg();
            const visitors = site.visitors || 0;

            return `
                <tr style="cursor: pointer;" onclick="if (!event.target.closest('button') && !event.target.closest('a')) WebsitesPage.openSiteAnalytics('${site.id}')">
                    <td>
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <span class="site-table-avatar">
                                <img src="${favicon}" alt="" class="site-favicon-img" onerror="WebsitesPage.handleFaviconError(this)">
                            </span>
                            <div>
                                <span class="site-table-name">${this.escapeHtml(site.name || site.id)}</span>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="site-table-domain">${domain ? this.escapeHtml(domain) : '<span style="color: var(--text-muted);">–</span>'}</span>
                    </td>
                    <td>
                        <div style="display: flex; align-items: center; gap: 12px;">
                            ${sparkline}
                            <span style="font-size: 12.5px; font-weight: 600; color: var(--text-secondary);">${visitors}</span>
                        </div>
                    </td>
                    <td>
                        <span class="site-table-time">${timeAgo}</span>
                    </td>
                    <td style="text-align: right;">
                        <div style="display: inline-flex; align-items: center; gap: 6px;">
                            <!-- Open Analytics -->
                            <button class="btn-icon" style="width: 30px; height: 30px;" onclick="WebsitesPage.openSiteAnalytics('${site.id}')" title="Open analytics">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                                    <polyline points="15 3 21 3 21 9"></polyline>
                                    <line x1="10" y1="14" x2="21" y2="3"></line>
                                </svg>
                            </button>

                            <!-- Get snippet -->
                            <button class="btn-icon" style="width: 30px; height: 30px;" onclick="WebsitesPage.showSnippet('${site.id}', '${this.escapeHtml(site.name)}')" title="Get tracking script snippet">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="16 18 22 12 16 6"></polyline>
                                    <polyline points="8 6 2 12 8 18"></polyline>
                                </svg>
                            </button>

                            <!-- Delete website -->
                            <button class="btn-icon" style="width: 30px; height: 30px; color: var(--danger);" onclick="WebsitesPage.confirmDelete('${site.id}', '${this.escapeHtml(site.name)}')" title="Delete website and its database">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="3 6 5 6 21 6"></polyline>
                                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                </svg>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        }).join('');
    },

    openSiteAnalytics(siteId) {
        if (window.App) {
            window.App.selectSiteAndOpen(siteId);
        }
    },

    showSnippet(siteId, siteName) {
        const host = window.location.host;
        const protocol = window.location.protocol;
        const scriptUrl = `${protocol}//${host}/minilytics.js`;
        const snippet = `<script defer src="${scriptUrl}" data-site-id="${siteId}"></script>`;

        const snippetBox = document.getElementById('createdSiteSnippet');
        if (snippetBox) {
            snippetBox.textContent = snippet;
        }

        const modal = document.getElementById('addSiteModal');
        const form = document.getElementById('addSiteForm');
        const successBox = document.getElementById('addSiteSuccess');

        if (modal && form && successBox) {
            form.style.display = 'none';
            successBox.style.display = 'block';
            modal.classList.add('active');
        }
    },

    async confirmDelete(siteId, siteName) {
        const msg = `Are you sure you want to permanently delete "${siteName || siteId}"?\n\nThis will permanently delete its dedicated database (data/${siteId}.db) and all recorded analytics data.`;
        if (confirm(msg)) {
            try {
                await Api.deleteSite(siteId);
                
                if (window.App && window.App.currentSiteId === siteId) {
                    window.App.currentSiteId = 'all';
                }

                await this.load();

                if (window.App) {
                    window.App.updateSiteSelect(this.sites);
                }
            } catch (err) {
                alert(`Failed to delete website: ${err.message}`);
            }
        }
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

window.WebsitesPage = WebsitesPage;
