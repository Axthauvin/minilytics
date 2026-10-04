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
            <div class="code-block-wrapper">
                <div class="code-block-header">
                    <div class="code-block-dots">
                        <span class="code-dot code-dot-red"></span>
                        <span class="code-dot code-dot-yellow"></span>
                        <span class="code-dot code-dot-green"></span>
                        <span class="code-block-title">JSON Payload</span>
                    </div>
                    <button type="button" class="btn-copy-code" data-copy-target="#modalJsonContent" title="Copy JSON">
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
                <pre class="code-box" id="modalJsonContent"></pre>
            </div>
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
            <div class="modal-summary-grid" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 24px; background: #f8fafc; padding: 14px; border-radius: var(--radius-md); border: 1px solid var(--border-card);">
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

<!-- 4. Data Import Modal -->
<div class="modal-overlay" id="importModal">
    <div class="modal-dialog import-dialog">
        <div class="modal-header">
            <div>
                <h3 class="modal-title">Import Analytics Data</h3>
                <span style="font-size: 13px; color: var(--text-secondary); display: block; margin-top: 2px;">
                    Migrate historical tracking data from external platforms into Minilytics.
                </span>
            </div>
            <button type="button" class="btn-close" data-close-modal title="Close">✕</button>
        </div>
        <div class="modal-body">
            <div class="import-stepper" aria-label="Import steps">
                <span class="active" data-step-indicator="1">1. Platform</span><span data-step-indicator="2">2. Export</span><span data-step-indicator="3">3. Website</span>
            </div>

            <section class="import-step" data-import-step="1">
                <div class="provider-section-title">Select Analytics Source</div>
                <div class="providers-grid" id="importProvidersGrid">
                    <!-- Umami (Active) -->
                    <div class="provider-card selected" data-provider="umami">
                        <div class="provider-card-top">
                            <div class="provider-icon-wrapper">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" />
                                    <path d="M12 2a10 10 0 0 1 10 10c0 5.52-4.48 10-10 10" fill="currentColor" />
                                </svg>
                            </div>
                            <span class="provider-badge badge-ready">Available</span>
                        </div>
                        <div class="provider-name">Umami</div>
                        <div class="provider-desc">Import events & custom data from an Umami ZIP export</div>
                    </div>

                    <!-- Google Analytics (GA4) (Soon) -->
                    <div class="provider-card disabled" data-provider="google_analytics" title="Google Analytics importer is coming soon">
                        <div class="provider-card-top">
                            <div class="provider-icon-wrapper">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M18 20V10M12 20V4M6 20v-6" />
                                </svg>
                            </div>
                            <span class="provider-badge badge-soon">Soon</span>
                        </div>
                        <div class="provider-name">Google Analytics</div>
                        <div class="provider-desc">GA4 BigQuery export & CSV reports</div>
                    </div>

                    <!-- Plausible Analytics (Soon) -->
                    <div class="provider-card disabled" data-provider="plausible" title="Plausible Analytics importer is coming soon">
                        <div class="provider-card-top">
                            <div class="provider-icon-wrapper">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="22 12 18 12 15 21 9 3 6 12 2 12" />
                                </svg>
                            </div>
                            <span class="provider-badge badge-soon">Soon</span>
                        </div>
                        <div class="provider-name">Plausible</div>
                        <div class="provider-desc">Plausible CSV goal & visit dumps</div>
                    </div>

                    <!-- Matomo (Soon) -->
                    <div class="provider-card disabled" data-provider="matomo" title="Matomo importer is coming soon">
                        <div class="provider-card-top">
                            <div class="provider-icon-wrapper">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10" />
                                    <path d="M12 6v6l4 2" />
                                </svg>
                            </div>
                            <span class="provider-badge badge-soon">Soon</span>
                        </div>
                        <div class="provider-name">Matomo</div>
                        <div class="provider-desc">Piwik / Matomo database archives</div>
                    </div>

                    <!-- Simple Analytics (Soon) -->
                    <div class="provider-card disabled" data-provider="simple_analytics" title="Simple Analytics importer is coming soon">
                        <div class="provider-card-top">
                            <div class="provider-icon-wrapper">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M3 3v18h18M18 9l-5 5-4-4-3 3" />
                                </svg>
                            </div>
                            <span class="provider-badge badge-soon">Soon</span>
                        </div>
                        <div class="provider-name">Simple Analytics</div>
                        <div class="provider-desc">Privacy-first analytics export files</div>
                    </div>
                </div>

                <div class="import-step-actions">
                    <button type="button" class="btn-primary import-next-button" id="btnImportProviderNext">
                        <span>Continue</span>
                        <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                            <polyline points="12 5 19 12 12 19"></polyline>
                        </svg>
                    </button>
                </div>
            </section>

            <form id="importForm">
                <input type="hidden" id="importSelectedProvider" name="provider" value="umami">

                <div id="importErrorAlert" style="display: none; padding: 10px 14px; background: #fef2f2; color: #b91c1c; border-radius: var(--radius-md); font-size: 13px; margin-bottom: 16px; border: 1px solid #fecaca;"></div>

                <section class="import-step" data-import-step="2" hidden>
                    <div class="provider-section-title">Upload Umami Export Archive</div>
                    <div class="import-dropzone" id="importDropzone">
                        <input type="file" id="importFileInput" name="file" accept=".zip,.csv" style="display: none;">
                        <div class="dropzone-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                                <polyline points="17 8 12 3 7 8" />
                                <line x1="12" y1="3" x2="12" y2="15" />
                            </svg>
                        </div>
                        <div class="dropzone-title">Click to upload or drag & drop your Umami .zip export</div>
                        <div class="dropzone-hint">Supports full Umami export archives (containing website_event.csv and event_data.csv)</div>

                        <!-- Selected file meta box -->
                        <div class="selected-file-card" id="selectedFileCard" style="display: none;">
                            <div class="file-info-left">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#6366f1" stroke-width="2">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                                    <polyline points="14 2 14 8 20 8" />
                                </svg>
                                <div style="text-align: left;">
                                    <div class="file-meta-name" id="selectedFileName">export.zip</div>
                                    <div class="file-meta-size" id="selectedFileSize">0 KB</div>
                                </div>
                            </div>
                            <button type="button" class="file-remove-btn" id="btnRemoveSelectedFile" title="Remove file">✕</button>
                        </div>
                    </div>

                    <!-- Server/Local Path Toggle -->
                    <a href="javascript:void(0)" class="local-path-toggle" id="btnToggleLocalPath">
                        <span>Or load from server/local folder or path</span>
                    </a>

                    <div class="local-path-box" id="localPathContainer" style="display: none;">
                        <label style="display: block; font-size: 12px; font-weight: 600; color: var(--text-primary); margin-bottom: 4px;">
                            Server Path or Directory:
                        </label>
                        <input type="text" id="importServerPath" name="server_path" placeholder="e.g. umami-export-sample.zip or umami-import" class="search-input" style="padding-left: 12px; font-family: monospace; font-size: 13px;">
                        <div>
                            <span style="font-size: 11px; color: var(--text-muted); margin-top: 6px; display: inline-block;">Quick select detected files:</span>
                            <span class="quick-path-chip" data-path="umami-export-sample.zip">umami-export-sample.zip</span>
                            <span class="quick-path-chip" data-path="umami-import">umami-import/</span>
                        </div>
                    </div>

                    <div class="import-step-actions">
                        <button type="button" class="btn-outline" id="btnImportUploadBack">Back</button>
                        <button type="button" class="btn-primary import-next-button" id="btnImportUploadNext" disabled aria-disabled="true">
                            <span>Continue</span>
                            <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                                <polyline points="12 5 19 12 12 19"></polyline>
                            </svg>
                        </button>
                    </div>
                </section>

                <section class="import-step" data-import-step="3" hidden>
                    <div class="provider-section-title" style="margin-top: 18px;">Choose Destination Website</div>
                    <div style="display: flex; gap: 20px; margin-bottom: 14px;">
                        <label style="display: flex; align-items: center; gap: 6px; font-size: 13px; cursor: pointer;">
                            <input type="radio" name="target_mode" value="new" checked id="radioTargetNew">
                            <span>Create new website</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 6px; font-size: 13px; cursor: pointer;">
                            <input type="radio" name="target_mode" value="existing" id="radioTargetExisting">
                            <span>Import into existing website</span>
                        </label>
                    </div>

                    <!-- Existing Site Selector -->
                    <div id="targetExistingBox" style="display: none; margin-bottom: 16px;">
                        <label style="display: block; font-size: 12.5px; font-weight: 600; color: var(--text-primary); margin-bottom: 6px;">Select Website</label>
                        <select id="importExistingSiteSelect" class="search-input" style="padding-left: 10px; width: 100%;">
                            <!-- Populated dynamically -->
                        </select>
                    </div>

                    <!-- New Site Inputs -->
                    <div id="targetNewBox">
                        <div class="import-detected-hint" id="importDetectedWebsiteHint" hidden>
                            <strong>Detected from your export</strong>
                            <span>We prefilled these website details from the analytics data. You can change any field before importing.</span>
                        </div>
                        <div class="import-site-fields" style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                            <div>
                                <label style="display: block; font-size: 12.5px; font-weight: 600; color: var(--text-primary); margin-bottom: 4px;">Website Name</label>
                                <input type="text" id="importNewSiteName" placeholder="e.g. Example" class="search-input" style="padding-left: 10px;">
                            </div>
                            <div>
                                <label style="display: block; font-size: 12.5px; font-weight: 600; color: var(--text-primary); margin-bottom: 4px;">Site ID</label>
                                <input type="text" id="importNewSiteId" placeholder="e.g. example" class="search-input" style="padding-left: 10px; font-family: monospace;">
                            </div>
                        </div>
                        <div style="margin-bottom: 16px;">
                            <label style="display: block; font-size: 12.5px; font-weight: 600; color: var(--text-primary); margin-bottom: 4px;">Domain (Optional)</label>
                            <input type="text" id="importNewSiteDomain" placeholder="e.g. example.com" class="search-input" style="padding-left: 10px;">
                        </div>
                    </div>

                    <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
                        <button type="button" class="btn-outline" id="btnImportDestinationBack">Back</button>
                        <button type="submit" class="btn-primary" id="btnSubmitImport">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                                <polyline points="17 8 12 3 7 8" />
                                <line x1="12" y1="3" x2="12" y2="15" />
                            </svg>
                            Start Import
                        </button>
                    </div>
                </section>
            </form>

            <!-- Loading / Progress State -->
            <div id="importProgressView" style="display: none; text-align: center; padding: 24px 0;">
                <div style="width: 48px; height: 48px; border-radius: 50%; background: #e0e7ff; color: #4f46e5; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px auto;">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="animate-spin">
                        <line x1="12" y1="2" x2="12" y2="6" />
                        <line x1="12" y1="18" x2="12" y2="22" />
                        <line x1="4.93" y1="4.93" x2="7.76" y2="7.76" />
                        <line x1="16.24" y1="16.24" x2="19.07" y2="19.07" />
                        <line x1="2" y1="12" x2="6" y2="12" />
                        <line x1="18" y1="12" x2="22" y2="12" />
                        <line x1="4.93" y1="19.07" x2="7.76" y2="16.24" />
                        <line x1="16.24" y1="7.76" x2="19.07" y2="4.93" />
                    </svg>
                </div>
                <h4 style="font-size: 16px; font-weight: 700; color: var(--text-primary); margin-bottom: 4px;" id="importProgressTitle">Importing Umami Data...</h4>
                <div class="progress-bar-track">
                    <div class="progress-bar-fill indeterminate" id="importProgressBar"></div>
                </div>
                <p class="import-status-text" id="importProgressStatus">Extracting archive and processing events...</p>
            </div>

            <!-- Success Summary State -->
            <div id="importSuccessView" style="display: none; text-align: center; padding: 16px 0;">
                <div style="width: 52px; height: 52px; border-radius: 50%; background: #ecfdf5; color: #059669; display: flex; align-items: center; justify-content: center; margin: 0 auto 14px auto;">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                </div>
                <h3 style="font-size: 19px; font-weight: 800; color: var(--text-primary); margin-bottom: 6px;">Import Successfully Completed!</h3>
                <p style="font-size: 13.5px; color: var(--text-secondary); margin-bottom: 18px;" id="importSuccessSubtext">
                    Historical analytics were loaded into your Minilytics database.
                </p>

                <!-- Key Metrics Grid -->
                <div class="import-stats-grid">
                    <div class="import-stat-card">
                        <div class="import-stat-num" id="resTotalEvents">0</div>
                        <div class="import-stat-label">Total Events</div>
                    </div>
                    <div class="import-stat-card">
                        <div class="import-stat-num" id="resPageviews">0</div>
                        <div class="import-stat-label">Pageviews</div>
                    </div>
                    <div class="import-stat-card">
                        <div class="import-stat-num" id="resCustomEvents">0</div>
                        <div class="import-stat-label">Custom Events</div>
                    </div>
                    <div class="import-stat-card">
                        <div class="import-stat-num" id="resSessions">0</div>
                        <div class="import-stat-label">Sessions</div>
                    </div>
                    <div class="import-stat-card" style="grid-column: span 2;">
                        <div class="import-stat-num" id="resDateRange" style="font-size: 14px; font-weight: 700;">-</div>
                        <div class="import-stat-label">Date Range Covered</div>
                    </div>
                </div>

                <div style="display: flex; gap: 10px; justify-content: center;">
                    <button type="button" class="btn-outline" data-close-modal>Close</button>
                    <button type="button" class="btn-primary" id="btnGoToImportedSite">
                        View Website Analytics
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
