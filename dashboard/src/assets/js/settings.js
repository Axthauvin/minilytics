const SettingsPage = {
  initialized: false,
  init() {
    if (this.initialized) return; this.initialized = true;
    const form = document.getElementById('inviteUserForm');
    if (form) form.addEventListener('submit', (event) => this.invite(event));
    const copy = document.getElementById('copyInviteLink');
    if (copy) copy.addEventListener('click', () => { const input = document.getElementById('inviteLink'); if (input) ClipboardHelper.copy(input.value, copy); });
  },
  async load() {
    this.init(); const list = document.getElementById('usersList'); if (!list) return;
    try { const res = await fetch('/dashboard/src/api/users.php'); const data = await res.json(); if (!res.ok) throw new Error(data.error || 'Unable to load users.');
      list.innerHTML = data.users.map((user) => `<div class="user-list-row"><div class="user-list-avatar">${this.escapeHtml(user.email.charAt(0).toUpperCase())}</div><div><strong>${this.escapeHtml(user.email)}</strong><span>${user.role === 'admin' ? 'Administrator' : 'Member'} · added ${new Date(user.created_at.replace(' ', 'T') + 'Z').toLocaleDateString()}</span></div></div>`).join('');
    } catch (error) { list.innerHTML = `<p class="settings-error">${this.escapeHtml(error.message)}</p>`; }
  },
  async invite(event) {
    event.preventDefault(); const email = document.getElementById('inviteEmail').value.trim(); const feedback = document.getElementById('inviteFeedback'); const result = document.getElementById('inviteResult');
    feedback.textContent = ''; try { const res = await fetch('/dashboard/src/api/users.php', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({email})}); const data = await res.json(); if (!res.ok) throw new Error(data.error || 'Unable to create the invitation.'); document.getElementById('inviteLink').value = data.invite_url; result.hidden = false; document.getElementById('inviteEmail').value = ''; feedback.textContent = 'Invitation created for ' + email + '.'; this.load(); } catch (error) { feedback.textContent = error.message; feedback.classList.add('settings-error'); }
  },
  escapeHtml(value) { const node = document.createElement('div'); node.textContent = value || ''; return node.innerHTML; }
}; window.SettingsPage = SettingsPage;
