const AcquisitionPage = {
  labels: {sources:'Sources', mediums:'Mediums', campaigns:'Campaigns', contents:'Campaign content', terms:'Campaign terms', channels:'Channels', landing_pages:'Landing pages', exit_pages:'Exit pages'},
  init() {},
  esc(v) { const n=document.createElement('span'); n.textContent=v; return n.innerHTML; },
  reasonLabels: {known_bot_user_agent:'Known bot or crawler', missing_user_agent:'Missing user agent', internal_traffic:'Internal traffic', webdriver:'Automated browser'},
  reasonLabel(reason) { if(String(reason).startsWith('client:')) return 'Automated browser ('+String(reason).slice(7)+')'; return this.reasonLabels[reason] || String(reason).replace(/_/g,' ').replace(/^./,c=>c.toUpperCase()); },
  bars(rows, key, label) {
    const top=Math.max(1,...rows.map(r=>r.count));
    return rows.length?rows.map(r=>`<div class="bot-bar"><div class="bot-bar-fill" style="width:${Math.round(r.count/top*100)}%"></div><span class="bot-bar-label" title="${this.esc(r[key])}">${this.esc(label?label(r[key]):r[key])}</span><strong>${r.count}</strong></div>`).join(''):'<span class="pill-muted">No data</span>';
  },
  sparkline(daily) {
    const top=Math.max(1,...daily.map(d=>d.count));
    return `<div class="bot-spark" role="img" aria-label="Bot requests per day">${daily.map(d=>`<i title="${this.esc(d.day)}: ${d.count}" style="height:${Math.max(8,Math.round(d.count/top*100))}%"></i>`).join('')}</div>`;
  },
  renderBots(bots) {
    const total=bots.total||0, recent=bots.recent||[];
    document.getElementById('botCount').textContent=`${total} detection${total===1?'':'s'}`;
    const panel=document.getElementById('botsList');
    if(!total){ panel.innerHTML='<span class="pill-muted">No detected bot traffic for this period.</span>'; return; }
    const top=(bots.reasons||[])[0];
    panel.innerHTML=`<div class="bot-kpis"><div class="bot-kpi"><small>Blocked requests</small><strong>${total}</strong></div><div class="bot-kpi"><small>Distinct sources</small><strong>${bots.sources||0}</strong></div><div class="bot-kpi"><small>Main reason</small><strong>${top?this.esc(this.reasonLabel(top.reason)):'-'}</strong></div><div class="bot-kpi bot-kpi--chart"><small>Per day</small>${this.sparkline(bots.daily||[])}</div></div>
      <div class="bot-columns"><div><h4>Why they were blocked</h4>${this.bars(bots.reasons||[],'reason',r=>this.reasonLabel(r))}</div><div><h4>Top user agents</h4>${this.bars(bots.agents||[],'user_agent')}</div><div><h4>Targeted websites</h4>${this.bars(bots.origins||[],'origin')}</div></div>
      <h4>Latest requests</h4><div class="bot-list">${recent.map(b=>`<div class="bot-row"><strong>${this.esc(this.reasonLabel(b.reason))}</strong><span title="${this.esc(b.user_agent||'')}">${this.esc(b.user_agent||'Unknown agent')}</span><time>${this.esc(b.timestamp)}</time></div>`).join('')}</div>`;
  },
  async load(range, siteId, customDates) {
    const box=document.getElementById('acquisitionReports'); if(!box) return;
    try { const data=await Api.getAcquisition(range,siteId,customDates); const reports=data.reports||{};
      const card=(key)=>{const rows=(reports[key]||[]).slice(0,8), total=rows.reduce((n,r)=>n+Number(r.sessions||0),0), top=Math.max(1,...rows.map(r=>Number(r.sessions||0))); return `<section class="acquisition-card acquisition-card--${key}"><div class="acquisition-card-head"><div><h3>${this.labels[key]}</h3></div><span class="acquisition-total">${total.toLocaleString()}<small>sessions</small></span></div><ul class="acquisition-list">${rows.length ? rows.map(r=>`<li class="acquisition-row"><div class="acquisition-row-main"><span class="acquisition-bar" style="--bar:${Math.max(4,Math.round(Number(r.sessions)/top*100))}%"></span><span class="acquisition-name" title="${this.esc(r.name)}">${this.esc(r.name)}</span></div><strong>${Number(r.sessions).toLocaleString()}</strong></li>`).join('') : '<li class="acquisition-empty">No data for this period</li>'}</ul></section>`;};
      box.innerHTML=`<section class="acquisition-section"><div class="acquisition-section-title"><h2>How visitors find you</h2><p>Channels and referral sources</p></div><div class="acquisition-grid acquisition-grid--three">${card('channels')}${card('sources')}${card('mediums')}</div></section><section class="acquisition-section"><div class="acquisition-section-title"><h2>Campaign attribution</h2><p>UTM values captured at the start of a session</p></div><div class="acquisition-grid acquisition-grid--three">${card('campaigns')}${card('contents')}${card('terms')}</div></section><section class="acquisition-section"><div class="acquisition-section-title"><h2>Entry & exit pages</h2><p>Where sessions start and where they end</p></div><div class="acquisition-grid acquisition-grid--two">${card('landing_pages')}${card('exit_pages')}</div></section>`;
      this.renderBots(data.bots||{});
    } catch(e) { box.innerHTML=`<p class="settings-error">${this.esc(e.message)}</p>`; }
  }
}; window.AcquisitionPage=AcquisitionPage;
