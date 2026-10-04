<div class="page-view" id="page-settings">
    <div class="settings-page-header">
        <div>
            <p class="settings-eyebrow">Workspace</p>
            <h2>Settings</h2>
            <p>Manage the people who are allowed to access Minilytics.</p>
        </div><a class="btn-outline" href="#websites">Back to websites</a>
    </div>
    <section class="settings-card" id="inviteUserCard">
        <div class="settings-card-heading">
            <div>
                <h3>Invite someone</h3>
                <p>Only email addresses added here can create an account.</p>
            </div>
        </div>
        <form id="inviteUserForm" class="invite-form"><label for="inviteEmail">Email address</label>
            <div class="invite-form-row"><input id="inviteEmail" required type="email" placeholder="colleague@example.com" autocomplete="email"><button class="btn-primary" type="submit">Create invitation link</button></div>
        </form>
        <div class="invite-result" id="inviteResult" hidden><strong>Invitation link ready</strong>
            <p>Copy this link and send it to the invited person. It expires in 7 days.</p>
            <div class="invite-link-row"><input id="inviteLink" readonly><button type="button" class="btn-outline" id="copyInviteLink">Copy</button></div>
        </div>
        <p class="settings-feedback" id="inviteFeedback" role="status"></p>
    </section>
    <section class="settings-card">
        <div class="settings-card-heading">
            <div>
                <h3>Authorized users</h3>
                <p>Accounts that already have access to this workspace.</p>
            </div>
        </div>
        <p class="settings-feedback" id="usersListFeedback" role="status"></p>
        <div id="usersList" class="users-list">
            <p class="settings-muted">Loading users…</p>
        </div>
    </section>
    <section class="settings-card" id="trackingSettingsCard">
        <div class="settings-card-heading"><div><h3>Tracking & data protection</h3><p>Allowed domains, internal traffic, retention and the private script key.</p></div></div>
        <form id="trackingSettingsForm" class="tracking-form">
            <div class="tracking-field tracking-field--site"><label for="trackingSiteSelect">Website</label><select id="trackingSiteSelect"></select></div>
            <div class="tracking-field">
                <label for="trackingDomainsInput">Allowed domains</label>
                <div class="tracking-tag-editor" id="trackingDomainsEditor">
                    <div class="tracking-tag-list" id="trackingDomainsList" aria-live="polite"></div>
                    <div class="tracking-tag-add"><input id="trackingDomainsInput" placeholder="example.com" autocomplete="off"><button class="btn-outline btn-sm" id="addTrackingDomain" type="button">Add</button></div>
                </div>
                <small>Add one domain at a time. Only these domains can send events for this website.</small>
            </div>
            <div class="tracking-field">
                <label for="trackingInternalIpsInput">Internal IP addresses to ignore</label>
                <div class="tracking-tag-editor" id="trackingInternalIpsEditor">
                    <div class="tracking-tag-list" id="trackingInternalIpsList" aria-live="polite"></div>
                    <div class="tracking-tag-add"><input id="trackingInternalIpsInput" placeholder="203.0.113.10" autocomplete="off"><button class="btn-outline btn-sm" id="addTrackingInternalIp" type="button">Add</button></div>
                </div>
                <small>Add one address at a time. They are visible only in the ignored traffic log.</small>
            </div>
            <div class="tracking-field tracking-field--retention"><label for="trackingRetention">Data retention</label><div class="tracking-number"><input id="trackingRetention" type="number" min="1" max="760" value="395"><span>days</span></div></div>
            <label class="tracking-checkbox"><input id="trackingRotateKey" type="checkbox"><span><strong>Rotate the tracking key</strong><small>The current snippet will stop working until it is replaced.</small></span></label>
            <div class="tracking-actions"><button class="btn-primary" type="submit">Save tracking settings</button></div>
        </form>
        <div class="invite-result" id="trackingSnippetResult" hidden><strong>Updated tracking snippet</strong><p>Replace the existing script tag if you rotated the key.</p><div class="invite-link-row"><input id="trackingSnippet" readonly><button type="button" class="btn-outline" id="copyTrackingSnippet">Copy</button></div></div>
        <p class="settings-feedback" id="trackingFeedback" role="status"></p>
    </section>
</div>
