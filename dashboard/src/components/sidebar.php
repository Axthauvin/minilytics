<!-- Sleek Gray Sidebar (Minimalist with Text Labels & Back Navigation) -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-top">
        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 16px; padding: 2px 2px;">
            <svg width="24" height="24" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                <rect width="32" height="32" rx="9" fill="#0f172a"/>
                <path d="M7.5 22L12.5 15L17.5 18.5L24.5 9.5" stroke="#6366f1" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/>
                <circle cx="24.5" cy="9.5" r="2.2" fill="#818cf8"/>
            </svg>
            <span style="font-weight: 800; font-size: 15px; color: var(--text-primary); letter-spacing: -0.02em;">Minilytics</span>
        </div>

        <!-- Back to websites button (Primary navigation out of individual site) -->
        <a href="#websites" class="btn-back-websites" id="btnBackToWebsites" title="Back to All Websites">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="19" y1="12" x2="5" y2="12"></line>
                <polyline points="12 19 5 12 12 5"></polyline>
            </svg>
            <span>All Websites</span>
        </a>

        <!-- Current Active Website Info Card -->
        <div class="sidebar-site-box" id="sidebarSiteBox" title="Current Website Analytics">
            <div class="site-avatar" id="sidebarSiteAvatar">
                <img id="sidebarSiteLogo" class="site-avatar-img" src="" alt="" style="display: none;" />
                <span id="sidebarSiteFallback" class="site-avatar-fallback">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"></path>
                        <path d="M2 12h20"></path>
                    </svg>
                </span>
            </div>
            <div class="site-meta">
                <div class="site-name" id="sidebarSiteName">Demo Site</div>
                <div class="site-sub">
                    <span class="live-dot-green"></span>
                    <span id="sidebarSiteDomain">demo_site</span>
                </div>
            </div>
        </div>

        <div class="nav-section-label">Analytics</div>

        <!-- Navigation with Icons + Text Titles -->
        <nav class="sidebar-nav">
            <ul class="nav-list">
                <!-- 1. Overview -->
                <li class="nav-item active" data-page="overview">
                    <a href="#overview">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="7" height="7"></rect>
                            <rect x="14" y="3" width="7" height="7"></rect>
                            <rect x="14" y="14" width="7" height="7"></rect>
                            <rect x="3" y="14" width="7" height="7"></rect>
                        </svg>
                        <span class="nav-title">Overview</span>
                    </a>
                </li>

                <!-- 2. Sessions -->
                <li class="nav-item" data-page="sessions">
                    <a href="#sessions">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                            <circle cx="9" cy="7" r="4"></circle>
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                        </svg>
                        <span class="nav-title">Sessions</span>
                    </a>
                </li>

                <!-- 3. Events -->
                <li class="nav-item" data-page="events">
                    <a href="#events">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>
                        </svg>
                        <span class="nav-title">Events</span>
                    </a>
                </li>
            </ul>
        </nav>
    </div>

    <!-- Bottom Actions -->
    <div class="sidebar-footer">
        <a href="/demo.html" target="_blank" class="footer-link" title="Open Tracking Demo Site">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"></circle>
                <polygon points="10 8 16 12 10 16 10 8"></polygon>
            </svg>
            <span>Live Demo Page</span>
        </a>

        <div class="sidebar-user-row">
            <div class="user-avatar">A</div>
            <div class="user-info">
                <span class="user-name">Admin</span>
                <span class="user-role">Minilytics Local</span>
            </div>
        </div>
    </div>
</aside>
