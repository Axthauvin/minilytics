# Privacy and data protection

Minilytics is private without any setting. It uses no cookies, stores nothing in your visitors' browsers, never keeps their IP address and never sends their data to another company.

## Two tracking modes

**Strict mode** is the default, and it works without a consent banner. Minilytics recognizes a visitor with an anonymous code that changes every month, and keeps nothing on their device. The only downside is that a visitor who changes networks, for example from Wi-Fi to mobile data, is counted twice.

**Enriched mode** is more precise, but it needs your visitors' consent. It only starts once a visitor accepts analytics in your consent banner, then keeps a temporary identifier in their browser so that a visit stays one visit even when their network changes. The identifier is deleted when the tab is closed or after 30 minutes without activity, so nobody can be followed over time. Browsers that ask not to be tracked (Do Not Track or Global Privacy Control) are never tracked in this mode.

Choose strict mode if you do not want a consent banner, and enriched mode if you already have one.

In both modes, the end of page addresses, after a `?` or a `#`, is removed because it can contain personal information. Campaign tags such as `utm_source` are kept.

## Turning on enriched mode

In the tracking snippet, replace `data-privacy-mode="strict"` with `data-privacy-mode="enriched"`. Then, in your consent banner, call `minilytics.consent()` when the visitor accepts, and `minilytics.withdrawConsent()` if they change their mind.

```html
<script
  defer
  src="https://analytics.example.com/minilytics.js"
  data-site-id="my_site"
  data-site-key="your-private-key"
  data-privacy-mode="enriched"
></script>
<script>
  // When the visitor accepts analytics
  minilytics.consent();

  // When the visitor withdraws their consent
  minilytics.withdrawConsent();
</script>
```

If your page already knows that the visitor accepted, add `data-consent="granted"` to the snippet instead of calling `minilytics.consent()`.

Your consent banner remembers the visitor's choice. Minilytics does not store it.

## Letting visitors opt out

You can offer visitors a way to stop being counted, for example with a link in your privacy policy. Call `minilytics.optOut()` to stop counting them and `minilytics.optIn()` to start again. The choice applies to the browser they use.

```js
minilytics.optOut();
minilytics.optIn();
```

## Visitor location

Minilytics shows the country, region and city of your visitors. It finds them from the visitor's IP address, without storing that address and without asking another service. If your website already runs behind a service that provides the location, such as Cloudflare, Minilytics uses it instead. Locations are approximate, especially cities.

The location comes from [DB-IP City Lite](https://db-ip.com/db/download/ip-to-city-lite), a free database published under the CC BY 4.0 license. Your server downloads it from [jsDelivr](https://www.jsdelivr.com/package/npm/dbip-city-lite) the first time it is needed, then once a month. No visitor information is ever sent with this download.

## Website settings

In **Settings → Tracking**, administrators choose which domains can send data, ignore their own visits by IP address, decide how long data is kept, and can change the website's tracking key.

![Settings → Tracking, with the allowed domains, ignored IP addresses and data retention](../assets/settings-tracking.png)
