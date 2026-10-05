const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

const root = path.resolve(__dirname, '..');

function storage(initial = {}) {
  const values = new Map(Object.entries(initial));
  return {
    getItem: (key) => values.get(key) ?? null,
    setItem: (key, value) => values.set(key, String(value)),
  };
}

function loadApp(initialStorage) {
  const context = {
    console,
    localStorage: storage(initialStorage),
    sessionStorage: storage(),
    document: { addEventListener() {}, getElementById() { return null; } },
    window: {},
  };
  context.window = context;
  vm.runInNewContext(fs.readFileSync(path.join(root, 'dashboard/src/assets/js/app.js'), 'utf8'), context);
  return context;
}

function loadOverview() {
  const context = { Intl, Date, window: {} };
  context.window = context;
  vm.runInNewContext(fs.readFileSync(path.join(root, 'dashboard/src/assets/js/overview.js'), 'utf8'), context);
  return context.OverviewPage;
}

test('restores a persisted custom reporting range', () => {
  const context = loadApp({
    minilytics_date_range: JSON.stringify({
      range: 'custom',
      customDates: { from: '2026-10-04', to: '2026-10-01' },
    }),
  });

  context.App.restoreDateRangePreference();
  assert.equal(context.App.currentRange, 'custom');
  assert.deepEqual(JSON.parse(JSON.stringify(context.App.customDates)), {
    from: '2026-10-01', to: '2026-10-04',
  });
});

test('formats hourly overview labels in the browser timezone', () => {
  const overview = loadOverview();
  const timestamp = 1791216000;
  const [point] = overview.localizeHourlyChartLabels([
    { timestamp, interval_hours: 1, label: '12 AM', full_label: 'UTC label' },
  ]);

  assert.equal(point.label, new Intl.DateTimeFormat(undefined, { hour: 'numeric', hour12: true }).format(new Date(timestamp * 1000)));
  assert.notEqual(point.full_label, 'UTC label');
});

test('does not relabel daily overview buckets', () => {
  const overview = loadOverview();
  const data = [{ timestamp: 1791216000, interval_hours: 24, label: 'Oct 05' }];
  assert.equal(overview.localizeHourlyChartLabels(data), data);
});

function loadEvents() {
  const elements = new Map();
  const getEl = (id) => {
    if (!elements.has(id)) {
      elements.set(id, {
        id,
        innerHTML: '',
        textContent: '',
        style: {},
        classList: {
          contains: (cls) => cls === 'event-series-combobox-menu-portal',
          add() {},
          remove() {},
        },
        getBoundingClientRect: () => ({ left: 20, right: 200, bottom: 100, top: 70 }),
      });
    }
    return elements.get(id);
  };
  const context = {
    Intl,
    Date,
    window: { innerWidth: 360, addEventListener() {} },
    document: {
      addEventListener() {},
      getElementById: (id) => getEl(id),
      body: { appendChild() {} },
    },
    Icons: {
      get: () => '',
      getCountryFlag: () => '',
      getBrowserIcon: () => '',
      getOsIcon: () => '',
    },
  };
  context.window.document = context.document;
  context.window.Icons = context.Icons;
  vm.runInNewContext(fs.readFileSync(path.join(root, 'dashboard/src/assets/js/events.js'), 'utf8'), context);
  return { EventsPage: context.window.EventsPage, getEl, context };
}

test('events page renders 3 metrics without Largest peak in event-insight-metric containers', () => {
  const { EventsPage, getEl } = loadEvents();
  EventsPage.renderInsights(14548, [{ name: 'CV Upload', count: 1486 }]);
  const html = getEl('eventInsightsStrip').innerHTML;

  assert.ok(html.includes('Events tracked'), 'must include Events tracked');
  assert.ok(html.includes('Event types'), 'must include Event types');
  assert.ok(html.includes('Top event'), 'must include Top event');
  assert.ok(!html.includes('Largest peak'), 'must not include Largest peak');
  assert.ok(html.includes('class="event-insight-metric"'), 'must include event-insight-metric container');
  assert.ok(html.includes('class="event-insight-detail"'), 'must include event-insight-detail class');
});

test('events series combobox menu positions within screen boundaries on small screens', () => {
  const { EventsPage, getEl, context } = loadEvents();
  context.window.innerWidth = 360;
  const trigger = getEl('eventsSeriesTrigger');
  const menu = getEl('eventsSeriesMenu');
  trigger.getBoundingClientRect = () => ({ left: 16, right: 180, bottom: 90, top: 60 });
  menu.hidden = false;

  EventsPage.positionSeriesMenu();

  assert.ok(parseInt(menu.style.width, 10) <= 336, 'menu width should not exceed viewport');
  if (menu.style.left) {
    assert.ok(parseInt(menu.style.left, 10) >= 12, 'left offset must not overflow screen');
  }
  if (menu.style.right && menu.style.right !== 'auto') {
    assert.ok(parseInt(menu.style.right, 10) >= 12, 'right offset must not overflow screen');
  }
});

test('events timeline does not use inline padding styles', () => {
  const { EventsPage, getEl } = loadEvents();
  const mockEvent = {
    id: 1,
    name: 'custom_event',
    timestamp: '2026-10-05 12:00:00',
    data: { country: 'France', browser: 'Chrome' },
  };
  EventsPage.renderEvents([mockEvent]);
  const html = getEl('eventsListContainer').innerHTML;

  assert.ok(html.includes('class="journey-steps-container"'), 'must have journey-steps-container');
  assert.ok(!html.includes('style="padding: 20px 24px;"'), 'must not hardcode inline padding on journey-steps-container');
});

test('dashboard.css includes responsive breakpoints for events', () => {
  const css = fs.readFileSync(path.join(root, 'dashboard/src/assets/css/dashboard.css'), 'utf8');

  assert.ok(css.includes('.event-insight-metric'), 'must define .event-insight-metric');
  assert.ok(css.includes('grid-template-columns: repeat(3, minmax(0, 1fr))'), 'desktop must be 3 columns');
  assert.ok(css.includes('@media (max-width: 480px)'), 'must include 480px breakpoint');
  assert.ok(css.includes('.journey-step-meta-row'), 'must define .journey-step-meta-row');
  assert.ok(css.includes('flex-wrap: wrap'), 'must include flex-wrap');
});

