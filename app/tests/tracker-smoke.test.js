const assert = require("node:assert/strict");
const fs = require("node:fs");
const path = require("node:path");
const test = require("node:test");
const vm = require("node:vm");

const root = path.resolve(__dirname, "..");
const tracker = fs.readFileSync(path.join(root, "minilytics.js"), "utf8");

function storage() {
    const values = new Map();
    return {
        values,
        getItem: (key) => values.get(key) ?? null,
        setItem: (key, value) => values.set(key, String(value)),
        removeItem: (key) => values.delete(key),
    };
}

function loadTracker(options = {}) {
    const beacons = [];
    const errors = [];
    const script = {
        src: "http://localhost:8080/minilytics.js",
        getAttribute(name) {
            return (
                {
                    "data-site-id": "demo",
                    "data-site-key": "test-write-key",
                    ...options.attributes,
                }[name] ?? null
            );
        },
    };
    const document = {
        currentScript: script,
        readyState: "complete",
        title: "Tracker smoke test",
        referrer: "",
        hidden: false,
        addEventListener() {},
    };
    const context = {
        Blob,
        URL,
        URLSearchParams,
        crypto: { randomUUID: () => "00000000-0000-4000-8000-000000000000" },
        document,
        location: {
            href: "http://localhost:8080/demo.html",
            hostname: "localhost",
            pathname: "/demo.html",
            search: "",
        },
        navigator: {
            language: "en-US",
            sendBeacon(endpoint, body) {
                beacons.push({ endpoint, body });
                return true;
            },
            ...options.navigator,
        },
        console: { error: (...args) => errors.push(args), debug() {} },
        screen: { width: 1440, height: 900 },
        innerWidth: 1280,
        innerHeight: 720,
        matchMedia: () => ({ matches: false }),
        localStorage: storage(),
        sessionStorage: storage(),
        history: { pushState() {}, replaceState() {} },
        addEventListener() {},
        setTimeout() {},
        fetch: options.fetch,
    };
    context.window = context;
    vm.runInNewContext(tracker, context, { filename: "minilytics.js" });
    return { beacons, context, errors };
}

async function payload(body) {
    return JSON.parse(await body.text());
}

test("tracker auto-records a pageview and custom events on the script origin", async () => {
    const { beacons, context } = loadTracker();

    assert.equal(beacons.length, 1);
    assert.equal(beacons[0].endpoint, "http://localhost:8080/track.php");
    assert.deepEqual(await payload(beacons[0].body), {
        site_id: "demo",
        site_key: "test-write-key",
        session_id: "00000000000040008000000000000000",
        visitor_id: "00000000000040008000000000000000",
        name: "pageview",
        data: {
            path: "/demo.html",
            title: "Tracker smoke test",
            hostname: "localhost",
            referrer: null,
            language: "en-US",
            tracking_mode: "strict",
            screen: "1440×900",
        },
    });

    context.minilytics.track("signup_click", { plan: "pro" });
    assert.equal(beacons.length, 2);
    assert.equal(beacons[1].endpoint, "http://localhost:8080/track.php");
    assert.equal((await payload(beacons[1].body)).name, "signup_click");
    assert.equal((await payload(beacons[1].body)).data.plan, "pro");
});

test("tracking endpoint preserves spaces in event names", () => {
    const endpoint = fs.readFileSync(path.join(root, "track.php"), "utf8");

    assert.ok(
        endpoint.includes("preg_replace('/[\\x00-\\x1F\\x7F]/u', '', substr"),
    );
    assert.ok(
        !endpoint.includes("preg_replace('/[^a-zA-Z0-9_\\-:.]/', '_', substr"),
    );
});

function rejectingFetch() {
    return Promise.resolve({
        ok: false,
        status: 403,
        statusText: "Forbidden",
        text: () => Promise.resolve('{"error":"Invalid tracking key."}'),
    });
}

