<!-- Page: Events Stream & Explorer -->
<div class="page-view" id="page-events">
    <div class="page-header-box">
        <div class="page-intro">
            <h1>Events Explorer</h1>
            <p>Live stream of tracked pageviews, custom interactions, and system events.</p>
        </div>
    </div>

    <!-- Filter & Search Toolbar -->
    <div class="filter-toolbar">
        <div class="search-input-wrapper">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="11" cy="11" r="8"></circle>
                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
            </svg>
            <input type="text" class="search-input" id="eventSearch" placeholder="Search by event name, session ID, or path...">
        </div>

        <select class="select-filter" id="eventTypeFilter">
            <option value="all">All Events</option>
        </select>

        <!-- Active session filter badge -->
        <div id="activeSessionFilterBox" style="display: none; align-items: center; gap: 6px; background: #e0f2fe; color: #0284c7; padding: 5px 12px; border-radius: var(--radius-md); font-size: 12.5px; font-weight: 600;">
            <span>Session: <span id="activeSessionIdLabel"></span></span>
            <button type="button" id="clearSessionFilterBtn" style="border: none; background: transparent; cursor: pointer; color: #0284c7; font-size: 14px; padding: 0 2px;">✕</button>
        </div>
    </div>

    <!-- Event Type Distribution -->
    <div class="data-table-card" style="margin-bottom: 20px;">
        <div class="events-card-header">
            <span class="events-card-title">Event Distribution</span>
            <span style="font-size: 12px; color: var(--text-muted); font-weight: 500;">Click a row to filter</span>
        </div>
        <div id="eventsDistribution" style="padding: 8px 0;">
            <div style="padding: 32px; text-align: center; color: var(--text-muted); font-size: 13px;">Loading…</div>
        </div>
        <div class="pagination-footer" style="border-top: 1px solid var(--border);">
            <span id="eventsTotalCount" style="font-size: 13px; color: var(--text-muted);">0 events</span>
        </div>
    </div>


    <!-- Events Stream Card with Clean List -->

    <div class="data-table-card events-stream-card">
        <div class="events-card-header">
            <span class="events-card-title">Real-Time Event Stream</span>
            <span id="eventsPageInfo" style="font-size: 13px; font-weight: 600; color: var(--text-muted);">Page 1 of 1</span>
        </div>

        <!-- Clean Event Feed List -->
        <div class="events-list-container" id="eventsListContainer">
            <div class="empty-state" style="padding: 40px; text-align: center; color: var(--text-muted);">Loading events...</div>
        </div>

        <!-- Pagination Controls -->
        <div class="pagination-footer">
            <span id="eventsFooterCount" style="font-size: 13px; color: var(--text-muted);">Showing events</span>
            <div class="pagination-controls">
                <button type="button" class="btn-page" id="eventsPrevPage">Previous</button>
                <button type="button" class="btn-page" id="eventsNextPage">Next</button>
            </div>
        </div>
    </div>
</div>
