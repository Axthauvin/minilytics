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
