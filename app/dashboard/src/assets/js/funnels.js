const FunnelsPage = {
  data: null,
  activeId: null,
  activeKind: "funnel",
  builderSteps: [],
  init() {
    document
      .getElementById("btnCreateFunnel")
      ?.addEventListener("click", () => this.openBuilder());
    document.querySelectorAll("[data-analysis-kind]").forEach((tab) => tab.addEventListener("click", () => {
      this.activeKind = tab.dataset.analysisKind;
      this.activeId = null;
      this.render();
    }));
    // Every card is rendered again after loading data. Delegate from the
    // stable wrapper so their freshly rendered buttons still work.
    document.getElementById("funnelWorkspace")?.addEventListener("click", (event) => {
      if (event.target.closest("#btnCreateFirstFunnel")) return this.openBuilder();
      const card = event.target.closest("[data-analysis-id]");
      const analysis = card && this.itemById(+card.dataset.analysisId);
      if (!analysis) return;
      const toggle = event.target.closest("[data-toggle-journey]");
      if (event.target.closest("[data-edit-analysis]")) this.openBuilder(analysis);
      else if (event.target.closest("[data-delete-analysis]")) this.remove(analysis);
      else if (toggle) this.toggleJourney(analysis, toggle.dataset.toggleJourney);
    });
    document.getElementById("funnelsList")?.addEventListener("click", (event) => {
      const item = event.target.closest("[data-id]");
      if (item) this.scrollToAnalysis(+item.dataset.id);
    });
    document.getElementById("funnelDuplicateNotice")?.addEventListener("click", (event) => {
      const button = event.target.closest("[data-open-duplicate]");
      if (!button) return;
      this.closeBuilder();
      this.scrollToAnalysis(+button.dataset.openDuplicate);
    });
    document.addEventListener("keydown", (event) => {
      if (event.key === "Escape" && document.getElementById("funnelBuilder")?.classList.contains("open")) this.closeBuilder();
    });
    document
      .getElementById("btnCloseFunnelBuilder")
      ?.addEventListener("click", () => this.closeBuilder());
    document
      .getElementById("btnCancelFunnel")
      ?.addEventListener("click", () => this.closeBuilder());
    document
      .getElementById("btnSaveFunnel")
      ?.addEventListener("click", () => this.save());
    document.getElementById("funnelBuilder")?.addEventListener("click", (e) => {
      if (e.target.id === "funnelBuilder") this.closeBuilder();
    });
  },
  async load(range, siteId, customDates) {
    if (!siteId) return;
    this.renderLoading();
    try {
      this.data = await Api.getFunnels({ range, siteId, customDates });
      this.render();
    } catch (e) {
      console.error(e);
    }
  },
  esc(value) {
    const el = document.createElement("span");
    el.textContent = value || "";
    return el.innerHTML;
  },
  emptyStateMarkup() {
    const labels = this.kindLabels();
    return `<div class="funnel-empty-state"><div class="funnel-empty-icon"><svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 4h18l-7 8v6l-4 2v-8z"></path></svg></div><h2>No ${labels.plural.toLowerCase()} yet</h2><p>${labels.empty}</p><button class="btn-funnel-create" id="btnCreateFirstFunnel" type="button"><span>+</span> Create ${labels.article}</button></div>`;
  },
  kindLabels() {
    return this.activeKind === "goal" ? { singular: "Goal", plural: "Goals", article: "a goal", empty: "Track one meaningful action, such as a signup or a purchase." } : this.activeKind === "journey" ? { singular: "Journey", plural: "Journeys", article: "a journey", empty: "Save a path to understand how visitors progress through it." } : { singular: "Funnel", plural: "Funnels", article: "a funnel", empty: "Create a funnel to see how visitors move through your site." };
  },
  renderLoading() {
    const list = document.getElementById("funnelsList");
    const workspace = document.getElementById("funnelWorkspace");
    if (list) list.innerHTML = '<div class="funnel-list-loading"><span class="funnel-loader-ring"></span><span>Loading…</span></div>';
    if (workspace) workspace.innerHTML = '<div class="analysis-card"><div class="funnel-page-loader" role="status"><span class="funnel-loader-ring" aria-hidden="true"></span><span>Loading funnels…</span></div></div>';
  },
  itemById(id) {
    return (this.data?.funnels || []).find((item) => item.id === id);
  },
  render() {
    const labels = this.kindLabels();
    const funnels = (this.data?.funnels || []).filter((item) => (item.kind || "funnel") === this.activeKind);
    const list = document.getElementById("funnelsList");
    const workspace = document.getElementById("funnelWorkspace");
    document.getElementById("funnelsCount").textContent = funnels.length;
    document.querySelectorAll("[data-analysis-kind]").forEach((tab) => tab.classList.toggle("active", tab.dataset.analysisKind === this.activeKind));
    document.getElementById("btnCreateFunnel").innerHTML = `<span>+</span> Create ${labels.singular.toLowerCase()}`;
    document.getElementById("analysisListTitle").textContent = `Your ${labels.plural.toLowerCase()}`;
    document.getElementById("analysisPageTitle").textContent = labels.plural;
    document.getElementById("analysisPageDescription").textContent = this.activeKind === "goal" ? "Measure the actions that matter most, using the page views and events you already track." : this.activeKind === "journey" ? "See where visitors actually go after a chosen page view or event." : "Build a funnel from the page views and events you already track. Steps are counted in the order visitors complete them.";
    this.cardObserver?.disconnect();
    if (!funnels.length) {
      list.innerHTML =
        `<div class="funnel-list-empty">No ${labels.plural.toLowerCase()} yet.<br>Create one from your events.</div>`;
      workspace.innerHTML = `<div class="analysis-card">${this.emptyStateMarkup()}</div>`;
      return;
    }
    if (!funnels.some((f) => f.id === this.activeId))
      this.activeId = funnels[0].id;
    list.innerHTML = funnels
      .map(
        (f) =>
          `<button class="funnel-list-item ${f.id === this.activeId ? "active" : ""}" data-id="${f.id}" type="button"><span><strong>${this.esc(f.name)}</strong><small>${this.activeKind === "goal" ? `${f.progress.at(-1) || 0} reached` : `${f.steps.length} steps · ${f.progress.at(-1) || 0} completed`}</small></span></button>`,
      )
      .join("");
    workspace.innerHTML = funnels
      .map((f) => `<section class="analysis-card" id="analysis-${f.id}" data-analysis-id="${f.id}">${this.cardMarkup(f)}</section>`)
      .join("");
    this.observeCards(workspace);
  },
  /** Highlights, in the list, the analysis being read in the workspace. */
  observeCards(workspace) {
    if (!("IntersectionObserver" in window)) return;
    this.cardObserver = new IntersectionObserver((entries) => {
      if (this.scrollingTo) return;
      const visible = entries
        .filter((entry) => entry.isIntersecting)
        .sort((a, b) => a.boundingClientRect.top - b.boundingClientRect.top)[0];
      if (visible) this.setActive(+visible.target.dataset.analysisId);
    }, { rootMargin: "-80px 0px -55% 0px" });
    workspace.querySelectorAll("[data-analysis-id]").forEach((card) => this.cardObserver.observe(card));
  },
  setActive(id) {
    this.activeId = id;
    document.querySelectorAll("#funnelsList [data-id]").forEach((item) => {
      const active = +item.dataset.id === id;
      item.classList.toggle("active", active);
      if (active) item.scrollIntoView({ block: "nearest" });
    });
  },
  scrollToAnalysis(id) {
    const card = document.getElementById(`analysis-${id}`);
    if (!card) return;
    this.setActive(id);
    // Keep the clicked item highlighted while the page scrolls past other cards.
    this.scrollingTo = id;
    clearTimeout(this.scrollTimer);
    this.scrollTimer = setTimeout(() => { this.scrollingTo = null; }, 800);
    card.scrollIntoView({ behavior: "smooth", block: "start" });
  },
  cardMarkup(analysis) {
    const kind = analysis.kind || "funnel";
    if (kind === "goal") return this.goalMarkup(analysis);
    if (kind === "journey") return this.journeyMarkup(analysis);
    return this.funnelMarkup(analysis);
  },
  funnelMarkup(funnel) {
    const first = funnel.progress[0] || 0;
    const completed = funnel.progress.at(-1) || 0;
    const rate = first ? (completed / first) * 100 : 0;
    const steps = funnel.steps
      .map((step, i) => {
        const count = funnel.progress[i] || 0;
        const previous = i ? funnel.progress[i - 1] || 0 : count;
        const progress = first ? (count / first) * 100 : 0;
        const previousProgress = first ? (previous / first) * 100 : progress;
        const retainedHeight = previousProgress ? (progress / previousProgress) * 100 : 0;
        const retained = i ? (previous ? (count / previous) * 100 : 0) : 100;
        // Nobody reached the previous step, so nobody could drop off.
        const lost = i && previous ? Math.max(0, 100 - retained) : 0;
        return `<article class="funnel-stage"><div class="funnel-column-chart"><span class="funnel-bar-percent">${progress.toFixed(1)}%</span><div class="funnel-bar-slot"><div class="funnel-bar-stack" style="height:${Math.max(previousProgress, 3)}%"><div class="funnel-vertical-bar" style="height:${Math.max(retainedHeight, 3)}%"></div></div></div></div><div class="funnel-column-details"><div class="funnel-stage-top"><span class="funnel-step-number">${i + 1}</span><div class="funnel-stage-name"><strong>${this.esc(step.label || step.value)}</strong><span>${step.type === "pageview" ? "Page view" : "Event"} · ${this.esc(step.value)}</span></div></div><div class="funnel-stage-stat"><strong>${count.toLocaleString()}</strong><span>visitors</span></div><div class="funnel-stage-footer">${i ? `<span>${retained.toFixed(1)}% continued</span><span class="funnel-drop">−${lost.toFixed(1)}% dropped off</span>` : "<span>Starting point</span>"}</div></div></article>`;
      })
      .join("");
    return `${this.analysisHeader(funnel, `${funnel.steps.length} steps · selected period`)}<div class="funnel-summary"><div><span>Visitors started</span><strong>${first.toLocaleString()}</strong></div><div><span>Completed all steps</span><strong>${completed.toLocaleString()}</strong></div><div class="funnel-summary-rate"><span>Conversion rate</span><strong>${rate.toFixed(1)}%</strong></div></div><div class="funnel-flow">${steps}</div><div class="funnel-chart-legend"><span><i class="legend-visitors"></i> Visitors who continued</span><span><i class="legend-dropoff"></i> Dropped off</span></div>`;
  },
  analysisHeader(funnel, context = "Selected period") {
    return `<div class="funnel-workspace-head"><div><div class="funnel-context">${context}</div><h2>${this.esc(funnel.name)}</h2></div><div class="funnel-actions"><button data-edit-analysis class="btn-secondary-funnel" type="button">Edit</button><button data-delete-analysis class="btn-delete-funnel" type="button">Delete</button></div></div>`;
  },
  goalMarkup(goal) {
    const audience = this.data?.audience || 0; const reached = goal.progress.at(-1) || 0; const rate = audience ? reached / audience * 100 : 0; const step = goal.steps[0] || {};
    return `${this.analysisHeader(goal)}<div class="funnel-summary"><div><span>All visitors</span><strong>${audience.toLocaleString()}</strong></div><div><span>Reached goal</span><strong>${reached.toLocaleString()}</strong></div><div class="funnel-summary-rate"><span>Conversion rate</span><strong>${rate.toFixed(1)}%</strong></div></div><section class="goal-result"><div class="goal-result-top"><div><span class="goal-result-label">Goal condition</span><h3>${this.esc(step.label || step.value)}</h3><p>${step.type === "pageview" ? "Page view" : "Event"} · ${this.esc(step.value)}</p></div><strong>${rate.toFixed(1)}%</strong></div><div class="goal-ratio"><div style="width:${Math.max(rate, 1)}%"></div></div><p class="goal-result-caption"><b>${reached.toLocaleString()}</b> of ${audience.toLocaleString()} visitors reached this goal.</p></section>`;
  },
  journeyMarkup(journey) {
    const data = journey.journey || { entered: 0, completed: 0, tree: { children: [] } }; const start = journey.steps[0] || {}; const exit = journey.steps[1] || {}; const usingFallback = !data.completed && data.fallback?.completed; const displayData = usingFallback ? data.fallback : data; const tree = displayData.tree || { children: [] };
    this.journeyExpanded ||= new Set();
    const renderNode = (node, parentCount, id, depth) => {
      const rate = parentCount ? node.count / parentCount * 100 : 0; const hasChildren = Boolean(node.children?.length); const expanded = this.journeyExpanded.has(id);
      const children = hasChildren ? `<div class="journey-tree-children" ${expanded ? "" : "hidden"}>${node.children.map((child, index) => renderNode(child, node.count, `${id}-${index}`, depth + 1)).join("")}</div>` : "";
      return `<div class="journey-tree-node journey-tree-depth-${depth}"><article class="journey-tree-card"><span class="journey-tree-dot"></span><div><strong>${this.esc(node.value)}</strong><span>${node.type === "pageview" ? "Page view" : "Event"}</span></div><div class="journey-tree-count"><strong>${node.count.toLocaleString()}</strong><span>${rate.toFixed(1)}%</span></div>${hasChildren ? `<button class="journey-tree-toggle" data-toggle-journey="${id}" type="button" aria-expanded="${expanded}" aria-label="${expanded ? "Hide" : "Show"} next paths">${expanded ? "−" : "+"}</button>` : '<span class="journey-tree-leaf"></span>'}</article>${children}</div>`;
    };
    // Several journeys share the page, so branch ids start with the journey's own id.
    const branches = tree.children?.length ? `<div class="journey-tree-branches">${tree.children.map((child, index) => renderNode(child, displayData.completed, `${journey.id}-${index}`, 1)).join("")}</div>` : '<div class="journey-empty">No visitor completed a path from this entry to this exit in the selected period.</div>';
    const fallbackNote = usingFallback ? '<div class="journey-history-note">No completed route during the selected period. The tree below uses the same route across your full history.</div>' : "";
    return `${this.analysisHeader(journey, "Selected period · actual paths")}<div class="journey-boundaries"><div><span>Entry</span><strong>${this.esc(start.label || start.value)}</strong><small>${start.type === "pageview" ? "Page view" : "Event"}</small></div><i>→</i><div><span>Exit</span><strong>${this.esc(exit.label || exit.value)}</strong><small>${exit.type === "pageview" ? "Page view" : "Event"}</small></div><b>${data.entered.toLocaleString()} entered · ${data.completed.toLocaleString()} reached exit</b></div>${fallbackNote}<section class="journey-sources journey-tree"><div class="journey-sources-head"><div><span>Paths between entry and exit</span><p>Open a branch to explore the most common completed routes.</p></div></div>${branches}</section>`;
  },
  toggleJourney(journey, id) {
    if (this.journeyExpanded.has(id)) this.journeyExpanded.delete(id);
    else this.journeyExpanded.add(id);
    const card = document.getElementById(`analysis-${journey.id}`);
    if (card) card.innerHTML = this.journeyMarkup(journey);
  },
  async remove(analysis) {
    if (!confirm(`Delete “${analysis.name}”?`)) return;
    await Api.deleteFunnel(analysis.id);
    this.activeId = null;
    this.load(App.currentRange, App.currentSiteId, App.customDates);
  },
  openBuilder(funnel = null) {
    if (funnel?.kind) this.activeKind = funnel.kind;
    this.editId = funnel?.id || null;
    document.getElementById("funnelName").value = funnel?.name || "";
    this.builderSteps = funnel
      ? structuredClone(funnel.steps)
      : this.activeKind === "journey" ? [{ type: "pageview", value: "" }, { type: "pageview", value: "" }] : [{ type: "pageview", value: "" }];
    if (this.activeKind === "goal") this.builderSteps = this.builderSteps.slice(0, 1);
    if (this.activeKind === "journey") this.builderSteps = this.builderSteps.slice(0, 2);
    if (this.activeKind === "journey" && this.builderSteps.length < 2) this.builderSteps.push({ type: "pageview", value: "" });
    const labels = this.kindLabels();
    document.getElementById("builderTitle").textContent = funnel
      ? `Edit ${labels.singular.toLowerCase()}`
      : `Create ${labels.article}`;
    document.querySelector(".funnel-builder-help").textContent = this.activeKind === "goal" ? "Choose the one page view or event that marks success." : this.activeKind === "journey" ? "Choose an entry and an exit. We will reveal the paths visitors actually took between them." : "Add the actions visitors should take, in order. Each step can be a page or an event, just search for it.";
    document.getElementById("builderStepsLabel").textContent = this.activeKind === "goal" ? "Goal condition" : this.activeKind === "journey" ? "Entry and exit" : "Steps";
    document.getElementById("btnSaveFunnel").textContent = `Save ${labels.singular.toLowerCase()}`;
    this.renderBuilderSteps();
    document.getElementById("funnelBuilder").classList.add("open");
  },
  closeBuilder() {
    document.getElementById("funnelBuilder").classList.remove("open");
  },
  typeLabel(type) {
    return type === "event" ? "Event" : "Page";
  },
  /** Options matching the query, pages and events together, so no type has to be chosen first. */
  searchOptions(query) {
    const q = query.trim().toLowerCase();
    const limit = 60;
    const match = (value) => !q || value.toLowerCase().includes(q);
    const groups = [
      { type: "pageview", title: "Pages", values: (this.data?.pages || []).filter(match) },
      { type: "event", title: "Events", values: (this.data?.events || []).filter(match) },
    ];
    const options = [];
    groups.forEach((group) => group.values.slice(0, limit).forEach((value) => options.push({ type: group.type, value, group: group.title })));
    // Anything not recorded yet can still be used, as a page path or an event name.
    const exact = groups.some((group) => group.values.some((value) => value.toLowerCase() === q));
    if (q && !exact) {
      const value = query.trim();
      options.push({ type: value.startsWith("/") ? "pageview" : "event", value, group: "Not recorded yet", custom: true });
    }
    const hidden = groups.reduce((total, group) => total + Math.max(0, group.values.length - limit), 0);
    return { options, hidden };
  },
  renderResults(index) {
    const combobox = this.comboboxAt(index);
    if (!combobox) return;
    const input = combobox.querySelector("[data-value]");
    const step = this.builderSteps[index];
    // Focusing a chosen step lists everything; typing narrows the list.
    const query = input.value === (step.value || "") ? "" : input.value;
    const { options, hidden } = this.searchOptions(query);
    this.resultOptions = options;
    this.activeOption = options.length ? 0 : -1;
    let group = null;
    const markup = options.map((option, i) => {
      const heading = option.group !== group ? `<span class="builder-search-group">${option.group}</span>` : "";
      group = option.group;
      const text = option.custom ? `Use “${this.esc(option.value)}” as ${option.type === "event" ? "an event" : "a page"}` : this.esc(option.value);
      const selected = option.type === step.type && option.value === step.value;
      return `${heading}<button class="builder-search-option builder-search-option--${option.type}${selected ? " is-selected" : ""}" data-option-index="${i}" type="button" role="option" tabindex="-1"><span class="builder-search-option-mark"></span><span class="builder-search-option-text">${text}</span>${selected ? '<span class="builder-search-option-check">✓</span>' : ""}</button>`;
    }).join("");
    const results = combobox.querySelector("[data-results]");
    results.innerHTML = markup || '<span class="builder-search-empty">No page or event recorded yet</span>';
    if (hidden) results.insertAdjacentHTML("beforeend", `<span class="builder-search-empty">${hidden} more, keep typing to narrow the list</span>`);
    combobox.classList.add("open");
    input.setAttribute("aria-expanded", "true");
    this.highlightOption(index);
  },
  highlightOption(index) {
    const results = this.comboboxAt(index)?.querySelector("[data-results]");
    results?.querySelectorAll("[data-option-index]").forEach((option) => {
      const active = +option.dataset.optionIndex === this.activeOption;
      option.classList.toggle("is-active", active);
      if (active) option.scrollIntoView({ block: "nearest" });
    });
  },
  comboboxAt(index) {
    return document.querySelector(`#funnelBuilderSteps [data-step-index="${index}"] .builder-combobox`);
  },
  closeResults(index) {
    const combobox = this.comboboxAt(index);
    combobox?.classList.remove("open");
    combobox?.querySelector("[data-value]")?.setAttribute("aria-expanded", "false");
  },
  pickOption(index, option) {
    if (!option) return;
    const step = this.builderSteps[index];
    if (step.type !== option.type || step.value !== option.value) delete step.label;
    step.type = option.type;
    step.value = option.value;
    this.closeResults(index);
    this.refreshStep(index);
    this.updateDuplicateNotice();
    // Carry on with the next empty step, as when filling a form.
    const next = this.builderSteps.findIndex((item, i) => i > index && !item.value);
    if (next !== -1) document.querySelector(`#funnelBuilderSteps [data-value="${next}"]`)?.focus();
  },
  /** Updates one step in place, so the other inputs keep their focus and text. */
  refreshStep(index) {
    const stage = document.querySelector(`#funnelBuilderSteps [data-step-index="${index}"]`);
    const step = this.builderSteps[index];
    if (!stage) return;
    stage.classList.toggle("builder-step--event", step.type === "event");
    stage.classList.toggle("builder-step--pageview", step.type !== "event");
    stage.classList.toggle("is-empty", !step.value);
    const badge = stage.querySelector("[data-type-badge]");
    badge.textContent = this.typeLabel(step.type);
    badge.hidden = !step.value;
    stage.querySelector("[data-value]").value = step.value || "";
  },
  /** The link between two nodes, with a button to insert a step at that position. */
  connectorMarkup(insertAt, canInsert) {
    const button = canInsert ? `<button class="builder-insert-step" data-insert="${insertAt}" type="button" aria-label="Insert a step here" title="Insert a step here">+</button>` : "";
    return `<div class="builder-step-connector"><span></span>${button}</div>`;
  },
  insertStep(index) {
    this.builderSteps.splice(index, 0, this.emptyStep());
    this.renderBuilderSteps();
    document.querySelector(`#funnelBuilderSteps [data-value="${index}"]`)?.focus();
  },
  emptyStep() {
    return { type: "pageview", value: "" };
  },
  renderBuilderSteps() {
    const box = document.getElementById("funnelBuilderSteps");
    const canReorder = this.activeKind === "funnel";
    // The API accepts at most 12 steps.
    const canInsert = canReorder && this.builderSteps.length < 12;
    box.classList.toggle("is-funnel", canReorder);
    box.innerHTML = this.builderSteps
      .map((step, i) => {
        const moveControls = canReorder && this.builderSteps.length > 1 ? `<div class="builder-step-actions"><button data-move="up" data-index="${i}" class="btn-move-step" type="button" aria-label="Move step up" ${i === 0 ? "disabled" : ""}>↑</button><button data-move="down" data-index="${i}" class="btn-move-step" type="button" aria-label="Move step down" ${i === this.builderSteps.length - 1 ? "disabled" : ""}>↓</button><button data-remove="${i}" class="btn-remove-step" type="button" aria-label="Remove step">×</button></div>` : "";
        const role = this.activeKind === "journey" ? `<span class="builder-step-role">${i === 0 ? "Entry" : "Exit"}</span>` : "";
        const handle = canReorder ? '<button class="builder-drag-handle" type="button" aria-label="Drag to reorder" title="Drag to reorder"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M9 5h.01M15 5h.01M9 12h.01M15 12h.01M9 19h.01M15 19h.01"/></svg></button>' : "";
        return `<div class="builder-step builder-step--${step.type === "event" ? "event" : "pageview"}${step.value ? "" : " is-empty"}" data-step-index="${i}">${handle}<span class="builder-step-index">${i + 1}</span>${role}<div class="builder-combobox"><span class="builder-type-badge" data-type-badge ${step.value ? "" : "hidden"}>${this.typeLabel(step.type)}</span><input data-value="${i}" value="${this.esc(step.value || "")}" placeholder="Search a page or an event…" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-label="Step ${i + 1}"><div class="builder-search-results" data-results="${i}" role="listbox"></div></div>${moveControls}</div>${i < this.builderSteps.length - 1 ? this.connectorMarkup(i + 1, canInsert) : ""}`;
      })
      .join("") + (canInsert ? `${this.connectorMarkup(null, false)}<button id="btnAddFunnelStep" type="button" class="builder-add-node">+ Add a step</button>` : "");
    box.querySelector("#btnAddFunnelStep")?.addEventListener("click", () => this.insertStep(this.builderSteps.length));
    box.querySelectorAll("[data-insert]").forEach((button) => button.addEventListener("click", () => this.insertStep(Number(button.dataset.insert))));
    box.querySelectorAll("[data-value]").forEach((input) => {
      const index = Number(input.dataset.value);
      input.addEventListener("focus", () => {
        input.select();
        this.renderResults(index);
      });
      input.addEventListener("input", () => this.renderResults(index));
      input.addEventListener("keydown", (event) => this.onSearchKey(event, index));
      // A query left without a pick goes back to the chosen value.
      input.addEventListener("blur", () => setTimeout(() => {
        this.closeResults(index);
        if (document.activeElement !== input) this.refreshStep(index);
      }, 160));
    });
    box.querySelectorAll("[data-results]").forEach((results) => results.addEventListener("mousedown", (event) => {
      const option = event.target.closest("[data-option-index]");
      if (!option) return;
      event.preventDefault();
      this.pickOption(Number(results.dataset.results), this.resultOptions[+option.dataset.optionIndex]);
    }));
    box.querySelectorAll("[data-remove]").forEach((button) => button.addEventListener("click", () => {
      if (this.builderSteps.length > 1) {
        this.builderSteps.splice(Number(button.dataset.remove), 1);
        this.renderBuilderSteps();
      }
    }));
    box.querySelectorAll("[data-move]").forEach((button) => button.addEventListener("click", () => {
      const from = Number(button.dataset.index);
      const to = button.dataset.move === "up" ? from - 1 : from + 1;
      this.moveStep(from, to);
    }));
    box.querySelectorAll(".builder-step").forEach((step) => {
      // Only the handle starts a drag, so selecting text in the search stays possible.
      step.querySelector(".builder-drag-handle")?.addEventListener("mousedown", () => { step.draggable = true; });
      step.addEventListener("dragstart", (event) => {
        this.draggedStepIndex = Number(step.dataset.stepIndex);
        event.dataTransfer.effectAllowed = "move";
        requestAnimationFrame(() => step.classList.add("is-dragging"));
      });
      step.addEventListener("dragover", (event) => { event.preventDefault(); step.classList.add("is-drop-target"); });
      step.addEventListener("dragleave", () => step.classList.remove("is-drop-target"));
      step.addEventListener("drop", (event) => {
        event.preventDefault();
        this.moveStep(this.draggedStepIndex, Number(step.dataset.stepIndex));
      });
      step.addEventListener("dragend", () => {
        this.draggedStepIndex = null;
        box.querySelectorAll(".builder-step").forEach((item) => {
          item.draggable = false;
          item.classList.remove("is-dragging", "is-drop-target");
        });
      });
    });
    this.updateDuplicateNotice();
  },
  onSearchKey(event, index) {
    const count = this.resultOptions?.length || 0;
    if (event.key === "ArrowDown" || event.key === "ArrowUp") {
      event.preventDefault();
      if (!this.comboboxAt(index).classList.contains("open")) return this.renderResults(index);
      if (!count) return;
      this.activeOption = (this.activeOption + (event.key === "ArrowDown" ? 1 : -1) + count) % count;
      this.highlightOption(index);
    } else if (event.key === "Enter") {
      event.preventDefault();
      if (this.comboboxAt(index).classList.contains("open")) this.pickOption(index, this.resultOptions[this.activeOption]);
    } else if (event.key === "Escape" && this.comboboxAt(index).classList.contains("open")) {
      // Closes the list only, not the whole builder.
      event.stopPropagation();
      this.closeResults(index);
      this.refreshStep(index);
    }
  },
  moveStep(from, to) {
    if (!Number.isInteger(from) || !Number.isInteger(to) || from === to || to < 0 || to >= this.builderSteps.length) return;
    const [step] = this.builderSteps.splice(from, 1);
    this.builderSteps.splice(to, 0, step);
    this.renderBuilderSteps();
  },
  /** The saved analysis of the same kind with exactly the same steps, if any. */
  duplicateOf() {
    if (this.builderSteps.some((step) => !step.value)) return null;
    const key = (steps) => JSON.stringify(steps.map((step) => [step.type, step.value]));
    const steps = key(this.builderSteps);
    return (this.data?.funnels || []).find((item) => (item.kind || "funnel") === this.activeKind && item.id !== this.editId && key(item.steps) === steps) || null;
  },
  updateDuplicateNotice() {
    const notice = document.getElementById("funnelDuplicateNotice");
    const duplicate = this.duplicateOf();
    document.getElementById("btnSaveFunnel").disabled = Boolean(duplicate);
    if (!notice) return;
    notice.hidden = !duplicate;
    if (!duplicate) return;
    const noun = this.kindLabels().singular.toLowerCase();
    const what = this.activeKind === "funnel" ? "these exact steps" : this.activeKind === "goal" ? "this exact condition" : "this exact entry and exit";
    notice.innerHTML = `<span>“${this.esc(duplicate.name)}” already tracks ${what}. Change a step, or open the existing ${noun}.</span><button type="button" class="btn-secondary-funnel" data-open-duplicate="${duplicate.id}">Open it</button>`;
  },
  async save() {
    const name = document.getElementById("funnelName").value.trim();
    if (!name || this.builderSteps.some((s) => !s.value)) {
      alert(`Give your ${this.kindLabels().singular.toLowerCase()} a name and choose ${this.activeKind === "funnel" ? "every step" : "the action"}.`);
      return;
    }
    const btn = document.getElementById("btnSaveFunnel");
    btn.disabled = true;
    try {
      const saved = await Api.saveFunnel({ id: this.editId, kind: this.activeKind, name, steps: this.builderSteps });
      this.closeBuilder();
      await this.load(App.currentRange, App.currentSiteId, App.customDates);
      // Bring the created or edited card into view among the others.
      this.scrollToAnalysis(+saved.id);
    } catch (e) {
      alert(e.message);
    } finally {
      btn.disabled = false;
    }
  },
};
window.FunnelsPage = FunnelsPage;
