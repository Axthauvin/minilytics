(function () {
    'use strict';

    const currentScript = document.currentScript;
    const siteId = (currentScript && currentScript.getAttribute('data-site-id')) || 'default_site';
    const endpoint = (currentScript && currentScript.getAttribute('data-endpoint'))
        || (currentScript && currentScript.src ? new URL(currentScript.src).origin + '/track.php' : '/track.php');

    const SESSION_KEY = 'minilytics_session_id';

    function getSessionId() {
        try {
            let sessionId = sessionStorage.getItem(SESSION_KEY);
            if (!sessionId) {
                sessionId = (typeof crypto !== 'undefined' && crypto.randomUUID)
                    ? crypto.randomUUID()
                    : (Math.random().toString(36).substring(2) + Date.now().toString(36));
                sessionStorage.setItem(SESSION_KEY, sessionId);
            }
            return sessionId;
        } catch (e) {
            return 'anonymous';
        }
    }

    function sendEvent(name, data) {
        const payload = JSON.stringify({
            site_id: siteId,
            session_id: getSessionId(),
            name: name,
            data: data || {}
        });

        if (navigator.sendBeacon) {
            const blob = new Blob([payload], { type: 'application/json' });
            navigator.sendBeacon(endpoint, blob);
        } else {
            fetch(endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: payload,
                keepalive: true
            }).catch(function () {});
        }
    }

    function trackPageView() {
        sendEvent('pageview', {
            path: window.location.pathname,
            title: document.title,
            referrer: document.referrer || null
        });
    }

    // Umami-style public API: minilytics.track(name, data)
    window.minilytics = {
        track: function (name, data) {
            // If called without a name (e.g., minilytics.track()), track a pageview
            if (!name) {
                trackPageView();
            } else {
                sendEvent(name, data);
            }
        },
        pageview: trackPageView
    };

    // Automatic pageview tracking on page load
    if (document.readyState === 'complete') {
        trackPageView();
    } else {
        window.addEventListener('load', trackPageView);
    }

    // Support for Single Page Application (SPA) URL changes
    window.addEventListener('popstate', trackPageView);

    // Umami-style declarative tracking: automatically track clicks on elements with data-minilytics-event
    document.addEventListener('click', function (e) {
        const target = e.target.closest('[data-minilytics-event]');
        if (!target) return;

        const eventName = target.getAttribute('data-minilytics-event');
        const eventData = {};

        // Collect all other data-minilytics-* attributes (e.g., data-minilytics-plan="pro")
        Array.from(target.attributes).forEach(function (attr) {
            if (attr.name.startsWith('data-minilytics-') && attr.name !== 'data-minilytics-event') {
                const prop = attr.name.replace('data-minilytics-', '');
                eventData[prop] = attr.value;
            }
        });

        window.minilytics.track(eventName, eventData);
    });
})();
