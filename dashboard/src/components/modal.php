<!-- Modals Component -->

<!-- 1. Event Payload Inspector Modal -->
<div class="modal-overlay" id="payloadModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title">Event Payload Details</h3>
            <button type="button" class="btn-close" data-close-modal title="Close">✕</button>
        </div>
        <div class="modal-body">
            <p style="font-size: 13px; color: var(--text-secondary); margin-bottom: 12px;">
                Raw structured activity JSON recorded for this action:
            </p>
            <pre class="json-box" id="modalJsonContent"></pre>
        </div>
    </div>
</div>

<!-- 2. Session Journey Inspector Modal -->
<div class="modal-overlay" id="sessionDetailModal">
    <div class="modal-dialog" style="max-width: 680px;">
        <div class="modal-header">
            <div>
                <h3 class="modal-title">Session Journey Inspector</h3>
                <span id="inspectSessionId" style="font-family: monospace; font-size: 12px; color: var(--text-muted);"></span>
            </div>
            <button type="button" class="btn-close" data-close-modal title="Close">✕</button>
        </div>
        <div class="modal-body">
            <!-- Metadata summary row -->
            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 24px; background: #f8fafc; padding: 14px; border-radius: var(--radius-md); border: 1px solid var(--border-card);">
                <div>
                    <div style="font-size: 11px; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">Duration</div>
                    <div id="inspectDuration" style="font-size: 15px; font-weight: 700; color: var(--text-primary);">-</div>
                </div>
                <div>
                    <div style="font-size: 11px; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">Total Actions</div>
                    <div id="inspectEventCount" style="font-size: 15px; font-weight: 700; color: var(--text-primary);">-</div>
                </div>
                <div>
                    <div style="font-size: 11px; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">Landing Page</div>
                    <div id="inspectEntryPage" style="font-size: 13px; font-weight: 600; color: var(--text-primary); overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">-</div>
                </div>
                <div>
                    <div style="font-size: 11px; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">Referrer</div>
                    <div id="inspectReferrer" style="font-size: 13px; font-weight: 600; color: var(--text-primary); overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">-</div>
                </div>
            </div>

            <h4 style="font-size: 14px; font-weight: 700; margin-bottom: 16px; color: var(--text-primary);">
                Activity Timeline
            </h4>

            <!-- Timeline items -->
            <div class="timeline" id="sessionTimeline">
                <!-- Populated dynamically by SessionsPage.inspectSession -->
            </div>
        </div>
    </div>
</div>

<!-- 3. Add Website Modal -->
<div class="modal-overlay" id="addSiteModal">
    <div class="modal-dialog" style="max-width: 540px;">
        <div class="modal-header">
            <h3 class="modal-title">Add New Website</h3>
            <button type="button" class="btn-close" data-close-modal title="Close">✕</button>
        </div>
        <div class="modal-body">
            <!-- Form view -->
            <form id="addSiteForm">
                <div id="addSiteError" style="display: none; padding: 10px 14px; background: #fef2f2; color: #b91c1c; border-radius: var(--radius-md); font-size: 13px; margin-bottom: 16px; border: 1px solid #fecaca;"></div>

                <div style="margin-bottom: 16px;">
                    <label style="display: block; font-size: 13px; font-weight: 600; color: var(--text-primary); margin-bottom: 6px;">Website Name</label>
                    <input type="text" id="newSiteName" required placeholder="e.g. My SaaS App, Blog, Online Store" class="search-input" style="padding-left: 12px;">
                </div>

                <div style="margin-bottom: 16px;">
                    <label style="display: block; font-size: 13px; font-weight: 600; color: var(--text-primary); margin-bottom: 6px;">Website Identifier (Site ID)</label>
                    <input type="text" id="newSiteId" required placeholder="e.g. my_saas_app" class="search-input" style="padding-left: 12px; font-family: monospace;">
                    <span style="font-size: 12px; color: var(--text-muted); display: block; margin-top: 4px;">Used in data-site-id attribute. A dedicated database <code style="background: #f1f5f9; padding: 2px 5px; border-radius: 4px;">data/&lt;site_id&gt;.db</code> will be created automatically.</span>
                </div>

                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-size: 13px; font-weight: 600; color: var(--text-primary); margin-bottom: 6px;">Domain (Optional)</label>
                    <input type="text" id="newSiteDomain" placeholder="e.g. example.com or localhost:3000" class="search-input" style="padding-left: 12px;">
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" class="btn-outline" data-close-modal>Cancel</button>
                    <button type="submit" class="btn-primary" id="btnSubmitNewSite">Create Website</button>
                </div>
            </form>

            <!-- Success view (shown after creation) -->
            <div id="addSiteSuccess" style="display: none; text-align: center; padding: 10px 0;">
                <div style="width: 48px; height: 48px; border-radius: 50%; background: #ecfdf5; color: #059669; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px auto;">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                </div>
                <h3 style="font-size: 18px; font-weight: 700; margin-bottom: 6px;">Website Ready!</h3>
                <p style="font-size: 13.5px; color: var(--text-secondary); margin-bottom: 16px;">
                    Copy this tag and insert it into the <code style="background: #f1f5f9; padding: 2px 6px; border-radius: 4px;">&lt;head&gt;</code> of your website:
                </p>

                <div style="position: relative; margin-bottom: 20px;">
                    <pre class="json-box" id="createdSiteSnippet" style="text-align: left; padding-right: 70px; color: #e2e8f0; font-size: 12px;"></pre>
                    <button type="button" class="btn-outline" id="btnCopySnippet" style="position: absolute; right: 10px; top: 10px; font-size: 12px; padding: 4px 10px;">
                        Copy
                    </button>
                </div>

                <button type="button" class="btn-primary" id="btnGoToCreatedSite" style="width: 100%; justify-content: center;">
                    View Website Analytics
                </button>
            </div>
        </div>
    </div>
</div>
