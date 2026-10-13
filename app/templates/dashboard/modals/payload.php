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
