(function () {
    'use strict';

    const currentScript = document.currentScript;
    const siteId = (currentScript && currentScript.getAttribute('data-site-id')) || 'default_site';
    const endpoint = (currentScript && currentScript.getAttribute('data-endpoint'))
        || (currentScript && currentScript.src ? new URL(currentScript.src, window.location.href).origin + '/track.php' : '/track.php');
    const autoTrack = (currentScript && currentScript.getAttribute('data-auto-track')) !== 'false';
    const trackOutbound = (currentScript && currentScript.getAttribute('data-track-outbound')) !== 'false';

    const SESSION_KEY = 'minilytics_session_id';
    let lastTrackedPath = null;
    let previousUrl = document.referrer || null;

    /**
     * Get or create a persistent anonymous session ID for this browser tab
     */
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

    /**
     * Extract UTM parameters from current URL search query
     */
    function getUtmParams() {
        try {
            const params = new URLSearchParams(window.location.search);
            const utm = {};
            const keys = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'ref', 'source'];
            let hasAny = false;

            keys.forEach(function (k) {
                if (params.has(k)) {
                    utm[k] = params.get(k);
                    hasAny = true;
                }
            });

            return hasAny ? utm : null;
        } catch (e) {
            return null;
        }
    }

    /**
     * Collect full page and client context
     */
    function getPageContext() {
        const utm = getUtmParams();
        const screenRes = (typeof screen !== 'undefined') ? (screen.width + 'x' + screen.height) : null;
        const viewport = (typeof window !== 'undefined') ? (window.innerWidth + 'x' + window.innerHeight) : null;
        const device = (typeof window !== 'undefined' && window.innerWidth) 
            ? (window.innerWidth < 768 ? 'Mobile' : (window.innerWidth < 1024 ? 'Tablet' : 'Desktop')) 
            : 'Desktop';

        return {
            path: window.location.pathname || '/',
            title: document.title || '',
            url: window.location.href,
            referrer: previousUrl || (document.referrer ? document.referrer : null),
            hostname: window.location.hostname,
            search: window.location.search || null,
            hash: window.location.hash || null,
            screen: screenRes,
            viewport: viewport,
            device: device,
            language: navigator.language || (navigator.languages && navigator.languages[0]) || null,
            ...(utm ? { utm: utm } : {})
        };
    }

    /**
     * Compute an in-memory device/hardware fingerprint
     * Zero local storage, zero cookies: calculated entirely on-the-fly from stable hardware attributes.
     * Stays consistent when the same person returns with the same device/browser.
     */
    function getDeviceFingerprint() {
        try {
            let gpu = '';
            try {
                const canvas = document.createElement('canvas');
                const gl = canvas.getContext('webgl') || canvas.getContext('experimental-webgl');
                if (gl) {
                    const debugInfo = gl.getExtension('WEBGL_debug_renderer_info');
                    if (debugInfo) {
                        gpu = gl.getParameter(debugInfo.UNMASKED_RENDERER_WEBGL) || '';
                    }
                }
            } catch (e) {}

            const raw = [
                gpu,
                (typeof screen !== 'undefined') ? `${screen.width}x${screen.height}x${screen.colorDepth}` : '',
                (typeof window !== 'undefined') ? (window.devicePixelRatio || 1) : '',
                (typeof navigator !== 'undefined') ? (navigator.hardwareConcurrency || 0) : 0,
                (typeof navigator !== 'undefined') ? (navigator.deviceMemory || 0) : 0,
                (typeof Intl !== 'undefined' && Intl.DateTimeFormat) ? Intl.DateTimeFormat().resolvedOptions().timeZone : '',
                (typeof navigator !== 'undefined') ? (navigator.language || '') : ''
            ].join('###');

            let hash = 0;
            for (let i = 0; i < raw.length; i++) {
                hash = ((hash << 5) - hash) + raw.charCodeAt(i);
                hash |= 0;
            }
            return 'vid_' + Math.abs(hash).toString(36);
        } catch (e) {
            return 'vid_unknown';
        }
    }

    /**
     * Send event payload to Minilytics backend
     */
    function sendEvent(name, customData) {
        const context = getPageContext();
        // Merge page context with custom user payload
        const mergedData = Object.assign({}, context, customData || {});

        const payload = JSON.stringify({
            site_id: siteId,
            visitor_id: getDeviceFingerprint(),
            session_id: getSessionId(),
            name: name,
            data: mergedData
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

    /**
     * Track a pageview event
     */
    function trackPageView(customData) {
        const currentPath = window.location.pathname + window.location.search;
        if (lastTrackedPath === currentPath) {
            return; // Avoid duplicate firing for identical path
        }

        sendEvent('pageview', customData || {});
        previousUrl = window.location.href;
        lastTrackedPath = currentPath;
    }

    /**
     * Public API: window.minilytics
     */
    window.minilytics = {
        track: function (name, data) {
            if (!name || name === 'pageview') {
                trackPageView(data);
            } else {
                sendEvent(name, data);
            }
        },
        pageview: trackPageView,
        sessionId: getSessionId,
        visitorId: getDeviceFingerprint,
        siteId: siteId
    };

    if (!autoTrack) return;

    // 1. Initial pageview tracking
    if (document.readyState === 'complete') {
        trackPageView();
    } else {
        window.addEventListener('load', function () {
            trackPageView();
        });
    }

    // 2. SPA support: History API (pushState & replaceState)
    if (history.pushState) {
        const originalPush = history.pushState;
        history.pushState = function () {
            originalPush.apply(this, arguments);
            trackPageView();
        };

        const originalReplace = history.replaceState;
        history.replaceState = function () {
            originalReplace.apply(this, arguments);
            trackPageView();
        };
    }

    // 3. SPA support: popstate & hashchange
    window.addEventListener('popstate', function () {
        trackPageView();
    });

    window.addEventListener('hashchange', function () {
        trackPageView();
    });

    // 4. Declarative element tracking + Outbound & File download auto-tracking
    document.addEventListener('click', function (e) {
        // A. Custom declarative tracking: [data-minilytics-event]
        const customTarget = e.target.closest('[data-minilytics-event]');
        if (customTarget) {
            const eventName = customTarget.getAttribute('data-minilytics-event');
            const eventData = {};

            Array.from(customTarget.attributes).forEach(function (attr) {
                if (attr.name.startsWith('data-minilytics-') && attr.name !== 'data-minilytics-event') {
                    const prop = attr.name.replace('data-minilytics-', '');
                    eventData[prop] = attr.value;
                }
            });

            window.minilytics.track(eventName, eventData);
            return;
        }

        if (!trackOutbound) return;

        // B. Link tracking (Outbound links & file downloads)
        const link = e.target.closest('a');
        if (!link || !link.href) return;

        try {
            const url = new URL(link.href, window.location.href);

            // Check if outbound
            if (url.hostname && url.hostname !== window.location.hostname) {
                window.minilytics.track('outbound_click', {
                    target_url: link.href,
                    target_host: url.hostname,
                    link_text: (link.innerText || '').trim().substring(0, 100)
                });
                return;
            }

            // Check if downloadable file
            const fileExtensions = /\.(pdf|zip|tar|gz|csv|xlsx|docx|pptx|mp3|mp4|exe|dmg|pkg)$/i;
            if (fileExtensions.test(url.pathname)) {
                const parts = url.pathname.split('/');
                const fileName = parts[parts.length - 1];
                const ext = fileName.split('.').pop();
                window.minilytics.track('file_download', {
                    file_name: fileName,
                    file_ext: ext,
                    file_url: link.href
                });
            }
        } catch (err) {
            // Ignore URL parsing errors
        }
    });
})();
