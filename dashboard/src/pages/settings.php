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
</div>