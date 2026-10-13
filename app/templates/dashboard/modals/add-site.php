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
                    <label style="display: block; font-size: 13px; font-weight: 600; color: var(--text-primary); margin-bottom: 6px;">Domain (required, used to protect collection)</label>
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

                <div class="code-block-wrapper" style="text-align: left; margin: 16px 0 20px 0;">
                    <div class="code-block-header">
                        <div class="code-block-dots">
                            <span class="code-dot code-dot-red"></span>
                            <span class="code-dot code-dot-yellow"></span>
                            <span class="code-dot code-dot-green"></span>
                            <span class="code-block-title">HTML to insert</span>
                        </div>
                        <button type="button" class="btn-copy-code" id="btnCopySnippet" data-copy-target="#createdSiteSnippet" title="Copy tracking tag">
                            <svg class="copy-icon" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                            </svg>
                            <svg class="check-icon" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="display: none;">
                                <polyline points="20 6 9 17 4 12"></polyline>
                            </svg>
                            <span class="copy-text">Copy</span>
                        </button>
                    </div>
                    <pre class="code-box" id="createdSiteSnippet"></pre>
                </div>

                <button type="button" class="btn-primary" id="btnGoToCreatedSite" style="width: 100%; justify-content: center;">
                    View Website Analytics
                </button>
            </div>
        </div>
    </div>
</div>
