<!-- Funnel analysis: simple, ordered journeys assembled from existing events and page views. -->
<div class="page-view" id="page-funnels">
    <div class="funnel-page-head">
        <div class="page-intro">
            <h1>Funels</h1>
            <p>Build a funnel from the page views and events you already track. Steps are counted in the order visitors complete them.</p>
        </div>
        <button class="btn-funnel-create" id="btnCreateFunnel" type="button">
            <span>+</span> Create funnel
        </button>
    </div>

    <div class="funnel-layout">
        <aside class="funnel-list-panel">
            <div class="funnel-list-heading"><span>Your funnels</span><span id="funnelsCount" class="funnel-count">0</span></div>
            <div id="funnelsList" class="funnels-list">
                <div class="funnel-list-empty">Loading your funnels…</div>
            </div>
        </aside>

        <section id="funnelWorkspace" class="funnel-workspace">
            <div class="funnel-page-loader" role="status" aria-live="polite">
                <span class="funnel-loader-ring" aria-hidden="true"></span>
                <span>Loading funnels…</span>
            </div>
        </section>
    </div>

    <div id="funnelBuilder" class="funnel-builder-overlay" aria-hidden="true">
        <div class="funnel-builder" role="dialog" aria-modal="true" aria-labelledby="builderTitle">
            <div class="funnel-builder-header">
                <div><h2 id="builderTitle">Create a funnel</h2></div>
                <button id="btnCloseFunnelBuilder" class="funnel-close" type="button" aria-label="Close">×</button>
            </div>
            <p class="funnel-builder-help">Add the actions visitors should take, in order. You can use page views or any event already recorded.</p>
            <label class="funnel-field-label" for="funnelName">Name</label>
            <input id="funnelName" class="funnel-name-input" maxlength="80" placeholder="e.g. Checkout completion">
            <div class="funnel-builder-steps-head"><span>Steps</span></div>
            <div id="funnelBuilderSteps" class="funnel-builder-steps"></div>
            <div class="funnel-builder-footer"><button id="btnCancelFunnel" type="button" class="btn-secondary-funnel">Cancel</button><button id="btnSaveFunnel" type="button" class="btn-funnel-create">Save funnel</button></div>
        </div>
    </div>
</div>
