const FunnelsPage = {
  data: null,
  activeId: null,
  builderSteps: [],
  init() {
    document
      .getElementById("btnCreateFunnel")
      ?.addEventListener("click", () => this.openBuilder());
    // The empty workspace is replaced after loading data. Delegate from the
    // stable wrapper so its freshly rendered "first funnel" button still works.
    document.getElementById("funnelWorkspace")?.addEventListener("click", (event) => {
      if (event.target.closest("#btnCreateFirstFunnel")) this.openBuilder();
    });
    // One stable listener for the dynamically rendered search inputs.
    document.getElementById("funnelBuilderSteps")?.addEventListener("input", (event) => {
      if (event.target.matches("[data-value]")) this.filterSearchResults(event.target);
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
    return `<div class="funnel-empty-state"><div class="funnel-empty-icon"><svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 4h18l-7 8v6l-4 2v-8z"></path></svg></div><h2>You haven't created any funnels yet</h2><p>Create your first funnel to see how visitors move through your site.</p><button class="btn-funnel-create" id="btnCreateFirstFunnel" type="button"><span>+</span> Create your first funnel</button></div>`;
  },
  renderLoading() {
    const list = document.getElementById("funnelsList");
    const workspace = document.getElementById("funnelWorkspace");
    if (list) list.innerHTML = '<div class="funnel-list-loading"><span class="funnel-loader-ring"></span><span>Loading…</span></div>';
    if (workspace) workspace.innerHTML = '<div class="funnel-page-loader" role="status"><span class="funnel-loader-ring" aria-hidden="true"></span><span>Loading funnels…</span></div>';
  },
  render() {
    const funnels = this.data?.funnels || [];
    const list = document.getElementById("funnelsList");
    document.getElementById("funnelsCount").textContent = funnels.length;
    if (!funnels.length) {
      list.innerHTML =
        '<div class="funnel-list-empty">No funnels yet.<br>Create one from your events.</div>';
      document.getElementById("funnelWorkspace").innerHTML = this.emptyStateMarkup();
      return;
    }
    if (!funnels.some((f) => f.id === this.activeId))
      this.activeId = funnels[0].id;
    list.innerHTML = funnels
      .map(
        (f) =>
          `<button class="funnel-list-item ${f.id === this.activeId ? "active" : ""}" data-id="${f.id}"><span><strong>${this.esc(f.name)}</strong><small>${f.steps.length} steps · ${f.progress.at(-1) || 0} completed</small></span></button>`,
      )
      .join("");
    list.querySelectorAll("[data-id]").forEach((btn) =>
      btn.addEventListener("click", () => {
        this.activeId = +btn.dataset.id;
        this.render();
      }),
    );
    this.renderWorkspace(funnels.find((f) => f.id === this.activeId));
  },
  renderWorkspace(funnel) {
    if (!funnel) return;
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
        const lost = i ? Math.max(0, 100 - retained) : 0;
        return `<article class="funnel-stage"><div class="funnel-column-chart"><span class="funnel-bar-percent">${progress.toFixed(1)}%</span><div class="funnel-bar-slot"><div class="funnel-bar-stack" style="height:${Math.max(previousProgress, 3)}%"><div class="funnel-vertical-bar" style="height:${Math.max(retainedHeight, 3)}%"></div></div></div></div><div class="funnel-column-details"><div class="funnel-stage-top"><span class="funnel-step-number">${i + 1}</span><div class="funnel-stage-name"><strong>${this.esc(step.label)}</strong><span>${step.type === "pageview" ? "Page view" : "Event"} · ${this.esc(step.value)}</span></div></div><div class="funnel-stage-stat"><strong>${count.toLocaleString()}</strong><span>visitors</span></div><div class="funnel-stage-footer">${i ? `<span>${retained.toFixed(1)}% continued</span><span class="funnel-drop">−${lost.toFixed(1)}% dropped off</span>` : "<span>Starting point</span>"}</div></div></article>`;
      })
      .join("");
    document.getElementById("funnelWorkspace").innerHTML =
      `<div class="funnel-workspace-head"><div><div class="funnel-context">${funnel.steps.length} steps · selected period</div><h2>${this.esc(funnel.name)}</h2></div><div class="funnel-actions"><button id="btnEditFunnel" class="btn-secondary-funnel" type="button">Edit</button><button id="btnDeleteFunnel" class="btn-delete-funnel" type="button">Delete</button></div></div><div class="funnel-summary"><div><span>Visitors started</span><strong>${first.toLocaleString()}</strong></div><div><span>Completed all steps</span><strong>${completed.toLocaleString()}</strong></div><div class="funnel-summary-rate"><span>Conversion rate</span><strong>${rate.toFixed(1)}%</strong></div></div><div class="funnel-flow">${steps}</div><div class="funnel-chart-legend"><span><i class="legend-visitors"></i> Visitors who continued</span><span><i class="legend-dropoff"></i> Dropped off</span></div>`;
    document
      .getElementById("btnEditFunnel")
      .addEventListener("click", () => this.openBuilder(funnel));
    document
      .getElementById("btnDeleteFunnel")
      .addEventListener("click", async () => {
        if (!confirm(`Delete “${funnel.name}”?`)) return;
        await Api.deleteFunnel(funnel.id);
        this.activeId = null;
        this.load(App.currentRange, App.currentSiteId, App.customDates);
      });
  },
  openBuilder(funnel = null) {
    this.editId = funnel?.id || null;
    document.getElementById("funnelName").value = funnel?.name || "";
    this.builderSteps = funnel
      ? structuredClone(funnel.steps)
      : [{ type: "pageview", value: "" }];
    document.getElementById("builderTitle").textContent = funnel
      ? "Edit funnel"
      : "Create a funnel";
    this.renderBuilderSteps();
    document.getElementById("funnelBuilder").classList.add("open");
  },
  closeBuilder() {
    document.getElementById("funnelBuilder").classList.remove("open");
  },
  suggestions(type) {
    const values =
      type === "pageview" ? this.data?.pages || [] : this.data?.events || [];
    const options = values
      .map((value) => `<button class="builder-search-option builder-search-option--${type}" data-option="${this.esc(value)}" type="button"><span class="builder-search-option-mark"></span>${this.esc(value)}</button>`)
      .join("");
    const source = type === "pageview" ? "Pages" : "Events";
    return `<span class="builder-search-group" data-search-heading="${source}">${source} · ${values.length}</span>${options}<span class="builder-search-empty" data-search-empty hidden>No matching items</span>`;
  },
  renderBuilderSteps() {
    const box = document.getElementById("funnelBuilderSteps");
    box.innerHTML = this.builderSteps
      .map(
        (step, i) => {
          const noun = step.type === "pageview" ? "page" : "event";
          const moveControls = this.builderSteps.length > 1 ? `<div class="builder-step-actions"><button data-move="up" data-index="${i}" class="btn-move-step" type="button" aria-label="Move step up" ${i === 0 ? "disabled" : ""}>↑</button><button data-move="down" data-index="${i}" class="btn-move-step" type="button" aria-label="Move step down" ${i === this.builderSteps.length - 1 ? "disabled" : ""}>↓</button><button data-remove="${i}" class="btn-remove-step" type="button" aria-label="Remove step">×</button></div>` : "";
          return `<div class="builder-step builder-step--${step.type}" draggable="true" data-step-index="${i}"><div class="builder-step-top"><button class="builder-drag-handle" type="button" aria-label="Drag to reorder" title="Drag to reorder"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M9 5h.01M15 5h.01M9 12h.01M15 12h.01M9 19h.01M15 19h.01"/></svg></button><span class="builder-step-index">${i + 1}</span><select data-type="${i}" aria-label="Step type"><option value="pageview" ${step.type === "pageview" ? "selected" : ""}>Page view</option><option value="event" ${step.type === "event" ? "selected" : ""}>Event</option></select>${moveControls}</div><div class="builder-combobox"><input data-value="${i}" value="${this.esc(step.value || "")}" placeholder="Search for a ${noun}…" autocomplete="off"><div class="builder-search-results" data-results="${i}">${this.suggestions(step.type)}</div></div></div>${i < this.builderSteps.length - 1 ? '<div class="builder-step-connector"><span></span></div>' : ""}`;
        },
      )
      .join("") + '<button id="btnAddFunnelStep" type="button" class="btn-add-step builder-add-next">+ Add next step</button>';
    box.querySelector("#btnAddFunnelStep").addEventListener("click", () => {
      this.builderSteps.push({ type: "pageview", value: "" });
      this.renderBuilderSteps();
      box.lastElementChild.previousElementSibling?.querySelector("[data-value]")?.focus();
    });
    box.querySelectorAll("[data-type]").forEach((e) =>
      e.addEventListener("change", () => {
        const index = Number(e.getAttribute("data-type"));
        const type = e.value === "event" ? "event" : "pageview";
        const step = this.builderSteps[index];
        step.type = type;
        step.value = "";

        // Update this exact block in-place. This avoids leaving an open list
        // from the former source when switching Page view ↔ Event.
        const stage = e.closest(".builder-step");
        stage.classList.toggle("builder-step--event", type === "event");
        stage.classList.toggle("builder-step--pageview", type === "pageview");
        const input = stage.querySelector("[data-value]");
        const results = stage.querySelector("[data-results]");
        input.value = "";
        input.placeholder = `Search for a ${type === "event" ? "event" : "page"}…`;
        results.innerHTML = this.suggestions(type);
        stage.querySelector(".builder-combobox").classList.add("open");
        this.bindSearchOptions(results, index);
        input.focus();
      }),
    );
    box
      .querySelectorAll("[data-value]")
      .forEach((e) => {
        const closeResults = () => e.closest(".builder-combobox").classList.remove("open");
        e.addEventListener("focus", () => e.closest(".builder-combobox").classList.add("open"));
        e.addEventListener("input", () => {
          const index = e.dataset.value;
          this.builderSteps[index].value = e.target.value;
        });
        e.addEventListener("blur", () => setTimeout(closeResults, 160));
      });
    box.querySelectorAll("[data-results]").forEach((results) => this.bindSearchOptions(results, results.dataset.results));
    box.querySelectorAll("[data-remove]").forEach((e) =>
      e.addEventListener("click", () => {
        if (this.builderSteps.length > 1) {
          this.builderSteps.splice(e.dataset.remove, 1);
          this.renderBuilderSteps();
        }
      }),
    );
    box.querySelectorAll("[data-move]").forEach((button) => button.addEventListener("click", () => {
      const from = Number(button.dataset.index);
      const to = button.dataset.move === "up" ? from - 1 : from + 1;
      this.moveStep(from, to);
    }));
    box.querySelectorAll(".builder-step").forEach((step) => {
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
        box.querySelectorAll(".builder-step").forEach((item) => item.classList.remove("is-dragging", "is-drop-target"));
      });
    });
  },
  moveStep(from, to) {
    if (!Number.isInteger(from) || !Number.isInteger(to) || from === to || to < 0 || to >= this.builderSteps.length) return;
    const [step] = this.builderSteps.splice(from, 1);
    this.builderSteps.splice(to, 0, step);
    this.renderBuilderSteps();
  },
  filterSearchResults(input) {
    const query = input.value.trim().toLowerCase();
    const index = input.dataset.value;
    const results = input.closest(".builder-combobox").querySelector(`[data-results="${index}"]`);
    const options = [...results.querySelectorAll("[data-option]")];
    let count = 0;
    options.forEach((option) => {
      const visible = option.dataset.option.toLowerCase().includes(query);
      option.hidden = !visible;
      if (visible) count++;
    });
    const heading = results.querySelector("[data-search-heading]");
    const source = heading.dataset.searchHeading;
    heading.textContent = query ? `${source} · ${count} matching` : `${source} · ${options.length}`;
    results.querySelector("[data-search-empty]").hidden = count > 0;
    results.parentElement.classList.add("open");
  },
  bindSearchOptions(results, index, close) {
    results.querySelectorAll("[data-option]").forEach((option) => option.addEventListener("mousedown", (event) => {
      event.preventDefault();
      const input = results.parentElement.querySelector("[data-value]");
      input.value = option.dataset.option;
      this.builderSteps[index].value = option.dataset.option;
      (close || (() => results.parentElement.classList.remove("open")))();
    }));
  },
  async save() {
    const name = document.getElementById("funnelName").value.trim();
    if (!name || this.builderSteps.some((s) => !s.value)) {
      alert("Give your funnel a name and choose every step.");
      return;
    }
    const btn = document.getElementById("btnSaveFunnel");
    btn.disabled = true;
    try {
      await Api.saveFunnel({ id: this.editId, name, steps: this.builderSteps });
      this.closeBuilder();
      await this.load(App.currentRange, App.currentSiteId, App.customDates);
    } catch (e) {
      alert(e.message);
    } finally {
      btn.disabled = false;
    }
  },
};
window.FunnelsPage = FunnelsPage;
