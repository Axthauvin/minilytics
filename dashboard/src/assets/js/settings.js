const SettingsPage = {
  initialized: false,
  currentUser: null,
  trackingTags: { domains: [], internalIps: [] },
  databaseTested: false,

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
    this.setupTrackingTagEditor('domains', 'trackingDomainsInput', 'addTrackingDomain');
    this.setupTrackingTagEditor('internalIps', 'trackingInternalIpsInput', 'addTrackingInternalIp');
    const copySnippet = document.getElementById('copyTrackingSnippet');
    if (copySnippet) copySnippet.addEventListener('click', () => ClipboardHelper.copy(document.getElementById('trackingSnippet').value, copySnippet));
    document.querySelectorAll('[data-settings-tab]').forEach((tab) => tab.addEventListener('click', () => this.selectTab(tab.dataset.settingsTab)));
    const databaseDriver = document.getElementById('databaseDriver');
    if (databaseDriver) databaseDriver.addEventListener('change', () => this.toggleDatabaseFields());
    const databaseForm = document.getElementById('databaseSettingsForm');
    if (databaseForm) databaseForm.addEventListener('submit', (event) => this.saveDatabase(event));
    const testDatabase = document.getElementById('testDatabaseConnection');
    if (testDatabase) testDatabase.addEventListener('click', () => this.testDatabase());
    const saveDatabase = document.getElementById('saveDatabaseConnector');
    if (saveDatabase) saveDatabase.addEventListener('click', () => this.saveDatabase());
    document.querySelectorAll('#databaseSettingsForm input').forEach((input) => input.addEventListener('input', () => this.invalidateDatabaseTest()));
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
      await this.loadDatabaseConfig(isAdmin);

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

  selectTab(name) {
    document.querySelectorAll('[data-settings-tab]').forEach((tab) => { const selected = tab.dataset.settingsTab === name; tab.classList.toggle('is-active', selected); tab.setAttribute('aria-selected', String(selected)); });
    document.querySelectorAll('[data-settings-panel]').forEach((panel) => { panel.hidden = panel.dataset.settingsPanel !== name; });
  },

  async loadDatabaseConfig(isAdmin) {
    const card = document.getElementById('databaseSettingsCard'); if (!card) return;
    card.hidden = !isAdmin; if (!isAdmin) return;
    try {
      const { config } = await Api.getDatabaseConfig();
      document.getElementById('databaseDriver').value = config.driver || 'sqlite';
      document.getElementById('databaseHost').value = config.host || '';
      document.getElementById('databasePort').value = config.port || 3306;
      document.getElementById('databaseName').value = config.database || '';
      document.getElementById('databaseUsername').value = config.username || '';
      document.getElementById('databasePassword').value = '';
      this.toggleDatabaseFields();
    } catch (error) { this.setDatabaseFeedback(error.message, 'error'); }
  },

  databasePayload() {
    return { driver: document.getElementById('databaseDriver').value, host: document.getElementById('databaseHost').value.trim(), port: document.getElementById('databasePort').value, database: document.getElementById('databaseName').value.trim(), username: document.getElementById('databaseUsername').value.trim(), password: document.getElementById('databasePassword').value, create_database: document.getElementById('databaseCreateIfMissing').checked };
  },

  toggleDatabaseFields() {
    const form = document.getElementById('databaseSettingsForm'); const isRemote = document.getElementById('databaseDriver').value !== 'sqlite';
    form.hidden = !isRemote;
    form.querySelectorAll('input').forEach((input) => { input.disabled = !isRemote; });
    if (isRemote) {
      this.invalidateDatabaseTest();
    } else {
      this.databaseTested = true;
      document.getElementById('saveDatabaseConnector').disabled = false;
      this.setDatabaseFeedback('');
    }
  },

  invalidateDatabaseTest(showMessage = true) {
    const save = document.getElementById('saveDatabaseConnector');
    this.databaseTested = false;
    if (save) save.disabled = true;
    if (showMessage) this.setDatabaseFeedback('Test the current connection before saving.');
  },

  async testDatabase() {
    this.setDatabaseFeedback('Testing connection…');
    try { const result = await Api.databaseConnector('test', this.databasePayload()); this.databaseTested = true; document.getElementById('saveDatabaseConnector').disabled = false; const created = result.connection.database_created ? ' Database created.' : ''; this.setDatabaseFeedback(`${result.message}${created} Server version: ${result.connection.version}. You can now save this connector.`, 'success'); }
    catch (error) { this.setDatabaseFeedback(error.message, 'error'); }
  },

  async saveDatabase(event) {
    event?.preventDefault();
    const isRemote = document.getElementById('databaseDriver').value !== 'sqlite';
    if (isRemote && !this.databaseTested) { this.setDatabaseFeedback('Test the current connection before saving.', 'error'); return; }
    this.setDatabaseFeedback(isRemote ? 'Rechecking and saving connector…' : 'Saving SQLite connector…');
    try { const result = await Api.databaseConnector('save', this.databasePayload()); document.getElementById('databasePassword').value = ''; this.setDatabaseFeedback(result.message, 'success'); }
    catch (error) { this.setDatabaseFeedback(error.message, 'error'); }
  },

  setDatabaseFeedback(message, type = '') { const el = document.getElementById('databaseFeedback'); if (!el) return; el.textContent = message; el.className = 'settings-feedback'; if (type) el.classList.add(type === 'error' ? 'settings-error' : 'settings-success'); },

  async loadTrackingConfig() {
    const select = document.getElementById('trackingSiteSelect'); if (!select || !select.value) return;
    try { const data = await Api.getTrackingConfig(select.value); const site = data.site;
      this.setTrackingTags('domains', site.allowed_domains || []);
      this.setTrackingTags('internalIps', site.internal_ips || []);
      document.getElementById('trackingRetention').value = site.retention_days || 395;
    } catch (e) { this.setTrackingFeedback(e.message, 'error'); }
  },

  async saveTracking(event) {
    event.preventDefault(); const select = document.getElementById('trackingSiteSelect');
    try { const data = await Api.updateSiteConfig({ id: select.value, allowed_domains: this.trackingTags.domains, internal_ips: this.trackingTags.internalIps, retention_days: document.getElementById('trackingRetention').value, rotate_key: document.getElementById('trackingRotateKey').checked });
      this.setTrackingTags('domains', data.site.allowed_domains || []); this.setTrackingTags('internalIps', data.site.internal_ips || []);
      document.getElementById('trackingRotateKey').checked = false; document.getElementById('trackingSnippet').value = data.snippet; document.getElementById('trackingSnippetResult').hidden = false; this.setTrackingFeedback('Tracking settings saved.', 'success');
    } catch (e) { this.setTrackingFeedback(e.message, 'error'); }
  },

  setupTrackingTagEditor(kind, inputId, buttonId) {
    const input = document.getElementById(inputId); const button = document.getElementById(buttonId);
    if (!input || !button) return;
    const add = () => this.addTrackingTags(kind, input);
    button.addEventListener('click', add);
    input.addEventListener('keydown', (event) => {
      if (event.key === 'Enter' || event.key === ',') { event.preventDefault(); add(); }
      if (event.key === 'Backspace' && !input.value && this.trackingTags[kind].length) this.removeTrackingTag(kind, this.trackingTags[kind].length - 1);
    });
    input.addEventListener('blur', () => { if (input.value.trim()) add(); });
  },

  addTrackingTags(kind, input) {
    const values = input.value.split(/[\s,]+/).map(value => value.trim()).filter(Boolean);
    if (!values.length) return;
    const existing = this.trackingTags[kind].map(value => value.toLowerCase());
    values.forEach(value => { if (!existing.includes(value.toLowerCase())) { this.trackingTags[kind].push(value); existing.push(value.toLowerCase()); } });
    input.value = ''; this.renderTrackingTags(kind);
  },

  setTrackingTags(kind, values) {
    this.trackingTags[kind] = [...new Set((Array.isArray(values) ? values : []).map(value => String(value).trim()).filter(Boolean))];
    this.renderTrackingTags(kind);
  },

  removeTrackingTag(kind, index) { this.trackingTags[kind].splice(index, 1); this.renderTrackingTags(kind); },

  renderTrackingTags(kind) {
    const list = document.getElementById(kind === 'domains' ? 'trackingDomainsList' : 'trackingInternalIpsList');
    if (!list) return;
    list.replaceChildren(...this.trackingTags[kind].map((value, index) => {
      const tag = document.createElement('span'); tag.className = 'tracking-tag';
      const text = document.createElement('span'); text.className = 'tracking-tag-value'; text.textContent = value;
      const remove = document.createElement('button'); remove.type = 'button'; remove.className = 'tracking-tag-remove'; remove.setAttribute('aria-label', `Remove ${value}`); remove.title = `Remove ${value}`; remove.textContent = '×';
      remove.addEventListener('click', () => this.removeTrackingTag(kind, index)); tag.append(text, remove); return tag;
    }));
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
