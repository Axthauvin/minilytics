const SettingsPage = {
  initialized: false,
  currentUser: null,

  init() {
    if (this.initialized) return;
    this.initialized = true;

    const form = document.getElementById('inviteUserForm');
    if (form) form.addEventListener('submit', (event) => this.invite(event));

    const copy = document.getElementById('copyInviteLink');
    if (copy) {
      copy.addEventListener('click', () => {
        const input = document.getElementById('inviteLink');
        if (!input) return;
        if (window.ClipboardHelper && typeof ClipboardHelper.copy === 'function') {
          ClipboardHelper.copy(input.value, copy);
        } else if (navigator.clipboard) {
          navigator.clipboard.writeText(input.value).then(() => {
            const originalText = copy.textContent;
            copy.textContent = 'Copied!';
            setTimeout(() => { copy.textContent = originalText; }, 2000);
          });
        }
      });
    }
    const trackingForm = document.getElementById('trackingSettingsForm');
    if (trackingForm) trackingForm.addEventListener('submit', (event) => this.saveTracking(event));
    const siteSelect = document.getElementById('trackingSiteSelect');
    if (siteSelect) siteSelect.addEventListener('change', () => this.loadTrackingConfig());
    const copySnippet = document.getElementById('copyTrackingSnippet');
    if (copySnippet) copySnippet.addEventListener('click', () => ClipboardHelper.copy(document.getElementById('trackingSnippet').value, copySnippet));
  },

  async load() {
    this.init();
    const list = document.getElementById('usersList');
    if (!list) return;

    list.innerHTML = '<p class="settings-muted">Loading users…</p>';

    try {
      const data = await Api.getUsers();
      this.currentUser = data.current_user || null;
      const isAdmin = Boolean(this.currentUser && this.currentUser.role === 'admin');
      await this.loadTrackingSites(isAdmin);

      // Only administrators can invite new users
      const inviteCard = document.getElementById('inviteUserCard');
      if (inviteCard) {
        inviteCard.hidden = !isAdmin;
      }

      const users = Array.isArray(data.users) ? data.users : [];
      if (users.length === 0) {
        list.innerHTML = '<p class="settings-muted">No authorized users found.</p>';
        return;
      }

      const adminCount = users.filter((u) => u.role === 'admin').length;

      list.innerHTML = users.map((user) => {
        const isSelf = Boolean(this.currentUser && Number(this.currentUser.id) === Number(user.id));
        const isTargetAdmin = user.role === 'admin';
        const isOnlyAdmin = isTargetAdmin && adminCount <= 1;

        let actionsHtml = '';
        if (isAdmin) {
          let roleBtn = '';
          if (isSelf) {
            roleBtn = `
              <button type="button" class="btn-outline btn-sm btn-user-action" disabled title="You cannot change your own administrator status.">
                ${isTargetAdmin ? 'Admin' : 'Member'}
              </button>
            `;
          } else if (isTargetAdmin) {
            if (isOnlyAdmin) {
              roleBtn = `
                <button type="button" class="btn-outline btn-sm btn-user-action" disabled title="Cannot remove the only administrator.">
                  Remove Admin
                </button>
              `;
            } else {
              roleBtn = `
                <button type="button" class="btn-outline btn-sm btn-user-action" onclick="SettingsPage.toggleRole(${user.id}, 'member', '${this.escapeHtml(user.email)}')" title="Revoke administrator privileges">
                  Remove Admin
                </button>
              `;
            }
          } else {
            roleBtn = `
              <button type="button" class="btn-outline btn-sm btn-user-action" onclick="SettingsPage.toggleRole(${user.id}, 'admin', '${this.escapeHtml(user.email)}')" title="Grant administrator privileges">
                Make Admin
              </button>
            `;
          }

          let deleteBtn = '';
          if (isSelf) {
            deleteBtn = `
              <button type="button" class="btn-icon btn-sm btn-delete-user" disabled title="You cannot delete your own account.">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M3 6h18m-2 0v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6m3 0V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path>
                </svg>
              </button>
            `;
          } else if (isOnlyAdmin) {
            deleteBtn = `
              <button type="button" class="btn-icon btn-sm btn-delete-user" disabled title="Cannot delete the only administrator.">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M3 6h18m-2 0v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6m3 0V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path>
                </svg>
              </button>
            `;
          } else {
            deleteBtn = `
              <button type="button" class="btn-icon btn-sm btn-delete-user btn-danger" onclick="SettingsPage.deleteUser(${user.id}, '${this.escapeHtml(user.email)}')" title="Delete user account">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M3 6h18m-2 0v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6m3 0V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path>
                </svg>
              </button>
            `;
          }

          actionsHtml = `<div class="user-list-actions">${roleBtn}${deleteBtn}</div>`;
        }

        const dateStr = user.created_at
          ? new Date(user.created_at.replace(' ', 'T') + 'Z').toLocaleDateString()
          : '';

        return `
          <div class="user-list-row" id="user-row-${user.id}">
            <div class="user-list-main">
              <div class="user-list-avatar">${this.escapeHtml((user.email || 'U').charAt(0).toUpperCase())}</div>
              <div class="user-list-details">
                <div class="user-list-meta">
                  <strong class="user-list-email">${this.escapeHtml(user.email)}</strong>
                  ${isSelf ? '<span class="user-pill user-pill-self">You</span>' : ''}
                  <span class="user-pill ${isTargetAdmin ? 'user-pill-admin' : 'user-pill-member'}">
                    ${isTargetAdmin ? 'Admin' : 'Member'}
                  </span>
                </div>
                <span class="user-list-date">Added ${dateStr}</span>
              </div>
            </div>
            ${actionsHtml}
          </div>
        `;
      }).join('');
    } catch (error) {
      list.innerHTML = `<p class="settings-error">${this.escapeHtml(error.message)}</p>`;
    }
  },

  async loadTrackingSites(isAdmin) {
    const card = document.getElementById('trackingSettingsCard'); if (!card) return;
    card.hidden = !isAdmin; if (!isAdmin) return;
    const select = document.getElementById('trackingSiteSelect'); const data = await Api.getSites();
    select.innerHTML = (data.sites || []).map(s => `<option value="${this.escapeHtml(s.id)}">${this.escapeHtml(s.name || s.id)}</option>`).join('');
    await this.loadTrackingConfig();
  },

  async loadTrackingConfig() {
    const select = document.getElementById('trackingSiteSelect'); if (!select || !select.value) return;
    try { const data = await Api.getTrackingConfig(select.value); const site = data.site;
      document.getElementById('trackingDomains').value = (site.allowed_domains || []).join(', ');
      document.getElementById('trackingInternalIps').value = (site.internal_ips || []).join(', ');
      document.getElementById('trackingRetention').value = site.retention_days || 395;
    } catch (e) { this.setTrackingFeedback(e.message, 'error'); }
  },

  async saveTracking(event) {
    event.preventDefault(); const select = document.getElementById('trackingSiteSelect');
    try { const data = await Api.updateSiteConfig({ id: select.value, allowed_domains: document.getElementById('trackingDomains').value, internal_ips: document.getElementById('trackingInternalIps').value, retention_days: document.getElementById('trackingRetention').value, rotate_key: document.getElementById('trackingRotateKey').checked });
      document.getElementById('trackingRotateKey').checked = false; document.getElementById('trackingSnippet').value = data.snippet; document.getElementById('trackingSnippetResult').hidden = false; this.setTrackingFeedback('Tracking settings saved.', 'success');
    } catch (e) { this.setTrackingFeedback(e.message, 'error'); }
  },

  setTrackingFeedback(message, type = '') { const el=document.getElementById('trackingFeedback'); if (!el) return; el.textContent=message; el.className='settings-feedback'; if(type==='error')el.classList.add('settings-error'); if(type==='success')el.classList.add('settings-success'); },

  async invite(event) {
    event.preventDefault();
    const emailInput = document.getElementById('inviteEmail');
    const email = emailInput.value.trim();
    const feedback = document.getElementById('inviteFeedback');
    const result = document.getElementById('inviteResult');

    feedback.textContent = '';
    feedback.className = 'settings-feedback';

    try {
      const data = await Api.inviteUser(email);
      document.getElementById('inviteLink').value = data.invite_url;
      result.hidden = false;
      emailInput.value = '';
      feedback.textContent = `Invitation created for ${email}.`;
      this.load();
    } catch (error) {
      feedback.textContent = error.message;
      feedback.classList.add('settings-error');
    }
  },

  async toggleRole(userId, newRole, userEmail) {
    const isPromoting = newRole === 'admin';
    const message = isPromoting
      ? `Promote "${userEmail}" to Administrator?`
      : `Revoke Administrator privileges from "${userEmail}"? They will become a Member.`;

    if (!confirm(message)) return;

    this.setFeedback('Updating role…', '');

    try {
      const data = await Api.updateUserRole(userId, newRole);
      this.setFeedback(data.message || 'User role updated successfully.', 'success');
      await this.load();
    } catch (error) {
      this.setFeedback(error.message, 'error');
    }
  },

  async deleteUser(userId, userEmail) {
    const confirmMsg = `Are you sure you want to permanently delete user "${userEmail}"?\n\nThis account will lose access immediately.`;
    if (!confirm(confirmMsg)) return;

    this.setFeedback('Deleting user…', '');

    try {
      const data = await Api.deleteUser(userId);
      this.setFeedback(data.message || 'User deleted successfully.', 'success');
      await this.load();
    } catch (error) {
      this.setFeedback(error.message, 'error');
    }
  },

  setFeedback(message, type = '') {
    const fb = document.getElementById('usersListFeedback');
    if (!fb) return;
    fb.textContent = message;
    fb.className = 'settings-feedback';
    if (type === 'error') {
      fb.classList.add('settings-error');
    } else if (type === 'success') {
      fb.classList.add('settings-success');
    }
  },

  escapeHtml(value) {
    const node = document.createElement('div');
    node.textContent = value || '';
    return node.innerHTML;
  }
};

window.SettingsPage = SettingsPage;
