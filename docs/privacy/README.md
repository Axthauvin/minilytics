# Privacy and data protection

As Minilytics wants to be a plug and play solution, it does not require any configuration to be privacy-friendly. It does not use cookies, local storage, or session storage in its default mode. It does not store raw IP addresses or user agents. It does not call a third-party geolocation service.

## Tracking modes

Minilytics has two tracking modes.

The **`strict` mode (used by default)** doesn't need a consent banner. Minilytics stores nothing in the visitor's browser. The server recognizes visitors with an anonymous hash that changes every month, and never stores IPs or user agents. It works well, but if the visitor's IP changes (for example when they switch from Wi-Fi to mobile data), Minilytics counts them as a new visitor.

The **`enriched` mode (opt-in)** only starts once the visitor accepts analytics in your consent banner (you call `minilytics.consent()`). Nothing is sent before that. Instead of relying on the IP, it keeps a random session ID in the browser's `sessionStorage`, so a visit stays tracked as one session even if the IP changes. It also records the viewport size. The ID is deleted when the tab is closed or after 30 minutes of inactivity, so it can't follow anyone long-term. This also means a visitor who comes back later counts as a new visitor. It's turned off automatically if the browser sends Do Not Track or Global Privacy Control.

So use `strict` if you don't want a consent banner, and `enriched` if you already have one and want more accurate sessions.

In both modes, URL query strings and fragments are removed (campaign parameters like `utm_source` are kept).

### Enabling enriched mode

Add `data-privacy-mode="enriched"` to the tracking snippet and call `minilytics.consent()` once the visitor accepts. Call `minilytics.withdrawConsent()` if they change their mind. If your page already knows consent was granted when it loads, you can add `data-consent="granted"` to the snippet instead.

Example snippet for enriched mode:

```html
<script
  defer
  src="https://analytics.example.com/minilytics.js"
  data-site-id="my_site"
  data-site-key="your-private-key"
  data-privacy-mode="enriched"
></script>
<script>
  // Call after analytics consent is granted.
  minilytics.consent();

  // Call when consent is withdrawn.
  minilytics.withdrawConsent();
</script>
```

Your consent banner remains responsible for storing the consent choice. Minilytics does not create that cookie.

## Visitor choice

Visitors can opt out and opt back in for their browser profile. Opting out saves a `minilytics_opt_out` flag in `localStorage`, which is the only thing strict mode ever writes to the browser, and only when the visitor asks for it.

```js
minilytics.optOut();
minilytics.optIn();
```

## Site safeguards

In a site's settings, administrators can allow specific domains, exclude internal IP addresses, set data retention, and rotate the write key. Treat the generated tracking snippet as sensitive because it contains that key.

For a multi-node deployment, configure the same `MINILYTICS_TRACKING_SECRET` value on every node. Otherwise, Minilytics stores a local secret in `data/.tracking-secret`.

Minilytics determines country, region and city from the request IP without storing that IP. It first uses location headers supplied by Cloudflare, Vercel, CloudFront, Fastly or Apache GeoIP/MaxMind. Otherwise it uses the local DB-IP City Lite database. The database is downloaded automatically on first use and refreshed monthly; no API key, account, lookup quota or per-visitor external request is involved. City-level IP location is approximate and must not be treated as a precise address.

The bundled reader is licensed under Apache-2.0. DB-IP City Lite data is licensed under CC BY 4.0 and is attributed in the dashboard.

See also: [tracking](../tracking/README.md), [operations](../operations/README.md), [documentation index](../README.md).
