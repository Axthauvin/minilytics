/**
 * Minilytics Main Application Controller
 * Enforces per-site navigation via GET URL parameter (?site=<site_id>),
 * ensuring analytics are ALWAYS scoped to an active individual website (never 'all').
 */

const App = {
    currentPage: 'websites',
    currentRange: '7d',
    currentSiteId: null,
    refreshInterval: null,

    init() {
        // Read URL search parameter (?site=... or ?site_id=...)
        const urlParams = new URLSearchParams(window.location.search);
        const siteParam = urlParams.get('site') || urlParams.get('site_id');
        if (siteParam && siteParam !== 'all') {
            this.currentSiteId = siteParam;
        }

        this.bindNavigation();
        this.bindHeaderActions();
        this.bindModals();
        this.bindAddSiteModal();

        // Initialize sub-controllers
        WebsitesPage.init();
        OverviewPage.init();
        EventsPage.init();
        SessionsPage.init();

        // Handle URL routing
        window.addEventListener('hashchange', () => this.handleRoute());
        window.addEventListener('popstate', () => this.handleRoute());
        this.handleRoute();

        // Auto-refresh every 30 seconds for live visitors
        this.refreshInterval = setInterval(() => {
            this.refreshCurrentPage(true);
        }, 30000);
    },

    bindNavigation() {
        // Back to All Websites button
        const backBtn = document.getElementById('btnBackToWebsites');
        if (backBtn) {
            backBtn.addEventListener('click', (e) => {
                e.preventDefault();
                const url = new URL(window.location);
                url.searchParams.delete('site');
                url.searchParams.delete('site_id');
                url.hash = '#websites';
                window.history.pushState({}, '', url);
                this.navigateTo('websites');
            });
        }

        // Sidebar collapse toggle if present
        const collapseBtn = document.getElementById('btnSidebarCollapse');
        const sidebar = document.getElementById('sidebar');
        if (collapseBtn && sidebar) {
            collapseBtn.addEventListener('click', () => {
                sidebar.classList.toggle('collapsed');
                setTimeout(() => {
                    if (this.currentPage === 'overview' && OverviewPage.chart) {
                        OverviewPage.chart.resize();
                    }
                }, 260);
            });
        }
    },

    bindHeaderActions() {
        // Website selector dropdown (if present in header)
        const siteSelect = document.getElementById('siteSelect');
        if (siteSelect) {
            siteSelect.addEventListener('change', (e) => {
                const newSiteId = e.target.value;
                if (!newSiteId || newSiteId === 'all') return;
                this.currentSiteId = newSiteId;
                const url = new URL(window.location);
                url.searchParams.set('site', newSiteId);
                window.history.replaceState({}, '', url);
                this.updateSidebarSiteInfo(newSiteId);
                this.refreshCurrentPage();
            });
        }

        // Date range dropdown
        const rangeSelect = document.getElementById('rangeSelect');
        if (rangeSelect) {
            rangeSelect.addEventListener('change', (e) => {
                this.currentRange = e.target.value;
                this.refreshCurrentPage();
            });
        }

        // Manual refresh button
        const refreshBtn = document.getElementById('btnRefresh');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', () => {
                refreshBtn.style.transform = 'rotate(360deg)';
                refreshBtn.style.transition = 'transform 0.5s ease';
                this.refreshCurrentPage();
                setTimeout(() => {
                    refreshBtn.style.transform = '';
                    refreshBtn.style.transition = '';
                }, 500);
            });
        }
    },

    updateSiteSelect(sites) {
        const select = document.getElementById('siteSelect');
        if (!select || !Array.isArray(sites)) return;

        let html = '';
        sites.forEach(s => {
            const sId = typeof s === 'object' ? s.id : s;
            const sName = typeof s === 'object' ? (s.name || s.id) : s;
            const selected = sId === this.currentSiteId ? 'selected' : '';
            html += `<option value="${this.escapeHtml(sId)}" ${selected}>${this.escapeHtml(sName)}</option>`;
        });

        select.innerHTML = html;
        if (this.currentSiteId) {
            select.value = this.currentSiteId;
        }
    },

    bindModals() {
        // Close modal buttons
        document.querySelectorAll('[data-close-modal]').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.modal-overlay').forEach(m => m.classList.remove('active'));
            });
        });

        // Click outside modal dialog to close
        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) {
                    overlay.classList.remove('active');
                }
            });
        });
    },

    bindAddSiteModal() {
        const openBtn = document.getElementById('btnOpenAddSiteModal');
        const modal = document.getElementById('addSiteModal');
        const form = document.getElementById('addSiteForm');
        const nameInput = document.getElementById('newSiteName');
        const idInput = document.getElementById('newSiteId');
        const domainInput = document.getElementById('newSiteDomain');
        const errorBox = document.getElementById('addSiteError');
        const successBox = document.getElementById('addSiteSuccess');
        const snippetBox = document.getElementById('createdSiteSnippet');
        const copyBtn = document.getElementById('btnCopySnippet');
        const goToSiteBtn = document.getElementById('btnGoToCreatedSite');

        if (!modal) return;

        let autoSlug = true;

        if (openBtn) {
            openBtn.addEventListener('click', () => {
                form.reset();
                form.style.display = 'block';
                successBox.style.display = 'none';
                errorBox.style.display = 'none';
                autoSlug = true;
                modal.classList.add('active');
                setTimeout(() => nameInput.focus(), 50);
            });
        }

        if (nameInput && idInput) {
            nameInput.addEventListener('input', () => {
                if (autoSlug) {
                    idInput.value = nameInput.value
                        .toLowerCase()
                        .trim()
                        .replace(/[^a-z0-9]+/g, '_')
                        .replace(/^_+|_+$/g, '');
                }
            });

            idInput.addEventListener('input', () => {
                autoSlug = false;
            });
        }

        if (form) {
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                errorBox.style.display = 'none';
                const submitBtn = document.getElementById('btnSubmitNewSite');

                const siteData = {
                    name: nameInput.value.trim(),
                    id: idInput.value.trim(),
                    domain: domainInput.value.trim()
                };

                try {
                    submitBtn.disabled = true;
                    submitBtn.textContent = 'Creating...';

                    const res = await Api.createSite(siteData);

                    // Show success snippet
                    form.style.display = 'none';
                    successBox.style.display = 'block';
                    snippetBox.textContent = res.snippet;

                    // Update active site
                    this.currentSiteId = res.site.id;

                    // Refresh sites list from backend
                    const sitesRes = await Api.getSites();
                    this.updateSiteSelect(sitesRes.sites || []);

                    if (this.currentPage === 'websites') {
                        WebsitesPage.load();
                    }

                } catch (err) {
                    errorBox.textContent = err.message || 'Failed to create website';
                    errorBox.style.display = 'block';
                } finally {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Create Website';
                }
            });
        }

        if (copyBtn) {
            copyBtn.addEventListener('click', () => {
                const text = snippetBox.textContent;
                navigator.clipboard.writeText(text).then(() => {
                    const original = copyBtn.textContent;
                    copyBtn.textContent = 'Copied!';
                    setTimeout(() => copyBtn.textContent = original, 2000);
                });
            });
        }

        if (goToSiteBtn) {
            goToSiteBtn.addEventListener('click', () => {
                modal.classList.remove('active');
                this.selectSiteAndOpen(this.currentSiteId);
            });
        }
    },

    selectSiteAndOpen(siteId) {
        if (!siteId) return;
        this.currentSiteId = siteId;
        this.updateSidebarSiteInfo(siteId);

        const url = new URL(window.location);
        url.searchParams.set('site', siteId);
        url.hash = '#overview';
        window.history.pushState({}, '', url);

        this.navigateTo('overview');
    },

    async updateSidebarSiteInfo(siteId) {
        if (!siteId) return;

        let site = (WebsitesPage.sites || []).find(s => s.id === siteId);
        if (!site) {
            try {
                const res = await Api.getSites();
                WebsitesPage.sites = res.sites || [];
                site = (WebsitesPage.sites || []).find(s => s.id === siteId);
            } catch (e) {
                // Ignore
            }
        }

        const siteName = site ? (site.name || site.id) : siteId;
        const siteDomain = site && site.domain ? site.domain : siteId;

        const nameEl = document.getElementById('sidebarSiteName');
        const domEl = document.getElementById('sidebarSiteDomain');
        const logoImg = document.getElementById('sidebarSiteLogo');
        const fallbackEl = document.getElementById('sidebarSiteFallback');

        if (nameEl) nameEl.textContent = siteName;
        if (domEl) domEl.textContent = siteDomain;

        if (logoImg && fallbackEl) {
            const cleanDomain = (siteDomain || '').replace(/^https?:\/\//, '').replace(/\/.*$/, '').split(':')[0].trim();
            if (cleanDomain && cleanDomain !== 'localhost' && cleanDomain !== '127.0.0.1' && cleanDomain.includes('.')) {
                logoImg.onload = () => {
                    logoImg.style.display = 'block';
                    fallbackEl.style.display = 'none';
                };
                logoImg.onerror = () => {
                    logoImg.style.display = 'none';
                    fallbackEl.style.display = 'flex';
                };
                logoImg.src = `https://www.google.com/s2/favicons?domain=${encodeURIComponent(cleanDomain)}&sz=64`;
            } else {
                logoImg.style.display = 'none';
                fallbackEl.style.display = 'flex';
            }
        }
    },

    async handleRoute() {
        const urlParams = new URLSearchParams(window.location.search);
        const siteParam = urlParams.get('site') || urlParams.get('site_id');
        const hash = window.location.hash.replace('#', '').trim();

        if (siteParam && siteParam !== 'all') {
            this.currentSiteId = siteParam;
            // If user loaded ?site=... with no hash or #websites, navigate to #overview
            const targetPage = (!hash || hash === 'websites') ? 'overview' : hash;
            if (window.location.hash !== `#${targetPage}`) {
                window.location.hash = `#${targetPage}`;
            }
            this.navigateTo(targetPage);
            return;
        }

        // No ?site= param in URL
        if (hash === 'overview' || hash === 'sessions' || hash === 'events') {
            // Analytics requested without site param -> auto-select first available site (never 'all')
            try {
                const res = await Api.getSites();
                const sites = res.sites || [];
                const firstSite = sites[0] ? sites[0].id : 'demo_site';
                this.currentSiteId = firstSite;

                const url = new URL(window.location);
                url.searchParams.set('site', firstSite);
                window.history.replaceState({}, '', url);

                this.navigateTo(hash);
            } catch (e) {
                this.currentSiteId = 'demo_site';
                this.navigateTo(hash);
            }
            return;
        }

        // Default to websites portal page
        this.navigateTo('websites');
    },

    navigateTo(pageName) {
        if (!['websites', 'overview', 'events', 'sessions'].includes(pageName)) {
            pageName = 'websites';
        }

        this.currentPage = pageName;

        const appContainer = document.querySelector('.app-container');
        if (appContainer) {
            if (pageName === 'websites') {
                appContainer.classList.add('is-portal');
            } else {
                appContainer.classList.remove('is-portal');
                // Ensure sidebar info is updated for active site
                if (this.currentSiteId) {
                    this.updateSidebarSiteInfo(this.currentSiteId);
                }
            }
        }

        // Update nav active state
        document.querySelectorAll('.nav-item').forEach(item => {
            const page = item.dataset.page;
            if (page === pageName) {
                item.classList.add('active');
            } else {
                item.classList.remove('active');
            }
        });

        // Update views visibility
        document.querySelectorAll('.page-view').forEach(view => {
            if (view.id === `page-${pageName}`) {
                view.classList.add('active');
            } else {
                view.classList.remove('active');
            }
        });

        // Update header title
        const titles = {
            websites: 'Websites & Projects',
            overview: 'Analytics Overview',
            events: 'Events Stream',
            sessions: 'User Sessions'
        };
        const titleElem = document.getElementById('headerPageTitle');
        if (titleElem) {
            titleElem.textContent = titles[pageName] || 'Dashboard';
        }

        // Load data for the active page
        this.refreshCurrentPage();
    },

    refreshCurrentPage(silent = false) {
        if (this.currentPage === 'websites') {
            WebsitesPage.load();
        } else if (this.currentPage === 'overview') {
            OverviewPage.load(this.currentRange, this.currentSiteId);
        } else if (this.currentPage === 'events') {
            EventsPage.load(this.currentRange, this.currentSiteId);
        } else if (this.currentPage === 'sessions') {
            SessionsPage.load(this.currentRange, this.currentSiteId);
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

document.addEventListener('DOMContentLoaded', () => {
    App.init();
});

window.App = App;
