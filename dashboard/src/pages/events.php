<!-- Page: Events Stream & Explorer -->
<div class="page-view" id="page-events">
    <div class="page-header-box">
        <div class="page-intro">
            <h1>Events Explorer</h1>
            <p>Live stream of tracked pageviews, custom interactions, and system events.</p>
        </div>
    </div>

    <!-- Active session filter -->
    <div class="filter-toolbar">
        <div id="activeSessionFilterBox" style="display: none; align-items: center; gap: 6px; background: #e0f2fe; color: #0284c7; padding: 5px 12px; border-radius: var(--radius-md); font-size: 12.5px; font-weight: 600;">
            <span>Session: <span id="activeSessionIdLabel"></span></span>
            <button type="button" id="clearSessionFilterBtn" style="border: none; background: transparent; cursor: pointer; color: #0284c7; font-size: 14px; padding: 0 2px;">✕</button>
        </div>
    </div>
    <!-- Event trend: multiple event series can be compared over the active period. -->
    <div class="chart-container-card full-width-chart" style="margin-bottom: 24px;">
        <div class="event-insights-strip" id="eventInsightsStrip" aria-label="Event insights"></div>
        <div class="chart-header">
            <div>
                <span class="chart-eyebrow">EVENT MONITORING</span>
                <div class="chart-legend-metrics-row">
                    <div class="chart-metric-indicator">
                        <span class="metric-color-dot" style="background: #f59e0b;"></span>
                        <span class="metric-name" id="eventsTrendLabel">All tracked events</span>
                    </div>
                </div>
            </div>
            <div class="event-series-combobox" id="eventsSeriesCombobox">
                <button type="button" class="event-series-combobox-trigger" id="eventsSeriesTrigger" aria-expanded="false" aria-controls="eventsSeriesMenu">
                    <span id="eventsSeriesSummary">All events</span>
                    <span aria-hidden="true">⌄</span>
                </button>
                <div class="event-series-combobox-menu" id="eventsSeriesMenu" hidden>
                    <input type="search" class="event-series-search" id="eventsSeriesSearch" placeholder="Search events…" autocomplete="off" aria-label="Search event series">
                    <div class="event-series-actions">
                        <button type="button" id="eventsSeriesSelectAll">Select all</button>
                        <button type="button" id="eventsSeriesClear">Clear</button>
                    </div>
                    <div class="event-series-options" id="eventsSeriesOptions" aria-label="Event series"></div>
                </div>
            </div>
        </div>
        <div class="canvas-wrapper">
            <canvas id="eventsTrendChart" class="chart-canvas"></canvas>
            <div id="eventsTrendTooltip" class="chart-tooltip"></div>
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
