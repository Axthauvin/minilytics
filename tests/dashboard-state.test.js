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
