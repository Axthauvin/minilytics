<!-- Page: User Sessions -->
<div class="page-view" id="page-sessions">
    <!-- View 1: Sessions List View -->
    <div id="sessionsListView">
        <div class="page-header-box">
            <div class="page-intro">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <h1>Sessions</h1>
                </div>
                <p>Recorded user sessions with real device, browser, country, and chronological actions.</p>
            </div>
        </div>

        <!-- Filter & Search Toolbar -->
        <div class="filter-toolbar" style="margin-bottom: 20px;">
            <div class="search-input-wrapper">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="8"></circle>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                </svg>
                <input type="text" class="search-input" id="sessionSearch" placeholder="Search sessions by ID, path, country...">
            </div>

            <!-- Date Filter (Jour spécifique) -->
            <div class="date-filter-wrapper">
                <svg class="date-filter-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect width="18" height="18" x="3" y="4" rx="2" ry="2"></rect>
                    <line x1="16" y1="2" x2="16" y2="6"></line>
                    <line x1="8" y1="2" x2="8" y2="6"></line>
                    <line x1="3" y1="10" x2="21" y2="10"></line>
                </svg>
                <input type="date" class="date-filter-input" id="sessionDateFilter" title="Filtrer par jour spécifique">
                <button type="button" class="btn-clear-date" id="clearDateFilterBtn" title="Effacer le filtre par jour" style="display: none;">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>

            <!-- Event Filter (Événement spécifique) -->
            <div class="event-filter-wrapper">
                <select class="select-filter" id="sessionEventFilter" title="Filtrer par événement">
                    <option value="all">Tous les événements</option>
                </select>
            </div>

            <!-- Total Sessions Count Badge (Repositionné dans la toolbar) -->
            <div class="sessions-count-wrapper" style="margin-left: auto;">
                <span id="sessionsTotalCount" class="sessions-count-badge">
                    0 sessions
                </span>
            </div>
        </div>

        <!-- Sessions Cards List -->
        <div class="sessions-cards-container" id="sessionsListContainer">
            <div class="data-table-card" style="padding: 32px; text-align: center; color: var(--text-muted);">
                Loading sessions...
            </div>
        </div>

        <!-- Pagination Controls -->
        <div class="pagination-footer" style="margin-top: 16px; background: #ffffff; border: 1px solid var(--border); border-radius: var(--radius-md);">
            <span id="sessionsPageInfo">Page 1 of 1</span>
            <div class="pagination-controls">
                <button type="button" class="btn-page" id="sessionsPrevPage">Previous</button>
                <button type="button" class="btn-page" id="sessionsNextPage">Next</button>
            </div>
        </div>
    </div>

    <!-- View 2: Session Detail Drill-down View (Inspired by User Screenshot) -->
    <div id="sessionDetailView" style="display: none;">
        <!-- Back Button Header -->
        <div style="margin-bottom: 20px;">
            <button type="button" class="btn-back-sessions-list" id="btnBackToSessionsList">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="15 18 9 12 15 6"></polyline>
                </svg>
                <span>Retour à la liste des sessions</span>
            </button>
        </div>

        <!-- 2-Column Inspection Grid -->
        <div class="session-detail-grid">
            <!-- Left Column: Parcours de pages / Page Journey -->
            <div class="session-journey-card">
                <div class="journey-header">
                    <h3 class="journey-title">PARCOURS DE PAGES</h3>
                </div>
                <div class="journey-steps-container" id="journeyStepsList">
                    <!-- Populated dynamically by SessionsPage.renderDetailView -->
                </div>
            </div>

            <!-- Right Column: Infos de session & Contexte -->
            <div class="session-meta-sidebar-card">
                <div class="session-meta-top-box">
                    <div style="display: flex; align-items: center; gap: 14px; margin-bottom: 12px;">
                        <div class="session-detail-avatar-wrap">
                            <img id="detailAvatarImg" src="" alt="Avatar" class="session-detail-avatar-img">
                            <span class="session-status-dot"></span>
                        </div>
                        <div style="min-width: 0; flex: 1;">
                            <div class="session-meta-id-row" style="margin-bottom: 4px;">
                                <span class="session-meta-id-title font-mono" id="detailSessionId">...</span>
                            </div>
                            <div class="session-meta-geo-row" id="detailGeoRow">
                                <span id="detailCountryFlag">🌐</span>
                                <span id="detailCountryName">Unknown</span>
                            </div>
                        </div>
                    </div>
                    <div class="session-meta-tech-row" id="detailTechRow">
                        <span id="detailOs">OS</span>,
                        <span id="detailBrowser">Browser</span>
                    </div>
                </div>

                <div class="session-meta-section">
                    <h4 class="meta-section-title">INFOS DE SESSION</h4>
                    <div class="meta-field-row">
                        <span class="meta-field-label">Durée de la session</span>
                        <span class="meta-field-val" id="detailDuration">0s</span>
                    </div>
                    <div class="meta-field-row">
                        <span class="meta-field-label">Référent</span>
                        <span class="meta-field-val" id="detailReferrer">Direct</span>
                    </div>
                    <div class="meta-field-row">
                        <span class="meta-field-label">Paramètres régionaux</span>
                        <span class="meta-field-val" id="detailLocale">fr</span>
                    </div>
                </div>

                <div class="session-meta-section">
                    <h4 class="meta-section-title">APPAREIL & ENVIRONNEMENT</h4>
                    <div class="meta-field-row">
                        <span class="meta-field-label">Type d'appareil</span>
                        <span class="meta-field-val" id="detailDevice">Desktop</span>
                    </div>
                    <div class="meta-field-row">
                        <span class="meta-field-label">Résolution d'écran</span>
                        <span class="meta-field-val" id="detailScreen">–</span>
                    </div>
                    <div class="meta-field-row">
                        <span class="meta-field-label">Viewport</span>
                        <span class="meta-field-val" id="detailViewport">–</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