test("tracker stays silent about a rejected endpoint response outside debug mode", async () => {
    const { errors } = loadTracker({ fetch: rejectingFetch });

    await new Promise((resolve) => setImmediate(resolve));
    assert.equal(errors.length, 0);
});

test("tracker stays silent about missing configuration outside debug mode", () => {
    const { beacons, errors } = loadTracker({
        attributes: { "data-site-key": null },
    });

    assert.equal(beacons.length, 0);
    assert.equal(errors.length, 0);
});

test("tracker reports a rejected endpoint response in the browser console in debug mode", async () => {
    const { errors } = loadTracker({
        attributes: { "data-debug": "true" },
        fetch: rejectingFetch,
    });

    await new Promise((resolve) => setImmediate(resolve));
    assert.equal(errors.length, 1);
    assert.equal(
        errors[0][0],
        "[Minilytics] Tracking endpoint rejected an event.",
    );
    assert.deepEqual(JSON.parse(JSON.stringify(errors[0][1])), {
        status: 403,
        statusText: "Forbidden",
        response: '{"error":"Invalid tracking key."}',
        event: "pageview",
        endpoint: "http://localhost:8080/track.php",
    });
});

test("tracker explains the local opt-out and does not send an event", () => {
    const { beacons, context } = loadTracker({
        attributes: { "data-debug": "true" },
    });
    context.localStorage.setItem("minilytics_opt_out", "true");
    const logs = [];
    context.console.debug = (...args) => logs.push(args);

    context.minilytics.track("button_click");
    assert.equal(beacons.length, 1, "the custom event must not be sent");
    assert.equal(
        logs[0][0],
        "[Minilytics] Event was not sent because tracking is opted out.",
    );
    assert.deepEqual(JSON.parse(JSON.stringify(logs[0][1])), {
        event: "button_click",
        reason: "local_storage",
        resolution:
            "For this browser profile, run minilytics.optIn() and reload the page.",
    });
});

test("strict mode sends a page-scoped event despite GPC without using web storage", async () => {
    const { beacons, context } = loadTracker({
        navigator: { globalPrivacyControl: true },
    });

    assert.equal(beacons.length, 1);
    const event = await payload(beacons[0].body);
    assert.equal(event.data.tracking_mode, "strict");
    assert.equal(event.data.screen, "1440×900");
    assert.equal(event.session_id, event.visitor_id);
    assert.equal(context.sessionStorage.values.size, 0);
    assert.equal(context.localStorage.values.size, 0);
});

test("enriched mode waits for consent and remains blocked by GPC", () => {
    const pending = loadTracker({
        attributes: { "data-privacy-mode": "enriched" },
    });
    assert.equal(pending.beacons.length, 0);
    assert.equal(pending.context.minilytics.consent(), true);
    assert.equal(pending.beacons.length, 1);

    const protectedBrowser = loadTracker({
        attributes: { "data-privacy-mode": "enriched" },
        navigator: { globalPrivacyControl: true },
    });
    assert.equal(protectedBrowser.context.minilytics.consent(), false);
    assert.equal(protectedBrowser.beacons.length, 0);
});

test("automated browser with navigator.webdriver is ignored", () => {
    const { beacons, context } = loadTracker({
        attributes: { "data-debug": "true" },
        navigator: { webdriver: true },
    });
    const logs = [];
    context.console.debug = (...args) => logs.push(args);

    assert.equal(
        beacons.length,
        0,
        "auto pageview must not be sent for webdriver",
    );
    context.minilytics.track("bot_event");
    assert.equal(
        beacons.length,
        0,
        "custom events must not be sent for webdriver",
    );
    assert.equal(
        logs[0][0],
        "[Minilytics] Event was not sent because an automated browser was detected.",
    );
    assert.deepEqual(JSON.parse(JSON.stringify(logs[0][1])), {
        event: "bot_event",
        reason: "webdriver",
    });
});
