# Privacy and data protection

As Minilytics wants to be a plug and play solution, it does not require any configuration to be privacy-friendly. It does not use cookies, local storage, or session storage in its default mode. It does not store raw IP addresses or user agents. It does not call a third-party geolocation service.

## Tracking modes

Minilytics supports two tracking modes :

- `strict` is the default mode. It creates no cookies, local storage, or session storage. It records the coarse screen resolution but removes URL query strings and fragments, and does not store raw IP addresses or user agents. The server derives a site-scoped visitor key from those values, a private secret, and a salt that rotates monthly.

- `enriched` is optional. It requires explicit visitor consent and may use `sessionStorage` to maintain a session. It remains disabled when the browser signals Global Privacy Control or Do Not Track.
  To activate this mode, add `data-privacy-mode="enriched"` to the tracking snippet and call `minilytics.consent()` after consent is granted. Call `minilytics.withdrawConsent()` when consent is withdrawn.

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

Visitors can opt out and opt back in for their browser profile:

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
