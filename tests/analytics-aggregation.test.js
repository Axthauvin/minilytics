const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const root = path.resolve(__dirname, '..');
const read = (file) => fs.readFileSync(path.join(root, file), 'utf8');

test('audience breakdowns aggregate distinct visitors and use visitor labels', () => {
  const stats = read('dashboard/src/api/stats.php');
  const overview = read('dashboard/src/pages/overview.php');

  assert.match(stats, /COUNT\(DISTINCT COALESCE\(visitor_id, session_id\)\) as count/);
  assert.match(stats, /\$totalVisitors > 0 \? round\(\(\$cnt \/ \$totalVisitors\)/);
  assert.match(stats, /GROUP BY country, country_code/);
  assert.match(overview, /id="envColumnHeader">Browser<\/span>\s*<span style="text-align: right;">Visitors<\/span>/);
  assert.match(overview, /<span>Country<\/span>\s*<span style="text-align: right;">Visitors<\/span>/);
});

test('visitor-facing secondary analytics use visitor ids instead of sessions', () => {
  const db = read('dashboard/src/api/db.php');
  const funnels = read('dashboard/src/api/funnels.php');

  assert.doesNotMatch(db, /COUNT\(DISTINCT session_id\) FROM user_activity/);
  assert.match(funnels, /COUNT\(DISTINCT COALESCE\(visitor_id, session_id\)\)/);
});

test('tracker accepts country codes from supported server-side geo providers', () => {
  const tracker = read('track.php');
  for (const header of ['HTTP_CF_IPCOUNTRY', 'HTTP_CF_REGION_CODE', 'HTTP_CF_IPCITY', 'HTTP_X_VERCEL_IP_COUNTRY', 'HTTP_CLOUDFRONT_VIEWER_COUNTRY', 'HTTP_FASTLY_CLIENT_COUNTRY_CODE', 'GEOIP_COUNTRY_CODE']) {
    assert.match(tracker, new RegExp(`'${header}'`));
  }
  assert.match(tracker, /GeoLocation::lookup\(\$ip\)/);
});

test('local GeoIP database is refreshed without an API key or visitor lookup', () => {
  const geo = read('dashboard/src/api/geo.php');

  assert.match(geo, /dbip-city-lite\.mmdb\.gz/);
  assert.match(geo, /MAX_AGE_SECONDS = 35 \* 86400/);
  assert.match(geo, /new \\MaxMind\\Db\\Reader/);
  assert.doesNotMatch(geo, /api[_-]?key|token=/i);
});

test('sessions API deletes only a selected session and UI requires confirmation', () => {
  const api = read('dashboard/src/api/sessions.php');
  const client = read('dashboard/src/assets/js/api.js');
  const sessions = read('dashboard/src/assets/js/sessions.js');

  assert.match(api, /Auth::requireAdmin\(\)/);
  assert.match(api, /DELETE FROM user_activity WHERE session_id = :session_id/);
  assert.match(client, /async deleteSession\(sessionId, siteId/);
  assert.match(sessions, /Delete session \$\{sessionId\} and all of its events/);
});
